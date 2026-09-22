<!-- Staff Management, Shifts & Smart Biometric Attendance Hub View -->
<?php
$db = getDB();

// Today's Staff Attendance Logs
$today = date('Y-m-d');
$stmtToday = $db->prepare("
    SELECT sa.*, s.name as staff_name, s.role, s.phone, s.biometric_id, 
           sh.name as shift_name, sh.start_time as shift_start, sh.end_time as shift_end, sh.grace_period_mins, sh.color as shift_color
    FROM staff_attendance sa
    JOIN staff s ON sa.staff_id = s.id
    LEFT JOIN staff_shifts sh ON sa.shift_id = sh.id
    WHERE sa.date = ?
    ORDER BY sa.check_in_time DESC
");
$stmtToday->execute([$today]);
$todayAttendance = $stmtToday->fetchAll();

// All Staff Members
$staffList = $db->query("
    SELECT s.*, sh.name as shift_name, sh.start_time as shift_start, sh.end_time as shift_end, sh.color as shift_color,
           (SELECT status FROM staff_attendance WHERE staff_id = s.id AND date = '{$today}' LIMIT 1) as today_status,
           (SELECT check_in_time FROM staff_attendance WHERE staff_id = s.id AND date = '{$today}' LIMIT 1) as today_checkin,
           (SELECT check_out_time FROM staff_attendance WHERE staff_id = s.id AND date = '{$today}' LIMIT 1) as today_checkout,
           (SELECT late_minutes FROM staff_attendance WHERE staff_id = s.id AND date = '{$today}' LIMIT 1) as today_late_mins
    FROM staff s 
    LEFT JOIN staff_shifts sh ON s.shift_id = sh.id 
    ORDER BY s.id ASC
")->fetchAll();

// Shifts
$shifts = $db->query("SELECT * FROM staff_shifts ORDER BY start_time ASC")->fetchAll();

// Payroll History
$payroll = $db->query("
    SELECT p.*, s.name as staff_name, s.role, s.phone as staff_phone 
    FROM payroll p 
    JOIN staff s ON p.staff_id = s.id 
    ORDER BY p.id DESC
")->fetchAll();

$currency = getSetting('currency_symbol', '₹');

// Stats Counters
$totalStaff = count($staffList);
$presentToday = 0;
$lateToday = 0;
$onDutyNow = 0;
foreach ($todayAttendance as $att) {
    $presentToday++;
    if ($att['status'] === 'late' || $att['status'] === 'half_day' || $att['late_minutes'] > 0) {
        $lateToday++;
    }
    if (empty($att['check_out_time'])) {
        $onDutyNow++;
    }
}
?>

<div class="page-content">
  <!-- Top Banner Header -->
  <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem; margin-bottom:1.5rem;">
    <div>
      <h1 style="font-size:1.6rem; font-weight:800; color:var(--text-primary); margin:0; display:flex; align-items:center; gap:0.5rem;">
        👥 Staff Access &amp; Smart Biometric Attendance
      </h1>
      <p style="font-size:0.85rem; color:var(--text-secondary); margin-top:0.25rem;">
        Track Morning &amp; Evening Shifts, Automatic Late Detection, Hardware Biometric Sync &amp; Payroll
      </p>
    </div>

    <div style="display:flex; gap:0.5rem; flex-wrap:wrap;">
      <button class="btn btn-primary" onclick="openModal('punchStaffModal')" style="box-shadow:0 4px 12px rgba(37,99,235,0.25);">
        ⏱️ PUNCH ATTENDANCE (IN/OUT)
      </button>
      <button class="btn btn-outline" onclick="openAddStaffModal()">
        + Add Staff Member
      </button>
      <button class="btn btn-secondary" onclick="openModal('shiftConfigModal')">
        📅 Manage Shifts
      </button>
      <button class="btn btn-secondary" onclick="openModal('generatePayrollModal')">
        💵 Generate Payslip
      </button>
    </div>
  </div>

  <!-- KPI Summary Cards -->
  <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:1rem; margin-bottom:1.5rem;">
    <div class="card" style="border-left:4px solid var(--primary); padding:1.25rem;">
      <div style="font-size:0.8rem; font-weight:700; color:var(--text-muted); text-transform:uppercase;">Total Staff Members</div>
      <div style="font-size:2rem; font-weight:800; color:var(--text-primary); margin-top:0.25rem;"><?= $totalStaff ?></div>
      <div style="font-size:0.75rem; color:var(--text-secondary); margin-top:0.25rem;">Trainers, Reception &amp; Ops</div>
    </div>

    <div class="card" style="border-left:4px solid #10B981; padding:1.25rem;">
      <div style="font-size:0.8rem; font-weight:700; color:var(--text-muted); text-transform:uppercase;">Present Today</div>
      <div style="font-size:2rem; font-weight:800; color:#10B981; margin-top:0.25rem;"><?= $presentToday ?></div>
      <div style="font-size:0.75rem; color:#047857; margin-top:0.25rem;">🟢 Checked-In Today</div>
    </div>

    <div class="card" style="border-left:4px solid #F59E0B; padding:1.25rem;">
      <div style="font-size:0.8rem; font-weight:700; color:var(--text-muted); text-transform:uppercase;">Late Arrivals Today</div>
      <div style="font-size:2rem; font-weight:800; color:#F59E0B; margin-top:0.25rem;"><?= $lateToday ?></div>
      <div style="font-size:0.75rem; color:#B45309; margin-top:0.25rem;">⚠️ Auto-Fetched Late Entries</div>
    </div>

    <div class="card" style="border-left:4px solid #3B82F6; padding:1.25rem;">
      <div style="font-size:0.8rem; font-weight:700; color:var(--text-muted); text-transform:uppercase;">Currently On Duty</div>
      <div style="font-size:2rem; font-weight:800; color:#3B82F6; margin-top:0.25rem;"><?= $onDutyNow ?></div>
      <div style="font-size:0.75rem; color:#1D4ED8; margin-top:0.25rem;">⚡ Inside Gym Active Shift</div>
    </div>
  </div>

  <!-- Navigation Tab Bar -->
  <div style="display:flex; gap:0.5rem; border-bottom:2px solid var(--border-color); margin-bottom:1.5rem; flex-wrap:wrap;">
    <button class="btn btn-sm staff-tab-btn active" id="tabBtnAtt" onclick="switchStaffTab('attendance')" style="border-radius:8px 8px 0 0; font-weight:800; border:none; background:var(--primary); color:#fff; padding:0.6rem 1.25rem;">
      ⏱️ Today's Biometric Attendance Log
    </button>
    <button class="btn btn-sm staff-tab-btn" id="tabBtnDir" onclick="switchStaffTab('directory')" style="border-radius:8px 8px 0 0; font-weight:700; border:none; background:transparent; color:var(--text-secondary); padding:0.6rem 1.25rem;">
      👥 Staff Directory &amp; Roles (<?= $totalStaff ?>)
    </button>
    <button class="btn btn-sm staff-tab-btn" id="tabBtnShifts" onclick="switchStaffTab('shifts')" style="border-radius:8px 8px 0 0; font-weight:700; border:none; background:transparent; color:var(--text-secondary); padding:0.6rem 1.25rem;">
      📅 Shift Schedules &amp; Late Rules
    </button>
    <button class="btn btn-sm staff-tab-btn" id="tabBtnPay" onclick="switchStaffTab('payroll')" style="border-radius:8px 8px 0 0; font-weight:700; border:none; background:transparent; color:var(--text-secondary); padding:0.6rem 1.25rem;">
      🧾 Payroll &amp; Payslips
    </button>
  </div>

  <!-- ================= TAB 1: TODAY'S BIOMETRIC ATTENDANCE ================= -->
  <div id="staffAttendanceTab" class="staff-tab-pane">
    <div class="card">
      <div class="card-header" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:0.5rem;">
        <div>
          <span class="card-title">⏱️ Live Staff Punch &amp; Late Arrival Tracker (<?= date('d M Y') ?>)</span>
          <p style="font-size:0.78rem; color:var(--text-secondary); margin:0;">Real-time biometric punch logs with shift comparison and late minute calculations</p>
        </div>
        <button class="btn btn-sm btn-primary" onclick="openModal('punchStaffModal')">
          + Quick Punch Terminal
        </button>
      </div>

      <div style="overflow-x:auto;">
        <table class="table" style="width:100%;">
          <thead>
            <tr>
              <th>Staff Member</th>
              <th>Assigned Shift</th>
              <th>Check-In Time</th>
              <th>Check-Out Time</th>
              <th>Late / On-Time Status</th>
              <th>Working Hours</th>
              <th>Verification</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($todayAttendance)): ?>
              <tr>
                <td colspan="8" style="text-align:center; padding:2rem; color:var(--text-muted);">
                  No staff attendance recorded today. Click <strong>"PUNCH ATTENDANCE"</strong> or punch on the biometric terminal!
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($todayAttendance as $att): ?>
                <?php 
                  $isLate = ($att['status'] === 'late' || $att['late_minutes'] > 0);
                  $isHalfDay = ($att['status'] === 'half_day');
                ?>
                <tr style="<?= $isLate ? 'background:#FFFBEB;' : ($isHalfDay ? 'background:#FEF2F2;' : '') ?>">
                  <td>
                    <div style="font-weight:800; color:var(--text-primary);"><?= htmlspecialchars($att['staff_name']) ?></div>
                    <div style="font-size:0.75rem; color:var(--text-muted);">
                      <?= htmlspecialchars($att['role']) ?> • <strong style="color:var(--primary); font-family:monospace;"><?= htmlspecialchars($att['biometric_id'] ?: 'BIO-NA') ?></strong>
                    </div>
                  </td>

                  <td>
                    <span class="badge" style="background:<?= $att['shift_color'] ?: '#3B82F6' ?>; color:#FFFFFF; font-size:0.75rem; font-weight:700;">
                      <?= htmlspecialchars($att['shift_name'] ?: 'Morning Shift') ?>
                    </span>
                    <div style="font-size:0.72rem; color:var(--text-muted); margin-top:0.15rem;">
                      <?= date('h:i A', strtotime($att['shift_start'])) ?> - <?= date('h:i A', strtotime($att['shift_end'])) ?>
                    </div>
                  </td>

                  <td>
                    <strong style="color:var(--primary); font-size:0.95rem;">
                      <?= date('h:i A', strtotime($att['check_in_time'])) ?>
                    </strong>
                  </td>

                  <td>
                    <?php if ($att['check_out_time']): ?>
                      <strong style="color:#059669; font-size:0.95rem;">
                        <?= date('h:i A', strtotime($att['check_out_time'])) ?>
                      </strong>
                      <?php if ($att['overtime_minutes'] > 0): ?>
                        <div style="font-size:0.7rem; color:#059669; font-weight:700;">+<?= $att['overtime_minutes'] ?>m OT</div>
                      <?php endif; ?>
                    <?php else: ?>
                      <span class="badge badge-success" style="animation:pulse 2s infinite;">● ON DUTY NOW</span>
                    <?php endif; ?>
                  </td>

                  <td>
                    <?php if ($isHalfDay): ?>
                      <span class="badge badge-danger" style="font-weight:800; font-size:0.78rem;">
                        🔴 HALF DAY (LATE <?= $att['late_minutes'] ?>M)
                      </span>
                    <?php elseif ($isLate): ?>
                      <span class="badge badge-warning" style="font-weight:800; background:#F59E0B; color:#FFFFFF; font-size:0.78rem;">
                        ⚠️ LATE BY <?= $att['late_minutes'] ?> MINS
                      </span>
                    <?php else: ?>
                      <span class="badge badge-success" style="font-weight:800; font-size:0.78rem;">
                        🟢 ON TIME
                      </span>
                    <?php endif; ?>
                    <?php if ($att['late_reason']): ?>
                      <div style="font-size:0.7rem; color:#92400E; margin-top:0.15rem;"><?= htmlspecialchars($att['late_reason']) ?></div>
                    <?php endif; ?>
                  </td>

                  <td>
                    <strong><?= $att['working_hours'] > 0 ? $att['working_hours'] . ' hrs' : 'In Progress' ?></strong>
                  </td>

                  <td>
                    <span class="badge badge-info" style="text-transform:uppercase; font-size:0.72rem;">
                      <?= htmlspecialchars($att['verification_method']) ?>
                    </span>
                  </td>

                  <td>
                    <?php if (empty($att['check_out_time'])): ?>
                      <button class="btn btn-sm btn-outline" onclick="punchStaffDirect(<?= $att['staff_id'] ?>, 'check_out')" style="font-size:0.75rem; padding:0.25rem 0.5rem; color:#DC2626; border-color:#FCA5A5;">
                        👋 Punch Out
                      </button>
                    <?php else: ?>
                      <span style="font-size:0.75rem; color:var(--text-muted);">✓ Shift Done</span>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- ================= TAB 2: STAFF DIRECTORY & ROLES ================= -->
  <div id="staffDirectoryTab" class="staff-tab-pane" style="display:none;">
    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(320px, 1fr)); gap:1.25rem;">
      <?php foreach ($staffList as $st): ?>
        <div class="card" style="border-top:4px solid <?= $st['shift_color'] ?: 'var(--primary)' ?>; display:flex; flex-direction:column; justify-content:space-between;">
          <div>
            <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:0.5rem;">
              <div>
                <strong style="font-size:1.15rem; color:var(--text-primary); display:block;"><?= htmlspecialchars($st['name']) ?></strong>
                <span style="font-size:0.8rem; font-weight:700; color:var(--primary);"><?= htmlspecialchars($st['role']) ?></span>
              </div>
              <span class="badge" style="background:#F1F5F9; color:#334155; font-family:monospace; font-weight:700; font-size:0.8rem; border:1px solid #CBD5E1;">
                <?= htmlspecialchars($st['biometric_id'] ?: 'BIO-NA') ?>
              </span>
            </div>

            <div style="display:flex; flex-direction:column; gap:0.45rem; font-size:0.83rem; color:var(--text-secondary); background:var(--bg-main); padding:0.85rem; border-radius:10px; margin-bottom:1rem; border:1px solid var(--border-color);">
              <div style="display:flex; justify-content:space-between;">
                <span>📞 <strong>Phone:</strong></span>
                <strong style="color:var(--text-primary);"><?= htmlspecialchars($st['phone']) ?></strong>
              </div>

              <div style="display:flex; justify-content:space-between; align-items:center;">
                <span>📅 <strong>Assigned Shift:</strong></span>
                <span class="badge" style="background:<?= $st['shift_color'] ?: '#3B82F6' ?>; color:#FFFFFF; font-size:0.75rem;">
                  <?= htmlspecialchars($st['shift_name'] ?: 'Morning Shift') ?> (<?= date('h:i A', strtotime($st['shift_start'])) ?> - <?= date('h:i A', strtotime($st['shift_end'])) ?>)
                </span>
              </div>

              <div style="display:flex; justify-content:space-between;">
                <span>💵 <strong>Base Salary:</strong></span>
                <strong style="color:#059669;"><?= $currency ?><?= number_format($st['base_salary'], 2) ?> / mo</strong>
              </div>

              <!-- Today's Live Status Preview -->
              <div style="display:flex; justify-content:space-between; align-items:center; border-top:1px dashed var(--border-color); padding-top:0.4rem; margin-top:0.2rem;">
                <span>Today's Punch:</span>
                <?php if ($st['today_status']): ?>
                  <span class="badge badge-<?= $st['today_status'] === 'present' ? 'success' : ($st['today_status'] === 'late' ? 'warning' : 'danger') ?>">
                    <?= strtoupper($st['today_status']) ?> (<?= date('h:i A', strtotime($st['today_checkin'])) ?>)
                  </span>
                <?php else: ?>
                  <span style="font-size:0.75rem; color:var(--text-muted);">Not punched yet</span>
                <?php endif; ?>
              </div>
            </div>
          </div>

          <div style="display:flex; gap:0.5rem;">
            <button type="button" class="btn btn-outline btn-block btn-sm" onclick="openEditStaffModal(<?= htmlspecialchars(json_encode($st)) ?>)">
              ✏️ Edit Staff &amp; Shift
            </button>
            <?php if (empty($st['today_checkin'])): ?>
              <button type="button" class="btn btn-primary btn-block btn-sm" onclick="punchStaffDirect(<?= $st['id'] ?>, 'check_in')">
                ⏱️ Check-In
              </button>
            <?php elseif (empty($st['today_checkout'])): ?>
              <button type="button" class="btn btn-danger btn-block btn-sm" onclick="punchStaffDirect(<?= $st['id'] ?>, 'check_out')">
                👋 Check-Out
              </button>
            <?php else: ?>
              <button type="button" class="btn btn-secondary btn-block btn-sm" disabled>
                ✓ Done Today
              </button>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- ================= TAB 3: SHIFT SCHEDULES & RULES ================= -->
  <div id="staffShiftsTab" class="staff-tab-pane" style="display:none;">
    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(280px, 1fr)); gap:1.25rem; margin-bottom:1.5rem;">
      <?php foreach ($shifts as $sh): ?>
        <div class="card" style="border-left:5px solid <?= $sh['color'] ?: '#3B82F6' ?>; padding:1.25rem;">
          <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.5rem;">
            <strong style="font-size:1.15rem; color:var(--text-primary);"><?= htmlspecialchars($sh['name']) ?></strong>
            <span class="badge badge-<?= $sh['status'] === 'active' ? 'success' : 'secondary' ?>">
              ● <?= strtoupper($sh['status']) ?>
            </span>
          </div>

          <div style="font-size:1.3rem; font-weight:800; color:var(--primary); margin-bottom:0.75rem;">
            <?= date('h:i A', strtotime($sh['start_time'])) ?> - <?= date('h:i A', strtotime($sh['end_time'])) ?>
          </div>

          <div style="font-size:0.82rem; color:var(--text-secondary); background:var(--bg-main); padding:0.75rem; border-radius:8px; display:flex; flex-direction:column; gap:0.35rem;">
            <div>⏱️ <strong>Grace Period:</strong> <?= $sh['grace_period_mins'] ?> Mins (Late after this)</div>
            <div>🟡 <strong>Half Day Rule:</strong> Late &gt; <?= $sh['half_day_threshold_mins'] ?> Mins</div>
          </div>

          <div style="margin-top:1rem;">
            <button class="btn btn-outline btn-block btn-sm" onclick="openEditShiftModal(<?= htmlspecialchars(json_encode($sh)) ?>)">
              ✏️ Edit Shift Timing
            </button>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- ================= TAB 4: PAYROLL & PAYSLIPS ================= -->
  <div id="staffPayrollTab" class="staff-tab-pane" style="display:none;">
    <div class="card">
      <div class="card-header" style="display:flex; justify-content:space-between; align-items:center;">
        <span class="card-title">🧾 Generated Staff Payslips &amp; History</span>
        <button class="btn btn-primary btn-sm" onclick="openModal('generatePayrollModal')">
          + Generate New Payslip
        </button>
      </div>

      <div style="overflow-x:auto;">
        <table class="table" style="width:100%;">
          <thead>
            <tr>
              <th>Payslip No</th>
              <th>Staff Member</th>
              <th>Role</th>
              <th>Pay Period</th>
              <th>Basic Salary</th>
              <th>Allowances</th>
              <th>Net Paid</th>
              <th>Date</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($payroll)): ?>
              <tr><td colspan="10" style="text-align:center; padding:2rem; color:var(--text-muted);">No payroll history recorded yet.</td></tr>
            <?php else: ?>
              <?php foreach ($payroll as $pay): ?>
                <?php
                  $cleanP = preg_replace('/[^0-9]/', '', $pay['staff_phone'] ?? '');
                  if (strlen($cleanP) === 10) $cleanP = '91' . $cleanP;
                  $payPeriodFmt = date('F Y', strtotime($pay['pay_period'] . '-01'));
                  $slipWaMsg = "🧾 *Salary Payslip - {$payPeriodFmt}*\n\nDear {$pay['staff_name']},\nYour salary of *{$currency}" . number_format($pay['net_salary'], 2) . "* for {$payPeriodFmt} has been processed successfully!\n\n📋 Payslip No: {$pay['payslip_no']}\n💼 Designation: {$pay['role']}\n💵 Net Take-Home: {$currency}" . number_format($pay['net_salary'], 2) . "\n\n📄 View / Download Slip:\nhttp://localhost/GYM/index.php?page=payslip&id={$pay['id']}";
                  $slipWaUrl = "https://wa.me/{$cleanP}?text=" . urlencode($slipWaMsg);
                ?>
                <tr>
                  <td style="font-weight:700; color:var(--primary); font-family:monospace;"><?= htmlspecialchars($pay['payslip_no']) ?></td>
                  <td><strong><?= htmlspecialchars($pay['staff_name']) ?></strong></td>
                  <td><?= htmlspecialchars($pay['role']) ?></td>
                  <td><?= htmlspecialchars($pay['pay_period']) ?></td>
                  <td><?= $currency ?><?= number_format($pay['basic_salary'], 2) ?></td>
                  <td>+<?= $currency ?><?= number_format($pay['allowances'] + $pay['commission'], 2) ?></td>
                  <td><strong style="color:var(--success);"><?= $currency ?><?= number_format($pay['net_salary'], 2) ?></strong></td>
                  <td style="font-size:0.8rem;"><?= date('d M Y', strtotime($pay['payment_date'])) ?></td>
                  <td><span class="badge badge-success">PAID</span></td>
                  <td>
                    <div style="display:flex; gap:0.4rem; flex-wrap:wrap;">
                      <a href="index.php?page=payslip&id=<?= $pay['id'] ?>" target="_blank" class="btn btn-sm btn-primary" style="font-size:0.75rem; font-weight:800; text-decoration:none; padding:0.3rem 0.65rem;">
                        📥 Download / Print
                      </a>
                      <?php if (!empty($cleanP)): ?>
                        <a href="<?= $slipWaUrl ?>" target="_blank" class="btn btn-sm" style="background:#25D366; color:#fff; font-weight:800; border:none; text-decoration:none; font-size:0.75rem; padding:0.3rem 0.65rem;">
                          💬 WhatsApp
                        </a>
                      <?php endif; ?>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- QUICK PUNCH ATTENDANCE TERMINAL MODAL -->
