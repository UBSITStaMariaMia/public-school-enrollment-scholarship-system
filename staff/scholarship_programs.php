<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('System Administrator','Scholarship Coordinator');
$pageTitle='Scholarship Programs';$error='';$success='';
if($_SERVER['REQUEST_METHOD']==='POST'){
 try{
  if(!verify_csrf_token($_POST['csrf_token']??null))throw new RuntimeException('Invalid request. Refresh the page.');
  $action=$_POST['action']??'';
  if($action==='save'){
   $id=filter_var($_POST['program_id']??null,FILTER_VALIDATE_INT)?:null;$name=trim($_POST['program_name']??'');$description=trim($_POST['description']??'');$slots=($_POST['available_slots']??'')===''?null:filter_var($_POST['available_slots'],FILTER_VALIDATE_INT);$status=$_POST['status']??'Active';
   if($name===''||strlen($name)>100||!in_array($status,['Active','Inactive'],true)||($slots!==null&&($slots===false||$slots<0)))throw new RuntimeException('Enter a valid program name, status, and available slot count.');
   if($id){$q=$pdo->prepare('UPDATE special_programs SET program_name=:name,description=:description,available_slots=:slots,status=:status WHERE program_id=:id');$q->execute(['name'=>$name,'description'=>$description?:null,'slots'=>$slots,'status'=>$status,'id'=>$id]);}
   else{$q=$pdo->prepare('INSERT INTO special_programs(program_name,description,available_slots,status) VALUES(:name,:description,:slots,:status)');$q->execute(['name'=>$name,'description'=>$description?:null,'slots'=>$slots,'status'=>$status]);}
   $success='Scholarship program saved.';
  }elseif($action==='toggle'){
   $id=filter_var($_POST['program_id']??null,FILTER_VALIDATE_INT);if(!$id)throw new RuntimeException('Invalid program.');
   $q=$pdo->prepare("UPDATE special_programs SET status=IF(status='Active','Inactive','Active') WHERE program_id=:id");$q->execute(['id'=>$id]);$success='Program status updated.';
  }
 }catch(PDOException $ex){$error=$ex->getCode()==='23000'?'A program with that name already exists.':'Unable to save the program.';error_log('Scholarship programs: '.$ex->getMessage());}catch(RuntimeException $ex){$error=$ex->getMessage();}
}
$editId=filter_input(INPUT_GET,'edit',FILTER_VALIDATE_INT);$editing=null;if($editId){$q=$pdo->prepare('SELECT * FROM special_programs WHERE program_id=:id');$q->execute(['id'=>$editId]);$editing=$q->fetch()?:null;}
$programs=$pdo->query('SELECT sp.*, (SELECT COUNT(*) FROM scholarship_applications sa WHERE sa.program_id=sp.program_id) AS application_count FROM special_programs sp ORDER BY sp.program_name')->fetchAll();
require_once __DIR__.'/../includes/header.php';
?>
<section class="form-card form-card-wide"><h1>Scholarship Programs</h1><p class="muted">Create and maintain the special programs available to students.</p><?php if($success):?><p class="success-message"><?=e($success)?></p><?php endif;?><?php if($error):?><p class="error-message"><?=e($error)?></p><?php endif;?>
<h2><?=$editing?'Edit Program':'Add Program'?></h2><form method="post"><input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="save"><input type="hidden" name="program_id" value="<?=(int)($editing['program_id']??0)?>">
<label>Program name<input name="program_name" maxlength="100" required value="<?=e($editing['program_name']??'')?>"></label><label>Description<textarea name="description" rows="3"><?=e($editing['description']??'')?></textarea></label><label>Available slots (leave blank for unlimited)<input type="number" min="0" name="available_slots" value="<?=e(isset($editing['available_slots'])?(string)$editing['available_slots']:'')?>"></label><label>Status<select name="status"><option <?=($editing['status']??'Active')==='Active'?'selected':''?>>Active</option><option <?=($editing['status']??'')==='Inactive'?'selected':''?>>Inactive</option></select></label><button class="button button-primary">Save Program</button><?php if($editing):?> <a href="<?=BASE_URL?>/staff/scholarship_programs.php">Cancel</a><?php endif;?></form>
<h2>Existing Programs</h2><div class="table-responsive"><table><thead><tr><th>Program</th><th>Slots</th><th>Applications</th><th>Status</th><th>Actions</th></tr></thead><tbody><?php foreach($programs as $p):?><tr><td><strong><?=e($p['program_name'])?></strong><br><?=e($p['description']??'')?></td><td><?=e($p['available_slots']===null?'Unlimited':(string)$p['available_slots'])?></td><td><?=(int)$p['application_count']?></td><td><?=e($p['status'])?></td><td><a href="<?=BASE_URL?>/staff/scholarship_programs.php?edit=<?=(int)$p['program_id']?>">Edit</a><form method="post" style="display:inline"><input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="toggle"><input type="hidden" name="program_id" value="<?=(int)$p['program_id']?>"><button class="button button-secondary">Toggle status</button></form></td></tr><?php endforeach;?></tbody></table></div></section>
<?php require_once __DIR__.'/../includes/footer.php';?>
