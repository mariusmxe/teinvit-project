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
        'default_included_edits' => 2,
    ];
}

function teinvit_default_included_edits_fallback() {
    return 2;
}

function teinvit_normalize_nonnegative_catalog_int( $value, $fallback = 0 ) {
    if ( is_array( $value ) || is_object( $value ) || $value === '' || $value === null ) {
        return max( 0, (int) $fallback );
    }

    if ( ! is_numeric( $value ) ) {
        return max( 0, (int) $fallback );
    }

    return max( 0, (int) $value );
}

function teinvit_catalog_default_included_edits( array $catalog_entry, $fallback = 2 ) {
    $fallback = teinvit_normalize_nonnegative_catalog_int( $fallback, teinvit_default_included_edits_fallback() );
    if ( ! array_key_exists( 'default_included_edits', $catalog_entry ) ) {
        return $fallback;
    }

    return teinvit_normalize_nonnegative_catalog_int( $catalog_entry['default_included_edits'], $fallback );
}

function teinvit_catalog_order_contains_role( $order, array $catalog, $role_key ) {
    if ( ! $order || ! method_exists( $order, 'get_items' ) ) {
        return false;
    }

    $ids = teinvit_catalog_role_ids( $catalog, $role_key );
    if ( empty( $ids ) ) {
        return false;
    }

    foreach ( $order->get_items( 'line_item' ) as $item ) {
        $product_id = (int) $item->get_product_id();
        $variation_id = (int) $item->get_variation_id();
        if ( in_array( $product_id, $ids, true ) || in_array( $variation_id, $ids, true ) ) {
            return true;
        }
    }

    return false;
}

function teinvit_get_catalog_for_order( $order ) {
    $catalog_all = teinvit_get_custom_products_catalog();
    if ( ! $order || ! method_exists( $order, 'get_items' ) ) {
        return isset( $catalog_all['wedding'] ) ? $catalog_all['wedding'] : teinvit_custom_product_defaults();
    }

    foreach ( $order->get_items( 'line_item' ) as $item ) {
        $vertical = teinvit_find_catalog_vertical_for_product_id( (int) $item->get_product_id() );
        if ( $vertical === '' ) {
            $vertical = teinvit_find_catalog_vertical_for_product_id( (int) $item->get_variation_id() );
        }
        if ( $vertical !== '' && isset( $catalog_all[ $vertical ] ) ) {
            return $catalog_all[ $vertical ];
        }
    }

    return isset( $catalog_all['wedding'] ) ? $catalog_all['wedding'] : teinvit_custom_product_defaults();
}

function teinvit_order_has_basic_invitation_product( $order, array $catalog ) {
    return teinvit_catalog_order_contains_role( $order, $catalog, 'basic_product_ids' );
}

function teinvit_order_has_premium_native_product( $order, array $catalog ) {
    return teinvit_catalog_order_contains_role( $order, $catalog, 'premium_native_product_ids' );
}

function teinvit_order_should_receive_initial_included_edits( $order, array $catalog ) {
    if ( teinvit_order_has_premium_native_product( $order, $catalog ) ) {
        return true;
    }

    if ( teinvit_order_has_basic_invitation_product( $order, $catalog ) ) {
        return false;
    }

    return true;
}

function teinvit_config_apply_default_included_edits( array $config, array $catalog_entry, $source = '', $source_id = 0 ) {
    if ( ! empty( $config['default_included_edits_applied'] ) ) {
        return $config;
    }

    $config['edits_free_remaining'] = teinvit_catalog_default_included_edits( $catalog_entry, teinvit_default_included_edits_fallback() );
    $config['edits_admin_remaining'] = max( 0, (int) ( $config['edits_admin_remaining'] ?? 0 ) );
    $config['edits_paid_remaining'] = max( 0, (int) ( $config['edits_paid_remaining'] ?? 0 ) );
    $config['default_included_edits_applied'] = 1;
    $config['default_included_edits_applied_value'] = (int) $config['edits_free_remaining'];

    $source = sanitize_key( (string) $source );
    if ( $source !== '' ) {
        $config['default_included_edits_applied_source'] = $source;
    }

    $source_id = (int) $source_id;
    if ( $source_id > 0 ) {
        $config['default_included_edits_applied_order_id'] = $source_id;
    }

    return $config;
}

