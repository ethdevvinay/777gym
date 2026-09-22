<!-- Diet & Nutrition Management View -->
<?php
$db = getDB();
$members = $db->query("SELECT id, name, member_code FROM members WHERE status = 'active'")->fetchAll();

$dietPlans = $db->query("
    SELECT d.*, m.name as member_name, m.member_code 
    FROM diet_plans d 
    JOIN members m ON d.member_id = m.id 
    ORDER BY d.id DESC LIMIT 40
")->fetchAll();

$totalPlans = count($dietPlans);
$avgCalories = $totalPlans > 0 ? round(array_sum(array_column($dietPlans, 'calories')) / $totalPlans) : 2200;
$avgProtein = $totalPlans > 0 ? round(array_sum(array_column($dietPlans, 'protein_g')) / $totalPlans) : 130;
?>

<div class="page-content">
  <!-- Header & Actions -->
  <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1.25rem; margin-bottom:1.5rem;">
    <div>
      <h1 style="font-size:1.65rem; font-weight:900; color:var(--text-primary); letter-spacing:-0.03em; display:flex; align-items:center; gap:0.65rem;">
        <span style="background:#ECFDF5; color:#059669; width:42px; height:42px; border-radius:12px; display:inline-flex; align-items:center; justify-content:center; font-size:1.4rem; box-shadow:0 2px 8px rgba(16,185,129,0.2);">🥗</span>
        <span>Diet &amp; Nutrition Protocols</span>
      </h1>
      <p style="font-size:0.85rem; color:var(--text-secondary); margin-top:0.25rem;">Prescribe customized caloric goals, macro splits (Protein/Carbs/Fats) &amp; hydration schedules</p>
    </div>

    <button class="btn btn-primary" style="border-radius:var(--radius-md); font-weight:800; box-shadow:0 4px 12px var(--primary-glow);" onclick="openModal('addDietModal')">
      🥗 + Assign Member Meal Plan
    </button>
  </div>

  <!-- KPI Metric Summary Cards -->
  <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:1.25rem; margin-bottom:1.75rem;">
    <div class="card" style="border-left:4px solid var(--success); padding:1.25rem; border-radius:var(--radius-lg); box-shadow:var(--shadow-card);">
      <div style="font-size:0.75rem; font-weight:800; text-transform:uppercase; color:var(--text-muted); letter-spacing:0.05em;">Active Meal Protocols</div>
      <div style="font-size:1.85rem; font-weight:900; color:var(--success); margin-top:0.35rem; font-family:var(--font-heading);"><?= $totalPlans ?> Plans</div>
      <div style="font-size:0.78rem; color:var(--text-secondary); margin-top:0.2rem;">Prescribed nutrition schedules</div>
    </div>

    <div class="card" style="border-left:4px solid var(--warning); padding:1.25rem; border-radius:var(--radius-lg); box-shadow:var(--shadow-card);">
      <div style="font-size:0.75rem; font-weight:800; text-transform:uppercase; color:var(--text-muted); letter-spacing:0.05em;">Avg Daily Calories</div>
      <div style="font-size:1.85rem; font-weight:900; color:var(--warning); margin-top:0.35rem; font-family:var(--font-heading);"><?= $avgCalories ?> kcal</div>
      <div style="font-size:0.78rem; color:var(--text-secondary); margin-top:0.2rem;">Target daily intake baseline</div>
    </div>

    <div class="card" style="border-left:4px solid var(--primary); padding:1.25rem; border-radius:var(--radius-lg); box-shadow:var(--shadow-card);">
      <div style="font-size:0.75rem; font-weight:800; text-transform:uppercase; color:var(--text-muted); letter-spacing:0.05em;">Avg Target Protein</div>
      <div style="font-size:1.85rem; font-weight:900; color:var(--primary); margin-top:0.35rem; font-family:var(--font-heading);"><?= $avgProtein ?>g / Day</div>
      <div style="font-size:0.78rem; color:var(--text-secondary); margin-top:0.2rem;">Muscle repair &amp; synthesis target</div>
    </div>
  </div>

  <!-- Search & Filter Bar -->
  <div class="card" style="padding:1rem 1.25rem; margin-bottom:1.5rem; border-radius:var(--radius-lg); box-shadow:var(--shadow-subtle);">
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem;">
      <div style="display:flex; gap:0.45rem; flex-wrap:wrap;" id="dietFilterGroup">
        <button class="diet-filter-chip active" onclick="filterDiet('ALL', this)">All Slots</button>
        <button class="diet-filter-chip" onclick="filterDiet('Breakfast', this)">🍳 Breakfast</button>
        <button class="diet-filter-chip" onclick="filterDiet('Lunch', this)">🍛 Lunch</button>
        <button class="diet-filter-chip" onclick="filterDiet('Snacks', this)">🥜 Pre/Post Snacks</button>
        <button class="diet-filter-chip" onclick="filterDiet('Dinner', this)">🍲 Dinner</button>
      </div>

      <div style="position:relative; min-width:240px;">
        <input type="text" id="dietSearchInput" class="form-control" placeholder="🔍 Search member or food items..." oninput="searchDiet(this.value)" style="padding-left:2.2rem; height:38px; border-radius:var(--radius-full); font-size:0.82rem;">
      </div>
    </div>
  </div>

  <!-- Assigned Diet Plans Table -->
  <div class="card" style="border-radius:var(--radius-lg); box-shadow:var(--shadow-card); padding:1.25rem;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;">
      <span style="font-size:1.15rem; font-weight:800; color:var(--text-primary);">🥗 Prescribed Meal Schedules</span>
      <span style="font-size:0.8rem; color:var(--text-muted);" id="dietCountLabel">Showing <?= count($dietPlans) ?> meal plans</span>
    </div>

    <div class="table-wrapper">
      <table id="dietPlansTable">
        <thead>
          <tr>
            <th>Member</th>
            <th>Meal Slot</th>
            <th>Prescribed Food Items</th>
            <th>Caloric Target</th>
            <th>Macro Split (P / C / F)</th>
            <th>Daily Water Target</th>
            <th>Dietary Category</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($dietPlans)): ?>
            <tr>
              <td colspan="7" style="text-align:center; padding:2rem; color:var(--text-muted);">
                🥗 No member diet plans assigned yet. Click "Assign Member Meal Plan" to add personalized protocols.
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($dietPlans as $plan): ?>
              <tr data-slot="<?= htmlspecialchars($plan['meal_type']) ?>" data-member="<?= htmlspecialchars(strtolower($plan['member_name'])) ?>" data-food="<?= htmlspecialchars(strtolower($plan['food_items'])) ?>">
                <td>
                  <strong style="color:var(--text-primary);"><?= htmlspecialchars($plan['member_name']) ?></strong>
                  <div style="font-size:0.75rem; font-family:var(--font-mono); color:var(--text-muted);"><?= htmlspecialchars($plan['member_code']) ?></div>
                </td>
                <td><span class="badge badge-info"><?= htmlspecialchars($plan['meal_type']) ?></span></td>
                <td style="max-width:320px; font-size:0.85rem; color:var(--text-secondary); line-height:1.4;"><?= htmlspecialchars($plan['food_items']) ?></td>
                <td><strong style="color:var(--warning); font-size:0.95rem;"><?= $plan['calories'] ?> kcal</strong></td>
                <td>
                  <div style="font-size:0.82rem; display:flex; gap:0.4rem;">
                    <span style="background:#EFF6FF; color:#1D4ED8; padding:0.15rem 0.4rem; border-radius:4px; font-weight:700;">P: <?= $plan['protein_g'] ?>g</span>
                    <span style="background:#FEF3C7; color:#B45309; padding:0.15rem 0.4rem; border-radius:4px; font-weight:700;">C: <?= $plan['carbs_g'] ?>g</span>
                    <span style="background:#FEE2E2; color:#B91C1C; padding:0.15rem 0.4rem; border-radius:4px; font-weight:700;">F: <?= $plan['fats_g'] ?>g</span>
                  </div>
                </td>
                <td><span style="font-weight:700; color:#0284C7;">💧 <?= $plan['water_intake_liters'] ?> L / day</span></td>
                <td><span class="badge badge-secondary"><?= htmlspecialchars($plan['food_restrictions'] ?: 'General Fitness') ?></span></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Add Diet Modal -->
