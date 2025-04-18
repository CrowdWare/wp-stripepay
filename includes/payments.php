<?php
/**
 * Zahlungsverarbeitung und Kaufverwaltung für WP StripePay.
 */

// Sicherheitscheck
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Fügt den Admin-Menüpunkt für die Kaufübersicht hinzu.
 */
function stripepay_add_purchases_menu() {
    add_submenu_page(
        'stripepay-settings',
        'Käufe verwalten',
        'Käufe',
        'manage_options',
        'stripepay-purchases',
        'stripepay_purchases_page'
    );
}
add_action('admin_menu', 'stripepay_add_purchases_menu');

/**
 * Admin-Seite: Kaufübersicht.
 */
function stripepay_purchases_page() {
    global $wpdb;
    $purchases_table = $wpdb->prefix . 'stripepay_purchases';
    $products_table = $wpdb->prefix . 'stripepay_products';

    // Aktion: Download-Link erneuern
    if (isset($_GET['action']) && $_GET['action'] === 'renew' && isset($_GET['id'])) {
        $purchase_id = intval($_GET['id']);
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

        // E-Mail mit neuem Download-Link senden
        $purchase = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $purchases_table WHERE id = %d",
            $purchase_id
        ));
        stripepay_send_download_email($purchase);

        echo '<div class="updated"><p>Download-Link erneuert und E-Mail gesendet.</p></div>';
    }

    // Filter
    $where = '';
    $product_filter = isset($_GET['product']) ? intval($_GET['product']) : 0;
    $status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
    $email_filter = isset($_GET['email']) ? sanitize_email($_GET['email']) : '';

    if ($product_filter) {
        $where .= $wpdb->prepare(" AND p.product_id = %d", $product_filter);
    }
    if ($status_filter) {
        $where .= $wpdb->prepare(" AND p.payment_status = %s", $status_filter);
    }
    if ($email_filter) {
        $where .= $wpdb->prepare(" AND p.email LIKE %s", '%' . $wpdb->esc_like($email_filter) . '%');
    }

    // Paginierung
    $per_page = 20;
    $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
    $offset = ($current_page - 1) * $per_page;

    // Käufe aus der Datenbank holen
    $purchases = $wpdb->get_results("
        SELECT p.*, pr.name as product_name
        FROM $purchases_table p
        LEFT JOIN $products_table pr ON p.product_id = pr.id
        WHERE 1=1 $where
        ORDER BY p.purchase_date DESC
        LIMIT $offset, $per_page
    ");

    // Gesamtanzahl für Paginierung
    $total_purchases = $wpdb->get_var("
        SELECT COUNT(*)
        FROM $purchases_table p
        WHERE 1=1 $where
    ");

    $total_pages = ceil($total_purchases / $per_page);

    // Alle Produkte für Filter
    $products = $wpdb->get_results("SELECT id, name FROM $products_table ORDER BY name");

    // Status-Optionen für Filter
    $status_options = [
        'pending' => 'Ausstehend',
        'completed' => 'Abgeschlossen',
        'failed' => 'Fehlgeschlagen',
    ];

    ?>
    <div class="wrap">
        <h1>Käufe verwalten</h1>

        <!-- Filter -->
        <form method="get">
            <input type="hidden" name="page" value="<?php echo esc_attr($_GET['page']); ?>">
            <div class="tablenav top">
                <div class="alignleft actions">
                    <select name="product">
                        <option value="">Alle Produkte</option>
                        <?php foreach ($products as $product) : ?>
                            <option value="<?php echo esc_attr($product->id); ?>" <?php selected($product_filter, $product->id); ?>>
                                <?php echo esc_html($product->name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <select name="status">
                        <option value="">Alle Status</option>
                        <?php foreach ($status_options as $value => $label) : ?>
                            <option value="<?php echo esc_attr($value); ?>" <?php selected($status_filter, $value); ?>>
                                <?php echo esc_html($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <input type="text" name="email" placeholder="E-Mail-Adresse" value="<?php echo esc_attr($email_filter); ?>">
                    <?php submit_button('Filter', 'action', '', false); ?>
                </div>
            </div>
        </form>

        <!-- Käufe-Tabelle -->
        <table class="widefat striped">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Produkt</th>
                    <th>E-Mail</th>
                    <th>Betrag</th>
                    <th>Status</th>
                    <th>Kaufdatum</th>
                    <th>Download-Link gültig bis</th>
                    <th>Downloads</th>
                    <th>Aktionen</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($purchases) : ?>
                    <?php foreach ($purchases as $purchase) : ?>
                        <tr>
                            <td><?php echo esc_html($purchase->id); ?></td>
                            <td><?php echo esc_html($purchase->product_name); ?></td>
                            <td><?php echo esc_html($purchase->email); ?></td>
                            <td><?php echo number_format($purchase->amount / 100, 2, ',', '.') . ' €'; ?></td>
                            <td>
                                <?php
                                $status_label = '';
                                switch ($purchase->payment_status) {
                                    case 'completed':
                                        $status_label = '<span style="color: green;">Abgeschlossen</span>';
                                        break;
                                    case 'pending':
                                        $status_label = '<span style="color: orange;">Ausstehend</span>';
                                        break;
                                    case 'failed':
                                        $status_label = '<span style="color: red;">Fehlgeschlagen</span>';
                                        break;
                                    default:
                                        $status_label = esc_html($purchase->payment_status);
                                }
                                echo $status_label;
                                ?>
                            </td>
                            <td><?php echo date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($purchase->purchase_date)); ?></td>
                            <td>
                                <?php
                                if ($purchase->download_expiry) {
                                    $expiry_date = strtotime($purchase->download_expiry);
                                    $now = time();
                                    if ($now > $expiry_date) {
                                        echo '<span style="color: red;">Abgelaufen</span>';
                                    } else {
                                        echo date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $expiry_date);
                                    }
                                } else {
                                    echo '-';
                                }
                                ?>
                            </td>
                            <td><?php echo esc_html($purchase->download_count); ?></td>
                            <td>
                                <?php if ($purchase->payment_status === 'completed') : ?>
                                    <a href="?page=<?php echo esc_attr($_GET['page']); ?>&action=renew&id=<?php echo esc_attr($purchase->id); ?>" class="button button-small">
                                        Download-Link erneuern
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else : ?>
                    <tr>
                        <td colspan="9">Keine Käufe gefunden.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <!-- Paginierung -->
        <?php if ($total_pages > 1) : ?>
            <div class="tablenav bottom">
                <div class="tablenav-pages">
                    <span class="displaying-num"><?php echo esc_html($total_purchases); ?> Einträge</span>
                    <span class="pagination-links">
                        <?php
                        $page_links = paginate_links([
                            'base' => add_query_arg('paged', '%#%'),
                            'format' => '',
                            'prev_text' => '&laquo;',
                            'next_text' => '&raquo;',
                            'total' => $total_pages,
                            'current' => $current_page,
                        ]);
                        echo $page_links;
                        ?>
                    </span>
                </div>
            </div>
        <?php endif; ?>
    </div>
    <?php
}

/**
 * Gemeinsame Webhook-Verarbeitungslogik für Stripe.
 * 
 * @param string $mode 'live' oder 'test'
 */
function stripepay_process_webhook($mode = 'live') {
    // Debug-Informationen
    error_log('Stripe Webhook wurde aufgerufen (Modus: ' . $mode . ')');
    error_log('REQUEST_METHOD: ' . $_SERVER['REQUEST_METHOD']);
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        error_log('Ungültige Anfragemethode: ' . $_SERVER['REQUEST_METHOD']);
        status_header(400);
        echo json_encode(['error' => 'Ungültige Anfragemethode. POST erwartet.']);
        exit;
    }

    $payload = @file_get_contents('php://input');
    error_log('Webhook Payload: ' . substr($payload, 0, 100) . '...');
    
    $sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
    if (empty($sig_header)) {
        error_log('Stripe-Signature Header fehlt');
    } else {
        error_log('Stripe-Signature Header vorhanden: ' . substr($sig_header, 0, 50) . '...');
    }

    try {
        // Stripe-Bibliothek initialisieren
        stripepay_init_stripe();
        error_log('Stripe-Bibliothek initialisiert');

        // Webhook-Secret aus den Einstellungen holen (basierend auf dem Modus)
        $webhook_secret = ($mode === 'live') 
            ? get_option('stripepay_webhook_secret', '') 
            : get_option('stripepay_webhook_secret_test', '');

        if (!$webhook_secret) {
            error_log('Webhook-Secret nicht konfiguriert für Modus: ' . $mode);
            status_header(400);
            echo json_encode(['error' => 'Webhook-Secret nicht konfiguriert für Modus: ' . $mode]);
            exit;
        }
        
        error_log('Webhook-Secret konfiguriert: ' . substr($webhook_secret, 0, 5) . '...');

        // Event verifizieren
        try {
            $event = \Stripe\Webhook::constructEvent(
                $payload,
                $sig_header,
                $webhook_secret
            );
            error_log('Event verifiziert: ' . $event->id . ', Typ: ' . $event->type);
        } catch (\UnexpectedValueException $e) {
            error_log('Ungültiger Payload: ' . $e->getMessage());
            status_header(400);
            echo json_encode(['error' => 'Ungültiger Payload: ' . $e->getMessage()]);
            exit;
        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            error_log('Ungültige Signatur: ' . $e->getMessage());
            status_header(400);
            echo json_encode(['error' => 'Ungültige Signatur: ' . $e->getMessage()]);
            exit;
        }

        // Event verarbeiten
        if ($event->type === 'payment_intent.succeeded') {
            error_log('Verarbeite payment_intent.succeeded Event');
            $result = stripepay_process_webhook_event($event);
            
            if ($result) {
                error_log('Event erfolgreich verarbeitet');
                status_header(200);
                echo json_encode(['status' => 'success']);
            } else {
                error_log('Fehler bei der Verarbeitung des Events');
                status_header(500);
                echo json_encode(['error' => 'Fehler bei der Verarbeitung des Events']);
            }
            exit;
        } else {
            error_log('Event-Typ wird ignoriert: ' . $event->type);
        }

        status_header(200);
        echo json_encode(['status' => 'ignored']);
    } catch (\Exception $e) {
        error_log('Unerwarteter Fehler: ' . $e->getMessage());
        error_log('Stack Trace: ' . $e->getTraceAsString());
        status_header(500);
        echo json_encode(['error' => 'Unerwarteter Fehler: ' . $e->getMessage()]);
        exit;
    }

    exit;
}

