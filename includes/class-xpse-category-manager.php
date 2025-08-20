<?php
/**
 * Category Manager class for XML Product Sync Enhanced
 *
 * @package XML_Product_Sync_Enhanced
 */

if (!defined('ABSPATH')) {
    exit;
}

class XPSE_Category_Manager {
    
    private $logger;
    private $category_cache = [];
    private $category_mapping = [];
    
    /**
     * Constructor
     */
    public function __construct($logger = null) {
        $this->logger = $logger;
        $this->load_category_mapping();
    }
    
    /**
     * Load custom category mapping
     */
    private function load_category_mapping() {
        $this->category_mapping = get_option('xpse_category_mapping', []);
    }
    
    /**
     * Process hierarchical categories for a product
     */
public function process_product_categories($item) {
    $categories = $this->extract_categories_from_item($item);

    if (empty($categories)) {
        return [$this->get_default_category()];
    }

    $term_ids = [];

    foreach ($categories as $cat) {
        if (is_int($cat)) {
            // Ako je već WC ID iz mape → samo dodaj
            $term_ids[] = $cat;
        } else {
            // Inače kreiraj/hijerarhija
            $term_ids = array_merge($term_ids, $this->build_category_hierarchy([$cat]));
        }
    }

    return array_unique($term_ids);
}

    
    /**
     * Extract categories from XML item
     */
    private function extract_categories_from_item($item) {
        $categories = [];
        
        for ($i = 1; $i <= 5; $i++) {
            $cat_name = trim((string) $item->{'Kategorija' . $i});
            
            if ($this->logger) {
                $this->logger->debug("Čitam kategoriju iz XML-a", [
                    'position' => $i,
                    'raw_value' => $cat_name,
                    'is_valid' => XPSE_Utilities::is_valid_category($cat_name)
                ]);
            }
            
            if (XPSE_Utilities::is_valid_category($cat_name)) {
                // Apply custom mapping if exists
                $mapped_name = $this->apply_category_mapping($cat_name);
                $categories[] = $mapped_name;
                
                if ($this->logger) {
                    $this->logger->debug("Kategorija dodana u listu", [
                        'original' => $cat_name,
                        'mapped' => $mapped_name
                    ]);
                }
            }
        }
        
        if ($this->logger) {
            $this->logger->debug("Kategorije izvučene iz XML-a", [
                'categories' => $categories
            ]);
        }
        
        return $categories;
    }
    
    /**
     * Apply custom category mapping
     */
   private function apply_category_mapping($category_name) {
    $normalized = strtolower(trim($category_name));
    
    if (isset($this->category_mapping[$normalized])) {
        // Vrati WooCommerce category ID direktno
        $mapping = $this->category_mapping[$normalized];
        
        if (is_array($mapping) && isset($mapping['to'])) {
            return (int) $mapping['to'];
        }

        // Stari format (samo broj)
        return (int) $mapping;
    }
    
    // Ako nema mapiranja → vrati originalni naziv (da se eventualno kreira nova kategorija)
    return $category_name;
}

    
    /**
     * Build hierarchical category structure
     */
    private function build_category_hierarchy($categories) {
        $parent_id = 0;
        $term_ids = [];
        
        if ($this->logger) {
            $this->logger->debug("Gradim hijerarhiju kategorija", [
                'categories' => $categories
            ]);
        }
        
        foreach ($categories as $category_name) {
            $term_id = $this->get_or_create_category($category_name, $parent_id);
            
            if ($term_id) {
                $term_ids[] = $term_id;
                $parent_id = $term_id;
                
                if ($this->logger) {
                    $this->logger->debug("Kategorija dodana", [
                        'name' => $category_name,
                        'term_id' => $term_id,
                        'parent_id' => $parent_id
                    ]);
                }
            } else {
                // Log error but continue with existing hierarchy
                if ($this->logger) {
                    $this->logger->warning("Nije moguće kreirati kategoriju: {$category_name}", [
                        'parent_id' => $parent_id
                    ]);
                }
                break;
            }
        }
        
        if ($this->logger) {
            $this->logger->debug("Hijerarhija kategorija završena", [
                'term_ids' => $term_ids
            ]);
        }
        
        return $term_ids;
    }
    
