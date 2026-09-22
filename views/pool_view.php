<!-- Swimming Pool Management, Olympic Pool Plans & Slot Booking Hub -->
<?php
$db = getDB();

// Fetch Swimming Pool Plans
$plans = $db->query("SELECT * FROM pool_plans ORDER BY status ASC, price ASC")->fetchAll();

// Fetch Active Subscribers
$subscribers = $db->query("
    SELECT ps.*, m.name as member_name, m.member_code, m.phone as member_phone, pp.title as plan_title, pp.slot_timing, pp.coach_name,
           DATEDIFF(ps.end_date, CURRENT_DATE()) as days_left
    FROM pool_subscriptions ps
    JOIN members m ON ps.member_id = m.id
    JOIN pool_plans pp ON ps.pool_plan_id = pp.id
    ORDER BY ps.status ASC, ps.end_date ASC
")->fetchAll();

// Fetch All Members for quick pass assignment
$members = $db->query("SELECT id, name, member_code, phone FROM members WHERE status = 'active' ORDER BY name ASC")->fetchAll();

// KPIs
$activePoolMembers = $db->query("SELECT COUNT(*) FROM pool_subscriptions WHERE status = 'active' AND end_date >= CURRENT_DATE()")->fetchColumn();
$totalPoolRevenue = $db->query("SELECT IFNULL(SUM(price_paid), 0) FROM pool_subscriptions WHERE status = 'active'")->fetchColumn();
$totalPlansCount = count($plans);

$currency = getSetting('currency_symbol', '₹');
?>

<div class="page-content">
  <!-- Page Header -->
  <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem; margin-bottom:1.5rem;">
    <div>
      <h1 style="font-size:1.6rem; font-weight:800; color:var(--text-primary); display:flex; align-items:center; gap:0.6rem;">
        <span style="background:#0284C7; color:#fff; width:38px; height:38px; display:inline-flex; align-items:center; justify-content:center; border-radius:10px;">🏊</span>
        <span>Swimming Pool &amp; Aquatics Hub</span>
      </h1>
      <p style="font-size:0.85rem; color:var(--text-secondary);">
        Olympic Heated Pool Plans, Coaching Batches, Plan Editor, Lane Schedules &amp; Member Check-ins
      </p>
    </div>

    <div style="display:flex; gap:0.5rem; flex-wrap:wrap;">
      <button class="btn" style="background:#F59E0B; color:#78350F; font-weight:800; border:none;" onclick="openModal('broadcastPoolOffModal')">
        📢 Broadcast Pool Off Notice (WhatsApp)
      </button>
      <button class="btn btn-primary" onclick="openCreatePlanModal()">
        + Create New Pool Plan
      </button>
      <button class="btn btn-outline" onclick="openModal('assignPoolPassModal')">
        🏊 Assign Member Pass
      </button>
    </div>
  </div>

  <!-- Real-time KPI Cards -->
  <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:1rem; margin-bottom:1.5rem;">
    <div class="card" style="border-left:4px solid #0284C7;">
      <div style="font-size:0.75rem; font-weight:700; color:var(--text-muted); text-transform:uppercase;">ACTIVE POOL MEMBERS</div>
      <div style="font-size:1.8rem; font-weight:800; color:#0284C7; margin-top:0.25rem;"><?= $activePoolMembers ?> Swimmers</div>
    </div>
    <div class="card" style="border-left:4px solid var(--success);">
      <div style="font-size:0.75rem; font-weight:700; color:var(--text-muted); text-transform:uppercase;">POOL REVENUE</div>
      <div style="font-size:1.8rem; font-weight:800; color:var(--success); margin-top:0.25rem;"><?= $currency ?><?= number_format($totalPoolRevenue, 2) ?></div>
    </div>
    <div class="card" style="border-left:4px solid #F59E0B;">
      <div style="font-size:0.75rem; font-weight:700; color:var(--text-muted); text-transform:uppercase;">AVAILABLE POOL PLANS</div>
      <div style="font-size:1.8rem; font-weight:800; color:#F59E0B; margin-top:0.25rem;"><?= $totalPlansCount ?> Active Plans</div>
    </div>
    <div class="card" style="border-left:4px solid #10B981;">
      <div style="font-size:0.75rem; font-weight:700; color:var(--text-muted); text-transform:uppercase;">POOL WATER STATUS</div>
      <div style="font-size:1.1rem; font-weight:800; color:#059669; margin-top:0.35rem;">✓ 28°C Clean &amp; Heated</div>
      <div style="font-size:0.72rem; color:var(--text-muted);">pH: 7.4 | Lifeguard on Duty</div>
    </div>
  </div>

  <!-- Navigation Tab Bar -->
  <div style="display:flex; gap:0.5rem; border-bottom:2px solid var(--border-color); margin-bottom:1.5rem; overflow-x:auto;">
    <button class="btn pool-tab-btn active" id="tabBtnPlans" onclick="switchPoolTab('plans', this)" style="border-radius:8px 8px 0 0; font-weight:700;">
      🏊 Swimming Pool Plans (<?= count($plans) ?>)
    </button>
    <button class="btn pool-tab-btn" id="tabBtnSubs" onclick="switchPoolTab('subs', this)" style="border-radius:8px 8px 0 0; font-weight:700;">
      👥 Active Swimmers &amp; Passes (<?= count($subscribers) ?>)
    </button>
    <button class="btn pool-tab-btn" id="tabBtnSchedule" onclick="switchPoolTab('schedule', this)" style="border-radius:8px 8px 0 0; font-weight:700;">
      ⏱️ Slot Timings &amp; Batches
    </button>
  </div>

  <!-- TAB 1: SWIMMING POOL PLANS (WITH ADD & EDIT SYSTEM) -->
  <div id="poolTabContentPlans">
    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(320px, 1fr)); gap:1.25rem;">
      <?php foreach ($plans as $p): ?>
        <?php $isActive = ($p['status'] === 'active'); ?>
        <div class="card" style="border:1px solid var(--border-color); border-radius:14px; position:relative; overflow:hidden; opacity:<?= $isActive ? '1' : '0.6' ?>; display:flex; flex-direction:column; justify-content:space-between;">
          <div style="height:6px; background:linear-gradient(90deg, #0284C7, #38BDF8);"></div>
          
          <div style="padding:1.25rem;">
            <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:0.75rem;">
              <span class="badge" style="background:#E0F2FE; color:#0369A1; font-weight:800; font-size:0.75rem; text-transform:uppercase;">
                <?= htmlspecialchars($p['category']) ?>
              </span>
              <span class="badge badge-<?= $isActive ? 'success' : 'danger' ?>">
                <?= strtoupper($p['status']) ?>
              </span>
            </div>

            <h3 style="font-size:1.2rem; font-weight:800; color:var(--text-primary); margin-bottom:0.25rem;">
              <?= htmlspecialchars($p['title']) ?>
            </h3>
            
            <div style="font-size:0.8rem; color:var(--text-secondary); margin-bottom:1rem; min-height:36px;">
              <?= htmlspecialchars($p['description'] ?: 'Unlimited swimming pool access with heated water and shower lockers.') ?>
            </div>

            <!-- Price & Duration Box -->
            <div style="background:#F8FAFC; border:1px solid #E2E8F0; border-radius:10px; padding:0.85rem; margin-bottom:1rem;">
              <div style="display:flex; justify-content:space-between; align-items:baseline;">
                <span style="font-size:0.8rem; font-weight:600; color:var(--text-muted);">Membership Price</span>
                <span style="font-size:1.5rem; font-weight:900; color:#0284C7;"><?= $currency ?><?= number_format($p['price'], 2) ?></span>
              </div>
              
              <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.5rem; margin-top:0.5rem; font-size:0.78rem; color:var(--text-secondary);">
                <div>📅 <strong>Duration:</strong> <?= $p['duration_days'] ?> Days (<?= $p['duration_months'] ?> mo)</div>
                <div>🎟️ <strong>Sessions:</strong> <?= $p['sessions_limit'] > 0 ? $p['sessions_limit'] . ' Sessions' : 'Unlimited' ?></div>
              </div>
            </div>

            <!-- Slot & Coach Info -->
            <div style="font-size:0.78rem; color:var(--text-secondary); margin-bottom:0.5rem;">
              <div>⏱️ <strong>Slot:</strong> <?= htmlspecialchars($p['slot_timing']) ?></div>
              <div style="margin-top:0.25rem;">🏊 <strong>Coach/Lifeguard:</strong> <?= htmlspecialchars($p['coach_name']) ?></div>
            </div>
          </div>

          <!-- Actions Footer -->
          <div style="padding:0.85rem 1.25rem; background:#F8FAFC; border-top:1px solid var(--border-color); display:flex; gap:0.5rem; justify-content:space-between; align-items:center;">
            <button class="btn btn-outline btn-sm" onclick="openEditPlanModal(<?= htmlspecialchars(json_encode($p)) ?>)" style="flex:1; font-weight:700;">
              ✏️ Edit Plan
            </button>
            <button class="btn btn-primary btn-sm" onclick="quickAssignPlan(<?= $p['id'] ?>, '<?= htmlspecialchars(addslashes($p['title'])) ?>', <?= $p['price'] ?>)" style="flex:1; font-weight:700; background:#0284C7; border-color:#0284C7;">
              + Assign Pass
            </button>
            <button class="btn btn-danger btn-sm" onclick="deletePoolPlan(<?= $p['id'] ?>, '<?= htmlspecialchars(addslashes($p['title'])) ?>')" title="Delete Plan" style="padding:0.25rem 0.5rem;">
              🗑️
            </button>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- TAB 2: ACTIVE SUBSCRIBERS & LIVE POOL CHECK-IN -->
  <div id="poolTabContentSubs" style="display:none;">
    <div class="card">
      <div class="card-header" style="display:flex; justify-content:space-between; align-items:center;">
        <span class="card-title">👥 Active Swimming Pool Members &amp; Passes</span>
        <button class="btn btn-primary btn-sm" onclick="openModal('assignPoolPassModal')">+ Assign New Pass</button>
      </div>

      <div class="table-responsive" style="margin-top:0.5rem;">
        <table class="table table-mobile-card">
          <thead>
            <tr>
              <th>Member</th>
              <th>Pool Plan</th>
              <th>Assigned Slot</th>
              <th>Validity Dates</th>
              <th>Sessions Tracker</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($subscribers)): ?>
              <tr><td colspan="7" style="text-align:center; color:var(--text-muted); padding:2rem;">No active swimming pool subscribers recorded yet.</td></tr>
            <?php else: ?>
              <?php foreach ($subscribers as $sub): ?>
                <?php 
                  $isExp = ($sub['status'] !== 'active' || $sub['days_left'] < 0); 
                  $isLimited = ($sub['sessions_total'] > 0);
                ?>
                <tr>
                  <td data-label="Member">
                    <a href="index.php?page=member_profile&id=<?= $sub['member_id'] ?>" style="color:var(--text-primary); text-decoration:none; font-weight:700;">
                      <?= htmlspecialchars($sub['member_name']) ?>
                    </a>
                    <div style="font-size:0.75rem; color:var(--text-muted);"><?= htmlspecialchars($sub['member_code']) ?> | <?= htmlspecialchars($sub['member_phone']) ?></div>
                  </td>
                  <td data-label="Plan">
                    <strong style="color:#0284C7;"><?= htmlspecialchars($sub['plan_title']) ?></strong>
                    <div style="font-size:0.72rem; color:var(--text-muted);"><?= $currency ?><?= number_format($sub['price_paid'], 2) ?> Paid</div>
                  </td>
                  <td data-label="Slot">
                    <span class="badge" style="background:#E0F2FE; color:#0369A1; font-weight:700;"><?= htmlspecialchars($sub['slot_assigned']) ?></span>
                  </td>
                  <td data-label="Validity">
                    <div style="font-size:0.8rem; font-weight:600;"><?= date('d M Y', strtotime($sub['start_date'])) ?> to <?= date('d M Y', strtotime($sub['end_date'])) ?></div>
                    <?php if ($isExp): ?>
                      <span class="badge badge-danger">EXPIRED</span>
                    <?php else: ?>
                      <span class="badge badge-success"><?= $sub['days_left'] ?> Days Left</span>
                    <?php endif; ?>
                  </td>
                  <td data-label="Sessions">
                    <?php if ($isLimited): ?>
                      <div style="font-size:0.8rem; font-weight:700;"><?= $sub['sessions_used'] ?> / <?= $sub['sessions_total'] ?> Used</div>
                      <div style="font-size:0.72rem; color:#059669; font-weight:800;"><?= $sub['sessions_remaining'] ?> Remaining</div>
                    <?php else: ?>
                      <span class="badge badge-info" style="font-weight:700;">♾️ Unlimited Daily</span>
                    <?php endif; ?>
                  </td>
                  <td data-label="Status">
                    <span class="badge badge-<?= $isExp ? 'danger' : 'success' ?>">
                      ● <?= strtoupper($sub['status']) ?>
                    </span>
                  </td>
                  <td data-label="Actions">
                    <?php if (!$isExp && (!$isLimited || $sub['sessions_remaining'] > 0)): ?>
                      <button class="btn btn-primary btn-sm" onclick="checkinPoolSession(<?= $sub['id'] ?>, '<?= htmlspecialchars(addslashes($sub['member_name'])) ?>')" style="background:#0284C7; border-color:#0284C7; font-weight:700; font-size:0.78rem;">
                        🏊 Punch Pool Entry
                      </button>
                    <?php else: ?>
                      <span style="font-size:0.75rem; color:var(--text-muted);">Pass Inactive</span>
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

  <!-- TAB 3: SLOT TIMINGS & LANE SCHEDULE -->
  <div id="poolTabContentSchedule" style="display:none;">
    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(280px, 1fr)); gap:1rem;">
      <div class="card" style="border-top:4px solid #0284C7;">
        <h3 style="font-size:1.1rem; font-weight:800; color:#0284C7; margin-bottom:0.5rem;">🌅 Morning Early Bird Slot</h3>
        <div style="font-size:0.85rem; font-weight:700; margin-bottom:0.5rem;">⏰ 6:00 AM - 9:00 AM</div>
        <p style="font-size:0.8rem; color:var(--text-secondary); margin-bottom:0.75rem;">Open lap swimming for all active members. 6 designated lanes with heated water.</p>
        <div style="font-size:0.75rem; color:var(--text-muted);">🏊 Lifeguard: Ramesh Verma on duty</div>
      </div>

      <div class="card" style="border-top:4px solid #10B981;">
        <h3 style="font-size:1.1rem; font-weight:800; color:#10B981; margin-bottom:0.5rem;">☀️ General / Ladies Special</h3>
        <div style="font-size:0.85rem; font-weight:700; margin-bottom:0.5rem;">⏰ 9:30 AM - 11:30 AM</div>
        <p style="font-size:0.8rem; color:var(--text-secondary); margin-bottom:0.75rem;">Dedicated calm swimming hour with female swim instructor available.</p>
        <div style="font-size:0.75rem; color:var(--text-muted);">🏊 Coach: Sunita Sharma (Certified Coach)</div>
      </div>

      <div class="card" style="border-top:4px solid #F59E0B;">
        <h3 style="font-size:1.1rem; font-weight:800; color:#F59E0B; margin-bottom:0.5rem;">👦 Kids &amp; Beginners Coaching Batch</h3>
        <div style="font-size:0.85rem; font-weight:700; margin-bottom:0.5rem;">⏰ 4:00 PM - 5:30 PM</div>
        <p style="font-size:0.8rem; color:var(--text-secondary); margin-bottom:0.75rem;">15-day stroke mastery, float training &amp; breath control coaching program.</p>
        <div style="font-size:0.75rem; color:var(--text-muted);">🏊 Head Coach: Vikram Malhotra</div>
      </div>

      <div class="card" style="border-top:4px solid #6366F1;">
        <h3 style="font-size:1.1rem; font-weight:800; color:#6366F1; margin-bottom:0.5rem;">🌆 Evening All-Access Prime Slot</h3>
        <div style="font-size:0.85rem; font-weight:700; margin-bottom:0.5rem;">⏰ 6:00 PM - 9:30 PM</div>
        <p style="font-size:0.8rem; color:var(--text-secondary); margin-bottom:0.75rem;">Prime open swimming for gym + pool combo members and daily pass holders.</p>
        <div style="font-size:0.75rem; color:var(--text-muted);">🏊 Lifeguard: Rahul Sen on duty</div>
      </div>
    </div>
  </div>
