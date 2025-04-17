<?php
/**
 * Stripe Integration für WP StripePay.
 */

// Sicherheitscheck
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Initialisiert die Stripe API mit dem entsprechenden API-Key.
 */
function stripepay_init_stripe() {
    // Prüfen, ob die Stripe PHP-Bibliothek bereits geladen ist
    if (!class_exists('\Stripe\Stripe')) {
        $stripe_loaded = false;
        
        // Methode 1: Versuchen, über Composer zu laden
        $vendor_path = plugin_dir_path(dirname(__FILE__)) . 'vendor/autoload.php';
        if (file_exists($vendor_path)) {
            require_once $vendor_path;
            $stripe_loaded = true;
            error_log('Stripe PHP-Bibliothek über Composer geladen');
        }
        
        // Methode 2: Versuchen, manuell zu laden
        if (!$stripe_loaded && function_exists('stripepay_manual_load_stripe')) {
            $stripe_loaded = stripepay_manual_load_stripe();
            if ($stripe_loaded) {
                error_log('Stripe PHP-Bibliothek manuell geladen');
            }
        }
        
        // Wenn die Bibliothek nicht geladen werden konnte
        if (!$stripe_loaded) {
            error_log('Stripe PHP-Bibliothek nicht gefunden. Weder Composer noch manuelle Installation gefunden.');
            throw new Exception('Die Stripe PHP-Bibliothek wurde nicht gefunden. Bitte installieren Sie die Bibliothek entweder über Composer oder manuell.');
        }
    }

    // API-Key basierend auf dem Modus (Live oder Test) setzen
    $live_mode = get_option('stripepay_live_mode', false);
    if ($live_mode) {
        $api_key = get_option('stripepay_stripe_live_key', '');
    } else {
        $api_key = get_option('stripepay_stripe_test_key', '');
    }

    // Prüfen, ob ein API-Key konfiguriert ist
    if (empty($api_key)) {
        error_log('Kein Stripe API-Key konfiguriert');
        throw new Exception('Kein Stripe API-Key konfiguriert. Bitte geben Sie Ihre Stripe API-Keys in den Plugin-Einstellungen ein.');
    }

    // Stripe API-Key setzen
    \Stripe\Stripe::setApiKey($api_key);
    
    error_log('Stripe wurde erfolgreich initialisiert. Modus: ' . ($live_mode ? 'Live' : 'Test'));
}

/**
 * Erstellt einen Stripe Payment Intent für eine Zahlung.
 *
 * @param int $amount Betrag in Cent
 * @param string $currency Währung (default: EUR)
 * @param string $description Beschreibung der Zahlung
 * @param array $metadata Zusätzliche Metadaten
 * @return \Stripe\PaymentIntent|WP_Error
 */
function stripepay_create_payment_intent($amount, $currency = 'eur', $description = '', $metadata = []) {
    try {
        stripepay_init_stripe();

        $payment_intent = \Stripe\PaymentIntent::create([
            'amount' => $amount,
            'currency' => $currency,
            'description' => $description,
            'metadata' => $metadata,
            'payment_method_types' => ['card'],
        ]);

        return $payment_intent;
    } catch (\Stripe\Exception\ApiErrorException $e) {
        return new WP_Error('stripe_error', $e->getMessage());
    }
}

/**
 * Überprüft den Status eines Payment Intents.
 *
 * @param string $payment_intent_id ID des Payment Intents
 * @return \Stripe\PaymentIntent|WP_Error
 */
function stripepay_retrieve_payment_intent($payment_intent_id) {
    try {
        stripepay_init_stripe();

        $payment_intent = \Stripe\PaymentIntent::retrieve($payment_intent_id);
        return $payment_intent;
    } catch (\Stripe\Exception\ApiErrorException $e) {
        return new WP_Error('stripe_error', $e->getMessage());
    }
}

