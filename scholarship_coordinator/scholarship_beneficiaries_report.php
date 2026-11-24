<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_role('System Administrator', 'Scholarship Coordinator');

$pageTitle = 'Scholarship Beneficiaries';

$schoolYearFilter = filter_var($_GET['school_year'] ?? '', FILTER_VALIDATE_INT) ?: null;
$programFilter = filter_var($_GET['program'] ?? '', FILTER_VALIDATE_INT) ?: null;
$gradeFilter = filter_var($_GET['grade'] ?? '', FILTER_VALIDATE_INT) ?: null;
$sectionFilter = filter_var($_GET['section'] ?? '', FILTER_VALIDATE_INT) ?: null;
$search = trim((string) ($_GET['search'] ?? ''));

$allowedColumns = [
    'lrn' => 'LRN',
    'student' => 'Student Name',
    'program' => 'Special Program',
    'school_year' => 'School Year',
    'grade' => 'Grade Level',
    'section' => 'Section',
    'participation' => 'Participation Status',
    'renewal' => 'Renewal Eligibility',
    'academic' => 'Academic Performance',
    'behavior' => 'Behavior Remarks',
    'stipend_total' => 'Total Stipend',
    'stipend_claimed' => 'Claimed Stipend',
    'stipend_pending' => 'Pending / Unclaimed Stipend',
    'last_release' => 'Last Release Date',
];

$selectedColumns = $_GET['columns'] ?? [
    'lrn', 'student', 'program', 'school_year',
    'grade', 'section', 'participation'
];

if (!is_array($selectedColumns)) {
    $selectedColumns = [];
}

$selectedColumns = array_values(
    array_intersect(array_keys($allowedColumns), $selectedColumns)
);

$where = ["sa.application_status = 'Approved'"];
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

