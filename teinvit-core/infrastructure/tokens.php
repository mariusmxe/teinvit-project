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

function teinvit_custom_product_vertical_keys() {
    return [ 'wedding', 'baptism', 'birthday', 'private_party' ];
}

function teinvit_custom_product_defaults() {
    return [
        'basic_product_id' => 560,
        'premium_upgrade_addon_id' => 526,
        'premium_native_product_ids' => [ 70, 286 ],
        'extra_edits_addon_id' => 301,
        'extra_gifts_addon_id' => 298,
    ];
}

function teinvit_normalize_custom_product_catalog_entry( $entry ) {
    $defaults = teinvit_custom_product_defaults();
    $entry = is_array( $entry ) ? $entry : [];

    $native = isset( $entry['premium_native_product_ids'] ) ? $entry['premium_native_product_ids'] : $defaults['premium_native_product_ids'];
    if ( ! is_array( $native ) ) {
        $native = preg_split( '/[\s,]+/', (string) $native, -1, PREG_SPLIT_NO_EMPTY );
    }
    $native = array_values( array_filter( array_map( 'intval', $native ), static function( $id ) {
        return $id > 0;
    } ) );

    if ( empty( $native ) ) {
        $native = array_values( array_filter( array_map( 'intval', (array) $defaults['premium_native_product_ids'] ) ) );
    }

    return [
        'basic_product_id' => max( 0, (int) ( $entry['basic_product_id'] ?? $defaults['basic_product_id'] ) ),
        'premium_upgrade_addon_id' => max( 0, (int) ( $entry['premium_upgrade_addon_id'] ?? $defaults['premium_upgrade_addon_id'] ) ),
        'premium_native_product_ids' => array_values( array_unique( $native ) ),
        'extra_edits_addon_id' => max( 0, (int) ( $entry['extra_edits_addon_id'] ?? $defaults['extra_edits_addon_id'] ) ),
        'extra_gifts_addon_id' => max( 0, (int) ( $entry['extra_gifts_addon_id'] ?? $defaults['extra_gifts_addon_id'] ) ),
    ];
}

function teinvit_get_custom_products_catalog() {
    $catalog = get_option( 'teinvit_custom_products_catalog', [] );

    if ( ! is_array( $catalog ) || empty( $catalog ) ) {
        $legacy = get_option( 'teinvit_custom_product_ids', [] );
        $catalog = [
            'wedding' => is_array( $legacy ) && ! empty( $legacy ) ? $legacy : teinvit_custom_product_defaults(),
        ];
    }

    $out = [];
    foreach ( teinvit_custom_product_vertical_keys() as $vertical ) {
        $source = isset( $catalog[ $vertical ] ) ? $catalog[ $vertical ] : teinvit_custom_product_defaults();
        $out[ $vertical ] = teinvit_normalize_custom_product_catalog_entry( $source );
    }

    return $out;
}

function teinvit_get_custom_product_ids( $vertical = 'all' ) {
    $catalog = teinvit_get_custom_products_catalog();

    if ( is_string( $vertical ) && $vertical !== '' && $vertical !== 'all' && isset( $catalog[ $vertical ] ) ) {
        return $catalog[ $vertical ];
    }

    $merged = [
        'basic_product_id' => 0,
        'premium_upgrade_addon_id' => 0,
        'premium_native_product_ids' => [],
        'extra_edits_addon_id' => 0,
        'extra_gifts_addon_id' => 0,
    ];

    foreach ( $catalog as $entry ) {
        if ( empty( $merged['basic_product_id'] ) && ! empty( $entry['basic_product_id'] ) ) {
            $merged['basic_product_id'] = (int) $entry['basic_product_id'];
        }
        if ( empty( $merged['premium_upgrade_addon_id'] ) && ! empty( $entry['premium_upgrade_addon_id'] ) ) {
            $merged['premium_upgrade_addon_id'] = (int) $entry['premium_upgrade_addon_id'];
        }
        if ( empty( $merged['extra_edits_addon_id'] ) && ! empty( $entry['extra_edits_addon_id'] ) ) {
            $merged['extra_edits_addon_id'] = (int) $entry['extra_edits_addon_id'];
        }
        if ( empty( $merged['extra_gifts_addon_id'] ) && ! empty( $entry['extra_gifts_addon_id'] ) ) {
            $merged['extra_gifts_addon_id'] = (int) $entry['extra_gifts_addon_id'];
        }

        $merged['premium_native_product_ids'] = array_merge(
            $merged['premium_native_product_ids'],
            array_map( 'intval', (array) ( $entry['premium_native_product_ids'] ?? [] ) )
        );
    }

    $merged['premium_native_product_ids'] = array_values( array_unique( array_filter( $merged['premium_native_product_ids'], static function( $id ) {
        return $id > 0;
    } ) ) );

    return $merged;
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
        $order->add_order_note( 'Skip PDF pipeline: order does not contain invitation product (configured Basic/Premium).' );
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
