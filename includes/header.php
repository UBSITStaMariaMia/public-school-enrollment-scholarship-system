
<?php
$user = current_user();
$pageTitle = $pageTitle ?? 'Baguio City National High School Portal';

$isStudent = $user && ($user['role_name'] ?? '') === 'Student';
$currentPage = basename($_SERVER['PHP_SELF'] ?? '');

$isAccountPage = $isStudent && in_array(
    $currentPage,
    ['profile.php', 'change_password.php'],
    true
);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title><?= e($pageTitle) ?> | Baguio City National High School Portal</title>

    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>

<body class="<?= $isStudent ? 'student-portal' : '' ?> <?= $isAccountPage ? 'account-page' : '' ?>">

<header class="site-header">

    <a
        class="brand"
        href="<?= $user
            ? BASE_URL . dashboard_path_for_role($user['role_name'])
            : BASE_URL . '/index.php' ?>"
    >
        <img
            src="<?= BASE_URL ?>/assets/images/school-logo.png"
            alt="Baguio City National High School Logo"
        >

        <span>Baguio City National High School Portal</span>
    </a>

    <?php if ($isStudent): ?>

        <!-- STUDENT TOP NAVIGATION -->
        <nav class="main-nav student-main-nav" aria-label="Student navigation">

            <a
                href="<?= BASE_URL ?>/student/dashboard.php"
                class="<?= $currentPage === 'dashboard.php' ? 'active' : '' ?>"
                <?= $currentPage === 'dashboard.php' ? 'aria-current="page"' : '' ?>
            >Dashboard</a>

            <a
                href="<?= BASE_URL ?>/student/new_enrollment.php"
                class="<?= in_array($currentPage, ['new_enrollment.php', 'edit_enrollment.php'], true) ? 'active' : '' ?>"
                <?= in_array($currentPage, ['new_enrollment.php', 'edit_enrollment.php'], true) ? 'aria-current="page"' : '' ?>
            >Enrollment</a>

            <a
                href="<?= BASE_URL ?>/student/my_applications.php"
                class="<?= $currentPage === 'my_applications.php' ? 'active' : '' ?>"
                <?= $currentPage === 'my_applications.php' ? 'aria-current="page"' : '' ?>
            >My Applications</a>

            <a
                href="<?= BASE_URL ?>/student/records.php"
                class="<?= $currentPage === 'records.php' ? 'active' : '' ?>"
                <?= $currentPage === 'records.php' ? 'aria-current="page"' : '' ?>
            >Student Records</a>

            <a
                href="<?= BASE_URL ?>/announcements.php"
                class="<?= $currentPage === 'announcements.php' ? 'active' : '' ?>"
                <?= $currentPage === 'announcements.php' ? 'aria-current="page"' : '' ?>
            >Announcements</a>

           <!-- ACCOUNT ICON: ALWAYS LAST -->
            <button
                type="button"
                class="profile-icon-link"
                id="accountIcon"
                aria-label="Open account menu"
                title="Account Menu"
            >
                <img
                    src="<?= BASE_URL ?>/assets/images/account.png"
                    alt=""
                    class="profile-account-icon"
                >
            </button>

        </nav>

    <?php else: ?>

        <!-- STAFF AND PUBLIC NAVIGATION -->
        <nav class="main-nav" aria-label="Main navigation">

            <?php if ($user && in_array(
                $user['role_name'],
                ['Staff', 'Admin'],
                true
            )): ?>

                <a href="<?= BASE_URL ?>/staff/dashboard.php">Dashboard</a>

                <?php if ($user['role_name'] === 'Admin'): ?>
                    <a href="<?= BASE_URL ?>/staff/users.php">User Management</a>
                <?php endif; ?>

                <a href="<?= BASE_URL ?>/staff/subjects.php">Subject Management</a>
                <a href="<?= BASE_URL ?>/staff/student_records.php">Student Records</a>
                <a href="<?= BASE_URL ?>/staff/staff_records.php">Staff Records</a>
                <a href="<?= BASE_URL ?>/staff/sections.php">Sections</a>
                <a href="<?= BASE_URL ?>/staff/teacher_assignments.php">Teacher Assignments</a>
                <a href="<?= BASE_URL ?>/staff/scholarship_programs.php">Scholarships</a>
                <a href="<?= BASE_URL ?>/staff/reports.php">Reports</a>

            <?php elseif (!$user): ?>

                <a href="<?= BASE_URL ?>/index.php">Home</a>
                <a href="<?= BASE_URL ?>/announcements.php">Announcements</a>
                <a href="<?= BASE_URL ?>/login.php">Log In</a>

                <a class="nav-register" href="<?= BASE_URL ?>/register.php">
                    Student Registration
                </a>

            <?php endif; ?>

            <?php if ($user): ?>
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

    <?php endif; ?>

