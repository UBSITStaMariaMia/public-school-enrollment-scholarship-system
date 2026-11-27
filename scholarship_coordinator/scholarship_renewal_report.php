<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_role('System Administrator', 'Scholarship Coordinator');
$pageTitle = 'Scholarship Renewal Report';

$schoolYear = filter_var($_GET['school_year'] ?? '', FILTER_VALIDATE_INT) ?: null;
$program = filter_var($_GET['program'] ?? '', FILTER_VALIDATE_INT) ?: null;
$grade = filter_var($_GET['grade'] ?? '', FILTER_VALIDATE_INT) ?: null;
$section = filter_var($_GET['section'] ?? '', FILTER_VALIDATE_INT) ?: null;
$status = (string) ($_GET['status'] ?? '');
$participation = (string) ($_GET['participation'] ?? '');
$search = trim((string) ($_GET['search'] ?? ''));

$where = ["sa.application_status = 'Approved'"];
$params = [];
if ($schoolYear) { $where[] = 'sa.school_year_id = :school_year'; $params['school_year'] = $schoolYear; }
if ($program) { $where[] = 'sa.program_id = :program'; $params['program'] = $program; }
if ($grade) { $where[] = 's.current_grade_level_id = :grade'; $params['grade'] = $grade; }
if ($section) { $where[] = 's.current_section_id = :section'; $params['section'] = $section; }
if (in_array($status, ['Eligible', 'Not Eligible', 'Pending', 'Not Monitored'], true)) {
    $where[] = $status === 'Not Monitored'
        ? 'sm.monitoring_id IS NULL'
        : 'sm.renewal_eligibility = :renewal_status';
    if ($status !== 'Not Monitored') { $params['renewal_status'] = $status; }
}
if (in_array($participation, ['Qualified', 'Currently Enrolled', 'Removed'], true)) {
    $where[] = 'sm.participation_status = :participation';
    $params['participation'] = $participation;
}
if ($search !== '') {
    $where[] = "(CONCAT_WS(' ', s.first_name, NULLIF(s.middle_name, ''), s.last_name) LIKE :full_name OR s.first_name LIKE :first_name OR s.middle_name LIKE :middle_name OR s.last_name LIKE :last_name OR s.lrn LIKE :lrn)";
    $term = '%' . $search . '%';
    $params['full_name'] = $term;
    $params['first_name'] = $term; $params['middle_name'] = $term;
    $params['last_name'] = $term; $params['lrn'] = $term;
}
$whereSql = implode(' AND ', $where);
$schoolYears = []; $programs = []; $grades = []; $sections = [];
$records = []; $summary = [];

