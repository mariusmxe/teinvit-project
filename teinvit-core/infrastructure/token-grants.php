<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function teinvit_token_grants_table() {
    global $wpdb;
    return $wpdb->prefix . 'teinvit_token_grants';
}

function teinvit_install_token_grants_table() {
    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $table = teinvit_token_grants_table();
    $charset = $wpdb->get_charset_collate();

    dbDelta( "CREATE TABLE {$table} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        token varchar(191) NOT NULL,
        vertical varchar(64) NOT NULL DEFAULT 'wedding',
        order_id bigint(20) unsigned NOT NULL DEFAULT 0,
        admin_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
        admin_user_login varchar(191) NOT NULL DEFAULT '',
        admin_user_email varchar(191) NOT NULL DEFAULT '',
        action_type varchar(40) NOT NULL,
        quantity int NOT NULL DEFAULT 0,
        packages_granted int NOT NULL DEFAULT 0,
        slots_per_package int NOT NULL DEFAULT 0,
        slots_total int NOT NULL DEFAULT 0,
        balance_before_json longtext NULL,
        balance_after_json longtext NULL,
        reason text NULL,
        notes text NULL,
        idempotency_key varchar(64) NOT NULL DEFAULT '',
        created_at datetime NOT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY idempotency_key (idempotency_key),
        KEY token_created (token, created_at),
        KEY action_type (action_type),
        KEY order_id (order_id)
    ) $charset;" );
}

function teinvit_register_token_grants_capability() {
    $role = get_role( 'administrator' );
    if ( $role && ! $role->has_cap( 'teinvit_manage_token_grants' ) ) {
        $role->add_cap( 'teinvit_manage_token_grants' );
    }
}
add_action( 'init', 'teinvit_register_token_grants_capability', 5 );

function teinvit_token_grants_capability() {
    return 'teinvit_manage_token_grants';
}

function teinvit_token_grants_admin_url( $token = '', array $args = [] ) {
    $base = [
        'page' => 'teinvit-token-grants',
    ];
    if ( $token !== '' ) {
        $base['token'] = sanitize_text_field( (string) $token );
    }
    return admin_url( 'admin.php?' . http_build_query( array_merge( $base, $args ) ) );
}

function teinvit_admin_register_token_grants_menu_page() {
    $parent = function_exists( 'teinvit_admin_root_slug' ) ? teinvit_admin_root_slug() : 'teinvit-admin-hub';
    add_submenu_page(
        $parent,
        'Alocari token',
        'Alocari token',
        teinvit_token_grants_capability(),
        'teinvit-token-grants',
        'teinvit_admin_render_token_grants_page'
    );
}
add_action( 'admin_menu', 'teinvit_admin_register_token_grants_menu_page', 26 );

function teinvit_token_grants_normalize_token( $token ) {
    $token = sanitize_text_field( (string) $token );
    $token = trim( $token );
    if ( $token === '' || strlen( $token ) > 191 ) {
        return '';
    }
    if ( ! preg_match( '/^[A-Za-z0-9\-]+$/', $token ) ) {
        return '';
    }
    return $token;
}

function teinvit_token_grants_resolve_token_context( $token, $seed_if_missing = true ) {
    $token = teinvit_token_grants_normalize_token( $token );
    if ( $token === '' ) {
        return new WP_Error( 'invalid_token', 'Token invalid.' );
    }

    $order_id = function_exists( 'teinvit_get_order_id_by_token' ) ? (int) teinvit_get_order_id_by_token( $token ) : 0;
    if ( $order_id <= 0 ) {
        return new WP_Error( 'missing_order', 'Tokenul nu este asociat unei comenzi.' );
    }

    $order = function_exists( 'wc_get_order' ) ? wc_get_order( $order_id ) : null;
    if ( ! $order ) {
        return new WP_Error( 'missing_order', 'Comanda asociata tokenului nu a fost gasita.' );
    }

    if ( $seed_if_missing && function_exists( 'teinvit_seed_invitation_if_missing' ) ) {
        teinvit_seed_invitation_if_missing( $token, $order_id );
    }

    $vertical = function_exists( 'teinvit_resolve_token_vertical' ) ? teinvit_resolve_token_vertical( $token ) : 'wedding';
    $inv = function_exists( 'teinvit_get_invitation_record' ) ? teinvit_get_invitation_record( $token, $vertical ) : ( function_exists( 'teinvit_get_invitation' ) ? teinvit_get_invitation( $token ) : null );
    if ( ! is_array( $inv ) ) {
        return new WP_Error( 'missing_invitation', 'Nu exista configuratie TeInvit pentru token.' );
    }

    return [
        'token' => $token,
        'order_id' => $order_id,
        'order' => $order,
        'vertical' => $vertical,
        'invitation' => $inv,
    ];
}

function teinvit_edit_balance_with_defaults( array $config ) {
    return [
        'free' => max( 0, (int) ( $config['edits_free_remaining'] ?? 2 ) ),
        'admin' => max( 0, (int) ( $config['edits_admin_remaining'] ?? 0 ) ),
        'paid' => max( 0, (int) ( $config['edits_paid_remaining'] ?? 0 ) ),
    ];
}

function teinvit_edit_balance_summary( array $config ) {
    $balance = teinvit_edit_balance_with_defaults( $config );
    $balance['total'] = $balance['free'] + $balance['admin'] + $balance['paid'];
    return $balance;
}

function teinvit_config_ensure_edit_balance_keys( array $config ) {
    $balance = teinvit_edit_balance_with_defaults( $config );
    $config['edits_free_remaining'] = $balance['free'];
    $config['edits_admin_remaining'] = $balance['admin'];
    $config['edits_paid_remaining'] = $balance['paid'];
    return $config;
}

