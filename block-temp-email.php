<?php
/**
 * Plugin Name: Block Temporary Email (BTE)
 * Plugin URI:  https://yourdomain.com/block-temporary-email
 * Description: Block disposable or temporary email addresses on registration, WooCommerce checkout, and major form plugins. Supports multisite with admin settings and logging.
 * Version:     1.0.0
 * Author:      Nayan Ray
 * Author URI:  https://yourdomain.com
 * Text Domain: block-temp-email
 *
 * License: GPLv2 or later
 *
 * Requires PHP: 7.4
 * Requires at least: 5.8
 *
 * @package BlockTemporaryEmail
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define plugin constants
define( 'BTE_VERSION', '1.0.0' );
define( 'BTE_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'BTE_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'BTE_TEXT_DOMAIN', 'block-temp-email' );

// Include required files
require_once BTE_PLUGIN_DIR . 'includes/functions.php';
require_once BTE_PLUGIN_DIR . 'includes/admin-page.php';
require_once BTE_PLUGIN_DIR . 'includes/integrations.php';
require_once BTE_PLUGIN_DIR . 'includes/logging.php';
require_once BTE_PLUGIN_DIR . 'includes/notifications.php';

// Activation hook
register_activation_hook( __FILE__, 'bte_activate_plugin' );

// Deactivation hook
register_deactivation_hook( __FILE__, 'bte_deactivate_plugin' );

/**
 * Plugin activation callback.
 * Schedule cron jobs and initialize options.
 */
function bte_activate_plugin() {
    if ( is_multisite() ) {
        $sites = get_sites();
        foreach ( $sites as $site ) {
            switch_to_blog( $site->blog_id );
            bte_schedule_cron();
            restore_current_blog();
        }
    } else {
        bte_schedule_cron();
    }
}

/**
 * Plugin deactivation callback.
 * Clear scheduled cron jobs.
 */
function bte_deactivate_plugin() {
    if ( is_multisite() ) {
        $sites = get_sites();
        foreach ( $sites as $site ) {
            switch_to_blog( $site->blog_id );
            bte_clear_cron();
            restore_current_blog();
        }
    } else {
        bte_clear_cron();
    }
}

/**
 * Schedule the weekly blocklist update cron event.
 */
function bte_schedule_cron() {
    if ( ! wp_next_scheduled( 'bte_weekly_blocklist_update' ) ) {
        wp_schedule_event( time(), 'weekly', 'bte_weekly_blocklist_update' );
    }
}

/**
 * Clear the scheduled blocklist update cron event.
 */
function bte_clear_cron() {
    wp_clear_scheduled_hook( 'bte_weekly_blocklist_update' );
}

// Hook cron event to update blocklist
add_action( 'bte_weekly_blocklist_update', 'bte_fetch_blocklist' );

// Load plugin textdomain for translations
function bte_load_textdomain() {
    load_plugin_textdomain( BTE_TEXT_DOMAIN, false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}
add_action( 'plugins_loaded', 'bte_load_textdomain' );
