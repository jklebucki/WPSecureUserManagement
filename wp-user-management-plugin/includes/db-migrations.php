<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Funkcja wykonująca migracje bazy danych
function wpum_run_migrations() {
    $migrations = array(
        '1.0' => 'wpum_migration_1_0',
        // Dodaj kolejne migracje w przyszłości:
        // '1.1' => 'wpum_migration_1_1',
    );
    
    $current_db_version = get_option('wpum_db_version', '0');
    
    foreach ($migrations as $version => $migration_function) {
        if (version_compare($current_db_version, $version, '<')) {
            if (function_exists($migration_function)) {
                wpum_log("Wykonywanie migracji do wersji $version");
                call_user_func($migration_function);
            }
        }
    }
}

// Migracja do wersji 1.0
function wpum_migration_1_0() {
    global $wpdb;
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    
    $charset_collate = $wpdb->get_charset_collate();
    $table_name = $wpdb->prefix . 'wpum_shooting_credentials';
    
    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        user_id bigint(20) NOT NULL,
        credential_type varchar(50) NOT NULL,
        credential_number varchar(100) NOT NULL,
        file_path varchar(255) NOT NULL,
        uploaded_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        KEY user_id (user_id)
    ) $charset_collate;";
    
    dbDelta($sql);
}

// Przykład przyszłej migracji (zakomentowany)
/*
function wpum_migration_1_1() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'wpum_shooting_credentials';
    
    // Przykład dodania nowej kolumny
    $wpdb->query("ALTER TABLE $table_name 
                  ADD COLUMN expiration_date DATE NULL DEFAULT NULL 
                  AFTER uploaded_at");
}
*/ 