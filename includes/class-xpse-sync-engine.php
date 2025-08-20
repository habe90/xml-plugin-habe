<?php
/**
 * Sync Engine class for XML Product Sync Enhanced
 *
 * @package XML_Product_Sync_Enhanced
 */

if (!defined('ABSPATH')) {
    exit;
}

class XPSE_Sync_Engine {
    
    private $logger;
    private $settings;
    private $category_manager;
    private $image_processor;
    private $current_session;
    private $sync_stats;
    
    /**
     * Constructor
     */
    public function __construct($logger = null) {
        $this->logger = $logger;
        $this->settings = new XPSE_Settings($logger);
        $this->category_manager = new XPSE_Category_Manager($logger);
        $this->image_processor = new XPSE_Image_Processor($logger);
        $this->sync_stats = $this->init_sync_stats();
    }
    
    /**
     * Initialize sync statistics
     */
    private function init_sync_stats() {
        return [
            'total_items' => 0,
            'processed' => 0,
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => 0,
            'start_time' => 0,
            'end_time' => 0,
            'memory_peak' => 0
        ];
    }
    
    /**
     * Main sync execution
     */
    public function run_sync($manual = false) {
        // Check if already running
        if ($this->is_sync_running() && !$manual) {
            if ($this->logger) {
                $this->logger->warning('Sync je već u toku, preskačem');
            }
            return false;
        }
        
        // Set running flag
        $this->set_sync_status('running');
        
        try {
            // Initialize sync session
            $this->start_sync_session($manual);
            
            // Check and prepare environment
            if (!$this->prepare_environment()) {
                throw new Exception('Neuspešna priprema okruženja za sync');
            }
            
            // Fetch and validate XML
            $xml_data = $this->fetch_xml_data();
            if (!$xml_data) {
                throw new Exception('Neuspešno preuzimanje XML podataka');
            }
            
            // Process data in batches
            $this->process_xml_data($xml_data);
            
            // Complete sync
            $this->complete_sync();
            
            return true;
            
        } catch (Exception $e) {
            $this->handle_sync_error($e);
            return false;
        } finally {
            $this->cleanup_sync();
        }
    }
    
    /**
     * Start sync session
     */
    private function start_sync_session($manual = false) {
        $this->current_session = $this->logger ? $this->logger->start_session() : uniqid('sync_');
        $this->sync_stats['start_time'] = microtime(true);
        
        // Clear previous progress data
        delete_transient('xpse_sync_progress');
        delete_transient('xpse_batch_offset');
        
        if ($this->logger) {
            $this->logger->info('Sync pokrećen', [
                'manual' => $manual,
                'session' => $this->current_session,
                'xml_url' => $this->settings->get('xml_url')
            ]);
        }
    }
    
    /**
     * Prepare environment for sync
     */
    private function prepare_environment() {
        // Increase memory and time limits
        $memory_limit = $this->settings->get('memory_limit_mb', 512);
        $time_limit = $this->settings->get('max_execution_time', 300);
        
        XPSE_Utilities::increase_memory_limit($memory_limit);
        XPSE_Utilities::increase_time_limit($time_limit);
        
        // Check memory availability
        if (!XPSE_Utilities::check_memory_limit(256)) {
            if ($this->logger) {
                $this->logger->warning('Nedovoljan memory limit za sync operaciju');
            }
        }
        
        // Cleanup temporary files
        if ($this->settings->get('cleanup_temp_files', true)) {
            XPSE_Utilities::cleanup_temp_files();
        }
        
        return true;
    }
    
