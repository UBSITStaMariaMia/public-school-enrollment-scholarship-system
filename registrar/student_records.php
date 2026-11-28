<?php

require_once __DIR__ . '/../includes/bootstrap.php';

require_role('School Registrar');

$pageTitle = 'Student Records Management';
$schoolName = 'Baguio City National High School';

$user = current_user();

$isAdmin = true;

$error = '';

$gradingPeriods = [

    'First Quarter',

    'Second Quarter',

    'Third Quarter',

    'Fourth Quarter'

];

$activeSchoolYear = $pdo->query(

    "SELECT school_year_id, school_year

     FROM school_years

     WHERE status = 'Active'

     LIMIT 1"

)->fetch();

$selectedStudentId = filter_var(
    $_GET['student_id'] ?? null,
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);

$showStudentRecord = $selectedStudentId !== false;
$isAttendanceHistory = $showStudentRecord && ($_GET['view'] ?? '') === 'attendance';
$recordExport = $_GET['record_export'] ?? '';
$attendancePage = filter_var($_GET['attendance_page'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 1;
$attendancePageSize = 30;
if ($showStudentRecord) {
    $pageTitle = $isAttendanceHistory ? 'Attendance History' : 'Student Record';
}

$getAccessibleStudent = function (int $studentId) use (

    $pdo,

    $user,

    $isAdmin,

    $activeSchoolYear

): ?array {

    if (!$activeSchoolYear) {

        return null;

    }

    if ($isAdmin) {

        $query = $pdo->prepare(

            "SELECT

                s.student_id,

                s.lrn,

                s.first_name,

                s.middle_name,

                s.last_name,

                s.current_section_id,

                g.grade_level,

                sec.section_name

             FROM students s

             LEFT JOIN grade_levels g

                ON g.grade_level_id = s.current_grade_level_id

             LEFT JOIN sections sec

                ON sec.section_id = s.current_section_id

             WHERE s.student_id = :student_id

             LIMIT 1"

        );

        $query->execute([

            'student_id' => $studentId

        ]);

    } else {

        $query = $pdo->prepare(

            "SELECT

                s.student_id,

                s.lrn,

                s.first_name,

                s.middle_name,

                s.last_name,

                s.current_section_id,

                g.grade_level,

                sec.section_name

             FROM students s

             INNER JOIN sections sec

                ON sec.section_id = s.current_section_id

             INNER JOIN teacher_section_assignments tsa

                ON tsa.section_id = sec.section_id

             INNER JOIN grade_levels g

                ON g.grade_level_id = s.current_grade_level_id

             WHERE s.student_id = :student_id

             AND tsa.teacher_user_id = :teacher_user_id

             AND tsa.school_year_id = :school_year_id

             AND sec.school_year_id = :section_year_id

             LIMIT 1"

        );

        $query->execute([

            'student_id' => $studentId,

            'teacher_user_id' => $user['user_id'],

            'school_year_id' => $activeSchoolYear['school_year_id'],

            'section_year_id' => $activeSchoolYear['school_year_id']

        ]);

    }

    $student = $query->fetch();

    return $student ?: null;

};

$gradeLevels = [7, 8, 9, 10];
$gradeFilter = is_string($_GET['grade'] ?? null) ? $_GET['grade'] : '';
if (!in_array($gradeFilter, ['7', '8', '9', '10', 'unassigned'], true)) {
    $gradeFilter = '';
}
$sectionFilter = filter_var($_GET['section'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: '';
$search = is_string($_GET['q'] ?? null) ? mb_substr(trim($_GET['q']), 0, 150) : '';
$page = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 1;
$perPage = 10;
$totalPages = 1;
$filteredCount = 0;
$sections = [];
$students = [];

if ($activeSchoolYear) {
    $yearId = (int) $activeSchoolYear['school_year_id'];
    $sectionQuery = $pdo->prepare(
        'SELECT sec.section_id, sec.section_name, g.grade_level
         FROM sections sec
         JOIN grade_levels g ON g.grade_level_id = sec.grade_level_id
         WHERE sec.school_year_id = :school_year_id
         ORDER BY g.grade_level_id, sec.section_name'
    );
    $sectionQuery->execute(['school_year_id' => $yearId]);
    $sections = $sectionQuery->fetchAll();
    $sectionsById = array_column($sections, null, 'section_id');
    if ($sectionFilter !== '' && (
        !isset($sectionsById[$sectionFilter])
        || ($gradeFilter !== '' && $gradeFilter !== (string) $sectionsById[$sectionFilter]['grade_level'])
    )) {
        $sectionFilter = '';
    }

    if (!$showStudentRecord) {
        $where = [];
        $params = ['section_year' => $yearId];
        if ($gradeFilter === 'unassigned') {
            $where[] = 's.current_grade_level_id IS NULL';
        } elseif ($gradeFilter !== '') {
            $where[] = 'g.grade_level = :grade';
            $params['grade'] = $gradeFilter;
        }
        if ($sectionFilter !== '') {
            $where[] = 'sec.section_id = :section';
            $params['section'] = $sectionFilter;
        }
        if (preg_match('/^[0-9]{12}$/', $search)) {
            $where[] = 's.lrn = :lrn';
            $params['lrn'] = $search;
        } elseif ($search !== '') {
            $searchTerm = '%' . strtr($search, ['!' => '!!', '%' => '!%', '_' => '!_']) . '%';
            $where[] = "(
                CONCAT_WS(' ', s.first_name, NULLIF(s.middle_name, ''), s.last_name) LIKE :full_name ESCAPE '!'
                OR CONCAT_WS(' ', s.first_name, s.last_name) LIKE :short_name ESCAPE '!'
                OR CONCAT_WS(' ', s.last_name, s.first_name) LIKE :surname_first ESCAPE '!'
                OR s.lrn LIKE :lrn ESCAPE '!'
            )";
            $params += ['full_name' => $searchTerm, 'short_name' => $searchTerm,
                        'surname_first' => str_replace(', ', ' ', $searchTerm), 'lrn' => $searchTerm];
        }

        $from = 'FROM students s
                 LEFT JOIN grade_levels g ON g.grade_level_id = s.current_grade_level_id
                 LEFT JOIN sections sec ON sec.section_id = s.current_section_id AND sec.school_year_id = :section_year';
        $whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';
        $countQuery = $pdo->prepare('SELECT COUNT(*) ' . $from . $whereSql);
        $countQuery->execute($params);
        $filteredCount = (int) $countQuery->fetchColumn();
        $totalPages = max(1, (int) ceil($filteredCount / $perPage));
        $page = min($page, $totalPages);
        $offset = ($page - 1) * $perPage;

        $studentQuery = $pdo->prepare(
            "SELECT s.student_id, s.lrn, s.first_name, s.middle_name, s.last_name, g.grade_level, sec.section_name
             $from $whereSql
             ORDER BY s.last_name, s.first_name, s.student_id
             LIMIT $perPage OFFSET $offset"
        );
        $studentQuery->execute($params);
        $students = $studentQuery->fetchAll();
    }
}

$queryParameters = ['q' => $search, 'grade' => $gradeFilter, 'section' => $sectionFilter];
$paginationUrl = static function (int $targetPage) use ($queryParameters): string {
    return '?' . http_build_query($queryParameters + ['page' => $targetPage]) . '#student-results';
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    http_response_code(405);
    header('Allow: GET, HEAD');
    exit('Student records are read-only in the Registrar portal.');
}

$selectedStudent = null;

$assignedSubjects = [];

$gradeRecords = [];

$gradeMatrix = [];

$attendanceRecords = [];

$attendanceSummary = [

    'total_records' => 0,

    'present_count' => 0,

    'absent_count' => 0,

    'late_count' => 0,

    'excused_count' => 0

];

if ($selectedStudentId && $activeSchoolYear) {

    $selectedStudent = $getAccessibleStudent((int) $selectedStudentId);

    if (!$selectedStudent) {

        $error = 'Student not found or you do not have access to this student.';

        $selectedStudentId = null;

    } else {

        $subjectQuery = $pdo->prepare(

            "SELECT

                sub.subject_id,

                sub.subject_code,

                sub.subject_name

             FROM student_subject_enrollments sse

             INNER JOIN subjects sub

                ON sub.subject_id = sse.subject_id

             WHERE sse.student_id = :student_id

             AND sse.school_year_id = :school_year_id

             AND sse.enrollment_status = 'Enrolled'

             ORDER BY sub.subject_name"

        );

        $subjectQuery->execute([

            'student_id' => $selectedStudentId,

            'school_year_id' => $activeSchoolYear['school_year_id']

        ]);

        $assignedSubjects = $subjectQuery->fetchAll();

        $gradeQuery = $pdo->prepare(

            "SELECT

                ar.record_id,

                ar.subject_id,

                ar.grading_period,

                ar.grade,

                ar.remarks,

                ar.recorded_at,

                sub.subject_code,

                sub.subject_name,

                u.username AS recorded_by_name

             FROM academic_records ar

             INNER JOIN subjects sub

                ON sub.subject_id = ar.subject_id

             LEFT JOIN users u

                ON u.user_id = ar.recorded_by

             WHERE ar.student_id = :student_id

             AND ar.school_year_id = :school_year_id

             ORDER BY sub.subject_name, ar.record_id DESC"

        );

        $gradeQuery->execute([

            'student_id' => $selectedStudentId,

            'school_year_id' => $activeSchoolYear['school_year_id']

        ]);

        $gradeRecords = $gradeQuery->fetchAll();

        foreach ($assignedSubjects as $subject) {

            $subjectKey = (int) $subject['subject_id'];

            $gradeMatrix[$subjectKey] = [

                'subject_code' => $subject['subject_code'],

                'subject_name' => $subject['subject_name'],

                'quarters' => []

            ];

        }

        foreach ($gradeRecords as $record) {

            $subjectKey = (int) $record['subject_id'];

            if (!isset($gradeMatrix[$subjectKey])) {

                $gradeMatrix[$subjectKey] = [

                    'subject_code' => $record['subject_code'],

                    'subject_name' => $record['subject_name'],

                    'quarters' => []

                ];

            }

            if (!isset($gradeMatrix[$subjectKey]['quarters'][$record['grading_period']])) {

                $gradeMatrix[$subjectKey]['quarters'][$record['grading_period']] = $record['grade'];

            }

        }

        $attendanceSummaryQuery = $pdo->prepare(

            "SELECT

                COUNT(*) AS total_records,

                SUM(CASE WHEN ar.attendance_status = 'Present' THEN 1 ELSE 0 END) AS present_count,

                SUM(CASE WHEN ar.attendance_status = 'Absent' THEN 1 ELSE 0 END) AS absent_count,

                SUM(CASE WHEN ar.attendance_status = 'Late' THEN 1 ELSE 0 END) AS late_count,

                SUM(CASE WHEN ar.attendance_status = 'Excused' THEN 1 ELSE 0 END) AS excused_count

             FROM attendance_records ar

             INNER JOIN student_subject_enrollments sse

                ON sse.student_id = ar.student_id

                AND sse.subject_id = ar.subject_id

                AND sse.school_year_id = :school_year_id

             WHERE ar.student_id = :student_id

             AND sse.enrollment_status = 'Enrolled'"

        );

        $attendanceSummaryQuery->execute([

            'student_id' => $selectedStudentId,

            'school_year_id' => $activeSchoolYear['school_year_id']

        ]);

        $attendanceSummary = $attendanceSummaryQuery->fetch() ?: $attendanceSummary;

        if ($isAttendanceHistory || in_array($recordExport, ['print', 'download'], true)) {
            $attendancePages = max(1, (int) ceil((int) $attendanceSummary['total_records'] / $attendancePageSize));
            $attendancePage = $isAttendanceHistory ? min($attendancePage, $attendancePages) : 1;
            $attendanceOffset = ($attendancePage - 1) * $attendancePageSize;
            $attendanceQuery = $pdo->prepare(

                "SELECT

                    ar.attendance_id,

                    ar.attendance_date,

                    ar.attendance_status,

                    ar.remarks,

                    sub.subject_code,

                    sub.subject_name,

                    u.username AS recorded_by_name

                 FROM attendance_records ar

                 INNER JOIN subjects sub

                    ON sub.subject_id = ar.subject_id

                 LEFT JOIN users u

                    ON u.user_id = ar.recorded_by

                 INNER JOIN student_subject_enrollments sse

                    ON sse.student_id = ar.student_id

                    AND sse.subject_id = ar.subject_id

                    AND sse.school_year_id = :school_year_id

                 WHERE ar.student_id = :student_id

                 AND sse.enrollment_status = 'Enrolled'

                 ORDER BY ar.attendance_date DESC, sub.subject_name, ar.attendance_id DESC

                 LIMIT $attendancePageSize OFFSET $attendanceOffset"

            );

            $attendanceQuery->execute([

                'student_id' => $selectedStudentId,

                'school_year_id' => $activeSchoolYear['school_year_id']

            ]);

            $attendanceRecords = $attendanceQuery->fetchAll();
        }

    }

}

if ($selectedStudent) {
    $recordStudentName = trim($selectedStudent['first_name'] . ' ' .
        ($selectedStudent['middle_name'] ? $selectedStudent['middle_name'] . ' ' : '') . $selectedStudent['last_name']);
    $studentRecordQuery = $queryParameters + ['page' => $page, 'student_id' => $selectedStudentId];
    $studentRecordPath = BASE_URL . '/registrar/student_records.php';
    $studentDetailUrl = $studentRecordPath . '?' . http_build_query($studentRecordQuery);
    $attendanceUrl = static function (int $targetPage) use ($studentRecordPath, $studentRecordQuery): string {
        return $studentRecordPath . '?' . http_build_query(
            $studentRecordQuery + ['view' => 'attendance', 'attendance_page' => $targetPage]
        );
    };

    if (in_array($recordExport, ['print', 'download'], true)) {
        $formalTitle = 'Student Record Summary';
        $formalOffice = "Registrar's Office";
        $formalAutoPrint = $recordExport === 'print';
        $formalBackLabel = 'Back to student record';
        $formalBackUrl = $studentDetailUrl;
        if ($recordExport === 'download') {
            $formalFilename = 'student-record-' . (int) $selectedStudentId . '.doc';
        }
        require __DIR__ . '/../includes/student_record_document.php';
        exit;
    }
}

require_once __DIR__ . '/../includes/header.php';

?>

<style>

    .records-student-filters {

        display: grid;

        grid-template-columns: minmax(240px, 1.5fr) repeat(2, minmax(150px, .8fr));

        gap: 14px;

        align-items: start;

        margin: 18px 0 8px;

    }

    .records-student-filters .form-group {
        min-width: 0;
        margin-bottom: 0;
    }

    #student-results {
        margin-top: 24px;
        scroll-margin-top: 110px;
    }

    .records-results-list {
        list-style: none;
        margin: 12px 0;
        padding: 0;
        border-top: 1px solid var(--line);
    }

    .records-results-list > li {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 10px 16px;
        padding: 12px 0;
        border-bottom: 1px solid var(--line);
    }

    #student-record {
        scroll-margin-top: 110px;
    }

    .records-picker-actions {

        display: flex;

        align-items: center;

        gap: 10px;

        flex-wrap: wrap;

        margin-top: 14px;

    }

    @media (max-width: 760px) {

        .records-student-filters {

            grid-template-columns: 1fr 1fr;

        }

        .records-search-field {

            grid-column: 1 / -1;

        }

    }

    @media (max-width: 480px) {

        .records-student-filters {

            grid-template-columns: 1fr;

        }

        .records-search-field {

            grid-column: auto;

        }

        .records-picker-heading {

            align-items: flex-start;

            flex-direction: column;

        }

    }

    .records-student-card,

    .records-table-card,

    .records-management-card {

        min-width: 0;

    }

    .records-section-heading {

        display: flex;

        justify-content: space-between;

        align-items: center;

        gap: 14px;

        margin-bottom: 18px;

    }

    .records-section-heading h2 {

        margin: 0;

    }

    .records-section-heading .eyebrow {

        margin: 0 0 5px;

    }

    .records-student-status,

    .records-table-note {

        color: #64748b;

        font-size: 13px;

        white-space: nowrap;

    }

    .records-student-grid {

        display: grid;

        grid-template-columns: repeat(2, minmax(0, 1fr));

        gap: 12px;

        width: 100%;

        max-width: none;

    }

    .records-student-grid .form-group {

        margin: 0;

        padding: 14px;

        border: 1px solid #e2e8f0;

        border-radius: 10px;

        background: #f8fafc;

        min-width: 0;

    }

    .records-student-grid label {

        display: block;

        margin-bottom: 6px;

        color: #64748b;

        font-size: 12px;

    }

    .records-student-grid strong {

        display: block;

        color: #172033;

        overflow-wrap: anywhere;

    }

    .records-workspace-grid {

        display: grid;

        grid-template-columns: minmax(0, 1fr);

        gap: 20px;

        align-items: stretch;

    }

    .records-management-card {

        width: 100%;
        max-width: none;
        margin: 0 0 18px;

    }

    .records-management-card h2 {

        margin-top: 0;

    }


    .attendance-summary-grid {

        display: grid;

        grid-template-columns: repeat(2, minmax(0, 1fr));

        gap: 10px;

        margin-top: 18px;

    }

    .attendance-summary-item {

        padding: 14px;

        border: 1px solid #e2e8f0;

        border-radius: 10px;

        background: #f8fafc;

    }

    .attendance-summary-item span {

        display: block;

        color: #64748b;

        font-size: 12px;

        margin-bottom: 5px;

    }

    .attendance-summary-item strong {

        color: #651b35;

        font-size: 23px;

    }

    .attendance-summary-item .attendance-present {

        color: #187344;

    }

    .attendance-summary-item .attendance-absent {

        color: #b42318;

    }

    .attendance-total {

        margin: 14px 0 0;

        color: #475569;

        font-size: 13px;

    }

    .records-table-card {

        overflow: hidden;

    }

    .records-table-container {

        width: 100%;

        overflow-x: auto;

    }

    .records-table-container table {

        width: 100%;

        min-width: 680px;

        border-collapse: collapse;

    }

    .records-table-container th,

    .records-table-container td {

        padding: 12px 14px;

        vertical-align: middle;

    }

    .records-grade-table th:not(:first-child),

    .records-grade-table .records-grade-cell {

        text-align: center;

    }

    .records-grade-table th {

        white-space: nowrap;

    }

    .records-subject-name {

        display: block;

        color: #64748b;

        font-size: 12px;

        margin-top: 3px;

    }

    .records-no-grade {

        color: #94a3b8;

    }

    .records-grade-cell {

        font-weight: 600;

        font-variant-numeric: tabular-nums;

    }

    @media (max-width: 900px) {

        .records-workspace-grid {

            grid-template-columns: minmax(0, 1fr);

        }

        .records-student-grid {

            grid-template-columns: repeat(2, minmax(0, 1fr));

        }

    }

    @media (max-width: 600px) {

        .records-student-grid,


        .records-section-heading {

            align-items: flex-start;

            flex-direction: column;

        }

        .records-student-status,

        .records-table-note {

            white-space: normal;

        }

    }

    .records-profile-actions {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 16px;
        flex-wrap: wrap;
    }

    .records-export-icon {
        display: inline-grid;
        place-items: center;
        width: 44px;
        height: 44px;
        padding: 0;
    }

    .records-export-icon svg {
        width: 20px;
        height: 20px;
        fill: none;
        stroke: currentColor;
        stroke-width: 1.7;
        stroke-linecap: round;
        stroke-linejoin: round;
    }

    .records-export-actions {
        display: flex;
        align-items: center;
        gap: 8px;
    }

    @media (max-width: 600px) {
        .records-profile-actions {
            justify-content: flex-start;
            align-items: flex-start;
            flex-direction: column;
            gap: 10px;
        }
    }

</style>

<?php if (!$showStudentRecord): ?>
<section class="form-card">


    <h1>Student Records Management</h1>

    <?php if ($activeSchoolYear): ?>

        <p>

            <strong>Active School Year:</strong>

            <?= e($activeSchoolYear['school_year']) ?>

        </p>

    <?php else: ?>

        <p class="alert alert-error">

            No active school year is currently configured.

        </p>

    <?php endif; ?>

    <?php if ($error): ?>

        <p class="alert alert-error"><?= e($error) ?></p>

    <?php endif; ?>

    <?php if ($activeSchoolYear): ?>
        <nav class="application-status-links" aria-label="Filter student records by grade">
            <?php foreach (array_merge([''], $gradeLevels, ['unassigned']) as $gradeOption): ?>
                <?php
                $tabGrade = (string) $gradeOption;
                $tabQuery = ['q' => $search, 'grade' => $tabGrade];
                if ($tabGrade === $gradeFilter && $sectionFilter !== '') {
                    $tabQuery['section'] = $sectionFilter;
                }
                $tabLabel = match ($tabGrade) {
                    '' => 'All grades',
                    'unassigned' => 'Grade not assigned',
                    default => 'Grade ' . $tabGrade
                };
                ?>
                <a class="button <?= $gradeFilter === $tabGrade ? 'button-primary' : 'button-outline' ?>"
                   href="?<?= e(http_build_query($tabQuery)) ?>"
                   <?= $gradeFilter === $tabGrade ? 'aria-current="true"' : '' ?>>
                    <?= e($tabLabel) ?>
                </a>
            <?php endforeach; ?>
        </nav>

        <form method="get" class="table-search-form" action="<?= BASE_URL ?>/registrar/student_records.php#student-results">
            <input type="hidden" name="grade" value="<?= e($gradeFilter) ?>">
            <div class="table-search-toolbar">
                <div class="table-search-field">
                    <label for="student_search">Student name or LRN</label>
                    <input type="search" id="student_search" name="q" maxlength="150" placeholder="Enter student name or LRN"
                           value="<?= e($search) ?>" autocomplete="off"
                           data-student-suggestions="registrar_records"
                           data-suggestion-url="<?= BASE_URL ?>/includes/student_suggestions.php">
                </div>
                <button type="submit" class="button button-primary">Search</button>
                <details class="table-search-disclosure">
                    <summary class="button button-outline">Filters<?= $sectionFilter !== '' ? ' (active)' : '' ?></summary>
                    <div class="table-search-panel">
                        <h3 class="table-search-panel-title">Filter results</h3>
                        <div class="table-search-fields">
                            <div class="form-group">
                                <label for="section_filter">Section</label>
                                <?php
                                $availableSections = array_filter($sections, static function (array $section) use ($gradeFilter): bool {
                                    return $gradeFilter === '' || (string) $section['grade_level'] === $gradeFilter;
                                });
                                ?>
                                <select id="section_filter" name="section">
                                    <option value=""><?= $availableSections ? 'All sections' : 'No sections for this grade' ?></option>
                                    <?php foreach ($availableSections as $section): ?>
                                        <option value="<?= (int) $section['section_id'] ?>"
                                                <?= $sectionFilter === (int) $section['section_id'] ? 'selected' : '' ?>>
                                            <?= e($section['section_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="table-search-actions">
                            <button type="submit" class="button button-primary">Apply filters</button>
                            <a class="button button-outline" href="?<?= e(http_build_query(['q' => $search, 'grade' => $gradeFilter])) ?>">Clear filters</a>
                        </div>
                    </div>
                </details>
            </div>
        </form>

        <div id="student-results">
            <h2>Student results</h2>
            <p class="muted">
                <?php if ($filteredCount): ?>
                    Showing <?= number_format($offset + 1) ?>–<?= number_format(min($offset + $perPage, $filteredCount)) ?>
                    of <?= number_format($filteredCount) ?> students
                <?php else: ?>
                    No students match these filters. Try a different grade, section, or search term.
                <?php endif; ?>
            </p>
            <?php if ($students): ?>
                <ul class="records-results-list">
                    <?php foreach ($students as $resultStudent): ?>
                        <?php
                        $studentName = $resultStudent['last_name'] . ', ' . trim(
                            $resultStudent['first_name'] . ' ' . ($resultStudent['middle_name'] ?? '')
                        );
                        $recordUrl = '?' . http_build_query(
                            $queryParameters + ['page' => $page, 'student_id' => $resultStudent['student_id']]
                        ) . '#student-record';
                        ?>
                        <li>
                            <div>
                                <strong><?= e($studentName) ?></strong>
                                <div class="muted">
                                    <?= e($resultStudent['grade_level'] ? 'Grade ' . $resultStudent['grade_level'] : 'Grade not assigned') ?>
                                    · <?= e($resultStudent['section_name'] ?: 'No section') ?>
                                    <?php if ($resultStudent['lrn']): ?>
                                        · LRN <?= e($resultStudent['lrn']) ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <a class="button button-outline" href="<?= e($recordUrl) ?>"
                               aria-label="<?= e('View record for ' . $studentName) ?>">View record</a>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <?php if ($totalPages > 1): ?>
                    <?php
                    $paginationLabel = 'Student results pages';
                    $showPageJump = false;
                    require __DIR__ . '/../includes/registrar_list_pagination.php';
                    ?>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</section>



<?php else: ?>
    <div class="portal-back-row">
        <?php if ($selectedStudent && $isAttendanceHistory): ?>
            <a class="portal-back-link" href="<?= e($studentDetailUrl) ?>">Back to student record</a>
        <?php else: ?>
        <a class="portal-back-link"
           href="<?= BASE_URL ?>/registrar/student_records.php<?= e($paginationUrl($page)) ?>">
            Back to student results
        </a>
        <?php endif; ?>
    </div>
    <?php if (!$selectedStudent): ?>
        <section class="form-card">
            <h1>Student record unavailable</h1>
            <p><?= e($error ?: 'No active school year is currently configured.') ?></p>
        </section>
    <?php endif; ?>
<?php endif; ?>

<?php if ($selectedStudent && !$isAttendanceHistory): ?>

    <section class="form-card records-student-card" id="student-record">

        <div class="records-section-heading">

            <div>


                <h1>Student Record</h1>

            </div>

            <div class="records-profile-actions">
                <span class="records-student-status">Active School Year: <?= e($activeSchoolYear['school_year']) ?></span>
                <div class="records-export-actions">
                    <a class="button button-outline records-export-icon" href="<?= e($studentDetailUrl . '&record_export=print') ?>"
                       data-print-report target="_blank" rel="noopener" aria-label="Print student record" title="Print student record">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 8V3h10v5M7 17H5a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2M7 14h10v7H7z"/><path d="M17 11h.01"/></svg>
                    </a>
                    <a class="button button-primary records-export-icon" href="<?= e($studentDetailUrl . '&record_export=download') ?>"
                       aria-label="Download student record" title="Download student record">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3v12m0 0 5-5m-5 5-5-5M4 17v4h16v-4"/></svg>
                    </a>
                </div>
            </div>

        </div>

        <div class="records-student-grid">

            <div class="form-group">

                <label>Student Name</label>

                <strong>

                    <?= e(

                        $selectedStudent['first_name'] . ' ' .

                        ($selectedStudent['middle_name'] ?? '') . ' ' .

                        $selectedStudent['last_name']

                    ) ?>

                </strong>

            </div>

            <div class="form-group">

                <label>LRN</label>

                <strong><?= e($selectedStudent['lrn'] ?? 'Not provided') ?></strong>

            </div>

            <div class="form-group">

                <label>Grade Level</label>

                <strong>

                    <?= e($selectedStudent['grade_level'] ?? 'Not assigned') ?>

                </strong>

            </div>

            <div class="form-group">

                <label>Section</label>

                <strong>

                    <?php

                    $sectionDisplay = trim((string) ($selectedStudent['section_name'] ?? ''));

                    $gradeDisplay = trim((string) ($selectedStudent['grade_level'] ?? ''));

                    if ($sectionDisplay !== '' && $gradeDisplay !== '') {

                        $sectionDisplay = preg_replace(

                            '/^Grade\s*' . preg_quote($gradeDisplay, '/') . '\s*[-–:]?\s*/i',

                            '',

                            $sectionDisplay

                        );

                    }

                    ?>

                    <?= e($sectionDisplay !== '' ? $sectionDisplay : 'Not assigned') ?>

                </strong>

            </div>

        </div>

    </section>

    <div class="records-workspace-grid">

        <section class="form-card records-management-card">

            <h2>Attendance Summary</h2>

            <p class="muted">Attendance is displayed for reference. Daily attendance entry is handled by teachers or advisers.</p>

            <div class="attendance-summary-grid">

                <div class="attendance-summary-item">

                    <span>Present</span>

                    <strong class="attendance-present"><?= number_format((int) ($attendanceSummary['present_count'] ?? 0)) ?></strong>

                </div>

                <div class="attendance-summary-item">

                    <span>Absent</span>

                    <strong class="attendance-absent"><?= number_format((int) ($attendanceSummary['absent_count'] ?? 0)) ?></strong>

                </div>

                <div class="attendance-summary-item">

                    <span>Late</span>

                    <strong><?= number_format((int) ($attendanceSummary['late_count'] ?? 0)) ?></strong>

                </div>

                <div class="attendance-summary-item">

                    <span>Excused</span>

                    <strong><?= number_format((int) ($attendanceSummary['excused_count'] ?? 0)) ?></strong>

                </div>

            </div>

            <p class="attendance-total">

                Total attendance entries: <strong><?= number_format((int) ($attendanceSummary['total_records'] ?? 0)) ?></strong>

            </p>
            <a class="button button-outline" href="<?= e($attendanceUrl(1)) ?>">View attendance history</a>

        </section>

    </div>

    <section class="form-card records-table-card">

        <div class="records-section-heading">

            <div>


                <h2>Grade Records</h2>

            </div>

            <span class="records-table-note"><?= e($activeSchoolYear['school_year']) ?></span>

        </div>

        <?php if ($gradeMatrix): ?>

            <div class="table-container records-table-container">

                <table class="records-grade-table">

                    <thead>

                        <tr>

                            <th>Subject</th>

                            <th>First Quarter</th>

                            <th>Second Quarter</th>

                            <th>Third Quarter</th>

                            <th>Fourth Quarter</th>

                        </tr>

                    </thead>

                    <tbody>

                        <?php foreach ($gradeMatrix as $subject): ?>

                            <tr>

                                <td>

                                    <strong><?= e($subject['subject_code']) ?></strong>

                                    <span class="records-subject-name"><?= e($subject['subject_name']) ?></span>

                                </td>

                                <?php foreach ($gradingPeriods as $period): ?>

                                    <td class="records-grade-cell">

                                        <?php if (isset($subject['quarters'][$period])): ?>

                                            <?= e((string) $subject['quarters'][$period]) ?>

                                        <?php else: ?>

                                            <span class="records-no-grade">—</span>

                                        <?php endif; ?>

                                    </td>

                                <?php endforeach; ?>

                            </tr>

                        <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        <?php else: ?>

            <p class="muted">No enrolled subjects are available for this student.</p>

        <?php endif; ?>

    </section>

<?php endif; ?>

<?php if ($selectedStudent && $isAttendanceHistory): ?>
    <section class="form-card records-table-card">

        <div class="records-section-heading">

            <div>


                <h1>Attendance History</h1>
                <p class="muted"><?= e($recordStudentName) ?> · School year <?= e($activeSchoolYear['school_year']) ?></p>

            </div>

            <span class="records-table-note"><?= number_format((int) $attendanceSummary['total_records']) ?> entries</span>

        </div>

        <?php if ($attendanceRecords): ?>

            <div class="table-container records-table-container">

                <table class="records-attendance-table">

                    <thead>

                        <tr>

                            <th>Date</th>

                            <th>Subject</th>

                            <th>Status</th>

                            <th>Remarks</th>

                            <th>Recorded By</th>

                        </tr>

                    </thead>

                    <tbody>

                        <?php foreach ($attendanceRecords as $record): ?>

                            <tr>

                                <td><?= e($record['attendance_date']) ?></td>

                                <td>

                                    <?= e($record['subject_code'] . ' - ' . $record['subject_name']) ?>

                                </td>

                                <td><?= e($record['attendance_status']) ?></td>

                                <td><?= e($record['remarks'] ?? '') ?></td>

                                <td><?= e($record['recorded_by_name'] ?? 'Previous record') ?></td>

                            </tr>

                        <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

            <?php if ($attendancePages > 1): ?>
                <nav class="registrar-list-pagination" aria-label="Attendance history pages">
                    <span>Page <?= $attendancePage ?> of <?= $attendancePages ?></span>
                    <?php if ($attendancePage > 1): ?>
                        <a class="button button-outline" href="<?= e($attendanceUrl($attendancePage - 1)) ?>">Previous page</a>
                    <?php endif; ?>
                    <?php if ($attendancePage < $attendancePages): ?>
                        <a class="button button-outline" href="<?= e($attendanceUrl($attendancePage + 1)) ?>">Next page</a>
                    <?php endif; ?>
                </nav>
            <?php endif; ?>
        <?php else: ?>

            <p>No attendance records found for this student.</p>

        <?php endif; ?>

    </section>

<?php endif; ?>

<script src="<?= BASE_URL ?>/assets/js/student_suggestions.js" defer></script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
