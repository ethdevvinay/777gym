<!-- Security Audit Trail Logs View -->
<?php
$db = getDB();
$stmt = $db->query("SELECT * FROM audit_logs ORDER BY id DESC LIMIT 50");
$logs = $stmt->fetchAll();

// Calculate Security & Activity Metrics from recent 50 events without extra DB queries
$totalLogsCount = count($logs);
$userActionsCount = 0;
$configChangesCount = 0;
$securityEventsCount = 0;

$uniqueUsers = [];
$uniqueModules = [];
$uniqueActions = [];

foreach ($logs as $l) {
    $act = strtolower($l['action'] ?? '');
    $mod = strtolower($l['module'] ?? '');
    $uName = $l['user_name'] ?? 'System';

    if (!in_array($uName, $uniqueUsers)) $uniqueUsers[] = $uName;
    if (!empty($l['module']) && !in_array($l['module'], $uniqueModules)) $uniqueModules[] = $l['module'];
    if (!empty($l['action']) && !in_array($l['action'], $uniqueActions)) $uniqueActions[] = $l['action'];

    // Categorization
    if (strpos($act, 'delete') !== false || strpos($act, 'drop') !== false || strpos($act, 'security') !== false || strpos($act, 'permission') !== false || strpos($act, 'role') !== false || strpos($act, 'password') !== false) {
        $securityEventsCount++;
    } elseif ($mod === 'settings' || $mod === 'config' || $mod === 'system' || strpos($act, 'config') !== false || strpos($act, 'setting') !== false) {
        $configChangesCount++;
    } else {
        $userActionsCount++;
    }
}
?>