if ($search !== '') {
    $where[] = "(
        s.first_name LIKE :search_first
        OR s.middle_name LIKE :search_middle
        OR s.last_name LIKE :search_last
        OR s.lrn LIKE :search_lrn
    )";

    $term = '%' . $search . '%';
    $params['search_first'] = $term;
    $params['search_middle'] = $term;
    $params['search_last'] = $term;
    $params['search_lrn'] = $term;
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
            COUNT(*) AS total_beneficiaries,
            COALESCE(SUM(
                sm.participation_status = 'Currently Enrolled'
            ), 0) AS currently_enrolled,
            COALESCE(SUM(
                sm.participation_status = 'Qualified'
            ), 0) AS qualified,
            COALESCE(SUM(
                sm.participation_status = 'Removed'
            ), 0) AS removed
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
                        WHEN claiming_status IN (
                            'Pending Claim', 'Unclaimed'
                        )
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
            s.lrn,
            s.first_name,
            s.middle_name,
            s.last_name,
            sp.program_name,
            sy.school_year,
            gl.grade_level,
            sec.section_name,
            sm.participation_status,
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
        ORDER BY s.last_name, s.first_name, s.middle_name
    ";

    $recordsQuery = $pdo->prepare($recordsSql);
    $recordsQuery->execute($params);
    $beneficiaries = $recordsQuery->fetchAll();

    if (($_GET['export'] ?? '') === 'csv') {
        if (!$selectedColumns) {
            $selectedColumns = ['student', 'program', 'participation'];
        }

        $filename = 'scholarship-beneficiaries-' . date('Y-m-d') . '.csv';

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

        foreach ($beneficiaries as $row) {
            $fullName = trim(
                $row['first_name'] . ' ' .
                ($row['middle_name'] ?? '') . ' ' .
                $row['last_name']
            );

            $values = [
                'lrn' => $row['lrn'] ?: 'Not recorded',
                'student' => $fullName,
                'program' => $row['program_name'],
                'school_year' => $row['school_year'],
                'grade' => $row['grade_level'] !== null
                    ? 'Grade ' . $row['grade_level']
                    : 'Not assigned',
                'section' => $row['section_name'] ?: 'Not assigned',
                'participation' => $row['participation_status'] ?: 'Not Monitored',
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
    error_log('Scholarship beneficiaries report: ' . $exception->getMessage());
    http_response_code(500);
    exit('Unable to load the scholarship beneficiaries report right now. Please try again later.');
}

$totalBeneficiaries = (int) ($summary['total_beneficiaries'] ?? 0);
$currentlyEnrolled = (int) ($summary['currently_enrolled'] ?? 0);
$qualified = (int) ($summary['qualified'] ?? 0);
$removed = (int) ($summary['removed'] ?? 0);
$notMonitored = max(0, $totalBeneficiaries - $currentlyEnrolled - $qualified - $removed);

require_once __DIR__ . '/../includes/header.php';
?>

<style>
    .beneficiaries-page { display: grid; gap: 22px; }
    .beneficiaries-heading { display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; flex-wrap: wrap; }
    .beneficiaries-heading h1 { margin: 0 0 6px; color: var(--text); }
    .beneficiaries-heading p { margin: 0; }
    .beneficiary-stats { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 14px; }
    .beneficiary-stat { min-width: 0; padding: 18px; border: 1px solid var(--line); border-radius: 12px; background: #fff; }
    .beneficiary-stat span { display: block; color: var(--muted); font-size: .88rem; }
    .beneficiary-stat strong { display: block; margin-top: 7px; color: var(--maroon); font-size: 1.8rem; line-height: 1.2; }
    .beneficiary-stat small { display: block; margin-top: 5px; color: var(--muted); font-size: .78rem; }
    .beneficiary-filters { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 13px; padding: 18px; border: 1px solid var(--line); border-radius: 12px; background: #fafafb; }
    .beneficiary-filters label { display: block; margin: 0; color: var(--text); font-size: .87rem; font-weight: 650; }
    .beneficiary-filters input, .beneficiary-filters select { width: 100%; margin-top: 7px; }
    .beneficiary-search { grid-column: span 2; }
    .beneficiary-filter-actions { display: flex; align-items: end; gap: 8px; flex-wrap: wrap; }
    .beneficiary-filter-actions .button { white-space: nowrap; }
    .beneficiary-table-wrap { width: 100%; overflow-x: auto; }
    .beneficiary-table { width: 100%; min-width: 760px; border-collapse: collapse; }
    .beneficiary-table th, .beneficiary-table td { padding: 13px 12px; text-align: left; vertical-align: middle; border-bottom: 1px solid var(--line); }
    .beneficiary-table th { color: var(--maroon); background: #faf7f8; font-size: .84rem; white-space: nowrap; }
    .beneficiary-table tbody tr:hover { background: #fcfafb; }
    .beneficiary-student-name { display: block; color: var(--text); font-weight: 700; }
    .beneficiary-subtext { display: block; margin-top: 4px; color: var(--muted); font-size: .78rem; }
    .beneficiary-program { display: inline-block; padding: 5px 9px; border-radius: 20px; background: #f6edf1; color: var(--maroon); font-size: .78rem; font-weight: 700; }
    .beneficiary-status { display: inline-block; padding: 5px 9px; border-radius: 20px; font-size: .77rem; font-weight: 700; white-space: nowrap; }
    .beneficiary-status.enrolled { color: #17633a; background: #e8f7ed; }
    .beneficiary-status.qualified { color: #24518a; background: #eaf2ff; }
    .beneficiary-status.removed { color: #9b1c1c; background: #fff0f0; }
    .beneficiary-status.unmonitored { color: #765a16; background: #fff5df; }
    .beneficiary-empty { padding: 36px 18px; text-align: center; border: 1px dashed var(--line); border-radius: 12px; background: #fcfcfd; }
    .beneficiary-empty h2 { margin: 0 0 7px; color: var(--maroon); font-size: 1.1rem; }
    .beneficiary-empty p { margin: 0; color: var(--muted); }
    .beneficiary-export { display: grid; gap: 14px; padding: 18px; border: 1px solid var(--line); border-radius: 12px; background: #fff; }
    .beneficiary-export h2 { margin: 0; font-size: 1.1rem; }
    .beneficiary-export p { margin: 4px 0 0; color: var(--muted); font-size: .88rem; }
    .beneficiary-export-options { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 10px 16px; }
    .beneficiary-export-options label { display: flex; align-items: center; gap: 8px; margin: 0; color: var(--text); font-size: .86rem; }
    .beneficiary-export-options input { width: auto; margin: 0; }
    .beneficiary-export-actions { display: flex; justify-content: flex-end; }
    @media (max-width: 900px) { .beneficiary-stats { grid-template-columns: repeat(2, minmax(0, 1fr)); } .beneficiary-filters { grid-template-columns: repeat(2, minmax(0, 1fr)); } .beneficiary-export-options { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    @media (max-width: 600px) { .beneficiary-filters { grid-template-columns: 1fr; } .beneficiary-search { grid-column: auto; } .beneficiary-filter-actions { align-items: stretch; } .beneficiary-export-options { grid-template-columns: 1fr; } }
</style>

<section class="form-card form-card-wide beneficiaries-page">
    <div class="beneficiaries-heading">
        <div>
            <h1>Scholarship Beneficiaries</h1>
            <p class="muted">Monitor approved scholarship students and stipend records.</p>
        </div>
    </div>

    <div class="beneficiary-stats">
        <div class="beneficiary-stat">
            <span>Total Beneficiaries</span>
            <strong><?= number_format($totalBeneficiaries) ?></strong>
            <small>Approved scholarship applications</small>
        </div>
        <div class="beneficiary-stat">
            <span>Currently Enrolled</span>
            <strong><?= number_format($currentlyEnrolled) ?></strong>
            <small>Latest monitoring status</small>
        </div>
        <div class="beneficiary-stat">
            <span>Qualified</span>
            <strong><?= number_format($qualified) ?></strong>
            <small>Latest monitoring status</small>
        </div>
        <div class="beneficiary-stat">
            <span>Removed</span>
            <strong><?= number_format($removed) ?></strong>
            <small><?= number_format($notMonitored) ?> not yet monitored</small>
        </div>
    </div>

    <form method="GET" class="beneficiary-filters">
        <label>School Year
            <select name="school_year">
                <option value="">All School Years</option>
                <?php foreach ($schoolYears as $year): ?>
                    <option value="<?= (int) $year['school_year_id'] ?>" <?= $schoolYearFilter === (int) $year['school_year_id'] ? 'selected' : '' ?>><?= e($year['school_year']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>

        <label>Program
            <select name="program">
                <option value="">All Programs</option>
                <?php foreach ($programs as $program): ?>
                    <option value="<?= (int) $program['program_id'] ?>" <?= $programFilter === (int) $program['program_id'] ? 'selected' : '' ?>><?= e($program['program_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>

        <label>Grade Level
            <select name="grade">
                <option value="">All Grades</option>
                <?php foreach ($grades as $grade): ?>
                    <option value="<?= (int) $grade['grade_level_id'] ?>" <?= $gradeFilter === (int) $grade['grade_level_id'] ? 'selected' : '' ?>>Grade <?= e($grade['grade_level']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>

        <label>Section
            <select name="section">
                <option value="">All Sections</option>
                <?php foreach ($sections as $section): ?>
                    <option value="<?= (int) $section['section_id'] ?>" <?= $sectionFilter === (int) $section['section_id'] ? 'selected' : '' ?>><?= e($section['section_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>

        <label class="beneficiary-search">Search Beneficiaries
            <input type="search" name="search" value="<?= e($search) ?>" placeholder="Student name or LRN...">
        </label>

        <div class="beneficiary-filter-actions">
            <button type="submit" class="button button-primary">Apply Filters</button>
            <a class="button button-outline" href="<?= e(BASE_URL) ?>/scholarship_coordinator/scholarship_beneficiaries_report.php">Reset</a>
        </div>
    </form>

    <div>
        <div class="beneficiaries-heading" style="margin-bottom: 12px;">
            <div>
                <h2 style="margin:0;">Beneficiary Records</h2>
                <p class="muted" style="margin-top:4px;">
                    <?= number_format(count($beneficiaries)) ?> matching approved <?= count($beneficiaries) === 1 ? 'beneficiary' : 'beneficiaries' ?>
                </p>
            </div>
            <span class="beneficiary-program">Approved Only</span>
        </div>

        <?php if (!$beneficiaries): ?>
            <div class="beneficiary-empty">
                <h2>No beneficiaries found</h2>
                <p>There are no approved scholarship applications matching your selected filters.</p>
            </div>
        <?php else: ?>
            <div class="beneficiary-table-wrap">
                <table class="beneficiary-table">
                    <thead>
                        <tr><th>Student</th><th>Program</th><th>Grade Level</th><th>Section</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($beneficiaries as $beneficiary): ?>
                            <?php
                                $fullName = trim(
                                    $beneficiary['first_name'] . ' ' .
                                    ($beneficiary['middle_name'] ?? '') . ' ' .
                                    $beneficiary['last_name']
                                );
                                $participation = $beneficiary['participation_status'] ?: 'Not Monitored';
                                $statusClass = match ($participation) {
                                    'Currently Enrolled' => 'enrolled',
                                    'Qualified' => 'qualified',
                                    'Removed' => 'removed',
                                    default => 'unmonitored',
                                };
                            ?>
                            <tr>
                                <td>
                                    <span class="beneficiary-student-name"><?= e($fullName) ?></span>
                                    <span class="beneficiary-subtext">LRN: <?= e($beneficiary['lrn'] ?: 'Not recorded') ?></span>
                                </td>
                                <td><span class="beneficiary-program"><?= e($beneficiary['program_name']) ?></span></td>
                                <td><?= $beneficiary['grade_level'] !== null ? 'Grade ' . e($beneficiary['grade_level']) : 'Not assigned' ?></td>
                                <td><?= e($beneficiary['section_name'] ?: 'Not assigned') ?></td>
                                <td><span class="beneficiary-status <?= e($statusClass) ?>"><?= e($participation) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <form method="GET" class="beneficiary-export">
        <input type="hidden" name="school_year" value="<?= $schoolYearFilter !== null ? (int) $schoolYearFilter : '' ?>">
        <input type="hidden" name="program" value="<?= $programFilter !== null ? (int) $programFilter : '' ?>">
        <input type="hidden" name="grade" value="<?= $gradeFilter !== null ? (int) $gradeFilter : '' ?>">
        <input type="hidden" name="section" value="<?= $sectionFilter !== null ? (int) $sectionFilter : '' ?>">
        <input type="hidden" name="search" value="<?= e($search) ?>">

        <div>
            <h2>Export Report</h2>
            <p>Select which columns to include in the exported CSV file. The file can be opened in Microsoft Excel.</p>
        </div>

        <div class="beneficiary-export-options">
            <?php foreach ($allowedColumns as $key => $label): ?>
                <label>
                    <input type="checkbox" name="columns[]" value="<?= e($key) ?>" <?= in_array($key, $selectedColumns, true) ? 'checked' : '' ?>>
                    <?= e($label) ?>
                </label>
            <?php endforeach; ?>
        </div>

        <div class="beneficiary-export-actions">
            <button type="submit" name="export" value="csv" class="button button-primary">Export to Excel (CSV)</button>
        </div>
    </form>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
