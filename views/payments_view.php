<!-- Financial Payments, Sales Ledger & Wrong Billing Correction / Void Management View with Pending Dues Engine -->
<?php
$db = getDB();
$sales = $db->query("
    SELECT s.*, m.name as member_name, m.member_code, m.phone as member_phone 
    FROM sales s 
    LEFT JOIN members m ON s.member_id = m.id 
    ORDER BY s.created_at DESC
")->fetchAll();

$allMembers = $db->query("SELECT id, name, member_code FROM members ORDER BY name ASC")->fetchAll();

// Total Net Revenue (excluding voided / cancelled wrong bills)
$totalRev = $db->query("SELECT IFNULL(SUM(paid_amount), 0) FROM sales WHERE payment_status != 'voided' AND payment_status != 'cancelled'")->fetchColumn();
$totalTax = $db->query("SELECT IFNULL(SUM(tax), 0) FROM sales WHERE payment_status != 'voided' AND payment_status != 'cancelled'")->fetchColumn();
$totalDue = $db->query("SELECT IFNULL(SUM(due_amount), 0) FROM sales WHERE payment_status != 'voided' AND payment_status != 'cancelled' AND due_amount > 0")->fetchColumn();
$dueCount = $db->query("SELECT COUNT(*) FROM sales WHERE payment_status != 'voided' AND payment_status != 'cancelled' AND due_amount > 0")->fetchColumn();
$voidedCount = $db->query("SELECT COUNT(*) FROM sales WHERE payment_status = 'voided' OR payment_status = 'cancelled'")->fetchColumn();
$voidedAmount = $db->query("SELECT IFNULL(SUM(total), 0) FROM sales WHERE payment_status = 'voided' OR payment_status = 'cancelled'")->fetchColumn();

$currency = getSetting('currency_symbol', '₹');
?>

<div class="page-content">
  <!-- Header & Actions -->
  <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1.25rem; margin-bottom:1.5rem;">
    <div>
      <h1 style="font-size:1.65rem; font-weight:900; color:var(--text-primary); letter-spacing:-0.03em; display:flex; align-items:center; gap:0.65rem;">
        <span style="background:#EFF6FF; color:var(--primary); width:42px; height:42px; border-radius:12px; display:inline-flex; align-items:center; justify-content:center; font-size:1.4rem; box-shadow:0 2px 8px var(--primary-glow);">🧾</span>
        <span>Payments, Invoices &amp; Billing Ledger</span>
      </h1>
      <p style="font-size:0.85rem; color:var(--text-secondary); margin-top:0.25rem;">Audit transaction invoices, collect outstanding balances, reprint thermal receipts &amp; correct wrong bills</p>
    </div>

    <div style="display:flex; gap:0.65rem; flex-wrap:wrap;">
      <button class="btn btn-outline" style="border-radius:var(--radius-md); font-weight:700; background:var(--bg-surface);" onclick="exportPaymentsCsv()">
        📥 Export CSV Ledger
      </button>

      <a href="index.php?page=pos" class="btn btn-primary" style="border-radius:var(--radius-md); font-weight:800; box-shadow:0 4px 12px var(--primary-glow);">
        ⚡ Open Touch POS (F1)
      </a>
    </div>
  </div>

  <!-- Executive Financial KPI Cards -->
  <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:1.25rem; margin-bottom:1.75rem;">
    <div class="card" style="border-left:4px solid var(--success); padding:1.25rem; border-radius:var(--radius-lg); box-shadow:var(--shadow-card);">
      <div style="font-size:0.75rem; font-weight:800; text-transform:uppercase; color:var(--text-muted); letter-spacing:0.05em;">Net Revenue Collected</div>
      <div style="font-size:1.85rem; font-weight:900; color:var(--success); margin-top:0.35rem; font-family:var(--font-heading);"><?= $currency ?><?= number_format($totalRev, 2) ?></div>
      <div style="font-size:0.78rem; color:var(--text-secondary); margin-top:0.2rem;">Collected POS &amp; renewal receipts</div>
    </div>

    <div class="card" style="border-left:4px solid #DC2626; padding:1.25rem; border-radius:var(--radius-lg); box-shadow:var(--shadow-card);">
      <div style="font-size:0.75rem; font-weight:800; text-transform:uppercase; color:var(--text-muted); letter-spacing:0.05em;">Outstanding Pending Dues</div>
      <div style="font-size:1.85rem; font-weight:900; color:#DC2626; margin-top:0.35rem; font-family:var(--font-heading);"><?= $currency ?><?= number_format($totalDue, 2) ?></div>
      <div style="font-size:0.78rem; color:var(--text-secondary); margin-top:0.2rem; font-weight:700;"><?= $dueCount ?> Pending Invoices</div>
    </div>

    <div class="card" style="border-left:4px solid var(--primary); padding:1.25rem; border-radius:var(--radius-lg); box-shadow:var(--shadow-card);">
      <div style="font-size:0.75rem; font-weight:800; text-transform:uppercase; color:var(--text-muted); letter-spacing:0.05em;">Total Transactions</div>
      <div style="font-size:1.85rem; font-weight:900; color:var(--primary); margin-top:0.35rem; font-family:var(--font-heading);"><?= count($sales) ?> Invoices</div>
      <div style="font-size:0.78rem; color:var(--text-secondary); margin-top:0.2rem;">Lifetime billing ledger entries</div>
    </div>

    <div class="card" style="border-left:4px solid var(--danger); padding:1.25rem; border-radius:var(--radius-lg); box-shadow:var(--shadow-card);">
      <div style="font-size:0.75rem; font-weight:800; text-transform:uppercase; color:var(--text-muted); letter-spacing:0.05em;">Void / Wrong Bills Reversed</div>
      <div style="font-size:1.85rem; font-weight:900; color:var(--danger); margin-top:0.35rem; font-family:var(--font-heading);"><?= $voidedCount ?> Bills</div>
      <div style="font-size:0.78rem; color:var(--text-secondary); margin-top:0.2rem;">(<?= $currency ?><?= number_format($voidedAmount, 2) ?> reversed)</div>
    </div>
  </div>

  <!-- Search & Filter Controls Bar -->
  <div class="card" style="padding:1rem 1.25rem; margin-bottom:1.5rem; border-radius:var(--radius-lg); box-shadow:var(--shadow-subtle);">
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem;">
      <div style="display:flex; gap:0.45rem; flex-wrap:wrap;" id="invoiceFilterGroup">
        <button class="invoice-filter-chip active" onclick="filterInvoices('all', this)">All Invoices (<?= count($sales) ?>)</button>
        <button class="invoice-filter-chip" onclick="filterInvoices('paid', this)">🟢 Paid (<?= count($sales) - $voidedCount - $dueCount ?>)</button>
        <button class="invoice-filter-chip" onclick="filterInvoices('due', this)" style="color:#DC2626;">🟡 Pending Dues (<?= $dueCount ?>)</button>
        <button class="invoice-filter-chip" onclick="filterInvoices('voided', this)" style="color:var(--danger);">🚫 Voided (<?= $voidedCount ?>)</button>
      </div>

      <div style="position:relative; min-width:260px;">
        <input type="text" id="invoiceSearchInput" class="form-control" placeholder="🔍 Search Invoice #, Member, UTR..." oninput="searchInvoicesTable(this.value)" style="padding-left:2.2rem; height:38px; border-radius:var(--radius-full); font-size:0.82rem;">
      </div>
    </div>
  </div>

  <!-- Invoices Ledger Table Card -->
  <div class="card" style="border-radius:var(--radius-lg); box-shadow:var(--shadow-card); padding:1.25rem;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;">
      <span style="font-size:1.15rem; font-weight:800; color:var(--text-primary);">🧾 Financial Transactions &amp; Receipts Ledger</span>
      <span style="font-size:0.8rem; color:var(--text-muted);" id="invoiceCountLabel">Showing <?= count($sales) ?> transactions</span>
    </div>

    <div class="table-wrapper">
      <table id="invoicesTable">
        <thead>
          <tr>
            <th>Invoice #</th>
            <th>Member Client</th>
            <th>Total Bill</th>
            <th>Paid Amount</th>
            <th>Pending Due</th>
            <th>Payment Mode</th>
            <th>Date &amp; Time</th>
            <th>Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($sales)): ?>
            <tr>
              <td colspan="9" style="text-align:center; padding:2rem; color:var(--text-muted);">
                No financial transactions recorded.
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($sales as $s): ?>
              <?php 
                $isVoided = ($s['payment_status'] === 'voided' || $s['payment_status'] === 'cancelled'); 
                $isDue = ($s['due_amount'] > 0 && !$isVoided);
              ?>
              <tr class="invoice-row" data-status="<?= $isVoided ? 'voided' : ($isDue ? 'due' : 'paid') ?>" data-invoice="<?= htmlspecialchars(strtolower($s['invoice_no'])) ?>" data-member="<?= htmlspecialchars(strtolower($s['member_name'] ?: '')) ?>" data-utr="<?= htmlspecialchars(strtolower($s['utr_ref'] ?: '')) ?>" style="<?= $isVoided ? 'background:rgba(239,68,68,0.04);' : ($isDue ? 'background:#FFFBEB;' : '') ?>">
                <td>
                  <strong style="color:var(--primary); font-family:var(--font-mono); font-size:0.92rem;"><?= htmlspecialchars($s['invoice_no']) ?></strong>
                </td>

                <td>
                  <?php if (!empty($s['member_id'])): ?>
                    <a href="index.php?page=member_profile&id=<?= $s['member_id'] ?>" style="color:var(--text-primary); text-decoration:none; font-weight:800; font-size:0.92rem;">
                      <?= htmlspecialchars($s['member_name']) ?>
                    </a>
                  <?php else: ?>
                    <strong style="color:var(--text-primary);"><?= htmlspecialchars($s['member_name'] ?: 'Walk-in Customer') ?></strong>
                  <?php endif; ?>
                  <div style="font-size:0.75rem; font-family:var(--font-mono); color:var(--text-muted);"><?= htmlspecialchars($s['member_code'] ?: 'N/A') ?></div>
                </td>

                <td class="font-mono">
                  <?php if ($isVoided): ?>
                    <span style="text-decoration:line-through; color:var(--text-muted);"><?= $currency ?><?= number_format($s['total'], 2) ?></span>
                  <?php else: ?>
                    <strong style="color:var(--text-primary); font-size:0.95rem;"><?= $currency ?><?= number_format($s['total'], 2) ?></strong>
                  <?php endif; ?>
                </td>

                <td class="font-mono">
                  <strong style="color:var(--success); font-size:0.95rem;"><?= $currency ?><?= number_format($s['paid_amount'], 2) ?></strong>
                </td>

                <td>
                  <?php if ($isDue): ?>
                    <strong style="color:#DC2626; background:#FEE2E2; padding:2px 6px; border-radius:4px; font-family:var(--font-mono); font-size:0.92rem;">
                      <?= $currency ?><?= number_format($s['due_amount'], 2) ?>
                    </strong>
                    <?php if (!empty($s['due_date'])): ?>
                      <?php $daysDiff = (int) ceil((strtotime($s['due_date']) - strtotime(date('Y-m-d'))) / 86400); ?>
                      <div style="font-size:0.72rem; margin-top:0.2rem; color:<?= $daysDiff < 0 ? '#DC2626' : '#92400E' ?>; font-weight:700;">
                        <?= $daysDiff < 0 ? "⚠️ Overdue by " . abs($daysDiff) . "d" : "📅 Promised: " . date('d M', strtotime($s['due_date'])) . " ({$daysDiff}d left)" ?>
                      </div>
                    <?php endif; ?>
                  <?php else: ?>
                    <span style="color:var(--text-muted); font-size:0.8rem;">₹0.00</span>
                  <?php endif; ?>
                </td>

                <td>
                  <span class="badge badge-info"><?= htmlspecialchars($s['payment_method']) ?></span>
                  <?php if ($s['utr_ref']): ?>
                    <div style="font-size:0.72rem; color:var(--text-muted); font-family:var(--font-mono); margin-top:0.15rem;">UTR: <?= htmlspecialchars($s['utr_ref']) ?></div>
                  <?php endif; ?>
                </td>

                <td style="font-size:0.82rem; color:var(--text-secondary); font-family:var(--font-mono);">
                  <?= date('d M Y, h:i A', strtotime($s['created_at'])) ?>
                </td>

                <td>
                  <?php if ($isVoided): ?>
                    <span class="badge badge-danger" style="font-weight:800;">🚫 VOIDED</span>
                    <?php if ($s['notes']): ?>
                      <div style="font-size:0.7rem; color:var(--danger); max-width:160px; margin-top:0.2rem;"><?= htmlspecialchars($s['notes']) ?></div>
                    <?php endif; ?>
                  <?php elseif ($isDue): ?>
                    <span class="badge badge-warning" style="font-weight:800; background:#F59E0B; color:#FFFFFF;">
                      🟡 DUE PENDING
                    </span>
                  <?php else: ?>
                    <span class="badge badge-success">● <?= strtoupper($s['payment_status']) ?></span>
                  <?php endif; ?>
                </td>

                <td>
                  <div style="display:flex; gap:0.35rem; flex-wrap:wrap;">
                    <a href="index.php?page=receipt&invoice_no=<?= urlencode($s['invoice_no']) ?>" class="btn btn-outline btn-sm" style="padding:0.25rem 0.5rem; font-size:0.75rem; font-weight:800;" title="View & Print Official A4 Half Fee Receipt">
                      🧾 A4 Receipt
                    </a>
                    <?php if (!empty($s['member_phone'])): ?>
                      <button class="btn btn-success btn-sm" style="padding:0.25rem 0.5rem; font-size:0.75rem; font-weight:800; background:#25D366; border-color:#25D366; color:#FFFFFF;" onclick="sendInvoiceWhatsApp('<?= addslashes($s['invoice_no']) ?>', '<?= addslashes($s['member_phone']) ?>', this)" title="Send Official PDF Receipt directly on WhatsApp">
                        💬 WhatsApp PDF
                      </button>
                    <?php endif; ?>
                    <?php if ($isDue): ?>
                      <button class="btn btn-success btn-sm" onclick="openCollectDueModal(<?= htmlspecialchars(json_encode($s)) ?>)" style="padding:0.25rem 0.5rem; font-size:0.75rem; font-weight:800;">
                        💵 Collect Due
                      </button>
                    <?php endif; ?>
                    <?php if (!$isVoided): ?>
                      <button class="btn btn-secondary btn-sm" onclick="openEditBillModal(<?= htmlspecialchars(json_encode($s)) ?>)" style="padding:0.25rem 0.45rem; font-size:0.75rem;" title="Correct Payment Mode / Member / UTR">
                        ✏️ Fix
                      </button>
                      <button class="btn btn-danger btn-sm" onclick="openVoidBillModal(<?= $s['id'] ?>, '<?= htmlspecialchars($s['invoice_no']) ?>', <?= $s['total'] ?>)" style="padding:0.25rem 0.45rem; font-size:0.75rem;" title="Void / Cancel Wrong Bill & Reverse">
                        🚫 Void
                      </button>
                    <?php else: ?>
                      <span style="font-size:0.75rem; color:var(--text-muted);">Reversed</span>
                    <?php endif; ?>
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

