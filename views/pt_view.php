<!-- Redesigned Commercial Personal Training (PT) Hub & Session Management View -->
<?php
$db = getDB();

// Active Member PT Subscriptions
$stmt = $db->query("
    SELECT ps.*, m.name as member_name, m.member_code, m.phone as member_phone, p.title as package_title, t.name as trainer_name, t.phone as trainer_phone 
    FROM pt_subscriptions ps
    JOIN members m ON ps.member_id = m.id
    JOIN pt_packages p ON ps.pt_package_id = p.id
    LEFT JOIN trainers t ON ps.trainer_id = t.id
    ORDER BY ps.id DESC
");
$ptSubscriptions = $stmt->fetchAll();

// PT Packages List
$pkgStmt = $db->query("SELECT p.*, t.name as trainer_name FROM pt_packages p LEFT JOIN trainers t ON p.trainer_id = t.id ORDER BY p.id ASC");
$packages = $pkgStmt->fetchAll();

// Trainers List
$trainersStmt = $db->query("SELECT t.*, (SELECT COUNT(*) FROM pt_subscriptions ps WHERE ps.trainer_id = t.id AND ps.status = 'active') as active_clients_count FROM trainers t ORDER BY t.name ASC");
$trainers = $trainersStmt->fetchAll();

// Active Members List for Assignment Dropdown
$membersList = $db->query("SELECT id, name, member_code, phone FROM members WHERE status != 'inactive' ORDER BY name ASC")->fetchAll();

// KPI Stats
$activePtClients = $db->query("SELECT COUNT(DISTINCT member_id) FROM pt_subscriptions WHERE status = 'active'")->fetchColumn();
$totalSessionsLogged = $db->query("SELECT IFNULL(SUM(sessions_used), 0) FROM pt_subscriptions")->fetchColumn();
$totalPtRevenue = $db->query("SELECT IFNULL(SUM(price_paid), 0) FROM pt_subscriptions")->fetchColumn();
$currency = getSetting('currency_symbol', '₹');
?>

<div class="page-content">
  <!-- Header & Actions -->
  <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1.25rem; margin-bottom:1.5rem;">
    <div>
      <h1 style="font-size:1.65rem; font-weight:900; color:var(--text-primary); letter-spacing:-0.03em; display:flex; align-items:center; gap:0.65rem;">
        <span style="background:#F5F3FF; color:#7C3AED; width:42px; height:42px; border-radius:12px; display:inline-flex; align-items:center; justify-content:center; font-size:1.4rem; box-shadow:0 2px 8px rgba(124,58,237,0.2);">💪</span>
        <span>Personal Training (PT) Command Center</span>
      </h1>
      <p style="font-size:0.85rem; color:var(--text-secondary); margin-top:0.25rem;">
        Track personal workout sessions, log client punches, manage trainer assignments &amp; commission packages
      </p>
    </div>

    <div style="display:flex; gap:0.65rem; flex-wrap:wrap;">
      <a href="index.php?page=pos" class="btn btn-outline" style="border-radius:var(--radius-md); font-weight:700; background:var(--bg-surface);">
        ⚡ Sell PT in POS
      </a>
      <button class="btn btn-secondary" style="border-radius:var(--radius-md); font-weight:700;" onclick="openCreatePackageModal()">
        📦 + New Package
      </button>
      <button class="btn btn-primary" style="border-radius:var(--radius-md); font-weight:800; background:#7C3AED; border-color:#7C3AED; box-shadow:0 4px 12px rgba(124,58,237,0.25);" onclick="openModal('assignPtModal')">
        ➕ Assign PT to Member
      </button>
    </div>
  </div>

  <!-- Executive Metric Summary Cards -->
  <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:1.25rem; margin-bottom:1.75rem;">
    <div class="card" style="border-left:4px solid #7C3AED; padding:1.25rem; border-radius:var(--radius-lg); box-shadow:var(--shadow-card);">
      <div style="font-size:0.75rem; font-weight:800; text-transform:uppercase; color:var(--text-muted); letter-spacing:0.05em;">Active PT Clients</div>
      <div style="font-size:1.85rem; font-weight:900; color:#7C3AED; margin-top:0.35rem; font-family:var(--font-heading);"><?= $activePtClients ?> Clients</div>
      <div style="font-size:0.78rem; color:var(--text-secondary); margin-top:0.2rem;"><?= count($ptSubscriptions) ?> Total Enrollments</div>
    </div>

    <div class="card" style="border-left:4px solid var(--success); padding:1.25rem; border-radius:var(--radius-lg); box-shadow:var(--shadow-card);">
      <div style="font-size:0.75rem; font-weight:800; text-transform:uppercase; color:var(--text-muted); letter-spacing:0.05em;">Sessions Completed</div>
      <div style="font-size:1.85rem; font-weight:900; color:var(--success); margin-top:0.35rem; font-family:var(--font-heading);"><?= $totalSessionsLogged ?> Punches</div>
      <div style="font-size:0.78rem; color:var(--text-secondary); margin-top:0.2rem;">Live trainer workout logs</div>
    </div>

    <div class="card" style="border-left:4px solid var(--primary); padding:1.25rem; border-radius:var(--radius-lg); box-shadow:var(--shadow-card);">
      <div style="font-size:0.75rem; font-weight:800; text-transform:uppercase; color:var(--text-muted); letter-spacing:0.05em;">Certified Trainers</div>
      <div style="font-size:1.85rem; font-weight:900; color:var(--primary); margin-top:0.35rem; font-family:var(--font-heading);"><?= count($trainers) ?> Coaches</div>
      <div style="font-size:0.78rem; color:var(--text-secondary); margin-top:0.2rem;">Master trainers &amp; fitness experts</div>
    </div>

    <div class="card" style="border-left:4px solid var(--warning); padding:1.25rem; border-radius:var(--radius-lg); box-shadow:var(--shadow-card);">
      <div style="font-size:0.75rem; font-weight:800; text-transform:uppercase; color:var(--text-muted); letter-spacing:0.05em;">Total PT Revenue</div>
      <div style="font-size:1.85rem; font-weight:900; color:var(--warning); margin-top:0.35rem; font-family:var(--font-heading);"><?= $currency ?><?= number_format($totalPtRevenue, 2) ?></div>
      <div style="font-size:0.78rem; color:var(--text-secondary); margin-top:0.2rem;"><?= count($packages) ?> Packages Configured</div>
    </div>
  </div>

  <!-- Navigation Tabs -->
  <div style="display:flex; gap:0.5rem; border-bottom:2px solid var(--border-color); margin-bottom:1.5rem; overflow-x:auto;">
    <button class="btn pt-nav-tab active" id="ptTabBtn_clients" onclick="switchPtTab('clients')">
      🏋️ Active PT Clients &amp; Session Tracker (<?= count($ptSubscriptions) ?>)
    </button>
    <button class="btn pt-nav-tab" id="ptTabBtn_packages" onclick="switchPtTab('packages')">
      📦 PT Packages &amp; Pricing (<?= count($packages) ?>)
    </button>
    <button class="btn pt-nav-tab" id="ptTabBtn_trainers" onclick="switchPtTab('trainers')">
      👨‍🏫 Personal Trainers Roster (<?= count($trainers) ?>)
    </button>
  </div>

  <!-- TAB 1: ACTIVE CLIENTS & SESSION TRACKER -->
  <div id="ptTabPane_clients" class="pt-tab-pane">
    <div class="card" style="border-radius:var(--radius-lg); box-shadow:var(--shadow-card); padding:1.25rem;">
      <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem; margin-bottom:1rem;">
        <span style="font-size:1.15rem; font-weight:800; color:var(--text-primary);">📋 Live PT Sessions &amp; Remaining Balance Tracker</span>
        
        <div style="position:relative; min-width:260px;">
          <input type="text" id="ptClientSearch" class="form-control" placeholder="🔍 Search member, coach, package..." oninput="searchPtClients(this.value)" style="padding-left:2.2rem; height:38px; border-radius:var(--radius-full); font-size:0.82rem;">
        </div>
      </div>

      <div class="table-wrapper">
        <table id="ptClientsTable">
          <thead>
            <tr>
              <th>Member Client</th>
              <th>PT Package</th>
              <th>Assigned Coach</th>
              <th>Session Progress</th>
              <th>Validity Expiry</th>
              <th>Status</th>
              <th>Quick Punch &amp; Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($ptSubscriptions)): ?>
              <tr><td colspan="7" style="text-align:center; color:var(--text-muted); padding:2rem;">No active Personal Training client subscriptions found.</td></tr>
            <?php else: ?>
              <?php foreach ($ptSubscriptions as $pt): ?>
                <?php
                  $pct = $pt['sessions_total'] > 0 ? round(($pt['sessions_used'] / $pt['sessions_total']) * 100) : 0;
                  $isCompleted = ($pt['sessions_remaining'] <= 0 || $pt['status'] === 'completed');
                  $cleanPhone = preg_replace('/[^0-9]/', '', $pt['member_phone']);
                  if (strlen($cleanPhone) === 10) $cleanPhone = '91' . $cleanPhone;
                  $waMsg = urlencode("Hello {$pt['member_name']}! You have completed {$pt['sessions_used']}/{$pt['sessions_total']} Personal Training sessions at THE CLUB 777®. Remaining balance: {$pt['sessions_remaining']} sessions. Keep pushing!");
                  $waUrl = "https://wa.me/{$cleanPhone}?text={$waMsg}";
                ?>
                <tr class="pt-client-row" data-name="<?= htmlspecialchars(strtolower($pt['member_name'])) ?>" data-coach="<?= htmlspecialchars(strtolower($pt['trainer_name'] ?: '')) ?>" data-pkg="<?= htmlspecialchars(strtolower($pt['package_title'])) ?>">
                  <td>
                    <div style="display:flex; align-items:center; gap:0.65rem;">
                      <div style="width:38px; height:38px; border-radius:50%; background:linear-gradient(135deg, #7C3AED 0%, #C084FC 100%); color:#fff; display:flex; align-items:center; justify-content:center; font-weight:800; font-size:1rem; flex-shrink:0;">
                        <?= strtoupper(substr($pt['member_name'], 0, 1)) ?>
                      </div>
                      <div>
                        <a href="index.php?page=member_profile&id=<?= $pt['member_id'] ?>" style="color:var(--text-primary); text-decoration:none; font-weight:800; font-size:0.92rem;">
                          <?= htmlspecialchars($pt['member_name']) ?>
                        </a>
                        <div style="font-size:0.75rem; font-family:var(--font-mono); color:var(--text-muted);"><?= htmlspecialchars($pt['member_code']) ?> &bull; <?= htmlspecialchars($pt['member_phone']) ?></div>
                      </div>
                    </div>
                  </td>

                  <td>
                    <strong style="color:var(--text-primary);"><?= htmlspecialchars($pt['package_title']) ?></strong>
                    <div style="font-size:0.75rem; color:var(--success); font-weight:700; font-family:var(--font-mono);"><?= $currency ?><?= number_format($pt['price_paid'], 2) ?></div>
                  </td>

                  <td>
                    <div style="font-weight:700; color:var(--text-primary);">👨‍🏫 <?= htmlspecialchars($pt['trainer_name'] ?: 'Unassigned') ?></div>
                    <?php if ($pt['trainer_phone']): ?>
                      <div style="font-size:0.72rem; font-family:var(--font-mono); color:var(--text-muted);"><?= htmlspecialchars($pt['trainer_phone']) ?></div>
                    <?php endif; ?>
                  </td>

                  <td style="min-width:180px;">
                    <div style="display:flex; justify-content:space-between; font-size:0.78rem; font-weight:800; margin-bottom:0.25rem;">
                      <span><?= $pt['sessions_used'] ?> / <?= $pt['sessions_total'] ?> Sessions Done</span>
                      <span style="color:<?= $pt['sessions_remaining'] <= 2 ? 'var(--danger)' : '#059669' ?>; font-family:var(--font-mono);">
                        <?= $pt['sessions_remaining'] ?> Left
                      </span>
                    </div>
                    <div class="progress-bar-bg" style="height:7px; border-radius:4px; background:var(--border-color); overflow:hidden;">
                      <div class="progress-bar-fill" style="width:<?= min(100, $pct) ?>%; height:100%; background:<?= $pct >= 100 ? 'var(--success)' : '#7C3AED' ?>; border-radius:4px; transition:width 0.3s ease;"></div>
                    </div>
                  </td>

                  <td>
                    <?php if (!empty($pt['end_date'])): ?>
                      <div style="font-size:0.85rem; font-weight:700; font-family:var(--font-mono); color:var(--text-primary);"><?= date('d M Y', strtotime($pt['end_date'])) ?></div>
                      <div style="font-size:0.72rem; color:var(--text-muted);">Valid Period</div>
                    <?php else: ?>
                      <span style="color:var(--text-muted); font-size:0.8rem;">Ongoing</span>
                    <?php endif; ?>
                  </td>

                  <td>
                    <span class="badge badge-<?= $pt['status'] === 'active' ? 'success' : ($pt['status'] === 'completed' ? 'info' : 'secondary') ?>" style="font-weight:800; font-size:0.72rem;">
                      ● <?= strtoupper($pt['status']) ?>
                    </span>
                  </td>

                  <td>
                    <div style="display:flex; gap:0.35rem; flex-wrap:wrap;">
                      <?php if (!$isCompleted): ?>
                        <button class="btn btn-primary btn-sm" style="font-weight:800; font-size:0.75rem; background:#7C3AED; border-color:#7C3AED; padding:0.25rem 0.55rem;" onclick="openLogSessionModal(<?= $pt['id'] ?>, '<?= htmlspecialchars(addslashes($pt['member_name'])) ?>', <?= $pt['sessions_remaining'] ?>)">
                          ⚡ Log Punch
                        </button>
                      <?php endif; ?>
                      <a href="<?= $waUrl ?>" target="_blank" class="btn btn-success btn-sm" style="font-size:0.75rem; padding:0.25rem 0.45rem; background:#25D366; border-color:#25D366; text-decoration:none;" title="Send WhatsApp Update">
                        💬
                      </a>
                      <button class="btn btn-danger btn-sm" style="font-size:0.75rem; padding:0.25rem 0.45rem;" onclick="cancelPtSubscription(<?= $pt['id'] ?>, '<?= htmlspecialchars(addslashes($pt['member_name'])) ?>')" title="Cancel PT Package">
                        ✕
                      </button>
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

  <!-- TAB 2: PT PACKAGES & PRICING -->
  <div id="ptTabPane_packages" class="pt-tab-pane" style="display:none;">
    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(290px, 1fr)); gap:1.35rem;">
      <?php foreach ($packages as $pkg): ?>
        <div class="card" style="border-top:5px solid #7C3AED; border-radius:var(--radius-lg); box-shadow:var(--shadow-card); padding:1.25rem; display:flex; flex-direction:column; justify-content:space-between;">
          <div>
            <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:0.75rem;">
              <div>
                <strong style="font-size:1.2rem; font-weight:900; color:var(--text-primary); display:block;"><?= htmlspecialchars($pkg['title']) ?></strong>
                <span style="font-size:0.75rem; color:#7C3AED; font-weight:800; text-transform:uppercase;">💪 <?= $pkg['sessions_count'] ?? $pkg['sessions_total'] ?? 12 ?> SESSIONS BUNDLE</span>
              </div>
              <span class="badge badge-success font-mono" style="font-weight:800;">₹<?= number_format($pkg['price'], 2) ?></span>
            </div>

            <div style="background:var(--bg-main); border:1px solid var(--border-color); border-radius:10px; padding:0.85rem; margin-bottom:1rem; font-size:0.82rem; color:var(--text-secondary); display:flex; flex-direction:column; gap:0.4rem;">
              <div style="display:flex; justify-content:space-between;">
                <span>⏱️ <strong>Validity:</strong></span>
                <span class="font-mono"><?= $pkg['validity_days'] ?? 30 ?> Days</span>
              </div>
              <div style="display:flex; justify-content:space-between;">
                <span>👨‍🏫 <strong>Dedicated Trainer:</strong></span>
                <span><?= htmlspecialchars($pkg['trainer_name'] ?: 'Any Certified Coach') ?></span>
              </div>
              <?php if (isset($pkg['commission_pct'])): ?>
                <div style="display:flex; justify-content:space-between;">
                  <span>💰 <strong>Trainer Share:</strong></span>
                  <span class="font-mono"><?= $pkg['commission_pct'] ?>% Commission</span>
                </div>
              <?php endif; ?>
            </div>
          </div>

          <div style="display:flex; gap:0.5rem;">
            <button class="btn btn-secondary btn-block btn-sm" style="font-weight:700;" onclick="openEditPackageModal(<?= htmlspecialchars(json_encode($pkg)) ?>)">
              ✏️ Edit Package
            </button>
            <a href="index.php?page=pos" class="btn btn-primary btn-block btn-sm" style="background:#7C3AED; border-color:#7C3AED; font-weight:800; text-decoration:none; text-align:center;">
              🛒 POS Sale
            </a>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- TAB 3: PERSONAL TRAINERS ROSTER -->
  <div id="ptTabPane_trainers" class="pt-tab-pane" style="display:none;">
    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(290px, 1fr)); gap:1.35rem;">
      <?php foreach ($trainers as $tr): ?>
        <div class="card" style="border-top:5px solid var(--primary); border-radius:var(--radius-lg); box-shadow:var(--shadow-card); padding:1.25rem;">
          <div style="display:flex; align-items:center; gap:0.75rem; margin-bottom:1rem;">
            <div style="width:46px; height:46px; border-radius:50%; background:linear-gradient(135deg, var(--primary) 0%, #38BDF8 100%); color:#fff; display:flex; align-items:center; justify-content:center; font-weight:900; font-size:1.2rem;">
              <?= strtoupper(substr($tr['name'], 0, 1)) ?>
            </div>
            <div>
              <strong style="font-size:1.1rem; color:var(--text-primary); display:block;"><?= htmlspecialchars($tr['name']) ?></strong>
              <span style="font-size:0.75rem; color:var(--text-muted); font-weight:700;"><?= htmlspecialchars($tr['specialization'] ?: 'Strength & Conditioning') ?></span>
            </div>
          </div>

          <div style="background:var(--bg-main); border:1px solid var(--border-color); border-radius:10px; padding:0.85rem; font-size:0.82rem; color:var(--text-secondary); display:flex; flex-direction:column; gap:0.4rem; margin-bottom:1rem;">
            <div style="display:flex; justify-content:space-between;">
              <span>📞 <strong>Contact Phone:</strong></span>
              <span class="font-mono"><?= htmlspecialchars($tr['phone']) ?></span>
            </div>
            <div style="display:flex; justify-content:space-between;">
              <span>👥 <strong>Active PT Clients:</strong></span>
              <strong style="color:var(--primary);"><?= $tr['active_clients_count'] ?> Members</strong>
            </div>
            <div style="display:flex; justify-content:space-between;">
              <span>💰 <strong>Commission Rate:</strong></span>
              <span class="font-mono"><?= $tr['commission_pct'] ?? 30 ?>% / Session</span>
            </div>
          </div>

          <div style="display:flex; gap:0.5rem;">
            <a href="https://wa.me/91<?= preg_replace('/[^0-9]/', '', $tr['phone']) ?>" target="_blank" class="btn btn-success btn-block btn-sm" style="background:#25D366; border-color:#25D366; font-weight:800; text-decoration:none; text-align:center;">
              💬 Chat Coach
            </a>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<!-- ========================================================= -->