<div class="modal" id="punchStaffModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:100; align-items:center; justify-content:center;">
  <div class="modal-overlay" style="position:absolute; inset:0;" onclick="closeModal('punchStaffModal')"></div>
  <div class="card" style="position:relative; z-index:10; background:#fff; width:90%; max-width:460px; border-radius:16px; padding:1.5rem; border-top:5px solid var(--primary);">
    <div class="card-header" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;">
      <span class="card-title" style="font-size:1.2rem; font-weight:800;">⏱️ Staff Biometric Punch Terminal</span>
      <button style="background:none; border:none; font-size:1.2rem; cursor:pointer;" onclick="closeModal('punchStaffModal')">✕</button>
    </div>

    <form onsubmit="submitStaffPunch(event)">
      <div class="form-group">
        <label class="form-label">Select Staff Member *</label>
        <select name="staff_id" id="punchStaffIdSelect" class="form-control" required onchange="onPunchStaffSelect()">
          <option value="">-- Choose Staff Member --</option>
          <?php foreach ($staffList as $st): ?>
            <option value="<?= $st['id'] ?>" data-shift="<?= htmlspecialchars($st['shift_name'] ?: 'Morning') ?>" data-start="<?= $st['shift_start'] ?>" data-bio="<?= $st['biometric_id'] ?>">
              <?= htmlspecialchars($st['name']) ?> (<?= htmlspecialchars($st['role']) ?> • <?= htmlspecialchars($st['biometric_id']) ?>)
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <!-- Shift Info Box -->
      <div id="punchStaffShiftInfo" style="display:none; background:#EFF6FF; border:1px solid #BFDBFE; border-radius:8px; padding:0.75rem; margin-bottom:1rem; font-size:0.82rem; color:#1E40AF;">
        📅 Assigned Shift: <strong id="punchStaffShiftName"></strong> (<span id="punchStaffShiftTime"></span>)<br>
        💡 <em>System will auto-calculate Late minutes if punching past shift start + grace time.</em>
      </div>

      <div class="form-group">
        <label class="form-label">Punch Time *</label>
        <input type="datetime-local" name="punch_time" id="punchTimeInput" class="form-control" required>
      </div>

      <div class="form-group">
        <label class="form-label">Punch Method</label>
        <select name="method" class="form-control">
          <option value="biometric">Fingerprint / Face ID Scan</option>
          <option value="rfid">RFID Staff Smart Card</option>
          <option value="manual">Manual Admin Punch</option>
        </select>
      </div>

      <div class="form-group">
        <label class="form-label">Notes / Remarks (Optional)</label>
        <input type="text" name="notes" class="form-control" placeholder="e.g. Traffic delay on highway">
      </div>

      <div style="display:flex; gap:0.75rem; margin-top:1.5rem;">
        <button type="button" class="btn btn-secondary btn-block" onclick="closeModal('punchStaffModal')">Cancel</button>
        <button type="submit" class="btn btn-primary btn-block" style="font-weight:800;">✓ CONFIRM PUNCH IN / OUT</button>
      </div>
    </form>
  </div>
