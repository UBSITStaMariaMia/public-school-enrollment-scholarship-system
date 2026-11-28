<?php
require_once __DIR__ . '/../includes/bootstrap.php';

require_role('System Administrator', 'Scholarship Coordinator');

$pageTitle = 'Scholarship Programs';
$error = '';
$success = '';

if (isset($_GET['success'])) {
    $successMessages = [
        'added' => 'Scholarship program added successfully.',
        'updated' => 'Scholarship program updated successfully.',
        'status' => 'Scholarship program status updated successfully.'
    ];

    $success = $successMessages[$_GET['success']] ?? '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
            throw new RuntimeException('Invalid request. Please refresh the page and try again.');
        }

        $action = $_POST['action'] ?? '';

        if ($action === 'toggle') {
            $id = filter_var($_POST['program_id'] ?? null, FILTER_VALIDATE_INT);

            if (!$id || $id < 1) {
                throw new RuntimeException('Invalid scholarship program.');
            }

            $stmt = $pdo->prepare(
                "UPDATE special_programs
                 SET status = CASE
                     WHEN status = 'Active' THEN 'Inactive'
                     ELSE 'Active'
                 END
                 WHERE program_id = :id"
            );
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

            redirect('/scholarship_coordinator/scholarship_programs.php?success=status');
        }

        throw new RuntimeException('Invalid request.');
    } catch (PDOException $ex) {
        error_log('Scholarship program status update error: ' . $ex->getMessage());
        $error = 'Unable to update the scholarship program status. Please try again.';
    } catch (RuntimeException $ex) {
        $error = $ex->getMessage();
    }
}

$sql = 'SELECT sp.*,
        (SELECT COUNT(*)
         FROM scholarship_applications sa
         WHERE sa.program_id = sp.program_id) AS application_count,
        (SELECT COUNT(*)
         FROM scholarship_applications sa
         JOIN school_years sy ON sy.school_year_id = sa.school_year_id
         WHERE sa.program_id = sp.program_id AND sa.application_status = \'Approved\'
           AND sy.status = \'Active\') AS current_scholar_count
        FROM special_programs sp
        ORDER BY sp.program_name';

$programs = $pdo->query($sql)->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<section class="form-card form-card-wide scholarship-program-card">
    <h1>Scholarship Programs</h1>

    <?php if ($success): ?>
        <p class="success-message"><?= e($success) ?></p>
    <?php endif; ?>

    <?php if ($error): ?>
        <p class="error-message"><?= e($error) ?></p>
    <?php endif; ?>

    <div class="portal-list-toolbar">
        <h2>Existing Programs</h2>
        <a
            href="<?= BASE_URL ?>/scholarship_coordinator/add_scholarship_program.php"
            class="button button-primary portal-add-action"
        >
            <span class="portal-add-icon" aria-hidden="true">+</span> Add Program
        </a>
    </div>


    <div class="table-responsive scholarship-program-table-wrap">
        <table class="scholarship-program-table">
            <thead>
                <tr>
                    <th scope="col">Program</th>
                    <th scope="col">Slots</th>
                    <th scope="col">Applications</th>
                    <th scope="col">Status</th>
                    <th scope="col">Actions</th>
                </tr>
            </thead>

            <tbody>
                <?php if ($programs): ?>
                    <?php foreach ($programs as $program): ?>
                        <tr>
                            <td>
                                <strong><?= e($program['program_name']) ?></strong>
                                <br>
                                <?= e($program['description'] ?? '') ?>
                            </td>

                            <td>
                                <?= $program['available_slots'] === null
                                    ? 'Unlimited'
                                    : (int) $program['available_slots'] ?>
                            </td>

                            <td><?= (int) $program['application_count'] ?></td>

                            <td>
                                <span class="scholarship-status-label <?= $program['status'] === 'Active'
                                    ? 'program-status-active'
                                    : 'program-status-inactive' ?>">
                                    <?= e($program['status']) ?>
                                </span>
                            </td>

                            <td>
                                <div class="scholarship-program-actions">
                                    <a
                                        class="button button-outline portal-record-action"
                                        href="<?= BASE_URL ?>/scholarship_coordinator/edit_scholarship_program.php?id=<?= (int) $program['program_id'] ?>"
                                    >
                                        Edit
                                    </a>

                                    <form method="post" class="scholarship-toggle-form"
                                        <?php if ($program['status'] === 'Active' && (int) $program['current_scholar_count'] > 0): ?>
                                            data-confirm-delete="<?= e('This program has ' . $program['current_scholar_count'] . ' approved scholarship applications for the active school year. Deactivating it closes the program to new applications. Existing records will remain available.') ?>"
                                            data-confirm-title="Deactivate scholarship program?"
                                            data-confirm-label="Deactivate"
                                        <?php endif; ?>
                                    >
                                        <input
                                            type="hidden"
                                            name="csrf_token"
                                            value="<?= e(csrf_token()) ?>"
                                        >
                                        <input type="hidden" name="action" value="toggle">
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
                        <td colspan="5">No scholarship programs found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
