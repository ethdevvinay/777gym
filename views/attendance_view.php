<!-- Core Attendance Management System View -->
<?php
$db = getDB();

// Today's Attendance Logs
$stmt = $db->query("
    SELECT a.*, m.name as member_name, m.member_code, m.phone, m.photo_url, d.name as device_name 
    FROM attendance a 
    JOIN members m ON a.member_id = m.id 
    LEFT JOIN devices d ON a.device_id = d.id 
    ORDER BY a.check_in_time DESC
");
$attendanceLogs = $stmt->fetchAll();

$todayCount = count($attendanceLogs);
$biometricCount = (int) $db->query("SELECT COUNT(*) FROM attendance WHERE verification_method = 'biometric' AND DATE(check_in_time) = CURRENT_DATE()")->fetchColumn();
$qrCount = (int) $db->query("SELECT COUNT(*) FROM attendance WHERE verification_method = 'qr' AND DATE(check_in_time) = CURRENT_DATE()")->fetchColumn();
$manualCount = (int) $db->query("SELECT COUNT(*) FROM attendance WHERE verification_method = 'manual' AND DATE(check_in_time) = CURRENT_DATE()")->fetchColumn();

// Fetch Active Members for Manual Check-In Modal
$membersList = $db->query("SELECT id, name, member_code, phone FROM members WHERE status = 'active'")->fetchAll();
?>

<div class="page-content att-page-container">
  
  <!-- ========================================================= -->
  <!-- 1. COMPACT PAGE HEADER & ACTIONS                          -->
  <!-- ========================================================= -->
  <header class="att-header-card">
    <div class="att-header-main">
      <div class="att-header-tag">
        <span class="att-status-dot" aria-hidden="true"></span>
        <span>ACCESS CONTROL SYSTEM</span>
      </div>
      <h1 class="att-page-title">
        <span aria-hidden="true">⏱️</span> <span>Attendance Management</span>
      </h1>
      <p class="att-page-subtitle">Real-time check-in tracker, biometric sync status &amp; manual override</p>
    </div>

    <div class="att-header-actions">
      <button type="button" class="btn btn-primary att-action-btn" onclick="openModal('manualCheckinModal')" title="Manually record member entry [F5]">
        <span>+ Manual Check-In</span>
        <kbd class="att-kbd">F5</kbd>
      </button>

      <button type="button" class="btn btn-outline att-action-btn" onclick="Scanner.openCameraScanner((code) => { handleQRCheckin(code); })" title="Scan member QR code using camera">
        <span>📷 QR Check-In</span>
      </button>

      <a href="index.php?page=devices" class="btn btn-secondary att-action-btn" title="View Biometric Turnstiles & Devices">
        <span>📟 Biometric Status</span>
      </a>

      <button type="button" class="btn btn-outline att-action-btn" onclick="exportAttendanceCSV()" title="Export today's attendance to CSV">
        <span>⬇️ Export CSV</span>
      </button>
    </div>
  </header>

  <!-- ========================================================= -->
  <!-- 2. 5 BALANCED KPI SUMMARY CARDS                           -->
  <!-- ========================================================= -->
  <section class="att-kpi-grid" aria-label="Attendance Overview Metrics">
    
    <!-- 1. Today's Total Visits -->
    <div class="card att-kpi-card kpi-blue">
      <div class="att-kpi-header">
        <span class="att-kpi-label">Today's Total Visits</span>
        <div class="att-kpi-icon text-primary" aria-hidden="true">👥</div>
      </div>
      <div class="att-kpi-body">
        <div class="att-kpi-val text-primary"><?= number_format($todayCount) ?></div>
        <span class="att-kpi-sub">Total check-in punches</span>
      </div>
    </div>

    <!-- 2. Biometric Check-Ins -->
    <div class="card att-kpi-card kpi-green">
      <div class="att-kpi-header">
        <span class="att-kpi-label">Biometric Check-Ins</span>
        <div class="att-kpi-icon text-success" aria-hidden="true">👆</div>
      </div>
      <div class="att-kpi-body">
        <div class="att-kpi-val text-success"><?= number_format($biometricCount) ?></div>
        <span class="att-kpi-sub">Fingerprint &amp; Face Sync</span>
      </div>
    </div>

    <!-- 3. QR Check-Ins -->
    <div class="card att-kpi-card kpi-purple">
      <div class="att-kpi-header">
        <span class="att-kpi-label">Digital QR Scans</span>
        <div class="att-kpi-icon text-purple" aria-hidden="true">📱</div>
      </div>
      <div class="att-kpi-body">
        <div class="att-kpi-val text-purple"><?= number_format($qrCount) ?></div>
        <span class="att-kpi-sub">App &amp; ID Card QR code</span>
      </div>
    </div>

    <!-- 4. Manual Check-Ins -->
    <div class="card att-kpi-card kpi-orange">
      <div class="att-kpi-header">
        <span class="att-kpi-label">Manual Staff Entries</span>
        <div class="att-kpi-icon text-warning" aria-hidden="true">✍️</div>
      </div>
      <div class="att-kpi-body">
        <div class="att-kpi-val text-warning"><?= number_format($manualCount) ?></div>
        <span class="att-kpi-sub">Reception desk overrides</span>
      </div>
    </div>

  </section>

  <!-- ========================================================= -->
  <!-- 2b. LIVE BIOMETRIC MACHINE FEED (Real-time Auto-Refresh)  -->
  <!-- ========================================================= -->
  <section class="card att-live-feed-card" aria-label="Live Biometric Machine Feed">
    <div class="att-live-feed-header">
      <div style="display:flex; align-items:center; gap:0.75rem;">
        <div class="att-live-pulse-icon">📡</div>
        <div>
          <h2 class="att-section-title" style="margin:0;">Live Biometric Machine Feed</h2>
          <p style="font-size:0.75rem; color:var(--text-muted); margin:0;">Auto-refresh every 5 seconds • Check-In &amp; Check-Out tracking</p>
        </div>
      </div>
      <div style="display:flex; align-items:center; gap:1rem; flex-wrap:wrap;">
        <div id="liveMachineStatus" class="att-machine-status-badge" title="Machine connection status">
          <span class="att-pulse-dot" style="background:#f59e0b;"></span>
          <span id="liveMachineStatusText">Connecting...</span>
        </div>
        <div style="font-size:0.75rem; color:var(--text-muted);">
          Last punch: <strong id="liveLastPunchTime">—</strong>
        </div>
        <div style="font-size:0.75rem; color:var(--text-muted);">
          Currently inside: <strong id="liveInsideCount" style="color:var(--success);">—</strong>
        </div>
        <button class="btn btn-outline btn-sm" onclick="forceLiveFeedRefresh()" title="Refresh now">↻ Refresh</button>
      </div>
    </div>

    <!-- Alert for unregistered punches -->
    <div id="unregisteredPunchAlert" style="display:none; background:linear-gradient(135deg,#7c3aed,#a21caf); color:#fff; padding:0.75rem 1.25rem; font-size:0.82rem; font-weight:600; border-radius:8px; margin:0.75rem 1.25rem 0;">
      ⚠️ <span id="unregisteredPunchText">Unknown biometric IDs detected today.</span>
      <a href="index.php?page=devices" style="color:#fde68a; margin-left:0.5rem;">Review in Devices →</a>
    </div>

    <!-- Live punch cards stream -->
    <div id="liveFeedContainer" style="padding:1rem 1.25rem; min-height:120px; display:flex; flex-direction:column; gap:0.6rem;">
      <div class="att-live-placeholder">
        <div class="att-live-spinner"></div>
        <span>Waiting for biometric punches...</span>
      </div>
    </div>
  </section>

  <!-- ========================================================= -->
  <!-- 3. LIVE ATTENDANCE TABLE & SEARCH CONTROLS                -->
  <!-- ========================================================= -->
  <section class="card att-table-card" aria-label="Live Attendance Records">
    
    <!-- Table Header & Controls Bar -->
    <div class="att-table-header">
      <div class="att-table-title-group">
        <h2 class="att-section-title">
          <span aria-hidden="true">📋</span> <span>Live Check-In Activity Log</span>
        </h2>
        <span class="att-status-indicator" title="Hardware connection active and listening for punches">
          <span class="att-pulse-dot" aria-hidden="true"></span>
          <span>Hardware Online</span>
        </span>
      </div>

      <!-- Quick Search & Filter Controls -->
      <div class="att-controls-bar">
        <div class="att-search-box">
          <span class="att-search-icon" aria-hidden="true">🔍</span>
          <input type="text" id="attSearchInput" class="att-search-input" placeholder="Search by name, code, phone..." oninput="filterAttendanceTable()" autocomplete="off">
        </div>

        <div class="att-filter-pills" role="tablist" aria-label="Filter Attendance By Method">
          <button type="button" class="att-filter-pill active" onclick="setAttendanceFilter('all', this)">All (<?= $todayCount ?>)</button>
          <button type="button" class="att-filter-pill" onclick="setAttendanceFilter('inside', this)">Inside Gym</button>
          <button type="button" class="att-filter-pill" onclick="setAttendanceFilter('biometric', this)">Biometric</button>
          <button type="button" class="att-filter-pill" onclick="setAttendanceFilter('qr', this)">QR</button>
          <button type="button" class="att-filter-pill" onclick="setAttendanceFilter('manual', this)">Manual</button>
        </div>
      </div>
    </div>

    <!-- Attendance Data Table -->
    <div class="table-responsive">
      <table class="table att-table" id="attendanceTable" style="width:100%;">
        <thead>
          <tr>
            <th scope="col">Member</th>
            <th scope="col">Check-In Time</th>
            <th scope="col">Check-Out Time</th>
            <th scope="col">Verification Method</th>
            <th scope="col">Terminal Device</th>
            <th scope="col">Status / Alert</th>
            <th scope="col" style="text-align:right;">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($attendanceLogs)): ?>
            <tr id="attEmptyRow">
              <td colspan="7" class="att-empty-state">
                <div class="empty-state-content">
                  <div class="empty-state-icon" aria-hidden="true">⏱️</div>
                  <h3 class="empty-state-title">No attendance records yet</h3>
                  <p class="empty-state-text">Member check-ins will appear here automatically as they scan in.</p>
                </div>
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($attendanceLogs as $log): ?>
              <?php
                $isInside = empty($log['check_out_time']);
                $method = strtolower($log['verification_method'] ?? 'biometric');
                $methodBadgeClass = ($method === 'biometric') ? 'badge-success' : (($method === 'qr') ? 'badge-info' : 'badge-warning');
                $statusBadgeClass = ($log['status'] === 'success') ? 'badge-success' : 'badge-danger';
              ?>
              <tr class="att-row" 
                  data-name="<?= htmlspecialchars(strtolower($log['member_name'])) ?>" 
                  data-code="<?= htmlspecialchars(strtolower($log['member_code'])) ?>" 
                  data-phone="<?= htmlspecialchars($log['phone'] ?? '') ?>"
                  data-method="<?= $method ?>"
                  data-inside="<?= $isInside ? 'yes' : 'no' ?>">
                
                <!-- Member Details -->
                <td data-label="Member">
                  <div class="att-member-identity">
                    <div class="att-avatar-circle" aria-hidden="true">
                      <?= strtoupper(substr($log['member_name'], 0, 1)) ?>
                    </div>
                    <div>
                      <a href="index.php?page=member_profile&id=<?= $log['member_id'] ?>" class="att-member-name" title="View Member Profile">
                        <?= htmlspecialchars($log['member_name']) ?>
                      </a>
                      <div class="att-member-code"><?= htmlspecialchars($log['member_code']) ?></div>
                    </div>
                  </div>
                </td>

                <!-- Check-In Time -->
                <td data-label="Check-In Time">
                  <span class="att-time-checkin"><?= date('h:i A', strtotime($log['check_in_time'])) ?></span>
                </td>

                <!-- Check-Out Time -->
                <td data-label="Check-Out Time">
                  <?php if (!$isInside): ?>
                    <span class="att-time-checkout"><?= date('h:i A', strtotime($log['check_out_time'])) ?></span>
                  <?php else: ?>
                    <span class="badge badge-success att-inside-badge" title="Member is currently inside the gym">
                      <span class="att-dot-inside" aria-hidden="true"></span>
                      <span>INSIDE</span>
                    </span>
                  <?php endif; ?>
                </td>

                <!-- Verification Method -->
                <td data-label="Verification Method">
                  <span class="badge <?= $methodBadgeClass ?> font-bold uppercase">
                    <?= strtoupper($log['verification_method'] ?? 'BIOMETRIC') ?>
                  </span>
                </td>

                <!-- Terminal Device -->
                <td data-label="Terminal Device">
                  <span class="att-device-tag"><?= htmlspecialchars($log['device_name'] ?: 'Reception Desk Gate') ?></span>
                </td>

                <!-- Status / Alert -->
                <td data-label="Status / Alert">
                  <span class="badge <?= $statusBadgeClass ?> font-bold">
                    <?= htmlspecialchars($log['notes'] ?: strtoupper($log['status'])) ?>
                  </span>
                </td>

                <!-- Action Buttons -->
                <td data-label="Actions" style="text-align:right;">
                  <?php if ($isInside): ?>
                    <button type="button" class="btn btn-outline btn-sm att-checkout-btn" onclick="checkoutMember(<?= $log['id'] ?>)" title="Mark member checked out">
                      <span>🚪 Check-Out</span>
                    </button>
                  <?php else: ?>
                    <span class="att-completed-label">Completed</span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>

            <!-- Client-side filter no results row -->
            <tr id="attNoFilterResults" style="display:none;">
              <td colspan="7" class="att-empty-state">
                <div class="empty-state-content">
                  <div class="empty-state-icon" aria-hidden="true">🔍</div>
                  <h3 class="empty-state-title">No matching records found</h3>
                  <p class="empty-state-text">Try adjusting your search terms or filter criteria.</p>
                </div>
              </td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

  </section>

