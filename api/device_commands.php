<?php
/**
 * Remote Device Commands API for eSSL X2008 & ZKTeco Terminals
 * Allows web admin to trigger:
 *   - Face Enrollment on Device
 *   - Fingerprint Enrollment on Device
 *   - User Info Push (Name & ID)
 *   - Remote Device Reboot
 *   - Reset Transaction Stamp
 *   - Clear Device Logs
 */
require_once __DIR__ . '/../config/database.php';
header('Content-Type: application/json');

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$db = getDB();

if ($action === 'enroll_face') {
    $pin = trim($_POST['pin'] ?? $_GET['pin'] ?? '');
    $serial = trim($_POST['device_serial'] ?? $_GET['device_serial'] ?? 'NYU7262500437');

    if (empty($pin)) {
        jsonResponse(false, [], 'User / Biometric ID required', 400);
    }

    $cmd = "ENROLL_FACE PIN={$pin}";
    $stmt = $db->prepare("INSERT INTO device_commands (device_serial, command_text, status) VALUES (?, ?, 'pending')");
    $stmt->execute([$serial, $cmd]);
    $cmdId = $db->lastInsertId();

    jsonResponse(true, ['command_id' => $cmdId, 'command' => $cmd], "Face Enrollment command queued for User #{$pin}. Please ask member to look into device camera!");

} elseif ($action === 'enroll_fp') {
    $pin = trim($_POST['pin'] ?? $_GET['pin'] ?? '');
    $serial = trim($_POST['device_serial'] ?? $_GET['device_serial'] ?? 'NYU7262500437');

    if (empty($pin)) {
        jsonResponse(false, [], 'User / Biometric ID required', 400);
    }

    $cmd = "ENROLL_FP PIN={$pin}\tFID=0\tRetry=3\tOverwrite=1";
    $stmt = $db->prepare("INSERT INTO device_commands (device_serial, command_text, status) VALUES (?, ?, 'pending')");
    $stmt->execute([$serial, $cmd]);
    $cmdId = $db->lastInsertId();

    jsonResponse(true, ['command_id' => $cmdId, 'command' => $cmd], "Fingerprint Enrollment command queued for User #{$pin}. Please ask member to place finger on scanner!");

} elseif ($action === 'sync_user') {
    $pin = trim($_POST['pin'] ?? $_GET['pin'] ?? '');
    $name = trim($_POST['name'] ?? $_GET['name'] ?? 'Member');
    $serial = trim($_POST['device_serial'] ?? $_GET['device_serial'] ?? 'NYU7262500437');

    if (empty($pin)) {
        jsonResponse(false, [], 'User / Biometric ID required', 400);
    }

    // Clean name (alphanumeric and spaces only for device firmware)
    $cleanName = substr(preg_replace('/[^a-zA-Z0-9 ]/', '', $name), 0, 24);
    $cmd = "DATA UPDATE USERINFO PIN={$pin}\tName={$cleanName}\tPri=0\tPasswd=\tCard=\tGrp=1\tTZ=1";
    $stmt = $db->prepare("INSERT INTO device_commands (device_serial, command_text, status) VALUES (?, ?, 'pending')");
    $stmt->execute([$serial, $cmd]);
    $cmdId = $db->lastInsertId();

    jsonResponse(true, ['command_id' => $cmdId], "User {$cleanName} (ID: {$pin}) queued for sync to device {$serial}");

} elseif ($action === 'reboot') {
    $serial = trim($_POST['device_serial'] ?? $_GET['device_serial'] ?? 'NYU7262500437');
    $stmt = $db->prepare("INSERT INTO device_commands (device_serial, command_text, status) VALUES (?, 'REBOOT', 'pending')");
    $stmt->execute([$serial]);
    jsonResponse(true, ['command_id' => $db->lastInsertId()], "Reboot command sent to device {$serial}");

} elseif ($action === 'reset_stamp') {
    $serial = trim($_POST['device_serial'] ?? $_GET['device_serial'] ?? 'NYU7262500437');
    $stmt = $db->prepare("INSERT INTO device_commands (device_serial, command_text, status) VALUES (?, 'SET OPTION Stamp=0', 'pending')");
    $stmt->execute([$serial]);
    jsonResponse(true, ['command_id' => $db->lastInsertId()], "Reset Stamp command sent. Device will push all punch history.");

} elseif ($action === 'list_commands') {
    $serial = trim($_GET['device_serial'] ?? '');
    $where = !empty($serial) ? "WHERE device_serial = " . $db->quote($serial) : "";
    $rows = $db->query("SELECT * FROM device_commands {$where} ORDER BY id DESC LIMIT 30")->fetchAll();
    jsonResponse(true, $rows);

} else {
    jsonResponse(false, [], 'Invalid action. Supported: enroll_face, enroll_fp, sync_user, reboot, reset_stamp, list_commands', 400);
}