</div>

<!-- ADD / EDIT SWIMMING POOL PLAN MODAL -->
<div class="modal" id="poolPlanModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:100; align-items:center; justify-content:center;">
  <div class="modal-overlay" style="position:absolute; inset:0;" onclick="closeModal('poolPlanModal')"></div>
  <div class="card" style="position:relative; z-index:10; background:#fff; width:90%; max-width:540px; border-radius:16px; padding:1.5rem; border-top:5px solid #0284C7;">
    <div class="card-header" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;">
      <span class="card-title" id="poolPlanModalTitle" style="font-size:1.2rem; font-weight:800; color:#0369A1;">✏️ Edit Swimming Pool Plan</span>
      <button class="modal-close" style="background:none; border:none; font-size:1.2rem; cursor:pointer;" onclick="closeModal('poolPlanModal')">✕</button>
    </div>

    <form onsubmit="event.preventDefault(); submitPoolPlanForm();">
      <input type="hidden" id="planId">

      <div class="form-group">
        <label class="form-label">Plan Title *</label>
        <input type="text" id="planTitle" class="form-control" placeholder="e.g. Monthly Unlimited Swimming Pass" required>
      </div>

      <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.75rem;">
        <div class="form-group">
          <label class="form-label">Category</label>
          <select id="planCategory" class="form-control">
            <option value="monthly">Monthly</option>
            <option value="quarterly">Quarterly</option>
            <option value="half_yearly">Half-Yearly</option>
            <option value="yearly">Yearly / Annual</option>
            <option value="coaching">Coaching Special</option>
            <option value="daily">Daily Pass</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Plan Price (<?= $currency ?>) *</label>
          <input type="number" step="0.01" id="planPrice" class="form-control" placeholder="0.00" required style="font-weight:700; color:#0284C7;">
        </div>
      </div>

      <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:0.75rem;">
        <div class="form-group">
          <label class="form-label">Duration (Mo)</label>
          <input type="number" id="planMonths" class="form-control" value="1" min="0" oninput="document.getElementById('planDays').value = this.value * 30">
        </div>
        <div class="form-group">
          <label class="form-label">Duration (Days) *</label>
          <input type="number" id="planDays" class="form-control" value="30" required>
        </div>
        <div class="form-group">
          <label class="form-label">Sessions (0=Unl)</label>
          <input type="number" id="planSessions" class="form-control" value="0" placeholder="0 for unlimited">
        </div>
      </div>

      <div class="form-group">
        <label class="form-label">Slot Timing</label>
        <input type="text" id="planSlotTiming" class="form-control" placeholder="e.g. Morning 6:00 AM - 10:00 AM &amp; Evening 5:00 PM - 9:00 PM">
      </div>

      <div class="form-group">
        <label class="form-label">Assigned Coach / Lifeguard</label>
        <input type="text" id="planCoach" class="form-control" placeholder="e.g. Certified Swim Coach &amp; Lifeguard">
      </div>

      <div class="form-group">
        <label class="form-label">Plan Description &amp; Inclusions</label>
        <textarea id="planDesc" class="form-control" rows="2" placeholder="e.g. Heated Olympic pool access, warm showers, locker room"></textarea>
      </div>

      <div class="form-group">
        <label class="form-label">Status</label>
        <select id="planStatus" class="form-control">
          <option value="active">Active</option>
          <option value="inactive">Inactive</option>
        </select>
      </div>

      <div style="display:flex; gap:0.75rem; margin-top:1.5rem;">
        <button type="button" class="btn btn-secondary btn-block" onclick="closeModal('poolPlanModal')">Cancel</button>
        <button type="submit" class="btn btn-primary btn-block" style="background:#0284C7; border-color:#0284C7;">✓ SAVE POOL PLAN</button>
      </div>
    </form>
  </div>
