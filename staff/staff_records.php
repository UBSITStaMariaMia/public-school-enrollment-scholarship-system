
<?php
require_once __DIR__ . '/../includes/bootstrap.php';

require_role('System Administrator');

$pageTitle = 'Staff Records Management';

$admin = current_user();

$success = $_SESSION['staff_records_success'] ?? '';
unset($_SESSION['staff_records_success']);

$error = '';

$employmentStatuses = [
    'Active',
    'On Leave',
    'Resigned',
    'Retired'
];

$accountStatuses = [
    'Active',
    'Inactive',
    'Locked'
];

$employmentTypes = [
    'Permanent',
    'Contractual',
    'Temporary',
    'Part-time',
    'Volunteer',
    'Other'
];

$activeSchoolYear = $pdo->query(
    "SELECT school_year_id, school_year
     FROM school_years
     WHERE status = 'Active'
     LIMIT 1"
)->fetch();

/*
|--------------------------------------------------------------------------
| Helper: Get a staff account
|--------------------------------------------------------------------------
*/

$getStaffAccount = function (int $userId) use ($pdo): ?array {
    $query = $pdo->prepare(
        "SELECT
            u.user_id,
            u.username,
            u.email,
            u.account_status,
            r.role_name
         FROM users u
         INNER JOIN roles r
            ON r.role_id = u.role_id
         WHERE u.user_id = :user_id
         AND r.role_name <> 'Student'
         LIMIT 1"
    );

    $query->execute([
        'user_id' => $userId
    ]);

    return $query->fetch() ?: null;
};

/*
|--------------------------------------------------------------------------
| Get selected staff account
|--------------------------------------------------------------------------
*/

$selectedUserId = filter_input(
    INPUT_GET,
    'user_id',
    FILTER_VALIDATE_INT
);

