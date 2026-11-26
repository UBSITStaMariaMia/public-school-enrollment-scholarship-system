
<?php
require_once __DIR__ . '/../includes/bootstrap.php';

require_role('Student');

$userId = (int) current_user()['user_id'];
$errors = [];
$success = '';

/* Get student information */
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

/* Get parent information */
$parentQuery = $pdo->prepare(
    'SELECT *
     FROM student_parent_information
     WHERE student_id = :student_id
     LIMIT 1'
);

$parentQuery->execute(['student_id' => $studentId]);
$parents = $parentQuery->fetch() ?: [];

/* Get primary guardian information */
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

/* Convert Philippine phone numbers to 10-digit format */
function phone_digits_for_display($phone): string
{
    $digits = preg_replace('/\D+/', '', (string) $phone);

    if (strlen($digits) === 12 && str_starts_with($digits, '63')) {
        $digits = substr($digits, 2);
    } elseif (strlen($digits) === 11 && str_starts_with($digits, '0')) {
        $digits = substr($digits, 1);
    }

    return $digits;
}

/* Prepare editable contact information */
$values = [
    'email' => $student['email'] ?? '',
    'contact_number' => phone_digits_for_display($student['contact_number'] ?? ''),

    'mother_email' => $parents['mother_email'] ?? '',
    'mother_contact_number' => phone_digits_for_display($parents['mother_contact_number'] ?? ''),

    'father_email' => $parents['father_email'] ?? '',
    'father_contact_number' => phone_digits_for_display($parents['father_contact_number'] ?? ''),

    'guardian_email' => $guardian['email'] ?? '',
    'guardian_contact_number' => phone_digits_for_display($guardian['contact_number'] ?? '')
];

$isEditing = isset($_GET['edit']) && $_GET['edit'] === '1';

