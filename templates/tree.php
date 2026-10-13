<?php
/**
 * Layout "Tree": collapsible category groups, any number of levels deep.
 *
 * Override it by copying this file to {theme}/crnube-docs/tree.php.
 *
 * @var \CRNube\Docs\Frontend\View $view
 */

defined('ABSPATH') || exit;

$crnd_tree = $view->tree;

$view->part('library-open');

if (!$crnd_tree->isEmpty()) : ?>
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
<?php endif;

$view->part('library-close');
