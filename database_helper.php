<?php
class DatabaseHelper {
    private $conn;
    
    public function __construct($conn) {
        $this->conn = $conn;
    }
    
    /**
     * Soft delete a user (move to deleted_users table)
     */
    public function softDeleteUser($user_id, $deleted_by, $deleted_by_role, $reason = null) {
        try {
            // Start transaction
            $this->conn->beginTransaction();
            
            // Get user data
            $stmt = $this->conn->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();
            
            if (!$user) {
                throw new Exception("User not found");
            }
            
            // Insert into deleted_users table
            $insert_stmt = $this->conn->prepare("
                INSERT INTO deleted_users 
                (original_id, name, email, password, role, status, last_login, created_at, updated_at, 
                 deleted_by, deleted_by_role, deletion_reason)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $insert_stmt->execute([
                $user['id'],
                $user['name'],
                $user['email'],
                $user['password'],
                $user['role'],
                $user['status'],
                $user['last_login'],
                $user['created_at'],
                $user['updated_at'],
                $deleted_by,
                $deleted_by_role,
                $reason
            ]);
            
            // Delete from original table
            $delete_stmt = $this->conn->prepare("DELETE FROM users WHERE id = ?");
            $delete_stmt->execute([$user_id]);
            
            $this->conn->commit();
            return true;
            
        } catch (Exception $e) {
            $this->conn->rollBack();
            throw $e;
        }
    }
    
    /**
     * Soft delete a client (move to deleted_clients table)
     */
    public function softDeleteClient($client_id, $deleted_by, $deleted_by_role, $reason = null) {
        try {
            // Start transaction
            $this->conn->beginTransaction();
            
            // Get client data
            $stmt = $this->conn->prepare("SELECT * FROM clients WHERE id = ?");
            $stmt->execute([$client_id]);
            $client = $stmt->fetch();
            
            if (!$client) {
                throw new Exception("Client not found");
            }
            
            // Insert into deleted_clients table
            $insert_stmt = $this->conn->prepare("
                INSERT INTO deleted_clients 
                (original_id, user_id, staff_id, name, email, phone, address, city, state, zip_code, 
                 age, date_of_birth, gender, weight, height, waist_circumference, hip_circumference, 
                 health_conditions, dietary_restrictions, goals, notes, status, created_at, updated_at,
                 deleted_by, deleted_by_role, deletion_reason)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $insert_stmt->execute([
                $client['id'],
                $client['user_id'],
                $client['staff_id'],
                $client['name'],
                $client['email'],
                $client['phone'],
                $client['address'],
                $client['city'],
                $client['state'],
                $client['zip_code'],
                $client['age'],
                $client['date_of_birth'],
                $client['gender'],
                $client['weight'],
                $client['height'],
                $client['waist_circumference'],
                $client['hip_circumference'],
                $client['health_conditions'],
                $client['dietary_restrictions'],
                $client['goals'],
                $client['notes'],
                $client['status'],
                $client['created_at'],
                $client['updated_at'],
                $deleted_by,
                $deleted_by_role,
                $reason
            ]);
            
            // Delete from original table
            $delete_stmt = $this->conn->prepare("DELETE FROM clients WHERE id = ?");
            $delete_stmt->execute([$client_id]);
            
            $this->conn->commit();
            return true;
            
        } catch (Exception $e) {
            $this->conn->rollBack();
            throw $e;
        }
    }
    
    /**
     * Restore a deleted user
     */
    public function restoreUser($deleted_user_id, $restored_by) {
        try {
            $this->conn->beginTransaction();
            
            // Get deleted user data
            $stmt = $this->conn->prepare("SELECT * FROM deleted_users WHERE id = ? AND is_restored = 0");
            $stmt->execute([$deleted_user_id]);
            $deleted_user = $stmt->fetch();
            
            if (!$deleted_user) {
                throw new Exception("Deleted user not found or already restored");
            }
            
            // Check if email already exists in users table
            $check_stmt = $this->conn->prepare("SELECT id FROM users WHERE email = ?");
            $check_stmt->execute([$deleted_user['email']]);
            if ($check_stmt->fetch()) {
                throw new Exception("A user with this email already exists");
            }
            
            // Insert back into users table
            $insert_stmt = $this->conn->prepare("
                INSERT INTO users (name, email, password, role, status, last_login, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $insert_stmt->execute([
                $deleted_user['name'],
                $deleted_user['email'],
                $deleted_user['password'],
                $deleted_user['role'],
                $deleted_user['status'],
                $deleted_user['last_login'],
                $deleted_user['created_at']
            ]);
            
            $new_user_id = $this->conn->lastInsertId();
            
            // Mark as restored in deleted_users table
            $update_stmt = $this->conn->prepare("
                UPDATE deleted_users 
                SET is_restored = 1, restored_at = NOW(), restored_by = ? 
                WHERE id = ?
            ");
            $update_stmt->execute([$restored_by, $deleted_user_id]);
            
            $this->conn->commit();
            return $new_user_id;
            
        } catch (Exception $e) {
            $this->conn->rollBack();
            throw $e;
        }
    }
    
    /**
     * Restore a deleted client
     */
    public function restoreClient($deleted_client_id, $restored_by) {
        try {
            $this->conn->beginTransaction();
            
            // Get deleted client data
            $stmt = $this->conn->prepare("SELECT * FROM deleted_clients WHERE id = ? AND is_restored = 0");
            $stmt->execute([$deleted_client_id]);
            $deleted_client = $stmt->fetch();
            
            if (!$deleted_client) {
                throw new Exception("Deleted client not found or already restored");
            }
            
            // Check if staff_id still exists
            $staff_id = $deleted_client['staff_id'];
            if ($staff_id) {
                $check_staff = $this->conn->prepare("SELECT id FROM users WHERE id = ? AND role = 'staff'");
                $check_staff->execute([$staff_id]);
                if (!$check_staff->fetch()) {
                    $staff_id = null; // Staff no longer exists
                }
            }
            
            // Check if email already exists in clients table
            $check_stmt = $this->conn->prepare("SELECT id FROM clients WHERE email = ?");
            $check_stmt->execute([$deleted_client['email']]);
            if ($check_stmt->fetch()) {
                throw new Exception("A client with this email already exists");
            }
            
            // Insert back into clients table
            $insert_stmt = $this->conn->prepare("
                INSERT INTO clients 
                (user_id, staff_id, name, email, phone, address, city, state, zip_code, 
                 age, date_of_birth, gender, weight, height, waist_circumference, hip_circumference, 
                 health_conditions, dietary_restrictions, goals, notes, status, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $insert_stmt->execute([
                $deleted_client['user_id'],
                $staff_id,
                $deleted_client['name'],
                $deleted_client['email'],
                $deleted_client['phone'],
                $deleted_client['address'],
                $deleted_client['city'],
                $deleted_client['state'],
                $deleted_client['zip_code'],
                $deleted_client['age'],
                $deleted_client['date_of_birth'],
                $deleted_client['gender'],
                $deleted_client['weight'],
                $deleted_client['height'],
                $deleted_client['waist_circumference'],
                $deleted_client['hip_circumference'],
                $deleted_client['health_conditions'],
                $deleted_client['dietary_restrictions'],
                $deleted_client['goals'],
                $deleted_client['notes'],
                $deleted_client['status'],
                $deleted_client['created_at']
            ]);
            
            $new_client_id = $this->conn->lastInsertId();
            
            // Mark as restored in deleted_clients table
            $update_stmt = $this->conn->prepare("
                UPDATE deleted_clients 
                SET is_restored = 1, restored_at = NOW(), restored_by = ? 
                WHERE id = ?
            ");
            $update_stmt->execute([$restored_by, $deleted_client_id]);
            
            $this->conn->commit();
            return $new_client_id;
            
        } catch (Exception $e) {
            $this->conn->rollBack();
            throw $e;
        }
    }
    
    /**
     * Get deleted users history
     */
    public function getDeletedUsers($limit = 50) {
        $stmt = $this->conn->prepare("
            SELECT du.*, 
                   u1.name as deleted_by_name, 
                   u2.name as restored_by_name
            FROM deleted_users du
            LEFT JOIN users u1 ON du.deleted_by = u1.id
            LEFT JOIN users u2 ON du.restored_by = u2.id
            ORDER BY du.deleted_at DESC
            LIMIT ?
        ");
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    /**
     * Get deleted clients history
     */
    public function getDeletedClients($limit = 50) {
        $stmt = $this->conn->prepare("
            SELECT dc.*, 
                   u1.name as deleted_by_name, 
                   u2.name as restored_by_name,
                   u3.name as staff_name
            FROM deleted_clients dc
            LEFT JOIN users u1 ON dc.deleted_by = u1.id
            LEFT JOIN users u2 ON dc.restored_by = u2.id
            LEFT JOIN users u3 ON dc.staff_id = u3.id
            ORDER BY dc.deleted_at DESC
            LIMIT ?
        ");
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    /**
     * Permanently delete a record from deleted table
     */
    public function permanentlyDeleteUser($deleted_user_id) {
        $stmt = $this->conn->prepare("DELETE FROM deleted_users WHERE id = ?");
        return $stmt->execute([$deleted_user_id]);
    }
    
    public function permanentlyDeleteClient($deleted_client_id) {
        $stmt = $this->conn->prepare("DELETE FROM deleted_clients WHERE id = ?");
        return $stmt->execute([$deleted_client_id]);
    }
}
?>