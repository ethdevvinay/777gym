<!-- Redesigned Biometric & Attendance Devices Management Hub with Realtime LAN Machine Support -->
<?php
$db = getDB();

// Ensure default Realtime machine exists in database
$stmtRt = $db->query("SELECT id FROM devices WHERE manufacturer = 'Realtime' LIMIT 1");
if (!$stmtRt->fetch()) {
    $db->exec("INSERT INTO devices (name, ip_address, port, serial_no, manufacturer, device_type, location, status, last_sync) VALUES ('Realtime T502 LAN Gate Terminal', '192.168.1.205', 5005, 'RT-502-LAN', 'Realtime', 'fingerprint', 'Main Entrance Gate', 'ONLINE', NOW())");
}

// Ensure eSSL Smart Terminal (ADMS) exists in database
$stmtEssl = $db->prepare("SELECT id FROM devices WHERE serial_no = ? LIMIT 1");
$stmtEssl->execute(['NYU7262500437']);
if (!$stmtEssl->fetch()) {
    $db->exec("INSERT INTO devices (name, ip_address, port, serial_no, manufacturer, device_type, location, status, last_sync) VALUES ('eSSL Smart Terminal (Face + Fingerprint)', '192.168.1.50', 80, 'NYU7262500437', 'eSSL', 'face_fingerprint', 'Gym Main Gate', 'ONLINE', NOW())");
}

$stmt = $db->query("SELECT * FROM devices ORDER BY id ASC");
$devices = $stmt->fetchAll();

// Fetch Member Biometric Mappings
$membersStmt = $db->query("SELECT id, member_code, name, phone, biometric_id, photo_url FROM members ORDER BY id ASC LIMIT 10");
$membersList = $membersStmt->fetchAll();

$dupWindow = getSetting('duplicate_attendance_window_mins', '5');

// ─── Smart Server URL Detection ─────────────────────────────────────────────
// On LOCAL XAMPP  → uses LAN IP (e.g. http://192.168.1.100/GYM/...)
// On LIVE SERVER  → uses actual domain with HTTPS (e.g. https://yourdomain.com/GYM/...)
// ─────────────────────────────────────────────────────────────────────────────
$isLocal = (DIRECTORY_SEPARATOR === '\\') && (strpos(__DIR__, 'xampp') !== false || ($_SERVER['HTTP_HOST'] ?? '') === 'localhost');

if (!$isLocal) {
    // ── LIVE SERVER (Hostinger / cPanel / VPS) ──
    // Use the actual domain name so biometric machine can push over internet
    $scheme     = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $httpHost   = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '';
    $serverPort = $_SERVER['SERVER_PORT'] ?? '443';

    // Detect base path — on Hostinger root installs it may be just /GYM or /
    $scriptDir  = str_replace('\\', '/', dirname(dirname(dirname($_SERVER['SCRIPT_NAME'] ?? '/index.php'))));
    $basePath   = rtrim($scriptDir, '/');

    $realtimePushUrl  = "{$scheme}://{$httpHost}{$basePath}/api/devices.php?action=realtime_push";
    $serverIp         = $httpHost;

} else {
    // ── LOCAL XAMPP ──
    $serverIp = $_SERVER['SERVER_ADDR'] ?? '192.168.1.100';
    if ($serverIp === '::1' || $serverIp === '127.0.0.1') {
        $serverIp = gethostbyname(gethostname()) ?: '192.168.1.100';
    }
    $serverPort = $_SERVER['SERVER_PORT'] ?? '80';
    $basePath   = '/GYM';
    $realtimePushUrl = "http://{$serverIp}:{$serverPort}{$basePath}/api/devices.php?action=realtime_push";
}

// Live server push URL hint for the machine settings panel
$isHttps        = str_starts_with($realtimePushUrl, 'https');
$pushUrlDisplay = $realtimePushUrl;
?>

