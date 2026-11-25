

<?php

require_once __DIR__ . '/../includes/bootstrap.php';

require_role('School Registrar');

$pageTitle = 'Student Records Management';
$schoolName = $_SESSION['school_name'] ?? 'School Name';

$user = current_user();

$isAdmin = true;

$success = $_SESSION['records_success'] ?? '';

unset($_SESSION['records_success']);

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

$selectedStudentId = filter_input(

    INPUT_GET,

    'student_id',

    FILTER_VALIDATE_INT

);

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

$students = [];

if ($activeSchoolYear) {

    if ($isAdmin) {

        $studentQuery = $pdo->prepare(

            "SELECT

                s.student_id,

                s.lrn,

                s.first_name,

                s.middle_name,

                s.last_name,

                g.grade_level,

                sec.section_name

             FROM students s

             LEFT JOIN grade_levels g

                ON g.grade_level_id = s.current_grade_level_id

             LEFT JOIN sections sec

                ON sec.section_id = s.current_section_id

             ORDER BY s.last_name, s.first_name"

        );

        $studentQuery->execute();

    } else {

        $studentQuery = $pdo->prepare(

            "SELECT

                s.student_id,

                s.lrn,

                s.first_name,

                s.middle_name,

                s.last_name,

                g.grade_level,

                sec.section_name

             FROM students s

             INNER JOIN sections sec

                ON sec.section_id = s.current_section_id

             INNER JOIN teacher_section_assignments tsa

                ON tsa.section_id = sec.section_id

             INNER JOIN grade_levels g

                ON g.grade_level_id = s.current_grade_level_id

             WHERE tsa.teacher_user_id = :teacher_user_id

             AND tsa.school_year_id = :school_year_id

             AND sec.school_year_id = :section_year_id

             ORDER BY s.last_name, s.first_name"

        );

        $studentQuery->execute([

            'teacher_user_id' => $user['user_id'],

            'school_year_id' => $activeSchoolYear['school_year_id'],

            'section_year_id' => $activeSchoolYear['school_year_id']

        ]);

    }

    $students = $studentQuery->fetchAll();

}

