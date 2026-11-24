<?php
require_once __DIR__ . '/../includes/bootstrap.php';

require_role('School Registrar');

$pageTitle = 'Enrollment Review';
$error = '';
$success = '';

$allowedStatuses = [
    'Pending Review',
    'Incomplete Requirements',
    'Approved',
    'Rejected'
];

$perPage = 10;

/*
 * Read and validate list filters.
 */
$search = trim((string) ($_GET['q'] ?? ''));
$statusFilter = trim((string) ($_GET['status'] ?? ''));
$schoolYearFilter = filter_var(
    $_GET['school_year'] ?? '',
    FILTER_VALIDATE_INT
);
$page = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT);

$sort = $_GET['sort'] ?? 'newest';
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
    try {
        if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
            throw new RuntimeException('Invalid request. Refresh the page.');
        }

        $id = filter_var($_POST['application_id'] ?? null, FILTER_VALIDATE_INT);
        $status = $_POST['application_status'] ?? '';
        $remarks = trim($_POST['remarks'] ?? '');
        $section = ($_POST['assigned_section_id'] ?? '') !== ''
            ? filter_var($_POST['assigned_section_id'], FILTER_VALIDATE_INT)
            : null;

        if (!$id || !in_array($status, $allowedStatuses, true)) {
            throw new RuntimeException('Select a valid application and status.');
        }

        if ($section === false) {
            throw new RuntimeException('Select a valid section.');
        }

        $check = $pdo->prepare(
            'SELECT application_id
             FROM enrollment_applications
             WHERE application_id = :id'
        );
        $check->execute(['id' => $id]);

        if (!$check->fetch()) {
            throw new RuntimeException('Application not found.');
        }

        if ($section) {
            $sectionCheck = $pdo->prepare(
                'SELECT section_id
                 FROM sections
                 WHERE section_id = :id'
            );
            $sectionCheck->execute(['id' => $section]);

            if (!$sectionCheck->fetch()) {
                throw new RuntimeException('Selected section does not exist.');
            }
        }

        $stmt = $pdo->prepare(
            'UPDATE enrollment_applications
             SET application_status = :status,
                 remarks = :remarks,
                 assigned_section_id = :section,
                 reviewed_by = :reviewer,
                 reviewed_at = NOW()
             WHERE application_id = :id'
        );

        $stmt->execute([
            'status' => $status,
            'remarks' => $remarks !== '' ? $remarks : null,
            'section' => $section ?: null,
            'reviewer' => current_user()['user_id'],
            'id' => $id
        ]);

        $success = 'Enrollment application updated.';
    } catch (PDOException $ex) {
        error_log('Enrollment review: ' . $ex->getMessage());
        $error = 'Unable to update the application. Check the selected section and related records.';
    } catch (RuntimeException $ex) {
        $error = $ex->getMessage();
    }
}

/*
 * Retrieve sections and school-year options for the filters.
 */
$sections = $pdo->query(
    'SELECT
        s.section_id,
        s.section_name,
        g.grade_level,
        y.school_year
     FROM sections s
     JOIN grade_levels g ON g.grade_level_id = s.grade_level_id
     JOIN school_years y ON y.school_year_id = s.school_year_id
     ORDER BY y.school_year_id DESC, g.grade_level_id, s.section_name'
)->fetchAll();

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

if ($search !== '') {
    $where[] = "(
        CONCAT_WS(' ', st.first_name, st.middle_name, st.last_name) LIKE :search_name
        OR st.first_name LIKE :search_first
        OR st.middle_name LIKE :search_middle
        OR st.last_name LIKE :search_last
        OR u.email LIKE :search_email
        OR ea.lrn LIKE :search_lrn
    )";

    $searchTerm = '%' . $search . '%';
    $params['search_name'] = $searchTerm;
    $params['search_first'] = $searchTerm;
    $params['search_middle'] = $searchTerm;
    $params['search_last'] = $searchTerm;
    $params['search_email'] = $searchTerm;
    $params['search_lrn'] = $searchTerm;
}

if ($statusFilter !== '') {
    $where[] = 'ea.application_status = :status_filter';
    $params['status_filter'] = $statusFilter;
}

