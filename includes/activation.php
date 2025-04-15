<?php
/**
 * Aktivierung: Erstellen der benötigten Datenbanktabellen
 */
function stripepay_activate() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();

    // Tabellenname für Produkte und Autoren
    $products_table = $wpdb->prefix . 'stripepay_products';
    $authors_table  = $wpdb->prefix . 'stripepay_authors';

    // SQL für Tabelle Produkte (neues Feld download_url wurde hinzugefügt)
    $sql_products = "CREATE TABLE $products_table (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        name varchar(255) NOT NULL,
        price int NOT NULL,
        image varchar(255) DEFAULT NULL,
        kurztext text,
        langtext text,
        life tinyint(1) DEFAULT 0,
        categories text,
        download_url varchar(255) DEFAULT NULL,
        creation_date datetime DEFAULT CURRENT_TIMESTAMP,
        author_id bigint(20) DEFAULT NULL,
        PRIMARY KEY (id)
    ) $charset_collate;";

    // SQL für Tabelle Autoren
    $sql_authors = "CREATE TABLE $authors_table (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        name varchar(255) NOT NULL,
        image varchar(255) DEFAULT NULL,
        bio text,
        PRIMARY KEY (id)
    ) $charset_collate;";

    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    dbDelta( $sql_products );
    dbDelta( $sql_authors );
}
