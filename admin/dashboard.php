
<?php
require_once __DIR__ . '/../includes/bootstrap.php';

require_role('System Administrator');

$pageTitle = 'System Administrator Dashboard';

/*
 * Retrieve account summaries from the existing users and roles tables.
 */
$accountSummary = [
    'total' => 0,
    'students' => 0,
    'personnel' => 0,
    'inactive' => 0
];

try {
    $summaryQuery = $pdo->query(
        "SELECT
            COUNT(*) AS total_accounts,
            SUM(CASE
                WHEN r.role_name = 'Student' THEN 1 ELSE 0
            END) AS student_accounts,
            SUM(CASE
                WHEN r.role_name IN (
                    'School Registrar',
                    'Teacher',
                    'Enrollment Committee',
                    'Scholarship Coordinator'
                ) THEN 1 ELSE 0
            END) AS personnel_accounts,
            SUM(CASE
                WHEN u.account_status = 'Inactive' THEN 1 ELSE 0
            END) AS inactive_accounts
         FROM users u
         INNER JOIN roles r ON r.role_id = u.role_id"
    );

    $summary = $summaryQuery->fetch(PDO::FETCH_ASSOC);

    if ($summary) {
        $accountSummary['total'] = (int) ($summary['total_accounts'] ?? 0);
        $accountSummary['students'] = (int) ($summary['student_accounts'] ?? 0);
        $accountSummary['personnel'] = (int) ($summary['personnel_accounts'] ?? 0);
        $accountSummary['inactive'] = (int) ($summary['inactive_accounts'] ?? 0);
    }

} catch (PDOException $exception) {
    error_log('Admin dashboard account summary error: ' . $exception->getMessage());
    $summaryError = 'Account summaries are temporarily unavailable.';
}

require_once __DIR__ . '/../includes/header.php';
?>



<!-- =====================================
     WELCOME SECTION
===================================== -->

<section class="form-card admin-welcome-card">

    <p class="eyebrow">System Administration</p>

    <h1>System Administrator Dashboard</h1>

    <p>
        Manage school portal accounts, review account information,
        and maintain appropriate access for students and school personnel.
    </p>

</section>

<?php if (!empty($summaryError)): ?>

    <div class="admin-dashboard-error">
        <?= e($summaryError) ?>
    </div>

<?php endif; ?>


<!-- =====================================
     ACCOUNT OVERVIEW
===================================== -->

<section class="admin-summary-section">

    <h2>Account Overview</h2>

    <div class="admin-summary-grid">

        <article class="admin-summary-card">

            <span class="admin-summary-label">
                Total User Accounts
            </span>

            <strong>
                <?= number_format($accountSummary['total']) ?>
            </strong>

            <span class="admin-summary-note">
                All registered portal accounts
            </span>

        </article>


        <article class="admin-summary-card">

            <span class="admin-summary-label">
                Student Accounts
            </span>

            <strong>
                <?= number_format($accountSummary['students']) ?>
            </strong>

            <span class="admin-summary-note">
                Accounts assigned the Student role
            </span>

        </article>


        <article class="admin-summary-card">

            <span class="admin-summary-label">
                School Personnel Accounts
            </span>

            <strong>
                <?= number_format($accountSummary['personnel']) ?>
            </strong>

            <span class="admin-summary-note">
                Registrar, teacher, and coordinator accounts
            </span>

        </article>


        <article class="admin-summary-card">

            <span class="admin-summary-label">
                Inactive Accounts
            </span>

            <strong>
                <?= number_format($accountSummary['inactive']) ?>
            </strong>

            <span class="admin-summary-note">
                Accounts currently marked inactive
            </span>

        </article>

    </div>

</section>


<!-- =====================================
     ACCOUNT MANAGEMENT
===================================== -->

<section class="admin-services-section">

    <div class="admin-section-heading">

        <p class="eyebrow">Administration</p>

        <h2>Account Management</h2>

        <p>
            Use these pages to manage portal access and review account information.
        </p>

    </div>


    <div class="admin-service-grid">

        <!-- USER MANAGEMENT -->

        <article class="admin-service-card">

            <div class="admin-service-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                     stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="4" width="18" height="16" rx="2.5"></rect>
                    <circle cx="9" cy="10" r="2.2"></circle>
                    <path d="M5.8 16c.7-1.8 2-2.7 3.2-2.7s2.5.9 3.2 2.7"></path>
                    <path d="M15 9h3M15 13h3"></path>
                </svg>
            </div>

            <h3>User Management</h3>

            <p>
                Create staff accounts, assign authorized roles,
                and activate or deactivate staff access.
            </p>

            <a
                class="btn btn-primary"
                href="<?= BASE_URL ?>/admin/user_management.php"
            >
                Manage Staff Accounts
            </a>

        </article>


        <!-- STUDENT RECORDS -->

        <article class="admin-service-card">

            <div class="admin-service-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                     stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="5" y="3" width="16" height="18" rx="2"></rect>
                    <path d="M13 3v6l2.5-1.5L18 9V3"></path>
                    <path d="M2 7v.01M2 12v.01M2 17v.01M9 13h3M9 17h8"></path>
                </svg>
            </div>

            <h3>Student Records</h3>

            <p>
                Review student account and profile information,
                enrollment details, academic records, and scholarship information.
            </p>

            <a
                class="btn btn-primary"
                href="<?= BASE_URL ?>/admin/student_records.php"
            >
                View Student Records
            </a>

        </article>


        <!-- TEACHER AND STAFF RECORDS -->

        <article class="admin-service-card">

            <div class="admin-service-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                     stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="3" width="18" height="13" rx="2"></rect>
                    <path d="M12 16v5M8 21h8M7 8l3 3 2-2 2 2 3-3"></path>
                </svg>
            </div>

            <h3>Teacher and Staff Records</h3>

            <p>
                Review school personnel accounts, assigned system roles,
                employment information, and account status.
            </p>

            <a
                class="btn btn-primary"
                href="<?= BASE_URL ?>/admin/staff_records.php"
            >
                View Teacher and Staff Records
            </a>

        </article>

    </div>

</section>


<?php require_once __DIR__ . '/../includes/footer.php'; ?>
