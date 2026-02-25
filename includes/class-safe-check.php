<?php
defined('ABSPATH') || die('No direct script access allowed.');

/**
 * Checks whether an attachment is safely deletable.
 * Returns a report of all detected references so nothing important is lost.
 */
class WP_Washer_Safe_Check
{

    /**
     * Check if an attachment is referenced anywhere in the site.
     *
     * @param int $attachment_id Attachment post ID.
     * @return array {
     *     @type bool   $safe    True if no references found.
     *     @type array  $used_in List of reference descriptions.
     * }
     */
    public static function check($attachment_id)
    {
        $attachment_id = absint($attachment_id);
        $used_in = array();

        $attachment = get_post($attachment_id);
        if (!$attachment || 'attachment' !== $attachment->post_type) {
            return array('safe' => false, 'used_in' => array('Invalid attachment ID'));
        }

        $file_url = wp_get_attachment_url($attachment_id);

        // 1. Featured image on other posts.
        $used_in = array_merge($used_in, self::check_featured_image($attachment_id));

        // 2. Post content (HTML src references).
        if ($file_url) {
            $used_in = array_merge($used_in, self::check_post_content($file_url));
        }

        // 3. WooCommerce product gallery.
        $used_in = array_merge($used_in, self::check_wc_gallery($attachment_id));

        // 4. Page-builder meta (Elementor, WPBakery, ACF, Gutenberg).
        $used_in = array_merge($used_in, self::check_postmeta_references($attachment_id));

        return array(
            'safe' => empty($used_in),
            'used_in' => $used_in,
        );
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    /**
     * Find posts that use this attachment as their featured image.
     */
    private static function check_featured_image($attachment_id)
    {
        global $wpdb;
        $references = array();

        $post_ids = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT `post_id` FROM `{$wpdb->postmeta}`
                 WHERE `meta_key` = '_thumbnail_id'
                   AND `meta_value` = %s",
                (string) $attachment_id
            )
        );

        foreach ($post_ids as $pid) {
            $post = get_post(absint($pid));
            if ($post) {
                /* translators: 1: post type, 2: post title, 3: post ID */
                $references[] = sprintf(__('Featured image on %1$s "%2$s" (#%3$d)', 'wp-washer'), $post->post_type, $post->post_title, $post->ID);
            }
        }

        return $references;
    }

    /**
     * Find posts whose content HTML contains the attachment URL.
     */
    private static function check_post_content($file_url)
    {
        global $wpdb;
        $references = array();

        // Escape % and _ for LIKE query.
        $like = '%' . $wpdb->esc_like($file_url) . '%';

        $post_ids = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT `ID` FROM `{$wpdb->posts}`
                 WHERE `post_content` LIKE %s
                   AND `post_status` != 'trash'",
                $like
            )
        );

        foreach ($post_ids as $pid) {
            $post = get_post(absint($pid));
            if ($post) {
                /* translators: 1: post type, 2: post title, 3: post ID */
                $references[] = sprintf(__('Embedded in %1$s content "%2$s" (#%3$d)', 'wp-washer'), $post->post_type, $post->post_title, $post->ID);
            }
        }

        return $references;
    }

    /**
     * Check WooCommerce product_image_gallery meta.
     */
    private static function check_wc_gallery($attachment_id)
    {
        global $wpdb;
        $references = array();

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT `post_id` FROM `{$wpdb->postmeta}`
                 WHERE `meta_key` = '_product_image_gallery'
                   AND FIND_IN_SET(%s, `meta_value`) > 0",
                (string) $attachment_id
            ),
            ARRAY_A
        );

        foreach ($rows as $row) {
            $post = get_post(absint($row['post_id']));
            if ($post) {
                /* translators: 1: post title, 2: post ID */
                $references[] = sprintf(__('WooCommerce gallery on product "%1$s" (#%2$d)', 'wp-washer'), $post->post_title, $post->ID);
            }
        }

        return $references;
    }

    /**
     * Check serialized postmeta (Elementor, WPBakery, ACF, Gutenberg) for attachment ID.
     */
    private static function check_postmeta_references($attachment_id)
    {
        global $wpdb;
        $references = array();

        $like = '%' . $wpdb->esc_like('"' . $attachment_id . '"') . '%';

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT DISTINCT `post_id`, `meta_key` FROM `{$wpdb->postmeta}`
                 WHERE `meta_value` LIKE %s
                   AND `meta_key` NOT IN ('_thumbnail_id','_product_image_gallery')",
                $like
            ),
            ARRAY_A
        );

        foreach ($rows as $row) {
            $post = get_post(absint($row['post_id']));
            if ($post) {
                /* translators: 1: meta key, 2: post title, 3: post ID */
                $references[] = sprintf(__('Referenced in meta "%1$s" on "%2$s" (#%3$d)', 'wp-washer'), $row['meta_key'], $post->post_title, $post->ID);
            }
        }

        return $references;
    }
}
