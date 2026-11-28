
<?php
require_once __DIR__ . '/../includes/bootstrap.php';

require_role('System Administrator');

$pageTitle = 'Student Records';

require __DIR__ . '/../includes/admin_student_records_query.php';

$searchPrompts = [
    'name' => 'Enter student name',
    'lrn' => 'Enter 12-digit LRN',
    'username' => 'Enter username',
];

/* Load available grade levels and sections for the search filters */
$gradeOptions = $pdo->query("
    SELECT grade_level
    FROM grade_levels
    ORDER BY grade_level ASC
")->fetchAll(PDO::FETCH_COLUMN);

$sectionOptions = $pdo->query("
    SELECT DISTINCT section_name
    FROM sections
    ORDER BY section_name ASC
")->fetchAll(PDO::FETCH_COLUMN);



require_once __DIR__ . '/../includes/header.php';
?>




<section class="form-card">

    <h1>Student Records</h1>

    <?php if (isset($_GET['deleted'])): ?>
        <p class="success-message">
            Student account and connected records were permanently deleted.
        </p>
    <?php endif; ?>

    <?php if (isset($_GET['error'])): ?>
        <p class="error-message">
            Unable to complete the requested operation.
        </p>
    <?php endif; ?>

    <nav class="application-status-links" aria-label="Filter records by grade">
        <?php foreach (['', '7', '8', '9', '10'] as $grade): ?>
            <?php
            $gradeParams = array_replace($recordFilters, ['grade' => $grade]);
            if ($gradeParams['search_by'] === 'grade') {
                $gradeParams['search'] = '';
                $gradeParams['search_by'] = 'name';
            }
            ?>
            <a class="button <?= $gradeFilter === $grade ? 'button-primary' : 'button-outline' ?>"
               href="?<?= e(http_build_query($gradeParams)) ?>" <?= $gradeFilter === $grade ? 'aria-current="true"' : '' ?>>
                <?= $grade === '' ? 'All grades' : 'Grade ' . e($grade) ?>
            </a>
        <?php endforeach; ?>
    </nav>

    <form method="get" class="table-search-form student-search-form">
        <?php if ($gradeFilter !== ''): ?>
            <input type="hidden" name="grade" value="<?= e($gradeFilter) ?>">
        <?php endif; ?>
        <div class="table-search-toolbar">
            <div class="table-search-field">
                <label id="student-search-label" for="<?= in_array($searchBy, ['grade', 'section'], true) ? e($searchBy . '-filter') : 'search' ?>"><?= ['grade' => 'Grade level', 'section' => 'Section'][$searchBy] ?? 'Search Student Records' ?></label>

                <input
                    type="search" data-student-suggestions="admin_records" data-suggestion-url="<?= BASE_URL ?>/includes/student_suggestions.php"
                    id="search"
                    name="search"
                    value="<?= e($search) ?>"
                    placeholder="<?= e($searchPrompts[$searchBy] ?? $searchPrompts['name']) ?>"
                    <?= in_array($searchBy, ['grade', 'section'], true) ? 'hidden disabled' : '' ?>
                >

                <select
                    id="grade-filter"
                    name="search"
                    <?= $searchBy !== 'grade' ? 'hidden disabled' : '' ?>
                >
                    <option value="">Select a grade level</option>
                    <?php foreach ($gradeOptions as $grade): ?>
                        <option value="<?= e($grade) ?>"
                            <?= $searchBy === 'grade' && $search === $grade ? 'selected' : '' ?>>
                            <?= e($grade) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <select
                    id="section-filter"
                    name="search"
                    <?= $searchBy !== 'section' ? 'hidden disabled' : '' ?>
                >
                    <option value="">Select a section</option>
                    <?php foreach ($sectionOptions as $section): ?>
                        <option value="<?= e($section) ?>"
                            <?= $searchBy === 'section' && $search === $section ? 'selected' : '' ?>>
                            <?= e($section) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="button button-primary">Search</button>
            <details class="table-search-disclosure">
                <summary class="button button-outline">
                    Filters<?= ($searchBy !== 'name') ? ' (active)' : '' ?>
                </summary>
                <div class="table-search-panel">
                    <h3 class="table-search-panel-title">Filter results</h3>
                    <div class="table-search-fields">
                        <fieldset class="table-search-mode">
                            <legend>Search by</legend>
                        <div class="student-search-filters">

                                    <label>
                                        <input type="radio" name="search_by" value="name"
                                            <?= $searchBy === 'name' ? 'checked' : '' ?>>
                                        <span>Name</span>
                                    </label>

                                    <label>
                                        <input type="radio" name="search_by" value="lrn"
                                            <?= $searchBy === 'lrn' ? 'checked' : '' ?>>
                                        <span>LRN</span>
                                    </label>

                                    <label>
                                        <input type="radio" name="search_by" value="username"
                                            <?= $searchBy === 'username' ? 'checked' : '' ?>>
                                        <span>Username</span>
                                    </label>

                                    <label>
                                        <input type="radio" name="search_by" value="section"
                                            <?= $searchBy === 'section' ? 'checked' : '' ?>>
                                        <span>Section</span>
                                    </label>

                                    <label>
                                        <input type="radio" name="search_by" value="grade"
                                            <?= $searchBy === 'grade' ? 'checked' : '' ?>>
                                        <span>Grade</span>
                                    </label>

                                </div>
                        </fieldset>
                    </div>
                    <div class="table-search-actions">
                        <button type="submit" class="button button-primary">Apply filters</button>
                        <a class="button button-outline" href="<?= BASE_URL ?>/admin/student_records.php">Clear filters</a>
                    </div>
                </div>
            </details>
        </div>
    </form>

</section>


<section class="form-card">

    <div class="section-heading student-records-heading">
        <div>
            <h2 id="records-heading">Registered Students</h2>
            <p><?= count($students) ?> record(s) found</p>
        </div>

        <?php if ($students): ?>
            <?php
            $reportParameters = http_build_query($recordFilters);
            ?>

            <div class="report-export-icons">
                <a class="button button-outline report-export-icon"
                   href="<?= BASE_URL ?>/admin/student_records_report.php?<?= e($reportParameters) ?>&amp;print=1"
                   data-print-report target="_blank" rel="noopener"
                   title="Print student records" aria-label="Print student records">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 8V3h10v5M7 17H5a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2M7 14h10v7H7z"/><path d="M17 11h.01"/></svg>
                </a>
                <a class="button button-primary report-export-icon"
                   href="<?= BASE_URL ?>/admin/student_records_report.php?<?= e($reportParameters) ?>&amp;download=1"
                   title="Download student records" aria-label="Download student records">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3v12m0 0 5-5m-5 5-5-5M4 17v4h16v-4"/></svg>
                </a>
            </div>
        <?php endif; ?>
    </div>

    <?php if (!$students): ?>

        <p>No student records found.</p>

    <?php else: ?>

        <div class="table-responsive">

            <table class="student-records-table" aria-labelledby="records-heading">

                <thead>
                    <tr>
                        <th scope="col">LRN</th>
                        <th scope="col">Student Name</th>
                        <th scope="col">Grade</th>
                        <th scope="col">Section</th>
                        <th scope="col">Username</th>
                        <th scope="col">Account Status</th>
                        <th scope="col">Scholarship</th>
                        <th scope="col">Actions</th>
                    </tr>
                </thead>

                <tbody>

                    <?php foreach ($students as $student): ?>

                        <tr>

                            <td>
                                <?= e($student['lrn'] ?? '—') ?>
                            </td>

                            <td>
                                <?= e(trim(
                                    $student['first_name'] . ' ' .
                                    ($student['middle_name']
                                        ? $student['middle_name'] . ' '
                                        : '') .
                                    $student['last_name']
                                )) ?>
                            </td>

                            <td>
                                <?= e($student['grade_level'] ?? 'Not assigned') ?>
                            </td>

                            <td>
                                <?= e($student['section_name'] ?? 'Not assigned') ?>
                            </td>

                            <td>
                                <?= e($student['username']) ?>
                            </td>

                            <td>
                                <?= e($student['account_status']) ?>
                            </td>

                            <td>
                                <?= e($student['scholarship_info'] ?? 'None') ?>
                            </td>

                            <td>

                                <a class="button button-outline portal-record-action"
                                   href="<?= BASE_URL ?>/admin/student_view.php?id=<?= (int) $student['student_id'] ?>&amp;<?= e($reportParameters) ?>">
                                    View record
                                </a>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                </tbody>

            </table>

        </div>

    <?php endif; ?>

</section>


<style>
.student-records-heading {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    flex-wrap: wrap;
}

@media (max-width: 640px) {
    .student-records-heading {
        align-items: flex-start;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const searchTypeInputs = document.querySelectorAll('input[name="search_by"]');
    const textSearch = document.getElementById('search');
    const gradeFilter = document.getElementById('grade-filter');
    const sectionFilter = document.getElementById('section-filter');

    const searchLabel = document.getElementById('student-search-label');
    const searchPrompts = <?= json_encode($searchPrompts) ?>;

    function updateSearchControl() {
        const selected = document.querySelector('input[name="search_by"]:checked')?.value || 'name';

        const activeControl = { grade: gradeFilter, section: sectionFilter }[selected] || textSearch;
        searchLabel.htmlFor = activeControl.id;
        searchLabel.textContent = { grade: 'Grade level', section: 'Section' }[selected] || 'Search Student Records';
        textSearch.placeholder = searchPrompts[selected] || searchPrompts.name;

        textSearch.hidden = selected === 'grade' || selected === 'section';
        gradeFilter.hidden = selected !== 'grade';
        sectionFilter.hidden = selected !== 'section';

        textSearch.disabled = textSearch.hidden;
        gradeFilter.disabled = gradeFilter.hidden;
        sectionFilter.disabled = sectionFilter.hidden;
    }

    searchTypeInputs.forEach(function (input) {
        input.addEventListener('change', updateSearchControl);
    });

    updateSearchControl();
});
</script>

<script src="<?= BASE_URL ?>/assets/js/student_suggestions.js" defer></script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
