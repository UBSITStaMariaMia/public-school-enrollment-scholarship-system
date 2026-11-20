
<?php
require_once __DIR__ . '/../includes/bootstrap.php';

require_role('Student');

$userId = (int) current_user()['user_id'];

$errors = [];
$success = '';

$studentQuery = $pdo->prepare(
    'SELECT s.*, u.email
     FROM students s
     INNER JOIN users u ON u.user_id = s.user_id
     WHERE s.user_id = :user_id
     LIMIT 1'
);
$studentQuery->execute(['user_id' => $userId]);
$student = $studentQuery->fetch();

if (!$student) {
    http_response_code(404);
    exit('Student profile not found.');
}

$studentId = (int) $student['student_id'];

// Get mother and father information.
$parentQuery = $pdo->prepare(
    'SELECT *
     FROM student_parent_information
     WHERE student_id = :student_id
     LIMIT 1'
);
$parentQuery->execute(['student_id' => $studentId]);
$parents = $parentQuery->fetch() ?: [];

// Get the primary additional guardian.
$guardianQuery = $pdo->prepare(
    'SELECT *
     FROM student_guardians
     WHERE student_id = :student_id
       AND is_primary = 1
     ORDER BY guardian_id DESC
     LIMIT 1'
);
$guardianQuery->execute(['student_id' => $studentId]);
$guardian = $guardianQuery->fetch() ?: [];

