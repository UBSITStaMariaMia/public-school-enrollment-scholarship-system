<?php
require_once __DIR__ . '/../includes/bootstrap.php';

require_role('School Registrar');

$user = current_user();
$registrarName = $user['username'] ?? 'Registrar';

$pageTitle = 'Registrar Dashboard';

require_once __DIR__ . '/../includes/header.php';
?>

<section class="welcome-banner">

    <p class="eyebrow">Registrar Portal</p>

    <h1>Welcome, <?= e($registrarName) ?></h1>

    <p>
        Manage enrollment applications, verify student documents,
        maintain student records, and generate enrollment reports.
    </p>

</section>

<section class="dashboard-grid" aria-label="Registrar services">

    <a
        class="dashboard-card"
        href="<?= BASE_URL ?>/registrar/enrollment_review.php"
    >
        <span class="card-kicker">01</span>
        <h2>Enrollment Applications</h2>
        <p>Review and manage student enrollment applications.</p>
        <span class="dashboard-card-action">
            Review Applications <span aria-hidden="true">&rarr;</span>
        </span>
    </a>

    <a
        class="dashboard-card"
        href="<?= BASE_URL ?>/registrar/enrollment_documents.php"
    >
        <span class="card-kicker">02</span>
        <h2>Document Verification</h2>
        <p>Review supporting documents and update their verification status.</p>
        <span class="dashboard-card-action">
            Verify Documents <span aria-hidden="true">&rarr;</span>
        </span>
    </a>

    <a
        class="dashboard-card"
        href="<?= BASE_URL ?>/registrar/student_records.php"
    >
        <span class="card-kicker">03</span>
        <h2>Student Records</h2>
        <p>View and maintain student information and academic records.</p>
        <span class="dashboard-card-action">
            View Records <span aria-hidden="true">&rarr;</span>
        </span>
    </a>

    <a
        class="dashboard-card"
        href="<?= BASE_URL ?>/registrar/sections.php"
    >
        <span class="card-kicker">04</span>
        <h2>Section Management</h2>
        <p>Manage school sections and their assigned students.</p>
        <span class="dashboard-card-action">
            Manage Sections <span aria-hidden="true">&rarr;</span>
        </span>
    </a>

    <a
        class="dashboard-card"
        href="<?= BASE_URL ?>/registrar/enrollment_reports.php"
    >
        <span class="card-kicker">05</span>
        <h2>Enrollment Reports</h2>
        <p>Generate and review reports about student enrollment.</p>
        <span class="dashboard-card-action">
            View Reports <span aria-hidden="true">&rarr;</span>
        </span>
    </a>

    <a
        class="dashboard-card"
        href="<?= BASE_URL ?>/registrar/announcements.php"
    >
        <span class="card-kicker">06</span>
        <h2>Announcements</h2>
        <p>View and manage announcements for the school community.</p>
        <span class="dashboard-card-action">
            Manage Announcements <span aria-hidden="true">&rarr;</span>
        </span>
    </a>

</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
