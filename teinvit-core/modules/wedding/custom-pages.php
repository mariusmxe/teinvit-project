<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function teinvit_model_background_url( $model_key ) {
    $model_key = preg_replace( '/[^a-zA-Z0-9_-]/', '', (string) $model_key );
    if ( $model_key === '' ) {
        $model_key = 'invn01';
    }

    $base = TEINVIT_WEDDING_MODULE_PATH . 'assets/backgrounds/' . $model_key;
    if ( file_exists( $base . '.jpg' ) ) {
        return TEINVIT_WEDDING_MODULE_URL . 'assets/backgrounds/' . $model_key . '.jpg';
    }
    if ( file_exists( $base . '.png' ) ) {
        return TEINVIT_WEDDING_MODULE_URL . 'assets/backgrounds/' . $model_key . '.png';
    }

    return TEINVIT_WEDDING_MODULE_URL . 'assets/backgrounds/invn01.png';
}

add_action( 'init', function() {
    add_rewrite_rule( '^admin-client/([^/]+)/?$', 'index.php?teinvit_admin_client_token=$matches[1]', 'top' );
    add_rewrite_rule( '^invitati/([^/]+)/?$', 'index.php?teinvit_invitati_token=$matches[1]', 'top' );
} );

add_filter( 'query_vars', function( $vars ) {
    $vars[] = 'teinvit_admin_client_token';
    $vars[] = 'teinvit_invitati_token';
    return $vars;
} );

add_action( 'template_redirect', function() {
    $token = get_query_var( 'teinvit_admin_client_token' );
    if ( ! $token ) {
        return;
    }

    if ( ! is_user_logged_in() ) {
        wp_safe_redirect( wp_login_url( home_url( '/admin-client/' . rawurlencode( $token ) ) ) );
        exit;
    }

    $order_id = function_exists( 'teinvit_get_order_id_by_token' ) ? teinvit_get_order_id_by_token( $token ) : 0;
    if ( ! $order_id ) {
        status_header( 404 );
        get_header();
        echo '<p>Token invalid.</p>';
        get_footer();
        exit;
    }

    $order = wc_get_order( $order_id );
    if ( ! $order || (int) $order->get_user_id() !== get_current_user_id() ) {
        status_header( 403 );
        get_header();
        echo '<p>Nu ai permisiunea pentru această invitație.</p>';
        get_footer();
        exit;
    }

    teinvit_seed_invitation_if_missing( $token, $order_id );

    status_header( 200 );
    get_header();
    include TEINVIT_WEDDING_MODULE_PATH . 'templates/page-admin-client.php';
    get_footer();
    exit;
}, 2 );

add_action( 'template_redirect', function() {
    $token = get_query_var( 'teinvit_invitati_token' );
    if ( ! $token ) {
        return;
    }

    $order_id = function_exists( 'teinvit_get_order_id_by_token' ) ? teinvit_get_order_id_by_token( $token ) : 0;
    if ( ! $order_id ) {
        status_header( 404 );
        get_header();
        echo '<p>Invitația nu a fost găsită.</p>';
        get_footer();
        exit;
    }

    teinvit_seed_invitation_if_missing( $token, $order_id );
    teinvit_touch_invitation_activity( $token );

    status_header( 200 );
    get_header();
    include TEINVIT_WEDDING_MODULE_PATH . 'templates/page-invitati.php';
    get_footer();
    exit;
}, 2 );

function teinvit_admin_post_guard( $token ) {
    if ( ! wp_verify_nonce( $_POST['_wpnonce'] ?? '', 'teinvit_admin_' . $token ) ) {
        wp_die( 'Nonce invalid' );
    }

    $order_id = function_exists( 'teinvit_get_order_id_by_token' ) ? teinvit_get_order_id_by_token( $token ) : 0;
    $order = $order_id ? wc_get_order( $order_id ) : null;
    if ( ! $order || (int) $order->get_user_id() !== get_current_user_id() ) {
        wp_die( 'Acces interzis' );
    }

    return [ $order_id, $order ];
}

add_action( 'admin_post_teinvit_save_invitation_info', function() {
    $token = sanitize_text_field( wp_unslash( $_POST['token'] ?? '' ) );
    teinvit_admin_post_guard( $token );

    $inv = teinvit_get_invitation( $token );
    $config = is_array( $inv['config'] ?? null ) ? $inv['config'] : teinvit_default_rsvp_config();
    $config['show_rsvp_deadline'] = isset( $_POST['show_rsvp_deadline'] ) ? 1 : 0;
    $config['rsvp_deadline_text'] = sanitize_text_field( wp_unslash( $_POST['rsvp_deadline_text'] ?? '' ) );

    teinvit_save_invitation_config( $token, [
        'event_date' => sanitize_text_field( wp_unslash( $_POST['event_date'] ?? '' ) ) ?: null,
        'model_key' => sanitize_text_field( wp_unslash( $_POST['model_key'] ?? 'invn01' ) ),
        'config' => $config,
    ] );

    wp_safe_redirect( home_url( '/admin-client/' . rawurlencode( $token ) . '?saved=info' ) );
    exit;
} );