function teinvit_config_consume_one_edit( array $config ) {
    $config = teinvit_config_ensure_edit_balance_keys( $config );
    if ( (int) $config['edits_free_remaining'] > 0 ) {
        $config['edits_free_remaining'] = max( 0, (int) $config['edits_free_remaining'] - 1 );
        return $config;
    }
    if ( (int) $config['edits_admin_remaining'] > 0 ) {
        $config['edits_admin_remaining'] = max( 0, (int) $config['edits_admin_remaining'] - 1 );
        return $config;
    }
    $config['edits_paid_remaining'] = max( 0, (int) $config['edits_paid_remaining'] - 1 );
    return $config;
}

function teinvit_token_has_premium_admin_grant( $token ) {
    $token = teinvit_token_grants_normalize_token( $token );
    if ( $token === '' || ! function_exists( 'teinvit_get_invitation' ) ) {
        return false;
    }

    $inv = teinvit_get_invitation( $token );
    $config = is_array( $inv['config'] ?? null ) ? $inv['config'] : [];
    return ! empty( $config['premium_admin_grant_active'] );
}

function teinvit_token_grants_order_contains_any_product_ids( $order, array $product_ids ) {
    if ( ! $order || empty( $product_ids ) ) {
        return false;
    }

    $product_ids = array_values( array_filter( array_map( 'intval', $product_ids ), static function( $id ) {
        return $id > 0;
    } ) );
    if ( empty( $product_ids ) ) {
        return false;
    }

    foreach ( $order->get_items( 'line_item' ) as $item ) {
        $pid = (int) $item->get_product_id();
        $vid = (int) $item->get_variation_id();
        if ( in_array( $pid, $product_ids, true ) || in_array( $vid, $product_ids, true ) ) {
            return true;
        }
    }

    return false;
}

function teinvit_token_has_premium_woo_upgrade( $token ) {
    global $wpdb;

    $token = teinvit_token_grants_normalize_token( $token );
    if ( $token === '' ) {
        return false;
    }

    $inv = function_exists( 'teinvit_get_invitation' ) ? teinvit_get_invitation( $token ) : null;
    $config = is_array( $inv['config'] ?? null ) ? $inv['config'] : [];
    if ( ! empty( $config['premium_upgrade_active'] ) ) {
        return true;
    }

    $catalog = function_exists( 'teinvit_get_catalog_for_token' ) ? teinvit_get_catalog_for_token( $token ) : [];
    $upgrade_ids = function_exists( 'teinvit_catalog_role_ids' ) ? teinvit_catalog_role_ids( $catalog, 'premium_upgrade_addon_ids' ) : [];
    if ( empty( $upgrade_ids ) ) {
        return false;
    }

    $statuses = [ 'wc-processing', 'wc-completed' ];
    $status_placeholders = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );
    $sql = "
        SELECT p.ID
        FROM {$wpdb->posts} p
        INNER JOIN {$wpdb->postmeta} pm_token ON pm_token.post_id = p.ID AND pm_token.meta_key = '_teinvit_token_target' AND pm_token.meta_value = %s
        INNER JOIN {$wpdb->prefix}woocommerce_order_items oi ON oi.order_id = p.ID AND oi.order_item_type = 'line_item'
        INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim ON oim.order_item_id = oi.order_item_id AND oim.meta_key = '_product_id' AND oim.meta_value = %s
        WHERE p.post_type = 'shop_order'
          AND p.post_status IN ($status_placeholders)
        LIMIT 1
    ";

    foreach ( $upgrade_ids as $upgrade_id ) {
        $args = array_merge( [ $token, (string) (int) $upgrade_id ], $statuses );
        $order_id = (int) $wpdb->get_var( $wpdb->prepare( $sql, $args ) );
        if ( $order_id > 0 ) {
            return true;
        }
    }

    return false;
}

function teinvit_resolve_token_premium_source( $token ) {
    $token = teinvit_token_grants_normalize_token( $token );
    if ( $token === '' ) {
        return 'premium_native';
    }

    $catalog = function_exists( 'teinvit_get_catalog_for_token' ) ? teinvit_get_catalog_for_token( $token ) : [];
    $basic_ids = function_exists( 'teinvit_catalog_role_ids' ) ? teinvit_catalog_role_ids( $catalog, 'basic_product_ids' ) : [];
    $premium_native_ids = function_exists( 'teinvit_catalog_role_ids' ) ? teinvit_catalog_role_ids( $catalog, 'premium_native_product_ids' ) : [];
    $order_id = function_exists( 'teinvit_get_order_id_by_token' ) ? (int) teinvit_get_order_id_by_token( $token ) : 0;
    $order = $order_id > 0 && function_exists( 'wc_get_order' ) ? wc_get_order( $order_id ) : null;

    if ( $order && teinvit_token_grants_order_contains_any_product_ids( $order, $premium_native_ids ) ) {
        return 'premium_native';
    }

    if ( teinvit_token_has_premium_admin_grant( $token ) ) {
        return 'basic_upgraded_admin';
    }

    if ( teinvit_token_has_premium_woo_upgrade( $token ) ) {
        return 'basic_upgraded_woo';
    }

    if ( $order && teinvit_token_grants_order_contains_any_product_ids( $order, $basic_ids ) ) {
        return 'basic_pure';
    }

    return 'premium_native';
}

