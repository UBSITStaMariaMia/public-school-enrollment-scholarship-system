<?php
header('Cache-Control: private, no-store');
if (!empty($formalFilename)) {
    header('Content-Type: application/msword; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $formalFilename . '"');
    header('X-Content-Type-Options: nosniff');
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($formalTitle) ?></title>
    <?php require __DIR__ . '/formal_report_styles.php'; ?>
    <?php if (!empty($formalAutoPrint) && empty($formalFilename)): ?>
        <script>
        window.addEventListener('load', function () {
            if (window.parent === window) window.print();
        }, { once: true });
        </script>
    <?php endif; ?>
</head>
<body>
<?php if (empty($formalFilename)): ?>
    <nav class="report-actions" aria-label="Document actions">
        <a class="portal-back-link" href="<?= e($formalBackUrl) ?>"><?= e($formalBackLabel ?? 'Back') ?></a>
        <button type="button" onclick="window.print()">Print / Save as PDF</button>
    </nav>
<?php endif; ?>
<main class="report-page">
    <header class="report-header">
        <h1 class="school-name">Baguio City National High School</h1>
        <p class="school-subtitle"><?= e($formalOffice) ?></p>
    </header>
    <h2 class="report-title"><?= e($formalTitle) ?></h2>
