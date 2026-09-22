<!-- Workout Management & Exercise Library View -->
<?php
$db = getDB();
$exercises = $db->query("SELECT * FROM exercises ORDER BY id ASC")->fetchAll();
$members = $db->query("SELECT id, name, member_code FROM members WHERE status = 'active'")->fetchAll();

// Fetch Assigned Workout Plans with Member & Exercise details
$assignedWorkouts = $db->query("
    SELECT wp.*, m.name as member_name, m.member_code, e.name as exercise_name, e.muscle_group, e.equipment 
    FROM workout_plans wp
    JOIN members m ON wp.member_id = m.id
    JOIN exercises e ON wp.exercise_id = e.id
    ORDER BY wp.id DESC LIMIT 30
")->fetchAll();

$totalExercises = count($exercises);
$muscleGroupsCount = count(array_unique(array_column($exercises, 'muscle_group')));
$totalAssignedPlans = count($assignedWorkouts);
?>

<div class="page-content">
  <!-- Page Header & Actions -->
  <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1.25rem; margin-bottom:1.5rem;">
    <div>
      <h1 style="font-size:1.65rem; font-weight:900; color:var(--text-primary); letter-spacing:-0.03em; display:flex; align-items:center; gap:0.65rem;">
        <span style="background:var(--primary-light); color:var(--primary); width:42px; height:42px; border-radius:12px; display:inline-flex; align-items:center; justify-content:center; font-size:1.4rem; box-shadow:0 2px 8px var(--primary-glow);">🏋️</span>
        <span>Workout Plans &amp; Exercise Hub</span>
      </h1>
      <p style="font-size:0.85rem; color:var(--text-secondary); margin-top:0.25rem;">Design custom training routines, muscle splits, sets, reps, and assign structured workout regimens</p>
    </div>

    <div style="display:flex; gap:0.65rem; flex-wrap:wrap;">
      <button class="btn btn-outline" style="border-radius:var(--radius-md); font-weight:700; background:var(--bg-surface);" onclick="openModal('addExerciseModal')">
        ➕ Add Exercise to Catalog
      </button>
      <button class="btn btn-primary" style="border-radius:var(--radius-md); font-weight:800; box-shadow:0 4px 12px var(--primary-glow);" onclick="openModal('assignWorkoutModal')">
        📋 + Assign Routine to Member
      </button>
    </div>
  </div>

  <!-- KPI Metric Overview Cards -->
  <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:1.25rem; margin-bottom:1.75rem;">
    <div class="card" style="border-left:4px solid var(--primary); padding:1.25rem; border-radius:var(--radius-lg); box-shadow:var(--shadow-card);">
      <div style="font-size:0.75rem; font-weight:800; text-transform:uppercase; color:var(--text-muted); letter-spacing:0.05em;">Exercise Catalog</div>
      <div style="font-size:1.85rem; font-weight:900; color:var(--primary); margin-top:0.35rem; font-family:var(--font-heading);"><?= $totalExercises ?> Moves</div>
      <div style="font-size:0.78rem; color:var(--text-secondary); margin-top:0.2rem;">Multi-muscle strength &amp; cardio library</div>
    </div>

    <div class="card" style="border-left:4px solid var(--success); padding:1.25rem; border-radius:var(--radius-lg); box-shadow:var(--shadow-card);">
      <div style="font-size:0.75rem; font-weight:800; text-transform:uppercase; color:var(--text-muted); letter-spacing:0.05em;">Muscle Splits</div>
      <div style="font-size:1.85rem; font-weight:900; color:var(--success); margin-top:0.35rem; font-family:var(--font-heading);"><?= $muscleGroupsCount ?> Groups</div>
      <div style="font-size:0.78rem; color:var(--text-secondary); margin-top:0.2rem;">Chest, Back, Legs, Core &amp; Conditioning</div>
    </div>

    <div class="card" style="border-left:4px solid var(--purple); padding:1.25rem; border-radius:var(--radius-lg); box-shadow:var(--shadow-card);">
      <div style="font-size:0.75rem; font-weight:800; text-transform:uppercase; color:var(--text-muted); letter-spacing:0.05em;">Active Routines</div>
      <div style="font-size:1.85rem; font-weight:900; color:var(--purple); margin-top:0.35rem; font-family:var(--font-heading);"><?= $totalAssignedPlans ?> Schedules</div>
      <div style="font-size:0.78rem; color:var(--text-secondary); margin-top:0.2rem;">Assigned to active gym members</div>
    </div>
  </div>

  <!-- Interactive Muscle Group Filter & Live Search Toolbar -->
  <div class="card" style="padding:1rem 1.25rem; margin-bottom:1.5rem; border-radius:var(--radius-lg); box-shadow:var(--shadow-subtle);">
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem;">
      <!-- Filter Chips -->
      <div style="display:flex; gap:0.45rem; flex-wrap:wrap;" id="muscleFilterGroup">
        <button class="muscle-filter-chip active" onclick="filterExercises('ALL', this)">All (<?= count($exercises) ?>)</button>
        <button class="muscle-filter-chip" onclick="filterExercises('Chest', this)">💥 Chest</button>
        <button class="muscle-filter-chip" onclick="filterExercises('Back', this)">🦅 Back</button>
        <button class="muscle-filter-chip" onclick="filterExercises('Legs', this)">🦵 Legs</button>
        <button class="muscle-filter-chip" onclick="filterExercises('Shoulders', this)">⚡ Shoulders</button>
        <button class="muscle-filter-chip" onclick="filterExercises('Biceps', this)">💪 Biceps</button>
        <button class="muscle-filter-chip" onclick="filterExercises('Triceps', this)">🔨 Triceps</button>
        <button class="muscle-filter-chip" onclick="filterExercises('Abs/Core', this)">🔥 Core</button>
        <button class="muscle-filter-chip" onclick="filterExercises('Cardio', this)">🏃 Cardio</button>
      </div>

      <!-- Quick Search Input -->
      <div style="position:relative; min-width:240px;">
        <input type="text" id="exerciseSearchInput" class="form-control" placeholder="🔍 Search exercise or equipment..." oninput="searchExercises(this.value)" style="padding-left:2.2rem; height:38px; border-radius:var(--radius-full); font-size:0.82rem;">
      </div>
    </div>
  </div>

  <!-- Exercise Library Grid -->
  <div style="margin-bottom:2rem;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;">
      <h3 style="font-size:1.15rem; font-weight:800; color:var(--text-primary); margin:0;">💪 Exercise Library Catalog</h3>
      <span style="font-size:0.8rem; color:var(--text-muted);" id="exerciseCountLabel">Showing <?= count($exercises) ?> exercises</span>
    </div>

    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(290px, 1fr)); gap:1.15rem;" id="exerciseCatalogGrid">
      <?php foreach ($exercises as $ex): 
        $badgeClass = 'badge-primary';
        $mg = strtolower($ex['muscle_group']);
        if ($mg === 'chest') $badgeClass = 'badge-primary';
        elseif ($mg === 'back') $badgeClass = 'badge-info';
        elseif ($mg === 'legs') $badgeClass = 'badge-success';
        elseif ($mg === 'shoulders') $badgeClass = 'badge-warning';
        elseif (strpos($mg, 'core') !== false || strpos($mg, 'abs') !== false) $badgeClass = 'badge-danger';
      ?>
        <div class="exercise-card" data-muscle="<?= htmlspecialchars($ex['muscle_group']) ?>" data-name="<?= htmlspecialchars(strtolower($ex['name'])) ?>" data-equip="<?= htmlspecialchars(strtolower($ex['equipment'])) ?>">
          <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:0.6rem;">
            <strong style="font-size:1.05rem; color:var(--text-primary); font-family:var(--font-heading);"><?= htmlspecialchars($ex['name']) ?></strong>
            <span class="badge <?= $badgeClass ?>" style="font-size:0.72rem; padding:0.25rem 0.6rem;"><?= htmlspecialchars($ex['muscle_group']) ?></span>
          </div>
          
          <div style="display:flex; flex-direction:column; gap:0.4rem; font-size:0.82rem; color:var(--text-secondary); background:var(--bg-surface-secondary); padding:0.75rem; border-radius:var(--radius-md); margin-bottom:0.75rem;">
            <div>🏋️ <strong>Equipment:</strong> <?= htmlspecialchars($ex['equipment']) ?></div>
            <div>⭐ <strong>Level:</strong> <span style="font-weight:700; color:<?= $ex['difficulty'] === 'Advanced' ? 'var(--danger)' : ($ex['difficulty'] === 'Intermediate' ? 'var(--warning)' : 'var(--success)') ?>"><?= htmlspecialchars($ex['difficulty']) ?></span></div>
          </div>

          <div style="font-size:0.78rem; color:var(--text-muted); line-height:1.45; min-height:40px;">
            <?= htmlspecialchars($ex['instructions'] ?: 'Standard execution form with full range of motion & controlled tempo.') ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Assigned Member Workout Plans Table -->
  <div class="card" style="border-radius:var(--radius-lg); box-shadow:var(--shadow-card); padding:1.25rem;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem; flex-wrap:wrap; gap:0.75rem;">
      <div>
        <h3 style="font-size:1.15rem; font-weight:800; color:var(--text-primary); margin:0;">📋 Assigned Member Daily Routines</h3>
        <p style="font-size:0.8rem; color:var(--text-muted); margin:0.2rem 0 0 0;">Recent exercise assignments, sets, reps and target weights</p>
      </div>
      <button class="btn btn-primary btn-sm" onclick="openModal('assignWorkoutModal')">
        + Assign New Routine
      </button>
    </div>

    <div class="table-wrapper">
      <table>
        <thead>
          <tr>
            <th>Member</th>
            <th>Day of Week</th>
            <th>Exercise Movement</th>
            <th>Muscle Target</th>
            <th>Sets x Reps</th>
            <th>Target Load</th>
            <th>Rest Interval</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($assignedWorkouts)): ?>
            <tr>
              <td colspan="7" style="text-align:center; padding:2rem; color:var(--text-muted);">
                🏋️ No member workout plans assigned yet. Click "Assign Member Workout Plan" to create routine schedules.
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($assignedWorkouts as $wp): ?>
              <tr>
                <td>
                  <strong style="color:var(--text-primary);"><?= htmlspecialchars($wp['member_name']) ?></strong>
                  <div style="font-size:0.74rem; font-family:var(--font-mono); color:var(--text-muted);"><?= htmlspecialchars($wp['member_code']) ?></div>
                </td>
                <td><span class="badge badge-primary font-mono"><?= htmlspecialchars($wp['day_of_week']) ?></span></td>
                <td><strong style="color:var(--text-primary);"><?= htmlspecialchars($wp['exercise_name']) ?></strong></td>
                <td><span class="badge badge-info"><?= htmlspecialchars($wp['muscle_group']) ?></span></td>
                <td><strong><?= htmlspecialchars($wp['sets']) ?> Sets</strong> &times; <?= htmlspecialchars($wp['reps']) ?></td>
                <td><span style="font-weight:800; color:var(--primary);"><?= $wp['weight_kg'] > 0 ? $wp['weight_kg'] . ' kg' : 'Bodyweight' ?></span></td>
                <td>⏱️ <?= htmlspecialchars($wp['rest_time_sec']) ?>s rest</td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Add Exercise Modal -->
