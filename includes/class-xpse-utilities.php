<?php
/**
 * Utilities class for XML Product Sync Enhanced
 *
 * @package XML_Product_Sync_Enhanced
 */

if (!defined('ABSPATH')) {
    exit;
}

class XPSE_Utilities {
    
    /**
     * Parse price from string
     */
    public static function parse_price($price_string) {
        if (empty($price_string)) {
            return 0;
        }
        
        // Remove any currency symbols and whitespace
        $price = trim(str_replace([' ', ','], ['.', '.'], (string) $price_string));
        
        // Extract numeric value
        if (preg_match('/([0-9]+\.?[0-9]*)/', $price, $matches)) {
            return (float) $matches[1];
        }
        
        return 0;
    }
    
    /**
     * Parse dimension from string
     */
    public static function parse_dimension($dimension_string) {
        if (empty($dimension_string)) {
            return 0;
        }
        
        $dimension = trim(str_replace(',', '.', (string) $dimension_string));
        return (float) $dimension;
    }
    
    /**
     * Validate category name
     */
    public static function is_valid_category($cat_name) {
        // Check if empty
        if (empty($cat_name)) {
            return false;
        }
        
        // Trim whitespace
        $cat_name = trim($cat_name);
        
        // Check if only numeric
        if (is_numeric($cat_name)) {
            return false;
        }
        
        // Check minimum length
        if (strlen($cat_name) < 2) {
            return false;
        }
        
        // Check if only dots
        if (preg_match('/^\.+$/', $cat_name)) {
            return false;
        }
        
        // Check allowed characters (letters, numbers, spaces, hyphens, parentheses, slashes, ampersands, plus)
        if (!preg_match('/^[\p{L}0-9\s\-\(\)\/\&\+\.]+$/u', $cat_name)) {
            return false;
        }
        
        // Check for blacklisted terms
        $blacklist = apply_filters('xpse_category_blacklist', [
            'test', 'temp', 'delete', 'remove', 'null', 'undefined', 'none'
        ]);
        
        if (in_array(strtolower($cat_name), $blacklist)) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Sanitize text content
     */
    public static function sanitize_text($text) {
        if (empty($text)) {
            return '';
        }
        
        // Decode HTML entities
        $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
        
        // Remove extra whitespace
        $text = preg_replace('/\s+/', ' ', $text);
        
        // Trim
        $text = trim($text);
        
        return $text;
    }
    
    /**
     * Sanitize HTML content
     */
    public static function sanitize_html($html) {
        if (empty($html)) {
            return '';
        }
        
        // Allow specific HTML tags for product descriptions
        $allowed_tags = apply_filters('xpse_allowed_html_tags', [
            'p' => [],
            'br' => [],
            'strong' => [],
            'b' => [],
            'em' => [],
            'i' => [],
            'ul' => [],
            'ol' => [],
            'li' => [],
            'h1' => [],
            'h2' => [],
            'h3' => [],
            'h4' => [],
            'h5' => [],
            'h6' => [],
            'span' => ['style' => []],
            'div' => ['style' => []],
            'a' => ['href' => [], 'title' => [], 'target' => []]
        ]);
        
        return wp_kses($html, $allowed_tags);
    }
    
    /**
     * Generate SKU if empty
     */
    public static function generate_sku($product_name, $fallback_sku = '') {
        if (!empty($fallback_sku)) {
            return $fallback_sku;
        }
        
        // Generate from product name
        $sku = sanitize_title($product_name);
        $sku = substr($sku, 0, 20); // Limit length
        
        // Add random suffix to ensure uniqueness
        $sku .= '-' . wp_generate_password(6, false, false);
        
        return strtoupper($sku);
    }
    
    /**
     * Check if URL is valid image
     */
    public static function is_valid_image_url($url) {
        if (empty($url)) {
            return false;
        }
        
        // Check if URL is valid
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }
        
        // Check file extension
        $allowed_extensions = apply_filters('xpse_allowed_image_extensions', [
            'jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'
        ]);
        
        $extension = strtolower(pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
        
        return in_array($extension, $allowed_extensions);
    }
    
    /**
     * Format file size
     */
    public static function format_file_size($bytes) {
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        } else {
            return $bytes . ' bytes';
        }
    }
    
