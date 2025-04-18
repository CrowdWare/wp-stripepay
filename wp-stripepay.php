<?php
/*
Plugin Name: WP StripePay
Description: Ein WordPress Plugin zum Verkauf von Büchern und digitalen Inhalten über Stripe. Es beinhaltet Admin-Bereiche für Stripe API Einstellungen, Produkte und Autoren sowie Shortcodes für die Anzeige einzelner Produkte und eines Produkt-Grids.
Version: 1.0.85
Author: CrowdWare
*/

// Sicherheitscheck
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Fügt Rewrite-Regeln für SEO-freundliche URLs hinzu
 */
function stripepay_add_rewrite_rules() {
    // Füge einen neuen Query-Parameter hinzu
    add_rewrite_tag('%product_id%', '([0-9]+)');
    
    // Füge verschiedene Rewrite-Regeln für verschiedene URL-Formate hinzu
    
    // Format 1: product/title-id/
    add_rewrite_rule(
        'product/([^/]+)-([0-9]+)/?$',
        'index.php?pagename=product&id=$2',
        'top'
    );
    
    // Format 2: product/title-with-numbers-123/
    add_rewrite_rule(
        'product/([^/]+)([0-9]+)/?$',
        'index.php?pagename=product&id=$2',
        'top'
    );
    
    // Format 3: product/anything/
    add_rewrite_rule(
        'product/([^/]+)/?$',
        'index.php?pagename=product',
        'top'
    );
}
add_action('init', 'stripepay_add_rewrite_rules');

/**
 * Fügt den product_id Parameter zur Query hinzu
 */
function stripepay_query_vars($vars) {
    $vars[] = 'product_id';
    return $vars;
}
add_filter('query_vars', 'stripepay_query_vars');

/**
 * Setzt den id Parameter für den Shortcode
 */
function stripepay_template_redirect() {
    global $wp_query;
    
    // Debug-Ausgabe
    error_log('WP StripePay Debug - Query Vars: ' . print_r($wp_query->query_vars, true));
    error_log('WP StripePay Debug - Is Product Page: ' . (is_page('product') ? 'Yes' : 'No'));
    
    // Prüfen, ob wir auf der Produktseite sind
    if (is_page('product')) {
        // Wenn id bereits in der Query ist, nichts tun
        if (isset($wp_query->query_vars['id']) && $wp_query->query_vars['id'] != '$2') {
            $_GET['id'] = $wp_query->query_vars['id'];
            error_log('WP StripePay Debug - Using ID from query_vars: ' . $_GET['id']);
        }
        // Sonst versuchen, die ID aus der URL zu extrahieren
        elseif (isset($_SERVER['REQUEST_URI'])) {
            $uri = $_SERVER['REQUEST_URI'];
            error_log('WP StripePay Debug - Analyzing URI in template_redirect: ' . $uri);
            
            // Format 1: product/title-id/
            if (preg_match('/product\/([^\/]+)-([0-9]+)\/?$/', $uri, $matches)) {
                $_GET['id'] = $matches[2];
                error_log('WP StripePay Debug - Extracted ID from URL format 1: ' . $_GET['id']);
            }
            // Format 2: product/title123/
            elseif (preg_match('/product\/[^0-9]*([0-9]+)\/?$/', $uri, $matches)) {
                $_GET['id'] = $matches[1];
                error_log('WP StripePay Debug - Extracted ID from URL format 2: ' . $_GET['id']);
            }
            // Format 3: Extrahiere jede Zahl aus der URL
            elseif (preg_match('/([0-9]+)/', $uri, $matches)) {
                $_GET['id'] = $matches[1];
                error_log('WP StripePay Debug - Extracted ID from any number in URL: ' . $_GET['id']);
            }
        }
    }
}
add_action('template_redirect', 'stripepay_template_redirect', 1);

/**
 * Flush der Rewrite-Regeln bei Aktivierung
 */
function stripepay_flush_rewrite_rules() {
    stripepay_add_rewrite_rules();
    flush_rewrite_rules();
}
register_activation_hook(__FILE__, 'stripepay_flush_rewrite_rules');

/**
 * Fügt Open Graph und Twitter Card Meta-Tags für Produktseiten hinzu
 */
