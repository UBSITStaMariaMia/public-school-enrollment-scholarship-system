
<?php
require_once __DIR__ . '/../includes/bootstrap.php';

require_role('School Registrar');

$pageTitle = 'Registrar Dashboard';

require_once __DIR__ . '/../includes/header.php';

$user = current_user();
?>

<section class="form-card">
    <p class="eyebrow">Registrar workspace</p>

    <h1>School Registrar Dashboard</h1>

    <p>
        Welcome to the Registrar workspace.
        Manage enrollment applications, verify student documents,
        maintain student records, and generate enrollment reports.
    </p>

    <div class="form-actions">

        <a class="btn btn-primary"
           href="<?= BASE_URL ?>/registrar/enrollment_review.php">
            Enrollment Applications
        </a>

        <a class="btn btn-secondary"
           href="<?= BASE_URL ?>/registrar/enrollment_documents.php">
            Document Verification
        </a>

        <a class="btn btn-secondary"
           href="<?= BASE_URL ?>/registrar/student_records.php">
            Student Records
        </a>

        <a class="btn btn-secondary"
           href="<?= BASE_URL ?>/registrar/sections.php">
            Section Management
        </a>

        <a class="btn btn-secondary"
           href="<?= BASE_URL ?>/registrar/enrollment_reports.php">
            Enrollment Reports
        </a>

        <a class="btn btn-secondary"
           href="<?= BASE_URL ?>/registrar/announcements.php">
            Announcements
        </a>

    </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