</div>

<!-- ASSIGN POOL PASS MODAL -->
<div class="modal" id="assignPoolPassModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:100; align-items:center; justify-content:center;">
  <div class="modal-overlay" style="position:absolute; inset:0;" onclick="closeModal('assignPoolPassModal')"></div>
  <div class="card" style="position:relative; z-index:10; background:#fff; width:90%; max-width:480px; border-radius:16px; padding:1.5rem; border-top:5px solid #0284C7;">
    <div class="card-header" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;">
      <span class="card-title" style="font-size:1.15rem; font-weight:800; color:#0369A1;">🏊 Assign Swimming Pool Pass</span>
      <button class="modal-close" style="background:none; border:none; font-size:1.2rem; cursor:pointer;" onclick="closeModal('assignPoolPassModal')">✕</button>
    </div>

    <form onsubmit="event.preventDefault(); submitAssignPoolPass();">
      <div class="form-group">
        <label class="form-label">Select Member *</label>
        <select id="assignMemberId" class="form-control" required>
          <option value="">-- Choose Member --</option>
          <?php foreach ($members as $m): ?>
            <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['name']) ?> (<?= $m['member_code'] ?> - <?= $m['phone'] ?>)</option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-group">
        <label class="form-label">Select Pool Plan *</label>
        <select id="assignPlanId" class="form-control" onchange="updateAssignPlanPrice()" required>
          <option value="">-- Choose Pool Plan --</option>
          <?php foreach ($plans as $p): ?>
            <option value="<?= $p['id'] ?>" data-price="<?= $p['price'] ?>"><?= htmlspecialchars($p['title']) ?> (<?= $currency ?><?= number_format($p['price'], 2) ?> - <?= $p['duration_days'] ?>d)</option>
          <?php endforeach; ?>
        </select>
      </div>

      <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.75rem;">
        <div class="form-group">
          <label class="form-label">Start Date</label>
          <input type="date" id="assignStartDate" class="form-control" value="<?= date('Y-m-d') ?>">
        </div>
        <div class="form-group">
          <label class="form-label">Amount Paid (<?= $currency ?>)</label>
          <input type="number" step="0.01" id="assignPricePaid" class="form-control" placeholder="0.00" style="font-weight:700;">
        </div>
      </div>

      <div class="form-group">
        <label class="form-label">Assigned Batch / Slot</label>
        <select id="assignSlot" class="form-control">
          <option value="Morning Slot (6-9 AM)">🌅 Morning Slot (6-9 AM)</option>
          <option value="Ladies Special (9:30-11:30 AM)">☀️ Ladies Special (9:30-11:30 AM)</option>
          <option value="Kids Coaching (4-5:30 PM)">👦 Kids Coaching (4-5:30 PM)</option>
          <option value="Evening Prime (6-9:30 PM)">🌆 Evening Prime (6-9:30 PM)</option>
          <option value="All Day Open Access">♾️ All Day Open Access</option>
        </select>
      </div>

      <div style="display:flex; gap:0.75rem; margin-top:1.5rem;">
        <button type="button" class="btn btn-secondary btn-block" onclick="closeModal('assignPoolPassModal')">Cancel</button>
        <button type="submit" class="btn btn-primary btn-block" style="background:#0284C7; border-color:#0284C7;">✓ ACTIVATE POOL PASS</button>
      </div>
    </form>
  </div>
