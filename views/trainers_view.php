<!-- Trainers Management View -->
<?php
$db = getDB();
$stmt = $db->query("SELECT * FROM trainers ORDER BY id ASC");
$trainers = $stmt->fetchAll();
$activePtCount = $db->query("SELECT COUNT(DISTINCT member_id) FROM pt_subscriptions WHERE status = 'active' AND (sessions_total = 0 OR sessions_used < sessions_total)")->fetchColumn();
$currency = getSetting('currency_symbol', '₹');
?>

<div class="page-content">
  <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem; margin-bottom:1.5rem;">
    <div>
      <h1 style="font-size:1.6rem; font-weight:800; color:var(--text-primary); display:flex; align-items:center; gap:0.6rem;">
        <span>💪</span> <span>Fitness Trainers &amp; PT Hub</span>
      </h1>
      <p style="font-size:0.85rem; color:var(--text-secondary);">Manage trainer profiles, client sessions balance, commissions &amp; automated leave alerts</p>
    </div>

    <div style="display:flex; gap:0.5rem; flex-wrap:wrap;">
      <button class="btn" style="background:#8B5CF6; color:#fff; font-weight:800; border:none; box-shadow:0 2px 8px rgba(139,92,246,0.3);" onclick="openModal('broadcastPtOffModal')">
        📢 Broadcast PT Off Notice (WhatsApp)
      </button>
      <button class="btn btn-primary" onclick="openModal('addTrainerModal')">
        + Add New Trainer
      </button>
    </div>
  </div>

  <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(320px, 1fr)); gap:1.25rem;">
    <?php foreach ($trainers as $t): ?>
      <div class="card" style="border-top:4px solid var(--primary);">
        <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:0.75rem;">
          <div>
            <strong style="font-size:1.1rem; color:var(--text-primary);"><?= htmlspecialchars($t['name']) ?></strong>
            <div style="font-size:0.8rem; color:var(--primary); font-weight:600;"><?= htmlspecialchars($t['specialization']) ?></div>
          </div>
          <span class="badge badge-success">● <?= strtoupper($t['status']) ?></span>
        </div>

        <div style="display:flex; flex-direction:column; gap:0.4rem; font-size:0.83rem; color:var(--text-secondary); background:var(--bg-main); padding:0.75rem; border-radius:8px; margin-bottom:1rem;">
          <div>📞 <strong>Phone:</strong> <?= htmlspecialchars($t['phone']) ?></div>
          <div>💵 <strong>Base Salary:</strong> <?= $currency ?><?= number_format($t['salary'], 2) ?></div>
          <div>⭐ <strong>Commission Rate:</strong> <?= number_format($t['commission_rate'], 1) ?>%</div>
          <div>⏰ <strong>Assigned Batches:</strong> <?= htmlspecialchars($t['assigned_batches']) ?></div>
        </div>

        <div style="display:flex; gap:0.5rem;">
          <a href="index.php?page=pt" class="btn btn-primary btn-block btn-sm">
            🏋️ View PT Sessions
          </a>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<!-- Add Trainer Modal -->
<div class="modal" id="addTrainerModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:100; align-items:center; justify-content:center;">
  <div class="modal-overlay" style="position:absolute; inset:0;" onclick="closeModal('addTrainerModal')"></div>
  <div class="card" style="position:relative; z-index:10; background:#fff; width:90%; max-width:450px; border-radius:16px; padding:1.5rem;">
    <div class="card-header" style="display:flex; justify-content:space-between; align-items:center;">
      <span class="card-title">➕ Add Fitness Trainer</span>
      <button style="background:none; border:none; font-size:1.2rem; cursor:pointer;" onclick="closeModal('addTrainerModal')">✕</button>
    </div>

    <form onsubmit="saveTrainer(event)">
      <div class="form-group">
        <label class="form-label">Full Name</label>
        <input type="text" name="name" class="form-control" placeholder="e.g. Vikram Singh" required>
      </div>

      <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.75rem;">
        <div class="form-group">
          <label class="form-label">Phone Number</label>
          <input type="text" name="phone" class="form-control" placeholder="+91 99887 76655" required>
        </div>
        <div class="form-group">
          <label class="form-label">Specialization</label>
          <input type="text" name="specialization" class="form-control" value="Crossfit & Strength">
        </div>
      </div>

      <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.75rem;">
        <div class="form-group">
          <label class="form-label">Monthly Salary (<?= $currency ?>)</label>
          <input type="number" name="salary" class="form-control" value="25000">
        </div>
        <div class="form-group">
          <label class="form-label">Commission Rate (%)</label>
          <input type="number" name="commission_rate" class="form-control" value="12">
        </div>
      </div>

      <div style="display:flex; gap:0.75rem; margin-top:1.5rem;">
        <button type="button" class="btn btn-secondary btn-block" onclick="closeModal('addTrainerModal')">Cancel</button>
        <button type="submit" class="btn btn-primary btn-block">✓ SAVE TRAINER</button>
      </div>
    </form>
  </div>