</div>

<!-- ADD / EDIT STAFF MODAL -->
<div class="modal" id="addStaffModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:100; align-items:center; justify-content:center;">
  <div class="modal-overlay" style="position:absolute; inset:0;" onclick="closeModal('addStaffModal')"></div>
  <div class="card" style="position:relative; z-index:10; background:#fff; width:90%; max-width:500px; border-radius:16px; padding:1.5rem; border-top:5px solid var(--primary);">
    <div class="card-header" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;">
      <span class="card-title" id="staffModalTitle" style="font-size:1.2rem; font-weight:800;">➕ Add Staff Member</span>
      <button style="background:none; border:none; font-size:1.2rem; cursor:pointer;" onclick="closeModal('addStaffModal')">✕</button>
    </div>

    <form onsubmit="saveStaff(event)">
      <input type="hidden" name="id" id="staffId" value="0">

      <div class="form-group">
        <label class="form-label">Staff Full Name *</label>
        <input type="text" name="name" id="staffNameInput" class="form-control" placeholder="e.g. Vikram Singh" required>
      </div>

      <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.75rem;">
        <div class="form-group">
          <label class="form-label">Role / Designation *</label>
          <select name="role" id="staffRoleSelect" class="form-control">
            <option value="Manager">Manager</option>
            <option value="Personal Trainer">Personal Trainer</option>
            <option value="Floor Trainer">Floor Trainer</option>
            <option value="Receptionist">Receptionist / Front Desk</option>
            <option value="Nutritionist">Nutritionist / Dietitian</option>
            <option value="Swim Coach">Swim Coach / Lifeguard</option>
            <option value="Housekeeping">Housekeeping / Cleaner</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Phone Number *</label>
          <input type="text" name="phone" id="staffPhoneInput" class="form-control" placeholder="9876543210" required>
        </div>
      </div>

      <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.75rem;">
        <div class="form-group">
          <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.35rem;">
            <label class="form-label" style="margin-bottom:0;">Assigned Shift Schedule *</label>
            <div style="display:flex; gap:0.3rem;">
              <button type="button" class="btn btn-sm btn-outline" onclick="openEditSelectedShift()" style="font-size:0.7rem; padding:0.15rem 0.45rem; color:#059669; border-color:#A7F3D0; font-weight:800;" title="Edit the currently chosen shift">
                ✏️ Edit Shift
              </button>
              <button type="button" class="btn btn-sm btn-outline" onclick="openCreateShiftModal()" style="font-size:0.7rem; padding:0.15rem 0.45rem; color:#2563EB; border-color:#BFDBFE; font-weight:800;" title="Create a new custom shift">
                + New
              </button>
            </div>
          </div>
          <select name="shift_id" id="staffShiftSelect" class="form-control" required onchange="onStaffShiftSelectChange()">
            <?php foreach ($shifts as $sh): ?>
              <option value="<?= $sh['id'] ?>" data-name="<?= htmlspecialchars($sh['name']) ?>" data-start="<?= $sh['start_time'] ?>" data-end="<?= $sh['end_time'] ?>" data-grace="<?= $sh['grace_period_mins'] ?>" data-half="<?= $sh['half_day_threshold_mins'] ?>" data-color="<?= $sh['color'] ?>">
                <?= htmlspecialchars($sh['name']) ?> (<?= date('h:i A', strtotime($sh['start_time'])) ?> - <?= date('h:i A', strtotime($sh['end_time'])) ?>)
              </option>
            <?php endforeach; ?>
          </select>
          <div id="staffShiftTimingBadge" style="font-size:0.75rem; color:var(--text-muted); margin-top:0.35rem; background:var(--bg-main); padding:0.35rem 0.6rem; border-radius:6px; border:1px solid var(--border-color);">
            ⏰ Timing: <strong>05:00 AM - 09:00 AM</strong> | ⏱️ Grace: 15 mins
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Biometric Device ID</label>
          <input type="text" name="biometric_id" id="staffBiometricInput" class="form-control" placeholder="e.g. STF-101">
        </div>
      </div>

      <div class="form-group">
        <label class="form-label">Base Monthly Salary (<?= $currency ?>)</label>
        <input type="number" step="0.01" name="base_salary" id="staffSalaryInput" class="form-control" value="18000" required>
      </div>

      <div class="form-group">
        <label class="form-label">Status</label>
        <select name="status" id="staffStatusSelect" class="form-control">
          <option value="active">Active Staff</option>
          <option value="inactive">Inactive / On Leave</option>
        </select>
      </div>

      <div style="display:flex; gap:0.75rem; margin-top:1.5rem;">
        <button type="button" class="btn btn-secondary btn-block" onclick="closeModal('addStaffModal')">Cancel</button>
        <button type="submit" class="btn btn-primary btn-block">✓ SAVE STAFF PROFILE</button>
      </div>
    </form>
  </div>
