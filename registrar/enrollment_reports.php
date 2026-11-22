
<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('School Registrar');
$pageTitle = 'Enrollment Reports';


function count_rows($pdo, $sql, $params)
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch();

    return $row['total'];
}


$applicationStatuses = ['Pending Review', 'Incomplete Requirements', 'Approved', 'Rejected'];
$enrollmentTypes = ['Incoming Grade 7', 'Returning', 'Transferee'];

$yearStmt = $pdo->prepare(
    'SELECT school_year_id, school_year, status
     FROM school_years
     ORDER BY school_year_id DESC'
);
$yearStmt->execute();
$yearList = $yearStmt->fetchAll();

if (count($yearList) === 0) {
    exit('No school year records found.');
}

$selectedYearId = $yearList[0]['school_year_id'];

foreach ($yearList as $year) {
    if ($year['status'] === 'Active') {
        $selectedYearId = $year['school_year_id'];
        break;
    }
}

if (isset($_GET['school_year_id'])) {
    foreach ($yearList as $year) {
        if ($year['school_year_id'] == $_GET['school_year_id']) {
            $selectedYearId = $year['school_year_id'];
            break;
        }
    }
}

$selectedYearName = '';

foreach ($yearList as $year) {
    if ($year['school_year_id'] === $selectedYearId) {
        $selectedYearName = $year['school_year'];
    }
}


$statusCounts = [];
$totalApplications = 0;

foreach ($applicationStatuses as $status) {
    $total = count_rows(
        $pdo,
        'SELECT COUNT(*) AS total
         FROM enrollment_applications
         WHERE school_year_id = :school_year_id
           AND application_status = :status',
        ['school_year_id' => $selectedYearId, 'status' => $status]
    );

    $statusCounts[$status] = $total;
    $totalApplications += $total;
}


$typeCounts = [];

foreach ($enrollmentTypes as $type) {
    $typeCounts[$type] = count_rows(
        $pdo,
        'SELECT COUNT(*) AS total
         FROM enrollment_applications
         WHERE school_year_id = :school_year_id
           AND enrollment_type = :type',
        ['school_year_id' => $selectedYearId, 'type' => $type]
    );
}

$gradeStmt = $pdo->prepare(
    'SELECT grade_level_id, grade_level
     FROM grade_levels
     ORDER BY grade_level_id ASC'
);
$gradeStmt->execute();
$gradeLevels = $gradeStmt->fetchAll();

$gradeCounts = [];
$gradeTotals = [];

foreach ($gradeLevels as $grade) {
    $gradeId = $grade['grade_level_id'];
    $gradeTotals[$gradeId] = 0;

    foreach ($applicationStatuses as $status) {
        $total = count_rows(
            $pdo,
            'SELECT COUNT(*) AS total
             FROM enrollment_applications
             WHERE school_year_id = :school_year_id
               AND requested_grade_level_id = :grade_level_id
               AND application_status = :status',
            [
                'school_year_id' => $selectedYearId,
                'grade_level_id' => $gradeId,
                'status' => $status
            ]
        );

        $gradeCounts[$gradeId][$status] = $total;
        $gradeTotals[$gradeId] += $total;
    }
}

require_once __DIR__ . '/../includes/header.php';
?>
<section class="form-card form-card-wide">
    <h1>Enrollment Reports</h1>
    <p class="muted">Enrollment statistics and enrollment-status summaries for the selected school year.</p>

    <form method="get" class="form-grid">
        <label>
            School Year
            <select name="school_year_id">
                <?php foreach ($yearList as $year): ?>
                    <option value="<?= e($year['school_year_id']) ?>"
                        <?= $year['school_year_id'] === $selectedYearId ? 'selected' : '' ?>>
                        <?= e($year['school_year']) ?> (<?= e($year['status']) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </label>

        <div class="form-actions">
            <button type="submit" class="button button-primary">View Report</button>
        </div>
    </form>

    <h2>1. Applications by Status (<?= e($selectedYearName) ?>)</h2>
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Application Status</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($applicationStatuses as $status): ?>
                    <?php $total = $statusCounts[$status]; ?>
                    <tr>
                        <td><?= e($status) ?></td>
                        <td><?= e($total) ?></td>
                    </tr>
                <?php endforeach; ?>
                <tr>
                    <td><strong>Total Applications</strong></td>
                    <td><strong><?= $totalApplications ?></strong></td>
                </tr>
            </tbody>
        </table>
    </div>

    <h2>2. Applications by Enrollment Type</h2>
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Enrollment Type</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($enrollmentTypes as $type): ?>
                    <?php $total = $typeCounts[$type]; ?>
                    <tr>
                        <td><?= e($type) ?></td>
                        <td><?= e($total) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

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
                </tr>
            </thead>
            <tbody>
                <?php foreach ($gradeLevels as $grade): ?>
                    <?php $gradeId = $grade['grade_level_id']; ?>
                    <tr>
                        <td>Grade <?= e($grade['grade_level']) ?></td>
                        <?php foreach ($applicationStatuses as $status): ?>
                            <td><?= $gradeCounts[$gradeId][$status] ?></td>
                        <?php endforeach; ?>
                        <td><strong><?= $gradeTotals[$gradeId] ?></strong></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
