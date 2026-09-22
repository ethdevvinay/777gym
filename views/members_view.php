<!-- Members Management View Component -->
<?php
$db = getDB();
$members = $db->query("
    SELECT m.*, 
           (SELECT ms.end_date FROM member_subscriptions ms WHERE ms.member_id = m.id AND ms.status = 'active' ORDER BY ms.id DESC LIMIT 1) as plan_expiry,
           (SELECT mt.title FROM member_subscriptions ms JOIN membership_types mt ON ms.membership_type_id = mt.id WHERE ms.member_id = m.id AND ms.status = 'active' ORDER BY ms.id DESC LIMIT 1) as plan_title,
           (CASE WHEN m.dob IS NOT NULL AND MONTH(m.dob) = MONTH(CURRENT_DATE()) AND DAY(m.dob) = DAY(CURRENT_DATE()) THEN 1 ELSE 0 END) as is_birthday_today
    FROM members m
    ORDER BY is_birthday_today DESC, m.id DESC LIMIT 200
")->fetchAll();

$totalMembers = count($members);
$activeCount = 0;
$expiredCount = 0;
$frozenCount = 0;
$todayBdaysCount = 0;
$expiringSoonCount = 0;

$todayTs = strtotime('today');

foreach ($members as $m) {
    if ($m['is_birthday_today']) $todayBdaysCount++;

    // Auto-correct status for display
    $mStatus = $m['status'];
    if (!empty($m['plan_expiry'])) {
        $daysLeft = ceil((strtotime($m['plan_expiry']) - $todayTs) / 86400);
        if ($daysLeft >= 0 && $daysLeft <= 7) {
            $expiringSoonCount++;
        }
        // If plan expired but status is still 'active', treat as expired
        if ($mStatus === 'active' && $daysLeft <= 0) {
            $mStatus = 'expired';
        }
    }

    if ($mStatus === 'active') $activeCount++;
    elseif ($mStatus === 'expired') $expiredCount++;
    elseif ($mStatus === 'frozen') $frozenCount++;
}
?>

<div class="page-content">
  <!-- Header & Primary Actions -->
  <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1.25rem; margin-bottom:1.5rem;">
    <div>
      <h1 style="font-size:1.65rem; font-weight:900; color:var(--text-primary); letter-spacing:-0.03em; display:flex; align-items:center; gap:0.65rem;">
        <span style="background:#EFF6FF; color:var(--primary); width:42px; height:42px; border-radius:12px; display:inline-flex; align-items:center; justify-content:center; font-size:1.4rem; box-shadow:0 2px 8px var(--primary-glow);">👥</span>
        <span>Gym Members Directory</span>
      </h1>
      <p style="font-size:0.85rem; color:var(--text-secondary); margin-top:0.25rem;">Manage registered clients, digital passes, birthday triggers &amp; membership subscriptions</p>
    </div>

    <div style="display:flex; gap:0.65rem; flex-wrap:wrap;">
      <?php if ($todayBdaysCount > 0): ?>
        <a href="index.php?page=communication" class="btn btn-warning" style="border-radius:var(--radius-md); font-weight:800; animation:pulse 2s infinite;">
          🎂 <?= $todayBdaysCount ?> Birthday(s) Today!
        </a>
      <?php endif; ?>

      <button class="btn btn-outline" style="border-radius:var(--radius-md); font-weight:700; background:var(--bg-surface);" onclick="exportMembersCsv()">
        📥 Export CSV
      </button>

      <button class="btn btn-primary" style="border-radius:var(--radius-md); font-weight:800; box-shadow:0 4px 12px var(--primary-glow);" onclick="openModal('newMemberModal')">
        + Add New Member (F3)
      </button>
    </div>
  </div>

  <!-- KPI Metric Summary Cards -->
  <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(210px, 1fr)); gap:1.25rem; margin-bottom:1.75rem;">
    <div class="card" style="border-left:4px solid var(--primary); padding:1.25rem; border-radius:var(--radius-lg); box-shadow:var(--shadow-card);">
      <div style="font-size:0.75rem; font-weight:800; text-transform:uppercase; color:var(--text-muted); letter-spacing:0.05em;">Total Members</div>
      <div style="font-size:1.85rem; font-weight:900; color:var(--primary); margin-top:0.35rem; font-family:var(--font-heading);"><?= $totalMembers ?> Clients</div>
      <div style="font-size:0.78rem; color:var(--text-secondary); margin-top:0.2rem;">Registered in gym database</div>
    </div>

    <div class="card" style="border-left:4px solid var(--success); padding:1.25rem; border-radius:var(--radius-lg); box-shadow:var(--shadow-card);">
      <div style="font-size:0.75rem; font-weight:800; text-transform:uppercase; color:var(--text-muted); letter-spacing:0.05em;">Active Members</div>
      <div style="font-size:1.85rem; font-weight:900; color:var(--success); margin-top:0.35rem; font-family:var(--font-heading);"><?= $activeCount ?> Active</div>
      <div style="font-size:0.78rem; color:var(--text-secondary); margin-top:0.2rem;">Valid membership passes</div>
    </div>

    <div class="card" style="border-left:4px solid var(--warning); padding:1.25rem; border-radius:var(--radius-lg); box-shadow:var(--shadow-card);">
      <div style="font-size:0.75rem; font-weight:800; text-transform:uppercase; color:var(--text-muted); letter-spacing:0.05em;">Expiring This Week</div>
      <div style="font-size:1.85rem; font-weight:900; color:var(--warning); margin-top:0.35rem; font-family:var(--font-heading);"><?= $expiringSoonCount ?> Due Soon</div>
      <div style="font-size:0.78rem; color:var(--text-secondary); margin-top:0.2rem;">Auto-reminders active</div>
    </div>

    <div class="card" style="border-left:4px solid var(--danger); padding:1.25rem; border-radius:var(--radius-lg); box-shadow:var(--shadow-card);">
      <div style="font-size:0.75rem; font-weight:800; text-transform:uppercase; color:var(--text-muted); letter-spacing:0.05em;">Expired / Inactive</div>
      <div style="font-size:1.85rem; font-weight:900; color:var(--danger); margin-top:0.35rem; font-family:var(--font-heading);"><?= $expiredCount ?> Expired</div>
      <div style="font-size:0.78rem; color:var(--text-secondary); margin-top:0.2rem;">Pending renewal follow-ups</div>
    </div>
  </div>

  <!-- Search & Filter Bar -->
  <div class="card" style="padding:1rem 1.25rem; margin-bottom:1.5rem; border-radius:var(--radius-lg); box-shadow:var(--shadow-subtle);">
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem;">
      <div style="display:flex; gap:0.45rem; flex-wrap:wrap;" id="memberFilterGroup">
        <button class="member-filter-chip active" onclick="filterMembers('ALL', this)">All (<?= $totalMembers ?>)</button>
        <button class="member-filter-chip" onclick="filterMembers('active', this)">🟢 Active (<?= $activeCount ?>)</button>
        <button class="member-filter-chip" onclick="filterMembers('expiring', this)">⚠️ Expiring Soon (<?= $expiringSoonCount ?>)</button>
        <button class="member-filter-chip" onclick="filterMembers('expired', this)">🔴 Expired (<?= $expiredCount ?>)</button>
        <button class="member-filter-chip" onclick="filterMembers('frozen', this)">❄️ Frozen (<?= $frozenCount ?>)</button>
        <?php if ($todayBdaysCount > 0): ?>
          <button class="member-filter-chip" onclick="filterMembers('birthday', this)">🎂 Birthdays (<?= $todayBdaysCount ?>)</button>
        <?php endif; ?>
      </div>

      <div style="position:relative; min-width:260px;">
        <input type="text" id="memberSearchInput" class="form-control" placeholder="🔍 Search name, phone, code or locker..." oninput="searchMembersTable(this.value)" style="padding-left:2.2rem; height:38px; border-radius:var(--radius-full); font-size:0.82rem;">
      </div>
    </div>
  </div>

  <!-- Members Directory Table -->
  <div class="card" style="border-radius:var(--radius-lg); box-shadow:var(--shadow-card); padding:1.25rem;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;">
      <span style="font-size:1.15rem; font-weight:800; color:var(--text-primary);">👥 Registered Member Records</span>
      <span style="font-size:0.8rem; color:var(--text-muted);" id="memberCountLabel">Showing <?= count($members) ?> members</span>
    </div>

    <div class="table-wrapper">
      <table id="membersDirectoryTable">
        <thead>
          <tr>
            <th>Member Client</th>
            <th>Contact &amp; DOB</th>
            <th>Active Plan</th>
            <th>Plan Expiry</th>

            <th>Status</th>
            <th>Quick Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($members as $m): ?>
            <?php 
              $cleanPhone = preg_replace('/[^0-9]/', '', $m['phone']);
              if (strlen($cleanPhone) === 10) $cleanPhone = '91' . $cleanPhone;
              $waChatUrl = "https://wa.me/{$cleanPhone}";

              $expDays = null;
              $isExpiringSoon = false;
              if (!empty($m['plan_expiry'])) {
                  $expDays = ceil((strtotime($m['plan_expiry']) - $todayTs) / 86400);
                  if ($expDays >= 0 && $expDays <= 7) $isExpiringSoon = true;
              }
              // Auto-correct status: if plan is expired but DB still says active, show expired
              $st = $m['status'];
              if ($st === 'active' && !empty($m['plan_expiry']) && $expDays !== null && $expDays <= 0) {
                  $st = 'expired';
              }
              $isBday = $m['is_birthday_today'] ? '1' : '0';
            ?>
            <tr data-status="<?= $st ?>" data-expiring="<?= $isExpiringSoon ? '1' : '0' ?>" data-birthday="<?= $isBday ?>" data-name="<?= htmlspecialchars(strtolower($m['name'])) ?>" data-phone="<?= htmlspecialchars($m['phone']) ?>" data-code="<?= htmlspecialchars(strtolower($m['member_code'])) ?>" data-biometric="<?= htmlspecialchars(strtolower($m['biometric_id'] ?? '')) ?>" style="<?= $m['is_birthday_today'] ? 'background:rgba(245,158,11,0.06);' : '' ?>">
              <td>
                <div style="display:flex; align-items:center; gap:0.75rem;">
                  <div style="width:40px; height:40px; border-radius:50%; background:linear-gradient(135deg, var(--primary) 0%, #38BDF8 100%); color:#fff; display:flex; align-items:center; justify-content:center; font-weight:800; font-size:1.1rem; flex-shrink:0;">
                    <?= strtoupper(substr($m['name'], 0, 1)) ?>
                  </div>
                  <div>
                    <div style="display:flex; align-items:center; gap:0.4rem;">
                      <a href="index.php?page=member_profile&id=<?= $m['id'] ?>" style="font-weight:800; color:var(--text-primary); text-decoration:none; font-size:0.92rem;">
                        <?= htmlspecialchars($m['name']) ?>
                      </a>
                      <?php if ($m['is_birthday_today']): ?>
                        <span class="badge badge-warning" style="font-size:0.65rem; font-weight:800;">🎂 TODAY!</span>
                      <?php endif; ?>
                    </div>
                    <div style="font-size:0.75rem; font-family:var(--font-mono); color:var(--text-muted); margin-top:0.1rem; display:flex; align-items:center; gap:0.35rem; flex-wrap:wrap;">
                      <span><?= htmlspecialchars($m['member_code']) ?></span>
                      <span>&bull;</span>
                      <span><?= htmlspecialchars($m['gender'] ?: 'Male') ?></span>
                      <?php if (!empty($m['biometric_id'])): ?>
                        <span>&bull;</span>
                        <span class="badge badge-secondary" style="font-size:0.68rem; padding:1px 5px; font-weight:700;">📟 <?= htmlspecialchars($m['biometric_id']) ?></span>
                      <?php endif; ?>
                    </div>
                  </div>
                </div>
              </td>

              <td>
                <div style="font-weight:700; font-family:var(--font-mono); color:var(--text-primary); font-size:0.88rem;"><?= htmlspecialchars($m['phone']) ?></div>
                <div style="font-size:0.75rem; color:var(--text-muted);">
                  <?= !empty($m['dob']) ? '🎂 ' . date('d M Y', strtotime($m['dob'])) : (htmlspecialchars($m['email'] ?: 'No email')) ?>
                </div>
              </td>

              <td>
                <span class="badge badge-info" style="font-size:0.78rem; font-weight:700;">
                  <?= htmlspecialchars($m['plan_title'] ?: 'No Active Plan') ?>
                </span>
              </td>

              <td>
                <?php if ($m['plan_expiry']): ?>
                  <?php 
                    $expColor = ($expDays <= 0) ? 'var(--danger)' : (($expDays <= 7) ? 'var(--warning)' : 'var(--success)');
                  ?>
                  <div style="font-weight:800; color:<?= $expColor ?>; font-family:var(--font-mono); font-size:0.88rem;"><?= date('d M Y', strtotime($m['plan_expiry'])) ?></div>
                  <div style="font-size:0.72rem; color:var(--text-muted);"><?= $expDays <= 0 ? 'Expired' : ($expDays . ' days left') ?></div>
                <?php else: ?>
                  <span style="color:var(--text-muted); font-size:0.8rem;">N/A</span>
                <?php endif; ?>
              </td>



              <td>
                <span class="badge badge-<?= ($st === 'active') ? 'success' : (($st === 'expired') ? 'danger' : 'warning') ?>" style="font-weight:800; font-size:0.72rem;">
                  ● <?= strtoupper($st) ?>
                </span>
              </td>

              <td>
                <div style="display:flex; gap:0.35rem; flex-wrap:wrap;">
                  <a href="index.php?page=member_profile&id=<?= $m['id'] ?>" class="btn btn-secondary btn-sm" style="font-size:0.74rem; padding:0.25rem 0.5rem;" title="View 360° Profile">
                    👁️ 360°
                  </a>
                  <button class="btn btn-secondary btn-sm" style="font-size:0.74rem; padding:0.25rem 0.5rem;" onclick="openEditMember(<?= htmlspecialchars(json_encode($m)) ?>)" title="Edit Details">
                    ✏️
                  </button>
                  <a href="<?= $waChatUrl ?>" target="_blank" class="btn btn-success btn-sm" style="font-size:0.74rem; padding:0.25rem 0.5rem; background:#25D366; border-color:#25D366; text-decoration:none;" title="Open WhatsApp Chat">
                    💬
                  </a>
                  <a href="index.php?page=pos&member_id=<?= $m['id'] ?>" class="btn btn-primary btn-sm" style="font-size:0.74rem; padding:0.25rem 0.5rem;" title="Open Touch POS">
                    🛒 POS
                  </a>
                  <button class="btn btn-danger btn-sm" style="font-size:0.74rem; padding:0.25rem 0.45rem;" onclick="deleteMember(<?= $m['id'] ?>, '<?= htmlspecialchars(addslashes($m['name'])) ?>')" title="Delete Member">
                    🗑️
                  </button>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Edit Member Modal -->
