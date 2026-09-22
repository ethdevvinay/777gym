<!-- Professional A4 Half (A5) Official Gym Fee Receipt & Membership Invoice Component -->
<?php
$db = getDB();

$saleId = $_GET['id'] ?? null;
$invoiceNo = $_GET['invoice_no'] ?? null;

$sale = null;
if ($saleId) {
    $stmt = $db->prepare("
        SELECT s.*, m.name as member_name, m.member_code, m.phone as member_phone, m.email as member_email, 
               m.gender, m.dob, m.blood_group, m.locker_no, m.biometric_id
        FROM sales s
        LEFT JOIN members m ON s.member_id = m.id
        WHERE s.id = ?
    ");
    $stmt->execute([$saleId]);
    $sale = $stmt->fetch();
} elseif ($invoiceNo) {
    $stmt = $db->prepare("
        SELECT s.*, m.name as member_name, m.member_code, m.phone as member_phone, m.email as member_email, 
               m.gender, m.dob, m.blood_group, m.locker_no, m.biometric_id
        FROM sales s
        LEFT JOIN members m ON s.member_id = m.id
        WHERE s.invoice_no = ?
    ");
    $stmt->execute([$invoiceNo]);
    $sale = $stmt->fetch();
}

// If no specific sale, fallback to latest sale
if (!$sale) {
    $sale = $db->query("
        SELECT s.*, m.name as member_name, m.member_code, m.phone as member_phone, m.email as member_email, 
               m.gender, m.dob, m.blood_group, m.locker_no, m.biometric_id
        FROM sales s
        LEFT JOIN members m ON s.member_id = m.id
        ORDER BY s.id DESC LIMIT 1
    ")->fetch();
}

if (!$sale) {
    $gymName = getSetting('gym_name', 'THE CLUB 777®');
    echo '<div style="max-width:600px; margin:3rem auto; text-align:center; padding:2.5rem; background:#fff; border-radius:18px; box-shadow:0 10px 25px rgba(0,0,0,0.06);">'
       . '<div style="font-size:3rem; margin-bottom:1rem;">🧾</div>'
       . '<h2 style="color:#0F172A; font-weight:800; margin-bottom:0.5rem;">Receipt Not Found</h2>'
       . '<p style="color:#64748B; font-size:0.95rem; margin-bottom:1.5rem;">The requested receipt or invoice could not be found. Please contact ' . htmlspecialchars($gymName) . ' reception.</p>'
       . '<a href="tel:8053576777" class="btn btn-primary btn-sm" style="font-weight:700; text-decoration:none;">📞 Call Reception (8053576777)</a>'
       . '</div>';
    return;
}

$saleItems = [];
if ($sale) {
    $stmtItems = $db->prepare("
        SELECT si.*, 
               IFNULL(si.item_name, p.name) as product_name, 
               IFNULL(si.item_type, 'Service') as product_type,
               si.qty as quantity
        FROM sale_items si 
        LEFT JOIN products p ON (si.item_type = 'product' AND si.item_id = p.id)
        WHERE si.sale_id = ?
    ");
    $stmtItems->execute([$sale['id']]);
    $saleItems = $stmtItems->fetchAll();
}

$gymName = getSetting('gym_name', 'THE CLUB 777®');
$gymPhone = getSetting('gym_phone', '8053576777, 8053570777, 9416528777');
$gymEmail = getSetting('gym_email', 'theclub777jjr@gmail.com');
$gymAddress = getSetting('gym_address', 'Behind Shehnai Garden, Near Railway Station, Jhajjar - 124103 (Haryana)');
$currency = getSetting('currency_symbol', '₹');

// Client WhatsApp Link
$cleanPhone = preg_replace('/[^0-9]/', '', $sale['member_phone'] ?? '');
if (strlen($cleanPhone) === 10) $cleanPhone = '91' . $cleanPhone;

$appUrl = rtrim(getSetting('app_url', 'https://gym.ethicscomputer.in'), '/');
$receiptUrl = "{$appUrl}/index.php?page=receipt&id=" . intval($sale['id'] ?? 0);
$pdfDownloadUrl = "{$appUrl}/api/generate_invoice_pdf.php?invoice_no=" . urlencode($sale['invoice_no'] ?? '') . "&output=download";
$dueText = (($sale['due_amount'] ?? 0) > 0) ? "🔴 Pending Due: {$currency}" . number_format($sale['due_amount'], 2) . "\n" : "🟢 Status: FULLY PAID\n";

$waText = urlencode("Hello " . ($sale['member_name'] ?? 'Member') . "! 🏋️ Here is your Official Fee Receipt from {$gymName}.\n\n📄 Invoice No: " . ($sale['invoice_no'] ?? '') . "\n💰 Total Bill: {$currency}" . number_format($sale['total'] ?? 0, 2) . "\n🟢 Paid Amount: {$currency}" . number_format($sale['paid_amount'] ?? 0, 2) . "\n{$dueText}\n🔗 View Official Receipt:\n{$receiptUrl}\n\n📥 Download A4 PDF Invoice:\n{$pdfDownloadUrl}\n\n📍 Behind Shehnai Garden, Near Railway Station, Jhajjar\n📞 8053576777, 8053570777\n\n_Stay Strong & Keep Transforming!_ 💪");
$waShareUrl = "https://wa.me/{$cleanPhone}?text={$waText}";
?>

<!-- Action Bar (Hidden on Print) -->
<div class="receipt-action-bar hide-on-print" style="max-width:820px; margin:0 auto 1.5rem auto; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:0.75rem; padding:0.85rem 1.25rem; background:var(--bg-surface); border:1px solid var(--border-color); border-radius:14px; box-shadow:var(--shadow-card);">
  <div style="display:flex; align-items:center; gap:0.6rem;">
    <?php if (isset($_SESSION['user_id'])): ?>
    <a href="index.php?page=payments" class="btn btn-secondary btn-sm" style="font-weight:700;">
      ← Back to Ledger
    </a>
    <?php endif; ?>
    <span style="font-weight:800; font-size:0.95rem; color:var(--text-primary);">
      🧾 <?= isset($_SESSION['user_id']) ? 'Official Fee Receipt Preview' : 'Official Fee Receipt — ' . htmlspecialchars($gymName) ?>
    </span>
  </div>

  <div style="display:flex; gap:0.5rem; flex-wrap:wrap;">
    <!-- Send PDF Document on WhatsApp (Admin Only) -->
    <?php if (!empty($sale['member_phone']) && isset($_SESSION['user_id'])): ?>
    <button class="btn btn-success btn-sm" id="receiptSendPdfWA" style="background:#25D366; border-color:#25D366; font-weight:800; display:inline-flex; align-items:center; gap:0.35rem;" title="Send Actual PDF File on WhatsApp" onclick="sendReceiptPdfWhatsApp()">
      <span>📎 Send on WhatsApp</span>
    </button>
    <?php endif; ?>
    <!-- Download PDF -->
    <a href="<?= htmlspecialchars($pdfDownloadUrl) ?>" class="btn btn-primary btn-sm" style="font-weight:800; display:inline-flex; align-items:center; gap:0.35rem; text-decoration:none;" title="Download A4 PDF Invoice">
      <span>📥 Download A4 PDF</span>
    </a>
    <!-- Print -->
    <button class="btn btn-secondary btn-sm" style="font-weight:800; display:inline-flex; align-items:center; gap:0.35rem;" onclick="window.print()">
      <span>🖨️ Print</span>
    </button>
  </div>
</div>

<?php if (!empty($sale['member_phone'])): ?>
<script>
function sendReceiptPdfWhatsApp() {
  const btn = document.getElementById('receiptSendPdfWA');
  if (btn) { btn.disabled = true; btn.innerHTML = '<span>⏳ Generating & Sending...</span>'; }

  fetch('api/whatsapp.php?action=send_document', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      phone: '<?= addslashes($cleanPhone) ?>',
      invoice_no: '<?= addslashes($sale['invoice_no'] ?? '') ?>'
    })
  })
  .then(r => r.json())
  .then(res => {
    if (res.success) {
      if (btn) { btn.innerHTML = '<span>✅ PDF Sent!</span>'; btn.style.background = '#059669'; }
      if (typeof showToast === 'function') showToast('✅ PDF Invoice sent via WhatsApp!', 'success');
    } else {
      // Fallback to wa.me link
      if (res.data && res.data.fallback_url) {
        window.open(res.data.fallback_url, '_blank');
      } else {
        window.open('<?= $waShareUrl ?>', '_blank');
      }
      if (btn) { btn.innerHTML = '<span>📎 Send PDF on WhatsApp</span>'; btn.disabled = false; }
    }
  })
  .catch(() => {
    window.open('<?= $waShareUrl ?>', '_blank');
    if (btn) { btn.innerHTML = '<span>📎 Send PDF on WhatsApp</span>'; btn.disabled = false; }
  });
}
</script>
<?php endif; ?>

<!-- ========================================================= -->
<!-- OFFICIAL A4 HALF (A5) INVOICE CONTAINER                   -->
<!-- ========================================================= -->
<div class="a4-half-invoice-wrapper" id="printableA4Invoice">
  <div class="a4-half-invoice-card">
    
    <!-- Top Header Ribbon -->
    <div class="invoice-header-grid">
      <div class="gym-brand-col">
        <div style="display:flex; align-items:center; gap:0.75rem;">
          <img src="logo.png" alt="<?= htmlspecialchars($gymName) ?>" style="height:52px; width:auto; max-width:65px; object-fit:contain; border-radius:8px;" onerror="this.onerror=null; this.parentElement.insertAdjacentHTML('afterbegin', '<div class=\'gym-logo-box\' style=\'background:linear-gradient(135deg, #F59E0B 0%, #D97706 100%); color:#fff; border-radius:10px; font-size:1.5rem; width:44px; height:44px; display:flex; align-items:center; justify-content:center;\'>👑</div>'); this.style.display='none';">
          <div>
            <h2 class="gym-title" style="color:#B45309; font-weight:900; font-size:1.45rem; letter-spacing:-0.02em;"><?= htmlspecialchars($gymName) ?></h2>
            <div class="gym-subtitle" style="font-weight:800; color:#92400E; letter-spacing:0.04em;">GYM, SWIM &amp; MORE... (Jhajjar, Haryana)</div>
          </div>
        </div>
        <div class="gym-details-text" style="font-size:0.82rem; line-height:1.45; color:#334155; margin-top:0.4rem;">
          <span>📍 <?= htmlspecialchars($gymAddress) ?></span><br>
          <span>📞 <?= htmlspecialchars($gymPhone) ?></span><br>
          <span>✉️ <?= htmlspecialchars($gymEmail) ?> &bull; 🌐 fb.com/<?= htmlspecialchars(getSetting('gym_facebook', 'theclub777jhajjar')) ?></span>
        </div>
      </div>

      <div class="invoice-badge-col">
        <div class="invoice-type-pill">OFFICIAL FEE RECEIPT</div>
        <div class="invoice-number-tag"><?= htmlspecialchars($sale['invoice_no'] ?? 'INV-0000') ?></div>
        <div class="invoice-meta-date">
          <strong>Date:</strong> <?= !empty($sale['created_at']) ? date('d M Y, h:i A', strtotime($sale['created_at'])) : date('d M Y') ?>
        </div>
        <div class="invoice-meta-date">
          <strong>Payment Mode:</strong> <span style="font-weight:700;"><?= htmlspecialchars($sale['payment_method'] ?? 'Cash') ?></span>
          <?php if (!empty($sale['utr_ref'])): ?>
            <br><span style="font-size:0.75rem; color:#64748B;">UTR: <?= htmlspecialchars($sale['utr_ref']) ?></span>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Client / Member Bill To Info Box -->
    <div class="member-info-panel">
      <div class="info-section">
        <span class="info-label">BILLED TO (CLIENT DETAILS):</span>
        <div class="info-name"><?= htmlspecialchars($sale['member_name'] ?: 'Walk-in Customer') ?></div>
        <div class="info-sub">
          <strong>Member ID:</strong> <?= htmlspecialchars($sale['member_code'] ?: 'GUEST') ?> &bull; 
          <strong>Phone:</strong> <?= htmlspecialchars($sale['member_phone'] ?: 'N/A') ?>
        </div>
        <?php if (!empty($sale['member_email'])): ?>
          <div class="info-sub"><strong>Email:</strong> <?= htmlspecialchars($sale['member_email']) ?></div>
        <?php endif; ?>
      </div>

      <div class="info-section text-right">
        <span class="info-label">PAYMENT &amp; ISSUANCE:</span>
        <div class="info-sub" style="margin-top:0.2rem;">
          <strong>Payment Mode:</strong> <?= htmlspecialchars(strtoupper($sale['payment_method'] ?? 'CASH')) ?>
        </div>
        <div class="info-sub">
          <strong>Billing Status:</strong> 
          <span style="font-weight:800; color:<?= (!empty($sale['due_amount']) && $sale['due_amount'] > 0) ? '#DC2626' : '#059669' ?>;">
            <?= (!empty($sale['due_amount']) && $sale['due_amount'] > 0) ? 'PARTIAL / DUE' : 'PAID IN FULL' ?>
          </span>
        </div>
        <div class="info-sub">
          <strong>Branch:</strong> Jhajjar Main Branch
        </div>
      </div>
    </div>

    <!-- Itemized Particulars Table -->
    <table class="invoice-items-table">
      <thead>
        <tr>
          <th style="width:35px;">#</th>
          <th>Description of Services / Products</th>
          <th class="text-center" style="width:60px;">Qty</th>
          <th class="text-right" style="width:100px;">Rate (<?= $currency ?>)</th>
          <th class="text-right" style="width:110px;">Amount (<?= $currency ?>)</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!empty($saleItems)): ?>
          <?php $idx = 1; foreach ($saleItems as $item): ?>
            <tr>
              <td class="text-muted font-mono"><?= $idx++ ?></td>
              <td>
                <strong style="color:#0F172A;"><?= htmlspecialchars($item['product_name'] ?: 'Gym Membership Protocol') ?></strong>
                <div style="font-size:0.72rem; color:#64748B;">Category: <?= htmlspecialchars(ucfirst($item['product_type'] ?? 'Service')) ?></div>
              </td>
              <td class="text-center font-mono"><?= $item['quantity'] ?></td>
              <td class="text-right font-mono"><?= number_format($item['unit_price'], 2) ?></td>
              <td class="text-right font-mono" style="font-weight:700; color:#0F172A;"><?= number_format($item['total_price'], 2) ?></td>
            </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr>
            <td class="text-muted font-mono">1</td>
            <td>
              <strong style="color:#0F172A;">Gym Membership Subscription / Service Bill</strong>
              <div style="font-size:0.72rem; color:#64748B;">Full Access Protocol &bull; Floor, Cardio &amp; Steam</div>
            </td>
            <td class="text-center font-mono">1</td>
            <td class="text-right font-mono"><?= number_format($sale['subtotal'] ?? $sale['total'], 2) ?></td>
            <td class="text-right font-mono" style="font-weight:700; color:#0F172A;"><?= number_format($sale['subtotal'] ?? $sale['total'], 2) ?></td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>

    <!-- Calculations & Grand Total Summary Grid -->
    <div class="invoice-bottom-grid">
      <!-- Left Terms & Conditions Column -->
      <div class="terms-column">
        <div class="terms-title">TERMS &amp; GYM POLICIES:</div>
        <ul class="terms-list">
          <li>1. Fees once paid are non-refundable and non-transferable under any circumstances.</li>
          <li>2. Membership freeze requests must be submitted in advance as per plan limits.</li>
          <li>3. Members must carry clean workout shoes &amp; towel on gym floor.</li>
          <li>4. This is a computer-generated authorized digital receipt.</li>
        </ul>

        <?php if (!empty($sale['notes'])): ?>
          <div style="margin-top:0.5rem; padding:0.4rem 0.6rem; background:#FFFBEB; border:1px solid #FDE68A; border-radius:6px; font-size:0.72rem; color:#92400E;">
            <strong>Remarks / Note:</strong> <?= htmlspecialchars($sale['notes']) ?>
          </div>
        <?php endif; ?>
      </div>

      <!-- Right Summary Column -->
      <div class="summary-table-column">
        <div class="summary-line">
          <span>Subtotal Amount:</span>
          <span class="font-mono"><?= $currency ?><?= number_format($sale['subtotal'] ?? $sale['total'], 2) ?></span>
        </div>

        <?php if (!empty($sale['discount']) && $sale['discount'] > 0): ?>
          <div class="summary-line text-success">
            <span>Special Discount (Waived):</span>
            <span class="font-mono">- <?= $currency ?><?= number_format($sale['discount'], 2) ?></span>
          </div>
        <?php endif; ?>

        <div class="summary-line grand-total-line">
          <span>TOTAL PAYABLE:</span>
          <span class="font-mono"><?= $currency ?><?= number_format($sale['total'], 2) ?></span>
        </div>

        <div class="summary-line" style="font-weight:700; color:#065F46;">
          <span>Paid Amount:</span>
          <span class="font-mono"><?= $currency ?><?= number_format($sale['paid_amount'], 2) ?></span>
        </div>

        <?php if (($sale['due_amount'] ?? 0) > 0): ?>
          <div class="summary-line due-line">
            <span>OUTSTANDING DUE:</span>
            <span class="font-mono"><?= $currency ?><?= number_format($sale['due_amount'], 2) ?></span>
          </div>
          <?php if (!empty($sale['due_date'])): ?>
            <div style="font-size:0.72rem; color:#DC2626; text-align:right; font-weight:700; margin-top:-2px;">
              Promised By: <?= date('d M Y', strtotime($sale['due_date'])) ?>
            </div>
          <?php endif; ?>
        <?php endif; ?>
      </div>
    </div>

    <!-- Official Stamp & Authorized Signatory Footer -->
    <div class="invoice-signature-row">
      <div style="font-size:0.72rem; color:#64748B;">
        <span>Generated by: Staff Desk (User ID: <?= htmlspecialchars($_SESSION['user_name'] ?? 'Admin') ?>)</span><br>
        <span>Thank you for being part of our fitness family! 💪</span>
      </div>

      <div class="signatory-box">
        <div class="sign-line"></div>
        <div class="sign-text">Authorized Signatory &amp; Stamp</div>
        <div class="sign-sub"><?= htmlspecialchars($gymName) ?></div>
      </div>
    </div>

  </div>
