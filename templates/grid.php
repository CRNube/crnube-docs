<?php
/**
 * Layout "Grid": every document as a card, under one heading per category (with its
 * full path, e.g. "Estados financieros › Auditados"). Columns: the `columns` setting.
 *
 * Override it by copying this file to {theme}/crnube-docs/grid.php.
 *
 * @var \CRNube\Docs\Frontend\View $view
 */

defined('ABSPATH') || exit;

$view->part('library-open');

if (!$view->tree->isEmpty()) : ?>
    <?php foreach ($view->flatGroups() as $crnd_group) : ?>
        <section class="crnd-gsection" data-crnd-group>
            <?php if ($crnd_group['path'] !== '') : ?>
                <header class="crnd-gsection__header">
                    <h3 class="crnd-gsection__title" data-crnd-group-title><?php echo esc_html($crnd_group['path']); ?></h3>
                    <?php if ($view->option('counts')) : ?>
                        <span class="crnd-badge" data-crnd-count data-total="<?php echo (int) count($crnd_group['node']->documents); ?>"><?php echo (int) count($crnd_group['node']->documents); ?></span>
                    <?php endif; ?>
                </header>
            <?php endif; ?>
            <ul class="crnd-cards" role="list">
                <?php foreach ($crnd_group['node']->documents as $crnd_document) : ?>
                    <?php $view->part('card', ['document' => $crnd_document]); ?>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endforeach; ?>
<?php endif;

$view->part('library-close');
