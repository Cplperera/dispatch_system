<?php
/**
 * Shared footer — prev/next nav, mark-as-read, scripts.
 * Requires: $page, $manualSections
 */
if (!function_exists('manual_prev_next')) {
    require_once __DIR__ . '/_config.php';
}
[$prev, $next] = manual_prev_next($manualSections, $page['slug']);
?>

    <!-- Mark as read -->
    <button class="m-mark-read" id="markRead" data-page="<?= htmlspecialchars($page['slug']) ?>" type="button">
        <i class="fa-regular fa-circle-check"></i> Mark as Read
    </button>

    <!-- Prev / Next -->
    <div class="m-page-nav">
        <?php if ($prev): ?>
            <a href="<?= htmlspecialchars($prev['slug']) ?>.php" class="prev">
                <span class="lbl">← Previous</span>
                <span class="ttl"><?= htmlspecialchars($prev['title']) ?></span>
            </a>
        <?php else: ?><span></span><?php endif; ?>

        <?php if ($next): ?>
            <a href="<?= htmlspecialchars($next['slug']) ?>.php" class="next">
                <span class="lbl">Next →</span>
                <span class="ttl"><?= htmlspecialchars($next['title']) ?></span>
            </a>
        <?php endif; ?>
    </div>

</main>

<button class="m-mobile-toggle" id="mobileToggle" type="button">
    <i class="fa-solid fa-bars"></i>
</button>

<script src="../assets/manual.js"></script>
</body>
</html>