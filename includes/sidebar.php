
<?php
$sidebarUser = $user ?? current_user();
$sidebarRole = $sidebarUser['role_name'] ?? '';
$sidebarUserId = (int) ($sidebarUser['user_id'] ?? 0);
$sidebarPage = basename($_SERVER['PHP_SELF'] ?? '');

$sidebarIcons = [
    'dashboard' => '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M3 10.5 12 4l9 6.5V20H3z"/><path d="M8 20v-6h8v6M7 10h.01M12 10h.01M17 10h.01"/></svg>',
    'users' => '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><circle cx="9" cy="8" r="3"/><path d="M3.5 19c.5-3.1 2.3-4.8 5.5-4.8s5 1.7 5.5 4.8M16 5.5a3 3 0 0 1 0 5.8M17 14.5c2 .5 3.1 2 3.5 4.5"/></svg>',
    'student-record' => '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><rect x="3.5" y="3.5" width="17" height="17" rx="2"/><circle cx="9" cy="9" r="2.2"/><path d="M5.8 16c.7-1.7 1.8-2.5 3.2-2.5s2.5.8 3.2 2.5M14.5 8h3M14.5 11h3M14.5 14h3"/></svg>',
    'staff-record' => '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M4 20V7.5L12 4l8 3.5V20"/><path d="M8 20v-5h8v5M8 9h.01M12 9h.01M16 9h.01M9 12h6"/></svg>',
    'enrollment' => '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M6 3.5h8l4 4V20H6z"/><path d="M14 3.5v4h4M9 12h6M9 15h4"/><path d="m14.5 17 1.5 1.5 3-3"/></svg>',
    'document-check' => '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M5 3.5h9l5 5V13M14 3.5v5h5M5 3.5V21h8"/><path d="M8 12h6M8 15h4"/><circle cx="17" cy="17" r="3.5"/><path d="m15.5 17 1 1 2-2"/></svg>',
    'section' => '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><rect x="8.5" y="3" width="7" height="5" rx="1"/><rect x="2.5" y="16" width="7" height="5" rx="1"/><rect x="14.5" y="16" width="7" height="5" rx="1"/><path d="M12 8v4M6 16v-4h12v4"/></svg>',
    'report' => '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M4 20V5M4 20h17"/><path d="M7.5 16v-4M12 16V8M16.5 16V5M20 16v-7"/><path d="m7 8 4-3 4 1 4-3"/></svg>',
    'announcement' => '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M4 10v4h3l10 4V6L7 10H4Z"/><path d="M7 14l2 5h3l-2-4M20 9.5a4 4 0 0 1 0 5"/></svg>',
    'subject' => '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M4 5.5c3.2-1.2 5.8-.7 8 1.2 2.2-1.9 4.8-2.4 8-1.2V19c-3.2-1.2-5.8-.7-8 1.2-2.2-1.9-4.8-2.4-8-1.2z"/><path d="M12 6.7v13.5M7 9h2M7 12h2M15 9h2M15 12h2"/></svg>',
    'classroom' => '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M3 20V5h18v15M3 9h18M7 5v4M17 5v4"/><path d="M7 13h4v7M15 13h2v2h-2zM14 20v-3h4v3"/></svg>',
    'new-enrollment' => '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M5 3.5h10l4 4V20H5z"/><path d="M15 3.5v4h4M8 12h8M8 15h4"/><path d="M17 15v5M14.5 17.5h5"/></svg>',
    'applications' => '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M4 7h16v13H4zM7 4h10v3H7z"/><path d="M8 11h8M8 14h5M8 17h7"/></svg>',
    'profile' => '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><circle cx="12" cy="8" r="3.2"/><path d="M5 20c.6-4 2.9-6 7-6s6.4 2 7 6"/><path d="M18 4.5h2.5M19.25 3.25v2.5"/></svg>',
    'password' => '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3M12 14v3"/></svg>',
    'graduation' => '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="m2 9 10-5 10 5-10 5zM6 11v6c4 3 8 3 12 0v-6M22 9v6"/></svg>',
    'shield' => '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M12 3 4.5 6v5.5c0 4.3 3.3 7.9 7.5 9.5 4.2-1.6 7.5-5.2 7.5-9.5V6zM8.5 11.5l2.5 2.5 4.5-5"/></svg>',
    'award' => '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><circle cx="12" cy="9" r="5.5"/><path d="m8 13-2 8 6-3 6 3-2-8m-6-4 1.5 1.5L14 8"/></svg>',
    'logout' => '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M10 4H5v16h5M14 8l4 4-4 4M8 12h10"/></svg>',
];

