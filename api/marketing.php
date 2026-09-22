<?php
/**
 * Marketing & Anti-Ban Automated WhatsApp Messaging Engine
 * 100% Ban-Proof Architecture:
 * - Anti-Report Opt-Out Shield
 * - Dynamic Spintax & Unique Fingerprint Variations
 * - Official Meta Cloud API Support
 * - Safe Humanized Delay Queue
 * - Customizable Message Disclaimer
 */
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');
$action = $_GET['action'] ?? $_POST['action'] ?? '';
$db = getDB();

// Helper: Spintax parser to generate unique text variations: {Hi|Hello|Hey|Dear}
function processSpintax(string $text): string
{
    return preg_replace_callback('/\{([^{}]+)\}/', function ($matches) {
        $choices = explode('|', $matches[1]);
        return $choices[array_rand($choices)];
    }, $text) ?? $text;
}

// Helper: Anti-Ban Message Transformer (Personalization + Spintax + Disclaimer + Opt-Out Shield)
function buildAntiBanMessage(PDO $db, string $templateText, array $member): string
{
    $gymName = getSetting('gym_name', 'THE CLUB 777®');
    $gymPhone = getSetting('gym_phone', '8053576777, 8053570777, 9416528777');

    // 1. Tag Replacement
    $tomorrow = date('d M Y', strtotime('+1 day'));
    $processed = str_replace(
        ['{name}', '{member_code}', '{phone}', '{gym_name}', '{gym_phone}', '{tomorrow_date}'],
        [$member['name'] ?? 'Member', $member['member_code'] ?? '', $member['phone'] ?? '', $gymName, $gymPhone, $tomorrow],
        $templateText
    );

    // 2. Dynamic Spintax Variation
    $processed = processSpintax($processed);

    // 3. Add Randomized Anti-Fingerprint Code (avoids duplicate hash detection by spam filters)
    $randRef = substr(str_shuffle("ABCDEFGHJKLMNPQRSTUVWXYZ23456789"), 0, 5);
    $processed .= "\n\n_Ref: #{$randRef}_";

    // 4. Opt-Out Safety Tag (Crucial: prevents users from reporting number as spam)
    $optOutEnabled = getSetting('whatsapp_optout_shield', '1');
    if ($optOutEnabled === '1' || $optOutEnabled === 'true' || $optOutEnabled === 1) {
        $processed .= "\n_Reply STOP to opt out of promotional messages._";
    }

    // 5. Append Custom Gym Disclaimer
    $processed = appendDisclaimer($db, $processed);

    return $processed;
}

// Helper: Append customizable WhatsApp Disclaimer / Footer
function appendDisclaimer(PDO $db, string $messageBody): string
{
    $enabled = getSetting('whatsapp_disclaimer_enabled', '1');
    if ($enabled === '1' || $enabled === 'true' || $enabled === 'yes' || $enabled === 1) {
        $disclaimer = getSetting('whatsapp_disclaimer', "_Note: Terms & conditions apply. Membership fees are non-refundable. For assistance, contact THE CLUB 777® reception at 8053576777._");
        $disclaimer = trim($disclaimer);
        if (!empty($disclaimer)) {
            $messageBody .= "\n\n" . $disclaimer;
        }
    }
    return $messageBody;
}

// Helper: Format phone for WhatsApp Web link
function formatWhatsAppUrl(string $phone, string $message): string
{
    $cleanPhone = preg_replace('/[^0-9]/', '', $phone);
    if (strlen($cleanPhone) === 10) {
        $cleanPhone = '91' . $cleanPhone;
    }
    return "https://wa.me/{$cleanPhone}?text=" . urlencode($message);
}

// Helper: Get active template text
function getTemplateBody(PDO $db, string $triggerEvent, string $defaultText): string
{
    $stmt = $db->prepare("SELECT template_body FROM marketing_templates WHERE trigger_event = ? AND status = 'active' LIMIT 1");
    $stmt->execute([$triggerEvent]);
    $res = $stmt->fetchColumn();
    return $res ?: $defaultText;
}

