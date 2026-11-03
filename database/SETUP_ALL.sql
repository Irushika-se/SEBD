-- THE ONLY SQL FILE YOU NEED: one import = tables + upgrades + sample data (logins in README).
-- ONE-IMPORT FRESH INSTALL ONLY. Select/create database `sebd` in phpMyAdmin first.
-- WARNING: base dump contains DROP TABLE and replaces existing tables/data.
-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Oct 06, 2026 at 03:09 PM
-- Server version: 8.4.7
-- PHP Version: 8.3.28

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `sebd`
--

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

DROP TABLE IF EXISTS `audit_logs`;
CREATE TABLE IF NOT EXISTS `audit_logs` (
  `audit_id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `action` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `table_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `record_id` int DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`audit_id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `audit_logs`
--

INSERT INTO `audit_logs` (`audit_id`, `user_id`, `action`, `table_name`, `record_id`, `description`, `ip_address`, `created_at`) VALUES
(1, 1, 'CREATE', 'emergency_requests', 1, 'Emergency blood request created.', '127.0.0.1', '2026-10-06 20:12:34'),
(2, 2, 'UPDATE', 'blood_inventory', 2, 'Blood inventory updated after emergency use.', '127.0.0.1', '2026-10-06 20:12:34'),
(3, 4, 'VIEW', 'donor_matches', 1, 'Donor viewed emergency matching request.', '127.0.0.1', '2026-10-06 20:12:34');

-- --------------------------------------------------------

--
-- Table structure for table `blood_inventory`
--

DROP TABLE IF EXISTS `blood_inventory`;
CREATE TABLE IF NOT EXISTS `blood_inventory` (
  `inventory_id` int NOT NULL AUTO_INCREMENT,
  `organization_id` int NOT NULL,
  `blood_group` enum('A+','A-','B+','B-','AB+','AB-','O+','O-') COLLATE utf8mb4_unicode_ci NOT NULL,
  `units_available` int NOT NULL DEFAULT '0',
  `minimum_stock` int NOT NULL DEFAULT '0',
  `last_updated` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`inventory_id`),
  UNIQUE KEY `organization_id` (`organization_id`,`blood_group`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `blood_inventory`
--

INSERT INTO `blood_inventory` (`inventory_id`, `organization_id`, `blood_group`, `units_available`, `minimum_stock`, `last_updated`) VALUES
(1, 1, 'A+', 5, 3, '2026-10-06 20:12:34'),
(2, 1, 'O+', 2, 5, '2026-10-06 20:12:34'),
(3, 1, 'B+', 4, 3, '2026-10-06 20:12:34'),
(4, 1, 'AB+', 3, 2, '2026-10-06 20:12:34'),
(5, 2, 'A+', 6, 3, '2026-10-06 20:12:34'),
(6, 2, 'O+', 4, 3, '2026-10-06 20:12:34'),
(7, 2, 'B+', 2, 3, '2026-10-06 20:12:34');

-- --------------------------------------------------------

--
-- Table structure for table `campaign_target_blood_groups`
--

DROP TABLE IF EXISTS `campaign_target_blood_groups`;
CREATE TABLE IF NOT EXISTS `campaign_target_blood_groups` (
  `campaign_target_id` int NOT NULL AUTO_INCREMENT,
  `campaign_id` int NOT NULL,
  `blood_group` enum('A+','A-','B+','B-','AB+','AB-','O+','O-') COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`campaign_target_id`),
  UNIQUE KEY `campaign_id` (`campaign_id`,`blood_group`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `campaign_target_blood_groups`
--

INSERT INTO `campaign_target_blood_groups` (`campaign_target_id`, `campaign_id`, `blood_group`) VALUES
(2, 1, 'A+'),
(1, 1, 'O+'),
(3, 2, 'B+');

-- --------------------------------------------------------

--
-- Table structure for table `donations`
--

DROP TABLE IF EXISTS `donations`;
CREATE TABLE IF NOT EXISTS `donations` (
  `donation_id` int NOT NULL AUTO_INCREMENT,
  `donor_id` int NOT NULL,
  `organization_id` int NOT NULL,
  `campaign_id` int DEFAULT NULL,
  `match_id` int DEFAULT NULL,
  `donation_type` enum('VOLUNTARY','CAMPAIGN','WALK_IN','EMERGENCY') COLLATE utf8mb4_unicode_ci NOT NULL,
  `blood_group` enum('A+','A-','B+','B-','AB+','AB-','O+','O-') COLLATE utf8mb4_unicode_ci NOT NULL,
  `units_donated` int NOT NULL DEFAULT '1',
  `donation_date` datetime NOT NULL,
  `status` enum('COMPLETED','CANCELLED') COLLATE utf8mb4_unicode_ci DEFAULT 'COMPLETED',
  `notes` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`donation_id`),
  KEY `donor_id` (`donor_id`),
  KEY `organization_id` (`organization_id`),
  KEY `campaign_id` (`campaign_id`),
  KEY `match_id` (`match_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `donations`
--

INSERT INTO `donations` (`donation_id`, `donor_id`, `organization_id`, `campaign_id`, `match_id`, `donation_type`, `blood_group`, `units_donated`, `donation_date`, `status`, `notes`) VALUES
(1, 1, 1, NULL, NULL, 'VOLUNTARY', 'O+', 1, '2026-06-10 10:00:00', 'COMPLETED', 'Voluntary blood donation.'),
(2, 2, 2, 2, NULL, 'CAMPAIGN', 'A+', 1, '2026-07-15 11:00:00', 'COMPLETED', 'Blood donated during campaign.');

-- --------------------------------------------------------

--
-- Table structure for table `donation_campaigns`
--

DROP TABLE IF EXISTS `donation_campaigns`;
CREATE TABLE IF NOT EXISTS `donation_campaigns` (
  `campaign_id` int NOT NULL AUTO_INCREMENT,
  `organization_id` int NOT NULL,
  `campaign_name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `location` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `campaign_date` date NOT NULL,
  `start_time` time NOT NULL DEFAULT '09:00:00',
  `end_time` time NOT NULL DEFAULT '15:00:00',
  `max_donors` int DEFAULT NULL,
  `session_type` enum('CAMPAIGN','WALK_IN_SESSION') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'CAMPAIGN',
  `status` enum('UPCOMING','ACTIVE','COMPLETED','CANCELLED') COLLATE utf8mb4_unicode_ci DEFAULT 'UPCOMING',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`campaign_id`),
  KEY `organization_id` (`organization_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `donation_campaigns`
--

INSERT INTO `donation_campaigns` (`campaign_id`, `organization_id`, `campaign_name`, `description`, `location`, `campaign_date`, `status`, `created_at`) VALUES
(1, 1, 'Colombo Blood Donation Camp', 'Community blood donation campaign.', 'Colombo Town Hall', '2026-10-15', 'UPCOMING', '2026-10-06 20:12:34'),
(2, 2, 'Kandy Blood Donation Camp', 'Emergency blood donation campaign.', 'Kandy City Centre', '2026-10-20', 'UPCOMING', '2026-10-06 20:12:34');

-- --------------------------------------------------------

--
-- Table structure for table `donation_history`
--

DROP TABLE IF EXISTS `donation_history`;
CREATE TABLE IF NOT EXISTS `donation_history` (
  `history_id` int NOT NULL AUTO_INCREMENT,
  `donor_id` int NOT NULL,
  `donation_id` int NOT NULL,
  `donation_date` datetime NOT NULL,
  `organization_id` int NOT NULL,
  `blood_group` enum('A+','A-','B+','B-','AB+','AB-','O+','O-') COLLATE utf8mb4_unicode_ci NOT NULL,
  `units_donated` int NOT NULL,
  PRIMARY KEY (`history_id`),
  KEY `donor_id` (`donor_id`),
  KEY `donation_id` (`donation_id`),
  KEY `organization_id` (`organization_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `donation_history`
--

INSERT INTO `donation_history` (`history_id`, `donor_id`, `donation_id`, `donation_date`, `organization_id`, `blood_group`, `units_donated`) VALUES
(1, 1, 1, '2026-06-10 10:00:00', 1, 'O+', 1),
(2, 2, 2, '2026-07-15 11:00:00', 2, 'A+', 1);

-- --------------------------------------------------------

--
-- Table structure for table `donors`
--

DROP TABLE IF EXISTS `donors`;
CREATE TABLE IF NOT EXISTS `donors` (
  `donor_id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `blood_group` enum('A+','A-','B+','B-','AB+','AB-','O+','O-') COLLATE utf8mb4_unicode_ci NOT NULL,
  `date_of_birth` date NOT NULL,
  `weight_kg` decimal(5,2) DEFAULT NULL,
  `gender` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `city` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `latitude` decimal(10,7) DEFAULT NULL,
  `longitude` decimal(10,7) DEFAULT NULL,
  `last_donation_date` date DEFAULT NULL,
  `recent_illness` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `medication` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `preferred_organization_id` int DEFAULT NULL,
  `rejection_reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `re_review_requested` tinyint(1) NOT NULL DEFAULT '0',
  `health_status` enum('PENDING','CLEARED') COLLATE utf8mb4_unicode_ci DEFAULT 'PENDING',
  `verification_status` enum('PENDING','VERIFIED','REJECTED') COLLATE utf8mb4_unicode_ci DEFAULT 'PENDING',
  `availability_status` enum('AVAILABLE','UNAVAILABLE') COLLATE utf8mb4_unicode_ci DEFAULT 'UNAVAILABLE',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`donor_id`),
  UNIQUE KEY `user_id` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `donors`
--

INSERT INTO `donors` (`donor_id`, `user_id`, `blood_group`, `date_of_birth`, `weight_kg`, `gender`, `address`, `city`, `latitude`, `longitude`, `last_donation_date`, `recent_illness`, `medication`, `health_status`, `verification_status`, `availability_status`, `created_at`, `updated_at`) VALUES
(1, 4, 'O+', '2002-05-15', 65.00, 'Male', 'Colombo 05', 'Colombo', 6.9271000, 79.8612000, '2026-06-10', NULL, NULL, 'CLEARED', 'VERIFIED', 'AVAILABLE', '2026-10-06 20:12:34', '2026-10-06 20:12:34'),
(2, 5, 'A+', '2001-08-20', 60.00, 'Male', 'Kandy', 'Kandy', 7.2906000, 80.6337000, '2026-07-15', NULL, NULL, 'CLEARED', 'VERIFIED', 'AVAILABLE', '2026-10-06 20:12:34', '2026-10-06 20:12:34'),
(3, 6, 'B+', '2003-02-10', 58.00, 'Female', 'Colombo 08', 'Colombo', 6.9147000, 79.8775000, NULL, NULL, NULL, 'CLEARED', 'VERIFIED', 'UNAVAILABLE', '2026-10-06 20:12:34', '2026-10-06 20:12:34');

-- --------------------------------------------------------

--
-- Table structure for table `donor_availability_history`
--

DROP TABLE IF EXISTS `donor_availability_history`;
CREATE TABLE IF NOT EXISTS `donor_availability_history` (
  `availability_id` int NOT NULL AUTO_INCREMENT,
  `donor_id` int NOT NULL,
  `availability_status` enum('AVAILABLE','UNAVAILABLE') COLLATE utf8mb4_unicode_ci NOT NULL,
  `changed_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`availability_id`),
  KEY `donor_id` (`donor_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `donor_availability_history`
--

INSERT INTO `donor_availability_history` (`availability_id`, `donor_id`, `availability_status`, `changed_at`) VALUES
(1, 1, 'AVAILABLE', '2026-10-06 20:12:34'),
(2, 2, 'AVAILABLE', '2026-10-06 20:12:34'),
(3, 3, 'UNAVAILABLE', '2026-10-06 20:12:34');

-- --------------------------------------------------------

--
-- Table structure for table `donor_checkups`
--

DROP TABLE IF EXISTS `donor_checkups`;
CREATE TABLE IF NOT EXISTS `donor_checkups` (
  `checkup_id` int NOT NULL AUTO_INCREMENT,
  `donor_id` int NOT NULL,
  `organization_id` int NOT NULL,
  `scheduled_by` int NOT NULL,
  `scheduled_date` date NOT NULL,
  `actual_date` date DEFAULT NULL,
  `outcome` enum('PENDING','CLEARED','NO_SHOW') COLLATE utf8mb4_unicode_ci DEFAULT 'PENDING',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `recorded_by` int DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`checkup_id`),
  KEY `donor_id` (`donor_id`),
  KEY `organization_id` (`organization_id`),
  KEY `scheduled_by` (`scheduled_by`),
  KEY `recorded_by` (`recorded_by`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `donor_checkups`
--

INSERT INTO `donor_checkups` (`checkup_id`, `donor_id`, `organization_id`, `scheduled_by`, `scheduled_date`, `actual_date`, `outcome`, `notes`, `recorded_by`, `created_at`) VALUES
(1, 1, 1, 1, '2026-05-20', '2026-05-20', 'CLEARED', 'Donor passed health checkup.', 2, '2026-10-06 20:12:34'),
(2, 2, 2, 1, '2026-06-20', '2026-06-20', 'CLEARED', 'Donor passed health checkup.', 3, '2026-10-06 20:12:34'),
(3, 3, 1, 1, '2026-07-20', '2026-07-20', 'CLEARED', 'Donor passed health checkup.', 2, '2026-10-06 20:12:34');

-- --------------------------------------------------------

--
-- Table structure for table `donor_matches`
--

DROP TABLE IF EXISTS `donor_matches`;
CREATE TABLE IF NOT EXISTS `donor_matches` (
  `match_id` int NOT NULL AUTO_INCREMENT,
  `request_id` int NOT NULL,
  `donor_id` int NOT NULL,
  `compatibility_status` enum('COMPATIBLE','INCOMPATIBLE') COLLATE utf8mb4_unicode_ci NOT NULL,
  `distance_km` decimal(10,2) DEFAULT NULL,
  `match_score` decimal(5,2) DEFAULT NULL,
  `status` enum('NOTIFIED','ACCEPTED','DECLINED','COMPLETED') COLLATE utf8mb4_unicode_ci DEFAULT 'NOTIFIED',
  `matched_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `responded_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `decline_reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`match_id`),
  UNIQUE KEY `request_id` (`request_id`,`donor_id`),
  KEY `donor_id` (`donor_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `donor_matches`
--

INSERT INTO `donor_matches` (`match_id`, `request_id`, `donor_id`, `compatibility_status`, `distance_km`, `match_score`, `status`, `matched_at`, `responded_at`, `completed_at`) VALUES
(1, 1, 1, 'COMPATIBLE', 5.20, 95.00, 'NOTIFIED', '2026-10-06 20:12:34', NULL, NULL),
(2, 2, 2, 'COMPATIBLE', 3.50, 90.00, 'NOTIFIED', '2026-10-06 20:12:34', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `emergency_requests`
--

DROP TABLE IF EXISTS `emergency_requests`;
CREATE TABLE IF NOT EXISTS `emergency_requests` (
  `request_id` int NOT NULL AUTO_INCREMENT,
  `organization_id` int NOT NULL,
  `blood_group` enum('A+','A-','B+','B-','AB+','AB-','O+','O-') COLLATE utf8mb4_unicode_ci NOT NULL,
  `units_required` int NOT NULL,
  `urgency` enum('CRITICAL','HIGH','NORMAL') COLLATE utf8mb4_unicode_ci NOT NULL,
  `required_time` datetime NOT NULL,
  `location` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `reason` text COLLATE utf8mb4_unicode_ci,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `status` enum('PENDING','MATCHING','PARTIALLY_FULFILLED','FULFILLED','CANCELLED') COLLATE utf8mb4_unicode_ci DEFAULT 'PENDING',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `closed_at` datetime DEFAULT NULL,
  PRIMARY KEY (`request_id`),
  KEY `organization_id` (`organization_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `emergency_requests`
--

INSERT INTO `emergency_requests` (`request_id`, `organization_id`, `blood_group`, `units_required`, `urgency`, `required_time`, `location`, `reason`, `notes`, `status`, `created_at`, `updated_at`, `closed_at`) VALUES
(1, 1, 'O+', 4, 'CRITICAL', '2026-10-06 20:00:00', 'Colombo General Hospital', 'Emergency surgery', 'Urgent requirement for O+ blood.', 'MATCHING', '2026-10-06 20:12:34', '2026-10-06 20:12:34', NULL),
(2, 2, 'A+', 2, 'HIGH', '2026-10-07 10:00:00', 'Kandy Hospital', 'Patient treatment', 'Blood required for scheduled treatment.', 'PENDING', '2026-10-06 20:12:34', '2026-10-06 20:12:34', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `emergency_request_history`
--

DROP TABLE IF EXISTS `emergency_request_history`;
CREATE TABLE IF NOT EXISTS `emergency_request_history` (
  `history_id` int NOT NULL AUTO_INCREMENT,
  `request_id` int NOT NULL,
  `old_status` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `new_status` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `changed_by` int NOT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `changed_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`history_id`),
  KEY `request_id` (`request_id`),
  KEY `changed_by` (`changed_by`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `emergency_request_history`
--

INSERT INTO `emergency_request_history` (`history_id`, `request_id`, `old_status`, `new_status`, `changed_by`, `notes`, `changed_at`) VALUES
(1, 1, 'PENDING', 'MATCHING', 2, 'Inventory was below minimum stock, donor matching started.', '2026-10-06 20:12:34');

-- --------------------------------------------------------

--
-- Table structure for table `healthcare_organizations`
--

DROP TABLE IF EXISTS `healthcare_organizations`;
CREATE TABLE IF NOT EXISTS `healthcare_organizations` (
  `organization_id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `organization_name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `organization_type` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `registration_number` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `address` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `city` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `verification_status` enum('PENDING','VERIFIED','REJECTED') COLLATE utf8mb4_unicode_ci DEFAULT 'PENDING',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `certificate_file` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rejection_reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reviewed_by` int DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `pending_city` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pending_address` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `location_change_at` datetime DEFAULT NULL,
  PRIMARY KEY (`organization_id`),
  UNIQUE KEY `user_id` (`user_id`),
  UNIQUE KEY `registration_number` (`registration_number`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `healthcare_organizations`
--

INSERT INTO `healthcare_organizations` (`organization_id`, `user_id`, `organization_name`, `organization_type`, `registration_number`, `address`, `city`, `phone`, `verification_status`, `created_at`) VALUES
(1, 2, 'Colombo Blood Bank', 'Blood Bank', 'BB-CMB-001', 'Colombo 07', 'Colombo', '0772222222', 'VERIFIED', '2026-10-06 20:12:34'),
(2, 3, 'Kandy Blood Bank', 'Blood Bank', 'BB-KDY-001', 'Kandy', 'Kandy', '0773333333', 'VERIFIED', '2026-10-06 20:12:34');

-- --------------------------------------------------------

--
-- Table structure for table `inventory_logs`
--

DROP TABLE IF EXISTS `inventory_logs`;
CREATE TABLE IF NOT EXISTS `inventory_logs` (
  `inventory_log_id` int NOT NULL AUTO_INCREMENT,
  `inventory_id` int NOT NULL,
  `organization_id` int NOT NULL,
  `blood_group` enum('A+','A-','B+','B-','AB+','AB-','O+','O-') COLLATE utf8mb4_unicode_ci NOT NULL,
  `change_type` enum('DONATION','EMERGENCY_USE','ADJUSTMENT','RESTOCK') COLLATE utf8mb4_unicode_ci NOT NULL,
  `quantity_change` int NOT NULL,
  `previous_units` int NOT NULL,
  `new_units` int NOT NULL,
  `reference_id` int DEFAULT NULL,
  `changed_by` int NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`inventory_log_id`),
  KEY `inventory_id` (`inventory_id`),
  KEY `organization_id` (`organization_id`),
  KEY `changed_by` (`changed_by`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `inventory_logs`
--

INSERT INTO `inventory_logs` (`inventory_log_id`, `inventory_id`, `organization_id`, `blood_group`, `change_type`, `quantity_change`, `previous_units`, `new_units`, `reference_id`, `changed_by`, `created_at`) VALUES
(1, 1, 1, 'A+', 'DONATION', 1, 4, 5, NULL, 2, '2026-10-06 20:12:34'),
(2, 2, 1, 'O+', 'EMERGENCY_USE', -1, 3, 2, 1, 2, '2026-10-06 20:12:34'),
(3, 3, 1, 'B+', 'RESTOCK', 2, 2, 4, NULL, 2, '2026-10-06 20:12:34');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

DROP TABLE IF EXISTS `notifications`;
CREATE TABLE IF NOT EXISTS `notifications` (
  `notification_id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `notification_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `message` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `related_request_id` int DEFAULT NULL,
  `related_match_id` int DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`notification_id`),
  KEY `user_id` (`user_id`),
  KEY `related_request_id` (`related_request_id`),
  KEY `related_match_id` (`related_match_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`notification_id`, `user_id`, `notification_type`, `title`, `message`, `related_request_id`, `related_match_id`, `is_read`, `created_at`) VALUES
(1, 4, 'EMERGENCY_REQUEST', 'Emergency Blood Request', 'An emergency O+ blood request is available near you.', 1, 1, 0, '2026-10-06 20:12:34'),
(2, 5, 'EMERGENCY_REQUEST', 'Emergency Blood Request', 'An emergency A+ blood request is available.', 2, 2, 0, '2026-10-06 20:12:34');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
CREATE TABLE IF NOT EXISTS `users` (
  `user_id` int NOT NULL AUTO_INCREMENT,
  `full_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` enum('DONOR','HEALTHCARE','ADMIN') COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('PENDING','ACTIVE','SUSPENDED') COLLATE utf8mb4_unicode_ci DEFAULT 'PENDING',
  `must_change_password` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `full_name`, `email`, `password`, `role`, `phone`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Admin User', 'admin@sebd.com', 'admin123', 'ADMIN', '0771111111', 'ACTIVE', '2026-10-06 20:12:34', '2026-10-06 20:12:34'),
(2, 'Colombo Blood Bank', 'colombo@sebd.com', 'health123', 'HEALTHCARE', '0772222222', 'ACTIVE', '2026-10-06 20:12:34', '2026-10-06 20:12:34'),
(3, 'Kandy Blood Bank', 'kandy@sebd.com', 'health123', 'HEALTHCARE', '0773333333', 'ACTIVE', '2026-10-06 20:12:34', '2026-10-06 20:12:34'),
(4, 'Nimal Perera', 'nimal@gmail.com', 'donor123', 'DONOR', '0774444444', 'ACTIVE', '2026-10-06 20:12:34', '2026-10-06 20:12:34'),
(5, 'Kamal Silva', 'kamal@gmail.com', 'donor123', 'DONOR', '0775555555', 'ACTIVE', '2026-10-06 20:12:34', '2026-10-06 20:12:34'),
(6, 'Amal Fernando', 'amal@gmail.com', 'donor123', 'DONOR', '0776666666', 'ACTIVE', '2026-10-06 20:12:34', '2026-10-06 20:12:34');

--
-- Constraints for dumped tables
--

--
-- Constraints for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD CONSTRAINT `audit_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE RESTRICT ON UPDATE CASCADE;

--
-- Constraints for table `blood_inventory`
--
ALTER TABLE `blood_inventory`
  ADD CONSTRAINT `blood_inventory_ibfk_1` FOREIGN KEY (`organization_id`) REFERENCES `healthcare_organizations` (`organization_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `campaign_target_blood_groups`
--
ALTER TABLE `campaign_target_blood_groups`
  ADD CONSTRAINT `campaign_target_blood_groups_ibfk_1` FOREIGN KEY (`campaign_id`) REFERENCES `donation_campaigns` (`campaign_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `donations`
--
ALTER TABLE `donations`
  ADD CONSTRAINT `donations_ibfk_1` FOREIGN KEY (`donor_id`) REFERENCES `donors` (`donor_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `donations_ibfk_2` FOREIGN KEY (`organization_id`) REFERENCES `healthcare_organizations` (`organization_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `donations_ibfk_3` FOREIGN KEY (`campaign_id`) REFERENCES `donation_campaigns` (`campaign_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `donations_ibfk_4` FOREIGN KEY (`match_id`) REFERENCES `donor_matches` (`match_id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `donation_campaigns`
--
ALTER TABLE `donation_campaigns`
  ADD CONSTRAINT `donation_campaigns_ibfk_1` FOREIGN KEY (`organization_id`) REFERENCES `healthcare_organizations` (`organization_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `donation_history`
--
ALTER TABLE `donation_history`
  ADD CONSTRAINT `donation_history_ibfk_1` FOREIGN KEY (`donor_id`) REFERENCES `donors` (`donor_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `donation_history_ibfk_2` FOREIGN KEY (`donation_id`) REFERENCES `donations` (`donation_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `donation_history_ibfk_3` FOREIGN KEY (`organization_id`) REFERENCES `healthcare_organizations` (`organization_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `donors`
--
ALTER TABLE `donors`
  ADD CONSTRAINT `donors_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `donor_availability_history`
--
ALTER TABLE `donor_availability_history`
  ADD CONSTRAINT `donor_availability_history_ibfk_1` FOREIGN KEY (`donor_id`) REFERENCES `donors` (`donor_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `donor_checkups`
--
ALTER TABLE `donor_checkups`
  ADD CONSTRAINT `donor_checkups_ibfk_1` FOREIGN KEY (`donor_id`) REFERENCES `donors` (`donor_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `donor_checkups_ibfk_2` FOREIGN KEY (`organization_id`) REFERENCES `healthcare_organizations` (`organization_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `donor_checkups_ibfk_3` FOREIGN KEY (`scheduled_by`) REFERENCES `users` (`user_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `donor_checkups_ibfk_4` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `donor_matches`
--
ALTER TABLE `donor_matches`
  ADD CONSTRAINT `donor_matches_ibfk_1` FOREIGN KEY (`request_id`) REFERENCES `emergency_requests` (`request_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `donor_matches_ibfk_2` FOREIGN KEY (`donor_id`) REFERENCES `donors` (`donor_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `emergency_requests`
--
ALTER TABLE `emergency_requests`
  ADD CONSTRAINT `emergency_requests_ibfk_1` FOREIGN KEY (`organization_id`) REFERENCES `healthcare_organizations` (`organization_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `emergency_request_history`
--
ALTER TABLE `emergency_request_history`
  ADD CONSTRAINT `emergency_request_history_ibfk_1` FOREIGN KEY (`request_id`) REFERENCES `emergency_requests` (`request_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `emergency_request_history_ibfk_2` FOREIGN KEY (`changed_by`) REFERENCES `users` (`user_id`) ON DELETE RESTRICT ON UPDATE CASCADE;

--
-- Constraints for table `healthcare_organizations`
--
ALTER TABLE `healthcare_organizations`
  ADD CONSTRAINT `healthcare_organizations_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `inventory_logs`
--
ALTER TABLE `inventory_logs`
  ADD CONSTRAINT `inventory_logs_ibfk_1` FOREIGN KEY (`inventory_id`) REFERENCES `blood_inventory` (`inventory_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `inventory_logs_ibfk_2` FOREIGN KEY (`organization_id`) REFERENCES `healthcare_organizations` (`organization_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `inventory_logs_ibfk_3` FOREIGN KEY (`changed_by`) REFERENCES `users` (`user_id`) ON DELETE RESTRICT ON UPDATE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `notifications_ibfk_2` FOREIGN KEY (`related_request_id`) REFERENCES `emergency_requests` (`request_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `notifications_ibfk_3` FOREIGN KEY (`related_match_id`) REFERENCES `donor_matches` (`match_id`) ON DELETE SET NULL ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;


-- Compatibility, coordinates and hashed seed passwords
-- Run AFTER importing sebd.sql (database: sebd)
ALTER TABLE healthcare_organizations ADD COLUMN latitude DECIMAL(10,7) NULL, ADD COLUMN longitude DECIMAL(10,7) NULL;
ALTER TABLE emergency_requests ADD COLUMN latitude DECIMAL(10,7) NULL, ADD COLUMN longitude DECIMAL(10,7) NULL;
UPDATE healthcare_organizations SET latitude=6.9271000, longitude=79.8612000 WHERE organization_id=1;
UPDATE healthcare_organizations SET latitude=7.2906000, longitude=80.6337000 WHERE organization_id=2;
-- Hash the sample passwords (admin123 / health123 / donor123 still work for login)
UPDATE users SET password='$2y$10$F2ViJuyV47uwVCQga1zg/eJrxdyFtKt2cnHr3vv7hwJeqniu7N/Ui' WHERE role='ADMIN';
UPDATE users SET password='$2y$10$88vHDmMatn0TLu1ZVUbwuOQCSVupyTdGRAcZ/dlLr7jXo4rZon7dS' WHERE role='HEALTHCARE';
UPDATE users SET password='$2y$10$IyUfnBJEHEu0BI4KavUeFeN/OmzhKdRFNxo68jLQekfmtuymHpWfu' WHERE role='DONOR';


-- Donor campaign registrations (legacy table retained for compatibility)
-- SEBD donor-flow upgrade (safe to import after sebd.sql).
-- This adds appointment requests; it does not drop or replace existing project data.
USE sebd;

CREATE TABLE IF NOT EXISTS donor_appointments (
  appointment_id INT NOT NULL AUTO_INCREMENT,
  donor_id INT NOT NULL,
  organization_id INT NOT NULL,
  requested_date DATE NOT NULL,
  requested_time TIME NOT NULL,
  reason VARCHAR(255) NULL,
  donor_notes TEXT NULL,
  organization_notes TEXT NULL,
  status ENUM('PENDING','CONFIRMED','REJECTED','CANCELLED','COMPLETED') NOT NULL DEFAULT 'PENDING',
  reviewed_by INT NULL,
  reviewed_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (appointment_id),
  KEY idx_appointments_donor_status (donor_id,status),
  KEY idx_appointments_org_date (organization_id,requested_date,requested_time),
  KEY idx_appointments_status (status),
  CONSTRAINT fk_donor_appointments_donor FOREIGN KEY (donor_id) REFERENCES donors(donor_id),
  CONSTRAINT fk_donor_appointments_org FOREIGN KEY (organization_id) REFERENCES healthcare_organizations(organization_id),
  CONSTRAINT fk_donor_appointments_reviewer FOREIGN KEY (reviewed_by) REFERENCES users(user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Privacy consent evidence is kept in audit_logs by the registration flow.

CREATE TABLE IF NOT EXISTS donor_campaign_registrations (
  registration_id INT NOT NULL AUTO_INCREMENT,
  campaign_id INT NOT NULL,
  donor_id INT NOT NULL,
  status ENUM('REGISTERED','CANCELLED','ATTENDED') NOT NULL DEFAULT 'REGISTERED',
  registered_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (registration_id),
  UNIQUE KEY uq_campaign_donor (campaign_id,donor_id),
  KEY idx_campaign_registration_donor (donor_id,status),
  KEY idx_campaign_registration_campaign (campaign_id,status),
  CONSTRAINT fk_campaign_registrations_campaign FOREIGN KEY (campaign_id) REFERENCES donation_campaigns(campaign_id),
  CONSTRAINT fk_campaign_registrations_donor FOREIGN KEY (donor_id) REFERENCES donors(donor_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- Expiry-aware inventory (batches) ----

CREATE TABLE IF NOT EXISTS blood_batches (
  batch_id INT NOT NULL AUTO_INCREMENT,
  organization_id INT NOT NULL,
  blood_group ENUM('A+','A-','B+','B-','AB+','AB-','O+','O-') NOT NULL,
  units_remaining INT NOT NULL,
  collected_date DATE NOT NULL,
  expiry_date DATE NOT NULL,
  source ENUM('DONATION','RESTOCK','ADJUSTMENT','OPENING') NOT NULL DEFAULT 'RESTOCK',
  donation_id INT NULL,
  status ENUM('ACTIVE','USED','EXPIRED') NOT NULL DEFAULT 'ACTIVE',
  disposed_at DATETIME NULL,
  disposed_by INT NULL,
  disposal_note VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (batch_id),
  KEY idx_batch_org_group (organization_id,blood_group,status,expiry_date),
  CONSTRAINT fk_batch_org FOREIGN KEY (organization_id) REFERENCES healthcare_organizations(organization_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE inventory_logs MODIFY change_type ENUM('DONATION','EMERGENCY_USE','ADJUSTMENT','RESTOCK','EXPIRED') NOT NULL;

-- Opening balance: existing stock becomes one batch per blood group, assumed collected today (expires in 35 days).
INSERT INTO blood_batches(organization_id,blood_group,units_remaining,collected_date,expiry_date,source)
SELECT i.organization_id,i.blood_group,i.units_available,CURDATE(),DATE_ADD(CURDATE(),INTERVAL 35 DAY),'OPENING'
FROM blood_inventory i
WHERE i.units_available>0 AND NOT EXISTS(SELECT 1 FROM blood_batches b WHERE b.organization_id=i.organization_id AND b.blood_group=i.blood_group);


-- ---- Urgency / matching upgrade (precise donor and request location) ----
ALTER TABLE donors             ADD COLUMN location_precise TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE emergency_requests ADD COLUMN location_precise TINYINT(1) NOT NULL DEFAULT 0;

-- ---- Quarantine: donated blood is held until screening tests (HIV, hepatitis B/C, syphilis, malaria) are done ----
-- QUARANTINE = collected, tests pending (NOT counted as stock). REJECTED = reactive/failed, must be discarded.
ALTER TABLE blood_batches MODIFY status ENUM('ACTIVE','USED','EXPIRED','QUARANTINE','REJECTED') NOT NULL DEFAULT 'ACTIVE';
ALTER TABLE blood_batches ADD COLUMN released_at DATETIME NULL AFTER disposal_note, ADD COLUMN released_by INT NULL AFTER released_at;

-- ---- Admin review fixes ----
-- 1) audit_logs.user_id may be NULL so failed logins for UNKNOWN e-mails can be recorded
ALTER TABLE audit_logs MODIFY user_id INT NULL;
-- 2) heartbeat written by cron/process_requests.php, read by the admin dashboard
CREATE TABLE IF NOT EXISTS system_status (
  status_key VARCHAR(50) NOT NULL,
  status_value VARCHAR(255) NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (status_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===================================================================
-- SEBD sample data for the donor / real-world match test.
-- Run ONCE after SETUP_ALL.sql (database: sebd). Dates are relative to the moment you import it.
-- Logins: every donor below uses password  donor123 .

-- 1. Fix the original seed data (its dates had expired and two donors could not match)
UPDATE emergency_requests SET required_time = NOW() + INTERVAL 1 DAY,  status='PENDING' WHERE request_id=1;
UPDATE emergency_requests SET required_time = NOW() + INTERVAL 2 DAY,  status='PENDING' WHERE request_id=2;
UPDATE emergency_requests r JOIN healthcare_organizations o USING(organization_id)
   SET r.latitude=o.latitude, r.longitude=o.longitude WHERE r.latitude IS NULL;
UPDATE donors SET last_donation_date = CURDATE() - INTERVAL 6 MONTH WHERE donor_id IN (1,2);  -- 4-month gap now satisfied
UPDATE donors SET availability_status='AVAILABLE' WHERE donor_id=3;

-- 2. Extra donor users (password donor123)
INSERT IGNORE INTO users(full_name,email,password,role,phone,status) VALUES
 ('Sunil Jayasinghe','sunil.a@sebd.test','$2y$10$IyUfnBJEHEu0BI4KavUeFeN/OmzhKdRFNxo68jLQekfmtuymHpWfu','DONOR','0777000001','ACTIVE'),
 ('Dilani Wickrama','dilani.o@sebd.test','$2y$10$IyUfnBJEHEu0BI4KavUeFeN/OmzhKdRFNxo68jLQekfmtuymHpWfu','DONOR','0777000002','ACTIVE'),
 ('Ruwan Bandara','ruwan.a@sebd.test','$2y$10$IyUfnBJEHEu0BI4KavUeFeN/OmzhKdRFNxo68jLQekfmtuymHpWfu','DONOR','0777000003','ACTIVE'),
 ('Nadeesha Perera','nadeesha.b@sebd.test','$2y$10$IyUfnBJEHEu0BI4KavUeFeN/OmzhKdRFNxo68jLQekfmtuymHpWfu','DONOR','0777000004','ACTIVE'),
 ('Test Recent Donor','recent@sebd.test','$2y$10$IyUfnBJEHEu0BI4KavUeFeN/OmzhKdRFNxo68jLQekfmtuymHpWfu','DONOR','0777000005','ACTIVE'),
 ('Test Underweight','light@sebd.test','$2y$10$IyUfnBJEHEu0BI4KavUeFeN/OmzhKdRFNxo68jLQekfmtuymHpWfu','DONOR','0777000006','ACTIVE'),
 ('Test Unverified','pending@sebd.test','$2y$10$IyUfnBJEHEu0BI4KavUeFeN/OmzhKdRFNxo68jLQekfmtuymHpWfu','DONOR','0777000007','ACTIVE');

-- 3. Donor profiles. First four are eligible; last three are deliberate NEGATIVE cases (must NOT be matched)
INSERT INTO donors(user_id,blood_group,date_of_birth,weight_kg,gender,address,city,latitude,longitude,last_donation_date,health_status,verification_status,availability_status)
SELECT u.user_id,x.bg,x.dob,x.w,x.g,x.addr,x.city,x.la,x.ln,x.last,x.hs,x.vs,x.av FROM users u JOIN (
 SELECT 'sunil.a@sebd.test' e,'A+' bg,'1994-03-12' dob,72 w,'Male' g,'Colombo 03' addr,'Colombo' city,6.9100000 la,79.8500000 ln,NULL `last`,'CLEARED' hs,'VERIFIED' vs,'AVAILABLE' av
 UNION ALL SELECT 'dilani.o@sebd.test','O-','1998-07-01',60,'Female','Colombo 07','Colombo',6.9050000,79.8650000,(CURDATE() - INTERVAL 8 MONTH),'CLEARED','VERIFIED','AVAILABLE'
 UNION ALL SELECT 'ruwan.a@sebd.test','A+','1990-11-23',80,'Male','Kandy','Kandy',7.2950000,80.6300000,NULL,'CLEARED','VERIFIED','AVAILABLE'
 UNION ALL SELECT 'nadeesha.b@sebd.test','B+','1999-01-30',55,'Female','Kandy','Kandy',7.2850000,80.6400000,NULL,'CLEARED','VERIFIED','AVAILABLE'
 UNION ALL SELECT 'recent@sebd.test','A+','1993-05-05',70,'Male','Colombo 02','Colombo',6.9300000,79.8500000,(CURDATE() - INTERVAL 1 MONTH),'CLEARED','VERIFIED','AVAILABLE'
 UNION ALL SELECT 'light@sebd.test','A+','2000-09-09',49,'Female','Colombo 04','Colombo',6.9000000,79.8600000,NULL,'CLEARED','VERIFIED','AVAILABLE'
 UNION ALL SELECT 'pending@sebd.test','A+','1996-02-02',68,'Male','Colombo 06','Colombo',6.8900000,79.8700000,NULL,'PENDING','PENDING','UNAVAILABLE'
) x ON u.email=x.e WHERE NOT EXISTS (SELECT 1 FROM donors d WHERE d.user_id=u.user_id);

-- 4. Fresh emergency requests for the match test (matched by cron/process_requests.php or when an organization creates one)
INSERT INTO emergency_requests(organization_id,blood_group,units_required,urgency,required_time,location,reason,notes,status,latitude,longitude)
SELECT o.organization_id,'A+',2,'CRITICAL',NOW() + INTERVAL 6 HOUR,'Colombo General Hospital','SAMPLE-MATCH-TEST trauma surgery','Expect: Sunil + Ruwan matched; Recent Donor, Underweight and Unverified must NOT be.','PENDING',o.latitude,o.longitude
FROM healthcare_organizations o WHERE o.organization_id=1 AND NOT EXISTS (SELECT 1 FROM emergency_requests WHERE reason LIKE 'SAMPLE-MATCH-TEST trauma%');
INSERT INTO emergency_requests(organization_id,blood_group,units_required,urgency,required_time,location,reason,notes,status,latitude,longitude)
SELECT o.organization_id,'B+',1,'NORMAL',NOW() + INTERVAL 3 DAY,'Kandy Hospital','SAMPLE-MATCH-TEST scheduled treatment','Expect: Nadeesha (B+, Kandy) and Amal (B+, Colombo, farther) matched.','PENDING',o.latitude,o.longitude
FROM healthcare_organizations o WHERE o.organization_id=2 AND NOT EXISTS (SELECT 1 FROM emergency_requests WHERE reason LIKE 'SAMPLE-MATCH-TEST scheduled%');
