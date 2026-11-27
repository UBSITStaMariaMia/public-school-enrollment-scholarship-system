<?php
require_once __DIR__ . '/../includes/bootstrap.php';

require_role('Student');

$userId = (int) current_user()['user_id'];
$error = '';
$success = '';

/* Retrieve the existing student record. Students do not re-enter these details. */
$studentQuery = $pdo->prepare(
    'SELECT
        st.student_id,
        st.lrn,
        st.first_name,
        st.middle_name,
        st.last_name,
        st.birth_date,
        st.sex,
        st.contact_number,
        st.current_address,
        gl.grade_level,
        sec.section_name
     FROM students st
     LEFT JOIN grade_levels gl
        ON gl.grade_level_id = st.current_grade_level_id
     LEFT JOIN sections sec
        ON sec.section_id = st.current_section_id
     WHERE st.user_id = :user_id
     LIMIT 1'
);
$studentQuery->execute(['user_id' => $userId]);
$student = $studentQuery->fetch();

if (!$student) {
    http_response_code(404);
    exit('Student record not found. Please contact the school.');
}

$studentId = (int) $student['student_id'];

/* Retrieve the active school year. */
$schoolYearQuery = $pdo->prepare(
    "SELECT school_year_id, school_year
     FROM school_years
     WHERE status = 'Active'
     ORDER BY school_year_id DESC
     LIMIT 1"
);
$schoolYearQuery->execute();
$activeSchoolYear = $schoolYearQuery->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
            throw new RuntimeException('Invalid request. Please refresh the page and try again.');
        }

        if (!$activeSchoolYear) {
            throw new RuntimeException('There is currently no active school year. Please contact the school.');
        }

        $programId = filter_var($_POST['program_id'] ?? null, FILTER_VALIDATE_INT);

        if (!$programId || $programId < 1) {
            throw new RuntimeException('Please select one valid scholarship program.');
        }

        $pdo->beginTransaction();

        /* Lock this student's record so simultaneous submissions cannot create
           applications for different programs in the same school year. */
        $lockStudentQuery = $pdo->prepare(
            'SELECT student_id FROM students WHERE student_id = :student_id FOR UPDATE'
        );
        $lockStudentQuery->execute(['student_id' => $studentId]);

        /* A student may submit only one scholarship application per school year,
           regardless of which special program is selected. */
        $existingApplicationQuery = $pdo->prepare(
            'SELECT scholarship_application_id
             FROM scholarship_applications
             WHERE student_id = :student_id
               AND school_year_id = :school_year_id
             LIMIT 1'
        );
        $existingApplicationQuery->execute([
            'student_id' => $studentId,
            'school_year_id' => $activeSchoolYear['school_year_id']
        ]);

        if ($existingApplicationQuery->fetch()) {
            throw new RuntimeException('You have already submitted a scholarship application for the active school year. You may apply for only one scholarship program per school year.');
        }

        /* Confirm that the selected program is active. */
        $programQuery = $pdo->prepare(
            "SELECT program_id, program_name
             FROM special_programs
             WHERE program_id = :program_id
               AND status = 'Active'
             FOR UPDATE"
        );
        $programQuery->execute(['program_id' => $programId]);
        $program = $programQuery->fetch();

        if (!$program) {
            throw new RuntimeException('The selected scholarship program is not currently available.');
        }

        /* available_slots represents scholar slots, not the number of applicants. */
        $insertQuery = $pdo->prepare(
            "INSERT INTO scholarship_applications
                (student_id, program_id, school_year_id, application_status)
             VALUES
                (:student_id, :program_id, :school_year_id, 'Pending Review')"
        );
        $insertQuery->execute([
            'student_id' => $studentId,
            'program_id' => $programId,
            'school_year_id' => $activeSchoolYear['school_year_id']
        ]);

        $pdo->commit();
        $success = 'Your scholarship application has been submitted successfully. Please wait for the initial review.';
    } catch (PDOException $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        if ($exception->getCode() === '23000') {
            $error = 'You have already submitted a scholarship application for the active school year. You may apply for only one scholarship program per school year.';
        } else {
            $error = 'Unable to submit your application right now. Please try again later.';
            error_log('Scholarship application submission: ' . $exception->getMessage());
        }
    } catch (RuntimeException $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = $exception->getMessage();
    }
}

