<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('System Administrator', 'Scholarship Coordinator');

$pageTitle = 'Scholarship Applications';
$error = '';
$success = '';
$statuses = ['Pending Review', 'For Screening', 'Approved', 'Rejected'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
            throw new RuntimeException('Invalid request. Please refresh the page and try again.');
        }

        $applicationId = filter_var($_POST['scholarship_application_id'] ?? null, FILTER_VALIDATE_INT);
        $status = $_POST['application_status'] ?? '';
        $remarks = trim($_POST['remarks'] ?? '');
        $screeningDate = trim($_POST['screening_date'] ?? '');
        $screeningTime = trim($_POST['screening_time'] ?? '');
        $screeningVenue = trim($_POST['screening_venue'] ?? '');
        $attendance = $_POST['screening_attendance'] ?? 'Pending';

        if (!$applicationId || !in_array($status, $statuses, true)) {
            throw new RuntimeException('Select a valid application and status.');
        }

        if (!in_array($attendance, ['Pending', 'Present', 'Absent'], true)) {
            throw new RuntimeException('Select a valid screening attendance status.');
        }

        if ($status === 'For Screening') {
            if ($screeningDate === '' || $screeningTime === '' || $screeningVenue === '') {
                throw new RuntimeException('Provide the screening date, time, and venue before setting the application to For Screening.');
            }
            $dateObject = DateTime::createFromFormat('Y-m-d', $screeningDate);
            if (!$dateObject || $dateObject->format('Y-m-d') !== $screeningDate) {
                throw new RuntimeException('Enter a valid screening date.');
            }
            if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $screeningTime)) {
                throw new RuntimeException('Enter a valid screening time.');
            }
            if (strlen($screeningVenue) > 180) {
                throw new RuntimeException('The screening venue must not exceed 180 characters.');
            }
        } else {
            $screeningDate = '';
            $screeningTime = '';
            $screeningVenue = '';
            $attendance = 'Pending';
        }

        $applicationCheck = $pdo->prepare(
            'SELECT scholarship_application_id
             FROM scholarship_applications
             WHERE scholarship_application_id = :application_id'
        );
        $applicationCheck->execute(['application_id' => $applicationId]);
        if (!$applicationCheck->fetch()) {
            throw new RuntimeException('The selected scholarship application could not be found.');
        }

        $updateQuery = $pdo->prepare(
            'UPDATE scholarship_applications
             SET application_status = :status,
                 remarks = :remarks,
                 screening_date = :screening_date,
                 screening_time = :screening_time,
                 screening_venue = :screening_venue,
                 screening_attendance = :screening_attendance,
                 reviewed_by = :reviewed_by,
                 reviewed_at = NOW()
             WHERE scholarship_application_id = :application_id'
        );
        $updateQuery->execute([
            'status' => $status,
            'remarks' => $remarks !== '' ? $remarks : null,
            'screening_date' => $screeningDate !== '' ? $screeningDate : null,
            'screening_time' => $screeningTime !== '' ? $screeningTime : null,
            'screening_venue' => $screeningVenue !== '' ? $screeningVenue : null,
            'screening_attendance' => $attendance,
            'reviewed_by' => (int) current_user()['user_id'],
            'application_id' => $applicationId
        ]);

        $success = 'Scholarship application review and screening details have been saved.';
    } catch (PDOException $exception) {
        error_log('Scholarship application review: ' . $exception->getMessage());
        $error = 'Unable to update the application right now. Please try again.';
    } catch (RuntimeException $exception) {
        $error = $exception->getMessage();
    }
}

$applicationsQuery = $pdo->query(
    'SELECT
        sa.scholarship_application_id,
        sa.application_status,
        sa.remarks,
        sa.application_date,
        sa.screening_date,
        sa.screening_time,
        sa.screening_venue,
        sa.screening_attendance,
        sp.program_name,
        st.first_name,
        st.middle_name,
        st.last_name,
        st.lrn,
        sy.school_year
     FROM scholarship_applications sa
     INNER JOIN special_programs sp ON sp.program_id = sa.program_id
     INNER JOIN students st ON st.student_id = sa.student_id
     INNER JOIN school_years sy ON sy.school_year_id = sa.school_year_id
     ORDER BY sa.application_date DESC'
);
$applications = $applicationsQuery->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<section class="form-card form-card-wide">
    <h1>Scholarship Applications</h1>
    <p class="muted">Review applicants, shortlist students for screening, assign their screening schedule, and record the screening attendance.</p>

    <?php if ($success): ?><p class="success-message"><?= e($success) ?></p><?php endif; ?>
    <?php if ($error): ?><p class="error-message"><?= e($error) ?></p><?php endif; ?>

    <?php if (!$applications): ?>
        <div class="empty-state"><h3>No Scholarship Applications</h3><p class="muted">No students have submitted scholarship applications yet.</p></div>
    <?php else: ?>
        <div class="table-responsive">
            <table>
                <thead><tr><th>Applicant</th><th>Program</th><th>School Year</th><th>Submitted</th><th>Review and Screening</th></tr></thead>
                <tbody>
                <?php foreach ($applications as $application): ?>
                    <tr>
                        <td>
                            <strong><?= e(trim($application['first_name'] . ' ' . ($application['middle_name'] ?? '') . ' ' . $application['last_name'])) ?></strong><br>
                            <small>LRN: <?= e($application['lrn'] ?: 'Not recorded') ?></small>
                        </td>
                        <td><?= e($application['program_name']) ?></td>
                        <td><?= e($application['school_year']) ?></td>
                        <td><?= e($application['application_date']) ?></td>
                        <td>
                            <form method="post">
                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="scholarship_application_id" value="<?= (int) $application['scholarship_application_id'] ?>">

                                <label>Application Status
                                    <select name="application_status" required>
                                        <?php foreach ($statuses as $status): ?>
                                            <option value="<?= e($status) ?>" <?= $application['application_status'] === $status ? 'selected' : '' ?>><?= e($status) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </label>

                                <fieldset style="margin:10px 0;padding:12px;border:1px solid var(--line);border-radius:8px;">
                                    <legend>Screening Schedule (required for For Screening)</legend>
                                    <label>Date
                                        <input type="date" name="screening_date" value="<?= e($application['screening_date'] ?? '') ?>">
                                    </label>
                                    <label>Time
                                        <input type="time" name="screening_time" value="<?= e($application['screening_time'] ? substr($application['screening_time'], 0, 5) : '') ?>">
                                    </label>
                                    <label>Venue
                                        <input type="text" name="screening_venue" maxlength="180" value="<?= e($application['screening_venue'] ?? '') ?>" placeholder="Enter screening venue">
                                    </label>
                                    <label>Attendance
                                        <select name="screening_attendance">
                                            <?php foreach (['Pending', 'Present', 'Absent'] as $attendance): ?>
                                                <option value="<?= e($attendance) ?>" <?= ($application['screening_attendance'] ?? 'Pending') === $attendance ? 'selected' : '' ?>><?= e($attendance) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </label>
                                </fieldset>

                                <label>Remarks
                                    <textarea name="remarks" rows="2" placeholder="Review or screening remarks"><?= e($application['remarks'] ?? '') ?></textarea>
                                </label>
                                <button type="submit" class="button button-primary">Save Review</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
