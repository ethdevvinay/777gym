<?php
/**
 * Membership Plans, Subscriptions, Freeze Limit Enforcement & Status Engine Controller
 */
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');
$action = $_GET['action'] ?? $_POST['action'] ?? '';
$db = getDB();

if ($action === 'list' || $action === 'types' || $action === 'get_all' || empty($action)) {
    $stmt = $db->query("SELECT * FROM membership_types ORDER BY duration_days ASC, id ASC");
    $plans = $stmt->fetchAll();
    jsonResponse(true, $plans, 'Membership plans retrieved successfully');

} elseif ($action === 'save_plan') {
    // Add or Edit Membership Plan Template with Max Freeze Count Limit
    $id = intval($_POST['id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $category = $_POST['category'] ?? 'monthly';
    $months = intval($_POST['duration_months'] ?? 1);
    $days = intval($_POST['duration_days'] ?? ($months * 30));
    $price = floatval($_POST['price'] ?? 0);
    $regFee = floatval($_POST['reg_fee'] ?? 0);
    $joiningFee = floatval($_POST['joining_fee'] ?? 0);
    $discount = floatval($_POST['discount'] ?? 0);
    $tax = floatval($_POST['tax_pct'] ?? 18);
    $freeze = isset($_POST['freeze_facility']) ? 1 : 1;
    $maxFreezeDays = intval($_POST['max_freeze_days'] ?? 15);
    
    // Default freeze counts: 1M->1, 3M->2, 6M->3, 12M->4 unless custom specified
    $defaultFreezeCount = ($months <= 1 ? 1 : ($months == 3 ? 2 : ($months == 6 ? 3 : 4)));
    $maxFreezeCount = isset($_POST['max_freeze_count']) ? intval($_POST['max_freeze_count']) : $defaultFreezeCount;
    
    $timing = trim($_POST['access_timing'] ?? 'Full Day (6:00 AM - 10:00 PM)');
    $branchAccess = $_POST['branch_access'] ?? 'single_branch';
    $services = trim($_POST['included_services'] ?? 'Gym Floor, Cardio Zone');
    $desc = trim($_POST['description'] ?? '');
    $status = $_POST['status'] ?? 'active';

    if (empty($title) || $price <= 0) {
        jsonResponse(false, [], 'Title and valid price are required', 400);
    }

    if ($id > 0) {
        $stmt = $db->prepare("
            UPDATE membership_types 
            SET title = ?, category = ?, duration_months = ?, duration_days = ?, price = ?, reg_fee = ?, joining_fee = ?, discount = ?, tax_pct = ?, freeze_facility = ?, max_freeze_days = ?, max_freeze_count = ?, access_timing = ?, branch_access = ?, included_services = ?, description = ?, status = ? 
            WHERE id = ?
        ");
        $stmt->execute([$title, $category, $months, $days, $price, $regFee, $joiningFee, $discount, $tax, $freeze, $maxFreezeDays, $maxFreezeCount, $timing, $branchAccess, $services, $desc, $status, $id]);
        logAuditAction(1, 'Update Membership Plan', 'MEMBERSHIPS', null, ['id' => $id, 'title' => $title, 'max_freeze_count' => $maxFreezeCount]);
        jsonResponse(true, ['id' => $id], 'Membership plan updated successfully');
    } else {
        $stmt = $db->prepare("
            INSERT INTO membership_types (title, category, duration_months, duration_days, price, reg_fee, joining_fee, discount, tax_pct, freeze_facility, max_freeze_days, max_freeze_count, access_timing, branch_access, included_services, description, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$title, $category, $months, $days, $price, $regFee, $joiningFee, $discount, $tax, $freeze, $maxFreezeDays, $maxFreezeCount, $timing, $branchAccess, $services, $desc, $status]);
        $newId = $db->lastInsertId();
        logAuditAction(1, 'Create Membership Plan', 'MEMBERSHIPS', null, ['id' => $newId, 'title' => $title, 'max_freeze_count' => $maxFreezeCount]);
        jsonResponse(true, ['id' => $newId], 'New Membership plan created successfully');
    }

} elseif ($action === 'get_plan') {
    $id = intval($_GET['id'] ?? 0);
    $stmt = $db->prepare("SELECT * FROM membership_types WHERE id = ?");
    $stmt->execute([$id]);
    $plan = $stmt->fetch();
    if ($plan) {
        jsonResponse(true, $plan, 'Plan details retrieved');
    } else {
        jsonResponse(false, [], 'Plan not found', 404);
    }

} elseif ($action === 'delete_plan') {
    $id = intval($_POST['id'] ?? $_GET['id'] ?? 0);
    if ($id <= 0) {
        jsonResponse(false, [], 'Valid plan ID required', 400);
    }
    $stmt = $db->prepare("DELETE FROM membership_types WHERE id = ?");
    $stmt->execute([$id]);
    jsonResponse(true, [], 'Plan deleted successfully');

} elseif ($action === 'edit_subscription') {
    // Edit Existing Member Subscription (Plan, Dates, Price, Status)
    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;

    $subId = intval($input['subscription_id'] ?? $input['id'] ?? 0);
    $planTypeId = intval($input['membership_type_id'] ?? 0);
    $startDate = trim($input['start_date'] ?? '');
    $endDate = trim($input['end_date'] ?? '');
    $pricePaid = floatval($input['price_paid'] ?? 0);
    $status = $input['status'] ?? 'active';

    if ($subId <= 0 || empty($startDate) || empty($endDate)) {
        jsonResponse(false, [], 'Valid subscription ID, start date and expiry date are required', 400);
    }

    $subStmt = $db->prepare("SELECT * FROM member_subscriptions WHERE id = ?");
    $subStmt->execute([$subId]);
    $sub = $subStmt->fetch();

    if (!$sub) {
        jsonResponse(false, [], 'Subscription not found', 404);
    }

    try {
        $db->beginTransaction();

        $planIdToSet = ($planTypeId > 0) ? $planTypeId : $sub['membership_type_id'];

        $upd = $db->prepare("
            UPDATE member_subscriptions 
            SET membership_type_id = ?, start_date = ?, end_date = ?, price_paid = ?, status = ?, updated_at = NOW() 
            WHERE id = ?
        ");
        $upd->execute([$planIdToSet, $startDate, $endDate, $pricePaid, $status, $subId]);

        // Update member general status
        $memUpd = $db->prepare("UPDATE members SET status = ? WHERE id = ?");
        $memUpd->execute([$status, $sub['member_id']]);

        $db->commit();

        logAuditAction($_SESSION['user_id'] ?? 1, 'Edit Member Subscription', 'MEMBERSHIPS', ['sub_id' => $subId], [
            'plan_id' => $planIdToSet,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'status' => $status
        ]);

        jsonResponse(true, [
            'subscription_id' => $subId,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'status' => $status
        ], 'Member subscription and dates updated successfully!');

    } catch (Exception $e) {
        $db->rollBack();
        jsonResponse(false, [], 'Failed to update subscription: ' . $e->getMessage(), 500);
    }

} elseif ($action === 'assign_plan' || $action === 'add_member_subscription') {
    // Add / Assign New Subscription Plan to Member
    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $memberId = intval($input['member_id'] ?? 0);
    $planId = intval($input['membership_type_id'] ?? 0);
    $startDate = !empty($input['start_date']) ? trim($input['start_date']) : date('Y-m-d');
    $status = $input['status'] ?? 'active';

    if ($memberId <= 0 || $planId <= 0) {
        jsonResponse(false, [], 'Member ID and Membership Plan are required', 400);
    }

    $planStmt = $db->prepare("SELECT * FROM membership_types WHERE id = ?");
    $planStmt->execute([$planId]);
    $plan = $planStmt->fetch();

    if (!$plan) {
        jsonResponse(false, [], 'Selected plan does not exist', 404);
    }

    // Check if member already has an active subscription currently running
    $existSubStmt = $db->prepare("
        SELECT MAX(end_date) as latest_end 
        FROM member_subscriptions 
        WHERE member_id = ? AND status IN ('active', 'frozen') AND end_date >= CURRENT_DATE()
    ");
    $existSubStmt->execute([$memberId]);
    $latestEnd = $existSubStmt->fetchColumn();

    if (!empty($latestEnd) && strtotime($latestEnd) >= strtotime('today')) {
        // Queue new plan starting the day after current plan ends (unless a future custom start_date was provided)
        if (empty($input['start_date']) || $input['start_date'] === date('Y-m-d')) {
            $startDate = date('Y-m-d', strtotime("{$latestEnd} + 1 day"));
        } else {
            $startDate = trim($input['start_date']);
        }
    } else {
        $startDate = !empty($input['start_date']) ? trim($input['start_date']) : date('Y-m-d');
        // Deactivate previous expired subscriptions for clean state
        $db->prepare("UPDATE member_subscriptions SET status = 'expired' WHERE member_id = ? AND status = 'active'")->execute([$memberId]);
    }

    $months = intval($plan['duration_months'] ?? 0);
    $days = !empty($plan['duration_days']) ? intval($plan['duration_days']) : ($months > 0 ? $months * 30 : 1);
    $endDate = (!empty($input['end_date']) && strtotime($input['end_date']) > strtotime($startDate)) ? trim($input['end_date']) : date('Y-m-d', strtotime("{$startDate} + {$days} days"));
    $pricePaid = isset($input['price_paid']) ? floatval($input['price_paid']) : floatval($plan['price']);

    $ins = $db->prepare("
        INSERT INTO member_subscriptions (member_id, membership_type_id, start_date, end_date, original_end_date, price_paid, status) 
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $ins->execute([$memberId, $planId, $startDate, $endDate, $endDate, $pricePaid, $status]);
    $newSubId = $db->lastInsertId();

    $db->prepare("UPDATE members SET status = ? WHERE id = ?")->execute([$status, $memberId]);

    logAuditAction($_SESSION['user_id'] ?? 1, 'Assign Membership Plan', 'MEMBERSHIPS', null, [
        'member_id' => $memberId,
        'plan_id' => $planId,
        'plan_title' => $plan['title'],
        'end_date' => $endDate
    ]);

    // Auto-dispatch Plan Confirmation WhatsApp Message
    try {
        $mRow = $db->query("SELECT name, phone, member_code FROM members WHERE id = " . intval($memberId))->fetch();
        if ($mRow && !empty($mRow['phone'])) {
            $isQueued = (strtotime($startDate) > strtotime('today'));
            $planMsg = "🏋️ *Membership Confirmed — THE CLUB 777®* 🎉\n\nHello *{$mRow['name']}*!\nYour membership plan has been successfully activated in the system.\n\n⭐ *Plan:* {$plan['title']}\n📅 *Start Date:* " . date('d M Y', strtotime($startDate)) . ($isQueued ? " _(Queued: Starts after current plan finishes)_" : "") . "\n🏁 *Valid Till:* " . date('d M Y', strtotime($endDate)) . "\n💰 *Price Paid:* ₹" . number_format($pricePaid, 2) . "\n\n📍 *Address:* Behind Shehnai Garden, Near Railway Station, Jhajjar\n📞 *Helpline:* 8053576777, 8053570777\n\n_Stay Consistent & Crush Your Fitness Goals!_ 💪";
            
            sendWhatsAppMessage($mRow['phone'], $planMsg);
            $db->prepare("INSERT INTO marketing_logs (member_id, recipient_phone, message_type, trigger_event, message_body, sent_status, sent_at) VALUES (?, ?, 'whatsapp', 'plan_assigned', ?, 'sent', NOW())")
               ->execute([$memberId, $mRow['phone'], $planMsg]);
        }
    } catch (Exception $e) {}

    jsonResponse(true, [
        'subscription_id' => $newSubId,
        'member_id' => $memberId,
        'plan_title' => $plan['title'],
        'start_date' => $startDate,
        'end_date' => $endDate,
        'status' => $status
    ], "Membership Plan '{$plan['title']}' assigned successfully!");

} elseif ($action === 'freeze') {
    // 1-Click Membership Freeze Engine with Plan-based Freeze Limit Validation
    $subId = intval($_POST['subscription_id'] ?? 0);
    $memberId = intval($_POST['member_id'] ?? 0);
    $freezeDate = !empty($_POST['freeze_date']) ? trim($_POST['freeze_date']) : date('Y-m-d');
    $reason = trim($_POST['reason'] ?? 'Member Requested Freeze / Travel / Medical');
    $userId = $_SESSION['user_id'] ?? 1;

    if ($subId <= 0) {
        jsonResponse(false, [], 'Valid subscription ID required', 400);
    }

    $subStmt = $db->prepare("
        SELECT s.*, m.name as member_name, mt.title as plan_title, mt.duration_months, mt.max_freeze_count 
        FROM member_subscriptions s 
        JOIN members m ON s.member_id = m.id 
        JOIN membership_types mt ON s.membership_type_id = mt.id 
        WHERE s.id = ?
    ");
    $subStmt->execute([$subId]);
    $sub = $subStmt->fetch();

    if (!$sub) {
        jsonResponse(false, [], 'Subscription not found', 404);
    }

    if ($sub['status'] === 'frozen') {
        jsonResponse(false, [], 'This subscription is already frozen', 400);
    }

    // Freeze Limit Enforcement: Check if max allowed freezes reached!
    $months = intval($sub['duration_months'] ?: 1);
    $defaultLimit = ($months <= 1 ? 1 : ($months == 3 ? 2 : ($months == 6 ? 3 : 4)));
    $allowedFreezeCount = intval($sub['max_freeze_count'] ?: $defaultLimit);
    $currentFreezeCount = intval($sub['freeze_count'] ?: 0);

    if ($currentFreezeCount >= $allowedFreezeCount) {
        jsonResponse(false, [], "❌ Freeze Limit Reached: This {$sub['plan_title']} allows maximum {$allowedFreezeCount} freeze(s). You have already used all {$currentFreezeCount} freeze(s) for this subscription!", 400);
    }

    $endDateTimestamp = strtotime($sub['end_date']);
} elseif ($action === 'freeze') {
    // Freeze/Pause Active Membership Subscription
    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $subId = intval($input['subscription_id'] ?? 0);
    $freezeDate = !empty($input['freeze_start_date']) ? trim($input['freeze_start_date']) : (!empty($input['freeze_date']) ? trim($input['freeze_date']) : date('Y-m-d'));
    $reason = trim($input['reason'] ?? 'Member Request');
    $userId = $_SESSION['user_id'] ?? 1;

    if ($subId <= 0) {
        jsonResponse(false, [], 'Valid subscription ID required', 400);
    }

    $subStmt = $db->prepare("
        SELECT s.*, m.name as member_name, IFNULL(mt.title, 'Gym Membership') as plan_title, IFNULL(mt.max_freeze_count, 2) as max_freeze_count, IFNULL(mt.max_freeze_days, 30) as max_freeze_days 
        FROM member_subscriptions s 
        JOIN members m ON s.member_id = m.id 
        LEFT JOIN membership_types mt ON s.membership_type_id = mt.id 
        WHERE s.id = ?
    ");
    $subStmt->execute([$subId]);
    $sub = $subStmt->fetch();

    if (!$sub) {
        jsonResponse(false, [], 'Subscription not found', 404);
    }

    if ($sub['status'] === 'frozen') {
        jsonResponse(false, [], 'This subscription is already frozen', 400);
    }

    $allowedFreezeCount = intval($sub['max_freeze_count'] ?: 2);
    $currentFreezeCount = intval($sub['freeze_count'] ?: 0);

    $endDateTimestamp = strtotime($sub['end_date']);
    $freezeTimestamp = strtotime($freezeDate);

    // Calculate exact remaining balance days
    $remainingDays = max(1, (int) ceil(($endDateTimestamp - $freezeTimestamp) / 86400));
    $daysUsed = max(0, (int) ceil(($freezeTimestamp - strtotime($sub['start_date'])) / 86400));

    $freezeType = in_array($input['freeze_type'] ?? '', ['temporary', 'permanent']) ? $input['freeze_type'] : 'permanent';
    $tempDays = intval($input['freeze_days'] ?? ($input['temp_freeze_days'] ?? 7));
    $autoUnfreezeDate = null;

    if ($freezeType === 'temporary') {
        $tempDays = max(1, $tempDays);
        $autoUnfreezeDate = date('Y-m-d', strtotime("{$freezeDate} + {$tempDays} days"));
    }

    try {
        $db->beginTransaction();

        $newFreezeCount = $currentFreezeCount + 1;

        $upd = $db->prepare("
            UPDATE member_subscriptions 
            SET status = 'frozen', freeze_count = ?, freeze_type = ?, auto_unfreeze_date = ?, freeze_start_date = ?, frozen_remaining_days = ? 
            WHERE id = ?
        ");
        $upd->execute([$newFreezeCount, $freezeType, $autoUnfreezeDate, $freezeDate, $remainingDays, $subId]);

        $updMem = $db->prepare("UPDATE members SET status = 'frozen' WHERE id = ?");
        $updMem->execute([$sub['member_id']]);

        $ins = $db->prepare("
            INSERT INTO freeze_history (subscription_id, freeze_type, member_id, start_date, end_date, freeze_days, auto_unfreeze_date, reason) 
            VALUES (?, ?, ?, ?, NULL, ?, ?, ?)
        ");
        $ins->execute([$subId, $freezeType, $sub['member_id'], $freezeDate, $remainingDays, $autoUnfreezeDate, $reason]);

        $db->commit();

        logAuditAction($userId, 'Freeze Membership (' . ucfirst($freezeType) . ')', 'MEMBERSHIPS', ['sub_id' => $subId, 'end_date' => $sub['end_date']], [
            'freeze_type' => $freezeType,
            'freeze_date' => $freezeDate,
            'auto_unfreeze_date' => $autoUnfreezeDate,
            'freeze_attempt' => "{$newFreezeCount} of {$allowedFreezeCount}",
            'remaining_balance_days' => $remainingDays,
            'days_used' => $daysUsed,
            'reason' => $reason
        ]);

        $msg = ($freezeType === 'temporary') 
            ? "❄️ Temporary Freeze Activated for {$tempDays} Days! Member paused until " . date('d M Y', strtotime($autoUnfreezeDate)) . ". All {$remainingDays} balance days are safely locked."
            : "❄️ Permanent / Indefinite Freeze Activated! Member paused. All {$remainingDays} remaining balance days are safely locked and will resume on manual unfreeze.";

        jsonResponse(true, [
            'subscription_id' => $subId,
            'freeze_type' => $freezeType,
            'freeze_date' => date('d M Y', strtotime($freezeDate)),
            'auto_unfreeze_date' => $autoUnfreezeDate ? date('d M Y', strtotime($autoUnfreezeDate)) : null,
            'freeze_count_used' => $newFreezeCount,
            'max_freeze_count' => $allowedFreezeCount,
            'days_used' => $daysUsed,
            'remaining_days' => $remainingDays
        ], $msg);

    } catch (Exception $e) {
        $db->rollBack();
        jsonResponse(false, [], 'Freeze failed: ' . $e->getMessage(), 500);
    }

} elseif ($action === 'unfreeze') {
    // 1-Click Membership Unfreeze / Resume Engine
    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $subId = intval($input['subscription_id'] ?? 0);
    $memberId = intval($input['member_id'] ?? 0);
    $unfreezeDate = !empty($input['unfreeze_date']) ? trim($input['unfreeze_date']) : date('Y-m-d');
    $userId = $_SESSION['user_id'] ?? 1;

    if ($subId <= 0 && $memberId > 0) {
        // Find active frozen subscription for this member
        $findSub = $db->prepare("SELECT id FROM member_subscriptions WHERE member_id = ? AND status = 'frozen' ORDER BY id DESC LIMIT 1");
        $findSub->execute([$memberId]);
        $subId = intval($findSub->fetchColumn() ?: 0);
    }

    if ($subId <= 0) {
        // Fallback: If member is frozen but has no frozen sub, just reactivate member
        if ($memberId > 0) {
            $db->prepare("UPDATE members SET status = 'active' WHERE id = ?")->execute([$memberId]);
            jsonResponse(true, ['member_id' => $memberId], '▶️ Member status reactivated to Active!');
        }
        jsonResponse(false, [], 'Valid subscription ID required', 400);
    }

    $subStmt = $db->prepare("
        SELECT s.*, m.name as member_name, IFNULL(mt.title, 'Gym Membership') as plan_title 
        FROM member_subscriptions s 
        JOIN members m ON s.member_id = m.id 
        LEFT JOIN membership_types mt ON s.membership_type_id = mt.id 
        WHERE s.id = ?
    ");
    $subStmt->execute([$subId]);
    $sub = $subStmt->fetch();

    if (!$sub) {
        jsonResponse(false, [], 'Subscription not found', 404);
    }

    if ($sub['status'] !== 'frozen') {
        // Even if sub is not marked frozen, make sure member is active
        $db->prepare("UPDATE members SET status = 'active' WHERE id = ?")->execute([$sub['member_id']]);
        jsonResponse(true, ['subscription_id' => $subId], 'Member is already active.');
    }

    $remainingDays = intval($sub['frozen_remaining_days']);
    if ($remainingDays <= 0) {
        $freezeStart = !empty($sub['freeze_start_date']) ? strtotime($sub['freeze_start_date']) : strtotime('today');
        $endTs = strtotime($sub['end_date']);
        $remainingDays = max(1, (int) ceil(($endTs - $freezeStart) / 86400));
    }

    // Calculate new expiry date starting from the unfreeze resumption date + locked remaining days
    $newEndDate = date('Y-m-d', strtotime("{$unfreezeDate} + {$remainingDays} days"));

    // Calculate total days spent on freeze
    $freezeStartDate = !empty($sub['freeze_start_date']) ? $sub['freeze_start_date'] : $unfreezeDate;
    $daysSpentOnFreeze = max(1, (int) ceil((strtotime($unfreezeDate) - strtotime($freezeStartDate)) / 86400));
    $newTotalFreezeDays = intval($sub['freeze_days_used']) + $daysSpentOnFreeze;

    try {
        $db->beginTransaction();

        $upd = $db->prepare("
            UPDATE member_subscriptions 
            SET status = 'active', end_date = ?, freeze_days_used = ?, frozen_remaining_days = 0, freeze_start_date = NULL, auto_unfreeze_date = NULL 
            WHERE id = ?
        ");
        $upd->execute([$newEndDate, $newTotalFreezeDays, $subId]);

        $updMem = $db->prepare("UPDATE members SET status = 'active' WHERE id = ?");
        $updMem->execute([$sub['member_id']]);

        // Close out open freeze_history record
        $histUpd = $db->prepare("
            UPDATE freeze_history 
            SET end_date = ? 
            WHERE subscription_id = ? AND end_date IS NULL 
            ORDER BY id DESC LIMIT 1
        ");
        $histUpd->execute([$unfreezeDate, $subId]);

        $db->commit();

        logAuditAction($userId, 'Unfreeze Membership (Resume)', 'MEMBERSHIPS', ['sub_id' => $subId, 'old_end_date' => $sub['end_date']], [
            'unfreeze_date' => $unfreezeDate,
            'restored_balance_days' => $remainingDays,
            'new_end_date' => $newEndDate,
            'days_spent_on_freeze' => $daysSpentOnFreeze
        ]);

        jsonResponse(true, [
            'subscription_id' => $subId,
            'unfreeze_date' => date('d M Y', strtotime($unfreezeDate)),
            'remaining_days' => $remainingDays,
            'new_end_date' => date('d M Y', strtotime($newEndDate)),
            'new_end_date_raw' => $newEndDate
        ], "▶️ Membership Resumed on " . date('d M Y', strtotime($unfreezeDate)) . "! All {$remainingDays} remaining balance days restored. New expiry date is " . date('d M Y', strtotime($newEndDate)) . ".");

    } catch (Exception $e) {
        $db->rollBack();
        jsonResponse(false, [], 'Unfreeze failed: ' . $e->getMessage(), 500);
    }

} else {
    jsonResponse(false, [], 'Invalid action', 400);
}

