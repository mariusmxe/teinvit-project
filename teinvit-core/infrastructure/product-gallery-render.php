<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'init', function () {
    add_rewrite_rule( '^teinvit-gallery-render/?$', 'index.php?teinvit_gallery_render=1', 'top' );
} );

add_filter( 'query_vars', function ( $vars ) {
    $vars[] = 'teinvit_gallery_render';
    return $vars;
} );

function teinvit_product_gallery_render_fail( $status, $message ) {
    status_header( (int) $status );
    if ( ! defined( 'DONOTCACHEPAGE' ) ) {
        define( 'DONOTCACHEPAGE', true );
    }
    nocache_headers();
    header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0' );
    header( 'X-Robots-Tag: noindex, nofollow, noarchive' );
    header( 'Content-Type: text/plain; charset=UTF-8' );
    echo esc_html( (string) $message );
    exit;
}

add_action( 'template_redirect', function () {
    $request_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
    $parsed_path = $request_uri !== '' ? wp_parse_url( $request_uri, PHP_URL_PATH ) : '';
    $request_path = is_string( $parsed_path ) ? trim( $parsed_path, '/' ) : '';
    if ( ! get_query_var( 'teinvit_gallery_render' ) && $request_path !== 'teinvit-gallery-render' ) {
        return;
    }

    if ( ! defined( 'DONOTCACHEPAGE' ) ) {
        define( 'DONOTCACHEPAGE', true );
    }

    $product_id = isset( $_GET['product_id'] ) ? absint( wp_unslash( $_GET['product_id'] ) ) : 0;
    $vertical = isset( $_GET['vertical'] ) ? sanitize_key( wp_unslash( $_GET['vertical'] ) ) : '';
    $theme_key = isset( $_GET['theme_key'] ) ? sanitize_key( wp_unslash( $_GET['theme_key'] ) ) : '';
    $exp = isset( $_GET['exp'] ) ? absint( wp_unslash( $_GET['exp'] ) ) : 0;
    $sig = isset( $_GET['sig'] ) ? sanitize_text_field( wp_unslash( $_GET['sig'] ) ) : '';

    if ( ! teinvit_product_gallery_verify_render_signature( $product_id, $vertical, $theme_key, $exp, $sig ) ) {
        teinvit_product_gallery_render_fail( 403, 'Gallery render signature invalida.' );
    }

    $product_context = teinvit_product_gallery_detect_product_context( $product_id );
    if ( is_wp_error( $product_context ) ) {
        teinvit_product_gallery_render_fail( 404, $product_context->get_error_message() );
    }

    if ( sanitize_key( $product_context['vertical'] ) !== $vertical ) {
        teinvit_product_gallery_render_fail( 400, 'Verticala nu este compatibila cu produsul.' );
    }

    $theme = teinvit_product_gallery_resolve_theme( $vertical, $theme_key );
    if ( ! is_array( $theme ) ) {
        teinvit_product_gallery_render_fail( 400, 'Tema nu este activa pentru galerie.' );
    }

    $background = teinvit_product_gallery_background_details_for_product( $product_id );
    if ( empty( $background['background_attachment_id'] ) || empty( $background['expected_background_url'] ) ) {
        teinvit_product_gallery_render_fail( 400, 'Produsul nu are background TeInvit valid.' );
    }

    $payload = teinvit_product_gallery_demo_payload( $vertical, $theme );
    $render_subject = $vertical === 'wedding' ? $product_context['product'] : null;

    $gallery_config = [
        'mode'                         => 'gallery',
        'capture_selector'             => '[data-teinvit-gallery-capture="1"]',
        'requested_product_id'         => $product_id,
        'product_slug'                 => $product_context['product_slug'],
        'gallery_file_base_slug'       => $product_context['gallery_file_base_slug'],
        'vertical'                     => $vertical,
        'theme_key_internal'           => $theme['theme_key_internal'],
        'theme_label'                  => $theme['label'],
        'theme_file_key'               => $theme['file_key'],
        'theme_css_class'              => $theme['css_class'],
        'theme_css_class_expected'     => $theme['css_class'],
        'background_source_product_id' => $background['background_source_product_id'],
        'background_attachment_id'     => $background['background_attachment_id'],
        'background_url'               => $background['expected_background_url'],
        'expected_background_url'      => $background['expected_background_url'],
        'layout'                       => [
            'css_width'            => 559,
            'css_height'           => 794,
            'device_scale_factor'  => 2,
            'output_width'         => 1118,
            'output_height'        => 1588,
        ],
    ];

    status_header( 200 );
    nocache_headers();
    header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0' );
    header( 'Pragma: no-cache' );
    header( 'Expires: Wed, 11 Jan 1984 05:00:00 GMT' );
    header( 'X-Robots-Tag: noindex, nofollow, noarchive' );
    header( 'Content-Type: text/html; charset=UTF-8' );

    $GLOBALS['TEINVIT_RENDER_CONTEXT'] = 'gallery';
    $GLOBALS['TEINVIT_GALLERY_RENDER_CONFIG'] = $gallery_config;
    $GLOBALS['TEINVIT_RENDER_PRODUCT_ID'] = $product_id;
    $GLOBALS['TEINVIT_RENDER_TOKEN_CONTEXT'] = [
        'gallery'     => true,
        'product_id'  => $product_id,
        'vertical'    => $vertical,
        'theme_key'   => $theme_key,
    ];
    $GLOBALS['TEINVIT_RENDER_TOKEN'] = '';

    echo '<!DOCTYPE html><html lang="ro" class="teinvit-gallery"><head><meta charset="utf-8"><meta name="viewport" content="width=559, height=794, initial-scale=1"><meta name="robots" content="noindex,nofollow,noarchive"><title>TeInvit Gallery Render</title></head><body style="margin:0;padding:0;background:#fff;display:flex;justify-content:center;">';
    echo '<script>window.__TEINVIT_GALLERY_MODE__=true;window.TEINVIT_GALLERY_CONFIG=' . wp_json_encode( $gallery_config ) . ';</script>';

    echo function_exists( 'teinvit_render_invitation_html_for_vertical' )
        ? teinvit_render_invitation_html_for_vertical( $vertical, $payload, $render_subject, 'gallery', $product_id, $GLOBALS['TEINVIT_RENDER_TOKEN_CONTEXT'] )
        : '';

    echo '</body></html>';
    exit;
} );