<div class="page-content audit-container">
  
  <!-- ========================================================= -->
  <!-- 1. COMPACT SECURITY HEADER                                -->
  <!-- ========================================================= -->
  <header class="audit-header">
    <div class="audit-header-main">
      <div class="audit-header-tag">
        <span class="audit-live-dot" aria-hidden="true"></span>
        <span>SECURITY &amp; COMPLIANCE LOG</span>
      </div>
      <h1 class="audit-title">
        <span aria-hidden="true">🛡️</span> <span>Security Audit Logs</span>
      </h1>
      <p class="audit-subtitle">Monitor user activity, system changes and security events.</p>
    </div>

    <div class="audit-header-status">
      <div class="audit-status-badge" title="Audit logging subsystem running normally">
        <span class="audit-pulse-dot" aria-hidden="true"></span>
        <span>Audit Engine Active</span>
      </div>
    </div>
  </header>

  <!-- ========================================================= -->
  <!-- 2. SECURITY SUMMARY TILES                                 -->
  <!-- ========================================================= -->
  <section class="audit-summary-grid" aria-label="Audit Log Metrics">
    <div class="card audit-stat-card border-blue">
      <div class="stat-meta">
        <span class="stat-label">Total Recent Events</span>
        <div class="stat-icon text-primary" aria-hidden="true">📜</div>
      </div>
      <div class="stat-number text-primary"><?= number_format($totalLogsCount) ?></div>
      <span class="stat-caption">Last 50 captured audit records</span>
    </div>

    <div class="card audit-stat-card border-green">
      <div class="stat-meta">
        <span class="stat-label">User Actions</span>
        <div class="stat-icon text-success" aria-hidden="true">👤</div>
      </div>
      <div class="stat-number text-success"><?= number_format($userActionsCount) ?></div>
      <span class="stat-caption">Operational records &amp; entries</span>
    </div>

    <div class="card audit-stat-card border-purple">
      <div class="stat-meta">
        <span class="stat-label">Configuration Changes</span>
        <div class="stat-icon text-purple" aria-hidden="true">⚙️</div>
      </div>
      <div class="stat-number text-purple"><?= number_format($configChangesCount) ?></div>
      <span class="stat-caption">Settings, branches &amp; parameters</span>
    </div>

    <div class="card audit-stat-card border-orange">
      <div class="stat-meta">
        <span class="stat-label">Security Events</span>
        <div class="stat-icon text-danger" aria-hidden="true">🔒</div>
      </div>
      <div class="stat-number <?= $securityEventsCount > 0 ? 'text-danger' : 'text-secondary' ?>">
        <?= number_format($securityEventsCount) ?>
      </div>
      <span class="stat-caption">Deletions, overrides &amp; role changes</span>
    </div>
  </section>

  <!-- ========================================================= -->
  <!-- 3. SEARCH & FILTER TOOLBAR                                -->
  <!-- ========================================================= -->
  <section class="card audit-filter-card" aria-label="Audit Log Filter Toolbar">
    <div class="audit-filters-flex">
      <!-- Search Input -->
      <div class="audit-search-wrapper">
        <span class="search-icon" aria-hidden="true">🔍</span>
        <input type="text" id="auditSearchInput" class="form-control audit-search-input" placeholder="Search user, action, module, IP, changes..." oninput="filterAuditLogs()" autocomplete="off">
      </div>

      <!-- User Dropdown Filter -->
      <div class="audit-filter-item">
        <select id="auditUserFilter" class="form-control audit-select" onchange="filterAuditLogs()">
          <option value="">All Users</option>
          <?php foreach ($uniqueUsers as $u): ?>
            <option value="<?= htmlspecialchars(strtolower($u)) ?>"><?= htmlspecialchars($u) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <!-- Module Filter -->
      <div class="audit-filter-item">
        <select id="auditModuleFilter" class="form-control audit-select" onchange="filterAuditLogs()">
          <option value="">All Modules</option>
          <?php foreach ($uniqueModules as $m): ?>
            <option value="<?= htmlspecialchars(strtolower($m)) ?>"><?= htmlspecialchars($m) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <!-- Action / Severity Filter -->
      <div class="audit-filter-item">
        <select id="auditActionFilter" class="form-control audit-select" onchange="filterAuditLogs()">
          <option value="">All Actions</option>
          <option value="create">Create</option>
          <option value="update">Update</option>
          <option value="delete">Delete / Drop</option>
          <option value="login">Login / Auth</option>
        </select>
      </div>

      <!-- Reset Filters -->
      <button type="button" class="btn btn-outline btn-sm audit-reset-btn" onclick="resetAuditFilters()" title="Reset all search filters">
        <span>✕ Reset</span>
      </button>
    </div>
  </section>

  <!-- ========================================================= -->
  <!-- 4. AUDIT TRAIL DATA TABLE                                 -->
  <!-- ========================================================= -->
  <section class="card audit-table-card" aria-label="Audit Trail Ledger">
    <div class="audit-card-header">
      <div class="header-left">
        <h2 class="card-title">
          <span aria-hidden="true">📜</span> <span>Audit Trail Ledger</span>
        </h2>
        <span class="card-subtitle">Real-time immutable security logs (Latest 50 events)</span>
      </div>
      <span class="audit-counter-badge" id="auditMatchCount">Showing <?= $totalLogsCount ?> logs</span>
    </div>

    <div class="table-responsive">
      <table class="table audit-table" id="auditLedgerTable" style="width:100%;">
        <thead>
          <tr>
            <th scope="col" style="width:60px;">ID</th>
            <th scope="col" style="width:170px;">User &amp; Role</th>
            <th scope="col" style="width:130px;">Action</th>
            <th scope="col" style="width:120px;">Module</th>
            <th scope="col">Change Details</th>
            <th scope="col" style="width:160px;">IP / Device</th>
            <th scope="col" style="width:150px;">Timestamp</th>
            <th scope="col" style="width:80px; text-align:right;">Details</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($logs)): ?>
            <tr id="auditEmptyStateRow">
              <td colspan="8" class="audit-empty-cell">
                <div class="empty-state-wrap">
                  <div class="empty-state-icon" aria-hidden="true">🛡️</div>
                  <h3 class="empty-state-title">No audit events found</h3>
                  <p class="empty-state-text">System activity will appear here automatically as users interact with the app.</p>
                </div>
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($logs as $log): ?>
              <?php
                $role = strtolower($log['role'] ?? 'admin');
                $roleClass = ($role === 'super_admin' || $role === 'admin') ? 'role-admin' : (($role === 'manager') ? 'role-manager' : (($role === 'staff' || $role === 'cashier') ? 'role-staff' : 'role-neutral'));

                $action = strtolower($log['action'] ?? '');
                $isCritical = (strpos($action, 'delete') !== false || strpos($action, 'drop') !== false || strpos($action, 'security') !== false || strpos($action, 'password') !== false || strpos($action, 'role') !== false);
                
                $actionBadgeClass = 'act-neutral';
                if (strpos($action, 'create') !== false || strpos($action, 'add') !== false || strpos($action, 'init') !== false) {
                    $actionBadgeClass = 'act-success';
                } elseif (strpos($action, 'update') !== false || strpos($action, 'edit') !== false || strpos($action, 'modify') !== false) {
                    $actionBadgeClass = 'act-info';
                } elseif (strpos($action, 'delete') !== false || strpos($action, 'remove') !== false || strpos($action, 'drop') !== false) {
                    $actionBadgeClass = 'act-danger';
                } elseif (strpos($action, 'login') !== false || strpos($action, 'auth') !== false) {
                    $actionBadgeClass = 'act-success';
                } elseif (strpos($action, 'logout') !== false) {
                    $actionBadgeClass = 'act-neutral';
                }

                $oldVal = $log['old_value'] ?? '';
                $newVal = $log['new_value'] ?? '';
                $hasChange = ($oldVal !== '' || $newVal !== '');

                $ip = $log['ip_address'] ?: '127.0.0.1';
                $device = $log['device_info'] ?? 'Web Console';
                if (empty($device)) $device = 'Unknown Device';

                $timeRaw = $log['created_at'];
                $timeFmtDate = date('d M Y', strtotime($timeRaw));
                $timeFmtTime = date('h:i:s A', strtotime($timeRaw));

                // Payload for modal
                $logPayload = [
                    'id' => $log['id'],
                    'user' => $log['user_name'] ?: 'System',
                    'role' => strtoupper($log['role'] ?: 'admin'),
                    'action' => $log['action'],
                    'module' => $log['module'],
                    'old' => $oldVal,
                    'new' => $newVal,
                    'ip' => $ip,
                    'device' => $device,
                    'timestamp' => date('d M Y, h:i:s A', strtotime($timeRaw))
                ];
              ?>
              <tr class="audit-row <?= $isCritical ? 'row-critical-alert' : '' ?>"
                  data-user="<?= htmlspecialchars(strtolower($log['user_name'] ?: 'system')) ?>"
                  data-role="<?= htmlspecialchars(strtolower($log['role'] ?: 'admin')) ?>"
                  data-action="<?= htmlspecialchars(strtolower($log['action'])) ?>"
                  data-module="<?= htmlspecialchars(strtolower($log['module'])) ?>"
                  data-ip="<?= htmlspecialchars($ip) ?>"
                  data-old="<?= htmlspecialchars(strtolower($oldVal)) ?>"
                  data-new="<?= htmlspecialchars(strtolower($newVal)) ?>">
                
                <!-- ID -->
                <td data-label="ID">
                  <span class="audit-id-tag">#<?= $log['id'] ?></span>
                </td>

                <!-- User & Role -->
                <td data-label="User & Role">
                  <div class="user-cell-wrap">
                    <div class="user-initial-chip" aria-hidden="true">
                      <?= strtoupper(substr($log['user_name'] ?: 'S', 0, 1)) ?>
                    </div>
                    <div>
                      <strong class="user-name-text"><?= htmlspecialchars($log['user_name'] ?: 'System') ?></strong>
                      <div class="role-badge <?= $roleClass ?>"><?= strtoupper($log['role'] ?: 'admin') ?></div>
                    </div>
                  </div>
                </td>

                <!-- Action -->
                <td data-label="Action">
                  <span class="action-pill <?= $actionBadgeClass ?> <?= $isCritical ? 'pill-critical' : '' ?>">
                    <?= htmlspecialchars($log['action']) ?>
                  </span>
                </td>

                <!-- Module -->
                <td data-label="Module">
                  <span class="module-badge"><?= htmlspecialchars($log['module']) ?></span>
                </td>

                <!-- Change Visualization -->
                <td data-label="Change Details">
                  <div class="change-diff-box">
                    <?php if (!$hasChange): ?>
                      <span class="text-muted text-xs">No modification payload recorded</span>
                    <?php else: ?>
                      <div class="diff-stream">
                        <?php if ($oldVal !== '' && $oldVal !== null): ?>
                          <div class="diff-line diff-old" title="Previous Value">
                            <span class="diff-tag">OLD</span>
                            <span class="diff-val"><?= htmlspecialchars(strlen($oldVal) > 40 ? substr($oldVal, 0, 38) . '…' : $oldVal) ?></span>
                          </div>
                        <?php else: ?>
                          <span class="diff-empty-tag">No previous value</span>
                        <?php endif; ?>

                        <span class="diff-arrow" aria-hidden="true">➔</span>

                        <?php if ($newVal !== '' && $newVal !== null): ?>
                          <div class="diff-line diff-new" title="New Value">
                            <span class="diff-tag">NEW</span>
                            <span class="diff-val"><?= htmlspecialchars(strlen($newVal) > 40 ? substr($newVal, 0, 38) . '…' : $newVal) ?></span>
                          </div>
                        <?php else: ?>
                          <span class="diff-empty-tag">No new value</span>
                        <?php endif; ?>
                      </div>
                    <?php endif; ?>
                  </div>
                </td>

                <!-- IP & Device -->
                <td data-label="IP & Device">
                  <div class="ip-device-cell">
                    <span class="ip-code"><?= htmlspecialchars($ip) ?></span>
                    <span class="device-text" title="<?= htmlspecialchars($device) ?>">
                      <?= htmlspecialchars(strlen($device) > 22 ? substr($device, 0, 20) . '…' : $device) ?>
                    </span>
                  </div>
                </td>

                <!-- Timestamp -->
                <td data-label="Timestamp">
                  <div class="timestamp-cell">
                    <span class="time-date"><?= $timeFmtDate ?></span>
                    <span class="time-clock"><?= $timeFmtTime ?></span>
                  </div>
                </td>

                <!-- Action / Details Button -->
                <td data-label="Details" style="text-align:right;">
                  <button type="button" class="btn btn-outline btn-sm audit-view-btn" onclick='openAuditDetailModal(<?= json_encode($logPayload, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)' title="View full JSON payload & security details">
                    <span>View</span>
                  </button>
                </td>
              </tr>
            <?php endforeach; ?>

            <!-- Client Filter No Match Row -->
            <tr id="auditNoFilterResults" style="display:none;">
              <td colspan="8" class="audit-empty-cell">
                <div class="empty-state-wrap">
                  <div class="empty-state-icon" aria-hidden="true">🔍</div>
                  <h3 class="empty-state-title">No matching audit logs</h3>
                  <p class="empty-state-text">Try adjusting search parameters or clearing filters.</p>
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
<!-- 5. AUDIT EVENT DETAILS MODAL DIALOG                       -->
<!-- ========================================================= -->
<div class="modal" id="auditDetailModal" style="display:none;">
  <div class="modal-overlay" onclick="closeModal('auditDetailModal')" aria-hidden="true"></div>
  <div class="card audit-modal-card" role="dialog" aria-labelledby="auditModalTitle" aria-modal="true">
    
    <div class="audit-modal-header">
      <div class="modal-title-group">
        <span class="modal-shield-icon" aria-hidden="true">🛡️</span>
        <div>
          <h3 id="auditModalTitle" class="modal-heading">Audit Event Details</h3>
          <span class="modal-subheading" id="modalLogIdTag">Log Record #—</span>
        </div>
      </div>
      <button type="button" class="modal-close-icon" onclick="closeModal('auditDetailModal')" aria-label="Close dialog">✕</button>
    </div>

    <div class="audit-modal-body">
      <!-- 2-Col Key Metadata -->
      <div class="modal-meta-grid">
        <div class="meta-field">
          <span class="meta-field-label">ACTOR (USER)</span>
          <div class="meta-field-val" id="modalUserVal">—</div>
        </div>
        <div class="meta-field">
          <span class="meta-field-label">ROLE</span>
          <div class="meta-field-val" id="modalRoleVal">—</div>
        </div>
        <div class="meta-field">
          <span class="meta-field-label">ACTION</span>
          <div class="meta-field-val" id="modalActionVal">—</div>
        </div>
        <div class="meta-field">
          <span class="meta-field-label">MODULE</span>
          <div class="meta-field-val" id="modalModuleVal">—</div>
        </div>
        <div class="meta-field">
          <span class="meta-field-label">IP ADDRESS</span>
          <div class="meta-field-val font-mono" id="modalIpVal">—</div>
        </div>
        <div class="meta-field">
          <span class="meta-field-label">TIMESTAMP</span>
          <div class="meta-field-val" id="modalTimeVal">—</div>
        </div>
        <div class="meta-field col-span-2">
          <span class="meta-field-label">DEVICE &amp; CLIENT INFO</span>
          <div class="meta-field-val font-mono text-xs" id="modalDeviceVal">—</div>
        </div>
      </div>

      <!-- Old & New Value Inspector -->
      <div class="modal-payload-section">
        <div class="payload-box box-old">
          <div class="payload-header text-danger">
            <span>🔴 Previous Value (Before Change)</span>
          </div>
          <pre class="payload-code" id="modalOldValBox">None</pre>
        </div>

        <div class="payload-box box-new">
          <div class="payload-header text-success">
            <span>🟢 New Value (After Change)</span>
          </div>
          <pre class="payload-code" id="modalNewValBox">None</pre>
        </div>
      </div>
    </div>

    <div class="audit-modal-footer">
      <button type="button" class="btn btn-secondary" onclick="closeModal('auditDetailModal')">Close</button>
    </div>
  </div>