// Default form values.
$values = [
    'first_name' => $student['first_name'],
    'middle_name' => $student['middle_name'] ?? '',
    'last_name' => $student['last_name'],
    'lrn' => $student['lrn'] ?? '',
    'email' => $student['email'],
    'birth_date' => $student['birth_date'] ?? '',
    'birthplace' => $student['birthplace'] ?? '',
    'sex' => $student['sex'] ?? '',
    'civil_status' => $student['civil_status'] ?? '',
    'nationality' => $student['nationality'] ?? '',
    'religion' => $student['religion'] ?? '',
    'contact_number' => $student['contact_number'] ?? '',
    'current_address' => $student['current_address'] ?? '',
    'permanent_address' => $student['permanent_address'] ?? '',

    'mother_name' => $parents['mother_name'] ?? '',
    'mother_occupation' => $parents['mother_occupation'] ?? '',
    'mother_contact_number' => $parents['mother_contact_number'] ?? '',
    'mother_email' => $parents['mother_email'] ?? '',
    'mother_address' => $parents['mother_address'] ?? '',

    'father_name' => $parents['father_name'] ?? '',
    'father_occupation' => $parents['father_occupation'] ?? '',
    'father_contact_number' => $parents['father_contact_number'] ?? '',
    'father_email' => $parents['father_email'] ?? '',
    'father_address' => $parents['father_address'] ?? '',

    'guardian_name' => $guardian['full_name'] ?? '',
    'guardian_relationship' => $guardian['relationship'] ?? '',
    'guardian_contact_number' => $guardian['contact_number'] ?? '',
    'guardian_email' => $guardian['email'] ?? '',
    'guardian_occupation' => $guardian['occupation'] ?? '',
    'guardian_address' => $guardian['address'] ?? '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    foreach ($values as $field => $oldValue) {
        $values[$field] = trim($_POST[$field] ?? '');
    }

    // CSRF validation.
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Your session expired. Please refresh the page and try again.';
    }

    // Required student information.
    if ($values['first_name'] === '' || $values['last_name'] === '') {
        $errors[] = 'First name and last name are required.';
    }

    if (!filter_var($values['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Enter a valid student email address.';
    }

    if ($values['email'] !== '' && strlen($values['email']) > 150) {
        $errors[] = 'Student email must not exceed 150 characters.';
    }

    // Validate sex against the database enum.
    $allowedSex = ['', 'Male', 'Female', 'Prefer not to say'];

    if (!in_array($values['sex'], $allowedSex, true)) {
        $errors[] = 'Select a valid sex option.';
    }

    // Validate birth date.
    if ($values['birth_date'] !== '') {
        $date = DateTime::createFromFormat('!Y-m-d', $values['birth_date']);

        if (!$date || $date->format('Y-m-d') !== $values['birth_date']) {
            $errors[] = 'Enter a valid birth date.';
        }
    }

    // Check duplicate email.
    if (!$errors) {
        $emailCheck = $pdo->prepare(
            'SELECT COUNT(*)
             FROM users
             WHERE email = :email
               AND user_id <> :user_id'
        );

        $emailCheck->execute([
            'email' => $values['email'],
            'user_id' => $userId
        ]);

        if ((int) $emailCheck->fetchColumn() > 0) {
            $errors[] = 'That email address is already being used by another account.';
        }
    }

    // Check duplicate LRN.
    if (!$errors && $values['lrn'] !== '') {
        $lrnCheck = $pdo->prepare(
            'SELECT COUNT(*)
             FROM students
             WHERE lrn = :lrn
               AND student_id <> :student_id'
        );

        $lrnCheck->execute([
            'lrn' => $values['lrn'],
            'student_id' => $studentId
        ]);

        if ((int) $lrnCheck->fetchColumn() > 0) {
            $errors[] = 'That LRN is already registered to another student.';
        }
    }

    // Validate parent email addresses when provided.
    foreach (['mother_email', 'father_email', 'guardian_email'] as $emailField) {
        if (
            $values[$emailField] !== '' &&
            !filter_var($values[$emailField], FILTER_VALIDATE_EMAIL)
        ) {
            $errors[] = 'Please enter a valid email address for ' .
                str_replace('_', ' ', $emailField) . '.';
        }
    }

    // Validate additional guardian name.
    $guardianHasInformation = false;

    foreach ([
        'guardian_name',
        'guardian_relationship',
        'guardian_contact_number',
        'guardian_email',
        'guardian_occupation',
        'guardian_address'
    ] as $field) {
        if ($values[$field] !== '') {
            $guardianHasInformation = true;
            break;
        }
    }

    if ($guardianHasInformation && $values['guardian_name'] === '') {
        $errors[] = 'Enter the full name of the additional guardian.';
    }

    // Save all profile sections.
    if (!$errors) {
        try {
            $pdo->beginTransaction();

            // Update student account email.
            $updateUser = $pdo->prepare(
                'UPDATE users
                 SET email = :email
                 WHERE user_id = :user_id'
            );

            $updateUser->execute([
                'email' => $values['email'],
                'user_id' => $userId
            ]);

            // Update student personal and contact information.
            $updateStudent = $pdo->prepare(
                'UPDATE students
                 SET
                    first_name = :first_name,
                    middle_name = :middle_name,
                    last_name = :last_name,
                    lrn = :lrn,
                    birth_date = :birth_date,
                    birthplace = :birthplace,
                    sex = :sex,
                    civil_status = :civil_status,
                    nationality = :nationality,
                    religion = :religion,
                    contact_number = :contact_number,
                    current_address = :current_address,
                    permanent_address = :permanent_address
                 WHERE student_id = :student_id'
            );

            $updateStudent->execute([
                'first_name' => $values['first_name'],
                'middle_name' => $values['middle_name'] ?: null,
                'last_name' => $values['last_name'],
                'lrn' => $values['lrn'] ?: null,
                'birth_date' => $values['birth_date'] ?: null,
                'birthplace' => $values['birthplace'] ?: null,
                'sex' => $values['sex'] ?: null,
                'civil_status' => $values['civil_status'] ?: null,
                'nationality' => $values['nationality'] ?: null,
                'religion' => $values['religion'] ?: null,
                'contact_number' => $values['contact_number'] ?: null,
                'current_address' => $values['current_address'] ?: null,
                'permanent_address' => $values['permanent_address'] ?: null,
                'student_id' => $studentId
            ]);

            // Save mother and father information.
            $parentData = [
                'mother_name' => $values['mother_name'] ?: null,
                'mother_occupation' => $values['mother_occupation'] ?: null,
                'mother_contact_number' => $values['mother_contact_number'] ?: null,
                'mother_email' => $values['mother_email'] ?: null,
                'mother_address' => $values['mother_address'] ?: null,

                'father_name' => $values['father_name'] ?: null,
                'father_occupation' => $values['father_occupation'] ?: null,
                'father_contact_number' => $values['father_contact_number'] ?: null,
                'father_email' => $values['father_email'] ?: null,
                'father_address' => $values['father_address'] ?: null,
            ];

            if ($parents) {
                $parentUpdate = $pdo->prepare(
                    'UPDATE student_parent_information
                     SET
                        mother_name = :mother_name,
                        mother_occupation = :mother_occupation,
                        mother_contact_number = :mother_contact_number,
                        mother_email = :mother_email,
                        mother_address = :mother_address,
                        father_name = :father_name,
                        father_occupation = :father_occupation,
                        father_contact_number = :father_contact_number,
                        father_email = :father_email,
                        father_address = :father_address
                     WHERE student_id = :student_id'
                );

                $parentUpdate->execute(
                    $parentData + ['student_id' => $studentId]
                );
            } else {
                $parentInsert = $pdo->prepare(
                    'INSERT INTO student_parent_information (
                        student_id,
                        mother_name,
                        mother_occupation,
                        mother_contact_number,
                        mother_email,
                        mother_address,
                        father_name,
                        father_occupation,
                        father_contact_number,
                        father_email,
                        father_address
                    ) VALUES (
                        :student_id,
                        :mother_name,
                        :mother_occupation,
                        :mother_contact_number,
                        :mother_email,
                        :mother_address,
                        :father_name,
                        :father_occupation,
                        :father_contact_number,
                        :father_email,
                        :father_address
                    )'
                );

                $parentInsert->execute(
                    $parentData + ['student_id' => $studentId]
                );
            }

            // Save the primary additional guardian.
            if ($values['guardian_name'] !== '') {

                $guardianData = [
                    'full_name' => $values['guardian_name'],
                    'relationship' => $values['guardian_relationship'] ?: null,
                    'contact_number' => $values['guardian_contact_number'] ?: null,
                    'email' => $values['guardian_email'] ?: null,
                    'occupation' => $values['guardian_occupation'] ?: null,
                    'address' => $values['guardian_address'] ?: null,
                ];

                if ($guardian) {
                    $guardianUpdate = $pdo->prepare(
                        'UPDATE student_guardians
                         SET
                            full_name = :full_name,
                            relationship = :relationship,
                            contact_number = :contact_number,
                            email = :email,
                            occupation = :occupation,
                            address = :address,
                            is_primary = 1
                         WHERE guardian_id = :guardian_id
                           AND student_id = :student_id'
                    );

                    $guardianUpdate->execute(
                        $guardianData + [
                            'guardian_id' => $guardian['guardian_id'],
                            'student_id' => $studentId
                        ]
                    );
                } else {
                    $guardianInsert = $pdo->prepare(
                        'INSERT INTO student_guardians (
                            student_id,
                            full_name,
                            relationship,
                            contact_number,
                            email,
                            occupation,
                            address,
                            is_primary
                        ) VALUES (
                            :student_id,
                            :full_name,
                            :relationship,
                            :contact_number,
                            :email,
                            :occupation,
                            :address,
                            1
                        )'
                    );

                    $guardianInsert->execute(
                        $guardianData + ['student_id' => $studentId]
                    );
                }

            } elseif ($guardian) {
                // Remove the primary additional guardian if the form is cleared.
                $guardianDelete = $pdo->prepare(
                    'DELETE FROM student_guardians
                     WHERE guardian_id = :guardian_id
                       AND student_id = :student_id'
                );

                $guardianDelete->execute([
                    'guardian_id' => $guardian['guardian_id'],
                    'student_id' => $studentId
                ]);
            }

            $pdo->commit();

            // Update the email displayed in the current session.
            $_SESSION['user']['email'] = $values['email'];

            $success = 'Your student profile has been updated successfully.';

            // Refresh stored profile information.
            $studentQuery->execute(['user_id' => $userId]);
            $student = $studentQuery->fetch();

            $parentQuery->execute(['student_id' => $studentId]);
            $parents = $parentQuery->fetch() ?: [];

            $guardianQuery->execute(['student_id' => $studentId]);
            $guardian = $guardianQuery->fetch() ?: [];

        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log('Student profile update error: ' . $exception->getMessage());
            $errors[] = 'Your profile could not be saved. Please try again.';
        }
    }
}

