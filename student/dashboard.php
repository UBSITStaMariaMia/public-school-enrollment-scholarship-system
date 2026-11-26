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

$dashboardLinks = [
    ['New Enrollment', '/student/new_enrollment.php', 'new-enrollment', 'Submit an enrollment application for the active school year.'],
    ['Scholarship Application', '/student/scholarship_application.php', 'award', 'Check the available programs and apply for a scholarship.'],
    ['My Records', '/student/records.php', 'student-record', 'View your academic and attendance records.'],
    ['My Applications', '/student/my_applications.php', 'applications', 'Check the status of your applications.'],
    ['Announcements', '/announcements.php', 'announcement', 'Read school updates and notices.'],
    ['My Profile', '/student/profile.php', 'profile', 'View and update your student information.'],
    ['Change Password', '/student/change_password.php', 'password', 'Update the password for your portal account.'],
];
$dashboardHeading = 'Welcome, ' . $studentName;

require_once __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/dashboard_shortcuts.php';
require_once __DIR__ . '/../includes/footer.php';