</header>

<?php if ($isStudent): ?>
    

    <!-- SIDEBAR OVERLAY -->
    <div
        class="profile-sidebar-overlay"
        id="profileSidebarOverlay"
        aria-hidden="true"
    ></div>

    <!-- COLLAPSIBLE ACCOUNT SIDEBAR -->
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
                <?= $currentPage === 'profile.php' ? 'aria-current="page"' : '' ?>
            >
                My Profile
            </a>

            <a
                href="<?= BASE_URL ?>/student/change_password.php"
                class="<?= $currentPage === 'change_password.php' ? 'active' : '' ?>"
                <?= $currentPage === 'change_password.php' ? 'aria-current="page"' : '' ?>
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

<?php if ($user): ?>

    <!-- LOGOUT CONFIRMATION MODAL -->
    <div
        class="logout-modal"
        id="logoutModal"
        aria-hidden="true"
    >
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

                <form
                    method="POST"
                    action="<?= BASE_URL ?>/logout.php"
                >
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

    // ACCOUNT SIDEBAR
const accountIcon = document.getElementById('accountIcon');
const sidebar = document.getElementById('profileSidebar');
const sidebarClose = document.getElementById('profileSidebarClose');
const sidebarOverlay = document.getElementById('profileSidebarOverlay');

if (accountIcon && sidebar && sidebarClose && sidebarOverlay) {

    function openSidebar() {
        sidebar.classList.add('active');
        sidebarOverlay.classList.add('active');

        sidebar.setAttribute('aria-hidden', 'false');
        sidebarOverlay.setAttribute('aria-hidden', 'false');

        document.body.classList.add('sidebar-open');
    }

    function closeSidebar() {
        sidebar.classList.remove('active');
        sidebarOverlay.classList.remove('active');

        sidebar.setAttribute('aria-hidden', 'true');
        sidebarOverlay.setAttribute('aria-hidden', 'true');

        document.body.classList.remove('sidebar-open');
    }

    // Open sidebar when account icon is clicked.
    accountIcon.addEventListener('click', openSidebar);

    // Close sidebar.
    sidebarClose.addEventListener('click', closeSidebar);
    sidebarOverlay.addEventListener('click', closeSidebar);

    // Close sidebar using Escape.
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            closeSidebar();
        }
    });

    // Close sidebar when navigating through its links.
    sidebar.querySelectorAll('nav a').forEach(function (link) {
        link.addEventListener('click', closeSidebar);
    });
}

    // LOGOUT CONFIRMATION MODAL
    const modal = document.getElementById('logoutModal');
    const triggers = document.querySelectorAll('[data-logout-trigger]');
    const cancelButton = document.getElementById('cancelLogout');

    if (!modal || !cancelButton) {
        return;
    }

    function openModal(event) {
        event.preventDefault();

        modal.classList.add('active');
        modal.setAttribute('aria-hidden', 'false');

        cancelButton.focus();
    }

    function closeModal() {
        modal.classList.remove('active');
        modal.setAttribute('aria-hidden', 'true');
    }

    triggers.forEach(function (trigger) {
        trigger.addEventListener('click', openModal);
    });

    cancelButton.addEventListener('click', closeModal);

    modal.addEventListener('click', function (event) {
        if (event.target === modal) {
            closeModal();
        }
    });

    document.addEventListener('keydown', function (event) {
        if (
            event.key === 'Escape' &&
            modal.classList.contains('active')
        ) {
            closeModal();
        }
    });

});
</script>

<main class="page-container">