<div class="modal" id="addDietModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.55); z-index:100; align-items:center; justify-content:center; backdrop-filter:blur(2px);">
  <div class="modal-overlay" style="position:absolute; inset:0;" onclick="closeModal('addDietModal')"></div>
  <div class="card" style="position:relative; z-index:10; background:#fff; width:92%; max-width:540px; border-radius:18px; padding:1.5rem; box-shadow:var(--shadow-modal);">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.25rem; border-bottom:1px solid var(--border-color); padding-bottom:0.75rem;">
      <span style="font-size:1.15rem; font-weight:800; color:var(--text-primary); display:flex; align-items:center; gap:0.4rem;">
        <span>🥗</span> <span>Assign Member Nutrition Plan</span>
      </span>
      <button style="background:none; border:none; font-size:1.2rem; cursor:pointer; color:var(--text-muted);" onclick="closeModal('addDietModal')">✕</button>
    </div>

    <form onsubmit="saveDietPlan(event)">
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
          <label class="form-label">Meal Slot *</label>
          <select name="meal_type" class="form-control">
            <option value="Breakfast">Breakfast (8:00 AM)</option>
            <option value="Lunch">Lunch (1:30 PM)</option>
            <option value="Snacks">Pre/Post Workout Snacks</option>
            <option value="Dinner">Dinner (8:30 PM)</option>
          </select>
        </div>

        <div class="form-group">
          <label class="form-label">Daily Hydration Goal (Liters)</label>
          <input type="number" step="0.5" name="water_intake_liters" class="form-control font-mono" value="4.0">
        </div>
      </div>

      <div class="form-group">
        <label class="form-label">Prescribed Food Items &amp; Portion Sizes *</label>
        <textarea name="food_items" class="form-control" rows="3" placeholder="e.g. 4 Boiled Eggs (1 yolk) + 60g Rolled Oats with 1 Scoop Whey Protein + 1 Banana" required></textarea>
      </div>

      <div style="display:grid; grid-template-columns:1fr 1fr 1fr 1fr; gap:0.5rem;">
        <div class="form-group">
          <label class="form-label">Calories</label>
          <input type="number" name="calories" class="form-control font-mono" value="500">
        </div>
        <div class="form-group">
          <label class="form-label">Protein (g)</label>
          <input type="number" name="protein_g" class="form-control font-mono" value="40">
        </div>
        <div class="form-group">
          <label class="form-label">Carbs (g)</label>
          <input type="number" name="carbs_g" class="form-control font-mono" value="50">
        </div>
        <div class="form-group">
          <label class="form-label">Fats (g)</label>
          <input type="number" name="fats_g" class="form-control font-mono" value="12">
        </div>
      </div>

      <div class="form-group">
        <label class="form-label">Dietary Preference / Category</label>
        <input type="text" name="food_restrictions" class="form-control" value="High Protein Lean Bulk">
      </div>

      <div style="display:flex; gap:0.75rem; margin-top:1.5rem;">
        <button type="button" class="btn btn-secondary btn-block" onclick="closeModal('addDietModal')">Cancel</button>
        <button type="submit" class="btn btn-primary btn-block" style="font-weight:800;">✓ ASSIGN NUTRITION PLAN</button>
      </div>
    </form>
  </div>
