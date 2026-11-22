
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

/* Get uploaded documents for each enrollment application */
$documentsByApplication = [];

$documentQuery = $pdo->prepare(
    'SELECT
        document_type,
        file_path,
        verification_status,
        remarks
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
        'Approved', 'Accepted' => 'status-approved',
        'Rejected' => 'status-rejected',
        'Pending Review', 'For Screening', 'For Verification' => 'status-pending',
        'Incomplete Requirements', 'Not Submitted' => 'status-incomplete',
        default => 'status-default'
    };
}

$pageTitle = 'My Applications';
require_once __DIR__ . '/../includes/header.php';
?>



<section class="form-card form-card-wide">

    <a
        href="<?= BASE_URL ?>/student/dashboard.php"
        class="button button-outline applications-back-button"
    >
        &larr; Back to Dashboard
    </a>

    <p class="eyebrow">Student Services</p>
    <h1>My Applications</h1>

    <p class="muted">
        View your enrollment applications, supporting documents,
        and special program scholarship applications.
    </p>

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

                <h2>
                    Enrollment Application #<?= $applicationId ?>
                </h2>

                <div>
                    <strong>Application Status:</strong>

                    <span class="status-badge <?= e(status_class($application['application_status'])) ?>">
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

                <h3>Supporting Documents</h3>

                <div class="document-list">

                    <?php foreach ($requiredDocuments as $documentName): ?>

                        <?php
                        $uploaded = $documentMap[$documentName] ?? null;
                        ?>

                        <div class="document-item">

                            <strong><?= e($documentName) ?></strong>

                            <?php if ($uploaded): ?>

                                <div>

                                    <span class="status-badge <?= e(status_class($uploaded['verification_status'])) ?>">
                                        <?= e($uploaded['verification_status']) ?>
                                    </span>

                                    <?php if (!empty($uploaded['file_path'])): ?>
                                        <br>

                                        <a href="<?= BASE_URL . '/' . e(ltrim($uploaded['file_path'], '/')) ?>"
                                           target="_blank"
                                           rel="noopener">
                                            View Uploaded File
                                        </a>
                                    <?php endif; ?>

                                    <?php if (!empty($uploaded['remarks'])): ?>
                                        <p class="muted">
                                            Remarks: <?= e($uploaded['remarks']) ?>
                                        </p>
                                    <?php endif; ?>

                                </div>

                            <?php else: ?>

                                <span class="status-badge status-incomplete">
                                    Not Submitted
                                </span>

                            <?php endif; ?>

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
                           class="button button-primary">
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

                <h2><?= e($scholarship['program_name']) ?></h2>

                <div class="application-details">

                    <div class="detail-item">
                        <small>School Year</small>
                        <strong><?= e($scholarship['school_year']) ?></strong>
                    </div>

                    <div class="detail-item">
                        <small>Scholarship Status</small>

                        <span class="status-badge <?= e(status_class($scholarship['application_status'])) ?>">
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

                <p class="muted">
                    This scholarship application is reviewed separately from your enrollment application.
                    Check Scholarship Application Status for screening updates.
                </p>

            </div>

        <?php endforeach; ?>

    <?php endif; ?>

</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
