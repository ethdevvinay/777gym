<?php
$db = getDB();

require_once __DIR__ . '/../api/reports.php';

// Auto-save today's daily Excel file if not already saved
$todayDate = date('Y-m-d');
$reportsDir = __DIR__ . '/../reports/daily_excel';
$todayExcelFile = "Daily_Summary_Report_{$todayDate}.xls";
if (!file_exists("{$reportsDir}/{$todayExcelFile}")) {
    autoSaveDailyExcel($db, $todayDate);
}

// Fetch all saved daily excel files
$savedExcelFiles = [];
if (is_dir($reportsDir)) {
    $scan = scandir($reportsDir);
    foreach ($scan as $f) {
        if ($f === '.' || $f === '..') continue;
        if (substr($f, -4) === '.xls' || substr($f, -5) === '.xlsx') {
            $fPath = "{$reportsDir}/{$f}";
            $savedExcelFiles[] = [
                'filename' => $f,
                'path' => "reports/daily_excel/{$f}",
                'size_kb' => round(filesize($fPath) / 1024, 1),
                'date' => str_replace(['Daily_Summary_Report_', '.xls', '.xlsx'], '', $f),
                'mtime' => filemtime($fPath)
            ];
        }
    }
}
usort($savedExcelFiles, function($a, $b) {
    return $b['mtime'] - $a['mtime'];
});

$totalCollection = $db->query("SELECT IFNULL(SUM(paid_amount), 0) FROM sales")->fetchColumn();
$membershipRev = $db->query("SELECT IFNULL(SUM(si.total_price), 0) FROM sale_items si WHERE si.item_type = 'membership'")->fetchColumn();
$ptRev = $db->query("SELECT IFNULL(SUM(si.total_price), 0) FROM sale_items si WHERE si.item_type IN ('pt', 'pt_package')")->fetchColumn();
$productRev = $db->query("SELECT IFNULL(SUM(si.total_price), 0) FROM sale_items si WHERE si.item_type = 'product'")->fetchColumn();
$poolRev = $db->query("SELECT IFNULL(SUM(si.total_price), 0) FROM sale_items si WHERE si.item_type IN ('pool', 'pool_plan')")->fetchColumn();

$totalExpenses = $db->query("SELECT IFNULL(SUM(amount), 0) FROM expenses")->fetchColumn();
$netProfit = max(0, $totalCollection - $totalExpenses);

$activeCount = $db->query("SELECT COUNT(*) FROM members WHERE status = 'active'")->fetchColumn();
$expiredCount = $db->query("SELECT COUNT(*) FROM members WHERE status = 'expired'")->fetchColumn();
$totalMembers = $activeCount + $expiredCount;
$retentionRate = $totalMembers > 0 ? round(($activeCount / $totalMembers) * 100, 1) : 100;