<!-- ========================================================= -->
<!-- MODALS SECTION                                            -->
<!-- ========================================================= -->

<!-- 1. COLLECT PENDING DUE MODAL -->
<div class="modal" id="collectDueModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.55); z-index:100; align-items:center; justify-content:center; backdrop-filter:blur(2px);">
  <div class="modal-overlay" style="position:absolute; inset:0;" onclick="closeModal('collectDueModal')"></div>
  <div class="card" style="position:relative; z-index:10; background:#fff; width:92%; max-width:440px; border-radius:18px; padding:1.5rem; border-top:5px solid #10B981; box-shadow:var(--shadow-modal);">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.25rem; border-bottom:1px solid var(--border-color); padding-bottom:0.75rem;">
      <span style="font-size:1.15rem; font-weight:800; color:#065F46;">💵 Collect Pending Due Payment</span>
      <button style="background:none; border:none; font-size:1.2rem; cursor:pointer;" onclick="closeModal('collectDueModal')">✕</button>
    </div>

    <form onsubmit="event.preventDefault(); submitCollectDue();">
      <input type="hidden" id="cdSaleId">

      <div style="background:#ECFDF5; border:1px solid #A7F3D0; padding:0.85rem; border-radius:8px; margin-bottom:1rem; font-size:0.85rem;">
        <div>Invoice: <strong id="cdInvoiceNo" style="font-family:var(--font-mono); color:var(--primary);"></strong></div>
        <div>Member: <strong id="cdMemberName"></strong></div>
        <div>Outstanding Due: <strong id="cdDueAmountDisplay" style="color:#DC2626; font-size:1.15rem; font-family:var(--font-mono);"></strong></div>
      </div>

      <div class="form-group">
        <label class="form-label">Payment Amount Collected Now (<?= $currency ?>) *</label>
        <input type="number" step="0.01" id="cdCollectAmount" class="form-control font-mono" style="font-size:1.2rem; font-weight:800; color:#065F46;" required>
      </div>

      <div class="form-group">
        <label class="form-label">Payment Mode</label>
        <select id="cdPaymentMode" class="form-control">
          <option value="Cash">Cash</option>
          <option value="UPI">UPI / QR Code</option>
          <option value="Card">Credit / Debit Card</option>
          <option value="Bank Transfer">Bank Transfer / NEFT</option>
        </select>
      </div>

      <div class="form-group">
        <label class="form-label">UTR / Transaction Reference (Optional)</label>
        <input type="text" id="cdUtrRef" class="form-control font-mono" placeholder="e.g. UPI Ref 88472910">
      </div>

      <div style="display:flex; gap:0.75rem; margin-top:1.5rem;">
        <button type="button" class="btn btn-secondary btn-block" onclick="closeModal('collectDueModal')">Cancel</button>
        <button type="submit" class="btn btn-primary btn-block" style="font-weight:800;">✓ RECORD PAYMENT</button>
      </div>
    </form>
  </div>
