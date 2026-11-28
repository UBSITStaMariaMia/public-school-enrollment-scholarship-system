
<?php
require_once __DIR__ . '/../includes/bootstrap.php';

require_role('System Administrator');

require __DIR__ . '/../includes/admin_staff_records_query.php';

if (!$staffMembers) {
    http_response_code(404);
    exit('No school personnel records found for this report.');
}

$download = isset($_GET['download']);
$print = isset($_GET['print']);

$generatedDate = date('F d, Y h:i A');
$reportTitle = 'School Personnel Records';

if ($download) {

    header('Content-Type: application/msword; charset=utf-8');

    header('Content-Disposition: attachment; filename="school_personnel_records_' . date('Ymd_His') . '.doc"');

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
                OFFICIAL SCHOOL PERSONNEL RECORDS
            </p>

        </header>

        <h2 class="report-title">
            <?= e($reportTitle) ?>
        </h2>
        <?php if ($roleFilter !== '' || $gradeFilter !== ''): ?>
            <p><?= e(implode(' · ', array_filter([$roleFilter, $gradeFilter !== '' ? 'Teaching Grade ' . $gradeFilter : '']))) ?></p>
        <?php endif; ?>


        <table class="report-details">

            <tr>

                <td>
                    <strong>Records Included:</strong>
                    <?= count($staffMembers) ?>
                </td>

                <td>
                    <strong>Generated:</strong>
                    <?= e($generatedDate) ?>
                </td>

                <td>
                    <strong>Search:</strong>
                    <?= $search !== '' ? e($search) : 'All Personnel' ?>
                </td>

            </tr>

        </table>

        <table class="report-table">

            <thead>

                <tr>
                    <th>Employee ID</th>
                    <th>Name</th>
                    <th>Username</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Position / Department</th>
                    <th>Account Status</th>
                </tr>

            </thead>

            <tbody>

                <?php foreach ($staffMembers as $member): ?>

                    <?php

                    $fullName = trim(
                        ($member['first_name'] ?? '') . ' ' .
                        (!empty($member['middle_name']) ? $member['middle_name'] . ' ' : '') .
                        ($member['last_name'] ?? '')
                    );

                    ?>

                    <tr>

                        <td>
                            <?= e($member['employee_id'] ?? 'Not provided') ?>
                        </td>

                        <td>
                            <?= e($fullName !== '' ? $fullName : $member['username']) ?>
                        </td>

                        <td>
                            <?= e($member['username']) ?>
                        </td>

                        <td>
                            <?= e($member['email']) ?>
                        </td>

                        <td>
                            <?= e($member['role_name']) ?>
                        </td>

                        <td>

                            <?= e($member['position_title'] ?? '—') ?>

                            <?php if (!empty($member['department'])): ?>

                                <br>
                                <?= e($member['department']) ?>

                            <?php endif; ?>

                        </td>

                        <td>
                            <?= e($member['account_status']) ?>
                        </td>

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
                    School Personnel Records |
                    <?= count($staffMembers) ?> record(s)
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
