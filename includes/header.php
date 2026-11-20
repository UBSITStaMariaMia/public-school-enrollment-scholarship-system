
<?php
$user = current_user();
$pageTitle = $pageTitle ?? 'Baguio City National High School Portal';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title><?= e($pageTitle) ?> | Baguio City National High School Portal</title>

    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>

<body>

<header class="site-header">

    <!-- SCHOOL BRAND -->
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

    <!-- MAIN NAVIGATION -->
    <nav class="main-nav">

        <?php if ($user && $user['role_name'] === 'Student'): ?>

            <a href="<?= BASE_URL ?>/student/dashboard.php">Dashboard</a>
            <a href="<?= BASE_URL ?>/student/new_enrollment.php">Enrollment</a>
            <a href="<?= BASE_URL ?>/student/my_applications.php">My Applications</a>
            <a href="<?= BASE_URL ?>/student/records.php">Student Records</a>
            <a href="<?= BASE_URL ?>/student/profile.php">My Profile</a>
            <a href="<?= BASE_URL ?>/announcements.php">Announcements</a>

        <?php elseif ($user && in_array(
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

        <?php else: ?>

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

</header>

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

            <button
                type="button"
                id="cancelLogout"
            >
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

<script>
document.addEventListener('DOMContentLoaded', function () {

    const modal = document.getElementById('logoutModal');
    const triggers = document.querySelectorAll('[data-logout-trigger]');
    const cancelButton = document.getElementById('cancelLogout');

    if (!modal || !cancelButton) {
        return;
    }

    function openModal() {
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

<?php endif; ?>

<main class="page-container">
