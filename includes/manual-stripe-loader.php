<?php
/**
 * Manuelle Stripe-Bibliothek Loader für WP StripePay.
 * Diese Datei wird verwendet, wenn Composer nicht verfügbar ist.
 */

// Sicherheitscheck
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Lädt die Stripe PHP-Bibliothek manuell.
 */
function stripepay_manual_load_stripe() {
    // Pfad zur manuell installierten Stripe-Bibliothek
    $stripe_path = plugin_dir_path(dirname(__FILE__)) . 'vendor/stripe/stripe-php/init.php';
    
    // Prüfen, ob die Datei existiert
    if (file_exists($stripe_path)) {
        require_once $stripe_path;
        return true;
    }
    
    return false;
}
