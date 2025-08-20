<?php
/**
 * Settings class for XML Product Sync Enhanced
 *
 * @package XML_Product_Sync_Enhanced
 */

if (!defined('ABSPATH')) {
    exit;
}

class XPSE_Settings {
    
    private $logger;
    private $settings = [];
    
    /**
     * Constructor
     */
    public function __construct($logger = null) {
        $this->logger = $logger;
        $this->load_settings();
    }
    
    /**
     * Load all settings
     */
    private function load_settings() {
        $default_settings = $this->get_default_settings();
        
        foreach ($default_settings as $key => $default_value) {
            $this->settings[$key] = get_option('xpse_' . $key, $default_value);
        }
    }
    
    /**
     * Get default settings
     */
    private function get_default_settings() {
        return [
            // General settings
            'xml_url' => '',
            'sync_interval' => 'xpse_six_hours',
            'batch_size' => 100,
            'batch_delay' => 15,
            'max_retries' => 3,
            
            // Logging settings
            'enable_logging' => true,
            'log_level' => 'info',
            'log_retention_days' => 30,
            
            // Notification settings
            'enable_email_notifications' => false,
            'notification_email' => get_option('admin_email'),
            'notify_on_errors' => true,
            'notify_on_completion' => false,
            
            // Product settings
            'handle_variants' => true,
            'auto_update_existing' => true,
            'skip_images_update' => false,
            'update_stock_only' => false,
            
            // Category settings
            'create_missing_categories' => true,
            'default_category' => 'Bez kategorije',
            'enable_fuzzy_category_matching' => false,
            
            // Image settings
            'image_download_timeout' => 30,
            'max_image_size_mb' => 10,
            'min_image_width' => 50,
            'min_image_height' => 50,
            'image_resize_on_upload' => true,
            
            // Performance settings
            'memory_limit_mb' => 512,
            'max_execution_time' => 300,
            'enable_progress_tracking' => true,
            'cleanup_temp_files' => true,
            
            // Backup settings
            'enable_backup' => true,
            'backup_retention_days' => 7,
            
            // Advanced settings
            'debug_mode' => false,
            'test_mode' => false,
            'enable_webhooks' => false,
            'webhook_url' => ''
        ];
    }
    
    /**
     * Get setting value
     */
    public function get($key, $default = null) {
        if (isset($this->settings[$key])) {
            return $this->settings[$key];
        }
        
        // Try to get from database if not in cache
        $value = get_option('xpse_' . $key, $default);
        $this->settings[$key] = $value;
        
        return $value;
    }
    
    /**
     * Set setting value
     */
    public function set($key, $value) {
        $old_value = $this->get($key);
        
        // Validate setting
        $validated_value = $this->validate_setting($key, $value);
        
        if ($validated_value === false) {
            return false;
        }
        
        // Update in database
        $result = update_option('xpse_' . $key, $validated_value);
        
        if ($result) {
            $this->settings[$key] = $validated_value;
            
            // Log the change
            if ($this->logger) {
                $this->logger->info("Postavka promijenjena: {$key}", [
                    'old_value' => $old_value,
                    'new_value' => $validated_value
                ]);
            }
            
            // Handle setting-specific actions
            $this->handle_setting_change($key, $validated_value, $old_value);
        }
        
        return $result;
    }
    
    /**
     * Validate setting value
     */
    private function validate_setting($key, $value) {
        switch ($key) {
            case 'xml_url':
                if (empty($value)) {
                    return '';
                }
                if (!filter_var($value, FILTER_VALIDATE_URL)) {
                    return false;
                }
                return esc_url_raw($value);
                
            case 'batch_size':
                $value = intval($value);
                return ($value >= 1 && $value <= 1000) ? $value : 100;
                
            case 'batch_delay':
                $value = intval($value);
                return ($value >= 0 && $value <= 300) ? $value : 15;
                
            case 'max_retries':
                $value = intval($value);
                return ($value >= 0 && $value <= 10) ? $value : 3;
                
            case 'notification_email':
                if (empty($value)) {
                    return get_option('admin_email');
                }
                return is_email($value) ? sanitize_email($value) : false;
                
            case 'log_level':
                $allowed_levels = ['debug', 'info', 'warning', 'error', 'critical'];
                return in_array($value, $allowed_levels) ? $value : 'info';
                
            case 'sync_interval':
                $allowed_intervals = [
                    'xpse_fifteen_minutes',
                    'xpse_thirty_minutes',
                    'hourly',
                    'xpse_two_hours',
                    'xpse_six_hours',
                    'xpse_twelve_hours',
                    'daily'
                ];
                return in_array($value, $allowed_intervals) ? $value : 'xpse_six_hours';
                
            case 'memory_limit_mb':
                $value = intval($value);
                return ($value >= 128 && $value <= 2048) ? $value : 512;
                
            case 'max_execution_time':
                $value = intval($value);
                return ($value >= 30 && $value <= 3600) ? $value : 300;
                
            case 'image_download_timeout':
                $value = intval($value);
                return ($value >= 5 && $value <= 120) ? $value : 30;
                
            case 'max_image_size_mb':
                $value = intval($value);
                return ($value >= 1 && $value <= 100) ? $value : 10;
                
            case 'min_image_width':
            case 'min_image_height':
                $value = intval($value);
                return ($value >= 1 && $value <= 1000) ? $value : 50;
                
            case 'log_retention_days':
            case 'backup_retention_days':
                $value = intval($value);
                return ($value >= 1 && $value <= 365) ? $value : 30;
                
            case 'default_category':
                return !empty($value) ? sanitize_text_field($value) : 'Bez kategorije';
                
            case 'webhook_url':
                if (empty($value)) {
                    return '';
                }
                return filter_var($value, FILTER_VALIDATE_URL) ? esc_url_raw($value) : false;
                
            // Boolean settings
            case 'enable_logging':
            case 'enable_email_notifications':
            case 'notify_on_errors':
            case 'notify_on_completion':
            case 'handle_variants':
            case 'auto_update_existing':
            case 'skip_images_update':
            case 'update_stock_only':
            case 'create_missing_categories':
            case 'enable_fuzzy_category_matching':
            case 'image_resize_on_upload':
            case 'enable_progress_tracking':
            case 'cleanup_temp_files':
            case 'enable_backup':
            case 'debug_mode':
            case 'test_mode':
            case 'enable_webhooks':
                return (bool) $value;
                
            default:
                return sanitize_text_field($value);
        }
    }
    