</div>

<style>
.diet-filter-chip {
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
.diet-filter-chip:hover {
  background: var(--bg-surface-hover);
  color: var(--primary);
  border-color: var(--primary-border);
}
.diet-filter-chip.active {
  background: var(--success);
  color: #fff;
  border-color: var(--success);
  box-shadow: 0 2px 6px rgba(16,185,129,0.3);
}
</style>

<script>
let currentDietSlot = 'ALL';

function filterDiet(slot, btn) {
  currentDietSlot = slot;
  document.querySelectorAll('#dietFilterGroup .diet-filter-chip').forEach(b => b.classList.remove('active'));
  if (btn) btn.classList.add('active');
  applyDietFilters();
}

function searchDiet(query) {
  applyDietFilters();
}

function applyDietFilters() {
  const query = (document.getElementById('dietSearchInput').value || '').toLowerCase().trim();
  const rows = document.querySelectorAll('#dietPlansTable tbody tr');
  let visibleCount = 0;

  rows.forEach(row => {
    const slot = row.getAttribute('data-slot') || '';
    const member = row.getAttribute('data-member') || '';
    const food = row.getAttribute('data-food') || '';

    const matchesSlot = (currentDietSlot === 'ALL') || (slot.toLowerCase() === currentDietSlot.toLowerCase());
    const matchesSearch = !query || member.includes(query) || food.includes(query) || slot.toLowerCase().includes(query);

    if (matchesSlot && matchesSearch) {
      row.style.display = '';
      visibleCount++;
    } else {
      row.style.display = 'none';
    }
  });

  const countLabel = document.getElementById('dietCountLabel');
  if (countLabel) countLabel.innerText = `Showing ${visibleCount} meal plans`;
}

function saveDietPlan(e) {
  e.preventDefault();
  const formData = new FormData(e.target);
  formData.append('action', 'save_diet_plan');

  fetch('api/fitness.php', {
    method: 'POST',
    body: formData
  })
  .then(res => res.json())
  .then(res => {
    if (res.success) {
      showToast(res.message, 'success');
      closeModal('addDietModal');
      setTimeout(() => location.reload(), 1000);
    } else {
      showToast(res.message || 'Saving failed', 'danger');
    }
  });
}
</script>
