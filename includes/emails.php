<?php
/**
 * E-Mail-Funktionen für WP StripePay.
 */

// Sicherheitscheck
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Sendet eine E-Mail mit dem Download-Link an den Kunden.
 *
 * @param object $purchase Kauf-Objekt aus der Datenbank
 * @return bool
 */
function stripepay_send_download_email($purchase) {
    global $wpdb;
    $products_table = $wpdb->prefix . 'stripepay_products';

    // Produkt aus der Datenbank holen
    $product = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $products_table WHERE id = %d",
        $purchase->product_id
    ));

    if (!$product) {
        return false;
    }

    $to = $purchase->email;
    $subject = 'Ihr Download für ' . $product->name;
    
    // Download-URL generieren
    $download_url = add_query_arg([
        'stripepay_download' => 'true',
        'token' => $purchase->download_token,
    ], home_url('/download/'));

    // E-Mail-Inhalt
    $message = '
    <html>
    <head>
        <title>Ihr Download für ' . esc_html($product->name) . '</title>
    </head>
    <body>
        <p>Vielen Dank für Ihren Kauf von <strong>' . esc_html($product->name) . '</strong>.</p>
        <p>Hier ist Ihr Download-Link:</p>
        <p><a href="' . esc_url($download_url) . '">Jetzt herunterladen</a></p>
        <p>Dieser Link ist 7 Tage gültig.</p>
        <p>Bei Fragen stehen wir Ihnen gerne zur Verfügung.</p>
        <p>Mit freundlichen Grüßen,<br>Ihr Team</p>
    </body>
    </html>
    ';

    // E-Mail-Header
    $headers = [
        'Content-Type: text/html; charset=UTF-8',
        'From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>',
    ];

    // E-Mail senden
    $sent = wp_mail($to, $subject, $message, $headers);

    return $sent;
}

/**
 * Verarbeitet Download-Anfragen.
 */
function stripepay_process_download_request() {
    if (!isset($_GET['stripepay_download']) || $_GET['stripepay_download'] !== 'true' || !isset($_GET['token'])) {
        return;
    }

    $token = sanitize_text_field($_GET['token']);

    global $wpdb;
    $purchases_table = $wpdb->prefix . 'stripepay_purchases';
    $products_table = $wpdb->prefix . 'stripepay_products';

    // Kauf anhand des Tokens suchen
    $purchase = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $purchases_table WHERE download_token = %s AND payment_status = 'completed'",
        $token
    ));

    if (!$purchase) {
        wp_die('Ungültiger Download-Link.');
    }

    // Prüfen, ob der Download-Link abgelaufen ist
    $expiry_date = strtotime($purchase->download_expiry);
    if (time() > $expiry_date) {
        wp_die('Der Download-Link ist abgelaufen.');
    }

    // Produkt aus der Datenbank holen
    $product = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $products_table WHERE id = %d",
        $purchase->product_id
    ));

    if (!$product || !$product->download_url) {
        wp_die('Download nicht verfügbar.');
    }

    // Download-Zähler erhöhen
    $wpdb->update(
        $purchases_table,
        ['download_count' => $purchase->download_count + 1],
        ['id' => $purchase->id]
    );

    // Datei herunterladen
    $file_url = $product->download_url;
    $file_path = '';

    // Prüfen, ob es sich um eine lokale Datei handelt
    if (strpos($file_url, home_url()) === 0) {
        $file_path = str_replace(home_url('/'), ABSPATH, $file_url);
    }

    if ($file_path && file_exists($file_path)) {
        // Lokale Datei herunterladen
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($file_path) . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($file_path));
        flush();
        readfile($file_path);
        exit;
    } else {
        // Externe Datei - Weiterleitung
        wp_redirect($file_url);
        exit;
    }
}
add_action('init', 'stripepay_process_download_request');
