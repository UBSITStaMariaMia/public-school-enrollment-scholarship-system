<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_role('System Administrator', 'Scholarship Coordinator');

$pageTitle = 'Stipend Records';
$error = '';
$success = '';
$programFilter = filter_var($_GET['program'] ?? '', FILTER_VALIDATE_INT) ?: null;
$statusFilter = trim((string) ($_GET['status'] ?? ''));
$search = trim((string) ($_GET['search'] ?? ''));
$validStatuses = ['Pending Claim', 'Claimed', 'Unclaimed'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
            throw new RuntimeException('Invalid request. Please refresh the page.');
        }

        $applicationId = filter_var($_POST['scholarship_application_id'] ?? null, FILTER_VALIDATE_INT);
        $amount = filter_var($_POST['amount'] ?? null, FILTER_VALIDATE_FLOAT);
        $releaseDate = trim((string) ($_POST['release_date'] ?? ''));
        $recipient = trim((string) ($_POST['recipient_name'] ?? ''));
        $claimingStatus = (string) ($_POST['claiming_status'] ?? 'Pending Claim');
        $remarks = trim((string) ($_POST['remarks'] ?? ''));

        if (!$applicationId || $amount === false || $amount <= 0 || $recipient === '' || strlen($recipient) > 150 || !in_array($claimingStatus, $validStatuses, true)) {
            throw new RuntimeException('Please enter a valid scholar, amount, recipient, and claiming status.');
        }

        if ($releaseDate !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $releaseDate)) {
            throw new RuntimeException('Please enter a valid release date.');
        }

        $check = $pdo->prepare("SELECT scholarship_application_id FROM scholarship_applications WHERE scholarship_application_id = :id AND application_status = 'Approved'");
        $check->execute(['id' => $applicationId]);
        if (!$check->fetch()) {
            throw new RuntimeException('Stipends can only be recorded for approved scholarship applications.');
        }

        $insert = $pdo->prepare('INSERT INTO stipend_records (scholarship_application_id, amount, release_date, recipient_name, claiming_status, remarks, recorded_by) VALUES (:application_id, :amount, :release_date, :recipient, :status, :remarks, :recorded_by)');
        $insert->execute([
            'application_id' => $applicationId,
            'amount' => $amount,
            'release_date' => $releaseDate !== '' ? $releaseDate : null,
            'recipient' => $recipient,
            'status' => $claimingStatus,
            'remarks' => $remarks !== '' ? $remarks : null,
            'recorded_by' => current_user()['user_id'],
        ]);
        $success = 'Stipend record saved successfully.';
    } catch (PDOException $ex) {
        error_log('Stipend records: ' . $ex->getMessage());
        $error = 'Unable to save the stipend record. Please try again.';
    } catch (RuntimeException $ex) {
        $error = $ex->getMessage();
    }
}

