<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('Student');
$pageTitle='Scholarship Application Status';
$userId=(int)current_user()['user_id'];
$studentQuery=$pdo->prepare('SELECT student_id,first_name,last_name FROM students WHERE user_id=:user LIMIT 1');
$studentQuery->execute(['user'=>$userId]);$student=$studentQuery->fetch();
if(!$student){http_response_code(404);exit('Student record not found.');}
$q=$pdo->prepare("SELECT sa.scholarship_application_id,sa.application_status,sa.remarks,sa.application_date,sp.program_name,sp.description,y.school_year FROM scholarship_applications sa JOIN special_programs sp ON sp.program_id=sa.program_id JOIN school_years y ON y.school_year_id=sa.school_year_id WHERE sa.student_id=:student ORDER BY sa.application_date DESC");
$q->execute(['student'=>$student['student_id']]);$applications=$q->fetchAll();
require_once __DIR__.'/../includes/header.php';
?>
<section class="form-card form-card-wide"><h1>Scholarship Application Status</h1><p class="muted">View the status and remarks for your special program scholarship applications.</p>
<?php if(!$applications):?><div class="empty-state"><h2>No Scholarship Applications</h2><p>You do not have any scholarship applications recorded yet. If you selected a special program during enrollment, its application will appear here once submitted.</p><a class="button button-primary" href="<?=BASE_URL?>/student/my_applications.php">View My Applications</a></div><?php else:?>
<div class="table-responsive"><table><thead><tr><th>Program</th><th>School Year</th><th>Application Date</th><th>Status</th><th>Remarks</th></tr></thead><tbody><?php foreach($applications as $a):?><tr><td><strong><?=e($a['program_name'])?></strong><br><small><?=e($a['description']??'')?></small></td><td><?=e($a['school_year'])?></td><td><?=e($a['application_date'])?></td><td><?=e($a['application_status'])?></td><td><?=e($a['remarks']??'No remarks provided.')?></td></tr><?php endforeach;?></tbody></table></div><?php endif;?></section>
<?php require_once __DIR__.'/../includes/footer.php';?>
