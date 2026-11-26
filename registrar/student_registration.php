
<?php
require_once __DIR__ . '/../includes/bootstrap.php';

require_role('School Registrar');

$errors = [];
$success = false;
$generatedUsername = '';
$hasAdditionalGuardian = false;

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
    'current_province' => '',
    'current_city' => '',
    'current_zip' => '',
    'permanent_house' => '',
    'permanent_street' => '',
    'permanent_barangay' => '',
    'permanent_province' => '',
    'permanent_city' => '',
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
    'mother_province' => '',
    'mother_city' => '',
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
    'father_province' => '',
    'father_city' => '',
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
    'guardian_province' => '',
    'guardian_city' => '',
    'guardian_zip' => '',
    'guardian_same_as_current' => '0',
    'guardian_address' => ''
];

$password = '';
$confirmPassword = '';

// Philippine Statistics Authority PSGC: https://psa.gov.ph/classification/psgc
$addressLocations = json_decode(
    file_get_contents(__DIR__ . '/../assets/data/registration-psgc.json'),
    true
);

// Keep the official CAR grouping and offer Baguio under Benguet for address lookup.
$addressLocations['1430300000']['lookup_area'] = 'Benguet';
$addressAreas = [];
foreach ($addressLocations as $cityCode => $location) {
    $addressAreas[$location['area']][$cityCode] = $location;
    if (isset($location['lookup_area'])) {
        $addressAreas[$location['lookup_area']][$cityCode] = $location;
    }
}
ksort($addressAreas, SORT_NATURAL | SORT_FLAG_CASE);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $hasAdditionalGuardian = ($_POST['has_additional_guardian'] ?? '') === '1';

    // Collect form values safely.
    foreach ($values as $field => $oldValue) {
        if (!$hasAdditionalGuardian && strpos($field, 'guardian_') === 0) {
            continue;
        }
        $submittedValue = $_POST[$field] ?? '';

        if (!is_string($submittedValue)) {
            $errors[] = 'Invalid registration information submitted.';
            continue;
        }

        $values[$field] = trim($submittedValue);
    }

    // Read checkbox values safely (unchecked boxes are not submitted).
    foreach (['permanent_same_as_current', 'mother_same_as_current', 'father_same_as_current', 'guardian_same_as_current'] as $checkboxField) {
        if ($checkboxField === 'guardian_same_as_current' && !$hasAdditionalGuardian) {
            continue;
        }
        $values[$checkboxField] = isset($_POST[$checkboxField]) && $_POST[$checkboxField] === '1' ? '1' : '0';
    }

    // Resolve submitted PSGC codes to names before formatting addresses.
    $addressValues = $values;
    $addressLabels = [
        'current' => 'current address',
        'permanent' => 'permanent address',
        'mother' => "mother's address",
        'father' => "father's address",
        'guardian' => "guardian's address"
    ];
    foreach ($addressLabels as $prefix => $addressLabel) {
        $areaName = $values[$prefix . '_province'];
        $cityCode = $values[$prefix . '_city'];
        $barangayCode = $values[$prefix . '_barangay'];
        $addressValues[$prefix . '_city'] = '';
        $addressValues[$prefix . '_barangay'] = '';
        $addressValues[$prefix . '_province'] = '';

        if (($values[$prefix . '_same_as_current'] ?? '0') === '1') {
            continue;
        }

        $city = $addressAreas[$areaName][$cityCode] ?? null;
        if ($areaName !== '' && !isset($addressAreas[$areaName])) {
            $errors[] = 'Select a valid province or region for the ' . $addressLabel . '.';
        }
        if ($cityCode !== '' && $city === null) {
            $errors[] = 'Select a city or municipality within the selected province or region for the ' . $addressLabel . '.';
        }
        if ($barangayCode !== '' && !isset($city['barangays'][$barangayCode])) {
            $errors[] = 'Select a barangay within the selected city or municipality for the ' . $addressLabel . '.';
        }

        if (isset($addressAreas[$areaName])) {
            $addressValues[$prefix . '_province'] = $areaName;
        }
        if ($city !== null) {
            $addressValues[$prefix . '_city'] = $city['name'];
            $addressValues[$prefix . '_province'] = $city['area'];
            $addressValues[$prefix . '_barangay'] = $city['barangays'][$barangayCode] ?? '';
        }
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

    $values['current_address'] = $buildAddress($addressValues, 'current');
    $values['permanent_address'] = $values['permanent_same_as_current'] === '1'
        ? $values['current_address']
        : $buildAddress($addressValues, 'permanent');

    foreach (['mother', 'father', 'guardian'] as $personPrefix) {
        $values[$personPrefix . '_address'] = $values[$personPrefix . '_same_as_current'] === '1'
            ? $values['current_address']
            : $buildAddress($addressValues, $personPrefix);
    }

    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (!is_string($password) || !is_string($confirmPassword)) {
        $password = '';
        $confirmPassword = '';
        $errors[] = 'Invalid password information submitted.';
    }

    // CSRF validation.
    $submittedToken = $_POST['csrf_token'] ?? null;
    if (!is_string($submittedToken) || !verify_csrf_token($submittedToken)) {
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

    // DepEd LRNs contain exactly 12 digits.
    if ($values['lrn'] === '') {
        $errors[] = 'Learner Reference Number (LRN) is required.';
    } elseif (!preg_match('/^[0-9]{12}$/', $values['lrn'])) {
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

    if ($hasAdditionalGuardian && $values['guardian_name'] === '') {
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

    // Check whether the LRN is already registered.
    if (!$errors) {
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
                'lrn' => $values['lrn'],
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

            // Save an additional guardian when the section is selected.
            if ($hasAdditionalGuardian) {
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
require_once __DIR__ . '/../includes/header.php';
?>



<section class="form-card form-card-wide">

    <?php if (!$success): ?>

        <h1>Student Registration</h1>

        <p class="muted">
            Create a portal account for the student.
        </p>
    <?php endif; ?>

    <?php if ($success): ?>

        <div class="alert alert-success">
            The student account has been created successfully.
            <br>
            The student's username is:
            <strong><?= e($generatedUsername) ?></strong>
            <br>
            Give this username to the student for their first login.
            <br>
            <a href="<?= BASE_URL ?>/registrar/student_registration.php">Register another student</a>.
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

            <p class="registration-required-note"><span class="registration-required">*</span> Required fields</p>

            <h2>1. Student Personal Information</h2>

            <label class="registration-lrn">
                <span><span class="registration-required" aria-hidden="true">*</span> Learner Reference Number (LRN)</span>
                <input
                    type="text"
                    name="lrn"
                    inputmode="numeric"
                    maxlength="12"
                    pattern="[0-9]{12}"
                    title="LRN must contain exactly 12 digits."
                    oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 12)"
                    value="<?= e($values['lrn']) ?>"
                    required
                >
            </label>

            <div class="registration-name-fields">
                <label>
                    <span><span class="registration-required" aria-hidden="true">*</span> First Name</span>
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
                    <span><span class="registration-required" aria-hidden="true">*</span> Last Name</span>
                    <input
                        type="text"
                        name="last_name"
                        maxlength="100"
                        autocomplete="family-name"
                        value="<?= e($values['last_name']) ?>"
                        required
                    >
                </label>
            </div>

            <label>
                <span><span class="registration-required" aria-hidden="true">*</span> Email Address</span>
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

            <div class="registration-field-group">
                <label for="nationalityChoice">Nationality</label>
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
                <label id="nationalityOtherWrap" for="nationalityOther" <?= $values['nationality_choice'] !== 'Other' ? 'hidden' : '' ?>>
                    <span><span class="registration-required" aria-hidden="true">*</span> Specify your nationality</span>
                    <input
                        type="text"
                        name="nationality_other"
                        id="nationalityOther"
                        maxlength="80"
                        placeholder="Please specify your nationality"
                        value="<?= e($values['nationality_other']) ?>"
                        <?= $values['nationality_choice'] === 'Other' ? 'required' : '' ?>
                    >
                </label>
            </div>

            <div class="registration-field-group">
                <label for="religionChoice"><span class="inline-label"><span>Religion</span><span class="muted">(Optional)</span></span></label>
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
                <label id="religionOtherWrap" for="religionOther" <?= $values['religion_choice'] !== 'Other' ? 'hidden' : '' ?>>
                    <span><span class="registration-required" aria-hidden="true">*</span> Specify your religion</span>
                    <input
                        type="text"
                        name="religion_other"
                        id="religionOther"
                        maxlength="100"
                        placeholder="Please specify your religion"
                        value="<?= e($values['religion_other']) ?>"
                        <?= $values['religion_choice'] === 'Other' ? 'required' : '' ?>
                    >
                </label>
            </div>

            <label>
                Are you a member of an Indigenous Peoples (IP) Community?
                <select name="indigenous_people_status" id="indigenousPeopleStatus">
                    <option value="">Select an option</option>
                    <option value="Yes" <?= $values['indigenous_people_status'] === 'Yes' ? 'selected' : '' ?>>Yes</option>
                    <option value="No" <?= $values['indigenous_people_status'] === 'No' ? 'selected' : '' ?>>No</option>
                </select>
            </label>
            <label id="indigenousPeopleGroupWrap" hidden>
                <span><span class="registration-required" aria-hidden="true">*</span> If yes, please specify your IP community</span>
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
                <span><span class="registration-required" aria-hidden="true">*</span> If yes, please specify your disability</span>
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
                <?php $addressPrefix = 'current'; ?>
                <?php require __DIR__ . '/../includes/registration_address_fields.php'; ?>
            </div>
            <div class="address-section">
                <h3>Permanent Address</h3>
                <label class="checkbox-inline"><input type="checkbox" name="permanent_same_as_current" value="1" <?= $values['permanent_same_as_current'] === '1' ? 'checked' : '' ?>> Same as current address</label>
                <?php $addressPrefix = 'permanent'; ?>
                <?php require __DIR__ . '/../includes/registration_address_fields.php'; ?>
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
                <?php $addressPrefix = 'mother'; ?>
                <?php require __DIR__ . '/../includes/registration_address_fields.php'; ?>
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
                <?php $addressPrefix = 'father'; ?>
                <?php require __DIR__ . '/../includes/registration_address_fields.php'; ?>
            </div>

            <h2>5. Additional Guardian Information (Optional)</h2>

            <label class="checkbox-inline registration-guardian-toggle">
                <input type="checkbox" name="has_additional_guardian" id="hasAdditionalGuardian" value="1"
                       aria-controls="additionalGuardianFields" <?= $hasAdditionalGuardian ? 'checked' : '' ?>>
                Add an additional guardian
            </label>

            <fieldset class="registration-guardian-fields" id="additionalGuardianFields"
                      aria-label="Additional guardian details" <?= $hasAdditionalGuardian ? '' : 'hidden disabled' ?>>
                <label>
                    <span><span class="registration-required" aria-hidden="true">*</span> Guardian's Full Name</span>
                    <input
                        type="text"
                        name="guardian_name"
                        maxlength="150"
                        value="<?= e($values['guardian_name']) ?>"
                        required
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
                    <?php $addressPrefix = 'guardian'; ?>
                    <?php require __DIR__ . '/../includes/registration_address_fields.php'; ?>
                </div>
            </fieldset>

            <h2>Account Security</h2>

            <label>
                <span><span class="registration-required" aria-hidden="true">*</span> Password</span>
                <input
                    type="password"
                    name="password"
                    minlength="8"
                    autocomplete="new-password"
                    required
                >
            </label>

            <label>
                <span><span class="registration-required" aria-hidden="true">*</span> Confirm Password</span>
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
        <script type="application/json" id="registrationAddressLocations">
            <?= json_encode($addressLocations, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>
        </script>

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

    const guardianToggle = document.getElementById('hasAdditionalGuardian');
    const guardianFields = document.getElementById('additionalGuardianFields');
    if (guardianToggle && guardianFields) {
        function updateGuardianFields() {
            guardianFields.hidden = !guardianToggle.checked;
            guardianFields.disabled = !guardianToggle.checked;
        }
        guardianToggle.addEventListener('change', updateGuardianFields);
        updateGuardianFields();
    }

    let registrationLocations;
    function getRegistrationLocations() {
        if (!registrationLocations) {
            const locationsElement = document.getElementById('registrationAddressLocations');
            registrationLocations = JSON.parse(locationsElement.textContent);
        }
        return registrationLocations;
    }

    document.querySelectorAll('.address-fields').forEach(function (fields) {
        const provinceSelect = fields.querySelector('[data-address-area]');
        const citySelect = fields.querySelector('[data-address-city]');
        const barangaySelect = fields.querySelector('[data-address-barangay]');

        function updateBarangays() {
            const city = getRegistrationLocations()[citySelect.value];
            barangaySelect.replaceChildren(new Option(
                city ? 'Select barangay' : 'Choose a city or municipality first',
                ''
            ));
            if (city) {
                Object.entries(city.barangays).forEach(function ([code, name]) {
                    barangaySelect.add(new Option(name, code));
                });
            }
        }

        provinceSelect.addEventListener('change', function () {
            const province = provinceSelect.value;
            citySelect.replaceChildren(new Option(
                province ? 'Select city or municipality' : 'Choose a province or region first',
                ''
            ));
            if (province) {
                const cities = Object.entries(getRegistrationLocations())
                    .filter(function ([code, city]) { return city.area === province || city.lookup_area === province; })
                    .sort(function (first, second) { return first[1].name.localeCompare(second[1].name); });
                cities.forEach(function ([code, city]) {
                    citySelect.add(new Option(city.name, code));
                });
            }
            updateBarangays();
        });
        citySelect.addEventListener('change', updateBarangays);
    });

    document.querySelectorAll('input[name$="_same_as_current"]').forEach(function (checkbox) {
        function updateAddressFields() {
            const prefix = checkbox.name.replace('_same_as_current', '');
            const fields = document.querySelector('[data-address-fields="' + prefix + '"]');
            if (fields) {
                fields.hidden = checkbox.checked;
                fields.querySelectorAll('input, select').forEach(function (input) {
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

        const wrapper = document.getElementById(inputId + 'Wrap');
        if (!select || !input || !wrapper) return;

        function updateField() {
            const showField = select.value === 'Other';
            wrapper.hidden = !showField;
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

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
