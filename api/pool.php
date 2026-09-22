<?php
/**
 * Swimming Pool Management, Pool Plans CRUD, Member Subscriptions & Pool Check-In API
 */
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');
$action = $_GET['action'] ?? $_POST['action'] ?? '';
$db = getDB();

if ($action === 'list_plans') {
    // List all swimming pool plans
    $status = $_GET['status'] ?? '';
    $sql = "SELECT * FROM pool_plans";
    if ($status === 'active') {
        $sql .= " WHERE status = 'active'";
    }
    $sql .= " ORDER BY price ASC";
    
    $stmt = $db->query($sql);
    $plans = $stmt->fetchAll();
    jsonResponse(true, $plans, 'Pool plans fetched successfully');

} elseif ($action === 'get_plan') {
    // Get single pool plan for editing
    $id = intval($_GET['id'] ?? 0);
    if ($id <= 0) {
        jsonResponse(false, [], 'Invalid plan ID', 400);
    }
    $stmt = $db->prepare("SELECT * FROM pool_plans WHERE id = ?");
    $stmt->execute([$id]);
    $plan = $stmt->fetch();
    if (!$plan) {
        jsonResponse(false, [], 'Pool plan not found', 404);
    }
    jsonResponse(true, $plan, 'Pool plan details retrieved');

} elseif ($action === 'save_plan') {
    // Add or Edit a Swimming Pool Plan
    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $id = !empty($input['id']) ? intval($input['id']) : null;
    $title = trim($input['title'] ?? '');
    $category = trim($input['category'] ?? 'monthly');
    $durationMonths = intval($input['duration_months'] ?? 1);
    $durationDays = intval($input['duration_days'] ?? ($durationMonths > 0 ? $durationMonths * 30 : 1));
    $price = floatval($input['price'] ?? 0);
    $sessionsLimit = intval($input['sessions_limit'] ?? 0); // 0 = unlimited
    $slotTiming = trim($input['slot_timing'] ?? 'Morning 6:00 AM - 10:00 AM & Evening 5:00 PM - 9:00 PM');
    $coachName = trim($input['coach_name'] ?? 'Certified Swim Coach & Lifeguard');
    $description = trim($input['description'] ?? '');
    $status = trim($input['status'] ?? 'active');
    $userId = $_SESSION['user_id'] ?? 1;

    if (empty($title) || $price < 0) {
        jsonResponse(false, [], 'Plan title and valid price are required', 400);
    }

    try {
        if ($id) {
            // Update existing Pool Plan
            $stmt = $db->prepare("
                UPDATE pool_plans 
                SET title = ?, category = ?, duration_months = ?, duration_days = ?, price = ?, sessions_limit = ?, slot_timing = ?, coach_name = ?, description = ?, status = ?
                WHERE id = ?
            ");
            $stmt->execute([$title, $category, $durationMonths, $durationDays, $price, $sessionsLimit, $slotTiming, $coachName, $description, $status, $id]);

            logAuditAction($userId, 'Edit Swimming Pool Plan', 'Pool', ['plan_id' => $id], [
                'title' => $title,
                'price' => $price,
                'duration_days' => $durationDays
            ]);

            jsonResponse(true, ['id' => $id], 'Swimming Pool Plan updated successfully!');
        } else {
            // Insert new Pool Plan
            $stmt = $db->prepare("
                INSERT INTO pool_plans (title, category, duration_months, duration_days, price, sessions_limit, slot_timing, coach_name, description, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$title, $category, $durationMonths, $durationDays, $price, $sessionsLimit, $slotTiming, $coachName, $description, $status]);
            $newId = $db->lastInsertId();

            logAuditAction($userId, 'Create Swimming Pool Plan', 'Pool', null, [
                'id' => $newId,
                'title' => $title,
                'price' => $price
            ]);

            jsonResponse(true, ['id' => $newId], 'New Swimming Pool Plan created successfully!');
        }
    } catch (Exception $e) {
        jsonResponse(false, [], 'Failed to save plan: ' . $e->getMessage(), 500);
    }

} elseif ($action === 'delete_plan') {
    // Delete Pool Plan
    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $id = intval($input['id'] ?? 0);
    $userId = $_SESSION['user_id'] ?? 1;

    if ($id <= 0) {
        jsonResponse(false, [], 'Valid plan ID required', 400);
    }

    // Check if active subscriptions exist
    $subCount = $db->prepare("SELECT COUNT(*) FROM pool_subscriptions WHERE pool_plan_id = ? AND status = 'active'");
    $subCount->execute([$id]);
    if ($subCount->fetchColumn() > 0) {
        // Soft delete / mark inactive
        $stmt = $db->prepare("UPDATE pool_plans SET status = 'inactive' WHERE id = ?");
        $stmt->execute([$id]);
        jsonResponse(true, [], 'Plan has active subscribers. Marked as inactive instead of deleting.');
    } else {
        $stmt = $db->prepare("DELETE FROM pool_plans WHERE id = ?");
        $stmt->execute([$id]);
        logAuditAction($userId, 'Delete Pool Plan', 'Pool', ['plan_id' => $id], null);
        jsonResponse(true, [], 'Swimming Pool Plan deleted successfully!');
    }

} elseif ($action === 'assign_member_pass' || $action === 'assign_pass') {
    // Assign a Pool Plan Pass to a Member
    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $memberId = intval($input['member_id'] ?? 0);
    $planId = intval($input['pool_plan_id'] ?? 0);
    $startDate = !empty($input['start_date']) ? $input['start_date'] : date('Y-m-d');
    $slotAssigned = trim($input['slot_assigned'] ?? 'Morning Slot (6-9 AM)');
    $pricePaid = floatval($input['price_paid'] ?? 0);
    $userId = $_SESSION['user_id'] ?? 1;

    if ($memberId <= 0 || $planId <= 0) {
        jsonResponse(false, [], 'Member and Pool Plan must be selected', 400);
    }

    $planStmt = $db->prepare("SELECT * FROM pool_plans WHERE id = ?");
    $planStmt->execute([$planId]);
    $plan = $planStmt->fetch();
    if (!$plan) {
        jsonResponse(false, [], 'Selected pool plan does not exist', 404);
    }

    $days = $plan['duration_days'] ?: 30;
    $endDate = date('Y-m-d', strtotime("{$startDate} +{$days} days"));
    $sessionsTotal = $plan['sessions_limit'] ?: 0;
    $price = ($pricePaid > 0) ? $pricePaid : $plan['price'];

    try {
        $stmt = $db->prepare("
            INSERT INTO pool_subscriptions (member_id, pool_plan_id, start_date, end_date, sessions_total, sessions_used, sessions_remaining, price_paid, slot_assigned, status)
            VALUES (?, ?, ?, ?, ?, 0, ?, ?, ?, 'active')
        ");
        $stmt->execute([$memberId, $planId, $startDate, $endDate, $sessionsTotal, $sessionsTotal, $price, $slotAssigned]);
        $subId = $db->lastInsertId();

        logAuditAction($userId, 'Assign Pool Membership Pass', 'Pool', null, [
            'member_id' => $memberId,
            'plan' => $plan['title'],
            'end_date' => $endDate
        ]);

        jsonResponse(true, ['subscription_id' => $subId], "Swimming Pool pass assigned successfully until " . date('d M Y', strtotime($endDate)));
    } catch (Exception $e) {
        jsonResponse(false, [], 'Failed to assign pool pass: ' . $e->getMessage(), 500);
    }

} elseif ($action === 'checkin_pool' || $action === 'check_in') {
    // 1-Click Pool Check-in / Punch
    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $subId = intval($input['subscription_id'] ?? 0);
    $notes = trim($input['notes'] ?? 'Daily Pool Entry');
    $userId = $_SESSION['user_id'] ?? 1;

    if ($subId <= 0) {
        jsonResponse(false, [], 'Valid Pool Subscription ID required', 400);
    }

    $subStmt = $db->prepare("
        SELECT ps.*, m.name as member_name, pp.title as plan_title 
        FROM pool_subscriptions ps
        JOIN members m ON ps.member_id = m.id
        JOIN pool_plans pp ON ps.pool_plan_id = pp.id
        WHERE ps.id = ?
    ");
    $subStmt->execute([$subId]);
    $sub = $subStmt->fetch();

    if (!$sub) {
        jsonResponse(false, [], 'Pool subscription not found', 404);
    }

    if ($sub['status'] !== 'active' || strtotime($sub['end_date']) < time()) {
        jsonResponse(false, [], 'This pool pass has expired or is inactive', 400);
    }

    if ($sub['sessions_total'] > 0 && $sub['sessions_remaining'] <= 0) {
        jsonResponse(false, [], 'All allotted swimming sessions for this pass have been used', 400);
    }

    try {
        $db->beginTransaction();

        // 1. Deduct session if limited
        if ($sub['sessions_total'] > 0) {
            $upd = $db->prepare("
                UPDATE pool_subscriptions 
                SET sessions_used = sessions_used + 1, sessions_remaining = GREATEST(0, sessions_remaining - 1)
                WHERE id = ?
            ");
            $upd->execute([$subId]);
        }

        // 2. Log Pool Entry
        $logStmt = $db->prepare("
            INSERT INTO pool_logs (member_id, pool_subscription_id, slot_name, notes)
            VALUES (?, ?, ?, ?)
        ");
        $logStmt->execute([$sub['member_id'], $subId, $sub['slot_assigned'], $notes]);

        $db->commit();

        $remMsg = ($sub['sessions_total'] > 0) ? " (" . ($sub['sessions_remaining'] - 1) . " sessions remaining)" : "";
        jsonResponse(true, [], "🏊 Pool Entry Verified for {$sub['member_name']}!{$remMsg}");
    } catch (Exception $e) {
        $db->rollBack();
        jsonResponse(false, [], 'Check-in failed: ' . $e->getMessage(), 500);
    }

} elseif ($action === 'list_subscribers') {
    // List all active pool subscribers
    $stmt = $db->query("
        SELECT ps.*, m.name as member_name, m.member_code, m.phone as member_phone, pp.title as plan_title, pp.slot_timing, pp.coach_name,
               DATEDIFF(ps.end_date, CURRENT_DATE()) as days_left
        FROM pool_subscriptions ps
        JOIN members m ON ps.member_id = m.id
        JOIN pool_plans pp ON ps.pool_plan_id = pp.id
        ORDER BY ps.status ASC, ps.end_date ASC
    ");
    $subs = $stmt->fetchAll();
    jsonResponse(true, $subs, 'Subscribers fetched successfully');

} else {
    jsonResponse(false, [], 'Invalid action', 400);
}
