<?php
declare(strict_types=1);

namespace CRNube\Docs\Admin;

use CRNube\Docs\Domain\CategoryTaxonomy;
use CRNube\Docs\Frontend\Assets;
use CRNube\Docs\Frontend\Shortcode;

/**
 * Extra fields and columns on the categories screen: display order, and the shortcode
 * that shows each category, ready to copy.
 */
final class CategoryFields
{
    private const NONCE = 'crnd_category_nonce';

    public function register(): void
    {
        $tax = CategoryTaxonomy::NAME;
        add_action("{$tax}_add_form_fields", [$this, 'addField']);
        add_action("{$tax}_edit_form_fields", [$this, 'editField']);
        add_action("created_{$tax}", [$this, 'save']);
        add_action("edited_{$tax}", [$this, 'save']);
        add_filter("manage_edit-{$tax}_columns", [$this, 'columns']);
        add_filter("manage_{$tax}_custom_column", [$this, 'column'], 10, 3);
        add_action('admin_enqueue_scripts', [$this, 'enqueue']);
    }

    public function enqueue(string $hook): void
    {
        if (in_array($hook, ['edit-tags.php', 'term.php'], true) && get_current_screen()?->taxonomy === CategoryTaxonomy::NAME) {
            wp_enqueue_style('crnd-admin', CRND_URL . 'assets/css/admin.css', [], Assets::version('assets/css/admin.css'));
            wp_enqueue_script('crnd-admin-copy', CRND_URL . 'assets/js/admin-copy.js', [], Assets::version('assets/js/admin-copy.js'), true);
            wp_localize_script('crnd-admin-copy', 'crndCopy', ['copied' => __('Copied!', 'crnube-docs')]);
        }
    }

    public function addField(): void
    {
        wp_nonce_field('crnd_save_category', self::NONCE);
        ?>
        <div class="form-field">
            <label for="crnd-order"><?php esc_html_e('Order', 'crnube-docs'); ?></label>
            <input type="number" name="crnd_order" id="crnd-order" value="0" step="1">
            <p><?php esc_html_e('Categories are shown from the lowest number to the highest; equal numbers go alphabetically.', 'crnube-docs'); ?></p>
        </div>
        <?php
    }

    public function editField(\WP_Term $term): void
    {
        $order = (int) get_term_meta($term->term_id, CategoryTaxonomy::META_ORDER, true);
        ?>
        <tr class="form-field">
            <th scope="row"><label for="crnd-order"><?php esc_html_e('Order', 'crnube-docs'); ?></label></th>
            <td>
                <?php wp_nonce_field('crnd_save_category', self::NONCE); ?>
                <input type="number" name="crnd_order" id="crnd-order" value="<?php echo (int) $order; ?>" step="1">
                <p class="description"><?php esc_html_e('Categories are shown from the lowest number to the highest; equal numbers go alphabetically.', 'crnube-docs'); ?></p>
            </td>
        </tr>
        <tr class="form-field">
            <th scope="row"><?php esc_html_e('Shortcode', 'crnube-docs'); ?></th>
            <td><?php $this->shortcodeButton($term); ?></td>
        </tr>
        <?php
    }

    public function save(int $termId): void
    {
        if (!isset($_POST[self::NONCE]) || !wp_verify_nonce(sanitize_key(wp_unslash($_POST[self::NONCE])), 'crnd_save_category')) {
            return;
        }
        if (!current_user_can('edit_term', $termId) || !isset($_POST['crnd_order'])) {
            return;
        }
        update_term_meta($termId, CategoryTaxonomy::META_ORDER, (int) $_POST['crnd_order']);
    }

    /**
     * @param array<string, string> $columns
     * @return array<string, string>
     */
    public function columns(array $columns): array
    {
        unset($columns['slug']);
        $columns['crnd_order']     = __('Order', 'crnube-docs');
        $columns['crnd_shortcode'] = __('Shortcode', 'crnube-docs');
        return $columns;
    }

    public function column(string $content, string $column, int $termId): string
    {
        $term = get_term($termId, CategoryTaxonomy::NAME);
        if (!$term instanceof \WP_Term) {
            return $content;
        }
        if ($column === 'crnd_order') {
            return (string) (int) get_term_meta($termId, CategoryTaxonomy::META_ORDER, true);
        }
        if ($column === 'crnd_shortcode') {
            ob_start();
            $this->shortcodeButton($term);
            return (string) ob_get_clean();
        }
        return $content;
    }

    private function shortcodeButton(\WP_Term $term): void
    {
        $code = sprintf('[%s category="%s"]', Shortcode::TAG, $term->slug);
        printf(
            '<button type="button" class="crnd-copy" data-crnd-copy="%1$s" title="%2$s"><code>%3$s</code><span class="dashicons dashicons-clipboard" aria-hidden="true"></span></button>',
            esc_attr($code),
            esc_attr__('Copy shortcode', 'crnube-docs'),
            esc_html($code)
        );
    }
}
