
<?php
require_once __DIR__ . '/../includes/bootstrap.php';

require_role('System Administrator');

$pageTitle = 'Teacher and Staff Records';

$search = trim($_GET['search'] ?? '');
$searchBy = $_GET['search_by'] ?? 'name';

$allowedSearch = ['name', 'id', 'role', 'position', 'username'];

if (!in_array($searchBy, $allowedSearch, true)) {
    $searchBy = 'name';
}

$sql = "SELECT
            u.user_id,
            u.username,
            u.email,
            u.account_status,
            r.role_name,
            sp.employee_id,
            sp.first_name,
            sp.middle_name,
            sp.last_name,
            sp.position_title,
            sp.department
        FROM users u
        INNER JOIN roles r ON r.role_id = u.role_id
        LEFT JOIN staff_profiles sp ON sp.user_id = u.user_id
        WHERE r.role_name IN (
            'School Registrar',
            'Teacher',
            'Enrollment Committee',
            'Scholarship Coordinator'
        )";

$params = [];

if ($search !== '') {

    $term = '%' . $search . '%';

    if ($searchBy === 'name') {
        $sql .= " AND CONCAT_WS(' ',
                    sp.first_name,
                    NULLIF(sp.middle_name, ''),
                    sp.last_name
                ) LIKE :search_name";

        $params = ['search_name' => $term];

    } elseif ($searchBy === 'id') {
        $sql .= " AND sp.employee_id LIKE :search_id";

        $params = ['search_id' => $term];

    } elseif ($searchBy === 'role') {
        $sql .= " AND r.role_name LIKE :search_role";

        $params = ['search_role' => $term];

    } elseif ($searchBy === 'position') {
        $sql .= " AND (
                    sp.position_title LIKE :search_position
                    OR sp.department LIKE :search_department
                )";

        $params = [
            'search_position' => $term,
            'search_department' => $term
        ];

    } elseif ($searchBy === 'username') {
        $sql .= " AND u.username LIKE :search_username";

        $params = ['search_username' => $term];
    }
}

$sql .= " ORDER BY r.role_name ASC, sp.last_name ASC, u.username ASC";

$query = $pdo->prepare($sql);
$query->execute($params);
$staffMembers = $query->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/../includes/header.php';
?>

<style>

/* Search By Layout */
.student-search-options {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 15px;
    margin-bottom: 20px;
}

.student-search-options > label {
    margin: 0;
}

.student-search-filters {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 10px;
}

.student-search-filters label {
    margin: 0;
    cursor: pointer;
}

.student-search-filters input[type="radio"] {
    display: none;
}

.student-search-filters span {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 70px;
    min-height: 42px;
    padding: 8px 16px;
    border: 1px solid #d9dce3;
    border-radius: 9px;
    background: #ffffff;
    color: #4b1020;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
}

.student-search-filters input[type="radio"]:checked + span {
    background: #64152f;
    color: #ffffff;
    border-color: #64152f;
}

.student-search-filters span:hover {
    border-color: #64152f;
}

/* Staff Records Table */
.staff-records-table {
    width: 100%;
    min-width: 0;
    table-layout: fixed;
    border-collapse: collapse;
    border: 1px solid #e1dce0;
}

.staff-records-table th,
.staff-records-table td {
    padding: 13px 10px;
    border-bottom: 1px solid #e1dce0;
    border-right: 1px solid #e1dce0;
    text-align: left;
    vertical-align: middle;
    font-size: 13px;
    line-height: 1.5;
    overflow-wrap: anywhere;
}

.staff-records-table th:last-child,
.staff-records-table td:last-child {
    border-right: none;
}

.staff-records-table thead th {
    background: #f7f0f3;
    color: #4b1020;
    font-weight: 600;
    white-space: normal;
    overflow-wrap: normal;
    word-break: normal;
    font-size: 12px;
}

/* Adjusted Column Widths */
.staff-records-table th:nth-child(1),
.staff-records-table td:nth-child(1) {
    width: 11%;
}

.staff-records-table th:nth-child(2),
.staff-records-table td:nth-child(2) {
    width: 13%;
}

.staff-records-table th:nth-child(3),
.staff-records-table td:nth-child(3) {
    width: 10%;
}

.staff-records-table th:nth-child(4),
.staff-records-table td:nth-child(4) {
    width: 21%;
}

.staff-records-table th:nth-child(5),
.staff-records-table td:nth-child(5) {
    width: 10%;
}

.staff-records-table th:nth-child(6),
.staff-records-table td:nth-child(6) {
    width: 20%;
}

.staff-records-table th:nth-child(7),
.staff-records-table td:nth-child(7) {
    width: 15%;
}

/* Email Display */
.staff-records-table .staff-email {
    white-space: nowrap;
    font-size: 11px;
}

/* Position and Department */
.staff-records-table .staff-department {
    display: block;
    margin-top: 4px;
    font-size: 12px;
    color: #555555;
}

/* Table Container */
.staff-table-container {
    width: 100%;
    max-width: 100%;
    overflow-x: auto;
    border-radius: 8px;
}

/* Mobile Layout */
@media (max-width: 600px) {
    .student-search-filters {
        gap: 8px;
    }

    .student-search-filters span {
        padding: 8px 12px;
    }

    .staff-records-table {
        min-width: 700px;
    }
}

</style>

<section class="form-card">

    <p class="eyebrow">System Administration</p>

    <h1>Teacher and Staff Records</h1>

    <p>View school personnel accounts, assigned roles, and available staff profile information.</p>

    <form method="GET" action="" class="student-search-form">

        <div class="student-search-options">

            <label>Search By:</label>

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

        </div>

        <div class="form-group">

            <label for="search">Search Personnel</label>

            <input
                type="search"
                id="search"
                name="search"
                value="<?= e($search) ?>"
                placeholder="Enter personnel name..."
            >

        </div>

        <div class="form-actions">

            <button type="submit" class="button button-primary">
                Search
            </button>

            <a href="<?= BASE_URL ?>/admin/staff_records.php"
               class="button button-outline">
                Clear
            </a>

        </div>

    </form>

</section>

<section class="form-card">

    <div class="section-heading">
        <h2>School Personnel</h2>
        <p><?= count($staffMembers) ?> record(s) found</p>
    </div>

    <?php if (!$staffMembers): ?>

        <p>No teacher or staff accounts found.</p>

    <?php else: ?>

        <div class="staff-table-container">

            <table class="staff-records-table">

                <thead>
                    <tr>
                        <th>Employee ID</th>
                        <th>Name</th>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Position / Department</th>
                        <th>Account Status</th>
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

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
