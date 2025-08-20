<?php
/**
 * Settings view for XML Product Sync Enhanced
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1>XML Product Sync Enhanced - Postavke</h1>
    
    <?php settings_errors(); ?>
    
    <div class="nav-tab-wrapper">
        <a href="?page=xpse-settings&tab=general" class="nav-tab <?php echo $active_tab === 'general' ? 'nav-tab-active' : ''; ?>">Opće</a>
        <a href="?page=xpse-settings&tab=sync" class="nav-tab <?php echo $active_tab === 'sync' ? 'nav-tab-active' : ''; ?>">Sinhronizacija</a>
        <a href="?page=xpse-settings&tab=categories" class="nav-tab <?php echo $active_tab === 'categories' ? 'nav-tab-active' : ''; ?>">Kategorije</a>
        <a href="?page=xpse-settings&tab=performance" class="nav-tab <?php echo $active_tab === 'performance' ? 'nav-tab-active' : ''; ?>">Performanse</a>
        <a href="?page=xpse-settings&tab=notifications" class="nav-tab <?php echo $active_tab === 'notifications' ? 'nav-tab-active' : ''; ?>">Obavijesti</a>
        <a href="?page=xpse-settings&tab=advanced" class="nav-tab <?php echo $active_tab === 'advanced' ? 'nav-tab-active' : ''; ?>">Napredne</a>
    </div>
    
    <form method="post" action="" enctype="multipart/form-data">
        <?php wp_nonce_field('xpse_settings'); ?>
        
        <?php if ($active_tab === 'general'): ?>
            <table class="form-table">
                <tr>
                    <th scope="row">XML Feed URL</th>
                    <td>
                        <input type="url" name="xpse_xml_url" value="<?php echo esc_attr($this->settings->get('xml_url')); ?>" class="regular-text" required />
                        <p class="description">URL do XML feed-a s prodanim podacima.</p>
                        <button type="button" class="button" id="test-connection">Testiraj Konekciju</button>
                        <span id="connection-result"></span>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Osnovne Kategorije</th>
                    <td>
                        <input type="text" name="xpse_default_category" value="<?php echo esc_attr($this->settings->get('default_category')); ?>" class="regular-text" />
                        <p class="description">Naziv default kategorije za proizvode bez kategorije.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Kreiraj Nedostajuće Kategorije</th>
                    <td>
                        <label>
                            <input type="checkbox" name="xpse_create_missing_categories" value="1" <?php checked($this->settings->get('create_missing_categories'), true); ?> />
                            Automatski kreiraj kategorije koje ne postoje u WooCommerce
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Ažuriraj Postojeće Proizvode</th>
                    <td>
                        <label>
                            <input type="checkbox" name="xpse_auto_update_existing" value="1" <?php checked($this->settings->get('auto_update_existing'), true); ?> />
                            Automatski ažuriraj postojeće proizvode tijekom sync-a
                        </label>
                    </td>
                </tr>
            </table>
        <?php endif; ?>
        
        <?php if ($active_tab === 'sync'): ?>
            <table class="form-table">
                <tr>
                    <th scope="row">Interval Sinhronizacije</th>
                    <td>
                        <select name="xpse_sync_interval">
                            <option value="xpse_fifteen_minutes" <?php selected($this->settings->get('sync_interval'), 'xpse_fifteen_minutes'); ?>>Svakih 15 minuta</option>
                            <option value="xpse_thirty_minutes" <?php selected($this->settings->get('sync_interval'), 'xpse_thirty_minutes'); ?>>Svakih 30 minuta</option>
                            <option value="hourly" <?php selected($this->settings->get('sync_interval'), 'hourly'); ?>>Svaki sat</option>
                            <option value="xpse_two_hours" <?php selected($this->settings->get('sync_interval'), 'xpse_two_hours'); ?>>Svaka 2 sata</option>
                            <option value="xpse_six_hours" <?php selected($this->settings->get('sync_interval'), 'xpse_six_hours'); ?>>Svakih 6 sati</option>
                            <option value="xpse_twelve_hours" <?php selected($this->settings->get('sync_interval'), 'xpse_twelve_hours'); ?>>Svakih 12 sati</option>
                            <option value="daily" <?php selected($this->settings->get('sync_interval'), 'daily'); ?>>Jednom dnevno</option>
                        </select>
                        <p class="description">Koliko često će se automatski pokretati sinhronizacija.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Rukovaj Varijante Proizvoda</th>
                    <td>
                        <label>
                            <input type="checkbox" name="xpse_handle_variants" value="1" <?php checked($this->settings->get('handle_variants'), true); ?> />
                            Kreiraj varijante proizvoda na osnovu specifikacija
                        </label>
                        <p class="description">Na primer, različite veličine ili boje kao varijante.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Preskoči Ažuriranje Slika</th>
                    <td>
                        <label>
                            <input type="checkbox" name="xpse_skip_images_update" value="1" <?php checked($this->settings->get('skip_images_update'), true); ?> />
                            Ne ažuriraj slike za postojeće proizvode
                        </label>
                        <p class="description">Pomaže u ubrzavanju sync procesa.</p>
                    </td>
                </tr>
            </table>
        <?php endif; ?>
        
        <?php if ($active_tab === 'performance'): ?>
            <table class="form-table">
                <tr>
                    <th scope="row">Batch Veličina</th>
                    <td>
                        <input type="number" name="xpse_batch_size" value="<?php echo esc_attr($this->settings->get('batch_size')); ?>" min="10" max="500" class="small-text" />
                        <p class="description">Broj proizvoda koji se obrađuje u jednom batch-u (10-500).</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Batch Delay</th>
                    <td>
                        <input type="number" name="xpse_batch_delay" value="<?php echo esc_attr($this->settings->get('batch_delay')); ?>" min="0" max="60" class="small-text" /> sekundi
                        <p class="description">Pauza između batch-ova da se smanji opterećenje servera.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Memory Limit</th>
                    <td>
                        <input type="number" name="xpse_memory_limit_mb" value="<?php echo esc_attr($this->settings->get('memory_limit_mb')); ?>" min="128" max="2048" class="small-text" /> MB
                        <p class="description">Maksimalna količina memorije koju plugin smije koristiti.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Max Execution Time</th>
                    <td>
                        <input type="number" name="xpse_max_execution_time" value="<?php echo esc_attr($this->settings->get('max_execution_time')); ?>" min="60" max="1800" class="small-text" /> sekundi
                        <p class="description">Maksimalno vrijeme izvršavanja jednog sync cycle-a.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Image Download Timeout</th>
                    <td>
                        <input type="number" name="xpse_image_download_timeout" value="<?php echo esc_attr($this->settings->get('image_download_timeout')); ?>" min="10" max="120" class="small-text" /> sekundi
                        <p class="description">Timeout za download slika proizvoda.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Maksimalno Pokušaja</th>
                    <td>
                        <input type="number" name="xpse_max_retries" value="<?php echo esc_attr($this->settings->get('max_retries')); ?>" min="1" max="10" class="small-text" />
                        <p class="description">Broj ponovnih pokušaja u slučaju greške.</p>
                    </td>
                </tr>
            </table>
        <?php endif; ?>
        
        <?php if ($active_tab === 'notifications'): ?>
            <table class="form-table">
                <tr>
                    <th scope="row">Email Obavijesti</th>
                    <td>
                        <label>
                            <input type="checkbox" name="xpse_enable_email_notifications" value="1" <?php checked($this->settings->get('enable_email_notifications'), true); ?> />
                            Pošalji email obavijesti za kritične greške
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Email Adresa</th>
                    <td>
                        <input type="email" name="xpse_notification_email" value="<?php echo esc_attr($this->settings->get('notification_email')); ?>" class="regular-text" />
                        <p class="description">Email adresa za primanje obavijesti.</p>
                    </td>
                </tr>
            </table>
        <?php endif; ?>
        
        <?php if ($active_tab === 'advanced'): ?>
            <table class="form-table">
                <tr>
                    <th scope="row">Enable Logging</th>
                    <td>
                        <label>
                            <input type="checkbox" name="xpse_enable_logging" value="1" <?php checked($this->settings->get('enable_logging'), true); ?> />
                            Aktiviraj detaljno logiranje
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Log Level</th>
                    <td>
                        <select name="xpse_log_level">
                            <option value="debug" <?php selected($this->settings->get('log_level'), 'debug'); ?>>Debug (sve)</option>
                            <option value="info" <?php selected($this->settings->get('log_level'), 'info'); ?>>Info</option>
                            <option value="warning" <?php selected($this->settings->get('log_level'), 'warning'); ?>>Warning</option>
                            <option value="error" <?php selected($this->settings->get('log_level'), 'error'); ?>>Error</option>
                            <option value="critical" <?php selected($this->settings->get('log_level'), 'critical'); ?>>Critical</option>
                        </select>
                        <p class="description">Minimalni nivo za logiranje.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Enable Progress Tracking</th>
                    <td>
                        <label>
                            <input type="checkbox" name="xpse_enable_progress_tracking" value="1" <?php checked($this->settings->get('enable_progress_tracking'), true); ?> />
                            Aktiviraj praćenje napretka sync operacija
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Enable Backup</th>
                    <td>
                        <label>
                            <input type="checkbox" name="xpse_enable_backup" value="1" <?php checked($this->settings->get('enable_backup'), true); ?> />
                            Kreiraj backup prije svakog sync-a
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Backup Retention</th>
                    <td>
                        <input type="number" name="xpse_backup_retention_days" value="<?php echo esc_attr($this->settings->get('backup_retention_days')); ?>" min="1" max="365" class="small-text" /> dana
                        <p class="description">Koliko dugo zadržati backup datoteke.</p>
                    </td>
                </tr>
            </table>
        <?php endif; ?>
        
        <div class="xpse-settings-actions">
            <?php submit_button('Spremi Postavke', 'primary', 'submit'); ?>
            
            <div class="xpse-secondary-actions">
                <h3>Upravljanje Postavkama</h3>
                
                <div class="xpse-action-group">
                    <h4>Reset Postavki</h4>
                    <p>Vrati postavke na default vrijednosti.</p>
                    <button type="submit" name="action" value="reset_defaults" class="button button-secondary" onclick="return confirm('Da li ste sigurni da želite resetovati sve postavke?');">Reset na Default</button>
                </div>
                
                <div class="xpse-action-group">
                    <h4>Export Postavki</h4>
                    <p>Preuzmi trenutne postavke kao JSON datoteku.</p>
                    <button type="submit" name="action" value="export_settings" class="button">Export Postavki</button>
                </div>
                
                <div class="xpse-action-group">
                    <h4>Import Postavki</h4>
                    <p>Učitaj postavke iz JSON datoteke.</p>
                    <input type="file" name="import_file" accept=".json" />
                    <button type="submit" name="action" value="import_settings" class="button" onclick="return confirm('Da li ste sigurni da želite importovati postavke? Ovo će prebrisati trenutne postavke.');">Import Postavki</button>
                </div>
            </div>
        </div>
    </form>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
    // Test connection
    $('#test-connection').on('click', function() {
        var $btn = $(this);
        var $result = $('#connection-result');
        var url = $('input[name="xpse_xml_url"]').val();
        
        if (!url) {
            $result.html('<span style="color: red;">Molimo unesite URL</span>');
            return;
        }
        
        $btn.prop('disabled', true).text('Testiram...');
        $result.html('<span style="color: orange;">Testiram konekciju...</span>');
        
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
                    $result.html('<span style="color: green;">✓ Konekcija uspješna - ' + response.data.message + '</span>');
                } else {
                    $result.html('<span style="color: red;">✗ Greška: ' + response.data + '</span>');
                }
            },
            error: function() {
                $result.html('<span style="color: red;">✗ Greška pri testiranju konekcije</span>');
            },
            complete: function() {
                $btn.prop('disabled', false).text('Testiraj Konekciju');
            }
        });
    });
});
</script>