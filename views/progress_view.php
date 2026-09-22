<!-- Body Measurement & Fitness Progress View -->
<?php
$db = getDB();
$members = $db->query("SELECT id, name, member_code FROM members WHERE status = 'active'")->fetchAll();

$measurements = $db->query("
    SELECT b.*, m.name as member_name, m.member_code 
    FROM body_measurements b 
    JOIN members m ON b.member_id = m.id 
    ORDER BY b.record_date DESC, b.id DESC LIMIT 40
")->fetchAll();

$totalLogs = count($measurements);
$avgBmi = $totalLogs > 0 ? round(array_sum(array_column($measurements, 'bmi')) / $totalLogs, 1) : 23.4;
$avgWeight = $totalLogs > 0 ? round(array_sum(array_column($measurements, 'weight_kg')) / $totalLogs, 1) : 72.5;
?>

<div class="page-content">
  <!-- Header & Actions -->
  <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1.25rem; margin-bottom:1.5rem;">
    <div>
      <h1 style="font-size:1.65rem; font-weight:900; color:var(--text-primary); letter-spacing:-0.03em; display:flex; align-items:center; gap:0.65rem;">
        <span style="background:var(--primary-light); color:var(--primary); width:42px; height:42px; border-radius:12px; display:inline-flex; align-items:center; justify-content:center; font-size:1.4rem; box-shadow:0 2px 8px var(--primary-glow);">📏</span>
        <span>Body Metrics &amp; Fitness Analytics</span>
      </h1>
      <p style="font-size:0.85rem; color:var(--text-secondary); margin-top:0.25rem;">Monitor member weight transformations, Body Mass Index (BMI), body fat %, and tape circumferences</p>
    </div>

    <button class="btn btn-primary" style="border-radius:var(--radius-md); font-weight:800; box-shadow:0 4px 12px var(--primary-glow);" onclick="openModal('addMeasurementModal')">
      📏 + Record Member Metrics
    </button>
  </div>

  <!-- KPI Metric Summary Cards -->
  <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:1.25rem; margin-bottom:1.75rem;">
    <div class="card" style="border-left:4px solid var(--primary); padding:1.25rem; border-radius:var(--radius-lg); box-shadow:var(--shadow-card);">
      <div style="font-size:0.75rem; font-weight:800; text-transform:uppercase; color:var(--text-muted); letter-spacing:0.05em;">Total Progress Logs</div>
      <div style="font-size:1.85rem; font-weight:900; color:var(--primary); margin-top:0.35rem; font-family:var(--font-heading);"><?= $totalLogs ?> Records</div>
      <div style="font-size:0.78rem; color:var(--text-secondary); margin-top:0.2rem;">Transformation check-ins captured</div>
    </div>

    <div class="card" style="border-left:4px solid var(--success); padding:1.25rem; border-radius:var(--radius-lg); box-shadow:var(--shadow-card);">
      <div style="font-size:0.75rem; font-weight:800; text-transform:uppercase; color:var(--text-muted); letter-spacing:0.05em;">Average Member BMI</div>
      <div style="font-size:1.85rem; font-weight:900; color:var(--success); margin-top:0.35rem; font-family:var(--font-heading);"><?= $avgBmi ?> BMI</div>
      <div style="font-size:0.78rem; color:var(--text-secondary); margin-top:0.2rem;">Healthy &amp; optimal wellness range</div>
    </div>

    <div class="card" style="border-left:4px solid var(--purple); padding:1.25rem; border-radius:var(--radius-lg); box-shadow:var(--shadow-card);">
      <div style="font-size:0.75rem; font-weight:800; text-transform:uppercase; color:var(--text-muted); letter-spacing:0.05em;">Average Client Weight</div>
      <div style="font-size:1.85rem; font-weight:900; color:var(--purple); margin-top:0.35rem; font-family:var(--font-heading);"><?= $avgWeight ?> kg</div>
      <div style="font-size:0.78rem; color:var(--text-secondary); margin-top:0.2rem;">Active gym member baseline</div>
    </div>
  </div>

  <!-- Search & Filter Bar -->
  <div class="card" style="padding:1rem 1.25rem; margin-bottom:1.5rem; border-radius:var(--radius-lg); box-shadow:var(--shadow-subtle);">
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem;">
      <div style="display:flex; gap:0.45rem; flex-wrap:wrap;" id="bmiFilterGroup">
        <button class="bmi-filter-chip active" onclick="filterBmi('ALL', this)">All Records</button>
        <button class="bmi-filter-chip" onclick="filterBmi('normal', this)">✅ Normal BMI (18.5 - 24.9)</button>
        <button class="bmi-filter-chip" onclick="filterBmi('overweight', this)">⚠️ Overweight (25.0 - 29.9)</button>
        <button class="bmi-filter-chip" onclick="filterBmi('obese', this)">🚨 Obese (30.0+)</button>
      </div>

      <div style="position:relative; min-width:240px;">
        <input type="text" id="bmiSearchInput" class="form-control" placeholder="🔍 Search member name or code..." oninput="searchBmi(this.value)" style="padding-left:2.2rem; height:38px; border-radius:var(--radius-full); font-size:0.82rem;">
      </div>
    </div>
  </div>

  <!-- Body Measurement Log Table -->
  <div class="card" style="border-radius:var(--radius-lg); box-shadow:var(--shadow-card); padding:1.25rem;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;">
      <span style="font-size:1.15rem; font-weight:800; color:var(--text-primary);">📏 Transformation &amp; Measurement History</span>
      <span style="font-size:0.8rem; color:var(--text-muted);" id="bmiCountLabel">Showing <?= count($measurements) ?> records</span>
    </div>

    <div class="table-wrapper">
      <table id="measurementsTable">
        <thead>
          <tr>
            <th>Member</th>
            <th>Check-in Date</th>
            <th>Weight</th>
            <th>Height</th>
            <th>Calculated BMI</th>
            <th>Body Fat %</th>
            <th>Tape Circumference (Chest / Waist / Arms)</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($measurements)): ?>
            <tr>
              <td colspan="7" style="text-align:center; padding:2rem; color:var(--text-muted);">
                📏 No transformation records logged yet. Click "Record Member Metrics" to log initial assessments.
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($measurements as $bm): 
              $bmiVal = floatval($bm['bmi']);
              $category = 'normal';
              $badgeStyle = 'background:#ECFDF5; color:#065F46; border:1px solid #A7F3D0;';
              if ($bmiVal >= 30) {
                $category = 'obese';
                $badgeStyle = 'background:#FEF2F2; color:#991B1B; border:1px solid #FECACA;';
              } elseif ($bmiVal >= 25) {
                $category = 'overweight';
                $badgeStyle = 'background:#FFFBEB; color:#92400E; border:1px solid #FDE68A;';
              } elseif ($bmiVal < 18.5) {
                $category = 'underweight';
                $badgeStyle = 'background:#F0F9FF; color:#0C4A6E; border:1px solid #BAE6FD;';
              }
            ?>
              <tr data-category="<?= $category ?>" data-member="<?= htmlspecialchars(strtolower($bm['member_name'])) ?>" data-code="<?= htmlspecialchars(strtolower($bm['member_code'])) ?>">
                <td>
                  <strong style="color:var(--text-primary);"><?= htmlspecialchars($bm['member_name']) ?></strong>
                  <div style="font-size:0.75rem; font-family:var(--font-mono); color:var(--text-muted);"><?= htmlspecialchars($bm['member_code']) ?></div>
                </td>
                <td><span class="badge badge-secondary font-mono"><?= date('d M Y', strtotime($bm['record_date'])) ?></span></td>
                <td><strong style="color:var(--primary); font-size:1.05rem; font-family:var(--font-mono);"><?= $bm['weight_kg'] ?> kg</strong></td>
                <td><span style="font-family:var(--font-mono);"><?= $bm['height_cm'] ?> cm</span></td>
                <td>
                  <span class="badge" style="<?= $badgeStyle ?> font-weight:800; font-family:var(--font-mono);">
                    BMI: <?= $bm['bmi'] ?>
                  </span>
                </td>
                <td><strong style="color:var(--purple);"><?= $bm['body_fat_pct'] > 0 ? $bm['body_fat_pct'] . '%' : 'N/A' ?></strong></td>
                <td style="font-size:0.84rem; color:var(--text-secondary);">
                  <div style="display:flex; gap:0.4rem;">
                    <span style="background:var(--bg-surface-secondary); padding:0.15rem 0.4rem; border-radius:4px;">Chest: <strong><?= $bm['chest_in'] ?>"</strong></span>
                    <span style="background:var(--bg-surface-secondary); padding:0.15rem 0.4rem; border-radius:4px;">Waist: <strong><?= $bm['waist_in'] ?>"</strong></span>
                    <span style="background:var(--bg-surface-secondary); padding:0.15rem 0.4rem; border-radius:4px;">Arms: <strong><?= $bm['arms_in'] ?>"</strong></span>
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

