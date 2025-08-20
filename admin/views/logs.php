<?php
/**
 * Logs view for XML Product Sync Enhanced
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1>XML Product Sync Enhanced - Logovi</h1>
    
    <?php settings_errors(); ?>
    
    <div class="xpse-logs-header">
        <div class="xpse-logs-filters">
            <form method="get" action="">
                <input type="hidden" name="page" value="xpse-logs" />
                
                <label for="session-filter">Sync Sesija:</label>
                <select name="session" id="session-filter">
                    <option value="">Sve sesije</option>
                    <?php foreach ($sessions as $session): ?>
                        <option value="<?php echo esc_attr($session['sync_session']); ?>" 
                                <?php selected($current_session, $session['sync_session']); ?>>
                            <?php echo esc_html($session['sync_session']); ?> 
                            (<?php echo esc_html($session['start_time']); ?> - 
                             <?php echo $session['error_count'] > 0 ? $session['error_count'] . ' grešaka' : 'OK'; ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                
                <label for="level-filter">Log Level:</label>
                <select name="level" id="level-filter">
                    <option value="">Svi nivoi</option>
                    <option value="debug" <?php selected($log_level, 'debug'); ?>>Debug</option>
                    <option value="info" <?php selected($log_level, 'info'); ?>>Info</option>
                    <option value="warning" <?php selected($log_level, 'warning'); ?>>Warning</option>
                    <option value="error" <?php selected($log_level, 'error'); ?>>Error</option>
                    <option value="critical" <?php selected($log_level, 'critical'); ?>>Critical</option>
                </select>
                
                <input type="submit" class="button" value="Filtriraj" />
            </form>
        </div>
        
        <div class="xpse-logs-actions">
            <?php if ($current_session): ?>
                <button type="button" class="button" onclick="exportLogs('<?php echo esc_js($current_session); ?>', 'csv')">Export CSV</button>
                <button type="button" class="button" onclick="exportLogs('<?php echo esc_js($current_session); ?>', 'json')">Export JSON</button>
                <button type="button" class="button button-secondary" onclick="clearLogs('<?php echo esc_js($current_session); ?>')">Obriši Sesiju</button>
            <?php else: ?>
                <button type="button" class="button" onclick="exportLogs('', 'csv')">Export Svih (CSV)</button>
                <button type="button" class="button" onclick="exportLogs('', 'json')">Export Svih (JSON)</button>
                <button type="button" class="button button-secondary" onclick="clearLogs()">Obriši Sve Logove</button>
            <?php endif; ?>
        </div>
    </div>
    
    <?php if (!empty($error_logs)): ?>
    <div class="xpse-error-summary">
        <h2>Poslednje Greške</h2>
        <div class="xpse-error-logs">
            <?php foreach ($error_logs as $error): ?>
                <div class="xpse-error-item level-<?php echo esc_attr($error['level']); ?>">
                    <div class="error-header">
                        <span class="error-time"><?php echo esc_html($error['timestamp']); ?></span>
                        <span class="error-level"><?php echo esc_html(strtoupper($error['level'])); ?></span>
                    </div>
                    <div class="error-message"><?php echo esc_html($error['message']); ?></div>
                    <?php if (!empty($error['context'])): ?>
                        <div class="error-context">
                            <details>
                                <summary>Prikaži detalje</summary>
                                <pre><?php echo esc_html(json_encode(json_decode($error['context']), JSON_PRETTY_PRINT)); ?></pre>
                            </details>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
    
    <div class="xpse-logs-table">
        <h2>Logovi <?php echo $current_session ? 'za sesiju: ' . esc_html($current_session) : '(svi)'; ?></h2>
        
        <?php if (!empty($logs)): ?>
            <table class="wp-list-table widefat fixed striped logs">
                <thead>
                    <tr>
                        <th scope="col" class="column-timestamp">Vrijeme</th>
                        <th scope="col" class="column-level">Nivo</th>
                        <th scope="col" class="column-message">Poruka</th>
                        <th scope="col" class="column-session">Sesija</th>
                        <th scope="col" class="column-context">Kontekst</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log): ?>
                        <tr class="log-row level-<?php echo esc_attr($log['level']); ?>">
                            <td class="column-timestamp">
                                <?php echo esc_html(date('d.m.Y H:i:s', strtotime($log['timestamp']))); ?>
                            </td>
                            <td class="column-level">
                                <span class="log-level level-<?php echo esc_attr($log['level']); ?>">
                                    <?php echo esc_html(strtoupper($log['level'])); ?>
                                </span>
                            </td>
                            <td class="column-message">
                                <?php echo esc_html($log['message']); ?>
                            </td>
                            <td class="column-session">
                                <?php if ($log['sync_session']): ?>
                                    <a href="?page=xpse-logs&session=<?php echo esc_attr($log['sync_session']); ?>">
                                        <?php echo esc_html($log['sync_session']); ?>
                                    </a>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td class="column-context">
                                <?php if (!empty($log['context'])): ?>
                                    <details>
                                        <summary>Prikaži</summary>
                                        <pre class="log-context"><?php echo esc_html(json_encode(json_decode($log['context']), JSON_PRETTY_PRINT)); ?></pre>
                                    </details>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <div class="xpse-logs-pagination">
                <p>Prikazano: <?php echo count($logs); ?> logova</p>
                <?php if (count($logs) >= $per_page): ?>
                    <p><em>Možda postoji više logova. Koristite filtere za precizniji prikaz.</em></p>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="xpse-no-logs">
                <p>Nema logova za prikaz.</p>
                <?php if ($current_session || $log_level): ?>
                    <p><a href="?page=xpse-logs">Prikaži sve logove</a></p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <div class="xpse-logs-cleanup">
        <h3>Čišćenje Logova</h3>
        <form method="post" action="" onsubmit="return confirm('Da li ste sigurni da želite obrisati logove?');">
            <?php wp_nonce_field('xpse_logs'); ?>
            
            <label>
                <input type="radio" name="cleanup_type" value="older_than" checked />
                Obriši logove starije od 
                <input type="number" name="older_than_days" value="30" min="1" max="365" style="width: 60px;" /> dana
            </label><br />
            
            <label>
                <input type="radio" name="cleanup_type" value="all" />
                Obriši sve logove
            </label><br />
            
            <button type="submit" name="action" value="cleanup_logs" class="button button-secondary">Pokreni Čišćenje</button>
        </form>
    </div>
</div>

<script type="text/javascript">
function exportLogs(session, format) {
    var form = document.createElement('form');
    form.method = 'POST';
    form.action = '';
    
    var fields = {
        'action': 'xpse_export_logs',
        'nonce': xpse_admin.nonce,
        'session': session,
        'format': format
    };
    
    for (var key in fields) {
        var input = document.createElement('input');
        input.type = 'hidden';
        input.name = key;
        input.value = fields[key];
        form.appendChild(input);
    }
    
    document.body.appendChild(form);
    
    jQuery.ajax({
        url: xpse_admin.ajax_url,
        type: 'POST',
        data: jQuery(form).serialize(),
        success: function(response) {
            if (response.success) {
                var blob = new Blob([response.data.data], {type: response.data.mime_type});
                var url = window.URL.createObjectURL(blob);
                var a = document.createElement('a');
                a.href = url;
                a.download = response.data.filename;
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                window.URL.revokeObjectURL(url);
            } else {
                alert('Greška: ' + response.data);
            }
        },
        error: function() {
            alert('Greška pri exportu logova.');
        }
    });
    
    document.body.removeChild(form);
}

function clearLogs(session) {
    if (!confirm(xpse_admin.strings.confirm_clear_logs)) {
        return;
    }
    
    jQuery.ajax({
        url: xpse_admin.ajax_url,
        type: 'POST',
        data: {
            action: 'xpse_clear_logs',
            nonce: xpse_admin.nonce,
            session: session || ''
        },
        success: function(response) {
            if (response.success) {
                alert(response.data.message);
                location.reload();
            } else {
                alert('Greška: ' + response.data);
            }
        },
        error: function() {
            alert('Greška pri brisanju logova.');
        }
    });
}
</script>