<!-- Official Staff Salary Payslip Printable & Downloadable View -->
<?php
$db = getDB();
$payslipId = intval($_GET['id'] ?? 0);
$payslipNo = trim($_GET['no'] ?? '');

if ($payslipId > 0) {
    $stmt = $db->prepare("
        SELECT p.*, s.name as staff_name, s.role as staff_role, s.phone as staff_phone, s.biometric_id, s.joining_date,
               sh.name as shift_name, sh.start_time as shift_start, sh.end_time as shift_end
        FROM payroll p
        JOIN staff s ON p.staff_id = s.id
        LEFT JOIN staff_shifts sh ON s.shift_id = sh.id
        WHERE p.id = ?
    ");
    $stmt->execute([$payslipId]);
} elseif (!empty($payslipNo)) {
    $stmt = $db->prepare("
        SELECT p.*, s.name as staff_name, s.role as staff_role, s.phone as staff_phone, s.biometric_id, s.joining_date,
               sh.name as shift_name, sh.start_time as shift_start, sh.end_time as shift_end
        FROM payroll p
        JOIN staff s ON p.staff_id = s.id
        LEFT JOIN staff_shifts sh ON s.shift_id = sh.id
        WHERE p.payslip_no = ?
    ");
    $stmt->execute([$payslipNo]);
} else {
    // Default to the latest payslip if no ID provided
    $stmt = $db->query("
        SELECT p.*, s.name as staff_name, s.role as staff_role, s.phone as staff_phone, s.biometric_id, s.joining_date,
               sh.name as shift_name, sh.start_time as shift_start, sh.end_time as shift_end
        FROM payroll p
        JOIN staff s ON p.staff_id = s.id
        LEFT JOIN staff_shifts sh ON s.shift_id = sh.id
        ORDER BY p.id DESC LIMIT 1
    ");
}

$pay = $stmt->fetch();

$gymName = getSetting('gym_name', 'THE CLUB 777®');
$gymPhone = getSetting('gym_phone', '8053576777, 8053570777, 9416528777');
$gymAddress = getSetting('gym_address', 'Behind Shehnai Garden, Near Railway Station, Jhajjar - 124103 (Haryana)');
$currency = getSetting('currency_symbol', '₹');

if (!$pay) {
    echo '<div class="page-content"><div class="card" style="text-align:center; padding:3rem;"><h2>⚠️ Payslip Not Found</h2><p>The requested staff salary payslip record does not exist.</p><a href="index.php?page=staff" class="btn btn-primary" style="margin-top:1rem;">← Return to Staff & Payroll</a></div></div>';
    return;
}

$grossEarnings = (float)$pay['basic_salary'] + (float)$pay['allowances'] + (float)$pay['commission'];
$totalDeductions = (float)$pay['deductions'] + (float)$pay['advance_paid'];
$netSalary = (float)$pay['net_salary'];

$cleanPhone = preg_replace('/[^0-9]/', '', $pay['staff_phone']);
if (strlen($cleanPhone) === 10) $cleanPhone = '91' . $cleanPhone;

$payPeriodFormatted = date('F Y', strtotime($pay['pay_period'] . '-01'));
$payslipWaMsg = "🧾 Official Salary Payslip - {$gymName}\n\nDear {$pay['staff_name']},\nYour salary for {$payPeriodFormatted} has been processed successfully!\n\n📋 Payslip No: {$pay['payslip_no']}\n💼 Designation: {$pay['staff_role']}\n💵 Basic Salary: {$currency}" . number_format($pay['basic_salary'], 2) . "\n➕ Allowances & Commission: {$currency}" . number_format($pay['allowances'] + $pay['commission'], 2) . "\n➖ Deductions & Advance: {$currency}" . number_format($totalDeductions, 2) . "\n\n💰 NET TAKE-HOME PAY: {$currency}" . number_format($netSalary, 2) . "\n📅 Paid On: " . date('d M Y', strtotime($pay['payment_date'])) . "\n\nThank you for your dedicated service at {$gymName}!";
$payslipWaUrl = "https://wa.me/{$cleanPhone}?text=" . urlencode($payslipWaMsg);
?>

<div class="page-content" style="max-width:850px; margin:0 auto;">
  
  <!-- Action Toolbar (Hidden during Print) -->
  <div class="no-print" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:0.75rem; margin-bottom:1.5rem;">
    <a href="index.php?page=staff" class="btn btn-outline" style="font-weight:700;">
      ← Back to Staff &amp; Payroll
    </a>

    <div style="display:flex; gap:0.5rem; flex-wrap:wrap;">
      <a href="<?= $payslipWaUrl ?>" target="_blank" class="btn btn-sm" style="background:#25D366; color:#FFFFFF; font-weight:800; border:none; text-decoration:none; padding:0.5rem 1rem; border-radius:var(--radius-md); box-shadow:0 2px 8px rgba(37,211,102,0.3);">
        💬 Send Payslip on WhatsApp
      </a>
      <button class="btn btn-primary" onclick="window.print()" style="font-weight:800; padding:0.5rem 1.25rem; box-shadow:0 4px 12px rgba(37,99,235,0.3);">
        🖨️ Print / Download PDF
      </button>
    </div>
  </div>

  <!-- Official Printable Payslip Card -->
  <div class="card payslip-paper" style="background:#FFFFFF; border:2px solid #E2E8F0; border-radius:16px; padding:2.5rem; box-shadow:var(--shadow-card); position:relative;">
    
    <!-- Top Header -->
    <div style="display:flex; justify-content:space-between; align-items:flex-start; border-bottom:2px solid #0F172A; padding-bottom:1.25rem; margin-bottom:1.5rem;">
      <div>
        <div style="display:flex; align-items:center; gap:0.5rem; font-size:1.4rem; font-weight:900; color:var(--text-primary); letter-spacing:-0.02em;">
          <span style="color:var(--primary);">🏋️</span>
          <span><?= htmlspecialchars($gymName) ?></span>
        </div>
        <div style="font-size:0.82rem; color:var(--text-secondary); margin-top:0.2rem;"><?= htmlspecialchars($gymAddress) ?></div>
        <div style="font-size:0.82rem; color:var(--text-muted);">📞 Phone: <?= htmlspecialchars($gymPhone) ?> • Jhajjar, Haryana</div>
      </div>

      <div style="text-align:right;">
        <div style="display:inline-block; background:#ECFDF5; color:#059669; border:1px solid #A7F3D0; font-size:0.8rem; font-weight:800; padding:0.35rem 0.85rem; border-radius:99px; text-transform:uppercase; letter-spacing:0.05em; margin-bottom:0.4rem;">
          ✓ SALARY PAID
        </div>
        <div style="font-family:var(--font-mono); font-weight:800; font-size:0.95rem; color:var(--primary);">
          <?= htmlspecialchars($pay['payslip_no']) ?>
        </div>
        <div style="font-size:0.78rem; color:var(--text-muted); margin-top:0.15rem;">
          Date: <strong><?= date('d M Y', strtotime($pay['payment_date'])) ?></strong>
        </div>
      </div>
    </div>

    <!-- Payslip Title Banner -->
    <div style="text-align:center; background:#F8FAFC; border:1px solid #E2E8F0; padding:0.6rem; border-radius:8px; margin-bottom:1.5rem;">
      <h2 style="font-size:1.15rem; font-weight:800; color:var(--text-primary); margin:0; text-transform:uppercase; letter-spacing:0.05em;">
        SALARY PAYSLIP FOR THE MONTH OF <?= strtoupper($payPeriodFormatted) ?>
      </h2>
    </div>

    <!-- Employee Information Grid -->
    <div style="display:grid; grid-template-columns:1fr 1fr; gap:1.25rem; background:#F8FAFC; border:1px solid var(--border-color); border-radius:10px; padding:1.25rem; margin-bottom:1.5rem; font-size:0.88rem;">
      <div style="display:flex; flex-direction:column; gap:0.5rem;">
        <div>👤 <strong>Employee Name:</strong> <span style="font-weight:700; color:var(--text-primary);"><?= htmlspecialchars($pay['staff_name']) ?></span></div>
        <div>💼 <strong>Designation / Role:</strong> <?= htmlspecialchars($pay['staff_role']) ?></div>
        <div>📱 <strong>Mobile Number:</strong> <?= htmlspecialchars($pay['staff_phone']) ?></div>
      </div>

      <div style="display:flex; flex-direction:column; gap:0.5rem;">
        <div>🆔 <strong>Employee / Bio ID:</strong> <span style="font-family:var(--font-mono); font-weight:800; color:var(--primary);"><?= htmlspecialchars($pay['biometric_id'] ?: 'STF-NA') ?></span></div>
        <div>⏱️ <strong>Assigned Shift:</strong> <?= htmlspecialchars($pay['shift_name'] ?: 'Morning Shift') ?></div>
        <div>📅 <strong>Payment Mode:</strong> Bank Transfer / Cash UPI</div>
      </div>
    </div>

    <!-- Earnings & Deductions Dual Table -->
    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:1.5rem; margin-bottom:1.5rem;">
      
      <!-- Earnings Section -->
      <div style="border:1px solid #E2E8F0; border-radius:10px; overflow:hidden;">
        <div style="background:#F1F5F9; padding:0.6rem 0.85rem; font-weight:800; font-size:0.85rem; color:#1E293B; border-bottom:1px solid #E2E8F0; text-transform:uppercase; letter-spacing:0.04em;">
          💰 Earnings Breakdown
        </div>
        <table style="width:100%; border-collapse:collapse; font-size:0.85rem;">
          <tbody>
            <tr style="border-bottom:1px solid #F1F5F9;">
              <td style="padding:0.65rem 0.85rem; color:var(--text-secondary);">Basic Monthly Salary</td>
              <td style="padding:0.65rem 0.85rem; text-align:right; font-weight:700;"><?= $currency ?><?= number_format($pay['basic_salary'], 2) ?></td>
            </tr>
            <tr style="border-bottom:1px solid #F1F5F9;">
              <td style="padding:0.65rem 0.85rem; color:var(--text-secondary);">Special Allowances / HRA</td>
              <td style="padding:0.65rem 0.85rem; text-align:right; font-weight:700;"><?= $currency ?><?= number_format($pay['allowances'], 2) ?></td>
            </tr>
            <tr style="border-bottom:1px solid #F1F5F9;">
              <td style="padding:0.65rem 0.85rem; color:var(--text-secondary);">Performance &amp; PT Commission</td>
              <td style="padding:0.65rem 0.85rem; text-align:right; font-weight:700;"><?= $currency ?><?= number_format($pay['commission'], 2) ?></td>
            </tr>
            <tr style="background:#F8FAFC; font-weight:800; border-top:1px solid #CBD5E1;">
              <td style="padding:0.75rem 0.85rem; color:var(--text-primary);">GROSS EARNINGS (A)</td>
              <td style="padding:0.75rem 0.85rem; text-align:right; color:#2563EB;"><?= $currency ?><?= number_format($grossEarnings, 2) ?></td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Deductions Section -->
      <div style="border:1px solid #E2E8F0; border-radius:10px; overflow:hidden;">
        <div style="background:#F1F5F9; padding:0.6rem 0.85rem; font-weight:800; font-size:0.85rem; color:#1E293B; border-bottom:1px solid #E2E8F0; text-transform:uppercase; letter-spacing:0.04em;">
          ➖ Deductions Breakdown
        </div>
        <table style="width:100%; border-collapse:collapse; font-size:0.85rem;">
          <tbody>
            <tr style="border-bottom:1px solid #F1F5F9;">
              <td style="padding:0.65rem 0.85rem; color:var(--text-secondary);">Salary Advance Adjusted</td>
              <td style="padding:0.65rem 0.85rem; text-align:right; font-weight:700;"><?= $currency ?><?= number_format($pay['advance_paid'], 2) ?></td>
            </tr>
            <tr style="border-bottom:1px solid #F1F5F9;">
              <td style="padding:0.65rem 0.85rem; color:var(--text-secondary);">Unpaid Leaves / Late Deductions</td>
              <td style="padding:0.65rem 0.85rem; text-align:right; font-weight:700;"><?= $currency ?><?= number_format($pay['deductions'], 2) ?></td>
            </tr>
            <tr style="border-bottom:1px solid #F1F5F9;">
              <td style="padding:0.65rem 0.85rem; color:var(--text-secondary);">Professional Tax / TDS</td>
              <td style="padding:0.65rem 0.85rem; text-align:right; font-weight:700;"><?= $currency ?>0.00</td>
            </tr>
            <tr style="background:#F8FAFC; font-weight:800; border-top:1px solid #CBD5E1;">
              <td style="padding:0.75rem 0.85rem; color:var(--text-primary);">TOTAL DEDUCTIONS (B)</td>
              <td style="padding:0.75rem 0.85rem; text-align:right; color:#DC2626;"><?= $currency ?><?= number_format($totalDeductions, 2) ?></td>
            </tr>
          </tbody>
        </table>
      </div>

    </div>

    <!-- NET TAKE-HOME SALARY HIGHLIGHT BOX -->
    <div style="background:linear-gradient(135deg, #ECFDF5 0%, #D1FAE5 100%); border:2px solid #10B981; border-radius:12px; padding:1.25rem 1.5rem; display:flex; justify-content:space-between; align-items:center; margin-bottom:2rem;">
      <div>
        <div style="font-size:0.8rem; font-weight:800; color:#065F46; text-transform:uppercase; letter-spacing:0.06em;">NET SALARY PAYABLE (A - B)</div>
        <div style="font-size:0.8rem; color:#047857; margin-top:0.2rem;">Credited directly to employee registered bank / UPI</div>
      </div>
      <div style="font-size:2.1rem; font-weight:900; color:#065F46; letter-spacing:-0.03em;">
        <?= $currency ?><?= number_format($netSalary, 2) ?>
      </div>
    </div>

    <!-- Signatures & Authorization Area -->
    <div style="display:flex; justify-content:space-between; align-items:flex-end; margin-top:3rem; padding-top:1.5rem; border-top:1px dashed #CBD5E1; font-size:0.82rem; color:var(--text-secondary);">
      <div style="text-align:center;">
        <div style="border-bottom:1px solid #94A3B8; width:180px; margin-bottom:0.4rem;"></div>
        <div>Employee Signature</div>
      </div>

      <div style="text-align:center;">
        <div style="border-bottom:1px solid #94A3B8; width:180px; margin-bottom:0.4rem;"></div>
        <div><strong>Authorized Signatory / Manager</strong></div>
        <div style="font-size:0.75rem; color:var(--text-muted);"><?= htmlspecialchars($gymName) ?></div>
      </div>
    </div>

    <div style="text-align:center; font-size:0.72rem; color:var(--text-muted); margin-top:2rem;">
      This is a computer-generated salary payslip document issued by THE CLUB 777® Enterprise Gym Management System.
    </div>
  </div>

</div>

<style>
@media print {
  body {
    background: #FFFFFF !important;
  }
  .app-header, .app-sidebar, .no-print, #toastContainer {
    display: none !important;
  }
  .app-main {
    height: auto !important;
    overflow: visible !important;
  }
  .page-content {
    padding: 0 !important;
    max-width: 100% !important;
  }
  .payslip-paper {
    border: none !important;
    box-shadow: none !important;
    padding: 1rem !important;
  }
}
</style>