</div>

<!-- ========================================================= -->
<!-- 4. MANUAL CHECK-IN MODAL DIALOG                           -->
<!-- ========================================================= -->
<div class="modal" id="manualCheckinModal" style="display:none;">
  <div class="modal-overlay" onclick="closeModal('manualCheckinModal')" aria-hidden="true"></div>
  <div class="card att-modal-card" role="dialog" aria-labelledby="modalTitleManual" aria-modal="true">
    
    <div class="att-modal-header">
      <div class="att-modal-title-wrap">
        <span class="att-modal-icon" aria-hidden="true">✍️</span>
        <div>
          <h3 id="modalTitleManual" class="att-modal-title">Manual Check-In</h3>
          <span class="att-modal-subtitle">Record guest or reception override attendance</span>
        </div>
      </div>
      <button type="button" class="att-modal-close" onclick="closeModal('manualCheckinModal')" aria-label="Close dialog">✕</button>
    </div>

    <form onsubmit="submitManualCheckin(event)" class="att-modal-form">
      <div class="form-group">
        <label class="form-label" for="manualMemberSelect">Select Member <span class="text-danger">*</span></label>
        <select id="manualMemberSelect" name="member_id" class="form-control" required>
          <option value="">-- Choose Active Member --</option>
          <?php foreach ($membersList as $m): ?>
            <option value="<?= $m['id'] ?>">
              <?= htmlspecialchars($m['name']) ?> (<?= htmlspecialchars($m['member_code']) ?> &bull; <?= htmlspecialchars($m['phone']) ?>)
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-group">
        <label class="form-label" for="manualReasonSelect">Reason for Manual Entry</label>
        <select id="manualReasonSelect" name="reason" class="form-control">
          <option value="Fingerprint sensor failed">Fingerprint sensor failed</option>
          <option value="Member forgot RFID card / Phone">Member forgot RFID card / Phone</option>
          <option value="Admin Override">Admin Manual Override</option>
        </select>
      </div>

      <div class="att-modal-actions">
        <button type="button" class="btn btn-secondary att-modal-btn" onclick="closeModal('manualCheckinModal')">Cancel</button>
        <button type="submit" class="btn btn-primary att-modal-btn">✓ Record Check-In</button>
      </div>
    </form>
  </div>
