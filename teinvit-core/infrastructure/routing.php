<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'init', function () {
    add_rewrite_rule( '^i/([^/]+)/?$', 'index.php?teinvit_token=$matches[1]', 'top' );
    add_rewrite_rule( '^pdf/([^/]+)/?$', 'index.php?teinvit_pdf_token=$matches[1]', 'top' );
} );

add_filter( 'query_vars', function ( $vars ) {
    $vars[] = 'teinvit_token';
    $vars[] = 'teinvit_pdf_token';
    return $vars;
} );

add_action( 'template_redirect', function () {
    $token = get_query_var( 'teinvit_token' );
    if ( ! $token ) {
        return;
    }

    $order_id = function_exists( 'teinvit_get_order_id_by_token' ) ? teinvit_get_order_id_by_token( $token ) : 0;
    if ( ! $order_id ) {
        status_header( 404 );
        echo 'Invitația nu a fost găsită.';
        exit;
    }

    $order = wc_get_order( $order_id );
    if ( ! $order ) {
        status_header( 404 );
        echo 'Comandă invalidă.';
        exit;
    }

    $html = '';
    $payload = function_exists( 'teinvit_get_modular_active_payload' ) ? teinvit_get_modular_active_payload( $token ) : [];
    if ( ! empty( $payload['invitation'] ) && is_array( $payload['invitation'] ) ) {
        $html = TeInvit_Wedding_Preview_Renderer::render_from_invitation_data( $payload['invitation'], $order );
    }

    if ( $html === '' ) {
        $html = TeInvit_Wedding_Preview_Renderer::render_from_order( $order );
    }

    status_header( 200 );
    nocache_headers();
    echo '<!DOCTYPE html><html lang="ro"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Invitație</title>';
    wp_head();
    echo '</head><body>';
    echo '<div style="display:flex;justify-content:center;flex-direction:column;">';
    echo '<div>' . $html . '</div>';
    echo '</div>';
    wp_footer();
    echo '</body></html>';
    exit;
} );

add_action( 'template_redirect', function () {
    $token = get_query_var( 'teinvit_pdf_token' );
    if ( ! $token ) {
        return;
    }

    $order_id = function_exists( 'teinvit_get_order_id_by_token' ) ? teinvit_get_order_id_by_token( $token ) : 0;
    if ( ! $order_id ) {
        status_header( 404 );
        echo 'PDF invalid.';
        exit;
    }

    $order = wc_get_order( $order_id );
    if ( ! $order ) {
        status_header( 404 );
        echo 'Comandă invalidă.';
        exit;
    }

    status_header( 200 );
    nocache_headers();

    $GLOBALS['TEINVIT_RENDER_CONTEXT'] = 'pdf';

    echo '<!DOCTYPE html><html lang="ro" class="teinvit-pdf"><head><meta charset="utf-8"><meta name="viewport" content="width=148mm, height=210mm, initial-scale=1"><title>Invitație PDF</title></head><body style="display:flex;justify-content:center;">';

    $payload = function_exists( 'teinvit_get_modular_active_payload' ) ? teinvit_get_modular_active_payload( $token ) : [];

    $requested_version_id = isset( $_GET['teinvit_version_id'] ) ? (int) $_GET['teinvit_version_id'] : 0;
    if ( $requested_version_id > 0 ) {
        global $wpdb;
        $t = function_exists( 'teinvit_db_tables' ) ? teinvit_db_tables() : [];
        if ( ! empty( $t['versions'] ) ) {
            $row = $wpdb->get_row( $wpdb->prepare( "SELECT snapshot FROM {$t['versions']} WHERE id = %d AND token = %s LIMIT 1", $requested_version_id, $token ), ARRAY_A );
            if ( ! empty( $row['snapshot'] ) ) {
                $requested_payload = json_decode( (string) $row['snapshot'], true );
                if ( ! empty( $requested_payload['invitation'] ) && is_array( $requested_payload['invitation'] ) ) {
                    $payload = $requested_payload;
                }
            }
        }
    }

    if ( ! empty( $payload['invitation'] ) && is_array( $payload['invitation'] ) ) {
        echo TeInvit_Wedding_Preview_Renderer::render_from_invitation_data( $payload['invitation'], $order );
    } else {
        echo TeInvit_Wedding_Preview_Renderer::render_from_order( $order );
    }

    echo '</body></html>';
    exit;
} );
