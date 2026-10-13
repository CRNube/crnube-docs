<?php
/**
 * Opening of every layout: the wrapper, the title and the toolbar. Closed by
 * parts/library-close.php.
 *
 * @var \CRNube\Docs\Frontend\View $view
 */

defined('ABSPATH') || exit;

$crnd_style = $view->wrapperStyle();
?>
<div id="<?php echo esc_attr($view->id); ?>"
    class="<?php echo esc_attr($view->wrapperClass()); ?>"
    <?php if ($crnd_style !== '') : ?>style="<?php echo esc_attr($crnd_style); ?>"<?php endif; ?>
    data-crnd-library>

    <?php if ($view->option('title')) : ?>
        <h2 class="crnd-title"><?php echo esc_html($view->option('title')); ?></h2>
    <?php endif; ?>

    <?php if ($view->tree->isEmpty()) : ?>
        <p class="crnd-empty"><?php esc_html_e('There are no documents published here yet.', 'crnube-docs'); ?></p>
    <?php elseif ($view->hasToolbar()) : ?>
        <?php $view->part('toolbar'); ?>
    <?php endif; ?>