</div>

<!-- ========================================================= -->
<!-- STYLESHEET FOR ATTENDANCE MANAGEMENT VIEW                 -->
<!-- ========================================================= -->
<style>
.att-page-container {
  display: flex;
  flex-direction: column;
  gap: 1.25rem;
  padding-bottom: 3rem;
}

/* 1. Header Card */
.att-header-card {
  background: var(--bg-surface);
  border: 1px solid var(--border-color);
  border-radius: var(--radius-lg);
  padding: 1.15rem 1.35rem;
  box-shadow: var(--shadow-subtle);
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 1rem;
}

.att-header-main {
  flex: 1 1 300px;
}

.att-header-tag {
  display: inline-flex;
  align-items: center;
  gap: 0.45rem;
  font-size: 0.7rem;
  font-weight: 800;
  color: var(--primary);
  text-transform: uppercase;
  letter-spacing: 0.06em;
  margin-bottom: 0.25rem;
}

.att-status-dot {
  width: 7px;
  height: 7px;
  border-radius: 50%;
  background: var(--primary);
}

.att-page-title {
  font-size: 1.35rem;
  font-weight: 800;
  color: var(--text-primary);
  margin: 0;
  display: flex;
  align-items: center;
  gap: 0.45rem;
  letter-spacing: -0.02em;
}

