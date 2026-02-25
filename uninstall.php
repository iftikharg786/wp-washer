<?php
// Prevent direct file execution.
defined( 'WP_UNINSTALL_PLUGIN' ) || die( 'No direct script access allowed.' );

global $wpdb;

// Drop custom tables.
$wpdb->query( "DROP TABLE IF EXISTS `{$wpdb->prefix}wpwasher_log`" );
$wpdb->query( "DROP TABLE IF EXISTS `{$wpdb->prefix}wpwasher_restore`" );

// Remove all plugin options.
$wpdb->query( "DELETE FROM `{$wpdb->options}` WHERE `option_name` LIKE 'wpwasher_%'" );

// Remove any remaining transients.
$wpdb->query( "DELETE FROM `{$wpdb->options}` WHERE `option_name` LIKE '_transient_wpwasher_%'" );
$wpdb->query( "DELETE FROM `{$wpdb->options}` WHERE `option_name` LIKE '_transient_timeout_wpwasher_%'" );
