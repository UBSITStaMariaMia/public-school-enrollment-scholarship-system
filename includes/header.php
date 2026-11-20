
<?php
require_once __DIR__ . '/bootstrap.php';

$user = current_user();
$pageTitle = $pageTitle ?? APP_NAME;

$canManageSubjects = $user && in_array(
    $user['role_name'],
    ['System Administrator', 'Teacher'],
    true
);

$canManageStudentRecords = $user && in_array(
    $user['role_name'],
    ['System Administrator', 'Teacher'],
    true
);

$isAdmin = $user && $user['role_name'] === 'System Administrator';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> | <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body>
<header class="site-header">
    <a class="brand" href="<?= $user ? BASE_URL . dashboard_path_for_role($user['role_name']) : BASE_URL . '/index.php' ?>">
        <img src="<?= BASE_URL ?>/assets/images/school-logo.png" alt="School logo">
        <span><?= e(APP_NAME) ?></span>
    </a>

    <nav class="main-nav">
        <?php if ($user && $user['role_name'] === 'Student'): ?>

            <a href="<?= BASE_URL ?>/student/dashboard.php">Dashboard</a>
            <a href="<?= BASE_URL ?>/student/new_enrollment.php">Enrollment</a>
            <a href="<?= BASE_URL ?>/student/my_applications.php">My Applications</a>
            <a href="<?= BASE_URL ?>/student/records.php">Student Records</a>
            <a href="<?= BASE_URL ?>/student/profile.php">My Profile</a>
            <a href="<?= BASE_URL ?>/announcements.php">Announcements</a>
            <a class="nav-logout" href="<?= BASE_URL ?>/logout.php">Logout</a>

        <?php elseif ($user): ?>

            <a href="<?= BASE_URL ?>/staff/dashboard.php">Dashboard</a>

            <?php if ($isAdmin): ?>
                <a href="<?= BASE_URL ?>/staff/users.php">User Management</a>
            <?php endif; ?>

            <?php if ($canManageSubjects): ?>
                <a href="<?= BASE_URL ?>/staff/subjects.php">Subject Management</a>
            <?php endif; ?>

            <?php if ($canManageStudentRecords): ?>
                <a href="<?= BASE_URL ?>/staff/student_records.php">Student Records Management</a>
            <?php endif; ?>

            <?php if ($isAdmin): ?>
                <a href="<?= BASE_URL ?>/staff/staff_records.php">Staff Records Management</a>
            <?php endif; ?>

            <a href="<?= BASE_URL ?>/logout.php">Logout</a>

        <?php else: ?>

            <a href="<?= BASE_URL ?>/index.php">Home</a>
            <a href="<?= BASE_URL ?>/announcements.php">Announcements</a>
            <a href="<?= BASE_URL ?>/login.php">Login</a>
            <a class="nav-register" href="<?= BASE_URL ?>/register.php">Student Registration</a>

        <?php endif; ?>
    </nav>
</header>
<main class="page-container">