.att-page-subtitle {
  font-size: 0.8rem;
  color: var(--text-muted);
  margin: 0.2rem 0 0 0;
}

.att-header-actions {
  display: flex;
  align-items: center;
  gap: 0.6rem;
  flex-wrap: wrap;
}

.att-action-btn {
  display: inline-flex;
  align-items: center;
  gap: 0.45rem;
  font-weight: 700;
  font-size: 0.82rem;
  padding: 0.5rem 0.95rem;
  min-height: 38px;
}

.att-kbd {
  background: rgba(255, 255, 255, 0.25);
  border-radius: 4px;
  padding: 0.05rem 0.35rem;
  font-family: var(--font-mono);
  font-size: 0.68rem;
  font-weight: 800;
}

/* 2. KPI Cards Grid */
.att-kpi-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 1rem;
}

.att-kpi-card {
  padding: 1.1rem 1.25rem;
  border-radius: var(--radius-lg);
  background: var(--bg-surface);
  border: 1px solid var(--border-color);
  box-shadow: var(--shadow-subtle);
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  min-height: 110px;
  transition: transform var(--transition-fast), box-shadow var(--transition-fast);
}

.att-kpi-card:hover {
  transform: translateY(-2px);
  box-shadow: var(--shadow-card);
}

.kpi-blue   { border-left: 4px solid var(--primary); }
.kpi-green  { border-left: 4px solid var(--success); }
.kpi-purple { border-left: 4px solid var(--purple); }
.kpi-orange { border-left: 4px solid var(--warning); }

.att-kpi-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 0.5rem;
  margin-bottom: 0.4rem;
}

.att-kpi-label {
  font-size: 0.72rem;
  font-weight: 800;
  color: var(--text-muted);
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

.att-kpi-icon {
  font-size: 1.15rem;
  line-height: 1;
}

.att-kpi-body {
  display: flex;
  flex-direction: column;
}

.att-kpi-val {
  font-size: 1.85rem;
  font-weight: 800;
  font-family: var(--font-heading);
  line-height: 1;
  letter-spacing: -0.03em;
}

.att-kpi-sub {
  font-size: 0.72rem;
  color: var(--text-muted);
  font-weight: 600;
  margin-top: 0.3rem;
}

/* 3. Table Card & Controls */
.att-table-card {
  padding: 0;
  overflow: hidden;
  border-radius: var(--radius-lg);
  border: 1px solid var(--border-color);
  background: var(--bg-surface);
  box-shadow: var(--shadow-card);
}

.att-table-header {
  padding: 1.15rem 1.35rem;
  border-bottom: 1px solid var(--border-color);
  display: flex;
  flex-direction: column;
  gap: 0.85rem;
}

.att-table-title-group {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 0.5rem;
}

.att-section-title {
  font-size: 1.05rem;
  font-weight: 800;
  color: var(--text-primary);
  margin: 0;
  display: flex;
  align-items: center;
  gap: 0.45rem;
}

.att-status-indicator {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  background: #ECFDF5;
  color: #065F46;
  border: 1px solid #A7F3D0;
  padding: 0.22rem 0.65rem;
  border-radius: 99px;
  font-size: 0.72rem;
  font-weight: 800;
  letter-spacing: 0.02em;
}

.att-pulse-dot {
  width: 7px;
  height: 7px;
  background: #10B981;
  border-radius: 50%;
  box-shadow: 0 0 0 2px rgba(16, 185, 129, 0.25);
  animation: pulseDot 2s infinite ease-in-out;
}

/* Search & Filter Controls */
.att-controls-bar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 0.75rem;
}

.att-search-box {
  position: relative;
  flex: 1 1 240px;
  max-width: 380px;
}

.att-search-icon {
  position: absolute;
  left: 0.75rem;
  top: 50%;
  transform: translateY(-50%);
  font-size: 0.85rem;
  color: var(--text-muted);
  pointer-events: none;
}

.att-search-input {
  width: 100%;
  padding: 0.48rem 0.85rem 0.48rem 2.2rem;
  background: var(--bg-surface-secondary);
  border: 1px solid var(--border-color);
  border-radius: var(--radius-full);
  font-size: 0.82rem;
  font-family: inherit;
  color: var(--text-primary);
  outline: none;
  transition: all var(--transition-fast);
}

.att-search-input:focus {
  background: #FFFFFF;
  border-color: var(--primary);
  box-shadow: 0 0 0 3px var(--primary-light);
}

.att-filter-pills {
  display: flex;
  gap: 0.35rem;
  flex-wrap: wrap;
}

.att-filter-pill {
  background: var(--bg-surface-secondary);
  color: var(--text-secondary);
  border: 1px solid var(--border-color);
  padding: 0.35rem 0.75rem;
  border-radius: var(--radius-full);
  font-size: 0.75rem;
  font-weight: 700;
  cursor: pointer;
  transition: all var(--transition-fast);
  font-family: inherit;
}

