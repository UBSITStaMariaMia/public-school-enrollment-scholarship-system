<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_role('System Administrator', 'School Registrar');
$pageTitle = 'Announcement Management';

require_once __DIR__ . '/../includes/header.php';
?>
<section class="form-card form-card-wide">
    <h1>Announcement Management</h1>
    <p class="muted">This page is reserved for creating, editing, publishing, and managing school announcements.</p>
    <p class="muted">Feature implementation is pending.</p>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
