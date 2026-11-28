<?php
$search = is_string($_GET['search'] ?? null) ? trim($_GET['search']) : '';
$searchBy = $_GET['search_by'] ?? 'name';

$allowedSearch = ['name', 'id', 'role', 'position', 'username'];

if (!in_array($searchBy, $allowedSearch, true)) {
    $searchBy = 'name';
}

$gradeFilter = in_array($_GET['grade'] ?? '', ['7', '8', '9', '10'], true) ? $_GET['grade'] : '';
$staffRoles = ['Teacher', 'School Registrar', 'Scholarship Coordinator', 'Enrollment Committee'];
$roleFilter = $gradeFilter !== '' ? 'Teacher' : (in_array($_GET['role'] ?? '', $staffRoles, true) ? $_GET['role'] : '');

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

if ($roleFilter !== '') {
    $sql .= ' AND r.role_name = :role_filter';
    $params['role_filter'] = $roleFilter;
}

if ($gradeFilter !== '') {
    $sql .= " AND EXISTS (
        SELECT 1 FROM teacher_section_assignments a
        JOIN sections s ON s.section_id = a.section_id AND s.school_year_id = a.school_year_id
        JOIN school_years y ON y.school_year_id = a.school_year_id AND y.status = 'Active'
        JOIN grade_levels g ON g.grade_level_id = s.grade_level_id
        WHERE a.teacher_user_id = u.user_id AND g.grade_level = :grade_filter
    )";
    $params['grade_filter'] = $gradeFilter;
}

$sql .= " ORDER BY r.role_name ASC, sp.last_name ASC, u.username ASC";

$query = $pdo->prepare($sql);
$query->execute($params);
$staffMembers = $query->fetchAll(PDO::FETCH_ASSOC);
$recordFilters = ['search' => $search, 'search_by' => $searchBy, 'grade' => $gradeFilter];
$recordFilters['role'] = $roleFilter;
