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
    // Verwende eine statische Variable, um zu verfolgen, welche E-Mails bereits gesendet wurden
    static $sent_emails = [];
    
    // Erstelle einen eindeutigen Schlüssel für diese E-Mail
    $email_key = $purchase->id . '-' . $purchase->payment_intent_id;
    
    // Wenn diese E-Mail bereits gesendet wurde, nicht erneut senden
    if (isset($sent_emails[$email_key])) {
        error_log('E-Mail wurde bereits gesendet für Kauf ID: ' . $purchase->id . '. Überspringe...');
        return true;
    }
    
    global $wpdb;
    $products_table = $wpdb->prefix . 'stripepay_products';

    // Debug-Informationen
    error_log('stripepay_send_download_email wurde aufgerufen für Kauf ID: ' . $purchase->id);
    error_log('E-Mail-Adresse: ' . $purchase->email);

    // Produkt aus der Datenbank holen
    $product = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $products_table WHERE id = %d",
        $purchase->product_id
    ));

    if (!$product) {
        error_log('Produkt nicht gefunden für ID: ' . $purchase->product_id);
        return false;
    }

    $to = $purchase->email;
    $subject = 'Ihr Download für ' . $product->name;
    
    // Download-URL generieren
    // Prüfen, ob eine Seite mit dem Slug 'download' existiert
    $download_page = get_page_by_path('download');
    
    if ($download_page) {
        $download_url = add_query_arg([
            'stripepay_download' => 'true',
            'token' => $purchase->download_token,
        ], get_permalink($download_page->ID));
    } else {
        // Fallback: Verwende die Home-URL
        $download_url = add_query_arg([
            'stripepay_download' => 'true',
            'token' => $purchase->download_token,
        ], home_url());
        
        error_log('Keine Download-Seite gefunden. Verwende Home-URL: ' . $download_url);
    }

    // E-Mail-Inhalt mit direktem Link (ohne Tracking)
    // Verwende eine Tabelle für den Download-Link, um Tracking zu erschweren
    $message = '
    <html>
    <head>
        <title>Ihr Download für ' . esc_html($product->name) . '</title>
        <meta name="x-apple-disable-tracking" content="yes">
        <meta name="x-no-tracking" content="yes">
    </head>
    <body>
        <p>Vielen Dank für Ihren Kauf von <strong>' . esc_html($product->name) . '</strong>.</p>
        <p>Hier ist Ihr Download-Link:</p>
        <table width="100%" border="0" cellspacing="0" cellpadding="0">
            <tr>
                <td>
                    <table border="0" cellspacing="0" cellpadding="0">
                        <tr>
                            <td bgcolor="#4CAF50" style="padding: 12px 18px 12px 18px; border-radius:3px" align="center">
                                <a href="' . esc_url($download_url) . '" target="_blank" style="font-size: 16px; font-family: Helvetica, Arial, sans-serif; font-weight: normal; color: #ffffff; text-decoration: none; display: inline-block;">Jetzt herunterladen</a>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
        <p>Falls der Button nicht funktioniert, kopieren Sie diese URL direkt in Ihren Browser:</p>
        <p style="word-break: break-all; font-family: monospace; background-color: #f5f5f5; padding: 10px; border: 1px solid #ddd;">' . esc_url($download_url) . '</p>
        <p>Dieser Link ist 7 Tage gültig.</p>
        <p>Bei Fragen stehen wir Ihnen gerne zur Verfügung.</p>
        <p>Mit freundlichen Grüßen,<br>Ihr Team</p>
    </body>
    </html>
    ';

    // E-Mail-Header mit No-Track-Anweisungen für verschiedene E-Mail-Dienste
    $headers = [
        'Content-Type: text/html; charset=UTF-8',
        'From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>',
        // Brevo/SendinBlue spezifische Header
        'X-Mailin-Tag: no-rewrite',
        'X-Brevo-Track-Click: 0',
        'X-Brevo-Track-Open: 0',
        // Mailjet spezifische Header
        'X-Mailjet-TrackClick: 0',
        'X-Mailjet-TrackOpen: 0',
        // Amazon SES spezifische Header
        'X-SES-CONFIGURATION-SET: no-tracking',
        // SparkPost spezifische Header
        'X-MSYS-API: {"options": {"click_tracking": false, "open_tracking": false}}',
        // Postmark spezifische Header
        'X-PM-TrackClicks: 0',
        'X-PM-TrackOpens: 0',
        // Allgemeine Header
        'X-No-Track: 1',
        'X-No-Tracking: 1',
        'X-Disable-Tracking: 1'
    ];

    // Entferne alle Hooks, die E-Mails modifizieren könnten
    remove_all_filters('wp_mail_from');
    remove_all_filters('wp_mail_from_name');
    remove_all_filters('wp_mail_content_type');
    remove_all_filters('wp_mail_charset');
    remove_all_filters('wp_mail');
    
    // E-Mail senden
    $sent = wp_mail($to, $subject, $message, $headers);
    
    if ($sent) {
        // Markiere diese E-Mail als gesendet
        $sent_emails[$email_key] = true;
        
        error_log('E-Mail erfolgreich gesendet an: ' . $to);
    } else {
        error_log('Fehler beim Senden der E-Mail an: ' . $to);
        
        // Versuche, den Fehler zu diagnostizieren
        global $phpmailer;
        if (isset($phpmailer) && $phpmailer->ErrorInfo) {
            error_log('PHPMailer-Fehler: ' . $phpmailer->ErrorInfo);
        }
    }

    return $sent;
}

/**
 * Test-Funktion zum Senden einer E-Mail.
 * Diese Funktion kann im Admin-Bereich verwendet werden, um zu testen, ob E-Mails gesendet werden können.
 */
function stripepay_test_email($email) {
    $subject = 'StripePay Test-E-Mail';
    $message = '
    <html>
    <head>
        <title>StripePay Test-E-Mail</title>
    </head>
    <body>
        <p>Dies ist eine Test-E-Mail von StripePay.</p>
        <p>Wenn Sie diese E-Mail erhalten, funktioniert der E-Mail-Versand korrekt.</p>
    </body>
    </html>
    ';
    
    $headers = [
        'Content-Type: text/html; charset=UTF-8',
        'From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>',
    ];
    
    $sent = wp_mail($email, $subject, $message, $headers);
    
    if ($sent) {
        error_log('Test-E-Mail erfolgreich gesendet an: ' . $email);
    } else {
        error_log('Fehler beim Senden der Test-E-Mail an: ' . $email);
        
        // Versuche, den Fehler zu diagnostizieren
        global $phpmailer;
        if (isset($phpmailer) && $phpmailer->ErrorInfo) {
            error_log('PHPMailer-Fehler: ' . $phpmailer->ErrorInfo);
        }
    }
    
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