/**
 * Verarbeitet einen erfolgreichen Stripe Webhook Event.
 *
 * @param \Stripe\Event $event Stripe Event
 * @return bool
 */
function stripepay_process_webhook_event($event) {
    global $wpdb;
    $purchases_table = $wpdb->prefix . 'stripepay_purchases';

    // Nur payment_intent.succeeded Events verarbeiten
    if ($event->type !== 'payment_intent.succeeded') {
        return false;
    }

    $payment_intent = $event->data->object;
    $payment_intent_id = $payment_intent->id;

    // Kauf in der Datenbank aktualisieren
    $wpdb->update(
        $purchases_table,
        [
            'payment_status' => 'completed',
            'download_token' => stripepay_generate_download_token(),
            'download_expiry' => date('Y-m-d H:i:s', strtotime('+7 days')),
        ],
        ['payment_intent_id' => $payment_intent_id]
    );

    // E-Mail mit Download-Link senden
    $purchase = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $purchases_table WHERE payment_intent_id = %s",
        $payment_intent_id
    ));

    if ($purchase) {
        stripepay_send_download_email($purchase);
        return true;
    }

    return false;
}

/**
 * Generiert ein sicheres Token für den Download.
 *
 * @return string
 */
function stripepay_generate_download_token() {
    return bin2hex(random_bytes(32));
}

/**
 * AJAX-Handler für die Stripe-Zahlung.
 */
