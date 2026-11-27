
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
$isAdmin = $user && $roleName === 'System Administrator';
$isRegistrar = $user && $roleName === 'School Registrar';
$isTeacher = $user && $roleName === 'Teacher';

$currentPage = basename($_SERVER['PHP_SELF'] ?? '');
$showPortalNavigation = $user || !in_array($currentPage, ['index.php', 'register.php', 'login.php'], true);
$isAccountPage = $isStudent && in_array(
    $currentPage,
    ['profile.php', 'change_password.php'],
    true
);

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

    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css?v=6">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/responsive.css?v=3">
</head>

<body class="portal-layout <?= $showPortalNavigation ? 'portal-navigation' : '' ?> <?= $isStudent ? 'student-portal' : '' ?> <?= $isAdmin ? 'admin-portal' : '' ?> <?= $isRegistrar ? 'registrar-portal' : '' ?> <?= $isTeacher ? 'teacher-portal' : '' ?> <?= $user ? 'has-role-sidebar' : '' ?> <?= $isAccountPage ? 'account-page' : '' ?> <?= (!$user && in_array($currentPage, ['index.php', 'register.php', 'login.php'], true)) ? 'public-auth-page' : '' ?>">
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

<?php if ($showPortalNavigation): ?>
    <!-- PUBLIC DESKTOP NAVIGATION. Authenticated users use the shared sidebar. -->
    <nav class="main-nav <?= $isStudent ? 'student-main-nav' : '' ?>"
         aria-label="Main navigation">
        <?php if (!$user): ?>
            <a href="<?= BASE_URL ?>/index.php" class="<?= header_link_active($currentPage, ['index.php']) ?>">Home</a>
            <a href="<?= BASE_URL ?>/announcements.php" class="<?= header_link_active($currentPage, ['announcements.php']) ?>">Announcements</a>
            <a href="<?= BASE_URL ?>/login.php" class="<?= header_link_active($currentPage, ['login.php']) ?>">Log In</a>
            <a href="<?= BASE_URL ?>/register.php" class="nav-register <?= header_link_active($currentPage, ['register.php']) ?>">How to Register?</a>
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
<?php endif; ?>

</header>


<?php if ($showPortalNavigation): ?>
<!-- MAIN NAVIGATION OVERLAY -->
<div
    class="main-sidebar-overlay"
    id="mainSidebarOverlay"
    aria-hidden="true"
></div>


<!-- SHARED ROLE-BASED SIDEBAR -->
<?php require __DIR__ . '/sidebar.php'; ?>
<?php endif; ?>


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

    const desktopNavigation = window.matchMedia('(min-width: 700px)');
    const compactToggle = document.getElementById('sidebarCompactToggle');

    function syncNavigation() {
        closeMainSidebar();
        mainSidebar.inert = !desktopNavigation.matches;
        mainSidebar.setAttribute('aria-hidden', String(!desktopNavigation.matches));
    }

    if (mainSidebar) {
        desktopNavigation.addEventListener('change', syncNavigation);
        syncNavigation();
    }

    if (compactToggle) {
        function setCompact(compact) {
            const label = compact ? 'Expand navigation' : 'Collapse navigation';
            document.body.classList.toggle('sidebar-compact', compact);
            compactToggle.setAttribute('aria-label', label);
            compactToggle.setAttribute('title', label);
            compactToggle.setAttribute('aria-expanded', String(!compact));
        }

        try {
            setCompact(localStorage.getItem('bcnchs-sidebar-compact') === 'true');
        } catch (error) {
            setCompact(false);
        }

        compactToggle.addEventListener('click', function () {
            const compact = !document.body.classList.contains('sidebar-compact');
            setCompact(compact);
            try {
                localStorage.setItem('bcnchs-sidebar-compact', String(compact));
            } catch (error) {
                // The menu still works when browser storage is unavailable.
            }
        });
    }

    function openMainSidebar() {
        if (!mainSidebar || !mainSidebarOverlay || desktopNavigation.matches) return;

        mainSidebar.inert = false;
        mainSidebar.classList.add('active');
        mainSidebarOverlay.classList.add('active');

        mainSidebar.setAttribute('aria-hidden', 'false');

        if (menuToggle) {
            menuToggle.setAttribute('aria-expanded', 'true');
        }

        document.body.classList.add('main-sidebar-open');
        if (mainSidebarClose) mainSidebarClose.focus();
    }

    function closeMainSidebar() {
        if (!mainSidebar || !mainSidebarOverlay) return;

        const restoreFocus = !desktopNavigation.matches && mainSidebar.contains(document.activeElement);
        if (restoreFocus && menuToggle) menuToggle.focus();
        mainSidebar.inert = !desktopNavigation.matches;
        mainSidebar.classList.remove('active');
        mainSidebarOverlay.classList.remove('active');

        mainSidebar.setAttribute('aria-hidden', String(!desktopNavigation.matches));
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