/*
|--------------------------------------------------------------------------
| Process form submissions
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    $postedUserId = filter_var(
        $_POST['user_id'] ?? null,
        FILTER_VALIDATE_INT
    );

    if ($postedUserId) {
        $selectedUserId = $postedUserId;
    }

    try {
        if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
            throw new RuntimeException(
                'Invalid request. Please refresh the page.'
            );
        }

        if ($action === 'save_profile') {

            if (!$postedUserId) {
                throw new RuntimeException(
                    'Please select a valid staff account.'
                );
            }

            $staffAccount = $getStaffAccount($postedUserId);

            if (!$staffAccount) {
                throw new RuntimeException(
                    'Staff account not found.'
                );
            }

            $employeeId = trim($_POST['employee_id'] ?? '');
            $firstName = trim($_POST['first_name'] ?? '');
            $middleName = trim($_POST['middle_name'] ?? '');
            $lastName = trim($_POST['last_name'] ?? '');

            $birthDate = trim($_POST['birth_date'] ?? '');
            $contactNumber = trim($_POST['contact_number'] ?? '');
            $address = trim($_POST['address'] ?? '');

            $positionTitle = trim($_POST['position_title'] ?? '');
            $department = trim($_POST['department'] ?? '');
            $employmentType = trim($_POST['employment_type'] ?? '');
            $hireDate = trim($_POST['hire_date'] ?? '');

            $employmentStatus = $_POST['employment_status'] ?? '';
            $accountStatus = $_POST['account_status'] ?? '';

            if (
                $employeeId === '' ||
                $firstName === '' ||
                $lastName === ''
            ) {
                throw new RuntimeException(
                    'Employee ID, first name, and last name are required.'
                );
            }

            if (
                strlen($employeeId) > 30 ||
                strlen($firstName) > 100 ||
                strlen($middleName) > 100 ||
                strlen($lastName) > 100 ||
                strlen($contactNumber) > 30 ||
                strlen($positionTitle) > 100 ||
                strlen($department) > 100 ||
                strlen($employmentType) > 50
            ) {
                throw new RuntimeException(
                    'One or more fields exceed their allowed length.'
                );
            }

            if (!in_array($employmentStatus, $employmentStatuses, true)) {
                throw new RuntimeException(
                    'Please select a valid employment status.'
                );
            }

            if (!in_array($employmentType, $employmentTypes, true)) {
                throw new RuntimeException(
                    'Please select a valid employment type.'
                );
            }

            if (!in_array($accountStatus, $accountStatuses, true)) {
                throw new RuntimeException(
                    'Please select a valid account status.'
                );
            }

            /*
            |--------------------------------------------------------------
            | Validate optional dates
            |--------------------------------------------------------------
            */

            foreach ([
                'Birth date' => $birthDate,
                'Hire date' => $hireDate
            ] as $label => $dateValue) {
                if ($dateValue !== '') {
                    $dateParts = explode('-', $dateValue);

                    if (
                        count($dateParts) !== 3 ||
                        !checkdate(
                            (int) ($dateParts[1] ?? 0),
                            (int) ($dateParts[2] ?? 0),
                            (int) ($dateParts[0] ?? 0)
                        )
                    ) {
                        throw new RuntimeException(
                            'Please enter a valid ' . strtolower($label) . '.'
                        );
                    }
                }
            }

            /*
            |--------------------------------------------------------------
            | Prevent administrator from deactivating their own account
            |--------------------------------------------------------------
            */

            if ((int) $postedUserId === (int) $admin['user_id']) {
                $accountStatus = $staffAccount['account_status'];
            }

            $pdo->beginTransaction();

            $existingProfileQuery = $pdo->prepare(
                "SELECT staff_profile_id
                 FROM staff_profiles
                 WHERE user_id = :user_id
                 LIMIT 1"
            );

            $existingProfileQuery->execute([
                'user_id' => $postedUserId
            ]);

            $existingProfile = $existingProfileQuery->fetch();

            if ($existingProfile) {

                $saveProfile = $pdo->prepare(
                    "UPDATE staff_profiles
                     SET employee_id = :employee_id,
                         first_name = :first_name,
                         middle_name = :middle_name,
                         last_name = :last_name,
                         birth_date = :birth_date,
                         contact_number = :contact_number,
                         address = :address,
                         position_title = :position_title,
                         department = :department,
                         employment_type = :employment_type,
                         hire_date = :hire_date,
                         employment_status = :employment_status
                     WHERE user_id = :user_id"
                );

            } else {

                $saveProfile = $pdo->prepare(
                    "INSERT INTO staff_profiles
                     (
                         user_id,
                         employee_id,
                         first_name,
                         middle_name,
                         last_name,
                         birth_date,
                         contact_number,
                         address,
                         position_title,
                         department,
                         employment_type,
                         hire_date,
                         employment_status
                     )
                     VALUES
                     (
                         :user_id,
                         :employee_id,
                         :first_name,
                         :middle_name,
                         :last_name,
                         :birth_date,
                         :contact_number,
                         :address,
                         :position_title,
                         :department,
                         :employment_type,
                         :hire_date,
                         :employment_status
                     )"
                );
            }

            $saveProfile->execute([
                'user_id' => $postedUserId,
                'employee_id' => $employeeId,
                'first_name' => $firstName,
                'middle_name' => $middleName !== '' ? $middleName : null,
                'last_name' => $lastName,
                'birth_date' => $birthDate !== '' ? $birthDate : null,
                'contact_number' => $contactNumber !== '' ? $contactNumber : null,
                'address' => $address !== '' ? $address : null,
                'position_title' => $positionTitle !== '' ? $positionTitle : null,
                'department' => $department !== '' ? $department : null,
                'employment_type' => $employmentType,
                'hire_date' => $hireDate !== '' ? $hireDate : null,
                'employment_status' => $employmentStatus
            ]);

            /*
            |--------------------------------------------------------------
            | Update the associated login account status
            |--------------------------------------------------------------
            */

            if ((int) $postedUserId !== (int) $admin['user_id']) {
                $updateAccount = $pdo->prepare(
                    "UPDATE users
                     SET account_status = :account_status
                     WHERE user_id = :user_id"
                );

                $updateAccount->execute([
                    'account_status' => $accountStatus,
                    'user_id' => $postedUserId
                ]);
            }

            $pdo->commit();

            $_SESSION['staff_records_success'] =
                'Staff information saved successfully.';

            redirect('/staff/staff_records.php?user_id=' . $postedUserId);

        } elseif ($action === 'assign_section') {

            if (!$postedUserId) {
                throw new RuntimeException(
                    'Please select a valid teacher.'
                );
            }

            if (!$activeSchoolYear) {
                throw new RuntimeException(
                    'There is no active school year.'
                );
            }

            $sectionId = filter_var(
                $_POST['section_id'] ?? null,
                FILTER_VALIDATE_INT
            );

            if (!$sectionId) {
                throw new RuntimeException(
                    'Please select a valid section.'
                );
            }

            $staffAccount = $getStaffAccount($postedUserId);

            if (
                !$staffAccount ||
                $staffAccount['role_name'] !== 'Teacher'
            ) {
                throw new RuntimeException(
                    'Only teacher accounts can be assigned to sections.'
                );
            }

            if ($staffAccount['account_status'] !== 'Active') {
                throw new RuntimeException(
                    'Only active teacher accounts can receive section assignments.'
                );
            }

            $sectionCheck = $pdo->prepare(
                "SELECT section_id
                 FROM sections
                 WHERE section_id = :section_id
                 AND school_year_id = :school_year_id
                 LIMIT 1"
            );

            $sectionCheck->execute([
                'section_id' => $sectionId,
                'school_year_id' => $activeSchoolYear['school_year_id']
            ]);

            if (!$sectionCheck->fetch()) {
                throw new RuntimeException(
                    'The selected section does not belong to the active school year.'
                );
            }

            $assignSection = $pdo->prepare(
                "INSERT INTO teacher_section_assignments
                 (
                     teacher_user_id,
                     section_id,
                     school_year_id,
                     assigned_by
                 )
                 VALUES
                 (
                     :teacher_user_id,
                     :section_id,
                     :school_year_id,
                     :assigned_by
                 )"
            );

            $assignSection->execute([
                'teacher_user_id' => $postedUserId,
                'section_id' => $sectionId,
                'school_year_id' => $activeSchoolYear['school_year_id'],
                'assigned_by' => $admin['user_id']
            ]);

            $_SESSION['staff_records_success'] =
                'Teacher section assigned successfully.';

            redirect('/staff/staff_records.php?user_id=' . $postedUserId);

        } elseif ($action === 'remove_section') {

            if (!$postedUserId || !$activeSchoolYear) {
                throw new RuntimeException(
                    'Unable to process the section removal request.'
                );
            }

            $assignmentId = filter_var(
                $_POST['assignment_id'] ?? null,
                FILTER_VALIDATE_INT
            );

            if (!$assignmentId) {
                throw new RuntimeException(
                    'Invalid section assignment.'
                );
            }

            $removeAssignment = $pdo->prepare(
                "DELETE FROM teacher_section_assignments
                 WHERE assignment_id = :assignment_id
                 AND teacher_user_id = :teacher_user_id
                 AND school_year_id = :school_year_id"
            );

            $removeAssignment->execute([
                'assignment_id' => $assignmentId,
                'teacher_user_id' => $postedUserId,
                'school_year_id' => $activeSchoolYear['school_year_id']
            ]);

            $_SESSION['staff_records_success'] =
                'Teacher section assignment removed successfully.';

            redirect('/staff/staff_records.php?user_id=' . $postedUserId);

        } else {
            throw new RuntimeException('Invalid action.');
        }

    } catch (RuntimeException $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        $error = $exception->getMessage();

    } catch (PDOException $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        error_log('Staff records error: ' . $exception->getMessage());

        if ($exception->getCode() === '23000') {
            $error = 'This employee ID or section assignment already exists.';
        } else {
            $error = 'Unable to save the information. Please check your entries and try again.';
        }
    }
}