<!-- MODALS SECTION                                            -->
<!-- ========================================================= -->

<!-- 1. ASSIGN PT TO MEMBER MODAL -->
<div class="modal" id="assignPtModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.55); z-index:100; align-items:center; justify-content:center; backdrop-filter:blur(2px);">
  <div class="modal-overlay" style="position:absolute; inset:0;" onclick="closeModal('assignPtModal')"></div>
  <div class="card" style="position:relative; z-index:10; background:#fff; width:92%; max-width:520px; border-radius:18px; padding:1.5rem; border-top:5px solid #7C3AED; box-shadow:var(--shadow-modal);">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.25rem; border-bottom:1px solid var(--border-color); padding-bottom:0.75rem;">
      <span style="font-size:1.15rem; font-weight:800; color:#7C3AED;">🏋️ Assign PT Package to Member</span>
      <button style="background:none; border:none; font-size:1.2rem; cursor:pointer;" onclick="closeModal('assignPtModal')">✕</button>
    </div>

    <form onsubmit="submitAssignPt(event)">
      <div class="form-group">
        <label class="form-label">Select Member Client *</label>
        <select id="aptMemberId" name="member_id" class="form-control" required>
          <option value="">-- Choose Member --</option>
          <?php foreach ($membersList as $m): ?>
            <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['name']) ?> (<?= $m['member_code'] ?> - <?= $m['phone'] ?>)</option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-group">
        <label class="form-label">Select PT Package *</label>
        <select id="aptPackageId" name="package_id" class="form-control" onchange="onPackageSelect(this)" required>
          <option value="">-- Choose Package --</option>
          <?php foreach ($packages as $p): ?>
            <option value="<?= $p['id'] ?>" data-price="<?= $p['price'] ?>" data-sessions="<?= $p['sessions_count'] ?? $p['sessions_total'] ?? 12 ?>" data-validity="<?= $p['validity_days'] ?? 30 ?>">
              <?= htmlspecialchars($p['title']) ?> - <?= $currency ?><?= number_format($p['price'], 2) ?> (<?= $p['sessions_count'] ?? $p['sessions_total'] ?? 12 ?> Sessions)
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-group">
        <label class="form-label">Assign Personal Trainer / Coach</label>
        <select id="aptTrainerId" name="trainer_id" class="form-control">
          <option value="">-- Unassigned / Gym Floor Trainer --</option>
          <?php foreach ($trainers as $t): ?>
            <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['name']) ?> (<?= $t['specialization'] ?: 'General Fitness' ?>)</option>
          <?php endforeach; ?>
        </select>
      </div>

      <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.75rem;">
        <div class="form-group">
          <label class="form-label">Total Sessions</label>
          <input type="number" id="aptSessionsTotal" name="sessions_total" class="form-control font-mono" value="12" required>
        </div>
        <div class="form-group">
          <label class="form-label">Price Paid (<?= $currency ?>) *</label>
          <input type="number" step="0.01" id="aptPricePaid" name="price_paid" class="form-control font-mono" placeholder="4999" required style="font-weight:800; font-size:1.1rem; color:#7C3AED;">
        </div>
      </div>

      <div class="form-group">
        <label class="form-label">Validity Expiry Date</label>
        <input type="date" id="aptEndDate" name="end_date" class="form-control font-mono" value="<?= date('Y-m-d', strtotime('+30 days')) ?>">
      </div>

      <div style="display:flex; gap:0.75rem; margin-top:1.5rem;">
        <button type="button" class="btn btn-secondary btn-block" onclick="closeModal('assignPtModal')">Cancel</button>
        <button type="submit" class="btn btn-primary btn-block" style="background:#7C3AED; border-color:#7C3AED; font-weight:800;">✓ CONFIRM ASSIGNMENT</button>
      </div>
    </form>
  </div>