add_action( 'admin_post_teinvit_save_rsvp_config', function() {
    $token = sanitize_text_field( wp_unslash( $_POST['token'] ?? '' ) );
    teinvit_admin_post_guard( $token );

    $config = teinvit_default_rsvp_config();
    foreach ( array_keys( $config ) as $key ) {
        if ( $key === 'rsvp_deadline_text' ) {
            $config[ $key ] = sanitize_text_field( wp_unslash( $_POST[ $key ] ?? '' ) );
        } else {
            $config[ $key ] = isset( $_POST[ $key ] ) ? 1 : 0;
        }
    }

    teinvit_save_invitation_config( $token, [ 'config' => $config ] );
    wp_safe_redirect( home_url( '/admin-client/' . rawurlencode( $token ) . '?saved=config' ) );
    exit;
} );

add_action( 'admin_post_teinvit_set_active_version', function() {
    global $wpdb;
    $token = sanitize_text_field( wp_unslash( $_POST['token'] ?? '' ) );
    teinvit_admin_post_guard( $token );

    $version_id = (int) ( $_POST['active_version_id'] ?? 0 );
    $t = teinvit_db_tables();
    $exists = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$t['versions']} WHERE token=%s AND id=%d", $token, $version_id ) );
    if ( $exists ) {
        teinvit_save_invitation_config( $token, [ 'active_version_id' => $version_id ] );
    }

    wp_safe_redirect( home_url( '/admin-client/' . rawurlencode( $token ) . '?saved=active' ) );
    exit;
} );

add_action( 'admin_post_teinvit_save_version_snapshot', function() {
    global $wpdb;
    $token = sanitize_text_field( wp_unslash( $_POST['token'] ?? '' ) );
    list( $order_id, $order ) = teinvit_admin_post_guard( $token );

    $snapshot = [
        'invitation' => [
            'names' => sanitize_text_field( wp_unslash( $_POST['names'] ?? '' ) ),
            'message' => sanitize_textarea_field( wp_unslash( $_POST['message'] ?? '' ) ),
        ],
        'meta' => [ 'order_id' => (int) $order_id ],
    ];

    $t = teinvit_db_tables();
    $wpdb->insert( $t['versions'], [
        'token' => $token,
        'snapshot' => wp_json_encode( $snapshot ),
        'created_at' => current_time( 'mysql' ),
    ] );
    $version_id = (int) $wpdb->insert_id;
    teinvit_save_invitation_config( $token, [ 'active_version_id' => $version_id ] );

    wp_safe_redirect( home_url( '/admin-client/' . rawurlencode( $token ) . '?saved=version' ) );
    exit;
} );

add_action( 'admin_post_teinvit_save_gifts', function() {
    global $wpdb;
    $token = sanitize_text_field( wp_unslash( $_POST['token'] ?? '' ) );
    teinvit_admin_post_guard( $token );

    $inv = teinvit_get_invitation( $token );
    if ( ! empty( $inv['gifts_locked'] ) ) {
        wp_safe_redirect( home_url( '/admin-client/' . rawurlencode( $token ) . '?saved=gifts_locked' ) );
        exit;
    }

    $t = teinvit_db_tables();
    $rows = isset( $_POST['gifts'] ) && is_array( $_POST['gifts'] ) ? $_POST['gifts'] : [];
    $wpdb->delete( $t['gifts'], [ 'token' => $token ] );

    foreach ( $rows as $index => $gift ) {
        $gift_name = sanitize_text_field( wp_unslash( $gift['gift_name'] ?? '' ) );
        if ( $gift_name === '' ) {
            continue;
        }
        $wpdb->insert( $t['gifts'], [
            'token' => $token,
            'gift_id' => sanitize_text_field( wp_unslash( $gift['gift_id'] ?? ( 'gift-' . $index ) ) ),
            'gift_name' => $gift_name,
            'gift_link' => esc_url_raw( wp_unslash( $gift['gift_link'] ?? '' ) ),
            'gift_delivery_address' => sanitize_text_field( wp_unslash( $gift['gift_delivery_address'] ?? '' ) ),
            'status' => 'free',
        ] );
    }

    wp_safe_redirect( home_url( '/admin-client/' . rawurlencode( $token ) . '?saved=gifts' ) );
    exit;
} );

