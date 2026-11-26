
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

$printReport = isset($_GET['print']);
$downloadReport = isset($_GET['download']);

if ($printReport || $downloadReport) {
    if (!$applications) {
        http_response_code(404);
        exit('No scholarship applications found.');
    }

    $generatedDate = date('F d, Y h:i A');

    if ($downloadReport) {
        header('Content-Type: application/msword; charset=utf-8');
        header('Content-Disposition: attachment; filename="scholarship_applications_' . date('Ymd_His') . '.doc"');
        header('Cache-Control: max-age=0');
        header('X-Content-Type-Options: nosniff');
    } else {
        header('Content-Type: text/html; charset=utf-8');
    }
    ?>
    <!DOCTYPE html>
    <html lang="en" xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:w="urn:schemas-microsoft-com:office:word">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Scholarship Applications Report</title>
        <style>
            body {
                margin: 0;
                padding: 30px;
                background: #f3f4f6;
                color: #222;
                font-family: Arial, Helvetica, sans-serif;
            }

            .report-page {
                max-width: 1050px;
                margin: 0 auto;
                padding: 38px;
                background: #fff;
                border: 1px solid #ddd;
            }

            .report-header {
                padding-bottom: 20px;
                text-align: center;
                border-bottom: 3px solid #64152f;
            }

            .school-name {
                margin: 0;
                color: #64152f;
                font-size: 23px;
                font-weight: bold;
                text-transform: uppercase;
            }

            .school-subtitle {
                margin: 5px 0 0;
                font-size: 12px;
                letter-spacing: 1px;
            }

            .report-title {
                margin: 25px 0 15px;
                text-align: center;
                font-size: 20px;
                text-transform: uppercase;
            }

            .report-meta {
                display: flex;
                justify-content: space-between;
                gap: 12px;
                margin: 18px 0;
                font-size: 12px;
            }

            .application-report-card {
                margin: 18px 0;
                padding: 16px;
                border: 1px solid #ccc;
                page-break-inside: avoid;
            }

            .application-report-card h3 {
                margin: 0 0 5px;
                color: #64152f;
                font-size: 16px;
            }

            .application-report-card table {
                width: 100%;
                margin-top: 12px;
                border-collapse: collapse;
            }

            .application-report-card th,
            .application-report-card td {
                padding: 8px;
                border: 1px solid #ccc;
                text-align: left;
                vertical-align: top;
                font-size: 11px;
                overflow-wrap: anywhere;
            }

            .application-report-card th {
                width: 27%;
                background: #f2e9ed;
                color: #4b1020;
            }

            .report-footer {
                margin-top: 25px;
                padding-top: 12px;
                border-top: 1px solid #ccc;
                color: #666;
                font-size: 10px;
            }

            .report-actions {
                max-width: 1050px;
                margin: 0 auto 15px;
                text-align: right;
            }

            .report-actions button {
                padding: 10px 18px;
                border: 0;
                border-radius: 5px;
                background: #64152f;
                color: #fff;
                cursor: pointer;
            }

            @media print {
                @page {
                    size: portrait;
                    margin: 12mm;
                }

                body {
                    padding: 0;
                    background: #fff;
                }

                .report-page {
                    max-width: none;
                    padding: 0;
                    border: 0;
                }

                .report-actions {
                    display: none;
                }
            }
        </style>
    </head>

    <body>

        <?php if (!$downloadReport): ?>

            <div class="report-actions">
                <button type="button" onclick="window.print()">
                    Print Report
                </button>
            </div>

        <?php endif; ?>

        <main class="report-page">

            <header class="report-header">
                <h1 class="school-name">
                    Baguio City National High School
                </h1>

                <p class="school-subtitle">
                    OFFICIAL SCHOOL RECORDS
                </p>
            </header>

            <h2 class="report-title">
                Scholarship Applications Report
            </h2>

            <div class="report-meta">

                <span>
                    <strong>Total Applications:</strong>
                    <?= count($applications) ?>
                </span>

                <span>
                    <strong>Generated:</strong>
                    <?= e($generatedDate) ?>
                </span>

            </div>

            <?php foreach ($applications as $index => $application): ?>

                <?php
                $applicantName = trim(
                    $application['first_name'] . ' ' .
                    ($application['middle_name'] ?? '') . ' ' .
                    $application['last_name']
                );

                $applicationDocuments = $documentsByApplication[
                    (int) $application['scholarship_application_id']
                ] ?? [];
                ?>

                <section class="application-report-card">

                    <h3>
                        Applicant #<?= $index + 1 ?> - <?= e($applicantName) ?>
                    </h3>

                    <table>

                        <tr>
                            <th>LRN</th>
                            <td>
                                <?= e($application['lrn'] ?: 'Not recorded') ?>
                            </td>
                        </tr>

                        <tr>
                            <th>Scholarship Program</th>
                            <td>
                                <?= e($application['program_name']) ?>
                            </td>
                        </tr>

                        <tr>
                            <th>School Year</th>
                            <td>
                                <?= e($application['school_year']) ?>
                            </td>
                        </tr>

                        <tr>
                            <th>Application Date</th>
                            <td>
                                <?= e($application['application_date']) ?>
                            </td>
                        </tr>

                        <tr>
                            <th>Application Status</th>
                            <td>
                                <?= e($application['application_status']) ?>
                            </td>
                        </tr>

                        <tr>
                            <th>Screening Date</th>
                            <td>
                                <?= e($application['screening_date'] ?: 'Not scheduled') ?>
                            </td>
                        </tr>

                        <tr>
                            <th>Screening Time</th>
                            <td>
                                <?= e(
                                    $application['screening_time']
                                    ? date('h:i A', strtotime($application['screening_time']))
                                    : 'Not scheduled'
                                ) ?>
                            </td>
                        </tr>

                        <tr>
                            <th>Screening Venue</th>
                            <td>
                                <?= e($application['screening_venue'] ?: 'Not assigned') ?>
                            </td>
                        </tr>

                        <tr>
                            <th>Supporting Documents</th>
                            <td>

                                <?php if ($applicationDocuments): ?>

                                    <?php foreach ($applicationDocuments as $document): ?>

                                        <?= e($document['document_name']) ?>

                                        <?php if (
                                            $document['document_name'] === 'Other (Specify)'
                                            && !empty($document['other_document'])
                                        ): ?>
                                            : <?= e($document['other_document']) ?>
                                        <?php endif; ?>

                                        <br>

                                    <?php endforeach; ?>

                                <?php else: ?>

                                    No supporting documents selected.

                                <?php endif; ?>

                            </td>
                        </tr>

                        <tr>
                            <th>Review Remarks</th>
                            <td>
                                <?= nl2br(e($application['remarks'] ?: 'No remarks')) ?>
                            </td>
                        </tr>

                    </table>

                </section>

            <?php endforeach; ?>

            <div class="report-footer">
                Baguio City National High School |
                Scholarship Applications |
                <?= count($applications) ?> record(s)
            </div>

        </main>

        <?php if ($printReport): ?>

            <script>
                window.onload = function () {
                    window.print();
                };
            </script>

        <?php endif; ?>

    </body>
    </html>
    <?php
    exit;
}