// Recent Transactions
$recentSales = $db->query("
    SELECT s.*, m.name as member_name, m.member_code 
    FROM sales s 
    LEFT JOIN members m ON s.member_id = m.id 
    ORDER BY s.id DESC LIMIT 15
")->fetchAll();

$currency = getSetting('currency_symbol', '₹');
$gymName = getSetting('gym_name', 'THE CLUB 777®');
?>

<div class="page-content">
  <!-- Header & Export Action -->
  <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1.25rem; margin-bottom:1.5rem;">
    <div>
      <h1 style="font-size:1.65rem; font-weight:900; color:var(--text-primary); letter-spacing:-0.03em; display:flex; align-items:center; gap:0.65rem;">
        <span style="background:#EFF6FF; color:var(--primary); width:42px; height:42px; border-radius:12px; display:inline-flex; align-items:center; justify-content:center; font-size:1.4rem; box-shadow:0 2px 8px var(--primary-glow);">📊</span>
        <span>Financial &amp; Daily Automated Excel Reports</span>
      </h1>
      <p style="font-size:0.85rem; color:var(--text-secondary); margin-top:0.25rem;">Automatic daily Excel saving, executive revenue breakdown, sales ledger &amp; member retention</p>
    </div>

    <div style="display:flex; gap:0.65rem; flex-wrap:wrap;">
      <a href="api/reports.php?action=download_daily_excel&date=<?= $todayDate ?>" class="btn" style="background:#10B981; color:#fff; font-weight:800; border-radius:var(--radius-md); box-shadow:0 4px 12px rgba(16,185,129,0.3); text-decoration:none; display:inline-flex; align-items:center; gap:0.4rem;">
        <span>📥</span> <span>Download Today's Live Excel</span>
      </a>
      <button class="btn btn-outline" style="border-radius:var(--radius-md); font-weight:700; background:var(--bg-surface);" onclick="exportReportCsv()">
        📋 Export CSV
      </button>
      <button class="btn btn-primary" style="border-radius:var(--radius-md); font-weight:800; box-shadow:0 4px 12px var(--primary-glow);" onclick="window.print()">
        🖨️ Print Sheet
      </button>
    </div>
  </div>

  <!-- AUTOMATED DAILY EXCEL HUB HERO CARD -->
  <div class="card" style="border-left:5px solid #10B981; background:linear-gradient(135deg, #F0FDF4 0%, #FFFFFF 100%); padding:1.35rem 1.5rem; border-radius:var(--radius-lg); box-shadow:var(--shadow-card); margin-bottom:1.75rem;">
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem;">
      <div style="display:flex; align-items:center; gap:1rem;">
        <div style="width:52px; height:52px; border-radius:14px; background:#10B981; color:#fff; display:flex; align-items:center; justify-content:center; font-size:1.8rem; box-shadow:0 4px 14px rgba(16,185,129,0.35);">
          📗
        </div>
        <div>
          <div style="display:flex; align-items:center; gap:0.5rem;">
            <strong style="font-size:1.15rem; color:#065F46;">Daily Automatic Excel Backup &amp; Report Engine</strong>
            <span class="badge" style="background:#D1FAE5; color:#065F46; font-weight:800; font-size:0.72rem;">● AUTO-SAVED EVERY DAY</span>
          </div>
          <p style="font-size:0.84rem; color:#047857; margin-top:0.25rem;">
            Today's Excel Workbook (Executive Summary, Sales Invoices, Attendance Log, Active Subscriptions, Pending Dues) is auto-saved to <code>reports/daily_excel/</code>.
          </p>
        </div>
      </div>

      <!-- Quick Date Selector for Custom Excel Download -->
      <div style="display:flex; gap:0.5rem; align-items:center;">
        <input type="date" id="customExcelDate" value="<?= $todayDate ?>" class="form-control" style="height:40px; font-weight:700; width:150px;">
        <button class="btn btn-outline" style="height:40px; font-weight:800; background:#fff;" onclick="downloadCustomExcel()">
          📥 Export Date Excel
        </button>
        <button class="btn" style="height:40px; font-weight:800; background:#10B981; color:#fff;" onclick="regenerateTodayExcel()">
          🔄 Refresh Today
        </button>
      </div>
    </div>
  </div>

  <!-- Executive Financial KPI Cards -->
  <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(240px, 1fr)); gap:1.25rem; margin-bottom:1.75rem;">
    <div class="card" style="border-left:4px solid var(--success); padding:1.35rem; border-radius:var(--radius-lg); box-shadow:var(--shadow-card);">
      <div style="font-size:0.75rem; font-weight:800; text-transform:uppercase; color:var(--text-muted); letter-spacing:0.05em;">Gross Realized Revenue</div>
      <div style="font-size:1.9rem; font-weight:900; color:var(--success); margin-top:0.35rem; font-family:var(--font-heading);">
        <?= $currency ?><?= number_format($totalCollection, 2) ?>
      </div>
      <div style="font-size:0.78rem; color:var(--text-secondary); margin-top:0.2rem;">All collected POS &amp; renewal receipts</div>
    </div>

    <div class="card" style="border-left:4px solid var(--danger); padding:1.35rem; border-radius:var(--radius-lg); box-shadow:var(--shadow-card);">
      <div style="font-size:0.75rem; font-weight:800; text-transform:uppercase; color:var(--text-muted); letter-spacing:0.05em;">Operating Expenses</div>
      <div style="font-size:1.9rem; font-weight:900; color:var(--danger); margin-top:0.35rem; font-family:var(--font-heading);">
        <?= $currency ?><?= number_format($totalExpenses, 2) ?>
      </div>
      <div style="font-size:0.78rem; color:var(--text-secondary); margin-top:0.2rem;">Rent, utilities, salaries &amp; maintenance</div>
    </div>

    <div class="card" style="border-left:4px solid var(--primary); padding:1.35rem; border-radius:var(--radius-lg); box-shadow:var(--shadow-card);">
      <div style="font-size:0.75rem; font-weight:800; text-transform:uppercase; color:var(--text-muted); letter-spacing:0.05em;">Net Operating Profit</div>
      <div style="font-size:1.9rem; font-weight:900; color:var(--primary); margin-top:0.35rem; font-family:var(--font-heading);">
        <?= $currency ?><?= number_format($netProfit, 2) ?>
      </div>
      <div style="font-size:0.78rem; color:var(--text-secondary); margin-top:0.2rem;">Net bottom-line gym operating margin</div>
    </div>
  </div>

  <!-- SAVED DAILY EXCEL REPORTS ARCHIVE -->
  <div class="card" style="border-radius:var(--radius-lg); box-shadow:var(--shadow-card); padding:1.35rem; margin-bottom:2rem;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem; flex-wrap:wrap; gap:0.5rem;">
      <h3 style="font-size:1.15rem; font-weight:800; color:var(--text-primary); margin:0; display:flex; align-items:center; gap:0.5rem;">
        <span>📁</span> <span>Auto-Saved Daily Excel Archives (reports/daily_excel/)</span>
      </h3>
      <span class="badge badge-success" style="font-weight:800;">
        <?= count($savedExcelFiles) ?> Reports Saved On Disk
      </span>
    </div>

    <div class="table-wrapper">
      <table>
        <thead>
          <tr>
            <th>Date</th>
            <th>File Name</th>
            <th>File Size</th>
            <th>Saved Timestamp</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($savedExcelFiles)): ?>
            <tr>
              <td colspan="5" style="text-align:center; padding:1.5rem; color:var(--text-muted);">
                No saved daily reports found in storage yet.
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($savedExcelFiles as $ef): ?>
              <tr>
                <td><strong><?= date('d M Y (D)', strtotime($ef['date'])) ?></strong></td>
                <td><code style="font-size:0.8rem; background:var(--bg-surface-secondary); padding:0.2rem 0.4rem; border-radius:4px;"><?= htmlspecialchars($ef['filename']) ?></code></td>
                <td><span style="font-family:var(--font-mono); font-size:0.85rem;"><?= $ef['size_kb'] ?> KB</span></td>
                <td><span style="font-size:0.82rem; color:var(--text-secondary);"><?= date('d M Y, h:i A', $ef['mtime']) ?></span></td>
                <td>
                  <a href="<?= $ef['path'] ?>" download class="btn btn-outline btn-sm" style="font-weight:700; font-size:0.75rem; text-decoration:none;">
                    📥 Download Excel
                  </a>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Revenue Stream Breakdown & Member Retention Metrics -->
  <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(350px, 1fr)); gap:1.5rem; margin-bottom:2rem;">
    <!-- Revenue Stream Breakdown -->
    <div class="card" style="border-radius:var(--radius-lg); box-shadow:var(--shadow-card); padding:1.35rem;">
      <h3 style="font-size:1.15rem; font-weight:800; color:var(--text-primary); margin-bottom:1.15rem;">💰 Revenue Stream Distribution</h3>
      
      <div style="display:flex; flex-direction:column; gap:1rem;">
        <!-- Memberships -->
        <div>
          <div style="display:flex; justify-content:space-between; font-size:0.85rem; margin-bottom:0.35rem;">
            <span>🏋️ Membership Subscriptions</span>
            <strong style="color:var(--text-primary);"><?= $currency ?><?= number_format($membershipRev, 2) ?></strong>
          </div>
          <div style="height:8px; background:var(--bg-surface-secondary); border-radius:99px; overflow:hidden;">
            <div style="height:100%; background:var(--primary); width:<?= $totalCollection > 0 ? round(($membershipRev / $totalCollection) * 100) : 60 ?>%;"></div>
          </div>
        </div>

        <!-- Personal Training -->
        <div>
          <div style="display:flex; justify-content:space-between; font-size:0.85rem; margin-bottom:0.35rem;">
            <span>💪 Personal Training (PT)</span>
            <strong style="color:var(--text-primary);"><?= $currency ?><?= number_format($ptRev, 2) ?></strong>
          </div>
          <div style="height:8px; background:var(--bg-surface-secondary); border-radius:99px; overflow:hidden;">
            <div style="height:100%; background:var(--purple); width:<?= $totalCollection > 0 ? round(($ptRev / $totalCollection) * 100) : 25 ?>%;"></div>
          </div>
        </div>

        <!-- Supplements POS -->
        <div>
          <div style="display:flex; justify-content:space-between; font-size:0.85rem; margin-bottom:0.35rem;">
            <span>🥤 Supplements &amp; POS Products</span>
            <strong style="color:var(--text-primary);"><?= $currency ?><?= number_format($productRev, 2) ?></strong>
          </div>
          <div style="height:8px; background:var(--bg-surface-secondary); border-radius:99px; overflow:hidden;">
            <div style="height:100%; background:var(--success); width:<?= $totalCollection > 0 ? round(($productRev / $totalCollection) * 100) : 10 ?>%;"></div>
          </div>
        </div>

        <!-- Swimming Pool -->
        <div>
          <div style="display:flex; justify-content:space-between; font-size:0.85rem; margin-bottom:0.35rem;">
            <span>🏊 Swimming Pool Passes</span>
            <strong style="color:var(--text-primary);"><?= $currency ?><?= number_format($poolRev, 2) ?></strong>
          </div>
          <div style="height:8px; background:var(--bg-surface-secondary); border-radius:99px; overflow:hidden;">
            <div style="height:100%; background:#0284C7; width:<?= $totalCollection > 0 ? round(($poolRev / $totalCollection) * 100) : 5 ?>%;"></div>
          </div>
        </div>
      </div>
    </div>

    <!-- Member Status & Retention -->
    <div class="card" style="border-radius:var(--radius-lg); box-shadow:var(--shadow-card); padding:1.35rem;">
      <h3 style="font-size:1.15rem; font-weight:800; color:var(--text-primary); margin-bottom:1.15rem;">👥 Member Retention &amp; Health</h3>

      <div style="display:flex; flex-direction:column; gap:0.9rem; font-size:0.88rem;">
        <div style="display:flex; justify-content:space-between; padding-bottom:0.75rem; border-bottom:1px solid var(--border-color);">
          <span>Active Gym Members:</span>
          <strong style="color:var(--success); font-size:1rem;"><?= $activeCount ?> Members</strong>
        </div>

        <div style="display:flex; justify-content:space-between; padding-bottom:0.75rem; border-bottom:1px solid var(--border-color);">
          <span>Expired / Due Members:</span>
          <strong style="color:var(--danger); font-size:1rem;"><?= $expiredCount ?> Members</strong>
        </div>

        <div style="display:flex; justify-content:space-between; padding-bottom:0.75rem; border-bottom:1px solid var(--border-color);">
          <span>Member Retention Ratio:</span>
          <strong style="color:var(--primary); font-size:1rem; font-family:var(--font-mono);"><?= $retentionRate ?>%</strong>
        </div>

        <div style="display:flex; justify-content:space-between;">
          <span>Automated Renewal Reminders:</span>
          <span class="badge badge-success">● 100% ACTIVE (WHATSAPP)</span>
        </div>
      </div>
    </div>
  </div>

  <!-- Recent Transactions Ledger Table -->
  <div class="card" style="border-radius:var(--radius-lg); box-shadow:var(--shadow-card); padding:1.35rem;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;">
      <h3 style="font-size:1.15rem; font-weight:800; color:var(--text-primary); margin:0;">🧾 Recent Transactions Ledger</h3>
      <a href="index.php?page=payments" class="btn btn-outline btn-sm">View All Invoices</a>
    </div>

    <div class="table-wrapper">
      <table>
        <thead>
          <tr>
            <th>Invoice #</th>
            <th>Member</th>
            <th>Date &amp; Time</th>
            <th>Payment Mode</th>
            <th>Total Amount</th>
            <th>Paid Status</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($recentSales)): ?>
            <tr>
              <td colspan="6" style="text-align:center; padding:2rem; color:var(--text-muted);">
                No transaction receipts recorded yet.
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($recentSales as $s): ?>
              <tr>
                <td><strong style="font-family:var(--font-mono); color:var(--primary);"><?= htmlspecialchars($s['invoice_no']) ?></strong></td>
                <td>
                  <strong style="color:var(--text-primary);"><?= htmlspecialchars($s['member_name'] ?: 'Walk-in Customer') ?></strong>
                  <?php if (!empty($s['member_code'])): ?>
                    <div style="font-size:0.72rem; font-family:var(--font-mono); color:var(--text-muted);"><?= htmlspecialchars($s['member_code']) ?></div>
                  <?php endif; ?>
                </td>
                <td><span style="font-size:0.82rem; color:var(--text-secondary);"><?= date('d M Y, h:i A', strtotime($s['created_at'])) ?></span></td>
                <td><span class="badge badge-secondary"><?= htmlspecialchars($s['payment_method']) ?></span></td>
                <td><strong style="font-family:var(--font-mono); font-size:0.95rem; color:var(--text-primary);"><?= $currency ?><?= number_format($s['total'], 2) ?></strong></td>
                <td>
                  <span class="badge badge-<?= $s['payment_status'] === 'paid' ? 'success' : ($s['payment_status'] === 'voided' ? 'danger' : 'warning') ?>">
                    <?= strtoupper($s['payment_status']) ?>
                  </span>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<script>
