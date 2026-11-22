<?php
require_once __DIR__ . '/../includes/bootstrap.php';

require_role('Student');

$userId = (int) current_user()['user_id'];
$error = '';
$success = '';

/* Get the student's record */
$studentQuery = $pdo->prepare(
    'SELECT student_id
     FROM students
     WHERE user_id = :user_id
     LIMIT 1'
);
$studentQuery->execute(['user_id' => $userId]);
$student = $studentQuery->fetch();

if (!$student) {
    http_response_code(404);
    exit('Student record not found.');
}

$studentId = (int) $student['student_id'];

/* Get the active school year */
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

        $programId = filter_var(
            $_POST['program_id'] ?? null,
            FILTER_VALIDATE_INT
        );

        if (!$programId || $programId < 1) {
            throw new RuntimeException('Please select a valid special program.');
        }

        $pdo->beginTransaction();

        /* Lock the selected program while checking its availability */
        $programQuery = $pdo->prepare(
            "SELECT program_id, program_name, available_slots
             FROM special_programs
             WHERE program_id = :program_id
               AND status = 'Active'
             FOR UPDATE"
        );
        $programQuery->execute(['program_id' => $programId]);
        $program = $programQuery->fetch();

        if (!$program) {
            throw new RuntimeException('The selected program is not available. Please choose another program.');
        }

        /* Check the number of applications for this program and school year */
        $countQuery = $pdo->prepare(
            'SELECT COUNT(*)
             FROM scholarship_applications
             WHERE program_id = :program_id
               AND school_year_id = :school_year_id'
        );
        $countQuery->execute([
            'program_id' => $programId,
            'school_year_id' => $activeSchoolYear['school_year_id']
        ]);
        $applicationCount = (int) $countQuery->fetchColumn();

        if (
            $program['available_slots'] !== null
            && $applicationCount >= (int) $program['available_slots']
        ) {
            throw new RuntimeException('This special program has reached its available application slots.');
        }

        /* Prevent the student from applying twice for the same program and school year */
        $duplicateQuery = $pdo->prepare(
            'SELECT scholarship_application_id
             FROM scholarship_applications
             WHERE student_id = :student_id
               AND program_id = :program_id
               AND school_year_id = :school_year_id
             LIMIT 1'
        );
        $duplicateQuery->execute([
            'student_id' => $studentId,
            'program_id' => $programId,
            'school_year_id' => $activeSchoolYear['school_year_id']
        ]);

        if ($duplicateQuery->fetch()) {
            throw new RuntimeException('You have already applied for this program for the active school year.');
        }

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
        $success = 'Your special program application has been submitted successfully.';
    } catch (PDOException $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        if ($exception->getCode() === '23000') {
            $error = 'You have already applied for this program for the active school year.';
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

/* Get active programs and application counts for the active school year */
$programs = [];

if ($activeSchoolYear) {
    $programQuery = $pdo->prepare(
        "SELECT
            sp.program_id,
            sp.program_name,
            sp.description,
            sp.available_slots,
            (
                SELECT COUNT(*)
                FROM scholarship_applications sa
                WHERE sa.program_id = sp.program_id
                  AND sa.school_year_id = :school_year_id
            ) AS application_count,
            (
                SELECT COUNT(*)
                FROM scholarship_applications mine
                WHERE mine.program_id = sp.program_id
                  AND mine.school_year_id = :student_school_year_id
                  AND mine.student_id = :student_id
            ) AS already_applied
         FROM special_programs sp
         WHERE sp.status = 'Active'
         ORDER BY sp.program_name"
    );
    $programQuery->execute([
        'school_year_id' => $activeSchoolYear['school_year_id'],
        'student_school_year_id' => $activeSchoolYear['school_year_id'],
        'student_id' => $studentId
    ]);
    $programs = $programQuery->fetchAll();
}

$pageTitle = 'Scholarship Application';
require_once __DIR__ . '/../includes/header.php';
?>

<style>
.scholarship-intro {
    margin-bottom: 22px;
}

.scholarship-year-note {
    padding: 12px 15px;
    background: #f8f8fa;
    border-left: 4px solid var(--maroon);
    border-radius: 8px;
    margin: 18px 0;
}

.scholarship-program-list {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 18px;
    margin-top: 20px;
}

.scholarship-program-option {
    display: block;
    height: 100%;
    padding: 20px;
    border: 1px solid var(--line);
    border-radius: 12px;
    background: #fff;
    cursor: pointer;
    transition: border-color .2s ease, box-shadow .2s ease;
}

.scholarship-program-option:has(input:checked) {
    border-color: var(--maroon);
    box-shadow: 0 0 0 2px rgba(105, 20, 45, .10);
}

.scholarship-program-option input {
    margin-right: 8px;
    accent-color: var(--maroon);
}

.scholarship-program-option h3 {
    display: inline;
    color: var(--maroon);
    margin: 0;
}

.scholarship-program-option p {
    margin: 13px 0;
    color: var(--muted);
}

.program-availability {
    display: block;
    font-size: .88rem;
    font-weight: 600;
    color: var(--ink);
}

.program-unavailable {
    opacity: .65;
    cursor: not-allowed;
}

.scholarship-form-actions {
    margin-top: 22px;
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
}

@media (max-width: 700px) {
    .scholarship-program-list {
        grid-template-columns: 1fr;
    }
}
</style>

<section class="form-card form-card-wide">

    <a href="<?= BASE_URL ?>/student/dashboard.php"
       class="button button-outline applications-back-button">
        &larr; Back to Dashboard
    </a>

    <p class="eyebrow">Student Services</p>
    <h1>Special Program Scholarship Application</h1>

    <p class="muted scholarship-intro">
        Choose an available special program to submit your application.
        Your application will be reviewed separately from your enrollment application.
    </p>

    <?php if ($success): ?>
        <p class="success-message"><?= e($success) ?></p>
    <?php endif; ?>

    <?php if ($error): ?>
        <p class="error-message"><?= e($error) ?></p>
    <?php endif; ?>

    <?php if ($activeSchoolYear): ?>

        <div class="scholarship-year-note">
            <strong>Active School Year:</strong>
            <?= e($activeSchoolYear['school_year']) ?>
        </div>

        <?php if (empty($programs)): ?>

            <div class="empty-state">
                <h3>No Programs Currently Available</h3>
                <p class="muted">
                    There are no active special programs available for application at this time.
                </p>
            </div>

        <?php else: ?>

            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

                <h2>Available Special Programs</h2>

                <div class="scholarship-program-list">

                    <?php foreach ($programs as $program): ?>
                        <?php
                        $hasApplied = (int) $program['already_applied'] > 0;
                        $isFull = $program['available_slots'] !== null
                            && (int) $program['application_count'] >= (int) $program['available_slots'];
                        $disabled = $hasApplied || $isFull;
                        ?>

                        <label class="scholarship-program-option <?= $disabled ? 'program-unavailable' : '' ?>">

                            <input
                                type="radio"
                                name="program_id"
                                value="<?= (int) $program['program_id'] ?>"
                                required
                                <?= $disabled ? 'disabled' : '' ?>
                            >

                            <h3><?= e($program['program_name']) ?></h3>

                            <p>
                                <?= e($program['description'] ?: 'No program description is available.') ?>
                            </p>

                            <span class="program-availability">
                                <?php if ($hasApplied): ?>
                                    You have already applied for this school year.
                                <?php elseif ($isFull): ?>
                                    No available slots.
                                <?php elseif ($program['available_slots'] === null): ?>
                                    Applications: <?= (int) $program['application_count'] ?> · No slot limit
                                <?php else: ?>
                                    Available slots:
                                    <?= max(0, (int) $program['available_slots'] - (int) $program['application_count']) ?>
                                <?php endif; ?>
                            </span>

                        </label>
                    <?php endforeach; ?>

                </div>

                <div class="scholarship-form-actions">
                    <button
                        type="submit"
                        class="button button-primary"
                        <?= count(array_filter($programs, static function ($program) {
                            return (int) $program['already_applied'] === 0
                                && (
                                    $program['available_slots'] === null
                                    || (int) $program['application_count'] < (int) $program['available_slots']
                                );
                        })) === 0 ? 'disabled' : '' ?>
                    >
                        Submit Application
                    </button>

                    <a href="<?= BASE_URL ?>/student/my_applications.php"
                       class="button button-secondary">
                        View My Applications
                    </a>
                </div>

                <p class="muted">
                    Once submitted, your application status will be set to Pending Review.
                    You can monitor its progress under My Applications.
                </p>
            </form>

        <?php endif; ?>

    <?php else: ?>

        <div class="empty-state">
            <h3>No Active School Year</h3>
            <p class="muted">
                Scholarship applications are currently unavailable because no school year is marked as active.
                Please contact the school for assistance.
            </p>
        </div>

    <?php endif; ?>

</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
