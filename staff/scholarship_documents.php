<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_role('System Administrator', 'Scholarship Coordinator');

$pageTitle = 'Scholarship Documents';

$search = trim((string) ($_GET['search'] ?? ''));
$selectedProgram = trim((string) ($_GET['program'] ?? ''));
$selectedSchoolYear = trim((string) ($_GET['school_year'] ?? ''));
$selectedStatus = trim((string) ($_GET['status'] ?? ''));
$selectedDocument = trim((string) ($_GET['document'] ?? ''));

$allowedStatuses = ['Pending Review', 'For Screening', 'Approved', 'Rejected'];
if (!in_array($selectedStatus, $allowedStatuses, true)) {
    $selectedStatus = '';
}

/* Load filter choices from the existing database tables. */
$programsQuery = $pdo->query(
    'SELECT program_id, program_name
     FROM special_programs
     ORDER BY program_name'
);
$programs = $programsQuery->fetchAll();

$schoolYearsQuery = $pdo->query(
    'SELECT school_year_id, school_year
     FROM school_years
     ORDER BY school_year_id DESC'
);
$schoolYears = $schoolYearsQuery->fetchAll();

$documentTypesQuery = $pdo->query(
    'SELECT DISTINCT document_name
     FROM scholarship_documents
     ORDER BY document_name'
);
$documentTypes = $documentTypesQuery->fetchAll(PDO::FETCH_COLUMN);

/* Summary figures count the document entries already saved in the database. */
$summaryQuery = $pdo->query(
    'SELECT
        COUNT(sd.scholarship_document_id) AS total_documents,
        COUNT(DISTINCT sd.scholarship_application_id) AS applications_with_documents,
        COUNT(DISTINCT sa.student_id) AS students_with_documents
     FROM scholarship_documents sd
     INNER JOIN scholarship_applications sa
        ON sa.scholarship_application_id = sd.scholarship_application_id'
);
$summary = $summaryQuery->fetch() ?: [
    'total_documents' => 0,
    'applications_with_documents' => 0,
    'students_with_documents' => 0
];

$where = [];
$params = [];

if ($search !== '') {
    $where[] = "(
        CONCAT_WS(' ', s.first_name, s.middle_name, s.last_name) LIKE :search_name
        OR s.lrn LIKE :search_lrn
        OR sd.document_name LIKE :search_document
        OR sd.other_document LIKE :search_other
        OR sp.program_name LIKE :search_program
    )";

    $searchValue = '%' . $search . '%';
    $params['search_name'] = $searchValue;
    $params['search_lrn'] = $searchValue;
    $params['search_document'] = $searchValue;
    $params['search_other'] = $searchValue;
    $params['search_program'] = $searchValue;
}

if ($selectedProgram !== '' && ctype_digit($selectedProgram)) {
    $where[] = 'sp.program_id = :program_id';
    $params['program_id'] = (int) $selectedProgram;
}

if ($selectedSchoolYear !== '' && ctype_digit($selectedSchoolYear)) {
    $where[] = 'sa.school_year_id = :school_year_id';
    $params['school_year_id'] = (int) $selectedSchoolYear;
}

if ($selectedStatus !== '') {
    $where[] = 'sa.application_status = :application_status';
    $params['application_status'] = $selectedStatus;
}

if ($selectedDocument !== '') {
    $where[] = 'sd.document_name = :document_name';
    $params['document_name'] = $selectedDocument;
}

$sql = 'SELECT
            sd.scholarship_document_id,
            sd.document_name,
            sd.other_document,
            sd.created_at,
            sa.scholarship_application_id,
            sa.application_status,
            s.student_id,
            s.lrn,
            s.first_name,
            s.middle_name,
            s.last_name,
            sp.program_name,
            sy.school_year,
            gl.grade_level,
            sec.section_name
        FROM scholarship_documents sd
        INNER JOIN scholarship_applications sa
            ON sa.scholarship_application_id = sd.scholarship_application_id
        INNER JOIN students s
            ON s.student_id = sa.student_id
        INNER JOIN special_programs sp
            ON sp.program_id = sa.program_id
        INNER JOIN school_years sy
            ON sy.school_year_id = sa.school_year_id
        LEFT JOIN grade_levels gl
            ON gl.grade_level_id = s.current_grade_level_id
        LEFT JOIN sections sec
            ON sec.section_id = s.current_section_id';

if ($where !== []) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}