</div>

<style>
.pool-tab-btn {
  background: transparent;
  color: var(--text-secondary);
  border: none;
  padding: 0.6rem 1rem;
}
.pool-tab-btn.active {
  background: var(--bg-surface);
  color: #0284C7;
  border-bottom: 3px solid #0284C7;
}
</style>

<script>
function switchPoolTab(tab, btn) {
  document.querySelectorAll('.pool-tab-btn').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');

  document.getElementById('poolTabContentPlans').style.display = (tab === 'plans') ? 'block' : 'none';
  document.getElementById('poolTabContentSubs').style.display = (tab === 'subs') ? 'block' : 'none';
  document.getElementById('poolTabContentSchedule').style.display = (tab === 'schedule') ? 'block' : 'none';
}

function openCreatePlanModal() {
  document.getElementById('poolPlanModalTitle').innerText = '🏊 Create New Swimming Pool Plan';
  document.getElementById('planId').value = '';
  document.getElementById('planTitle').value = '';
  document.getElementById('planCategory').value = 'monthly';
  document.getElementById('planMonths').value = '1';
  document.getElementById('planDays').value = '30';
  document.getElementById('planPrice').value = '';
  document.getElementById('planSessions').value = '0';
  document.getElementById('planSlotTiming').value = 'Morning 6:00 AM - 10:00 AM & Evening 5:00 PM - 9:00 PM';
  document.getElementById('planCoach').value = 'Certified Swim Coach & Lifeguard';
  document.getElementById('planDesc').value = 'Unlimited heated Olympic swimming pool access and lockers.';
  document.getElementById('planStatus').value = 'active';
  openModal('poolPlanModal');
}

