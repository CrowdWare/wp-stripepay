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
        echo '<div class="updated"><p>Einstellungen gespeichert.</p></div>';
    }
    $live_key = get_option( 'stripepay_stripe_live_key', '' );
    $test_key = get_option( 'stripepay_stripe_test_key', '' );
    ?>
    <div class="wrap">
        <h1>StripePay Einstellungen</h1>
        <form method="post" action="">
            <?php wp_nonce_field( 'stripepay_save_settings', 'stripepay_nonce' ); ?>
            <table class="form-table">
                <tr>
                    <th scope="row">Stripe Live API Key</th>
                    <td><input type="text" name="stripepay_stripe_live_key" value="<?php echo esc_attr( $live_key ); ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th scope="row">Stripe Test API Key</th>
                    <td><input type="text" name="stripepay_stripe_test_key" value="<?php echo esc_attr( $test_key ); ?>" class="regular-text"></td>
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

    if ( isset( $_POST['product_name'] ) ) {
        $name = sanitize_text_field( $_POST['product_name'] );
        $price = intval( $_POST['product_price'] );
        $image = sanitize_text_field( $_POST['product_image'] );
        $kurztext = sanitize_textarea_field( $_POST['product_kurztext'] );
        $langtext = sanitize_textarea_field( $_POST['product_langtext'] );
        $life = intval( $_POST['product_life'] );
        $categories = sanitize_text_field( $_POST['product_categories'] );
        $download_url = sanitize_text_field( $_POST['product_download'] );
        $author_id = intval( $_POST['product_author'] );

        $wpdb->insert( $products_table, array(
            'name'         => $name,
            'price'        => $price,
            'image'        => $image,
            'kurztext'     => $kurztext,
            'langtext'     => $langtext,
            'life'         => $life,
            'categories'   => $categories,
            'download_url' => $download_url,
            'author_id'    => $author_id
        ), array( '%s', '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s' ) );

        echo '<div class="updated"><p>Produkt hinzugefügt.</p></div>';
    }
    ?>
    <div class="wrap">
        <h1>Produkte verwalten</h1>
        <h2>Neues Produkt hinzufügen</h2>
        <form method="post">
            <table class="form-table">
                <tr>
                    <th>Name</th>
                    <td><input type="text" name="product_name" required></td>
                </tr>
                <tr>
                    <th>Preis (in Cent)</th>
                    <td><input type="number" name="product_price" required></td>
                </tr>
                <tr>
                    <th>Bild URL</th>
                    <td>
                        <input type="text" id="product_image" name="product_image">
                        <button type="button" class="upload_button" data-target="product_image">Bild auswählen</button>
                    </td>
                </tr>
                <tr>
                    <th>Download URL</th>
                    <td>
                        <input type="text" id="product_download" name="product_download">
                        <button type="button" class="upload_button" data-target="product_download">Datei hochladen</button>
                    </td>
                </tr>
                <tr>
                    <th>Kurztext</th>
                    <td><textarea name="product_kurztext"></textarea></td>
                </tr>
                <tr>
                    <th>Langtext</th>
                    <td><textarea name="product_langtext"></textarea></td>
                </tr>
                <tr>
                    <th>Bezahlmodus</th>
                    <td>
                        <select name="product_life">
                            <option value="1">Live</option>
                            <option value="0">Test</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th>Kategorien (Komma-getrennt)</th>
                    <td><input type="text" name="product_categories"></td>
                </tr>
                <tr>
                    <th>Autor (UUID)</th>
                    <td>
                        <?php
                        $authors_table = $wpdb->prefix . 'stripepay_authors';
                        $authors = $wpdb->get_results( "SELECT id, name FROM $authors_table" );
                        if ( $authors ) {
                            echo '<select name="product_author">';
                            foreach ( $authors as $author ) {
                                echo '<option value="' . esc_attr( $author->id ) . '">' . esc_html( $author->name ) . '</option>';
                            }
                            echo '</select>';
                        } else {
                            echo 'Keine Autoren gefunden.';
                        }
                        ?>
                    </td>
                </tr>
            </table>
            <?php submit_button( 'Produkt hinzufügen' ); ?>
        </form>
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
    $authors_table = $wpdb->prefix . 'stripepay_authors';

    if ( isset($_POST['stripepay_author_nonce']) && wp_verify_nonce($_POST['stripepay_author_nonce'], 'stripepay_save_author') && isset($_POST['author_name']) ) {
        $uuid = function_exists('wp_generate_uuid4') ? wp_generate_uuid4() : uniqid();
        $name = sanitize_text_field( $_POST['author_name'] );
        $image = sanitize_text_field( $_POST['author_image'] );
        $bio = sanitize_textarea_field( $_POST['author_bio'] );
        
        $result = $wpdb->insert( $authors_table, array(
            'uuid'  => $uuid,
            'name'  => $name,
            'image' => $image,
            'bio'   => $bio
        ), array( '%s', '%s', '%s', '%s' ) );
        
        if ( false === $result ) {
            echo '<div class="error"><p>Fehler beim Hinzufügen des Autors: ' . esc_html( $wpdb->last_error ) . '</p></div>';
        } else {
            echo '<div class="updated"><p>Autor hinzugefügt. ID: ' . esc_html( $wpdb->insert_id ) . '</p></div>';
        }
    }
    $author_count = $wpdb->get_var("SELECT COUNT(*) FROM $authors_table");
    ?>
    <div class="wrap">
        <h1>Autoren verwalten</h1>
        <p>Anzahl Autoren: <?php echo esc_html($author_count); ?></p>
        <form method="post">
            <table class="form-table">
                <tr>
                    <th>Name</th>
                    <td><input type="text" name="author_name" required></td>
                </tr>
                <tr>
                    <th>Bild URL</th>
                    <td>
                        <input type="text" id="author_image" name="author_image">
                        <button type="button" class="upload_button" data-target="author_image">Bild auswählen</button>
                    </td>
                </tr>
                <tr>
                    <th>Bio</th>
                    <td><textarea name="author_bio"></textarea></td>
                </tr>
            </table>
            <?php submit_button( 'Autor hinzufügen' ); ?>
        </form>
        <?php
        $authors = $wpdb->get_results( "SELECT * FROM $authors_table" );
        if ( $authors ) {
            echo '<h2>Autorenliste</h2>';
            echo '<table class="wp-list-table widefat fixed striped">';
            echo '<thead><tr>';
            echo '<th>ID</th>';
            echo '<th>UUID</th>';
            echo '<th>Name</th>';
            echo '<th>Aktionen</th>';
            echo '</tr></thead><tbody>';
            foreach ( $authors as $author ) {
                echo '<tr>';
                echo '<td>' . esc_html( $author->id ) . '</td>';
                echo '<td>' . esc_html( $author->uuid ) . '</td>';
                echo '<td>' . esc_html( $author->name ) . '</td>';
                echo '<td>Edit | Delete</td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
        } else {
            echo '<p>Keine Autoren gefunden.</p>';
        }
        ?>
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