$studentsJson = json_encode(

    $students,

    JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT

);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? '';

    $postedStudentId = filter_var(

        $_POST['student_id'] ?? null,

        FILTER_VALIDATE_INT

    );

    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {

        $error = 'Invalid request. Please refresh the page.';

    } elseif (!$activeSchoolYear) {

        $error = 'There is no active school year.';

    } elseif (!$postedStudentId) {

        $error = 'Please select a valid student.';

    } else {

        $selectedStudentId = $postedStudentId;

        $studentForAction = $getAccessibleStudent($postedStudentId);

        if (!$studentForAction) {

            $error = 'You do not have permission to manage records for this student.';

        } else {

            try {

                $subjectId = filter_var(

                    $_POST['subject_id'] ?? null,

                    FILTER_VALIDATE_INT

                );

                if (!$subjectId) {

                    throw new RuntimeException('Please select a valid subject.');

                }

                $subjectCheck = $pdo->prepare(

                    "SELECT sub.subject_id

                     FROM student_subject_enrollments sse

                     INNER JOIN subjects sub

                        ON sub.subject_id = sse.subject_id

                     WHERE sse.student_id = :student_id

                     AND sse.subject_id = :subject_id

                     AND sse.school_year_id = :school_year_id

                     AND sse.enrollment_status = 'Enrolled'

                     AND sub.grade_level_id = (

                         SELECT current_grade_level_id

                         FROM students

                         WHERE student_id = :grade_student_id

                     )

                     LIMIT 1"

                );

                $subjectCheck->execute([

                    'student_id' => $postedStudentId,

                    'subject_id' => $subjectId,

                    'school_year_id' => $activeSchoolYear['school_year_id'],

                    'grade_student_id' => $postedStudentId

                ]);

                if (!$subjectCheck->fetch()) {

                    throw new RuntimeException(

                        'This subject is not currently assigned to the selected student.'

                    );

                }

                if ($action === 'save_grade') {

                    $gradingPeriod = $_POST['grading_period'] ?? '';

                    $gradeInput = trim($_POST['grade'] ?? '');

                    $remarks = trim($_POST['remarks'] ?? '');

                    if (!in_array($gradingPeriod, $gradingPeriods, true)) {

                        throw new RuntimeException('Please select a valid grading period.');

                    }

                    if (

                        $gradeInput === '' ||

                        !is_numeric($gradeInput) ||

                        (float) $gradeInput < 0 ||

                        (float) $gradeInput > 100

                    ) {

                        throw new RuntimeException(

                            'Grade must be a number between 0 and 100.'

                        );

                    }

                    $grade = round((float) $gradeInput, 2);

                    $existingGradeQuery = $pdo->prepare(

                        "SELECT record_id

                         FROM academic_records

                         WHERE student_id = :student_id

                         AND subject_id = :subject_id

                         AND school_year_id = :school_year_id

                         AND grading_period = :grading_period

                         ORDER BY record_id DESC

                         LIMIT 1"

                    );

                    $existingGradeQuery->execute([

                        'student_id' => $postedStudentId,

                        'subject_id' => $subjectId,

                        'school_year_id' => $activeSchoolYear['school_year_id'],

                        'grading_period' => $gradingPeriod

                    ]);

                    $existingGrade = $existingGradeQuery->fetch();

                    if ($existingGrade) {

                        $saveGrade = $pdo->prepare(

                            "UPDATE academic_records

                             SET grade = :grade,

                                 remarks = :remarks,

                                 recorded_by = :recorded_by

                             WHERE record_id = :record_id"

                        );

                        $saveGrade->execute([

                            'grade' => $grade,

                            'remarks' => $remarks !== '' ? $remarks : null,

                            'recorded_by' => $user['user_id'],

                            'record_id' => $existingGrade['record_id']

                        ]);

                    } else {

                        $saveGrade = $pdo->prepare(

                            "INSERT INTO academic_records

                                (

                                    student_id,

                                    subject_id,

                                    school_year_id,

                                    grading_period,

                                    grade,

                                    remarks,

                                    recorded_by

                                )

                             VALUES

                                (

                                    :student_id,

                                    :subject_id,

                                    :school_year_id,

                                    :grading_period,

                                    :grade,

                                    :remarks,

                                    :recorded_by

                                )"

                        );

                        $saveGrade->execute([

                            'student_id' => $postedStudentId,

                            'subject_id' => $subjectId,

                            'school_year_id' => $activeSchoolYear['school_year_id'],

                            'grading_period' => $gradingPeriod,

                            'grade' => $grade,

                            'remarks' => $remarks !== '' ? $remarks : null,

                            'recorded_by' => $user['user_id']

                        ]);

                    }

                    $_SESSION['records_success'] = 'Grade saved successfully.';

                    redirect('/registrar/student_records.php?student_id=' . $postedStudentId);

                } else {

                    throw new RuntimeException('Invalid action.');

                }

            } catch (RuntimeException $exception) {

                $error = $exception->getMessage();

            } catch (PDOException $exception) {

                error_log('Registrar records error: ' . $exception->getMessage());

                $error = 'Unable to save the record. Please try again.';

            }

        }

    }

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

             ORDER BY ar.attendance_date DESC, sub.subject_name

             LIMIT 30"

        );

        $attendanceQuery->execute([

            'student_id' => $selectedStudentId,

            'school_year_id' => $activeSchoolYear['school_year_id']

        ]);

        $attendanceRecords = $attendanceQuery->fetchAll();

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

    .records-student-filters .form-group,

    .records-student-picker {

        min-width: 0;

        margin-bottom: 0;

    }

    .records-student-filters small {

        display: block;

        margin-top: 6px;

        font-size: 12px;

    }

    .records-picker-heading {

        display: flex;

        align-items: center;

        justify-content: space-between;

        gap: 12px;

        margin-bottom: 6px;

    }

    .records-picker-heading label {

        margin: 0;

    }

    .records-result-count {

        color: #64748b;

        font-size: 12px;

    }

    .records-empty-message {

        display: block;

        margin-top: 8px;

        color: #b42318;

        font-size: 12px;

    }

    .records-empty-message[hidden] {

        display: none;

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

        grid-template-columns: minmax(0, 1.2fr) minmax(280px, .8fr);

        gap: 20px;

        align-items: stretch;

    }

    .records-management-card {

        margin: 0;

    }

    .records-management-card h2 {

        margin-top: 0;

    }

    .records-grade-form-grid {

        grid-template-columns: repeat(2, minmax(0, 1fr));

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

            grid-template-columns: 1fr;

        }

        .records-student-grid {

            grid-template-columns: repeat(2, minmax(0, 1fr));

        }

    }

    @media (max-width: 600px) {

        .records-student-grid,

        .records-grade-form-grid {

            grid-template-columns: 1fr;

        }

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

    .records-export-actions {
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .records-icon-button {
        width: 42px;
        height: 42px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border: 1px solid #651b35;
        border-radius: 10px;
        background: #fff;
        color: #651b35;
        cursor: pointer;
        transition: .2s ease;
    }

    .records-icon-button:hover {
        background: #f8edf1;
    }

    .records-icon-button.records-download-button {
        background: #651b35;
        color: #fff;
    }

    .records-icon-button.records-download-button:hover {
        background: #4f1429;
    }

    .records-icon-button svg {
        width: 20px;
        height: 20px;
        fill: none;
        stroke: currentColor;
        stroke-width: 1.8;
        stroke-linecap: round;
        stroke-linejoin: round;
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

<section class="form-card">

    <p class="eyebrow">Registrar workspace</p>

    <h1>Student Records Management</h1>

    <p>

        Review student information, academic performance, and attendance for the active school year.

    </p>

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

    <?php if ($success): ?>

        <p class="alert alert-success"><?= e($success) ?></p>

    <?php endif; ?>

    <?php if ($error): ?>

        <p class="alert alert-error"><?= e($error) ?></p>

    <?php endif; ?>

    <?php if (!$isAdmin): ?>

        <p class="muted">

            You can only manage students in your assigned sections.

        </p>

    <?php else: ?>

        <p class="muted">

            Registrar access: all student sections.

        </p>

    <?php endif; ?>

    <form method="GET" id="studentSearchForm">

        <div class="records-student-filters">

            <div class="form-group records-search-field">

                <label for="student_search">Search Student</label>

                <input

                    type="search"

                    id="student_search"

                    placeholder="Search by student name or LRN..."

                    autocomplete="off"

                >

                <small class="muted">Type a student's first name, last name, or LRN.</small>

            </div>

            <div class="form-group">

                <label for="grade_filter">Grade Level</label>

                <select id="grade_filter">

                    <option value="">All Grade Levels</option>

                </select>

            </div>

            <div class="form-group">

                <label for="section_filter">Section</label>

                <select id="section_filter">

                    <option value="">All Sections</option>

                </select>

            </div>

        </div>

        <div class="form-group records-student-picker">

            <div class="records-picker-heading">

                <label for="student_id">Select Student</label>

                <span id="student_result_count" class="records-result-count"></span>

            </div>

            <select name="student_id" id="student_id" required>

                <option value="">-- Select a Student --</option>

            </select>

            <small id="student_empty_message" class="records-empty-message" hidden>

                No students match your search or selected filters.

            </small>

        </div>

        <div class="form-actions records-picker-actions">

            <button type="submit" class="button button-primary">

                View Student Records

            </button>

            <button type="button" class="button button-secondary" id="clear_student_filters">

                Clear Filters

            </button>

        </div>

    </form>

</section>

<script>

document.addEventListener('DOMContentLoaded', function () {

    const students = <?= $studentsJson ?: '[]' ?>;

    const selectedStudentId = <?= json_encode((string) ($selectedStudentId ?? '')) ?>;

    const searchInput = document.getElementById('student_search');

    const gradeFilter = document.getElementById('grade_filter');

    const sectionFilter = document.getElementById('section_filter');

    const studentSelect = document.getElementById('student_id');

    const resultCount = document.getElementById('student_result_count');

    const emptyMessage = document.getElementById('student_empty_message');

    const clearButton = document.getElementById('clear_student_filters');

    const normalize = value => String(value ?? '').toLocaleLowerCase().trim();

    const gradeName = student => student.grade_level || 'Grade Not Assigned';

    const sectionName = student => student.section_name || 'No Section';

    const fullName = student => [

        student.last_name,

        student.first_name,

        student.middle_name

    ].filter(Boolean).join(', ');

    const compareText = (a, b) => String(a).localeCompare(String(b), undefined, {

        numeric: true,

        sensitivity: 'base'

    });

    function populateGradeFilter() {

        const grades = [...new Set(students.map(gradeName))].sort(compareText);

        grades.forEach(grade => {

            const option = document.createElement('option');

            option.value = grade;

            option.textContent = grade;

            gradeFilter.appendChild(option);

        });

    }

    function populateSectionFilter() {

        const selectedGrade = gradeFilter.value;

        const sections = [...new Set(

            students

                .filter(student => !selectedGrade || gradeName(student) === selectedGrade)

                .map(sectionName)

        )].sort(compareText);

        const currentSection = sectionFilter.value;

        sectionFilter.innerHTML = '<option value="">All Sections</option>';

        sections.forEach(section => {

            const option = document.createElement('option');

            option.value = section;

            option.textContent = section;

            sectionFilter.appendChild(option);

        });

        if (sections.includes(currentSection)) {

            sectionFilter.value = currentSection;

        }

    }

    function renderStudentOptions() {

        const query = normalize(searchInput.value);

        const selectedGrade = gradeFilter.value;

        const selectedSection = sectionFilter.value;

        let filtered = students.filter(student => {

            const searchable = normalize([

                student.first_name,

                student.middle_name,

                student.last_name,

                student.lrn

            ].join(' '));

            return (!query || searchable.includes(query))

                && (!selectedGrade || gradeName(student) === selectedGrade)

                && (!selectedSection || sectionName(student) === selectedSection);

        });

        filtered.sort((a, b) => {

            const gradeCompare = compareText(gradeName(a), gradeName(b));

            if (gradeCompare !== 0) return gradeCompare;

            const sectionCompare = compareText(sectionName(a), sectionName(b));

            if (sectionCompare !== 0) return sectionCompare;

            return compareText(a.last_name, b.last_name)

                || compareText(a.first_name, b.first_name);

        });

        studentSelect.innerHTML = '';

        const placeholder = document.createElement('option');

        placeholder.value = '';

        placeholder.textContent = '-- Select a Student --';

        studentSelect.appendChild(placeholder);

        const groups = new Map();

        filtered.forEach(student => {

            const groupLabel = gradeName(student) + ' — ' + sectionName(student);

            if (!groups.has(groupLabel)) {

                const group = document.createElement('optgroup');

                group.label = groupLabel;

                groups.set(groupLabel, group);

                studentSelect.appendChild(group);

            }

            const option = document.createElement('option');

            option.value = student.student_id;

            option.textContent = fullName(student)

                + (student.lrn ? ' | LRN: ' + student.lrn : '');

            groups.get(groupLabel).appendChild(option);

        });

        const selectedStillVisible = filtered.some(

            student => String(student.student_id) === String(selectedStudentId)

        );

        if (selectedStillVisible && !query && !selectedGrade && !selectedSection) {

            studentSelect.value = selectedStudentId;

        } else if (studentSelect.querySelector('option[value="' + CSS.escape(selectedStudentId) + '"]')) {

            studentSelect.value = selectedStudentId;

        }

        resultCount.textContent = 'Showing ' + filtered.length + ' of ' + students.length + ' students';

        emptyMessage.hidden = filtered.length !== 0;

    }

    populateGradeFilter();

    populateSectionFilter();

    renderStudentOptions();

    searchInput.addEventListener('input', renderStudentOptions);

    gradeFilter.addEventListener('change', function () {

        populateSectionFilter();

        renderStudentOptions();

    });

    sectionFilter.addEventListener('change', renderStudentOptions);

    clearButton.addEventListener('click', function (event) {

        event.preventDefault();

        window.location.href = window.location.pathname;

    });

});

</script>

</section>

<?php if ($selectedStudent): ?>

    <section class="form-card records-student-card">

        <div class="records-section-heading">

            <div>

                <p class="eyebrow">Student Profile</p>

                <h2>Student Information</h2>

            </div>

            <div class="records-profile-actions">
                <span class="records-student-status">Active School Year: <?= e($activeSchoolYear['school_year']) ?></span>
                <div class="records-export-actions">
                    <button type="button" class="records-icon-button records-print-button" id="print_student_record" aria-label="Print student record" title="Print student record">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 8V3h10v5M7 17H5a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2M7 14h10v7H7z"/><path d="M17 11h.01"/></svg>
                    </button>
                    <button type="button" class="records-icon-button records-download-button" id="download_student_record" aria-label="Download student record" title="Download student record">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3v12m0 0 5-5m-5 5-5-5M4 17v4h16v-4"/></svg>
                    </button>
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

            <?php if ($assignedSubjects): ?>

                <h2>Grade Management</h2>

                <p class="muted">Enter or update a grade for the selected student's enrolled subject.</p>

                <form method="POST">

                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

                    <input type="hidden" name="action" value="save_grade">

                    <input type="hidden" name="student_id" value="<?= (int) $selectedStudent['student_id'] ?>">

                    <div class="form-group">

                        <label for="grade_subject">Subject</label>

                        <select name="subject_id" id="grade_subject" required>

                            <option value="">Select Subject</option>

                            <?php foreach ($assignedSubjects as $subject): ?>

                                <option value="<?= (int) $subject['subject_id'] ?>">

                                    <?= e($subject['subject_code'] . ' - ' . $subject['subject_name']) ?>

                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>

                    <div class="form-grid records-grade-form-grid">

                        <div class="form-group">

                            <label for="grading_period">Grading Period</label>

                            <select name="grading_period" id="grading_period" required>

                                <option value="">Select Quarter</option>

                                <?php foreach ($gradingPeriods as $period): ?>

                                    <option value="<?= e($period) ?>"><?= e($period) ?></option>

                                <?php endforeach; ?>

                            </select>

                        </div>

                        <div class="form-group">

                            <label for="grade">Grade</label>

                            <input type="number" name="grade" id="grade" min="0" max="100"

                                   step="0.01" placeholder="Enter grade" required>

                        </div>

                    </div>

                    <div class="form-group">

                        <label for="grade_remarks">Remarks</label>

                        <input type="text" name="remarks" id="grade_remarks" placeholder="Optional remarks">

                    </div>

                    <div class="form-actions">

                        <button type="submit" class="btn btn-primary">Save Grade</button>

                    </div>

                </form>

            <?php else: ?>

                <h2>No Assigned Subjects</h2>

                <p>This student does not have any enrolled subjects for the active school year. Assign subjects first through Subject Management.</p>

            <?php endif; ?>

        </section>

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

        </section>

    </div>

    <section class="form-card records-table-card">

        <div class="records-section-heading">

            <div>

                <p class="eyebrow">Academic Performance</p>

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

    <section class="form-card records-table-card">

        <div class="records-section-heading">

            <div>

                <p class="eyebrow">Attendance History</p>

                <h2>Recent Attendance Records</h2>

            </div>

            <span class="records-table-note">Latest 30 entries</span>

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

        <?php else: ?>

            <p>No attendance records found for this student.</p>

        <?php endif; ?>

    </section>

<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const printButton = document.getElementById('print_student_record');
    const downloadButton = document.getElementById('download_student_record');

    if (!printButton || !downloadButton) {
        return;
    }

    const schoolName = <?= json_encode($schoolName, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
    const studentName = <?= json_encode(trim($selectedStudent['first_name'] . ' ' . ($selectedStudent['middle_name'] ?? '') . ' ' . $selectedStudent['last_name']), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
    const schoolYear = <?= json_encode($activeSchoolYear['school_year'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;

    function escapeHtml(value) {
        return String(value ?? '').replace(/[&<>"']/g, function (character) {
            return {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            }[character];
        });
    }

    function createRecordDocument() {
        const profile = document.querySelector('.records-student-card');
        const workspace = document.querySelector('.records-workspace-grid');
        const tableCards = document.querySelectorAll('.records-table-card');

        if (!profile || !workspace) {
            return null;
        }

        const profileCopy = profile.cloneNode(true);
        const profileActions = profileCopy.querySelector('.records-profile-actions');

        if (profileActions) {
            profileActions.remove();
        }

        const managementCards = workspace.querySelectorAll('.records-management-card');
        const attendanceSummary = managementCards[1];

        const sections = [
            profileCopy.outerHTML,
            attendanceSummary ? attendanceSummary.outerHTML : '',
            tableCards[0] ? tableCards[0].outerHTML : '',
            tableCards[1] ? tableCards[1].outerHTML : ''
        ];

        return '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Student Record - ' + escapeHtml(studentName) + '</title>' +
            '<style>' +
            'body{font-family:Arial,sans-serif;color:#172033;margin:32px;font-size:12px;}' +
            '.document-header{text-align:center;border-bottom:3px solid #651b35;padding-bottom:14px;margin-bottom:22px;}' +
            '.document-header h1{color:#651b35;font-size:22px;margin:0 0 6px;}' +
            '.document-header h2{font-size:17px;margin:5px 0;}' +
            '.document-header p{margin:4px 0;color:#475569;}' +
            '.form-card{border:1px solid #cbd5e1;border-radius:8px;padding:16px;margin:0 0 16px;break-inside:avoid;}' +
            '.records-section-heading{display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:12px;}' +
            '.records-section-heading h2{font-size:16px;margin:0;color:#651b35;}' +
            '.eyebrow{font-size:10px;font-weight:bold;letter-spacing:1px;text-transform:uppercase;color:#187344;margin:0 0 5px;}' +
            '.records-student-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px;max-width:none;}' +
            '.records-student-grid .form-group,.attendance-summary-item{border:1px solid #dbe2ea;border-radius:6px;padding:10px;margin:0;background:#f8fafc;}' +
            '.records-student-grid label,.attendance-summary-item span{display:block;color:#64748b;font-size:10px;margin-bottom:5px;}' +
            '.records-student-grid strong,.attendance-summary-item strong{font-size:12px;}' +
            '.attendance-summary-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:8px;}' +
            '.attendance-summary-item strong{display:block;font-size:18px;color:#651b35;}' +
            '.attendance-total{margin:10px 0 0;}' +
            '.records-table-container{width:100%;overflow:visible;}' +
            'table{width:100%;border-collapse:collapse;font-size:10px;}' +
            'th,td{border:1px solid #cbd5e1;padding:7px;text-align:left;}' +
            'th{background:#f3e9ed;color:#651b35;}' +
            '.records-subject-name{display:block;color:#64748b;font-size:9px;margin-top:3px;}' +
            '.records-grade-cell{text-align:center;font-weight:bold;}' +
            '.records-no-grade{color:#94a3b8;}' +
            '.records-table-note{color:#64748b;font-size:10px;}' +
            '@media print{body{margin:15mm;} .form-card{break-inside:avoid;}}' +
            '</style></head><body>' +
            '<header class="document-header"><h1>' + escapeHtml(schoolName) + '</h1>' +
            '<h2>STUDENT RECORD</h2><p>Academic Year: ' + escapeHtml(schoolYear) + '</p>' +
            '<p>' + escapeHtml(studentName) + '</p></header>' +
            sections.join('') +
            '<p style="margin-top:24px;font-size:10px;color:#64748b;text-align:center;">Generated from Student Records Management</p>' +
            '</body></html>';
    }

    printButton.addEventListener('click', function () {
        const documentContent = createRecordDocument();

        if (!documentContent) {
            alert('Unable to prepare this student record.');
            return;
        }

        const printWindow = window.open('', '_blank', 'width=1000,height=750');

        if (!printWindow) {
            alert('Please allow pop-ups to print the student record.');
            return;
        }

        printWindow.document.open();
        printWindow.document.write(documentContent);
        printWindow.document.close();
        printWindow.onload = function () {
            printWindow.focus();
            printWindow.print();
        };
    });

    downloadButton.addEventListener('click', function () {
        const documentContent = createRecordDocument();

        if (!documentContent) {
            alert('Unable to prepare this student record.');
            return;
        }

        const blob = new Blob(['\ufeff', documentContent], {
            type: 'application/msword'
        });
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        const safeName = studentName.replace(/[^a-z0-9]+/gi, '_').replace(/^_+|_+$/g, '');

        link.href = url;
        link.download = 'student_record_' + safeName + '_' + schoolYear.replace(/[^a-z0-9]+/gi, '_') + '.doc';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        URL.revokeObjectURL(url);
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