    /**
     * Fetch XML data from URL
     */
    private function fetch_xml_data() {
        $xml_url = $this->settings->get('xml_url');
        
        if (empty($xml_url)) {
            throw new Exception('XML URL nije konfigurisan');
        }
        
        if ($this->logger) {
            $this->logger->info('Preuzimam XML podatke', ['url' => $xml_url]);
        }
        
        $response = wp_remote_get($xml_url, [
            'timeout' => 60,
            'user-agent' => 'XML Product Sync Enhanced/' . XPSE_VERSION
        ]);
        
        if (is_wp_error($response)) {
            throw new Exception('Greška pri preuzimanju XML: ' . $response->get_error_message());
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            throw new Exception('HTTP greška: ' . $response_code);
        }
        
        $body = wp_remote_retrieve_body($response);
        if (empty($body)) {
            throw new Exception('Prazan XML odgovor');
        }
        
        // Validate XML
        $validation = XPSE_Utilities::validate_xml($body);
        if (!$validation['valid']) {
            throw new Exception('Nevažeći XML: ' . $validation['error']);
        }
        
        if ($this->logger) {
            $this->logger->info('XML uspešno preuzet i validiran', [
                'size' => XPSE_Utilities::format_file_size(strlen($body))
            ]);
        }
        
        return $validation['xml'];
    }
    
    /**
     * Process XML data in batches
     */
    private function process_xml_data($xml) {
        // Extract all items
        $all_items = [];
        foreach ($xml->children() as $item) {
            $all_items[] = $item;
        }
        
        $this->sync_stats['total_items'] = count($all_items);
        
        if ($this->logger) {
            $this->logger->info('Broj proizvoda u XML-u: ' . $this->sync_stats['total_items']);
        }
        
        // Get batch configuration
        $batch_size = $this->settings->get('batch_size', 100);
        $batch_delay = $this->settings->get('batch_delay', 15);
        
        // Get current offset
        $offset = (int) get_transient('xpse_batch_offset');
        
        // Process current batch
        $items = array_slice($all_items, $offset, $batch_size);
        
        if ($this->logger) {
            $this->logger->info('Obrađujem batch', [
                'offset' => $offset,
                'batch_size' => count($items),
                'total_items' => $this->sync_stats['total_items']
            ]);
        }
        
        foreach ($items as $index => $item) {
            $this->process_single_item($item, $offset + $index + 1);
            
            // Update progress
            $this->update_progress();
        }
        
        // Schedule next batch if needed
        $new_offset = $offset + $batch_size;
        if ($new_offset < $this->sync_stats['total_items']) {
            set_transient('xpse_batch_offset', $new_offset, 3600);
            
            if ($this->logger) {
                $this->logger->info('Slanjem sljedeći batch', [
                    'next_offset' => $new_offset,
                    'delay' => $batch_delay
                ]);
            }
            
            // Schedule next batch
            wp_schedule_single_event(time() + $batch_delay, 'xpse_sync_event');
        } else {
            // All batches completed
            delete_transient('xpse_batch_offset');
            $this->complete_sync();
        }
    }
    
    /**
     * Process single product item
     */
    private function process_single_item($item, $item_number) {
        try {
            $sku = trim((string) $item->Sifra);
            
            if (empty($sku)) {
                $this->sync_stats['skipped']++;
                if ($this->logger) {
                    $this->logger->warning('Prazan SKU, preskačem item', ['item_number' => $item_number]);
                }
                return;
            }
            
            // Sanitize SKU first to match what will be stored
            $sanitized_sku = XPSE_Utilities::sanitize_text($sku);
            
            if ($this->logger) {
                $this->logger->debug('Obrađujem proizvod', [
                    'item_number' => $item_number,
                    'original_sku' => $sku,
                    'sanitized_sku' => $sanitized_sku
                ]);
            }
            
            // Check if test mode
            if ($this->settings->get('test_mode', false)) {
                $this->simulate_product_processing($item);
                return;
            }
            
            // Check if product exists by original and sanitized SKU
            $existing_product_id = $this->find_existing_product($sku, $sanitized_sku, $item);
            
            if ($existing_product_id) {
                // Check if product actually needs updating
                if ($this->product_needs_update($existing_product_id, $item)) {
                    $this->update_existing_product($existing_product_id, $item);
                } else {
                    $this->sync_stats['skipped']++;
                    if ($this->logger) {
                        $this->logger->debug('Proizvod nije promijenjen, preskačem', [
                            'product_id' => $existing_product_id,
                            'sku' => $sanitized_sku
                        ]);
                    }
                }
            } else {
                $this->create_new_product($item);
            }
            
            $this->sync_stats['processed']++;
            
        } catch (Exception $e) {
            $this->sync_stats['errors']++;
            
            if ($this->logger) {
                $this->logger->error('Greška pri obradi proizvoda', [
                    'item_number' => $item_number,
                    'sku' => $sku ?? 'N/A',
                    'error' => $e->getMessage()
                ]);
            }
        }
    }
    
