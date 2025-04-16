# Manuelle Installation der Stripe PHP-Bibliothek

Diese Anleitung beschreibt, wie Sie die Stripe PHP-Bibliothek manuell über FTP installieren können, wenn Sie keinen direkten SSH-Zugriff auf Ihren Server haben (z.B. bei IONOS-Hosting).

## Schritt 1: Stripe PHP-Bibliothek herunterladen

1. Besuchen Sie die [Stripe PHP GitHub-Seite](https://github.com/stripe/stripe-php)
2. Klicken Sie auf den grünen "Code"-Button und wählen Sie "Download ZIP"
3. Speichern Sie die ZIP-Datei auf Ihrem Computer und entpacken Sie sie

## Schritt 2: Verzeichnisstruktur vorbereiten

1. Erstellen Sie in dem entpackten Verzeichnis folgende Ordnerstruktur:
   ```
   vendor/
   └── stripe/
       └── stripe-php/
   ```

2. Verschieben Sie alle Dateien und Ordner aus dem entpackten Verzeichnis in den `vendor/stripe/stripe-php/` Ordner

## Schritt 3: Dateien über FTP hochladen

1. Verbinden Sie sich über FTP mit Ihrem Server
2. Navigieren Sie zum Plugin-Verzeichnis: `/wp-content/plugins/wp-stripepay/`
3. Falls der `vendor`-Ordner noch nicht existiert, erstellen Sie ihn
4. Laden Sie den gesamten `vendor`-Ordner mit der Stripe PHP-Bibliothek hoch

## Schritt 4: Überprüfen der Installation

1. Stellen Sie sicher, dass die Datei `vendor/stripe/stripe-php/init.php` existiert
2. Diese Datei wird vom Plugin automatisch erkannt und geladen

## Verzeichnisstruktur nach der Installation

Nach der erfolgreichen Installation sollte die Verzeichnisstruktur wie folgt aussehen:

```
wp-stripepay/
├── assets/
│   ├── css/
│   │   └── stripe-elements.css
│   └── js/
│       └── stripe-elements.js
├── includes/
│   ├── activation.php
│   ├── admin.php
│   ├── emails.php
│   ├── manual-stripe-loader.php
│   ├── payments.php
│   ├── scripts.php
│   ├── shortcodes.php
│   └── stripe-integration.php
├── vendor/
│   └── stripe/
│       └── stripe-php/
│           ├── init.php
│           ├── lib/
│           ├── data/
│           └── ... (weitere Dateien und Ordner)
├── composer.json
├── README.md
└── wp-stripepay.php
```

## Fehlerbehebung

Wenn nach der manuellen Installation weiterhin Fehler auftreten:

1. Überprüfen Sie, ob die Datei `vendor/stripe/stripe-php/init.php` existiert und lesbar ist
2. Stellen Sie sicher, dass die Verzeichnisberechtigungen korrekt sind (in der Regel 755 für Ordner und 644 für Dateien)
3. Überprüfen Sie die WordPress-Fehlerprotokolle für weitere Informationen

## Alternative: Lokale Composer-Installation

Wenn Sie Composer lokal auf Ihrem Computer installiert haben, können Sie auch:

1. Das Plugin auf Ihren Computer herunterladen
2. `composer install` im Plugin-Verzeichnis ausführen
3. Den gesamten Plugin-Ordner inklusive des `vendor`-Ordners auf Ihren Server hochladen