function openEditPlanModal(plan) {
  document.getElementById('poolPlanModalTitle').innerText = '✏️ Edit Swimming Pool Plan';
  document.getElementById('planId').value = plan.id;
  document.getElementById('planTitle').value = plan.title;
  document.getElementById('planCategory').value = plan.category;
  document.getElementById('planMonths').value = plan.duration_months;
  document.getElementById('planDays').value = plan.duration_days;
  document.getElementById('planPrice').value = plan.price;
  document.getElementById('planSessions').value = plan.sessions_limit;
  document.getElementById('planSlotTiming').value = plan.slot_timing;
  document.getElementById('planCoach').value = plan.coach_name;
  document.getElementById('planDesc').value = plan.description;
  document.getElementById('planStatus').value = plan.status;
  openModal('poolPlanModal');
}

function submitPoolPlanForm() {
  const payload = {
    id: document.getElementById('planId').value || null,
    title: document.getElementById('planTitle').value.trim(),
    category: document.getElementById('planCategory').value,
    duration_months: document.getElementById('planMonths').value,
    duration_days: document.getElementById('planDays').value,
    price: document.getElementById('planPrice').value,
    sessions_limit: document.getElementById('planSessions').value,
    slot_timing: document.getElementById('planSlotTiming').value.trim(),
    coach_name: document.getElementById('planCoach').value.trim(),
    description: document.getElementById('planDesc').value.trim(),
    status: document.getElementById('planStatus').value
  };

  fetch('api/pool.php?action=save_plan', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(payload)
  })
  .then(res => res.json())
  .then(res => {
    if (res.success) {
      showToast(res.message, 'success');
      closeModal('poolPlanModal');
      setTimeout(() => location.reload(), 800);
    } else {
      showToast(res.message || 'Failed to save plan', 'danger');
    }
  });
}

