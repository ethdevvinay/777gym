<?php
/**
 * Financial, Business Analytics & Automated Daily Excel Generation Engine
 * Generates structured, styled multi-sheet Excel workbooks (.xls XML Spreadsheet) & auto-saves to disk.
 */
require_once __DIR__ . '/../config/database.php';

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$db = getDB();

// Ensure daily excel storage directory exists
$reportsDir = __DIR__ . '/../reports/daily_excel';
if (!is_dir($reportsDir)) {
    @mkdir($reportsDir, 0777, true);
}

/**
 * Clean string for XML output
 */
function xmlEscape(?string $str): string {
    return htmlspecialchars((string) ($str ?? ''), ENT_XML1, 'UTF-8');
}

/**
 * Generate Complete Daily Excel XML Workbook Content
 */
function buildDailyExcelXml(PDO $db, string $targetDate): string {
    $gymName = getSetting('gym_name', 'THE CLUB 777®');
    $gymPhone = getSetting('gym_phone', '8053576777, 8053570777, 9416528777');
    $formattedDate = date('d F Y (l)', strtotime($targetDate));
    $genTimestamp = date('d M Y, h:i A');

    // 1. Fetch Sales for Target Date
    $salesStmt = $db->prepare("
        SELECT s.*, m.name as member_name, m.member_code, m.phone as member_phone, u.name as cashier_name
        FROM sales s
        LEFT JOIN members m ON s.member_id = m.id
        LEFT JOIN users u ON s.created_by = u.id
        WHERE DATE(s.created_at) = ?
        ORDER BY s.id ASC
    ");
    $salesStmt->execute([$targetDate]);
    $sales = $salesStmt->fetchAll(PDO::FETCH_ASSOC);

    // 2. Fetch Attendance Logs for Target Date
    $attStmt = $db->prepare("
        SELECT a.*, a.check_in_time as punch_time, a.verification_method as punch_method,
               m.name as member_name, m.member_code, m.phone as member_phone, m.gender, m.status as member_status,
               d.name as device_name
        FROM attendance a
        LEFT JOIN members m ON a.member_id = m.id
        LEFT JOIN devices d ON a.device_id = d.id
        WHERE DATE(a.check_in_time) = ?
        ORDER BY a.check_in_time ASC
    ");
    $attStmt->execute([$targetDate]);
    $attendances = $attStmt->fetchAll(PDO::FETCH_ASSOC);

    // 3. Fetch Active Subscriptions Snapshot
    $activeSubsStmt = $db->query("
        SELECT ms.id, ms.start_date, ms.end_date, ms.price_paid, ms.status,
               m.name, m.phone, m.member_code, m.gender,
               p.title as plan_title, p.category as plan_category, p.price as plan_price,
               DATEDIFF(ms.end_date, CURRENT_DATE()) as days_left
        FROM member_subscriptions ms
        JOIN members m ON ms.member_id = m.id
        JOIN membership_types p ON ms.membership_type_id = p.id
        WHERE ms.status = 'active'
        ORDER BY ms.end_date ASC
    ");
    $activeSubs = $activeSubsStmt ? $activeSubsStmt->fetchAll(PDO::FETCH_ASSOC) : [];

    // 4. Fetch Pending Dues & Defaulters
    $duesStmt = $db->query("
        SELECT s.invoice_no, s.total, s.paid_amount, s.due_amount, s.due_date, s.notes, s.created_at,
               m.name as member_name, m.member_code, m.phone as member_phone
        FROM sales s
        LEFT JOIN members m ON s.member_id = m.id
        WHERE s.due_amount > 0 AND s.payment_status != 'voided'
        ORDER BY s.due_amount DESC
    ");
    $pendingDues = $duesStmt->fetchAll(PDO::FETCH_ASSOC);

    // Financial KPI Summary Computations
    $totalSalesGross = array_sum(array_column($sales, 'total'));
    $totalCollectedToday = array_sum(array_column($sales, 'paid_amount'));
    $totalDuesToday = array_sum(array_column($sales, 'due_amount'));
    $totalCash = 0; $totalUpi = 0; $totalCard = 0; $totalOtherPay = 0;

    foreach ($sales as $s) {
        $mode = strtolower($s['payment_method'] ?? 'cash');
        $paid = (float)$s['paid_amount'];
        if (strpos($mode, 'cash') !== false) $totalCash += $paid;
        elseif (strpos($mode, 'upi') !== false || strpos($mode, 'qr') !== false || strpos($mode, 'gpay') !== false || strpos($mode, 'phonepe') !== false) $totalUpi += $paid;
        elseif (strpos($mode, 'card') !== false || strpos($mode, 'pos') !== false) $totalCard += $paid;
        else $totalOtherPay += $paid;
    }

    $totalCheckins = count($attendances);
    $totalActiveMembersCount = count($activeSubs);
    $totalPendingDuesAmt = array_sum(array_column($pendingDues, 'due_amount'));

    // Build XML Spreadsheet 2003 Document in pure PHP string buffer
    $xml = [];
    $xml[] = '<?xml version="1.0" encoding="UTF-8"?>';
    $xml[] = '<?mso-application progid="Excel.Sheet"?>';
    $xml[] = '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"';
    $xml[] = ' xmlns:o="urn:schemas-microsoft-com:office:office"';
    $xml[] = ' xmlns:x="urn:schemas-microsoft-com:office:excel"';
    $xml[] = ' xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"';
    $xml[] = ' xmlns:html="http://www.w3.org/TR/REC-html40">';

    // Document Properties & Style Definitions
    $xml[] = ' <DocumentProperties xmlns="urn:schemas-microsoft-com:office:office">';
    $xml[] = '  <Title>' . xmlEscape($gymName) . ' Daily Summary Report</Title>';
    $xml[] = '  <Author>' . xmlEscape($gymName) . '</Author>';
    $xml[] = '  <Created>' . date('Y-m-d\TH:i:s\Z') . '</Created>';
    $xml[] = ' </DocumentProperties>';

    $xml[] = ' <Styles>';
    $xml[] = '  <Style ss:ID="Default" ss:Name="Normal"><Alignment ss:Vertical="Center"/><Font ss:FontName="Calibri" x:Family="Swiss" ss:Size="11" ss:Color="#1E293B"/></Style>';
    $xml[] = '  <Style ss:ID="TitleStyle"><Alignment ss:Horizontal="Center" ss:Vertical="Center"/><Font ss:FontName="Calibri" ss:Size="16" ss:Bold="1" ss:Color="#FFFFFF"/><Interior ss:Color="#1E3A8A" ss:Pattern="Solid"/></Style>';
    $xml[] = '  <Style ss:ID="SubTitleStyle"><Alignment ss:Horizontal="Center" ss:Vertical="Center"/><Font ss:FontName="Calibri" ss:Size="11" ss:Italic="1" ss:Color="#E2E8F0"/><Interior ss:Color="#1E3A8A" ss:Pattern="Solid"/></Style>';
    $xml[] = '  <Style ss:ID="SectionHeader"><Alignment ss:Horizontal="Left" ss:Vertical="Center"/><Font ss:FontName="Calibri" ss:Size="12" ss:Bold="1" ss:Color="#1E3A8A"/><Interior ss:Color="#E0F2FE" ss:Pattern="Solid"/><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#93C5FD"/></Borders></Style>';
    $xml[] = '  <Style ss:ID="TableHeader"><Alignment ss:Horizontal="Center" ss:Vertical="Center" ss:WrapText="1"/><Font ss:FontName="Calibri" ss:Size="11" ss:Bold="1" ss:Color="#FFFFFF"/><Interior ss:Color="#2563EB" ss:Pattern="Solid"/><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#1D4ED8"/></Borders></Style>';
    $xml[] = '  <Style ss:ID="KpiLabel"><Alignment ss:Horizontal="Left" ss:Vertical="Center"/><Font ss:FontName="Calibri" ss:Size="11" ss:Bold="1" ss:Color="#334155"/><Interior ss:Color="#F1F5F9" ss:Pattern="Solid"/><Borders><Border ss:Position="All" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E2E8F0"/></Borders></Style>';
    $xml[] = '  <Style ss:ID="KpiValue"><Alignment ss:Horizontal="Right" ss:Vertical="Center"/><Font ss:FontName="Calibri" ss:Size="12" ss:Bold="1" ss:Color="#0F172A"/><Interior ss:Color="#FFFFFF" ss:Pattern="Solid"/><Borders><Border ss:Position="All" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E2E8F0"/></Borders></Style>';
    $xml[] = '  <Style ss:ID="CurrencyCell"><Alignment ss:Horizontal="Right" ss:Vertical="Center"/><NumberFormat ss:Format="₹#,##0.00"/><Borders><Border ss:Position="All" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E2E8F0"/></Borders></Style>';
    $xml[] = '  <Style ss:ID="DataCell"><Alignment ss:Horizontal="Left" ss:Vertical="Center"/><Borders><Border ss:Position="All" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E2E8F0"/></Borders></Style>';
    $xml[] = '  <Style ss:ID="DataCellCenter"><Alignment ss:Horizontal="Center" ss:Vertical="Center"/><Borders><Border ss:Position="All" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E2E8F0"/></Borders></Style>';
    $xml[] = '  <Style ss:ID="BadgePaid"><Alignment ss:Horizontal="Center" ss:Vertical="Center"/><Font ss:FontName="Calibri" ss:Bold="1" ss:Color="#065F46"/><Interior ss:Color="#D1FAE5" ss:Pattern="Solid"/><Borders><Border ss:Position="All" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#A7F3D0"/></Borders></Style>';
    $xml[] = '  <Style ss:ID="BadgeDue"><Alignment ss:Horizontal="Center" ss:Vertical="Center"/><Font ss:FontName="Calibri" ss:Bold="1" ss:Color="#991B1B"/><Interior ss:Color="#FEE2E2" ss:Pattern="Solid"/><Borders><Border ss:Position="All" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#FECACA"/></Borders></Style>';
    $xml[] = '  <Style ss:ID="TotalRow"><Alignment ss:Horizontal="Right" ss:Vertical="Center"/><Font ss:FontName="Calibri" ss:Size="11" ss:Bold="1" ss:Color="#0F172A"/><Interior ss:Color="#F8FAFC" ss:Pattern="Solid"/><Borders><Border ss:Position="Top" ss:LineStyle="Double" ss:Weight="3" ss:Color="#64748B"/></Borders></Style>';
    $xml[] = ' </Styles>';

    // ==========================================
    // SHEET 1: EXECUTIVE DAILY SUMMARY
    // ==========================================
    $xml[] = ' <Worksheet ss:Name="Executive Summary">';
    $xml[] = '  <Table ss:DefaultColumnWidth="60" ss:DefaultRowHeight="20">';
    $xml[] = '   <Column ss:Width="220"/>';
    $xml[] = '   <Column ss:Width="160"/>';
    $xml[] = '   <Column ss:Width="40"/>';
    $xml[] = '   <Column ss:Width="200"/>';
    $xml[] = '   <Column ss:Width="160"/>';
    
    // Header Banner
    $xml[] = '   <Row ss:Height="36">';
    $xml[] = '    <Cell ss:MergeAcross="4" ss:StyleID="TitleStyle"><Data ss:Type="String">' . xmlEscape($gymName) . ' - EXECUTIVE DAILY RECONCILIATION</Data></Cell>';
    $xml[] = '   </Row>';
    $xml[] = '   <Row ss:Height="22">';
    $xml[] = '    <Cell ss:MergeAcross="4" ss:StyleID="SubTitleStyle"><Data ss:Type="String">Audit Date: ' . xmlEscape($formattedDate) . ' | Generated: ' . xmlEscape($genTimestamp) . ' | Phone: ' . xmlEscape($gymPhone) . '</Data></Cell>';
    $xml[] = '   </Row>';
    $xml[] = '   <Row ss:Height="12"></Row>';

    // Financial KPI Table
    $xml[] = '   <Row ss:Height="26">';
    $xml[] = '    <Cell ss:MergeAcross="1" ss:StyleID="SectionHeader"><Data ss:Type="String">💰 Daily Financial Collection Breakdown</Data></Cell>';
    $xml[] = '    <Cell></Cell>';
    $xml[] = '    <Cell ss:MergeAcross="1" ss:StyleID="SectionHeader"><Data ss:Type="String">👥 Operations &amp; Facility Footfall</Data></Cell>';
    $xml[] = '   </Row>';

    $xml[] = '   <Row ss:Height="24">';
    $xml[] = '    <Cell ss:StyleID="KpiLabel"><Data ss:Type="String">Total Invoiced Volume (Gross)</Data></Cell>';
    $xml[] = '    <Cell ss:StyleID="CurrencyCell"><Data ss:Type="Number">' . $totalSalesGross . '</Data></Cell>';
    $xml[] = '    <Cell></Cell>';
    $xml[] = '    <Cell ss:StyleID="KpiLabel"><Data ss:Type="String">Daily Member Check-ins (Attendance)</Data></Cell>';
    $xml[] = '    <Cell ss:StyleID="KpiValue"><Data ss:Type="Number">' . $totalCheckins . '</Data></Cell>';
    $xml[] = '   </Row>';

    $xml[] = '   <Row ss:Height="24">';
    $xml[] = '    <Cell ss:StyleID="KpiLabel"><Data ss:Type="String">Total Realized Collection (Paid)</Data></Cell>';
    $xml[] = '    <Cell ss:StyleID="CurrencyCell"><Data ss:Type="Number">' . $totalCollectedToday . '</Data></Cell>';
    $xml[] = '    <Cell></Cell>';
    $xml[] = '    <Cell ss:StyleID="KpiLabel"><Data ss:Type="String">Active Subscriptions on File</Data></Cell>';
    $xml[] = '    <Cell ss:StyleID="KpiValue"><Data ss:Type="Number">' . $totalActiveMembersCount . '</Data></Cell>';
    $xml[] = '   </Row>';

    $xml[] = '   <Row ss:Height="24">';
    $xml[] = '    <Cell ss:StyleID="KpiLabel"><Data ss:Type="String">💵 Cash Payments Collected</Data></Cell>';
    $xml[] = '    <Cell ss:StyleID="CurrencyCell"><Data ss:Type="Number">' . $totalCash . '</Data></Cell>';
    $xml[] = '    <Cell></Cell>';
    $xml[] = '    <Cell ss:StyleID="KpiLabel"><Data ss:Type="String">Total Outstanding Dues (All Members)</Data></Cell>';
    $xml[] = '    <Cell ss:StyleID="CurrencyCell"><Data ss:Type="Number">' . $totalPendingDuesAmt . '</Data></Cell>';
    $xml[] = '   </Row>';

    $xml[] = '   <Row ss:Height="24">';
    $xml[] = '    <Cell ss:StyleID="KpiLabel"><Data ss:Type="String">📱 UPI / QR Code Collections</Data></Cell>';
    $xml[] = '    <Cell ss:StyleID="CurrencyCell"><Data ss:Type="Number">' . $totalUpi . '</Data></Cell>';
    $xml[] = '    <Cell></Cell>';
    $xml[] = '    <Cell ss:StyleID="KpiLabel"><Data ss:Type="String">New Transactions Billed Today</Data></Cell>';
    $xml[] = '    <Cell ss:StyleID="KpiValue"><Data ss:Type="Number">' . count($sales) . '</Data></Cell>';
    $xml[] = '   </Row>';

    $xml[] = '   <Row ss:Height="24">';
    $xml[] = '    <Cell ss:StyleID="KpiLabel"><Data ss:Type="String">💳 Debit / Credit Card Collections</Data></Cell>';
    $xml[] = '    <Cell ss:StyleID="CurrencyCell"><Data ss:Type="Number">' . $totalCard . '</Data></Cell>';
    $xml[] = '    <Cell></Cell>';
    $xml[] = '    <Cell ss:StyleID="KpiLabel"><Data ss:Type="String">New Dues Incurred Today</Data></Cell>';
    $xml[] = '    <Cell ss:StyleID="CurrencyCell"><Data ss:Type="Number">' . $totalDuesToday . '</Data></Cell>';
    $xml[] = '   </Row>';

    $xml[] = '  </Table>';
    $xml[] = ' </Worksheet>';

    // ==========================================
    // SHEET 2: DAILY SALES & INVOICES
    // ==========================================
    $xml[] = ' <Worksheet ss:Name="Sales &amp; Invoices">';
    $xml[] = '  <Table ss:DefaultColumnWidth="60" ss:DefaultRowHeight="20">';
    $xml[] = '   <Column ss:Width="110"/>';
    $xml[] = '   <Column ss:Width="80"/>';
    $xml[] = '   <Column ss:Width="160"/>';
    $xml[] = '   <Column ss:Width="90"/>';
    $xml[] = '   <Column ss:Width="110"/>';
    $xml[] = '   <Column ss:Width="100"/>';
    $xml[] = '   <Column ss:Width="100"/>';
    $xml[] = '   <Column ss:Width="90"/>';
    $xml[] = '   <Column ss:Width="90"/>';
    $xml[] = '   <Column ss:Width="90"/>';
    $xml[] = '   <Column ss:Width="120"/>';
    
    $xml[] = '   <Row ss:Height="30">';
    $xml[] = '    <Cell ss:MergeAcross="10" ss:StyleID="TitleStyle"><Data ss:Type="String">DAILY SALES &amp; BILLING INVOICE LEDGER - ' . xmlEscape($targetDate) . '</Data></Cell>';
    $xml[] = '   </Row>';
    
    $xml[] = '   <Row ss:Height="24">';
    $xml[] = '    <Cell ss:StyleID="TableHeader"><Data ss:Type="String">Invoice #</Data></Cell>';
    $xml[] = '    <Cell ss:StyleID="TableHeader"><Data ss:Type="String">Time</Data></Cell>';
    $xml[] = '    <Cell ss:StyleID="TableHeader"><Data ss:Type="String">Customer / Member</Data></Cell>';
    $xml[] = '    <Cell ss:StyleID="TableHeader"><Data ss:Type="String">Member ID</Data></Cell>';
    $xml[] = '    <Cell ss:StyleID="TableHeader"><Data ss:Type="String">Phone</Data></Cell>';
    $xml[] = '    <Cell ss:StyleID="TableHeader"><Data ss:Type="String">Gross Total</Data></Cell>';
    $xml[] = '    <Cell ss:StyleID="TableHeader"><Data ss:Type="String">Discount</Data></Cell>';
    $xml[] = '    <Cell ss:StyleID="TableHeader"><Data ss:Type="String">Paid Amount</Data></Cell>';
    $xml[] = '    <Cell ss:StyleID="TableHeader"><Data ss:Type="String">Due Balance</Data></Cell>';
    $xml[] = '    <Cell ss:StyleID="TableHeader"><Data ss:Type="String">Payment Mode</Data></Cell>';
    $xml[] = '    <Cell ss:StyleID="TableHeader"><Data ss:Type="String">Cashier</Data></Cell>';
    $xml[] = '   </Row>';

    if (empty($sales)) {
        $xml[] = '   <Row><Cell ss:MergeAcross="10" ss:StyleID="DataCellCenter"><Data ss:Type="String">No transaction receipts recorded for this date.</Data></Cell></Row>';
    } else {
        foreach ($sales as $s) {
            $timeStr = date('h:i A', strtotime($s['created_at']));
            $xml[] = '   <Row>';
            $xml[] = '    <Cell ss:StyleID="DataCell"><Data ss:Type="String">' . xmlEscape($s['invoice_no']) . '</Data></Cell>';
            $xml[] = '    <Cell ss:StyleID="DataCellCenter"><Data ss:Type="String">' . $timeStr . '</Data></Cell>';
            $xml[] = '    <Cell ss:StyleID="DataCell"><Data ss:Type="String">' . xmlEscape($s['member_name'] ?: 'Walk-in Customer') . '</Data></Cell>';
            $xml[] = '    <Cell ss:StyleID="DataCellCenter"><Data ss:Type="String">' . xmlEscape($s['member_code'] ?: '-') . '</Data></Cell>';
            $xml[] = '    <Cell ss:StyleID="DataCellCenter"><Data ss:Type="String">' . xmlEscape($s['member_phone'] ?: '-') . '</Data></Cell>';
            $xml[] = '    <Cell ss:StyleID="CurrencyCell"><Data ss:Type="Number">' . (float)$s['total'] . '</Data></Cell>';
            $xml[] = '    <Cell ss:StyleID="CurrencyCell"><Data ss:Type="Number">' . (float)($s['discount'] ?? 0) . '</Data></Cell>';
            $xml[] = '    <Cell ss:StyleID="CurrencyCell"><Data ss:Type="Number">' . (float)$s['paid_amount'] . '</Data></Cell>';
            $xml[] = '    <Cell ss:StyleID="CurrencyCell"><Data ss:Type="Number">' . (float)$s['due_amount'] . '</Data></Cell>';
            $xml[] = '    <Cell ss:StyleID="DataCellCenter"><Data ss:Type="String">' . xmlEscape(strtoupper($s['payment_method'] ?? 'CASH')) . '</Data></Cell>';
            $xml[] = '    <Cell ss:StyleID="DataCell"><Data ss:Type="String">' . xmlEscape($s['cashier_name'] ?: 'System') . '</Data></Cell>';
            $xml[] = '   </Row>';
        }

        // Total Row
        $xml[] = '   <Row ss:Height="24">';
        $xml[] = '    <Cell ss:MergeAcross="4" ss:StyleID="TotalRow"><Data ss:Type="String">DAILY TOTALS:</Data></Cell>';
        $xml[] = '    <Cell ss:StyleID="CurrencyCell"><Data ss:Type="Number">' . $totalSalesGross . '</Data></Cell>';
        $xml[] = '    <Cell ss:StyleID="CurrencyCell"><Data ss:Type="Number">0</Data></Cell>';
        $xml[] = '    <Cell ss:StyleID="CurrencyCell"><Data ss:Type="Number">' . $totalCollectedToday . '</Data></Cell>';
        $xml[] = '    <Cell ss:StyleID="CurrencyCell"><Data ss:Type="Number">' . $totalDuesToday . '</Data></Cell>';
        $xml[] = '    <Cell ss:MergeAcross="1" ss:StyleID="DataCell"></Cell>';
        $xml[] = '   </Row>';
    }

    $xml[] = '  </Table>';
    $xml[] = ' </Worksheet>';

    // ==========================================
    // SHEET 3: ATTENDANCE & PUNCH LOGS
    // ==========================================
    $xml[] = ' <Worksheet ss:Name="Attendance Log">';
    $xml[] = '  <Table ss:DefaultColumnWidth="60" ss:DefaultRowHeight="20">';
    $xml[] = '   <Column ss:Width="70"/>';
    $xml[] = '   <Column ss:Width="80"/>';
    $xml[] = '   <Column ss:Width="160"/>';
    $xml[] = '   <Column ss:Width="90"/>';
    $xml[] = '   <Column ss:Width="110"/>';
    $xml[] = '   <Column ss:Width="160"/>';
    $xml[] = '   <Column ss:Width="110"/>';
    $xml[] = '   <Column ss:Width="90"/>';

    $xml[] = '   <Row ss:Height="30">';
    $xml[] = '    <Cell ss:MergeAcross="7" ss:StyleID="TitleStyle"><Data ss:Type="String">FACILITY ATTENDANCE &amp; BIOMETRIC ACCESS LOG - ' . xmlEscape($targetDate) . '</Data></Cell>';
    $xml[] = '   </Row>';

    $xml[] = '   <Row ss:Height="24">';
    $xml[] = '    <Cell ss:StyleID="TableHeader"><Data ss:Type="String">Entry #</Data></Cell>';
    $xml[] = '    <Cell ss:StyleID="TableHeader"><Data ss:Type="String">Punch Time</Data></Cell>';
    $xml[] = '    <Cell ss:StyleID="TableHeader"><Data ss:Type="String">Member Name</Data></Cell>';
    $xml[] = '    <Cell ss:StyleID="TableHeader"><Data ss:Type="String">Member Code</Data></Cell>';
    $xml[] = '    <Cell ss:StyleID="TableHeader"><Data ss:Type="String">Phone</Data></Cell>';
    $xml[] = '    <Cell ss:StyleID="TableHeader"><Data ss:Type="String">Active Plan</Data></Cell>';
    $xml[] = '    <Cell ss:StyleID="TableHeader"><Data ss:Type="String">Verification</Data></Cell>';
    $xml[] = '    <Cell ss:StyleID="TableHeader"><Data ss:Type="String">Zone/Access</Data></Cell>';
    $xml[] = '   </Row>';

    if (empty($attendances)) {
        $xml[] = '   <Row><Cell ss:MergeAcross="7" ss:StyleID="DataCellCenter"><Data ss:Type="String">No member attendance entries recorded for this date.</Data></Cell></Row>';
    } else {
        foreach ($attendances as $idx => $a) {
            $timeStr = date('h:i:s A', strtotime($a['punch_time']));
            $method = ucfirst($a['punch_method'] ?? 'Biometric');
            $xml[] = '   <Row>';
            $xml[] = '    <Cell ss:StyleID="DataCellCenter"><Data ss:Type="Number">' . ($idx + 1) . '</Data></Cell>';
            $xml[] = '    <Cell ss:StyleID="DataCellCenter"><Data ss:Type="String">' . $timeStr . '</Data></Cell>';
            $xml[] = '    <Cell ss:StyleID="DataCell"><Data ss:Type="String">' . xmlEscape($a['member_name'] ?: 'Unregistered Card') . '</Data></Cell>';
            $xml[] = '    <Cell ss:StyleID="DataCellCenter"><Data ss:Type="String">' . xmlEscape($a['member_code'] ?: '-') . '</Data></Cell>';
            $xml[] = '    <Cell ss:StyleID="DataCellCenter"><Data ss:Type="String">' . xmlEscape($a['member_phone'] ?: '-') . '</Data></Cell>';
            $xml[] = '    <Cell ss:StyleID="DataCell"><Data ss:Type="String">' . xmlEscape($a['plan_title'] ?: 'Standard Floor') . '</Data></Cell>';
            $xml[] = '    <Cell ss:StyleID="DataCellCenter"><Data ss:Type="String">' . xmlEscape($method) . '</Data></Cell>';
            $xml[] = '    <Cell ss:StyleID="DataCellCenter"><Data ss:Type="String">' . xmlEscape($a['device_name'] ?? 'Main Turnstile') . '</Data></Cell>';
            $xml[] = '   </Row>';
        }
    }

    $xml[] = '  </Table>';
    $xml[] = ' </Worksheet>';

    // ==========================================
    // SHEET 4: ACTIVE MEMBERSHIPS & SUBSCRIPTIONS
    // ==========================================
    $xml[] = ' <Worksheet ss:Name="Active Members">';
    $xml[] = '  <Table ss:DefaultColumnWidth="60" ss:DefaultRowHeight="20">';
    $xml[] = '   <Column ss:Width="60"/>';
    $xml[] = '   <Column ss:Width="90"/>';
    $xml[] = '   <Column ss:Width="160"/>';
    $xml[] = '   <Column ss:Width="110"/>';
    $xml[] = '   <Column ss:Width="160"/>';
    $xml[] = '   <Column ss:Width="100"/>';
    $xml[] = '   <Column ss:Width="95"/>';
    $xml[] = '   <Column ss:Width="95"/>';
    $xml[] = '   <Column ss:Width="80"/>';
    $xml[] = '   <Column ss:Width="90"/>';

    $xml[] = '   <Row ss:Height="30">';
    $xml[] = '    <Cell ss:MergeAcross="9" ss:StyleID="TitleStyle"><Data ss:Type="String">ACTIVE MEMBERSHIPS &amp; SUBSCRIPTIONS REGISTER</Data></Cell>';
    $xml[] = '   </Row>';

    $xml[] = '   <Row ss:Height="24">';
    $xml[] = '    <Cell ss:StyleID="TableHeader"><Data ss:Type="String">#</Data></Cell>';
    $xml[] = '    <Cell ss:StyleID="TableHeader"><Data ss:Type="String">Member ID</Data></Cell>';
    $xml[] = '    <Cell ss:StyleID="TableHeader"><Data ss:Type="String">Full Name</Data></Cell>';
    $xml[] = '    <Cell ss:StyleID="TableHeader"><Data ss:Type="String">Phone Number</Data></Cell>';
    $xml[] = '    <Cell ss:StyleID="TableHeader"><Data ss:Type="String">Enrolled Plan</Data></Cell>';
    $xml[] = '    <Cell ss:StyleID="TableHeader"><Data ss:Type="String">Category</Data></Cell>';
    $xml[] = '    <Cell ss:StyleID="TableHeader"><Data ss:Type="String">Start Date</Data></Cell>';
    $xml[] = '    <Cell ss:StyleID="TableHeader"><Data ss:Type="String">Expiry Date</Data></Cell>';
    $xml[] = '    <Cell ss:StyleID="TableHeader"><Data ss:Type="String">Days Left</Data></Cell>';
    $xml[] = '    <Cell ss:StyleID="TableHeader"><Data ss:Type="String">Plan Value</Data></Cell>';
    $xml[] = '   </Row>';

    if (empty($activeSubs)) {
        $xml[] = '   <Row><Cell ss:MergeAcross="9" ss:StyleID="DataCellCenter"><Data ss:Type="String">No active subscriptions currently on record.</Data></Cell></Row>';
    } else {
        foreach ($activeSubs as $idx => $m) {
            $daysLeft = intval($m['days_left']);
            $xml[] = '   <Row>';
            $xml[] = '    <Cell ss:StyleID="DataCellCenter"><Data ss:Type="Number">' . ($idx + 1) . '</Data></Cell>';
            $xml[] = '    <Cell ss:StyleID="DataCellCenter"><Data ss:Type="String">' . xmlEscape($m['member_code'] ?: '-') . '</Data></Cell>';
            $xml[] = '    <Cell ss:StyleID="DataCell"><Data ss:Type="String">' . xmlEscape($m['name']) . '</Data></Cell>';
            $xml[] = '    <Cell ss:StyleID="DataCellCenter"><Data ss:Type="String">' . xmlEscape($m['phone']) . '</Data></Cell>';
            $xml[] = '    <Cell ss:StyleID="DataCell"><Data ss:Type="String">' . xmlEscape($m['plan_title'] ?: 'Standard Membership') . '</Data></Cell>';
            $xml[] = '    <Cell ss:StyleID="DataCellCenter"><Data ss:Type="String">' . xmlEscape(strtoupper($m['plan_category'] ?? 'MONTHLY')) . '</Data></Cell>';
            $xml[] = '    <Cell ss:StyleID="DataCellCenter"><Data ss:Type="String">' . (!empty($m['start_date']) ? date('d-M-Y', strtotime($m['start_date'])) : '-') . '</Data></Cell>';
            $xml[] = '    <Cell ss:StyleID="DataCellCenter"><Data ss:Type="String">' . (!empty($m['end_date']) ? date('d-M-Y', strtotime($m['end_date'])) : '-') . '</Data></Cell>';
            $xml[] = '    <Cell ss:StyleID="DataCellCenter"><Data ss:Type="Number">' . max(0, $daysLeft) . '</Data></Cell>';
            $xml[] = '    <Cell ss:StyleID="CurrencyCell"><Data ss:Type="Number">' . (float)($m['plan_price'] ?? 0) . '</Data></Cell>';
            $xml[] = '   </Row>';
        }
    }

    $xml[] = '  </Table>';
    $xml[] = ' </Worksheet>';

    // ==========================================
    // SHEET 5: PENDING DUES & DEFAULTERS
    // ==========================================
    $xml[] = ' <Worksheet ss:Name="Pending Dues">';
    $xml[] = '  <Table ss:DefaultColumnWidth="60" ss:DefaultRowHeight="20">';
    $xml[] = '   <Column ss:Width="110"/>';
    $xml[] = '   <Column ss:Width="160"/>';
    $xml[] = '   <Column ss:Width="90"/>';
    $xml[] = '   <Column ss:Width="110"/>';
    $xml[] = '   <Column ss:Width="100"/>';
    $xml[] = '   <Column ss:Width="100"/>';
    $xml[] = '   <Column ss:Width="110"/>';
    $xml[] = '   <Column ss:Width="100"/>';
    $xml[] = '   <Column ss:Width="160"/>';

    $xml[] = '   <Row ss:Height="30">';
    $xml[] = '    <Cell ss:MergeAcross="8" ss:StyleID="TitleStyle"><Data ss:Type="String">OUTSTANDING DUES &amp; FEE RECOVERY LEDGER</Data></Cell>';
    $xml[] = '   </Row>';

    $xml[] = '   <Row ss:Height="24">';
    $xml[] = '    <Cell ss:StyleID="TableHeader"><Data ss:Type="String">Invoice #</Data></Cell>';
    $xml[] = '    <Cell ss:StyleID="TableHeader"><Data ss:Type="String">Customer Name</Data></Cell>';
    $xml[] = '    <Cell ss:StyleID="TableHeader"><Data ss:Type="String">Member ID</Data></Cell>';
    $xml[] = '    <Cell ss:StyleID="TableHeader"><Data ss:Type="String">Phone Number</Data></Cell>';
    $xml[] = '    <Cell ss:StyleID="TableHeader"><Data ss:Type="String">Total Bill</Data></Cell>';
    $xml[] = '    <Cell ss:StyleID="TableHeader"><Data ss:Type="String">Paid</Data></Cell>';
    $xml[] = '    <Cell ss:StyleID="TableHeader"><Data ss:Type="String">Due Amount</Data></Cell>';
    $xml[] = '    <Cell ss:StyleID="TableHeader"><Data ss:Type="String">Promised Due Date</Data></Cell>';
    $xml[] = '    <Cell ss:StyleID="TableHeader"><Data ss:Type="String">Remarks / Notes</Data></Cell>';
    $xml[] = '   </Row>';

    if (empty($pendingDues)) {
        $xml[] = '   <Row><Cell ss:MergeAcross="8" ss:StyleID="BadgePaid"><Data ss:Type="String">✨ 100% CLEAR - All accounts are paid up with zero outstanding balance!</Data></Cell></Row>';
    } else {
        foreach ($pendingDues as $d) {
            $xml[] = '   <Row>';
            $xml[] = '    <Cell ss:StyleID="DataCell"><Data ss:Type="String">' . xmlEscape($d['invoice_no']) . '</Data></Cell>';
            $xml[] = '    <Cell ss:StyleID="DataCell"><Data ss:Type="String">' . xmlEscape($d['member_name'] ?: 'Direct Sale Customer') . '</Data></Cell>';
            $xml[] = '    <Cell ss:StyleID="DataCellCenter"><Data ss:Type="String">' . xmlEscape($d['member_code'] ?: '-') . '</Data></Cell>';
            $xml[] = '    <Cell ss:StyleID="DataCellCenter"><Data ss:Type="String">' . xmlEscape($d['member_phone'] ?: '-') . '</Data></Cell>';
            $xml[] = '    <Cell ss:StyleID="CurrencyCell"><Data ss:Type="Number">' . (float)$d['total'] . '</Data></Cell>';
            $xml[] = '    <Cell ss:StyleID="CurrencyCell"><Data ss:Type="Number">' . (float)$d['paid_amount'] . '</Data></Cell>';
            $xml[] = '    <Cell ss:StyleID="BadgeDue"><Data ss:Type="Number">' . (float)$d['due_amount'] . '</Data></Cell>';
            $xml[] = '    <Cell ss:StyleID="DataCellCenter"><Data ss:Type="String">' . (!empty($d['due_date']) ? date('d-M-Y', strtotime($d['due_date'])) : 'Immediate') . '</Data></Cell>';
            $xml[] = '    <Cell ss:StyleID="DataCell"><Data ss:Type="String">' . xmlEscape($d['notes'] ?: '-') . '</Data></Cell>';
            $xml[] = '   </Row>';
        }

        $xml[] = '   <Row ss:Height="24">';
        $xml[] = '    <Cell ss:MergeAcross="5" ss:StyleID="TotalRow"><Data ss:Type="String">TOTAL UNCOLLECTED DUES:</Data></Cell>';
        $xml[] = '    <Cell ss:StyleID="BadgeDue"><Data ss:Type="Number">' . $totalPendingDuesAmt . '</Data></Cell>';
        $xml[] = '    <Cell ss:MergeAcross="1" ss:StyleID="DataCell"></Cell>';
        $xml[] = '   </Row>';
    }

    $xml[] = '  </Table>';
    $xml[] = ' </Worksheet>';

    $xml[] = '</Workbook>';

    return implode("\n", $xml);
}

/**
 * Auto-Save Daily Excel to Disk
 */
function autoSaveDailyExcel(PDO $db, string $targetDate): string {
    global $reportsDir;
    $fileName = "Daily_Summary_Report_{$targetDate}.xls";
    $filePath = "{$reportsDir}/{$fileName}";
    
    $xml = buildDailyExcelXml($db, $targetDate);
    @file_put_contents($filePath, $xml);
    return $fileName;
}

// -------------------------------------------------------------
// CONTROLLER ACTIONS
// -------------------------------------------------------------

if ($action === 'generate_daily_excel' || $action === 'download_daily_excel') {
    $targetDate = trim($_GET['date'] ?? $_POST['date'] ?? date('Y-m-d'));
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $targetDate)) {
        $targetDate = date('Y-m-d');
    }

    // Auto-save copy to disk
    $savedFileName = autoSaveDailyExcel($db, $targetDate);

    if ($action === 'download_daily_excel' || !isset($_GET['json_only'])) {
        $xmlContent = buildDailyExcelXml($db, $targetDate);
        header('Content-Type: application/vnd.ms-excel; charset=utf-8');
        header("Content-Disposition: attachment; filename=\"Daily_Summary_Report_{$targetDate}.xls\"");
        header('Cache-Control: max-age=0');
        echo $xmlContent;
        exit;
    } else {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'message' => "Daily Excel report generated & saved successfully for {$targetDate}",
            'filename' => $savedFileName,
            'file_url' => "reports/daily_excel/{$savedFileName}",
            'date' => $targetDate
        ]);
        exit;
    }

} elseif ($action === 'list_saved_daily_excel') {
    header('Content-Type: application/json');
    $files = [];
    if (is_dir($reportsDir)) {
        $scan = scandir($reportsDir);
        foreach ($scan as $f) {
            if ($f === '.' || $f === '..') continue;
            if (substr($f, -4) === '.xls' || substr($f, -5) === '.xlsx') {
                $fullPath = "{$reportsDir}/{$f}";
                $files[] = [
                    'filename' => $f,
                    'file_url' => "reports/daily_excel/{$f}",
                    'size_kb' => round(filesize($fullPath) / 1024, 1),
                    'created_at' => date('d M Y, h:i A', filemtime($fullPath)),
                    'date' => str_replace(['Daily_Summary_Report_', '.xls', '.xlsx'], '', $f)
                ];
            }
        }
    }
    // Sort reverse chronological
    usort($files, function($a, $b) {
        return strcmp($b['filename'], $a['filename']);
    });
    echo json_encode(['success' => true, 'data' => $files]);
    exit;

} elseif ($action === 'auto_cron_trigger') {
    $today = date('Y-m-d');
    $fileName = autoSaveDailyExcel($db, $today);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => "Daily automatic Excel cron executed successfully",
        'file' => $fileName
    ]);
    exit;
}
