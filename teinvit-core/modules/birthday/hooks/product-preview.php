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

    if ( $vertical !== 'birthday' ) {
        return;
    }

    wp_enqueue_script( 'jquery' );
    wp_enqueue_script(
        'teinvit-preview-birthday',
        TEINVIT_CORE_URL . 'infrastructure/assets/preview/preview-vertical.js',
        [ 'jquery' ],
        TEINVIT_CORE_VERSION,
        true
    );

    wp_localize_script( 'teinvit-preview-birthday', 'teinvitVerticalPreviewConfig', [
        'previewBuildUrl' => esc_url_raw( rest_url( 'teinvit/v2/preview/build' ) ),
        'vertical' => 'birthday',
        'maxChars' => 250,
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

    if ( $vertical !== 'birthday' ) {
        return;
    }

    $runtime = function_exists( 'teinvit_build_invitation_payload_from_wapf_map' )
        ? teinvit_build_invitation_payload_from_wapf_map( 'birthday', [], (int) $product->get_id() )
        : [ 'invitation' => [] ];

    $preview_html = function_exists( 'teinvit_render_invitation_html_for_vertical' )
        ? teinvit_render_invitation_html_for_vertical( 'birthday', (array) ( $runtime['invitation'] ?? [] ), null, 'preview', (int) $product->get_id() )
        : '';

    echo '<div id="teinvit-product-preview" class="teinvit-product-preview">';
    echo '<h3 class="teinvit-preview-title">Previzualizare invitație</h3>';
    echo '<div id="teinvit-vertical-product-preview" data-vertical="birthday" data-product-id="' . (int) $product->get_id() . '">';
    echo $preview_html;
    echo '</div></div>';
}, 25 );
