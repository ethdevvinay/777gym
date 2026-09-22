<!-- Enterprise Gym 360° AI-Powered Command Center & Advanced Analytics Dashboard -->
<?php
$db = getDB();

// 1. PRIMARY EXECUTIVE METRICS & COUNTERS
$totalMembers = (int) $db->query("SELECT COUNT(*) FROM members")->fetchColumn();
$activeMembers = (int) $db->query("SELECT COUNT(*) FROM members WHERE status = 'active'")->fetchColumn();
$expiredMembers = (int) $db->query("SELECT COUNT(*) FROM members WHERE status = 'expired' OR status = 'inactive'")->fetchColumn();
$retentionRate = $totalMembers > 0 ? round(($activeMembers / $totalMembers) * 100, 1) : 0;

// Financial Collections Today & Month
$todayCollection = (float) $db->query("SELECT IFNULL(SUM(paid_amount), 0) FROM sales WHERE DATE(created_at) = CURRENT_DATE()")->fetchColumn();
$monthlyCollection = (float) $db->query("SELECT IFNULL(SUM(paid_amount), 0) FROM sales WHERE MONTH(created_at) = MONTH(CURRENT_DATE()) AND YEAR(created_at) = YEAR(CURRENT_DATE())")->fetchColumn();
$monthlyGoal = 250000; // Target: ₹2,50,000
$monthlyGoalPercent = min(100, round(($monthlyCollection / $monthlyGoal) * 100, 1));
$pendingDues = (float) $db->query("SELECT IFNULL(SUM(due_amount), 0) FROM sales WHERE payment_status != 'paid' AND due_amount > 0")->fetchColumn();

// Attendance Punches Today
$todayCheckins = (int) $db->query("SELECT COUNT(*) FROM attendance WHERE DATE(check_in_time) = CURRENT_DATE()")->fetchColumn();

