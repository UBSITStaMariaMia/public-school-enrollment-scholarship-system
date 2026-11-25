
<?php
require_once __DIR__ . '/../includes/bootstrap.php';

require_role('System Administrator');

$pageTitle = 'System Administrator Dashboard';

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
