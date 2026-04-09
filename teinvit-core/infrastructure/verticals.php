<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function teinvit_vertical_keys() {
    return [ 'wedding', 'baptism', 'birthday' ];
}

function teinvit_default_vertical_key() {
    return 'wedding';
}

function teinvit_normalize_vertical_key( $vertical_key ) {
    $vertical_key = sanitize_key( (string) $vertical_key );
    return in_array( $vertical_key, teinvit_vertical_keys(), true ) ? $vertical_key : teinvit_default_vertical_key();
}

function teinvit_vertical_storage_family_map( $vertical_key ) {
    global $wpdb;

    $vertical_key = teinvit_normalize_vertical_key( $vertical_key );
    $prefix = $wpdb->prefix;

    $map = [
        'wedding' => [
            'invitations' => $prefix . 'teinvit_invitations',
            'versions' => $prefix . 'teinvit_versions',
            'rsvp' => $prefix . 'teinvit_rsvp',
            'gifts' => $prefix . 'teinvit_gifts',
        ],
        'baptism' => [
            'invitations' => $prefix . 'teinvit_baptism_invitations',
            'versions' => $prefix . 'teinvit_baptism_versions',
            'rsvp' => $prefix . 'teinvit_baptism_rsvp',
            'gifts' => $prefix . 'teinvit_baptism_gifts',
        ],
        'birthday' => [
            'invitations' => $prefix . 'teinvit_birthday_invitations',
            'versions' => $prefix . 'teinvit_birthday_versions',
            'rsvp' => $prefix . 'teinvit_birthday_rsvp',
            'gifts' => $prefix . 'teinvit_birthday_gifts',
        ],
    ];

    return $map[ $vertical_key ];
}

function teinvit_module_contract_registry() {
    return [
        'wedding' => [
            'key' => 'wedding',
            'label' => 'Wedding',
            'status' => 'legacy-safe',
            'path' => TEINVIT_CORE_PATH . 'modules/wedding/module.php',
            'boot_callable' => null,
        ],
        'baptism' => [
            'key' => 'baptism',
            'label' => 'Baptism',
            'status' => 'scaffold',
            'path' => TEINVIT_CORE_PATH . 'modules/baptism/module.php',
            'boot_callable' => 'teinvit_baptism_module_bootstrap_contract',
        ],
        'birthday' => [
            'key' => 'birthday',
            'label' => 'Birthday',
            'status' => 'scaffold',
            'path' => TEINVIT_CORE_PATH . 'modules/birthday/module.php',
            'boot_callable' => 'teinvit_birthday_module_bootstrap_contract',
        ],
    ];
}

function teinvit_vertical_module_contract( $vertical_key ) {
    $vertical_key = teinvit_normalize_vertical_key( $vertical_key );
    $registry = teinvit_module_contract_registry();

    return isset( $registry[ $vertical_key ] )
        ? $registry[ $vertical_key ]
        : $registry[ teinvit_default_vertical_key() ];
}

function teinvit_module_contract_for_token( $token ) {
    $vertical_key = teinvit_resolve_token_vertical( $token );
    return teinvit_vertical_module_contract( $vertical_key );
}

function teinvit_register_vertical_module_runtime( $vertical_key, array $runtime ) {
    $vertical_key = teinvit_normalize_vertical_key( $vertical_key );
    $current = isset( $GLOBALS['teinvit_vertical_module_runtime'] ) && is_array( $GLOBALS['teinvit_vertical_module_runtime'] )
        ? $GLOBALS['teinvit_vertical_module_runtime']
        : [];
    $current[ $vertical_key ] = $runtime;
    $GLOBALS['teinvit_vertical_module_runtime'] = $current;
}

function teinvit_get_vertical_module_runtime( $vertical_key = 'all' ) {
    $current = isset( $GLOBALS['teinvit_vertical_module_runtime'] ) && is_array( $GLOBALS['teinvit_vertical_module_runtime'] )
        ? $GLOBALS['teinvit_vertical_module_runtime']
        : [];

    if ( $vertical_key === 'all' ) {
        return $current;
    }

    $vertical_key = teinvit_normalize_vertical_key( $vertical_key );
    return isset( $current[ $vertical_key ] ) ? $current[ $vertical_key ] : [];
}

function teinvit_module_runtime_for_token( $token ) {
    $vertical_key = teinvit_resolve_token_vertical( $token );
    return teinvit_get_vertical_module_runtime( $vertical_key );
}

function teinvit_resolve_vertical_for_order( $order ) {
    if ( ! $order instanceof WC_Order ) {
        return teinvit_default_vertical_key();
    }

    foreach ( $order->get_items() as $item ) {
        $product_id = (int) $item->get_product_id();
        if ( $product_id <= 0 || ! function_exists( 'teinvit_find_catalog_vertical_for_product_id' ) ) {
            continue;
        }

        $candidate = teinvit_find_catalog_vertical_for_product_id( $product_id );
        if ( $candidate !== '' ) {
            return teinvit_normalize_vertical_key( $candidate );
        }
    }

    return teinvit_default_vertical_key();
}

function teinvit_snapshot_vertical_on_token_generated( $order_id, $token ) {
    $order_id = (int) $order_id;
    $token = sanitize_text_field( (string) $token );
    if ( $order_id <= 0 || $token === '' ) {
        return;
    }

    $order = wc_get_order( $order_id );
    if ( ! $order ) {
        return;
    }

    $vertical_key = teinvit_resolve_vertical_for_order( $order );

    update_post_meta( $order_id, '_teinvit_vertical_key_snapshot', $vertical_key );
    update_post_meta( $order_id, '_teinvit_vertical_key', $vertical_key );
}
add_action( 'teinvit_token_generated', 'teinvit_snapshot_vertical_on_token_generated', 20, 2 );

function teinvit_resolve_token_vertical( $token ) {
    $token = sanitize_text_field( (string) $token );
    if ( $token === '' ) {
        return teinvit_default_vertical_key();
    }

    if ( function_exists( 'teinvit_get_invitation' ) ) {
        $inv = teinvit_get_invitation( $token );
        if ( is_array( $inv ) && ! empty( $inv['module_key'] ) ) {
            return teinvit_normalize_vertical_key( $inv['module_key'] );
        }
    }

    $order_id = function_exists( 'teinvit_get_order_id_by_token' ) ? (int) teinvit_get_order_id_by_token( $token ) : 0;
    if ( $order_id > 0 ) {
        $snapshot = get_post_meta( $order_id, '_teinvit_vertical_key_snapshot', true );
        if ( is_string( $snapshot ) && $snapshot !== '' ) {
            return teinvit_normalize_vertical_key( $snapshot );
        }

        $meta_vertical = get_post_meta( $order_id, '_teinvit_vertical_key', true );
        if ( is_string( $meta_vertical ) && $meta_vertical !== '' ) {
            return teinvit_normalize_vertical_key( $meta_vertical );
        }

    }

    return teinvit_default_vertical_key();
}

function teinvit_storage_tables_for_vertical( $vertical_key ) {
    return teinvit_vertical_storage_family_map( $vertical_key );
}

function teinvit_storage_tables_for_token( $token ) {
    $vertical_key = teinvit_resolve_token_vertical( $token );
    return teinvit_storage_tables_for_vertical( $vertical_key );
}

function teinvit_is_legacy_wedding_token( $token ) {
    return teinvit_resolve_token_vertical( $token ) === teinvit_default_vertical_key();
}