</div>

<!-- 2. LOG SESSION PUNCH MODAL -->
<div class="modal" id="logSessionModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.55); z-index:100; align-items:center; justify-content:center; backdrop-filter:blur(2px);">
  <div class="modal-overlay" style="position:absolute; inset:0;" onclick="closeModal('logSessionModal')"></div>
  <div class="card" style="position:relative; z-index:10; background:#fff; width:92%; max-width:440px; border-radius:18px; padding:1.5rem; border-top:5px solid var(--success); box-shadow:var(--shadow-modal);">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.25rem; border-bottom:1px solid var(--border-color); padding-bottom:0.75rem;">
      <span style="font-size:1.15rem; font-weight:800; color:var(--success);">⚡ Log Workout Punch</span>
      <button style="background:none; border:none; font-size:1.2rem; cursor:pointer;" onclick="closeModal('logSessionModal')">✕</button>
    </div>

    <form onsubmit="submitLogSession(event)">
      <input type="hidden" id="lsPtSubId">

      <div style="background:#ECFDF5; border:1px solid #A7F3D0; padding:0.85rem; border-radius:8px; margin-bottom:1rem; font-size:0.85rem;">
        <div>Member: <strong id="lsMemberName" style="color:var(--text-primary);"></strong></div>
        <div>Remaining Sessions: <strong id="lsRemainingSessions" style="color:#065F46; font-size:1.15rem; font-family:var(--font-mono);"></strong></div>
      </div>

      <div class="form-group">
        <label class="form-label">Workout Date &amp; Time</label>
        <input type="datetime-local" id="lsPunchTime" class="form-control font-mono" value="<?= date('Y-m-d\TH:i') ?>">
      </div>

      <div class="form-group">
        <label class="form-label">Exercise Focus / Training Notes</label>
        <input type="text" id="lsNotes" class="form-control" placeholder="e.g. Chest & Triceps Hypertrophy, Form Correction">
      </div>

      <div style="display:flex; gap:0.75rem; margin-top:1.5rem;">
        <button type="button" class="btn btn-secondary btn-block" onclick="closeModal('logSessionModal')">Cancel</button>
        <button type="submit" class="btn btn-primary btn-block" style="font-weight:800;">✓ PUNCH 1 SESSION</button>
      </div>
    </form>
  </div>
