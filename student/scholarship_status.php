<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('Student');

$pageTitle = 'Scholarship Application Status';
$userId = (int) current_user()['user_id'];

$studentQuery = $pdo->prepare(
    'SELECT student_id FROM students WHERE user_id = :user_id LIMIT 1'
);
$studentQuery->execute(['user_id' => $userId]);
$student = $studentQuery->fetch();

if (!$student) {
    http_response_code(404);
    exit('Student record not found.');
}

$applicationsQuery = $pdo->prepare(
    'SELECT
        sa.scholarship_application_id,
        sa.application_status,
        sa.remarks,
        sa.application_date,
        sa.screening_date,
        sa.screening_time,
        sa.screening_venue,
        sa.screening_attendance,
        sp.program_name,
        sp.description,
        sy.school_year
     FROM scholarship_applications sa
     INNER JOIN special_programs sp ON sp.program_id = sa.program_id
     INNER JOIN school_years sy ON sy.school_year_id = sa.school_year_id
     WHERE sa.student_id = :student_id
     ORDER BY sa.application_date DESC'
);
$applicationsQuery->execute(['student_id' => $student['student_id']]);
$applications = $applicationsQuery->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>



<div class="portal-back-row">
    <a href="<?= BASE_URL ?>/student/my_applications.php" class="portal-back-link">Back to My Applications</a>
</div>
<section class="form-card form-card-wide">
    <h1>Scholarship Application Status</h1>

    <?php if (!$applications): ?>
        <div class="empty-state">
            <h2>No Scholarship Applications</h2>
            <p>You have not submitted a scholarship application yet.</p>
            <a class="button button-primary" href="<?= BASE_URL ?>/student/scholarship_application.php">Apply for a Scholarship</a>
        </div>
    <?php else: ?>
        <?php foreach ($applications as $application): ?>
            <div class="application-card application-card-spaced">
                <h2><?= e($application['program_name']) ?></h2>
                <p class="muted"><?= e($application['description'] ?? '') ?></p>
                <p><strong>School Year:</strong> <?= e($application['school_year']) ?></p>
                <p><strong>Application Date:</strong> <?= e($application['application_date']) ?></p>
                <p><strong>Status:</strong> <span class="student-status <?= $application['application_status'] === 'Approved' ? 'status-approved' : ($application['application_status'] === 'Rejected' ? 'status-rejected' : 'status-pending') ?>"><?= e($application['application_status']) ?></span></p>

                <?php if ($application['application_status'] === 'For Screening' && $application['screening_date'] && $application['screening_time'] && $application['screening_venue']): ?>
                    <div class="scholarship-year-note scholarship-year-note-spaced">
                        <h3>Screening Schedule</h3>
                        <p><strong>Date:</strong> <?= e(date('F j, Y', strtotime($application['screening_date']))) ?></p>
                        <p><strong>Time:</strong> <?= e(date('g:i A', strtotime($application['screening_time']))) ?></p>
                        <p><strong>Venue:</strong> <?= e($application['screening_venue']) ?></p>
                        <p><strong>Attendance:</strong> <?= e($application['screening_attendance'] ?? 'Pending') ?></p>
                        <p class="muted">Please arrive on time and follow any additional instructions from the Scholarship Coordinator.</p>
                    </div>
                <?php elseif ($application['application_status'] === 'Pending Review'): ?>
                    <p class="muted">Your application is being reviewed. If shortlisted, your screening schedule will appear here.</p>
                <?php endif; ?>

                <?php if (!empty($application['remarks'])): ?>
                    <p><strong>Remarks:</strong> <?= e($application['remarks']) ?></p>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