// 2. EXPIRY BRACKETS BREAKDOWN (15 Days, 1 Week, 2 Days, Same Day, 3 Weeks)
// A. Same Day & Overdue
$expiringSameDay = $db->query("
    SELECT s.*, m.id as member_id, m.name as member_name, m.phone, m.member_code, mt.title as plan_title,
           DATEDIFF(s.end_date, CURRENT_DATE()) as days_left
    FROM member_subscriptions s
    JOIN members m ON s.member_id = m.id
    JOIN membership_types mt ON s.membership_type_id = mt.id
    WHERE s.status = 'active' AND s.end_date <= CURRENT_DATE()
    ORDER BY s.end_date ASC
")->fetchAll();

// B. 1 to 2 Days Left
$expiring2Days = $db->query("
    SELECT s.*, m.id as member_id, m.name as member_name, m.phone, m.member_code, mt.title as plan_title,
           DATEDIFF(s.end_date, CURRENT_DATE()) as days_left
    FROM member_subscriptions s
    JOIN members m ON s.member_id = m.id
    JOIN membership_types mt ON s.membership_type_id = mt.id
    WHERE s.status = 'active' AND s.end_date BETWEEN DATE_ADD(CURRENT_DATE(), INTERVAL 1 DAY) AND DATE_ADD(CURRENT_DATE(), INTERVAL 2 DAY)
    ORDER BY s.end_date ASC
")->fetchAll();

// C. 3 to 7 Days Left (1 Week)
$expiring1Week = $db->query("
    SELECT s.*, m.id as member_id, m.name as member_name, m.phone, m.member_code, mt.title as plan_title,
           DATEDIFF(s.end_date, CURRENT_DATE()) as days_left
    FROM member_subscriptions s
    JOIN members m ON s.member_id = m.id
    JOIN membership_types mt ON s.membership_type_id = mt.id
    WHERE s.status = 'active' AND s.end_date BETWEEN DATE_ADD(CURRENT_DATE(), INTERVAL 3 DAY) AND DATE_ADD(CURRENT_DATE(), INTERVAL 7 DAY)
    ORDER BY s.end_date ASC
")->fetchAll();

// D. 8 to 15 Days Left (15 Days)
$expiring15Days = $db->query("
    SELECT s.*, m.id as member_id, m.name as member_name, m.phone, m.member_code, mt.title as plan_title,
           DATEDIFF(s.end_date, CURRENT_DATE()) as days_left
    FROM member_subscriptions s
    JOIN members m ON s.member_id = m.id
    JOIN membership_types mt ON s.membership_type_id = mt.id
    WHERE s.status = 'active' AND s.end_date BETWEEN DATE_ADD(CURRENT_DATE(), INTERVAL 8 DAY) AND DATE_ADD(CURRENT_DATE(), INTERVAL 15 DAY)
    ORDER BY s.end_date ASC
")->fetchAll();

// E. 16 to 21 Days Left (3 Weeks)
$expiring3Weeks = $db->query("
    SELECT s.*, m.id as member_id, m.name as member_name, m.phone, m.member_code, mt.title as plan_title,
           DATEDIFF(s.end_date, CURRENT_DATE()) as days_left
    FROM member_subscriptions s
    JOIN members m ON s.member_id = m.id
    JOIN membership_types mt ON s.membership_type_id = mt.id
    WHERE s.status = 'active' AND s.end_date BETWEEN DATE_ADD(CURRENT_DATE(), INTERVAL 16 DAY) AND DATE_ADD(CURRENT_DATE(), INTERVAL 21 DAY)
    ORDER BY s.end_date ASC
")->fetchAll();

$totalExpiringCount = count($expiringSameDay) + count($expiring2Days) + count($expiring1Week) + count($expiring15Days) + count($expiring3Weeks);

// 3. TODAY'S PAYMENTS & COLLECTIONS STREAM
$todayPayments = $db->query("
    SELECT s.*, m.name as member_name, m.member_code, m.phone as member_phone, u.name as cashier_name
    FROM sales s
    LEFT JOIN members m ON s.member_id = m.id
    LEFT JOIN users u ON s.created_by = u.id
    WHERE DATE(s.created_at) = CURRENT_DATE()
    ORDER BY s.id DESC
")->fetchAll();

// Payment Mode Summary (UPI, Cash, Card)
$modeSummary = $db->query("
    SELECT payment_method, IFNULL(SUM(paid_amount), 0) as total_amount, COUNT(*) as tx_count
    FROM sales
    WHERE DATE(created_at) = CURRENT_DATE()
    GROUP BY payment_method
")->fetchAll();

$upiTotal = 0; $cashTotal = 0; $cardTotal = 0;
foreach ($modeSummary as $ms) {
    if (stripos($ms['payment_method'], 'upi') !== false) $upiTotal += (float)$ms['total_amount'];
    elseif (stripos($ms['payment_method'], 'cash') !== false) $cashTotal += (float)$ms['total_amount'];
    elseif (stripos($ms['payment_method'], 'card') !== false) $cardTotal += (float)$ms['total_amount'];
}

// 4. PENDING DUES & PROMISED DATES LIST
$pendingDuesList = $db->query("
    SELECT s.*, m.name as member_name, m.member_code, m.phone as member_phone,
           DATEDIFF(s.due_date, CURRENT_DATE()) as days_until_due
    FROM sales s
    JOIN members m ON s.member_id = m.id
    WHERE s.payment_status != 'paid' AND s.due_amount > 0
    ORDER BY (s.due_date IS NULL) ASC, s.due_date ASC, s.due_amount DESC
    LIMIT 30
")->fetchAll();

// 5. ACTIVE TRIAL PASS HOLDERS & PROSPECTS
$trialMembers = $db->query("
    SELECT s.*, m.id as member_id, m.name as member_name, m.phone, m.member_code, mt.title as plan_title, mt.duration_days,
           DATEDIFF(s.end_date, CURRENT_DATE()) as days_left,
           (SELECT COUNT(*) FROM marketing_logs l WHERE l.member_id = m.id AND l.trigger_event = 'trial_welcome' AND DATE(l.sent_at) = DATE(s.start_date)) as welcome_sent
    FROM member_subscriptions s
    JOIN members m ON s.member_id = m.id
    JOIN membership_types mt ON s.membership_type_id = mt.id
    WHERE s.status = 'active' AND (mt.category = 'trial' OR mt.title LIKE '%trial%' OR mt.title LIKE '%pass%' OR mt.duration_days <= 15)
    ORDER BY s.end_date ASC
")->fetchAll();

// 6. SWIMMING POOL & PT ACTIVE SUMMARY
$activePoolSwimmers = $db->query("
    SELECT ps.*, m.name as member_name, m.member_code, m.phone, pp.title as plan_title, pp.slot_timing,
           DATEDIFF(ps.end_date, CURRENT_DATE()) as days_left
    FROM pool_subscriptions ps
    JOIN members m ON ps.member_id = m.id
    JOIN pool_plans pp ON ps.pool_plan_id = pp.id
    WHERE ps.status = 'active' AND ps.end_date >= CURRENT_DATE()
    ORDER BY ps.end_date ASC
")->fetchAll();

$activePtClients = $db->query("
    SELECT pts.*, m.name as member_name, m.member_code, m.phone, t.name as trainer_name, ptp.title as package_title
    FROM pt_subscriptions pts
    JOIN members m ON pts.member_id = m.id
    JOIN trainers t ON pts.trainer_id = t.id
    JOIN pt_packages ptp ON pts.pt_package_id = ptp.id
    WHERE pts.status = 'active' AND (pts.sessions_total = 0 OR pts.sessions_used < pts.sessions_total)
    ORDER BY pts.id DESC
")->fetchAll();

// 7. STAFF ATTENDANCE & SHIFTS TODAY (5-9 Morning & 5-9 Evening)
$todayStaffLogs = $db->query("
    SELECT sa.*, s.name as staff_name, s.role, s.phone, sh.name as shift_name
    FROM staff_attendance sa
    JOIN staff s ON sa.staff_id = s.id
    LEFT JOIN staff_shifts sh ON sa.shift_id = sh.id
    WHERE sa.date = CURRENT_DATE()
    ORDER BY sa.id DESC
")->fetchAll();

// 8. TODAY'S BIRTHDAYS
$todayBirthdays = $db->query("
    SELECT m.*, mt.title as plan_title 
    FROM members m
    LEFT JOIN member_subscriptions ms ON m.id = ms.member_id AND ms.status = 'active'
    LEFT JOIN membership_types mt ON ms.membership_type_id = mt.id
    WHERE m.dob IS NOT NULL 
      AND MONTH(m.dob) = MONTH(CURRENT_DATE()) 
      AND DAY(m.dob) = DAY(CURRENT_DATE())
    GROUP BY m.id
    ORDER BY m.name ASC
")->fetchAll();

// 9. ADVANCED ANALYTICS CHART DATA (7-Day Trend & Rush Hours)
$chartDates = [];
$chartRevenue = [];
$chartUpi = [];
$chartCash = [];

for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $chartDates[] = date('d M', strtotime($d));
    
    $dayRev = $db->query("SELECT IFNULL(SUM(paid_amount), 0) FROM sales WHERE DATE(created_at) = '{$d}'")->fetchColumn();
    $dayUpi = $db->query("SELECT IFNULL(SUM(paid_amount), 0) FROM sales WHERE DATE(created_at) = '{$d}' AND LOWER(payment_method) LIKE '%upi%'")->fetchColumn();
    $dayCash = $db->query("SELECT IFNULL(SUM(paid_amount), 0) FROM sales WHERE DATE(created_at) = '{$d}' AND LOWER(payment_method) LIKE '%cash%'")->fetchColumn();
    
    $chartRevenue[] = (float)$dayRev;
    $chartUpi[] = (float)$dayUpi;
    $chartCash[] = (float)$dayCash;
}

// Hourly Peak Rush Distribution (5 AM to 10 PM)
$hourlyPunches = [];
$hourlyLabels = ['5 AM', '6 AM', '7 AM', '8 AM', '9 AM', '10 AM', '11 AM', '12 PM', '1 PM', '2 PM', '3 PM', '4 PM', '5 PM', '6 PM', '7 PM', '8 PM', '9 PM', '10 PM'];
for ($h = 5; $h <= 22; $h++) {
    $cnt = $db->query("SELECT COUNT(*) FROM attendance WHERE DATE(check_in_time) = CURRENT_DATE() AND HOUR(check_in_time) = {$h}")->fetchColumn();
    $hourlyPunches[] = (int)$cnt;
}

// Plan Category Share
$planDistribution = $db->query("
    SELECT mt.title, COUNT(ms.id) as plan_count
    FROM member_subscriptions ms
    JOIN membership_types mt ON ms.membership_type_id = mt.id
    WHERE ms.status = 'active'
    GROUP BY mt.id
    ORDER BY plan_count DESC
    LIMIT 5
")->fetchAll();

$planLabels = [];
$planCounts = [];
foreach ($planDistribution as $pd) {
    $planLabels[] = $pd['title'];
    $planCounts[] = (int)$pd['plan_count'];
}

$currency = getSetting('currency_symbol', '₹');
$gymPhone = getSetting('gym_phone', '+91 98765 43210');
?>

<!-- Include Chart.js for Interactive Analytics Visualizations (100% Offline-Ready) -->
<script src="assets/js/chart.umd.min.js"></script>

<div class="page-content dash-container">
  
  <!-- ========================================================= -->
  <!-- 1. HERO EXECUTIVE COMMAND STRIP                           -->
  <!-- ========================================================= -->
  <header class="dash-hero-banner" role="banner">
    <div class="dash-hero-main">
      <div class="dash-hero-tag">
        <span class="dash-live-dot" aria-hidden="true"></span>
        <span>LIVE GYM OPERATIONS PULSE</span>
      </div>
      <h1 class="dash-hero-title">
        <span class="dash-hero-icon" aria-hidden="true">⚡</span>
        <span>Executive 360° Command Center</span>
      </h1>
      <p class="dash-hero-sub">
        Real-time metrics for Expiries, Collections, Dues, Active Trials &amp; Peak Footfall • Indian Standard Time (IST)
      </p>
    </div>

    <!-- Live Clock & Quick Operational Pill Badges -->
    <div class="dash-hero-meta">
      <div class="dash-clock-pill">
        <span class="dash-clock-label">SYSTEM TIME (IST)</span>
        <div id="liveClockDisplay" class="dash-clock-val"><?= date('h:i:s A') ?></div>
      </div>
      <div class="dash-hero-stats">
        <span class="dash-meta-chip chip-success" title="Total Active Members">
          <span class="chip-dot"></span> <strong><?= number_format($activeMembers) ?></strong> Active
        </span>
        <span class="dash-meta-chip chip-danger" title="Total Expiring Soon in 21 Days">
          <span class="chip-dot"></span> <strong><?= $totalExpiringCount ?></strong> Expiries
        </span>
        <span class="dash-meta-chip chip-info" title="Today's Check-ins">
          <span class="chip-dot"></span> <strong><?= $todayCheckins ?></strong> Check-ins
        </span>
      </div>
    </div>
  </header>

  <!-- ========================================================= -->
  <!-- 2. 1-CLICK EASY-ACCESS QUICK OPERATIONS LAUNCHPAD         -->
  <!-- ========================================================= -->
  <section class="dash-section" aria-labelledby="quick-ops-heading">
    <div class="dash-section-header">
      <h2 id="quick-ops-heading" class="dash-section-title">
        <span aria-hidden="true">🚀</span> <span>1-CLICK QUICK OPERATIONS LAUNCHPAD</span>
      </h2>
      <span class="dash-shortcuts-tip">
        Shortcuts: <kbd>F1</kbd> POS &bull; <kbd>F3</kbd> Add Member &bull; <kbd>F5</kbd> Attendance
      </span>
    </div>

    <div class="dash-quick-grid">
      <!-- Button 1: POS / New Sale -->
      <a href="index.php?page=pos" class="dash-quick-card qk-blue" title="Open Touch POS or Record New Sale [F1]">
        <div class="qk-icon-wrapper" aria-hidden="true">🛒</div>
        <div class="qk-info">
          <span class="qk-title">New Sale / POS</span>
          <span class="qk-sub"><kbd>F1</kbd> Instant Sale</span>
        </div>
      </a>

      <!-- Button 2: Add Member -->
      <button type="button" onclick="openModal('newMemberModal')" class="dash-quick-card qk-emerald" title="Register New Gym Member [F3]">
        <div class="qk-icon-wrapper" aria-hidden="true">👤</div>
        <div class="qk-info">
          <span class="qk-title">+ Add Member</span>
          <span class="qk-sub"><kbd>F3</kbd> Quick Register</span>
        </div>
      </button>

      <!-- Button 3: Scan Attendance -->
      <a href="index.php?page=attendance" class="dash-quick-card qk-purple" title="Punch or Scan Member Attendance [F5]">
        <div class="qk-icon-wrapper" aria-hidden="true">⏱️</div>
        <div class="qk-info">
          <span class="qk-title">Attendance</span>
          <span class="qk-sub"><kbd>F5</kbd> Scan QR / In-Out</span>
        </div>
      </a>

      <!-- Button 4: Collect Dues -->
      <button type="button" onclick="switchDashTab('pending', document.getElementById('btnTabPending')); (document.getElementById('dashTab_pending') || document.getElementById('tabPanePending'))?.scrollIntoView({behavior:'smooth'});" class="dash-quick-card qk-rose" title="View & Collect Outstanding Dues">
        <div class="qk-icon-wrapper" aria-hidden="true">💵</div>
        <div class="qk-info">
          <span class="qk-title">Collect Dues</span>
          <span class="qk-sub font-semibold"><?= count($pendingDuesList) ?> Pending</span>
        </div>
      </button>

      <!-- Button 5: Swimming Pool -->
      <a href="index.php?page=pool" class="dash-quick-card qk-cyan" title="Manage Swimming Pool Plans & Slots">
        <div class="qk-icon-wrapper" aria-hidden="true">🏊</div>
        <div class="qk-info">
          <span class="qk-title">Swimming Pool</span>
          <span class="qk-sub">Passes &amp; Slots</span>
        </div>
      </a>

      <!-- Button 6: Personal Training -->
      <a href="index.php?page=trainers" class="dash-quick-card qk-amber" title="Manage PT Clients & Trainers">
        <div class="qk-icon-wrapper" aria-hidden="true">💪</div>
        <div class="qk-info">
          <span class="qk-title">PT Coaching</span>
          <span class="qk-sub">Trainers &amp; Clients</span>
        </div>
      </a>

      <!-- Button 7: WhatsApp Marketing -->
      <a href="index.php?page=marketing" class="dash-quick-card qk-teal" title="Broadcast WhatsApp Offers & Alerts">
        <div class="qk-icon-wrapper" aria-hidden="true">📢</div>
        <div class="qk-info">
          <span class="qk-title">WhatsApp Offers</span>
          <span class="qk-sub">Broadcasts &amp; Alerts</span>
        </div>
      </a>

      <!-- Button 8: Staff & Payslips -->
      <a href="index.php?page=staff" class="dash-quick-card qk-indigo" title="Manage Staff Attendance & Payroll">
        <div class="qk-icon-wrapper" aria-hidden="true">👔</div>
        <div class="qk-info">
          <span class="qk-title">Staff &amp; Payroll</span>
          <span class="qk-sub">Shifts &amp; Payslips</span>
        </div>
      </a>
    </div>
  </section>

  <!-- ========================================================= -->
  <!-- 3. BIRTHDAY ALERT BANNER (Conditional)                    -->
  <!-- ========================================================= -->
  <?php if (!empty($todayBirthdays)): ?>
    <section class="dash-bday-banner" aria-label="Today's Birthdays">
      <div class="bday-banner-content">
        <div class="bday-icon-circle" aria-hidden="true">🎂</div>
        <div>
          <h3 class="bday-title"><?= count($todayBirthdays) ?> Member(s) Celebrating Birthday Today!</h3>
          <p class="bday-desc">Send 1-click personalized WhatsApp birthday wishes &amp; festive renewal discounts.</p>
        </div>
      </div>
      <div class="bday-action-group">
        <?php foreach ($todayBirthdays as $bm): ?>
          <?php
            $cleanP = preg_replace('/[^0-9]/', '', $bm['phone']);
            if (strlen($cleanP) === 10) $cleanP = '91' . $cleanP;
            $bMsg = "🎂 Happy Birthday {$bm['name']}! THE CLUB 777® wishes you tremendous health, strength, and joy! 🎉 Claim your 15% birthday discount on your renewal today!";
            $bWaUrl = "https://wa.me/{$cleanP}?text=" . urlencode($bMsg);
          ?>
          <a href="<?= $bWaUrl ?>" target="_blank" class="btn btn-warning btn-sm bday-btn" title="Send WhatsApp Birthday Message to <?= htmlspecialchars($bm['name']) ?>">
            <span>🎉 Wish <?= htmlspecialchars(explode(' ', $bm['name'])[0]) ?></span>
          </a>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endif; ?>

  <!-- ========================================================= -->
  <!-- 4. 6 REAL-TIME EXECUTIVE KPI SUMMARY TILES                -->
  <!-- UX Ordering: Active Members -> Today's Collection ->     -->
  <!-- Pending Dues -> Expiring Pipeline -> Monthly Goal -> Hub -->
  <!-- ========================================================= -->
  <section class="dash-kpi-container" aria-label="Executive Metrics">
    
    <!-- 1. Active Members & Retention -->
    <div class="kpi-box kpi-theme-primary">
      <div class="kpi-box-top">
        <div class="kpi-meta-left">
          <span class="kpi-label">ACTIVE MEMBERS</span>
          <div class="kpi-value-row">
            <span class="kpi-number text-primary"><?= number_format($activeMembers) ?></span>
            <span class="kpi-total-sub">/ <?= number_format($totalMembers) ?> Total</span>
          </div>
        </div>
        <div class="kpi-badge badge-primary-subtle" title="Active member retention rate">
          <?= $retentionRate ?>% Retention
        </div>
      </div>
      <div class="kpi-box-bottom">
        <span class="kpi-stat-item stat-success">
          <span class="kpi-indicator dot-success" aria-hidden="true"></span>
          <span>Active: <strong><?= number_format($activeMembers) ?></strong></span>
        </span>
        <span class="kpi-stat-item stat-danger">
          <span class="kpi-indicator dot-danger" aria-hidden="true"></span>
          <span>Expired: <strong><?= number_format($expiredMembers) ?></strong></span>
        </span>
      </div>
    </div>

    <!-- 2. Today's Collections & Payment Modes -->
    <div class="kpi-box kpi-theme-success">
      <div class="kpi-box-top">
        <div class="kpi-meta-left">
          <span class="kpi-label">TODAY'S COLLECTION</span>
          <div class="kpi-value-row">
            <span class="kpi-number text-success"><?= $currency ?><?= number_format($todayCollection, 2) ?></span>
          </div>
        </div>
        <div class="kpi-badge badge-success-subtle" title="Invoices generated today">
          <?= count($todayPayments) ?> Invoices
        </div>
      </div>
      <div class="kpi-box-bottom">
        <span class="kpi-stat-item text-secondary">
          <span>📱 UPI: <strong><?= $currency ?><?= number_format($upiTotal) ?></strong></span>
        </span>
        <span class="kpi-stat-item text-secondary">
          <span>💵 Cash: <strong><?= $currency ?><?= number_format($cashTotal) ?></strong></span>
        </span>
      </div>
    </div>

    <!-- 3. Outstanding Pending Dues -->
    <div class="kpi-box kpi-theme-danger">
      <div class="kpi-box-top">
        <div class="kpi-meta-left">
          <span class="kpi-label text-danger font-bold">TOTAL PENDING DUES</span>
          <div class="kpi-value-row">
            <span class="kpi-number text-danger"><?= $currency ?><?= number_format($pendingDues, 2) ?></span>
          </div>
        </div>
        <div class="kpi-badge badge-danger-subtle font-bold" title="Unpaid accounts">
          ⚠️ <?= count($pendingDuesList) ?> Members
        </div>
      </div>
      <div class="kpi-box-bottom">
        <span class="kpi-stat-item text-danger" title="Track promised payment dates">
          <span>📅 Track promised payment dates</span>
        </span>
      </div>
    </div>

    <!-- 4. Expiring Next 21 Days Pipeline -->
    <div class="kpi-box kpi-theme-warning">
      <div class="kpi-box-top">
        <div class="kpi-meta-left">
          <span class="kpi-label">EXPIRING SOON (21 DAYS)</span>
          <div class="kpi-value-row">
            <span class="kpi-number text-warning"><?= $totalExpiringCount ?></span>
            <span class="kpi-total-sub">Pipeline</span>
          </div>
        </div>
        <div class="kpi-badge badge-warning-subtle font-bold" title="Expiring today or overdue">
          <?= count($expiringSameDay) ?> Today
        </div>
      </div>
      <div class="kpi-box-bottom">
        <span class="kpi-stat-item text-amber-deep">
          <span>⚡ 15d: <strong><?= count($expiring15Days) ?></strong> &bull; 7d: <strong><?= count($expiring1Week) ?></strong> &bull; 2d: <strong><?= count($expiring2Days) ?></strong></span>
        </span>
      </div>
    </div>

    <!-- 5. Monthly Revenue Goal Tracker -->
    <div class="kpi-box kpi-theme-purple">
      <div class="kpi-box-top">
        <div class="kpi-meta-left">
          <span class="kpi-label">MONTHLY COLLECTION</span>
          <div class="kpi-value-row">
            <span class="kpi-number text-purple"><?= $currency ?><?= number_format($monthlyCollection, 2) ?></span>
          </div>
        </div>
        <div class="kpi-badge badge-purple-subtle font-bold">
          <?= $monthlyGoalPercent ?>% Target
        </div>
      </div>
      <div class="kpi-box-bottom kpi-progress-col">
        <div class="dash-progress-track" role="progressbar" aria-valuenow="<?= $monthlyGoalPercent ?>" aria-valuemin="0" aria-valuemax="100">
          <div class="dash-progress-fill" style="width: <?= $monthlyGoalPercent ?>%;"></div>
        </div>
        <div class="kpi-progress-meta">
          <span>Target: <?= $currency ?><?= number_format($monthlyGoal) ?></span>
          <span><strong><?= $monthlyGoalPercent ?>%</strong> Achieved</span>
        </div>
      </div>
    </div>

    <!-- 6. Active Trials & Multi-Sport Hub -->
    <div class="kpi-box kpi-theme-info">
      <div class="kpi-box-top">
        <div class="kpi-meta-left">
          <span class="kpi-label">ACTIVE TRIAL PASSES</span>
          <div class="kpi-value-row">
            <span class="kpi-number text-info"><?= count($trialMembers) ?></span>
            <span class="kpi-total-sub">Prospects</span>
          </div>
        </div>
        <div class="kpi-badge badge-info-subtle font-bold">
          48h Offer
        </div>
      </div>
      <div class="kpi-box-bottom">
        <span class="kpi-stat-item text-secondary">
          <span>🏊 Pool: <strong><?= count($activePoolSwimmers) ?></strong></span>
        </span>
        <span class="kpi-stat-item text-secondary">
          <span>💪 PT: <strong><?= count($activePtClients) ?></strong></span>
        </span>
      </div>
    </div>

  </section>

  <!-- ========================================================= -->
  <!-- 5. ADVANCED VISUAL ANALYTICS SECTION (CHARTS)             -->
  <!-- ========================================================= -->
  <section class="dash-analytics-grid" aria-label="Visual Analytics Charts">
    
    <!-- Chart 1: 7-Day Revenue & Collection Stream (UPI vs Cash) -->
    <div class="card chart-card">
      <div class="card-header chart-card-header">
        <div class="chart-header-left">
          <h2 class="card-title">
            <span aria-hidden="true">📈</span> <span>7-Day Revenue &amp; Collection Analytics</span>
          </h2>
          <span class="card-subtitle">Daily income trend comparison (Total vs UPI)</span>
        </div>
        <span class="badge badge-primary">Live Stream</span>
      </div>
      <div class="chart-canvas-wrapper">
        <canvas id="revenueChart"></canvas>
      </div>
    </div>

    <!-- Chart 2: Today's Hourly Gym Rush Hours (Peak Footfall) -->
    <div class="card chart-card">
      <div class="card-header chart-card-header">
        <div class="chart-header-left">
          <h2 class="card-title">
            <span aria-hidden="true">⏱️</span> <span>Today's Peak Rush Hours</span>
          </h2>
          <span class="card-subtitle">Hourly biometric check-in distribution</span>
        </div>
        <span class="badge badge-success"><?= $todayCheckins ?> Punches</span>
      </div>
      <div class="chart-canvas-wrapper">
        <canvas id="rushChart"></canvas>
      </div>
    </div>

  </section>

  <!-- ========================================================= -->
  <!-- 6. INTERACTIVE 360° COMMAND CENTER TABS                   -->
  <!-- ========================================================= -->
  <section class="dash-tabs-section" aria-label="Operations Pipeline Tabs">
    <nav class="dash-tab-nav" role="tablist" aria-label="Dashboard Operational Tabs">
      <button role="tab" aria-selected="true" aria-controls="dashTab_expiries" class="dash-tab-btn active" id="btnTabExpiries" onclick="switchDashTab('expiries', this)">
        <span class="tab-icon" aria-hidden="true">⚠️</span>
        <span class="tab-text">Expiries Pipeline</span>
        <span class="tab-badge badge-warning-count"><?= $totalExpiringCount ?></span>
      </button>

      <button role="tab" aria-selected="false" aria-controls="dashTab_payments" class="dash-tab-btn" id="btnTabPayments" onclick="switchDashTab('payments', this)">
        <span class="tab-icon" aria-hidden="true">💵</span>
        <span class="tab-text">Today's Collections</span>
        <span class="tab-badge badge-success-count"><?= count($todayPayments) ?></span>
      </button>

      <button role="tab" aria-selected="false" aria-controls="dashTab_pending" class="dash-tab-btn" id="btnTabPending" onclick="switchDashTab('pending', this)">
        <span class="tab-icon" aria-hidden="true">🚨</span>
        <span class="tab-text">Pending Dues &amp; Dates</span>
        <span class="tab-badge badge-danger-count"><?= count($pendingDuesList) ?></span>
      </button>

      <button role="tab" aria-selected="false" aria-controls="dashTab_trials" class="dash-tab-btn" id="btnTabTrials" onclick="switchDashTab('trials', this)">
        <span class="tab-icon" aria-hidden="true">🏋️‍♂️</span>
        <span class="tab-text">Active Trial Passes</span>
        <span class="tab-badge badge-info-count"><?= count($trialMembers) ?></span>
      </button>

      <button role="tab" aria-selected="false" aria-controls="dashTab_pool_pt" class="dash-tab-btn" id="btnTabPoolPt" onclick="switchDashTab('pool_pt', this)">
        <span class="tab-icon" aria-hidden="true">🏊</span>
        <span class="tab-text">Pool &amp; PT Hub</span>
        <span class="tab-badge badge-purple-count"><?= count($activePoolSwimmers) + count($activePtClients) ?></span>
      </button>

      <button role="tab" aria-selected="false" aria-controls="dashTab_staff" class="dash-tab-btn" id="btnTabStaff" onclick="switchDashTab('staff', this)">
        <span class="tab-icon" aria-hidden="true">⏱️</span>
        <span class="tab-text">Staff Attendance</span>
        <span class="tab-badge badge-secondary-count"><?= count($todayStaffLogs) ?></span>
      </button>
    </nav>

    <!-- ========================================================= -->
    <!-- TAB 1: MEMBERSHIP EXPIRIES BREAKDOWN                      -->
    <!-- ========================================================= -->
    <div id="dashTab_expiries" class="dash-tab-pane" role="tabpanel" aria-labelledby="btnTabExpiries">
      <!-- Sub-Filters for Expiry Brackets -->
      <div class="dash-filter-bar" role="group" aria-label="Filter Expiry Categories">
        <button class="btn btn-sm exp-filter-btn active" onclick="filterExpiryCards('all', this)">
          <span>🌟 All Expiries (<?= $totalExpiringCount ?>)</span>
        </button>
        <button class="btn btn-sm exp-filter-btn filter-btn-info" onclick="filterExpiryCards('15days', this)">
          <span>⏳ 15 Days Left (<?= count($expiring15Days) ?>)</span>
        </button>
        <button class="btn btn-sm exp-filter-btn filter-btn-warning" onclick="filterExpiryCards('1week', this)">
          <span>⚠️ 1 Week Left (7d) (<?= count($expiring1Week) ?>)</span>
        </button>
        <button class="btn btn-sm exp-filter-btn filter-btn-danger" onclick="filterExpiryCards('2days', this)">
          <span>🚨 2 Days Left (<?= count($expiring2Days) ?>)</span>
        </button>
        <button class="btn btn-sm exp-filter-btn filter-btn-urgent" onclick="filterExpiryCards('sameday', this)">
          <span>🔔 Expiring Today / Overdue (<?= count($expiringSameDay) ?>)</span>
        </button>
        <button class="btn btn-sm exp-filter-btn filter-btn-purple" onclick="filterExpiryCards('3weeks', this)">
          <span>📅 3 Weeks Left (<?= count($expiring3Weeks) ?>)</span>
        </button>
      </div>

      <!-- Expiring Members Table Card -->
      <div class="card dash-table-card">
        <div class="table-responsive">
          <table class="table table-modern" style="width:100%;">
            <thead>
              <tr>
                <th scope="col">Member Details</th>
                <th scope="col">Phone</th>
                <th scope="col">Current Plan</th>
                <th scope="col">Expiry Date</th>
                <th scope="col">Remaining Days</th>
                <th scope="col" style="text-align:right;">1-Click Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php
              $allExpiringCombined = array_merge(
                  array_map(function($i){ $i['bracket'] = 'sameday'; return $i; }, $expiringSameDay),
                  array_map(function($i){ $i['bracket'] = '2days'; return $i; }, $expiring2Days),
                  array_map(function($i){ $i['bracket'] = '1week'; return $i; }, $expiring1Week),
                  array_map(function($i){ $i['bracket'] = '15days'; return $i; }, $expiring15Days),
                  array_map(function($i){ $i['bracket'] = '3weeks'; return $i; }, $expiring3Weeks)
              );
              ?>

              <?php if (empty($allExpiringCombined)): ?>
                <tr>
                  <td colspan="6" class="table-empty-cell">
                    <div class="empty-state-box">
                      <div class="empty-state-icon" aria-hidden="true">🎉</div>
                      <h4 class="empty-state-title">Great News!</h4>
                      <p class="empty-state-text">No memberships are expiring in the next 21 days.</p>
                    </div>
                  </td>
                </tr>
              <?php else: ?>
                <?php foreach ($allExpiringCombined as $item): ?>
                  <?php
                    $days = $item['days_left'];
                    $cleanPhone = preg_replace('/[^0-9]/', '', $item['phone']);
                    if (strlen($cleanPhone) === 10) $cleanPhone = '91' . $cleanPhone;
                    $endDateFmt = date('d M Y', strtotime($item['end_date']));

                    if ($days <= 0) {
                        $badgeClass = 'badge-danger';
                        $badgeText = '🔔 Expiring Today';
                        $waMsg = "🔔 Hello {$item['member_name']}, your {$item['plan_title']} at THE CLUB 777® expires TODAY ({$endDateFmt}). Please renew today at the reception to keep your workout streak and biometric access active!";
                    } elseif ($days <= 2) {
                        $badgeClass = 'badge-danger';
                        $badgeText = "🚨 {$days} Days Left";
                        $waMsg = "🚨 Urgent: Hi {$item['member_name']}, your {$item['plan_title']} at THE CLUB 777® will expire in {$days} DAYS on {$endDateFmt}. Renew today to enjoy uninterrupted gym workouts!";
                    } elseif ($days <= 7) {
                        $badgeClass = 'badge-warning';
                        $badgeText = "⚠️ {$days} Days Left (1w)";
                        $waMsg = "⚠️ Hi {$item['member_name']}, your {$item['plan_title']} at THE CLUB 777® will expire in {$days} days on {$endDateFmt}. Don't pause your workout streak—visit front desk to renew today!";
                    } elseif ($days <= 15) {
                        $badgeClass = 'badge-info';
                        $badgeText = "⏳ {$days} Days Left (15d)";
                        $waMsg = "⏳ Friendly Reminder: Hi {$item['member_name']}, your {$item['plan_title']} at THE CLUB 777® will expire in {$days} days on {$endDateFmt}. Plan your renewal early with our special long-term discount plans!";
                    } else {
                        $badgeClass = 'badge-secondary';
                        $badgeText = "📅 {$days} Days Left (3w)";
                        $waMsg = "⏳ Early Notice: Hi {$item['member_name']}, your {$item['plan_title']} at THE CLUB 777® will expire in 3 weeks on {$endDateFmt}. Plan ahead and renew early for special perks!";
                    }
                    $waUrl = "https://wa.me/{$cleanPhone}?text=" . urlencode($waMsg);
                  ?>
                  <tr class="exp-row" data-bracket="<?= $item['bracket'] ?>">
                    <td data-label="Member Details">
                      <div class="member-identity-col">
                        <div class="member-avatar-chip" aria-hidden="true">
                          <?= strtoupper(substr($item['member_name'], 0, 1)) ?>
                        </div>
                        <div>
                          <div class="member-full-name"><?= htmlspecialchars($item['member_name']) ?></div>
                          <div class="member-code-tag"><?= htmlspecialchars($item['member_code']) ?></div>
                        </div>
                      </div>
                    </td>
                    <td data-label="Phone">
                      <span class="phone-link-text"><?= htmlspecialchars($item['phone']) ?></span>
                    </td>
                    <td data-label="Current Plan">
                      <span class="badge badge-secondary font-semibold">
                        <?= htmlspecialchars($item['plan_title']) ?>
                      </span>
                    </td>
                    <td data-label="Expiry Date">
                      <span class="date-highlight-text"><?= $endDateFmt ?></span>
                    </td>
                    <td data-label="Remaining Days">
                      <span class="badge <?= $badgeClass ?> font-bold">
                        <?= $badgeText ?>
                      </span>
                    </td>
                    <td data-label="1-Click Actions" style="text-align:right;">
                      <div class="action-btn-cluster justify-end">
                        <a href="<?= $waUrl ?>" target="_blank" class="btn btn-sm btn-whatsapp" title="Send WhatsApp Renewal Reminder">
                          <span>💬 WhatsApp</span>
                        </a>
                        <a href="index.php?page=pos&member_id=<?= $item['member_id'] ?>" class="btn btn-primary btn-sm" title="Renew Member in POS">
                          <span>🔄 Renew</span>
                        </a>
                        <a href="index.php?page=member_profile&id=<?= $item['member_id'] ?>" class="btn btn-outline btn-sm" title="View Member Profile">
                          <span>Profile</span>
                        </a>
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

    <!-- ========================================================= -->
    <!-- TAB 2: TODAY'S PAYMENTS & COLLECTIONS STREAM              -->
    <!-- ========================================================= -->
    <div id="dashTab_payments" class="dash-tab-pane" role="tabpanel" aria-labelledby="btnTabPayments" style="display:none;">
      <!-- Revenue Summary Cards -->
      <div class="dash-summary-row">
        <div class="card summary-card border-success">
          <span class="summary-label">TRANSACTIONS TODAY</span>
          <div class="summary-val text-success"><?= count($todayPayments) ?> Invoices</div>
        </div>
        <div class="card summary-card border-primary">
          <span class="summary-label">UPI / QR PAYMENTS</span>
          <div class="summary-val text-primary"><?= $currency ?><?= number_format($upiTotal, 2) ?></div>
        </div>
        <div class="card summary-card border-warning">
          <span class="summary-label">CASH COLLECTED</span>
          <div class="summary-val text-warning"><?= $currency ?><?= number_format($cashTotal, 2) ?></div>
        </div>
        <div class="card summary-card border-purple">
          <span class="summary-label">MONTHLY TO DATE</span>
          <div class="summary-val text-purple"><?= $currency ?><?= number_format($monthlyCollection, 2) ?></div>
        </div>
      </div>

      <div class="card dash-table-card">
        <div class="card-header table-card-header">
          <div>
            <h3 class="card-title">
              <span aria-hidden="true">💵</span> <span>Today's Live Sales &amp; Collections Log</span>
            </h3>
            <span class="card-subtitle">Real-time point of sale transactions recorded today</span>
          </div>
          <a href="index.php?page=payments" class="btn btn-outline btn-sm">View All Invoices</a>
        </div>

        <div class="table-responsive">
          <table class="table table-modern" style="width:100%;">
            <thead>
              <tr>
                <th scope="col">Invoice #</th>
                <th scope="col">Member Name</th>
                <th scope="col">Total Amount</th>
                <th scope="col">Amount Paid</th>
                <th scope="col">Payment Mode</th>
                <th scope="col">Status</th>
                <th scope="col">Time</th>
                <th scope="col" style="text-align:right;">Action</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($todayPayments)): ?>
                <tr>
                  <td colspan="8" class="table-empty-cell">
                    <div class="empty-state-box">
                      <div class="empty-state-icon" aria-hidden="true">🧾</div>
                      <h4 class="empty-state-title">No Transactions Yet</h4>
                      <p class="empty-state-text">No payment records logged today. Launch Touch POS to process new sales.</p>
                    </div>
                  </td>
                </tr>
              <?php else: ?>
                <?php foreach ($todayPayments as $p): ?>
                  <tr>
                    <td data-label="Invoice #">
                      <span class="mono-code-text"><?= htmlspecialchars($p['invoice_no']) ?></span>
                    </td>
                    <td data-label="Member Name">
                      <div class="member-identity-col">
                        <div class="member-avatar-chip chip-neutral" aria-hidden="true">
                          <?= strtoupper(substr($p['member_name'] ?: 'W', 0, 1)) ?>
                        </div>
                        <div>
                          <strong class="member-full-name"><?= htmlspecialchars($p['member_name'] ?: 'Walk-in Customer') ?></strong>
                          <div class="member-sub-text"><?= htmlspecialchars($p['member_phone'] ?: 'N/A') ?></div>
                        </div>
                      </div>
                    </td>
                    <td data-label="Total Amount">
                      <span class="font-semibold"><?= $currency ?><?= number_format($p['total'], 2) ?></span>
                    </td>
                    <td data-label="Amount Paid">
                      <strong class="text-success font-bold"><?= $currency ?><?= number_format($p['paid_amount'], 2) ?></strong>
                    </td>
                    <td data-label="Payment Mode">
                      <span class="badge badge-info uppercase font-bold">
                        <?= htmlspecialchars($p['payment_method']) ?>
                      </span>
                    </td>
                    <td data-label="Status">
                      <span class="badge badge-<?= $p['payment_status'] === 'paid' ? 'success' : 'warning' ?> font-bold uppercase">
                        <?= strtoupper($p['payment_status']) ?>
                      </span>
                    </td>
                    <td data-label="Time">
                      <span class="time-stamp-text"><?= date('h:i A', strtotime($p['created_at'])) ?></span>
                    </td>
                    <td data-label="Action" style="text-align:right;">
                      <a href="index.php?page=receipt&id=<?= $p['id'] ?>" target="_blank" class="btn btn-outline btn-sm" title="Print/View Receipt">
                        <span>🧾 Receipt</span>
                      </a>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- ========================================================= -->
    <!-- TAB 3: PENDING DUES & PROMISED DATES                      -->
    <!-- ========================================================= -->
    <div id="dashTab_pending" class="dash-tab-pane" role="tabpanel" aria-labelledby="btnTabPending" style="display:none;">
      <div class="card dash-table-card border-top-danger">
        <div class="card-header table-card-header">
          <div>
            <h3 class="card-title text-danger font-bold">
              <span aria-hidden="true">🚨</span> <span>Members with Outstanding Balance / Pending Dues</span>
            </h3>
            <span class="card-subtitle">Track unpaid balances, promised payment dates &amp; send 1-click WhatsApp payment reminders</span>
          </div>
          <span class="badge badge-danger font-bold text-sm">
            Total Due: <?= $currency ?><?= number_format($pendingDues, 2) ?>
          </span>
        </div>

        <div class="table-responsive">
          <table class="table table-modern" style="width:100%;">
            <thead>
              <tr>
                <th scope="col">Member Name</th>
                <th scope="col">Phone</th>
                <th scope="col">Invoice #</th>
                <th scope="col">Total Bill</th>
                <th scope="col">Paid Amount</th>
                <th scope="col">Pending Balance</th>
                <th scope="col">Promised Due Date</th>
                <th scope="col" style="text-align:right;">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($pendingDuesList)): ?>
                <tr>
                  <td colspan="8" class="table-empty-cell">
                    <div class="empty-state-box">
                      <div class="empty-state-icon" aria-hidden="true">🎉</div>
                      <h4 class="empty-state-title text-success">Zero Outstanding Balance!</h4>
                      <p class="empty-state-text">All accounts and sales invoices are completely paid and clear.</p>
                    </div>
                  </td>
                </tr>
              <?php else: ?>
                <?php foreach ($pendingDuesList as $pd): ?>
                  <?php
                    $daysDue = $pd['days_until_due'];
                    $dueFmt = $pd['due_date'] ? date('d M Y', strtotime($pd['due_date'])) : 'Not Specified';
                    
                    $cleanPhone = preg_replace('/[^0-9]/', '', $pd['member_phone']);
                    if (strlen($cleanPhone) === 10) $cleanPhone = '91' . $cleanPhone;

                    $dueWaText = "🚨 Payment Reminder: Hi {$pd['member_name']}, you have a pending balance of {$currency}" . number_format($pd['due_amount'], 2) . " at THE CLUB 777® for invoice #{$pd['invoice_no']}." . ($pd['due_date'] ? " Promised date was {$dueFmt}." : "") . " Kindly clear your outstanding amount at the front desk or via UPI to keep your membership active!";
                    $dueWaUrl = "https://wa.me/{$cleanPhone}?text=" . urlencode($dueWaText);
                  ?>
                  <tr>
                    <td data-label="Member Name">
                      <div class="member-identity-col">
                        <div class="member-avatar-chip chip-danger" aria-hidden="true">
                          <?= strtoupper(substr($pd['member_name'], 0, 1)) ?>
                        </div>
                        <div>
                          <strong class="member-full-name"><?= htmlspecialchars($pd['member_name']) ?></strong>
                          <div class="member-code-tag"><?= htmlspecialchars($pd['member_code']) ?></div>
                        </div>
                      </div>
                    </td>
                    <td data-label="Phone">
                      <span class="phone-link-text"><?= htmlspecialchars($pd['member_phone']) ?></span>
                    </td>
                    <td data-label="Invoice #">
                      <span class="mono-code-text"><?= htmlspecialchars($pd['invoice_no']) ?></span>
                    </td>
                    <td data-label="Total Bill">
                      <span><?= $currency ?><?= number_format($pd['total'], 2) ?></span>
                    </td>
                    <td data-label="Paid Amount">
                      <span class="text-secondary"><?= $currency ?><?= number_format($pd['paid_amount'], 2) ?></span>
                    </td>
                    <td data-label="Pending Balance">
                      <strong class="text-danger font-bold text-base"><?= $currency ?><?= number_format($pd['due_amount'], 2) ?></strong>
                    </td>
                    <td data-label="Promised Due Date">
                      <?php if ($pd['due_date']): ?>
                        <div class="due-date-val <?= ($daysDue < 0) ? 'text-danger font-bold' : (($daysDue <= 2) ? 'text-warning font-bold' : 'text-primary font-semibold') ?>">
                          <?= $dueFmt ?>
                        </div>
                        <div class="due-date-sub">
                          <?= ($daysDue < 0) ? abs($daysDue) . ' days OVERDUE!' : (($daysDue == 0) ? 'Due TODAY!' : "In {$daysDue} days") ?>
                        </div>
                      <?php else: ?>
                        <span class="text-muted text-sm">No Date Set</span>
                      <?php endif; ?>
                    </td>
                    <td data-label="Actions" style="text-align:right;">
                      <div class="action-btn-cluster justify-end">
                        <a href="<?= $dueWaUrl ?>" target="_blank" class="btn btn-sm btn-whatsapp" title="Send WhatsApp Due Reminder">
                          <span>💬 WhatsApp Due</span>
                        </a>
                        <a href="index.php?page=pos&member_id=<?= $pd['member_id'] ?>" class="btn btn-primary btn-sm" title="Open POS to clear pending balance">
                          <span>💳 Clear Due</span>
                        </a>
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

    <!-- ========================================================= -->
    <!-- TAB 4: ACTIVE TRIAL PASSES & PROSPECTS                    -->
    <!-- ========================================================= -->
    <div id="dashTab_trials" class="dash-tab-pane" role="tabpanel" aria-labelledby="btnTabTrials" style="display:none;">
      <div class="card dash-table-card border-top-primary">
        <div class="card-header table-card-header">
          <div>
            <h3 class="card-title text-primary font-bold">
              <span aria-hidden="true">🏋️‍♂️</span> <span>Active Trial Pass Holders &amp; Conversion Pipeline</span>
            </h3>
            <span class="card-subtitle">Members on trial passes (1-15 days). Follow up proactively to convert them into long-term plans!</span>
          </div>
          <a href="index.php?page=marketing" class="btn btn-primary btn-sm">📢 Blast Trial Conversion Offer</a>
        </div>

        <div class="table-responsive">
          <table class="table table-modern" style="width:100%;">
            <thead>
              <tr>
                <th scope="col">Member Name</th>
                <th scope="col">Phone</th>
                <th scope="col">Trial Pass Type</th>
                <th scope="col">Started On</th>
                <th scope="col">Valid Until</th>
                <th scope="col">Days Left</th>
                <th scope="col" style="text-align:right;">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($trialMembers)): ?>
                <tr>
                  <td colspan="7" class="table-empty-cell">
                    <div class="empty-state-box">
                      <div class="empty-state-icon" aria-hidden="true">🏋️‍♂️</div>
                      <h4 class="empty-state-title">No Active Trials</h4>
                      <p class="empty-state-text">No trial passes active right now. New trial signups will appear here automatically.</p>
                    </div>
                  </td>
                </tr>
              <?php else: ?>
                <?php foreach ($trialMembers as $tm): ?>
                  <?php
                    $cleanPhone = preg_replace('/[^0-9]/', '', $tm['phone']);
                    if (strlen($cleanPhone) === 10) $cleanPhone = '91' . $cleanPhone;
                    $trialWaMsg = "🔥 Hello {$tm['member_name']}! Welcome to THE CLUB 777®! 🎉 Hope you enjoyed your workout session today!\n\n🎁 SPECIAL TRIAL CONVERSION OFFER:\nUpgrade to our Regular 3-Month, 6-Month or Annual Plan within 48 hours & get:\n✅ 100% Admission Fee Waived\n✅ 1 FREE Personal Training Session\n✅ FREE Customized Nutrition Chart\n\nVisit front desk or call {$gymPhone} to claim!";
                    $trialWaUrl = "https://wa.me/{$cleanPhone}?text=" . urlencode($trialWaMsg);
                  ?>
                  <tr>
                    <td data-label="Member Name">
                      <div class="member-identity-col">
                        <div class="member-avatar-chip chip-info" aria-hidden="true">
                          <?= strtoupper(substr($tm['member_name'], 0, 1)) ?>
                        </div>
                        <div>
                          <strong class="member-full-name"><?= htmlspecialchars($tm['member_name']) ?></strong>
                          <div class="member-code-tag"><?= htmlspecialchars($tm['member_code']) ?></div>
                        </div>
                      </div>
                    </td>
                    <td data-label="Phone">
                      <span class="phone-link-text"><?= htmlspecialchars($tm['phone']) ?></span>
                    </td>
                    <td data-label="Trial Pass Type">
                      <span class="badge badge-primary font-bold">
                        <?= htmlspecialchars($tm['plan_title']) ?> (<?= $tm['duration_days'] ?>d)
                      </span>
                    </td>
                    <td data-label="Started On">
                      <span class="text-secondary"><?= date('d M Y', strtotime($tm['start_date'])) ?></span>
                    </td>
                    <td data-label="Valid Until">
                      <strong class="date-highlight-text"><?= date('d M Y', strtotime($tm['end_date'])) ?></strong>
                    </td>
                    <td data-label="Days Left">
                      <span class="badge badge-<?= ($tm['days_left'] <= 2) ? 'danger' : 'info' ?> font-bold">
                        <?= max(0, $tm['days_left']) ?> Days Left
                      </span>
                    </td>
                    <td data-label="Actions" style="text-align:right;">
                      <div class="action-btn-cluster justify-end">
                        <a href="<?= $trialWaUrl ?>" target="_blank" class="btn btn-sm btn-whatsapp" title="Send 48-Hour Conversion Offer on WhatsApp">
                          <span>🎁 48h Offer</span>
                        </a>
                        <a href="index.php?page=pos&member_id=<?= $tm['member_id'] ?>" class="btn btn-primary btn-sm" title="Upgrade Trial to Regular Plan">
                          <span>🔄 Upgrade Plan</span>
                        </a>
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

    <!-- ========================================================= -->
    <!-- TAB 5: SWIMMING POOL & PERSONAL TRAINING (PT)             -->
    <!-- ========================================================= -->
    <div id="dashTab_pool_pt" class="dash-tab-pane" role="tabpanel" aria-labelledby="btnTabPoolPt" style="display:none;">
      <div class="dash-dual-grid">
        <!-- Swimming Pool Active Swimmers -->
        <div class="card dash-subhub-card border-top-cyan">
          <div class="card-header table-card-header">
            <div>
              <h3 class="card-title text-info font-bold">
                <span aria-hidden="true">🏊</span> <span>Swimming Pool Passes (<?= count($activePoolSwimmers) ?>)</span>
              </h3>
              <span class="card-subtitle">Active pool memberships &amp; assigned time slots</span>
            </div>
            <a href="index.php?page=pool" class="btn btn-outline btn-sm">Open Pool Hub</a>
          </div>

          <div class="hub-item-list">
            <?php if (empty($activePoolSwimmers)): ?>
              <div class="hub-empty-state">No active pool subscribers currently.</div>
            <?php else: ?>
              <?php foreach ($activePoolSwimmers as $ps): ?>
                <div class="hub-member-item">
                  <div class="hub-member-info">
                    <strong class="hub-member-name"><?= htmlspecialchars($ps['member_name']) ?></strong>
                    <div class="hub-plan-tag text-info font-semibold"><?= htmlspecialchars($ps['plan_title']) ?></div>
                    <div class="hub-meta-text">Slot: <?= htmlspecialchars($ps['slot_timing'] ?: ($ps['slot_assigned'] ?? 'All Day')) ?></div>
                  </div>
                  <div class="hub-member-status">
                    <span class="badge badge-success font-bold"><?= max(0, $ps['days_left']) ?> Days Left</span>
                    <div class="hub-date-text">Ends: <?= date('d M Y', strtotime($ps['end_date'])) ?></div>
                  </div>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>

        <!-- Personal Training Active Clients -->
        <div class="card dash-subhub-card border-top-purple">
          <div class="card-header table-card-header">
            <div>
              <h3 class="card-title text-purple font-bold">
                <span aria-hidden="true">💪</span> <span>Personal Training Clients (<?= count($activePtClients) ?>)</span>
              </h3>
              <span class="card-subtitle">Active 1-on-1 PT coaching packages &amp; sessions</span>
            </div>
            <a href="index.php?page=trainers" class="btn btn-outline btn-sm">Open PT Hub</a>
          </div>

          <div class="hub-item-list">
            <?php if (empty($activePtClients)): ?>
              <div class="hub-empty-state">No active personal training clients currently.</div>
            <?php else: ?>
              <?php foreach ($activePtClients as $pt): ?>
                <div class="hub-member-item">
                  <div class="hub-member-info">
                    <strong class="hub-member-name"><?= htmlspecialchars($pt['member_name']) ?></strong>
                    <div class="hub-plan-tag text-purple font-semibold"><?= htmlspecialchars($pt['package_title']) ?></div>
                    <div class="hub-meta-text">Trainer: <strong><?= htmlspecialchars($pt['trainer_name']) ?></strong></div>
                  </div>
                  <div class="hub-member-status">
                    <span class="badge badge-purple-subtle font-bold">
                      <?= $pt['sessions_total'] > 0 ? "{$pt['sessions_used']}/{$pt['sessions_total']} Sessions" : 'Unlimited' ?>
                    </span>
                  </div>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>

    <!-- ========================================================= -->
    <!-- TAB 6: STAFF SHIFTS & BIOMETRIC ATTENDANCE                -->
    <!-- ========================================================= -->
    <div id="dashTab_staff" class="dash-tab-pane" role="tabpanel" aria-labelledby="btnTabStaff" style="display:none;">
      <div class="card dash-table-card border-top-emerald">
        <div class="card-header table-card-header">
          <div>
            <h3 class="card-title font-bold">
              <span aria-hidden="true">⏱️</span> <span>Today's Staff Shift Attendance &amp; Late Evaluation</span>
            </h3>
            <span class="card-subtitle">
              Morning Shift: <strong>05:00 AM - 09:00 AM</strong> &bull; Evening Shift: <strong>05:00 PM - 09:00 PM</strong> (15 Mins Grace)
            </span>
          </div>
          <a href="index.php?page=staff" class="btn btn-primary btn-sm">Staff Attendance Hub</a>
        </div>

        <div class="table-responsive">
          <table class="table table-modern" style="width:100%;">
            <thead>
              <tr>
                <th scope="col">Staff Member</th>
                <th scope="col">Role</th>
                <th scope="col">Assigned Shift</th>
                <th scope="col">Check-in Time</th>
                <th scope="col">Status</th>
                <th scope="col">Check-out Time</th>
                <th scope="col" style="text-align:right;">Total Hours</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($todayStaffLogs)): ?>
                <tr>
                  <td colspan="7" class="table-empty-cell">
                    <div class="empty-state-box">
                      <div class="empty-state-icon" aria-hidden="true">⏱️</div>
                      <h4 class="empty-state-title">No Staff Punches Yet</h4>
                      <p class="empty-state-text">No staff attendance logged today yet. Automatic punches will appear here.</p>
                    </div>
                  </td>
                </tr>
              <?php else: ?>
                <?php foreach ($todayStaffLogs as $sl): ?>
                  <tr>
                    <td data-label="Staff Member">
                      <div class="member-identity-col">
                        <div class="member-avatar-chip chip-neutral" aria-hidden="true">
                          <?= strtoupper(substr($sl['staff_name'], 0, 1)) ?>
                        </div>
                        <div>
                          <strong class="member-full-name"><?= htmlspecialchars($sl['staff_name']) ?></strong>
                          <div class="member-sub-text"><?= htmlspecialchars($sl['phone']) ?></div>
                        </div>
                      </div>
                    </td>
                    <td data-label="Role">
                      <span class="badge badge-secondary font-semibold"><?= htmlspecialchars($sl['role']) ?></span>
                    </td>
                    <td data-label="Assigned Shift">
                      <span class="badge badge-info uppercase font-semibold">
                        <?= strtoupper($sl['shift_name'] ?: 'General Shift') ?>
                      </span>
                    </td>
                    <td data-label="Check-in Time">
                      <strong class="date-highlight-text"><?= $sl['check_in_time'] ? date('h:i A', strtotime($sl['check_in_time'])) : 'N/A' ?></strong>
                    </td>
                    <td data-label="Status">
                      <span class="badge badge-<?= $sl['status'] === 'on_time' ? 'success' : (($sl['status'] === 'late') ? 'danger' : 'warning') ?> font-bold uppercase">
                        <?= ($sl['status'] === 'late' && $sl['late_minutes'] > 0) ? "⚠️ LATE BY {$sl['late_minutes']} MINS" : strtoupper(str_replace('_', ' ', $sl['status'])) ?>
                      </span>
                    </td>
                    <td data-label="Check-out Time">
                      <?= $sl['check_out_time'] ? date('h:i A', strtotime($sl['check_out_time'])) : '<span class="status-badge-live">🟢 On Duty Now</span>' ?>
                    </td>
                    <td data-label="Total Hours" style="text-align:right;">
                      <strong class="font-bold"><?= number_format($sl['working_hours'], 1) ?> hrs</strong>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

  </section>

</div>

<!-- ========================================================= -->
<!-- MODERN SAAS DASHBOARD STYLESHEET                          -->
<!-- Clean, light theme, high-contrast, fully responsive      -->
<!-- ========================================================= -->
<style>
/* Layout Root & Base Tokens */
.dash-container {
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
  padding-bottom: 3rem;
}

/* 1. Hero Command Banner */
.dash-hero-banner {
  background: #0F172A;
  background: linear-gradient(135deg, #1E293B 0%, #0F172A 100%);
  border-radius: var(--radius-xl);
  padding: 1.35rem 1.65rem;
  color: #FFFFFF;
  border: 1px solid rgba(255, 255, 255, 0.08);
  box-shadow: var(--shadow-card);
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 1.25rem;
}

.dash-hero-main {
  flex: 1 1 340px;
}

.dash-hero-tag {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.72rem;
  font-weight: 800;
  color: #38BDF8;
  text-transform: uppercase;
  letter-spacing: 0.08em;
  margin-bottom: 0.35rem;
}

.dash-live-dot {
  width: 8px;
  height: 8px;
  background-color: #38BDF8;
  border-radius: 50%;
  box-shadow: 0 0 8px #38BDF8;
  animation: pulseDot 2s infinite ease-in-out;
}

.dash-hero-title {
  font-size: 1.5rem;
  font-weight: 800;
  color: #FFFFFF;
  margin: 0;
  letter-spacing: -0.025em;
  display: flex;
  align-items: center;
  gap: 0.45rem;
}

.dash-hero-icon {
  font-size: 1.35rem;
}

.dash-hero-sub {
  font-size: 0.82rem;
  color: #94A3B8;
  margin: 0.35rem 0 0 0;
  line-height: 1.4;
}

.dash-hero-meta {
  display: flex;
  align-items: center;
  gap: 0.85rem;
  flex-wrap: wrap;
}

.dash-clock-pill {
  background: rgba(255, 255, 255, 0.06);
  border: 1px solid rgba(255, 255, 255, 0.12);
  border-radius: 12px;
  padding: 0.5rem 0.95rem;
  text-align: right;
  min-width: 140px;
}

.dash-clock-label {
  font-size: 0.68rem;
  color: #94A3B8;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  display: block;
}

.dash-clock-val {
  font-family: var(--font-mono);
  font-size: 1.05rem;
  font-weight: 800;
  color: #38BDF8;
  margin-top: 0.1rem;
}

.dash-hero-stats {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
}

.dash-meta-chip {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  padding: 0.22rem 0.65rem;
  border-radius: 99px;
  font-size: 0.72rem;
  font-weight: 600;
  background: rgba(255, 255, 255, 0.08);
  border: 1px solid rgba(255, 255, 255, 0.15);
  color: #F8FAFC;
  white-space: nowrap;
}

.chip-dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
}
.chip-success .chip-dot { background: #10B981; }
.chip-danger .chip-dot { background: #EF4444; }
.chip-info .chip-dot { background: #38BDF8; }

/* 2. Quick Operations Launchpad */
.dash-section-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 0.75rem;
  flex-wrap: wrap;
  gap: 0.5rem;
}

.dash-section-title {
  font-size: 0.78rem;
  font-weight: 800;
  color: var(--text-muted);
  text-transform: uppercase;
  letter-spacing: 0.06em;
  display: flex;
  align-items: center;
  gap: 0.4rem;
  margin: 0;
}

.dash-shortcuts-tip {
  font-size: 0.74rem;
  color: var(--text-muted);
}

.dash-shortcuts-tip kbd {
  background: var(--bg-surface-secondary);
  border: 1px solid var(--border-color);
  border-radius: 4px;
  padding: 0.1rem 0.35rem;
  font-family: var(--font-mono);
  font-size: 0.7rem;
  font-weight: 700;
  color: var(--text-secondary);
}

.dash-quick-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
  gap: 0.75rem;
}

.dash-quick-card {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  text-align: center;
  padding: 0.95rem 0.65rem;
  border-radius: var(--radius-lg);
  color: #FFFFFF;
  text-decoration: none;
  border: none;
  cursor: pointer;
  box-shadow: var(--shadow-subtle);
  transition: transform 0.18s cubic-bezier(0.16, 1, 0.3, 1), box-shadow 0.18s ease;
  user-select: none;
  position: relative;
}

.dash-quick-card:hover {
  transform: translateY(-2px);
  box-shadow: var(--shadow-float);
  color: #FFFFFF !important;
}

.dash-quick-card:active {
  transform: scale(0.98);
}

.qk-icon-wrapper {
  font-size: 1.45rem;
  margin-bottom: 0.35rem;
  line-height: 1;
}

.qk-title {
  display: block;
  font-weight: 800;
  font-size: 0.84rem;
  line-height: 1.2;
}

.qk-sub {
  display: block;
  font-size: 0.68rem;
  opacity: 0.9;
  margin-top: 0.2rem;
}

.qk-sub kbd {
  background: rgba(255, 255, 255, 0.25);
  border-radius: 3px;
  padding: 0.05rem 0.3rem;
  font-family: var(--font-mono);
  font-size: 0.65rem;
}

/* Launchpad Color Presets */
.qk-blue    { background: linear-gradient(135deg, #2563EB 0%, #1D4ED8 100%); }
.qk-emerald { background: linear-gradient(135deg, #059669 0%, #047857 100%); }
.qk-purple  { background: linear-gradient(135deg, #7C3AED 0%, #6D28D9 100%); }
.qk-rose    { background: linear-gradient(135deg, #DC2626 0%, #B91C1C 100%); }
.qk-cyan    { background: linear-gradient(135deg, #0284C7 0%, #0369A1 100%); }
.qk-amber   { background: linear-gradient(135deg, #D97706 0%, #B45309 100%); }
.qk-teal    { background: linear-gradient(135deg, #0D9488 0%, #0F766E 100%); }
.qk-indigo  { background: linear-gradient(135deg, #4F46E5 0%, #4338CA 100%); }

/* 3. Birthday Banner */
.dash-bday-banner {
  background: linear-gradient(135deg, #FFFBEB 0%, #FEF3C7 100%);
  border: 1px solid #FCD34D;
  border-radius: var(--radius-lg);
  padding: 1rem 1.25rem;
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 1rem;
  box-shadow: var(--shadow-subtle);
}

.bday-banner-content {
  display: flex;
  align-items: center;
  gap: 0.85rem;
}

.bday-icon-circle {
  font-size: 1.5rem;
  background: #FEF08A;
  border: 1px solid #FDE047;
  width: 44px;
  height: 44px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.bday-title {
  font-size: 1rem;
  font-weight: 800;
  color: #92400E;
  margin: 0;
}

.bday-desc {
  font-size: 0.8rem;
  color: #B45309;
  margin: 0.15rem 0 0 0;
}

.bday-action-group {
  display: flex;
  gap: 0.5rem;
  flex-wrap: wrap;
}

.bday-btn {
  font-weight: 800;
  text-decoration: none;
  box-shadow: 0 2px 6px rgba(245, 158, 11, 0.25);
}

/* 4. KPI Cards Grid System */
.dash-kpi-container {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 1.1rem;
}

.kpi-box {
  background: var(--bg-surface);
  border: 1px solid var(--border-color);
  border-radius: var(--radius-lg);
  padding: 1.15rem 1.25rem;
  box-shadow: var(--shadow-card);
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  min-height: 138px;
  transition: transform 0.2s cubic-bezier(0.16, 1, 0.3, 1), box-shadow 0.2s ease;
  position: relative;
  overflow: hidden;
}

.kpi-box:hover {
  transform: translateY(-2px);
  box-shadow: var(--shadow-float);
}

.kpi-theme-primary { border-left: 4px solid var(--primary); }
.kpi-theme-success { border-left: 4px solid var(--success); }
.kpi-theme-danger  { border-left: 4px solid var(--danger); }
.kpi-theme-warning { border-left: 4px solid var(--warning); }
.kpi-theme-purple  { border-left: 4px solid var(--purple); }
.kpi-theme-info    { border-left: 4px solid var(--info); }

.kpi-box-top {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 0.5rem;
  margin-bottom: 0.65rem;
}

.kpi-label {
  font-size: 0.72rem;
  font-weight: 800;
  color: var(--text-muted);
  text-transform: uppercase;
  letter-spacing: 0.05em;
  display: block;
  margin-bottom: 0.3rem;
}

.kpi-value-row {
  display: flex;
  align-items: baseline;
  gap: 0.45rem;
  flex-wrap: wrap;
}

.kpi-number {
  font-size: 1.7rem;
  font-weight: 800;
  font-family: var(--font-heading);
  letter-spacing: -0.03em;
  line-height: 1;
}

.kpi-total-sub {
  font-size: 0.85rem;
  color: var(--text-muted);
  font-weight: 600;
}

.kpi-badge {
  padding: 0.22rem 0.55rem;
  border-radius: 99px;
  font-size: 0.7rem;
  font-weight: 800;
  white-space: nowrap;
  letter-spacing: 0.02em;
}

.badge-primary-subtle { background: #EFF6FF; color: #1E40AF; border: 1px solid #BFDBFE; }
.badge-success-subtle { background: #ECFDF5; color: #065F46; border: 1px solid #A7F3D0; }
.badge-danger-subtle  { background: #FEF2F2; color: #991B1B; border: 1px solid #FECACA; }
.badge-warning-subtle { background: #FFFBEB; color: #92400E; border: 1px solid #FDE68A; }
.badge-purple-subtle  { background: #F5F3FF; color: #6D28D9; border: 1px solid #DDD6FE; }
.badge-info-subtle    { background: #F0F9FF; color: #0369A1; border: 1px solid #BAE6FD; }

.kpi-box-bottom {
  padding-top: 0.6rem;
  border-top: 1px solid var(--border-light);
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 0.76rem;
  font-weight: 600;
  color: var(--text-secondary);
}

.kpi-stat-item {
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
}

.kpi-indicator {
  width: 7px;
  height: 7px;
  border-radius: 50%;
}
.dot-success { background: #10B981; }
.dot-danger  { background: #EF4444; }

.kpi-progress-col {
  flex-direction: column;
  align-items: stretch;
  gap: 0.35rem;
}

.dash-progress-track {
  width: 100%;
  height: 6px;
  background: var(--bg-surface-secondary);
  border-radius: 99px;
  overflow: hidden;
}

.dash-progress-fill {
  height: 100%;
  background: linear-gradient(90deg, #8B5CF6 0%, #6366F1 100%);
  border-radius: 99px;
  transition: width 0.4s ease;
}

.kpi-progress-meta {
  display: flex;
  justify-content: space-between;
  font-size: 0.7rem;
  color: var(--text-muted);
  font-weight: 600;
}

/* 5. Visual Analytics Section */
.dash-analytics-grid {
  display: grid;
  grid-template-columns: 2fr 1fr;
  gap: 1.25rem;
}

.chart-card {
  display: flex;
  flex-direction: column;
}

.chart-card-header {
  margin-bottom: 0.85rem;
}

.chart-header-left .card-title {
  margin: 0;
  font-size: 1.02rem;
}

.chart-header-left .card-subtitle {
  font-size: 0.75rem;
  color: var(--text-muted);
  margin-top: 0.15rem;
  display: block;
}

.chart-canvas-wrapper {
  position: relative;
  height: 250px;
  width: 100%;
}

/* 6. Tabs & Operational Center */
.dash-tab-nav {
  display: flex;
  gap: 0.45rem;
  border-bottom: 2px solid var(--border-color);
  margin-bottom: 1.25rem;
  overflow-x: auto;
  scrollbar-width: none;
  -webkit-overflow-scrolling: touch;
  padding-bottom: 1px;
}

.dash-tab-nav::-webkit-scrollbar {
  display: none;
}

.dash-tab-btn {
  background: var(--bg-surface);
  color: var(--text-secondary);
  border: 1px solid var(--border-color);
  border-bottom: none;
  padding: 0.65rem 1.1rem;
  font-size: 0.82rem;
  font-weight: 700;
  cursor: pointer;
  border-radius: 10px 10px 0 0;
  transition: all var(--transition-fast);
  white-space: nowrap;
  display: inline-flex;
  align-items: center;
  gap: 0.45rem;
  font-family: inherit;
}

.dash-tab-btn:hover {
  background: var(--bg-surface-secondary);
  color: var(--text-primary);
}

.dash-tab-btn.active {
  background: var(--primary);
  color: #FFFFFF;
  border-color: var(--primary);
  box-shadow: 0 -2px 10px rgba(37, 99, 235, 0.25);
}

.tab-badge {
  padding: 0.15rem 0.45rem;
  border-radius: 99px;
  font-size: 0.68rem;
  font-weight: 800;
}

.dash-tab-btn:not(.active) .badge-warning-count   { background: #FEF3C7; color: #92400E; }
.dash-tab-btn:not(.active) .badge-success-count   { background: #D1FAE5; color: #065F46; }
.dash-tab-btn:not(.active) .badge-danger-count    { background: #FEE2E2; color: #991B1B; }
.dash-tab-btn:not(.active) .badge-info-count      { background: #E0F2FE; color: #075985; }
.dash-tab-btn:not(.active) .badge-purple-count    { background: #EDE9FE; color: #5B21B6; }
.dash-tab-btn:not(.active) .badge-secondary-count { background: #F1F5F9; color: #475569; }

.dash-tab-btn.active .tab-badge {
  background: rgba(255, 255, 255, 0.25);
  color: #FFFFFF;
}

/* Expiry Sub-Filters */
.dash-filter-bar {
  display: flex;
  gap: 0.5rem;
  margin-bottom: 1rem;
  flex-wrap: wrap;
}

.exp-filter-btn {
  font-weight: 700;
  font-size: 0.78rem;
  padding: 0.4rem 0.8rem;
  border-radius: var(--radius-md);
  transition: all var(--transition-fast);
}

.exp-filter-btn.active {
  background: var(--text-primary) !important;
  color: #FFFFFF !important;
  border-color: var(--text-primary) !important;
  box-shadow: var(--shadow-subtle);
}

.filter-btn-info    { background: #EFF6FF; color: #1E40AF; border: 1px solid #BFDBFE; }
.filter-btn-warning { background: #FFFBEB; color: #B45309; border: 1px solid #FCD34D; }
.filter-btn-danger  { background: #FEF2F2; color: #B91C1C; border: 1px solid #FECACA; }
.filter-btn-urgent  { background: #991B1B; color: #FFFFFF; border: 1px solid #991B1B; }
.filter-btn-purple  { background: #F5F3FF; color: #6D28D9; border: 1px solid #DDD6FE; }

/* Modern Table Enhancements */
.dash-table-card {
  padding: 0;
  overflow: hidden;
}

.table-card-header {
  padding: 1.15rem 1.35rem;
  margin-bottom: 0;
  border-bottom: 1px solid var(--border-color);
  background: var(--bg-surface);
  flex-wrap: wrap;
  gap: 0.5rem;
}

.table-card-header .card-subtitle {
  font-size: 0.78rem;
  color: var(--text-muted);
  margin-top: 0.15rem;
  display: block;
}

.table-modern {
  margin: 0;
}

.table-modern thead th {
  background: var(--bg-surface-secondary);
  color: var(--text-muted);
  font-size: 0.72rem;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  padding: 0.85rem 1.15rem;
  border-bottom: 1px solid var(--border-color);
  white-space: nowrap;
}

.table-modern tbody td {
  padding: 0.85rem 1.15rem;
  vertical-align: middle;
  border-bottom: 1px solid var(--border-light);
  background: var(--bg-surface);
}

.table-modern tbody tr:last-child td {
  border-bottom: none;
}

.table-modern tbody tr:hover td {
  background: #F8FAFC;
}

/* Identity & Text Formatting inside Tables */
.member-identity-col {
  display: flex;
  align-items: center;
  gap: 0.65rem;
}

.member-avatar-chip {
  width: 32px;
  height: 32px;
  border-radius: 50%;
  background: var(--primary-light);
  color: var(--primary);
  border: 1px solid var(--primary-border);
  font-weight: 800;
  font-size: 0.78rem;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.member-avatar-chip.chip-neutral { background: var(--bg-surface-secondary); color: var(--text-secondary); border-color: var(--border-color); }
.member-avatar-chip.chip-danger  { background: #FEE2E2; color: #DC2626; border-color: #FECACA; }
.member-avatar-chip.chip-info    { background: #E0F2FE; color: #0284C7; border-color: #BAE6FD; }

.member-full-name {
  font-weight: 800;
  color: var(--text-primary);
  font-size: 0.86rem;
  line-height: 1.2;
}

.member-code-tag {
  font-size: 0.72rem;
  color: var(--text-muted);
  font-family: var(--font-mono);
  margin-top: 0.1rem;
}

.member-sub-text {
  font-size: 0.72rem;
  color: var(--text-muted);
  margin-top: 0.1rem;
}

.phone-link-text {
  font-weight: 600;
  color: var(--text-secondary);
}

.date-highlight-text {
  font-weight: 700;
  color: var(--text-primary);
}

.mono-code-text {
  font-family: var(--font-mono);
  font-weight: 700;
  color: var(--primary);
}

.time-stamp-text {
  font-size: 0.78rem;
  color: var(--text-muted);
  font-weight: 600;
}

.status-badge-live {
  color: #059669;
  font-weight: 700;
  font-size: 0.78rem;
}

.action-btn-cluster {
  display: flex;
  gap: 0.4rem;
  align-items: center;
  flex-wrap: wrap;
}

.justify-end {
  justify-content: flex-end;
}

.btn-whatsapp {
  background: #25D366;
  color: #FFFFFF;
  font-weight: 800;
  border: none;
  text-decoration: none;
  box-shadow: 0 2px 6px rgba(37, 211, 102, 0.3);
}

.btn-whatsapp:hover {
  background: #1EBE5D;
  color: #FFFFFF;
}

/* Empty State Styling */
.table-empty-cell {
  text-align: center;
  padding: 3rem 1.5rem !important;
}

.empty-state-box {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
}

.empty-state-icon {
  font-size: 2rem;
  margin-bottom: 0.5rem;
}

.empty-state-title {
  font-size: 1rem;
  font-weight: 800;
  color: var(--text-primary);
  margin: 0;
}

.empty-state-text {
  font-size: 0.82rem;
  color: var(--text-muted);
  margin: 0.25rem 0 0 0;
  max-width: 380px;
}

/* Mini Revenue Stream Summary Rows */
.dash-summary-row {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
  gap: 0.85rem;
  margin-bottom: 1rem;
}

.summary-card {
  padding: 1rem 1.15rem;
}

.summary-label {
  font-size: 0.7rem;
  font-weight: 800;
  color: var(--text-muted);
  text-transform: uppercase;
  letter-spacing: 0.05em;
  display: block;
}

.summary-val {
  font-size: 1.4rem;
  font-weight: 800;
  font-family: var(--font-heading);
  letter-spacing: -0.02em;
  margin-top: 0.25rem;
}

.border-primary { border-left: 4px solid var(--primary); }
.border-success { border-left: 4px solid var(--success); }
.border-warning { border-left: 4px solid var(--warning); }
.border-danger  { border-left: 4px solid var(--danger); }
.border-purple  { border-left: 4px solid var(--purple); }

.border-top-primary { border-top: 4px solid var(--primary); }
.border-top-danger  { border-top: 4px solid var(--danger); }
.border-top-cyan    { border-top: 4px solid #0284C7; }
.border-top-purple  { border-top: 4px solid #8B5CF6; }
.border-top-emerald { border-top: 4px solid #10B981; }

/* Sub-Hub Dual Grid (Pool & PT) */
.dash-dual-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
  gap: 1.25rem;
}

.dash-subhub-card {
  padding: 0;
  overflow: hidden;
}

.hub-item-list {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  padding: 1rem 1.25rem;
  max-height: 460px;
  overflow-y: auto;
}

.hub-member-item {
  padding: 0.75rem 0.95rem;
  background: var(--bg-main);
  border: 1px solid var(--border-color);
  border-radius: var(--radius-md);
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 0.75rem;
  transition: background var(--transition-fast);
}

.hub-member-item:hover {
  background: #F1F5F9;
}

.hub-member-name {
  color: var(--text-primary);
  font-size: 0.88rem;
  display: block;
}

.hub-plan-tag {
  font-size: 0.75rem;
  margin-top: 0.1rem;
}

.hub-meta-text {
  font-size: 0.72rem;
  color: var(--text-muted);
}

.hub-member-status {
  text-align: right;
  flex-shrink: 0;
}

.hub-date-text {
  font-size: 0.7rem;
  color: var(--text-muted);
  margin-top: 0.2rem;
}

.hub-empty-state {
  padding: 2.5rem 1rem;
  text-align: center;
  color: var(--text-muted);
  font-size: 0.85rem;
}

/* ========================================================= */
/* RESPONSIVE BREAKPOINTS (Desktop -> Tablet -> Mobile)      */
/* ========================================================= */
@media (max-width: 1100px) {
  .dash-kpi-container {
    grid-template-columns: repeat(2, 1fr);
  }
  .dash-analytics-grid {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 768px) {
  .dash-container {
    gap: 1.15rem;
  }

  .dash-hero-banner {
    padding: 1.15rem;
    flex-direction: column;
    align-items: flex-start;
  }

  .dash-hero-meta {
    width: 100%;
    justify-content: space-between;
  }

  .dash-clock-pill {
    text-align: left;
    min-width: unset;
  }

  .dash-kpi-container {
    grid-template-columns: 1fr;
    gap: 0.85rem;
  }

  .dash-quick-grid {
    grid-template-columns: repeat(2, 1fr);
  }

  .dash-tab-btn {
    padding: 0.55rem 0.85rem;
    font-size: 0.76rem;
  }

  /* Responsive Mobile Card View for Tables */
  .table-modern thead {
    display: none;
  }

  .table-modern, 
  .table-modern tbody, 
  .table-modern tr, 
  .table-modern td {
    display: block;
    width: 100%;
  }

  .table-modern tbody tr {
    margin-bottom: 0.85rem;
    background: var(--bg-surface);
    border: 1px solid var(--border-color);
    border-radius: var(--radius-md);
    padding: 0.75rem 0.9rem;
    box-shadow: var(--shadow-subtle);
  }

  .table-modern tbody td {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.45rem 0;
    border-bottom: 1px solid var(--border-light);
    font-size: 0.82rem;
  }

  .table-modern tbody td:last-child {
    border-bottom: none;
    padding-top: 0.65rem;
  }

  .table-modern tbody td::before {
    content: attr(data-label);
    font-weight: 700;
    font-size: 0.72rem;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--text-muted);
    flex-shrink: 0;
    margin-right: 0.75rem;
  }

  .table-modern tbody td[data-label="1-Click Actions"]::before,
  .table-modern tbody td[data-label="Action"]::before,
  .table-modern tbody td[data-label="Actions"]::before {
    display: none;
  }

  .table-modern tbody td .action-btn-cluster {
    width: 100%;
    justify-content: flex-start;
  }

  .table-modern tbody td .btn {
    flex: 1;
    text-align: center;
  }

  .table-empty-cell {
    display: block !important;
  }
  .table-empty-cell::before {
    display: none !important;
  }
}

@media (max-width: 480px) {
  .dash-hero-title {
    font-size: 1.25rem;
  }
  .dash-quick-grid {
    grid-template-columns: repeat(2, 1fr);
    gap: 0.55rem;
  }
  .dash-quick-card {
    padding: 0.75rem 0.5rem;
  }
  .qk-icon-wrapper {
    font-size: 1.25rem;
  }
  .qk-title {
    font-size: 0.78rem;
  }
  .dash-shortcuts-tip {
    display: none;
  }
}
</style>

<!-- ========================================================= -->
<!-- JAVASCRIPT LOGIC & CHART INITIALIZATION                   -->
<!-- ========================================================= -->
<script>
// Tab Switching Controller
function switchDashTab(tabKey, btnEl) {
  document.querySelectorAll('.dash-tab-pane').forEach(p => p.style.display = 'none');
  document.querySelectorAll('.dash-tab-btn').forEach(b => {
    b.classList.remove('active');
    b.setAttribute('aria-selected', 'false');
  });

  const targetPane = document.getElementById('dashTab_' + tabKey);
  if (targetPane) {
    targetPane.style.display = 'block';
  }
  if (btnEl) {
    btnEl.classList.add('active');
    btnEl.setAttribute('aria-selected', 'true');
  }
}

// Expiry Sub-Category Filtering
function filterExpiryCards(bracket, btnEl) {
  document.querySelectorAll('.exp-filter-btn').forEach(b => b.classList.remove('active'));
  if (btnEl) btnEl.classList.add('active');

  const rows = document.querySelectorAll('.exp-row');
  rows.forEach(r => {
    if (bracket === 'all' || r.getAttribute('data-bracket') === bracket) {
      r.style.display = '';
    } else {
      r.style.display = 'none';
    }
  });
}

// Live Digital Clock (IST)
function startLiveClock() {
  const clockEl = document.getElementById('liveClockDisplay');
  if (!clockEl) return;
  setInterval(() => {
    const now = new Date();
    clockEl.textContent = now.toLocaleTimeString('en-US', {
      hour: '2-digit',
      minute: '2-digit',
      second: '2-digit',
      hour12: true
    });
  }, 1000);
}

// Initialize Interactive Analytics Charts
document.addEventListener('DOMContentLoaded', function() {
  startLiveClock();

  // Global Chart.js defaults
  if (typeof Chart !== 'undefined') {
    Chart.defaults.font.family = "'Plus Jakarta Sans', -apple-system, sans-serif";
    Chart.defaults.color = '#64748B';
  }

  // 1. Revenue 7-Day Trend Chart
  const revCtx = document.getElementById('revenueChart');
  if (revCtx) {
    new Chart(revCtx, {
      type: 'line',
      data: {
        labels: <?= json_encode($chartDates) ?>,
        datasets: [
          {
            label: 'Total Revenue (₹)',
            data: <?= json_encode($chartRevenue) ?>,
            borderColor: '#2563EB',
            backgroundColor: 'rgba(37, 99, 235, 0.08)',
            fill: true,
            tension: 0.35,
            borderWidth: 2.5,
            pointRadius: 4,
            pointHoverRadius: 6,
            pointBackgroundColor: '#2563EB'
          },
          {
            label: 'UPI / QR (₹)',
            data: <?= json_encode($chartUpi) ?>,
            borderColor: '#10B981',
            backgroundColor: 'transparent',
            borderDash: [4, 4],
            tension: 0.35,
            borderWidth: 2,
            pointRadius: 3,
            pointHoverRadius: 5,
            pointBackgroundColor: '#10B981'
          }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: {
          mode: 'index',
          intersect: false
        },
        plugins: {
          legend: {
            position: 'top',
            align: 'end',
            labels: {
              boxWidth: 12,
              usePointStyle: true,
              pointStyle: 'circle',
              font: { size: 11, weight: '700' }
            }
          },
          tooltip: {
            backgroundColor: '#0F172A',
            titleFont: { size: 12, weight: '800' },
            bodyFont: { size: 11 },
            padding: 10,
            cornerRadius: 8,
            callbacks: {
              label: function(context) {
                return context.dataset.label + ': ₹' + Number(context.raw).toLocaleString('en-IN');
              }
            }
          }
        },
        scales: {
          x: {
            grid: { display: false },
            ticks: { font: { size: 10, weight: '600' } }
          },
          y: { 
            grid: { color: '#F1F5F9' },
            ticks: { 
              font: { size: 10 },
              callback: function(value) { return '₹' + Number(value).toLocaleString('en-IN'); }
            } 
          }
        }
      }
    });
  }

  // 2. Hourly Rush Hours Peak Footfall Chart
  const rushCtx = document.getElementById('rushChart');
  if (rushCtx) {
    new Chart(rushCtx, {
      type: 'bar',
      data: {
        labels: <?= json_encode($hourlyLabels) ?>,
        datasets: [{
          label: 'Punches / Check-ins',
          data: <?= json_encode($hourlyPunches) ?>,
          backgroundColor: function(context) {
            const val = context.raw || 0;
            return val >= 5 ? '#EF4444' : (val >= 2 ? '#F59E0B' : '#3B82F6');
          },
          borderRadius: 5,
          borderSkipped: false
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: false },
          tooltip: {
            backgroundColor: '#0F172A',
            titleFont: { size: 12, weight: '800' },
            bodyFont: { size: 11 },
            padding: 10,
            cornerRadius: 8,
            callbacks: {
              label: function(context) {
                return ' ' + context.raw + ' check-ins';
              }
            }
          }
        },
        scales: {
          x: {
            grid: { display: false },
            ticks: { font: { size: 9, weight: '600' }, maxRotation: 45 }
          },
          y: { 
            grid: { color: '#F1F5F9' },
            ticks: { stepSize: 1, font: { size: 10 } }
          }
        }
      }
    });
  }
});
</script>