</div>

<!-- 3. CREATE / EDIT PT PACKAGE MODAL -->
<div class="modal" id="packageModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.55); z-index:100; align-items:center; justify-content:center; backdrop-filter:blur(2px);">
  <div class="modal-overlay" style="position:absolute; inset:0;" onclick="closeModal('packageModal')"></div>
  <div class="card" style="position:relative; z-index:10; background:#fff; width:92%; max-width:480px; border-radius:18px; padding:1.5rem; border-top:5px solid #7C3AED; box-shadow:var(--shadow-modal);">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.25rem; border-bottom:1px solid var(--border-color); padding-bottom:0.75rem;">
      <span id="packageModalTitle" style="font-size:1.15rem; font-weight:800; color:#7C3AED;">📦 Create PT Package</span>
      <button style="background:none; border:none; font-size:1.2rem; cursor:pointer;" onclick="closeModal('packageModal')">✕</button>
    </div>

    <form onsubmit="submitSavePackage(event)">
      <input type="hidden" id="pkgId" name="id" value="0">

      <div class="form-group">
        <label class="form-label">Package Title *</label>
        <input type="text" id="pkgTitle" name="title" class="form-control" placeholder="e.g. 24 Sessions Body Transformation" required>
      </div>

      <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.75rem;">
        <div class="form-group">
          <label class="form-label">Total Sessions *</label>
          <input type="number" id="pkgSessions" name="sessions_count" class="form-control font-mono" value="12" min="1" required>
        </div>
        <div class="form-group">
          <label class="form-label">Validity (Days) *</label>
          <input type="number" id="pkgValidity" name="validity_days" class="form-control font-mono" value="30" min="1" required>
        </div>
      </div>

      <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.75rem;">
        <div class="form-group">
          <label class="form-label">Package Price (<?= $currency ?>) *</label>
          <input type="number" step="0.01" id="pkgPrice" name="price" class="form-control font-mono" placeholder="4999" required style="font-weight:800; font-size:1.1rem; color:#7C3AED;">
        </div>
        <div class="form-group">
          <label class="form-label">Trainer Commission (%)</label>
          <input type="number" id="pkgCommission" name="commission_pct" class="form-control font-mono" value="30" min="0" max="100">
        </div>
      </div>

      <div class="form-group">
        <label class="form-label">Assign Default Trainer (Optional)</label>
        <select id="pkgTrainerId" name="trainer_id" class="form-control">
          <option value="">-- Any Certified Trainer --</option>
          <?php foreach ($trainers as $t): ?>
            <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div style="display:flex; gap:0.75rem; margin-top:1.5rem;">
        <button type="button" class="btn btn-secondary btn-block" onclick="closeModal('packageModal')">Cancel</button>
        <button type="submit" class="btn btn-primary btn-block" style="background:#7C3AED; border-color:#7C3AED; font-weight:800;">✓ SAVE PACKAGE</button>
      </div>
    </form>
  </div>
