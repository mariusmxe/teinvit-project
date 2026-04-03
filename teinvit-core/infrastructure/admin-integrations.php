<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function teinvit_admin_root_slug() {
    return 'teinvit-admin-hub';
}

function teinvit_admin_register_menu_pages() {
    add_menu_page(
        'TeInvit',
        'TeInvit',
        'manage_woocommerce',
        teinvit_admin_root_slug(),
        'teinvit_admin_render_integrations_page',
        'dashicons-email-alt2',
        57
    );

    add_submenu_page( teinvit_admin_root_slug(), 'Integrations', 'Integrations', 'manage_woocommerce', 'teinvit-integrations', 'teinvit_admin_render_integrations_page' );
    add_submenu_page( teinvit_admin_root_slug(), 'API Keys', 'API Keys', 'manage_woocommerce', 'teinvit-api-keys', 'teinvit_admin_render_api_keys_page' );
    add_submenu_page( teinvit_admin_root_slug(), 'Contacts', 'Contacts', 'manage_woocommerce', 'teinvit-contacts', 'teinvit_admin_render_contacts_page' );
    add_submenu_page( teinvit_admin_root_slug(), 'Reports', 'Reports', 'manage_woocommerce', 'teinvit-reports', 'teinvit_admin_render_reports_page' );
}
add_action( 'admin_menu', 'teinvit_admin_register_menu_pages', 25 );

function teinvit_admin_render_integrations_page() {
    if ( ! current_user_can( 'manage_woocommerce' ) ) {
        wp_die( 'Unauthorized' );
    }

    $provider_key = isset( $_GET['provider'] ) ? sanitize_key( (string) wp_unslash( $_GET['provider'] ) ) : 'newsman';
    $provider = teinvit_integrations_get_provider( $provider_key );
    if ( ! is_array( $provider ) ) {
        $provider_key = 'newsman';
        $provider = teinvit_integrations_get_provider( $provider_key );
    }

    $state = teinvit_integrations_get_state( $provider_key );
    $notice = '';

    if ( isset( $_POST['teinvit_integrations_save'] ) && check_admin_referer( 'teinvit_integrations_save' ) ) {
        $config = (array) ( $state['config'] ?? [] );
        $config['base_url'] = esc_url_raw( (string) ( $_POST['base_url'] ?? '' ) );
        $config['api_version'] = sanitize_text_field( (string) ( $_POST['api_version'] ?? '' ) );
        $config['user_id'] = sanitize_text_field( (string) ( $_POST['user_id'] ?? '' ) );
        $config['list_id'] = sanitize_text_field( (string) ( $_POST['list_id'] ?? '' ) );
        $config['timeout'] = max( 5, (int) ( $_POST['timeout'] ?? 20 ) );
        $config['double_optin'] = ! empty( $_POST['double_optin'] ) ? 1 : 0;

        $api_key_raw = isset( $_POST['api_key'] ) ? (string) wp_unslash( $_POST['api_key'] ) : '';
        if ( trim( $api_key_raw ) !== '' ) {
            $config['api_key'] = sanitize_text_field( $api_key_raw );
        }

        teinvit_integrations_save_state(
            $provider_key,
            [
                'enabled' => ! empty( $_POST['enabled'] ) ? 1 : 0,
                'config' => $config,
                'last_status' => $state['last_status'] ?? 'never',
                'last_error' => $state['last_error'] ?? '',
                'last_tested_at' => $state['last_tested_at'] ?? null,
            ]
        );

        $state = teinvit_integrations_get_state( $provider_key );
        $notice = 'Integration settings saved.';
    }

    if ( isset( $_POST['teinvit_integrations_test'] ) && check_admin_referer( 'teinvit_integrations_test' ) ) {
        $result = teinvit_integrations_run_action( $provider_key, 'test_connection', [] );
        $state = teinvit_integrations_get_state( $provider_key );
        $notice = is_wp_error( $result ) ? 'Test connection failed: ' . $result->get_error_message() : 'Test connection OK.';
    }

    echo '<div class="wrap"><h1>TeInvit Integrations</h1>';
    if ( $notice !== '' ) {
        echo '<div class="notice notice-info"><p>' . esc_html( $notice ) . '</p></div>';
    }

    echo '<form method="post">';
    wp_nonce_field( 'teinvit_integrations_save' );
    echo '<table class="form-table"><tbody>';
    echo '<tr><th>Provider</th><td><strong>' . esc_html( $provider['label'] ?? $provider_key ) . '</strong><p class="description">' . esc_html( (string) ( $provider['description'] ?? '' ) ) . '</p></td></tr>';
    echo '<tr><th>Enabled</th><td><label><input type="checkbox" name="enabled" value="1" ' . checked( ! empty( $state['enabled'] ), true, false ) . '> Enable provider</label></td></tr>';
    echo '<tr><th>Base URL</th><td><input type="url" class="regular-text" name="base_url" value="' . esc_attr( (string) ( $state['config']['base_url'] ?? '' ) ) . '"></td></tr>';
    echo '<tr><th>API Version</th><td><input type="text" class="regular-text" name="api_version" value="' . esc_attr( (string) ( $state['config']['api_version'] ?? '' ) ) . '"></td></tr>';
    echo '<tr><th>User ID</th><td><input type="text" class="regular-text" name="user_id" value="' . esc_attr( (string) ( $state['config']['user_id'] ?? '' ) ) . '"></td></tr>';
    echo '<tr><th>API Key</th><td><input type="password" class="regular-text" name="api_key" value="" placeholder="•••••• (leave empty to keep current)"></td></tr>';
    echo '<tr><th>List ID</th><td><input type="text" class="regular-text" name="list_id" value="' . esc_attr( (string) ( $state['config']['list_id'] ?? '' ) ) . '"></td></tr>';
    echo '<tr><th>Timeout</th><td><input type="number" min="5" name="timeout" value="' . esc_attr( (string) ( $state['config']['timeout'] ?? 20 ) ) . '"></td></tr>';
    echo '<tr><th>Double opt-in</th><td><label><input type="checkbox" name="double_optin" value="1" ' . checked( ! empty( $state['config']['double_optin'] ), true, false ) . '> Use init subscribe endpoint</label></td></tr>';
    echo '<tr><th>Last status</th><td>' . esc_html( (string) ( $state['last_status'] ?? 'never' ) ) . '<br><small>' . esc_html( (string) ( $state['last_error'] ?? '' ) ) . '</small></td></tr>';
    echo '<tr><th>Last tested at</th><td>' . esc_html( (string) ( $state['last_tested_at'] ?? '-' ) ) . '</td></tr>';
    echo '</tbody></table>';
    submit_button( 'Save integration', 'primary', 'teinvit_integrations_save' );
    echo '</form>';

    echo '<form method="post" style="margin-top:10px;">';
    wp_nonce_field( 'teinvit_integrations_test' );
    submit_button( 'Test connection', 'secondary', 'teinvit_integrations_test', false );
    echo '</form>';
    echo '</div>';
}

