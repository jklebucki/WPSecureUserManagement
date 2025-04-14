<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Create tables on plugin activation
function wpum_create_shooting_credentials_table() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();
    
    $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}wpum_shooting_credentials (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        user_id bigint(20) NOT NULL,
        credential_type varchar(50) NOT NULL,
        credential_number varchar(100) NOT NULL,
        file_path varchar(255) NOT NULL,
        uploaded_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        KEY user_id (user_id)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

// Register shooting credential types
function wpum_get_shooting_credential_types() {
    return array(
        'shooting_patent' => __('Shooting Patent Number', 'wp-user-management-plugin'),
        'instructor_license' => __('Shooting Instructor License Number', 'wp-user-management-plugin')
    );
}

// Handle file upload
function wpum_handle_credential_file_upload($file, $user_id, $credential_type) {
    wpum_log("Rozpoczęcie przesyłania pliku:");
    wpum_log("- typ pliku: " . $file['type']);
    wpum_log("- rozmiar: " . $file['size']);
    wpum_log("- nazwa tymczasowa: " . $file['tmp_name']);
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        wpum_log("Błąd przesyłania pliku: " . $file['error']);
        return new WP_Error('upload_error', __('File upload failed', 'wp-user-management-plugin'));
    }

    // Verify file type
    $file_type = wp_check_filetype($file['name'], array('pdf' => 'application/pdf'));
    if ($file_type['type'] !== 'application/pdf') {
        wpum_log("Nieprawidłowy typ pliku: " . $file_type['type']);
        return new WP_Error('invalid_type', __('Only PDF files are allowed', 'wp-user-management-plugin'));
    }

    // Create upload directory if it doesn't exist
    $upload_dir = wp_upload_dir();
    $credential_dir = $upload_dir['basedir'] . '/shooting-credentials/' . $user_id;
    
    wpum_log("Katalog docelowy: " . $credential_dir);
    
    if (!file_exists($credential_dir)) {
        if (!wp_mkdir_p($credential_dir)) {
            wpum_log("Nie można utworzyć katalogu: " . $credential_dir);
            return new WP_Error('directory_error', __('Cannot create upload directory', 'wp-user-management-plugin'));
        }
    }
    
    // Sprawdź uprawnienia do zapisu
    if (!is_writable($credential_dir)) {
        wpum_log("Brak uprawnień do zapisu w katalogu: " . $credential_dir);
        return new WP_Error('permission_error', __('Cannot write to upload directory', 'wp-user-management-plugin'));
    }

    // Generate unique filename
    $filename = sanitize_file_name($credential_type . '-' . time() . '.pdf');
    $file_path = $credential_dir . '/' . $filename;
    
    wpum_log("Próba przeniesienia pliku do: " . $file_path);

    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $file_path)) {
        wpum_log("Nie udało się przenieść pliku do: " . $file_path);
        return new WP_Error('move_error', __('Failed to save file', 'wp-user-management-plugin'));
    }

    wpum_log("Plik został pomyślnie przeniesiony");
    return str_replace($upload_dir['basedir'], '', $file_path);
}

// Save credential data
function wpum_save_shooting_credential($user_id, $credential_type, $credential_number, $file_path) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'wpum_shooting_credentials';
    
    wpum_log("Rozpoczęcie zapisu uprawnienia:");
    wpum_log("- user_id: " . $user_id);
    wpum_log("- credential_type: " . $credential_type);
    wpum_log("- credential_number: " . $credential_number);
    wpum_log("- file_path: " . $file_path);
    
    // Sprawdź czy tabela istnieje
    if (!wpum_table_exists($table_name)) {
        wpum_log("Tabela $table_name nie istnieje!");
        return false;
    }
    
    // Usuń istniejące uprawnienie tego samego typu
    $delete_result = $wpdb->delete(
        $table_name,
        array(
            'user_id' => $user_id,
            'credential_type' => $credential_type
        ),
        array('%d', '%s')
    );
    
    wpum_log("Wynik usunięcia starego uprawnienia: " . ($delete_result !== false ? 'sukces' : 'błąd'));
    if ($delete_result === false) {
        wpum_log("Błąd podczas usuwania: " . $wpdb->last_error);
    }
    
    // Dodaj nowe uprawnienie
    $insert_result = $wpdb->insert(
        $table_name,
        array(
            'user_id' => $user_id,
            'credential_type' => $credential_type,
            'credential_number' => $credential_number,
            'file_path' => $file_path
        ),
        array('%d', '%s', '%s', '%s')
    );
    
    if ($insert_result === false) {
        wpum_log("Błąd podczas zapisywania uprawnienia: " . $wpdb->last_error);
        return false;
    }
    
    wpum_log("Uprawnienie zostało zapisane pomyślnie. ID: " . $wpdb->insert_id);
    return true;
}

