<?php
/**
 * Admin-Bereich Funktionen für WP StripePay.
 */

/**
 * Registriert das Admin-Menü.
 */
function stripepay_admin_menu() {
    // Hauptmenü für Einstellungen (Stripe API Keys)
    add_menu_page(
        'StripePay Einstellungen',
        'StripePay',
        'manage_options',
        'stripepay-settings',
        'stripepay_settings_page'
    );

    // Untermenü: Produkte verwalten
    add_submenu_page(
        'stripepay-settings',
        'Produkte verwalten',
        'Produkte',
        'manage_options',
        'stripepay-products',
        'stripepay_products_page'
    );

    // Untermenü: Autoren verwalten
    add_submenu_page(
        'stripepay-settings',
        'Autoren verwalten',
        'Autoren',
        'manage_options',
        'stripepay-authors',
        'stripepay_authors_page'
    );
}
add_action( 'admin_menu', 'stripepay_admin_menu' );

/**
 * Einstellungsseite: Stripe API Keys.
 */
function stripepay_settings_page() {
    if ( isset( $_POST['stripepay_nonce'] ) && wp_verify_nonce( $_POST['stripepay_nonce'], 'stripepay_save_settings' ) ) {
        update_option( 'stripepay_stripe_live_key', sanitize_text_field( $_POST['stripepay_stripe_live_key'] ) );
        update_option( 'stripepay_stripe_test_key', sanitize_text_field( $_POST['stripepay_stripe_test_key'] ) );
        update_option( 'stripepay_stripe_live_publishable_key', sanitize_text_field( $_POST['stripepay_stripe_live_publishable_key'] ) );
        update_option( 'stripepay_stripe_test_publishable_key', sanitize_text_field( $_POST['stripepay_stripe_test_publishable_key'] ) );
        update_option( 'stripepay_webhook_secret', sanitize_text_field( $_POST['stripepay_webhook_secret'] ) );
        update_option( 'stripepay_webhook_secret_test', sanitize_text_field( $_POST['stripepay_webhook_secret_test'] ) );
        echo '<div class="updated"><p>Einstellungen gespeichert.</p></div>';
    }
    $live_key = get_option( 'stripepay_stripe_live_key', '' );
    $test_key = get_option( 'stripepay_stripe_test_key', '' );
    $live_publishable_key = get_option( 'stripepay_stripe_live_publishable_key', '' );
    $test_publishable_key = get_option( 'stripepay_stripe_test_publishable_key', '' );
    $webhook_secret = get_option( 'stripepay_webhook_secret', '' );
    ?>
    <div class="wrap">
        <h1>StripePay Einstellungen</h1>
        <form method="post" action="">
            <?php wp_nonce_field( 'stripepay_save_settings', 'stripepay_nonce' ); ?>
            <table class="form-table">
                <tr>
                    <th scope="row">Modus</th>
                    <td>
                        <p class="description">Der Modus (Live oder Test) wird jetzt pro Produkt bestimmt. Setzen Sie das Feld "Bezahlmodus" in den Produkteinstellungen auf "Live" oder "Test".</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Stripe Live Secret Key</th>
                    <td><input type="text" name="stripepay_stripe_live_key" value="<?php echo esc_attr( $live_key ); ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th scope="row">Stripe Live Publishable Key</th>
                    <td><input type="text" name="stripepay_stripe_live_publishable_key" value="<?php echo esc_attr( $live_publishable_key ); ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th scope="row">Stripe Test Secret Key</th>
                    <td><input type="text" name="stripepay_stripe_test_key" value="<?php echo esc_attr( $test_key ); ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th scope="row">Stripe Test Publishable Key</th>
                    <td><input type="text" name="stripepay_stripe_test_publishable_key" value="<?php echo esc_attr( $test_publishable_key ); ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th scope="row">Stripe Live Webhook Secret</th>
                    <td>
                        <input type="text" name="stripepay_webhook_secret" value="<?php echo esc_attr( $webhook_secret ); ?>" class="regular-text">
                        <p class="description">Webhook-Secret für die Verifizierung von Stripe-Events im Live-Modus.</p>
                        <p>Live Webhook-URL: <code><?php echo esc_html( admin_url( 'admin-ajax.php?action=stripepay_webhook' ) ); ?></code></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Stripe Test Webhook Secret</th>
                    <td>
                        <input type="text" name="stripepay_webhook_secret_test" value="<?php echo esc_attr( get_option( 'stripepay_webhook_secret_test', '' ) ); ?>" class="regular-text">
                        <p class="description">Webhook-Secret für die Verifizierung von Stripe-Events im Test-Modus.</p>
                        <p>Test Webhook-URL: <code><?php echo esc_html( admin_url( 'admin-ajax.php?action=stripepay_webhook_test' ) ); ?></code></p>
                    </td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}

