<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function teinvit_birthday_admin_redirect( $token, array $args = [] ) {
    $token = sanitize_text_field( (string) $token );
    $url = home_url( '/admin-client/' . rawurlencode( $token ) );
    if ( ! empty( $args ) ) {
        $url = add_query_arg( $args, $url );
    }

    wp_safe_redirect( $url );
    exit;
}

function teinvit_birthday_admin_client_template( array $context = [] ) {
    $token = sanitize_text_field( (string) ( $context['token'] ?? '' ) );
    if ( $token !== '' ) {
        set_query_var( 'teinvit_admin_client_token', $token );
    }

    ob_start();
    include TEINVIT_BIRTHDAY_MODULE_PATH . 'templates/page-admin-client.php';
    return ob_get_clean();
}

function teinvit_birthday_invitati_template( array $context = [] ) {
    $token = sanitize_text_field( (string) ( $context['token'] ?? '' ) );
    if ( $token !== '' ) {
        set_query_var( 'teinvit_invitati_token', $token );
    }

    ob_start();
    include TEINVIT_BIRTHDAY_MODULE_PATH . 'templates/page-invitati.php';
    return ob_get_clean();
}

function teinvit_birthday_read_checkbox( array $source, $key ) {
    return isset( $source[ $key ] ) && ! empty( $source[ $key ] ) ? 1 : 0;
}

function teinvit_birthday_config_with_defaults( array $config = [] ) {
    $defaults = function_exists( 'teinvit_default_rsvp_config_for_vertical' )
        ? teinvit_default_rsvp_config_for_vertical( 'birthday' )
        : [];

    $config = wp_parse_args( $config, $defaults );

    if ( isset( $config['birthday_show_party_theme'] ) && ! isset( $config['show_birthday_party_theme'] ) ) {
        $config['show_birthday_party_theme'] = ! empty( $config['birthday_show_party_theme'] ) ? 1 : 0;
    }
    if ( isset( $config['birthday_show_dress_code'] ) && ! isset( $config['show_birthday_dress_code'] ) ) {
        $config['show_birthday_dress_code'] = ! empty( $config['birthday_show_dress_code'] ) ? 1 : 0;
    }
    if ( isset( $config['show_attending_people_count'] ) && ! isset( $config['show_guest_count'] ) ) {
        $config['show_guest_count'] = ! empty( $config['show_attending_people_count'] ) ? 1 : 0;
    }
    if ( ! isset( $config['edits_free_remaining'] ) ) {
        $config['edits_free_remaining'] = 2;
    }
    if ( ! isset( $config['edits_paid_remaining'] ) ) {
        $config['edits_paid_remaining'] = 0;
    }

    return $config;
}

function teinvit_birthday_admin_post_guard( $token, $required_capability = '' ) {
    $token = sanitize_text_field( (string) $token );
    if ( $token === '' ) {
        teinvit_birthday_admin_redirect( $token, [ 'error' => 'missing' ] );
    }

    $nonce = isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';
    if ( ! wp_verify_nonce( $nonce, 'teinvit_admin_' . $token ) ) {
        teinvit_birthday_admin_redirect( $token, [ 'error' => 'forbidden' ] );
    }

    $ctx = function_exists( 'teinvit_token_access_context' ) ? teinvit_token_access_context( $token ) : new WP_Error( 'missing_guard' );
    if ( is_wp_error( $ctx ) ) {
        teinvit_birthday_admin_redirect( $token, [ 'error' => 'forbidden' ] );
    }

    $vertical = function_exists( 'teinvit_resolve_token_vertical' ) ? teinvit_resolve_token_vertical( $token ) : 'wedding';
    if ( $vertical !== 'birthday' ) {
        teinvit_birthday_admin_redirect( $token, [ 'error' => 'wrong_vertical' ] );
    }

    $inv = function_exists( 'teinvit_get_invitation_record' ) ? teinvit_get_invitation_record( $token, 'birthday' ) : null;
    if ( ! is_array( $inv ) ) {
        teinvit_birthday_admin_redirect( $token, [ 'error' => 'missing' ] );
    }

    $caps = function_exists( 'teinvit_capabilities_for_token' ) ? teinvit_capabilities_for_token( $token ) : [];
    $can_manage_all = function_exists( 'teinvit_user_can_manage_all_tokens' ) && teinvit_user_can_manage_all_tokens();
    if ( $required_capability !== '' && ! $can_manage_all && empty( $caps[ $required_capability ] ) ) {
        teinvit_birthday_admin_redirect( $token, [ 'error' => 'forbidden' ] );
    }

    return [
        'order_id' => (int) $ctx[0],
        'order' => $ctx[1],
        'invitation' => $inv,
        'capabilities' => $caps,
    ];
}