function teinvit_token_grants_gift_summary_for_token( $token, $config = null ) {
    $vertical = function_exists( 'teinvit_resolve_token_vertical' ) ? teinvit_resolve_token_vertical( $token ) : 'wedding';
    if ( $vertical === 'birthday' && function_exists( 'teinvit_birthday_build_gifts_summary_for_token' ) ) {
        return teinvit_birthday_build_gifts_summary_for_token( $token, $config );
    }
    if ( $vertical === 'baptism' && function_exists( 'teinvit_baptism_build_gifts_summary_for_token' ) ) {
        return teinvit_baptism_build_gifts_summary_for_token( $token, $config );
    }
    if ( function_exists( 'teinvit_build_gifts_summary_for_token' ) ) {
        return teinvit_build_gifts_summary_for_token( $token, $config );
    }
    return [ 'base_slots' => 0, 'addon_slots' => 0, 'admin_slots' => 0, 'total_slots' => 0, 'used_slots' => 0, 'available_slots' => 0, 'allocations' => [] ];
}

function teinvit_token_grants_normalize_gift_summary( array $summary ) {
    $summary['base_slots'] = max( 0, (int) ( $summary['base_slots'] ?? 0 ) );
    $summary['addon_slots'] = max( 0, (int) ( $summary['addon_slots'] ?? 0 ) );
    $summary['admin_slots'] = max( 0, (int) ( $summary['admin_slots'] ?? 0 ) );
    $summary['used_slots'] = max( 0, (int) ( $summary['used_slots'] ?? 0 ) );
    $summary['total_slots'] = max( 0, (int) ( $summary['total_slots'] ?? ( $summary['base_slots'] + $summary['addon_slots'] + $summary['admin_slots'] ) ) );
    $summary['available_slots'] = max( 0, (int) ( $summary['available_slots'] ?? ( $summary['total_slots'] - $summary['used_slots'] ) ) );
    return $summary;
}

function teinvit_token_grants_balance_snapshot( $token, array $config ) {
    $gift_summary = teinvit_token_grants_normalize_gift_summary( teinvit_token_grants_gift_summary_for_token( $token, $config ) );
    $premium_source = function_exists( 'teinvit_resolve_token_premium_source' ) ? teinvit_resolve_token_premium_source( $token ) : 'premium_native';
    if ( ! empty( $config['premium_admin_grant_active'] ) ) {
        $premium_source = 'basic_upgraded_admin';
    }

    return [
        'edits' => teinvit_edit_balance_summary( $config ),
        'gifts' => [
            'base' => $gift_summary['base_slots'],
            'addon' => $gift_summary['addon_slots'],
            'admin' => $gift_summary['admin_slots'],
            'used' => $gift_summary['used_slots'],
            'available' => $gift_summary['available_slots'],
            'total' => $gift_summary['total_slots'],
        ],
        'premium_source' => $premium_source,
    ];
}

function teinvit_token_grant_existing_by_idempotency_key( $key ) {
    global $wpdb;
    $key = sanitize_text_field( (string) $key );
    if ( $key === '' ) {
        return null;
    }

    $table = teinvit_token_grants_table();
    $row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE idempotency_key=%s LIMIT 1", $key ), ARRAY_A );
    return is_array( $row ) ? $row : null;
}

function teinvit_token_grants_transaction_begin() {
    global $wpdb;
    $wpdb->query( 'START TRANSACTION' );
}

function teinvit_token_grants_transaction_commit() {
    global $wpdb;
    $wpdb->query( 'COMMIT' );
}

function teinvit_token_grants_transaction_rollback() {
    global $wpdb;
    $wpdb->query( 'ROLLBACK' );
}

function teinvit_token_grants_record( array $data ) {
    global $wpdb;

    $user = wp_get_current_user();
    $payload = [
        'token' => teinvit_token_grants_normalize_token( $data['token'] ?? '' ),
        'vertical' => sanitize_key( (string) ( $data['vertical'] ?? 'wedding' ) ),
        'order_id' => max( 0, (int) ( $data['order_id'] ?? 0 ) ),
        'admin_user_id' => (int) get_current_user_id(),
        'admin_user_login' => sanitize_user( (string) ( $user ? $user->user_login : '' ), true ),
        'admin_user_email' => sanitize_email( (string) ( $user ? $user->user_email : '' ) ),
        'action_type' => sanitize_key( (string) ( $data['action_type'] ?? '' ) ),
        'quantity' => max( 0, (int) ( $data['quantity'] ?? 0 ) ),
        'packages_granted' => max( 0, (int) ( $data['packages_granted'] ?? 0 ) ),
        'slots_per_package' => max( 0, (int) ( $data['slots_per_package'] ?? 0 ) ),
        'slots_total' => max( 0, (int) ( $data['slots_total'] ?? 0 ) ),
        'balance_before_json' => wp_json_encode( $data['balance_before'] ?? [] ),
        'balance_after_json' => wp_json_encode( $data['balance_after'] ?? [] ),
        'reason' => sanitize_textarea_field( (string) ( $data['reason'] ?? '' ) ),
        'notes' => sanitize_textarea_field( (string) ( $data['notes'] ?? '' ) ),
        'idempotency_key' => sanitize_text_field( (string) ( $data['idempotency_key'] ?? wp_generate_uuid4() ) ),
        'created_at' => current_time( 'mysql' ),
    ];

    if ( $payload['token'] === '' || $payload['action_type'] === '' || $payload['idempotency_key'] === '' ) {
        return new WP_Error( 'invalid_grant', 'Date grant invalide.' );
    }
    if ( ! in_array( $payload['action_type'], [ 'edit_grant', 'gift_slots_grant', 'premium_grant' ], true ) ) {
        return new WP_Error( 'invalid_grant_type', 'Tip grant invalid.' );
    }

    $existing = teinvit_token_grant_existing_by_idempotency_key( $payload['idempotency_key'] );
    if ( $existing ) {
        return new WP_Error( 'duplicate_grant', 'Grantul a fost deja procesat.', [ 'grant_id' => (int) $existing['id'] ] );
    }

    $ok = $wpdb->insert( teinvit_token_grants_table(), $payload );
    if ( $ok === false ) {
        if ( stripos( (string) $wpdb->last_error, 'Duplicate entry' ) !== false ) {
            $existing = teinvit_token_grant_existing_by_idempotency_key( $payload['idempotency_key'] );
            if ( $existing ) {
                return new WP_Error( 'duplicate_grant', 'Grantul a fost deja procesat.', [ 'grant_id' => (int) $existing['id'] ] );
            }
        }
        return new WP_Error( 'grant_insert_failed', 'Grantul nu a putut fi inregistrat.' );
    }

    return (int) $wpdb->insert_id;
}