/* Handle profile update */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $isEditing = true;

    $emailFields = [
        'email' => 'Student',
        'mother_email' => 'Mother',
        'father_email' => 'Father',
        'guardian_email' => 'Guardian'
    ];

    $phoneFields = [
        'contact_number' => 'Student',
        'mother_contact_number' => 'Mother',
        'father_contact_number' => 'Father',
        'guardian_contact_number' => 'Guardian'
    ];

    foreach ($emailFields as $field => $person) {
        $values[$field] = trim($_POST[$field] ?? '');
    }

    foreach ($phoneFields as $field => $person) {
        $values[$field] = trim($_POST[$field] ?? '');
    }

    /* CSRF validation */
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Your session expired. Please refresh the page and try again.';
    }

    /* Validate all four Gmail addresses */
    foreach ($emailFields as $field => $person) {

        $email = $values[$field];

        /* Student email is required; family emails are optional. */
        if ($field === 'email' && $email === '') {
            $errors[] = 'Student email address is required.';
            continue;
        }

        if ($email === '') {
            continue;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = $person . ' email address is invalid.';
        } elseif (strlen($email) > 150) {
            $errors[] = $person . ' email address must not exceed 150 characters.';
        } else {
            $emailDomain = strtolower(substr(strrchr($email, '@'), 1));

            if ($emailDomain !== 'gmail.com') {
                $errors[] = $person . ' email must be a valid Gmail address ending in @gmail.com.';
            }
        }
    }

    /* Validate all four Philippine phone numbers */
    foreach ($phoneFields as $field => $person) {

        if (
            $values[$field] !== '' &&
            !preg_match('/^9[0-9]{9}$/', $values[$field])
        ) {
            $errors[] = $person . ' phone number must contain exactly 10 digits after +63.';
        }
    }

    /* Check duplicate student login email */
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
            $errors[] = 'That student email address is already being used by another account.';
        }
    }

    /* Save contact information */
    if (!$errors) {

        try {

            $pdo->beginTransaction();

            /* Update student email */
            $updateUser = $pdo->prepare(
                'UPDATE users
                 SET email = :email
                 WHERE user_id = :user_id'
            );

            $updateUser->execute([
                'email' => $values['email'],
                'user_id' => $userId
            ]);

            /* Update student phone number */
            $updateStudent = $pdo->prepare(
                'UPDATE students
                 SET contact_number = :contact_number
                 WHERE student_id = :student_id'
            );

            $updateStudent->execute([
                'contact_number' => $values['contact_number'] !== ''
                    ? '+63' . $values['contact_number']
                    : null,
                'student_id' => $studentId
            ]);

            /* Update mother's email and phone number */
            if (!empty($parents)) {

                $updateMother = $pdo->prepare(
                    'UPDATE student_parent_information
                     SET mother_email = :email,
                         mother_contact_number = :contact_number
                     WHERE student_id = :student_id'
                );

                $updateMother->execute([
                    'email' => $values['mother_email'] !== ''
                        ? $values['mother_email']
                        : null,
                    'contact_number' => $values['mother_contact_number'] !== ''
                        ? '+63' . $values['mother_contact_number']
                        : null,
                    'student_id' => $studentId
                ]);

                /* Update father's email and phone number */
                $updateFather = $pdo->prepare(
                    'UPDATE student_parent_information
                     SET father_email = :email,
                         father_contact_number = :contact_number
                     WHERE student_id = :student_id'
                );

                $updateFather->execute([
                    'email' => $values['father_email'] !== ''
                        ? $values['father_email']
                        : null,
                    'contact_number' => $values['father_contact_number'] !== ''
                        ? '+63' . $values['father_contact_number']
                        : null,
                    'student_id' => $studentId
                ]);
            }

            /* Update guardian email and phone number */
            if (!empty($guardian) && !empty($guardian['guardian_id'])) {

                $updateGuardian = $pdo->prepare(
                    'UPDATE student_guardians
                     SET email = :email,
                         contact_number = :contact_number
                     WHERE guardian_id = :guardian_id
                       AND student_id = :student_id'
                );

                $updateGuardian->execute([
                    'email' => $values['guardian_email'] !== ''
                        ? $values['guardian_email']
                        : null,
                    'contact_number' => $values['guardian_contact_number'] !== ''
                        ? '+63' . $values['guardian_contact_number']
                        : null,
                    'guardian_id' => $guardian['guardian_id'],
                    'student_id' => $studentId
                ]);
            }

            $pdo->commit();

            $_SESSION['user']['email'] = $values['email'];

            $success = 'Your contact information has been updated successfully.';

            /* Refresh student information */
            $studentQuery->execute(['user_id' => $userId]);
            $student = $studentQuery->fetch();

            $parentQuery->execute(['student_id' => $studentId]);
            $parents = $parentQuery->fetch() ?: [];

            $guardianQuery->execute(['student_id' => $studentId]);
            $guardian = $guardianQuery->fetch() ?: [];

            /* Refresh editable values */
            $values = [
                'email' => $student['email'] ?? '',
                'contact_number' => phone_digits_for_display($student['contact_number'] ?? ''),

                'mother_email' => $parents['mother_email'] ?? '',
                'mother_contact_number' => phone_digits_for_display($parents['mother_contact_number'] ?? ''),

                'father_email' => $parents['father_email'] ?? '',
                'father_contact_number' => phone_digits_for_display($parents['father_contact_number'] ?? ''),

                'guardian_email' => $guardian['email'] ?? '',
                'guardian_contact_number' => phone_digits_for_display($guardian['contact_number'] ?? '')
            ];

            $isEditing = false;

        } catch (Throwable $exception) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log('Student profile update error: ' . $exception->getMessage());

            $errors[] = 'Your profile could not be saved. Please try again.';
        }
    }
}

/* Student personal information */
$personalInformation = [
    'Learner Reference Number (LRN)' => $student['lrn'] ?? '',
    'First Name' => $student['first_name'] ?? '',
    'Middle Name' => $student['middle_name'] ?? '',
    'Last Name' => $student['last_name'] ?? '',
    'Date of Birth' => $student['birth_date'] ?? '',
    'Place of Birth' => $student['birthplace'] ?? '',
    'Sex' => $student['sex'] ?? '',
    'Civil Status' => $student['civil_status'] ?? '',
    'Nationality' => $student['nationality'] ?? '',
    'Religion' => $student['religion'] ?? ''
];

$pageTitle = 'My Profile';
require_once __DIR__ . '/../includes/header.php';
?>