</div>

<!-- 2. EDIT BILL MODAL -->
<div class="modal" id="editBillModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.55); z-index:100; align-items:center; justify-content:center; backdrop-filter:blur(2px);">
  <div class="modal-overlay" style="position:absolute; inset:0;" onclick="closeModal('editBillModal')"></div>
  <div class="card" style="position:relative; z-index:10; background:#fff; width:92%; max-width:480px; border-radius:18px; padding:1.5rem; box-shadow:var(--shadow-modal);">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.25rem; border-bottom:1px solid var(--border-color); padding-bottom:0.75rem;">
      <span style="font-size:1.15rem; font-weight:800; color:var(--text-primary);">✏️ Fix Invoice Details</span>
      <button style="background:none; border:none; font-size:1.2rem; cursor:pointer;" onclick="closeModal('editBillModal')">✕</button>
    </div>

    <form onsubmit="event.preventDefault(); submitEditBill();">
      <input type="hidden" id="editSaleId">

      <div class="form-group">
        <label class="form-label">Invoice Number</label>
        <input type="text" id="editInvoiceNo" class="form-control font-mono" readonly style="background:#F1F5F9; font-weight:800;">
      </div>

      <div class="form-group">
        <label class="form-label">Assign to Member</label>
        <select id="editMemberSelect" class="form-control">
          <option value="">-- Walk-in / Unassigned --</option>
          <?php foreach ($allMembers as $m): ?>
            <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['name']) ?> (<?= $m['member_code'] ?>)</option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-group">
        <label class="form-label">Payment Mode</label>
        <select id="editPaymentMode" class="form-control">
          <option value="Cash">Cash</option>
          <option value="UPI">UPI</option>
          <option value="Card">Card</option>
          <option value="Split">Split</option>
          <option value="Bank Transfer">Bank Transfer</option>
        </select>
      </div>

      <div class="form-group">
        <label class="form-label">UTR / Payment Reference</label>
        <input type="text" id="editUtrRef" class="form-control font-mono" placeholder="e.g. UPI transaction ID">
      </div>

      <div class="form-group">
        <label class="form-label">Promised Due Date (if balance pending)</label>
        <input type="date" id="editDueDate" class="form-control font-mono">
      </div>

      <div class="form-group">
        <label class="form-label">Correction Note / Reason</label>
        <input type="text" id="editBillNotes" class="form-control" placeholder="e.g. Changed payment mode from Cash to UPI">
      </div>

      <div style="display:flex; gap:0.75rem; margin-top:1.5rem;">
        <button type="button" class="btn btn-secondary btn-block" onclick="closeModal('editBillModal')">Cancel</button>
        <button type="submit" class="btn btn-primary btn-block">✓ SAVE CORRECTIONS</button>
      </div>
    </form>
  </div>
