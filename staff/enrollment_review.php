<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('System Administrator', 'School Registrar', 'Enrollment Committee');
$pageTitle = 'Enrollment Review';
$error = ''; $success = '';
$allowedStatuses = ['Pending Review','Incomplete Requirements','Approved','Rejected'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!verify_csrf_token($_POST['csrf_token'] ?? null)) throw new RuntimeException('Invalid request. Refresh the page.');
        $id=filter_var($_POST['application_id'] ?? null,FILTER_VALIDATE_INT);
        $status=$_POST['application_status'] ?? '';
        $remarks=trim($_POST['remarks'] ?? '');
        $section=($_POST['assigned_section_id'] ?? '') !== '' ? filter_var($_POST['assigned_section_id'],FILTER_VALIDATE_INT) : null;
        if (!$id || !in_array($status,$allowedStatuses,true)) throw new RuntimeException('Select a valid application and status.');
        if ($section === false) throw new RuntimeException('Select a valid section.');
        $check=$pdo->prepare('SELECT application_id FROM enrollment_applications WHERE application_id=:id');
        $check->execute(['id'=>$id]);
        if (!$check->fetch()) throw new RuntimeException('Application not found.');
        if ($section) {
            $sectionCheck=$pdo->prepare('SELECT section_id FROM sections WHERE section_id=:id');
            $sectionCheck->execute(['id'=>$section]);
            if (!$sectionCheck->fetch()) throw new RuntimeException('Selected section does not exist.');
        }
        $stmt=$pdo->prepare('UPDATE enrollment_applications SET application_status=:status,remarks=:remarks,assigned_section_id=:section,reviewed_by=:reviewer,reviewed_at=NOW() WHERE application_id=:id');
        $stmt->execute(['status'=>$status,'remarks'=>$remarks !== ''?$remarks:null,'section'=>$section ?: null,'reviewer'=>current_user()['user_id'],'id'=>$id]);
        $success='Enrollment application updated.';
    } catch (PDOException $ex) {error_log('Enrollment review: '.$ex->getMessage());$error='Unable to update the application. Check the selected section and related records.';}
      catch (RuntimeException $ex) {$error=$ex->getMessage();}
}
$sections=$pdo->query('SELECT s.section_id,s.section_name,g.grade_level,y.school_year FROM sections s JOIN grade_levels g ON g.grade_level_id=s.grade_level_id JOIN school_years y ON y.school_year_id=s.school_year_id ORDER BY y.school_year_id DESC,g.grade_level_id,s.section_name')->fetchAll();
$applications=$pdo->query("SELECT ea.application_id,ea.lrn,ea.enrollment_type,ea.application_status,ea.remarks,ea.submitted_at,ea.assigned_section_id,st.first_name,st.middle_name,st.last_name,u.email,g.grade_level,y.school_year FROM enrollment_applications ea JOIN students st ON st.student_id=ea.student_id JOIN users u ON u.user_id=st.user_id JOIN grade_levels g ON g.grade_level_id=ea.requested_grade_level_id JOIN school_years y ON y.school_year_id=ea.school_year_id ORDER BY ea.submitted_at DESC")->fetchAll();
require_once __DIR__ . '/../includes/header.php';
?>
<section class="form-card form-card-wide"><h1>Enrollment Review</h1><p class="muted">Review submitted enrollment applications and record decisions.</p>
<?php if($success):?><p class="success-message"><?=e($success)?></p><?php endif;?><?php if($error):?><p class="error-message"><?=e($error)?></p><?php endif;?>
<?php foreach($applications as $a):?><article class="application-card"><h2>Application #<?=(int)$a['application_id']?> — <?=e(trim($a['first_name'].' '.$a['middle_name'].' '.$a['last_name']))?></h2>
<p><strong>Email:</strong> <?=e($a['email'])?> | <strong>LRN:</strong> <?=e($a['lrn']??'')?> | <strong>Type:</strong> <?=e($a['enrollment_type'])?></p><p><strong>Requested grade:</strong> <?=e($a['grade_level'])?> | <strong>School year:</strong> <?=e($a['school_year'])?> | <strong>Submitted:</strong> <?=e($a['submitted_at'])?></p>
<p><strong>Current status:</strong> <?=e($a['application_status'])?></p>
<form method="post"><input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>"><input type="hidden" name="application_id" value="<?=(int)$a['application_id']?>">
<label>Application status<select name="application_status" required><?php foreach($allowedStatuses as $st):?><option value="<?=e($st)?>" <?=$a['application_status']===$st?'selected':''?>><?=e($st)?></option><?php endforeach;?></select></label>
<label>Assign section (optional)<select name="assigned_section_id"><option value="">No section assigned</option><?php foreach($sections as $sec):?><option value="<?=(int)$sec['section_id']?>" <?=((int)$a['assigned_section_id']===(int)$sec['section_id'])?'selected':''?>><?=e('Grade '.$sec['grade_level'].' - '.$sec['section_name'].' ('.$sec['school_year'].')')?></option><?php endforeach;?></select></label>
<label>Review remarks<textarea name="remarks" rows="2"><?=e($a['remarks']??'')?></textarea></label><button class="button button-primary">Save Review</button></form></article><?php endforeach;?>
<?php if(!$applications):?><p>No enrollment applications have been submitted.</p><?php endif;?></section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
