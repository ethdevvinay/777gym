<?php
/**
 * Members Management API
 * Supports Full 360° Profiles, DOB & Birthday Queries, Biometrics, and Contact Updates
 */
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');
$action = $_GET['action'] ?? '';
$db = getDB();

if ($action === 'search') {
    $q = trim($_GET['q'] ?? '');
    if (empty($q)) {
        jsonResponse(true, []);
    }

    $stmt = $db->prepare("
        SELECT m.*, s.end_date, s.status as sub_status 
        FROM members m
        LEFT JOIN member_subscriptions s ON m.id = s.member_id AND s.status = 'active'
        WHERE m.name LIKE ? OR m.phone LIKE ? OR m.member_code LIKE ? OR m.biometric_id LIKE ?
        ORDER BY m.id DESC LIMIT 15
    ");
    $searchTerm = "%{$q}%";
    $stmt->execute([$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
    $members = $stmt->fetchAll();

    jsonResponse(true, $members);

} elseif ($action === 'create') {
    $input = json_decode(file_get_contents('php://input'), true);
    $name = trim($input['name'] ?? '');
    $phone = trim($input['phone'] ?? '');
    $email = trim($input['email'] ?? '');
    $gender = $input['gender'] ?? 'Male';
    $dob = !empty($input['dob']) ? $input['dob'] : null;
    $emergencyContact = trim($input['emergency_contact'] ?? '');
    $parentName = trim($input['parent_name'] ?? '');
    $address = trim($input['address'] ?? '');
    $notes = trim($input['notes'] ?? '');
    $biometricId = trim($input['biometric_id'] ?? '');

    if (empty($name) || empty($phone)) {
        jsonResponse(false, [], 'Full Name and Mobile Phone Number are required', 400);
    }

    try {
        $memberCode = generateMemberCode();
        if (empty($biometricId)) {
            $biometricId = "BIO-" . rand(1000, 9999);
        }

        $stmt = $db->prepare("
            INSERT INTO members (member_code, name, parent_name, phone, whatsapp_no, email, dob, gender, emergency_contact, address, biometric_id, notes, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')
        ");
        $stmt->execute([$memberCode, $name, $parentName, $phone, $phone, $email, $dob, $gender, $emergencyContact, $address, $biometricId, $notes]);
        $memberId = $db->lastInsertId();

        // Optional: Initial membership plan assignment
        $planId = intval($input['membership_type_id'] ?? 0);
        if ($planId > 0) {
            $stmtPlan = $db->prepare("SELECT * FROM membership_types WHERE id = ?");
            $stmtPlan->execute([$planId]);
            $plan = $stmtPlan->fetch();
            if ($plan) {
                $startDate = date('Y-m-d');
                $endDate = date('Y-m-d', strtotime("+{$plan['duration_days']} days"));
                $stmtSub = $db->prepare("
                    INSERT INTO member_subscriptions (member_id, membership_type_id, start_date, end_date, price_paid, status)
                    VALUES (?, ?, ?, ?, ?, 'active')
                ");
                $stmtSub->execute([$memberId, $planId, $startDate, $endDate, $plan['price']]);
            }
        }

        // Auto-send Welcome Message on WhatsApp
        try {
            $welcomeMsg = "🎉 *Welcome to THE CLUB 777®!* 🏋️‍♂️\n\nHello *{$name}*!\nWe are thrilled to welcome you to the ultimate fitness destination in Jhajjar.\n\n🆔 *Member ID:* {$memberCode}\n📞 *Registered Mobile:* {$phone}\n📍 *Club Address:* Behind Shehnai Garden, Near Railway Station, Jhajjar - 124103\n📱 *Helpline:* 8053576777, 8053570777\n\nOur trainers and world-class equipment are ready to fuel your transformation journey!\n\n_Stay Strong & Keep Transforming!_ 💪";
            
            sendWhatsAppMessage($phone, $welcomeMsg);
            $db->prepare("INSERT INTO marketing_logs (member_id, recipient_phone, message_type, trigger_event, message_body, sent_status, sent_at) VALUES (?, ?, 'whatsapp', 'welcome', ?, 'sent', NOW())")
               ->execute([$memberId, $phone, $welcomeMsg]);
        } catch (Exception $e) {}

        logAuditAction(1, 'Register New Member', 'MEMBERS', null, ['id' => $memberId, 'name' => $name, 'phone' => $phone]);

        jsonResponse(true, [
            'id' => $memberId,
            'member_code' => $memberCode,
            'name' => $name,
            'phone' => $phone,
            'dob' => $dob,
            'gender' => $gender,
            'biometric_id' => $biometricId,
            'status' => 'active'
        ], 'Member registered successfully!');
    } catch (PDOException $e) {
        jsonResponse(false, [], 'Registration Error: ' . $e->getMessage(), 500);
    }

} elseif ($action === 'list') {
    $stmt = $db->query("
        SELECT m.*, 
               (SELECT ms.end_date FROM member_subscriptions ms WHERE ms.member_id = m.id AND ms.status = 'active' ORDER BY ms.id DESC LIMIT 1) as plan_expiry,
               (SELECT mt.title FROM member_subscriptions ms JOIN membership_types mt ON ms.membership_type_id = mt.id WHERE ms.member_id = m.id AND ms.status = 'active' ORDER BY ms.id DESC LIMIT 1) as plan_title
        FROM members m
        ORDER BY m.id DESC LIMIT 100
    ");
    jsonResponse(true, $stmt->fetchAll());

} elseif ($action === 'today_birthdays') {
    $stmt = $db->query("
        SELECT m.*, mt.title as plan_title, ms.end_date as plan_expiry
        FROM members m
        LEFT JOIN member_subscriptions ms ON m.id = ms.member_id AND ms.status = 'active'
        LEFT JOIN membership_types mt ON ms.membership_type_id = mt.id
        WHERE m.dob IS NOT NULL 
          AND MONTH(m.dob) = MONTH(CURRENT_DATE()) 
          AND DAY(m.dob) = DAY(CURRENT_DATE())
        GROUP BY m.id
        ORDER BY m.name ASC
    ");
    jsonResponse(true, $stmt->fetchAll(), 'Today\'s birthdays fetched');

} elseif ($action === 'update') {
    $input = json_decode(file_get_contents('php://input'), true);
    $id = intval($input['id'] ?? 0);
    $name = trim($input['name'] ?? '');
    $phone = trim($input['phone'] ?? '');
    $email = trim($input['email'] ?? '');
    $gender = $input['gender'] ?? 'Male';
    $dob = !empty($input['dob']) ? $input['dob'] : null;
    $emergencyContact = trim($input['emergency_contact'] ?? '');
    $parentName = trim($input['parent_name'] ?? '');
    $biometricId = trim($input['biometric_id'] ?? '');
    $status = $input['status'] ?? 'active';
    $notes = trim($input['notes'] ?? '');

    if ($id <= 0 || empty($name) || empty($phone)) {
        jsonResponse(false, [], 'ID, Full Name and Mobile Number are required', 400);
    }

    $stmt = $db->prepare("
        UPDATE members 
        SET name = ?, phone = ?, whatsapp_no = ?, email = ?, gender = ?, dob = ?, emergency_contact = ?, parent_name = ?, biometric_id = ?, status = ?, notes = ?
        WHERE id = ?
    ");
    $stmt->execute([$name, $phone, $phone, $email, $gender, $dob, $emergencyContact, $parentName, $biometricId, $status, $notes, $id]);

    logAuditAction(1, 'Update Member Profile', 'MEMBERS', null, ['id' => $id, 'name' => $name, 'phone' => $phone]);

    jsonResponse(true, [], 'Member profile updated successfully');

} elseif ($action === 'delete') {
    $id = intval($_GET['id'] ?? 0);
    if ($id <= 0) {
        jsonResponse(false, [], 'Valid member ID required', 400);
    }

    $stmt = $db->prepare("DELETE FROM members WHERE id = ?");
    $stmt->execute([$id]);

    logAuditAction(1, 'Delete Member', 'MEMBERS', ['id' => $id], null);
    jsonResponse(true, [], 'Member deleted successfully');

} elseif ($action === 'profile_details') {
    $id = intval($_GET['id'] ?? 0);
    if ($id <= 0) {
        jsonResponse(false, [], 'Valid member ID required', 400);
    }

    // Fetch Member Core Record
    $stmt = $db->prepare("SELECT * FROM members WHERE id = ?");
    $stmt->execute([$id]);
    $member = $stmt->fetch();

    if (!$member) {
        jsonResponse(false, [], 'Member not found', 404);
    }

    // Fetch Subscriptions History
    $stmtSub = $db->prepare("
        SELECT s.*, mt.title as plan_title 
        FROM member_subscriptions s 
        JOIN membership_types mt ON s.membership_type_id = mt.id 
        WHERE s.member_id = ? 
        ORDER BY s.id DESC
    ");
    $stmtSub->execute([$id]);
    $subscriptions = $stmtSub->fetchAll();

    // Fetch Sales / Invoices History
    $stmtSales = $db->prepare("SELECT * FROM sales WHERE member_id = ? ORDER BY id DESC");
    $stmtSales->execute([$id]);
    $sales = $stmtSales->fetchAll();

    // Fetch Attendance Logs & Total Count
    $stmtAtt = $db->prepare("SELECT * FROM attendance WHERE member_id = ? ORDER BY id DESC LIMIT 20");
    $stmtAtt->execute([$id]);
    $attendanceLogs = $stmtAtt->fetchAll();

    $stmtAttCount = $db->prepare("SELECT COUNT(*) FROM attendance WHERE member_id = ?");
    $stmtAttCount->execute([$id]);
    $totalVisits = $stmtAttCount->fetchColumn();

    // Fetch Fitness Measurements
    $stmtFit = $db->prepare("SELECT * FROM member_fitness WHERE member_id = ?");
    $stmtFit->execute([$id]);
    $fitness = $stmtFit->fetch() ?: [
        'weight_kg' => 72.5,
        'height_cm' => 175,
        'bmi' => 23.7,
        'body_fat_pct' => 16.5,
        'workout_plan' => "Day 1: Chest & Triceps\nDay 2: Back & Biceps\nDay 3: Legs & Shoulders\nDay 4: Cardio & Abs",
        'diet_plan' => "Breakfast: Oats + Protein Shake\nLunch: Chicken/Tofu Rice\nSnack: Almonds & Fruit\nDinner: Grilled Fish & Salad"
    ];

    // Fetch Marketing & WhatsApp Logs for this member
    $stmtLogs = $db->prepare("SELECT * FROM marketing_logs WHERE member_id = ? ORDER BY id DESC LIMIT 10");
    $stmtLogs->execute([$id]);
    $marketingLogs = $stmtLogs->fetchAll();

    jsonResponse(true, [
        'member' => $member,
        'subscriptions' => $subscriptions,
        'sales' => $sales,
        'attendance_logs' => $attendanceLogs,
        'total_visits' => $totalVisits,
        'fitness' => $fitness,
        'marketing_logs' => $marketingLogs
    ]);

} else {
    jsonResponse(false, [], 'Invalid action', 400);
}