function teinvit_token_grants_for_token( $token, $limit = 100 ) {
    global $wpdb;
    $token = teinvit_token_grants_normalize_token( $token );
    if ( $token === '' ) {
        return [];
    }

    $limit = max( 1, min( 300, (int) $limit ) );
    $table = teinvit_token_grants_table();
    return $wpdb->get_results(
        $wpdb->prepare( "SELECT * FROM {$table} WHERE token=%s ORDER BY id DESC LIMIT %d", $token, $limit ),
        ARRAY_A
    );
}

function teinvit_token_grants_add_order_note( $order, $grant_id ) {
    if ( $order && method_exists( $order, 'add_order_note' ) && (int) $grant_id > 0 ) {
        $order->add_order_note( sprintf( 'TeInvit admin grant #%d aplicat; nu este tranzactie comerciala.', (int) $grant_id ) );
        if ( method_exists( $order, 'save' ) ) {
            $order->save();
        }
    }
}

function teinvit_token_grants_sync_legacy_edit_balance( $token, $qty ) {
    if ( ! function_exists( 'teinvit_get_settings' ) || ! function_exists( 'teinvit_update_settings' ) ) {
        return;
    }

    $settings = teinvit_get_settings( $token );
    if ( ! is_array( $settings ) ) {
        return;
    }

    $current_admin = max( 0, (int) ( $settings['edits_admin_remaining'] ?? 0 ) );
    teinvit_update_settings(
        $token,
        [
            'edits_admin_remaining' => $current_admin + max( 0, (int) $qty ),
        ]
    );
}

function teinvit_token_grants_redirect_for_record_error( $token, WP_Error $error ) {
    if ( $error->get_error_code() === 'duplicate_grant' ) {
        $data = $error->get_error_data();
        $grant_id = is_array( $data ) ? (int) ( $data['grant_id'] ?? 0 ) : 0;
        teinvit_token_grants_redirect( $token, [ 'grant_notice' => 'duplicate', 'grant_id' => $grant_id ] );
    }

    teinvit_token_grants_redirect( $token, [ 'grant_error' => $error->get_error_code() ] );
}

function teinvit_token_grants_redirect( $token, array $args = [] ) {
    wp_safe_redirect( teinvit_token_grants_admin_url( $token, $args ) );
    exit;
}

function teinvit_token_grants_verify_action_request() {
    if ( ! current_user_can( teinvit_token_grants_capability() ) ) {
        wp_die( 'Unauthorized' );
    }

    $token = teinvit_token_grants_normalize_token( wp_unslash( $_POST['token'] ?? '' ) );
    if ( $token === '' ) {
        teinvit_token_grants_redirect( '', [ 'grant_error' => 'invalid_token' ] );
    }

    $nonce = sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ?? '' ) );
    if ( ! wp_verify_nonce( $nonce, 'teinvit_token_grants_' . $token ) ) {
        teinvit_token_grants_redirect( $token, [ 'grant_error' => 'nonce' ] );
    }

    $idempotency_key = sanitize_text_field( wp_unslash( $_POST['idempotency_key'] ?? '' ) );
    if ( $idempotency_key === '' ) {
        $idempotency_key = wp_generate_uuid4();
    }

    $existing = teinvit_token_grant_existing_by_idempotency_key( $idempotency_key );
    if ( $existing ) {
        teinvit_token_grants_redirect( $token, [ 'grant_notice' => 'duplicate', 'grant_id' => (int) $existing['id'] ] );
    }

    $ctx = teinvit_token_grants_resolve_token_context( $token, true );
    if ( is_wp_error( $ctx ) ) {
        teinvit_token_grants_redirect( $token, [ 'grant_error' => $ctx->get_error_code() ] );
    }

    return [ $ctx, $idempotency_key ];
}

function teinvit_token_grants_handle_edit_grant() {
    list( $ctx, $idempotency_key ) = teinvit_token_grants_verify_action_request();
    $qty = (int) ( $_POST['quantity'] ?? 0 );
    if ( $qty < 1 || $qty > 99 ) {
        teinvit_token_grants_redirect( $ctx['token'], [ 'grant_error' => 'invalid_quantity' ] );
    }

    $reason = sanitize_textarea_field( wp_unslash( $_POST['reason'] ?? '' ) );
    $inv = $ctx['invitation'];
    $config = teinvit_config_ensure_edit_balance_keys( is_array( $inv['config'] ?? null ) ? $inv['config'] : [] );
    $before = teinvit_token_grants_balance_snapshot( $ctx['token'], $config );
    $config['edits_admin_remaining'] = max( 0, (int) $config['edits_admin_remaining'] ) + $qty;
    $after = teinvit_token_grants_balance_snapshot( $ctx['token'], $config );

    teinvit_token_grants_transaction_begin();
    $grant_id = teinvit_token_grants_record( [
        'token' => $ctx['token'],
        'vertical' => $ctx['vertical'],
        'order_id' => $ctx['order_id'],
        'action_type' => 'edit_grant',
        'quantity' => $qty,
        'balance_before' => $before,
        'balance_after' => $after,
        'reason' => $reason,
        'idempotency_key' => $idempotency_key,
    ] );
    if ( is_wp_error( $grant_id ) ) {
        teinvit_token_grants_transaction_rollback();
        teinvit_token_grants_redirect_for_record_error( $ctx['token'], $grant_id );
    }

    $saved = teinvit_save_invitation_config_for_token( $ctx['token'], [ 'config' => $config ], $ctx['vertical'] );
    if ( $saved === false ) {
        teinvit_token_grants_transaction_rollback();
        teinvit_token_grants_redirect( $ctx['token'], [ 'grant_error' => 'save_failed' ] );
    }
    teinvit_token_grants_sync_legacy_edit_balance( $ctx['token'], $qty );
    teinvit_token_grants_transaction_commit();

    teinvit_token_grants_add_order_note( $ctx['order'], $grant_id );
    teinvit_token_grants_redirect( $ctx['token'], [ 'grant_success' => 'edit', 'grant_id' => (int) $grant_id ] );
}
add_action( 'admin_post_teinvit_token_grant_edits', 'teinvit_token_grants_handle_edit_grant' );

