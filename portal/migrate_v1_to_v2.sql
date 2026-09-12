-- Safe migration from the original portal schema to the current one.
-- Run this ONCE if your database still only has: clients, files, notes,
-- settings, tasks (no users/payments/maintenance_items/expenses).
--
-- This does NOT drop or wipe anything — it only creates the tables that
-- are missing and adds the columns the app now expects, and carries
-- forward any "amount paid" you'd already entered per client into the new
-- payments ledger so that data isn't lost.
--
-- In phpMyAdmin: select your database → SQL tab → paste this whole file →
-- Go.

-- STEP 1: Create the tables that don't exist yet (safe no-op for any that
-- already do — none of these do yet, but this makes it safe to re-run).
CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(190) NOT NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role VARCHAR(20) NOT NULL DEFAULT 'developer',
  created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS payments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  client_id INT NOT NULL,
  amount DECIMAL(10,2) NOT NULL,
  paid_date DATE NOT NULL,
  note VARCHAR(255) DEFAULT NULL,
  created_at DATETIME NOT NULL,
  FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS maintenance_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  client_id INT NOT NULL,
  label VARCHAR(190) NOT NULL,
  amount DECIMAL(10,2) NOT NULL,
  created_at DATETIME NOT NULL,
  FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS expenses (
  id INT AUTO_INCREMENT PRIMARY KEY,
  client_id INT DEFAULT NULL,
  label VARCHAR(190) NOT NULL,
  amount DECIMAL(10,2) NOT NULL,
  expense_date DATE NOT NULL,
  created_at DATETIME NOT NULL,
  FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- STEP 2: Carry forward any existing per-client "amount paid" total into
-- the new payments ledger, as a single migrated entry, so it isn't
-- silently hidden once the app stops reading clients.amount_paid.
INSERT INTO payments (client_id, amount, paid_date, note, created_at)
SELECT id, amount_paid, CURDATE(), 'Migrated from previous total paid', NOW()
FROM clients
WHERE amount_paid > 0;

-- STEP 3: Add the columns the app now expects on tables that already
-- exist (schema.sql's CREATE TABLE IF NOT EXISTS can't do this on its own
-- for tables that are already present).
ALTER TABLE tasks
  ADD COLUMN assigned_to INT DEFAULT NULL,
  ADD COLUMN photo_path VARCHAR(255) DEFAULT NULL,
  ADD CONSTRAINT fk_tasks_assigned_to FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL;

ALTER TABLE files
  ADD COLUMN stored_path VARCHAR(255) DEFAULT NULL,
  MODIFY COLUMN url VARCHAR(500) NULL;

-- Not touched on purpose: clients.amount_paid (now unused by the app, but
-- left in place rather than risk a destructive DROP COLUMN on live data)
-- and the old `settings` table (no longer used, harmless to leave).