$pageTitle = 'My Profile';
require_once __DIR__ . '/../includes/header.php';
?>

<section class="form-card form-card-wide">
    <p class="eyebrow">Student Information</p>
    <h1>My Profile</h1>
    <p class="muted">
        View, edit, and save your personal information and parent or guardian details.
    </p>

    <?php if ($success): ?>
        <div class="alert alert-success">
            <?= e($success) ?>
        </div>
    <?php endif; ?>

    <?php foreach ($errors as $message): ?>
        <div class="alert alert-error">
            <?= e($message) ?>
        </div>
    <?php endforeach; ?>

    <form method="post" class="form-grid">

        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

        <h2>1. Student Personal Information</h2>

        <label>
            Learner Reference Number (LRN)
            <input type="text" name="lrn" maxlength="20"
                value="<?= e($values['lrn']) ?>">
        </label>

        <label>
            First Name
            <input type="text" name="first_name" maxlength="100"
                value="<?= e($values['first_name']) ?>" required>
        </label>

        <label>
            Middle Name
            <input type="text" name="middle_name" maxlength="100"
                value="<?= e($values['middle_name']) ?>">
        </label>

        <label>
            Last Name
            <input type="text" name="last_name" maxlength="100"
                value="<?= e($values['last_name']) ?>" required>
        </label>

        <label>
            Email Address
            <input type="email" name="email" maxlength="150"
                value="<?= e($values['email']) ?>" required>
        </label>

        <label>
            Date of Birth
            <input type="date" name="birth_date"
                value="<?= e($values['birth_date']) ?>">
        </label>

        <label>
            Place of Birth
            <input type="text" name="birthplace" maxlength="150"
                value="<?= e($values['birthplace']) ?>">
        </label>

        <label>
            Sex
            <select name="sex">
                <option value="">Select</option>
                <option value="Male" <?= $values['sex'] === 'Male' ? 'selected' : '' ?>>Male</option>
                <option value="Female" <?= $values['sex'] === 'Female' ? 'selected' : '' ?>>Female</option>
                <option value="Prefer not to say" <?= $values['sex'] === 'Prefer not to say' ? 'selected' : '' ?>>Prefer not to say</option>
            </select>
        </label>

        <label>
            Civil Status
            <input type="text" name="civil_status" maxlength="40"
                value="<?= e($values['civil_status']) ?>">
        </label>

        <label>
            Nationality
            <input type="text" name="nationality" maxlength="80"
                value="<?= e($values['nationality']) ?>">
        </label>

        <label>
            Religion
            <input type="text" name="religion" maxlength="100"
                value="<?= e($values['religion']) ?>">
        </label>

        <h2>2. Contact Information</h2>

        <label>
            Contact Number
            <input type="text" name="contact_number" maxlength="30"
                value="<?= e($values['contact_number']) ?>">
        </label>

        <label>
            Current Address
            <textarea name="current_address" rows="3"><?= e($values['current_address']) ?></textarea>
        </label>

        <label>
            Permanent Address
            <textarea name="permanent_address" rows="3"><?= e($values['permanent_address']) ?></textarea>
        </label>

        <h2>3. Mother's Information</h2>

        <label>
            Mother's Full Name
            <input type="text" name="mother_name" maxlength="150"
                value="<?= e($values['mother_name']) ?>">
        </label>

        <label>
            Occupation
            <input type="text" name="mother_occupation" maxlength="150"
                value="<?= e($values['mother_occupation']) ?>">
        </label>

        <label>
            Contact Number
            <input type="text" name="mother_contact_number" maxlength="30"
                value="<?= e($values['mother_contact_number']) ?>">
        </label>

        <label>
            Email Address
            <input type="email" name="mother_email" maxlength="150"
                value="<?= e($values['mother_email']) ?>">
        </label>

        <label>
            Address
            <textarea name="mother_address" rows="3"><?= e($values['mother_address']) ?></textarea>
        </label>

        <h2>4. Father's Information</h2>

        <label>
            Father's Full Name
            <input type="text" name="father_name" maxlength="150"
                value="<?= e($values['father_name']) ?>">
        </label>

        <label>
            Occupation
            <input type="text" name="father_occupation" maxlength="150"
                value="<?= e($values['father_occupation']) ?>">
        </label>

        <label>
            Contact Number
            <input type="text" name="father_contact_number" maxlength="30"
                value="<?= e($values['father_contact_number']) ?>">
        </label>

        <label>
            Email Address
            <input type="email" name="father_email" maxlength="150"
                value="<?= e($values['father_email']) ?>">
        </label>

        <label>
            Address
            <textarea name="father_address" rows="3"><?= e($values['father_address']) ?></textarea>
        </label>

        <h2>5. Additional Guardian Information</h2>

        <p class="muted">
            Use this section if someone other than the mother or father
            is responsible for the student's care.
            This does not create a separate guardian account.
        </p>

        <label>
            Guardian's Full Name
            <input type="text" name="guardian_name" maxlength="150"
                value="<?= e($values['guardian_name']) ?>">
        </label>

        <label>
            Relationship to Student
            <input type="text" name="guardian_relationship" maxlength="60"
                value="<?= e($values['guardian_relationship']) ?>">
        </label>

        <label>
            Contact Number
            <input type="text" name="guardian_contact_number" maxlength="30"
                value="<?= e($values['guardian_contact_number']) ?>">
        </label>

        <label>
            Email Address
            <input type="email" name="guardian_email" maxlength="150"
                value="<?= e($values['guardian_email']) ?>">
        </label>

        <label>
            Occupation
            <input type="text" name="guardian_occupation" maxlength="150"
                value="<?= e($values['guardian_occupation']) ?>">
        </label>

        <label>
            Address
            <textarea name="guardian_address" rows="3"><?= e($values['guardian_address']) ?></textarea>
        </label>

        <div class="form-actions">
            <button class="button button-primary" type="submit">
                Save Profile
            </button>
        </div>

    </form>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
