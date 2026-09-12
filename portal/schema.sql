-- Startup Sites PH — Client Portal schema
-- Import this once via Hostinger's phpMyAdmin (Databases → Manage → phpMyAdmin →
-- Import) against the empty MySQL database you create for the portal.
--
-- If you already imported an earlier version of this schema (before roles,
-- payments, maintenance, or expenses existed), drop the database and
-- re-import fresh — there's no real client data on it yet to preserve.

CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(190) NOT NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role VARCHAR(20) NOT NULL DEFAULT 'developer',
  created_at DATETIME NOT NULL
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
  live_url VARCHAR(255) DEFAULT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Individual payments received from a client. Paid-so-far and balance are
-- always computed as SUM(amount) rather than stored, so they can never
-- drift out of sync, and monthly totals can be graphed on the dashboard.
CREATE TABLE IF NOT EXISTS payments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  client_id INT NOT NULL,
  amount DECIMAL(10,2) NOT NULL,
  paid_date DATE NOT NULL,
  note VARCHAR(255) DEFAULT NULL,
  created_at DATETIME NOT NULL,
  FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Line-item breakdown of what a client's monthly maintenance covers
-- (hosting, specific plugin licenses, monitoring, etc). The monthly total
-- shown and emailed is always the sum of these rows — never a separate
-- manually-entered number that could drift from the actual breakdown.
CREATE TABLE IF NOT EXISTS maintenance_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  client_id INT NOT NULL,
  label VARCHAR(190) NOT NULL,
  amount DECIMAL(10,2) NOT NULL,
  created_at DATETIME NOT NULL,
  FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- General business expenses. client_id is optional — link an expense to a
-- specific client's costs, or leave it null for overhead (hosting, tools,
-- salaries) that isn't tied to any one client.
CREATE TABLE IF NOT EXISTS expenses (
  id INT AUTO_INCREMENT PRIMARY KEY,
  client_id INT DEFAULT NULL,
  label VARCHAR(190) NOT NULL,
  amount DECIMAL(10,2) NOT NULL,
  expense_date DATE NOT NULL,
  created_at DATETIME NOT NULL,
  FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE SET NULL
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
  assigned_to INT DEFAULT NULL,
  photo_path VARCHAR(255) DEFAULT NULL,
  created_at DATETIME NOT NULL,
  FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
  FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- A row is either a pasted external link (url set, stored_path null) or an
-- uploaded file served from portal/uploads/ (stored_path set, url null).
CREATE TABLE IF NOT EXISTS files (
  id INT AUTO_INCREMENT PRIMARY KEY,
  client_id INT NOT NULL,
  label VARCHAR(190) NOT NULL,
  url VARCHAR(500) DEFAULT NULL,
  stored_path VARCHAR(255) DEFAULT NULL,
  created_at DATETIME NOT NULL,
  FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