function deletePoolPlan(id, title) {
  if (!confirm(`Are you sure you want to delete or deactivate pool plan "${title}"?`)) return;

  fetch('api/pool.php?action=delete_plan', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ id: id })
  })
  .then(res => res.json())
  .then(res => {
    if (res.success) {
      showToast(res.message, 'success');
      setTimeout(() => location.reload(), 800);
    } else {
      showToast(res.message || 'Failed to delete plan', 'danger');
    }
  });
}

function quickAssignPlan(planId, title, price) {
  document.getElementById('assignPlanId').value = planId;
  document.getElementById('assignPricePaid').value = price;
  openModal('assignPoolPassModal');
}

function updateAssignPlanPrice() {
  const sel = document.getElementById('assignPlanId');
  const opt = sel.options[sel.selectedIndex];
  if (opt && opt.getAttribute('data-price')) {
    document.getElementById('assignPricePaid').value = opt.getAttribute('data-price');
  }
}

function submitAssignPoolPass() {
  const payload = {
    member_id: document.getElementById('assignMemberId').value,
    pool_plan_id: document.getElementById('assignPlanId').value,
    start_date: document.getElementById('assignStartDate').value,
    price_paid: document.getElementById('assignPricePaid').value,
    slot_assigned: document.getElementById('assignSlot').value
  };

  fetch('api/pool.php?action=assign_member_pass', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(payload)
  })
  .then(res => res.json())
  .then(res => {
    if (res.success) {
      showToast(res.message, 'success');
      closeModal('assignPoolPassModal');
      setTimeout(() => location.reload(), 800);
    } else {
      showToast(res.message || 'Assignment failed', 'danger');
    }
  });
}

