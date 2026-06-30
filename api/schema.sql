-- ============================================================
-- schema_fix.sql — Run this in phpMyAdmin to fix all errors
-- CSI Hub — Research Unlimited
-- ============================================================

-- Use the correct database
USE csi_hub;

-- ============================================================
-- TABLE: companies (partners)
-- ============================================================
CREATE TABLE IF NOT EXISTS companies (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  name       VARCHAR(255) NOT NULL,
  sector     VARCHAR(100),
  since_year VARCHAR(10),
  status     ENUM('active','inactive','pending') DEFAULT 'active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================
-- TABLE: schools
-- ============================================================
CREATE TABLE IF NOT EXISTS schools (
  id                 INT AUTO_INCREMENT PRIMARY KEY,
  name               VARCHAR(255) NOT NULL,
  location           VARCHAR(255),
  province           VARCHAR(100),
  district           VARCHAR(150),
  school_type        VARCHAR(50) DEFAULT 'Public',
  status             ENUM('active','inactive','pending') DEFAULT 'active',
  funding_requested  DECIMAL(15,2) DEFAULT 0,
  funding_granted    DECIMAL(15,2) DEFAULT 0,
  created_at         TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================
-- TABLE: partnerships  ← THIS IS WHERE YOUR ERROR WAS
-- ============================================================
CREATE TABLE IF NOT EXISTS partnerships (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  company_id  INT NOT NULL,
  school_id   INT NOT NULL,
  focus_area  VARCHAR(100),
  amount      DECIMAL(15,2) DEFAULT 0,
  start_date  DATE,
  end_date    DATE,
  status      ENUM('active','pending','completed','paused') DEFAULT 'pending',
  notes       TEXT,
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
  FOREIGN KEY (school_id)  REFERENCES schools(id)   ON DELETE CASCADE
);

-- Add missing columns if table already exists but is missing them
ALTER TABLE partnerships
  ADD COLUMN IF NOT EXISTS start_date  DATE AFTER amount,
  ADD COLUMN IF NOT EXISTS end_date    DATE AFTER start_date,
  ADD COLUMN IF NOT EXISTS notes       TEXT AFTER status,
  ADD COLUMN IF NOT EXISTS created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER notes;

-- ============================================================
-- TABLE: documents
-- ============================================================
CREATE TABLE IF NOT EXISTS documents (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  title        VARCHAR(255) NOT NULL,
  file_name    VARCHAR(255) NOT NULL,
  category     ENUM('MOU','Report','Proposal','Invoice','Tax Clearance','B-BBEE Certificate','Company Registration','Bank Details','Programme Proposal','Other') DEFAULT 'Other',
  uploaded_by  VARCHAR(100),
  notes        TEXT,
  created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Add missing category options if table exists
ALTER TABLE documents MODIFY COLUMN category VARCHAR(100) DEFAULT 'Other';

-- ============================================================
-- TABLE: events
-- ============================================================
CREATE TABLE IF NOT EXISTS events (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  title        VARCHAR(255) NOT NULL,
  event_type   ENUM('Site Visit','Meeting','Deadline','Review','Training','Other') DEFAULT 'Other',
  event_date   DATE NOT NULL,
  event_time   TIME,
  company_id   INT,
  school_id    INT,
  notes        TEXT,
  status       ENUM('upcoming','completed','cancelled') DEFAULT 'upcoming',
  created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================
-- TABLE: impact_stats
-- ============================================================
CREATE TABLE IF NOT EXISTS impact_stats (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  partnership_id INT NOT NULL,
  report_date    DATE NOT NULL,
  learners       INT DEFAULT 0,
  educators      INT DEFAULT 0,
  notes          TEXT,
  recorded_by    VARCHAR(100),
  created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (partnership_id) REFERENCES partnerships(id) ON DELETE CASCADE
);

-- ============================================================
-- TABLE: surveys (for admin survey feature)
-- ============================================================
CREATE TABLE IF NOT EXISTS surveys (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  title       VARCHAR(255) NOT NULL,
  description TEXT,
  target_type ENUM('all','companies','schools') DEFAULT 'all',
  status      ENUM('draft','active','closed') DEFAULT 'draft',
  created_by  VARCHAR(100),
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS survey_questions (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  survey_id   INT NOT NULL,
  question    TEXT NOT NULL,
  type        ENUM('text','rating','yesno','multiple') DEFAULT 'text',
  options     TEXT,
  sort_order  INT DEFAULT 0,
  FOREIGN KEY (survey_id) REFERENCES surveys(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS survey_responses (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  survey_id    INT NOT NULL,
  question_id  INT NOT NULL,
  respondent   VARCHAR(100),
  answer       TEXT,
  submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================
-- SAMPLE DATA — only inserts if tables are empty
-- ============================================================

-- Sample companies
INSERT IGNORE INTO companies (id, name, sector, since_year, status) VALUES
(1, 'TechCorp SA',        'Technology',          '2024', 'active'),
(2, 'Absa Foundation',    'Financial Services',  '2023', 'active'),
(3, 'Sasol Foundation',   'Energy',              '2025', 'active'),
(4, 'Nedbank Foundation', 'Financial Services',  NULL,   'pending');

-- Sample schools
INSERT IGNORE INTO schools (id, name, location, province, school_type, status) VALUES
(1, 'Diepsloot Secondary School',  'Diepsloot, Gauteng',          'Gauteng',       'Public', 'active'),
(2, 'Umlazi Combined School',      'Umlazi, KwaZulu-Natal',       'KwaZulu-Natal', 'Public', 'active'),
(3, 'Tshepiso Primary School',     'Sebokeng, Gauteng',           'Gauteng',       'Public', 'active'),
(4, 'Soweto Arts & Culture School','Soweto, Gauteng',             'Gauteng',       'Public', 'active'),
(5, 'Secunda Technical High',      'Secunda, Mpumalanga',         'Mpumalanga',    'Public', 'active'),
(6, 'Cape Flats STEM Academy',     'Mitchells Plain, Western Cape','Western Cape',  'Public', 'pending');

-- Sample partnerships
INSERT IGNORE INTO partnerships (id, company_id, school_id, focus_area, amount, start_date, end_date, status) VALUES
(1, 1, 1, 'STEM',           800000, '2026-01-01', '2026-12-31', 'active'),
(2, 1, 2, 'Digital Skills', 600000, '2026-03-01', '2027-02-28', 'active'),
(3, 2, 3, 'Literacy',       450000, '2025-07-01', '2026-06-30', 'active'),
(4, 3, 5, 'Science',        550000, '2026-02-01', '2027-01-31', 'active'),
(5, 4, 6, 'STEM',           300000, '2026-07-01', '2027-06-30', 'pending');

-- ============================================================
-- VERIFY — run this to confirm all tables exist
-- ============================================================
SHOW TABLES;