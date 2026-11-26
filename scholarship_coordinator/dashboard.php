
<?php
require_once __DIR__ . '/../includes/bootstrap.php';

require_role('System Administrator', 'Scholarship Coordinator');

$pageTitle = 'Scholarship Coordinator Dashboard';

require_once __DIR__ . '/../includes/header.php';
?>

<main class="scholarship-dashboard">

    <!-- WELCOME SECTION -->
    <section class="scholarship-welcome-card">

        <p class="scholarship-eyebrow">Scholarship Management</p>

        <h1>Scholarship Coordinator Dashboard</h1>

        <p>
            Manage scholarship programs and applications, monitor student
            beneficiaries, and keep track of stipend records and scholarship reports.
        </p>

    </section>

    <!-- SCHOLARSHIP MANAGEMENT -->
    <section class="scholarship-services-section">

        <div class="scholarship-section-heading">
            <p class="scholarship-eyebrow">Management Workspace</p>
            <h2>Scholarship Management</h2>
            <p>
                Access and manage all scholarship programs, applications,
                beneficiaries, monitoring records, and reports.
            </p>
        </div>

        <div class="scholarship-service-grid">

            <!-- SCHOLARSHIP PROGRAMS -->
            <article class="scholarship-service-card">

                <div class="scholarship-service-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24">
                        <path d="M4 5h16v14H4z"></path>
                        <path d="M8 9h8M8 13h5M8 16h8"></path>
                    </svg>
                </div>

                <h3>Scholarship Programs</h3>

                <p>
                    Manage available scholarship programs, descriptions,
                    slots, and program status.
                </p>

                <a href="<?= BASE_URL ?>/scholarship_coordinator/scholarship_programs.php"
                   class="scholarship-service-link">
                    Manage Programs <span aria-hidden="true">→</span>
                </a>

            </article>

            <!-- SCHOLARSHIP APPLICATIONS -->
            <article class="scholarship-service-card">

                <div class="scholarship-service-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24">
                        <path d="M5 3h10l4 4v14H5z"></path>
                        <path d="M15 3v5h4M8 12h8M8 16h8"></path>
                    </svg>
                </div>

                <h3>Scholarship Applications</h3>

                <p>
                    Review applications, update application statuses,
                    and manage screening schedules.
                </p>

                <a href="<?= BASE_URL ?>/scholarship_coordinator/scholarship_applications.php"
                   class="scholarship-service-link">
                    Review Applications <span aria-hidden="true">→</span>
                </a>

            </article>

            <!-- SCHOLARSHIP MONITORING -->
            <article class="scholarship-service-card">

                <div class="scholarship-service-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24">
                        <circle cx="12" cy="12" r="9"></circle>
                        <path d="M12 7v5l3 2"></path>
                    </svg>
                </div>

                <h3>Scholarship Monitoring</h3>

                <p>
                    Monitor beneficiary performance, participation,
                    and renewal eligibility.
                </p>

                <a href="<?= BASE_URL ?>/scholarship_coordinator/scholarship_monitoring.php"
                   class="scholarship-service-link">
                    Manage Monitoring <span aria-hidden="true">→</span>
                </a>

            </article>

            <!-- STIPEND RECORDS -->
            <article class="scholarship-service-card">

                <div class="scholarship-service-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24">
                        <rect x="3" y="6" width="18" height="13" rx="2"></rect>
                        <path d="M7 10h10M7 14h5M8 3h8"></path>
                    </svg>
                </div>

                <h3>Stipend Records</h3>

                <p>
                    Record stipend amounts, release dates, recipients,
                    and claiming statuses.
                </p>

                <a href="<?= BASE_URL ?>/scholarship_coordinator/stipend_records.php"
                   class="scholarship-service-link">
                    Manage Stipends <span aria-hidden="true">→</span>
                </a>

            </article>

            <!-- BENEFICIARIES REPORT -->
            <article class="scholarship-service-card">

                <div class="scholarship-service-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24">
                        <path d="M4 20V5M4 20h17"></path>
                        <path d="M8 16v-4M12 16V8M16 16v-7"></path>
                    </svg>
                </div>

                <h3>Beneficiaries Report</h3>

                <p>
                    Access scholarship beneficiaries and their recorded
                    application information.
                </p>

                <a href="<?= BASE_URL ?>/scholarship_coordinator/scholarship_beneficiaries_report.php"
                   class="scholarship-service-link">
                    View Report <span aria-hidden="true">→</span>
                </a>

            </article>

            <!-- RENEWAL REPORT -->
            <article class="scholarship-service-card">

                <div class="scholarship-service-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24">
                        <path d="M4 4h16v16H4z"></path>
                        <path d="M8 8h8M8 12h8M8 16h5"></path>
                    </svg>
                </div>

                <h3>Renewal Report</h3>

                <p>
                    Access scholarship monitoring and renewal eligibility
                    reporting.
                </p>

                <a href="<?= BASE_URL ?>/scholarship_coordinator/scholarship_renewal_report.php"
                   class="scholarship-service-link">
                    View Report <span aria-hidden="true">→</span>
                </a>

            </article>

        </div>

    </section>

</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
