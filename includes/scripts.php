<?php
/**
 * Skripte und Styles einbinden (Isotope.js und ggf. eigene Skripte)
 */
function stripepay_enqueue_scripts() {
    wp_enqueue_script( 'isotope-js', 'https://unpkg.com/isotope-layout@3/dist/isotope.pkgd.min.js', array( 'jquery' ), null, true );
}
add_action( 'wp_enqueue_scripts', 'stripepay_enqueue_scripts' );
