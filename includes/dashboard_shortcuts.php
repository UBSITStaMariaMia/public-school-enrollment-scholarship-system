<div class="portal-dashboard">
    <header class="portal-dashboard-heading">
        <div>
            <h1><?= e($dashboardHeading) ?></h1>
            <?php if (!empty($dashboardIntro)): ?>
                <p><?= e($dashboardIntro) ?></p>
            <?php endif; ?>
        </div>
        <?php if ($sidebarRoleIcon && !in_array($sidebarRole, ['School Registrar', 'Student'], true)): ?>
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