</div>

<!-- ========================================================= -->
<!-- STYLESHEET FOR A4 HALF (A5) INVOICE                       -->
<!-- ========================================================= -->
<style>
.a4-half-invoice-wrapper {
  max-width: 820px;
  margin: 0 auto;
  background: #fff;
  border-radius: 16px;
  box-shadow: 0 10px 30px rgba(0,0,0,0.06);
  border: 1px solid var(--border-color);
  padding: 1.75rem 2rem;
  box-sizing: border-box;
}

.a4-half-invoice-card {
  font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
  color: #1E293B;
  line-height: 1.4;
}

.invoice-header-grid {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  border-bottom: 2px solid #E2E8F0;
  padding-bottom: 1rem;
  margin-bottom: 1rem;
  gap: 1rem;
}

.gym-logo-box {
  width: 44px;
  height: 44px;
  background: #EFF6FF;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.5rem;
  border: 1px solid #DBEAFE;
}

.gym-title {
  font-size: 1.25rem;
  font-weight: 900;
  color: #0F172A;
  margin: 0;
  letter-spacing: -0.02em;
}

.gym-subtitle {
  font-size: 0.75rem;
  color: #64748B;
  font-weight: 600;
}

.gym-details-text {
  font-size: 0.74rem;
  color: #475569;
  margin-top: 0.4rem;
  line-height: 1.35;
}

