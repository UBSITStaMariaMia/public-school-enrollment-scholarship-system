<?php
require_once __DIR__ . '/../includes/bootstrap.php';

require_role('School Registrar');

$pageTitle = 'Enrollment Reports';

function report_count_rows(PDO $pdo, string $sql, array $params = []): int
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return (int) ($row['total'] ?? 0);
}

$applicationStatuses = [
    'Pending Review',
    'Incomplete Requirements',
    'Approved',
    'Rejected'
];

$enrollmentTypes = [
    'Incoming Grade 7',
    'Returning',
    'Transferee'
];

// Load available school years.
$yearStmt = $pdo->query(
    'SELECT school_year_id, school_year, status
     FROM school_years
     ORDER BY school_year_id DESC'
);
$yearList = $yearStmt->fetchAll(PDO::FETCH_ASSOC);

if (!$yearList) {
    require_once __DIR__ . '/../includes/header.php';
    ?>
    <section class="form-card form-card-wide">
        <h1>Enrollment Reports</h1>
        <p class="muted">No school year records were found.</p>
    </section>
    <?php
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

// Default to the active school year, or the newest year if none is active.
$selectedYearId = (int) $yearList[0]['school_year_id'];

foreach ($yearList as $year) {
    if (strcasecmp((string) $year['status'], 'Active') === 0) {
        $selectedYearId = (int) $year['school_year_id'];
        break;
    }
}

// Accept only a school year that exists in the database.
if (isset($_GET['school_year_id'])) {
    $requestedYearId = filter_var(
        $_GET['school_year_id'],
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );

    if ($requestedYearId !== false) {
        foreach ($yearList as $year) {
            if ((int) $year['school_year_id'] === $requestedYearId) {
                $selectedYearId = $requestedYearId;
                break;
            }
        }
    }
}

$selectedYearName = '';
$selectedYearStatus = '';

foreach ($yearList as $year) {
    if ((int) $year['school_year_id'] === $selectedYearId) {
        $selectedYearName = (string) $year['school_year'];
        $selectedYearStatus = (string) $year['status'];
        break;
    }
}

// Fetch application totals in one query.
$statusStmt = $pdo->prepare(
    'SELECT application_status, COUNT(*) AS total
     FROM enrollment_applications
     WHERE school_year_id = :school_year_id
     GROUP BY application_status'
);
$statusStmt->execute(['school_year_id' => $selectedYearId]);
$statusRows = $statusStmt->fetchAll(PDO::FETCH_ASSOC);

$statusCounts = array_fill_keys($applicationStatuses, 0);
foreach ($statusRows as $row) {
    if (array_key_exists($row['application_status'], $statusCounts)) {
        $statusCounts[$row['application_status']] = (int) $row['total'];
    }
}
$totalApplications = array_sum($statusCounts);

// Fetch enrollment type totals in one query.
$typeStmt = $pdo->prepare(
    'SELECT enrollment_type, COUNT(*) AS total
     FROM enrollment_applications
     WHERE school_year_id = :school_year_id
     GROUP BY enrollment_type'
);
$typeStmt->execute(['school_year_id' => $selectedYearId]);
$typeRows = $typeStmt->fetchAll(PDO::FETCH_ASSOC);

$typeCounts = array_fill_keys($enrollmentTypes, 0);
foreach ($typeRows as $row) {
    if (array_key_exists($row['enrollment_type'], $typeCounts)) {
        $typeCounts[$row['enrollment_type']] = (int) $row['total'];
    }
}
$totalByType = array_sum($typeCounts);

// Load grade levels and aggregate application status counts by grade.
$gradeStmt = $pdo->query(
    'SELECT grade_level_id, grade_level
     FROM grade_levels
     ORDER BY grade_level_id ASC'
);
$gradeLevels = $gradeStmt->fetchAll(PDO::FETCH_ASSOC);

$gradeCounts = [];
$gradeTotals = [];

foreach ($gradeLevels as $grade) {
    $gradeId = (int) $grade['grade_level_id'];
    $gradeCounts[$gradeId] = array_fill_keys($applicationStatuses, 0);
    $gradeTotals[$gradeId] = 0;
}

$gradeSummaryStmt = $pdo->prepare(
    'SELECT requested_grade_level_id, application_status, COUNT(*) AS total
     FROM enrollment_applications
     WHERE school_year_id = :school_year_id
     GROUP BY requested_grade_level_id, application_status'
);
$gradeSummaryStmt->execute(['school_year_id' => $selectedYearId]);
$gradeSummaryRows = $gradeSummaryStmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($gradeSummaryRows as $row) {
    $gradeId = (int) $row['requested_grade_level_id'];
    $status = (string) $row['application_status'];
    $total = (int) $row['total'];

    if (isset($gradeCounts[$gradeId]) && array_key_exists($status, $gradeCounts[$gradeId])) {
        $gradeCounts[$gradeId][$status] = $total;
        $gradeTotals[$gradeId] += $total;
    }
}

// Prepare CSV export before the shared page header outputs HTML.
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $filenameYear = preg_replace('/[^A-Za-z0-9_-]/', '_', $selectedYearName);
    $filename = 'enrollment-report-' . ($filenameYear !== '' ? $filenameYear : $selectedYearId) . '.csv';

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $outputHandle = fopen('php://output', 'w');
    // UTF-8 BOM helps spreadsheet programs display the text correctly.
    fwrite($outputHandle, "\xEF\xBB\xBF");

    fputcsv($outputHandle, ['Enrollment Reports', $selectedYearName]);
    fputcsv($outputHandle, []);
    fputcsv($outputHandle, ['Applications by Status']);
    fputcsv($outputHandle, ['Application Status', 'Total', 'Percentage']);

    foreach ($applicationStatuses as $status) {
        $count = $statusCounts[$status];
        $percentage = $totalApplications > 0 ? round(($count / $totalApplications) * 100, 2) : 0;
        fputcsv($outputHandle, [$status, $count, $percentage . '%']);
    }
    fputcsv($outputHandle, ['Total Applications', $totalApplications, '100%']);
    fputcsv($outputHandle, []);

    fputcsv($outputHandle, ['Applications by Enrollment Type']);
    fputcsv($outputHandle, ['Enrollment Type', 'Total', 'Percentage']);

    foreach ($enrollmentTypes as $type) {
        $count = $typeCounts[$type];
        $percentage = $totalByType > 0 ? round(($count / $totalByType) * 100, 2) : 0;
        fputcsv($outputHandle, [$type, $count, $percentage . '%']);
    }
    fputcsv($outputHandle, ['Total by Enrollment Type', $totalByType, '100%']);
    fputcsv($outputHandle, []);

    fputcsv($outputHandle, ['Applications by Requested Grade Level']);
    fputcsv($outputHandle, array_merge(
        ['Grade Level'],
        $applicationStatuses,
        ['Total']
    ));

    foreach ($gradeLevels as $grade) {
        $gradeId = (int) $grade['grade_level_id'];
        $csvRow = ['Grade ' . $grade['grade_level']];

        foreach ($applicationStatuses as $status) {
            $csvRow[] = $gradeCounts[$gradeId][$status] ?? 0;
        }

        $csvRow[] = $gradeTotals[$gradeId] ?? 0;
        fputcsv($outputHandle, $csvRow);
    }

    fclose($outputHandle);
    exit;
}

