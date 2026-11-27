<?php
require_once __DIR__ . '/../includes/bootstrap.php';

require_role('Student');

$userId = (int) current_user()['user_id'];

/* Get the student's record */
$studentQuery = $pdo->prepare(
    'SELECT student_id
     FROM students
     WHERE user_id = :user_id
     LIMIT 1'
);

$studentQuery->execute(['user_id' => $userId]);
$student = $studentQuery->fetch();

if (!$student) {
    http_response_code(404);
    exit('Student record not found.');
}

$studentId = (int) $student['student_id'];

/* Get enrollment applications */
$applicationQuery = $pdo->prepare(
    'SELECT
        ea.application_id,
        ea.lrn,
        ea.enrollment_type,
        ea.application_status,
        ea.school_year_id,
        ea.requested_grade_level_id,
        sy.school_year,
        gl.grade_level
     FROM enrollment_applications ea
     INNER JOIN school_years sy
        ON ea.school_year_id = sy.school_year_id
     INNER JOIN grade_levels gl
        ON ea.requested_grade_level_id = gl.grade_level_id
     WHERE ea.student_id = :student_id
     ORDER BY ea.application_id DESC'
);

$applicationQuery->execute(['student_id' => $studentId]);
$applications = $applicationQuery->fetchAll();

/* Get scholarship applications separately */
$scholarshipQuery = $pdo->prepare(
    'SELECT
        sa.scholarship_application_id,
        sa.program_id,
        sa.school_year_id,
        sa.application_status,
        sa.screening_date,
        sa.screening_time,
        sa.screening_venue,
        sa.screening_attendance,
        sp.program_name,
        sy.school_year
     FROM scholarship_applications sa
     INNER JOIN special_programs sp
        ON sa.program_id = sp.program_id
     INNER JOIN school_years sy
        ON sa.school_year_id = sy.school_year_id
     WHERE sa.student_id = :student_id
     ORDER BY sa.scholarship_application_id DESC'
);

$scholarshipQuery->execute(['student_id' => $studentId]);
$scholarships = $scholarshipQuery->fetchAll();

/* Required supporting documents */
$requiredDocuments = [
    'PSA Birth Certificate',
    'Old School Card (before moving up)',
    'Good Moral Certificate'
];

/* Get supporting document records for each enrollment application */
$documentsByApplication = [];

$documentQuery = $pdo->prepare(
    'SELECT
        document_type, verification_status
     FROM enrollment_documents
     WHERE application_id = :application_id'
);

foreach ($applications as $application) {
    $documentQuery->execute([
        'application_id' => $application['application_id']
    ]);

    $documentsByApplication[$application['application_id']] =
        $documentQuery->fetchAll();
}

/* Status styling */
function status_class(string $status): string
{
    return match ($status) {
        'Approved', 'Accepted', 'Available' => 'status-approved',
        'Rejected' => 'status-rejected',
        'Not Available' => 'status-incomplete',
        'Pending Review', 'For Screening', 'For Verification', 'Awaiting Submission' => 'status-pending',
        'Incomplete Requirements', 'Not Submitted' => 'status-incomplete',
        default => 'status-default'
    };
}

$pageTitle = 'My Applications';
require_once __DIR__ . '/../includes/header.php';
?>



<div class="portal-back-row">
    <a href="<?= BASE_URL ?>/student/dashboard.php" class="portal-back-link">Back to Dashboard</a>
