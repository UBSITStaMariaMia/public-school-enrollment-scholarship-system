<?php
require_once __DIR__ . '/../includes/bootstrap.php';

require_role('System Administrator');

require __DIR__ . '/../includes/admin_student_records_query.php';

if (!$students) {
    http_response_code(404);
    exit('No student records found for this report.');
}

$download = isset($_GET['download']);
$print = isset($_GET['print']);

$generatedDate = date('F d, Y h:i A');
$reportTitle = 'Student Records';

if ($download) {
    header('Content-Type: application/msword; charset=utf-8');
    header('Content-Disposition: attachment; filename="student_records_' . date('Ymd_His') . '.doc"');
    header('Cache-Control: max-age=0');
    header('X-Content-Type-Options: nosniff');
} else {
    header('Content-Type: text/html; charset=utf-8');
}
?>

<!DOCTYPE html>
<html lang="en"
      xmlns:o="urn:schemas-microsoft-com:office:office"
      xmlns:w="urn:schemas-microsoft-com:office:word">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($reportTitle) ?></title>

    <?php $formalLandscape = true; ?>
    <?php require __DIR__ . '/../includes/formal_report_styles.php'; ?>
</head>

<body>

    <?php if (!$download): ?>
        <div class="report-actions">
            <button type="button" onclick="window.print()">
                Print Report
            </button>
        </div>
    <?php endif; ?>

    <main class="report-page">

        <header class="report-header">
            <h1 class="school-name">
                Baguio City National High School
            </h1>

            <p class="school-subtitle">
                OFFICIAL STUDENT RECORDS
            </p>
        </header>

        <h2 class="report-title">
            <?= e($reportTitle) ?>
        </h2>
        <?php if ($gradeFilter !== ''): ?>
            <p>Grade <?= e($gradeFilter) ?></p>
        <?php endif; ?>


        <table class="report-details">
            <tr>
                <td>
                    <strong>Records Included:</strong>
                    <?= count($students) ?>
                </td>

                <td>
                    <strong>Generated:</strong>
                    <?= e($generatedDate) ?>
                </td>

                <td>
                    <strong>Search:</strong>
                    <?= $search !== '' ? e($search) : 'All Students' ?>
                </td>
            </tr>
        </table>

        <table class="report-table">
            <thead>
                <tr>
                    <th>LRN</th>
                    <th>Student Name</th>
                    <th>Grade</th>
                    <th>Section</th>
                    <th>Username</th>
                    <th>Account Status</th>
                    <th>Scholarship</th>
                </tr>
            </thead>

            <tbody>
                <?php foreach ($students as $student): ?>
                    <?php
                    $fullName = trim(
                        ($student['first_name'] ?? '') . ' ' .
                        (!empty($student['middle_name']) ? $student['middle_name'] . ' ' : '') .
                        ($student['last_name'] ?? '')
                    );
                    ?>

                    <tr>
                        <td><?= e($student['lrn'] ?? '—') ?></td>
                        <td><?= e($fullName) ?></td>
                        <td><?= e($student['grade_level'] ?? 'Not assigned') ?></td>
                        <td><?= e($student['section_name'] ?? 'Not assigned') ?></td>
                        <td><?= e($student['username']) ?></td>
                        <td><?= e($student['account_status']) ?></td>
                        <td><?= e($student['scholarship_info'] ?? 'None') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <table class="report-footer">
            <tr>
                <td>
                    Baguio City National High School
                </td>

                <td align="right">
                    Student Records |
                    <?= count($students) ?> record(s)
                </td>
            </tr>
        </table>

    </main>

    <?php if ($print): ?>
        <script>
            window.onload = function () {
                if (window.parent === window) window.print();
            };
        </script>
    <?php endif; ?>

</body>
</html>
