<?php
require_once __DIR__ . '/includes/bootstrap.php';
if (current_user()) {
    redirect(dashboard_path_for_role(current_user()['role_name']));
}
$pageTitle = 'Home';
require_once __DIR__ . '/includes/header.php';
?>
<style>
    /* Match the hero section's width, gradient, and rounded-card treatment. */
    .enrollment-requirements {
        width: 100%;
        box-sizing: border-box;
        margin: 32px 0 0;
        padding: clamp(28px, 4vw, 48px);
        border: 1px solid rgba(226, 232, 240, .9);
        border-radius: 24px;
        background: linear-gradient(130deg, #fff 0%, #fff 55%, #f5eaf0 100%);
        box-shadow: var(--shadow, 0 12px 32px rgba(15, 23, 42, .08));
    }

    .requirements-heading {
        margin: 0 0 28px;
        text-align: left;
    }

    .requirements-heading .eyebrow {
        margin-bottom: 8px;
    }

    .requirements-heading h2 {
        margin: 8px 0 10px;
        color: var(--maroon-dark, #50152b);
        font-size: clamp(1.7rem, 2.5vw, 2.15rem);
        line-height: 1.2;
    }

    .requirements-heading > p:last-child {
        margin: 0;
        color: var(--muted, #64748b);
    }

    .requirements-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        grid-auto-rows: 1fr;
        gap: 20px;
        align-items: stretch;
    }

    .requirement-card {
        display: flex;
        flex-direction: column;
        box-sizing: border-box;
        min-width: 0;
        height: 100%;
        padding: 26px;
        border: 1px solid rgba(226, 232, 240, .95);
        border-radius: 16px;
        background: #fff;
        box-shadow: 0 6px 18px rgba(15, 23, 42, .05);
    }

    .requirement-card h3 {
        display: flex;
        align-items: center;
        min-height: 2.8em;
        margin: 0 0 16px;
        color: var(--maroon, #651b35);
        font-size: 1.18rem;
        line-height: 1.35;
    }

    .requirement-card ul {
        margin: 0;
        padding-left: 20px;
        color: var(--text, #374151);
    }

    .requirement-card li {
        margin: 0 0 13px;
        line-height: 1.6;
    }

    .requirement-card li:last-child {
        margin-bottom: 0;
    }

    @media (max-width: 900px) {
        .requirements-grid {
            grid-template-columns: 1fr;
            grid-auto-rows: auto;
        }

        .requirement-card {
            height: auto;
        }

        .requirement-card h3 {
            min-height: 0;
        }
    }

    @media (max-width: 520px) {
        .enrollment-requirements {
            margin-top: 24px;
            padding: 22px;
            border-radius: 18px;
        }

        .requirement-card {
            padding: 22px;
        }
    }
</style>

<section class="hero">
    <div class="hero-copy">
        <p class="eyebrow">Student Services Portal</p>
        <h1>Welcome to Baguio City National High School</h1>
        <p class="hero-lead">A centralized portal for student enrollment, student information, and special program scholarship applications.</p>
        <div class="hero-actions">
            <a class="button button-primary" href="<?= BASE_URL ?>/register.php">Create Student Account</a>
            <a class="button button-outline" href="<?= BASE_URL ?>/login.php">Student Login</a>
        </div>
    </div>
    <div class="hero-panel">
        <h2>Student Portal</h2>
        <p>Use your account to submit enrollment information, check application progress, and view school announcements.</p>
        <ul>
            <li>Student enrollment applications</li>
            <li>Independent scholarship application status</li>
            <li>Student profile and assigned subjects</li>
        </ul>
    </div>
</section>

<section class="enrollment-requirements" aria-labelledby="enrollmentRequirementsTitle">
    <div class="requirements-heading">
        <p class="eyebrow">Enrollment Guide</p>
        <h2 id="enrollmentRequirementsTitle">Enrollment Requirements</h2>
        <p>Prepare the documents listed for your student category before proceeding with enrollment.</p>
    </div>

    <div class="requirements-grid">
        <article class="requirement-card">
            <h3>Incoming Grade 7</h3>
            <ul>
                <li>PSA Birth Certificate (photocopy)</li>
                <li>School Form 138 (Form 138 / Report Card)</li>
                <li>Good Moral Certificate</li>
            </ul>
        </article>

        <article class="requirement-card">
            <h3>Returning Students</h3>
            <ul>
                <li>Previous school ID</li>
                <li>Most recent School Form 138 (Form 138 / Report Card)</li>
                <li>Good Moral Certificate</li>
            </ul>
        </article>

        <article class="requirement-card">
            <h3>Transferees</h3>
            <ul>
                <li>PSA Birth Certificate (photocopy)</li>
                <li>School Form 137 (Permanent Record)</li>
                <li>Latest School Form 138 (Report Card)</li>
                <li>Good Moral Certificate</li>
            </ul>
        </article>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
