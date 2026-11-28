<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('System Administrator','Scholarship Coordinator');
$pageTitle='Scholarship Monitoring';$error='';$success = ($_GET['saved'] ?? '') === '1' ? 'Scholarship monitoring record saved.' : '';
if($_SERVER['REQUEST_METHOD']==='POST'){
 try{
  if(!verify_csrf_token($_POST['csrf_token']??null))throw new RuntimeException('Invalid request. Refresh the page.');
  $appId=filter_var($_POST['scholarship_application_id']??null,FILTER_VALIDATE_INT);$performance=trim($_POST['academic_performance']??'');$behavior=trim($_POST['behavior_remarks']??'');$participation=$_POST['participation_status']??'';$eligibility=$_POST['renewal_eligibility']??'';$date=$_POST['monitoring_date']??date('Y-m-d');
  if(!$appId||!in_array($participation,['Qualified','Currently Enrolled','Removed'],true)||!in_array($eligibility,['Eligible','Not Eligible','Pending'],true)||!preg_match('/^\d{4}-\d{2}-\d{2}$/',$date))throw new RuntimeException('Complete the monitoring fields with valid values.');
  $check=$pdo->prepare("SELECT scholarship_application_id FROM scholarship_applications WHERE scholarship_application_id=:id AND application_status='Approved'");$check->execute(['id'=>$appId]);if(!$check->fetch())throw new RuntimeException('Select an approved scholarship application.');
  $q=$pdo->prepare('INSERT INTO scholarship_monitoring(scholarship_application_id,academic_performance,behavior_remarks,participation_status,renewal_eligibility,monitoring_date) VALUES(:app,:performance,:behavior,:participation,:eligibility,:date)');
  $q->execute(['app'=>$appId,'performance'=>$performance?:null,'behavior'=>$behavior?:null,'participation'=>$participation,'eligibility'=>$eligibility,'date'=>$date]);redirect('/scholarship_coordinator/scholarship_monitoring.php?saved=1');
 }catch(PDOException $ex){error_log('Scholarship monitoring: '.$ex->getMessage());$error='Unable to save the monitoring record.';}catch(RuntimeException $ex){$error=$ex->getMessage();}
}
$approved=$pdo->query("SELECT sa.scholarship_application_id,st.first_name,st.last_name,sp.program_name,y.school_year FROM scholarship_applications sa JOIN students st ON st.student_id=sa.student_id JOIN special_programs sp ON sp.program_id=sa.program_id JOIN school_years y ON y.school_year_id=sa.school_year_id WHERE sa.application_status='Approved' ORDER BY st.last_name,st.first_name")->fetchAll();
$records = $pdo->query("
    SELECT sm.*, st.first_name, st.middle_name, st.last_name, sp.program_name, sy.school_year
    FROM scholarship_applications sa
    JOIN students st ON st.student_id = sa.student_id
    JOIN special_programs sp ON sp.program_id = sa.program_id
    JOIN school_years sy ON sy.school_year_id = sa.school_year_id
    JOIN scholarship_monitoring sm ON sm.scholarship_application_id = sa.scholarship_application_id
    ORDER BY sm.monitoring_date DESC, sm.monitoring_id DESC
")->fetchAll();
$latestByApplication = [];
foreach ($records as $record) {
    $latestByApplication[$record['scholarship_application_id']] ??= $record;
}
$showForm = ($_GET['add'] ?? '') === '1' || ($_SERVER['REQUEST_METHOD'] === 'POST' && $error !== '');
$selectedScholar = filter_var($_POST['scholarship_application_id'] ?? $_GET['scholar'] ?? null, FILTER_VALIDATE_INT);
require_once __DIR__.'/../includes/header.php';
?>
<?php if ($showForm): ?>
<div class="portal-back-row">
    <a class="portal-back-link" href="?">Back to scholarship monitoring</a>
</div>
<?php endif; ?>
<section class="form-card form-card-wide">
    <div class="portal-page-actions scholarship-page-toolbar">
        <h1><?= $showForm ? 'New Monitoring Record' : 'Scholarship Monitoring' ?></h1>
        <?php if (!$showForm && $approved): ?>
            <a class="button button-primary portal-add-action" href="?add=1"><span class="portal-add-icon" aria-hidden="true">+</span> New Monitoring Record</a>
        <?php endif; ?>
    </div>
    <?php if ($success): ?><p class="success-message" role="status"><?= e($success) ?></p><?php endif; ?>
    <?php if ($error): ?><p class="error-message" role="alert"><?= e($error) ?></p><?php endif; ?>
    <?php if ($showForm): ?>
        <form method="post" class="form-grid two-columns">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <label>Approved Scholar
                <select name="scholarship_application_id" required>
                    <option value="">Select scholar</option>
                    <?php foreach ($approved as $scholar): ?>
                        <option value="<?= (int) $scholar['scholarship_application_id'] ?>" <?= (int) $scholar['scholarship_application_id'] === $selectedScholar ? 'selected' : '' ?>>
                            <?= e($scholar['last_name'] . ', ' . $scholar['first_name'] . ' · ' . $scholar['program_name'] . ' · ' . $scholar['school_year']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Monitoring Date<input type="date" name="monitoring_date" value="<?= e($_POST['monitoring_date'] ?? date('Y-m-d')) ?>" required></label>
            <label>Academic Performance<textarea name="academic_performance" rows="3"><?= e($_POST['academic_performance'] ?? '') ?></textarea></label>
            <label>Behavior Remarks<textarea name="behavior_remarks" rows="3"><?= e($_POST['behavior_remarks'] ?? '') ?></textarea></label>
            <label>Participation Status
                <select name="participation_status">
                    <?php foreach (['Qualified', 'Currently Enrolled', 'Removed'] as $status): ?>
                        <option <?= ($_POST['participation_status'] ?? '') === $status ? 'selected' : '' ?>><?= e($status) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Renewal Eligibility
                <select name="renewal_eligibility">
                    <?php foreach (['Pending', 'Eligible', 'Not Eligible'] as $status): ?>
                        <option <?= ($_POST['renewal_eligibility'] ?? '') === $status ? 'selected' : '' ?>><?= e($status) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <div class="form-actions scholarship-form-footer">
                <button class="button button-primary" type="submit">Save Monitoring Record</button>
                <a class="button button-outline" href="?">Cancel</a>
            </div>
        </form>
    <?php else: ?>
        <h2>Approved Scholars</h2>
        <div class="table-responsive">
            <table class="portal-row-table">
                <thead><tr><th scope="col">Scholar</th><th scope="col">Program</th><th scope="col">School Year</th><th scope="col">Participation</th><th scope="col">Renewal</th><th scope="col">Last Monitored</th><th scope="col">Actions</th></tr></thead>
                <tbody>
                <?php foreach ($approved as $scholar): ?>
                    <?php $latest = $latestByApplication[$scholar['scholarship_application_id']] ?? []; ?>
                    <tr>
                        <td><?= e($scholar['last_name'] . ', ' . $scholar['first_name']) ?></td>
                        <td><?= e($scholar['program_name']) ?></td>
                        <td><?= e($scholar['school_year']) ?></td>
                        <td><?= e($latest['participation_status'] ?? 'Not Monitored') ?></td>
                        <td><?= e($latest['renewal_eligibility'] ?? '—') ?></td>
                        <td><?= e($latest['monitoring_date'] ?? '—') ?></td>
                        <td><a class="button button-outline" href="?add=1&amp;scholar=<?= (int) $scholar['scholarship_application_id'] ?>">Add record</a></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$approved): ?><tr><td colspan="7">No approved scholars yet.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
        <details>
            <summary>Monitoring History</summary>
            <div class="table-responsive">
                <table class="portal-row-table">
                    <thead><tr><th scope="col">Date</th><th scope="col">Scholar</th><th scope="col">Program</th><th scope="col">Participation</th><th scope="col">Renewal</th><th scope="col">Notes</th></tr></thead>
                    <tbody>
                    <?php foreach ($records as $record): ?>
                        <tr>
                            <td><?= e($record['monitoring_date']) ?></td>
                            <td><?= e($record['last_name'] . ', ' . $record['first_name']) ?></td>
                            <td><?= e($record['program_name']) ?></td>
                            <td><?= e($record['participation_status']) ?></td>
                            <td><?= e($record['renewal_eligibility']) ?></td>
                            <td><?= e(trim(($record['academic_performance'] ?? '') . ' ' . ($record['behavior_remarks'] ?? '')) ?: '—') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$records): ?><tr><td colspan="6">No monitoring records yet.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </details>
    <?php endif; ?>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