function teinvit_config_apply_initial_edit_entitlement( array $config, array $catalog_entry, $is_premium, $source = '', $source_id = 0 ) {
    $config['edits_admin_remaining'] = max( 0, (int) ( $config['edits_admin_remaining'] ?? 0 ) );
    $config['edits_paid_remaining'] = max( 0, (int) ( $config['edits_paid_remaining'] ?? 0 ) );

    if ( $is_premium ) {
        return teinvit_config_apply_default_included_edits( $config, $catalog_entry, $source, $source_id );
    }

    $config['edits_free_remaining'] = 0;
    unset( $config['default_included_edits_applied'] );
    unset( $config['default_included_edits_applied_value'] );
    unset( $config['default_included_edits_applied_source'] );
    unset( $config['default_included_edits_applied_order_id'] );

    return $config;
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

function teinvit_gifts_default_base_slots_for_token( $token, array $config = [] ) {
    $configured = array_key_exists( 'gifts_base_slots_applied', $config )
        ? max( 0, (int) $config['gifts_base_slots_applied'] )
        : null;
    if ( $configured !== null && $configured > 0 ) {
        return $configured;
    }

    $catalog = function_exists( 'teinvit_get_catalog_for_token' ) ? teinvit_get_catalog_for_token( $token ) : [];
    $catalog_slots = max( 0, (int) ( $catalog['default_free_gift_slots'] ?? 20 ) );
    if ( $catalog_slots > 0 ) {
        return $catalog_slots;
    }

    return $configured !== null ? $configured : 20;
}

function teinvit_gifts_ensure_base_and_legacy_allocations( $token, array $config, array $allocations ) {
    $token = sanitize_text_field( (string) $token );
    $has_base = false;
    $has_addon = false;

    foreach ( $allocations as $allocation ) {
        if ( ! is_array( $allocation ) ) {
            continue;
        }
        $slots_total = max( 0, (int) ( $allocation['slots_total'] ?? 0 ) );
        if ( $slots_total <= 0 ) {
            continue;
        }

        $kind = sanitize_key( (string) ( $allocation['kind'] ?? 'addon' ) );
        if ( $kind === 'base' ) {
            $has_base = true;
        } elseif ( $kind !== 'admin_grant' ) {
            $has_addon = true;
        }
    }

    if ( ! $has_base ) {
        $base_slots = teinvit_gifts_default_base_slots_for_token( $token, $config );
        if ( $base_slots > 0 ) {
            $context = function_exists( 'teinvit_resolve_token_context' ) ? teinvit_resolve_token_context( $token ) : [];
            $allocations[] = [
                'allocation_key' => 'legacy-base',
                'kind' => 'base',
                'order_id' => is_array( $context ) ? max( 0, (int) ( $context['order_id'] ?? 0 ) ) : 0,
                'item_id' => is_array( $context ) ? max( 0, (int) ( $context['order_item_id'] ?? 0 ) ) : 0,
                'product_id' => is_array( $context ) ? max( 0, (int) ( $context['product_id'] ?? 0 ) ) : 0,
                'qty' => 1,
                'slots_per_unit' => $base_slots,
                'slots_total' => $base_slots,
                'slots_remaining' => $base_slots,
                'status' => 'applied',
                'applied_at' => '',
            ];
        }
    }

    if ( ! $has_addon ) {
        $addon_slots = max( 0, (int) ( $config['gifts_extra_slots'] ?? 0 ) );
        if ( $addon_slots > 0 ) {
            $allocations[] = [
                'allocation_key' => 'legacy-addon',
                'kind' => 'addon',
                'order_id' => 0,
                'item_id' => 0,
                'slots_total' => $addon_slots,
                'slots_remaining' => $addon_slots,
                'status' => 'applied',
                'applied_at' => '',
            ];
        }
    }

    return array_values( $allocations );
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
        'default_included_edits' => teinvit_catalog_default_included_edits( $entry, $defaults['default_included_edits'] ),
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
        if ( isset( $entry['default_included_edits'] ) ) {
            $merged['default_included_edits'] = teinvit_catalog_default_included_edits( $entry, $merged['default_included_edits'] ?? teinvit_default_included_edits_fallback() );
        }
    }

    $merged['basic_product_ids'] = teinvit_parse_product_ids_csv( $merged['basic_product_ids'] );
    $merged['premium_upgrade_addon_ids'] = teinvit_parse_product_ids_csv( $merged['premium_upgrade_addon_ids'] );
    $merged['premium_native_product_ids'] = teinvit_parse_product_ids_csv( $merged['premium_native_product_ids'] );
    $merged['extra_edits_addon_ids'] = teinvit_parse_product_ids_csv( $merged['extra_edits_addon_ids'] );
    $merged['extra_gifts_addon_ids'] = teinvit_parse_product_ids_csv( $merged['extra_gifts_addon_ids'] );
    $merged['extra_gifts_addon_slots'] = teinvit_parse_addon_slots_csv( $merged['extra_gifts_addon_slots'], $merged['extra_gifts_addon_ids'], 10 );
    $merged['default_free_gift_slots'] = max( 0, (int) ( $merged['default_free_gift_slots'] ?? 20 ) );
    $merged['default_included_edits'] = teinvit_catalog_default_included_edits( $merged, teinvit_default_included_edits_fallback() );

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
        if ( $vertical === '' ) {
            $vertical = teinvit_find_catalog_vertical_for_product_id( (int) $item->get_variation_id() );
        }
        if ( $vertical !== '' && isset( $catalog_all[ $vertical ] ) ) {
            return $catalog_all[ $vertical ];
        }
    }

    return isset( $catalog_all['wedding'] ) ? $catalog_all['wedding'] : teinvit_custom_product_defaults();
}