// Get user credentials
function wpum_get_user_credentials($user_id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'wpum_shooting_credentials';
    
    // Sprawdź czy tabela istnieje
    if (!wpum_table_exists($table_name)) {
        wpum_log("Tabela $table_name nie istnieje - próba utworzenia");
        if (!function_exists('wpum_create_tables')) {
            wpum_log("Funkcja wpum_create_tables nie jest dostępna");
            return array();
        }
        if (!wpum_create_tables()) {
            wpum_log("Nie można utworzyć tabeli $table_name");
            return array();
        }
    }
    
    $results = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table_name WHERE user_id = %d",
        $user_id
    ));
    
    return $results ?: array();
}

// Obsługa formularza uprawnień
function wpum_process_shooting_credentials() {
    wpum_log("Rozpoczęcie przetwarzania formularza uprawnień strzeleckich");
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        wpum_log("Otrzymano żądanie POST");
        wpum_log("POST data: " . print_r($_POST, true));
        wpum_log("FILES data: " . print_r($_FILES, true));
        
        // Sprawdź czy to nasz formularz
        if (isset($_POST['wpum_action']) && $_POST['wpum_action'] === 'save_shooting_credentials') {
            wpum_log("Wykryto akcję zapisu uprawnień strzeleckich");
            
            // Sprawdź nonce
            if (!isset($_POST['wpum_shooting_credentials_nonce']) || 
                !wp_verify_nonce($_POST['wpum_shooting_credentials_nonce'], 'wpum_shooting_credentials')) {
                wpum_log("Błąd weryfikacji nonce");
                wp_die(__('Security check failed!', 'wp-user-management-plugin'));
            }
            
            $user_id = get_current_user_id();
            if (!$user_id) {
                wpum_log("Brak zalogowanego użytkownika");
                wp_die(__('You must be logged in to perform this action.', 'wp-user-management-plugin'));
            }
            
            wpum_log("Przetwarzanie dla użytkownika ID: " . $user_id);
            
            if (isset($_POST['wpum_credentials']) && is_array($_POST['wpum_credentials'])) {
                foreach ($_POST['wpum_credentials'] as $type => $data) {
                    wpum_log("Przetwarzanie typu: " . $type);
                    
                    if (!empty($data['number'])) {
                        $credential_number = sanitize_text_field($data['number']);
                        $file_key = "wpum_credentials_{$type}_file";
                        
                        if (!empty($_FILES[$file_key]['name'])) {
                            wpum_log("Znaleziono nowy plik dla typu: " . $type);
                            $upload_result = wpum_handle_credential_file_upload($_FILES[$file_key], $user_id, $type);
                            
                            if (is_wp_error($upload_result)) {
                                wpum_log("Błąd uploadu: " . $upload_result->get_error_message());
                                wp_die($upload_result->get_error_message());
                            }
                            
                            if (!wpum_save_shooting_credential($user_id, $type, $credential_number, $upload_result)) {
                                wpum_log("Błąd zapisu do bazy");
                                wp_die(__('Failed to save credential data.', 'wp-user-management-plugin'));
                            }
                        } else {
                            // Aktualizacja bez pliku
                            $existing_credentials = wpum_get_user_credentials($user_id);
                            $current_credential = null;
                            
                            foreach ($existing_credentials as $cred) {
                                if ($cred->credential_type === $type) {
                                    $current_credential = $cred;
                                    break;
                                }
                            }
                            
                            if ($current_credential) {
                                if (!wpum_save_shooting_credential($user_id, $type, $credential_number, $current_credential->file_path)) {
                                    wpum_log("Błąd aktualizacji danych");
                                    wp_die(__('Failed to update credential data.', 'wp-user-management-plugin'));
                                }
                            } else if (!empty($_FILES[$file_key]['name'])) {
                                wpum_log("Próba utworzenia nowego uprawnienia bez pliku");
                                wp_die(__('PDF file is required for new credentials.', 'wp-user-management-plugin'));
                            }
                        }
                    }
                }
            }
            
            wpum_log("Zakończono przetwarzanie formularza pomyślnie");
            wp_safe_redirect(add_query_arg('updated', '1', $_SERVER['REQUEST_URI']));
            exit;
        }
    }
}
add_action('init', 'wpum_process_shooting_credentials', 20); 