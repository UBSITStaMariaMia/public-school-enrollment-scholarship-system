<?php
require_once __DIR__ . '/includes/bootstrap.php';
if (current_user()) {
    redirect(dashboard_path_for_role(current_user()['role_name']));
}
$pageTitle = 'Home';
require_once __DIR__ . '/includes/header.php';
?>
<section class="hero">
    <div class="hero-copy">
        <p class="eyebrow">Student Services Portal</p>
        <h1>Welcome to Baguio City National High School</h1>
        <p class="hero-lead">A centralized portal for student enrollment, student information, and special program scholarship applications.</p>
        <div class="hero-actions">
            <a class="button button-primary" href="<?= BASE_URL ?>/register.php">Create Student Account</a>
            <a class="button button-outline" href="<?= BASE_URL ?>/login.php">Student Login</a>
        </div>
    </div>
    <div class="hero-panel">
        <h2>Student Portal</h2>
        <p>Use your account to submit enrollment information, check application progress, and view school announcements.</p>
        <ul>
            <li>Student enrollment applications</li>
            <li>Independent scholarship application status</li>
            <li>Student profile and assigned subjects</li>
        </ul>
    </div>
</section>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
