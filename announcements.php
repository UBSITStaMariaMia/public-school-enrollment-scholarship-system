<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

$pageTitle = 'Announcements';

/*
 * Show all published announcements, including future-dated posts.
 * Drafts, archived posts, and expired announcements remain hidden.
 */
$announcements = [];

try {
    $announcementQuery = $pdo->prepare(
        "SELECT
            title,
            content,
            announcement_type,
            publish_date
         FROM announcements
         WHERE status = 'Published'
           AND publish_date <= (UTC_TIMESTAMP() + INTERVAL 8 HOUR)
           AND (expiry_date IS NULL OR expiry_date >= (UTC_TIMESTAMP() + INTERVAL 8 HOUR))
         ORDER BY publish_date DESC, announcement_id DESC"
    );

    $announcementQuery->execute();
    $announcements = $announcementQuery->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $exception) {
    error_log('Public announcements page error: ' . $exception->getMessage());
    http_response_code(500);
}

require_once __DIR__ . '/includes/header.php';
?>

<style>
    /*
     * Match the landing page's full-height layout so the shared footer
     * stays at the bottom of the viewport when the page has little content.
     */
    html,
    body {
        min-height: 100%;
    }

    body {
        min-height: 100vh;
        display: flex;
        flex-direction: column;
    }

    /*
     * header.php opens this shared page container.
     * Let it expand and push the footer to the bottom.
     */
    body > .page-container {
        flex: 1 0 auto;
        width: 100%;
        display: flex;
        flex-direction: column;
    }

    .public-announcements-page {
        width: min(100% - 32px, 920px);
        margin: 38px auto;
        flex: 1 0 auto;
    }

    .public-announcements-card {
        background: #fff;
        border: 1px solid #e1e5eb;
        border-radius: 14px;
        padding: 28px;
        box-shadow: 0 12px 30px rgba(20, 30, 50, 0.08);
    }

    .public-announcements-back {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 22px;
        padding: 10px 16px;
        border: 1px solid #7b1e3a;
        border-radius: 7px;
        color: #7b1e3a;
        background: #fff;
        font-weight: 600;
        text-decoration: none;
        transition: background-color 0.2s ease, color 0.2s ease;
    }

    .public-announcements-back:hover {
        color: #fff;
        background: #7b1e3a;
    }

    .public-announcements-eyebrow {
        margin: 0 0 8px;
        color: #176b50;
        font-size: 11px;
        font-weight: 800;
        letter-spacing: 1.4px;
        text-transform: uppercase;
    }

    .public-announcements-title {
        margin: 0 0 10px;
        color: #681b35;
        font-size: clamp(26px, 4vw, 34px);
    }

    .public-announcements-intro {
        margin: 0 0 24px;
        color: #596273;
        line-height: 1.6;
    }

    .public-announcements-list {
        display: grid;
        gap: 16px;
    }

    .public-announcement-item {
        padding: 20px;
        border: 1px solid #e4e7ec;
        border-left: 4px solid #7b1e3a;
        border-radius: 9px;
        background: #fff;
    }

    .public-announcement-meta {
        display: flex;
        flex-wrap: wrap;
        justify-content: space-between;
        gap: 8px 16px;
        margin-bottom: 10px;
        color: #687386;
        font-size: 12px;
    }

    .public-announcement-category {
        color: #7b1e3a;
        font-weight: 700;
    }

    .public-announcement-item h2 {
        margin: 0 0 10px;
        color: #681b35;
        font-size: 20px;
    }

    .public-announcement-content {
        margin: 0;
        color: #333b49;
        line-height: 1.7;
        overflow-wrap: anywhere;
    }

    .public-announcements-empty {
        padding: 30px 20px;
        border: 1px dashed #ccd2dc;
        border-radius: 9px;
        text-align: center;
        color: #596273;
    }

    .public-announcements-empty h2 {
        margin: 0 0 8px;
        color: #681b35;
        font-size: 19px;
    }

    @media (max-width: 600px) {
        .public-announcements-page {
            width: min(100% - 20px, 920px);
            margin: 20px auto;
        }

        .public-announcements-card {
            padding: 18px;
        }

        .public-announcement-item {
            padding: 16px;
        }
    }
</style>

<div class="public-announcements-page">
    <div class="portal-back-row">
    <a class="portal-back-link" href="<?= BASE_URL ?>/index.php">
        Back to Login
    </a>
    </div>

    <section class="public-announcements-card" aria-labelledby="publicAnnouncementsTitle">
        <h1 class="public-announcements-title" id="publicAnnouncementsTitle">
            Public Announcements
        </h1>
        <p class="public-announcements-intro">
            Stay updated with the latest published announcements from
            Baguio City National High School.
        </p>

        <?php if (!empty($announcements)): ?>
            <div class="public-announcements-list">
                <?php foreach ($announcements as $announcement): ?>
                    <article class="public-announcement-item">
                        <div class="public-announcement-meta">
                            <span class="public-announcement-category">
                                <?= e($announcement['announcement_type']) ?>
                            </span>
                            <time datetime="<?= e(date('c', strtotime($announcement['publish_date']))) ?>">
                                <?= e(date('M d, Y \a\t h:i A', strtotime($announcement['publish_date']))) ?>
                            </time>
                        </div>

                        <h2><?= e($announcement['title']) ?></h2>

                        <p class="public-announcement-content">
                            <?= nl2br(e($announcement['content'])) ?>
                        </p>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="public-announcements-empty">
                <h2>No Announcements Available</h2>
                <p>
                    There are currently no published announcements.
                    Please check again later.
                </p>
            </div>
        <?php endif; ?>
    </section>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
