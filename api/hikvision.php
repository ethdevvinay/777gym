<?php
/**
 * Hikvision MinMoe Face Recognition Terminal Webhook & Event Listener
 * 
 * Supports:
 *   - Hikvision DS-K1T343, DS-K1T341, DS-K1T320, DS-K1T671 series
 *   - Native JSON & Multipart/form-data (JSON + Event Snapshot Image)
 *   - Automatic Member & Staff Check-in / Check-out toggle
 *   - Instant WhatsApp Notifications
 *   - Subscription Expiry Flagging
 *   - Unregistered Face capture logging
 */

date_default_timezone_set('Asia/Kolkata');
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Health check / Verification
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    echo json_encode([
        'status' => 'online',
        'service' => 'Hikvision MinMoe Event Listener',
        'server_time' => date('Y-m-d H:i:s'),
        'endpoint' => 'POST /api/hikvision.php'
    ]);
    exit;
}

$rawInput = file_get_contents('php://input');
$contentType = $_SERVER['CONTENT-TYPE'] ?? '';

// Debug log for installation verification
@file_put_contents(__DIR__ . '/hikvision_debug.log', date('Y-m-d H:i:s') . " [{$_SERVER['REQUEST_METHOD']}] CT: {$contentType} - Body: " . substr(trim($rawInput), 0, 500) . "\n", FILE_APPEND);

$data = [];

// Handle Multipart (Hikvision sends JSON part + JPEG face image part)
if (stripos($contentType, 'multipart/form-data') !== false) {
    if (isset($_POST['event_log'])) {
        $data = json_decode($_POST['event_log'], true) ?: [];
    } elseif (isset($_POST['AccessControllerEvent'])) {
        $data = json_decode($_POST['AccessControllerEvent'], true) ?: [];
    } else {
        // Fallback: search JSON string inside raw multipart payload
        if (preg_match('/\{[\s\S]*"AccessControllerEvent"[\s\S]*\}/U', $rawInput, $matches)) {
            $data = json_decode($matches[0], true) ?: [];
        }
    }
} else {
    // Pure application/json payload
    $data = json_decode($rawInput, true) ?: [];
}

// Extract Event Attributes
$event = $data['AccessControllerEvent'] ?? $data;
$pin = trim($event['employeeNoString'] ?? ($event['serialNo'] ?? ($event['cardNo'] ?? '')));
$devSerial = trim($event['deviceSerial'] ?? ($event['devSerial'] ?? ($data['macAddress'] ?? 'Hikvision-MinMoe')));
$eventTime = !empty($event['time']) ? date('Y-m-d H:i:s', strtotime($event['time'])) : date('Y-m-d H:i:s');
$cardType = strtolower(trim($event['cardType'] ?? 'face'));

if (empty($pin)) {
    // Hikvision heartbeat or non-access event (door opened manually, tamper, etc.)
    http_response_code(200);
    echo json_encode(['status' => 'acknowledged', 'message' => 'Non-user or heartbeat event']);
    exit;
}

$db = getDB();

// ── 1. Register or Update Hikvision Device in DB ─────────────────────────────
$deviceId = 1;
try {
    $devStmt = $db->prepare("SELECT id FROM devices WHERE serial_no = ? LIMIT 1");
    $devStmt->execute([$devSerial]);
    $devRow = $devStmt->fetch();

    if ($devRow) {
        $deviceId = $devRow['id'];
        $db->prepare("UPDATE devices SET status = 'ONLINE', last_sync = NOW() WHERE id = ?")->execute([$deviceId]);
    } else {
        $insDev = $db->prepare("
            INSERT INTO devices (name, ip_address, port, serial_no, manufacturer, device_type, location, status, last_sync) 
            VALUES (?, ?, 80, ?, 'Hikvision', 'face', 'Gym Entrance Gate', 'ONLINE', NOW())
        ");
        $remoteIp = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $insDev->execute(["Hikvision MinMoe ({$devSerial})", $remoteIp, $devSerial]);
        $deviceId = $db->lastInsertId();
    }
} catch (Exception $e) {}

// ── 2. WhatsApp Notification Helper ──────────────────────────────────────────
function sendFastWhatsAppHik(string $phone, string $message): void {
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
    curl_setopt($ch, CURLOPT_TIMEOUT, 2);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 1);
    @curl_exec($ch);
    if (PHP_VERSION_ID < 80500 && function_exists('curl_close')) {
        @curl_close($ch);
    }
}

