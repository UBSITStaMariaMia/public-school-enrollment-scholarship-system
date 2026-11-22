
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

<style>
/* =========================================
   SYSTEM ADMINISTRATOR DASHBOARD
========================================= */

/* Welcome Section */

.admin-welcome-card {
    max-width: 100%;
    width: 100%;
    min-height: 210px;
    margin: 0 auto 42px;
    padding: 42px 48px;

    display: flex;
    flex-direction: column;
    justify-content: center;

    background: #ffffff;
    border: 1px solid #eadfe4;
    border-radius: 18px;

    box-shadow: 0 5px 20px rgba(40, 20, 30, 0.07);
}

.admin-welcome-card .eyebrow {
    margin-bottom: 12px;
    color: #741f42;
}

.admin-welcome-card h1 {
    margin: 0 0 14px;
    color: #55172f;
    font-size: clamp(2rem, 3vw, 2.7rem);
    line-height: 1.25;
}

.admin-welcome-card > p:last-child {
    max-width: 850px;
    margin: 0;
    color: #716a72;
    font-size: 1.05rem;
    line-height: 1.8;
}

/* Dashboard Sections */

.admin-summary-section,
.admin-services-section {
    width: 100%;
    margin: 42px 0;
}

.admin-summary-section h2,
.admin-section-heading h2 {
    margin: 0 0 16px;
    color: #55172f;
    font-size: 1.55rem;
    line-height: 1.4;
}

.admin-section-heading {
    margin-bottom: 24px;
}

.admin-section-heading .eyebrow {
    margin-bottom: 8px;
    color: #741f42;
}

.admin-section-heading > p:last-child {
    margin-top: 8px;
    color: #716a72;
    font-size: 1rem;
    line-height: 1.6;
}

/* Account Overview */

.admin-summary-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 22px;
}

.admin-summary-card {
    min-height: 165px;
    padding: 26px;

    display: flex;
    flex-direction: column;
    justify-content: space-between;
    gap: 12px;

    background: #ffffff;
    border: 1px solid #eadfe4;
    border-radius: 16px;

    box-shadow: 0 5px 18px rgba(40, 20, 30, 0.06);
}

.admin-summary-card:first-child {
    background: #741f42;
    border-color: #741f42;
}

.admin-summary-label {
    color: #625b63;
    font-size: 0.95rem;
    font-weight: 600;
    line-height: 1.5;
}

.admin-summary-card strong {
    color: #55172f;
    font-size: 2.5rem;
    line-height: 1.2;
}

.admin-summary-note {
    color: #817982;
    font-size: 0.88rem;
    line-height: 1.5;
}

.admin-summary-card:first-child .admin-summary-label,
.admin-summary-card:first-child strong,
.admin-summary-card:first-child .admin-summary-note {
    color: #ffffff;
}

/* Account Management Cards */

.admin-service-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 24px;
}

.admin-service-card {
    min-height: 310px;
    padding: 30px;

    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 17px;

    background: #ffffff;
    border: 1px solid #eadfe4;
    border-radius: 17px;

    box-shadow: 0 5px 18px rgba(40, 20, 30, 0.06);

    transition:
        transform 0.2s ease,
        box-shadow 0.2s ease;
}

.admin-service-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 9px 25px rgba(40, 20, 30, 0.10);
}

/* Service Icons */

.admin-service-icon {
    width: 56px;
    height: 56px;
    flex-shrink: 0;

    display: grid;
    place-items: center;

    background: #f7eaf0;
    color: #741f42;
    border-radius: 14px;
}

.admin-service-icon svg {
    width: 28px;
    height: 28px;
}

.admin-service-icon span {
    font-size: 0.95rem;
    font-weight: 800;
}

/* Service Card Text */

.admin-service-card h3 {
    margin: 0;
    color: #55172f;
    font-size: 1.25rem;
    line-height: 1.4;
}

.admin-service-card p {
    margin: 0 0 10px;
    color: #716a72;
    font-size: 0.96rem;
    line-height: 1.7;
}

/* Service Card Links */

.admin-service-card .btn {
    width: 100%;
    min-height: 46px;
    margin-top: auto;
    padding: 12px 16px;

    display: flex;
    align-items: center;
    justify-content: space-between;

    border-radius: 9px;
    text-decoration: none;
    font-size: 0.9rem;
    font-weight: 600;
}

.admin-service-card .btn::after {
    content: "→";
    font-size: 1.1rem;
}

.admin-service-status {
    width: 100%;
    margin-top: auto;
    padding: 12px 14px;

    background: #f7f2f4;
    border-radius: 9px;

    color: #756b72;
    font-size: 0.85rem;
    line-height: 1.5;
}

/* Dashboard Error Message */

.admin-dashboard-error {
    margin-bottom: 24px;
    padding: 14px 18px;

    background: #fff0f0;
    border: 1px solid #f3caca;
    border-radius: 9px;

    color: #9b1c1c;
}

/* =========================================
   RESPONSIVE DESIGN
========================================= */

@media (max-width: 1100px) {

    .admin-summary-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .admin-service-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

}

@media (max-width: 700px) {

    .admin-welcome-card {
        min-height: auto;
        padding: 30px 25px;
        margin-bottom: 30px;
    }

    .admin-welcome-card h1 {
        font-size: 1.9rem;
    }

    .admin-welcome-card > p:last-child {
        font-size: 0.95rem;
    }

    .admin-summary-grid,
    .admin-service-grid {
        grid-template-columns: 1fr;
        gap: 18px;
    }

    .admin-summary-section,
    .admin-services-section {
        margin: 32px 0;
    }

    .admin-summary-card {
        min-height: 145px;
        padding: 23px;
    }

    .admin-service-card {
        min-height: 275px;
        padding: 25px;
    }

}

@media (max-width: 480px) {

    .admin-welcome-card {
        padding: 25px 20px;
    }

    .admin-welcome-card h1 {
        font-size: 1.65rem;
    }

    .admin-summary-section h2,
    .admin-section-heading h2 {
        font-size: 1.35rem;
    }

    .admin-service-card {
        padding: 22px;
    }

}
</style>

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
