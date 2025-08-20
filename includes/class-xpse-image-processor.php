<?php
/**
 * Image Processor class for XML Product Sync Enhanced
 *
 * @package XML_Product_Sync_Enhanced
 */

if (!defined('ABSPATH')) {
    exit;
}

class XPSE_Image_Processor {
    
    private $logger;
    private $max_file_size;
    private $allowed_types;
    private $download_timeout;
    private $image_cache = [];
    
    /**
     * Constructor
     */
    public function __construct($logger = null) {
        $this->logger = $logger;
        $this->max_file_size = apply_filters('xpse_max_image_size', 10 * 1024 * 1024); // 10MB
        $this->allowed_types = apply_filters('xpse_allowed_image_types', [
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp'
        ]);
        $this->download_timeout = get_option('xpse_image_download_timeout', 30);
        
        // Ensure required WordPress functions are available
        $this->ensure_required_functions();
    }
    
    /**
     * Ensure required WordPress functions are loaded
     */
    private function ensure_required_functions() {
        if (!function_exists('wp_handle_sideload')) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
        }
        
        if (!function_exists('wp_get_image_editor')) {
            require_once(ABSPATH . 'wp-admin/includes/image.php');
        }
        
        if (!function_exists('media_handle_sideload')) {
            require_once(ABSPATH . 'wp-admin/includes/media.php');
        }
    }
    
    /**
     * Process all images for a product
     */
 public function process_product_images($item, $product_id) {
    $gallery_ids = [];
    $processed_urls = [];
    $skip_update = get_option('xpse_skip_images_update', false);

    // Ako proizvod već ima slike i opcija skip_update je uključena → preskoči
    if ($skip_update && $this->product_has_images($product_id)) {
        if ($this->logger) {
            $this->logger->debug("Preskačem ažuriranje slika za postojeći proizvod", [
                'product_id' => $product_id
            ]);
        }
        return [];
    }

    // Featured slika = uvijek Slika1 ako postoji
    $featured_url = trim((string) $item->{'Slika1'});
    if (!empty($featured_url)) {
        $featured_id = $this->process_single_image($featured_url, $product_id, 1);
        if ($featured_id) {
            set_post_thumbnail($product_id, $featured_id);
            $processed_urls[] = $featured_url;
            $gallery_ids[] = $featured_id;
        }
    }

    // Ostale Slika2–Slika10 idu u galeriju
    for ($i = 2; $i <= 10; $i++) {
        $image_url = trim((string) $item->{'Slika' . $i});
        if (empty($image_url)) {
            continue;
        }

        if (in_array($image_url, $processed_urls)) {
            continue;
        }

        $attach_id = $this->process_single_image($image_url, $product_id, $i);
        if ($attach_id) {
            $gallery_ids[] = $attach_id;
            $processed_urls[] = $image_url;
        }
    }

    // Upis u galeriju
    if (count($gallery_ids) > 1) {
        update_post_meta($product_id, '_product_image_gallery', implode(',', array_slice($gallery_ids, 1)));
    } else {
        delete_post_meta($product_id, '_product_image_gallery');
    }

    return $gallery_ids;
}

    
    /**
     * Process single image
     */
    private function process_single_image($image_url, $product_id, $position) {
        if (!XPSE_Utilities::is_valid_image_url($image_url)) {
            if ($this->logger) {
                $this->logger->warning("Nevažeći URL slike", [
                    'url' => $image_url,
                    'position' => $position
                ]);
            }
            return false;
        }
        
        // Check if image already exists in cache
        $cache_key = md5($image_url);
        if (isset($this->image_cache[$cache_key])) {
            return $this->image_cache[$cache_key];
        }
        
        // Check if image already exists in media library
        $existing_attachment = $this->find_existing_image($image_url);
        if ($existing_attachment) {
            $this->image_cache[$cache_key] = $existing_attachment;
            return $existing_attachment;
        }
        
        // Download and process new image
        $attach_id = $this->download_and_attach_image($image_url, $product_id, $position);
        
        if ($attach_id) {
            $this->image_cache[$cache_key] = $attach_id;
        }
        
        return $attach_id;
    }
    
    /**
     * Find existing image in media library
     */
    private function find_existing_image($image_url) {
        global $wpdb;
        
        // Try to find by original URL
        $attachment_id = $wpdb->get_var($wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta} 
             WHERE meta_key = '_source_url' AND meta_value = %s 
             LIMIT 1",
            $image_url
        ));
        
        if ($attachment_id) {
            return (int) $attachment_id;
        }
        
        // Try to find by filename
        $filename = basename(parse_url($image_url, PHP_URL_PATH));
        if (!empty($filename)) {
            $attachment_id = $wpdb->get_var($wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts} 
                 WHERE post_type = 'attachment' 
                 AND post_title = %s 
                 LIMIT 1",
                pathinfo($filename, PATHINFO_FILENAME)
            ));
            
            if ($attachment_id) {
                return (int) $attachment_id;
            }
        }
        
        return false;
    }
    
    /**
     * Download and attach image to WordPress media library
     */
    private function download_and_attach_image($image_url, $product_id, $position) {
        try {
            // Download the image
            $temp_file = $this->download_image($image_url);
            
            if (!$temp_file) {
                return false;
            }
            
            // Validate downloaded file
            if (!$this->validate_image_file($temp_file)) {
                $this->cleanup_temp_file($temp_file);
                return false;
            }
            
            // Prepare file for WordPress
            $file_array = $this->prepare_file_array($temp_file, $image_url);
            
            // Create attachment
            $attach_id = media_handle_sideload($file_array, $product_id);
            
            if (is_wp_error($attach_id)) {
                if ($this->logger) {
                    $this->logger->error("Greška pri kreiranju attachment-a", [
                        'url' => $image_url,
                        'error' => $attach_id->get_error_message()
                    ]);
                }
                return false;
            }
            
            // Store source URL as meta
            update_post_meta($attach_id, '_source_url', $image_url);
            update_post_meta($attach_id, '_xpse_position', $position);
            update_post_meta($attach_id, '_xpse_imported', current_time('mysql'));
            
            // Generate alt text
            $this->set_image_alt_text($attach_id, $product_id, $position);
            
            if ($this->logger) {
                $this->logger->debug("Slika uspešno preuzeta i dodana", [
                    'url' => $image_url,
                    'attachment_id' => $attach_id,
                    'position' => $position
                ]);
            }
            
            return $attach_id;
            
        } catch (Exception $e) {
            if ($this->logger) {
                $this->logger->error("Exception pri preuzimanju slike", [
                    'url' => $image_url,
                    'error' => $e->getMessage()
                ]);
            }
            return false;
        }
    }
    
    /**
     * Download image from URL
     */