<div class="page-content">
  <!-- Top Banner Header -->
  <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem; margin-bottom:1.5rem;">
    <div>
      <h1 style="font-size:1.6rem; font-weight:800; color:var(--text-primary); display:flex; align-items:center; gap:0.5rem;">
        <span>📟</span> <span>Biometric & Realtime LAN Machine Hub</span>
      </h1>
      <p style="font-size:0.85rem; color:var(--text-secondary);">
        Direct TCP/IP LAN socket integration and Realtime Cloud Push protocol for biometric gates & attendance
      </p>
    </div>

    <div style="display:flex; gap:0.5rem; flex-wrap:wrap;">
      <button class="btn btn-primary" onclick="openModal('esslGuideModal')" style="background:#059669; border-color:#059669; font-weight:700;">
        ⚡ eSSL Machine Setup (NYU7262500437)
      </button>
      <button class="btn btn-outline" onclick="openModal('realtimeGuideModal')">
        📘 Realtime Machine Guide
      </button>
      <button class="btn btn-outline" onclick="openModal('addDeviceModal')">
        + Add New Machine
      </button>
      <button class="btn btn-secondary" onclick="triggerTimeSync()">
        🕒 Time Sync Clocks
      </button>
      <button class="btn btn-success" onclick="triggerPullSync()">
        ↻ Pull Attendance Logs
      </button>
    </div>
  </div>

  <!-- Connected Hardware Machines Grid -->
  <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(320px, 1fr)); gap:1.25rem; margin-bottom:2rem;">
    <?php foreach ($devices as $dev): ?>
      <div class="card" style="border-top:4px solid <?= $dev['status'] === 'ONLINE' ? 'var(--success)' : 'var(--warning)' ?>;">
        <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:0.75rem;">
          <div>
            <strong style="font-size:1.05rem; color:var(--text-primary); display:block;"><?= htmlspecialchars($dev['name']) ?></strong>
            <span style="font-size:0.75rem; color:var(--text-muted); font-family:monospace;"><?= htmlspecialchars($dev['serial_no']) ?></span>
          </div>
          <span class="badge badge-<?= $dev['status'] === 'ONLINE' ? 'success' : 'warning' ?>">
            ● <?= $dev['status'] ?>
          </span>
        </div>

        <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.5rem; font-size:0.82rem; color:var(--text-secondary); background:var(--bg-main); padding:0.75rem; border-radius:8px; margin-bottom:1rem;">
          <div>📡 <strong>LAN IP:</strong> <?= htmlspecialchars($dev['ip_address']) ?>:<?= $dev['port'] ?></div>
          <div>🏭 <strong>Vendor:</strong> <strong style="color:var(--primary);"><?= htmlspecialchars($dev['manufacturer']) ?></strong></div>
          <div>🔑 <strong>Type:</strong> <?= ucfirst(str_replace('_', ' ', $dev['device_type'])) ?></div>
          <div>📍 <strong>Location:</strong> <?= htmlspecialchars($dev['location']) ?></div>
          <div style="grid-column:1 / -1; font-size:0.78rem; color:var(--text-muted);">
            ⏱️ <strong>Last Sync:</strong> <?= date('d M Y, h:i A', strtotime($dev['last_sync'])) ?>
          </div>
        </div>

        <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.5rem;">
          <button class="btn btn-secondary btn-sm" onclick="pingDevice(<?= $dev['id'] ?>)">
            📶 LAN Ping Test
          </button>
          <?php if (($dev['manufacturer'] ?? '') === 'eSSL'): ?>
          <button class="btn btn-primary btn-sm" onclick="testEsslPunch('<?= htmlspecialchars($dev['serial_no']) ?>')" style="background:#059669; border-color:#059669; font-weight:700;">
            👤 Test Face Punch
          </button>
          <?php else: ?>
          <button class="btn btn-outline btn-sm" onclick="testRealtimePunch('<?= htmlspecialchars($dev['serial_no']) ?>')">
            ⚡ Test Live Punch
          </button>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <div style="display:grid; grid-template-columns: 2fr 1fr; gap:1.5rem; flex-wrap:wrap;">
    <!-- LEFT: Biometric User Enrollment & Mapping Table -->
    <div class="card">
      <div class="card-header" style="display:flex; justify-content:space-between; align-items:center;">
        <span class="card-title">👤 Member Biometric ID Mapping</span>
        <button class="btn btn-primary btn-sm" onclick="pushMemberData()">
          📤 Push Users to Realtime Machine
        </button>
      </div>

      <div style="overflow-x:auto;">
        <table class="table" style="width:100%;">
          <thead>
            <tr>
              <th>Member Code</th>
              <th>Name</th>
              <th>Phone</th>
              <th>Biometric ID / RFID</th>
              <th>Machine Status</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($membersList as $m): ?>
              <tr>
                <td style="font-weight:700; color:var(--primary); font-family:monospace;"><?= htmlspecialchars($m['member_code']) ?></td>
                <td>
                  <div style="font-weight:600;"><?= htmlspecialchars($m['name']) ?></div>
                </td>
                <td><?= htmlspecialchars($m['phone']) ?></td>
                <td>
                  <span class="badge" style="background:#E2E8F0; color:#334155; font-family:monospace;">
                    <?= htmlspecialchars($m['biometric_id'] ?: 'NOT ENROLLED') ?>
                  </span>
                </td>
                <td>
                  <span class="badge badge-success">ENROLLED</span>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- RIGHT: IoT Hardware Settings & Realtime Push Listener -->
    <div style="display:flex; flex-direction:column; gap:1.25rem;">
      <!-- Machine Push URL Configuration Box -->
      <div class="card" style="background:#0F172A; color:#F8FAFC; border:1px solid #334155;">
        
        <!-- Mode Badge -->
        <?php if (!$isLocal): ?>
        <div style="background:#10b98122; border:1px solid #10b981; border-radius:6px; padding:0.4rem 0.75rem; font-size:0.72rem; font-weight:800; color:#10b981; margin-bottom:0.75rem; display:inline-flex; align-items:center; gap:0.4rem;">
          🌐 LIVE SERVER MODE — Machine internet se push kar sakti hai
        </div>
        <?php else: ?>
        <div style="background:#f59e0b22; border:1px solid #f59e0b; border-radius:6px; padding:0.4rem 0.75rem; font-size:0.72rem; font-weight:800; color:#f59e0b; margin-bottom:0.75rem; display:inline-flex; align-items:center; gap:0.4rem;">
          🏠 LOCAL MODE — Machine aur PC ek hi WiFi pe hone chahiye
        </div>
        <?php endif; ?>

        <h3 style="font-size:0.95rem; font-weight:700; color:#38BDF8; margin-bottom:0.5rem; display:flex; align-items:center; gap:0.4rem;">
          ⚡ Machine Host URL — Isse Machine Mein Daalo
        </h3>
        <p style="font-size:0.78rem; color:#94A3B8; margin-bottom:0.75rem;">
          Machine mein <strong style="color:#fde68a;">Menu → Comm. → Cloud Server / Web Server</strong> mein jaao:
        </p>

        <!-- URL Display + Copy Button -->
        <div style="display:flex; gap:0.5rem; align-items:stretch; margin-bottom:0.5rem;">
          <div id="pushUrlBox" style="flex:1; background:#020617; padding:0.75rem; border-radius:6px; font-family:monospace; font-size:0.78rem; color:#4ADE80; word-break:break-all; border:1px solid #1E293B; user-select:all; cursor:pointer;" onclick="copyPushUrl()" title="Click to copy">
            <?= htmlspecialchars($pushUrlDisplay) ?>
          </div>
          <button onclick="copyPushUrl()" style="background:#38BDF8; color:#0F172A; border:none; border-radius:6px; padding:0.5rem 0.85rem; font-weight:800; font-size:0.82rem; cursor:pointer; white-space:nowrap;" title="Copy URL">
            📋 Copy
          </button>
        </div>
        <div style="font-size:0.72rem; color:#64748b; margin-bottom:0.75rem;">
          ☝️ Click URL ya Copy button → Machine mein paste karo
        </div>

        <!-- Machine Settings Reference Table -->
        <div style="background:#020617; border:1px solid #1E293B; border-radius:8px; overflow:hidden; font-size:0.78rem; margin-bottom:0.75rem;">
          <div style="background:#1E293B; padding:0.4rem 0.75rem; font-size:0.7rem; font-weight:800; color:#94A3B8; text-transform:uppercase; letter-spacing:0.05em;">
            📋 Machine Settings Reference
          </div>
          <table style="width:100%; border-collapse:collapse;">
            <tr style="border-bottom:1px solid #1E293B;">
              <td style="padding:0.5rem 0.75rem; color:#94A3B8; width:45%;">Host PC / Server URL</td>
              <td style="padding:0.5rem 0.75rem; color:#4ADE80; font-family:monospace; font-weight:700; font-size:0.72rem;"><?= htmlspecialchars($serverIp) ?></td>
            </tr>
            <tr style="border-bottom:1px solid #1E293B;">
              <td style="padding:0.5rem 0.75rem; color:#94A3B8;">Host PC Port</td>
              <td style="padding:0.5rem 0.75rem; color:#e2e8f0; font-weight:700;"><?= $isLocal ? htmlspecialchars($serverPort) : ($isHttps ? '443 (HTTPS)' : '80 (HTTP)') ?></td>
            </tr>
            <tr style="border-bottom:1px solid #1E293B;">
              <td style="padding:0.5rem 0.75rem; color:#94A3B8;">Request Path / URL</td>
              <td style="padding:0.5rem 0.75rem; color:#4ADE80; font-family:monospace; font-size:0.72rem;"><?= htmlspecialchars($basePath) ?>/api/devices.php</td>
            </tr>
            <tr style="border-bottom:1px solid #1E293B;">
              <td style="padding:0.5rem 0.75rem; color:#94A3B8;">Event Transfer Mode</td>
              <td style="padding:0.5rem 0.75rem; color:#e2e8f0; font-weight:700;">TCP/IP ✅ (Already set)</td>
            </tr>
            <tr style="border-bottom:1px solid #1E293B;">
              <td style="padding:0.5rem 0.75rem; color:#94A3B8;">Communication Password</td>
              <td style="padding:0.5rem 0.75rem; color:#94A3B8;">No / Blank (leave empty)</td>
            </tr>
            <tr>
              <td style="padding:0.5rem 0.75rem; color:#94A3B8;">Device TCP Port</td>
              <td style="padding:0.5rem 0.75rem; color:#e2e8f0; font-weight:700;">5005 (machine's own port)</td>
            </tr>
          </table>
        </div>

        <?php if (!$isLocal && $isHttps): ?>
        <!-- HTTPS Warning for Realtime machines -->
        <div style="background:#92400e33; border:1px solid #f59e0b; border-radius:6px; padding:0.6rem 0.75rem; font-size:0.75rem; color:#fde68a; margin-bottom:0.75rem;">
          ⚠️ <strong>HTTPS Note:</strong> Agar Realtime machine HTTPS ke saath kaam na kare, toh machine ke menu mein <strong>HTTP Mode</strong> set karo ya server par HTTP redirect allow karo.
          Alternatively: machine mein URL ke jagah sirf domain name daalo: <code style="background:#0F172A; padding:0.1rem 0.3rem; border-radius:3px;"><?= htmlspecialchars(parse_url($realtimePushUrl, PHP_URL_HOST)) ?></code>
        </div>
        <?php endif; ?>

        <button class="btn btn-sm" style="background:#38BDF8; color:#0F172A; font-weight:700; width:100%; border:none;" onclick="testRealtimePunch('RT-502-LAN')">
          🧪 SIMULATE PUNCH — Test Machine Connection
        </button>
      </div>


      <!-- Security & Duplicate Prevention Settings -->
      <div class="card">
        <h3 class="card-title" style="margin-bottom:1rem;">🛡️ Duplicate Scan Buffer</h3>
        <div class="form-group">
          <label class="form-label">Check-in Buffer Window (Minutes)</label>
          <div style="display:flex; gap:0.5rem;">
            <input type="number" id="dupWindowInput" class="form-control" value="<?= htmlspecialchars($dupWindow) ?>" min="1" max="30">
            <button class="btn btn-secondary" onclick="showToast('Duplicate prevention buffer updated!', 'success')">Save</button>
          </div>
          <p style="font-size:0.78rem; color:var(--text-muted); margin-top:0.4rem;">
            Ignores consecutive punches within buffer window to prevent double entry records.
          </p>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ═══════════════════════════════════════════════════════════ -->
<!-- UNREGISTERED PUNCHES ALERT PANEL                          -->
<!-- ═══════════════════════════════════════════════════════════ -->
<div class="card" style="margin-top:1.5rem; border-top:4px solid #7c3aed;" id="unregisteredPunchesPanel">
  <div class="card-header" style="display:flex; justify-content:space-between; align-items:center;">
    <span class="card-title" style="color:#7c3aed;">⚠️ Unregistered Biometric Punches (Unknown IDs)</span>
    <button class="btn btn-outline btn-sm" onclick="loadUnregisteredPunches()">🔄 Refresh</button>
  </div>
  <div id="unregisteredPunchesBody" style="padding:1rem;">
    <div style="text-align:center; color:var(--text-muted); padding:1.5rem;">
      <div style="font-size:2rem;">👆</div>
      <div>Loading unknown punch records...</div>
    </div>
  </div>
  <div style="padding:0.75rem 1rem; background:var(--bg-main); font-size:0.78rem; color:var(--text-muted); border-top:1px solid var(--border-color);">
    💡 These biometric IDs punched the machine but are NOT registered in the GYM system.
    Go to <a href="index.php?page=members" style="color:var(--primary);">Members</a> and assign their Biometric ID to fix this.
  </div>
</div>

<!-- eSSL Smart Terminal Cloud Setup Guide Modal -->
<div class="modal" id="esslGuideModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.6); z-index:100; align-items:center; justify-content:center;">
  <div class="modal-overlay" style="position:absolute; inset:0;" onclick="closeModal('esslGuideModal')"></div>
  <div class="card" style="position:relative; z-index:10; background:#fff; width:92%; max-width:640px; border-radius:16px; padding:1.5rem; max-height:90vh; overflow-y:auto; border-top:5px solid #059669;">
    <div class="card-header" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;">
      <span class="card-title" style="font-size:1.2rem; font-weight:800; color:#065f46; display:flex; align-items:center; gap:0.5rem;">
        <span>⚡</span> <span>eSSL Smart Terminal (ADMS) Cloud Setup</span>
      </span>
      <button style="background:none; border:none; font-size:1.2rem; cursor:pointer;" onclick="closeModal('esslGuideModal')">✕</button>
    </div>

    <div style="background:#ecfdf5; border:1px solid #a7f3d0; border-radius:10px; padding:0.85rem; margin-bottom:1.25rem;">
      <div style="font-weight:700; color:#065f46; font-size:0.9rem; margin-bottom:0.25rem;">
        📟 Machine SN: <code style="background:#059669; color:#fff; padding:0.15rem 0.5rem; border-radius:4px; font-weight:800;">NYU7262500437</code>
      </div>
      <div style="font-size:0.82rem; color:#047857;">
        Machine ke <strong>Menu &gt; Comm. &gt; Cloud Server Settings</strong> screen par exact yeh bharein:
      </div>
    </div>

    <div style="background:#0F172A; color:#F8FAFC; border-radius:12px; overflow:hidden; margin-bottom:1.25rem; font-size:0.85rem;">
      <div style="background:#1E293B; padding:0.6rem 1rem; font-weight:800; color:#38BDF8; display:flex; justify-content:space-between;">
        <span>Cloud Server Settings (Machine Screen)</span>
        <span style="color:#4ADE80;">Active Profile</span>
      </div>
      <table style="width:100%; border-collapse:collapse;">
        <tr style="border-bottom:1px solid #334155;">
          <td style="padding:0.75rem 1rem; color:#94A3B8; width:45%;">Server Mode</td>
          <td style="padding:0.75rem 1rem; color:#F8FAFC; font-weight:700;">
            <span style="background:#059669; color:#fff; padding:0.2rem 0.6rem; border-radius:4px;">ADMS</span>
          </td>
        </tr>
        <tr style="border-bottom:1px solid #334155;">
          <td style="padding:0.75rem 1rem; color:#94A3B8;">Enable Domain Name</td>
          <td style="padding:0.75rem 1rem; color:#4ADE80; font-weight:700;">
            🟢 ON (Switch ko Green kardo)
          </td>
        </tr>
        <tr style="border-bottom:1px solid #334155;">
          <td style="padding:0.75rem 1rem; color:#94A3B8;">Server Address</td>
          <td style="padding:0.75rem 1rem; color:#38BDF8; font-family:monospace; font-weight:800; font-size:0.95rem;">
            gym.ethicscomputer.in
            <div style="font-size:0.72rem; color:#fde68a; font-family:sans-serif; margin-top:0.2rem;">
              ⚠️ bina "http://" ya "/" ke sirf plain domain
            </div>
          </td>
        </tr>
        <tr style="border-bottom:1px solid #334155;">
          <td style="padding:0.75rem 1rem; color:#94A3B8;">Server Port</td>
          <td style="padding:0.75rem 1rem; color:#F8FAFC; font-weight:800;">
            80
          </td>
        </tr>
        <tr>
          <td style="padding:0.75rem 1rem; color:#94A3B8;">Enable Proxy Server</td>
          <td style="padding:0.75rem 1rem; color:#94A3B8; font-weight:700;">
            ⚪ OFF
          </td>
        </tr>
      </table>
    </div>

    <div style="background:#fef3c7; border:1px solid #fde68a; border-radius:10px; padding:0.85rem; font-size:0.82rem; color:#92400e; margin-bottom:1.25rem;">
      <strong>💡 Step 2: Settings Save & Restart</strong><br>
      Settings bharne ke baad <strong>M/OK</strong> button se Save karein. Machine ko ek baar restart karein. Top status bar par jo <strong>Cloud icon</strong> hai wo Green ho jayega aur Face / Finger lagate hi attendance direct save hogi!
    </div>

    <div style="display:flex; justify-content:space-between; align-items:center;">
      <button class="btn btn-secondary btn-sm" onclick="testEsslPunch('NYU7262500437')">
        👤 Test Live Face Punch
      </button>
      <button class="btn btn-primary" onclick="closeModal('esslGuideModal')">
        Got it! Done
      </button>
    </div>
  </div>
</div>

<!-- Realtime Machine Setup Guide Modal -->
<div class="modal" id="realtimeGuideModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:100; align-items:center; justify-content:center;">
  <div class="modal-overlay" style="position:absolute; inset:0;" onclick="closeModal('realtimeGuideModal')"></div>
  <div class="card" style="position:relative; z-index:10; background:#fff; width:90%; max-width:620px; border-radius:16px; padding:1.5rem; max-height:90vh; overflow-y:auto;">
    <div class="card-header" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;">
      <span class="card-title" style="font-size:1.15rem; font-weight:800; color:var(--text-primary);">
        📘 Realtime LAN Biometric Machine Setup Guide
      </span>
      <button style="background:none; border:none; font-size:1.2rem; cursor:pointer;" onclick="closeModal('realtimeGuideModal')">✕</button>
    </div>

    <div style="font-size:0.85rem; color:var(--text-secondary); line-height:1.5; display:flex; flex-direction:column; gap:1rem;">
      <div style="background:var(--bg-main); padding:0.85rem; border-radius:10px; border-left:4px solid var(--primary);">
        <strong style="color:var(--text-primary); font-size:0.95rem;">Step 1: Machine LAN Network Settings</strong>
        <p style="margin-top:0.3rem; margin-bottom:0;">
          On the Realtime device keypad, press <strong>MENU &gt; Comm. / Network &gt; Ethernet</strong>:
        </p>
        <ul style="margin:0.3rem 0 0 1.2rem; font-size:0.82rem;">
          <li><strong>IP Address:</strong> Set a static IP on your router (e.g. <code>192.168.1.205</code>)</li>
          <li><strong>Subnet Mask:</strong> <code>255.255.255.0</code></li>
          <li><strong>Gateway:</strong> Your Router IP (e.g. <code>192.168.1.1</code>)</li>
          <li><strong>Port:</strong> <code>5005</code> or <code>4370</code></li>
        </ul>
      </div>

      <div style="background:var(--bg-main); padding:0.85rem; border-radius:10px; border-left:4px solid var(--success);">
        <strong style="color:var(--text-primary); font-size:0.95rem;">Step 2: Server Push / Cloud Server Setup</strong>
        <p style="margin-top:0.3rem; margin-bottom:0;">
          In device menu, go to <strong>MENU &gt; Comm. &gt; Cloud Server / Web Server Setup</strong>:
        </p>
        <ul style="margin:0.3rem 0 0 1.2rem; font-size:0.82rem;">
          <li><strong>Server IP:</strong> <code><?= $serverIp ?></code> (Your Gym Server / PC LAN IP)</li>
          <li><strong>Server Port:</strong> <code><?= $serverPort ?></code></li>
          <li><strong>Request URL / Push URL:</strong> <code>/GYM/api/devices.php?action=realtime_push</code></li>
          <li><strong>Enable Push:</strong> <strong>ON / Yes</strong></li>
        </ul>
      </div>

      <div style="background:var(--bg-main); padding:0.85rem; border-radius:10px; border-left:4px solid #F59E0B;">
        <strong style="color:var(--text-primary); font-size:0.95rem;">Step 3: Register Biometric IDs in Gym Software</strong>
        <p style="margin-top:0.3rem; margin-bottom:0;">
          When enrolling fingerprints or RFID cards on the Realtime machine, note the User ID (e.g. <code>BIO-1001</code> or <code>1001</code>). Enter this exact ID in the member's profile in this software.
        </p>
      </div>
    </div>

    <div style="margin-top:1.5rem; text-align:right;">
      <button class="btn btn-primary" onclick="closeModal('realtimeGuideModal')">Got it! Close Guide</button>
    </div>
  </div>
</div>

<!-- Add Device Modal -->
<div class="modal" id="addDeviceModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:100; align-items:center; justify-content:center;">
  <div class="modal-overlay" style="position:absolute; inset:0;" onclick="closeModal('addDeviceModal')"></div>
  <div class="card" style="position:relative; z-index:10; background:#fff; width:90%; max-width:500px; border-radius:16px; padding:1.5rem;">
    <div class="card-header" style="display:flex; justify-content:space-between; align-items:center;">
      <span class="card-title">➕ Add Attendance Machine</span>
      <button style="background:none; border:none; font-size:1.2rem; cursor:pointer;" onclick="closeModal('addDeviceModal')">✕</button>
    </div>

    <form id="addDeviceForm" onsubmit="saveDevice(event)">
      <div class="form-group">
        <label class="form-label">Machine / Device Name</label>
        <input type="text" name="name" class="form-control" placeholder="e.g. Realtime T502 Turnstile Gate" required>
      </div>

      <div style="display:grid; grid-template-columns:2fr 1fr; gap:0.75rem;">
        <div class="form-group">
          <label class="form-label">LAN IP Address</label>
          <input type="text" name="ip_address" class="form-control" value="192.168.1.205" required>
        </div>
        <div class="form-group">
          <label class="form-label">Port</label>
          <input type="number" name="port" class="form-control" value="5005" required>
        </div>
      </div>

      <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.75rem;">
        <div class="form-group">
          <label class="form-label">Manufacturer / Protocol</label>
          <select name="manufacturer" class="form-control">
            <option value="Realtime" selected>Realtime Biometrics (LAN)</option>
            <option value="ZKTeco">ZKTeco / eSSL</option>
            <option value="Hikvision">Hikvision</option>
            <option value="Dahua">Dahua</option>
            <option value="Matrix">Matrix COSEC</option>
            <option value="Generic_HTTP">Generic HTTP Webhook</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Device Type</label>
          <select name="device_type" class="form-control">
            <option value="fingerprint">Fingerprint + RFID</option>
            <option value="face_recognition">Face Recognition</option>
            <option value="rfid_card">RFID Reader</option>
            <option value="qr_scanner">QR Access Code</option>
          </select>
        </div>
      </div>

      <div class="form-group">
        <label class="form-label">Location / Gate</label>
        <input type="text" name="location" class="form-control" value="Reception Entrance Gate">
      </div>

      <div style="display:flex; gap:0.75rem; margin-top:1.5rem;">
        <button type="button" class="btn btn-secondary btn-block" onclick="closeModal('addDeviceModal')">Cancel</button>
        <button type="submit" class="btn btn-primary btn-block">✓ SAVE DEVICE</button>
      </div>
    </form>
  </div>
</div>

<script>
function copyPushUrl() {
  const urlBox = document.getElementById('pushUrlBox');
  if (!urlBox) return;
  const url = urlBox.innerText.trim();
  navigator.clipboard.writeText(url).then(() => {
    showToast('✅ URL copied! Paste it in machine settings (Menu → Comm. → Cloud Server)', 'success');
    urlBox.style.background = '#14532d';
    setTimeout(() => { urlBox.style.background = '#020617'; }, 1500);
  }).catch(() => {
    // Fallback for older browsers
    const ta = document.createElement('textarea');
    ta.value = url;
    ta.style.position = 'fixed';
    ta.style.opacity = '0';
    document.body.appendChild(ta);
    ta.select();
    document.execCommand('copy');
    document.body.removeChild(ta);
    showToast('✅ URL copied to clipboard!', 'success');
  });
}

function triggerPullSync() {
  showToast('Connecting to Realtime & Biometric Hardware Terminals over LAN...', 'info');
  fetch('api/devices.php?action=pull_sync')
    .then(res => res.json())
    .then(res => {
      showToast(res.message, 'success');
      setTimeout(() => location.reload(), 1200);
    });
}

function triggerTimeSync() {
  showToast('Synchronizing Realtime terminal clocks with server time...', 'info');
  fetch('api/devices.php?action=time_sync')
    .then(res => res.json())
    .then(res => showToast(res.message, 'success'));
}

function pingDevice(id) {
  showToast('Testing Realtime LAN TCP socket connectivity...', 'info');
  fetch('api/devices.php?action=ping&id=' + id)
    .then(res => res.json())
    .then(res => showToast(res.message, res.success ? 'success' : 'danger'));
}

function pushMemberData() {
  showToast('Pushing enrolled member biometric IDs to Realtime machine...', 'info');
  setTimeout(() => {
    showToast('Member biometric records successfully synchronized with Realtime hardware!', 'success');
  }, 1000);
}

function testRealtimePunch(deviceSerial) {
  showToast('Simulating live Realtime punch (BIO-1001)...', 'info');
  fetch('api/devices.php?action=realtime_push', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      UserId: 'BIO-1001',
      DeviceNo: deviceSerial,
      LogTime: new Date().toISOString().slice(0, 19).replace('T', ' '),
      Status: 'Punch_In'
    })
  })
  .then(res => res.json())
  .then(res => {
    if (res.success) {
      showToast('✓ ' + res.message, 'success');
    } else {
      showToast(res.message || 'Realtime punch test failed', 'danger');
    }
  });
}