    /**
     * Format duration
     */
    public static function format_duration($seconds) {
        if ($seconds < 60) {
            return round($seconds, 1) . ' sekundi';
        } elseif ($seconds < 3600) {
            $minutes = floor($seconds / 60);
            $secs = $seconds % 60;
            return $minutes . 'm ' . round($secs) . 's';
        } else {
            $hours = floor($seconds / 3600);
            $minutes = floor(($seconds % 3600) / 60);
            return $hours . 'h ' . $minutes . 'm';
        }
    }
    
    /**
     * Get memory usage
     */
    public static function get_memory_usage() {
        return [
            'used' => memory_get_usage(true),
            'used_formatted' => self::format_file_size(memory_get_usage(true)),
            'peak' => memory_get_peak_usage(true),
            'peak_formatted' => self::format_file_size(memory_get_peak_usage(true)),
            'limit' => self::get_memory_limit(),
            'limit_formatted' => self::format_file_size(self::get_memory_limit())
        ];
    }
    
    /**
     * Get memory limit in bytes
     */
    public static function get_memory_limit() {
        $memory_limit = ini_get('memory_limit');
        
        if (preg_match('/^(\d+)(.)$/', $memory_limit, $matches)) {
            $value = $matches[1];
            $unit = strtolower($matches[2]);
            
            switch ($unit) {
                case 'g':
                    $value *= 1024 * 1024 * 1024;
                    break;
                case 'm':
                    $value *= 1024 * 1024;
                    break;
                case 'k':
                    $value *= 1024;
                    break;
            }
            
            return $value;
        }
        
        return 0;
    }
    
    /**
     * Check if memory limit is sufficient
     */
    public static function check_memory_limit($required_mb = 256) {
        $limit = self::get_memory_limit();
        $required = $required_mb * 1024 * 1024;
        
        return $limit >= $required;
    }
    
    /**
     * Attempt to increase memory limit
     */
    public static function increase_memory_limit($target_mb = 512) {
        $current_limit = self::get_memory_limit();
        $target_limit = $target_mb * 1024 * 1024;
        
        if ($current_limit < $target_limit) {
            return ini_set('memory_limit', $target_mb . 'M');
        }
        
        return true;
    }
    
    /**
     * Increase execution time limit
     */
    public static function increase_time_limit($seconds = 300) {
        if (function_exists('set_time_limit')) {
            return set_time_limit($seconds);
        }
        
        return false;
    }
    
    /**
     * Clean temporary files
     */
    public static function cleanup_temp_files($pattern = 'xpse_temp_*') {
        $temp_dir = get_temp_dir();
        $files = glob($temp_dir . $pattern);
        $deleted = 0;
        
        foreach ($files as $file) {
            if (is_file($file) && unlink($file)) {
                $deleted++;
            }
        }
        
        return $deleted;
    }
    
    /**
     * Create backup of product before update
     */
    public static function backup_product($product_id) {
        if (!get_option('xpse_enable_backup', true)) {
            return false;
        }
        
        $product = wc_get_product($product_id);
        if (!$product) {
            return false;
        }
        
        $backup_data = [
            'id' => $product_id,
            'name' => $product->get_name(),
            'description' => $product->get_description(),
            'short_description' => $product->get_short_description(),
            'sku' => $product->get_sku(),
            'price' => $product->get_price(),
            'regular_price' => $product->get_regular_price(),
            'sale_price' => $product->get_sale_price(),
            'stock_quantity' => $product->get_stock_quantity(),
            'stock_status' => $product->get_stock_status(),
            'weight' => $product->get_weight(),
            'dimensions' => [
                'width' => $product->get_width(),
                'height' => $product->get_height(),
                'length' => $product->get_length()
            ],
            'categories' => wp_get_post_terms($product_id, 'product_cat', ['fields' => 'ids']),
            'images' => [
                'featured' => get_post_thumbnail_id($product_id),
                'gallery' => $product->get_gallery_image_ids()
            ],
            'meta' => get_post_meta($product_id),
            'timestamp' => time()
        ];
        
        $transient_key = 'xpse_backup_' . $product_id;
        return set_transient($transient_key, $backup_data, DAY_IN_SECONDS);
    }
    
