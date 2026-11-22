<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_role('System Administrator', 'Scholarship Coordinator');
$pageTitle = 'Scholarship Beneficiaries Report';

require_once __DIR__ . '/../includes/header.php';
?>
<section class="form-card form-card-wide">
    <h1>Scholarship Beneficiaries Report</h1>
    <p class="muted">This page is reserved for reports on approved scholarship applicants and current beneficiaries.</p>
    <p class="muted">Feature implementation is pending.</p>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