function teinvit_api_keys_generate_secret() {
    $plain = 'tinv_' . wp_generate_password( 40, false, false );
    return [
        'plain' => $plain,
        'prefix' => substr( $plain, 0, 12 ),
        'hash' => hash( 'sha256', $plain ),
    ];
}

function teinvit_admin_render_api_keys_page() {
    if ( ! current_user_can( 'manage_woocommerce' ) ) {
        wp_die( 'Unauthorized' );
    }

    global $wpdb;
    $tables = teinvit_db_tables();
    $notice = '';
    $new_plain = '';

    if ( isset( $_POST['teinvit_api_key_create'] ) && check_admin_referer( 'teinvit_api_key_create' ) ) {
        $name = sanitize_text_field( (string) ( $_POST['key_name'] ?? '' ) );
        $scopes = sanitize_text_field( (string) ( $_POST['scopes'] ?? '' ) );
        $notes = sanitize_textarea_field( (string) ( $_POST['notes'] ?? '' ) );

        if ( $name !== '' ) {
            $gen = teinvit_api_keys_generate_secret();
            $now = current_time( 'mysql' );
            $wpdb->insert(
                $tables['api_keys'],
                [
                    'name' => $name,
                    'key_prefix' => $gen['prefix'],
                    'key_hash' => $gen['hash'],
                    'scopes' => $scopes,
                    'status' => 'active',
                    'notes' => $notes,
                    'last_used_at' => null,
                    'revoked_at' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
            $new_plain = $gen['plain'];
            $notice = 'API key created. Copy now, it will not be shown again.';
        }
    }

    if ( isset( $_POST['teinvit_api_key_revoke'] ) && check_admin_referer( 'teinvit_api_key_revoke' ) ) {
        $id = (int) ( $_POST['id'] ?? 0 );
        if ( $id > 0 ) {
            $wpdb->update(
                $tables['api_keys'],
                [
                    'status' => 'revoked',
                    'revoked_at' => current_time( 'mysql' ),
                    'updated_at' => current_time( 'mysql' ),
                ],
                [ 'id' => $id ]
            );
            $notice = 'API key revoked.';
        }
    }

    if ( isset( $_POST['teinvit_api_key_activate'] ) && check_admin_referer( 'teinvit_api_key_activate' ) ) {
        $id = (int) ( $_POST['id'] ?? 0 );
        if ( $id > 0 ) {
            $wpdb->update(
                $tables['api_keys'],
                [ 'status' => 'active', 'updated_at' => current_time( 'mysql' ) ],
                [ 'id' => $id ]
            );
            $notice = 'API key activated.';
        }
    }

    $rows = $wpdb->get_results( "SELECT * FROM {$tables['api_keys']} ORDER BY id DESC LIMIT 300", ARRAY_A );

    echo '<div class="wrap"><h1>TeInvit API Keys</h1>';
    if ( $notice !== '' ) {
        echo '<div class="notice notice-info"><p>' . esc_html( $notice ) . '</p></div>';
    }
    if ( $new_plain !== '' ) {
        echo '<div class="notice notice-warning"><p><strong>New key:</strong> <code>' . esc_html( $new_plain ) . '</code></p></div>';
    }

    echo '<h2>Create key</h2><form method="post">';
    wp_nonce_field( 'teinvit_api_key_create' );
    echo '<table class="form-table"><tbody>';
    echo '<tr><th>Name</th><td><input type="text" class="regular-text" name="key_name" required></td></tr>';
    echo '<tr><th>Scopes</th><td><input type="text" class="regular-text" name="scopes" placeholder="reports.read, contacts.read"></td></tr>';
    echo '<tr><th>Notes</th><td><textarea name="notes" rows="3" class="large-text"></textarea></td></tr>';
    echo '</tbody></table>';
    submit_button( 'Generate key', 'primary', 'teinvit_api_key_create' );
    echo '</form>';

    echo '<h2>Existing keys</h2>';
    echo '<table class="widefat striped"><thead><tr><th>ID</th><th>Name</th><th>Prefix</th><th>Scopes</th><th>Status</th><th>Created</th><th>Last used</th><th>Revoked</th><th>Action</th></tr></thead><tbody>';
    foreach ( $rows as $row ) {
        echo '<tr>';
        echo '<td>' . esc_html( (string) $row['id'] ) . '</td>';
        echo '<td>' . esc_html( (string) $row['name'] ) . '</td>';
        echo '<td><code>' . esc_html( (string) $row['key_prefix'] ) . '</code></td>';
        echo '<td>' . esc_html( (string) $row['scopes'] ) . '</td>';
        echo '<td>' . esc_html( (string) $row['status'] ) . '</td>';
        echo '<td>' . esc_html( (string) $row['created_at'] ) . '</td>';
        echo '<td>' . esc_html( (string) ( $row['last_used_at'] ?? '' ) ) . '</td>';
        echo '<td>' . esc_html( (string) ( $row['revoked_at'] ?? '' ) ) . '</td>';
        echo '<td><form method="post">';
        echo '<input type="hidden" name="id" value="' . esc_attr( (string) $row['id'] ) . '">';
        if ( $row['status'] === 'active' ) {
            wp_nonce_field( 'teinvit_api_key_revoke' );
            echo '<button class="button" name="teinvit_api_key_revoke" value="1">Revoke</button>';
        } else {
            wp_nonce_field( 'teinvit_api_key_activate' );
            echo '<button class="button" name="teinvit_api_key_activate" value="1">Activate</button>';
        }
        echo '</form></td>';
        echo '</tr>';
    }
    echo '</tbody></table></div>';
}

function teinvit_contacts_where_sql( &$params = [] ) {
    $where = ' WHERE 1=1 ';

    $search = isset( $_GET['s'] ) ? sanitize_text_field( (string) wp_unslash( $_GET['s'] ) ) : '';
    if ( $search !== '' ) {
        $where .= ' AND (email LIKE %s OR phone LIKE %s OR source_token LIKE %s) ';
        $like = '%' . $search . '%';
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }

    $filters = [ 'subscription_status', 'suppression_active', 'gdpr_accepted', 'marketing_consent', 'last_newsman_sync_status' ];
    foreach ( $filters as $field ) {
        if ( isset( $_GET[ $field ] ) && $_GET[ $field ] !== '' ) {
            $where .= " AND {$field} = %s ";
            $params[] = sanitize_text_field( (string) wp_unslash( $_GET[ $field ] ) );
        }
    }

    $token = isset( $_GET['source_token'] ) ? sanitize_text_field( (string) wp_unslash( $_GET['source_token'] ) ) : '';
    if ( $token !== '' ) {
        $where .= ' AND source_token = %s ';
        $params[] = $token;
    }

    $from = isset( $_GET['date_from'] ) ? sanitize_text_field( (string) wp_unslash( $_GET['date_from'] ) ) : '';
    $to = isset( $_GET['date_to'] ) ? sanitize_text_field( (string) wp_unslash( $_GET['date_to'] ) ) : '';
    if ( $from !== '' ) {
        $where .= ' AND updated_at >= %s ';
        $params[] = $from . ' 00:00:00';
    }
    if ( $to !== '' ) {
        $where .= ' AND updated_at <= %s ';
        $params[] = $to . ' 23:59:59';
    }

    if ( isset( $_GET['sync_error'] ) && $_GET['sync_error'] === '1' ) {
        $where .= " AND last_newsman_error <> '' ";
    }

    return $where;
}

function teinvit_admin_render_contacts_page() {
    if ( ! current_user_can( 'manage_woocommerce' ) ) {
        wp_die( 'Unauthorized' );
    }

    global $wpdb;
    $tables = teinvit_db_tables();

    $email_drill = isset( $_GET['contact_email'] ) ? sanitize_email( (string) wp_unslash( $_GET['contact_email'] ) ) : '';
    if ( $email_drill !== '' ) {
        $contact = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$tables['marketing_contacts']} WHERE email=%s LIMIT 1", $email_drill ), ARRAY_A );
        $journal = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$tables['consent_journal']} WHERE email=%s ORDER BY id DESC LIMIT 300", $email_drill ), ARRAY_A );

        echo '<div class="wrap"><h1>Contact details</h1>';
        echo '<p><a href="' . esc_url( admin_url( 'admin.php?page=teinvit-contacts' ) ) . '">← Back to list</a></p>';
        if ( ! $contact ) {
            echo '<p>Contact not found.</p></div>';
            return;
        }

        echo '<table class="widefat striped"><tbody>';
        foreach ( $contact as $k => $v ) {
            echo '<tr><th>' . esc_html( (string) $k ) . '</th><td>' . esc_html( (string) $v ) . '</td></tr>';
        }
        echo '</tbody></table>';

        echo '<h2>Consent journal</h2>';
        echo '<table class="widefat striped"><thead><tr><th>ID</th><th>Action</th><th>Status</th><th>Source</th><th>Token</th><th>Phone</th><th>Context</th><th>Created</th></tr></thead><tbody>';
        foreach ( $journal as $row ) {
            echo '<tr>';
            echo '<td>' . esc_html( (string) $row['id'] ) . '</td>';
            echo '<td>' . esc_html( (string) $row['action'] ) . '</td>';
            echo '<td>' . esc_html( (string) $row['status'] ) . '</td>';
            echo '<td>' . esc_html( (string) $row['source'] ) . '</td>';
            echo '<td>' . esc_html( (string) $row['token'] ) . '</td>';
            echo '<td>' . esc_html( (string) $row['phone'] ) . '</td>';
            echo '<td><code>' . esc_html( (string) $row['context'] ) . '</code></td>';
            echo '<td>' . esc_html( (string) $row['created_at'] ) . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table></div>';
        return;
    }

    $params = [];
    $where = teinvit_contacts_where_sql( $params );

    $export = isset( $_GET['export'] ) ? sanitize_key( (string) wp_unslash( $_GET['export'] ) ) : '';
    if ( $export === 'csv' ) {
        $sql = "SELECT * FROM {$tables['marketing_contacts']} {$where} ORDER BY id DESC";
        $rows = $params ? $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A ) : $wpdb->get_results( $sql, ARRAY_A );

        nocache_headers();
        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename=teinvit_contacts.csv' );
        $out = fopen( 'php://output', 'w' );
        if ( ! empty( $rows ) ) {
            fputcsv( $out, array_keys( $rows[0] ) );
            foreach ( $rows as $row ) {
                fputcsv( $out, $row );
            }
        }
        fclose( $out );
        exit;
    }

    $paged = max( 1, (int) ( $_GET['paged'] ?? 1 ) );
    $per_page = 50;
    $offset = ( $paged - 1 ) * $per_page;

    $count_sql = "SELECT COUNT(*) FROM {$tables['marketing_contacts']} {$where}";
    $total = (int) ( $params ? $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) ) : $wpdb->get_var( $count_sql ) );

    $data_sql = "SELECT * FROM {$tables['marketing_contacts']} {$where} ORDER BY id DESC LIMIT %d OFFSET %d";
    $prepared_params = $params;
    $prepared_params[] = $per_page;
    $prepared_params[] = $offset;
    $rows = $wpdb->get_results( $wpdb->prepare( $data_sql, $prepared_params ), ARRAY_A );

    echo '<div class="wrap"><h1>TeInvit Contacts</h1>';
    echo '<form method="get"><input type="hidden" name="page" value="teinvit-contacts">';
    echo '<p><input type="search" name="s" value="' . esc_attr( (string) ( $_GET['s'] ?? '' ) ) . '" placeholder="Search email/phone/token"> ';
    echo '<input type="text" name="source_token" value="' . esc_attr( (string) ( $_GET['source_token'] ?? '' ) ) . '" placeholder="Token"> ';
    echo '<select name="subscription_status"><option value="">Any status</option><option value="subscribed">subscribed</option><option value="unsubscribed">unsubscribed</option><option value="consent_incomplete">consent_incomplete</option></select> ';
    echo '<select name="suppression_active"><option value="">Suppression</option><option value="1">active</option><option value="0">inactive</option></select> ';
    echo '<select name="last_newsman_sync_status"><option value="">Sync status</option><option value="ok">ok</option><option value="error">error</option><option value="none">none</option></select> ';
    echo '<input type="date" name="date_from" value="' . esc_attr( (string) ( $_GET['date_from'] ?? '' ) ) . '"> ';
    echo '<input type="date" name="date_to" value="' . esc_attr( (string) ( $_GET['date_to'] ?? '' ) ) . '"> ';
    echo '<label><input type="checkbox" name="sync_error" value="1" ' . checked( (string) ( $_GET['sync_error'] ?? '' ), '1', false ) . '> with sync error</label> ';
    echo '<button class="button">Filter</button> ';
    echo '<a class="button" href="' . esc_url( add_query_arg( 'export', 'csv' ) ) . '">Export CSV</a></p>';
    echo '</form>';

    echo '<table class="widefat striped"><thead><tr>';
    $cols = [ 'email', 'phone', 'gdpr_accepted', 'marketing_consent', 'suppression_active', 'subscription_status', 'source_token', 'source_event', 'last_subscribed_at', 'last_unsubscribed_at', 'last_resubscribed_at', 'last_consent_updated_at', 'last_newsman_sync_status', 'last_newsman_error', 'created_at', 'updated_at' ];
    foreach ( $cols as $col ) {
        echo '<th>' . esc_html( $col ) . '</th>';
    }
    echo '</tr></thead><tbody>';
    foreach ( $rows as $row ) {
        echo '<tr>';
        foreach ( $cols as $col ) {
            $value = (string) ( $row[ $col ] ?? '' );
            if ( $col === 'email' ) {
                $url = add_query_arg( [ 'page' => 'teinvit-contacts', 'contact_email' => $value ], admin_url( 'admin.php' ) );
                echo '<td><a href="' . esc_url( $url ) . '">' . esc_html( $value ) . '</a></td>';
            } else {
                echo '<td>' . esc_html( $value ) . '</td>';
            }
        }
        echo '</tr>';
    }
    echo '</tbody></table>';

    $pages = (int) ceil( $total / $per_page );
    if ( $pages > 1 ) {
        echo '<p>Page: ';
        for ( $i = 1; $i <= $pages; $i++ ) {
            $url = add_query_arg( 'paged', (string) $i );
            if ( $i === $paged ) {
                echo '<strong>' . esc_html( (string) $i ) . '</strong> ';
            } else {
                echo '<a href="' . esc_url( $url ) . '">' . esc_html( (string) $i ) . '</a> ';
            }
        }
        echo '</p>';
    }

    echo '</div>';
}

