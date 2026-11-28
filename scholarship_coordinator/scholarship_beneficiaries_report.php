<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('System Administrator', 'Scholarship Coordinator');

$pageTitle = 'Scholar Masterlist';

$schoolYearFilter = filter_var($_GET['school_year'] ?? '', FILTER_VALIDATE_INT) ?: null;
$programFilter = filter_var($_GET['program'] ?? '', FILTER_VALIDATE_INT) ?: null;
$gradeFilter = filter_var($_GET['grade'] ?? '', FILTER_VALIDATE_INT) ?: null;
$sectionFilter = filter_var($_GET['section'] ?? '', FILTER_VALIDATE_INT) ?: null;
$participationFilter = trim((string) ($_GET['participation'] ?? ''));
$search = trim((string) ($_GET['search'] ?? ''));

$allowedParticipation = ['Currently Enrolled', 'Qualified', 'Not Monitored'];

if (!in_array($participationFilter, $allowedParticipation, true)) {
    $participationFilter = '';
}

$allowedColumns = [
    'lrn' => 'LRN',
    'student' => 'Student Name',
    'contact' => 'Contact Number',
    'program' => 'Scholarship Program',
    'school_year' => 'School Year',
    'grade' => 'Grade Level',
    'section' => 'Section',
    'participation' => 'Scholar Status',
    'renewal' => 'Renewal Eligibility',
    'academic' => 'Academic Performance',
    'behavior' => 'Behavior Remarks',
    'stipend_total' => 'Total Stipend',
    'stipend_claimed' => 'Claimed Stipend',
    'stipend_pending' => 'Pending / Unclaimed Stipend',
    'last_release' => 'Last Release Date',
];

$selectedColumns = [
    'lrn', 'student', 'program', 'school_year', 'grade', 'section', 'participation', 'renewal'
];

$where = [
    "sa.application_status = 'Approved'",
    "COALESCE(sm.participation_status, 'Not Monitored') <> 'Removed'"
];

$params = [];

if ($schoolYearFilter !== null) {
    $where[] = 'sa.school_year_id = :school_year';
    $params['school_year'] = $schoolYearFilter;
}

if ($programFilter !== null) {
    $where[] = 'sa.program_id = :program';
    $params['program'] = $programFilter;
}

if ($gradeFilter !== null) {
    $where[] = 's.current_grade_level_id = :grade';
    $params['grade'] = $gradeFilter;
}

if ($sectionFilter !== null) {
    $where[] = 's.current_section_id = :section';
    $params['section'] = $sectionFilter;
}

if ($participationFilter !== '') {
    if ($participationFilter === 'Not Monitored') {
        $where[] = 'sm.participation_status IS NULL';
    } else {
        $where[] = 'sm.participation_status = :participation';
        $params['participation'] = $participationFilter;
    }
}

if ($search !== '') {
    $where[] = "(
        CONCAT_WS(' ', s.first_name, NULLIF(s.middle_name, ''), s.last_name) LIKE :search_full
        OR s.first_name LIKE :search_first
        OR s.middle_name LIKE :search_middle
        OR s.last_name LIKE :search_last
        OR s.lrn LIKE :search_lrn
        OR s.contact_number LIKE :search_contact
        OR sp.program_name LIKE :search_program
    )";

    $term = '%' . $search . '%';
    $params['search_full'] = '%' . $search . '%';
    $params['search_first'] = $term;
    $params['search_middle'] = $term;
    $params['search_last'] = $term;
    $params['search_lrn'] = $term;
    $params['search_contact'] = $term;
    $params['search_program'] = $term;
}

$whereSql = implode(' AND ', $where);