/**
 * Admin-Seite: Produkte verwalten.
 */

function stripepay_products_page() {
    global $wpdb;
    wp_enqueue_media();
    $products_table = $wpdb->prefix . 'stripepay_products';
    $authors_table = $wpdb->prefix . 'stripepay_authors';

    $action = $_GET['action'] ?? null;
    $edit_id = isset($_GET['id']) ? intval($_GET['id']) : null;

    // Speichern
    if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['product_name'])) {
        $name = sanitize_text_field($_POST['product_name']);
        $subtitle = sanitize_text_field($_POST['product_subtitle']);
        $price = intval($_POST['product_price']);
        $image = sanitize_text_field($_POST['product_image']);
        $kurztext = sanitize_textarea_field($_POST['product_kurztext']);
        $langtext = sanitize_textarea_field($_POST['product_langtext']);
        $life = intval($_POST['product_life']);
        $categories = sanitize_text_field($_POST['product_categories']);
        $download_url = sanitize_text_field($_POST['product_download']);
        $author_id = intval($_POST['product_author']);

        $data = compact('name', 'subtitle', 'price', 'image', 'kurztext', 'langtext', 'life', 'categories', 'download_url', 'author_id');

        if ($edit_id) {
            $wpdb->update($products_table, $data, ['id' => $edit_id]);
            echo '<div class="updated"><p>Produkt aktualisiert.</p></div>';
        } else {
            $wpdb->insert($products_table, $data);
            echo '<div class="updated"><p>Produkt hinzugefügt.</p></div>';
        }

        $action = null;
    }

    // Löschen
    if ($action === 'delete' && $edit_id) {
        $wpdb->delete($products_table, ['id' => $edit_id]);
        echo '<div class="updated"><p>Produkt gelöscht.</p></div>';
        $action = null;
    }

    ?>
    <div class="wrap">
        <h1>Produkte verwalten</h1>

        <?php if ($action === 'new' || ($action === 'edit' && $edit_id)) :

            $product = [
                'name' => '', 'subtitle' => '', 'price' => '', 'image' => '', 'kurztext' => '',
                'langtext' => '', 'life' => 1, 'categories' => '', 'download_url' => '', 'author_id' => ''
            ];
            if ($action === 'edit') {
                $product = $wpdb->get_row($wpdb->prepare("SELECT * FROM $products_table WHERE id = %d", $edit_id), ARRAY_A);
            }
            ?>
            <h2><?php echo $action === 'new' ? 'Neues Produkt hinzufügen' : 'Produkt bearbeiten'; ?></h2>
            <form method="post">
                <?php wp_nonce_field('stripepay_save_product', 'stripepay_product_nonce'); ?>
                <table class="form-table">
                    <tr><th>Name</th>
                        <td><input type="text" name="product_name" value="<?php echo esc_attr($product['name']); ?>" required></td>
                    </tr>
                    <tr><th>Untertitel</th>
                        <td><input type="text" name="product_subtitle" value="<?php echo esc_attr($product['subtitle']); ?>"></td>
                    </tr>
                    <tr><th>Preis (in Cent)</th>
                        <td><input type="number" name="product_price" value="<?php echo esc_attr($product['price']); ?>" required></td>
                    </tr>
                    <tr><th>Bild URL</th>
                        <td>
                            <input type="text" id="product_image" name="product_image" value="<?php echo esc_attr($product['image']); ?>">
                            <button type="button" class="upload_button" data-target="product_image">Bild auswählen</button>
                        </td>
                    </tr>
                    <tr><th>Download URL</th>
                        <td>
                            <input type="text" id="product_download" name="product_download" value="<?php echo esc_attr($product['download_url']); ?>">
                            <button type="button" class="upload_button" data-target="product_download">Datei hochladen</button>
                        </td>
                    </tr>
                    <tr><th>Kurztext</th>
                        <td><textarea name="product_kurztext"><?php echo esc_textarea($product['kurztext']); ?></textarea></td>
                    </tr>
                    <tr><th>Langtext</th>
                        <td><textarea name="product_langtext"><?php echo esc_textarea($product['langtext']); ?></textarea></td>
                    </tr>
                    <tr><th>Bezahlmodus</th>
                        <td>
                            <select name="product_life">
                                <option value="1" <?php selected($product['life'], 1); ?>>Live</option>
                                <option value="0" <?php selected($product['life'], 0); ?>>Test</option>
                            </select>
                        </td>
                    </tr>
                    <tr><th>Kategorien (Komma-getrennt)</th>
                        <td><input type="text" name="product_categories" value="<?php echo esc_attr($product['categories']); ?>"></td>
                    </tr>
                    <tr><th>Autor</th>
                        <td>
                            <?php
                            $authors = $wpdb->get_results("SELECT id, name FROM $authors_table");
                            if ($authors) {
                                echo '<select name="product_author">';
                                foreach ($authors as $author) {
                                    $selected = selected($product['author_id'], $author->id, false);
                                    echo '<option value="' . esc_attr($author->id) . '" ' . $selected . '>' . esc_html($author->name) . '</option>';
                                }
                                echo '</select>';
                            } else {
                                echo 'Keine Autoren gefunden.';
                            }
                            ?>
                        </td>
                    </tr>
                </table>
                <input type="hidden" name="id" value="<?php echo esc_attr($edit_id); ?>">
                <?php submit_button($action === 'new' ? 'Produkt hinzufügen' : 'Änderungen speichern'); ?>
            </form>
            <p><a href="?page=<?php echo esc_attr($_GET['page']); ?>">Zurück zur Liste</a></p>

        <?php else : ?>
            <p><a href="?page=<?php echo esc_attr($_GET['page']); ?>&action=new" class="button button-primary">Neues Produkt anlegen</a></p>

            <table class="widefat striped">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Untertitel</th>
                        <th>Kurztext</th>
                        <th>Bild</th>
                        <th>Preis</th>
                        <th>Autor</th>
                        <th>Bezahlmodus</th>
                        <th>Aktionen</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $products = $wpdb->get_results("SELECT p.*, a.name AS author_name FROM $products_table p LEFT JOIN $authors_table a ON p.author_id = a.id");
                    if ($products) :
                        foreach ($products as $p) :
                            ?>
                            <tr>
                                <td><?php echo esc_html($p->name); ?></td>
                                <td><?php echo esc_html($p->subtitle); ?></td>
                                <td><?php echo esc_html($p->kurztext); ?></td>
                                <td><img src="<?php echo esc_html($p->image); ?>" style="height: 60px; width: auto;" alt="Produktbild"></td>
                                <td><?php echo number_format($p->price / 100, 2, ',', '.') . ' €'; ?></td>
                                <td><?php echo esc_html($p->author_name); ?></td>
                                <td><?php echo $p->life ? 'Live' : 'Test'; ?></td>
                                <td>
                                    <a href="?page=<?php echo esc_attr($_GET['page']); ?>&action=edit&id=<?php echo $p->id; ?>">Edit</a> |
                                    <a href="?page=<?php echo esc_attr($_GET['page']); ?>&action=delete&id=<?php echo $p->id; ?>" onclick="return confirm('Wirklich löschen?');">Delete</a>
                                </td>
                            </tr>
                        <?php endforeach;
                    else : ?>
                        <tr><td colspan="4">Keine Produkte gefunden.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    <script>
    jQuery(document).ready(function($){
        $('.upload_button').click(function(e){
            e.preventDefault();
            var target = $(this).data('target');
            var custom_uploader = wp.media({
                title: 'Datei auswählen',
                button: { text: 'Auswählen' },
                multiple: false 
            }).on('select', function(){
                var attachment = custom_uploader.state().get('selection').first().toJSON();
                $('#' + target).val(attachment.url);
            }).open();
        });
    });
    </script>
    <?php
}



