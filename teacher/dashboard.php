<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('Teacher');

$pageTitle = 'Teacher Dashboard';
$dashboardHeading = 'Teacher Dashboard';
$dashboardIntro = 'View your assigned sections, manage subjects, and record grades and attendance.';
$dashboardLinks = require __DIR__ . '/../includes/teacher_navigation.php';

require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/dashboard_shortcuts.php';
require __DIR__ . '/../includes/footer.php';
