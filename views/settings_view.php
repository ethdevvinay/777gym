<?php
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $gymName = $_POST['gym_name'] ?? '';
    $phone = $_POST['gym_phone'] ?? '';
    $gymAddress = $_POST['gym_address'] ?? '';
    $gymEmail = $_POST['gym_email'] ?? '';
    $gymFacebook = $_POST['gym_facebook'] ?? '';
    $gymTagline = $_POST['gym_tagline'] ?? '';
    $upiId = $_POST['upi_id'] ?? '';
    $taxRate = '0.00';
    $primaryColor = $_POST['primary_color'] ?? '#EAB308';
    $whatsappDisclaimer = $_POST['whatsapp_disclaimer'] ?? '';
    $whatsappDisclaimerEnabled = isset($_POST['whatsapp_disclaimer_enabled']) ? '1' : '0';

    $stmt = $db->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
    $stmt->execute(['gym_name', $gymName]);
    $stmt->execute(['gym_phone', $phone]);
    $stmt->execute(['gym_address', $gymAddress]);
    $stmt->execute(['gym_email', $gymEmail]);
    $stmt->execute(['gym_facebook', $gymFacebook]);
    $stmt->execute(['gym_tagline', $gymTagline]);
    $stmt->execute(['upi_id', $upiId]);
    $stmt->execute(['tax_rate', $taxRate]);
    $stmt->execute(['primary_color', $primaryColor]);
    $stmt->execute(['whatsapp_disclaimer', $whatsappDisclaimer]);
    $stmt->execute(['whatsapp_disclaimer_enabled', $whatsappDisclaimerEnabled]);

    logAuditAction(1, 'Update Gym System Settings', 'SETTINGS', null, ['gym_name' => $gymName]);
    $saved = true;
}

$gymName = getSetting('gym_name', 'THE CLUB 777®');
$phone = getSetting('gym_phone', '8053576777, 8053570777, 9416528777');
$gymAddress = getSetting('gym_address', 'Behind Shehnai Garden, Near Railway Station, Jhajjar - 124103 (Haryana)');
$gymEmail = getSetting('gym_email', 'theclub777jjr@gmail.com');
$gymFacebook = getSetting('gym_facebook', 'theclub777jhajjar');
$gymTagline = getSetting('gym_tagline', 'GYM, SWIM & MORE...');
$gstin = getSetting('gstin', '06AAAC7777H1Z5');
$upiId = getSetting('upi_id', '8053576777@upi');
$taxRate = getSetting('tax_rate', '18.00');
$primaryColor = getSetting('primary_color', '#EAB308');
$whatsappDisclaimer = getSetting('whatsapp_disclaimer', "_Note: Terms & conditions apply. Membership fees are non-refundable. For queries, contact THE CLUB 777® reception at 8053576777._");
$whatsappDisclaimerEnabled = getSetting('whatsapp_disclaimer_enabled', '1');
$isDiscOn = ($whatsappDisclaimerEnabled === '1' || $whatsappDisclaimerEnabled === 'true' || $whatsappDisclaimerEnabled === 'yes' || $whatsappDisclaimerEnabled === 1);

$branches = $db->query("SELECT * FROM branches ORDER BY id ASC")->fetchAll();
?>