.att-filter-pill:hover {
  background: var(--bg-surface-hover);
  color: var(--text-primary);
}

.att-filter-pill.active {
  background: var(--text-primary);
  color: #FFFFFF;
  border-color: var(--text-primary);
}

/* Data Table Typography & Rows */
.att-table thead th {
  background: var(--bg-surface-secondary);
  color: var(--text-muted);
  font-size: 0.72rem;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  padding: 0.75rem 1.15rem;
  border-bottom: 1px solid var(--border-color);
  white-space: nowrap;
}

.att-table tbody td {
  padding: 0.75rem 1.15rem;
  vertical-align: middle;
  border-bottom: 1px solid var(--border-light);
  font-size: 0.85rem;
}

.att-table tbody tr:last-child td {
  border-bottom: none;
}

.att-table tbody tr:hover td {
  background: #F8FAFC;
}

.att-member-identity {
  display: flex;
  align-items: center;
  gap: 0.65rem;
}

.att-avatar-circle {
  width: 32px;
  height: 32px;
  border-radius: 50%;
  background: var(--primary-light);
  color: var(--primary);
  border: 1px solid var(--primary-border);
  font-weight: 800;
  font-size: 0.78rem;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.att-member-name {
  font-weight: 800;
  color: var(--text-primary);
  text-decoration: none;
  font-size: 0.88rem;
  display: block;
  line-height: 1.2;
}

.att-member-name:hover {
  color: var(--primary);
}

.att-member-code {
  font-size: 0.72rem;
  color: var(--text-muted);
  font-family: var(--font-mono);
  margin-top: 0.1rem;
}

.att-time-checkin {
  font-weight: 800;
  color: var(--primary);
  font-size: 0.85rem;
}

.att-time-checkout {
  font-weight: 600;
  color: var(--text-secondary);
}

.att-inside-badge {
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  padding: 0.22rem 0.55rem;
}

.att-dot-inside {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: #10B981;
}

.att-device-tag {
  color: var(--text-secondary);
  font-size: 0.82rem;
  font-weight: 600;
}

.att-checkout-btn {
  font-weight: 700;
  font-size: 0.76rem;
  padding: 0.3rem 0.65rem;
  min-height: 28px;
  border-color: #CBD5E1;
}

.att-checkout-btn:hover {
  background: #FEE2E2;
  border-color: #FECACA;
  color: #DC2626;
}

.att-completed-label {
  font-size: 0.76rem;
  color: var(--text-muted);
  font-weight: 600;
}

/* Empty States */
.att-empty-state {
  text-align: center;
  padding: 3.5rem 1.5rem !important;
}

.empty-state-content {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
}

.empty-state-icon {
  font-size: 2rem;
  margin-bottom: 0.5rem;
}

.empty-state-title {
  font-size: 1.05rem;
  font-weight: 800;
  color: var(--text-primary);
  margin: 0;
}

.empty-state-text {
  font-size: 0.82rem;
  color: var(--text-muted);
  margin: 0.25rem 0 0 0;
}

/* 4. Manual Checkin Modal */
.att-modal-card {
  position: relative;
  z-index: 10;
  background: #FFFFFF;
  width: 92%;
  max-width: 460px;
  border-radius: var(--radius-xl);
  padding: 1.5rem;
  box-shadow: var(--shadow-modal);
  border: 1px solid var(--border-color);
}

.att-modal-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 1.25rem;
  padding-bottom: 0.75rem;
  border-bottom: 1px solid var(--border-color);
}

.att-modal-title-wrap {
  display: flex;
  align-items: center;
  gap: 0.65rem;
}

.att-modal-icon {
  font-size: 1.35rem;
}

.att-modal-title {
  font-size: 1.15rem;
  font-weight: 800;
  color: var(--text-primary);
  margin: 0;
}

.att-modal-subtitle {
  font-size: 0.75rem;
  color: var(--text-muted);
  display: block;
}

.att-modal-close {
  background: none;
  border: none;
  font-size: 1.2rem;
  color: var(--text-muted);
  cursor: pointer;
  line-height: 1;
  padding: 0.25rem;
  border-radius: 4px;
}

.att-modal-close:hover {
  color: var(--text-primary);
}

.att-modal-form .form-group {
  margin-bottom: 1rem;
}

.att-modal-actions {
  display: flex;
  gap: 0.75rem;
  margin-top: 1.5rem;
}

.att-modal-btn {
  flex: 1;
  justify-content: center;
  font-weight: 700;
}

/* ========================================================= -->
<!-- RESPONSIVE BREAKPOINTS                                    -->
<!-- ========================================================= --> */
@media (max-width: 1024px) {
  .att-kpi-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}

@media (max-width: 768px) {
  .att-header-card {
    flex-direction: column;
    align-items: flex-start;
    padding: 1rem;
  }

  .att-header-actions {
    width: 100%;
  }

  .att-action-btn {
    flex: 1 1 120px;
    justify-content: center;
  }

  .att-kpi-grid {
    grid-template-columns: 1fr;
    gap: 0.75rem;
  }

  .att-controls-bar {
    flex-direction: column;
    align-items: stretch;
  }

  .att-search-box {
    max-width: 100%;
  }

  .att-filter-pills {
    overflow-x: auto;
    padding-bottom: 0.25rem;
  }

  /* Responsive Stacked Table on Mobile */
  .att-table thead {
    display: none;
  }

  .att-table, 
  .att-table tbody, 
  .att-table tr, 
  .att-table td {
    display: block;
    width: 100%;
  }

  .att-table tbody tr {
    margin: 0.85rem;
    background: var(--bg-surface);
    border: 1px solid var(--border-color);
    border-radius: var(--radius-md);
    padding: 0.75rem 0.9rem;
    box-shadow: var(--shadow-subtle);
  }

  .att-table tbody td {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.45rem 0;
    border-bottom: 1px solid var(--border-light);
    font-size: 0.82rem;
  }

  .att-table tbody td:last-child {
    border-bottom: none;
    padding-top: 0.65rem;
  }

  .att-table tbody td::before {
    content: attr(data-label);
    font-weight: 700;
    font-size: 0.72rem;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--text-muted);
    flex-shrink: 0;
    margin-right: 0.75rem;
  }

  .att-table tbody td[data-label="Actions"]::before {
    display: none;
  }

  .att-table tbody td .att-checkout-btn {
    width: 100%;
    text-align: center;
  }

  .att-empty-state {
    display: block !important;
  }
  .att-empty-state::before {
    display: none !important;
  }
}