function stripepay_ajax_process_payment() {
    // Aktiviere die Fehlerberichterstattung für Debugging
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
    
    try {
        // Debug-Informationen
        error_log('stripepay_ajax_process_payment wurde aufgerufen');
        error_log('POST-Daten: ' . print_r($_POST, true));
        
        // Prüfen, ob die Stripe PHP-Bibliothek installiert ist
        if (!file_exists(plugin_dir_path(dirname(__FILE__)) . 'vendor/autoload.php')) {
            error_log('Stripe PHP-Bibliothek nicht gefunden');
            wp_send_json_error(['message' => 'Die Stripe PHP-Bibliothek wurde nicht gefunden. Bitte führen Sie "composer install" im Plugin-Verzeichnis aus.']);
            exit;
        }
        
        // Nonce überprüfen (mehrere Möglichkeiten)
        $nonce_verified = false;
        
        // Methode 1: Standard-Nonce-Parameter
        if (isset($_POST['nonce']) && wp_verify_nonce($_POST['nonce'], 'stripepay_payment')) {
            $nonce_verified = true;
            error_log('Nonce-Überprüfung erfolgreich (Methode 1)');
        }
        
        // Methode 2: Security-Parameter
        if (!$nonce_verified && isset($_POST['security']) && wp_verify_nonce($_POST['security'], 'stripepay_payment')) {
            $nonce_verified = true;
            error_log('Nonce-Überprüfung erfolgreich (Methode 2)');
        }
        
        // Methode 3: X-WP-Nonce Header
        if (!$nonce_verified && isset($_SERVER['HTTP_X_WP_NONCE'])) {
            $nonce = $_SERVER['HTTP_X_WP_NONCE'];
            if (wp_verify_nonce($nonce, 'stripepay_payment')) {
                $nonce_verified = true;
                error_log('Nonce-Überprüfung erfolgreich (Methode 3)');
            }
        }
        
        if (!$nonce_verified) {
            error_log('Nonce-Überprüfung fehlgeschlagen');
            wp_send_json_error(['message' => 'Sicherheitsüberprüfung fehlgeschlagen.']);
            exit;
        }

        // Daten validieren
        $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
        $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
        $payment_method_id = isset($_POST['payment_method_id']) ? sanitize_text_field($_POST['payment_method_id']) : '';

        error_log('Validierte Daten: product_id=' . $product_id . ', email=' . $email . ', payment_method_id=' . $payment_method_id);

        if (!$product_id || !$email || !$payment_method_id || !is_email($email)) {
            error_log('Datenvalidierung fehlgeschlagen');
            wp_send_json_error(['message' => 'Ungültige Daten.']);
            exit;
        }

        global $wpdb;
        $products_table = $wpdb->prefix . 'stripepay_products';
        $purchases_table = $wpdb->prefix . 'stripepay_purchases';

        // Prüfen, ob die Tabellen existieren
        if ($wpdb->get_var("SHOW TABLES LIKE '$products_table'") != $products_table) {
            error_log('Produkt-Tabelle existiert nicht');
            wp_send_json_error(['message' => 'Die Datenbanktabelle für Produkte existiert nicht. Bitte deaktivieren und reaktivieren Sie das Plugin.']);
            exit;
        }
        
        if ($wpdb->get_var("SHOW TABLES LIKE '$purchases_table'") != $purchases_table) {
            error_log('Käufe-Tabelle existiert nicht');
            wp_send_json_error(['message' => 'Die Datenbanktabelle für Käufe existiert nicht. Bitte deaktivieren und reaktivieren Sie das Plugin.']);
            exit;
        }

        // Produkt aus der Datenbank holen
        $product = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $products_table WHERE id = %d",
            $product_id
        ));

        if (!$product) {
            error_log('Produkt nicht gefunden: ' . $product_id);
            wp_send_json_error(['message' => 'Produkt nicht gefunden.']);
            exit;
        }

        // Stripe initialisieren
        try {
            // Versuchen, die Stripe-Bibliothek zu laden
            if (!class_exists('\Stripe\Stripe')) {
                // Methode 1: Versuchen, über Composer zu laden
                $vendor_path = plugin_dir_path(dirname(__FILE__)) . 'vendor/autoload.php';
                if (file_exists($vendor_path)) {
                    require_once $vendor_path;
                    error_log('Stripe PHP-Bibliothek über Composer geladen');
                } 
                // Methode 2: Versuchen, manuell zu laden
                else if (function_exists('stripepay_manual_load_stripe')) {
                    $loaded = stripepay_manual_load_stripe();
                    if ($loaded) {
                        error_log('Stripe PHP-Bibliothek manuell geladen');
                    } else {
                        error_log('Stripe PHP-Bibliothek konnte nicht manuell geladen werden');
                        wp_send_json_error(['message' => 'Die Stripe PHP-Bibliothek konnte nicht geladen werden. Bitte installieren Sie die Bibliothek entweder über Composer oder manuell.']);
                        exit;
                    }
                } else {
                    error_log('Stripe PHP-Bibliothek nicht gefunden');
                    wp_send_json_error(['message' => 'Die Stripe PHP-Bibliothek wurde nicht gefunden. Bitte installieren Sie die Bibliothek entweder über Composer oder manuell.']);
                    exit;
                }
            }
            
            // API-Key basierend auf dem Modus (Live oder Test) setzen
            $live_mode = get_option('stripepay_live_mode', false);
            if ($live_mode) {
                $api_key = get_option('stripepay_stripe_live_key', '');
            } else {
                $api_key = get_option('stripepay_stripe_test_key', '');
            }
            
            // Prüfen, ob ein API-Key konfiguriert ist
            if (empty($api_key)) {
                error_log('Kein Stripe API-Key konfiguriert');
                wp_send_json_error(['message' => 'Kein Stripe API-Key konfiguriert. Bitte geben Sie Ihre Stripe API-Keys in den Plugin-Einstellungen ein.']);
                exit;
            }
            
            // Stripe API-Key setzen
            \Stripe\Stripe::setApiKey($api_key);
            error_log('Stripe wurde erfolgreich initialisiert. Modus: ' . ($live_mode ? 'Live' : 'Test'));
        } catch (Exception $e) {
            error_log('Fehler bei der Stripe-Initialisierung: ' . $e->getMessage());
            wp_send_json_error(['message' => 'Fehler bei der Stripe-Initialisierung: ' . $e->getMessage()]);
            exit;
        }

        // Payment Intent erstellen
        try {
            $payment_intent = \Stripe\PaymentIntent::create([
                'amount' => $product->price,
                'currency' => 'eur',
                'description' => 'Kauf von ' . $product->name,
                'metadata' => [
                    'product_id' => $product_id,
                    'email' => $email,
                ],
                'payment_method_types' => ['card'],
            ]);
            error_log('Payment Intent erstellt: ' . $payment_intent->id);
        } catch (\Stripe\Exception\ApiErrorException $e) {
            error_log('Fehler bei der Payment Intent Erstellung: ' . $e->getMessage());
            wp_send_json_error(['message' => 'Fehler bei der Zahlungserstellung: ' . $e->getMessage()]);
            exit;
        }
        
        // Payment Method an den Payment Intent anhängen und bestätigen
        try {
            $payment_intent->confirm([
                'payment_method' => $payment_method_id,
            ]);
            error_log('Payment Intent wurde bestätigt: ' . $payment_intent->id);
        } catch (\Stripe\Exception\ApiErrorException $e) {
            error_log('Fehler bei der Payment Intent Bestätigung: ' . $e->getMessage());
            wp_send_json_error(['message' => 'Fehler bei der Zahlungsbestätigung: ' . $e->getMessage()]);
            exit;
        }

        // Kauf in der Datenbank speichern
        $insert_result = $wpdb->insert(
            $purchases_table,
            [
                'product_id' => $product_id,
                'email' => $email,
                'amount' => $product->price,
                'payment_intent_id' => $payment_intent->id,
                'payment_status' => $payment_intent->status === 'succeeded' ? 'completed' : 'pending',
            ]
        );
        
        if ($insert_result === false) {
            error_log('Fehler beim Speichern des Kaufs in der Datenbank: ' . $wpdb->last_error);
            wp_send_json_error(['message' => 'Fehler beim Speichern des Kaufs in der Datenbank.']);
            exit;
        }

        // Wenn die Zahlung sofort erfolgreich ist
        if ($payment_intent->status === 'succeeded') {
            $purchase_id = $wpdb->insert_id;
            $download_token = stripepay_generate_download_token();
            $download_expiry = date('Y-m-d H:i:s', strtotime('+7 days'));

            $update_result = $wpdb->update(
                $purchases_table,
                [
                    'download_token' => $download_token,
                    'download_expiry' => $download_expiry,
                ],
                ['id' => $purchase_id]
            );
            
            if ($update_result === false) {
                error_log('Fehler beim Aktualisieren des Kaufs in der Datenbank: ' . $wpdb->last_error);
            }

            // KEINE E-Mail senden - wird vom Webhook-Handler erledigt
            $purchase = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $purchases_table WHERE id = %d",
                $purchase_id
            ));
            
            if ($purchase) {
                error_log('Kauf erfolgreich. E-Mail wird vom Webhook-Handler gesendet.');
            }

            wp_send_json_success([
                'status' => 'succeeded',
                'message' => 'Zahlung erfolgreich. Sie erhalten eine E-Mail mit dem Download-Link.',
            ]);
        } else if ($payment_intent->status === 'requires_action' && isset($payment_intent->next_action) && $payment_intent->next_action->type === 'use_stripe_sdk') {
            // 3D Secure erforderlich
            wp_send_json_success([
                'status' => 'requires_action',
                'client_secret' => $payment_intent->client_secret,
                'message' => 'Zusätzliche Authentifizierung erforderlich.',
            ]);
        } else {
            wp_send_json_success([
                'status' => $payment_intent->status,
                'message' => 'Zahlung wird verarbeitet.',
            ]);
        }
    } catch (Exception $e) {
        error_log('Unerwarteter Fehler: ' . $e->getMessage());
        error_log('Stack Trace: ' . $e->getTraceAsString());
        wp_send_json_error(['message' => 'Ein unerwarteter Fehler ist aufgetreten: ' . $e->getMessage()]);
    }

    exit;
}
add_action('wp_ajax_stripepay_process_payment', 'stripepay_ajax_process_payment');
add_action('wp_ajax_nopriv_stripepay_process_payment', 'stripepay_ajax_process_payment');

