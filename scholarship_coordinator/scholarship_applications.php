
<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('System Administrator', 'Scholarship Coordinator');

$pageTitle = 'Scholarship Applications';
$error = '';
$success = '';
$statuses = ['Pending Review', 'For Screening', 'Approved', 'Rejected'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
            throw new RuntimeException('Invalid request. Please refresh the page and try again.');
        }

        $applicationId = filter_var($_POST['scholarship_application_id'] ?? null, FILTER_VALIDATE_INT);
        $status = $_POST['application_status'] ?? '';
        $remarks = trim($_POST['remarks'] ?? '');
        $screeningDate = trim($_POST['screening_date'] ?? '');
        $screeningTime = trim($_POST['screening_time'] ?? '');
        $screeningVenue = trim($_POST['screening_venue'] ?? '');

        if (!$applicationId || !in_array($status, $statuses, true)) {
            throw new RuntimeException('Select a valid application and status.');
        }

        if ($status === 'For Screening') {
            if ($screeningDate === '' || $screeningTime === '' || $screeningVenue === '') {
                throw new RuntimeException('Provide the screening date, time, and venue before setting the application to For Screening.');
            }

            $dateObject = DateTime::createFromFormat('Y-m-d', $screeningDate);
            if (!$dateObject || $dateObject->format('Y-m-d') !== $screeningDate) {
                throw new RuntimeException('Enter a valid screening date.');
            }

            if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $screeningTime)) {
                throw new RuntimeException('Enter a valid screening time.');
            }

            if (strlen($screeningVenue) > 180) {
                throw new RuntimeException('The screening venue must not exceed 180 characters.');
            }
        } else {
            $screeningDate = '';
            $screeningTime = '';
            $screeningVenue = '';
        }

        $applicationCheck = $pdo->prepare(
            'SELECT scholarship_application_id
             FROM scholarship_applications
             WHERE scholarship_application_id = :application_id'
        );

        $applicationCheck->execute([
            'application_id' => $applicationId
        ]);

        if (!$applicationCheck->fetch()) {
            throw new RuntimeException('The selected scholarship application could not be found.');
        }

        $updateQuery = $pdo->prepare(
            'UPDATE scholarship_applications
             SET application_status = :status,
                 remarks = :remarks,
                 screening_date = :screening_date,
                 screening_time = :screening_time,
                 screening_venue = :screening_venue,
                 reviewed_by = :reviewed_by,
                 reviewed_at = NOW()
             WHERE scholarship_application_id = :application_id'
        );

        $updateQuery->execute([
            'status' => $status,
            'remarks' => $remarks !== '' ? $remarks : null,
            'screening_date' => $screeningDate !== '' ? $screeningDate : null,
            'screening_time' => $screeningTime !== '' ? $screeningTime : null,
            'screening_venue' => $screeningVenue !== '' ? $screeningVenue : null,
            'reviewed_by' => (int) current_user()['user_id'],
            'application_id' => $applicationId
        ]);

        $success = 'Scholarship application review and screening details have been saved.';

    } catch (PDOException $exception) {
        error_log('Scholarship application review: ' . $exception->getMessage());
        $error = 'Unable to update the application right now. Please try again.';

    } catch (RuntimeException $exception) {
        $error = $exception->getMessage();
    }
}

/* Get all scholarship applications */
$applicationsQuery = $pdo->query(
    'SELECT
        sa.scholarship_application_id,
        sa.application_status,
        sa.remarks,
        sa.application_date,
        sa.screening_date,
        sa.screening_time,
        sa.screening_venue,
        sp.program_name,
        st.first_name,
        st.middle_name,
        st.last_name,
        st.lrn,
        sy.school_year
     FROM scholarship_applications sa
     INNER JOIN special_programs sp ON sp.program_id = sa.program_id
     INNER JOIN students st ON st.student_id = sa.student_id
     INNER JOIN school_years sy ON sy.school_year_id = sa.school_year_id
     ORDER BY sa.application_date DESC'
);

$applications = $applicationsQuery->fetchAll();

/* Get scholarship supporting documents */
$documentsByApplication = [];

