
<?php
require_once __DIR__ . '/../includes/bootstrap.php';

require_role('System Administrator', 'Teacher');

$pageTitle = 'Subject Management';
$user = current_user();

$success = '';
$error = '';

$activeSchoolYear = $pdo->query(
    "SELECT school_year_id, school_year
     FROM school_years
     WHERE status = 'Active'
     LIMIT 1"
)->fetch();

$students = $pdo->query(
    "SELECT
        s.student_id,
        s.lrn,
        s.first_name,
        s.middle_name,
        s.last_name,
        g.grade_level
     FROM students s
     INNER JOIN grade_levels g
        ON s.current_grade_level_id = g.grade_level_id
     ORDER BY s.last_name, s.first_name"
)->fetchAll();

$selectedStudentId = filter_input(
    INPUT_GET,
    'student_id',
    FILTER_VALIDATE_INT
);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    $postedStudentId = filter_input(
        INPUT_POST,
        'student_id',
        FILTER_VALIDATE_INT
    );

    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $error = 'Invalid request. Please refresh the page.';

    } elseif (!$activeSchoolYear) {
        $error = 'There is no active school year.';

    } elseif (!$postedStudentId) {
        $error = 'Please select a valid student.';

    } else {
        $studentCheck = $pdo->prepare(
            "SELECT student_id, current_grade_level_id
             FROM students
             WHERE student_id = :student_id"
        );

        $studentCheck->execute([
            'student_id' => $postedStudentId
        ]);

        $studentForAction = $studentCheck->fetch();

        if (!$studentForAction || !$studentForAction['current_grade_level_id']) {
            $error = 'Student not found or has no assigned grade level.';

        } else {
            try {
                if ($action === 'assign_subjects') {
                    $subjectIds = $_POST['subject_ids'] ?? [];

                    if (!is_array($subjectIds) || empty($subjectIds)) {
                        throw new RuntimeException(
                            'Please select at least one subject.'
                        );
                    }

                    $pdo->beginTransaction();

                    $subjectCheck = $pdo->prepare(
                        "SELECT subject_id
                         FROM subjects
                         WHERE subject_id = :subject_id
                         AND grade_level_id = :grade_level_id"
                    );

                    $existingCheck = $pdo->prepare(
                        "SELECT subject_enrollment_id
                         FROM student_subject_enrollments
                         WHERE student_id = :student_id
                         AND subject_id = :subject_id
                         AND school_year_id = :school_year_id"
                    );

                    $reactivate = $pdo->prepare(
                        "UPDATE student_subject_enrollments
                         SET enrollment_status = 'Enrolled',
                             assigned_by = :assigned_by
                         WHERE student_id = :student_id
                         AND subject_id = :subject_id
                         AND school_year_id = :school_year_id"
                    );

                    $insert = $pdo->prepare(
                        "INSERT INTO student_subject_enrollments
                         (student_id, subject_id, school_year_id,
                          enrollment_status, assigned_by)
                         VALUES
                         (:student_id, :subject_id, :school_year_id,
                          'Enrolled', :assigned_by)"
                    );

                    $processedSubjects = [];

                    foreach ($subjectIds as $subjectId) {
                        $subjectId = filter_var(
                            $subjectId,
                            FILTER_VALIDATE_INT
                        );

                        if (
                            !$subjectId ||
                            in_array($subjectId, $processedSubjects, true)
                        ) {
                            throw new RuntimeException(
                                'Invalid or duplicate subject selection.'
                            );
                        }

                        $processedSubjects[] = $subjectId;

                        $subjectCheck->execute([
                            'subject_id' => $subjectId,
                            'grade_level_id' => $studentForAction['current_grade_level_id']
                        ]);

                        if (!$subjectCheck->fetch()) {
                            throw new RuntimeException(
                                'Selected subjects must match the student grade level.'
                            );
                        }

                        $existingCheck->execute([
                            'student_id' => $postedStudentId,
                            'subject_id' => $subjectId,
                            'school_year_id' => $activeSchoolYear['school_year_id']
                        ]);

                        $existing = $existingCheck->fetch();

                        if ($existing) {
                            $reactivate->execute([
                                'assigned_by' => $user['user_id'],
                                'student_id' => $postedStudentId,
                                'subject_id' => $subjectId,
                                'school_year_id' => $activeSchoolYear['school_year_id']
                            ]);
                        } else {
                            $insert->execute([
                                'student_id' => $postedStudentId,
                                'subject_id' => $subjectId,
                                'school_year_id' => $activeSchoolYear['school_year_id'],
                                'assigned_by' => $user['user_id']
                            ]);
                        }
                    }

                    $pdo->commit();

                    $success = 'Selected subjects were assigned successfully.';
                    $selectedStudentId = $postedStudentId;

                } elseif ($action === 'update_status') {
                    $assignmentId = filter_var(
                        $_POST['assignment_id'] ?? null,
                        FILTER_VALIDATE_INT
                    );

                    $newStatus = $_POST['enrollment_status'] ?? '';

                    $allowedStatuses = [
                        'Enrolled',
                        'Dropped',
                        'Completed'
                    ];

                    if (
                        !$assignmentId ||
                        !in_array($newStatus, $allowedStatuses, true)
                    ) {
                        throw new RuntimeException(
                            'Invalid assignment or status.'
                        );
                    }

                    $update = $pdo->prepare(
                        "UPDATE student_subject_enrollments
                         SET enrollment_status = :status
                         WHERE subject_enrollment_id = :assignment_id
                         AND student_id = :student_id
                         AND school_year_id = :school_year_id"
                    );

                    $update->execute([
                        'status' => $newStatus,
                        'assignment_id' => $assignmentId,
                        'student_id' => $postedStudentId,
                        'school_year_id' => $activeSchoolYear['school_year_id']
                    ]);

                    $success = 'Subject assignment status updated.';
                    $selectedStudentId = $postedStudentId;

                } else {
                    throw new RuntimeException('Invalid action.');
                }

            } catch (RuntimeException $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                $error = $exception->getMessage();

            } catch (PDOException $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                error_log(
                    'Subject management error: ' . $exception->getMessage()
                );

                $error = 'Unable to process the subject assignment. Please try again.';
            }
        }
    }

    if (!empty($postedStudentId)) {
        $selectedStudentId = $postedStudentId;
    }
}

