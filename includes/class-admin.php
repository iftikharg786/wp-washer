<?php
defined('ABSPATH') || die('No direct script access allowed.');

/**
 * Registers admin menus, enqueues assets, and routes page views.
 */
class WP_Washer_Admin
{

    public function __construct()
    {
        add_action('admin_menu', array($this, 'register_menus'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
        add_filter('plugin_action_links_' . WP_WASHER_BASENAME, array($this, 'plugin_action_links'));
    }

    /**
     * Register top-level menu and subpages.
     */
    public function register_menus()
    {
        $capability = 'manage_options';
        $slug = 'wp-washer';

        add_menu_page(
            __('WP Washer', 'wp-washer'),
            __('WP Washer', 'wp-washer'),
            $capability,
            $slug,
            array($this, 'render_dashboard'),
            'dashicons-trash',
            80
        );

        add_submenu_page($slug, __('Dashboard', 'wp-washer'), __('Dashboard', 'wp-washer'), $capability, $slug, array($this, 'render_dashboard'));
        add_submenu_page($slug, __('Settings', 'wp-washer'), __('Settings', 'wp-washer'), $capability, 'wp-washer-settings', array($this, 'render_settings'));
        add_submenu_page($slug, __('Bulk Cleaner', 'wp-washer'), __('Bulk Cleaner', 'wp-washer'), $capability, 'wp-washer-bulk-cleaner', array($this, 'render_bulk_cleaner'));
        add_submenu_page($slug, __('Cleanup Reports', 'wp-washer'), __('Cleanup Reports', 'wp-washer'), $capability, 'wp-washer-reports', array($this, 'render_reports'));
        add_submenu_page($slug, __('Logs', 'wp-washer'), __('Logs', 'wp-washer'), $capability, 'wp-washer-logs', array($this, 'render_logs'));
    }

    /**
     * Determine if we are on a WP Washer admin page.
     */
    private function is_plugin_page($hook)
    {
        $pages = array(
            'toplevel_page_wp-washer',
            'wp-washer_page_wp-washer-settings',
            'wp-washer_page_wp-washer-bulk-cleaner',
            'wp-washer_page_wp-washer-reports',
            'wp-washer_page_wp-washer-logs',
        );
        return in_array($hook, $pages, true);
    }

    /**
     * Enqueue CSS and JS only on plugin pages.
     */
    public function enqueue_assets($hook)
    {
        if (!$this->is_plugin_page($hook)) {
            return;
        }

        wp_enqueue_style(
            'wp-washer-admin',
            WP_WASHER_URL . 'admin/css/wp-washer-admin.css',
            array(),
            WP_WASHER_VERSION
        );

        wp_enqueue_script(
            'wp-washer-admin',
            WP_WASHER_URL . 'admin/js/wp-washer-admin.js',
            array('jquery'),
            WP_WASHER_VERSION,
            true
        );

        wp_localize_script('wp-washer-admin', 'wpWasher', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wpwasher_nonce'),
            'settingsNonce' => wp_create_nonce('wpwasher_settings_nonce'),
            'strings' => array(
                'confirm_delete_meta' => __('This will permanently delete metadata for the selected files. This cannot be undone easily. Continue?', 'wp-washer'),
                'confirm_delete_file' => __('⚠ WARNING: This will permanently delete the selected files from the server. This CANNOT be undone. Are you absolutely sure?', 'wp-washer'),
                'confirm_cleanup' => __('This will remove orphaned database records. Continue?', 'wp-washer'),
                'processing' => __('Processing…', 'wp-washer'),
                'done' => __('Done!', 'wp-washer'),
                'error' => __('An error occurred. Please try again.', 'wp-washer'),
                'no_selection' => __('Please select at least one media file.', 'wp-washer'),
                'select_action' => __('Please choose an action.', 'wp-washer'),
                'restore_confirm' => __('This will restore metadata from the last operation snapshot. Continue?', 'wp-washer'),
            ),
        ));

        // Enqueue Media Library enhancement only on Bulk Cleaner page.
        if ('wp-washer_page_wp-washer-bulk-cleaner' === $hook) {
            wp_enqueue_media();
            wp_enqueue_script(
                'wp-washer-media',
                WP_WASHER_URL . 'admin/js/wp-washer-media.js',
                array('jquery', 'media-views', 'wp-washer-admin'),
                WP_WASHER_VERSION,
                true
            );
        }
    }

    /**
     * Add Settings link to plugin list.
     */
    public function plugin_action_links($links)
    {
        $settings_link = '<a href="' . esc_url(admin_url('admin.php?page=wp-washer-settings')) . '">' . esc_html__('Settings', 'wp-washer') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }

    // ── Page renderers ────────────────────────────────────────────────────────

    public function render_dashboard()
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Access denied.', 'wp-washer'));
        }
        require_once WP_WASHER_DIR . 'admin/views/dashboard.php';
    }

    public function render_settings()
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Access denied.', 'wp-washer'));
        }
        require_once WP_WASHER_DIR . 'admin/views/settings.php';
    }

    public function render_bulk_cleaner()
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Access denied.', 'wp-washer'));
        }
        require_once WP_WASHER_DIR . 'admin/views/bulk-cleaner.php';
    }

    public function render_reports()
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Access denied.', 'wp-washer'));
        }
        require_once WP_WASHER_DIR . 'admin/views/cleanup-reports.php';
    }

    public function render_logs()
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Access denied.', 'wp-washer'));
        }
        require_once WP_WASHER_DIR . 'admin/views/logs.php';
    }
}
