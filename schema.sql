-- ============================================================
--  Nagrik Seva — Database Schema
--  Run this in your MySQL client or phpMyAdmin
-- ============================================================

CREATE DATABASE IF NOT EXISTS nagrik_seva
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE nagrik_seva;

-- ── USERS ────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS users (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name          VARCHAR(120)  NOT NULL,
  email         VARCHAR(180)  NOT NULL UNIQUE,
  phone         VARCHAR(20)   DEFAULT NULL,
  password_hash VARCHAR(255)  NOT NULL,
  role          ENUM('citizen','officer','regulator') NOT NULL DEFAULT 'citizen',
  zone          VARCHAR(80)   DEFAULT NULL,       -- For officers: which zone
  avatar        VARCHAR(255)  DEFAULT NULL,       -- Profile photo path
  is_active     TINYINT(1)    NOT NULL DEFAULT 1,
  last_login    DATETIME      DEFAULT NULL,
  created_at    TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_email (email),
  INDEX idx_role  (role)
) ENGINE=InnoDB;


-- ── OTP TOKENS ───────────────────────────────────────────
CREATE TABLE IF NOT EXISTS otp_tokens (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email      VARCHAR(180) NOT NULL UNIQUE,
  otp        VARCHAR(6)   NOT NULL,
  expires_at DATETIME     NOT NULL,
  created_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_otp_email (email)
) ENGINE=InnoDB;


-- ── COMPLAINTS ───────────────────────────────────────────
-- (You'll need this for the dashboard pages coming next)
CREATE TABLE IF NOT EXISTS complaints (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  complaint_no  VARCHAR(20)  NOT NULL UNIQUE,      -- e.g. GRV-1001
  citizen_id    INT UNSIGNED NOT NULL,
  officer_id    INT UNSIGNED DEFAULT NULL,
  category      ENUM('road','water','electricity','sanitation','property','lost') NOT NULL,
  title         VARCHAR(200) NOT NULL,
  description   TEXT         NOT NULL,
  location      VARCHAR(250) NOT NULL,
  latitude      DECIMAL(10,7) DEFAULT NULL,
  longitude     DECIMAL(10,7) DEFAULT NULL,
  zone          VARCHAR(80)  DEFAULT NULL,
  status        ENUM('new','assigned','in_progress','resolved','closed','escalated')
                NOT NULL DEFAULT 'new',
  priority      ENUM('low','medium','high') NOT NULL DEFAULT 'medium',
  photo_path    VARCHAR(255) DEFAULT NULL,         -- uploaded photo
  officer_notes TEXT         DEFAULT NULL,
  resolved_at   DATETIME     DEFAULT NULL,
  created_at    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (citizen_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (officer_id) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_status (status),
  INDEX idx_citizen (citizen_id),
  INDEX idx_officer (officer_id),
  INDEX idx_zone    (zone)
) ENGINE=InnoDB;


-- ── NOTIFICATIONS ────────────────────────────────────────
CREATE TABLE IF NOT EXISTS notifications (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id      INT UNSIGNED NOT NULL,
  complaint_id INT UNSIGNED DEFAULT NULL,
  type         VARCHAR(50)  NOT NULL,   -- 'status_update','assigned','resolved' etc
  message      TEXT         NOT NULL,
  is_read      TINYINT(1)   NOT NULL DEFAULT 0,
  created_at   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id)      REFERENCES users(id)      ON DELETE CASCADE,
  FOREIGN KEY (complaint_id) REFERENCES complaints(id) ON DELETE SET NULL,
  INDEX idx_user_unread (user_id, is_read)
) ENGINE=InnoDB;


-- ── SEED: Demo users (password = "Test@1234" for all) ────
-- Hash generated with: password_hash('Test@1234', PASSWORD_DEFAULT)
INSERT IGNORE INTO users (name, email, phone, password_hash, role, zone, is_active) VALUES
  ('Rahul Naik',     'citizen@demo.com',   '9876543210',
   '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uRe6i3e26', 'citizen',   NULL,    1),
  ('Officer Parab',  'officer@demo.com',   '9876543211',
   '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uRe6i3e26', 'officer',   'Panaji',1),
  ('Inspector Dias', 'regulator@demo.com', '9876543212',
   '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uRe6i3e26', 'regulator', NULL,    1);

-- ── AUTO-INCREMENT complaint numbers ─────────────────────
-- A simple trigger to auto-generate GRV-XXXX
DELIMITER $$
CREATE TRIGGER IF NOT EXISTS before_insert_complaint
BEFORE INSERT ON complaints
FOR EACH ROW
BEGIN
  DECLARE next_id INT;
  SELECT AUTO_INCREMENT INTO next_id
  FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = 'nagrik_seva' AND TABLE_NAME = 'complaints';
  SET NEW.complaint_no = CONCAT('GRV-', LPAD(next_id, 4, '0'));
END$$
DELIMITER ;