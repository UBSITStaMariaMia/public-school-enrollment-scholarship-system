<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

require_role('School Registrar');

$pageTitle = 'Announcement Management';

$errors = [];
$success = '';

$announcementTypes = [
    'Enrollment' => 'Enrollment Announcements',
    'Scholarship' => 'Scholarship Application Deadlines',
    'School' => 'School Announcements',
    'General' => 'General School Notices'
];

$allowedStatuses = ['Draft', 'Published', 'Archived'];

$values = [
    'announcement_id' => '',
    'title' => '',
    'content' => '',
    'announcement_type' => 'School',
    'publish_date' => date('Y-m-d\TH:i'),
    'expiry_date' => '',
    'status' => 'Draft'
];

/* Handle form submissions */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Your session expired. Please refresh the page and try again.';
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'save') {

        $values['announcement_id'] = trim($_POST['announcement_id'] ?? '');
        $values['title'] = trim($_POST['title'] ?? '');
        $values['content'] = trim($_POST['content'] ?? '');
        $values['announcement_type'] = trim($_POST['announcement_type'] ?? '');
        $values['publish_date'] = trim($_POST['publish_date'] ?? '');
        $values['expiry_date'] = trim($_POST['expiry_date'] ?? '');
        $values['status'] = trim($_POST['status'] ?? '');

        /* Validate title */
        if ($values['title'] === '') {
            $errors[] = 'Announcement title is required.';
        } elseif (mb_strlen($values['title']) > 200) {
            $errors[] = 'Announcement title cannot exceed 200 characters.';
        }

        /* Validate content */
        if ($values['content'] === '') {
            $errors[] = 'Announcement content is required.';
        }

        /* Validate category */
        if (!array_key_exists($values['announcement_type'], $announcementTypes)) {
            $errors[] = 'Select a valid announcement category.';
        }

        /* Validate status */
        if (!in_array($values['status'], $allowedStatuses, true)) {
            $errors[] = 'Select a valid announcement status.';
        }

        /* Validate publication date */
        $publishDate = DateTime::createFromFormat(
            '!Y-m-d\TH:i',
            $values['publish_date']
        );

        if (
            !$publishDate ||
            $publishDate->format('Y-m-d\TH:i') !== $values['publish_date']
        ) {
            $errors[] = 'Enter a valid publication date.';
        }

        /* Validate optional expiry date */
        $expiryDate = null;

        if ($values['expiry_date'] !== '') {

            $expiryDate = DateTime::createFromFormat(
                '!Y-m-d\TH:i',
                $values['expiry_date']
            );

            if (
                !$expiryDate ||
                $expiryDate->format('Y-m-d\TH:i') !== $values['expiry_date']
            ) {
                $errors[] = 'Enter a valid expiry date.';
            } elseif ($publishDate && $expiryDate < $publishDate) {
                $errors[] = 'Expiry date cannot be earlier than the publication date.';
            }
        }

        /* Save announcement */
        if (!$errors) {

            try {

                $publishDateValue = $publishDate->format('Y-m-d H:i:s');

                $expiryDateValue = $expiryDate
                    ? $expiryDate->format('Y-m-d H:i:s')
                    : null;

                if ($values['announcement_id'] !== '') {

                    $announcementId = filter_var(
                        $values['announcement_id'],
                        FILTER_VALIDATE_INT
                    );

                    if (!$announcementId || $announcementId <= 0) {
                        throw new RuntimeException('Invalid announcement ID.');
                    }

                    $updateQuery = $pdo->prepare(
                        'UPDATE announcements
                         SET title = :title,
                             content = :content,
                             announcement_type = :announcement_type,
                             publish_date = :publish_date,
                             expiry_date = :expiry_date,
                             status = :status
                         WHERE announcement_id = :announcement_id'
                    );

                    $updateQuery->execute([
                        'title' => $values['title'],
                        'content' => $values['content'],
                        'announcement_type' => $values['announcement_type'],
                        'publish_date' => $publishDateValue,
                        'expiry_date' => $expiryDateValue,
                        'status' => $values['status'],
                        'announcement_id' => $announcementId
                    ]);

                } else {

                    $insertQuery = $pdo->prepare(
                        'INSERT INTO announcements (
                            title,
                            content,
                            announcement_type,
                            publish_date,
                            expiry_date,
                            status,
                            created_by
                        ) VALUES (
                            :title,
                            :content,
                            :announcement_type,
                            :publish_date,
                            :expiry_date,
                            :status,
                            :created_by
                        )'
                    );

                    $insertQuery->execute([
                        'title' => $values['title'],
                        'content' => $values['content'],
                        'announcement_type' => $values['announcement_type'],
                        'publish_date' => $publishDateValue,
                        'expiry_date' => $expiryDateValue,
                        'status' => $values['status'],
                        'created_by' => current_user()['user_id']
                    ]);
                }

                redirect('/registrar/announcements.php?saved=1');

            } catch (Throwable $exception) {

                error_log(
                    'Announcement save error: ' .
                    $exception->getMessage()
                );

                $errors[] = 'The announcement could not be saved. Please try again.';
            }
        }
    }

    /* Delete announcement */
    if ($action === 'delete' && !$errors) {

        $announcementId = filter_var(
            $_POST['announcement_id'] ?? null,
            FILTER_VALIDATE_INT
        );

        if (!$announcementId || $announcementId <= 0) {

            $errors[] = 'Invalid announcement selected.';

        } else {

            try {

                $deleteQuery = $pdo->prepare(
                    'DELETE FROM announcements
                     WHERE announcement_id = :announcement_id'
                );

                $deleteQuery->execute([
                    'announcement_id' => $announcementId
                ]);

                redirect('/registrar/announcements.php?deleted=1');

            } catch (Throwable $exception) {

                error_log(
                    'Announcement deletion error: ' .
                    $exception->getMessage()
                );

                $errors[] = 'The announcement could not be deleted.';
            }
        }
    }
}

