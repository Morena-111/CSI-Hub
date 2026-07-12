-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jul 12, 2026 at 09:56 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `csi_hub`
--
CREATE DATABASE IF NOT EXISTS csi_hub
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE csi_hub;
-- --------------------------------------------------------

--
-- Table structure for table `companies`
--

CREATE TABLE IF NOT EXISTS `companies` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `sector` varchar(100) DEFAULT NULL,
  `since_year` varchar(10) DEFAULT NULL,
  `status` enum('active','inactive','pending') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `entity_type` enum('company','school','ngo','other') DEFAULT 'company'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `companies`
--

INSERT INTO `companies` (`id`, `name`, `sector`, `since_year`, `status`, `created_at`, `entity_type`) VALUES
(1, 'Research Unlimited', 'CSI Partner', NULL, 'active', '2026-07-07 10:23:59', 'company'),
(2, 'M&L TECH', 'CSI Partner', NULL, 'active', '2026-07-07 10:43:24', 'company'),
(3, 'Cwele Logistics', 'CSI Partner', NULL, 'active', '2026-07-08 07:43:04', 'company');

-- --------------------------------------------------------

--
-- Table structure for table `documents`
--

CREATE TABLE IF NOT EXISTS `documents` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `category` varchar(100) DEFAULT 'Other',
  `uploaded_by` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `partnership_id` int(11) DEFAULT NULL,
  `company_id` int(11) DEFAULT NULL,
  `school_id` int(11) DEFAULT NULL,
  `file_size` bigint(20) DEFAULT 0,
  `file_type` varchar(20) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `admin_note` text DEFAULT NULL,
  `reviewed_by` varchar(100) DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `email_log`
--

