<?php
require_once __DIR__ . '/../includes/bootstrap.php';

require_role('System Administrator');

$pageTitle = 'Teacher and Staff Records';

require __DIR__ . '/../includes/admin_staff_records_query.php';

$searchPrompts = [
    'name' => 'Enter personnel name',
    'id' => 'Enter employee ID',
    'role' => 'Enter staff role',
    'position' => 'Enter position or department',
    'username' => 'Enter username',
];



require_once __DIR__ . '/../includes/header.php';
?>

<section class="form-card">


    <h1>Teacher and Staff Records</h1>

    <nav class="application-status-links" aria-label="Filter personnel by role">
        <?php foreach (array_merge([''], $staffRoles) as $role): ?>
            <?php $roleParams = array_replace($recordFilters, ['role' => $role, 'grade' => $role === 'Teacher' ? $gradeFilter : '']); ?>
            <a class="button <?= $roleFilter === $role ? 'button-primary' : 'button-outline' ?>"
               href="?<?= e(http_build_query($roleParams)) ?>" <?= $roleFilter === $role ? 'aria-current="true"' : '' ?>>
                <?= e($role !== '' ? $role : 'All roles') ?>
            </a>
        <?php endforeach; ?>
    </nav>
    <?php if ($roleFilter === 'Teacher'): ?>
    <h2>Assigned grade</h2>
    <nav class="application-status-links" aria-label="Filter teachers by assigned grade">
        <?php foreach (['', '7', '8', '9', '10'] as $grade): ?>
            <?php
            $gradeParams = array_replace($recordFilters, ['grade' => $grade]);
            if ($grade !== '') {
                $gradeParams['role'] = 'Teacher';
            }
            ?>
            <a class="button <?= $gradeFilter === $grade ? 'button-primary' : 'button-outline' ?>"
               href="?<?= e(http_build_query($gradeParams)) ?>" <?= $gradeFilter === $grade ? 'aria-current="true"' : '' ?>>
                <?= $grade === '' ? 'All grades' : 'Grade ' . e($grade) ?>
            </a>
        <?php endforeach; ?>
    </nav>
    <?php endif; ?>

    <form method="get" class="table-search-form student-search-form">
        <?php if ($gradeFilter !== ''): ?>
            <input type="hidden" name="grade" value="<?= e($gradeFilter) ?>">
        <?php endif; ?>
        <?php if ($roleFilter !== ''): ?>
            <input type="hidden" name="role" value="<?= e($roleFilter) ?>">
        <?php endif; ?>
        <div class="table-search-toolbar">
            <div class="table-search-field">
                <label for="search">Search Personnel</label>

                <input
                    type="search"
                    id="search"
                    name="search"
                    value="<?= e($search) ?>"
                    placeholder="<?= e($searchPrompts[$searchBy]) ?>"
                >
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
                                            <input type="radio" name="search_by" value="id"
                                                <?= $searchBy === 'id' ? 'checked' : '' ?>>
                                            <span>ID</span>
                                        </label>

                                        <label>
                                            <input type="radio" name="search_by" value="role"
                                                <?= $searchBy === 'role' ? 'checked' : '' ?>>
                                            <span>Role</span>
                                        </label>

                                        <label>
                                            <input type="radio" name="search_by" value="position"
                                                <?= $searchBy === 'position' ? 'checked' : '' ?>>
                                            <span>Position / Department</span>
                                        </label>

                                        <label>
                                            <input type="radio" name="search_by" value="username"
                                                <?= $searchBy === 'username' ? 'checked' : '' ?>>
                                            <span>Username</span>
                                        </label>

                                    </div>
                        </fieldset>
                    </div>
                    <div class="table-search-actions">
                        <button type="submit" class="button button-primary">Apply filters</button>
                        <a class="button button-outline" href="<?= BASE_URL ?>/admin/staff_records.php">Clear filters</a>
                    </div>
                </div>
            </details>
        </div>
    </form>

</section>

<section class="form-card">

    <div class="section-heading staff-records-heading">

        <div>
            <h2 id="records-heading">School Personnel</h2>
            <p><?= count($staffMembers) ?> record(s) found</p>
        </div>

        <?php if ($staffMembers): ?>

            <?php
            $reportParameters = http_build_query($recordFilters);
            ?>

            <div class="report-export-icons">
                <a class="button button-outline report-export-icon"
                   href="<?= BASE_URL ?>/admin/staff_records_report.php?<?= e($reportParameters) ?>&amp;print=1"
                   data-print-report target="_blank" rel="noopener"
                   title="Print staff records" aria-label="Print staff records">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 8V3h10v5M7 17H5a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2M7 14h10v7H7z"/><path d="M17 11h.01"/></svg>
                </a>
                <a class="button button-primary report-export-icon"
                   href="<?= BASE_URL ?>/admin/staff_records_report.php?<?= e($reportParameters) ?>&amp;download=1"
                   title="Download staff records" aria-label="Download staff records">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3v12m0 0 5-5m-5 5-5-5M4 17v4h16v-4"/></svg>
                </a>
            </div>

        <?php endif; ?>

    </div>

    <?php if (!$staffMembers): ?>

        <p><?= $search !== '' || $roleFilter !== '' || $gradeFilter !== '' ? 'No personnel records match these filters.' : 'No teacher or staff accounts found.' ?></p>

    <?php else: ?>

        <div class="staff-table-container">

            <table class="staff-records-table" aria-labelledby="records-heading">

                <thead>
                    <tr>
                        <th scope="col">Employee ID</th>
                        <th scope="col">Name</th>
                        <th scope="col">Username</th>
                        <th scope="col">Email</th>
                        <th scope="col">Role</th>
                        <th scope="col">Position / Department</th>
                        <th scope="col">Account Status</th>
                    </tr>
                </thead>

                <tbody>

                    <?php foreach ($staffMembers as $member): ?>

                        <tr>

                            <td>
                                <?= e($member['employee_id'] ?? 'Not provided') ?>
                            </td>

                            <td>

                                <?php
                                $fullName = trim(
                                    ($member['first_name'] ?? '') . ' ' .
                                    (!empty($member['middle_name']) ? $member['middle_name'] . ' ' : '') .
                                    ($member['last_name'] ?? '')
                                );
                                ?>

                                <?= e($fullName !== '' ? $fullName : $member['username']) ?>

                            </td>

                            <td>
                                <?= e($member['username']) ?>
                            </td>

                            <td class="staff-email">
                                <?= e($member['email']) ?>
                            </td>

                            <td>
                                <?= e($member['role_name']) ?>
                            </td>

                            <td>

                                <?= e($member['position_title'] ?? '—') ?>

                                <?php if (!empty($member['department'])): ?>

                                    <small class="staff-department">
                                        <?= e($member['department']) ?>
                                    </small>

                                <?php endif; ?>

                            </td>

                            <td>
                                <?= e($member['account_status']) ?>
                            </td>

                        </tr>

                    <?php endforeach; ?>

                </tbody>

            </table>

        </div>

    <?php endif; ?>

</section>

<script>
const personnelSearch = document.getElementById('search');
const personnelSearchPrompts = <?= json_encode($searchPrompts) ?>;
document.querySelectorAll('input[name="search_by"]').forEach(function (option) {
    option.addEventListener('change', function () {
        personnelSearch.placeholder = personnelSearchPrompts[option.value];
    });
});
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
