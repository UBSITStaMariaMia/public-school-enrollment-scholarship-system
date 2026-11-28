<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('System Administrator', 'Scholarship Coordinator');

$pageTitle = 'Scholarship Applications';
$error = '';
$success = '';
$statuses = ['Pending Review', 'For Screening', 'Approved', 'Rejected'];
$statusFilter = $_GET['status'] ?? '';
if (!in_array($statusFilter, $statuses, true)) {
    $statusFilter = '';
}

$search = is_string($_GET['q'] ?? null) ? mb_substr(trim($_GET['q']), 0, 150) : '';
$programFilter = filter_var($_GET['program'] ?? null, FILTER_VALIDATE_INT) ?: '';
$yearFilter = filter_var($_GET['year'] ?? null, FILTER_VALIDATE_INT) ?: '';
$filters = ['q' => $search, 'program' => $programFilter, 'year' => $yearFilter, 'status' => $statusFilter];
$page = max(1, filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT) ?: 1);
$reviewId = filter_var($_GET['review'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: null;
$listUrl = '?' . http_build_query($filters + ['page' => $page]);
$feedback = $_SESSION['scholarship_review_feedback'] ?? [];
$completedQueue = false;
if ($reviewId && ($feedback['review_id'] ?? null) === $reviewId) {
    $success = $feedback['message'];
    $completedQueue = $feedback['complete'];
    unset($_SESSION['scholarship_review_feedback']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
            throw new RuntimeException('Invalid request. Please refresh the page and try again.');
        }

        $applicationId = filter_var($_POST['scholarship_application_id'] ?? null, FILTER_VALIDATE_INT);
        if (!$reviewId || $applicationId !== $reviewId) {
            throw new RuntimeException('Open the scholarship application before saving its review.');
        }
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

        $savedReview = true;
        $continueReview = ($_POST['after_save'] ?? '') === 'next';

    } catch (PDOException $exception) {
        error_log('Scholarship application review: ' . $exception->getMessage());
        $error = 'Unable to update the application right now. Please try again.';

    } catch (RuntimeException $exception) {
        $error = $exception->getMessage();
    }
}

$where = [];
$params = [];
foreach (['sa.application_status' => $statusFilter, 'sa.program_id' => $programFilter, 'sa.school_year_id' => $yearFilter] as $field => $value) {
    if ($value !== '') {
        $key = 'filter_' . count($params);
        $where[] = "$field = :$key";
        $params[$key] = $value;
    }
}
if ($search !== '') {
    $searchPattern = '%' . strtr($search, ['!' => '!!', '%' => '!%', '_' => '!_']) . '%';
    $where[] = "(CONCAT_WS(' ', st.first_name, st.middle_name, st.last_name) LIKE :full_name ESCAPE '!'
        OR CONCAT_WS(' ', st.last_name, st.first_name, st.middle_name) LIKE :surname_first ESCAPE '!'
        OR CONCAT_WS(' ', st.first_name, st.last_name) LIKE :short_name ESCAPE '!'
        OR st.lrn LIKE :lrn ESCAPE '!')";
    $params += array_fill_keys(['full_name', 'surname_first', 'short_name', 'lrn'], $searchPattern);
}
$joins = '
     FROM scholarship_applications sa
     INNER JOIN special_programs sp ON sp.program_id = sa.program_id
     INNER JOIN students st ON st.student_id = sa.student_id
     INNER JOIN school_years sy ON sy.school_year_id = sa.school_year_id
