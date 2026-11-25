<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('School Registrar');

$pageTitle = 'Section Management';
$error = '';
$success = '';

$editingSection = null;

// Display feedback returned by registrar/delete.php.
if (isset($_GET['deleted']) && $_GET['deleted'] === '1') {
    $success = 'Section deleted successfully.';
}

if (isset($_GET['error'])) {
    switch ($_GET['error']) {
        case 'invalid':
            $error = 'Invalid section selected. Please try again.';
            break;
        case 'not_found':
            $error = 'The section was not found or has already been deleted.';
            break;
        case 'in_use':
            $error = 'This section cannot be deleted because it is still assigned to student records or referenced by other records.';
            break;
        case 'delete_failed':
            $error = 'Unable to delete the section. Please try again or check the PHP error log.';
            break;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
            throw new RuntimeException('Invalid request. Please refresh the page and try again.');
        }

        $action = $_POST['action'] ?? '';

        if ($action === 'create' || $action === 'update') {
            $name = trim($_POST['section_name'] ?? '');
            $gradeId = filter_var($_POST['grade_level_id'] ?? null, FILTER_VALIDATE_INT);
            $yearId = filter_var($_POST['school_year_id'] ?? null, FILTER_VALIDATE_INT);

            if ($name === '' || strlen($name) > 100 || !$gradeId || !$yearId) {
                throw new RuntimeException('Enter a section name and select a valid grade level and school year.');
            }

            // Confirm the selected grade and school year exist before saving.
            $validate = $pdo->prepare(
                'SELECT
                    (SELECT COUNT(*) FROM grade_levels WHERE grade_level_id = :grade_id) AS grade_exists,
                    (SELECT COUNT(*) FROM school_years WHERE school_year_id = :year_id) AS year_exists'
            );
            $validate->execute([
                'grade_id' => $gradeId,
                'year_id' => $yearId
            ]);
            $validSelection = $validate->fetch(PDO::FETCH_ASSOC);

            if (!$validSelection || !(int)$validSelection['grade_exists'] || !(int)$validSelection['year_exists']) {
                throw new RuntimeException('The selected grade level or school year is invalid. Please select again.');
            }

            if ($action === 'create') {
                $stmt = $pdo->prepare(
                    'INSERT INTO sections (section_name, grade_level_id, school_year_id)
                     VALUES (:name, :grade, :year)'
                );
                $stmt->execute([
                    'name' => $name,
                    'grade' => $gradeId,
                    'year' => $yearId
                ]);

                $success = 'Section created successfully.';
            } else {
                $sectionId = filter_var($_POST['section_id'] ?? null, FILTER_VALIDATE_INT);

                if (!$sectionId) {
                    throw new RuntimeException('Invalid section selected for editing.');
                }

                $exists = $pdo->prepare('SELECT section_id FROM sections WHERE section_id = :id');
                $exists->execute(['id' => $sectionId]);

                if (!$exists->fetchColumn()) {
                    throw new RuntimeException('The section you are trying to edit no longer exists.');
                }

                $stmt = $pdo->prepare(
                    'UPDATE sections
                     SET section_name = :name,
                         grade_level_id = :grade,
                         school_year_id = :year
                     WHERE section_id = :id'
                );
                $stmt->execute([
                    'name' => $name,
                    'grade' => $gradeId,
                    'year' => $yearId,
                    'id' => $sectionId
                ]);

                $success = 'Section updated successfully.';
            }
        } elseif ($action === 'delete') {
            $sectionId = filter_var($_POST['section_id'] ?? null, FILTER_VALIDATE_INT);

            if (!$sectionId) {
                throw new RuntimeException('Invalid section selected.');
            }

            // Confirm the section exists before attempting deletion.
            $sectionCheck = $pdo->prepare(
                'SELECT section_id FROM sections WHERE section_id = :id'
            );
            $sectionCheck->execute(['id' => $sectionId]);

            if (!$sectionCheck->fetchColumn()) {
                throw new RuntimeException('The section was not found or has already been deleted.');
            }

            // Find records in tables that have a foreign key pointing to this section.
            // This allows us to explain why MySQL prevents deletion instead of failing silently.
            $referenceQuery = $pdo->prepare(
                'SELECT TABLE_NAME, COLUMN_NAME
                 FROM information_schema.KEY_COLUMN_USAGE
                 WHERE REFERENCED_TABLE_SCHEMA = DATABASE()
                   AND REFERENCED_TABLE_NAME = :table_name
                   AND REFERENCED_COLUMN_NAME = :column_name'
            );
            $referenceQuery->execute([
                'table_name' => 'sections',
                'column_name' => 'section_id'
            ]);

            $references = $referenceQuery->fetchAll(PDO::FETCH_ASSOC);
            $blockingRecords = [];

            foreach ($references as $reference) {
                $tableName = str_replace('`', '``', $reference['TABLE_NAME']);
                $columnName = str_replace('`', '``', $reference['COLUMN_NAME']);

                $countSql = "SELECT COUNT(*) FROM `{$tableName}` WHERE `{$columnName}` = :section_id";
                $countQuery = $pdo->prepare($countSql);
                $countQuery->execute(['section_id' => $sectionId]);
                $count = (int)$countQuery->fetchColumn();

                if ($count > 0) {
                    $blockingRecords[] = $reference['TABLE_NAME'] . ' (' . $count . ')';
                }
            }

            if ($blockingRecords) {
                throw new RuntimeException(
                    'This section cannot be deleted because it is still referenced by: ' .
                    implode(', ', $blockingRecords) .
                    '. Reassign or remove those related records first.'
                );
            }

            $stmt = $pdo->prepare('DELETE FROM sections WHERE section_id = :id');
            $stmt->execute(['id' => $sectionId]);

            if ($stmt->rowCount() !== 1) {
                throw new RuntimeException('The section was not deleted. Please refresh the page and try again.');
            }

            $success = 'Section deleted successfully.';
        } else {
            throw new RuntimeException('Invalid section action.');
        }
    } catch (PDOException $ex) {
        if ($ex->getCode() === '23000') {
            $error = 'The database prevented this change because the section is still being used by related records. Reassign or remove those records first.';
        } else {
            $error = 'Unable to complete the section action. Please try again. Details have been recorded in the PHP error log.';
        }

        error_log('Section management: ' . $ex->getMessage());
    } catch (RuntimeException $ex) {
        $error = $ex->getMessage();
    }
}

