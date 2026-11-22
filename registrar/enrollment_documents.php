<?php
require_once __DIR__ . '/../includes/bootstrap.php';

require_role('School Registrar');

$pageTitle = 'Enrollment Document Verification';
$errors = [];
$success = '';

$statuses = ['Submitted', 'For Verification', 'Accepted', 'Rejected'];

$requiredDocuments = [
    'PSA Birth Certificate',
    'Old School Card (before moving up)',
    'Good Moral Certificate'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $documentId = $_POST['document_id'] ?? '';
    $status = $_POST['verification_status'] ?? '';
    $remarks = trim($_POST['remarks'] ?? '');

    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Your session expired. Please refresh the page and try again.';
    }

    if ($documentId === '' || !in_array($status, $statuses, true)) {
        $errors[] = 'Select a valid document and verification status.';
    }

    if ($status === 'Rejected' && $remarks === '') {
        $errors[] = 'Enter remarks explaining why the document was rejected.';
    }

    if ($documentId !== '') {
        $documentQuery = $pdo->prepare(
            'SELECT document_id
             FROM enrollment_documents
             WHERE document_id = :document_id
             LIMIT 1'
        );
        $documentQuery->execute(['document_id' => $documentId]);

        if (!$documentQuery->fetch()) {
            $errors[] = 'Document record not found.';
        }
    }
    if (!$errors) {
        try {
            $update = $pdo->prepare(
                'UPDATE enrollment_documents
                 SET verification_status = :status,
                     remarks = :remarks
                 WHERE document_id = :document_id'
            );

            $update->execute([
                'status' => $status,
                'remarks' => $remarks !== '' ? $remarks : null,
                'document_id' => $documentId
            ]);

            $success = 'Document verification updated.';

        } catch (PDOException $exception) {
            error_log('Document verification error: ' . $exception->getMessage());
            $errors[] = 'Unable to update document verification. Please try again.';
        }
    }
}


$applicationQuery = $pdo->prepare(
    'SELECT ea.application_id, ea.application_status, ea.submitted_at,
            st.first_name, st.last_name
     FROM enrollment_applications ea
     INNER JOIN students st
        ON st.student_id = ea.student_id
     ORDER BY ea.application_id DESC'
);
$applicationQuery->execute();
$applications = $applicationQuery->fetchAll();


$documentListQuery = $pdo->prepare(
    'SELECT document_id, document_type, file_path, verification_status, remarks
     FROM enrollment_documents
     WHERE application_id = :application_id'
);

$applicationRows = [];

foreach ($applications as $application) {

    $documentListQuery->execute([
        'application_id' => $application['application_id']
    ]);
    $savedDocuments = $documentListQuery->fetchAll();

    $rows = [];
    $missingText = '';

    foreach ($requiredDocuments as $requiredName) {

        $found = null;

        foreach ($savedDocuments as $saved) {
            if ($saved['document_type'] === $requiredName && $saved['file_path']) {
                $found = $saved;
            }
        }

        $rows[] = [
            'name' => $requiredName,
            'document' => $found
        ];

        if (!$found) {
            if ($missingText !== '') {
                $missingText .= ', ';
            }
            $missingText .= $requiredName;
        }
    }

    $applicationRows[] = [
        'application' => $application,
        'rows' => $rows,
        'missing' => $missingText
    ];
}

require_once __DIR__ . '/../includes/header.php';
?>

<section class="form-card form-card-wide">

    <h1>Enrollment Document Verification</h1>
    <p class="muted">
        Review the supporting documents of each enrollment application.
        Documents the student has not uploaded yet are marked as missing.
    </p>

    <?php if ($success): ?>
        <div class="alert alert-success">
            <?= e($success) ?>
        </div>
    <?php endif; ?>

    <?php foreach ($errors as $message): ?>
        <div class="alert alert-error">
            <?= e($message) ?>
        </div>
    <?php endforeach; ?>

    <?php foreach ($applicationRows as $item): ?>

        <h2>
            Application #<?= e($item['application']['application_id']) ?> -
            <?= e($item['application']['first_name'] . ' ' . $item['application']['last_name']) ?>
        </h2>

        <p class="muted">
            Application status: <?= e($item['application']['application_status']) ?> |
            Submitted: <?= e($item['application']['submitted_at']) ?>
        </p>

        <?php if ($item['missing'] !== ''): ?>
            <p><strong>Missing documents:</strong> <?= e($item['missing']) ?></p>
        <?php else: ?>
            <p><strong>All required documents have been uploaded.</strong></p>
        <?php endif; ?>

        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Document</th>
                        <th>File</th>
                        <th>Verification</th>
                        <th>Remarks / Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($item['rows'] as $row): ?>
                        <?php $document = $row['document']; ?>
                        <tr>
                            <td><?= e($row['name']) ?></td>

                            <?php if ($document): ?>
                                <td>
                                    <a href="<?= BASE_URL . '/' . e($document['file_path']) ?>"
                                       target="_blank" rel="noopener">Open file</a>
                                </td>
                                <td><?= e($document['verification_status']) ?></td>
                                <td>
                                    <form method="post">
                                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                        <input type="hidden" name="document_id" value="<?= e($document['document_id']) ?>">

                                        <select name="verification_status">
                                            <?php foreach ($statuses as $status): ?>
                                                <option value="<?= e($status) ?>"
                                                    <?= $document['verification_status'] === $status ? 'selected' : '' ?>>
                                                    <?= e($status) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>

                                        <textarea name="remarks" rows="2"
                                            placeholder="Remarks (required if rejected)"><?= e($document['remarks'] ?? '') ?></textarea>

                                        <button type="submit" class="button button-primary">Update</button>
                                    </form>
                                </td>
                            <?php else: ?>
                                <td>No file</td>
                                <td>Not Submitted</td>
                                <td>Waiting for the student to upload this document.</td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    <?php endforeach; ?>

    <?php if (count($applicationRows) === 0): ?>
        <p>No enrollment applications have been submitted.</p>
    <?php endif; ?>

</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