/*
|--------------------------------------------------------------------------
| Load all staff accounts
|--------------------------------------------------------------------------
*/

$staffQuery = $pdo->query(
    "SELECT
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
        sp.employment_status
     FROM users u
     INNER JOIN roles r
        ON r.role_id = u.role_id
     LEFT JOIN staff_profiles sp
        ON sp.user_id = u.user_id
     WHERE r.role_name <> 'Student'
     ORDER BY r.role_name, sp.last_name, u.username"
);

$staffUsers = $staffQuery->fetchAll();

/*
|--------------------------------------------------------------------------
| Load selected staff profile
|--------------------------------------------------------------------------
*/

$selectedStaff = null;

if ($selectedUserId) {
    $selectedStaff = $getStaffAccount((int) $selectedUserId);

    if ($selectedStaff) {
        $profileQuery = $pdo->prepare(
            "SELECT *
             FROM staff_profiles
             WHERE user_id = :user_id
             LIMIT 1"
        );

        $profileQuery->execute([
            'user_id' => $selectedUserId
        ]);

        $profile = $profileQuery->fetch();

        if ($profile) {
            $selectedStaff = array_merge($selectedStaff, $profile);
        }
    } else {
        $error = 'Staff account not found.';
        $selectedUserId = null;
    }
}

