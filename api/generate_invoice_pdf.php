<?php
/**
 * THE CLUB 777® — Professional A4 Invoice PDF Generator
 * Generates a branded PDF invoice for a given sale/invoice_no and saves to disk.
 * 
 * Usage:
 *   GET  api/generate_invoice_pdf.php?invoice_no=INV-XXXX          → Force download PDF
 *   GET  api/generate_invoice_pdf.php?invoice_no=INV-XXXX&output=file  → Save to disk & return JSON path
 *   POST api/generate_invoice_pdf.php  { invoice_no: "INV-XXXX" }  → Save to disk & return JSON path
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../lib/fpdf/fpdf.php';

// ─── Resolve invoice ────────────────────────────────────────────
$input = json_decode(file_get_contents('php://input'), true) ?: [];
$invoiceNo = $_GET['invoice_no'] ?? $input['invoice_no'] ?? '';
$saleId    = $_GET['id'] ?? $input['id'] ?? '';
$outputMode = $_GET['output'] ?? $input['output'] ?? 'download'; // 'download' | 'file'

if (empty($invoiceNo) && empty($saleId)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'invoice_no or id is required']);
    exit;
}

$db = getDB();

if ($invoiceNo) {
    $stmt = $db->prepare("
        SELECT s.*, m.name as member_name, m.member_code, m.phone as member_phone, m.email as member_email, m.gender
        FROM sales s LEFT JOIN members m ON s.member_id = m.id
        WHERE s.invoice_no = ?
    ");
    $stmt->execute([$invoiceNo]);
} else {
    $stmt = $db->prepare("
        SELECT s.*, m.name as member_name, m.member_code, m.phone as member_phone, m.email as member_email, m.gender
        FROM sales s LEFT JOIN members m ON s.member_id = m.id
        WHERE s.id = ?
    ");
    $stmt->execute([$saleId]);
}

$sale = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$sale) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Sale/Invoice not found']);
    exit;
}

// Fetch sale items
$stmtItems = $db->prepare("
    SELECT si.*, IFNULL(si.item_name, p.name) as product_name, 
           IFNULL(si.item_type, 'Service') as product_type, si.qty as quantity
    FROM sale_items si LEFT JOIN products p ON (si.item_type = 'product' AND si.item_id = p.id)
    WHERE si.sale_id = ?
");
$stmtItems->execute([$sale['id']]);
$saleItems = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

// ─── Settings ───────────────────────────────────────────────────
$gymName    = getSetting('gym_name', 'THE CLUB 777®');
$gymPhone   = getSetting('gym_phone', '8053576777, 8053570777, 9416528777');
$gymEmail   = getSetting('gym_email', 'theclub777jjr@gmail.com');
$gymAddress = getSetting('gym_address', 'Behind Shehnai Garden, Near Railway Station, Jhajjar - 124103 (Haryana)');
$currency   = 'Rs.';

// ─── Build PDF ──────────────────────────────────────────────────
class InvoicePDF extends FPDF {
    public $gymName;
    public $gymPhone;
    public $gymEmail;
    public $gymAddress;

    function Header() {
        // Gold accent bar at top
        $this->SetFillColor(180, 83, 9); // Amber-700
        $this->Rect(0, 0, 210, 4, 'F');

        // Gym Name
        $this->SetY(10);
        $this->SetFont('Arial', 'B', 22);
        $this->SetTextColor(15, 23, 42);
        $this->Cell(0, 10, $this->convUtf8($this->gymName), 0, 1, 'L');

        // Tagline
        $this->SetFont('Arial', 'B', 9);
        $this->SetTextColor(146, 64, 14);
        $this->Cell(0, 5, 'GYM, SWIM & MORE... (Jhajjar, Haryana)', 0, 1, 'L');

        // Address & Phone & Email
        $this->SetFont('Arial', '', 8);
        $this->SetTextColor(71, 85, 105);
        $this->Cell(0, 4, $this->convUtf8($this->gymAddress), 0, 1, 'L');
        $this->Cell(0, 4, 'Phone: ' . $this->gymPhone . '  |  Email: ' . $this->gymEmail, 0, 1, 'L');

        // OFFICIAL FEE RECEIPT badge on right
        $this->SetY(10);
        $this->SetFont('Arial', 'B', 10);
        $this->SetFillColor(15, 23, 42);
        $this->SetTextColor(255, 255, 255);
        $this->Cell(0, 7, '  OFFICIAL FEE RECEIPT  ', 0, 1, 'R', true);

        // Separator line
        $this->SetY(38);
        $this->SetDrawColor(203, 213, 225);
        $this->SetLineWidth(0.5);
        $this->Line(10, $this->GetY(), 200, $this->GetY());
        $this->Ln(3);
    }

    function Footer() {
        $this->SetY(-25);
        $this->SetDrawColor(203, 213, 225);
        $this->SetLineWidth(0.3);
        $this->Line(10, $this->GetY(), 200, $this->GetY());

        $this->Ln(3);
        $this->SetFont('Arial', '', 7);
        $this->SetTextColor(100, 116, 139);
        $this->Cell(0, 4, 'This is a computer-generated authorized digital receipt. No physical signature required.', 0, 1, 'C');
        $this->Cell(0, 4, 'Thank you for being part of our fitness family! - ' . $this->convUtf8($this->gymName), 0, 1, 'C');

        // Bottom accent bar
        $this->SetFillColor(180, 83, 9);
        $this->Rect(0, 293, 210, 4, 'F');
    }

    function convUtf8($str) {
        return iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', $str ?: '');
    }
}

$pdf = new InvoicePDF();
$pdf->gymName    = $gymName;
$pdf->gymPhone   = $gymPhone;
$pdf->gymEmail   = $gymEmail;
$pdf->gymAddress = $gymAddress;
$pdf->SetAutoPageBreak(true, 30);
$pdf->AddPage();

// ─── Invoice Meta Section ───────────────────────────────────────
$pdf->SetFont('Arial', 'B', 11);
$pdf->SetTextColor(15, 23, 42);
$pdf->Cell(95, 7, 'Invoice No: ' . ($sale['invoice_no'] ?? 'N/A'), 0, 0, 'L');

$dateFormatted = !empty($sale['created_at']) ? date('d M Y, h:i A', strtotime($sale['created_at'])) : date('d M Y');
$pdf->SetFont('Arial', '', 10);
$pdf->SetTextColor(71, 85, 105);
$pdf->Cell(95, 7, 'Date: ' . $dateFormatted, 0, 1, 'R');

$pdf->Ln(2);

// ─── Billed To Panel ───────────────────────────────────────────
$pdf->SetFillColor(248, 250, 252);
$pdf->SetDrawColor(226, 232, 240);
$pdf->Rect(10, $pdf->GetY(), 190, 22, 'DF');

$py = $pdf->GetY() + 2;
$pdf->SetXY(14, $py);
$pdf->SetFont('Arial', 'B', 7);
$pdf->SetTextColor(148, 163, 184);
$pdf->Cell(80, 4, 'BILLED TO (CLIENT DETAILS):', 0, 0, 'L');

$pdf->SetX(110);
$pdf->Cell(80, 4, 'PAYMENT & ISSUANCE:', 0, 1, 'R');

$pdf->SetX(14);
$pdf->SetFont('Arial', 'B', 12);
$pdf->SetTextColor(15, 23, 42);
$pdf->Cell(80, 7, $pdf->convUtf8($sale['member_name'] ?: 'Walk-in Customer'), 0, 0, 'L');

$pdf->SetX(110);
$pdf->SetFont('Arial', '', 9);
$pdf->SetTextColor(71, 85, 105);
$payMethod = strtoupper($sale['payment_method'] ?? 'CASH');
$pdf->Cell(80, 7, 'Payment Mode: ' . $payMethod, 0, 1, 'R');

$pdf->SetX(14);
$pdf->SetFont('Arial', '', 9);
$pdf->SetTextColor(71, 85, 105);
$pdf->Cell(80, 5, 'Member ID: ' . ($sale['member_code'] ?: 'GUEST') . '   Phone: ' . ($sale['member_phone'] ?: 'N/A'), 0, 0, 'L');

$isDue = (!empty($sale['due_amount']) && $sale['due_amount'] > 0);
$pdf->SetX(110);
$pdf->SetFont('Arial', 'B', 9);
if ($isDue) {
    $pdf->SetTextColor(220, 38, 38);
    $pdf->Cell(80, 5, 'Status: PARTIAL / DUE', 0, 1, 'R');
} else {
    $pdf->SetTextColor(5, 150, 105);
    $pdf->Cell(80, 5, 'Status: PAID IN FULL', 0, 1, 'R');
}

$pdf->Ln(6);

// ─── Itemized Table ─────────────────────────────────────────────
// Table Header
$pdf->SetFillColor(241, 245, 249);
$pdf->SetTextColor(71, 85, 105);
$pdf->SetFont('Arial', 'B', 8);
$pdf->SetDrawColor(203, 213, 225);
$pdf->Cell(12, 7, '#', 1, 0, 'C', true);
$pdf->Cell(98, 7, 'Description of Services / Products', 1, 0, 'L', true);
$pdf->Cell(20, 7, 'Qty', 1, 0, 'C', true);
$pdf->Cell(30, 7, 'Rate (' . $currency . ')', 1, 0, 'R', true);
$pdf->Cell(30, 7, 'Amount (' . $currency . ')', 1, 1, 'R', true);

// Table Body
$pdf->SetFont('Arial', '', 9);
$pdf->SetTextColor(15, 23, 42);
if (!empty($saleItems)) {
    $idx = 1;
    foreach ($saleItems as $item) {
        $pdf->Cell(12, 7, $idx++, 'LRB', 0, 'C');
        $pdf->Cell(98, 7, $pdf->convUtf8($item['product_name'] ?: 'Gym Membership'), 'LRB', 0, 'L');
        $pdf->Cell(20, 7, $item['quantity'], 'LRB', 0, 'C');
        $pdf->Cell(30, 7, number_format($item['unit_price'], 2), 'LRB', 0, 'R');
        $pdf->Cell(30, 7, number_format($item['total_price'], 2), 'LRB', 1, 'R');
    }
} else {
    $pdf->Cell(12, 7, '1', 'LRB', 0, 'C');
    $pdf->Cell(98, 7, 'Gym Membership / Service', 'LRB', 0, 'L');
    $pdf->Cell(20, 7, '1', 'LRB', 0, 'C');
    $subtotalAmt = $sale['subtotal'] ?? $sale['total'];
    $pdf->Cell(30, 7, number_format($subtotalAmt, 2), 'LRB', 0, 'R');
    $pdf->Cell(30, 7, number_format($subtotalAmt, 2), 'LRB', 1, 'R');
}

$pdf->Ln(5);

// ─── Summary Section (Right-Aligned) ────────────────────────────
$summaryX = 120;
$summaryW = 70;

// Subtotal
$pdf->SetX($summaryX);
$pdf->SetFont('Arial', '', 9);
$pdf->SetTextColor(71, 85, 105);
$pdf->Cell(40, 6, 'Subtotal Amount:', 0, 0, 'L');
$pdf->SetFont('Arial', 'B', 9);
$pdf->Cell(30, 6, $currency . ' ' . number_format($sale['subtotal'] ?? $sale['total'], 2), 0, 1, 'R');

// Discount (if any)
if (!empty($sale['discount']) && $sale['discount'] > 0) {
    $pdf->SetX($summaryX);
    $pdf->SetFont('Arial', '', 9);
    $pdf->SetTextColor(5, 150, 105);
    $pdf->Cell(40, 6, 'Special Discount:', 0, 0, 'L');
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->Cell(30, 6, '- ' . $currency . ' ' . number_format($sale['discount'], 2), 0, 1, 'R');
}

// Grand Total
$pdf->SetDrawColor(203, 213, 225);
$pdf->SetX($summaryX);
$pdf->Line($summaryX, $pdf->GetY(), $summaryX + $summaryW, $pdf->GetY());
$pdf->Ln(1);
$pdf->SetX($summaryX);
$pdf->SetFont('Arial', 'B', 12);
$pdf->SetTextColor(15, 23, 42);
$pdf->Cell(40, 8, 'TOTAL PAYABLE:', 0, 0, 'L');
$pdf->Cell(30, 8, $currency . ' ' . number_format($sale['total'], 2), 0, 1, 'R');
$pdf->SetX($summaryX);
$pdf->Line($summaryX, $pdf->GetY(), $summaryX + $summaryW, $pdf->GetY());
$pdf->Ln(1);

// Paid Amount
$pdf->SetX($summaryX);
$pdf->SetFont('Arial', 'B', 10);
$pdf->SetTextColor(5, 150, 105);
$pdf->Cell(40, 6, 'Paid Amount:', 0, 0, 'L');
$pdf->Cell(30, 6, $currency . ' ' . number_format($sale['paid_amount'], 2), 0, 1, 'R');

// Due Amount (if any)
if ($isDue) {
    $pdf->SetX($summaryX);
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->SetTextColor(220, 38, 38);
    $pdf->SetFillColor(254, 242, 242);
    $pdf->Cell(40, 6, 'OUTSTANDING DUE:', 0, 0, 'L');
    $pdf->Cell(30, 6, $currency . ' ' . number_format($sale['due_amount'], 2), 0, 1, 'R');

    if (!empty($sale['due_date'])) {
        $pdf->SetX($summaryX);
        $pdf->SetFont('Arial', '', 8);
        $pdf->Cell($summaryW, 5, 'Promised By: ' . date('d M Y', strtotime($sale['due_date'])), 0, 1, 'R');
    }
}

// ─── Terms & Conditions (Left Side) ────────────────────────────
$termsY = $pdf->GetY() + 8;
if ($termsY < 210) { // Only show if space is available
    $pdf->SetXY(10, $termsY);
    $pdf->SetFont('Arial', 'B', 8);
    $pdf->SetTextColor(71, 85, 105);
    $pdf->Cell(0, 5, 'TERMS & GYM POLICIES:', 0, 1, 'L');

    $pdf->SetFont('Arial', '', 7);
    $pdf->SetTextColor(100, 116, 139);
    $terms = [
        '1. Fees once paid are non-refundable and non-transferable under any circumstances.',
        '2. Membership freeze requests must be submitted in advance as per plan limits.',
        '3. Members must carry clean workout shoes & towel on gym floor.',
        '4. This is a computer-generated authorized digital receipt.'
    ];
    foreach ($terms as $term) {
        $pdf->SetX(14);
        $pdf->Cell(0, 4, $term, 0, 1, 'L');
    }
}

// ─── Authorized Signatory ──────────────────────────────────────
$signY = max($pdf->GetY() + 10, 240);
if ($signY > 265) $signY = 265;
$pdf->SetXY(140, $signY);
$pdf->SetDrawColor(148, 163, 184);
$pdf->Line(140, $signY, 195, $signY);
$pdf->Ln(2);
$pdf->SetX(140);
$pdf->SetFont('Arial', 'B', 8);
$pdf->SetTextColor(15, 23, 42);
$pdf->Cell(55, 4, 'Authorized Signatory & Stamp', 0, 1, 'C');
$pdf->SetX(140);
$pdf->SetFont('Arial', '', 7);
$pdf->SetTextColor(100, 116, 139);
$pdf->Cell(55, 4, $pdf->convUtf8($gymName), 0, 1, 'C');

// ─── Add RoundedRect method to FPDF ────────────────────────────
// Note: FPDF doesn't have RoundedRect natively, so we draw a regular rect
// We already called it above, so let's make sure the method exists.

// ─── Output ─────────────────────────────────────────────────────
$invoiceFileName = 'Invoice_' . preg_replace('/[^A-Za-z0-9_-]/', '_', $sale['invoice_no'] ?? 'UNKNOWN') . '.pdf';

if ($outputMode === 'file') {
    // Save to disk and return path
    $invoiceDir = __DIR__ . '/../reports/invoices';
    if (!is_dir($invoiceDir)) {
        mkdir($invoiceDir, 0755, true);
    }
    $filePath = $invoiceDir . '/' . $invoiceFileName;
    $pdf->Output('F', $filePath);

    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'file_path' => realpath($filePath),
        'file_name' => $invoiceFileName,
        'file_url' => 'reports/invoices/' . $invoiceFileName,
        'invoice_no' => $sale['invoice_no'],
        'member_name' => $sale['member_name'],
        'member_phone' => $sale['member_phone']
    ]);
} else {
    // Force download
    $pdf->Output('D', $invoiceFileName);
}
