</main>
<footer class="site-footer">
    <p>&copy; <?= date('Y') ?> Baguio City National High School. All rights reserved.</p>
</footer>
<?php if (current_user()): ?>
    <dialog class="portal-delete-dialog" id="portalDeleteDialog" aria-labelledby="portalDeleteTitle" aria-describedby="portalDeleteMessage">
        <div class="logout-modal-heading">
            <div class="logout-modal-icon">
                <img src="<?= BASE_URL ?>/assets/images/school-logo.png" alt="">
            </div>
            <div>
                <h2 id="portalDeleteTitle">Confirm removal</h2>
                <p>Baguio City National High School Portal</p>
            </div>
        </div>
        <p id="portalDeleteMessage"></p>
        <form method="dialog" class="portal-delete-actions">
            <button class="button button-outline" value="cancel" autofocus>Cancel</button>
            <button class="button button-danger portal-record-action" id="portalDeleteConfirm" value="confirm">Delete</button>
        </form>
    </dialog>
    <script src="<?= BASE_URL ?>/assets/js/delete_confirmation.js" defer></script>
<?php endif; ?>
<script src="<?= BASE_URL ?>/assets/js/responsive_tables.js" defer></script>
<script src="<?= BASE_URL ?>/assets/js/print_report.js" defer></script>
</body>
</html>
