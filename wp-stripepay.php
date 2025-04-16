<?php
/*
Plugin Name: WP StripePay
Description: Ein WordPress Plugin zum Verkauf von Büchern und digitalen Inhalten über Stripe. Es beinhaltet Admin-Bereiche für Stripe API Einstellungen, Produkte und Autoren sowie Shortcodes für die Anzeige einzelner Produkte und eines Produkt-Grids.
Version: 1.0.21
Author: CrowdWare
*/

// Sicherheitscheck
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Inkludiere benötigte Dateien
require_once plugin_dir_path( __FILE__ ) . 'includes/activation.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/admin.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/shortcodes.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/scripts.php';

// Aktivierungshook registrieren
register_activation_hook( __FILE__, 'stripepay_activate' );
