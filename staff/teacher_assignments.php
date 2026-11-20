<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('System Administrator', 'School Registrar');
$pageTitle = 'Teacher Section Assignments';
$error = ''; $success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!verify_csrf_token($_POST['csrf_token'] ?? null)) throw new RuntimeException('Invalid request. Refresh the page.');
        $action = $_POST['action'] ?? '';
        if ($action === 'assign') {
            $teacherId = filter_var($_POST['teacher_user_id'] ?? null, FILTER_VALIDATE_INT);
            $sectionId = filter_var($_POST['section_id'] ?? null, FILTER_VALIDATE_INT);
            $yearId = filter_var($_POST['school_year_id'] ?? null, FILTER_VALIDATE_INT);
            if (!$teacherId || !$sectionId || !$yearId) throw new RuntimeException('Select a teacher, section, and school year.');
            $check = $pdo->prepare("SELECT user_id FROM users u JOIN roles r ON r.role_id=u.role_id WHERE u.user_id=:id AND r.role_name='Teacher' AND u.account_status='Active'");
            $check->execute(['id'=>$teacherId]);
            if (!$check->fetch()) throw new RuntimeException('Select an active teacher account.');
            $sectionCheck = $pdo->prepare('SELECT section_id FROM sections WHERE section_id=:section AND school_year_id=:year');
            $sectionCheck->execute(['section'=>$sectionId,'year'=>$yearId]);
            if (!$sectionCheck->fetch()) throw new RuntimeException('The selected section does not belong to that school year.');
            $stmt=$pdo->prepare('INSERT INTO teacher_section_assignments (teacher_user_id,section_id,school_year_id,assigned_by) VALUES (:teacher,:section,:year,:admin)');
            $stmt->execute(['teacher'=>$teacherId,'section'=>$sectionId,'year'=>$yearId,'admin'=>current_user()['user_id']]);
            $success='Teacher assigned to section successfully.';
        } elseif ($action === 'remove') {
            $id=filter_var($_POST['assignment_id'] ?? null,FILTER_VALIDATE_INT);
            if (!$id) throw new RuntimeException('Invalid assignment.');
            $stmt=$pdo->prepare('DELETE FROM teacher_section_assignments WHERE assignment_id=:id');
            $stmt->execute(['id'=>$id]); $success='Assignment removed.';
        }
    } catch (PDOException $ex) {
        $error=$ex->getCode()==='23000'?'This teacher is already assigned to that section for the selected school year.':'Unable to save the assignment.';
        error_log('Teacher assignment: '.$ex->getMessage());
    } catch (RuntimeException $ex) {$error=$ex->getMessage();}
}
$teachers=$pdo->query("SELECT u.user_id,u.username,sp.first_name,sp.last_name FROM users u JOIN roles r ON r.role_id=u.role_id LEFT JOIN staff_profiles sp ON sp.user_id=u.user_id WHERE r.role_name='Teacher' AND u.account_status='Active' ORDER BY sp.last_name,u.username")->fetchAll();
$sections=$pdo->query('SELECT s.section_id,s.section_name,g.grade_level,y.school_year,y.school_year_id FROM sections s JOIN grade_levels g ON g.grade_level_id=s.grade_level_id JOIN school_years y ON y.school_year_id=s.school_year_id ORDER BY y.school_year_id DESC,g.grade_level_id,s.section_name')->fetchAll();
$years=$pdo->query('SELECT school_year_id,school_year FROM school_years ORDER BY school_year_id DESC')->fetchAll();
$assignments=$pdo->query("SELECT tsa.assignment_id,u.username,sp.first_name,sp.last_name,s.section_name,g.grade_level,y.school_year FROM teacher_section_assignments tsa JOIN users u ON u.user_id=tsa.teacher_user_id LEFT JOIN staff_profiles sp ON sp.user_id=u.user_id JOIN sections s ON s.section_id=tsa.section_id JOIN grade_levels g ON g.grade_level_id=s.grade_level_id JOIN school_years y ON y.school_year_id=tsa.school_year_id ORDER BY y.school_year_id DESC,sp.last_name")->fetchAll();
require_once __DIR__ . '/../includes/header.php';
?>
<section class="form-card form-card-wide"><h1>Teacher Section Assignments</h1><p class="muted">Assign active teacher accounts to class sections for a school year.</p>
<?php if($success):?><p class="success-message"><?=e($success)?></p><?php endif;?><?php if($error):?><p class="error-message"><?=e($error)?></p><?php endif;?>
<h2>Assign Teacher</h2><form method="post"><input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="assign">
<label>Teacher<select name="teacher_user_id" required><option value="">Select teacher</option><?php foreach($teachers as $t):?><option value="<?=(int)$t['user_id']?>"><?=e(trim(($t['first_name']??'').' '.($t['last_name']??'')) ?: $t['username'])?> (<?=e($t['username'])?>)</option><?php endforeach;?></select></label>
<label>Section<select name="section_id" required><option value="">Select section</option><?php foreach($sections as $s):?><option value="<?=(int)$s['section_id']?>"><?=e('Grade '.$s['grade_level'].' - '.$s['section_name'].' ('.$s['school_year'].')')?></option><?php endforeach;?></select></label>
<label>School year<select name="school_year_id" required><option value="">Select school year</option><?php foreach($years as $y):?><option value="<?=(int)$y['school_year_id']?>"><?=e($y['school_year'])?></option><?php endforeach;?></select></label><button class="button button-primary">Save Assignment</button></form>
<h2>Current Assignments</h2><div class="table-responsive"><table><thead><tr><th>Teacher</th><th>Section</th><th>School Year</th><th>Action</th></tr></thead><tbody><?php foreach($assignments as $a):?><tr><td><?=e(trim(($a['first_name']??'').' '.($a['last_name']??'')) ?: $a['username'])?></td><td><?=e('Grade '.$a['grade_level'].' - '.$a['section_name'])?></td><td><?=e($a['school_year'])?></td><td><form method="post" onsubmit="return confirm('Remove this assignment?');"><input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="remove"><input type="hidden" name="assignment_id" value="<?=(int)$a['assignment_id']?>"><button class="button button-secondary">Remove</button></form></td></tr><?php endforeach;?><?php if(!$assignments):?><tr><td colspan="4">No teacher assignments yet.</td></tr><?php endif;?></tbody></table></div></section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
