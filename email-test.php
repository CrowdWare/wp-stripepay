<?php
// Einfacher E-Mail-Test ohne WordPress

// Konfiguration
$to = isset($_GET['email']) ? $_GET['email'] : 'ihre-email@example.com';
$subject = 'Test-E-Mail von PHP mail()';
$message = 'Dies ist eine Test-E-Mail, die direkt mit der PHP mail()-Funktion gesendet wurde.';
$headers = 'From: webmaster@' . $_SERVER['SERVER_NAME'] . "\r\n" .
    'Reply-To: webmaster@' . $_SERVER['SERVER_NAME'] . "\r\n" .
    'X-Mailer: PHP/' . phpversion();

// Aktiviere Fehlerberichterstattung
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo '<h1>PHP E-Mail-Test</h1>';

// Versuche, die E-Mail zu senden
if(isset($_GET['send'])) {
    echo '<h2>Versuch, eine E-Mail zu senden...</h2>';
    
    $result = mail($to, $subject, $message, $headers);
    
    if($result) {
        echo '<p style="color: green;">E-Mail wurde erfolgreich gesendet an: ' . htmlspecialchars($to) . '</p>';
    } else {
        echo '<p style="color: red;">Fehler beim Senden der E-Mail an: ' . htmlspecialchars($to) . '</p>';
        
        // Versuche, mehr Informationen zu erhalten
        echo '<h3>Fehlerinformationen:</h3>';
        echo '<pre>';
        echo 'PHP mail() Rückgabewert: ' . var_export($result, true) . "\n";
        echo 'error_get_last(): ' . var_export(error_get_last(), true) . "\n";
        echo '</pre>';
    }
}

// Zeige Serverinformationen an
echo '<h2>Server-Informationen</h2>';
echo '<pre>';
echo 'PHP-Version: ' . phpversion() . "\n";
echo 'Server-Software: ' . $_SERVER['SERVER_SOFTWARE'] . "\n";
echo 'Server-Name: ' . $_SERVER['SERVER_NAME'] . "\n";
echo 'sendmail_path: ' . ini_get('sendmail_path') . "\n";
echo 'SMTP: ' . ini_get('SMTP') . "\n";
echo 'smtp_port: ' . ini_get('smtp_port') . "\n";
echo '</pre>';

// Formular zum Senden einer Test-E-Mail
echo '<h2>Test-E-Mail senden</h2>';
echo '<form method="get">';
echo '<label for="email">E-Mail-Adresse:</label> ';
echo '<input type="email" name="email" id="email" value="' . htmlspecialchars($to) . '" required>';
echo '<input type="hidden" name="send" value="1">';
echo '<input type="submit" value="Test-E-Mail senden">';
echo '</form>';

// Hinweise zur Fehlerbehebung
echo '<h2>Fehlerbehebung</h2>';
echo '<ul>';
echo '<li>Überprüfen Sie Ihren Spam-Ordner</li>';
echo '<li>Viele Hosting-Anbieter blockieren den E-Mail-Versand über die PHP mail()-Funktion</li>';
echo '<li>Überprüfen Sie die Server-Fehlerprotokolle für weitere Informationen</li>';
echo '<li>Erwägen Sie die Verwendung eines SMTP-Plugins für WordPress</li>';
echo '</ul>';
?>
