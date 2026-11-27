
<?php
require_once __DIR__ . '/../includes/bootstrap.php';

require_role('System Administrator');

$search = trim($_GET['search'] ?? '');
$searchBy = $_GET['search_by'] ?? 'name';

$allowedSearch = ['name', 'id', 'role', 'position', 'username'];

if (!in_array($searchBy, $allowedSearch, true)) {
    $searchBy = 'name';
}

$sql = "SELECT
            u.user_id,
            u.username,
            u.email,
            u.account_status,
            r.role_name,
            sp.employee_id,
            sp.first_name,
            sp.middle_name,
            sp.last_name,
            sp.position_title,
            sp.department
        FROM users u
        INNER JOIN roles r ON r.role_id = u.role_id
        LEFT JOIN staff_profiles sp ON sp.user_id = u.user_id
        WHERE r.role_name IN (
            'School Registrar',
            'Teacher',
            'Enrollment Committee',
            'Scholarship Coordinator'
        )";

$params = [];

if ($search !== '') {

    $term = '%' . $search . '%';

    if ($searchBy === 'name') {

        $sql .= " AND CONCAT_WS(' ',
                    sp.first_name,
                    NULLIF(sp.middle_name, ''),
                    sp.last_name
                ) LIKE :search_name";

        $params = ['search_name' => $term];

    } elseif ($searchBy === 'id') {

        $sql .= " AND sp.employee_id LIKE :search_id";

        $params = ['search_id' => $term];

    } elseif ($searchBy === 'role') {

        $sql .= " AND r.role_name LIKE :search_role";

        $params = ['search_role' => $term];

    } elseif ($searchBy === 'position') {

        $sql .= " AND (
                    sp.position_title LIKE :search_position
                    OR sp.department LIKE :search_department
                )";

        $params = [
            'search_position' => $term,
            'search_department' => $term
        ];

    } elseif ($searchBy === 'username') {

        $sql .= " AND u.username LIKE :search_username";

        $params = ['search_username' => $term];
    }
}

$sql .= " ORDER BY r.role_name ASC, sp.last_name ASC, u.username ASC";

$query = $pdo->prepare($sql);
$query->execute($params);
$staffMembers = $query->fetchAll(PDO::FETCH_ASSOC);

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
