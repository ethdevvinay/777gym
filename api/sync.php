<?php
/**
 * Offline Synchronization Controller
 * Processes batch sync requests from client IndexedDB when internet connection restores.
 * Supports:
 *   - Offline POS Sales & Invoices
 *   - Offline Member Attendance Punches (Check-In & Check-Out)
 */
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');
$db = getDB();

$input = json_decode(file_get_contents('php://input'), true);
$batch = $input['batch'] ?? [];

if (empty($batch)) {
    jsonResponse(true, ['synced_count' => 0], 'No batch records provided');
}

$syncedSalesCount = 0;
$syncedAttCount   = 0;

foreach ($batch as $item) {
    $itemType = $item['type'] ?? (isset($item['payload']['items']) ? 'sale' : 'attendance');
    $payload  = $item['payload'] ?? [];
    $recordedTime = $item['timestamp'] ?? date('Y-m-d H:i:s');

    if (empty($payload)) continue;

    // ── 1. Process Offline POS Sale ──────────────────────────────────────────
    if ($itemType === 'sale' && !empty($payload['items'])) {
        try {
            $db->beginTransaction();

            $invoiceNo = generateInvoiceNo();
            $stmt = $db->prepare("
                INSERT INTO sales (invoice_no, member_id, subtotal, discount, tax, total, paid_amount, due_amount, payment_status, payment_method, synced, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, 0.00, 'paid', ?, 1, ?)
            ");
            $stmt->execute([
                $invoiceNo,
                $payload['member_id'] ?? null,
                $payload['subtotal'] ?? 0,
                $payload['discount'] ?? 0,
                $payload['tax'] ?? 0,
                $payload['total'] ?? 0,
                $payload['total'] ?? 0,
                $payload['payment_method'] ?? 'Offline Cash',
                $recordedTime
            ]);
            $saleId = $db->lastInsertId();

            foreach ($payload['items'] as $line) {
                $stmtItem = $db->prepare("
                    INSERT INTO sale_items (sale_id, item_type, item_id, item_name, qty, unit_price, total_price)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $stmtItem->execute([
                    $saleId,
                    $line['type'] ?? 'product',
                    $line['id'] ?? null,
                    $line['name'],
                    $line['qty'],
                    $line['price'],
                    $line['price'] * $line['qty']
                ]);
            }

            $db->commit();
            $syncedSalesCount++;

        } catch (Exception $e) {
            $db->rollBack();
        }
    }

    // ── 2. Process Offline Attendance Punch ──────────────────────────────────
    if ($itemType === 'attendance') {
        try {
            $action = $payload['action'] ?? 'checkin';
            $punchTime = $payload['time'] ?? $recordedTime;

            if ($action === 'checkout') {
                $attId = intval($payload['att_id'] ?? 0);
                $memberId = intval($payload['member_id'] ?? 0);

                if ($attId > 0) {
                    $att = $db->query("SELECT * FROM attendance WHERE id = {$attId} AND check_out_time IS NULL")->fetch();
                } else {
                    $att = $db->query("SELECT * FROM attendance WHERE member_id = {$memberId} AND DATE(check_in_time) = DATE('{$punchTime}') AND check_out_time IS NULL ORDER BY id DESC LIMIT 1")->fetch();
                }

                if ($att) {
                    $durationMins = max(1, (int) round((strtotime($punchTime) - strtotime($att['check_in_time'])) / 60));
                    $db->prepare("
                        UPDATE attendance 
                        SET check_out_time = ?, duration_minutes = ?, notes = CONCAT(IFNULL(notes,''), ' | Offline Checkout Synced') 
                        WHERE id = ?
                    ")->execute([$punchTime, $durationMins, $att['id']]);
                    $syncedAttCount++;
                }

            } else {
                // Check-In
                $memberId = intval($payload['member_id'] ?? 0);
                $memberCode = trim($payload['member_code'] ?? '');

                if ($memberId <= 0 && !empty($memberCode)) {
                    $mem = $db->prepare("SELECT id FROM members WHERE member_code = ? OR phone = ? OR biometric_id = ?");
                    $mem->execute([$memberCode, $memberCode, $memberCode]);
                    $memberId = intval($mem->fetchColumn() ?: 0);
                }

                if ($memberId > 0) {
                    // Check duplicate buffer
                    $dupChk = $db->prepare("SELECT id FROM attendance WHERE member_id = ? AND check_in_time >= DATE_SUB(?, INTERVAL 5 MINUTE)");
                    $dupChk->execute([$memberId, $punchTime]);

                    if (!$dupChk->fetch()) {
                        $method = $payload['verification_method'] ?? 'manual';
                        $notes  = ($payload['notes'] ?? 'Offline Check-in') . ' (Synced)';

                        $db->prepare("
                            INSERT INTO attendance (member_id, device_id, check_in_time, verification_method, status, notes) 
                            VALUES (?, 1, ?, ?, 'success', ?)
                        ")->execute([$memberId, $punchTime, $method, $notes]);

                        $syncedAttCount++;
                    }
                }
            }
        } catch (Exception $e) {
            // Skip problematic punch
        }
    }
}

$totalSynced = $syncedSalesCount + $syncedAttCount;
jsonResponse(true, [
    'synced_count' => $totalSynced,
    'sales_synced' => $syncedSalesCount,
    'attendance_synced' => $syncedAttCount
], "Successfully synced {$totalSynced} offline records ({$syncedSalesCount} sales, {$syncedAttCount} attendance)");
