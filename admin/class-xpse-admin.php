<?php
/**
 * Admin class for XML Product Sync Enhanced
 *
 * @package XML_Product_Sync_Enhanced
 */

if (!defined('ABSPATH')) {
    exit;
}

class XPSE_Admin {
    
    private $logger;
    private $sync_engine;
    private $settings;
    
    /**
     * Constructor
     */
    public function __construct($logger = null, $sync_engine = null) {
        $this->logger = $logger;
        $this->sync_engine = $sync_engine;
        $this->settings = new XPSE_Settings($logger);
        
        $this->init_hooks();
    }
    
    /**
     * Initialize admin hooks
     */
    private function init_hooks() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('wp_ajax_xpse_test_connection', array($this, 'ajax_test_connection'));
        add_action('wp_ajax_xpse_export_logs', array($this, 'ajax_export_logs'));
        add_action('wp_ajax_xpse_clear_logs', array($this, 'ajax_clear_logs'));
        add_action('wp_ajax_xpse_cleanup_images', array($this, 'ajax_cleanup_images'));
        add_action('wp_ajax_xpse_cleanup_categories', array($this, 'ajax_cleanup_categories'));
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        $capability = 'manage_options';
        
        // Main menu page
        add_menu_page(
            'XML Product Sync Enhanced',
            'XML Product Sync',
            $capability,
            'xpse-dashboard',
            array($this, 'dashboard_page'),
            'dashicons-update',
            56
        );
        
        // Submenu pages
        add_submenu_page(
            'xpse-dashboard',
            'Dashboard',
            'Dashboard',
            $capability,
            'xpse-dashboard',
            array($this, 'dashboard_page')
        );
        
        add_submenu_page(
            'xpse-dashboard',
            'Postavke',
            'Postavke',
            $capability,
            'xpse-settings',
            array($this, 'settings_page')
        );
        
        add_submenu_page(
            'xpse-dashboard',
            'Logs',
            'Logs',
            $capability,
            'xpse-logs',
            array($this, 'logs_page')
        );
        
        add_submenu_page(
            'xpse-dashboard',
            'Kategorije',
            'Kategorije',
            $capability,
            'xpse-categories',
            array($this, 'categories_page')
        );
        
        add_submenu_page(
            'xpse-dashboard',
            'Alati',
            'Alati',
            $capability,
            'xpse-tools',
            array($this, 'tools_page')
        );
    }
    
    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_scripts($hook) {
        if (strpos($hook, 'xpse-') === false) {
            return;
        }
        
        wp_enqueue_script(
            'xpse-admin',
            XPSE_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            XPSE_VERSION,
            true
        );
        
        wp_enqueue_style(
            'xpse-admin',
            XPSE_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            XPSE_VERSION
        );
        
        wp_localize_script('xpse-admin', 'xpse_admin', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('xpse_admin'),
            'strings' => array(
                'confirm_clear_logs' => 'Da li ste sigurni da želite obrisati sve logove?',
                'confirm_cancel_sync' => 'Da li ste sigurni da želite otkazati sync?',
                'confirm_cleanup' => 'Da li ste sigurni da želite pokrenuti cleanup?'
            )
        ));
    }
    
    /**
     * Register settings
     */
    public function register_settings() {
        // Register setting groups
        $groups = $this->settings->get_settings_groups();
        
        foreach ($groups as $group_id => $group) {
            register_setting(
                'xpse_' . $group_id,
                'xpse_' . $group_id,
                array($this, 'validate_settings')
            );
        }
    }
    
    /**
     * Validate settings
     */
    public function validate_settings($input) {
        // Settings validation is handled by XPSE_Settings class
        return $input;
    }
    
    /**
     * Dashboard page
     */
    public function dashboard_page() {
        $sync_status = $this->sync_engine->get_sync_status();
        $sync_progress = $this->sync_engine->get_sync_progress();
        $sync_stats = get_transient('xpse_sync_stats') ?: [];
        $recent_logs = $this->logger ? $this->logger->get_recent_logs(10) : [];
        
        // Get system info
        $system_info = XPSE_Utilities::get_system_info();
        $memory_usage = XPSE_Utilities::get_memory_usage();
        
        // Get next scheduled sync
        $next_sync = wp_next_scheduled('xpse_sync_event');
        
        include XPSE_PLUGIN_DIR . 'admin/views/dashboard.php';
    }
    
    /**
     * Settings page
     */
    public function settings_page() {
        $active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'general';
        $settings_groups = $this->settings->get_settings_groups();
        
        // Handle form submission
        if (isset($_POST['submit']) && wp_verify_nonce($_POST['_wpnonce'], 'xpse_settings')) {
            $this->handle_settings_save($_POST);
        }
        
        // Handle other actions
        if (isset($_POST['action'])) {
            $this->handle_settings_actions($_POST);
        }
        
        include XPSE_PLUGIN_DIR . 'admin/views/settings.php';
    }
    
    /**
     * Handle settings save
     */
    private function handle_settings_save($post_data) {
        $updated_settings = [];
        
        foreach ($post_data as $key => $value) {
            if (strpos($key, 'xpse_') === 0) {
                $setting_key = str_replace('xpse_', '', $key);
                $this->settings->set($setting_key, $value);
                $updated_settings[] = $setting_key;
            }
        }
        
        if (!empty($updated_settings)) {
            add_settings_error(
                'xpse_settings',
                'settings_updated',
                'Postavke su uspešno sačuvane.',
                'updated'
            );
        }
    }
    
    /**
     * Handle settings actions
     */
    private function handle_settings_actions($post_data) {
        $action = $post_data['action'];
        
        switch ($action) {
            case 'reset_defaults':
                $reset_keys = $post_data['reset_keys'] ?? null;
                $this->settings->reset_to_defaults($reset_keys);
                add_settings_error(
                    'xpse_settings',
                    'settings_reset',
                    'Postavke su vraćene na default vrijednosti.',
                    'updated'
                );
                break;
                
            case 'export_settings':
                $this->export_settings();
                break;
                
            case 'import_settings':
                $this->import_settings($_FILES['import_file'] ?? null);
                break;
        }
    }
    
    /**
     * Export settings
     */
    private function export_settings() {
        $export_data = $this->settings->export_settings();
        $filename = 'xpse-settings-' . date('Y-m-d-H-i-s') . '.json';
        
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo $export_data;
        exit;
    }
    
    /**
     * Import settings
     */
    private function import_settings($file) {
        if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
            add_settings_error(
                'xpse_settings',
                'import_error',
                'Greška pri upload-u datoteke.',
                'error'
            );
            return;
        }
        
        $content = file_get_contents($file['tmp_name']);
        $result = $this->settings->import_settings($content);
        
        if ($result['success']) {
            add_settings_error(
                'xpse_settings',
                'import_success',
                sprintf(
                    'Postavke su uspešno importovane. Ažurirano: %d, neuspešno: %d',
                    count($result['updated']),
                    count($result['failed'])
                ),
                'updated'
            );
        } else {
            add_settings_error(
                'xpse_settings',
                'import_error',
                'Greška pri importu: ' . $result['error'],
                'error'
            );
        }
    }
    
    /**
     * Logs page
     */
    public function logs_page() {
        $current_session = isset($_GET['session']) ? $_GET['session'] : null;
        $log_level = isset($_GET['level']) ? $_GET['level'] : null;
        $per_page = 50;
        
        if ($this->logger) {
            if ($current_session) {
                $logs = $this->logger->get_session_logs($current_session);
            } else {
                $logs = $this->logger->get_recent_logs($per_page, null, $log_level);
            }
            
            $sessions = $this->logger->get_sync_sessions();
            $error_logs = $this->logger->get_error_logs(10);
        } else {
            $logs = [];
            $sessions = [];
            $error_logs = [];
        }
        
        include XPSE_PLUGIN_DIR . 'admin/views/logs.php';
    }
    
    /**
     * Categories page
     */