/*
|--------------------------------------------------------------------------
| Get available sections for the active school year
|--------------------------------------------------------------------------
*/

$availableSections = [];

if ($activeSchoolYear) {
    $sectionQuery = $pdo->prepare(
        "SELECT
            s.section_id,
            s.section_name,
            g.grade_level
         FROM sections s
         INNER JOIN grade_levels g
            ON g.grade_level_id = s.grade_level_id
         WHERE s.school_year_id = :school_year_id
         ORDER BY g.grade_level, s.section_name"
    );

    $sectionQuery->execute([
        'school_year_id' => $activeSchoolYear['school_year_id']
    ]);

    $availableSections = $sectionQuery->fetchAll();
}

/*
|--------------------------------------------------------------------------
| Get selected teacher's section assignments
|--------------------------------------------------------------------------
*/

$teacherAssignments = [];

if (
    $selectedStaff &&
    $selectedStaff['role_name'] === 'Teacher' &&
    $activeSchoolYear
) {
    $assignmentQuery = $pdo->prepare(
        "SELECT
            tsa.assignment_id,
            s.section_name,
            g.grade_level
         FROM teacher_section_assignments tsa
         INNER JOIN sections s
            ON s.section_id = tsa.section_id
         INNER JOIN grade_levels g
            ON g.grade_level_id = s.grade_level_id
         WHERE tsa.teacher_user_id = :teacher_user_id
         AND tsa.school_year_id = :school_year_id
         ORDER BY g.grade_level, s.section_name"
    );

    $assignmentQuery->execute([
        'teacher_user_id' => $selectedStaff['user_id'],
        'school_year_id' => $activeSchoolYear['school_year_id']
    ]);

    $teacherAssignments = $assignmentQuery->fetchAll();
}

require_once __DIR__ . '/../includes/header.php';
?>

