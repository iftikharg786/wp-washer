<?php
defined('ABSPATH') || die('No direct script access allowed.');

/**
 * Handles plugin activation, deactivation, and database table creation.
 */
class WP_Washer_Activator
{

    /**
     * Run on plugin activation.
     */
    public static function activate()
    {
        self::create_tables();
        self::set_defaults();
        flush_rewrite_rules();
    }

    /**
     * Run on plugin deactivation.
     */
    public static function deactivate()
    {
        flush_rewrite_rules();
    }

    /**
     * Create custom database tables.
     */
    private static function create_tables()
    {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        // Log table.
        $log_table = $wpdb->prefix . 'wpwasher_log';
        $log_sql = "CREATE TABLE IF NOT EXISTS `{$log_table}` (
            `id`            BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            `action_type`   VARCHAR(80)         NOT NULL DEFAULT '',
            `attachment_id` BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
            `file_path`     TEXT                NOT NULL,
            `detail`        TEXT                NOT NULL,
            `status`        VARCHAR(20)         NOT NULL DEFAULT 'success',
            `created_at`    DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `attachment_id` (`attachment_id`),
            KEY `action_type`   (`action_type`),
            KEY `created_at`    (`created_at`)
        ) {$charset_collate};";

        // Restore / undo table.
        $restore_table = $wpdb->prefix . 'wpwasher_restore';
        $restore_sql = "CREATE TABLE IF NOT EXISTS `{$restore_table}` (
            `id`            BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            `batch_key`     VARCHAR(64)         NOT NULL DEFAULT '',
            `attachment_id` BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
            `file_path`     TEXT                NOT NULL,
            `meta_snapshot` LONGTEXT            NOT NULL,
            `created_at`    DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `batch_key`     (`batch_key`),
            KEY `attachment_id` (`attachment_id`)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($log_sql);
        dbDelta($restore_sql);

        update_option('wpwasher_db_version', WP_WASHER_VERSION);
    }

    /**
     * Set default plugin options.
     */
    private static function set_defaults()
    {
        $defaults = array(
            'enable_auto_delete' => '0',
            'apply_to_posts' => '1',
            'apply_to_pages' => '1',
            'apply_to_products' => '1',
            'trigger_on_trash' => '0',
            'trigger_on_delete' => '1',
            'delete_files' => '0',
            'strip_exif' => '0',
            'enable_logging' => '1',
            'batch_size' => '30',
        );

        foreach ($defaults as $key => $value) {
            if (false === get_option('wpwasher_' . $key)) {
                add_option('wpwasher_' . $key, $value);
            }
        }
    }
}
