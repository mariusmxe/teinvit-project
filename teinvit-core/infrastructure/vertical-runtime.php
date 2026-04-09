<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function teinvit_theme_class_from_key( $theme_key ) {
    $theme_key = strtolower( trim( (string) $theme_key ) );
    switch ( $theme_key ) {
        case 'romantic':
            return 'theme-romantic-floral';
        case 'modern':
            return 'theme-modern-minimal';
        case 'classic':
            return 'theme-classic-elegant';
        case 'editorial':
        default:
            return 'theme-editorial-luxury';
    }
}

function teinvit_extract_order_wapf_field_map( WC_Order $order ) {
    $items = $order->get_items();
    if ( empty( $items ) ) {
        return [];
    }

    $item = reset( $items );
    if ( ! $item instanceof WC_Order_Item_Product ) {
        return [];
    }

    $map = [];

    $collect = static function( $node ) use ( &$collect, &$map ) {
        if ( is_array( $node ) ) {
            if ( isset( $node['id'] ) ) {
                $id = function_exists( 'teinvit_normalize_wapf_field_id' ) ? teinvit_normalize_wapf_field_id( $node['id'] ) : trim( (string) $node['id'] );
                $value = isset( $node['value'] ) ? $node['value'] : '';
                if ( $id !== '' ) {
                    $map[ $id ] = is_scalar( $value ) ? trim( (string) $value ) : trim( (string) wp_json_encode( $value ) );
                }
            }

            foreach ( $node as $key => $value ) {
                if ( is_string( $key ) && strpos( $key, 'field_' ) === 0 ) {
                    $id = function_exists( 'teinvit_normalize_wapf_field_id' ) ? teinvit_normalize_wapf_field_id( $key ) : trim( (string) $key );
                    if ( $id !== '' ) {
                        if ( is_array( $value ) ) {
                            $flat = array_values( array_filter( array_map( static function( $v ) {
                                return is_scalar( $v ) ? trim( (string) $v ) : '';
                            }, $value ) ) );
                            $map[ $id ] = implode( ', ', $flat );
                        } else {
                            $map[ $id ] = is_scalar( $value ) ? trim( (string) $value ) : trim( (string) wp_json_encode( $value ) );
                        }
                    }
                }

                if ( is_array( $value ) || is_object( $value ) ) {
                    $collect( $value );
                }
            }
            return;
        }

        if ( is_object( $node ) ) {
            $collect( json_decode( wp_json_encode( $node ), true ) );
        }
    };

    $raw_wapf = $item->get_meta( '_wapf_meta' );
    if ( is_string( $raw_wapf ) && $raw_wapf !== '' ) {
        $decoded = json_decode( $raw_wapf, true );
        if ( is_array( $decoded ) ) {
            $collect( $decoded );
        }
    } elseif ( is_array( $raw_wapf ) || is_object( $raw_wapf ) ) {
        $collect( $raw_wapf );
    }

    foreach ( $item->get_meta_data() as $meta ) {
        $key = isset( $meta->key ) ? (string) $meta->key : '';
        $value = isset( $meta->value ) ? $meta->value : '';
        if ( $key === '' ) {
            continue;
        }

        if ( strpos( $key, 'field_' ) === 0 || strpos( $key, 'wapf[field_' ) === 0 ) {
            $id = function_exists( 'teinvit_normalize_wapf_field_id' ) ? teinvit_normalize_wapf_field_id( $key ) : trim( (string) $key );
            if ( $id === '' ) {
                continue;
            }

            if ( is_array( $value ) ) {
                $flat = array_values( array_filter( array_map( static function( $v ) {
                    return is_scalar( $v ) ? trim( (string) $v ) : '';
                }, $value ) ) );
                $map[ $id ] = implode( ', ', $flat );
            } else {
                $map[ $id ] = is_scalar( $value ) ? trim( (string) $value ) : '';
            }
        }
    }

    return $map;
}