public function categories_page() {
    $category_manager = new XPSE_Category_Manager($this->logger);
    $category_stats = $category_manager->get_category_stats();
    $category_mappings = $category_manager->get_category_mappings();

    // Normalizacija starih zapisa (ako su ostali iz starije verzije plugina)
    foreach ($category_mappings as $key => $mapping) {
        if (!is_array($mapping)) {
            $category_mappings[$key] = [
                'from' => (string)$mapping,
                'to'   => 0,
                'product_count' => 0,
            ];
        } else {
            if (!isset($mapping['product_count'])) {
                $category_mappings[$key]['product_count'] = 0;
            }
        }
    }

    // Handle form submissions
    if (isset($_POST['action'])) {
        $this->handle_category_actions($_POST, $category_manager);
    }

    include XPSE_PLUGIN_DIR . 'admin/views/categories.php';
}

    
    /**
     * Handle category actions
     */
    private function handle_category_actions($post_data, $category_manager) {
        if (!wp_verify_nonce($post_data['_wpnonce'], 'xpse_categories')) {
            return;
        }
        
        $action = $post_data['action'];
        
        switch ($action) {
      case 'add_mapping':
    $from = sanitize_text_field($post_data['mapping_from']); // XML kategorija (string)
    $to   = intval($post_data['mapping_to']);               // WooCommerce cat ID

    if ($to > 0) {
        $category_manager->add_category_mapping($from, $to);

        add_settings_error(
            'xpse_categories',
            'mapping_added',
            'Mapiranje kategorije je dodano.',
            'updated'
        );
    } else {
        add_settings_error(
            'xpse_categories',
            'mapping_error',
            'Morate izabrati WooCommerce kategoriju.',
            'error'
        );
    }
    break;


                
            case 'remove_mapping':
                $from = sanitize_text_field($post_data['remove_mapping']);
                if ($category_manager->remove_category_mapping($from)) {
                    add_settings_error(
                        'xpse_categories',
                        'mapping_removed',
                        'Mapiranje kategorije je uklonjeno.',
                        'updated'
                    );
                }
                break;
        }
    }
    
    /**
     * Tools page
     */
    public function tools_page() {
        $xml_url = $this->settings->get('xml_url');
        $connection_test = null;
        
        // Handle tool actions
        if (isset($_POST['action'])) {
            $this->handle_tool_actions($_POST);
        }
        
        // Test connection if URL is provided
        if (!empty($xml_url)) {
            $connection_test = XPSE_Utilities::test_connection($xml_url);
        }
        
        include XPSE_PLUGIN_DIR . 'admin/views/tools.php';
    }
    
    /**
     * Handle tool actions
     */
    private function handle_tool_actions($post_data) {
        if (!wp_verify_nonce($post_data['_wpnonce'], 'xpse_tools')) {
            return;
        }
        
        $action = $post_data['action'];
        
        switch ($action) {
            case 'clear_all_logs':
                if ($this->logger) {
                    $deleted = $this->logger->clear_logs();
                    add_settings_error(
                        'xpse_tools',
                        'logs_cleared',
                        sprintf('Obrisano %d log stavki.', $deleted),
                        'updated'
                    );
                }
                break;
                
            case 'cleanup_temp_files':
                $deleted = XPSE_Utilities::cleanup_temp_files();
                add_settings_error(
                    'xpse_tools',
                    'temp_cleaned',
                    sprintf('Obrisano %d privremenih datoteka.', $deleted),
                    'updated'
                );
                break;
        }
    }
    
    /**
     * AJAX: Test XML connection
     */
    public function ajax_test_connection() {
        check_ajax_referer('xpse_admin', 'nonce');
        
        $url = sanitize_url($_POST['url'] ?? '');
        
        if (empty($url)) {
            wp_send_json_error('URL nije specificiran.');
        }
        
        $result = XPSE_Utilities::test_connection($url);
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result['error']);
        }
    }
    
    /**
     * AJAX: Export logs
     */
    public function ajax_export_logs() {
        check_ajax_referer('xpse_admin', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Nemate dozvole za ovu akciju.');
        }
        
        $session = sanitize_text_field($_POST['session'] ?? '');
        $format = sanitize_text_field($_POST['format'] ?? 'csv');
        
        if (!$this->logger) {
            wp_send_json_error('Logger nije dostupan.');
        }
        
        $export_data = $this->logger->export_logs($session, $format);
        
        if ($export_data) {
            $filename = 'xpse-logs-' . ($session ?: 'all') . '-' . date('Y-m-d-H-i-s') . '.' . $format;
            
            wp_send_json_success([
                'data' => $export_data,
                'filename' => $filename,
                'mime_type' => $format === 'csv' ? 'text/csv' : 'application/json'
            ]);
        } else {
            wp_send_json_error('Nema podataka za export.');
        }
    }
    
    /**
     * AJAX: Clear logs
     */
    public function ajax_clear_logs() {
        check_ajax_referer('xpse_admin', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Nemate dozvole za ovu akciju.');
        }
        
        $session = sanitize_text_field($_POST['session'] ?? '');
        $older_than = intval($_POST['older_than'] ?? 0);
        
        if (!$this->logger) {
            wp_send_json_error('Logger nije dostupan.');
        }
        
        if ($session) {
            $deleted = $this->logger->clear_logs(null, $session);
        } elseif ($older_than > 0) {
            $deleted = $this->logger->clear_logs($older_than);
        } else {
            $deleted = $this->logger->clear_logs();
        }
        
        wp_send_json_success([
            'message' => sprintf('Obrisano %d log stavki.', $deleted),
            'deleted' => $deleted
        ]);
    }
    
    /**
     * AJAX: Cleanup images
     */
    public function ajax_cleanup_images() {
        check_ajax_referer('xpse_admin', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Nemate dozvole za ovu akciju.');
        }
        
        $dry_run = isset($_POST['dry_run']) && $_POST['dry_run'] === 'true';
        
        $image_processor = new XPSE_Image_Processor($this->logger);
        $result = $image_processor->cleanup_orphaned_images($dry_run);
        
        $message = $dry_run ? 
            sprintf('Pronađeno %d orphaned slika.', count($result)) :
            sprintf('Obrisano %d orphaned slika.', count($result));
        
        wp_send_json_success([
            'message' => $message,
            'images' => $result
        ]);
    }
    
    /**
     * AJAX: Cleanup categories
     */
    public function ajax_cleanup_categories() {
        check_ajax_referer('xpse_admin', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Nemate dozvole za ovu akciju.');
        }
        
        $dry_run = isset($_POST['dry_run']) && $_POST['dry_run'] === 'true';
        
        $category_manager = new XPSE_Category_Manager($this->logger);
        $result = $category_manager->cleanup_empty_categories($dry_run);
        
        $message = $dry_run ? 
            sprintf('Pronađeno %d praznih kategorija.', count($result)) :
            sprintf('Obrisano %d praznih kategorija.', count($result));
        
        wp_send_json_success([
            'message' => $message,
            'categories' => $result
        ]);
    }
    
    /**
     * Get admin page URL
     */
    public static function get_admin_url($page = 'dashboard', $args = []) {
        $url = admin_url('admin.php?page=xpse-' . $page);
        
        if (!empty($args)) {
            $url = add_query_arg($args, $url);
        }
        
        return $url;
    }
}