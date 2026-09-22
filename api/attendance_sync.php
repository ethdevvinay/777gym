<?php
/**
 * Attendance Sync & Management API
 * Provides real-time attendance feed, summary stats, member timeline,
 * date-range filtering, manual checkout, and CSV export.
 *
 * Actions:
 *   live_feed        - Last 30 punches for real-time polling (every 5s)
 *   today_summary    - KPI stats for today
 *   logs             - Paginated attendance logs with date/member filters
 *   member_timeline  - Today's history for a specific member
 *   currently_inside - Members currently inside gym
 *   export_csv       - Download attendance CSV for a date range
 */
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

$action = $_GET['action'] ?? $_POST['action'] ?? 'live_feed';
$db     = getDB();

// Ensure attendance table has checkout & duration columns
try { $db->exec("ALTER TABLE `attendance` ADD COLUMN `check_out_time` DATETIME NULL AFTER `check_in_time`"); } catch(Exception $e) {}
try { $db->exec("ALTER TABLE `attendance` ADD COLUMN `duration_minutes` INT DEFAULT NULL AFTER `check_out_time`"); } catch(Exception $e) {}
try { $db->exec("ALTER TABLE `members` ADD COLUMN `photo_url` VARCHAR(255) NULL AFTER `phone`"); } catch(Exception $e) {}

