
<?php
require_once __DIR__ . '/../includes/bootstrap.php';

require_role('System Administrator', 'Scholarship Coordinator');

$pageTitle = 'Scholarship Coordinator Dashboard';

$scholarshipSummary = [
    'programs' => 0,
    'applications' => 0,
    'pending' => 0,
    'screening' => 0,
    'approved' => 0,
    'monitoring' => 0,
    'stipends' => 0
];

$summaryError = '';

try {
    $summaryQuery = $pdo->query(
        "SELECT
            (SELECT COUNT(*)
             FROM special_programs
             WHERE status = 'Active') AS active_programs,

            (SELECT COUNT(*)
             FROM scholarship_applications) AS total_applications,

            (SELECT COUNT(*)
             FROM scholarship_applications
             WHERE application_status = 'Pending Review') AS pending_applications,

            (SELECT COUNT(*)
             FROM scholarship_applications
             WHERE application_status = 'For Screening') AS screening_applications,

            (SELECT COUNT(*)
             FROM scholarship_applications
             WHERE application_status = 'Approved') AS approved_applications,

            (SELECT COUNT(*)
             FROM scholarship_monitoring) AS monitoring_records,

            (SELECT COUNT(*)
             FROM stipend_records) AS stipend_records"
    );

    $summary = $summaryQuery->fetch(PDO::FETCH_ASSOC);

    if ($summary) {
        $scholarshipSummary['programs'] = (int) $summary['active_programs'];
        $scholarshipSummary['applications'] = (int) $summary['total_applications'];
        $scholarshipSummary['pending'] = (int) $summary['pending_applications'];
        $scholarshipSummary['screening'] = (int) $summary['screening_applications'];
        $scholarshipSummary['approved'] = (int) $summary['approved_applications'];
        $scholarshipSummary['monitoring'] = (int) $summary['monitoring_records'];
        $scholarshipSummary['stipends'] = (int) $summary['stipend_records'];
    }

} catch (PDOException $exception) {
    error_log('Scholarship Coordinator dashboard error: ' . $exception->getMessage());
    $summaryError = 'Scholarship summaries are temporarily unavailable.';
}

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

    <?php if ($summaryError !== ''): ?>
        <div class="scholarship-dashboard-error" role="alert">
            <?= e($summaryError) ?>
        </div>
    <?php endif; ?>

    <!-- SCHOLARSHIP OVERVIEW -->
    <section class="scholarship-summary-section">

        <div class="scholarship-section-heading">
            <h2>Scholarship Overview</h2>
            <p>Summary of scholarship programs, applications, and records.</p>
        </div>

        <div class="scholarship-summary-grid">

            <article class="scholarship-summary-card scholarship-summary-primary">
                <span class="scholarship-summary-label">Approved Applications</span>
                <strong><?= number_format($scholarshipSummary['approved']) ?></strong>
                <span class="scholarship-summary-note">Official scholarship beneficiaries</span>
            </article>

            <article class="scholarship-summary-card">
                <span class="scholarship-summary-label">Total Applications</span>
                <strong><?= number_format($scholarshipSummary['applications']) ?></strong>
                <span class="scholarship-summary-note">All recorded applications</span>
            </article>

            <article class="scholarship-summary-card scholarship-summary-primary">
                <span class="scholarship-summary-label">Active Scholarship Programs</span>
                <strong><?= number_format($scholarshipSummary['programs']) ?></strong>
                <span class="scholarship-summary-note">Currently active programs</span>
            </article>

            <article class="scholarship-summary-card">
                <span class="scholarship-summary-label">Pending Review</span>
                <strong><?= number_format($scholarshipSummary['pending']) ?></strong>
                <span class="scholarship-summary-note">Awaiting application review</span>
            </article>

            <article class="scholarship-summary-card">
                <span class="scholarship-summary-label">For Screening</span>
                <strong><?= number_format($scholarshipSummary['screening']) ?></strong>
                <span class="scholarship-summary-note">Applications for screening</span>
            </article>

            <article class="scholarship-summary-card">
                <span class="scholarship-summary-label">Monitoring Records</span>
                <strong><?= number_format($scholarshipSummary['monitoring']) ?></strong>
                <span class="scholarship-summary-note">Recorded monitoring entries</span>
            </article>

            <article class="scholarship-summary-card">
                <span class="scholarship-summary-label">Stipend Records</span>
                <strong><?= number_format($scholarshipSummary['stipends']) ?></strong>
                <span class="scholarship-summary-note">Recorded stipend transactions</span>
            </article>

        </div>

    </section>

    <!-- SCHOLARSHIP MANAGEMENT -->
    <section class="scholarship-services-section">

        <div class="scholarship-section-heading">
            <p class="scholarship-eyebrow">Management Workspace</p>
            <h2>Scholarship Management</h2>
            <p>Access the available scholarship management pages and reports.</p>
        </div>

        <div class="scholarship-service-grid">

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

            <article class="scholarship-service-card">
                <div class="scholarship-service-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24">
                        <path d="M5 3h10l4 4v14H5z"></path>
                        <path d="M15 3v5h4M8 12h8M8 16h5"></path>
                    </svg>
                </div>
                <h3>Scholarship Documents</h3>
                <p>
                    Access the scholarship document management workspace.
                </p>
                <a href="<?= BASE_URL ?>/scholarship_coordinator/scholarship_documents.php"
                   class="scholarship-service-link">
                    Open Documents <span aria-hidden="true">→</span>
                </a>
            </article>

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

            <article class="scholarship-service-card">
                <div class="scholarship-service-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24">
                        <path d="M4 10v4h3l10 4V6L7 10H4Z"></path>
                        <path d="M7 14l2 5h3l-2-4"></path>
                    </svg>
                </div>
                <h3>Announcements</h3>
                <p>
                    View school announcements and important scholarship updates.
                </p>
                <a href="<?= BASE_URL ?>/announcements.php"
                   class="scholarship-service-link">
                    View Announcements <span aria-hidden="true">→</span>
                </a>
            </article>

        </div>

    </section>

</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
