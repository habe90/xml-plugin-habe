<?php
/**
 * Categories view for XML Product Sync Enhanced
 */

if (!defined('ABSPATH')) {
    exit();
}
?>

<div class="wrap">
    <h1>XML Product Sync Enhanced - Upravljanje Kategorijama</h1>

    <?php settings_errors(); ?>

    <div class="xpse-categories">
        <!-- Category Statistics -->
        <div class="xpse-card">
            <h2>Statistike Kategorija</h2>
            <div class="xpse-stats-grid">
                <div class="stat-item">
                    <span class="stat-value"><?php echo number_format($category_stats['total_categories'] ?? 0); ?></span>
                    <span class="stat-label">Ukupno Kategorija</span>
                </div>
                <div class="stat-item">
                    <span class="stat-value"><?php echo number_format($category_stats['mapped_categories'] ?? 0); ?></span>
                    <span class="stat-label">Mapirane Kategorije</span>
                </div>
                <div class="stat-item">
                    <span class="stat-value"><?php echo number_format($category_stats['products_with_categories'] ?? 0); ?></span>
                    <span class="stat-label">Proizvodi s Kategorijama</span>
                </div>
                <div class="stat-item">
                    <span class="stat-value"><?php echo number_format($category_stats['products_without_categories'] ?? 0); ?></span>
                    <span class="stat-label">Proizvodi bez Kategorija</span>
                </div>
            </div>
        </div>

        <!-- Category Mappings -->
        <div class="xpse-card">
            <h2>Mapiranje Kategorija</h2>
            <p>Definiši kako se XML kategorije mapiraju na WooCommerce kategorije.</p>

            <!-- Add New Mapping -->
            <div class="xpse-add-mapping">
                <h3>Dodaj Novo Mapiranje</h3>
                <form method="post" action="">
                    <?php wp_nonce_field('xpse_categories'); ?>
                    <table class="form-table">
                        <tr>
                            <th scope="row">XML Kategorija</th>
                            <td>
                                <input type="text" name="mapping_from" placeholder="npr. Biciklizam > MTB"
                                    class="regular-text" required />
                                <p class="description">Naziv kategorije iz XML feed-a (koristi > za hijerarhiju).</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">WooCommerce Kategorija</th>
                            <td>
                                <?php
                                $args = [
                                    'taxonomy' => 'product_cat',
                                    'name' => 'mapping_to',
                                    'class' => 'regular-text',
                                    'hierarchical' => true,
                                    'show_option_none' => 'Izaberi kategoriju...',
                                    'option_none_value' => '',
                                    'number' => 0,
                                    'hide_empty' => false,
                                ];
                                wp_dropdown_categories($args);
                                ?>
                                <p class="description">WooCommerce kategorija na koju se mapira.</p>
                            </td>
                        </tr>
                    </table>
                    <button type="submit" name="action" value="add_mapping" class="button button-primary">Dodaj
                        Mapiranje</button>
                </form>
            </div>

            <!-- Existing Mappings -->
            <div class="xpse-existing-mappings">
                <h3>Postojeća Mapiranja</h3>
                <?php if (!empty($category_mappings)): ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th scope="col">XML Kategorija</th>
                            <th scope="col">WooCommerce Kategorija</th>
                            <th scope="col">Broj Proizvoda</th>
                            <th scope="col">Akcije</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($category_mappings as $mapping): ?>
                        <tr>
                            <td>
                                <?php echo is_array($mapping) ? esc_html($mapping['from']) : esc_html($mapping); ?>
                            </td>
                            <td>
                                <?php
                                if (is_array($mapping) && isset($mapping['to'])) {
                                    $term = get_term($mapping['to'], 'product_cat');
                                    if ($term && !is_wp_error($term)) {
                                        echo esc_html($term->name);
                                    } else {
                                        echo '<span style="color: red;">Kategorija ne postoji</span>';
                                    }
                                } else {
                                    echo '<span style="color: orange;">Nedefinisano mapiranje</span>';
                                }
                                ?>
                            </td>
                            <td>
                                <?php echo is_array($mapping) && isset($mapping['product_count']) ? number_format($mapping['product_count']) : 0; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>

                    </tbody>
                </table>
                <?php else: ?>
                <p>Nema definisanih mapiranja kategorija.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Category Hierarchy Preview -->
        <div class="xpse-card">
            <h2>Pregled Hijerarhije Kategorija</h2>
            <div class="xpse-category-tree">
                <?php
                $categories = get_terms(array(
                    'taxonomy' => 'product_cat',
                    'hide_empty' => false,
                    'hierarchical' => true
                ));
                
                if (!empty($categories)):
                    $category_tree = array();
                    foreach ($categories as $category) {
                        if ($category->parent == 0) {
                            $category_tree[$category->term_id] = $category;
                            $category_tree[$category->term_id]->children = array();
                        }
                    }
                    
                    foreach ($categories as $category) {
                        if ($category->parent != 0 && isset($category_tree[$category->parent])) {
                            $category_tree[$category->parent]->children[] = $category;
                        }
                    }
                ?>
                <ul class="xpse-tree">
                    <?php foreach ($category_tree as $parent_category): ?>
                    <li>
                        <strong><?php echo esc_html($parent_category->name); ?></strong>
                        <span class="category-count">(<?php echo $parent_category->count; ?> proizvoda)</span>
                        <?php if (!empty($parent_category->children)): ?>
                        <ul>
                            <?php foreach ($parent_category->children as $child_category): ?>
                            <li>
                                <?php echo esc_html($child_category->name); ?>
                                <span class="category-count">(<?php echo $child_category->count; ?> proizvoda)</span>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                        <?php endif; ?>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php else: ?>
                <p>Nema WooCommerce kategorija.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Category Tools -->
        <div class="xpse-card">
            <h2>Alati za Kategorije</h2>

            <div class="xpse-tool-group">
                <h3>Automatsko Kreiranje Kategorija</h3>
                <p>Automatski kreiraj nedostajuće kategorije na osnovu XML feed-a.</p>
                <button type="button" class="button" id="auto-create-categories">Analiziraj XML i Kreiraj
                    Kategorije</button>
                <div id="auto-create-result"></div>
            </div>

            <div class="xpse-tool-group">
                <h3>Čišćenje Praznih Kategorija</h3>
                <p>Ukloni kategorije koje nemaju proizvode.</p>
                <button type="button" class="button button-secondary" onclick="cleanupCategories(true)">Pregled (Dry
                    Run)</button>
                <button type="button" class="button button-secondary" onclick="cleanupCategories(false)">Obriši Prazne
                    Kategorije</button>
                <div id="cleanup-categories-result"></div>
            </div>

            <div class="xpse-tool-group">
                <h3>Re-mapiranje Proizvoda</h3>
                <p>Ponovo dodijeli proizvode kategorijama na osnovu trenutnih mapiranja.</p>
                <button type="button" class="button" id="remap-products">Ponovo Mapiraj Sve Proizvode</button>
                <div id="remap-result"></div>
            </div>
        </div>
    </div>
