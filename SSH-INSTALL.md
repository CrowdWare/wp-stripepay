# Installation der Stripe PHP-Bibliothek über SSH

Da Sie SSH-Zugriff auf Ihren IONOS-Server haben, können Sie Composer direkt auf dem Server verwenden, um die Stripe PHP-Bibliothek zu installieren. Dies ist die empfohlene Methode, da sie einfacher und zuverlässiger ist als die manuelle Installation über FTP.

## Schritt 1: SSH-Verbindung herstellen

Verbinden Sie sich über SSH mit Ihrem Server:

```bash
ssh benutzername@ihr-server.de
```

## Schritt 2: Zum Plugin-Verzeichnis navigieren

Navigieren Sie zum Plugin-Verzeichnis:

```bash
cd /pfad/zu/wordpress/wp-content/plugins/wp-stripepay
```

Der genaue Pfad kann je nach Ihrer Server-Konfiguration variieren.

## Schritt 3: Composer installieren (falls noch nicht vorhanden)

Überprüfen Sie, ob Composer bereits installiert ist:

```bash
composer --version
```

Falls Composer nicht installiert ist, können Sie es mit folgendem Befehl installieren:

```bash
curl -sS https://getcomposer.org/installer | php
mv composer.phar /usr/local/bin/composer
```

Falls Sie keine Root-Rechte haben, können Sie Composer auch lokal im Plugin-Verzeichnis installieren:

```bash
curl -sS https://getcomposer.org/installer | php
```

Dann verwenden Sie `php composer.phar` statt `composer` für die folgenden Befehle.

## Schritt 4: Stripe PHP-Bibliothek installieren

Installieren Sie die Stripe PHP-Bibliothek mit Composer:

```bash
composer install
```

Dieser Befehl liest die `composer.json`-Datei und installiert alle benötigten Abhängigkeiten, einschließlich der Stripe PHP-Bibliothek.

## Schritt 5: Berechtigungen überprüfen

Stellen Sie sicher, dass die Dateien die richtigen Berechtigungen haben:

```bash
chmod -R 755 vendor
find vendor -type f -exec chmod 644 {} \;
```

## Schritt 6: Plugin konfigurieren

1. Gehen Sie in Ihrem WordPress-Admin-Bereich zu StripePay > Einstellungen
2. Geben Sie Ihre Stripe API-Keys ein (sowohl Secret als auch Publishable Keys)
3. Konfigurieren Sie das Webhook-Secret, wenn Sie Webhooks verwenden möchten

## Fehlerbehebung

Wenn nach der Installation weiterhin Fehler auftreten:

1. Überprüfen Sie die WordPress-Fehlerprotokolle für weitere Informationen
2. Stellen Sie sicher, dass die Stripe API-Keys korrekt konfiguriert sind
3. Überprüfen Sie, ob die Datenbanktabellen korrekt erstellt wurden (deaktivieren und reaktivieren Sie das Plugin, falls nötig)

## Aktualisierung der Bibliothek

Um die Stripe PHP-Bibliothek zu aktualisieren, führen Sie einfach folgenden Befehl aus:

```bash
composer update stripe/stripe-php
