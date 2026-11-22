
<?php
$user = current_user();
$pageTitle = $pageTitle ?? 'Baguio City National High School Portal';

$roleName = $user['role_name'] ?? '';
$isStudent = $user && $roleName === 'Student';

$isStaff = $user && in_array($roleName, [
    'Enrollment Committee',
    'Scholarship Coordinator',
    'School Registrar',
    'System Administrator',
    'Teacher'
], true);

$isAdmin = $roleName === 'System Administrator';

$currentPage = basename($_SERVER['PHP_SELF'] ?? '');
$isRegistrationPage = $currentPage === 'register.php';

$isAccountPage = $isStudent && in_array(
    $currentPage,
    ['profile.php', 'change_password.php'],
    true
);

$staffLinks = [
    ['Dashboard', '/staff/dashboard.php', ['dashboard.php']],
    ['Subject Management', '/staff/subjects.php', ['subjects.php']],
    ['Student Records', '/staff/student_records.php', ['student_records.php']],
    ['Staff Records', '/staff/staff_records.php', ['staff_records.php']],
    ['Sections', '/staff/sections.php', ['sections.php']],
    ['Teacher Assignments', '/staff/teacher_assignments.php', ['teacher_assignments.php']],
    ['Scholarships', '/staff/scholarship_programs.php', ['scholarship_programs.php']],
    ['Reports', '/staff/reports.php', ['reports.php']]
];

