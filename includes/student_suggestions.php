<?php
require_once __DIR__ . '/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');

$scopes = [
    'registrar_records' => ['School Registrar'],
    'enrollment' => ['School Registrar'],
    'admin_records' => ['System Administrator'],
    'scholars' => ['System Administrator', 'Scholarship Coordinator'],
    'stipends' => ['System Administrator', 'Scholarship Coordinator']
];
$scope = $_GET['scope'] ?? null;
$role = current_user()['role_name'] ?? '';
if (!is_string($scope) || !isset($scopes[$scope]) || !in_array($role, $scopes[$scope], true)) {
    http_response_code(403);
    echo '[]';
    exit;
}

$term = is_string($_GET['q'] ?? null) ? mb_substr(trim($_GET['q']), 0, 100) : '';
if (mb_strlen($term) < 2) {
    echo '[]';
    exit;
}

$field = $_GET['field'] ?? 'name';
$fullNameSql = "CONCAT_WS(' ', s.first_name, NULLIF(s.middle_name, ''), s.last_name)";
$shortNameSql = "CONCAT_WS(' ', s.first_name, s.last_name)";
$searchMode = $scope === 'admin_records' && in_array($field, ['lrn', 'username'], true) ? $field : 'name';
$matchColumn = match ($searchMode) {
    'lrn' => 's.lrn',
    'username' => 'u.username',
    default => $fullNameSql
};
$searchTerm = '%' . strtr($term, ['!' => '!!', '%' => '!%', '_' => '!_']) . '%';
$conditions = ["$matchColumn LIKE :term ESCAPE '!'"];
$params = ['term' => $searchTerm];

if ($searchMode === 'name') {
    $conditions = ["($fullNameSql LIKE :term ESCAPE '!'
                    OR $shortNameSql LIKE :short_name ESCAPE '!'
                    OR s.lrn LIKE :lrn ESCAPE '!')"];
    $params += ['short_name' => $searchTerm, 'lrn' => $searchTerm];
    if ($scope === 'admin_records') {
        $conditions = ["($fullNameSql LIKE :term ESCAPE '!' OR $shortNameSql LIKE :short_name ESCAPE '!')"];
        unset($params['lrn']);
    }
}

if ($scope === 'enrollment') {
    $enrollmentStatus = $_GET['status'] ?? null;
    $enrollmentScope = 'ea.student_id = s.student_id';
    if (in_array($enrollmentStatus, ['Pending Review', 'Incomplete Requirements', 'Approved', 'Rejected'], true)) {
        $enrollmentScope .= ' AND ea.application_status = :status';
        $params['status'] = $enrollmentStatus;
    }
    $conditions[] = 'EXISTS (SELECT 1 FROM enrollment_applications ea WHERE ' . $enrollmentScope . ')';
} elseif ($scope === 'scholars') {
    $conditions[] = "EXISTS (SELECT 1 FROM scholarship_applications sa
                             WHERE sa.student_id = s.student_id AND sa.application_status = 'Approved')";
} elseif ($scope === 'stipends') {
    $conditions[] = 'EXISTS (SELECT 1 FROM scholarship_applications sa
                            JOIN stipend_records sr ON sr.scholarship_application_id = sa.scholarship_application_id
                            WHERE sa.student_id = s.student_id)';
} elseif ($scope === 'registrar_records') {
    $grade = $_GET['grade'] ?? '';
    if (in_array($grade, ['7', '8', '9', '10'], true)) {
        $conditions[] = 'EXISTS (SELECT 1 FROM grade_levels g WHERE g.grade_level_id = s.current_grade_level_id AND g.grade_level = :grade)';
        $params['grade'] = $grade;
    } elseif ($grade === 'unassigned') {
        $conditions[] = 's.current_grade_level_id IS NULL';
    }
    $section = filter_var($_GET['section'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($section) {
        $conditions[] = 's.current_section_id = :section';
        $params['section'] = $section;
    }
}

try {
    $where = implode(' AND ', $conditions);
    $query = $pdo->prepare(
        "SELECT $fullNameSql AS student_name, s.lrn, u.username
         FROM students s JOIN users u ON u.user_id = s.user_id
         WHERE $where
         ORDER BY s.last_name, s.first_name, s.student_id LIMIT 8"
    );
    $query->execute($params);
    $suggestions = [];
    foreach ($query->fetchAll() as $student) {
        $value = match ($searchMode) {
            'username' => $student['username'],
            'lrn' => $student['lrn'],
            default => ctype_digit($term) ? $student['lrn'] : $student['student_name']
        };
        if ($value) {
            $suggestions[$value] = ['value' => $value, 'label' => $student['student_name']];
        }
    }
    echo json_encode(array_values($suggestions), JSON_INVALID_UTF8_SUBSTITUTE);
} catch (PDOException $exception) {
    error_log('Student suggestions: ' . $exception->getMessage());
    http_response_code(500);
    echo '[]';
}
