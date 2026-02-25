<?php
defined('ABSPATH') || die('No direct script access allowed.');

/**
 * Stores, retrieves, and restores metadata snapshots before bulk operations.
 */
class WP_Washer_Restore
{

    /**
     * Snapshot all postmeta for an attachment before a bulk operation.
     *
     * @param int    $attachment_id Attachment post ID.
     * @param string $batch_key     Unique batch identifier (pass same key for all items in one batch).
     * @param string $file_path     Absolute file path for reference.
     */
    public static function snapshot($attachment_id, $batch_key, $file_path = '')
    {
        global $wpdb;

        $attachment_id = absint($attachment_id);
        $meta = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT `meta_key`, `meta_value` FROM `{$wpdb->postmeta}` WHERE `post_id` = %d",
                $attachment_id
            ),
            ARRAY_A
        );

        $wpdb->insert(
            $wpdb->prefix . 'wpwasher_restore',
            array(
                'batch_key' => sanitize_text_field($batch_key),
                'attachment_id' => $attachment_id,
                'file_path' => sanitize_text_field($file_path),
                'meta_snapshot' => wp_json_encode($meta),
                'created_at' => current_time('mysql'),
            ),
            array('%s', '%d', '%s', '%s', '%s')
        );
    }

    /**
     * Restore metadata for all attachments in the most recent batch.
     *
     * @return array { 'restored' => int, 'errors' => array }
     */
    public static function restore_last()
    {
        global $wpdb;

        // Find most recent batch key.
        $batch_key = $wpdb->get_var(
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            "SELECT `batch_key` FROM `{$wpdb->prefix}wpwasher_restore`
             ORDER BY `created_at` DESC LIMIT 1"
        );

        if (!$batch_key) {
            return array('restored' => 0, 'errors' => array('No restore snapshot available.'));
        }

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM `{$wpdb->prefix}wpwasher_restore` WHERE `batch_key` = %s",
                $batch_key
            ),
            ARRAY_A
        );

        $restored = 0;
        $errors = array();

        foreach ($rows as $row) {
            $attachment_id = absint($row['attachment_id']);
            $meta_items = json_decode($row['meta_snapshot'], true);

            if (!is_array($meta_items)) {
                $errors[] = "Invalid snapshot for attachment #{$attachment_id}.";
                continue;
            }

            // Re-insert all meta that no longer exists.
            foreach ($meta_items as $meta) {
                $existing = get_post_meta($attachment_id, $meta['meta_key']);
                if (empty($existing)) {
                    add_post_meta($attachment_id, sanitize_text_field($meta['meta_key']), maybe_unserialize($meta['meta_value']));
                }
            }

            WP_Washer_Logger::log('restore_meta', $attachment_id, $row['file_path'], 'success', 'Metadata restored from snapshot.');
            $restored++;
        }

        // Remove used snapshots.
        $wpdb->delete(
            $wpdb->prefix . 'wpwasher_restore',
            array('batch_key' => $batch_key),
            array('%s')
        );

        return array('restored' => $restored, 'errors' => $errors);
    }

    /**
     * Check if any undo snapshot is available.
     *
     * @return bool
     */
    public static function has_snapshot()
    {
        global $wpdb;
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        return (bool) $wpdb->get_var("SELECT COUNT(*) FROM `{$wpdb->prefix}wpwasher_restore`");
    }

    /**
     * Prune snapshots older than 7 days.
     */
    public static function prune_old_snapshots()
    {
        global $wpdb;
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM `{$wpdb->prefix}wpwasher_restore` WHERE `created_at` < %s",
                gmdate('Y-m-d H:i:s', strtotime('-7 days'))
            )
        );
    }
}