    /**
     * Handle setting-specific actions after change
     */
    private function handle_setting_change($key, $new_value, $old_value) {
        switch ($key) {
            case 'sync_interval':
                if ($new_value !== $old_value) {
                    $this->reschedule_sync_event($new_value);
                }
                break;
                
            case 'memory_limit_mb':
                if ($new_value !== $old_value) {
                    XPSE_Utilities::increase_memory_limit($new_value);
                }
                break;
                
            case 'max_execution_time':
                if ($new_value !== $old_value) {
                    XPSE_Utilities::increase_time_limit($new_value);
                }
                break;
        }
    }
    
    /**
     * Reschedule sync event with new interval
     */
    private function reschedule_sync_event($interval) {
        // Clear existing schedule
        wp_clear_scheduled_hook('xpse_sync_event');
        
        // Schedule with new interval
        wp_schedule_event(time(), $interval, 'xpse_sync_event');
        
        if ($this->logger) {
            $this->logger->info("Sync interval promijenjen", [
                'new_interval' => $interval
            ]);
        }
    }
    
    /**
     * Get all settings
     */
    public function get_all() {
        return $this->settings;
    }
    
    /**
     * Update multiple settings
     */
    public function update_multiple($settings) {
        $updated = [];
        $failed = [];
        
        foreach ($settings as $key => $value) {
            if ($this->set($key, $value)) {
                $updated[] = $key;
            } else {
                $failed[] = $key;
            }
        }
        
        return [
            'updated' => $updated,
            'failed' => $failed
        ];
    }
    
    /**
     * Reset to default settings
     */
    public function reset_to_defaults($keys = null) {
        $defaults = $this->get_default_settings();
        
        if ($keys === null) {
            $keys = array_keys($defaults);
        }
        
        $reset = [];
        
        foreach ($keys as $key) {
            if (isset($defaults[$key])) {
                $this->set($key, $defaults[$key]);
                $reset[] = $key;
            }
        }
        
        if ($this->logger) {
            $this->logger->info("Postavke vraćene na default", [
                'reset_settings' => $reset
            ]);
        }
        
        return $reset;
    }
    
    /**
     * Export settings
     */
    public function export_settings() {
        $export_data = [
            'version' => XPSE_VERSION,
            'export_date' => current_time('mysql'),
            'settings' => $this->settings
        ];
        
        return wp_json_encode($export_data, JSON_PRETTY_PRINT);
    }
    
    /**
     * Import settings
     */
    public function import_settings($json_data) {
        $data = json_decode($json_data, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return [
                'success' => false,
                'error' => 'Nevažeći JSON format'
            ];
        }
        
        if (!isset($data['settings']) || !is_array($data['settings'])) {
            return [
                'success' => false,
                'error' => 'Nedostaju postavke u import datoteci'
            ];
        }
        
        $result = $this->update_multiple($data['settings']);
        
        if ($this->logger) {
            $this->logger->info("Postavke importovane", [
                'imported_version' => $data['version'] ?? 'unknown',
                'updated_count' => count($result['updated']),
                'failed_count' => count($result['failed'])
            ]);
        }
        
        return [
            'success' => true,
            'updated' => $result['updated'],
            'failed' => $result['failed']
        ];
    }
    
