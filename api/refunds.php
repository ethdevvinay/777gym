<?php
/**
 * Cancellation & Refund Management API
 */
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');
$action = $_GET['action'] ?? $_POST['action'] ?? '';
$db = getDB();

if ($action === 'process_refund') {
    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $saleId = intval($input['sale_id'] ?? 0);
    $memberId = !empty($input['member_id']) ? intval($input['member_id']) : null;
    $amount = floatval($input['refund_amount'] ?? 0);
    $reason = trim($input['reason'] ?? 'Member Relocation / Health Issues');

    if ($saleId <= 0 || $amount <= 0) {
        jsonResponse(false, [], 'Valid sale ID and refund amount required', 400);
    }

    $stmt = $db->prepare("INSERT INTO refunds (sale_id, member_id, refund_amount, reason, approved_by) VALUES (?, ?, ?, ?, 1)");
    $stmt->execute([$saleId, $memberId, $amount, $reason]);

    if ($memberId) {
        // Update member subscription status to cancelled
        $updSub = $db->prepare("UPDATE member_subscriptions SET status = 'cancelled' WHERE member_id = ?");
        $updSub->execute([$memberId]);

        $updMem = $db->prepare("UPDATE members SET status = 'cancelled' WHERE id = ?");
        $updMem->execute([$memberId]);
    }

    logAuditAction(1, 'Process Refund & Cancel Membership', 'REFUNDS', null, ['sale_id' => $saleId, 'amount' => $amount, 'reason' => $reason]);

    jsonResponse(true, ['refund_id' => $db->lastInsertId()], "Refund of ₹{$amount} processed and membership cancelled successfully.");

} else {
    jsonResponse(false, [], 'Invalid action', 400);
}