$sidebarRoleIcon = match ($sidebarRole) {
    'School Registrar' => 'student-record',
    'Student' => 'graduation',
    'Teacher' => 'subject',
    'System Administrator' => 'shield',
    'Scholarship Coordinator' => 'award',
    'Enrollment Committee' => 'users',
    default => null
};

$sidebarLinks = [];
$sidebarHeading = 'Navigation';
$sidebarDisplayName = '';

if ($sidebarRole === 'Student' && $sidebarUserId > 0) {
    $nameQuery = $pdo->prepare('SELECT first_name, middle_name, last_name FROM students WHERE user_id = :user_id LIMIT 1');
    $nameQuery->execute(['user_id' => $sidebarUserId]);
    $profileName = $nameQuery->fetch(PDO::FETCH_ASSOC);

    if ($profileName) {
        $middleInitial = '';

        if (!empty($profileName['middle_name'])) {
            $middleName = trim($profileName['middle_name']);
            $middleInitial = (function_exists('mb_substr') ? mb_substr($middleName, 0, 1, 'UTF-8') : substr($middleName, 0, 1)) . '.';
        }

        $sidebarDisplayName = trim($profileName['first_name'] . ' ' . ($middleInitial !== '' ? $middleInitial . ' ' : '') . $profileName['last_name']);
    }
} elseif ($sidebarRole === 'Teacher' && $sidebarUserId > 0) {
    $nameQuery = $pdo->prepare('SELECT first_name, middle_name, last_name FROM staff_profiles WHERE user_id = :user_id LIMIT 1');
    $nameQuery->execute(['user_id' => $sidebarUserId]);
    $profileName = $nameQuery->fetch(PDO::FETCH_ASSOC);

    if ($profileName) {
        $middleInitial = '';

        if (!empty($profileName['middle_name'])) {
            $middleName = trim($profileName['middle_name']);
            $middleInitial = (function_exists('mb_substr') ? mb_substr($middleName, 0, 1, 'UTF-8') : substr($middleName, 0, 1)) . '.';
        }

        $sidebarDisplayName = trim($profileName['first_name'] . ' ' . ($middleInitial !== '' ? $middleInitial . ' ' : '') . $profileName['last_name']);
    }
}

if ($sidebarDisplayName === '') {
    $sidebarDisplayName = $sidebarUser['username'] ?? $sidebarRole;
}

$sidebarAdd = static function (string $label, string $url, string $page, string $icon) use (&$sidebarLinks): void {
    $sidebarLinks[] = ['label' => $label, 'url' => $url, 'pages' => [$page], 'icon' => $icon];
};

