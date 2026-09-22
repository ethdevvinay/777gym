<!-- Commercial Member 360° Executive Profile View -->
<?php
$db = getDB();
$memberId = intval($_GET['id'] ?? 1001);

// Fetch Member Record
$stmt = $db->prepare("SELECT * FROM members WHERE id = ? OR member_code = ?");
$stmt->execute([$memberId, "M-{$memberId}"]);
$member = $stmt->fetch();

if (!$member) {
  $member = $db->query("SELECT * FROM members ORDER BY id ASC LIMIT 1")->fetch();
}

if (!$member) {
  $member = [
    'id' => 1001,
    'name' => 'Rahul Kumar',
    'member_code' => 'M-1001',
    'phone' => '+91 98765 00001',
    'email' => 'rahul.kumar@gmail.com',
    'dob' => '1998-05-15',
    'gender' => 'Male',
    'blood_group' => 'B+',
    'locker_no' => 'LKR-04',
    'biometric_id' => 'BIO-1001',
    'emergency_contact' => '+91 98765 99999',
    'parent_name' => 'Suresh Kumar',
    'address' => 'Plot 42, Civil Lines, Near City Center',
    'status' => 'active',
    'created_at' => date('Y-m-d H:i:s', strtotime('-60 days'))
  ];
}

$mId = $member['id'];

// Fetch All Members for Quick Switch Dropdown
$allMembersList = $db->query("SELECT id, name, member_code, phone FROM members ORDER BY name ASC LIMIT 100")->fetchAll();

// Fetch All Membership Types for Edit & Add Modals
$membershipTypes = $db->query("SELECT * FROM membership_types ORDER BY id ASC")->fetchAll();

