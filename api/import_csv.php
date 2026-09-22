<?php
/**
 * CSV Bulk Import Script
 * Imports members + fee records from:
 *   - CLUB FEES.csv (Nov 2023 data)
 *   - data.csv      (Aug 2026 data)
 *
 * Run from browser: http://localhost/GYM/api/import_csv.php
 * OR with ?confirm=1 to actually commit data
 */
require_once __DIR__ . '/../config/database.php';

// ── Security: only allow admin IP or localhost ──────────────────────────────
$allowedIps = ['127.0.0.1', '::1', '192.168.1.100'];
$clientIp = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
if (!in_array($clientIp, $allowedIps) && !isset($_GET['token']) && PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "❌ Access Denied. Run this only from localhost.";
    exit;
}

header('Content-Type: text/html; charset=UTF-8');
$db       = getDB();
$dryRun   = !isset($_GET['confirm']) || $_GET['confirm'] !== '1';

// ── Helper: parse DD.MM.YY or DD.MM.YYYY to YYYY-MM-DD ─────────────────────
function parseDate(string $d): ?string {
    $d = trim($d);
    if (empty($d)) return null;
    if (preg_match('/^(\d{2})\.(\d{2})\.(\d{2})$/', $d, $m)) {
        $year = intval($m[3]) >= 50 ? '19'.$m[3] : '20'.$m[3];
        return "{$year}-{$m[2]}-{$m[1]}";
    }
    if (preg_match('/^(\d{2})\.(\d{2})\.(\d{4})$/', $d, $m)) {
        return "{$m[3]}-{$m[2]}-{$m[1]}";
    }
    return null;
}

// ── Helper: parse category → months duration ───────────────────────────────
function parseDuration(string $cat): int {
    $cat = strtoupper(trim($cat));
    if (str_contains($cat, '1YEAR') || str_contains($cat, '12'))  return 365;
    if (str_contains($cat, '(6)'))  return 180;
    if (str_contains($cat, '(3)'))  return 90;
    if (str_contains($cat, '(2)'))  return 60;
    if (str_contains($cat, '15DAYS')) return 15;
    if (str_contains($cat, 'COACHING')) return 30;
    return 30; // default 1 month
}

// ── Helper: parse category → membership plan name ──────────────────────────
function parsePlanName(string $cat): string {
    $cat = strtoupper(trim($cat));
    if (str_contains($cat, 'POOL+COACHING')) return 'Pool + Coaching';
    if (str_contains($cat, 'POOL'))   return 'Pool';
    if (str_contains($cat, 'SAUNA'))  return 'GYM + Sauna & Steam';
    if (str_contains($cat, '1YEAR') || str_contains($cat, '12')) return 'GYM (1 Year)';
    if (str_contains($cat, '(6)'))  return 'GYM (6 Months)';
    if (str_contains($cat, '(3)'))  return 'GYM (3 Months)';
    if (str_contains($cat, '(2)'))  return 'GYM (2 Months)';
    if (str_contains($cat, '15DAYS')) return 'GYM (15 Days)';
    if (str_contains($cat, 'GYM'))  return 'GYM (1 Month)';
    return 'General';
}

// ── Parse a CSV file ────────────────────────────────────────────────────────
function parseCsvFile(string $path): array {
    $rows = [];
    if (($fh = fopen($path, 'r')) === false) return $rows;
    $header = null;
    while (($line = fgetcsv($fh)) !== false) {
        if ($header === null) { $header = $line; continue; }
        // Skip empty rows (no name AND no phone)
        if (empty(trim($line[2] ?? '')) && empty(trim($line[5] ?? ''))) continue;
        $rows[] = $line;
    }
    fclose($fh);
    return $rows;
}

// ── Get or Create a membership_type ────────────────────────────────────────
function getOrCreateMembershipType(PDO $db, string $planName, int $durationDays, float $price): int {
    $s = $db->prepare("SELECT id FROM membership_types WHERE title = ? LIMIT 1");
    $s->execute([$planName]);
    $r = $s->fetch();
    if ($r) return (int)$r['id'];
    $s = $db->prepare("INSERT INTO membership_types (title, duration_days, price, status) VALUES (?, ?, ?, 'active')");
    $s->execute([$planName, $durationDays, $price]);
    return (int)$db->lastInsertId();
}

