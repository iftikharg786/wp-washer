<?php
defined('ABSPATH') || die('No direct script access allowed.');

/**
 * Performs the actual media cleaning operations.
 * All public methods accept a single attachment ID and return a result array.
 */
class WP_Washer_Media_Cleaner
{

    /**
     * Delete all postmeta for an attachment (keeps the file and attachment post).
     *
     * @param int $id Attachment ID.
     * @return array { 'success' => bool, 'message' => string }
     */
    public static function delete_meta($id)
    {
        $id = absint($id);
        if (!self::valid_attachment($id)) {
            return array('success' => false, 'message' => "#{$id} is not a valid attachment.");
        }

        global $wpdb;
        $deleted = $wpdb->delete($wpdb->postmeta, array('post_id' => $id), array('%d'));

        WP_Washer_Logger::log('delete_meta', $id, self::get_path($id), 'success', "Deleted {$deleted} meta rows.");
        return array('success' => true, 'message' => "Metadata deleted for attachment #{$id}.");
    }

    /**
     * Permanently delete attachment (file + postmeta + attachment record).
     *
     * @param int $id Attachment ID.
     * @return array
     */
    public static function delete_attachment($id)
    {
        $id = absint($id);
        if (!self::valid_attachment($id)) {
            return array('success' => false, 'message' => "#{$id} is not a valid attachment.");
        }

        // Security: ensure file is inside uploads directory.
        $file_path = get_attached_file($id);
        if (!self::is_safe_path($file_path)) {
            WP_Washer_Logger::log('delete_file', $id, (string) $file_path, 'error', 'Path traversal attempt blocked.');
            return array('success' => false, 'message' => "File path outside uploads directory. Blocked.");
        }

        $result = wp_delete_attachment($id, true);
        if (false === $result || null === $result) {
            WP_Washer_Logger::log('delete_file', $id, (string) $file_path, 'error', 'wp_delete_attachment() returned false.');
            return array('success' => false, 'message' => "Failed to delete attachment #{$id}.");
        }

        WP_Washer_Logger::log('delete_file', $id, (string) $file_path, 'success', 'Attachment permanently deleted.');
        return array('success' => true, 'message' => "Attachment #{$id} permanently deleted.");
    }

    /**
     * Strip EXIF data from an image attachment.
     *
     * @param int $id Attachment ID.
     * @return array
     */
    public static function strip_exif($id)
    {
        $id = absint($id);
        if (!self::valid_attachment($id)) {
            return array('success' => false, 'message' => "#{$id} is not a valid attachment.");
        }

        $file_path = get_attached_file($id);
        if (!self::is_safe_path($file_path) || !file_exists($file_path)) {
            return array('success' => false, 'message' => "File not found or unsafe path for #{$id}.");
        }

        $mime = get_post_mime_type($id);
        if (!in_array($mime, array('image/jpeg', 'image/tiff'), true)) {
            return array('success' => false, 'message' => "EXIF stripping only applies to JPEG/TIFF. Skipped #{$id}.");
        }

        // Use WordPress image editor to re-save without EXIF.
        $editor = wp_get_image_editor($file_path);
        if (is_wp_error($editor)) {
            WP_Washer_Logger::log('strip_exif', $id, $file_path, 'error', $editor->get_error_message());
            return array('success' => false, 'message' => $editor->get_error_message());
        }

        $saved = $editor->save($file_path);
        if (is_wp_error($saved)) {
            WP_Washer_Logger::log('strip_exif', $id, $file_path, 'error', $saved->get_error_message());
            return array('success' => false, 'message' => $saved->get_error_message());
        }

        // Remove EXIF-related postmeta.
        delete_post_meta($id, '_wp_attachment_image_exif');

        WP_Washer_Logger::log('strip_exif', $id, $file_path, 'success', 'EXIF stripped and file re-saved.');
        return array('success' => true, 'message' => "EXIF stripped from attachment #{$id}.");
    }

    /**
     * Delete generated thumbnail files that are no longer registered in active image sizes.
     *
     * @param int $id Attachment ID.
     * @return array
     */
    public static function remove_unused_thumbnails($id)
    {
        $id = absint($id);
        if (!self::valid_attachment($id)) {
            return array('success' => false, 'message' => "#{$id} is not a valid attachment.");
        }

        $meta = wp_get_attachment_metadata($id);
        if (empty($meta['sizes']) || empty($meta['file'])) {
            return array('success' => true, 'message' => "No thumbnails to remove for #{$id}.");
        }

        $upload_dir = wp_upload_dir();
        $base_dir = $upload_dir['basedir'];
        $file_dir = trailingslashit($base_dir . '/' . dirname($meta['file']));
        $active_sizes = get_intermediate_image_sizes();
        $removed = 0;

        foreach ($meta['sizes'] as $size_name => $size_info) {
            if (!in_array($size_name, $active_sizes, true)) {
                $thumb_path = $file_dir . $size_info['file'];
                if (self::is_safe_path($thumb_path) && file_exists($thumb_path)) {
                    wp_delete_file($thumb_path);
                    $removed++;
                }
                unset($meta['sizes'][$size_name]);
            }
        }

        if ($removed > 0) {
            wp_update_attachment_metadata($id, $meta);
        }

        WP_Washer_Logger::log('remove_thumbs', $id, self::get_path($id), 'success', "Removed {$removed} unused thumbnails.");
        return array('success' => true, 'message' => "Removed {$removed} unused thumbnails for attachment #{$id}.");
    }

    /**
     * Delete orphaned wp_postmeta rows where the parent post no longer exists.
     *
     * @param int $id Attachment ID.
     * @return array
     */
    public static function clean_orphaned_postmeta($id)
    {
        $id = absint($id);
        global $wpdb;

        // Check the attachment post itself exists.
        $post_exists = $wpdb->get_var(
            $wpdb->prepare("SELECT COUNT(*) FROM `{$wpdb->posts}` WHERE `ID` = %d", $id)
        );

        if (!$post_exists) {
            // The attachment itself is an orphan — clean its meta.
            $deleted = $wpdb->delete($wpdb->postmeta, array('post_id' => $id), array('%d'));
            WP_Washer_Logger::log('clean_orphan_meta', $id, '', 'success', "Orphan: Deleted {$deleted} meta rows for non-existent post #{$id}.");
            return array('success' => true, 'message' => "Cleaned orphaned meta for post #{$id}.");
        }

        return array('success' => true, 'message' => "Post #{$id} exists — no orphan meta to clean.");
    }

    // ── Private helpers ────────────────────────────────────────────────────────

    private static function valid_attachment($id)
    {
        return 'attachment' === get_post_type($id);
    }

    /**
     * Confirm the resolved path is inside the uploads directory.
     */
    public static function is_safe_path($path)
    {
        if (empty($path)) {
            return false;
        }
        $real = realpath($path);
        $uploads = realpath(WP_WASHER_UPLOADS);
        if (false === $real || false === $uploads) {
            // realpath fails if file doesn't exist yet — fallback to string check.
            $normalized_uploads = wp_normalize_path(WP_WASHER_UPLOADS);
            $normalized_path = wp_normalize_path($path);
            return (0 === strpos($normalized_path, $normalized_uploads));
        }
        return (0 === strpos($real, $uploads));
    }

    private static function get_path($id)
    {
        return (string) get_attached_file($id);
    }
}
