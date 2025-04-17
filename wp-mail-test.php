<?php
/**
 * Plugin Name: WP Mail Test
 * Description: Einfaches Plugin zum Testen des WordPress-E-Mail-Versands
 * Version: 1.0
 * Author: Cline
 */

// Sicherheitscheck
if (!defined('ABSPATH')) {
    exit;
}

// Admin-Menü hinzufügen
function wp_mail_test_menu() {
    add_menu_page(
        'WP Mail Test',
        'WP Mail Test',
        'manage_options',
        'wp-mail-test',
        'wp_mail_test_page',
        'dashicons-email'
    );
}
add_action('admin_menu', 'wp_mail_test_menu');

// Admin-Seite
function wp_mail_test_page() {
    $test_email = '';
    $test_result = '';
    $test_message = '';
    $error_details = '';
    
    // Wenn das Formular abgesendet wurde
    if (isset($_POST['wp_mail_test_nonce']) && wp_verify_nonce($_POST['wp_mail_test_nonce'], 'wp_mail_test')) {
        $test_email = isset($_POST['test_email']) ? sanitize_email($_POST['test_email']) : '';
        
        if (!$test_email || !is_email($test_email)) {
            $test_result = 'error';
            $test_message = 'Bitte geben Sie eine gültige E-Mail-Adresse ein.';
        } else {
            // Debug-Informationen sammeln
            global $phpmailer;
            
            // PHPMailer zurücksetzen
            if (isset($phpmailer)) {
                $phpmailer = null;
            }
            
            // Aktiviere Debug-Modus für PHPMailer
            add_action('phpmailer_init', function($phpmailer) {
                $phpmailer->SMTPDebug = 2;
                $phpmailer->Debugoutput = function($str, $level) {
                    error_log("PHPMailer [$level] $str");
                };
            });
            
            // E-Mail senden
            $subject = 'WP Mail Test - ' . date('Y-m-d H:i:s');
            $message = 'Dies ist eine Test-E-Mail von WordPress, gesendet am ' . date('Y-m-d H:i:s');
            $headers = [
                'Content-Type: text/html; charset=UTF-8',
                'From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>',
            ];
            
            // Fehler abfangen
            ob_start();
            $sent = wp_mail($test_email, $subject, $message, $headers);
            $error_output = ob_get_clean();
            
            if ($sent) {
                $test_result = 'success';
                $test_message = 'E-Mail erfolgreich gesendet! Bitte überprüfen Sie Ihren Posteingang und ggf. den Spam-Ordner.';
            } else {
                $test_result = 'error';
                $test_message = 'Fehler beim Senden der E-Mail.';
                
                // Versuche, den Fehler zu diagnostizieren
                global $phpmailer;
                if (isset($phpmailer) && $phpmailer->ErrorInfo) {
                    $error_details = $phpmailer->ErrorInfo;
                }
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
        <h1>WordPress E-Mail-Test</h1>
        
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
                    <?php if ($error_details) : ?>
                        <p><strong>Fehlerdetails:</strong> <?php echo esc_html($error_details); ?></p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <form method="post">
                <?php wp_nonce_field('wp_mail_test', 'wp_mail_test_nonce'); ?>
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
            <h2>WordPress-Fehlerprotokolle aktivieren</h2>
            <p>Um detaillierte Fehlerinformationen zu erhalten, aktivieren Sie das WordPress-Debugging:</p>
            <ol>
                <li>Öffnen Sie die Datei <code>wp-config.php</code> im WordPress-Hauptverzeichnis</li>
                <li>Fügen Sie diese Zeilen hinzu (oder ersetzen Sie bestehende Debug-Einstellungen):</li>
            </ol>
            <pre style="background-color: #f5f5f5; padding: 10px; border: 1px solid #ddd;">
// Aktiviere Debug-Modus
define('WP_DEBUG', true);

// Schreibe Fehler in eine Datei
define('WP_DEBUG_LOG', true);

// Zeige keine Fehler im Frontend an
define('WP_DEBUG_DISPLAY', false);
            </pre>
            <p>Nach der Aktivierung werden Fehler in die Datei <code>wp-content/debug.log</code> geschrieben.</p>
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
