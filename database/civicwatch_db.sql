-- ============================================================================
-- CivicWatch – Smart Community Issue Reporting and Tracking System
-- Complete MySQL schema and demonstration data
--
-- Import through phpMyAdmin or:
--   mysql -u root -p < database/civicwatch_db.sql
--
-- Default accounts:
--   Administrator : admin@civicwatch.local  / Admin@123
--   Citizen       : amina@example.com       / Citizen@123
--   Staff         : yonas.staff@civicwatch.local / Staff@123
--
-- IMPORTANT: Change the default administrator password after first login.
-- ============================================================================

CREATE DATABASE IF NOT EXISTS civicwatch_db
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE civicwatch_db;

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS announcements;
DROP TABLE IF EXISTS notifications;
DROP TABLE IF EXISTS report_updates;
DROP TABLE IF EXISTS report_assignments;
DROP TABLE IF EXISTS report_images;
DROP TABLE IF EXISTS reports;
DROP TABLE IF EXISTS categories;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS departments;

SET FOREIGN_KEY_CHECKS = 1;

-- ----------------------------------------------------------------------------
-- Departments
-- ----------------------------------------------------------------------------
CREATE TABLE departments (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(120) NOT NULL,
    slug VARCHAR(140) NOT NULL,
    description TEXT NULL,
    email VARCHAR(160) NULL,
    phone VARCHAR(30) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_departments_name (name),
    UNIQUE KEY uq_departments_slug (slug),
    KEY idx_departments_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Users (administrators, citizens and department staff)
-- ----------------------------------------------------------------------------
CREATE TABLE users (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    full_name VARCHAR(120) NOT NULL,
    email VARCHAR(160) NOT NULL,
    phone VARCHAR(30) NULL,
    address VARCHAR(255) NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin', 'citizen', 'staff') NOT NULL DEFAULT 'citizen',
    department_id INT UNSIGNED NULL,
    avatar VARCHAR(255) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    last_login DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_email (email),
    KEY idx_users_role (role),
    KEY idx_users_department (department_id),
    KEY idx_users_active (is_active),
    CONSTRAINT fk_users_department
        FOREIGN KEY (department_id) REFERENCES departments (id)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Issue categories
-- ----------------------------------------------------------------------------
CREATE TABLE categories (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(120) NOT NULL,
    slug VARCHAR(140) NOT NULL,
    description VARCHAR(255) NULL,
    icon VARCHAR(60) NOT NULL DEFAULT 'fa-circle-exclamation',
    color VARCHAR(20) NOT NULL DEFAULT '#0f9b8e',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_categories_name (name),
    UNIQUE KEY uq_categories_slug (slug),
    KEY idx_categories_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Community reports
-- ----------------------------------------------------------------------------
CREATE TABLE reports (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    reference_no VARCHAR(30) NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    category_id INT UNSIGNED NOT NULL,
    department_id INT UNSIGNED NULL,
    assigned_staff_id INT UNSIGNED NULL,
    title VARCHAR(180) NOT NULL,
    description TEXT NOT NULL,
    address VARCHAR(255) NOT NULL,
    latitude DECIMAL(10, 8) NOT NULL,
    longitude DECIMAL(11, 8) NOT NULL,
    priority ENUM('low', 'medium', 'high', 'critical') NOT NULL DEFAULT 'medium',
    status ENUM('pending', 'under_review', 'approved', 'assigned', 'in_progress', 'resolved', 'rejected') NOT NULL DEFAULT 'pending',
    admin_notes TEXT NULL,
    rejection_reason VARCHAR(500) NULL,
    resolved_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_reports_reference (reference_no),
    KEY idx_reports_user (user_id),
    KEY idx_reports_category (category_id),
    KEY idx_reports_department (department_id),
    KEY idx_reports_staff (assigned_staff_id),
    KEY idx_reports_status (status),
    KEY idx_reports_priority (priority),
    KEY idx_reports_created (created_at),
    KEY idx_reports_status_created (status, created_at),
    CONSTRAINT fk_reports_user
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_reports_category
        FOREIGN KEY (category_id) REFERENCES categories (id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_reports_department
        FOREIGN KEY (department_id) REFERENCES departments (id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_reports_staff
        FOREIGN KEY (assigned_staff_id) REFERENCES users (id)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Report images (citizen photos and department evidence)
-- ----------------------------------------------------------------------------
CREATE TABLE report_images (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    report_id INT UNSIGNED NOT NULL,
    uploaded_by INT UNSIGNED NULL,
    image_type ENUM('report', 'evidence') NOT NULL DEFAULT 'report',
    file_path VARCHAR(255) NOT NULL,
    original_name VARCHAR(180) NULL,
    mime_type VARCHAR(80) NULL,
    file_size INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_report_images_report (report_id),
    KEY idx_report_images_type (image_type),
    CONSTRAINT fk_report_images_report
        FOREIGN KEY (report_id) REFERENCES reports (id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_report_images_user
        FOREIGN KEY (uploaded_by) REFERENCES users (id)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Assignment history
-- ----------------------------------------------------------------------------
CREATE TABLE report_assignments (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    report_id INT UNSIGNED NOT NULL,
    department_id INT UNSIGNED NOT NULL,
    assigned_by INT UNSIGNED NULL,
    assigned_to INT UNSIGNED NULL,
    note VARCHAR(500) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_assignments_report (report_id),
    KEY idx_assignments_department (department_id),
    KEY idx_assignments_staff (assigned_to),
    CONSTRAINT fk_assignments_report
        FOREIGN KEY (report_id) REFERENCES reports (id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_assignments_department
        FOREIGN KEY (department_id) REFERENCES departments (id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_assignments_admin
        FOREIGN KEY (assigned_by) REFERENCES users (id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_assignments_staff
        FOREIGN KEY (assigned_to) REFERENCES users (id)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Report progress / status history
-- ----------------------------------------------------------------------------
CREATE TABLE report_updates (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    report_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NULL,
    status VARCHAR(30) NULL,
    note TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_updates_report (report_id),
    KEY idx_updates_created (created_at),
    CONSTRAINT fk_updates_report
        FOREIGN KEY (report_id) REFERENCES reports (id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_updates_user
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Notifications
-- ----------------------------------------------------------------------------
CREATE TABLE notifications (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    report_id INT UNSIGNED NULL,
    title VARCHAR(160) NOT NULL,
    message TEXT NOT NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_notifications_user (user_id, is_read),
    KEY idx_notifications_created (created_at),
    CONSTRAINT fk_notifications_user
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_notifications_report
        FOREIGN KEY (report_id) REFERENCES reports (id)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Admin announcements
-- ----------------------------------------------------------------------------
CREATE TABLE announcements (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    title VARCHAR(180) NOT NULL,
    content TEXT NOT NULL,
    audience ENUM('all', 'citizens', 'staff') NOT NULL DEFAULT 'all',
    published_by INT UNSIGNED NULL,
    is_published TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_announcements_published (is_published, created_at),
    KEY idx_announcements_audience (audience),
    CONSTRAINT fk_announcements_user
        FOREIGN KEY (published_by) REFERENCES users (id)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- Demonstration data
-- ============================================================================

INSERT INTO departments (id, name, slug, description, email, phone, is_active) VALUES
(1, 'Public Works', 'public-works', 'Roads, pavements, bridges and public infrastructure maintenance.', 'publicworks@civicwatch.local', '+1 (555) 014-2001', 1),
(2, 'Water & Sanitation', 'water-sanitation', 'Water supply, pipe repairs, leaks and sanitation services.', 'water@civicwatch.local', '+1 (555) 014-2002', 1),
(3, 'Power & Lighting', 'power-lighting', 'Electricity distribution, streetlights and public lighting.', 'power@civicwatch.local', '+1 (555) 014-2003', 1),
(4, 'Waste Management', 'waste-management', 'Household waste collection, public bins and illegal dumping.', 'waste@civicwatch.local', '+1 (555) 014-2004', 1),
(5, 'Drainage & Environment', 'drainage-environment', 'Storm drains, flooding, watercourses and environmental health.', 'drainage@civicwatch.local', '+1 (555) 014-2005', 1);

INSERT INTO users (id, full_name, email, phone, address, password_hash, role, department_id, is_active, last_login, created_at, updated_at) VALUES
(1, 'CivicWatch Administrator', 'admin@civicwatch.local', '+251914976416', 'Oda Bultum University, Chiro, Oromia, Ethiopia', '$2y$10$TXmwqoSY5DUJkpChp4tERO52TuqS8wmTXi6FXDAioxqxKSF2kUiia', 'admin', NULL, 1, NULL, DATE_SUB(NOW(), INTERVAL 30 DAY), NOW()),
(2, 'Amina Bekele', 'amina@example.com', '+1 (555) 010-1188', '18 Jacaranda Street, Greenfield City', '$2y$10$VBqxg1Xl5NtAMdls3cKbweynb.RO0eg9QK19OnCxiRngzj7krS87e', 'citizen', NULL, 1, NULL, DATE_SUB(NOW(), INTERVAL 24 DAY), NOW()),
(3, 'Daniel Mekonnen', 'daniel@example.com', '+1 (555) 010-2244', '42 Acacia Avenue, Greenfield City', '$2y$10$VBqxg1Xl5NtAMdls3cKbweynb.RO0eg9QK19OnCxiRngzj7krS87e', 'citizen', NULL, 1, NULL, DATE_SUB(NOW(), INTERVAL 20 DAY), NOW()),
(4, 'Sara Tesfaye', 'sara@example.com', '+1 (555) 010-3399', '7 Cedar Lane, Greenfield City', '$2y$10$VBqxg1Xl5NtAMdls3cKbweynb.RO0eg9QK19OnCxiRngzj7krS87e', 'citizen', NULL, 1, NULL, DATE_SUB(NOW(), INTERVAL 16 DAY), NOW()),
(5, 'Yonas Alemu', 'yonas.staff@civicwatch.local', '+1 (555) 014-2101', NULL, '$2y$10$6AypPrYEECTaY38pOrunk.5BAjxuj8ypMl8b3OxiMDnGJRY/yg7CW', 'staff', 1, 1, NULL, DATE_SUB(NOW(), INTERVAL 28 DAY), NOW()),
(6, 'Meron Haile', 'meron.staff@civicwatch.local', '+1 (555) 014-2102', NULL, '$2y$10$6AypPrYEECTaY38pOrunk.5BAjxuj8ypMl8b3OxiMDnGJRY/yg7CW', 'staff', 2, 1, NULL, DATE_SUB(NOW(), INTERVAL 27 DAY), NOW()),
(7, 'Samuel Girma', 'samuel.staff@civicwatch.local', '+1 (555) 014-2103', NULL, '$2y$10$6AypPrYEECTaY38pOrunk.5BAjxuj8ypMl8b3OxiMDnGJRY/yg7CW', 'staff', 3, 1, NULL, DATE_SUB(NOW(), INTERVAL 26 DAY), NOW()),
(8, 'Hana Bekele', 'hana.staff@civicwatch.local', '+1 (555) 014-2104', NULL, '$2y$10$6AypPrYEECTaY38pOrunk.5BAjxuj8ypMl8b3OxiMDnGJRY/yg7CW', 'staff', 4, 1, NULL, DATE_SUB(NOW(), INTERVAL 25 DAY), NOW()),
(9, 'Dawit Tadesse', 'dawit.staff@civicwatch.local', '+1 (555) 014-2105', NULL, '$2y$10$6AypPrYEECTaY38pOrunk.5BAjxuj8ypMl8b3OxiMDnGJRY/yg7CW', 'staff', 5, 1, NULL, DATE_SUB(NOW(), INTERVAL 24 DAY), NOW()),
(10, 'Liya Abebe', 'liya@example.com', '+1 (555) 010-4455', '90 Olive Grove, Greenfield City', '$2y$10$VBqxg1Xl5NtAMdls3cKbweynb.RO0eg9QK19OnCxiRngzj7krS87e', 'citizen', NULL, 1, NULL, DATE_SUB(NOW(), INTERVAL 12 DAY), NOW());

INSERT INTO categories (id, name, slug, description, icon, color, is_active) VALUES
(1, 'Road Damage', 'road-damage', 'Potholes, broken pavements, damaged road surfaces and road hazards.', 'fa-road', '#d97706', 1),
(2, 'Water Supply Problems', 'water-supply-problems', 'Leaks, burst pipes, low pressure, unsafe water and supply interruptions.', 'fa-droplet', '#2a76d2', 1),
(3, 'Electricity Problems', 'electricity-problems', 'Power outages, exposed wiring, damaged poles and electrical hazards.', 'fa-bolt', '#e0a02a', 1),
(4, 'Waste Management', 'waste-management', 'Missed collections, overflowing bins and illegal dumping.', 'fa-trash-can', '#16a34a', 1),
(5, 'Broken Streetlights', 'broken-streetlights', 'Streetlights that are out, flickering or physically damaged.', 'fa-lightbulb', '#8b5cf6', 1),
(6, 'Drainage Problems', 'drainage-problems', 'Blocked drains, flooding, standing water and damaged culverts.', 'fa-water', '#0ea5e9', 1),
(7, 'Public Infrastructure', 'public-infrastructure', 'Parks, benches, public buildings, signage and community facilities.', 'fa-building-columns', '#64748b', 1),
(8, 'Other Community Issues', 'other-community-issues', 'Anything else that affects the safety or quality of community life.', 'fa-circle-question', '#db2777', 1);

INSERT INTO reports
(id, reference_no, user_id, category_id, department_id, assigned_staff_id, title, description, address, latitude, longitude, priority, status, admin_notes, rejection_reason, resolved_at, created_at, updated_at)
VALUES
(1, 'CW-2026-A1B2C3', 2, 1, 1, 5,
 'Large pothole near Medhanialem junction',
 'A deep pothole has opened in the eastbound lane just before the junction. It fills with water after rain and forces cars into the opposite lane. Two cyclists have already fallen here this week.',
 'Bole Road, near Medhanialem Church, Greenfield City',
 9.01250000, 38.76200000, 'high', 'in_progress',
 'High-traffic location. Temporary warning cone requested while repairs are scheduled.', NULL, NULL,
 DATE_SUB(NOW(), INTERVAL 5 DAY), DATE_SUB(NOW(), INTERVAL 1 DAY)),
(2, 'CW-2026-D4E5F6', 3, 5, 3, 7,
 'Streetlight out for three nights',
 'The streetlight directly outside Sunrise School has been out since Saturday. The area is very dark by 6:30 PM and children cross the road here after evening classes.',
 'Kirkos Sub-city, Woreda 08, near Sunrise School, Greenfield City',
 9.02180000, 38.74630000, 'medium', 'assigned',
 'School zone. Please prioritise inspection.', NULL, NULL,
 DATE_SUB(NOW(), INTERVAL 3 DAY), DATE_SUB(NOW(), INTERVAL 2 DAY)),
(3, 'CW-2026-G7H8I9', 2, 2, 2, 6,
 'Water pipe leaking into the street',
 'A water pipe is leaking continuously near the corner shop. Water is running across the pavement and the road surface is beginning to erode. The shop has lost access to its side entrance.',
 'Meskel Flower Road, beside Alem Market, Greenfield City',
 9.03520000, 38.73810000, 'high', 'resolved',
 'Repair completed and water service restored.', NULL,
 DATE_SUB(NOW(), INTERVAL 2 DAY),
 DATE_SUB(NOW(), INTERVAL 12 DAY), DATE_SUB(NOW(), INTERVAL 2 DAY)),
(4, 'CW-2026-J1K2L3', 4, 4, NULL, NULL,
 'Illegal dumping beside the market',
 'Construction waste and household rubbish have been dumped on the empty plot beside the vegetable market. The pile is attracting rodents and blocking the pedestrian path.',
 'Greenfield Central Market, east entrance, Greenfield City',
 9.02770000, 38.75120000, 'medium', 'under_review',
 NULL, NULL, NULL,
 DATE_SUB(NOW(), INTERVAL 1 DAY), DATE_SUB(NOW(), INTERVAL 20 HOUR)),
(5, 'CW-2026-M4N5O6', 3, 6, NULL, NULL,
 'Blocked storm drain ahead of rainy season',
 'The storm drain on the corner is completely blocked with silt and plastic waste. Water already pools 20 cm deep after a short shower and threatens two ground-floor homes.',
 'Atlas Road, Woreda 04, Greenfield City',
 9.04400000, 38.72980000, 'high', 'approved',
 'Approved for drainage crew inspection and clearance.', NULL, NULL,
 DATE_SUB(NOW(), INTERVAL 2 DAY), DATE_SUB(NOW(), INTERVAL 16 HOUR)),
(6, 'CW-2026-P7Q8R9', 10, 3, NULL, NULL,
 'Frequent power outages in Block C',
 'Our block has experienced five unplanned outages in the last four days. Each outage lasts between 40 minutes and three hours. Several households have lost food and one medical device has been affected.',
 'Block C, Harmony Residences, Greenfield City',
 9.01890000, 38.77040000, 'critical', 'pending',
 NULL, NULL, NULL,
 DATE_SUB(NOW(), INTERVAL 4 HOUR), DATE_SUB(NOW(), INTERVAL 4 HOUR)),
(7, 'CW-2026-S1T2U3', 4, 1, NULL, NULL,
 'Pothole on Bole Road',
 'Reporting a pothole on Bole Road near the junction.',
 'Bole Road, Greenfield City',
 9.01300000, 38.76250000, 'low', 'rejected',
 NULL,
 'This is a duplicate of report CW-2026-A1B2C3, which is already being repaired.',
 NULL,
 DATE_SUB(NOW(), INTERVAL 4 DAY), DATE_SUB(NOW(), INTERVAL 4 DAY)),
(8, 'CW-2026-V4W5X6', 2, 7, 1, 5,
 'Broken public bench in community park',
 'The wooden bench near the playground has a broken leg and a loose backrest. It tips forward when someone sits on it, which is dangerous for older residents and children.',
 'Greenfield Community Park, north playground, Greenfield City',
 9.03830000, 38.75650000, 'low', 'resolved',
 'Replacement timber fitted and bench re-anchored.', NULL,
 DATE_SUB(NOW(), INTERVAL 10 DAY),
 DATE_SUB(NOW(), INTERVAL 20 DAY), DATE_SUB(NOW(), INTERVAL 10 DAY)),
(9, 'CW-2026-Y7Z8A9', 10, 4, 4, 8,
 'Overflowing skip near community clinic',
 'The skip outside the clinic has not been emptied for over a week. Waste is now spilling onto the road and the smell is noticeable inside the clinic waiting area.',
 'Greenfield Community Clinic, service lane, Greenfield City',
 9.03210000, 38.76480000, 'high', 'in_progress',
 'Collection vehicle scheduled. Clinic has been informed.', NULL, NULL,
 DATE_SUB(NOW(), INTERVAL 6 DAY), DATE_SUB(NOW(), INTERVAL 10 HOUR));

INSERT INTO report_images (report_id, uploaded_by, image_type, file_path, original_name, mime_type, file_size, created_at) VALUES
(1, 2, 'report', 'uploads/reports/sample-pothole.jpg', 'pothole-bole-road.jpg', 'image/jpeg', 120000, DATE_SUB(NOW(), INTERVAL 5 DAY)),
(2, 3, 'report', 'uploads/reports/sample-streetlight.jpg', 'streetlight-at-night.jpg', 'image/jpeg', 120000, DATE_SUB(NOW(), INTERVAL 3 DAY)),
(3, 2, 'report', 'uploads/reports/sample-water-leak.jpg', 'water-pipe-leak.jpg', 'image/jpeg', 120000, DATE_SUB(NOW(), INTERVAL 12 DAY)),
(3, 6, 'evidence', 'uploads/evidence/sample-water-fixed.jpg', 'pipe-repair-complete.jpg', 'image/jpeg', 120000, DATE_SUB(NOW(), INTERVAL 2 DAY)),
(8, 2, 'report', 'uploads/reports/sample-bench.jpg', 'broken-park-bench.jpg', 'image/jpeg', 120000, DATE_SUB(NOW(), INTERVAL 20 DAY)),
(8, 5, 'evidence', 'uploads/evidence/sample-bench-fixed.jpg', 'bench-repaired.jpg', 'image/jpeg', 120000, DATE_SUB(NOW(), INTERVAL 10 DAY)),
(9, 10, 'report', 'uploads/reports/sample-waste.jpg', 'overflowing-skip.jpg', 'image/jpeg', 120000, DATE_SUB(NOW(), INTERVAL 6 DAY));

INSERT INTO report_assignments (report_id, department_id, assigned_by, assigned_to, note, created_at) VALUES
(1, 1, 1, 5, 'Please inspect and arrange an urgent road repair. High pedestrian traffic.', DATE_SUB(NOW(), INTERVAL 4 DAY)),
(2, 3, 1, 7, 'School zone — inspect the circuit and lamp head before Friday.', DATE_SUB(NOW(), INTERVAL 2 DAY)),
(3, 2, 1, 6, 'Leak is eroding the road edge. Please isolate and repair.', DATE_SUB(NOW(), INTERVAL 11 DAY)),
(8, 1, 1, 5, 'Bench repairs can be scheduled with the parks maintenance round.', DATE_SUB(NOW(), INTERVAL 18 DAY)),
(9, 4, 1, 8, 'Clinic reported odour. Please prioritise the next collection round.', DATE_SUB(NOW(), INTERVAL 5 DAY));

INSERT INTO report_updates (report_id, user_id, status, note, created_at) VALUES
(1, 2, 'pending', 'Report submitted with photo and map location.', DATE_SUB(NOW(), INTERVAL 5 DAY)),
(1, 1, 'under_review', 'Report received and being reviewed by the civic office.', DATE_SUB(NOW(), INTERVAL 5 DAY)),
(1, 1, 'approved', 'Report verified. Approved for assignment to Public Works.', DATE_SUB(NOW(), INTERVAL 4 DAY)),
(1, 1, 'assigned', 'Assigned to Yonas Alemu at Public Works.', DATE_SUB(NOW(), INTERVAL 4 DAY)),
(1, 5, 'in_progress', 'Site inspected. A temporary warning cone has been placed and a hot-mix repair is scheduled.', DATE_SUB(NOW(), INTERVAL 1 DAY)),

(2, 3, 'pending', 'Report submitted with photo and map location.', DATE_SUB(NOW(), INTERVAL 3 DAY)),
(2, 1, 'under_review', 'Report received and being reviewed by the civic office.', DATE_SUB(NOW(), INTERVAL 3 DAY)),
(2, 1, 'approved', 'Report verified. Approved for assignment to Power & Lighting.', DATE_SUB(NOW(), INTERVAL 2 DAY)),
(2, 1, 'assigned', 'Assigned to Samuel Girma at Power & Lighting.', DATE_SUB(NOW(), INTERVAL 2 DAY)),

(3, 2, 'pending', 'Report submitted with photo and map location.', DATE_SUB(NOW(), INTERVAL 12 DAY)),
(3, 1, 'under_review', 'Report received and being reviewed by the civic office.', DATE_SUB(NOW(), INTERVAL 12 DAY)),
(3, 1, 'approved', 'Report verified. Approved for assignment to Water & Sanitation.', DATE_SUB(NOW(), INTERVAL 11 DAY)),
(3, 1, 'assigned', 'Assigned to Meron Haile at Water & Sanitation.', DATE_SUB(NOW(), INTERVAL 11 DAY)),
(3, 6, 'in_progress', 'Leak isolated. Excavation started to reach the damaged pipe section.', DATE_SUB(NOW(), INTERVAL 4 DAY)),
(3, 6, 'resolved', 'Damaged pipe section replaced, leak stopped and the pavement reinstated. Water supply restored to the market.', DATE_SUB(NOW(), INTERVAL 2 DAY)),

(4, 4, 'pending', 'Report submitted with photo and map location.', DATE_SUB(NOW(), INTERVAL 1 DAY)),
(4, 1, 'under_review', 'Report received and being reviewed by the civic office.', DATE_SUB(NOW(), INTERVAL 20 HOUR)),

(5, 3, 'pending', 'Report submitted with photo and map location.', DATE_SUB(NOW(), INTERVAL 2 DAY)),
(5, 1, 'under_review', 'Report received and being reviewed by the civic office.', DATE_SUB(NOW(), INTERVAL 2 DAY)),
(5, 1, 'approved', 'Report verified. Approved for drainage crew inspection.', DATE_SUB(NOW(), INTERVAL 16 HOUR)),

(6, 10, 'pending', 'Report submitted with map location.', DATE_SUB(NOW(), INTERVAL 4 HOUR)),

(7, 4, 'pending', 'Report submitted with map location.', DATE_SUB(NOW(), INTERVAL 4 DAY)),
(7, 1, 'rejected', 'Report closed as a duplicate of CW-2026-A1B2C3.', DATE_SUB(NOW(), INTERVAL 4 DAY)),

(8, 2, 'pending', 'Report submitted with photo and map location.', DATE_SUB(NOW(), INTERVAL 20 DAY)),
(8, 1, 'under_review', 'Report received and being reviewed by the civic office.', DATE_SUB(NOW(), INTERVAL 19 DAY)),
(8, 1, 'approved', 'Report verified. Approved for assignment to Public Works.', DATE_SUB(NOW(), INTERVAL 18 DAY)),
(8, 1, 'assigned', 'Assigned to Yonas Alemu at Public Works.', DATE_SUB(NOW(), INTERVAL 18 DAY)),
(8, 5, 'in_progress', 'Bench removed from its anchors and replacement timber prepared.', DATE_SUB(NOW(), INTERVAL 14 DAY)),
(8, 5, 'resolved', 'Replacement timber fitted, backrest secured and bench re-anchored. Area cleaned.', DATE_SUB(NOW(), INTERVAL 10 DAY)),

(9, 10, 'pending', 'Report submitted with photo and map location.', DATE_SUB(NOW(), INTERVAL 6 DAY)),
(9, 1, 'under_review', 'Report received and being reviewed by the civic office.', DATE_SUB(NOW(), INTERVAL 6 DAY)),
(9, 1, 'approved', 'Report verified. Approved for assignment to Waste Management.', DATE_SUB(NOW(), INTERVAL 5 DAY)),
(9, 1, 'assigned', 'Assigned to Hana Bekele at Waste Management.', DATE_SUB(NOW(), INTERVAL 5 DAY)),
(9, 8, 'in_progress', 'Clinic contacted. Additional skip requested and next collection round prioritised.', DATE_SUB(NOW(), INTERVAL 10 HOUR));

INSERT INTO notifications (user_id, report_id, title, message, is_read, created_at) VALUES
(2, NULL, 'Welcome to CivicWatch', 'Your account is ready. Report community issues and follow their progress from your dashboard.', 1, DATE_SUB(NOW(), INTERVAL 24 DAY)),
(2, 1, 'Report received', 'Your report CW-2026-A1B2C3 has been received and is now pending review.', 1, DATE_SUB(NOW(), INTERVAL 5 DAY)),
(2, 1, 'Report approved', 'CW-2026-A1B2C3 has been approved and is being routed to the responsible department.', 1, DATE_SUB(NOW(), INTERVAL 4 DAY)),
(2, 1, 'Report assigned', 'CW-2026-A1B2C3 has been assigned to Public Works.', 0, DATE_SUB(NOW(), INTERVAL 4 DAY)),
(2, 1, 'Work has started', 'Public Works has started work on CW-2026-A1B2C3. A temporary warning cone has been placed.', 0, DATE_SUB(NOW(), INTERVAL 1 DAY)),
(2, 3, 'Report resolved', 'Good news — CW-2026-G7H8I9 has been marked as resolved by Water & Sanitation.', 0, DATE_SUB(NOW(), INTERVAL 2 DAY)),
(3, 2, 'Report assigned', 'CW-2026-D4E5F6 has been assigned to Power & Lighting.', 0, DATE_SUB(NOW(), INTERVAL 2 DAY)),
(5, 1, 'New report assigned', 'Report CW-2026-A1B2C3 has been assigned to you by the administrator.', 0, DATE_SUB(NOW(), INTERVAL 4 DAY)),
(7, 2, 'New report assigned', 'Report CW-2026-D4E5F6 has been assigned to you by the administrator.', 0, DATE_SUB(NOW(), INTERVAL 2 DAY)),
(8, 9, 'New report assigned', 'Report CW-2026-Y7Z8A9 has been assigned to you by the administrator.', 0, DATE_SUB(NOW(), INTERVAL 5 DAY)),
(10, 6, 'Report received', 'Your report CW-2026-P7Q8R9 has been received and is now pending review.', 0, DATE_SUB(NOW(), INTERVAL 4 HOUR)),
(1, 6, 'New critical report', 'A new critical priority report, CW-2026-P7Q8R9, requires review.', 0, DATE_SUB(NOW(), INTERVAL 4 HOUR));

INSERT INTO announcements (title, content, audience, published_by, is_published, created_at, updated_at) VALUES
('New online reporting service now live',
 'CivicWatch is now available to all residents of Greenfield City. You can report road damage, water leaks, streetlight faults, waste issues and more from any device. Every report receives a tracking number so you can follow progress from review to resolution.',
 'all', 1, 1, DATE_SUB(NOW(), INTERVAL 14 DAY), DATE_SUB(NOW(), INTERVAL 14 DAY)),
('Waste collection schedule update',
 'Waste collection in Woredas 04, 08 and 12 will move to a 6:00 AM start next week to avoid peak traffic. Please place bins outside the evening before your scheduled collection. Missed collections can be reported through CivicWatch under Waste Management.',
 'citizens', 1, 1, DATE_SUB(NOW(), INTERVAL 7 DAY), DATE_SUB(NOW(), INTERVAL 7 DAY)),
('Staff reminder: add clear progress notes',
 'When updating a report, please include a short, resident-friendly note describing what was inspected, what work was completed and what happens next. Attach completion evidence before marking a report as resolved.',
 'staff', 1, 1, DATE_SUB(NOW(), INTERVAL 3 DAY), DATE_SUB(NOW(), INTERVAL 3 DAY));

-- ============================================================================
-- End of file
-- ============================================================================
