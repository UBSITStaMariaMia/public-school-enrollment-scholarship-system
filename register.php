
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
    'nationality_choice' => '',
    'nationality_other' => '',
    'religion' => '',
    'religion_choice' => '',
    'religion_other' => '',
    'indigenous_people_status' => '',
    'indigenous_people_group' => '',
    'is_pwd' => '',
    'disability_details' => '',
    'current_house' => '',
    'current_street' => '',
    'current_barangay' => '',
    'current_city' => '',
    'current_province' => '',
    'current_zip' => '',
    'permanent_house' => '',
    'permanent_street' => '',
    'permanent_barangay' => '',
    'permanent_city' => '',
    'permanent_province' => '',
    'permanent_zip' => '',
    'permanent_same_as_current' => '0',
    'contact_number' => '',
    'current_address' => '',
    'permanent_address' => '',

    'mother_name' => '',
    'mother_occupation' => '',
    'mother_contact_number' => '',
    'mother_email' => '',
    'mother_house' => '',
    'mother_street' => '',
    'mother_barangay' => '',
    'mother_city' => '',
    'mother_province' => '',
    'mother_zip' => '',
    'mother_same_as_current' => '0',
    'mother_address' => '',

    'father_name' => '',
    'father_occupation' => '',
    'father_contact_number' => '',
    'father_email' => '',
    'father_house' => '',
    'father_street' => '',
    'father_barangay' => '',
    'father_city' => '',
    'father_province' => '',
    'father_zip' => '',
    'father_same_as_current' => '0',
    'father_address' => '',

    'guardian_name' => '',
    'guardian_relationship' => '',
    'guardian_contact_number' => '',
    'guardian_email' => '',
    'guardian_occupation' => '',
    'guardian_house' => '',
    'guardian_street' => '',
    'guardian_barangay' => '',
    'guardian_city' => '',
    'guardian_province' => '',
    'guardian_zip' => '',
    'guardian_same_as_current' => '0',
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

    // Read checkbox values safely (unchecked boxes are not submitted).
    foreach (['permanent_same_as_current', 'mother_same_as_current', 'father_same_as_current', 'guardian_same_as_current'] as $checkboxField) {
        $values[$checkboxField] = isset($_POST[$checkboxField]) && $_POST[$checkboxField] === '1' ? '1' : '0';
    }

    // Combine structured address parts into the existing database TEXT columns.
    $buildAddress = static function (array $data, string $prefix): string {
        $parts = [];
        foreach (['house', 'street', 'barangay', 'city', 'province', 'zip'] as $part) {
            $value = trim($data[$prefix . '_' . $part] ?? '');
            if ($value !== '') {
                $parts[] = $value;
            }
        }
        return implode(', ', $parts);
    };

    $values['current_address'] = $buildAddress($values, 'current');
    $values['permanent_address'] = $values['permanent_same_as_current'] === '1'
        ? $values['current_address']
        : $buildAddress($values, 'permanent');

    foreach (['mother', 'father', 'guardian'] as $personPrefix) {
        $values[$personPrefix . '_address'] = $values[$personPrefix . '_same_as_current'] === '1'
            ? $values['current_address']
            : $buildAddress($values, $personPrefix);
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

    // Validate civil status using the categories shown in DepEd learner forms.
    $allowedCivilStatus = [
        '',
        'Single',
        'Married',
        'Separated',
        'Widowed',
        'Solo Parent',
        'Prefer not to say'
    ];

    if (!in_array($values['civil_status'], $allowedCivilStatus, true)) {
        $errors[] = 'Select a valid civil status.';
    }

    // Validate nationality dropdown and optional custom entry.
    $allowedNationalities = [
        '',
        'Filipino',
        'American',
        'Australian',
        'British',
        'Canadian',
        'Chinese',
        'Indian',
        'Japanese',
        'Korean',
        'Other'
    ];

    if (!in_array($values['nationality_choice'], $allowedNationalities, true)) {
        $errors[] = 'Select a valid nationality.';
    } elseif ($values['nationality_choice'] === 'Other') {
        if ($values['nationality_other'] === '' || strlen($values['nationality_other']) > 80) {
            $errors[] = 'Please specify your nationality (maximum 80 characters).';
        } else {
            $values['nationality'] = $values['nationality_other'];
        }
    } else {
        $values['nationality'] = $values['nationality_choice'];
    }

    // Validate religion dropdown. This field remains optional.
    $allowedReligions = [
        '',
        'Roman Catholic',
        'Islam',
        'Iglesia ni Cristo',
        'Evangelical / Protestant',
        'Aglipayan (Iglesia Filipina Independiente)',
        'Seventh-day Adventist',
        "Jehovah's Witnesses",
        'Buddhist',
        'Hindu',
        'Other Christian denomination',
        'Other',
        'No religion',
        'Prefer not to say'
    ];

    if (!in_array($values['religion_choice'], $allowedReligions, true)) {
        $errors[] = 'Select a valid religion option.';
    } elseif ($values['religion_choice'] === 'Other') {
        if ($values['religion_other'] === '' || strlen($values['religion_other']) > 100) {
            $errors[] = 'Please specify your religion (maximum 100 characters).';
        } else {
            $values['religion'] = $values['religion_other'];
        }
    } else {
        $values['religion'] = $values['religion_choice'];
    }

    // Validate Indigenous Peoples and disability information.
    if (!in_array($values['indigenous_people_status'], ['', 'Yes', 'No'], true)) {
        $errors[] = 'Select a valid Indigenous Peoples membership option.';
    }
    if ($values['indigenous_people_status'] === 'Yes' && $values['indigenous_people_group'] === '') {
        $errors[] = 'Please specify the Indigenous Peoples community.';
    }
    if (strlen($values['indigenous_people_group']) > 150) {
        $errors[] = 'Indigenous Peoples community must not exceed 150 characters.';
    }

    if (!in_array($values['is_pwd'], ['', 'Yes', 'No'], true)) {
        $errors[] = 'Select a valid PWD option.';
    }
    if ($values['is_pwd'] === 'Yes' && $values['disability_details'] === '') {
        $errors[] = 'Please specify the disability information.';
    }
    if (strlen($values['disability_details']) > 255) {
        $errors[] = 'Disability details must not exceed 255 characters.';
    }

    // Validate structured address field lengths.
    foreach ($values as $field => $value) {
        if (preg_match('/_(house|street|barangay|city|province)$/', $field) && strlen($value) > 150) {
            $errors[] = 'Address fields must not exceed 150 characters.';
            break;
        }
        if (str_ends_with($field, '_zip') && $value !== '' && !preg_match('/^[0-9]{4,10}$/', $value)) {
            $errors[] = 'Enter a valid ZIP code using 4 to 10 digits.';
        }
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
                    indigenous_people_status,
                    indigenous_people_group,
                    is_pwd,
                    disability_details,
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
                    :indigenous_people_status,
                    :indigenous_people_group,
                    :is_pwd,
                    :disability_details,
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
                'indigenous_people_status' => $values['indigenous_people_status'] ?: null,
                'indigenous_people_group' => $values['indigenous_people_status'] === 'Yes' ? ($values['indigenous_people_group'] ?: null) : null,
                'is_pwd' => $values['is_pwd'] ?: null,
                'disability_details' => $values['is_pwd'] === 'Yes' ? ($values['disability_details'] ?: null) : null,
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
    .registration-form-grid .address-section {
        grid-column: 1 / -1;
        padding: 16px;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        background: rgba(255, 255, 255, 0.72);
    }

    .registration-form-grid .address-section h3 {
        margin: 0 0 12px;
        color: #5b1830;
        font-size: 1rem;
    }

    .registration-form-grid .address-fields {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 14px 18px;
    }

    .registration-form-grid .address-fields label {
        display: flex;
        flex-direction: column;
        gap: 6px;
    }

    .registration-form-grid .checkbox-inline {
        display: flex;
        flex-direction: row;
        align-items: center;
        gap: 8px;
        margin-bottom: 12px;
        font-weight: 500;
    }

    .registration-form-grid .checkbox-inline input {
        width: auto;
        margin: 0;
    }

    .registration-form-grid [hidden] {
        display: none !important;
    }

    @media (max-width: 640px) {
        .registration-form-grid .address-fields {
            grid-template-columns: 1fr;
        }
    }

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
</style>

<section class="form-card form-card-wide">

    <?php if (!$success): ?>
        <p class="eyebrow">Create an account</p>

        <h1>Student Registration</h1>

        <p class="muted">
            Create your student account and provide your personal,
            contact, parent, and guardian information.
        </p>
    <?php endif; ?>

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
                <select name="civil_status">
                    <option value="">Select civil status</option>
                    <option value="Single" <?= $values['civil_status'] === 'Single' ? 'selected' : '' ?>>Single</option>
                    <option value="Married" <?= $values['civil_status'] === 'Married' ? 'selected' : '' ?>>Married</option>
                    <option value="Separated" <?= $values['civil_status'] === 'Separated' ? 'selected' : '' ?>>Separated</option>
                    <option value="Widowed" <?= $values['civil_status'] === 'Widowed' ? 'selected' : '' ?>>Widowed</option>
                    <option value="Solo Parent" <?= $values['civil_status'] === 'Solo Parent' ? 'selected' : '' ?>>Solo Parent</option>
                    <option value="Prefer not to say" <?= $values['civil_status'] === 'Prefer not to say' ? 'selected' : '' ?>>Prefer not to say</option>
                </select>
            </label>

            <label>
                Nationality
                <select name="nationality_choice" id="nationalityChoice">
                    <option value="">Select nationality</option>
                    <option value="Filipino" <?= $values['nationality_choice'] === 'Filipino' ? 'selected' : '' ?>>Filipino</option>
                    <option value="American" <?= $values['nationality_choice'] === 'American' ? 'selected' : '' ?>>American</option>
                    <option value="Australian" <?= $values['nationality_choice'] === 'Australian' ? 'selected' : '' ?>>Australian</option>
                    <option value="British" <?= $values['nationality_choice'] === 'British' ? 'selected' : '' ?>>British</option>
                    <option value="Canadian" <?= $values['nationality_choice'] === 'Canadian' ? 'selected' : '' ?>>Canadian</option>
                    <option value="Chinese" <?= $values['nationality_choice'] === 'Chinese' ? 'selected' : '' ?>>Chinese</option>
                    <option value="Indian" <?= $values['nationality_choice'] === 'Indian' ? 'selected' : '' ?>>Indian</option>
                    <option value="Japanese" <?= $values['nationality_choice'] === 'Japanese' ? 'selected' : '' ?>>Japanese</option>
                    <option value="Korean" <?= $values['nationality_choice'] === 'Korean' ? 'selected' : '' ?>>Korean</option>
                    <option value="Other" <?= $values['nationality_choice'] === 'Other' ? 'selected' : '' ?>>Other (please specify)</option>
                </select>
                <input
                    type="text"
                    name="nationality_other"
                    id="nationalityOther"
                    maxlength="80"
                    placeholder="Please specify your nationality"
                    value="<?= e($values['nationality_other']) ?>"
                    <?= $values['nationality_choice'] === 'Other' ? 'required' : 'hidden' ?>
                >
            </label>

            <label>
                <span class="inline-label"><span>Religion</span><span class="muted">(Optional)</span></span>
                <select name="religion_choice" id="religionChoice">
                    <option value="">Select religion</option>
                    <option value="Roman Catholic" <?= $values['religion_choice'] === 'Roman Catholic' ? 'selected' : '' ?>>Roman Catholic</option>
                    <option value="Islam" <?= $values['religion_choice'] === 'Islam' ? 'selected' : '' ?>>Islam</option>
                    <option value="Iglesia ni Cristo" <?= $values['religion_choice'] === 'Iglesia ni Cristo' ? 'selected' : '' ?>>Iglesia ni Cristo</option>
                    <option value="Evangelical / Protestant" <?= $values['religion_choice'] === 'Evangelical / Protestant' ? 'selected' : '' ?>>Evangelical / Protestant</option>
                    <option value="Aglipayan (Iglesia Filipina Independiente)" <?= $values['religion_choice'] === 'Aglipayan (Iglesia Filipina Independiente)' ? 'selected' : '' ?>>Aglipayan (Iglesia Filipina Independiente)</option>
                    <option value="Seventh-day Adventist" <?= $values['religion_choice'] === 'Seventh-day Adventist' ? 'selected' : '' ?>>Seventh-day Adventist</option>
                    <option value="Jehovah's Witnesses" <?= $values['religion_choice'] === "Jehovah's Witnesses" ? 'selected' : '' ?>>Jehovah's Witnesses</option>
                    <option value="Buddhist" <?= $values['religion_choice'] === 'Buddhist' ? 'selected' : '' ?>>Buddhist</option>
                    <option value="Hindu" <?= $values['religion_choice'] === 'Hindu' ? 'selected' : '' ?>>Hindu</option>
                    <option value="Other Christian denomination" <?= $values['religion_choice'] === 'Other Christian denomination' ? 'selected' : '' ?>>Other Christian denomination</option>
                    <option value="Other" <?= $values['religion_choice'] === 'Other' ? 'selected' : '' ?>>Other (please specify)</option>
                    <option value="No religion" <?= $values['religion_choice'] === 'No religion' ? 'selected' : '' ?>>No religion</option>
                    <option value="Prefer not to say" <?= $values['religion_choice'] === 'Prefer not to say' ? 'selected' : '' ?>>Prefer not to say</option>
                </select>
                <input
                    type="text"
                    name="religion_other"
                    id="religionOther"
                    maxlength="100"
                    placeholder="Please specify your religion"
                    value="<?= e($values['religion_other']) ?>"
                    <?= $values['religion_choice'] === 'Other' ? 'required' : 'hidden' ?>
                >
            </label>

            <label>
                Are you a member of an Indigenous Peoples (IP) Community?
                <select name="indigenous_people_status" id="indigenousPeopleStatus">
                    <option value="">Select an option</option>
                    <option value="Yes" <?= $values['indigenous_people_status'] === 'Yes' ? 'selected' : '' ?>>Yes</option>
                    <option value="No" <?= $values['indigenous_people_status'] === 'No' ? 'selected' : '' ?>>No</option>
                </select>
            </label>
            <label id="indigenousPeopleGroupWrap" hidden>
                If yes, please specify your IP community
                <input type="text" name="indigenous_people_group" maxlength="150" value="<?= e($values['indigenous_people_group']) ?>">
            </label>

            <label>
                Are you a person with disability (PWD)?
                <select name="is_pwd" id="pwdStatus">
                    <option value="">Select an option</option>
                    <option value="Yes" <?= $values['is_pwd'] === 'Yes' ? 'selected' : '' ?>>Yes</option>
                    <option value="No" <?= $values['is_pwd'] === 'No' ? 'selected' : '' ?>>No</option>
                </select>
            </label>
            <label id="disabilityDetailsWrap" hidden>
                If yes, please specify your disability
                <input type="text" name="disability_details" maxlength="255" value="<?= e($values['disability_details']) ?>">
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

            <div class="address-section">
                <h3>Current Address</h3>
                <div class="address-fields">
                    <label>House / Building No.<input type="text" name="current_house" maxlength="150" value="<?= e($values['current_house']) ?>"></label>
                    <label>Street / Sitio / Purok<input type="text" name="current_street" maxlength="150" value="<?= e($values['current_street']) ?>"></label>
                    <label>Barangay<input type="text" name="current_barangay" maxlength="150" value="<?= e($values['current_barangay']) ?>"></label>
                    <label>Municipality / City<input type="text" name="current_city" maxlength="150" value="<?= e($values['current_city']) ?>"></label>
                    <label>Province<input type="text" name="current_province" maxlength="150" value="<?= e($values['current_province']) ?>"></label>
                    <label>ZIP Code<input type="text" name="current_zip" inputmode="numeric" maxlength="10" value="<?= e($values['current_zip']) ?>"></label>
                </div>
            </div>
            <div class="address-section">
                <h3>Permanent Address</h3>
                <label class="checkbox-inline"><input type="checkbox" name="permanent_same_as_current" value="1" <?= $values['permanent_same_as_current'] === '1' ? 'checked' : '' ?>> Same as current address</label>
                <div class="address-fields" data-address-fields="permanent">
                    <label>House / Building No.<input type="text" name="permanent_house" maxlength="150" value="<?= e($values['permanent_house']) ?>"></label>
                    <label>Street / Sitio / Purok<input type="text" name="permanent_street" maxlength="150" value="<?= e($values['permanent_street']) ?>"></label>
                    <label>Barangay<input type="text" name="permanent_barangay" maxlength="150" value="<?= e($values['permanent_barangay']) ?>"></label>
                    <label>Municipality / City<input type="text" name="permanent_city" maxlength="150" value="<?= e($values['permanent_city']) ?>"></label>
                    <label>Province<input type="text" name="permanent_province" maxlength="150" value="<?= e($values['permanent_province']) ?>"></label>
                    <label>ZIP Code<input type="text" name="permanent_zip" inputmode="numeric" maxlength="10" value="<?= e($values['permanent_zip']) ?>"></label>
                </div>
            </div>

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

            <div class="address-section">
                <h3>Mother's Address</h3>
                <label class="checkbox-inline"><input type="checkbox" name="mother_same_as_current" value="1" <?= $values['mother_same_as_current'] === '1' ? 'checked' : '' ?>> Same as student's current address</label>
                <div class="address-fields" data-address-fields="mother">
                    <label>House / Building No.<input type="text" name="mother_house" maxlength="150" value="<?= e($values['mother_house']) ?>"></label>
                    <label>Street / Sitio / Purok<input type="text" name="mother_street" maxlength="150" value="<?= e($values['mother_street']) ?>"></label>
                    <label>Barangay<input type="text" name="mother_barangay" maxlength="150" value="<?= e($values['mother_barangay']) ?>"></label>
                    <label>Municipality / City<input type="text" name="mother_city" maxlength="150" value="<?= e($values['mother_city']) ?>"></label>
                    <label>Province<input type="text" name="mother_province" maxlength="150" value="<?= e($values['mother_province']) ?>"></label>
                    <label>ZIP Code<input type="text" name="mother_zip" inputmode="numeric" maxlength="10" value="<?= e($values['mother_zip']) ?>"></label>
                </div>
            </div>

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

            <div class="address-section">
                <h3>Father's Address</h3>
                <label class="checkbox-inline"><input type="checkbox" name="father_same_as_current" value="1" <?= $values['father_same_as_current'] === '1' ? 'checked' : '' ?>> Same as student's current address</label>
                <div class="address-fields" data-address-fields="father">
                    <label>House / Building No.<input type="text" name="father_house" maxlength="150" value="<?= e($values['father_house']) ?>"></label>
                    <label>Street / Sitio / Purok<input type="text" name="father_street" maxlength="150" value="<?= e($values['father_street']) ?>"></label>
                    <label>Barangay<input type="text" name="father_barangay" maxlength="150" value="<?= e($values['father_barangay']) ?>"></label>
                    <label>Municipality / City<input type="text" name="father_city" maxlength="150" value="<?= e($values['father_city']) ?>"></label>
                    <label>Province<input type="text" name="father_province" maxlength="150" value="<?= e($values['father_province']) ?>"></label>
                    <label>ZIP Code<input type="text" name="father_zip" inputmode="numeric" maxlength="10" value="<?= e($values['father_zip']) ?>"></label>
                </div>
            </div>

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

            <div class="address-section">
                <h3>Guardian's Address</h3>
                <label class="checkbox-inline"><input type="checkbox" name="guardian_same_as_current" value="1" <?= $values['guardian_same_as_current'] === '1' ? 'checked' : '' ?>> Same as student's current address</label>
                <div class="address-fields" data-address-fields="guardian">
                    <label>House / Building No.<input type="text" name="guardian_house" maxlength="150" value="<?= e($values['guardian_house']) ?>"></label>
                    <label>Street / Sitio / Purok<input type="text" name="guardian_street" maxlength="150" value="<?= e($values['guardian_street']) ?>"></label>
                    <label>Barangay<input type="text" name="guardian_barangay" maxlength="150" value="<?= e($values['guardian_barangay']) ?>"></label>
                    <label>Municipality / City<input type="text" name="guardian_city" maxlength="150" value="<?= e($values['guardian_city']) ?>"></label>
                    <label>Province<input type="text" name="guardian_province" maxlength="150" value="<?= e($values['guardian_province']) ?>"></label>
                    <label>ZIP Code<input type="text" name="guardian_zip" inputmode="numeric" maxlength="10" value="<?= e($values['guardian_zip']) ?>"></label>
                </div>
            </div>

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





<script>
document.addEventListener('DOMContentLoaded', function () {
    function toggleConditional(selectId, wrapperId, requiredInputSelector) {
        const select = document.getElementById(selectId);
        const wrapper = document.getElementById(wrapperId);
        if (!select || !wrapper) return;

        const input = wrapper.querySelector(requiredInputSelector);
        function update() {
            const show = select.value === 'Yes';
            wrapper.hidden = !show;
            if (input) {
                input.required = show;
                if (!show) input.value = '';
            }
        }
        select.addEventListener('change', update);
        update();
    }

    toggleConditional('indigenousPeopleStatus', 'indigenousPeopleGroupWrap', 'input');
    toggleConditional('pwdStatus', 'disabilityDetailsWrap', 'input');

    document.querySelectorAll('input[name$="_same_as_current"]').forEach(function (checkbox) {
        function updateAddressFields() {
            const prefix = checkbox.name.replace('_same_as_current', '');
            const fields = document.querySelector('[data-address-fields="' + prefix + '"]');
            if (fields) {
                fields.hidden = checkbox.checked;
                fields.querySelectorAll('input').forEach(function (input) {
                    input.disabled = checkbox.checked;
                });
            }
        }
        checkbox.addEventListener('change', updateAddressFields);
        updateAddressFields();
    });
});
</script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    function toggleOtherField(selectId, inputId) {
        const select = document.getElementById(selectId);
        const input = document.getElementById(inputId);

        if (!select || !input) return;

        function updateField() {
            const showField = select.value === 'Other';
            input.hidden = !showField;
            input.required = showField;
            if (!showField) input.value = '';
        }

        select.addEventListener('change', updateField);
        updateField();
    }

    toggleOtherField('nationalityChoice', 'nationalityOther');
    toggleOtherField('religionChoice', 'religionOther');
});
</script>

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

