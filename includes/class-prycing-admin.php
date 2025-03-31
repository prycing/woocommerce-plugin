<?php
/**
 * Admin functionality for Prycing
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class Prycing_Admin {

    /**
     * Constructor
     */
    public function __construct() {
        // Add admin menu
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // Register settings
        add_action('admin_init', array($this, 'register_settings'));
        
        // Add action links
        add_filter('plugin_action_links_prycing/prycing.php', array($this, 'add_action_links'));
        
        // Add manual update button
        add_action('admin_init', array($this, 'handle_manual_update'));
        
        // Enqueue admin styles
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_submenu_page(
            'woocommerce',
            __('Prycing Settings', 'prycing'),
            __('Prycing', 'prycing'),
            'manage_woocommerce',
            'prycing-settings',
            array($this, 'settings_page')
        );
    }

    /**
     * Register settings
     */
    public function register_settings() {
        register_setting('prycing_settings', 'prycing_xml_url', array(
            'sanitize_callback' => 'esc_url_raw',
        ));
        
        register_setting('prycing_settings', 'prycing_update_frequency', array(
            'sanitize_callback' => 'sanitize_text_field',
            'default' => 'daily',
        ));

        register_setting('prycing_settings', 'prycing_log_enabled', array(
            'sanitize_callback' => 'rest_sanitize_boolean',
            'default' => true,
        ));
    }

    /**
     * Add action links
     */
    public function add_action_links($links) {
        $settings_link = '<a href="' . admin_url('admin.php?page=prycing-settings') . '">' . __('Settings', 'prycing') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }
    
    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_scripts($hook) {
        // Only load on plugin settings page
        if ($hook != 'woocommerce_page_prycing-settings') {
            return;
        }
        
        wp_enqueue_style(
            'prycing-admin-css',
            PRYCING_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            PRYCING_VERSION
        );
    }

    /**
     * Settings page content
     */
    public function settings_page() {
        ?>
        <div class="wrap">
            <div class="prycing-admin-header">
                <h1><?php echo esc_html__('Prycing', 'prycing'); ?></h1>
                <p><?php echo esc_html__('Automatically update WooCommerce product prices from Prycing platform.', 'prycing'); ?></p>
            </div>
            
            <div class="prycing-section">
                <form method="post" action="options.php" class="prycing-form">
                    <?php settings_fields('prycing_settings'); ?>
                    <?php do_settings_sections('prycing_settings'); ?>
                    
                    <h2><?php echo esc_html__('Configuration Settings', 'prycing'); ?></h2>
                    <p class="description"><?php echo esc_html__('Configure the plugin connection to Prycing platform.', 'prycing'); ?></p>
                    
                    <table class="form-table">
                        <tr valign="top">
                            <th scope="row"><?php echo esc_html__('Prycing Feed URL', 'prycing'); ?></th>
                            <td>
                                <input type="url" name="prycing_xml_url" class="regular-text" value="<?php echo esc_attr(get_option('prycing_xml_url')); ?>" />
                                <p class="description"><?php echo esc_html__('Enter the Prycing platform feed URL.', 'prycing'); ?></p>
                            </td>
                        </tr>
                        
                        <tr valign="top">
                            <th scope="row"><?php echo esc_html__('Update Frequency', 'prycing'); ?></th>
                            <td>
                                <select name="prycing_update_frequency">
                                    <option value="every30seconds" <?php selected(get_option('prycing_update_frequency', 'daily'), 'every30seconds'); ?>><?php echo esc_html__('Every 30 Seconds', 'prycing'); ?></option>
                                    <option value="everyminute" <?php selected(get_option('prycing_update_frequency', 'daily'), 'everyminute'); ?>><?php echo esc_html__('Every Minute', 'prycing'); ?></option>
                                    <option value="every5minutes" <?php selected(get_option('prycing_update_frequency', 'daily'), 'every5minutes'); ?>><?php echo esc_html__('Every 5 Minutes', 'prycing'); ?></option>
                                    <option value="every10minutes" <?php selected(get_option('prycing_update_frequency', 'daily'), 'every10minutes'); ?>><?php echo esc_html__('Every 10 Minutes', 'prycing'); ?></option>
                                    <option value="every30minutes" <?php selected(get_option('prycing_update_frequency', 'daily'), 'every30minutes'); ?>><?php echo esc_html__('Every 30 Minutes', 'prycing'); ?></option>
                                    <option value="hourly" <?php selected(get_option('prycing_update_frequency', 'daily'), 'hourly'); ?>><?php echo esc_html__('Hourly', 'prycing'); ?></option>
                                    <option value="twicedaily" <?php selected(get_option('prycing_update_frequency', 'daily'), 'twicedaily'); ?>><?php echo esc_html__('Twice Daily', 'prycing'); ?></option>
                                    <option value="daily" <?php selected(get_option('prycing_update_frequency', 'daily'), 'daily'); ?>><?php echo esc_html__('Daily', 'prycing'); ?></option>
                                    <option value="weekly" <?php selected(get_option('prycing_update_frequency', 'daily'), 'weekly'); ?>><?php echo esc_html__('Weekly', 'prycing'); ?></option>
                                </select>
                                <p class="description"><?php echo esc_html__('How often should prices be updated from Prycing', 'prycing'); ?></p>
                                <?php if (in_array(get_option('prycing_update_frequency'), array('every30seconds', 'everyminute', 'every5minutes', 'every10minutes'))): ?>
                                <p class="description" style="color: #d63638;">
                                    <?php echo esc_html__('Warning: Frequent updates may impact site performance. For production sites, consider using longer intervals.', 'prycing'); ?>
                                </p>
                                <?php endif; ?>
                            </td>
                        </tr>
                        
                        <tr valign="top">
                            <th scope="row"><?php echo esc_html__('Enable Logging', 'prycing'); ?></th>
                            <td>
                                <input type="checkbox" name="prycing_log_enabled" value="1" <?php checked(get_option('prycing_log_enabled', true)); ?> />
                                <span class="description"><?php echo esc_html__('Log price updates and errors', 'prycing'); ?></span>
                            </td>
                        </tr>
                    </table>
                    
                    <?php submit_button(); ?>
                </form>
            </div>
            
            <div class="prycing-section">
                <h2><?php echo esc_html__('Manual Price Update', 'prycing'); ?></h2>
                <p class="description"><?php echo esc_html__('Click the button below to manually update prices from Prycing.', 'prycing'); ?></p>
                
                <form method="post" action="">
                    <?php wp_nonce_field('prycing_manual_update', 'prycing_manual_update_nonce'); ?>
                    <input type="hidden" name="prycing_manual_update" value="1">
                    <?php submit_button(__('Update Prices Now', 'prycing'), 'secondary', 'submit', false); ?>
                </form>
                
                <?php
                // Display update log if available
                if (get_transient('prycing_update_log')) {
                    echo '<div class="prycing-log">';
                    echo '<p>' . esc_html(get_transient('prycing_update_log')) . '</p>';
                    echo '</div>';
                    delete_transient('prycing_update_log');
                }
                ?>
            </div>
        </div>
        <?php
    }

    /**
     * Handle manual update
     */
    public function handle_manual_update() {
        if (isset($_POST['prycing_manual_update']) && 
            isset($_POST['prycing_manual_update_nonce']) && 
            wp_verify_nonce($_POST['prycing_manual_update_nonce'], 'prycing_manual_update')) {
            
            // Run the updater
            $updater = new Prycing_Updater();
            $result = $updater->update_prices();
            
            // Set transient with update result
            if ($result['success']) {
                set_transient('prycing_update_log', sprintf(
                    __('Successfully updated %d products. %d products not found.', 'prycing'),
                    $result['updated'],
                    $result['not_found']
                ), 60);
            } else {
                set_transient('prycing_update_log', $result['message'], 60);
            }
            
            // Redirect to avoid form resubmission
            wp_redirect(admin_url('admin.php?page=prycing-settings'));
            exit;
        }
    }
} 