.invoice-badge-col {
  text-align: right;
}

.invoice-type-pill {
  display: inline-block;
  background: #0F172A;
  color: #FFFFFF;
  font-size: 0.72rem;
  font-weight: 800;
  padding: 0.25rem 0.65rem;
  border-radius: 6px;
  letter-spacing: 0.05em;
}

.invoice-number-tag {
  font-size: 1.15rem;
  font-weight: 900;
  color: var(--primary);
  font-family: var(--font-mono);
  margin-top: 0.3rem;
}

.invoice-meta-date {
  font-size: 0.76rem;
  color: #475569;
  margin-top: 0.15rem;
}

.member-info-panel {
  display: flex;
  justify-content: space-between;
  background: #F8FAFC;
  border: 1px solid #E2E8F0;
  border-radius: 10px;
  padding: 0.75rem 1rem;
  margin-bottom: 1rem;
  gap: 1rem;
}

.info-label {
  font-size: 0.68rem;
  font-weight: 800;
  color: #94A3B8;
  letter-spacing: 0.04em;
  text-transform: uppercase;
}

.info-name {
  font-size: 1rem;
  font-weight: 800;
  color: #0F172A;
  margin-top: 0.1rem;
}

.info-sub {
  font-size: 0.75rem;
  color: #475569;
  margin-top: 0.1rem;
}