    /**
     * Find existing product by SKU
     */
    private function find_existing_product($original_sku, $sanitized_sku, $item = null) {
        // First try with original SKU
        $product_id = wc_get_product_id_by_sku($original_sku);
        
        if ($product_id) {
            if ($this->logger) {
                $this->logger->debug('Proizvod pronađen po originalnom SKU', [
                    'sku' => $original_sku,
                    'product_id' => $product_id
                ]);
            }
            return $product_id;
        }
        
        // Try with sanitized SKU if different
        if ($original_sku !== $sanitized_sku) {
            $product_id = wc_get_product_id_by_sku($sanitized_sku);
            
            if ($product_id) {
                if ($this->logger) {
                    $this->logger->debug('Proizvod pronađen po sanitized SKU', [
                        'original_sku' => $original_sku,
                        'sanitized_sku' => $sanitized_sku,
                        'product_id' => $product_id
                    ]);
                }
                return $product_id;
            }
        }
        
        // Try by EAN if available
        if ($item) {
            $ean = trim((string) $item->EAN ?? '');
            if (!empty($ean)) {
                global $wpdb;
                $product_id = $wpdb->get_var($wpdb->prepare(
                    "SELECT post_id FROM {$wpdb->postmeta} 
                     WHERE meta_key = '_ean' AND meta_value = %s 
                     LIMIT 1",
                    $ean
                ));
                
                if ($product_id) {
                    if ($this->logger) {
                        $this->logger->debug('Proizvod pronađen po EAN-u', [
                            'ean' => $ean,
                            'product_id' => $product_id
                        ]);
                    }
                    return (int) $product_id;
                }
            }
        }
        
        if ($this->logger) {
            $this->logger->debug('Proizvod nije pronađen', [
                'original_sku' => $original_sku,
                'sanitized_sku' => $sanitized_sku
            ]);
        }
        
        return false;
    }
    // The misplaced line is removed here. If this code was meant to be part of a method (e.g., create_new_product), ensure it is inside that method.
    
    /**
     * Update existing product
     */
    private function update_existing_product($product_id, $item) {
        // Check if should update
        if (!$this->settings->get('auto_update_existing', true)) {
            $this->sync_stats['skipped']++;
            return;
        }
        
        // Create backup if enabled
        if ($this->settings->get('enable_backup', true)) {
            XPSE_Utilities::backup_product($product_id);
        }
        
        $product_data = $this->extract_product_data($item);
        
        // Update post
        $post_data = [
            'ID' => $product_id,
            'post_title' => $product_data['name'],
            'post_content' => $product_data['description'],
            'post_excerpt' => $product_data['short_description']
        ];
        
        wp_update_post($post_data);
        
        // Set product data
        $this->set_product_data($product_id, $product_data, $item, true);
        
        $this->sync_stats['updated']++;
        
        if ($this->logger) {
            $this->logger->debug('Proizvod ažuriran', [
                'product_id' => $product_id,
                'sku' => $product_data['sku'],
                'name' => $product_data['name']
            ]);
        }
    }
    
