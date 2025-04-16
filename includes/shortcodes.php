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
        <div class="row">
            <ul class="sort-destination isotope" data-sort-id="isotope-list" style="position: relative; overflow: hidden; height: 908px;">
                <?php
                if ( $products ) {
                    foreach ( $products as $product ) {
                        $cats = explode( ',', $product->categories );
                        $cat_classes = '';
                        foreach ( $cats as $cat ) {
                            $cat_classes .= ' ' . strtolower( trim( $cat ) );
                        }
                        ?>
                        <li style="list-style: none; position: absolute;" class="isotope-item col-sm-6 col-md-3<?php echo esc_attr( $cat_classes ); ?>">
                                <figure>
                                    <a href="<?php echo esc_html( $product->id ); ?>"><img width="260" height="170" class="img-responsive img-rounded" src="<?php echo esc_url( $product->image ); ?>" alt="<?php echo esc_attr( $product->name ); ?>"></a>
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

 

    .col-sm-6, .col-md-3 {
    	padding-left: 15px;
    	padding-right: 15px;
    	box-sizing: border-box;
    }

    .col-sm-6 {
    	width: 100%;
    }

    @media (min-width: 768px) {
    .col-sm-6 {
        width: 50%;
    }
    }

    @media (min-width: 992px) {
    .col-md-3 {
        width: 25%;
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

  imagesLoaded(grid, function () {
    var iso = new Isotope(grid, {
      itemSelector: '.isotope-item',
      layoutMode: 'fitRows'
    });

    var filtersElem = document.querySelector('.isotope-filter');
    if (!filtersElem) return;

    filtersElem.addEventListener('click', function (event) {
      if (!event.target.matches('a')) return;

      event.preventDefault();
      var filterValue = event.target.parentNode.getAttribute('data-option-value');
      iso.arrange({ filter: filterValue });

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
