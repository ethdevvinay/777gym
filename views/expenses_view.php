<!-- Redesigned Commercial Operational Expenses & Daily Gym Kharcha Tracker View -->
<?php
$db = getDB();
$stmt = $db->query("SELECT * FROM expenses ORDER BY expense_date DESC, id DESC");
$expenses = $stmt->fetchAll();

$totalExpense = $db->query("SELECT IFNULL(SUM(amount), 0) FROM expenses")->fetchColumn();
$thisMonthExpense = $db->query("SELECT IFNULL(SUM(amount), 0) FROM expenses WHERE MONTH(expense_date) = MONTH(CURRENT_DATE()) AND YEAR(expense_date) = YEAR(CURRENT_DATE())")->fetchColumn();

// Category Breakdown Stats
$catStmt = $db->query("SELECT category, COUNT(*) as count, IFNULL(SUM(amount), 0) as total FROM expenses GROUP BY category ORDER BY total DESC");
$categoryStats = $catStmt->fetchAll();

$topCategory = !empty($categoryStats) ? $categoryStats[0]['category'] . ' (' . getSetting('currency_symbol', '₹') . number_format($categoryStats[0]['total'], 0) . ')' : 'None';

$currency = getSetting('currency_symbol', '₹');
?>

<div class="page-content">
  <!-- Top Banner Header -->
  <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem; margin-bottom:1.5rem;">
    <div>
      <h1 style="font-size:1.6rem; font-weight:800; color:var(--text-primary); display:flex; align-items:center; gap:0.5rem;">
        <span>💸</span> <span>Operational Expenses &amp; Gym Kharcha Tracker</span>
      </h1>
      <p style="font-size:0.85rem; color:var(--text-secondary);">
        Track Rent, Electricity, Staff Salaries, Equipment Maintenance, Marketing, Supplements Restock &amp; Daily Petty Cash
      </p>
    </div>

    <div style="display:flex; gap:0.5rem; flex-wrap:wrap;">
      <button class="btn btn-primary" onclick="openAddExpenseModal()">
        + Record New Expense
      </button>
    </div>
  </div>

  <!-- Summary KPI Cards -->
  <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:1rem; margin-bottom:1.5rem;">
    <div class="card" style="border-left:4px solid var(--danger);">
      <div style="font-size:0.75rem; font-weight:700; color:var(--text-muted); text-transform:uppercase;">TOTAL EXPENSES LOGGED</div>
      <div style="font-size:1.8rem; font-weight:800; color:var(--danger); margin-top:0.25rem;"><?= $currency ?><?= number_format($totalExpense, 2) ?></div>
      <div style="font-size:0.72rem; color:var(--text-muted);"><?= count($expenses) ?> Total Entries</div>
    </div>

    <div class="card" style="border-left:4px solid #F59E0B;">
      <div style="font-size:0.75rem; font-weight:700; color:var(--text-muted); text-transform:uppercase;">THIS MONTH'S EXPENSES</div>
      <div style="font-size:1.8rem; font-weight:800; color:#D97706; margin-top:0.25rem;"><?= $currency ?><?= number_format($thisMonthExpense, 2) ?></div>
      <div style="font-size:0.72rem; color:var(--text-muted);"><?= date('F Y') ?></div>
    </div>

    <div class="card" style="border-left:4px solid var(--primary);">
      <div style="font-size:0.75rem; font-weight:700; color:var(--text-muted); text-transform:uppercase;">TOP SPENDING CATEGORY</div>
      <div style="font-size:1.3rem; font-weight:800; color:var(--primary); margin-top:0.35rem;"><?= htmlspecialchars($topCategory) ?></div>
      <div style="font-size:0.72rem; color:var(--text-muted);"><?= count($categoryStats) ?> Active Categories</div>
    </div>
  </div>

  <!-- Category Quick Badges Filter Bar -->
  <div class="card" style="padding:1rem 1.25rem; margin-bottom:1.5rem;">
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:0.75rem;">
      <div style="display:flex; gap:0.4rem; flex-wrap:wrap; align-items:center;">
        <span style="font-size:0.8rem; font-weight:700; color:var(--text-muted); margin-right:0.2rem;">Filter Category:</span>
        <button class="btn btn-sm exp-filter-btn active" onclick="filterCategory('all', this)">All Categories</button>
        <button class="btn btn-sm exp-filter-btn" onclick="filterCategory('Rent', this)">🏢 Rent</button>
        <button class="btn btn-sm exp-filter-btn" onclick="filterCategory('Electricity', this)">⚡ Electricity</button>
        <button class="btn btn-sm exp-filter-btn" onclick="filterCategory('Salary', this)">👥 Salary</button>
        <button class="btn btn-sm exp-filter-btn" onclick="filterCategory('Equipment', this)">🏋️ Equipment</button>
        <button class="btn btn-sm exp-filter-btn" onclick="filterCategory('Maintenance', this)">🛠️ Maintenance</button>
        <button class="btn btn-sm exp-filter-btn" onclick="filterCategory('Marketing', this)">📢 Marketing</button>
        <button class="btn btn-sm exp-filter-btn" onclick="filterCategory('Other', this)">📋 Other</button>
      </div>

      <div>
        <input type="text" id="expenseSearch" class="form-control" placeholder="🔍 Search Vendor, Notes, Category..." style="padding:0.4rem 0.8rem; font-size:0.85rem; width:240px;" onkeyup="searchExpenseTable()">
      </div>
    </div>
  </div>

  <!-- Expenses Table -->
  <div class="card">
    <div class="card-header" style="display:flex; justify-content:space-between; align-items:center;">
      <span class="card-title">🧾 Expense Records Ledger</span>
      <button class="btn btn-primary btn-sm" onclick="openAddExpenseModal()">+ Record Expense</button>
    </div>

    <div class="table-responsive" style="margin-top:0.75rem;">
      <table class="table table-mobile-card" id="expensesTable">
        <thead>
          <tr>
            <th>Date</th>
            <th>Category</th>
            <th>Vendor / Beneficiary</th>
            <th>Amount Paid</th>
            <th>Payment Mode</th>
            <th>Description / Remarks</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($expenses)): ?>
            <tr><td colspan="7" style="text-align:center; color:var(--text-muted); padding:2rem;">No operational expenses recorded yet.</td></tr>
          <?php else: ?>
            <?php foreach ($expenses as $exp): ?>
              <tr class="expense-row" data-category="<?= htmlspecialchars($exp['category']) ?>">
                <td data-label="Date">
                  <strong><?= date('d M Y', strtotime($exp['expense_date'])) ?></strong>
                </td>
                <td data-label="Category">
                  <span class="badge badge-info"><?= htmlspecialchars($exp['category']) ?></span>
                </td>
                <td data-label="Vendor">
                  <strong><?= htmlspecialchars($exp['vendor_name'] ?: 'General Vendor') ?></strong>
                </td>
                <td data-label="Amount">
                  <strong style="color:var(--danger); font-size:1rem;"><?= $currency ?><?= number_format($exp['amount'], 2) ?></strong>
                </td>
                <td data-label="Mode">
                  <span class="badge badge-secondary"><?= htmlspecialchars($exp['payment_mode']) ?></span>
                </td>
                <td data-label="Notes" style="font-size:0.83rem; color:var(--text-secondary); max-width:280px;">
                  <?= htmlspecialchars($exp['notes'] ?: 'No notes provided') ?>
                </td>
                <td data-label="Actions">
                  <div style="display:flex; gap:0.35rem;">
                    <button class="btn btn-secondary btn-sm" onclick="openEditExpenseModal(<?= htmlspecialchars(json_encode($exp)) ?>)" title="Edit Expense">
                      ✏️ Edit
                    </button>
                    <button class="btn btn-danger btn-sm" onclick="deleteExpense(<?= $exp['id'] ?>)" title="Delete Entry">
                      🗑️
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

