CREATE TABLE IF NOT EXISTS essl_devices (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  serial_number VARCHAR(100) NOT NULL,
  device_name VARCHAR(150) NULL,
  last_seen DATETIME NULL,
  last_stamp VARCHAR(50) NULL,
  last_ip VARCHAR(45) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_serial (serial_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS essl_employees (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  device_pin VARCHAR(100) NOT NULL,
  employee_name VARCHAR(255) NULL,
  card_no VARCHAR(100) NULL,
  device_serial VARCHAR(100) NULL,
  raw_data TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_pin_device (device_pin, device_serial)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS essl_attendance (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  device_serial VARCHAR(100) NOT NULL,
  employee_pin VARCHAR(100) NOT NULL,
  punch_time DATETIME NOT NULL,
  status VARCHAR(20) NULL,
  verify_mode VARCHAR(20) NULL,
  work_code VARCHAR(50) NULL,
  raw_line TEXT NULL,
  received_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_employee_time (employee_pin, punch_time),
  KEY idx_device_time (device_serial, punch_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS essl_raw_requests (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  device_serial VARCHAR(100) NULL,
  request_method VARCHAR(10) NOT NULL,
  request_uri TEXT NULL,
  query_string TEXT NULL,
  request_body MEDIUMTEXT NULL,
  received_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_raw_device_time (device_serial, received_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS essl_commands (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  device_serial VARCHAR(100) NOT NULL,
  command_text TEXT NOT NULL,
  status ENUM('pending','sent') NOT NULL DEFAULT 'pending',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  sent_at DATETIME NULL,
  PRIMARY KEY (id),
  KEY idx_cmd_device_status (device_serial, status, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
