<?php
require_once __DIR__ . '/../includes/bootstrap.php';

require_role('System Administrator', 'Scholarship Coordinator');

$pageTitle = 'Edit Scholarship Program';
$error = '';

$programId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$programId || $programId < 1) {
    http_response_code(400);
    exit('Invalid scholarship program.');
}

$findProgram = $pdo->prepare(
    'SELECT program_id, program_name, description, available_slots, status
     FROM special_programs
     WHERE program_id = :id
     LIMIT 1'
);
$findProgram->execute(['id' => $programId]);
$program = $findProgram->fetch();

if (!$program) {
    http_response_code(404);
    exit('The selected scholarship program was not found.');
}

$values = [
    'program_name' => $program['program_name'],
    'description' => $program['description'] ?? '',
    'available_slots' => $program['available_slots'] === null
        ? ''
        : (string) $program['available_slots'],
    'status' => $program['status']
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $values['program_name'] = trim($_POST['program_name'] ?? '');
    $values['description'] = trim($_POST['description'] ?? '');
    $values['available_slots'] = trim($_POST['available_slots'] ?? '');
    $values['status'] = $_POST['status'] ?? 'Active';

    try {
        if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
            throw new RuntimeException('Invalid request. Please refresh the page and try again.');
        }

        $name = $values['program_name'];
        $description = $values['description'];
        $slotsInput = $values['available_slots'];
        $status = $values['status'];

        $slots = null;
        if ($slotsInput !== '') {
            $slots = filter_var($slotsInput, FILTER_VALIDATE_INT);
        }

        if (
            $name === '' ||
            strlen($name) > 100 ||
            !in_array($status, ['Active', 'Inactive'], true) ||
            ($slotsInput !== '' && ($slots === false || $slots < 0))
        ) {
            throw new RuntimeException(
                'Please enter a valid program name, status, and available slot count.'
            );
        }

        $update = $pdo->prepare(
            'UPDATE special_programs
             SET program_name = :name,
                 description = :description,
                 available_slots = :slots,
                 status = :status
             WHERE program_id = :id'
        );

        $update->execute([
            'name' => $name,
            'description' => $description !== '' ? $description : null,
            'slots' => $slots,
            'status' => $status,
            'id' => $programId
        ]);

        redirect('/scholarship_coordinator/scholarship_programs.php?success=updated');

    } catch (PDOException $ex) {
        error_log('Edit scholarship program error: ' . $ex->getMessage());

        $error = $ex->getCode() === '23000'
            ? 'A scholarship program with that name already exists.'
            : 'Unable to update the scholarship program. Please try again.';

    } catch (RuntimeException $ex) {
        $error = $ex->getMessage();
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="portal-back-row">
    <a
    href="<?= BASE_URL ?>/scholarship_coordinator/scholarship_programs.php"
    class="portal-back-link"
    >
    Back to Scholarship Programs
    </a>
</div>
<section class="form-card form-card-wide scholarship-program-card">

    <h1>Edit Scholarship Program</h1>

    <?php if ($error): ?>
        <p class="error-message"><?= e($error) ?></p>
    <?php endif; ?>

    <form method="post" class="scholarship-program-form">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

        <label>
            Program name
            <input
                type="text"
                name="program_name"
                maxlength="100"
                required
                value="<?= e($values['program_name']) ?>"
            >
        </label>

        <label>
            Description
            <textarea name="description" rows="4"><?= e($values['description']) ?></textarea>
        </label>

        <label>
            Available slots (leave blank for unlimited)
            <input
                type="number"
                min="0"
                name="available_slots"
                value="<?= e($values['available_slots']) ?>"
            >
        </label>

        <label>
            Status
            <select name="status">
                <option value="Active" <?= $values['status'] === 'Active' ? 'selected' : '' ?>>
                    Active
                </option>
                <option value="Inactive" <?= $values['status'] === 'Inactive' ? 'selected' : '' ?>>
                    Inactive
                </option>
            </select>
        </label>

        <div class="scholarship-form-actions">
            <button type="submit" class="button button-primary">Save Changes</button>
            <a
                href="<?= BASE_URL ?>/scholarship_coordinator/scholarship_programs.php"
                class="button button-secondary"
            >
                Cancel
            </a>
        </div>
    </form>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
