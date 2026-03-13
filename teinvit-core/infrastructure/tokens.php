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

function teinvit_parse_product_ids_csv( $raw ) {
    if ( is_array( $raw ) ) {
        $values = $raw;
    } else {
        $values = preg_split( '/[\s,]+/', (string) $raw, -1, PREG_SPLIT_NO_EMPTY );
    }

    $values = array_values( array_filter( array_map( 'intval', (array) $values ), static function( $id ) {
        return $id > 0;
    } ) );

    return array_values( array_unique( $values ) );
}

function teinvit_custom_product_defaults() {
    return [
        'basic_product_ids' => [],
        'premium_upgrade_addon_ids' => [],
        'premium_native_product_ids' => [],
        'extra_edits_addon_ids' => [],
        'extra_gifts_addon_ids' => [],
    ];
}

function teinvit_normalize_custom_product_catalog_entry( $entry ) {
    $defaults = teinvit_custom_product_defaults();
    $entry = is_array( $entry ) ? $entry : [];

    $basic = $entry['basic_product_ids'] ?? ( isset( $entry['basic_product_id'] ) ? [ $entry['basic_product_id'] ] : [] );
    $upgrade = $entry['premium_upgrade_addon_ids'] ?? ( isset( $entry['premium_upgrade_addon_id'] ) ? [ $entry['premium_upgrade_addon_id'] ] : [] );
    $premium_native = $entry['premium_native_product_ids'] ?? [];
    $extra_edits = $entry['extra_edits_addon_ids'] ?? ( isset( $entry['extra_edits_addon_id'] ) ? [ $entry['extra_edits_addon_id'] ] : [] );
    $extra_gifts = $entry['extra_gifts_addon_ids'] ?? ( isset( $entry['extra_gifts_addon_id'] ) ? [ $entry['extra_gifts_addon_id'] ] : [] );

    return [
        'basic_product_ids' => teinvit_parse_product_ids_csv( $basic ),
        'premium_upgrade_addon_ids' => teinvit_parse_product_ids_csv( $upgrade ),
        'premium_native_product_ids' => teinvit_parse_product_ids_csv( $premium_native ),
        'extra_edits_addon_ids' => teinvit_parse_product_ids_csv( $extra_edits ),
        'extra_gifts_addon_ids' => teinvit_parse_product_ids_csv( $extra_gifts ),
    ];
}

function teinvit_get_custom_products_catalog() {
    $catalog = get_option( 'teinvit_custom_products_catalog', [] );

    if ( ! is_array( $catalog ) || empty( $catalog ) ) {
        $legacy = get_option( 'teinvit_custom_product_ids', [] );
        $catalog = [
            'wedding' => is_array( $legacy ) ? $legacy : [],
        ];
    }

    $out = [];
    foreach ( teinvit_custom_product_vertical_keys() as $vertical ) {
        $source = isset( $catalog[ $vertical ] ) ? $catalog[ $vertical ] : [];
        $out[ $vertical ] = teinvit_normalize_custom_product_catalog_entry( $source );
    }

    return $out;
}

function teinvit_get_custom_product_ids( $vertical = 'all' ) {
    $catalog = teinvit_get_custom_products_catalog();

    if ( is_string( $vertical ) && $vertical !== '' && $vertical !== 'all' && isset( $catalog[ $vertical ] ) ) {
        return $catalog[ $vertical ];
    }

    $merged = teinvit_custom_product_defaults();

    foreach ( $catalog as $entry ) {
        $merged['basic_product_ids'] = array_merge( $merged['basic_product_ids'], (array) ( $entry['basic_product_ids'] ?? [] ) );
        $merged['premium_upgrade_addon_ids'] = array_merge( $merged['premium_upgrade_addon_ids'], (array) ( $entry['premium_upgrade_addon_ids'] ?? [] ) );
        $merged['premium_native_product_ids'] = array_merge( $merged['premium_native_product_ids'], (array) ( $entry['premium_native_product_ids'] ?? [] ) );
        $merged['extra_edits_addon_ids'] = array_merge( $merged['extra_edits_addon_ids'], (array) ( $entry['extra_edits_addon_ids'] ?? [] ) );
        $merged['extra_gifts_addon_ids'] = array_merge( $merged['extra_gifts_addon_ids'], (array) ( $entry['extra_gifts_addon_ids'] ?? [] ) );
    }

    foreach ( $merged as $k => $ids ) {
        $merged[ $k ] = teinvit_parse_product_ids_csv( $ids );
    }

    return $merged;
}

function teinvit_catalog_role_ids( array $catalog, $role_key ) {
    return teinvit_parse_product_ids_csv( $catalog[ $role_key ] ?? [] );
}


function teinvit_order_contains_invitation_product( $order ) {
    if ( ! $order ) {
        return false;
    }

    $catalog = teinvit_get_custom_product_ids();
    $allowed = array_values( array_unique( array_merge(
        teinvit_catalog_role_ids( $catalog, 'basic_product_ids' ),
        teinvit_catalog_role_ids( $catalog, 'premium_native_product_ids' )
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
