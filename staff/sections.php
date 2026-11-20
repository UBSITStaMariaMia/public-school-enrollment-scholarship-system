<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('System Administrator', 'School Registrar');

$pageTitle = 'Section Management';
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
            throw new RuntimeException('Invalid request. Please refresh the page.');
        }
        $action = $_POST['action'] ?? '';
        if ($action === 'create') {
            $name = trim($_POST['section_name'] ?? '');
            $gradeId = filter_var($_POST['grade_level_id'] ?? null, FILTER_VALIDATE_INT);
            $yearId = filter_var($_POST['school_year_id'] ?? null, FILTER_VALIDATE_INT);
            if ($name === '' || strlen($name) > 100 || !$gradeId || !$yearId) {
                throw new RuntimeException('Enter a section name and select a valid grade level and school year.');
            }
            $stmt = $pdo->prepare('INSERT INTO sections (section_name, grade_level_id, school_year_id) VALUES (:name, :grade, :year)');
            $stmt->execute(['name' => $name, 'grade' => $gradeId, 'year' => $yearId]);
            $success = 'Section created successfully.';
        } elseif ($action === 'delete') {
            $sectionId = filter_var($_POST['section_id'] ?? null, FILTER_VALIDATE_INT);
            if (!$sectionId) throw new RuntimeException('Invalid section selected.');
            $stmt = $pdo->prepare('DELETE FROM sections WHERE section_id = :id');
            $stmt->execute(['id' => $sectionId]);
            $success = 'Section deleted successfully.';
        }
    } catch (PDOException $ex) {
        $error = $ex->getCode() === '23000'
            ? 'This section is already assigned for the selected grade level and school year, or it is currently in use.'
            : 'Unable to save the section. Please check related records.';
        error_log('Section management: '.$ex->getMessage());
    } catch (RuntimeException $ex) { $error = $ex->getMessage(); }
}

$grades = $pdo->query('SELECT grade_level_id, grade_level FROM grade_levels ORDER BY grade_level_id')->fetchAll();
$years = $pdo->query('SELECT school_year_id, school_year, status FROM school_years ORDER BY school_year_id DESC')->fetchAll();
$sections = $pdo->query('SELECT s.section_id, s.section_name, g.grade_level, y.school_year FROM sections s JOIN grade_levels g ON g.grade_level_id=s.grade_level_id JOIN school_years y ON y.school_year_id=s.school_year_id ORDER BY y.school_year_id DESC, g.grade_level_id, s.section_name')->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>
<section class="form-card form-card-wide">
<h1>Section Management</h1>
<p class="muted">Create and review class sections by grade level and school year.</p>
<?php if ($success): ?><p class="success-message"><?= e($success) ?></p><?php endif; ?>
<?php if ($error): ?><p class="error-message"><?= e($error) ?></p><?php endif; ?>
<h2>Add Section</h2>
<form method="post">
<input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
<input type="hidden" name="action" value="create">
<label>Section name<input name="section_name" maxlength="100" required></label>
<label>Grade level<select name="grade_level_id" required><option value="">Select grade</option><?php foreach ($grades as $g): ?><option value="<?= (int)$g['grade_level_id'] ?>">Grade <?= e($g['grade_level']) ?></option><?php endforeach; ?></select></label>
<label>School year<select name="school_year_id" required><option value="">Select school year</option><?php foreach ($years as $y): ?><option value="<?= (int)$y['school_year_id'] ?>"><?= e($y['school_year']) ?><?= $y['status']==='Active'?' (Active)':'' ?></option><?php endforeach; ?></select></label>
<button class="button button-primary" type="submit">Add Section</button>
</form>
<h2>Existing Sections</h2>
<div class="table-responsive"><table><thead><tr><th>Section</th><th>Grade</th><th>School Year</th><th>Action</th></tr></thead><tbody>
<?php foreach ($sections as $row): ?><tr><td><?= e($row['section_name']) ?></td><td><?= e($row['grade_level']) ?></td><td><?= e($row['school_year']) ?></td><td><form method="post" onsubmit="return confirm('Delete this section?');"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="section_id" value="<?= (int)$row['section_id'] ?>"><button class="button button-secondary" type="submit">Delete</button></form></td></tr><?php endforeach; ?>
<?php if (!$sections): ?><tr><td colspan="4">No sections have been created.</td></tr><?php endif; ?>
</tbody></table></div>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