// Subscriptions
$stmtSub = $db->prepare("
    SELECT s.*, mt.title as plan_title, mt.duration_days, mt.duration_months, mt.max_freeze_count, mt.access_timing, mt.included_services, mt.price as base_price
    FROM member_subscriptions s 
    JOIN membership_types mt ON s.membership_type_id = mt.id 
    WHERE s.member_id = ? 
    ORDER BY s.id DESC
");
$stmtSub->execute([$mId]);
$subscriptions = $stmtSub->fetchAll();

// Active & Queued Subscriptions Resolution
$activeSub = null;
$queuedSub = null;
$todayDate = date('Y-m-d');

// 1. Find the plan currently running today
foreach ($subscriptions as $s) {
    if (($s['status'] === 'active' || $s['status'] === 'frozen') && $s['start_date'] <= $todayDate && $s['end_date'] >= $todayDate) {
        $activeSub = $s;
        break;
    }
}

// 2. Find any upcoming queued plan that starts in the future
foreach ($subscriptions as $s) {
    if ($s['status'] === 'active' && $s['start_date'] > $todayDate) {
        $queuedSub = $s;
        break;
    }
}

// 3. Fallback if no plan is running today
if (!$activeSub) {
    foreach ($subscriptions as $s) {
        if ($s['status'] === 'active' || $s['status'] === 'frozen') {
            $activeSub = $s;
            break;
        }
    }
}

// Sales / Invoices & Pending Due Calculation
$stmtSales = $db->prepare("SELECT * FROM sales WHERE member_id = ? ORDER BY id DESC");
$stmtSales->execute([$mId]);
$sales = $stmtSales->fetchAll();

$totalSpent = 0;
$totalDue = 0;
$nearestDueDate = null;
foreach ($sales as $sale) {
  $totalSpent += floatval($sale['total']);
  if ($sale['payment_status'] === 'pending' || $sale['payment_status'] === 'partial' || $sale['payment_status'] === 'due') {
    $due = floatval($sale['due_amount'] > 0 ? $sale['due_amount'] : (floatval($sale['total']) - floatval($sale['paid_amount'])));
    if ($due > 0) {
      $totalDue += $due;
      if (!empty($sale['due_date'])) {
        if (!$nearestDueDate || strtotime($sale['due_date']) < strtotime($nearestDueDate)) {
          $nearestDueDate = $sale['due_date'];
        }
      }
    }
  }
}

// Attendance Stats
$stmtAtt = $db->prepare("SELECT * FROM attendance WHERE member_id = ? ORDER BY id DESC LIMIT 25");
$stmtAtt->execute([$mId]);
$attendanceLogs = $stmtAtt->fetchAll();

$stmtCount = $db->prepare("SELECT COUNT(*) FROM attendance WHERE member_id = ?");
$stmtCount->execute([$mId]);
$totalVisits = $stmtCount->fetchColumn();

// Personal Training (PT) Subscriptions for this Member
$stmtMemberPt = $db->prepare("
    SELECT ps.*, p.title as package_title, t.name as trainer_name, t.phone as trainer_phone
    FROM pt_subscriptions ps
    JOIN pt_packages p ON ps.pt_package_id = p.id
    LEFT JOIN trainers t ON ps.trainer_id = t.id
    WHERE ps.member_id = ?
    ORDER BY ps.id DESC
");
$stmtMemberPt->execute([$mId]);
$memberPtSubs = $stmtMemberPt->fetchAll();

// Swimming Pool Subscriptions for this Member
$stmtMemberPool = $db->prepare("
    SELECT ps.*, pp.title as plan_title, pp.slot_timing, pp.coach_name,
           DATEDIFF(ps.end_date, CURRENT_DATE()) as days_left
    FROM pool_subscriptions ps
    JOIN pool_plans pp ON ps.pool_plan_id = pp.id
    WHERE ps.member_id = ?
    ORDER BY ps.id DESC
");
$stmtMemberPool->execute([$mId]);
$memberPoolSubs = $stmtMemberPool->fetchAll();

$activePoolSub = null;
foreach ($memberPoolSubs as $pps) {
  if ($pps['status'] === 'active' && strtotime($pps['end_date']) >= strtotime('today')) {
    $activePoolSub = $pps;
    break;
  }
}

// Fetch all available pool plans for assignment modal
$allPoolPlans = $db->query("SELECT * FROM pool_plans WHERE status = 'active' ORDER BY duration_days ASC")->fetchAll();

// Body Measurements & Fitness
$bodyStmt = $db->prepare("SELECT * FROM body_measurements WHERE member_id = ? ORDER BY record_date DESC LIMIT 1");
$bodyStmt->execute([$mId]);
$body = $bodyStmt->fetch();

$fitStmt = $db->prepare("SELECT * FROM member_fitness WHERE member_id = ?");
$fitStmt->execute([$mId]);
$fitness = $fitStmt->fetch() ?: [
  'weight_kg' => 74.5,
  'height_cm' => 176,
  'bmi' => 24.1,
  'body_fat_pct' => 15.8,
  'workout_plan' => "Day 1: Chest & Triceps (Bench Press, Incline DB, Dips)\nDay 2: Back & Biceps (Deadlifts, Pull-ups, Rows)\nDay 3: Legs & Core (Squats, Leg Press, Planks)\nDay 4: Shoulders & Abs (Overhead Press, Lateral Raises)\nDay 5: HIIT Cardio & Stretching\nDay 6: Functional & Core Workout",
  'diet_plan' => "• Breakfast: 4 Boiled Eggs / Oats + Whey Protein Scoop\n• Lunch: Grilled Chicken / Paneer + Brown Rice + Green Veggies\n• Pre-Workout: Banana + Black Coffee / Peanut Butter Toast\n• Dinner: Stir-fried Fish / Tofu + Quinoa Salad\n• Water: 3.5 Liters Daily"
];

// Marketing & WhatsApp History for this Member
$waStmt = $db->prepare("SELECT * FROM marketing_logs WHERE member_id = ? ORDER BY id DESC LIMIT 15");
$waStmt->execute([$mId]);
$waLogs = $waStmt->fetchAll();

$currency = getSetting('currency_symbol', '₹');
$gymName = getSetting('gym_name', 'THE CLUB 777®');

// Calculate Age and Birthday Status
$isBirthdayToday = false;
$ageStr = 'N/A';
if (!empty($member['dob'])) {
  $dobTime = strtotime($member['dob']);
  $ageStr = date_diff(date_create($member['dob']), date_create('today'))->y . ' yrs';
  if (date('m-d', $dobTime) === date('m-d')) {
    $isBirthdayToday = true;
  }
}

// Calculate Plan Expiry & Progress
$daysRemaining = 0;
$planProgressPct = 0;
$expiryStatusText = 'No Active Plan';
$expiryBadgeClass = 'secondary';

if ($activeSub) {
  $startDateTs = strtotime($activeSub['start_date']);
  $endDateTs = strtotime($activeSub['end_date']);
  $todayTs = strtotime('today');

  $totalDays = max(1, ($endDateTs - $startDateTs) / 86400);
  $elapsedDays = max(0, ($todayTs - $startDateTs) / 86400);
  $daysRemaining = ceil(($endDateTs - $todayTs) / 86400);

  $planProgressPct = min(100, max(0, round(($elapsedDays / $totalDays) * 100)));

  if ($daysRemaining < 0) {
    $expiryStatusText = 'Expired ' . abs($daysRemaining) . ' days ago';
    $expiryBadgeClass = 'danger';
  } elseif ($daysRemaining === 0) {
    $expiryStatusText = 'Expires Today';
    $expiryBadgeClass = 'danger';
  } elseif ($daysRemaining <= 2) {
    $expiryStatusText = "{$daysRemaining} Days Left (Urgent)";
    $expiryBadgeClass = 'danger';
  } elseif ($daysRemaining <= 7) {
    $expiryStatusText = "{$daysRemaining} Days Left";
    $expiryBadgeClass = 'warning';
  } else {
    $expiryStatusText = "{$daysRemaining} Days Remaining";
    $expiryBadgeClass = 'success';
  }
}

$cleanPhone = preg_replace('/[^0-9]/', '', $member['phone']);
if (strlen($cleanPhone) === 10)
  $cleanPhone = '91' . $cleanPhone;
$directWaChatUrl = "https://wa.me/{$cleanPhone}";
?>

<div class="page-content">
  <!-- Top Utility & Quick Member Switcher Bar -->
  <div
    style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem; margin-bottom:1.5rem;">
    <div style="display:flex; align-items:center; gap:0.85rem; flex-wrap:wrap;">
      <a href="index.php?page=members" class="btn btn-outline"
        style="border-radius:var(--radius-md); font-weight:700; background:var(--bg-surface);">
        <span>← Members Directory</span>
      </a>

      <!-- Quick Switcher Dropdown -->
      <div style="display:flex; align-items:center; gap:0.5rem;">
        <span style="font-size:0.82rem; font-weight:700; color:var(--text-muted);">Quick Switch:</span>
        <select onchange="window.location.href='index.php?page=member_profile&id=' + this.value" class="form-control"
          style="font-size:0.82rem; min-width:220px; font-weight:700;">
          <?php foreach ($allMembersList as $am): ?>
            <option value="<?= $am['id'] ?>" <?= $am['id'] == $member['id'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($am['name']) ?> (<?= $am['member_code'] ?>)
            </option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <!-- Quick Action Buttons Header -->
    <div style="display:flex; gap:0.5rem; flex-wrap:wrap;">
      <button class="btn btn-secondary" onclick="openProfileEdit()">
        ✏️ Edit Profile
      </button>

      <?php if ($activeSub): ?>
        <button class="btn btn-primary" onclick="openEditPlan(<?= htmlspecialchars(json_encode($activeSub)) ?>)"
          style="background:#0284C7; border-color:#0284C7;">
          📝 Edit Plan &amp; Dates
        </button>
      <?php else: ?>
        <button class="btn btn-primary" onclick="openModal('addPlanModal')">
          + Assign Plan
        </button>
      <?php endif; ?>

      <?php if ($activeSub && $activeSub['status'] === 'active'): ?>
        <?php
        $months = intval($activeSub['duration_months'] ?: 1);
        $defLimit = ($months <= 1 ? 1 : ($months == 3 ? 2 : ($months == 6 ? 3 : 4)));
        $allowedLimit = intval($activeSub['max_freeze_count'] ?: $defLimit);
        $usedCount = intval($activeSub['freeze_count'] ?: 0);
        $limitExhausted = ($usedCount >= $allowedLimit);
        ?>
        <?php if ($limitExhausted): ?>
          <button class="btn btn-warning" style="opacity:0.6; cursor:not-allowed;"
            onclick="showToast('Freeze Limit Reached (<?= $usedCount ?>/<?= $allowedLimit ?> Used).', 'warning')">
            ❄️ Freeze Limit Reached
          </button>
        <?php else: ?>
          <button class="btn btn-warning" onclick="openModal('freezeMembershipModal')">
            ❄️ Freeze Plan (<?= $usedCount ?>/<?= $allowedLimit ?>)
          </button>
        <?php endif; ?>
      <?php elseif ($activeSub && $activeSub['status'] === 'frozen'): ?>
        <button class="btn btn-success" onclick="unfreezeMembership(<?= $activeSub['id'] ?>)">
          ▶️ Reactivate / Unfreeze
        </button>
      <?php endif; ?>

      <a href="index.php?page=pos&member_id=<?= $member['id'] ?>" class="btn btn-primary"
        style="font-weight:800; box-shadow:0 4px 12px var(--primary-glow);">
        🛒 POS Renew / Sale
      </a>

      <button class="btn btn-danger" onclick="openModal('refundModal')">
        ⚠️ Refund
      </button>
    </div>
  </div>

  <!-- HERO 360° EXECUTIVE PROFILE BANNER -->
  <div class="card"
    style="background:linear-gradient(135deg, #0F172A 0%, #1E293B 50%, #334155 100%); color:#FFFFFF; border:none; padding:2rem; margin-bottom:1.5rem; border-radius:var(--radius-xl); box-shadow:var(--shadow-float); position:relative; overflow:hidden;">
    <div
      style="position:absolute; right:-60px; top:-60px; width:260px; height:260px; background:radial-gradient(circle, rgba(56,189,248,0.15) 0%, rgba(0,0,0,0) 70%); border-radius:50%; pointer-events:none;">
    </div>

    <div
      style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1.75rem; position:relative; z-index:2;">
      <!-- Left: Identity & Core Details -->
      <div style="display:flex; align-items:center; gap:1.35rem; flex-wrap:wrap;">
        <div style="position:relative;">
          <div
            style="width:84px; height:84px; border-radius:50%; background:linear-gradient(135deg, var(--primary) 0%, #38BDF8 100%); color:#FFFFFF; display:flex; align-items:center; justify-content:center; font-size:2.4rem; font-weight:900; border:3px solid rgba(255,255,255,0.25); box-shadow:0 8px 20px rgba(37,99,235,0.4);">
            <?= strtoupper(substr($member['name'], 0, 1)) ?>
          </div>
          <span
            style="position:absolute; bottom:2px; right:2px; width:20px; height:20px; border-radius:50%; background:<?= $member['status'] === 'active' ? '#10B981' : ($member['status'] === 'frozen' ? '#F59E0B' : '#EF4444') ?>; border:3px solid #0F172A;"
            title="Status: <?= $member['status'] ?>"></span>
        </div>

        <div>
          <div style="display:flex; align-items:center; gap:0.6rem; flex-wrap:wrap;">
            <h1
              style="font-size:1.75rem; font-weight:900; color:#FFFFFF; margin:0; font-family:var(--font-heading); letter-spacing:-0.02em;">
              <?= htmlspecialchars($member['name']) ?>
            </h1>
            <span class="badge"
              style="background:rgba(255,255,255,0.15); color:#38BDF8; font-family:var(--font-mono); font-size:0.85rem; font-weight:800; padding:0.25rem 0.6rem;">
              <?= $member['member_code'] ?>
            </span>
            <span
              class="badge badge-<?= $member['status'] === 'active' ? 'success' : ($member['status'] === 'frozen' ? 'warning' : 'danger') ?>"
              style="font-size:0.8rem; font-weight:800;">
              ● <?= strtoupper($member['status']) ?>
            </span>
            <?php if ($isBirthdayToday): ?>
              <span class="badge"
                style="background:#F59E0B; color:#78350F; font-weight:900; font-size:0.8rem; animation:pulse 2s infinite;">
                🎂 BIRTHDAY TODAY!
              </span>
            <?php endif; ?>
          </div>

          <div
            style="display:flex; align-items:center; gap:1.15rem; flex-wrap:wrap; margin-top:0.65rem; font-size:0.85rem; color:#94A3B8;">
            <span>📞 <strong style="color:#F1F5F9;"><?= htmlspecialchars($member['phone']) ?></strong></span>
            <span>🎂
              <strong><?= !empty($member['dob']) ? date('d M Y', strtotime($member['dob'])) . " ({$ageStr})" : 'DOB not set' ?></strong></span>
            <span>⚧️ <strong><?= htmlspecialchars($member['gender'] ?: 'Male') ?></strong></span>
            <span>📟 Biometric ID: <strong style="color:#38BDF8; font-family:var(--font-mono);"><?= htmlspecialchars($member['biometric_id'] ?: 'BIO-' . $member['id']) ?></strong></span>
            <span>📅 Joined: <strong><?= date('d M Y', strtotime($member['created_at'])) ?></strong></span>
          </div>
        </div>
      </div>

      <!-- Right: KPI Summary Counters -->
      <div style="display:flex; gap:0.85rem; flex-wrap:wrap;">
        <div
          style="background:rgba(255,255,255,0.08); padding:0.85rem 1.25rem; border-radius:14px; border:1px solid rgba(255,255,255,0.12); min-width:140px;">
          <div
            style="font-size:0.72rem; color:#94A3B8; font-weight:800; text-transform:uppercase; letter-spacing:0.04em;">
            ACTIVE PLAN</div>
          <div style="font-size:1.15rem; font-weight:900; color:#38BDF8; margin-top:0.25rem;">
            <?= $activeSub ? htmlspecialchars($activeSub['plan_title']) : 'None' ?>
          </div>
          <div
            style="font-size:0.75rem; color:<?= $daysRemaining <= 7 ? '#F87171' : '#4ADE80' ?>; font-weight:700; margin-top:0.15rem;">
            <?= $expiryStatusText ?>
          </div>
        </div>

        <div
          style="background:rgba(255,255,255,0.08); padding:0.85rem 1.25rem; border-radius:14px; border:1px solid rgba(255,255,255,0.12); min-width:110px;">
          <div
            style="font-size:0.72rem; color:#94A3B8; font-weight:800; text-transform:uppercase; letter-spacing:0.04em;">
            GYM VISITS</div>
          <div
            style="font-size:1.45rem; font-weight:900; color:#4ADE80; margin-top:0.2rem; font-family:var(--font-heading);">
            🔥 <?= $totalVisits ?>
          </div>
          <div style="font-size:0.72rem; color:#94A3B8;">Verified Punches</div>
        </div>

        <div
          style="background:rgba(255,255,255,0.08); padding:0.85rem 1.25rem; border-radius:14px; border:1px solid rgba(255,255,255,0.12); min-width:120px;">
          <div
            style="font-size:0.72rem; color:#94A3B8; font-weight:800; text-transform:uppercase; letter-spacing:0.04em;">
            TOTAL SPENT</div>
          <div
            style="font-size:1.25rem; font-weight:900; color:#FCD34D; margin-top:0.25rem; font-family:var(--font-mono);">
            <?= $currency ?><?= number_format($totalSpent, 2) ?>
          </div>
          <div style="font-size:0.72rem; color:#94A3B8;"><?= count($sales) ?> Invoices</div>
        </div>

        <?php if ($totalDue > 0): ?>
          <div
            style="background:rgba(239, 68, 68, 0.25); padding:0.85rem 1.25rem; border-radius:14px; border:1px solid rgba(239, 68, 68, 0.45); min-width:130px;">
            <div style="font-size:0.72rem; color:#FCA5A5; font-weight:800; text-transform:uppercase;">PENDING DUE</div>
            <div
              style="font-size:1.25rem; font-weight:900; color:#EF4444; margin-top:0.25rem; font-family:var(--font-mono);">
              <?= $currency ?>  <?= number_format($totalDue, 2) ?>
            </div>
            <div style="font-size:0.72rem; color:#FCA5A5; font-weight:700;">
              <?= $nearestDueDate ? 'Due ' . date('d M', strtotime($nearestDueDate)) : 'Unpaid Balance' ?>
            </div>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- WHATSAPP AUTOMATED NOTIFICATION CENTER -->
  <div class="card"
    style="background:linear-gradient(135deg, #064E3B 0%, #047857 100%); color:#FFFFFF; border:none; padding:1.15rem 1.5rem; margin-bottom:1.5rem; border-radius:var(--radius-lg); box-shadow:0 4px 15px rgba(4, 120, 87, 0.25);">
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem;">
      <div>
        <div style="display:flex; align-items:center; gap:0.5rem;">
          <span style="font-size:1.3rem;">📱</span>
          <strong style="font-size:1.05rem; color:#FFFFFF;">WhatsApp Notification Center (Instant 1-Click
            Triggers)</strong>
        </div>
        <p style="font-size:0.8rem; color:#A7F3D0; margin:0.2rem 0 0 0;">
          Direct renewal reminders, Trial Welcome greetings, Fees Due alerts &amp; Birthday wishes
        </p>
      </div>

      <div style="display:flex; gap:0.45rem; flex-wrap:wrap;">
        <?php if ($isBirthdayToday): ?>
          <button class="btn" style="background:#F59E0B; color:#78350F; font-weight:800; border:none;"
            onclick="triggerDirectWa('birthday')">
            🎂 Send Birthday Wish!
          </button>
        <?php endif; ?>

        <button class="btn" style="background:#3B82F6; color:#FFFFFF; font-weight:700; border:none; font-size:0.8rem;"
          onclick="triggerDirectWa('trial_welcome')">
          🏋️‍♂️ Trial Welcome
        </button>

        <?php if ($member['status'] === 'expired' || ($activeSub && strtotime($activeSub['end_date']) < time())): ?>
          <button class="btn" style="background:#DC2626; color:#FFFFFF; font-weight:800; border:none; font-size:0.8rem;"
            onclick="triggerDirectWa('fees_due')">
            🚨 Send Fees Due Alert
          </button>
        <?php endif; ?>

        <button class="btn"
          style="background:rgba(255,255,255,0.18); color:#FFFFFF; border:1px solid rgba(255,255,255,0.35); font-size:0.8rem;"
          onclick="triggerDirectWa('expiry_3_weeks')">
          ⏳ 3 Weeks (21d)
        </button>

        <button class="btn"
          style="background:rgba(255,255,255,0.18); color:#FFFFFF; border:1px solid rgba(255,255,255,0.35); font-size:0.8rem;"
          onclick="triggerDirectWa('expiry_1_week')">
          ⚠️ 1 Week (7d)
        </button>

        <button class="btn"
          style="background:rgba(255,255,255,0.18); color:#FFFFFF; border:1px solid rgba(255,255,255,0.35); font-size:0.8rem;"
          onclick="triggerDirectWa('expiry_2_days')">
          🚨 2 Days
        </button>

        <button class="btn" style="background:#EF4444; color:#FFFFFF; font-weight:700; border:none; font-size:0.8rem;"
          onclick="triggerDirectWa('expiry_same_day')">
          🔔 Same Day
        </button>

        <button class="btn"
          style="background:rgba(255,255,255,0.25); color:#FFFFFF; font-weight:700; border:none; font-size:0.8rem;"
          onclick="openModal('customWaModal')">
          ✍️ Custom Note
        </button>

        <a href="<?= $directWaChatUrl ?>" target="_blank" class="btn"
          style="background:#25D366; color:#FFFFFF; font-weight:800; border:none; text-decoration:none; font-size:0.82rem;">
          💬 Open Chat
        </a>
      </div>
    </div>
  </div>

  <!-- TAB NAVIGATION BAR -->
  <div
    style="display:flex; gap:0.5rem; border-bottom:2px solid var(--border-color); margin-bottom:1.5rem; overflow-x:auto; padding-bottom:0.25rem;">
    <button class="btn profile-tab-btn active" id="tabBtn_overview" onclick="switchProfileTab('overview')">
      🌟 Active Plan &amp; Overview
    </button>
    <button class="btn profile-tab-btn" id="tabBtn_subs" onclick="switchProfileTab('subs')">
      📜 Subscriptions (<?= count($subscriptions) ?>)
    </button>
    <button class="btn profile-tab-btn" id="tabBtn_pool" onclick="switchProfileTab('pool')">
      🏊 Swimming Pool (<?= count($memberPoolSubs) ?>)
    </button>
    <button class="btn profile-tab-btn" id="tabBtn_pt" onclick="switchProfileTab('pt')">
      💪 Personal Training (<?= count($memberPtSubs) ?>)
    </button>
    <button class="btn profile-tab-btn" id="tabBtn_attendance" onclick="switchProfileTab('attendance')">
      ⏱️ Attendance (<?= $totalVisits ?>)
    </button>
    <button class="btn profile-tab-btn" id="tabBtn_sales" onclick="switchProfileTab('sales')">
      🧾 Invoices &amp; Ledger (<?= count($sales) ?>)
    </button>
    <button class="btn profile-tab-btn" id="tabBtn_whatsapp" onclick="switchProfileTab('whatsapp')">
      📱 WhatsApp History (<?= count($waLogs) ?>)
    </button>
  </div>

  <!-- TAB PANE 1: OVERVIEW & ACTIVE PLAN -->
  <div id="tabPane_overview" class="profile-tab-pane">
    <div style="display:grid; grid-template-columns: 320px 1fr; gap:1.5rem;">

      <!-- Left Card: Contact Info & Emergency -->
      <div style="display:flex; flex-direction:column; gap:1.25rem;">
        <div class="card" style="border-radius:var(--radius-lg); box-shadow:var(--shadow-card); padding:1.25rem;">
          <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.85rem;">
            <span style="font-size:1.05rem; font-weight:800; color:var(--text-primary);">👤 Member Profile</span>
            <button class="btn btn-secondary btn-sm" onclick="openProfileEdit()">✏️ Edit</button>
          </div>
          <div style="display:flex; flex-direction:column; gap:0.75rem; font-size:0.85rem;">
            <div>📞 <strong>Mobile Number:</strong> <?= htmlspecialchars($member['phone']) ?></div>
            <div>🎂 <strong>Date of Birth:</strong> <?= !empty($member['dob']) ? date('d M Y', strtotime($member['dob'])) . " ({$ageStr})" : 'Not Provided' ?></div>
            <div>⚧️ <strong>Gender:</strong> <?= htmlspecialchars($member['gender'] ?: 'Male') ?></div>
            <div>📅 <strong>Registration Date:</strong> <?= date('d M Y', strtotime($member['created_at'])) ?></div>
            <div>⚡ <strong>Member Status:</strong> <span class="badge badge-<?= $member['status'] === 'active' ? 'success' : ($member['status'] === 'frozen' ? 'warning' : 'danger') ?>">● <?= strtoupper($member['status']) ?></span></div>
          </div>
        </div>
      </div>

      <!-- Right Card: Active Plan & Progress -->
      <div style="display:flex; flex-direction:column; gap:1.25rem;">

        <?php if ($totalDue > 0): ?>
          <div class="card"
            style="background:#FFFBEB; border:1px solid #FCD34D; border-left:5px solid #D97706; padding:1rem 1.25rem; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:0.75rem; border-radius:var(--radius-lg);">
            <div>
              <div
                style="font-weight:800; color:#92400E; font-size:1.05rem; display:flex; align-items:center; gap:0.4rem;">
                <span>⚠️</span> <span>PENDING PAYMENT DUE: <?= $currency ?><?= number_format($totalDue, 2) ?></span>
              </div>
              <div style="font-size:0.82rem; color:#78350F; margin-top:0.25rem;">
                <?php if ($nearestDueDate): ?>
                  📅 Promised Payment Date: <strong><?= date('d M Y', strtotime($nearestDueDate)) ?></strong>
                  <?php $dueDaysLeft = (int) ceil((strtotime($nearestDueDate) - strtotime(date('Y-m-d'))) / 86400); ?>
                  (<?= $dueDaysLeft >= 0 ? "{$dueDaysLeft} days left" : "⚠️ OVERDUE by " . abs($dueDaysLeft) . " days" ?>)
                <?php else: ?>
                  Pending balance from recent POS transactions.
                <?php endif; ?>
              </div>
            </div>
            <div style="display:flex; gap:0.5rem;">
              <a href="index.php?page=payments&member_id=<?= $member['id'] ?>" class="btn btn-sm"
                style="background:#D97706; color:#fff; font-weight:800; border:none; box-shadow:0 2px 8px rgba(217,119,6,0.3);">
                💵 Clear Due in Payments Ledger
              </a>
              <a href="index.php?page=pos&member_id=<?= $member['id'] ?>" class="btn btn-sm btn-outline"
                style="color:#D97706; border-color:#FCD34D; font-weight:700;">
                🛒 POS Pay
              </a>
            </div>
          </div>
        <?php endif; ?>

        <!-- Detailed Active Subscription Card -->
        <div class="card"
          style="border-top:4px solid var(--primary); border-radius:var(--radius-lg); box-shadow:var(--shadow-card); padding:1.25rem;">
          <div
            style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem; flex-wrap:wrap; gap:0.5rem;">
            <div style="font-size:1.15rem; font-weight:800; color:var(--text-primary);">
              ⭐ Active Membership Protocol
            </div>

            <div style="display:flex; gap:0.4rem; flex-wrap:wrap;">
              <?php if ($activeSub): ?>
                <button class="btn btn-primary btn-sm"
                  onclick="openEditPlan(<?= htmlspecialchars(json_encode($activeSub)) ?>)">
                  ✏️ Edit Plan &amp; Dates
                </button>
              <?php endif; ?>
              <button class="btn btn-outline btn-sm" onclick="openModal('addPlanModal')">
                + Change / Add Plan
              </button>
            </div>
          </div>

          <?php if ($activeSub): ?>
            <div
              style="background:var(--bg-main); padding:1.25rem; border-radius:12px; border:1px solid var(--border-color);">
              <div
                style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:0.5rem;">
                <div>
                  <h2
                    style="font-size:1.35rem; font-weight:900; color:var(--primary); margin:0; font-family:var(--font-heading);">
                    <?= htmlspecialchars($activeSub['plan_title']) ?>
                  </h2>
                  <div style="font-size:0.8rem; color:var(--text-muted); margin-top:0.2rem;">
                    Access: <strong><?= htmlspecialchars($activeSub['access_timing'] ?: 'Full Day Access') ?></strong>
                    &bull; Services:
                    <strong><?= htmlspecialchars($activeSub['included_services'] ?: 'Gym & Cardio Zone') ?></strong>
                  </div>
                </div>

                <div style="text-align:right;">
                  <span class="badge badge-<?= $expiryBadgeClass ?>"
                    style="font-size:0.85rem; font-weight:800; padding:0.35rem 0.75rem;">
                    ● <?= $expiryStatusText ?>
                  </span>
                  <div style="font-size:0.75rem; color:var(--text-muted); margin-top:0.25rem;">
                    Status: <strong style="text-transform:uppercase;"><?= $activeSub['status'] ?></strong>
                  </div>
                </div>
              </div>

              <!-- Plan Details Grid -->
              <div
                style="display:grid; grid-template-columns:repeat(auto-fit, minmax(150px, 1fr)); gap:0.85rem; margin-top:1.25rem; padding-top:1rem; border-top:1px solid var(--border-color);">
                <div
                  style="background:#FFFFFF; padding:0.75rem; border-radius:8px; border:1px solid var(--border-color);">
                  <div style="font-size:0.72rem; color:var(--text-muted); font-weight:700; text-transform:uppercase;">
                    START DATE</div>
                  <div
                    style="font-size:0.95rem; font-weight:800; color:var(--text-primary); margin-top:0.15rem; font-family:var(--font-mono);">
                    <?= date('d M Y', strtotime($activeSub['start_date'])) ?>
                  </div>
                </div>

                <div
                  style="background:#FFFFFF; padding:0.75rem; border-radius:8px; border:1px solid var(--border-color);">
                  <div style="font-size:0.72rem; color:var(--text-muted); font-weight:700; text-transform:uppercase;">
                    EXPIRY / END DATE</div>
                  <div
                    style="font-size:0.95rem; font-weight:800; color:<?= $daysRemaining <= 7 ? 'var(--danger)' : 'var(--primary)' ?>; margin-top:0.15rem; font-family:var(--font-mono);">
                    <?= date('d M Y', strtotime($activeSub['end_date'])) ?>
                  </div>
                </div>

                <div
                  style="background:#FFFFFF; padding:0.75rem; border-radius:8px; border:1px solid var(--border-color);">
                  <div style="font-size:0.72rem; color:var(--text-muted); font-weight:700; text-transform:uppercase;">
                    ACTUAL PRICE PAID</div>
                  <div
                    style="font-size:0.95rem; font-weight:800; color:var(--success); margin-top:0.15rem; font-family:var(--font-mono);">
                    <?= $currency ?>  <?= number_format($activeSub['price_paid'], 2) ?>
                  </div>
                  <?php if (!empty($activeSub['discount_given']) && floatval($activeSub['discount_given']) > 0): ?>
                    <div style="font-size:0.68rem; color:#059669; font-weight:700; margin-top:2px;">
                      🎉 Saved <?= $currency ?><?= number_format($activeSub['discount_given'], 0) ?> Discount
                    </div>
                  <?php endif; ?>
                </div>

                <div
                  style="background:#FFFFFF; padding:0.75rem; border-radius:8px; border:1px solid var(--border-color);">
                  <div style="font-size:0.72rem; color:var(--text-muted); font-weight:700; text-transform:uppercase;">
                    FREEZE USED</div>
                  <div
                    style="font-size:0.95rem; font-weight:800; color:#D97706; margin-top:0.15rem; font-family:var(--font-mono);">
                    <?= intval($activeSub['freeze_days_used']) ?> Days
                  </div>
                </div>
              </div>

              <!-- Days Timeline Progress Bar -->
              <div style="margin-top:1.25rem;">
                <div
                  style="display:flex; justify-content:space-between; font-size:0.78rem; color:var(--text-secondary); margin-bottom:0.35rem;">
                  <span>Plan Timeline (<?= date('d M Y', strtotime($activeSub['start_date'])) ?> ➔
                    <?= date('d M Y', strtotime($activeSub['end_date'])) ?>)</span>
                  <span><strong><?= $planProgressPct ?>%</strong> elapsed</span>
                </div>
                <div style="width:100%; height:8px; background:#E2E8F0; border-radius:99px; overflow:hidden;">
                  <div
                    style="width:<?= $planProgressPct ?>%; height:100%; background:<?= $daysRemaining <= 7 ? 'var(--danger)' : 'var(--primary)' ?>; transition:width 0.5s ease;">
                  </div>
                </div>
              </div>
            </div>

            <?php if ($queuedSub): ?>
              <div style="margin-top:1rem; padding:1rem 1.25rem; background:linear-gradient(135deg, #ECFDF5 0%, #D1FAE5 100%); border:1.5px solid #10B981; border-radius:12px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:0.75rem; box-shadow:0 2px 8px rgba(16,185,129,0.15);">
                <div>
                  <div style="font-size:0.92rem; font-weight:900; color:#065F46; display:flex; align-items:center; gap:0.4rem;">
                    <span>🔄 UPCOMING PLAN QUEUED (AUTO-EXTENDED):</span>
                    <span style="color:#047857; text-decoration:underline;"><?= htmlspecialchars($queuedSub['plan_title']) ?></span>
                  </div>
                  <div style="font-size:0.78rem; color:#065F46; margin-top:0.3rem;">
                    📅 Automatically activates on <strong><?= date('d M Y', strtotime($queuedSub['start_date'])) ?></strong> (Starts immediately after current active plan finishes) &bull; Valid till <strong><?= date('d M Y', strtotime($queuedSub['end_date'])) ?></strong>
                  </div>
                </div>
                <div style="display:flex; align-items:center; gap:0.5rem;">
                  <span class="badge" style="background:#10B981; color:#fff; font-weight:800; font-size:0.75rem; padding:0.35rem 0.65rem;">
                    ✅ 100% QUEUED
                  </span>
                  <button class="btn btn-outline btn-sm" style="background:#fff; color:#065F46; border-color:#10B981; font-weight:700;" onclick="openEditPlan(<?= htmlspecialchars(json_encode($queuedSub)) ?>)">
                    ✏️ Edit
                  </button>
                </div>
              </div>
            <?php endif; ?>
          <?php else: ?>
            <div
              style="padding:2rem; text-align:center; background:var(--bg-main); border-radius:12px; color:var(--text-muted);">
              <div style="font-size:2.5rem; margin-bottom:0.5rem;">📜</div>
              <h3 style="font-size:1.1rem; font-weight:800; color:var(--text-primary); margin-bottom:0.25rem;">No Active
                Membership Plan</h3>
              <p style="font-size:0.85rem; margin:0 0 1rem 0;">Assign a membership plan directly or renew through the
                Touch POS terminal.</p>
              <button class="btn btn-primary" onclick="openModal('addPlanModal')">+ Assign Membership Plan Now</button>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <!-- TAB PANE 2: ALL SUBSCRIPTIONS -->
  <div id="tabPane_subs" class="profile-tab-pane" style="display:none;">
    <div class="card" style="border-radius:var(--radius-lg); box-shadow:var(--shadow-card); padding:1.25rem;">
      <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;">
        <span style="font-size:1.15rem; font-weight:800; color:var(--text-primary);">📜 Membership Plans History</span>
        <button class="btn btn-primary btn-sm" onclick="openModal('addPlanModal')">+ Assign Plan</button>
      </div>

      <div class="table-wrapper">
        <table>
          <thead>
            <tr>
              <th>Plan Title</th>
              <th>Start Date</th>
              <th>End Date</th>
              <th>Price Paid</th>
              <th>Freeze Days</th>
              <th>Status</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($subscriptions)): ?>
              <tr>
                <td colspan="7" style="text-align:center; padding:2rem; color:var(--text-muted);">No subscription history
                  found.</td>
              </tr>
            <?php else: ?>
              <?php foreach ($subscriptions as $sub): ?>
                <tr>
                  <td><strong style="color:var(--text-primary);"><?= htmlspecialchars($sub['plan_title']) ?></strong></td>
                  <td class="font-mono"><?= date('d M Y', strtotime($sub['start_date'])) ?></td>
                  <td class="font-mono"><?= date('d M Y', strtotime($sub['end_date'])) ?></td>
                  <td class="font-mono"><strong
                      style="color:var(--success);"><?= $currency ?><?= number_format($sub['price_paid'], 2) ?></strong>
                  </td>
                  <td class="font-mono"><?= intval($sub['freeze_days_used']) ?> Days</td>
                  <td>
                    <span
                      class="badge badge-<?= $sub['status'] === 'active' ? 'success' : ($sub['status'] === 'frozen' ? 'warning' : 'secondary') ?>">
                      <?= strtoupper($sub['status']) ?>
                    </span>
                  </td>
                  <td>
                    <button class="btn btn-outline btn-sm"
                      onclick="openEditPlan(<?= htmlspecialchars(json_encode($sub)) ?>)">✏️ Edit</button>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- TAB PANE 3: SWIMMING POOL -->
  <div id="tabPane_pool" class="profile-tab-pane" style="display:none;">
    <div class="card" style="border-radius:var(--radius-lg); box-shadow:var(--shadow-card); padding:1.25rem;">
      <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;">
        <span style="font-size:1.15rem; font-weight:800; color:var(--text-primary);">🏊 Swimming Pool Passes &amp;
          Slots</span>
        <button class="btn btn-primary btn-sm" onclick="openModal('assignMemberPoolModal')">+ Assign Pool Pass</button>
      </div>

      <div class="table-wrapper">
        <table>
          <thead>
            <tr>
              <th>Pool Plan</th>
              <th>Assigned Slot</th>
              <th>Validity</th>
              <th>Sessions Left</th>
              <th>Status</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($memberPoolSubs)): ?>
              <tr>
                <td colspan="6" style="text-align:center; padding:2rem; color:var(--text-muted);">No swimming pool passes
                  assigned yet.</td>
              </tr>
            <?php else: ?>
              <?php foreach ($memberPoolSubs as $ps): ?>
                <tr>
                  <td><strong style="color:#0284C7;"><?= htmlspecialchars($ps['plan_title']) ?></strong></td>
                  <td><span class="badge badge-info"><?= htmlspecialchars($ps['slot_assigned'] ?: 'Morning Slot') ?></span>
                  </td>
                  <td class="font-mono"><?= date('d M Y', strtotime($ps['start_date'])) ?> ➔
                    <?= date('d M Y', strtotime($ps['end_date'])) ?></td>
                  <td>
                    <strong><?= $ps['sessions_total'] > 0 ? "{$ps['sessions_remaining']} / {$ps['sessions_total']}" : '♾️ Unlimited' ?></strong>
                  </td>
                  <td><span
                      class="badge badge-<?= $ps['status'] === 'active' ? 'success' : 'secondary' ?>"><?= strtoupper($ps['status']) ?></span>
                  </td>
                  <td>
                    <?php if ($ps['status'] === 'active'): ?>
                      <button class="btn btn-success btn-sm" onclick="punchMemberPoolCheckin(<?= $ps['id'] ?>)">🏊 Punch
                        Entry</button>
                    <?php else: ?>
                      <span style="font-size:0.75rem; color:var(--text-muted);">Expired</span>
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

  <!-- TAB PANE 4: PERSONAL TRAINING -->
  <div id="tabPane_pt" class="profile-tab-pane" style="display:none;">
    <div class="card" style="border-radius:var(--radius-lg); box-shadow:var(--shadow-card); padding:1.25rem;">
      <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;">
        <span style="font-size:1.15rem; font-weight:800; color:var(--text-primary);">💪 Personal Training (PT)
          Packages</span>
        <a href="index.php?page=pt" class="btn btn-primary btn-sm">+ Assign PT in Hub</a>
      </div>

      <div class="table-wrapper">
        <table>
          <thead>
            <tr>
              <th>Package</th>
              <th>Assigned Trainer</th>
              <th>Sessions Total</th>
              <th>Sessions Left</th>
              <th>Expiry Date</th>
              <th>Status</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($memberPtSubs)): ?>
              <tr>
                <td colspan="7" style="text-align:center; padding:2rem; color:var(--text-muted);">No 1-on-1 personal
                  training packages assigned.</td>
              </tr>
            <?php else: ?>
              <?php foreach ($memberPtSubs as $pt): ?>
                <tr>
                  <td><strong style="color:var(--purple);"><?= htmlspecialchars($pt['package_title']) ?></strong></td>
                  <td><strong
                      style="color:var(--text-primary);"><?= htmlspecialchars($pt['trainer_name'] ?: 'Unassigned') ?></strong>
                  </td>
                  <td class="font-mono"><?= $pt['sessions_total'] ?></td>
                  <td><strong style="color:var(--primary); font-size:1rem;"><?= $pt['sessions_remaining'] ?></strong></td>
                  <td class="font-mono"><?= date('d M Y', strtotime($pt['expiry_date'])) ?></td>
                  <td><span
                      class="badge badge-<?= $pt['status'] === 'active' ? 'success' : 'secondary' ?>"><?= strtoupper($pt['status']) ?></span>
                  </td>
                  <td>
                    <?php if ($pt['status'] === 'active' && $pt['sessions_remaining'] > 0): ?>
                      <button class="btn btn-primary btn-sm"
                        onclick="logProfilePtSession(<?= $pt['id'] ?>, '<?= htmlspecialchars(addslashes($member['name'])) ?>')">✓
                        Log Session</button>
                    <?php else: ?>
                      <span style="font-size:0.75rem; color:var(--text-muted);">Completed</span>
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

  <!-- TAB PANE 5: ATTENDANCE -->
  <div id="tabPane_attendance" class="profile-tab-pane" style="display:none;">
    <div class="card" style="border-radius:var(--radius-lg); box-shadow:var(--shadow-card); padding:1.25rem;">
      <span style="font-size:1.15rem; font-weight:800; color:var(--text-primary); display:block; margin-bottom:1rem;">⏱️
        Biometric Attendance Logs (Last 25 Entries)</span>

      <div class="table-wrapper">
        <table>
          <thead>
            <tr>
              <th>Check-in Date &amp; Time</th>
              <th>Verification Mode</th>
              <th>Gate / Machine</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($attendanceLogs)): ?>
              <tr>
                <td colspan="4" style="text-align:center; padding:2rem; color:var(--text-muted);">No attendance check-ins
                  logged yet.</td>
              </tr>
            <?php else: ?>
              <?php foreach ($attendanceLogs as $att): ?>
                <tr>
                  <td class="font-mono"><strong><?= date('d M Y, h:i:s A', strtotime($att['check_in_time'])) ?></strong>
                  </td>
                  <td><span class="badge badge-info"><?= strtoupper($att['verification_method'] ?: 'BIOMETRIC') ?></span>
                  </td>
                  <td>Main Entrance Gate</td>
                  <td><span
                      class="badge badge-<?= $att['status'] === 'success' ? 'success' : 'danger' ?>"><?= strtoupper($att['status']) ?></span>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- TAB PANE 6: SALES & INVOICES -->
  <div id="tabPane_sales" class="profile-tab-pane" style="display:none;">
    <div class="card" style="border-radius:var(--radius-lg); box-shadow:var(--shadow-card); padding:1.25rem;">
      <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;">
        <span style="font-size:1.15rem; font-weight:800; color:var(--text-primary);">🧾 Financial Invoices &amp; Billing
          Ledger</span>
        <a href="index.php?page=pos&member_id=<?= $member['id'] ?>" class="btn btn-primary btn-sm">+ New Sale in POS</a>
      </div>

      <div class="table-wrapper">
        <table>
          <thead>
            <tr>
              <th>Invoice #</th>
              <th>Total Bill</th>
              <th>Paid Amount</th>
              <th>Pending Due</th>
              <th>Payment Mode</th>
              <th>Date</th>
              <th>Status</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($sales)): ?>
              <tr>
                <td colspan="8" style="text-align:center; padding:2rem; color:var(--text-muted);">No billing invoices
                  recorded.</td>
              </tr>
            <?php else: ?>
              <?php foreach ($sales as $s): ?>
                <?php $isVoided = ($s['payment_status'] === 'voided' || $s['payment_status'] === 'cancelled'); ?>
                <tr>
                  <td><strong
                      style="color:var(--primary); font-family:var(--font-mono);"><?= htmlspecialchars($s['invoice_no']) ?></strong>
                  </td>
                  <td class="font-mono"><?= $currency ?><?= number_format($s['total'], 2) ?></td>
                  <td class="font-mono"><strong
                      style="color:var(--success);"><?= $currency ?><?= number_format($s['paid_amount'], 2) ?></strong></td>
                  <td class="font-mono">
                    <?= $s['due_amount'] > 0 ? "<strong style='color:var(--danger);'>{$currency}" . number_format($s['due_amount'], 2) . "</strong>" : "₹0.00" ?>
                  </td>
                  <td><span class="badge badge-secondary"><?= htmlspecialchars($s['payment_method']) ?></span></td>
                  <td class="font-mono"><?= date('d M Y', strtotime($s['created_at'])) ?></td>
                  <td>
                    <span
                      class="badge badge-<?= $s['payment_status'] === 'paid' ? 'success' : ($isVoided ? 'danger' : 'warning') ?>">
                      <?= strtoupper($s['payment_status']) ?>
                    </span>
                  </td>
                  <td>
                    <?php if (!$isVoided): ?>
                      <button class="btn btn-danger btn-sm"
                        onclick="openProfileVoidBillModal(<?= $s['id'] ?>, '<?= htmlspecialchars($s['invoice_no']) ?>', <?= $s['total'] ?>)">🚫
                        Void</button>
                    <?php else: ?>
                      <span style="font-size:0.75rem; color:var(--text-muted);">Reversed</span>
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

  <!-- TAB PANE 7: WHATSAPP LOGS -->
  <div id="tabPane_whatsapp" class="profile-tab-pane" style="display:none;">
    <div class="card" style="border-radius:var(--radius-lg); box-shadow:var(--shadow-card); padding:1.25rem;">
      <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;">
        <span style="font-size:1.15rem; font-weight:800; color:var(--text-primary);">📱 WhatsApp Message Audit
          Logs</span>
        <button class="btn btn-primary btn-sm" onclick="openModal('customWaModal')">✍️ Send New Message</button>
      </div>

      <div class="table-wrapper">
        <table>
          <thead>
            <tr>
              <th>Date &amp; Time</th>
              <th>Trigger Event</th>
              <th>Message Body</th>
              <th>Delivery Status</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($waLogs)): ?>
              <tr>
                <td colspan="4" style="text-align:center; padding:2rem; color:var(--text-muted);">No WhatsApp
                  communications dispatched yet.</td>
              </tr>
            <?php else: ?>
              <?php foreach ($waLogs as $w): ?>
                <tr>
                  <td class="font-mono"><?= date('d M Y, h:i A', strtotime($w['sent_at'])) ?></td>
                  <td><span class="badge badge-info"><?= htmlspecialchars($w['trigger_event']) ?></span></td>
                  <td style="max-width:340px; font-size:0.82rem;"><?= htmlspecialchars($w['message_body']) ?></td>
                  <td><span class="badge badge-success"><?= strtoupper($w['sent_status']) ?></span></td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- ========================================================= -->