</div>

<!-- 3. VOID / CANCEL WRONG BILL MODAL -->
<div class="modal" id="voidBillModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.55); z-index:100; align-items:center; justify-content:center; backdrop-filter:blur(2px);">
  <div class="modal-overlay" style="position:absolute; inset:0;" onclick="closeModal('voidBillModal')"></div>
  <div class="card" style="position:relative; z-index:10; background:#fff; width:92%; max-width:460px; border-radius:18px; padding:1.5rem; border-top:5px solid var(--danger); box-shadow:var(--shadow-modal);">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.25rem; border-bottom:1px solid var(--border-color); padding-bottom:0.75rem;">
      <span style="font-size:1.15rem; font-weight:800; color:var(--danger);">🚫 Void / Cancel Wrong Bill</span>
      <button style="background:none; border:none; font-size:1.2rem; cursor:pointer;" onclick="closeModal('voidBillModal')">✕</button>
    </div>

    <form onsubmit="event.preventDefault(); submitVoidBill();">
      <input type="hidden" id="voidSaleId">

      <div style="background:var(--danger-bg); border:1px solid var(--danger-border); padding:0.85rem; border-radius:8px; margin-bottom:1rem; font-size:0.82rem; color:#991B1B;">
        ⚠️ <strong>Warning:</strong> Voiding invoice <strong id="voidInvoiceNo"></strong> (Amount: <span id="voidAmountDisplay"></span>) will cancel any generated subscription, restore product stock, and record a reversal entry.
      </div>

      <div class="form-group">
        <label class="form-label">Select Wrong Billing Reason *</label>
        <select id="voidReasonSelect" class="form-control">
          <option value="Wrong Member Selected in POS">Wrong Member Selected in POS</option>
          <option value="Incorrect Plan or Price Entered">Incorrect Plan or Price Entered</option>
          <option value="Duplicate Bill Created by Mistake">Duplicate Bill Created by Mistake</option>
          <option value="Customer Dispute / Cancelled Transaction">Customer Dispute / Cancelled Transaction</option>
          <option value="Payment Mode Error / Accidental Punch">Payment Mode Error / Accidental Punch</option>
        </select>
      </div>

      <div class="form-group">
        <label class="form-label">Additional Notes / Remarks</label>
        <input type="text" id="voidCustomReason" class="form-control" placeholder="e.g. Cash was not received, customer walked out">
      </div>

      <div style="display:flex; gap:0.75rem; margin-top:1.5rem;">
        <button type="button" class="btn btn-secondary btn-block" onclick="closeModal('voidBillModal')">Cancel</button>
        <button type="submit" class="btn btn-danger btn-block">🚫 CONFIRM VOID &amp; REVERSE</button>
      </div>
    </form>
  </div>
