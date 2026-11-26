
<?php
require_once __DIR__ . '/../includes/bootstrap.php';

require_role('Student');

$userId = (int) current_user()['user_id'];
$errors = [];
$success = '';

$requiredDocuments = [
    'PSA Birth Certificate',
    'Old School Card (before moving up)',
    'Good Moral Certificate'
];

$allowedTypes = [
    'Incoming Grade 7',
    'Returning',
    'Transferee'
];

/* Get the student's ID */
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

/* Get the application ID */
$applicationId = (int) (
    $_SERVER['REQUEST_METHOD'] === 'POST'
        ? ($_POST['application_id'] ?? 0)
        : ($_GET['application_id'] ?? 0)
);

if ($applicationId <= 0) {
    redirect('/student/my_applications.php');
}

/* Get this student's enrollment application only */
$applicationQuery = $pdo->prepare(
    'SELECT
        ea.application_id,
        ea.student_id,
        ea.school_year_id,
        ea.lrn,
        ea.enrollment_type,
        ea.requested_grade_level_id,
        ea.application_status,
        sy.school_year
     FROM enrollment_applications ea
     INNER JOIN school_years sy
        ON ea.school_year_id = sy.school_year_id
     WHERE ea.application_id = :application_id
       AND ea.student_id = :student_id
     LIMIT 1'
);

$applicationQuery->execute([
    'application_id' => $applicationId,
    'student_id' => $studentId
]);

$application = $applicationQuery->fetch();

if (!$application) {
    http_response_code(404);
    exit('Enrollment application not found.');
}

/* Only Pending Review applications can be edited */
$canEdit = $application['application_status'] === 'Pending Review';

/* Get grade levels */
$gradeQuery = $pdo->query(
    'SELECT grade_level_id, grade_level
     FROM grade_levels
     ORDER BY grade_level_id ASC'
);

$gradeLevels = $gradeQuery->fetchAll();

/* Get existing supporting documents */
$documentQuery = $pdo->prepare(
    'SELECT document_type, file_path, verification_status, remarks
     FROM enrollment_documents
     WHERE application_id = :application_id'
);

$documentQuery->execute([
    'application_id' => $applicationId
]);

$existingDocuments = [];

foreach ($documentQuery->fetchAll() as $document) {
    $existingDocuments[$document['document_type']] = $document;
}

/* Initial form values */
$values = [
    'lrn' => $application['lrn'] ?? '',
    'enrollment_type' => $application['enrollment_type'],
    'requested_grade_level_id' => (string) $application['requested_grade_level_id'],
    'available_documents' => []
];

/* Preselect documents already recorded for this application. */
foreach ($requiredDocuments as $index => $documentName) {
    if (isset($existingDocuments[$documentName])) {
        $values['available_documents'][] = (string) $index;
    }
}

