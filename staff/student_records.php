
<?php
require_once __DIR__ . '/../includes/bootstrap.php';

require_role('System Administrator', 'Teacher');

$pageTitle = 'Student Records Management';

$user = current_user();
$isAdmin = $user['role_name'] === 'System Administrator';
$recordsPath = $isAdmin ? '/staff/student_records.php' : '/teacher/student_records.php';
$sectionFilter = $isAdmin ? 0 : (int) filter_var($_GET['section'] ?? 0, FILTER_VALIDATE_INT);
$sectionQuery = $sectionFilter > 0 ? 'section=' . $sectionFilter . '&' : '';

$success = $_SESSION['records_success'] ?? '';
unset($_SESSION['records_success']);

$error = '';
$gradingPeriods = [
    'First Quarter',
    'Second Quarter',
    'Third Quarter',
    'Fourth Quarter'
];

$attendanceStatuses = [
    'Present',
    'Absent',
    'Late',
    'Excused'
];

$activeSchoolYear = $pdo->query(
    "SELECT school_year_id, school_year
     FROM school_years
     WHERE status = 'Active'
     LIMIT 1"
)->fetch();

$selectedStudentId = filter_var($_GET['student_id'] ?? null, FILTER_VALIDATE_INT);

/*
|--------------------------------------------------------------------------
| Check whether a student belongs to the current user's accessible section
|--------------------------------------------------------------------------
*/

$getAccessibleStudent = function (int $studentId) use (
    $pdo,
    $user,
    $isAdmin,
    $activeSchoolYear,
    $sectionFilter
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

    if (!$student || ($sectionFilter > 0 && (int) $student['current_section_id'] !== $sectionFilter)) {
        return null;
    }

    return $student;
};

/*
|--------------------------------------------------------------------------
| Get students available to the current user
|--------------------------------------------------------------------------
*/

$students = [];

if ($activeSchoolYear) {
    if ($isAdmin) {
        $studentQuery = $pdo->prepare(
            "SELECT
                s.student_id,
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
             ORDER BY s.last_name, s.first_name"
        );

        $studentQuery->execute();
    } else {
        $studentQuery = $pdo->prepare(
            "SELECT
                s.student_id,
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
    if ($sectionFilter > 0) {
        $students = array_values(array_filter($students, static fn (array $student): bool => (int) $student['current_section_id'] === $sectionFilter));
    }
}

/*
|--------------------------------------------------------------------------
| Process grade and attendance submissions
|--------------------------------------------------------------------------
*/

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

                /*
                |----------------------------------------------------------
                | Confirm that the subject is assigned to the student
                |----------------------------------------------------------
                */

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

                    /*
                    |------------------------------------------------------
                    | Update an existing grade or insert a new one
                    |------------------------------------------------------
                    */

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

                    redirect($recordsPath . '?' . $sectionQuery . 'student_id=' . $postedStudentId);

                } elseif ($action === 'save_attendance') {
                    $attendanceDate = $_POST['attendance_date'] ?? '';
                    $attendanceStatus = $_POST['attendance_status'] ?? '';
                    $remarks = trim($_POST['attendance_remarks'] ?? '');

                    $dateParts = explode('-', $attendanceDate);

                    if (
                        count($dateParts) !== 3 ||
                        !checkdate(
                            (int) ($dateParts[1] ?? 0),
                            (int) ($dateParts[2] ?? 0),
                            (int) ($dateParts[0] ?? 0)
                        )
                    ) {
                        throw new RuntimeException('Please enter a valid attendance date.');
                    }

                    if (!in_array($attendanceStatus, $attendanceStatuses, true)) {
                        throw new RuntimeException('Please select a valid attendance status.');
                    }

                    /*
                    |------------------------------------------------------
                    | Update an existing attendance record or insert one
                    |------------------------------------------------------
                    */

                    $existingAttendanceQuery = $pdo->prepare(
                        "SELECT attendance_id
                         FROM attendance_records
                         WHERE student_id = :student_id
                         AND subject_id = :subject_id
                         AND attendance_date = :attendance_date
                         ORDER BY attendance_id DESC
                         LIMIT 1"
                    );

                    $existingAttendanceQuery->execute([
                        'student_id' => $postedStudentId,
                        'subject_id' => $subjectId,
                        'attendance_date' => $attendanceDate
                    ]);

                    $existingAttendance = $existingAttendanceQuery->fetch();

                    if ($existingAttendance) {
                        $saveAttendance = $pdo->prepare(
                            "UPDATE attendance_records
                             SET attendance_status = :attendance_status,
                                 remarks = :remarks,
                                 recorded_by = :recorded_by
                             WHERE attendance_id = :attendance_id"
                        );

                        $saveAttendance->execute([
                            'attendance_status' => $attendanceStatus,
                            'remarks' => $remarks !== '' ? $remarks : null,
                            'recorded_by' => $user['user_id'],
                            'attendance_id' => $existingAttendance['attendance_id']
                        ]);
                    } else {
                        $saveAttendance = $pdo->prepare(
                            "INSERT INTO attendance_records
                                (
                                    student_id,
                                    subject_id,
                                    attendance_date,
                                    attendance_status,
                                    remarks,
                                    recorded_by
                                )
                             VALUES
                                (
                                    :student_id,
                                    :subject_id,
                                    :attendance_date,
                                    :attendance_status,
                                    :remarks,
                                    :recorded_by
                                )"
                        );

                        $saveAttendance->execute([
                            'student_id' => $postedStudentId,
                            'subject_id' => $subjectId,
                            'attendance_date' => $attendanceDate,
                            'attendance_status' => $attendanceStatus,
                            'remarks' => $remarks !== '' ? $remarks : null,
                            'recorded_by' => $user['user_id']
                        ]);
                    }

                    $_SESSION['records_success'] = 'Attendance saved successfully.';

                    redirect($recordsPath . '?' . $sectionQuery . 'student_id=' . $postedStudentId);

                } else {
                    throw new RuntimeException('Invalid action.');
                }

            } catch (RuntimeException $exception) {
                $error = $exception->getMessage();

            } catch (PDOException $exception) {
                error_log('Staff records error: ' . $exception->getMessage());
                $error = 'Unable to save the record. Please try again.';
            }
        }
    }
}

