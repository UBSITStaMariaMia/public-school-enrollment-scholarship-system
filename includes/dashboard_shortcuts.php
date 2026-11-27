<?php
$dashboardIntro = match ($sidebarRole) {
    'School Registrar' => 'Register students, review enrollment applications, and manage class sections for the school year.',
    'Student' => 'Apply for enrollment or a scholarship, check application updates, and view your school records.',
    'Teacher' => 'Manage subjects and review the academic records of students in your assigned sections.',
    'System Administrator' => 'Manage portal accounts and look up records for students and school personnel.',
    'Scholarship Coordinator' => 'Review scholarship applications, monitor scholars, and track stipend releases.',
    default => 'Check the latest announcements and access your staff tools.'
};
?>
<div class="portal-dashboard">
    <header class="portal-dashboard-heading">
        <div>
            <h1><?= e($dashboardHeading) ?></h1>
            <p><?= e($dashboardIntro) ?></p>
        </div>
        <?php if ($sidebarRoleIcon && $sidebarRole !== 'School Registrar'): ?>
            <span class="sidebar-nav-icon portal-dashboard-role-mark" aria-hidden="true"><?= $sidebarIcons[$sidebarRoleIcon] ?></span>
        <?php endif; ?>
    </header>
    <nav aria-label="Dashboard shortcuts">
        <ul class="portal-dashboard-links" role="list">
            <?php foreach ($dashboardLinks as [$label, $path, $icon, $description]): ?>
                <li>
                    <a class="portal-dashboard-link" href="<?= e(BASE_URL . $path) ?>">
                        <span class="portal-dashboard-card-top" aria-hidden="true">
                            <span class="sidebar-nav-icon"><?= $sidebarIcons[$icon] ?></span>
                            <span class="portal-dashboard-arrow">&rarr;</span>
                        </span>
                        <h2><?= e($label) ?></h2>
                        <p><?= e($description) ?></p>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </nav>
</div>
