<?php
/**
 * Logger class for XML Product Sync Enhanced
 *
 * @package XML_Product_Sync_Enhanced
 */

if (!defined('ABSPATH')) {
    exit;
}

class XPSE_Logger {
    
    const LEVEL_DEBUG = 'debug';
    const LEVEL_INFO = 'info';
    const LEVEL_WARNING = 'warning';
    const LEVEL_ERROR = 'error';
    const LEVEL_CRITICAL = 'critical';
    
    private $table_name;
    private $enabled;
    private $level;
    private $max_entries;
    private $current_session;
    
    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'xpse_sync_logs';
        $this->enabled = get_option('xpse_enable_logging', true);
        $this->level = get_option('xpse_log_level', self::LEVEL_INFO);
        $this->max_entries = apply_filters('xpse_max_log_entries', 10000);
        // Don't generate session ID immediately - will be generated when needed
        $this->current_session = null;
    }
    
    /**
     * Generate unique session ID
     */
    private function generate_session_id() {
        // Use PHP random string generation instead of wp_generate_password to avoid early load issues
        $random = function_exists('wp_generate_password') 
            ? wp_generate_password(8, false)
            : substr(str_shuffle('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 8);
        
        return 'sync_' . date('Y-m-d_H-i-s') . '_' . $random;
    }
    
    /**
     * Start new sync session
     */
    public function start_session() {
        $this->current_session = $this->generate_session_id();
        $this->log('Nova sync sesija pokrećena', self::LEVEL_INFO, [], $this->current_session);
        return $this->current_session;
    }
    
    /**
     * End current sync session
     */
    public function end_session($stats = []) {
        $message = 'Sync sesija završena';
        if (!empty($stats)) {
            $message .= ' - ' . $this->format_stats($stats);
        }
        $this->log($message, self::LEVEL_INFO, $stats, $this->get_current_session());
    }
    
    /**
     * Log message
     */
    public function log($message, $level = self::LEVEL_INFO, $context = [], $session = null) {
        if (!$this->enabled || !$this->should_log($level)) {
            return false;
        }
        
        global $wpdb;
        
        $session = $session ?: $this->get_current_session();
        
        // Add backtrace for errors
        if (in_array($level, [self::LEVEL_ERROR, self::LEVEL_CRITICAL])) {
            if (empty($context['backtrace'])) {
                $context['backtrace'] = wp_debug_backtrace_summary(__CLASS__, 0, false);
            }
        }
        
        $result = $wpdb->insert(
            $this->table_name,
            [
                'timestamp' => current_time('mysql'),
                'level' => $level,
                'message' => $message,
                'context' => !empty($context) ? wp_json_encode($context) : '',
                'sync_session' => $session
            ],
            ['%s', '%s', '%s', '%s', '%s']
        );
        
        // Cleanup old entries periodically
        if ($result && rand(1, 100) === 1) {
            $this->cleanup_old_entries();
        }
        
        // Send email notification for critical errors
        if ($level === self::LEVEL_CRITICAL && get_option('xpse_enable_email_notifications', false)) {
            $this->send_email_notification($message, $context);
        }
        
        // Also log to WordPress debug.log if WP_DEBUG is enabled
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $formatted_message = sprintf('[XPSE %s] %s', strtoupper($level), $message);
            if (!empty($context)) {
                $formatted_message .= ' Context: ' . wp_json_encode($context);
            }
            error_log($formatted_message);
        }
        
        return $result !== false;
    }
    
    /**
     * Log debug message
     */
    public function debug($message, $context = []) {
        return $this->log($message, self::LEVEL_DEBUG, $context);
    }
    
    /**
     * Log info message
     */
    public function info($message, $context = []) {
        return $this->log($message, self::LEVEL_INFO, $context);
    }
    
    /**
     * Log warning message
     */
    public function warning($message, $context = []) {
        return $this->log($message, self::LEVEL_WARNING, $context);
    }
    
    /**
     * Log error message
     */
    public function error($message, $context = []) {
        return $this->log($message, self::LEVEL_ERROR, $context);
    }
    
    /**
     * Log critical message
     */
    public function critical($message, $context = []) {
        return $this->log($message, self::LEVEL_CRITICAL, $context);
    }
    
    /**
     * Check if should log based on level
     */
    private function should_log($level) {
        $levels = [
            self::LEVEL_DEBUG => 0,
            self::LEVEL_INFO => 1,
            self::LEVEL_WARNING => 2,
            self::LEVEL_ERROR => 3,
            self::LEVEL_CRITICAL => 4
        ];
        
        $current_level = $levels[$this->level] ?? 1;
        $message_level = $levels[$level] ?? 1;
        
        return $message_level >= $current_level;
    }
    
    /**
     * Get recent logs
     */
    public function get_recent_logs($limit = 100, $session = null, $level = null) {
        global $wpdb;
        
        $where = [];
        $values = [];
        
        if ($session) {
            $where[] = 'sync_session = %s';
            $values[] = $session;
        }
        
        if ($level) {
            $where[] = 'level = %s';
            $values[] = $level;
        }
        
        $where_clause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        
        $query = $wpdb->prepare(
            "SELECT * FROM {$this->table_name} {$where_clause} ORDER BY timestamp DESC LIMIT %d",
            array_merge($values, [intval($limit)])
        );
        
        return $wpdb->get_results($query, ARRAY_A);
    }
    
    /**
     * Get logs by session
     */
    public function get_session_logs($session, $limit = null) {
        $limit = $limit ?: 1000;
        return $this->get_recent_logs($limit, $session);
    }
    
    /**
     * Get error logs
     */
    public function get_error_logs($limit = 50) {
        global $wpdb;
        
        $query = $wpdb->prepare(
            "SELECT * FROM {$this->table_name} 
             WHERE level IN ('error', 'critical') 
             ORDER BY timestamp DESC 
             LIMIT %d",
            intval($limit)
        );
        
        return $wpdb->get_results($query, ARRAY_A);
    }
    
    /**
     * Get sync sessions
     */
    public function get_sync_sessions($limit = 20) {
        global $wpdb;
        
        $query = $wpdb->prepare(
            "SELECT sync_session, 
                    MIN(timestamp) as start_time,
                    MAX(timestamp) as end_time,
                    COUNT(*) as log_count,
                    SUM(CASE WHEN level IN ('error', 'critical') THEN 1 ELSE 0 END) as error_count
             FROM {$this->table_name} 
             WHERE sync_session IS NOT NULL 
             GROUP BY sync_session 
             ORDER BY start_time DESC 
             LIMIT %d",
            intval($limit)
        );
        
        return $wpdb->get_results($query, ARRAY_A);
    }
    
    /**
     * Clear logs
     */
    public function clear_logs($older_than_days = null, $session = null) {
        global $wpdb;
        
        $where = [];
        $values = [];
        
        if ($older_than_days) {
            $where[] = 'timestamp < %s';
            $values[] = date('Y-m-d H:i:s', strtotime("-{$older_than_days} days"));
        }
        
        if ($session) {
            $where[] = 'sync_session = %s';
            $values[] = $session;
        }
        
        if (empty($where)) {
            $query = "DELETE FROM {$this->table_name}";
            return $wpdb->query($query);
        } else {
            $where_clause = 'WHERE ' . implode(' AND ', $where);
            $query = $wpdb->prepare(
                "DELETE FROM {$this->table_name} {$where_clause}",
                $values
            );
            return $wpdb->query($query);
        }
    }
    
    /**
     * Cleanup old log entries
     */
    private function cleanup_old_entries() {
        global $wpdb;
        
        // Remove entries older than retention period
        $retention_days = get_option('xpse_log_retention_days', 30);
        $this->clear_logs($retention_days);
        
        // Remove excess entries if over limit
        $count = $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name}");
        
        if ($count > $this->max_entries) {
            $excess = $count - $this->max_entries;
            $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$this->table_name} ORDER BY timestamp ASC LIMIT %d",
                    $excess
                )
            );
        }
    }
    
    /**
     * Format statistics for logging
     */
    private function format_stats($stats) {
        $formatted = [];
        
        if (isset($stats['processed'])) {
            $formatted[] = "obrađeno: {$stats['processed']}";
        }
        if (isset($stats['created'])) {
            $formatted[] = "kreirano: {$stats['created']}";
        }
        if (isset($stats['updated'])) {
            $formatted[] = "ažurirano: {$stats['updated']}";
        }
        if (isset($stats['errors'])) {
            $formatted[] = "greške: {$stats['errors']}";
        }
        if (isset($stats['duration'])) {
            $formatted[] = "trajanje: {$stats['duration']}s";
        }
        
        return implode(', ', $formatted);
    }
    
    /**
     * Send email notification
     */
    private function send_email_notification($message, $context = []) {
        $email = get_option('xpse_notification_email', get_option('admin_email'));
        if (!$email) {
            return false;
        }
        
        $subject = sprintf(
            '[%s] XML Product Sync - Kritična greška',
            get_bloginfo('name')
        );
        
        $body = "Kritična greška u XML Product Sync:\n\n";
        $body .= "Poruka: {$message}\n\n";
        
        if (!empty($context)) {
            $body .= "Kontekst:\n";
            $body .= print_r($context, true);
        }
        
        $body .= "\n\nVrijeme: " . current_time('mysql');
        $body .= "\nURL: " . home_url();
        
        return wp_mail($email, $subject, $body);
    }
    
    /**
     * Get current session ID - generate if not exists
     */
    public function get_current_session() {
        if ($this->current_session === null) {
            $this->current_session = $this->generate_session_id();
        }
        return $this->current_session;
    }
    
    /**
     * Export logs
     */
    public function export_logs($session = null, $format = 'csv') {
        $logs = $session ? $this->get_session_logs($session) : $this->get_recent_logs(1000);
        
        if ($format === 'csv') {
            return $this->export_to_csv($logs);
        } elseif ($format === 'json') {
            return wp_json_encode($logs, JSON_PRETTY_PRINT);
        }
        
        return false;
    }
    
    /**
     * Export logs to CSV
     */
    private function export_to_csv($logs) {
        $output = "Timestamp,Level,Message,Context,Session\n";
        
        foreach ($logs as $log) {
            $output .= sprintf(
                "\"%s\",\"%s\",\"%s\",\"%s\",\"%s\"\n",
                $log['timestamp'],
                $log['level'],
                str_replace('"', '""', $log['message']),
                str_replace('"', '""', $log['context']),
                $log['sync_session']
            );
        }
        
        return $output;
    }
}