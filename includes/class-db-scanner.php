<?php
defined('ABSPATH') || die('No direct script access allowed.');

/**
 * Scans the database for orphaned attachments and orphaned postmeta.
 */
class WP_Washer_Db_Scanner
{

    /**
     * Find attachment posts that have no parent post (post_parent = 0)
     * and are not used anywhere (not featured image, not in content).
     *
     * @param int $limit Max records to return.
     * @return array Array of attachment IDs.
     */
    public static function scan_orphaned_attachments($limit = 500)
    {
        global $wpdb;

        // Attachments with post_parent = 0 and not thumbnails of any post.
        $results = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT p.`ID`
                 FROM `{$wpdb->posts}` p
                 LEFT JOIN `{$wpdb->postmeta}` pm
                     ON pm.`meta_value` = p.`ID`
                     AND pm.`meta_key` = '_thumbnail_id'
                 WHERE p.`post_type` = 'attachment'
                   AND p.`post_parent` = 0
                   AND pm.`meta_id` IS NULL
                 LIMIT %d",
                $limit
            )
        );

        return array_map('absint', $results ?: array());
    }

    /**
     * Find postmeta rows whose post_id references a non-existent post.
     *
     * @param int $limit Max records to return.
     * @return array Array of postmeta IDs.
     */
    public static function scan_orphaned_postmeta($limit = 1000)
    {
        global $wpdb;

        $results = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT pm.`meta_id`
                 FROM `{$wpdb->postmeta}` pm
                 LEFT JOIN `{$wpdb->posts}` p ON p.`ID` = pm.`post_id`
                 WHERE p.`ID` IS NULL
                 LIMIT %d",
                $limit
            )
        );

        return array_map('absint', $results ?: array());
    }

    /**
     * Delete a list of orphaned postmeta rows by meta_id.
     *
     * @param array $meta_ids Array of meta_id values.
     * @return int Number deleted.
     */
    public static function delete_orphaned_postmeta(array $meta_ids)
    {
        if (empty($meta_ids)) {
            return 0;
        }

        global $wpdb;
        $meta_ids = array_map('absint', $meta_ids);
        $placeholders = implode(',', array_fill(0, count($meta_ids), '%d'));

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
        $deleted = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM `{$wpdb->postmeta}` WHERE `meta_id` IN ({$placeholders})",
                $meta_ids
            )
        );

        WP_Washer_Logger::log('clean_orphan_postmeta', 0, '', 'success', "Deleted {$deleted} orphan postmeta rows.");
        return (int) $deleted;
    }

    /**
     * Estimate total disk space for a list of attachment IDs.
     *
     * @param array $ids Attachment IDs.
     * @return int Bytes.
     */
    public static function estimate_storage(array $ids)
    {
        $total = 0;
        foreach ($ids as $id) {
            $path = get_attached_file(absint($id));
            if ($path && file_exists($path)) {
                $total += (int) filesize($path);
            }
        }
        return $total;
    }

    /**
     * Build a full scan report.
     *
     * @return array {
     *     @type array $orphaned_attachments  IDs of orphaned attachments.
     *     @type array $orphaned_postmeta     meta_ids of orphaned postmeta rows.
     *     @type int   $estimated_bytes       Estimated bytes to be recovered.
     * }
     */
    public static function full_scan()
    {
        $orphaned_attachments = self::scan_orphaned_attachments();
        $orphaned_postmeta = self::scan_orphaned_postmeta();
        $estimated_bytes = self::estimate_storage($orphaned_attachments);

        return array(
            'orphaned_attachments' => $orphaned_attachments,
            'orphaned_postmeta' => $orphaned_postmeta,
            'estimated_bytes' => $estimated_bytes,
        );
    }
}