function teinvit_birthday_merge_invitation_info_from_post( array $config, array $source ) {
    $config = teinvit_birthday_config_with_defaults( $config );

    $config['show_rsvp_deadline'] = teinvit_birthday_read_checkbox( $source, 'date_confirm' );
    $config['rsvp_deadline_date'] = sanitize_text_field( wp_unslash( $source['selecteaza_data'] ?? '' ) );
    $config['rsvp_deadline_text'] = $config['rsvp_deadline_date'];

    $config['show_birthday_party_theme'] = teinvit_birthday_read_checkbox( $source, 'show_birthday_party_theme' );
    $config['birthday_party_theme_text'] = sanitize_text_field( wp_unslash( $source['birthday_party_theme_text'] ?? '' ) );
    $config['show_birthday_dress_code'] = teinvit_birthday_read_checkbox( $source, 'show_birthday_dress_code' );
    $config['birthday_dress_code_text'] = sanitize_text_field( wp_unslash( $source['birthday_dress_code_text'] ?? '' ) );

    // Backward aliases for any 5.1 placeholder reads; Birthday uses the show_birthday_* keys going forward.
    $config['birthday_show_party_theme'] = $config['show_birthday_party_theme'];
    $config['birthday_show_dress_code'] = $config['show_birthday_dress_code'];

    return $config;
}

function teinvit_birthday_merge_rsvp_config_from_post( array $config, array $source ) {
    $config = teinvit_birthday_config_with_defaults( $config );

    $map = [
        'show_attending_party',
        'show_guest_count',
        'show_kids',
        'show_child_menu',
        'show_accommodation',
        'show_vegetarian',
        'show_allergies',
        'show_message',
        'show_special_observations',
    ];

    $published_order = [];
    foreach ( $map as $config_key ) {
        $enabled = teinvit_birthday_read_checkbox( $source, $config_key );
        $config[ $config_key ] = $enabled;
        if ( $enabled ) {
            $published_order[] = $config_key;
        }
    }

    $config['show_attending_people_count'] = ! empty( $config['show_guest_count'] ) ? 1 : 0;
    $config['rsvp_zone2_order'] = $published_order;

    return $config;
}

function teinvit_birthday_snapshot_is_minimally_valid( array $snapshot_invitation ) {
    $celebrants = isset( $snapshot_invitation['celebrants'] ) && is_array( $snapshot_invitation['celebrants'] )
        ? array_values( array_filter( $snapshot_invitation['celebrants'] ) )
        : [];
    $theme = trim( (string) ( $snapshot_invitation['theme'] ?? '' ) );

    return ! empty( $celebrants ) && $theme !== '';
}

function teinvit_birthday_order_product_ids( WC_Order $order ) {
    $product_ids = [];
    foreach ( $order->get_items( 'line_item' ) as $item ) {
        $product_id = (int) $item->get_product_id();
        $variation_id = (int) $item->get_variation_id();
        if ( $product_id > 0 ) {
            $product_ids[] = $product_id;
        }
        if ( $variation_id > 0 ) {
            $product_ids[] = $variation_id;
        }
    }

    return array_values( array_unique( array_filter( array_map( 'intval', $product_ids ) ) ) );
}