$grades = $pdo->query(
    'SELECT grade_level_id, grade_level
     FROM grade_levels
     ORDER BY grade_level_id'
)->fetchAll(PDO::FETCH_ASSOC);

$years = $pdo->query(
    'SELECT school_year_id, school_year, status
     FROM school_years
     ORDER BY school_year_id DESC'
)->fetchAll(PDO::FETCH_ASSOC);

// Load a selected section into the edit form.
$editId = filter_var($_GET['edit'] ?? null, FILTER_VALIDATE_INT);

if ($editId) {
    $editStmt = $pdo->prepare(
        'SELECT section_id, section_name, grade_level_id, school_year_id
         FROM sections
         WHERE section_id = :id'
    );
    $editStmt->execute(['id' => $editId]);
    $editingSection = $editStmt->fetch(PDO::FETCH_ASSOC) ?: null;

    if (!$editingSection && !$error) {
        $error = 'The section you selected for editing could not be found.';
    }
}

$sections = $pdo->query(
    'SELECT
        s.section_id,
        s.section_name,
        s.grade_level_id,
        s.school_year_id,
        g.grade_level,
        y.school_year
     FROM sections s
     JOIN grade_levels g ON g.grade_level_id = s.grade_level_id
     JOIN school_years y ON y.school_year_id = s.school_year_id
     ORDER BY y.school_year_id DESC, g.grade_level_id, s.section_name'
)->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/../includes/header.php';
?>
<section class="form-card form-card-wide">
    <h1>Section Management</h1>
    <p class="muted">Create and review class sections by grade level and school year.</p>

    <?php if ($success): ?>
        <p class="success-message"><?= e($success) ?></p>
    <?php endif; ?>

    <?php if ($error): ?>
        <p class="error-message"><?= e($error) ?></p>
    <?php endif; ?>

    <?php if ($editingSection): ?>
        <h2 class="section-edit-heading">Edit Section</h2>

        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="section_id" value="<?= (int)$editingSection['section_id'] ?>">

            <label>
                Section name
                <input
                    name="section_name"
                    maxlength="100"
                    value="<?= e($editingSection['section_name']) ?>"
                    required
                >
            </label>

            <label>
                Grade level
                <select name="grade_level_id" required>
                    <option value="">Select grade</option>
                    <?php foreach ($grades as $g): ?>
                        <option
                            value="<?= (int)$g['grade_level_id'] ?>"
                            <?= (int)$editingSection['grade_level_id'] === (int)$g['grade_level_id'] ? 'selected' : '' ?>
                        >
                            Grade <?= e($g['grade_level']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label>
                School year
                <select name="school_year_id" required>
                    <option value="">Select school year</option>
                    <?php foreach ($years as $y): ?>
                        <option
                            value="<?= (int)$y['school_year_id'] ?>"
                            <?= (int)$editingSection['school_year_id'] === (int)$y['school_year_id'] ? 'selected' : '' ?>
                        >
                            <?= e($y['school_year']) ?><?= $y['status'] === 'Active' ? ' (Active)' : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <div class="section-form-actions">
                <button class="button button-primary" type="submit">Save Changes</button>
                <a class="button button-secondary" href="<?= e(BASE_URL) ?>/registrar/sections.php">Cancel</a>
            </div>
        </form>
    <?php else: ?>
        <h2>Add Section</h2>

        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="create">

            <label>
                Section name
                <input name="section_name" maxlength="100" required>
            </label>

            <label>
                Grade level
                <select name="grade_level_id" required>
                    <option value="">Select grade</option>
                    <?php foreach ($grades as $g): ?>
                        <option value="<?= (int)$g['grade_level_id'] ?>">
                            Grade <?= e($g['grade_level']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label>
                School year
                <select name="school_year_id" required>
                    <option value="">Select school year</option>
                    <?php foreach ($years as $y): ?>
                        <option value="<?= (int)$y['school_year_id'] ?>">
                            <?= e($y['school_year']) ?><?= $y['status'] === 'Active' ? ' (Active)' : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <button class="button button-primary" type="submit">Add Section</button>
        </form>
    <?php endif; ?>

    <h2>Existing Sections</h2>

    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Section</th>
                    <th>Grade</th>
                    <th>School Year</th>
                    <th>Action</th>
                </tr>
            </thead>

            <tbody>
                <?php foreach ($sections as $row): ?>
                    <tr>
                        <td><?= e($row['section_name']) ?></td>
                        <td><?= e($row['grade_level']) ?></td>
                        <td><?= e($row['school_year']) ?></td>
                        <td>
                            <div class="section-row-actions">
                                <a
                                    class="button button-outline"
                                    href="<?= e(BASE_URL) ?>/registrar/sections.php?edit=<?= (int)$row['section_id'] ?>"
                                >
                                    Edit
                                </a>

                                <form
                                    method="post"
                                    action="<?= e(BASE_URL) ?>/registrar/delete.php"
                                    onsubmit="return confirm('Are you sure you want to delete this section? Sections assigned to students or other records cannot be deleted.');"
                                >
                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="section_id" value="<?= (int)$row['section_id'] ?>">
                                    <button class="button button-secondary" type="submit">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>

                <?php if (!$sections): ?>
                    <tr>
                        <td colspan="4">No sections have been created.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<style>
.section-row-actions,
.section-form-actions {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}

.section-row-actions form {
    margin: 0;
}

.section-row-actions .button,
.section-form-actions .button {
    white-space: nowrap;
}

@media (max-width: 600px) {
    .section-row-actions {
        align-items: stretch;
        flex-direction: column;
    }

    .section-row-actions .button {
        width: 100%;
        text-align: center;
    }
}
</style>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