</div>

<!-- MANAGE / EDIT SHIFT MODAL -->
<div class="modal" id="shiftConfigModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:100; align-items:center; justify-content:center;">
  <div class="modal-overlay" style="position:absolute; inset:0;" onclick="closeModal('shiftConfigModal')"></div>
  <div class="card" style="position:relative; z-index:10; background:#fff; width:90%; max-width:500px; border-radius:16px; padding:1.5rem; border-top:5px solid #10B981;">
    <div class="card-header" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;">
      <span class="card-title" id="shiftModalTitle" style="font-size:1.2rem; font-weight:800; color:#065F46;">📅 Configure Staff Shift Schedule</span>
      <button style="background:none; border:none; font-size:1.2rem; cursor:pointer;" onclick="closeModal('shiftConfigModal')">✕</button>
    </div>

    <!-- Quick Preset Buttons -->
    <div style="margin-bottom:1rem;">
      <label class="form-label" style="font-size:0.75rem; color:var(--text-muted); font-weight:700;">⚡ QUICK TIMING PRESETS:</label>
      <div style="display:flex; gap:0.4rem; flex-wrap:wrap;">
        <button type="button" class="btn btn-sm" onclick="applyShiftPreset('Morning Shift', '05:00', '09:00', 15, 120)" style="font-size:0.75rem; background:#EFF6FF; color:#1E40AF; border:1px solid #BFDBFE; font-weight:700;">
          🌅 Morning (05-09 AM)
        </button>
        <button type="button" class="btn btn-sm" onclick="applyShiftPreset('Evening Shift', '17:00', '21:00', 15, 120)" style="font-size:0.75rem; background:#FFFBEB; color:#B45309; border:1px solid #FCD34D; font-weight:700;">
          🌆 Evening (05-09 PM)
        </button>
        <button type="button" class="btn btn-sm" onclick="applyShiftPreset('Split Shift', '05:00', '21:00', 15, 120)" style="font-size:0.75rem; background:#F5F3FF; color:#6D28D9; border:1px solid #DDD6FE; font-weight:700;">
          🔄 Split (05-09 AM &amp; PM)
        </button>
        <button type="button" class="btn btn-sm" onclick="applyShiftPreset('General Shift', '10:00', '18:00', 15, 120)" style="font-size:0.75rem; background:#F0FDF4; color:#15803D; border:1px solid #86EFAC; font-weight:700;">
          🏢 General (10 AM-06 PM)
        </button>
      </div>
    </div>

    <form onsubmit="saveShift(event)">
      <input type="hidden" name="id" id="shiftId" value="0">

      <div class="form-group">
        <label class="form-label">Shift Name *</label>
        <input type="text" name="name" id="shiftNameInput" class="form-control" placeholder="e.g. Morning Shift (05:00 AM - 09:00 AM)" required>
      </div>

      <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.75rem;">
        <div class="form-group">
          <label class="form-label">Shift Start Time *</label>
          <input type="time" name="start_time" id="shiftStartTime" class="form-control" value="05:00" required>
        </div>
        <div class="form-group">
          <label class="form-label">Shift End Time *</label>
          <input type="time" name="end_time" id="shiftEndTime" class="form-control" value="09:00" required>
        </div>
      </div>

      <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.75rem;">
        <div class="form-group">
          <label class="form-label">Grace Period (Minutes)</label>
          <input type="number" name="grace_period_mins" id="shiftGraceMins" class="form-control" value="15" required>
          <p style="font-size:0.72rem; color:var(--text-muted); margin-top:0.2rem;">Punching after this marks Late</p>
        </div>
        <div class="form-group">
          <label class="form-label">Half-Day Threshold (Mins)</label>
          <input type="number" name="half_day_threshold_mins" id="shiftHalfDayMins" class="form-control" value="120" required>
          <p style="font-size:0.72rem; color:var(--text-muted); margin-top:0.2rem;">Late &gt; 2 hrs marks Half Day</p>
        </div>
      </div>

      <div style="display:flex; gap:0.75rem; margin-top:1.5rem;">
        <button type="button" class="btn btn-secondary btn-block" onclick="closeModal('shiftConfigModal')">Cancel</button>
        <button type="submit" class="btn btn-primary btn-block" style="background:#059669; border-color:#059669; font-weight:800;">✓ SAVE SHIFT SCHEDULE</button>
      </div>
    </form>
  </div>