</div>

<style>
.invoice-filter-chip {
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
.invoice-filter-chip:hover {
  background: var(--bg-surface-hover);
  color: var(--primary);
  border-color: var(--primary-border);
}
.invoice-filter-chip.active {
  background: var(--primary);
  color: #fff;
  border-color: var(--primary);
  box-shadow: 0 2px 6px var(--primary-glow);
}
</style>

<script>
let currentInvoiceStatus = 'all';

function filterInvoices(status, btn) {
  currentInvoiceStatus = status;
  document.querySelectorAll('#invoiceFilterGroup .invoice-filter-chip').forEach(b => b.classList.remove('active'));
  if (btn) btn.classList.add('active');
  applyInvoiceFilters();
}

let invoiceFilterTimer = null;
function searchInvoicesTable(query) {
  clearTimeout(invoiceFilterTimer);
  invoiceFilterTimer = setTimeout(() => {
    requestAnimationFrame(applyInvoiceFilters);
  }, 60);
}

function applyInvoiceFilters() {
  const query = (document.getElementById('invoiceSearchInput').value || '').toLowerCase().trim();
  const rows = document.querySelectorAll('#invoicesTable tbody tr');
  let visibleCount = 0;

  for (let i = 0; i < rows.length; i++) {
    const row = rows[i];
    const s = row.getAttribute('data-status') || '';
    const invoice = row.getAttribute('data-invoice') || '';
    const member = row.getAttribute('data-member') || '';
    const utr = row.getAttribute('data-utr') || '';

    const matchesStatus = (currentInvoiceStatus === 'all') || (s === currentInvoiceStatus);
    const matchesSearch = !query || invoice.includes(query) || member.includes(query) || utr.includes(query);

    if (matchesStatus && matchesSearch) {
      row.style.display = '';
      visibleCount++;
    } else {
      row.style.display = 'none';
    }
  }

  const countLabel = document.getElementById('invoiceCountLabel');
  if (countLabel) countLabel.innerText = `Showing ${visibleCount} transactions`;
}

function openCollectDueModal(sale) {
  document.getElementById('cdSaleId').value = sale.id;
  document.getElementById('cdInvoiceNo').innerText = sale.invoice_no;
  document.getElementById('cdMemberName').innerText = sale.member_name || 'Walk-in';
  document.getElementById('cdDueAmountDisplay').innerText = '<?= $currency ?>' + parseFloat(sale.due_amount).toFixed(2);
  document.getElementById('cdCollectAmount').value = parseFloat(sale.due_amount).toFixed(2);
  document.getElementById('cdUtrRef').value = '';
  openModal('collectDueModal');
}

function submitCollectDue() {
  const payload = {
    sale_id: document.getElementById('cdSaleId').value,
    amount: document.getElementById('cdCollectAmount').value,
    payment_method: document.getElementById('cdPaymentMode').value,
    utr_ref: document.getElementById('cdUtrRef').value.trim()
  };

  fetch('api/pos.php?action=collect_due', {
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
      showToast(res.message, 'success');
      closeModal('collectDueModal');
      setTimeout(() => location.reload(), 1000);
    } else {
      showToast(res.message || 'Collection failed', 'danger');
    }
  })
  .catch(err => {
    showToast('Network error during due collection', 'danger');
  });
}

