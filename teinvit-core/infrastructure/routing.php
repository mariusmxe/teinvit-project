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

function teinvit_public_route_context_for_token( $token ) {
    $token = sanitize_text_field( (string) $token );
    if ( $token === '' ) {
        return [];
    }

    $context = function_exists( 'teinvit_resolve_token_context' ) ? teinvit_resolve_token_context( $token ) : [];
    if ( is_array( $context ) && ! empty( $context['valid'] ) ) {
        $order = isset( $context['order'] ) && is_object( $context['order'] ) ? $context['order'] : null;
        $order_id = max( 0, (int) ( $context['order_id'] ?? 0 ) );
        if ( ! $order && $order_id > 0 && function_exists( 'wc_get_order' ) ) {
            $order = wc_get_order( $order_id );
        }

        if ( $order ) {
            $vertical = isset( $context['vertical'] ) ? (string) $context['vertical'] : '';
            if ( $vertical === '' && function_exists( 'teinvit_resolve_token_vertical' ) ) {
                $vertical = teinvit_resolve_token_vertical( $token );
            }

            return [
                'token' => $token,
                'context' => $context,
                'order_id' => $order_id,
                'order' => $order,
                'vertical' => $vertical !== '' ? $vertical : 'wedding',
                'product_id' => max( 0, (int) ( $context['product_id'] ?? 0 ) ),
                'variation_id' => max( 0, (int) ( $context['variation_id'] ?? 0 ) ),
            ];
        }
    }

    $order_id = function_exists( 'teinvit_get_order_id_by_token' ) ? (int) teinvit_get_order_id_by_token( $token ) : 0;
    $order = $order_id > 0 && function_exists( 'wc_get_order' ) ? wc_get_order( $order_id ) : null;
    if ( ! $order ) {
        return [];
    }

    return [
        'token' => $token,
        'context' => [],
        'order_id' => $order_id,
        'order' => $order,
        'vertical' => function_exists( 'teinvit_resolve_token_vertical' ) ? teinvit_resolve_token_vertical( $token ) : 'wedding',
        'product_id' => function_exists( 'teinvit_get_order_primary_product_id' ) ? max( 0, (int) teinvit_get_order_primary_product_id( $order ) ) : 0,
        'variation_id' => 0,
    ];
}

add_action( 'template_redirect', function () {
    $token = get_query_var( 'teinvit_token' );
    if ( ! $token ) {
        return;
    }

    $route_context = teinvit_public_route_context_for_token( $token );
    if ( empty( $route_context['order'] ) ) {
        status_header( 404 );
        echo 'Invitația nu a fost găsită.';
        exit;
    }

    $order = $route_context['order'];
    $token = $route_context['token'];
    $vertical = $route_context['vertical'];
    $product_id = ! empty( $route_context['variation_id'] ) ? (int) $route_context['variation_id'] : (int) $route_context['product_id'];
    if ( ! $order ) {
        status_header( 404 );
        echo 'Comandă invalidă.';
        exit;
    }

    $payload = function_exists( 'teinvit_ensure_active_snapshot_payload' )
        ? teinvit_ensure_active_snapshot_payload( $token, $order )
        : ( function_exists( 'teinvit_get_modular_active_payload' ) ? teinvit_get_modular_active_payload( $token ) : [] );

    if ( empty( $payload['invitation'] ) || ! is_array( $payload['invitation'] ) ) {
        status_header( 404 );
        echo 'Invitația nu a fost găsită.';
        exit;
    }

    $html = function_exists( 'teinvit_render_invitation_html_for_vertical' )
        ? teinvit_render_invitation_html_for_vertical( $vertical, $payload['invitation'], $order, 'preview', $product_id )
        : TeInvit_Wedding_Preview_Renderer::render_from_invitation_data( $payload['invitation'], $order );

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

    $route_context = teinvit_public_route_context_for_token( $token );
    if ( empty( $route_context['order'] ) ) {
        status_header( 404 );
        echo 'PDF invalid.';
        exit;
    }

    $order = $route_context['order'];
    $token = $route_context['token'];
    $vertical = $route_context['vertical'];
    $product_id = ! empty( $route_context['variation_id'] ) ? (int) $route_context['variation_id'] : (int) $route_context['product_id'];
    if ( ! $order ) {
        status_header( 404 );
        echo 'Comandă invalidă.';
        exit;
    }

    status_header( 200 );
    nocache_headers();

    $GLOBALS['TEINVIT_RENDER_CONTEXT'] = 'pdf';

    echo '<!DOCTYPE html><html lang="ro" class="teinvit-pdf"><head><meta charset="utf-8"><meta name="viewport" content="width=148mm, height=210mm, initial-scale=1"><title>Invitație PDF</title></head><body style="display:flex;justify-content:center;">';

    $payload = function_exists( 'teinvit_ensure_active_snapshot_payload' )
        ? teinvit_ensure_active_snapshot_payload( $token, $order )
        : ( function_exists( 'teinvit_get_modular_active_payload' ) ? teinvit_get_modular_active_payload( $token ) : [] );

    $requested_version_id = isset( $_GET['teinvit_version_id'] ) ? absint( wp_unslash( $_GET['teinvit_version_id'] ) ) : 0;
    if ( $requested_version_id > 0 ) {
        global $wpdb;
        $t = function_exists( 'teinvit_storage_tables_for_token' ) ? teinvit_storage_tables_for_token( $token ) : teinvit_db_tables();
        if ( empty( $t['versions'] ) ) {
            status_header( 404 );
            echo 'PDF version invalid.';
            exit;
        }

        $row = $wpdb->get_row( $wpdb->prepare( "SELECT snapshot FROM {$t['versions']} WHERE id = %d AND token = %s LIMIT 1", $requested_version_id, $token ), ARRAY_A );
        if ( empty( $row['snapshot'] ) ) {
            status_header( 404 );
            echo 'PDF version invalid.';
            exit;
        }

        $requested_payload = json_decode( (string) $row['snapshot'], true );
        if ( empty( $requested_payload['invitation'] ) || ! is_array( $requested_payload['invitation'] ) ) {
            status_header( 404 );
            echo 'PDF version invalid.';
            exit;
        }

        $payload = $requested_payload;
    }

    if ( empty( $payload['invitation'] ) || ! is_array( $payload['invitation'] ) ) {
        status_header( 404 );
        echo 'PDF invalid.';
        exit;
    }

    echo function_exists( 'teinvit_render_invitation_html_for_vertical' )
        ? teinvit_render_invitation_html_for_vertical( $vertical, $payload['invitation'], $order, 'pdf', $product_id )
        : TeInvit_Wedding_Preview_Renderer::render_from_invitation_data( $payload['invitation'], $order );

    echo '</body></html>';
    exit;
} );
