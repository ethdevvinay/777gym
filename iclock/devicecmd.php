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
$rawInput = file_get_contents('php://input');

if (!empty($sn)) {
    try {
        $db = getDB();
        $db->prepare("UPDATE devices SET status = 'ONLINE', last_sync = NOW() WHERE serial_no = ?")->execute([$sn]);

        if (!empty($rawInput)) {
            // Raw input format: ID=12&Return=0&CMD=ENROLL_FACE
            parse_str(str_replace("\n", '&', $rawInput), $params);
            $cmdId = intval($params['ID'] ?? 0);
            $retCode = intval($params['Return'] ?? -1);

            if ($cmdId > 0) {
                $status = ($retCode === 0) ? 'success' : 'failed';
                
                // Get command text
                $chk = $db->prepare("SELECT command_text FROM device_commands WHERE id = ?");
                $chk->execute([$cmdId]);
                $cmdRow = $chk->fetch();

                $db->prepare("
                    UPDATE device_commands 
                    SET status = ?, completed_at = NOW(), response_log = ? 
                    WHERE id = ?
                ")->execute([$status, $rawInput, $cmdId]);

                // If enrollment succeeded, mark flag
                if ($status === 'success' && $cmdRow) {
                    $cmdText = $cmdRow['command_text'];
                    if (preg_match('/PIN=([^\s\t]+)/i', $cmdText, $m)) {
                        $pin = trim($m[1]);
                        if (stripos($cmdText, 'ENROLL_FACE') !== false) {
                            $db->prepare("UPDATE members SET face_enrolled = 1 WHERE biometric_id = ? OR id = ?")->execute([$pin, intval($pin)]);
                            $db->prepare("UPDATE staff SET face_enrolled = 1 WHERE biometric_id = ? OR id = ?")->execute([$pin, intval($pin)]);
                        } elseif (stripos($cmdText, 'ENROLL_FP') !== false) {
                            $db->prepare("UPDATE members SET finger_enrolled = 1 WHERE biometric_id = ? OR id = ?")->execute([$pin, intval($pin)]);
                            $db->prepare("UPDATE staff SET finger_enrolled = 1 WHERE biometric_id = ? OR id = ?")->execute([$pin, intval($pin)]);
                        }
                    }
                }
            }
        }
    } catch (Exception $e) {}
}

echo "OK";