    /**
     * Extract product data from XML item
     */
    private function extract_product_data($item) {
        return [
            'sku' => XPSE_Utilities::sanitize_text((string) $item->Sifra),
            'name' => XPSE_Utilities::sanitize_text((string) $item->Naziv),
            'description' => XPSE_Utilities::sanitize_html((string) $item->Opis),
            'short_description' => $this->extract_short_description($item),
            'regular_price' => $this->calculate_price($item),
            'stock_quantity' => (int) $item->Kolicina,
            'stock_status' => ((int) $item->Kolicina > 0) ? 'instock' : 'outofstock',
            'weight' => XPSE_Utilities::parse_dimension((string) $item->{'Package-weight'}),
            'width' => XPSE_Utilities::parse_dimension((string) $item->Width),
            'height' => XPSE_Utilities::parse_dimension((string) $item->Height),
            'length' => XPSE_Utilities::parse_dimension((string) $item->Length),
            'ean' => XPSE_Utilities::sanitize_text((string) $item->EAN),
            'variant_sku' => XPSE_Utilities::sanitize_text((string) $item->{'Varijant-sifra'}),
            'variant_definition' => XPSE_Utilities::sanitize_text((string) $item->{'Varijant-definicija'})
        ];
    }
    
    /**
     * Extract short description from specifications
     */
    private function extract_short_description($item) {
        $specifications = (string) $item->Specifikacija;
        
        if (empty($specifications)) {
            return '';
        }
        
        // Parse specifications (format: key:value§key:value)
        $specs = explode('§', $specifications);
        $short_desc = [];
        
        foreach ($specs as $spec) {
            if (strpos($spec, ':') !== false) {
                list($key, $value) = explode(':', $spec, 2);
                $short_desc[] = trim($key) . ': ' . trim($value);
            }
        }
        
        return implode(', ', array_slice($short_desc, 0, 3)); // Limit to first 3 specs
    }
    
    /**
     * Calculate product price
     */
    private function calculate_price($item) {
        $osnovna = XPSE_Utilities::parse_price((string) $item->{'Osnovna-cijena'});
        $preporucena = XPSE_Utilities::parse_price((string) $item->{'Preporucena-cijena'});
        
        return $preporucena > 0 ? $preporucena : $osnovna;
    }
    
    /**
     * Set product data
     */
    private function set_product_data($product_id, $product_data, $item, $is_update = false) {
        // Set product type
        wp_set_object_terms($product_id, 'simple', 'product_type');
        
        // Set basic meta
        update_post_meta($product_id, '_sku', $product_data['sku']);
        update_post_meta($product_id, '_regular_price', $product_data['regular_price']);
        update_post_meta($product_id, '_price', $product_data['regular_price']);
        update_post_meta($product_id, '_stock', $product_data['stock_quantity']);
        update_post_meta($product_id, '_stock_status', $product_data['stock_status']);
        update_post_meta($product_id, '_manage_stock', 'yes');
        
        // Set dimensions
        update_post_meta($product_id, '_weight', $product_data['weight']);
        update_post_meta($product_id, '_width', $product_data['width']);
        update_post_meta($product_id, '_height', $product_data['height']);
        update_post_meta($product_id, '_length', $product_data['length']);
        
        // Set custom meta
        update_post_meta($product_id, '_ean', $product_data['ean']);
        update_post_meta($product_id, '_xpse_last_sync', current_time('mysql'));
        update_post_meta($product_id, '_xpse_session', $this->current_session);
        
        // Store data hash for future change detection
        $data_hash = $this->create_product_hash($item);
        update_post_meta($product_id, '_xpse_data_hash', $data_hash);
        
        // Handle variants
        if ($this->settings->get('handle_variants', true)) {
            $this->handle_product_variants($product_id, $product_data, $item);
        }
        
        // Set categories
        $category_ids = $this->category_manager->process_product_categories($item);
        if (!empty($category_ids)) {
            wp_set_object_terms($product_id, $category_ids, 'product_cat');
        }
        
        // Process images
        if (!$is_update || !$this->settings->get('skip_images_update', false)) {
            $this->image_processor->process_product_images($item, $product_id);
        }
    }
    
