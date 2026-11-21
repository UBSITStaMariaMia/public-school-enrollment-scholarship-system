
<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('System Administrator', 'School Registrar');

$pageTitle = 'Student Population Report';

function count_rows($pdo, $sql, $params)
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch();

    return $row['total'];
}

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

$gradeStmt = $pdo->prepare(
    'SELECT grade_level_id, grade_level
     FROM grade_levels
     ORDER BY grade_level_id ASC'
);
$gradeStmt->execute();
$gradeLevels = $gradeStmt->fetchAll();

$gradeRows = array();
$totalEnrolled = 0;
$totalNoSection = 0;
$totalCurrent = 0;


foreach ($gradeLevels as $grade) {
    $gradeId = $grade['grade_level_id'];

    $enrolled = count_rows(
        $pdo,
        "SELECT COUNT(*) AS total
         FROM enrollment_applications
         WHERE school_year_id = :school_year_id
           AND requested_grade_level_id = :grade_level_id
           AND application_status = 'Approved'",
        array(
            'school_year_id' => $selectedYearId,
            'grade_level_id' => $gradeId
        )
    );

    $noSection = count_rows(
        $pdo,
        "SELECT COUNT(*) AS total
         FROM enrollment_applications
         WHERE school_year_id = :school_year_id
           AND requested_grade_level_id = :grade_level_id
           AND application_status = 'Approved'
           AND assigned_section_id IS NULL",
        array(
            'school_year_id' => $selectedYearId,
            'grade_level_id' => $gradeId
        )
    );

    $current = count_rows(
        $pdo,
        'SELECT COUNT(*) AS total
         FROM students
         WHERE current_grade_level_id = :grade_level_id',
        array(
            'grade_level_id' => $gradeId
        )
    );

    $gradeRows[count($gradeRows)] = array(
        'grade_level' => $grade['grade_level'],
        'enrolled' => $enrolled,
        'no_section' => $noSection,
        'current' => $current
    );

    $totalEnrolled += $enrolled;
    $totalNoSection += $noSection;
    $totalCurrent += $current;
}

$sectionStmt = $pdo->prepare(
    'SELECT s.section_id, s.section_name, g.grade_level
     FROM sections s
     INNER JOIN grade_levels g
        ON s.grade_level_id = g.grade_level_id
     WHERE s.school_year_id = :school_year_id
     ORDER BY g.grade_level_id ASC, s.section_name ASC'
);

$sectionStmt->execute(
    array(
        'school_year_id' => $selectedYearId
    )
);

$sections = $sectionStmt->fetchAll();

$sectionRows = array();
$totalSectionStudents = 0;

foreach ($sections as $section) {
    $enrolled = count_rows(
        $pdo,
        "SELECT COUNT(*) AS total
         FROM enrollment_applications
         WHERE school_year_id = :school_year_id
           AND assigned_section_id = :section_id
           AND application_status = 'Approved'",
        array(
            'school_year_id' => $selectedYearId,
            'section_id' => $section['section_id']
        )
    );

    $sectionRows[count($sectionRows)] = array(
        'grade_level' => $section['grade_level'],
        'section_name' => $section['section_name'],
        'enrolled' => $enrolled
    );

    $totalSectionStudents += $enrolled;
}

require_once __DIR__ . '/../includes/header.php';
?>

<section class="form-card form-card-wide">

    <h1>Student Population Report</h1>

    <p class="muted">
        Student population summaries by grade level, section, and school year.
        Enrolled students are counted from applications with an Approved status.
    </p>

    <form method="get" class="form-grid">

        <label>
            School Year

            <select name="school_year_id">

                <?php foreach ($yearList as $year): ?>

                    <option value="<?php echo e($year['school_year_id']); ?>"
                        <?php
                        if ($year['school_year_id'] === $selectedYearId) {
                            echo 'selected';
                        }
                        ?>>

                        <?php echo e($year['school_year']); ?>
                        (<?php echo e($year['status']); ?>)

                    </option>

                <?php endforeach; ?>

            </select>
        </label>

        <div class="form-actions">
            <button type="submit" class="button button-primary">
                View Report
            </button>
        </div>

    </form>

    <h2>
        1. Enrolled Students by Grade Level
        (<?php echo e($selectedYearName); ?>)
    </h2>

    <div class="table-responsive">

        <table>
            <thead>
                <tr>
                    <th>Grade Level</th>
                    <th>Enrolled Students</th>
                    <th>Not Yet Assigned to a Section</th>
                </tr>
            </thead>

            <tbody>

                <?php foreach ($gradeRows as $row): ?>

                    <tr>
                        <td>
                            Grade <?php echo e($row['grade_level']); ?>
                        </td>

                        <td>
                            <?php echo $row['enrolled']; ?>
                        </td>

                        <td>
                            <?php echo $row['no_section']; ?>
                        </td>
                    </tr>

                <?php endforeach; ?>

                <tr>
                    <td><strong>Total</strong></td>
                    <td><strong><?php echo $totalEnrolled; ?></strong></td>
                    <td><strong><?php echo $totalNoSection; ?></strong></td>
                </tr>

            </tbody>
        </table>

    </div>

    <h2>2. Enrolled Students by Section</h2>

    <div class="table-responsive">

        <table>
            <thead>
                <tr>
                    <th>Grade Level</th>
                    <th>Section</th>
                    <th>Enrolled Students</th>
                </tr>
            </thead>

            <tbody>

                <?php foreach ($sectionRows as $row): ?>

                    <tr>
                        <td>
                            Grade <?php echo e($row['grade_level']); ?>
                        </td>

                        <td>
                            <?php echo e($row['section_name']); ?>
                        </td>

                        <td>
                            <?php echo $row['enrolled']; ?>
                        </td>
                    </tr>

                <?php endforeach; ?>

                <?php if (count($sectionRows) === 0): ?>

                    <tr>
                        <td colspan="3">
                            No sections found for this school year.
                        </td>
                    </tr>

                <?php else: ?>

                    <tr>
                        <td colspan="2"><strong>Total</strong></td>
                        <td>
                            <strong><?php echo $totalSectionStudents; ?></strong>
                        </td>
                    </tr>

                <?php endif; ?>

            </tbody>
        </table>

    </div>

    <h2>3. Current Student Records by Grade Level</h2>

    <p class="muted">
        Based on each student's current grade level in the student records.
    </p>

    <div class="table-responsive">

        <table>
            <thead>
                <tr>
                    <th>Grade Level</th>
                    <th>Students</th>
                </tr>
            </thead>

            <tbody>

                <?php foreach ($gradeRows as $row): ?>

                    <tr>
                        <td>
                            Grade <?php echo e($row['grade_level']); ?>
                        </td>

                        <td>
                            <?php echo $row['current']; ?>
                        </td>
                    </tr>

                <?php endforeach; ?>

                <tr>
                    <td><strong>Total</strong></td>
                    <td><strong><?php echo $totalCurrent; ?></strong></td>
                </tr>

            </tbody>
        </table>

    </div>

</section>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>
