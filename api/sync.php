<?php
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');
$db = getDB();

$input = json_decode(file_get_contents('php://input'), true);
$batch = $input['batch'] ?? [];

if (empty($batch)) {
    jsonResponse(true, [], 'No batch records provided');
}

$syncedCount = 0;

foreach ($batch as $item) {
    $payload = $item['payload'] ?? [];
    if (empty($payload)) continue;

    try {
        $db->beginTransaction();

        $invoiceNo = generateInvoiceNo();
        $stmt = $db->prepare("
            INSERT INTO sales (invoice_no, member_id, subtotal, discount, tax, total, paid_amount, due_amount, payment_status, payment_method, synced)
            VALUES (?, ?, ?, ?, ?, ?, ?, 0.00, 'paid', ?, 1)
        ");
        $stmt->execute([
            $invoiceNo,
            $payload['member_id'] ?? null,
            $payload['subtotal'] ?? 0,
            $payload['discount'] ?? 0,
            $payload['tax'] ?? 0,
            $payload['total'] ?? 0,
            $payload['total'] ?? 0,
            $payload['payment_method'] ?? 'Offline Cash'
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
        $syncedCount++;

    } catch (Exception $e) {
        $db->rollBack();
    }
}

jsonResponse(true, ['synced_count' => $syncedCount], "Successfully synced {$syncedCount} offline transactions");