function teinvit_catalog_role_ids( array $catalog, $role_key ) {
    return teinvit_parse_product_ids_csv( $catalog[ $role_key ] ?? [] );
}

function teinvit_catalog_product_id_matches_role( array $catalog, $role_key, array $product_ids ) {
    $role_ids = function_exists( 'teinvit_catalog_role_ids' ) ? teinvit_catalog_role_ids( $catalog, $role_key ) : [];
    if ( empty( $role_ids ) ) {
        return false;
    }

    $product_ids = array_values( array_filter( array_map( 'intval', $product_ids ), static function( $id ) {
        return $id > 0;
    } ) );

    return ! empty( array_intersect( $product_ids, array_map( 'intval', $role_ids ) ) );
}

function teinvit_get_configurable_product_context( $product_id, $variation_id = 0, $preferred_vertical = '' ) {
    $product_id = (int) $product_id;
    $variation_id = (int) $variation_id;
    $product_ids = array_values( array_filter( [ $product_id, $variation_id ], static function( $id ) {
        return (int) $id > 0;
    } ) );

    if ( empty( $product_ids ) || ! function_exists( 'teinvit_get_custom_products_catalog' ) ) {
        return null;
    }

    $catalogs = teinvit_get_custom_products_catalog();
    if ( ! is_array( $catalogs ) ) {
        return null;
    }

    $preferred_vertical = sanitize_key( (string) $preferred_vertical );
    $vertical_order = [];
    if ( $preferred_vertical !== '' && isset( $catalogs[ $preferred_vertical ] ) ) {
        $vertical_order[] = $preferred_vertical;
    }
    foreach ( array_keys( $catalogs ) as $vertical_key ) {
        $vertical_key = sanitize_key( (string) $vertical_key );
        if ( $vertical_key !== '' ) {
            $vertical_order[] = $vertical_key;
        }
    }
    $vertical_order = array_values( array_unique( $vertical_order ) );

    foreach ( $vertical_order as $vertical_key ) {
        $catalog = isset( $catalogs[ $vertical_key ] ) && is_array( $catalogs[ $vertical_key ] ) ? $catalogs[ $vertical_key ] : [];
        if ( teinvit_catalog_product_id_matches_role( $catalog, 'basic_product_ids', $product_ids ) ) {
            return [
                'vertical' => $vertical_key,
                'package_type' => 'basic',
                'role_key' => 'basic_product_ids',
                'catalog' => $catalog,
            ];
        }
        if ( teinvit_catalog_product_id_matches_role( $catalog, 'premium_native_product_ids', $product_ids ) ) {
            return [
                'vertical' => $vertical_key,
                'package_type' => 'premium',
                'role_key' => 'premium_native_product_ids',
                'catalog' => $catalog,
            ];
        }
    }

    return null;
}

