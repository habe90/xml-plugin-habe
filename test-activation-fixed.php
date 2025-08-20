<?php
// Test plugin activation without WordPress context

// Simulate WordPress environment
define('ABSPATH', '/tmp/');
define('MINUTE_IN_SECONDS', 60);
define('HOUR_IN_SECONDS', 3600);

// Mock WordPress functions
function plugin_dir_path($file) { return dirname($file) . '/'; }
function plugin_dir_url($file) { return 'http://localhost/plugins/' . basename(dirname($file)) . '/'; }
function plugin_basename($file) { return basename(dirname($file)) . '/' . basename($file); }
function esc_html($text) { return htmlspecialchars($text, ENT_QUOTES, 'UTF-8'); }
function __($text, $domain = '') { return $text; }
function wp_die($message, $title = '', $args = []) { die($message); }
function deactivate_plugins($plugin) { echo "Plugin deactivated: $plugin\n"; }
function get_option($name, $default = false) { return $default; }
function add_option($name, $value) { echo "Added option: $name = $value\n"; return true; }
function wp_next_scheduled($hook) { return false; }
function wp_schedule_event($timestamp, $recurrence, $hook, $args = []) { 
    echo "Scheduled event: $hook with recurrence: $recurrence\n"; 
    return true; 
}
function wp_clear_scheduled_hook($hook) { echo "Cleared scheduled hook: $hook\n"; }
function delete_transient($transient) { echo "Deleted transient: $transient\n"; }
function admin_url($path) { return "http://localhost/wp-admin/$path"; }
function is_admin() { return false; }
function load_plugin_textdomain($domain, $deprecated, $plugin_rel_path) { return true; }
function apply_filters($tag, $value) { return $value; }
function add_action($hook, $callback, $priority = 10, $accepted_args = 1) { 
    echo "Added action: $hook\n"; 
}
function add_filter($hook, $callback, $priority = 10, $accepted_args = 1) { 
    echo "Added filter: $hook\n"; 
}

// Mock WordPress database
class MockWPDB {
    public $prefix = 'wp_';
    public function get_charset_collate() { return 'DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'; }
    public function query($sql) { echo "SQL Query executed: " . substr($sql, 0, 50) . "...\n"; return true; }
}
$wpdb = new MockWPDB();

// Mock classes that would be loaded
class XPSE_Logger {
    public function info($message) { echo "LOG INFO: $message\n"; }
}

class XPSE_Sync_Engine {
    public function __construct($logger) {}
}

class XPSE_Admin {
    public function __construct($logger, $sync_engine) {}
}

// Mock WooCommerce check
class WooCommerce {}

// Mock dbDelta function
function dbDelta($sql) {
    echo "Database table created/updated with SQL: " . substr($sql, 0, 50) . "...\n";
    return ['xpse_sync_logs' => 'Created table xpse_sync_logs'];
}

// Include the plugin file
require_once '/workspace/code/xml-product-sync-enhanced/xml-product-sync-enhanced.php';

echo "\n=== TESTING PLUGIN ACTIVATION ===\n";

// Test activation
try {
    $plugin = XML_Product_Sync_Enhanced::get_instance();
    $plugin->activate();
    echo "\n✅ Plugin activation completed successfully!\n";
} catch (Exception $e) {
    echo "\n❌ Plugin activation failed: " . $e->getMessage() . "\n";
}

echo "\n=== TESTING PLUGIN DEACTIVATION ===\n";

// Test deactivation
try {
    $plugin->deactivate();
    echo "\n✅ Plugin deactivation completed successfully!\n";
} catch (Exception $e) {
    echo "\n❌ Plugin deactivation failed: " . $e->getMessage() . "\n";
}

echo "\n=== TESTING SYNTAX ===\n";
echo "✅ No syntax errors found in the plugin file!\n";