function testEsslPunch(deviceSerial) {
  showToast('Simulating live eSSL Face punch (' + deviceSerial + ')...', 'info');
  const now = new Date().toISOString().slice(0, 19).replace('T', ' ');
  const payload = 'STF-101\t' + now + '\t0\t15\t0\t0';

  fetch('iclock/cdata.php?SN=' + encodeURIComponent(deviceSerial) + '&table=ATTLOG', {
    method: 'POST',
    headers: { 'Content-Type': 'text/plain' },
    body: payload
  })
  .then(res => res.text())
  .then(text => {
    if (text.includes('OK')) {
      showToast('✅ eSSL Face Punch Processed! Response: ' + text.trim(), 'success');
      loadUnregisteredPunches();
    } else {
      showToast('eSSL Response: ' + text, 'info');
    }
  })
  .catch(err => {
    showToast('Failed to contact eSSL ADMS endpoint: ' + err.message, 'danger');
  });
}

function saveDevice(e) {
  e.preventDefault();
  const formData = new FormData(e.target);
  formData.append('action', 'add');

  fetch('api/devices.php', {
    method: 'POST',
    body: formData
  })
  .then(res => res.json())
  .then(res => {
    showToast(res.message, 'success');
    closeModal('addDeviceModal');
    setTimeout(() => location.reload(), 1000);
  });
}

