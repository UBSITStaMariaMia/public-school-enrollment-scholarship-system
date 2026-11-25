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

    <div class="section-heading staff-records-heading">

        <div>
            <h2>School Personnel</h2>
            <p><?= count($staffMembers) ?> record(s) found</p>
        </div>

        <?php if ($staffMembers): ?>

            <?php
            $reportParameters = http_build_query([
                'search' => $search,
                'search_by' => $searchBy
            ]);
            ?>

            <div class="staff-record-actions">

                <a
                    href="<?= BASE_URL ?>/admin/staff_records_report.php?<?= e($reportParameters) ?>&print=1"
                    class="button button-outline"
                    target="_blank"
                    rel="noopener"
                >
                    Print Records
                </a>

                <a
                    href="<?= BASE_URL ?>/admin/staff_records_report.php?<?= e($reportParameters) ?>&download=1"
                    class="button button-primary"
                >
                    Download Records
                </a>

            </div>

        <?php endif; ?>

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
