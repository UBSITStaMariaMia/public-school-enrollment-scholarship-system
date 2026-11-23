
<?php
require_once __DIR__ . '/../includes/bootstrap.php';

require_role('System Administrator', 'Scholarship Coordinator');

$pageTitle = 'Scholarship Programs';
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
            throw new RuntimeException('Invalid request. Please refresh the page.');
        }

        $action = $_POST['action'] ?? '';

        // SAVE OR UPDATE PROGRAM
        if ($action === 'save') {

            $id = filter_var($_POST['program_id'] ?? null, FILTER_VALIDATE_INT);
            $name = trim($_POST['program_name'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $slotsInput = $_POST['available_slots'] ?? '';
            $slots = $slotsInput === '' ? null : filter_var($slotsInput, FILTER_VALIDATE_INT);
            $status = $_POST['status'] ?? 'Active';

            if (
                $name === '' ||
                strlen($name) > 100 ||
                !in_array($status, ['Active', 'Inactive'], true) ||
                ($slots !== null && ($slots === false || $slots < 0))
            ) {
                throw new RuntimeException('Please enter a valid program name, status, and available slot count.');
            }

            // UPDATE EXISTING PROGRAM
            if ($id) {

                $check = $pdo->prepare(
                    'SELECT program_id FROM special_programs WHERE program_id = :id'
                );
                $check->execute(['id' => $id]);

                if (!$check->fetch()) {
                    throw new RuntimeException('The selected scholarship program was not found.');
                }

                $sql = 'UPDATE special_programs
                        SET program_name = :name,
                            description = :description,
                            available_slots = :slots,
                            status = :status
                        WHERE program_id = :id';

                $stmt = $pdo->prepare($sql);

                $stmt->execute([
                    'name' => $name,
                    'description' => $description !== '' ? $description : null,
                    'slots' => $slots,
                    'status' => $status,
                    'id' => $id
                ]);

                $success = 'Scholarship program updated successfully.';

            } else {

                // ADD NEW PROGRAM
                $sql = 'INSERT INTO special_programs
                        (program_name, description, available_slots, status)
                        VALUES (:name, :description, :slots, :status)';

                $stmt = $pdo->prepare($sql);

                $stmt->execute([
                    'name' => $name,
                    'description' => $description !== '' ? $description : null,
                    'slots' => $slots,
                    'status' => $status
                ]);

                $success = 'Scholarship program added successfully.';
            }

        }

        // TOGGLE PROGRAM STATUS
        elseif ($action === 'toggle') {

            $id = filter_var($_POST['program_id'] ?? null, FILTER_VALIDATE_INT);

            if (!$id) {
                throw new RuntimeException('Invalid scholarship program.');
            }

            $sql = "UPDATE special_programs
                    SET status = CASE
                        WHEN status = 'Active' THEN 'Inactive'
                        ELSE 'Active'
                    END
                    WHERE program_id = :id";

            $stmt = $pdo->prepare($sql);
            $stmt->execute(['id' => $id]);

            if ($stmt->rowCount() < 1) {

                $check = $pdo->prepare(
                    'SELECT program_id FROM special_programs WHERE program_id = :id'
                );

                $check->execute(['id' => $id]);

                if (!$check->fetch()) {
                    throw new RuntimeException('The selected scholarship program was not found.');
                }
            }

            $success = 'Scholarship program status updated successfully.';
        }

    } catch (PDOException $ex) {

        $error = $ex->getCode() === '23000'
            ? 'A scholarship program with that name already exists.'
            : 'Unable to save the scholarship program. Please try again.';

        error_log('Scholarship programs: ' . $ex->getMessage());

    } catch (RuntimeException $ex) {

        $error = $ex->getMessage();
    }
}


// GET PROGRAM DATA FOR EDITING
$editId = filter_input(INPUT_GET, 'edit', FILTER_VALIDATE_INT);
$editing = null;

if ($editId) {

    $stmt = $pdo->prepare(
        'SELECT * FROM special_programs WHERE program_id = :id'
    );

    $stmt->execute(['id' => $editId]);

    $editing = $stmt->fetch() ?: null;

    if (!$editing) {
        $error = 'The scholarship program you selected could not be found.';
    }
}


// GET ALL EXISTING PROGRAMS
$sql = 'SELECT sp.*,
        (SELECT COUNT(*)
         FROM scholarship_applications sa
         WHERE sa.program_id = sp.program_id) AS application_count
        FROM special_programs sp
        ORDER BY sp.program_name';

