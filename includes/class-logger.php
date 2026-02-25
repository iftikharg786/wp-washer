<?php
defined('ABSPATH') || die('No direct script access allowed.');

/**
 * Logs plugin operations to the wpwasher_log database table.
 */
class WP_Washer_Logger
{

    /**
     * Write a log entry.
     *
     * @param string $action_type   Action identifier (e.g. 'delete_meta', 'delete_file').
     * @param int    $attachment_id Attachment post ID.
     * @param string $file_path     Absolute path to the file (may be empty).
     * @param string $status        'success' | 'skipped' | 'error'.
     * @param string $detail        Human-readable detail / error message.
     */
    public static function log($action_type, $attachment_id, $file_path = '', $status = 'success', $detail = '')
    {
        // Respect the logging toggle.
        if (!WP_Washer_Settings::get('enable_logging', '1')) {
            return;
        }

        global $wpdb;

        $wpdb->insert(
            $wpdb->prefix . 'wpwasher_log',
            array(
                'action_type' => sanitize_text_field($action_type),
                'attachment_id' => absint($attachment_id),
                'file_path' => sanitize_text_field($file_path),
                'detail' => sanitize_textarea_field($detail),
                'status' => sanitize_text_field($status),
                'created_at' => current_time('mysql'),
            ),
            array('%s', '%d', '%s', '%s', '%s', '%s')
        );
    }

    /**
     * Retrieve log entries with optional filters.
     *
     * @param array $args {
     *     @type string $action_type  Filter by action type.
     *     @type string $status       Filter by status.
     *     @type string $date_from    MySQL date string.
     *     @type string $date_to      MySQL date string.
     *     @type int    $per_page     Items per page (default 50).
     *     @type int    $page         Page number (1-indexed).
     * }
     * @return array { 'items' => array, 'total' => int }
     */
    public static function get_logs(array $args = array())
    {
        global $wpdb;

        $defaults = array(
            'action_type' => '',
            'status' => '',
            'date_from' => '',
            'date_to' => '',
            'per_page' => 50,
            'page' => 1,
        );
        $args = wp_parse_args($args, $defaults);

        $where = array('1=1');
        $values = array();

        if ('' !== $args['action_type']) {
            $where[] = '`action_type` = %s';
            $values[] = sanitize_text_field($args['action_type']);
        }
        if ('' !== $args['status']) {
            $where[] = '`status` = %s';
            $values[] = sanitize_text_field($args['status']);
        }
        if ('' !== $args['date_from']) {
            $where[] = '`created_at` >= %s';
            $values[] = sanitize_text_field($args['date_from']);
        }
        if ('' !== $args['date_to']) {
            $where[] = '`created_at` <= %s';
            $values[] = sanitize_text_field($args['date_to']);
        }

        $where_sql = implode(' AND ', $where);
        $table = $wpdb->prefix . 'wpwasher_log';
        $offset = (absint($args['page']) - 1) * absint($args['per_page']);

        // Total count.
        if (!empty($values)) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
            $total = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM `{$table}` WHERE {$where_sql}", $values));
        } else {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $total = (int) $wpdb->get_var("SELECT COUNT(*) FROM `{$table}` WHERE {$where_sql}");
        }

        $limit_values = array_merge($values, array(absint($args['per_page']), $offset));
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
        $items = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM `{$table}` WHERE {$where_sql} ORDER BY `created_at` DESC LIMIT %d OFFSET %d",
                $limit_values
            ),
            ARRAY_A
        );

        return array(
            'items' => $items ? $items : array(),
            'total' => $total,
        );
    }

    /**
     * Export all logs as a CSV string.
     *
     * @return string
     */
    public static function export_csv()
    {
        global $wpdb;
        $table = $wpdb->prefix . 'wpwasher_log';
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $rows = $wpdb->get_results("SELECT * FROM `{$table}` ORDER BY `created_at` DESC", ARRAY_A);

        $csv = "ID,Action,Attachment ID,File Path,Detail,Status,Date\n";
        foreach ($rows as $row) {
            $csv .= implode(',', array_map(function ($v) {
                return '"' . str_replace('"', '""', $v) . '"';
            }, $row)) . "\n";
        }
        return $csv;
    }

    /**
     * Clear all log entries.
     */
    public static function clear_all()
    {
        global $wpdb;
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $wpdb->query("TRUNCATE TABLE `{$wpdb->prefix}wpwasher_log`");
    }
}
