<?php
/**
 * Skripte und Styles einbinden (Isotope.js, Stripe.js und eigene Skripte)
 */
function stripepay_enqueue_scripts() {
    // Isotope.js für das Produkt-Grid
    wp_enqueue_script( 'isotope-js', 'https://unpkg.com/isotope-layout@3/dist/isotope.pkgd.min.js', array( 'jquery' ), null, true );
    
    // Stripe.js für die Zahlungsabwicklung
    wp_enqueue_script( 'stripe-js', 'https://js.stripe.com/v3/', array(), null, true );
    
    // Eigenes JavaScript für Stripe Elements
    wp_enqueue_script(
        'stripepay-elements-js',
        plugin_dir_url( dirname( __FILE__ ) ) . 'assets/js/stripe-elements.js',
        array( 'jquery', 'stripe-js' ),
        '1.0.0',
        true
    );
    
    // Daten für das JavaScript bereitstellen
    $live_mode = get_option( 'stripepay_live_mode', false );
    if ( $live_mode ) {
        $publishable_key = get_option( 'stripepay_stripe_live_publishable_key', '' );
    } else {
        $publishable_key = get_option( 'stripepay_stripe_test_publishable_key', '' );
    }
    
    wp_localize_script(
        'stripepay-elements-js',
        'stripePayData',
        array(
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce' => wp_create_nonce( 'stripepay_payment' ),
            'publishableKey' => $publishable_key,
        )
    );
    
    // CSS für Stripe Elements
    wp_enqueue_style(
        'stripepay-elements-css',
        plugin_dir_url( dirname( __FILE__ ) ) . 'assets/css/stripe-elements.css',
        array(),
        '1.0.0'
    );
}
add_action( 'wp_enqueue_scripts', 'stripepay_enqueue_scripts' );