<div class="modal" id="editMemberModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.55); z-index:100; align-items:center; justify-content:center; backdrop-filter:blur(2px);">
  <div class="modal-overlay" style="position:absolute; inset:0;" onclick="closeModal('editMemberModal')"></div>
  <div class="card" style="position:relative; z-index:10; background:#fff; width:92%; max-width:520px; border-radius:18px; padding:1.5rem; box-shadow:var(--shadow-modal);">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.25rem; border-bottom:1px solid var(--border-color); padding-bottom:0.75rem;">
      <span style="font-size:1.15rem; font-weight:800; color:var(--text-primary);">✏️ Edit Member Profile</span>
      <button style="background:none; border:none; font-size:1.2rem; cursor:pointer;" onclick="closeModal('editMemberModal')">✕</button>
    </div>

    <form id="editMemberForm" onsubmit="event.preventDefault(); submitEditMember();">
      <input type="hidden" id="emId">
      
      <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.75rem;">
        <div class="form-group" style="grid-column:1 / -1;">
          <label class="form-label">Full Name *</label>
          <input type="text" id="emName" class="form-control" required>
        </div>

        <div class="form-group">
          <label class="form-label">Mobile Number *</label>
          <input type="tel" id="emPhone" class="form-control font-mono" required>
        </div>

        <div class="form-group">
          <label class="form-label">Date of Birth (DOB) 🎂</label>
          <input type="date" id="emDob" class="form-control font-mono">
        </div>

        <div class="form-group">
          <label class="form-label">Gender</label>
          <select id="emGender" class="form-control">
            <option value="Male">Male</option>
            <option value="Female">Female</option>
            <option value="Other">Other</option>
          </select>
        </div>



        <div class="form-group">
          <label class="form-label">Biometric ID / RFID 📟</label>
          <input type="text" id="emBiometric" class="form-control font-mono" placeholder="BIO-1001">
        </div>

        <div class="form-group">
          <label class="form-label">Account Status</label>
          <select id="emStatus" class="form-control">
            <option value="active">Active</option>
            <option value="expired">Expired</option>
            <option value="frozen">Frozen</option>
          </select>
        </div>

        <div class="form-group" style="grid-column:1 / -1;">
          <label class="form-label">Email Address</label>
          <input type="email" id="emEmail" class="form-control">
        </div>
      </div>

      <div style="display:flex; gap:0.75rem; margin-top:1.5rem;">
        <button type="button" class="btn btn-secondary btn-block" onclick="closeModal('editMemberModal')">Cancel</button>
        <button type="submit" class="btn btn-primary btn-block">✓ Update Member Profile</button>
      </div>
    </form>
  </div>