function openEditBillModal(sale) {
  document.getElementById('editSaleId').value = sale.id;
  document.getElementById('editInvoiceNo').value = sale.invoice_no;
  document.getElementById('editMemberSelect').value = sale.member_id || '';
  document.getElementById('editPaymentMode').value = sale.payment_method || 'Cash';
  document.getElementById('editUtrRef').value = sale.utr_ref || '';
  document.getElementById('editDueDate').value = sale.due_date || '';
  document.getElementById('editBillNotes').value = sale.notes || '';
  openModal('editBillModal');
}

function submitEditBill() {
  const saleId = document.getElementById('editSaleId').value;
  const memberId = document.getElementById('editMemberSelect').value;
  const method = document.getElementById('editPaymentMode').value;
  const utr = document.getElementById('editUtrRef').value.trim();
  const dueDate = document.getElementById('editDueDate').value;
  const notes = document.getElementById('editBillNotes').value.trim();

  fetch('api/pos.php?action=edit_invoice', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      sale_id: saleId,
      member_id: memberId || null,
      payment_method: method,
      utr_ref: utr,
      due_date: dueDate || null,
      notes: notes
    })
  })
  .then(res => res.text())
  .then(text => {
    try { return JSON.parse(text); } catch(e) { return { success: false, message: text }; }
  })
  .then(res => {
    if (res.success) {
      showToast('Invoice details updated successfully!', 'success');
      closeModal('editBillModal');
      setTimeout(() => location.reload(), 1000);
    } else {
      showToast(res.message || 'Update failed', 'danger');
    }
  })
  .catch(err => {
    showToast('Network error during invoice edit', 'danger');
  });
}