    /**
     * Handle product variants
     */
    private function handle_product_variants($product_id, $product_data, $item) {
        $sku = $product_data['sku'];
        $variant_sku = $product_data['variant_sku'];
        
        if ($sku !== $variant_sku && !empty($variant_sku)) {
            // This is a variant - store variant info
            update_post_meta($product_id, '_xpse_parent_sku', $variant_sku);
            update_post_meta($product_id, '_xpse_variant_definition', $product_data['variant_definition']);
            
            if ($this->logger) {
                $this->logger->debug('Variant proizvod identificiran', [
                    'product_id' => $product_id,
                    'sku' => $sku,
                    'parent_sku' => $variant_sku,
                    'definition' => $product_data['variant_definition']
                ]);
            }
        }
    }

    /**
 * Create new product
 */
private function create_new_product($item) {
    // Ekstrahuj podatke iz XML item-a
    $product_data = $this->extract_product_data($item);

    // Kreiraj novi WP post (WooCommerce product)
    $post_id = wp_insert_post([
        'post_title'   => $product_data['name'],
        'post_content' => $product_data['description'],
        'post_excerpt' => $product_data['short_description'],
        'post_status'  => 'publish',
        'post_type'    => 'product'
    ]);

    if (is_wp_error($post_id)) {
        throw new Exception('Neuspješno kreiranje proizvoda: ' . $post_id->get_error_message());
    }

    // Set product data
    $this->set_product_data($post_id, $product_data, $item);

    $this->sync_stats['created']++;

    if ($this->logger) {
        $this->logger->info('Novi proizvod kreiran', [
            'product_id' => $post_id,
            'sku'        => $product_data['sku'],
            'name'       => $product_data['name']
        ]);
    }

    return $post_id;
}

    
    /**
     * Simulate product processing for test mode
     */
    private function simulate_product_processing($item) {
        $sku = trim((string) $item->Sifra);
        $name = trim((string) $item->Naziv);
        
        if ($this->logger) {
            $this->logger->debug('TEST MODE: Simuliram obradu proizvoda', [
                'sku' => $sku,
                'name' => $name
            ]);
        }
        
        $this->sync_stats['processed']++;
        
        // Simulate random outcomes
        $random = rand(1, 100);
        if ($random <= 60) {
            $this->sync_stats['updated']++;
        } elseif ($random <= 90) {
            $this->sync_stats['created']++;
        } else {
            $this->sync_stats['errors']++;
        }
    }
    
    /**
     * Update sync progress
     */
    private function update_progress() {
        if (!$this->settings->get('enable_progress_tracking', true)) {
            return;
        }
        
        $progress_data = [
            'session' => $this->current_session,
            'stats' => $this->sync_stats,
            'memory_usage' => XPSE_Utilities::get_memory_usage(),
            'timestamp' => time()
        ];
        
        set_transient('xpse_sync_progress', $progress_data, 3600);
    }
    
    /**
     * Complete sync process
     */
    private function complete_sync() {
        $this->sync_stats['end_time'] = microtime(true);
        $duration = $this->sync_stats['end_time'] - $this->sync_stats['start_time'];
        $this->sync_stats['memory_peak'] = memory_get_peak_usage(true);
        
        if ($this->logger) {
            $this->logger->end_session($this->sync_stats);
        }
        
        // Send notifications
        $this->send_completion_notification();
        
        // Call webhook if enabled
        $this->call_webhook('completed', $this->sync_stats);
        
        // Clear running status
        $this->set_sync_status('completed');
        
        // Store final stats
        set_transient('xpse_sync_stats', $this->sync_stats, DAY_IN_SECONDS);
    }
    
    /**
     * Handle sync error
     */
    private function handle_sync_error($exception) {
        $this->sync_stats['end_time'] = microtime(true);
        
        if ($this->logger) {
            $this->logger->critical('Sync neuspešan', [
                'error' => $exception->getMessage(),
                'stats' => $this->sync_stats
            ]);
        }
        
        // Send error notification
        $this->send_error_notification($exception);
        
        // Call webhook
        $this->call_webhook('failed', [
            'error' => $exception->getMessage(),
            'stats' => $this->sync_stats
        ]);
        
        // Set error status
        $this->set_sync_status('failed');
    }
    
