<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'wp_enqueue_scripts', function () {
    if ( ! is_product() ) {
        return;
    }

    $product_id = function_exists( 'get_queried_object_id' ) ? (int) get_queried_object_id() : 0;
    $product = $product_id > 0 ? wc_get_product( $product_id ) : null;
    if ( ! $product || ! $product instanceof WC_Product ) {
        return;
    }

    $vertical = function_exists( 'teinvit_find_catalog_vertical_for_product_id' )
        ? teinvit_find_catalog_vertical_for_product_id( (int) $product->get_id() )
        : 'wedding';

    if ( $vertical !== 'baptism' ) {
        return;
    }

    wp_enqueue_script( 'jquery' );
    wp_enqueue_script(
        'teinvit-preview-layout-engine',
        TEINVIT_CORE_URL . 'infrastructure/preview-layout-engine.js',
        [],
        TEINVIT_CORE_VERSION,
        true
    );
    wp_enqueue_script(
        'teinvit-baptism-preview',
        TEINVIT_BAPTISM_MODULE_URL . 'preview/preview.js',
        [ 'jquery', 'teinvit-preview-layout-engine' ],
        TEINVIT_CORE_VERSION,
        true
    );

    wp_localize_script( 'teinvit-baptism-preview', 'teinvitBaptismPreviewConfig', [
        'previewBuildUrl' => esc_url_raw( rest_url( 'teinvit/v2/preview/build' ) ),
        'vertical' => 'baptism',
        'maxChars' => 255,
    ] );
}, 20 );

add_action( 'woocommerce_after_add_to_cart_form', function () {
    $product_id = function_exists( 'get_queried_object_id' ) ? (int) get_queried_object_id() : 0;
    $product = $product_id > 0 ? wc_get_product( $product_id ) : null;
    if ( ! $product || ! $product instanceof WC_Product ) {
        return;
    }

    $vertical = function_exists( 'teinvit_find_catalog_vertical_for_product_id' )
        ? teinvit_find_catalog_vertical_for_product_id( (int) $product->get_id() )
        : 'wedding';

    if ( $vertical !== 'baptism' ) {
        return;
    }

    $runtime = function_exists( 'teinvit_build_invitation_payload_from_wapf_map' )
        ? teinvit_build_invitation_payload_from_wapf_map( 'baptism', [], (int) $product->get_id() )
        : [ 'invitation' => [] ];

    $preview_html = function_exists( 'teinvit_render_invitation_html_for_vertical' )
        ? teinvit_render_invitation_html_for_vertical( 'baptism', (array) ( $runtime['invitation'] ?? [] ), null, 'preview', (int) $product->get_id() )
        : '';

    echo '<div id="teinvit-product-preview" class="teinvit-product-preview">';
    echo '<h3 class="teinvit-preview-title">Previzualizare invitație</h3>';
    echo '<div id="teinvit-vertical-product-preview" data-vertical="baptism" data-product-id="' . (int) $product->get_id() . '">';
    echo $preview_html;
    echo '</div></div>';
}, 25 );