<!-- MODALS SECTION                                            -->
<!-- ========================================================= -->

<!-- 1. EDIT PROFILE MODAL -->
<div class="modal" id="editProfileModal"
  style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.55); z-index:100; align-items:center; justify-content:center; backdrop-filter:blur(2px);">
  <div class="modal-overlay" style="position:absolute; inset:0;" onclick="closeModal('editProfileModal')"></div>
  <div class="card"
    style="position:relative; z-index:10; background:#fff; width:92%; max-width:540px; border-radius:18px; padding:1.5rem; box-shadow:var(--shadow-modal); max-height:90vh; overflow-y:auto;">
    <div
      style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.25rem; border-bottom:1px solid var(--border-color); padding-bottom:0.75rem;">
      <span style="font-size:1.15rem; font-weight:800; color:var(--text-primary);">✏️ Edit Member Profile</span>
      <button style="background:none; border:none; font-size:1.2rem; cursor:pointer;"
        onclick="closeModal('editProfileModal')">✕</button>
    </div>

    <form onsubmit="event.preventDefault(); submitProfileUpdate();">
      <input type="hidden" id="epId" value="<?= $member['id'] ?>">

      <div class="form-group">
        <label class="form-label">Full Name *</label>
        <input type="text" id="epName" class="form-control" value="<?= htmlspecialchars($member['name']) ?>" required>
      </div>

      <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.75rem;">
        <div class="form-group">
          <label class="form-label">Phone Number *</label>
          <input type="tel" id="epPhone" class="form-control font-mono"
            value="<?= htmlspecialchars($member['phone']) ?>" required>
        </div>
        <div class="form-group">
          <label class="form-label">Date of Birth</label>
          <input type="date" id="epDob" class="form-control font-mono" value="<?= $member['dob'] ?>">
        </div>
      </div>

      <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.75rem;">
        <div class="form-group">
          <label class="form-label">Gender</label>
          <select id="epGender" class="form-control">
            <option value="Male" <?= $member['gender'] === 'Male' ? 'selected' : '' ?>>Male</option>
            <option value="Female" <?= $member['gender'] === 'Female' ? 'selected' : '' ?>>Female</option>
            <option value="Other" <?= $member['gender'] === 'Other' ? 'selected' : '' ?>>Other</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Status</label>
          <select id="epStatus" class="form-control">
            <option value="active" <?= $member['status'] === 'active' ? 'selected' : '' ?>>Active</option>
            <option value="expired" <?= $member['status'] === 'expired' ? 'selected' : '' ?>>Expired</option>
            <option value="frozen" <?= $member['status'] === 'frozen' ? 'selected' : '' ?>>Frozen</option>
            <option value="inactive" <?= $member['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
          </select>
        </div>
      </div>

      <div class="form-group">
        <label class="form-label">Biometric ID / RFID 📟</label>
        <input type="text" id="epBiometric" class="form-control font-mono" value="<?= htmlspecialchars($member['biometric_id'] ?? '') ?>" placeholder="BIO-1001">
      </div>

      <div style="display:flex; gap:0.75rem; margin-top:1.5rem;">
        <button type="button" class="btn btn-secondary btn-block"
          onclick="closeModal('editProfileModal')">Cancel</button>
        <button type="submit" class="btn btn-primary btn-block">✓ SAVE CHANGES</button>
      </div>
    </form>
  </div>
</div>

<!-- 2. EDIT PLAN MODAL -->
<div class="modal" id="editPlanModal"
  style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.55); z-index:100; align-items:center; justify-content:center; backdrop-filter:blur(2px);">
  <div class="modal-overlay" style="position:absolute; inset:0;" onclick="closeModal('editPlanModal')"></div>
  <div class="card"
    style="position:relative; z-index:10; background:#fff; width:92%; max-width:480px; border-radius:18px; padding:1.5rem; box-shadow:var(--shadow-modal);">
    <div
      style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.25rem; border-bottom:1px solid var(--border-color); padding-bottom:0.75rem;">
      <span style="font-size:1.15rem; font-weight:800; color:var(--text-primary);">📝 Edit Membership Plan &amp;
        Dates</span>
      <button style="background:none; border:none; font-size:1.2rem; cursor:pointer;"
        onclick="closeModal('editPlanModal')">✕</button>
    </div>

    <form onsubmit="event.preventDefault(); submitEditPlan();">
      <input type="hidden" id="editPlanSubId">

      <div class="form-group">
        <label class="form-label">Membership Type</label>
        <select id="editPlanTypeId" class="form-control" onchange="handlePlanTypeChange(this)">
          <?php foreach ($membershipTypes as $mt): ?>
            <option value="<?= $mt['id'] ?>" data-days="<?= $mt['duration_days'] ?>" data-price="<?= $mt['price'] ?>">
              <?= htmlspecialchars($mt['title']) ?> (<?= $currency ?><?= number_format($mt['price'], 2) ?>)
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.75rem;">
        <div class="form-group">
          <label class="form-label">Start Date</label>
          <input type="date" id="editPlanStartDate" class="form-control font-mono" required>
        </div>
        <div class="form-group">
          <label class="form-label">End Date (Expiry)</label>
          <input type="date" id="editPlanEndDate" class="form-control font-mono" required>
        </div>
      </div>

      <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.75rem;">
        <div class="form-group">
          <label class="form-label">Price Paid (<?= $currency ?>)</label>
          <input type="number" step="0.01" id="editPlanPrice" class="form-control font-mono" required>
        </div>
        <div class="form-group">
          <label class="form-label">Plan Status</label>
          <select id="editPlanStatus" class="form-control">
            <option value="active">Active</option>
            <option value="expired">Expired</option>
            <option value="frozen">Frozen</option>
            <option value="cancelled">Cancelled</option>
          </select>
        </div>
      </div>

      <div style="display:flex; gap:0.75rem; margin-top:1.5rem;">
        <button type="button" class="btn btn-secondary btn-block" onclick="closeModal('editPlanModal')">Cancel</button>
        <button type="submit" class="btn btn-primary btn-block">✓ UPDATE PLAN</button>
      </div>
    </form>
  </div>
