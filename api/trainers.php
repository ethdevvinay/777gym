<?php
/**
 * Trainer & Personal Training (PT) Management API
 */
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');
$action = $_GET['action'] ?? $_POST['action'] ?? '';
$db = getDB();

if ($action === 'list') {
    $stmt = $db->query("SELECT * FROM trainers ORDER BY id ASC");
    jsonResponse(true, $stmt->fetchAll(), 'Trainers fetched');

} elseif ($action === 'add' || $action === 'edit') {
    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $id = intval($input['id'] ?? 0);
    $name = trim($input['name'] ?? '');
    $phone = trim($input['phone'] ?? '');
    $email = trim($input['email'] ?? '');
    $specialization = trim($input['specialization'] ?? 'Fitness & Strength');
    $salary = floatval($input['salary'] ?? 25000);
    $commission = floatval($input['commission_rate'] ?? 10);
    $batches = trim($input['assigned_batches'] ?? 'Morning 6-9 AM');

    if (empty($name) || empty($phone)) {
        jsonResponse(false, [], 'Name and Phone are required', 400);
    }

    if ($id > 0) {
        $stmt = $db->prepare("UPDATE trainers SET name = ?, phone = ?, email = ?, specialization = ?, salary = ?, commission_rate = ?, assigned_batches = ? WHERE id = ?");
        $stmt->execute([$name, $phone, $email, $specialization, $salary, $commission, $batches, $id]);
        logAuditAction(1, 'Update Trainer', 'TRAINERS', null, ['id' => $id, 'name' => $name]);
        jsonResponse(true, ['id' => $id], 'Trainer profile updated successfully');
    } else {
        $stmt = $db->prepare("INSERT INTO trainers (name, phone, email, specialization, joining_date, salary, commission_rate, assigned_batches) VALUES (?, ?, ?, ?, CURRENT_DATE(), ?, ?, ?)");
        $stmt->execute([$name, $phone, $email, $specialization, $salary, $commission, $batches]);
        $newId = $db->lastInsertId();
        logAuditAction(1, 'Add Trainer', 'TRAINERS', null, ['id' => $newId, 'name' => $name]);
        jsonResponse(true, ['id' => $newId], 'Trainer added successfully');
    }

} elseif ($action === 'pt_packages') {
    $stmt = $db->query("SELECT p.*, t.name as trainer_name FROM pt_packages p LEFT JOIN trainers t ON p.trainer_id = t.id ORDER BY p.id ASC");
    jsonResponse(true, $stmt->fetchAll(), 'PT packages fetched');

} elseif ($action === 'save_pt_package' || $action === 'save_package') {
    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $id = intval($input['id'] ?? 0);
    $trainerId = !empty($input['trainer_id']) ? intval($input['trainer_id']) : null;
    $title = trim($input['title'] ?? '');
    $sessionsCount = intval($input['sessions_count'] ?? $input['sessions_total'] ?? 12);
    $price = floatval($input['price'] ?? 5000);
    $validityDays = intval($input['validity_days'] ?? 60);

    if (empty($title) || $sessionsCount <= 0 || $price <= 0) {
        jsonResponse(false, [], 'Title, valid sessions count and price are required', 400);
    }

    if ($id > 0) {
        $stmt = $db->prepare("UPDATE pt_packages SET trainer_id = ?, title = ?, sessions_count = ?, price = ?, validity_days = ? WHERE id = ?");
        $stmt->execute([$trainerId, $title, $sessionsCount, $price, $validityDays, $id]);
        logAuditAction(1, 'Update PT Package', 'PERSONAL_TRAINING', null, ['id' => $id, 'title' => $title]);
        jsonResponse(true, ['id' => $id], 'Personal Training Package updated successfully!');
    } else {
        $stmt = $db->prepare("INSERT INTO pt_packages (trainer_id, title, sessions_count, price, validity_days) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$trainerId, $title, $sessionsCount, $price, $validityDays]);
        $newId = $db->lastInsertId();
        logAuditAction(1, 'Create PT Package', 'PERSONAL_TRAINING', null, ['id' => $newId, 'title' => $title]);
        jsonResponse(true, ['id' => $newId], 'Personal Training Package created successfully!');
    }

} elseif ($action === 'delete_pt_package') {
    $id = intval($_GET['id'] ?? $_POST['id'] ?? 0);
    if ($id > 0) {
        $db->prepare("DELETE FROM pt_packages WHERE id = ?")->execute([$id]);
        jsonResponse(true, [], 'PT Package deleted successfully');
    } else {
        jsonResponse(false, [], 'Invalid ID', 400);
    }

} elseif ($action === 'assign_member_pt' || $action === 'assign_pt') {
    // Assign PT Subscription directly to a member
    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $memberId = intval($input['member_id'] ?? 0);
    $packageId = intval($input['package_id'] ?? $input['pt_package_id'] ?? 0);
    $trainerId = !empty($input['trainer_id']) ? intval($input['trainer_id']) : null;
    $sessionsTotal = intval($input['sessions_total'] ?? 12);
    $pricePaid = floatval($input['price_paid'] ?? 0);
    $startDate = trim($input['start_date'] ?? date('Y-m-d'));
    $validityDays = intval($input['validity_days'] ?? 30);
    $expiryDate = !empty($input['end_date']) ? trim($input['end_date']) : (!empty($input['expiry_date']) ? trim($input['expiry_date']) : date('Y-m-d', strtotime("{$startDate} + {$validityDays} days")));

    if ($memberId <= 0 || $packageId <= 0) {
        jsonResponse(false, [], 'Member and PT Package selection are required', 400);
    }

    $ins = $db->prepare("
        INSERT INTO pt_subscriptions (member_id, pt_package_id, trainer_id, sessions_total, sessions_used, sessions_remaining, price_paid, start_date, expiry_date, status)
        VALUES (?, ?, ?, ?, 0, ?, ?, ?, ?, 'active')
    ");
    $ins->execute([$memberId, $packageId, $trainerId, $sessionsTotal, $sessionsTotal, $pricePaid, $startDate, $expiryDate]);
    $newSubId = $db->lastInsertId();

    // Also record sale invoice if pricePaid > 0 so that invoice number and receipt exist!
    if ($pricePaid > 0) {
        try {
            $invNo = 'INV-' . date('Ymd') . '-' . str_pad(rand(100, 999), 3, '0', STR_PAD_LEFT);
            $db->prepare("
                INSERT INTO sales (invoice_no, member_id, user_id, subtotal, discount, tax, total, paid_amount, due_amount, payment_method, payment_status)
                VALUES (?, ?, 1, ?, 0, 0, ?, ?, 0, 'Cash', 'paid')
            ")->execute([$invNo, $memberId, $pricePaid, $pricePaid, $pricePaid]);
            $saleId = $db->lastInsertId();

            $pkgTitle = $db->query("SELECT title FROM pt_packages WHERE id = {$packageId}")->fetchColumn() ?: 'Personal Training';
            $db->prepare("
                INSERT INTO sale_items (sale_id, item_type, item_id, item_name, qty, unit_price, total_price)
                VALUES (?, 'pt', ?, ?, 1, ?, ?)
            ")->execute([$saleId, $packageId, $pkgTitle, $pricePaid, $pricePaid]);
        } catch (Exception $e) {}
    }

    // Update member assigned trainer if set
    if ($trainerId) {
        $db->prepare("UPDATE members SET assigned_trainer_id = ? WHERE id = ?")->execute([$trainerId, $memberId]);
    }

    logAuditAction(1, 'Assign PT Subscription', 'PERSONAL_TRAINING', null, [
        'member_id' => $memberId,
        'package_id' => $packageId,
        'sessions' => $sessionsTotal
    ]);

    jsonResponse(true, [
        'pt_subscription_id' => $newSubId,
        'member_id' => $memberId,
        'sessions_total' => $sessionsTotal,
        'sessions_remaining' => $sessionsTotal
    ], 'Personal Training Package assigned to member successfully!');

} elseif ($action === 'edit_member_pt') {
    // Edit existing PT Subscription
    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $id = intval($input['id'] ?? 0);
    $trainerId = !empty($input['trainer_id']) ? intval($input['trainer_id']) : null;
    $sessionsTotal = intval($input['sessions_total'] ?? 12);
    $sessionsUsed = intval($input['sessions_used'] ?? 0);
    $sessionsRemaining = max(0, $sessionsTotal - $sessionsUsed);
    $pricePaid = floatval($input['price_paid'] ?? 0);
    $expiryDate = trim($input['expiry_date'] ?? date('Y-m-d'));
    $status = $input['status'] ?? ($sessionsRemaining == 0 ? 'completed' : 'active');

    if ($id <= 0) {
        jsonResponse(false, [], 'Valid PT Subscription ID required', 400);
    }

    $upd = $db->prepare("
        UPDATE pt_subscriptions 
        SET trainer_id = ?, sessions_total = ?, sessions_used = ?, sessions_remaining = ?, price_paid = ?, expiry_date = ?, status = ?
        WHERE id = ?
    ");
    $upd->execute([$trainerId, $sessionsTotal, $sessionsUsed, $sessionsRemaining, $pricePaid, $expiryDate, $status, $id]);

    logAuditAction(1, 'Edit PT Subscription', 'PERSONAL_TRAINING', ['id' => $id], [
        'trainer_id' => $trainerId,
        'sessions_remaining' => $sessionsRemaining,
        'status' => $status
    ]);

    jsonResponse(true, ['id' => $id], 'PT Subscription updated successfully!');

} elseif ($action === 'checkin_pt_session' || $action === 'log_session') {
    // Deduct 1 PT session from active subscription
    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $ptSubId = intval($input['subscription_id'] ?? $input['pt_subscription_id'] ?? $input['id'] ?? 0);
    
    $stmt = $db->prepare("SELECT * FROM pt_subscriptions WHERE id = ?");
    $stmt->execute([$ptSubId]);
    $pt = $stmt->fetch();

    if (!$pt || $pt['sessions_remaining'] <= 0) {
        jsonResponse(false, [], 'No remaining PT sessions available in this package', 400);
    }

    $newUsed = $pt['sessions_used'] + 1;
    $newRem = $pt['sessions_remaining'] - 1;
    $status = $newRem == 0 ? 'completed' : 'active';

    $upd = $db->prepare("UPDATE pt_subscriptions SET sessions_used = ?, sessions_remaining = ?, status = ? WHERE id = ?");
    $upd->execute([$newUsed, $newRem, $status, $ptSubId]);

    // Record attendance / workout log note
    $notes = !empty($input['notes']) ? trim($input['notes']) : "Personal Training (PT) Session #{$newUsed} of {$pt['sessions_total']} Logged";
    $insAtt = $db->prepare("
        INSERT INTO attendance (member_id, check_in_time, verification_method, status, notes)
        VALUES (?, NOW(), 'manual', 'success', ?)
    ");
    $insAtt->execute([$pt['member_id'], $notes]);

    logAuditAction(1, 'Record PT Session', 'PERSONAL_TRAINING', ['rem_before' => $pt['sessions_remaining']], ['rem_after' => $newRem]);

    jsonResponse(true, [
        'pt_subscription_id' => $ptSubId,
        'sessions_used' => $newUsed,
        'sessions_remaining' => $newRem,
        'status' => $status
    ], "PT Session logged successfully! {$newRem} sessions remaining.");

} elseif ($action === 'cancel_subscription') {
    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $id = intval($input['subscription_id'] ?? $input['id'] ?? 0);
    if ($id > 0) {
        $db->prepare("UPDATE pt_subscriptions SET status = 'expired' WHERE id = ?")->execute([$id]);
        jsonResponse(true, [], 'PT Subscription marked as cancelled');
    } else {
        jsonResponse(false, [], 'Invalid subscription ID', 400);
    }

} else {
    jsonResponse(false, [], 'Invalid action: ' . htmlspecialchars($action), 400);
}