<div class="modal" id="addExerciseModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.55); z-index:100; align-items:center; justify-content:center; backdrop-filter:blur(2px);">
  <div class="modal-overlay" style="position:absolute; inset:0;" onclick="closeModal('addExerciseModal')"></div>
  <div class="card" style="position:relative; z-index:10; background:#fff; width:92%; max-width:480px; border-radius:18px; padding:1.5rem; box-shadow:var(--shadow-modal);">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.25rem; border-bottom:1px solid var(--border-color); padding-bottom:0.75rem;">
      <span style="font-size:1.15rem; font-weight:800; color:var(--text-primary); display:flex; align-items:center; gap:0.4rem;">
        <span>➕</span> <span>Add Exercise to Library</span>
      </span>
      <button style="background:none; border:none; font-size:1.2rem; cursor:pointer; color:var(--text-muted);" onclick="closeModal('addExerciseModal')">✕</button>
    </div>

    <form onsubmit="saveExercise(event)">
      <div class="form-group">
        <label class="form-label">Exercise Name *</label>
        <input type="text" name="name" class="form-control" placeholder="e.g. Incline Dumbbell Bench Press" required>
      </div>

      <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.75rem;">
        <div class="form-group">
          <label class="form-label">Muscle Target *</label>
          <select name="muscle_group" class="form-control">
            <option value="Chest">Chest</option>
            <option value="Back">Back</option>
            <option value="Legs">Legs</option>
            <option value="Shoulders">Shoulders</option>
            <option value="Biceps">Biceps</option>
            <option value="Triceps">Triceps</option>
            <option value="Abs/Core">Abs/Core</option>
            <option value="Cardio">Cardio</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Skill Difficulty</label>
          <select name="difficulty" class="form-control">
            <option value="Beginner">Beginner</option>
            <option value="Intermediate" selected>Intermediate</option>
            <option value="Advanced">Advanced</option>
          </select>
        </div>
      </div>

      <div class="form-group">
        <label class="form-label">Equipment Required</label>
        <input type="text" name="equipment" class="form-control" value="Dumbbells / Incline Bench">
      </div>

      <div class="form-group">
        <label class="form-label">Execution Form &amp; Technique Notes</label>
        <textarea name="instructions" class="form-control" rows="2" placeholder="Key cue notes, grip width, posture & tempo..."></textarea>
      </div>

      <div style="display:flex; gap:0.75rem; margin-top:1.5rem;">
        <button type="button" class="btn btn-secondary btn-block" onclick="closeModal('addExerciseModal')">Cancel</button>
        <button type="submit" class="btn btn-primary btn-block" style="font-weight:800;">✓ SAVE EXERCISE</button>
      </div>
    </form>
  </div>