    /**
     * Cleanup after sync
     */
    private function cleanup_sync() {
        // Clear caches
        $this->category_manager->clear_cache();
        $this->image_processor->clear_cache();
        
        // Clean up transients if sync completed
        $status = get_transient('xpse_sync_status');
        if (in_array($status, ['completed', 'failed'])) {
            delete_transient('xpse_sync_progress');
            delete_transient('xpse_batch_offset');
        }
    }
    
    /**
     * Send completion notification
     */
    private function send_completion_notification() {
        if (!$this->settings->get('enable_email_notifications', false) || 
            !$this->settings->get('notify_on_completion', false)) {
            return;
        }
        
        $email = $this->settings->get('notification_email');
        if (!$email) {
            return;
        }
        
        $duration = $this->sync_stats['end_time'] - $this->sync_stats['start_time'];
        
        $subject = sprintf('[%s] XML Product Sync - Uspešno završen', get_bloginfo('name'));
        
        $body = "XML Product Sync je uspešno završen.\n\n";
        $body .= "Statistike:\n";
        $body .= "- Ukupno stavki: {$this->sync_stats['total_items']}\n";
        $body .= "- Obrađeno: {$this->sync_stats['processed']}\n";
        $body .= "- Kreirano: {$this->sync_stats['created']}\n";
        $body .= "- Ažurirano: {$this->sync_stats['updated']}\n";
        $body .= "- Preskočeno: {$this->sync_stats['skipped']}\n";
        $body .= "- Greške: {$this->sync_stats['errors']}\n";
        $body .= "- Trajanje: " . XPSE_Utilities::format_duration($duration) . "\n";
        $body .= "- Memory peak: " . XPSE_Utilities::format_file_size($this->sync_stats['memory_peak']) . "\n";
        $body .= "\nVrijeme: " . current_time('mysql');
        
        wp_mail($email, $subject, $body);
    }
    
    /**
     * Send error notification
     */
    private function send_error_notification($exception) {
        if (!$this->settings->get('enable_email_notifications', false) || 
            !$this->settings->get('notify_on_errors', true)) {
            return;
        }
        
        $email = $this->settings->get('notification_email');
        if (!$email) {
            return;
        }
        
        $subject = sprintf('[%s] XML Product Sync - Greška', get_bloginfo('name'));
        
        $body = "Došlo je do greške tokom XML Product Sync operacije.\n\n";
        $body .= "Greška: {$exception->getMessage()}\n\n";
        $body .= "Statistike do greške:\n";
        $body .= "- Obrađeno: {$this->sync_stats['processed']}\n";
        $body .= "- Kreirano: {$this->sync_stats['created']}\n";
        $body .= "- Ažurirano: {$this->sync_stats['updated']}\n";
        $body .= "- Greške: {$this->sync_stats['errors']}\n";
        $body .= "\nVrijeme: " . current_time('mysql');
        
        wp_mail($email, $subject, $body);
    }
    
    /**
     * Call webhook
     */
    private function call_webhook($event, $data) {
        if (!$this->settings->get('enable_webhooks', false)) {
            return;
        }
        
        $webhook_url = $this->settings->get('webhook_url');
        if (empty($webhook_url)) {
            return;
        }
        
        $payload = [
            'event' => $event,
            'timestamp' => current_time('mysql'),
            'site_url' => home_url(),
            'session' => $this->current_session,
            'data' => $data
        ];
        
        wp_remote_post($webhook_url, [
            'timeout' => 15,
            'headers' => [
                'Content-Type' => 'application/json',
                'User-Agent' => 'XML Product Sync Enhanced/' . XPSE_VERSION
            ],
            'body' => wp_json_encode($payload)
        ]);
    }
    
    /**
     * Check if sync is currently running
     */
    public function is_sync_running() {
        $status = get_transient('xpse_sync_status');
        return $status === 'running';
    }
    
    /**
     * Set sync status
     */
    private function set_sync_status($status) {
        set_transient('xpse_sync_status', $status, HOUR_IN_SECONDS);
    }
    
