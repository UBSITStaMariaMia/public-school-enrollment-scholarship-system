
<?php
require_once __DIR__ . '/includes/bootstrap.php';

if (current_user()) {
    redirect(dashboard_path_for_role(current_user()['role_name']));
}

$errors = [];
$success = false;
$generatedUsername = '';

$values = [
    'first_name' => '',
    'middle_name' => '',
    'last_name' => '',
    'lrn' => '',
    'email' => '',
    'birth_date' => '',
    'birthplace' => '',
    'sex' => '',
    'civil_status' => '',
    'nationality' => '',
    'religion' => '',
    'contact_number' => '',
    'current_address' => '',
    'permanent_address' => '',

    'mother_name' => '',
    'mother_occupation' => '',
    'mother_contact_number' => '',
    'mother_email' => '',
    'mother_address' => '',

    'father_name' => '',
    'father_occupation' => '',
    'father_contact_number' => '',
    'father_email' => '',
    'father_address' => '',

    'guardian_name' => '',
    'guardian_relationship' => '',
    'guardian_contact_number' => '',
    'guardian_email' => '',
    'guardian_occupation' => '',
    'guardian_address' => ''
];

$password = '';
$confirmPassword = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Collect form values safely.
    foreach ($values as $field => $oldValue) {
        $submittedValue = $_POST[$field] ?? '';

        if (!is_string($submittedValue)) {
            $errors[] = 'Invalid registration information submitted.';
            continue;
        }

        $values[$field] = trim($submittedValue);
    }

    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (!is_string($password) || !is_string($confirmPassword)) {
        $password = '';
        $confirmPassword = '';
        $errors[] = 'Invalid password information submitted.';
    }

    // CSRF validation.
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Your session expired. Please submit the form again.';
    }

    // Capitalize student names.
    foreach (['first_name', 'middle_name', 'last_name'] as $field) {
        $values[$field] = mb_convert_case(
            mb_strtolower($values[$field], 'UTF-8'),
            MB_CASE_TITLE,
            'UTF-8'
        );
    }

    // Required student information.
    if ($values['first_name'] === '' || $values['last_name'] === '') {
        $errors[] = 'First name and last name are required.';
    }

    if (
        strlen($values['first_name']) > 100 ||
        strlen($values['last_name']) > 100 ||
        strlen($values['middle_name']) > 100
    ) {
        $errors[] = 'Student names must not exceed 100 characters.';
    }

    // Validate email.
    if (!filter_var($values['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Enter a valid student email address.';
    }

    if (strlen($values['email']) > 150) {
        $errors[] = 'Email address must not exceed 150 characters.';
    }

    // Validate LRN: optional, but exactly 12 digits when provided.
    if ($values['lrn'] !== '' && !preg_match('/^[0-9]{12}$/', $values['lrn'])) {
        $errors[] = 'LRN must contain exactly 12 digits.';
    }

    // Validate sex.
    $allowedSex = ['', 'Male', 'Female', 'Prefer not to say'];

    if (!in_array($values['sex'], $allowedSex, true)) {
        $errors[] = 'Select a valid sex option.';
    }

    // Validate birth date.
    if ($values['birth_date'] !== '') {
        $date = DateTime::createFromFormat(
            '!Y-m-d',
            $values['birth_date']
        );

        if (!$date || $date->format('Y-m-d') !== $values['birth_date']) {
            $errors[] = 'Enter a valid birth date.';
        }
    }

    // Validate parent and guardian email addresses.
    foreach (['mother_email', 'father_email', 'guardian_email'] as $field) {
        if (
            $values[$field] !== '' &&
            !filter_var($values[$field], FILTER_VALIDATE_EMAIL)
        ) {
            $errors[] = 'Enter a valid email address for ' .
                str_replace('_', ' ', $field) . '.';
        }
    }

    // Validate Philippine contact numbers (10 digits after the +63 prefix).
    $phoneFields = [
        'contact_number' => 'Student',
        'mother_contact_number' => 'Mother',
        'father_contact_number' => 'Father',
        'guardian_contact_number' => 'Guardian'
    ];

    foreach ($phoneFields as $field => $person) {
        if (
            $values[$field] !== '' &&
            !preg_match('/^9[0-9]{9}$/', $values[$field])
        ) {
            $errors[] = $person . ' contact number must contain exactly 10 digits after +63.';
        }
    }

    // Validate password.
    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    }

    if ($password !== $confirmPassword) {
        $errors[] = 'Passwords do not match.';
    }

    // Check whether the guardian section contains information.
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

    // Generate username automatically.
    // Format: surname_firstinitialmiddleinitial
    if (!$errors) {

        $normalizedSurname = strtolower($values['last_name']);

        $normalizedSurname = preg_replace(
            '/[^a-z0-9.]/',
            '',
            $normalizedSurname
        );

        $firstName = strtolower($values['first_name']);
        $middleName = strtolower($values['middle_name']);

        $firstInitial = substr(
            preg_replace('/[^a-z0-9]/', '', $firstName),
            0,
            1
        );

        $middleInitial = substr(
            preg_replace('/[^a-z0-9]/', '', $middleName),
            0,
            1
        );

        if ($normalizedSurname === '' || $firstInitial === '') {
            $errors[] = 'Unable to generate username. Please check the name entered.';
        } else {

            $baseUsername = substr($normalizedSurname, 0, 50)
                . '_'
                . $firstInitial
                . $middleInitial;

            $usernameCheck = $pdo->prepare(
                'SELECT 1
                 FROM users
                 WHERE username = :username
                 LIMIT 1'
            );

            $generatedUsername = $baseUsername;
            $suffix = 2;

            while (true) {
                $usernameCheck->execute([
                    'username' => $generatedUsername
                ]);

                if (!$usernameCheck->fetchColumn()) {
                    break;
                }

                $suffixText = (string) $suffix;

                $generatedUsername = substr(
                    $baseUsername,
                    0,
                    60 - strlen($suffixText)
                ) . $suffixText;

                $suffix++;
            }
        }
    }

    // Check duplicate email.
    if (!$errors) {
        $emailCheck = $pdo->prepare(
            'SELECT 1
             FROM users
             WHERE email = :email
             LIMIT 1'
        );

        $emailCheck->execute([
            'email' => $values['email']
        ]);

        if ($emailCheck->fetchColumn()) {
            $errors[] = 'That email address is already registered.';
        }
    }

    // Validate LRN when provided. DepEd LRNs contain exactly 12 digits.
    if (
        $values['lrn'] !== '' &&
        !preg_match('/^[0-9]{12}$/', $values['lrn'])
    ) {
        $errors[] = 'LRN must contain exactly 12 digits.';
    }

    // Check duplicate LRN when provided.
    if (!$errors && $values['lrn'] !== '') {
        $lrnCheck = $pdo->prepare(
            'SELECT 1
             FROM students
             WHERE lrn = :lrn
             LIMIT 1'
        );

        $lrnCheck->execute([
            'lrn' => $values['lrn']
        ]);

        if ($lrnCheck->fetchColumn()) {
            $errors[] = 'That LRN is already registered to another student.';
        }
    }

    // Create the student account and save profile information.
    if (!$errors) {
        try {
            $pdo->beginTransaction();

            // Retrieve Student role.
            $roleStatement = $pdo->prepare(
                'SELECT role_id
                 FROM roles
                 WHERE role_name = :role
                 LIMIT 1'
            );

            $roleStatement->execute([
                'role' => 'Student'
            ]);

            $roleId = $roleStatement->fetchColumn();

            if (!$roleId) {
                throw new RuntimeException(
                    'Student role is missing from the database.'
                );
            }

            // Hash password.
            $passwordHash = password_hash(
                $password,
                PASSWORD_DEFAULT
            );

            // Insert user account.
            $userStatement = $pdo->prepare(
                'INSERT INTO users
                    (role_id, username, email, password_hash)
                 VALUES
                    (:role_id, :username, :email, :password_hash)'
            );

            $userStatement->execute([
                'role_id' => $roleId,
                'username' => $generatedUsername,
                'email' => $values['email'],
                'password_hash' => $passwordHash
            ]);

            $userId = (int) $pdo->lastInsertId();

            // Insert student personal and contact information.
            $studentStatement = $pdo->prepare(
                'INSERT INTO students (
                    user_id,
                    lrn,
                    first_name,
                    middle_name,
                    last_name,
                    birth_date,
                    birthplace,
                    sex,
                    civil_status,
                    nationality,
                    religion,
                    contact_number,
                    current_address,
                    permanent_address
                ) VALUES (
                    :user_id,
                    :lrn,
                    :first_name,
                    :middle_name,
                    :last_name,
                    :birth_date,
                    :birthplace,
                    :sex,
                    :civil_status,
                    :nationality,
                    :religion,
                    :contact_number,
                    :current_address,
                    :permanent_address
                )'
            );

            $studentStatement->execute([
                'user_id' => $userId,
                'lrn' => $values['lrn'] ?: null,
                'first_name' => $values['first_name'],
                'middle_name' => $values['middle_name'] ?: null,
                'last_name' => $values['last_name'],
                'birth_date' => $values['birth_date'] ?: null,
                'birthplace' => $values['birthplace'] ?: null,
                'sex' => $values['sex'] ?: null,
                'civil_status' => $values['civil_status'] ?: null,
                'nationality' => $values['nationality'] ?: null,
                'religion' => $values['religion'] ?: null,
                'contact_number' => $values['contact_number'] !== '' ? '+63' . $values['contact_number'] : null,
                'current_address' => $values['current_address'] ?: null,
                'permanent_address' => $values['permanent_address'] ?: null
            ]);

            $studentId = (int) $pdo->lastInsertId();

            // Insert mother and father information.
            $parentStatement = $pdo->prepare(
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

            $parentStatement->execute([
                'student_id' => $studentId,
                'mother_name' => $values['mother_name'] ?: null,
                'mother_occupation' => $values['mother_occupation'] ?: null,
                'mother_contact_number' => $values['mother_contact_number'] !== '' ? '+63' . $values['mother_contact_number'] : null,
                'mother_email' => $values['mother_email'] ?: null,
                'mother_address' => $values['mother_address'] ?: null,
                'father_name' => $values['father_name'] ?: null,
                'father_occupation' => $values['father_occupation'] ?: null,
                'father_contact_number' => $values['father_contact_number'] !== '' ? '+63' . $values['father_contact_number'] : null,
                'father_email' => $values['father_email'] ?: null,
                'father_address' => $values['father_address'] ?: null
            ]);

            // Insert additional guardian only when a name is provided.
            if ($values['guardian_name'] !== '') {
                $guardianStatement = $pdo->prepare(
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

                $guardianStatement->execute([
                    'student_id' => $studentId,
                    'full_name' => $values['guardian_name'],
                    'relationship' => $values['guardian_relationship'] ?: null,
                    'contact_number' => $values['guardian_contact_number'] !== '' ? '+63' . $values['guardian_contact_number'] : null,
                    'email' => $values['guardian_email'] ?: null,
                    'occupation' => $values['guardian_occupation'] ?: null,
                    'address' => $values['guardian_address'] ?: null
                ]);
            }

            // Commit all registration records.
            $pdo->commit();

            $success = true;

            // Clear submitted information.
            foreach ($values as $field => $oldValue) {
                $values[$field] = '';
            }

            $password = '';
            $confirmPassword = '';

        } catch (Throwable $exception) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log(
                'Student registration error: ' .
                $exception->getMessage()
            );

            $errors[] = 'Registration could not be completed. Please try again.';
        }
    }
}

