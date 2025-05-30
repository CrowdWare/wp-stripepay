<?php
/**
 * Shortcode: Einzelproduktanzeige.
 */
function stripepay_product_shortcode($atts = array()) {
    global $wpdb, $wp_query;
    
    // Debug-Informationen
    error_log('WP StripePay Shortcode Debug - GET params: ' . print_r($_GET, true));
    error_log('WP StripePay Shortcode Debug - WP Query: ' . print_r($wp_query->query_vars, true));
    error_log('WP StripePay Shortcode Debug - REQUEST_URI: ' . $_SERVER['REQUEST_URI']);
    
    // Versuche ID aus verschiedenen Quellen zu bekommen
    $id = 0;
    
    // 1. Aus Shortcode-Attributen (für manuelle Verwendung)
    if (isset($atts['id'])) {
        $id = intval($atts['id']);
        error_log('WP StripePay Shortcode Debug - ID from shortcode attribute: ' . $id);
    }
    // 2. Aus $_GET['id']
    elseif (isset($_GET['id'])) {
        $id = intval($_GET['id']);
        error_log('WP StripePay Shortcode Debug - ID from GET: ' . $id);
    }
    // 3. Aus Query-Variablen
    elseif (isset($wp_query->query_vars['product_id'])) {
        $id = intval($wp_query->query_vars['product_id']);
        error_log('WP StripePay Shortcode Debug - ID from query_vars: ' . $id);
    }
    // 4. Aus URL extrahieren (direkter Ansatz)
    elseif (isset($_SERVER['REQUEST_URI'])) {
        $uri = $_SERVER['REQUEST_URI'];
        error_log('WP StripePay Shortcode Debug - Analyzing URI: ' . $uri);
        
        // Methode 1: Standard-Format product/title-id/
        if (preg_match('/product\/([^\/]+)-([0-9]+)\/?$/', $uri, $matches)) {
            $id = intval($matches[2]);
            error_log('WP StripePay Shortcode Debug - ID from URL regex method 1: ' . $id);
        }
        // Methode 2: Alternative Format product/title-id
        elseif (preg_match('/product\/([^\/]+)-([0-9]+)$/', $uri, $matches)) {
            $id = intval($matches[2]);
            error_log('WP StripePay Shortcode Debug - ID from URL regex method 2: ' . $id);
        }
        // Methode 3: Extraktion der letzten Zahl aus dem letzten Pfadteil
        elseif (strpos($uri, 'product/') !== false) {
            $parts = explode('/', trim($uri, '/'));
            $last_part = end($parts);
            error_log('WP StripePay Shortcode Debug - Last path part: ' . $last_part);
            
            // Versuche, die letzte Zahl zu extrahieren
            if (preg_match('/([0-9]+)$/', $last_part, $matches)) {
                $id = intval($matches[1]);
                error_log('WP StripePay Shortcode Debug - ID from last number in path: ' . $id);
            }
            // Versuche, eine Zahl irgendwo im letzten Pfadteil zu finden
            elseif (preg_match('/([0-9]+)/', $last_part, $matches)) {
                $id = intval($matches[1]);
                error_log('WP StripePay Shortcode Debug - ID from any number in path: ' . $id);
            }
        }
    }
    
    if (!$id) {
        error_log('WP StripePay Shortcode Debug - No ID found in any source');
        // Versuche, alle Produkte zu laden und das erste anzuzeigen (Fallback)
        $products_table = $wpdb->prefix . 'stripepay_products';
        $products = $wpdb->get_results("SELECT id FROM $products_table LIMIT 1");
        if (!empty($products)) {
            $id = $products[0]->id;
            error_log('WP StripePay Shortcode Debug - Using fallback product ID: ' . $id);
        } else {
            return '<p>Kein Produkt gefunden. (ID konnte nicht ermittelt werden)</p>';
        }
    }
    
    $product_id = intval($id);
    if (!$product_id) {
        error_log('WP StripePay Shortcode Debug - Invalid product ID');
        return '<p>Ungültiges Produkt.</p>';
    }
    
    $products_table = $wpdb->prefix . 'stripepay_products';
    $author_table = $wpdb->prefix . 'stripepay_authors';
    $product = $wpdb->get_row( $wpdb->prepare( "SELECT p.*, a.name as author_name, a.image as author_image, a.bio as author_bio FROM $products_table p LEFT JOIN $author_table a ON p.author_id = a.id WHERE p.id = %d", $product_id ) );
    if ( ! $product ) {
        return 'Produkt nicht gefunden.';
    }
    
    // Generiere die kanonische URL für dieses Produkt
    $product_slug = sanitize_title($product->name);
    $product_url = home_url("/product/{$product_slug}-{$product->id}/");
    
    ob_start();
    $Parsedown = new Parsedown();
    ?>
    <div class="stripepay-product">
        <div class="stripepay-row">
            <div class="stripepay-image">
                <img src="<?php echo esc_html($product->image); ?>" alt="Produktbild">
            </div>
            <div class="stripepay-content">
                <h2><?php echo esc_html( $product->name ); ?></h2>
                <p><strong><?php echo esc_html( $product->subtitle ); ?></strong></p>
                <p>Von: <?php echo esc_html( $product->author_name ); ?></p>
                <p>Preis: <?php 
                    if ($product->price == 0) {
                        echo 'kostenlos';
                    } else {
                        echo number_format($product->price / 100, 2, ',', '.') . ' €'; 
                    }
                ?></p>
                <p><?php echo $Parsedown->text($product->kurztext); ?></p>
                
                <!-- Eigene Share-Buttons -->
                <div class="stripepay-share-buttons">
                    <p>Teilen:</p>
                    <?php
                    // Generiere die SEO-freundliche URL für das Sharing
                    $share_url = urlencode($product_url);
                    $share_title = urlencode($product->name);
                    ?>
                    <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo $share_url; ?>" target="_blank" class="stripepay-share-button stripepay-facebook">
                        Facebook
                    </a>
                    <a href="https://twitter.com/intent/tweet?url=<?php echo $share_url; ?>&text=<?php echo $share_title; ?>" target="_blank" class="stripepay-share-button stripepay-twitter">
                        Twitter
                    </a>
                    <a href="mailto:?subject=<?php echo $share_title; ?>&body=<?php echo $share_url; ?>" class="stripepay-share-button stripepay-email">
                        E-Mail
                    </a>
                    <a href="https://api.whatsapp.com/send?text=<?php echo $share_title . '%20' . $share_url; ?>" target="_blank" class="stripepay-share-button stripepay-whatsapp">
                        WhatsApp
                    </a>
                </div>
            </div>
        </div>
        <p><?php echo $Parsedown->text($product->langtext); ?></p>
        
        <?php 
        // Prüfen, ob die download_url eine externe URL ist
        $is_external_url = false;
        if (!empty($product->download_url)) {
            // Prüfen, ob die URL mit http:// oder https:// beginnt
            $is_url = strpos($product->download_url, 'http://') === 0 || strpos($product->download_url, 'https://') === 0;
            
            if ($is_url) {
                // Aktuelle Domain ermitteln
                $site_url = parse_url(site_url(), PHP_URL_HOST);
                
                // Prüfen, ob die URL die aktuelle Domain enthält
                $url_host = parse_url($product->download_url, PHP_URL_HOST);
                
                // Wenn die URL-Domain nicht die aktuelle Domain ist, handelt es sich um eine externe URL
                $is_external_url = $url_host !== $site_url && strpos($url_host, $site_url) === false;
            }
        }
        
        // Prüfen, ob die download_url auf eine APK-Datei verweist
        $is_apk_file = !empty($product->download_url) && strpos($product->download_url, '.apk') !== false;
        
        if ($product->price == 0): ?>
            <?php if ($is_apk_file): ?>
                <div class="stripepay-apk-notice">
                    <h3>Hinweis</h3>
                    <p>Dieses Buch findest Du nur im FreeBookReader für Android</p>
                    <a href="<?php echo esc_url($product->download_url); ?>" class="stripepay-apk-button">
                        Download APK
                    </a>
                </div>
            <?php elseif (!empty($product->download_url)): ?>
                <div class="stripepay-free-download">
                    <h3>Download</h3>
                    <a href="<?php echo esc_url($product->download_url); ?>" class="stripepay-download-button">
                        Jetzt herunterladen
                    </a>
                </div>
            <?php endif; ?>
        <?php elseif ($is_external_url): ?>
            <div class="stripepay-external-order">
                <h3>Bestellung</h3>
                <a href="<?php echo esc_url($product->download_url); ?>" class="stripepay-order-button" target="_blank">
                    Bestellen
                </a>
            </div>
        <?php else: ?>
            <div class="stripepay-payment-container">
                <h3>Jetzt kaufen</h3>
                
                <!-- Erfolgs- und Fehlermeldungen -->
                <div id="stripepay-payment-success" style="display: none;"></div>
                <div id="stripepay-payment-processing" style="display: none;"></div>
                <div id="stripepay-card-errors" role="alert" style="display: none;"></div>
                
                <!-- Zahlungsformular -->
                <form id="stripepay-payment-form" data-product-id="<?php echo esc_attr($product_id); ?>">
                    <div class="stripepay-form-row">
                        <label for="stripepay_email">E-Mail-Adresse</label>
                        <input type="email" id="stripepay_email" name="stripepay_email" required>
                    </div>
                    
                    <div class="stripepay-form-row">
                        <label for="stripepay-card-element">Kreditkarte</label>
                        <div id="stripepay-card-element">
                            <!-- Stripe Elements wird hier eingefügt -->
                        </div>
                    </div>
                    
                    <button type="submit">Kaufen</button>
                </form>
            </div>
        <?php endif; ?>
    </div>
    <style>
    .stripepay-apk-notice {
        background-color: #343a40;
        border: 1px solid #495057;
        border-radius: 4px;
        padding: 20px;
        margin: 20px 0;
        text-align: center;
        color: #f8f9fa;
    }
    
    .stripepay-apk-notice h3 {
        color: #f8f9fa;
    }
    
    .stripepay-apk-button {
        display: inline-block;
        background-color: #6c757d;
        color: white;
        padding: 10px 20px;
        border-radius: 4px;
        text-decoration: none;
        font-weight: bold;
        margin: 15px 0 5px 0;
    }
    
    .stripepay-apk-button:hover {
        background-color: #5a6268;
        color: white;
        text-decoration: none;
    }
    
    .stripepay-free-download {
        margin: 20px 0;
        text-align: center;
    }
    
    .stripepay-download-button {
        display: inline-block;
        background-color: #28a745;
        color: white;
        padding: 10px 20px;
        border-radius: 4px;
        text-decoration: none;
        font-weight: bold;
        margin: 10px 0;
    }
    
    .stripepay-download-button:hover {
        background-color: #218838;
        color: white;
        text-decoration: none;
    }
    
    .stripepay-external-order {
        margin: 20px 0;
        text-align: center;
    }
    
    .stripepay-order-button {
        display: inline-block;
        background-color: #0275d8;
        color: white;
        padding: 10px 20px;
        border-radius: 4px;
        text-decoration: none;
        font-weight: bold;
        margin: 10px 0;
    }
    
    .stripepay-order-button:hover {
        background-color: #025aa5;
        color: white;
        text-decoration: none;
    }
    
    .stripepay-row {
    display: flex;
    align-items: flex-start;
    gap: 2rem;
    flex-wrap: wrap;
    }

    .stripepay-content h2 {
        margin-top: 0;
    }

    .stripepay-image img {
    max-width: 100%;
    height: auto;
    max-height: 400px;
    object-fit: contain;
    }

    .stripepay-image {
    flex: 1 1 300px;
    }

    .stripepay-content {
    flex: 2 1 400px;
    }

    /* Share Buttons Styling */
    .stripepay-share-buttons {
        margin-top: 20px;
        display: flex;
        align-items: center;
        flex-wrap: wrap;
    }
    
    .stripepay-share-buttons p {
        margin-right: 10px;
        margin-bottom: 10px;
    }
    
    .stripepay-share-button {
        display: inline-block;
        padding: 8px 12px;
        margin-right: 8px;
        margin-bottom: 8px;
        border-radius: 4px;
        text-decoration: none;
        color: white;
        font-size: 14px;
        transition: background-color 0.3s ease;
    }
    
    .stripepay-facebook {
        background-color: #3b5998;
    }
    
    .stripepay-facebook:hover {
        background-color: #2d4373;
    }
    
    .stripepay-twitter {
        background-color: #1da1f2;
    }
    
    .stripepay-twitter:hover {
        background-color: #0c85d0;
    }
    
    .stripepay-email {
        background-color: #777;
    }
    
    .stripepay-email:hover {
        background-color: #555;
    }
    
    .stripepay-whatsapp {
        background-color: #25d366;
    }
    
    .stripepay-whatsapp:hover {
        background-color: #1da851;
    }

    @media (max-width: 768px) {
    .stripepay-row {
        flex-direction: column;
    }
    }
    </style>
    <?php
    return ob_get_clean();
}
add_shortcode( 'stripepay_product', 'stripepay_product_shortcode' );

