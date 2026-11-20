<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_role('System Administrator', 'School Registrar');
$pageTitle = 'Student Population Report';

require_once __DIR__ . '/../includes/header.php';
?>
<section class="form-card form-card-wide">
    <h1>Student Population Report</h1>
    <p class="muted">This page is reserved for student population summaries by grade level, section, and school year.</p>
    <p class="muted">Feature implementation is pending.</p>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