/**
 * Admin-Seite: Autoren verwalten.
 */

function stripepay_authors_page() {
    global $wpdb;
    wp_enqueue_media();
    $table_name = $wpdb->prefix . 'stripepay_authors';
    $products_table = $wpdb->prefix . 'stripepay_products';

    $action = $_GET['action'] ?? null;
    $edit_id = isset($_GET['id']) ? intval($_GET['id']) : null;

    // Formularverarbeitung
    if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['author_submit'])) {
        $name = sanitize_text_field($_POST['author_name']);
        $image = sanitize_text_field($_POST['author_image']);
        $bio = sanitize_textarea_field($_POST['author_bio']);
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;

        if ($id > 0) {
            $wpdb->update($table_name, [
                'name' => $name,
                'image' => $image,
                'bio' => $bio
            ], ['id' => $id]);
            echo "<div class='updated'><p>Autor aktualisiert.</p></div>";
        } else {
            $wpdb->insert($table_name, [
                'name' => $name,
                'image' => $image,
                'bio' => $bio
            ]);
            echo "<div class='updated'><p>Neuer Autor angelegt.</p></div>";
        }

        $action = null;
    }

    // Delete
    if ($action === 'delete' && $edit_id) {
        $product_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $products_table WHERE author_id = %d",
            $edit_id
        ));

        if ($product_count == 0) {
            $wpdb->delete($table_name, ['id' => $edit_id]);
            echo "<div class='updated'><p>Autor gelöscht.</p></div>";
        } else {
            echo "<div class='error'><p>Autor kann nicht gelöscht werden, da Produkte vorhanden sind.</p></div>";
        }

        $action = null;
    }
    ?>
    <div class="wrap">
        <h2>Autorenverwaltung</h2>

        <?php if ($action === 'new' || ($action === 'edit' && $edit_id)) :
            $author = ['name' => '', 'image' => '', 'bio' => ''];
            if ($action === 'edit') {
                $author = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $edit_id), ARRAY_A);
            }
        ?>
            <h3><?php echo $action === 'new' ? 'Neuer Autor' : 'Autor bearbeiten'; ?></h3>
            <form method="post">
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="author_name">Name</label></th>
                        <td><input name="author_name" type="text" id="author_name" value="<?php echo esc_attr($author['name']); ?>" class="regular-text" required></td>
                    </tr>
                   
                    <tr>
                        <th><label for="author_image">Bild</label></th>
                        <td>
                            <input type="text" id="author_image" name="author_image">
                            <button type="button" class="upload_button" data-target="author_image">Bild auswählen</button>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="author_bio">Biografie</label></th>
                        <td><textarea name="author_bio" id="author_bio" rows="5" class="large-text"><?php echo esc_textarea($author['bio']); ?></textarea></td>
                    </tr>
                </table>
                <input type="hidden" name="id" value="<?php echo esc_attr($edit_id); ?>">
                <p class="submit"><input type="submit" name="author_submit" class="button button-primary" value="Speichern"></p>
            </form>
            <p><a href="?page=<?php echo esc_attr($_GET['page']); ?>">Zurück zur Liste</a></p>

        <?php else : ?>
            <p><a href="?page=<?php echo esc_attr($_GET['page']); ?>&action=new" class="button button-primary">Neuen Autor anlegen</a></p>

            <table class="widefat fixed" cellspacing="0">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Bild</th>
                        <th>Biografie</th>
                        <th>Aktionen</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $authors = $wpdb->get_results("SELECT * FROM $table_name");
                    if ($authors) :
                        foreach ($authors as $author) :
                            $product_count = $wpdb->get_var($wpdb->prepare(
                                "SELECT COUNT(*) FROM $products_table WHERE author_id = %d",
                                $author->id
                            ));
                            $can_delete = $product_count == 0;
                            ?>
                            <tr>
                                <td><?php echo esc_html($author->name); ?></td>
                                <td><img src="<?php echo esc_html($author->image); ?>" style="height: 60px; width: auto;" alt="Bild"></td>
                                <td><?php echo esc_html($author->bio); ?></td>
                                <td>
                                    <a href="?page=<?php echo esc_attr($_GET['page']); ?>&action=edit&id=<?php echo $author->id; ?>">Edit</a> |
                                    <?php if ($can_delete) : ?>
                                        <a href="?page=<?php echo esc_attr($_GET['page']); ?>&action=delete&id=<?php echo $author->id; ?>" onclick="return confirm('Wirklich löschen?');">Delete</a>
                                    <?php else : ?>
                                        <span style="color:#999;">Delete</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach;
                    else : ?>
                        <tr><td colspan="4">Keine Autoren gefunden.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    <script>
    jQuery(document).ready(function ($) {
        $('.upload_button').click(function (e) {
            e.preventDefault();
            var target = $(this).data('target');
            var custom_uploader = wp.media({
                title: 'Bild auswählen',
                button: {
                    text: 'Bild verwenden'
                },
                multiple: false
            });

            custom_uploader.on('select', function () {
                var attachment = custom_uploader.state().get('selection').first().toJSON();
                $('#' + target).val(attachment.url);
            });

            custom_uploader.open();
        });
    });
    </script>
    <?php
}