/**
 * Webhook-Endpunkt für Stripe Live-Modus.
 */
function stripepay_webhook_handler() {
    stripepay_process_webhook('live');
}
add_action('wp_ajax_stripepay_webhook', 'stripepay_webhook_handler');
add_action('wp_ajax_nopriv_stripepay_webhook', 'stripepay_webhook_handler');

/**
 * Webhook-Endpunkt für Stripe Test-Modus.
 */
function stripepay_webhook_test_handler() {
    stripepay_process_webhook('test');
}
add_action('wp_ajax_stripepay_webhook_test', 'stripepay_webhook_test_handler');
add_action('wp_ajax_nopriv_stripepay_webhook_test', 'stripepay_webhook_test_handler');

/**
 * Fügt ein Feld für das Webhook-Secret in den Einstellungen hinzu.
 */
function stripepay_add_webhook_secret_field() {
    add_settings_field(
        'stripepay_webhook_secret',
        'Stripe Webhook Secret',
        'stripepay_webhook_secret_callback',
        'stripepay-settings',
        'default'
    );
    register_setting('stripepay_settings', 'stripepay_webhook_secret');
}
add_action('admin_init', 'stripepay_add_webhook_secret_field');

/**
 * Callback für das Webhook-Secret-Feld.
 */
function stripepay_webhook_secret_callback() {
    $live_webhook_secret = get_option('stripepay_webhook_secret', '');
    $test_webhook_secret = get_option('stripepay_webhook_secret_test', '');
    
    // Live Webhook
    echo '<h3>Live Webhook</h3>';
    echo '<input type="text" name="stripepay_webhook_secret" value="' . esc_attr($live_webhook_secret) . '" class="regular-text">';
    echo '<p class="description">Webhook-Secret für die Verifizierung von Stripe-Events im Live-Modus.</p>';
    
    $live_webhook_url = admin_url('admin-ajax.php?action=stripepay_webhook');
    echo '<p>Live Webhook-URL: <code>' . esc_html($live_webhook_url) . '</code></p>';
    
    // Test Webhook
    echo '<h3>Test Webhook</h3>';
    echo '<input type="text" name="stripepay_webhook_secret_test" value="' . esc_attr($test_webhook_secret) . '" class="regular-text">';
    echo '<p class="description">Webhook-Secret für die Verifizierung von Stripe-Events im Test-Modus.</p>';
    
    $test_webhook_url = admin_url('admin-ajax.php?action=stripepay_webhook_test');
    echo '<p>Test Webhook-URL: <code>' . esc_html($test_webhook_url) . '</code></p>';
    
    // Test-Button für E-Mail
    echo '<div style="margin-top: 15px;">';
    echo '<h3>E-Mail-Test</h3>';
    echo '<p>Senden Sie eine Test-E-Mail, um zu überprüfen, ob der E-Mail-Versand funktioniert:</p>';
    echo '<input type="email" id="test_email" placeholder="E-Mail-Adresse" class="regular-text">';
    echo '<button type="button" id="send_test_email" class="button">Test-E-Mail senden</button>';
    echo '<span id="email_test_result" style="margin-left: 10px;"></span>';
    echo '</div>';
    
    // JavaScript für den Test-Button
    ?>
    <script>
    jQuery(document).ready(function($) {
        $('#send_test_email').on('click', function() {
            var email = $('#test_email').val();
            if (!email) {
                alert('Bitte geben Sie eine E-Mail-Adresse ein.');
                return;
            }
            
            $('#email_test_result').text('Sende...');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'stripepay_test_email',
                    email: email,
                    nonce: '<?php echo wp_create_nonce('stripepay_test_email'); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        $('#email_test_result').text('E-Mail gesendet! Bitte überprüfen Sie Ihren Posteingang und ggf. den Spam-Ordner.');
                        $('#email_test_result').css('color', 'green');
                    } else {
                        $('#email_test_result').text('Fehler: ' + response.data.message);
                        $('#email_test_result').css('color', 'red');
                    }
                },
                error: function() {
                    $('#email_test_result').text('Fehler bei der Verbindung zum Server.');
                    $('#email_test_result').css('color', 'red');
                }
            });
        });
    });
    </script>
    <?php
    
    // Webhook-Test-Bereich
    echo '<div style="margin-top: 15px;">';
    echo '<h3>Webhook-Test</h3>';
    echo '<p>Überprüfen Sie, ob die Webhooks korrekt konfiguriert sind:</p>';
    echo '<ol>';
    echo '<li>Gehen Sie zu Ihrem <a href="https://dashboard.stripe.com/webhooks" target="_blank">Stripe Dashboard</a></li>';
    echo '<li>Fügen Sie zwei neue Webhook-Endpunkte hinzu:</li>';
    echo '<ul>';
    echo '<li>Live-Modus: <code>' . esc_html($live_webhook_url) . '</code></li>';
    echo '<li>Test-Modus: <code>' . esc_html($test_webhook_url) . '</code></li>';
    echo '</ul>';
    echo '<li>Wählen Sie für beide das Event <code>payment_intent.succeeded</code></li>';
    echo '<li>Kopieren Sie die Webhook-Secrets und fügen Sie sie oben ein</li>';
    echo '<li>Klicken Sie auf "Webhook testen", um Test-Events zu senden</li>';
    echo '</ol>';
    echo '<p>Überprüfen Sie die WordPress-Fehlerprotokolle, um zu sehen, ob die Webhooks korrekt verarbeitet werden.</p>';
    echo '</div>';
}

