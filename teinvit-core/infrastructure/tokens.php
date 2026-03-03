<?php
/**
 * Te Invit – Token logic
 * CANONIC / SAFE / STABIL
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Generate secure random token part
 */
function teinvit_generate_token_part( $length = 20 ) {
    return bin2hex( random_bytes( $length / 2 ) );
}


function teinvit_order_contains_invitation_product( $order ) {
    if ( ! $order ) {
        return false;
    }

    $allowed = [ 70, 286 ];
    foreach ( $order->get_items() as $item ) {
        $pid = (int) $item->get_product_id();
        if ( in_array( $pid, $allowed, true ) ) {
            return true;
        }
    }

    return false;
}

/**
 * Attach token to order
 * Triggered ONLY when order becomes COMPLETED
 * Format: {order_id}-{random}
 */
function teinvit_attach_token_on_completed( $order_id ) {

    if ( ! $order_id ) {
        return;
    }

    // Prevent regeneration
    if ( get_post_meta( $order_id, '_teinvit_token', true ) ) {
        return;
    }

    $random_part = teinvit_generate_token_part( 20 );
    $token       = $order_id . '-' . $random_part;

    update_post_meta( $order_id, '_teinvit_token', $token );

    $order = wc_get_order( $order_id );
    if ( ! $order ) {
        return;
    }

    if ( ! teinvit_order_contains_invitation_product( $order ) ) {
        $order->add_order_note( 'Skip PDF pipeline: order does not contain invitation product (70/286)' );
        return;
    }

    // Admin-only order note
    $order->add_order_note(
        'Token invitație generat: ' . $token
    );

    /**
     * 🔑 CANONIC EVENT
     * De aici pornește TOT ce ține de PDF
     */
    do_action( 'teinvit_token_generated', $order_id, $token );
}

/**
 * 🔒 SINGURUL HOOK PERMIS
 * Tokenul se generează DOAR la completed
 */
add_action(
    'woocommerce_order_status_completed',
    'teinvit_attach_token_on_completed',
    10
);

/**
 * Get token by order ID
 */
function teinvit_get_token_by_order( $order_id ) {
    return get_post_meta( $order_id, '_teinvit_token', true );
}
