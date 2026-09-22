<?php
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');
$action = $_GET['action'] ?? $_POST['action'] ?? '';
$db = getDB();

if ($action === 'create') {
    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $catId = intval($input['category_id'] ?? 1);
    $barcode = trim($input['barcode'] ?? '');
    $name = trim($input['name'] ?? '');
    $type = $input['type'] ?? 'product';
    $price = floatval($input['price'] ?? 0);
    $stock = intval($input['stock_quantity'] ?? 0);
    $unit = trim($input['unit'] ?? 'pcs');

    if (empty($name) || $price <= 0) {
        jsonResponse(false, [], 'Product name and valid price required', 400);
    }

    if (empty($barcode)) {
        $barcode = "BC-" . rand(10000, 99999);
    }

    $stmt = $db->prepare("
        INSERT INTO products (category_id, barcode, name, type, price, stock_quantity, unit, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, 'active')
    ");
    $stmt->execute([$catId, $barcode, $name, $type, $price, $stock, $unit]);
    $prodId = $db->lastInsertId();

    jsonResponse(true, ['id' => $prodId], 'Product created successfully');

} elseif ($action === 'update') {
    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $id = intval($input['id'] ?? 0);
    $name = trim($input['name'] ?? '');
    $price = floatval($input['price'] ?? 0);
    $stock = intval($input['stock_quantity'] ?? 0);
    $status = $input['status'] ?? 'active';

    if ($id <= 0 || empty($name)) {
        jsonResponse(false, [], 'ID and Product Name required', 400);
    }

    $stmt = $db->prepare("
        UPDATE products 
        SET name = ?, price = ?, stock_quantity = ?, status = ?
        WHERE id = ?
    ");
    $stmt->execute([$name, $price, $stock, $status, $id]);

    jsonResponse(true, [], 'Product updated successfully');

} elseif ($action === 'delete') {
    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $id = intval($input['id'] ?? $_GET['id'] ?? 0);
    if ($id <= 0) {
        jsonResponse(false, [], 'Valid Product ID required', 400);
    }

    $stmt = $db->prepare("DELETE FROM products WHERE id = ?");
    $stmt->execute([$id]);

    jsonResponse(true, [], 'Product deleted successfully');

} else {
    jsonResponse(false, [], 'Invalid action', 400);
}
