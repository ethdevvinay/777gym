<?php
/**
 * FITZONE Node.js WhatsApp Gateway Bridge Controller
 * Handles live status checking, QR code streaming, direct automated messaging, and fallback dispatch.
 */
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');
$action = $_GET['action'] ?? $_POST['action'] ?? '';
$db = getDB();

$nodeGatewayUrl = getSetting('whatsapp_node_url', 'http://127.0.0.1:3001');

// Helper: Make cURL request to local Node.js Gateway
function callNodeGateway(string $endpoint, string $method = 'GET', array $data = []): array {
    global $nodeGatewayUrl;
    $url = rtrim($nodeGatewayUrl, '/') . '/' . ltrim($endpoint, '/');

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 12);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    if (PHP_VERSION_ID < 80500 && function_exists('curl_close')) {
        @curl_close($ch);
    }

    if ($curlErr || !$response) {
        return [
            'online' => false,
            'success' => false,
            'status' => 'offline',
            'error' => 'Node.js WhatsApp service is currently offline. Please run start_whatsapp_gateway.bat on your server.'
        ];
    }

    $decoded = json_decode($response, true) ?: [];
    $decoded['online'] = true;
    return $decoded;
}

if ($action === 'status') {
    $res = callNodeGateway('status');
    jsonResponse(true, $res, 'Gateway status fetched');

} elseif ($action === 'qr') {
    $res = callNodeGateway('qr');
    jsonResponse(true, $res, 'QR status fetched');

} elseif ($action === 'send') {
    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $phone = trim($input['phone'] ?? '');
    $message = trim($input['message'] ?? '');

    if (empty($phone) || empty($message)) {
        jsonResponse(false, [], 'Phone number and message are required', 400);
    }

    $res = callNodeGateway('send', 'POST', [
        'phone' => $phone,
        'message' => $message
    ]);

    if (!empty($res['success'])) {
        logAuditAction($_SESSION['user_id'] ?? 1, 'Send Direct Node.js WhatsApp', 'COMMUNICATION', null, ['phone' => $phone]);
        jsonResponse(true, $res, 'Message sent successfully via Node.js WhatsApp Multi-Device Gateway!');
    } else {
        // Fallback wa.me URL
        $cleanPhone = preg_replace('/[^0-9]/', '', $phone);
        if (strlen($cleanPhone) === 10) $cleanPhone = '91' . $cleanPhone;
        $waUrl = "https://wa.me/{$cleanPhone}?text=" . urlencode($message);

        jsonResponse(false, [
            'fallback_url' => $waUrl,
            'node_error' => $res['error'] ?? 'Service unavailable'
        ], 'Direct Node dispatch unavailable. Fallback WhatsApp link generated.');
    }

} elseif ($action === 'logout') {
    $res = callNodeGateway('logout', 'POST');
    jsonResponse(true, $res, 'WhatsApp session reset initiated');

} elseif ($action === 'test_ping') {
    $adminPhone = trim($_POST['phone'] ?? getSetting('gym_phone', '9876543210'));
    $gymName = getSetting('gym_name', 'THE CLUB 777®');
    $testMsg = "🚀 *THE CLUB 777® WhatsApp Gateway Connected!*\n\nHello from {$gymName}!\nYour local Node.js Multi-Device WhatsApp connection is active and operational.\n\n⏰ Time: " . date('d M Y, h:i:s A') . "\n🛡️ Automated Notifications & Reminders are now 100% Active!";

    $res = callNodeGateway('send', 'POST', [
        'phone' => $adminPhone,
        'message' => $testMsg
    ]);

    if (!empty($res['success'])) {
        jsonResponse(true, $res, "Test ping message delivered to +{$adminPhone}!");
    } else {
        jsonResponse(false, $res, $res['error'] ?? 'Failed to send test message');
    }

} elseif ($action === 'send_document') {
    // Send a PDF document via WhatsApp
    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $phone     = trim($input['phone'] ?? '');
    $invoiceNo = trim($input['invoice_no'] ?? '');
    $filePath  = trim($input['file_path'] ?? '');
    $fileName  = trim($input['file_name'] ?? '');
    $caption   = trim($input['caption'] ?? '');

    if (empty($phone)) {
        jsonResponse(false, [], 'Phone number is required', 400);
    }

    // If invoice_no provided but no file_path, generate the PDF first
    if (!empty($invoiceNo) && empty($filePath)) {
        $invoiceDir = __DIR__ . '/../reports/invoices';
        if (!is_dir($invoiceDir)) {
            mkdir($invoiceDir, 0755, true);
        }
        $safeInvoice = preg_replace('/[^A-Za-z0-9_-]/', '_', $invoiceNo);
        $targetFile = $invoiceDir . '/Invoice_' . $safeInvoice . '.pdf';

        // Generate the PDF by making an internal HTTP call to the generator
        $genUrl = 'http://127.0.0.1/GYM/api/generate_invoice_pdf.php?invoice_no=' . urlencode($invoiceNo) . '&output=file';
        $ch = curl_init($genUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        // Forward session cookie for auth
        if (!empty($_COOKIE['PHPSESSID'])) {
            curl_setopt($ch, CURLOPT_COOKIE, 'PHPSESSID=' . $_COOKIE['PHPSESSID']);
        }
        $genResp = curl_exec($ch);
        if (PHP_VERSION_ID < 80500 && function_exists('curl_close')) {
            @curl_close($ch);
        }

        $genData = json_decode($genResp, true);
        if (!empty($genData['success']) && !empty($genData['file_path'])) {
            $filePath = $genData['file_path'];
            $fileName = $genData['file_name'] ?? $fileName;
        } else {
            jsonResponse(false, ['gen_error' => $genData], 'Failed to generate PDF invoice');
        }
    }

    if (empty($filePath) || !file_exists($filePath)) {
        jsonResponse(false, [], 'PDF file not found or could not be generated', 404);
    }

    if (empty($fileName)) {
        $fileName = basename($filePath);
    }

    $appUrl = rtrim(getSetting('app_url', 'https://gym.ethicscomputer.in'), '/');
    $receiptUrl = "{$appUrl}/index.php?page=receipt&invoice_no=" . urlencode($invoiceNo);
    $pdfDownloadUrl = "{$appUrl}/api/generate_invoice_pdf.php?invoice_no=" . urlencode($invoiceNo) . "&output=download";

    if (empty($caption)) {
        $gymName = getSetting('gym_name', 'THE CLUB 777®');
        $caption = "📄 *Official Fee Receipt — {$gymName}*\n\n"
            . "🧾 *Invoice No:* {$invoiceNo}\n\n"
            . "🔗 *View Official Receipt Online:* \n{$receiptUrl}\n\n"
            . "📥 *Download Official A4 PDF Invoice:* \n{$pdfDownloadUrl}\n\n"
            . "📍 Behind Shehnai Garden, Near Railway Station, Jhajjar - 124103\n"
            . "📞 Helpline: 8053576777, 8053570777\n\n"
            . "_Stay Strong & Keep Transforming!_ 💪";
    }

    // Send document via Node.js Gateway
    $pdfFileContent = @file_get_contents($filePath);
    $res = callNodeGateway('send-document', 'POST', [
        'phone'       => $phone,
        'file_path'   => str_replace('\\', '/', $filePath),
        'file_name'   => $fileName,
        'file_base64' => $pdfFileContent ? base64_encode($pdfFileContent) : null,
        'caption'     => $caption
    ]);

    if (!empty($res['success'])) {
        logAuditAction($_SESSION['user_id'] ?? 1, 'Send PDF Invoice via WhatsApp', 'COMMUNICATION', null, [
            'phone' => $phone, 'invoice_no' => $invoiceNo, 'file' => $fileName
        ]);
        jsonResponse(true, $res, 'PDF Invoice sent successfully via WhatsApp!');
    } else {
        $cleanPhone = preg_replace('/[^0-9]/', '', $phone);
        if (strlen($cleanPhone) === 10) $cleanPhone = '91' . $cleanPhone;

        // Auto-fallback: Dispatch full receipt text directly via WhatsApp Gateway
        $textRes = callNodeGateway('send', 'POST', [
            'phone'   => $cleanPhone,
            'message' => $caption
        ]);

        if (!empty($textRes['success'])) {
            logAuditAction($_SESSION['user_id'] ?? 1, 'Send Receipt Text via WhatsApp', 'COMMUNICATION', null, [
                'phone' => $phone, 'invoice_no' => $invoiceNo
            ]);
            jsonResponse(true, $textRes, 'Official fee receipt sent successfully to member via WhatsApp!');
        }

        // Final fallback: wa.me manual link
        $waUrl = "https://wa.me/{$cleanPhone}?text=" . urlencode($caption);

        jsonResponse(false, [
            'fallback_url' => $waUrl,
            'node_error'   => $res['error'] ?? 'Document service unavailable'
        ], 'Direct dispatch unavailable. Fallback WhatsApp link generated.');
    }

} elseif (!empty($action)) {
    jsonResponse(false, [], 'Invalid action', 400);
}
