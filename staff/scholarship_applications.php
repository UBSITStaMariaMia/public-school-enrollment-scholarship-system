<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('System Administrator','Scholarship Coordinator');
$pageTitle='Scholarship Applications';$error='';$success='';$statuses=['Pending Review','For Screening','Approved','Rejected'];
if($_SERVER['REQUEST_METHOD']==='POST'){
 try{
  if(!verify_csrf_token($_POST['csrf_token']??null))throw new RuntimeException('Invalid request. Refresh the page.');
  $id=filter_var($_POST['scholarship_application_id']??null,FILTER_VALIDATE_INT);$status=$_POST['application_status']??'';$remarks=trim($_POST['remarks']??'');
  if(!$id||!in_array($status,$statuses,true))throw new RuntimeException('Select a valid application and status.');
  $q=$pdo->prepare('UPDATE scholarship_applications SET application_status=:status,remarks=:remarks,reviewed_by=:reviewer,reviewed_at=NOW() WHERE scholarship_application_id=:id');$q->execute(['status'=>$status,'remarks'=>$remarks?:null,'reviewer'=>current_user()['user_id'],'id'=>$id]);
  $success='Scholarship application updated.';
 }catch(PDOException $ex){error_log('Scholarship application review: '.$ex->getMessage());$error='Unable to update the application.';}catch(RuntimeException $ex){$error=$ex->getMessage();}
}
$apps=$pdo->query("SELECT sa.scholarship_application_id,sa.application_status,sa.remarks,sa.application_date,sp.program_name,st.first_name,st.last_name,st.lrn,y.school_year FROM scholarship_applications sa JOIN special_programs sp ON sp.program_id=sa.program_id JOIN students st ON st.student_id=sa.student_id JOIN school_years y ON y.school_year_id=sa.school_year_id ORDER BY sa.application_date DESC")->fetchAll();
require_once __DIR__.'/../includes/header.php';
?>
<section class="form-card form-card-wide"><h1>Scholarship Applications</h1><p class="muted">Review student applications for special programs and record screening decisions.</p><?php if($success):?><p class="success-message"><?=e($success)?></p><?php endif;?><?php if($error):?><p class="error-message"><?=e($error)?></p><?php endif;?>
<div class="table-responsive"><table><thead><tr><th>Student</th><th>Program</th><th>School Year</th><th>Date</th><th>Review</th></tr></thead><tbody><?php foreach($apps as $a):?><tr><td><?=e($a['first_name'].' '.$a['last_name'])?><br><small>LRN: <?=e($a['lrn']??'')?></small></td><td><?=e($a['program_name'])?></td><td><?=e($a['school_year'])?></td><td><?=e($a['application_date'])?></td><td><form method="post"><input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>"><input type="hidden" name="scholarship_application_id" value="<?=(int)$a['scholarship_application_id']?>"><select name="application_status"><?php foreach($statuses as $st):?><option value="<?=e($st)?>" <?=$a['application_status']===$st?'selected':''?>><?=e($st)?></option><?php endforeach;?></select><textarea name="remarks" rows="2" placeholder="Screening remarks"><?=e($a['remarks']??'')?></textarea><button class="button button-primary">Save Review</button></form></td></tr><?php endforeach;?><?php if(!$apps):?><tr><td colspan="5">No scholarship applications submitted.</td></tr><?php endif;?></tbody></table></div></section>
<?php require_once __DIR__.'/../includes/footer.php';?>