$statusColors = [
    'Pending Review' => 'pending',
    'Incomplete Requirements' => 'incomplete',
    'Approved' => 'approved',
    'Rejected' => 'rejected'
];

require_once __DIR__ . '/../includes/header.php';
?>

<style>
    .report-intro {
        margin-bottom: 20px;
    }

    .report-year-form {
        display: grid;
        grid-template-columns: minmax(220px, 1fr) auto;
        align-items: end;
        gap: 12px;
        margin-bottom: 24px;
    }

    .report-year-form label {
        margin: 0;
    }

    .report-summary-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 14px;
        margin: 20px 0 28px;
    }

    .report-stat-card {
        min-width: 0;
        padding: 18px;
        border: 1px solid var(--border-color, #dce1e8);
        border-radius: 12px;
        background: var(--surface-muted, #f7f8fa);
    }

    .report-stat-card .stat-label {
        display: block;
        margin-bottom: 8px;
        color: var(--text-muted, #64748b);
        font-size: 0.82rem;
    }

    .report-stat-card .stat-value {
        display: block;
        font-size: 1.8rem;
        line-height: 1.2;
        font-weight: 700;
    }

    .report-stat-card.pending .stat-value { color: #a16207; }
    .report-stat-card.approved .stat-value { color: #15803d; }
    .report-stat-card.rejected .stat-value { color: #b91c1c; }

    .report-section {
        margin-top: 30px;
    }

    .report-section h2 {
        margin-bottom: 12px;
    }

    .report-chart-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 18px;
        margin-top: 22px;
    }

    .report-chart-card {
        min-width: 0;
        padding: 20px;
        border: 1px solid var(--border-color, #dce1e8);
        border-radius: 12px;
        background: #fff;
    }

    .report-chart-card h3 {
        margin: 0 0 18px;
        font-size: 1rem;
    }

    .report-bar-list {
        display: grid;
        gap: 15px;
    }

    .report-bar-item {
        display: grid;
        gap: 6px;
    }

    .report-bar-heading {
        display: flex;
        justify-content: space-between;
        gap: 12px;
        font-size: 0.88rem;
    }

    .report-bar-track {
        height: 10px;
        overflow: hidden;
        border-radius: 999px;
        background: #edf0f4;
    }

    .report-bar-fill {
        height: 100%;
        min-width: 0;
        border-radius: inherit;
        background: #6b1835;
    }

    .report-bar-fill.pending { background: #d69a26; }
    .report-bar-fill.incomplete { background: #64748b; }
    .report-bar-fill.approved { background: #23945a; }
    .report-bar-fill.rejected { background: #c24141; }

    .report-percent {
        color: var(--text-muted, #64748b);
        font-size: 0.78rem;
    }

    .report-empty {
        color: var(--text-muted, #64748b);
        font-size: 0.9rem;
    }

    .report-table-total td {
        font-weight: 700;
    }

    .report-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        margin-top: 28px;
        padding-top: 20px;
        border-top: 1px solid var(--border-color, #dce1e8);
    }

    .report-actions .button {
        text-decoration: none;
    }

    @media (max-width: 800px) {
        .report-summary-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .report-chart-grid {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 560px) {
        .report-year-form {
            grid-template-columns: 1fr;
        }

        .report-summary-grid {
            gap: 10px;
        }

        .report-stat-card {
            padding: 14px;
        }

        .report-stat-card .stat-value {
            font-size: 1.5rem;
        }
    }

    @media print {
        body {
            background: #fff !important;
        }

        .site-header,
        header,
        nav,
        footer,
        .report-year-form,
        .report-actions,
        .menu-toggle,
        .mobile-menu-toggle {
            display: none !important;
        }

        .form-card,
        .report-chart-card,
        .report-stat-card {
            box-shadow: none !important;
            break-inside: avoid;
        }

        .form-card {
            width: 100% !important;
            max-width: none !important;
            margin: 0 !important;
            padding: 0 !important;
            border: 0 !important;
        }

        .report-section,
        .report-chart-grid {
            break-inside: avoid;
        }

        table {
            font-size: 10pt;
        }
    }
</style>

<section class="form-card form-card-wide">
    <div class="report-intro">
        <h1>Enrollment Reports</h1>
        <p class="muted">
            Enrollment statistics and enrollment-status summaries for the selected school year.
        </p>
    </div>

    <form method="get" class="report-year-form">
        <label>
            School Year
            <select name="school_year_id">
                <?php foreach ($yearList as $year): ?>
                    <option value="<?= e($year['school_year_id']) ?>"
                        <?= (int) $year['school_year_id'] === $selectedYearId ? 'selected' : '' ?>>
                        <?= e($year['school_year']) ?> (<?= e($year['status']) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </label>

        <div class="form-actions">
            <button type="submit" class="button button-primary">View Report</button>
        </div>
    </form>

    <div class="report-summary-grid">
        <div class="report-stat-card">
            <span class="stat-label">Total Applications</span>
            <strong class="stat-value"><?= e($totalApplications) ?></strong>
        </div>

        <div class="report-stat-card pending">
            <span class="stat-label">Pending Review</span>
            <strong class="stat-value"><?= e($statusCounts['Pending Review']) ?></strong>
        </div>

        <div class="report-stat-card approved">
            <span class="stat-label">Approved</span>
            <strong class="stat-value"><?= e($statusCounts['Approved']) ?></strong>
        </div>

        <div class="report-stat-card rejected">
            <span class="stat-label">Rejected</span>
            <strong class="stat-value"><?= e($statusCounts['Rejected']) ?></strong>
        </div>
    </div>

    <div class="report-chart-grid">
        <div class="report-chart-card">
            <h3>Applications by Status</h3>

            <?php if ($totalApplications === 0): ?>
                <p class="report-empty">No applications found for this school year.</p>
            <?php else: ?>
                <div class="report-bar-list">
                    <?php foreach ($applicationStatuses as $status): ?>
                        <?php
                        $count = $statusCounts[$status];
                        $percentage = ($count / $totalApplications) * 100;
                        ?>
                        <div class="report-bar-item">
                            <div class="report-bar-heading">
                                <span><?= e($status) ?></span>
                                <strong><?= e($count) ?> <span class="report-percent">(<?= e(number_format($percentage, 1)) ?>%)</span></strong>
                            </div>
                            <div class="report-bar-track">
                                <div class="report-bar-fill <?= e($statusColors[$status]) ?>"
                                     style="width: <?= e(number_format($percentage, 2, '.', '')) ?>%"></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="report-chart-card">
            <h3>Applications by Grade Level</h3>

            <?php if ($totalApplications === 0): ?>
                <p class="report-empty">No applications found for this school year.</p>
            <?php else: ?>
                <div class="report-bar-list">
                    <?php foreach ($gradeLevels as $grade): ?>
                        <?php
                        $gradeId = (int) $grade['grade_level_id'];
                        $count = $gradeTotals[$gradeId] ?? 0;
                        $percentage = ($count / $totalApplications) * 100;
                        ?>
                        <div class="report-bar-item">
                            <div class="report-bar-heading">
                                <span>Grade <?= e($grade['grade_level']) ?></span>
                                <strong><?= e($count) ?> <span class="report-percent">(<?= e(number_format($percentage, 1)) ?>%)</span></strong>
                            </div>
                            <div class="report-bar-track">
                                <div class="report-bar-fill"
                                     style="width: <?= e(number_format($percentage, 2, '.', '')) ?>%"></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="report-section">
        <h2>1. Applications by Status (<?= e($selectedYearName) ?>)</h2>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Application Status</th>
                        <th>Total</th>
                        <th>Percentage</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($applicationStatuses as $status): ?>
                        <?php
                        $total = $statusCounts[$status];
                        $percentage = $totalApplications > 0
                            ? ($total / $totalApplications) * 100
                            : 0;
                        ?>
                        <tr>
                            <td><?= e($status) ?></td>
                            <td><?= e($total) ?></td>
                            <td><?= e(number_format($percentage, 1)) ?>%</td>
                        </tr>
                    <?php endforeach; ?>
                    <tr class="report-table-total">
                        <td>Total Applications</td>
                        <td><?= e($totalApplications) ?></td>
                        <td><?= $totalApplications > 0 ? '100%' : '0%' ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <div class="report-section">
        <h2>2. Applications by Enrollment Type</h2>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Enrollment Type</th>
                        <th>Total</th>
                        <th>Percentage</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($enrollmentTypes as $type): ?>
                        <?php
                        $total = $typeCounts[$type];
                        $percentage = $totalByType > 0
                            ? ($total / $totalByType) * 100
                            : 0;
                        ?>
                        <tr>
                            <td><?= e($type) ?></td>
                            <td><?= e($total) ?></td>
                            <td><?= e(number_format($percentage, 1)) ?>%</td>
                        </tr>
                    <?php endforeach; ?>
                    <tr class="report-table-total">
                        <td>Total Applications by Type</td>
                        <td><?= e($totalByType) ?></td>
                        <td><?= $totalByType > 0 ? '100%' : '0%' ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <div class="report-section">
        <h2>3. Applications by Requested Grade Level</h2>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Grade Level</th>
                        <?php foreach ($applicationStatuses as $status): ?>
                            <th><?= e($status) ?></th>
                        <?php endforeach; ?>
                        <th>Total</th>
                        <th>Share</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($gradeLevels as $grade): ?>
                        <?php
                        $gradeId = (int) $grade['grade_level_id'];
                        $gradeTotal = $gradeTotals[$gradeId] ?? 0;
                        $gradeShare = $totalApplications > 0
                            ? ($gradeTotal / $totalApplications) * 100
                            : 0;
                        ?>
                        <tr>
                            <td>Grade <?= e($grade['grade_level']) ?></td>
                            <?php foreach ($applicationStatuses as $status): ?>
                                <td><?= e($gradeCounts[$gradeId][$status] ?? 0) ?></td>
                            <?php endforeach; ?>
                            <td><strong><?= e($gradeTotal) ?></strong></td>
                            <td><?= e(number_format($gradeShare, 1)) ?>%</td>
                        </tr>
                    <?php endforeach; ?>

                    <?php if (!$gradeLevels): ?>
                        <tr>
                            <td colspan="7">No grade levels found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="report-actions">
        <a class="button button-primary"
           href="?school_year_id=<?= e($selectedYearId) ?>&amp;export=csv">
            Export to Excel (CSV)
        </a>

        <button type="button" class="button button-secondary" onclick="window.print()">
            Print / Save as PDF
        </button>
    </div>

    <p class="muted" style="margin-top: 14px;">
        Report school year: <?= e($selectedYearName) ?> (<?= e($selectedYearStatus) ?>).
        Exported and printed reports use the selected school year.
    </p>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