    /**
     * Restore product from backup
     */
    public static function restore_product($product_id) {
        $transient_key = 'xpse_backup_' . $product_id;
        $backup_data = get_transient($transient_key);
        
        if (!$backup_data) {
            return false;
        }
        
        $product = wc_get_product($product_id);
        if (!$product) {
            return false;
        }
        
        // Restore basic data
        $product->set_name($backup_data['name']);
        $product->set_description($backup_data['description']);
        $product->set_short_description($backup_data['short_description']);
        $product->set_sku($backup_data['sku']);
        $product->set_regular_price($backup_data['regular_price']);
        $product->set_sale_price($backup_data['sale_price']);
        $product->set_stock_quantity($backup_data['stock_quantity']);
        $product->set_stock_status($backup_data['stock_status']);
        $product->set_weight($backup_data['weight']);
        $product->set_width($backup_data['dimensions']['width']);
        $product->set_height($backup_data['dimensions']['height']);
        $product->set_length($backup_data['dimensions']['length']);
        
        $product->save();
        
        // Restore categories
        if (!empty($backup_data['categories'])) {
            wp_set_object_terms($product_id, $backup_data['categories'], 'product_cat');
        }
        
        // Restore images
        if (!empty($backup_data['images']['featured'])) {
            set_post_thumbnail($product_id, $backup_data['images']['featured']);
        }
        
        if (!empty($backup_data['images']['gallery'])) {
            update_post_meta($product_id, '_product_image_gallery', implode(',', $backup_data['images']['gallery']));
        }
        
        // Clean up backup
        delete_transient($transient_key);
        
        return true;
    }
    
    /**
     * Validate XML structure
     */
    public static function validate_xml($xml_string) {
        if (empty($xml_string)) {
            return ['valid' => false, 'error' => 'XML string je prazan'];
        }
        
        // Check for common XML issues
        if (strpos($xml_string, '<?xml') === false) {
            return ['valid' => false, 'error' => 'XML ne sadrži deklaraciju'];
        }
        
        // Try to parse XML
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($xml_string);
        
        if ($xml === false) {
            $errors = libxml_get_errors();
            $error_messages = [];
            
            foreach ($errors as $error) {
                $error_messages[] = trim($error->message);
            }
            
            libxml_clear_errors();
            
            return [
                'valid' => false,
                'error' => 'XML parsing error: ' . implode(', ', $error_messages)
            ];
        }
        
        return ['valid' => true, 'xml' => $xml];
    }
    
    /**
     * Get system information
     */
    public static function get_system_info() {
        global $wp_version;
        
        return [
            'wordpress' => $wp_version,
            'woocommerce' => defined('WC_VERSION') ? WC_VERSION : 'N/A',
            'php' => PHP_VERSION,
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size'),
            'mysql' => self::get_mysql_version(),
            'server' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
            'curl' => function_exists('curl_version') ? curl_version()['version'] : 'N/A'
        ];
    }
    
    /**
     * Get MySQL version
     */
    private static function get_mysql_version() {
        global $wpdb;
        return $wpdb->get_var('SELECT VERSION()');
    }
    
    /**
     * Test remote URL connection
     */
    public static function test_connection($url, $timeout = 10) {
        $start_time = microtime(true);
        
        $response = wp_remote_get($url, [
            'timeout' => $timeout,
            'user-agent' => 'XML Product Sync Enhanced/' . XPSE_VERSION
        ]);
        
        $end_time = microtime(true);
        $response_time = round(($end_time - $start_time) * 1000, 2);
        
        if (is_wp_error($response)) {
            return [
                'success' => false,
                'error' => $response->get_error_message(),
                'response_time' => $response_time
            ];
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $content_type = wp_remote_retrieve_header($response, 'content-type');
        $content_length = wp_remote_retrieve_header($response, 'content-length');
        
        return [
            'success' => true,
            'response_code' => $response_code,
            'content_type' => $content_type,
            'content_length' => $content_length,
            'response_time' => $response_time . 'ms'
        ];
    }
}