</div>

<style>
.pt-nav-tab {
  background: var(--bg-surface);
  border: 1px solid var(--border-color);
  border-bottom: none;
  color: var(--text-secondary);
  padding: 0.65rem 1.15rem;
  border-radius: 10px 10px 0 0;
  font-weight: 700;
  font-size: 0.85rem;
  cursor: pointer;
  transition: all var(--transition-fast);
}
.pt-nav-tab.active {
  background: #7C3AED;
  color: #fff;
  border-color: #7C3AED;
  box-shadow: 0 -2px 8px rgba(124,58,237,0.25);
}
</style>

<script>
function switchPtTab(tab) {
  document.querySelectorAll('.pt-nav-tab').forEach(t => t.classList.remove('active'));
  document.querySelectorAll('.pt-tab-pane').forEach(p => p.style.display = 'none');

  const btn = document.getElementById('ptTabBtn_' + tab);
  const pane = document.getElementById('ptTabPane_' + tab);

  if (btn) btn.classList.add('active');
  if (pane) pane.style.display = 'block';
}

let ptSearchTimer = null;
function searchPtClients(query) {
  clearTimeout(ptSearchTimer);
  ptSearchTimer = setTimeout(() => {
    requestAnimationFrame(() => {
      query = (query || '').toLowerCase().trim();
      const rows = document.querySelectorAll('#ptClientsTable tbody tr.pt-client-row');
      for (let i = 0; i < rows.length; i++) {
        const row = rows[i];
        const name = row.getAttribute('data-name') || '';
        const coach = row.getAttribute('data-coach') || '';
        const pkg = row.getAttribute('data-pkg') || '';
        row.style.display = (!query || name.includes(query) || coach.includes(query) || pkg.includes(query)) ? '' : 'none';
      }
    });
  }, 60);
}

