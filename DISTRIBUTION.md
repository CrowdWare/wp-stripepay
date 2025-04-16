# Plugin-Distribution mit eingebundener Stripe-Bibliothek

## Option 1: Stripe-Bibliothek in die ZIP-Datei einbinden (empfohlen für die meisten Benutzer)

1. Stellen Sie sicher, dass die Stripe-Bibliothek korrekt installiert ist:
   ```
   vendor/
   └── stripe/
       └── stripe-php/
           ├── init.php
           └── ...
   ```

2. Erstellen Sie eine ZIP-Datei des gesamten Plugin-Verzeichnisses, einschließlich des vendor-Ordners:
   ```bash
   zip -r wp-stripepay.zip wp-stripepay/ -x "wp-stripepay/.git/*"
   ```

3. Diese ZIP-Datei kann dann direkt in WordPress installiert werden, ohne dass der Benutzer Composer verwenden muss.

## Option 2: Stripe-Bibliothek separat installieren lassen (für fortgeschrittene Benutzer)

1. Erstellen Sie eine ZIP-Datei ohne den vendor-Ordner:
   ```bash
   zip -r wp-stripepay.zip wp-stripepay/ -x "wp-stripepay/.git/*" "wp-stripepay/vendor/*"
   ```

2. Fügen Sie in Ihrer README.md oder Installationsanleitung einen Hinweis hinzu, dass der Benutzer Composer verwenden muss, um die Abhängigkeiten zu installieren:
   ```
   Nach der Installation des Plugins müssen Sie die Abhängigkeiten mit Composer installieren:
   cd wp-content/plugins/wp-stripepay
   composer install
   ```

## Empfehlung

Für die meisten Anwendungsfälle empfehlen wir Option 1, da sie die einfachste Installation für Endbenutzer bietet. Wenn Ihre Zielgruppe jedoch technisch versiert ist oder Sie regelmäßige Updates der Stripe-Bibliothek erwarten, könnte Option 2 besser geeignet sein.

## Hinweis zur Versionierung

Wenn Sie die Stripe-Bibliothek mit einbinden, sollten Sie in Ihrer Plugin-Dokumentation die Version der eingebundenen Stripe-Bibliothek angeben, damit Benutzer wissen, ob sie aktuell ist oder nicht.

Sie können die Version der Stripe-Bibliothek mit folgendem Befehl überprüfen:
```bash
cd vendor/stripe/stripe-php
cat VERSION
```

Oder in PHP:
```php
echo \Stripe\Stripe::VERSION;
