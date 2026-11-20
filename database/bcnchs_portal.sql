-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 18, 2026 at 06:26 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `bcnchs_portal`
--

-- --------------------------------------------------------

--
-- Table structure for table `academic_records`
--

CREATE TABLE `academic_records` (
  `record_id` int(10) UNSIGNED NOT NULL,
  `student_id` int(10) UNSIGNED NOT NULL,
  `subject_id` int(10) UNSIGNED NOT NULL,
  `school_year_id` int(10) UNSIGNED NOT NULL,
  `grading_period` varchar(50) DEFAULT NULL,
  `grade` decimal(5,2) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `recorded_by` int(10) UNSIGNED DEFAULT NULL,
  `recorded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `academic_records`
--

INSERT INTO `academic_records` (`record_id`, `student_id`, `subject_id`, `school_year_id`, `grading_period`, `grade`, `remarks`, `recorded_by`, `recorded_at`) VALUES
(1, 1, 5, 1, 'First Quarter', 89.00, 'Sample grade for testing', NULL, '2026-11-17 17:05:48'),
(2, 1, 1, 1, 'First Quarter', 92.00, 'Sample grade for testing', NULL, '2026-11-17 17:05:48'),
(3, 1, 6, 1, 'First Quarter', 94.00, 'Sample grade for testing', NULL, '2026-11-17 17:05:48'),
(4, 1, 4, 1, 'First Quarter', 91.00, 'Sample grade for testing', NULL, '2026-11-17 17:05:48'),
(5, 1, 7, 1, 'First Quarter', 93.00, 'Sample grade for testing', NULL, '2026-11-17 17:05:48'),
(6, 1, 2, 1, 'First Quarter', 88.00, 'Sample grade for testing', NULL, '2026-11-17 17:05:48'),
(7, 1, 3, 1, 'First Quarter', 90.00, 'Sample grade for testing', NULL, '2026-11-17 17:05:48'),
(8, 1, 8, 1, 'First Quarter', 90.00, 'Sample grade for testing', NULL, '2026-11-17 17:05:48'),
(16, 1, 5, 1, 'Second Quarter', 91.00, 'Sample grade for testing', NULL, '2026-11-17 17:07:24'),
(17, 1, 5, 1, 'Third Quarter', 91.00, 'Sample grade for testing', NULL, '2026-11-17 17:07:24'),
(18, 1, 5, 1, 'Fourth Quarter', 91.00, 'Sample grade for testing', NULL, '2026-11-17 17:07:24'),
(19, 1, 1, 1, 'Second Quarter', 93.00, 'Sample grade for testing', NULL, '2026-11-17 17:07:24'),
(20, 1, 1, 1, 'Third Quarter', 93.00, 'Sample grade for testing', NULL, '2026-11-17 17:07:24'),
(21, 1, 1, 1, 'Fourth Quarter', 93.00, 'Sample grade for testing', NULL, '2026-11-17 17:07:24'),
(22, 1, 6, 1, 'Second Quarter', 95.00, 'Sample grade for testing', NULL, '2026-11-17 17:07:24'),
(23, 1, 6, 1, 'Third Quarter', 95.00, 'Sample grade for testing', NULL, '2026-11-17 17:07:24'),
(24, 1, 6, 1, 'Fourth Quarter', 95.00, 'Sample grade for testing', NULL, '2026-11-17 17:07:24'),
(25, 1, 4, 1, 'Second Quarter', 94.00, 'Sample grade for testing', NULL, '2026-11-17 17:07:24'),
(26, 1, 4, 1, 'Third Quarter', 94.00, 'Sample grade for testing', NULL, '2026-11-17 17:07:24'),
(27, 1, 4, 1, 'Fourth Quarter', 94.00, 'Sample grade for testing', NULL, '2026-11-17 17:07:24'),
(28, 1, 7, 1, 'Second Quarter', 94.00, 'Sample grade for testing', NULL, '2026-11-17 17:07:24'),
(29, 1, 7, 1, 'Third Quarter', 94.00, 'Sample grade for testing', NULL, '2026-11-17 17:07:24'),
(30, 1, 7, 1, 'Fourth Quarter', 94.00, 'Sample grade for testing', NULL, '2026-11-17 17:07:24'),
(31, 1, 2, 1, 'Second Quarter', 90.00, 'Sample grade for testing', NULL, '2026-11-17 17:07:24'),
(32, 1, 2, 1, 'Third Quarter', 90.00, 'Sample grade for testing', NULL, '2026-11-17 17:07:24'),
(33, 1, 2, 1, 'Fourth Quarter', 90.00, 'Sample grade for testing', NULL, '2026-11-17 17:07:24'),
(34, 1, 3, 1, 'Second Quarter', 92.00, 'Sample grade for testing', NULL, '2026-11-17 17:07:24'),
(35, 1, 3, 1, 'Third Quarter', 92.00, 'Sample grade for testing', NULL, '2026-11-17 17:07:24'),
(36, 1, 3, 1, 'Fourth Quarter', 92.00, 'Sample grade for testing', NULL, '2026-11-17 17:07:24'),
(37, 1, 8, 1, 'Second Quarter', 92.00, 'Sample grade for testing', NULL, '2026-11-17 17:07:24'),
(38, 1, 8, 1, 'Third Quarter', 92.00, 'Sample grade for testing', NULL, '2026-11-17 17:07:24'),
(39, 1, 8, 1, 'Fourth Quarter', 92.00, 'Sample grade for testing', NULL, '2026-11-17 17:07:24');

-- --------------------------------------------------------

--
-- Table structure for table `announcements`
--

CREATE TABLE `announcements` (
  `announcement_id` int(10) UNSIGNED NOT NULL,
  `title` varchar(200) NOT NULL,
  `content` text NOT NULL,
  `announcement_type` enum('Enrollment','Scholarship','School','General') NOT NULL DEFAULT 'General',
  `publish_date` datetime NOT NULL DEFAULT current_timestamp(),
  `expiry_date` datetime DEFAULT NULL,
  `status` enum('Published','Draft','Archived') NOT NULL DEFAULT 'Draft',
  `created_by` int(10) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `attendance_records`
--

CREATE TABLE `attendance_records` (
  `attendance_id` int(10) UNSIGNED NOT NULL,
  `student_id` int(10) UNSIGNED NOT NULL,
  `subject_id` int(10) UNSIGNED DEFAULT NULL,
  `attendance_date` date NOT NULL,
  `attendance_status` enum('Present','Absent','Late','Excused') NOT NULL,
  `remarks` text DEFAULT NULL,
  `recorded_by` int(10) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `attendance_records`
--

INSERT INTO `attendance_records` (`attendance_id`, `student_id`, `subject_id`, `attendance_date`, `attendance_status`, `remarks`, `recorded_by`) VALUES
(1, 1, 1, '2026-11-09', 'Present', 'Attended class', NULL),
(2, 1, 2, '2026-11-09', 'Present', 'Attended class', NULL),
(3, 1, 3, '2026-11-10', 'Late', 'Arrived late', NULL),
(4, 1, 4, '2026-11-10', 'Present', 'Attended class', NULL),
(5, 1, 5, '2026-11-11', 'Absent', 'No attendance', NULL),
(6, 1, 6, '2026-11-11', 'Excused', 'Excused absence', NULL),
(7, 1, 7, '2026-11-12', 'Present', 'Attended class', NULL),
(8, 1, 8, '2026-11-12', 'Present', 'Attended class', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `audit_id` bigint(20) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `entity_type` varchar(80) DEFAULT NULL,
  `entity_id` bigint(20) UNSIGNED DEFAULT NULL,
  `details` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `enrollment_applications`
--

CREATE TABLE `enrollment_applications` (
  `application_id` int(10) UNSIGNED NOT NULL,
  `student_id` int(10) UNSIGNED NOT NULL,
  `school_year_id` int(10) UNSIGNED NOT NULL,
  `lrn` varchar(20) DEFAULT NULL,
  `enrollment_type` enum('Incoming Grade 7','Returning','Transferee') NOT NULL,
  `requested_grade_level_id` int(10) UNSIGNED NOT NULL,
  `assigned_section_id` int(10) UNSIGNED DEFAULT NULL,
  `application_status` enum('Pending Review','Incomplete Requirements','Approved','Rejected') NOT NULL DEFAULT 'Pending Review',
  `remarks` text DEFAULT NULL,
  `submitted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `reviewed_by` int(10) UNSIGNED DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `enrollment_applications`
--

INSERT INTO `enrollment_applications` (`application_id`, `student_id`, `school_year_id`, `lrn`, `enrollment_type`, `requested_grade_level_id`, `assigned_section_id`, `application_status`, `remarks`, `submitted_at`, `updated_at`, `reviewed_by`, `reviewed_at`) VALUES
(1, 1, 1, '1234567891012', 'Returning', 3, NULL, 'Pending Review', NULL, '2026-11-17 16:50:49', '2026-11-17 16:55:18', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `enrollment_documents`
--

CREATE TABLE `enrollment_documents` (
  `document_id` int(10) UNSIGNED NOT NULL,
  `application_id` int(10) UNSIGNED NOT NULL,
  `document_type` enum('PSA Birth Certificate','Old School Card (before moving up)','Good Moral Certificate') NOT NULL,
  `file_path` varchar(255) DEFAULT NULL,
  `verification_status` enum('Not Submitted','Submitted','For Verification','Accepted','Rejected') NOT NULL DEFAULT 'Not Submitted',
  `remarks` text DEFAULT NULL,
  `uploaded_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `enrollment_documents`
--

INSERT INTO `enrollment_documents` (`document_id`, `application_id`, `document_type`, `file_path`, `verification_status`, `remarks`, `uploaded_at`) VALUES
(1, 1, 'PSA Birth Certificate', 'uploads/enrollment/c3265217ae66363f925e65cf429b6abb.jpg', 'Submitted', NULL, '2026-11-18 00:54:30'),
(2, 1, 'Old School Card (before moving up)', 'uploads/enrollment/d8d08f3aa73c9c4c9f7230e507ee0ad9.jpg', 'Submitted', NULL, '2026-11-18 00:56:55'),
(3, 1, 'Good Moral Certificate', 'uploads/enrollment/5cbf99ab7a25eb7eb594ba6bfa431c42.jpg', 'Submitted', NULL, '2026-11-18 00:56:55');

-- --------------------------------------------------------

--
-- Table structure for table `grade_levels`
--

CREATE TABLE `grade_levels` (
  `grade_level_id` int(10) UNSIGNED NOT NULL,
  `grade_level` varchar(10) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `grade_levels`
--

INSERT INTO `grade_levels` (`grade_level_id`, `grade_level`) VALUES
(4, '10'),
(1, '7'),
(2, '8'),
(3, '9');

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `role_id` int(10) UNSIGNED NOT NULL,
  `role_name` varchar(60) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`role_id`, `role_name`) VALUES
(4, 'Enrollment Committee'),
(5, 'Scholarship Coordinator'),
(2, 'School Registrar'),
(6, 'Student'),
(1, 'System Administrator'),
(3, 'Teacher');

-- --------------------------------------------------------

--
-- Table structure for table `scholarship_applications`
--

CREATE TABLE `scholarship_applications` (
  `scholarship_application_id` int(10) UNSIGNED NOT NULL,
  `student_id` int(10) UNSIGNED NOT NULL,
  `program_id` int(10) UNSIGNED NOT NULL,
  `school_year_id` int(10) UNSIGNED NOT NULL,
  `application_status` enum('Pending Review','For Screening','Approved','Rejected') NOT NULL DEFAULT 'Pending Review',
  `remarks` text DEFAULT NULL,
  `application_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `reviewed_by` int(10) UNSIGNED DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `scholarship_monitoring`
--

CREATE TABLE `scholarship_monitoring` (
  `monitoring_id` int(10) UNSIGNED NOT NULL,
  `scholarship_application_id` int(10) UNSIGNED NOT NULL,
  `academic_performance` text DEFAULT NULL,
  `behavior_remarks` text DEFAULT NULL,
  `participation_status` enum('Qualified','Currently Enrolled','Removed') NOT NULL DEFAULT 'Qualified',
  `renewal_eligibility` enum('Eligible','Not Eligible','Pending') NOT NULL DEFAULT 'Pending',
  `monitoring_date` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `school_years`
--

CREATE TABLE `school_years` (
  `school_year_id` int(10) UNSIGNED NOT NULL,
  `school_year` varchar(20) NOT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `status` enum('Active','Inactive','Completed') NOT NULL DEFAULT 'Inactive'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `school_years`
--

INSERT INTO `school_years` (`school_year_id`, `school_year`, `start_date`, `end_date`, `status`) VALUES
(1, '2026-2027', NULL, NULL, 'Active');

-- --------------------------------------------------------

--
-- Table structure for table `sections`
--

CREATE TABLE `sections` (
  `section_id` int(10) UNSIGNED NOT NULL,
  `section_name` varchar(100) NOT NULL,
  `grade_level_id` int(10) UNSIGNED NOT NULL,
  `school_year_id` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sections`
--

INSERT INTO `sections` (`section_id`, `section_name`, `grade_level_id`, `school_year_id`) VALUES
(1, 'Grade 9 - Section A', 3, 1);

-- --------------------------------------------------------

--
-- Table structure for table `special_programs`
--

CREATE TABLE `special_programs` (
  `program_id` int(10) UNSIGNED NOT NULL,
  `program_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `available_slots` int(10) UNSIGNED DEFAULT NULL,
  `status` enum('Active','Inactive') NOT NULL DEFAULT 'Active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `special_programs`
--

INSERT INTO `special_programs` (`program_id`, `program_name`, `description`, `available_slots`, `status`) VALUES
(1, 'Sports', 'Special program for student athletes.', NULL, 'Active'),
(2, 'Arts', 'Special program for students with artistic skills.', NULL, 'Active'),
(3, 'Journalism', 'Special program for student journalists.', NULL, 'Active'),
(4, 'Foreign Language', 'Special program for foreign language learning.', NULL, 'Active');

-- --------------------------------------------------------

--
-- Table structure for table `staff_profiles`
--

CREATE TABLE `staff_profiles` (
  `staff_profile_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `employee_id` varchar(30) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `middle_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) NOT NULL,
  `birth_date` date DEFAULT NULL,
  `contact_number` varchar(30) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `position_title` varchar(100) DEFAULT NULL,
  `department` varchar(100) DEFAULT NULL,
  `employment_type` varchar(50) DEFAULT NULL,
  `hire_date` date DEFAULT NULL,
  `employment_status` enum('Active','On Leave','Resigned','Retired') NOT NULL DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `staff_profiles`
--

INSERT INTO `staff_profiles` (`staff_profile_id`, `user_id`, `employee_id`, `first_name`, `middle_name`, `last_name`, `birth_date`, `contact_number`, `address`, `position_title`, `department`, `employment_type`, `hire_date`, `employment_status`, `created_at`, `updated_at`) VALUES
(1, 3, 'DEMO-TEACH-001', 'Maria', 'Santos', 'Dela Cruz', '1990-05-15', '09171234567', 'Baguio City, Benguet', 'Teacher I', 'English Department', 'Permanent', '0020-06-01', 'Active', '2026-11-18 04:29:45', '2026-11-18 04:29:45');

-- --------------------------------------------------------

--
-- Table structure for table `stipend_records`
--

CREATE TABLE `stipend_records` (
  `stipend_id` int(10) UNSIGNED NOT NULL,
  `scholarship_application_id` int(10) UNSIGNED NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `release_date` date DEFAULT NULL,
  `recipient_name` varchar(150) NOT NULL,
  `claiming_status` enum('Pending Claim','Claimed','Unclaimed') NOT NULL DEFAULT 'Pending Claim',
  `remarks` text DEFAULT NULL,
  `recorded_by` int(10) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `students`
--

CREATE TABLE `students` (
  `student_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `lrn` varchar(20) DEFAULT NULL,
  `first_name` varchar(100) NOT NULL,
  `middle_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) NOT NULL,
  `birth_date` date DEFAULT NULL,
  `birthplace` varchar(150) DEFAULT NULL,
  `sex` enum('Male','Female','Prefer not to say') DEFAULT NULL,
  `civil_status` varchar(40) DEFAULT NULL,
  `nationality` varchar(80) DEFAULT NULL,
  `religion` varchar(100) DEFAULT NULL,
  `contact_number` varchar(30) DEFAULT NULL,
  `current_address` text DEFAULT NULL,
  `permanent_address` text DEFAULT NULL,
  `current_grade_level_id` int(10) UNSIGNED DEFAULT NULL,
  `current_section_id` int(10) UNSIGNED DEFAULT NULL,
  `readmission_status` enum('Eligible','Not Eligible') NOT NULL DEFAULT 'Eligible',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `students`
--

INSERT INTO `students` (`student_id`, `user_id`, `lrn`, `first_name`, `middle_name`, `last_name`, `birth_date`, `birthplace`, `sex`, `civil_status`, `nationality`, `religion`, `contact_number`, `current_address`, `permanent_address`, `current_grade_level_id`, `current_section_id`, `readmission_status`, `created_at`, `updated_at`) VALUES
(1, 1, NULL, 'Juan', 'Dela', 'Cruz', '2006-02-07', 'dito lang', 'Male', 'Single', 'Filipino', 'Catholic', '09683553457', 'dito lang', 'doon lang', 3, 1, 'Eligible', '2026-11-17 15:52:01', '2026-11-18 03:47:26');

-- --------------------------------------------------------

--
-- Table structure for table `student_guardians`
--

CREATE TABLE `student_guardians` (
  `guardian_id` int(10) UNSIGNED NOT NULL,
  `student_id` int(10) UNSIGNED NOT NULL,
  `full_name` varchar(150) NOT NULL,
  `relationship` varchar(60) DEFAULT NULL,
  `contact_number` varchar(30) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `occupation` varchar(150) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `is_primary` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `student_parent_information`
--

CREATE TABLE `student_parent_information` (
  `family_information_id` int(10) UNSIGNED NOT NULL,
  `student_id` int(10) UNSIGNED NOT NULL,
  `mother_name` varchar(150) DEFAULT NULL,
  `mother_occupation` varchar(150) DEFAULT NULL,
  `mother_contact_number` varchar(30) DEFAULT NULL,
  `mother_email` varchar(150) DEFAULT NULL,
  `mother_address` text DEFAULT NULL,
  `father_name` varchar(150) DEFAULT NULL,
  `father_occupation` varchar(150) DEFAULT NULL,
  `father_contact_number` varchar(30) DEFAULT NULL,
  `father_email` varchar(150) DEFAULT NULL,
  `father_address` text DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `student_parent_information`
--

INSERT INTO `student_parent_information` (`family_information_id`, `student_id`, `mother_name`, `mother_occupation`, `mother_contact_number`, `mother_email`, `mother_address`, `father_name`, `father_occupation`, `father_contact_number`, `father_email`, `father_address`, `updated_at`) VALUES
(1, 1, 'mother', 'nurse', '09782445364', 'wowwow@gmail.com', 'edi meow', 'pogi', 'doctor', '09854665768', 'wewwew@gmail.com', 'doon daw', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `student_subject_enrollments`
--

CREATE TABLE `student_subject_enrollments` (
  `subject_enrollment_id` int(10) UNSIGNED NOT NULL,
  `student_id` int(10) UNSIGNED NOT NULL,
  `subject_id` int(10) UNSIGNED NOT NULL,
  `school_year_id` int(10) UNSIGNED NOT NULL,
  `enrollment_status` enum('Enrolled','Dropped','Completed') NOT NULL DEFAULT 'Enrolled',
  `assigned_by` int(10) UNSIGNED DEFAULT NULL,
  `assigned_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `student_subject_enrollments`
--

INSERT INTO `student_subject_enrollments` (`subject_enrollment_id`, `student_id`, `subject_id`, `school_year_id`, `enrollment_status`, `assigned_by`, `assigned_at`) VALUES
(1, 1, 5, 1, 'Enrolled', NULL, '2026-11-17 17:03:52'),
(2, 1, 1, 1, 'Enrolled', NULL, '2026-11-17 17:03:52'),
(3, 1, 6, 1, 'Enrolled', NULL, '2026-11-17 17:03:52'),
(4, 1, 4, 1, 'Enrolled', NULL, '2026-11-17 17:03:52'),
(5, 1, 7, 1, 'Enrolled', NULL, '2026-11-17 17:03:52'),
(6, 1, 2, 1, 'Enrolled', NULL, '2026-11-17 17:03:52'),
(7, 1, 3, 1, 'Enrolled', NULL, '2026-11-17 17:03:52'),
(8, 1, 8, 1, 'Enrolled', NULL, '2026-11-17 17:03:52');

-- --------------------------------------------------------

--
-- Table structure for table `subjects`
--

CREATE TABLE `subjects` (
  `subject_id` int(10) UNSIGNED NOT NULL,
  `subject_code` varchar(30) NOT NULL,
  `subject_name` varchar(150) NOT NULL,
  `grade_level_id` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `subjects`
--

INSERT INTO `subjects` (`subject_id`, `subject_code`, `subject_name`, `grade_level_id`) VALUES
(1, 'ENG9', 'English 9', 3),
(2, 'MATH9', 'Mathematics 9', 3),
(3, 'SCI9', 'Science 9', 3),
(4, 'FIL9', 'Filipino 9', 3),
(5, 'AP9', 'Araling Panlipunan 9', 3),
(6, 'ESP9', 'Values Education 9', 3),
(7, 'MAPEH9', 'MAPEH 9', 3),
(8, 'TLE9', 'Technology and Livelihood Education 9', 3);

-- --------------------------------------------------------

--
-- Table structure for table `teacher_section_assignments`
--

CREATE TABLE `teacher_section_assignments` (
  `assignment_id` int(10) UNSIGNED NOT NULL,
  `teacher_user_id` int(10) UNSIGNED NOT NULL,
  `section_id` int(10) UNSIGNED NOT NULL,
  `school_year_id` int(10) UNSIGNED NOT NULL,
  `assigned_by` int(10) UNSIGNED DEFAULT NULL,
  `assigned_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `teacher_section_assignments`
--

INSERT INTO `teacher_section_assignments` (`assignment_id`, `teacher_user_id`, `section_id`, `school_year_id`, `assigned_by`, `assigned_at`) VALUES
(1, 3, 1, 1, 2, '2026-11-18 03:52:35');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(10) UNSIGNED NOT NULL,
  `role_id` int(10) UNSIGNED NOT NULL,
  `username` varchar(60) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `account_status` enum('Active','Inactive','Locked') NOT NULL DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `role_id`, `username`, `email`, `password_hash`, `account_status`, `created_at`, `updated_at`) VALUES
(1, 6, 'juantest01', 'juan.test01@example.com', '$2y$10$UyV1ahvEPLN.o4s.oXkuneGeEiQdg3UxOTPR2RbPawTXrH3.Gqf0q', 'Active', '2026-11-17 15:52:01', NULL),
(2, 1, 'cityhighadmin', 'cityhigh@gmail.com', '$2y$10$VGA8RD77EoD.VsOL7x2c4en4ArnrMrw7MBF0mswF.36el6WpMP2tS', 'Active', '2026-11-18 03:20:00', NULL),
(3, 3, 'Manalo', 'ManaloMatalo@gmail.com', '$2y$10$A3Z4ydtbhlg5zmffswCwO.ZXXsICmhhEIrgsh6BmTOiRhInYD.0tK', 'Active', '2026-11-18 03:51:59', NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `academic_records`
--
ALTER TABLE `academic_records`
  ADD PRIMARY KEY (`record_id`),
  ADD KEY `fk_academic_student` (`student_id`),
  ADD KEY `fk_academic_subject` (`subject_id`),
  ADD KEY `fk_academic_year` (`school_year_id`),
  ADD KEY `fk_academic_recorder` (`recorded_by`);

--
-- Indexes for table `announcements`
--
ALTER TABLE `announcements`
  ADD PRIMARY KEY (`announcement_id`),
  ADD KEY `fk_announcement_creator` (`created_by`);

--
-- Indexes for table `attendance_records`
--
ALTER TABLE `attendance_records`
  ADD PRIMARY KEY (`attendance_id`),
  ADD KEY `fk_attendance_subject` (`subject_id`),
  ADD KEY `fk_attendance_recorder` (`recorded_by`),
  ADD KEY `idx_attendance_student_date` (`student_id`,`attendance_date`);

--
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`audit_id`),
  ADD KEY `fk_audit_user` (`user_id`),
  ADD KEY `idx_audit_created` (`created_at`);

--
-- Indexes for table `enrollment_applications`
--
ALTER TABLE `enrollment_applications`
  ADD PRIMARY KEY (`application_id`),
  ADD UNIQUE KEY `uq_student_enrollment_year` (`student_id`,`school_year_id`),
  ADD KEY `fk_application_year` (`school_year_id`),
  ADD KEY `fk_application_grade` (`requested_grade_level_id`),
  ADD KEY `fk_application_section` (`assigned_section_id`),
  ADD KEY `fk_application_reviewer` (`reviewed_by`);

--
-- Indexes for table `enrollment_documents`
--
ALTER TABLE `enrollment_documents`
  ADD PRIMARY KEY (`document_id`),
  ADD UNIQUE KEY `uq_application_document` (`application_id`,`document_type`);

--
-- Indexes for table `grade_levels`
--
ALTER TABLE `grade_levels`
  ADD PRIMARY KEY (`grade_level_id`),
  ADD UNIQUE KEY `grade_level` (`grade_level`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`role_id`),
  ADD UNIQUE KEY `role_name` (`role_name`);

--
-- Indexes for table `scholarship_applications`
--
ALTER TABLE `scholarship_applications`
  ADD PRIMARY KEY (`scholarship_application_id`),
  ADD UNIQUE KEY `uq_student_program_year` (`student_id`,`program_id`,`school_year_id`),
  ADD KEY `fk_scholarship_program` (`program_id`),
  ADD KEY `fk_scholarship_year` (`school_year_id`),
  ADD KEY `fk_scholarship_reviewer` (`reviewed_by`);

--
-- Indexes for table `scholarship_monitoring`
--
ALTER TABLE `scholarship_monitoring`
  ADD PRIMARY KEY (`monitoring_id`),
  ADD KEY `fk_monitoring_application` (`scholarship_application_id`);

--
-- Indexes for table `school_years`
--
ALTER TABLE `school_years`
  ADD PRIMARY KEY (`school_year_id`),
  ADD UNIQUE KEY `school_year` (`school_year`);

--
-- Indexes for table `sections`
--
ALTER TABLE `sections`
  ADD PRIMARY KEY (`section_id`),
  ADD UNIQUE KEY `uq_section_year` (`section_name`,`grade_level_id`,`school_year_id`),
  ADD KEY `fk_sections_grade` (`grade_level_id`),
  ADD KEY `fk_sections_year` (`school_year_id`);

--
-- Indexes for table `special_programs`
--
ALTER TABLE `special_programs`
  ADD PRIMARY KEY (`program_id`),
  ADD UNIQUE KEY `program_name` (`program_name`);

--
-- Indexes for table `staff_profiles`
--
ALTER TABLE `staff_profiles`
  ADD PRIMARY KEY (`staff_profile_id`),
  ADD UNIQUE KEY `employee_id` (`employee_id`),
  ADD UNIQUE KEY `uq_staff_profile_user` (`user_id`);

--
-- Indexes for table `stipend_records`
--
ALTER TABLE `stipend_records`
  ADD PRIMARY KEY (`stipend_id`),
  ADD KEY `fk_stipend_application` (`scholarship_application_id`),
  ADD KEY `fk_stipend_recorder` (`recorded_by`);

--
-- Indexes for table `students`
--
ALTER TABLE `students`
  ADD PRIMARY KEY (`student_id`),
  ADD UNIQUE KEY `user_id` (`user_id`),
  ADD UNIQUE KEY `lrn` (`lrn`),
  ADD KEY `fk_students_grade` (`current_grade_level_id`),
  ADD KEY `fk_students_section` (`current_section_id`);

--
-- Indexes for table `student_guardians`
--
ALTER TABLE `student_guardians`
  ADD PRIMARY KEY (`guardian_id`),
  ADD KEY `idx_guardian_student` (`student_id`);

--
-- Indexes for table `student_parent_information`
--
ALTER TABLE `student_parent_information`
  ADD PRIMARY KEY (`family_information_id`),
  ADD UNIQUE KEY `student_id` (`student_id`);

--
-- Indexes for table `student_subject_enrollments`
--
ALTER TABLE `student_subject_enrollments`
  ADD PRIMARY KEY (`subject_enrollment_id`),
  ADD UNIQUE KEY `uq_student_subject_year` (`student_id`,`subject_id`,`school_year_id`),
  ADD KEY `fk_subject_enrollment_subject` (`subject_id`),
  ADD KEY `fk_subject_enrollment_year` (`school_year_id`),
  ADD KEY `fk_subject_enrollment_assigner` (`assigned_by`);

--
-- Indexes for table `subjects`
--
ALTER TABLE `subjects`
  ADD PRIMARY KEY (`subject_id`),
  ADD UNIQUE KEY `subject_code` (`subject_code`),
  ADD KEY `fk_subject_grade` (`grade_level_id`);

--
-- Indexes for table `teacher_section_assignments`
--
ALTER TABLE `teacher_section_assignments`
  ADD PRIMARY KEY (`assignment_id`),
  ADD UNIQUE KEY `uq_teacher_section_year` (`teacher_user_id`,`section_id`,`school_year_id`),
  ADD KEY `fk_teacher_assignment_section` (`section_id`),
  ADD KEY `fk_teacher_assignment_year` (`school_year_id`),
  ADD KEY `fk_teacher_assignment_admin` (`assigned_by`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `fk_users_role` (`role_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `academic_records`
--
ALTER TABLE `academic_records`
  MODIFY `record_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=40;

--
-- AUTO_INCREMENT for table `announcements`
--
ALTER TABLE `announcements`
  MODIFY `announcement_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `attendance_records`
--
ALTER TABLE `attendance_records`
  MODIFY `attendance_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `audit_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `enrollment_applications`
--
ALTER TABLE `enrollment_applications`
  MODIFY `application_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `enrollment_documents`
--
ALTER TABLE `enrollment_documents`
  MODIFY `document_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `grade_levels`
--
ALTER TABLE `grade_levels`
  MODIFY `grade_level_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `role_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `scholarship_applications`
--
ALTER TABLE `scholarship_applications`
  MODIFY `scholarship_application_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `scholarship_monitoring`
--
ALTER TABLE `scholarship_monitoring`
  MODIFY `monitoring_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `school_years`
--
ALTER TABLE `school_years`
  MODIFY `school_year_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `sections`
--
ALTER TABLE `sections`
  MODIFY `section_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `special_programs`
--
ALTER TABLE `special_programs`
  MODIFY `program_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `staff_profiles`
--
ALTER TABLE `staff_profiles`
  MODIFY `staff_profile_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `stipend_records`
--
ALTER TABLE `stipend_records`
  MODIFY `stipend_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `students`
--
ALTER TABLE `students`
  MODIFY `student_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `student_guardians`
--
ALTER TABLE `student_guardians`
  MODIFY `guardian_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `student_parent_information`
--
ALTER TABLE `student_parent_information`
  MODIFY `family_information_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `student_subject_enrollments`
--
ALTER TABLE `student_subject_enrollments`
  MODIFY `subject_enrollment_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `subjects`
--
ALTER TABLE `subjects`
  MODIFY `subject_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `teacher_section_assignments`
--
ALTER TABLE `teacher_section_assignments`
  MODIFY `assignment_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `academic_records`
--
ALTER TABLE `academic_records`
  ADD CONSTRAINT `fk_academic_recorder` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_academic_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`),
  ADD CONSTRAINT `fk_academic_subject` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`subject_id`),
  ADD CONSTRAINT `fk_academic_year` FOREIGN KEY (`school_year_id`) REFERENCES `school_years` (`school_year_id`);

--
-- Constraints for table `announcements`
--
ALTER TABLE `announcements`
  ADD CONSTRAINT `fk_announcement_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `attendance_records`
--
ALTER TABLE `attendance_records`
  ADD CONSTRAINT `fk_attendance_recorder` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_attendance_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`),
  ADD CONSTRAINT `fk_attendance_subject` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`subject_id`);

--
-- Constraints for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD CONSTRAINT `fk_audit_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `enrollment_applications`
--
ALTER TABLE `enrollment_applications`
  ADD CONSTRAINT `fk_application_grade` FOREIGN KEY (`requested_grade_level_id`) REFERENCES `grade_levels` (`grade_level_id`),
  ADD CONSTRAINT `fk_application_reviewer` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_application_section` FOREIGN KEY (`assigned_section_id`) REFERENCES `sections` (`section_id`),
  ADD CONSTRAINT `fk_application_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`),
  ADD CONSTRAINT `fk_application_year` FOREIGN KEY (`school_year_id`) REFERENCES `school_years` (`school_year_id`);

--
-- Constraints for table `enrollment_documents`
--
ALTER TABLE `enrollment_documents`
  ADD CONSTRAINT `fk_document_application` FOREIGN KEY (`application_id`) REFERENCES `enrollment_applications` (`application_id`) ON DELETE CASCADE;

--
-- Constraints for table `scholarship_applications`
--
ALTER TABLE `scholarship_applications`
  ADD CONSTRAINT `fk_scholarship_program` FOREIGN KEY (`program_id`) REFERENCES `special_programs` (`program_id`),
  ADD CONSTRAINT `fk_scholarship_reviewer` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_scholarship_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`),
  ADD CONSTRAINT `fk_scholarship_year` FOREIGN KEY (`school_year_id`) REFERENCES `school_years` (`school_year_id`);

--
-- Constraints for table `scholarship_monitoring`
--
ALTER TABLE `scholarship_monitoring`
  ADD CONSTRAINT `fk_monitoring_application` FOREIGN KEY (`scholarship_application_id`) REFERENCES `scholarship_applications` (`scholarship_application_id`) ON DELETE CASCADE;

--
-- Constraints for table `sections`
--
ALTER TABLE `sections`
  ADD CONSTRAINT `fk_sections_grade` FOREIGN KEY (`grade_level_id`) REFERENCES `grade_levels` (`grade_level_id`),
  ADD CONSTRAINT `fk_sections_year` FOREIGN KEY (`school_year_id`) REFERENCES `school_years` (`school_year_id`);

--
-- Constraints for table `staff_profiles`
--
ALTER TABLE `staff_profiles`
  ADD CONSTRAINT `fk_staff_profile_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `stipend_records`
--
ALTER TABLE `stipend_records`
  ADD CONSTRAINT `fk_stipend_application` FOREIGN KEY (`scholarship_application_id`) REFERENCES `scholarship_applications` (`scholarship_application_id`),
  ADD CONSTRAINT `fk_stipend_recorder` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `students`
--
ALTER TABLE `students`
  ADD CONSTRAINT `fk_students_grade` FOREIGN KEY (`current_grade_level_id`) REFERENCES `grade_levels` (`grade_level_id`),
  ADD CONSTRAINT `fk_students_section` FOREIGN KEY (`current_section_id`) REFERENCES `sections` (`section_id`),
  ADD CONSTRAINT `fk_students_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `student_guardians`
--
ALTER TABLE `student_guardians`
  ADD CONSTRAINT `fk_guardian_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE;

--
-- Constraints for table `student_parent_information`
--
ALTER TABLE `student_parent_information`
  ADD CONSTRAINT `fk_parent_info_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE;

--
-- Constraints for table `student_subject_enrollments`
--
ALTER TABLE `student_subject_enrollments`
  ADD CONSTRAINT `fk_subject_enrollment_assigner` FOREIGN KEY (`assigned_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_subject_enrollment_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_subject_enrollment_subject` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`subject_id`),
  ADD CONSTRAINT `fk_subject_enrollment_year` FOREIGN KEY (`school_year_id`) REFERENCES `school_years` (`school_year_id`);

--
-- Constraints for table `subjects`
--
ALTER TABLE `subjects`
  ADD CONSTRAINT `fk_subject_grade` FOREIGN KEY (`grade_level_id`) REFERENCES `grade_levels` (`grade_level_id`);

--
-- Constraints for table `teacher_section_assignments`
--
ALTER TABLE `teacher_section_assignments`
  ADD CONSTRAINT `fk_teacher_assignment_admin` FOREIGN KEY (`assigned_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_teacher_assignment_section` FOREIGN KEY (`section_id`) REFERENCES `sections` (`section_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_teacher_assignment_user` FOREIGN KEY (`teacher_user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_teacher_assignment_year` FOREIGN KEY (`school_year_id`) REFERENCES `school_years` (`school_year_id`) ON DELETE CASCADE;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `fk_users_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`role_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