/* Display success messages */
if (isset($_GET['saved'])) {
    $success = 'Announcement saved successfully.';
}

if (isset($_GET['deleted'])) {
    $success = 'Announcement deleted successfully.';
}

/* Load announcement for editing */
if (
    $_SERVER['REQUEST_METHOD'] !== 'POST' &&
    isset($_GET['edit'])
) {

    $editId = filter_var($_GET['edit'], FILTER_VALIDATE_INT);

    if ($editId && $editId > 0) {

        $editQuery = $pdo->prepare(
            'SELECT *
             FROM announcements
             WHERE announcement_id = :announcement_id
             LIMIT 1'
        );

        $editQuery->execute([
            'announcement_id' => $editId
        ]);

        $announcement = $editQuery->fetch();

        if ($announcement) {

            $values = [
                'announcement_id' => (string) $announcement['announcement_id'],
                'title' => $announcement['title'],
                'content' => $announcement['content'],
                'announcement_type' => $announcement['announcement_type'],
                'publish_date' => date(
                    'Y-m-d\TH:i',
                    strtotime($announcement['publish_date'])
                ),
                'expiry_date' => $announcement['expiry_date']
                    ? date('Y-m-d\TH:i', strtotime($announcement['expiry_date']))
                    : '',
                'status' => $announcement['status']
            ];

        } else {
            $errors[] = 'Announcement not found.';
        }
    }
}

/* Get all announcements */
$announcementQuery = $pdo->query(
    'SELECT
        a.announcement_id,
        a.title,
        a.content,
        a.announcement_type,
        a.publish_date,
        a.expiry_date,
        a.status,
        u.username AS creator_name
     FROM announcements a
     LEFT JOIN users u
        ON a.created_by = u.user_id
     ORDER BY a.publish_date DESC, a.announcement_id DESC'
);

$announcements = $announcementQuery->fetchAll();

/* Count announcements by status */
$statusCounts = [
    'Published' => 0,
    'Draft' => 0,
    'Archived' => 0
];

foreach ($announcements as $announcement) {
    if (isset($statusCounts[$announcement['status']])) {
        $statusCounts[$announcement['status']]++;
    }
}

require_once __DIR__ . '/../includes/header.php';
?>