/**
 * Shortcode: Download-Seite.
 */
function stripepay_download_shortcode() {
    // Prüfen, ob ein Token in der URL vorhanden ist
    if (isset($_GET['stripepay_download']) && $_GET['stripepay_download'] === 'true' && isset($_GET['token'])) {
        $token = sanitize_text_field($_GET['token']);
        
        global $wpdb;
        $purchases_table = $wpdb->prefix . 'stripepay_purchases';
        $products_table = $wpdb->prefix . 'stripepay_products';
        
        // Kauf anhand des Tokens suchen
        $purchase = $wpdb->get_row($wpdb->prepare(
            "SELECT p.*, pr.name as product_name, pr.download_url 
            FROM $purchases_table p
            LEFT JOIN $products_table pr ON p.product_id = pr.id
            WHERE p.download_token = %s AND p.payment_status = 'completed'",
            $token
        ));
        
        if (!$purchase) {
            return '<div class="stripepay-download-error">Ungültiger Download-Link.</div>';
        }
        
        // Prüfen, ob der Download-Link abgelaufen ist
        $expiry_date = strtotime($purchase->download_expiry);
        if (time() > $expiry_date) {
            return '<div class="stripepay-download-error">Der Download-Link ist abgelaufen.</div>';
        }
        
        // Download-Informationen anzeigen
        ob_start();
        ?>
        <div class="stripepay-download-container">
            <h2>Download: <?php echo esc_html($purchase->product_name); ?></h2>
            <p>Vielen Dank für Ihren Kauf!</p>
            <p>Klicken Sie auf den Button unten, um Ihre Datei herunterzuladen:</p>
            <p>
                <a href="<?php echo esc_url($purchase->download_url); ?>" class="stripepay-download-button" download>
                    Jetzt herunterladen
                </a>
            </p>
            <p><small>Dieser Link ist gültig bis: <?php echo date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $expiry_date); ?></small></p>
        </div>
        <style>
            .stripepay-download-container {
                max-width: 600px;
                margin: 20px auto;
                padding: 20px;
                border: 1px solid #e0e0e0;
                border-radius: 4px;
                background-color: #f9f9f9;
                text-align: center;
            }
            .stripepay-download-button {
                display: inline-block;
                background-color: #007bff;
                color: white;
                padding: 10px 20px;
                border-radius: 4px;
                text-decoration: none;
                font-weight: bold;
                margin: 20px 0;
            }
            .stripepay-download-button:hover {
                background-color: #0056b3;
                color: white;
            }
            .stripepay-download-error {
                max-width: 600px;
                margin: 20px auto;
                padding: 20px;
                border: 1px solid #fa755a;
                border-radius: 4px;
                background-color: #fff0f0;
                color: #fa755a;
                text-align: center;
            }
        </style>
        <?php
        return ob_get_clean();
    } else {
        // Keine Token-Parameter in der URL
        return '<div class="stripepay-download-error">Kein gültiger Download-Link gefunden.</div>';
    }
}
add_shortcode('stripepay_download', 'stripepay_download_shortcode');

