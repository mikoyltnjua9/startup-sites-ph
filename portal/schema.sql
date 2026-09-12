-- Startup Sites PH — Client Portal schema
-- Import this once via Hostinger's phpMyAdmin (Databases → Manage → phpMyAdmin →
-- Import) against the empty MySQL database you create for the portal.

CREATE TABLE IF NOT EXISTS settings (
  `key` VARCHAR(64) PRIMARY KEY,
  `value` TEXT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS clients (
  id INT AUTO_INCREMENT PRIMARY KEY,
  company_name VARCHAR(190) NOT NULL,
  contact_name VARCHAR(190) NOT NULL,
  email VARCHAR(190) DEFAULT NULL,
  phone VARCHAR(60) DEFAULT NULL,
  source VARCHAR(190) DEFAULT NULL,
  service_type VARCHAR(30) NOT NULL DEFAULT 'web_design',
  stage VARCHAR(30) NOT NULL DEFAULT 'lead',
  start_date DATE DEFAULT NULL,
  target_launch_date DATE DEFAULT NULL,
  actual_launch_date DATE DEFAULT NULL,
  next_follow_up_date DATE DEFAULT NULL,
  total_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
  amount_paid DECIMAL(10,2) NOT NULL DEFAULT 0,
  live_url VARCHAR(255) DEFAULT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS notes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  client_id INT NOT NULL,
  body TEXT NOT NULL,
  created_at DATETIME NOT NULL,
  FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tasks (
  id INT AUTO_INCREMENT PRIMARY KEY,
  client_id INT NOT NULL,
  label VARCHAR(255) NOT NULL,
  is_done TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS files (
  id INT AUTO_INCREMENT PRIMARY KEY,
  client_id INT NOT NULL,
  label VARCHAR(190) NOT NULL,
  url VARCHAR(500) NOT NULL,
  created_at DATETIME NOT NULL,
  FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
