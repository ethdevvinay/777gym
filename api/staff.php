<?php
/**
 * Staff Access, Shifts & Smart Biometric Attendance API Controller
 */
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');
$action = $_GET['action'] ?? $_POST['action'] ?? '';
$db = getDB();

if ($action === 'list') {
    $stmt = $db->query("
        SELECT s.*, sh.name as shift_name, sh.start_time as shift_start, sh.end_time as shift_end, sh.grace_period_mins, sh.color as shift_color,
               sa.id as today_att_id, sa.check_in_time, sa.check_out_time, sa.status as today_status, sa.late_minutes
        FROM staff s 
        LEFT JOIN staff_shifts sh ON s.shift_id = sh.id 
        LEFT JOIN staff_attendance sa ON s.id = sa.staff_id AND sa.date = CURRENT_DATE() 
        ORDER BY s.id ASC
    ");
    jsonResponse(true, $stmt->fetchAll(), 'Staff list fetched');

} elseif ($action === 'add' || $action === 'edit') {
    $id = intval($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $role = trim($_POST['role'] ?? 'Receptionist');
    $phone = trim($_POST['phone'] ?? '');
    $shiftId = !empty($_POST['shift_id']) ? intval($_POST['shift_id']) : 1;
    $biometricId = trim($_POST['biometric_id'] ?? '');
    $salary = floatval($_POST['base_salary'] ?? 15000);
    $status = $_POST['status'] ?? 'active';
    $permissions = isset($_POST['permissions']) ? (is_array($_POST['permissions']) ? json_encode($_POST['permissions']) : $_POST['permissions']) : json_encode(['pos', 'members', 'attendance']);

    if (empty($name) || empty($phone)) {
        jsonResponse(false, [], 'Staff name and phone number are required', 400);
    }

    if ($id > 0) {
        if (empty($biometricId)) {
            $biometricId = "STF-" . str_pad($id, 3, '0', STR_PAD_LEFT);
        }
        $stmt = $db->prepare("
            UPDATE staff 
            SET name = ?, role = ?, phone = ?, shift_id = ?, biometric_id = ?, base_salary = ?, permissions = ?, status = ? 
            WHERE id = ?
        ");
        $stmt->execute([$name, $role, $phone, $shiftId, $biometricId, $salary, $permissions, $status, $id]);
        logAuditAction($_SESSION['user_id'] ?? 1, 'Update Staff Profile', 'STAFF', null, ['id' => $id, 'name' => $name, 'role' => $role, 'shift_id' => $shiftId]);
        jsonResponse(true, ['id' => $id], 'Staff profile and shift assignment updated successfully');
    } else {
        $stmt = $db->prepare("
            INSERT INTO staff (name, role, phone, shift_id, biometric_id, joining_date, base_salary, permissions, status) 
            VALUES (?, ?, ?, ?, ?, CURRENT_DATE(), ?, ?, ?)
        ");
        $stmt->execute([$name, $role, $phone, $shiftId, $biometricId, $salary, $permissions, $status]);
        $newId = $db->lastInsertId();
        if (empty($biometricId)) {
            $genBio = "STF-" . str_pad($newId, 3, '0', STR_PAD_LEFT);
            $db->prepare("UPDATE staff SET biometric_id = ? WHERE id = ?")->execute([$genBio, $newId]);
            $biometricId = $genBio;
        }
        logAuditAction($_SESSION['user_id'] ?? 1, 'Add Staff Member', 'STAFF', null, ['id' => $newId, 'name' => $name, 'role' => $role]);
        jsonResponse(true, ['id' => $newId, 'biometric_id' => $biometricId], 'Staff member added successfully with assigned shift');
    }

} elseif ($action === 'punch_attendance') {
    // Smart Biometric / Manual Staff Punch Check-In & Check-Out Engine
    $raw = file_get_contents('php://input');
    $input = json_decode($raw, true) ?: $_POST;

    $staffId = intval($input['staff_id'] ?? 0);
    $biometricId = trim($input['biometric_id'] ?? '');
    $method = trim($input['method'] ?? 'biometric');
    $deviceId = !empty($input['device_id']) ? intval($input['device_id']) : null;
    $notes = trim($input['notes'] ?? '');
    $punchTimeStr = !empty($input['punch_time']) ? trim($input['punch_time']) : date('Y-m-d H:i:s');
    $punchTimestamp = strtotime($punchTimeStr);
    $punchDate = date('Y-m-d', $punchTimestamp);
    $punchTimeOnly = date('H:i:s', $punchTimestamp);

    // Look up staff by ID or Biometric ID
    $stStmt = null;
    if ($staffId > 0) {
        $stStmt = $db->prepare("SELECT s.*, sh.name as shift_name, sh.start_time as shift_start, sh.end_time as shift_end, sh.grace_period_mins, sh.half_day_threshold_mins FROM staff s LEFT JOIN staff_shifts sh ON s.shift_id = sh.id WHERE s.id = ?");
        $stStmt->execute([$staffId]);
    } elseif (!empty($biometricId)) {
        $stStmt = $db->prepare("SELECT s.*, sh.name as shift_name, sh.start_time as shift_start, sh.end_time as shift_end, sh.grace_period_mins, sh.half_day_threshold_mins FROM staff s LEFT JOIN staff_shifts sh ON s.shift_id = sh.id WHERE s.biometric_id = ?");
        $stStmt->execute([$biometricId]);
    } else {
        jsonResponse(false, [], 'Staff ID or Biometric Hardware ID is required', 400);
    }

    $staff = $stStmt ? $stStmt->fetch() : null;
    if (!$staff) {
        jsonResponse(false, [], 'Staff profile not found', 404);
    }

    $staffId = $staff['id'];
    $shiftId = $staff['shift_id'] ?: 1;

    // Check if staff has an open attendance record for this date
    $attStmt = $db->prepare("SELECT * FROM staff_attendance WHERE staff_id = ? AND date = ? ORDER BY id DESC LIMIT 1");
    $attStmt->execute([$staffId, $punchDate]);
    $existing = $attStmt->fetch();

    if (!$existing) {
        // --- CHECK-IN PUNCH (START OF SHIFT) ---
        $shiftStart = $staff['shift_start'] ?: '05:00:00';
        if (stripos($staff['shift_name'] ?? '', 'split') !== false || stripos($staff['shift_name'] ?? '', 'double') !== false) {
            // If punching in afternoon/evening for split shift, compare against 05:00 PM (17:00)
            $hour = intval(date('H', $punchTimestamp));
            if ($hour >= 12) {
                $shiftStart = '17:00:00';
            } else {
                $shiftStart = '05:00:00';
            }
        }

        $graceMins = intval($staff['grace_period_mins'] ?? 15);
        $halfDayThreshold = intval($staff['half_day_threshold_mins'] ?? 60);

        $shiftStartTimestamp = strtotime("{$punchDate} {$shiftStart}");
        $graceTimestamp = $shiftStartTimestamp + ($graceMins * 60);

        $status = 'present';
        $lateMins = 0;
        $lateReason = null;

        if ($punchTimestamp > $graceTimestamp) {
            // Late Punch detected!
            $lateMins = max(1, (int) ceil(($punchTimestamp - $shiftStartTimestamp) / 60));
            if ($lateMins > $halfDayThreshold) {
                $status = 'half_day';
                $lateReason = "Late by {$lateMins} mins (Exceeded {$halfDayThreshold}m threshold)";
            } else {
                $status = 'late';
                $lateReason = "Late by {$lateMins} mins (Shift Start: " . date('h:i A', $shiftStartTimestamp) . ", Grace: {$graceMins}m)";
            }
        }

        $ins = $db->prepare("
            INSERT INTO staff_attendance (staff_id, date, shift_id, check_in_time, status, late_minutes, late_reason, verification_method, device_id, notes) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $ins->execute([$staffId, $punchDate, $shiftId, $punchTimeStr, $status, $lateMins, $lateReason, $method, $deviceId, $notes]);
        $attId = $db->lastInsertId();

        $statusLabel = ($status === 'present') ? '🟢 ON TIME' : ($status === 'late' ? "⚠️ LATE BY {$lateMins} MINS" : "🟡 HALF DAY (LATE {$lateMins}M)");
        $msg = "✅ {$staff['name']} ({$staff['role']}) Checked In at " . date('h:i A', $punchTimestamp) . " [{$statusLabel}]";

        jsonResponse(true, [
            'type' => 'check_in',
            'attendance_id' => $attId,
            'staff_name' => $staff['name'],
            'shift_name' => $staff['shift_name'] ?: 'Morning Shift',
            'check_in_time' => date('h:i A', $punchTimestamp),
            'status' => $status,
            'late_minutes' => $lateMins,
            'status_label' => $statusLabel
        ], $msg);

    } else if (empty($existing['check_out_time'])) {
        // --- CHECK-OUT PUNCH (END OF SHIFT) ---
        $checkInTimestamp = strtotime($existing['check_in_time']);
        $workHours = round(max(0, ($punchTimestamp - $checkInTimestamp) / 3600), 2);

        $shiftEnd = $staff['shift_end'] ?: '14:00:00';
        $shiftEndTimestamp = strtotime("{$punchDate} {$shiftEnd}");
        $overtimeMins = 0;
        if ($punchTimestamp > $shiftEndTimestamp) {
            $overtimeMins = max(0, (int) floor(($punchTimestamp - $shiftEndTimestamp) / 60));
        }

        $upd = $db->prepare("
            UPDATE staff_attendance 
            SET check_out_time = ?, working_hours = ?, overtime_minutes = ?, notes = CONCAT(IFNULL(notes,''), ' ', ?) 
            WHERE id = ?
        ");
        $upd->execute([$punchTimeStr, $workHours, $overtimeMins, $notes, $existing['id']]);

        $msg = "👋 {$staff['name']} ({$staff['role']}) Checked Out at " . date('h:i A', $punchTimestamp) . " [Worked: {$workHours} hrs" . ($overtimeMins > 0 ? " | OT: {$overtimeMins}m" : "") . "]";

        jsonResponse(true, [
            'type' => 'check_out',
            'attendance_id' => $existing['id'],
            'staff_name' => $staff['name'],
            'check_out_time' => date('h:i A', $punchTimestamp),
            'working_hours' => $workHours,
            'overtime_minutes' => $overtimeMins
        ], $msg);

    } else {
        // Already checked in and checked out for today
        jsonResponse(false, [], "{$staff['name']} has already completed check-in & check-out for {$punchDate}.", 400);
    }

} elseif ($action === 'list_shifts') {
    $stmt = $db->query("SELECT * FROM staff_shifts ORDER BY start_time ASC");
    jsonResponse(true, $stmt->fetchAll(), 'Shifts list fetched');

} elseif ($action === 'save_shift') {
    $id = intval($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $startTime = trim($_POST['start_time'] ?? '06:00:00');
    $endTime = trim($_POST['end_time'] ?? '14:00:00');
    $grace = intval($_POST['grace_period_mins'] ?? 15);
    $halfDay = intval($_POST['half_day_threshold_mins'] ?? 120);
    $color = trim($_POST['color'] ?? '#3B82F6');
    $status = $_POST['status'] ?? 'active';

    if (empty($name) || empty($startTime) || empty($endTime)) {
        jsonResponse(false, [], 'Shift name, start time and end time are required', 400);
    }

    if ($id > 0) {
        $stmt = $db->prepare("UPDATE staff_shifts SET name = ?, start_time = ?, end_time = ?, grace_period_mins = ?, half_day_threshold_mins = ?, color = ?, status = ? WHERE id = ?");
        $stmt->execute([$name, $startTime, $endTime, $grace, $halfDay, $color, $status, $id]);
        jsonResponse(true, ['id' => $id], 'Shift schedule updated successfully');
    } else {
        $stmt = $db->prepare("INSERT INTO staff_shifts (name, start_time, end_time, grace_period_mins, half_day_threshold_mins, color, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$name, $startTime, $endTime, $grace, $halfDay, $color, $status]);
        $newId = $db->lastInsertId();
        jsonResponse(true, ['id' => $newId], 'New shift schedule created successfully');
    }

} elseif ($action === 'get_attendance_logs') {
    $month = trim($_GET['month'] ?? date('Y-m'));
    $staffId = intval($_GET['staff_id'] ?? 0);

    $sql = "
        SELECT sa.*, s.name as staff_name, s.role, s.phone, s.biometric_id, sh.name as shift_name, sh.start_time as shift_start, sh.end_time as shift_end, sh.color as shift_color 
        FROM staff_attendance sa 
        JOIN staff s ON sa.staff_id = s.id 
        LEFT JOIN staff_shifts sh ON sa.shift_id = sh.id 
        WHERE sa.date LIKE ?
    ";
    $params = [$month . '%'];

    if ($staffId > 0) {
        $sql .= " AND sa.staff_id = ?";
        $params[] = $staffId;
    }

    $sql .= " ORDER BY sa.date DESC, sa.check_in_time DESC";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);

    jsonResponse(true, $stmt->fetchAll(), 'Staff attendance logs fetched');

} elseif ($action === 'generate_payroll') {
    $staffId = intval($_POST['staff_id'] ?? 0);
    $period = trim($_POST['pay_period'] ?? date('Y-m'));
    $basic = floatval($_POST['basic_salary'] ?? 0);
    $allowances = floatval($_POST['allowances'] ?? 0);
    $deductions = floatval($_POST['deductions'] ?? 0);
    $commission = floatval($_POST['commission'] ?? 0);
    $advance = floatval($_POST['advance_paid'] ?? 0);

    if ($staffId <= 0 || $basic <= 0) {
        jsonResponse(false, [], 'Valid staff ID and basic salary required', 400);
    }

    $netSalary = max(0, ($basic + $allowances + $commission) - ($deductions + $advance));
    $payslipNo = "PAY-" . date('Ym') . "-" . str_pad($staffId, 4, '0', STR_PAD_LEFT);

    $stmt = $db->prepare("INSERT INTO payroll (staff_id, pay_period, basic_salary, allowances, deductions, commission, advance_paid, net_salary, payment_date, status, payslip_no) VALUES (?, ?, ?, ?, ?, ?, ?, ?, CURRENT_DATE(), 'paid', ?)");
    $stmt->execute([$staffId, $period, $basic, $allowances, $deductions, $commission, $advance, $netSalary, $payslipNo]);

    logAuditAction($_SESSION['user_id'] ?? 1, 'Generate Payslip', 'PAYROLL', null, ['staff_id' => $staffId, 'net_salary' => $netSalary, 'payslip' => $payslipNo]);
    jsonResponse(true, ['payslip_no' => $payslipNo, 'net_salary' => $netSalary], "Payroll generated successfully! Payslip No: {$payslipNo} (Net ₹{$netSalary})");

} else {
    jsonResponse(false, [], 'Invalid action', 400);
}
