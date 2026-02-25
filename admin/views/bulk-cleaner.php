<?php
defined('ABSPATH') || die('No direct script access allowed.');
if (!current_user_can('manage_options')) {
    wp_die(esc_html__('Access denied.', 'wp-washer'));
}
?>
<div class="wrap wpw-wrap">
    <h1 class="wpw-page-title"><span class="dashicons dashicons-editor-removeformatting"></span>
        <?php esc_html_e('Bulk Media Cleaner', 'wp-washer'); ?>
    </h1>
    <p class="wpw-description">
        <?php esc_html_e('Select media files below and apply a bulk action. Use drag-select, Shift+Click or Ctrl+Click to choose multiple files.', 'wp-washer'); ?>
    </p>

    <!-- Toolbar -->
    <div class="wpw-bulk-toolbar">
        <div class="wpw-selection-info">
            <span id="wpw-selection-count" class="wpw-badge">0</span>
            <?php esc_html_e('files selected', 'wp-washer'); ?>
        </div>
        <div class="wpw-toolbar-actions">
            <button type="button" id="wpw-select-all-view" class="wpw-btn wpw-btn-outline">
                <span class="dashicons dashicons-yes-alt"></span>
                <?php esc_html_e('Select All in View', 'wp-washer'); ?>
            </button>
            <button type="button" id="wpw-select-all-library" class="wpw-btn wpw-btn-outline">
                <span class="dashicons dashicons-portfolio"></span>
                <?php esc_html_e('Select Entire Library', 'wp-washer'); ?>
            </button>
            <button type="button" id="wpw-deselect-all" class="wpw-btn wpw-btn-outline">
                <span class="dashicons dashicons-no-alt"></span>
                <?php esc_html_e('Clear Selection', 'wp-washer'); ?>
            </button>
        </div>
    </div>

    <!-- Media Grid -->
    <div id="wpw-media-grid" class="wpw-media-grid-container">
        <div id="wpw-drag-overlay" class="wpw-drag-overlay" style="display:none;"></div>
        <div id="wpw-media-items" class="wpw-media-items">
            <?php
            $paged = isset($_GET['wpw_page']) ? absint($_GET['wpw_page']) : 1; // phpcs:ignore WordPress.Security.NonceVerification
            $per_page = 48;
            $attachments = get_posts(array(
                'post_type' => 'attachment',
                'post_status' => 'inherit',
                'posts_per_page' => $per_page,
                'paged' => $paged,
                'orderby' => 'date',
                'order' => 'DESC',
                'fields' => 'all',
            ));

            if (empty($attachments)):
                echo '<p class="wpw-empty">' . esc_html__('No media files found.', 'wp-washer') . '</p>';
            else:
                foreach ($attachments as $attachment):
                    $thumb = wp_get_attachment_image_src($attachment->ID, 'thumbnail');
                    $thumb_url = $thumb ? $thumb[0] : '';
                    $is_image = strpos($attachment->post_mime_type, 'image/') === 0;
                    $filename = basename(get_attached_file($attachment->ID));
                    $icon = $is_image ? '' : wp_mime_type_icon($attachment->post_mime_type);
                    ?>
                    <div class="wpw-media-item" data-id="<?php echo esc_attr($attachment->ID); ?>"
                        data-filename="<?php echo esc_attr($filename); ?>" title="<?php echo esc_attr($filename); ?>">
                        <?php if ($is_image && $thumb_url): ?>
                            <img src="<?php echo esc_url($thumb_url); ?>" alt="<?php echo esc_attr($filename); ?>"
                                loading="lazy">
                        <?php else: ?>
                            <div class="wpw-file-icon">
                                <span class="dashicons dashicons-media-default"></span>
                                <span class="wpw-file-ext">
                                    <?php echo esc_html(strtoupper(pathinfo($filename, PATHINFO_EXTENSION))); ?>
                                </span>
                            </div>
                        <?php endif; ?>
                        <div class="wpw-media-item-overlay">
                            <span class="wpw-check-icon dashicons dashicons-yes"></span>
                        </div>
                        <div class="wpw-media-item-name">
                            <?php echo esc_html($filename); ?>
                        </div>
                    </div>
                    <?php
                endforeach;
            endif;
            ?>
        </div>

        <!-- Pagination -->
        <?php
        $total_count = (int) wp_count_posts('attachment')->inherit;
        $total_pages = ceil($total_count / $per_page);
        if ($total_pages > 1):
            ?>
            <div class="wpw-pagination">
                <?php for ($i = 1; $i <= min($total_pages, 20); $i++): ?>
                    <a href="<?php echo esc_url(add_query_arg('wpw_page', $i)); ?>"
                        class="wpw-page-btn <?php echo $i === $paged ? 'active' : ''; ?>">
                        <?php echo esc_html($i); ?>
                    </a>
                <?php endfor; ?>
                <?php if ($total_pages > 20): ?>
                    <span class="wpw-page-dots">…
                        <?php echo esc_html(number_format_i18n($total_pages)); ?>
                        <?php esc_html_e('pages', 'wp-washer'); ?>
                    </span>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Bulk Action Panel -->
    <div class="wpw-bulk-action-panel wpw-card" id="wpw-bulk-panel">
        <h3>
            <?php esc_html_e('Bulk Action', 'wp-washer'); ?>
        </h3>
        <div class="wpw-bulk-actions-row">
            <select id="wpw-action-select" class="wpw-select">
                <option value="">
                    <?php esc_html_e('— Choose Action —', 'wp-washer'); ?>
                </option>
                <option value="delete_meta">
                    <?php esc_html_e('Delete Metadata Only', 'wp-washer'); ?>
                </option>
                <option value="delete_file">
                    <?php esc_html_e('⚠ Delete Metadata + File (Permanent)', 'wp-washer'); ?>
                </option>
                <option value="strip_exif">
                    <?php esc_html_e('Remove EXIF Data', 'wp-washer'); ?>
                </option>
                <option value="remove_thumbs">
                    <?php esc_html_e('Remove Unused Thumbnails', 'wp-washer'); ?>
                </option>
                <option value="clean_orphan_meta">
                    <?php esc_html_e('Clean Orphaned Post Meta', 'wp-washer'); ?>
                </option>
            </select>
            <button type="button" id="wpw-run-bulk" class="wpw-btn wpw-btn-primary">
                <span class="dashicons dashicons-controls-play"></span>
                <?php esc_html_e('Run Action', 'wp-washer'); ?>
            </button>
        </div>
    </div>

    <!-- Progress Modal -->
    <div id="wpw-modal-overlay" class="wpw-modal-overlay" style="display:none;" role="dialog" aria-modal="true">
        <div class="wpw-modal">
            <div class="wpw-modal-header">
                <h2 id="wpw-modal-title">
                    <?php esc_html_e('Confirm Action', 'wp-washer'); ?>
                </h2>
            </div>
            <div class="wpw-modal-body">
                <div id="wpw-confirm-section">
                    <div class="wpw-modal-summary">
                        <div class="wpw-modal-row">
                            <span>
                                <?php esc_html_e('Files selected:', 'wp-washer'); ?>
                            </span>
                            <strong id="wpw-modal-count">0</strong>
                        </div>
                        <div class="wpw-modal-row">
                            <span>
                                <?php esc_html_e('Action:', 'wp-washer'); ?>
                            </span>
                            <strong id="wpw-modal-action-label">—</strong>
                        </div>
                    </div>
                    <div id="wpw-modal-danger-warning" class="wpw-danger-box" style="display:none;">
                        <span class="dashicons dashicons-warning"></span>
                        <?php esc_html_e('This action will PERMANENTLY DELETE files from the server. This cannot be undone. Only files not referenced in any post will be deleted.', 'wp-washer'); ?>
                    </div>
                    <div class="wpw-modal-footer">
                        <button type="button" id="wpw-modal-cancel" class="wpw-btn wpw-btn-outline">
                            <?php esc_html_e('Cancel', 'wp-washer'); ?>
                        </button>
                        <button type="button" id="wpw-modal-confirm" class="wpw-btn wpw-btn-primary">
                            <?php esc_html_e('Confirm & Run', 'wp-washer'); ?>
                        </button>
                    </div>
                </div>
                <div id="wpw-progress-section" style="display:none;">
                    <p id="wpw-progress-label">
                        <?php esc_html_e('Processing…', 'wp-washer'); ?>
                    </p>
                    <div class="wpw-progress-bar-wrap">
                        <div class="wpw-progress-bar" id="wpw-progress-bar" style="width:0%"></div>
                    </div>
                    <div class="wpw-progress-stats">
                        <span id="wpw-processed-count">0</span> / <span id="wpw-total-count">0</span>
                        <?php esc_html_e('processed', 'wp-washer'); ?>
                        &nbsp;|&nbsp;
                        <span id="wpw-error-count" class="wpw-error-text">0
                            <?php esc_html_e('errors', 'wp-washer'); ?>
                        </span>
                    </div>
                </div>
                <div id="wpw-result-section" style="display:none;">
                    <div class="wpw-result-icon dashicons dashicons-yes-alt"></div>
                    <p id="wpw-result-message"></p>
                    <button type="button" id="wpw-modal-close" class="wpw-btn wpw-btn-primary">
                        <?php esc_html_e('Close', 'wp-washer'); ?>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>