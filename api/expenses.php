<?php
/**
 * Operational Expenses Management & Tracking API
 */
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');
$action = $_GET['action'] ?? $_POST['action'] ?? '';
$db = getDB();

if ($action === 'list') {
    $stmt = $db->query("SELECT * FROM expenses ORDER BY expense_date DESC");
    jsonResponse(true, $stmt->fetchAll(), 'Expenses fetched');

} elseif ($action === 'add') {
    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $category = $input['category'] ?? 'Other';
    $amount = floatval($input['amount'] ?? 0);
    $date = $input['expense_date'] ?? date('Y-m-d');
    $vendor = trim($input['vendor_name'] ?? '');
    $mode = $input['payment_mode'] ?? 'Bank Transfer';
    $notes = trim($input['notes'] ?? '');

    if ($amount <= 0) {
        jsonResponse(false, [], 'Valid expense amount required', 400);
    }

    $stmt = $db->prepare("INSERT INTO expenses (category, amount, expense_date, vendor_name, payment_mode, notes) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$category, $amount, $date, $vendor, $mode, $notes]);
    $newId = $db->lastInsertId();

    logAuditAction(1, 'Log Expense', 'EXPENSES', null, ['category' => $category, 'amount' => $amount, 'vendor' => $vendor]);
    jsonResponse(true, ['id' => $newId], 'Expense entry recorded successfully!');

} elseif ($action === 'edit') {
    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $id = intval($input['id'] ?? 0);
    $category = $input['category'] ?? 'Other';
    $amount = floatval($input['amount'] ?? 0);
    $date = $input['expense_date'] ?? date('Y-m-d');
    $vendor = trim($input['vendor_name'] ?? '');
    $mode = $input['payment_mode'] ?? 'Bank Transfer';
    $notes = trim($input['notes'] ?? '');

    if ($id <= 0 || $amount <= 0) {
        jsonResponse(false, [], 'Valid expense ID and amount required', 400);
    }

    $stmt = $db->prepare("UPDATE expenses SET category = ?, amount = ?, expense_date = ?, vendor_name = ?, payment_mode = ?, notes = ? WHERE id = ?");
    $stmt->execute([$category, $amount, $date, $vendor, $mode, $notes, $id]);

    logAuditAction(1, 'Edit Expense', 'EXPENSES', ['id' => $id], ['category' => $category, 'amount' => $amount]);
    jsonResponse(true, ['id' => $id], 'Expense record updated successfully!');

} elseif ($action === 'delete') {
    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $id = intval($input['id'] ?? $_GET['id'] ?? 0);

    if ($id <= 0) {
        jsonResponse(false, [], 'Valid expense ID required', 400);
    }

    $stmt = $db->prepare("DELETE FROM expenses WHERE id = ?");
    $stmt->execute([$id]);

    logAuditAction(1, 'Delete Expense', 'EXPENSES', ['id' => $id], null);
    jsonResponse(true, ['id' => $id], 'Expense record deleted successfully!');

} else {
    jsonResponse(false, [], 'Invalid action', 400);
}
