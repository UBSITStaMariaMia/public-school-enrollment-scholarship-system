
<?php
require_once __DIR__ . '/../includes/bootstrap.php';

require_role('System Administrator');

$pageTitle = 'Student Records';

$search = trim($_GET['search'] ?? '');
$searchBy = $_GET['search_by'] ?? 'name';

$allowedSearch = ['name', 'lrn', 'username', 'section', 'grade'];

if (!in_array($searchBy, $allowedSearch, true)) {
    $searchBy = 'name';
}

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

$sql = "SELECT
            s.student_id,
            s.lrn,
            s.first_name,
            s.middle_name,
            s.last_name,
            u.username,
            u.account_status,
            g.grade_level,
            sec.section_name,
            scholarship.scholarship_info
        FROM students s
        INNER JOIN users u ON u.user_id = s.user_id
        LEFT JOIN grade_levels g
            ON g.grade_level_id = s.current_grade_level_id
        LEFT JOIN sections sec
            ON sec.section_id = s.current_section_id
        LEFT JOIN (
            SELECT
                sa.student_id,
                GROUP_CONCAT(
                    CONCAT(sp.program_name, ' - ', sa.application_status)
                    SEPARATOR ', '
                ) AS scholarship_info
            FROM scholarship_applications sa
            INNER JOIN special_programs sp
                ON sp.program_id = sa.program_id
            GROUP BY sa.student_id
        ) scholarship ON scholarship.student_id = s.student_id";

$params = [];

if ($search !== '') {

    $term = '%' . $search . '%';

    if ($searchBy === 'lrn') {
        $sql .= " WHERE s.lrn LIKE :search";
    } elseif ($searchBy === 'username') {
        $sql .= " WHERE u.username LIKE :search";
    } elseif ($searchBy === 'name') {
        $sql .= " WHERE CONCAT_WS(' ',
                    s.first_name,
                    NULLIF(s.middle_name, ''),
                    s.last_name
                ) LIKE :search";
    } elseif ($searchBy === 'section') {
        $sql .= " WHERE sec.section_name = :search";
        $params = ['search' => $search];
    } elseif ($searchBy === 'grade') {
        $sql .= " WHERE g.grade_level = :search";
        $params = ['search' => $search];
    }

    if (in_array($searchBy, ['name', 'lrn', 'username'], true)) {
        $params = ['search' => $term];
    }
}

$sql .= " ORDER BY s.last_name ASC, s.first_name ASC";

$query = $pdo->prepare($sql);
$query->execute($params);
$students = $query->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/../includes/header.php';
?>

<style>

/* Student Records Table */
.student-records-table {
    width: 100%;
    min-width: 0 !important;
    table-layout: fixed;
    border-collapse: collapse;
    border: 1px solid #d9dce3;
}

.student-records-table th,
.student-records-table td {
    padding: 12px 8px;
    border-right: 1px solid #d9dce3;
    border-bottom: 1px solid #d9dce3;
    text-align: left;
    vertical-align: middle;
    overflow-wrap: anywhere;
    font-size: 14px;
}

/* Remove divider from the last column */
.student-records-table th:last-child,
.student-records-table td:last-child {
    border-right: none;
}

/* Table Header */
.student-records-table thead th {
    background: #f7f0f3;
    color: #4b1020;
    font-weight: 600;
}

/* Column Widths - 12 Digit LRN Included */
.student-records-table th:nth-child(1),
.student-records-table td:nth-child(1) {
    width: 12%;
    white-space: nowrap;
    font-size: 11px;
}

.student-records-table th:nth-child(2),
.student-records-table td:nth-child(2) {
    width: 15%;
}

.student-records-table th:nth-child(3),
.student-records-table td:nth-child(3) {
    width: 7%;
}

.student-records-table th:nth-child(4),
.student-records-table td:nth-child(4) {
    width: 11%;
}

