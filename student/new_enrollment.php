
<?php
require_once __DIR__ . '/../includes/bootstrap.php';

require_role('Student');

$userId = (int) current_user()['user_id'];
$errors = [];
$success = '';

/* Get student information */
$studentQuery = $pdo->prepare(
    'SELECT student_id, lrn, first_name, middle_name, last_name,
            birth_date, contact_number, current_address
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

/* Get active school year */
$yearQuery = $pdo->query(
    "SELECT school_year_id, school_year
     FROM school_years
     WHERE status = 'Active'
     ORDER BY school_year_id DESC
     LIMIT 1"
);
$schoolYear = $yearQuery->fetch();

if (!$schoolYear) {
    exit('There is currently no active school year. Please contact the school administrator.');
}

/* Get available grade levels */
$gradeQuery = $pdo->query(
    'SELECT grade_level_id, grade_level
     FROM grade_levels
     ORDER BY grade_level_id ASC'
);
$gradeLevels = $gradeQuery->fetchAll();

/* Get available scholarship programs */
$programQuery = $pdo->query(
    "SELECT program_id, program_name, description
     FROM special_programs
     WHERE status = 'Active'
     ORDER BY program_name ASC"
);
$programs = $programQuery->fetchAll();

/* Required documents */
$requiredDocuments = [
    'PSA Birth Certificate',
    'Old School Card (before moving up)',
    'Good Moral Certificate'
];

/* Form values */
$values = [
    'lrn' => $student['lrn'] ?? '',
    'enrollment_type' => '',
    'requested_grade_level_id' => '',
    'scholarship' => 'No',
    'program_id' => '',
    'available_documents' => []
];

/* Handle form submission */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    foreach (['lrn', 'enrollment_type', 'requested_grade_level_id', 'scholarship', 'program_id'] as $field) {
        $values[$field] = trim($_POST[$field] ?? '');
    }

    $submittedDocuments = $_POST['available_documents'] ?? [];
    if (is_array($submittedDocuments)) {
        $values['available_documents'] = array_values(array_unique(array_map('strval', $submittedDocuments)));
    }

    /* CSRF validation */
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Your session expired. Please refresh the page and try again.';
    }

    /* Validate LRN */
    if ($values['lrn'] === '' || strlen($values['lrn']) > 20) {
        $errors[] = 'Enter a valid LRN.';
    }

    /* Validate enrollment type */
    $allowedTypes = [
        'Incoming Grade 7',
        'Returning',
        'Transferee'
    ];

    if (!in_array($values['enrollment_type'], $allowedTypes, true)) {
        $errors[] = 'Select a valid enrollment type.';
    }

    /* Validate requested grade level */
    $selectedGrade = null;

    foreach ($gradeLevels as $grade) {
        if ((string) $grade['grade_level_id'] === $values['requested_grade_level_id']) {
            $selectedGrade = $grade;
            break;
        }
    }

    if (!$selectedGrade) {
        $errors[] = 'Select a valid grade level.';
    }

    /* Validate scholarship selection */
    if (!in_array($values['scholarship'], ['Yes', 'No'], true)) {
        $errors[] = 'Select whether you are applying for a special program scholarship.';
    }

    $selectedProgram = null;

    if ($values['scholarship'] === 'Yes') {
        foreach ($programs as $program) {
            if ((string) $program['program_id'] === $values['program_id']) {
                $selectedProgram = $program;
                break;
            }
        }

        if (!$selectedProgram) {
            $errors[] = 'Select an available special program.';
        }
    }

    /* Check duplicate enrollment */
    $duplicateCheck = $pdo->prepare(
        'SELECT COUNT(*)
         FROM enrollment_applications
         WHERE student_id = :student_id
           AND school_year_id = :school_year_id'
    );

    $duplicateCheck->execute([
        'student_id' => $studentId,
        'school_year_id' => $schoolYear['school_year_id']
    ]);

    if ((int) $duplicateCheck->fetchColumn() > 0) {
        $errors[] = 'You already have an enrollment application for this school year. Please check My Applications.';
    }

    /* Validate selected supporting document checkboxes */
    foreach ($values['available_documents'] as $selectedIndex) {
        if (
            !ctype_digit($selectedIndex) ||
            !array_key_exists((int) $selectedIndex, $requiredDocuments)
        ) {
            $errors[] = 'An invalid supporting document was selected.';
        }
    }

    /* Save application */
    if (!$errors) {
        try {
            $pdo->beginTransaction();

            /* Create enrollment application */
            $applicationInsert = $pdo->prepare(
                'INSERT INTO enrollment_applications (
                    student_id,
                    school_year_id,
                    lrn,
                    enrollment_type,
                    requested_grade_level_id,
                    application_status
                ) VALUES (
                    :student_id,
                    :school_year_id,
                    :lrn,
                    :enrollment_type,
                    :grade_level_id,
                    :application_status
                )'
            );

            $applicationInsert->execute([
                'student_id' => $studentId,
                'school_year_id' => $schoolYear['school_year_id'],
                'lrn' => $values['lrn'],
                'enrollment_type' => $values['enrollment_type'],
                'grade_level_id' => $values['requested_grade_level_id'],
                'application_status' => 'Pending Review'
            ]);

            $applicationId = (int) $pdo->lastInsertId();

            /* Save supporting documents indicated as available by the student.
               No digital file is uploaded through this enrollment form. */
            $documentInsert = $pdo->prepare(
                'INSERT INTO enrollment_documents (
                    application_id,
                    document_type,
                    file_path,
                    verification_status,
                    remarks,
                    uploaded_at
                ) VALUES (
                    :application_id,
                    :document_type,
                    NULL,
                    :verification_status,
                    :remarks,
                    NULL
                )'
            );

            foreach ($values['available_documents'] as $selectedIndex) {
                $index = (int) $selectedIndex;

                $documentInsert->execute([
                    'application_id' => $applicationId,
                    'document_type' => $requiredDocuments[$index],
                    'verification_status' => 'Not Submitted',
                    'remarks' => 'Student indicated this document is available. No digital file was uploaded.'
                ]);
            }

            /* Create separate scholarship application */
            if ($values['scholarship'] === 'Yes') {
                $scholarshipInsert = $pdo->prepare(
                    'INSERT INTO scholarship_applications (
                        student_id,
                        program_id,
                        school_year_id,
                        application_status
                    ) VALUES (
                        :student_id,
                        :program_id,
                        :school_year_id,
                        :application_status
                    )'
                );

                $scholarshipInsert->execute([
                    'student_id' => $studentId,
                    'program_id' => $values['program_id'],
                    'school_year_id' => $schoolYear['school_year_id'],
                    'application_status' => 'Pending Review'
                ]);
            }

            $pdo->commit();

            $success = 'Your enrollment application has been submitted successfully.';

            if ($values['scholarship'] === 'Yes') {
                $success .= ' Your special program scholarship application has also been submitted.';
            }

        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log('Enrollment submission error: ' . $exception->getMessage());

            $errors[] = 'Your application could not be submitted. Please try again.';
        }
    }
}

