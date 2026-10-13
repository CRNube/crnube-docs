<?php
/**
 * Layout "Sections": each top-level category is a section with a large heading; its own
 * documents are shown as cards and its subcategories as tiles that open in place.
 *
 * Override it by copying this file to {theme}/crnube-docs/sections.php.
 *
 * @var \CRNube\Docs\Frontend\View $view
 */

defined('ABSPATH') || exit;

$crnd_tree = $view->tree;

$view->part('library-open');

if (!$crnd_tree->isEmpty()) : ?>
    <?php if ($crnd_tree->documents !== []) : ?>
        <ul class="crnd-cards crnd-cards--compact" role="list">
            <?php foreach ($crnd_tree->documents as $crnd_document) : ?>
                <?php $view->part('card', ['document' => $crnd_document]); ?>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <?php foreach ($crnd_tree->groups as $crnd_node) : ?>
        <section class="crnd-section" id="<?php echo esc_attr($view->groupId($crnd_node)); ?>" data-crnd-group>
            <header class="crnd-section__header">
                <h3 class="crnd-section__title" data-crnd-group-title><?php echo esc_html($crnd_node->name); ?></h3>
                <?php if ($view->option('counts')) : ?>
                    <span class="crnd-section__count" data-crnd-count data-total="<?php echo (int) $crnd_node->count; ?>"
                        data-template="<?php
                        /* translators: %s: number of documents */
                        echo esc_attr(_n('%s document', '%s documents', 2, 'crnube-docs'));
                        ?>"
                        data-template-one="<?php
                        /* translators: %s: number of documents */
                        echo esc_attr(_n('%s document', '%s documents', 1, 'crnube-docs'));
                        ?>"><?php
                        /* translators: %s: number of documents */
                        echo esc_html(sprintf(_n('%s document', '%s documents', $crnd_node->count, 'crnube-docs'), number_format_i18n($crnd_node->count)));
                        ?></span>
                <?php endif; ?>
            </header>

            <?php if ($crnd_node->description !== '') : ?>
                <p class="crnd-section__description"><?php echo esc_html($crnd_node->description); ?></p>
            <?php endif; ?>

            <?php if ($crnd_node->documents !== []) : ?>
                <ul class="crnd-cards crnd-cards--compact" role="list">
                    <?php foreach ($crnd_node->documents as $crnd_document) : ?>
                        <?php $view->part('card', ['document' => $crnd_document]); ?>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <?php if ($crnd_node->children !== []) : ?>
                <div class="crnd-tiles">
                    <?php foreach ($crnd_node->children as $crnd_index => $crnd_child) : ?>
                        <?php $view->part('group', ['node' => $crnd_child, 'level' => 2, 'index' => $crnd_index]); ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    <?php endforeach; ?>
<?php endif;

$view->part('library-close');