<!-- RECORD / ADD EXPENSE MODAL -->
<div class="modal" id="addExpenseModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:100; align-items:center; justify-content:center;">
  <div class="modal-overlay" style="position:absolute; inset:0;" onclick="closeModal('addExpenseModal')"></div>
  <div class="card" style="position:relative; z-index:10; background:#fff; width:90%; max-width:480px; border-radius:16px; padding:1.5rem; max-height:90vh; overflow-y:auto;">
    <div class="card-header" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;">
      <span class="card-title" id="expenseModalTitle" style="font-size:1.15rem; font-weight:800;">💸 Record Gym Expense</span>
      <button class="modal-close" style="background:none; border:none; font-size:1.2rem; cursor:pointer;" onclick="closeModal('addExpenseModal')">✕</button>
    </div>

    <form onsubmit="event.preventDefault(); submitExpenseForm();">
      <input type="hidden" id="expId" value="0">

      <div class="form-group">
        <label class="form-label">Expense Category *</label>
        <select id="expCategory" class="form-control" required>
          <option value="Rent">🏢 Rent &amp; Property Lease</option>
          <option value="Electricity">⚡ Electricity &amp; Power</option>
          <option value="Salary">👥 Staff &amp; Trainer Salaries / Payroll</option>
          <option value="Equipment">🏋️ Equipment Purchase / Lease</option>
          <option value="Maintenance">🛠️ Machine Repairs &amp; Maintenance</option>
          <option value="Marketing">📢 Marketing, Meta Ads &amp; Banners</option>
          <option value="Supplements">📦 Supplements &amp; Inventory Restock</option>
          <option value="Cleaning">🧹 Cleaning, Sanitization &amp; Supplies</option>
          <option value="Internet">🌐 Internet, Software &amp; Tech</option>
          <option value="Water">💧 Water &amp; Dispensers</option>
          <option value="Other">📋 Other Miscellaneous Expenses</option>
        </select>
      </div>

      <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.75rem;">
        <div class="form-group">
          <label class="form-label">Amount Paid (<?= $currency ?>) *</label>
          <input type="number" step="0.01" id="expAmount" class="form-control" placeholder="5000" required>
        </div>

        <div class="form-group">
          <label class="form-label">Expense Date *</label>
          <input type="date" id="expDate" class="form-control" value="<?= date('Y-m-d') ?>" required>
        </div>
      </div>

      <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.75rem;">
        <div class="form-group">
          <label class="form-label">Vendor / Person Paid</label>
          <input type="text" id="expVendor" class="form-control" placeholder="e.g. Landlord / Power Board">
        </div>

        <div class="form-group">
          <label class="form-label">Payment Mode</label>
          <select id="expMode" class="form-control">
            <option value="UPI / QR">UPI / GPay / PhonePe</option>
            <option value="Bank Transfer">Bank Transfer / NEFT</option>
            <option value="Cash">Cash (Petty Cash)</option>
            <option value="Credit / Debit Card">Credit / Debit Card</option>
            <option value="Cheque">Cheque</option>
          </select>
        </div>
      </div>

      <div class="form-group">
        <label class="form-label">Notes / Bill Description</label>
        <textarea id="expNotes" class="form-control" rows="3" placeholder="e.g. Paid August monthly electricity bill with meter reading proof"></textarea>
      </div>

      <div style="display:flex; gap:0.75rem; margin-top:1.5rem;">
        <button type="button" class="btn btn-secondary btn-block" onclick="closeModal('addExpenseModal')">Cancel</button>
        <button type="submit" class="btn btn-primary btn-block">✓ RECORD EXPENSE</button>
      </div>
    </form>
  </div>
