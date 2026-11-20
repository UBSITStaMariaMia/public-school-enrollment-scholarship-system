<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('Student');
$studentQuery = $pdo->prepare('SELECT first_name, middle_name, last_name FROM students WHERE user_id = :user_id LIMIT 1');
$studentQuery->execute(['user_id' => current_user()['user_id']]);
$student = $studentQuery->fetch();
$studentName = $student ? trim($student['first_name'] . ' ' . ($student['middle_name'] ? $student['middle_name'] . ' ' : '') . $student['last_name']) : current_user()['username'];
$pageTitle = 'Student Dashboard';
require_once __DIR__ . '/../includes/header.php';
?>
<section class="welcome-banner">
    <p class="eyebrow">Student Portal</p>
    <h1>Welcome, <?= e($studentName) ?></h1>
    <p>Manage your enrollment, review your applications, and maintain your student information.</p>
</section>
<section class="dashboard-grid">
    <a class="dashboard-card" href="<?= BASE_URL ?>/student/new_enrollment.php"><span class="card-kicker">01</span><h2>New Enrollment</h2><p>Submit your enrollment details and required documents.</p></a>
    <a class="dashboard-card" href="<?= BASE_URL ?>/student/my_applications.php"><span class="card-kicker">02</span><h2>My Applications</h2><p>Track enrollment and scholarship application statuses separately.</p></a>
    <a class="dashboard-card" href="<?= BASE_URL ?>/student/profile.php"><span class="card-kicker">03</span><h2>My Profile</h2><p>Review and update your personal and family information.</p></a>
    <a class="dashboard-card" href="<?= BASE_URL ?>/announcements.php"><span class="card-kicker">04</span><h2>Announcements</h2><p>Read the latest school announcements.</p></a>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