</div>

<!-- Assign Workout Modal -->
<div class="modal" id="assignWorkoutModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.55); z-index:100; align-items:center; justify-content:center; backdrop-filter:blur(2px);">
  <div class="modal-overlay" style="position:absolute; inset:0;" onclick="closeModal('assignWorkoutModal')"></div>
  <div class="card" style="position:relative; z-index:10; background:#fff; width:92%; max-width:500px; border-radius:18px; padding:1.5rem; box-shadow:var(--shadow-modal);">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.25rem; border-bottom:1px solid var(--border-color); padding-bottom:0.75rem;">
      <span style="font-size:1.15rem; font-weight:800; color:var(--text-primary); display:flex; align-items:center; gap:0.4rem;">
        <span>📋</span> <span>Assign Member Routine Item</span>
      </span>
      <button style="background:none; border:none; font-size:1.2rem; cursor:pointer; color:var(--text-muted);" onclick="closeModal('assignWorkoutModal')">✕</button>
    </div>

    <form onsubmit="saveWorkoutPlan(event)">
      <div class="form-group">
        <label class="form-label">Select Active Member *</label>
        <select name="member_id" class="form-control" required>
          <?php foreach ($members as $m): ?>
            <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['name']) ?> (<?= $m['member_code'] ?>)</option>
          <?php endforeach; ?>
        </select>
      </div>

      <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.75rem;">
        <div class="form-group">
          <label class="form-label">Day of Week *</label>
          <select name="day_of_week" class="form-control">
            <option value="Monday">Monday (Chest &amp; Triceps)</option>
            <option value="Tuesday">Tuesday (Back &amp; Biceps)</option>
            <option value="Wednesday">Wednesday (Shoulders &amp; Abs)</option>
            <option value="Thursday">Thursday (Legs &amp; Calves)</option>
            <option value="Friday">Friday (Full Body / Cardio)</option>
            <option value="Saturday">Saturday (Core &amp; Conditioning)</option>
          </select>
        </div>

        <div class="form-group">
          <label class="form-label">Exercise Movement *</label>
          <select name="exercise_id" class="form-control" required>
            <?php foreach ($exercises as $ex): ?>
              <option value="<?= $ex['id'] ?>"><?= htmlspecialchars($ex['name']) ?> (<?= $ex['muscle_group'] ?>)</option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:0.5rem;">
        <div class="form-group">
          <label class="form-label">Sets</label>
          <input type="number" name="sets" class="form-control font-mono" value="4">
        </div>
        <div class="form-group">
          <label class="form-label">Reps</label>
          <input type="text" name="reps" class="form-control font-mono" value="10-12">
        </div>
        <div class="form-group">
          <label class="form-label">Rest (sec)</label>
          <input type="number" name="rest_time_sec" class="form-control font-mono" value="60">
        </div>
      </div>

      <div class="form-group">
        <label class="form-label">Target Starting Load (kg, optional)</label>
        <input type="number" step="0.5" name="weight_kg" class="form-control font-mono" placeholder="e.g. 20 (or leave 0 for Bodyweight)">
      </div>

      <div style="display:flex; gap:0.75rem; margin-top:1.5rem;">
        <button type="button" class="btn btn-secondary btn-block" onclick="closeModal('assignWorkoutModal')">Cancel</button>
        <button type="submit" class="btn btn-primary btn-block" style="font-weight:800;">✓ ASSIGN TO MEMBER</button>
      </div>
    </form>
  </div>
