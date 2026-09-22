<!-- Trial & Short Pass Members Management / Lead Conversion Hub -->
<?php
$db = getDB();

// Fetch trial plans (duration_days < 30 or category = 'trial')
$trialPlans = $db->query("
    SELECT * FROM membership_types 
    WHERE duration_days < 30 OR category = 'trial' 
    ORDER BY duration_days ASC
")->fetchAll();

// Fetch regular upgrade plans (1 month, 3 months, 6 months, 1 year, etc.)
$regularPlans = $db->query("
    SELECT * FROM membership_types 
    WHERE duration_days >= 30 AND status = 'active' 
    ORDER BY duration_days ASC, price ASC
")->fetchAll();

// Fetch all members with trial passes (current or past)
$trialMembers = $db->query("
    SELECT m.*, ms.id as sub_id, ms.start_date, ms.end_date as plan_expiry, ms.status as sub_status,
           mt.title as plan_title, mt.duration_days, mt.price as plan_price,
           (SELECT COUNT(*) FROM attendance a WHERE a.member_id = m.id AND a.check_in_time >= ms.start_date AND a.check_in_time <= CONCAT(ms.end_date, ' 23:59:59')) as trial_punches,
           (SELECT COUNT(*) FROM member_subscriptions ms2 JOIN membership_types mt2 ON ms2.membership_type_id = mt2.id WHERE ms2.member_id = m.id AND mt2.duration_days >= 30 AND ms2.status = 'active') as has_full_plan
    FROM members m
    JOIN member_subscriptions ms ON m.id = ms.member_id
    JOIN membership_types mt ON ms.membership_type_id = mt.id
    WHERE (mt.duration_days < 30 OR mt.category = 'trial')
    ORDER BY ms.id DESC
")->fetchAll();

$totalTrials = count($trialMembers);
$activeTrialsCount = 0;
$expiringSoonCount = 0;
$expiredTrialsCount = 0;
$convertedCount = 0;

$todayTs = strtotime('today');

foreach ($trialMembers as $tm) {
    $hasFull = intval($tm['has_full_plan'] ?? 0) > 0;
    if ($hasFull) {
        $convertedCount++;
    }

    if (!empty($tm['plan_expiry'])) {
        $daysLeft = ceil((strtotime($tm['plan_expiry']) - $todayTs) / 86400);
        if ($daysLeft >= 0) {
            $activeTrialsCount++;
            if ($daysLeft <= 2) {
                $expiringSoonCount++;
            }
        } else {
            $expiredTrialsCount++;
        }
    }
}

$currency = getSetting('currency_symbol', '₹');
?>

<div class="page-content">
  <!-- Header & Primary Actions -->
  <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1.25rem; margin-bottom:1.5rem;">
    <div>
      <h1 style="font-size:1.65rem; font-weight:900; color:var(--text-primary); letter-spacing:-0.03em; display:flex; align-items:center; gap:0.65rem;">
        <span style="background:#E0F2FE; color:#0284C7; width:42px; height:42px; border-radius:12px; display:inline-flex; align-items:center; justify-content:center; font-size:1.4rem; box-shadow:0 2px 8px rgba(2,132,199,0.2);">⚡</span>
        <span>Trial Passes &amp; Lead Conversions</span>
      </h1>
      <p style="font-size:0.85rem; color:var(--text-secondary); margin-top:0.25rem;">
        Dedicated roster for 1 to 15 Days trial clients, attendance check-ins &amp; proactive WhatsApp conversion pitches
      </p>
    </div>

    <div style="display:flex; gap:0.65rem; flex-wrap:wrap;">
      <button class="btn btn-outline" style="border-radius:var(--radius-md); font-weight:700; background:var(--bg-surface);" onclick="exportTrialsCsv()">
        📥 Export CSV
      </button>
      <button class="btn btn-primary" style="border-radius:var(--radius-md); font-weight:800; background:#0284C7; border-color:#0284C7; box-shadow:0 4px 12px rgba(2,132,199,0.25);" onclick="openModal('newTrialModal')">
        ⚡ + Register New Trial Client
      </button>
    </div>
  </div>

  <!-- Executive Metric Summary Cards -->
  <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:1.25rem; margin-bottom:1.75rem;">
    <div class="card" style="border-left:4px solid #0284C7; padding:1.25rem; border-radius:var(--radius-lg); box-shadow:var(--shadow-card);">
      <div style="font-size:0.75rem; font-weight:800; text-transform:uppercase; color:var(--text-muted); letter-spacing:0.05em;">Active Trial Clients</div>
      <div style="font-size:1.85rem; font-weight:900; color:#0284C7; margin-top:0.35rem; font-family:var(--font-heading);"><?= $activeTrialsCount ?> Clients</div>
      <div style="font-size:0.78rem; color:var(--text-secondary); margin-top:0.2rem;">Currently utilizing short trial passes</div>
    </div>

    <div class="card" style="border-left:4px solid #DC2626; padding:1.25rem; border-radius:var(--radius-lg); box-shadow:var(--shadow-card);">
      <div style="font-size:0.75rem; font-weight:800; text-transform:uppercase; color:var(--text-muted); letter-spacing:0.05em;">Expiring in 24-48 Hours</div>
      <div style="font-size:1.85rem; font-weight:900; color:#DC2626; margin-top:0.35rem; font-family:var(--font-heading);"><?= $expiringSoonCount ?> Follow-ups</div>
      <div style="font-size:0.78rem; color:var(--text-secondary); margin-top:0.2rem; font-weight:700;">Critical membership conversion window</div>
    </div>

    <div class="card" style="border-left:4px solid var(--success); padding:1.25rem; border-radius:var(--radius-lg); box-shadow:var(--shadow-card);">
      <div style="font-size:0.75rem; font-weight:800; text-transform:uppercase; color:var(--text-muted); letter-spacing:0.05em;">Converted to Full Plans</div>
      <div style="font-size:1.85rem; font-weight:900; color:var(--success); margin-top:0.35rem; font-family:var(--font-heading);"><?= $convertedCount ?> Upgrades</div>
      <div style="font-size:0.78rem; color:var(--text-secondary); margin-top:0.2rem;">Successfully converted to monthly/annual</div>
    </div>

    <div class="card" style="border-left:4px solid #64748B; padding:1.25rem; border-radius:var(--radius-lg); box-shadow:var(--shadow-card);">
      <div style="font-size:0.75rem; font-weight:800; text-transform:uppercase; color:var(--text-muted); letter-spacing:0.05em;">Total Trial Database</div>
      <div style="font-size:1.85rem; font-weight:900; color:#475569; margin-top:0.35rem; font-family:var(--font-heading);"><?= $totalTrials ?> Total Leads</div>
      <div style="font-size:0.78rem; color:var(--text-secondary); margin-top:0.2rem;">Lifetime trial pass registrations</div>
    </div>
  </div>

  <!-- Search & Filter Controls Bar -->
  <div class="card" style="padding:1rem 1.25rem; margin-bottom:1.5rem; border-radius:var(--radius-lg); box-shadow:var(--shadow-subtle);">
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem;">
      <div style="display:flex; gap:0.45rem; flex-wrap:wrap;" id="trialFilterGroup">
        <button class="trial-filter-chip active" onclick="filterTrials('all', this)">All Trials (<?= $totalTrials ?>)</button>
        <button class="trial-filter-chip" onclick="filterTrials('active', this)">🟢 Active (<?= $activeTrialsCount ?>)</button>
        <button class="trial-filter-chip" onclick="filterTrials('expiring', this)" style="color:#DC2626;">⚠️ Expiring Soon (<?= $expiringSoonCount ?>)</button>
        <button class="trial-filter-chip" onclick="filterTrials('expired', this)">🔴 Expired (<?= $expiredTrialsCount ?>)</button>
        <button class="trial-filter-chip" onclick="filterTrials('converted', this)" style="color:#059669;">⭐ Converted (<?= $convertedCount ?>)</button>
      </div>

      <div style="position:relative; min-width:260px;">
        <input type="text" id="trialSearchInput" class="form-control" placeholder="🔍 Search trial client, phone, code..." oninput="searchTrialsTable(this.value)" style="padding-left:2.2rem; height:38px; border-radius:var(--radius-full); font-size:0.82rem;">
      </div>
    </div>
  </div>

  <!-- Trial Clients Directory Table -->
  <div class="card" style="border-radius:var(--radius-lg); box-shadow:var(--shadow-card); padding:1.25rem;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;">
      <span style="font-size:1.15rem; font-weight:800; color:var(--text-primary);">⚡ Exclusive Trial Members Roster</span>
      <span style="font-size:0.8rem; color:var(--text-muted);" id="trialCountLabel">Showing <?= count($trialMembers) ?> trial passes</span>
    </div>

    <div class="table-wrapper">
      <table id="trialsTable">
        <thead>
          <tr>
            <th>Trial Member</th>
            <th>Contact &amp; Gender</th>
            <th>Trial Package</th>
            <th>Pass Validity &amp; Days Left</th>
            <th>Gym Punches</th>
            <th>Conversion Status</th>
            <th>Actions / Follow-up</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($trialMembers)): ?>
            <tr>
              <td colspan="7" style="text-align:center; padding:2rem; color:var(--text-muted);">
                No trial members found. Click <strong>+ Register New Trial Client</strong> to issue a 1-15 days pass.
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($trialMembers as $tm): ?>
              <?php 
                $cleanPhone = preg_replace('/[^0-9]/', '', $tm['phone']);
                if (strlen($cleanPhone) === 10) $cleanPhone = '91' . $cleanPhone;
                
                $expDays = null;
                $isExpired = false;
                $isExpiringSoon = false;

                if (!empty($tm['plan_expiry'])) {
                    $expDays = ceil((strtotime($tm['plan_expiry']) - $todayTs) / 86400);
                    if ($expDays < 0) $isExpired = true;
                    elseif ($expDays <= 2) $isExpiringSoon = true;
                }

                $hasFull = intval($tm['has_full_plan'] ?? 0) > 0;
                $rowStatus = $hasFull ? 'converted' : ($isExpired ? 'expired' : ($isExpiringSoon ? 'expiring' : 'active'));

                // WhatsApp follow-up pitch message
                $waMsg = urlencode("Hello {$tm['name']}! Hope you are enjoying your Gym Trial at THE CLUB 777®. We have a special upgrade offer for you: Get 20% OFF when you upgrade to our Quarterly or Annual Membership today! Reply YES to claim.");
                $waUrl = "https://wa.me/{$cleanPhone}?text={$waMsg}";
              ?>
              <tr class="trial-row" data-status="<?= $rowStatus ?>" data-name="<?= htmlspecialchars(strtolower($tm['name'])) ?>" data-phone="<?= htmlspecialchars($tm['phone']) ?>" data-code="<?= htmlspecialchars(strtolower($tm['member_code'])) ?>" style="<?= $isExpiringSoon ? 'background:#FFFBEB;' : ($hasFull ? 'background:#F0FDF4;' : '') ?>">
                <td>
                  <div style="display:flex; align-items:center; gap:0.65rem;">
                    <div style="width:38px; height:38px; border-radius:50%; background:linear-gradient(135deg, #0284C7 0%, #38BDF8 100%); color:#fff; display:flex; align-items:center; justify-content:center; font-weight:800; font-size:1rem; flex-shrink:0;">
                      <?= strtoupper(substr($tm['name'], 0, 1)) ?>
                    </div>
                    <div>
                      <a href="index.php?page=member_profile&id=<?= $tm['id'] ?>" style="color:var(--text-primary); text-decoration:none; font-weight:800; font-size:0.92rem;">
                        <?= htmlspecialchars($tm['name']) ?>
                      </a>
                      <div style="font-size:0.75rem; font-family:var(--font-mono); color:var(--text-muted);"><?= htmlspecialchars($tm['member_code']) ?></div>
                    </div>
                  </div>
                </td>

                <td>
                  <div style="font-weight:700; font-family:var(--font-mono); color:var(--text-primary); font-size:0.88rem;"><?= htmlspecialchars($tm['phone']) ?></div>
                  <div style="font-size:0.75rem; color:var(--text-muted);"><?= htmlspecialchars($tm['gender'] ?: 'Male') ?></div>
                </td>

                <td>
                  <span class="badge" style="background:#E0F2FE; color:#0369A1; border:1px solid #BAE6FD; font-weight:800; font-size:0.78rem;">
                    ⚡ <?= htmlspecialchars($tm['plan_title']) ?>
                  </span>
                  <div style="font-size:0.75rem; color:var(--text-muted); margin-top:0.2rem; font-family:var(--font-mono);"><?= $currency ?><?= number_format($tm['plan_price'], 2) ?></div>
                </td>

                <td>
                  <?php if ($tm['plan_expiry']): ?>
                    <?php 
                      $expColor = $isExpired ? 'var(--danger)' : ($isExpiringSoon ? '#DC2626' : 'var(--success)');
                    ?>
                    <div style="font-weight:800; color:<?= $expColor ?>; font-family:var(--font-mono); font-size:0.88rem;"><?= date('d M Y', strtotime($tm['plan_expiry'])) ?></div>
                    <div style="font-size:0.74rem; font-weight:700; color:<?= $expColor ?>;">
                      <?= $isExpired ? 'Expired ' . abs($expDays) . 'd ago' : ($expDays === 0 ? '⚠️ Ends Today!' : ($expDays === 1 ? '⚠️ Ends Tomorrow' : "{$expDays} days left")) ?>
                    </div>
                  <?php else: ?>
                    <span style="color:var(--text-muted); font-size:0.8rem;">N/A</span>
                  <?php endif; ?>
                </td>

                <td>
                  <div style="font-weight:800; font-size:0.95rem; color:var(--primary); font-family:var(--font-mono);">
                    <?= $tm['trial_punches'] ?> Check-in<?= $tm['trial_punches'] != 1 ? 's' : '' ?>
                  </div>
                  <div style="font-size:0.72rem; color:var(--text-muted);">Verified punches</div>
                </td>

                <td>
                  <?php if ($hasFull): ?>
                    <span class="badge badge-success" style="font-weight:800; font-size:0.75rem;">⭐ CONVERTED</span>
                    <div style="font-size:0.72rem; color:var(--success); font-weight:700; margin-top:0.2rem;">Active Full Member</div>
                  <?php elseif ($isExpired): ?>
                    <span class="badge badge-danger" style="font-weight:800; font-size:0.72rem;">🔴 EXPIRED TRIAL</span>
                  <?php elseif ($isExpiringSoon): ?>
                    <span class="badge badge-warning" style="font-weight:800; font-size:0.72rem; background:#DC2626; color:#fff;">⚠️ DUE TO UPGRADE</span>
                  <?php else: ?>
                    <span class="badge badge-info" style="font-weight:800; font-size:0.72rem;">● ACTIVE TRIAL</span>
                  <?php endif; ?>
                </td>

                <td>
                  <div style="display:flex; gap:0.35rem; flex-wrap:wrap;">
                    <button type="button" class="btn btn-primary btn-sm" style="font-size:0.75rem; padding:0.25rem 0.55rem; font-weight:800;" onclick="openUpgradeTrialModal(<?= htmlspecialchars(json_encode([
                        'id' => $tm['id'],
                        'name' => $tm['name'],
                        'member_code' => $tm['member_code'],
                        'phone' => $tm['phone'],
                        'plan_title' => $tm['plan_title'],
                        'plan_expiry' => $tm['plan_expiry']
                    ])) ?>)" title="Upgrade to Full Monthly/Annual Membership">
                      ⭐ Upgrade Plan
                    </button>
                    <a href="<?= $waUrl ?>" target="_blank" class="btn btn-success btn-sm" style="font-size:0.75rem; padding:0.25rem 0.45rem; background:#25D366; border-color:#25D366; text-decoration:none;" title="Send WhatsApp Conversion Offer">
                      💬
                    </a>
                    <a href="index.php?page=member_profile&id=<?= $tm['id'] ?>" class="btn btn-secondary btn-sm" style="font-size:0.75rem; padding:0.25rem 0.45rem;" title="View 360° Profile">
                      👁️
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
<!-- REGISTER NEW TRIAL CLIENT MODAL                          -->
<!-- ========================================================= -->
<div class="modal" id="newTrialModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.55); z-index:100; align-items:center; justify-content:center; backdrop-filter:blur(2px);">
  <div class="modal-overlay" style="position:absolute; inset:0;" onclick="closeModal('newTrialModal')"></div>
  <div class="card" style="position:relative; z-index:10; background:#fff; width:92%; max-width:500px; border-radius:18px; padding:1.5rem; border-top:5px solid #0284C7; box-shadow:var(--shadow-modal);">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.25rem; border-bottom:1px solid var(--border-color); padding-bottom:0.75rem;">
      <span style="font-size:1.15rem; font-weight:800; color:#0369A1;">⚡ Issue New Gym Trial Pass</span>
      <button style="background:none; border:none; font-size:1.2rem; cursor:pointer;" onclick="closeModal('newTrialModal')">✕</button>
    </div>

    <form onsubmit="submitNewTrial(event)">
      <div class="form-group">
        <label class="form-label">Full Name *</label>
        <input type="text" id="ntName" class="form-control" placeholder="e.g. Vikas Sharma" required>
      </div>

      <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.75rem;">
        <div class="form-group">
          <label class="form-label">Mobile Number *</label>
          <input type="tel" id="ntPhone" class="form-control font-mono" placeholder="9876543210" required>
        </div>
        <div class="form-group">
          <label class="form-label">Gender</label>
          <select id="ntGender" class="form-control">
            <option value="Male">Male</option>
            <option value="Female">Female</option>
            <option value="Other">Other</option>
          </select>
        </div>
      </div>

      <div class="form-group">
        <label class="form-label">Select Trial Pass Type *</label>
        <select id="ntPlanSelect" class="form-control" onchange="onTrialPlanChange(this)" required>
          <option value="">-- Choose Trial Pass --</option>
          <?php foreach ($trialPlans as $tp): ?>
            <option value="<?= $tp['id'] ?>" data-price="<?= $tp['price'] ?>" data-days="<?= $tp['duration_days'] ?>">
              <?= htmlspecialchars($tp['title']) ?> (<?= $tp['duration_days'] ?> Days) - <?= $currency ?><?= number_format($tp['price'], 2) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.75rem;">
        <div class="form-group">
          <label class="form-label">Trial Price Paid (<?= $currency ?>)</label>
          <input type="number" step="0.01" id="ntPrice" class="form-control font-mono" placeholder="0" required style="font-weight:800; font-size:1.1rem; color:#0284C7;">
        </div>
        <div class="form-group">
          <label class="form-label">Payment Mode</label>
          <select id="ntPaymentMode" class="form-control">
            <option value="Cash">Cash</option>
            <option value="UPI">UPI / QR</option>
            <option value="Free Trial">Free / Complimentary</option>
          </select>
        </div>
      </div>

      <div style="display:flex; gap:0.75rem; margin-top:1.5rem;">
        <button type="button" class="btn btn-secondary btn-block" onclick="closeModal('newTrialModal')">Cancel</button>
        <button type="submit" class="btn btn-primary btn-block" style="background:#0284C7; border-color:#0284C7; font-weight:800;">✓ ISSUE TRIAL PASS</button>
      </div>
    </form>
  </div>
