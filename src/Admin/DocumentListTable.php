<?php
declare(strict_types=1);

namespace CRNube\Docs\Admin;

use CRNube\Docs\Domain\CategoryTaxonomy;
use CRNube\Docs\Domain\DocumentPostType;
use CRNube\Docs\Domain\FileTypes;
use CRNube\Docs\Domain\YearTaxonomy;
use CRNube\Docs\Frontend\Assets;
use CRNube\Docs\Frontend\Icons;

/**
 * Columns and filters of the documents list (Documents > All documents).
 */
final class DocumentListTable
{
    private const FILTER_CATEGORY = 'crnd_cat';
    private const FILTER_YEAR     = 'crnd_y';

    public function register(): void
    {
        $type = DocumentPostType::NAME;
        add_filter("manage_{$type}_posts_columns", [$this, 'columns']);
        add_action("manage_{$type}_posts_custom_column", [$this, 'column'], 10, 2);
        add_action('restrict_manage_posts', [$this, 'filters']);
        add_action('pre_get_posts', [$this, 'applyFilters']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue']);
    }

    public function enqueue(string $hook): void
    {
        if ($hook === 'edit.php' && get_current_screen()?->post_type === DocumentPostType::NAME) {
            wp_enqueue_style('crnd-admin', CRND_URL . 'assets/css/admin.css', [], Assets::version('assets/css/admin.css'));
        }
    }

    /**
     * @param array<string, string> $columns
     * @return array<string, string>
     */
    public function columns(array $columns): array
    {
        $ordered = [];
        foreach ($columns as $key => $label) {
            if ($key === 'title') {
                $ordered['crnd_icon'] = '<span class="screen-reader-text">' . esc_html__('Type', 'crnube-docs') . '</span>';
            }
            $ordered[$key] = $label;
            if ($key === 'title') {
                $ordered['crnd_file'] = __('File', 'crnube-docs');
            }
        }
        $ordered['crnd_downloads'] = __('Downloads', 'crnube-docs');

        // The date column goes last.
        if (isset($ordered['date'])) {
            $date = $ordered['date'];
            unset($ordered['date']);
            $ordered['date'] = $date;
        }
        return $ordered;
    }

    public function column(string $column, int $postId): void
    {
        $fileId   = (int) get_post_meta($postId, DocumentPostType::META_FILE_ID, true);
        $external = (string) get_post_meta($postId, DocumentPostType::META_EXTERNAL, true);
        $path     = $fileId > 0 ? (string) get_attached_file($fileId) : '';
        $ext      = $path !== ''
            ? strtolower(pathinfo($path, PATHINFO_EXTENSION))
            : strtolower(pathinfo((string) wp_parse_url($external, PHP_URL_PATH), PATHINFO_EXTENSION));

        switch ($column) {
            case 'crnd_icon':
                if ($fileId > 0 || $external !== '') {
                    echo Icons::file(FileTypes::kind($ext), $ext); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in Icons::file()
                }
                break;

            case 'crnd_file':
                if ($fileId > 0 && $path !== '') {
                    $size = is_readable($path) ? FileTypes::formatSize((int) filesize($path)) : '';
                    printf(
                        '<span class="crnd-col-file">%s</span><span class="crnd-col-size">%s</span>',
                        esc_html(wp_basename($path)),
                        esc_html($size)
                    );
                } elseif ($external !== '') {
                    printf(
                        '<span class="crnd-col-file"><span class="dashicons dashicons-admin-links" aria-hidden="true"></span> %s</span>',
                        esc_html((string) wp_parse_url($external, PHP_URL_HOST))
                    );
                } else {
                    echo '<span class="crnd-col-missing">' . esc_html__('No file', 'crnube-docs') . '</span>';
                }
                break;

            case 'crnd_downloads':
                echo esc_html(number_format_i18n((int) get_post_meta($postId, DocumentPostType::META_DOWNLOADS, true)));
                break;
        }
    }

    public function filters(string $postType): void
    {
        if ($postType !== DocumentPostType::NAME) {
            return;
        }

        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only list filters
        wp_dropdown_categories([
            'taxonomy'        => CategoryTaxonomy::NAME,
            'name'            => self::FILTER_CATEGORY,
            'show_option_all' => __('All categories', 'crnube-docs'),
            'hierarchical'    => true,
            'hide_empty'      => false,
            'selected'        => absint($_GET[self::FILTER_CATEGORY] ?? 0),
            'value_field'     => 'term_id',
        ]);

        $years = get_terms(['taxonomy' => YearTaxonomy::NAME, 'hide_empty' => true, 'orderby' => 'name', 'order' => 'DESC']);
        if (is_array($years) && $years !== []) {
            $selected = sanitize_text_field(wp_unslash($_GET[self::FILTER_YEAR] ?? ''));
            echo '<label for="crnd-filter-year" class="screen-reader-text">' . esc_html__('Filter by year', 'crnube-docs') . '</label>';
            echo '<select name="' . esc_attr(self::FILTER_YEAR) . '" id="crnd-filter-year">';
            echo '<option value="">' . esc_html__('All years', 'crnube-docs') . '</option>';
            foreach ($years as $year) {
                printf('<option value="%1$s" %2$s>%1$s</option>', esc_attr($year->slug), selected($selected, $year->slug, false));
            }
            echo '</select>';
        }
        // phpcs:enable
    }

    public function applyFilters(\WP_Query $query): void
    {
        if (!is_admin() || !$query->is_main_query() || $query->get('post_type') !== DocumentPostType::NAME) {
            return;
        }

        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only list filters
        $taxQuery = [];
        $category = absint($_GET[self::FILTER_CATEGORY] ?? 0);
        if ($category > 0) {
            $taxQuery[] = ['taxonomy' => CategoryTaxonomy::NAME, 'field' => 'term_id', 'terms' => $category, 'include_children' => true];
        }
        $year = sanitize_title(wp_unslash($_GET[self::FILTER_YEAR] ?? ''));
        if ($year !== '') {
            $taxQuery[] = ['taxonomy' => YearTaxonomy::NAME, 'field' => 'slug', 'terms' => $year];
        }
        // phpcs:enable

        if ($taxQuery !== []) {
            $query->set('tax_query', $taxQuery); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
        }
    }
}