// ── Process one CSV row ─────────────────────────────────────────────────────
$stats = ['inserted_members' => 0, 'updated_members' => 0, 'subscriptions' => 0, 'fees' => 0, 'skipped' => 0, 'errors' => []];
$log   = [];

function processRow(PDO $db, array $r, bool $dryRun, array &$stats, array &$log): void {
    // CSV columns: SR, DATE, NAME, CODE, ADD, PHONE, FEES, SUBMIT, BALANCE/PENDING, CAT
    $name       = ucwords(strtolower(trim($r[2] ?? '')));
    $memberCode = trim($r[3] ?? '');
    $address    = trim($r[4] ?? 'JJR');
    $phone      = preg_replace('/\D/', '', trim($r[5] ?? ''));
    $feesTotal  = intval(trim($r[6] ?? 0));
    $feesSubmit = intval(trim($r[7] ?? 0));
    $feesBalance= intval(trim($r[8] ?? 0));
    $cat        = trim($r[9] ?? 'GYM');
    $dateRaw    = trim($r[1] ?? '');
    $payDate    = parseDate($dateRaw);

    if (empty($name) || strlen($name) < 2) { $stats['skipped']++; return; }
    if (strlen($phone) < 8 || strlen($phone) > 15) $phone = '';

    $planName    = parsePlanName($cat);
    $duration    = parseDuration($cat);
    $startDate   = $payDate ?? date('Y-m-d');
    $endDate     = date('Y-m-d', strtotime("+{$duration} days", strtotime($startDate)));

    // ── 1. Find existing member by code or phone ──────────────────────────
    $existingId = null;
    if (!empty($memberCode)) {
        $s = $db->prepare("SELECT id FROM members WHERE member_code = ? LIMIT 1");
        $s->execute([$memberCode]);
        $row = $s->fetch();
        if ($row) $existingId = $row['id'];
    }
    if (!$existingId && !empty($phone)) {
        $s = $db->prepare("SELECT id FROM members WHERE phone = ? LIMIT 1");
        $s->execute([$phone]);
        $row = $s->fetch();
        if ($row) $existingId = $row['id'];
    }

    // ── 2. Insert or use existing member ──────────────────────────────────
    $memberId   = $existingId;
    $finalCode  = $memberCode ?: ('C' . rand(5000, 9999));
    $biometricId = 'BIO-' . substr(preg_replace('/\D/', '', $finalCode . rand(100,999)), 0, 6);

    if (!$existingId) {
        $log[] = "🆕 NEW: <b>{$name}</b> ({$memberCode}) | {$phone} | {$planName} | {$feesSubmit}₹";
        if (!$dryRun) {
            try {
                $s = $db->prepare("INSERT INTO members (member_code, name, phone, whatsapp_no, address, biometric_id, status, created_at) VALUES (?, ?, ?, ?, ?, ?, 'active', ?)");
                $s->execute([$finalCode, $name, $phone, $phone, $address, $biometricId, $startDate . ' 00:00:00']);
                $memberId = (int)$db->lastInsertId();
            } catch (Exception $dupEx) {
                $sFind = $db->prepare("SELECT id FROM members WHERE member_code = ? OR (phone = ? AND phone != '') LIMIT 1");
                $sFind->execute([$finalCode, $phone]);
                $memberId = (int)$sFind->fetchColumn();
            }
        } else {
            $memberId = 0;
        }
        $stats['inserted_members']++;
    } else {
        $log[] = "♻️  EXISTS: <b>{$name}</b> ({$memberCode}) | Adding subscription: {$planName} | {$feesSubmit}₹";
        $stats['updated_members']++;
    }

    // ── 3. Create subscription ────────────────────────────────────────────
    $typeId = 0;
    if ($memberId > 0 || $dryRun) {
        if (!$dryRun) {
            $typeId = getOrCreateMembershipType($db, $planName, $duration, $feesTotal);
            // Check if this exact subscription already exists
            $sEx = $db->prepare("SELECT id FROM member_subscriptions WHERE member_id=? AND start_date=? AND membership_type_id=? LIMIT 1");
            $sEx->execute([$memberId, $startDate, $typeId]);
            if (!$sEx->fetch()) {
                $subStatus = (strtotime($endDate) >= strtotime('today')) ? 'active' : 'expired';
                $s = $db->prepare("INSERT INTO member_subscriptions (member_id, membership_type_id, start_date, end_date, original_end_date, price_paid, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $s->execute([$memberId, $typeId, $startDate, $endDate, $endDate, $feesSubmit, $subStatus]);
                $stats['subscriptions']++;
            }
        } else {
            $stats['subscriptions']++;
        }
    }

    // ── 4. Record fee payment in sales/payments ───────────────────────────
    if ($feesSubmit > 0 && ($memberId > 0 || $dryRun) && !$dryRun) {
        try {
            $invNo = 'INV-' . date('ymd', strtotime($startDate)) . '-' . strtoupper(substr(uniqid(), -4));
            $payStatus = ($feesBalance > 0) ? 'partial' : 'paid';
            $billTotal = $feesTotal > 0 ? $feesTotal : $feesSubmit;

            $s = $db->prepare("INSERT INTO sales (invoice_no, member_id, subtotal, total, paid_amount, due_amount, payment_status, payment_method, notes, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, 'cash', 'Imported from CSV', ?)");
            $s->execute([$invNo, $memberId, $billTotal, $billTotal, $feesSubmit, $feesBalance, $payStatus, $startDate . ' 10:00:00']);
            $saleId = (int)$db->lastInsertId();

            if ($saleId && $typeId) {
                $sItem = $db->prepare("INSERT INTO sale_items (sale_id, item_type, item_id, item_name, qty, unit_price, discount, total_price) VALUES (?, 'membership', ?, ?, 1, ?, 0, ?)");
                $sItem->execute([$saleId, $typeId, $planName, $billTotal, $billTotal]);
            }
            $stats['fees']++;
        } catch (Exception $e) {
            // sales table insert error fallback
        }
    }
}

// ── Load both CSV files ─────────────────────────────────────────────────────
$csvFiles = [
    'CLUB FEES.csv (Nov 2023)' => __DIR__ . '/../CLUB FEES.csv',
    'data.csv (Aug 2026)'      => __DIR__ . '/../data.csv',
];

$allRows = [];
foreach ($csvFiles as $label => $path) {
    $rows = parseCsvFile($path);
    foreach ($rows as $row) {
        $allRows[] = ['_source' => $label, 'row' => $row];
    }
}

// ── Run ─────────────────────────────────────────────────────────────────────
if (!$dryRun) {
    $db->beginTransaction();
}

try {
    foreach ($allRows as $item) {
        processRow($db, $item['row'], $dryRun, $stats, $log);
    }
    if (!$dryRun) {
        $db->commit();
    }
} catch (Exception $e) {
    if (!$dryRun) $db->rollBack();
    $stats['errors'][] = $e->getMessage();
}

// ── Output ──────────────────────────────────────────────────────────────────
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>CSV Import — THE CLUB 777®</title>
  <style>
    * { margin:0; padding:0; box-sizing:border-box; }
    body { font-family: 'Segoe UI', sans-serif; background:#0f172a; color:#e2e8f0; padding:2rem; }
    h1 { font-size:1.6rem; font-weight:800; color:#38bdf8; margin-bottom:0.25rem; }
    .subtitle { color:#64748b; font-size:0.85rem; margin-bottom:2rem; }
    .stats-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(150px,1fr)); gap:1rem; margin-bottom:2rem; }
    .stat-card { background:#1e293b; border:1px solid #334155; border-radius:10px; padding:1rem 1.25rem; }
    .stat-val { font-size:2rem; font-weight:800; font-family:monospace; }
    .stat-lbl { font-size:0.72rem; color:#64748b; text-transform:uppercase; letter-spacing:0.05em; margin-top:0.25rem; }
    .mode-badge { display:inline-block; padding:0.35rem 0.9rem; border-radius:20px; font-size:0.75rem; font-weight:800; margin-bottom:1.5rem; }
    .dry { background:#92400e33; border:1px solid #f59e0b; color:#fde68a; }
    .live { background:#14532d; border:1px solid #10b981; color:#6ee7b7; }
    .log-box { background:#020617; border:1px solid #1e293b; border-radius:10px; padding:1rem 1.25rem; max-height:400px; overflow-y:auto; font-size:0.78rem; line-height:1.7; }
    .action-btns { display:flex; gap:1rem; margin-bottom:1.5rem; flex-wrap:wrap; }
    .btn { display:inline-block; padding:0.6rem 1.4rem; border-radius:8px; font-weight:700; font-size:0.85rem; text-decoration:none; cursor:pointer; border:none; }
    .btn-green { background:#10b981; color:#fff; }
    .btn-red { background:#ef4444; color:#fff; }
    .btn-gray { background:#334155; color:#e2e8f0; }
    .error-box { background:#7f1d1d; border:1px solid #ef4444; border-radius:8px; padding:1rem; margin-bottom:1rem; }
    h3 { font-size:0.9rem; font-weight:700; color:#94a3b8; margin:1rem 0 0.5rem; }
  </style>
</head>
<body>
  <h1>📥 THE CLUB 777® — CSV Bulk Import</h1>
  <p class="subtitle">Importing <strong><?= count($allRows) ?></strong> total rows from 2 CSV files</p>

  <div class="mode-badge <?= $dryRun ? 'dry' : 'live' ?>">
    <?= $dryRun ? '🧪 DRY RUN — No data saved to database' : '✅ LIVE IMPORT — Data committed to database!' ?>
  </div>

  <!-- Stats -->
  <div class="stats-grid">
    <div class="stat-card">
      <div class="stat-val" style="color:#38bdf8;"><?= $stats['inserted_members'] ?></div>
      <div class="stat-lbl">🆕 New Members</div>
    </div>
    <div class="stat-card">
      <div class="stat-val" style="color:#a78bfa;"><?= $stats['updated_members'] ?></div>
      <div class="stat-lbl">♻️ Existing (Updated)</div>
    </div>
    <div class="stat-card">
      <div class="stat-val" style="color:#10b981;"><?= $stats['subscriptions'] ?></div>
      <div class="stat-lbl">📋 Subscriptions</div>
    </div>
    <div class="stat-card">
      <div class="stat-val" style="color:#f59e0b;"><?= $stats['fees'] ?></div>
      <div class="stat-lbl">💰 Fee Records</div>
    </div>
    <div class="stat-card">
      <div class="stat-val" style="color:#64748b;"><?= $stats['skipped'] ?></div>
      <div class="stat-lbl">⏭️ Skipped</div>
    </div>
  </div>

  <?php if (!empty($stats['errors'])): ?>
  <div class="error-box">
    <strong>❌ Errors:</strong><br>
    <?= implode('<br>', array_map('htmlspecialchars', $stats['errors'])) ?>
  </div>
  <?php endif; ?>

  <!-- Action Buttons -->
  <div class="action-btns">
    <?php if ($dryRun): ?>
    <a class="btn btn-green" href="?confirm=1">🚀 CONFIRM — Import ALL Data to Database</a>
    <a class="btn btn-gray" href="?">🔄 Re-run Dry Run</a>
    <?php else: ?>
    <a class="btn btn-gray" href="/GYM/index.php?page=members">👥 View Members</a>
    <a class="btn btn-red" onclick="return confirm('Delete this script?')" href="?delete=1">🗑️ Delete Import Script (Security)</a>
    <?php endif; ?>
  </div>

  <!-- Log -->
  <h3>📋 Import Log (<?= count($log) ?> entries)</h3>
  <div class="log-box">
    <?php foreach ($log as $i => $entry): ?>
      <div style="border-bottom:1px solid #1e293b; padding:0.2rem 0;"><?= ($i+1) ?>. <?= $entry ?></div>
    <?php endforeach; ?>
    <?php if (empty($log)): ?>
      <div style="color:#64748b;">No entries to display.</div>
    <?php endif; ?>
  </div>

  <?php if (isset($_GET['delete']) && $_GET['delete'] === '1'): ?>
  <?php @unlink(__FILE__); echo "<script>setTimeout(()=>window.location='/GYM/index.php?page=members',500)</script>"; ?>
  <?php endif; ?>
</body>
</html>