</div>

<style>
.trial-filter-chip {
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
.trial-filter-chip:hover {
  background: var(--bg-surface-hover);
  color: #0284C7;
  border-color: #BAE6FD;
}
.trial-filter-chip.active {
  background: #0284C7;
  color: #fff;
  border-color: #0284C7;
  box-shadow: 0 2px 6px rgba(2,132,199,0.3);
}
</style>

<script>
let currentTrialFilter = 'all';
let trialFilterTimer = null;

function filterTrials(filter, btn) {
  currentTrialFilter = filter;
  document.querySelectorAll('#trialFilterGroup .trial-filter-chip').forEach(b => b.classList.remove('active'));
  if (btn) btn.classList.add('active');
  applyTrialFilters();
}

function searchTrialsTable(query) {
  clearTimeout(trialFilterTimer);
  trialFilterTimer = setTimeout(() => {
    requestAnimationFrame(applyTrialFilters);
  }, 60);
}

function applyTrialFilters() {
  const query = (document.getElementById('trialSearchInput').value || '').toLowerCase().trim();
  const rows = document.querySelectorAll('#trialsTable tbody tr.trial-row');
  let visibleCount = 0;

  rows.forEach(row => {
    const s = row.getAttribute('data-status') || '';
    const name = row.getAttribute('data-name') || '';
    const phone = row.getAttribute('data-phone') || '';
    const code = row.getAttribute('data-code') || '';

    let matchesFilter = true;
    if (currentTrialFilter === 'active') matchesFilter = (s === 'active' || s === 'expiring');
    else if (currentTrialFilter === 'expiring') matchesFilter = (s === 'expiring');
    else if (currentTrialFilter === 'expired') matchesFilter = (s === 'expired');
    else if (currentTrialFilter === 'converted') matchesFilter = (s === 'converted');

    const matchesSearch = !query || name.includes(query) || phone.includes(query) || code.includes(query);

    if (matchesFilter && matchesSearch) {
      row.style.display = '';
      visibleCount++;
    } else {
      row.style.display = 'none';
    }
  });

  const countLabel = document.getElementById('trialCountLabel');
  if (countLabel) countLabel.innerText = `Showing ${visibleCount} trial passes`;
}

function onTrialPlanChange(select) {
  const opt = select.options[select.selectedIndex];
  if (opt && opt.value) {
    const price = opt.getAttribute('data-price');
    if (price) document.getElementById('ntPrice').value = price;
  }
}

function submitNewTrial(e) {
  e.preventDefault();
  const name = document.getElementById('ntName').value.trim();
  const phone = document.getElementById('ntPhone').value.trim();
  const gender = document.getElementById('ntGender').value;
  const planId = document.getElementById('ntPlanSelect').value;
  const price = document.getElementById('ntPrice').value;
  const method = document.getElementById('ntPaymentMode').value;

  fetch('api/members.php?action=create', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      name: name,
      phone: phone,
      gender: gender,
      membership_type_id: planId,
      paid_amount: price,
      payment_method: method
    })
  })
  .then(res => res.text())
  .then(text => {
    try { return JSON.parse(text); } catch(e) { return { success: false, message: text }; }
  })
  .then(res => {
    if (res.success) {
      showToast('Trial Member Registered Successfully!', 'success');
      closeModal('newTrialModal');
      setTimeout(() => location.reload(), 1000);
    } else {
      showToast(res.message || 'Registration failed', 'danger');
    }
  })
  .catch(err => {
    showToast('Network error registering trial client', 'danger');
  });
}

