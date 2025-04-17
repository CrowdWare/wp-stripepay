<?php
/**
 * E-Mail-Test-Seite für WP StripePay.
 */

// Sicherheitscheck
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Fügt den Admin-Menüpunkt für den E-Mail-Test hinzu.
 */
function stripepay_add_email_test_menu() {
    add_submenu_page(
        'stripepay-settings',
        'E-Mail-Test',
        'E-Mail-Test',
        'manage_options',
        'stripepay-email-test',
        'stripepay_email_test_page'
    );
}
add_action('admin_menu', 'stripepay_add_email_test_menu');

/**
 * Admin-Seite: E-Mail-Test.
 */
function stripepay_email_test_page() {
    $test_email = '';
    $test_result = '';
    $test_message = '';
    
    // Wenn das Formular abgesendet wurde
    if (isset($_POST['stripepay_test_email_nonce']) && wp_verify_nonce($_POST['stripepay_test_email_nonce'], 'stripepay_test_email')) {
        $test_email = isset($_POST['test_email']) ? sanitize_email($_POST['test_email']) : '';
        
        if (!$test_email || !is_email($test_email)) {
            $test_result = 'error';
            $test_message = 'Bitte geben Sie eine gültige E-Mail-Adresse ein.';
        } else {
            // E-Mail senden
            $result = stripepay_test_email($test_email);
            
            if ($result) {
                $test_result = 'success';
                $test_message = 'E-Mail erfolgreich gesendet! Bitte überprüfen Sie Ihren Posteingang und ggf. den Spam-Ordner.';
            } else {
                $test_result = 'error';
                $test_message = 'Fehler beim Senden der E-Mail. Bitte überprüfen Sie die WordPress-Fehlerprotokolle.';
            }
        }
    }
    
    // WordPress-E-Mail-Konfiguration überprüfen
    $admin_email = get_option('admin_email');
    $site_name = get_bloginfo('name');
    $wp_mail_from = apply_filters('wp_mail_from', $admin_email);
    $wp_mail_from_name = apply_filters('wp_mail_from_name', $site_name);
    
    // PHP-Mail-Konfiguration überprüfen
    $php_mail_enabled = function_exists('mail') && is_callable('mail');
    
    ?>
    <div class="wrap">
        <h1>E-Mail-Test</h1>
        
        <div class="card">
            <h2>WordPress-E-Mail-Konfiguration</h2>
            <table class="form-table">
                <tr>
                    <th>Admin-E-Mail:</th>
                    <td><?php echo esc_html($admin_email); ?></td>
                </tr>
                <tr>
                    <th>Website-Name:</th>
                    <td><?php echo esc_html($site_name); ?></td>
                </tr>
                <tr>
                    <th>Von E-Mail (nach Filtern):</th>
                    <td><?php echo esc_html($wp_mail_from); ?></td>
                </tr>
                <tr>
                    <th>Von Name (nach Filtern):</th>
                    <td><?php echo esc_html($wp_mail_from_name); ?></td>
                </tr>
                <tr>
                    <th>PHP mail() Funktion:</th>
                    <td><?php echo $php_mail_enabled ? '<span style="color: green;">Verfügbar</span>' : '<span style="color: red;">Nicht verfügbar</span>'; ?></td>
                </tr>
            </table>
        </div>
        
        <div class="card" style="margin-top: 20px;">
            <h2>E-Mail-Test</h2>
            
            <?php if ($test_result) : ?>
                <div class="notice notice-<?php echo $test_result === 'success' ? 'success' : 'error'; ?> is-dismissible">
                    <p><?php echo esc_html($test_message); ?></p>
                </div>
            <?php endif; ?>
            
            <form method="post">
                <?php wp_nonce_field('stripepay_test_email', 'stripepay_test_email_nonce'); ?>
                <table class="form-table">
                    <tr>
                        <th><label for="test_email">E-Mail-Adresse:</label></th>
                        <td>
                            <input type="email" id="test_email" name="test_email" value="<?php echo esc_attr($test_email); ?>" class="regular-text" required>
                            <p class="description">Geben Sie die E-Mail-Adresse ein, an die die Test-E-Mail gesendet werden soll.</p>
                        </td>
                    </tr>
                </table>
                <p class="submit">
                    <input type="submit" name="submit" id="submit" class="button button-primary" value="Test-E-Mail senden">
                </p>
            </form>
        </div>
        
        <div class="card" style="margin-top: 20px;">
            <h2>Fehlerbehebung</h2>
            <p>Wenn Sie keine E-Mails erhalten, überprüfen Sie folgende Punkte:</p>
            <ol>
                <li>Überprüfen Sie Ihren Spam-Ordner</li>
                <li>Stellen Sie sicher, dass Ihr Server E-Mails versenden kann (viele Hosting-Anbieter blockieren den E-Mail-Versand)</li>
                <li>Installieren Sie ein SMTP-Plugin wie "WP Mail SMTP", um E-Mails über einen SMTP-Server zu versenden</li>
                <li>Überprüfen Sie die WordPress-Fehlerprotokolle für weitere Informationen</li>
            </ol>
            
            <h3>Empfohlene SMTP-Plugins:</h3>
            <ul>
                <li><a href="https://wordpress.org/plugins/wp-mail-smtp/" target="_blank">WP Mail SMTP</a></li>
                <li><a href="https://wordpress.org/plugins/easy-wp-smtp/" target="_blank">Easy WP SMTP</a></li>
                <li><a href="https://wordpress.org/plugins/post-smtp/" target="_blank">Post SMTP</a></li>
            </ul>
        </div>
    </div>
    <?php
}