</div>

<!-- GENERATE PAYROLL MODAL -->
<div class="modal" id="generatePayrollModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:100; align-items:center; justify-content:center;">
  <div class="modal-overlay" style="position:absolute; inset:0;" onclick="closeModal('generatePayrollModal')"></div>
  <div class="card" style="position:relative; z-index:10; background:#fff; width:90%; max-width:450px; border-radius:16px; padding:1.5rem;">
    <div class="card-header" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;">
      <span class="card-title" style="font-size:1.2rem; font-weight:800;">💵 Generate Monthly Payslip</span>
      <button style="background:none; border:none; font-size:1.2rem; cursor:pointer;" onclick="closeModal('generatePayrollModal')">✕</button>
    </div>

    <form onsubmit="savePayroll(event)">
      <div class="form-group">
        <label class="form-label">Staff Member *</label>
        <select name="staff_id" id="payrollStaffSelect" class="form-control" required onchange="onPayrollStaffSelect()">
          <option value="">-- Select Staff Member --</option>
          <?php foreach ($staffList as $st): ?>
            <option value="<?= $st['id'] ?>" data-salary="<?= $st['base_salary'] ?>">
              <?= htmlspecialchars($st['name']) ?> (<?= htmlspecialchars($st['role']) ?> - ₹<?= number_format($st['base_salary']) ?>)
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.75rem;">
        <div class="form-group">
          <label class="form-label">Pay Period (Month)</label>
          <input type="month" name="pay_period" class="form-control" value="<?= date('Y-m') ?>" required>
        </div>
        <div class="form-group">
          <label class="form-label">Basic Salary (₹)</label>
          <input type="number" step="0.01" name="basic_salary" id="payrollBasicSalary" class="form-control" placeholder="18000" required>
        </div>
      </div>

      <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.75rem;">
        <div class="form-group">
          <label class="form-label">Allowances / PT Comm.</label>
          <input type="number" step="0.01" name="allowances" class="form-control" value="0">
        </div>
        <div class="form-group">
          <label class="form-label">Late / Leave Deductions</label>
          <input type="number" step="0.01" name="deductions" class="form-control" value="0">
        </div>
      </div>

      <div style="display:flex; gap:0.75rem; margin-top:1.5rem;">
        <button type="button" class="btn btn-secondary btn-block" onclick="closeModal('generatePayrollModal')">Cancel</button>
        <button type="submit" class="btn btn-primary btn-block">✓ ISSUE PAYSLIP</button>
      </div>
    </form>
  </div>