function exportTrialsCsv() {
  const table = document.getElementById('trialsTable');
  let csv = [];
  const rows = table.querySelectorAll('tr');
  
  for (let i = 0; i < rows.length; i++) {
    if (rows[i].style.display === 'none') continue;
    const row = [], cols = rows[i].querySelectorAll('td, th');
    for (let j = 0; j < cols.length - 1; j++) {
      let data = cols[j].innerText.replace(/(\r\n|\n|\r)/gm, ' ').replace(/(\s\s+)/gm, ' ').trim();
      data = data.replace(/"/g, '""');
      row.push('"' + data + '"');
    }
    csv.push(row.join(','));
  }
  
  const csvFile = new Blob([csv.join('\n')], {type: 'text/csv'});
  const downloadLink = document.createElement('a');
  downloadLink.download = 'gym_trial_passes_' + new Date().toISOString().slice(0,10) + '.csv';
  downloadLink.href = window.URL.createObjectURL(csvFile);
  downloadLink.style.display = 'none';
  document.body.appendChild(downloadLink);
  downloadLink.click();
  document.body.removeChild(downloadLink);
  showToast('Trial Passes CSV exported successfully!', 'success');
}

let currentUpgMember = null;

function openUpgradeTrialModal(member) {
  currentUpgMember = member;
  document.getElementById('upgMemberId').value = member.id;
  document.getElementById('upgMemberName').innerText = member.name;
  document.getElementById('upgMemberCode').innerText = member.member_code || 'ID: ' + member.id;
  document.getElementById('upgMemberPhone').innerText = member.phone || 'No phone';
  document.getElementById('upgCurrentPlan').innerText = member.plan_title || 'Trial';

  const today = new Date().toISOString().slice(0, 10);
  document.getElementById('upgStartDate').value = today;
  document.getElementById('upgPlanSelect').selectedIndex = 0;
  document.getElementById('upgPrice').value = '';

  openModal('upgradeTrialModal');
}

function onUpgPlanChange() {
  const sel = document.getElementById('upgPlanSelect');
  const opt = sel.options[sel.selectedIndex];
  if (opt && opt.dataset.price) {
    document.getElementById('upgPrice').value = opt.dataset.price;
  }
}

function goToPosFromModal() {
  if (currentUpgMember && currentUpgMember.id) {
    window.location.href = 'index.php?page=pos&member_id=' + currentUpgMember.id;
  }
}

function submitTrialUpgrade(e) {
  e.preventDefault();
  const memberId = document.getElementById('upgMemberId').value;
  const planId = document.getElementById('upgPlanSelect').value;
  const startDate = document.getElementById('upgStartDate').value;
  const price = document.getElementById('upgPrice').value;
  const btn = document.getElementById('btnUpgSubmit');

  if (!planId) {
    showToast('Please select a membership plan', 'warning');
    return;
  }

  btn.disabled = true;
  btn.innerHTML = '⏳ Upgrading...';

  fetch('api/memberships.php?action=assign_plan', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      member_id: memberId,
      membership_type_id: planId,
      start_date: startDate,
      price_paid: price
    })
  })
  .then(res => res.json())
  .then(res => {
    if (res.success) {
      showToast('🎉 Member Plan Upgraded Successfully!', 'success');
      closeModal('upgradeTrialModal');
      setTimeout(() => location.reload(), 1200);
    } else {
      showToast(res.message || 'Upgrade failed', 'danger');
      btn.disabled = false;
      btn.innerHTML = '⚡ Confirm Upgrade Now';
    }
  })
  .catch(err => {
    showToast('Network error during upgrade', 'danger');
    btn.disabled = false;
    btn.innerHTML = '⚡ Confirm Upgrade Now';
  });
}
</script>