</div>

<style>
.member-filter-chip {
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
.member-filter-chip:hover {
  background: var(--bg-surface-hover);
  color: var(--primary);
  border-color: var(--primary-border);
}
.member-filter-chip.active {
  background: var(--primary);
  color: #fff;
  border-color: var(--primary);
  box-shadow: 0 2px 6px var(--primary-glow);
}
</style>

<script>
let currentMemberFilter = 'ALL';

function filterMembers(filter, btn) {
  currentMemberFilter = filter;
  document.querySelectorAll('#memberFilterGroup .member-filter-chip').forEach(b => b.classList.remove('active'));
  if (btn) btn.classList.add('active');
  applyMemberFilters();
}

let memberFilterTimer = null;
function searchMembersTable(query) {
  clearTimeout(memberFilterTimer);
  memberFilterTimer = setTimeout(() => {
    requestAnimationFrame(applyMemberFilters);
  }, 60);
}

function applyMemberFilters() {
  const query = (document.getElementById('memberSearchInput').value || '').toLowerCase().trim();
  const rows = document.querySelectorAll('#membersDirectoryTable tbody tr');
  let visibleCount = 0;

  for (let i = 0; i < rows.length; i++) {
    const row = rows[i];
    const st = row.getAttribute('data-status') || '';
    const expiring = row.getAttribute('data-expiring') || '0';
    const birthday = row.getAttribute('data-birthday') || '0';
    const name = row.getAttribute('data-name') || '';
    const phone = row.getAttribute('data-phone') || '';
    const code = row.getAttribute('data-code') || '';
    const bio = row.getAttribute('data-biometric') || '';

    let matchesFilter = true;
    if (currentMemberFilter === 'active') matchesFilter = (st === 'active');
    else if (currentMemberFilter === 'expired') matchesFilter = (st === 'expired');
    else if (currentMemberFilter === 'frozen') matchesFilter = (st === 'frozen');
    else if (currentMemberFilter === 'expiring') matchesFilter = (expiring === '1');
    else if (currentMemberFilter === 'birthday') matchesFilter = (birthday === '1');

    const matchesSearch = !query || name.includes(query) || phone.includes(query) || code.includes(query) || bio.includes(query);

    if (matchesFilter && matchesSearch) {
      row.style.display = '';
      visibleCount++;
    } else {
      row.style.display = 'none';
    }
  }

  const countLabel = document.getElementById('memberCountLabel');
  if (countLabel) countLabel.innerText = `Showing ${visibleCount} members`;
}

function openEditMember(member) {
  document.getElementById('emId').value = member.id;
  document.getElementById('emName').value = member.name;
  document.getElementById('emPhone').value = member.phone;
  document.getElementById('emDob').value = member.dob || '';
  document.getElementById('emEmail').value = member.email || '';
  document.getElementById('emGender').value = member.gender || 'Male';
  document.getElementById('emBiometric').value = member.biometric_id || '';
  document.getElementById('emStatus').value = member.status || 'active';
  openModal('editMemberModal');
}

function submitEditMember() {
  const payload = {
    id: document.getElementById('emId').value,
    name: document.getElementById('emName').value,
    phone: document.getElementById('emPhone').value,
    dob: document.getElementById('emDob').value || null,
    email: document.getElementById('emEmail').value,
    gender: document.getElementById('emGender').value,
    biometric_id: document.getElementById('emBiometric').value,
    status: document.getElementById('emStatus').value
  };

  fetch('api/members.php?action=update', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(payload)
  })
  .then(res => res.text())
  .then(text => {
    try { return JSON.parse(text); } catch(e) { return { success: false, message: text }; }
  })
  .then(res => {
    if (res.success) {
      showToast('Member Profile Updated Successfully!', 'success');
      closeModal('editMemberModal');
      setTimeout(() => window.location.reload(), 1000);
    } else {
      showToast(res.message || 'Update failed', 'danger');
    }
  });
}

function deleteMember(id, name) {
  if (confirm(`Are you sure you want to delete member: ${name}?`)) {
    fetch(`api/members.php?action=delete&id=${id}`)
      .then(res => res.text())
      .then(text => {
        try { return JSON.parse(text); } catch(e) { return { success: false, message: text }; }
      })
      .then(res => {
        if (res.success) {
          showToast('Member Deleted Successfully!', 'success');
          setTimeout(() => window.location.reload(), 1000);
        } else {
          showToast(res.message || 'Deletion failed', 'danger');
        }
      });
  }
}

function exportMembersCsv() {
  const table = document.getElementById('membersDirectoryTable');
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
  downloadLink.download = 'gym_members_directory_' + new Date().toISOString().slice(0,10) + '.csv';
  downloadLink.href = window.URL.createObjectURL(csvFile);
  downloadLink.style.display = 'none';
  document.body.appendChild(downloadLink);
  downloadLink.click();
  document.body.removeChild(downloadLink);
  showToast('Members Directory CSV exported successfully!', 'success');
}
</script>
