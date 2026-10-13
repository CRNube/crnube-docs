<?php
/**
 * Layout "Tree": collapsible category groups, any number of levels deep.
 *
 * Override it by copying this file to {theme}/crnube-docs/tree.php.
 *
 * @var \CRNube\Docs\Frontend\View $view
 */

defined('ABSPATH') || exit;

$crnd_tree  = $view->tree;
$crnd_style = $view->wrapperStyle();
?>
<div id="<?php echo esc_attr($view->id); ?>"
    class="<?php echo esc_attr($view->wrapperClass()); ?>"
    <?php if ($crnd_style !== '') : ?>style="<?php echo esc_attr($crnd_style); ?>"<?php endif; ?>
    data-crnd-library>

    <?php if ($view->option('title')) : ?>
        <h2 class="crnd-title"><?php echo esc_html($view->option('title')); ?></h2>
    <?php endif; ?>

    <?php if ($crnd_tree->isEmpty()) : ?>
        <p class="crnd-empty"><?php esc_html_e('There are no documents published here yet.', 'crnube-docs'); ?></p>
    <?php else : ?>

        <?php if ($view->hasToolbar()) : ?>
            <?php $view->part('toolbar'); ?>
        <?php endif; ?>

        <?php if ($crnd_tree->documents !== []) : ?>
            <ul class="crnd-list crnd-list--root" role="list">
                <?php foreach ($crnd_tree->documents as $crnd_document) : ?>
                    <?php $view->part('document', ['document' => $crnd_document]); ?>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <?php if ($crnd_tree->groups !== []) : ?>
            <div class="crnd-groups">
                <?php foreach ($crnd_tree->groups as $crnd_index => $crnd_node) : ?>
                    <?php $view->part('group', ['node' => $crnd_node, 'level' => 1, 'index' => $crnd_index]); ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <p class="crnd-no-results" data-crnd-no-results hidden><?php echo esc_html($view->label('empty')); ?></p>
    <?php endif; ?>
</div>
