<?php
/**
 * POS Sales, Checkout, Split / Pending Due Payment, Custom Promise Due Date & Wrong Billing Correction / Void API Controller
 */
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');
$action = $_GET['action'] ?? $_POST['action'] ?? '';
$db = getDB();

if ($action === 'checkout') {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true) ?: $_POST;

    $memberId = !empty($data['member_id']) ? intval($data['member_id']) : null;
    $subtotal = floatval($data['subtotal'] ?? 0);
    $discount = floatval($data['discount'] ?? 0);
    $couponCode = trim($data['coupon_code'] ?? '');
    $tax = floatval($data['tax'] ?? 0);
    $total = floatval($data['total'] ?? 0);
    $method = trim($data['payment_method'] ?? 'Cash');
    $utrRef = trim($data['utr_ref'] ?? '');
    $notes = trim($data['notes'] ?? '');
    $items = $data['items'] ?? [];

    $statusRequested = trim($data['payment_status'] ?? 'paid'); // 'paid', 'partial', 'unpaid'
    $paidAmount = isset($data['paid_amount']) ? floatval($data['paid_amount']) : $total;
    if ($statusRequested === 'unpaid') {
        $paidAmount = 0.00;
    }
    $dueAmount = max(0, round($total - $paidAmount, 2));
    $paymentStatus = 'paid';
    if ($dueAmount > 0) {
        $paymentStatus = ($paidAmount <= 0) ? 'unpaid' : 'partial';
    }

    // Handle Custom Promise Due Date (e.g. 2 days, 3 days, or custom calendar date)
    $dueDate = null;
    if ($dueAmount > 0) {
        if (!empty($data['due_date'])) {
            $dueDate = trim($data['due_date']);
        } elseif (!empty($data['due_days'])) {
            $dueDays = intval($data['due_days']);
            $dueDate = date('Y-m-d', strtotime("+{$dueDays} days"));
        } else {
            $dueDate = date('Y-m-d', strtotime("+2 days")); // Default 2 days promise
        }
    }

    if (empty($memberId)) {
        jsonResponse(false, [], 'Billing ke liye Member select karna zaroori hai. Please select a valid member.', 400);
    }

    if ($total <= 0 || empty($items)) {
        jsonResponse(false, [], 'Invalid cart items or total amount', 400);
    }

    $invoiceNo = generateInvoiceNo();
    $userId = $_SESSION['user_id'] ?? 1;

    try {
        $db->beginTransaction();

        $stmt = $db->prepare("
            INSERT INTO sales (invoice_no, member_id, subtotal, discount, coupon_code, tax, total, paid_amount, due_amount, due_date, payment_status, payment_method, utr_ref, notes, created_by) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$invoiceNo, $memberId, $subtotal, $discount, $couponCode, $tax, $total, $paidAmount, $dueAmount, $dueDate, $paymentStatus, $method, $utrRef, $notes, $userId]);
        $saleId = $db->lastInsertId();

        foreach ($items as $item) {
            $itemType = $item['type'] ?? 'product';
            $itemId = intval($item['id'] ?? 0);
            $itemName = $item['name'] ?? 'Item';
            $qty = intval($item['qty'] ?? 1);
            $unitPrice = floatval($item['price'] ?? 0);
            $lineTotal = $unitPrice * $qty;
            
            // Calculate proportional item discount and actual net price paid
            $itemDiscount = ($subtotal > 0 && $discount > 0) ? round($discount * ($lineTotal / $subtotal), 2) : 0.00;
            $actualPricePaid = max(0, $lineTotal - $itemDiscount);

            $itemIns = $db->prepare("INSERT INTO sale_items (sale_id, item_type, item_id, item_name, qty, unit_price, discount, total_price) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $itemIns->execute([$saleId, $itemType, $itemId, $itemName, $qty, $unitPrice, $itemDiscount, $actualPricePaid]);

            // Deduct product stock if product type
            if ($itemType === 'product' && $itemId > 0) {
                $stockUpd = $db->prepare("UPDATE products SET stock_quantity = GREATEST(0, stock_quantity - ?) WHERE id = ?");
                $stockUpd->execute([$qty, $itemId]);
            }

            // Create Member Subscription if membership item
            if ($itemType === 'membership' && $memberId > 0) {
                $planStmt = $db->prepare("SELECT duration_months, duration_days, category, title FROM membership_types WHERE id = ?");
                $planStmt->execute([$itemId]);
                $planData = $planStmt->fetch();
                $months = intval($planData['duration_months'] ?? 0);
                $days = !empty($planData['duration_days']) ? intval($planData['duration_days']) : ($months > 0 ? $months * 30 : 1);

                // Auto Renewal Queue Engine: If member currently has an active plan running, new plan queues and starts the next day after it expires!
                $existSubStmt = $db->prepare("
                    SELECT MAX(end_date) as latest_end 
                    FROM member_subscriptions 
                    WHERE member_id = ? AND status IN ('active', 'frozen') AND end_date >= CURRENT_DATE()
                ");
                $existSubStmt->execute([$memberId]);
                $latestEnd = $existSubStmt->fetchColumn();

                if (!empty($latestEnd) && strtotime($latestEnd) >= strtotime('today')) {
                    $startDate = date('Y-m-d', strtotime("{$latestEnd} + 1 day"));
                    $endDate = date('Y-m-d', strtotime("{$startDate} + {$days} days"));
                } else {
                    $startDate = date('Y-m-d');
                    $endDate = date('Y-m-d', strtotime("+{$days} days"));
                    // Expire old overdue/expired subscriptions if starting fresh today
                    $db->prepare("UPDATE member_subscriptions SET status = 'expired' WHERE member_id = ? AND status = 'active'")->execute([$memberId]);
                }

                $subIns = $db->prepare("INSERT INTO member_subscriptions (member_id, membership_type_id, start_date, end_date, original_end_date, price_paid, discount_given, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'active')");
                $subIns->execute([$memberId, $itemId, $startDate, $endDate, $endDate, $actualPricePaid, $itemDiscount]);

                $memUpd = $db->prepare("UPDATE members SET status = 'active' WHERE id = ?");
                $memUpd->execute([$memberId]);

                // Auto-queue Same-Day Trial Welcome & Experience Message if Trial Pass
                $isTrial = (!empty($planData['category']) && $planData['category'] === 'trial') || ($days <= 15) || (stripos($planData['title'] ?? '', 'trial') !== false);
                if ($isTrial && $memberId) {
                    try {
                        $mMem = $db->query("SELECT name, phone, member_code FROM members WHERE id = {$memberId}")->fetch();
                        if ($mMem) {
                            $tTpl = "🏋️‍♂️ Hello {name}! Welcome to THE CLUB 777®! 🎉 We are thrilled you experienced our fitness floor & swimming pool today!\n\n🔥 Hope you had a power-packed workout session! Our certified trainers, Olympic heated pool, and shower/steam facilities are here for your complete transformation.\n\n🎁 SPECIAL TRIAL CONVERSION PERK:\nUpgrade to our Regular 3-Month, 6-Month or Annual Plan within 48 hours & get:\n✅ 100% Admission Fee Waived\n✅ FREE 1-on-1 Personal Training Session\n✅ FREE Customized Nutrition Chart\n\nVisit front desk at Behind Shehnai Garden, Near Railway Station, Jhajjar or call {gym_phone} to claim your offer! 💪";
                            $tMsg = str_replace(
                                ['{name}', '{member_code}', '{phone}', '{gym_phone}'],
                                [$mMem['name'], $mMem['member_code'], $mMem['phone'], getSetting('gym_phone', '8053576777')],
                                $tTpl
                            );
                            $db->prepare("INSERT INTO marketing_logs (member_id, recipient_phone, message_type, trigger_event, message_body, sent_status, sent_at) VALUES (?, ?, 'whatsapp', 'trial_welcome', ?, 'sent', NOW())")
                               ->execute([$memberId, $mMem['phone'], $tMsg]);
                        }
                    } catch (Exception $e) {}
                }
            }

            // Create Pool Subscription if Swimming Pool Plan
            if ($itemType === 'pool_plan' && $memberId > 0) {
                $pStmt = $db->prepare("SELECT * FROM pool_plans WHERE id = ?");
                $pStmt->execute([$itemId]);
                $pData = $pStmt->fetch();
                if ($pData) {
                    $pDays = intval($pData['duration_days'] ?: 30);
                    $pTotal = intval($pData['sessions_limit'] ?? 0);
                    $pSlot = !empty($item['slot_assigned']) ? $item['slot_assigned'] : 'Morning 6:00 AM - 10:00 AM & Evening 5:00 PM - 9:00 PM';
                    
                    // Queue after currently running active pool pass if running
                    $existPoolStmt = $db->prepare("
                        SELECT MAX(end_date) as latest_pool_end 
                        FROM pool_subscriptions 
                        WHERE member_id = ? AND status = 'active' AND end_date >= CURRENT_DATE()
                    ");
                    $existPoolStmt->execute([$memberId]);
                    $latestPoolEnd = $existPoolStmt->fetchColumn();

                    if (!empty($latestPoolEnd) && strtotime($latestPoolEnd) >= strtotime('today')) {
                        $pStart = date('Y-m-d', strtotime("{$latestPoolEnd} + 1 day"));
                        $pEnd = date('Y-m-d', strtotime("{$pStart} + {$pDays} days"));
                    } else {
                        $pStart = date('Y-m-d');
                        $pEnd = date('Y-m-d', strtotime("+{$pDays} days"));
                    }

                    $db->prepare("INSERT INTO pool_subscriptions (member_id, pool_plan_id, start_date, end_date, sessions_total, sessions_used, sessions_remaining, price_paid, slot_assigned, status) VALUES (?, ?, ?, ?, ?, 0, ?, ?, ?, 'active')")
                       ->execute([$memberId, $itemId, $pStart, $pEnd, $pTotal, $pTotal, $actualPricePaid, $pSlot]);
                }
            }

            // Create PT Subscription if Personal Training Package
            if ($itemType === 'pt_package' && $memberId > 0) {
                $ptStmt = $db->prepare("SELECT * FROM pt_packages WHERE id = ?");
                $ptStmt->execute([$itemId]);
                $ptData = $ptStmt->fetch();
                if ($ptData) {
                    $ptDays = intval($ptData['validity_days'] ?? 30);
                    $ptTotal = intval($ptData['sessions_count'] ?? 12);
                    $trainerId = !empty($item['trainer_id']) ? intval($item['trainer_id']) : (!empty($ptData['trainer_id']) ? intval($ptData['trainer_id']) : 1);
                    
                    // Queue after currently running active PT package if running
                    $existPtStmt = $db->prepare("
                        SELECT MAX(expiry_date) as latest_pt_end 
                        FROM pt_subscriptions 
                        WHERE member_id = ? AND status = 'active' AND expiry_date >= CURRENT_DATE()
                    ");
                    $existPtStmt->execute([$memberId]);
                    $latestPtEnd = $existPtStmt->fetchColumn();

                    if (!empty($latestPtEnd) && strtotime($latestPtEnd) >= strtotime('today')) {
                        $ptStart = date('Y-m-d', strtotime("{$latestPtEnd} + 1 day"));
                        $ptEnd = date('Y-m-d', strtotime("{$ptStart} + {$ptDays} days"));
                    } else {
                        $ptStart = date('Y-m-d');
                        $ptEnd = date('Y-m-d', strtotime("+{$ptDays} days"));
                    }

                    $db->prepare("INSERT INTO pt_subscriptions (member_id, pt_package_id, trainer_id, start_date, expiry_date, sessions_total, sessions_used, sessions_remaining, price_paid, status) VALUES (?, ?, ?, ?, ?, ?, 0, ?, ?, 'active')")
                       ->execute([$memberId, $itemId, $trainerId, $ptStart, $ptEnd, $ptTotal, $ptTotal, $actualPricePaid]);
                }
            }
        }

        $db->commit();

        // Fetch Member Details
        $memberName = 'Walk-in Customer';
        $memberPhone = '';
        if ($memberId) {
            $mStmt = $db->prepare("SELECT name, phone FROM members WHERE id = ?");
            $mStmt->execute([$memberId]);
            $mRow = $mStmt->fetch();
            if ($mRow) {
                $memberName = $mRow['name'];
                $memberPhone = $mRow['phone'] ?? '';
            }
        }

        logAuditAction($userId, 'POS Checkout Sale', 'POS', null, [
            'invoice_no' => $invoiceNo, 
            'total' => $total, 
            'paid_amount' => $paidAmount, 
            'due_amount' => $dueAmount, 
            'due_date' => $dueDate,
            'payment_status' => $paymentStatus,
            'payment_method' => $method
        ]);

        $dueMsg = ($dueAmount > 0) ? "Sale completed with pending due of ₹" . number_format($dueAmount, 2) . ($dueDate ? " (Promised by " . date('d M Y', strtotime($dueDate)) . ")" : "") : 'Sale transaction completed successfully!';

        // ── Auto-Generate PDF Invoice & Send via WhatsApp ──────────
        try {
            if ($memberId && !empty($memberPhone)) {
                // 1. Generate PDF to disk
                $invoiceDir = __DIR__ . '/../reports/invoices';
                if (!is_dir($invoiceDir)) {
                    mkdir($invoiceDir, 0755, true);
                }
                $safeInvName = preg_replace('/[^A-Za-z0-9_-]/', '_', $invoiceNo);
                $pdfFileName = "Invoice_{$safeInvName}.pdf";
                $pdfFilePath = $invoiceDir . '/' . $pdfFileName;

                // Internal HTTP call to PDF generator
                $genUrl = 'http://127.0.0.1/GYM/api/generate_invoice_pdf.php?invoice_no=' . urlencode($invoiceNo) . '&output=file';
                $ch = curl_init($genUrl);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 10);
                curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
                if (!empty($_COOKIE['PHPSESSID'])) {
                    curl_setopt($ch, CURLOPT_COOKIE, 'PHPSESSID=' . $_COOKIE['PHPSESSID']);
                }
                $genResp = curl_exec($ch);
                if (PHP_VERSION_ID < 80500 && function_exists('curl_close')) {
                    @curl_close($ch);
                }
                $genData = json_decode($genResp, true);
                $actualPdfPath = $genData['file_path'] ?? $pdfFilePath;

                // 2. Send PDF document via WhatsApp Gateway
                if (file_exists($actualPdfPath)) {
                    $cleanP = preg_replace('/[^0-9]/', '', $memberPhone);
                    if (strlen($cleanP) === 10) $cleanP = '91' . $cleanP;

                    $gymName = getSetting('gym_name', 'THE CLUB 777®');
                    $dueStr = ($dueAmount > 0) ? "\n🔴 *Pending Due:* ₹" . number_format($dueAmount, 2) : "\n🟢 *Status:* FULLY PAID (₹0.00 Due)";
                    $appUrl = rtrim(getSetting('app_url', 'https://gym.ethicscomputer.in'), '/');
                    $receiptUrl = "{$appUrl}/index.php?page=receipt&id=" . intval($saleId);
                    $pdfDownloadUrl = "{$appUrl}/api/generate_invoice_pdf.php?invoice_no=" . urlencode($invoiceNo) . "&output=download";

                    $captionMsg = "📄 *Official Fee Receipt — {$gymName}*\n\n"
                        . "🧾 *Invoice No:* {$invoiceNo}\n"
                        . "👤 *Member:* {$memberName}\n"
                        . "💰 *Total Amount:* ₹" . number_format($total, 2) . "\n"
                        . "🟢 *Amount Paid:* ₹" . number_format($paidAmount, 2) . "{$dueStr}\n"
                        . "📅 *Date:* " . date('d M Y') . "\n\n"
                        . "🔗 *View Official Receipt Online:* \n{$receiptUrl}\n\n"
                        . "📥 *Download Official A4 PDF Invoice:* \n{$pdfDownloadUrl}\n\n"
                        . "📍 Behind Shehnai Garden, Nr Railway Station, Jhajjar\n"
                        . "📞 8053576777, 8053570777\n\n"
                        . "_Stay Strong & Keep Transforming!_ 💪";

                    $nodeUrl = rtrim(getSetting('whatsapp_node_url', 'http://127.0.0.1:3001'), '/') . '/send-document';
                    $ch2 = curl_init($nodeUrl);
                    curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch2, CURLOPT_POST, true);
                    curl_setopt($ch2, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
                    curl_setopt($ch2, CURLOPT_TIMEOUT, 12);
                    curl_setopt($ch2, CURLOPT_SSL_VERIFYPEER, false);
                    curl_setopt($ch2, CURLOPT_SSL_VERIFYHOST, 0);
                    $pdfContent = @file_get_contents($actualPdfPath);
                    $docPayload = [
                        'phone'       => $cleanP,
                        'file_path'   => str_replace('\\', '/', $actualPdfPath),
                        'file_name'   => $pdfFileName,
                        'file_base64' => $pdfContent ? base64_encode($pdfContent) : null,
                        'caption'     => $captionMsg
                    ];
                    curl_setopt($ch2, CURLOPT_POSTFIELDS, json_encode($docPayload));
                    $docResp = curl_exec($ch2);
                    if (PHP_VERSION_ID < 80500 && function_exists('curl_close')) {
                        @curl_close($ch2);
                    }

                    $docResult = json_decode($docResp, true);
                    if (!empty($docResult['success'])) {
                        $db->prepare("INSERT INTO marketing_logs (member_id, recipient_phone, message_type, trigger_event, message_body, sent_status, sent_at) VALUES (?, ?, 'whatsapp', 'pdf_invoice_sent', ?, 'sent', NOW())")
                           ->execute([$memberId, $memberPhone, "PDF Invoice: {$invoiceNo} sent as document"]);
                    } else {
                        // Fallback: send text receipt directly via sendWhatsAppMessage
                        $textRes = sendWhatsAppMessage($cleanP, $captionMsg);
                        if (!empty($textRes['success'])) {
                            $db->prepare("INSERT INTO marketing_logs (member_id, recipient_phone, message_type, trigger_event, message_body, sent_status, sent_at) VALUES (?, ?, 'whatsapp', 'invoice_text_sent', ?, 'sent', NOW())")
                               ->execute([$memberId, $memberPhone, "Fee Receipt: {$invoiceNo} sent via WhatsApp"]);
                        }
                    }
                }
            }
        } catch (Exception $waEx) {
            // Don't fail checkout if WhatsApp dispatch fails
        }

        jsonResponse(true, [
            'sale_id' => $saleId,
            'invoice_no' => $invoiceNo,
            'member_id' => $memberId,
            'member_name' => $memberName,
            'member_phone' => $memberPhone,
            'date' => date('d M Y, h:i A'),
            'subtotal' => $subtotal,
            'discount' => $discount,
            'tax' => $tax,
            'total' => $total,
            'paid_amount' => $paidAmount,
            'due_amount' => $dueAmount,
            'due_date' => $dueDate,
            'due_date_formatted' => $dueDate ? date('d M Y', strtotime($dueDate)) : null,
            'payment_status' => $paymentStatus,
            'payment_method' => $method,
            'utr_ref' => $utrRef,
            'notes' => $notes,
            'items' => $items
        ], $dueMsg);

    } catch (Exception $e) {
        $db->rollBack();
        jsonResponse(false, [], 'Transaction failed: ' . $e->getMessage(), 500);
    }

} elseif ($action === 'collect_due') {
    // Collect pending balance on an existing partial / unpaid invoice
    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $saleId = intval($input['sale_id'] ?? 0);
    $collectAmount = floatval($input['amount'] ?? 0);
    $paymentMethod = trim($input['payment_method'] ?? 'Cash');
    $utrRef = trim($input['utr_ref'] ?? '');
    $userId = $_SESSION['user_id'] ?? 1;

    if ($saleId <= 0 || $collectAmount <= 0) {
        jsonResponse(false, [], 'Valid Sale ID and payment amount required', 400);
    }

    $saleStmt = $db->prepare("SELECT * FROM sales WHERE id = ?");
    $saleStmt->execute([$saleId]);
    $sale = $saleStmt->fetch();

    if (!$sale) {
        jsonResponse(false, [], 'Invoice not found', 404);
    }

    $newPaid = round($sale['paid_amount'] + $collectAmount, 2);
    $newDue = max(0, round($sale['total'] - $newPaid, 2));
    $newStatus = ($newDue <= 0) ? 'paid' : 'partial';
    $newDueDate = ($newDue <= 0) ? null : $sale['due_date'];

    $upd = $db->prepare("
        UPDATE sales 
        SET paid_amount = ?, due_amount = ?, due_date = ?, payment_status = ?, payment_method = ?, utr_ref = ?, updated_at = NOW() 
        WHERE id = ?
    ");
    $upd->execute([$newPaid, $newDue, $newDueDate, $newStatus, $paymentMethod, $utrRef, $saleId]);

    logAuditAction($userId, 'Collect Pending Dues', 'POS', ['due_before' => $sale['due_amount']], [
        'collected' => $collectAmount,
        'due_remaining' => $newDue,
        'status' => $newStatus
    ]);

    // Auto-dispatch Due Receipt WhatsApp Message
    try {
        if (!empty($sale['member_id'])) {
            $mRow = $db->query("SELECT name, phone FROM members WHERE id = " . intval($sale['member_id']))->fetch();
            if ($mRow && !empty($mRow['phone'])) {
                $dueStatusText = ($newDue > 0) ? "🔴 *Remaining Due:* ₹" . number_format($newDue, 2) : "🎉 *Status:* ALL DUES FULLY CLEARED (₹0.00)";
                $dueMsg = "✅ *Payment Received — THE CLUB 777®* 🧾\n\nHello *{$mRow['name']}*!\nWe have received your payment of *₹" . number_format($collectAmount, 2) . "* against Invoice #*{$sale['invoice_no']}* via {$paymentMethod}.\n\n💰 *Total Bill:* ₹" . number_format($sale['total'], 2) . "\n🟢 *Total Paid:* ₹" . number_format($newPaid, 2) . "\n{$dueStatusText}\n\n📍 *Address:* Behind Shehnai Garden, Near Railway Station, Jhajjar\n📞 *Helpline:* 8053576777, 8053570777\n\n_Thank you for working out with us!_ 💪";
                
                sendWhatsAppMessage($mRow['phone'], $dueMsg);
                $db->prepare("INSERT INTO marketing_logs (member_id, recipient_phone, message_type, trigger_event, message_body, sent_status, sent_at) VALUES (?, ?, 'whatsapp', 'due_payment_cleared', ?, 'sent', NOW())")
                   ->execute([$sale['member_id'], $mRow['phone'], $dueMsg]);
            }
        }
    } catch (Exception $e) {}

    jsonResponse(true, [
        'sale_id' => $saleId,
        'paid_amount' => $newPaid,
        'due_amount' => $newDue,
        'payment_status' => $newStatus
    ], ($newDue <= 0 ? 'Full pending dues cleared successfully!' : "Payment of ₹" . number_format($collectAmount, 2) . " received. Remaining due: ₹" . number_format($newDue, 2)));

} elseif ($action === 'void_invoice' || $action === 'cancel_wrong_bill') {
    // Void / Cancel a Wrong Billing Invoice & Restore Inventory / Subscriptions
    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $saleId = intval($input['sale_id'] ?? 0);
    $reason = trim($input['reason'] ?? 'Wrong Billing Entry');
    $userId = $_SESSION['user_id'] ?? 1;

    if ($saleId <= 0) {
        jsonResponse(false, [], 'Valid Sale/Invoice ID is required', 400);
    }

    $saleStmt = $db->prepare("SELECT * FROM sales WHERE id = ?");
    $saleStmt->execute([$saleId]);
    $sale = $saleStmt->fetch();

    if (!$sale) {
        jsonResponse(false, [], 'Invoice not found', 404);
    }

    if ($sale['payment_status'] === 'voided' || $sale['payment_status'] === 'cancelled') {
        jsonResponse(false, [], 'This invoice has already been voided and reversed', 400);
    }

    try {
        $db->beginTransaction();

        // 1. Update sale payment_status to 'voided' and store cancellation reason
        $cancelNote = !empty($sale['notes']) ? $sale['notes'] . " | VOIDED: " . $reason : "VOIDED: " . $reason;
        $updSale = $db->prepare("UPDATE sales SET payment_status = 'voided', notes = ?, updated_at = NOW() WHERE id = ?");
        $updSale->execute([$cancelNote, $saleId]);

        // 2. Fetch sale items to reverse effects
        $itemsStmt = $db->prepare("SELECT * FROM sale_items WHERE sale_id = ?");
        $itemsStmt->execute([$saleId]);
        $items = $itemsStmt->fetchAll();

        foreach ($items as $item) {
            // Restore Product stock
            if ($item['item_type'] === 'product' && $item['item_id'] > 0) {
                $restoreStock = $db->prepare("UPDATE products SET stock_quantity = stock_quantity + ? WHERE id = ?");
                $restoreStock->execute([$item['qty'], $item['item_id']]);
            }

            // Cancel any generated Member Subscription created from this wrong sale
            if ($item['item_type'] === 'membership' && $sale['member_id'] > 0) {
                $cancelSub = $db->prepare("
                    UPDATE member_subscriptions 
                    SET status = 'cancelled' 
                    WHERE member_id = ? AND membership_type_id = ? AND DATE(created_at) = DATE(?) 
                    ORDER BY id DESC LIMIT 1
                ");
                $cancelSub->execute([$sale['member_id'], $item['item_id'], $sale['created_at']]);
            }
        }

        // 3. Record reversal refund log entry
        $refStmt = $db->prepare("
            INSERT INTO refunds (sale_id, member_id, refund_amount, reason, approved_by, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $refStmt->execute([$saleId, $sale['member_id'] ?: null, $sale['paid_amount'], "Wrong Billing Void: {$reason}", $userId]);

        $db->commit();

        logAuditAction($userId, 'Void Wrong Billing Invoice', 'POS', ['sale_id' => $saleId, 'invoice_no' => $sale['invoice_no']], [
            'reversed_amount' => $sale['paid_amount'],
            'reason' => $reason
        ]);

        jsonResponse(true, [
            'sale_id' => $saleId,
            'invoice_no' => $sale['invoice_no'],
            'status' => 'voided'
        ], "Invoice {$sale['invoice_no']} successfully VOIDED and all subscriptions & product stock reversed!");

    } catch (Exception $e) {
        $db->rollBack();
        jsonResponse(false, [], 'Void transaction failed: ' . $e->getMessage(), 500);
    }

} elseif ($action === 'edit_invoice') {
    // Edit details of an existing invoice (Reassign Member, Change Payment Mode, UTR, Due Date)
    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $saleId = intval($input['sale_id'] ?? 0);
    $memberId = !empty($input['member_id']) ? intval($input['member_id']) : null;
    $paymentMethod = trim($input['payment_method'] ?? 'Cash');
    $utrRef = trim($input['utr_ref'] ?? '');
    $dueDate = !empty($input['due_date']) ? trim($input['due_date']) : null;
    $notes = trim($input['notes'] ?? '');
    $userId = $_SESSION['user_id'] ?? 1;

    if ($saleId <= 0) {
        jsonResponse(false, [], 'Valid Sale ID required', 400);
    }

    $stmt = $db->prepare("UPDATE sales SET member_id = ?, payment_method = ?, utr_ref = ?, due_date = ?, notes = ?, updated_at = NOW() WHERE id = ?");
    $stmt->execute([$memberId, $paymentMethod, $utrRef, $dueDate, $notes, $saleId]);

    logAuditAction($userId, 'Edit Invoice Details', 'POS', ['sale_id' => $saleId], [
        'member_id' => $memberId,
        'payment_method' => $paymentMethod,
        'due_date' => $dueDate,
        'utr_ref' => $utrRef
    ]);

    jsonResponse(true, ['sale_id' => $saleId], 'Invoice details updated successfully!');

} elseif ($action === 'collect_due') {
    // Settle pending balance / collect due amount for an existing sale
    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $saleId = intval($input['sale_id'] ?? 0);
    $collectAmount = floatval($input['amount'] ?? 0);
    $paymentMethod = trim($input['payment_method'] ?? 'Cash');
    $utrRef = trim($input['utr_ref'] ?? '');
    $userId = $_SESSION['user_id'] ?? 1;

    if ($saleId <= 0 || $collectAmount <= 0) {
        jsonResponse(false, [], 'Valid Sale ID and positive collected amount required', 400);
    }

    $stmt = $db->prepare("SELECT * FROM sales WHERE id = ?");
    $stmt->execute([$saleId]);
    $sale = $stmt->fetch();

    if (!$sale) {
        jsonResponse(false, [], 'Invoice not found', 404);
    }

    $currentPaid = floatval($sale['paid_amount'] ?? 0);
    $currentDue = floatval($sale['due_amount'] ?? 0);
    $newPaid = $currentPaid + $collectAmount;
    $newDue = max(0, $currentDue - $collectAmount);
    $newStatus = ($newDue <= 0.01) ? 'paid' : 'partial';

    $upd = $db->prepare("
        UPDATE sales 
        SET paid_amount = ?, due_amount = ?, payment_status = ?, payment_method = ?, utr_ref = ?, updated_at = NOW() 
        WHERE id = ?
    ");
    $upd->execute([$newPaid, $newDue, $newStatus, $paymentMethod, $utrRef ?: $sale['utr_ref'], $saleId]);

    logAuditAction($userId, 'Collect Pending Due', 'POS', [
        'sale_id' => $saleId,
        'invoice_no' => $sale['invoice_no'],
        'prev_due' => $currentDue
    ], [
        'collected' => $collectAmount,
        'new_due' => $newDue,
        'status' => $newStatus
    ]);

    $currency = getSetting('currency_symbol', '₹');
    jsonResponse(true, [
        'sale_id' => $saleId,
        'invoice_no' => $sale['invoice_no'],
        'collected' => $collectAmount,
        'new_due' => $newDue,
        'status' => $newStatus
    ], "Payment of {$currency}{$collectAmount} recorded successfully!");

} elseif ($action === 'get_by_barcode') {
    // Barcode / SKU Hardware & Camera Scanner lookup
    $code = trim($_GET['code'] ?? '');
    if (empty($code)) {
        jsonResponse(false, [], 'Barcode code is required', 400);
    }

    // 1. Search Products by barcode or sku
    $stmt = $db->prepare("SELECT id, name, price, 'product' as type, stock_quantity FROM products WHERE (barcode = ? OR sku = ?) AND status = 'active' LIMIT 1");
    $stmt->execute([$code, $code]);
    $item = $stmt->fetch();

    // 2. Search Membership Plans if matching
    if (!$item) {
        $stmt2 = $db->prepare("SELECT id, title as name, price, 'membership' as type, duration_days FROM membership_types WHERE (title LIKE ? OR id = ?) AND status = 'active' LIMIT 1");
        $stmt2->execute(["%{$code}%", intval($code)]);
        $item = $stmt2->fetch();
    }

    // 3. Search Pool Plans if matching
    if (!$item) {
        $stmt3 = $db->prepare("SELECT id, title as name, price, 'pool_plan' as type, duration_days FROM pool_plans WHERE (title LIKE ? OR id = ?) AND status = 'active' LIMIT 1");
        $stmt3->execute(["%{$code}%", intval($code)]);
        $item = $stmt3->fetch();
    }

    if ($item) {
        $item['price'] = floatval($item['price']);
        jsonResponse(true, $item, 'Item found');
    } else {
        jsonResponse(false, [], 'No catalog product or plan found for this code', 404);
    }

} else {
    jsonResponse(false, [], 'Invalid action', 400);
}