<section class="form-card form-card-wide announcement-management">

    <div>
        <p class="eyebrow">School Administration</p>
        <h1>Announcement Management</h1>

        <p class="muted">
            Create, edit, publish, and manage enrollment announcements,
            scholarship application deadlines, and school announcements.
        </p>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success">
            <?= e($success) ?>
        </div>
    <?php endif; ?>

    <?php foreach ($errors as $error): ?>
        <div class="alert alert-error">
            <?= e($error) ?>
        </div>
    <?php endforeach; ?>

    <div class="announcement-stats">

        <div class="announcement-stat">
            <small>Published Announcements</small>
            <strong><?= $statusCounts['Published'] ?></strong>
        </div>

        <div class="announcement-stat">
            <small>Draft Announcements</small>
            <strong><?= $statusCounts['Draft'] ?></strong>
        </div>

        <div class="announcement-stat">
            <small>Archived Announcements</small>
            <strong><?= $statusCounts['Archived'] ?></strong>
        </div>

    </div>

    <div class="announcement-form-card">

        <h2>
            <?= $values['announcement_id'] !== ''
                ? 'Edit Announcement'
                : 'Create New Announcement' ?>
        </h2>

        <form method="post" class="announcement-form">

            <input
                type="hidden"
                name="csrf_token"
                value="<?= e(csrf_token()) ?>"
            >

            <input
                type="hidden"
                name="action"
                value="save"
            >

            <input
                type="hidden"
                name="announcement_id"
                value="<?= e($values['announcement_id']) ?>"
            >

            <label>
                Announcement Title

                <input
                    type="text"
                    name="title"
                    maxlength="200"
                    placeholder="Enter announcement title"
                    value="<?= e($values['title']) ?>"
                    required
                >
            </label>

            <label>
                Announcement Category

                <select name="announcement_type" required>

                    <?php foreach ($announcementTypes as $type => $label): ?>
                        <option
                            value="<?= e($type) ?>"
                            <?= $values['announcement_type'] === $type ? 'selected' : '' ?>
                        >
                            <?= e($label) ?>
                        </option>
                    <?php endforeach; ?>

                </select>
            </label>

            <label>
                Announcement Details

                <textarea
                    name="content"
                    placeholder="Write the announcement details here..."
                    required
                ><?= e($values['content']) ?></textarea>
            </label>

            <label>
                Publication Date

                <input
                    type="datetime-local"
                    name="publish_date"
                    value="<?= e($values['publish_date']) ?>"
                    required
                >
            </label>

            <label>
                Expiry Date (Optional)

                <input
                    type="datetime-local"
                    name="expiry_date"
                    value="<?= e($values['expiry_date']) ?>"
                >
            </label>

            <label>
                Announcement Status

                <select name="status" required>

                    <?php foreach ($allowedStatuses as $status): ?>
                        <option
                            value="<?= e($status) ?>"
                            <?= $values['status'] === $status ? 'selected' : '' ?>
                        >
                            <?= e($status) ?>
                        </option>
                    <?php endforeach; ?>

                </select>
            </label>

            <div class="announcement-form-actions">

                <button
                    type="submit"
                    class="button button-primary"
                >
                    <?= $values['announcement_id'] !== ''
                        ? 'Save Changes'
                        : 'Create Announcement' ?>
                </button>

                <?php if ($values['announcement_id'] !== ''): ?>
                    <a
                        href="<?= BASE_URL ?>/registrar/announcements.php"
                        class="button button-outline"
                    >
                        Cancel Editing
                    </a>
                <?php endif; ?>

            </div>

        </form>

    </div>

    <div class="announcement-list-card">

        <h2>All Announcements</h2>

        <p class="muted">
            View and manage all created announcements, including drafts and archived posts.
        </p>

        <?php if (empty($announcements)): ?>

            <div class="announcement-empty">
                <h3>No Announcements Yet</h3>
                <p>Create your first announcement using the form above.</p>
            </div>

        <?php else: ?>

            <div class="announcement-table-wrapper">

                <table class="announcement-table">

                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Category</th>
                            <th>Publication Date</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php foreach ($announcements as $announcement): ?>

                            <tr>

                                <td>
                                    <strong>
                                        <?= e($announcement['title']) ?>
                                    </strong>
                                </td>

                                <td>
                                    <span class="announcement-category">
                                        <?= e($announcementTypes[$announcement['announcement_type']] ?? $announcement['announcement_type']) ?>
                                    </span>
                                </td>

                                <td>
                                    <?= date(
                                        'M d, Y h:i A',
                                        strtotime($announcement['publish_date'])
                                    ) ?>
                                </td>

                                <td>
                                    <span class="announcement-status status-<?= strtolower(e($announcement['status'])) ?>">
                                        <?= e($announcement['status']) ?>
                                    </span>
                                </td>

                                <td>
                                    <div class="announcement-actions">

                                        <a
                                            href="<?= BASE_URL ?>/registrar/announcements.php?edit=<?= (int) $announcement['announcement_id'] ?>"
                                            class="button button-outline"
                                        >
                                            Edit
                                        </a>

                                        <form
                                            method="post"
                                            onsubmit="return confirm('Are you sure you want to delete this announcement?');"
                                        >

                                            <input
                                                type="hidden"
                                                name="csrf_token"
                                                value="<?= e(csrf_token()) ?>"
                                            >

                                            <input
                                                type="hidden"
                                                name="action"
                                                value="delete"
                                            >

                                            <input
                                                type="hidden"
                                                name="announcement_id"
                                                value="<?= (int) $announcement['announcement_id'] ?>"
                                            >

                                            <button
                                                type="submit"
                                                class="button button-danger"
                                            >
                                                Delete
                                            </button>

                                        </form>

                                    </div>
                                </td>

                            </tr>

                        <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        <?php endif; ?>

    </div>

</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