<section class="form-card form-card-wide <?= $isEditing ? 'profile-editing' : '' ?>">


    <h1>My Profile</h1>

    <p class="muted">
        View your personal information and contact details.
        You can update your email addresses and the contact numbers listed in your profile.
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

        <?php if ($isEditing): ?>
            <input
                type="hidden"
                name="csrf_token"
                value="<?= e(csrf_token()) ?>"
            >
        <?php endif; ?>

        <!-- 1. Student Personal Information -->
        <h2>1. Student Personal Information</h2>

        <?php foreach ($personalInformation as $label => $value): ?>
            <label>
                <?= e($label) ?>

                <input
                    type="text"
                    value="<?= e($value !== '' ? $value : 'Not provided') ?>"
                    disabled
                >
            </label>
        <?php endforeach; ?>

        <!-- 2. Student Contact Information -->
        <h2>2. Contact Information</h2>

        <label>
            Email Address

            <input
                type="email"
                name="email"
                maxlength="150"
                pattern="^[^@\s]+@gmail\.com$"
                title="Enter a valid Gmail address ending in @gmail.com."
                placeholder="example@gmail.com"
                value="<?= e($values['email']) ?>"
                <?= !$isEditing ? 'disabled' : 'required' ?>
            >
        </label>

        <label>
            Phone Number

            <div class="phone-input-wrapper" style="display: flex; align-items: center; flex-wrap: nowrap; width: 100%; min-height: 45px; box-sizing: border-box; padding: 0 12px; border: 1px solid #ced4da; border-radius: 8px; background: #fff;">
                <span class="phone-prefix" style="flex: 0 0 auto; white-space: nowrap; padding-right: 12px;">+63</span>
                <span class="phone-divider" aria-hidden="true" style="flex: 0 0 1px; height: 22px; background: #ced4da; margin-right: 12px;"></span>

                <input
                    type="tel"
                    style="flex: 1 1 auto; min-width: 0; width: 100%; box-sizing: border-box; border: 0; outline: 0; box-shadow: none; background: transparent; padding: 12px 0; margin: 0;"
                    name="contact_number"
                    inputmode="numeric"
                    pattern="9[0-9]{9}"
                    minlength="10"
                    maxlength="10"
                    placeholder="9123456789"
                    value="<?= e($values['contact_number']) ?>"
                    <?= !$isEditing ? 'disabled' : '' ?>
                    oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 10);"
                >
            </div>
        </label>

        <!-- 3. Mother's Information -->
        <h2>3. Mother's Information</h2>

        <label>
            Mother's Full Name

            <input
                type="text"
                value="<?= e(($parents['mother_name'] ?? '') ?: 'Not provided') ?>"
                disabled
            >
        </label>

        <label>
            Contact Number

            <div class="phone-input-wrapper" style="display: flex; align-items: center; flex-wrap: nowrap; width: 100%; min-height: 45px; box-sizing: border-box; padding: 0 12px; border: 1px solid #ced4da; border-radius: 8px; background: #fff;">
                <span class="phone-prefix" style="flex: 0 0 auto; white-space: nowrap; padding-right: 12px;">+63</span>
                <span class="phone-divider" aria-hidden="true" style="flex: 0 0 1px; height: 22px; background: #ced4da; margin-right: 12px;"></span>

                <input
                    type="tel"
                    style="flex: 1 1 auto; min-width: 0; width: 100%; box-sizing: border-box; border: 0; outline: 0; box-shadow: none; background: transparent; padding: 12px 0; margin: 0;"
                    name="mother_contact_number"
                    inputmode="numeric"
                    pattern="9[0-9]{9}"
                    minlength="10"
                    maxlength="10"
                    placeholder="9123456789"
                    value="<?= e($values['mother_contact_number']) ?>"
                    <?= !$isEditing ? 'disabled' : '' ?>
                    oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 10);"
                >
            </div>
        </label>

        <label>
            Occupation

            <input
                type="text"
                value="<?= e(($parents['mother_occupation'] ?? '') ?: 'Not provided') ?>"
                disabled
            >
        </label>

        <label>
            Email Address

            <input
                type="email"
                name="mother_email"
                maxlength="150"
                pattern="^[^@\s]+@gmail\.com$"
                title="Enter a valid Gmail address ending in @gmail.com."
                placeholder="example@gmail.com"
                value="<?= e($values['mother_email']) ?>"
                <?= !$isEditing ? 'disabled' : '' ?>
            >
        </label>

        <label>
            Address

            <input
                type="text"
                value="<?= e(($parents['mother_address'] ?? '') ?: 'Not provided') ?>"
                disabled
            >
        </label>

        <!-- 4. Father's Information -->
        <h2>4. Father's Information</h2>

        <label>
            Father's Full Name

            <input
                type="text"
                value="<?= e(($parents['father_name'] ?? '') ?: 'Not provided') ?>"
                disabled
            >
        </label>

        <label>
            Contact Number

            <div class="phone-input-wrapper" style="display: flex; align-items: center; flex-wrap: nowrap; width: 100%; min-height: 45px; box-sizing: border-box; padding: 0 12px; border: 1px solid #ced4da; border-radius: 8px; background: #fff;">
                <span class="phone-prefix" style="flex: 0 0 auto; white-space: nowrap; padding-right: 12px;">+63</span>
                <span class="phone-divider" aria-hidden="true" style="flex: 0 0 1px; height: 22px; background: #ced4da; margin-right: 12px;"></span>

                <input
                    type="tel"
                    style="flex: 1 1 auto; min-width: 0; width: 100%; box-sizing: border-box; border: 0; outline: 0; box-shadow: none; background: transparent; padding: 12px 0; margin: 0;"
                    name="father_contact_number"
                    inputmode="numeric"
                    pattern="9[0-9]{9}"
                    minlength="10"
                    maxlength="10"
                    placeholder="9123456789"
                    value="<?= e($values['father_contact_number']) ?>"
                    <?= !$isEditing ? 'disabled' : '' ?>
                    oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 10);"
                >
            </div>
        </label>

        <label>
            Occupation

            <input
                type="text"
                value="<?= e(($parents['father_occupation'] ?? '') ?: 'Not provided') ?>"
                disabled
            >
        </label>

        <label>
            Email Address

            <input
                type="email"
                name="father_email"
                maxlength="150"
                pattern="^[^@\s]+@gmail\.com$"
                title="Enter a valid Gmail address ending in @gmail.com."
                placeholder="example@gmail.com"
                value="<?= e($values['father_email']) ?>"
                <?= !$isEditing ? 'disabled' : '' ?>
            >
        </label>

        <label>
            Address

            <input
                type="text"
                value="<?= e(($parents['father_address'] ?? '') ?: 'Not provided') ?>"
                disabled
            >
        </label>

        <!-- 5. Additional Guardian Information -->
        <h2>5. Additional Guardian Information</h2>

        <p class="muted">
            This section displays the additional guardian information
            provided during student enrollment.
        </p>

        <label>
            Guardian's Full Name

            <input
                type="text"
                value="<?= e(($guardian['full_name'] ?? '') ?: 'Not provided') ?>"
                disabled
            >
        </label>

        <label>
            Contact Number

            <div class="phone-input-wrapper" style="display: flex; align-items: center; flex-wrap: nowrap; width: 100%; min-height: 45px; box-sizing: border-box; padding: 0 12px; border: 1px solid #ced4da; border-radius: 8px; background: #fff;">
                <span class="phone-prefix" style="flex: 0 0 auto; white-space: nowrap; padding-right: 12px;">+63</span>
                <span class="phone-divider" aria-hidden="true" style="flex: 0 0 1px; height: 22px; background: #ced4da; margin-right: 12px;"></span>

                <input
                    type="tel"
                    style="flex: 1 1 auto; min-width: 0; width: 100%; box-sizing: border-box; border: 0; outline: 0; box-shadow: none; background: transparent; padding: 12px 0; margin: 0;"
                    name="guardian_contact_number"
                    inputmode="numeric"
                    pattern="9[0-9]{9}"
                    minlength="10"
                    maxlength="10"
                    placeholder="9123456789"
                    value="<?= e($values['guardian_contact_number']) ?>"
                    <?= !$isEditing ? 'disabled' : '' ?>
                    oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 10);"
                >
            </div>
        </label>

        <label>
            Relationship to Student

            <input
                type="text"
                value="<?= e(($guardian['relationship'] ?? '') ?: 'Not provided') ?>"
                disabled
            >
        </label>

        <label>
            Email Address

            <input
                type="email"
                name="guardian_email"
                maxlength="150"
                pattern="^[^@\s]+@gmail\.com$"
                title="Enter a valid Gmail address ending in @gmail.com."
                placeholder="example@gmail.com"
                value="<?= e($values['guardian_email']) ?>"
                <?= !$isEditing ? 'disabled' : '' ?>
            >
        </label>

        <label>
            Occupation

            <input
                type="text"
                value="<?= e(($guardian['occupation'] ?? '') ?: 'Not provided') ?>"
                disabled
            >
        </label>

        <label>
            Address

            <input
                type="text"
                value="<?= e(($guardian['address'] ?? '') ?: 'Not provided') ?>"
                disabled
            >
        </label>

        <!-- Profile actions -->
        <div class="form-actions">

            <?php if ($isEditing): ?>

                <button
                    class="button button-primary"
                    type="submit"
                >
                    Save Changes
                </button>

                <a
                    href="profile.php"
                    class="button button-secondary"
                >
                    Cancel
                </a>

            <?php else: ?>

                <a
                    href="profile.php?edit=1"
                    class="button button-primary"
                >
                    Edit Profile
                </a>

            <?php endif; ?>

        </div>

    </form>

</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