<section class="form-card">
    <h1>Staff Records Management</h1>

    <p>
        Manage employee information, employment details,
        staff account status, and teacher section assignments.
    </p>

    <?php if ($activeSchoolYear): ?>
        <p>
            <strong>Active School Year:</strong>
            <?= e($activeSchoolYear['school_year']) ?>
        </p>
    <?php else: ?>
        <p class="alert alert-error">
            No active school year is currently configured.
        </p>
    <?php endif; ?>

    <?php if ($success): ?>
        <p class="alert alert-success"><?= e($success) ?></p>
    <?php endif; ?>

    <?php if ($error): ?>
        <p class="alert alert-error"><?= e($error) ?></p>
    <?php endif; ?>

    <p class="muted">
        Staff login accounts are created through User Management.
        Select an existing staff account below to manage its employee information.
    </p>

    <form method="GET">
        <div class="form-group">
            <label for="user_id">Select Staff Account</label>

            <select name="user_id" id="user_id" required>
                <option value="">-- Select Staff Account --</option>

                <?php foreach ($staffUsers as $staff): ?>
                    <option
                        value="<?= (int) $staff['user_id'] ?>"
                        <?= (string) $selectedUserId === (string) $staff['user_id'] ? 'selected' : '' ?>
                    >
                        <?= e(
                            $staff['first_name']
                                ? $staff['last_name'] . ', ' . $staff['first_name']
                                : $staff['username']
                        ) ?>
                        - <?= e($staff['role_name']) ?>
                        - <?= e($staff['account_status']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">
                View Staff Information
            </button>
        </div>
    </form>
</section>

<?php if ($selectedStaff): ?>

    <section class="form-card">
        <h2>Staff Account Information</h2>

        <div class="form-grid">
            <div class="form-group">
                <label>Username</label>
                <strong><?= e($selectedStaff['username']) ?></strong>
            </div>

            <div class="form-group">
                <label>Email Address</label>
                <strong><?= e($selectedStaff['email']) ?></strong>
            </div>

            <div class="form-group">
                <label>Role</label>
                <strong><?= e($selectedStaff['role_name']) ?></strong>
            </div>

            <div class="form-group">
                <label>Current Account Status</label>
                <strong><?= e($selectedStaff['account_status']) ?></strong>
            </div>
        </div>
    </section>

    <section class="form-card">
        <h2>Employee Information</h2>

        <p>
            Enter the staff member's personal and employment information.
            Fields marked with * are required.
        </p>

        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="save_profile">
            <input type="hidden" name="user_id" value="<?= (int) $selectedStaff['user_id'] ?>">

            <div class="form-grid">

                <div class="form-group">
                    <label for="employee_id">Employee ID *</label>
                    <input
                        type="text"
                        name="employee_id"
                        id="employee_id"
                        maxlength="30"
                        value="<?= e($selectedStaff['employee_id'] ?? '') ?>"
                        placeholder="Enter employee ID"
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="first_name">First Name *</label>
                    <input
                        type="text"
                        name="first_name"
                        id="first_name"
                        maxlength="100"
                        value="<?= e($selectedStaff['first_name'] ?? '') ?>"
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="middle_name">Middle Name</label>
                    <input
                        type="text"
                        name="middle_name"
                        id="middle_name"
                        maxlength="100"
                        value="<?= e($selectedStaff['middle_name'] ?? '') ?>"
                    >
                </div>

                <div class="form-group">
                    <label for="last_name">Last Name *</label>
                    <input
                        type="text"
                        name="last_name"
                        id="last_name"
                        maxlength="100"
                        value="<?= e($selectedStaff['last_name'] ?? '') ?>"
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="birth_date">Birth Date</label>
                    <input
                        type="date"
                        name="birth_date"
                        id="birth_date"
                        value="<?= e($selectedStaff['birth_date'] ?? '') ?>"
                    >
                </div>

                <div class="form-group">
                    <label for="contact_number">Contact Number</label>
                    <input
                        type="text"
                        name="contact_number"
                        id="contact_number"
                        maxlength="30"
                        value="<?= e($selectedStaff['contact_number'] ?? '') ?>"
                        placeholder="Enter contact number"
                    >
                </div>

                <div class="form-group">
                    <label for="address">Home Address</label>
                    <textarea
                        name="address"
                        id="address"
                        rows="3"
                    ><?= e($selectedStaff['address'] ?? '') ?></textarea>
                </div>

                <div class="form-group">
                    <label for="position_title">Position / Job Title</label>
                    <input
                        type="text"
                        name="position_title"
                        id="position_title"
                        maxlength="100"
                        value="<?= e($selectedStaff['position_title'] ?? '') ?>"
                        placeholder="Example: Teacher I"
                    >
                </div>

                <div class="form-group">
                    <label for="department">Department</label>
                    <input
                        type="text"
                        name="department"
                        id="department"
                        maxlength="100"
                        value="<?= e($selectedStaff['department'] ?? '') ?>"
                        placeholder="Example: Mathematics Department"
                    >
                </div>

                <div class="form-group">
                    <label for="employment_type">Employment Type</label>
                    <select name="employment_type" id="employment_type" required>
                        <option value="">Select Employment Type</option>

                        <?php foreach ($employmentTypes as $type): ?>
                            <option
                                value="<?= e($type) ?>"
                                <?= ($selectedStaff['employment_type'] ?? '') === $type ? 'selected' : '' ?>
                            >
                                <?= e($type) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="hire_date">Date Hired</label>
                    <input
                        type="date"
                        name="hire_date"
                        id="hire_date"
                        value="<?= e($selectedStaff['hire_date'] ?? '') ?>"
                    >
                </div>

                <div class="form-group">
                    <label for="employment_status">Employment Status</label>
                    <select name="employment_status" id="employment_status" required>
                        <?php foreach ($employmentStatuses as $status): ?>
                            <option
                                value="<?= e($status) ?>"
                                <?= ($selectedStaff['employment_status'] ?? 'Active') === $status ? 'selected' : '' ?>
                            >
                                <?= e($status) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="account_status">Login Account Status</label>

                    <?php if ((int) $selectedStaff['user_id'] === (int) $admin['user_id']): ?>

                        <input
                            type="text"
                            value="<?= e($selectedStaff['account_status']) ?> (Your own account)"
                            disabled
                        >
                        <input
                            type="hidden"
                            name="account_status"
                            value="<?= e($selectedStaff['account_status']) ?>"
                        >

                    <?php else: ?>

                        <select name="account_status" id="account_status" required>
                            <?php foreach ($accountStatuses as $status): ?>
                                <option
                                    value="<?= e($status) ?>"
                                    <?= $selectedStaff['account_status'] === $status ? 'selected' : '' ?>
                                >
                                    <?= e($status) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                    <?php endif; ?>
                </div>

            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    Save Staff Information
                </button>
            </div>
        </form>
    </section>

    <?php if ($selectedStaff['role_name'] === 'Teacher'): ?>

        <section class="form-card">
            <h2>Teacher Section Assignments</h2>

            <p>
                Assign this teacher to sections for
                <?= e($activeSchoolYear['school_year'] ?? 'the active school year') ?>.
            </p>

            <?php if ($activeSchoolYear && $availableSections): ?>

                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="assign_section">
                    <input type="hidden" name="user_id" value="<?= (int) $selectedStaff['user_id'] ?>">

                    <div class="form-group">
                        <label for="section_id">Select Section</label>

                        <select name="section_id" id="section_id" required>
                            <option value="">-- Select Section --</option>

                            <?php foreach ($availableSections as $section): ?>
                                <option value="<?= (int) $section['section_id'] ?>">
                                    Grade <?= e($section['grade_level']) ?>
                                    - <?= e($section['section_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            Assign Section
                        </button>
                    </div>
                </form>

            <?php else: ?>

                <p class="alert alert-error">
                    No sections are available for the active school year.
                </p>

            <?php endif; ?>

            <h3>Currently Assigned Sections</h3>

            <?php if ($teacherAssignments): ?>

                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Grade Level</th>
                                <th>Section</th>
                                <th>Action</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php foreach ($teacherAssignments as $assignment): ?>
                                <tr>
                                    <td>
                                        Grade <?= e($assignment['grade_level']) ?>
                                    </td>

                                    <td>
                                        <?= e($assignment['section_name']) ?>
                                    </td>

                                    <td>
                                        <form method="POST" data-confirm-delete="Remove this teacher from the selected section?">
                                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                            <input type="hidden" name="action" value="remove_section">
                                            <input type="hidden" name="user_id" value="<?= (int) $selectedStaff['user_id'] ?>">
                                            <input type="hidden" name="assignment_id" value="<?= (int) $assignment['assignment_id'] ?>">

                                            <button type="submit" class="btn btn-secondary">
                                                Remove
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

            <?php else: ?>

                <p>This teacher has no assigned sections for the active school year.</p>

            <?php endif; ?>
        </section>

    <?php endif; ?>

<?php endif; ?>

<section class="form-card">
    <h2>Staff Directory</h2>

    <p>List of existing staff accounts and their employee information.</p>

    <?php if ($staffUsers): ?>

        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Employee ID</th>
                        <th>Staff Name</th>
                        <th>Role</th>
                        <th>Position</th>
                        <th>Account Status</th>
                        <th>Employment Status</th>
                        <th>Action</th>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach ($staffUsers as $staff): ?>

                        <tr>
                            <td><?= e($staff['employee_id'] ?? 'Not assigned') ?></td>

                            <td>
                                <?php if ($staff['first_name']): ?>
                                    <?= e(
                                        $staff['last_name'] . ', ' .
                                        $staff['first_name'] . ' ' .
                                        ($staff['middle_name'] ?? '')
                                    ) ?>
                                <?php else: ?>
                                    <?= e($staff['username']) ?>
                                <?php endif; ?>
                            </td>

                            <td><?= e($staff['role_name']) ?></td>

                            <td><?= e($staff['position_title'] ?? 'Not provided') ?></td>

                            <td><?= e($staff['account_status']) ?></td>

                            <td><?= e($staff['employment_status'] ?? 'No profile') ?></td>

                            <td>
                                <a
                                    class="btn btn-secondary"
                                    href="<?= BASE_URL ?>/staff/staff_records.php?user_id=<?= (int) $staff['user_id'] ?>"
                                >
                                    Manage
                                </a>
                            </td>
                        </tr>

                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    <?php else: ?>

        <p>No staff accounts have been created yet.</p>

    <?php endif; ?>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
