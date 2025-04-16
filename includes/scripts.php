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
    
    // Überprüfen, ob die Publishable Keys konfiguriert sind
    if (empty($publishable_key)) {
        error_log('Stripe Publishable Key ist nicht konfiguriert. Bitte konfigurieren Sie die Stripe API-Keys in den Plugin-Einstellungen.');
    }
    
    // Absolute URL zum AJAX-Endpunkt
    $ajax_url = admin_url('admin-ajax.php');
    
    // Nonce für die Sicherheit
    $nonce = wp_create_nonce('stripepay_payment');
    
    error_log('AJAX URL: ' . $ajax_url);
    error_log('Nonce: ' . $nonce);
    error_log('Publishable Key: ' . $publishable_key);
    
    wp_localize_script(
        'stripepay-elements-js',
        'stripePayData',
        array(
            'ajaxUrl' => $ajax_url,
            'nonce' => $nonce,
            'publishableKey' => $publishable_key,
            'cookiePath' => COOKIEPATH,
            'cookieDomain' => COOKIE_DOMAIN,
            'siteUrl' => get_site_url(),
            'homeUrl' => home_url(),
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