</div>

<style>
.muscle-filter-chip {
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
.muscle-filter-chip:hover {
  background: var(--bg-surface-hover);
  color: var(--primary);
  border-color: var(--primary-border);
}
.muscle-filter-chip.active {
  background: var(--primary);
  color: #fff;
  border-color: var(--primary);
  box-shadow: 0 2px 6px var(--primary-glow);
}
.exercise-card {
  background: var(--bg-surface);
  border: 1px solid var(--border-color);
  border-radius: var(--radius-lg);
  padding: 1.15rem;
  box-shadow: var(--shadow-card);
  transition: all var(--transition-normal);
}
.exercise-card:hover {
  transform: translateY(-3px);
  box-shadow: var(--shadow-float);
  border-color: var(--primary-border);
}
</style>

<script>
let currentMuscleFilter = 'ALL';

function filterExercises(muscle, btn) {
  currentMuscleFilter = muscle;
  document.querySelectorAll('#muscleFilterGroup .muscle-filter-chip').forEach(b => b.classList.remove('active'));
  if (btn) btn.classList.add('active');
  applyFilters();
}

function searchExercises(query) {
  applyFilters();
}

function applyFilters() {
  const query = (document.getElementById('exerciseSearchInput').value || '').toLowerCase().trim();
  const cards = document.querySelectorAll('#exerciseCatalogGrid .exercise-card');
  let visibleCount = 0;

  cards.forEach(card => {
    const cardMuscle = card.getAttribute('data-muscle') || '';
    const cardName = card.getAttribute('data-name') || '';
    const cardEquip = card.getAttribute('data-equip') || '';

    const matchesMuscle = (currentMuscleFilter === 'ALL') || (cardMuscle.toLowerCase() === currentMuscleFilter.toLowerCase());
    const matchesSearch = !query || cardName.includes(query) || cardEquip.includes(query) || cardMuscle.toLowerCase().includes(query);

    if (matchesMuscle && matchesSearch) {
      card.style.display = 'block';
      visibleCount++;
    } else {
      card.style.display = 'none';
    }
  });

  const countLabel = document.getElementById('exerciseCountLabel');
  if (countLabel) countLabel.innerText = `Showing ${visibleCount} exercises`;
}

function saveExercise(e) {
  e.preventDefault();
  const formData = new FormData(e.target);
  formData.append('action', 'add_exercise');

  fetch('api/fitness.php', {
    method: 'POST',
    body: formData
  })
  .then(res => res.json())
  .then(res => {
    if (res.success) {
      showToast(res.message, 'success');
      closeModal('addExerciseModal');
      setTimeout(() => location.reload(), 1000);
    } else {
      showToast(res.message || 'Saving failed', 'danger');
    }
  });
}

function saveWorkoutPlan(e) {
  e.preventDefault();
  const formData = new FormData(e.target);
  formData.append('action', 'save_workout_plan');

  fetch('api/fitness.php', {
    method: 'POST',
    body: formData
  })
  .then(res => res.json())
  .then(res => {
    if (res.success) {
      showToast(res.message, 'success');
      closeModal('assignWorkoutModal');
      setTimeout(() => location.reload(), 1000);
    } else {
      showToast(res.message || 'Assignment failed', 'danger');
    }
  });
}
</script>
