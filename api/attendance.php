<?php
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');
$action = $_GET['action'] ?? '';
$db = getDB();

if ($action === 'checkin') {
    $input = json_decode(file_get_contents('php://input'), true);
    $memberCode = trim($input['member_code'] ?? '');
    $method = $input['method'] ?? 'manual';

    if (empty($memberCode)) {
        jsonResponse(false, [], 'Member code or ID required', 400);
    }

    $stmt = $db->prepare("
        SELECT m.*, s.end_date, s.status as sub_status 
        FROM members m 
        LEFT JOIN member_subscriptions s ON m.id = s.member_id AND s.status = 'active'
        WHERE m.member_code = ? OR m.biometric_id = ? OR m.phone = ?
    ");
    $stmt->execute([$memberCode, $memberCode, $memberCode]);
    $member = $stmt->fetch();

    if (!$member) {
        jsonResponse(false, [], 'Member not found', 444);
    }

    $isExpired = false;
    if (!$member['end_date'] || strtotime($member['end_date']) < strtotime(date('Y-m-d'))) {
        $isExpired = true;
    }

    $status = $isExpired ? 'expired_alert' : 'success';
    $notes = $isExpired ? 'Membership Expired' : 'Check-in Approved';

    // Record Attendance
    $stmtLog = $db->prepare("
        INSERT INTO attendance (member_id, check_in_time, verification_method, status, notes)
        VALUES (?, NOW(), ?, ?, ?)
    ");
    $stmtLog->execute([$member['id'], $method, $status, $notes]);

    jsonResponse(true, [
        'member' => $member,
        'is_expired' => $isExpired,
        'check_in_time' => date('h:i A'),
        'status' => $status
    ], $isExpired ? 'Membership Expired Alert!' : 'Check-in Successful');

} elseif ($action === 'logs') {
    $stmt = $db->query("
        SELECT a.*, m.name as member_name, m.member_code, m.photo_url 
        FROM attendance a 
        JOIN members m ON a.member_id = m.id 
        ORDER BY a.id DESC LIMIT 30
    ");
    jsonResponse(true, $stmt->fetchAll());

} elseif ($action === 'biometric_webhook') {
    // Biometric Hardware Machine API simulation
    $biometricId = trim($_REQUEST['bio_id'] ?? '');
    if (empty($biometricId)) {
        jsonResponse(false, [], 'Missing bio_id', 400);
    }

    $stmt = $db->prepare("SELECT * FROM members WHERE biometric_id = ?");
    $stmt->execute([$biometricId]);
    $member = $stmt->fetch();

    if ($member) {
        $db->prepare("INSERT INTO attendance (member_id, check_in_time, verification_method, status) VALUES (?, NOW(), 'biometric', 'success')")
           ->execute([$member['id']]);
        jsonResponse(true, ['member_name' => $member['name']], 'Access Granted');
    } else {
        jsonResponse(false, [], 'Biometric ID Not Registered', 404);
    }
} else {
    jsonResponse(false, [], 'Invalid action', 400);
}
