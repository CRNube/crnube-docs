<?php
/**
 * One document as a card (layouts Grid and Sections).
 *
 * @var \CRNube\Docs\Frontend\View   $view
 * @var \CRNube\Docs\Domain\Document $document
 */

defined('ABSPATH') || exit;

$crnd_meta     = $view->meta($document);
$crnd_can_view = $view->showViewButton($document);
$crnd_locked   = $view->isLocked($document);
$crnd_title    = $crnd_locked
    ? $view->loginUrl($document)
    : ($crnd_can_view ? $view->viewUrl($document) : $view->downloadUrl($document));
?>
<li class="crnd-card crnd-card--<?php echo esc_attr($document->kind()); ?><?php echo $document->restricted ? ' is-restricted' : ''; ?><?php echo $crnd_locked ? ' is-locked' : ''; ?>"
    data-crnd-doc
    data-id="<?php echo (int) $document->id; ?>"
    data-year="<?php echo esc_attr($document->year); ?>"
    data-search="<?php echo esc_attr($view->searchText($document)); ?>">

    <div class="crnd-card__top">
        <span class="crnd-card__icon"><?php echo $view->fileIconHtml($document); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in Icons::file() ?></span>
        <?php if ($document->hasFile() && $crnd_title !== '') : ?>
            <a class="crnd-card__title" href="<?php echo esc_url($crnd_title); ?>"
                <?php if ($crnd_can_view) : ?>target="_blank" rel="noopener"<?php endif; ?>>
                <?php echo esc_html($document->title); ?>
            </a>
        <?php else : ?>
            <span class="crnd-card__title"><?php echo esc_html($document->title); ?></span>
        <?php endif; ?>
        <?php if ($document->restricted) : ?>
            <span class="crnd-private" title="<?php esc_attr_e('Restricted document', 'crnube-docs'); ?>">
                <?php echo $view->iconHtml('lock'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed SVG markup ?>
                <span class="crnd-sr-only"><?php esc_html_e('Restricted document', 'crnube-docs'); ?></span>
            </span>
        <?php endif; ?>
    </div>

    <?php if ($document->excerpt !== '') : ?>
        <p class="crnd-card__excerpt"><?php echo esc_html($document->excerpt); ?></p>
    <?php endif; ?>

    <?php if ($crnd_meta !== []) : ?>
        <p class="crnd-doc__meta crnd-card__meta">
            <?php foreach ($crnd_meta as $crnd_piece) : ?>
                <span><?php echo esc_html($crnd_piece); ?></span>
            <?php endforeach; ?>
        </p>
    <?php endif; ?>

    <div class="crnd-card__actions">
        <?php $view->part('actions', ['document' => $document]); ?>
    </div>
</li>