private function download_image($image_url) {
    // download_url već radi encoding + timeout handling
    $tmp = download_url($image_url, $this->download_timeout);

    if (is_wp_error($tmp)) {
        if ($this->logger) {
            $this->logger->warning("Greška pri preuzimanju slike", [
                'url' => $image_url,
                'error' => $tmp->get_error_message()
            ]);
        }
        return false;
    }

    // Provjera veličine fajla
    if (filesize($tmp) > $this->max_file_size) {
        if ($this->logger) {
            $this->logger->warning("Slika je prevelika", [
                'url' => $image_url,
                'size' => XPSE_Utilities::format_file_size(filesize($tmp)),
                'max_size' => XPSE_Utilities::format_file_size($this->max_file_size)
            ]);
        }
        @unlink($tmp);
        return false;
    }

    return $tmp;
}

    
    /**
     * Validate image file
     */
    private function validate_image_file($file_path) {
        if (!file_exists($file_path)) {
            return false;
        }
        
        // Check MIME type
        $mime_type = wp_check_filetype($file_path)['type'];
        if (!in_array($mime_type, $this->allowed_types)) {
            if ($this->logger) {
                $this->logger->warning("Nepodržan tip slike", [
                    'file' => $file_path,
                    'mime_type' => $mime_type
                ]);
            }
            return false;
        }
        
        // Try to get image dimensions
        $image_info = getimagesize($file_path);
        if ($image_info === false) {
            if ($this->logger) {
                $this->logger->warning("Nevažeća slika ili koruptirana datoteka", [
                    'file' => $file_path
                ]);
            }
            return false;
        }
        
        // Check minimum dimensions
        $min_width = apply_filters('xpse_min_image_width', 50);
        $min_height = apply_filters('xpse_min_image_height', 50);
        
        if ($image_info[0] < $min_width || $image_info[1] < $min_height) {
            if ($this->logger) {
                $this->logger->warning("Slika je premala", [
                    'file' => $file_path,
                    'dimensions' => $image_info[0] . 'x' . $image_info[1],
                    'min_required' => $min_width . 'x' . $min_height
                ]);
            }
            return false;
        }
        
        return true;
    }
    
    /**
     * Prepare file array for WordPress
     */
    private function prepare_file_array($temp_file, $original_url) {
        $filename = $this->generate_filename($original_url);
        
        return [
            'name' => $filename,
            'tmp_name' => $temp_file,
            'size' => filesize($temp_file),
            'type' => wp_check_filetype($temp_file)['type'],
            'error' => 0
        ];
    }
    
    /**
     * Generate unique filename
     */
    private function generate_filename($url) {
        $original_filename = basename(parse_url($url, PHP_URL_PATH));
        
        if (empty($original_filename) || $original_filename === '/') {
            $original_filename = 'product-image-' . time() . '.jpg';
        }
        
        // Sanitize filename
        $filename = sanitize_file_name($original_filename);
        
        // Ensure file extension
        $file_info = pathinfo($filename);
        if (empty($file_info['extension'])) {
            $filename .= '.jpg';
        }
        
        return $filename;
    }
    
    /**
     * Set image alt text
     */
    private function set_image_alt_text($attachment_id, $product_id, $position) {
        $product = wc_get_product($product_id);
        
        if ($product) {
            $alt_text = $product->get_name();
            
            if ($position > 1) {
                $alt_text .= ' - Slika ' . $position;
            }
            
            update_post_meta($attachment_id, '_wp_attachment_image_alt', $alt_text);
        }
    }
    
    /**
     * Set product featured image and gallery
     */
    private function set_product_images($product_id, $gallery_ids) {
        if (empty($gallery_ids)) {
            return;
        }
        
        // Set first image as featured
        $featured_image_id = $gallery_ids[0];
        set_post_thumbnail($product_id, $featured_image_id);
        
        // Set remaining images as gallery
        if (count($gallery_ids) > 1) {
            $gallery_ids_remaining = array_slice($gallery_ids, 1);
            update_post_meta($product_id, '_product_image_gallery', implode(',', $gallery_ids_remaining));
        } else {
            // Clear gallery if only one image
            delete_post_meta($product_id, '_product_image_gallery');
        }
        
        if ($this->logger) {
            $this->logger->debug("Slike dodijeljene proizvodu", [
                'product_id' => $product_id,
                'featured_image' => $featured_image_id,
                'gallery_count' => count($gallery_ids) - 1
            ]);
        }
    }
    
    /**
     * Check if product already has images
     */
    private function product_has_images($product_id) {
        $featured_image = get_post_thumbnail_id($product_id);
        $gallery = get_post_meta($product_id, '_product_image_gallery', true);
        
        return !empty($featured_image) || !empty($gallery);
    }
    
    /**
     * Clean up temporary file
     */
    private function cleanup_temp_file($file_path) {
        if (file_exists($file_path)) {
            unlink($file_path);
        }
    }
    
    /**
     * Clean up orphaned images
     */
    public function cleanup_orphaned_images($dry_run = true) {
        global $wpdb;
        
        // Find attachments imported by XPSE that are not attached to any product
        $orphaned_attachments = $wpdb->get_results(
            "SELECT p.ID, p.post_title, pm.meta_value as source_url
             FROM {$wpdb->posts} p
             LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_source_url'
             LEFT JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = '_xpse_imported'
             WHERE p.post_type = 'attachment'
             AND pm2.meta_value IS NOT NULL
             AND p.post_parent = 0"
        );
        
        $deleted = [];
        
        foreach ($orphaned_attachments as $attachment) {
            if (!$dry_run) {
                $result = wp_delete_attachment($attachment->ID, true);
                
                if ($result) {
                    $deleted[] = [
                        'id' => $attachment->ID,
                        'title' => $attachment->post_title,
                        'url' => $attachment->source_url
                    ];
                }
            } else {
                $deleted[] = [
                    'id' => $attachment->ID,
                    'title' => $attachment->post_title,
                    'url' => $attachment->source_url
                ];
            }
        }
        
        if ($this->logger && !$dry_run) {
            $this->logger->info("Obrisano " . count($deleted) . " orphaned slika", [
                'deleted_images' => $deleted
            ]);
        }
        
        return $deleted;
    }
    
    /**
     * Get image processing statistics
     */
    public function get_image_stats() {
        global $wpdb;
        
        $total_imported = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->postmeta} 
             WHERE meta_key = '_xpse_imported'"
        );
        
        $total_size = $wpdb->get_var(
            "SELECT SUM(pm.meta_value) FROM {$wpdb->postmeta} pm
             JOIN {$wpdb->postmeta} pm2 ON pm.post_id = pm2.post_id
             WHERE pm.meta_key = '_wp_attachment_metadata'
             AND pm2.meta_key = '_xpse_imported'"
        );
        
        return [
            'total_imported' => (int) $total_imported,
            'total_size' => (int) $total_size,
            'total_size_formatted' => XPSE_Utilities::format_file_size($total_size)
        ];
    }
    
    /**
     * Clear image cache
     */
    public function clear_cache() {
        $this->image_cache = [];
    }
}