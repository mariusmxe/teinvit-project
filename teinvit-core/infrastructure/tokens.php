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
        'extra_gifts_addon_slots' => [],
        'default_free_gift_slots' => 20,
    ];
}

function teinvit_parse_addon_slots_csv( $raw, $allowed_ids = [], $default_slots = 10 ) {
    $default_slots = max( 1, (int) $default_slots );
    $allowed_ids = teinvit_parse_product_ids_csv( $allowed_ids );
    $map = [];

    if ( is_array( $raw ) ) {
        foreach ( $raw as $key => $value ) {
            $product_id = (int) $key;
            $slots = (int) $value;
            if ( $product_id > 0 && $slots > 0 ) {
                $map[ $product_id ] = $slots;
            }
        }
    } else {
        $chunks = preg_split( '/[\r\n,;]+/', (string) $raw, -1, PREG_SPLIT_NO_EMPTY );
        foreach ( (array) $chunks as $chunk ) {
            if ( strpos( $chunk, ':' ) === false ) {
                continue;
            }
            list( $product_id_raw, $slots_raw ) = array_map( 'trim', explode( ':', (string) $chunk, 2 ) );
            $product_id = (int) $product_id_raw;
            $slots = (int) $slots_raw;
            if ( $product_id > 0 && $slots > 0 ) {
                $map[ $product_id ] = $slots;
            }
        }
    }

    if ( ! empty( $allowed_ids ) ) {
        foreach ( $allowed_ids as $product_id ) {
            if ( ! isset( $map[ $product_id ] ) ) {
                $map[ $product_id ] = $default_slots;
            }
        }

        $map = array_intersect_key( $map, array_flip( $allowed_ids ) );
    }

    ksort( $map );
    return $map;
}

function teinvit_catalog_extra_gifts_slots_map( array $catalog, $default_slots = 10 ) {
    $gift_ids = teinvit_catalog_role_ids( $catalog, 'extra_gifts_addon_ids' );
    $raw_map = isset( $catalog['extra_gifts_addon_slots'] ) && is_array( $catalog['extra_gifts_addon_slots'] ) ? $catalog['extra_gifts_addon_slots'] : [];
    return teinvit_parse_addon_slots_csv( $raw_map, $gift_ids, $default_slots );
}

function teinvit_catalog_extra_gifts_slots_for_product( array $catalog, $product_id, $default_slots = 10 ) {
    $product_id = (int) $product_id;
    if ( $product_id <= 0 ) {
        return max( 1, (int) $default_slots );
    }

    $map = teinvit_catalog_extra_gifts_slots_map( $catalog, $default_slots );
    return isset( $map[ $product_id ] ) ? (int) $map[ $product_id ] : max( 1, (int) $default_slots );
}

function teinvit_catalog_first_extra_gifts_slots( array $catalog, $default_slots = 10 ) {
    $gift_ids = teinvit_catalog_role_ids( $catalog, 'extra_gifts_addon_ids' );
    $first_id = ! empty( $gift_ids ) ? (int) $gift_ids[0] : 0;
    if ( $first_id <= 0 ) {
        return max( 1, (int) $default_slots );
    }

    return teinvit_catalog_extra_gifts_slots_for_product( $catalog, $first_id, $default_slots );
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
        'extra_gifts_addon_slots' => teinvit_parse_addon_slots_csv( $entry['extra_gifts_addon_slots'] ?? [], $extra_gifts, 10 ),
        'default_free_gift_slots' => max( 0, (int) ( $entry['default_free_gift_slots'] ?? $defaults['default_free_gift_slots'] ) ),
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
        $merged['extra_gifts_addon_slots'] = array_merge( $merged['extra_gifts_addon_slots'], (array) ( $entry['extra_gifts_addon_slots'] ?? [] ) );
        if ( isset( $entry['default_free_gift_slots'] ) ) {
            $merged['default_free_gift_slots'] = max( 0, (int) $entry['default_free_gift_slots'] );
        }
    }

    $merged['basic_product_ids'] = teinvit_parse_product_ids_csv( $merged['basic_product_ids'] );
    $merged['premium_upgrade_addon_ids'] = teinvit_parse_product_ids_csv( $merged['premium_upgrade_addon_ids'] );
    $merged['premium_native_product_ids'] = teinvit_parse_product_ids_csv( $merged['premium_native_product_ids'] );
    $merged['extra_edits_addon_ids'] = teinvit_parse_product_ids_csv( $merged['extra_edits_addon_ids'] );
    $merged['extra_gifts_addon_ids'] = teinvit_parse_product_ids_csv( $merged['extra_gifts_addon_ids'] );
    $merged['extra_gifts_addon_slots'] = teinvit_parse_addon_slots_csv( $merged['extra_gifts_addon_slots'], $merged['extra_gifts_addon_ids'], 10 );
    $merged['default_free_gift_slots'] = max( 0, (int) ( $merged['default_free_gift_slots'] ?? 20 ) );

    return $merged;
}

function teinvit_find_catalog_vertical_for_product_id( $product_id ) {
    $product_id = (int) $product_id;
    if ( $product_id <= 0 ) {
        return '';
    }

    $catalog = teinvit_get_custom_products_catalog();
    foreach ( $catalog as $vertical => $entry ) {
        $pool = array_merge(
            teinvit_catalog_role_ids( $entry, 'basic_product_ids' ),
            teinvit_catalog_role_ids( $entry, 'premium_native_product_ids' ),
            teinvit_catalog_role_ids( $entry, 'extra_edits_addon_ids' ),
            teinvit_catalog_role_ids( $entry, 'extra_gifts_addon_ids' ),
            teinvit_catalog_role_ids( $entry, 'premium_upgrade_addon_ids' )
        );
        if ( in_array( $product_id, array_map( 'intval', $pool ), true ) ) {
            return (string) $vertical;
        }
    }

    return '';
}

function teinvit_get_catalog_for_token( $token ) {
    $token = sanitize_text_field( (string) $token );
    $catalog_all = teinvit_get_custom_products_catalog();
    if ( $token === '' ) {
        return isset( $catalog_all['wedding'] ) ? $catalog_all['wedding'] : teinvit_custom_product_defaults();
    }

    $order_id = function_exists( 'teinvit_get_order_id_by_token' ) ? (int) teinvit_get_order_id_by_token( $token ) : 0;
    $order = $order_id ? wc_get_order( $order_id ) : null;
    if ( ! $order ) {
        return isset( $catalog_all['wedding'] ) ? $catalog_all['wedding'] : teinvit_custom_product_defaults();
    }

    foreach ( $order->get_items() as $item ) {
        $vertical = teinvit_find_catalog_vertical_for_product_id( (int) $item->get_product_id() );
        if ( $vertical !== '' && isset( $catalog_all[ $vertical ] ) ) {
            return $catalog_all[ $vertical ];
        }
    }

    return isset( $catalog_all['wedding'] ) ? $catalog_all['wedding'] : teinvit_custom_product_defaults();
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