try {
    $schoolYears = $pdo->query(
        'SELECT school_year_id, school_year
         FROM school_years
         ORDER BY school_year DESC'
    )->fetchAll();

    $programs = $pdo->query(
        'SELECT program_id, program_name
         FROM special_programs
         ORDER BY program_name'
    )->fetchAll();

    $grades = $pdo->query(
        'SELECT grade_level_id, grade_level
         FROM grade_levels
         ORDER BY CAST(grade_level AS UNSIGNED)'
    )->fetchAll();

    $sections = $pdo->query(
        'SELECT section_id, section_name, grade_level_id
         FROM sections
         ORDER BY section_name'
    )->fetchAll();

    $baseFrom = "
        FROM scholarship_applications sa
        INNER JOIN students s
            ON s.student_id = sa.student_id
        INNER JOIN special_programs sp
            ON sp.program_id = sa.program_id
        INNER JOIN school_years sy
            ON sy.school_year_id = sa.school_year_id
        LEFT JOIN grade_levels gl
            ON gl.grade_level_id = s.current_grade_level_id
        LEFT JOIN sections sec
            ON sec.section_id = s.current_section_id
        LEFT JOIN scholarship_monitoring sm
            ON sm.monitoring_id = (
                SELECT sm2.monitoring_id
                FROM scholarship_monitoring sm2
                WHERE sm2.scholarship_application_id =
                    sa.scholarship_application_id
                ORDER BY
                    sm2.monitoring_date DESC,
                    sm2.monitoring_id DESC
                LIMIT 1
            )
    ";

    $summarySql = "
        SELECT
            COUNT(*) AS total_scholars,
            COALESCE(SUM(
                COALESCE(sm.participation_status, 'Not Monitored') = 'Currently Enrolled'
            ), 0) AS currently_enrolled,
            COALESCE(SUM(
                COALESCE(sm.participation_status, 'Not Monitored') = 'Qualified'
            ), 0) AS qualified,
            COALESCE(SUM(sm.participation_status IS NULL), 0) AS not_monitored
        $baseFrom
        WHERE $whereSql
    ";

    $summaryQuery = $pdo->prepare($summarySql);
    $summaryQuery->execute($params);
    $summary = $summaryQuery->fetch() ?: [];

    $stipendJoin = "
        LEFT JOIN (
            SELECT
                scholarship_application_id,
                SUM(amount) AS total_stipend,
                SUM(
                    CASE
                        WHEN claiming_status = 'Claimed'
                        THEN amount ELSE 0
                    END
                ) AS claimed_stipend,
                SUM(
                    CASE
                        WHEN claiming_status IN ('Pending Claim', 'Unclaimed')
                        THEN amount ELSE 0
                    END
                ) AS pending_stipend,
                MAX(release_date) AS last_release
            FROM stipend_records
            GROUP BY scholarship_application_id
        ) st
            ON st.scholarship_application_id =
                sa.scholarship_application_id
    ";

    $recordsSql = "
        SELECT
            sa.scholarship_application_id,
            sa.application_date,
            sa.remarks AS application_remarks,
            s.lrn,
            s.first_name,
            s.middle_name,
            s.last_name,
            s.contact_number,
            s.sex,
            s.birth_date,
            s.current_address,
            sp.program_name,
            sy.school_year,
            gl.grade_level,
            sec.section_name,
            COALESCE(sm.participation_status, 'Not Monitored') AS participation_status,
            sm.renewal_eligibility,
            sm.academic_performance,
            sm.behavior_remarks,
            sm.monitoring_date,
            COALESCE(st.total_stipend, 0) AS total_stipend,
            COALESCE(st.claimed_stipend, 0) AS claimed_stipend,
            COALESCE(st.pending_stipend, 0) AS pending_stipend,
            st.last_release
        $baseFrom
        $stipendJoin
        WHERE $whereSql
        ORDER BY s.last_name, s.first_name, s.middle_name, sy.school_year DESC
    ";

    $recordsQuery = $pdo->prepare($recordsSql);
    $recordsQuery->execute($params);
    $scholars = $recordsQuery->fetchAll();

    if (($_GET['export'] ?? '') === 'csv') {
        $filename = 'scholar-masterlist-' . date('Y-m-d') . '.csv';

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $output = fopen('php://output', 'w');
        fwrite($output, "\xEF\xBB\xBF");

        $headers = [];

        foreach ($selectedColumns as $column) {
            $headers[] = $allowedColumns[$column];
        }

        fputcsv($output, $headers);

        foreach ($scholars as $row) {
            $fullName = trim(
                $row['first_name'] . ' ' .
                ($row['middle_name'] ?? '') . ' ' .
                $row['last_name']
            );

            $values = [
                'lrn' => $row['lrn'] ?: 'Not recorded',
                'student' => $fullName,
                'contact' => $row['contact_number'] ?: 'Not recorded',
                'program' => $row['program_name'],
                'school_year' => $row['school_year'],
                'grade' => $row['grade_level'] !== null
                    ? 'Grade ' . $row['grade_level']
                    : 'Not assigned',
                'section' => $row['section_name'] ?: 'Not assigned',
                'participation' => $row['participation_status'],
                'renewal' => $row['renewal_eligibility'] ?: 'Pending',
                'academic' => $row['academic_performance'] ?: '—',
                'behavior' => $row['behavior_remarks'] ?: '—',
                'stipend_total' => number_format((float) $row['total_stipend'], 2, '.', ''),
                'stipend_claimed' => number_format((float) $row['claimed_stipend'], 2, '.', ''),
                'stipend_pending' => number_format((float) $row['pending_stipend'], 2, '.', ''),
                'last_release' => $row['last_release'] ?: '—',
            ];

            $line = [];

            foreach ($selectedColumns as $column) {
                $line[] = $values[$column] ?? '';
            }

            fputcsv($output, $line);
        }

        fclose($output);
        exit;
    }

} catch (PDOException $exception) {
    error_log('Scholar masterlist: ' . $exception->getMessage());
    http_response_code(500);
    exit('Unable to load the scholar masterlist right now. Please try again later.');
}

