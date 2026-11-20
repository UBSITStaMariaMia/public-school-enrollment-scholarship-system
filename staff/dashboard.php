
<?php
require_once __DIR__ . '/../includes/bootstrap.php';

require_role(
    'System Administrator',
    'School Registrar',
    'Teacher',
    'Enrollment Committee',
    'Scholarship Coordinator'
);

$pageTitle = 'Staff Dashboard';
require_once __DIR__ . '/../includes/header.php';

$user = current_user();
$isAdmin = $user['role_name'] === 'System Administrator';
$isTeacher = $user['role_name'] === 'Teacher';
?>

<section class="form-card">
    <p class="eyebrow">Staff workspace</p>
    <h1><?= e($user['role_name']) ?> Dashboard</h1>

    <?php if ($isAdmin): ?>

        <p>
            Manage staff accounts, staff information, and student academic records.
        </p>

        <div class="form-actions">
            <a class="btn btn-primary" href="<?= BASE_URL ?>/staff/users.php">
                User Management
            </a>

            <a class="btn btn-secondary" href="<?= BASE_URL ?>/staff/subjects.php">
                Subject Management
            </a>

            <a class="btn btn-secondary" href="<?= BASE_URL ?>/staff/student_records.php">
                Student Records Management
            </a>

            <a class="btn btn-secondary" href="<?= BASE_URL ?>/staff/staff_records.php">
                Staff Records Management
            </a>
        </div>

    <?php elseif ($isTeacher): ?>

        <p>
            Access your teaching tools and manage the academic records
            of students in your assigned sections.
        </p>

        <div class="form-actions">
            <a class="btn btn-primary" href="<?= BASE_URL ?>/staff/subjects.php">
                Subject Management
            </a>

            <a class="btn btn-secondary" href="<?= BASE_URL ?>/staff/student_records.php">
                Student Records Management
            </a>
        </div>

    <?php else: ?>

        <p>
            Welcome to the staff workspace. Your role-specific
            management features will appear here.
        </p>

    <?php endif; ?>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
