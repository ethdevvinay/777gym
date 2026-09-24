<?php
/**
 * eSSL / ZKTeco ADMS (iClock / Push Protocol) Gateway
 * 
 * Handles:
 *   1. GET  /iclock/cdata?SN=...&options=all       -> Handshake / Initialization options
 *   2. POST /iclock/cdata?SN=...&table=ATTLOG     -> Live Attendance punches (Face, Fingerprint, RFID, Password)
 *   3. POST /iclock/cdata?SN=...&table=OPERLOG    -> Device operation logs
 *   4. POST /iclock/cdata?SN=...&table=BIOPHOTO   -> Device capture photo
 * 
 * Compatible with eSSL Smart Terminals, SilkBio, MB series, Horus, and all ZKTeco ADMS devices.
 */

// Disable output buffering and set execution time
@ini_set('max_execution_time', '30');
date_default_timezone_set('Asia/Kolkata');

require_once __DIR__ . '/../config/database.php';

// ADMS devices expect text/plain responses
header('Content-Type: text/plain; charset=utf-8');
header('Connection: close');

// Debug Logger: Log every machine hit
$rawInput = file_get_contents('php://input');
@file_put_contents(__DIR__ . '/adms_debug.log', date('Y-m-d H:i:s') . ' [' . ($_SERVER['REQUEST_METHOD'] ?? 'GET') . '] ' . ($_SERVER['REQUEST_URI'] ?? '') . ' - Body: ' . trim($rawInput) . "\n", FILE_APPEND);

$db = getDB();

// ── 1. Extract Query Parameters ──────────────────────────────────────────────
$sn        = trim($_GET['SN'] ?? $_GET['sn'] ?? '');
$table     = strtoupper(trim($_GET['table'] ?? ''));
$options   = trim($_GET['options'] ?? '');
$pushver   = trim($_GET['pushver'] ?? '');
$method    = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// ── 2. Update Device Status / Auto-Register ──────────────────────────────────
$deviceId = 1;
if (!empty($sn)) {
    try {
        $devStmt = $db->prepare("SELECT id, name FROM devices WHERE serial_no = ? LIMIT 1");
        $devStmt->execute([$sn]);
        $device = $devStmt->fetch();

        if ($device) {
            $deviceId = $device['id'];
            $db->prepare("UPDATE devices SET status = 'ONLINE', last_sync = NOW() WHERE id = ?")->execute([$deviceId]);
        } else {
            // Auto-register newly connected eSSL machine
            $insDev = $db->prepare("INSERT INTO devices (name, ip_address, port, serial_no, manufacturer, device_type, location, status, last_sync) 
                                    VALUES (?, ?, 80, ?, 'eSSL', 'face_fingerprint', 'Gym Main Gate', 'ONLINE', NOW())");
            $remoteIp = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
            $insDev->execute(["eSSL Terminal ({$sn})", $remoteIp, $sn]);
            $deviceId = $db->lastInsertId();
        }
    } catch (Exception $e) {
        // Continue gracefully
    }
}

// ── 3. Helper: Send WhatsApp Notification Fast ───────────────────────────────
function sendFastWhatsApp(string $phone, string $message): void {
    if (empty($phone)) return;
    $nodeUrl = getSetting('whatsapp_node_url', 'http://127.0.0.1:3001');
    $cleanPhone = preg_replace('/[^0-9]/', '', $phone);
    if (strlen($cleanPhone) === 10) $cleanPhone = '91' . $cleanPhone;

    $ch = curl_init(rtrim($nodeUrl, '/') . '/send');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'phone' => $cleanPhone,
        'message' => $message
    ]));
    curl_setopt($ch, CURLOPT_TIMEOUT, 2); // 2 sec max so we never block machine response
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 1);
    @curl_exec($ch);
    if (PHP_VERSION_ID < 80500 && function_exists('curl_close')) {
        @curl_close($ch);
    }
}

// ── 4. HANDSHAKE / INITIALIZATION (GET /iclock/cdata) ────────────────────────
if ($method === 'GET') {
    // If device is requesting server config/options
    if (!empty($options) || !empty($sn)) {
        // Standard ADMS initialization handshake response
        $response  = "GET_OPTION_FROM: {$sn}\n";
        $response .= "Stamp=0\n"; // 0 tells machine to push all pending punches
        $response .= "OpStamp=0\n";
        $response .= "PhotoStamp=0\n";
        $response .= "ErrorDelay=30\n";
        $response .= "Delay=5\n"; // Check every 5 seconds for fast response
        $response .= "TransTimes=00:00;14:05\n";
        $response .= "TransInterval=1\n"; // Push instantly in real time
        $response .= "TransFlag=TransData AttLog\tOpLog\tAttPhoto\tEnrollFP\tEnrollUser\n";
        $response .= "Realtime=1\n"; // Enable instant punch push
        $response .= "Encrypt=0\n";
        $response .= "ServerVersion=3.1.1\n";
        
        echo $response;
        exit;
    }

    echo "OK";
    exit;
}