$selectedStudent = null;
$availableSubjects = [];
$assignments = [];

if ($selectedStudentId) {
    $studentQuery = $pdo->prepare(
        "SELECT
            s.student_id,
            s.lrn,
            s.first_name,
            s.middle_name,
            s.last_name,
            s.current_grade_level_id,
            g.grade_level
         FROM students s
         LEFT JOIN grade_levels g
            ON s.current_grade_level_id = g.grade_level_id
         WHERE s.student_id = :student_id"
    );

    $studentQuery->execute([
        'student_id' => $selectedStudentId
    ]);

    $selectedStudent = $studentQuery->fetch();

    if (
        $selectedStudent &&
        $selectedStudent['current_grade_level_id'] &&
        $activeSchoolYear
    ) {
        $availableQuery = $pdo->prepare(
            "SELECT
                sub.subject_id,
                sub.subject_code,
                sub.subject_name,
                existing.enrollment_status
             FROM subjects sub
             LEFT JOIN student_subject_enrollments existing
                ON existing.subject_id = sub.subject_id
                AND existing.student_id = :student_id
                AND existing.school_year_id = :school_year_id
             WHERE sub.grade_level_id = :grade_level_id
             AND (
                existing.subject_enrollment_id IS NULL
                OR existing.enrollment_status = 'Dropped'
             )
             ORDER BY sub.subject_name"
        );

        $availableQuery->execute([
            'student_id' => $selectedStudentId,
            'school_year_id' => $activeSchoolYear['school_year_id'],
            'grade_level_id' => $selectedStudent['current_grade_level_id']
        ]);

        $availableSubjects = $availableQuery->fetchAll();

        $assignmentQuery = $pdo->prepare(
            "SELECT
                sse.subject_enrollment_id,
                sse.enrollment_status,
                sse.assigned_at,
                sub.subject_code,
                sub.subject_name
             FROM student_subject_enrollments sse
             INNER JOIN subjects sub
                ON sse.subject_id = sub.subject_id
             WHERE sse.student_id = :student_id
             AND sse.school_year_id = :school_year_id
             ORDER BY sub.subject_name"
        );

        $assignmentQuery->execute([
            'student_id' => $selectedStudentId,
            'school_year_id' => $activeSchoolYear['school_year_id']
        ]);

        $assignments = $assignmentQuery->fetchAll();

    } elseif (!$selectedStudent) {
        $error = 'Selected student was not found.';
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<section class="form-card subject-management-card">
    <h1>Subject Management</h1>

    <?php if ($success): ?>
        <p class="alert alert-success"><?= e($success) ?></p>
    <?php endif; ?>

    <?php if ($error): ?>
        <p class="alert alert-error"><?= e($error) ?></p>
    <?php endif; ?>

    <?php if (!$activeSchoolYear): ?>
        <p class="alert alert-error">
            No active school year is currently configured.
        </p>
    <?php else: ?>
        <p>
            <strong>Active School Year:</strong>
            <?= e($activeSchoolYear['school_year']) ?>
        </p>
    <?php endif; ?>

    <form method="GET" class="student-selection-form">
        <div class="form-group">
            <label for="student_id">Select Student</label>

            <select name="student_id" id="student_id" required>
                <option value="">-- Select a Student --</option>

                <?php foreach ($students as $student): ?>
                    <option
                        value="<?= (int) $student['student_id'] ?>"
                        <?= (string) $selectedStudentId === (string) $student['student_id'] ? 'selected' : '' ?>>
                        <?= e($student['last_name'] . ', ' . $student['first_name']) ?>
                        - Grade <?= e($student['grade_level']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <button type="submit" class="subject-primary-button">
            View Student Subjects
        </button>
    </form>
</section>

<?php if ($selectedStudent): ?>

    <section class="form-card subject-management-card">
        <h2>Student Information</h2>

        <div class="student-information-grid">
            <div>
                <span class="subject-info-label">Student Name</span>
                <strong>
                    <?= e(
                        $selectedStudent['first_name'] . ' ' .
                        ($selectedStudent['middle_name'] ?? '') . ' ' .
                        $selectedStudent['last_name']
                    ) ?>
                </strong>
            </div>

            <div>
                <span class="subject-info-label">LRN</span>
                <strong>
                    <?= e($selectedStudent['lrn'] ?? 'Not provided') ?>
                </strong>
            </div>

            <div>
                <span class="subject-info-label">Grade Level</span>
                <strong>
                    <?= e($selectedStudent['grade_level'] ?? 'Not assigned') ?>
                </strong>
            </div>
        </div>
    </section>

    <?php if ($activeSchoolYear && $selectedStudent['current_grade_level_id']): ?>

        <section class="form-card subject-management-card">
            <h2>Assign Subjects</h2>

            <p class="muted">
                Available subjects for Grade
                <?= e($selectedStudent['grade_level']) ?>.
            </p>

            <?php if ($availableSubjects): ?>
                <form method="POST" class="subject-assignment-form">
                    <input type="hidden" name="csrf_token"
                           value="<?= e(csrf_token()) ?>">

                    <input type="hidden" name="action"
                           value="assign_subjects">

                    <input type="hidden" name="student_id"
                           value="<?= (int) $selectedStudent['student_id'] ?>">

                    <div class="subject-checklist">
                        <?php foreach ($availableSubjects as $subject): ?>
                            <label class="subject-checklist-item">
                                <input
                                    type="checkbox"
                                    name="subject_ids[]"
                                    value="<?= (int) $subject['subject_id'] ?>">

                                <span>
                                    <strong><?= e($subject['subject_code']) ?></strong>
                                    <span><?= e($subject['subject_name']) ?></span>

                                    <?php if ($subject['enrollment_status'] === 'Dropped'): ?>
                                        <small>Previously dropped - available for reassignment</small>
                                    <?php endif; ?>
                                </span>
                            </label>
                        <?php endforeach; ?>
                    </div>

                    <button type="submit" class="subject-primary-button">
                        Assign Selected Subjects
                    </button>
                </form>
            <?php else: ?>
                <p class="subject-empty-message">
                    There are no available subjects to assign for this student.
                </p>
            <?php endif; ?>
        </section>

    <?php endif; ?>

    <section class="form-card subject-management-card">
        <h2>Currently Assigned Subjects</h2>

        <p class="muted">
            Manage the enrollment status of each assigned subject.
        </p>

        <?php if ($assignments): ?>
            <div class="table-container subject-table-container">
                <table class="subject-assignment-table">
                    <thead>
                        <tr>
                            <th>Subject Code</th>
                            <th>Subject Name</th>
                            <th>Current Status</th>
                            <th>Manage Status</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php foreach ($assignments as $assignment): ?>
                            <tr>
                                <td>
                                    <strong><?= e($assignment['subject_code']) ?></strong>
                                </td>

                                <td><?= e($assignment['subject_name']) ?></td>

                                <td>
                                    <span class="subject-status-text">
                                        <?= e($assignment['enrollment_status']) ?>
                                    </span>
                                </td>

                                <td>
                                    <form method="POST" class="subject-status-form">
                                        <input type="hidden" name="csrf_token"
                                               value="<?= e(csrf_token()) ?>">

                                        <input type="hidden" name="action"
                                               value="update_status">

                                        <input type="hidden" name="student_id"
                                               value="<?= (int) $selectedStudent['student_id'] ?>">

                                        <input type="hidden" name="assignment_id"
                                               value="<?= (int) $assignment['subject_enrollment_id'] ?>">

                                        <div class="subject-status-controls">
                                            <select
                                                name="enrollment_status"
                                                class="subject-status-select"
                                                required>
                                                <option value="Enrolled"
                                                    <?= $assignment['enrollment_status'] === 'Enrolled' ? 'selected' : '' ?>>
                                                    Enrolled
                                                </option>

                                                <option value="Dropped"
                                                    <?= $assignment['enrollment_status'] === 'Dropped' ? 'selected' : '' ?>>
                                                    Dropped
                                                </option>

                                                <option value="Completed"
                                                    <?= $assignment['enrollment_status'] === 'Completed' ? 'selected' : '' ?>>
                                                    Completed
                                                </option>
                                            </select>

                                            <button
                                                type="submit"
                                                class="subject-save-button">
                                                Save
                                            </button>
                                        </div>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="subject-empty-message">
                No subject assignments found for this student.
            </p>
        <?php endif; ?>
    </section>

<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