.student-records-table th:nth-child(5),
.student-records-table td:nth-child(5) {
    width: 11%;
}

.student-records-table th:nth-child(6),
.student-records-table td:nth-child(6) {
    width: 13%;
}

.student-records-table th:nth-child(7),
.student-records-table td:nth-child(7) {
    width: 13%;
}

.student-records-table th:nth-child(8),
.student-records-table td:nth-child(8) {
    width: 18%;
}

/* Student Action Buttons */
.student-actions {
    display: flex;
    flex-direction: column;
    gap: 7px;
    width: 100%;
    max-width: 105px;
    margin: 0 auto;
}

.student-actions form {
    width: 100%;
    margin: 0;
}

.student-actions .button {
    width: 100%;
    height: 42px;
    min-height: 42px;
    box-sizing: border-box;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 8px 5px;
    border-radius: 9px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    text-align: center;
    text-decoration: none;
    white-space: nowrap;
}

/* Delete Button */
.student-actions .delete-button {
    background: #b42318;
    color: #ffffff;
    border: 1px solid #b42318;
}

.student-actions .delete-button:hover {
    background: #941b13;
    border-color: #941b13;
}

/* Table Container */
.table-responsive {
    width: 100%;
    max-width: 100%;
    overflow-x: hidden;
}

</style>


<section class="form-card">

    <p class="eyebrow">System Administration</p>
    <h1>Student Records</h1>

    <p>View and manage registered student accounts and information.</p>

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

    <form method="GET" action="" class="student-search-form">

        <label>Search By:</label>

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

        <div class="form-group">

            <label for="search">Search Student Records</label>

            <input
                type="search"
                id="search"
                name="search"
                value="<?= e($search) ?>"
                placeholder="Enter name, LRN, or username..."
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

        <div class="form-actions">

            <button type="submit" class="button button-primary">
                Search
            </button>

            <a href="<?= BASE_URL ?>/admin/student_records.php"
               class="button button-outline">
                Clear
            </a>

        </div>

    </form>

</section>


<section class="form-card">

    <div class="section-heading">
        <h2>Registered Students</h2>
        <p><?= count($students) ?> record(s) found</p>
    </div>

    <?php if (!$students): ?>

        <p>No student records found.</p>

    <?php else: ?>

        <div class="table-responsive">

            <table class="student-records-table">

                <thead>
                    <tr>
                        <th>LRN</th>
                        <th>Student Name</th>
                        <th>Grade</th>
                        <th>Section</th>
                        <th>Username</th>
                        <th>Account Status</th>
                        <th>Scholarship</th>
                        <th>Actions</th>
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

                                <div class="student-actions">

                                    <a
                                        href="<?= BASE_URL ?>/admin/student_view.php?id=<?= (int) $student['student_id'] ?>"
                                        class="button button-outline"
                                    >
                                        View
                                    </a>

                                    <form
                                        method="POST"
                                        action="<?= BASE_URL ?>/admin/delete_student.php"
                                        onsubmit="return confirm('WARNING: This will permanently delete this student account and ALL connected records. This action cannot be undone. Are you sure?');"
                                    >

                                        <input
                                            type="hidden"
                                            name="student_id"
                                            value="<?= (int) $student['student_id'] ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="csrf_token"
                                            value="<?= e(csrf_token()) ?>"
                                        >

                                        <button
                                            type="submit"
                                            class="button delete-button"
                                        >
                                            Delete
                                        </button>

                                    </form>

                                </div>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                </tbody>

            </table>

        </div>

    <?php endif; ?>

</section>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const searchTypeInputs = document.querySelectorAll('input[name="search_by"]');
    const textSearch = document.getElementById('search');
    const gradeFilter = document.getElementById('grade-filter');
    const sectionFilter = document.getElementById('section-filter');

    function updateSearchControl() {
        const selected = document.querySelector('input[name="search_by"]:checked')?.value || 'name';

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

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