.badge-accent {
  background: #FEF3C7;
  color: #92400E;
  font-weight: 800;
  padding: 1px 6px;
  border-radius: 4px;
  font-size: 0.72rem;
  font-family: var(--font-mono);
}

.invoice-items-table {
  width: 100%;
  border-collapse: collapse;
  margin-bottom: 1rem;
  font-size: 0.8rem;
}

.invoice-items-table th {
  background: #F1F5F9;
  color: #475569;
  font-weight: 800;
  text-transform: uppercase;
  font-size: 0.7rem;
  letter-spacing: 0.03em;
  padding: 0.5rem 0.65rem;
  border-top: 1px solid #CBD5E1;
  border-bottom: 1px solid #CBD5E1;
}

.invoice-items-table td {
  padding: 0.6rem 0.65rem;
  border-bottom: 1px solid #E2E8F0;
  vertical-align: middle;
}

.text-center { text-align: center; }
.text-right { text-align: right; }
.text-success { color: #059669; }

.invoice-bottom-grid {
  display: grid;
  grid-template-columns: 1.1fr 1fr;
  gap: 1.25rem;
  align-items: flex-start;
  margin-bottom: 1.25rem;
}

.terms-title {
  font-size: 0.72rem;
  font-weight: 800;
  color: #475569;
  margin-bottom: 0.25rem;
}

.terms-list {
  margin: 0;
  padding-left: 1rem;
  font-size: 0.7rem;
  color: #64748B;
  line-height: 1.35;
}

.summary-table-column {
  background: #F8FAFC;
  border: 1px solid #E2E8F0;
  border-radius: 10px;
  padding: 0.75rem 1rem;
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
}

.summary-line {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 0.78rem;
  color: #475569;
}

.grand-total-line {
  border-top: 2px solid #CBD5E1;
  border-bottom: 2px solid #CBD5E1;
  padding: 0.4rem 0;
  font-size: 0.95rem;
  font-weight: 900;
  color: #0F172A;
}

.due-line {
  color: #DC2626;
  font-weight: 800;
  background: #FEF2F2;
  padding: 0.25rem 0.4rem;
  border-radius: 4px;
}

.invoice-signature-row {
  display: flex;
  justify-content: space-between;
  align-items: flex-end;
  border-top: 1px dashed #CBD5E1;
  padding-top: 1rem;
  margin-top: 0.5rem;
}

.signatory-box {
  text-align: center;
  min-width: 180px;
}

.sign-line {
  width: 100%;
  border-bottom: 1px solid #94A3B8;
  margin-bottom: 0.35rem;
}

.sign-text {
  font-size: 0.75rem;
  font-weight: 800;
  color: #0F172A;
}

.sign-sub {
  font-size: 0.68rem;
  color: #64748B;
}

/* ========================================================= */
/* PRINT MEDIA QUERY FOR A4 HALF (A5) INVOICE                */
/* ========================================================= */
@media print {
  @page {
    size: A5 landscape;
    margin: 8mm;
  }

  body {
    background: #fff !important;
    color: #000 !important;
    padding: 0 !important;
    margin: 0 !important;
  }

  .hide-on-print,
  .app-sidebar,
  .app-header,
  .bottom-nav {
    display: none !important;
  }

  .main-content, .page-content {
    margin: 0 !important;
    padding: 0 !important;
  }

  .a4-half-invoice-wrapper {
    box-shadow: none !important;
    border: none !important;
    padding: 0 !important;
    max-width: 100% !important;
    width: 100% !important;
  }

  .invoice-items-table th {
    background: #F1F5F9 !important;
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
  }

  .member-info-panel,
  .summary-table-column {
    background: #F8FAFC !important;
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
  }

  .invoice-type-pill {
    background: #000 !important;
    color: #fff !important;
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
  }
}
</style>
