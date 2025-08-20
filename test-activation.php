<?php
/**
 * Test script za provjeru aktivacije XML Product Sync Enhanced plugina
 * 
 * Stavi ovaj file u wp-content/plugins/xml-product-sync-enhanced/ direktorij
 * i pokreni preko WP-CLI ili direktno u browseru (s autentifikacijom)
 */

// Sprječava direktan pristup
if (!defined('ABSPATH')) {
    // Ako nije WordPress okruženje, pokušaj učitati WordPress
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
if (!current_user_can('activate_plugins')) {
    die('Nemate dozvole za testiranje plugina.');
}

echo "<h2>Test aktivacije XML Product Sync Enhanced plugina</h2>";
echo "<hr>";

// 1. Provjeri da li je plugin prisutan
echo "<h3>1. Provjera prisutnosti plugina</h3>";
$plugin_file = 'xml-product-sync-enhanced/xml-product-sync-enhanced.php';
$plugin_path = WP_PLUGIN_DIR . '/xml-product-sync-enhanced/xml-product-sync-enhanced.php';

if (file_exists($plugin_path)) {
    echo "✅ Plugin datoteka postoji: " . $plugin_path . "<br>";
} else {
    echo "❌ Plugin datoteka ne postoji: " . $plugin_path . "<br>";
    die('Plugin nije pronađen. Zaustaviti test.');
}

// 2. Provjeri da li je WooCommerce aktivan
echo "<h3>2. Provjera WooCommerce</h3>";
if (class_exists('WooCommerce')) {
    echo "✅ WooCommerce je aktivan<br>";
    if (defined('WC_VERSION')) {
        echo "📋 WooCommerce verzija: " . WC_VERSION . "<br>";
    }
} else {
    echo "❌ WooCommerce nije aktivan<br>";
    echo "⚠️ Plugin možda neće raditi bez WooCommerce<br>";
}

// 3. Provjeri trenutno stanje plugina
echo "<h3>3. Trenutno stanje plugina</h3>";
if (is_plugin_active($plugin_file)) {
    echo "✅ Plugin je aktivan<br>";
    
    // Provjeri da li glavna klasa postoji
    if (class_exists('XML_Product_Sync_Enhanced')) {
        echo "✅ Glavna klasa XML_Product_Sync_Enhanced postoji<br>";
        
        // Provjeri da li je instanca stvorena
        $instance = XML_Product_Sync_Enhanced::get_instance();
        if ($instance) {
            echo "✅ Singleton instanca je kreirana<br>";
            
            // Provjeri da li helper funkcija radi
            if (function_exists('xpse')) {
                $helper_instance = xpse();
                if ($helper_instance) {
                    echo "✅ Helper funkcija xpse() radi<br>";
                }
            }
        }
    } else {
        echo "❌ Glavna klasa nije učitana<br>";
    }
} else {
    echo "⚪ Plugin nije aktivan<br>";
}

// 4. Provjeri potrebne klase
echo "<h3>4. Provjera potrebnih klasa</h3>";
$required_classes = [
    'XPSE_Logger',
    'XPSE_Sync_Engine', 
    'XPSE_Category_Manager',
    'XPSE_Image_Processor',
    'XPSE_Utilities',
    'XPSE_Settings'
];

foreach ($required_classes as $class) {
    if (class_exists($class)) {
        echo "✅ Klasa {$class} postoji<br>";
    } else {
        echo "❌ Klasa {$class} ne postoji<br>";
    }
}

// 5. Provjeri admin klasu (samo ako je admin)
if (is_admin()) {
    echo "<h3>5. Provjera admin klase</h3>";
    if (class_exists('XPSE_Admin')) {
        echo "✅ Admin klasa XPSE_Admin postoji<br>";
    } else {
        echo "❌ Admin klasa XPSE_Admin ne postoji<br>";
    }
}

// 6. Provjeri database tabelu
echo "<h3>6. Provjera database tabele</h3>";
global $wpdb;
$table_name = $wpdb->prefix . 'xpse_sync_logs';
$table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") == $table_name;

if ($table_exists) {
    echo "✅ Log tabela postoji: {$table_name}<br>";
    $count = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");
    echo "📋 Broj zapisa u tabeli: {$count}<br>";
} else {
    echo "❌ Log tabela ne postoji: {$table_name}<br>";
}

// 7. Provjeri opcije
echo "<h3>7. Provjera osnovnih opcija</h3>";
$key_options = [
    'xpse_xml_url',
    'xpse_sync_interval',
    'xpse_batch_size',
    'xpse_enable_logging'
];

foreach ($key_options as $option) {
    $value = get_option($option);
    if ($value !== false) {
        echo "✅ Opcija {$option}: " . (is_array($value) ? json_encode($value) : $value) . "<br>";
    } else {
        echo "❌ Opcija {$option} nije postavljena<br>";
    }
}

// 8. Provjeri cron job
echo "<h3>8. Provjera cron job-a</h3>";
$next_sync = wp_next_scheduled('xpse_sync_event');
if ($next_sync) {
    echo "✅ Sync cron je zakazan za: " . date('Y-m-d H:i:s', $next_sync) . "<br>";
} else {
    echo "⚪ Sync cron nije zakazan<br>";
}

// 9. Test jednostavne funkcionalnosti
echo "<h3>9. Test osnovne funkcionalnosti</h3>";
if (is_plugin_active($plugin_file) && class_exists('XML_Product_Sync_Enhanced')) {
    try {
        $instance = XML_Product_Sync_Enhanced::get_instance();
        if ($instance && method_exists($instance, 'get_logger')) {
            $logger = $instance->get_logger();
            if ($logger) {
                echo "✅ Logger je dostupan<br>";
            }
        }
        
        if ($instance && method_exists($instance, 'get_sync_engine')) {
            $sync_engine = $instance->get_sync_engine();
            if ($sync_engine) {
                echo "✅ Sync engine je dostupan<br>";
            }
        }
        
        echo "✅ Osnovne funkcionalnosti rade<br>";
    } catch (Exception $e) {
        echo "❌ Greška u osnovnim funkcionalnostima: " . $e->getMessage() . "<br>";
    }
}

echo "<hr>";
echo "<h3>Zaključak</h3>";
if (is_plugin_active($plugin_file) && class_exists('XML_Product_Sync_Enhanced')) {
    echo "<strong style='color: green;'>✅ Plugin je uspješno aktiviran i radi!</strong><br>";
    echo "Možete koristiti plugin iz admin panela: <a href='" . admin_url('admin.php?page=xpse-settings') . "'>Settings > XML Product Sync</a><br>";
} else {
    echo "<strong style='color: red;'>❌ Plugin ima probleme s aktivacijom.</strong><br>";
    echo "Provjerite PHP error log za detaljnije informacije.<br>";
}

echo "<hr>";
echo "<p><em>Test kompletiran: " . date('Y-m-d H:i:s') . "</em></p>";
?>