function teinvit_order_configurable_quantity_violations( $order ) {
    if ( ! $order || ! method_exists( $order, 'get_items' ) ) {
        return [];
    }

    $violations = [];
    foreach ( $order->get_items( 'line_item' ) as $item_id => $item ) {
        if ( ! is_object( $item ) || ! method_exists( $item, 'get_quantity' ) ) {
            continue;
        }
        if ( teinvit_order_item_is_addon_product( $item ) ) {
            continue;
        }

        $product_id = method_exists( $item, 'get_product_id' ) ? (int) $item->get_product_id() : 0;
        $variation_id = method_exists( $item, 'get_variation_id' ) ? (int) $item->get_variation_id() : 0;
        $context = teinvit_get_configurable_product_context( $product_id, $variation_id );
        if ( ! $context ) {
            continue;
        }

        $qty = (float) $item->get_quantity();
        if ( $qty <= 1 ) {
            continue;
        }

        $violations[] = [
            'item_id' => (int) $item_id,
            'product_id' => $product_id,
            'variation_id' => $variation_id,
            'qty' => $qty,
            'vertical' => $context['vertical'],
            'package_type' => $context['package_type'],
            'name' => method_exists( $item, 'get_name' ) ? (string) $item->get_name() : '',
        ];
    }

    return $violations;
}


function teinvit_order_contains_invitation_product( $order ) {
    if ( ! $order ) {
        return false;
    }

    foreach ( $order->get_items( 'line_item' ) as $item ) {
        if ( teinvit_order_item_is_addon_product( $item ) ) {
            continue;
        }

        $product_id = method_exists( $item, 'get_product_id' ) ? (int) $item->get_product_id() : 0;
        $variation_id = method_exists( $item, 'get_variation_id' ) ? (int) $item->get_variation_id() : 0;
        if ( teinvit_get_configurable_product_context( $product_id, $variation_id ) ) {
            return true;
        }
    }

    return false;
}

function teinvit_order_item_is_addon_product( $item ) {
    if ( ! is_object( $item ) || ! function_exists( 'teinvit_addon_product_context' ) ) {
        return false;
    }

    $product_id = method_exists( $item, 'get_product_id' ) ? (int) $item->get_product_id() : 0;
    $variation_id = method_exists( $item, 'get_variation_id' ) ? (int) $item->get_variation_id() : 0;

    return (bool) teinvit_addon_product_context( $product_id, $variation_id );
}

function teinvit_phase3_order_note_once( WC_Order $order, $note, $key ) {
    $key = sanitize_key( 'phase3_' . md5( (string) $key ) );
    if ( $key === '' ) {
        return false;
    }

    $stored = $order->get_meta( '_teinvit_phase3_note_keys', true );
    if ( ! is_array( $stored ) ) {
        $stored = [];
    }

    if ( in_array( $key, $stored, true ) ) {
        return false;
    }

    $order->add_order_note( (string) $note );
    $stored[] = $key;
    $order->update_meta_data( '_teinvit_phase3_note_keys', array_values( array_unique( $stored ) ) );

    return true;
}