$adminLinks = [
    ['Dashboard', '/admin/dashboard.php', ['dashboard.php']],
    ['User Management', '/admin/user_management.php', ['user_management.php']],
    ['Student Records', '/admin/student_records.php', ['student_records.php']],
    ['Teacher and Staff Records', '/admin/staff_records.php', ['staff_records.php']]
];
function header_link_active(string $page, array $pages): string
{
    return in_array($page, $pages, true) ? 'active' : '';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title><?= e($pageTitle) ?> | Baguio City National High School Portal</title>

    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>

<body class="<?= $isStudent ? 'student-portal' : '' ?> <?= $isAccountPage ? 'account-page' : '' ?> <?= $isRegistrationPage ? 'registration-page' : '' ?> <?= (!$user && in_array($currentPage, ['index.php', 'register.php', 'login.php'], true)) ? 'public-auth-page' : '' ?>">

<header class="site-header">

    <!-- SCHOOL BRAND -->
    <a
        class="brand"
        href="<?= $user
            ? BASE_URL . dashboard_path_for_role($roleName)
            : BASE_URL . '/index.php' ?>"
    >
        <img
            src="<?= BASE_URL ?>/assets/images/school-logo.png"
            alt="Baguio City National High School Logo"
        >

        <span>Baguio City National High School Portal</span>
    </a>

    <?php if ($isRegistrationPage): ?>
    <a
        href="<?= BASE_URL ?>/index.php"
        class="button button-outline registration-back-button"
    >
        &larr; Back to Log In
    </a>
<?php endif; ?>


    <!-- DESKTOP NAVIGATION -->
    <nav class="main-nav <?= $isStudent ? 'student-main-nav' : '' ?>"
         aria-label="Main navigation">

        <?php if ($isStudent): ?>

            <!-- Student navigation is available through the hamburger sidebar. -->

        <?php elseif ($isAdmin): ?>

            <?php foreach ($adminLinks as $link): ?>
                <a
                    href="<?= BASE_URL . $link[1] ?>"
                    class="<?= header_link_active($currentPage, $link[2]) ?>"
                    <?= header_link_active($currentPage, $link[2]) ? 'aria-current="page"' : '' ?>
                >
                    <?= e($link[0]) ?>
                </a>
            <?php endforeach; ?>

        <?php elseif ($isStaff): ?>

            <?php foreach ($staffLinks as $link): ?>
                <a
                    href="<?= BASE_URL . $link[1] ?>"
                    class="<?= header_link_active($currentPage, $link[2]) ?>"
                >
                    <?= e($link[0]) ?>
                </a>
            <?php endforeach; ?>

            <a
                href="<?= BASE_URL ?>/announcements.php"
                class="<?= header_link_active($currentPage, ['announcements.php']) ?>"
                <?= header_link_active($currentPage, ['announcements.php']) ? 'aria-current="page"' : '' ?>
            >
                Announcements
            </a>

        <?php else: ?>

    <a
        href="<?= BASE_URL ?>/index.php"
        class="<?= header_link_active($currentPage, ['index.php']) ?>"
    >
        Home
    </a>

            <a
                href="<?= BASE_URL ?>/announcements.php"
                class="<?= header_link_active($currentPage, ['announcements.php']) ?>"
                <?= header_link_active($currentPage, ['announcements.php']) ? 'aria-current="page"' : '' ?>
            >
                Announcements
            </a>
    <a
        href="<?= BASE_URL ?>/login.php"
        class="<?= header_link_active($currentPage, ['login.php']) ?>"
    >
        Log In
    </a>

    <a
        href="<?= BASE_URL ?>/register.php"
        class="nav-register <?= header_link_active($currentPage, ['register.php']) ?>"
    >
        Student Registration
    </a>

<?php endif; ?>

        <?php if ($user && !$isStudent): ?>
            <a href="#" class="nav-logout" data-logout-trigger>
                <img
                    src="<?= BASE_URL ?>/assets/images/logout.png"
                    alt=""
                    class="logout-icon"
                >
                Logout
            </a>
        <?php endif; ?>

    </nav>


    <!-- HEADER ACTIONS -->
    <div class="header-actions">

        <!-- HAMBURGER BUTTON -->
        <button
            type="button"
            class="menu-toggle"
            id="menuToggle"
            aria-label="Open navigation menu"
            aria-controls="mainSidebar"
            aria-expanded="false"
        >
            <span></span>
            <span></span>
            <span></span>
        </button>

    </div>

</header>


<!-- MAIN NAVIGATION OVERLAY -->
<div
    class="main-sidebar-overlay"
    id="mainSidebarOverlay"
    aria-hidden="true"
></div>


<!-- MAIN NAVIGATION SIDEBAR -->
<aside
    class="main-sidebar"
    id="mainSidebar"
    aria-label="Main navigation menu"
    aria-hidden="true"
>

    <div class="main-sidebar-header">
        <h2>Navigation</h2>

        <button
            type="button"
            class="main-sidebar-close"
            id="mainSidebarClose"
            aria-label="Close navigation menu"
        >&times;</button>
    </div>

    <nav class="main-sidebar-nav">

        <?php if ($isStudent): ?>

            <a
                href="<?= BASE_URL ?>/student/dashboard.php"
                class="<?= header_link_active($currentPage, ['dashboard.php']) ?>"
                <?= header_link_active($currentPage, ['dashboard.php']) ? 'aria-current="page"' : '' ?>
            >
                Dashboard
            </a>

            <a
                href="<?= BASE_URL ?>/student/my_applications.php"
                class="<?= header_link_active($currentPage, ['my_applications.php']) ?>"
                <?= header_link_active($currentPage, ['my_applications.php']) ? 'aria-current="page"' : '' ?>
            >
                My Applications
            </a>

            <a
                href="<?= BASE_URL ?>/announcements.php"
                class="<?= header_link_active($currentPage, ['announcements.php']) ?>"
                <?= header_link_active($currentPage, ['announcements.php']) ? 'aria-current="page"' : '' ?>
            >
                Announcements
            </a>

            <div class="sidebar-divider"></div>

            <a href="<?= BASE_URL ?>/student/profile.php">
                My Profile
            </a>

            <a href="<?= BASE_URL ?>/student/change_password.php">
                Change Password
            </a>

        <?php elseif ($isAdmin): ?>

            <?php foreach ($adminLinks as $link): ?>
                <a
                    href="<?= BASE_URL . $link[1] ?>"
                    class="<?= header_link_active($currentPage, $link[2]) ?>"
                >
                    <?= e($link[0]) ?>
                </a>
            <?php endforeach; ?>

        <?php elseif ($isStaff): ?>

            <?php foreach ($staffLinks as $link): ?>
                <a href="<?= BASE_URL . $link[1] ?>">
                    <?= e($link[0]) ?>
                </a>
            <?php endforeach; ?>

            <a
                href="<?= BASE_URL ?>/announcements.php"
                class="<?= header_link_active($currentPage, ['announcements.php']) ?>"
            >
                Announcements
            </a>

        <?php else: ?>

            <a href="<?= BASE_URL ?>/index.php">Home</a>
            <a
                href="<?= BASE_URL ?>/announcements.php"
                class="<?= header_link_active($currentPage, ['announcements.php']) ?>"
            >
                Announcements
            </a>
            <a href="<?= BASE_URL ?>/login.php">Log In</a>

            <a href="<?= BASE_URL ?>/register.php">
                Student Registration
            </a>

        <?php endif; ?>

    </nav>

    <?php if ($user): ?>
        <div class="main-sidebar-footer">
            <a href="#" class="nav-logout" data-logout-trigger>
                <img
                    src="<?= BASE_URL ?>/assets/images/logout.png"
                    alt=""
                    class="logout-icon"
                >
                Logout
            </a>
        </div>
    <?php endif; ?>

</aside>


<!-- LOGOUT CONFIRMATION MODAL -->
<?php if ($user): ?>

    <div class="logout-modal" id="logoutModal" aria-hidden="true">

        <div
            class="logout-modal-content"
            role="dialog"
            aria-modal="true"
            aria-labelledby="logoutTitle"
            aria-describedby="logoutDescription"
        >

            <div class="logout-modal-heading">

                <div class="logout-modal-icon">
                    <img
                        src="<?= BASE_URL ?>/assets/images/logout.png"
                        alt=""
                    >
                </div>

                <div>
                    <h2>Confirm Logout</h2>
                    <p>Baguio City National High School Portal</p>
                </div>

            </div>

            <div class="logout-modal-body">
                <h3 id="logoutTitle">
                    Are you sure you want to log out?
                </h3>

                <p id="logoutDescription">
                    You will be signed out of your account and redirected
                    to the portal's homepage.
                </p>
            </div>

            <div class="logout-modal-actions">

                <button type="button" id="cancelLogout">
                    Cancel
                </button>

                <form method="POST" action="<?= BASE_URL ?>/logout.php">

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= e(csrf_token()) ?>"
                    >

                    <button type="submit" class="confirm-logout-button">
                        Yes, Log Out
                    </button>

                </form>

            </div>

        </div>

    </div>

<?php endif; ?>


<script>
document.addEventListener('DOMContentLoaded', function () {

    // MAIN HAMBURGER SIDEBAR
    const menuToggle = document.getElementById('menuToggle');
    const mainSidebar = document.getElementById('mainSidebar');
    const mainSidebarClose = document.getElementById('mainSidebarClose');
    const mainSidebarOverlay = document.getElementById('mainSidebarOverlay');

    function openMainSidebar() {
        if (!mainSidebar || !mainSidebarOverlay) return;

        mainSidebar.classList.add('active');
        mainSidebarOverlay.classList.add('active');

        mainSidebar.setAttribute('aria-hidden', 'false');
        mainSidebarOverlay.setAttribute('aria-hidden', 'false');

        if (menuToggle) {
            menuToggle.setAttribute('aria-expanded', 'true');
        }

        document.body.classList.add('main-sidebar-open');
    }

    function closeMainSidebar() {
        if (!mainSidebar || !mainSidebarOverlay) return;

        mainSidebar.classList.remove('active');
        mainSidebarOverlay.classList.remove('active');

        mainSidebar.setAttribute('aria-hidden', 'true');
        mainSidebarOverlay.setAttribute('aria-hidden', 'true');

        if (menuToggle) {
            menuToggle.setAttribute('aria-expanded', 'false');
        }

        document.body.classList.remove('main-sidebar-open');
    }

    // EVENT LISTENERS
    if (menuToggle) {
        menuToggle.addEventListener('click', openMainSidebar);
    }

    if (mainSidebarClose) {
        mainSidebarClose.addEventListener('click', closeMainSidebar);
    }

    if (mainSidebarOverlay) {
        mainSidebarOverlay.addEventListener('click', closeMainSidebar);
    }

    // CLOSE SIDEBARS AFTER NAVIGATION
    if (mainSidebar) {
        mainSidebar.querySelectorAll('a').forEach(function (link) {
            link.addEventListener('click', function () {
                if (!link.hasAttribute('data-logout-trigger')) {
                    closeMainSidebar();
                }
            });
        });
    }

    // LOGOUT CONFIRMATION MODAL
    const modal = document.getElementById('logoutModal');
    const triggers = document.querySelectorAll('[data-logout-trigger]');
    const cancelButton = document.getElementById('cancelLogout');

    function openLogoutModal(event) {
        event.preventDefault();

        closeMainSidebar();

        if (!modal) return;

        modal.classList.add('active');
        modal.setAttribute('aria-hidden', 'false');

        if (cancelButton) {
            cancelButton.focus();
        }
    }

    function closeLogoutModal() {
        if (!modal) return;

        modal.classList.remove('active');
        modal.setAttribute('aria-hidden', 'true');
    }

    triggers.forEach(function (trigger) {
        trigger.addEventListener('click', openLogoutModal);
    });

    if (cancelButton) {
        cancelButton.addEventListener('click', closeLogoutModal);
    }

    if (modal) {
        modal.addEventListener('click', function (event) {
            if (event.target === modal) {
                closeLogoutModal();
            }
        });
    }


    // ESCAPE KEY
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            closeMainSidebar();
                closeLogoutModal();
        }
    });

});
</script>

<main class="page-container">
