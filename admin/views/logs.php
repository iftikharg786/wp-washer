<?php
defined('ABSPATH') || die('No direct script access allowed.');
if (!current_user_can('manage_options')) {
    wp_die(esc_html__('Access denied.', 'wp-washer'));
}

$current_page = isset($_GET['wpw_paged']) ? max(1, absint($_GET['wpw_paged'])) : 1; // phpcs:ignore WordPress.Security.NonceVerification
$filter_action = isset($_GET['wpw_action']) ? sanitize_key($_GET['wpw_action']) : ''; // phpcs:ignore WordPress.Security.NonceVerification
$filter_status = isset($_GET['wpw_status']) ? sanitize_key($_GET['wpw_status']) : ''; // phpcs:ignore WordPress.Security.NonceVerification

$log_data = WP_Washer_Logger::get_logs(array(
    'page' => $current_page,
    'per_page' => 50,
    'action_type' => $filter_action,
    'status' => $filter_status,
));

$logs = $log_data['items'];
$total = $log_data['total'];
$total_pages = ceil($total / 50);
?>
<div class="wrap wpw-wrap">
    <h1 class="wpw-page-title">
        <span class="dashicons dashicons-list-view"></span>
        <?php esc_html_e('Operation Logs', 'wp-washer'); ?>
        <span class="wpw-badge wpw-badge-sm">
            <?php echo esc_html(number_format_i18n($total)); ?>
        </span>
    </h1>

    <div class="wpw-log-toolbar">
        <form method="get" class="wpw-filter-form">
            <input type="hidden" name="page" value="wp-washer-logs">

            <select name="wpw_action" class="wpw-select wpw-select-sm">
                <option value="">
                    <?php esc_html_e('All Actions', 'wp-washer'); ?>
                </option>
                <?php
                $actions = array('delete_meta', 'delete_file', 'strip_exif', 'remove_thumbs', 'clean_orphan_meta', 'auto_delete_skipped', 'restore_meta', 'clean_orphan_postmeta');
                foreach ($actions as $a):
                    ?>
                    <option value="<?php echo esc_attr($a); ?>" <?php selected($filter_action, $a); ?>>
                        <?php echo esc_html($a); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <select name="wpw_status" class="wpw-select wpw-select-sm">
                <option value="">
                    <?php esc_html_e('All Statuses', 'wp-washer'); ?>
                </option>
                <option value="success" <?php selected($filter_status, 'success'); ?>>
                    <?php esc_html_e('Success', 'wp-washer'); ?>
                </option>
                <option value="skipped" <?php selected($filter_status, 'skipped'); ?>>
                    <?php esc_html_e('Skipped', 'wp-washer'); ?>
                </option>
                <option value="error" <?php selected($filter_status, 'error'); ?>>
                    <?php esc_html_e('Error', 'wp-washer'); ?>
                </option>
            </select>

            <button type="submit" class="wpw-btn wpw-btn-outline wpw-btn-sm">
                <span class="dashicons dashicons-filter"></span>
                <?php esc_html_e('Filter', 'wp-washer'); ?>
            </button>
        </form>

        <div class="wpw-log-actions">
            <button type="button" id="wpw-export-log" class="wpw-btn wpw-btn-outline wpw-btn-sm">
                <span class="dashicons dashicons-download"></span>
                <?php esc_html_e('Export CSV', 'wp-washer'); ?>
            </button>
            <button type="button" id="wpw-clear-log" class="wpw-btn wpw-btn-danger wpw-btn-sm">
                <span class="dashicons dashicons-trash"></span>
                <?php esc_html_e('Clear All Logs', 'wp-washer'); ?>
            </button>
        </div>
    </div>

    <?php if (empty($logs)): ?>
        <div class="wpw-card wpw-empty-state">
            <span class="dashicons dashicons-list-view wpw-empty-icon"></span>
            <p>
                <?php esc_html_e('No log entries found.', 'wp-washer'); ?>
            </p>
        </div>
    <?php else: ?>
        <div class="wpw-card wpw-card-flush">
            <table class="wpw-table wpw-log-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>
                            <?php esc_html_e('Action', 'wp-washer'); ?>
                        </th>
                        <th>
                            <?php esc_html_e('Attachment ID', 'wp-washer'); ?>
                        </th>
                        <th>
                            <?php esc_html_e('File Path', 'wp-washer'); ?>
                        </th>
                        <th>
                            <?php esc_html_e('Detail', 'wp-washer'); ?>
                        </th>
                        <th>
                            <?php esc_html_e('Status', 'wp-washer'); ?>
                        </th>
                        <th>
                            <?php esc_html_e('Date', 'wp-washer'); ?>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $row): ?>
                        <tr class="wpw-log-row wpw-status-row-<?php echo esc_attr($row['status']); ?>">
                            <td class="wpw-log-id">
                                <?php echo esc_html($row['id']); ?>
                            </td>
                            <td><code><?php echo esc_html($row['action_type']); ?></code></td>
                            <td>
                                <?php if ($row['attachment_id']):
                                    $edit_link = get_edit_post_link((int) $row['attachment_id']);
                                    if ($edit_link):
                                        ?>
                                        <a href="<?php echo esc_url($edit_link); ?>" target="_blank">#
                                            <?php echo esc_html($row['attachment_id']); ?>
                                        </a>
                                    <?php else: ?>
                                        <?php echo esc_html('#' . $row['attachment_id']); ?>
                                        <?php
                                    endif;
                                else:
                                    echo '—';
                                endif; ?>
                            </td>
                            <td class="wpw-log-path" title="<?php echo esc_attr($row['file_path']); ?>">
                                <?php echo esc_html(basename($row['file_path']) ?: '—'); ?>
                            </td>
                            <td>
                                <?php echo esc_html($row['detail']); ?>
                            </td>
                            <td><span class="wpw-status-badge wpw-status-<?php echo esc_attr($row['status']); ?>">
                                    <?php echo esc_html($row['status']); ?>
                                </span></td>
                            <td>
                                <?php echo esc_html($row['created_at']); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div class="wpw-pagination wpw-pagination-bottom">
                <?php for ($i = 1; $i <= $total_pages && $i <= 15; $i++): ?>
                    <a href="<?php echo esc_url(add_query_arg(array('wpw_paged' => $i, 'wpw_action' => $filter_action, 'wpw_status' => $filter_status))); ?>"
                        class="wpw-page-btn <?php echo $i === $current_page ? 'active' : ''; ?>">
                        <?php echo esc_html($i); ?>
                    </a>
                <?php endfor; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>