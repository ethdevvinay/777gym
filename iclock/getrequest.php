<?php
/**
 * eSSL / ZKTeco ADMS Device Command & Heartbeat Poller
 * Handles GET /iclock/getrequest?SN=...
 */
date_default_timezone_set('Asia/Kolkata');
require_once __DIR__ . '/../config/database.php';

header('Content-Type: text/plain; charset=utf-8');
header('Connection: close');

@file_put_contents(__DIR__ . '/adms_debug.log', date('Y-m-d H:i:s') . ' [GET-HEARTBEAT] ' . ($_SERVER['REQUEST_URI'] ?? '') . "\n", FILE_APPEND);

$sn = trim($_GET['SN'] ?? $_GET['sn'] ?? '');

if (!empty($sn)) {
    try {
        $db = getDB();
        $db->prepare("UPDATE devices SET status = 'ONLINE', last_sync = NOW() WHERE serial_no = ?")->execute([$sn]);
    } catch (Exception $e) {}
}

// Return OK to signal idle heartbeat (no pending remote commands)
echo "OK";