/**
 * Shortcode: Produkt-Grid.
 */
function stripepay_products_grid_shortcode( $atts ) {
    global $wpdb;
    $products_table = $wpdb->prefix . 'stripepay_products';
    $products = $wpdb->get_results( "SELECT * FROM $products_table" );
    
    
	wp_enqueue_script(
    'imagesloaded',
    'https://unpkg.com/imagesloaded@5/imagesloaded.pkgd.min.js',
    array(),
    null,
    true
);
	ob_start();
    ?>
    <div class="stripepay-products-grid">
        
        <ul class="nav nav-pills isotope-filter" data-sort-id="isotope-list" data-option-key="filter">
            <li data-option-value="*" class="active"><a href="#">Alle anzeigen</a></li>
            <?php
            $all_categories = array();
            if ( $products ) {
                foreach ( $products as $product ) {
                    $cats = explode( ',', $product->categories );
                    foreach ( $cats as $cat ) {
                        $cat = trim( $cat );
                        if ( $cat && ! in_array( $cat, $all_categories ) ) {
                            $all_categories[] = $cat;
                        }
                    }
                }
            }
            foreach ( $all_categories as $cat ) {
                $class_name = strtolower( trim( $cat ) );
                echo '<li data-option-value=".' . esc_attr( $class_name ) . '"><a href="#">' . esc_html( $cat ) . '</a></li>';
            }?>
        </ul>
        <div class="row" style="display: flex; flex-wrap: wrap; width: 100%;">
            <ul class="sort-destination isotope" data-sort-id="isotope-list" style="display: flex; flex-wrap: wrap; width: 100%; height: auto !important; position: static !important;">
                <?php
                if ( $products ) {
                    foreach ( $products as $product ) {
                        $cats = explode( ',', $product->categories );
                        $cat_classes = '';
                        foreach ( $cats as $cat ) {
                            $cat_classes .= ' ' . strtolower( trim( $cat ) );
                        }
                        ?>
                        <li style="list-style: none;" class="isotope-item col-sm-6 col-md-3<?php echo esc_attr( $cat_classes ); ?>">
                                <figure>
                                    <?php 
                                    $product_slug = sanitize_title($product->name);
                                    $product_url = home_url("/product/{$product_slug}-{$product->id}/");
                                    ?>
                                    <a href="<?php echo esc_url($product_url); ?>"><img width="260" height="170" class="img-responsive img-rounded" src="<?php echo esc_url( $product->image ); ?>" alt="<?php echo esc_attr( $product->name ); ?>"></a>
                                </figure>
                        </li>
                    <?php
                    }
                }
                ?>
            </ul>
        </div>
    </div>
    <style>
    .nav {
    padding-left: 0;
    margin-bottom: 0;
    list-style: none;
    display: flex;
    flex-wrap: wrap;
    }

    .nav > li {
    margin-right: 10px;
    }

    .nav > li > a {
    display: block;
    padding: 8px 16px;
    text-decoration: none;
    border-radius: 4px;
    background-color: #f8f9fa;
    color: #007bff;
    }

    .nav > li.active > a,
    .nav > li > a:hover {
    background-color: #007bff;
    color: white;
    }

    .img-rounded {
    border-radius: 6px;
    }

    .img-responsive {
  	display:block;
  	max-width:100%;
  	height:auto
	}

    .row {
        margin-left: -15px;
        margin-right: -15px;
        width: 100%;
    }

    .sort-destination {
        display: flex;
        flex-wrap: wrap;
        width: 100%;
        position: relative !important;
        height: auto !important;
    }

    .isotope-item {
        position: relative !important;
        left: auto !important;
        top: auto !important;
        width: 25% !important;
        padding: 15px;
        box-sizing: border-box;
        transform: none !important;
    }

    .col-sm-6, .col-md-3 {
    	padding-left: 15px;
    	padding-right: 15px;
    	box-sizing: border-box;
    }

    .col-sm-6 {
    	width: 100%;
    }

    @media (max-width: 991px) {
        .isotope-item {
            width: 33.333% !important;
        }
    }

    @media (max-width: 767px) {
        .isotope-item {
            width: 50% !important;
        }
    }

    @media (max-width: 480px) {
        .isotope-item {
            width: 100% !important;
        }
    }

    .btn {
    display: inline-block;
    padding: 6px 12px;
    font-size: 14px;
    text-align: center;
    border-radius: 4px;
    text-decoration: none;
    color: #fff;
    background-color: #007bff;
    border: none;
    }

    .btn:hover {
    background-color: #0056b3;
    }

    .btn-icon i {
    margin-right: 6px;
    }

    .item-hover {
    position: relative;
    display: block;
    overflow: hidden;
    }

    .item-hover .overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0,123,255,0.6);
    opacity: 0;
    transition: opacity 0.3s ease;
    }

    .item-hover:hover .overlay {
    opacity: 1;
    }

    .item-hover .inner {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    text-align: center;
    color: white;
    opacity: 0;
    transition: opacity 0.3s ease;
    }

    .item-hover:hover .inner {
    opacity: 1;
    }

    .block {
    display: block;
    }

    .fsize20 {
    font-size: 20px;
	}

   
    .button-container {
    	text-align: center;
    	margin-top: 15px;
	}
    </style>
    <script>