/**
 * AJAX-Handler für die Überprüfung des Payment Intent Status.
 */
function stripepay_ajax_check_payment_status() {
    // Aktiviere die Fehlerberichterstattung für Debugging
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
    
    try {
        // Debug-Informationen
        error_log('stripepay_ajax_check_payment_status wurde aufgerufen');
        error_log('POST-Daten: ' . print_r($_POST, true));
        
        // Prüfen, ob die Stripe PHP-Bibliothek installiert ist
        if (!file_exists(plugin_dir_path(dirname(__FILE__)) . 'vendor/autoload.php')) {
            error_log('Stripe PHP-Bibliothek nicht gefunden');
            wp_send_json_error(['message' => 'Die Stripe PHP-Bibliothek wurde nicht gefunden. Bitte führen Sie "composer install" im Plugin-Verzeichnis aus.']);
            exit;
        }
        
        // Nonce überprüfen (mehrere Möglichkeiten)
        $nonce_verified = false;
        
        // Methode 1: Standard-Nonce-Parameter
        if (isset($_POST['nonce']) && wp_verify_nonce($_POST['nonce'], 'stripepay_payment')) {
            $nonce_verified = true;
            error_log('Nonce-Überprüfung erfolgreich (Methode 1)');
        }
        
        // Methode 2: Security-Parameter
        if (!$nonce_verified && isset($_POST['security']) && wp_verify_nonce($_POST['security'], 'stripepay_payment')) {
            $nonce_verified = true;
            error_log('Nonce-Überprüfung erfolgreich (Methode 2)');
        }
        
        // Methode 3: X-WP-Nonce Header
        if (!$nonce_verified && isset($_SERVER['HTTP_X_WP_NONCE'])) {
            $nonce = $_SERVER['HTTP_X_WP_NONCE'];
            if (wp_verify_nonce($nonce, 'stripepay_payment')) {
                $nonce_verified = true;
                error_log('Nonce-Überprüfung erfolgreich (Methode 3)');
            }
        }
        
        if (!$nonce_verified) {
            error_log('Nonce-Überprüfung fehlgeschlagen');
            wp_send_json_error(['message' => 'Sicherheitsüberprüfung fehlgeschlagen.']);
            exit;
        }

        $payment_intent_id = isset($_POST['payment_intent_id']) ? sanitize_text_field($_POST['payment_intent_id']) : '';

        if (!$payment_intent_id) {
            error_log('Keine Payment Intent ID angegeben');
            wp_send_json_error(['message' => 'Ungültige Daten.']);
            exit;
        }

        global $wpdb;
        $purchases_table = $wpdb->prefix . 'stripepay_purchases';
        
        // Prüfen, ob die Tabelle existiert
        if ($wpdb->get_var("SHOW TABLES LIKE '$purchases_table'") != $purchases_table) {
            error_log('Käufe-Tabelle existiert nicht');
            wp_send_json_error(['message' => 'Die Datenbanktabelle für Käufe existiert nicht. Bitte deaktivieren und reaktivieren Sie das Plugin.']);
            exit;
        }

        // Stripe initialisieren
        try {
            // Prüfen, ob die Stripe PHP-Bibliothek geladen werden kann
            require_once plugin_dir_path(dirname(__FILE__)) . 'vendor/autoload.php';
            
            // API-Key basierend auf dem Modus (Live oder Test) setzen
            $live_mode = get_option('stripepay_live_mode', false);
            if ($live_mode) {
                $api_key = get_option('stripepay_stripe_live_key', '');
            } else {
                $api_key = get_option('stripepay_stripe_test_key', '');
            }
            
            // Prüfen, ob ein API-Key konfiguriert ist
            if (empty($api_key)) {
                error_log('Kein Stripe API-Key konfiguriert');
                wp_send_json_error(['message' => 'Kein Stripe API-Key konfiguriert. Bitte geben Sie Ihre Stripe API-Keys in den Plugin-Einstellungen ein.']);
                exit;
            }
            
            // Stripe API-Key setzen
            \Stripe\Stripe::setApiKey($api_key);
            error_log('Stripe wurde erfolgreich initialisiert. Modus: ' . ($live_mode ? 'Live' : 'Test'));
        } catch (Exception $e) {
            error_log('Fehler bei der Stripe-Initialisierung: ' . $e->getMessage());
            wp_send_json_error(['message' => 'Fehler bei der Stripe-Initialisierung: ' . $e->getMessage()]);
            exit;
        }

        // Payment Intent abrufen
        try {
            $payment_intent = \Stripe\PaymentIntent::retrieve($payment_intent_id);
            error_log('Payment Intent abgerufen: ' . $payment_intent->id . ', Status: ' . $payment_intent->status);
        } catch (\Stripe\Exception\ApiErrorException $e) {
            error_log('Fehler beim Abrufen des Payment Intent: ' . $e->getMessage());
            wp_send_json_error(['message' => 'Fehler beim Abrufen der Zahlungsinformationen: ' . $e->getMessage()]);
            exit;
        }

        if ($payment_intent->status === 'succeeded') {
            // Kauf in der Datenbank suchen
            $purchase = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $purchases_table WHERE payment_intent_id = %s",
                $payment_intent_id
            ));

            if (!$purchase) {
                error_log('Kauf nicht gefunden für Payment Intent: ' . $payment_intent_id);
                wp_send_json_error(['message' => 'Kauf nicht gefunden.']);
                exit;
            }

            if ($purchase->payment_status !== 'completed') {
                $download_token = stripepay_generate_download_token();
                $download_expiry = date('Y-m-d H:i:s', strtotime('+7 days'));

                $update_result = $wpdb->update(
                    $purchases_table,
                    [
                        'payment_status' => 'completed',
                        'download_token' => $download_token,
                        'download_expiry' => $download_expiry,
                    ],
                    ['payment_intent_id' => $payment_intent_id]
                );
                
                if ($update_result === false) {
                    error_log('Fehler beim Aktualisieren des Kaufs in der Datenbank: ' . $wpdb->last_error);
                    wp_send_json_error(['message' => 'Fehler beim Aktualisieren des Kaufs in der Datenbank.']);
                    exit;
                }

                // KEINE E-Mail senden - wird vom Webhook-Handler erledigt
                $purchase = $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM $purchases_table WHERE payment_intent_id = %s",
                    $payment_intent_id
                ));
                
                if ($purchase) {
                    error_log('Kauf erfolgreich. E-Mail wird vom Webhook-Handler gesendet.');
                }
            }

            wp_send_json_success([
                'status' => 'succeeded',
                'message' => 'Zahlung erfolgreich. Sie erhalten eine E-Mail mit dem Download-Link.',
            ]);
        } else {
            wp_send_json_success([
                'status' => $payment_intent->status,
                'message' => 'Zahlung wird verarbeitet.',
            ]);
        }
    } catch (Exception $e) {
        error_log('Unerwarteter Fehler: ' . $e->getMessage());
        error_log('Stack Trace: ' . $e->getTraceAsString());
        wp_send_json_error(['message' => 'Ein unerwarteter Fehler ist aufgetreten: ' . $e->getMessage()]);
    }

    exit;
}
add_action('wp_ajax_stripepay_check_payment_status', 'stripepay_ajax_check_payment_status');
add_action('wp_ajax_nopriv_stripepay_check_payment_status', 'stripepay_ajax_check_payment_status');