';
if (!empty($savedReview)) {
    $nextId = null;
    if ($continueReview) {
        $nextWhere = $where;
        $nextWhere[] = '(sa.application_date, sa.scholarship_application_id) < (
            SELECT application_date, scholarship_application_id
            FROM scholarship_applications
            WHERE scholarship_application_id = :current_review
        )';
        $next = $pdo->prepare(
            'SELECT sa.scholarship_application_id ' . $joins . ' WHERE ' . implode(' AND ', $nextWhere)
            . ' ORDER BY sa.application_date DESC, sa.scholarship_application_id DESC LIMIT 1'
        );
        $next->execute($params + ['current_review' => $reviewId]);
        $nextId = $next->fetchColumn();
    }

    $targetId = (int) ($nextId ?: $reviewId);
    $_SESSION['scholarship_review_feedback'] = [
        'review_id' => $targetId,
        'message' => $nextId ? 'Previous review saved. Now reviewing the next applicant.' : 'Scholarship review saved.',
        'complete' => $continueReview && !$nextId,
    ];
    redirect('/scholarship_coordinator/scholarship_applications.php?' . http_build_query(
        $filters + ['page' => $page, 'review' => $targetId]
    ));
}
if ($reviewId) {
    // A saved review can change status and therefore leave the originating list.
    $where = ['sa.scholarship_application_id = :review'];
    $params = ['review' => $reviewId];
}
$whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';
$limit = '';
$total = 0;
$totalPages = 1;
if (!$reviewId) {
    $count = $pdo->prepare('SELECT COUNT(*) ' . $joins . $whereSql);
    $count->execute($params);
    $total = (int) $count->fetchColumn();
    $totalPages = max(1, (int) ceil($total / 10));
    $page = min($page, $totalPages);
    $offset = ($page - 1) * 10;
    $limit = " LIMIT 10 OFFSET $offset";
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
        sp.program_name,
        st.first_name,
        st.middle_name,
        st.last_name,
        st.lrn,
        sy.school_year
    ' . $joins . $whereSql . ' ORDER BY sa.application_date DESC, sa.scholarship_application_id DESC' . $limit
);
$applicationsQuery->execute($params);

$applications = $applicationsQuery->fetchAll();
$documentsByApplication = [];

$documentDataAvailable = true;
try {
    $applicationIds = array_column($applications, 'scholarship_application_id');
    if ($applicationIds && $reviewId) {
        $placeholders = implode(', ', array_fill(0, count($applicationIds), '?'));
        $documentsQuery = $pdo->prepare(
        'SELECT
            scholarship_application_id,
            document_name,
            other_document
         FROM scholarship_documents
         WHERE scholarship_application_id IN (' . $placeholders . ')
         ORDER BY scholarship_document_id'
    );

    $documentsQuery->execute($applicationIds);
    foreach ($documentsQuery->fetchAll() as $document) {
        $applicationId = (int) $document['scholarship_application_id'];
        $documentsByApplication[$applicationId][] = $document;
    }
    }
} catch (PDOException $exception) {
    if ($exception->getCode() !== '42S02') {
        throw $exception;
    }
    $documentDataAvailable = false;
}

require_once __DIR__ . '/../includes/header.php';
?>