<!-- Add Measurement Modal -->
<div class="modal" id="addMeasurementModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.55); z-index:100; align-items:center; justify-content:center; backdrop-filter:blur(2px);">
  <div class="modal-overlay" style="position:absolute; inset:0;" onclick="closeModal('addMeasurementModal')"></div>
  <div class="card" style="position:relative; z-index:10; background:#fff; width:92%; max-width:520px; border-radius:18px; padding:1.5rem; box-shadow:var(--shadow-modal);">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.25rem; border-bottom:1px solid var(--border-color); padding-bottom:0.75rem;">
      <span style="font-size:1.15rem; font-weight:800; color:var(--text-primary); display:flex; align-items:center; gap:0.4rem;">
        <span>📏</span> <span>Log Member Body Check-in</span>
      </span>
      <button style="background:none; border:none; font-size:1.2rem; cursor:pointer; color:var(--text-muted);" onclick="closeModal('addMeasurementModal')">✕</button>
    </div>

    <form onsubmit="saveMeasurement(event)">
      <div class="form-group">
        <label class="form-label">Active Member *</label>
        <select name="member_id" class="form-control" required>
          <?php foreach ($members as $m): ?>
            <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['name']) ?> (<?= $m['member_code'] ?>)</option>
          <?php endforeach; ?>
        </select>
      </div>

      <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.75rem;">
        <div class="form-group">
          <label class="form-label">Current Weight (kg) *</label>
          <input type="number" step="0.1" name="weight_kg" id="modalWeight" class="form-control font-mono" value="74.0" oninput="calcModalBmi()" required>
        </div>
        <div class="form-group">
          <label class="form-label">Height (cm) *</label>
          <input type="number" step="0.1" name="height_cm" id="modalHeight" class="form-control font-mono" value="175.0" oninput="calcModalBmi()" required>
        </div>
      </div>

      <!-- Real-time Live BMI Preview Box -->
      <div style="background:#EFF6FF; border:1px solid #BFDBFE; border-radius:var(--radius-md); padding:0.65rem 0.85rem; margin-bottom:1rem; display:flex; justify-content:space-between; align-items:center;">
        <span style="font-size:0.8rem; font-weight:700; color:#1E40AF;">⚡ Auto-Calculated BMI:</span>
        <span id="modalBmiPreview" style="font-size:1.1rem; font-weight:900; color:#1D4ED8; font-family:var(--font-mono);">24.2 (Normal)</span>
      </div>

      <div style="display:grid; grid-template-columns:1fr 1fr 1fr 1fr; gap:0.4rem;">
        <div class="form-group">
          <label class="form-label">Body Fat %</label>
          <input type="number" step="0.1" name="body_fat_pct" class="form-control font-mono" value="17.5">
        </div>
        <div class="form-group">
          <label class="form-label">Chest (in)</label>
          <input type="number" step="0.1" name="chest_in" class="form-control font-mono" value="40.0">
        </div>
        <div class="form-group">
          <label class="form-label">Waist (in)</label>
          <input type="number" step="0.1" name="waist_in" class="form-control font-mono" value="32.0">
        </div>
        <div class="form-group">
          <label class="form-label">Arms (in)</label>
          <input type="number" step="0.1" name="arms_in" class="form-control font-mono" value="15.0">
        </div>
      </div>

      <div style="display:flex; gap:0.75rem; margin-top:1.5rem;">
        <button type="button" class="btn btn-secondary btn-block" onclick="closeModal('addMeasurementModal')">Cancel</button>
        <button type="submit" class="btn btn-primary btn-block" style="font-weight:800;">✓ SAVE MEASUREMENT</button>
      </div>
    </form>
  </div>
