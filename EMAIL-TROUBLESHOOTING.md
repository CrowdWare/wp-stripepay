# Fehlerbehebung für E-Mail-Probleme

## Ihr spezifisches Problem

Die Fehlermeldung "PHPMailer was able to connect to SMTP server but failed while trying to send an email" bedeutet, dass WordPress eine Verbindung zum SMTP-Server herstellen konnte, aber der eigentliche E-Mail-Versand fehlgeschlagen ist.

## Mögliche Ursachen und Lösungen

### 1. Falsche Absender-E-Mail-Adresse

Viele SMTP-Server erlauben nur den Versand von E-Mails mit bestimmten Absender-Adressen.

**Lösung:**
- Installieren Sie das Plugin "WP Mail SMTP"
- Konfigurieren Sie die "From Email" auf eine E-Mail-Adresse, die zu Ihrer Domain gehört
- Stellen Sie sicher, dass diese E-Mail-Adresse mit der übereinstimmt, die Sie für die SMTP-Authentifizierung verwenden

### 2. SMTP-Authentifizierungsprobleme

Obwohl die Verbindung hergestellt wurde, könnten die Anmeldedaten unvollständig oder falsch sein.

**Lösung:**
- Überprüfen Sie Benutzername und Passwort
- Stellen Sie sicher, dass das Konto die Berechtigung zum Senden von E-Mails hat
- Bei Gmail: Aktivieren Sie "Weniger sichere Apps" oder erstellen Sie ein App-Passwort

### 3. Einschränkungen des SMTP-Servers

Viele SMTP-Server haben Einschränkungen bezüglich der Anzahl der E-Mails, die gesendet werden können.

**Lösung:**
- Überprüfen Sie, ob Ihr SMTP-Server Ratenbegrenzungen hat
- Verwenden Sie einen dedizierten E-Mail-Dienst wie SendGrid, Mailgun oder Amazon SES

### 4. Probleme mit der E-Mail selbst

Der Inhalt der E-Mail könnte vom SMTP-Server als Spam erkannt werden.

**Lösung:**
- Vereinfachen Sie den E-Mail-Inhalt (weniger HTML, keine verdächtigen Wörter)
- Stellen Sie sicher, dass der Betreff nicht leer ist
- Überprüfen Sie, ob die Empfänger-E-Mail-Adresse gültig ist

## Schnelle Lösung: Externes SMTP-Plugin

Die einfachste Lösung ist die Verwendung eines SMTP-Plugins mit einem zuverlässigen E-Mail-Dienst:

1. Installieren Sie das Plugin "WP Mail SMTP" (https://wordpress.org/plugins/wp-mail-smtp/)
2. Konfigurieren Sie es mit einem dieser Dienste:
   - SendGrid (250 E-Mails/Tag kostenlos)
   - Mailgun (5.000 E-Mails/Monat kostenlos)
   - Amazon SES (62.000 E-Mails/Monat kostenlos mit AWS Free Tier)
   - Gmail (mit App-Passwort)

## Temporäre Alternative: Manuelle E-Mail-Benachrichtigung

Bis das E-Mail-Problem behoben ist, können Sie die Download-Links manuell an Kunden senden:

1. Gehen Sie zu StripePay > Käufe
2. Suchen Sie den entsprechenden Kauf
3. Klicken Sie auf "Download-Link erneuern"
4. Kopieren Sie den Link aus dem WordPress-Fehlerprotokoll
5. Senden Sie den Link manuell per E-Mail an den Kunden

## Überprüfen der WordPress-Fehlerprotokolle

Die detaillierten Fehlerprotokolle können weitere Hinweise auf das Problem geben:

1. Aktivieren Sie das WordPress-Debugging, falls noch nicht geschehen:
   - Fügen Sie diese Zeilen zu Ihrer wp-config.php hinzu:
   ```php
   define('WP_DEBUG', true);
   define('WP_DEBUG_LOG', true);
   define('WP_DEBUG_DISPLAY', false);
   ```

2. Überprüfen Sie die Datei wp-content/debug.log nach spezifischen PHPMailer-Fehlern

## Testen mit einem anderen E-Mail-Dienst

Um zu überprüfen, ob das Problem mit Ihrem SMTP-Server zusammenhängt, können Sie temporär einen anderen E-Mail-Dienst testen:

1. Erstellen Sie ein kostenloses Konto bei SendGrid oder Mailgun
2. Konfigurieren Sie WP Mail SMTP mit diesen Daten
3. Führen Sie den E-Mail-Test erneut durch