@media (max-width: 480px) {
  .att-page-title {
    font-size: 1.2rem;
  }
  .att-action-btn {
    flex: 1 1 100%;
  }
}
</style>

<!-- ========================================================= -->
<!-- JAVASCRIPT LOGIC, SCANNER, SEARCH & FILTERING             -->
<!-- ========================================================= -->
<script>
// Active Method Filter state
let currentFilter = 'all';

function setAttendanceFilter(filterKey, btnEl) {
  currentFilter = filterKey;
  document.querySelectorAll('.att-filter-pill').forEach(b => b.classList.remove('active'));
  if (btnEl) btnEl.classList.add('active');
  filterAttendanceTable();
}

function filterAttendanceTable() {
  const query = (document.getElementById('attSearchInput')?.value || '').toLowerCase().trim();
  const rows = document.querySelectorAll('.att-row');
  let visibleCount = 0;

  rows.forEach(row => {
    const name = row.getAttribute('data-name') || '';
    const code = row.getAttribute('data-code') || '';
    const phone = row.getAttribute('data-phone') || '';
    const method = row.getAttribute('data-method') || '';
    const isInside = row.getAttribute('data-inside') === 'yes';

    // 1. Text match (Name, Code, Phone)
    const matchesSearch = !query || name.includes(query) || code.includes(query) || phone.includes(query);

    // 2. Filter Tab match
    let matchesFilter = true;
    if (currentFilter === 'inside') {
      matchesFilter = isInside;
    } else if (currentFilter === 'biometric' || currentFilter === 'qr' || currentFilter === 'manual') {
      matchesFilter = (method === currentFilter);
    }

    if (matchesSearch && matchesFilter) {
      row.style.display = '';
      visibleCount++;
    } else {
      row.style.display = 'none';
    }
  });

  const noResultsEl = document.getElementById('attNoFilterResults');
  if (noResultsEl) {
    noResultsEl.style.display = (visibleCount === 0 && rows.length > 0) ? '' : 'none';
  }
}

// Check-in & Check-out Handlers
function submitManualCheckin(e) {
  e.preventDefault();
  const formData = new FormData(e.target);
  const memberId = formData.get('member_id');
  const reason = formData.get('reason');

  if (!navigator.onLine) {
    if (window.OfflineManager) {
      window.OfflineManager.queueAttendance({
        action: 'checkin',
        member_id: memberId,
        verification_method: 'manual',
        notes: reason || 'Manual Check-in',
        time: new Date().toISOString()
      });
      showToast('⚡ Check-In Saved Locally! (Offline Mode — Auto-syncs when online)', 'warning');
      closeModal('manualCheckinModal');
    }
    return;
  }

  fetch('api/attendance.php?action=checkin', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: `member_id=${memberId}&verification_method=manual&notes=${encodeURIComponent(reason)}`
  })
  .then(res => res.json())
  .then(res => {
    showToast(res.message, res.success ? 'success' : 'danger');
    closeModal('manualCheckinModal');
    setTimeout(() => location.reload(), 1000);
  })
  .catch(err => {
    if (window.OfflineManager) {
      window.OfflineManager.queueAttendance({
        action: 'checkin',
        member_id: memberId,
        verification_method: 'manual',
        notes: reason || 'Manual Check-in',
        time: new Date().toISOString()
      });
      showToast('⚡ Network Error — Check-In Saved Offline! (Will auto-sync)', 'warning');
      closeModal('manualCheckinModal');
    } else {
      showToast('Failed to record manual check-in', 'danger');
    }
  });
}

function checkoutMember(logId) {
  if (!confirm('Mark this member as checked out?')) return;

  if (!navigator.onLine) {
    if (window.OfflineManager) {
      window.OfflineManager.queueAttendance({
        action: 'checkout',
        att_id: logId,
        time: new Date().toISOString()
      });
      showToast('⚡ Check-Out Saved Locally! (Offline Mode — Auto-syncs when online)', 'warning');
    }
    return;
  }

  fetch(`api/devices.php?action=manual_checkout&att_id=${logId}`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: `att_id=${logId}`
  })
  .then(res => res.json())
  .then(res => {
    if (res.success) {
      showToast(`✅ Checked-out! Duration: ${res.data.duration_minutes} mins`, 'success');
      setTimeout(() => location.reload(), 1000);
    } else {
      showToast(res.message || 'Checkout failed', 'danger');
    }
  })
  .catch(() => {
    if (window.OfflineManager) {
      window.OfflineManager.queueAttendance({
        action: 'checkout',
        att_id: logId,
        time: new Date().toISOString()
      });
      showToast('⚡ Network Error — Checkout Saved Offline! (Will auto-sync)', 'warning');
    } else {
      showToast('Checkout request failed. Please try again.', 'danger');
    }
  });
}