</div>

<style>
.exp-filter-btn {
  background: var(--bg-surface);
  color: var(--text-secondary);
  border: 1px solid var(--border-color);
}
.exp-filter-btn.active {
  background: var(--primary);
  color: #FFFFFF;
  border-color: var(--primary);
}
</style>

<script>
function filterCategory(category, btn) {
  document.querySelectorAll('.exp-filter-btn').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');

  const rows = document.querySelectorAll('.expense-row');
  rows.forEach(r => {
    const cat = r.getAttribute('data-category');
    if (category === 'all') {
      r.style.display = '';
    } else {
      r.style.display = (cat === category) ? '' : 'none';
    }
  });
}

function searchExpenseTable() {
  const query = document.getElementById('expenseSearch').value.toLowerCase();
  const rows = document.querySelectorAll('.expense-row');
  rows.forEach(r => {
    const text = r.textContent.toLowerCase();
    r.style.display = text.includes(query) ? '' : 'none';
  });
}

function openAddExpenseModal() {
  document.getElementById('expId').value = '0';
  document.getElementById('expenseModalTitle').innerText = '💸 Record Gym Expense';
  document.getElementById('expCategory').value = 'Rent';
  document.getElementById('expAmount').value = '';
  document.getElementById('expDate').value = new Date().toISOString().slice(0, 10);
  document.getElementById('expVendor').value = '';
  document.getElementById('expMode').value = 'UPI / QR';
  document.getElementById('expNotes').value = '';
  openModal('addExpenseModal');
}

function openEditExpenseModal(exp) {
  document.getElementById('expId').value = exp.id;
  document.getElementById('expenseModalTitle').innerText = '✏️ Edit Expense Entry';
  document.getElementById('expCategory').value = exp.category;
  document.getElementById('expAmount').value = exp.amount;
  document.getElementById('expDate').value = exp.expense_date;
  document.getElementById('expVendor').value = exp.vendor_name || '';
  document.getElementById('expMode').value = exp.payment_mode || 'UPI / QR';
  document.getElementById('expNotes').value = exp.notes || '';
  openModal('addExpenseModal');
}

function submitExpenseForm() {
  const id = parseInt(document.getElementById('expId').value) || 0;
  const payload = {
    id: id,
    category: document.getElementById('expCategory').value,
    amount: document.getElementById('expAmount').value,
    expense_date: document.getElementById('expDate').value,
    vendor_name: document.getElementById('expVendor').value.trim(),
    payment_mode: document.getElementById('expMode').value,
    notes: document.getElementById('expNotes').value.trim()
  };

  const action = id > 0 ? 'edit' : 'add';

  fetch(`api/expenses.php?action=${action}`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(payload)
  })
  .then(res => res.json())
  .then(res => {
    if (res.success) {
      showToast(res.message, 'success');
      closeModal('addExpenseModal');
      setTimeout(() => location.reload(), 1000);
    } else {
      showToast(res.message || 'Action failed', 'danger');
    }
  });
}

function deleteExpense(id) {
  if (!confirm('Are you sure you want to delete this expense record?')) return;
  fetch(`api/expenses.php?action=delete&id=${id}`)
    .then(res => res.json())
    .then(res => {
      if (res.success) {
        showToast(res.message, 'success');
        setTimeout(() => location.reload(), 1000);
      } else {
        showToast(res.message || 'Delete failed', 'danger');
      }
    });
}
</script>
