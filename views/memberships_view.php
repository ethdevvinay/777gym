<!-- Membership Plans & Package Configuration Hub View -->
<?php
$db = getDB();

// Fetch plans and active subscriber counts
$plans = $db->query("
    SELECT mt.*, 
           (SELECT COUNT(*) FROM member_subscriptions ms WHERE ms.membership_type_id = mt.id AND ms.status = 'active') as active_subscribers_count
    FROM membership_types mt 
    ORDER BY mt.duration_days ASC, mt.id ASC
")->fetchAll();

$totalPlans = count($plans);
$activePlansCount = 0;
$totalSubscribers = 0;
$trialPlansCount = 0;

foreach ($plans as $p) {
  if ($p['status'] === 'active')
    $activePlansCount++;
  $totalSubscribers += intval($p['active_subscribers_count'] ?? 0);
  $days = intval($p['duration_days'] ?: ($p['duration_months'] * 30));
  if ($days < 30)
    $trialPlansCount++;
}

$currency = getSetting('currency_symbol', '₹');
?>

<div class="page-content">
  <!-- Header & Actions -->
  <div
    style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1.25rem; margin-bottom:1.5rem;">
    <div>
      <h1
        style="font-size:1.65rem; font-weight:900; color:var(--text-primary); letter-spacing:-0.03em; display:flex; align-items:center; gap:0.65rem;">
        <span
          style="background:#EFF6FF; color:var(--primary); width:42px; height:42px; border-radius:12px; display:inline-flex; align-items:center; justify-content:center; font-size:1.4rem; box-shadow:0 2px 8px var(--primary-glow);">🏋️</span>
        <span>Membership Plans &amp; Packages</span>
      </h1>
      <p style="font-size:0.85rem; color:var(--text-secondary); margin-top:0.25rem;">
        Configure gym passes, trial periods, annual discounts, freeze limits &amp; selling prices for Touch POS
      </p>
    </div>

    <div style="display:flex; gap:0.65rem; flex-wrap:wrap;">
      <a href="index.php?page=pos" class="btn btn-outline"
        style="border-radius:var(--radius-md); font-weight:700; background:var(--bg-surface);">
        🛒 Touch POS Catalog
      </a>
      <button class="btn btn-primary"
        style="border-radius:var(--radius-md); font-weight:800; box-shadow:0 4px 12px var(--primary-glow);"
        onclick="openCreatePlanModal()">
        + Create Custom Plan
      </button>
    </div>
  </div>

  <!-- Executive Metric Summary Cards -->
  <div
    style="display:grid; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:1.25rem; margin-bottom:1.75rem;">
    <div class="card"
      style="border-left:4px solid var(--primary); padding:1.25rem; border-radius:var(--radius-lg); box-shadow:var(--shadow-card);">
      <div
        style="font-size:0.75rem; font-weight:800; text-transform:uppercase; color:var(--text-muted); letter-spacing:0.05em;">
        Total Gym Plans</div>
      <div
        style="font-size:1.85rem; font-weight:900; color:var(--primary); margin-top:0.35rem; font-family:var(--font-heading);">
        <?= $totalPlans ?> Protocols</div>
      <div style="font-size:0.78rem; color:var(--text-secondary); margin-top:0.2rem;"><?= $activePlansCount ?> Active in
        POS Catalog</div>
    </div>

    <div class="card"
      style="border-left:4px solid var(--success); padding:1.25rem; border-radius:var(--radius-lg); box-shadow:var(--shadow-card);">
      <div
        style="font-size:0.75rem; font-weight:800; text-transform:uppercase; color:var(--text-muted); letter-spacing:0.05em;">
        Active Subscribers</div>
      <div
        style="font-size:1.85rem; font-weight:900; color:var(--success); margin-top:0.35rem; font-family:var(--font-heading);">
        <?= $totalSubscribers ?> Members</div>
      <div style="font-size:0.78rem; color:var(--text-secondary); margin-top:0.2rem;">Currently holding active
        subscriptions</div>
    </div>

    <div class="card"
      style="border-left:4px solid #3B82F6; padding:1.25rem; border-radius:var(--radius-lg); box-shadow:var(--shadow-card);">
      <div
        style="font-size:0.75rem; font-weight:800; text-transform:uppercase; color:var(--text-muted); letter-spacing:0.05em;">
        Short &amp; Trial Passes</div>
      <div
        style="font-size:1.85rem; font-weight:900; color:#3B82F6; margin-top:0.35rem; font-family:var(--font-heading);">
        <?= $trialPlansCount ?> Short Passes</div>
      <div style="font-size:0.78rem; color:var(--text-secondary); margin-top:0.2rem;">1 to 15 Days conversion trials
      </div>
    </div>

    <div class="card"
      style="border-left:4px solid #F59E0B; padding:1.25rem; border-radius:var(--radius-lg); box-shadow:var(--shadow-card);">
      <div
        style="font-size:0.75rem; font-weight:800; text-transform:uppercase; color:var(--text-muted); letter-spacing:0.05em;">
        Standard Freeze Policy</div>
      <div
        style="font-size:1.85rem; font-weight:900; color:#F59E0B; margin-top:0.35rem; font-family:var(--font-heading);">
        1 to 4 Times</div>
      <div style="font-size:0.78rem; color:var(--text-secondary); margin-top:0.2rem;">Tiered automated freeze engine
      </div>
    </div>
  </div>

  <!-- Search & Filter Controls Bar -->
  <div class="card"
    style="padding:1rem 1.25rem; margin-bottom:1.5rem; border-radius:var(--radius-lg); box-shadow:var(--shadow-subtle);">
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem;">
      <div style="display:flex; gap:0.45rem; flex-wrap:wrap;" id="planFilterGroup">
        <button class="plan-filter-chip active" onclick="filterPlans('all', this)">All Plans
          (<?= $totalPlans ?>)</button>
        <button class="plan-filter-chip" onclick="filterPlans('gym', this)">🏋️ Gym</button>
        <button class="plan-filter-chip" onclick="filterPlans('pool', this)">🏊 Pool</button>
        <button class="plan-filter-chip" onclick="filterPlans('steam', this)">🧖 Steam</button>
        <button class="plan-filter-chip" onclick="filterPlans('sauna', this)">♨️ Sauna</button>
        <button class="plan-filter-chip" onclick="filterPlans('vip', this)">👑 VIP Combo</button>
        <button class="plan-filter-chip" onclick="filterPlans('trial', this)">⚡ Trial (<30d)< /button>
      </div>

      <div style="position:relative; min-width:260px;">
        <input type="text" id="planSearchInput" class="form-control" placeholder="🔍 Search plan title, days, price..."
          oninput="searchPlansGrid(this.value)"
          style="padding-left:2.2rem; height:38px; border-radius:var(--radius-full); font-size:0.82rem;">
      </div>
    </div>
  </div>

  <!-- Membership Plans Cards Grid -->
  <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(320px, 1fr)); gap:1.35rem;"
    id="plansCardsGrid">
    <?php foreach ($plans as $plan): ?>
      <?php
      $months = intval($plan['duration_months'] ?? 0);
      $days = intval($plan['duration_days'] ?: ($months * 30));
      $sType = $plan['service_type'] ?? 'gym';

      $defaultFreeze = 0;
      if ($days >= 360 || $months >= 12)
        $defaultFreeze = 4;
      elseif ($days >= 180 || $months >= 6)
        $defaultFreeze = 3;
      elseif ($days >= 90 || $months >= 3)
        $defaultFreeze = 2;
      elseif ($days >= 30 || $months >= 1)
        $defaultFreeze = 1;

      $freezeLimit = ($plan['max_freeze_count'] !== null) ? intval($plan['max_freeze_count']) : $defaultFreeze;
      $durationLabel = ($months > 0) ? "{$months} Mo ({$days} Days)" : ($days === 1 ? "1 Day Pass" : "{$days} Days Pass");

      $isShortTrial = ($days < 30);
      $isAnnual = ($months >= 12 || $days >= 300);
      $isQuarterly = ($months >= 3 && $months < 12);

      $themeBorder = ($sType === 'pool') ? '#0284C7' : (($sType === 'steam') ? '#0EA5E9' : (($sType === 'sauna') ? '#F59E0B' : (($sType === 'vip_combo') ? '#DB2777' : ($isAnnual ? '#F59E0B' : 'var(--primary)'))));
      $activeSubscribers = intval($plan['active_subscribers_count'] ?? 0);
      ?>
      <div class="card plan-card-item" data-service="<?= $sType ?>" data-category="<?= $plan['category'] ?>"
        data-status="<?= $plan['status'] ?>" data-name="<?= htmlspecialchars(strtolower($plan['title'])) ?>"
        data-days="<?= $days ?>" data-price="<?= $plan['price'] ?>"
        style="border-top:5px solid <?= $themeBorder ?>; border-radius:var(--radius-lg); box-shadow:var(--shadow-card); display:flex; flex-direction:column; justify-content:space-between; position:relative; overflow:hidden; transition:all var(--transition-fast);">

        <?php if ($sType === 'vip_combo'): ?>
          <div
            style="position:absolute; top:0; right:0; background:linear-gradient(135deg, #DB2777 0%, #BE185D 100%); color:#fff; font-size:0.68rem; font-weight:900; padding:0.25rem 0.75rem; border-bottom-left-radius:8px; text-transform:uppercase; letter-spacing:0.04em;">
            👑 ALL-INCLUSIVE VIP
          </div>
        <?php elseif ($isAnnual): ?>
          <div
            style="position:absolute; top:0; right:0; background:linear-gradient(135deg, #F59E0B 0%, #D97706 100%); color:#fff; font-size:0.68rem; font-weight:900; padding:0.25rem 0.75rem; border-bottom-left-radius:8px; text-transform:uppercase; letter-spacing:0.04em;">
            ⭐ BEST VALUE
          </div>
        <?php elseif ($isShortTrial): ?>
          <div
            style="position:absolute; top:0; right:0; background:#0284C7; color:#fff; font-size:0.68rem; font-weight:900; padding:0.25rem 0.75rem; border-bottom-left-radius:8px; text-transform:uppercase; letter-spacing:0.04em;">
            ⚡ TRIAL / PASS
          </div>
        <?php endif; ?>

        <div>
          <!-- Plan Title & Tag -->
          <div
            style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:0.75rem; margin-top:<?= ($isAnnual || $isShortTrial || $sType === 'vip_combo') ? '0.5rem' : '0' ?>;">
            <div>
              <strong
                style="font-size:1.25rem; font-weight:900; color:var(--text-primary); display:block; letter-spacing:-0.02em;"><?= htmlspecialchars($plan['title']) ?></strong>
              <span
                style="font-size:0.75rem; color:var(--text-muted); text-transform:uppercase; font-weight:800; letter-spacing:0.04em;"><?= strtoupper($sType) ?>
                &bull; <?= htmlspecialchars($plan['category']) ?></span>
            </div>
            <span class="badge badge-<?= $plan['status'] === 'active' ? 'success' : 'secondary' ?>"
              style="font-weight:800; font-size:0.72rem;">
              ● <?= strtoupper($plan['status']) ?>
            </span>
          </div>

          <!-- Price & Duration Big Banner -->
          <div
            style="background:var(--bg-main); border:1px solid var(--border-color); border-radius:var(--radius-md); padding:0.85rem 1rem; margin-bottom:1rem; display:flex; justify-content:space-between; align-items:baseline;">
            <div>
              <span
                style="font-size:1.85rem; font-weight:900; color:var(--primary); font-family:var(--font-heading);"><?= $currency ?><?= number_format($plan['price'], 2) ?></span>
              <?php if (!empty($plan['original_price']) && $plan['original_price'] > $plan['price']): ?>
                <del
                  style="font-size:0.9rem; color:var(--text-muted); margin-left:0.35rem;">₹<?= number_format($plan['original_price'], 0) ?></del>
              <?php endif; ?>
              <span style="font-size:0.82rem; font-weight:700; color:var(--text-muted);">/ <?= $durationLabel ?></span>
            </div>
            <div style="font-size:0.75rem; font-weight:800; color:var(--text-secondary); font-family:var(--font-mono);">
              <?= $activeSubscribers ?> Active Member<?= $activeSubscribers !== 1 ? 's' : '' ?>
            </div>
          </div>

          <?php if (!empty($plan['free_gifts'])): ?>
            <div
              style="background:#ECFDF5; border:1px solid #A7F3D0; border-radius:8px; padding:0.55rem 0.75rem; margin-bottom:0.85rem; font-size:0.78rem; color:#065F46; font-weight:700; display:flex; align-items:center; gap:0.4rem;">
              <span>🎁</span>
              <span><strong>Free Gifts:</strong> <?= htmlspecialchars($plan['free_gifts']) ?></span>
            </div>
          <?php endif; ?>

          <?php if (!empty($plan['coupons_count']) && $plan['coupons_count'] > 0): ?>
            <div
              style="background:#E0F2FE; border:1px solid #BAE6FD; border-radius:8px; padding:0.45rem 0.75rem; margin-bottom:0.85rem; font-size:0.78rem; color:#0369A1; font-weight:700; display:flex; align-items:center; gap:0.4rem;">
              <span>🎟️</span>
              <span><strong>Session Coupons:</strong> <?= $plan['coupons_count'] ?> Coupons Included</span>
            </div>
          <?php endif; ?>

          <!-- Plan Features & Benefits Ledger -->
          <div
            style="display:flex; flex-direction:column; gap:0.5rem; font-size:0.82rem; color:var(--text-secondary); background:var(--bg-surface-secondary); padding:0.85rem; border-radius:10px; margin-bottom:1.25rem; border:1px solid var(--border-light);">
            <div style="display:flex; justify-content:space-between;">
              <span>📝 <strong>Registration Fee:</strong></span>
              <span
                class="font-mono"><?= $plan['reg_fee'] > 0 ? $currency . number_format($plan['reg_fee'], 2) : 'Free / Waived' ?></span>
            </div>

            <!-- Freeze Limit Badge Display -->
            <div
              style="display:flex; justify-content:space-between; align-items:center; background:<?= $freezeLimit > 0 ? '#FFFBEB' : '#F1F5F9' ?>; padding:0.35rem 0.55rem; border-radius:6px; border:1px solid <?= $freezeLimit > 0 ? '#FDE68A' : '#E2E8F0' ?>;">
              <span style="color:<?= $freezeLimit > 0 ? '#92400E' : '#64748B' ?>; font-weight:800; font-size:0.78rem;">❄️
                Freeze Allowed:</span>
              <strong style="color:<?= $freezeLimit > 0 ? '#B45309' : '#64748B' ?>; font-size:0.82rem;">
                <?= $freezeLimit > 0 ? "{$freezeLimit} Times ({$freezeLimit} Bar)" : "No Freezes (Trial)" ?>
              </strong>
            </div>

            <div style="display:flex; justify-content:space-between;">
              <span>⏱️ <strong>Access Timing:</strong></span>
              <span><?= htmlspecialchars($plan['access_timing'] ?: 'Full Day (05:00 AM - 10:00 PM)') ?></span>
            </div>

            <div style="border-top:1px dashed var(--border-color); padding-top:0.4rem; font-size:0.78rem;">
              ⚡ <strong>Inclusions:</strong>
              <?= htmlspecialchars($plan['included_services'] ?: 'Gym Floor, Cardio Zone, Steam Bath') ?>
            </div>
          </div>
        </div>

        <!-- Card Action Triggers -->
        <div style="display:flex; gap:0.5rem; margin-top:0.5rem;">
          <button type="button" class="btn btn-secondary btn-block" style="font-weight:700; font-size:0.82rem;"
            onclick="openEditPlanModal(<?= htmlspecialchars(json_encode($plan)) ?>)">
            ✏️ Edit Plan
          </button>
          <a href="index.php?page=pos" class="btn btn-primary btn-block"
            style="font-weight:800; font-size:0.82rem; text-decoration:none; text-align:center;">
            🛒 Sell in POS
          </a>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<!-- ========================================================= -->