CREATE TABLE IF NOT EXISTS `email_log` (
  `id` int(11) NOT NULL,
  `to_email` varchar(255) DEFAULT NULL,
  `subject` varchar(255) DEFAULT NULL,
  `type` varchar(100) DEFAULT NULL,
  `status` enum('sent','failed') DEFAULT 'sent',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `events`
--

CREATE TABLE IF NOT EXISTS `events` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `event_type` enum('Site Visit','Meeting','Deadline','Review','Training','Other') DEFAULT 'Other',
  `event_date` date NOT NULL,
  `event_time` time DEFAULT NULL,
  `company_id` int(11) DEFAULT NULL,
  `school_id` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `status` enum('upcoming','completed','cancelled') DEFAULT 'upcoming',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `impact_milestones`
--

CREATE TABLE IF NOT EXISTS `impact_milestones` (
  `id` int(11) NOT NULL,
  `partnership_id` int(11) NOT NULL,
  `school_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `target_value` int(11) DEFAULT 0,
  `achieved_value` int(11) DEFAULT 0,
  `milestone_type` enum('learners','educators','schools','funding','programmes','other') DEFAULT 'other',
  `status` enum('not_started','in_progress','achieved','exceeded') DEFAULT 'not_started',
  `due_date` date DEFAULT NULL,
  `achieved_date` date DEFAULT NULL,
  `quarter` enum('Q1','Q2','Q3','Q4') DEFAULT NULL,
  `year` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `impact_stats`
--

CREATE TABLE IF NOT EXISTS `impact_stats` (
  `id` int(11) NOT NULL,
  `partnership_id` int(11) NOT NULL,
  `report_date` date NOT NULL,
  `learners` int(11) DEFAULT 0,
  `educators` int(11) DEFAULT 0,
  `notes` text DEFAULT NULL,
  `recorded_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `quarter` enum('Q1','Q2','Q3','Q4') DEFAULT NULL,
  `year` int(11) DEFAULT NULL,
  `schools_reached` int(11) DEFAULT 0,
  `programmes_count` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `mous`
--

CREATE TABLE IF NOT EXISTS `mous` (
  `id` int(11) NOT NULL,
  `pledge_id` int(11) NOT NULL,
  `company_id` int(11) NOT NULL,
  `school_id` int(11) NOT NULL,
  `need_id` int(11) NOT NULL,
  `amount` decimal(15,2) DEFAULT NULL,
  `file_name` varchar(255) DEFAULT NULL,
  `status` enum('draft','signed','active','expired') DEFAULT 'draft',
  `generated_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `signed_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `need_pledges`
--

CREATE TABLE IF NOT EXISTS `need_pledges` (
  `id` int(11) NOT NULL,
  `need_id` int(11) NOT NULL,
  `company_id` int(11) NOT NULL,
  `amount` decimal(15,2) DEFAULT 0.00,
  `message` text DEFAULT NULL,
  `status` enum('pending','confirmed','declined') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `partnerships`
--

CREATE TABLE IF NOT EXISTS `partnerships` (
  `id` int(11) NOT NULL,
  `company_id` int(11) NOT NULL DEFAULT 1,
  `school_id` int(11) NOT NULL DEFAULT 1,
  `focus_area` varchar(100) DEFAULT NULL,
  `amount` decimal(15,2) DEFAULT 0.00,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `status` enum('active','pending','completed','paused') DEFAULT 'pending',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `name` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `password_resets`
--

CREATE TABLE IF NOT EXISTS `password_resets` (
  `id` int(11) NOT NULL,
  `username` varchar(100) NOT NULL,
  `token` varchar(64) NOT NULL,
  `expires_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `used` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `schools`
--

CREATE TABLE IF NOT EXISTS `schools` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `location` varchar(255) DEFAULT NULL,
  `province` varchar(100) DEFAULT NULL,
  `district` varchar(150) DEFAULT NULL,
  `school_type` varchar(50) DEFAULT 'Public',
  `status` enum('active','inactive','pending') DEFAULT 'active',
  `funding_requested` decimal(15,2) DEFAULT 0.00,
  `funding_granted` decimal(15,2) DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `learners` int(11) DEFAULT 0,
  `educators` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `schools`
--

INSERT INTO `schools` (`id`, `name`, `location`, `province`, `district`, `school_type`, `status`, `funding_requested`, `funding_granted`, `created_at`, `learners`, `educators`) VALUES
(1, 'Jeppe Boys High School', NULL, 'Gauteng', 'City of Johannesburg', 'Public', 'active', 200.00, 0.00, '2026-07-07 10:35:04', 0, 0);

-- --------------------------------------------------------

--
-- Table structure for table `school_needs`
--

CREATE TABLE IF NOT EXISTS `school_needs` (
  `id` int(11) NOT NULL,
  `school_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `focus_area` varchar(100) DEFAULT NULL,
  `amount_needed` decimal(15,2) DEFAULT 0.00,
  `amount_funded` decimal(15,2) DEFAULT 0.00,
  `status` enum('open','partially_funded','fully_funded','closed') DEFAULT 'open',
  `priority` enum('high','medium','low') DEFAULT 'medium',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `school_needs`
--

INSERT INTO `school_needs` (`id`, `school_id`, `title`, `description`, `focus_area`, `amount_needed`, `amount_funded`, `status`, `priority`, `created_at`) VALUES
(1, 1, 'Funding Request — Jeppe Boys High School', 'Water and electricity', NULL, 200.00, 0.00, 'open', 'high', '2026-07-07 10:35:04');

-- --------------------------------------------------------

--
-- Table structure for table `surveys`
--

CREATE TABLE IF NOT EXISTS `surveys` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `target_type` enum('all','companies','schools') DEFAULT 'all',
  `status` enum('draft','active','closed') DEFAULT 'draft',
  `created_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `survey_type` varchar(100) DEFAULT 'custom',
  `is_template` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `surveys`
--

INSERT INTO `surveys` (`id`, `title`, `description`, `target_type`, `status`, `created_by`, `created_at`, `survey_type`, `is_template`) VALUES
(10, 'School Needs Assessment', 'Identify the most urgent needs of schools in our CSI programme', 'schools', 'active', 'admin', '2026-07-08 15:06:01', 'needs_assessment', 1),
(11, 'School Environment Survey', 'Assess the learning environment and infrastructure of partner schools', 'schools', 'active', 'admin', '2026-07-08 15:06:01', 'environment', 1),
(12, 'Leadership & Management Survey', 'Evaluate school leadership effectiveness and management practices', 'schools', 'active', 'admin', '2026-07-08 15:06:01', 'leadership', 1),
(13, 'CSI Programme Impact Survey', 'Measure the impact of CSI programmes on learners and educators', 'all', 'active', 'admin', '2026-07-08 15:06:01', 'impact', 1),
(14, 'Community Needs Survey', 'Understand the broader community needs surrounding beneficiary schools', 'companies', 'active', 'admin', '2026-07-08 15:06:01', 'community', 1),
(15, 'Partner Satisfaction Survey', 'Gauge corporate partner satisfaction with Research Unlimited services', 'companies', 'active', 'admin', '2026-07-08 15:06:01', 'satisfaction', 1);

-- --------------------------------------------------------

--
-- Table structure for table `survey_questions`
--

CREATE TABLE IF NOT EXISTS `survey_questions` (
  `id` int(11) NOT NULL,
  `survey_id` int(11) NOT NULL,
  `question` text NOT NULL,
  `type` enum('text','rating','yesno','multiple') DEFAULT 'text',
  `options` text DEFAULT NULL,
  `sort_order` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `survey_questions`
--

INSERT INTO `survey_questions` (`id`, `survey_id`, `question`, `type`, `options`, `sort_order`) VALUES
(1, 10, 'What is the most urgent need at your school?', 'multiple', 'Computers & Technology,Science Laboratory,Library Books,Sports Equipment,Classroom Furniture,Sanitation Facilities,Security,Internet Access', 1),
(2, 10, 'How would you rate your current infrastructure?', 'rating', '', 2),
(3, 10, 'Does your school have access to reliable electricity?', 'yesno', '', 3),
(4, 10, 'How many learners lack basic learning materials?', 'text', '', 4),
(5, 10, 'What subject areas need the most support?', 'multiple', 'Mathematics,Science,English,Technology,Arts,Physical Education', 5),
(6, 11, 'How safe is the school environment for learners?', 'rating', '', 1),
(7, 11, 'Are classrooms adequately ventilated and lit?', 'yesno', '', 2),
(8, 11, 'Does the school have a functioning library?', 'yesno', '', 3),
(9, 11, 'How would you rate sanitation facilities?', 'rating', '', 4),
(10, 11, 'Are sports and recreational facilities available?', 'yesno', '', 5),
(11, 12, 'How would you rate school management effectiveness?', 'rating', '', 1),
(12, 12, 'Is there a clear strategic plan in place?', 'yesno', '', 2),
(13, 12, 'How often does the SGB meet?', 'multiple', 'Weekly,Monthly,Quarterly,Rarely', 3),
(14, 12, 'Are teachers supported in professional development?', 'rating', '', 4),
(15, 12, 'How transparent is financial management?', 'rating', '', 5),
(16, 13, 'How has the CSI programme improved learning outcomes?', 'rating', '', 1),
(17, 13, 'Have learner attendance rates improved?', 'yesno', '', 2),
(18, 13, 'What is the most valuable aspect of the programme?', 'text', '', 3),
(19, 13, 'Would you recommend this programme to other schools?', 'yesno', '', 4),
(20, 13, 'How would you rate Research Unlimited support?', 'rating', '', 5),
(21, 14, 'What are the top community challenges affecting learners?', 'multiple', 'Poverty,Unemployment,Crime,Lack of Transport,Drug Abuse,Teen Pregnancy', 1),
(22, 14, 'Is parental involvement in school activities adequate?', 'rating', '', 2),
(23, 14, 'What community support services are most needed?', 'text', '', 3),
(24, 14, 'Are there local businesses supporting the school?', 'yesno', '', 4),
(25, 14, 'How would you rate community safety?', 'rating', '', 5),
(26, 15, 'How satisfied are you with RU programme delivery?', 'rating', '', 1),
(27, 15, 'Are M&E reports delivered on time?', 'yesno', '', 2),
(28, 15, 'How would you rate communication from RU?', 'rating', '', 3),
(29, 15, 'Would you renew your CSI programme with RU?', 'yesno', '', 4),
(30, 15, 'What improvements would you suggest?', 'text', '', 5);

-- --------------------------------------------------------

--
-- Table structure for table `survey_responses`
--

CREATE TABLE IF NOT EXISTS `survey_responses` (
  `id` int(11) NOT NULL,
  `survey_id` int(11) NOT NULL,
  `question_id` int(11) NOT NULL,
  `respondent` varchar(100) DEFAULT NULL,
  `answer` text DEFAULT NULL,
  `submitted_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `survey_responses`
--

INSERT INTO `survey_responses` (`id`, `survey_id`, `question_id`, `respondent`, `answer`, `submitted_at`) VALUES
(1, 10, 1, 'Silindelo Ndlovu', 'Computers & Technology, Internet Access', '2026-07-08 20:02:05'),
(2, 10, 2, 'Silindelo Ndlovu', '4', '2026-07-08 20:02:05'),
(3, 10, 3, 'Silindelo Ndlovu', 'No', '2026-07-08 20:02:05'),
(4, 10, 4, 'Silindelo Ndlovu', '10', '2026-07-08 20:02:05'),
(5, 10, 5, 'Silindelo Ndlovu', 'Mathematics, Technology', '2026-07-08 20:02:05');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `companies`
--
ALTER TABLE `companies`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `documents`
--
ALTER TABLE `documents`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `email_log`
--
ALTER TABLE `email_log`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `events`
--
ALTER TABLE `events`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `impact_milestones`
--
ALTER TABLE `impact_milestones`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `impact_stats`
--
ALTER TABLE `impact_stats`
  ADD PRIMARY KEY (`id`),
  ADD KEY `partnership_id` (`partnership_id`);

--
-- Indexes for table `mous`
--
ALTER TABLE `mous`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `need_pledges`
--
ALTER TABLE `need_pledges`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `partnerships`
--
ALTER TABLE `partnerships`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `schools`
--
ALTER TABLE `schools`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `school_needs`
--
ALTER TABLE `school_needs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `surveys`
--
ALTER TABLE `surveys`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `survey_questions`
--
ALTER TABLE `survey_questions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `survey_id` (`survey_id`);

--
-- Indexes for table `survey_responses`
--
ALTER TABLE `survey_responses`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `companies`
--
ALTER TABLE `companies`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `documents`
--
ALTER TABLE `documents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `email_log`
--
ALTER TABLE `email_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `events`
--
ALTER TABLE `events`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `impact_milestones`
--
ALTER TABLE `impact_milestones`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `impact_stats`
--
ALTER TABLE `impact_stats`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `mous`
--
ALTER TABLE `mous`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `need_pledges`
--
ALTER TABLE `need_pledges`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `partnerships`
--
ALTER TABLE `partnerships`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `schools`
--
ALTER TABLE `schools`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `school_needs`
--
ALTER TABLE `school_needs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `surveys`
--
ALTER TABLE `surveys`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `survey_questions`
--
ALTER TABLE `survey_questions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT for table `survey_responses`
--
ALTER TABLE `survey_responses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `impact_stats`
--
ALTER TABLE `impact_stats`
  ADD CONSTRAINT `impact_stats_ibfk_1` FOREIGN KEY (`partnership_id`) REFERENCES `partnerships` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `survey_questions`
--
ALTER TABLE `survey_questions`
  ADD CONSTRAINT `survey_questions_ibfk_1` FOREIGN KEY (`survey_id`) REFERENCES `surveys` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