document.addEventListener("DOMContentLoaded", function () {
  var grid = document.querySelector('.isotope');
  if (!grid) return;

  // Entferne alle inline styles, die von Isotope gesetzt wurden
  var items = grid.querySelectorAll('.isotope-item');
  items.forEach(function(item) {
    item.style.position = 'relative';
    item.style.left = 'auto';
    item.style.top = 'auto';
    item.style.transform = 'none';
  });

  // Setze die Höhe des Containers zurück
  grid.style.height = 'auto';

  imagesLoaded(grid, function () {
    var iso = new Isotope(grid, {
      itemSelector: '.isotope-item',
      layoutMode: 'fitRows',
      fitRows: {
        gutter: 0
      },
      // Deaktiviere die Positionierung durch Isotope
      transitionDuration: 0
    });

    // Überschreibe die Positionierung nach der Initialisierung
    setTimeout(function() {
      items.forEach(function(item) {
        item.style.position = 'relative';
        item.style.left = 'auto';
        item.style.top = 'auto';
        item.style.transform = 'none';
      });
      grid.style.height = 'auto';
    }, 100);

    var filtersElem = document.querySelector('.isotope-filter');
    if (!filtersElem) return;

    filtersElem.addEventListener('click', function (event) {
      if (!event.target.matches('a')) return;

      event.preventDefault();
      var filterValue = event.target.parentNode.getAttribute('data-option-value');
      
      // Manuelles Filtern statt Isotope
      items.forEach(function(item) {
        if (filterValue === '*' || item.classList.contains(filterValue.substring(1))) {
          item.style.display = '';
        } else {
          item.style.display = 'none';
        }
      });

      // active class toggling
      filtersElem.querySelectorAll('li').forEach(function(el) {
        el.classList.remove('active');
      });
      event.target.parentNode.classList.add('active');
    });
  });
});
</script>
    <?php
    return ob_get_clean();
}
add_shortcode( 'stripepay_products_grid', 'stripepay_products_grid_shortcode' );

