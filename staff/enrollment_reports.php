<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_role('System Administrator', 'School Registrar');
$pageTitle = 'Enrollment Reports';

require_once __DIR__ . '/../includes/header.php';
?>
<section class="form-card form-card-wide">
    <h1>Enrollment Reports</h1>
    <p class="muted">This page is reserved for enrollment statistics and enrollment-status summaries.</p>
    <p class="muted">Feature implementation is pending.</p>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