// ── 5. PUNCH & DATA LOGS (POST /iclock/cdata) ─────────────────────────────────
if ($method === 'POST') {
    $rawInput = file_get_contents('php://input');

    // If Operation Log or other administrative device log
    if ($table === 'OPERLOG' || strpos($rawInput, 'OPLOG') !== false) {
        echo "OK";
        exit;
    }

    // Process Attendance Logs (ATTLOG)
    $processedCount = 0;

    if (!empty($rawInput)) {
        // Split by lines
        $lines = preg_split('/[\r\n]+/', trim($rawInput));
        $dupWindow = intval(getSetting('duplicate_attendance_window_mins', '5'));
        $gymName = getSetting('gym_name', 'THE CLUB 777®');

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;

            // Skip header lines like "PIN\tTime\t..."
            if (stripos($line, 'PIN') !== false || stripos($line, 'USER') !== false) {
                continue;
            }

            // eSSL / ZKTeco ATTLOG formats:
            // Format 1 (tab-separated): <PIN>\t<DateTime>\t<Status>\t<VerifyType>\t<WorkCode>\t<Reserved>
            // Format 2 (space-separated): <PIN> <DateTime> <Status> <VerifyType>
            $parts = preg_split('/\t+|\s{2,}/', $line);
            if (count($parts) < 2) {
                $parts = explode(' ', $line);
            }

            if (empty($parts[0])) continue;

            $pin = trim($parts[0]);

            // Combine date & time if separated
            $punchTime = date('Y-m-d H:i:s');
            if (isset($parts[1]) && isset($parts[2]) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $parts[1]) && preg_match('/^\d{2}:\d{2}/', $parts[2])) {
                $punchTime = $parts[1] . ' ' . $parts[2];
                $verifyCode = $parts[4] ?? ($parts[3] ?? 1);
            } elseif (isset($parts[1]) && strtotime($parts[1]) !== false) {
                $punchTime = date('Y-m-d H:i:s', strtotime($parts[1]));
                $verifyCode = $parts[3] ?? ($parts[2] ?? 1);
            } else {
                $verifyCode = 1;
            }

            // Timezone Drift Safeguard:
            // If machine clock is set to UTC or drifted > 45 minutes, use current IST time
            // so today's live punches never get filed under yesterday.
            $nowTs = time();
            $parsedTs = strtotime($punchTime);
            if (!$parsedTs || abs($nowTs - $parsedTs) > 2700) {
                $punchTime = date('Y-m-d H:i:s', $nowTs);
            }

            // Map verify code to method (15=Face, 1=Fingerprint, 4=Card, 2=Password)
            $verifyMethod = 'biometric';
            if ($verifyCode == 15 || $verifyCode == 'face') {
                $verifyMethod = 'face';
            } elseif ($verifyCode == 4 || $verifyCode == 3) {
                $verifyMethod = 'rfid';
            }

            // ── SMART PIN RESOLUTION (Handles 101, STF-101, BIO-101, M-101, 00101) ──
            $cleanPin = preg_replace('/^(STF|BIO|MEM|M)[\-_]?/i', '', $pin);
            $pinVariants = [$pin];
            if (!empty($cleanPin)) {
                $pinVariants[] = $cleanPin;
                $pinVariants[] = 'BIO-' . $cleanPin;
                $pinVariants[] = 'STF-' . $cleanPin;
                $pinVariants[] = 'M-' . $cleanPin;
                $ltrimPin = ltrim($cleanPin, '0');
                if (!empty($ltrimPin)) {
                    $pinVariants[] = $ltrimPin;
                    $pinVariants[] = 'BIO-' . $ltrimPin;
                    $pinVariants[] = 'STF-' . $ltrimPin;
                    $pinVariants[] = 'M-' . $ltrimPin;
                }
            }
            $pinVariants = array_values(array_unique(array_filter($pinVariants)));
            $inPlaceholders = implode(',', array_fill(0, count($pinVariants), '?'));
            $numericPin = intval($cleanPin);

            // ── A) CHECK IF PIN MATCHES GYM MEMBER FIRST ────────────────────
            $memSql = "
                SELECT * FROM members 
                WHERE biometric_id IN ({$inPlaceholders}) 
                   OR member_code IN ({$inPlaceholders}) 
                   OR phone = ? 
                   OR (id = ? AND (biometric_id IS NULL OR biometric_id = '' OR biometric_id = ?))
                LIMIT 1
            ";
            $memStmt = $db->prepare($memSql);
            $memParams = array_merge($pinVariants, $pinVariants, [$pin, $numericPin, $pin]);
            $memStmt->execute($memParams);
            $member = $memStmt->fetch();

            if (!$member) {
                // ── B) CHECK IF PIN MATCHES STAFF ────────────────────────────
                $staffSql = "
                    SELECT s.*, sh.name as shift_name, sh.start_time as shift_start, sh.end_time as shift_end,
                           sh.grace_period_mins, sh.half_day_threshold_mins 
                    FROM staff s 
                    LEFT JOIN staff_shifts sh ON s.shift_id = sh.id 
                    WHERE s.biometric_id IN ({$inPlaceholders}) 
                       OR s.phone = ?
                    LIMIT 1
                ";
                $staffStmt = $db->prepare($staffSql);
                $staffParams = array_merge($pinVariants, [$pin]);
                $staffStmt->execute($staffParams);
                $staff = $staffStmt->fetch();

                if ($staff) {
                    $today   = date('Y-m-d', strtotime($punchTime));
                    $shiftId = $staff['shift_id'] ?: 1;
                    $shiftStart = $staff['shift_start'] ?: '05:00:00';

                    $attStmt = $db->prepare("SELECT * FROM staff_attendance WHERE staff_id = ? AND date = ? ORDER BY id DESC LIMIT 1");
                    $attStmt->execute([$staff['id'], $today]);
                    $existingAtt = $attStmt->fetch();

                    if (!$existingAtt) {
                        // Staff First Punch = CHECK-IN
                        $shiftStartTs = strtotime("{$today} {$shiftStart}");
                        $graceMins    = intval($staff['grace_period_mins'] ?? 15);
                        $graceTs      = $shiftStartTs + ($graceMins * 60);
                        $status       = 'present';
                        $lateMins     = 0;
                        $lateReason   = null;

                        $punchTs = strtotime($punchTime);
                        if ($punchTs > $graceTs) {
                            $lateMins   = max(1, (int) ceil(($punchTs - $shiftStartTs) / 60));
                            $status     = ($lateMins > intval($staff['half_day_threshold_mins'] ?? 60)) ? 'half_day' : 'late';
                            $lateReason = "Late by {$lateMins} mins (Grace: {$graceMins}m)";
                        }

                        $db->prepare("
                            INSERT INTO staff_attendance (staff_id, date, shift_id, check_in_time, status, late_minutes, late_reason, verification_method, notes) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'eSSL ADMS Gate Punch')
                        ")->execute([$staff['id'], $today, $shiftId, $punchTime, $status, $lateMins, $lateReason, $verifyMethod]);

                        $processedCount++;
                        continue;

                    } elseif (empty($existingAtt['check_out_time'])) {
                        // Staff Second Punch = CHECK-OUT
                        $workHours = round(max(0, (strtotime($punchTime) - strtotime($existingAtt['check_in_time'])) / 3600), 2);
                        $db->prepare("UPDATE staff_attendance SET check_out_time = ?, working_hours = ? WHERE id = ?")
                           ->execute([$punchTime, $workHours, $existingAtt['id']]);

                        $processedCount++;
                        continue;
                    } else {
                        $processedCount++;
                        continue;
                    }
                }
            }

            if (!$member) {
                // Log to Raw Biometric Transactions Audit
                try {
                    $db->prepare("
                        INSERT INTO biometric_transactions (device_serial, biometric_user_id, entity_type, entity_id, punch_time, verify_type, raw_payload) 
                        VALUES (?, ?, 'unknown', NULL, ?, ?, ?)
                    ")->execute([$sn, $pin, $punchTime, $verifyMethod, $line]);
                } catch (Exception $e) {}

                // Log Unregistered Punch for Admin review
                try {
                    $db->prepare("
                        INSERT INTO unregistered_punches (biometric_id, device_serial, punch_time, raw_payload) 
                        VALUES (?, ?, ?, ?)
                    ")->execute([$pin, $sn, $punchTime, $line]);
                } catch (Exception $e) {}

                $processedCount++;
                continue;
            }

            // Log to Raw Biometric Transactions Audit (Member)
            try {
                $db->prepare("
                    INSERT INTO biometric_transactions (device_serial, biometric_user_id, entity_type, entity_id, punch_time, verify_type, raw_payload) 
                    VALUES (?, ?, 'member', ?, ?, ?, ?)
                ")->execute([$sn, $pin, $member['id'], $punchTime, $verifyMethod, $line]);
            } catch (Exception $e) {}

            // ── C) DUPLICATE SCAN BUFFER CHECK ───────────────────────────────
            $dupChk = $db->prepare("SELECT id FROM attendance WHERE member_id = ? AND check_in_time >= DATE_SUB(?, INTERVAL ? MINUTE)");
            $dupChk->execute([$member['id'], $punchTime, $dupWindow]);
            if ($dupChk->fetch()) {
                // Within duplicate buffer window — ignore smoothly
                $processedCount++;
                continue;
            }

            // ── D) MEMBERSHIP EXPIRY VERIFICATION ────────────────────────────
            $subStmt = $db->prepare("
                SELECT * FROM member_subscriptions 
                WHERE member_id = ? AND status = 'active' 
                ORDER BY end_date DESC LIMIT 1
            ");
            $subStmt->execute([$member['id']]);
            $activeSub = $subStmt->fetch();

            $memberStatus = 'success';
            $memberNotes  = 'eSSL ADMS Face/Biometric Punch';
            $isExpired    = false;
            $daysLeft     = 0;

            if (!$activeSub || strtotime($activeSub['end_date']) < strtotime(date('Y-m-d', strtotime($punchTime)))) {
                $memberStatus = 'expired_alert';
                $memberNotes  = 'Membership Expired — Access Flagged';
                $isExpired    = true;
            } else {
                $daysLeft = max(0, (int) ceil((strtotime($activeSub['end_date']) - strtotime('today')) / 86400));
            }

            // ── E) TOGGLE CHECK-IN / CHECK-OUT ───────────────────────────────
            $today = date('Y-m-d', strtotime($punchTime));
            $openAttStmt = $db->prepare("
                SELECT * FROM attendance 
                WHERE member_id = ? AND DATE(check_in_time) = ? AND check_out_time IS NULL 
                ORDER BY id DESC LIMIT 1
            ");
            $openAttStmt->execute([$member['id'], $today]);
            $openAtt = $openAttStmt->fetch();

            if ($openAtt) {
                // Second punch of the day = CHECK-OUT
                $durationMins = max(1, (int) round((strtotime($punchTime) - strtotime($openAtt['check_in_time'])) / 60));
                $db->prepare("
                    UPDATE attendance 
                    SET check_out_time = ?, duration_minutes = ?, notes = CONCAT(IFNULL(notes,''), ' | Checked-Out via eSSL Terminal') 
                    WHERE id = ?
                ")->execute([$punchTime, $durationMins, $openAtt['id']]);

                // Automated WhatsApp: Checkout Workout Completed
                if (!empty($member['phone'])) {
                    $msg = "💪 *Workout Completed!*\n\nHello *{$member['name']}*,\nGreat workout session today at *{$gymName}*!\n\n⏱️ Total Gym Time: *{$durationMins} minutes*\n🚪 Checked Out: *" . date('h:i A', strtotime($punchTime)) . "*\n\nStay consistent, keep crushing your goals! 🔥";
                    sendFastWhatsApp($member['phone'], $msg);
                }

                $processedCount++;

            } else {
                // First punch of the day = CHECK-IN
                $db->prepare("
                    INSERT INTO attendance (member_id, device_id, check_in_time, verification_method, status, notes) 
                    VALUES (?, ?, ?, ?, ?, ?)
                ")->execute([$member['id'], $deviceId, $punchTime, $verifyMethod, $memberStatus, $memberNotes]);

                // Automated WhatsApp: Check-in Alert
                if (!empty($member['phone'])) {
                    if ($isExpired) {
                        $msg = "⚠️ *Membership Alert — {$gymName}*\n\nHello *{$member['name']}*,\nYour gym membership plan has *expired*.\n\nChecked-in at: *" . date('h:i A', strtotime($punchTime)) . "*\nPlease visit the reception desk to renew your membership and continue hassle-free workouts. Thank you! 🙏";
                    } else {
                        $msg = "👋 *Welcome to {$gymName}!*\n\nHello *{$member['name']}*,\nYour attendance has been recorded successfully.\n\n⏰ Time: *" . date('h:i A', strtotime($punchTime)) . "*\n📅 Plan Valid: *{$daysLeft} days remaining*\n\nHave a great workout session! 💪";
                    }
                    sendFastWhatsApp($member['phone'], $msg);
                }

                $processedCount++;
            }
        }
    }

    // Machine expects OK: {count} as acknowledgment
    echo "OK: " . max(1, $processedCount);
    exit;
}

// Fallback response
echo "OK";
