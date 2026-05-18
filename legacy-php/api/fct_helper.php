<?php
ini_set('display_errors', 0);
// This matches your specific file structure
require_once dirname(__DIR__) . '/database.php';

class FCTHelper
{
    private $db;
    private $conn;

    public function __construct()
    {
        // Matches your original database class usage
        $this->db = new Database();
        $this->conn = $this->db->getConnection();
    }

    public static function canManage($role)
    {
        return ($role === 'admin' || $role === 'staff');
    }

    public static function canDelete($role)
    {
        return ($role === 'admin');
    }

    public function getFoodItems($page = 1, $limit = 20, $search = '', $category = '')
    {
        $offset = ($page - 1) * $limit;
        $params = [];
        $where = "WHERE 1=1";

        if (!empty($search)) {
            $where .= " AND (f.food_name LIKE :search OR f.food_id LIKE :search)";
            $params[':search'] = "%$search%";
        }

        if (!empty($category)) {
            $where .= " AND TRIM(f.category) = TRIM(:category)";
            $params[':category'] = $category;
        }

        // Get total count for pagination
        $countStmt = $this->conn->prepare("SELECT COUNT(*) FROM fct_food_items f $where");
        $countStmt->execute($params);
        $totalItems = $countStmt->fetchColumn();

        // Get items with nutrients joined
        $sql = "SELECT f.*, 
                MAX(CASE WHEN n.nutrient_name = 'Energy' THEN n.value ELSE 0 END) as calories,
                MAX(CASE WHEN n.nutrient_name = 'Protein' THEN n.value ELSE 0 END) as protein,
                MAX(CASE WHEN n.nutrient_name = 'Carbohydrate' THEN n.value ELSE 0 END) as carbs,
                MAX(CASE WHEN n.nutrient_name = 'Fat' THEN n.value ELSE 0 END) as fat
                FROM fct_food_items f
                LEFT JOIN fct_nutrients n ON f.id = n.food_item_id
                $where 
                GROUP BY f.id
                ORDER BY f.food_id ASC 
                LIMIT :limit OFFSET :offset";
        
        $stmt = $this->conn->prepare($sql);

        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val);
        }
        $stmt->bindValue(':limit', (int) $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', (int) $offset, PDO::PARAM_INT);

        $stmt->execute();
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'items' => $items,
            'pages' => ceil($totalItems / $limit)
        ];
    }

    public function getDetails($id)
    {
        $stmt = $this->conn->prepare("SELECT * FROM fct_food_items WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$item)
            return null;

        $stmtN = $this->conn->prepare("SELECT * FROM fct_nutrients WHERE food_item_id = :id");
        $stmtN->execute([':id' => $id]);
        $nutrients = $stmtN->fetchAll(PDO::FETCH_ASSOC);

        return ['item' => $item, 'nutrients' => $nutrients];
    }

    public function saveItem($data, $nutrients)
    {
        try {
            $this->conn->beginTransaction();

            $sql = "INSERT INTO fct_food_items (food_id, food_name, category) 
                    VALUES (:food_id, :food_name, :category)
                    ON DUPLICATE KEY UPDATE food_name = :food_name, category = :category";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute([
                ':food_id' => $data['food_id'],
                ':food_name' => $data['food_name'],
                ':category' => $data['category']
            ]);

            // Get the ID (either new or existing)
            $itemId = $this->conn->lastInsertId() ?: $this->getFoodIdByCode($data['food_id']);

            // Clear old nutrients to prevent duplicates
            $del = $this->conn->prepare("DELETE FROM fct_nutrients WHERE food_item_id = ?");
            $del->execute([$itemId]);

            $stmtN = $this->conn->prepare("INSERT INTO fct_nutrients (food_item_id, nutrient_name, value, unit) VALUES (?, ?, ?, ?)");
            foreach ($nutrients as $n) {
                $stmtN->execute([$itemId, $n['name'], $n['value'], $n['unit']]);
            }

            $this->conn->commit();
            return true;
        } catch (Exception $e) {
            $this->conn->rollBack();
            return false;
        }
    }

    private function getFoodIdByCode($code)
    {
        $stmt = $this->conn->prepare("SELECT id FROM fct_food_items WHERE food_id = ?");
        $stmt->execute([$code]);
        return $stmt->fetchColumn();
    }

    public function getAllItems()
    {
        $sql = "SELECT f.*, 
                MAX(CASE WHEN n.nutrient_name = 'Energy' THEN n.value ELSE 0 END) as calories,
                MAX(CASE WHEN n.nutrient_name = 'Protein' THEN n.value ELSE 0 END) as protein,
                MAX(CASE WHEN n.nutrient_name = 'Carbohydrate' THEN n.value ELSE 0 END) as carbs,
                MAX(CASE WHEN n.nutrient_name = 'Fat' THEN n.value ELSE 0 END) as fat
                FROM fct_food_items f
                LEFT JOIN fct_nutrients n ON f.id = n.food_item_id
                GROUP BY f.id
                ORDER BY f.food_id ASC";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function deleteItem($id)
    {
        $stmt = $this->conn->prepare("DELETE FROM fct_food_items WHERE id = ?");
        return $stmt->execute([$id]);
    }
}