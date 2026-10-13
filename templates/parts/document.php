<?php
/**
 * One document row.
 *
 * @var \CRNube\Docs\Frontend\View   $view
 * @var \CRNube\Docs\Domain\Document $document
 */

defined('ABSPATH') || exit;

$crnd_meta     = $view->meta($document);
$crnd_can_view = $view->showViewButton($document);
$crnd_title    = $crnd_can_view ? $view->viewUrl($document) : $view->downloadUrl($document);
?>
<li class="crnd-doc crnd-doc--<?php echo esc_attr($document->kind()); ?>"
    data-crnd-doc
    data-id="<?php echo (int) $document->id; ?>"
    data-year="<?php echo esc_attr($document->year); ?>"
    data-search="<?php echo esc_attr($view->searchText($document)); ?>">

    <span class="crnd-doc__icon"><?php echo $view->fileIconHtml($document); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in Icons::file() ?></span>

    <div class="crnd-doc__body">
        <?php if ($document->hasFile()) : ?>
            <a class="crnd-doc__title" href="<?php echo esc_url($crnd_title); ?>"
                <?php if ($crnd_can_view) : ?>target="_blank" rel="noopener"<?php endif; ?>>
                <?php echo esc_html($document->title); ?>
            </a>
        <?php else : ?>
            <span class="crnd-doc__title"><?php echo esc_html($document->title); ?></span>
        <?php endif; ?>

        <?php if ($document->excerpt !== '') : ?>
            <p class="crnd-doc__excerpt"><?php echo esc_html($document->excerpt); ?></p>
        <?php endif; ?>

        <?php if ($crnd_meta !== []) : ?>
            <p class="crnd-doc__meta">
                <?php foreach ($crnd_meta as $crnd_piece) : ?>
                    <span><?php echo esc_html($crnd_piece); ?></span>
                <?php endforeach; ?>
            </p>
        <?php endif; ?>
    </div>

    <div class="crnd-doc__actions">
        <?php if ($crnd_can_view) : ?>
            <a class="crnd-btn crnd-btn--ghost" href="<?php echo esc_url($view->viewUrl($document)); ?>" target="_blank" rel="noopener">
                <?php echo $view->iconHtml($document->isExternal() ? 'external' : 'eye'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed SVG markup ?>
                <span><?php echo esc_html($view->label('view')); ?></span>
                <span class="crnd-sr-only"><?php echo esc_html($document->title); ?></span>
            </a>
        <?php endif; ?>
        <?php if ($view->showDownloadButton($document)) : ?>
            <a class="crnd-btn crnd-btn--primary" href="<?php echo esc_url($view->downloadUrl($document)); ?>">
                <?php echo $view->iconHtml('download'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed SVG markup ?>
                <span><?php echo esc_html($view->label('download')); ?></span>
                <span class="crnd-sr-only"><?php echo esc_html($document->title); ?></span>
            </a>
        <?php endif; ?>
    </div>
</li>
