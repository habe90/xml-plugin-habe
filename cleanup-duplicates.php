<?php
/**
 * Skripta za čišćenje duplikata proizvoda
 * Pokreći samo kroz WP CLI ili iz admin panela s pažnjom!
 */

function xpse_find_duplicate_products() {
    global $wpdb;
    
    // Pronađi duplikate po SKU-u
    $duplicate_skus = $wpdb->get_results(
        "SELECT meta_value as sku, COUNT(*) as count 
         FROM {$wpdb->postmeta} pm
         INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
         WHERE pm.meta_key = '_sku' 
         AND p.post_type = 'product'
         AND p.post_status = 'publish'
         AND meta_value != ''
         GROUP BY meta_value 
         HAVING COUNT(*) > 1
         ORDER BY count DESC"
    );
    
    echo "<h2>Pronađeni duplikati po SKU-u</h2>";
    echo "<p>Ukupno duplikata: " . count($duplicate_skus) . "</p>";
    
    foreach ($duplicate_skus as $sku_data) {
        echo "<h3>SKU: {$sku_data->sku} ({$sku_data->count} kopija)</h3>";
        
        // Pronađi sve proizvode s ovim SKU-om
        $products = $wpdb->get_results($wpdb->prepare(
            "SELECT p.ID, p.post_title, p.post_date, pm.meta_value as sku
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
             WHERE pm.meta_key = '_sku' 
             AND pm.meta_value = %s
             AND p.post_type = 'product'
             ORDER BY p.post_date ASC",
            $sku_data->sku
        ));
        
        echo "<ul>";
        foreach ($products as $index => $product) {
            $keep_flag = ($index === 0) ? ' <strong>(ČUVATI - najstariji)</strong>' : ' <span style="color: red;">(BRISATI)</span>';
            echo "<li>ID: {$product->ID} - {$product->post_title} - {$product->post_date}{$keep_flag}</li>";
        }
        echo "</ul>";
    }
    
    return $duplicate_skus;
}

function xpse_clean_duplicate_products($dry_run = true) {
    global $wpdb;
    
    $duplicate_skus = $wpdb->get_results(
        "SELECT meta_value as sku, COUNT(*) as count 
         FROM {$wpdb->postmeta} pm
         INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
         WHERE pm.meta_key = '_sku' 
         AND p.post_type = 'product'
         AND p.post_status = 'publish'
         AND meta_value != ''
         GROUP BY meta_value 
         HAVING COUNT(*) > 1"
    );
    
    $deleted_count = 0;
    $kept_count = 0;
    
    foreach ($duplicate_skus as $sku_data) {
        // Pronađi sve proizvode s ovim SKU-om, sortiraj po datumu (najstariji prvi)
        $products = $wpdb->get_results($wpdb->prepare(
            "SELECT p.ID, p.post_date
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
             WHERE pm.meta_key = '_sku' 
             AND pm.meta_value = %s
             AND p.post_type = 'product'
             ORDER BY p.post_date ASC",
            $sku_data->sku
        ));
        
        // Čuvaj najstariji, obriši ostale
        foreach ($products as $index => $product) {
            if ($index === 0) {
                // Čuvaj najstariji
                $kept_count++;
                echo "ČUVAM: ID {$product->ID} (SKU: {$sku_data->sku})<br>";
            } else {
                // Briši duplikate
                if (!$dry_run) {
                    wp_delete_post($product->ID, true); // force delete
                    echo "OBRISAN: ID {$product->ID} (SKU: {$sku_data->sku})<br>";
                } else {
                    echo "TREBAM OBRISATI: ID {$product->ID} (SKU: {$sku_data->sku})<br>";
                }
                $deleted_count++;
            }
        }
    }
    
    echo "<h3>Rezultati čišćenja</h3>";
    echo "<p>Čuvano proizvoda: $kept_count</p>";
    echo "<p>" . ($dry_run ? 'Za brisanje' : 'Obrisano') . " proizvoda: $deleted_count</p>";
    
    if ($dry_run) {
        echo "<p style='color: orange;'><strong>OVO JE SAMO TEST! Za stvarno brisanje pozovite s parametrom dry_run=false</strong></p>";
    }
    
    return [
        'kept' => $kept_count,
        'deleted' => $deleted_count,
        'duplicate_skus' => count($duplicate_skus)
    ];
}

function xpse_get_duplicate_stats() {
    global $wpdb;
    
    $total_products = $wpdb->get_var(
        "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'product' AND post_status = 'publish'"
    );
    
    $products_with_sku = $wpdb->get_var(
        "SELECT COUNT(*) FROM {$wpdb->posts} p
         INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
         WHERE pm.meta_key = '_sku'
         AND p.post_type = 'product'
         AND p.post_status = 'publish'
         AND pm.meta_value != ''"
    );
    
    $unique_skus = $wpdb->get_var(
        "SELECT COUNT(DISTINCT meta_value) FROM {$wpdb->postmeta} pm
         INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
         WHERE pm.meta_key = '_sku'
         AND p.post_type = 'product'
         AND p.post_status = 'publish'
         AND pm.meta_value != ''"
    );
    
    $duplicate_products = $products_with_sku - $unique_skus;
    
    echo "<h2>Statistike duplikata</h2>";
    echo "<p>Ukupno proizvoda: $total_products</p>";
    echo "<p>Proizvoda s SKU-om: $products_with_sku</p>";
    echo "<p>Jedinstvenih SKU-ova: $unique_skus</p>";
    echo "<p><strong>Duplikata za brisanje: $duplicate_products</strong></p>";
    
    return [
        'total' => $total_products,
        'with_sku' => $products_with_sku,
        'unique_skus' => $unique_skus,
        'duplicates' => $duplicate_products
    ];
}

// Test pozivi
if (isset($_GET['action'])) {
    echo "<html><body>";
    
    switch ($_GET['action']) {
        case 'stats':
            xpse_get_duplicate_stats();
            break;
            
        case 'find':
            xpse_find_duplicate_products();
            break;
            
        case 'clean_test':
            xpse_clean_duplicate_products(true); // dry run
            break;
            
        case 'clean_real':
            if (isset($_GET['confirm']) && $_GET['confirm'] === 'yes') {
                xpse_clean_duplicate_products(false); // stvarno brisanje
            } else {
                echo "<p style='color: red;'>Za stvarno brisanje dodajte ?action=clean_real&confirm=yes</p>";
                echo "<p><strong>PAŽNJA: Ovo će trajno obrisati duplikate!</strong></p>";
            }
            break;
            
        default:
            echo "<h2>Dostupne akcije:</h2>";
            echo "<ul>";
            echo "<li><a href='?action=stats'>Prikaži statistike duplikata</a></li>";
            echo "<li><a href='?action=find'>Pronađi duplikate</a></li>";
            echo "<li><a href='?action=clean_test'>Test čišćenja (neće brisati)</a></li>";
            echo "<li><a href='?action=clean_real&confirm=yes' style='color: red;'>STVARNO ČIŠĆENJE (OPASNO!)</a></li>";
            echo "</ul>";
    }
    
    echo "</body></html>";
}