/*
|--------------------------------------------------------------------------
| Load selected student and assigned subjects
|--------------------------------------------------------------------------
*/

$selectedStudent = null;
$assignedSubjects = [];
$gradeRecords = [];
$attendanceRecords = [];

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
             ORDER BY sub.subject_name, ar.grading_period"
        );

        $gradeQuery->execute([
            'student_id' => $selectedStudentId,
            'school_year_id' => $activeSchoolYear['school_year_id']
        ]);

        $gradeRecords = $gradeQuery->fetchAll();

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

<?php if ($sectionFilter > 0): ?>
    <div class="portal-back-row">
        <a class="portal-back-link" href="<?= BASE_URL ?>/teacher/my_sections.php">Back to assigned sections</a>
    </div>
<?php endif; ?>
<section class="form-card">
    <h1><?= $sectionFilter > 0 ? 'Class Roster' : ($isAdmin ? 'Student Records Management' : 'Grades and Attendance') ?></h1>

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
        <p class="alert alert-success" role="status"><?= e($success) ?></p>
    <?php endif; ?>

    <?php if ($error): ?>
        <p class="alert alert-error" role="alert"><?= e($error) ?></p>
    <?php endif; ?>

    <?php if (!$isAdmin): ?>
        <p class="muted">
            You can only manage students in your assigned sections.
        </p>
    <?php else: ?>
        <p class="muted">
            System Administrator access: all student sections.
        </p>
    <?php endif; ?>

    <?php if (!$students): ?>
        <p>No students are available in this view.</p>
    <?php elseif ($sectionFilter > 0): ?>
        <h2><?= e($students[0]['section_name']) ?></h2>
        <div class="table-responsive">
            <table class="portal-row-table" aria-label="Class roster">
                <thead>
                    <tr>
                        <th scope="col">Student</th>
                        <th scope="col">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($students as $student): ?>
                        <tr>
                            <td><?= e($student['last_name'] . ', ' . trim($student['first_name'] . ' ' . ($student['middle_name'] ?? ''))) ?></td>
                            <td>
                                <a class="button button-outline" href="?section=<?= $sectionFilter ?>&amp;student_id=<?= (int) $student['student_id'] ?>#student-record">
                                    View grades and attendance
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
    <form method="GET">
        <div class="form-group">
            <label for="student_id">Select Student</label>

            <select name="student_id" id="student_id" required>
                <option value="">-- Select a Student --</option>

                <?php foreach ($students as $student): ?>
                    <option
                        value="<?= (int) $student['student_id'] ?>"
                        <?= (string) $selectedStudentId === (string) $student['student_id'] ? 'selected' : '' ?>
                    >
                        <?= e($student['last_name'] . ', ' . $student['first_name']) ?>
                        - Grade <?= e($student['grade_level'] ?? 'Not assigned') ?>
                        - <?= e($student['section_name'] ?? 'No section') ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">
                View Student Records
            </button>
        </div>
    </form>
    <?php endif; ?>