/**
 * Shortcode: Neues Produkt-Grid mit CSS Grid und Vanilla JS.
 */
function stripepay_products_grid_new_shortcode( $atts ) {
    global $wpdb;
    $products_table = $wpdb->prefix . 'stripepay_products';
    $products = $wpdb->get_results( "SELECT * FROM $products_table" );
    
    // ImagesLoaded für bessere Bildladung
    wp_enqueue_script(
        'imagesloaded',
        'https://unpkg.com/imagesloaded@5/imagesloaded.pkgd.min.js',
        array(),
        null,
        true
    );
    
    ob_start();
    ?>
    <div class="stripepay-products-grid-new">
        <!-- Filter-Navigation -->
        <ul class="stripepay-filter">
            <li data-filter="all" class="active"><a href="#">Alle anzeigen</a></li>
            <?php
            // Kategorien generieren
            $all_categories = array();
            if ( $products ) {
                foreach ( $products as $product ) {
                    $cats = explode( ',', $product->categories );
                    foreach ( $cats as $cat ) {
                        $cat = trim( $cat );
                        if ( $cat && ! in_array( $cat, $all_categories ) ) {
                            $all_categories[] = $cat;
                        }
                    }
                }
            }
            foreach ( $all_categories as $cat ) {
                $class_name = strtolower( trim( $cat ) );
                echo '<li data-filter="' . esc_attr( $class_name ) . '"><a href="#">' . esc_html( $cat ) . '</a></li>';
            }
            ?>
        </ul>
        
        <!-- Produkt-Grid -->
        <div class="stripepay-grid">
            <?php
            if ( $products ) {
                foreach ( $products as $product ) {
                    $cats = explode( ',', $product->categories );
                    $cat_classes = '';
                    foreach ( $cats as $cat ) {
                        $cat_classes .= ' ' . strtolower( trim( $cat ) );
                    }
                    ?>
                    <div class="stripepay-grid-item<?php echo esc_attr( $cat_classes ); ?>">
                        <?php 
                        $product_slug = sanitize_title($product->name);
                        $product_url = home_url("/product/{$product_slug}-{$product->id}/");
                        ?>
                        <a href="<?php echo esc_url($product_url); ?>">
                            <img class="stripepay-product-image" src="<?php echo esc_url( $product->image ); ?>" alt="<?php echo esc_attr( $product->name ); ?>">
                        </a>
                    </div>
                    <?php
                }
            }
            ?>
        </div>
    </div>
    
    <style>
    /* CSS für das neue Grid */
    .stripepay-products-grid-new {
        width: 100%;
        max-width: 100%;
        margin-bottom: 30px;
    }
    
    /* Filter-Styling */
    .stripepay-filter {
        display: flex;
        flex-wrap: wrap;
        list-style: none;
        padding: 0;
        margin: 0 0 20px 0;
    }
    
    .stripepay-filter li {
        margin-right: 10px;
        margin-bottom: 10px;
    }
    
    .stripepay-filter li a {
        display: block;
        padding: 8px 16px;
        text-decoration: none;
        border-radius: 4px;
        background-color: #f8f9fa;
        color: #007bff;
        transition: all 0.3s ease;
    }
    
    .stripepay-filter li.active a,
    .stripepay-filter li a:hover {
        background-color: #007bff;
        color: white;
    }
    
    /* Grid-Layout */
    .stripepay-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 20px;
    }
    
    .stripepay-grid-item {
        transition: opacity 0.3s ease, transform 0.3s ease;
    }
    
    .stripepay-grid-item.hidden {
        display: none;
    }
    
    .stripepay-product-image {
        width: 100%;
        height: auto;
        border-radius: 6px;
        display: block;
    }
    
    /* Responsive Design */
    @media (max-width: 992px) {
        .stripepay-grid {
            grid-template-columns: repeat(3, 1fr);
        }
    }
    
    @media (max-width: 768px) {
        .stripepay-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }
    
    @media (max-width: 480px) {
        .stripepay-grid {
            grid-template-columns: 1fr;
        }
    }
    </style>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Filter-Funktionalität
        const filterButtons = document.querySelectorAll('.stripepay-filter li');
        const gridItems = document.querySelectorAll('.stripepay-grid-item');
        
        // Bilder laden lassen, bevor wir das Grid initialisieren
        imagesLoaded(document.querySelector('.stripepay-grid'), function() {
            // Alle Elemente initial sichtbar machen
            gridItems.forEach(item => {
                item.style.opacity = '1';
            });
            
            // Filter-Funktionalität
            filterButtons.forEach(button => {
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    
                    // Aktiven Button markieren
                    filterButtons.forEach(btn => btn.classList.remove('active'));
                    this.classList.add('active');
                    
                    const filter = this.getAttribute('data-filter');
                    
                    // Elemente filtern mit Animation
                    gridItems.forEach(item => {
                        if (filter === 'all') {
                            // Einblenden
                            item.style.opacity = '0';
                            setTimeout(() => {
                                item.classList.remove('hidden');
                                item.style.opacity = '1';
                            }, 300);
                        } else {
                            if (item.classList.contains(filter)) {
                                // Einblenden
                                item.style.opacity = '0';
                                setTimeout(() => {
                                    item.classList.remove('hidden');
                                    item.style.opacity = '1';
                                }, 300);
                            } else {
                                // Ausblenden
                                item.style.opacity = '0';
                                setTimeout(() => {
                                    item.classList.add('hidden');
                                }, 300);
                            }
                        }
                    });
                });
            });
        });
    });
    </script>
    <?php
    return ob_get_clean();
}
add_shortcode( 'stripepay_products_grid_new', 'stripepay_products_grid_new_shortcode' );