$documentsQuery = $pdo->query(
    'SELECT
        scholarship_application_id,
        document_name,
        other_document
     FROM scholarship_documents
     ORDER BY scholarship_document_id'
);

foreach ($documentsQuery->fetchAll() as $document) {
    $applicationId = (int) $document['scholarship_application_id'];
    $documentsByApplication[$applicationId][] = $document;
}

require_once __DIR__ . '/../includes/header.php';
?>

<section class="form-card form-card-wide">

    <h1>Scholarship Applications</h1>

    <p class="muted">
        Review applicants, shortlist students for screening,
        assign their screening schedule, and record their review.
    </p>

    <?php if ($success): ?>
        <p class="success-message"><?= e($success) ?></p>
    <?php endif; ?>

    <?php if ($error): ?>
        <p class="error-message"><?= e($error) ?></p>
    <?php endif; ?>

    <?php if (!$applications): ?>

        <div class="empty-state">
            <h3>No Scholarship Applications</h3>
            <p class="muted">
                No students have submitted scholarship applications yet.
            </p>
        </div>

    <?php else: ?>

        <style>
            .applicant-card-list {
                display: grid;
                gap: 18px;
                margin-top: 20px;
            }

            .applicant-card {
                border: 1px solid var(--line);
                border-radius: 12px;
                padding: 20px;
                background: #fff;
            }

            .applicant-card-header {
                display: flex;
                flex-wrap: wrap;
                justify-content: space-between;
                gap: 12px;
                padding-bottom: 14px;
                border-bottom: 1px solid var(--line);
                margin-bottom: 16px;
            }

            .applicant-card-header h2 {
                margin: 0 0 5px;
                color: var(--maroon);
                font-size: 1.15rem;
            }

            .applicant-number {
                display: inline-block;
                margin-bottom: 8px;
                padding: 5px 10px;
                border-radius: 20px;
                background: #f8f0f3;
                color: var(--maroon-dark);
                font-size: 0.85rem;
                font-weight: 700;
            }

            .applicant-info-grid {
                display: grid;
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 10px;
                margin-bottom: 16px;
            }

            .applicant-info-item {
                padding: 10px 12px;
                border: 1px solid var(--line);
                border-radius: 8px;
            }

            .applicant-info-item small {
                display: block;
                color: var(--muted);
                margin-bottom: 3px;
            }

            .applicant-documents {
                padding: 13px 15px;
                background: #f8f8fa;
                border: 1px solid var(--line);
                border-radius: 8px;
                margin: 14px 0 18px;
            }

            .applicant-documents ul {
                margin: 8px 0 0;
                padding-left: 20px;
            }

            .applicant-documents li {
                margin-bottom: 5px;
            }

            .applicant-review-form {
                border-top: 1px solid var(--line);
                padding-top: 16px;
            }

            .screening-schedule-fieldset {
                margin: 14px 0;
            }

            @media (max-width: 700px) {
                .applicant-info-grid {
                    grid-template-columns: 1fr;
                }
            }
        </style>

        <div class="applicant-card-list">

            <?php $counter = 1; ?>

            <?php foreach ($applications as $application): ?>

                <article class="applicant-card">

                    <!-- Applicant Number and Student Information -->
                    <div class="applicant-card-header">

                        <div>
                            <span class="applicant-number">
                                Applicant #<?= $counter ?>
                            </span>

                            <h2>
                                <?= e(trim(
                                    $application['first_name'] . ' ' .
                                    ($application['middle_name'] ?? '') . ' ' .
                                    $application['last_name']
                                )) ?>
                            </h2>

                            <small>
                                LRN: <?= e($application['lrn'] ?: 'Not recorded') ?>
                            </small>
                        </div>

                        <strong>
                            <?= e($application['application_status']) ?>
                        </strong>

                    </div>

                    <!-- Application Information -->
                    <div class="applicant-info-grid">

                        <div class="applicant-info-item">
                            <small>Scholarship Program</small>
                            <strong><?= e($application['program_name']) ?></strong>
                        </div>

                        <div class="applicant-info-item">
                            <small>School Year</small>
                            <strong><?= e($application['school_year']) ?></strong>
                        </div>

                        <div class="applicant-info-item">
                            <small>Application Date</small>
                            <strong><?= e($application['application_date']) ?></strong>
                        </div>

                    </div>

                    <!-- Scholarship Supporting Documents -->
                    <div class="applicant-documents">

                        <strong>Scholarship Supporting Documents</strong>

                        <?php
                        $applicationDocuments =
                            $documentsByApplication[
                                (int) $application['scholarship_application_id']
                            ] ?? [];
                        ?>

                        <?php if ($applicationDocuments): ?>

                            <ul>
                                <?php foreach ($applicationDocuments as $document): ?>

                                    <li>
                                        <?= e($document['document_name']) ?>

                                        <?php if (
                                            $document['document_name'] === 'Other (Specify)'
                                            && !empty($document['other_document'])
                                        ): ?>
                                            : <?= e($document['other_document']) ?>
                                        <?php endif; ?>
                                    </li>

                                <?php endforeach; ?>
                            </ul>

                        <?php else: ?>

                            <p class="muted">
                                No supporting documents selected.
                            </p>

                        <?php endif; ?>

                    </div>

                    <!-- Review and Screening -->
                    <form method="post" class="applicant-review-form">

                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?= e(csrf_token()) ?>"
                        >

                        <input
                            type="hidden"
                            name="scholarship_application_id"
                            value="<?= (int) $application['scholarship_application_id'] ?>"
                        >

                        <h3>Review and Screening</h3>

                        <label>
                            Application Status

                            <select
                                name="application_status"
                                class="application-status-select"
                                required
                            >
                                <?php foreach ($statuses as $status): ?>

                                    <option
                                        value="<?= e($status) ?>"
                                        <?= $application['application_status'] === $status ? 'selected' : '' ?>
                                    >
                                        <?= e($status) ?>
                                    </option>

                                <?php endforeach; ?>
                            </select>
                        </label>

                        <!-- Only visible when status is For Screening -->
                        <fieldset
                            class="screening-schedule-fieldset"
                            <?= $application['application_status'] === 'For Screening' ? '' : 'hidden' ?>
                        >

                            <legend>
                                Screening Schedule (required for For Screening)
                            </legend>

                            <label>
                                Date

                                <input
                                    type="date"
                                    name="screening_date"
                                    value="<?= e($application['screening_date'] ?? '') ?>"
                                >
                            </label>

                            <label>
                                Time

                                <input
                                    type="time"
                                    name="screening_time"
                                    value="<?= e(
                                        $application['screening_time']
                                        ? substr($application['screening_time'], 0, 5)
                                        : ''
                                    ) ?>"
                                >
                            </label>

                            <label>
                                Venue

                                <input
                                    type="text"
                                    name="screening_venue"
                                    maxlength="180"
                                    value="<?= e($application['screening_venue'] ?? '') ?>"
                                    placeholder="Enter screening venue"
                                >
                            </label>

                        </fieldset>

                        <label>
                            Remarks

                            <textarea
                                name="remarks"
                                rows="2"
                                placeholder="Review or screening remarks"
                            ><?= e($application['remarks'] ?? '') ?></textarea>
                        </label>

                        <button type="submit" class="button button-primary">
                            Save Review
                        </button>

                    </form>

                </article>

                <?php $counter++; ?>

            <?php endforeach; ?>

        </div>

        <script>
            // Show the screening schedule only when the status is For Screening.
            document.querySelectorAll('.applicant-review-form').forEach(function(form) {

                var statusSelect = form.querySelector('.application-status-select');
                var screeningSchedule = form.querySelector('.screening-schedule-fieldset');

                function toggleScreeningSchedule() {

                    if (statusSelect.value === 'For Screening') {
                        screeningSchedule.hidden = false;
                    } else {
                        screeningSchedule.hidden = true;
                    }

                }

                statusSelect.addEventListener('change', toggleScreeningSchedule);

                // Check the current status when the page loads.
                toggleScreeningSchedule();

            });
        </script>

    <?php endif; ?>

</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
