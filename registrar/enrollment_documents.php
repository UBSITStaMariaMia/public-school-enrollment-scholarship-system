<?php
require_once __DIR__ . '/../includes/bootstrap.php';

require_role('School Registrar');

$pageTitle = 'Enrollment Document Verification';
$errors = [];
$success = '';

/*
 * Students no longer upload files for these required documents.
 * A document row means the student checked that the document is available.
 * Registrar verification is therefore tracked separately from availability.
 */
$statuses = ['For Verification', 'Accepted', 'Rejected'];

$requiredDocuments = [
    'PSA Birth Certificate',
    'Old School Card (before moving up)',
    'Good Moral Certificate'
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

if (!in_array($statusFilter, $statuses, true) && $statusFilter !== 'Not Available') {
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
 * Update a selected document's registrar verification.
 * Only documents with a saved availability record can be verified.
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
            throw new RuntimeException('Your session expired. Please refresh the page and try again.');
        }

        $documentId = filter_var($_POST['document_id'] ?? null, FILTER_VALIDATE_INT);
        $status = $_POST['verification_status'] ?? '';
        $remarks = trim($_POST['remarks'] ?? '');

        if (!$documentId || !in_array($status, $statuses, true)) {
            throw new RuntimeException('Select a valid available document and verification status.');
        }

        if ($status === 'Rejected' && $remarks === '') {
            throw new RuntimeException('Enter remarks explaining why the document was rejected.');
        }

        $documentQuery = $pdo->prepare(
            'SELECT document_id
             FROM enrollment_documents
             WHERE document_id = :document_id
             LIMIT 1'
        );
        $documentQuery->execute(['document_id' => $documentId]);

        if (!$documentQuery->fetch()) {
            throw new RuntimeException('Document record not found.');
        }

        $update = $pdo->prepare(
            'UPDATE enrollment_documents
             SET verification_status = :status,
                 remarks = :remarks
             WHERE document_id = :document_id'
        );

        $update->execute([
            'status' => $status,
            'remarks' => $remarks !== '' ? $remarks : null,
            'document_id' => $documentId
        ]);

        $success = 'Document verification updated.';
    } catch (PDOException $exception) {
        error_log('Document verification error: ' . $exception->getMessage());
        $errors[] = 'Unable to update document verification. Please try again.';
    } catch (RuntimeException $exception) {
        $errors[] = $exception->getMessage();
    }
}

/*
 * School-year options for filtering.
 */
$schoolYears = $pdo->query(
    'SELECT school_year_id, school_year
     FROM school_years
     ORDER BY school_year_id DESC'
)->fetchAll();

/*
 * Build application filters.
 */
$where = [];
$params = [];

if ($search !== '') {
    $searchTerm = '%' . $search . '%';

    $where[] = "(
        CONCAT_WS(' ', st.first_name, st.middle_name, st.last_name) LIKE :search_name
        OR st.first_name LIKE :search_first
        OR st.middle_name LIKE :search_middle
        OR st.last_name LIKE :search_last
        OR u.email LIKE :search_email
        OR ea.lrn LIKE :search_lrn
    )";

    $params['search_name'] = $searchTerm;
    $params['search_first'] = $searchTerm;
    $params['search_middle'] = $searchTerm;
    $params['search_last'] = $searchTerm;
    $params['search_email'] = $searchTerm;
    $params['search_lrn'] = $searchTerm;
}

if ($schoolYearFilter !== '') {
    $where[] = 'ea.school_year_id = :school_year_filter';
    $params['school_year_filter'] = $schoolYearFilter;
}

/*
 * Status filter is based on the required-document availability records.
 * A record exists when the student checked that document.
 */