</div>

<!-- 3. ADD / ASSIGN PLAN MODAL -->
<div class="modal" id="addPlanModal"
  style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.55); z-index:100; align-items:center; justify-content:center; backdrop-filter:blur(2px);">
  <div class="modal-overlay" style="position:absolute; inset:0;" onclick="closeModal('addPlanModal')"></div>
  <div class="card"
    style="position:relative; z-index:10; background:#fff; width:92%; max-width:480px; border-radius:18px; padding:1.5rem; box-shadow:var(--shadow-modal);">
    <div
      style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.25rem; border-bottom:1px solid var(--border-color); padding-bottom:0.75rem;">
      <span style="font-size:1.15rem; font-weight:800; color:var(--text-primary);">+ Assign New Membership Plan</span>
      <button style="background:none; border:none; font-size:1.2rem; cursor:pointer;"
        onclick="closeModal('addPlanModal')">✕</button>
    </div>

    <form onsubmit="event.preventDefault(); submitAddPlan();">
      <input type="hidden" id="addPlanMemberId" value="<?= $member['id'] ?>">

      <div class="form-group">
        <label class="form-label">Select Membership Plan *</label>
        <select id="addPlanTypeId" class="form-control" onchange="handleAddPlanTypeChange(this)" required>
          <?php foreach ($membershipTypes as $mt): ?>
            <option value="<?= $mt['id'] ?>" data-days="<?= $mt['duration_days'] ?>" data-price="<?= $mt['price'] ?>">
              <?= htmlspecialchars($mt['title']) ?> (<?= $mt['duration_days'] ?> Days -
              <?= $currency ?>  <?= number_format($mt['price'], 2) ?>)
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.75rem;">
        <div class="form-group">
          <label class="form-label">Start Date *</label>
          <input type="date" id="addPlanStartDate" class="form-control font-mono" value="<?= date('Y-m-d') ?>"
            onchange="calculateAddEndDate()" required>
        </div>
        <div class="form-group">
          <label class="form-label">End Date *</label>
          <input type="date" id="addPlanEndDate" class="form-control font-mono" required>
        </div>
      </div>

      <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.75rem;">
        <div class="form-group">
          <label class="form-label">Price Paid (<?= $currency ?>)</label>
          <input type="number" step="0.01" id="addPlanPrice" class="form-control font-mono" required>
        </div>
        <div class="form-group">
          <label class="form-label">Initial Status</label>
          <select id="addPlanStatus" class="form-control">
            <option value="active">Active</option>
            <option value="pending">Pending</option>
          </select>
        </div>
      </div>

      <div style="display:flex; gap:0.75rem; margin-top:1.5rem;">
        <button type="button" class="btn btn-secondary btn-block" onclick="closeModal('addPlanModal')">Cancel</button>
        <button type="submit" class="btn btn-primary btn-block">✓ ASSIGN PLAN</button>
      </div>
    </form>
  </div>