$sql .= ' ORDER BY sd.created_at DESC, sd.scholarship_document_id DESC';

$documentsQuery = $pdo->prepare($sql);
$documentsQuery->execute($params);
$documents = $documentsQuery->fetchAll();

$h = static fn ($value): string => e((string) ($value ?? ''));

$studentName = static function (array $row): string {
    $nameParts = [
        $row['first_name'] ?? '',
        $row['middle_name'] ?? '',
        $row['last_name'] ?? ''
    ];

    return trim(implode(' ', array_filter($nameParts, static function ($part): bool {
        return trim((string) $part) !== '';
    })));
};

require_once __DIR__ . '/../includes/header.php';
?>
<style>
.scholarship-documents-page {
    width: min(100% - 32px, 1160px);
    margin: 0 auto 36px;
    color: var(--text, #343b49);
}
.scholarship-documents-page .page-heading {
    margin: 0 0 24px;
}
.scholarship-documents-page .page-heading h1 {
    margin: 0 0 8px;
    color: var(--maroon-dark, #501329);
    font-size: clamp(1.65rem, 2.5vw, 2.1rem);
    line-height: 1.2;
}
.scholarship-documents-page .page-heading p {
    max-width: 780px;
    margin: 0;
    color: var(--muted, #68738a);
    line-height: 1.6;
}
.scholarship-documents-page .document-summary {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 16px;
    margin-bottom: 22px;
}
.scholarship-documents-page .summary-card,
.scholarship-documents-page .documents-panel {
    border: 1px solid var(--line, #e2e5eb);
    border-radius: 13px;
    background: #fff;
}
.scholarship-documents-page .summary-card {
    min-width: 0;
    padding: 18px 20px;
}
.scholarship-documents-page .summary-label {
    display: block;
    margin-bottom: 9px;
    color: var(--muted, #68738a);
    font-size: .87rem;
}
.scholarship-documents-page .summary-value {
    color: var(--maroon-dark, #641b35);
    font-size: 1.9rem;
    font-weight: 750;
    line-height: 1;
}
.scholarship-documents-page .documents-panel {
    padding: clamp(17px, 2.5vw, 25px);
    box-shadow: 0 5px 18px rgba(20, 30, 50, .035);
}
.scholarship-documents-page .panel-heading {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 14px;
    margin-bottom: 18px;
}
.scholarship-documents-page .panel-heading h2 {
    margin: 0;
    color: var(--maroon-dark, #501329);
    font-size: 1.17rem;
}
.scholarship-documents-page .record-count {
    padding: 6px 11px;
    border-radius: 999px;
    background: #f7f1f3;
    color: var(--maroon-dark, #641b35);
    font-size: .82rem;
    font-weight: 700;
    white-space: nowrap;
}
.scholarship-documents-page .document-filters {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 14px;
    margin-bottom: 23px;
    padding: 17px;
    border: 1px solid var(--line, #e5e7ed);
    border-radius: 11px;
    background: #fafafb;
}
.scholarship-documents-page .filter-search,
.scholarship-documents-page .filter-actions {
    grid-column: 1 / -1;
}
.scholarship-documents-page .filter-field {
    min-width: 0;
}
.scholarship-documents-page .filter-field label {
    display: block;
    margin: 0 0 7px;
    font-size: .83rem;
    font-weight: 650;
}
.scholarship-documents-page .filter-field input,
.scholarship-documents-page .filter-field select {
    box-sizing: border-box;
    display: block;
    width: 100%;
    min-height: 43px;
    padding: 9px 11px;
    border: 1px solid #d8dce5;
    border-radius: 8px;
    background: #fff;
    color: var(--text, #343b49);
    font: inherit;
    font-size: .89rem;
}
.scholarship-documents-page .filter-field input:focus,
.scholarship-documents-page .filter-field select:focus {
    outline: 2px solid rgba(100, 27, 53, .15);
    border-color: var(--maroon-dark, #641b35);
}
.scholarship-documents-page .filter-actions {
    display: flex;
    justify-content: flex-end;
    gap: 9px;
}
.scholarship-documents-page .filter-actions .button {
    min-height: 40px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 9px 17px;
    text-decoration: none;
    white-space: nowrap;
}
.scholarship-documents-page .table-scroll {
    overflow-x: auto;
}
.scholarship-documents-page table {
    width: 100%;
    min-width: 850px;
    border-collapse: collapse;
}
.scholarship-documents-page th {
    padding: 12px 13px;
    background: #f8f5f6;
    color: var(--maroon-dark, #501329);
    font-size: .79rem;
    font-weight: 700;
    text-align: left;
    white-space: nowrap;
}
.scholarship-documents-page td {
    padding: 13px;
    border-bottom: 1px solid var(--line, #e8eaf0);
    font-size: .87rem;
    vertical-align: middle;
}
.scholarship-documents-page tbody tr:hover {
    background: #fcfafb;
}
.scholarship-documents-page .student-cell strong {
    display: block;
}
.scholarship-documents-page .student-cell small,
.scholarship-documents-page .subtle-cell {
    display: block;
    margin-top: 4px;
    color: var(--muted, #68738a);
    font-size: .78rem;
}
.scholarship-documents-page .status-pill {
    display: inline-block;
    padding: 5px 9px;
    border-radius: 999px;
    background: #f1f2f5;
    color: #535b68;
    font-size: .75rem;
    font-weight: 700;
    white-space: nowrap;
}
.scholarship-documents-page .status-approved {
    background: #e8f5ed;
    color: #21663c;
}
.scholarship-documents-page .status-rejected {
    background: #fbeaea;
    color: #a12c2c;
}
.scholarship-documents-page .status-screening {
    background: #eaf1fb;
    color: #315c91;
}
.scholarship-documents-page .status-pending {
    background: #fff4df;
    color: #875b12;
}
.scholarship-documents-page .empty-state {
    padding: 40px 18px;
    border: 1px dashed #dfe2e9;
    border-radius: 11px;
    background: #fcfcfd;
    text-align: center;
}
.scholarship-documents-page .empty-state h3 {
    margin: 0 0 8px;
    color: var(--maroon-dark, #501329);
    font-size: 1.05rem;
}
.scholarship-documents-page .empty-state p {
    max-width: 570px;
    margin: 0 auto;
    color: var(--muted, #68738a);
    line-height: 1.55;
}
.scholarship-documents-page .data-note {
    margin: 17px 0 0;
    padding-top: 13px;
    border-top: 1px solid #eceef2;
    color: var(--muted, #68738a);
    font-size: .77rem;
    line-height: 1.5;
}
@media (max-width: 850px) {
    .scholarship-documents-page .document-filters {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}
@media (max-width: 600px) {
    .scholarship-documents-page {
        width: min(100% - 22px, 1160px);
    }
    .scholarship-documents-page .document-summary {
        grid-template-columns: 1fr;
        gap: 10px;
    }
    .scholarship-documents-page .document-filters {
        grid-template-columns: 1fr;
        padding: 14px;
    }
    .scholarship-documents-page .filter-search,
    .scholarship-documents-page .filter-actions {
        grid-column: auto;
    }
    .scholarship-documents-page .filter-actions {
        justify-content: stretch;
    }
    .scholarship-documents-page .filter-actions .button {
        flex: 1;
    }
    .scholarship-documents-page .panel-heading {
        align-items: flex-start;
    }
}
</style>

<main class="scholarship-documents-page">
    <section class="page-heading">
        <h1>Scholarship Supporting Documents</h1>
        <p>View and filter the supporting document details selected by students in their scholarship applications.</p>
    </section>

    <section class="document-summary" aria-label="Document summary">
        <article class="summary-card">
            <span class="summary-label">Total Document Entries</span>
            <div class="summary-value"><?= number_format((int) $summary['total_documents']) ?></div>
        </article>
        <article class="summary-card">
            <span class="summary-label">Applications with Documents</span>
            <div class="summary-value"><?= number_format((int) $summary['applications_with_documents']) ?></div>
        </article>
        <article class="summary-card">
            <span class="summary-label">Students Represented</span>
            <div class="summary-value"><?= number_format((int) $summary['students_with_documents']) ?></div>
        </article>
    </section>

    <section class="documents-panel">
        <div class="panel-heading">
            <h2>Supporting Document Records</h2>
            <span class="record-count"><?= number_format(count($documents)) ?> record(s)</span>
        </div>

        <form method="get" class="document-filters" action="">
            <div class="filter-field filter-search">
                <label for="document-search">Search records</label>
                <input type="search" id="document-search" name="search" value="<?= $h($search) ?>" placeholder="Student name, LRN, program, or document">
            </div>

            <div class="filter-field">
                <label for="program-filter">Special program</label>
                <select id="program-filter" name="program">
                    <option value="">All programs</option>
                    <?php foreach ($programs as $program): ?>
                        <option value="<?= $h($program['program_id']) ?>" <?= $selectedProgram === (string) $program['program_id'] ? 'selected' : '' ?>><?= $h($program['program_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filter-field">
                <label for="school-year-filter">School year</label>
                <select id="school-year-filter" name="school_year">
                    <option value="">All school years</option>
                    <?php foreach ($schoolYears as $schoolYear): ?>
                        <option value="<?= $h($schoolYear['school_year_id']) ?>" <?= $selectedSchoolYear === (string) $schoolYear['school_year_id'] ? 'selected' : '' ?>><?= $h($schoolYear['school_year']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filter-field">
                <label for="status-filter">Application status</label>
                <select id="status-filter" name="status">
                    <option value="">All statuses</option>
                    <?php foreach ($allowedStatuses as $status): ?>
                        <option value="<?= $h($status) ?>" <?= $selectedStatus === $status ? 'selected' : '' ?>><?= $h($status) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filter-field">
                <label for="document-filter">Document type</label>
                <select id="document-filter" name="document">
                    <option value="">All document types</option>
                    <?php foreach ($documentTypes as $documentType): ?>
                        <option value="<?= $h($documentType) ?>" <?= $selectedDocument === (string) $documentType ? 'selected' : '' ?>><?= $h($documentType) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filter-actions">
                <button type="submit" class="button button-primary">Apply Filters</button>
                <a href="<?= $h(basename($_SERVER['PHP_SELF'] ?? 'scholarship_documents.php')) ?>" class="button button-outline">Clear</a>
            </div>
        </form>

        <?php if ($documents === []): ?>
            <div class="empty-state">
                <h3>No supporting document records found</h3>
                <p><?= ($search !== '' || $selectedProgram !== '' || $selectedSchoolYear !== '' || $selectedStatus !== '' || $selectedDocument !== '')
                    ? 'Try changing or clearing your filters.'
                    : 'Supporting document selections will appear here after students submit scholarship applications.' ?></p>
            </div>
        <?php else: ?>
            <div class="table-scroll">
                <table>
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Special Program</th>
                            <th>School Year</th>
                            <th>Supporting Document</th>
                            <th>Application Status</th>
                            <th>Date Recorded</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($documents as $document): ?>
                            <?php
                                $statusClass = 'status-pending';
                                if ($document['application_status'] === 'Approved') {
                                    $statusClass = 'status-approved';
                                } elseif ($document['application_status'] === 'Rejected') {
                                    $statusClass = 'status-rejected';
                                } elseif ($document['application_status'] === 'For Screening') {
                                    $statusClass = 'status-screening';
                                }

                                $displayDocument = $document['document_name'];
                                if ($document['document_name'] === 'Other (Specify)' && !empty($document['other_document'])) {
                                    $displayDocument = $document['other_document'];
                                }

                                $gradeSection = '';
                                if (!empty($document['grade_level'])) {
                                    $gradeSection = 'Grade ' . $document['grade_level'];
                                }
                                if (!empty($document['section_name'])) {
                                    $gradeSection .= ($gradeSection !== '' ? ' · ' : '') . $document['section_name'];
                                }
                            ?>
                            <tr>
                                <td class="student-cell">
                                    <strong><?= $h($studentName($document)) ?></strong>
                                    <small>LRN: <?= $h($document['lrn'] ?: 'Not provided') ?></small>
                                    <?php if ($gradeSection !== ''): ?>
                                        <small><?= $h($gradeSection) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><?= $h($document['program_name']) ?></td>
                                <td><?= $h($document['school_year']) ?></td>
                                <td>
                                    <?= $h($displayDocument) ?>
                                    <?php if ($document['document_name'] === 'Other (Specify)'): ?>
                                        <span class="subtle-cell">Other supporting document</span>
                                    <?php endif; ?>
                                </td>
                                <td><span class="status-pill <?= $h($statusClass) ?>"><?= $h($document['application_status']) ?></span></td>
                                <td><?= $h(date('M d, Y', strtotime((string) $document['created_at']))) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <p class="data-note">
            Note: The current scholarship database stores the names of supporting documents selected by students. It does not store uploaded files or individual document verification statuses, so this page displays document records only.
        </p>
    </section>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
