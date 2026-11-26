<nav class="registrar-list-pagination" aria-label="<?= e($paginationLabel) ?>">
    <span>Page <?= number_format($page) ?> of <?= number_format($totalPages) ?></span>
    <?php if ($page > 1): ?>
        <a class="button button-outline" href="<?= e($paginationUrl($page - 1)) ?>">Previous page</a>
    <?php endif; ?>
    <?php if ($page < $totalPages): ?>
        <a class="button button-outline" href="<?= e($paginationUrl($page + 1)) ?>">Next page</a>
    <?php endif; ?>
    <?php if ($showPageJump): ?>
        <form method="get" class="registrar-page-jump">
            <?php foreach ($queryParameters as $key => $value): ?>
                <input type="hidden" name="<?= e($key) ?>" value="<?= e((string) $value) ?>">
            <?php endforeach; ?>
            <label for="registrar-page-number">Go to page</label>
            <input type="number" id="registrar-page-number" name="page"
                   min="1" max="<?= $totalPages ?>" value="<?= $page ?>" required>
            <button type="submit" class="button button-outline">Go</button>
        </form>
    <?php endif; ?>
</nav>
