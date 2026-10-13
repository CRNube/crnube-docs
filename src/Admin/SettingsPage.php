<?php
declare(strict_types=1);

namespace CRNube\Docs\Admin;

use CRNube\Docs\Capabilities;
use CRNube\Docs\Domain\CategoryNode;
use CRNube\Docs\Domain\Document;
use CRNube\Docs\Domain\DocumentPostType;
use CRNube\Docs\Domain\DocumentTree;
use CRNube\Docs\Frontend\Assets;
use CRNube\Docs\Frontend\Renderer;
use CRNube\Docs\Frontend\Shortcode;
use CRNube\Docs\Settings\Settings;

/**
 * Documents > Settings: design, display defaults and labels, with a live preview of the
 * library built from sample documents.
 */
final class SettingsPage
{
    public const SLUG   = 'crnd-settings';
    private const GROUP = 'crnd_settings_group';

    private string $hook = '';

    public function __construct(
        private readonly Settings $settings,
        private readonly Renderer $renderer,
    ) {
    }

    public function register(): void
    {
        add_action('admin_menu', [$this, 'menu']);
        add_action('admin_init', [$this, 'registerSetting']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue']);
        add_filter('option_page_capability_' . self::GROUP, static fn (): string => Capabilities::MANAGE_SETTINGS);
        add_filter('plugin_action_links_' . plugin_basename(CRND_FILE), [$this, 'actionLinks']);
    }

    public function menu(): void
    {
        $this->hook = (string) add_submenu_page(
            LibraryPage::SLUG,
            __('CRNube Docs settings', 'crnube-docs'),
            __('Settings', 'crnube-docs'),
            Capabilities::MANAGE_SETTINGS,
            self::SLUG,
            [$this, 'render']
        );
    }

    public function registerSetting(): void
    {
        register_setting(self::GROUP, Settings::OPTION, [
            'type'              => 'array',
            'sanitize_callback' => [Settings::class, 'sanitize'],
            'default'           => Settings::defaults(),
        ]);
    }

    /**
     * @param array<string, string> $links
     * @return array<string, string>
     */
    public function actionLinks(array $links): array
    {
        if (current_user_can(Capabilities::MANAGE_SETTINGS)) {
            array_unshift($links, sprintf('<a href="%s">%s</a>', esc_url($this->url()), esc_html__('Settings', 'crnube-docs')));
        }
        return $links;
    }

    public function enqueue(string $hook): void
    {
        if ($hook !== $this->hook) {
            return;
        }
        wp_enqueue_style('crnd-admin', CRND_URL . 'assets/css/admin.css', [], Assets::version('assets/css/admin.css'));
        Assets::enqueue();
        wp_enqueue_script('crnd-admin-settings', CRND_URL . 'assets/js/admin-settings.js', [], Assets::version('assets/js/admin-settings.js'), true);
        wp_localize_script('crnd-admin-settings', 'crndSettings', [
            'option'  => Settings::OPTION,
            'keys'    => Settings::DESIGN_KEYS,
            'presets' => array_map(static fn (array $p): array => $p['values'], Settings::presets()),
            'site'    => sanitize_title(get_bloginfo('name')) ?: 'sitio',
            'i18n'    => [
                'applied'  => __('Preset applied. Save the changes to keep it.', 'crnube-docs'),
                'imported' => __('Design imported. Save the changes to keep it.', 'crnube-docs'),
                'invalid'  => __('This file is not a CRNube Docs design.', 'crnube-docs'),
            ],
        ]);
        wp_enqueue_script('crnd-admin-copy', CRND_URL . 'assets/js/admin-copy.js', [], Assets::version('assets/js/admin-copy.js'), true);
        wp_localize_script('crnd-admin-copy', 'crndCopy', ['copied' => __('Copied!', 'crnube-docs')]);
    }

    private function url(): string
    {
        return admin_url('admin.php?page=' . self::SLUG);
    }

    public function render(): void
    {
        if (!current_user_can(Capabilities::MANAGE_SETTINGS)) {
            return;
        }
        $v = $this->settings->all();
        ?>
        <div class="wrap crnd-settings">
            <h1 class="crnd-settings__title">
                <span class="dashicons dashicons-media-document" aria-hidden="true"></span>
                <?php esc_html_e('CRNube Docs settings', 'crnube-docs'); ?>
            </h1>
            <?php settings_errors(); ?>

            <form method="post" action="options.php" class="crnd-settings__grid">
                <?php settings_fields(self::GROUP); ?>

                <div class="crnd-settings__main">
                    <?php $this->layoutCard($v); ?>
                    <?php $this->presetsCard(); ?>
                    <?php $this->designCard($v); ?>
                    <?php $this->displayCard($v); ?>
                    <?php $this->labelsCard($v); ?>
                    <?php $this->statsCard($v); ?>
                    <?php $this->advancedCard($v); ?>
                    <div class="crnd-settings__submit">
                        <?php submit_button(__('Save changes', 'crnube-docs'), 'primary large', 'submit', false); ?>
                    </div>
                </div>

                <aside class="crnd-settings__side">
                    <section class="crnd-card crnd-card--preview">
                        <header class="crnd-card__header">
                            <h2><?php esc_html_e('Live preview', 'crnube-docs'); ?></h2>
                            <span class="crnd-card__hint"><?php esc_html_e('Design changes show instantly. Display options apply after saving.', 'crnube-docs'); ?></span>
                        </header>
                        <div class="crnd-preview" data-crnd-preview>
                            <?php echo $this->preview(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the templates ?>
                        </div>
                    </section>
                    <?php $this->usageCard(); ?>
                </aside>
            </form>
        </div>
        <?php
    }

    /** @param array<string, mixed> $v */
    private function layoutCard(array $v): void
    {
        $layouts = [
            'tree'     => [__('Tree', 'crnube-docs'), __('Collapsible groups, any depth.', 'crnube-docs'), 'list-view'],
            'sections' => [__('Sections', 'crnube-docs'), __('Large headings, subcategories as tiles.', 'crnube-docs'), 'columns'],
            'grid'     => [__('Grid', 'crnube-docs'), __('A card per document.', 'crnube-docs'), 'grid-view'],
            'list'     => [__('List', 'crnube-docs'), __('One list with category, year and search filters.', 'crnube-docs'), 'editor-ul'],
        ];
        ?>
        <section class="crnd-card">
            <header class="crnd-card__header">
                <h2><?php esc_html_e('Layout', 'crnube-docs'); ?></h2>
                <span class="crnd-card__hint"><?php esc_html_e('The default for every library; a shortcode can choose another with layout="…".', 'crnube-docs'); ?></span>
            </header>
            <div class="crnd-layouts" role="radiogroup" aria-label="<?php esc_attr_e('Layout', 'crnube-docs'); ?>">
                <?php foreach ($layouts as $key => [$label, $hint, $icon]) : ?>
                    <label class="crnd-layout">
                        <input type="radio" name="<?php echo esc_attr($this->name('layout')); ?>" value="<?php echo esc_attr($key); ?>"
                            <?php checked($v['layout'], $key); ?> data-crnd-layout>
                        <span class="crnd-layout__box">
                            <span class="dashicons dashicons-<?php echo esc_attr($icon); ?>" aria-hidden="true"></span>
                            <strong><?php echo esc_html($label); ?></strong>
                            <small><?php echo esc_html($hint); ?></small>
                            <code>layout="<?php echo esc_html($key); ?>"</code>
                        </span>
                    </label>
                <?php endforeach; ?>
            </div>
            <label class="crnd-field crnd-field--inline" data-crnd-columns-field <?php echo $v['layout'] === 'grid' ? '' : 'hidden'; ?>>
                <span class="crnd-field__label"><?php esc_html_e('Grid columns', 'crnube-docs'); ?></span>
                <select name="<?php echo esc_attr($this->name('columns')); ?>" data-crnd-var="--crnd-columns">
                    <?php foreach ([2, 3, 4] as $columns) : ?>
                        <option value="<?php echo (int) $columns; ?>" <?php selected((int) $v['columns'], $columns); ?>><?php echo (int) $columns; ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        </section>
        <?php
    }

    private function presetsCard(): void
    {
        ?>
        <section class="crnd-card">
            <header class="crnd-card__header">
                <h2><?php esc_html_e('Brand presets', 'crnube-docs'); ?></h2>
                <span class="crnd-card__hint"><?php esc_html_e('Start from a preset, or export this design to reuse it on another site. Nothing changes until you save.', 'crnube-docs'); ?></span>
            </header>
            <div class="crnd-presets">
                <?php foreach (Settings::presets() as $id => $preset) : ?>
                    <button type="button" class="crnd-preset" data-crnd-preset="<?php echo esc_attr($id); ?>">
                        <span class="crnd-preset__swatches" aria-hidden="true">
                            <?php foreach (['primary', 'accent', 'surface_alt'] as $key) : ?>
                                <span style="background:<?php echo esc_attr((string) $preset['values'][$key]); ?>"></span>
                            <?php endforeach; ?>
                        </span>
                        <span class="crnd-preset__name"><?php echo esc_html($preset['name']); ?></span>
                    </button>
                <?php endforeach; ?>
            </div>
            <div class="crnd-presets__io">
                <button type="button" class="button" data-crnd-export>
                    <span class="dashicons dashicons-download" aria-hidden="true"></span>
                    <?php esc_html_e('Export design (.json)', 'crnube-docs'); ?>
                </button>
                <label class="button">
                    <span class="dashicons dashicons-upload" aria-hidden="true"></span>
                    <?php esc_html_e('Import design', 'crnube-docs'); ?>
                    <input type="file" accept="application/json,.json" data-crnd-import hidden>
                </label>
                <span class="crnd-presets__status" data-crnd-preset-status aria-live="polite"></span>
            </div>
        </section>
        <?php
    }

    /** @param array<string, mixed> $v */
    private function designCard(array $v): void
    {
        $colors = [
            'primary'     => [__('Primary', 'crnube-docs'), '--crnd-primary'],
            'accent'      => [__('Accent', 'crnube-docs'), '--crnd-accent'],
            'text'        => [__('Text', 'crnube-docs'), '--crnd-text'],
            'muted'       => [__('Secondary text', 'crnube-docs'), '--crnd-muted'],
            'surface'     => [__('Background', 'crnube-docs'), '--crnd-surface'],
            'surface_alt' => [__('Alternate background', 'crnube-docs'), '--crnd-surface-alt'],
            'border'      => [__('Borders', 'crnube-docs'), '--crnd-border'],
        ];
        ?>
        <section class="crnd-card">
            <header class="crnd-card__header">
                <h2><?php esc_html_e('Design', 'crnube-docs'); ?></h2>
                <span class="crnd-card__hint"><?php esc_html_e("Match the library to the cooperative's brand.", 'crnube-docs'); ?></span>
            </header>

            <div class="crnd-colors">
                <?php foreach ($colors as $key => [$label, $var]) : ?>
                    <label class="crnd-color">
                        <input type="color" name="<?php echo esc_attr($this->name($key)); ?>" value="<?php echo esc_attr((string) $v[$key]); ?>"
                            data-crnd-var="<?php echo esc_attr($var); ?>">
                        <span class="crnd-color__text">
                            <span class="crnd-color__label"><?php echo esc_html($label); ?></span>
                            <code data-crnd-hex><?php echo esc_html((string) $v[$key]); ?></code>
                        </span>
                    </label>
                <?php endforeach; ?>
            </div>

            <div class="crnd-field">
                <span class="crnd-field__label"><?php esc_html_e('Group header style', 'crnube-docs'); ?></span>
                <div class="crnd-segmented" data-crnd-class-group="crnd--header-">
                    <?php
                    $this->segment('header_style', 'soft', __('Soft', 'crnube-docs'), $v);
                    $this->segment('header_style', 'solid', __('Solid', 'crnube-docs'), $v);
                    $this->segment('header_style', 'minimal', __('Minimal', 'crnube-docs'), $v);
                    ?>
                </div>
            </div>

            <div class="crnd-field">
                <span class="crnd-field__label"><?php esc_html_e('Density', 'crnube-docs'); ?></span>
                <div class="crnd-segmented" data-crnd-class-group="crnd--">
                    <?php
                    $this->segment('density', 'comfortable', __('Comfortable', 'crnube-docs'), $v);
                    $this->segment('density', 'compact', __('Compact', 'crnube-docs'), $v);
                    ?>
                </div>
            </div>

            <div class="crnd-field-row">
                <label class="crnd-field">
                    <span class="crnd-field__label"><?php esc_html_e('Corner radius', 'crnube-docs'); ?> <output data-crnd-output><?php echo (int) $v['radius']; ?>px</output></span>
                    <input type="range" min="0" max="32" step="1" name="<?php echo esc_attr($this->name('radius')); ?>" value="<?php echo (int) $v['radius']; ?>"
                        data-crnd-var="--crnd-radius" data-crnd-unit="px">
                </label>
                <label class="crnd-field">
                    <span class="crnd-field__label"><?php esc_html_e('Font', 'crnube-docs'); ?></span>
                    <input type="text" class="regular-text" name="<?php echo esc_attr($this->name('font')); ?>" value="<?php echo esc_attr((string) $v['font']); ?>"
                        placeholder="<?php esc_attr_e("Theme's font", 'crnube-docs'); ?>" data-crnd-var="--crnd-font">
                </label>
            </div>
        </section>
        <?php
    }

    /** @param array<string, mixed> $v */
    private function displayCard(array $v): void
    {
        $toggles = [
            'search'        => __('Search box', 'crnube-docs'),
            'year_filter'   => __('Year filter', 'crnube-docs'),
            'toggle_all'    => __('Expand / collapse all buttons', 'crnube-docs'),
            'counts'        => __('Document count on each group', 'crnube-docs'),
            'show_date'     => __('Publication date', 'crnube-docs'),
            'show_size'     => __('File size', 'crnube-docs'),
            'show_type'     => __('File type', 'crnube-docs'),
            'group_by_year' => __('Group documents by year inside each category', 'crnube-docs'),
        ];
        ?>
        <section class="crnd-card">
            <header class="crnd-card__header">
                <h2><?php esc_html_e('Display', 'crnube-docs'); ?></h2>
                <span class="crnd-card__hint"><?php esc_html_e('Defaults for every library; each shortcode can override them.', 'crnube-docs'); ?></span>
            </header>

            <div class="crnd-toggles">
                <?php foreach ($toggles as $key => $label) : ?>
                    <label class="crnd-toggle">
                        <input type="checkbox" name="<?php echo esc_attr($this->name($key)); ?>" value="1" <?php checked((bool) $v[$key]); ?>>
                        <span class="crnd-toggle__switch" aria-hidden="true"></span>
                        <span><?php echo esc_html($label); ?></span>
                    </label>
                <?php endforeach; ?>
            </div>

            <div class="crnd-field-row">
                <label class="crnd-field">
                    <span class="crnd-field__label"><?php esc_html_e('Groups open on load', 'crnube-docs'); ?></span>
                    <select name="<?php echo esc_attr($this->name('open')); ?>">
                        <option value="none" <?php selected($v['open'], 'none'); ?>><?php esc_html_e('All closed', 'crnube-docs'); ?></option>
                        <option value="first" <?php selected($v['open'], 'first'); ?>><?php esc_html_e('First one open', 'crnube-docs'); ?></option>
                        <option value="all" <?php selected($v['open'], 'all'); ?>><?php esc_html_e('All open', 'crnube-docs'); ?></option>
                    </select>
                </label>
                <label class="crnd-field">
                    <span class="crnd-field__label"><?php esc_html_e('Order documents by', 'crnube-docs'); ?></span>
                    <select name="<?php echo esc_attr($this->name('orderby')); ?>">
                        <option value="date" <?php selected($v['orderby'], 'date'); ?>><?php esc_html_e('Publication date', 'crnube-docs'); ?></option>
                        <option value="title" <?php selected($v['orderby'], 'title'); ?>><?php esc_html_e('Title', 'crnube-docs'); ?></option>
                        <option value="menu_order" <?php selected($v['orderby'], 'menu_order'); ?>><?php esc_html_e('Manual order', 'crnube-docs'); ?></option>
                    </select>
                </label>
                <label class="crnd-field">
                    <span class="crnd-field__label"><?php esc_html_e('Direction', 'crnube-docs'); ?></span>
                    <select name="<?php echo esc_attr($this->name('order')); ?>">
                        <option value="DESC" <?php selected($v['order'], 'DESC'); ?>><?php esc_html_e('Newest / Z–A first', 'crnube-docs'); ?></option>
                        <option value="ASC" <?php selected($v['order'], 'ASC'); ?>><?php esc_html_e('Oldest / A–Z first', 'crnube-docs'); ?></option>
                    </select>
                </label>
                <label class="crnd-field">
                    <span class="crnd-field__label"><?php esc_html_e('Date format', 'crnube-docs'); ?></span>
                    <input type="text" name="<?php echo esc_attr($this->name('date_format')); ?>" value="<?php echo esc_attr((string) $v['date_format']); ?>"
                        placeholder="<?php echo esc_attr((string) get_option('date_format')); ?>">
                </label>
            </div>
        </section>
        <?php
    }

    /** @param array<string, mixed> $v */
    private function labelsCard(array $v): void
    {
        $labels = [
            'download' => __('Download button', 'crnube-docs'),
            'view'     => __('View button', 'crnube-docs'),
            'search'   => __('Search placeholder', 'crnube-docs'),
            'empty'    => __('No results message', 'crnube-docs'),
        ];
        ?>
        <section class="crnd-card">
            <header class="crnd-card__header">
                <h2><?php esc_html_e('Texts', 'crnube-docs'); ?></h2>
                <span class="crnd-card__hint"><?php esc_html_e('Leave empty to use the default text.', 'crnube-docs'); ?></span>
            </header>
            <div class="crnd-field-row">
                <?php foreach ($labels as $key => $label) : ?>
                    <label class="crnd-field">
                        <span class="crnd-field__label"><?php echo esc_html($label); ?></span>
                        <input type="text" name="<?php echo esc_attr($this->name('label_' . $key)); ?>" value="<?php echo esc_attr((string) $v['label_' . $key]); ?>"
                            placeholder="<?php echo esc_attr(Settings::defaultLabel($key)); ?>">
                    </label>
                <?php endforeach; ?>
            </div>
        </section>
        <?php
    }

    /** @param array<string, mixed> $v */
    private function statsCard(array $v): void
    {
        ?>
        <section class="crnd-card">
            <header class="crnd-card__header">
                <h2><?php esc_html_e('Statistics and privacy', 'crnube-docs'); ?></h2>
                <span class="crnd-card__hint"><?php esc_html_e('IP addresses are never stored: each visitor gets an anonymous key that changes every day.', 'crnube-docs'); ?></span>
            </header>
            <div class="crnd-toggles">
                <label class="crnd-toggle">
                    <input type="checkbox" name="<?php echo esc_attr($this->name('track_enabled')); ?>" value="1" <?php checked((bool) $v['track_enabled']); ?>>
                    <span class="crnd-toggle__switch" aria-hidden="true"></span>
                    <span><?php esc_html_e('Record views and downloads', 'crnube-docs'); ?></span>
                </label>
                <label class="crnd-toggle">
                    <input type="checkbox" name="<?php echo esc_attr($this->name('respect_gpc')); ?>" value="1" <?php checked((bool) $v['respect_gpc']); ?>>
                    <span class="crnd-toggle__switch" aria-hidden="true"></span>
                    <span>
                        <?php esc_html_e('Respect the browser privacy signal', 'crnube-docs'); ?>
                        <small><?php esc_html_e('Visitors who send "Do Not Track" or "Global Privacy Control" are counted without device or country.', 'crnube-docs'); ?></small>
                    </span>
                </label>
            </div>
            <label class="crnd-field">
                <span class="crnd-field__label"><?php esc_html_e('Keep the history for', 'crnube-docs'); ?></span>
                <select name="<?php echo esc_attr($this->name('retention_months')); ?>">
                    <?php foreach ([6, 12, 24, 36, 60, 0] as $months) : ?>
                        <option value="<?php echo (int) $months; ?>" <?php selected((int) $v['retention_months'], $months); ?>>
                            <?php
                            echo esc_html($months === 0
                                ? __('Forever', 'crnube-docs')
                                /* translators: %d: number of months */
                                : sprintf(_n('%d month', '%d months', $months, 'crnube-docs'), $months));
                            ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
        </section>
        <?php
    }

    /** @param array<string, mixed> $v */
    private function advancedCard(array $v): void
    {
        ?>
        <section class="crnd-card">
            <header class="crnd-card__header">
                <h2><?php esc_html_e('Advanced', 'crnube-docs'); ?></h2>
            </header>
            <label class="crnd-toggle crnd-toggle--danger">
                <input type="checkbox" name="<?php echo esc_attr($this->name('delete_data')); ?>" value="1" <?php checked((bool) $v['delete_data']); ?>>
                <span class="crnd-toggle__switch" aria-hidden="true"></span>
                <span>
                    <?php esc_html_e('Delete all documents, categories and statistics when the plugin is deleted', 'crnube-docs'); ?>
                    <small><?php esc_html_e('The files stay in the media library.', 'crnube-docs'); ?></small>
                </span>
            </label>
        </section>
        <?php
    }

    private function usageCard(): void
    {
        $examples = [
            sprintf('[%s]', Shortcode::TAG)                                         => __('Every category', 'crnube-docs'),
            sprintf('[%s category="informes"]', Shortcode::TAG)                     => __('One category and its subcategories', 'crnube-docs'),
            sprintf('[%s category="informes" group_by_year="yes"]', Shortcode::TAG) => __('Grouped by year', 'crnube-docs'),
            sprintf('[%s layout="sections" header_style="solid"]', Shortcode::TAG) => __('Sections with solid headers', 'crnube-docs'),
            sprintf('[%s layout="grid" columns="4"]', Shortcode::TAG)             => __('Grid of cards, 4 columns', 'crnube-docs'),
            sprintf('[%s layout="list"]', Shortcode::TAG)                         => __('One list with filters', 'crnube-docs'),
            sprintf('[%s primary="#7a1fa2" search="no"]', Shortcode::TAG)           => __('Own color, no search box', 'crnube-docs'),
        ];
        ?>
        <section class="crnd-card">
            <header class="crnd-card__header">
                <h2><?php esc_html_e('How to use', 'crnube-docs'); ?></h2>
                <span class="crnd-card__hint"><?php esc_html_e('Paste a shortcode in any page, or in an Elementor "Shortcode" widget.', 'crnube-docs'); ?></span>
            </header>
            <ul class="crnd-usage">
                <?php foreach ($examples as $code => $label) : ?>
                    <li>
                        <span><?php echo esc_html($label); ?></span>
                        <button type="button" class="crnd-copy" data-crnd-copy="<?php echo esc_attr($code); ?>"><code><?php echo esc_html($code); ?></code><span class="dashicons dashicons-clipboard" aria-hidden="true"></span></button>
                    </li>
                <?php endforeach; ?>
            </ul>
            <p class="crnd-usage__attrs">
                <?php esc_html_e('Other attributes:', 'crnube-docs'); ?>
                <code>layout</code> <code>columns</code> <code>title</code> <code>search</code> <code>open</code> <code>header_style</code> <code>year_filter</code> <code>counts</code> <code>date</code> <code>size</code> <code>type</code>
                <code>toggle_all</code> <code>orderby</code> <code>order</code> <code>depth</code> <code>accent</code> <code>density</code> <code>class</code>
            </p>
        </section>
        <?php
    }

    /**
     * @param array<string, mixed> $v
     */
    private function segment(string $key, string $value, string $label, array $v): void
    {
        printf(
            '<label><input type="radio" name="%1$s" value="%2$s" %3$s><span>%4$s</span></label>',
            esc_attr($this->name($key)),
            esc_attr($value),
            checked($v[$key], $value, false),
            esc_html($label)
        );
    }

    private function name(string $key): string
    {
        return Settings::OPTION . '[' . $key . ']';
    }

    /** The library rendered with sample documents, so the design can be judged before any upload. */
    private function preview(): string
    {
        $now  = time();
        $day  = DAY_IN_SECONDS;
        $make = static fn (int $id, string $title, int $daysAgo, string $ext, int $size): Document => new Document(
            id: $id,
            title: $title,
            excerpt: '',
            timestamp: $now - $daysAgo * $day,
            year: (string) wp_date('Y', $now - $daysAgo * $day),
            fileId: 1,
            externalUrl: '',
            extension: $ext,
            size: $size,
            categoryIds: [],
        );

        $audited = [
            $make(1, __('Audited financial statements 2025', 'crnube-docs'), 20, 'pdf', 1_840_000),
            $make(2, __('Audited financial statements 2024', 'crnube-docs'), 385, 'pdf', 1_620_000),
        ];
        $quarterly = [
            $make(3, __('Quarterly statements — September', 'crnube-docs'), 12, 'xlsx', 284_000),
            $make(4, __('Quarterly statements — June', 'crnube-docs'), 104, 'xlsx', 266_000),
        ];
        $risk = [
            $make(5, __('Annual risk report 2025', 'crnube-docs'), 30, 'pdf', 360_000),
            $make(6, __('Corporate governance code', 'crnube-docs'), 400, 'docx', 98_000),
        ];

        $node = static fn (string $name, string $slug, array $docs, array $children = []): CategoryNode => new CategoryNode(
            termId: 0,
            name: $name,
            slug: $slug,
            description: '',
            isYearGroup: false,
            documents: $docs,
            children: $children,
            count: count($docs) + array_sum(array_map(static fn (CategoryNode $c): int => $c->count, $children)),
        );

        $tree = new DocumentTree(
            documents: [],
            groups: [
                $node(__('Financial statements', 'crnube-docs'), 'sample-financial', [], [
                    $node(__('Audited', 'crnube-docs'), 'sample-audited', $audited),
                    $node(__('Quarterly', 'crnube-docs'), 'sample-quarterly', $quarterly),
                ]),
                $node(__('Reports', 'crnube-docs'), 'sample-reports', $risk),
            ],
            years: array_values(array_unique([(string) wp_date('Y'), (string) wp_date('Y', $now - 400 * $day)])),
            total: 6,
        );

        // Every layout is rendered once; the layout picker only shows the chosen one.
        $current = (string) $this->settings->get('layout');
        $html    = '';
        foreach (Settings::LAYOUTS as $layout) {
            $options           = $this->renderer->options(['layout' => $layout]);
            $options['open']   = 'first';
            $html             .= sprintf(
                '<div class="crnd-preview__layout" data-crnd-preview-layout="%s"%s>%s</div>',
                esc_attr($layout),
                $layout === $current ? '' : ' hidden',
                $this->renderer->renderTree($tree, $options)
            );
        }
        return $html;
    }
}