$pageTitle = 'Student Registration';
require_once __DIR__ . '/includes/header.php';
?>

<style>
    .registration-form-grid {
        row-gap: 24px;
        column-gap: 24px;
    }

    .registration-form-grid > label {
        display: flex;
        flex-direction: column;
        gap: 8px;
        margin-bottom: 4px;
    }

    .registration-form-grid > label input,
    .registration-form-grid > label select,
    .registration-form-grid > label textarea {
        margin-top: 0;
    }

    /* Prevent users from manually resizing address textareas. */
    .registration-form-grid textarea {
        resize: none;
    }

    .registration-form-grid > h2 {
        margin-top: 12px;
        margin-bottom: 0;
    }

    .registration-form-grid .inline-label {
        display: flex;
        align-items: baseline;
        gap: 6px;
    }

    .registration-form-grid .inline-label .muted {
        display: inline;
        margin: 0;
    }

    .registration-form-grid .phone-input-wrapper {
        display: flex;
        align-items: center;
        width: 100%;
        min-height: 48px;
        border: 1px solid #cbd5e1;
        border-radius: 8px;
        background: #fff;
        overflow: hidden;
    }

    .registration-form-grid .phone-prefix {
        padding: 0 12px;
        color: #374151;
        font-weight: 600;
        white-space: nowrap;
    }

    .registration-form-grid .phone-divider {
        width: 1px;
        align-self: stretch;
        background: #cbd5e1;
    }

    .registration-form-grid .phone-input-wrapper input {
        flex: 1;
        min-width: 0;
        width: 100%;
        border: 0;
        outline: 0;
        box-shadow: none;
        margin: 0;
    }

    .registration-form-grid .phone-input-wrapper:focus-within {
        border-color: #7b1e3a;
        box-shadow: 0 0 0 3px rgba(123, 30, 58, 0.12);
    }


    /* Registration reminder modal: self-contained so it works even if style.css is cached or not updated. */
    .registration-reminder-overlay {
        position: fixed;
        inset: 0;
        z-index: 99999;
        display: none;
        align-items: center;
        justify-content: center;
        padding: 20px;
        background: rgba(20, 24, 32, .70);
        backdrop-filter: blur(3px);
        animation: reminderFadeIn 260ms ease-out both;
    }
    .registration-reminder-modal {
        width: min(100%, 650px);
        max-height: 85vh;
        overflow-y: auto;
        border: 1px solid #e5e7eb;
        border-radius: 14px;
        background: #fff;
        color: #1f2937;
        box-shadow: 0 18px 55px rgba(0, 0, 0, .28);
        animation: reminderSlideIn 320ms cubic-bezier(.2, .8, .2, 1) both;
        transform-origin: center;
    }
    @keyframes reminderFadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }
    @keyframes reminderSlideIn {
        from { opacity: 0; transform: translateY(18px) scale(.97); }
        to { opacity: 1; transform: translateY(0) scale(1); }
    }
    @media (prefers-reduced-motion: reduce) {
        .registration-reminder-overlay,
        .registration-reminder-modal { animation: none; }
    }
    .registration-reminder-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        padding: 17px 24px;
        border-bottom: 1px solid #e5e7eb;
    }
    .registration-reminder-header h2 { margin: 0; color: #64152d; font-size: 1.15rem; line-height: 1.35; }
    .registration-reminder-close {
        display: inline-flex; align-items: center; justify-content: center;
        width: 36px; height: 36px; padding: 0; border: 0; border-radius: 8px;
        background: transparent; color: #6b7280; font-size: 1.8rem; line-height: 1; cursor: pointer;
    }
    .registration-reminder-body { padding: 22px 26px 26px; line-height: 1.6; }
    .registration-reminder-body > p { margin: 0 0 16px; color: #6b7280; }
    .registration-reminder-body ul { margin: 0; padding-left: 22px; }
    .registration-reminder-body li { margin: 0 0 12px; }
    .registration-reminder-note { margin-top: 20px; padding: 14px 16px; border-left: 4px solid #7b1e3a; border-radius: 7px; background: #f8f1f4; color: #4b2634; }
    .registration-reminder-actions { display: flex; justify-content: flex-end; margin-top: 22px; }
    .registration-reminder-confirm { border: 0; border-radius: 8px; padding: 11px 20px; background: #7b1e3a; color: #fff; font-weight: 600; cursor: pointer; }
    body.reminder-modal-open { overflow: hidden; }
    @media (max-width: 600px) { .registration-reminder-overlay { padding: 12px; } .registration-reminder-modal { max-height: 90vh; } .registration-reminder-body { padding: 18px; } }
</style>

<section class="form-card form-card-wide">

    <p class="eyebrow">Create an account</p>

    <h1>Student Registration</h1>

    <p class="muted">
        Create your student account and provide your personal,
        contact, parent, and guardian information.
    </p>

    <?php if ($success): ?>

        <div class="alert alert-success">
            Your student account has been created successfully.
            <br>
            Your generated username is:
            <strong><?= e($generatedUsername) ?></strong>
            <br>
            Please remember your username when logging in.
            <br>
            <a href="<?= BASE_URL ?>/login.php">Continue to Log In</a>.
        </div>

    <?php endif; ?>

    <?php foreach ($errors as $message): ?>

        <div class="alert alert-error">
            <?= e($message) ?>
        </div>

    <?php endforeach; ?>

    <?php if (!$success): ?>

        <form method="post" class="form-grid registration-form-grid">

            <input
                type="hidden"
                name="csrf_token"
                value="<?= e(csrf_token()) ?>"
            >

            <h2>1. Student Personal Information</h2>

            <label>
                Learner Reference Number (LRN)
                <input
                    type="text"
                    name="lrn"
                    inputmode="numeric"
                    maxlength="12"
                    pattern="[0-9]{12}"
                    title="LRN must contain exactly 12 digits."
                    oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 12)"
                    value="<?= e($values['lrn']) ?>"
                >
            </label>

            <label>
                First Name
                <input
                    type="text"
                    name="first_name"
                    maxlength="100"
                    autocomplete="given-name"
                    value="<?= e($values['first_name']) ?>"
                    required
                >
            </label>

            <label>
                <span class="inline-label">
                    <span>Middle Name</span>
                    <span class="muted">(Optional)</span>
                </span>
                <input
                    type="text"
                    name="middle_name"
                    maxlength="100"
                    autocomplete="additional-name"
                    value="<?= e($values['middle_name']) ?>"
                >
            </label>

            <label>
                Last Name
                <input
                    type="text"
                    name="last_name"
                    maxlength="100"
                    autocomplete="family-name"
                    value="<?= e($values['last_name']) ?>"
                    required
                >
            </label>

            <label>
                Email Address
                <input
                    type="email"
                    name="email"
                    maxlength="150"
                    autocomplete="email"
                    value="<?= e($values['email']) ?>"
                    required
                >
            </label>

            <label>
                Date of Birth
                <input
                    type="date"
                    name="birth_date"
                    value="<?= e($values['birth_date']) ?>"
                >
            </label>

            <label>
                Place of Birth
                <input
                    type="text"
                    name="birthplace"
                    maxlength="150"
                    value="<?= e($values['birthplace']) ?>"
                >
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
                <input
                    type="text"
                    name="civil_status"
                    maxlength="40"
                    value="<?= e($values['civil_status']) ?>"
                >
            </label>

            <label>
                Nationality
                <input
                    type="text"
                    name="nationality"
                    maxlength="80"
                    value="<?= e($values['nationality']) ?>"
                >
            </label>

            <label>
                Religion
                <input
                    type="text"
                    name="religion"
                    maxlength="100"
                    value="<?= e($values['religion']) ?>"
                >
            </label>

            <h2>2. Contact Information</h2>

            <label>
                Contact Number
                <div class="phone-input-wrapper">
                    <span class="phone-prefix">+63</span>
                    <span class="phone-divider" aria-hidden="true"></span>
                    <input
                        type="tel"
                        name="contact_number"
                        inputmode="numeric"
                        pattern="9[0-9]{9}"
                        minlength="10"
                        maxlength="10"
                        placeholder="9123456789"
                        title="Enter exactly 10 digits after +63."
                        oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 10);"
                        value="<?= e($values['contact_number']) ?>"
                    >
                </div>
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
                <input
                    type="text"
                    name="mother_name"
                    maxlength="150"
                    value="<?= e($values['mother_name']) ?>"
                >
            </label>

            <label>
                Occupation
                <input
                    type="text"
                    name="mother_occupation"
                    maxlength="150"
                    value="<?= e($values['mother_occupation']) ?>"
                >
            </label>

            <label>
                Contact Number
                <div class="phone-input-wrapper">
                    <span class="phone-prefix">+63</span>
                    <span class="phone-divider" aria-hidden="true"></span>
                    <input
                        type="tel"
                        name="mother_contact_number"
                        inputmode="numeric"
                        pattern="9[0-9]{9}"
                        minlength="10"
                        maxlength="10"
                        placeholder="9123456789"
                        title="Enter exactly 10 digits after +63."
                        oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 10);"
                        value="<?= e($values['mother_contact_number']) ?>"
                    >
                </div>
            </label>

            <label>
                Email Address
                <input
                    type="email"
                    name="mother_email"
                    maxlength="150"
                    value="<?= e($values['mother_email']) ?>"
                >
            </label>

            <label>
                Address
                <textarea name="mother_address" rows="3"><?= e($values['mother_address']) ?></textarea>
            </label>

            <h2>4. Father's Information</h2>

            <label>
                Father's Full Name
                <input
                    type="text"
                    name="father_name"
                    maxlength="150"
                    value="<?= e($values['father_name']) ?>"
                >
            </label>

            <label>
                Occupation
                <input
                    type="text"
                    name="father_occupation"
                    maxlength="150"
                    value="<?= e($values['father_occupation']) ?>"
                >
            </label>

            <label>
                Contact Number
                <div class="phone-input-wrapper">
                    <span class="phone-prefix">+63</span>
                    <span class="phone-divider" aria-hidden="true"></span>
                    <input
                        type="tel"
                        name="father_contact_number"
                        inputmode="numeric"
                        pattern="9[0-9]{9}"
                        minlength="10"
                        maxlength="10"
                        placeholder="9123456789"
                        title="Enter exactly 10 digits after +63."
                        oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 10);"
                        value="<?= e($values['father_contact_number']) ?>"
                    >
                </div>
            </label>

            <label>
                Email Address
                <input
                    type="email"
                    name="father_email"
                    maxlength="150"
                    value="<?= e($values['father_email']) ?>"
                >
            </label>

            <label>
                Address
                <textarea name="father_address" rows="3"><?= e($values['father_address']) ?></textarea>
            </label>

            <h2>5. Additional Guardian Information</h2>

            <p class="muted">
                Complete this section if someone other than the mother
                or father is responsible for the student's care.
                This does not create a separate guardian account.
            </p>

            <label>
                Guardian's Full Name
                <input
                    type="text"
                    name="guardian_name"
                    maxlength="150"
                    value="<?= e($values['guardian_name']) ?>"
                >
            </label>

            <label>
                Relationship to Student
                <input
                    type="text"
                    name="guardian_relationship"
                    maxlength="60"
                    value="<?= e($values['guardian_relationship']) ?>"
                >
            </label>

            <label>
                Contact Number
                <div class="phone-input-wrapper">
                    <span class="phone-prefix">+63</span>
                    <span class="phone-divider" aria-hidden="true"></span>
                    <input
                        type="tel"
                        name="guardian_contact_number"
                        inputmode="numeric"
                        pattern="9[0-9]{9}"
                        minlength="10"
                        maxlength="10"
                        placeholder="9123456789"
                        title="Enter exactly 10 digits after +63."
                        oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 10);"
                        value="<?= e($values['guardian_contact_number']) ?>"
                    >
                </div>
            </label>

            <label>
                Email Address
                <input
                    type="email"
                    name="guardian_email"
                    maxlength="150"
                    value="<?= e($values['guardian_email']) ?>"
                >
            </label>

            <label>
                Occupation
                <input
                    type="text"
                    name="guardian_occupation"
                    maxlength="150"
                    value="<?= e($values['guardian_occupation']) ?>"
                >
            </label>

            <label>
                Address
                <textarea name="guardian_address" rows="3"><?= e($values['guardian_address']) ?></textarea>
            </label>

            <h2>Account Security</h2>

            <label>
                Password
                <input
                    type="password"
                    name="password"
                    minlength="8"
                    autocomplete="new-password"
                    required
                >
            </label>

            <label>
                Confirm Password
                <input
                    type="password"
                    name="confirm_password"
                    minlength="8"
                    autocomplete="new-password"
                    required
                >
            </label>

            <div class="form-actions">
                <button
                    class="button button-primary"
                    type="submit"
                >
                    Create Student Account
                </button>
            </div>

        </form>

    <?php endif; ?>

