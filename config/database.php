<?php
/**
 * Core Database & System Configuration
 * Provides PDO connection, auto-installation, helper utilities, audit logging, and secure session management.
 */

if (session_status() === PHP_SESSION_NONE && php_sapi_name() !== 'cli') {
    session_start();
}

// Strictly enforce Indian Standard Time (IST / Asia/Kolkata +05:30)
date_default_timezone_set('Asia/Kolkata');

// ─── Database Credentials ───────────────────────────────────────────────────
// Supports both LOCAL (XAMPP) and LIVE (Hostinger / cPanel) deployments.
// On live server: create a file  config/.env  with your Hostinger DB details.
// On local XAMPP: uses default root/no-password settings automatically.

$_envFile = __DIR__ . '/.env';
$_env = [];
if (file_exists($_envFile)) {
    foreach (file($_envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $_line) {
        if (strpos(trim($_line), '#') === 0) continue;
        if (strpos($_line, '=') !== false) {
            [$_k, $_v] = explode('=', $_line, 2);
            $_env[trim($_k)] = trim($_v);
        }
    }
}

$isLocalXampp = (DIRECTORY_SEPARATOR === '\\') && (strpos(__DIR__, 'xampp') !== false || ($_SERVER['HTTP_HOST'] ?? '') === 'localhost');

if ($isLocalXampp) {
    // Local Windows XAMPP environment
    define('DB_HOST',    '127.0.0.1');
    define('DB_USER',    'root');
    define('DB_PASS',    '');
    define('DB_NAME',    'gym_db');
    define('DB_CHARSET', 'utf8mb4');
} else {
    // Live Server (Hostinger / cPanel / Linux)
    define('DB_HOST',    $_env['DB_HOST']    ?? 'localhost');
    define('DB_USER',    $_env['DB_USER']    ?? 'root');
    define('DB_PASS',    $_env['DB_PASS']    ?? '');
    define('DB_NAME',    $_env['DB_NAME']    ?? 'gym_db');
    define('DB_CHARSET', $_env['DB_CHARSET'] ?? 'utf8mb4');
}
// ────────────────────────────────────────────────────────────────────────────

class Database
{
    private static ?Database $instance = null;
    private PDO $pdo;

    private function __construct()
    {
        try {
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];

            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;

            try {
                $this->pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
            } catch (PDOException $connEx) {
                // If database does not exist, create it
                if ($connEx->getCode() == 1049 || strpos($connEx->getMessage(), 'Unknown database') !== false) {
                    $dsnNoDb = "mysql:host=" . DB_HOST . ";charset=" . DB_CHARSET;
                    $tempPdo = new PDO($dsnNoDb, DB_USER, DB_PASS, $options);
                    $tempPdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");
                    $this->pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
                } else {
                    throw $connEx;
                }
            }

            $this->pdo->exec("SET time_zone = '+05:30';");

            // Run checkAndInstall only once or if explicitly triggered, NEVER on every request
            $lockFile = __DIR__ . '/.installed_v3';
            if (!file_exists($lockFile) || isset($_GET['migrate'])) {
                $this->checkAndInstall();
                @touch($lockFile);
            }

        } catch (PDOException $e) {
            die("Database Connection Error: " . $e->getMessage());
        }
    }

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new Database();
        }
        return self::$instance->pdo;
    }

    private function checkAndInstall()
    {
        try {
            // Install or upgrade schema if devices or audit_logs tables missing
            $stmt = $this->pdo->query("SHOW TABLES LIKE 'devices'");
            if ($stmt->rowCount() === 0) {
                $schemaFile = __DIR__ . '/../database/schema.sql';
                if (file_exists($schemaFile)) {
                    $sql = file_get_contents($schemaFile);
                    $this->pdo->exec($sql);
                }
            }

            // Ensure marketing_templates table exists and has updated ENUM
            $this->pdo->exec("CREATE TABLE IF NOT EXISTS `marketing_templates` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `title` VARCHAR(100) NOT NULL,
                `trigger_event` ENUM('welcome', 'birthday', 'anniversary', 'expiry_3_weeks', 'expiry_1_week', 'expiry_2_days', 'expiry_same_day', 'expiry_reminder', 'fees_due', 'custom_broadcast') NOT NULL,
                `template_body` TEXT NOT NULL,
                `status` ENUM('active', 'inactive') DEFAULT 'active',
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

            try {
                $this->pdo->exec("ALTER TABLE `marketing_templates` MODIFY COLUMN `trigger_event` ENUM('welcome', 'birthday', 'anniversary', 'expiry_3_weeks', 'expiry_1_week', 'expiry_2_days', 'expiry_same_day', 'expiry_reminder', 'fees_due', 'custom_broadcast') NOT NULL;");
            } catch (Exception $e) {}

            // Ensure marketing_logs table exists and has trigger_event column
            $this->pdo->exec("CREATE TABLE IF NOT EXISTS `marketing_logs` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `member_id` INT DEFAULT NULL,
                `recipient_phone` VARCHAR(25) NOT NULL,
                `message_type` VARCHAR(20) DEFAULT 'whatsapp',
                `trigger_event` VARCHAR(50) DEFAULT 'custom_broadcast',
                `message_body` TEXT NOT NULL,
                `sent_status` ENUM('sent', 'delivered', 'pending', 'failed') DEFAULT 'sent',
                `sent_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

            try {
                $this->pdo->exec("ALTER TABLE `marketing_logs` ADD COLUMN `trigger_event` VARCHAR(50) DEFAULT 'custom_broadcast' AFTER `message_type`;");
            } catch (Exception $e) {}

            // Ensure sales table has notes, due_date, updated_at columns and updated payment_status ENUM
            try {
                $this->pdo->exec("ALTER TABLE `sales` ADD COLUMN `due_date` DATE NULL AFTER `due_amount`;");
            } catch (Exception $e) {}
            try {
                $this->pdo->exec("ALTER TABLE `sales` ADD COLUMN `notes` TEXT NULL AFTER `utr_ref`;");
            } catch (Exception $e) {}
            try {
                $this->pdo->exec("ALTER TABLE `sales` ADD COLUMN `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER `created_at`;");
            } catch (Exception $e) {}
            try {
                $this->pdo->exec("ALTER TABLE `sales` MODIFY COLUMN `payment_status` ENUM('paid', 'partial', 'due', 'voided', 'cancelled', 'refunded') DEFAULT 'paid';");
            } catch (Exception $e) {}

            // Ensure member_subscriptions has freeze tracking columns
            try {
                $this->pdo->exec("ALTER TABLE `membership_types` ADD COLUMN `max_freeze_count` INT DEFAULT 1 AFTER `max_freeze_days`;");
            } catch (Exception $e) {}
            try {
                $this->pdo->exec("ALTER TABLE `member_subscriptions` ADD COLUMN `freeze_count` INT DEFAULT 0 AFTER `freeze_days_used`;");
            } catch (Exception $e) {}
            try {
                $this->pdo->exec("ALTER TABLE `member_subscriptions` ADD COLUMN `freeze_type` ENUM('temporary', 'permanent') DEFAULT 'permanent' AFTER `freeze_count`;");
            } catch (Exception $e) {}
            try {
                $this->pdo->exec("ALTER TABLE `member_subscriptions` ADD COLUMN `auto_unfreeze_date` DATE NULL AFTER `freeze_type`;");
            } catch (Exception $e) {}
            try {
                $this->pdo->exec("ALTER TABLE `member_subscriptions` ADD COLUMN `freeze_start_date` DATE NULL AFTER `auto_unfreeze_date`;");
            } catch (Exception $e) {}
            try {
                $this->pdo->exec("ALTER TABLE `member_subscriptions` ADD COLUMN `frozen_remaining_days` INT DEFAULT 0 AFTER `freeze_start_date`;");
            } catch (Exception $e) {}
            try {
                $this->pdo->exec("ALTER TABLE `freeze_history` ADD COLUMN `freeze_type` ENUM('temporary', 'permanent') DEFAULT 'permanent' AFTER `subscription_id`;");
            } catch (Exception $e) {}
            try {
                $this->pdo->exec("ALTER TABLE `freeze_history` ADD COLUMN `auto_unfreeze_date` DATE NULL AFTER `freeze_days`;");
            } catch (Exception $e) {}
            try {
                $this->pdo->exec("ALTER TABLE `freeze_history` MODIFY COLUMN `end_date` DATE NULL DEFAULT NULL;");
            } catch (Exception $e) {}

            // Auto-Unfreeze routine for Temporary Freezes whose auto_unfreeze_date has arrived
            try {
                $today = date('Y-m-d');
                $dueTempFreezes = $this->pdo->query("
                    SELECT s.*, m.id as mem_id 
                    FROM member_subscriptions s 
                    JOIN members m ON s.member_id = m.id 
                    WHERE s.status = 'frozen' 
                      AND s.freeze_type = 'temporary' 
                      AND s.auto_unfreeze_date IS NOT NULL 
                      AND s.auto_unfreeze_date <= '{$today}'
                ")->fetchAll();

                foreach ($dueTempFreezes as $tf) {
                    $remDays = intval($tf['frozen_remaining_days']);
                    if ($remDays <= 0) {
                        $remDays = max(1, (int) ceil((strtotime($tf['end_date']) - strtotime($tf['freeze_start_date'])) / 86400));
                    }
                    $newEndDate = date('Y-m-d', strtotime("{$today} + {$remDays} days"));
                    $freezeDaysSpent = max(1, (int) ceil((strtotime($today) - strtotime($tf['freeze_start_date'])) / 86400));
                    $totalFreezeUsed = intval($tf['freeze_days_used']) + $freezeDaysSpent;

                    $this->pdo->exec("
                        UPDATE member_subscriptions 
                        SET status = 'active', end_date = '{$newEndDate}', freeze_days_used = {$totalFreezeUsed}, 
                            frozen_remaining_days = 0, freeze_type = 'permanent', auto_unfreeze_date = NULL, freeze_start_date = NULL, updated_at = NOW() 
                        WHERE id = {$tf['id']}
                    ");
                    $this->pdo->exec("UPDATE members SET status = 'active' WHERE id = {$tf['mem_id']}");
                    $this->pdo->exec("UPDATE freeze_history SET end_date = '{$today}' WHERE subscription_id = {$tf['id']} AND end_date IS NULL");
                }
            } catch (Exception $e) {}

            // Schema migrations for Club 777 Plan features
            try {
                $this->pdo->exec("ALTER TABLE `membership_types` ADD COLUMN `service_type` ENUM('gym', 'pool', 'steam', 'sauna', 'vip_combo', 'general') DEFAULT 'gym' AFTER `category`;");
            } catch (Exception $e) {}
            try {
                $this->pdo->exec("ALTER TABLE `membership_types` ADD COLUMN `free_gifts` TEXT NULL AFTER `included_services`;");
            } catch (Exception $e) {}
            try {
                $this->pdo->exec("ALTER TABLE `membership_types` ADD COLUMN `coupons_count` INT DEFAULT 0 AFTER `free_gifts`;");
            } catch (Exception $e) {}
            try {
                $this->pdo->exec("ALTER TABLE `membership_types` ADD COLUMN `original_price` DECIMAL(10,2) NULL AFTER `price`;");
            } catch (Exception $e) {}
            try {
                $this->pdo->exec("ALTER TABLE `pool_plans` ADD COLUMN `free_gifts` TEXT NULL AFTER `sessions_limit`;");
            } catch (Exception $e) {}
            try {
                $this->pdo->exec("ALTER TABLE `pool_plans` ADD COLUMN `original_price` DECIMAL(10,2) NULL AFTER `price`;");
            } catch (Exception $e) {}

            // Seed/Ensure Categories
            $categoriesSeed = [
                [1, 'Membership Plans', 'badge'],
                [2, 'Personal Training', 'user-check'],
                [3, 'Supplements', 'package'],
                [4, 'Drinks & Beverages', 'coffee'],
                [5, 'Gym Gear & Accessories', 'shopping-bag'],
                [6, 'Swimming Pool', 'droplet'],
                [7, 'Steam Bath', 'cloud'],
                [8, 'Sauna Bath', 'sun'],
                [9, 'VIP & All-Inclusive Combos', 'crown']
            ];
            foreach ($categoriesSeed as $cs) {
                try {
                    $this->pdo->prepare("INSERT INTO `categories` (`id`, `name`, `icon`) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `icon` = VALUES(`icon`)")->execute($cs);
                } catch (Exception $e) {}
            }

            // Seed / Update Official THE CLUB 777® Plans (Gym, Swimming Pool, Steam Bath, Sauna Bath, VIP All-Inclusive Combo)
            $club777Plans = [
                // 1. Gymnasium Plans
                ['Gym - Happy Hour Pass', 'trial', 'gym', 0, 1, 150.00, 150.00, 0, 0, 'Happy Hour (6 AM - 12 PM & 12 PM - 4 PM)', 'Gym Floor, Cardio Zone', '', 0, 'Gym Floor & Cardio Access during Happy Hours'],
                ['Gym - 1 Month Plan', 'monthly', 'gym', 1, 30, 1777.00, 2000.00, 7, 1, 'Full Day (6:00 AM - 10:00 PM)', 'Gym Floor, Cardio Zone, Locker Room', '', 0, 'Full Day Access to Gym Floor & Cardio Zone (Offer ₹1777, Reg ₹2000)'],
                ['Gym - 3 Months Pass', 'quarterly', 'gym', 3, 90, 4777.00, 4777.00, 15, 2, 'Full Day (6:00 AM - 10:00 PM)', 'Gym Floor, Cardio Zone, Locker', '1-Hand Towel + 1 T-Shirt', 0, 'Full Gym Access + Free 1-Hand Towel + 1 T-Shirt'],
                ['Gym - 6 Months Pass', 'half_yearly', 'gym', 6, 180, 8777.00, 8777.00, 30, 3, 'Full Day (6:00 AM - 10:00 PM)', 'Gym Floor, Cardio Zone, Locker, Steam', '1 T-Shirt + 1 Shaker + 1 Hand Towel', 0, 'Full Gym Access + Free 1 T-Shirt + 1 Shaker + 1 Hand Towel'],
                ['Gym - 1 Year Gold Pass', 'yearly', 'gym', 12, 365, 15777.00, 15777.00, 60, 4, 'Full Day (6:00 AM - 10:00 PM)', 'VIP Gym Floor, Cardio, Locker, 12 Steam Sessions', '1-Hand Towel + 1 T-Shirt + 1 Shaker + 1 Gym Bag + 12 Steam', 12, 'VIP All-Facility Access + Free 1-Hand Towel + 1 T-Shirt + 1 Shaker + 1 Gym Bag + 12 Steam Bath Sessions'],

                // 2. Swimming Pool Plans
                ['Swimming Pool - Happy Hour Pass', 'trial', 'pool', 0, 1, 120.00, 200.00, 0, 0, 'Morning Happy Hour Slot', 'Olympic Heated Pool, Shower Lockers', '', 0, 'Pool Single Session Happy Hour Pass (Special ₹120, Reg ₹150/200)'],
                ['Swimming Pool - 1 Month Pass', 'monthly', 'pool', 1, 30, 1777.00, 2000.00, 7, 1, 'Morning 6-10 AM & Evening 5-9 PM', 'Heated Olympic Pool, Shower & Lockers', '', 0, 'Unlimited Heated Olympic Swimming Pool Access (Offer ₹1777, Reg ₹2000)'],
                ['Swimming Pool - 3 Months Pass', 'quarterly', 'pool', 3, 90, 4777.00, 4777.00, 15, 2, 'Morning 6-10 AM & Evening 5-9 PM', 'Olympic Heated Pool Access, Lifeguard on Duty', '1 Costume', 0, 'Olympic Heated Pool Access + Free 1 Swimming Costume'],
                ['Swimming Pool - 6 Months Pass', 'half_yearly', 'pool', 6, 180, 8777.00, 8777.00, 30, 3, 'Morning 6-10 AM & Evening 5-9 PM', 'Olympic Heated Pool Access, Lifeguard, Steam', '1 Costume + 1 Googles + 1 Towel', 0, 'Heated Swimming Pool Access + Free 1 Costume + 1 Googles + 1 Towel'],
                ['Swimming Pool - 1 Year Pass', 'yearly', 'pool', 12, 365, 15777.00, 15777.00, 60, 4, 'Morning 6-10 AM & Evening 5-9 PM', 'VIP Heated Pool Pass, Master Coach Assistance', '1 Costume + 1 Googles + 1 Towel + 1 Cap', 0, 'VIP Heated Pool Pass + Free 1 Costume + 1 Googles + 1 Towel + 1 Swimming Cap'],

                // 3. Steam Bath Plans
                ['Steam Bath - Happy Hour Pass', 'trial', 'steam', 0, 1, 500.00, 500.00, 0, 0, 'Spa Timings (7:00 AM - 9:00 PM)', '1 Steam Bath Session, Towel & Shower', '1 Steam Session', 1, 'Single Entry Steam Bath Session Pass'],
                ['Steam Bath - 1 Month (7 Coupons)', 'monthly', 'steam', 1, 30, 1777.00, 1777.00, 0, 0, 'Spa Timings (7:00 AM - 9:00 PM)', '7 Steam Bath Sessions / Coupons', '7 Steam Coupons', 7, '1 Month Steam Bath Pass with 7 Session Coupons'],
                ['Steam Bath - 3 Months (21 Coupons)', 'quarterly', 'steam', 3, 90, 4777.00, 4777.00, 15, 1, 'Spa Timings (7:00 AM - 9:00 PM)', '21 Steam Bath Sessions / Coupons', '21 Steam Coupons', 21, '3 Months Steam Bath Pass with 21 Session Coupons'],
                ['Steam Bath - 6 Months (42 Coupons)', 'half_yearly', 'steam', 6, 180, 8777.00, 8777.00, 30, 2, 'Spa Timings (7:00 AM - 9:00 PM)', '42 Steam Bath Sessions / Coupons', '42 Steam Coupons', 42, '6 Months Steam Bath Pass with 42 Session Coupons'],
                ['Steam Bath - 1 Year (84 Coupons)', 'yearly', 'steam', 12, 365, 15777.00, 15777.00, 60, 4, 'Spa Timings (7:00 AM - 9:00 PM)', '84 Steam Bath Sessions / Coupons', '84 Steam Coupons', 84, '1 Year Steam Bath Pass with 84 Session Coupons'],

                // 4. Sauna Bath Plans
                ['Sauna Bath - Happy Hour Pass', 'trial', 'sauna', 0, 1, 500.00, 500.00, 0, 0, 'Spa Timings (7:00 AM - 9:00 PM)', '1 Sauna Bath Session, Towel & Shower', '1 Sauna Session', 1, 'Single Entry Sauna Bath Session Pass'],
                ['Sauna Bath - 1 Month (7 Coupons)', 'monthly', 'sauna', 1, 30, 1777.00, 1777.00, 0, 0, 'Spa Timings (7:00 AM - 9:00 PM)', '7 Sauna Bath Sessions / Coupons', '7 Sauna Coupons', 7, '1 Month Sauna Bath Pass with 7 Session Coupons'],
                ['Sauna Bath - 3 Months (21 Coupons)', 'quarterly', 'sauna', 3, 90, 4777.00, 4777.00, 15, 1, 'Spa Timings (7:00 AM - 9:00 PM)', '21 Sauna Bath Sessions / Coupons', '21 Sauna Coupons', 21, '3 Months Sauna Bath Pass with 21 Session Coupons'],
                ['Sauna Bath - 6 Months (42 Coupons)', 'half_yearly', 'sauna', 6, 180, 8777.00, 8777.00, 30, 2, 'Spa Timings (7:00 AM - 9:00 PM)', '42 Sauna Bath Sessions / Coupons', '42 Sauna Coupons', 42, '6 Months Sauna Bath Pass with 42 Session Coupons'],
                ['Sauna Bath - 1 Year (84 Coupons)', 'yearly', 'sauna', 12, 365, 15777.00, 15777.00, 60, 4, 'Spa Timings (7:00 AM - 9:00 PM)', '84 Sauna Bath Sessions / Coupons', '84 Sauna Coupons', 84, '1 Year Sauna Bath Pass with 84 Session Coupons'],

                // 5. VIP All-Inclusive Club 777 Combo (Special Discount for Ladies & Couples)
                ['VIP All-Inclusive Club 777 Pass (Ladies & Couples Special)', 'vip', 'vip_combo', 12, 365, 35777.00, 78885.00, 60, 4, 'VIP Full Day Priority Access', 'Gymnasium, Swimming Pool, Steam Bath, Sauna Bath, Jacuzzi, Cafeteria', '21 Meals (7 Juices + 7 Shakes + 7 Sandwiches) + 1 Gym Bag + 1 Gym Towel + 1 T-Shirt + 1 Shaker + 1 Costume (Gifts worth Rs. 2200/-)', 0, 'SPECIAL DISCOUNT FOR LADIES & COUPLES (Original ₹78,885 -> Special ₹35,777): Inclusions: Gymnasium + Swimming Pool + Steam Bath + Sauna Bath + Jacuzzi + Cafeteria. Complimentary Gifts: 21 Meals (7 Juices + 7 Shakes + 7 Sandwiches) + 1 Gym Bag + 1 Gym Towel + 1 T-Shirt + 1 Shaker + 1 Costume (Gifts worth Rs. 2200/-)']
            ];

            foreach ($club777Plans as $cp) {
                try {
                    $chk = $this->pdo->prepare("SELECT id FROM membership_types WHERE title = ?");
                    $chk->execute([$cp[0]]);
                    $existing = $chk->fetchColumn();
                    if (!$existing) {
                        $ins = $this->pdo->prepare("
                            INSERT INTO membership_types (title, category, service_type, duration_months, duration_days, price, original_price, max_freeze_days, max_freeze_count, access_timing, included_services, free_gifts, coupons_count, description, status) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')
                        ");
                        $ins->execute([$cp[0], $cp[1], $cp[2], $cp[3], $cp[4], $cp[5], $cp[6], $cp[7], $cp[8], $cp[9], $cp[10], $cp[11], $cp[12], $cp[13]]);
                    } else {
                        $upd = $this->pdo->prepare("
                            UPDATE membership_types 
                            SET category = ?, service_type = ?, duration_months = ?, duration_days = ?, price = ?, original_price = ?, max_freeze_days = ?, max_freeze_count = ?, access_timing = ?, included_services = ?, free_gifts = ?, coupons_count = ?, description = ?, status = 'active'
                            WHERE id = ?
                        ");
                        $upd->execute([$cp[1], $cp[2], $cp[3], $cp[4], $cp[5], $cp[6], $cp[7], $cp[8], $cp[9], $cp[10], $cp[11], $cp[12], $cp[13], $existing]);
                    }
                } catch (Exception $e) {}
            }

            // Seed & Synchronize Official THE CLUB 777® Brand Settings (Non-GST / Tax-Free)
            $club777Settings = [
                'gym_name' => 'THE CLUB 777®',
                'gym_tagline' => 'GYM, SWIM & MORE...',
                'gym_phone' => '8053576777, 8053570777, 9416528777',
                'gym_address' => 'Behind Shehnai Garden, Near Railway Station, Jhajjar - 124103 (Haryana)',
                'gym_email' => 'theclub777jjr@gmail.com',
                'gym_facebook' => 'theclub777jhajjar',
                'currency_symbol' => '₹',
                'tax_rate' => '0.00',
                'upi_id' => '8053576777@upi',
                'allow_expired_checkin' => '1',
                'duplicate_attendance_window_mins' => '5',
                'primary_color' => '#EAB308'
            ];
            $setStmt = $this->pdo->prepare("INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `setting_value` = VALUES(`setting_value`)");
            foreach ($club777Settings as $k => $v) {
                try {
                    $setStmt->execute([$k, $v]);
                } catch (Exception $e) {}
            }
            try {
                $this->pdo->exec("DELETE FROM `settings` WHERE `setting_key` = 'gstin'");
            } catch (Exception $e) {}

            // Sync Main Branch with The Club 777 Jhajjar
            try {
                $this->pdo->prepare("
                    INSERT INTO `branches` (`id`, `code`, `name`, `phone`, `address`, `city`, `state`, `pin_code`) VALUES 
                    (1, 'BR-777', 'THE CLUB 777® Jhajjar Branch', '8053576777', 'Behind Shehnai Garden, Near Railway Station', 'Jhajjar', 'Haryana', '124103')
                    ON DUPLICATE KEY UPDATE 
                        `name` = VALUES(`name`), 
                        `phone` = VALUES(`phone`), 
                        `address` = VALUES(`address`), 
                        `city` = VALUES(`city`), 
                        `state` = VALUES(`state`), 
                        `pin_code` = VALUES(`pin_code`)
                ")->execute();
            } catch (Exception $e) {}

            // Seed or update English & Festive reminder templates for THE CLUB 777®
            $templates = [
                ['Trial Pass Welcome & Experience Message', 'trial_welcome', "🏋️‍♂️ Hello {name}! Welcome to THE CLUB 777®! 🎉 We are thrilled you experienced our fitness floor & swimming pool today!\n\n🔥 Hope you had a power-packed session! Our certified trainers, Olympic heated pool, and shower/steam facilities are here for your complete transformation.\n\n🎁 SPECIAL TRIAL CONVERSION PERK:\nUpgrade to our Regular 3-Month, 6-Month or Annual Plan within 48 hours & get:\n✅ 100% Admission Fee Waived\n✅ FREE 1-on-1 Personal Training Session\n✅ FREE Customized Nutrition Chart\n\nVisit front desk at Behind Shehnai Garden, Near Railway Station, Jhajjar or call {gym_phone} to claim your offer! 💪"],
                ['Diwali Mega Dhamaka Offer', 'diwali_offer', "🪔 Happy Diwali {name}! May this festival of lights bring abundant health, strength, and joy to you and your family! ✨\n\n💥 THE CLUB 777® DIWALI MEGA FITNESS DHAMAKA:\nGet UP TO 40% OFF on 6-Month & Annual Gym + Pool Memberships!\n🎁 Buy 1-Year Membership & Get 2 Months EXTRA FREE + Free Gym Kit Bag!\n\n⏳ Offer valid till Diwali weekend only. Visit THE CLUB 777® (Near Railway Station, Jhajjar) or call {gym_phone} to lock your festive pass! 🏋️‍♂️🔥"],
                ['Festive Season Special Offer', 'festive_offer', "🎊 Festive Greetings from THE CLUB 777®, {name}! Celebrate this festive season by gifting yourself supreme health & fitness! ✨\n\n🎁 FESTIVE SPECIAL DEAL:\nEnjoy FLAT 25% DISCOUNT on all Transformation & Annual Passes + Free Diet Plan!\n\nContact reception at Behind Shehnai Garden, Jhajjar or call {gym_phone} to claim today! 🚀"],
                ['New Year Fitness Resolution Offer', 'new_year_offer', "🎉 Happy New Year, {name}! 🚀 Make 2027 your fittest and strongest year yet at THE CLUB 777®!\n\n💥 NEW YEAR SPECIAL PASS:\nGet FLAT 30% OFF on all 6-Month & 12-Month memberships + 2 Free PT Sessions!\n\nReply RESOLUTION or call {gym_phone} to lock your festive discount! 💪"],
                ['3 Weeks Expiry Reminder', 'expiry_3_weeks', "👋 Hi {name}, this is a gentle reminder that your {plan_name} at THE CLUB 777® is expiring in 3 weeks on {end_date}. Renew early to stay committed to your fitness journey! Contact: {gym_phone}"],
                ['1 Week Expiry Reminder', 'expiry_1_week', "⚠️ Hi {name}, your {plan_name} at THE CLUB 777® will expire in 7 days on {end_date}. Don't pause your workout streak—visit the front desk or renew your membership today! Contact: {gym_phone}"],
                ['2 Days Expiry Reminder', 'expiry_2_days', "🚨 Urgent Alert: Hi {name}, only 2 days remain on your THE CLUB 777® membership ({end_date}). Renew today to keep your biometric gate access active without interruption! Contact: {gym_phone}"],
                ['Same Day Expiry Alert', 'expiry_same_day', "🔔 Membership Expiring Today: Hi {name}, your THE CLUB 777® membership expires today ({end_date}). Please renew today to continue enjoying uninterrupted gym & pool access! Contact: {gym_phone}"],
                ['Fees Due & Expired Membership Alert', 'fees_due', "🚨 Fees Due Notice: Hi {name}, your {plan_name} at THE CLUB 777® has expired on {end_date}. Your gym access & biometric punch are currently paused. Please clear your renewal dues or visit the reception at Jhajjar today! Phone: {gym_phone}"],
                ['Birthday Special Wishes', 'birthday', "🎂 Happy Birthday {name}! THE CLUB 777® wishes you great health, strength, and happiness! 🎉 Enjoy a 15% discount on your next membership renewal or cafe voucher today!"],
                ['New Member Welcome', 'welcome', "🎉 Welcome to THE CLUB 777®, {name}! Your Member Code is {member_code}. Behind Shehnai Garden, Near Railway Station, Jhajjar (Phone: {gym_phone}). We are thrilled to guide your fitness transformation!"],
                ['Joining Anniversary Special', 'anniversary', "⭐ Happy Workout Anniversary {name}! Thank you for completing another strong year with THE CLUB 777®. Enjoy a free PT & Spa consultation session!"]
            ];

            $chkStmt = $this->pdo->prepare("SELECT id FROM marketing_templates WHERE trigger_event = ? LIMIT 1");
            $updStmt = $this->pdo->prepare("UPDATE marketing_templates SET title = ?, template_body = ?, status = 'active' WHERE trigger_event = ?");
            $insStmt = $this->pdo->prepare("INSERT INTO marketing_templates (title, trigger_event, template_body, status) VALUES (?, ?, ?, 'active')");
            foreach ($templates as $t) {
                $chkStmt->execute([$t[1]]);
                $existingId = $chkStmt->fetchColumn();
                if ($existingId) {
                    $updStmt->execute([$t[0], $t[2], $t[1]]);
                } else {
                    $insStmt->execute([$t[0], $t[1], $t[2]]);
                }
            }

            // Ensure Swimming Pool tables exist
            $this->pdo->exec("CREATE TABLE IF NOT EXISTS `pool_plans` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `title` VARCHAR(120) NOT NULL,
                `category` VARCHAR(50) DEFAULT 'monthly',
                `duration_months` INT DEFAULT 1,
                `duration_days` INT DEFAULT 30,
                `price` DECIMAL(10,2) NOT NULL,
                `sessions_limit` INT DEFAULT 0,
                `slot_timing` VARCHAR(150) DEFAULT 'Morning 6:00 AM - 10:00 AM & Evening 5:00 PM - 9:00 PM',
                `coach_name` VARCHAR(100) DEFAULT 'Certified Swim Coach & Lifeguard',
                `description` TEXT,
                `status` ENUM('active', 'inactive') DEFAULT 'active',
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

            $this->pdo->exec("CREATE TABLE IF NOT EXISTS `pool_subscriptions` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `member_id` INT NOT NULL,
                `pool_plan_id` INT NOT NULL,
                `start_date` DATE NOT NULL,
                `end_date` DATE NOT NULL,
                `sessions_total` INT DEFAULT 0,
                `sessions_used` INT DEFAULT 0,
                `sessions_remaining` INT DEFAULT 0,
                `price_paid` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                `slot_assigned` VARCHAR(100) DEFAULT 'Morning Slot (6-9 AM)',
                `status` ENUM('active', 'expired', 'cancelled') DEFAULT 'active',
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

            $this->pdo->exec("CREATE TABLE IF NOT EXISTS `pool_logs` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `member_id` INT NOT NULL,
                `pool_subscription_id` INT DEFAULT NULL,
                `entry_time` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `slot_name` VARCHAR(100) DEFAULT 'Morning Batch',
                `notes` TEXT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

            // 23. Staff Shifts & Smart Biometric Attendance Tables
            $this->pdo->exec("CREATE TABLE IF NOT EXISTS `staff_shifts` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `name` VARCHAR(100) NOT NULL,
                `start_time` TIME NOT NULL,
                `end_time` TIME NOT NULL,
                `grace_period_mins` INT DEFAULT 15,
                `half_day_threshold_mins` INT DEFAULT 120,
                `color` VARCHAR(20) DEFAULT '#3B82F6',
                `status` ENUM('active', 'inactive') DEFAULT 'active',
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

            try {
                $this->pdo->exec("ALTER TABLE `staff` ADD COLUMN `shift_id` INT NULL AFTER `role`;");
            } catch (Exception $e) {}
            try {
                $this->pdo->exec("ALTER TABLE `staff` ADD COLUMN `biometric_id` VARCHAR(50) NULL AFTER `phone`;");
            } catch (Exception $e) {}
            try {
                $this->pdo->exec("ALTER TABLE `staff` ADD COLUMN `permissions` TEXT NULL AFTER `base_salary`;");
            } catch (Exception $e) {}
            try {
                $this->pdo->exec("ALTER TABLE `staff` ADD COLUMN `status` ENUM('active', 'inactive') DEFAULT 'active' AFTER `permissions`;");
            } catch (Exception $e) {}

            try {
                $this->pdo->exec("ALTER TABLE `members` ADD COLUMN `blood_group` VARCHAR(10) DEFAULT 'B+' AFTER `gender`;");
            } catch (Exception $e) {}
            try {
                $this->pdo->exec("ALTER TABLE `members` ADD COLUMN `locker_no` VARCHAR(20) DEFAULT 'LKR-12' AFTER `blood_group`;");
            } catch (Exception $e) {}
            try {
                $this->pdo->exec("ALTER TABLE `marketing_templates` MODIFY COLUMN `trigger_event` VARCHAR(100) NOT NULL;");
            } catch (Exception $e) {}

            $this->pdo->exec("CREATE TABLE IF NOT EXISTS `staff_attendance` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `staff_id` INT NOT NULL,
                `date` DATE NOT NULL,
                `shift_id` INT NULL,
                `check_in_time` DATETIME NOT NULL,
                `check_out_time` DATETIME DEFAULT NULL,
                `status` ENUM('present', 'late', 'half_day', 'absent', 'on_leave') DEFAULT 'present',
                `late_minutes` INT DEFAULT 0,
                `late_reason` VARCHAR(255) DEFAULT NULL,
                `overtime_minutes` INT DEFAULT 0,
                `working_hours` DECIMAL(5,2) DEFAULT 0.00,
                `verification_method` ENUM('biometric', 'rfid', 'face_id', 'manual', 'qr') DEFAULT 'biometric',
                `device_id` INT DEFAULT NULL,
                `notes` TEXT DEFAULT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (`staff_id`) REFERENCES `staff`(`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

            // Seed / Update standard 5-9 Subah & 5-9 Sham Staff Shifts
            $shifts = [
                [1, 'Morning Shift (Subah 5:00 AM - 9:00 AM)', '05:00:00', '09:00:00', 15, 60, '#10B981'],
                [2, 'Evening Shift (Sham 5:00 PM - 9:00 PM)', '17:00:00', '21:00:00', 15, 60, '#F59E0B'],
                [3, 'Double / Split Shift (Subah 5-9 AM + Sham 5-9 PM)', '05:00:00', '21:00:00', 15, 120, '#8B5CF6'],
                [4, 'Full Day General Shift (5:00 AM - 9:00 PM)', '05:00:00', '21:00:00', 20, 180, '#3B82F6']
            ];

            foreach ($shifts as $sh) {
                $chkSh = $this->pdo->prepare("SELECT id FROM staff_shifts WHERE id = ?");
                $chkSh->execute([$sh[0]]);
                if ($chkSh->fetch()) {
                    $this->pdo->prepare("UPDATE staff_shifts SET name = ?, start_time = ?, end_time = ?, grace_period_mins = ?, half_day_threshold_mins = ?, color = ?, status = 'active' WHERE id = ?")
                        ->execute([$sh[1], $sh[2], $sh[3], $sh[4], $sh[5], $sh[6], $sh[0]]);
                } else {
                    $this->pdo->prepare("INSERT INTO staff_shifts (id, name, start_time, end_time, grace_period_mins, half_day_threshold_mins, color, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'active')")
                        ->execute([$sh[0], $sh[1], $sh[2], $sh[3], $sh[4], $sh[5], $sh[6]]);
                }
            }

            // Assign default shifts and biometric IDs to existing staff if missing
            try {
                $this->pdo->exec("UPDATE staff SET shift_id = 1, biometric_id = CONCAT('STF-', LPAD(id, 3, '0')) WHERE shift_id IS NULL OR biometric_id IS NULL");
            } catch (Exception $e) {}

            // Seed / Update Official THE CLUB 777® Swimming Pool Plans
            $club777PoolPlans = [
                ['Swimming Pool - Happy Hour Pass', 'daily', 0, 1, 120.00, 200.00, 1, 'Morning Happy Hour Slot', 'Certified Swim Coach & Lifeguard', '', 'Single Session Pool Happy Hour Pass (Special ₹120, Reg ₹150/200)'],
                ['Swimming Pool - 1 Month Pass', 'monthly', 1, 30, 1777.00, 2000.00, 0, 'Morning 6-10 AM & Evening 5-9 PM', 'Certified Swim Coach & Lifeguard', '', 'Unlimited Heated Olympic Swimming Pool Access (Offer ₹1777, Reg ₹2000)'],
                ['Swimming Pool - 3 Months Pass', 'quarterly', 3, 90, 4777.00, 4777.00, 0, 'Morning 6-10 AM & Evening 5-9 PM', 'Master Coach & Lifeguard', '1 Costume', 'Olympic Heated Pool Access + Free 1 Swimming Costume'],
                ['Swimming Pool - 6 Months Pass', 'half_yearly', 6, 180, 8777.00, 8777.00, 0, 'Morning 6-10 AM & Evening 5-9 PM', 'Master Coach & Lifeguard', '1 Costume + 1 Googles + 1 Towel', 'Heated Swimming Pool Access + Free 1 Costume + 1 Googles + 1 Towel'],
                ['Swimming Pool - 1 Year Pass', 'yearly', 12, 365, 15777.00, 15777.00, 0, 'Morning 6-10 AM & Evening 5-9 PM', 'VIP Head Coach & Lifeguard', '1 Costume + 1 Googles + 1 Towel + 1 Cap', 'VIP Heated Pool Pass + Free 1 Costume + 1 Googles + 1 Towel + 1 Swimming Cap']
            ];

            foreach ($club777PoolPlans as $cpp) {
                try {
                    $chkP = $this->pdo->prepare("SELECT id FROM pool_plans WHERE title = ?");
                    $chkP->execute([$cpp[0]]);
                    $pId = $chkP->fetchColumn();
                    if (!$pId) {
                        $this->pdo->prepare("INSERT INTO pool_plans (title, category, duration_months, duration_days, price, original_price, sessions_limit, slot_timing, coach_name, free_gifts, description, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')")
                            ->execute([$cpp[0], $cpp[1], $cpp[2], $cpp[3], $cpp[4], $cpp[5], $cpp[6], $cpp[7], $cpp[8], $cpp[9], $cpp[10]]);
                    } else {
                        $this->pdo->prepare("UPDATE pool_plans SET category = ?, duration_months = ?, duration_days = ?, price = ?, original_price = ?, sessions_limit = ?, slot_timing = ?, coach_name = ?, free_gifts = ?, description = ?, status = 'active' WHERE id = ?")
                            ->execute([$cpp[1], $cpp[2], $cpp[3], $cpp[4], $cpp[5], $cpp[6], $cpp[7], $cpp[8], $cpp[9], $cpp[10], $pId]);
                    }
                } catch (Exception $e) {}
            }

            // Seed Marketing Templates if missing
            try {
                $chkM = $this->pdo->prepare("SELECT COUNT(*) FROM marketing_templates WHERE trigger_event = ?");
                $chkM->execute(['trial_welcome']);
                if ($chkM->fetchColumn() == 0) {
                    $this->pdo->exec("INSERT INTO `marketing_templates` (`title`, `trigger_event`, `template_body`, `status`) VALUES
                    ('Same-Day Trial Welcome & 48h Offer', 'trial_welcome', '🔥 *Welcome to FITZONE Gym, {name}!* 🔥\\n\\nWe are thrilled you experienced your trial pass with us today! 💪\\n\\n🎁 *Special Same-Day Conversion Offer:* Upgrade to our Monthly or Yearly Plan within 48 hours and receive:\\n✅ ₹1,000 Admission Fee 100% WAIVED\\n✅ 1 Free Personal Training Session\\n✅ Customized Nutrition Diet Chart\\n\\nVisit reception or call us at {gym_phone} to claim!', 'active'),
                    ('Diwali Mega Dhamaka Offer', 'diwali_offer', '🪔 *Happy Diwali from FITZONE Gym, {name}!* 🪔\\n\\nLight up your fitness journey with our *DIWALI MEGA DHAMAKA OFFER*:\\n💥 Get *3 MONTHS EXTRA FREE* on any 6 or 12 Month Membership!\\n💥 Free Access to Swimming Pool & Steam Bath\\n💥 Free Fitness Assessment & Body Scan\\n\\nOffer valid for limited slots! Call {gym_phone} now.', 'active'),
                    ('Festive Season Special Offer', 'festive_offer', '🎉 *Festive Season Special Discount at FITZONE Gym!* 🎉\\n\\nDear {name}, enjoy *FLAT 25% OFF* on all Annual Memberships & Personal Training Packages this festive week!\\n\\n💪 Don\'t miss out—start your transformation today. Contact reception at {gym_phone}.', 'active'),
                    ('New Year Fitness Resolution Offer', 'new_year_offer', '🚀 *New Year, Stronger You!* 🚀\\n\\nDear {name}, kickstart your New Year resolution with FITZONE Gym:\\n🔥 Join our 12-Month Plan and get 2 Months FREE + ₹500 Supplement Voucher!\\n\\nVisit {gym_name} or call {gym_phone} to get started.', 'active')");
                }

                $chkOff = $this->pdo->prepare("SELECT COUNT(*) FROM marketing_templates WHERE trigger_event = ?");
                $chkOff->execute(['pool_off_notice']);
                if ($chkOff->fetchColumn() == 0) {
                    $this->pdo->exec("INSERT INTO `marketing_templates` (`title`, `trigger_event`, `template_body`, `status`) VALUES
                    ('Swimming Pool Closed Tomorrow Notice', 'pool_off_notice', '🏊 *Notice: Swimming Pool Closed Tomorrow* 🏊\\n\\nDear {name},\\n\\nPlease be informed that the Swimming Pool at FITZONE Gym will remain *CLOSED tomorrow ({tomorrow_date})* due to scheduled deep cleaning, chemical treatment & water filtration maintenance.\\n\\n✅ Regular swimming batches will resume as normal from the day after.\\n\\nWe apologize for the temporary inconvenience and appreciate your cooperation! 🙏\\n\\nFor queries, contact front desk at {gym_phone}.', 'active'),
                    ('Personal Training (PT) Off Tomorrow Notice', 'pt_off_notice', '💪 *Notice: Personal Training (PT) Sessions Off Tomorrow* 💪\\n\\nDear {name},\\n\\nPlease note that Personal Training (PT) sessions with your assigned trainer will remain *OFF / SUSPENDED tomorrow ({tomorrow_date})* due to trainer workshop & schedule.\\n\\n✅ *Note:* Your session count will *NOT* be deducted and will be adjusted in your package.\\n\\nRegular 1-on-1 PT sessions will resume normally the day after. Keep up your fitness dedication!\\n\\nFor queries, contact your trainer or reception at {gym_phone}.', 'active'),
                    ('Gym Holiday / Maintenance Closed Notice', 'gym_holiday_notice', '🏋️ *Gym Holiday Notice: Closed Tomorrow* 🏋️\\n\\nDear {name},\\n\\nPlease note that FITZONE Gym will remain *CLOSED tomorrow ({tomorrow_date})* on the occasion of the public holiday/festival.\\n\\nRegular workout hours will resume normally from the following day.\\n\\nStay fit, stay strong! 💪\\n\\nWarm regards,\\n*{gym_name}* ({gym_phone})', 'active')");
                }
            } catch (Exception $e) {}

            // Ensure Lockers table exists & is seeded
            $this->pdo->exec("CREATE TABLE IF NOT EXISTS `lockers` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `branch_id` INT DEFAULT 1,
                `locker_number` VARCHAR(50) NOT NULL,
                `member_id` INT DEFAULT NULL,
                `status` ENUM('available', 'occupied', 'maintenance') DEFAULT 'available',
                `assigned_date` DATE DEFAULT NULL,
                `expiry_date` DATE DEFAULT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (`member_id`) REFERENCES `members`(`id`) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

            try {
                $lockerCnt = $this->pdo->query("SELECT COUNT(*) FROM `lockers`")->fetchColumn();
                if ($lockerCnt == 0) {
                    $insLocker = $this->pdo->prepare("INSERT INTO `lockers` (`locker_number`, `member_id`, `status`) VALUES (?, ?, ?)");
                    $insLocker->execute(['LKR-01', 1001, 'occupied']);
                    $insLocker->execute(['LKR-02', 1002, 'occupied']);
                    $insLocker->execute(['LKR-03', null, 'available']);
                    $insLocker->execute(['LKR-04', null, 'available']);
                    $insLocker->execute(['LKR-05', 1004, 'occupied']);
                    $insLocker->execute(['LKR-06', null, 'available']);
                    $insLocker->execute(['LKR-07', null, 'available']);
                    $insLocker->execute(['LKR-08', null, 'available']);
                    $insLocker->execute(['LKR-09', null, 'maintenance']);
                    $insLocker->execute(['LKR-10', null, 'available']);
                    $insLocker->execute(['LKR-11', null, 'available']);
                    $insLocker->execute(['LKR-12', null, 'available']);
                }
            } catch (Exception $e) {}

            // Ensure Equipment table exists & is seeded
            $this->pdo->exec("CREATE TABLE IF NOT EXISTS `equipment` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `branch_id` INT DEFAULT 1,
                `name` VARCHAR(120) NOT NULL,
                `brand` VARCHAR(100) DEFAULT 'Commercial Pro',
                `serial_number` VARCHAR(100) DEFAULT NULL,
                `purchase_date` DATE DEFAULT NULL,
                `last_service_date` DATE DEFAULT NULL,
                `next_service_date` DATE DEFAULT NULL,
                `status` ENUM('operational', 'maintenance_due', 'under_repair', 'out_of_order') DEFAULT 'operational',
                `notes` TEXT,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

            try {
                $equipCnt = $this->pdo->query("SELECT COUNT(*) FROM `equipment`")->fetchColumn();
                if ($equipCnt == 0) {
                    $insEquip = $this->pdo->prepare("INSERT INTO `equipment` (`name`, `brand`, `serial_number`, `purchase_date`, `last_service_date`, `next_service_date`, `status`) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $today = date('Y-m-d');
                    $nextM = date('Y-m-d', strtotime('+30 days'));
                    $prevM = date('Y-m-d', strtotime('-60 days'));
                    $insEquip->execute(['Matrix Commercial Treadmill T7xe', 'Matrix Fitness', 'MX-TRD-401', '2025-01-10', $prevM, $nextM, 'operational']);
                    $insEquip->execute(['Life Fitness Dual Cable Cross Pulley', 'Life Fitness', 'LF-CBL-102', '2025-02-15', $prevM, $nextM, 'operational']);
                    $insEquip->execute(['Hammer Strength Olympic Incline Bench', 'Hammer Strength', 'HS-BEN-204', '2025-03-01', $prevM, $nextM, 'operational']);
                    $insEquip->execute(['Precor Commercial Elliptical Trainer', 'Precor', 'PR-ELP-308', '2025-01-20', $prevM, $today, 'operational']);
                    $insEquip->execute(['Cybex Seated Leg Press 45 Degree', 'Cybex Pro', 'CY-LGP-505', '2025-04-10', $prevM, $nextM, 'operational']);
                }
            } catch (Exception $e) {}

            // Ensure member_fitness table exists & is seeded
            $this->pdo->exec("CREATE TABLE IF NOT EXISTS `member_fitness` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `member_id` INT NOT NULL,
                `weight_kg` DECIMAL(5,2) DEFAULT 72.00,
                `height_cm` DECIMAL(5,2) DEFAULT 175.00,
                `bmi` DECIMAL(4,1) DEFAULT 23.5,
                `body_fat_pct` DECIMAL(4,1) DEFAULT 16.0,
                `workout_plan` TEXT,
                `diet_plan` TEXT,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (`member_id`) REFERENCES `members`(`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

            try {
                $fitCnt = $this->pdo->query("SELECT COUNT(*) FROM `member_fitness`")->fetchColumn();
                if ($fitCnt == 0) {
                    $this->pdo->exec("INSERT INTO `member_fitness` (`member_id`, `weight_kg`, `height_cm`, `bmi`, `body_fat_pct`, `workout_plan`, `diet_plan`) VALUES
                    (1001, 74.5, 176.0, 24.1, 15.8, 'Day 1: Chest & Triceps (Bench Press, Incline DB, Dips)\nDay 2: Back & Biceps (Deadlifts, Pull-ups, Rows)\nDay 3: Legs & Core (Squats, Leg Press, Planks)\nDay 4: Shoulders & Abs (Overhead Press, Lateral Raises)\nDay 5: HIIT Cardio & Stretching', '• Breakfast: 4 Boiled Eggs / Oats + Whey Protein Scoop\n• Lunch: Grilled Chicken / Paneer + Brown Rice + Green Veggies\n• Pre-Workout: Banana + Black Coffee\n• Dinner: Stir-fried Fish / Tofu + Salad\n• Water: 3.5 Liters Daily'),
                    (1002, 58.0, 163.0, 21.8, 20.5, 'Day 1: Full Body HIIT & Cardio\nDay 2: Lower Body Glutes & Hamstrings\nDay 3: Upper Body Sculpt & Core\nDay 4: Yoga & Flexibility Recovery', '• Breakfast: Greek Yogurt + Berries + Almonds\n• Lunch: Quinoa Bowl + Tofu / Soya + Spinach\n• Snack: Protein Smoothie\n• Dinner: Soup + Grilled Veggies')");
                }
            } catch (Exception $e) {}
        } catch (PDOException $e) {
            // Log or ignore if already created
        }
    }
}

/**
 * Get Global PDO Connection
 */
function getDB()
{
    return Database::getInstance();
}

/**
 * Helper: Log Security & Action Audits
 */
function logAuditAction(?int $userId, string $action, string $module, $oldVal = null, $newVal = null): void
{
    try {
        $db = getDB();
        $userName = $_SESSION['user_name'] ?? 'System';
        $role = $_SESSION['user_role'] ?? 'admin';
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $device = $_SERVER['HTTP_USER_AGENT'] ?? 'Web App Browser';

        $oldStr = is_array($oldVal) || is_object($oldVal) ? json_encode($oldVal) : (string) $oldVal;
        $newStr = is_array($newVal) || is_object($newVal) ? json_encode($newVal) : (string) $newVal;

        $stmt = $db->prepare("INSERT INTO audit_logs (user_id, user_name, role, action, module, old_value, new_value, ip_address, device_info) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$userId ?: ($_SESSION['user_id'] ?? 1), $userName, $role, $action, $module, $oldStr, $newStr, $ip, substr($device, 0, 250)]);
    } catch (Exception $e) {
        // Silently skip if audit logging fails
    }
}

/**
 * Helper: Output JSON API Response
 */
function jsonResponse(bool $success, array $data = [], string $message = '', int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => (bool) $success,
        'message' => $message,
        'data' => $data,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    exit;
}

/**
 * Helper: Fetch Setting Value
 */
function getSetting(string $key, string $default = ''): string
{
    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $res = $stmt->fetchColumn();
        return $res !== false ? $res : $default;
    } catch (Exception $e) {
        return $default;
    }
}

/**
 * Helper: Generate Invoice Number
 */
function generateInvoiceNo()
{
    $db = getDB();
    $year = date('Y');
    $stmt = $db->prepare("SELECT COUNT(*) FROM sales WHERE YEAR(created_at) = ?");
    $stmt->execute([$year]);
    $count = $stmt->fetchColumn() + 1;
    return "INV-{$year}-" . str_pad($count, 5, '0', STR_PAD_LEFT);
}

/**
 * Helper: Generate Member Code
 */
function generateMemberCode()
{
    $db = getDB();
    $stmt = $db->query("SELECT MAX(id) FROM members");
    $maxId = $stmt->fetchColumn();
    $nextId = $maxId ? ($maxId + 1) : 1001;
    return "M-{$nextId}";
}

/**
 * 🛡️ Role-Based Access Control (RBAC) Helpers
 */
function getCurrentUserRole(): string
{
    return strtolower(trim($_SESSION['user_role'] ?? 'super_admin'));
}

function hasPageAccess(string $page, ?string $role = null): bool
{
    if ($role === null) {
        $role = getCurrentUserRole();
    }
    $role = strtolower(trim($role));

    // Super Admin & Gym Owner have unconditional full access to everything
    if ($role === 'super_admin' || $role === 'gym_owner') {
        return true;
    }

    // Role-specific permission matrix
    $rolePermissions = [
        'branch_manager' => [
            'dashboard', 'pos', 'attendance', 'members', 'member_profile', 'trials', 'payments', 
            'receipt', 'memberships', 'pt', 'trainers', 'pool', 'communication', 'marketing', 
            'crm', 'member_portal', 'inventory', 'expenses', 'staff', 'payroll', 'payslip', 
            'reports', 'devices'
        ],
        'receptionist' => [
            'dashboard', 'pos', 'attendance', 'members', 'member_profile', 'trials', 'payments', 
            'receipt', 'memberships', 'pt', 'trainers', 'pool', 'communication', 'crm', 
            'member_portal', 'inventory'
        ],
        'staff' => [
            'dashboard', 'pos', 'attendance', 'members', 'member_profile', 'trials', 'payments', 
            'receipt', 'memberships', 'pt', 'trainers', 'pool', 'crm', 'member_portal', 
            'inventory'
        ],
        'sales_exec' => [
            'dashboard', 'pos', 'members', 'member_profile', 'trials', 'payments', 'receipt', 
            'memberships', 'crm', 'marketing', 'communication', 'member_portal'
        ],
        'trainer' => [
            'dashboard', 'attendance', 'members', 'member_profile', 'pt', 'trainers', 
            'workout', 'diet', 'progress', 'member_portal'
        ],
        'accountant' => [
            'dashboard', 'payments', 'receipt', 'memberships', 'inventory', 'expenses', 
            'staff', 'payroll', 'payslip', 'reports'
        ]
    ];

    $allowedList = $rolePermissions[$role] ?? $rolePermissions['receptionist'];
    return in_array(strtolower(trim($page)), $allowedList);
}

function getRoleDisplayName(string $role): string
{
    $names = [
        'super_admin'    => 'Super Admin',
        'gym_owner'      => 'Gym Owner',
        'branch_manager' => 'Branch Manager',
        'receptionist'   => 'Receptionist',
        'staff'          => 'Front Desk Staff',
        'sales_exec'     => 'Sales Executive',
        'trainer'        => 'Fitness Trainer',
        'accountant'     => 'Accountant'
    ];
    return $names[strtolower(trim($role))] ?? ucfirst($role);
}

/**
 * Dispatch WhatsApp message directly via configured WhatsApp Gateway
 */
function sendWhatsAppMessage(string $phone, string $message, bool $isBulk = false): array
{
    $nodeGatewayUrl = getSetting('whatsapp_node_url', 'http://127.0.0.1:3001');
    if (empty($nodeGatewayUrl) || empty($phone) || empty($message)) {
        return ['success' => false, 'error' => 'Missing gateway URL, phone, or message'];
    }

    $cleanPhone = preg_replace('/[^0-9]/', '', $phone);
    if (strlen($cleanPhone) === 10) {
        $cleanPhone = '91' . $cleanPhone;
    }

    $url = rtrim($nodeGatewayUrl, '/') . '/send';
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'phone'   => $cleanPhone,
        'message' => $message,
        'is_bulk' => $isBulk
    ]));
    curl_setopt($ch, CURLOPT_TIMEOUT, 12);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);

    $resp = curl_exec($ch);
    $err = curl_error($ch);
    if (PHP_VERSION_ID < 80500 && function_exists('curl_close')) {
        @curl_close($ch);
    }

    if ($err || !$resp) {
        return ['success' => false, 'error' => $err ?: 'Gateway unreachable'];
    }

    $json = json_decode($resp, true);
    return is_array($json) ? $json : ['success' => false, 'error' => 'Invalid gateway response'];
}