<!-- ADD / EDIT PLAN MODAL                                     -->
<!-- ========================================================= -->
<div class="modal" id="planModal"
  style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.55); z-index:100; align-items:center; justify-content:center; backdrop-filter:blur(2px);">
  <div class="modal-overlay" style="position:absolute; inset:0;" onclick="closeModal('planModal')"></div>
  <div class="card"
    style="position:relative; z-index:10; background:#fff; width:92%; max-width:540px; border-radius:18px; padding:1.5rem; border-top:5px solid var(--primary); box-shadow:var(--shadow-modal);">
    <div
      style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.25rem; border-bottom:1px solid var(--border-color); padding-bottom:0.75rem;">
      <span id="planModalTitle" style="font-size:1.15rem; font-weight:800; color:var(--text-primary);">➕ Create
        Membership Plan</span>
      <button style="background:none; border:none; font-size:1.2rem; cursor:pointer;"
        onclick="closeModal('planModal')">✕</button>
    </div>

    <form onsubmit="savePlan(event)">
      <input type="hidden" name="id" id="planId" value="0">

      <div class="form-group">
        <label class="form-label">Plan Title *</label>
        <input type="text" name="title" id="planTitleInput" class="form-control"
          placeholder="e.g. 1 Year VIP Gold Annual Pass" required>
      </div>

      <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.75rem;">
        <div class="form-group">
          <label class="form-label">Category</label>
          <select name="category" id="planCategorySelect" class="form-control">
            <option value="trial">Trial / Short Pass</option>
            <option value="monthly">Monthly</option>
            <option value="quarterly">Quarterly</option>
            <option value="half_yearly">Half-Yearly</option>
            <option value="yearly">Yearly / Annual</option>
            <option value="couple">Couple Membership</option>
            <option value="student">Student Special</option>
            <option value="corporate">Corporate / VIP</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Duration Days (Total Days) *</label>
          <input type="number" name="duration_days" id="planDurationDays" class="form-control font-mono" value="30"
            min="1" required oninput="onDurationDaysChange()">
        </div>
      </div>

      <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.75rem;">
        <div class="form-group">
          <label class="form-label">Duration (Months Equivalent)</label>
          <input type="number" name="duration_months" id="planDurationMonths" class="form-control font-mono" value="1"
            min="0" oninput="onDurationMonthsInput()">
        </div>
        <div class="form-group">
          <label class="form-label">Selling Price (<?= $currency ?>) *</label>
          <input type="number" step="0.01" name="price" id="planPriceInput" class="form-control font-mono"
            placeholder="1999" required style="font-weight:800; font-size:1.1rem; color:var(--primary);">
        </div>
      </div>

      <!-- FREEZE LIMIT CONFIGURATION SECTION -->
      <div
        style="background:#FFFBEB; border:1px solid #FDE68A; border-radius:10px; padding:0.85rem; margin-bottom:1rem;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.35rem;">
          <label style="font-size:0.82rem; font-weight:800; color:#92400E; margin:0;">
            ❄️ Max Freeze Times Allowed (Freeze Count Limit) *
          </label>
          <span style="font-size:0.75rem; color:#B45309; font-weight:700;" id="freezeCountRecommendation">Auto
            suggested: 1 Bar</span>
        </div>
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.75rem; align-items:center;">
          <div>
            <input type="number" name="max_freeze_count" id="planMaxFreezeCount" class="form-control font-mono"
              value="1" min="0" max="20" required style="font-weight:800; font-size:1.1rem; color:#B45309;">
          </div>
          <div style="font-size:0.74rem; color:#78350F; line-height:1.3;">
            💡 <em>Rule: Trial (<30d)=0 bar, 1M=1 bar, 3M=2 bar, 6M=3 bar, 12M=4 bar.</em>
          </div>
        </div>
      </div>

      <div class="form-group">
        <label class="form-label">Included Amenities / Facilities</label>
        <input type="text" name="included_services" id="planServicesInput" class="form-control"
          value="Gym Floor, Cardio Zone, Steam Bath, Locker Facility">
      </div>

      <div class="form-group">
        <label class="form-label">Plan Status</label>
        <select name="status" id="planStatusSelect" class="form-control">
          <option value="active">Active (Visible in Touch POS)</option>
          <option value="inactive">Inactive (Hidden from POS)</option>
        </select>
      </div>

      <div style="display:flex; gap:0.75rem; margin-top:1.5rem;">
        <button type="button" class="btn btn-secondary btn-block" onclick="closeModal('planModal')">Cancel</button>
        <button type="submit" class="btn btn-primary btn-block" id="planSubmitBtn">✓ SAVE PLAN CONFIGURATION</button>
      </div>
    </form>
  </div>
