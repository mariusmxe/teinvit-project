<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * TeInvit – Preview invitație în pagina de produs
 * STANDARDUL VIZUAL al produsului
 *
 * Afișează preview-ul folosind RENDERERUL UNIC,
 * identic cu pagina de invitați.
 */

/**
 * 1️⃣ Enqueue JS necesar preview LIVE
 * (temele moderne NU mai încarcă jQuery)
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
    if ( ! is_string( $vertical ) || $vertical === '' ) {
        $vertical = 'wedding';
    }

    wp_enqueue_script( 'jquery' );

    if ( $vertical === 'wedding' ) {
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
        return;
    }

    wp_enqueue_script(
        'teinvit-preview-vertical',
        TEINVIT_CORE_URL . 'modules/wedding/preview/preview-vertical.js',
        [ 'jquery' ],
        TEINVIT_CORE_VERSION,
        true
    );
    wp_localize_script( 'teinvit-preview-vertical', 'teinvitVerticalPreviewConfig', [
        'previewBuildUrl' => esc_url_raw( rest_url( 'teinvit/v2/preview/build' ) ),
        'vertical' => $vertical,
        'maxChars' => 250,
    ] );

}, 20 );

/**
 * 2️⃣ Afișare preview în pagina de produs
 */
add_action( 'woocommerce_after_add_to_cart_form', function () {

    $product_id = function_exists( 'get_queried_object_id' ) ? (int) get_queried_object_id() : 0;
    $product = $product_id > 0 ? wc_get_product( $product_id ) : null;
    if ( ! $product || ! $product instanceof WC_Product ) {
        return;
    }


    echo '<div id="teinvit-product-preview" class="teinvit-product-preview">';
    echo '<h3 class="teinvit-preview-title">Previzualizare invitație</h3>';

    /**
     * 🔑 PREVIEW UNIC
     * Pe pagina de produs NU avem order,
     * deci trimitem invitație goală → JS o populează LIVE
     */
    $vertical = function_exists( 'teinvit_find_catalog_vertical_for_product_id' )
        ? teinvit_find_catalog_vertical_for_product_id( (int) $product->get_id() )
        : 'wedding';
    if ( ! is_string( $vertical ) || $vertical === '' ) {
        $vertical = 'wedding';
    }

    if ( $vertical === 'wedding' ) {
        echo TeInvit_Wedding_Preview_Renderer::render_from_product( $product );
    } else {
        $runtime = function_exists( 'teinvit_build_invitation_payload_from_wapf_map' )
            ? teinvit_build_invitation_payload_from_wapf_map( $vertical, [], (int) $product->get_id() )
            : [ 'invitation' => [] ];
        $preview_html = function_exists( 'teinvit_render_invitation_html_for_vertical' )
            ? teinvit_render_invitation_html_for_vertical( $vertical, (array) ( $runtime['invitation'] ?? [] ), null, 'preview', (int) $product->get_id() )
            : '';
        echo '<div id="teinvit-vertical-product-preview" data-vertical="' . esc_attr( $vertical ) . '" data-product-id="' . (int) $product->get_id() . '">';
        echo $preview_html;
        echo '</div>';
    }

    echo '</div>';

}, 25 );
