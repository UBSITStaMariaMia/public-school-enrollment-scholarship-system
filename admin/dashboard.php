<?php
require_once __DIR__ . '/../includes/bootstrap.php';

require_role('System Administrator');

$pageTitle = 'System Administrator Dashboard';

/*
 * Account summaries are retrieved from the existing users and roles tables.
 * Student accounts are counted separately from school personnel accounts.
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
            SUM(CASE WHEN r.role_name = 'Student' THEN 1 ELSE 0 END) AS student_accounts,
            SUM(CASE
                WHEN r.role_name IN (
                    'School Registrar',
                    'Teacher',
                    'Enrollment Committee',
                    'Scholarship Coordinator'
                ) THEN 1 ELSE 0
            END) AS personnel_accounts,
            SUM(CASE WHEN u.account_status = 'Inactive' THEN 1 ELSE 0 END) AS inactive_accounts
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

<section class="form-card admin-welcome-card">
    <p class="eyebrow">System Administration</p>
    <h1>System Administrator Dashboard</h1>
    <p>
        Manage school portal accounts, review account information, and maintain
        appropriate access for students and school personnel.
    </p>
</section>

<?php if (!empty($summaryError)): ?>
    <div class="error-message">
        <?= e($summaryError) ?>
    </div>
<?php endif; ?>

<section class="admin-summary-section">
    <h2>Account Overview</h2>

    <div class="admin-summary-grid">
        <article class="admin-summary-card">
            <span class="admin-summary-label">Total User Accounts</span>
            <strong><?= number_format($accountSummary['total']) ?></strong>
            <span class="admin-summary-note">All registered portal accounts</span>
        </article>

        <article class="admin-summary-card">
            <span class="admin-summary-label">Student Accounts</span>
            <strong><?= number_format($accountSummary['students']) ?></strong>
            <span class="admin-summary-note">Accounts assigned the Student role</span>
        </article>

        <article class="admin-summary-card">
            <span class="admin-summary-label">School Personnel Accounts</span>
            <strong><?= number_format($accountSummary['personnel']) ?></strong>
            <span class="admin-summary-note">Registrar, teacher, and coordinator accounts</span>
        </article>

        <article class="admin-summary-card">
            <span class="admin-summary-label">Inactive Accounts</span>
            <strong><?= number_format($accountSummary['inactive']) ?></strong>
            <span class="admin-summary-note">Accounts currently marked inactive</span>
        </article>
    </div>
</section>

<section class="admin-services-section">
    <div class="admin-section-heading">
        <p class="eyebrow">Administration</p>
        <h2>Account Management</h2>
        <p>Use these pages to manage portal access and review account information.</p>
    </div>

    <div class="admin-service-grid">
        <article class="admin-service-card">
            <div class="admin-service-icon" aria-hidden="true">
                <span>UM</span>
            </div>
            <h3>User Management</h3>
            <p>
                Create staff accounts, assign authorized roles, and activate
                or deactivate staff access.
            </p>
            <a class="btn btn-primary" href="<?= BASE_URL ?>/admin/user_management.php">
                Manage Staff Accounts
            </a>
        </article>

        <article class="admin-service-card">
            <div class="admin-service-icon" aria-hidden="true">
                <span>SR</span>
            </div>
            <h3>Student Records</h3>
            <p>
                Review student account and profile information maintained
                in the school portal.
            </p>
            <span class="admin-service-status">Dedicated admin page not yet configured</span>
        </article>

        <article class="admin-service-card">
            <div class="admin-service-icon" aria-hidden="true">
                <span>TR</span>
            </div>
            <h3>Teacher and Staff Records</h3>
            <p>
                Review school personnel accounts and their assigned system roles.
            </p>
            <span class="admin-service-status">Dedicated admin page not yet configured</span>
        </article>
    </div>
</section>

<style>
.admin-welcome-card {
    margin-bottom: 28px;
}

.admin-summary-section,
.admin-services-section {
    margin: 28px 0;
}

.admin-summary-section h2,
.admin-section-heading h2 {
    color: #55172f;
    margin: 0 0 16px;
}

.admin-summary-grid,
.admin-service-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 18px;
}

.admin-summary-card,
.admin-service-card {
    background: #fff;
    border: 1px solid #eadfe4;
    border-radius: 14px;
    padding: 22px;
    box-shadow: 0 5px 18px rgba(40, 20, 30, 0.06);
}

.admin-summary-card {
    display: flex;
    flex-direction: column;
    gap: 9px;
    min-height: 140px;
}

.admin-summary-label {
    color: #55515a;
    font-size: 0.92rem;
    font-weight: 600;
}

.admin-summary-card strong {
    color: #55172f;
    font-size: 2rem;
    line-height: 1.2;
}

.admin-summary-note {
    color: #77717a;
    font-size: 0.8rem;
}

.admin-section-heading {
    margin-bottom: 18px;
}

.admin-section-heading > p:last-child {
    color: #66616a;
    margin-top: 6px;
}

.admin-service-grid {
    grid-template-columns: repeat(3, minmax(0, 1fr));
}

.admin-service-card {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 12px;
}

.admin-service-icon {
    width: 44px;
    height: 44px;
    display: grid;
    place-items: center;
    border-radius: 12px;
    background: #f7eaf0;
    color: #741f42;
    font-weight: 800;
    font-size: 0.85rem;
}

.admin-service-card h3 {
    color: #55172f;
    margin: 0;
}

.admin-service-card p {
    color: #5f5a63;
    line-height: 1.6;
    margin: 0 0 8px;
}

.admin-service-card .btn {
    margin-top: auto;
    text-decoration: none;
}

.admin-service-status {
    margin-top: auto;
    color: #756b72;
    background: #f5f2f3;
    border-radius: 8px;
    padding: 9px 11px;
    font-size: 0.82rem;
}

@media (max-width: 1000px) {
    .admin-summary-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .admin-service-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 560px) {
    .admin-summary-grid {
        grid-template-columns: 1fr;
    }

    .admin-summary-card,
    .admin-service-card {
        padding: 18px;
    }
}
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