function teinvit_order_invitation_item_context( WC_Order $order, $item_id, $item ) {
    if ( ! $item instanceof WC_Order_Item_Product || teinvit_order_item_is_addon_product( $item ) ) {
        return null;
    }

    $product_id = method_exists( $item, 'get_product_id' ) ? (int) $item->get_product_id() : 0;
    $variation_id = method_exists( $item, 'get_variation_id' ) ? (int) $item->get_variation_id() : 0;
    $product_context = teinvit_get_configurable_product_context( $product_id, $variation_id );
    if ( ! $product_context ) {
        return null;
    }

    $product = null;
    if ( function_exists( 'wc_get_product' ) ) {
        $lookup_id = $variation_id > 0 ? $variation_id : $product_id;
        if ( $lookup_id > 0 ) {
            $product = wc_get_product( $lookup_id );
        }
        if ( ! is_object( $product ) && $product_id > 0 ) {
            $product = wc_get_product( $product_id );
        }
    }

    $product_slug = is_object( $product ) && method_exists( $product, 'get_slug' )
        ? sanitize_title( (string) $product->get_slug() )
        : '';
    $product_name = method_exists( $item, 'get_name' )
        ? sanitize_text_field( (string) $item->get_name() )
        : ( is_object( $product ) && method_exists( $product, 'get_name' ) ? sanitize_text_field( (string) $product->get_name() ) : '' );

    return [
        'order_id' => (int) $order->get_id(),
        'order_item_id' => (int) $item_id,
        'product_id' => $product_id,
        'variation_id' => $variation_id,
        'quantity_index' => 1,
        'vertical' => sanitize_key( (string) $product_context['vertical'] ),
        'package_type' => sanitize_key( (string) $product_context['package_type'] ),
        'product_slug' => $product_slug,
        'product_name' => $product_name,
        'catalog' => is_array( $product_context['catalog'] ?? null ) ? $product_context['catalog'] : [],
        'source' => 'order_tokens',
    ];
}

function teinvit_order_invitation_item_contexts( WC_Order $order ) {
    $contexts = [];
    $skipped_addons = [];

    foreach ( $order->get_items( 'line_item' ) as $item_id => $item ) {
        if ( teinvit_order_item_is_addon_product( $item ) ) {
            $skipped_addons[] = [
                'item_id' => (int) $item_id,
                'product_id' => method_exists( $item, 'get_product_id' ) ? (int) $item->get_product_id() : 0,
                'name' => method_exists( $item, 'get_name' ) ? (string) $item->get_name() : '',
            ];
            continue;
        }

        $context = teinvit_order_invitation_item_context( $order, $item_id, $item );
        if ( $context ) {
            $contexts[ (int) $item_id ] = $context;
        }
    }

    return [
        'eligible' => $contexts,
        'skipped_addons' => $skipped_addons,
    ];
}

function teinvit_generate_unique_order_token( $order_id ) {
    $order_id = max( 0, (int) $order_id );
    if ( $order_id <= 0 ) {
        return '';
    }

    for ( $attempt = 0; $attempt < 10; $attempt++ ) {
        $token = $order_id . '-' . teinvit_generate_token_part( 20 );
        $exists_in_order_tokens = function_exists( 'teinvit_get_order_token_row' ) && teinvit_get_order_token_row( $token );
        $exists_legacy = function_exists( 'teinvit_find_legacy_order_id_by_token' ) && teinvit_find_legacy_order_id_by_token( $token ) > 0;
        if ( ! $exists_in_order_tokens && ! $exists_legacy ) {
            return $token;
        }
    }

    return '';
}

function teinvit_order_token_public_context( array $context, array $row, $eligible_count, $generated ) {
    unset( $context['catalog'] );

    $context['token'] = sanitize_text_field( (string) ( $row['token'] ?? '' ) );
    $context['order_token_id'] = (int) ( $row['id'] ?? 0 );
    $context['source'] = 'order_tokens';
    $context['quantity_index'] = 1;
    $context['eligible_token_count'] = max( 0, (int) $eligible_count );
    $context['is_multi_token_order'] = (int) $eligible_count > 1;
    $context['generated_on_this_run'] = (bool) $generated;

    return $context;
}

