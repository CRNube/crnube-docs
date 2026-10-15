<?php
/**
 * Layout "List": one flat list of every document, each tagged with its category, with
 * search, year and category filters on top.
 *
 * Override it by copying this file to {theme}/crnube-docs/list.php.
 *
 * @var \CRNube\Docs\Frontend\View $view
 */

defined('ABSPATH') || exit;

$view->part('library-open');

if (!$view->tree->isEmpty()) : ?>
    <ul class="crnd-list crnd-list--flat" role="list">
        <?php foreach ($view->flatDocuments() as $crnd_item) : ?>
            <?php
            $view->part('document', [
                'document'   => $crnd_item['document'],
                'categories' => $crnd_item['categories'],
                'labels'     => $crnd_item['labels'],
            ]);
            ?>
        <?php endforeach; ?>
    </ul>
<?php endif;

$view->part('library-close');
