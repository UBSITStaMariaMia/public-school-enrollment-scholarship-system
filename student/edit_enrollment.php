
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

$allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png'];
$allowedMimeTypes = ['application/pdf', 'image/jpeg', 'image/png'];
$maxFileSize = 5 * 1024 * 1024;

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
    'upload_documents' => []
];

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

    $submittedDocuments = $_POST['upload_documents'] ?? [];

    if (is_array($submittedDocuments)) {
        $values['upload_documents'] = array_values(
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
    $validGrade = false;

    foreach ($gradeLevels as $grade) {
        if ((string) $grade['grade_level_id'] === $values['requested_grade_level_id']) {
            $validGrade = true;
            break;
        }
    }

    if (!$validGrade) {
        $errors[] = 'Select a valid grade level.';
    }

    /* Validate selected document uploads */
    $uploadedFiles = [];

    foreach ($values['upload_documents'] as $selectedIndex) {

        if (
            !ctype_digit($selectedIndex) ||
            !array_key_exists((int) $selectedIndex, $requiredDocuments)
        ) {
            $errors[] = 'An invalid supporting document was selected.';
            continue;
        }

        $index = (int) $selectedIndex;
        $documentName = $requiredDocuments[$index];

        $file = $_FILES['documents']['tmp_name'][$index] ?? '';
        $fileName = $_FILES['documents']['name'][$index] ?? '';
        $fileSize = (int) ($_FILES['documents']['size'][$index] ?? 0);
        $fileError = $_FILES['documents']['error'][$index] ?? UPLOAD_ERR_NO_FILE;

        if ($fileError !== UPLOAD_ERR_OK || !is_uploaded_file($file)) {
            $errors[] = 'Please choose a file for ' . $documentName . '.';
            continue;
        }

        if ($fileSize > $maxFileSize) {
            $errors[] = $documentName . ' must not exceed 5 MB.';
            continue;
        }

        $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        if (!in_array($extension, $allowedExtensions, true)) {
            $errors[] = $documentName . ' must be a PDF, JPG, JPEG, or PNG file.';
            continue;
        }

        $fileInfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $fileInfo->file($file);

        if (!in_array($mimeType, $allowedMimeTypes, true)) {
            $errors[] = $documentName . ' has an invalid file type.';
            continue;
        }

        $uploadedFiles[$index] = [
            'tmp_name' => $file,
            'extension' => $extension,
            'document_type' => $documentName
        ];
    }

    /* Save updates */
    if (!$errors) {

        $uploadDirectory = __DIR__ . '/../uploads/enrollment';

        if (
            !empty($uploadedFiles) &&
            !is_dir($uploadDirectory) &&
            !mkdir($uploadDirectory, 0755, true)
        ) {
            $errors[] = 'The document upload folder could not be created.';
        } else {

            $newFiles = [];
            $oldFiles = [];

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

                /* Insert new documents or replace existing ones */
                foreach ($uploadedFiles as $file) {

                    $storedName = bin2hex(random_bytes(16))
                        . '.'
                        . $file['extension'];

                    $destination = $uploadDirectory . '/' . $storedName;

                    if (!move_uploaded_file($file['tmp_name'], $destination)) {
                        throw new RuntimeException('A document could not be uploaded.');
                    }

                    $newFiles[] = $destination;

                    $relativePath = 'uploads/enrollment/' . $storedName;

                    $findDocument = $pdo->prepare(
                        'SELECT file_path
                         FROM enrollment_documents
                         WHERE application_id = :application_id
                           AND document_type = :document_type
                         LIMIT 1'
                    );

                    $findDocument->execute([
                        'application_id' => $applicationId,
                        'document_type' => $file['document_type']
                    ]);

                    $existing = $findDocument->fetch();

                    if ($existing) {

                        if (!empty($existing['file_path'])) {
                            $oldFiles[] = $existing['file_path'];
                        }

                        $saveDocument = $pdo->prepare(
                            'UPDATE enrollment_documents
                             SET file_path = :file_path,
                                 verification_status = :verification_status,
                                 remarks = NULL,
                                 uploaded_at = NOW()
                             WHERE application_id = :application_id
                               AND document_type = :document_type'
                        );

                    } else {

                        $saveDocument = $pdo->prepare(
                            'INSERT INTO enrollment_documents (
                                application_id,
                                document_type,
                                file_path,
                                verification_status,
                                uploaded_at
                             ) VALUES (
                                :application_id,
                                :document_type,
                                :file_path,
                                :verification_status,
                                NOW()
                             )'
                        );
                    }

                    $saveDocument->execute([
                        'application_id' => $applicationId,
                        'document_type' => $file['document_type'],
                        'file_path' => $relativePath,
                        'verification_status' => 'Submitted'
                    ]);
                }

                $pdo->commit();

                /*
                 * Delete replaced files only after the database update
                 * succeeds. Only files inside uploads/enrollment are removed.
                 */
                $realUploadDirectory = realpath($uploadDirectory);

                if ($realUploadDirectory !== false) {
                    foreach ($oldFiles as $oldFile) {

                        $oldAbsolutePath = realpath(
                            __DIR__ . '/../' . ltrim($oldFile, '/\\')
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

                foreach ($newFiles as $newFile) {
                    if (is_file($newFile)) {
                        unlink($newFile);
                    }
                }

                error_log('Enrollment update error: ' . $exception->getMessage());

                $errors[] = 'Your application could not be updated. Please try again.';
            }
        }
    }
}

$pageTitle = 'Edit Enrollment Application';
require_once __DIR__ . '/../includes/header.php';
?>



<section class="form-card form-card-wide">

    <p class="eyebrow">Student Services</p>
    <h1>Edit Enrollment Application</h1>

    <p class="muted">
        Update your enrollment information and upload missing or replacement
        supporting documents.
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

        <a href="<?= BASE_URL ?>/student/my_applications.php"
           class="button button-primary">
            Back to My Applications
        </a>

    <?php else: ?>

        <?php foreach ($errors as $message): ?>
            <div class="alert alert-error">
                <?= e($message) ?>
            </div>
        <?php endforeach; ?>

        <form method="post"
              enctype="multipart/form-data"
              class="form-grid">

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
                        <option value="<?= (int) $grade['grade_level_id'] ?>"
                            <?= $values['requested_grade_level_id'] === (string) $grade['grade_level_id'] ? 'selected' : '' ?>>
                            Grade <?= e($grade['grade_level']) ?>
                        </option>
                    <?php endforeach; ?>

                </select>
            </label>

            <h2>2. Supporting Documents</h2>

            <p class="muted">
                Your existing files will remain saved unless you choose to
                upload a replacement. You can also upload documents that
                you have not submitted yet.
                Accepted formats: PDF, JPG, JPEG, and PNG.
                Maximum file size: 5 MB each.
            </p>

            <?php foreach ($requiredDocuments as $index => $documentName): ?>

                <?php $existing = $existingDocuments[$documentName] ?? null; ?>

                <div class="edit-document">

                    <label>
                        <input type="checkbox"
                               name="upload_documents[]"
                               value="<?= $index ?>"
                               data-document-index="<?= $index ?>">

                        <span>
                            <?= $existing ? 'Replace or upload: ' : 'Upload: ' ?>
                            <?= e($documentName) ?>
                        </span>
                    </label>

                    <?php if ($existing): ?>

                        <div class="current-file">
                            Current status:
                            <strong><?= e($existing['verification_status']) ?></strong>
                            <br>

                            <a href="<?= BASE_URL . '/' . e(ltrim($existing['file_path'], '/')) ?>"
                               target="_blank"
                               rel="noopener">
                                View Current File
                            </a>
                        </div>

                    <?php else: ?>

                        <p class="current-file">Not submitted yet.</p>

                    <?php endif; ?>

                    <div id="edit-upload-<?= $index ?>"
                         class="edit-upload"
                         class="is-hidden">

                        <label for="edit-file-<?= $index ?>">
                            Choose File
                        </label>

                        <input type="file"
                               id="edit-file-<?= $index ?>"
                               name="documents[<?= $index ?>]"
                               accept=".pdf,.jpg,.jpeg,.png">

                    </div>

                </div>

            <?php endforeach; ?>

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

<script>
document.addEventListener('DOMContentLoaded', function () {

    const documentCheckboxes = document.querySelectorAll(
        'input[name="upload_documents[]"]'
    );

    documentCheckboxes.forEach(function (checkbox) {

        checkbox.addEventListener('change', function () {

            const index = this.dataset.documentIndex;

            const uploadArea = document.getElementById(
                'edit-upload-' + index
            );

            const fileInput = document.getElementById(
                'edit-file-' + index
            );

            if (this.checked) {
                uploadArea.style.display = 'block';
                fileInput.required = true;
            } else {
                uploadArea.style.display = 'none';
                fileInput.required = false;
                fileInput.value = '';
            }

        });

    });

});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