$programs = $pdo->query($sql)->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<section class="form-card form-card-wide scholarship-program-card">

    <h1>Scholarship Programs</h1>

    <p class="muted">
        Create and maintain the special programs available to students.
    </p>


    <!-- SUCCESS MESSAGE -->
    <?php if ($success): ?>
        <p class="success-message">
            <?= e($success) ?>
        </p>
    <?php endif; ?>


    <!-- ERROR MESSAGE -->
    <?php if ($error): ?>
        <p class="error-message">
            <?= e($error) ?>
        </p>
    <?php endif; ?>


    <!-- ADD / EDIT FORM -->
    <h2><?= $editing ? 'Edit Program' : 'Add Program' ?></h2>

    <form method="post" class="scholarship-program-form">

        <input type="hidden" name="csrf_token"
               value="<?= e(csrf_token()) ?>">

        <input type="hidden" name="action" value="save">

        <input type="hidden" name="program_id"
               value="<?= (int) ($editing['program_id'] ?? 0) ?>">


        <label>
            Program name

            <input
                type="text"
                name="program_name"
                maxlength="100"
                required
                value="<?= e($editing['program_name'] ?? '') ?>"
            >
        </label>


        <label>
            Description

            <textarea name="description" rows="3"><?= e($editing['description'] ?? '') ?></textarea>
        </label>


        <label>
            Available slots (leave blank for unlimited)

            <input
                type="number"
                min="0"
                name="available_slots"
                value="<?= e(isset($editing['available_slots']) ? (string) $editing['available_slots'] : '') ?>"
            >
        </label>


        <label>
            Status

            <select name="status">

                <option value="Active"
                    <?= ($editing['status'] ?? 'Active') === 'Active' ? 'selected' : '' ?>>
                    Active
                </option>

                <option value="Inactive"
                    <?= ($editing['status'] ?? '') === 'Inactive' ? 'selected' : '' ?>>
                    Inactive
                </option>

            </select>
        </label>


        <div class="scholarship-form-actions">

            <button type="submit" class="button button-primary">
                Save Program
            </button>

            <?php if ($editing): ?>

                <a href="<?= BASE_URL ?>/scholarship_coordinator/scholarship_programs.php"
                   class="scholarship-cancel-link">
                    Cancel
                </a>

            <?php endif; ?>

        </div>

    </form>


    <!-- EXISTING PROGRAMS TABLE -->
    <h2>Existing Programs</h2>

    <div class="table-responsive scholarship-program-table-wrap">

        <table class="scholarship-program-table">

            <thead>

                <tr>
                    <th>Program</th>
                    <th>Slots</th>
                    <th>Applications</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>

            </thead>


            <tbody>

                <?php if (count($programs) > 0): ?>

                    <?php foreach ($programs as $program): ?>

                        <tr>

                            <!-- PROGRAM DETAILS -->
                            <td>

                                <strong>
                                    <?= e($program['program_name']) ?>
                                </strong>

                                <br>

                                <?= e($program['description'] ?? '') ?>

                            </td>


                            <!-- AVAILABLE SLOTS -->
                            <td>
                                <?= e(
                                    $program['available_slots'] === null
                                    ? 'Unlimited'
                                    : (string) $program['available_slots']
                                ) ?>
                            </td>


                            <!-- APPLICATION COUNT -->
                            <td>
                                <?= (int) $program['application_count'] ?>
                            </td>


                            <!-- STATUS -->
                            <td>

                                <span class="program-status <?= $program['status'] === 'Active' ? 'program-status-active' : 'program-status-inactive' ?>">

                                    <?= e($program['status']) ?>

                                </span>

                            </td>


                            <!-- ACTIONS -->
                            <td>

                                <div class="scholarship-program-actions">


                                    <!-- EDIT BUTTON -->
                                    <a
                                        class="scholarship-edit-link"
                                        href="<?= BASE_URL ?>/scholarship_coordinator/scholarship_programs.php?edit=<?= (int) $program['program_id'] ?>"
                                    >
                                        Edit
                                    </a>


                                    <!-- ACTIVATE / DEACTIVATE BUTTON -->
                                    <form method="post" class="scholarship-toggle-form">

                                        <input
                                            type="hidden"
                                            name="csrf_token"
                                            value="<?= e(csrf_token()) ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="action"
                                            value="toggle"
                                        >

                                        <input
                                            type="hidden"
                                            name="program_id"
                                            value="<?= (int) $program['program_id'] ?>"
                                        >

                                        <button
                                            type="submit"
                                            class="button button-secondary scholarship-toggle-button"
                                        >

                                            <?= $program['status'] === 'Active'
                                                ? 'Deactivate'
                                                : 'Activate' ?>

                                        </button>

                                    </form>

                                </div>

                            </td>

                        </tr>

                    <?php endforeach; ?>


                <?php else: ?>

                    <tr>

                        <td colspan="5">
                            No scholarship programs found.
                        </td>

                    </tr>

                <?php endif; ?>

            </tbody>

        </table>

    </div>

</section>


<?php require_once __DIR__ . '/../includes/footer.php'; ?>