</div>
<section class="form-card form-card-wide">
    <h1>My Applications</h1>

    <?php if (($_GET['updated'] ?? '') === '1'): ?>
        <p class="success-message" role="status">Enrollment application updated.</p>
    <?php endif; ?>

    <h2 class="application-section-title">Enrollment Applications</h2>

    <?php if (empty($applications)): ?>

        <div class="empty-state">
            <h3>No Enrollment Applications Yet</h3>

            <p class="muted">
                You have not submitted an enrollment application.
            </p>

            <a href="<?= BASE_URL ?>/student/new_enrollment.php"
               class="button button-primary">
                Start New Enrollment
            </a>
        </div>

    <?php else: ?>

        <?php foreach ($applications as $application): ?>

            <?php
            $applicationId = (int) $application['application_id'];
            $documents = $documentsByApplication[$applicationId] ?? [];

            $documentMap = [];

            foreach ($documents as $document) {
                $documentMap[$document['document_type']] = $document;
            }
            ?>

            <div class="application-card">

                <h3>
                    Enrollment Application #<?= $applicationId ?>
                </h3>

                <div>
                    <strong>Application Status:</strong>

                    <span class="student-status <?= e(status_class($application['application_status'])) ?>">
                        <?= e($application['application_status']) ?>
                    </span>
                </div>

                <div class="application-details">

                    <div class="detail-item">
                        <small>School Year</small>
                        <strong><?= e($application['school_year']) ?></strong>
                    </div>

                    <div class="detail-item">
                        <small>Enrollment Type</small>
                        <strong><?= e($application['enrollment_type']) ?></strong>
                    </div>

                    <div class="detail-item">
                        <small>Requested Grade Level</small>
                        <strong>Grade <?= e($application['grade_level']) ?></strong>
                    </div>

                    <div class="detail-item">
                        <small>LRN</small>
                        <strong><?= e($application['lrn'] ?? '') ?></strong>
                    </div>

                </div>

                <h4>Supporting Documents</h4>

                <div class="document-list">

                    <?php foreach ($requiredDocuments as $documentName): ?>

                        <?php
                        $document = $documentMap[$documentName] ?? null;
                        $documentStatus = match ($document['verification_status'] ?? null) {
                            'Accepted' => 'Accepted',
                            'Rejected' => 'Rejected',
                            null => 'Not Available',
                            default => 'Awaiting Submission'
                        };
                        ?>

                        <div class="document-item">

                            <strong><?= e($documentName) ?></strong>

                            <span class="student-status <?= e(status_class($documentStatus)) ?>">
                                <?= e($documentStatus) ?>
                            </span>

                        </div>

                    <?php endforeach; ?>

                </div>

                <?php if ($application['application_status'] === 'Pending Review'): ?>

                    <p class="muted">
                        Your application is pending review. You may edit it
                        while it remains under this status.
                    </p>

                    <div class="application-actions">

                        <a href="<?= BASE_URL ?>/student/edit_enrollment.php?application_id=<?= $applicationId ?>"
                           class="button button-outline portal-record-action">
                            Edit Application
                        </a>

                    </div>

                <?php endif; ?>

            </div>

        <?php endforeach; ?>

    <?php endif; ?>


    <h2 class="application-section-title">
        Special Program Scholarship Applications
    </h2>

    <?php if (empty($scholarships)): ?>

        <div class="empty-state">

            <h3>No Scholarship Applications</h3>

            <p class="muted">
                You have not submitted a special program scholarship application.
            </p>

        </div>

    <?php else: ?>

        <?php foreach ($scholarships as $scholarship): ?>

            <div class="application-card scholarship-card">

                <h3><?= e($scholarship['program_name']) ?></h3>

                <div class="application-details">

                    <div class="detail-item">
                        <small>School Year</small>
                        <strong><?= e($scholarship['school_year']) ?></strong>
                    </div>

                    <div class="detail-item">
                        <small>Scholarship Status</small>

                        <span class="student-status <?= e(status_class($scholarship['application_status'])) ?>">
                            <?= e($scholarship['application_status']) ?>
                        </span>
                    </div>

                </div>

                <?php if ($scholarship['application_status'] === 'For Screening' && !empty($scholarship['screening_date']) && !empty($scholarship['screening_time']) && !empty($scholarship['screening_venue'])): ?>
                    <div class="detail-item">
                        <strong>Screening Schedule</strong>
                        <p><small>Date</small><?= e(date('F j, Y', strtotime($scholarship['screening_date']))) ?></p>
                        <p><small>Time</small><?= e(date('g:i A', strtotime($scholarship['screening_time']))) ?></p>
                        <p><small>Venue</small><?= e($scholarship['screening_venue']) ?></p>
                        <p><small>Attendance</small><?= e($scholarship['screening_attendance'] ?? 'Pending') ?></p>
                    </div>
                <?php endif; ?>


            </div>

        <?php endforeach; ?>

    <?php endif; ?>

</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
