
<?php
require_once __DIR__ . '/../includes/bootstrap.php';

require_role('Student');

$user = current_user();

$studentStmt = $pdo->prepare("
    SELECT student_id, first_name, middle_name, last_name
    FROM students
    WHERE user_id = :user_id
    LIMIT 1
");
$studentStmt->execute([
    'user_id' => $user['user_id']
]);
$student = $studentStmt->fetch();

if (!$student) {
    http_response_code(404);
    exit('Student record not found.');
}

$studentId = (int) $student['student_id'];

// Get the student's assigned subjects.
$subjectStmt = $pdo->prepare("
    SELECT
        s.subject_id,
        s.subject_code,
        s.subject_name,
        se.enrollment_status,
        sy.school_year
    FROM student_subject_enrollments se
    INNER JOIN subjects s
        ON se.subject_id = s.subject_id
    INNER JOIN school_years sy
        ON se.school_year_id = sy.school_year_id
    WHERE se.student_id = :student_id
    ORDER BY sy.school_year DESC, s.subject_name ASC
");
$subjectStmt->execute([
    'student_id' => $studentId
]);
$subjects = $subjectStmt->fetchAll();

// Get the student's grades in a quarterly summary.
$gradeStmt = $pdo->prepare("
    SELECT
        s.subject_id,
        s.subject_code,
        s.subject_name,
        sy.school_year,

        MAX(CASE
            WHEN ar.grading_period = 'First Quarter'
            THEN ar.grade
        END) AS first_quarter,

        MAX(CASE
            WHEN ar.grading_period = 'Second Quarter'
            THEN ar.grade
        END) AS second_quarter,

        MAX(CASE
            WHEN ar.grading_period = 'Third Quarter'
            THEN ar.grade
        END) AS third_quarter,

        MAX(CASE
            WHEN ar.grading_period = 'Fourth Quarter'
            THEN ar.grade
        END) AS fourth_quarter

    FROM academic_records ar
    INNER JOIN subjects s
        ON ar.subject_id = s.subject_id
    INNER JOIN school_years sy
        ON ar.school_year_id = sy.school_year_id

    WHERE ar.student_id = :student_id

    GROUP BY
        s.subject_id,
        s.subject_code,
        s.subject_name,
        sy.school_year_id,
        sy.school_year

    ORDER BY sy.school_year DESC, s.subject_name ASC
");
$gradeStmt->execute([
    'student_id' => $studentId
]);
$grades = $gradeStmt->fetchAll();

// Get the student's attendance records.
$attendanceStmt = $pdo->prepare("
    SELECT
        a.attendance_date,
        a.attendance_status,
        a.remarks,
        s.subject_code,
        s.subject_name
    FROM attendance_records a
    LEFT JOIN subjects s
        ON a.subject_id = s.subject_id
    WHERE a.student_id = :student_id
    ORDER BY a.attendance_date DESC, a.attendance_id DESC
");
$attendanceStmt->execute([
    'student_id' => $studentId
]);
$attendance = $attendanceStmt->fetchAll();

function record_status_class(string $status): string
{
    return match ($status) {
        'Enrolled', 'Present', 'Completed' => 'status-success',
        'Dropped', 'Absent' => 'status-danger',
        'Late' => 'status-warning',
        'Excused' => 'status-info',
        default => 'status-neutral',
    };
}

function display_grade(mixed $grade): string
{
    return $grade !== null
        ? number_format((float) $grade, 2)
        : '—';
}

$pageTitle = 'Student Records';

require_once __DIR__ . '/../includes/header.php';
?>



<div class="portal-back-row">
    <a href="<?= BASE_URL ?>/student/dashboard.php"
    class="portal-back-link">
    Back to Dashboard
    </a>
</div>
<main class="records-page">


    <div class="records-heading">
        <h1>Student Records</h1>
        <p>
            View your assigned subjects, academic grades,
            and attendance records.
        </p>
    </div>

    <!-- Assigned Subjects -->
    <section class="records-card">
        <h2>My Subjects</h2>

        <?php if ($subjects): ?>
            <div class="records-table-wrapper">
                <table class="records-table">
                    <thead>
                        <tr>
                            <th>Subject Code</th>
                            <th>Subject Name</th>
                            <th>School Year</th>
                            <th>Status</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php foreach ($subjects as $subject): ?>
                            <tr>
                                <td><?= e($subject['subject_code']) ?></td>
                                <td><?= e($subject['subject_name']) ?></td>
                                <td><?= e($subject['school_year']) ?></td>
                                <td>
                                    <span class="record-status <?= e(record_status_class($subject['enrollment_status'])) ?>">
                                        <?= e($subject['enrollment_status']) ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-records">
                <strong>No Assigned Subjects Yet</strong>
                <p>Your subjects will appear here once school staff assigns them.</p>
            </div>
        <?php endif; ?>
    </section>

    <!-- Academic Grades -->
    <section class="records-card">
        <h2>My Grades</h2>

        <?php if ($grades): ?>
            <div class="records-table-wrapper">
                <table class="records-table grades-table">
                    <thead>
                        <tr>
                            <th>Subject</th>
                            <th>School Year</th>
                            <th>1st Quarter</th>
                            <th>2nd Quarter</th>
                            <th>3rd Quarter</th>
                            <th>4th Quarter</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php foreach ($grades as $grade): ?>
                            <tr>
                                <td>
                                    <strong><?= e($grade['subject_code']) ?></strong><br>
                                    <?= e($grade['subject_name']) ?>
                                </td>

                                <td><?= e($grade['school_year']) ?></td>

                                <td><?= e(display_grade($grade['first_quarter'])) ?></td>

                                <td><?= e(display_grade($grade['second_quarter'])) ?></td>

                                <td><?= e(display_grade($grade['third_quarter'])) ?></td>

                                <td><?= e(display_grade($grade['fourth_quarter'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-records">
                <strong>No Grades Available</strong>
                <p>Your grades will appear here once your teachers record them.</p>
            </div>
        <?php endif; ?>
    </section>

    <!-- Attendance Records -->
    <section class="records-card">
        <h2>My Attendance</h2>

        <?php if ($attendance): ?>
            <div class="records-table-wrapper">
                <table class="records-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Subject</th>
                            <th>Attendance Status</th>
                            <th>Remarks</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php foreach ($attendance as $record): ?>
                            <tr>
                                <td>
                                    <?= e(date('M d, Y', strtotime($record['attendance_date']))) ?>
                                </td>

                                <td>
                                    <?php if ($record['subject_name']): ?>
                                        <strong><?= e($record['subject_code']) ?></strong><br>
                                        <?= e($record['subject_name']) ?>
                                    <?php else: ?>
                                        General Attendance
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <span class="record-status <?= e(record_status_class($record['attendance_status'])) ?>">
                                        <?= e($record['attendance_status']) ?>
                                    </span>
                                </td>

                                <td><?= e($record['remarks'] ?? '—') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-records">
                <strong>No Attendance Records</strong>
                <p>Your attendance information will appear here once recorded by school staff.</p>
            </div>
        <?php endif; ?>
    </section>

    <p class="records-note">
        Note: Subject assignments, grades, and attendance are managed by authorized school staff.
    </p>

</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
