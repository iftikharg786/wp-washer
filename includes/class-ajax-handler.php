<?php
defined('ABSPATH') || die('No direct script access allowed.');

/**
 * Registers all AJAX endpoints and enforces security on every request.
 */
class WP_Washer_Ajax_Handler
{

    public function __construct()
    {
        $actions = array(
            'wpwasher_bulk_action',
            'wpwasher_get_progress',
            'wpwasher_scan_orphans',
            'wpwasher_confirm_cleanup',
            'wpwasher_restore_last',
            'wpwasher_save_settings',
            'wpwasher_export_log',
            'wpwasher_clear_log',
            'wpwasher_get_all_attachment_ids',
        );

        foreach ($actions as $action) {
            // Logged-in admin only (no_priv versions intentionally omitted).
            add_action('wp_ajax_' . $action, array($this, str_replace('wpwasher_', 'ajax_', $action)));
        }
    }

    // ── Security helper ───────────────────────────────────────────────────────

    private function verify_request($nonce_action = 'wpwasher_nonce')
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'wp-washer')), 403);
        }
        if (!check_ajax_referer($nonce_action, 'nonce', false)) {
            wp_send_json_error(array('message' => __('Security check failed.', 'wp-washer')), 403);
        }
    }

    // ── Endpoints ─────────────────────────────────────────────────────────────

    /**
     * Start a bulk action batch.
     * POST: nonce, action_type (delete_meta|delete_file|strip_exif|remove_thumbs|clean_orphan_meta), ids[]
     */
    public function ajax_bulk_action()
    {
        $this->verify_request();

        $action_type = isset($_POST['action_type']) ? sanitize_key($_POST['action_type']) : '';
        $raw_ids = isset($_POST['ids']) && is_array($_POST['ids']) ? $_POST['ids'] : array();
        $attachment_ids = array_map('absint', $raw_ids);
        $attachment_ids = array_filter($attachment_ids);

        $allowed_actions = array('delete_meta', 'delete_file', 'strip_exif', 'remove_thumbs', 'clean_orphan_meta');
        if (!in_array($action_type, $allowed_actions, true)) {
            wp_send_json_error(array('message' => __('Invalid action type.', 'wp-washer')), 400);
        }

        if (empty($attachment_ids)) {
            wp_send_json_error(array('message' => __('No attachments selected.', 'wp-washer')), 400);
        }

        $job_id = wp_generate_uuid4();
        WP_Washer_Batch_Processor::enqueue($job_id, $attachment_ids, $action_type);

        wp_send_json_success(array(
            'job_id' => $job_id,
            'total' => count($attachment_ids),
            'message' => __('Batch queued.', 'wp-washer'),
        ));
    }

    /**
     * Poll progress of a running batch.
     * POST: nonce, job_id
     */
    public function ajax_get_progress()
    {
        $this->verify_request();

        $job_id = isset($_POST['job_id']) ? sanitize_text_field(wp_unslash($_POST['job_id'])) : '';
        $progress = WP_Washer_Batch_Processor::get_progress($job_id);

        if (!$progress) {
            wp_send_json_error(array('message' => __('Job not found.', 'wp-washer')));
        }

        // Process next chunk if not done.
        if (!$progress['done']) {
            $progress = WP_Washer_Batch_Processor::process_chunk($job_id);
        }

        wp_send_json_success($progress);
    }

    /**
     * Scan database for orphaned records.
     * POST: nonce
     */
    public function ajax_scan_orphans()
    {
        $this->verify_request();

        $report = WP_Washer_Db_Scanner::full_scan();
        $report['estimated_size_human'] = size_format($report['estimated_bytes']);

        wp_send_json_success($report);
    }

    /**
     * Run confirmed database cleanup.
     * POST: nonce, delete_attachments (0|1), meta_ids[], attachment_ids[]
     */
    public function ajax_confirm_cleanup()
    {
        $this->verify_request();

        $delete_attachments = absint(isset($_POST['delete_attachments']) ? $_POST['delete_attachments'] : 0);
        $raw_meta_ids = isset($_POST['meta_ids']) && is_array($_POST['meta_ids']) ? $_POST['meta_ids'] : array();
        $raw_att_ids = isset($_POST['attachment_ids']) && is_array($_POST['attachment_ids']) ? $_POST['attachment_ids'] : array();
        $meta_ids = array_map('absint', $raw_meta_ids);
        $att_ids = array_map('absint', $raw_att_ids);

        $meta_deleted = WP_Washer_Db_Scanner::delete_orphaned_postmeta($meta_ids);

        $files_deleted = 0;
        if ($delete_attachments) {
            foreach ($att_ids as $id) {
                $result = WP_Washer_Media_Cleaner::delete_attachment($id);
                if ($result['success']) {
                    $files_deleted++;
                }
            }
        }

        wp_send_json_success(array(
            'meta_deleted' => $meta_deleted,
            'files_deleted' => $files_deleted,
            'message' => sprintf(
                /* translators: 1: meta rows, 2: files */
                __('Cleanup complete. %1$d meta rows and %2$d files removed.', 'wp-washer'),
                $meta_deleted,
                $files_deleted
            ),
        ));
    }

    /**
     * Restore the last bulk operation metadata.
     * POST: nonce
     */
    public function ajax_restore_last()
    {
        $this->verify_request();

        $result = WP_Washer_Restore::restore_last();
        wp_send_json_success($result);
    }

    /**
     * Save plugin settings.
     * POST: nonce + all settings keys.
     */
    public function ajax_save_settings()
    {
        $this->verify_request('wpwasher_settings_nonce');

        $post_data = isset($_POST) ? wp_unslash($_POST) : array();
        WP_Washer_Settings::save_from_request($post_data);

        wp_send_json_success(array('message' => __('Settings saved.', 'wp-washer')));
    }

    /**
     * Export the log as a CSV download.
     * POST: nonce
     */
    public function ajax_export_log()
    {
        $this->verify_request();

        $csv = WP_Washer_Logger::export_csv();
        $filename = 'wp-washer-log-' . gmdate('Y-m-d') . '.csv';

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename);
        header('Pragma: no-cache');
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo $csv;
        wp_die();
    }

    /**
     * Clear all log entries.
     * POST: nonce
     */
    public function ajax_clear_log()
    {
        $this->verify_request();
        WP_Washer_Logger::clear_all();
        wp_send_json_success(array('message' => __('Log cleared.', 'wp-washer')));
    }

    /**
     * Return all attachment IDs in the library (for "Select All in Library").
     * POST: nonce
     */
    public function ajax_get_all_attachment_ids()
    {
        $this->verify_request();

        global $wpdb;
        $ids = $wpdb->get_col(
            "SELECT `ID` FROM `{$wpdb->posts}` WHERE `post_type` = 'attachment' AND `post_status` = 'inherit'"
        );

        wp_send_json_success(array('ids' => array_map('absint', $ids)));
    }
}
