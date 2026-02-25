<?php
defined('ABSPATH') || die('No direct script access allowed.');

/**
 * Hooks into WordPress post/product deletion events and triggers
 * safe, settings-aware metadata deletion.
 */
class WP_Washer_Auto_Delete
{

    public function __construct()
    {
        $this->register_hooks();
    }

    private function register_hooks()
    {
        // Permanent deletion.
        add_action('before_delete_post', array($this, 'handle_post_delete'), 10, 1);

        // Trash (move to bin).
        add_action('wp_trash_post', array($this, 'handle_post_trash'), 10, 1);

        // WooCommerce product deletion (fires before_delete_post too but guard explicitly).
        if (class_exists('WooCommerce')) {
            add_action('woocommerce_before_delete_product', array($this, 'handle_product_delete'), 10, 1);
        }
    }

    /**
     * Handle permanent post deletion.
     */
    public function handle_post_delete($post_id)
    {
        if (!WP_Washer_Settings::get('enable_auto_delete', '0')) {
            return;
        }
        if (!WP_Washer_Settings::get('trigger_on_delete', '1')) {
            return;
        }
        $this->process_post_attachments($post_id);
    }

    /**
     * Handle post being moved to trash.
     */
    public function handle_post_trash($post_id)
    {
        if (!WP_Washer_Settings::get('enable_auto_delete', '0')) {
            return;
        }
        if (!WP_Washer_Settings::get('trigger_on_trash', '0')) {
            return;
        }
        $this->process_post_attachments($post_id);
    }

    /**
     * Handle WooCommerce product deletion.
     */
    public function handle_product_delete($post_id)
    {
        if (!WP_Washer_Settings::get('enable_auto_delete', '0')) {
            return;
        }
        if (!WP_Washer_Settings::get('apply_to_products', '1')) {
            return;
        }
        $this->process_post_attachments($post_id);
    }

    /**
     * Find all attachments tied to a post and clean them per settings.
     *
     * @param int $post_id The post being deleted.
     */
    private function process_post_attachments($post_id)
    {
        $post_id = absint($post_id);
        $post = get_post($post_id);

        if (!$post || 'attachment' === $post->post_type) {
            // Avoid recursion on attachment deletion.
            return;
        }

        // Check post type settings.
        if ('post' === $post->post_type && !WP_Washer_Settings::get('apply_to_posts', '1')) {
            return;
        }
        if ('page' === $post->post_type && !WP_Washer_Settings::get('apply_to_pages', '1')) {
            return;
        }
        if ('product' === $post->post_type && !WP_Washer_Settings::get('apply_to_products', '1')) {
            return;
        }

        // Collect attached media.
        $attachments = get_children(array(
            'post_parent' => $post_id,
            'post_type' => 'attachment',
            'fields' => 'ids',
            'numberposts' => -1,
        ));

        // Also include featured image.
        $thumbnail_id = get_post_thumbnail_id($post_id);
        if ($thumbnail_id) {
            $attachments[] = $thumbnail_id;
        }

        $attachments = array_unique(array_map('absint', $attachments));

        if (empty($attachments)) {
            return;
        }

        $delete_files = (bool) WP_Washer_Settings::get('delete_files', '0');
        $batch_key = 'auto_' . $post_id . '_' . time();

        foreach ($attachments as $attachment_id) {
            // Safety check.
            $check = WP_Washer_Safe_Check::check($attachment_id);
            if (!$check['safe']) {
                WP_Washer_Logger::log(
                    'auto_delete_skipped',
                    $attachment_id,
                    (string) get_attached_file($attachment_id),
                    'skipped',
                    'Still referenced: ' . implode('; ', $check['used_in'])
                );
                continue;
            }

            // Snapshot for undo.
            WP_Washer_Restore::snapshot($attachment_id, $batch_key, (string) get_attached_file($attachment_id));

            if ($delete_files) {
                WP_Washer_Media_Cleaner::delete_attachment($attachment_id);
            } else {
                WP_Washer_Media_Cleaner::delete_meta($attachment_id);
            }
        }
    }
}
