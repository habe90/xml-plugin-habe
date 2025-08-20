<?php
/*
Plugin Name: XML Product Sync Enhanced
Description: Napredna sinhronizacija proizvoda iz XML feed-a u WooCommerce sa poboljšanim sigurnosnim mjerama, monitoring sistemom i konfigurabilnim opcijama.
Version: 2.0.0
Author: Octagon Solutions
Text Domain: xml-product-sync-enhanced
Requires at least: 5.0
Tested up to: 6.4
Requires PHP: 7.4
WC requires at least: 5.0
WC tested up to: 8.5
License: GPL v2 or later
*/

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Plugin constants
define('XPSE_PLUGIN_FILE', __FILE__);
define('XPSE_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('XPSE_PLUGIN_URL', plugin_dir_url(__FILE__));
define('XPSE_VERSION', '2.0.0');
define('XPSE_MINIMUM_WP_VERSION', '5.0');
define('XPSE_MINIMUM_WC_VERSION', '5.0');

// Check if WooCommerce is active
register_activation_hook(__FILE__, 'xpse_check_woocommerce');

function xpse_check_woocommerce() {
    if (!class_exists('WooCommerce')) {
        deactivate_plugins(plugin_basename(__FILE__));
        add_action('admin_notices', function () {
            ?>
            <div class="notice notice-error is-dismissible">
                <p><strong><?php _e('XML Product Sync Enhanced zahtijeva WooCommerce plugin da bude aktivan.', 'xml-product-sync-enhanced'); ?></strong></p>
            </div>
            <?php
        });
    }
}
// Main plugin class
class XML_Product_Sync_Enhanced {
    
    private static $instance = null;
    private $sync_engine = null;
    private $admin = null;
    private $logger = null;
    
    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        // Hook into WordPress init to ensure all functions are available
        add_action('init', array($this, 'init'), 10);
        
        // Setup activation/deactivation hooks early
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }
    
    /**
     * Initialize plugin
     */
    public function init() {
        // Load dependencies
        $this->load_dependencies();
        
        // Initialize components
        $this->init_components();
        
        // Setup hooks
        $this->setup_hooks();
        
        // Setup cron schedules
        $this->setup_cron();
        
        // Setup early hooks after components are initialized
        $this->setup_early_hooks();
    }
    
    /**
     * Load required files
     */
    private function load_dependencies() {
        require_once XPSE_PLUGIN_DIR . 'includes/class-xpse-logger.php';
        require_once XPSE_PLUGIN_DIR . 'includes/class-xpse-sync-engine.php';
        require_once XPSE_PLUGIN_DIR . 'includes/class-xpse-category-manager.php';
        require_once XPSE_PLUGIN_DIR . 'includes/class-xpse-image-processor.php';
        require_once XPSE_PLUGIN_DIR . 'includes/class-xpse-utilities.php';
        require_once XPSE_PLUGIN_DIR . 'includes/class-xpse-settings.php';
        
        if (is_admin()) {
            require_once XPSE_PLUGIN_DIR . 'admin/class-xpse-admin.php';
        }
    }
    
    /**
     * Initialize components
     */
    private function init_components() {
        $this->logger = new XPSE_Logger();
        $this->sync_engine = new XPSE_Sync_Engine($this->logger);
        
        if (is_admin()) {
            $this->admin = new XPSE_Admin($this->logger, $this->sync_engine);
        }
    }
    
    /**
     * Setup early hooks
     */
    private function setup_early_hooks() {
        // Load text domain
        add_action('plugins_loaded', array($this, 'load_textdomain'));
        
        // Admin hooks - only if components are initialized
        if (is_admin() && $this->admin) {
            add_action('admin_init', array($this, 'admin_init'));
            add_action('admin_notices', array($this, 'admin_notices'));
        }
    }
    
    /**
     * Setup WordPress hooks
     */
    private function setup_hooks() {
        // Cron hook - only if sync engine is available
        if ($this->sync_engine) {
            add_action('xpse_sync_event', array($this->sync_engine, 'run_sync'));
            
            // AJAX hooks
            add_action('wp_ajax_xpse_manual_sync', array($this->sync_engine, 'ajax_manual_sync'));
            add_action('wp_ajax_xpse_get_sync_status', array($this->sync_engine, 'ajax_get_sync_status'));
            add_action('wp_ajax_xpse_cancel_sync', array($this->sync_engine, 'ajax_cancel_sync'));
        }
    }
    
    /**
     * Setup custom cron schedules
     */
    private function setup_cron() {
        add_filter('cron_schedules', function($schedules) {
            $schedules['xpse_fifteen_minutes'] = [
                'interval' => 15 * MINUTE_IN_SECONDS,
                'display'  => __('Svakih 15 minuta', 'xml-product-sync-enhanced')
            ];
            $schedules['xpse_thirty_minutes'] = [
                'interval' => 30 * MINUTE_IN_SECONDS,
                'display'  => __('Svakih 30 minuta', 'xml-product-sync-enhanced')
            ];
            $schedules['xpse_two_hours'] = [
                'interval' => 2 * HOUR_IN_SECONDS,
                'display'  => __('Svaka 2 sata', 'xml-product-sync-enhanced')
            ];
            $schedules['xpse_six_hours'] = [
                'interval' => 6 * HOUR_IN_SECONDS,
                'display'  => __('Svakih 6 sati', 'xml-product-sync-enhanced')
            ];
            $schedules['xpse_twelve_hours'] = [
                'interval' => 12 * HOUR_IN_SECONDS,
                'display'  => __('Svakih 12 sati', 'xml-product-sync-enhanced')
            ];
            return $schedules;
        });
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        try {
            // Create default settings
            $this->create_default_settings();
            
            // Schedule cron event
            $this->schedule_sync();
            
            // Create necessary database tables if needed
            $this->create_tables();
            
            // Initialize logger if not already done
            if (!$this->logger) {
                require_once XPSE_PLUGIN_DIR . 'includes/class-xpse-logger.php';
                $this->logger = new XPSE_Logger();
            }
            
            // Log activation
            if ($this->logger) {
                $this->logger->info('Plugin aktiviran');
            }
        } catch (Exception $e) {
            // If activation fails, show error and deactivate
            wp_die(
                sprintf(__('Greška pri aktivaciji plugina: %s', 'xml-product-sync-enhanced'), $e->getMessage()),
                __('Plugin aktivacija neuspešna', 'xml-product-sync-enhanced'),
                array('back_link' => true)
            );
        }
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        try {
            // Clear scheduled events
            wp_clear_scheduled_hook('xpse_sync_event');
            
            // Clean up transients
            $this->cleanup_transients();
            
            // Log deactivation
            if ($this->logger) {
                $this->logger->info('Plugin deaktiviran');
            }
        } catch (Exception $e) {
            // If deactivation fails, at least log the error
            error_log('XML Product Sync Enhanced deactivation error: ' . $e->getMessage());
        }
    }
    
    /**
     * Create default settings
     */
    private function create_default_settings() {
        $defaults = [
            'xml_url' => '',
            'sync_interval' => 'xpse_six_hours',
            'batch_size' => 100,
            'batch_delay' => 15,
            'enable_logging' => true,
            'log_level' => 'info',
            'enable_email_notifications' => false,
            'notification_email' => get_option('admin_email'),
            'handle_variants' => true,
            'create_missing_categories' => true,
            'default_category' => 'Bez kategorije',
            'image_download_timeout' => 30,
            'max_retries' => 3,
            'enable_backup' => true,
            'backup_retention_days' => 7,
            'memory_limit_mb' => 512,
            'max_execution_time' => 300,
            'enable_progress_tracking' => true,
            'auto_update_existing' => true,
            'skip_images_update' => false
        ];
        
        foreach ($defaults as $key => $value) {
            $option_name = 'xpse_' . $key;
            if (!get_option($option_name)) {
                add_option($option_name, $value);
            }
        }
    }
    
    /**
     * Schedule sync event
     */
    private function schedule_sync() {
        $interval = get_option('xpse_sync_interval', 'xpse_six_hours');
        
        if (!wp_next_scheduled('xpse_sync_event')) {
            wp_schedule_event(time(), $interval, 'xpse_sync_event');
        }
    }
    
    /**
     * Create necessary database tables
     */
    private function create_tables() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'xpse_sync_logs';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            session_id varchar(50) NOT NULL,
            level varchar(20) NOT NULL,
            message text NOT NULL,
            context longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY session_id (session_id),
            KEY level (level),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        // Log table creation
        if ($this->logger) {
            $this->logger->info('Database tabele kreirane');
        }
    }
    
    /**
     * Clean up transients
     */
    private function cleanup_transients() {
        global $wpdb;
        
        // Clean up plugin transients
        $transients = [
            'xpse_sync_progress',
            'xpse_sync_status',
            'xpse_batch_offset',
            'xpse_xml_cache',
            'xpse_category_cache',
            'xpse_image_cache'
        ];
        
        foreach ($transients as $transient) {
            delete_transient($transient);
        }
        
        // Clean up any orphaned transients
        $wpdb->query(
            "DELETE FROM {$wpdb->options} 
             WHERE option_name LIKE '_transient_xpse_%' 
                OR option_name LIKE '_transient_timeout_xpse_%'"
        );
    }
    
    /**
     * Admin initialization
     */
    public function admin_init() {
        // Check minimum requirements
        if (!$this->check_requirements()) {
            add_action('admin_notices', array($this, 'requirements_notice'));
            return;
        }
    }
    
    /**
     * Check minimum requirements
     */
    private function check_requirements() {
        global $wp_version;
        
        if (version_compare($wp_version, XPSE_MINIMUM_WP_VERSION, '<')) {
            return false;
        }
        
        if (!class_exists('WooCommerce') && !in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
            return false;
        }
        
        if (defined('WC_VERSION') && version_compare(WC_VERSION, XPSE_MINIMUM_WC_VERSION, '<')) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Requirements notice
     */
    public function requirements_notice() {
        echo '<div class="error"><p>';
        echo sprintf(
            __('XML Product Sync Enhanced zahtijeva WordPress %s+ i WooCommerce %s+.', 'xml-product-sync-enhanced'),
            XPSE_MINIMUM_WP_VERSION,
            XPSE_MINIMUM_WC_VERSION
        );
        echo '</p></div>';
    }
    
    /**
     * Admin notices
     */
    public function admin_notices() {
        // Show configuration notice if XML URL not set
        $xml_url = get_option('xpse_xml_url', '');
        if (empty($xml_url)) {
            echo '<div class="notice notice-warning is-dismissible"><p>';
            echo sprintf(
                __('XML Product Sync Enhanced: Molimo konfigurirajte XML URL u <a href="%s">postavkama</a>.', 'xml-product-sync-enhanced'),
                admin_url('admin.php?page=xpse-settings')
            );
            echo '</p></div>';
        }
    }
    
    /**
     * Load text domain
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'xml-product-sync-enhanced',
            false,
            dirname(plugin_basename(__FILE__)) . '/languages/'
        );
    }
    
    /**
     * Get logger instance
     */
    public function get_logger() {
        return $this->logger;
    }
    
    /**
     * Get sync engine instance
     */
    public function get_sync_engine() {
        return $this->sync_engine;
    }
    
    /**
     * Get admin instance
     */
    public function get_admin() {
        return $this->admin;
    }
}

// Initialize the plugin
XML_Product_Sync_Enhanced::get_instance();

// Helper function to get main plugin instance
function xpse() {
    return XML_Product_Sync_Enhanced::get_instance();
}