    /**
     * Get sync status
     */
    public function get_sync_status() {
        return get_transient('xpse_sync_status') ?: 'idle';
    }
    
    /**
     * Get sync progress
     */
    public function get_sync_progress() {
        return get_transient('xpse_sync_progress') ?: [];
    }
    
    /**
     * Cancel running sync
     */
    public function cancel_sync() {
        if ($this->is_sync_running()) {
            wp_clear_scheduled_hook('xpse_sync_event');
            delete_transient('xpse_sync_status');
            delete_transient('xpse_sync_progress');
            delete_transient('xpse_batch_offset');
            
            if ($this->logger) {
                $this->logger->warning('Sync otkazan od strane korisnika');
            }
            
            return true;
        }
        
        return false;
    }
    
    /**
     * Check if product needs updating
     */
    private function product_needs_update($product_id, $item) {
        // If force update is enabled, always update
        if ($this->settings->get('force_update_all', false)) {
            return true;
        }
        
        // Create hash of important XML data
        $xml_hash = $this->create_product_hash($item);
        
        // Get stored hash
        $stored_hash = get_post_meta($product_id, '_xpse_data_hash', true);
        
        // Compare hashes
        $needs_update = ($xml_hash !== $stored_hash);
        
        if ($this->logger) {
            $this->logger->debug('Provjera potrebe za ažuriranje', [
                'product_id' => $product_id,
                'xml_hash' => $xml_hash,
                'stored_hash' => $stored_hash,
                'needs_update' => $needs_update
            ]);
        }
        
        return $needs_update;
    }
    
    /**
     * Create hash from product data
     */
    private function create_product_hash($item) {
        $hash_data = [
            'naziv' => trim((string) $item->Naziv),
            'opis' => trim((string) $item->Opis),
            'osnovna_cijena' => trim((string) $item->{'Osnovna-cijena'}),
            'preporucena_cijena' => trim((string) $item->{'Preporucena-cijena'}),
            'kolicina' => trim((string) $item->Kolicina),
            'specifikacija' => trim((string) $item->Specifikacija),
            'weight' => trim((string) $item->{'Package-weight'}),
            'dimensions' => trim((string) $item->Width) . 'x' . trim((string) $item->Height) . 'x' . trim((string) $item->Length),
            'kategorija1' => trim((string) $item->Kategorija1),
            'kategorija2' => trim((string) $item->Kategorija2),
            'kategorija3' => trim((string) $item->Kategorija3),
            'kategorija4' => trim((string) $item->Kategorija4),
            'kategorija5' => trim((string) $item->Kategorija5)
        ];
        
        // Add images if image update is enabled
        if (!$this->settings->get('skip_images_update', false)) {
            for ($i = 1; $i <= 10; $i++) {
                $hash_data['slika' . $i] = trim((string) $item->{'Slika' . $i});
            }
        }
        
        return md5(json_encode($hash_data));
    }
    
    /**
     * AJAX handler for manual sync
     */
    public function ajax_manual_sync() {
        check_ajax_referer('xpse_admin', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Nemate dozvole za ovu akciju.');
        }
        
        $result = $this->run_sync(true);
        
        wp_send_json([
            'success' => $result,
            'message' => $result ? 'Sync pokrećen uspešno' : 'Greška pri pokretanju sync-a'
        ]);
    }
    
    /**
     * AJAX handler for sync status
     */
    public function ajax_get_sync_status() {
        check_ajax_referer('xpse_admin', 'nonce');
        
        $status = $this->get_sync_status();
        $progress = $this->get_sync_progress();
        
        wp_send_json([
            'status' => $status,
            'progress' => $progress
        ]);
    }
    
    /**
     * AJAX handler for cancel sync
     */
    public function ajax_cancel_sync() {
        check_ajax_referer('xpse_admin', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Nemate dozvole za ovu akciju.');
        }
        
        $result = $this->cancel_sync();
        
        wp_send_json([
            'success' => $result,
            'message' => $result ? 'Sync otkazan' : 'Sync nije bio aktivan'
        ]);
    }
}