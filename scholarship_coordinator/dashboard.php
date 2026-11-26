<?php
require_once __DIR__ . '/../includes/bootstrap.php';

require_role('System Administrator', 'Scholarship Coordinator');

$pageTitle = 'Scholarship Coordinator Dashboard';

$dashboardLinks = [
    ['Scholarship Programs', '/scholarship_coordinator/scholarship_programs.php', 'award', 'Manage programs, availability, and slots.'],
    ['Scholarship Applications', '/scholarship_coordinator/scholarship_applications.php', 'applications', 'Review applicants and schedule screenings.'],
    ['Scholarship Monitoring', '/scholarship_coordinator/scholarship_monitoring.php', 'report', 'Track participation and renewal eligibility.'],
    ['Stipend Records', '/scholarship_coordinator/stipend_records.php', 'report', 'Record releases and check claiming status.'],
    ['Scholar Masterlist', '/scholarship_coordinator/scholarship_beneficiaries_report.php', 'student-record', 'Find approved scholars and their program details.'],
    ['Renewal Report', '/scholarship_coordinator/scholarship_renewal_report.php', 'report', 'Review scholars’ renewal eligibility and monitoring status.'],
    ['Announcements', '/announcements.php', 'announcement', 'Read school updates and notices.'],
];
$dashboardHeading = 'Scholarship Coordinator Dashboard';

require_once __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/dashboard_shortcuts.php';
require_once __DIR__ . '/../includes/footer.php';
