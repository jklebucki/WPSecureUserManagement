<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Funkcja wykonująca migracje bazy danych. Zwraca true tylko gdy wszystkie migracje się powiodły.
function wpum_run_migrations() {
    $migrations = array(
        '1.0' => 'wpum_migration_1_0',
        '1.1' => 'wpum_migration_1_1',
        '1.2' => 'wpum_migration_1_2',
        // Dodaj kolejne migracje w przyszłości:
        // '1.3' => 'wpum_migration_1_3',
    );

    $current_db_version = get_option('wpum_db_version', '0');

    foreach ($migrations as $version => $migration_function) {
        if (version_compare($current_db_version, $version, '<')) {
            if (function_exists($migration_function)) {
                wpum_log("Wykonywanie migracji do wersji $version");
                if (false === call_user_func($migration_function)) {
                    wpum_log("Migracja do wersji $version nie powiodła się");
                    return false;
                }
            }
        }
    }

    return true;
}

// Migracja do wersji 1.0 - tabela uprawnień
function wpum_migration_1_0() {
    return (bool) wpum_create_shooting_credentials_table();
}

/**
 * Migracja do wersji 1.1 - dokumenty uprawnień przenoszone do prywatnego katalogu.
 *
 * - pliki z uploads/shooting-credentials/{user}/ trafiają do uploads/wpum-private/{user}/ pod losowymi nazwami,
 * - wpisy z "pustym" plikiem (…-empty.pdf) dostają file_path = '',
 * - pliki osierocone (nigdy niekasowane stare wersje) trafiają do wpum-private/legacy/, żeby nie były publiczne,
 * - dotychczasowe kopie zapasowe tabeli (…_backup_…) są tylko zapamiętywane do pokazania ostrzeżenia w panelu.
 */
function wpum_migration_1_1() {
    global $wpdb;

    // Schemat (m.in. DEFAULT '' dla file_path)
    if (!wpum_create_shooting_credentials_table()) {
        return false;
    }

    $private_dir = wpum_ensure_private_dir();
    if (!$private_dir) {
        return false;
    }

    $table_name  = $wpdb->prefix . 'wpum_shooting_credentials';
    $upload_dir  = wp_upload_dir();
    $old_root    = untrailingslashit($upload_dir['basedir']) . '/shooting-credentials';
    $real_old    = realpath($old_root);

    $rows = $wpdb->get_results("SELECT id, user_id, file_path FROM $table_name");
    foreach ((array) $rows as $row) {
        $path = (string) $row->file_path;
        if ('' === $path) {
            continue;
        }

        if ('-empty.pdf' === substr($path, -10)) {
            $wpdb->update($table_name, array('file_path' => ''), array('id' => (int) $row->id), array('%s'), array('%d'));
            continue;
        }

        // Już nowy format (względem prywatnego katalogu)
        if (0 !== strpos($path, '/shooting-credentials/')) {
            continue;
        }

        $source = realpath(untrailingslashit($upload_dir['basedir']) . $path);
        if (!$real_old || !$source || 0 !== strpos($source, $real_old . DIRECTORY_SEPARATOR) || !is_file($source)) {
            wpum_log('Migracja 1.1: brak pliku dla wpisu ' . (int) $row->id . ', czyszczę file_path');
            $wpdb->update($table_name, array('file_path' => ''), array('id' => (int) $row->id), array('%s'), array('%d'));
            continue;
        }

        $user_id  = (int) $row->user_id;
        $dest_dir = $private_dir . '/' . $user_id;
        if (!file_exists($dest_dir) && !wp_mkdir_p($dest_dir)) {
            return false;
        }
        wpum_protect_directory($dest_dir);

        $relative = $user_id . '/' . wpum_generate_private_filename();
        $dest     = $private_dir . '/' . $relative;

        if (!@rename($source, $dest)) {
            if (!@copy($source, $dest)) {
                wpum_log('Migracja 1.1: nie udało się przenieść pliku wpisu ' . (int) $row->id);
                return false;
            }
            @unlink($source);
        }

        $wpdb->update($table_name, array('file_path' => $relative), array('id' => (int) $row->id), array('%s'), array('%d'));
    }

    // Wszystko, co zostało w starym katalogu, to pliki osierocone - przenosimy je do prywatnego katalogu "legacy".
    if ($real_old && is_dir($real_old)) {
        wpum_migrate_legacy_tree($real_old, $private_dir . '/legacy');
    }

    // Kopie zapasowe tabeli tworzone przez stare wersje zawierają dane osobowe - zapamiętaj do ostrzeżenia.
    $backups = $wpdb->get_col($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table_name . '_backup_') . '%'));
    if (!empty($backups)) {
        update_option('wpum_legacy_backup_tables', array_values($backups), false);
    }

    return true;
}

// Migracja do wersji 1.2 - tabela rejestracji oczekujących na potwierdzenie e-mail
function wpum_migration_1_2() {
    return (bool) wpum_create_pending_registrations_table();
}

// Przenosi rekurencyjnie pozostałe pliki do prywatnego katalogu i usuwa puste foldery.
function wpum_migrate_legacy_tree($source_root, $dest_root) {
    if (!file_exists($dest_root)) {
        wp_mkdir_p($dest_root);
    }
    wpum_protect_directory($dest_root);

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($source_root, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($iterator as $item) {
        $relative = ltrim(substr($item->getPathname(), strlen($source_root)), '/\\');
        if ($item->isDir()) {
            @rmdir($item->getPathname());
            continue;
        }

        $target = $dest_root . '/' . $relative;
        $target_dir = dirname($target);
        if (!file_exists($target_dir)) {
            wp_mkdir_p($target_dir);
        }
        if (!@rename($item->getPathname(), $target)) {
            if (@copy($item->getPathname(), $target)) {
                @unlink($item->getPathname());
            }
        }
    }

    @rmdir($source_root);
}

// Ostrzeżenie o kopiach zapasowych tabeli z danymi osobowymi (tworzonych przez wersje < 1.2.0).
function wpum_legacy_backup_notice() {
    if (!current_user_can('manage_options')) {
        return;
    }

    $tables = get_option('wpum_legacy_backup_tables', array());
    if (empty($tables) || !is_array($tables)) {
        return;
    }

    $dismiss_url = wp_nonce_url(admin_url('admin-post.php?action=wpum_dismiss_backup_notice'), 'wpum_dismiss_backup_notice');
    ?>
    <div class="notice notice-warning">
        <p>
            <strong><?php esc_html_e('WP User Management:', 'wp-user-management-plugin'); ?></strong>
            <?php esc_html_e('older plugin versions created copies of the credentials table containing personal data. They are no longer needed and can be dropped manually (e.g. in phpMyAdmin):', 'wp-user-management-plugin'); ?>
            <code><?php echo esc_html(implode(', ', $tables)); ?></code>
            <a href="<?php echo esc_url($dismiss_url); ?>"><?php esc_html_e('Dismiss', 'wp-user-management-plugin'); ?></a>
        </p>
    </div>
    <?php
}
add_action('admin_notices', 'wpum_legacy_backup_notice');

function wpum_dismiss_backup_notice() {
    check_admin_referer('wpum_dismiss_backup_notice');
    if (current_user_can('manage_options')) {
        delete_option('wpum_legacy_backup_tables');
    }
    wp_safe_redirect(wp_get_referer() ? wp_get_referer() : admin_url());
    exit;
}
add_action('admin_post_wpum_dismiss_backup_notice', 'wpum_dismiss_backup_notice');
