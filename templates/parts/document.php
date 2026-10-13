<?php
/**
 * One document row (layouts Tree, Sections and List).
 *
 * @var \CRNube\Docs\Frontend\View   $view
 * @var \CRNube\Docs\Domain\Document $document
 * @var list<int>                    $categories Optional: category IDs, for the List filter.
 * @var list<string>                 $labels     Optional: category names shown on the row.
 */

defined('ABSPATH') || exit;

$crnd_categories = $categories ?? [];
$crnd_labels     = $labels ?? [];
$crnd_meta       = $view->meta($document);
$crnd_can_view   = $view->showViewButton($document);
$crnd_locked     = $view->isLocked($document);
$crnd_title      = $crnd_locked
    ? $view->loginUrl($document)
    : ($crnd_can_view ? $view->viewUrl($document) : $view->downloadUrl($document));
?>
<li class="crnd-doc crnd-doc--<?php echo esc_attr($document->kind()); ?><?php echo $document->restricted ? ' is-restricted' : ''; ?><?php echo $crnd_locked ? ' is-locked' : ''; ?>"
    data-crnd-doc
    data-id="<?php echo (int) $document->id; ?>"
    data-year="<?php echo esc_attr($document->year); ?>"
    <?php if ($crnd_categories !== []) : ?>data-cats="<?php echo esc_attr(implode(' ', $crnd_categories)); ?>"<?php endif; ?>
    data-search="<?php echo esc_attr($view->searchText($document) . ' ' . implode(' ', $crnd_labels)); ?>">

    <span class="crnd-doc__icon"><?php echo $view->fileIconHtml($document); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in Icons::file() ?></span>

    <div class="crnd-doc__body">
        <?php if ($document->hasFile() && $crnd_title !== '') : ?>
            <a class="crnd-doc__title" href="<?php echo esc_url($crnd_title); ?>"
                <?php if ($crnd_can_view) : ?>target="_blank" rel="noopener"<?php endif; ?>>
                <?php echo esc_html($document->title); ?>
            </a>
        <?php else : ?>
            <span class="crnd-doc__title"><?php echo esc_html($document->title); ?></span>
        <?php endif; ?>
        <?php if ($document->restricted) : ?>
            <span class="crnd-private" title="<?php esc_attr_e('Restricted document', 'crnube-docs'); ?>">
                <?php echo $view->iconHtml('lock'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed SVG markup ?>
                <span class="crnd-sr-only"><?php esc_html_e('Restricted document', 'crnube-docs'); ?></span>
            </span>
        <?php endif; ?>

        <?php if ($document->excerpt !== '') : ?>
            <p class="crnd-doc__excerpt"><?php echo esc_html($document->excerpt); ?></p>
        <?php endif; ?>

        <?php if ($crnd_meta !== [] || $crnd_labels !== []) : ?>
            <p class="crnd-doc__meta">
                <?php foreach ($crnd_labels as $crnd_label) : ?>
                    <span class="crnd-tag"><?php echo esc_html($crnd_label); ?></span>
                <?php endforeach; ?>
                <?php foreach ($crnd_meta as $crnd_piece) : ?>
                    <span><?php echo esc_html($crnd_piece); ?></span>
                <?php endforeach; ?>
            </p>
        <?php endif; ?>
    </div>

    <div class="crnd-doc__actions">
        <?php $view->part('actions', ['document' => $document]); ?>
    </div>
</li>
