/**
 * Admin JavaScript for XML Product Sync Enhanced
 * 
 * @package XML_Product_Sync_Enhanced
 */

(function($) {
    'use strict';
    
    // Global variables
    var syncInProgress = false;
    var syncProgressInterval = null;
    var statusUpdateInterval = null;
    var ajaxInProgress = {}; // Track ongoing AJAX requests
    
    $(document).ready(function() {
        initDashboard();
        initSyncControls();
        initLogManagement();
        initSettingsPage();
        initToolsPage();
        
        // Cleanup intervals when page unloads
        $(window).on('beforeunload', function() {
            clearAllIntervals();
        });
    });
    
    /**
     * Clear all intervals to prevent memory leaks
     */
    function clearAllIntervals() {
        if (syncProgressInterval) {
            clearInterval(syncProgressInterval);
            syncProgressInterval = null;
        }
        if (statusUpdateInterval) {
            clearInterval(statusUpdateInterval);
            statusUpdateInterval = null;
        }
    }
    
    /**
     * Initialize dashboard functionality
     */
    function initDashboard() {
        // Auto-refresh dashboard when sync is running - but less frequently
        if ($('.status-running').length > 0) {
            startProgressTracking();
        }
        
        // Real-time sync status updates - reduced frequency
        if ($('#xpse-sync-status').length > 0) {
            // Only update every 30 seconds instead of 10
            statusUpdateInterval = setInterval(updateSyncStatus, 30000);
        }
    }
    
    /**
     * Initialize sync controls
     */
    function initSyncControls() {
        // Manual sync button
        $('#xpse-manual-sync').on('click', function(e) {
            e.preventDefault();
            startManualSync();
        });
        
        // Cancel sync button
        $('#xpse-cancel-sync').on('click', function(e) {
            e.preventDefault();
            cancelSync();
        });
        
        // Sync form submission
        $('form[data-sync-form]').on('submit', function(e) {
            e.preventDefault();
            startCustomSync($(this));
        });
    }
    
    /**
     * Start manual sync
     */
    function startManualSync() {
        if (syncInProgress) {
            return;
        }
        
        // Prevent multiple simultaneous requests
        if (ajaxInProgress['startManualSync']) {
            return;
        }
        
        ajaxInProgress['startManualSync'] = true;
        
        var $btn = $('#xpse-manual-sync');
        $btn.prop('disabled', true).text('Pokretam...');
        
        $.ajax({
            url: xpse_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'xpse_manual_sync',
                nonce: xpse_admin.nonce
            },
            success: function(response) {
                if (response.success) {
                    syncInProgress = true;
                    startProgressTracking();
                    showNotice('Sinhronizacija je pokrenuta.', 'success');
                } else {
                    showNotice('Greška: ' + response.data, 'error');
                    $btn.prop('disabled', false).text('Pokreni Sync');
                }
            },
            error: function() {
                showNotice('Greška pri pokretanju sinhronizacije.', 'error');
                $btn.prop('disabled', false).text('Pokreni Sync');
            },
            complete: function() {
                ajaxInProgress['startManualSync'] = false;
            }
        });
    }
    
    /**
     * Cancel running sync
     */
    function cancelSync() {
        if (!confirm(xpse_admin.strings.confirm_cancel_sync)) {
            return;
        }
        
        $.ajax({
            url: xpse_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'xpse_cancel_sync',
                nonce: xpse_admin.nonce
            },
            success: function(response) {
                if (response.success) {
                    syncInProgress = false;
                    stopProgressTracking();
                    showNotice('Sinhronizacija je otkazana.', 'info');
                    setTimeout(function() {
                        location.reload();
                    }, 2000);
                } else {
                    showNotice('Greška: ' + response.data, 'error');
                }
            },
            error: function() {
                showNotice('Greška pri otkazivanju sinhronizacije.', 'error');
            }
        });
    }
    
    /**
     * Start custom sync with options
     */
    function startCustomSync($form) {
        if (syncInProgress) {
            return;
        }
        
        var formData = $form.serialize();
        var $btn = $form.find('button[type="submit"]');
        
        $btn.prop('disabled', true).text('Pokretam...');
        
        $.ajax({
            url: xpse_admin.ajax_url,
            type: 'POST',
            data: formData + '&action=xpse_custom_sync&nonce=' + xpse_admin.nonce,
            success: function(response) {
                if (response.success) {
                    syncInProgress = true;
                    startProgressTracking();
                    showNotice('Prilagođena sinhronizacija je pokrenuta.', 'success');
                } else {
                    showNotice('Greška: ' + response.data, 'error');
                    $btn.prop('disabled', false).text('Pokreni Sinhronizaciju');
                }
            },
            error: function() {
                showNotice('Greška pri pokretanju sinhronizacije.', 'error');
                $btn.prop('disabled', false).text('Pokreni Sinhronizaciju');
            }
        });
    }
    
    /**
     * Start progress tracking
     */
    function startProgressTracking() {
        if (syncProgressInterval) {
            clearInterval(syncProgressInterval);
        }
        
        $('#manual-sync-progress').show();
        
        // Reduced frequency - update every 15 seconds instead of 2
        syncProgressInterval = setInterval(function() {
            updateSyncProgress();
        }, 15000);
    }
    
    /**
     * Stop progress tracking
     */
    function stopProgressTracking() {
        if (syncProgressInterval) {
            clearInterval(syncProgressInterval);
            syncProgressInterval = null;
        }
        
        $('#manual-sync-progress').hide();
    }
    
    /**
     * Update sync progress
     */
    function updateSyncProgress() {
        // Prevent multiple simultaneous requests
        if (ajaxInProgress['updateSyncProgress']) {
            return;
        }
        
        ajaxInProgress['updateSyncProgress'] = true;
        
        $.ajax({
            url: xpse_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'xpse_get_sync_status',
                nonce: xpse_admin.nonce
            },
            success: function(response) {
                if (response.success) {
                    var data = response.data;
                    
                    if (data.status === 'running') {
                        updateProgressBar(data.progress);
                    } else {
                        // Sync completed or failed
                        syncInProgress = false;
                        stopProgressTracking();
                        
                        if (data.status === 'completed') {
                            showNotice('Sinhronizacija je završena uspešno.', 'success');
                        } else if (data.status === 'failed') {
                            showNotice('Sinhronizacija je neuspešna. Proverite logove.', 'error');
                        }
                        
                        setTimeout(function() {
                            location.reload();
                        }, 3000);
                    }
                }
            },
            complete: function() {
                ajaxInProgress['updateSyncProgress'] = false;
            }
        });
    }
    
    /**
     * Update progress bar
     */
    function updateProgressBar(progress) {
        if (!progress) return;
        
        var percentage = 0;
        var info = '';
        
        if (progress.total_items > 0) {
            percentage = Math.round((progress.processed / progress.total_items) * 100);
        }
        
        info = progress.processed + ' / ' + progress.total_items + ' proizvoda';
        
        if (progress.current_step) {
            info += ' (' + progress.current_step + ')';
        }
        
        $('.progress-fill').css('width', percentage + '%');
        $('.progress-info').text(percentage + '% - ' + info);
    }
    
    /**
     * Update sync status
     */
    function updateSyncStatus() {
        // Prevent multiple simultaneous requests
        if (ajaxInProgress['updateSyncStatus']) {
            return;
        }
        
        ajaxInProgress['updateSyncStatus'] = true;
        
        $.ajax({
            url: xpse_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'xpse_get_sync_status',
                nonce: xpse_admin.nonce
            },
            success: function(response) {
                if (response.success) {
                    var status = response.data.status;
                    var $indicator = $('.xpse-status-indicator');
                    
                    // Update status indicator
                    $indicator.removeClass('status-idle status-running status-completed status-failed')
                             .addClass('status-' + status);
                    
                    // Update status text
                    var statusText = {
                        'idle': 'Spreman',
                        'running': 'U toku',
                        'completed': 'Završen',
                        'failed': 'Neuspešan'
                    };
                    
                    $('.status-text').text(statusText[status] || status);
                }
            },
            complete: function() {
                ajaxInProgress['updateSyncStatus'] = false;
            }
        });
    }
    
    /**
     * Initialize log management
     */
    function initLogManagement() {
        // Export logs functionality is handled by individual functions
        // Clear logs functionality is handled by individual functions
        
        // Auto-refresh error logs - only if on logs page and no sync running
        if ($('.xpse-error-logs').length > 0 && !syncInProgress) {
            setInterval(function() {
                refreshErrorLogs();
            }, 60000); // Refresh every 60 seconds instead of 30
        }
    }
    
    /**
     * Refresh error logs
     */
    function refreshErrorLogs() {
        $.ajax({
            url: xpse_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'xpse_get_recent_errors',
                nonce: xpse_admin.nonce
            },
            success: function(response) {
                if (response.success && response.data.length > 0) {
                    // Update error logs section
                    // This would require server-side implementation
                }
            }
        });
    }
    
    /**
     * Initialize settings page
     */
    function initSettingsPage() {
        // Tab switching
        $('.nav-tab').on('click', function(e) {
            e.preventDefault();
            
            var href = $(this).attr('href');
            var tab = href.split('tab=')[1];
            
            if (tab) {
                switchSettingsTab(tab);
            }
        });
        
        // Settings validation
        $('form[action=""]').on('submit', function() {
            return validateSettings($(this));
        });
        
        // Import/Export functionality
        $('input[name="import_file"]').on('change', function() {
            validateImportFile($(this));
        });
    }
    
    /**
     * Switch settings tab
     */
    function switchSettingsTab(tab) {
        $('.nav-tab').removeClass('nav-tab-active');
        $('.nav-tab[href*="tab=' + tab + '"]').addClass('nav-tab-active');
        
        // Show/hide tab content would require server-side implementation
        // For now, redirect to the tab URL
        window.location.href = '?page=xpse-settings&tab=' + tab;
    }
    
    /**
     * Validate settings form
     */
    function validateSettings($form) {
        var isValid = true;
        var errors = [];
        
        // XML URL validation
        var xmlUrl = $form.find('input[name="xpse_xml_url"]').val();
        if (xmlUrl && !isValidUrl(xmlUrl)) {
            errors.push('XML URL nije valjan.');
            isValid = false;
        }
        
        // Batch size validation
        var batchSize = parseInt($form.find('input[name="xpse_batch_size"]').val());
        if (batchSize && (batchSize < 10 || batchSize > 500)) {
            errors.push('Batch veličina mora biti između 10 i 500.');
            isValid = false;
        }
        
        // Memory limit validation
        var memoryLimit = parseInt($form.find('input[name="xpse_memory_limit_mb"]').val());
        if (memoryLimit && memoryLimit < 128) {
            errors.push('Memory limit mora biti najmanje 128MB.');
            isValid = false;
        }
        
        if (!isValid) {
            showNotice('Greške u postavkama:\n' + errors.join('\n'), 'error');
        }
        
        return isValid;
    }
    
    /**
     * Validate import file
     */
    function validateImportFile($input) {
        var file = $input[0].files[0];
        
        if (file) {
            if (file.type !== 'application/json' && !file.name.endsWith('.json')) {
                showNotice('Molimo izaberite JSON datoteku.', 'warning');
                $input.val('');
                return false;
            }
            
            if (file.size > 1024 * 1024) { // 1MB
                showNotice('Datoteka je prevelika. Maksimalno 1MB.', 'warning');
                $input.val('');
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Initialize tools page
     */
    function initToolsPage() {
        // Connection test is handled in the specific view file
        
        // System info copy functionality is handled in the specific view file
        
        // Database optimization
        $('#optimize-database').on('click', function() {
            optimizeDatabase();
        });
        
        // Cleanup tools are handled by individual functions
    }
    
    /**
     * Show notification
     */
    function showNotice(message, type) {
        type = type || 'info';
        
        var $notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
        
        // Remove existing notices of the same type
        $('.notice-' + type).remove();
        
        // Add new notice
        $('.wrap > h1').after($notice);
        
        // Auto-dismiss after 5 seconds
        setTimeout(function() {
            $notice.fadeOut(function() {
                $(this).remove();
            });
        }, 5000);
        
        // Scroll to top to show notice
        $('html, body').animate({ scrollTop: 0 }, 300);
    }
    
    /**
     * Utility functions
     */
    function isValidUrl(string) {
        try {
            new URL(string);
            return true;
        } catch (_) {
            return false;
        }
    }
    
    function formatBytes(bytes, decimals = 2) {
        if (bytes === 0) return '0 B';
        
        const k = 1024;
        const dm = decimals < 0 ? 0 : decimals;
        const sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        
        return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
    }
    
    function formatDuration(seconds) {
        const hours = Math.floor(seconds / 3600);
        const minutes = Math.floor((seconds % 3600) / 60);
        const secs = seconds % 60;
        
        if (hours > 0) {
            return hours + 'h ' + minutes + 'm ' + secs + 's';
        } else if (minutes > 0) {
            return minutes + 'm ' + secs + 's';
        } else {
            return secs + 's';
        }
    }
    
    // Global functions for inline event handlers
    window.xpseAdmin = {
        exportLogs: function(session, format) {
            // Function is defined in logs.php view file
            if (typeof exportLogs === 'function') {
                exportLogs(session, format);
            }
        },
        
        clearLogs: function(session) {
            // Function is defined in logs.php view file
            if (typeof clearLogs === 'function') {
                clearLogs(session);
            }
        },
        
        cleanupCategories: function(dryRun) {
            // Function is defined in categories.php view file
            if (typeof cleanupCategories === 'function') {
                cleanupCategories(dryRun);
            }
        },
        
        cleanupOrphanedImages: function(dryRun) {
            // Function is defined in tools.php view file
            if (typeof cleanupOrphanedImages === 'function') {
                cleanupOrphanedImages(dryRun);
            }
        },
        
        optimizeDatabase: function() {
            // Function is defined in tools.php view file
            if (typeof optimizeDatabase === 'function') {
                optimizeDatabase();
            }
        },
        
        copySystemInfo: function() {
            // Function is defined in tools.php view file
            if (typeof copySystemInfo === 'function') {
                copySystemInfo();
            }
        }
    };
    
})(jQuery);