/**
 * AJAX-Handler für den E-Mail-Test.
 */
function stripepay_ajax_test_email() {
    // Nonce überprüfen
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'stripepay_test_email')) {
        wp_send_json_error(['message' => 'Sicherheitsüberprüfung fehlgeschlagen.']);
        exit;
    }
    
    $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
    
    if (!$email || !is_email($email)) {
        wp_send_json_error(['message' => 'Ungültige E-Mail-Adresse.']);
        exit;
    }
    
    $result = stripepay_test_email($email);
    
    if ($result) {
        wp_send_json_success(['message' => 'E-Mail erfolgreich gesendet.']);
    } else {
        wp_send_json_error(['message' => 'Fehler beim Senden der E-Mail. Bitte überprüfen Sie die WordPress-Fehlerprotokolle.']);
    }
    
    exit;
}
add_action('wp_ajax_stripepay_test_email', 'stripepay_ajax_test_email');

/**
 * Fügt ein Feld für den Live-Modus in den Einstellungen hinzu.
 */
function stripepay_add_live_mode_field() {
    add_settings_field(
        'stripepay_live_mode',
        'Live-Modus',
        'stripepay_live_mode_callback',
        'stripepay-settings',
        'default'
    );
    register_setting('stripepay_settings', 'stripepay_live_mode');
}
add_action('admin_init', 'stripepay_add_live_mode_field');

/**
 * Callback für das Live-Modus-Feld.
 */
function stripepay_live_mode_callback() {
    $live_mode = get_option('stripepay_live_mode', false);
    echo '<input type="checkbox" name="stripepay_live_mode" value="1" ' . checked(1, $live_mode, false) . '>';
    echo '<p class="description">Aktivieren Sie diese Option, um den Live-Modus zu verwenden. Deaktivieren Sie sie für den Test-Modus.</p>';
}
