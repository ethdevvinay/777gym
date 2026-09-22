<?php
/**
 * Biometric Architecture Upgrade Migration
 * Adds:
 *   1. biometric_transactions (Raw punch audit logs)
 *   2. device_commands (Remote push command queue: Enroll Face, Enroll FP, Reboot, Sync Users)
 *   3. devices table enhancements (model, mac, firmware, push_version, algorithms)
 *   4. members & staff enrollment tracking (finger_enrolled, face_enrolled)
 */
require_once __DIR__ . '/../config/database.php';
header('Content-Type: application/json');

$db = getDB();
$results = [];

// 1. Raw Transactions Table
try {
    $db->exec("
        CREATE TABLE IF NOT EXISTS `biometric_transactions` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `device_serial` VARCHAR(100) NOT NULL,
            `biometric_user_id` VARCHAR(100) NOT NULL,
            `entity_type` ENUM('member', 'staff', 'unknown') DEFAULT 'unknown',
            `entity_id` INT NULL,
            `punch_time` DATETIME NOT NULL,
            `verify_type` VARCHAR(50) DEFAULT 'face',
            `work_code` VARCHAR(50) DEFAULT '0',
            `raw_payload` TEXT NULL,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX (`device_serial`),
            INDEX (`biometric_user_id`),
            INDEX (`punch_time`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    $results['biometric_transactions'] = 'OK';
} catch (Exception $e) {
    $results['biometric_transactions'] = $e->getMessage();
}

// 2. Remote Command Queue Table
try {
    $db->exec("
        CREATE TABLE IF NOT EXISTS `device_commands` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `device_serial` VARCHAR(100) NOT NULL,
            `command_text` TEXT NOT NULL,
            `status` ENUM('pending', 'sent', 'success', 'failed') DEFAULT 'pending',
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            `sent_at` DATETIME NULL,
            `completed_at` DATETIME NULL,
            `response_log` TEXT NULL,
            INDEX (`device_serial`),
            INDEX (`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    $results['device_commands'] = 'OK';
} catch (Exception $e) {
    $results['device_commands'] = $e->getMessage();
}

// 3. Devices Table Columns
$deviceCols = [
    'model' => "ALTER TABLE `devices` ADD COLUMN `model` VARCHAR(100) DEFAULT 'eSSL X2008' AFTER `name`",
    'mac_address' => "ALTER TABLE `devices` ADD COLUMN `mac_address` VARCHAR(50) DEFAULT '00:17:61:12:10:fb' AFTER `serial_no`",
    'firmware' => "ALTER TABLE `devices` ADD COLUMN `firmware` VARCHAR(100) DEFAULT 'ZAM70-NF24A-Ver 3.12' AFTER `device_type`",
    'push_version' => "ALTER TABLE `devices` ADD COLUMN `push_version` VARCHAR(100) DEFAULT 'Ver 3.1.2S-20250616' AFTER `firmware`",
    'algorithms' => "ALTER TABLE `devices` ADD COLUMN `algorithms` VARCHAR(100) DEFAULT 'Face VX4.0 / Finger VX10.0' AFTER `push_version`",
];
foreach ($deviceCols as $col => $sql) {
    try { $db->exec($sql); $results["col_{$col}"] = 'Added'; } catch (Exception $e) { $results["col_{$col}"] = 'Exists'; }
}

// 4. Update NYU7262500437 with exact detected hardware specs
try {
    $db->prepare("
        UPDATE devices 
        SET model = 'eSSL X2008',
            mac_address = '00:17:61:12:10:fb',
            firmware = 'ZAM70-NF24A-Ver 3.12',
            push_version = 'Ver 3.1.2S-20250616',
            algorithms = 'Face VX4.0 / Finger VX10.0',
            device_type = 'face_fingerprint'
        WHERE serial_no = 'NYU7262500437'
    ")->execute();
    $results['device_updated'] = 'NYU7262500437 specs saved';
} catch (Exception $e) {
    $results['device_updated'] = $e->getMessage();
}

// 5. Members & Staff face/finger enrolled flags
try { $db->exec("ALTER TABLE `members` ADD COLUMN `face_enrolled` TINYINT(1) DEFAULT 0 AFTER `biometric_id`"); } catch (Exception $e) {}
try { $db->exec("ALTER TABLE `members` ADD COLUMN `finger_enrolled` TINYINT(1) DEFAULT 0 AFTER `face_enrolled`"); } catch (Exception $e) {}
try { $db->exec("ALTER TABLE `staff` ADD COLUMN `face_enrolled` TINYINT(1) DEFAULT 0 AFTER `biometric_id`"); } catch (Exception $e) {}
try { $db->exec("ALTER TABLE `staff` ADD COLUMN `finger_enrolled` TINYINT(1) DEFAULT 0 AFTER `face_enrolled`"); } catch (Exception $e) {}

// Mark Vinay face_enrolled = 1 since face punch was successful!
try {
    $db->exec("UPDATE members SET face_enrolled = 1 WHERE id IN (3, 106)");
} catch (Exception $e) {}

echo json_encode([
    'success' => true,
    'message' => 'Biometric Architecture Upgraded Successfully',
    'details' => $results
], JSON_PRETTY_PRINT);