function teinvit_build_invitation_payload_from_order( $vertical_key, WC_Order $order, $token = '' ) {
    $vertical_key = function_exists( 'teinvit_normalize_vertical_key' ) ? teinvit_normalize_vertical_key( $vertical_key ) : 'wedding';

    if ( $vertical_key === 'wedding' ) {
        $wapf_map = TeInvit_Wedding_Preview_Renderer::get_order_wapf_field_map( $order );
        $defs = function_exists( 'teinvit_get_wapf_defs_for_product' ) ? teinvit_get_wapf_defs_for_product( teinvit_get_order_primary_product_id( $order ) ) : [];
        $built = function_exists( 'teinvit_build_invitation_from_wapf_map_canonical' ) ? teinvit_build_invitation_from_wapf_map_canonical( $wapf_map, $defs ) : [
            'invitation' => TeInvit_Wedding_Preview_Renderer::get_order_invitation_data( $order ),
            'wapf_map' => $wapf_map,
        ];

        return [
            'invitation' => isset( $built['invitation'] ) && is_array( $built['invitation'] ) ? $built['invitation'] : [],
            'wapf_fields' => isset( $built['wapf_map'] ) && is_array( $built['wapf_map'] ) ? $built['wapf_map'] : $wapf_map,
        ];
    }

    $runtime = function_exists( 'teinvit_get_vertical_module_runtime' ) ? teinvit_get_vertical_module_runtime( $vertical_key ) : [];
    $boundaries = isset( $runtime['boundaries'] ) && is_array( $runtime['boundaries'] ) ? $runtime['boundaries'] : [];
    $provider = isset( $boundaries['payload']['provider'] ) ? (string) $boundaries['payload']['provider'] : '';

    if ( $provider === '' || ! is_callable( $provider ) ) {
        return [ 'invitation' => [], 'wapf_fields' => [] ];
    }

    $result = call_user_func( $provider, [
        'order' => $order,
        'token' => sanitize_text_field( (string) $token ),
        'vertical' => $vertical_key,
    ] );

    if ( ! is_array( $result ) ) {
        return [ 'invitation' => [], 'wapf_fields' => [] ];
    }

    return [
        'invitation' => isset( $result['invitation'] ) && is_array( $result['invitation'] ) ? $result['invitation'] : [],
        'wapf_fields' => isset( $result['wapf_fields'] ) && is_array( $result['wapf_fields'] ) ? $result['wapf_fields'] : teinvit_extract_order_wapf_field_map( $order ),
    ];
}

function teinvit_render_invitation_html_for_vertical( $vertical_key, array $invitation, WC_Order $order, $render_context = 'preview' ) {
    $vertical_key = function_exists( 'teinvit_normalize_vertical_key' ) ? teinvit_normalize_vertical_key( $vertical_key ) : 'wedding';
    $GLOBALS['TEINVIT_RENDER_CONTEXT'] = $render_context === 'pdf' ? 'pdf' : 'preview';

    if ( $vertical_key === 'wedding' ) {
        return TeInvit_Wedding_Preview_Renderer::render_from_invitation_data( $invitation, $order );
    }

    $runtime = function_exists( 'teinvit_get_vertical_module_runtime' ) ? teinvit_get_vertical_module_runtime( $vertical_key ) : [];
    $boundaries = isset( $runtime['boundaries'] ) && is_array( $runtime['boundaries'] ) ? $runtime['boundaries'] : [];
    $provider = isset( $boundaries['renderer']['provider'] ) ? (string) $boundaries['renderer']['provider'] : '';

    if ( $provider === '' || ! is_callable( $provider ) ) {
        return '<p>Invitație indisponibilă.</p>';
    }

    $html = call_user_func( $provider, [
        'invitation' => $invitation,
        'order' => $order,
        'render_context' => $GLOBALS['TEINVIT_RENDER_CONTEXT'],
    ] );

    return is_string( $html ) && $html !== '' ? $html : '<p>Invitație indisponibilă.</p>';
}
