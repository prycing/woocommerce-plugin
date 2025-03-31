<?php
/**
 * Main Prycing class
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class Prycing {

    /**
     * Constructor
     */
    public function __construct() {
        // Load dependencies
        $this->load_dependencies();
        
        // Define hooks
        $this->define_hooks();
    }

    /**
     * Load plugin dependencies
     */
    private function load_dependencies() {
        // Admin class
        require_once PRYCING_PLUGIN_DIR . 'includes/class-prycing-admin.php';
        
        // Updater class
        require_once PRYCING_PLUGIN_DIR . 'includes/class-prycing-updater.php';
    }

    /**
     * Define hooks
     */
    private function define_hooks() {
        // Initialize admin
        if (is_admin()) {
            new Prycing_Admin();
        }
        
        // Register custom cron schedules
        add_filter('cron_schedules', array($this, 'add_cron_schedules'));
        
        // Scheduled update
        add_action('prycing_scheduled_update', array($this, 'run_scheduled_update'));
        
        // Plugin activation and deactivation
        register_activation_hook(PRYCING_PLUGIN_FILE, array($this, 'activate'));
        register_deactivation_hook(PRYCING_PLUGIN_FILE, array($this, 'deactivate'));
        
        // Handle option changes
        add_action('update_option_prycing_update_frequency', array($this, 'update_cron_schedule'), 10, 2);
        add_action('add_option_prycing_update_frequency', array($this, 'update_cron_schedule'), 10, 2);
    }
    
    /**
     * Add custom cron schedules
     *
     * @param array $schedules Existing schedules
     * @return array Modified schedules
     */
    public function add_cron_schedules($schedules) {
        // Add 30 seconds interval
        $schedules['every30seconds'] = array(
            'interval' => 30,
            'display'  => __('Every 30 Seconds', 'prycing'),
        );
        
        // Add 1 minute interval
        $schedules['everyminute'] = array(
            'interval' => 60,
            'display'  => __('Every Minute', 'prycing'),
        );
        
        // Add 5 minutes interval
        $schedules['every5minutes'] = array(
            'interval' => 300,
            'display'  => __('Every 5 Minutes', 'prycing'),
        );
        
        // Add 10 minutes interval
        $schedules['every10minutes'] = array(
            'interval' => 600,
            'display'  => __('Every 10 Minutes', 'prycing'),
        );
        
        // Add 30 minutes interval
        $schedules['every30minutes'] = array(
            'interval' => 1800,
            'display'  => __('Every 30 Minutes', 'prycing'),
        );
        
        return $schedules;
    }

    /**
     * Run scheduled update
     */
    public function run_scheduled_update() {
        $updater = new Prycing_Updater();
        $updater->update_prices();
    }

    /**
     * Plugin activation
     */
    public function activate() {
        // Set default options if not already set
        if (false === get_option('prycing_update_frequency')) {
            update_option('prycing_update_frequency', 'daily');
        }
        
        if (false === get_option('prycing_log_enabled')) {
            update_option('prycing_log_enabled', true);
        }
        
        // Schedule initial cron job
        $this->schedule_update();
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Clear scheduled hooks
        wp_clear_scheduled_hook('prycing_scheduled_update');
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Schedule update based on frequency setting
     */
    private function schedule_update() {
        // Clear existing scheduled update
        wp_clear_scheduled_hook('prycing_scheduled_update');
        
        // Get frequency
        $frequency = get_option('prycing_update_frequency', 'daily');
        
        // Schedule new update
        if (wp_next_scheduled('prycing_scheduled_update') === false) {
            wp_schedule_event(time(), $frequency, 'prycing_scheduled_update');
        }
    }

    /**
     * Update cron schedule when option is changed
     *
     * @param mixed $old_value Old option value
     * @param mixed $new_value New option value
     */
    public function update_cron_schedule($old_value, $new_value) {
        // Reschedule only if value has changed
        if ($old_value !== $new_value) {
            $this->schedule_update();
        }
    }
} 