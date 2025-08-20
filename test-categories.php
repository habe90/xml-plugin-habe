<?php
/**
 * Test script za kategorije - ovo je samo za testiranje
 * Pokrenuti preko WP CLI ili kroz admin panel
 */

// Simulacija XML item objekta
class MockXMLItem {
    public $Kategorija1;
    public $Kategorija2;
    public $Kategorija3;
    
    public function __construct($cat1, $cat2 = '', $cat3 = '') {
        $this->Kategorija1 = $cat1;
        $this->Kategorija2 = $cat2;
        $this->Kategorija3 = $cat3;
    }
}

function test_categories() {
    // Test kategorije iz XML-a
    $test_items = [
        new MockXMLItem('Garmin', 'Senzor trake'),
        new MockXMLItem('Cross', 'Bicikli', 'MTB'),
        new MockXMLItem('Shimano', 'Komponente'),
        new MockXMLItem('486'), // Problematična kategorija
    ];
    
    // Kreiraj logger
    if (class_exists('XPSE_Logger')) {
        $logger = new XPSE_Logger();
    } else {
        $logger = null;
    }
    
    // Kreiraj category manager
    if (class_exists('XPSE_Category_Manager')) {
        $category_manager = new XPSE_Category_Manager($logger);
        
        echo "<h2>Test kategorija</h2>";
        
        foreach ($test_items as $index => $item) {
            echo "<h3>Test item " . ($index + 1) . "</h3>";
            echo "<p>Kategorija1: '" . $item->Kategorija1 . "'</p>";
            echo "<p>Kategorija2: '" . $item->Kategorija2 . "'</p>";
            echo "<p>Kategorija3: '" . $item->Kategorija3 . "'</p>";
            
            try {
                $category_ids = $category_manager->process_product_categories($item);
                echo "<p><strong>Rezultat:</strong> " . implode(', ', $category_ids) . "</p>";
                
                // Prikaži nazive kategorija
                foreach ($category_ids as $cat_id) {
                    $term = get_term($cat_id, 'product_cat');
                    if ($term && !is_wp_error($term)) {
                        echo "<p>- ID $cat_id: {$term->name} (slug: {$term->slug})</p>";
                    }
                }
                
            } catch (Exception $e) {
                echo "<p style='color: red;'><strong>Greška:</strong> " . $e->getMessage() . "</p>";
            }
            
            echo "<hr>";
        }
        
    } else {
        echo "XPSE_Category_Manager klasa nije dostupna!";
    }
}

// Test validacije kategorija
function test_category_validation() {
    echo "<h2>Test validacije kategorija</h2>";
    
    $test_categories = [
        'Garmin' => true,
        'Senzor trake' => true,
        '486' => false, // numerička
        '' => false, // prazna
        'A' => false, // prekratka
        'Test & Co.' => true,
        'Bicikli 27.5"' => true,
        '...' => false, // samo točkice
    ];
    
    foreach ($test_categories as $category => $expected) {
        $is_valid = XPSE_Utilities::is_valid_category($category);
        $status = $is_valid ? 'VALID' : 'INVALID';
        $color = ($is_valid === $expected) ? 'green' : 'red';
        
        echo "<p style='color: $color;'>";
        echo "'$category' -> $status ";
        echo ($is_valid === $expected) ? '✓' : '✗';
        echo "</p>";
    }
}

// Ako je pozvan preko web interface-a
if (isset($_GET['test_categories'])) {
    echo "<html><body>";
    test_category_validation();
    test_categories();
    echo "</body></html>";
}