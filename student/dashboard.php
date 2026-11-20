
<?php
require_once __DIR__ . '/../includes/bootstrap.php';

require_role('Student');

// Retrieve the student's information.
$studentQuery = $pdo->prepare(
    'SELECT first_name, middle_name, last_name
     FROM students
     WHERE user_id = :user_id
     LIMIT 1'
);

$studentQuery->execute([
    'user_id' => current_user()['user_id']
]);

$student = $studentQuery->fetch();

// Format the student's name.
if ($student) {

    // Get middle initial.
    $middleInitial = '';

    if (!empty($student['middle_name'])) {
        $middleInitial = mb_substr(
            trim($student['middle_name']),
            0,
            1,
            'UTF-8'
        ) . '.';
    }

    // Capitalize surname after periods, spaces, and hyphens.
    $lastName = mb_strtolower(
        trim($student['last_name']),
        'UTF-8'
    );

    $lastName = preg_replace_callback(
        '/(^|[.\s-])(\p{L})/u',
        function ($matches) {
            return $matches[1] . mb_strtoupper(
                $matches[2],
                'UTF-8'
            );
        },
        $lastName
    );

    // Display first name, middle initial, and surname.
    $studentName = trim(
        $student['first_name'] . ' ' .
        ($middleInitial !== '' ? $middleInitial . ' ' : '') .
        $lastName
    );

} else {
    $studentName = current_user()['username'];
}

$pageTitle = 'Student Dashboard';

require_once __DIR__ . '/../includes/header.php';
?>

<section class="welcome-banner">

    <p class="eyebrow">Student Portal</p>

    <h1>Welcome, <?= e($studentName) ?></h1>

    <p>
        Manage your enrollment, review your applications,
        and maintain your student information.
    </p>

</section>

<section class="dashboard-grid">

    <a
        class="dashboard-card"
        href="<?= BASE_URL ?>/student/new_enrollment.php"
    >
        <span class="card-kicker">01</span>
        <h2>New Enrollment</h2>
        <p>
            Submit your enrollment details and required documents.
        </p>
    </a>

    <a
        class="dashboard-card"
        href="<?= BASE_URL ?>/student/my_applications.php"
    >
        <span class="card-kicker">02</span>
        <h2>My Applications</h2>
        <p>
            Track enrollment and scholarship application statuses separately.
        </p>
    </a>

</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
