<?php
defined('ABSPATH') || die('No direct script access allowed.');

/**
 * Settings helper — thin wrapper around WordPress options.
 */
class WP_Washer_Settings
{

    /** Option prefix */
    const PREFIX = 'wpwasher_';

    /** Allowed option keys with sanitize callbacks */
    private static $schema = array(
        'enable_auto_delete' => 'absint',
        'apply_to_posts' => 'absint',
        'apply_to_pages' => 'absint',
        'apply_to_products' => 'absint',
        'trigger_on_trash' => 'absint',
        'trigger_on_delete' => 'absint',
        'delete_files' => 'absint',
        'strip_exif' => 'absint',
        'enable_logging' => 'absint',
        'batch_size' => 'absint',
    );

    /**
     * Get a single setting value.
     *
     * @param string $key     Option key (without prefix).
     * @param mixed  $default Default value.
     * @return mixed
     */
    public static function get($key, $default = false)
    {
        return get_option(self::PREFIX . $key, $default);
    }

    /**
     * Set a single setting value after sanitization.
     *
     * @param string $key   Option key (without prefix).
     * @param mixed  $value Raw value.
     * @return bool
     */
    public static function set($key, $value)
    {
        if (!array_key_exists($key, self::$schema)) {
            return false;
        }
        $callback = self::$schema[$key];
        $value = call_user_func($callback, $value);
        return update_option(self::PREFIX . $key, $value);
    }

    /**
     * Get all settings as an associative array.
     *
     * @return array
     */
    public static function get_all()
    {
        $settings = array();
        foreach (array_keys(self::$schema) as $key) {
            $settings[$key] = self::get($key);
        }
        return $settings;
    }

    /**
     * Save multiple settings at once from a raw POST-style array.
     *
     * @param array $data Raw input data.
     * @return void
     */
    public static function save_from_request(array $data)
    {
        foreach (self::$schema as $key => $callback) {
            // Treat unchecked checkboxes as 0.
            $raw = isset($data[$key]) ? $data[$key] : '0';
            $value = call_user_func($callback, $raw);
            update_option(self::PREFIX . $key, $value);
        }
    }
}
