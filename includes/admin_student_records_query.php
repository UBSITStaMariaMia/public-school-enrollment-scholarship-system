<?php
$search = is_string($_GET['search'] ?? null) ? trim($_GET['search']) : '';
$searchBy = $_GET['search_by'] ?? 'name';

$allowedSearch = ['name', 'lrn', 'username', 'section', 'grade'];

if (!in_array($searchBy, $allowedSearch, true)) {
    $searchBy = 'name';
}

$gradeFilter = in_array($_GET['grade'] ?? '', ['7', '8', '9', '10'], true) ? $_GET['grade'] : '';

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
        ) scholarship ON scholarship.student_id = s.student_id WHERE 1 = 1";

$params = [];

if ($search !== '') {
    $term = '%' . $search . '%';

    if ($searchBy === 'lrn') {
        $sql .= " AND s.lrn LIKE :search";
        $params = ['search' => $term];
    } elseif ($searchBy === 'username') {
        $sql .= " AND u.username LIKE :search";
        $params = ['search' => $term];
    } elseif ($searchBy === 'name') {
        $sql .= " AND CONCAT_WS(' ',
                    s.first_name,
                    NULLIF(s.middle_name, ''),
                    s.last_name
                ) LIKE :search";
        $params = ['search' => $term];
    } elseif ($searchBy === 'section') {
        $sql .= " AND sec.section_name = :search";
        $params = ['search' => $search];
    } elseif ($searchBy === 'grade') {
        $sql .= " AND g.grade_level = :search";
        $params = ['search' => $search];
    }
}

if ($gradeFilter !== '') {
    $sql .= ' AND g.grade_level = :grade_filter';
    $params['grade_filter'] = $gradeFilter;
}

$sql .= " ORDER BY s.last_name ASC, s.first_name ASC";

$query = $pdo->prepare($sql);
$query->execute($params);
$students = $query->fetchAll(PDO::FETCH_ASSOC);
$recordFilters = ['search' => $search, 'search_by' => $searchBy, 'grade' => $gradeFilter];