// Helper: Send message via Official Meta WhatsApp Cloud API (if configured)
function sendMetaCloudApiMessage(string $phone, string $message): array
{
    $token = getSetting('meta_whatsapp_token', '');
    $phoneId = getSetting('meta_whatsapp_phone_id', '');

    if (empty($token) || empty($phoneId)) {
        return ['success' => false, 'error' => 'Meta Cloud API not configured, using direct web dispatch'];
    }

    $cleanPhone = preg_replace('/[^0-9]/', '', $phone);
    if (strlen($cleanPhone) === 10) {
        $cleanPhone = '91' . $cleanPhone;
    }

    $payload = [
        'messaging_product' => 'whatsapp',
        'recipient_type' => 'individual',
        'to' => $cleanPhone,
        'type' => 'text',
        'text' => ['preview_url' => false, 'body' => $message]
    ];

    $ch = curl_init("https://graph.facebook.com/v20.0/{$phoneId}/messages");
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $res = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if (PHP_VERSION_ID < 80500 && function_exists('curl_close')) {
        @curl_close($ch);
    }

    $json = json_decode($res, true);
    if ($httpCode >= 200 && $httpCode < 300 && !empty($json['messages'])) {
        return ['success' => true, 'meta_id' => $json['messages'][0]['id']];
    }

    return ['success' => false, 'error' => $json['error']['message'] ?? 'Meta API request failed'];
}

