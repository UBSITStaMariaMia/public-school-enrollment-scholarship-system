<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('System Administrator', 'School Registrar', 'Enrollment Committee');
$pageTitle='Enrollment Document Verification'; $error=''; $success='';
$statuses=['Submitted','For Verification','Accepted','Rejected'];
if($_SERVER['REQUEST_METHOD']==='POST'){
 try{
  if(!verify_csrf_token($_POST['csrf_token']??null)) throw new RuntimeException('Invalid request. Refresh the page.');
  $id=filter_var($_POST['document_id']??null,FILTER_VALIDATE_INT);$status=$_POST['verification_status']??'';$remarks=trim($_POST['remarks']??'');
  if(!$id||!in_array($status,$statuses,true)) throw new RuntimeException('Select a valid document and verification status.');
  $stmt=$pdo->prepare('UPDATE enrollment_documents SET verification_status=:status,remarks=:remarks WHERE document_id=:id');
  $stmt->execute(['status'=>$status,'remarks'=>$remarks!==''?$remarks:null,'id'=>$id]);
  if(!$stmt->rowCount()){$check=$pdo->prepare('SELECT document_id FROM enrollment_documents WHERE document_id=:id');$check->execute(['id'=>$id]);if(!$check->fetch())throw new RuntimeException('Document record not found.');}
  $success='Document verification updated.';
 }catch(PDOException $ex){error_log('Document verification: '.$ex->getMessage());$error='Unable to update document verification.';}catch(RuntimeException $ex){$error=$ex->getMessage();}
}
$docs=$pdo->query("SELECT ed.document_id,ed.document_type,ed.file_path,ed.verification_status,ed.remarks,ed.uploaded_at,ea.application_id,st.first_name,st.last_name,ea.application_status FROM enrollment_documents ed JOIN enrollment_applications ea ON ea.application_id=ed.application_id JOIN students st ON st.student_id=ea.student_id ORDER BY ed.uploaded_at DESC,ed.document_id DESC")->fetchAll();
require_once __DIR__.'/../includes/header.php';
?>
<section class="form-card form-card-wide"><h1>Enrollment Document Verification</h1><p class="muted">Review submitted supporting documents for enrollment applications.</p>
<?php if($success):?><p class="success-message"><?=e($success)?></p><?php endif;?><?php if($error):?><p class="error-message"><?=e($error)?></p><?php endif;?>
<div class="table-responsive"><table><thead><tr><th>Student</th><th>Application</th><th>Document</th><th>File</th><th>Verification</th><th>Remarks / Action</th></tr></thead><tbody>
<?php foreach($docs as $d):?><tr><td><?=e($d['first_name'].' '.$d['last_name'])?></td><td>#<?=(int)$d['application_id']?></td><td><?=e($d['document_type'])?></td><td><?php if($d['file_path']):?><a href="<?=BASE_URL.'/'.e(ltrim($d['file_path'],'/'))?>" target="_blank" rel="noopener">Open file</a><?php else:?>No file<?php endif;?></td><td><?=e($d['verification_status'])?></td><td><form method="post"><input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>"><input type="hidden" name="document_id" value="<?=(int)$d['document_id']?>"><select name="verification_status"><?php foreach($statuses as $st):?><option value="<?=e($st)?>" <?=$d['verification_status']===$st?'selected':''?>><?=e($st)?></option><?php endforeach;?></select><textarea name="remarks" rows="2" placeholder="Remarks"><?=e($d['remarks']??'')?></textarea><button class="button button-primary">Update</button></form></td></tr><?php endforeach;?>
<?php if(!$docs):?><tr><td colspan="6">No enrollment document records found.</td></tr><?php endif;?></tbody></table></div></section>
<?php require_once __DIR__.'/../includes/footer.php';?>