$printReport = isset($_GET['print']);
$downloadReport = isset($_GET['download']);

if ($printReport || $downloadReport) {
    $generatedDate = date('F d, Y h:i A');
    $filterDescription = [];

    if ($schoolYearFilter !== null) {
        foreach ($schoolYears as $year) {
            if ((int) $year['school_year_id'] === $schoolYearFilter) {
                $filterDescription[] = 'School Year: ' . $year['school_year'];
                break;
            }
        }
    }

    if ($programFilter !== null) {
        foreach ($programs as $program) {
            if ((int) $program['program_id'] === $programFilter) {
                $filterDescription[] = 'Program: ' . $program['program_name'];
                break;
            }
        }
    }

    if ($gradeFilter !== null) {
        foreach ($grades as $grade) {
            if ((int) $grade['grade_level_id'] === $gradeFilter) {
                $filterDescription[] = 'Grade: ' . $grade['grade_level'];
                break;
            }
        }
    }

    if ($sectionFilter !== null) {
        foreach ($sections as $section) {
            if ((int) $section['section_id'] === $sectionFilter) {
                $filterDescription[] = 'Section: ' . $section['section_name'];
                break;
            }
        }
    }

    if ($participationFilter !== '') {
        $filterDescription[] = 'Status: ' . $participationFilter;
    }

    if ($search !== '') {
        $filterDescription[] = 'Search: ' . $search;
    }

    if ($downloadReport) {
        header('Content-Type: application/msword; charset=utf-8');
        header('Content-Disposition: attachment; filename="scholar-masterlist-' . date('Ymd_His') . '.doc"');
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
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Scholar Masterlist</title>
        <?php $formalLandscape = true; ?>
    <?php require __DIR__ . '/../includes/formal_report_styles.php'; ?>
    </head>

    <body>

        <?php if (!$downloadReport): ?>
            <div class="report-actions">
                <button type="button" onclick="window.print()">
                    Print Masterlist
                </button>
            </div>
        <?php endif; ?>

        <main class="report-page">

            <header class="report-header">
                <h1 class="school-name">
                    Baguio City National High School
                </h1>

                <p class="school-subtitle">
                    OFFICIAL SCHOLARSHIP RECORDS
                </p>
            </header>

            <h2 class="report-title">
                Scholar Masterlist
            </h2>

            <p class="report-description">
                Approved scholarship recipients excluding scholars marked as Removed
            </p>

            <div class="report-meta">
                <span>
                    <strong>Total Scholar Records:</strong>
                    <?= count($scholars) ?>
                </span>

                <span>
                    <strong>Generated:</strong>
                    <?= e($generatedDate) ?>
                </span>
            </div>

            <?php if ($filterDescription): ?>
                <p>
                    <strong>Applied Filters:</strong>
                    <?= e(implode(' | ', $filterDescription)) ?>
                </p>
            <?php endif; ?>

            <table class="report-table">
                <thead>
                    <tr>
                        <th style="width:3%">#</th>
                        <th style="width:17%">Scholar Information</th>
                        <th style="width:12%">Program / School Year</th>
                        <th style="width:10%">Grade / Section</th>
                        <th style="width:9%">Scholar Status</th>
                        <th style="width:9%">Renewal</th>
                        <th style="width:12%">Academic Performance</th>
                        <th style="width:12%">Behavior Remarks</th>
                        <th style="width:16%">Stipend Summary</th>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach ($scholars as $index => $scholar): ?>

                        <?php
                        $fullName = trim(
                            $scholar['first_name'] . ' ' .
                            ($scholar['middle_name'] ?? '') . ' ' .
                            $scholar['last_name']
                        );
                        ?>

                        <tr>
                            <td><?= $index + 1 ?></td>

                            <td>
                                <strong><?= e($fullName) ?></strong><br>
                                LRN: <?= e($scholar['lrn'] ?: 'Not recorded') ?><br>
                                Contact: <?= e($scholar['contact_number'] ?: 'Not recorded') ?>
                            </td>

                            <td>
                                <?= e($scholar['program_name']) ?><br>
                                <?= e($scholar['school_year']) ?>
                            </td>

                            <td>
                                <?= $scholar['grade_level'] !== null ? 'Grade ' . e($scholar['grade_level']) : 'Not assigned' ?><br>
                                <?= e($scholar['section_name'] ?: 'Not assigned') ?>
                            </td>

                            <td><?= e($scholar['participation_status']) ?></td>

                            <td><?= e($scholar['renewal_eligibility'] ?: 'Pending') ?></td>

                            <td><?= nl2br(e($scholar['academic_performance'] ?: 'Not recorded')) ?></td>

                            <td><?= nl2br(e($scholar['behavior_remarks'] ?: 'Not recorded')) ?></td>

                            <td>
                                Total: ₱<?= number_format((float) $scholar['total_stipend'], 2) ?><br>
                                Claimed: ₱<?= number_format((float) $scholar['claimed_stipend'], 2) ?><br>
                                Pending: ₱<?= number_format((float) $scholar['pending_stipend'], 2) ?><br>
                                Last Release: <?= e($scholar['last_release'] ?: '—') ?>
                            </td>
                        </tr>

                    <?php endforeach; ?>

                    <?php if (!$scholars): ?>
                        <tr>
                            <td colspan="9" style="text-align:center;padding:18px">
                                No active approved scholars found.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <div class="report-footer">
                Baguio City National High School |
                Scholar Masterlist |
                <?= count($scholars) ?> record(s)
            </div>

        </main>

        <?php if ($printReport): ?>
            <script>
                window.onload = function () {
                    if (window.parent === window) window.print();
                };
            </script>
        <?php endif; ?>

    </body>
    </html>
    <?php
    exit;
}

$totalScholars = (int) ($summary['total_scholars'] ?? 0);
$currentlyEnrolled = (int) ($summary['currently_enrolled'] ?? 0);
$qualified = (int) ($summary['qualified'] ?? 0);
$notMonitored = (int) ($summary['not_monitored'] ?? 0);

$filterParams = $_GET;

unset(
    $filterParams['print'],
    $filterParams['download'],
    $filterParams['export'],
    $filterParams['columns']
);

$reportQuery = http_build_query($filterParams);
$reportQuery = $reportQuery !== '' ? '&' . $reportQuery : '';

require_once __DIR__ . '/../includes/header.php';
?>

<style>
    .masterlist-page {
        display: grid;
        gap: 22px;
    }

    .masterlist-heading {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 16px;
        flex-wrap: wrap;
    }

    .masterlist-heading h1 {
        margin: 0 0 6px;
        color: var(--text);
    }

    .masterlist-heading p {
        margin: 0;
    }

    .masterlist-actions {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 8px;
    }

    .masterlist-icon-button {
        width: 42px;
        height: 42px;
        min-width: 42px;
        padding: 0;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }

    .masterlist-icon-button svg {
        display: block;
        flex-shrink: 0;
    }

    .masterlist-stats {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 14px;
    }

    .masterlist-stat {
        min-width: 0;
        padding: 18px;
        border: 1px solid var(--line);
        border-radius: 12px;
        background: #fff;
    }

    .masterlist-stat span {
        display: block;
        color: var(--muted);
        font-size: .88rem;
    }

    .masterlist-stat strong {
        display: block;
        margin-top: 7px;
        color: var(--maroon);
        font-size: 1.8rem;
        line-height: 1.2;
    }

    .masterlist-filters {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 13px;
        padding: 18px;
        border: 1px solid var(--line);
        border-radius: 12px;
        background: #fafafb;
    }

    .masterlist-filters label {
        display: block;
        margin: 0;
        color: var(--text);
        font-size: .87rem;
        font-weight: 650;
    }

    .masterlist-filters input,
    .masterlist-filters select {
        width: 100%;
        margin-top: 7px;
    }

    .masterlist-search {
        grid-column: span 2;
    }

    .masterlist-filter-actions {
        display: flex;
        align-items: end;
        gap: 8px;
        flex-wrap: wrap;
    }

    .masterlist-filter-actions .button {
        white-space: nowrap;
    }

    .masterlist-table-wrap {
        width: 100%;
        overflow-x: auto;
    }

    .masterlist-table {
        width: 100%;
        min-width: 1050px;
        border-collapse: collapse;
    }

    .masterlist-table th,
    .masterlist-table td {
        padding: 13px 12px;
        text-align: left;
        vertical-align: middle;
        border-bottom: 1px solid var(--line);
    }

    .masterlist-table th {
        color: var(--maroon);
        background: #faf7f8;
        font-size: .84rem;
        white-space: nowrap;
    }

    .masterlist-table tbody tr:hover {
        background: #fcfafb;
    }

    .masterlist-student-name {
        display: block;
        color: var(--text);
        font-weight: 700;
    }

    .masterlist-subtext {
        display: block;
        margin-top: 4px;
        color: var(--muted);
        font-size: .78rem;
    }

    .masterlist-program {
        display: inline-block;
        padding: 5px 9px;
        border-radius: 20px;
        background: #f6edf1;
        color: var(--maroon);
        font-size: .78rem;
        font-weight: 700;
        white-space: nowrap;
    }

    .masterlist-status {
        display: inline-block;
        padding: 5px 9px;
        border-radius: 20px;
        font-size: .77rem;
        font-weight: 700;
        white-space: nowrap;
    }

    .masterlist-status.enrolled {
        color: #17633a;
        background: #e8f7ed;
    }

    .masterlist-status.qualified {
        color: #24518a;
        background: #eaf2ff;
    }

    .masterlist-status.unmonitored {
        color: #765a16;
        background: #fff5df;
    }

    .masterlist-empty {
        padding: 36px 18px;
        text-align: center;
        border: 1px dashed var(--line);
        border-radius: 12px;
        background: #fcfcfd;
    }

    .masterlist-empty h2 {
        margin: 0 0 7px;
        color: var(--maroon);
        font-size: 1.1rem;
    }

    .masterlist-empty p {
        margin: 0;
        color: var(--muted);
    }








    @media (max-width: 900px) {
        .masterlist-stats {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .masterlist-filters {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

    }

    @media (max-width: 600px) {
        .masterlist-filters {
            grid-template-columns: 1fr;
        }

        .masterlist-search {
            grid-column: auto;
        }

        .masterlist-filter-actions {
            align-items: stretch;
        }


        .masterlist-actions {
            width: auto;
            justify-content: flex-end;
        }
    }
</style>

<section class="form-card form-card-wide masterlist-page">

    <div class="masterlist-heading">

        <div>
            <h1>Scholar Masterlist</h1>
        </div>

    </div>

    <div class="masterlist-stats">

        <div class="masterlist-stat">
            <span>Total Scholar Records</span>
            <strong><?= number_format($totalScholars) ?></strong>
            <small>Approved and not removed</small>
        </div>

        <div class="masterlist-stat">
            <span>Currently Enrolled</span>
            <strong><?= number_format($currentlyEnrolled) ?></strong>
            <small>Latest monitoring status</small>
        </div>

        <div class="masterlist-stat">
            <span>Qualified</span>
            <strong><?= number_format($qualified) ?></strong>
            <small>Latest monitoring status</small>
        </div>

        <div class="masterlist-stat">
            <span>Not Yet Monitored</span>
            <strong><?= number_format($notMonitored) ?></strong>
            <small>Approved without monitoring record</small>
        </div>

    </div>

    <form method="get" class="table-search-form">
        <div class="table-search-toolbar">
            <div class="table-search-field">
                <label class="masterlist-search">Search Scholar
                            <input
                                type="search" data-student-suggestions="scholars" data-suggestion-url="<?= BASE_URL ?>/includes/student_suggestions.php"
                                name="search"
                                value="<?= e($search) ?>"
                                placeholder="Student name, LRN, contact number, or program..."
                            >
                        </label>
            </div>
            <button type="submit" class="button button-primary">Search</button>
            <details class="table-search-disclosure">
                <summary class="button button-outline">
                    Filters<?= ($schoolYearFilter || $programFilter || $gradeFilter || $sectionFilter || $participationFilter !== '') ? ' (active)' : '' ?>
                </summary>
                <div class="table-search-panel">
                    <h3 class="table-search-panel-title">Filter results</h3>
                    <div class="table-search-fields">
                        <label>School Year
                            <select name="school_year">
                                <option value="">All School Years</option>

                                <?php foreach ($schoolYears as $year): ?>
                                    <option
                                        value="<?= (int) $year['school_year_id'] ?>"
                                        <?= $schoolYearFilter === (int) $year['school_year_id'] ? 'selected' : '' ?>
                                    >
                                        <?= e($year['school_year']) ?>
                                    </option>
                                <?php endforeach; ?>

                            </select>
                        </label>

                        <label>Scholarship Program
                            <select name="program">
                                <option value="">All Programs</option>

                                <?php foreach ($programs as $program): ?>
                                    <option
                                        value="<?= (int) $program['program_id'] ?>"
                                        <?= $programFilter === (int) $program['program_id'] ? 'selected' : '' ?>
                                    >
                                        <?= e($program['program_name']) ?>
                                    </option>
                                <?php endforeach; ?>

                            </select>
                        </label>

                        <label>Grade Level
                            <select name="grade">
                                <option value="">All Grades</option>

                                <?php foreach ($grades as $grade): ?>
                                    <option
                                        value="<?= (int) $grade['grade_level_id'] ?>"
                                        <?= $gradeFilter === (int) $grade['grade_level_id'] ? 'selected' : '' ?>
                                    >
                                        Grade <?= e($grade['grade_level']) ?>
                                    </option>
                                <?php endforeach; ?>

                            </select>
                        </label>

                        <label>Section
                            <select name="section">
                                <option value="">All Sections</option>

                                <?php foreach ($sections as $section): ?>
                                    <option
                                        value="<?= (int) $section['section_id'] ?>"
                                        <?= $sectionFilter === (int) $section['section_id'] ? 'selected' : '' ?>
                                    >
                                        <?= e($section['section_name']) ?>
                                    </option>
                                <?php endforeach; ?>

                            </select>
                        </label>

                        <label>Scholar Status
                            <select name="participation">
                                <option value="">All Active Statuses</option>

                                <option value="Currently Enrolled" <?= $participationFilter === 'Currently Enrolled' ? 'selected' : '' ?>>
                                    Currently Enrolled
                                </option>

                                <option value="Qualified" <?= $participationFilter === 'Qualified' ? 'selected' : '' ?>>
                                    Qualified
                                </option>

                                <option value="Not Monitored" <?= $participationFilter === 'Not Monitored' ? 'selected' : '' ?>>
                                    Not Monitored
                                </option>

                            </select>
                        </label>
                    </div>
                    <div class="table-search-actions">
                        <button type="submit" class="button button-primary">Apply filters</button>
                        <a class="button button-outline" href="<?= BASE_URL ?>/scholarship_coordinator/scholarship_beneficiaries_report.php">Clear filters</a>
                    </div>
                </div>
            </details>
        </div>
    </form>

    <div>

        <div class="masterlist-heading" style="margin-bottom:12px">

            <div>
                <h2 style="margin:0">Scholar Records</h2>

                <p class="muted" style="margin-top:4px">
                    <?= number_format(count($scholars)) ?> matching active
                    <?= count($scholars) === 1 ? 'scholar' : 'scholars' ?>
                </p>
            </div>

            <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">

                <span class="masterlist-program">
                    Approved Scholars
                </span>

                <div class="masterlist-actions">


                    <a
                        href="<?= e(BASE_URL) ?>/scholarship_coordinator/scholarship_beneficiaries_report.php?print=1<?= e($reportQuery) ?>"
                        class="button button-outline masterlist-icon-button"
                        data-print-report target="_blank"
                        rel="noopener"
                        title="Print Scholar Masterlist"
                        aria-label="Print Scholar Masterlist"
                    >
                        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M6 9V2h12v7"/>
                            <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/>
                            <path d="M6 14h12v8H6z"/>
                        </svg>
                    </a>

                    <a
                        href="<?= e(BASE_URL) ?>/scholarship_coordinator/scholarship_beneficiaries_report.php?export=csv<?= e($reportQuery) ?>"
                        class="button button-primary masterlist-icon-button"
                        title="Download Scholar Masterlist CSV"
                        aria-label="Download Scholar Masterlist CSV"
                    >
                        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                            <path d="m7 10 5 5 5-5"/>
                            <path d="M12 15V3"/>
                        </svg>
                    </a>

                </div>

            </div>

        </div>

        <?php if (!$scholars): ?>

            <div class="masterlist-empty">
                <h2>No active scholars found</h2>

                <p>
                    There are no approved scholarship applications matching your selected filters.
                </p>
            </div>

        <?php else: ?>

            <div class="masterlist-table-wrap">

                <table class="masterlist-table">

                    <thead>
                        <tr>
                            <th>Scholar</th>
                            <th>Contact</th>
                            <th>Program</th>
                            <th>School Year</th>
                            <th>Grade Level</th>
                            <th>Section</th>
                            <th>Scholar Status</th>
                            <th>Renewal</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php foreach ($scholars as $scholar): ?>

                            <?php
                            $fullName = trim(
                                $scholar['first_name'] . ' ' .
                                ($scholar['middle_name'] ?? '') . ' ' .
                                $scholar['last_name']
                            );

                            $participation = $scholar['participation_status'];

                            $statusClass = 'unmonitored';

                            if ($participation === 'Currently Enrolled') {
                                $statusClass = 'enrolled';
                            } elseif ($participation === 'Qualified') {
                                $statusClass = 'qualified';
                            }
                            ?>

                            <tr>

                                <td>
                                    <span class="masterlist-student-name">
                                        <?= e($fullName) ?>
                                    </span>

                                    <span class="masterlist-subtext">
                                        LRN: <?= e($scholar['lrn'] ?: 'Not recorded') ?>
                                    </span>
                                </td>

                                <td>
                                    <?= e($scholar['contact_number'] ?: 'Not recorded') ?>
                                </td>

                                <td>
                                    <span class="masterlist-program">
                                        <?= e($scholar['program_name']) ?>
                                    </span>
                                </td>

                                <td>
                                    <?= e($scholar['school_year']) ?>
                                </td>

                                <td>
                                    <?= $scholar['grade_level'] !== null ? 'Grade ' . e($scholar['grade_level']) : 'Not assigned' ?>
                                </td>

                                <td>
                                    <?= e($scholar['section_name'] ?: 'Not assigned') ?>
                                </td>

                                <td>
                                    <span class="masterlist-status <?= e($statusClass) ?>">
                                        <?= e($participation) ?>
                                    </span>
                                </td>

                                <td>
                                    <?= e($scholar['renewal_eligibility'] ?: 'Pending') ?>
                                </td>

                            </tr>

                        <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        <?php endif; ?>

    </div>



</section>

<script src="<?= BASE_URL ?>/assets/js/student_suggestions.js" defer></script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
