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

    // Forțăm jQuery (dependință critică pentru preview.js)
    wp_enqueue_script( 'jquery' );

    // Preview JS – single source of truth
    wp_enqueue_script(
        'teinvit-preview',
        TEINVIT_CORE_URL . 'invitations/wedding/preview/preview.js',
        [ 'jquery' ],
        '1.0',
        true
    );

}, 20 );

/**
 * 2️⃣ Afișare preview în pagina de produs
 */
add_action( 'woocommerce_after_add_to_cart_form', function () {

    global $product;

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
    echo TeInvit_Wedding_Preview_Renderer::render_from_product(
        $product,
        [] // ← CRITIC: al doilea argument lipsă înainte
    );

    echo '</div>';

}, 25 );
