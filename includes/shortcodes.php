<?php
/**
 * Shortcode: Einzelproduktanzeige.
 */
function stripepay_product_shortcode( $atts ) {
    global $wpdb;
    $atts = shortcode_atts( array(
        'id' => 0,
    ), $atts, 'stripepay_product' );
    
    $product_id = intval( $atts['id'] );
    if ( ! $product_id ) {
        return 'Ungültiges Produkt.';
    }
    
    $products_table = $wpdb->prefix . 'stripepay_products';
    $product = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $products_table WHERE id = %d", $product_id ) );
    if ( ! $product ) {
        return 'Produkt nicht gefunden.';
    }
    
    ob_start();
    ?>
    <div class="stripepay-product">
        <h2><?php echo esc_html( $product->name ); ?></h2>
        <p>Preis: <?php echo esc_html( $product->price ); ?> Cent</p>
        <p><?php echo esc_html( $product->kurztext ); ?></p>
        <p><?php echo esc_html( $product->langtext ); ?></p>
        <form method="post" action="">
            <label for="stripepay_email">Email:</label>
            <input type="email" name="stripepay_email" id="stripepay_email" required>
            <button type="submit">Kaufen</button>
        </form>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode( 'stripepay_product', 'stripepay_product_shortcode' );

/**
 * Shortcode: Produkt-Grid.
 */
function stripepay_products_grid_shortcode( $atts ) {
    global $wpdb;
    $products_table = $wpdb->prefix . 'stripepay_products';
    $products = $wpdb->get_results( "SELECT * FROM $products_table" );
    
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
            foreach ( $all_categories as $cat ) { ?>
                <li data-option-value=".<?php echo esc_attr( strtolower( $cat ) ); ?>"><a href="#"><?php echo esc_html( $cat ); ?></a></li>
            <?php } ?>
        </ul>
        <div class="row">
            <ul class="sort-destination isotope" data-sort-id="isotope-list">
                <?php
                if ( $products ) {
                    foreach ( $products as $product ) {
                        $cats = explode( ',', $product->categories );
                        $cat_classes = '';
                        foreach ( $cats as $cat ) {
                            $cat_classes .= ' ' . strtolower( trim( $cat ) );
                        }
                        ?>
                        <li class="isotope-item col-sm-6 col-md-3<?php echo esc_attr( $cat_classes ); ?>">
                            <div class="item-box">
                                <figure>
                                    <a target="_blank" class="item-hover" href="#">
                                        <span class="overlay color2"></span>
                                        <span class="inner">
                                            <span class="block fa fa-plus fsize20"></span>
                                            <strong>Details</strong>
                                        </span>
                                    </a>
                                    <img class="img-responsive img-rounded" src="<?php echo esc_url( $product->image ); ?>" alt="<?php echo esc_attr( $product->name ); ?>">
                                </figure>
                                <div class="button-container">
                                    <a href="<?php echo esc_url( add_query_arg( 'product_id', $product->id, home_url( '/product' ) ) ); ?>" class="btn btn-icon">
                                        <i class="fas fa-book"></i> Produktseite
                                    </a>
                                </div>
                            </div>
                        </li>
                    <?php
                    }
                }
                ?>
            </ul>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode( 'stripepay_products_grid', 'stripepay_products_grid_shortcode' );
