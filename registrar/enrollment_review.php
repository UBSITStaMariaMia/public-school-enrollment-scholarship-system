<?php
require_once __DIR__ . '/../includes/bootstrap.php';

require_role('School Registrar');

$isReviewDetail = defined('ENROLLMENT_REVIEW_DETAIL');
$reviewId = filter_var($_GET['application_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$pageTitle = $isReviewDetail ? 'Review Application and Documents' : 'Enrollment Review';
$error = '';
$success = '';

$allowedStatuses = [
    'Pending Review',
    'Incomplete Requirements',
    'Approved',
    'Rejected'
];

$documentStatuses = ['For Verification', 'Accepted', 'Rejected'];

$requiredDocuments = [
    'PSA Birth Certificate',
    'Old School Card (before moving up)',
    'Good Moral Certificate'
];

$perPage = 10;

/*
 * Read and validate list filters.
 */
$search = is_string($_GET['q'] ?? null) ? mb_substr(trim($_GET['q']), 0, 150) : '';
$statusFilter = is_string($_GET['status'] ?? null) ? trim($_GET['status']) : '';
$schoolYearFilter = filter_var(
    $_GET['school_year'] ?? '',
    FILTER_VALIDATE_INT
);
$gradeFilter = filter_var($_GET['grade'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: '';
$page = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT);

$documentFilter = is_string($_GET['document_status'] ?? null) ? $_GET['document_status'] : '';
if (!in_array($documentFilter, array_merge($documentStatuses, ['Not Available']), true)) {
    $documentFilter = '';
}
$sort = is_string($_GET['sort'] ?? null) ? $_GET['sort'] : 'newest';
$allowedSorts = [
    'newest' => 'ea.submitted_at DESC, ea.application_id DESC',
    'oldest' => 'ea.submitted_at ASC, ea.application_id ASC'
];

if (!in_array($statusFilter, $allowedStatuses, true)) {
    $statusFilter = '';
}

if ($schoolYearFilter === false || $schoolYearFilter === null || $schoolYearFilter < 1) {
    $schoolYearFilter = '';
}

if (!isset($allowedSorts[$sort])) {
    $sort = 'newest';
}

if ($page === false || $page === null || $page < 1) {
    $page = 1;
}

/*
 * Process registrar review submissions.
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$isReviewDetail) {
        http_response_code(405);
        header('Allow: GET, HEAD');
        exit('Open an application to save its review.');
    }
    try {
        $token = $_POST['csrf_token'] ?? null;
        if (!is_string($token) || !verify_csrf_token($token)) {
            throw new RuntimeException('Invalid request. Refresh the page.');
        }

        $id = filter_var($_POST['application_id'] ?? null, FILTER_VALIDATE_INT);
        $status = $_POST['application_status'] ?? '';
        $remarks = $_POST['remarks'] ?? '';
        $documentChanges = $_POST['documents'] ?? [];
        if (!is_string($remarks) || !is_array($documentChanges)) {
            throw new RuntimeException('Enter valid review information.');
        }
        $remarks = trim($remarks);
        $section = ($_POST['assigned_section_id'] ?? '') !== ''
            ? filter_var($_POST['assigned_section_id'], FILTER_VALIDATE_INT)
            : null;

        if (!$id || $id !== $reviewId || !in_array($status, $allowedStatuses, true)) {
            throw new RuntimeException('Select a valid application and status.');
        }

        if ($section === false) {
            throw new RuntimeException('Select a valid section.');
        }

        $pdo->beginTransaction();
        $check = $pdo->prepare(
            'SELECT application_id, enrollment_type, requested_grade_level_id, school_year_id
             FROM enrollment_applications
             WHERE application_id = :id
             FOR UPDATE'
        );
        $check->execute(['id' => $id]);

        $reviewApplication = $check->fetch();
        if (!$reviewApplication) {
            throw new RuntimeException('Application not found.');
        }

        $requestedGradeId = (int) $reviewApplication['requested_grade_level_id'];
        if ($reviewApplication['enrollment_type'] === 'Incoming Grade 7') {
            $requestedGradeId = (int) $pdo->query('SELECT grade_level_id FROM grade_levels WHERE grade_level = 7')->fetchColumn();
            if (!$requestedGradeId) {
                throw new RuntimeException('Grade 7 is not configured.');
            }
        }

        if ($section) {
            $sectionCheck = $pdo->prepare(
                'SELECT section_id
                 FROM sections
                 WHERE section_id = :id AND grade_level_id = :grade_id AND school_year_id = :year_id'
            );
            $sectionCheck->execute([
                'id' => $section,
                'grade_id' => $requestedGradeId,
                'year_id' => $reviewApplication['school_year_id']
            ]);

            if (!$sectionCheck->fetch()) {
                throw new RuntimeException('Select a section from the requested grade and school year.');
            }
        }

        if ($documentChanges) {
            $documentCheck = $pdo->prepare(
                'SELECT document_id, document_type, verification_status, remarks
                 FROM enrollment_documents
                 WHERE application_id = :application_id
                 FOR UPDATE'
            );
            $documentCheck->execute(['application_id' => $id]);
            $applicationDocuments = array_column($documentCheck->fetchAll(), null, 'document_id');
            $documentUpdate = $pdo->prepare(
                'UPDATE enrollment_documents
                 SET verification_status = :status, remarks = :remarks
                 WHERE document_id = :document_id AND application_id = :application_id'
            );

            foreach ($documentChanges as $documentId => $change) {
                $document = $applicationDocuments[$documentId] ?? null;
                if (!$document || !in_array($document['document_type'], $requiredDocuments, true) || !is_array($change)) {
                    throw new RuntimeException('A document does not belong to this application.');
                }

                $documentStatus = $change['status'] ?? null;
                $documentRemarks = $change['remarks'] ?? null;
                if (!in_array($documentStatus, $documentStatuses, true) || !is_string($documentRemarks)) {
                    throw new RuntimeException('Select a valid verification status for each document.');
                }
                $documentRemarks = trim($documentRemarks);
                if ($documentStatus === 'Rejected' && $documentRemarks === '') {
                    throw new RuntimeException('Enter remarks for each rejected document.');
                }
                $documentRemarks = $documentRemarks !== '' ? $documentRemarks : null;
                $savedStatus = in_array($document['verification_status'], ['Not Submitted', 'Submitted'], true)
                    ? 'For Verification'
                    : $document['verification_status'];
                if ($documentStatus !== $savedStatus || $documentRemarks !== $document['remarks']) {
                    $documentUpdate->execute([
                        'status' => $documentStatus,
                        'remarks' => $documentRemarks,
                        'document_id' => $documentId,
                        'application_id' => $id
                    ]);
                }
            }
        }

        $stmt = $pdo->prepare(
            'UPDATE enrollment_applications
             SET application_status = :status,
                 remarks = :remarks,
                 assigned_section_id = :section,
                 requested_grade_level_id = :requested_grade_id,
                 reviewed_by = :reviewer,
                 reviewed_at = NOW()
             WHERE application_id = :id'
        );

        $stmt->execute([
            'status' => $status,
            'remarks' => $remarks !== '' ? $remarks : null,
            'section' => $section ?: null,
            'requested_grade_id' => $requestedGradeId,
            'reviewer' => current_user()['user_id'],
            'id' => $id
        ]);

        $pdo->commit();
        $success = 'Application review and document verification saved.';
    } catch (PDOException $ex) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Enrollment review: ' . $ex->getMessage());
        $error = 'Unable to update the application. Check the selected section and related records.';
    } catch (RuntimeException $ex) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = $ex->getMessage();
    }
}

/*
 * Retrieve sections and school-year options for the filters.
 */
$schoolYears = $pdo->query(
    'SELECT school_year_id, school_year
     FROM school_years
     ORDER BY school_year_id DESC'
)->fetchAll();

/*
 * Build one reusable WHERE clause for the result list and total count.
 */
$where = [];
$params = [];

if (preg_match('/^[0-9]{12}$/', $search)) {
    $where[] = '(st.lrn = :student_lrn OR ea.lrn = :application_lrn)';
    $params['student_lrn'] = $search;
    $params['application_lrn'] = $search;
} elseif ($search !== '') {
    $searchTerm = '%' . strtr($search, ['!' => '!!', '%' => '!%', '_' => '!_']) . '%';
    $where[] = "(
        CONCAT_WS(' ', st.first_name, NULLIF(st.middle_name, ''), st.last_name) LIKE :search_name ESCAPE '!'
        OR CONCAT_WS(' ', st.first_name, st.last_name) LIKE :search_short_name ESCAPE '!'
        OR st.middle_name LIKE :search_middle ESCAPE '!'
        OR u.email LIKE :search_email ESCAPE '!'
        OR ea.lrn LIKE :search_lrn ESCAPE '!'
    )";
    foreach (['search_name', 'search_short_name', 'search_middle', 'search_email', 'search_lrn'] as $parameter) {
        $params[$parameter] = $searchTerm;
    }
}

if ($gradeFilter !== '') {
    $where[] = 'g.grade_level_id = :grade_filter';
    $params['grade_filter'] = $gradeFilter;
}

if ($statusFilter !== '') {
    $where[] = 'ea.application_status = :status_filter';
    $params['status_filter'] = $statusFilter;
}

if ($schoolYearFilter !== '') {
    $where[] = 'ea.school_year_id = :school_year_filter';
    $params['school_year_filter'] = $schoolYearFilter;
}

$gradeLevels = $pdo->query(
    'SELECT grade_level_id, grade_level FROM grade_levels ORDER BY grade_level_id'
)->fetchAll();

if ($documentFilter === 'Not Available') {
    $where[] = "(
        SELECT COUNT(DISTINCT ed_filter.document_type)
        FROM enrollment_documents ed_filter
        WHERE ed_filter.application_id = ea.application_id
          AND ed_filter.document_type IN (
              'PSA Birth Certificate',
              'Old School Card (before moving up)',
              'Good Moral Certificate'
          )
    ) < 3";
} elseif ($documentFilter !== '') {
    $where[] = "EXISTS (
        SELECT 1
        FROM enrollment_documents ed_filter
        WHERE ed_filter.application_id = ea.application_id
          AND ed_filter.document_type IN (
              'PSA Birth Certificate',
              'Old School Card (before moving up)',
              'Good Moral Certificate'
          )
          AND (
              ed_filter.verification_status = :verification_filter
              OR (
                  :verification_filter_legacy = 'For Verification'
                  AND ed_filter.verification_status IN ('Not Submitted', 'Submitted')
              )
          )
    )";
    $params['verification_filter'] = $documentFilter;
    $params['verification_filter_legacy'] = $documentFilter;
}

