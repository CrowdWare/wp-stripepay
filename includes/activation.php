<?php
/**
 * Aktivierung: Erstellen der benötigten Datenbanktabellen
 */
function stripepay_activate() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();

    // Tabellenname für Produkte, Autoren und Käufe
    $products_table = $wpdb->prefix . 'stripepay_products';
    $authors_table  = $wpdb->prefix . 'stripepay_authors';
    $purchases_table = $wpdb->prefix . 'stripepay_purchases';

    // SQL für Tabelle Produkte (neues Feld download_url wurde hinzugefügt)
    $sql_products = "CREATE TABLE $products_table (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        name varchar(255) NOT NULL,
        subtitle varchar(255) DEFAULT NULL,
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

    // SQL für Tabelle Käufe
    $sql_purchases = "CREATE TABLE $purchases_table (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        product_id bigint(20) NOT NULL,
        email varchar(255) NOT NULL,
        amount int NOT NULL,
        payment_intent_id varchar(255) DEFAULT NULL,
        payment_status varchar(50) DEFAULT 'pending',
        download_token varchar(255) DEFAULT NULL,
        download_expiry datetime DEFAULT NULL,
        download_count int DEFAULT 0,
        purchase_date datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY product_id (product_id),
        KEY email (email),
        KEY payment_status (payment_status)
    ) $charset_collate;";

    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    dbDelta( $sql_products );
    dbDelta( $sql_authors );
    dbDelta( $sql_purchases );
}