</div>

<script>
function switchStaffTab(tab) {
  document.querySelectorAll('.staff-tab-pane').forEach(el => el.style.display = 'none');
  document.querySelectorAll('.staff-tab-btn').forEach(btn => {
    btn.style.background = 'transparent';
    btn.style.color = 'var(--text-secondary)';
    btn.classList.remove('active');
  });

  if (tab === 'attendance') {
    document.getElementById('staffAttendanceTab').style.display = 'block';
    const b = document.getElementById('tabBtnAtt');
    b.style.background = 'var(--primary)';
    b.style.color = '#fff';
  } else if (tab === 'directory') {
    document.getElementById('staffDirectoryTab').style.display = 'block';
    const b = document.getElementById('tabBtnDir');
    b.style.background = 'var(--primary)';
    b.style.color = '#fff';
  } else if (tab === 'shifts') {
    document.getElementById('staffShiftsTab').style.display = 'block';
    const b = document.getElementById('tabBtnShifts');
    b.style.background = 'var(--primary)';
    b.style.color = '#fff';
  } else if (tab === 'payroll') {
    document.getElementById('staffPayrollTab').style.display = 'block';
    const b = document.getElementById('tabBtnPay');
    b.style.background = 'var(--primary)';
    b.style.color = '#fff';
  }
}