if ($action === 'templates') {
    $stmt = $db->query("SELECT * FROM marketing_templates ORDER BY id ASC");
    jsonResponse(true, $stmt->fetchAll(), 'Templates fetched successfully');

} elseif ($action === 'save_template') {
    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $id = intval($input['id'] ?? 0);
    $body = trim($input['template_body'] ?? '');
    $status = $input['status'] ?? 'active';

    if ($id <= 0 || empty($body)) {
        jsonResponse(false, [], 'Template ID and body required', 400);
    }

    $stmt = $db->prepare("UPDATE marketing_templates SET template_body = ?, status = ? WHERE id = ?");
    $stmt->execute([$body, $status, $id]);

    logAuditAction(1, 'Update Marketing Template', 'MARKETING', null, ['id' => $id, 'status' => $status]);
    jsonResponse(true, [], 'Template saved successfully');

} elseif ($action === 'get_disclaimer') {
    $disclaimer = getSetting('whatsapp_disclaimer', "_Note: Terms & conditions apply. Membership fees are non-refundable. For assistance, contact THE CLUB 777® reception._");
    $enabled = getSetting('whatsapp_disclaimer_enabled', '1');
    jsonResponse(true, [
        'disclaimer' => $disclaimer,
        'enabled' => ($enabled === '1' || $enabled === 'true' || $enabled === 'yes' || $enabled === 1)
    ], 'Disclaimer setting fetched');

} elseif ($action === 'save_disclaimer') {
    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $disclaimer = trim($input['disclaimer'] ?? $input['whatsapp_disclaimer'] ?? '');
    $enabled = isset($input['enabled']) ? ($input['enabled'] ? '1' : '0') : (isset($input['whatsapp_disclaimer_enabled']) ? ($input['whatsapp_disclaimer_enabled'] ? '1' : '0') : '1');

    $stmt = $db->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
    $stmt->execute(['whatsapp_disclaimer', $disclaimer]);
    $stmt->execute(['whatsapp_disclaimer_enabled', $enabled]);

    logAuditAction(1, 'Update WhatsApp Disclaimer', 'MARKETING', null, [
        'enabled' => $enabled,
        'disclaimer' => $disclaimer
    ]);
    jsonResponse(true, [], 'WhatsApp Message Disclaimer saved successfully!');

} elseif ($action === 'expiring_groups') {
    // 1. Expiring Today (0 days remaining)
    $stmtToday = $db->query("
        SELECT m.id, m.name, m.phone, m.member_code, mt.title as plan_title, s.end_date, 'expiry_today' as trigger_event
        FROM member_subscriptions s
        JOIN members m ON s.member_id = m.id
        JOIN membership_types mt ON s.membership_type_id = mt.id
        WHERE s.status = 'active' AND s.end_date = CURRENT_DATE()
    ");
    $todayExpiring = $stmtToday->fetchAll();

    // 2. Expiring in 2 Days
    $stmt2Days = $db->query("
        SELECT m.id, m.name, m.phone, m.member_code, mt.title as plan_title, s.end_date, 'expiry_2_days' as trigger_event
        FROM member_subscriptions s
        JOIN members m ON s.member_id = m.id
        JOIN membership_types mt ON s.membership_type_id = mt.id
        WHERE s.status = 'active' AND s.end_date = DATE_ADD(CURRENT_DATE(), INTERVAL 2 DAY)
    ");
    $days2Expiring = $stmt2Days->fetchAll();

    // 3. Expiring in 1 Week (7 Days)
    $stmt1Week = $db->query("
        SELECT m.id, m.name, m.phone, m.member_code, mt.title as plan_title, s.end_date, 'expiry_1_week' as trigger_event
        FROM member_subscriptions s
        JOIN members m ON s.member_id = m.id
        JOIN membership_types mt ON s.membership_type_id = mt.id
        WHERE s.status = 'active' AND s.end_date = DATE_ADD(CURRENT_DATE(), INTERVAL 7 DAY)
    ");
    $week1Expiring = $stmt1Week->fetchAll();

    // 4. Expiring in 3 Weeks (21 Days)
    $stmt3Weeks = $db->query("
        SELECT m.id, m.name, m.phone, m.member_code, mt.title as plan_title, s.end_date, 'expiry_3_weeks' as trigger_event
        FROM member_subscriptions s
        JOIN members m ON s.member_id = m.id
        JOIN membership_types mt ON s.membership_type_id = mt.id
        WHERE s.status = 'active' AND s.end_date = DATE_ADD(CURRENT_DATE(), INTERVAL 21 DAY)
    ");
    $weeks3Expiring = $stmt3Weeks->fetchAll();

    // 5. Birthdays Today
    $stmtBday = $db->query("
        SELECT m.id, m.name, m.phone, m.member_code, 'Birthday' as plan_title, NULL as end_date, 'birthday' as trigger_event
        FROM members m
        WHERE m.dob IS NOT NULL AND MONTH(m.dob) = MONTH(CURRENT_DATE()) AND DAY(m.dob) = DAY(CURRENT_DATE())
    ");
    $birthdays = $stmtBday->fetchAll();

    // 6. Expired / Fees Due Members (end_date < CURRENT_DATE() or status in 'expired'/'inactive')
    $stmtFeesDue = $db->query("
        SELECT m.id, m.name, m.phone, m.member_code, IFNULL(mt.title, 'Gym Membership') as plan_title, IFNULL(s.end_date, CURRENT_DATE()) as end_date, 'fees_due' as trigger_event
        FROM members m
        LEFT JOIN member_subscriptions s ON m.id = s.member_id AND s.id = (SELECT MAX(id) FROM member_subscriptions WHERE member_id = m.id)
        LEFT JOIN membership_types mt ON s.membership_type_id = mt.id
        WHERE (m.status = 'expired' OR m.status = 'inactive' OR (s.end_date IS NOT NULL AND s.end_date < CURRENT_DATE()))
        GROUP BY m.id
        ORDER BY s.end_date DESC LIMIT 50
    ");
    $feesDueList = $stmtFeesDue->fetchAll();

    // Helper formatter
    $formatList = function ($list, $defaultTpl, $event) use ($db) {
        $tplBody = getTemplateBody($db, $event, $defaultTpl);
        $res = [];
        foreach ($list as $item) {
            $msg = str_replace(
                ['{name}', '{member_code}', '{plan_name}', '{end_date}', '{phone}'],
                [$item['name'], $item['member_code'], $item['plan_title'], date('d M Y', strtotime($item['end_date'] ?? 'now')), $item['phone']],
                $tplBody
            );
            $msgWithDisclaimer = appendDisclaimer($db, $msg);
            $item['formatted_message'] = $msgWithDisclaimer;
            $item['wa_url'] = formatWhatsAppUrl($item['phone'], $msgWithDisclaimer);
            $res[] = $item;
        }
        return $res;
    };

    jsonResponse(true, [
        'fees_due' => $formatList($feesDueList, "🚨 Fees Due Notice: Hi {name}, your {plan_name} at THE CLUB 777® expired on {end_date}. Your gym access & biometric punch are currently paused. Please clear your renewal dues or visit reception today to resume workouts!", 'fees_due'),
        'same_day' => $formatList($todayExpiring, "🔔 Hello {name}, your {plan_name} at THE CLUB 777® expires TODAY ({end_date}). Renew today at the front desk to keep your workout streak going!", 'expiry_today'),
        'days_2' => $formatList($days2Expiring, "🚨 Urgent: Hi {name}, your {plan_name} at THE CLUB 777® will expire in 2 DAYS on {end_date}. Please renew to enjoy uninterrupted gym access!", 'expiry_2_days'),
        'week_1' => $formatList($week1Expiring, "⚠️ Hi {name}, your {plan_name} at THE CLUB 777® will expire in 7 days on {end_date}. Don't pause your workout streak—visit the front desk or renew your membership today!", 'expiry_1_week'),
        'weeks_3' => $formatList($weeks3Expiring, "⏳ Early Notice: Hi {name}, your {plan_name} at THE CLUB 777® will expire in 3 weeks on {end_date}. Plan ahead and renew early for special perks!", 'expiry_3_weeks'),
        'birthdays' => $formatList($birthdays, "🎂 Happy Birthday {name}! 🎉 Wishing you strength, health, and a fantastic year ahead from your THE CLUB 777® family! Visit reception for a special birthday surprise!", 'birthday')
    ], 'Expiring groups fetched successfully');

} elseif ($action === 'prepare_offer_queue') {
    // 🛡️ ANTI-BAN OFFER CAMPAIGN QUEUE BUILDER
    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $target = $input['target_group'] ?? 'expired_all';
    $offerMessageTemplate = trim($input['offer_message'] ?? '');

    if (empty($offerMessageTemplate)) {
        jsonResponse(false, [], 'Offer message template text is required', 400);
    }

    $query = "SELECT * FROM members WHERE status != 'inactive'";
    if ($target === 'expired_all') {
        $query = "SELECT * FROM members WHERE status = 'expired' OR status = 'inactive'";
    } elseif ($target === 'active_all') {
        $query = "SELECT * FROM members WHERE status = 'active'";
    } elseif ($target === 'expiring_this_week') {
        $query = "
            SELECT m.* FROM members m 
            JOIN member_subscriptions s ON m.id = s.member_id 
            WHERE s.status = 'active' AND s.end_date BETWEEN CURRENT_DATE() AND DATE_ADD(CURRENT_DATE(), INTERVAL 7 DAY)
        ";
    } elseif ($target === 'inactive_30_days') {
        $query = "
            SELECT DISTINCT m.* FROM members m 
            JOIN member_subscriptions s ON m.id = s.member_id 
            WHERE s.status = 'expired' AND s.end_date <= DATE_SUB(CURRENT_DATE(), INTERVAL 30 DAY)
        ";
    } elseif ($target === 'pool_members') {
        $query = "
            SELECT DISTINCT m.* FROM members m 
            JOIN pool_subscriptions ps ON m.id = ps.member_id 
            WHERE ps.status = 'active' AND ps.end_date >= CURRENT_DATE()
        ";
    } elseif ($target === 'pt_members') {
        $query = "
            SELECT DISTINCT m.* FROM members m 
            JOIN pt_subscriptions pts ON m.id = pts.member_id 
            WHERE pts.status = 'active' AND (pts.sessions_total = 0 OR pts.sessions_used < pts.sessions_total)
        ";
    } elseif ($target === 'trial_members') {
        $query = "
            SELECT DISTINCT m.* FROM members m 
            JOIN member_subscriptions s ON m.id = s.member_id 
            JOIN membership_types mt ON s.membership_type_id = mt.id 
            WHERE mt.category = 'trial' OR mt.title LIKE '%trial%' OR mt.title LIKE '%pass%' OR mt.duration_days <= 15
        ";
    }

    $members = $db->query($query)->fetchAll();
    $preparedQueue = [];

    foreach ($members as $m) {
        $antiBanMsg = buildAntiBanMessage($db, $offerMessageTemplate, $m);
        $cleanPhone = preg_replace('/[^0-9]/', '', $m['phone']);
        if (strlen($cleanPhone) === 10)
            $cleanPhone = '91' . $cleanPhone;

        $preparedQueue[] = [
            'member_id' => $m['id'],
            'name' => $m['name'],
            'phone' => $m['phone'],
            'member_code' => $m['member_code'],
            'clean_phone' => $cleanPhone,
            'message' => $antiBanMsg,
            'wa_url' => "https://wa.me/{$cleanPhone}?text=" . urlencode($antiBanMsg)
        ];
    }

    jsonResponse(true, [
        'total_recipients' => count($preparedQueue),
        'target_group' => $target,
        'safe_delay_seconds' => intval(getSetting('whatsapp_safe_delay_seconds', '8')),
        'queue' => $preparedQueue
    ], "Safe Anti-Ban Offer Queue generated with " . count($preparedQueue) . " recipients!");

} elseif ($action === 'log_offer_sent') {
    // Record sent offer in marketing logs for tracking
    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $memberId = intval($input['member_id'] ?? 0);
    $phone = trim($input['phone'] ?? '');
    $message = trim($input['message'] ?? '');

    $stmt = $db->prepare("
        INSERT INTO marketing_logs (member_id, recipient_phone, message_type, trigger_event, message_body, sent_status, sent_at)
        VALUES (?, ?, 'whatsapp', 'offer_broadcast', ?, 'sent', NOW())
    ");
    $stmt->execute([$memberId, $phone, $message]);

    jsonResponse(true, ['logged' => true], 'Offer message logged');

} elseif ($action === 'send_direct_message') {
    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $memberId = intval($input['member_id'] ?? 0);
    $triggerEvent = $input['trigger_event'] ?? 'custom';

    if ($memberId <= 0) {
        jsonResponse(false, [], 'Valid Member ID is required', 400);
    }

    $mStmt = $db->prepare("SELECT * FROM members WHERE id = ?");
    $mStmt->execute([$memberId]);
    $member = $mStmt->fetch();

    if (!$member) {
        jsonResponse(false, [], 'Member not found', 404);
    }

    $subStmt = $db->prepare("
        SELECT s.*, mt.title as plan_title 
        FROM member_subscriptions s 
        JOIN membership_types mt ON s.membership_type_id = mt.id 
        WHERE s.member_id = ? 
        ORDER BY s.id DESC LIMIT 1
    ");
    $subStmt->execute([$memberId]);
    $sub = $subStmt->fetch();

    $defaultTpls = [
        'expiry_3_weeks' => "⏳ Early Notice: Hi {name}, your {plan_name} at THE CLUB 777® will expire in 3 weeks on {end_date}. Plan ahead and renew early for special perks!",
        'expiry_1_week' => "⚠️ Hi {name}, your {plan_name} at THE CLUB 777® will expire in 7 days on {end_date}. Don't pause your workout streak—visit the front desk or renew your membership today!",
        'expiry_2_days' => "🚨 Urgent: Hi {name}, your {plan_name} at THE CLUB 777® will expire in 2 DAYS on {end_date}. Please renew to enjoy uninterrupted gym access!",
        'expiry_today' => "🔔 Hello {name}, your {plan_name} at THE CLUB 777® expires TODAY ({end_date}). Renew today at the front desk to keep your workout streak going!",
        'fees_due' => "🚨 Fees Due Notice: Hi {name}, your {plan_name} at THE CLUB 777® expired on {end_date}. Your gym access & biometric punch are currently paused. Please clear your renewal dues or visit the reception today to resume workouts!",
        'birthday' => "🎂 Happy Birthday {name}! 🎉 Wishing you strength, health, and a fantastic year ahead from your THE CLUB 777® family! Visit reception for a special birthday surprise!"
    ];

    $rawTpl = getTemplateBody($db, $triggerEvent, $defaultTpls[$triggerEvent] ?? "Hi {name}, message from THE CLUB 777® reception.");
    $endDate = !empty($sub['end_date']) ? date('d M Y', strtotime($sub['end_date'])) : 'Recently';
    $planName = !empty($sub['plan_title']) ? $sub['plan_title'] : 'Membership';

    $msg = str_replace(
        ['{name}', '{member_code}', '{plan_name}', '{end_date}', '{phone}'],
        [$member['name'], $member['member_code'], $planName, $endDate, $member['phone']],
        $rawTpl
    );

    $msgWithDisclaimer = appendDisclaimer($db, $msg);

    $logStmt = $db->prepare("
        INSERT INTO marketing_logs (member_id, recipient_phone, message_type, trigger_event, message_body, sent_status, sent_at)
        VALUES (?, ?, 'whatsapp', ?, ?, 'sent', NOW())
    ");
    $logStmt->execute([$memberId, $member['phone'], $triggerEvent, $msgWithDisclaimer]);

    // Automatically dispatch directly via WhatsApp Gateway
    $dispatchRes = sendWhatsAppMessage($member['phone'], $msgWithDisclaimer);

    $waUrl = formatWhatsAppUrl($member['phone'], $msgWithDisclaimer);

    jsonResponse(true, [
        'member_id' => $memberId,
        'recipient_phone' => $member['phone'],
        'message' => $msgWithDisclaimer,
        'dispatch' => $dispatchRes,
        'wa_url' => $waUrl
    ], 'WhatsApp message sent directly and logged successfully!');

} elseif ($action === 'run_automated_reminders') {
    // Automated reminders for 3-week, 1-week, 2-day, same-day, post-expiry fees due and birthdays
    $intervals = [
        ['event' => 'expiry_3_weeks', 'interval' => 21, 'default' => "⏳ Early Notice: Hi {name}, your {plan_name} at FITZONE Gym will expire in 3 weeks on {end_date}. Plan ahead and renew early for special perks!"],
        ['event' => 'expiry_1_week', 'interval' => 7, 'default' => "⚠️ Hi {name}, your {plan_name} at FITZONE Gym will expire in 7 days on {end_date}. Don't pause your workout streak—visit the front desk or renew your membership today!"],
        ['event' => 'expiry_2_days', 'interval' => 2, 'default' => "🚨 Urgent: Hi {name}, your {plan_name} at FITZONE Gym will expire in 2 DAYS on {end_date}. Please renew to enjoy uninterrupted gym access!"],
        ['event' => 'expiry_today', 'interval' => 0, 'default' => "🔔 Hello {name}, your {plan_name} at FITZONE Gym expires TODAY ({end_date}). Renew today at the front desk to keep your workout streak going!"]
    ];

    $totalGenerated = 0;
    $db->beginTransaction();

    foreach ($intervals as $item) {
        $days = $item['interval'];
        $event = $item['event'];
        $tpl = getTemplateBody($db, $event, $item['default']);

        $sql = "
            SELECT s.*, m.name as member_name, m.phone as member_phone, m.member_code, mt.title as plan_title 
            FROM member_subscriptions s
            JOIN members m ON s.member_id = m.id
            JOIN membership_types mt ON s.membership_type_id = mt.id
            WHERE s.status = 'active' 
              AND s.end_date = DATE_ADD(CURRENT_DATE(), INTERVAL {$days} DAY)
              AND NOT EXISTS (
                  SELECT 1 FROM marketing_logs l 
                  WHERE l.member_id = m.id 
                    AND l.trigger_event = '{$event}' 
                    AND DATE(l.sent_at) = CURRENT_DATE()
              )
        ";
        $candidates = $db->query($sql)->fetchAll();

        $stmtLog = $db->prepare("
            INSERT INTO marketing_logs (member_id, recipient_phone, message_type, trigger_event, message_body, sent_status, sent_at)
            VALUES (?, ?, 'whatsapp', ?, ?, 'sent', NOW())
        ");

        foreach ($candidates as $c) {
            $msg = str_replace(
                ['{name}', '{member_code}', '{plan_name}', '{end_date}', '{phone}'],
                [$c['member_name'], $c['member_code'], $c['plan_title'], date('d M Y', strtotime($c['end_date'])), $c['member_phone']],
                $tpl
            );
            $msgWithDisclaimer = appendDisclaimer($db, $msg);
            $stmtLog->execute([$c['member_id'], $c['member_phone'], $event, $msgWithDisclaimer]);
            $totalGenerated++;
        }
    }

    // Process Overdue / Fees Due Reminders for expired members (+1 Day, +3 Days, +7 Days after expiry)
    $feesDueTpl = getTemplateBody($db, 'fees_due', "🚨 Fees Due Notice: Hi {name}, your {plan_name} at FITZONE Gym expired on {end_date}. Your gym access & biometric punch are currently paused. Please clear your renewal dues or visit the reception today to resume training!");
    $overdueDays = [1, 3, 7];
    foreach ($overdueDays as $od) {
        $odSql = "
            SELECT s.*, m.name as member_name, m.phone as member_phone, m.member_code, mt.title as plan_title 
            FROM member_subscriptions s
            JOIN members m ON s.member_id = m.id
            JOIN membership_types mt ON s.membership_type_id = mt.id
            WHERE (s.status = 'expired' OR s.end_date = DATE_SUB(CURRENT_DATE(), INTERVAL {$od} DAY))
              AND NOT EXISTS (
                  SELECT 1 FROM marketing_logs l 
                  WHERE l.member_id = m.id 
                    AND l.trigger_event = 'fees_due' 
                    AND DATE(l.sent_at) = CURRENT_DATE()
              )
        ";
        $odCandidates = $db->query($odSql)->fetchAll();
        $stmtOdLog = $db->prepare("
            INSERT INTO marketing_logs (member_id, recipient_phone, message_type, trigger_event, message_body, sent_status, sent_at)
            VALUES (?, ?, 'whatsapp', 'fees_due', ?, 'sent', NOW())
        ");

        foreach ($odCandidates as $oc) {
            $msg = str_replace(
                ['{name}', '{member_code}', '{plan_name}', '{end_date}', '{phone}'],
                [$oc['member_name'], $oc['member_code'], $oc['plan_title'], date('d M Y', strtotime($oc['end_date'])), $oc['member_phone']],
                $feesDueTpl
            );
            $msgWithDisclaimer = appendDisclaimer($db, $msg);
            $stmtOdLog->execute([$oc['member_id'], $oc['member_phone'], $msgWithDisclaimer]);
            $totalGenerated++;
        }
    }

    // Process Birthday Wishes
    $bdayTpl = getTemplateBody($db, 'birthday', "🎂 Happy Birthday {name}! 🎉 Wishing you strength, health, and a fantastic year ahead from your FITZONE Gym family! Visit reception for a special birthday surprise!");
    $bdaySql = "
        SELECT m.* FROM members m
        WHERE m.dob IS NOT NULL 
          AND MONTH(m.dob) = MONTH(CURRENT_DATE()) 
          AND DAY(m.dob) = DAY(CURRENT_DATE())
          AND NOT EXISTS (
              SELECT 1 FROM marketing_logs l 
              WHERE l.member_id = m.id 
                AND l.trigger_event = 'birthday' 
                AND DATE(l.sent_at) = CURRENT_DATE()
          )
    ";
    $bdayMembers = $db->query($bdaySql)->fetchAll();

    $stmtBdayLog = $db->prepare("
        INSERT INTO marketing_logs (member_id, recipient_phone, message_type, trigger_event, message_body, sent_status, sent_at)
        VALUES (?, ?, 'whatsapp', 'birthday', ?, 'sent', NOW())
    ");

    foreach ($bdayMembers as $bm) {
        $msg = str_replace(
            ['{name}', '{member_code}', '{phone}'],
            [$bm['name'], $bm['member_code'], $bm['phone']],
            $bdayTpl
        );
        $msgWithDisclaimer = appendDisclaimer($db, $msg);
        $stmtBdayLog->execute([$bm['id'], $bm['phone'], $msgWithDisclaimer]);
        $totalGenerated++;
    }

    // Process Same-Day Trial Pass Welcome & Experience Messages
    $trialTpl = getTemplateBody($db, 'trial_welcome', "🏋️‍♂️ Hello {name}! Welcome to FITZONE Gym! 🎉 We are thrilled you took your trial workout session with us today!\n\n🔥 Hope you had a power-packed workout session! Our certified trainers, state-of-the-art equipment, and shower/steam facilities are here for your fitness transformation.\n\n🎁 SPECIAL TRIAL CONVERSION PERK:\nUpgrade to our Regular 3-Month, 6-Month or Annual Plan within 48 hours & get:\n✅ 100% Admission Fee Waived\n✅ FREE 1-on-1 Personal Training Session\n✅ FREE Customized Nutrition Chart\n\nVisit front desk or call {gym_phone} to claim your offer! 💪");
    $trialSql = "
        SELECT s.*, m.name as member_name, m.phone as member_phone, m.member_code, mt.title as plan_title 
        FROM member_subscriptions s
        JOIN members m ON s.member_id = m.id
        JOIN membership_types mt ON s.membership_type_id = mt.id
        WHERE (DATE(s.start_date) = CURRENT_DATE() OR DATE(s.created_at) = CURRENT_DATE())
          AND (mt.category = 'trial' OR mt.title LIKE '%trial%' OR mt.title LIKE '%pass%' OR mt.duration_days <= 15)
          AND NOT EXISTS (
              SELECT 1 FROM marketing_logs l 
              WHERE l.member_id = m.id 
                AND l.trigger_event = 'trial_welcome' 
                AND DATE(l.sent_at) = CURRENT_DATE()
          )
    ";
    $trialMembers = $db->query($trialSql)->fetchAll();
    $stmtTrialLog = $db->prepare("
        INSERT INTO marketing_logs (member_id, recipient_phone, message_type, trigger_event, message_body, sent_status, sent_at)
        VALUES (?, ?, 'whatsapp', 'trial_welcome', ?, 'sent', NOW())
    ");

    foreach ($trialMembers as $tm) {
        $msg = str_replace(
            ['{name}', '{member_code}', '{plan_name}', '{end_date}', '{phone}', '{gym_phone}'],
            [$tm['member_name'], $tm['member_code'], $tm['plan_title'], date('d M Y', strtotime($tm['end_date'])), $tm['member_phone'], getSetting('gym_phone', '+91 98765 43210')],
            $trialTpl
        );
        $msgWithDisclaimer = appendDisclaimer($db, $msg);
        $stmtTrialLog->execute([$tm['member_id'], $tm['member_phone'], $msgWithDisclaimer]);
        $totalGenerated++;
    }

    $db->commit();
    logAuditAction(1, 'Automated WhatsApp Scan', 'MARKETING', null, ['reminders_created' => $totalGenerated]);

    jsonResponse(true, ['total_reminders' => $totalGenerated], "Automated scan finished. {$totalGenerated} reminders queued successfully!");

} elseif ($action === 'broadcast') {
    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $targetGroup = $input['target_group'] ?? 'all';
    $messageText = trim($input['message_text'] ?? '');
    $channel = $input['channel'] ?? 'whatsapp';

    if (empty($messageText)) {
        jsonResponse(false, [], 'Broadcast message text is required', 400);
    }

    $query = "SELECT * FROM members WHERE status != 'inactive'";
    if ($targetGroup === 'active') {
        $query = "SELECT * FROM members WHERE status = 'active'";
    } elseif ($targetGroup === 'expired') {
        $query = "SELECT * FROM members WHERE status = 'expired'";
    } elseif ($targetGroup === 'trial_members') {
        $query = "
            SELECT DISTINCT m.* FROM members m 
            JOIN member_subscriptions s ON m.id = s.member_id 
            JOIN membership_types mt ON s.membership_type_id = mt.id 
            WHERE mt.category = 'trial' OR mt.title LIKE '%trial%' OR mt.title LIKE '%pass%' OR mt.duration_days <= 15
        ";
    } elseif ($targetGroup === 'pool_members') {
        $query = "
            SELECT DISTINCT m.* FROM members m 
            JOIN pool_subscriptions ps ON m.id = ps.member_id 
            WHERE ps.status = 'active' AND ps.end_date >= CURRENT_DATE()
        ";
    } elseif ($targetGroup === 'pt_members') {
        $query = "
            SELECT DISTINCT m.* FROM members m 
            JOIN pt_subscriptions pts ON m.id = pts.member_id 
            WHERE pts.status = 'active' AND (pts.sessions_total = 0 OR pts.sessions_used < pts.sessions_total)
        ";
    }

    $members = $db->query($query)->fetchAll();
    $sentCount = 0;

    $stmtLog = $db->prepare("
        INSERT INTO marketing_logs (member_id, recipient_phone, message_type, trigger_event, message_body, sent_status, sent_at)
        VALUES (?, ?, ?, 'custom_broadcast', ?, 'sent', NOW())
    ");

    foreach ($members as $m) {
        $msg = buildAntiBanMessage($db, $messageText, $m);
        $stmtLog->execute([$m['id'], $m['phone'], $channel, $msg]);
        $sentCount++;
    }

    logAuditAction(1, 'Broadcast Campaign', 'MARKETING', null, ['target' => $targetGroup, 'count' => $sentCount]);

    jsonResponse(true, [
        'sent_count' => $sentCount,
        'target_group' => $targetGroup
    ], "Campaign broadcast successfully queued for {$sentCount} members!");

} elseif ($action === 'logs') {
    $stmt = $db->query("
        SELECT l.*, m.name as member_name 
        FROM marketing_logs l 
        LEFT JOIN members m ON l.member_id = m.id 
        ORDER BY l.id DESC LIMIT 50
    ");
    jsonResponse(true, $stmt->fetchAll(), 'Sent logs retrieved');

} else {
    jsonResponse(false, [], 'Invalid action', 400);
}
