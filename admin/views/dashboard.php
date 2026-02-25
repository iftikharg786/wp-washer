<?php
defined('ABSPATH') || die('No direct script access allowed.');
if (!current_user_can('manage_options')) {
    wp_die(esc_html__('Access denied.', 'wp-washer'));
}

global $wpdb;

// Quick stats.
$total_attachments = (int) $wpdb->get_var("SELECT COUNT(*) FROM `{$wpdb->posts}` WHERE `post_type` = 'attachment'");
$orphan_attachments = count(WP_Washer_Db_Scanner::scan_orphaned_attachments(50));
$orphan_meta = count(WP_Washer_Db_Scanner::scan_orphaned_postmeta(100));
$last_log = WP_Washer_Logger::get_logs(array('per_page' => 1));
$last_op = !empty($last_log['items']) ? $last_log['items'][0]['created_at'] : __('Never', 'wp-washer');
$has_snapshot = WP_Washer_Restore::has_snapshot();
?>
<div class="wrap wpw-wrap">
    <div class="wpw-header">
        <div class="wpw-logo">
            <span class="dashicons dashicons-trash wpw-logo-icon"></span>
            <div>
                <h1>
                    <?php esc_html_e('WP Washer', 'wp-washer'); ?>
                </h1>
                <p>
                    <?php esc_html_e('Media metadata management & database cleanup', 'wp-washer'); ?>
                </p>
            </div>
        </div>
        <div class="wpw-header-meta">
            <span>
                <?php esc_html_e('By Yellowhoster SMC Limited', 'wp-washer'); ?>
            </span>
            <span class="wpw-version">v
                <?php echo esc_html(WP_WASHER_VERSION); ?>
            </span>
        </div>
    </div>

    <div class="wpw-dashboard-grid">
        <div class="wpw-stat-card">
            <div class="wpw-stat-icon dashicons dashicons-images-alt2"></div>
            <div class="wpw-stat-value">
                <?php echo esc_html(number_format_i18n($total_attachments)); ?>
            </div>
            <div class="wpw-stat-label">
                <?php esc_html_e('Total Attachments', 'wp-washer'); ?>
            </div>
        </div>

        <div class="wpw-stat-card <?php echo $orphan_attachments > 0 ? 'wpw-stat-warn' : ''; ?>">
            <div class="wpw-stat-icon dashicons dashicons-warning"></div>
            <div class="wpw-stat-value">
                <?php echo esc_html(number_format_i18n($orphan_attachments)); ?>+
            </div>
            <div class="wpw-stat-label">
                <?php esc_html_e('Orphaned Attachments', 'wp-washer'); ?>
            </div>
        </div>

        <div class="wpw-stat-card <?php echo $orphan_meta > 0 ? 'wpw-stat-warn' : ''; ?>">
            <div class="wpw-stat-icon dashicons dashicons-database"></div>
            <div class="wpw-stat-value">
                <?php echo esc_html(number_format_i18n($orphan_meta)); ?>+
            </div>
            <div class="wpw-stat-label">
                <?php esc_html_e('Orphaned Meta Rows', 'wp-washer'); ?>
            </div>
        </div>

        <div class="wpw-stat-card">
            <div class="wpw-stat-icon dashicons dashicons-clock"></div>
            <div class="wpw-stat-value wpw-stat-date">
                <?php echo esc_html($last_op); ?>
            </div>
            <div class="wpw-stat-label">
                <?php esc_html_e('Last Operation', 'wp-washer'); ?>
            </div>
        </div>
    </div>

    <div class="wpw-quick-actions">
        <h2>
            <?php esc_html_e('Quick Actions', 'wp-washer'); ?>
        </h2>
        <div class="wpw-action-cards">
            <a href="<?php echo esc_url(admin_url('admin.php?page=wp-washer-bulk-cleaner')); ?>"
                class="wpw-action-card">
                <span class="dashicons dashicons-editor-removeformatting"></span>
                <strong>
                    <?php esc_html_e('Bulk Cleaner', 'wp-washer'); ?>
                </strong>
                <span>
                    <?php esc_html_e('Select and clean media files', 'wp-washer'); ?>
                </span>
            </a>
            <a href="<?php echo esc_url(admin_url('admin.php?page=wp-washer-reports')); ?>" class="wpw-action-card">
                <span class="dashicons dashicons-chart-area"></span>
                <strong>
                    <?php esc_html_e('Scan & Clean', 'wp-washer'); ?>
                </strong>
                <span>
                    <?php esc_html_e('Find orphaned records', 'wp-washer'); ?>
                </span>
            </a>
            <a href="<?php echo esc_url(admin_url('admin.php?page=wp-washer-settings')); ?>"
                class="wpw-action-card">
                <span class="dashicons dashicons-admin-settings"></span>
                <strong>
                    <?php esc_html_e('Settings', 'wp-washer'); ?>
                </strong>
                <span>
                    <?php esc_html_e('Configure auto-delete and options', 'wp-washer'); ?>
                </span>
            </a>
            <a href="<?php echo esc_url(admin_url('admin.php?page=wp-washer-logs')); ?>" class="wpw-action-card">
                <span class="dashicons dashicons-list-view"></span>
                <strong>
                    <?php esc_html_e('View Logs', 'wp-washer'); ?>
                </strong>
                <span>
                    <?php esc_html_e('Review operation history', 'wp-washer'); ?>
                </span>
            </a>
        </div>
    </div>

    <?php if ($has_snapshot): ?>
        <div class="wpw-notice wpw-notice-info">
            <span class="dashicons dashicons-undo"></span>
            <?php esc_html_e('A restore snapshot is available from your last operation.', 'wp-washer'); ?>
            <a href="<?php echo esc_url(admin_url('admin.php?page=wp-washer-reports')); ?>">
                <?php esc_html_e('Go to Reports to undo →', 'wp-washer'); ?>
            </a>
        </div>
    <?php endif; ?>
</div>