function teinvit_seed_order_token_context( WC_Order $order, array $context, array $row, $eligible_count, $generated ) {
    $token = sanitize_text_field( (string) ( $row['token'] ?? '' ) );
    if ( $token === '' ) {
        return false;
    }

    $event_context = teinvit_order_token_public_context( $context, $row, $eligible_count, $generated );
    $seed_context = array_merge( $event_context, [
        'catalog' => is_array( $context['catalog'] ?? null ) ? $context['catalog'] : [],
    ] );

    $previous_status = sanitize_key( (string) ( $row['status'] ?? '' ) );
    if ( $previous_status === 'refunded' ) {
        return true;
    }

    $seeded = function_exists( 'teinvit_seed_invitation_if_missing' )
        ? teinvit_seed_invitation_if_missing( $token, (int) $order->get_id(), $seed_context )
        : null;

    $ok = is_array( $seeded );
    if ( function_exists( 'teinvit_update_order_token_row' ) ) {
        teinvit_update_order_token_row( (int) $row['id'], [
            'status' => $ok ? 'active' : 'failed',
            'pdf_status' => 'not_generated',
            'last_error' => $ok ? '' : 'seed_failed',
            'debug_context' => [
                'phase' => 'phase3_token_generation',
                'seeded' => $ok ? 1 : 0,
                'generated_on_this_run' => $generated ? 1 : 0,
                'eligible_token_count' => max( 0, (int) $eligible_count ),
            ],
        ] );
    }

    if ( $ok && ( $generated || $previous_status !== 'active' ) ) {
        if ( (int) $eligible_count === 1 ) {
            update_post_meta( (int) $order->get_id(), '_teinvit_token', $token );
            update_post_meta( (int) $order->get_id(), '_teinvit_vertical_key_snapshot', sanitize_key( (string) ( $event_context['vertical'] ?? '' ) ) );
            update_post_meta( (int) $order->get_id(), '_teinvit_vertical_key', sanitize_key( (string) ( $event_context['vertical'] ?? '' ) ) );
        }
        do_action( 'teinvit_token_generated', (int) $order->get_id(), $token, $event_context );
    }

    return $ok;
}