function teinvit_token_grants_handle_gift_grant() {
    list( $ctx, $idempotency_key ) = teinvit_token_grants_verify_action_request();
    $packages = (int) ( $_POST['packages_granted'] ?? 0 );
    if ( $packages < 1 || $packages > 99 ) {
        teinvit_token_grants_redirect( $ctx['token'], [ 'grant_error' => 'invalid_quantity' ] );
    }

    $catalog = function_exists( 'teinvit_get_catalog_for_token' ) ? teinvit_get_catalog_for_token( $ctx['token'] ) : [];
    $slots_per_package = function_exists( 'teinvit_catalog_first_extra_gifts_slots' ) ? (int) teinvit_catalog_first_extra_gifts_slots( $catalog, 10 ) : 10;
    $slots_per_package = max( 1, $slots_per_package );
    $slots_total = $packages * $slots_per_package;
    $reason = sanitize_textarea_field( wp_unslash( $_POST['reason'] ?? '' ) );

    $inv = $ctx['invitation'];
    $config = is_array( $inv['config'] ?? null ) ? $inv['config'] : [];
    $before = teinvit_token_grants_balance_snapshot( $ctx['token'], $config );

    $allocation = [
        'allocation_key' => 'admin_grant:' . $idempotency_key,
        'kind' => 'admin_grant',
        'order_id' => 0,
        'source_order_id' => (int) $ctx['order_id'],
        'item_id' => 0,
        'product_id' => 0,
        'qty' => $packages,
        'packages_granted' => $packages,
        'slots_per_unit' => $slots_per_package,
        'slots_per_package' => $slots_per_package,
        'slots_total' => $slots_total,
        'slots_remaining' => $slots_total,
        'status' => 'applied',
        'applied_at' => current_time( 'mysql' ),
    ];

    $config_after_preview = $config;
    $preview_list = isset( $config_after_preview['gifts_allocations'] ) && is_array( $config_after_preview['gifts_allocations'] ) ? $config_after_preview['gifts_allocations'] : [];
    $preview_updated = false;
    foreach ( $preview_list as $idx => $entry ) {
        if ( is_array( $entry ) && sanitize_text_field( (string) ( $entry['allocation_key'] ?? '' ) ) === $allocation['allocation_key'] ) {
            $preview_list[ $idx ] = array_merge( $entry, $allocation );
            $preview_updated = true;
            break;
        }
    }
    if ( ! $preview_updated ) {
        $preview_list[] = $allocation;
    }
    $config_after_preview['gifts_allocations'] = array_values( $preview_list );
    $after = teinvit_token_grants_balance_snapshot( $ctx['token'], $config_after_preview );

    teinvit_token_grants_transaction_begin();
    $grant_id = teinvit_token_grants_record( [
        'token' => $ctx['token'],
        'vertical' => $ctx['vertical'],
        'order_id' => $ctx['order_id'],
        'action_type' => 'gift_slots_grant',
        'quantity' => $slots_total,
        'packages_granted' => $packages,
        'slots_per_package' => $slots_per_package,
        'slots_total' => $slots_total,
        'balance_before' => $before,
        'balance_after' => $after,
        'reason' => $reason,
        'idempotency_key' => $idempotency_key,
    ] );
    if ( is_wp_error( $grant_id ) ) {
        teinvit_token_grants_transaction_rollback();
        teinvit_token_grants_redirect_for_record_error( $ctx['token'], $grant_id );
    }

    if ( ! function_exists( 'teinvit_token_upsert_gifts_allocation' ) || ! teinvit_token_upsert_gifts_allocation( $ctx['token'], $allocation ) ) {
        teinvit_token_grants_transaction_rollback();
        teinvit_token_grants_redirect( $ctx['token'], [ 'grant_error' => 'save_failed' ] );
    }
    teinvit_token_grants_transaction_commit();

    teinvit_token_grants_add_order_note( $ctx['order'], $grant_id );
    teinvit_token_grants_redirect( $ctx['token'], [ 'grant_success' => 'gifts', 'grant_id' => (int) $grant_id ] );
}
add_action( 'admin_post_teinvit_token_grant_gifts', 'teinvit_token_grants_handle_gift_grant' );

