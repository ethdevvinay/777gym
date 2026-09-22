<?php
/**
 * eSSL eBioServer Web API Compatibility Service
 * Implements methods defined in eSSL-eBioserverNew Web API Manual:
 *   - GetDeviceLogs
 *   - GetEmployeePunchLogs
 *   - UpdateEmployee
 *   - GetDeviceList
 *   - GetDeviceLastPing
 *   - DeviceCommand_Reboot
 *   - DeviceCommand_ResetTransactionStamp
 *   - DeviceCommand_ClearLogs
 */
require_once __DIR__ . '/../config/database.php';
header('Content-Type: application/json; charset=utf-8');

$op = $_GET['op'] ?? $_POST['op'] ?? '';
$db = getDB();

switch ($op) {
    case 'GetDeviceList':
        $devices = $db->query("
            SELECT id, name AS DeviceName, serial_no AS DeviceSerialNumber, model AS DeviceModel, 
                   mac_address AS MacAddress, ip_address AS IpAddress, location AS Location, 
                   status AS Status, last_sync AS LastPing, firmware AS Firmware, push_version AS PushVersion
            FROM devices
        ")->fetchAll(PDO::FETCH_ASSOC);
        jsonResponse(true, ['devices' => $devices], 'Device list fetched');
        break;

    case 'GetDeviceLastPing':
        $sn = trim($_REQUEST['DeviceSerialNumber'] ?? '');
        $stmt = $db->prepare("SELECT last_sync FROM devices WHERE serial_no = ?");
        $stmt->execute([$sn]);
        $ping = $stmt->fetchColumn();
        jsonResponse(true, ['DeviceSerialNumber' => $sn, 'LastPing' => $ping ?: 'Offline']);
        break;

    case 'GetDeviceLogs':
        $date = trim($_REQUEST['LogDate'] ?? date('Y-m-d'));
        $stmt = $db->prepare("
            SELECT bt.id AS LogId, bt.punch_time AS LogDateTime, bt.biometric_user_id AS EmployeeCode, 
                   bt.verify_type AS VerificationType, d.name AS DeviceName, d.location AS Location,
                   CASE WHEN a.check_out_time IS NULL THEN 'IN' ELSE 'OUT' END AS Direction
            FROM biometric_transactions bt
            LEFT JOIN devices d ON bt.device_serial = d.serial_no
            LEFT JOIN attendance a ON bt.entity_id = a.member_id AND DATE(a.check_in_time) = DATE(bt.punch_time)
            WHERE DATE(bt.punch_time) = ?
            ORDER BY bt.id DESC LIMIT 200
        ");
        $stmt->execute([$date]);
        $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        jsonResponse(true, ['logs' => $logs, 'count' => count($logs)]);
        break;

    case 'GetEmployeePunchLogs':
        $empCode = trim($_REQUEST['EmployeeCode'] ?? '');
        $date = trim($_REQUEST['AttendanceDate'] ?? date('Y-m-d'));
        $stmt = $db->prepare("
            SELECT a.id, m.name, m.member_code, a.check_in_time AS InTime, a.check_out_time AS OutTime, 
                   a.duration_minutes AS WorkingMinutes, a.status AS Status
            FROM attendance a
            JOIN members m ON a.member_id = m.id
            WHERE (m.biometric_id = ? OR m.member_code = ? OR m.id = ?) AND DATE(a.check_in_time) = ?
        ");
        $stmt->execute([$empCode, $empCode, intval($empCode), $date]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        jsonResponse(true, ['punches' => $rows]);
        break;

    case 'UpdateEmployee':
        $empCode = trim($_REQUEST['EmployeeCode'] ?? '');
        $empName = trim($_REQUEST['EmployeeName'] ?? '');
        $serial = trim($_REQUEST['DeviceSerialNumber'] ?? 'NYU7262500437');

        if (empty($empCode) || empty($empName)) {
            jsonResponse(false, [], 'EmployeeCode and EmployeeName required', 400);
        }

        // Queue user sync to biometric device
        $cleanName = substr(preg_replace('/[^a-zA-Z0-9 ]/', '', $empName), 0, 24);
        $cmd = "DATA UPDATE USERINFO PIN={$empCode}\tName={$cleanName}\tPri=0\tPasswd=\tCard=\tGrp=1\tTZ=1";
        $db->prepare("INSERT INTO device_commands (device_serial, command_text, status) VALUES (?, ?, 'pending')")->execute([$serial, $cmd]);

        jsonResponse(true, ['status' => 'queued'], "Employee {$empName} queued to sync to device {$serial}");
        break;

    case 'DeviceCommand_ResetTransactionStamp':
        $serial = trim($_REQUEST['DeviceSerialNumber'] ?? 'NYU7262500437');
        $db->prepare("INSERT INTO device_commands (device_serial, command_text, status) VALUES (?, 'SET OPTION Stamp=0', 'pending')")->execute([$serial]);
        jsonResponse(true, [], "ResetTransactionStamp queued for device {$serial}");
        break;

    case 'DeviceCommand_Reboot':
        $serial = trim($_REQUEST['DeviceSerialNumber'] ?? 'NYU7262500437');
        $db->prepare("INSERT INTO device_commands (device_serial, command_text, status) VALUES (?, 'REBOOT', 'pending')")->execute([$serial]);
        jsonResponse(true, [], "Reboot command queued for device {$serial}");
        break;

    case 'DeviceCommand_ClearLogs':
        $serial = trim($_REQUEST['DeviceSerialNumber'] ?? 'NYU7262500437');
        $db->prepare("INSERT INTO device_commands (device_serial, command_text, status) VALUES (?, 'CLEAR LOG', 'pending')")->execute([$serial]);
        jsonResponse(true, [], "ClearLogs command queued for device {$serial}");
        break;

    default:
        jsonResponse(true, [
            'service' => 'eSSL eBioServer Web API Compatibility Gateway',
            'supported_methods' => [
                'GetDeviceList',
                'GetDeviceLastPing',
                'GetDeviceLogs',
                'GetEmployeePunchLogs',
                'UpdateEmployee',
                'DeviceCommand_ResetTransactionStamp',
                'DeviceCommand_Reboot',
                'DeviceCommand_ClearLogs'
            ]
        ], 'eBioServer Service Active');
        break;
}
