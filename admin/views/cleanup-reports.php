<?php
defined('ABSPATH') || die('No direct script access allowed.');
if (!current_user_can('manage_options')) {
    wp_die(esc_html__('Access denied.', 'wp-washer'));
}
?>
<div class="wrap wpw-wrap">
    <h1 class="wpw-page-title"><span class="dashicons dashicons-chart-area"></span>
        <?php esc_html_e('Cleanup Reports', 'wp-washer'); ?>
    </h1>

    <!-- Undo Section -->
    <?php if (WP_Washer_Restore::has_snapshot()): ?>
        <div class="wpw-card wpw-card-highlight">
            <h3><span class="dashicons dashicons-undo"></span>
                <?php esc_html_e('Undo Last Operation', 'wp-washer'); ?>
            </h3>
            <p>
                <?php esc_html_e('A metadata snapshot from your most recent bulk operation is available. You can restore the removed metadata (note: permanently deleted files cannot be recovered).', 'wp-washer'); ?>
            </p>
            <button type="button" id="wpw-restore-last" class="wpw-btn wpw-btn-outline">
                <span class="dashicons dashicons-undo"></span>
                <?php esc_html_e('Restore Last Operation', 'wp-washer'); ?>
            </button>
            <div id="wpw-restore-result" class="wpw-inline-result" style="display:none;"></div>
        </div>
    <?php else: ?>
        <div class="wpw-notice wpw-notice-info">
            <span class="dashicons dashicons-info"></span>
            <?php esc_html_e('No restore snapshot available. Snapshots are created before each bulk operation.', 'wp-washer'); ?>
        </div>
    <?php endif; ?>

    <!-- Database Scan -->
    <div class="wpw-card">
        <h3><span class="dashicons dashicons-search"></span>
            <?php esc_html_e('Database Scan & Cleanup', 'wp-washer'); ?>
        </h3>
        <p>
            <?php esc_html_e('Scan for orphaned attachments and postmeta records that are no longer linked to any post. Review the report before confirming deletion.', 'wp-washer'); ?>
        </p>

        <button type="button" id="wpw-scan-btn" class="wpw-btn wpw-btn-primary">
            <span class="dashicons dashicons-search" id="wpw-scan-icon"></span>
            <span id="wpw-scan-label">
                <?php esc_html_e('Scan Now', 'wp-washer'); ?>
            </span>
        </button>

        <div id="wpw-scan-results" style="display:none;" class="wpw-scan-results">
            <div class="wpw-scan-grid">
                <div class="wpw-scan-stat">
                    <div class="wpw-scan-stat-number" id="wpw-orphan-att-count">0</div>
                    <div class="wpw-scan-stat-label">
                        <?php esc_html_e('Orphaned Attachments', 'wp-washer'); ?>
                    </div>
                </div>
                <div class="wpw-scan-stat">
                    <div class="wpw-scan-stat-number" id="wpw-orphan-meta-count">0</div>
                    <div class="wpw-scan-stat-label">
                        <?php esc_html_e('Orphaned Meta Rows', 'wp-washer'); ?>
                    </div>
                </div>
                <div class="wpw-scan-stat">
                    <div class="wpw-scan-stat-number" id="wpw-space-saved">0 B</div>
                    <div class="wpw-scan-stat-label">
                        <?php esc_html_e('Estimated Space', 'wp-washer'); ?>
                    </div>
                </div>
            </div>

            <div class="wpw-cleanup-options">
                <label class="wpw-toggle-label">
                    <div class="wpw-toggle-wrap">
                        <input type="checkbox" id="wpw-delete-att-files" value="1">
                        <span class="wpw-toggle-slider wpw-toggle-danger"></span>
                    </div>
                    <span>
                        <?php esc_html_e('Also Delete Orphaned Attachment Files', 'wp-washer'); ?>
                    </span>
                </label>
                <p class="wpw-setting-desc wpw-danger-text">
                    <?php esc_html_e('⚠ Only files confirmed as orphaned and unused will be deleted.', 'wp-washer'); ?>
                </p>
            </div>

            <button type="button" id="wpw-cleanup-btn" class="wpw-btn wpw-btn-danger">
                <span class="dashicons dashicons-trash"></span>
                <?php esc_html_e('Run Cleanup', 'wp-washer'); ?>
            </button>
            <div id="wpw-cleanup-result" class="wpw-inline-result" style="display:none;"></div>
        </div>
    </div>

    <!-- Recent Log Summary -->
    <div class="wpw-card">
        <h3><span class="dashicons dashicons-backup"></span>
            <?php esc_html_e('Recent Operations', 'wp-washer'); ?>
        </h3>
        <?php
        $log_data = WP_Washer_Logger::get_logs(array('per_page' => 10));
        $logs = $log_data['items'];
        if (empty($logs)):
            ?>
            <p class="wpw-empty">
                <?php esc_html_e('No operations logged yet.', 'wp-washer'); ?>
            </p>
        <?php else: ?>
            <table class="wpw-table">
                <thead>
                    <tr>
                        <th>
                            <?php esc_html_e('Action', 'wp-washer'); ?>
                        </th>
                        <th>
                            <?php esc_html_e('Attachment', 'wp-washer'); ?>
                        </th>
                        <th>
                            <?php esc_html_e('Status', 'wp-washer'); ?>
                        </th>
                        <th>
                            <?php esc_html_e('Detail', 'wp-washer'); ?>
                        </th>
                        <th>
                            <?php esc_html_e('Date', 'wp-washer'); ?>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $row): ?>
                        <tr>
                            <td><code><?php echo esc_html($row['action_type']); ?></code></td>
                            <td>
                                <?php echo esc_html('#' . $row['attachment_id']); ?>
                            </td>
                            <td><span class="wpw-status-badge wpw-status-<?php echo esc_attr($row['status']); ?>">
                                    <?php echo esc_html($row['status']); ?>
                                </span></td>
                            <td>
                                <?php echo esc_html($row['detail']); ?>
                            </td>
                            <td>
                                <?php echo esc_html($row['created_at']); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <p><a href="<?php echo esc_url(admin_url('admin.php?page=wp-washer-logs')); ?>" class="wpw-link">
                    <?php esc_html_e('View All Logs →', 'wp-washer'); ?>
                </a></p>
        <?php endif; ?>
    </div>
</div>