<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * TeInvit – Preview invitație în pagina de produs (Wedding only).
 */

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

    if ( ! is_string( $vertical ) || $vertical !== 'wedding' ) {
        return;
    }

    wp_enqueue_script( 'jquery' );
    wp_enqueue_script(
        'teinvit-preview',
        TEINVIT_WEDDING_MODULE_URL . 'preview/preview.js',
        [ 'jquery' ],
        '1.0',
        true
    );

    wp_localize_script( 'teinvit-preview', 'teinvitPreviewConfig', [
        'previewBuildUrl' => esc_url_raw( rest_url( 'teinvit/v2/preview/build' ) ),
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

    if ( ! is_string( $vertical ) || $vertical !== 'wedding' ) {
        return;
    }

    echo '<div id="teinvit-product-preview" class="teinvit-product-preview">';
    echo '<h3 class="teinvit-preview-title">Previzualizare invitație</h3>';
    echo TeInvit_Wedding_Preview_Renderer::render_from_product( $product );
    echo '</div>';

}, 25 );
