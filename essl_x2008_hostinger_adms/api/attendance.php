<?php
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');

$token = $_SERVER['HTTP_X_API_KEY'] ?? '';
$expected = 'CHANGE_THIS_API_KEY';

if (!hash_equals($expected, $token)) {
    http_response_code(401);
    echo json_encode(['ok'=>false,'error'=>'Unauthorized']);
    exit;
}

$from = $_GET['from'] ?? date('Y-m-d 00:00:00');
$to   = $_GET['to'] ?? date('Y-m-d 23:59:59');
$pin  = trim($_GET['pin'] ?? '');

$sql = "SELECT id,device_serial,employee_pin,punch_time,status,verify_mode,work_code
        FROM essl_attendance
        WHERE punch_time BETWEEN ? AND ?";
$params = [$from,$to];

if ($pin !== '') {
    $sql .= " AND employee_pin=?";
    $params[] = $pin;
}
$sql .= " ORDER BY punch_time ASC";

$stmt = db()->prepare($sql);
$stmt->execute($params);

echo json_encode([
    'ok'=>true,
    'from'=>$from,
    'to'=>$to,
    'data'=>$stmt->fetchAll()
], JSON_UNESCAPED_SLASHES);
