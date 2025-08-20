<?php
/**
 * Test stranica za provjeru admin interface-a
 * Postaviti u plugin root direktorij i pozvati iz browser-a
 */

// Sprječava direktan pristup
if (!defined('ABSPATH')) {
    // Pokušaj učitati WordPress
    $wp_load_paths = [
        '../../../wp-load.php',
        '../../../../wp-load.php', 
        '../../../../../wp-load.php'
    ];
    
    $wp_loaded = false;
    foreach ($wp_load_paths as $path) {
        if (file_exists($path)) {
            require_once $path;
            $wp_loaded = true;
            break;
        }
    }
    
    if (!$wp_loaded) {
        die('Ne mogu pronaći WordPress. Pokrenite ovaj script iz WordPress okruženja.');
    }
}

// Provjeri da korisnik ima dozvole
if (!current_user_can('manage_options')) {
    die('Nemate dozvole za testiranje admin interface-a.');
}

echo "<h2>Test Admin Interface-a - XML Product Sync Enhanced</h2>";
echo "<hr>";

// 1. Provjeri da li je plugin aktivan
echo "<h3>1. Status Plugina</h3>";
$plugin_file = 'xml-product-sync-enhanced/xml-product-sync-enhanced.php';
if (is_plugin_active($plugin_file)) {
    echo "✅ Plugin je aktivan<br>";
} else {
    echo "❌ Plugin nije aktivan<br>";
    die('Plugin mora biti aktivan za testiranje admin interface-a.');
}

// 2. Provjeri admin klasu
echo "<h3>2. Admin klasa</h3>";
if (class_exists('XPSE_Admin')) {
    echo "✅ XPSE_Admin klasa postoji<br>";
} else {
    echo "❌ XPSE_Admin klasa ne postoji<br>";
}

// 3. Provjeri admin menu stranice
echo "<h3>3. Admin Menu Stranice</h3>";
global $menu, $submenu;

// Traži XML Product Sync u main menu
$found_menu = false;
foreach ($menu as $menu_item) {
    if (isset($menu_item[0]) && strpos($menu_item[0], 'XML Product Sync') !== false) {
        echo "✅ Glavna menu stavka pronađena: " . $menu_item[0] . "<br>";
        $found_menu = true;
        break;
    }
}

if (!$found_menu) {
    echo "❌ Glavna menu stavka nije pronađena<br>";
}

// Provjeri submenu stranice
if (isset($submenu['xpse-dashboard'])) {
    echo "✅ Submenu stranice:<br>";
    foreach ($submenu['xpse-dashboard'] as $submenu_item) {
        echo "&nbsp;&nbsp;&nbsp;&nbsp;- " . $submenu_item[0] . " (" . $submenu_item[2] . ")<br>";
    }
} else {
    echo "❌ Submenu stranice nisu pronađene<br>";
}

// 4. Provjeri admin view datoteke
echo "<h3>4. Admin View Datoteke</h3>";
$view_files = [
    'dashboard.php',
    'settings.php', 
    'logs.php',
    'categories.php',
    'tools.php'
];

foreach ($view_files as $view_file) {
    $file_path = WP_PLUGIN_DIR . '/xml-product-sync-enhanced/admin/views/' . $view_file;
    if (file_exists($file_path)) {
        echo "✅ {$view_file} postoji<br>";
    } else {
        echo "❌ {$view_file} ne postoji<br>";
    }
}

// 5. Provjeri asset datoteke
echo "<h3>5. Asset Datoteke</h3>";
$asset_files = [
    'assets/css/admin.css',
    'assets/js/admin.js'
];

foreach ($asset_files as $asset_file) {
    $file_path = WP_PLUGIN_DIR . '/xml-product-sync-enhanced/' . $asset_file;
    if (file_exists($file_path)) {
        echo "✅ {$asset_file} postoji<br>";
        $file_size = filesize($file_path);
        echo "&nbsp;&nbsp;&nbsp;&nbsp;Veličina: " . round($file_size / 1024, 2) . " KB<br>";
    } else {
        echo "❌ {$asset_file} ne postoji<br>";
    }
}

// 6. Test AJAX endpoint-a
echo "<h3>6. AJAX Endpoints</h3>";
$ajax_actions = [
    'xpse_test_connection',
    'xpse_export_logs',
    'xpse_clear_logs', 
    'xpse_manual_sync',
    'xpse_get_sync_status',
    'xpse_cancel_sync'
];

foreach ($ajax_actions as $action) {
    // Provjeri da li je action registriran
    $hook_exists = has_action('wp_ajax_' . $action);
    if ($hook_exists) {
        echo "✅ AJAX action '{$action}' registriran<br>";
    } else {
        echo "❌ AJAX action '{$action}' nije registriran<br>";
    }
}

// 7. Provjeri nonce za sigurnost
echo "<h3>7. Security (Nonce)</h3>";
$nonce = wp_create_nonce('xpse_admin');
if ($nonce) {
    echo "✅ Nonce kreiran uspješno: " . substr($nonce, 0, 10) . "...<br>";
} else {
    echo "❌ Problem s kreiranjem nonce-a<br>";
}

// 8. Test linkovai na admin stranice
echo "<h3>8. Admin Stranice Linkovi</h3>";
$admin_pages = [
    'xpse-dashboard' => 'Dashboard',
    'xpse-settings' => 'Postavke', 
    'xpse-logs' => 'Logs',
    'xpse-categories' => 'Kategorije',
    'xpse-tools' => 'Alati'
];

foreach ($admin_pages as $page_slug => $page_name) {
    $url = admin_url('admin.php?page=' . $page_slug);
    echo "✅ {$page_name}: <a href='{$url}' target='_blank'>{$url}</a><br>";
}

// 9. Performance provjera
echo "<h3>9. Performance Provjera</h3>";
echo "📈 Memory usage: " . round(memory_get_usage() / 1024 / 1024, 2) . " MB<br>";
echo "📈 Peak memory: " . round(memory_get_peak_usage() / 1024 / 1024, 2) . " MB<br>";
$execution_time = microtime(true) - $_SERVER["REQUEST_TIME_FLOAT"];
echo "🕒 Execution time: " . round($execution_time, 4) . " seconds<br>";

echo "<hr>";
echo "<h3>Zaključak</h3>";
echo "<strong style='color: green;'>✅ Admin interface test kompletiran!</strong><br>";
echo "Ako su svi prethodni testovi prošli, admin interface bi trebao raditi bez problema.<br>";
echo "<br>";
echo "<p><strong>Sljedeći koraci:</strong></p>";
echo "<ol>";
echo "<li>Idite na <a href='" . admin_url('admin.php?page=xpse-dashboard') . "'>Dashboard</a> i proverite funkcionalnost</li>";
echo "<li>Postavite XML URL u <a href='" . admin_url('admin.php?page=xpse-settings') . "'>Postavke</a></li>";
echo "<li>Pokrenite test sync i proverite da nema AJAX grešaka u browser console</li>";
echo "</ol>";

echo "<hr>";
echo "<p><em>Test kompletiran: " . date('Y-m-d H:i:s') . "</em></p>";
?>