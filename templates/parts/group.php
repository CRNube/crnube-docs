<?php
/**
 * One collapsible category (or year) group. Calls itself for the levels below.
 *
 * @var \CRNube\Docs\Frontend\View     $view
 * @var \CRNube\Docs\Domain\CategoryNode $node
 * @var int                              $level
 * @var int                              $index
 */

defined('ABSPATH') || exit;
?>
<details class="crnd-group crnd-group--level-<?php echo (int) min($level, 4); ?><?php echo $node->isYearGroup ? ' crnd-group--year' : ''; ?>"
    id="<?php echo esc_attr($view->groupId($node)); ?>"
    data-crnd-group
    <?php echo $view->isOpen($level, $index) ? 'open' : ''; ?>>
    <summary class="crnd-group__header">
        <span class="crnd-group__icon">
            <?php echo $view->iconHtml($node->isYearGroup ? 'calendar' : 'folder'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed SVG markup ?>
        </span>
        <span class="crnd-group__title" data-crnd-group-title><?php echo esc_html($node->name); ?></span>
        <?php if ($view->option('counts')) : ?>
            <span class="crnd-badge" data-crnd-count data-total="<?php echo (int) $node->count; ?>"><?php echo (int) $node->count; ?></span>
        <?php endif; ?>
        <span class="crnd-group__chevron">
            <?php echo $view->iconHtml('chevron'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed SVG markup ?>
        </span>
    </summary>

    <div class="crnd-group__body">
        <?php if ($node->description !== '') : ?>
            <p class="crnd-group__description"><?php echo esc_html($node->description); ?></p>
        <?php endif; ?>

        <?php if ($node->documents !== []) : ?>
            <ul class="crnd-list" role="list">
                <?php foreach ($node->documents as $crnd_document) : ?>
                    <?php $view->part('document', ['document' => $crnd_document]); ?>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <?php if ($node->children !== []) : ?>
            <div class="crnd-groups crnd-groups--nested">
                <?php foreach ($node->children as $crnd_index => $crnd_child) : ?>
                    <?php $view->part('group', ['node' => $crnd_child, 'level' => $level + 1, 'index' => $crnd_index]); ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</details>