/* Handle update */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!$canEdit) {
        $errors[] = 'This application can no longer be edited.';
    }

    $values['lrn'] = trim($_POST['lrn'] ?? '');
    $values['enrollment_type'] = trim($_POST['enrollment_type'] ?? '');
    $values['requested_grade_level_id'] = trim(
        $_POST['requested_grade_level_id'] ?? ''
    );

    $submittedDocuments = $_POST['available_documents'] ?? [];

    if (is_array($submittedDocuments)) {
        $values['available_documents'] = array_values(
            array_unique(array_map('strval', $submittedDocuments))
        );
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
    if (!in_array($values['enrollment_type'], $allowedTypes, true)) {
        $errors[] = 'Select a valid enrollment type.';
    }

    /* Validate grade level */
    $selectedGrade = null;

    foreach ($gradeLevels as $grade) {
        if ((string) $grade['grade_level_id'] === $values['requested_grade_level_id']) {
            $selectedGrade = $grade;
            break;
        }
    }

    if (!$selectedGrade) {
        $errors[] = 'Select a valid grade level.';
    } elseif ($values['enrollment_type'] === 'Incoming Grade 7' && (int) $selectedGrade['grade_level'] !== 7) {
        $errors[] = 'Incoming Grade 7 applications must request Grade 7.';
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

    /* Save updates */
    if (!$errors) {
        $removedFiles = [];

        try {
            $pdo->beginTransaction();

            /* Update enrollment information */
            $updateApplication = $pdo->prepare(
                'UPDATE enrollment_applications
                 SET lrn = :lrn,
                     enrollment_type = :enrollment_type,
                     requested_grade_level_id = :grade_level_id
                 WHERE application_id = :application_id
                   AND student_id = :student_id
                   AND application_status = :application_status'
            );

            $updateApplication->execute([
                'lrn' => $values['lrn'],
                'enrollment_type' => $values['enrollment_type'],
                'grade_level_id' => $values['requested_grade_level_id'],
                'application_id' => $applicationId,
                'student_id' => $studentId,
                'application_status' => 'Pending Review'
            ]);

            if ($updateApplication->rowCount() === 0) {
                /*
                 * A rowCount of zero can also mean that values were
                 * unchanged. Verify that the application still exists
                 * and remains editable.
                 */
                $checkStatus = $pdo->prepare(
                    'SELECT COUNT(*)
                     FROM enrollment_applications
                     WHERE application_id = :application_id
                       AND student_id = :student_id
                       AND application_status = :application_status'
                );

                $checkStatus->execute([
                    'application_id' => $applicationId,
                    'student_id' => $studentId,
                    'application_status' => 'Pending Review'
                ]);

                if ((int) $checkStatus->fetchColumn() !== 1) {
                    throw new RuntimeException('Application is no longer editable.');
                }
            }

            /*
             * Synchronize the selected supporting-document checklist.
             * Existing records that remain selected are preserved,
             * including any previous verification information.
             */
            $insertDocument = $pdo->prepare(
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

            $deleteDocument = $pdo->prepare(
                'DELETE FROM enrollment_documents
                 WHERE application_id = :application_id
                   AND document_type = :document_type'
            );

            $selectedIndexes = array_flip($values['available_documents']);

            foreach ($requiredDocuments as $index => $documentName) {
                $isSelected = isset($selectedIndexes[(string) $index]);
                $existing = $existingDocuments[$documentName] ?? null;

                if ($isSelected && !$existing) {
                    $insertDocument->execute([
                        'application_id' => $applicationId,
                        'document_type' => $documentName,
                        'verification_status' => 'Not Submitted',
                        'remarks' => 'Student indicated this document is available. No digital file was uploaded.'
                    ]);
                } elseif (!$isSelected && $existing) {
                    if (!empty($existing['file_path'])) {
                        $removedFiles[] = $existing['file_path'];
                    }

                    $deleteDocument->execute([
                        'application_id' => $applicationId,
                        'document_type' => $documentName
                    ]);
                }
            }

            $pdo->commit();

            /*
             * Remove old uploaded files only after the database update
             * succeeds, and only when they are inside uploads/enrollment.
             */
            $uploadDirectory = __DIR__ . '/../uploads/enrollment';
            $realUploadDirectory = is_dir($uploadDirectory)
                ? realpath($uploadDirectory)
                : false;

            if ($realUploadDirectory !== false) {
                foreach ($removedFiles as $removedFile) {
                    $oldAbsolutePath = realpath(
                        __DIR__ . '/../' . ltrim($removedFile, '/\\')
                    );

                    if (
                        $oldAbsolutePath !== false &&
                        str_starts_with(
                            $oldAbsolutePath,
                            $realUploadDirectory . DIRECTORY_SEPARATOR
                        ) &&
                        is_file($oldAbsolutePath)
                    ) {
                        unlink($oldAbsolutePath);
                    }
                }
            }

            redirect('/student/my_applications.php?updated=1');

        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log('Enrollment update error: ' . $exception->getMessage());

            $errors[] = 'Your application could not be updated. Please try again.';
        }
    }
}

$pageTitle = 'Edit Enrollment Application';
require_once __DIR__ . '/../includes/header.php';
?>



<div class="portal-back-row">
    <a href="<?= BASE_URL ?>/student/my_applications.php"
    class="portal-back-link">
    Back to My Applications
    </a>
</div>
<section class="form-card form-card-wide">

    <h1>Edit Enrollment Application</h1>

    <p class="muted">
        Update your enrollment information and indicate which supporting
        documents you currently have.
    </p>

    <div class="alert alert-success">
        Application #<?= $applicationId ?>
        <br>
        School Year: <?= e($application['school_year']) ?>
        <br>
        Status: <?= e($application['application_status']) ?>
    </div>

    <?php if (!$canEdit): ?>

        <div class="alert alert-error">
            This application can no longer be edited because its status is
            <?= e($application['application_status']) ?>.
        </div>


    <?php else: ?>

        <?php foreach ($errors as $message): ?>
            <div class="alert alert-error">
                <?= e($message) ?>
            </div>
        <?php endforeach; ?>

        <form method="post" class="form-grid">

            <input type="hidden"
                   name="csrf_token"
                   value="<?= e(csrf_token()) ?>">

            <input type="hidden"
                   name="application_id"
                   value="<?= $applicationId ?>">

            <h2>1. Enrollment Information</h2>

            <label>
                Learner Reference Number (LRN)
                <input type="text"
                       name="lrn"
                       maxlength="20"
                       value="<?= e($values['lrn']) ?>"
                       required>
            </label>

            <label>
                School Year
                <input type="text"
                       value="<?= e($application['school_year']) ?>"
                       readonly>
            </label>

            <label>
                Enrollment Type

                <select name="enrollment_type" required>
                    <option value="">Select enrollment type</option>

                    <?php foreach ($allowedTypes as $type): ?>
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
                        <option data-grade-level="<?= e($grade['grade_level']) ?>" value="<?= (int) $grade['grade_level_id'] ?>"
                            <?= $values['requested_grade_level_id'] === (string) $grade['grade_level_id'] ? 'selected' : '' ?>>
                            Grade <?= e($grade['grade_level']) ?>
                        </option>
                    <?php endforeach; ?>

                </select>
            </label>

            <h2>2. Supporting Documents</h2>

            <p class="muted">
                Check the supporting documents you currently have. You may leave
                documents unchecked if you do not have them yet.
            </p>

            <div class="document-checklist">
                <?php foreach ($requiredDocuments as $index => $documentName): ?>
                    <?php $isSelected = in_array((string) $index, $values['available_documents'], true); ?>

                    <div class="document-checklist-item">
                        <label class="document-checkbox">
                            <input type="checkbox"
                                   name="available_documents[]"
                                   value="<?= $index ?>"
                                   <?= $isSelected ? 'checked' : '' ?>>
                            <span><?= e($documentName) ?></span>
                        </label>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="edit-actions">

                <button type="submit" class="button button-primary">
                    Save Changes
                </button>

                <a href="<?= BASE_URL ?>/student/my_applications.php"
                   class="button button-outline">
                    Cancel
                </a>

            </div>

        </form>

    <?php endif; ?>

</section>



<?php require __DIR__ . '/../includes/enrollment_grade_selection.php'; ?>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