$pageTitle = 'New Enrollment';
require_once __DIR__ . '/../includes/header.php';
?>



<section class="form-card form-card-wide">

    <a href="<?= BASE_URL ?>/student/dashboard.php"
       class="button button-outline applications-back-button">
        &larr; Back to Dashboard
    </a>

    <p class="eyebrow">Student Services</p>
    <h1>New Enrollment</h1>

    <p class="muted">
        Complete the enrollment form and check the supporting documents you currently have.
    </p>

    <?php if ($success): ?>
        <div class="alert alert-success">
            <?= e($success) ?>
            <br>
            <a href="<?= BASE_URL ?>/student/my_applications.php">
                View My Applications
            </a>
        </div>
    <?php endif; ?>

    <?php foreach ($errors as $message): ?>
        <div class="alert alert-error">
            <?= e($message) ?>
        </div>
    <?php endforeach; ?>

    <?php if (!$success): ?>

    <form method="post" class="form-grid">

        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

        <h2>1. Student Information</h2>

        <label>
            Full Name
            <input type="text"
                value="<?= e(trim(
                    $student['first_name'] . ' ' .
                    ($student['middle_name'] ? $student['middle_name'] . ' ' : '') .
                    $student['last_name']
                )) ?>"
                readonly>
        </label>

        <label>
            Learner Reference Number (LRN)
            <input type="text" name="lrn" maxlength="20"
                value="<?= e($values['lrn']) ?>" required>
        </label>

        <label>
            Date of Birth
            <input type="text"
                value="<?= e($student['birth_date'] ?? '') ?>"
                readonly>
        </label>

        <label>
            Contact Number
            <input type="text"
                value="<?= e($student['contact_number'] ?? '') ?>"
                readonly>
        </label>

        <label>
            Current Address
            <textarea rows="2" readonly><?= e($student['current_address'] ?? '') ?></textarea>
        </label>

        <h2>2. Enrollment Information</h2>

        <label>
            School Year
            <input type="text"
                value="<?= e($schoolYear['school_year']) ?>"
                readonly>
        </label>

        <label>
            Enrollment Type
            <select name="enrollment_type" required>
                <option value="">Select enrollment type</option>

                <?php foreach (['Incoming Grade 7', 'Returning', 'Transferee'] as $type): ?>
                    <option value="<?= e($type) ?>"
                        <?= $values['enrollment_type'] === $type ? 'selected' : '' ?>>
                        <?= e($type) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>

        <label>
            Requested Grade Level
            <select name="requested_grade_level_id" required>
                <option value="">Select grade level</option>

                <?php foreach ($gradeLevels as $grade): ?>
                    <option value="<?= (int) $grade['grade_level_id'] ?>"
                        <?= $values['requested_grade_level_id'] === (string) $grade['grade_level_id'] ? 'selected' : '' ?>>
                        Grade <?= e($grade['grade_level']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>

        <h2>3. Special Program Scholarship</h2>

        <p>
            Are you applying for a special program scholarship?
        </p>

        <div class="radio-options">

            <label class="radio-option">
                <input type="radio"
                    name="scholarship"
                    value="Yes"
                    <?= $values['scholarship'] === 'Yes' ? 'checked' : '' ?>>
                <span>Yes</span>
            </label>

            <label class="radio-option">
                <input type="radio"
                    name="scholarship"
                    value="No"
                    <?= $values['scholarship'] === 'No' ? 'checked' : '' ?>>
                <span>No</span>
            </label>

        </div>

        <div id="scholarship-fields"
            class="<?= $values['scholarship'] === 'Yes' ? '' : 'is-hidden' ?>" >

            <label>
                Select Special Program

                <select name="program_id" id="program_id">
                    <option value="">Select a program</option>

                    <?php foreach ($programs as $program): ?>
                        <option value="<?= (int) $program['program_id'] ?>"
                            <?= $values['program_id'] === (string) $program['program_id'] ? 'selected' : '' ?>>
                            <?= e($program['program_name']) ?>
                        </option>
                    <?php endforeach; ?>

                </select>
            </label>

            <p class="muted">
                Your scholarship application will be reviewed separately
                from your enrollment application.
            </p>

        </div>

        <h2>4. Required Supporting Documents</h2>

        <p class="muted">
            Check the supporting documents you currently have. You may leave
            documents unchecked if you do not have them yet.
        </p>

        <div class="document-checklist">
            <?php foreach ($requiredDocuments as $index => $document): ?>
                <?php $isSelected = in_array((string) $index, $values['available_documents'], true); ?>

                <div class="document-checklist-item">
                    <label class="document-checkbox">
                        <input type="checkbox"
                            name="available_documents[]"
                            value="<?= $index ?>"
                            <?= $isSelected ? 'checked' : '' ?>>
                        <span><?= e($document) ?></span>
                    </label>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="form-actions">
            <button type="submit" class="button button-primary">
                Submit Enrollment Application
            </button>
        </div>

    </form>

    <?php endif; ?>

</section>

<script>
document.addEventListener('DOMContentLoaded', function () {

    const scholarshipOptions = document.querySelectorAll(
        'input[name="scholarship"]'
    );

    const scholarshipFields = document.getElementById(
        'scholarship-fields'
    );

    const programSelect = document.getElementById('program_id');

    function updateScholarshipFields() {

        const selected = document.querySelector(
            'input[name="scholarship"]:checked'
        );

        const showFields = selected && selected.value === 'Yes';

        scholarshipFields.style.display = showFields ? 'block' : 'none';

        if (programSelect) {
            programSelect.required = !!showFields;
        }
    }

    scholarshipOptions.forEach(function (option) {
        option.addEventListener('change', updateScholarshipFields);
    });

    updateScholarshipFields();


});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