require_once __DIR__ . '/../includes/header.php';
?>

<section class="form-card form-card-wide">

    <div class="scholarship-applications-heading">

        <div>
            <h1>Scholarship Applications</h1>

            <p class="muted">
                Review applicants, shortlist students for screening,
                assign their screening schedule, and record their review.
            </p>
        </div>

        <?php if ($applications): ?>

            <div class="scholarship-report-actions">

                <a
                    href="<?= BASE_URL ?>/scholarship_coordinator/scholarship_applications.php?print=1"
                    class="button button-outline scholarship-icon-button"
                    target="_blank"
                    rel="noopener"
                    title="Print Applications"
                    aria-label="Print Applications"
                >
                    <svg
                        viewBox="0 0 24 24"
                        width="20"
                        height="20"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        aria-hidden="true"
                    >
                        <path d="M6 9V2h12v7"/>
                        <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/>
                        <path d="M6 14h12v8H6z"/>
                    </svg>
                </a>

                <a
                    href="<?= BASE_URL ?>/scholarship_coordinator/scholarship_applications.php?download=1"
                    class="button button-primary scholarship-icon-button"
                    title="Download Applications"
                    aria-label="Download Applications"
                >
                    <svg
                        viewBox="0 0 24 24"
                        width="20"
                        height="20"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        aria-hidden="true"
                    >
                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                        <path d="m7 10 5 5 5-5"/>
                        <path d="M12 15V3"/>
                    </svg>
                </a>

            </div>

        <?php endif; ?>

    </div>

    <?php if ($success): ?>

        <p class="success-message">
            <?= e($success) ?>
        </p>

    <?php endif; ?>

    <?php if ($error): ?>

        <p class="error-message">
            <?= e($error) ?>
        </p>

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
            .scholarship-applications-heading {
                display: flex;
                justify-content: space-between;
                align-items: flex-start;
                gap: 18px;
                flex-wrap: wrap;
            }

            .scholarship-applications-heading > div:first-child {
                flex: 1 1 360px;
            }

            .scholarship-applications-heading h1 {
                margin-top: 0;
            }

            .scholarship-report-actions {
                display: flex;
                align-items: center;
                justify-content: flex-end;
                gap: 8px;
                flex-wrap: wrap;
                margin-top: 4px;
            }

            .scholarship-icon-button {
                width: 42px;
                height: 42px;
                min-width: 42px;
                padding: 0;
                display: inline-flex;
                align-items: center;
                justify-content: center;
            }

            .scholarship-icon-button svg {
                display: block;
                flex-shrink: 0;
            }

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

                .scholarship-report-actions {
                    width: 100%;
                    justify-content: flex-start;
                }
            }
        </style>

        <div class="applicant-card-list">

            <?php $counter = 1; ?>

            <?php foreach ($applications as $application): ?>

                <article class="applicant-card">

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

                    <div class="applicant-info-grid">

                        <div class="applicant-info-item">
                            <small>Scholarship Program</small>
                            <strong>
                                <?= e($application['program_name']) ?>
                            </strong>
                        </div>

                        <div class="applicant-info-item">
                            <small>School Year</small>
                            <strong>
                                <?= e($application['school_year']) ?>
                            </strong>
                        </div>

                        <div class="applicant-info-item">
                            <small>Application Date</small>
                            <strong>
                                <?= e($application['application_date']) ?>
                            </strong>
                        </div>

                    </div>

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
                toggleScreeningSchedule();

            });
        </script>

    <?php endif; ?>

</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
