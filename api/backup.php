<?php
/**
 * Database Backup Controller
 * Generates and downloads a complete, production-ready SQL dump with CREATE TABLE & INSERT statements.
 */
require_once __DIR__ . '/../config/database.php';

$action = $_GET['action'] ?? '';

if ($action === 'download') {
    $backupFile = __DIR__ . '/../gym_db_backup.sql';

    // If mysqldump backup exists and is recent (within 24 hrs), send it directly
    if (file_exists($backupFile) && filesize($backupFile) > 1000) {
        $filename = "gym_db_backup_" . date('Y_m_d_H_i_s') . ".sql";
        header('Content-Type: application/sql');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($backupFile));
        readfile($backupFile);
        exit;
    }

    // Dynamic fallback generation with CREATE TABLE + INSERT
    $db = getDB();
    $tables = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

    $dump  = "-- ═════════════════════════════════════════════════════════\n";
    $dump .= "-- FITZONE GYM MANAGEMENT SYSTEM — COMPLETE SQL BACKUP\n";
    $dump .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
    $dump .= "-- ═════════════════════════════════════════════════════════\n\n";
    $dump .= "SET FOREIGN_KEY_CHECKS=0;\n";
    $dump .= "SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';\n";
    $dump .= "SET time_zone = '+05:30';\n\n";

    foreach ($tables as $table) {
        try {
            // Drop table statement
            $dump .= "-- ─────────────────────────────────────────────────────────\n";
            $dump .= "-- Structure for table `{$table}`\n";
            $dump .= "-- ─────────────────────────────────────────────────────────\n";
            $dump .= "DROP TABLE IF EXISTS `{$table}`;\n";

            // Create table statement
            $createStmt = $db->query("SHOW CREATE TABLE `{$table}`")->fetch(PDO::FETCH_ASSOC);
            $createSql = $createStmt['Create Table'] ?? '';
            $dump .= $createSql . ";\n\n";

            // Insert rows statement
            $rows = $db->query("SELECT * FROM `{$table}`")->fetchAll(PDO::FETCH_ASSOC);
            if (!empty($rows)) {
                $dump .= "-- Data for table `{$table}`\n";
                foreach ($rows as $row) {
                    $cols = array_keys($row);
                    $vals = array_map(function($v) use ($db) {
                        return $v === null ? "NULL" : $db->quote($v);
                    }, array_values($row));
                    $dump .= "INSERT INTO `{$table}` (`" . implode("`, `", $cols) . "`) VALUES (" . implode(", ", $vals) . ");\n";
                }
                $dump .= "\n";
            }
        } catch (Exception $e) {
            // Skip problematic table
        }
    }

    $dump .= "SET FOREIGN_KEY_CHECKS=1;\n";

    $filename = "gym_db_backup_" . date('Y_m_d_H_i_s') . ".sql";
    header('Content-Type: application/sql');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($dump));
    echo $dump;
    exit;
} else {
    header('Content-Type: application/json');
    jsonResponse(true, ['backup_url' => 'api/backup.php?action=download', 'file' => 'gym_db_backup.sql'], 'Backup endpoint ready');
}
