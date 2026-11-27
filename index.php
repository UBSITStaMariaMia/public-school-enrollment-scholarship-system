<?php
require_once __DIR__ . '/includes/bootstrap.php';

if (current_user()) {
    redirect(dashboard_path_for_role(current_user()['role_name']));
}

$error = '';
$identifier = '';

/* Process login submissions on the landing page. */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedIdentifier = $_POST['identifier'] ?? '';
    $password = $_POST['password'] ?? '';
    $submittedToken = $_POST['csrf_token'] ?? null;

    if (
        !is_string($submittedIdentifier) ||
        !is_string($password)
    ) {
        $error = 'Invalid username/email or password.';
    } else {
        $identifier = trim($submittedIdentifier);

        if ($identifier === '' || $password === '') {
            $error = 'Please enter your username/email and password.';
        } elseif (!is_string($submittedToken) || !verify_csrf_token($submittedToken)) {
            $error = 'Your session expired. Please try again.';
        } else {
            try {
                $statement = $pdo->prepare(
                    'SELECT
                        u.user_id,
                        u.username,
                        u.email,
                        u.password_hash,
                        u.account_status,
                        r.role_name
                     FROM users u
                     INNER JOIN roles r ON r.role_id = u.role_id
                     WHERE u.username = :username
                        OR u.email = :email
                     LIMIT 1'
                );

                $statement->execute([
                    'username' => $identifier,
                    'email' => $identifier
                ]);

                $account = $statement->fetch(PDO::FETCH_ASSOC);

                if (
                    $account &&
                    $account['account_status'] === 'Active' &&
                    password_verify($password, $account['password_hash'])
                ) {
                    if (password_needs_rehash($account['password_hash'], PASSWORD_DEFAULT)) {
                        $newHash = password_hash($password, PASSWORD_DEFAULT);
                        $updateStatement = $pdo->prepare(
                            'UPDATE users
                             SET password_hash = :password_hash
                             WHERE user_id = :user_id'
                        );
                        $updateStatement->execute([
                            'password_hash' => $newHash,
                            'user_id' => $account['user_id']
                        ]);
                    }

                    session_regenerate_id(true);

                    $_SESSION['user'] = [
                        'user_id' => (int) $account['user_id'],
                        'username' => $account['username'],
                        'email' => $account['email'],
                        'role_name' => $account['role_name'],
                    ];

                    redirect(dashboard_path_for_role($account['role_name']));
                }

                $error = 'Invalid username/email or password.';
            } catch (PDOException $exception) {
                error_log('Login database error: ' . $exception->getMessage());
                http_response_code(500);
                $error = 'An unexpected error occurred. Please try again.';
            }
        }
    }
}

/* Retrieve the latest published announcements, including future-dated posts.
 * Drafts, archived posts, and expired announcements remain hidden.
 */
$announcements = [];

try {
    $announcementStatement = $pdo->prepare(
        "SELECT title, content, announcement_type, publish_date
         FROM announcements
         WHERE status = 'Published'
           AND publish_date <= (UTC_TIMESTAMP() + INTERVAL 8 HOUR)
           AND (expiry_date IS NULL OR expiry_date >= (UTC_TIMESTAMP() + INTERVAL 8 HOUR))
         ORDER BY publish_date DESC
         LIMIT 5"
    );
    $announcementStatement->execute();
    $announcements = $announcementStatement->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $exception) {
    error_log('Landing page announcements error: ' . $exception->getMessage());
}

$pageTitle = 'Log In';
require_once __DIR__ . '/includes/header.php';
?>

<section class="login-landing login-announcements-layout" aria-label="BCNHS portal access">
    <section class="login-panel" aria-labelledby="loginTitle">
        <div class="login-panel-heading">
            <h1 id="loginTitle">Portal Login</h1>
            <p>Baguio City National High School</p>
        </div>

        <?php if ($error !== ''): ?>
            <div class="alert alert-error" role="alert">
                <?= e($error) ?>
            </div>
        <?php endif; ?>

        <form method="post" action="<?= BASE_URL ?>/index.php" class="form-grid">
            <input
                type="hidden"
                name="csrf_token"
                value="<?= e(csrf_token()) ?>"
            >

            <label>
                Username or Email
                <input
                    type="text"
                    name="identifier"
                    value="<?= e($identifier) ?>"
                    required
                    autocomplete="username"
                    autofocus
                >
            </label>

            <label>
                Password
                <input
                    type="password"
                    name="password"
                    required
                    autocomplete="current-password"
                >
            </label>

            <button class="button button-primary button-full" type="submit">
                Log In
            </button>
        </form>

        <p class="login-register-link">
            Don't have a student account?
            <a href="<?= BASE_URL ?>/register.php">How to Register?</a>
        </p>
    </section>

    <section class="landing-announcements" aria-labelledby="landingAnnouncementsTitle">
        <div class="landing-announcements-heading">
            <h2 id="landingAnnouncementsTitle">Public Announcements</h2>
            <p>Keep up with the latest published school announcements and important updates.</p>
        </div>

        <?php if ($announcements): ?>
            <div class="landing-announcements-list">
                <?php foreach ($announcements as $announcement): ?>
                    <article class="landing-announcement-card">
                        <div class="landing-announcement-meta">
                            <span><?= e($announcement['announcement_type']) ?></span>
                            <time datetime="<?= e(date('c', strtotime($announcement['publish_date']))) ?>">
                                <?= e(date('M d, Y', strtotime($announcement['publish_date']))) ?>
                            </time>
                        </div>
                        <h3><?= e($announcement['title']) ?></h3>
                        <p><?= nl2br(e($announcement['content'])) ?></p>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="landing-announcements-empty">
                <img
                    class="announcement-icon"
                    src="<?= BASE_URL ?>/assets/images/promotion.png"
                    alt=""
                    aria-hidden="true"
                >
                <h3>No announcements available</h3>
                <p>There are currently no published school announcements.</p>
            </div>
        <?php endif; ?>

        <a class="landing-announcements-button" href="<?= BASE_URL ?>/announcements.php">
            View all announcements
        </a>
    </section>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