function checkinPoolSession(subId, memberName) {
  if (!confirm(`Verify and punch Swimming Pool check-in for ${memberName}?`)) return;

  fetch('api/pool.php?action=checkin_pool', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ subscription_id: subId })
  })
  .then(res => res.json())
  .then(res => {
    if (res.success) {
      showToast(res.message, 'success');
      setTimeout(() => location.reload(), 800);
    } else {
      showToast(res.message || 'Check-in failed', 'danger');
    }
  });
}

function sendPoolOffBroadcast() {
  const msg = document.getElementById('poolOffBroadcastText').value.trim();
  if (!msg) {
    showToast('Notice message cannot be empty', 'warning');
    return;
  }

  showToast('Preparing Anti-Ban WhatsApp Queue...', 'info');
  fetch('api/marketing.php?action=broadcast', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      target_group: 'pool_members',
      message_text: msg
    })
  })
  .then(res => res.json())
  .then(res => {
    if (res.success) {
      showToast(res.message || 'Pool Off Notice Dispatched to All Swimmers!', 'success');
      closeModal('broadcastPoolOffModal');
    } else {
      showToast(res.message || 'Broadcast failed', 'danger');
    }
  });
}
</script>

<!-- BROADCAST POOL OFF NOTICE MODAL -->
<div class="modal" id="broadcastPoolOffModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.6); z-index:100; align-items:center; justify-content:center;">
  <div class="modal-overlay" style="position:absolute; inset:0;" onclick="closeModal('broadcastPoolOffModal')"></div>
  <div class="card" style="position:relative; z-index:10; background:#fff; width:90%; max-width:550px; border-radius:18px; padding:1.5rem; border-top:5px solid #0284C7;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;">
      <span style="font-weight:800; font-size:1.15rem; color:#0369A1;">🏊 Broadcast Pool Off / Maintenance Notice</span>
      <button style="background:none; border:none; font-size:1.2rem; cursor:pointer;" onclick="closeModal('broadcastPoolOffModal')">✕</button>
    </div>

    <div style="background:#F0F9FF; border:1px solid #BAE6FD; padding:0.75rem 1rem; border-radius:8px; margin-bottom:1rem; font-size:0.82rem; color:#0369A1;">
      🎯 <strong>Audience:</strong> All <?= $activePoolMembers ?> Active Swimming Pool Members
    </div>

    <div class="form-group">
      <label class="form-label">WhatsApp Notice Message (Editable)</label>
      <textarea id="poolOffBroadcastText" class="form-control" rows="7" style="font-family:inherit; font-size:0.85rem; line-height:1.4;">🏊 *Notice: Swimming Pool Closed Tomorrow* 🏊

Dear {name},

Please be informed that the Swimming Pool at THE CLUB 777® will remain *CLOSED tomorrow (<?= date('d M Y', strtotime('+1 day')) ?>)* due to scheduled deep cleaning, chemical treatment & water filtration maintenance.

✅ Regular swimming batches will resume as normal from the day after.

We apologize for the temporary inconvenience and appreciate your cooperation! 🙏

For queries, contact front desk at <?= getSetting('gym_phone', '+91 98765 43210') ?>.</textarea>
    </div>

    <div style="display:flex; gap:0.75rem; margin-top:1.5rem;">
      <button type="button" class="btn btn-secondary btn-block" onclick="closeModal('broadcastPoolOffModal')">Cancel</button>
      <button type="button" class="btn btn-block" onclick="sendPoolOffBroadcast()" style="background:#0284C7; color:#fff; font-weight:800; border:none;">
        📢 SEND TO ALL SWIMMERS (WHATSAPP)
      </button>
    </div>
  </div>
</div>
