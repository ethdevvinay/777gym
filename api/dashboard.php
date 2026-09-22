<?php
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');
$db = getDB();

try {
    // 1. Total Active Members
    $stmt1 = $db->query("SELECT COUNT(*) FROM members WHERE status = 'active'");
    $activeMembers = $stmt1->fetchColumn();

    // 2. Today's Attendance
    $stmt2 = $db->query("SELECT COUNT(*) FROM attendance WHERE DATE(check_in_time) = CURRENT_DATE()");
    $todayAttendance = $stmt2->fetchColumn();

    // 3. Today's Collection Revenue
    $stmt3 = $db->query("SELECT SUM(total) FROM sales WHERE DATE(created_at) = CURRENT_DATE()");
    $todayRevenue = $stmt3->fetchColumn() ?: 0.00;

    // 4. Expiring Memberships within 7 Days
    $stmt4 = $db->query("
        SELECT COUNT(*) FROM member_subscriptions 
        WHERE status = 'active' AND end_date BETWEEN CURRENT_DATE() AND DATE_ADD(CURRENT_DATE(), INTERVAL 7 DAY)
    ");
    $expiringCount = $stmt4->fetchColumn();

    // 5. Recent Expiring Members Detail List
    $stmt5 = $db->query("
        SELECT m.name, m.member_code, m.phone, s.end_date 
        FROM member_subscriptions s
        JOIN members m ON s.member_id = m.id
        WHERE s.status = 'active' AND s.end_date BETWEEN CURRENT_DATE() AND DATE_ADD(CURRENT_DATE(), INTERVAL 14 DAY)
        ORDER BY s.end_date ASC LIMIT 5
    ");
    $expiringList = $stmt5->fetchAll();

    jsonResponse(true, [
        'active_members' => $activeMembers,
        'today_attendance' => $todayAttendance,
        'today_revenue' => floatval($todayRevenue),
        'expiring_count' => $expiringCount,
        'expiring_list' => $expiringList
    ]);

} catch (Exception $e) {
    jsonResponse(false, [], 'Failed to load dashboard metrics: ' . $e->getMessage(), 500);
}