document.addEventListener('DOMContentLoaded', function () {
    const overlay = document.getElementById('registrationReminder');
    const closeButton = document.getElementById('closeRegistrationReminder');
    const confirmButton = document.getElementById('confirmRegistrationReminder');

    if (!overlay || !closeButton || !confirmButton) {
        return;
    }

    // Updated key allows the reminder to appear again after this correction.
    const storageKey = 'bcnhsRegistrationReminderDismissed_v2';

    function closeReminder() {
        overlay.classList.remove('is-visible');
        document.body.classList.remove('reminder-modal-open');

        try {
            sessionStorage.setItem(storageKey, '1');
        } catch (error) {
            // Modal can still close if browser storage is unavailable.
        }
    }

    let alreadyDismissed = false;

    try {
        alreadyDismissed = sessionStorage.getItem(storageKey) === '1';
    } catch (error) {
        alreadyDismissed = false;
    }

    if (!alreadyDismissed) {
        document.body.classList.add('reminder-modal-open');

        // Allow the browser to apply the hidden state before animation.
        requestAnimationFrame(function () {
            overlay.classList.add('is-visible');
        });
    }

    closeButton.addEventListener('click', closeReminder);
    confirmButton.addEventListener('click', closeReminder);

    overlay.addEventListener('click', function (event) {
        if (event.target === overlay) {
            closeReminder();
        }
    });

    document.addEventListener('keydown', function (event) {
        if (
            event.key === 'Escape' &&
            overlay.classList.contains('is-visible')
        ) {
            closeReminder();
        }
    });
});

</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