function handleQRCheckin(code) {
  showToast('Scanning QR Code: ' + code, 'info');

  if (!navigator.onLine) {
    if (window.OfflineManager) {
      window.OfflineManager.queueAttendance({
        action: 'checkin',
        member_code: code,
        verification_method: 'qr',
        notes: 'QR Gate Scan',
        time: new Date().toISOString()
      });
      showToast('⚡ QR Check-In Saved Locally! (Offline Mode — Auto-syncs when online)', 'warning');
    }
    return;
  }

  fetch('api/devices.php?action=webhook', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ biometric_id: code, serial_no: 'CAM-QR-001' })
  })
  .then(res => res.json())
  .then(res => {
    showToast(res.message, res.success ? 'success' : 'warning');
    setTimeout(() => location.reload(), 1200);
  })
  .catch(err => {
    if (window.OfflineManager) {
      window.OfflineManager.queueAttendance({
        action: 'checkin',
        member_code: code,
        verification_method: 'qr',
        notes: 'QR Gate Scan',
        time: new Date().toISOString()
      });
      showToast('⚡ Network Error — QR Scan Saved Offline! (Will auto-sync)', 'warning');
    } else {
      showToast('QR Verification request error', 'danger');
    }
  });
}

function exportAttendanceCSV() {
  const today = new Date().toISOString().split('T')[0];
  window.open(`api/attendance_sync.php?action=export_csv&from_date=${today}&to_date=${today}`, '_blank');
}

// ============================================================
// LIVE BIOMETRIC FEED — Real-Time Auto Polling (every 5s)
// ============================================================
let liveFeedMaxId    = 0;
let liveFeedInterval = null;
let liveFirstLoad    = true;

function initLiveFeed() {
  fetchLiveFeed(); // immediate first call
  liveFeedInterval = setInterval(fetchLiveFeed, 5000); // then every 5s
}

function forceLiveFeedRefresh() {
  fetchLiveFeed();
  showToast('Feed refreshed!', 'info');
}

function fetchLiveFeed() {
  fetch(`api/attendance_sync.php?action=live_feed&since_id=${liveFirstLoad ? 0 : liveFeedMaxId}&limit=20`)
    .then(res => res.json())
    .then(res => {
      if (!res.success) return;

      const { logs, max_id, inside_count, display_time } = res.data;

      // Update machine status to ONLINE
      const statusBadge = document.getElementById('liveMachineStatus');
      const statusText  = document.getElementById('liveMachineStatusText');
      if (statusBadge && statusText) {
        statusBadge.style.borderColor = '#10b981';
        statusText.textContent = '● MACHINE ONLINE';
        statusText.style.color = '#10b981';
      }

      // Update inside count
      const insideEl = document.getElementById('liveInsideCount');
      if (insideEl) insideEl.textContent = inside_count;

      // Update KPI inside card too
      const kpiInside = document.getElementById('kpiCurrentlyInside');
      if (kpiInside) kpiInside.textContent = inside_count;

      // Update last punch time
      const lastPunchEl = document.getElementById('liveLastPunchTime');
      if (lastPunchEl) lastPunchEl.textContent = display_time;

      if (logs && logs.length > 0) {
        liveFeedMaxId = Math.max(liveFeedMaxId, max_id);
        renderLiveFeedCards(logs, liveFirstLoad);
        liveFirstLoad = false;

        // Update KPI total count
        const kpiTotalEl = document.getElementById('kpiTodayTotal');
        if (kpiTotalEl && !liveFirstLoad) {
          const current = parseInt(kpiTotalEl.textContent) || 0;
          kpiTotalEl.textContent = current + logs.length;
        }
      } else if (liveFirstLoad) {
        renderLiveFeedEmpty();
        liveFirstLoad = false;
      }

      // Check unregistered punches
      checkUnregisteredPunches();
    })
    .catch(() => {
      // Machine offline
      const statusText = document.getElementById('liveMachineStatusText');
      if (statusText) {
        statusText.textContent = '● OFFLINE / NO DATA';
        statusText.style.color = '#ef4444';
      }
    });
}

function checkUnregisteredPunches() {
  fetch('api/devices.php?action=unregistered_punches')
    .then(r => r.json())
    .then(r => {
      const alertEl = document.getElementById('unregisteredPunchAlert');
      const textEl  = document.getElementById('unregisteredPunchText');
      if (!alertEl || !r.success) return;
      if (r.data && r.data.length > 0) {
        alertEl.style.display = 'flex';
        textEl.textContent = `${r.data.length} unknown biometric ID(s) punched today — not registered in system.`;
      } else {
        alertEl.style.display = 'none';
      }
    })
    .catch(() => {});
}