</div>

<!-- ========================================================= -->
<!-- STYLESHEET FOR SECURITY AUDIT LOGS VIEW                   -->
<!-- ========================================================= -->
<style>
.audit-container {
  display: flex;
  flex-direction: column;
  gap: 1.25rem;
  padding-bottom: 3rem;
}

/* 1. Header */
.audit-header {
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

.audit-header-main {
  flex: 1 1 300px;
}

.audit-header-tag {
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

.audit-live-dot {
  width: 7px;
  height: 7px;
  border-radius: 50%;
  background: var(--primary);
}

.audit-title {
  font-size: 1.35rem;
  font-weight: 800;
  color: var(--text-primary);
  margin: 0;
  display: flex;
  align-items: center;
  gap: 0.45rem;
  letter-spacing: -0.02em;
}

.audit-subtitle {
  font-size: 0.8rem;
  color: var(--text-muted);
  margin: 0.2rem 0 0 0;
}

.audit-status-badge {
  display: inline-flex;
  align-items: center;
  gap: 0.45rem;
  background: #ECFDF5;
  color: #065F46;
  border: 1px solid #A7F3D0;
  padding: 0.35rem 0.8rem;
  border-radius: 99px;
  font-size: 0.75rem;
  font-weight: 800;
  letter-spacing: 0.02em;
}

.audit-pulse-dot {
  width: 7px;
  height: 7px;
  background: #10B981;
  border-radius: 50%;
  box-shadow: 0 0 0 2px rgba(16, 185, 129, 0.25);
  animation: pulseDot 2s infinite ease-in-out;
}

/* 2. Summary Grid */
.audit-summary-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 1rem;
}

.audit-stat-card {
  padding: 1.1rem 1.25rem;
  border-radius: var(--radius-lg);
  background: var(--bg-surface);
  border: 1px solid var(--border-color);
  box-shadow: var(--shadow-subtle);
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  min-height: 105px;
  transition: transform var(--transition-fast), box-shadow var(--transition-fast);
}

.audit-stat-card:hover {
  transform: translateY(-2px);
  box-shadow: var(--shadow-card);
}

.border-blue   { border-left: 4px solid var(--primary); }
.border-green  { border-left: 4px solid var(--success); }
.border-purple { border-left: 4px solid var(--purple); }
.border-orange { border-left: 4px solid var(--warning); }

.stat-meta {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 0.35rem;
}

.stat-label {
  font-size: 0.72rem;
  font-weight: 800;
  color: var(--text-muted);
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

.stat-icon {
  font-size: 1.1rem;
  line-height: 1;
}

.stat-number {
  font-size: 1.75rem;
  font-weight: 800;
  font-family: var(--font-heading);
  line-height: 1;
  letter-spacing: -0.02em;
}

.stat-caption {
  font-size: 0.72rem;
  color: var(--text-muted);
  font-weight: 600;
  margin-top: 0.25rem;
}

/* 3. Filter Toolbar */
.audit-filter-card {
  padding: 0.85rem 1.15rem;
  border-radius: var(--radius-lg);
  background: var(--bg-surface);
  border: 1px solid var(--border-color);
  box-shadow: var(--shadow-subtle);
}

.audit-filters-flex {
  display: flex;
  align-items: center;
  gap: 0.65rem;
  flex-wrap: wrap;
}

.audit-search-wrapper {
  position: relative;
  flex: 1 1 240px;
}

.audit-search-wrapper .search-icon {
  position: absolute;
  left: 0.75rem;
  top: 50%;
  transform: translateY(-50%);
  font-size: 0.85rem;
  color: var(--text-muted);
  pointer-events: none;
}

.audit-search-input {
  width: 100%;
  padding: 0.45rem 0.85rem 0.45rem 2.2rem;
  background: var(--bg-surface-secondary);
  border: 1px solid var(--border-color);
  border-radius: var(--radius-md);
  font-size: 0.82rem;
  font-family: inherit;
  color: var(--text-primary);
  outline: none;
  transition: all var(--transition-fast);
}

.audit-search-input:focus {
  background: #FFFFFF;
  border-color: var(--primary);
  box-shadow: 0 0 0 3px var(--primary-light);
}

.audit-filter-item {
  flex: 0 1 150px;
}

.audit-select {
  padding: 0.45rem 0.75rem;
  font-size: 0.82rem;
  border-radius: var(--radius-md);
  border: 1px solid var(--border-color);
  background: var(--bg-surface-secondary);
  color: var(--text-primary);
  cursor: pointer;
}

.audit-reset-btn {
  font-weight: 700;
  font-size: 0.78rem;
  padding: 0.42rem 0.75rem;
  border-radius: var(--radius-md);
}

/* 4. Table Design */
.audit-table-card {
  padding: 0;
  overflow: hidden;
  border-radius: var(--radius-lg);
  border: 1px solid var(--border-color);
  background: var(--bg-surface);
  box-shadow: var(--shadow-card);
}

.audit-card-header {
  padding: 1.1rem 1.35rem;
  border-bottom: 1px solid var(--border-color);
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 0.5rem;
}

.audit-card-header .card-subtitle {
  font-size: 0.76rem;
  color: var(--text-muted);
  margin-top: 0.15rem;
  display: block;
}

.audit-counter-badge {
  font-size: 0.72rem;
  font-weight: 800;
  color: var(--text-muted);
  background: var(--bg-surface-secondary);
  border: 1px solid var(--border-color);
  padding: 0.2rem 0.6rem;
  border-radius: 99px;
}

.audit-table thead th {
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

.audit-table tbody td {
  padding: 0.75rem 1.15rem;
  vertical-align: middle;
  border-bottom: 1px solid var(--border-light);
  font-size: 0.85rem;
  background: var(--bg-surface);
}

.audit-table tbody tr:last-child td {
  border-bottom: none;
}

.audit-table tbody tr:hover td {
  background: #F8FAFC;
}

.row-critical-alert td {
  background: #FFFDFD;
}
.row-critical-alert td:first-child {
  border-left: 3px solid var(--danger);
}

/* Table Cells Formatting */
.audit-id-tag {
  font-family: var(--font-mono);
  font-size: 0.75rem;
  font-weight: 700;
  color: var(--text-muted);
}

.user-cell-wrap {
  display: flex;
  align-items: center;
  gap: 0.6rem;
}

.user-initial-chip {
  width: 30px;
  height: 30px;
  border-radius: 50%;
  background: var(--primary-light);
  color: var(--primary);
  border: 1px solid var(--primary-border);
  font-size: 0.76rem;
  font-weight: 800;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.user-name-text {
  font-size: 0.85rem;
  color: var(--text-primary);
  display: block;
  line-height: 1.2;
}

.role-badge {
  display: inline-block;
  font-size: 0.65rem;
  font-weight: 800;
  letter-spacing: 0.04em;
  padding: 0.1rem 0.4rem;
  border-radius: 4px;
  margin-top: 0.15rem;
}

.role-admin   { background: #F5F3FF; color: #6D28D9; border: 1px solid #DDD6FE; }
.role-manager { background: #ECFDF5; color: #065F46; border: 1px solid #A7F3D0; }
.role-staff   { background: #EFF6FF; color: #1E40AF; border: 1px solid #BFDBFE; }
.role-neutral { background: #F1F5F9; color: #475569; border: 1px solid #CBD5E1; }

.action-pill {
  display: inline-block;
  padding: 0.22rem 0.55rem;
  border-radius: 6px;
  font-size: 0.72rem;
  font-weight: 800;
  letter-spacing: 0.02em;
  text-transform: uppercase;
  white-space: nowrap;
}

.act-success  { background: #ECFDF5; color: #065F46; border: 1px solid #A7F3D0; }
.act-info     { background: #EFF6FF; color: #1E40AF; border: 1px solid #BFDBFE; }
.act-danger   { background: #FEF2F2; color: #991B1B; border: 1px solid #FECACA; }
.act-neutral  { background: #F1F5F9; color: #475569; border: 1px solid #CBD5E1; }

.pill-critical {
  box-shadow: 0 0 0 1px #EF4444;
}

.module-badge {
  display: inline-block;
  padding: 0.2rem 0.5rem;
  border-radius: var(--radius-sm);
  background: var(--bg-surface-secondary);
  border: 1px solid var(--border-color);
  color: var(--text-secondary);
  font-size: 0.72rem;
  font-weight: 700;
  text-transform: uppercase;
}

/* Change Diff Box */
.change-diff-box {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.diff-stream {
  display: flex;
  align-items: center;
  gap: 0.45rem;
  flex-wrap: wrap;
}

.diff-line {
  display: inline-flex;
  align-items: center;
  gap: 0.3rem;
  padding: 0.15rem 0.45rem;
  border-radius: 4px;
  font-family: var(--font-mono);
  font-size: 0.72rem;
}

.diff-old {
  background: #FEF2F2;
  color: #991B1B;
  border: 1px solid #FECACA;
}

.diff-new {
  background: #ECFDF5;
  color: #065F46;
  border: 1px solid #A7F3D0;
}

.diff-tag {
  font-size: 0.6rem;
  font-weight: 800;
  opacity: 0.8;
}

.diff-arrow {
  color: var(--text-muted);
  font-size: 0.75rem;
}

.diff-empty-tag {
  font-size: 0.72rem;
  color: var(--text-muted);
  font-style: italic;
}

.ip-device-cell {
  display: flex;
  flex-direction: column;
  gap: 0.1rem;
}

.ip-code {
  font-family: var(--font-mono);
  font-size: 0.78rem;
  font-weight: 700;
  color: var(--text-secondary);
}

.device-text {
  font-size: 0.7rem;
  color: var(--text-muted);
}

.timestamp-cell {
  display: flex;
  flex-direction: column;
  gap: 0.1rem;
}

.time-date {
  font-size: 0.8rem;
  font-weight: 700;
  color: var(--text-primary);
}

.time-clock {
  font-size: 0.72rem;
  color: var(--text-muted);
  font-family: var(--font-mono);
}

.audit-view-btn {
  font-weight: 700;
  font-size: 0.75rem;
  padding: 0.25rem 0.65rem;
  min-height: 28px;
  border-radius: var(--radius-sm);
}

/* Empty State */
.audit-empty-cell {
  text-align: center;
  padding: 3.5rem 1.5rem !important;
}

.empty-state-wrap {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
}

.empty-state-icon {
  font-size: 2.2rem;
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

/* 5. Modal Details Card */
.audit-modal-card {
  position: relative;
  z-index: 10;
  background: #FFFFFF;
  width: 92%;
  max-width: 620px;
  border-radius: var(--radius-xl);
  padding: 1.5rem;
  box-shadow: var(--shadow-modal);
  border: 1px solid var(--border-color);
  max-height: 90vh;
  overflow-y: auto;
}

.audit-modal-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 1.25rem;
  padding-bottom: 0.75rem;
  border-bottom: 1px solid var(--border-color);
}

.modal-title-group {
  display: flex;
  align-items: center;
  gap: 0.65rem;
}

.modal-shield-icon {
  font-size: 1.4rem;
}

.modal-heading {
  font-size: 1.15rem;
  font-weight: 800;
  color: var(--text-primary);
  margin: 0;
}

.modal-subheading {
  font-size: 0.75rem;
  color: var(--text-muted);
  font-family: var(--font-mono);
  display: block;
}

.modal-close-icon {
  background: none;
  border: none;
  font-size: 1.2rem;
  color: var(--text-muted);
  cursor: pointer;
  line-height: 1;
  padding: 0.25rem;
  border-radius: 4px;
}

.modal-close-icon:hover {
  color: var(--text-primary);
}

.modal-meta-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 0.85rem;
  margin-bottom: 1.25rem;
  background: var(--bg-main);
  padding: 1rem;
  border-radius: var(--radius-lg);
  border: 1px solid var(--border-color);
}

.col-span-2 {
  grid-column: span 2;
}

.meta-field-label {
  font-size: 0.68rem;
  font-weight: 800;
  color: var(--text-muted);
  text-transform: uppercase;
  letter-spacing: 0.05em;
  display: block;
  margin-bottom: 0.2rem;
}

.meta-field-val {
  font-size: 0.85rem;
  font-weight: 700;
  color: var(--text-primary);
  word-break: break-all;
}

.modal-payload-section {
  display: flex;
  flex-direction: column;
  gap: 0.85rem;
}

.payload-box {
  border-radius: var(--radius-md);
  border: 1px solid var(--border-color);
  overflow: hidden;
}

.payload-header {
  padding: 0.45rem 0.85rem;
  font-size: 0.72rem;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  background: var(--bg-surface-secondary);
  border-bottom: 1px solid var(--border-color);
}

.payload-code {
  padding: 0.75rem 0.85rem;
  margin: 0;
  font-family: var(--font-mono);
  font-size: 0.78rem;
  line-height: 1.4;
  color: var(--text-secondary);
  background: #FFFFFF;
  max-height: 160px;
  overflow-y: auto;
  white-space: pre-wrap;
  word-break: break-word;
}

.audit-modal-footer {
  display: flex;
  justify-content: flex-end;
  margin-top: 1.25rem;
  padding-top: 0.75rem;
  border-top: 1px solid var(--border-color);
}

/* ========================================================= -->
<!-- RESPONSIVE BREAKPOINTS                                    -->
<!-- ========================================================= --> */
@media (max-width: 1024px) {
  .audit-summary-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}

@media (max-width: 768px) {
  .audit-header {
    flex-direction: column;
    align-items: flex-start;
    padding: 1rem;
  }

  .audit-summary-grid {
    grid-template-columns: 1fr;
    gap: 0.75rem;
  }

  .audit-filters-flex {
    flex-direction: column;
    align-items: stretch;
  }

  .audit-filter-item {
    flex: 1 1 100%;
  }

  /* Responsive Stacked Cards on Mobile */
  .audit-table thead {
    display: none;
  }

  .audit-table, 
  .audit-table tbody, 
  .audit-table tr, 
  .audit-table td {
    display: block;
    width: 100%;
  }

  .audit-table tbody tr {
    margin: 0.85rem;
    background: var(--bg-surface);
    border: 1px solid var(--border-color);
    border-radius: var(--radius-md);
    padding: 0.75rem 0.9rem;
    box-shadow: var(--shadow-subtle);
  }

  .audit-table tbody td {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.45rem 0;
    border-bottom: 1px solid var(--border-light);
    font-size: 0.82rem;
  }

  .audit-table tbody td:last-child {
    border-bottom: none;
    padding-top: 0.65rem;
  }

  .audit-table tbody td::before {
    content: attr(data-label);
    font-weight: 700;
    font-size: 0.72rem;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--text-muted);
    flex-shrink: 0;
    margin-right: 0.75rem;
  }

  .audit-table tbody td[data-label="Details"]::before {
    display: none;
  }

  .audit-table tbody td .audit-view-btn {
    width: 100%;
    text-align: center;
  }

  .audit-empty-cell {
    display: block !important;
  }
  .audit-empty-cell::before {
    display: none !important;
  }

  .modal-meta-grid {
    grid-template-columns: 1fr;
  }
  .col-span-2 {
    grid-column: span 1;
  }
}
</style>

<!-- ========================================================= -->
<!-- JAVASCRIPT LOGIC & INSTANT FILTERING                      -->
<!-- ========================================================= -->
<script>
function filterAuditLogs() {
  const query = (document.getElementById('auditSearchInput')?.value || '').toLowerCase().trim();
  const selectedUser = (document.getElementById('auditUserFilter')?.value || '').toLowerCase().trim();
  const selectedModule = (document.getElementById('auditModuleFilter')?.value || '').toLowerCase().trim();
  const selectedAction = (document.getElementById('auditActionFilter')?.value || '').toLowerCase().trim();

  const rows = document.querySelectorAll('.audit-row');
  let visibleCount = 0;

  rows.forEach(row => {
    const u = row.getAttribute('data-user') || '';
    const r = row.getAttribute('data-role') || '';
    const act = row.getAttribute('data-action') || '';
    const mod = row.getAttribute('data-module') || '';
    const ip = row.getAttribute('data-ip') || '';
    const oldV = row.getAttribute('data-old') || '';
    const newV = row.getAttribute('data-new') || '';

    // Search query matches
    const textCorpus = `${u} ${r} ${act} ${mod} ${ip} ${oldV} ${newV}`;
    const matchesQuery = !query || textCorpus.includes(query);

    // Filter selects matches
    const matchesUser = !selectedUser || u === selectedUser;
    const matchesModule = !selectedModule || mod === selectedModule;
    
    let matchesAction = true;
    if (selectedAction) {
      matchesAction = act.includes(selectedAction);
    }

    if (matchesQuery && matchesUser && matchesModule && matchesAction) {
      row.style.display = '';
      visibleCount++;
    } else {
      row.style.display = 'none';
    }
  });

  const countBadge = document.getElementById('auditMatchCount');
  if (countBadge) {
    countBadge.textContent = `Showing ${visibleCount} logs`;
  }

  const noResultsEl = document.getElementById('auditNoFilterResults');
  if (noResultsEl) {
    noResultsEl.style.display = (visibleCount === 0 && rows.length > 0) ? '' : 'none';
  }
}

function resetAuditFilters() {
  const searchInput = document.getElementById('auditSearchInput');
  const userFilter = document.getElementById('auditUserFilter');
  const moduleFilter = document.getElementById('auditModuleFilter');
  const actionFilter = document.getElementById('auditActionFilter');

  if (searchInput) searchInput.value = '';
  if (userFilter) userFilter.value = '';
  if (moduleFilter) moduleFilter.value = '';
  if (actionFilter) actionFilter.value = '';

  filterAuditLogs();
}

function openAuditDetailModal(data) {
  if (!data) return;

  const idTag = document.getElementById('modalLogIdTag');
  const userVal = document.getElementById('modalUserVal');
  const roleVal = document.getElementById('modalRoleVal');
  const actionVal = document.getElementById('modalActionVal');
  const moduleVal = document.getElementById('modalModuleVal');
  const ipVal = document.getElementById('modalIpVal');
  const timeVal = document.getElementById('modalTimeVal');
  const deviceVal = document.getElementById('modalDeviceVal');
  const oldBox = document.getElementById('modalOldValBox');
  const newBox = document.getElementById('modalNewValBox');

  if (idTag) idTag.textContent = `Log Record #${data.id}`;
  if (userVal) userVal.textContent = data.user || 'System';
  if (roleVal) roleVal.textContent = data.role || 'ADMIN';
  if (actionVal) actionVal.textContent = data.action || '—';
  if (moduleVal) moduleVal.textContent = data.module || '—';
  if (ipVal) ipVal.textContent = data.ip || '127.0.0.1';
  if (timeVal) timeVal.textContent = data.timestamp || '—';
  if (deviceVal) deviceVal.textContent = data.device || 'Unknown Device';

  if (oldBox) oldBox.textContent = (data.old !== null && data.old !== '') ? data.old : 'No previous value recorded.';
  if (newBox) newBox.textContent = (data.new !== null && data.new !== '') ? data.new : 'No new value recorded.';

  openModal('auditDetailModal');
}
</script>
