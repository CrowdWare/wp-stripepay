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
        // Stripe PHP-Bibliothek einbinden
        require_once plugin_dir_path(dirname(__FILE__)) . 'vendor/autoload.php';
    }

    // API-Key basierend auf dem Modus (Live oder Test) setzen
    $live_mode = get_option('stripepay_live_mode', false);
    if ($live_mode) {
        $api_key = get_option('stripepay_stripe_live_key', '');
    } else {
        $api_key = get_option('stripepay_stripe_test_key', '');
    }

    // Stripe API-Key setzen
    \Stripe\Stripe::setApiKey($api_key);
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
    // Nonce überprüfen
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'stripepay_payment')) {
        wp_send_json_error(['message' => 'Sicherheitsüberprüfung fehlgeschlagen.']);
        exit;
    }

    // Daten validieren
    $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
    $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
    $payment_method_id = isset($_POST['payment_method_id']) ? sanitize_text_field($_POST['payment_method_id']) : '';

    if (!$product_id || !$email || !$payment_method_id || !is_email($email)) {
        wp_send_json_error(['message' => 'Ungültige Daten.']);
        exit;
    }

    global $wpdb;
    $products_table = $wpdb->prefix . 'stripepay_products';
    $purchases_table = $wpdb->prefix . 'stripepay_purchases';

    // Produkt aus der Datenbank holen
    $product = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $products_table WHERE id = %d",
        $product_id
    ));

    if (!$product) {
        wp_send_json_error(['message' => 'Produkt nicht gefunden.']);
        exit;
    }

    // Payment Intent erstellen
    $payment_intent = stripepay_create_payment_intent(
        $product->price,
        'eur',
        'Kauf von ' . $product->name,
        [
            'product_id' => $product_id,
            'email' => $email,
        ]
    );

    if (is_wp_error($payment_intent)) {
        wp_send_json_error(['message' => $payment_intent->get_error_message()]);
        exit;
    }

    try {
        // Payment Method an den Payment Intent anhängen und bestätigen
        stripepay_init_stripe();
        $payment_intent->confirm([
            'payment_method' => $payment_method_id,
        ]);

        // Kauf in der Datenbank speichern
        $wpdb->insert(
            $purchases_table,
            [
                'product_id' => $product_id,
                'email' => $email,
                'amount' => $product->price,
                'payment_intent_id' => $payment_intent->id,
                'payment_status' => $payment_intent->status === 'succeeded' ? 'completed' : 'pending',
            ]
        );

        // Wenn die Zahlung sofort erfolgreich ist
        if ($payment_intent->status === 'succeeded') {
            $purchase_id = $wpdb->insert_id;
            $download_token = stripepay_generate_download_token();
            $download_expiry = date('Y-m-d H:i:s', strtotime('+7 days'));

            $wpdb->update(
                $purchases_table,
                [
                    'download_token' => $download_token,
                    'download_expiry' => $download_expiry,
                ],
                ['id' => $purchase_id]
            );

            // E-Mail mit Download-Link senden
            $purchase = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $purchases_table WHERE id = %d",
                $purchase_id
            ));
            stripepay_send_download_email($purchase);

            wp_send_json_success([
                'status' => 'succeeded',
                'message' => 'Zahlung erfolgreich. Sie erhalten eine E-Mail mit dem Download-Link.',
            ]);
        } else if ($payment_intent->status === 'requires_action' && $payment_intent->next_action->type === 'use_stripe_sdk') {
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
    } catch (\Stripe\Exception\ApiErrorException $e) {
        wp_send_json_error(['message' => $e->getMessage()]);
    }

    exit;
}
add_action('wp_ajax_stripepay_process_payment', 'stripepay_ajax_process_payment');
add_action('wp_ajax_nopriv_stripepay_process_payment', 'stripepay_ajax_process_payment');

/**
 * AJAX-Handler für die Überprüfung des Payment Intent Status.
 */
function stripepay_ajax_check_payment_status() {
    // Nonce überprüfen
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'stripepay_payment')) {
        wp_send_json_error(['message' => 'Sicherheitsüberprüfung fehlgeschlagen.']);
        exit;
    }

    $payment_intent_id = isset($_POST['payment_intent_id']) ? sanitize_text_field($_POST['payment_intent_id']) : '';

    if (!$payment_intent_id) {
        wp_send_json_error(['message' => 'Ungültige Daten.']);
        exit;
    }

    $payment_intent = stripepay_retrieve_payment_intent($payment_intent_id);

    if (is_wp_error($payment_intent)) {
        wp_send_json_error(['message' => $payment_intent->get_error_message()]);
        exit;
    }

    if ($payment_intent->status === 'succeeded') {
        global $wpdb;
        $purchases_table = $wpdb->prefix . 'stripepay_purchases';

        // Kauf in der Datenbank aktualisieren
        $purchase = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $purchases_table WHERE payment_intent_id = %s",
            $payment_intent_id
        ));

        if ($purchase && $purchase->payment_status !== 'completed') {
            $download_token = stripepay_generate_download_token();
            $download_expiry = date('Y-m-d H:i:s', strtotime('+7 days'));

            $wpdb->update(
                $purchases_table,
                [
                    'payment_status' => 'completed',
                    'download_token' => $download_token,
                    'download_expiry' => $download_expiry,
                ],
                ['payment_intent_id' => $payment_intent_id]
            );

            // E-Mail mit Download-Link senden
            $purchase = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $purchases_table WHERE payment_intent_id = %s",
                $payment_intent_id
            ));
            stripepay_send_download_email($purchase);
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

    exit;
}
add_action('wp_ajax_stripepay_check_payment_status', 'stripepay_ajax_check_payment_status');
add_action('wp_ajax_nopriv_stripepay_check_payment_status', 'stripepay_ajax_check_payment_status');
