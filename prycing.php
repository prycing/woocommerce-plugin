<?php
/**
 * Plugin Name: Prycing
 * Description: Imports product prices from an XML file and updates WooCommerce products
 * Version: 1.0.0
 * Author: Prycing
 * Text Domain: prycing
 * Requires at least: 5.0
 * Requires PHP: 7.2
 * WC requires at least: 3.0
 * WC tested up to: 7.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Check if WooCommerce is active
if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    add_action('admin_notices', function() {
        echo '<div class="error"><p>' . __('Prycing requires WooCommerce to be installed and active.', 'prycing') . '</p></div>';
    });
    return;
}

// Define plugin constants
define('PRYCING_VERSION', '1.0.0');
define('PRYCING_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('PRYCING_PLUGIN_URL', plugin_dir_url(__FILE__));

// Include required files
require_once PRYCING_PLUGIN_DIR . 'includes/class-prycing-admin.php';
require_once PRYCING_PLUGIN_DIR . 'includes/class-prycing-updater.php';

/**
 * Initialize the plugin
 */
function prycing_init() {
    // Initialize admin
    new Prycing_Admin();
    
    // Register activation hook
    register_activation_hook(__FILE__, 'prycing_activate');
    
    // Register deactivation hook
    register_deactivation_hook(__FILE__, 'prycing_deactivate');
    
    // Handle update frequency changes
    add_action('update_option_prycing_update_frequency', 'prycing_reschedule_updates', 10, 2);
}
add_action('plugins_loaded', 'prycing_init');

/**
 * Plugin activation
 */
function prycing_activate() {
    // Create default settings
    if (!get_option('prycing_xml_url')) {
        update_option('prycing_xml_url', '');
    }
    
    if (!get_option('prycing_update_frequency')) {
        update_option('prycing_update_frequency', 'daily');
    }
    
    if (get_option('prycing_log_enabled') === false) {
        update_option('prycing_log_enabled', true);
    }
    
    // Schedule price update event
    prycing_schedule_updates();
}

/**
 * Plugin deactivation
 */
function prycing_deactivate() {
    // Clear scheduled events
    wp_clear_scheduled_hook('prycing_update_prices');
}

/**
 * Schedule the price update event based on the frequency setting
 */
function prycing_schedule_updates() {
    $frequency = get_option('prycing_update_frequency', 'daily');
    
    // Clear any existing scheduled events
    wp_clear_scheduled_hook('prycing_update_prices');
    
    // Only schedule if we have a valid frequency
    if (in_array($frequency, array('everyminute', 'hourly', 'twicedaily', 'daily', 'weekly'))) {
        if (!wp_next_scheduled('prycing_update_prices')) {
            wp_schedule_event(time(), $frequency, 'prycing_update_prices');
        }
    }
}

/**
 * Add custom intervals to cron schedules
 */
function prycing_cron_schedules($schedules) {
    // Add weekly interval
    $schedules['weekly'] = array(
        'interval' => 604800, // 7 days in seconds
        'display'  => __('Once Weekly', 'prycing'),
    );
    
    // Add every minute interval
    $schedules['everyminute'] = array(
        'interval' => 60, // 1 minute in seconds
        'display'  => __('Every Minute', 'prycing'),
    );
    
    return $schedules;
}
add_filter('cron_schedules', 'prycing_cron_schedules');

/**
 * Reschedule updates when frequency is changed
 */
function prycing_reschedule_updates($old_value, $new_value) {
    if ($old_value !== $new_value) {
        prycing_schedule_updates();
    }
}

/**
 * Register the action for price updates
 */
add_action('prycing_update_prices', 'prycing_do_price_update');

/**
 * Run the price update process
 */
function prycing_do_price_update() {
    $updater = new Prycing_Updater();
    $updater->update_prices();
}

/**
 * Declare compatibility with High-Performance Order Storage
 */
function prycing_declare_hpos_compatibility() {
    if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
            'custom_order_tables',
            __FILE__,
            true
        );
    }
}
add_action('before_woocommerce_init', 'prycing_declare_hpos_compatibility'); 