if ($sidebarRole === 'System Administrator') {
    $sidebarHeading = 'System Administrator';
    $sidebarAdd('Dashboard', '/admin/dashboard.php', 'dashboard.php', 'dashboard');
    $sidebarAdd('User Management', '/admin/user_management.php', 'user_management.php', 'users');
    $sidebarAdd('Student Records', '/admin/student_records.php', 'student_records.php', 'student-record');
    $sidebarAdd('Teacher and Staff Records', '/admin/staff_records.php', 'staff_records.php', 'staff-record');

} elseif ($sidebarRole === 'School Registrar') {
    $sidebarHeading = 'School Registrar';
    $sidebarAdd('Dashboard', '/registrar/dashboard.php', 'dashboard.php', 'dashboard');
    $sidebarAdd('Student Registration', '/registrar/student_registration.php', 'student_registration.php', 'new-enrollment');
    $sidebarAdd('Enrollment Review', '/registrar/enrollment_review.php', 'enrollment_review.php', 'enrollment');
    $sidebarAdd('Student Records', '/registrar/student_records.php', 'student_records.php', 'student-record');
    $sidebarAdd('Section Management', '/registrar/sections.php', 'sections.php', 'section');
    $sidebarAdd('Enrollment Reports', '/registrar/enrollment_reports.php', 'enrollment_reports.php', 'report');
    $sidebarAdd('Announcements', '/registrar/announcements.php', 'announcements.php', 'announcement');

} elseif ($sidebarRole === 'Teacher') {
    $sidebarHeading = $sidebarDisplayName;
    $sidebarAdd('Dashboard', '/staff/dashboard.php', 'dashboard.php', 'dashboard');
    $sidebarAdd('Subject Management', '/staff/subjects.php', 'subjects.php', 'subject');
    $sidebarAdd('My Assigned Sections', '/staff/my_sections.php', 'my_sections.php', 'classroom');
    $sidebarAdd('Announcements', '/announcements.php', 'announcements.php', 'announcement');

} elseif ($sidebarRole === 'Student') {
    $sidebarHeading = $sidebarDisplayName;
    $sidebarAdd('Dashboard', '/student/dashboard.php', 'dashboard.php', 'dashboard');
    $sidebarAdd('New Enrollment', '/student/new_enrollment.php', 'new_enrollment.php', 'new-enrollment');
    $sidebarAdd('My Applications', '/student/my_applications.php', 'my_applications.php', 'applications');
    $sidebarAdd('Announcements', '/announcements.php', 'announcements.php', 'announcement');
    $sidebarAdd('My Profile', '/student/profile.php', 'profile.php', 'profile');
    $sidebarAdd('Change Password', '/student/change_password.php', 'change_password.php', 'password');

} elseif ($sidebarRole === 'Enrollment Committee') {
    $sidebarHeading = $sidebarRole;
    $sidebarAdd('Dashboard', '/staff/dashboard.php', 'dashboard.php', 'dashboard');
    $sidebarAdd('Subject Management', '/staff/subjects.php', 'subjects.php', 'subject');
    $sidebarAdd('Student Records', '/staff/student_records.php', 'student_records.php', 'student-record');
    $sidebarAdd('Staff Records', '/staff/staff_records.php', 'staff_records.php', 'staff-record');
    $sidebarAdd('Teacher Assignments', '/staff/teacher_assignments.php', 'teacher_assignments.php', 'classroom');
    $sidebarAdd('Reports', '/staff/reports.php', 'reports.php', 'report');
    $sidebarAdd('Announcements', '/announcements.php', 'announcements.php', 'announcement');

} elseif ($sidebarRole === 'Scholarship Coordinator') {
    $sidebarHeading = $sidebarRole;
    $sidebarAdd('Dashboard', '/scholarship_coordinator/dashboard.php', 'dashboard.php', 'dashboard');
    $sidebarAdd('Scholarship Programs', '/scholarship_coordinator/scholarship_programs.php', 'scholarship_programs.php', 'applications');
    $sidebarAdd('Scholarship Applications', '/scholarship_coordinator/scholarship_applications.php', 'scholarship_applications.php', 'applications');
    $sidebarAdd('Scholarship Monitoring', '/scholarship_coordinator/scholarship_monitoring.php', 'scholarship_monitoring.php', 'report');
    $sidebarAdd('Stipend Records', '/scholarship_coordinator/stipend_records.php', 'stipend_records.php', 'report');
    $sidebarAdd('Scholar Masterlist', '/scholarship_coordinator/scholarship_beneficiaries_report.php', 'scholarship_beneficiaries_report.php', 'student-record');
    $sidebarAdd('Renewal Report', '/scholarship_coordinator/scholarship_renewal_report.php', 'scholarship_renewal_report.php', 'report');
    $sidebarAdd('Announcements', '/announcements.php', 'announcements.php', 'announcement');
} else {
    $sidebarAdd('Home', '/index.php', 'index.php', 'dashboard');
    $sidebarAdd('Announcements', '/announcements.php', 'announcements.php', 'announcement');
    $sidebarAdd('Log In', '/login.php', 'login.php', 'profile');
    $sidebarAdd('How to Register?', '/register.php', 'register.php', 'new-enrollment');
}