<!-- ========================================================= -->
<!-- UPGRADE TRIAL MEMBER MODAL                                -->
<!-- ========================================================= -->
<div class="modal" id="upgradeTrialModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.55); z-index:100; align-items:center; justify-content:center; backdrop-filter:blur(2px);">
  <div class="modal-overlay" style="position:absolute; inset:0;" onclick="closeModal('upgradeTrialModal')"></div>
  <div class="card" style="position:relative; z-index:10; background:#fff; width:92%; max-width:500px; border-radius:18px; padding:1.5rem; border-top:5px solid #2563EB; box-shadow:var(--shadow-modal);">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.25rem; border-bottom:1px solid var(--border-color); padding-bottom:0.75rem;">
      <div>
        <span style="font-size:1.15rem; font-weight:800; color:#1E3A8A;">⭐ Upgrade Trial to Regular Plan</span>
        <div style="font-size:0.75rem; color:var(--text-muted); margin-top:0.15rem;">Convert trial client to full member with instant activation</div>
      </div>
      <button style="background:none; border:none; font-size:1.2rem; cursor:pointer;" onclick="closeModal('upgradeTrialModal')">✕</button>
    </div>

    <!-- Selected Member Info Card -->
    <div style="background:#EFF6FF; border:1px solid #BFDBFE; border-radius:12px; padding:0.85rem 1rem; margin-bottom:1.25rem; display:flex; justify-content:space-between; align-items:center;">
      <div>
        <strong id="upgMemberName" style="color:#1E40AF; font-size:1rem;">Member Name</strong>
        <div style="font-size:0.78rem; color:#3B82F6; margin-top:0.2rem;">
          <span id="upgMemberCode" class="font-mono">CODE</span> &bull; 
          <span id="upgMemberPhone" class="font-mono">PHONE</span>
        </div>
      </div>
      <span id="upgCurrentPlan" class="badge" style="background:#DBEAFE; color:#1E40AF; font-size:0.75rem; font-weight:700;">Trial</span>
    </div>

    <form onsubmit="submitTrialUpgrade(event)">
      <input type="hidden" id="upgMemberId" value="">

      <div class="form-group">
        <label class="form-label">Select Upgrade Plan *</label>
        <select id="upgPlanSelect" class="form-control" onchange="onUpgPlanChange()" required>
          <option value="">-- Choose Membership Plan --</option>
          <?php foreach ($regularPlans as $rp): ?>
            <option value="<?= $rp['id'] ?>" data-price="<?= $rp['price'] ?>" data-duration="<?= $rp['duration_days'] ?>">
              <?= htmlspecialchars($rp['title']) ?> &bull; <?= $currency ?><?= number_format($rp['price'], 2) ?> (<?= $rp['duration_days'] ?> Days)
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.75rem;">
        <div class="form-group">
          <label class="form-label">Start Date *</label>
          <input type="date" id="upgStartDate" class="form-control" value="<?= date('Y-m-d') ?>" required>
        </div>
        <div class="form-group">
          <label class="form-label">Price to Pay (<?= $currency ?>) *</label>
          <input type="number" id="upgPrice" class="form-control font-mono" placeholder="0.00" required>
        </div>
      </div>

      <div class="form-group">
        <label class="form-label">Payment Mode *</label>
        <select id="upgPaymentMode" class="form-control">
          <option value="cash">Cash</option>
          <option value="upi">UPI / GPay / PhonePe / Paytm</option>
          <option value="card">Debit / Credit Card (POS)</option>
          <option value="bank_transfer">Net Banking / Bank Transfer</option>
        </select>
      </div>

      <div style="display:flex; gap:0.65rem; margin-top:1.5rem;">
        <button type="submit" id="btnUpgSubmit" class="btn btn-primary" style="flex:1; font-weight:800; padding:0.65rem;">
          ⚡ Confirm Upgrade Now
        </button>
        <button type="button" class="btn btn-outline" style="font-weight:700; padding:0.65rem;" onclick="goToPosFromModal()" title="Open customer in Touch POS with cart auto-selected">
          🛒 Open in POS
        </button>
      </div>
    </form>
  </div>
</div>
