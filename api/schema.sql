-- ============================================================
-- CSI HUB — Schema Update: Documents + Events tables
-- Run this in phpMyAdmin → csi_hub database → SQL tab
-- ============================================================

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