function openVoidBillModal(saleId, invoiceNo, amount) {
  document.getElementById('voidSaleId').value = saleId;
  document.getElementById('voidInvoiceNo').innerText = invoiceNo;
  document.getElementById('voidAmountDisplay').innerText = '<?= $currency ?>' + parseFloat(amount).toFixed(2);
  document.getElementById('voidCustomReason').value = '';
  openModal('voidBillModal');
}

function submitVoidBill() {
  const saleId = document.getElementById('voidSaleId').value;
  const reasonSelect = document.getElementById('voidReasonSelect').value;
  const customReason = document.getElementById('voidCustomReason').value.trim();
  const finalReason = customReason ? `${reasonSelect}: ${customReason}` : reasonSelect;

  fetch('api/pos.php?action=void_invoice', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      sale_id: saleId,
      reason: finalReason
    })
  })
  .then(res => res.text())
  .then(text => {
    try { return JSON.parse(text); } catch(e) { return { success: false, message: text }; }
  })
  .then(res => {
    if (res.success) {
      showToast(res.message, 'success');
      closeModal('voidBillModal');
      setTimeout(() => location.reload(), 1000);
    } else {
      showToast(res.message || 'Failed to void invoice', 'danger');
    }
  })
  .catch(err => {
    showToast('Network error during invoice void', 'danger');
  });
}