</div>

<script>
function saveTrainer(e) {
  e.preventDefault();
  const formData = new FormData(e.target);
  formData.append('action', 'add');

  fetch('api/trainers.php', {
    method: 'POST',
    body: formData
  })
  .then(res => res.json())
  .then(res => {
    showToast(res.message, 'success');
    closeModal('addTrainerModal');
    setTimeout(() => location.reload(), 1000);
  });
}

function sendPtOffBroadcast() {
  const msg = document.getElementById('ptOffBroadcastText').value.trim();
  if (!msg) {
    showToast('Notice message cannot be empty', 'warning');
    return;
  }

  showToast('Preparing Anti-Ban WhatsApp Queue...', 'info');
  fetch('api/marketing.php?action=broadcast', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      target_group: 'pt_members',
      message_text: msg
    })
  })
  .then(res => res.json())
  .then(res => {
    if (res.success) {
      showToast(res.message || 'PT Off Notice Dispatched to All PT Clients!', 'success');
      closeModal('broadcastPtOffModal');
    } else {
      showToast(res.message || 'Broadcast failed', 'danger');
    }
  });
}
</script>

<!-- BROADCAST PT OFF NOTICE MODAL -->
<div class="modal" id="broadcastPtOffModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.6); z-index:100; align-items:center; justify-content:center;">
  <div class="modal-overlay" style="position:absolute; inset:0;" onclick="closeModal('broadcastPtOffModal')"></div>
  <div class="card" style="position:relative; z-index:10; background:#fff; width:90%; max-width:550px; border-radius:18px; padding:1.5rem; border-top:5px solid #8B5CF6;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;">
      <span style="font-weight:800; font-size:1.15rem; color:#6D28D9;">💪 Broadcast Personal Training (PT) Off Notice</span>
      <button style="background:none; border:none; font-size:1.2rem; cursor:pointer;" onclick="closeModal('broadcastPtOffModal')">✕</button>
    </div>

    <div style="background:#F5F3FF; border:1px solid #DDD6FE; padding:0.75rem 1rem; border-radius:8px; margin-bottom:1rem; font-size:0.82rem; color:#5B21B6;">
      🎯 <strong>Audience:</strong> All <?= $activePtCount ?> Active Personal Training Clients
    </div>

    <div class="form-group">
      <label class="form-label">WhatsApp Notice Message (Editable)</label>
      <textarea id="ptOffBroadcastText" class="form-control" rows="7" style="font-family:inherit; font-size:0.85rem; line-height:1.4;">💪 *Notice: Personal Training (PT) Sessions Off Tomorrow* 💪

Dear {name},

Please note that Personal Training (PT) sessions with your assigned trainer will remain *OFF / SUSPENDED tomorrow (<?= date('d M Y', strtotime('+1 day')) ?>)* due to trainer workshop & schedule.

✅ *Note:* Your session count will *NOT* be deducted and will be adjusted in your package.

Regular 1-on-1 PT sessions will resume normally the day after. Keep up your fitness dedication!

For queries, contact your trainer or reception at <?= getSetting('gym_phone', '+91 98765 43210') ?>.</textarea>
    </div>

    <div style="display:flex; gap:0.75rem; margin-top:1.5rem;">
      <button type="button" class="btn btn-secondary btn-block" onclick="closeModal('broadcastPtOffModal')">Cancel</button>
      <button type="button" class="btn btn-block" onclick="sendPtOffBroadcast()" style="background:#8B5CF6; color:#fff; font-weight:800; border:none;">
        📢 SEND TO ALL PT CLIENTS (WHATSAPP)
      </button>
    </div>
  </div>
</div>