// Auto set live local datetime in punch modal
function openPunchModal() {
  const now = new Date();
  const pad = (n) => String(n).padStart(2, '0');
  const nowLocal = `${now.getFullYear()}-${pad(now.getMonth()+1)}-${pad(now.getDate())}T${pad(now.getHours())}:${pad(now.getMinutes())}`;
  const input = document.getElementById('punchTimeInput');
  if (input) input.value = nowLocal;
}

document.addEventListener('DOMContentLoaded', () => {
  openPunchModal();
});

function onPunchStaffSelect() {
  const sel = document.getElementById('punchStaffIdSelect');
  const opt = sel.options[sel.selectedIndex];
  const box = document.getElementById('punchStaffShiftInfo');
  if (opt && opt.value) {
    box.style.display = 'block';
    document.getElementById('punchStaffShiftName').innerText = opt.dataset.shift || 'Morning Shift';
    document.getElementById('punchStaffShiftTime').innerText = 'Starts ' + opt.dataset.start;
  } else {
    box.style.display = 'none';
  }
}

function submitStaffPunch(e) {
  e.preventDefault();
  const formData = new FormData(e.target);
  formData.append('action', 'punch_attendance');

  fetch('api/staff.php', {
    method: 'POST',
    body: formData
  })
  .then(res => res.json())
  .then(res => {
    if (res.success) {
      showToast(res.message, 'success');
      closeModal('punchStaffModal');
      setTimeout(() => location.reload(), 1200);
    } else {
      showToast(res.message || 'Punch failed', 'danger');
    }
  });
}

function punchStaffDirect(staffId, type) {
  const now = new Date();
  const pad = (n) => String(n).padStart(2, '0');
  const nowStr = `${now.getFullYear()}-${pad(now.getMonth()+1)}-${pad(now.getDate())} ${pad(now.getHours())}:${pad(now.getMinutes())}:${pad(now.getSeconds())}`;

  fetch('api/staff.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      action: 'punch_attendance',
      staff_id: staffId,
      punch_time: nowStr,
      method: 'biometric'
    })
  })
  .then(res => res.json())
  .then(res => {
    if (res.success) {
      showToast(res.message, 'success');
      setTimeout(() => location.reload(), 1000);
    } else {
      showToast(res.message || 'Punch failed', 'danger');
    }
  });
}