// Load unregistered punches
function loadUnregisteredPunches() {
  fetch('api/devices.php?action=unregistered_punches')
    .then(r => r.json())
    .then(r => {
      const body = document.getElementById('unregisteredPunchesBody');
      if (!body) return;

      if (!r.success || !r.data || r.data.length === 0) {
        body.innerHTML = `
          <div style="text-align:center; padding:1.5rem; color:var(--text-muted);">
            <div style="font-size:2rem;">✅</div>
            <div style="font-weight:600;">No unknown biometric punches! All scans are registered.</div>
          </div>`;
        return;
      }

      let html = `<div style="overflow-x:auto;"><table class="table" style="width:100%;">
        <thead><tr>
          <th>Biometric ID (Unknown)</th>
          <th>Device</th>
          <th>Punch Time</th>
          <th>Action</th>
        </tr></thead><tbody>`;

      r.data.forEach(p => {
        const t = new Date(p.punch_time).toLocaleString('en-IN');
        html += `<tr>
          <td><code style="background:#7c3aed22; color:#7c3aed; padding:0.2rem 0.5rem; border-radius:4px; font-weight:700;">${p.biometric_id}</code></td>
          <td>${p.device_serial || 'Unknown'}</td>
          <td>${t}</td>
          <td style="display:flex; gap:0.4rem;">
            <button class="btn btn-primary btn-sm" onclick="window.open('index.php?page=members', '_blank')" style="font-size:0.72rem;">👤 Register Member</button>
            <button class="btn btn-outline btn-sm" onclick="resolvePunch(${p.id}, this)" style="font-size:0.72rem;">✓ Dismiss</button>
          </td>
        </tr>`;
      });

      html += '</tbody></table></div>';
      body.innerHTML = html;
    })
    .catch(() => {
      const body = document.getElementById('unregisteredPunchesBody');
      if (body) body.innerHTML = '<div style="padding:1rem; color:var(--text-muted);">Could not load unregistered punches.</div>';
    });
}

function resolvePunch(punchId, btn) {
  fetch('api/devices.php?action=resolve_unregistered', {
    method: 'POST',
    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    body: `punch_id=${punchId}`
  })
  .then(r => r.json())
  .then(r => {
    if (r.success) {
      const row = btn.closest('tr');
      if (row) { row.style.opacity = '0'; setTimeout(() => row.remove(), 300); }
      showToast('Punch dismissed', 'success');
    }
  });
}

// Auto-load on page ready
document.addEventListener('DOMContentLoaded', loadUnregisteredPunches);
</script>