</div>

<style>
.bmi-filter-chip {
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
.bmi-filter-chip:hover {
  background: var(--bg-surface-hover);
  color: var(--primary);
  border-color: var(--primary-border);
}
.bmi-filter-chip.active {
  background: var(--primary);
  color: #fff;
  border-color: var(--primary);
  box-shadow: 0 2px 6px var(--primary-glow);
}
</style>

<script>
let currentBmiCategory = 'ALL';

function calcModalBmi() {
  const w = parseFloat(document.getElementById('modalWeight').value) || 0;
  const h = parseFloat(document.getElementById('modalHeight').value) || 0;
  const prevEl = document.getElementById('modalBmiPreview');
  if (!prevEl) return;

  if (w > 0 && h > 0) {
    const hm = h / 100;
    const bmi = (w / (hm * hm)).toFixed(1);
    let label = 'Normal';
    if (bmi >= 30) label = 'Obese';
    else if (bmi >= 25) label = 'Overweight';
    else if (bmi < 18.5) label = 'Underweight';
    prevEl.innerText = `${bmi} (${label})`;
  }
}

function filterBmi(cat, btn) {
  currentBmiCategory = cat;
  document.querySelectorAll('#bmiFilterGroup .bmi-filter-chip').forEach(b => b.classList.remove('active'));
  if (btn) btn.classList.add('active');
  applyBmiFilters();
}

function searchBmi(query) {
  applyBmiFilters();
}

function applyBmiFilters() {
  const query = (document.getElementById('bmiSearchInput').value || '').toLowerCase().trim();
  const rows = document.querySelectorAll('#measurementsTable tbody tr');
  let visibleCount = 0;

  rows.forEach(row => {
    const cat = row.getAttribute('data-category') || '';
    const member = row.getAttribute('data-member') || '';
    const code = row.getAttribute('data-code') || '';

    const matchesCat = (currentBmiCategory === 'ALL') || (cat.toLowerCase() === currentBmiCategory.toLowerCase());
    const matchesSearch = !query || member.includes(query) || code.includes(query);

    if (matchesCat && matchesSearch) {
      row.style.display = '';
      visibleCount++;
    } else {
      row.style.display = 'none';
    }
  });

  const countLabel = document.getElementById('bmiCountLabel');
  if (countLabel) countLabel.innerText = `Showing ${visibleCount} records`;
}

function saveMeasurement(e) {
  e.preventDefault();
  const formData = new FormData(e.target);
  formData.append('action', 'log_body_measurement');

  fetch('api/fitness.php', {
    method: 'POST',
    body: formData
  })
  .then(res => res.json())
  .then(res => {
    if (res.success) {
      showToast(res.message, 'success');
      closeModal('addMeasurementModal');
      setTimeout(() => location.reload(), 1000);
    } else {
      showToast(res.message || 'Saving failed', 'danger');
    }
  });
}
</script>
