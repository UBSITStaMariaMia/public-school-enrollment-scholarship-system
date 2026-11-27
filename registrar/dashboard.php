<?php
require_once __DIR__ . '/../includes/bootstrap.php';

require_role('School Registrar');

$user = current_user();
$registrarName = $user['username'] ?? 'Registrar';

$pageTitle = 'Registrar Dashboard';

$dashboardLinks = [
    ['Student Registration', '/registrar/student_registration.php', 'new-enrollment', 'Create a student portal account at the registrar’s office.'],
    ['Enrollment Review', '/registrar/enrollment_review.php', 'enrollment', 'Review applications and verify supporting documents.'],
    ['Student Records', '/registrar/student_records.php', 'student-record', 'Find student profiles, grades, and attendance records.'],
    ['Section Management', '/registrar/sections.php', 'section', 'Create class sections and organize them by grade.'],
    ['Enrollment Reports', '/registrar/enrollment_reports.php', 'report', 'View application totals by status, grade, and school year.'],
    ['Announcements', '/registrar/announcements.php', 'announcement', 'Publish and manage notices for the school community.'],
];
$dashboardHeading = 'Welcome, ' . $registrarName;

require_once __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/dashboard_shortcuts.php';
require_once __DIR__ . '/../includes/footer.php';
