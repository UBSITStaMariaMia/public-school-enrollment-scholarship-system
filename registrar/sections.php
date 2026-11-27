<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('School Registrar');

$pageTitle = 'Section Management';
$error = '';
$success = $_SESSION['section_feedback'] ?? '';
unset($_SESSION['section_feedback']);

$editingSection = null;
$search = is_string($_GET['q'] ?? null) ? mb_substr(trim($_GET['q']), 0, 100) : '';
$gradeFilter = $_GET['grade'] ?? '';
if (!in_array($gradeFilter, ['7', '8', '9', '10'], true)) {
    $gradeFilter = '';
}
$yearFilter = filter_var($_GET['year'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: '';
$sectionFilters = array_filter(['q' => $search, 'grade' => $gradeFilter, 'year' => $yearFilter]);
$listPath = BASE_URL . '/registrar/sections.php';
$listQuery = $sectionFilters ? '?' . http_build_query($sectionFilters) : '';
$listUrl = $listPath . $listQuery;
$addUrl = $listPath . '?' . http_build_query($sectionFilters + ['add' => '1']);
$isAdding = ($_GET['add'] ?? '') === '1';
$deleteUrl = BASE_URL . '/registrar/delete.php' . ($sectionFilters ? '?' . http_build_query($sectionFilters) : '');

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
        $token = $_POST['csrf_token'] ?? null;
        if (!is_string($token) || !verify_csrf_token($token)) {
            throw new RuntimeException('Invalid request. Please refresh the page and try again.');
        }

        $action = $_POST['action'] ?? '';

        if ($action === 'create' || $action === 'update') {
            $name = is_string($_POST['section_name'] ?? null) ? trim($_POST['section_name']) : '';
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

                $_SESSION['section_feedback'] = 'Section created successfully.';
                redirect('/registrar/sections.php' . $listQuery);
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

                $_SESSION['section_feedback'] = 'Section updated successfully.';
                redirect('/registrar/sections.php' . $listQuery);
            }
        } else {
            throw new RuntimeException('Invalid section action.');
        }
    } catch (PDOException $ex) {
        if ((int) ($ex->errorInfo[1] ?? 0) === 1062) {
            $error = 'A section with this name already exists for that grade and school year.';
        } elseif ($ex->getCode() === '23000') {
            $error = 'The database prevented this change because the section is still being used by related records.';
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

$showSectionForm = $isAdding || $editingSection !== null;
if ($showSectionForm) {
    $pageTitle = $editingSection ? 'Edit Section' : 'Add Section';
    $formName = $editingSection['section_name'] ?? '';
    $formGrade = $editingSection['grade_level_id'] ?? '';
    $formYear = $editingSection['school_year_id'] ?? $yearFilter;
    if (!$editingSection && $gradeFilter !== '') {
        foreach ($grades as $grade) {
            if ((string) $grade['grade_level'] === $gradeFilter) {
                $formGrade = $grade['grade_level_id'];
                break;
            }
        }
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $formName = $name ?? $formName;
        $formGrade = $gradeId ?? $formGrade;
        $formYear = $yearId ?? $formYear;
    }
} else {
    $conditions = [];
    $params = [];
    if ($search !== '') {
        $conditions[] = "s.section_name LIKE :search ESCAPE '!'";
        $params['search'] = '%' . strtr($search, ['!' => '!!', '%' => '!%', '_' => '!_']) . '%';
    }
    if ($gradeFilter !== '') {
        $conditions[] = 'g.grade_level = :grade';
        $params['grade'] = $gradeFilter;
    }
    if ($yearFilter !== '') {
        $conditions[] = 's.school_year_id = :year';
        $params['year'] = $yearFilter;
    }
    $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
    $sectionsQuery = $pdo->prepare(
        "SELECT s.section_id, s.section_name, g.grade_level, y.school_year,
                (SELECT COUNT(*) FROM students st WHERE st.current_section_id = s.section_id) AS assigned_students,
                EXISTS (SELECT 1 FROM enrollment_applications ea WHERE ea.assigned_section_id = s.section_id) AS has_applications,
                EXISTS (SELECT 1 FROM teacher_section_assignments tsa WHERE tsa.section_id = s.section_id) AS has_teachers
         FROM sections s
         JOIN grade_levels g ON g.grade_level_id = s.grade_level_id
         JOIN school_years y ON y.school_year_id = s.school_year_id
         $where
         ORDER BY y.school_year_id DESC, g.grade_level_id, s.section_name"
    );
    $sectionsQuery->execute($params);
    $sections = $sectionsQuery->fetchAll(PDO::FETCH_ASSOC);
}

require_once __DIR__ . '/../includes/header.php';
?>
<?php if ($showSectionForm): ?>
    <div class="portal-back-row">
        <a class="portal-back-link" href="<?= e($listUrl) ?>">Back to sections</a>
    </div>
<?php endif; ?>
<section class="form-card form-card-wide">
    <?php if ($showSectionForm): ?>
        <h1><?= e($pageTitle) ?></h1>
    <?php else: ?>
        <h1>Section Management</h1>
    <?php endif; ?>

    <?php if ($success): ?>
        <p class="success-message"><?= e($success) ?></p>
    <?php endif; ?>

    <?php if ($error): ?>
        <p class="error-message"><?= e($error) ?></p>
    <?php endif; ?>

    <?php if ($showSectionForm): ?>
        <form method="post" class="section-editor">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="<?= $editingSection ? 'update' : 'create' ?>">
            <?php if ($editingSection): ?>
                <input type="hidden" name="section_id" value="<?= (int) $editingSection['section_id'] ?>">
            <?php endif; ?>

            <label>
                Section name
                <input name="section_name" maxlength="100" value="<?= e($formName) ?>" required>
            </label>

            <label>
                Grade level
                <select name="grade_level_id" required>
                    <option value="">Select grade</option>
                    <?php foreach ($grades as $grade): ?>
                        <option value="<?= (int) $grade['grade_level_id'] ?>"
                                <?= (string) $formGrade === (string) $grade['grade_level_id'] ? 'selected' : '' ?>>
                            Grade <?= e($grade['grade_level']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label>
                School year
                <select name="school_year_id" required>
                    <option value="">Select school year</option>
                    <?php foreach ($years as $year): ?>
                        <option value="<?= (int) $year['school_year_id'] ?>"
                                <?= (string) $formYear === (string) $year['school_year_id'] ? 'selected' : '' ?>>
                            <?= e($year['school_year']) ?><?= $year['status'] === 'Active' ? ' (Active)' : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <div class="section-form-actions">
                <button class="button button-primary" type="submit"><?= $editingSection ? 'Save Changes' : 'Add Section' ?></button>
                <a class="button button-outline" href="<?= e($listUrl) ?>">Cancel</a>
            </div>
        </form>
    <?php else: ?>

    <div class="portal-list-toolbar">
        <h2>Existing Sections</h2>
        <a class="button button-primary portal-add-action" href="<?= e($addUrl) ?>"><span class="portal-add-icon" aria-hidden="true">+</span>Add Section</a>
    </div>

    <nav class="application-status-links" aria-label="Filter sections by grade">
        <?php foreach (['', '7', '8', '9', '10'] as $tabGrade): ?>
            <?php
            $tabFilters = $sectionFilters;
            $tabFilters['grade'] = $tabGrade;
            ?>
            <a class="button <?= $gradeFilter === $tabGrade ? 'button-primary' : 'button-outline' ?>"
               href="<?= e($listPath . '?' . http_build_query($tabFilters)) ?>#section-results"
               <?= $gradeFilter === $tabGrade ? 'aria-current="true"' : '' ?>>
                <?= e($tabGrade === '' ? 'All grades' : 'Grade ' . $tabGrade) ?>
            </a>
        <?php endforeach; ?>
    </nav>

    <form method="get" class="table-search-form" action="<?= e($listPath) ?>#section-results">
        <input type="hidden" name="grade" value="<?= e($gradeFilter) ?>">
        <div class="table-search-toolbar">
            <div class="table-search-field">
                <label for="section-search">Search section name</label>
                <input type="search" name="q" id="section-search" maxlength="100" value="<?= e($search) ?>">
            </div>
            <button class="button button-primary" type="submit">Search</button>
            <details class="table-search-disclosure">
                <summary class="button button-outline">Filters<?= $yearFilter !== '' ? ' (active)' : '' ?></summary>
                <div class="table-search-panel">
                    <h3 class="table-search-panel-title">Filter results</h3>
                    <label>School year
                        <select name="year">
                            <option value="">All school years</option>
                            <?php foreach ($years as $year): ?>
                                <option value="<?= (int) $year['school_year_id'] ?>"
                                        <?= $yearFilter === (int) $year['school_year_id'] ? 'selected' : '' ?>>
                                    <?= e($year['school_year']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <div class="table-search-actions">
                        <button class="button button-primary" type="submit">Apply filters</button>
                        <a class="button button-outline" href="<?= e($listPath . '?' . http_build_query(['grade' => $gradeFilter])) ?>#section-results">Clear filters</a>
                    </div>
                </div>
            </details>
        </div>
    </form>

    <p class="muted" id="section-results"><?= count($sections) ?> <?= count($sections) === 1 ? 'section' : 'sections' ?> found</p>

    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Section</th>
                    <th>Grade</th>
                    <th>School Year</th>
                    <th class="portal-table-number">Students assigned</th>
                    <th>Action</th>
                </tr>
            </thead>

            <tbody>
                <?php foreach ($sections as $row): ?>
                    <tr>
                        <td><?= e($row['section_name']) ?></td>
                        <td><?= e($row['grade_level']) ?></td>
                        <td><?= e($row['school_year']) ?></td>
                        <td class="portal-table-number"><?= number_format((int) $row['assigned_students']) ?></td>
                        <td>
                            <div class="section-row-actions">
                                <a
                                    class="button button-outline portal-record-action"
                                    href="<?= e($listPath . '?' . http_build_query($sectionFilters + ['edit' => (int) $row['section_id']])) ?>"
                                >
                                    Edit
                                </a>

                                <?php
                                $usedBy = [];
                                if ($row['assigned_students']) $usedBy[] = 'student records';
                                if ($row['has_applications']) $usedBy[] = 'enrollment applications';
                                if ($row['has_teachers']) $usedBy[] = 'teacher assignments';
                                ?>
                                <?php if ($usedBy): ?>
                                    <button type="button" class="button portal-record-action section-delete-unavailable"
                                        aria-haspopup="dialog"
                                        data-section-in-use="<?= e($row['section_name'] . ' is still in use by ' . implode(', ', $usedBy) . '.') ?>">
                                        Delete
                                    </button>
                                <?php else: ?>
                                    <form method="post" action="<?= e($deleteUrl) ?>"
                                          data-confirm-delete="Delete <?= e($row['section_name']) ?> (Grade <?= e($row['grade_level']) ?>, <?= e($row['school_year']) ?>)? This action cannot be undone.">
                                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                        <input type="hidden" name="section_id" value="<?= (int) $row['section_id'] ?>">
                                        <button class="button portal-record-action button-danger-outline" type="submit">Delete</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>

                <?php if (!$sections): ?>
                    <tr>
                        <td colspan="5">No sections match your search and filters.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</section>

<?php if (!$showSectionForm): ?>
    <dialog class="portal-delete-dialog" id="sectionInUseDialog"
        aria-labelledby="sectionInUseTitle" aria-describedby="sectionInUseMessage">
        <h2 id="sectionInUseTitle">Section can't be deleted</h2>
        <p id="sectionInUseMessage"></p>
        <form method="dialog" class="portal-delete-actions">
            <button class="button button-outline" autofocus>Close</button>
        </form>
    </dialog>

    <script>
        const sectionInUseDialog = document.getElementById('sectionInUseDialog');
        const sectionInUseMessage = document.getElementById('sectionInUseMessage');
        document.querySelectorAll('[data-section-in-use]').forEach(function (button) {
            button.addEventListener('click', function () {
                sectionInUseMessage.textContent = button.dataset.sectionInUse;
                sectionInUseDialog.showModal();
            });
        });
    </script>
<?php endif; ?>

<style>
.button.section-delete-unavailable {
    background: #f1f2f4;
    border: 1px solid #cbd1d8;
    color: #515b68;
}

.section-editor {
    display: grid;
    gap: 14px;
    max-width: 440px;
    margin-top: 18px;
}

.section-editor label {
    display: grid;
    gap: 5px;
}

#section-results {
    scroll-margin-top: 110px;
}

.section-in-use {
    font-size: .9rem;
}

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