    /**
     * Validate settings integrity
     */
    public function validate_settings() {
        $issues = [];
        
        // Check XML URL
        $xml_url = $this->get('xml_url');
        if (empty($xml_url)) {
            $issues[] = 'XML URL nije konfigurisan';
        } elseif (!filter_var($xml_url, FILTER_VALIDATE_URL)) {
            $issues[] = 'XML URL nije valjan';
        }
        
        // Check notification email
        if ($this->get('enable_email_notifications')) {
            $email = $this->get('notification_email');
            if (!is_email($email)) {
                $issues[] = 'Notification email nije valjan';
            }
        }
        
        // Check memory limit
        $memory_limit = $this->get('memory_limit_mb');
        $system_limit = XPSE_Utilities::get_memory_limit() / (1024 * 1024);
        if ($memory_limit > $system_limit) {
            $issues[] = sprintf(
                'Konfigurisan memory limit (%dMB) veći od sistemskog (%dMB)',
                $memory_limit,
                $system_limit
            );
        }
        
        // Check batch size vs memory
        $batch_size = $this->get('batch_size');
        if ($batch_size > 500 && $memory_limit < 256) {
            $issues[] = 'Veliki batch size sa malim memory limit može uzrokovati probleme';
        }
        
        return $issues;
    }
    
    /**
     * Get settings groups for admin interface
     */
    public function get_settings_groups() {
        return [
            'general' => [
                'title' => 'Opšte postavke',
                'settings' => [
                    'xml_url',
                    'sync_interval',
                    'batch_size',
                    'batch_delay',
                    'max_retries'
                ]
            ],
            'products' => [
                'title' => 'Postavke proizvoda',
                'settings' => [
                    'handle_variants',
                    'auto_update_existing',
                    'skip_images_update',
                    'update_stock_only'
                ]
            ],
            'categories' => [
                'title' => 'Postavke kategorija',
                'settings' => [
                    'create_missing_categories',
                    'default_category',
                    'enable_fuzzy_category_matching'
                ]
            ],
            'images' => [
                'title' => 'Postavke slika',
                'settings' => [
                    'image_download_timeout',
                    'max_image_size_mb',
                    'min_image_width',
                    'min_image_height',
                    'image_resize_on_upload'
                ]
            ],
            'logging' => [
                'title' => 'Logging postavke',
                'settings' => [
                    'enable_logging',
                    'log_level',
                    'log_retention_days'
                ]
            ],
            'notifications' => [
                'title' => 'Obaveštenja',
                'settings' => [
                    'enable_email_notifications',
                    'notification_email',
                    'notify_on_errors',
                    'notify_on_completion'
                ]
            ],
            'performance' => [
                'title' => 'Performance',
                'settings' => [
                    'memory_limit_mb',
                    'max_execution_time',
                    'enable_progress_tracking',
                    'cleanup_temp_files'
                ]
            ],
            'backup' => [
                'title' => 'Backup',
                'settings' => [
                    'enable_backup',
                    'backup_retention_days'
                ]
            ],
            'advanced' => [
                'title' => 'Napredne postavke',
                'settings' => [
                    'debug_mode',
                    'test_mode',
                    'enable_webhooks',
                    'webhook_url'
                ]
            ]
        ];
    }
    
    /**
     * Get setting field configuration
     */
    public function get_field_config($key) {
        $configs = [
            'xml_url' => [
                'type' => 'url',
                'label' => 'XML Feed URL',
                'description' => 'URL na XML feed sa podacima o proizvodima'
            ],
            'sync_interval' => [
                'type' => 'select',
                'label' => 'Interval sinhronizacije',
                'options' => [
                    'xpse_fifteen_minutes' => 'Svakih 15 minuta',
                    'xpse_thirty_minutes' => 'Svakih 30 minuta',
                    'hourly' => 'Svaki sat',
                    'xpse_two_hours' => 'Svaka 2 sata',
                    'xpse_six_hours' => 'Svakih 6 sati',
                    'xpse_twelve_hours' => 'Svakih 12 sati',
                    'daily' => 'Jednom dnevno'
                ]
            ],
            'batch_size' => [
                'type' => 'number',
                'label' => 'Veličina batch-a',
                'description' => 'Broj proizvoda za obradu u jednom batch-u (1-1000)',
                'min' => 1,
                'max' => 1000
            ],
            'batch_delay' => [
                'type' => 'number',
                'label' => 'Pauza između batch-ova (sekunde)',
                'description' => 'Vrijeme čekanja između batch-ova (0-300)',
                'min' => 0,
                'max' => 300
            ],
            'enable_logging' => [
                'type' => 'checkbox',
                'label' => 'Omogući logging'
            ],
            'log_level' => [
                'type' => 'select',
                'label' => 'Nivo logiranja',
                'options' => [
                    'debug' => 'Debug',
                    'info' => 'Info',
                    'warning' => 'Warning',
                    'error' => 'Error',
                    'critical' => 'Critical'
                ]
            ]
        ];
        
        return $configs[$key] ?? [
            'type' => 'text',
            'label' => ucfirst(str_replace('_', ' ', $key))
        ];
    }
}