// =============================================================================
if ($action === 'live_feed') {
    // Real-time feed: last N punches for dashboard auto-refresh (every 5s)
    $sinceId = intval($_GET['since_id'] ?? 0);
    $limit   = min(50, max(10, intval($_GET['limit'] ?? 30)));
    $where   = $sinceId > 0 ? "AND a.id > {$sinceId}" : '';

    $stmt = $db->query("
        SELECT 
            a.id, a.member_id, a.check_in_time, a.check_out_time, a.duration_minutes,
            a.verification_method, a.status, a.notes,
            m.name AS member_name, m.member_code, m.phone, m.photo_url, m.biometric_id,
            d.name AS device_name, d.location AS device_location,
            CASE WHEN a.check_out_time IS NULL THEN 'inside' ELSE 'left' END AS presence_status,
            TIMESTAMPDIFF(MINUTE, a.check_in_time, IFNULL(a.check_out_time, NOW())) AS time_in_gym_mins
        FROM attendance a
        JOIN members m ON a.member_id = m.id
        LEFT JOIN devices d ON a.device_id = d.id
        WHERE DATE(a.check_in_time) = CURDATE() {$where}
        ORDER BY a.id DESC LIMIT {$limit}
    ");

    $logs    = $stmt->fetchAll();
    $maxId   = !empty($logs) ? max(array_column($logs, 'id')) : $sinceId;
    $inside  = (int) $db->query("SELECT COUNT(*) FROM attendance WHERE DATE(check_in_time) = CURDATE() AND check_out_time IS NULL")->fetchColumn();

    jsonResponse(true, [
        'logs'         => $logs,
        'max_id'       => $maxId,
        'inside_count' => $inside,
        'server_time'  => date('Y-m-d H:i:s'),
        'display_time' => date('h:i:s A'),
    ], 'Live feed OK');

// =============================================================================
} elseif ($action === 'today_summary') {
    $totalCheckIns   = (int) $db->query("SELECT COUNT(*) FROM attendance WHERE DATE(check_in_time) = CURDATE()")->fetchColumn();
    $totalCheckOuts  = (int) $db->query("SELECT COUNT(*) FROM attendance WHERE DATE(check_in_time) = CURDATE() AND check_out_time IS NOT NULL")->fetchColumn();
    $currentlyInside = (int) $db->query("SELECT COUNT(*) FROM attendance WHERE DATE(check_in_time) = CURDATE() AND check_out_time IS NULL")->fetchColumn();
    $expiredAlerts   = (int) $db->query("SELECT COUNT(*) FROM attendance WHERE DATE(check_in_time) = CURDATE() AND status = 'expired_alert'")->fetchColumn();
    $biometricCount  = (int) $db->query("SELECT COUNT(*) FROM attendance WHERE verification_method = 'biometric' AND DATE(check_in_time) = CURDATE()")->fetchColumn();
    $manualCount     = (int) $db->query("SELECT COUNT(*) FROM attendance WHERE verification_method = 'manual' AND DATE(check_in_time) = CURDATE()")->fetchColumn();
    $qrCount         = (int) $db->query("SELECT COUNT(*) FROM attendance WHERE verification_method = 'qr' AND DATE(check_in_time) = CURDATE()")->fetchColumn();
    $avgDuration     = round((float) $db->query("SELECT AVG(duration_minutes) FROM attendance WHERE DATE(check_in_time) = CURDATE() AND duration_minutes > 0")->fetchColumn());

    $peakHour = $db->query("SELECT HOUR(check_in_time) as hr, COUNT(*) as cnt FROM attendance WHERE DATE(check_in_time) = CURDATE() GROUP BY hr ORDER BY cnt DESC LIMIT 1")->fetch();
    $peakHourLabel = $peakHour ? date('h:00 A', strtotime("2000-01-01 {$peakHour['hr']}:00:00")) : 'N/A';

    $unregisteredToday = 0;
    try { $unregisteredToday = (int) $db->query("SELECT COUNT(*) FROM unregistered_punches WHERE DATE(punch_time) = CURDATE() AND resolved = 0")->fetchColumn(); } catch(Exception $e) {}

    $deviceOnline = (int) $db->query("SELECT COUNT(*) FROM devices WHERE status = 'ONLINE'")->fetchColumn();
    $lastSync     = $db->query("SELECT MAX(last_sync) FROM devices")->fetchColumn();

    jsonResponse(true, [
        'total_check_ins'    => $totalCheckIns,
        'total_check_outs'   => $totalCheckOuts,
        'currently_inside'   => $currentlyInside,
        'expired_alerts'     => $expiredAlerts,
        'biometric_count'    => $biometricCount,
        'manual_count'       => $manualCount,
        'qr_count'           => $qrCount,
        'avg_duration_mins'  => $avgDuration,
        'avg_duration_label' => $avgDuration > 0 ? "{$avgDuration} mins" : 'N/A',
        'peak_hour'          => $peakHourLabel,
        'unregistered_today' => $unregisteredToday,
        'device_online'      => $deviceOnline,
        'last_sync'          => $lastSync ? date('h:i A', strtotime($lastSync)) : 'Never',
        'date'               => date('d M Y'),
        'server_time'        => date('Y-m-d H:i:s'),
    ], 'Today summary fetched');

// =============================================================================
} elseif ($action === 'logs') {
    $fromDate = $_GET['from_date'] ?? date('Y-m-d');
    $toDate   = $_GET['to_date']   ?? date('Y-m-d');
    $memberId = intval($_GET['member_id'] ?? 0);
    $method   = $_GET['method']    ?? '';
    $statusF  = $_GET['status']    ?? '';
    $page     = max(1, intval($_GET['page'] ?? 1));
    $perPage  = min(100, max(10, intval($_GET['per_page'] ?? 50)));
    $offset   = ($page - 1) * $perPage;

    $where  = ["DATE(a.check_in_time) BETWEEN '{$fromDate}' AND '{$toDate}'"];
    $params = [];
    if ($memberId > 0) { $where[] = 'a.member_id = ?'; $params[] = $memberId; }
    if ($method)       { $where[] = 'a.verification_method = ?'; $params[] = $method; }
    if ($statusF)      { $where[] = 'a.status = ?'; $params[] = $statusF; }
    $whereSQL = implode(' AND ', $where);

    $totalStmt = $db->prepare("SELECT COUNT(*) FROM attendance a WHERE {$whereSQL}");
    $totalStmt->execute($params);
    $totalCount = (int) $totalStmt->fetchColumn();

    $stmt = $db->prepare("
        SELECT a.*, m.name AS member_name, m.member_code, m.phone, m.photo_url,
               d.name AS device_name, d.location AS device_location,
               CASE WHEN a.check_out_time IS NULL THEN 'inside' ELSE 'left' END AS presence_status,
               TIMESTAMPDIFF(MINUTE, a.check_in_time, IFNULL(a.check_out_time, NOW())) AS time_in_gym_mins
        FROM attendance a
        JOIN members m ON a.member_id = m.id
        LEFT JOIN devices d ON a.device_id = d.id
        WHERE {$whereSQL}
        ORDER BY a.id DESC LIMIT {$perPage} OFFSET {$offset}
    ");
    $stmt->execute($params);

    jsonResponse(true, [
        'logs'       => $stmt->fetchAll(),
        'pagination' => ['page' => $page, 'per_page' => $perPage, 'total' => $totalCount, 'total_pages' => (int) ceil($totalCount / $perPage)],
        'date_range' => ['from' => $fromDate, 'to' => $toDate],
    ], "{$totalCount} records found");

// =============================================================================
} elseif ($action === 'currently_inside') {
    $stmt = $db->query("
        SELECT 
            a.id AS att_id, a.check_in_time, a.verification_method, a.status,
            TIMESTAMPDIFF(MINUTE, a.check_in_time, NOW()) AS minutes_inside,
            m.id AS member_id, m.name AS member_name, m.member_code, m.phone, m.photo_url,
            ms.end_date AS subscription_end
        FROM attendance a
        JOIN members m ON a.member_id = m.id
        LEFT JOIN member_subscriptions ms ON ms.member_id = m.id AND ms.status = 'active'
        WHERE DATE(a.check_in_time) = CURDATE() AND a.check_out_time IS NULL
        ORDER BY a.check_in_time ASC
    ");
    $members = $stmt->fetchAll();

    jsonResponse(true, [
        'members'     => $members,
        'count'       => count($members),
        'server_time' => date('Y-m-d H:i:s'),
    ], count($members) . ' members currently inside');

// =============================================================================
} elseif ($action === 'member_timeline') {
    $memberId = intval($_GET['member_id'] ?? 0);
    $date     = $_GET['date'] ?? date('Y-m-d');
    if (!$memberId) { jsonResponse(false, [], 'member_id required', 400); }

    $stmt = $db->prepare("
        SELECT a.*, d.name AS device_name,
               TIMESTAMPDIFF(MINUTE, a.check_in_time, IFNULL(a.check_out_time, NOW())) AS duration_mins
        FROM attendance a
        LEFT JOIN devices d ON a.device_id = d.id
        WHERE a.member_id = ? AND DATE(a.check_in_time) = ?
        ORDER BY a.check_in_time ASC
    ");
    $stmt->execute([$memberId, $date]);
    $timeline  = $stmt->fetchAll();
    $totalMins = array_sum(array_column($timeline, 'duration_mins'));

    $memberStmt = $db->prepare("SELECT id, name, member_code, phone, photo_url FROM members WHERE id = ?");
    $memberStmt->execute([$memberId]);

    jsonResponse(true, [
        'member'        => $memberStmt->fetch(),
        'timeline'      => $timeline,
        'total_minutes' => $totalMins,
        'total_hours'   => round($totalMins / 60, 2),
        'date'          => $date,
    ], 'Timeline fetched');

// =============================================================================
} elseif ($action === 'export_csv') {
    $fromDate = $_GET['from_date'] ?? date('Y-m-d');
    $toDate   = $_GET['to_date']   ?? date('Y-m-d');

    $stmt = $db->prepare("
        SELECT m.member_code, m.name AS member_name, m.phone,
               a.check_in_time, a.check_out_time, a.duration_minutes,
               a.verification_method, a.status,
               IFNULL(d.name, 'Unknown') AS device, a.notes
        FROM attendance a
        JOIN members m ON a.member_id = m.id
        LEFT JOIN devices d ON a.device_id = d.id
        WHERE DATE(a.check_in_time) BETWEEN ? AND ?
        ORDER BY a.check_in_time DESC
    ");
    $stmt->execute([$fromDate, $toDate]);
    $rows = $stmt->fetchAll();

    header('Content-Type: text/csv; charset=UTF-8');
    header("Content-Disposition: attachment; filename=\"attendance_{$fromDate}_to_{$toDate}.csv\"");
    header('Pragma: no-cache');
    header('Expires: 0');

    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
    fputcsv($out, ['Member Code','Member Name','Phone','Check-In','Check-Out','Duration (mins)','Method','Status','Device','Notes']);
    foreach ($rows as $r) {
        fputcsv($out, [
            $r['member_code'], $r['member_name'], $r['phone'],
            $r['check_in_time'], $r['check_out_time'] ?? '—',
            $r['duration_minutes'] ?? '—',
            ucfirst($r['verification_method']), ucfirst($r['status']),
            $r['device'], $r['notes'] ?? '',
        ]);
    }
    fclose($out);
    exit;

// =============================================================================
} else {
    jsonResponse(false, [], 'Invalid action. Valid: live_feed, today_summary, logs, currently_inside, member_timeline, export_csv', 400);
}