add_action( 'rest_api_init', function() {
    register_rest_route( 'teinvit/v2', '/invitati/(?P<token>[a-zA-Z0-9\-]+)/rsvp', [
        'methods' => 'POST',
        'permission_callback' => '__return_true',
        'callback' => function( WP_REST_Request $request ) {
            global $wpdb;
            $token = sanitize_text_field( $request['token'] );
            $inv = teinvit_get_invitation( $token );
            if ( ! $inv ) {
                return new WP_Error( 'not_found', 'Token invalid', [ 'status' => 404 ] );
            }

            $p = (array) $request->get_json_params();
            $phone = preg_replace( '/\D+/', '', (string) ( $p['guest_phone'] ?? '' ) );
            if ( ! preg_match( '/^07\d{8}$/', $phone ) ) {
                return new WP_Error( 'phone_invalid', 'Telefon invalid', [ 'status' => 400 ] );
            }
            if ( empty( $p['gdpr_accepted'] ) ) {
                return new WP_Error( 'gdpr_required', 'GDPR este obligatoriu', [ 'status' => 400 ] );
            }

            $t = teinvit_db_tables();
            $wpdb->query( 'START TRANSACTION' );
            $wpdb->insert( $t['rsvp'], [
                'token' => $token,
                'guest_first_name' => sanitize_text_field( $p['guest_first_name'] ?? '' ),
                'guest_last_name' => sanitize_text_field( $p['guest_last_name'] ?? '' ),
                'guest_phone' => $phone,
                'attending_people_count' => max( 1, (int) ( $p['attending_people_count'] ?? 1 ) ),
                'attending_civil' => empty( $p['attending_civil'] ) ? 0 : 1,
                'attending_religious' => empty( $p['attending_religious'] ) ? 0 : 1,
                'attending_party' => empty( $p['attending_party'] ) ? 0 : 1,
                'bringing_kids' => empty( $p['bringing_kids'] ) ? 0 : 1,
                'kids_count' => max( 0, (int) ( $p['kids_count'] ?? 0 ) ),
                'needs_accommodation' => empty( $p['needs_accommodation'] ) ? 0 : 1,
                'accommodation_people_count' => max( 0, (int) ( $p['accommodation_people_count'] ?? 0 ) ),
                'vegetarian_requested' => empty( $p['vegetarian_requested'] ) ? 0 : 1,
                'has_allergies' => empty( $p['has_allergies'] ) ? 0 : 1,
                'allergy_details' => sanitize_text_field( $p['allergy_details'] ?? '' ),
                'message_to_couple' => sanitize_textarea_field( $p['message_to_couple'] ?? '' ),
                'gdpr_accepted' => 1,
                'created_at' => current_time( 'mysql' ),
            ] );

            if ( ! $wpdb->insert_id ) {
                $wpdb->query( 'ROLLBACK' );
                return new WP_Error( 'rsvp_failed', 'Nu s-a putut salva RSVP', [ 'status' => 500 ] );
            }

            $rsvp_id = (int) $wpdb->insert_id;
            $selected = array_values( array_unique( array_map( 'sanitize_text_field', (array) ( $p['gift_ids'] ?? [] ) ) ) );

            foreach ( $selected as $gift_id ) {
                $updated = $wpdb->query( $wpdb->prepare(
                    "UPDATE {$t['gifts']} SET status='reserved', reserved_by_rsvp_id=%d, reserved_at=%s WHERE token=%s AND gift_id=%s AND status='free'",
                    $rsvp_id,
                    current_time( 'mysql' ),
                    $token,
                    $gift_id
                ) );

                if ( $updated !== 1 ) {
                    $wpdb->query( 'ROLLBACK' );
                    return new WP_Error( 'gift_conflict', 'Cadou deja rezervat între timp.', [ 'status' => 409 ] );
                }
            }

            $wpdb->query( 'COMMIT' );
            teinvit_save_invitation_config( $token, [ 'gifts_locked' => 1 ] );
            teinvit_touch_invitation_activity( $token );

            return [ 'ok' => true ];
        },
    ] );
} );

add_action( 'init', function() {
    if ( ! wp_next_scheduled( 'teinvit_cleanup_cron' ) ) {
        wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'teinvit_cleanup_cron' );
    }
} );

add_action( 'teinvit_cleanup_cron', 'teinvit_cleanup_expired_invitations' );