function openAddStaffModal() {
  document.getElementById('staffId').value = 0;
  document.getElementById('staffModalTitle').innerText = '➕ Add Staff Member';
  document.getElementById('staffNameInput').value = '';
  document.getElementById('staffPhoneInput').value = '';
  document.getElementById('staffBiometricInput').value = '';
  document.getElementById('staffSalaryInput').value = 18000;
  document.getElementById('staffStatusSelect').value = 'active';
  openModal('addStaffModal');
}

function openEditStaffModal(st) {
  document.getElementById('staffId').value = st.id;
  document.getElementById('staffModalTitle').innerText = '✏️ Edit Staff: ' + st.name;
  document.getElementById('staffNameInput').value = st.name || '';
  document.getElementById('staffRoleSelect').value = st.role || 'Receptionist';
  document.getElementById('staffPhoneInput').value = st.phone || '';
  document.getElementById('staffShiftSelect').value = st.shift_id || 1;
  document.getElementById('staffBiometricInput').value = st.biometric_id || '';
  document.getElementById('staffSalaryInput').value = st.base_salary || 18000;
  document.getElementById('staffStatusSelect').value = st.status || 'active';
  openModal('addStaffModal');
}

function saveStaff(e) {
  e.preventDefault();
  const formData = new FormData(e.target);
  formData.append('action', 'add');

  fetch('api/staff.php', {
    method: 'POST',
    body: formData
  })
  .then(res => res.json())
  .then(res => {
    if (res.success) {
      showToast(res.message, 'success');
      closeModal('addStaffModal');
      setTimeout(() => location.reload(), 1000);
    } else {
      showToast(res.message || 'Saving failed', 'danger');
    }
  });
}

function onStaffShiftSelectChange() {
  const sel = document.getElementById('staffShiftSelect');
  const badge = document.getElementById('staffShiftTimingBadge');
  if (!sel || !badge) return;
  const opt = sel.options[sel.selectedIndex];
  if (opt) {
    const start = opt.dataset.start || '05:00:00';
    const end = opt.dataset.end || '09:00:00';
    const grace = opt.dataset.grace || '15';
    badge.innerHTML = `⏰ Timing: <strong>${start.substring(0,5)} - ${end.substring(0,5)}</strong> | ⏱️ Grace: ${grace} mins`;
  }
}

function openCreateShiftModal() {
  document.getElementById('shiftId').value = 0;
  document.getElementById('shiftModalTitle').innerText = '📅 Create New Shift Schedule';
  document.getElementById('shiftNameInput').value = '';
  document.getElementById('shiftStartTime').value = '05:00';
  document.getElementById('shiftEndTime').value = '09:00';
  document.getElementById('shiftGraceMins').value = 15;
  document.getElementById('shiftHalfDayMins').value = 120;
  openModal('shiftConfigModal');
}

function openEditSelectedShift() {
  const sel = document.getElementById('staffShiftSelect');
  if (!sel) return;
  const opt = sel.options[sel.selectedIndex];
  if (!opt) return;
  const sh = {
    id: opt.value,
    name: opt.dataset.name || opt.text,
    start_time: (opt.dataset.start || '05:00:00').substring(0, 5),
    end_time: (opt.dataset.end || '09:00:00').substring(0, 5),
    grace_period_mins: opt.dataset.grace || 15,
    half_day_threshold_mins: opt.dataset.half || 120
  };
  openEditShiftModal(sh);
}

function applyShiftPreset(name, start, end, grace, halfDay) {
  document.getElementById('shiftNameInput').value = name;
  document.getElementById('shiftStartTime').value = start;
  document.getElementById('shiftEndTime').value = end;
  document.getElementById('shiftGraceMins').value = grace || 15;
  document.getElementById('shiftHalfDayMins').value = halfDay || 120;
  showToast('Preset applied: ' + name, 'info');
}

function openEditShiftModal(sh) {
  document.getElementById('shiftId').value = sh.id;
  document.getElementById('shiftModalTitle').innerText = '📅 Edit Shift: ' + sh.name;
  document.getElementById('shiftNameInput').value = sh.name;
  document.getElementById('shiftStartTime').value = (sh.start_time || '05:00').substring(0, 5);
  document.getElementById('shiftEndTime').value = (sh.end_time || '09:00').substring(0, 5);
  document.getElementById('shiftGraceMins').value = sh.grace_period_mins || 15;
  document.getElementById('shiftHalfDayMins').value = sh.half_day_threshold_mins || 120;
  openModal('shiftConfigModal');
}

function saveShift(e) {
  e.preventDefault();
  const formData = new FormData(e.target);
  formData.append('action', 'save_shift');

  fetch('api/staff.php', {
    method: 'POST',
    body: formData
  })
  .then(res => res.json())
  .then(res => {
    if (res.success) {
      showToast(res.message, 'success');
      closeModal('shiftConfigModal');
      setTimeout(() => location.reload(), 1000);
    } else {
      showToast(res.message || 'Shift save failed', 'danger');
    }
  });
}

function onPayrollStaffSelect() {
  const sel = document.getElementById('payrollStaffSelect');
  const opt = sel.options[sel.selectedIndex];
  if (opt && opt.dataset.salary) {
    document.getElementById('payrollBasicSalary').value = opt.dataset.salary;
  }
}

function savePayroll(e) {
  e.preventDefault();
  const formData = new FormData(e.target);
  formData.append('action', 'generate_payroll');

  fetch('api/staff.php', {
    method: 'POST',
    body: formData
  })
  .then(res => res.json())
  .then(res => {
    if (res.success) {
      showToast(res.message, 'success');
      closeModal('generatePayrollModal');
      setTimeout(() => location.reload(), 1000);
    } else {
      showToast(res.message || 'Payroll failed', 'danger');
    }
  });
}
</script>