</section>

<?php if ($selectedStudent): ?>

    <section class="form-card" id="student-record">
        <h2>Student Information</h2>

        <div class="form-grid">
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
                    <?= e($selectedStudent['section_name'] ?? 'Not assigned') ?>
                </strong>
            </div>
        </div>
    </section>

    <?php if ($assignedSubjects): ?>

        <section class="form-card">
            <h2>Grade Management</h2>
            <p>Enter or update the student's grade for an assigned subject.</p>

            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="save_grade">
                <input type="hidden" name="student_id" value="<?= (int) $selectedStudent['student_id'] ?>">

                <div class="form-grid">
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

                    <div class="form-group">
                        <label for="grading_period">Grading Period</label>
                        <select name="grading_period" id="grading_period" required>
                            <option value="">Select Quarter</option>

                            <?php foreach ($gradingPeriods as $period): ?>
                                <option value="<?= e($period) ?>">
                                    <?= e($period) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="grade">Grade</label>
                        <input
                            type="number"
                            name="grade"
                            id="grade"
                            min="0"
                            max="100"
                            step="0.01"
                            placeholder="Enter grade"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label for="grade_remarks">Remarks</label>
                        <input
                            type="text"
                            name="remarks"
                            id="grade_remarks"
                            placeholder="Optional remarks"
                        >
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        Save Grade
                    </button>
                </div>
            </form>
        </section>

        <section class="form-card">
            <h2>Attendance Management</h2>
            <p>Record or update the student's attendance for an assigned subject.</p>

            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="save_attendance">
                <input type="hidden" name="student_id" value="<?= (int) $selectedStudent['student_id'] ?>">

                <div class="form-grid">
                    <div class="form-group">
                        <label for="attendance_subject">Subject</label>
                        <select name="subject_id" id="attendance_subject" required>
                            <option value="">Select Subject</option>

                            <?php foreach ($assignedSubjects as $subject): ?>
                                <option value="<?= (int) $subject['subject_id'] ?>">
                                    <?= e($subject['subject_code'] . ' - ' . $subject['subject_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="attendance_date">Attendance Date</label>
                        <input
                            type="date"
                            name="attendance_date"
                            id="attendance_date"
                            value="<?= e(date('Y-m-d')) ?>"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label for="attendance_status">Attendance Status</label>
                        <select name="attendance_status" id="attendance_status" required>
                            <option value="">Select Status</option>

                            <?php foreach ($attendanceStatuses as $status): ?>
                                <option value="<?= e($status) ?>">
                                    <?= e($status) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="attendance_remarks">Remarks</label>
                        <input
                            type="text"
                            name="attendance_remarks"
                            id="attendance_remarks"
                            placeholder="Optional remarks"
                        >
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        Save Attendance
                    </button>
                </div>
            </form>
        </section>

    <?php else: ?>

        <section class="form-card">
            <h2>No Assigned Subjects</h2>
            <p>
                This student does not have any enrolled subjects for the active school year.
                Assign subjects first through Subject Management.
            </p>
        </section>

    <?php endif; ?>

    <section class="form-card">
        <h2>Grade Records</h2>

        <?php if ($gradeRecords): ?>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Subject</th>
                            <th>Grading Period</th>
                            <th>Grade</th>
                            <th>Remarks</th>
                            <th>Recorded By</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php foreach ($gradeRecords as $record): ?>
                            <tr>
                                <td>
                                    <?= e($record['subject_code'] . ' - ' . $record['subject_name']) ?>
                                </td>
                                <td><?= e($record['grading_period'] ?? '') ?></td>
                                <td><?= e((string) $record['grade']) ?></td>
                                <td><?= e($record['remarks'] ?? '') ?></td>
                                <td><?= e($record['recorded_by_name'] ?? 'Previous record') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p>No grade records found for this student.</p>
        <?php endif; ?>
    </section>

    <section class="form-card">
        <h2>Attendance Records</h2>

        <?php if ($attendanceRecords): ?>
            <div class="table-container">
                <table>
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

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