function stripepay_add_social_meta_tags() {
    global $wpdb;
    
    // Nur auf Produktseiten ausführen
    if (!is_page('product') || !isset($_GET['id'])) {
        return;
    }
    
    $id = intval($_GET['id']);
    $products_table = $wpdb->prefix . 'stripepay_products';
    $author_table = $wpdb->prefix . 'stripepay_authors';
    
    $product = $wpdb->get_row($wpdb->prepare(
        "SELECT p.*, a.name as author_name FROM $products_table p 
         LEFT JOIN $author_table a ON p.author_id = a.id 
         WHERE p.id = %d",
        $id
    ));
    
    if (!$product) {
        return;
    }
    
    // Slug für die kanonische URL
    $product_slug = sanitize_title($product->name);
    $product_url = home_url("/product/{$product_slug}-{$product->id}/");
    
    // Aktuelle URL für AddToAny und andere Sharing-Dienste
    $current_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
    
    // Meta-Tags ausgeben
    echo '<meta property="og:title" content="' . esc_attr($product->name) . '" />' . "\n";
    echo '<meta property="og:description" content="' . esc_attr(wp_trim_words(strip_tags($product->kurztext), 30)) . '" />' . "\n";
    echo '<meta property="og:image" content="' . esc_url($product->image) . '" />' . "\n";
    echo '<meta property="og:url" content="' . esc_url($current_url) . '" />' . "\n";
    echo '<meta property="og:type" content="book" />' . "\n";
    
    // Zusätzliche Buch-spezifische Meta-Tags
    if (!empty($product->author_name)) {
        echo '<meta property="book:author" content="' . esc_attr($product->author_name) . '" />' . "\n";
    }
    
    // Twitter Card Tags
    echo '<meta name="twitter:card" content="summary_large_image" />' . "\n";
    echo '<meta name="twitter:title" content="' . esc_attr($product->name) . '" />' . "\n";
    echo '<meta name="twitter:description" content="' . esc_attr(wp_trim_words(strip_tags($product->kurztext), 30)) . '" />' . "\n";
    echo '<meta name="twitter:image" content="' . esc_url($product->image) . '" />' . "\n";
    
    // Kanonische URL
    echo '<link rel="canonical" href="' . esc_url($product_url) . '" />' . "\n";
    
    // AddToAny Meta-Tags
    echo '<meta name="addtoany:title" content="' . esc_attr($product->name) . '" />' . "\n";
    echo '<meta name="addtoany:description" content="' . esc_attr(wp_trim_words(strip_tags($product->kurztext), 30)) . '" />' . "\n";
}
add_action('wp_head', 'stripepay_add_social_meta_tags');

// Inkludiere benötigte Dateien
require_once plugin_dir_path( __FILE__ ) . 'includes/activation.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/admin.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/shortcodes.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/scripts.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/manual-stripe-loader.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/stripe-integration.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/emails.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/email-test.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/payments.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/parsedown.php';

// Aktivierungshook registrieren
register_activation_hook( __FILE__, 'stripepay_activate' );

/**
 * Filter für AddToAny Share Buttons
 * Ändert die URL und den Titel für das Sharing auf Produktseiten
 */
function stripepay_addtoany_share_link($link) {
    // Nur auf Produktseiten ausführen
    if (is_page('product') && isset($_GET['id'])) {
        global $wpdb;
        $id = intval($_GET['id']);
        $products_table = $wpdb->prefix . 'stripepay_products';
        
        $product = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $products_table WHERE id = %d",
            $id
        ));
        
        if ($product) {
            // Generiere die SEO-freundliche URL
            $product_slug = sanitize_title($product->name);
            $product_url = home_url("/product/{$product_slug}-{$product->id}/");
            
            // Ersetze die URL im Link
            $link = add_query_arg(array(
                'linkurl' => urlencode($product_url),
                'linkname' => urlencode($product->name)
            ), $link);
            
            error_log('WP StripePay Debug - Modified AddToAny link: ' . $link);
        }
    }
    return $link;
}
add_filter('addtoany_share_url', 'stripepay_addtoany_share_link');

/**
 * Filter für AddToAny Share Buttons Titel
 */
function stripepay_addtoany_share_title($title) {
    // Nur auf Produktseiten ausführen
    if (is_page('product') && isset($_GET['id'])) {
        global $wpdb;
        $id = intval($_GET['id']);
        $products_table = $wpdb->prefix . 'stripepay_products';
        
        $product = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $products_table WHERE id = %d",
            $id
        ));
        
        if ($product) {
            $title = $product->name;
            error_log('WP StripePay Debug - Modified AddToAny title: ' . $title);
        }
    }
    return $title;
}
add_filter('addtoany_share_title', 'stripepay_addtoany_share_title');

/**
 * Direktes Überschreiben der AddToAny-Buttons
 */
function stripepay_modify_addtoany_buttons() {
    // Nur auf Produktseiten ausführen
    if (is_page('product') && isset($_GET['id'])) {
        global $wpdb;
        $id = intval($_GET['id']);
        $products_table = $wpdb->prefix . 'stripepay_products';
        
        $product = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $products_table WHERE id = %d",
            $id
        ));
        
        if ($product) {
            // Generiere die SEO-freundliche URL
            $product_slug = sanitize_title($product->name);
            $product_url = home_url("/product/{$product_slug}-{$product->id}/");
            
            // JavaScript hinzufügen, um die AddToAny-Parameter zu überschreiben
            ?>
            <script type="text/javascript">
            document.addEventListener('DOMContentLoaded', function() {
                // AddToAny-Konfiguration überschreiben
                if (typeof window.a2a_config !== 'undefined') {
                    window.a2a_config.linkurl = "<?php echo esc_js($product_url); ?>";
                    window.a2a_config.linkname = "<?php echo esc_js($product->name); ?>";
                    
                    // Alle vorhandenen AddToAny-Links aktualisieren
                    var a2aLinks = document.querySelectorAll('a.a2a_dd, a.a2a_button_facebook, a.a2a_button_twitter, a.a2a_button_email');
                    a2aLinks.forEach(function(link) {
                        var href = link.getAttribute('href');
                        if (href) {
                            href = href.replace(/linkurl=[^&]+/, 'linkurl=' + encodeURIComponent("<?php echo esc_js($product_url); ?>"));
                            href = href.replace(/linkname=[^&]+/, 'linkname=' + encodeURIComponent("<?php echo esc_js($product->name); ?>"));
                            link.setAttribute('href', href);
                        }
                    });
                    
                    console.log('AddToAny configuration updated for product page');
                }
            });
            </script>
            <?php
        }
    }
}
add_action('wp_footer', 'stripepay_modify_addtoany_buttons');