</section>



<div
    class="registration-reminder-overlay"
    id="registrationReminder"
    role="dialog"
    aria-modal="true"
    aria-labelledby="registrationReminderTitle"
    aria-describedby="registrationReminderDescription"
>
    <div class="registration-reminder-modal">
        <div class="registration-reminder-header">
            <h2 id="registrationReminderTitle">BCNHS Registration Reminders</h2>
            <button
                type="button"
                class="registration-reminder-close"
                id="closeRegistrationReminder"
                aria-label="Close reminder"
            >&times;</button>
        </div>

        <div class="registration-reminder-body" id="registrationReminderDescription">
            <p>
                Before proceeding, please read these reminders carefully:
            </p>

            <ul>
                <li>
                    <strong>Provide accurate information.</strong>
                    Make sure that all details entered in the registration form
                    are correct, complete, and consistent with the student's
                    official documents. Check the spelling of names, birth date
                    and birth year, LRN, contact numbers, and parent or guardian
                    information before submitting.
                </li>
                <li>
                    <strong>Review your information before submission.</strong>
                    Incorrect or incomplete details may cause delays during
                    enrollment checking and document verification.
                </li>
            </ul>

            <div class="registration-reminder-note">
                Please ensure that the information you submit is truthful and
                accurate. The school may verify the details against the
                submitted enrollment documents.
            </div>

            <div class="registration-reminder-actions">
                <button
                    type="button"
                    class="registration-reminder-confirm"
                    id="confirmRegistrationReminder"
                >
                    I Understand
                </button>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    const overlay = document.getElementById('registrationReminder');
    const closeButton = document.getElementById('closeRegistrationReminder');
    const confirmButton = document.getElementById('confirmRegistrationReminder');

    if (!overlay) return;

    function closeReminder() {
        overlay.style.display = 'none';
        overlay.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('reminder-modal-open');
    }

    // Always show the reminder whenever the registration page is loaded.
    overlay.style.display = 'flex';
    overlay.removeAttribute('aria-hidden');
    document.body.classList.add('reminder-modal-open');

    if (closeButton) closeButton.addEventListener('click', closeReminder);
    if (confirmButton) confirmButton.addEventListener('click', closeReminder);

    overlay.addEventListener('click', function (event) {
        if (event.target === overlay) closeReminder();
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && overlay.style.display !== 'none') closeReminder();
    });
})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