</div>

<style>
  .plan-filter-chip {
    background: var(--bg-surface-secondary);
    border: 1px solid var(--border-color);
    color: var(--text-secondary);
    padding: 0.35rem 0.75rem;
    border-radius: var(--radius-full);
    font-size: 0.76rem;
    font-weight: 700;
    cursor: pointer;
    transition: all var(--transition-fast);
  }

  .plan-filter-chip:hover {
    background: var(--bg-surface-hover);
    color: var(--primary);
    border-color: var(--primary-border);
  }

  .plan-filter-chip.active {
    background: var(--primary);
    color: #fff;
    border-color: var(--primary);
    box-shadow: 0 2px 6px var(--primary-glow);
  }

  .plan-card-item:hover {
    transform: translateY(-3px);
    box-shadow: 0 12px 24px -6px rgba(0, 0, 0, 0.08), var(--shadow-card);
  }
</style>

<script>
  let currentPlanCategory = 'all';

  function filterPlans(cat, btn) {
    currentPlanCategory = cat;
    document.querySelectorAll('#planFilterGroup .plan-filter-chip').forEach(b => b.classList.remove('active'));
    if (btn) btn.classList.add('active');
    applyPlanFilters();
  }

  function searchPlansGrid(query) {
    applyPlanFilters();
  }

  function applyPlanFilters() {
    const query = (document.getElementById('planSearchInput').value || '').toLowerCase().trim();
    const cards = document.querySelectorAll('#plansCardsGrid .plan-card-item');

    cards.forEach(card => {
      const cat = card.getAttribute('data-category') || '';
      const st = card.getAttribute('data-status') || '';
      const name = card.getAttribute('data-name') || '';
      const days = card.getAttribute('data-days') || '';
      const price = card.getAttribute('data-price') || '';

      const srv = card.dataset.service;
      let matchesCategory = true;
      if (currentPlanCategory === 'trial') matchesCategory = (cat === 'trial' || parseInt(days) < 30);
      else if (currentPlanCategory === 'gym') matchesCategory = (srv === 'gym' || !srv);
      else if (currentPlanCategory === 'pool') matchesCategory = (srv === 'pool');
      else if (currentPlanCategory === 'steam') matchesCategory = (srv === 'steam');
      else if (currentPlanCategory === 'sauna') matchesCategory = (srv === 'sauna');
      else if (currentPlanCategory === 'vip') matchesCategory = (srv === 'vip_combo' || cat === 'vip');
      else if (currentPlanCategory === 'active') matchesCategory = (st === 'active');

      const matchesSearch = !query || name.includes(query) || days.includes(query) || price.includes(query);

      if (matchesCategory && matchesSearch) {
        card.style.display = 'flex';
      } else {
        card.style.display = 'none';
      }
    });
  }

  function onDurationDaysChange() {
    const days = parseInt(document.getElementById('planDurationDays').value) || 1;
    const monthsInput = document.getElementById('planDurationMonths');
    if (days < 30) {
      if (monthsInput) monthsInput.value = 0;
    } else {
      if (monthsInput) monthsInput.value = Math.round(days / 30);
    }
    updateFreezeSuggestion();
  }

  function onDurationMonthsInput() {
    const months = parseInt(document.getElementById('planDurationMonths').value) || 0;
    const daysInput = document.getElementById('planDurationDays');
    if (months > 0 && daysInput) {
      daysInput.value = (months === 12) ? 365 : (months * 30);
    }
    updateFreezeSuggestion();
  }

  function updateFreezeSuggestion() {
    const days = parseInt(document.getElementById('planDurationDays').value) || 1;
    const months = parseInt(document.getElementById('planDurationMonths').value) || 0;

    let recommended = 0;
    if (days < 30 && months === 0) recommended = 0;
    else if (months === 1 || (days >= 28 && days <= 45)) recommended = 1;
    else if (months === 3 || (days > 45 && days <= 120)) recommended = 2;
    else if (months === 6 || (days > 120 && days <= 240)) recommended = 3;
    else if (months >= 12 || days >= 300) recommended = 4;
    else recommended = Math.min(6, Math.ceil(days / 90));

    const freezeInput = document.getElementById('planMaxFreezeCount');
    if (freezeInput && (!freezeInput.dataset.manualEdited || freezeInput.dataset.manualEdited === 'false')) {
      freezeInput.value = recommended;
    }
    const label = document.getElementById('freezeCountRecommendation');
    if (label) label.innerText = `Auto suggested: ${recommended} Bar (${recommended} Time${recommended > 1 ? 's' : ''})`;
  }

  function openCreatePlanModal() {
    document.getElementById('planId').value = 0;
    document.getElementById('planModalTitle').innerText = '➕ Create Membership Plan';
    document.getElementById('planTitleInput').value = '';
    document.getElementById('planDurationDays').value = 30;
    document.getElementById('planDurationMonths').value = 1;
    document.getElementById('planPriceInput').value = '';
    document.getElementById('planMaxFreezeCount').value = 1;
    document.getElementById('planMaxFreezeCount').dataset.manualEdited = 'false';
    document.getElementById('planServicesInput').value = 'Gym Floor, Cardio Zone, Steam Bath, Locker Facility';
    document.getElementById('planStatusSelect').value = 'active';
    updateFreezeSuggestion();
    openModal('planModal');
  }

  function openEditPlanModal(plan) {
    document.getElementById('planId').value = plan.id;
    document.getElementById('planModalTitle').innerText = '✏️ Edit Membership Plan: ' + plan.title;
    document.getElementById('planTitleInput').value = plan.title || '';
    document.getElementById('planCategorySelect').value = plan.category || 'monthly';
    document.getElementById('planDurationDays').value = plan.duration_days || (plan.duration_months * 30);
    document.getElementById('planDurationMonths').value = plan.duration_months || 0;
    document.getElementById('planPriceInput').value = plan.price || '';

    const days = parseInt(plan.duration_days) || 30;
    const def = (days < 30 ? 0 : (days <= 45 ? 1 : (days <= 120 ? 2 : (days <= 240 ? 3 : 4))));
    document.getElementById('planMaxFreezeCount').value = (plan.max_freeze_count !== undefined && plan.max_freeze_count !== null) ? plan.max_freeze_count : def;
    document.getElementById('planMaxFreezeCount').dataset.manualEdited = 'true';

    document.getElementById('planServicesInput').value = plan.included_services || '';
    document.getElementById('planStatusSelect').value = plan.status || 'active';
    openModal('planModal');
  }

  function savePlan(e) {
    e.preventDefault();
    const formData = new FormData(e.target);
    formData.append('action', 'save_plan');

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
          showToast(res.message || 'Plan saved successfully!', 'success');
          closeModal('planModal');
          setTimeout(() => location.reload(), 1000);
        } else {
          showToast(res.message || 'Saving failed', 'danger');
        }
      })
      .catch(err => {
        showToast('Network error while saving plan', 'danger');
      });
  }
</script>