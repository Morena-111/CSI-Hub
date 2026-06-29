-- ============================================================
-- CSI HUB — Schema Update: Documents + Events tables
-- Run this in phpMyAdmin → csi_hub database → SQL tab
-- ============================================================

-- ─── CSI HUB DATABASE ───────────────────────────────────────────────
CREATE DATABASE IF NOT EXISTS csi_hub
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE csi_hub;

-- ─── COMPANIES ───────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS companies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    sector VARCHAR(100),
    initials VARCHAR(10),
    status ENUM('active','inactive','pending') DEFAULT 'active',
    since_year YEAR NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ─── SCHOOLS ───────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS schools (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    location VARCHAR(255),
    province VARCHAR(100),
    district VARCHAR(255),
    school_type VARCHAR(100),
    funding_requested DECIMAL(15,2) DEFAULT 0,
    funding_granted DECIMAL(15,2) DEFAULT 0,
    status ENUM('active','inactive','pending') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);


-- ─── PARTNERSHIPS ───────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS partnerships (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_id INT NOT NULL,
    school_id INT NOT NULL,
    amount DECIMAL(15,2) DEFAULT 0,
    focus_area VARCHAR(255),
    start_date DATE,
    end_date DATE,
    description TEXT,
    status ENUM('pending','active','completed','cancelled') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_partnership_company
        FOREIGN KEY (company_id)
        REFERENCES companies(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_partnership_school
        FOREIGN KEY (school_id)
        REFERENCES schools(id)
        ON DELETE CASCADE
);

-- ─── IMPACT STATS ───────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS impact_stats (
    id INT AUTO_INCREMENT PRIMARY KEY,
    partnership_id INT NOT NULL,
    learners INT DEFAULT 0,
    educators INT DEFAULT 0,
    classrooms INT DEFAULT 0,
    computers INT DEFAULT 0,
    books INT DEFAULT 0,
    schools_supported INT DEFAULT 0,
    reporting_period VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_impact_partnership
        FOREIGN KEY (partnership_id)
        REFERENCES partnerships(id)
        ON DELETE CASCADE
);


-- ─── DOCUMENTS ───────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS documents (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  title          VARCHAR(255) NOT NULL,
  description    TEXT,
  file_name      VARCHAR(255) NOT NULL,
  file_type      VARCHAR(50),
  file_size      INT,
  partnership_id INT NULL,
  company_id     INT NULL,
  school_id      INT NULL,
  category       ENUM('MOU','Report','Proposal','Invoice','Other') DEFAULT 'Other',
  uploaded_by    VARCHAR(100),
  created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (partnership_id) REFERENCES partnerships(id) ON DELETE SET NULL,
  FOREIGN KEY (company_id)     REFERENCES companies(id)    ON DELETE SET NULL,
  FOREIGN KEY (school_id)      REFERENCES schools(id)      ON DELETE SET NULL
);

-- ─── EVENTS ──────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS events (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  title          VARCHAR(255) NOT NULL,
  description    TEXT,
  event_date     DATE NOT NULL,
  event_time     TIME,
  location       VARCHAR(255),
  event_type     ENUM('Site Visit','Meeting','Deadline','Review','Other') DEFAULT 'Other',
  partnership_id INT NULL,
  company_id     INT NULL,
  school_id      INT NULL,
  status         ENUM('upcoming','completed','cancelled') DEFAULT 'upcoming',
  created_by     VARCHAR(100),
  created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (partnership_id) REFERENCES partnerships(id) ON DELETE SET NULL,
  FOREIGN KEY (company_id)     REFERENCES companies(id)    ON DELETE SET NULL,
  FOREIGN KEY (school_id)      REFERENCES schools(id)      ON DELETE SET NULL
);

-- ─── SAMPLE EVENTS ───────────────────────────────────────────
INSERT IGNORE INTO events (title, description, event_date, event_time, location, event_type, partnership_id, status, created_by) VALUES
('Site Visit — Diepsloot Secondary', 'TechCorp lab inspection and learner demo', DATE_ADD(CURDATE(), INTERVAL 3 DAY),  '09:00:00', 'Diepsloot Secondary School, Gauteng', 'Site Visit', 1, 'upcoming', 'admin'),
('Quarterly Review Meeting',         'All active partners virtual check-in',     DATE_ADD(CURDATE(), INTERVAL 9 DAY),  '14:00:00', 'Virtual (Teams)',                     'Review',     NULL,        'upcoming', 'admin'),
('MOU Signing — Nedbank Foundation', 'Formal partnership agreement signing',     DATE_ADD(CURDATE(), INTERVAL 14 DAY), '10:00:00', 'Nedbank Head Office, Sandton',       'Meeting',    6,           'upcoming', 'admin'),
('Q2 Report Deadline',               'Submit Q2 progress reports to all funders',DATE_ADD(CURDATE(), INTERVAL 21 DAY), '17:00:00', NULL,                                  'Deadline',   NULL,        'upcoming', 'admin');