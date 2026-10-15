<?php
/**
 * The "View" and "Download" buttons of a document, shared by every layout.
 *
 * @var \CRNube\Docs\Frontend\View   $view
 * @var \CRNube\Docs\Domain\Document $document
 */

defined('ABSPATH') || exit;
?>
<?php if ($view->showViewButton($document)) : ?>
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
<?php if ($view->isLocked($document)) : ?>
    <?php $crnd_login = $view->loginUrl($document); ?>
    <?php if ($crnd_login !== '') : ?>
        <a class="crnd-btn crnd-btn--ghost crnd-btn--lock" href="<?php echo esc_url($crnd_login); ?>">
            <?php echo $view->iconHtml('lock'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed SVG markup ?>
            <span><?php esc_html_e('Sign in to open', 'crnube-docs'); ?></span>
            <span class="crnd-sr-only"><?php echo esc_html($document->title); ?></span>
        </a>
    <?php else : ?>
        <span class="crnd-locked-note">
            <?php echo $view->iconHtml('lock'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed SVG markup ?>
            <?php esc_html_e('Not available for your account', 'crnube-docs'); ?>
        </span>
    <?php endif; ?>
<?php endif; ?>