function teinvit_admin_render_reports_page() {
    if ( ! current_user_can( 'manage_woocommerce' ) ) {
        wp_die( 'Unauthorized' );
    }

    global $wpdb;
    $t = teinvit_db_tables();

    $from = isset( $_GET['date_from'] ) ? sanitize_text_field( (string) wp_unslash( $_GET['date_from'] ) ) : '';
    $to = isset( $_GET['date_to'] ) ? sanitize_text_field( (string) wp_unslash( $_GET['date_to'] ) ) : '';
    $token = isset( $_GET['token'] ) ? sanitize_text_field( (string) wp_unslash( $_GET['token'] ) ) : '';

    $journal_where = ' WHERE 1=1 ';
    $params = [];
    if ( $from !== '' ) {
        $journal_where .= ' AND created_at >= %s ';
        $params[] = $from . ' 00:00:00';
    }
    if ( $to !== '' ) {
        $journal_where .= ' AND created_at <= %s ';
        $params[] = $to . ' 23:59:59';
    }
    if ( $token !== '' ) {
        $journal_where .= ' AND token = %s ';
        $params[] = $token;
    }

    $count_row = $wpdb->get_row( "SELECT COUNT(*) total, SUM(subscription_status='subscribed') subscribed, SUM(subscription_status='unsubscribed') unsubscribed, SUM(subscription_status='consent_incomplete') consent_incomplete, SUM(suppression_active=1) suppression_active, SUM(last_newsman_sync_status='error') sync_errors FROM {$t['marketing_contacts']}", ARRAY_A );

    $action_sql = "SELECT action, COUNT(*) c FROM {$t['consent_journal']} {$journal_where} GROUP BY action ORDER BY c DESC";
    $actions = $params ? $wpdb->get_results( $wpdb->prepare( $action_sql, $params ), ARRAY_A ) : $wpdb->get_results( $action_sql, ARRAY_A );

    $token_sql = "SELECT source_token, COUNT(*) c FROM {$t['marketing_contacts']} WHERE source_token<>'' GROUP BY source_token ORDER BY c DESC LIMIT 50";
    $token_rows = $wpdb->get_results( $token_sql, ARRAY_A );

    echo '<div class="wrap"><h1>TeInvit Reports</h1>';
    echo '<form method="get"><input type="hidden" name="page" value="teinvit-reports">';
    echo '<p><input type="date" name="date_from" value="' . esc_attr( $from ) . '"> <input type="date" name="date_to" value="' . esc_attr( $to ) . '"> <input type="text" name="token" value="' . esc_attr( $token ) . '" placeholder="Token"> <button class="button">Apply</button></p></form>';

    echo '<h2>Contacts snapshot</h2><table class="widefat striped"><tbody>';
    foreach ( [ 'total', 'subscribed', 'unsubscribed', 'consent_incomplete', 'suppression_active', 'sync_errors' ] as $metric ) {
        echo '<tr><th>' . esc_html( $metric ) . '</th><td>' . esc_html( (string) ( $count_row[ $metric ] ?? 0 ) ) . '</td></tr>';
    }
    echo '</tbody></table>';

    echo '<h2>Journal actions</h2><table class="widefat striped"><thead><tr><th>Action</th><th>Count</th></tr></thead><tbody>';
    foreach ( $actions as $row ) {
        echo '<tr><td>' . esc_html( (string) $row['action'] ) . '</td><td>' . esc_html( (string) $row['c'] ) . '</td></tr>';
    }
    echo '</tbody></table>';

    echo '<h2>Contacts per token</h2><table class="widefat striped"><thead><tr><th>Token</th><th>Contacts</th></tr></thead><tbody>';
    foreach ( $token_rows as $row ) {
        echo '<tr><td>' . esc_html( (string) $row['source_token'] ) . '</td><td>' . esc_html( (string) $row['c'] ) . '</td></tr>';
    }
    echo '</tbody></table></div>';
}