</div>

<!-- 4. FREEZE MEMBERSHIP MODAL (Temporary with duration vs Permanent indefinite) -->
<div class="modal" id="freezeMembershipModal"
  style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.55); z-index:100; align-items:center; justify-content:center; backdrop-filter:blur(2px);">
  <div class="modal-overlay" style="position:absolute; inset:0;" onclick="closeModal('freezeMembershipModal')"></div>
  <div class="card"
    style="position:relative; z-index:10; background:#fff; width:92%; max-width:520px; border-radius:18px; padding:1.5rem; box-shadow:var(--shadow-modal); max-height:90vh; overflow-y:auto;">
    <div
      style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.25rem; border-bottom:1px solid var(--border-color); padding-bottom:0.75rem;">
      <span style="font-size:1.15rem; font-weight:800; color:var(--warning);">❄️ Freeze / Pause Membership Plan</span>
      <button style="background:none; border:none; font-size:1.2rem; cursor:pointer;"
        onclick="closeModal('freezeMembershipModal')">✕</button>
    </div>

    <form onsubmit="submitFreeze(event)">
      <input type="hidden" name="member_id" value="<?= $member['id'] ?>">
      <input type="hidden" name="subscription_id" value="<?= $activeSub ? $activeSub['id'] : 0 ?>">
      <input type="hidden" name="freeze_type" id="freezeTypeInput" value="temporary">

      <!-- Freeze Type Selector: Temporary vs Permanent -->
      <div style="margin-bottom:1.25rem;">
        <label class="form-label" style="font-weight:800; font-size:0.88rem;">Select Freeze Type:</label>
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.75rem; margin-top:0.35rem;">
          <div id="freezeTypeCardTemp" onclick="selectFreezeType('temporary')"
            style="border:2px solid var(--primary); background:rgba(14,165,233,0.08); padding:0.85rem; border-radius:12px; cursor:pointer; text-align:center; transition:all 0.2s;">
            <div style="font-size:1.25rem;">⏳</div>
            <div style="font-weight:800; font-size:0.88rem; color:var(--primary); margin-top:0.25rem;">Temporary Freeze</div>
            <div style="font-size:0.72rem; color:var(--text-muted); margin-top:0.2rem;">Time Pata Hai (Fixed Days / Auto-Unfreeze)</div>
          </div>
          <div id="freezeTypeCardPerm" onclick="selectFreezeType('permanent')"
            style="border:2px solid var(--border-color); background:var(--bg-surface); padding:0.85rem; border-radius:12px; cursor:pointer; text-align:center; transition:all 0.2s;">
            <div style="font-size:1.25rem;">🔒</div>
            <div style="font-weight:800; font-size:0.88rem; color:var(--text-primary); margin-top:0.25rem;">Permanent Freeze</div>
            <div style="font-size:0.72rem; color:var(--text-muted); margin-top:0.2rem;">Time Pata Nahi (Indefinite / Manual Unfreeze)</div>
          </div>
        </div>
      </div>

      <div class="form-group">
        <label class="form-label">Freeze Effective Date</label>
        <input type="date" name="freeze_start_date" id="freezeEffectiveDate" class="form-control font-mono"
          value="<?= date('Y-m-d') ?>" onchange="calculateFreezePreview()">
      </div>

      <!-- Temporary Duration Box (Shown only if temporary) -->
      <div class="form-group" id="tempFreezeDaysGroup">
        <label class="form-label">Freeze Duration (Days)</label>
        <input type="number" name="freeze_days" id="tempFreezeDaysInput" class="form-control font-mono" value="7"
          min="1" max="180" oninput="calculateFreezePreview()">
        <div style="display:flex; gap:0.4rem; margin-top:0.4rem; flex-wrap:wrap;">
          <button type="button" class="btn btn-secondary btn-sm" style="font-size:0.72rem; padding:2px 8px;" onclick="setFreezeDays(7)">7 Days</button>
          <button type="button" class="btn btn-secondary btn-sm" style="font-size:0.72rem; padding:2px 8px;" onclick="setFreezeDays(15)">15 Days</button>
          <button type="button" class="btn btn-secondary btn-sm" style="font-size:0.72rem; padding:2px 8px;" onclick="setFreezeDays(30)">30 Days</button>
          <button type="button" class="btn btn-secondary btn-sm" style="font-size:0.72rem; padding:2px 8px;" onclick="setFreezeDays(60)">60 Days</button>
        </div>
      </div>

      <!-- Live Calculation Box -->
      <div id="freezeLivePreviewBox" style="background:#EFF6FF; border:1px solid #BFDBFE; border-radius:10px; padding:0.75rem 1rem; margin-bottom:1rem; font-size:0.8rem; line-height:1.45; color:#1E40AF;">
        <!-- Filled dynamically by calculateFreezePreview() -->
      </div>

      <div class="form-group">
        <label class="form-label">Reason for Freezing</label>
        <input type="text" name="reason" class="form-control" placeholder="e.g. Travel out of station, Medical recovery, Exams"
          required>
      </div>

      <div style="display:flex; gap:0.75rem; margin-top:1.5rem;">
        <button type="button" class="btn btn-secondary btn-block"
          onclick="closeModal('freezeMembershipModal')">Cancel</button>
        <button type="submit" class="btn btn-warning btn-block" style="font-weight:800; color:#fff;">❄️ CONFIRM
          FREEZE</button>
      </div>
    </form>
  </div>