$gradeLevels = $pdo->query(
    'SELECT grade_level_id, grade_level FROM grade_levels ORDER BY grade_level_id'
)->fetchAll();

if ($isReviewDetail) {
    $where = ['ea.application_id = :review_id'];
    $params = ['review_id' => $reviewId ?: 0];
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

/*
 * Summary counts cover all applications, independent of the active filters.
 */
$statusCounts = [];
if (!$isReviewDetail) {
    $statusCounts = array_map('intval', $pdo->query(
        'SELECT application_status, COUNT(*) FROM enrollment_applications GROUP BY application_status'
    )->fetchAll(PDO::FETCH_KEY_PAIR));
}

/*
 * Count matching applications, then fetch only the requested page.
 */
$countSql = "
    SELECT COUNT(*)
    FROM enrollment_applications ea
    JOIN students st ON st.student_id = ea.student_id
    JOIN users u ON u.user_id = st.user_id
    JOIN grade_levels g ON g.grade_level_id = CASE
        WHEN ea.enrollment_type = 'Incoming Grade 7' THEN (SELECT grade_level_id FROM grade_levels WHERE grade_level = 7 LIMIT 1)
        ELSE ea.requested_grade_level_id END
    JOIN school_years y ON y.school_year_id = ea.school_year_id
    $whereSql
";

$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$filteredCount = (int) $countStmt->fetchColumn();

$totalPages = max(1, (int) ceil($filteredCount / $perPage));
$returnPage = $page;
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$applicationsSql = "
    SELECT
        ea.application_id,
        ea.lrn,
        ea.enrollment_type,
        ea.application_status,
        ea.remarks,
        ea.submitted_at,
        ea.assigned_section_id,
        st.first_name,
        st.middle_name,
        st.last_name,
        u.email,
        g.grade_level,
        g.grade_level_id AS review_grade_id,
        ea.school_year_id,
        y.school_year
    FROM enrollment_applications ea
    JOIN students st ON st.student_id = ea.student_id
    JOIN users u ON u.user_id = st.user_id
    JOIN grade_levels g ON g.grade_level_id = CASE
        WHEN ea.enrollment_type = 'Incoming Grade 7' THEN (SELECT grade_level_id FROM grade_levels WHERE grade_level = 7 LIMIT 1)
        ELSE ea.requested_grade_level_id END
    JOIN school_years y ON y.school_year_id = ea.school_year_id
    $whereSql
    ORDER BY {$allowedSorts[$sort]}
    LIMIT {$perPage} OFFSET {$offset}
";

$applicationsStmt = $pdo->prepare($applicationsSql);
$applicationsStmt->execute($params);
$applications = $applicationsStmt->fetchAll();
$sections = [];
if ($isReviewDetail && $applications) {
    $sectionList = $pdo->prepare(
        "SELECT s.section_id, s.section_name,
                (SELECT COUNT(*) FROM enrollment_applications ea
                 WHERE ea.assigned_section_id = s.section_id
                   AND ea.school_year_id = s.school_year_id
                   AND ea.application_status = 'Approved') AS enrolled_count
         FROM sections s
         WHERE s.grade_level_id = :grade_id AND s.school_year_id = :year_id
         ORDER BY s.section_name"
    );
    $sectionList->execute([
        'grade_id' => $applications[0]['review_grade_id'],
        'year_id' => $applications[0]['school_year_id']
    ]);
    $sections = $sectionList->fetchAll();
} elseif ($isReviewDetail) {
    http_response_code(404);
}

$applicationIds = array_map(
    static fn(array $application): int => (int) $application['application_id'],
    $applications
);

$documentsByApplication = [];

if ($isReviewDetail && $applicationIds) {
    $placeholders = implode(',', array_fill(0, count($applicationIds), '?'));

    $documentsQuery = $pdo->prepare(
        "SELECT
            document_id,
            application_id,
            document_type,
            verification_status,
            remarks
         FROM enrollment_documents
         WHERE application_id IN ($placeholders)
           AND document_type IN (
               'PSA Birth Certificate',
               'Old School Card (before moving up)',
               'Good Moral Certificate'
           )"
    );
    $documentsQuery->execute($applicationIds);

    foreach ($documentsQuery->fetchAll() as $document) {
        $documentsByApplication[(int) $document['application_id']][$document['document_type']] = $document;
    }
}

$firstResult = $filteredCount > 0 ? $offset + 1 : 0;
$lastResult = min($offset + $perPage, $filteredCount);

/*
 * Preserve active filters when moving between pages.
 */
$queryParameters = [
    'q' => $search,
    'status' => $statusFilter,
    'document_status' => $documentFilter,
    'school_year' => $schoolYearFilter,
    'grade' => $gradeFilter,
    'sort' => $sort
];

$paginationUrl = static function (int $targetPage) use ($queryParameters): string {
    $parameters = $queryParameters;
    $parameters['page'] = $targetPage;

    return '?' . http_build_query($parameters);
};

$reviewListUrl = BASE_URL . '/registrar/enrollment_review.php';
$reviewDetailUrl = BASE_URL . '/registrar/review_application.php';
$backToApplications = $reviewListUrl . '?' . http_build_query($queryParameters + ['page' => $returnPage]) . '#enrollment-applications';
if (!$isReviewDetail) {
    $pageTitle .= ' (Page ' . $page . ' of ' . $totalPages . ')';
}
require_once __DIR__ . '/../includes/header.php';
?>

<style>
    .review-summary-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 14px;
        margin: 22px 0;
    }

    .review-summary-card {
        background: #f7f7f9;
        border: 1px solid #e1e4ea;
        border-radius: 12px;
        padding: 17px 19px;
    }

    .review-summary-card span {
        display: block;
        color: #64748b;
        font-size: 13px;
        margin-bottom: 5px;
    }

    .review-summary-card strong {
        display: block;
        color: #651b35;
        font-size: 25px;
    }

    .review-filter-panel {
        background: #f8f8fa;
        border: 1px solid #e1e4ea;
        border-radius: 12px;
        padding: 18px;
        margin: 20px 0;
    }

    .review-filter-grid {
        display: grid;
        grid-template-columns: minmax(220px, 2fr) repeat(3, minmax(140px, 1fr));
        gap: 12px;
        align-items: end;
    }

    .review-filter-grid label {
        display: block;
        font-weight: 600;
        font-size: 13px;
        margin-bottom: 6px;
    }

    .review-filter-grid input,
    .review-filter-grid select {
        width: 100%;
        min-width: 0;
        box-sizing: border-box;
    }

    .review-filter-actions {
        display: flex;
        gap: 8px;
        margin-top: 14px;
        align-items: center;
    }

    .review-results-heading {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 12px;
        margin: 24px 0 12px;
    }

    .review-results-heading h2 {
        margin: 0;
    }

    .review-result-count {
        color: #64748b;
        font-size: 13px;
    }

    .application-card {
        border: 1px solid #e1e4ea;
        border-radius: 12px;
        padding: 19px;
        margin-bottom: 14px;
        background: #fff;
    }

    .application-card h3 {
        margin: 0 0 12px;
        color: #651b35;
        font-size: 18px;
    }

    .application-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 8px 18px;
        color: #475569;
        font-size: 13px;
        margin: 8px 0;
    }

    .application-current-status {
        margin: 12px 0 15px;
        font-size: 14px;
    }

    .application-review-form {
        border-top: 1px solid #edf0f4;
        padding-top: 14px;
        margin-top: 14px;
    }

    .application-review-form .review-fields {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 12px;
    }

    .application-review-form label {
        display: block;
        font-size: 13px;
        font-weight: 600;
    }

    .application-review-form select,
    .application-review-form textarea {
        width: 100%;
        box-sizing: border-box;
        margin-top: 5px;
    }

    .application-review-form .remarks-field {
        grid-column: 1 / -1;
    }

    .application-review-form button {
        margin-top: 12px;
    }

    .review-pagination {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
        border-top: 1px solid #e1e4ea;
        padding-top: 18px;
        margin-top: 20px;
    }

    .review-pagination-links {
        display: flex;
        align-items: center;
        gap: 6px;
        flex-wrap: wrap;
    }

    .review-pagination a,
    .review-pagination span {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 36px;
        min-height: 36px;
        box-sizing: border-box;
        padding: 7px 10px;
        border: 1px solid #d8dce4;
        border-radius: 8px;
        color: #651b35;
        text-decoration: none;
        font-size: 13px;
    }

    .review-pagination .current-page {
        background: #651b35;
        border-color: #651b35;
        color: #fff;
    }

    .review-pagination .disabled-page {
        color: #9ca3af;
        background: #f8fafc;
    }

    .review-empty-state {
        border: 1px dashed #cbd5e1;
        border-radius: 12px;
        padding: 30px 18px;
        text-align: center;
        color: #64748b;
    }

    @media (max-width: 850px) {
        .review-filter-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (max-width: 600px) {
        .review-summary-grid {
            grid-template-columns: 1fr;
        }

        .review-filter-grid,
        .application-review-form .review-fields {
            grid-template-columns: 1fr;
        }

        .application-review-form .remarks-field {
            grid-column: auto;
        }

        .review-results-heading {
            align-items: flex-start;
            flex-direction: column;
        }
    }
</style>

<?php if ($isReviewDetail): ?>
    <div class="portal-back-row">
        <a class="portal-back-link" href="<?= e($backToApplications) ?>">Back to enrollment applications</a>
    </div>
<?php endif; ?>
<section class="form-card form-card-wide">

        <h1><?= e($isReviewDetail ? 'Review Application and Documents' : 'Enrollment Review') ?></h1>
    <p class="muted">
        Review enrollment applications and verify supporting documents in one place.
    </p>

    <?php if ($success): ?>
        <p class="success-message"><?= e($success) ?></p>
    <?php endif; ?>

    <?php if ($error): ?>
        <p class="error-message"><?= e($error) ?></p>
    <?php endif; ?>

    <?php if (!$isReviewDetail): ?>
    <nav class="application-status-links" aria-label="Enrollment application status">
        <?php foreach (array_merge([''], $allowedStatuses) as $quickStatus): ?>
            <?php
            $quickQuery = $queryParameters;
            $quickQuery['status'] = $quickStatus;
            ?>
            <a class="button <?= $statusFilter === $quickStatus ? 'button-primary' : 'button-outline' ?>"
               href="<?= e($reviewListUrl . '?' . http_build_query($quickQuery)) ?>#enrollment-applications"
               <?= $statusFilter === $quickStatus ? 'aria-current="true"' : '' ?>>
                <?= e($quickStatus ?: 'All applications') ?>
                (<?= number_format($quickStatus === '' ? array_sum($statusCounts) : ($statusCounts[$quickStatus] ?? 0)) ?>)
            </a>
        <?php endforeach; ?>
    </nav>

    <form method="get" class="table-search-form">
        <input type="hidden" name="status" value="<?= e($statusFilter) ?>">
        <div class="table-search-toolbar">
            <div class="table-search-field">
                <label for="application-search">Search applications</label>
                                <input
                                    type="search" data-student-suggestions="enrollment" data-suggestion-url="<?= BASE_URL ?>/includes/student_suggestions.php"
                                    id="application-search"
                                    name="q"
                                    value="<?= e($search) ?>"
                                    maxlength="150"
                                    placeholder="Name, 12-digit LRN, or email"
                                >
            </div>
            <button type="submit" class="button button-primary">Search</button>
            <details class="table-search-disclosure">
                <summary class="button button-outline">
                    Filters<?= ($documentFilter !== '' || $schoolYearFilter !== '' || $gradeFilter !== '' || $sort !== 'newest') ? ' (active)' : '' ?>
                </summary>
                <div class="table-search-panel">
                    <h3 class="table-search-panel-title">Filter results</h3>
                    <div class="table-search-fields">
                        <div>
                            <label for="document-status-filter">Document status</label>
                            <select id="document-status-filter" name="document_status">
                                <option value="">All document statuses</option>
                                <?php foreach (array_merge($documentStatuses, ['Not Available']) as $documentOption): ?>
                                    <option value="<?= e($documentOption) ?>" <?= $documentFilter === $documentOption ? 'selected' : '' ?>>
                                        <?= e($documentOption === 'For Verification' ? 'Awaiting Submission' : $documentOption) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label for="school-year-filter">School year</label>
                            <select id="school-year-filter" name="school_year">
                                <option value="">All school years</option>
                                <?php foreach ($schoolYears as $yearOption): ?>
                                    <option
                                        value="<?= (int) $yearOption['school_year_id'] ?>"
                                        <?= (string) $schoolYearFilter === (string) $yearOption['school_year_id'] ? 'selected' : '' ?>
                                    >
                                        <?= e($yearOption['school_year']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label for="registrar-grade-filter">Grade level</label>
                            <select id="registrar-grade-filter" name="grade">
                                <option value="">All grade levels</option>
                                <?php foreach ($gradeLevels as $grade): ?>
                                    <option value="<?= (int) $grade['grade_level_id'] ?>"
                                            <?= $gradeFilter === (int) $grade['grade_level_id'] ? 'selected' : '' ?>>
                                        <?= e('Grade ' . $grade['grade_level']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label for="sort-filter">Sort by</label>
                            <select id="sort-filter" name="sort">
                                <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>
                                    Newest first
                                </option>
                                <option value="oldest" <?= $sort === 'oldest' ? 'selected' : '' ?>>
                                    Oldest first
                                </option>
                            </select>
                        </div>
                    </div>
                    <p class="muted registrar-filter-help">Document status matches applications with at least one document in that status.</p>
                    <div class="table-search-actions">
                        <button type="submit" class="button button-primary">Apply filters</button>
                        <a class="button button-outline" href="<?= BASE_URL ?>/registrar/enrollment_review.php<?= $statusFilter !== '' ? '?' . e(http_build_query(['status' => $statusFilter])) : '' ?>">Clear filters</a>
                    </div>
                </div>
            </details>
        </div>
    </form>

    <div class="review-results-heading" id="enrollment-applications">
        <h2>Enrollment Applications</h2>
        <span class="review-result-count">
            Showing <?= number_format($firstResult) ?>–<?= number_format($lastResult) ?>
            of <?= number_format($filteredCount) ?> applications
        </span>
    </div>

    <?php endif; ?>

    <?php if ($applications): ?>

        <?php if ($totalPages > 1): ?>
            <?php
            $paginationLabel = 'Enrollment review pages, top';
            $showPageJump = true;
            require __DIR__ . '/../includes/registrar_list_pagination.php';
            ?>
        <?php endif; ?>


        <?php foreach ($applications as $application): ?>
            <article class="application-card">
                <div class="enrollment-application-row">
                    <span class="registrar-result-person">
                        <strong><?= e(trim($application['first_name'] . ' ' .
                            ($application['middle_name'] ? $application['middle_name'] . ' ' : '') . $application['last_name'])) ?></strong>
                        <span>Application #<?= (int) $application['application_id'] ?> · <?= e($application['school_year']) ?></span>
                    </span>
                    <span class="registrar-result-status"><?= e($application['application_status']) ?></span>
                    <?php if (!$isReviewDetail): ?>
                        <a class="button button-outline" href="<?= e($reviewDetailUrl . '?' . http_build_query(
                            $queryParameters + ['page' => $page, 'application_id' => $application['application_id']]
                        )) ?>">Review application and documents</a>
                    <?php endif; ?>
                </div>
                <?php if ($isReviewDetail): ?>
                <div class="registrar-result-details">
                    <div class="application-meta">
                        <span><strong>Email:</strong> <?= e($application['email']) ?></span>
                        <span><strong>LRN:</strong> <?= e($application['lrn'] ?? '') ?></span>
                        <span><strong>Type:</strong> <?= e($application['enrollment_type']) ?></span>
                    </div>

                    <div class="application-meta">
                        <span><strong>Requested grade:</strong> Grade <?= e($application['grade_level']) ?></span>
                        <span><strong>School year:</strong> <?= e($application['school_year']) ?></span>
                        <span><strong>Submitted:</strong> <?= e($application['submitted_at']) ?></span>
                    </div>

                    <p class="application-current-status">
                        <strong>Current status:</strong>
                        <?= e($application['application_status']) ?>
                    </p>

                    <form method="post" class="application-review-form">
                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?= e(csrf_token()) ?>"
                        >
                        <input
                            type="hidden"
                            name="application_id"
                            value="<?= (int) $application['application_id'] ?>"
                        >

                        <fieldset class="enrollment-review-documents">
                            <legend>1. Verify documents</legend>
                            <?php
                            $savedDocuments = $documentsByApplication[(int) $application['application_id']] ?? [];
                            foreach ($requiredDocuments as $requiredName):
                                $document = $savedDocuments[$requiredName] ?? null;
                            ?>
                                <div class="enrollment-review-document">
                                    <strong><?= e($requiredName) ?></strong>
                                    <?php if ($document): ?>
                                        <?php
                                        $documentId = (int) $document['document_id'];
                                        $currentDocumentStatus = $document['verification_status'];
                                        if (in_array($currentDocumentStatus, ['Not Submitted', 'Submitted'], true)) {
                                            $currentDocumentStatus = 'For Verification';
                                        }
                                        $documentRemarks = trim((string) ($document['remarks'] ?? ''));
                                        ?>
                                        <label>
                                            Verification status
                                            <select name="documents[<?= $documentId ?>][status]" required>
                                                <?php foreach ($documentStatuses as $documentOption): ?>
                                                    <option value="<?= e($documentOption) ?>" <?= $documentOption === $currentDocumentStatus ? 'selected' : '' ?>>
                                                        <?= e($documentOption === 'For Verification' ? 'Awaiting Submission' : $documentOption) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </label>
                                        <label>
                                            Document remarks (required if rejected)
                                            <textarea name="documents[<?= $documentId ?>][remarks]" rows="2"><?= e($documentRemarks) ?></textarea>
                                        </label>
                                    <?php else: ?>
                                        <p class="muted">Not marked as available by the student.</p>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </fieldset>

                        <fieldset class="enrollment-review-decision">
                            <legend>2. Review application</legend>
                            <div class="review-fields">

                            <label>
                                Application status
                                <select name="application_status" required>
                                    <?php foreach ($allowedStatuses as $statusOption): ?>
                                        <option
                                            value="<?= e($statusOption) ?>"
                                            <?= $application['application_status'] === $statusOption ? 'selected' : '' ?>
                                        >
                                            <?= e($statusOption) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </label>

                            <label>
                                Assign section (optional)
                                <select name="assigned_section_id">
                                    <option value="">No section assigned</option>
                                    <?php foreach ($sections as $section): ?>
                                        <?php
                                        $gradeLabel = 'Grade ' . $application['grade_level'];
                                        $sectionName = preg_replace(
                                            '/^' . preg_quote($gradeLabel, '/') . '\b\s*[-:]?\s*/i',
                                            '',
                                            $section['section_name']
                                        );
                                        ?>
                                        <option
                                            value="<?= (int) $section['section_id'] ?>"
                                            <?= (int) $application['assigned_section_id'] === (int) $section['section_id'] ? 'selected' : '' ?>
                                        >
                                            <?= e(trim($gradeLabel . ' ' . $sectionName) . ' (' . number_format((int) $section['enrolled_count']) . ')') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="muted">Numbers show approved enrollments for this school year.</small>
                            </label>

                            <label class="remarks-field">
                                Review remarks
                                <textarea name="remarks" rows="2"><?= e($application['remarks'] ?? '') ?></textarea>
                            </label>

                            </div>
                        </fieldset>

                        <button type="submit" class="button button-primary">
                            Save Review and Verification
                        </button>
                    </form>
                </div>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>

        <?php if ($totalPages > 1): ?>
            <?php
            $paginationLabel = 'Enrollment review pages, bottom';
            $showPageJump = false;
            require __DIR__ . '/../includes/registrar_list_pagination.php';
            ?>
        <?php endif; ?>

    <?php else: ?>

        <div class="review-empty-state">
            <?php if ($isReviewDetail): ?>
                <strong>Application not found.</strong>
            <?php elseif ($search !== '' || $statusFilter !== '' || $schoolYearFilter !== '' || $gradeFilter !== '' || $documentFilter !== ''): ?>
                <strong>No matching applications found.</strong>
                <p>Try changing your search terms or filters.</p>
            <?php else: ?>
                <strong>No enrollment applications have been submitted.</strong>
            <?php endif; ?>
        </div>

    <?php endif; ?>

</section>

<script src="<?= BASE_URL ?>/assets/js/student_suggestions.js" defer></script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
