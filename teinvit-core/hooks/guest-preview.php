<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Afișare preview invitație în pagina publică /i/{token}
 */
add_action( 'teinvit_guest_page_preview', function ( WC_Order $order ) {

    echo '<div class="teinvit-guest-preview">';
    echo TeInvit_Wedding_Preview_Renderer::render_from_order( $order );
    echo '</div>';

});