</div>

<!-- 5. ASSIGN POOL PASS MODAL -->
<div class="modal" id="assignMemberPoolModal"
  style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.55); z-index:100; align-items:center; justify-content:center; backdrop-filter:blur(2px);">
  <div class="modal-overlay" style="position:absolute; inset:0;" onclick="closeModal('assignMemberPoolModal')"></div>
  <div class="card"
    style="position:relative; z-index:10; background:#fff; width:92%; max-width:480px; border-radius:18px; padding:1.5rem; box-shadow:var(--shadow-modal);">
    <div
      style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.25rem; border-bottom:1px solid var(--border-color); padding-bottom:0.75rem;">
      <span style="font-size:1.15rem; font-weight:800; color:#0284C7;">🏊 Assign Swimming Pool Pass</span>
      <button style="background:none; border:none; font-size:1.2rem; cursor:pointer;"
        onclick="closeModal('assignMemberPoolModal')">✕</button>
    </div>

    <form onsubmit="event.preventDefault(); submitAssignPoolPass();">
      <input type="hidden" id="poolMemberId" value="<?= $member['id'] ?>">

      <div class="form-group">
        <label class="form-label">Select Swimming Pool Plan *</label>
        <select id="assignPoolPlanId" class="form-control" onchange="handlePoolPlanSelect(this)" required>
          <option value="">-- Choose Pool Plan --</option>
          <?php foreach ($allPoolPlans as $pp): ?>
            <option value="<?= $pp['id'] ?>" data-price="<?= $pp['price'] ?>" data-days="<?= $pp['duration_days'] ?>"
              data-slot="<?= htmlspecialchars($pp['slot_timing'] ?? '') ?>">
              <?= htmlspecialchars($pp['title']) ?> (<?= $pp['duration_days'] ?> Days -
              <?= $currency ?>  <?= number_format($pp['price'], 2) ?>)
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-group">
        <label class="form-label">Assigned Batch / Slot Timing</label>
        <input type="text" id="assignPoolSlot" class="form-control" value="Morning Slot (6:00 AM - 10:00 AM)">
      </div>

      <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.75rem;">
        <div class="form-group">
          <label class="form-label">Start Date *</label>
          <input type="date" id="assignPoolStartDate" class="form-control font-mono" value="<?= date('Y-m-d') ?>"
            onchange="calcPoolEndDate()" required>
        </div>
        <div class="form-group">
          <label class="form-label">End Date *</label>
          <input type="date" id="assignPoolEndDate" class="form-control font-mono" required>
        </div>
      </div>

      <div class="form-group">
        <label class="form-label">Pass Fee (<?= $currency ?>)</label>
        <input type="number" step="0.01" id="assignPoolPrice" class="form-control font-mono" required>
      </div>

      <div style="display:flex; gap:0.75rem; margin-top:1.5rem;">
        <button type="button" class="btn btn-secondary btn-block"
          onclick="closeModal('assignMemberPoolModal')">Cancel</button>
        <button type="submit" class="btn btn-primary btn-block">✓ ASSIGN POOL PASS</button>
      </div>
    </form>
  </div>
</div>