try {
    $approved = $pdo->query("SELECT sa.scholarship_application_id, st.first_name, st.last_name, sp.program_name, sy.school_year FROM scholarship_applications sa INNER JOIN students st ON st.student_id = sa.student_id INNER JOIN special_programs sp ON sp.program_id = sa.program_id INNER JOIN school_years sy ON sy.school_year_id = sa.school_year_id WHERE sa.application_status = 'Approved' ORDER BY st.last_name, st.first_name")->fetchAll();
    $programs = $pdo->query('SELECT program_id, program_name FROM special_programs ORDER BY program_name')->fetchAll();

    $conditions = ['1 = 1'];
    $params = [];
    if ($programFilter !== null) {
        $conditions[] = 'sa.program_id = :program';
        $params['program'] = $programFilter;
    }
    if (in_array($statusFilter, $validStatuses, true)) {
        $conditions[] = 'sr.claiming_status = :status';
        $params['status'] = $statusFilter;
    }
    if ($search !== '') {
        $conditions[] = "(CONCAT_WS(' ', st.first_name, NULLIF(st.middle_name, ''), st.last_name) LIKE :full_name OR sr.recipient_name LIKE :recipient OR st.first_name LIKE :first_name OR st.last_name LIKE :last_name OR st.lrn LIKE :lrn)";
        $term = '%' . $search . '%';
        $params['full_name'] = $term;
        $params['recipient'] = $term;
        $params['first_name'] = $term;
        $params['last_name'] = $term;
        $params['lrn'] = $term;
    }
    $whereSql = implode(' AND ', $conditions);

    $summary = $pdo->query("SELECT COALESCE(SUM(amount), 0) AS total_amount, COALESCE(SUM(CASE WHEN claiming_status = 'Claimed' THEN amount ELSE 0 END), 0) AS claimed_amount, COALESCE(SUM(CASE WHEN claiming_status = 'Pending Claim' THEN amount ELSE 0 END), 0) AS pending_amount, COALESCE(SUM(CASE WHEN claiming_status = 'Unclaimed' THEN amount ELSE 0 END), 0) AS unclaimed_amount FROM stipend_records")->fetch() ?: [];

    $recordsQuery = $pdo->prepare("SELECT sr.stipend_id, sr.amount, sr.release_date, sr.recipient_name, sr.claiming_status, sr.remarks, st.first_name, st.last_name, st.lrn, sp.program_name, sy.school_year FROM stipend_records sr INNER JOIN scholarship_applications sa ON sa.scholarship_application_id = sr.scholarship_application_id INNER JOIN students st ON st.student_id = sa.student_id INNER JOIN special_programs sp ON sp.program_id = sa.program_id INNER JOIN school_years sy ON sy.school_year_id = sa.school_year_id WHERE $whereSql ORDER BY sr.release_date DESC, sr.stipend_id DESC");
    $recordsQuery->execute($params);
    $records = $recordsQuery->fetchAll();
} catch (PDOException $ex) {
    error_log('Stipend records page: ' . $ex->getMessage());
    $approved = $programs = $records = [];
    $summary = [];
    $error = 'Unable to load stipend records. Please check the database connection.';
}