add_action( 'admin_post_teinvit_birthday_save_invitation_info', function() {
    $token = sanitize_text_field( wp_unslash( $_POST['token'] ?? '' ) );
    $ctx = teinvit_birthday_admin_post_guard( $token, 'can_save_invitation_info' );
    $inv = $ctx['invitation'];
    $config = teinvit_birthday_config_with_defaults( is_array( $inv['config'] ?? null ) ? $inv['config'] : [] );
    $config = teinvit_birthday_merge_invitation_info_from_post( $config, $_POST );

    teinvit_save_invitation_config_for_token( $token, [ 'config' => $config ], 'birthday' );
    teinvit_birthday_admin_redirect( $token, [ 'saved' => 'info' ] );
} );

add_action( 'admin_post_teinvit_birthday_save_rsvp_config', function() {
    $token = sanitize_text_field( wp_unslash( $_POST['token'] ?? '' ) );
    $ctx = teinvit_birthday_admin_post_guard( $token, 'can_save_rsvp_config' );
    $inv = $ctx['invitation'];
    $config = teinvit_birthday_config_with_defaults( is_array( $inv['config'] ?? null ) ? $inv['config'] : [] );
    $config = teinvit_birthday_merge_rsvp_config_from_post( $config, $_POST );

    teinvit_save_invitation_config_for_token( $token, [ 'config' => $config ], 'birthday' );
    teinvit_birthday_admin_redirect( $token, [ 'saved' => 'config' ] );
} );

add_action( 'admin_post_teinvit_birthday_set_active_version', function() {
    global $wpdb;

    $token = sanitize_text_field( wp_unslash( $_POST['token'] ?? '' ) );
    teinvit_birthday_admin_post_guard( $token, 'can_set_active_version' );

    $version_id = (int) ( $_POST['active_version_id'] ?? 0 );
    $tables = function_exists( 'teinvit_storage_tables_for_existing_token' ) ? teinvit_storage_tables_for_existing_token( $token, 'birthday' ) : [];
    $exists = 0;
    if ( ! empty( $tables['versions'] ) && $version_id > 0 ) {
        $exists = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$tables['versions']} WHERE token=%s AND id=%d", $token, $version_id ) );
    }

    if ( $exists ) {
        teinvit_save_invitation_config_for_token( $token, [ 'active_version_id' => $version_id ], 'birthday' );
    }

    teinvit_birthday_admin_redirect( $token, [
        'saved' => $exists ? 'active' : 'missing_version',
        'selected_version_id' => $version_id,
    ] );
} );