$sidebarRelatedPages = [
    'enrollment_review.php' => ['review_application.php'],
    'my_applications.php' => ['edit_enrollment.php', 'scholarship_application.php'],
    'scholarship_programs.php' => ['add_scholarship_program.php', 'edit_scholarship_program.php'],
    'student_records.php' => ['student_records_report.php'],
    'staff_records.php' => ['staff_records_report.php'],
];
?>

<aside class="main-sidebar" id="mainSidebar" aria-label="Portal menu" aria-hidden="true" inert>
    <div class="main-sidebar-header sidebar-heading">
        <div class="sidebar-role-identity">
            <?php if ($sidebarRoleIcon): ?>
                <span class="sidebar-nav-icon sidebar-role-icon" aria-hidden="true"><?= $sidebarIcons[$sidebarRoleIcon] ?></span>
            <?php endif; ?>
            <div class="sidebar-identity-text">
                <h2><?= e($sidebarHeading) ?></h2>
                <?php if ($sidebarUser && $sidebarHeading !== $sidebarRole): ?>
                    <p><?= e($sidebarRole) ?></p>
                <?php endif; ?>
            </div>
        </div>
        <button type="button" class="sidebar-compact-toggle" id="sidebarCompactToggle"
                aria-label="Collapse navigation" title="Collapse navigation"
                aria-controls="portalNavigationLinks" aria-expanded="true">
            <span aria-hidden="true">&lsaquo;</span>
        </button>
        <button type="button" class="main-sidebar-close" id="mainSidebarClose" aria-label="Close navigation menu">&times;</button>
    </div>

    <nav class="main-sidebar-nav" id="portalNavigationLinks" aria-label="Portal navigation">
        <?php foreach ($sidebarLinks as $link): ?>
            <?php
            $sidebarLinkPage = $link['pages'][0];
            $isCurrentPage = $sidebarPage === $sidebarLinkPage;
            $isActive = $isCurrentPage || in_array($sidebarPage, $sidebarRelatedPages[$sidebarLinkPage] ?? [], true);
            ?>
            <a href="<?= BASE_URL . $link['url'] ?>" class="sidebar-nav-link <?= $isActive ? 'active' : '' ?>"
               aria-label="<?= e($link['label']) ?>" title="<?= e($link['label']) ?>"
               <?= $isActive ? 'aria-current="' . ($isCurrentPage ? 'page' : 'location') . '"' : '' ?>>
                <span class="sidebar-nav-icon"><?= $sidebarIcons[$link['icon']] ?></span>
                <span class="sidebar-nav-text"><?= e($link['label']) ?></span>
            </a>
        <?php endforeach; ?>
    </nav>

    <?php if ($sidebarUser): ?>
        <div class="main-sidebar-footer">
            <a href="#" class="nav-logout" data-logout-trigger aria-label="Log Out" title="Log Out">
                <span class="sidebar-nav-icon"><?= $sidebarIcons['logout'] ?></span>
                <span class="sidebar-nav-text">Log Out</span>
            </a>
        </div>
    <?php endif; ?>
</aside>