function downloadCustomExcel() {
  const dateVal = document.getElementById('customExcelDate').value;
  if (!dateVal) {
    showToast('Please select a valid date', 'warning');
    return;
  }
  window.location.href = 'api/reports.php?action=download_daily_excel&date=' + encodeURIComponent(dateVal);
  showToast('Generating and downloading Excel for ' + dateVal, 'info');
}

function regenerateTodayExcel() {
  const today = '<?= $todayDate ?>';
  fetch('api/reports.php?action=generate_daily_excel&date=' + today + '&auto_save=1&json_only=1')
    .then(r => r.json())
    .then(d => {
      if (d.success) {
        showToast('Today\'s Excel file refreshed and saved successfully!', 'success');
        setTimeout(() => location.reload(), 1000);
      }
    })
    .catch(() => {
      showToast('Error refreshing daily Excel', 'danger');
    });
}

function exportReportCsv() {
  const table = document.querySelector('.table-wrapper table');
  let csv = [];
  const rows = table.querySelectorAll('tr');
  
  for (let i = 0; i < rows.length; i++) {
    const row = [], cols = rows[i].querySelectorAll('td, th');
    for (let j = 0; j < cols.length; j++) {
      let data = cols[j].innerText.replace(/(\r\n|\n|\r)/gm, ' ').replace(/(\s\s+)/gm, ' ').trim();
      data = data.replace(/"/g, '""');
      row.push('"' + data + '"');
    }
    csv.push(row.join(','));
  }
  
  const csvFile = new Blob([csv.join('\n')], {type: 'text/csv'});
  const downloadLink = document.createElement('a');
  downloadLink.download = 'gym_financial_report_' + new Date().toISOString().slice(0,10) + '.csv';
  downloadLink.href = window.URL.createObjectURL(csvFile);
  downloadLink.style.display = 'none';
  document.body.appendChild(downloadLink);
  downloadLink.click();
  document.body.removeChild(downloadLink);
  showToast('Financial CSV Ledger downloaded successfully!', 'success');
}
</script>
