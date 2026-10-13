<?php
/**
 * Search box, year filter and expand/collapse buttons. Without JavaScript it is hidden,
 * and the groups still open and close on their own (they are <details> elements).
 *
 * @var \CRNube\Docs\Frontend\View $view
 */

defined('ABSPATH') || exit;

$crnd_tree  = $view->tree;
$crnd_years = $view->option('year_filter') && count($crnd_tree->years) > 1;
?>
<div class="crnd-toolbar" data-crnd-toolbar hidden>
    <div class="crnd-toolbar__filters">
        <?php if ($view->option('search')) : ?>
            <label class="crnd-search">
                <span class="crnd-sr-only"><?php echo esc_html($view->label('search')); ?></span>
                <?php echo $view->iconHtml('search'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed SVG markup ?>
                <input type="search" class="crnd-search__input" data-crnd-search
                    placeholder="<?php echo esc_attr($view->label('search')); ?>" autocomplete="off">
            </label>
        <?php endif; ?>

        <?php if ($crnd_years) : ?>
            <label class="crnd-select">
                <span class="crnd-sr-only"><?php esc_html_e('Filter by year', 'crnube-docs'); ?></span>
                <select data-crnd-year>
                    <option value=""><?php esc_html_e('All years', 'crnube-docs'); ?></option>
                    <?php foreach ($crnd_tree->years as $crnd_year) : ?>
                        <option value="<?php echo esc_attr($crnd_year); ?>"><?php echo esc_html($crnd_year); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        <?php endif; ?>
    </div>

    <div class="crnd-toolbar__meta">
        <span class="crnd-status" data-crnd-status aria-live="polite"
            data-template="<?php
            /* translators: 1: number of documents shown, 2: total number of documents */
            echo esc_attr(__('Showing %1$s of %2$s documents', 'crnube-docs'));
            ?>"></span>

        <?php if ($view->option('toggle_all') && $crnd_tree->groups !== []) : ?>
            <button type="button" class="crnd-btn crnd-btn--ghost crnd-btn--icon" data-crnd-toggle="open"
                title="<?php esc_attr_e('Expand all', 'crnube-docs'); ?>">
                <?php echo $view->iconHtml('expand'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed SVG markup ?>
                <span class="crnd-sr-only"><?php esc_html_e('Expand all', 'crnube-docs'); ?></span>
            </button>
            <button type="button" class="crnd-btn crnd-btn--ghost crnd-btn--icon" data-crnd-toggle="close"
                title="<?php esc_attr_e('Collapse all', 'crnube-docs'); ?>">
                <?php echo $view->iconHtml('collapse'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed SVG markup ?>
                <span class="crnd-sr-only"><?php esc_html_e('Collapse all', 'crnube-docs'); ?></span>
            </button>
        <?php endif; ?>
    </div>
</div>
