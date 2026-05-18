<?php
ini_set('display_errors', 0);
$session_path = 'c:\xampp\htdocs\nutrideq\api\sessions';
if (!is_dir($session_path)) { mkdir($session_path, 0777, true); }
session_save_path($session_path);
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once 'fct_helper.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$fct = new FCTHelper();
$action = $_POST['action'] ?? '';

switch ($action) {
    case 'get_details':
        $id = $_POST['id'] ?? 0;
        $details = $fct->getDetails($id);
        if ($details) {
            echo json_encode(['success' => true, 'item' => $details['item'], 'nutrients' => $details['nutrients']]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Item not found']);
        }
        break;

    case 'save':
        if (!FCTHelper::canManage($_SESSION['user_role'])) {
            echo json_encode(['success' => false, 'message' => 'Permission denied']);
            break;
        }

        // Decode nutrients and check for errors
        $nutrients = json_decode($_POST['nutrients'], true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            echo json_encode(['success' => false, 'message' => 'Invalid nutrient data format']);
            break;
        }

        $data = [
            'food_id' => $_POST['food_id'],
            'food_name' => $_POST['food_name'],
            'category' => $_POST['category']
        ];

        // We use saveItem (ensure this matches the method name in fct_helper.php)
        if ($fct->saveItem($data, $nutrients)) {
            echo json_encode(['success' => true, 'message' => 'Update successful']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Database error during save']);
        }
        break;

    case 'delete':
        if (!FCTHelper::canDelete($_SESSION['user_role'])) {
            echo json_encode(['success' => false, 'message' => 'Permission denied']);
            break;
        }
        if ($fct->deleteItem($_POST['id'])) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to delete']);
        }
        break;
}