    /**
     * Get or create category
     */
    private function get_or_create_category($category_name, $parent_id = 0) {
        // Check cache first
        $cache_key = $category_name . '_' . $parent_id;
        if (isset($this->category_cache[$cache_key])) {
            return $this->category_cache[$cache_key];
        }
        
        // Sanitize category name
        $sanitized_name = XPSE_Utilities::sanitize_text($category_name);
        
        // Check if category exists
        $existing_term = $this->find_existing_category($sanitized_name, $parent_id);
        
        if ($existing_term) {
            $term_id = $existing_term['term_id'];
        } else {
            // Create new category
            $term_id = $this->create_new_category($sanitized_name, $parent_id);
        }
        
        // Cache the result
        if ($term_id) {
            $this->category_cache[$cache_key] = $term_id;
        }
        
        return $term_id;
    }
    
    /**
     * Find existing category
     */
    private function find_existing_category($category_name, $parent_id = 0) {
        // Try exact match first
        $term = term_exists($category_name, 'product_cat', $parent_id);
        
        if (is_array($term)) {
            return $term;
        }
        
        // Try case-insensitive search
        $terms = get_terms([
            'taxonomy' => 'product_cat',
            'parent' => $parent_id,
            'hide_empty' => false,
            'meta_query' => [],
            'name__like' => $category_name
        ]);
        
        foreach ($terms as $existing_term) {
            if (strcasecmp($existing_term->name, $category_name) === 0) {
                return [
                    'term_id' => $existing_term->term_id,
                    'term_taxonomy_id' => $existing_term->term_taxonomy_id
                ];
            }
        }
        
        // Try fuzzy matching if enabled
        if (get_option('xpse_enable_fuzzy_category_matching', false)) {
            return $this->find_fuzzy_match($category_name, $parent_id);
        }
        
        return false;
    }
    
    /**
     * Find fuzzy category match
     */
    private function find_fuzzy_match($category_name, $parent_id) {
        $terms = get_terms([
            'taxonomy' => 'product_cat',
            'parent' => $parent_id,
            'hide_empty' => false
        ]);
        
        $best_match = null;
        $best_score = 0;
        $threshold = 0.8; // 80% similarity threshold
        
        foreach ($terms as $term) {
            $similarity = 0;
            similar_text(strtolower($category_name), strtolower($term->name), $similarity);
            
            if ($similarity > $threshold && $similarity > $best_score) {
                $best_score = $similarity;
                $best_match = [
                    'term_id' => $term->term_id,
                    'term_taxonomy_id' => $term->term_taxonomy_id
                ];
            }
        }
        
        if ($best_match && $this->logger) {
            $this->logger->debug("Fuzzy match pronađen za kategoriju '{$category_name}'", [
                'matched_term_id' => $best_match['term_id'],
                'similarity' => $best_score
            ]);
        }
        
        return $best_match;
    }
    
    /**
     * Create new category
     */
    private function create_new_category($category_name, $parent_id = 0) {
        if (!get_option('xpse_create_missing_categories', true)) {
            if ($this->logger) {
                $this->logger->warning("Kreiranje kategorija je onemogućeno: {$category_name}");
            }
            return false;
        }
        
        $args = [
            'parent' => $parent_id,
            'slug' => $this->generate_category_slug($category_name, $parent_id)
        ];
        
        if ($this->logger) {
            $this->logger->debug("Kreiram novu kategoriju", [
                'name' => $category_name,
                'parent_id' => $parent_id,
                'slug' => $args['slug']
            ]);
        }
        
        $new_term = wp_insert_term($category_name, 'product_cat', $args);
        
        if (is_wp_error($new_term)) {
            if ($this->logger) {
                $this->logger->error("Greška pri kreiranju kategorije: {$category_name}", [
                    'error' => $new_term->get_error_message(),
                    'parent_id' => $parent_id
                ]);
            }
            return false;
        }
        
        if ($this->logger) {
            $this->logger->info("Nova kategorija kreirana: {$category_name}", [
                'term_id' => $new_term['term_id'],
                'parent_id' => $parent_id,
                'slug' => $args['slug']
            ]);
        }
        
        return $new_term['term_id'];
    }
    
    /**
     * Generate unique category slug
     */
    private function generate_category_slug($category_name, $parent_id = 0) {
        $base_slug = sanitize_title($category_name);
        $slug = $base_slug;
        $counter = 1;
        
        while (term_exists($slug, 'product_cat')) {
            $slug = $base_slug . '-' . $counter;
            $counter++;
        }
        
        return $slug;
    }
    
    /**
     * Get default category
     */
    private function get_default_category() {
        $default_category_name = get_option('xpse_default_category', 'Bez kategorije');
        
        // Try to find existing default category
        $term = term_exists($default_category_name, 'product_cat');
        
        if (is_array($term)) {
            return $term['term_id'];
        }
        
        // Create default category if it doesn't exist
        $new_term = wp_insert_term($default_category_name, 'product_cat');
        
        if (is_wp_error($new_term)) {
            if ($this->logger) {
                $this->logger->error("Nije moguće kreirati default kategoriju: {$default_category_name}", [
                    'error' => $new_term->get_error_message()
                ]);
            }
            
            // Return uncategorized category as fallback
            $uncategorized = get_option('default_product_cat');
            return $uncategorized ? $uncategorized : 0;
        }
        
        return $new_term['term_id'];
    }
    