require_once __DIR__ . '/../includes/header.php';
?>
<style>
.stipend-page{max-width:1240px;margin:32px auto;padding:32px;background:#fff;border:1px solid #e1e5ec;border-radius:18px;box-shadow:0 12px 35px rgba(25,35,55,.07)}
.stipend-page h1{margin:0;color:#56172f;font-size:2rem}.stipend-subtitle{margin:8px 0 26px;color:#667085}
.stipend-summary{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:16px;margin-bottom:26px}
.stipend-stat{padding:20px;border:1px solid #e1e5ec;border-top:4px solid #6b1c38;border-radius:12px}.stipend-stat:nth-child(2){border-top-color:#16845b}.stipend-stat:nth-child(3){border-top-color:#c18420}.stipend-stat:nth-child(4){border-top-color:#64748b}
.stipend-stat span{display:block;color:#667085;font-size:.9rem}.stipend-stat strong{display:block;margin-top:8px;color:#56172f;font-size:1.55rem}
.stipend-panel{padding:22px;margin:22px 0;border:1px solid #e1e5ec;border-radius:14px;background:#fafbfc}.stipend-panel h2{margin:0 0 16px;color:#56172f;font-size:1.25rem}
.stipend-form-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:15px}.stipend-form-grid label,.stipend-filter-grid label{display:flex;flex-direction:column;gap:7px;font-weight:600;color:#344054}.stipend-page input,.stipend-page select,.stipend-page textarea{width:100%;min-height:42px;padding:9px 11px;border:1px solid #cfd5df;border-radius:8px;background:#fff;font:inherit}.stipend-page textarea{min-height:74px}.stipend-wide{grid-column:span 2}.stipend-actions{display:flex;justify-content:flex-end;align-items:end}.stipend-page button{padding:11px 20px;border:0;border-radius:9px;background:#681b39;color:#fff;font-weight:700;cursor:pointer}.stipend-filter-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px;align-items:end}.stipend-filter-actions{display:flex;gap:10px}.stipend-reset{display:inline-flex;align-items:center;justify-content:center;padding:10px 17px;border:1px solid #681b39;border-radius:9px;color:#681b39;text-decoration:none;font-weight:700;background:#fff}
.stipend-table-wrap{width:100%;overflow-x:auto;border:1px solid #e1e5ec;border-radius:12px}.stipend-page table{width:100%;min-width:920px;border-collapse:collapse}.stipend-page th{padding:14px;text-align:left;background:#f7edf1;color:#56172f;white-space:nowrap}.stipend-page td{padding:13px 14px;border-top:1px solid #edf0f4;vertical-align:top}.stipend-page tbody tr:hover{background:#fcf8fa}.stipend-muted{display:block;color:#667085;font-size:.86rem;margin-top:3px}.stipend-status{display:inline-block;padding:5px 10px;border-radius:20px;background:#f1f3f5;color:#475467;font-size:.82rem;font-weight:700;white-space:nowrap}.stipend-status.claimed{background:#e8f5ee;color:#167348}.stipend-status.pending{background:#fff4df;color:#946200}.stipend-status.unclaimed{background:#fcebea;color:#a3332b}.stipend-empty{padding:30px!important;text-align:center;color:#667085}
.stipend-alert{padding:12px 15px;margin:14px 0;border-radius:9px}.stipend-alert.success{background:#eaf6ef;color:#176b42}.stipend-alert.error{background:#fff0ef;color:#a12e28}.stipend-note{margin:0 0 18px;color:#667085;font-size:.92rem}
@media(max-width:900px){.stipend-summary{grid-template-columns:repeat(2,minmax(0,1fr))}.stipend-form-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.stipend-filter-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:600px){.stipend-page{margin:16px 10px;padding:20px 14px}.stipend-summary,.stipend-form-grid,.stipend-filter-grid{grid-template-columns:1fr}.stipend-wide{grid-column:auto}.stipend-actions{justify-content:stretch}.stipend-actions button{width:100%}}
</style>

<main class="stipend-page">
    <h1>Stipend Records</h1>
    <p class="stipend-subtitle">Record stipend releases and monitor which scholarship recipients have claimed their allowance.</p>

    <?php if ($success): ?><div class="stipend-alert success"><?= e($success) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="stipend-alert error"><?= e($error) ?></div><?php endif; ?>

    <section class="stipend-summary" aria-label="Stipend summary">
        <div class="stipend-stat"><span>Total Stipend Recorded</span><strong>₱<?= number_format((float) ($summary['total_amount'] ?? 0), 2) ?></strong></div>
        <div class="stipend-stat"><span>Claimed</span><strong>₱<?= number_format((float) ($summary['claimed_amount'] ?? 0), 2) ?></strong></div>
        <div class="stipend-stat"><span>Pending Claim</span><strong>₱<?= number_format((float) ($summary['pending_amount'] ?? 0), 2) ?></strong></div>
        <div class="stipend-stat"><span>Unclaimed</span><strong>₱<?= number_format((float) ($summary['unclaimed_amount'] ?? 0), 2) ?></strong></div>
    </section>

    <section class="stipend-panel">
        <h2>Record a Stipend Release</h2>
        <p class="stipend-note">Enter a stipend once the release information is provided by the Finance Officer. A release date may be left blank if it has not yet been scheduled.</p>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <div class="stipend-form-grid">
                <label class="stipend-wide">Approved Scholar
                    <select name="scholarship_application_id" required>
                        <option value="">Select scholar</option>
                        <?php foreach ($approved as $scholar): ?>
                            <option value="<?= (int) $scholar['scholarship_application_id'] ?>"><?= e($scholar['last_name'] . ', ' . $scholar['first_name'] . ' — ' . $scholar['program_name'] . ' (' . $scholar['school_year'] . ')') ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Amount (₱)<input type="number" name="amount" min="0.01" step="0.01" required></label>
                <label>Release Date<input type="date" name="release_date"></label>
                <label>Recipient Name<input type="text" name="recipient_name" maxlength="150" required></label>
                <label>Claiming Status
                    <select name="claiming_status"><option>Pending Claim</option><option>Claimed</option><option>Unclaimed</option></select>
                </label>
                <label class="stipend-wide">Remarks<textarea name="remarks" rows="2" placeholder="Optional notes"></textarea></label>
                <div class="stipend-actions"><button type="submit">Save Stipend Record</button></div>
            </div>
        </form>
    </section>

    <section>
        <h2 style="color:#56172f;margin-bottom:14px">Stipend History</h2>
        <form method="get" class="table-search-form">
        <div class="table-search-toolbar">
            <div class="table-search-field">
                <label>Search Recipient / Student<input type="search" data-student-suggestions="stipends" data-suggestion-url="<?= BASE_URL ?>/includes/student_suggestions.php" name="search" value="<?= e($search) ?>" placeholder="Name or LRN"></label>
            </div>
            <button type="submit" class="button button-primary">Search</button>
            <details class="table-search-disclosure">
                <summary class="button button-outline">
                    Filters<?= ($programFilter || $statusFilter !== '') ? ' (active)' : '' ?>
                </summary>
                <div class="table-search-panel">
                    <h3 class="table-search-panel-title">Filter results</h3>
                    <div class="table-search-fields">
                        <label>Special Program
                            <select name="program"><option value="">All Programs</option><?php foreach ($programs as $program): ?><option value="<?= (int) $program['program_id'] ?>" <?= $programFilter === (int) $program['program_id'] ? 'selected' : '' ?>><?= e($program['program_name']) ?></option><?php endforeach; ?></select>
                        </label>
                        <label>Claiming Status
                            <select name="status"><option value="">All Statuses</option><?php foreach ($validStatuses as $status): ?><option value="<?= e($status) ?>" <?= $statusFilter === $status ? 'selected' : '' ?>><?= e($status) ?></option><?php endforeach; ?></select>
                        </label>
                    </div>
                    <div class="table-search-actions">
                        <button type="submit" class="button button-primary">Apply filters</button>
                        <a class="button button-outline" href="<?= BASE_URL ?>/scholarship_coordinator/stipend_records.php">Clear filters</a>
                    </div>
                </div>
            </details>
        </div>
    </form>

        <div class="stipend-table-wrap">
            <table>
                <thead><tr><th>Recipient</th><th>Scholar</th><th>Program</th><th>School Year</th><th>Amount</th><th>Release Date</th><th>Claiming Status</th><th>Remarks</th></tr></thead>
                <tbody>
                <?php foreach ($records as $record): ?>
                    <?php $statusClass = $record['claiming_status'] === 'Claimed' ? 'claimed' : ($record['claiming_status'] === 'Unclaimed' ? 'unclaimed' : 'pending'); ?>
                    <tr>
                        <td><?= e($record['recipient_name']) ?></td>
                        <td><?= e($record['first_name'] . ' ' . $record['last_name']) ?><span class="stipend-muted"><?= e($record['lrn'] ?: 'No LRN recorded') ?></span></td>
                        <td><?= e($record['program_name']) ?></td>
                        <td><?= e($record['school_year']) ?></td>
                        <td>₱<?= number_format((float) $record['amount'], 2) ?></td>
                        <td><?= e($record['release_date'] ?: 'Not scheduled') ?></td>
                        <td><span class="stipend-status <?= e($statusClass) ?>"><?= e($record['claiming_status']) ?></span></td>
                        <td><?= e($record['remarks'] ?: '—') ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$records): ?><tr><td colspan="8" class="stipend-empty">No stipend records match your selected filters.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>

<script src="<?= BASE_URL ?>/assets/js/student_suggestions.js" defer></script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
