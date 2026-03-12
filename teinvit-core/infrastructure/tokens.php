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

function teinvit_get_custom_product_ids() {
    $defaults = [
        'basic_product_id' => 560,
        'premium_upgrade_addon_id' => 526,
        'premium_native_product_ids' => [ 70, 286 ],
        'extra_edits_addon_id' => 301,
        'extra_gifts_addon_id' => 298,
    ];

    $saved = get_option( 'teinvit_custom_product_ids', [] );
    if ( ! is_array( $saved ) ) {
        $saved = [];
    }

    $basic_id = isset( $saved['basic_product_id'] ) ? (int) $saved['basic_product_id'] : $defaults['basic_product_id'];
    $upgrade_id = isset( $saved['premium_upgrade_addon_id'] ) ? (int) $saved['premium_upgrade_addon_id'] : $defaults['premium_upgrade_addon_id'];
    $edits_id = isset( $saved['extra_edits_addon_id'] ) ? (int) $saved['extra_edits_addon_id'] : $defaults['extra_edits_addon_id'];
    $gifts_id = isset( $saved['extra_gifts_addon_id'] ) ? (int) $saved['extra_gifts_addon_id'] : $defaults['extra_gifts_addon_id'];

    $native = isset( $saved['premium_native_product_ids'] ) ? $saved['premium_native_product_ids'] : $defaults['premium_native_product_ids'];
    if ( ! is_array( $native ) ) {
        $native = preg_split( '/[\s,]+/', (string) $native, -1, PREG_SPLIT_NO_EMPTY );
    }
    $native = array_values( array_filter( array_map( 'intval', $native ), static function( $id ) {
        return $id > 0;
    } ) );
    if ( empty( $native ) ) {
        $native = $defaults['premium_native_product_ids'];
    }

    return [
        'basic_product_id' => max( 0, $basic_id ),
        'premium_upgrade_addon_id' => max( 0, $upgrade_id ),
        'premium_native_product_ids' => array_values( array_unique( $native ) ),
        'extra_edits_addon_id' => max( 0, $edits_id ),
        'extra_gifts_addon_id' => max( 0, $gifts_id ),
    ];
}


function teinvit_order_contains_invitation_product( $order ) {
    if ( ! $order ) {
        return false;
    }

    $catalog = teinvit_get_custom_product_ids();
    $allowed = array_values( array_unique( array_merge(
        [ (int) $catalog['basic_product_id'] ],
        array_map( 'intval', (array) $catalog['premium_native_product_ids'] )
    ) ) );
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
