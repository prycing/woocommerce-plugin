<?php
/**
 * Price updater functionality for Prycing
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class Prycing_Updater {

    /**
     * Constructor
     */
    public function __construct() {
        // Get options
        $this->xml_url = get_option('prycing_xml_url', '');
        $this->log_enabled = get_option('prycing_log_enabled', true);
    }

    /**
     * Update prices from Prycing feed
     * 
     * @return array Result of the update process
     */
    public function update_prices() {
        global $wpdb;
        
        $result = array(
            'success' => false,
            'updated' => 0,
            'not_found' => 0,
            'message' => '',
        );

        // Check if URL is set
        if (empty($this->xml_url)) {
            $result['message'] = __('Prycing feed URL is not set. Please configure the plugin settings.', 'prycing');
            $this->log($result['message'], 'error');
            return $result;
        }

        // Fetch XML file
        $xml_data = $this->fetch_xml();
        if (is_wp_error($xml_data)) {
            $result['message'] = $xml_data->get_error_message();
            $this->log($result['message'], 'error');
            return $result;
        }

        // Parse XML
        $products = $this->parse_xml($xml_data);
        if (is_wp_error($products)) {
            $result['message'] = $products->get_error_message();
            $this->log($result['message'], 'error');
            return $result;
        }
        
        // Process products in batches of 50 for better memory management
        $batch_size = 50;
        $total_products = count($products);
        $batches = ceil($total_products / $batch_size);
        
        $updated = 0;
        $not_found = 0;
        
        // Process in batches
        for ($i = 0; $i < $batches; $i++) {
            $batch = array_slice($products, $i * $batch_size, $batch_size);
            
            // Extract EANs for this batch
            $eans = array_map(function($product) {
                return $product['ean'];
            }, $batch);
            
            if (empty($eans)) {
                continue;
            }
            
            // Find products by EAN (first check SKU)
            $ean_placeholders = implode(',', array_fill(0, count($eans), '%s'));
            $product_ids_by_sku = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT ID, post_id, meta_value 
                    FROM {$wpdb->posts} p 
                    JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id 
                    WHERE p.post_type IN ('product', 'product_variation') 
                    AND p.post_status = 'publish' 
                    AND pm.meta_key = '_sku' 
                    AND pm.meta_value IN ($ean_placeholders)",
                    $eans
                ),
                OBJECT_K
            );
            
            // Get product IDs by meta fields
            $meta_keys = array('_ean', '_barcode', 'ean', 'barcode');
            $product_ids_by_meta = array();
            
            foreach ($meta_keys as $meta_key) {
                $meta_results = $wpdb->get_results(
                    $wpdb->prepare(
                        "SELECT post_id, meta_value 
                        FROM {$wpdb->postmeta} 
                        WHERE meta_key = %s 
                        AND meta_value IN ($ean_placeholders)",
                        array_merge(array($meta_key), $eans)
                    ),
                    OBJECT_K
                );
                
                if (!empty($meta_results)) {
                    $product_ids_by_meta = array_merge($product_ids_by_meta, $meta_results);
                }
            }
            
            // Create EAN to product ID mapping
            $ean_to_product_id = array();
            
            // First use SKU matches
            foreach ($product_ids_by_sku as $result) {
                $ean_to_product_id[$result->meta_value] = $result->post_id;
            }
            
            // Then use meta matches if not already found
            foreach ($product_ids_by_meta as $result) {
                if (!isset($ean_to_product_id[$result->meta_value])) {
                    $ean_to_product_id[$result->meta_value] = $result->post_id;
                }
            }
            
            // Process each product in the batch
            foreach ($batch as $product) {
                $ean = $product['ean'];
                
                // Check if we found a matching product
                if (!isset($ean_to_product_id[$ean])) {
                    $not_found++;
                    $this->log(sprintf(
                        __('Product with EAN %s not found', 'prycing'),
                        $ean
                    ), 'notice');
                    continue;
                }
                
                $product_id = $ean_to_product_id[$ean];
                
                // Update prices using direct SQL queries
                $this->update_product_price_sql($product_id, $product);
                $updated++;
            }
            
            // Clear memory
            unset($batch);
            unset($ean_to_product_id);
            unset($product_ids_by_sku);
            unset($product_ids_by_meta);
        }
        
        // Clean WooCommerce product caches
        $this->clean_product_caches();
        
        $result['updated'] = $updated;
        $result['not_found'] = $not_found;
        $result['success'] = true;
        $result['message'] = sprintf(
            __('Price update completed. Updated %d products. %d products not found.', 'prycing'),
            $result['updated'],
            $result['not_found']
        );

        $this->log($result['message'], 'info');
        return $result;
    }

    /**
     * Fetch XML data from URL
     * 
     * @return string|WP_Error XML content or error
     */
    private function fetch_xml() {
        $response = wp_remote_get($this->xml_url, array(
            'timeout' => 60,
            'sslverify' => false,
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            return new WP_Error(
                'xml_fetch_error',
                sprintf(__('Failed to fetch Prycing feed. Server responded with code %s', 'prycing'), $response_code)
            );
        }

        $xml_content = wp_remote_retrieve_body($response);
        if (empty($xml_content)) {
            return new WP_Error('xml_empty', __('Prycing feed content is empty', 'prycing'));
        }

        return $xml_content;
    }

    /**
     * Parse XML data
     * 
     * @param string $xml_content XML content
     * @return array|WP_Error Array of product data or error
     */
    private function parse_xml($xml_content) {
        // Suppress XML parsing errors and handle them gracefully
        libxml_use_internal_errors(true);
        
        $xml = simplexml_load_string($xml_content);
        
        if ($xml === false) {
            $errors = libxml_get_errors();
            libxml_clear_errors();
            
            $error_msg = __('Failed to parse Prycing feed: ', 'prycing');
            foreach ($errors as $error) {
                $error_msg .= $error->message . ' ';
            }
            
            return new WP_Error('xml_parse_error', $error_msg);
        }
        
        $products = array();
        $namespaces = $xml->getNamespaces(true);
        $default_ns = '';
        
        // Check if we have a default namespace
        if (isset($namespaces[''])) {
            $default_ns = $namespaces[''];
            $xml_products = $xml->children($default_ns)->product;
        } else {
            $xml_products = $xml->product;
        }
        
        if (empty($xml_products)) {
            return new WP_Error('no_products', __('No products found in Prycing feed', 'prycing'));
        }
        
        foreach ($xml_products as $xml_product) {
            if ($default_ns) {
                $product_data = $xml_product->children($default_ns);
            } else {
                $product_data = $xml_product;
            }
            
            // Extract the EAN (required)
            $ean = (string)$product_data->ean;
            if (empty($ean)) {
                continue;
            }
            
            // Extract prices
            $regular_price = (string)$product_data->price;
            $special_price = (string)$product_data->special_price;
            $special_from = (string)$product_data->special_price_from;
            $special_to = (string)$product_data->special_price_to;
            
            // Add to products array
            $products[] = array(
                'ean' => $ean,
                'regular_price' => $regular_price,
                'sale_price' => $special_price,
                'sale_price_from' => $special_from,
                'sale_price_to' => $special_to,
            );
        }
        
        return $products;
    }

    /**
     * Update product prices using direct SQL queries for better performance
     * 
     * @param int $product_id Product ID
     * @param array $product Product data from Prycing
     */
    private function update_product_price_sql($product_id, $product) {
        global $wpdb;
        
        // Get product type to determine if it's a variable product
        $product_type = get_post_meta($product_id, '_product_type', true);
        
        // Skip variable products (parent products) as prices should be set at variation level
        if ($product_type === 'variable') {
            return;
        }
        
        // Update regular price
        if (!empty($product['regular_price'])) {
            $wpdb->update(
                $wpdb->postmeta, 
                array('meta_value' => $product['regular_price']), 
                array('post_id' => $product_id, 'meta_key' => '_regular_price')
            );
            
            $this->log(sprintf(
                __('Updated regular price for product #%d (EAN: %s) to %s', 'prycing'),
                $product_id,
                $product['ean'],
                $product['regular_price']
            ), 'info');
        }
        
        // Update sale price and dates
        if (!empty($product['sale_price'])) {
            // Update sale price
            $wpdb->update(
                $wpdb->postmeta, 
                array('meta_value' => $product['sale_price']), 
                array('post_id' => $product_id, 'meta_key' => '_sale_price')
            );
            
            // Update sale price dates if provided
            $sale_price_from = !empty($product['sale_price_from']) ? strtotime($product['sale_price_from']) : '';
            $sale_price_to = !empty($product['sale_price_to']) ? strtotime($product['sale_price_to']) : '';
            
            if (!empty($sale_price_from)) {
                $wpdb->update(
                    $wpdb->postmeta, 
                    array('meta_value' => $sale_price_from), 
                    array('post_id' => $product_id, 'meta_key' => '_sale_price_dates_from')
                );
            } else {
                $wpdb->delete(
                    $wpdb->postmeta, 
                    array('post_id' => $product_id, 'meta_key' => '_sale_price_dates_from')
                );
            }
            
            if (!empty($sale_price_to)) {
                $wpdb->update(
                    $wpdb->postmeta, 
                    array('meta_value' => $sale_price_to), 
                    array('post_id' => $product_id, 'meta_key' => '_sale_price_dates_to')
                );
            } else {
                $wpdb->delete(
                    $wpdb->postmeta, 
                    array('post_id' => $product_id, 'meta_key' => '_sale_price_dates_to')
                );
            }
            
            // Set the active price (used by WooCommerce for sorting and filtering)
            $wpdb->update(
                $wpdb->postmeta, 
                array('meta_value' => $product['sale_price']), 
                array('post_id' => $product_id, 'meta_key' => '_price')
            );
            
            $this->log(sprintf(
                __('Updated sale price for product #%d (EAN: %s) to %s', 'prycing'),
                $product_id,
                $product['ean'],
                $product['sale_price']
            ), 'info');
        } else {
            // Remove sale price if empty in XML
            $wpdb->delete(
                $wpdb->postmeta, 
                array('post_id' => $product_id, 'meta_key' => '_sale_price')
            );
            
            $wpdb->delete(
                $wpdb->postmeta, 
                array('post_id' => $product_id, 'meta_key' => '_sale_price_dates_from')
            );
            
            $wpdb->delete(
                $wpdb->postmeta, 
                array('post_id' => $product_id, 'meta_key' => '_sale_price_dates_to')
            );
            
            // Set the active price to regular price
            if (!empty($product['regular_price'])) {
                $wpdb->update(
                    $wpdb->postmeta, 
                    array('meta_value' => $product['regular_price']), 
                    array('post_id' => $product_id, 'meta_key' => '_price')
                );
            }
            
            $this->log(sprintf(
                __('Removed sale price for product #%d (EAN: %s)', 'prycing'),
                $product_id,
                $product['ean']
            ), 'info');
        }
        
        // Update the modified date to trigger any hooks or caching
        $wpdb->update(
            $wpdb->posts, 
            array('post_modified' => current_time('mysql'), 'post_modified_gmt' => current_time('mysql', 1)), 
            array('ID' => $product_id)
        );
    }
    
    /**
     * Clean product caches after bulk updates
     */
    private function clean_product_caches() {
        // Clear WooCommerce product transients and cache
        if (function_exists('wc_delete_product_transients')) {
            wc_delete_product_transients();
        }
        
        // Clear WooCommerce cache
        if (function_exists('wc_cache_helper_invalidate_cache_group')) {
            wc_cache_helper_invalidate_cache_group('product');
        }
        
        // Ensure pricing meta is refreshed
        do_action('woocommerce_product_meta_updated');
    }

    /**
     * Log messages if logging is enabled
     * 
     * @param string $message Message to log
     * @param string $type Log type (info, error, notice)
     */
    private function log($message, $type = 'info') {
        if (!$this->log_enabled) {
            return;
        }
        
        // Log to file if WC_Logger is available
        if (function_exists('wc_get_logger')) {
            $logger = wc_get_logger();
            $context = array('source' => 'prycing');
            
            switch ($type) {
                case 'error':
                    $logger->error($message, $context);
                    break;
                case 'notice':
                    $logger->notice($message, $context);
                    break;
                default:
                    $logger->info($message, $context);
            }
        }
    }
} 