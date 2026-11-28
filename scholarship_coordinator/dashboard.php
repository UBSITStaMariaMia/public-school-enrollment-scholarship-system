<?php
require_once __DIR__ . '/../includes/bootstrap.php';

require_role('System Administrator', 'Scholarship Coordinator');

$pageTitle = 'Scholarship Coordinator Dashboard';

$dashboardLinks = require __DIR__ . '/../includes/scholarship_navigation.php';
$dashboardIntro = 'Review applicants, schedule screenings, and track scholars and stipend releases.';
$dashboardHeading = 'Scholarship Coordinator Dashboard';

require_once __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/dashboard_shortcuts.php';
require_once __DIR__ . '/../includes/footer.php';