function exportPaymentsCsv() {
  const table = document.getElementById('invoicesTable');
  let csv = [];
  const rows = table.querySelectorAll('tr');
  
  for (let i = 0; i < rows.length; i++) {
    if (rows[i].style.display === 'none') continue;
    const row = [], cols = rows[i].querySelectorAll('td, th');
    for (let j = 0; j < cols.length - 1; j++) {
      let data = cols[j].innerText.replace(/(\r\n|\n|\r)/gm, ' ').replace(/(\s\s+)/gm, ' ').trim();
      data = data.replace(/"/g, '""');
      row.push('"' + data + '"');
    }
    csv.push(row.join(','));
  }
  
  const csvFile = new Blob([csv.join('\n')], {type: 'text/csv'});
  const downloadLink = document.createElement('a');
  downloadLink.download = 'gym_payments_ledger_' + new Date().toISOString().slice(0,10) + '.csv';
  downloadLink.href = window.URL.createObjectURL(csvFile);
  downloadLink.style.display = 'none';
  document.body.appendChild(downloadLink);
  downloadLink.click();
  document.body.removeChild(downloadLink);
  showToast('Payments CSV ledger exported successfully!', 'success');
}

function sendInvoiceWhatsApp(invoiceNo, phone, btn) {
  if (!phone) {
    showToast('Member phone number not available', 'warning');
    return;
  }
  const oldText = btn ? btn.innerHTML : '';
  if (btn) {
    btn.disabled = true;
    btn.innerHTML = '⏳ Sending PDF...';
  }
  showToast('Sending PDF Receipt on WhatsApp...', 'info');

  fetch('api/whatsapp.php?action=send_document', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      phone: phone,
      invoice_no: invoiceNo
    })
  })
  .then(r => r.json())
  .then(res => {
    if (res.success) {
      showToast('✅ PDF Receipt delivered on WhatsApp!', 'success');
      if (btn) {
        btn.innerHTML = '✅ Sent!';
        btn.style.background = '#059669';
        setTimeout(() => { if (btn) { btn.disabled = false; btn.innerHTML = oldText; btn.style.background = '#25D366'; } }, 3500);
      }
    } else {
      if (res.data && res.data.fallback_url) {
        window.open(res.data.fallback_url, '_blank');
      }
      showToast(res.message || 'WhatsApp dispatch issue. Fallback opened.', 'warning');
      if (btn) { btn.disabled = false; btn.innerHTML = oldText; }
    }
  })
  .catch(err => {
    showToast('Could not reach WhatsApp gateway', 'danger');
    if (btn) { btn.disabled = false; btn.innerHTML = oldText; }
  });
}
</script>
