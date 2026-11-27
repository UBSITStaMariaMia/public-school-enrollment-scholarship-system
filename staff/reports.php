<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_role('System Administrator', 'School Registrar', 'Scholarship Coordinator');
$pageTitle = 'Reports';

require_once __DIR__ . '/../includes/header.php';
?>
<section class="form-card form-card-wide">
    <h1>Reports</h1>
    <p class="muted">Feature implementation is pending.</p>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
