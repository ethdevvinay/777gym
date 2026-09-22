<?php
/**
 * Security Audit Log API Controller
 */
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');
$db = getDB();

$stmt = $db->query("SELECT * FROM audit_logs ORDER BY id DESC LIMIT 100");
$logs = $stmt->fetchAll();

jsonResponse(true, $logs, 'Audit logs fetched');