<!-- 6. CUSTOM WHATSAPP MODAL -->
<div class="modal" id="customWaModal"
  style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.55); z-index:100; align-items:center; justify-content:center; backdrop-filter:blur(2px);">
  <div class="modal-overlay" style="position:absolute; inset:0;" onclick="closeModal('customWaModal')"></div>
  <div class="card"
    style="position:relative; z-index:10; background:#fff; width:92%; max-width:460px; border-radius:18px; padding:1.5rem; box-shadow:var(--shadow-modal);">
    <div
      style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.25rem; border-bottom:1px solid var(--border-color); padding-bottom:0.75rem;">
      <span style="font-size:1.15rem; font-weight:800; color:#047857;">💬 Send Custom WhatsApp Message</span>
      <button style="background:none; border:none; font-size:1.2rem; cursor:pointer;"
        onclick="closeModal('customWaModal')">✕</button>
    </div>

    <form onsubmit="submitCustomWa(event)">
      <div class="form-group">
        <label class="form-label">Recipient</label>
        <input type="text" class="form-control"
          value="<?= htmlspecialchars($member['name']) ?> (<?= htmlspecialchars($member['phone']) ?>)" readonly>
      </div>

      <div class="form-group">
        <label class="form-label">Message Content *</label>
        <textarea id="customWaBody" class="form-control" rows="4"
          placeholder="Dear <?= htmlspecialchars($member['name']) ?>, this is a note regarding your gym schedule..."
          required></textarea>
      </div>

      <div style="display:flex; gap:0.75rem; margin-top:1.5rem;">
        <button type="button" class="btn btn-secondary btn-block" onclick="closeModal('customWaModal')">Cancel</button>
        <button type="submit" class="btn btn-success btn-block" style="background:#25D366; border-color:#25D366;">🚀
          DISPATCH WHATSAPP</button>
      </div>
    </form>
  </div>
</div>

<!-- 8. PROCESS REFUND MODAL -->
<div class="modal" id="refundModal"
  style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.55); z-index:100; align-items:center; justify-content:center; backdrop-filter:blur(2px);">
  <div class="modal-overlay" style="position:absolute; inset:0;" onclick="closeModal('refundModal')"></div>
  <div class="card"
    style="position:relative; z-index:10; background:#fff; width:92%; max-width:440px; border-radius:18px; padding:1.5rem; border-top:5px solid var(--danger); box-shadow:var(--shadow-modal);">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.25rem;">
      <span style="font-size:1.15rem; font-weight:800; color:var(--danger);">⚠️ Issue Member Refund</span>
      <button style="background:none; border:none; font-size:1.2rem; cursor:pointer;"
        onclick="closeModal('refundModal')">✕</button>
    </div>

    <form onsubmit="submitRefund(event)">
      <input type="hidden" name="member_id" value="<?= $member['id'] ?>">

      <div class="form-group">
        <label class="form-label">Refund Amount (<?= $currency ?>) *</label>
        <input type="number" step="0.01" name="refund_amount" class="form-control font-mono" required>
      </div>

      <div class="form-group">
        <label class="form-label">Refund Reason *</label>
        <input type="text" name="reason" class="form-control" placeholder="e.g. Relocation, Medical condition" required>
      </div>

      <div style="display:flex; gap:0.75rem; margin-top:1.5rem;">
        <button type="button" class="btn btn-secondary btn-block" onclick="closeModal('refundModal')">Cancel</button>
        <button type="submit" class="btn btn-danger btn-block">✓ PROCESS REFUND</button>
      </div>
    </form>
  </div>
</div>

<!-- 9. VOID BILL MODAL -->
<div class="modal" id="profileVoidBillModal"
  style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.55); z-index:100; align-items:center; justify-content:center; backdrop-filter:blur(2px);">
  <div class="modal-overlay" style="position:absolute; inset:0;" onclick="closeModal('profileVoidBillModal')"></div>
  <div class="card"
    style="position:relative; z-index:10; background:#fff; width:92%; max-width:440px; border-radius:18px; padding:1.5rem; border-top:5px solid var(--danger); box-shadow:var(--shadow-modal);">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.25rem;">
      <span style="font-size:1.15rem; font-weight:800; color:var(--danger);">🚫 Void / Cancel Wrong Bill</span>
      <button style="background:none; border:none; font-size:1.2rem; cursor:pointer;"
        onclick="closeModal('profileVoidBillModal')">✕</button>
    </div>

    <form onsubmit="event.preventDefault(); submitProfileVoidBill();">
      <input type="hidden" id="profileVoidSaleId">

      <div
        style="background:var(--danger-bg); border:1px solid var(--danger-border); padding:0.85rem; border-radius:8px; margin-bottom:1rem; font-size:0.82rem; color:#991B1B;">
        ⚠️ Voiding invoice <strong id="profileVoidInvoiceNo"></strong> (Amount: <span
          id="profileVoidAmountDisplay"></span>) will restore stock and reverse ledger entries.
      </div>

      <div class="form-group">
        <label class="form-label">Wrong Billing Reason *</label>
        <select id="profileVoidReasonSelect" class="form-control">
          <option value="Wrong Member Selected in POS">Wrong Member Selected in POS</option>
          <option value="Incorrect Plan or Price Entered">Incorrect Plan or Price Entered</option>
          <option value="Duplicate Bill Created by Mistake">Duplicate Bill Created by Mistake</option>
          <option value="Customer Dispute / Cancelled Transaction">Customer Dispute / Cancelled</option>
        </select>
      </div>

      <div class="form-group">
        <label class="form-label">Additional Notes</label>
        <input type="text" id="profileVoidCustomReason" class="form-control"
          placeholder="e.g. Billed incorrectly during rush hour">
      </div>

      <div style="display:flex; gap:0.75rem; margin-top:1.5rem;">
        <button type="button" class="btn btn-secondary btn-block"
          onclick="closeModal('profileVoidBillModal')">Cancel</button>
        <button type="submit" class="btn btn-danger btn-block">🚫 CONFIRM VOID</button>
      </div>
    </form>
  </div>
</div>

<style>
  .profile-tab-btn {
    background: var(--bg-surface-secondary);
    color: var(--text-secondary);
    border: 1px solid var(--border-color);
    padding: 0.5rem 1rem;
    border-radius: var(--radius-md) var(--radius-md) 0 0;
    font-weight: 700;
    font-size: 0.84rem;
    cursor: pointer;
    white-space: nowrap;
    transition: all var(--transition-fast);
  }

  .profile-tab-btn:hover {
    background: var(--bg-surface-hover);
    color: var(--primary);
  }

  .profile-tab-btn.active {
    background: var(--primary);
    color: #FFFFFF;
    border-color: var(--primary);
    box-shadow: 0 -2px 8px var(--primary-glow);
  }
</style>