/* Check whether the student has already applied to any program this school year. */
$existingApplication = null;
$programs = [];

if ($activeSchoolYear) {
    $existingApplicationQuery = $pdo->prepare(
        'SELECT
            sa.scholarship_application_id,
            sa.application_status,
            sp.program_name
         FROM scholarship_applications sa
         INNER JOIN special_programs sp
            ON sp.program_id = sa.program_id
         WHERE sa.student_id = :student_id
           AND sa.school_year_id = :school_year_id
         ORDER BY sa.application_date DESC, sa.scholarship_application_id DESC
         LIMIT 1'
    );
    $existingApplicationQuery->execute([
        'student_id' => $studentId,
        'school_year_id' => $activeSchoolYear['school_year_id']
    ]);
    $existingApplication = $existingApplicationQuery->fetch() ?: null;

    $programQuery = $pdo->prepare(
        "SELECT
            sp.program_id,
            sp.program_name,
            sp.description,
            sp.available_slots
         FROM special_programs sp
         WHERE sp.status = 'Active'
         ORDER BY sp.program_name"
    );
    $programQuery->execute();
    $programs = $programQuery->fetchAll();
}

$pageTitle = 'Scholarship Application';
require_once __DIR__ . '/../includes/header.php';
?>

<style>
.scholarship-intro { margin-bottom: 22px; }
.scholarship-year-note { padding: 12px 15px; background: #f8f8fa; border-left: 4px solid var(--maroon); border-radius: 8px; margin: 18px 0; }
.scholarship-profile-summary { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; margin: 18px 0 24px; }
.scholarship-profile-item { padding: 12px 14px; background: #f8f8fa; border: 1px solid var(--line); border-radius: 8px; }
.scholarship-profile-item small { display: block; color: var(--muted); margin-bottom: 4px; }
.scholarship-profile-item strong { color: var(--ink); overflow-wrap: anywhere; }
.scholarship-program-list { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 18px; margin-top: 20px; }
.scholarship-program-option { display: block; height: 100%; padding: 20px; border: 1px solid var(--line); border-radius: 12px; background: #fff; cursor: pointer; transition: border-color .2s ease, box-shadow .2s ease; }
.scholarship-program-option:has(input:checked) { border-color: var(--maroon); box-shadow: 0 0 0 2px rgba(105, 20, 45, .10); }
.scholarship-program-option input { margin-right: 8px; accent-color: var(--maroon); }
.scholarship-program-option h3 { display: inline; color: var(--maroon); margin: 0; }
.scholarship-program-option p { margin: 13px 0; color: var(--muted); }
.program-availability { display: block; font-size: .88rem; font-weight: 600; color: var(--ink); }
.program-unavailable { opacity: .65; cursor: not-allowed; }
.scholarship-existing-application { padding: 14px 16px; margin: 18px 0; background: #f8f8fa; border-left: 4px solid var(--maroon); border-radius: 8px; }
.scholarship-existing-application p { margin: 5px 0 0; }
.scholarship-form-actions { margin-top: 22px; display: flex; flex-wrap: wrap; gap: 12px; }
@media (max-width: 700px) { .scholarship-program-list, .scholarship-profile-summary { grid-template-columns: 1fr; } }
</style>

<div class="portal-back-row">
    <a href="<?= BASE_URL ?>/student/dashboard.php" class="portal-back-link">Back to Dashboard</a>
</div>
<section class="form-card form-card-wide">
    <h1>Special Program Scholarship Application</h1>
    <p class="muted scholarship-intro">
        Apply for one of the BCNHS special program scholarships. Your existing student information is retrieved from your student record, so you do not need to enter it again.
    </p>

    <?php if ($success): ?><p class="success-message"><?= e($success) ?></p><?php endif; ?>
    <?php if ($error): ?><p class="error-message"><?= e($error) ?></p><?php endif; ?>

    <h2>Your Student Information</h2>
    <p class="muted">These details come from your registered student record. Contact the school if any information needs correction.</p>
    <div class="scholarship-profile-summary">
        <div class="scholarship-profile-item"><small>Student Name</small><strong><?= e(trim($student['first_name'] . ' ' . ($student['middle_name'] ?? '') . ' ' . $student['last_name'])) ?></strong></div>
        <div class="scholarship-profile-item"><small>LRN</small><strong><?= e($student['lrn'] ?: 'Not yet recorded') ?></strong></div>
        <div class="scholarship-profile-item"><small>Birth Date</small><strong><?= e($student['birth_date'] ?: 'Not yet recorded') ?></strong></div>
        <div class="scholarship-profile-item"><small>Sex</small><strong><?= e($student['sex'] ?: 'Not yet recorded') ?></strong></div>
        <div class="scholarship-profile-item"><small>Grade Level</small><strong><?= $student['grade_level'] !== null ? 'Grade ' . e($student['grade_level']) : 'Not yet assigned' ?></strong></div>
        <div class="scholarship-profile-item"><small>Current Section</small><strong><?= e($student['section_name'] ?: 'Not yet assigned') ?></strong></div>
        <div class="scholarship-profile-item"><small>Contact Number</small><strong><?= e($student['contact_number'] ?: 'Not yet recorded') ?></strong></div>
        <div class="scholarship-profile-item"><small>Current Address</small><strong><?= e($student['current_address'] ?: 'Not yet recorded') ?></strong></div>
    </div>

    <?php if ($activeSchoolYear): ?>
        <div class="scholarship-year-note"><strong>Active School Year:</strong> <?= e($activeSchoolYear['school_year']) ?></div>

        <?php if (empty($programs)): ?>
            <div class="empty-state"><h3>No Programs Currently Available</h3><p class="muted">There are no active scholarship programs available for application at this time.</p></div>
        <?php else: ?>
            <?php if ($existingApplication): ?>
                <div class="scholarship-existing-application">
                    <strong>You have already submitted an application for this school year.</strong>
                    <p>Selected program: <?= e($existingApplication['program_name']) ?></p>
                    <p>Application status: <?= e($existingApplication['application_status']) ?></p>
                    <p>You may apply for only one scholarship program per school year. You can check your application progress under My Applications.</p>
                </div>
            <?php endif; ?>

            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <h2>Choose a Scholarship Program</h2>
                <div class="scholarship-program-list">
                    <?php foreach ($programs as $program): ?>
                        <label class="scholarship-program-option <?= $existingApplication ? 'program-unavailable' : '' ?>">
                            <input type="radio" name="program_id" value="<?= (int) $program['program_id'] ?>" required <?= $existingApplication ? 'disabled' : '' ?>>
                            <h3><?= e($program['program_name']) ?></h3>
                            <p><?= e($program['description'] ?: 'No program description is available.') ?></p>
                            <span class="program-availability">
                                <?php if ($existingApplication): ?>Applications are unavailable because you have already applied this school year.
                                <?php elseif ($program['available_slots'] === null): ?>Applications are open.
                                <?php else: ?>Scholar slot limit: <?= (int) $program['available_slots'] ?><?php endif; ?>
                            </span>
                        </label>
                    <?php endforeach; ?>
                </div>
                <div class="scholarship-form-actions">
                    <button type="submit" class="button button-primary" <?= $existingApplication ? 'disabled' : '' ?>>Submit Application</button>
                    <a href="<?= BASE_URL ?>/student/my_applications.php" class="button button-secondary">View My Applications</a>
                </div>
                <p class="muted">You may select only one scholarship program per school year. After submission, your application will undergo an initial review. Shortlisted applicants will receive their screening schedule through the portal.</p>
            </form>
        <?php endif; ?>
    <?php else: ?>
        <div class="empty-state"><h3>No Active School Year</h3><p class="muted">Scholarship applications are currently unavailable because no school year is marked as active. Please contact the school.</p></div>
    <?php endif; ?>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