function onPackageSelect(select) {
  const opt = select.options[select.selectedIndex];
  if (opt && opt.value) {
    const price = opt.getAttribute('data-price');
    const sessions = opt.getAttribute('data-sessions');
    const validity = parseInt(opt.getAttribute('data-validity')) || 30;

    if (price) document.getElementById('aptPricePaid').value = price;
    if (sessions) document.getElementById('aptSessionsTotal').value = sessions;

    const d = new Date();
    d.setDate(d.getDate() + validity);
    document.getElementById('aptEndDate').value = d.toISOString().slice(0, 10);
  }
}

function submitAssignPt(e) {
  e.preventDefault();
  const payload = {
    member_id: document.getElementById('aptMemberId').value,
    package_id: document.getElementById('aptPackageId').value,
    trainer_id: document.getElementById('aptTrainerId').value || null,
    sessions_total: document.getElementById('aptSessionsTotal').value,
    price_paid: document.getElementById('aptPricePaid').value,
    end_date: document.getElementById('aptEndDate').value || null
  };

  fetch('api/trainers.php?action=assign_pt', {
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
      showToast(res.message || 'PT Assigned successfully!', 'success');
      closeModal('assignPtModal');
      setTimeout(() => location.reload(), 1000);
    } else {
      showToast(res.message || 'Assignment failed', 'danger');
    }
  })
  .catch(err => {
    showToast('Network error assigning PT', 'danger');
  });
}