if ($statusFilter === 'Not Available') {
    $where[] = "(
        SELECT COUNT(*)
        FROM enrollment_documents ed_filter
        WHERE ed_filter.application_id = ea.application_id
          AND ed_filter.document_type IN (
              'PSA Birth Certificate',
              'Old School Card (before moving up)',
              'Good Moral Certificate'
          )
    ) < 3";
} elseif ($statusFilter !== '') {
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
    $params['verification_filter'] = $statusFilter;
    $params['verification_filter_legacy'] = $statusFilter;
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

/*
 * Summary counts across all enrollment applications.
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
 * Count matching applications and fetch only the current page.
 */
$baseFrom = "
    FROM enrollment_applications ea
    INNER JOIN students st ON st.student_id = ea.student_id
    INNER JOIN users u ON u.user_id = st.user_id
    INNER JOIN school_years sy ON sy.school_year_id = ea.school_year_id
";

$countQuery = $pdo->prepare(
    "SELECT COUNT(*) $baseFrom $whereSql"
);
$countQuery->execute($params);
$filteredCount = (int) $countQuery->fetchColumn();

$totalPages = max(1, (int) ceil($filteredCount / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$applicationsQuery = $pdo->prepare(
    "SELECT
        ea.application_id,
        ea.application_status,
        ea.submitted_at,
        ea.school_year_id,
        st.first_name,
        st.middle_name,
        st.last_name,
        ea.lrn,
        u.email,
        sy.school_year
     $baseFrom
     $whereSql
     ORDER BY {$allowedSorts[$sort]}
     LIMIT {$perPage} OFFSET {$offset}"
);
$applicationsQuery->execute($params);
$applications = $applicationsQuery->fetchAll();

/*
 * Fetch document records for the current page in one query.
 * A saved record indicates the student selected that document as available.
 */
$applicationRows = [];
$applicationIds = array_map(
    static fn(array $application): int => (int) $application['application_id'],
    $applications
);

$documentsByApplication = [];

if ($applicationIds) {
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

foreach ($applications as $application) {
    $savedDocuments = $documentsByApplication[(int) $application['application_id']] ?? [];
    $rows = [];
    $unavailableDocuments = [];

    foreach ($requiredDocuments as $requiredName) {
        $document = $savedDocuments[$requiredName] ?? null;

        /*
         * Legacy records may still say "Not Submitted" or "Submitted".
         * For selected documents, show them as awaiting verification until
         * the registrar records a current decision.
         */
        if ($document && in_array(
            $document['verification_status'],
            ['Not Submitted', 'Submitted'],
            true
        )) {
            $document['form_status'] = 'For Verification';
            $document['display_status'] = 'For Submission';
        } elseif ($document) {
            $document['form_status'] = $document['verification_status'];
            $document['display_status'] = $document['verification_status'] === 'For Verification'
                ? 'For Submission'
                : $document['verification_status'];
        }

        $rows[] = [
            'name' => $requiredName,
            'document' => $document
        ];

        if (!$document) {
            $unavailableDocuments[] = $requiredName;
        }
    }

    $applicationRows[] = [
        'application' => $application,
        'rows' => $rows,
        'unavailable' => $unavailableDocuments
    ];
}

$firstResult = $filteredCount > 0 ? $offset + 1 : 0;
$lastResult = min($offset + $perPage, $filteredCount);

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
    .verification-summary-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 14px;
        margin: 22px 0;
    }

    .verification-summary-card {
        background: #f7f7f9;
        border: 1px solid #e1e4ea;
        border-radius: 12px;
        padding: 17px 19px;
    }

    .verification-summary-card span {
        display: block;
        color: #64748b;
        font-size: 13px;
        margin-bottom: 5px;
    }

    .verification-summary-card strong {
        display: block;
        color: #651b35;
        font-size: 25px;
    }

    .verification-filter-panel {
        background: #f8f8fa;
        border: 1px solid #e1e4ea;
        border-radius: 12px;
        padding: 18px;
        margin: 20px 0;
    }

    .verification-filter-grid {
        display: grid;
        grid-template-columns: minmax(220px, 2fr) repeat(3, minmax(140px, 1fr));
        gap: 12px;
        align-items: end;
    }

    .verification-filter-grid label {
        display: block;
        font-weight: 600;
        font-size: 13px;
        margin-bottom: 6px;
    }

    .verification-filter-grid input,
    .verification-filter-grid select {
        width: 100%;
        min-width: 0;
        box-sizing: border-box;
    }

    .verification-filter-actions {
        display: flex;
        gap: 8px;
        margin-top: 14px;
        align-items: center;
    }

    .verification-application-card {
        border: 1px solid #e1e4ea;
        border-radius: 12px;
        padding: 19px;
        margin-bottom: 16px;
        background: #fff;
    }

    .verification-application-card h2 {
        margin: 0 0 10px;
        color: #651b35;
        font-size: 18px;
    }

    .verification-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 8px 18px;
        color: #475569;
        font-size: 13px;
        margin: 8px 0;
    }

    .verification-unavailable {
        background: #fff8e8;
        border: 1px solid #f2dfb4;
        border-radius: 8px;
        padding: 10px 12px;
        margin: 12px 0;
        color: #80530b;
        font-size: 13px;
    }

    .verification-table-wrap {
        overflow-x: auto;
        margin-top: 14px;
    }

    .verification-table-wrap table {
        width: 100%;
        min-width: 650px;
        border-collapse: collapse;
    }

    .verification-table-wrap th,
    .verification-table-wrap td {
        padding: 11px 12px;
        border: 1px solid #e1e4ea;
        text-align: left;
        vertical-align: top;
        font-size: 13px;
    }

    .verification-table-wrap th {
        background: #f8f4f6;
        color: #651b35;
    }

    .verification-table-wrap form {
        display: grid;
        gap: 7px;
        min-width: 190px;
    }

    .verification-table-wrap select,
    .verification-table-wrap textarea {
        width: 100%;
        box-sizing: border-box;
    }

    .verification-badge {
        display: inline-block;
        border-radius: 999px;
        padding: 5px 9px;
        background: #fff3d8;
        color: #875600;
        font-size: 12px;
        font-weight: 600;
        white-space: nowrap;
    }

    .verification-badge.available,
    .verification-badge.accepted {
        background: #e7f6ec;
        color: #176534;
    }

    .verification-badge.rejected {
        background: #fdeaea;
        color: #a32626;
    }

    .verification-badge.unavailable {
        background: #eef0f3;
        color: #596273;
    }

    .verification-results-heading,
    .verification-pagination {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
    }

    .verification-results-heading {
        margin: 24px 0 12px;
    }

    .verification-results-heading h2 {
        margin: 0;
    }

    .verification-result-count {
        color: #64748b;
        font-size: 13px;
    }

    .verification-pagination {
        border-top: 1px solid #e1e4ea;
        padding-top: 18px;
        margin-top: 20px;
    }

    .verification-pagination-links {
        display: flex;
        align-items: center;
        gap: 6px;
        flex-wrap: wrap;
    }

    .verification-pagination a,
    .verification-pagination span {
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

    .verification-pagination .current-page {
        background: #651b35;
        border-color: #651b35;
        color: #fff;
    }

    .verification-pagination .disabled-page {
        color: #9ca3af;
        background: #f8fafc;
    }

    .verification-empty-state {
        border: 1px dashed #cbd5e1;
        border-radius: 12px;
        padding: 30px 18px;
        text-align: center;
        color: #64748b;
    }

    @media (max-width: 850px) {
        .verification-filter-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (max-width: 600px) {
        .verification-summary-grid {
            grid-template-columns: 1fr;
        }

        .verification-filter-grid {
            grid-template-columns: 1fr;
        }

        .verification-results-heading {
            align-items: flex-start;
            flex-direction: column;
        }
    }
</style>

<section class="form-card form-card-wide">

    <p class="eyebrow">Registrar Workspace</p>
    <h1>Enrollment Document Verification</h1>
    <p class="muted">
        Review the supporting documents students will submit in person to the registrar
        and record each document's verification decision.
    </p>

    <?php if ($success): ?>
        <div class="alert alert-success"><?= e($success) ?></div>
    <?php endif; ?>

    <?php foreach ($errors as $message): ?>
        <div class="alert alert-error"><?= e($message) ?></div>
    <?php endforeach; ?>

    <div class="verification-summary-grid">
        <div class="verification-summary-card">
            <span>Total Applications</span>
            <strong><?= number_format($totalApplications) ?></strong>
        </div>
        <div class="verification-summary-card">
            <span>Pending Review</span>
            <strong><?= number_format($pendingApplications) ?></strong>
        </div>
        <div class="verification-summary-card">
            <span>Reviewed</span>
            <strong><?= number_format($reviewedApplications) ?></strong>
        </div>
    </div>

    <form method="get" class="verification-filter-panel">
        <div class="verification-filter-grid">

            <div>
                <label for="verification-search">Search applications</label>
                <input
                    type="search"
                    id="verification-search"
                    name="q"
                    value="<?= e($search) ?>"
                    placeholder="Name, LRN, or email"
                >
            </div>

            <div>
                <label for="verification-status-filter">Verification status</label>
                <select id="verification-status-filter" name="status">
                    <option value="">All statuses</option>
                    <?php foreach ($statuses as $statusOption): ?>
                        <option
                            value="<?= e($statusOption) ?>"
                            <?= $statusFilter === $statusOption ? 'selected' : '' ?>
                        >
                            <?= e($statusOption === 'For Verification' ? 'Awaiting Submission' : $statusOption) ?>
                        </option>
                    <?php endforeach; ?>
                    <option value="Not Available" <?= $statusFilter === 'Not Available' ? 'selected' : '' ?>>
                        Not Available
                    </option>
                </select>
            </div>

            <div>
                <label for="verification-school-year">School year</label>
                <select id="verification-school-year" name="school_year">
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
                <label for="verification-sort">Sort by</label>
                <select id="verification-sort" name="sort">
                    <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Newest first</option>
                    <option value="oldest" <?= $sort === 'oldest' ? 'selected' : '' ?>>Oldest first</option>
                </select>
            </div>

        </div>

        <div class="verification-filter-actions">
            <button type="submit" class="button button-primary">Apply Filters</button>
            <a class="btn btn-secondary" href="<?= e(strtok($_SERVER['REQUEST_URI'], '?')) ?>">
                Clear Filters
            </a>
        </div>
    </form>

    <div class="verification-results-heading">
        <h2>Enrollment Applications</h2>
        <span class="verification-result-count">
            Showing <?= number_format($firstResult) ?>–<?= number_format($lastResult) ?>
            of <?= number_format($filteredCount) ?> applications
        </span>
    </div>

    <?php if ($applicationRows): ?>

        <?php foreach ($applicationRows as $item): ?>
            <?php $application = $item['application']; ?>

            <article class="verification-application-card">

                <h2>
                    Application #<?= (int) $application['application_id'] ?>
                    —
                    <?= e(trim(
                        $application['first_name'] . ' ' .
                        ($application['middle_name'] ?? '') . ' ' .
                        $application['last_name']
                    )) ?>
                </h2>

                <div class="verification-meta">
                    <span><strong>Email:</strong> <?= e($application['email']) ?></span>
                    <span><strong>LRN:</strong> <?= e($application['lrn'] ?? '') ?></span>
                    <span><strong>School year:</strong> <?= e($application['school_year']) ?></span>
                </div>

                <div class="verification-meta">
                    <span><strong>Application status:</strong> <?= e($application['application_status']) ?></span>
                    <span><strong>Submitted:</strong> <?= e($application['submitted_at']) ?></span>
                </div>

                <div class="verification-table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Document</th>
                                <th>Student Selection</th>
                                <th>Verification</th>
                                <th>Remarks / Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($item['rows'] as $row): ?>
                                <?php $document = $row['document']; ?>
                                <tr>
                                    <td><?= e($row['name']) ?></td>

                                    <?php if ($document): ?>
                                        <td>
                                            <span class="verification-badge available">Available</span>
                                        </td>
                                        <td>
                                            <?php
                                            $displayStatus = $document['display_status'];
                                            $badgeClass = match ($displayStatus) {
                                                'Accepted' => 'accepted',
                                                'Rejected' => 'rejected',
                                                default => ''
                                            };
                                            ?>
                                            <span class="verification-badge <?= e($badgeClass) ?>">
                                                <?= e($displayStatus) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <form method="post">
                                                <input
                                                    type="hidden"
                                                    name="csrf_token"
                                                    value="<?= e(csrf_token()) ?>"
                                                >
                                                <input
                                                    type="hidden"
                                                    name="document_id"
                                                    value="<?= (int) $document['document_id'] ?>"
                                                >

                                                <select name="verification_status" required>
                                                    <?php foreach ($statuses as $statusOption): ?>
                                                        <option
                                                            value="<?= e($statusOption) ?>"
                                                            <?= $document['form_status'] === $statusOption ? 'selected' : '' ?>
                                                        >
                                                            <?= e($statusOption === 'For Verification' ? 'Awaiting Submission' : $statusOption) ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>

                                                <?php
                                                $existingRemarks = trim((string) ($document['remarks'] ?? ''));
                                                if (
                                                    stripos($existingRemarks, 'No digital file was uploaded') !== false
                                                    && stripos($existingRemarks, 'Student indicated this document is available') !== false
                                                ) {
                                                    $existingRemarks = '';
                                                }
                                                ?>
                                                <textarea
                                                    name="remarks"
                                                    rows="2"
                                                    placeholder="Registrar remarks (required if rejected)"
                                                ><?= e($existingRemarks) ?></textarea>

                                                <button type="submit" class="button button-primary">
                                                    Update Verification
                                                </button>
                                            </form>
                                        </td>
                                    <?php else: ?>
                                        <td>
                                            <span class="verification-badge unavailable">Not Available</span>
                                        </td>
                                        <td>
                                            <span class="verification-badge unavailable">Not Applicable</span>
                                        </td>
                                        <td>
                                            <span class="muted">
                                                Student marked this document as not available.
                                            </span>
                                        </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

            </article>
        <?php endforeach; ?>

        <?php if ($totalPages > 1): ?>
            <nav class="verification-pagination" aria-label="Enrollment document verification pages">

                <span>
                    Page <?= number_format($page) ?> of <?= number_format($totalPages) ?>
                </span>

                <div class="verification-pagination-links">

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
                            <span class="current-page" aria-current="page"><?= $pageNumber ?></span>
                        <?php else: ?>
                            <a href="<?= e($paginationUrl($pageNumber)) ?>"><?= $pageNumber ?></a>
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

        <div class="verification-empty-state">
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