</div>

<script type="text/javascript">
    jQuery(document).ready(function($) {

        // Auto-create categories
        $('#auto-create-categories').on('click', function() {
            var $btn = $(this);
            var $result = $('#auto-create-result');

            $btn.prop('disabled', true).text('Analiziram...');
            $result.html('<p>Analiziram XML feed i kreiram kategorije...</p>');

            $.ajax({
                url: xpse_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'xpse_auto_create_categories',
                    nonce: xpse_admin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $result.html('<div class="notice notice-success"><p>' + response
                            .data.message + '</p></div>');
                    } else {
                        $result.html('<div class="notice notice-error"><p>Greška: ' +
                            response.data + '</p></div>');
                    }
                },
                error: function() {
                    $result.html(
                        '<div class="notice notice-error"><p>Greška pri kreiranju kategorija.</p></div>'
                        );
                },
                complete: function() {
                    $btn.prop('disabled', false).text(
                    'Analiziraj XML i Kreiraj Kategorije');
                }
            });
        });

        // Re-map products
        $('#remap-products').on('click', function() {
            var $btn = $(this);
            var $result = $('#remap-result');

            if (!confirm(
                    'Da li ste sigurni da želite ponovno mapirati sve proizvode? Ovo može potrajati.'
                    )) {
                return;
            }

            $btn.prop('disabled', true).text('Mapiram...');
            $result.html('<p>Ponovno mapiram proizvode u kategorije...</p>');

            $.ajax({
                url: xpse_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'xpse_remap_products',
                    nonce: xpse_admin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $result.html('<div class="notice notice-success"><p>' + response
                            .data.message + '</p></div>');
                    } else {
                        $result.html('<div class="notice notice-error"><p>Greška: ' +
                            response.data + '</p></div>');
                    }
                },
                error: function() {
                    $result.html(
                        '<div class="notice notice-error"><p>Greška pri mapiranju proizvoda.</p></div>'
                        );
                },
                complete: function() {
                    $btn.prop('disabled', false).text('Ponovo Mapiraj Sve Proizvode');
                }
            });
        });
    });

    function cleanupCategories(dryRun) {
        var $result = $('#cleanup-categories-result');
        var action = dryRun ? 'pregled' : 'brisanje';

        if (!dryRun && !confirm(xpse_admin.strings.confirm_cleanup)) {
            return;
        }

        $result.html('<p>Pokrećem ' + action + ' praznih kategorija...</p>');

        jQuery.ajax({
            url: xpse_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'xpse_cleanup_categories',
                nonce: xpse_admin.nonce,
                dry_run: dryRun
            },
            success: function(response) {
                if (response.success) {
                    $result.html('<div class="notice notice-success"><p>' + response.data.message +
                        '</p></div>');
                    if (!dryRun && response.data.categories.length > 0) {
                        setTimeout(function() {
                            location.reload();
                        }, 2000);
                    }
                } else {
                    $result.html('<div class="notice notice-error"><p>Greška: ' + response.data +
                        '</p></div>');
                }
            },
            error: function() {
                $result.html('<div class="notice notice-error"><p>Greška pri ' + action +
                    ' kategorija.</p></div>');
            }
        });
    }
</script>