if ($schoolYearFilter !== '') {
    $where[] = 'ea.school_year_id = :school_year_filter';
    $params['school_year_filter'] = $schoolYearFilter;
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

/*
 * Summary counts cover all applications, independent of the active filters.
 */
$summary = $pdo->query(
    "SELECT
        COUNT(*) AS total_count,
        SUM(CASE WHEN application_status = 'Pending Review' THEN 1 ELSE 0 END) AS pending_count,
        SUM(CASE WHEN application_status <> 'Pending Review' THEN 1 ELSE 0 END) AS reviewed_count
     FROM enrollment_applications"
)->fetch() ?: [];

$totalApplications = (int) ($summary['total_count'] ?? 0);
$pendingApplications = (int) ($summary['pending_count'] ?? 0);
$reviewedApplications = (int) ($summary['reviewed_count'] ?? 0);

/*
 * Count matching applications, then fetch only the requested page.
 */
$countSql = "
    SELECT COUNT(*)
    FROM enrollment_applications ea
    JOIN students st ON st.student_id = ea.student_id
    JOIN users u ON u.user_id = st.user_id
    JOIN grade_levels g ON g.grade_level_id = ea.requested_grade_level_id
    JOIN school_years y ON y.school_year_id = ea.school_year_id
    $whereSql
";

$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$filteredCount = (int) $countStmt->fetchColumn();

$totalPages = max(1, (int) ceil($filteredCount / $perPage));
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
        y.school_year
    FROM enrollment_applications ea
    JOIN students st ON st.student_id = ea.student_id
    JOIN users u ON u.user_id = st.user_id
    JOIN grade_levels g ON g.grade_level_id = ea.requested_grade_level_id
    JOIN school_years y ON y.school_year_id = ea.school_year_id
    $whereSql
    ORDER BY {$allowedSorts[$sort]}
    LIMIT {$perPage} OFFSET {$offset}
";

$applicationsStmt = $pdo->prepare($applicationsSql);
$applicationsStmt->execute($params);
$applications = $applicationsStmt->fetchAll();

$firstResult = $filteredCount > 0 ? $offset + 1 : 0;
$lastResult = min($offset + $perPage, $filteredCount);

/*
 * Preserve active filters when moving between pages.
 */
$queryParameters = [
    'q' => $search,
    'status' => $statusFilter,
    'school_year' => $schoolYearFilter,
    'sort' => $sort
];

$paginationUrl = static function (int $targetPage) use ($queryParameters): string {
    $parameters = $queryParameters;
    $parameters['page'] = $targetPage;

    return '?' . http_build_query($parameters);
};

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

<section class="form-card form-card-wide">

    <p class="eyebrow">Registrar Workspace</p>
    <h1>Enrollment Review</h1>
    <p class="muted">
        Search, filter, and review student enrollment applications.
    </p>

    <?php if ($success): ?>
        <p class="success-message"><?= e($success) ?></p>
    <?php endif; ?>

    <?php if ($error): ?>
        <p class="error-message"><?= e($error) ?></p>
    <?php endif; ?>

    <div class="review-summary-grid">
        <div class="review-summary-card">
            <span>Total Applications</span>
            <strong><?= number_format($totalApplications) ?></strong>
        </div>

        <div class="review-summary-card">
            <span>Pending Review</span>
            <strong><?= number_format($pendingApplications) ?></strong>
        </div>

        <div class="review-summary-card">
            <span>Reviewed</span>
            <strong><?= number_format($reviewedApplications) ?></strong>
        </div>
    </div>

    <form method="get" class="review-filter-panel">
        <div class="review-filter-grid">

            <div>
                <label for="application-search">Search applications</label>
                <input
                    type="search"
                    id="application-search"
                    name="q"
                    value="<?= e($search) ?>"
                    placeholder="Name, LRN, or email"
                >
            </div>

            <div>
                <label for="status-filter">Application status</label>
                <select id="status-filter" name="status">
                    <option value="">All statuses</option>
                    <?php foreach ($allowedStatuses as $statusOption): ?>
                        <option
                            value="<?= e($statusOption) ?>"
                            <?= $statusFilter === $statusOption ? 'selected' : '' ?>
                        >
                            <?= e($statusOption) ?>
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

        <div class="review-filter-actions">
            <button type="submit" class="button button-primary">Apply Filters</button>
            <a class="btn btn-secondary" href="<?= e(strtok($_SERVER['REQUEST_URI'], '?')) ?>">
                Clear Filters
            </a>
        </div>
    </form>

    <div class="review-results-heading">
        <h2>Enrollment Applications</h2>
        <span class="review-result-count">
            Showing <?= number_format($firstResult) ?>–<?= number_format($lastResult) ?>
            of <?= number_format($filteredCount) ?> applications
        </span>
    </div>

    <?php if ($applications): ?>

        <?php foreach ($applications as $application): ?>
            <article class="application-card">

                <h3>
                    Application #<?= (int) $application['application_id'] ?>
                    —
                    <?= e(trim(
                        $application['first_name'] . ' ' .
                        ($application['middle_name'] ?? '') . ' ' .
                        $application['last_name']
                    )) ?>
                </h3>

                <div class="application-meta">
                    <span><strong>Email:</strong> <?= e($application['email']) ?></span>
                    <span><strong>LRN:</strong> <?= e($application['lrn'] ?? '') ?></span>
                    <span><strong>Type:</strong> <?= e($application['enrollment_type']) ?></span>
                </div>

                <div class="application-meta">
                    <span><strong>Requested grade:</strong> <?= e($application['grade_level']) ?></span>
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
                                    <option
                                        value="<?= (int) $section['section_id'] ?>"
                                        <?= (int) $application['assigned_section_id'] === (int) $section['section_id'] ? 'selected' : '' ?>
                                    >
                                        <?= e(
                                            'Grade ' . $section['grade_level'] .
                                            ' - ' . $section['section_name'] .
                                            ' (' . $section['school_year'] . ')'
                                        ) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </label>

                        <label class="remarks-field">
                            Review remarks
                            <textarea name="remarks" rows="2"><?= e($application['remarks'] ?? '') ?></textarea>
                        </label>

                    </div>

                    <button type="submit" class="button button-primary">
                        Save Review
                    </button>
                </form>

            </article>
        <?php endforeach; ?>

        <?php if ($totalPages > 1): ?>
            <nav class="review-pagination" aria-label="Enrollment application pages">

                <span>
                    Page <?= number_format($page) ?> of <?= number_format($totalPages) ?>
                </span>

                <div class="review-pagination-links">

                    <?php if ($page > 1): ?>
                        <a href="<?= e($paginationUrl(1)) ?>" aria-label="First page">First</a>
                        <a href="<?= e($paginationUrl($page - 1)) ?>" aria-label="Previous page">&laquo;</a>
                    <?php else: ?>
                        <span class="disabled-page">First</span>
                        <span class="disabled-page">&laquo;</span>
                    <?php endif; ?>

                    <?php
                    $startPage = max(1, $page - 2);
                    $endPage = min($totalPages, $page + 2);
                    ?>

                    <?php for ($pageNumber = $startPage; $pageNumber <= $endPage; $pageNumber++): ?>
                        <?php if ($pageNumber === $page): ?>
                            <span class="current-page" aria-current="page">
                                <?= $pageNumber ?>
                            </span>
                        <?php else: ?>
                            <a href="<?= e($paginationUrl($pageNumber)) ?>">
                                <?= $pageNumber ?>
                            </a>
                        <?php endif; ?>
                    <?php endfor; ?>

                    <?php if ($page < $totalPages): ?>
                        <a href="<?= e($paginationUrl($page + 1)) ?>" aria-label="Next page">&raquo;</a>
                        <a href="<?= e($paginationUrl($totalPages)) ?>" aria-label="Last page">Last</a>
                    <?php else: ?>
                        <span class="disabled-page">&raquo;</span>
                        <span class="disabled-page">Last</span>
                    <?php endif; ?>

                </div>
            </nav>
        <?php endif; ?>

    <?php else: ?>

        <div class="review-empty-state">
            <?php if ($search !== '' || $statusFilter !== '' || $schoolYearFilter !== ''): ?>
                <strong>No matching applications found.</strong>
                <p>Try changing your search terms or filters.</p>
            <?php else: ?>
                <strong>No enrollment applications have been submitted.</strong>
            <?php endif; ?>
        </div>

    <?php endif; ?>

</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
