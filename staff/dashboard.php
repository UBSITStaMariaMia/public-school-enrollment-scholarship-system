<?php
require_once __DIR__ . '/../includes/bootstrap.php';

require_role(
    'System Administrator',
    'School Registrar',
    'Teacher',
    'Enrollment Committee',
    'Scholarship Coordinator'
);

$pageTitle = 'Staff Dashboard';
$user = current_user();
$dashboardLinks = match ($user['role_name']) {
    'System Administrator' => [
        ['User Management', '/staff/users.php', 'users', 'Manage portal accounts and role access.'],
        ['Subject Management', '/staff/subjects.php', 'subject', 'Manage subjects and student subject assignments.'],
        ['Student Records Management', '/staff/student_records.php', 'student-record', 'Review records for the students you can manage.'],
        ['Staff Records Management', '/staff/staff_records.php', 'staff-record', 'Manage school personnel information.'],
        ['Announcements', '/announcements.php', 'announcement', 'Read school updates and notices.'],
    ],
    'Teacher' => [
        ['Subject Management', '/staff/subjects.php', 'subject', 'Manage subjects and student subject assignments.'],
        ['Student Records Management', '/staff/student_records.php', 'student-record', 'Review records for the students you can manage.'],
        ['Announcements', '/announcements.php', 'announcement', 'Read school updates and notices.'],
    ],
    default => [
        ['Announcements', '/announcements.php', 'announcement', 'Read school updates and notices.'],
    ],
};
$dashboardHeading = $user['role_name'] . ' Dashboard';

require_once __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/dashboard_shortcuts.php';
require_once __DIR__ . '/../includes/footer.php';