function renderLiveFeedCards(logs, replaceAll) {
  const container = document.getElementById('liveFeedContainer');
  if (!container) return;

  if (replaceAll) container.innerHTML = '';

  logs.forEach(log => {
    const isCheckOut  = !!log.check_out_time;
    const isExpired   = log.status === 'expired_alert';
    const methodIcon  = log.verification_method === 'biometric' ? '👆' : (log.verification_method === 'qr' ? '📱' : '✍️');
    const punchType   = isCheckOut ? 'CHECK-OUT' : 'CHECK-IN';
    const punchColor  = isCheckOut ? '#3b82f6' : (isExpired ? '#ef4444' : '#10b981');
    const timeLabel   = isCheckOut
      ? new Date(log.check_out_time).toLocaleTimeString('en-IN', {hour:'2-digit', minute:'2-digit', hour12:true})
      : new Date(log.check_in_time).toLocaleTimeString('en-IN',  {hour:'2-digit', minute:'2-digit', hour12:true});
    const duration    = log.duration_minutes > 0 ? ` • ${log.duration_minutes} mins` : '';
    const initials    = (log.member_name || '?').charAt(0).toUpperCase();

    const card = document.createElement('div');
    card.className  = 'att-live-card att-live-card--new';
    card.innerHTML  = `
      <div class="att-live-card-left">
        <div class="att-live-avatar" style="background:${punchColor}22; color:${punchColor};">${initials}</div>
        <div>
          <div class="att-live-member">${log.member_name || 'Unknown'}</div>
          <div class="att-live-code">${log.member_code || ''} ${log.verification_method ? '• ' + methodIcon + ' ' + log.verification_method.toUpperCase() : ''}</div>
        </div>
      </div>
      <div class="att-live-card-right">
        <span class="att-live-type-badge" style="background:${punchColor}22; color:${punchColor};">${punchType}${duration}</span>
        <span class="att-live-time">${timeLabel}</span>
        ${isExpired ? '<span class="att-live-expired-badge">⚠️ EXPIRED</span>' : ''}
        ${!isCheckOut && !log.check_out_time ? `<button class="btn btn-outline btn-sm" style="font-size:0.7rem; padding:0.2rem 0.5rem;" onclick="checkoutMember(${log.id})">🚪 Out</button>` : ''}
      </div>
    `;

    // Insert at top for newest-first
    container.insertBefore(card, container.firstChild);

    // Remove animation class after it plays
    setTimeout(() => card.classList.remove('att-live-card--new'), 600);

    // Keep max 30 cards in DOM
    while (container.children.length > 30) {
      container.removeChild(container.lastChild);
    }
  });
}

function renderLiveFeedEmpty() {
  const container = document.getElementById('liveFeedContainer');
  if (container) {
    container.innerHTML = `
      <div class="att-live-placeholder">
        <span style="font-size:2rem;">📡</span>
        <span>No check-ins today yet. Waiting for biometric punches...</span>
        <span style="font-size:0.75rem; color:var(--text-muted);">Machine will push data automatically when a member scans</span>
      </div>
    `;
  }
}

// Start live feed on page load
document.addEventListener('DOMContentLoaded', initLiveFeed);

// F5 hotkey for manual check-in
document.addEventListener('keydown', (e) => {
  if (e.key === 'F5' && !e.ctrlKey && !e.metaKey) {
    e.preventDefault();
    openModal('manualCheckinModal');
  }
});
</script>

<!-- ========================================================= -->
<!-- LIVE FEED STYLES                                          -->
<!-- ========================================================= -->
<style>
/* Live Feed Card */
.att-live-feed-card {
  padding: 0;
  border-radius: var(--radius-lg);
  border: 1px solid var(--border-color);
  overflow: hidden;
  background: var(--bg-surface);
}

.att-live-feed-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 0.75rem;
  padding: 1rem 1.25rem;
  border-bottom: 1px solid var(--border-color);
  background: linear-gradient(135deg, rgba(16,185,129,0.05), transparent);
}

.att-live-pulse-icon {
  font-size: 1.4rem;
  animation: att-pulse-icon 2s ease-in-out infinite;
}

@keyframes att-pulse-icon {
  0%, 100% { transform: scale(1); opacity: 1; }
  50% { transform: scale(1.15); opacity: 0.8; }
}

.att-machine-status-badge {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  background: var(--bg-main);
  border: 1px solid var(--border-color);
  border-radius: 20px;
  padding: 0.3rem 0.75rem;
  font-size: 0.72rem;
  font-weight: 700;
}

/* Live Feed Punch Cards */
.att-live-card {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 0.5rem;
  background: var(--bg-main);
  border: 1px solid var(--border-color);
  border-radius: 10px;
  padding: 0.65rem 0.9rem;
  transition: all 0.3s ease;
}

.att-live-card--new {
  animation: att-slide-in 0.4s ease;
  background: rgba(16,185,129,0.06);
  border-color: rgba(16,185,129,0.25);
}

@keyframes att-slide-in {
  from { opacity: 0; transform: translateY(-10px) scale(0.98); }
  to   { opacity: 1; transform: translateY(0) scale(1); }
}

.att-live-card-left {
  display: flex;
  align-items: center;
  gap: 0.65rem;
}

.att-live-card-right {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  flex-wrap: wrap;
}

.att-live-avatar {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 800;
  font-size: 0.95rem;
  flex-shrink: 0;
}

.att-live-member {
  font-weight: 700;
  font-size: 0.88rem;
  color: var(--text-primary);
}

.att-live-code {
  font-size: 0.72rem;
  color: var(--text-muted);
  font-family: var(--font-mono);
}

.att-live-type-badge {
  font-size: 0.68rem;
  font-weight: 800;
  padding: 0.2rem 0.55rem;
  border-radius: 20px;
  letter-spacing: 0.03em;
  text-transform: uppercase;
}

.att-live-time {
  font-size: 0.78rem;
  font-weight: 700;
  color: var(--text-secondary);
  font-family: var(--font-mono);
}

.att-live-expired-badge {
  font-size: 0.68rem;
  font-weight: 800;
  color: #ef4444;
  background: rgba(239,68,68,0.1);
  padding: 0.15rem 0.4rem;
  border-radius: 4px;
}

.att-live-placeholder {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 0.4rem;
  padding: 2rem;
  color: var(--text-muted);
  font-size: 0.85rem;
  text-align: center;
}

.att-live-spinner {
  width: 28px;
  height: 28px;
  border: 3px solid var(--border-color);
  border-top-color: var(--primary);
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

/* KPI inside count dynamic ID */
#kpiCurrentlyInside, #kpiTodayTotal {
  transition: all 0.3s ease;
}
</style>
