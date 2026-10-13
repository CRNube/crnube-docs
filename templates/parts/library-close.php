<?php
/**
 * Closing of every layout (see parts/library-open.php).
 *
 * @var \CRNube\Docs\Frontend\View $view
 */

defined('ABSPATH') || exit;
?>
    <?php if (!$view->tree->isEmpty()) : ?>
        <p class="crnd-no-results" data-crnd-no-results hidden><?php echo esc_html($view->label('empty')); ?></p>
    <?php endif; ?>
</div>