function teinvit_token_grants_handle_premium_grant() {
    list( $ctx, $idempotency_key ) = teinvit_token_grants_verify_action_request();
    $source = teinvit_resolve_token_premium_source( $ctx['token'] );
    if ( $source !== 'basic_pure' ) {
        teinvit_token_grants_redirect( $ctx['token'], [ 'grant_error' => 'already_premium' ] );
    }

    $reason = sanitize_textarea_field( wp_unslash( $_POST['reason'] ?? '' ) );
    $inv = $ctx['invitation'];
    $config = is_array( $inv['config'] ?? null ) ? $inv['config'] : [];
    $before = teinvit_token_grants_balance_snapshot( $ctx['token'], $config );
    $config['premium_admin_grant_active'] = 1;
    $config['premium_admin_grant_id'] = 0;
    $config['premium_admin_granted_at'] = current_time( 'mysql' );
    $config['premium_admin_granted_by'] = (int) get_current_user_id();
    $after = teinvit_token_grants_balance_snapshot( $ctx['token'], $config );

    teinvit_token_grants_transaction_begin();
    $grant_id = teinvit_token_grants_record( [
        'token' => $ctx['token'],
        'vertical' => $ctx['vertical'],
        'order_id' => $ctx['order_id'],
        'action_type' => 'premium_grant',
        'quantity' => 1,
        'balance_before' => $before,
        'balance_after' => $after,
        'reason' => $reason,
        'idempotency_key' => $idempotency_key,
    ] );
    if ( is_wp_error( $grant_id ) ) {
        teinvit_token_grants_transaction_rollback();
        teinvit_token_grants_redirect_for_record_error( $ctx['token'], $grant_id );
    }

    $config['premium_admin_grant_id'] = (int) $grant_id;
    $saved = teinvit_save_invitation_config_for_token( $ctx['token'], [ 'config' => $config ], $ctx['vertical'] );
    if ( $saved === false ) {
        teinvit_token_grants_transaction_rollback();
        teinvit_token_grants_redirect( $ctx['token'], [ 'grant_error' => 'save_failed' ] );
    }
    teinvit_token_grants_transaction_commit();

    teinvit_token_grants_add_order_note( $ctx['order'], $grant_id );
    teinvit_token_grants_redirect( $ctx['token'], [ 'grant_success' => 'premium', 'grant_id' => (int) $grant_id ] );
}
add_action( 'admin_post_teinvit_token_grant_premium', 'teinvit_token_grants_handle_premium_grant' );

function teinvit_token_grants_admin_notice() {
    $success = isset( $_GET['grant_success'] ) ? sanitize_key( (string) wp_unslash( $_GET['grant_success'] ) ) : '';
    $error = isset( $_GET['grant_error'] ) ? sanitize_key( (string) wp_unslash( $_GET['grant_error'] ) ) : '';
    $notice = isset( $_GET['grant_notice'] ) ? sanitize_key( (string) wp_unslash( $_GET['grant_notice'] ) ) : '';
    $grant_id = isset( $_GET['grant_id'] ) ? (int) $_GET['grant_id'] : 0;

    if ( $success !== '' ) {
        echo '<div class="notice notice-success"><p>Grantul TeInvit a fost aplicat' . ( $grant_id > 0 ? ' (#' . esc_html( (string) $grant_id ) . ')' : '' ) . '.</p></div>';
    }
    if ( $notice === 'duplicate' ) {
        echo '<div class="notice notice-info"><p>Actiunea fusese deja procesata' . ( $grant_id > 0 ? ' (#' . esc_html( (string) $grant_id ) . ')' : '' ) . '. Nu am aplicat-o din nou.</p></div>';
    }
    if ( $error !== '' ) {
        $messages = [
            'invalid_token' => 'Token invalid.',
            'missing_order' => 'Tokenul nu este asociat unei comenzi valide.',
            'missing_invitation' => 'Nu exista configuratie TeInvit pentru token.',
            'nonce' => 'Sesiune expirata. Reincarca pagina si incearca din nou.',
            'invalid_quantity' => 'Cantitatea trebuie sa fie intre 1 si 99.',
            'already_premium' => 'Tokenul este deja Premium sau upgradat.',
            'save_failed' => 'Modificarile nu au putut fi salvate.',
            'grant_insert_failed' => 'Grantul nu a putut fi inregistrat in audit trail.',
            'invalid_grant_type' => 'Tipul grantului este invalid.',
            'duplicate_grant' => 'Actiunea fusese deja procesata. Nu am aplicat-o din nou.',
        ];
        echo '<div class="notice notice-error"><p>' . esc_html( $messages[ $error ] ?? 'Actiunea nu a putut fi procesata.' ) . '</p></div>';
    }
}

function teinvit_token_grants_order_edit_url( $order ) {
    if ( $order && method_exists( $order, 'get_edit_order_url' ) ) {
        return (string) $order->get_edit_order_url();
    }
    $order_id = $order && method_exists( $order, 'get_id' ) ? (int) $order->get_id() : 0;
    return $order_id > 0 ? admin_url( 'post.php?post=' . $order_id . '&action=edit' ) : '';
}

function teinvit_token_grants_order_products_label( $order ) {
    if ( ! $order ) {
        return '-';
    }
    $labels = [];
    foreach ( $order->get_items( 'line_item' ) as $item ) {
        $product_id = (int) $item->get_product_id();
        $labels[] = trim( (string) $item->get_name() . ( $product_id > 0 ? ' (#' . $product_id . ')' : '' ) );
    }
    return empty( $labels ) ? '-' : implode( ', ', $labels );
}

function teinvit_token_grants_balance_label( $json ) {
    $data = json_decode( (string) $json, true );
    if ( ! is_array( $data ) ) {
        return '-';
    }
    $edits = is_array( $data['edits'] ?? null ) ? $data['edits'] : [];
    $gifts = is_array( $data['gifts'] ?? null ) ? $data['gifts'] : [];
    $parts = [];
    if ( ! empty( $edits ) ) {
        $parts[] = 'corectii ' . (int) ( $edits['total'] ?? 0 ) . ' (F' . (int) ( $edits['free'] ?? 0 ) . '/A' . (int) ( $edits['admin'] ?? 0 ) . '/P' . (int) ( $edits['paid'] ?? 0 ) . ')';
    }
    if ( ! empty( $gifts ) ) {
        $parts[] = 'cadouri ' . (int) ( $gifts['available'] ?? 0 ) . '/' . (int) ( $gifts['total'] ?? 0 );
    }
    if ( isset( $data['premium_source'] ) ) {
        $parts[] = 'premium ' . sanitize_text_field( (string) $data['premium_source'] );
    }
    return empty( $parts ) ? '-' : implode( '; ', $parts );
}