try {
    $schoolYears = $pdo->query('SELECT school_year_id, school_year FROM school_years ORDER BY school_year DESC')->fetchAll();
    $programs = $pdo->query('SELECT program_id, program_name FROM special_programs ORDER BY program_name')->fetchAll();
    $grades = $pdo->query('SELECT grade_level_id, grade_level FROM grade_levels ORDER BY CAST(grade_level AS UNSIGNED)')->fetchAll();
    $sections = $pdo->query('SELECT section_id, section_name FROM sections ORDER BY section_name')->fetchAll();

    $from = "FROM scholarship_applications sa
        INNER JOIN students s ON s.student_id = sa.student_id
        INNER JOIN special_programs sp ON sp.program_id = sa.program_id
        INNER JOIN school_years sy ON sy.school_year_id = sa.school_year_id
        LEFT JOIN grade_levels gl ON gl.grade_level_id = s.current_grade_level_id
        LEFT JOIN sections sec ON sec.section_id = s.current_section_id
        LEFT JOIN scholarship_monitoring sm ON sm.monitoring_id = (
            SELECT sm2.monitoring_id FROM scholarship_monitoring sm2
            WHERE sm2.scholarship_application_id = sa.scholarship_application_id
            ORDER BY sm2.monitoring_date DESC, sm2.monitoring_id DESC LIMIT 1
        )";

    $summaryQuery = $pdo->prepare("SELECT COUNT(*) AS total,
        SUM(sm.renewal_eligibility = 'Eligible') AS eligible,
        SUM(sm.renewal_eligibility = 'Not Eligible') AS not_eligible,
        SUM(sm.monitoring_id IS NULL OR sm.renewal_eligibility = 'Pending') AS pending,
        SUM(sm.participation_status = 'Removed') AS removed
        $from WHERE $whereSql");
    $summaryQuery->execute($params);
    $summary = $summaryQuery->fetch() ?: [];

    $sql = "SELECT sa.scholarship_application_id, s.lrn, s.first_name, s.middle_name, s.last_name,
        sp.program_name, sy.school_year, gl.grade_level, sec.section_name,
        sm.academic_performance, sm.behavior_remarks, sm.participation_status,
        sm.renewal_eligibility, sm.monitoring_date
        $from WHERE $whereSql ORDER BY s.last_name, s.first_name";
    $query = $pdo->prepare($sql);
    $query->execute($params);
    $records = $query->fetchAll();


} catch (PDOException $ex) {
    error_log('Scholarship renewal report: ' . $ex->getMessage());
    $records = []; $summary = [];
    $error = 'Unable to load the scholarship renewal report.';
}

require_once __DIR__ . '/../includes/header.php';
?>
<style>
    .renewal-page { display:grid; gap:24px; }
    .renewal-hero { padding:26px 30px; border-radius:16px; color:#fff; background:linear-gradient(120deg,#55152d,#79213f); }
    .renewal-hero .eyebrow { margin:0 0 8px; color:#f3dce5; font-size:.76rem; font-weight:700; letter-spacing:.12em; text-transform:uppercase; }
    .renewal-hero h1 { margin:0 0 8px; color:#fff; font-size:1.8rem; }
    .renewal-hero p { margin:0; color:#f8eaf0; max-width:760px; }
    .renewal-overview { display:grid; grid-template-columns:repeat(5,minmax(0,1fr)); gap:12px; }
    .renewal-stat { min-width:0; padding:17px 16px; border:1px solid var(--line); border-top:4px solid #9b6a7b; border-radius:10px; background:#fff; }
    .renewal-stat.eligible { border-top-color:#278451; }
    .renewal-stat.ineligible { border-top-color:#bd4242; }
    .renewal-stat.pending { border-top-color:#d29a28; }
    .renewal-stat.removed { border-top-color:#6d7480; }
    .renewal-stat span { display:block; color:var(--muted); font-size:.82rem; font-weight:650; }
    .renewal-stat strong { display:block; margin-top:9px; color:var(--text); font-size:1.85rem; line-height:1; }
    .renewal-stat small { display:block; margin-top:8px; color:var(--muted); font-size:.75rem; }
    .renewal-section-title { display:flex; align-items:center; justify-content:space-between; gap:12px; margin:0 0 13px; }
    .renewal-section-title h2 { margin:0; color:var(--text); font-size:1.12rem; }
    .renewal-filters { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:14px 16px; padding:20px; border:1px solid var(--line); border-radius:14px; background:#f8f8fa; }
    .renewal-filters label { display:block; margin:0; color:var(--text); font-size:.85rem; font-weight:650; }
    .renewal-filters input,.renewal-filters select { width:100%; margin-top:7px; }
    .renewal-filter-actions { display:flex; align-items:end; gap:9px; flex-wrap:wrap; }
    .renewal-filter-actions .button { white-space:nowrap; }
    .renewal-search { grid-column:span 2; }
    .renewal-records { padding:20px; border:1px solid var(--line); border-radius:14px; background:#fff; }
    .renewal-records-head { display:flex; align-items:center; justify-content:space-between; gap:12px; margin-bottom:14px; }
    .renewal-records-head h2 { margin:0 0 4px; color:var(--text); }
    .renewal-records-head p { margin:0; color:var(--muted); font-size:.86rem; }
    .renewal-approved-tag { padding:6px 11px; border-radius:20px; background:#f6edf1; color:var(--maroon); font-size:.76rem; font-weight:700; white-space:nowrap; }
    .renewal-table-wrap { width:100%; overflow-x:auto; }
    .renewal-table { width:100%; min-width:980px; border-collapse:collapse; }
    .renewal-table th,.renewal-table td { padding:13px 12px; text-align:left; vertical-align:middle; border-bottom:1px solid var(--line); }
    .renewal-table th { color:#fff; background:#67203a; font-size:.82rem; white-space:nowrap; }
    .renewal-table tbody tr:nth-child(even) { background:#faf8f9; }
    .renewal-table tbody tr:hover { background:#f7eef2; }
    .renewal-student-name { display:block; color:var(--text); font-weight:700; }
    .renewal-subtext { display:block; margin-top:4px; color:var(--muted); font-size:.77rem; }
    .renewal-program { display:inline-block; padding:5px 9px; border-radius:20px; background:#f6edf1; color:var(--maroon); font-size:.77rem; font-weight:700; }
    .renewal-status { display:inline-block; padding:5px 9px; border-radius:20px; font-size:.76rem; font-weight:700; white-space:nowrap; }
    .renewal-status.eligible { color:#17633a; background:#e8f7ed; }
    .renewal-status.not-eligible { color:#9b1c1c; background:#fff0f0; }
    .renewal-status.pending,.renewal-status.unmonitored { color:#765a16; background:#fff5df; }
    .renewal-status.removed { color:#9b1c1c; background:#fff0f0; }
    .renewal-empty { padding:34px 18px; text-align:center; border:1px dashed var(--line); border-radius:12px; background:#fcfcfd; }
    .renewal-empty h3 { margin:0 0 7px; color:var(--maroon); font-size:1.08rem; }
    .renewal-empty p { margin:0; color:var(--muted); }
    .renewal-note { margin:0; padding:14px 17px; border-left:4px solid #9b6a7b; border-radius:6px; background:#faf7f8; color:var(--muted); font-size:.86rem; }
    @media(max-width:1100px){.renewal-overview{grid-template-columns:repeat(3,minmax(0,1fr));}}
    @media(max-width:850px){.renewal-filters{grid-template-columns:repeat(2,minmax(0,1fr));}.renewal-search{grid-column:auto;}}
    @media(max-width:600px){.renewal-hero{padding:22px 18px;}.renewal-overview{grid-template-columns:repeat(2,minmax(0,1fr));}.renewal-filters{grid-template-columns:1fr;}.renewal-records{padding:14px;}.renewal-records-head{align-items:flex-start;flex-direction:column;}}
</style>

<section class="form-card form-card-wide renewal-page">
    <div class="renewal-hero">
        <h1>Scholarship Renewal Report</h1>
        <p>Track academic performance, behavior, program participation, and renewal decisions for approved scholars.</p>
    </div>

    <?php if (!empty($error)): ?><p class="error-message"><?= e($error) ?></p><?php endif; ?>

    <div>
        <div class="renewal-section-title"><h2>Renewal Overview</h2><span class="muted">Latest monitoring records</span></div>
        <div class="renewal-overview">
            <div class="renewal-stat"><span>Total Approved Scholars</span><strong><?= number_format((int) ($summary['total'] ?? 0)) ?></strong><small>Approved scholarship applications</small></div>
            <div class="renewal-stat eligible"><span>Eligible for Renewal</span><strong><?= number_format((int) ($summary['eligible'] ?? 0)) ?></strong><small>Qualified for next school year</small></div>
            <div class="renewal-stat ineligible"><span>Not Eligible</span><strong><?= number_format((int) ($summary['not_eligible'] ?? 0)) ?></strong><small>Does not meet renewal requirements</small></div>
            <div class="renewal-stat pending"><span>Pending Evaluation</span><strong><?= number_format((int) ($summary['pending'] ?? 0)) ?></strong><small>Awaiting coordinator evaluation</small></div>
            <div class="renewal-stat removed"><span>Removed from Program</span><strong><?= number_format((int) ($summary['removed'] ?? 0)) ?></strong><small>Moved to regular program</small></div>
        </div>
    </div>

    <div>
        <div class="renewal-section-title"><h2>Find Renewal Records</h2><span class="muted">Use filters to narrow the list</span></div>
        <form method="get" class="table-search-form">
        <div class="table-search-toolbar">
            <div class="table-search-field">
                <label class="renewal-search">Search Scholar
                                <input type="search" data-student-suggestions="scholars" data-suggestion-url="<?= BASE_URL ?>/includes/student_suggestions.php" name="search" value="<?= e($search) ?>" placeholder="Enter student name or LRN">
                            </label>
            </div>
            <button type="submit" class="button button-primary">Search</button>
            <details class="table-search-disclosure">
                <summary class="button button-outline">
                    Filters<?= ($schoolYear || $program || $grade || $section || $status !== '' || $participation !== '') ? ' (active)' : '' ?>
                </summary>
                <div class="table-search-panel">
                    <h3 class="table-search-panel-title">Filter results</h3>
                    <div class="table-search-fields">
                        <label>School Year
                            <select name="school_year"><option value="">All School Years</option>
                                <?php foreach ($schoolYears as $year): ?><option value="<?= (int) $year['school_year_id'] ?>" <?= $schoolYear === (int) $year['school_year_id'] ? 'selected' : '' ?>><?= e($year['school_year']) ?></option><?php endforeach; ?>
                            </select>
                        </label>
                        <label>Special Program
                            <select name="program"><option value="">All Programs</option>
                                <?php foreach ($programs as $item): ?><option value="<?= (int) $item['program_id'] ?>" <?= $program === (int) $item['program_id'] ? 'selected' : '' ?>><?= e($item['program_name']) ?></option><?php endforeach; ?>
                            </select>
                        </label>
                        <label>Grade Level
                            <select name="grade"><option value="">All Grades</option>
                                <?php foreach ($grades as $item): ?><option value="<?= (int) $item['grade_level_id'] ?>" <?= $grade === (int) $item['grade_level_id'] ? 'selected' : '' ?>>Grade <?= e($item['grade_level']) ?></option><?php endforeach; ?>
                            </select>
                        </label>
                        <label>Section
                            <select name="section"><option value="">All Sections</option>
                                <?php foreach ($sections as $item): ?><option value="<?= (int) $item['section_id'] ?>" <?= $section === (int) $item['section_id'] ? 'selected' : '' ?>><?= e($item['section_name']) ?></option><?php endforeach; ?>
                            </select>
                        </label>
                        <label>Renewal Eligibility
                            <select name="status"><option value="">All Statuses</option>
                                <?php foreach (['Eligible', 'Not Eligible', 'Pending', 'Not Monitored'] as $item): ?><option value="<?= e($item) ?>" <?= $status === $item ? 'selected' : '' ?>><?= e($item) ?></option><?php endforeach; ?>
                            </select>
                        </label>
                        <label>Participation Status
                            <select name="participation"><option value="">All Participation</option>
                                <?php foreach (['Qualified', 'Currently Enrolled', 'Removed'] as $item): ?><option value="<?= e($item) ?>" <?= $participation === $item ? 'selected' : '' ?>><?= e($item) ?></option><?php endforeach; ?>
                            </select>
                        </label>
                    </div>
                    <div class="table-search-actions">
                        <button type="submit" class="button button-primary">Apply filters</button>
                        <a class="button button-outline" href="<?= BASE_URL ?>/scholarship_coordinator/scholarship_renewal_report.php">Clear filters</a>
                    </div>
                </div>
            </details>
        </div>
    </form>
    </div>

    <div class="renewal-records">
        <div class="renewal-records-head">
            <div><h2>Monitoring Records</h2><p><?= number_format(count($records)) ?> approved <?= count($records) === 1 ? 'scholar' : 'scholars' ?> found</p></div>
            <span class="renewal-approved-tag">Approved Applications Only</span>
        </div>
        <?php if (!$records): ?>
            <div class="renewal-empty">
                <h3><?= (!$schoolYear && !$program && !$grade && !$section && $status === '' && $participation === '' && $search === '') ? 'No approved scholars yet' : 'No matching renewal records' ?></h3>
                <p><?= (!$schoolYear && !$program && !$grade && !$section && $status === '' && $participation === '' && $search === '') ? 'Approved scholarship applications will appear here after the Scholarship Coordinator approves them.' : 'Try adjusting your filters or clearing them to view all approved scholars.' ?></p>
            </div>
        <?php else: ?>
            <div class="renewal-table-wrap">
                <table class="renewal-table">
                    <thead><tr><th>Student</th><th>Program</th><th>Grade Level</th><th>Section</th><th>Academic Performance</th><th>Behavior Remarks</th><th>Participation</th><th>Renewal Eligibility</th><th>Last Monitoring</th></tr></thead>
                    <tbody>
                    <?php foreach ($records as $record): ?>
                        <?php
                            $fullName = trim($record['first_name'] . ' ' . ($record['middle_name'] ?? '') . ' ' . $record['last_name']);
                            $renewalStatus = $record['renewal_eligibility'] ?: 'Not Monitored';
                            $statusClass = match ($renewalStatus) {
                                'Eligible' => 'eligible',
                                'Not Eligible' => 'not-eligible',
                                'Pending' => 'pending',
                                default => 'unmonitored',
                            };
                            if (($record['participation_status'] ?? '') === 'Removed') { $statusClass = 'removed'; }
                        ?>
                        <tr>
                            <td><span class="renewal-student-name"><?= e($fullName) ?></span><span class="renewal-subtext">LRN: <?= e($record['lrn'] ?: 'Not recorded') ?></span></td>
                            <td><span class="renewal-program"><?= e($record['program_name']) ?></span></td>
                            <td><?= $record['grade_level'] !== null ? 'Grade ' . e($record['grade_level']) : 'Not assigned' ?></td>
                            <td><?= e($record['section_name'] ?: 'Not assigned') ?></td>
                            <td><?= e($record['academic_performance'] ?: '—') ?></td>
                            <td><?= e($record['behavior_remarks'] ?: '—') ?></td>
                            <td><?= e($record['participation_status'] ?: 'Not Monitored') ?></td>
                            <td><span class="renewal-status <?= e($statusClass) ?>"><?= e($renewalStatus) ?></span></td>
                            <td><?= e($record['monitoring_date'] ?: '—') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <p class="renewal-note">Academic performance is considered for the following school year's renewal evaluation. Serious behavior violations may result in removal during the current school year. Removal from a special program and renewal eligibility are recorded separately.</p>
</section>
<script src="<?= BASE_URL ?>/assets/js/student_suggestions.js" defer></script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
