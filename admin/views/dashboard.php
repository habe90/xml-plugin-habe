<?php
/**
 * Dashboard view for XML Product Sync Enhanced
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1>XML Product Sync Enhanced - Dashboard</h1>
    
    <?php settings_errors(); ?>
    
    <div class="xpse-dashboard">
        <div class="xpse-row">
            <!-- Sync Status Card -->
            <div class="xpse-card xpse-sync-status">
                <h2>Status Sinhronizacije</h2>
                <div class="xpse-status-indicator status-<?php echo esc_attr($sync_status); ?>">
                    <span class="status-dot"></span>
                    <span class="status-text">
                        <?php
                        switch ($sync_status) {
                            case 'running':
                                echo 'U toku';
                                break;
                            case 'completed':
                                echo 'Završen';
                                break;
                            case 'failed':
                                echo 'Neuspešan';
                                break;
                            default:
                                echo 'Spreman';
                        }
                        ?>
                    </span>
                </div>
                
                <?php if ($sync_status === 'running' && !empty($sync_progress)): ?>
                    <div class="xpse-progress">
                        <?php 
                        $progress_percent = 0;
                        if (isset($sync_progress['stats']['total_items']) && $sync_progress['stats']['total_items'] > 0) {
                            $progress_percent = round(($sync_progress['stats']['processed'] / $sync_progress['stats']['total_items']) * 100);
                        }
                        ?>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: <?php echo $progress_percent; ?>%"></div>
                        </div>
                        <p><?php echo $progress_percent; ?>% - <?php echo $sync_progress['stats']['processed']; ?> / <?php echo $sync_progress['stats']['total_items']; ?> proizvoda</p>
                    </div>
                <?php endif; ?>
                
                <div class="xpse-actions">
                    <?php if ($sync_status !== 'running'): ?>
                        <button type="button" class="button button-primary" id="xpse-manual-sync">
                            Pokretaj Sync
                        </button>
                    <?php else: ?>
                        <button type="button" class="button button-secondary" id="xpse-cancel-sync">
                            Otkaži Sync
                        </button>
                    <?php endif; ?>
                    
                    <a href="<?php echo XPSE_Admin::get_admin_url('logs'); ?>" class="button">
                        Pogledaj Logove
                    </a>
                </div>
            </div>
            
            <!-- Statistics Card -->
            <div class="xpse-card xpse-statistics">
                <h2>Statistike Poslednjeg Sync-a</h2>
                <?php if (!empty($sync_stats)): ?>
                    <div class="xpse-stats-grid">
                        <div class="stat-item">
                            <span class="stat-value"><?php echo number_format($sync_stats['processed'] ?? 0); ?></span>
                            <span class="stat-label">Obrađeno</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-value"><?php echo number_format($sync_stats['created'] ?? 0); ?></span>
                            <span class="stat-label">Kreirano</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-value"><?php echo number_format($sync_stats['updated'] ?? 0); ?></span>
                            <span class="stat-label">Ažurirano</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-value"><?php echo number_format($sync_stats['errors'] ?? 0); ?></span>
                            <span class="stat-label">Greške</span>
                        </div>
                    </div>
                    
                    <?php if (isset($sync_stats['start_time'], $sync_stats['end_time'])): ?>
                        <p class="xpse-duration">
                            Trajanje: <?php echo XPSE_Utilities::format_duration($sync_stats['end_time'] - $sync_stats['start_time']); ?>
                        </p>
                    <?php endif; ?>
                <?php else: ?>
                    <p>Nema podataka o poslednje sync operaciji.</p>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="xpse-row">
            <!-- Schedule Card -->
            <div class="xpse-card xpse-schedule">
                <h2>Raspored</h2>
                <p><strong>Interval:</strong> 
                    <?php 
                    $interval = $this->settings->get('sync_interval');
                    $intervals = [
                        'xpse_fifteen_minutes' => 'Svakih 15 minuta',
                        'xpse_thirty_minutes' => 'Svakih 30 minuta',
                        'hourly' => 'Svaki sat',
                        'xpse_two_hours' => 'Svaka 2 sata',
                        'xpse_six_hours' => 'Svakih 6 sati',
                        'xpse_twelve_hours' => 'Svakih 12 sati',
                        'daily' => 'Jednom dnevno'
                    ];
                    echo $intervals[$interval] ?? $interval;
                    ?>
                </p>
                
                <?php if ($next_sync): ?>
                    <p><strong>Sledeći sync:</strong> 
                        <?php echo date_i18n('d.m.Y H:i:s', $next_sync); ?>
                        (za <?php echo human_time_diff(time(), $next_sync); ?>)
                    </p>
                <?php else: ?>
                    <p><strong>Sledeći sync:</strong> Nije zakazan</p>
                <?php endif; ?>
                
                <a href="<?php echo XPSE_Admin::get_admin_url('settings'); ?>" class="button">
                    Promijeni Postavke
                </a>
            </div>
            
            <!-- System Info Card -->
            <div class="xpse-card xpse-system-info">
                <h2>Sistemske Informacije</h2>
                <table class="xpse-info-table">
                    <tr>
                        <td>WordPress:</td>
                        <td><?php echo $system_info['wordpress']; ?></td>
                    </tr>
                    <tr>
                        <td>WooCommerce:</td>
                        <td><?php echo $system_info['woocommerce']; ?></td>
                    </tr>
                    <tr>
                        <td>PHP:</td>
                        <td><?php echo $system_info['php']; ?></td>
                    </tr>
                    <tr>
                        <td>Memory Limit:</td>
                        <td><?php echo $system_info['memory_limit']; ?></td>
                    </tr>
                    <tr>
                        <td>Memory Usage:</td>
                        <td><?php echo $memory_usage['used_formatted']; ?> / <?php echo $memory_usage['limit_formatted']; ?></td>
                    </tr>
                    <tr>
                        <td>Max Execution Time:</td>
                        <td><?php echo $system_info['max_execution_time']; ?>s</td>
                    </tr>
                </table>
            </div>
        </div>
        
        <?php if (!empty($recent_logs)): ?>
        <div class="xpse-row">
            <div class="xpse-card xpse-recent-logs">
                <h2>Poslednji Logovi</h2>
                <div class="xpse-logs-table">
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th>Vrijeme</th>
                                <th>Nivo</th>
                                <th>Poruka</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_logs as $log): ?>
                                <tr>
                                    <td><?php echo esc_html($log['timestamp']); ?></td>
                                    <td><span class="log-level level-<?php echo esc_attr($log['level']); ?>"><?php echo esc_html(strtoupper($log['level'])); ?></span></td>
                                    <td><?php echo esc_html($log['message']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <a href="<?php echo XPSE_Admin::get_admin_url('logs'); ?>" class="button">
                    Pogledaj Sve Logove
                </a>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
    // Auto-refresh when sync is running
    if ('<?php echo $sync_status; ?>' === 'running') {
        setInterval(function() {
            location.reload();
        }, 5000); // Refresh every 5 seconds
    }
    
    // Manual sync button
    $('#xpse-manual-sync').on('click', function() {
        var $btn = $(this);
        $btn.prop('disabled', true).text('Pokrećem...');
        
        $.ajax({
            url: xpse_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'xpse_manual_sync',
                nonce: xpse_admin.nonce
            },
            success: function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert('Greška: ' + response.data);
                    $btn.prop('disabled', false).text('Pokretaj Sync');
                }
            },
            error: function() {
                alert('AJAX greška');
                $btn.prop('disabled', false).text('Pokretaj Sync');
            }
        });
    });
    
    // Cancel sync button
    $('#xpse-cancel-sync').on('click', function() {
        if (!confirm(xpse_admin.strings.confirm_cancel_sync)) {
            return;
        }
        
        var $btn = $(this);
        $btn.prop('disabled', true).text('Otkazujem...');
        
        $.ajax({
            url: xpse_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'xpse_cancel_sync',
                nonce: xpse_admin.nonce
            },
            success: function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert('Greška: ' + response.data);
                    $btn.prop('disabled', false).text('Otkaži Sync');
                }
            },
            error: function() {
                alert('AJAX greška');
                $btn.prop('disabled', false).text('Otkaži Sync');
            }
        });
    });
});
</script>