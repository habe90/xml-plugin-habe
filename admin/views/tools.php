<?php
/**
 * Tools view for XML Product Sync Enhanced
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1>XML Product Sync Enhanced - Alati</h1>
    
    <?php settings_errors(); ?>
    
    <div class="xpse-tools">
        <!-- Connection Test -->
        <div class="xpse-card">
            <h2>Test Konekcije</h2>
            <p>Provjeri konekciju s XML feed-om i analiziraj sadržaj.</p>
            
            <div class="xpse-tool-form">
                <table class="form-table">
                    <tr>
                        <th scope="row">XML URL</th>
                        <td>
                            <input type="url" id="test-url" value="<?php echo esc_attr($xml_url); ?>" class="regular-text" />
                            <button type="button" class="button button-primary" id="test-connection-btn">Test Konekcije</button>
                        </td>
                    </tr>
                </table>
                
                <div id="connection-test-result">
                    <?php if ($connection_test): ?>
                        <div class="connection-result <?php echo $connection_test['success'] ? 'success' : 'error'; ?>">
                            <h4>Poslednji Test Rezultat:</h4>
                            <?php if ($connection_test['success']): ?>
                                <p style="color: green;">✓ Konekcija uspješna</p>
                                <ul>
                                    <li><strong>Response kod:</strong> <?php echo $connection_test['response_code']; ?></li>
                                    <li><strong>Content-Type:</strong> <?php echo $connection_test['content_type']; ?></li>
                                    <li><strong>Veličina:</strong> <?php echo size_format($connection_test['content_length']); ?></li>
                                    <li><strong>Broj proizvoda:</strong> 
    <?php echo isset($connection_test['product_count']) 
        ? number_format((int) $connection_test['product_count']) 
        : 'N/A'; ?>
</li>
                                    <li><strong>Response time:</strong> <?php echo $connection_test['response_time']; ?>ms</li>
                                </ul>
                            <?php else: ?>
                                <p style="color: red;">✗ Greška: <?php echo esc_html($connection_test['error']); ?></p>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Manual Sync Tools -->
        <div class="xpse-card">
            <h2>Manualna Sinhronizacija</h2>
            <p>Pokrenite sinhronizaciju ručno sa različitim opcijama.</p>
            
            <div class="xpse-sync-options">
                <form method="post" action="">
                    <?php wp_nonce_field('xpse_tools'); ?>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">Tip Sync-a</th>
                            <td>
                                <label><input type="radio" name="sync_type" value="full" checked /> Potpuna sinhronizacija</label><br />
                                <label><input type="radio" name="sync_type" value="incremental" /> Inkrementalna (samo promjene)</label><br />
                                <label><input type="radio" name="sync_type" value="categories_only" /> Samo kategorije</label><br />
                                <label><input type="radio" name="sync_type" value="products_only" /> Samo proizvodi</label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Opcije</th>
                            <td>
                                <label><input type="checkbox" name="force_update" value="1" /> Forsiraj ažuriranje postojećih proizvoda</label><br />
                                <label><input type="checkbox" name="skip_images" value="1" /> Preskoči download slika</label><br />
                                <label><input type="checkbox" name="dry_run" value="1" /> Dry run (samo pregled bez promjena)</label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Limit Proizvoda</th>
                            <td>
                                <input type="number" name="product_limit" placeholder="npr. 100" min="1" max="10000" class="small-text" />
                                <p class="description">Ograniči broj proizvoda za testiranje (prazan = svi).</p>
                            </td>
                        </tr>
                    </table>
                    
                    <button type="submit" name="action" value="manual_sync" class="button button-primary">Pokreni Sinhronizaciju</button>
                </form>
            </div>
            
            <div id="manual-sync-progress" style="display: none;">
                <h4>Napredak Sinhronizacije:</h4>
                <div class="progress-bar"><div class="progress-fill"></div></div>
                <div class="progress-info"></div>
            </div>
        </div>
        
        <!-- System Information -->
        <div class="xpse-card">
            <h2>Sistemske Informacije</h2>
            <p>Pregled sistemskih resursa i konfiguracije.</p>
            
            <?php 
            $system_info = XPSE_Utilities::get_system_info();
            $memory_usage = XPSE_Utilities::get_memory_usage();
            ?>
            
            <table class="xpse-system-info-table">
                <tr><th>WordPress verzija:</th><td><?php echo $system_info['wordpress']; ?></td></tr>
                <tr><th>WooCommerce verzija:</th><td><?php echo $system_info['woocommerce']; ?></td></tr>
                <tr><th>PHP verzija:</th><td><?php echo $system_info['php']; ?></td></tr>
                <tr><th>MySQL verzija:</th><td><?php echo $system_info['mysql']; ?></td></tr>
                <tr><th>Web server:</th><td><?php echo $system_info['server']; ?></td></tr>
                <tr><th>Memory limit:</th><td><?php echo $system_info['memory_limit']; ?></td></tr>
                <tr><th>Memory usage:</th><td><?php echo $memory_usage['used_formatted']; ?> / <?php echo $memory_usage['limit_formatted']; ?> (<?php echo $memory_usage['percentage']; ?>%)</td></tr>
                <tr><th>Max execution time:</th><td><?php echo $system_info['max_execution_time']; ?>s</td></tr>
                <tr><th>Upload max filesize:</th><td><?php echo $system_info['upload_max_filesize']; ?></td></tr>
                <tr><th>Post max size:</th><td><?php echo $system_info['post_max_size']; ?></td></tr>
                <tr><th>Allow URL fopen:</th><td><?php echo !empty($system_info['allow_url_fopen']) ? 'Da' : 'Ne'; ?></td></tr>
<tr><th>cURL dostupan:</th><td><?php echo !empty($system_info['curl_available']) ? 'Da' : 'Ne'; ?></td></tr>
<tr><th>SimpleXML dostupan:</th><td><?php echo !empty($system_info['simplexml_available']) ? 'Da' : 'Ne'; ?></td></tr>
<tr><th>GD ekstenzija:</th><td><?php echo !empty($system_info['gd_available']) ? 'Da' : 'Ne'; ?></td></tr>

            </table>
            
            <button type="button" class="button" onclick="copySystemInfo()">Kopiraj Sistemske Info</button>
        </div>
        
        <!-- Database Tools -->
        <div class="xpse-card">
            <h2>Alati za Bazu Podataka</h2>
            <p>Održavanje i optimizacija baze podataka.</p>
            
            <div class="xpse-tool-group">
                <h3>Statistike Baze</h3>
                <?php
                global $wpdb;
                $stats = [
                    'products' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'product'"),
                    'product_categories' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->terms} t INNER JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id WHERE tt.taxonomy = 'product_cat'"),
                    'product_images' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_mime_type LIKE 'image%'"),
                    'log_entries' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}xpse_sync_logs")
                ];
                ?>
                <ul>
                    <li><strong>Proizvodi:</strong> <?php echo number_format($stats['products']); ?></li>
                    <li><strong>Kategorije proizvoda:</strong> <?php echo number_format($stats['product_categories']); ?></li>
                    <li><strong>Slike proizvoda:</strong> <?php echo number_format($stats['product_images']); ?></li>
                    <li><strong>Log stavke:</strong> <?php echo number_format($stats['log_entries']); ?></li>
                </ul>
            </div>
            
            <div class="xpse-tool-group">
                <h3>Cleanup Operacije</h3>
                <form method="post" action="">
                    <?php wp_nonce_field('xpse_tools'); ?>
                    
                    <button type="submit" name="action" value="clear_all_logs" class="button button-secondary" onclick="return confirm('Da li ste sigurni da želite obrisati sve logove?');">Obriši Sve Logove</button>
                    
                    <button type="button" class="button button-secondary" onclick="cleanupOrphanedImages(true)">Pregled Orphaned Slika</button>
                    <button type="button" class="button button-secondary" onclick="cleanupOrphanedImages(false)">Obriši Orphaned Slike</button>
                    
                    <button type="submit" name="action" value="cleanup_temp_files" class="button button-secondary">Obriši Privremene Datoteke</button>
                    
                    <button type="button" class="button button-secondary" onclick="optimizeDatabase()">Optimiziraj Bazu</button>
                </form>
                
                <div id="cleanup-result"></div>
            </div>
        </div>
        
        <!-- Export/Import Tools -->
        <div class="xpse-card">
            <h2>Export/Import Alati</h2>
            <p>Izvoz i uvoz podataka o proizvodima i kategorijama.</p>
            
            <div class="xpse-tool-group">
                <h3>Export Proizvoda</h3>
                <form method="post" action="">
                    <?php wp_nonce_field('xpse_tools'); ?>
                    
                    <label>
                        Format: 
                        <select name="export_format">
                            <option value="csv">CSV</option>
                            <option value="json">JSON</option>
                            <option value="xml">XML</option>
                        </select>
                    </label>
                    
                    <label>
                        <input type="checkbox" name="include_categories" value="1" checked /> Uključi kategorije
                    </label>
                    
                    <label>
                        <input type="checkbox" name="include_images" value="1" checked /> Uključi linkove slika
                    </label>
                    
                    <button type="submit" name="action" value="export_products" class="button">Export Proizvoda</button>
                </form>
            </div>
            
            <div class="xpse-tool-group">
                <h3>Import Test</h3>
                <p>Testiraj import XML datoteke bez upisivanja u bazu.</p>
                <form method="post" action="" enctype="multipart/form-data">
                    <?php wp_nonce_field('xpse_tools'); ?>
                    
                    <input type="file" name="test_xml_file" accept=".xml" required />
                    <button type="submit" name="action" value="test_import" class="button">Test Import</button>
                </form>
                
                <div id="import-test-result"></div>
            </div>
        </div>
        
        <!-- Debug Tools -->
        <div class="xpse-card">
            <h2>Debug Alati</h2>
            <p>Alati za otklanjanje grešaka i razvoj.</p>
            
            <div class="xpse-tool-group">
                <h3>Plugin Info</h3>
                <table class="xpse-debug-info">
                    <tr><th>Plugin verzija:</th><td><?php echo XPSE_VERSION; ?></td></tr>
                    <tr><th>Plugin direktorij:</th><td><?php echo XPSE_PLUGIN_DIR; ?></td></tr>
                    <tr><th>Plugin URL:</th><td><?php echo XPSE_PLUGIN_URL; ?></td></tr>
                    <tr><th>Cron događaj:</th><td><?php echo wp_next_scheduled('xpse_sync_event') ? 'Zakazan' : 'Nije zakazan'; ?></td></tr>
                    <tr><th>WP Debug:</th><td><?php echo defined('WP_DEBUG') && WP_DEBUG ? 'Aktiviran' : 'Deaktiviran'; ?></td></tr>
                    <tr><th>WP Debug Log:</th><td><?php echo defined('WP_DEBUG_LOG') && WP_DEBUG_LOG ? 'Aktiviran' : 'Deaktiviran'; ?></td></tr>
                </table>
            </div>
            
            <div class="xpse-tool-group">
                <h3>Reset Tools</h3>
                <form method="post" action="">
                    <?php wp_nonce_field('xpse_tools'); ?>
                    
                    <button type="submit" name="action" value="reset_cron" class="button button-secondary">Reset Cron Schedules</button>
                    <button type="submit" name="action" value="clear_transients" class="button button-secondary">Obriši Transients</button>
                    <button type="submit" name="action" value="recreate_tables" class="button button-secondary" onclick="return confirm('Da li ste sigurni? Ovo će obrisati sve logove.');">Rekreiraj DB Tabele</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
    
    // Test connection
    $('#test-connection-btn').on('click', function() {
        var $btn = $(this);
        var $result = $('#connection-test-result');
        var url = $('#test-url').val();
        
        if (!url) {
            alert('Molimo unesite URL');
            return;
        }
        
        $btn.prop('disabled', true).text('Testiram...');
        $result.html('<p>Testiram konekciju...</p>');
        
        $.ajax({
            url: xpse_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'xpse_test_connection',
                nonce: xpse_admin.nonce,
                url: url
            },
            success: function(response) {
                if (response.success) {
                    var data = response.data;
                    var html = '<div class="connection-result success">';
                    html += '<h4>Test Rezultat:</h4>';
                    html += '<p style="color: green;">✓ Konekcija uspješna</p>';
                    html += '<ul>';
                    html += '<li><strong>Response kod:</strong> ' + data.response_code + '</li>';
                    html += '<li><strong>Content-Type:</strong> ' + data.content_type + '</li>';
                    html += '<li><strong>Veličina:</strong> ' + data.content_length_formatted + '</li>';
                    html += '<li><strong>Broj proizvoda:</strong> ' + data.product_count.toLocaleString() + '</li>';
                    html += '<li><strong>Response time:</strong> ' + data.response_time + 'ms</li>';
                    html += '</ul></div>';
                    $result.html(html);
                } else {
                    $result.html('<div class="connection-result error"><h4>Test Rezultat:</h4><p style="color: red;">✗ Greška: ' + response.data + '</p></div>');
                }
            },
            error: function() {
                $result.html('<div class="connection-result error"><p style="color: red;">✗ Greška pri testiranju konekcije</p></div>');
            },
            complete: function() {
                $btn.prop('disabled', false).text('Test Konekcije');
            }
        });
    });
});

function cleanupOrphanedImages(dryRun) {
    var $result = $('#cleanup-result');
    var action = dryRun ? 'pregled' : 'brisanje';
    
    if (!dryRun && !confirm(xpse_admin.strings.confirm_cleanup)) {
        return;
    }
    
    $result.html('<p>Pokretam ' + action + ' orphaned slika...</p>');
    
    jQuery.ajax({
        url: xpse_admin.ajax_url,
        type: 'POST',
        data: {
            action: 'xpse_cleanup_images',
            nonce: xpse_admin.nonce,
            dry_run: dryRun
        },
        success: function(response) {
            if (response.success) {
                $result.html('<div class="notice notice-success"><p>' + response.data.message + '</p></div>');
            } else {
                $result.html('<div class="notice notice-error"><p>Greška: ' + response.data + '</p></div>');
            }
        },
        error: function() {
            $result.html('<div class="notice notice-error"><p>Greška pri ' + action + ' slika.</p></div>');
        }
    });
}

function optimizeDatabase() {
    var $result = $('#cleanup-result');
    
    if (!confirm('Da li ste sigurni da želite optimizovati bazu podataka?')) {
        return;
    }
    
    $result.html('<p>Optimizovam bazu podataka...</p>');
    
    jQuery.ajax({
        url: xpse_admin.ajax_url,
        type: 'POST',
        data: {
            action: 'xpse_optimize_database',
            nonce: xpse_admin.nonce
        },
        success: function(response) {
            if (response.success) {
                $result.html('<div class="notice notice-success"><p>' + response.data.message + '</p></div>');
            } else {
                $result.html('<div class="notice notice-error"><p>Greška: ' + response.data + '</p></div>');
            }
        },
        error: function() {
            $result.html('<div class="notice notice-error"><p>Greška pri optimizaciji baze.</p></div>');
        }
    });
}

function copySystemInfo() {
    var systemInfo = '';
    jQuery('.xpse-system-info-table tr').each(function() {
        var th = jQuery(this).find('th').text();
        var td = jQuery(this).find('td').text();
        systemInfo += th + ' ' + td + '\n';
    });
    
    if (navigator.clipboard) {
        navigator.clipboard.writeText(systemInfo).then(function() {
            alert('Sistemske informacije su kopirane u clipboard.');
        });
    } else {
        // Fallback for older browsers
        var textArea = document.createElement('textarea');
        textArea.value = systemInfo;
        document.body.appendChild(textArea);
        textArea.select();
        document.execCommand('copy');
        document.body.removeChild(textArea);
        alert('Sistemske informacije su kopirane u clipboard.');
    }
}
</script>