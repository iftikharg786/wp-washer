<?php
defined('ABSPATH') || die('No direct script access allowed.');
if (!current_user_can('manage_options')) {
    wp_die(esc_html__('Access denied.', 'wp-washer'));
}

$s = WP_Washer_Settings::get_all();
?>
<div class="wrap wpw-wrap">
    <h1 class="wpw-page-title"><span class="dashicons dashicons-admin-settings"></span>
        <?php esc_html_e('WP Washer Settings', 'wp-washer'); ?>
    </h1>

    <div id="wpw-settings-saved" class="wpw-notice wpw-notice-success" style="display:none;">
        <span class="dashicons dashicons-yes-alt"></span>
        <?php esc_html_e('Settings saved successfully.', 'wp-washer'); ?>
    </div>

    <form id="wpw-settings-form" class="wpw-card">
        <h2>
            <?php esc_html_e('Auto-Delete on Post Removal', 'wp-washer'); ?>
        </h2>
        <p class="wpw-description">
            <?php esc_html_e('Automatically clean media metadata when posts or products are deleted.', 'wp-washer'); ?>
        </p>

        <div class="wpw-setting-row">
            <label class="wpw-toggle-label">
                <div class="wpw-toggle-wrap">
                    <input type="checkbox" name="enable_auto_delete" value="1" <?php checked('1', $s['enable_auto_delete']); ?>>
                    <span class="wpw-toggle-slider"></span>
                </div>
                <span>
                    <?php esc_html_e('Enable Auto-Delete', 'wp-washer'); ?>
                </span>
            </label>
            <p class="wpw-setting-desc">
                <?php esc_html_e('Master switch — must be ON for any auto-deletion to occur.', 'wp-washer'); ?>
            </p>
        </div>

        <hr class="wpw-divider">
        <h3>
            <?php esc_html_e('Apply To', 'wp-washer'); ?>
        </h3>

        <div class="wpw-setting-row wpw-setting-row--inline">
            <label class="wpw-toggle-label">
                <div class="wpw-toggle-wrap">
                    <input type="checkbox" name="apply_to_posts" value="1" <?php checked('1', $s['apply_to_posts']); ?>>
                    <span class="wpw-toggle-slider"></span>
                </div>
                <span>
                    <?php esc_html_e('Posts', 'wp-washer'); ?>
                </span>
            </label>
            <label class="wpw-toggle-label">
                <div class="wpw-toggle-wrap">
                    <input type="checkbox" name="apply_to_pages" value="1" <?php checked('1', $s['apply_to_pages']); ?>>
                    <span class="wpw-toggle-slider"></span>
                </div>
                <span>
                    <?php esc_html_e('Pages', 'wp-washer'); ?>
                </span>
            </label>
            <label class="wpw-toggle-label">
                <div class="wpw-toggle-wrap">
                    <input type="checkbox" name="apply_to_products" value="1" <?php checked('1', $s['apply_to_products']); ?>>
                    <span class="wpw-toggle-slider"></span>
                </div>
                <span>
                    <?php esc_html_e('WooCommerce Products', 'wp-washer'); ?>
                </span>
            </label>
        </div>

        <hr class="wpw-divider">
        <h3>
            <?php esc_html_e('Trigger Events', 'wp-washer'); ?>
        </h3>

        <div class="wpw-setting-row wpw-setting-row--inline">
            <label class="wpw-toggle-label">
                <div class="wpw-toggle-wrap">
                    <input type="checkbox" name="trigger_on_delete" value="1" <?php checked('1', $s['trigger_on_delete']); ?>>
                    <span class="wpw-toggle-slider"></span>
                </div>
                <span>
                    <?php esc_html_e('On Permanent Delete', 'wp-washer'); ?>
                </span>
            </label>
            <label class="wpw-toggle-label">
                <div class="wpw-toggle-wrap">
                    <input type="checkbox" name="trigger_on_trash" value="1" <?php checked('1', $s['trigger_on_trash']); ?>>
                    <span class="wpw-toggle-slider"></span>
                </div>
                <span>
                    <?php esc_html_e('On Move to Trash', 'wp-washer'); ?>
                </span>
            </label>
        </div>

        <hr class="wpw-divider">
        <h2>
            <?php esc_html_e('Deletion Behaviour', 'wp-washer'); ?>
        </h2>

        <div class="wpw-setting-row">
            <label class="wpw-toggle-label">
                <div class="wpw-toggle-wrap">
                    <input type="checkbox" name="delete_files" value="1" <?php checked('1', $s['delete_files']); ?>>
                    <span class="wpw-toggle-slider wpw-toggle-danger"></span>
                </div>
                <span>
                    <?php esc_html_e('Delete Actual Files (Dangerous)', 'wp-washer'); ?>
                </span>
            </label>
            <p class="wpw-setting-desc wpw-danger-text">
                <?php esc_html_e('⚠ If enabled, media files on disk will be permanently deleted. Leave OFF to only clean metadata.', 'wp-washer'); ?>
            </p>
        </div>

        <div class="wpw-setting-row">
            <label class="wpw-toggle-label">
                <div class="wpw-toggle-wrap">
                    <input type="checkbox" name="strip_exif" value="1" <?php checked('1', $s['strip_exif']); ?>>
                    <span class="wpw-toggle-slider"></span>
                </div>
                <span>
                    <?php esc_html_e('Strip EXIF Data from Images', 'wp-washer'); ?>
                </span>
            </label>
            <p class="wpw-setting-desc">
                <?php esc_html_e('Removes EXIF metadata (camera info, GPS coordinates) from JPEG/TIFF images during bulk operations.', 'wp-washer'); ?>
            </p>
        </div>

        <hr class="wpw-divider">
        <h2>
            <?php esc_html_e('Performance', 'wp-washer'); ?>
        </h2>

        <div class="wpw-setting-row">
            <label for="wpw-batch-size">
                <?php esc_html_e('Batch Size (files per chunk)', 'wp-washer'); ?>
            </label>
            <input type="number" id="wpw-batch-size" name="batch_size"
                value="<?php echo esc_attr($s['batch_size'] ?: 30); ?>" min="5" max="100" class="wpw-number-input">
            <p class="wpw-setting-desc">
                <?php esc_html_e('Number of files processed per AJAX request. Lower values are safer on shared hosting (recommended: 20–30).', 'wp-washer'); ?>
            </p>
        </div>

        <hr class="wpw-divider">
        <h2>
            <?php esc_html_e('Logging', 'wp-washer'); ?>
        </h2>

        <div class="wpw-setting-row">
            <label class="wpw-toggle-label">
                <div class="wpw-toggle-wrap">
                    <input type="checkbox" name="enable_logging" value="1" <?php checked('1', $s['enable_logging']); ?>>
                    <span class="wpw-toggle-slider"></span>
                </div>
                <span>
                    <?php esc_html_e('Enable Operation Logging', 'wp-washer'); ?>
                </span>
            </label>
            <p class="wpw-setting-desc">
                <?php esc_html_e('Record all delete, skip, and error events in the Logs page.', 'wp-washer'); ?>
            </p>
        </div>

        <div class="wpw-form-actions">
            <button type="submit" class="wpw-btn wpw-btn-primary" id="wpw-save-settings">
                <span class="dashicons dashicons-saved"></span>
                <?php esc_html_e('Save Settings', 'wp-washer'); ?>
            </button>
        </div>
    </form>
</div>