<script>
  function switchProfileTab(tabName) {
    document.querySelectorAll('.profile-tab-pane').forEach(el => el.style.display = 'none');
    document.querySelectorAll('.profile-tab-btn').forEach(el => el.classList.remove('active'));

    const pane = document.getElementById('tabPane_' + tabName);
    if (pane) pane.style.display = 'block';

    const btn = document.getElementById('tabBtn_' + tabName);
    if (btn) btn.classList.add('active');
  }

  function openProfileEdit() {
    openModal('editProfileModal');
  }

  function openEditPlan(sub) {
    document.getElementById('editPlanSubId').value = sub.id;
    document.getElementById('editPlanTypeId').value = sub.membership_type_id;
    document.getElementById('editPlanStartDate').value = sub.start_date;
    document.getElementById('editPlanEndDate').value = sub.end_date;
    document.getElementById('editPlanPrice').value = sub.price_paid;
    document.getElementById('editPlanStatus').value = sub.status || 'active';
    openModal('editPlanModal');
  }

  function handlePlanTypeChange(selectEl) {
    const selectedOpt = selectEl.options[selectEl.selectedIndex];
    const days = parseInt(selectedOpt.getAttribute('data-days')) || 30;
    const price = parseFloat(selectedOpt.getAttribute('data-price')) || 0;

    const startVal = document.getElementById('editPlanStartDate').value || new Date().toISOString().slice(0, 10);
    const startDt = new Date(startVal);
    startDt.setDate(startDt.getDate() + days);

    document.getElementById('editPlanEndDate').value = startDt.toISOString().slice(0, 10);
    document.getElementById('editPlanPrice').value = price;
  }

  function submitEditPlan() {
    const payload = {
      subscription_id: document.getElementById('editPlanSubId').value,
      membership_type_id: document.getElementById('editPlanTypeId').value,
      start_date: document.getElementById('editPlanStartDate').value,
      end_date: document.getElementById('editPlanEndDate').value,
      price_paid: document.getElementById('editPlanPrice').value,
      status: document.getElementById('editPlanStatus').value
    };

    fetch('api/memberships.php?action=edit_subscription', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    })
      .then(res => res.text())
      .then(text => {
        try { return JSON.parse(text); } catch (e) { return { success: false, message: text }; }
      })
      .then(res => {
        if (res.success) {
          showToast('Membership Plan Updated Successfully!', 'success');
          closeModal('editPlanModal');
          setTimeout(() => location.reload(), 1000);
        } else {
          showToast(res.message || 'Plan update failed', 'danger');
        }
      });
  }

  function handleAddPlanTypeChange(selectEl) {
    calculateAddEndDate();
    const selectedOpt = selectEl.options[selectEl.selectedIndex];
    const price = parseFloat(selectedOpt.getAttribute('data-price')) || 0;
    document.getElementById('addPlanPrice').value = price;
  }

  function calculateAddEndDate() {
    const selectEl = document.getElementById('addPlanTypeId');
    const selectedOpt = selectEl.options[selectEl.selectedIndex];
    const days = parseInt(selectedOpt.getAttribute('data-days')) || 30;

    const startVal = document.getElementById('addPlanStartDate').value || new Date().toISOString().slice(0, 10);
    const startDt = new Date(startVal);
    startDt.setDate(startDt.getDate() + days);

    document.getElementById('addPlanEndDate').value = startDt.toISOString().slice(0, 10);
  }

  function submitAddPlan() {
    const payload = {
      member_id: document.getElementById('addPlanMemberId').value,
      membership_type_id: document.getElementById('addPlanTypeId').value,
      start_date: document.getElementById('addPlanStartDate').value,
      end_date: document.getElementById('addPlanEndDate').value,
      price_paid: document.getElementById('addPlanPrice').value,
      status: document.getElementById('addPlanStatus').value
    };

    fetch('api/memberships.php?action=add_member_subscription', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    })
      .then(res => res.text())
      .then(text => {
        try { return JSON.parse(text); } catch (e) { return { success: false, message: text }; }
      })
      .then(res => {
        if (res.success) {
          showToast(res.message || 'Plan Assigned Successfully!', 'success');
          closeModal('addPlanModal');
          setTimeout(() => location.reload(), 1000);
        } else {
          showToast(res.message || 'Plan assignment failed', 'danger');
        }
      });
  }

  function submitProfileUpdate() {
    const payload = {
      id: document.getElementById('epId').value,
      name: document.getElementById('epName').value.trim(),
      phone: document.getElementById('epPhone').value.trim(),
      dob: document.getElementById('epDob').value || null,
      gender: document.getElementById('epGender').value,
      biometric_id: document.getElementById('epBiometric') ? document.getElementById('epBiometric').value.trim() : '',
      status: document.getElementById('epStatus').value
    };

    fetch('api/members.php?action=update', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    })
      .then(res => res.text())
      .then(text => {
        try { return JSON.parse(text); } catch (e) { return { success: false, message: text }; }
      })
      .then(res => {
        if (res.success) {
          showToast('Member Profile Updated Successfully!', 'success');
          closeModal('editProfileModal');
          setTimeout(() => location.reload(), 1000);
        } else {
          showToast(res.message || 'Update failed', 'danger');
        }
      });
  }

  function triggerDirectWa(triggerEvent) {
    showToast('Preparing WhatsApp message...', 'info');
    fetch('api/marketing.php?action=send_direct_message', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        member_id: <?= $member['id'] ?>,
        trigger_event: triggerEvent
      })
    })
      .then(res => res.text())
      .then(text => {
        try { return JSON.parse(text); } catch (e) { return { success: false, message: text }; }
      })
      .then(res => {
        if (res.success && res.data.wa_url) {
          showToast('Opening WhatsApp with reminder message...', 'success');
          window.open(res.data.wa_url, '_blank');
          setTimeout(() => location.reload(), 1500);
        } else {
          showToast(res.message || 'Failed to prepare message', 'danger');
        }
      });
  }

  function punchMemberPoolCheckin(subId) {
    fetch('api/pool.php?action=check_in', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        member_id: <?= $member['id'] ?>,
        subscription_id: subId,
        slot_name: 'Member Profile Direct Entry'
      })
    })
      .then(res => res.text())
      .then(text => {
        try { return JSON.parse(text); } catch (e) { return { success: false, message: text }; }
      })
      .then(res => {
        if (res.success) {
          showToast(res.message, 'success');
          setTimeout(() => location.reload(), 1200);
        } else {
          showToast(res.message || 'Pool check-in failed', 'danger');
        }
      });
  }

  function logProfilePtSession(ptId, memberName) {
    if (confirm(`Log 1 completed Personal Training (PT) session for ${memberName}?`)) {
      fetch('api/trainers.php?action=checkin_pt_session', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ pt_subscription_id: ptId })
      })
        .then(res => res.text())
        .then(text => {
          try { return JSON.parse(text); } catch (e) { return { success: false, message: text }; }
        })
        .then(res => {
          if (res.success) {
            showToast(res.message, 'success');
            setTimeout(() => location.reload(), 1000);
          } else {
            showToast(res.message || 'Could not log PT session', 'danger');
          }
        });
    }
  }

  function submitCustomWa(e) {
    e.preventDefault();
    const text = document.getElementById('customWaBody').value.trim();
    if (!text) return;

    fetch('api/marketing.php?action=send_direct_message', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        member_id: <?= $member['id'] ?>,
        trigger_event: 'custom_broadcast',
        message_text: text
      })
    })
      .then(res => res.text())
      .then(text => {
        try { return JSON.parse(text); } catch (e) { return { success: false, message: text }; }
      })
      .then(res => {
        if (res.success && res.data.wa_url) {
          closeModal('customWaModal');
          window.open(res.data.wa_url, '_blank');
          setTimeout(() => location.reload(), 1500);
        }
      });
  }

  let currentFreezeMode = 'temporary';

  function selectFreezeType(type) {
    currentFreezeMode = type;
    const typeInput = document.getElementById('freezeTypeInput');
    if (typeInput) typeInput.value = type;

    const cardTemp = document.getElementById('freezeTypeCardTemp');
    const cardPerm = document.getElementById('freezeTypeCardPerm');
    const daysGroup = document.getElementById('tempFreezeDaysGroup');

    if (type === 'temporary') {
      if (cardTemp) { cardTemp.style.borderColor = 'var(--primary)'; cardTemp.style.background = 'rgba(14,165,233,0.08)'; }
      if (cardPerm) { cardPerm.style.borderColor = 'var(--border-color)'; cardPerm.style.background = 'var(--bg-surface)'; }
      if (daysGroup) daysGroup.style.display = 'block';
    } else {
      if (cardPerm) { cardPerm.style.borderColor = '#8B5CF6'; cardPerm.style.background = 'rgba(139,92,246,0.08)'; }
      if (cardTemp) { cardTemp.style.borderColor = 'var(--border-color)'; cardTemp.style.background = 'var(--bg-surface)'; }
      if (daysGroup) daysGroup.style.display = 'none';
    }
    calculateFreezePreview();
  }

  function setFreezeDays(days) {
    const input = document.getElementById('tempFreezeDaysInput');
    if (input) {
      input.value = days;
      calculateFreezePreview();
    }
  }

  function calculateFreezePreview() {
    const previewBox = document.getElementById('freezeLivePreviewBox');
    if (!previewBox) return;

    const freezeDateVal = document.getElementById('freezeEffectiveDate')?.value || '<?= date('Y-m-d') ?>';
    const subEndDate = new Date('<?= $activeSub ? $activeSub['end_date'] : date('Y-m-d') ?>');
    const freezeDate = new Date(freezeDateVal);

    const diffTime = subEndDate.getTime() - freezeDate.getTime();
    const remainingDays = Math.max(1, Math.ceil(diffTime / (1000 * 60 * 60 * 24)));

    if (currentFreezeMode === 'temporary') {
      const days = parseInt(document.getElementById('tempFreezeDaysInput')?.value) || 7;
      const unfreezeDt = new Date(freezeDate);
      unfreezeDt.setDate(unfreezeDt.getDate() + days);
      const unfreezeStr = unfreezeDt.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });

      previewBox.style.background = '#EFF6FF';
      previewBox.style.borderColor = '#BFDBFE';
      previewBox.style.color = '#1E40AF';
      previewBox.innerHTML = `
        <strong>⏳ Temporary Freeze Summary:</strong><br>
        • Plan paused from <strong>${freezeDateVal}</strong> for <strong>${days} Days</strong>.<br>
        • Auto-resumes on: <strong style="color:#0284C7;">${unfreezeStr}</strong>.<br>
        • Member's <strong style="color:#059669;">${remainingDays} Balance Days</strong> are safely locked and will be restored.
      `;
    } else {
      previewBox.style.background = '#F5F3FF';
      previewBox.style.borderColor = '#DDD6FE';
      previewBox.style.color = '#5B21B6';
      previewBox.innerHTML = `
        <strong>🔒 Permanent / Indefinite Freeze Summary:</strong><br>
        • Plan paused starting <strong>${freezeDateVal}</strong> indefinitely.<br>
        • Duration: <strong>Time unknown (till manual unfreeze)</strong>.<br>
        • Member's <strong style="color:#059669;">${remainingDays} Balance Days</strong> will be locked. When you click <em>Reactivate / Unfreeze</em> in the future, these ${remainingDays} days will start counting from that day!
      `;
    }
  }

  // Trigger calculation when freeze modal opens
  const origOpenFreeze = window.openFreezeModal;
  window.openFreezeModal = function() {
    openModal('freezeMembershipModal');
    selectFreezeType('temporary');
  };

  function submitFreeze(e) {
    e.preventDefault();
    const formData = new FormData(e.target);
    formData.append('action', 'freeze');

    fetch('api/memberships.php', {
      method: 'POST',
      body: formData
    })
      .then(res => res.text())
      .then(text => {
        try { return JSON.parse(text); } catch (e) { return { success: false, message: text }; }
      })
      .then(res => {
        if (res.success) {
          showToast(res.message, 'success');
          closeModal('freezeMembershipModal');
          setTimeout(() => location.reload(), 1200);
        } else {
          showToast(res.message || 'Freeze failed', 'danger');
        }
      });
  }

  function unfreezeMembership(subId) {
    if (confirm(`Reactivate & resume membership for <?= htmlspecialchars(addslashes($member['name'])) ?>?`)) {
      fetch('api/memberships.php?action=unfreeze', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ subscription_id: subId, member_id: <?= $member['id'] ?> })
      })
        .then(res => res.text())
        .then(text => {
          try { return JSON.parse(text); } catch (e) { return { success: false, message: text }; }
        })
        .then(res => {
          if (res.success) {
            showToast(res.message || 'Membership Resumed Successfully!', 'success');
            setTimeout(() => location.reload(), 1200);
          } else {
            showToast(res.message || 'Unfreeze failed', 'danger');
          }
        });
    }
  }

  function submitRefund(e) {
    e.preventDefault();
    const formData = new FormData(e.target);
    formData.append('action', 'process_refund');

    fetch('api/refunds.php', {
      method: 'POST',
      body: formData
    })
      .then(res => res.text())
      .then(text => {
        try { return JSON.parse(text); } catch (e) { return { success: false, message: text }; }
      })
      .then(res => {
        showToast(res.message || 'Refund processed', 'success');
        closeModal('refundModal');
        setTimeout(() => location.reload(), 1200);
      });
  }

  function openProfileVoidBillModal(saleId, invoiceNo, amount) {
    document.getElementById('profileVoidSaleId').value = saleId;
    document.getElementById('profileVoidInvoiceNo').innerText = invoiceNo;
    document.getElementById('profileVoidAmountDisplay').innerText = '<?= $currency ?>' + parseFloat(amount).toFixed(2);
    document.getElementById('profileVoidCustomReason').value = '';
    openModal('profileVoidBillModal');
  }

  function submitProfileVoidBill() {
    const saleId = document.getElementById('profileVoidSaleId').value;
    const reasonSelect = document.getElementById('profileVoidReasonSelect').value;
    const customReason = document.getElementById('profileVoidCustomReason').value.trim();
    const finalReason = customReason ? `${reasonSelect}: ${customReason}` : reasonSelect;

    fetch('api/pos.php?action=void_invoice', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        sale_id: saleId,
        reason: finalReason
      })
    })
      .then(res => res.text())
      .then(text => {
        try { return JSON.parse(text); } catch (e) { return { success: false, message: text }; }
      })
      .then(res => {
        if (res.success) {
          showToast(res.message, 'success');
          closeModal('profileVoidBillModal');
          setTimeout(() => location.reload(), 1000);
        } else {
          showToast(res.message || 'Failed to void invoice', 'danger');
        }
      });
  }

  function handlePoolPlanSelect(selectEl) {
    const selectedOpt = selectEl.options[selectEl.selectedIndex];
    if (!selectedOpt || !selectedOpt.value) return;

    const price = parseFloat(selectedOpt.getAttribute('data-price')) || 0;
    const days = parseInt(selectedOpt.getAttribute('data-days')) || 30;
    const slot = selectedOpt.getAttribute('data-slot') || 'Morning 6:00 AM - 10:00 AM';

    document.getElementById('assignPoolPrice').value = price;

    const startVal = document.getElementById('assignPoolStartDate').value || new Date().toISOString().slice(0, 10);
    const startDt = new Date(startVal);
    startDt.setDate(startDt.getDate() + days);
    document.getElementById('assignPoolEndDate').value = startDt.toISOString().slice(0, 10);
  }

  function calcPoolEndDate() {
    const selectEl = document.getElementById('assignPoolPlanId');
    if (selectEl) handlePoolPlanSelect(selectEl);
  }

  function submitAssignPoolPass() {
    const memberId = document.getElementById('poolMemberId').value;
    const planId = document.getElementById('assignPoolPlanId').value;
    const slot = document.getElementById('assignPoolSlot').value;
    const startDate = document.getElementById('assignPoolStartDate').value;
    const endDate = document.getElementById('assignPoolEndDate').value;
    const price = document.getElementById('assignPoolPrice').value;

    if (!planId || !startDate) {
      showToast('Please select a plan and start date', 'warning');
      return;
    }

    fetch('api/pool.php?action=assign_pass', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        member_id: memberId,
        pool_plan_id: planId,
        slot_assigned: slot,
        start_date: startDate,
        end_date: endDate,
        price_paid: price
      })
    })
      .then(res => res.text())
      .then(text => {
        try { return JSON.parse(text); } catch (e) { return { success: false, message: text }; }
      })
      .then(res => {
        if (res.success) {
          showToast(res.message || 'Swimming Pool Pass Assigned Successfully!', 'success');
          closeModal('assignMemberPoolModal');
          setTimeout(() => location.reload(), 1000);
        } else {
          showToast(res.message || 'Failed to assign pool pass', 'danger');
        }
      });
  }
</script>