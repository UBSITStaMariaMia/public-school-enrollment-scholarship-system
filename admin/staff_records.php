<?php
require_once __DIR__ . '/../includes/bootstrap.php';

require_role('System Administrator');

$pageTitle = 'Teacher and Staff Records';

$search = trim($_GET['search'] ?? '');

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
            sp.department,
            sp.employment_status
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
    $sql .= " AND (
        u.username LIKE :search_username
        OR u.email LIKE :search_email
        OR r.role_name LIKE :search_role
        OR sp.employee_id LIKE :search_employee
        OR sp.first_name LIKE :search_first
        OR sp.middle_name LIKE :search_middle
        OR sp.last_name LIKE :search_last
    )";

    $term = '%' . $search . '%';
    $params = [
        'search_username' => $term,
        'search_email' => $term,
        'search_role' => $term,
        'search_employee' => $term,
        'search_first' => $term,
        'search_middle' => $term,
        'search_last' => $term
    ];
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

    <form method="GET" action="" class="form-grid" style="align-items:end;">
        <div class="form-group">
            <label for="search">Search personnel</label>
            <input
                type="search"
                id="search"
                name="search"
                value="<?= e($search) ?>"
                placeholder="Search by employee ID, name, username, email, or role"
            >
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Search</button>
            <a href="<?= BASE_URL ?>/admin/staff_records.php" class="btn btn-secondary">Clear</a>
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
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Employee ID</th>
                        <th>Name</th>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Position / Department</th>
                        <th>Employment Status</th>
                        <th>Account Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($staffMembers as $member): ?>
                        <tr>
                            <td><?= e($member['employee_id'] ?? 'Not provided') ?></td>
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
                            <td><?= e($member['username']) ?></td>
                            <td><?= e($member['email']) ?></td>
                            <td><?= e($member['role_name']) ?></td>
                            <td>
                                <?= e($member['position_title'] ?? '—') ?>
                                <?php if (!empty($member['department'])): ?>
                                    <br><small><?= e($member['department']) ?></small>
                                <?php endif; ?>
                            </td>
                            <td><?= e($member['employment_status'] ?? 'Profile not completed') ?></td>
                            <td><?= e($member['account_status']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