function openLogSessionModal(subId, memberName, remaining) {
  document.getElementById('lsPtSubId').value = subId;
  document.getElementById('lsMemberName').innerText = memberName;
  document.getElementById('lsRemainingSessions').innerText = remaining + ' Left';
  document.getElementById('lsNotes').value = '';
  openModal('logSessionModal');
}

function submitLogSession(e) {
  e.preventDefault();
  const subId = document.getElementById('lsPtSubId').value;
  const punchTime = document.getElementById('lsPunchTime').value;
  const notes = document.getElementById('lsNotes').value.trim();

  fetch('api/trainers.php?action=log_session', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      subscription_id: subId,
      punch_time: punchTime,
      notes: notes
    })
  })
  .then(res => res.text())
  .then(text => {
    try { return JSON.parse(text); } catch(e) { return { success: false, message: text }; }
  })
  .then(res => {
    if (res.success) {
      showToast(res.message || 'Session punched successfully!', 'success');
      closeModal('logSessionModal');
      setTimeout(() => location.reload(), 1000);
    } else {
      showToast(res.message || 'Logging failed', 'danger');
    }
  })
  .catch(err => {
    showToast('Network error during session punch', 'danger');
  });
}

function cancelPtSubscription(id, name) {
  if (confirm(`Cancel Personal Training subscription for ${name}?`)) {
    fetch('api/trainers.php?action=cancel_subscription', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ subscription_id: id })
    })
    .then(res => res.text())
    .then(text => {
      try { return JSON.parse(text); } catch(e) { return { success: false, message: text }; }
    })
    .then(res => {
      if (res.success) {
        showToast(res.message || 'Subscription cancelled', 'success');
        setTimeout(() => location.reload(), 1000);
      } else {
        showToast(res.message || 'Cancellation failed', 'danger');
      }
    });
  }
}

