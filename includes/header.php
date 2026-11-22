
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

$studentLinks = [
    ['Dashboard', '/student/dashboard.php', ['dashboard.php']],
    ['Enrollment', '/student/new_enrollment.php', ['new_enrollment.php', 'edit_enrollment.php']],
    ['My Applications', '/student/my_applications.php', ['my_applications.php']],
    ['Student Records', '/student/records.php', ['records.php']]
];

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

<body class="<?= $isStudent ? 'student-portal' : '' ?> <?= $isAccountPage ? 'account-page' : '' ?> <?= $isRegistrationPage ? 'registration-page' : '' ?>">

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
        &larr; Back to Home
    </a>
<?php endif; ?>


    <!-- DESKTOP NAVIGATION -->
    <nav class="main-nav <?= $isStudent ? 'student-main-nav' : '' ?>"
         aria-label="Main navigation">

        <?php if ($isStudent): ?>

            <?php foreach ($studentLinks as $link): ?>
                <a
                    href="<?= BASE_URL . $link[1] ?>"
                    class="<?= header_link_active($currentPage, $link[2]) ?>"
                    <?= header_link_active($currentPage, $link[2]) ? 'aria-current="page"' : '' ?>
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

        <?php if ($isStudent): ?>

            <!-- PROFILE ICON: OPENS ACCOUNT SIDEBAR -->
            <button
                type="button"
                class="profile-icon-link"
                id="accountIcon"
                aria-label="Open account menu"
                aria-controls="profileSidebar"
                aria-expanded="false"
                title="Account Menu"
            >
                <img
                    src="<?= BASE_URL ?>/assets/images/account.png"
                    alt=""
                    class="profile-account-icon"
                >
            </button>

        <?php endif; ?>


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

            <?php foreach ($studentLinks as $link): ?>
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


<!-- STUDENT ACCOUNT SIDEBAR -->
<?php if ($isStudent): ?>

    <div
        class="profile-sidebar-overlay"
        id="profileSidebarOverlay"
        aria-hidden="true"
    ></div>

    <aside
        class="profile-sidebar"
        id="profileSidebar"
        aria-label="Account menu"
        aria-hidden="true"
    >

        <div class="profile-sidebar-header">
            <h2>Account Menu</h2>

            <button
                type="button"
                class="profile-sidebar-close"
                id="profileSidebarClose"
                aria-label="Close account menu"
            >&times;</button>
        </div>

        <nav class="profile-sidebar-nav">

            <a
                href="<?= BASE_URL ?>/student/profile.php"
                class="<?= $currentPage === 'profile.php' ? 'active' : '' ?>"
            >
                My Profile
            </a>

            <a
                href="<?= BASE_URL ?>/student/change_password.php"
                class="<?= $currentPage === 'change_password.php' ? 'active' : '' ?>"
            >
                Change Password
            </a>

        </nav>

        <div class="profile-sidebar-logout">
            <a href="#" class="nav-logout" data-logout-trigger>
                <img
                    src="<?= BASE_URL ?>/assets/images/logout.png"
                    alt=""
                    class="logout-icon"
                >
                Logout
            </a>
        </div>

    </aside>

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

    // STUDENT ACCOUNT SIDEBAR
    const accountIcon = document.getElementById('accountIcon');
    const profileSidebar = document.getElementById('profileSidebar');
    const profileSidebarClose = document.getElementById('profileSidebarClose');
    const profileSidebarOverlay = document.getElementById('profileSidebarOverlay');

    function openMainSidebar() {
        if (!mainSidebar || !mainSidebarOverlay) return;

        closeAccountSidebar();

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

    function openAccountSidebar() {
        if (!profileSidebar || !profileSidebarOverlay) return;

        closeMainSidebar();

        profileSidebar.classList.add('active');
        profileSidebarOverlay.classList.add('active');

        profileSidebar.setAttribute('aria-hidden', 'false');
        profileSidebarOverlay.setAttribute('aria-hidden', 'false');

        if (accountIcon) {
            accountIcon.setAttribute('aria-expanded', 'true');
        }

        document.body.classList.add('sidebar-open');
    }

    function closeAccountSidebar() {
        if (!profileSidebar || !profileSidebarOverlay) return;

        profileSidebar.classList.remove('active');
        profileSidebarOverlay.classList.remove('active');

        profileSidebar.setAttribute('aria-hidden', 'true');
        profileSidebarOverlay.setAttribute('aria-hidden', 'true');

        if (accountIcon) {
            accountIcon.setAttribute('aria-expanded', 'false');
        }

        document.body.classList.remove('sidebar-open');
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

    if (accountIcon) {
        accountIcon.addEventListener('click', openAccountSidebar);
    }

    if (profileSidebarClose) {
        profileSidebarClose.addEventListener('click', closeAccountSidebar);
    }

    if (profileSidebarOverlay) {
        profileSidebarOverlay.addEventListener('click', closeAccountSidebar);
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

    if (profileSidebar) {
        profileSidebar.querySelectorAll('nav a').forEach(function (link) {
            link.addEventListener('click', closeAccountSidebar);
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