function teinvit_attach_order_tokens_on_completed_phase3( $order_id ) {
    $order_id = (int) $order_id;
    $order = function_exists( 'wc_get_order' ) ? wc_get_order( $order_id ) : null;
    if ( ! $order ) {
        return;
    }

    $existing_order_token_rows = function_exists( 'teinvit_get_order_tokens_for_order' ) ? teinvit_get_order_tokens_for_order( $order_id ) : [];
    $legacy_token = sanitize_text_field( (string) get_post_meta( $order_id, '_teinvit_token', true ) );
    if ( $legacy_token !== '' && empty( $existing_order_token_rows ) ) {
        teinvit_phase3_order_note_once( $order, 'TeInvit: legacy order token already exists; Phase 3 did not backfill order_tokens.', 'legacy_existing_token' );
        $order->save();
        return;
    }

    $quantity_violations = teinvit_order_configurable_quantity_violations( $order );
    if ( ! empty( $quantity_violations ) ) {
        foreach ( $quantity_violations as $violation ) {
            $qty_label = rtrim( rtrim( number_format( (float) $violation['qty'], 4, '.', '' ), '0' ), '.' );
            teinvit_phase3_order_note_once( $order, sprintf(
                'TeInvit: blocked item #%d because configurable product quantity is greater than 1 (product %d, qty %s, %s %s).',
                (int) $violation['item_id'],
                (int) $violation['product_id'],
                $qty_label,
                (string) $violation['vertical'],
                (string) $violation['package_type']
            ), 'qty_gt_1_' . (int) $violation['item_id'] );
        }
        $order->update_meta_data( '_teinvit_token_generation_blocked_reason', 'configurable_qty_gt_1' );
        $order->save();
        return;
    }

    $scan = teinvit_order_invitation_item_contexts( $order );
    $eligible_contexts = $scan['eligible'];
    $eligible_count = count( $eligible_contexts );
    $generated_count = 0;
    $reused_count = 0;
    $failed_count = 0;
    $tokens = [];

    foreach ( $scan['skipped_addons'] as $addon ) {
        teinvit_phase3_order_note_once( $order, sprintf(
            'TeInvit: skipped addon item #%d, no token generation required.',
            (int) $addon['item_id']
        ), 'skipped_addon_' . (int) $addon['item_id'] );
    }

    if ( $eligible_count <= 0 ) {
        teinvit_phase3_order_note_once( $order, 'TeInvit: no invitation line items found; no token generated.', 'no_invitation_items' );
        $order->save();
        return;
    }

    foreach ( $eligible_contexts as $item_id => $context ) {
        $existing_row = function_exists( 'teinvit_get_order_token_row_by_unit' )
            ? teinvit_get_order_token_row_by_unit( $order_id, (int) $item_id, 1 )
            : null;
        $generated = false;

        if ( is_array( $existing_row ) ) {
            $row = $existing_row;
            $reused_count++;
            teinvit_phase3_order_note_once( $order, sprintf(
                'TeInvit: reused existing token %s for item #%d (%s %s).',
                (string) $row['token'],
                (int) $item_id,
                (string) $context['vertical'],
                (string) $context['package_type']
            ), 'reused_' . (int) $item_id . '_' . (int) ( $row['id'] ?? 0 ) );
        } else {
            $token = teinvit_generate_unique_order_token( $order_id );
            if ( $token === '' || ! function_exists( 'teinvit_insert_order_token_row' ) ) {
                $failed_count++;
                teinvit_phase3_order_note_once( $order, sprintf( 'TeInvit: failed to generate token for item #%d.', (int) $item_id ), 'failed_generate_' . (int) $item_id );
                continue;
            }

            $row = teinvit_insert_order_token_row( array_merge( $context, [
                'token' => $token,
                'status' => 'pending',
                'pdf_status' => 'not_generated',
                'legacy' => 0,
                'debug_context' => [
                    'phase' => 'phase3_token_generation',
                    'order_item_id' => (int) $item_id,
                ],
            ] ) );

            if ( ! is_array( $row ) ) {
                $row = function_exists( 'teinvit_get_order_token_row_by_unit' )
                    ? teinvit_get_order_token_row_by_unit( $order_id, (int) $item_id, 1 )
                    : null;
            }

            if ( ! is_array( $row ) ) {
                $failed_count++;
                teinvit_phase3_order_note_once( $order, sprintf( 'TeInvit: failed to save order_tokens row for item #%d.', (int) $item_id ), 'failed_save_' . (int) $item_id );
                continue;
            }

            $generated = true;
            $generated_count++;
        }

        $seeded = teinvit_seed_order_token_context( $order, $context, $row, $eligible_count, $generated );
        if ( ! $seeded ) {
            $failed_count++;
            teinvit_phase3_order_note_once( $order, sprintf(
                'TeInvit: seed failed for token %s, item #%d (%s %s).',
                (string) $row['token'],
                (int) $item_id,
                (string) $context['vertical'],
                (string) $context['package_type']
            ), 'seed_failed_' . (int) $item_id . '_' . (string) $row['token'] );
            continue;
        }

        $tokens[] = [
            'token' => (string) $row['token'],
            'vertical' => (string) $context['vertical'],
            'package_type' => (string) $context['package_type'],
            'order_item_id' => (int) $item_id,
        ];
        if ( $generated ) {
            teinvit_phase3_order_note_once( $order, sprintf(
                'TeInvit: generated token %s for item #%d %s %s.',
                (string) $row['token'],
                (int) $item_id,
                (string) $context['vertical'],
                (string) $context['package_type']
            ), 'generated_' . (int) $item_id . '_' . (string) $row['token'] );
        }
    }

    if ( $eligible_count === 1 && count( $tokens ) === 1 ) {
        $bridge_token = sanitize_text_field( (string) $tokens[0]['token'] );
        update_post_meta( $order_id, '_teinvit_token', $bridge_token );
        update_post_meta( $order_id, '_teinvit_vertical_key_snapshot', sanitize_key( (string) $tokens[0]['vertical'] ) );
        update_post_meta( $order_id, '_teinvit_vertical_key', sanitize_key( (string) $tokens[0]['vertical'] ) );
    }

    teinvit_phase3_order_note_once( $order, sprintf(
        'TeInvit: completed token scan found %d invitation item(s); generated %d, reused %d, failed %d.',
        $eligible_count,
        $generated_count,
        $reused_count,
        $failed_count
    ), 'completed_scan_summary' );

    $order->save();

    if ( $reused_count > 0 && function_exists( 'teinvit_generate_pdfs_for_order_tokens' ) ) {
        teinvit_generate_pdfs_for_order_tokens( $order_id, false );
    }
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

    teinvit_attach_order_tokens_on_completed_phase3( $order_id );
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