<?php if ($reviewId): ?>
<div class="portal-back-row"><a class="portal-back-link" href="<?= e($listUrl) ?>">Back to scholarship applications</a></div>
<?php endif; ?>
<section class="form-card form-card-wide">

    <h1><?= $reviewId ? 'Review Scholarship Application' : 'Scholarship Applications' ?></h1>

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

    <?php if (!$reviewId): ?>
    <nav class="application-status-links" aria-label="Scholarship application status">
        <?php foreach (array_merge([''], $statuses) as $quickStatus): ?>
            <a class="button <?= $statusFilter === $quickStatus ? 'button-primary' : 'button-outline' ?>"
               href="?<?= e(http_build_query(['status' => $quickStatus] + $filters)) ?>"
               <?= $statusFilter === $quickStatus ? 'aria-current="true"' : '' ?>>
                <?= e($quickStatus ?: 'All applications') ?>
            </a>
        <?php endforeach; ?>
    </nav>

    <form method="get" class="table-search-form">
        <input type="hidden" name="status" value="<?= e($statusFilter) ?>">
        <div class="table-search-toolbar">
            <label class="table-search-field">Search by name or LRN
                <input type="search" name="q" placeholder="Enter applicant name or LRN" value="<?= e($search) ?>">
            </label>
            <button type="submit" class="button button-primary">Search</button>
            <details class="table-search-disclosure">
                <summary class="button button-outline">Filters
                    <?php if ($programFilter || $yearFilter): ?>
                        <span class="scholarship-filter-active">Active</span>
                    <?php endif; ?>
                </summary>
                <div class="table-search-panel">
                    <div class="table-search-fields">
                        <?php foreach ([
                            ['program', 'Program', $programFilter, 'SELECT program_id, program_name FROM special_programs ORDER BY program_name'],
                            ['year', 'School Year', $yearFilter, 'SELECT school_year_id, school_year FROM school_years ORDER BY school_year DESC'],
                        ] as [$key, $label, $selected, $optionSql]): ?>
                            <label><?= e($label) ?>
                                <select name="<?= e($key) ?>">
                                    <option value="">All</option>
                                    <?php foreach ($pdo->query($optionSql)->fetchAll(PDO::FETCH_NUM) as [$id, $name]): ?>
                                        <option value="<?= (int) $id ?>" <?= (int) $id === $selected ? 'selected' : '' ?>><?= e($name) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <div class="table-search-actions">
                        <button class="button button-primary" type="submit">Apply filters</button>
                        <a class="button button-outline" href="?<?= e(http_build_query(['status' => $statusFilter])) ?>">Clear filters</a>
                    </div>
                </div>
            </details>
        </div>
    </form>
    <p class="muted"><?= $total ?> matching applications</p>

    <?php if ($applications): ?>
        <div class="table-responsive">
            <table class="portal-row-table">
                <thead><tr><th scope="col">Applicant</th><th scope="col">Program</th><th scope="col">School Year</th><th scope="col">Status</th><th scope="col">Actions</th></tr></thead>
                <tbody>
                <?php foreach ($applications as $application): ?>
                    <tr>
                        <td><?= e($application['last_name'] . ', ' . trim($application['first_name'] . ' ' . ($application['middle_name'] ?? ''))) ?></td>
                        <td><?= e($application['program_name']) ?></td>
                        <td><?= e($application['school_year']) ?></td>
                        <td><?= e($application['application_status']) ?></td>
                        <td><a class="button button-outline" href="?<?= e(http_build_query($filters + ['page' => $page, 'review' => $application['scholarship_application_id']])) ?>">Review application</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if ($totalPages > 1): ?>
            <nav class="portal-list-toolbar" aria-label="Scholarship application pages">
                <?php if ($page > 1): ?><a class="button button-outline" href="?<?= e(http_build_query($filters + ['page' => $page - 1])) ?>">Previous</a><?php endif; ?>
                <span>Page <?= $page ?> of <?= $totalPages ?></span>
                <?php if ($page < $totalPages): ?><a class="button button-outline" href="?<?= e(http_build_query($filters + ['page' => $page + 1])) ?>">Next</a><?php endif; ?>
            </nav>
        <?php endif; ?>
    <?php else: ?>
        <p>No applications match your search and filters.</p>
    <?php endif; ?>
    <?php elseif (!$applications): ?>
        <p>Application not found.</p>
    <?php endif; ?>

    <?php if ($reviewId && $applications): ?>
        <style>





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

            }
        </style>

        <div class="applicant-card-list">

            <?php foreach ($applications as $application): ?>

                <article class="applicant-card">

                    <div class="applicant-card-header">

                        <div>

                            <span class="applicant-number">
                                Application #<?= (int) $application['scholarship_application_id'] ?>
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
                                <?= $documentDataAvailable ? 'No supporting documents selected.' : 'Document information is unavailable in this installation.' ?>
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

                        <div class="scholarship-form-footer">
                            <button type="submit" class="button button-primary">Save Review</button>
                            <button type="submit" name="after_save" value="next" class="button button-outline">Save and Review Next</button>
                        </div>

                    </form>

                </article>

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

<?php if ($completedQueue): ?>
    <dialog class="portal-delete-dialog" id="scholarshipQueueComplete" aria-labelledby="scholarshipQueueTitle">
        <h2 id="scholarshipQueueTitle">Review queue complete</h2>
        <p>Your review was saved. There are no more applications in this filtered list.</p>
        <form method="dialog" class="portal-delete-actions">
            <button class="button button-primary" autofocus>OK</button>
        </form>
    </dialog>
    <script>document.getElementById('scholarshipQueueComplete').showModal();</script>
<?php endif; ?>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
