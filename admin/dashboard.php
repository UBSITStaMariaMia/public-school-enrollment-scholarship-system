<?php
require_once __DIR__ . '/../includes/bootstrap.php';

require_role('System Administrator');

$pageTitle = 'System Administrator Dashboard';

$dashboardLinks = [
    ['User Management', '/admin/user_management.php', 'users', 'Manage portal accounts and role access.'],
    ['Student Records', '/admin/student_records.php', 'student-record', 'Find student profiles, grades, and attendance records.'],
    ['Staff Records', '/admin/staff_records.php', 'staff-record', 'Find school personnel and review their records.'],
];
$dashboardHeading = 'System Administrator Dashboard';

require_once __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/dashboard_shortcuts.php';
require_once __DIR__ . '/../includes/footer.php';