    /**
     * Get category path for display
     */
    public function get_category_path($term_id) {
        $path = [];
        $current_term_id = $term_id;
        
        while ($current_term_id) {
            $term = get_term($current_term_id, 'product_cat');
            
            if (is_wp_error($term) || !$term) {
                break;
            }
            
            array_unshift($path, $term->name);
            $current_term_id = $term->parent;
        }
        
        return implode(' > ', $path);
    }
    
    /**
     * Add custom category mapping
     */
 public function add_category_mapping($from, $to) {
    $from = strtolower(trim($from));
    $to   = (int) $to;

    $this->category_mapping[$from] = [
        'from'          => $from,
        'to'            => $to,
        'product_count' => 0,
    ];

    update_option('xpse_category_mapping', $this->category_mapping);

    if ($this->logger) {
        $this->logger->info("Dodano mapiranje kategorije: '{$from}' -> '{$to}'");
    }
}

    
    /**
     * Remove category mapping
     */
    public function remove_category_mapping($from) {
        $from = strtolower(trim($from));
        
        if (isset($this->category_mapping[$from])) {
            unset($this->category_mapping[$from]);
            update_option('xpse_category_mapping', $this->category_mapping);
            
            if ($this->logger) {
                $this->logger->info("Uklono mapiranje kategorije: '{$from}'");
            }
            
            return true;
        }
        
        return false;
    }
    
    /**
     * Get all category mappings
     */
  public function get_category_mappings() {
    $normalized = [];

    foreach ($this->category_mapping as $from => $mapping) {
        if (is_array($mapping)) {
            $normalized[$from] = $mapping;
        } else {
            // Pretvori stari format u novi
            $normalized[$from] = [
                'from'          => $from,
                'to'            => (int) $mapping,
                'product_count' => 0,
            ];
        }
    }

    return $normalized;
}

    /**
     * Clear category cache
     */
    public function clear_cache() {
        $this->category_cache = [];
    }
    
    /**
     * Get category statistics
     */
    public function get_category_stats() {
        $categories = get_terms([
            'taxonomy' => 'product_cat',
            'hide_empty' => false
        ]);
        
        $stats = [
            'total' => count($categories),
            'with_products' => 0,
            'empty' => 0,
            'levels' => []
        ];
        
        foreach ($categories as $category) {
            if ($category->count > 0) {
                $stats['with_products']++;
            } else {
                $stats['empty']++;
            }
            
            // Calculate category level
            $level = $this->get_category_level($category->term_id);
            $stats['levels'][$level] = ($stats['levels'][$level] ?? 0) + 1;
        }
        
        return $stats;
    }
    
    /**
     * Get category level (depth in hierarchy)
     */
    private function get_category_level($term_id) {
        $level = 0;
        $current_term_id = $term_id;
        
        while ($current_term_id) {
            $term = get_term($current_term_id, 'product_cat');
            
            if (is_wp_error($term) || !$term) {
                break;
            }
            
            if ($term->parent) {
                $level++;
                $current_term_id = $term->parent;
            } else {
                break;
            }
        }
        
        return $level;
    }
    
    /**
     * Clean up empty categories
     */
    public function cleanup_empty_categories($dry_run = true) {
        $empty_categories = get_terms([
            'taxonomy' => 'product_cat',
            'hide_empty' => false,
            'meta_query' => [
                [
                    'key' => 'product_count_product_cat',
                    'value' => 0,
                    'compare' => '='
                ]
            ]
        ]);
        
        $deleted = [];
        
        foreach ($empty_categories as $category) {
            // Skip default category
            if ($category->term_id == get_option('default_product_cat')) {
                continue;
            }
            
            if (!$dry_run) {
                $result = wp_delete_term($category->term_id, 'product_cat');
                
                if (!is_wp_error($result)) {
                    $deleted[] = [
                        'id' => $category->term_id,
                        'name' => $category->name
                    ];
                }
            } else {
                $deleted[] = [
                    'id' => $category->term_id,
                    'name' => $category->name
                ];
            }
        }
        
        if ($this->logger && !$dry_run) {
            $this->logger->info("Obrisano " . count($deleted) . " praznih kategorija", [
                'deleted_categories' => $deleted
            ]);
        }
        
        return $deleted;
    }
}