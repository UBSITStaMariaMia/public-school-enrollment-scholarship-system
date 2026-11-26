<?php
require_once __DIR__ . '/../includes/bootstrap.php';

require_role('System Administrator');

$search = trim($_GET['search'] ?? '');
$searchBy = $_GET['search_by'] ?? 'name';

$allowedSearch = ['name', 'lrn', 'username', 'section', 'grade'];

if (!in_array($searchBy, $allowedSearch, true)) {
    $searchBy = 'name';
}

$sql = "SELECT
            s.student_id,
            s.lrn,
            s.first_name,
            s.middle_name,
            s.last_name,
            u.username,
            u.account_status,
            g.grade_level,
            sec.section_name,
            scholarship.scholarship_info
        FROM students s
        INNER JOIN users u ON u.user_id = s.user_id
        LEFT JOIN grade_levels g
            ON g.grade_level_id = s.current_grade_level_id
        LEFT JOIN sections sec
            ON sec.section_id = s.current_section_id
        LEFT JOIN (
            SELECT
                sa.student_id,
                GROUP_CONCAT(
                    CONCAT(sp.program_name, ' - ', sa.application_status)
                    SEPARATOR ', '
                ) AS scholarship_info
            FROM scholarship_applications sa
            INNER JOIN special_programs sp
                ON sp.program_id = sa.program_id
            GROUP BY sa.student_id
        ) scholarship ON scholarship.student_id = s.student_id";

$params = [];

if ($search !== '') {
    $term = '%' . $search . '%';

    if ($searchBy === 'lrn') {
        $sql .= " WHERE s.lrn LIKE :search";
        $params = ['search' => $term];
    } elseif ($searchBy === 'username') {
        $sql .= " WHERE u.username LIKE :search";
        $params = ['search' => $term];
    } elseif ($searchBy === 'name') {
        $sql .= " WHERE CONCAT_WS(' ',
                    s.first_name,
                    NULLIF(s.middle_name, ''),
                    s.last_name
                ) LIKE :search";
        $params = ['search' => $term];
    } elseif ($searchBy === 'section') {
        $sql .= " WHERE sec.section_name = :search";
        $params = ['search' => $search];
    } elseif ($searchBy === 'grade') {
        $sql .= " WHERE g.grade_level = :search";
        $params = ['search' => $search];
    }
}

$sql .= " ORDER BY s.last_name ASC, s.first_name ASC";

$query = $pdo->prepare($sql);
$query->execute($params);
$students = $query->fetchAll(PDO::FETCH_ASSOC);

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

    <style>
        body {
            margin: 0;
            padding: 30px;
            background: #f3f4f6;
            color: #222;
            font-family: Arial, Helvetica, sans-serif;
        }

        .report-page {
            max-width: 1100px;
            margin: 0 auto;
            padding: 38px;
            background: #fff;
            border: 1px solid #ddd;
        }

        .report-header {
            padding-bottom: 20px;
            text-align: center;
            border-bottom: 3px solid #64152f;
        }

        .school-name {
            margin: 0;
            color: #64152f;
            font-size: 23px;
            font-weight: bold;
            text-transform: uppercase;
        }

        .school-subtitle {
            margin: 5px 0 0;
            font-size: 12px;
            letter-spacing: 1px;
        }

        .report-title {
            margin: 25px 0 15px;
            color: #333;
            font-size: 20px;
            text-align: center;
            text-transform: uppercase;
        }

        .report-details {
            width: 100%;
            margin: 18px 0;
            border-collapse: collapse;
        }

        .report-details td {
            padding: 8px 5px;
            font-size: 12px;
            vertical-align: top;
        }

        .report-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .report-table th,
        .report-table td {
            padding: 10px 8px;
            border: 1px solid #cfcfcf;
            text-align: left;
            vertical-align: top;
            font-size: 11px;
            overflow-wrap: anywhere;
        }

        .report-table th {
            background: #f2e9ed;
            color: #4b1020;
            font-weight: bold;
        }

        .report-footer {
            width: 100%;
            margin-top: 25px;
            padding-top: 12px;
            border-top: 1px solid #ccc;
            color: #666;
            font-size: 10px;
        }

        .report-footer td {
            padding: 5px;
        }

        .report-actions {
            max-width: 1100px;
            margin: 0 auto 15px;
            text-align: right;
        }

        .report-actions button {
            padding: 10px 18px;
            border: 0;
            border-radius: 5px;
            background: #64152f;
            color: #fff;
            cursor: pointer;
        }

        @media print {
            @page {
                size: landscape;
                margin: 12mm;
            }

            body {
                padding: 0;
                background: #fff;
            }

            .report-page {
                max-width: none;
                padding: 0;
                border: 0;
            }

            .report-actions {
                display: none;
            }

            .report-table {
                page-break-inside: auto;
            }

            .report-table tr {
                page-break-inside: avoid;
                page-break-after: auto;
            }

            .report-table thead {
                display: table-header-group;
            }
        }
    </style>
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
                window.print();
            };
        </script>
    <?php endif; ?>

</body>
</html>
