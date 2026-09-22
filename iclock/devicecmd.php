<?php
/**
 * eSSL / ZKTeco ADMS Device Command Response Handler
 * Handles POST /iclock/devicecmd?SN=...
 */
date_default_timezone_set('Asia/Kolkata');
require_once __DIR__ . '/../config/database.php';

header('Content-Type: text/plain; charset=utf-8');
header('Connection: close');

$sn = trim($_GET['SN'] ?? $_GET['sn'] ?? '');

if (!empty($sn)) {
    try {
        $db = getDB();
        $db->prepare("UPDATE devices SET status = 'ONLINE', last_sync = NOW() WHERE serial_no = ?")->execute([$sn]);
    } catch (Exception $e) {}
}

echo "OK";