<div class="page-content">
  <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem; margin-bottom:1.5rem;">
    <div>
      <h1 style="font-size:1.6rem; font-weight:800; color:var(--text-primary);">THE CLUB 777® System &amp; Brand Settings</h1>
      <p style="font-size:0.85rem; color:var(--text-secondary);">Manage gym address, contact numbers, email, social profiles, GSTIN, receipt headers &amp; branch info</p>
    </div>

    <a href="api/backup.php?action=download" class="btn btn-success" style="text-decoration:none;">
      📥 Download SQL Database Backup
    </a>
  </div>

  <?php if (!empty($saved)): ?>
    <div style="padding:1rem; background:var(--success-bg); border:1px solid var(--success-border); color:var(--success); border-radius:8px; font-weight:700; margin-bottom:1.5rem;">
      ✓ Settings updated successfully!
    </div>
  <?php endif; ?>

  <div style="display:grid; grid-template-columns:1fr 1fr; gap:1.5rem;">
    <!-- System Profile & Financial Settings Form -->
    <form method="POST" action="index.php?page=settings" class="card">
      <div class="card-title">👑 Commercial Branding &amp; Invoicing Configuration</div>

      <!-- Active Brand Logo Display -->
      <div style="margin-top:1rem; padding:1rem; background:var(--bg-secondary); border:1px solid var(--border-color); border-radius:12px; display:flex; align-items:center; gap:1.25rem;">
        <div style="background:#fff; padding:6px; border-radius:10px; box-shadow:0 2px 8px rgba(0,0,0,0.06); border:1px solid #E2E8F0; display:flex; align-items:center; justify-content:center; width:64px; height:64px;">
          <img src="logo.png" alt="Gym Logo" style="max-width:100%; max-height:100%; object-fit:contain;" onerror="this.parentElement.innerHTML='👑';">
        </div>
        <div>
          <div style="font-weight:800; font-size:0.95rem; color:var(--text-primary);">Active Gym Logo</div>
          <div style="font-size:0.78rem; color:var(--text-secondary); margin-top:2px;">
            File: <code style="font-family:var(--font-mono); color:var(--primary);">logo.png</code> &bull; Displayed on Navbar, Login, Receipts &amp; Portal
          </div>
        </div>
      </div>

      <div style="display:grid; grid-template-columns:1.2fr 1fr; gap:0.75rem; margin-top:1rem;">
        <div class="form-group">
          <label class="form-label">Brand / Gym Name</label>
          <input type="text" name="gym_name" class="form-control" value="<?= htmlspecialchars($gymName) ?>" required>
        </div>
        <div class="form-group">
          <label class="form-label">Tagline / Slogan</label>
          <input type="text" name="gym_tagline" class="form-control" value="<?= htmlspecialchars($gymTagline) ?>">
        </div>
      </div>

      <div class="form-group">
        <label class="form-label">Physical Address</label>
        <input type="text" name="gym_address" class="form-control" value="<?= htmlspecialchars($gymAddress) ?>" required>
      </div>

      <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.75rem;">
        <div class="form-group">
          <label class="form-label">Contact / WhatsApp Phone Numbers</label>
          <input type="text" name="gym_phone" class="form-control" value="<?= htmlspecialchars($phone) ?>" required>
        </div>
        <div class="form-group">
          <label class="form-label">Official Email</label>
          <input type="email" name="gym_email" class="form-control" value="<?= htmlspecialchars($gymEmail) ?>">
        </div>
      </div>

      <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.75rem;">
        <div class="form-group">
          <label class="form-label">Facebook Page Username</label>
          <input type="text" name="gym_facebook" class="form-control" value="<?= htmlspecialchars($gymFacebook) ?>" placeholder="theclub777jhajjar">
        </div>
        <div class="form-group">
          <label class="form-label">UPI Merchant VPA ID</label>
          <input type="text" name="upi_id" class="form-control" value="<?= htmlspecialchars($upiId) ?>" placeholder="8053576777@upi">
        </div>
      </div>

      <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.75rem;">
        <div class="form-group">
          <label class="form-label">Tax Regime</label>
          <div style="padding:0.6rem 0.85rem; background:#ECFDF5; border:1px solid #A7F3D0; border-radius:8px; font-size:0.85rem; font-weight:700; color:#065F46;">
            ✅ Non-GST Billing (0.00% Tax Exempt)
          </div>
          <input type="hidden" name="tax_rate" value="0.00">
        </div>
        <div class="form-group">
          <label class="form-label">Central Primary Accent Color</label>
          <div style="display:flex; gap:0.75rem; align-items:center;">
            <input type="color" name="primary_color" class="form-control" style="width:50px; height:40px; padding:2px; cursor:pointer;" value="<?= htmlspecialchars($primaryColor) ?>" onchange="document.documentElement.style.setProperty('--primary', this.value)">
            <span style="font-size:0.82rem; color:var(--text-secondary); font-weight:600;">Gold Brand Theme</span>
          </div>
        </div>
      </div>

      <!-- WhatsApp Message Disclaimer Setting -->
      <div style="margin-top:1.5rem; padding-top:1rem; border-top:1px solid var(--border-color);">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.75rem;">
          <strong style="font-size:0.95rem; color:var(--text-primary);">📱 WhatsApp Message Disclaimer / Footer Notice</strong>
          <label style="display:flex; align-items:center; gap:0.4rem; cursor:pointer; font-size:0.8rem; font-weight:700;">
            <input type="checkbox" name="whatsapp_disclaimer_enabled" value="1" <?= $isDiscOn ? 'checked' : '' ?>>
            <span>Active</span>
          </label>
        </div>
        <div class="form-group">
          <textarea name="whatsapp_disclaimer" class="form-control" rows="3" placeholder="e.g. _Note: Terms & conditions apply. Membership fees are non-refundable._"><?= htmlspecialchars($whatsappDisclaimer) ?></textarea>
          <p style="font-size:0.72rem; color:var(--text-muted); margin-top:0.25rem;">
            This footer text is automatically attached at the end of every WhatsApp renewal reminder and birthday message.
          </p>
        </div>
      </div>

      <button type="submit" class="btn btn-primary btn-block btn-lg" style="margin-top:1rem;">✓ SAVE SYSTEM SETTINGS</button>
    </form>

    <!-- Multi-Branch Management Section -->
    <div class="card">
      <div class="card-header" style="display:flex; justify-content:space-between; align-items:center;">
        <span class="card-title">🏢 Multi-Branch Locations</span>
        <button class="btn btn-outline btn-sm" onclick="showToast('Branch Management Active', 'info')">+ Add Branch</button>
      </div>

      <div style="display:flex; flex-direction:column; gap:0.75rem; margin-top:0.75rem;">
        <?php foreach ($branches as $br): ?>
          <div style="background:var(--bg-main); border:1px solid var(--border-color); border-radius:10px; padding:1rem;">
            <div style="display:flex; justify-content:space-between; align-items:flex-start;">
              <div>
                <strong style="font-size:1.05rem; color:var(--text-primary);"><?= htmlspecialchars($br['name']) ?></strong>
                <div style="font-size:0.75rem; color:var(--primary); font-family:monospace;"><?= htmlspecialchars($br['code']) ?></div>
              </div>
              <span class="badge badge-success">ACTIVE</span>
            </div>
            <div style="font-size:0.8rem; color:var(--text-secondary); margin-top:0.5rem;">
              📍 <?= htmlspecialchars($br['address']) ?>, <?= htmlspecialchars($br['city']) ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>
