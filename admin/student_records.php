<?php
require_once __DIR__ . '/../includes/bootstrap.php';

require_role('System Administrator');

$pageTitle = 'Student Records';

$search = trim($_GET['search'] ?? '');

$sql = "SELECT
            s.student_id,
            s.lrn,
            s.first_name,
            s.middle_name,
            s.last_name,
            s.contact_number,
            s.readmission_status,
            u.username,
            u.email,
            u.account_status,
            g.grade_level,
            sec.section_name
        FROM students s
        INNER JOIN users u ON u.user_id = s.user_id
        LEFT JOIN grade_levels g ON g.grade_level_id = s.current_grade_level_id
        LEFT JOIN sections sec ON sec.section_id = s.current_section_id";

$params = [];

if ($search !== '') {
    $sql .= " WHERE (
        s.lrn LIKE :search_lrn
        OR s.first_name LIKE :search_first
        OR s.middle_name LIKE :search_middle
        OR s.last_name LIKE :search_last
        OR u.username LIKE :search_username
        OR u.email LIKE :search_email
    )";

    $term = '%' . $search . '%';
    $params = [
        'search_lrn' => $term,
        'search_first' => $term,
        'search_middle' => $term,
        'search_last' => $term,
        'search_username' => $term,
        'search_email' => $term
    ];
}

$sql .= " ORDER BY s.last_name ASC, s.first_name ASC";

$query = $pdo->prepare($sql);
$query->execute($params);
$students = $query->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/../includes/header.php';
?>

<section class="form-card">
    <p class="eyebrow">System Administration</p>
    <h1>Student Records</h1>
    <p>View registered student profiles and their current account and enrollment information.</p>

    <form method="GET" action="" class="form-grid" style="align-items:end;">
        <div class="form-group">
            <label for="search">Search students</label>
            <input
                type="search"
                id="search"
                name="search"
                value="<?= e($search) ?>"
                placeholder="Search by LRN, name, username, or email"
            >
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Search</button>
            <a href="<?= BASE_URL ?>/admin/student_records.php" class="btn btn-secondary">Clear</a>
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
            <table>
                <thead>
                    <tr>
                        <th>LRN</th>
                        <th>Student Name</th>
                        <th>Grade Level</th>
                        <th>Section</th>
                        <th>Contact Number</th>
                        <th>Account Status</th>
                        <th>Readmission</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($students as $student): ?>
                        <tr>
                            <td><?= e($student['lrn'] ?? '—') ?></td>
                            <td>
                                <?= e(trim(
                                    $student['first_name'] . ' ' .
                                    ($student['middle_name'] ? $student['middle_name'] . ' ' : '') .
                                    $student['last_name']
                                )) ?>
                            </td>
                            <td><?= e($student['grade_level'] ?? 'Not assigned') ?></td>
                            <td><?= e($student['section_name'] ?? 'Not assigned') ?></td>
                            <td><?= e($student['contact_number'] ?? '—') ?></td>
                            <td><?= e($student['account_status']) ?></td>
                            <td><?= e($student['readmission_status']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
