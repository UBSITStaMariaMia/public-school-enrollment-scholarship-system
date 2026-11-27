<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_role('Student');

$pageTitle = 'Settings';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="student-account-shell">
    <div class="portal-back-row">
        <a class="portal-back-link" href="<?= BASE_URL ?>/student/dashboard.php">Back to Dashboard</a>
    </div>
    <section class="form-card form-card-wide">
        <h1>Settings</h1>
        <nav aria-label="Account settings">
            <ul class="student-settings-list" role="list">
                <li>
                    <a href="<?= BASE_URL ?>/student/profile.php">My Profile</a>
                </li>
                <li>
                    <a href="<?= BASE_URL ?>/student/change_password.php">Change Password</a>
                </li>
            </ul>
        </nav>
    </section>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
