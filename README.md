# WP StripePay

Ein WordPress Plugin zum Verkauf von Büchern und digitalen Inhalten über Stripe. Es beinhaltet Admin-Bereiche für Stripe API Einstellungen, Produkte und Autoren sowie Shortcodes für die Anzeige einzelner Produkte und eines Produkt-Grids.

## Installation

1. Laden Sie das Plugin in das `/wp-content/plugins/` Verzeichnis hoch
2. Installieren Sie die Abhängigkeiten mit Composer:
   ```
   cd /wp-content/plugins/wp-stripepay
   composer install
   ```
3. Aktivieren Sie das Plugin über das 'Plugins' Menü in WordPress
4. Konfigurieren Sie Ihre Stripe API-Keys unter StripePay > Einstellungen

## Konfiguration

### Stripe API-Keys

1. Erstellen Sie ein Konto bei [Stripe](https://stripe.com) falls Sie noch keines haben
2. Gehen Sie zu den [API-Keys](https://dashboard.stripe.com/apikeys) in Ihrem Stripe Dashboard
3. Kopieren Sie die Secret Keys und Publishable Keys (sowohl für den Test- als auch für den Live-Modus)
4. Fügen Sie diese Keys in die StripePay Einstellungen ein

### Webhook einrichten

1. Gehen Sie zu [Webhooks](https://dashboard.stripe.com/webhooks) in Ihrem Stripe Dashboard
2. Klicken Sie auf "Add Endpoint"
3. Geben Sie die Webhook-URL ein, die in den StripePay Einstellungen angezeigt wird
4. Wählen Sie das Event `payment_intent.succeeded` aus
5. Kopieren Sie das Webhook-Secret und fügen Sie es in die StripePay Einstellungen ein

## Verwendung

### Produkte und Autoren verwalten

1. Gehen Sie zu StripePay > Autoren, um Autoren anzulegen
2. Gehen Sie zu StripePay > Produkte, um Produkte anzulegen
   - Geben Sie einen Namen, Preis (in Cent), Bild und Beschreibungen ein
   - Laden Sie die zu verkaufende Datei hoch und wählen Sie sie als Download-URL aus
   - Wählen Sie einen Autor und Kategorien für das Produkt

### Shortcodes

Das Plugin stellt folgende Shortcodes zur Verfügung:

1. `[stripepay_products_grid]` - Zeigt ein Grid aller Produkte an, mit Filterung nach Kategorien
2. `[stripepay_product]` - Zeigt ein einzelnes Produkt mit Kaufoption an (muss auf einer Seite mit dem Parameter `?id=X` in der URL verwendet werden)
3. `[stripepay_download]` - Zeigt eine Download-Seite an (muss auf einer Seite mit dem Namen "download" verwendet werden)

### Seiten einrichten

1. Erstellen Sie eine Seite "Produkte" und fügen Sie den Shortcode `[stripepay_products_grid]` ein
2. Erstellen Sie eine Seite "Produkt" und fügen Sie den Shortcode `[stripepay_product]` ein
3. Erstellen Sie eine Seite "Download" und fügen Sie den Shortcode `[stripepay_download]` ein

## Käufe verwalten

Unter StripePay > Käufe können Sie alle getätigten Käufe einsehen und verwalten:

- Sehen Sie alle Käufe mit Status, Betrag und Käufer-E-Mail
- Filtern Sie nach Produkt, Status oder E-Mail
- Erneuern Sie abgelaufene Download-Links

## Testmodus

1. Deaktivieren Sie die Option "Live-Modus aktivieren" in den Einstellungen, um den Testmodus zu verwenden
2. Im Testmodus können Sie Testkreditkarten verwenden:
   - Kreditkartennummer: 4242 4242 4242 4242
   - Ablaufdatum: Ein beliebiges zukünftiges Datum
   - CVC: Beliebige 3 Ziffern
   - PLZ: Beliebige 5 Ziffern

## Fehlerbehebung

### Die Stripe-Zahlung funktioniert nicht

1. Überprüfen Sie, ob die Stripe API-Keys korrekt eingegeben wurden
2. Stellen Sie sicher, dass der richtige Modus (Test oder Live) aktiviert ist
3. Überprüfen Sie die JavaScript-Konsole auf Fehler
4. Stellen Sie sicher, dass die Stripe PHP-Bibliothek korrekt installiert wurde (mit Composer)

### Download-Links funktionieren nicht

1. Stellen Sie sicher, dass die Download-URL des Produkts korrekt ist
2. Überprüfen Sie, ob der Download-Link abgelaufen ist (gültig für 7 Tage)
3. Stellen Sie sicher, dass die Seite mit dem Shortcode `[stripepay_download]` existiert und zugänglich ist