function openCreatePackageModal() {
  document.getElementById('pkgId').value = 0;
  document.getElementById('packageModalTitle').innerText = '📦 Create PT Package';
  document.getElementById('pkgTitle').value = '';
  document.getElementById('pkgSessions').value = 12;
  document.getElementById('pkgValidity').value = 30;
  document.getElementById('pkgPrice').value = '';
  document.getElementById('pkgCommission').value = 30;
  document.getElementById('pkgTrainerId').value = '';
  openModal('packageModal');
}

function openEditPackageModal(pkg) {
  document.getElementById('pkgId').value = pkg.id;
  document.getElementById('packageModalTitle').innerText = '✏️ Edit PT Package: ' + pkg.title;
  document.getElementById('pkgTitle').value = pkg.title || '';
  document.getElementById('pkgSessions').value = pkg.sessions_count || pkg.sessions_total || 12;
  document.getElementById('pkgValidity').value = pkg.validity_days || 30;
  document.getElementById('pkgPrice').value = pkg.price || '';
  document.getElementById('pkgCommission').value = pkg.commission_pct || 30;
  document.getElementById('pkgTrainerId').value = pkg.trainer_id || '';
  openModal('packageModal');
}

function submitSavePackage(e) {
  e.preventDefault();
  const formData = new FormData(e.target);

  fetch('api/trainers.php?action=save_package', {
    method: 'POST',
    body: formData
  })
  .then(res => res.text())
  .then(text => {
    try { return JSON.parse(text); } catch(e) { return { success: false, message: text }; }
  })
  .then(res => {
    if (res.success) {
      showToast(res.message || 'Package saved successfully!', 'success');
      closeModal('packageModal');
      setTimeout(() => location.reload(), 1000);
    } else {
      showToast(res.message || 'Saving failed', 'danger');
    }
  })
  .catch(err => {
    showToast('Network error saving package', 'danger');
  });
}
</script>