// ── 3. Smart PIN Resolution ──────────────────────────────────────────────────
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

$gymName   = getSetting('gym_name', 'THE CLUB 777®');
$dupWindow = intval(getSetting('duplicate_attendance_window_mins', '5'));

// ── 4. CHECK IF PIN MATCHES GYM MEMBER FIRST ────────────────────────────────
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

if ($member) {
    // Duplicate Buffer Check
    $dupChk = $db->prepare("SELECT id FROM attendance WHERE member_id = ? AND check_in_time >= DATE_SUB(?, INTERVAL ? MINUTE)");
    $dupChk->execute([$member['id'], $eventTime, $dupWindow]);
    if ($dupChk->fetch()) {
        http_response_code(200);
        echo json_encode(['status' => 'ignored', 'message' => 'Duplicate punch buffer window']);
        exit;
    }

    // Subscription Expiry Check
    $subStmt = $db->prepare("
        SELECT * FROM member_subscriptions 
        WHERE member_id = ? AND status = 'active' 
        ORDER BY end_date DESC LIMIT 1
    ");
    $subStmt->execute([$member['id']]);
    $activeSub = $subStmt->fetch();

    $memberStatus = 'success';
    $memberNotes  = 'Hikvision MinMoe AI Face Scan';
    $isExpired    = false;
    $daysLeft     = 0;

    if (!$activeSub || strtotime($activeSub['end_date']) < strtotime(date('Y-m-d', strtotime($eventTime)))) {
        $memberStatus = 'expired_alert';
        $memberNotes  = 'Membership Expired — Flagged at MinMoe Terminal';
        $isExpired    = true;
    } else {
        $daysLeft = max(0, (int) ceil((strtotime($activeSub['end_date']) - strtotime('today')) / 86400));
    }

    // Check-in vs Check-out Toggle
    $today = date('Y-m-d', strtotime($eventTime));
    $openAttStmt = $db->prepare("
        SELECT * FROM attendance 
        WHERE member_id = ? AND DATE(check_in_time) = ? AND check_out_time IS NULL 
        ORDER BY id DESC LIMIT 1
    ");
    $openAttStmt->execute([$member['id'], $today]);
    $openAtt = $openAttStmt->fetch();

    if ($openAtt) {
        // CHECK-OUT
        $durationMins = max(1, (int) round((strtotime($eventTime) - strtotime($openAtt['check_in_time'])) / 60));
        $db->prepare("
            UPDATE attendance 
            SET check_out_time = ?, duration_minutes = ?, notes = CONCAT(IFNULL(notes,''), ' | Checked-Out via MinMoe') 
            WHERE id = ?
        ")->execute([$eventTime, $durationMins, $openAtt['id']]);

        if (!empty($member['phone'])) {
            $msg = "💪 *Workout Completed!*\n\nHello *{$member['name']}*,\nGreat workout session today at *{$gymName}*!\n\n⏱️ Total Gym Time: *{$durationMins} minutes*\n🚪 Checked Out: *" . date('h:i A', strtotime($eventTime)) . "*\n\nStay consistent, keep crushing your goals! 🔥";
            sendFastWhatsAppHik($member['phone'], $msg);
        }

        http_response_code(200);
        echo json_encode([
            'status' => 'success',
            'action' => 'check_out',
            'member' => $member['name'],
            'duration' => $durationMins
        ]);
        exit;

    } else {
        // CHECK-IN
        $db->prepare("
            INSERT INTO attendance (member_id, device_id, check_in_time, verification_method, status, notes) 
            VALUES (?, ?, ?, 'face', ?, ?)
        ")->execute([$member['id'], $deviceId, $eventTime, $memberStatus, $memberNotes]);

        if (!empty($member['phone'])) {
            if ($isExpired) {
                $msg = "⚠️ *Membership Alert — {$gymName}*\n\nHello *{$member['name']}*,\nYour gym membership plan has *expired*.\n\nChecked-in at: *" . date('h:i A', strtotime($eventTime)) . "*\nPlease visit reception to renew your membership. Thank you! 🙏";
            } else {
                $msg = "👋 *Welcome to {$gymName}!*\n\nHello *{$member['name']}*,\nYour attendance has been recorded successfully.\n\n⏰ Time: *" . date('h:i A', strtotime($eventTime)) . "*\n📅 Plan Valid: *{$daysLeft} days remaining*\n\nHave a great workout session! 💪";
            }
            sendFastWhatsAppHik($member['phone'], $msg);
        }

        http_response_code(200);
        echo json_encode([
            'status' => 'success',
            'action' => 'check_in',
            'member' => $member['name'],
            'days_left' => $daysLeft
        ]);
        exit;
    }
}

// ── 5. CHECK IF PIN MATCHES STAFF ───────────────────────────────────────────
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
    $today = date('Y-m-d', strtotime($eventTime));
    $shiftId = $staff['shift_id'] ?: 1;
    $shiftStart = $staff['shift_start'] ?: '05:00:00';

    $attStmt = $db->prepare("SELECT * FROM staff_attendance WHERE staff_id = ? AND date = ? ORDER BY id DESC LIMIT 1");
    $attStmt->execute([$staff['id'], $today]);
    $existingAtt = $attStmt->fetch();

    if (!$existingAtt) {
        // Staff CHECK-IN
        $shiftStartTs = strtotime("{$today} {$shiftStart}");
        $graceMins    = intval($staff['grace_period_mins'] ?? 15);
        $graceTs      = $shiftStartTs + ($graceMins * 60);
        $status       = 'present';
        $lateMins     = 0;
        $lateReason   = null;

        $punchTs = strtotime($eventTime);
        if ($punchTs > $graceTs) {
            $lateMins   = max(1, (int) ceil(($punchTs - $shiftStartTs) / 60));
            $status     = ($lateMins > intval($staff['half_day_threshold_mins'] ?? 60)) ? 'half_day' : 'late';
            $lateReason = "Late by {$lateMins} mins (Grace: {$graceMins}m)";
        }

        $db->prepare("
            INSERT INTO staff_attendance (staff_id, date, shift_id, check_in_time, status, late_minutes, late_reason, verification_method, notes) 
            VALUES (?, ?, ?, ?, ?, ?, ?, 'face', 'Hikvision MinMoe Gate Punch')
        ")->execute([$staff['id'], $today, $shiftId, $eventTime, $status, $lateMins, $lateReason]);

        http_response_code(200);
        echo json_encode(['status' => 'success', 'staff' => $staff['name'], 'action' => 'check_in']);
        exit;

    } elseif (empty($existingAtt['check_out_time'])) {
        // Staff CHECK-OUT
        $workHours = round(max(0, (strtotime($eventTime) - strtotime($existingAtt['check_in_time'])) / 3600), 2);
        $db->prepare("UPDATE staff_attendance SET check_out_time = ?, working_hours = ? WHERE id = ?")
           ->execute([$eventTime, $workHours, $existingAtt['id']]);

        http_response_code(200);
        echo json_encode(['status' => 'success', 'staff' => $staff['name'], 'action' => 'check_out']);
        exit;
    }
}

// ── 6. UNREGISTERED / UNKNOWN FACE ──────────────────────────────────────────
try {
    $db->prepare("
        INSERT INTO unregistered_punches (biometric_id, device_serial, punch_time, raw_payload) 
        VALUES (?, ?, ?, ?)
    ")->execute([$pin, $devSerial, $eventTime, json_encode($event)]);
} catch (Exception $e) {}

http_response_code(200);
echo json_encode(['status' => 'unregistered', 'pin' => $pin, 'message' => 'User logged to unregistered punches']);