add_action( 'admin_post_teinvit_birthday_save_version_snapshot', function() {
    global $wpdb;

    $token = sanitize_text_field( wp_unslash( $_POST['token'] ?? '' ) );
    $ctx = teinvit_birthday_admin_post_guard( $token, 'can_save_version_snapshot' );
    $order_id = (int) $ctx['order_id'];
    $order = $ctx['order'];
    $inv = $ctx['invitation'];

    $config = teinvit_birthday_config_with_defaults( is_array( $inv['config'] ?? null ) ? $inv['config'] : [] );
    $free_remaining = max( 0, (int) ( $config['edits_free_remaining'] ?? 2 ) );
    $paid_remaining = max( 0, (int) ( $config['edits_paid_remaining'] ?? 0 ) );
    if ( $free_remaining + $paid_remaining <= 0 ) {
        teinvit_birthday_admin_redirect( $token, [ 'error' => 'noedits' ] );
    }

    $wapf = function_exists( 'teinvit_extract_posted_wapf_map' ) ? teinvit_extract_posted_wapf_map( $_POST ) : [];
    $product_id = function_exists( 'teinvit_get_order_primary_product_id' ) ? (int) teinvit_get_order_primary_product_id( $order ) : 0;
    $built = function_exists( 'teinvit_build_invitation_payload_from_wapf_map' )
        ? teinvit_build_invitation_payload_from_wapf_map( 'birthday', $wapf, $product_id )
        : [ 'invitation' => [], 'wapf_fields' => $wapf ];

    $snapshot_invitation = isset( $built['invitation'] ) && is_array( $built['invitation'] ) ? $built['invitation'] : [];
    $snapshot_wapf = isset( $built['wapf_fields'] ) && is_array( $built['wapf_fields'] ) ? $built['wapf_fields'] : $wapf;
    if ( ! teinvit_birthday_snapshot_is_minimally_valid( $snapshot_invitation ) ) {
        teinvit_birthday_admin_redirect( $token, [ 'error' => 'invalid_snapshot' ] );
    }

    $snapshot = [
        'invitation' => $snapshot_invitation,
        'wapf_fields' => $snapshot_wapf,
        'meta' => [
            'order_id' => $order_id,
            'vertical' => 'birthday',
        ],
    ];
    $snapshot_json = wp_json_encode( $snapshot );
    $tables = function_exists( 'teinvit_storage_tables_for_existing_token' ) ? teinvit_storage_tables_for_existing_token( $token, 'birthday' ) : [];
    if ( empty( $tables['versions'] ) ) {
        teinvit_birthday_admin_redirect( $token, [ 'error' => 'storage' ] );
    }

    $wpdb->insert( $tables['versions'], [
        'token' => $token,
        'snapshot' => $snapshot_json,
        'created_at' => current_time( 'mysql' ),
    ] );

    $version_id = (int) $wpdb->insert_id;
    if ( $version_id <= 0 ) {
        teinvit_birthday_admin_redirect( $token, [ 'error' => 'version_failed' ] );
    }

    $pdf_status = 'none';
    $pdf_url = '';
    $pdf_filename = '';
    $version_index = max( 0, (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$tables['versions']} WHERE token = %s AND id <= %d", $token, $version_id ) ) - 1 );

    if ( function_exists( 'teinvit_pdf_filename_for_version' ) && function_exists( 'teinvit_generate_pdf_for_version' ) ) {
        $pdf_filename = teinvit_pdf_filename_for_version( $order, $version_index );
        $wpdb->update( $tables['versions'], [
            'pdf_status' => 'processing',
            'pdf_filename' => $pdf_filename,
        ], [ 'id' => $version_id ] );

        $pdf_result = teinvit_generate_pdf_for_version( $token, $order_id, $pdf_filename, $version_id );
        if ( is_wp_error( $pdf_result ) ) {
            $pdf_status = 'failed';
        } else {
            $pdf_status = 'ready';
            $pdf_url = (string) ( $pdf_result['pdf_url'] ?? '' );
        }

        $wpdb->update( $tables['versions'], [
            'pdf_status' => $pdf_status,
            'pdf_url' => $pdf_url,
            'pdf_generated_at' => current_time( 'mysql' ),
            'pdf_filename' => $pdf_filename,
        ], [ 'id' => $version_id ] );
    }

    if ( $free_remaining > 0 ) {
        $config['edits_free_remaining'] = max( 0, $free_remaining - 1 );
    } else {
        $config['edits_paid_remaining'] = max( 0, $paid_remaining - 1 );
    }

    teinvit_save_invitation_config_for_token( $token, [ 'config' => $config ], 'birthday' );

    do_action( 'teinvit_invitation_version_saved', $token, $version_id, [
        'token' => $token,
        'order_id' => $order_id,
        'vertical' => 'birthday',
        'version_id' => $version_id,
        'version_index' => $version_index,
        'active_version_id' => (int) ( $inv['active_version_id'] ?? 0 ),
        'pdf_status' => $pdf_status,
        'pdf_url' => $pdf_url,
        'pdf_filename' => $pdf_filename,
        'admin_client_url' => home_url( '/admin-client/' . rawurlencode( $token ) ),
        'invitati_url' => home_url( '/invitati/' . rawurlencode( $token ) ),
        'product_ids' => teinvit_birthday_order_product_ids( $order ),
        'snapshot_hash' => hash( 'sha256', (string) $snapshot_json ),
    ] );

    teinvit_birthday_admin_redirect( $token, [
        'saved' => 'version',
        'selected_version_id' => $version_id,
    ] );
} );
