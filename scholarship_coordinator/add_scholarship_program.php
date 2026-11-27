<?php
require_once __DIR__ . '/../includes/bootstrap.php';

require_role('System Administrator', 'Scholarship Coordinator');

$pageTitle = 'Add Scholarship Program';
$error = '';

$values = [
    'program_name' => '',
    'description' => '',
    'available_slots' => '',
    'status' => 'Active'
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

        $stmt = $pdo->prepare(
            'INSERT INTO special_programs
                (program_name, description, available_slots, status)
             VALUES
                (:name, :description, :slots, :status)'
        );

        $stmt->execute([
            'name' => $name,
            'description' => $description !== '' ? $description : null,
            'slots' => $slots,
            'status' => $status
        ]);

        redirect('/scholarship_coordinator/scholarship_programs.php?success=added');

    } catch (PDOException $ex) {
        error_log('Add scholarship program error: ' . $ex->getMessage());

        $error = $ex->getCode() === '23000'
            ? 'A scholarship program with that name already exists.'
            : 'Unable to add the scholarship program. Please try again.';

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

    <h1>Add Scholarship Program</h1>

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
            <button type="submit" class="button button-primary">Save Program</button>
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