function teinvit_admin_render_token_grants_page() {
    if ( ! current_user_can( teinvit_token_grants_capability() ) ) {
        wp_die( 'Unauthorized' );
    }

    $token = isset( $_GET['token'] ) ? teinvit_token_grants_normalize_token( wp_unslash( $_GET['token'] ) ) : '';

    echo '<div class="wrap"><h1>Alocari token</h1>';
    echo '<p>Administreaza override-uri TeInvit pe token fara sa creezi comenzi WooCommerce.</p>';
    teinvit_token_grants_admin_notice();

    echo '<form method="get" style="margin:16px 0 20px;">';
    echo '<input type="hidden" name="page" value="teinvit-token-grants">';
    echo '<label for="teinvit-token-search"><strong>Token</strong></label> ';
    echo '<input type="text" id="teinvit-token-search" name="token" class="regular-text" value="' . esc_attr( $token ) . '" placeholder="1060-d31efe848354663efdd6"> ';
    echo '<button class="button button-primary">Cauta</button>';
    echo '</form>';

    if ( $token === '' ) {
        echo '</div>';
        return;
    }

    $ctx = teinvit_token_grants_resolve_token_context( $token, true );
    if ( is_wp_error( $ctx ) ) {
        echo '<div class="notice notice-error"><p>' . esc_html( $ctx->get_error_message() ) . '</p></div></div>';
        return;
    }

    $config = is_array( $ctx['invitation']['config'] ?? null ) ? $ctx['invitation']['config'] : [];
    $config = teinvit_config_ensure_edit_balance_keys( $config );
    $edit_balance = teinvit_edit_balance_summary( $config );
    $gift_summary = teinvit_token_grants_normalize_gift_summary( teinvit_token_grants_gift_summary_for_token( $token, $config ) );
    $premium_source = teinvit_resolve_token_premium_source( $token );
    $catalog = function_exists( 'teinvit_get_catalog_for_token' ) ? teinvit_get_catalog_for_token( $token ) : [];
    $slots_per_package = function_exists( 'teinvit_catalog_first_extra_gifts_slots' ) ? (int) teinvit_catalog_first_extra_gifts_slots( $catalog, 10 ) : 10;
    $slots_per_package = max( 1, $slots_per_package );
    $order_url = teinvit_token_grants_order_edit_url( $ctx['order'] );
    $admin_client_url = home_url( '/admin-client/' . rawurlencode( $token ) );
    $invitati_url = home_url( '/invitati/' . rawurlencode( $token ) );

    echo '<h2>Sumar token</h2>';
    echo '<table class="widefat striped" style="max-width:1100px;"><tbody>';
    echo '<tr><th>Token</th><td><code>' . esc_html( $token ) . '</code></td></tr>';
    echo '<tr><th>Verticala</th><td>' . esc_html( $ctx['vertical'] ) . '</td></tr>';
    echo '<tr><th>Order ID</th><td>' . ( $order_url !== '' ? '<a href="' . esc_url( $order_url ) . '">#' . esc_html( (string) $ctx['order_id'] ) . '</a>' : esc_html( (string) $ctx['order_id'] ) ) . '</td></tr>';
    echo '<tr><th>Produse</th><td>' . esc_html( teinvit_token_grants_order_products_label( $ctx['order'] ) ) . '</td></tr>';
    echo '<tr><th>Status Premium</th><td>' . esc_html( $premium_source ) . '</td></tr>';
    echo '<tr><th>Corectii disponibile</th><td>Free: <strong>' . (int) $edit_balance['free'] . '</strong> / Admin: <strong>' . (int) $edit_balance['admin'] . '</strong> / Paid: <strong>' . (int) $edit_balance['paid'] . '</strong> / Total: <strong>' . (int) $edit_balance['total'] . '</strong></td></tr>';
    echo '<tr><th>Sloturi cadouri</th><td>Base/free: <strong>' . (int) $gift_summary['base_slots'] . '</strong> / Addon: <strong>' . (int) $gift_summary['addon_slots'] . '</strong> / Admin: <strong>' . (int) $gift_summary['admin_slots'] . '</strong> / Used: <strong>' . (int) $gift_summary['used_slots'] . '</strong> / Available: <strong>' . (int) $gift_summary['available_slots'] . '</strong> / Total: <strong>' . (int) $gift_summary['total_slots'] . '</strong></td></tr>';
    echo '<tr><th>Linkuri</th><td><a href="' . esc_url( $admin_client_url ) . '" target="_blank" rel="noopener">/admin-client</a> &nbsp; <a href="' . esc_url( $invitati_url ) . '" target="_blank" rel="noopener">/invitati</a></td></tr>';
    echo '</tbody></table>';

    echo '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:16px;max-width:1100px;margin-top:18px;">';
    echo '<div class="postbox" style="padding:14px;"><h2 class="hndle" style="margin-top:0;">Adauga corectii</h2>';
    echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" onsubmit="return confirm(\'Aplici corectiile pe acest token?\');">';
    wp_nonce_field( 'teinvit_token_grants_' . $token );
    echo '<input type="hidden" name="action" value="teinvit_token_grant_edits">';
    echo '<input type="hidden" name="token" value="' . esc_attr( $token ) . '">';
    echo '<input type="hidden" name="idempotency_key" value="' . esc_attr( wp_generate_uuid4() ) . '">';
    echo '<p><label>Cantitate <input type="number" name="quantity" min="1" max="99" step="1" value="1" class="small-text"></label></p>';
    echo '<p><label>Motiv intern<br><textarea name="reason" rows="3" style="width:100%;"></textarea></label></p>';
    submit_button( 'Acorda corectii', 'primary', 'submit', false );
    echo '</form></div>';

    echo '<div class="postbox" style="padding:14px;"><h2 class="hndle" style="margin-top:0;">Adauga pachete cadouri</h2>';
    echo '<p>Sloturi per pachet: <strong id="teinvit-slots-per-package">' . (int) $slots_per_package . '</strong>. Se foloseste primul addon de cadouri configurat pentru verticala tokenului; fallback 10.</p>';
    echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" onsubmit="return confirm(\'Aplici pachetele de cadouri pe acest token?\');">';
    wp_nonce_field( 'teinvit_token_grants_' . $token );
    echo '<input type="hidden" name="action" value="teinvit_token_grant_gifts">';
    echo '<input type="hidden" name="token" value="' . esc_attr( $token ) . '">';
    echo '<input type="hidden" name="idempotency_key" value="' . esc_attr( wp_generate_uuid4() ) . '">';
    echo '<p><label>Pachete <input type="number" id="teinvit-gift-packages" name="packages_granted" min="1" max="99" step="1" value="1" class="small-text"></label></p>';
    echo '<p>Total sloturi acordate: <strong id="teinvit-gift-slots-total">' . (int) $slots_per_package . '</strong></p>';
    echo '<p><label>Motiv intern<br><textarea name="reason" rows="3" style="width:100%;"></textarea></label></p>';
    submit_button( 'Acorda pachete', 'primary', 'submit', false );
    echo '</form></div>';

    echo '<div class="postbox" style="padding:14px;"><h2 class="hndle" style="margin-top:0;">Activare Premium manual</h2>';
    echo '<p>Status curent: <strong>' . esc_html( $premium_source ) . '</strong></p>';
    if ( $premium_source === 'basic_pure' ) {
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" onsubmit="return confirm(\'Activezi Premium manual pentru acest token?\');">';
        wp_nonce_field( 'teinvit_token_grants_' . $token );
        echo '<input type="hidden" name="action" value="teinvit_token_grant_premium">';
        echo '<input type="hidden" name="token" value="' . esc_attr( $token ) . '">';
        echo '<input type="hidden" name="idempotency_key" value="' . esc_attr( wp_generate_uuid4() ) . '">';
        echo '<p><label>Motiv intern<br><textarea name="reason" rows="3" style="width:100%;"></textarea></label></p>';
        submit_button( 'Activeaza Premium', 'primary', 'submit', false );
        echo '</form>';
    } else {
        echo '<p><em>Tokenul este deja Premium sau upgradat; activarea manuala nu este disponibila.</em></p>';
    }
    echo '</div></div>';

    echo '<script>document.addEventListener("DOMContentLoaded",function(){var p=document.getElementById("teinvit-gift-packages"),s=document.getElementById("teinvit-slots-per-package"),t=document.getElementById("teinvit-gift-slots-total");function r(){var q=Math.max(1,Math.min(99,parseInt(p&&p.value||"1",10)||1));if(p)p.value=q;if(t)t.textContent=String(q*(parseInt(s&&s.textContent||"10",10)||10));}if(p){p.addEventListener("input",r);r();}});</script>';

    $history = teinvit_token_grants_for_token( $token, 100 );
    echo '<h2>Istoric alocari</h2>';
    echo '<table class="widefat striped"><thead><tr><th>Data</th><th>Admin</th><th>Actiune</th><th>Cantitate</th><th>Pachete</th><th>Sloturi/pachet</th><th>Sloturi total</th><th>Motiv</th><th>Sold inainte</th><th>Sold dupa</th></tr></thead><tbody>';
    if ( empty( $history ) ) {
        echo '<tr><td colspan="10">Nu exista granturi pentru acest token.</td></tr>';
    }
    foreach ( $history as $row ) {
        $admin = trim( (string) ( $row['admin_user_login'] ?? '' ) );
        $email = trim( (string) ( $row['admin_user_email'] ?? '' ) );
        if ( $email !== '' ) {
            $admin .= $admin !== '' ? ' <' . $email . '>' : $email;
        }
        echo '<tr>';
        echo '<td>' . esc_html( (string) ( $row['created_at'] ?? '' ) ) . '</td>';
        echo '<td>' . esc_html( $admin ) . '</td>';
        echo '<td>' . esc_html( (string) ( $row['action_type'] ?? '' ) ) . '</td>';
        echo '<td>' . esc_html( (string) ( $row['quantity'] ?? '0' ) ) . '</td>';
        echo '<td>' . esc_html( (string) ( $row['packages_granted'] ?? '0' ) ) . '</td>';
        echo '<td>' . esc_html( (string) ( $row['slots_per_package'] ?? '0' ) ) . '</td>';
        echo '<td>' . esc_html( (string) ( $row['slots_total'] ?? '0' ) ) . '</td>';
        echo '<td>' . esc_html( (string) ( $row['reason'] ?? '' ) ) . '</td>';
        echo '<td><code>' . esc_html( teinvit_token_grants_balance_label( $row['balance_before_json'] ?? '' ) ) . '</code></td>';
        echo '<td><code>' . esc_html( teinvit_token_grants_balance_label( $row['balance_after_json'] ?? '' ) ) . '</code></td>';
        echo '</tr>';
    }
    echo '</tbody></table>';
    echo '</div>';
}
