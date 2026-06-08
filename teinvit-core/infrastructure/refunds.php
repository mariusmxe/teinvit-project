<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function teinvit_refund_legacy_gift_allocation_hook_enabled() {
    return (bool) apply_filters( 'teinvit_refund_legacy_gift_allocation_hook_enabled', false );
}

function teinvit_refund_add_order_note_once( $order, $note, $key ) {
    if ( ! is_object( $order ) || ! method_exists( $order, 'add_order_note' ) || ! method_exists( $order, 'get_meta' ) ) {
        return false;
    }

    $note = trim( (string) $note );
    if ( $note === '' ) {
        return false;
    }

    $key = 'refund_' . md5( (string) $key );
    $stored = $order->get_meta( '_teinvit_refund_note_keys', true );
    if ( ! is_array( $stored ) ) {
        $stored = [];
    }
    if ( in_array( $key, $stored, true ) ) {
        return false;
    }

    $order->add_order_note( $note );
    $stored[] = $key;
    $order->update_meta_data( '_teinvit_refund_note_keys', array_values( array_unique( $stored ) ) );
    if ( method_exists( $order, 'save' ) ) {
        $order->save();
    }

    return true;
}

function teinvit_refund_get_original_order_item_id( $refund_item ) {
    if ( ! is_object( $refund_item ) || ! method_exists( $refund_item, 'get_meta' ) ) {
        return 0;
    }

    return max( 0, (int) $refund_item->get_meta( '_refunded_item_id', true ) );
}

function teinvit_refund_get_refund_line_items( $refund_id ) {
    $refund_id = max( 0, (int) $refund_id );
    if ( $refund_id <= 0 || ! function_exists( 'wc_get_order' ) ) {
        return [];
    }

    $refund = wc_get_order( $refund_id );
    if ( ! is_object( $refund ) || ! method_exists( $refund, 'get_items' ) ) {
        return [];
    }

    $items = [];
    foreach ( $refund->get_items( 'line_item' ) as $refund_item_id => $refund_item ) {
        $items[] = [
            'refund_id' => $refund_id,
            'refund_item_id' => max( 0, (int) $refund_item_id ),
            'order_item_id' => teinvit_refund_get_original_order_item_id( $refund_item ),
            'refunded_qty' => is_object( $refund_item ) && method_exists( $refund_item, 'get_quantity' ) ? abs( (float) $refund_item->get_quantity() ) : 0,
            'refunded_total' => is_object( $refund_item ) && method_exists( $refund_item, 'get_total' ) ? abs( (float) $refund_item->get_total() ) : 0,
        ];
    }

    return $items;
}

function teinvit_refund_compact_token_context( $context ) {
    if ( ! is_array( $context ) ) {
        return [];
    }

    return [
        'valid' => ! empty( $context['valid'] ) ? 1 : 0,
        'source' => sanitize_key( (string) ( $context['source'] ?? '' ) ),
        'legacy' => ! empty( $context['legacy'] ) ? 1 : 0,
        'token' => sanitize_text_field( (string) ( $context['token'] ?? '' ) ),
        'order_id' => max( 0, (int) ( $context['order_id'] ?? 0 ) ),
        'order_item_id' => max( 0, (int) ( $context['order_item_id'] ?? 0 ) ),
        'product_id' => max( 0, (int) ( $context['product_id'] ?? 0 ) ),
        'variation_id' => max( 0, (int) ( $context['variation_id'] ?? 0 ) ),
        'vertical' => sanitize_key( (string) ( $context['vertical'] ?? '' ) ),
        'package_type' => sanitize_key( (string) ( $context['package_type'] ?? '' ) ),
        'product_state' => sanitize_key( (string) ( $context['product_state'] ?? '' ) ),
        'status' => sanitize_key( (string) ( $context['status'] ?? '' ) ),
    ];
}

function teinvit_refund_order_item_meta_snapshot( $item ) {
    if ( ! is_object( $item ) || ! method_exists( $item, 'get_meta' ) ) {
        return [];
    }

    $keys = [
        '_teinvit_token_target',
        '_teinvit_addon_type',
        '_teinvit_target_vertical',
        '_teinvit_gift_slots_applied_per_unit',
        '_teinvit_gift_slots_applied_total',
    ];
    $meta = [];
    foreach ( $keys as $key ) {
        $value = $item->get_meta( $key, true );
        if ( $value !== '' && $value !== null ) {
            $meta[ $key ] = is_scalar( $value ) ? (string) $value : $value;
        }
    }

    return $meta;
}

function teinvit_refund_product_ids_for_item( $item ) {
    return [
        'product_id' => is_object( $item ) && method_exists( $item, 'get_product_id' ) ? max( 0, (int) $item->get_product_id() ) : 0,
        'variation_id' => is_object( $item ) && method_exists( $item, 'get_variation_id' ) ? max( 0, (int) $item->get_variation_id() ) : 0,
    ];
}

function teinvit_refund_classify_original_item( $order, $order_item_id ) {
    $order_item_id = max( 0, (int) $order_item_id );
    $item = is_object( $order ) && method_exists( $order, 'get_item' ) ? $order->get_item( $order_item_id ) : null;
    if ( ! is_object( $item ) ) {
        return [
            'type' => 'unmapped',
            'reason' => 'original_order_item_missing',
        ];
    }

    $ids = teinvit_refund_product_ids_for_item( $item );
    $meta = teinvit_refund_order_item_meta_snapshot( $item );
    $addon_type_meta = sanitize_key( (string) ( $meta['_teinvit_addon_type'] ?? '' ) );
    $target_vertical = sanitize_key( (string) ( $meta['_teinvit_target_vertical'] ?? '' ) );
    $addon_ledger = function_exists( 'teinvit_get_order_token_addon_ledger_by_item' )
        ? teinvit_get_order_token_addon_ledger_by_item( is_object( $order ) && method_exists( $order, 'get_id' ) ? (int) $order->get_id() : 0, $order_item_id, $addon_type_meta )
        : null;
    if ( ! $addon_ledger && function_exists( 'teinvit_get_order_token_addon_ledger_by_item' ) ) {
        $addon_ledger = teinvit_get_order_token_addon_ledger_by_item( is_object( $order ) && method_exists( $order, 'get_id' ) ? (int) $order->get_id() : 0, $order_item_id );
    }

    $addon_context = null;
    if ( function_exists( 'teinvit_addon_product_context' ) ) {
        $addon_context = teinvit_addon_product_context( $ids['product_id'], $ids['variation_id'], $target_vertical );
        if ( ! $addon_context ) {
            $addon_context = teinvit_addon_product_context( $ids['product_id'], $ids['variation_id'] );
        }
    }

    if ( is_array( $addon_ledger ) || $addon_type_meta !== '' || is_array( $addon_context ) ) {
        return [
            'type' => 'addon',
            'item' => $item,
            'product_id' => $ids['product_id'],
            'variation_id' => $ids['variation_id'],
            'meta' => $meta,
            'addon_type' => sanitize_key( (string) ( $addon_ledger['addon_type'] ?? ( $addon_type_meta !== '' ? $addon_type_meta : ( $addon_context['addon_type'] ?? 'unknown' ) ) ) ),
            'addon_context' => is_array( $addon_context ) ? $addon_context : [],
            'addon_ledger' => is_array( $addon_ledger ) ? $addon_ledger : [],
        ];
    }

    $invitation_context = function_exists( 'teinvit_order_invitation_item_context' )
        ? teinvit_order_invitation_item_context( $order, $order_item_id, $item )
        : null;

    if ( is_array( $invitation_context ) ) {
        return [
            'type' => 'invitation',
            'item' => $item,
            'product_id' => $ids['product_id'],
            'variation_id' => $ids['variation_id'],
            'meta' => $meta,
            'invitation_context' => $invitation_context,
        ];
    }

    return [
        'type' => 'non_teinvit',
        'item' => $item,
        'product_id' => $ids['product_id'],
        'variation_id' => $ids['variation_id'],
        'meta' => $meta,
    ];
}

function teinvit_refund_legacy_single_product_token( $order, $order_item_id ) {
    $order_item_id = max( 0, (int) $order_item_id );
    if ( ! is_object( $order ) || ! method_exists( $order, 'get_items' ) || ! method_exists( $order, 'get_id' ) ) {
        return [
            'mapped' => false,
            'reason' => 'legacy_order_missing',
        ];
    }

    $order_id = (int) $order->get_id();
    $existing_rows = function_exists( 'teinvit_get_order_tokens_for_order' ) ? teinvit_get_order_tokens_for_order( $order_id ) : [];
    if ( ! empty( $existing_rows ) ) {
        return [
            'mapped' => false,
            'reason' => 'order_tokens_exist_but_item_missing',
        ];
    }

    $items = $order->get_items( 'line_item' );
    if ( count( $items ) !== 1 || ! isset( $items[ $order_item_id ] ) ) {
        return [
            'mapped' => false,
            'reason' => 'legacy_not_single_product_order',
        ];
    }

    $raw_token = $order->get_meta( '_teinvit_token', true );
    if ( $raw_token === '' && function_exists( 'get_post_meta' ) ) {
        $raw_token = get_post_meta( $order_id, '_teinvit_token', true );
    }
    if ( is_array( $raw_token ) ) {
        return [
            'mapped' => false,
            'reason' => 'legacy_multiple_tokens',
        ];
    }

    $token = sanitize_text_field( (string) $raw_token );
    if ( $token === '' ) {
        return [
            'mapped' => false,
            'reason' => 'legacy_token_missing',
        ];
    }

    $parts = preg_split( '/[\s,;]+/', $token, -1, PREG_SPLIT_NO_EMPTY );
    if ( is_array( $parts ) && count( $parts ) !== 1 ) {
        return [
            'mapped' => false,
            'reason' => 'legacy_multiple_tokens',
        ];
    }

    return [
        'mapped' => true,
        'token' => $token,
        'source' => 'legacy_single_product',
        'legacy' => true,
    ];
}

function teinvit_refund_map_invitation_item_to_token( $order, $order_item_id ) {
    $order_id = is_object( $order ) && method_exists( $order, 'get_id' ) ? (int) $order->get_id() : 0;
    $order_item_id = max( 0, (int) $order_item_id );

    $row = function_exists( 'teinvit_get_order_token_row_by_unit' )
        ? teinvit_get_order_token_row_by_unit( $order_id, $order_item_id, 1 )
        : null;
    if ( is_array( $row ) && ! empty( $row['token'] ) ) {
        return [
            'mapped' => true,
            'token' => sanitize_text_field( (string) $row['token'] ),
            'source' => 'order_tokens',
            'legacy' => false,
            'order_token_row' => $row,
        ];
    }

    return teinvit_refund_legacy_single_product_token( $order, $order_item_id );
}

function teinvit_refund_addon_effect_type( $addon_type ) {
    $addon_type = sanitize_key( (string) $addon_type );
    if ( $addon_type === 'premium_upgrade' ) {
        return 'premium_upgrade_refund';
    }
    if ( $addon_type === 'extra_edits' ) {
        return 'extra_edits_refund';
    }
    if ( $addon_type === 'extra_gifts' ) {
        return 'extra_gifts_refund';
    }

    return 'unknown_addon_refund';
}

function teinvit_refund_premium_upgrade_markers_for_token( $target_token ) {
    $target_token = sanitize_text_field( (string) $target_token );
    if ( $target_token === '' || ! function_exists( 'teinvit_get_invitation' ) ) {
        return [];
    }

    $invitation = teinvit_get_invitation( $target_token );
    $config = is_array( $invitation['config'] ?? null ) ? $invitation['config'] : [];
    if ( empty( $config ) ) {
        return [];
    }

    return [
        'premium_upgrade_active' => ! empty( $config['premium_upgrade_active'] ) ? 1 : 0,
        'premium_upgrade_last_order_id' => max( 0, (int) ( $config['premium_upgrade_last_order_id'] ?? 0 ) ),
        'premium_upgrade_last_order_item_id' => max( 0, (int) ( $config['premium_upgrade_last_order_item_id'] ?? 0 ) ),
        'default_included_edits_applied' => ! empty( $config['default_included_edits_applied'] ) ? 1 : 0,
        'default_included_edits_applied_value' => max( 0, (int) ( $config['default_included_edits_applied_value'] ?? 0 ) ),
        'default_included_edits_applied_source' => sanitize_key( (string) ( $config['default_included_edits_applied_source'] ?? '' ) ),
        'default_included_edits_applied_order_id' => max( 0, (int) ( $config['default_included_edits_applied_order_id'] ?? 0 ) ),
        'edits_free_remaining' => max( 0, (int) ( $config['edits_free_remaining'] ?? 0 ) ),
        'edits_admin_remaining' => max( 0, (int) ( $config['edits_admin_remaining'] ?? 0 ) ),
        'edits_paid_remaining' => max( 0, (int) ( $config['edits_paid_remaining'] ?? 0 ) ),
    ];
}

function teinvit_refund_extra_gifts_grant_context( $order_id, $order_item_id, $item, $target_token ) {
    $per_unit = is_object( $item ) && method_exists( $item, 'get_meta' ) ? max( 0, (int) $item->get_meta( '_teinvit_gift_slots_applied_per_unit', true ) ) : 0;
    $total = is_object( $item ) && method_exists( $item, 'get_meta' ) ? max( 0, (int) $item->get_meta( '_teinvit_gift_slots_applied_total', true ) ) : 0;
    $source = $total > 0 ? 'order_item_meta' : '';
    $allocation_key = 'addon:' . (int) $order_id . ':' . (int) $order_item_id;
    $allocation = [];

    if ( $total <= 0 && $target_token !== '' && function_exists( 'teinvit_get_invitation' ) ) {
        $invitation = teinvit_get_invitation( $target_token );
        $config = is_array( $invitation['config'] ?? null ) ? $invitation['config'] : [];
        $allocations = is_array( $config['gifts_allocations'] ?? null ) ? $config['gifts_allocations'] : [];
        foreach ( $allocations as $candidate ) {
            if ( is_array( $candidate ) && (string) ( $candidate['allocation_key'] ?? '' ) === $allocation_key ) {
                $allocation = $candidate;
                $per_unit = max( 0, (int) ( $candidate['slots_per_unit'] ?? 0 ) );
                $total = max( 0, (int) ( $candidate['slots_total'] ?? 0 ) );
                $source = $total > 0 ? 'token_allocation' : '';
                break;
            }
        }
    }

    return [
        'safe' => $total > 0,
        'source' => $source,
        'allocation_key' => $allocation_key,
        'slots_per_unit' => $per_unit,
        'slots_total' => $total,
        'allocation' => $allocation,
    ];
}

function teinvit_refund_map_addon_item_to_target_token( $order, $order_item_id, array $classification, array $refund_context ) {
    $item = $classification['item'] ?? null;
    $meta = is_array( $classification['meta'] ?? null ) ? $classification['meta'] : [];
    $addon_ledger = is_array( $classification['addon_ledger'] ?? null ) ? $classification['addon_ledger'] : [];
    $addon_context = is_array( $classification['addon_context'] ?? null ) ? $classification['addon_context'] : [];
    $addon_type = sanitize_key( (string) ( $addon_ledger['addon_type'] ?? ( $classification['addon_type'] ?? ( $meta['_teinvit_addon_type'] ?? ( $addon_context['addon_type'] ?? 'unknown' ) ) ) ) );
    $target_token = sanitize_text_field( (string) ( $addon_ledger['target_token'] ?? ( $meta['_teinvit_token_target'] ?? '' ) ) );
    $target_vertical = sanitize_key( (string) ( $addon_ledger['vertical'] ?? ( $meta['_teinvit_target_vertical'] ?? ( $addon_context['vertical'] ?? '' ) ) ) );

    if ( $target_token === '' ) {
        return [
            'mapped' => false,
            'reason' => 'missing_target_token',
            'addon_type' => $addon_type !== '' ? $addon_type : 'unknown',
            'effect_type' => teinvit_refund_addon_effect_type( $addon_type ),
        ];
    }

    $target_context = [];
    if ( function_exists( 'teinvit_resolve_token_context' ) ) {
        $target_context = teinvit_resolve_token_context( $target_token );
        if ( ! is_array( $target_context ) || empty( $target_context['valid'] ) ) {
            return [
                'mapped' => false,
                'reason' => 'target_token_invalid',
                'target_token' => $target_token,
                'addon_type' => $addon_type !== '' ? $addon_type : 'unknown',
                'effect_type' => teinvit_refund_addon_effect_type( $addon_type ),
            ];
        }
        if ( $target_vertical === '' ) {
            $target_vertical = sanitize_key( (string) ( $target_context['vertical'] ?? '' ) );
        }
    }

    $grant_context = [
        'safe' => true,
        'source' => 'refund_quantity',
        'qty' => max( 0, (float) ( $refund_context['refunded_qty'] ?? 0 ) ),
    ];
    $granted_qty = max( 0, (float) ( $refund_context['refunded_qty'] ?? 0 ) );
    if ( $addon_type === 'premium_upgrade' ) {
        $markers = teinvit_refund_premium_upgrade_markers_for_token( $target_token );
        $grant_context = [
            'safe' => ! empty( $markers ) && (string) ( $markers['default_included_edits_applied_source'] ?? '' ) === 'woo_upgrade',
            'source' => 'premium_default_included_edits_markers',
            'premium_upgrade_markers' => $markers,
        ];
        $granted_qty = ! empty( $grant_context['safe'] ) ? max( 0, (float) ( $markers['default_included_edits_applied_value'] ?? 0 ) ) : 0;
    } elseif ( $addon_type === 'extra_gifts' ) {
        $grant_context = teinvit_refund_extra_gifts_grant_context(
            is_object( $order ) && method_exists( $order, 'get_id' ) ? (int) $order->get_id() : 0,
            $order_item_id,
            $item,
            $target_token
        );
        $refunded_qty = max( 0, (float) ( $refund_context['refunded_qty'] ?? 0 ) );
        $granted_qty = ! empty( $grant_context['safe'] )
            ? min( max( 0, (float) ( $grant_context['slots_total'] ?? 0 ) ), $refunded_qty * max( 0, (float) ( $grant_context['slots_per_unit'] ?? 0 ) ) )
            : 0;
    }

    return [
        'mapped' => true,
        'target_token' => $target_token,
        'addon_type' => $addon_type !== '' ? $addon_type : 'unknown',
        'effect_type' => teinvit_refund_addon_effect_type( $addon_type ),
        'target_vertical' => $target_vertical,
        'addon_ledger' => $addon_ledger,
        'addon_context' => $addon_context,
        'target_context' => teinvit_refund_compact_token_context( $target_context ),
        'grant_context' => $grant_context,
        'granted_qty' => $granted_qty,
    ];
}

function teinvit_refund_ledger_note( array $payload, $status, $error_message = '' ) {
    $effect_type = sanitize_key( (string) ( $payload['effect_type'] ?? 'unknown' ) );
    $token = sanitize_text_field( (string) ( $payload['token'] ?? '' ) );
    $target_token = sanitize_text_field( (string) ( $payload['target_token'] ?? '' ) );
    $subject = $target_token !== '' ? 'target_token=' . $target_token : ( $token !== '' ? 'token=' . $token : 'token=n/a' );

    if ( $status === 'skipped' ) {
        return sprintf(
            '[TeInvit Refund Etapa 1] Skipped mapping: refund #%d item #%d -> order item #%d product #%d effect=%s %s reason=%s. Nu s-au aplicat efecte comerciale.',
            (int) ( $payload['refund_id'] ?? 0 ),
            (int) ( $payload['refund_item_id'] ?? 0 ),
            (int) ( $payload['order_item_id'] ?? 0 ),
            (int) ( $payload['product_id'] ?? 0 ),
            $effect_type,
            $subject,
            sanitize_key( (string) $error_message )
        );
    }

    return sprintf(
        '[TeInvit Refund Etapa 1] Dry-run mapping: refund #%d item #%d -> order item #%d product #%d effect=%s %s status=%s. Nu s-au aplicat efecte comerciale.',
        (int) ( $payload['refund_id'] ?? 0 ),
        (int) ( $payload['refund_item_id'] ?? 0 ),
        (int) ( $payload['order_item_id'] ?? 0 ),
        (int) ( $payload['product_id'] ?? 0 ),
        $effect_type,
        $subject,
        sanitize_key( (string) $status )
    );
}

function teinvit_refund_record_ledger( $order, array $payload, $final_status, $error_message, array $debug_context, array $previous_state = [], array $after_state = [], $note_level = 'important' ) {
    if ( ! function_exists( 'teinvit_upsert_order_token_refund_ledger' ) || ! function_exists( 'teinvit_order_token_refund_ledger_key' ) ) {
        return [
            'ok' => false,
            'already_processed' => false,
            'ledger_id' => 0,
            'error' => 'ledger_helpers_missing',
        ];
    }

    $payload['status'] = 'pending';
    $payload['error_message'] = $error_message;
    $payload['debug_context'] = $debug_context;
    $payload['previous_state'] = $previous_state;
    $payload['after_state'] = $after_state;
    $payload['ledger_key'] = teinvit_order_token_refund_ledger_key( $payload );

    $existing = function_exists( 'teinvit_get_order_token_refund_ledger_by_key' )
        ? teinvit_get_order_token_refund_ledger_by_key( $payload['ledger_key'] )
        : null;
    if ( is_array( $existing ) && ! empty( $existing['id'] ) && (string) ( $existing['status'] ?? '' ) !== 'pending' ) {
        return [
            'ok' => true,
            'already_processed' => true,
            'ledger_id' => (int) $existing['id'],
            'status' => sanitize_key( (string) ( $existing['status'] ?? '' ) ),
        ];
    }

    $ledger_id = teinvit_upsert_order_token_refund_ledger( $payload );
    if ( ! $ledger_id ) {
        return [
            'ok' => false,
            'already_processed' => false,
            'ledger_id' => 0,
            'error' => 'ledger_insert_failed',
        ];
    }

    if ( function_exists( 'teinvit_update_order_token_refund_ledger_status' ) ) {
        teinvit_update_order_token_refund_ledger_status( $ledger_id, $final_status, [
            'error_message' => $error_message,
            'debug_context' => $debug_context,
            'previous_state' => $previous_state,
            'after_state' => $after_state,
            'refunded_qty' => $payload['refunded_qty'] ?? 0,
            'refunded_total' => $payload['refunded_total'] ?? 0,
            'granted_qty' => $payload['granted_qty'] ?? 0,
            'reversed_qty' => $payload['reversed_qty'] ?? 0,
        ] );
    }

    if ( $note_level !== 'none' ) {
        teinvit_refund_add_order_note_once(
            $order,
            teinvit_refund_ledger_note( $payload, $final_status, $error_message ),
            'stage1|' . $payload['ledger_key'] . '|' . $final_status
        );
    }

    return [
        'ok' => true,
        'already_processed' => false,
        'ledger_id' => (int) $ledger_id,
        'status' => $final_status,
    ];
}

function teinvit_refund_order_token_state_snapshot( array $row ) {
    return [
        'order_token_id' => max( 0, (int) ( $row['id'] ?? 0 ) ),
        'token' => sanitize_text_field( (string) ( $row['token'] ?? '' ) ),
        'order_id' => max( 0, (int) ( $row['order_id'] ?? 0 ) ),
        'order_item_id' => max( 0, (int) ( $row['order_item_id'] ?? 0 ) ),
        'quantity_index' => max( 1, (int) ( $row['quantity_index'] ?? 1 ) ),
        'order_tokens_status' => sanitize_key( (string) ( $row['status'] ?? '' ) ),
        'pdf_status' => sanitize_key( (string) ( $row['pdf_status'] ?? '' ) ),
        'vertical' => sanitize_key( (string) ( $row['vertical'] ?? '' ) ),
        'package_type' => sanitize_key( (string) ( $row['package_type'] ?? '' ) ),
        'legacy' => ! empty( $row['legacy'] ) ? 1 : 0,
    ];
}

function teinvit_refund_invitation_ledger_effect_applied( $ledger ) {
    if ( ! is_array( $ledger ) ) {
        return false;
    }

    $after_state = is_array( $ledger['after_state'] ?? null ) ? $ledger['after_state'] : [];
    return sanitize_key( (string) ( $after_state['order_tokens_status'] ?? '' ) ) === 'refunded';
}

function teinvit_refund_get_or_create_ledger_id( array $payload, array $debug_context = [] ) {
    if ( ! function_exists( 'teinvit_order_token_refund_ledger_key' ) || ! function_exists( 'teinvit_get_order_token_refund_ledger_by_key' ) || ! function_exists( 'teinvit_upsert_order_token_refund_ledger' ) ) {
        return [ 0, null, '' ];
    }

    $payload['ledger_key'] = teinvit_order_token_refund_ledger_key( $payload );
    $existing = teinvit_get_order_token_refund_ledger_by_key( $payload['ledger_key'] );
    if ( is_array( $existing ) && ! empty( $existing['id'] ) ) {
        return [ (int) $existing['id'], $existing, $payload['ledger_key'] ];
    }

    $payload['status'] = 'pending';
    $payload['debug_context'] = $debug_context;
    $ledger_id = teinvit_upsert_order_token_refund_ledger( $payload );
    if ( ! $ledger_id ) {
        return [ 0, null, $payload['ledger_key'] ];
    }

    $created = teinvit_get_order_token_refund_ledger_by_key( $payload['ledger_key'] );
    return [ (int) $ledger_id, $created, $payload['ledger_key'] ];
}

function teinvit_refund_stage2_note( array $payload, array $previous_state, array $after_state, $mode, $reason = '' ) {
    $mode = sanitize_key( (string) $mode );
    if ( $mode === 'skipped' ) {
        return sprintf(
            '[TeInvit Refund Etapa 2] Refund skipped: refund #%d, item #%d, order item #%d, token %s, motiv %s.',
            (int) ( $payload['refund_id'] ?? 0 ),
            (int) ( $payload['refund_item_id'] ?? 0 ),
            (int) ( $payload['order_item_id'] ?? 0 ),
            sanitize_text_field( (string) ( $payload['token'] ?? '' ) ),
            sanitize_key( (string) $reason )
        );
    }

    if ( $mode === 'already_applied' ) {
        return sprintf(
            '[TeInvit Refund Etapa 2] Token refund already applied: refund #%d, item #%d, order item #%d, token %s, status %s.',
            (int) ( $payload['refund_id'] ?? 0 ),
            (int) ( $payload['refund_item_id'] ?? 0 ),
            (int) ( $payload['order_item_id'] ?? 0 ),
            sanitize_text_field( (string) ( $payload['token'] ?? '' ) ),
            sanitize_key( (string) ( $after_state['order_tokens_status'] ?? '' ) )
        );
    }

    return sprintf(
        '[TeInvit Refund Etapa 2] Token refund applied: refund #%d, item #%d, order item #%d, token %s, status %s -> %s.',
        (int) ( $payload['refund_id'] ?? 0 ),
        (int) ( $payload['refund_item_id'] ?? 0 ),
        (int) ( $payload['order_item_id'] ?? 0 ),
        sanitize_text_field( (string) ( $payload['token'] ?? '' ) ),
        sanitize_key( (string) ( $previous_state['order_tokens_status'] ?? '' ) ),
        sanitize_key( (string) ( $after_state['order_tokens_status'] ?? '' ) )
    );
}

function teinvit_refund_process_invitation_item_stage2( $order, array $payload, array $mapping, array $debug_context ) {
    $debug_context['phase'] = 'stage2_invitation_refund';
    $debug_context['dry_run'] = false;
    $debug_context['commercial_effect'] = 'order_tokens_status_refunded';

    list( $ledger_id, $existing_ledger, $ledger_key ) = teinvit_refund_get_or_create_ledger_id( $payload, $debug_context );
    if ( $ledger_id <= 0 ) {
        teinvit_refund_add_order_note_once(
            $order,
            teinvit_refund_stage2_note( $payload, [], [], 'skipped', 'ledger_unavailable' ),
            'stage2|ledger_unavailable|' . md5( wp_json_encode( $payload ) )
        );
        return [
            'ok' => false,
            'status' => 'failed',
            'reason' => 'ledger_unavailable',
        ];
    }

    $order_token_row = is_array( $mapping['order_token_row'] ?? null ) ? $mapping['order_token_row'] : [];
    if ( empty( $order_token_row ) || ! empty( $order_token_row['legacy'] ) || empty( $order_token_row['id'] ) ) {
        $reason = sanitize_key( (string) ( $mapping['reason'] ?? '' ) );
        if ( $reason === '' ) {
            $reason = ! empty( $mapping['legacy'] ) ? 'legacy_no_order_tokens_row' : 'order_tokens_row_missing';
        }
        $debug_context['mapping'] = $mapping;
        if ( function_exists( 'teinvit_update_order_token_refund_ledger_status' ) ) {
            teinvit_update_order_token_refund_ledger_status( $ledger_id, 'skipped', [
                'error_message' => $reason,
                'debug_context' => $debug_context,
                'previous_state' => [],
                'after_state' => [],
                'refunded_qty' => $payload['refunded_qty'] ?? 0,
                'refunded_total' => $payload['refunded_total'] ?? 0,
            ] );
        }
        teinvit_refund_add_order_note_once(
            $order,
            teinvit_refund_stage2_note( $payload, [], [], 'skipped', $reason ),
            'stage2|' . $ledger_key . '|skipped|' . $reason
        );
        return [
            'ok' => true,
            'status' => 'skipped',
            'reason' => $reason,
        ];
    }

    $current_row = function_exists( 'teinvit_get_order_token_row' )
        ? teinvit_get_order_token_row( (string) $order_token_row['token'] )
        : $order_token_row;
    if ( ! is_array( $current_row ) ) {
        $current_row = $order_token_row;
    }

    $previous_state = teinvit_refund_order_token_state_snapshot( $current_row );
    if ( teinvit_refund_invitation_ledger_effect_applied( $existing_ledger ) && sanitize_key( (string) ( $current_row['status'] ?? '' ) ) === 'refunded' ) {
        return [
            'ok' => true,
            'status' => 'processed',
            'already_processed' => true,
        ];
    }

    $updated = true;
    if ( sanitize_key( (string) ( $current_row['status'] ?? '' ) ) !== 'refunded' ) {
        $updated = function_exists( 'teinvit_update_order_token_row' )
            ? teinvit_update_order_token_row( (int) $current_row['id'], [ 'status' => 'refunded' ] )
            : false;
    }

    if ( ! $updated ) {
        $debug_context['mapping'] = $mapping;
        $debug_context['previous_state'] = $previous_state;
        if ( function_exists( 'teinvit_update_order_token_refund_ledger_status' ) ) {
            teinvit_update_order_token_refund_ledger_status( $ledger_id, 'failed', [
                'error_message' => 'order_token_status_update_failed',
                'debug_context' => $debug_context,
                'previous_state' => $previous_state,
                'after_state' => [],
                'refunded_qty' => $payload['refunded_qty'] ?? 0,
                'refunded_total' => $payload['refunded_total'] ?? 0,
            ] );
        }
        teinvit_refund_add_order_note_once(
            $order,
            teinvit_refund_stage2_note( $payload, $previous_state, [], 'skipped', 'order_token_status_update_failed' ),
            'stage2|' . $ledger_key . '|failed'
        );
        return [
            'ok' => false,
            'status' => 'failed',
            'reason' => 'order_token_status_update_failed',
        ];
    }

    $after_row = function_exists( 'teinvit_get_order_token_row' )
        ? teinvit_get_order_token_row( (string) $order_token_row['token'] )
        : null;
    if ( ! is_array( $after_row ) ) {
        $after_row = array_merge( $current_row, [ 'status' => 'refunded' ] );
    }
    $after_state = teinvit_refund_order_token_state_snapshot( $after_row );
    $debug_context['mapping'] = $mapping;
    $debug_context['previous_state'] = $previous_state;
    $debug_context['after_state'] = $after_state;
    $debug_context['already_refunded_before_stage2'] = $previous_state['order_tokens_status'] === 'refunded' ? 1 : 0;

    if ( function_exists( 'teinvit_update_order_token_refund_ledger_status' ) ) {
        teinvit_update_order_token_refund_ledger_status( $ledger_id, 'processed', [
            'error_message' => '',
            'debug_context' => $debug_context,
            'previous_state' => $previous_state,
            'after_state' => $after_state,
            'refunded_qty' => $payload['refunded_qty'] ?? 0,
            'refunded_total' => $payload['refunded_total'] ?? 0,
            'reversed_qty' => 1,
        ] );
    }

    $note_mode = $previous_state['order_tokens_status'] === 'refunded' ? 'already_applied' : 'applied';
    teinvit_refund_add_order_note_once(
        $order,
        teinvit_refund_stage2_note( $payload, $previous_state, $after_state, $note_mode ),
        'stage2|' . $ledger_key . '|' . $note_mode
    );

    return [
        'ok' => true,
        'status' => 'processed',
        'already_processed' => $note_mode === 'already_applied',
    ];
}

function teinvit_refund_addon_reversal_effect_applied( $ledger ) {
    if ( ! is_array( $ledger ) ) {
        return false;
    }

    $after_state = is_array( $ledger['after_state'] ?? null ) ? $ledger['after_state'] : [];
    return ! empty( $after_state['addon_refund_reversal_processed'] );
}

function teinvit_refund_addon_ledger_snapshot( $ledger ) {
    if ( ! is_array( $ledger ) ) {
        return [];
    }

    return [
        'id' => max( 0, (int) ( $ledger['id'] ?? 0 ) ),
        'target_token' => sanitize_text_field( (string) ( $ledger['target_token'] ?? '' ) ),
        'order_id' => max( 0, (int) ( $ledger['order_id'] ?? 0 ) ),
        'order_item_id' => max( 0, (int) ( $ledger['order_item_id'] ?? 0 ) ),
        'product_id' => max( 0, (int) ( $ledger['product_id'] ?? 0 ) ),
        'variation_id' => max( 0, (int) ( $ledger['variation_id'] ?? 0 ) ),
        'addon_type' => sanitize_key( (string) ( $ledger['addon_type'] ?? '' ) ),
        'vertical' => sanitize_key( (string) ( $ledger['vertical'] ?? '' ) ),
        'status' => sanitize_key( (string) ( $ledger['status'] ?? '' ) ),
        'capability_changed' => sanitize_key( (string) ( $ledger['capability_changed'] ?? '' ) ),
        'error_message' => sanitize_textarea_field( (string) ( $ledger['error_message'] ?? '' ) ),
        'debug_context' => is_array( $ledger['debug_context'] ?? null ) ? $ledger['debug_context'] : [],
    ];
}

function teinvit_refund_load_invitation_config_for_token( $token, $vertical = '' ) {
    $token = sanitize_text_field( (string) $token );
    $vertical = sanitize_key( (string) $vertical );
    if ( $token === '' ) {
        return [
            'ok' => false,
            'reason' => 'missing_token',
            'vertical' => $vertical,
            'config' => [],
        ];
    }

    if ( $vertical === '' && function_exists( 'teinvit_resolve_token_context' ) ) {
        $context = teinvit_resolve_token_context( $token );
        if ( is_array( $context ) ) {
            $vertical = sanitize_key( (string) ( $context['vertical'] ?? '' ) );
        }
    }

    $invitation = null;
    if ( function_exists( 'teinvit_get_invitation_record' ) ) {
        $invitation = teinvit_get_invitation_record( $token, $vertical );
    }
    if ( ! is_array( $invitation ) && function_exists( 'teinvit_get_invitation' ) ) {
        $invitation = teinvit_get_invitation( $token );
    }
    if ( ! is_array( $invitation ) ) {
        return [
            'ok' => false,
            'reason' => 'invitation_config_missing',
            'vertical' => $vertical,
            'config' => [],
        ];
    }

    return [
        'ok' => true,
        'reason' => '',
        'vertical' => $vertical,
        'invitation' => $invitation,
        'config' => is_array( $invitation['config'] ?? null ) ? $invitation['config'] : [],
    ];
}

function teinvit_refund_save_invitation_config_for_token( $token, array $config, $vertical = '' ) {
    $token = sanitize_text_field( (string) $token );
    $vertical = sanitize_key( (string) $vertical );
    if ( $token === '' ) {
        return false;
    }

    if ( function_exists( 'teinvit_save_invitation_config_for_token' ) ) {
        return teinvit_save_invitation_config_for_token( $token, [ 'config' => $config ], $vertical ) !== false;
    }
    if ( function_exists( 'teinvit_save_invitation_config' ) ) {
        return teinvit_save_invitation_config( $token, [ 'config' => $config ] ) !== false;
    }

    return false;
}

function teinvit_refund_gift_summary_for_token( $token, array $config ) {
    if ( function_exists( 'teinvit_token_grants_gift_summary_for_token' ) ) {
        return teinvit_token_grants_gift_summary_for_token( $token, $config );
    }
    $vertical = function_exists( 'teinvit_resolve_token_vertical' ) ? sanitize_key( (string) teinvit_resolve_token_vertical( $token ) ) : '';
    if ( $vertical === 'birthday' && function_exists( 'teinvit_birthday_build_gifts_summary_for_token' ) ) {
        return teinvit_birthday_build_gifts_summary_for_token( $token, $config );
    }
    if ( $vertical === 'baptism' && function_exists( 'teinvit_baptism_build_gifts_summary_for_token' ) ) {
        return teinvit_baptism_build_gifts_summary_for_token( $token, $config );
    }
    if ( function_exists( 'teinvit_build_gifts_summary_for_token' ) ) {
        return teinvit_build_gifts_summary_for_token( $token, $config );
    }

    return [
        'base_slots' => 0,
        'addon_slots' => 0,
        'admin_slots' => 0,
        'total_slots' => 0,
        'used_slots' => 0,
        'available_slots' => 0,
        'allocations' => [],
    ];
}

function teinvit_refund_sync_gift_summary_to_config( $token, array $config ) {
    $summary = teinvit_refund_gift_summary_for_token( $token, $config );
    $config['gifts_allocations'] = is_array( $summary['allocations'] ?? null ) ? $summary['allocations'] : [];
    $config['gifts_base_slots_applied'] = max( 0, (int) ( $summary['base_slots'] ?? 0 ) );
    $config['gifts_extra_slots'] = max( 0, (int) ( $summary['addon_slots'] ?? 0 ) );
    $config['gifts_admin_slots'] = max( 0, (int) ( $summary['admin_slots'] ?? 0 ) );
    $config['gifts_total_slots_applied'] = max( 0, (int) ( $summary['total_slots'] ?? 0 ) );
    $config['gifts_slots_used'] = max( 0, (int) ( $summary['used_slots'] ?? 0 ) );
    $config['gifts_slots_available'] = max( 0, (int) ( $summary['available_slots'] ?? 0 ) );

    return [ $config, $summary ];
}

function teinvit_refund_sync_legacy_gift_capacity_from_summary( $token, array $summary ) {
    if ( function_exists( 'teinvit_get_settings' ) && function_exists( 'teinvit_update_settings' ) ) {
        $settings = teinvit_get_settings( $token );
        if ( is_array( $settings ) ) {
            teinvit_update_settings( $token, [
                'gifts_paid_capacity' => max( 0, (int) ( $summary['addon_slots'] ?? 0 ) ),
            ] );
        }
    }
}

function teinvit_refund_allocation_snapshot( $allocation ) {
    if ( ! is_array( $allocation ) ) {
        return [];
    }

    return [
        'allocation_key' => sanitize_text_field( (string) ( $allocation['allocation_key'] ?? '' ) ),
        'kind' => sanitize_key( (string) ( $allocation['kind'] ?? '' ) ),
        'order_id' => max( 0, (int) ( $allocation['order_id'] ?? 0 ) ),
        'item_id' => max( 0, (int) ( $allocation['item_id'] ?? 0 ) ),
        'product_id' => max( 0, (int) ( $allocation['product_id'] ?? 0 ) ),
        'qty' => max( 0, (int) ( $allocation['qty'] ?? 0 ) ),
        'slots_per_unit' => max( 0, (int) ( $allocation['slots_per_unit'] ?? 0 ) ),
        'slots_total' => max( 0, (int) ( $allocation['slots_total'] ?? 0 ) ),
        'slots_remaining' => max( 0, (int) ( $allocation['slots_remaining'] ?? 0 ) ),
        'slots_reversed' => max( 0, (int) ( $allocation['slots_reversed'] ?? 0 ) ),
        'status' => sanitize_key( (string) ( $allocation['status'] ?? '' ) ),
    ];
}

function teinvit_refund_config_state_snapshot( $token, array $config ) {
    $summary = teinvit_refund_gift_summary_for_token( $token, $config );
    $allocations = [];
    foreach ( (array) ( $summary['allocations'] ?? [] ) as $allocation ) {
        $snapshot = teinvit_refund_allocation_snapshot( $allocation );
        if ( ! empty( $snapshot ) ) {
            $allocations[] = $snapshot;
        }
    }

    return [
        'target_token' => sanitize_text_field( (string) $token ),
        'premium_upgrade_active' => ! empty( $config['premium_upgrade_active'] ) ? 1 : 0,
        'premium_upgrade_last_order_id' => max( 0, (int) ( $config['premium_upgrade_last_order_id'] ?? 0 ) ),
        'premium_upgrade_last_order_item_id' => max( 0, (int) ( $config['premium_upgrade_last_order_item_id'] ?? 0 ) ),
        'premium_admin_grant_active' => ! empty( $config['premium_admin_grant_active'] ) ? 1 : 0,
        'default_included_edits_applied' => ! empty( $config['default_included_edits_applied'] ) ? 1 : 0,
        'default_included_edits_applied_value' => max( 0, (int) ( $config['default_included_edits_applied_value'] ?? 0 ) ),
        'default_included_edits_applied_source' => sanitize_key( (string) ( $config['default_included_edits_applied_source'] ?? '' ) ),
        'default_included_edits_applied_order_id' => max( 0, (int) ( $config['default_included_edits_applied_order_id'] ?? 0 ) ),
        'edits_free_remaining' => max( 0, (int) ( $config['edits_free_remaining'] ?? 0 ) ),
        'edits_admin_remaining' => max( 0, (int) ( $config['edits_admin_remaining'] ?? 0 ) ),
        'edits_paid_remaining' => max( 0, (int) ( $config['edits_paid_remaining'] ?? 0 ) ),
        'gifts_base_slots_applied' => max( 0, (int) ( $config['gifts_base_slots_applied'] ?? 0 ) ),
        'gifts_extra_slots' => max( 0, (int) ( $config['gifts_extra_slots'] ?? 0 ) ),
        'gifts_admin_slots' => max( 0, (int) ( $config['gifts_admin_slots'] ?? 0 ) ),
        'gifts_total_slots_applied' => max( 0, (int) ( $config['gifts_total_slots_applied'] ?? 0 ) ),
        'gifts_slots_used' => max( 0, (int) ( $config['gifts_slots_used'] ?? 0 ) ),
        'gifts_slots_available' => max( 0, (int) ( $config['gifts_slots_available'] ?? 0 ) ),
        'gifts_summary' => [
            'base_slots' => max( 0, (int) ( $summary['base_slots'] ?? 0 ) ),
            'addon_slots' => max( 0, (int) ( $summary['addon_slots'] ?? 0 ) ),
            'admin_slots' => max( 0, (int) ( $summary['admin_slots'] ?? 0 ) ),
            'total_slots' => max( 0, (int) ( $summary['total_slots'] ?? 0 ) ),
            'used_slots' => max( 0, (int) ( $summary['used_slots'] ?? 0 ) ),
            'available_slots' => max( 0, (int) ( $summary['available_slots'] ?? 0 ) ),
        ],
        'gifts_allocations' => $allocations,
    ];
}

function teinvit_refund_quantity_to_int( $value ) {
    return max( 0, (int) round( max( 0, (float) $value ) ) );
}

function teinvit_refund_addon_ledger_reversal_status( $requested_qty, $reversed_qty ) {
    $requested_qty = teinvit_refund_quantity_to_int( $requested_qty );
    $reversed_qty = teinvit_refund_quantity_to_int( $reversed_qty );

    if ( $requested_qty <= 0 || $reversed_qty >= $requested_qty ) {
        return 'refunded';
    }

    return 'partially_refunded';
}

function teinvit_refund_update_addon_ledger_after_reversal( $addon_ledger, $status, array $debug_context = [], $capability_changed = '' ) {
    if ( ! is_array( $addon_ledger ) || empty( $addon_ledger['id'] ) || ! function_exists( 'teinvit_update_order_token_addon_ledger_status' ) ) {
        return false;
    }

    $previous_debug = is_array( $addon_ledger['debug_context'] ?? null ) ? $addon_ledger['debug_context'] : [];
    return teinvit_update_order_token_addon_ledger_status( (int) $addon_ledger['id'], $status, [
        'capability_changed' => $capability_changed !== '' ? $capability_changed : ( $addon_ledger['capability_changed'] ?? '' ),
        'error_message' => '',
        'debug_context' => array_merge( $previous_debug, [
            'refund_reversal' => $debug_context,
        ] ),
    ] );
}

function teinvit_refund_reverse_premium_upgrade( array $payload, array $mapping, array $debug_context ) {
    $target_token = sanitize_text_field( (string) ( $payload['target_token'] ?? '' ) );
    $loaded = teinvit_refund_load_invitation_config_for_token( $target_token, $mapping['target_vertical'] ?? '' );
    if ( empty( $loaded['ok'] ) ) {
        return [
            'status' => 'failed',
            'error_message' => sanitize_key( (string) ( $loaded['reason'] ?? 'invitation_config_missing' ) ),
            'previous_state' => [],
            'after_state' => [],
            'granted_qty' => max( 0, (float) ( $payload['granted_qty'] ?? 0 ) ),
            'reversed_qty' => 0,
        ];
    }

    $config = is_array( $loaded['config'] ?? null ) ? $loaded['config'] : [];
    if ( function_exists( 'teinvit_config_ensure_edit_balance_keys' ) ) {
        $config = teinvit_config_ensure_edit_balance_keys( $config );
    }

    $addon_ledger = is_array( $mapping['addon_ledger'] ?? null ) ? $mapping['addon_ledger'] : [];
    $previous_state = [
        'addon_ledger' => teinvit_refund_addon_ledger_snapshot( $addon_ledger ),
        'config' => teinvit_refund_config_state_snapshot( $target_token, $config ),
    ];
    $ledger_key = sanitize_text_field( (string) ( $payload['ledger_key'] ?? '' ) );
    $recorded_reversals = is_array( $config['premium_upgrade_refund_reversals'] ?? null ) ? $config['premium_upgrade_refund_reversals'] : [];
    if ( $ledger_key !== '' && isset( $recorded_reversals[ $ledger_key ] ) && is_array( $recorded_reversals[ $ledger_key ] ) ) {
        $recorded = $recorded_reversals[ $ledger_key ];
        return [
            'status' => 'processed',
            'error_message' => '',
            'previous_state' => $previous_state,
            'after_state' => [
                'addon_refund_reversal_processed' => 1,
                'effect_type' => 'premium_upgrade_refund',
                'addon_ledger' => teinvit_refund_addon_ledger_snapshot( $addon_ledger ),
                'config' => teinvit_refund_config_state_snapshot( $target_token, $config ),
                'already_recorded_in_config' => 1,
            ],
            'granted_qty' => max( 0, (float) ( $recorded['granted_qty'] ?? 0 ) ),
            'reversed_qty' => max( 0, (float) ( $recorded['reversed_qty'] ?? 0 ) ),
        ];
    }

    $marker_source = sanitize_key( (string) ( $config['default_included_edits_applied_source'] ?? '' ) );
    $marker_order_id = max( 0, (int) ( $config['default_included_edits_applied_order_id'] ?? 0 ) );
    $last_order_item_id = max( 0, (int) ( $config['premium_upgrade_last_order_item_id'] ?? 0 ) );
    $payload_order_id = max( 0, (int) ( $payload['order_id'] ?? 0 ) );
    $payload_order_item_id = max( 0, (int) ( $payload['order_item_id'] ?? 0 ) );
    $markers_match = $marker_source === 'woo_upgrade'
        && ( $marker_order_id <= 0 || $marker_order_id === $payload_order_id )
        && ( $last_order_item_id <= 0 || $last_order_item_id === $payload_order_item_id );
    $granted_qty = $markers_match ? teinvit_refund_quantity_to_int( $config['default_included_edits_applied_value'] ?? ( $payload['granted_qty'] ?? 0 ) ) : 0;
    $current_free = max( 0, (int) ( $config['edits_free_remaining'] ?? 0 ) );
    $reversed_qty = $markers_match ? min( $current_free, $granted_qty ) : 0;

    $config['premium_upgrade_active'] = 0;
    $config['premium_upgrade_refunded_at'] = current_time( 'mysql' );
    $config['premium_upgrade_refund_id'] = max( 0, (int) ( $payload['refund_id'] ?? 0 ) );
    $config['premium_upgrade_refund_order_item_id'] = $payload_order_item_id;
    if ( $ledger_key !== '' ) {
        $recorded_reversals[ $ledger_key ] = [
            'refund_id' => max( 0, (int) ( $payload['refund_id'] ?? 0 ) ),
            'refund_item_id' => max( 0, (int) ( $payload['refund_item_id'] ?? 0 ) ),
            'order_item_id' => $payload_order_item_id,
            'granted_qty' => $granted_qty,
            'reversed_qty' => $reversed_qty,
            'applied_at' => current_time( 'mysql' ),
        ];
        $config['premium_upgrade_refund_reversals'] = $recorded_reversals;
    }
    if ( $markers_match ) {
        $config['edits_free_remaining'] = max( 0, $current_free - $reversed_qty );
        $config['default_included_edits_last_reversed_value'] = $reversed_qty;
        $config['default_included_edits_last_reversed_refund_id'] = max( 0, (int) ( $payload['refund_id'] ?? 0 ) );
        $config['default_included_edits_last_reversed_at'] = current_time( 'mysql' );
        unset( $config['default_included_edits_applied'] );
        unset( $config['default_included_edits_applied_value'] );
        unset( $config['default_included_edits_applied_source'] );
        unset( $config['default_included_edits_applied_order_id'] );
    }

    $saved = teinvit_refund_save_invitation_config_for_token( $target_token, $config, $loaded['vertical'] ?? '' );
    if ( ! $saved ) {
        return [
            'status' => 'failed',
            'error_message' => 'premium_upgrade_config_save_failed',
            'previous_state' => $previous_state,
            'after_state' => [],
            'granted_qty' => $granted_qty,
            'reversed_qty' => 0,
        ];
    }
    if ( function_exists( 'teinvit_sync_legacy_edit_balance_from_config' ) ) {
        teinvit_sync_legacy_edit_balance_from_config( $target_token, $config );
    }

    teinvit_refund_update_addon_ledger_after_reversal( $addon_ledger, 'refunded', [
        'effect_type' => 'premium_upgrade_refund',
        'refund_id' => max( 0, (int) ( $payload['refund_id'] ?? 0 ) ),
        'refund_item_id' => max( 0, (int) ( $payload['refund_item_id'] ?? 0 ) ),
        'reversed_qty' => $reversed_qty,
        'markers_match' => $markers_match ? 1 : 0,
    ], 'premium_upgrade_refunded' );

    $after_loaded = teinvit_refund_load_invitation_config_for_token( $target_token, $loaded['vertical'] ?? '' );
    $after_config = ! empty( $after_loaded['ok'] ) && is_array( $after_loaded['config'] ?? null ) ? $after_loaded['config'] : $config;
    $after_ledger = ! empty( $addon_ledger['id'] ) && function_exists( 'teinvit_get_order_token_addon_ledger_by_item' )
        ? teinvit_get_order_token_addon_ledger_by_item( (int) ( $addon_ledger['order_id'] ?? 0 ), (int) ( $addon_ledger['order_item_id'] ?? 0 ), 'premium_upgrade' )
        : $addon_ledger;

    return [
        'status' => 'processed',
        'error_message' => '',
        'previous_state' => $previous_state,
        'after_state' => [
            'addon_refund_reversal_processed' => 1,
            'effect_type' => 'premium_upgrade_refund',
            'addon_ledger' => teinvit_refund_addon_ledger_snapshot( $after_ledger ),
            'config' => teinvit_refund_config_state_snapshot( $target_token, $after_config ),
            'premium_capability_disabled' => 1,
            'included_edits_markers_matched' => $markers_match ? 1 : 0,
        ],
        'granted_qty' => $granted_qty,
        'reversed_qty' => $reversed_qty,
    ];
}

function teinvit_refund_reverse_extra_edits( array $payload, array $mapping, array $debug_context ) {
    $target_token = sanitize_text_field( (string) ( $payload['target_token'] ?? '' ) );
    $loaded = teinvit_refund_load_invitation_config_for_token( $target_token, $mapping['target_vertical'] ?? '' );
    if ( empty( $loaded['ok'] ) ) {
        return [
            'status' => 'failed',
            'error_message' => sanitize_key( (string) ( $loaded['reason'] ?? 'invitation_config_missing' ) ),
            'previous_state' => [],
            'after_state' => [],
            'granted_qty' => max( 0, (float) ( $payload['granted_qty'] ?? 0 ) ),
            'reversed_qty' => 0,
        ];
    }

    $config = is_array( $loaded['config'] ?? null ) ? $loaded['config'] : [];
    if ( function_exists( 'teinvit_config_ensure_edit_balance_keys' ) ) {
        $config = teinvit_config_ensure_edit_balance_keys( $config );
    }

    $addon_ledger = is_array( $mapping['addon_ledger'] ?? null ) ? $mapping['addon_ledger'] : [];
    $previous_state = [
        'addon_ledger' => teinvit_refund_addon_ledger_snapshot( $addon_ledger ),
        'config' => teinvit_refund_config_state_snapshot( $target_token, $config ),
    ];
    $ledger_key = sanitize_text_field( (string) ( $payload['ledger_key'] ?? '' ) );
    $recorded_reversals = is_array( $config['edits_paid_refund_reversals'] ?? null ) ? $config['edits_paid_refund_reversals'] : [];
    if ( $ledger_key !== '' && isset( $recorded_reversals[ $ledger_key ] ) && is_array( $recorded_reversals[ $ledger_key ] ) ) {
        $recorded = $recorded_reversals[ $ledger_key ];
        return [
            'status' => 'processed',
            'error_message' => '',
            'previous_state' => $previous_state,
            'after_state' => [
                'addon_refund_reversal_processed' => 1,
                'effect_type' => 'extra_edits_refund',
                'addon_ledger' => teinvit_refund_addon_ledger_snapshot( $addon_ledger ),
                'config' => teinvit_refund_config_state_snapshot( $target_token, $config ),
                'already_recorded_in_config' => 1,
            ],
            'granted_qty' => max( 0, (float) ( $recorded['granted_qty'] ?? 0 ) ),
            'reversed_qty' => max( 0, (float) ( $recorded['reversed_qty'] ?? 0 ) ),
        ];
    }

    $granted_qty = teinvit_refund_quantity_to_int( $payload['granted_qty'] ?? ( $mapping['granted_qty'] ?? 0 ) );
    $current_paid = max( 0, (int) ( $config['edits_paid_remaining'] ?? 0 ) );
    $reversed_qty = min( $current_paid, $granted_qty );
    $config['edits_paid_remaining'] = max( 0, $current_paid - $reversed_qty );
    if ( $ledger_key !== '' ) {
        $recorded_reversals[ $ledger_key ] = [
            'refund_id' => max( 0, (int) ( $payload['refund_id'] ?? 0 ) ),
            'refund_item_id' => max( 0, (int) ( $payload['refund_item_id'] ?? 0 ) ),
            'order_item_id' => max( 0, (int) ( $payload['order_item_id'] ?? 0 ) ),
            'granted_qty' => $granted_qty,
            'reversed_qty' => $reversed_qty,
            'applied_at' => current_time( 'mysql' ),
        ];
        $config['edits_paid_refund_reversals'] = $recorded_reversals;
    }

    $saved = teinvit_refund_save_invitation_config_for_token( $target_token, $config, $loaded['vertical'] ?? '' );
    if ( ! $saved ) {
        return [
            'status' => 'failed',
            'error_message' => 'extra_edits_config_save_failed',
            'previous_state' => $previous_state,
            'after_state' => [],
            'granted_qty' => $granted_qty,
            'reversed_qty' => 0,
        ];
    }
    if ( function_exists( 'teinvit_sync_legacy_edit_balance_from_config' ) ) {
        teinvit_sync_legacy_edit_balance_from_config( $target_token, $config );
    }

    $addon_status = teinvit_refund_addon_ledger_reversal_status( $granted_qty, $reversed_qty );
    teinvit_refund_update_addon_ledger_after_reversal( $addon_ledger, $addon_status, [
        'effect_type' => 'extra_edits_refund',
        'refund_id' => max( 0, (int) ( $payload['refund_id'] ?? 0 ) ),
        'refund_item_id' => max( 0, (int) ( $payload['refund_item_id'] ?? 0 ) ),
        'granted_qty' => $granted_qty,
        'reversed_qty' => $reversed_qty,
    ], $addon_status === 'refunded' ? 'extra_edits_refunded' : 'extra_edits_partially_refunded' );

    $after_loaded = teinvit_refund_load_invitation_config_for_token( $target_token, $loaded['vertical'] ?? '' );
    $after_config = ! empty( $after_loaded['ok'] ) && is_array( $after_loaded['config'] ?? null ) ? $after_loaded['config'] : $config;
    $after_ledger = ! empty( $addon_ledger['id'] ) && function_exists( 'teinvit_get_order_token_addon_ledger_by_item' )
        ? teinvit_get_order_token_addon_ledger_by_item( (int) ( $addon_ledger['order_id'] ?? 0 ), (int) ( $addon_ledger['order_item_id'] ?? 0 ), 'extra_edits' )
        : $addon_ledger;

    return [
        'status' => 'processed',
        'error_message' => '',
        'previous_state' => $previous_state,
        'after_state' => [
            'addon_refund_reversal_processed' => 1,
            'effect_type' => 'extra_edits_refund',
            'addon_ledger' => teinvit_refund_addon_ledger_snapshot( $after_ledger ),
            'config' => teinvit_refund_config_state_snapshot( $target_token, $after_config ),
        ],
        'granted_qty' => $granted_qty,
        'reversed_qty' => $reversed_qty,
    ];
}

function teinvit_refund_reverse_extra_gifts( array $payload, array $mapping, array $debug_context ) {
    $target_token = sanitize_text_field( (string) ( $payload['target_token'] ?? '' ) );
    $grant_context = is_array( $mapping['grant_context'] ?? null ) ? $mapping['grant_context'] : [];
    if ( empty( $grant_context['safe'] ) ) {
        return [
            'status' => 'skipped',
            'error_message' => 'manual_review_extra_gifts_grant_unknown',
            'previous_state' => [],
            'after_state' => [],
            'granted_qty' => 0,
            'reversed_qty' => 0,
        ];
    }

    $loaded = teinvit_refund_load_invitation_config_for_token( $target_token, $mapping['target_vertical'] ?? '' );
    if ( empty( $loaded['ok'] ) ) {
        return [
            'status' => 'failed',
            'error_message' => sanitize_key( (string) ( $loaded['reason'] ?? 'invitation_config_missing' ) ),
            'previous_state' => [],
            'after_state' => [],
            'granted_qty' => max( 0, (float) ( $payload['granted_qty'] ?? 0 ) ),
            'reversed_qty' => 0,
        ];
    }

    $config = is_array( $loaded['config'] ?? null ) ? $loaded['config'] : [];
    list( $config, $summary_before ) = teinvit_refund_sync_gift_summary_to_config( $target_token, $config );
    $addon_ledger = is_array( $mapping['addon_ledger'] ?? null ) ? $mapping['addon_ledger'] : [];
    $previous_state = [
        'addon_ledger' => teinvit_refund_addon_ledger_snapshot( $addon_ledger ),
        'config' => teinvit_refund_config_state_snapshot( $target_token, $config ),
    ];
    $ledger_key = sanitize_text_field( (string) ( $payload['ledger_key'] ?? '' ) );
    $recorded_reversals = is_array( $config['gifts_refund_reversals'] ?? null ) ? $config['gifts_refund_reversals'] : [];
    if ( $ledger_key !== '' && isset( $recorded_reversals[ $ledger_key ] ) && is_array( $recorded_reversals[ $ledger_key ] ) ) {
        $recorded = $recorded_reversals[ $ledger_key ];
        return [
            'status' => 'processed',
            'error_message' => '',
            'previous_state' => $previous_state,
            'after_state' => [
                'addon_refund_reversal_processed' => 1,
                'effect_type' => 'extra_gifts_refund',
                'addon_ledger' => teinvit_refund_addon_ledger_snapshot( $addon_ledger ),
                'config' => teinvit_refund_config_state_snapshot( $target_token, $config ),
                'allocation_key' => sanitize_text_field( (string) ( $recorded['allocation_key'] ?? '' ) ),
                'already_recorded_in_config' => 1,
            ],
            'granted_qty' => max( 0, (float) ( $recorded['granted_qty'] ?? 0 ) ),
            'reversed_qty' => max( 0, (float) ( $recorded['reversed_qty'] ?? 0 ) ),
        ];
    }

    $allocation_key = sanitize_text_field( (string) ( $grant_context['allocation_key'] ?? '' ) );
    $summary_allocation = null;
    foreach ( (array) ( $summary_before['allocations'] ?? [] ) as $candidate ) {
        if ( is_array( $candidate ) && sanitize_text_field( (string) ( $candidate['allocation_key'] ?? '' ) ) === $allocation_key ) {
            $summary_allocation = $candidate;
            break;
        }
    }

    if ( ! is_array( $summary_allocation ) ) {
        return [
            'status' => 'skipped',
            'error_message' => 'manual_review_extra_gifts_allocation_missing',
            'previous_state' => $previous_state,
            'after_state' => [],
            'granted_qty' => teinvit_refund_quantity_to_int( $payload['granted_qty'] ?? 0 ),
            'reversed_qty' => 0,
        ];
    }

    $granted_qty = teinvit_refund_quantity_to_int( $payload['granted_qty'] ?? ( $mapping['granted_qty'] ?? 0 ) );
    $unused_slots = ( sanitize_key( (string) ( $summary_allocation['status'] ?? '' ) ) === 'applied' )
        ? max( 0, (int) ( $summary_allocation['slots_remaining'] ?? 0 ) )
        : 0;
    $reversed_qty = min( $unused_slots, $granted_qty );

    $allocations = is_array( $config['gifts_allocations'] ?? null ) ? $config['gifts_allocations'] : [];
    $changed = false;
    foreach ( $allocations as &$allocation ) {
        if ( ! is_array( $allocation ) ) {
            continue;
        }
        if ( sanitize_text_field( (string) ( $allocation['allocation_key'] ?? '' ) ) !== $allocation_key ) {
            continue;
        }

        if ( $reversed_qty > 0 ) {
            $old_total = max( 0, (int) ( $summary_allocation['slots_total'] ?? ( $allocation['slots_total'] ?? 0 ) ) );
            $new_total = max( 0, $old_total - $reversed_qty );
            $allocation['slots_reversed'] = max( 0, (int) ( $allocation['slots_reversed'] ?? 0 ) ) + $reversed_qty;
            $allocation['last_refund_id'] = max( 0, (int) ( $payload['refund_id'] ?? 0 ) );
            $allocation['last_refund_item_id'] = max( 0, (int) ( $payload['refund_item_id'] ?? 0 ) );
            $allocation['last_refunded_at'] = current_time( 'mysql' );
            if ( $new_total <= 0 ) {
                $allocation['status'] = 'reverted';
                $allocation['reverted_at'] = current_time( 'mysql' );
                $allocation['reverted_by_refund_id'] = max( 0, (int) ( $payload['refund_id'] ?? 0 ) );
                $allocation['slots_total_before_refund'] = $old_total;
                $allocation['slots_total'] = $old_total;
                $allocation['slots_remaining'] = 0;
            } else {
                $allocation['status'] = 'applied';
                $allocation['slots_total_before_last_refund'] = $old_total;
                $allocation['slots_total'] = $new_total;
                $allocation['slots_remaining'] = max( 0, (int) ( $summary_allocation['slots_remaining'] ?? 0 ) - $reversed_qty );
            }
            $changed = true;
        }
        break;
    }
    unset( $allocation );

    if ( $changed ) {
        if ( $ledger_key !== '' ) {
            $recorded_reversals[ $ledger_key ] = [
                'refund_id' => max( 0, (int) ( $payload['refund_id'] ?? 0 ) ),
                'refund_item_id' => max( 0, (int) ( $payload['refund_item_id'] ?? 0 ) ),
                'order_item_id' => max( 0, (int) ( $payload['order_item_id'] ?? 0 ) ),
                'allocation_key' => $allocation_key,
                'granted_qty' => $granted_qty,
                'reversed_qty' => $reversed_qty,
                'applied_at' => current_time( 'mysql' ),
            ];
            $config['gifts_refund_reversals'] = $recorded_reversals;
        }
        $config['gifts_allocations'] = array_values( $allocations );
        list( $config, $summary_after ) = teinvit_refund_sync_gift_summary_to_config( $target_token, $config );
    } else {
        if ( $ledger_key !== '' ) {
            $recorded_reversals[ $ledger_key ] = [
                'refund_id' => max( 0, (int) ( $payload['refund_id'] ?? 0 ) ),
                'refund_item_id' => max( 0, (int) ( $payload['refund_item_id'] ?? 0 ) ),
                'order_item_id' => max( 0, (int) ( $payload['order_item_id'] ?? 0 ) ),
                'allocation_key' => $allocation_key,
                'granted_qty' => $granted_qty,
                'reversed_qty' => 0,
                'applied_at' => current_time( 'mysql' ),
            ];
            $config['gifts_refund_reversals'] = $recorded_reversals;
        }
        $summary_after = $summary_before;
    }

    $saved = teinvit_refund_save_invitation_config_for_token( $target_token, $config, $loaded['vertical'] ?? '' );
    if ( ! $saved ) {
        return [
            'status' => 'failed',
            'error_message' => 'extra_gifts_config_save_failed',
            'previous_state' => $previous_state,
            'after_state' => [],
            'granted_qty' => $granted_qty,
            'reversed_qty' => 0,
        ];
    }
    teinvit_refund_sync_legacy_gift_capacity_from_summary( $target_token, $summary_after );

    $addon_status = teinvit_refund_addon_ledger_reversal_status( $granted_qty, $reversed_qty );
    teinvit_refund_update_addon_ledger_after_reversal( $addon_ledger, $addon_status, [
        'effect_type' => 'extra_gifts_refund',
        'refund_id' => max( 0, (int) ( $payload['refund_id'] ?? 0 ) ),
        'refund_item_id' => max( 0, (int) ( $payload['refund_item_id'] ?? 0 ) ),
        'allocation_key' => $allocation_key,
        'unused_slots_before_refund' => $unused_slots,
        'granted_qty' => $granted_qty,
        'reversed_qty' => $reversed_qty,
    ], $addon_status === 'refunded' ? 'extra_gifts_refunded' : 'extra_gifts_partially_refunded' );

    $after_loaded = teinvit_refund_load_invitation_config_for_token( $target_token, $loaded['vertical'] ?? '' );
    $after_config = ! empty( $after_loaded['ok'] ) && is_array( $after_loaded['config'] ?? null ) ? $after_loaded['config'] : $config;
    $after_ledger = ! empty( $addon_ledger['id'] ) && function_exists( 'teinvit_get_order_token_addon_ledger_by_item' )
        ? teinvit_get_order_token_addon_ledger_by_item( (int) ( $addon_ledger['order_id'] ?? 0 ), (int) ( $addon_ledger['order_item_id'] ?? 0 ), 'extra_gifts' )
        : $addon_ledger;

    return [
        'status' => 'processed',
        'error_message' => '',
        'previous_state' => $previous_state,
        'after_state' => [
            'addon_refund_reversal_processed' => 1,
            'effect_type' => 'extra_gifts_refund',
            'addon_ledger' => teinvit_refund_addon_ledger_snapshot( $after_ledger ),
            'config' => teinvit_refund_config_state_snapshot( $target_token, $after_config ),
            'allocation_key' => $allocation_key,
            'summary_after' => [
                'addon_slots' => max( 0, (int) ( $summary_after['addon_slots'] ?? 0 ) ),
                'used_slots' => max( 0, (int) ( $summary_after['used_slots'] ?? 0 ) ),
                'available_slots' => max( 0, (int) ( $summary_after['available_slots'] ?? 0 ) ),
                'total_slots' => max( 0, (int) ( $summary_after['total_slots'] ?? 0 ) ),
            ],
        ],
        'granted_qty' => $granted_qty,
        'reversed_qty' => $reversed_qty,
    ];
}

function teinvit_refund_addon_reversal_note( array $payload, $effect_type, $status, $error_message, $reversed_qty ) {
    $effect_type = sanitize_key( (string) $effect_type );
    $target_token = sanitize_text_field( (string) ( $payload['target_token'] ?? '' ) );
    $refund_id = (int) ( $payload['refund_id'] ?? 0 );
    $refund_item_id = (int) ( $payload['refund_item_id'] ?? 0 );

    if ( $status === 'skipped' || $status === 'failed' ) {
        return sprintf(
            '[TeInvit Refund] Addon refund %s: refund #%d item #%d, target token %s, effect=%s, reason=%s.',
            $status === 'failed' ? 'failed' : 'skipped/manual review',
            $refund_id,
            $refund_item_id,
            $target_token !== '' ? $target_token : 'n/a',
            $effect_type,
            sanitize_key( (string) $error_message )
        );
    }

    $payload['ledger_key'] = $ledger_key;

    if ( $effect_type === 'premium_upgrade_refund' ) {
        return sprintf(
            '[TeInvit Refund] Premium upgrade refund applied: refund #%d, item #%d, target token %s, premium capability disabled, included edits reversed %d.',
            $refund_id,
            $refund_item_id,
            $target_token,
            (int) $reversed_qty
        );
    }

    if ( $effect_type === 'extra_edits_refund' ) {
        return sprintf(
            '[TeInvit Refund] Extra edits refund applied: refund #%d, item #%d, target token %s, reversed edits %d.',
            $refund_id,
            $refund_item_id,
            $target_token,
            (int) $reversed_qty
        );
    }

    if ( $effect_type === 'extra_gifts_refund' && (int) $reversed_qty <= 0 ) {
        return sprintf(
            '[TeInvit Refund] Extra gifts refund processed with no available unused slots to reverse: refund #%d, item #%d, target token %s.',
            $refund_id,
            $refund_item_id,
            $target_token
        );
    }

    if ( $effect_type === 'extra_gifts_refund' ) {
        return sprintf(
            '[TeInvit Refund] Extra gifts refund applied: refund #%d, item #%d, target token %s, reversed gift slots %d.',
            $refund_id,
            $refund_item_id,
            $target_token,
            (int) $reversed_qty
        );
    }

    return sprintf(
        '[TeInvit Refund] Addon refund processed: refund #%d, item #%d, target token %s, effect=%s, reversed qty %d.',
        $refund_id,
        $refund_item_id,
        $target_token,
        $effect_type,
        (int) $reversed_qty
    );
}

function teinvit_refund_process_addon_item_reversal( $order, array $payload, array $mapping, array $debug_context ) {
    $effect_type = sanitize_key( (string) ( $payload['effect_type'] ?? 'unknown_addon_refund' ) );
    $debug_context['phase'] = 'stage6_8_addon_refund_reversal';
    $debug_context['dry_run'] = false;
    $debug_context['commercial_effect'] = 'addon_refund_reversal';

    list( $ledger_id, $existing_ledger, $ledger_key ) = teinvit_refund_get_or_create_ledger_id( $payload, $debug_context );
    if ( $ledger_id <= 0 ) {
        teinvit_refund_add_order_note_once(
            $order,
            teinvit_refund_addon_reversal_note( $payload, $effect_type, 'failed', 'ledger_unavailable', 0 ),
            'addon_reversal|ledger_unavailable|' . md5( wp_json_encode( $payload ) )
        );
        return [
            'ok' => false,
            'status' => 'failed',
            'reason' => 'ledger_unavailable',
        ];
    }

    if ( teinvit_refund_addon_reversal_effect_applied( $existing_ledger ) ) {
        return [
            'ok' => true,
            'status' => 'processed',
            'already_processed' => true,
        ];
    }

    if ( empty( $mapping['mapped'] ) || empty( $payload['target_token'] ) ) {
        $reason = sanitize_key( (string) ( $mapping['reason'] ?? 'addon_mapping_missing' ) );
        if ( function_exists( 'teinvit_update_order_token_refund_ledger_status' ) ) {
            teinvit_update_order_token_refund_ledger_status( $ledger_id, 'skipped', [
                'error_message' => $reason,
                'debug_context' => $debug_context,
                'refunded_qty' => $payload['refunded_qty'] ?? 0,
                'refunded_total' => $payload['refunded_total'] ?? 0,
                'granted_qty' => $payload['granted_qty'] ?? 0,
                'reversed_qty' => 0,
            ] );
        }
        teinvit_refund_add_order_note_once(
            $order,
            teinvit_refund_addon_reversal_note( $payload, $effect_type, 'skipped', $reason, 0 ),
            'addon_reversal|' . $ledger_key . '|skipped|' . $reason
        );
        return [
            'ok' => true,
            'status' => 'skipped',
            'reason' => $reason,
        ];
    }

    if ( $effect_type === 'premium_upgrade_refund' ) {
        $result = teinvit_refund_reverse_premium_upgrade( $payload, $mapping, $debug_context );
    } elseif ( $effect_type === 'extra_edits_refund' ) {
        $result = teinvit_refund_reverse_extra_edits( $payload, $mapping, $debug_context );
    } elseif ( $effect_type === 'extra_gifts_refund' ) {
        $result = teinvit_refund_reverse_extra_gifts( $payload, $mapping, $debug_context );
    } else {
        $result = [
            'status' => 'skipped',
            'error_message' => 'unknown_addon_refund',
            'previous_state' => [],
            'after_state' => [],
            'granted_qty' => $payload['granted_qty'] ?? 0,
            'reversed_qty' => 0,
        ];
    }

    $status = sanitize_key( (string) ( $result['status'] ?? 'failed' ) );
    if ( ! in_array( $status, [ 'processed', 'skipped', 'failed' ], true ) ) {
        $status = 'failed';
    }
    $error_message = sanitize_key( (string) ( $result['error_message'] ?? '' ) );
    $previous_state = is_array( $result['previous_state'] ?? null ) ? $result['previous_state'] : [];
    $after_state = is_array( $result['after_state'] ?? null ) ? $result['after_state'] : [];
    $granted_qty = max( 0, (float) ( $result['granted_qty'] ?? ( $payload['granted_qty'] ?? 0 ) ) );
    $reversed_qty = max( 0, (float) ( $result['reversed_qty'] ?? 0 ) );
    $debug_context['mapping'] = $mapping;
    $debug_context['handler_result'] = [
        'status' => $status,
        'error_message' => $error_message,
        'granted_qty' => $granted_qty,
        'reversed_qty' => $reversed_qty,
    ];

    if ( function_exists( 'teinvit_update_order_token_refund_ledger_status' ) ) {
        teinvit_update_order_token_refund_ledger_status( $ledger_id, $status, [
            'error_message' => $error_message,
            'debug_context' => $debug_context,
            'previous_state' => $previous_state,
            'after_state' => $after_state,
            'refunded_qty' => $payload['refunded_qty'] ?? 0,
            'refunded_total' => $payload['refunded_total'] ?? 0,
            'granted_qty' => $granted_qty,
            'reversed_qty' => $reversed_qty,
        ] );
    }

    teinvit_refund_add_order_note_once(
        $order,
        teinvit_refund_addon_reversal_note( $payload, $effect_type, $status, $error_message, $reversed_qty ),
        'addon_reversal|' . $ledger_key . '|' . $status . '|' . $effect_type
    );

    return [
        'ok' => $status !== 'failed',
        'status' => $status,
        'reason' => $error_message,
        'granted_qty' => $granted_qty,
        'reversed_qty' => $reversed_qty,
    ];
}

function teinvit_refund_process_unmapped_item( $order, array $refund_context, $reason ) {
    $payload = [
        'refund_id' => (int) ( $refund_context['refund_id'] ?? 0 ),
        'refund_item_id' => (int) ( $refund_context['refund_item_id'] ?? 0 ),
        'order_id' => is_object( $order ) && method_exists( $order, 'get_id' ) ? (int) $order->get_id() : 0,
        'order_item_id' => (int) ( $refund_context['order_item_id'] ?? 0 ),
        'product_id' => 0,
        'variation_id' => 0,
        'effect_type' => 'unmapped_line_item_skipped',
        'status' => 'pending',
        'refunded_qty' => (float) ( $refund_context['refunded_qty'] ?? 0 ),
        'refunded_total' => (float) ( $refund_context['refunded_total'] ?? 0 ),
    ];

    return teinvit_refund_record_ledger( $order, $payload, 'skipped', $reason, [
        'dry_run' => true,
        'phase' => 'stage1_mapping_only',
        'reason' => $reason,
        'refund_context' => $refund_context,
    ] );
}

function teinvit_refund_process_order_refunded( $order_id, $refund_id, $dry_run = true ) {
    if ( ! function_exists( 'wc_get_order' ) ) {
        return false;
    }

    $order_id = max( 0, (int) $order_id );
    $refund_id = max( 0, (int) $refund_id );
    $order = wc_get_order( $order_id );
    $refund = wc_get_order( $refund_id );
    if ( ! is_object( $order ) || ! is_object( $refund ) ) {
        return false;
    }

    $refund_items = teinvit_refund_get_refund_line_items( $refund_id );
    if ( empty( $refund_items ) ) {
        $payload = [
            'refund_id' => $refund_id,
            'refund_item_id' => 0,
            'order_id' => $order_id,
            'order_item_id' => 0,
            'product_id' => 0,
            'variation_id' => 0,
            'effect_type' => 'amount_only_skipped',
            'refunded_qty' => 0,
            'refunded_total' => is_object( $refund ) && method_exists( $refund, 'get_amount' ) ? abs( (float) $refund->get_amount() ) : 0,
        ];
        teinvit_refund_record_ledger( $order, $payload, 'skipped', 'refund_without_line_items', [
            'dry_run' => (bool) $dry_run,
            'phase' => 'stage1_mapping_only',
            'reason' => 'refund_without_line_items',
            'refund_id' => $refund_id,
            'order_id' => $order_id,
        ] );
        return true;
    }

    foreach ( $refund_items as $refund_context ) {
        $order_item_id = max( 0, (int) ( $refund_context['order_item_id'] ?? 0 ) );
        if ( $order_item_id <= 0 ) {
            teinvit_refund_process_unmapped_item( $order, $refund_context, 'missing_refunded_item_id' );
            continue;
        }

        $classification = teinvit_refund_classify_original_item( $order, $order_item_id );
        if ( (string) ( $classification['type'] ?? '' ) === 'unmapped' ) {
            teinvit_refund_process_unmapped_item( $order, $refund_context, $classification['reason'] ?? 'original_order_item_missing' );
            continue;
        }

        $product_id = max( 0, (int) ( $classification['product_id'] ?? 0 ) );
        $variation_id = max( 0, (int) ( $classification['variation_id'] ?? 0 ) );
        $base_payload = [
            'refund_id' => $refund_id,
            'refund_item_id' => (int) ( $refund_context['refund_item_id'] ?? 0 ),
            'order_id' => $order_id,
            'order_item_id' => $order_item_id,
            'product_id' => $product_id,
            'variation_id' => $variation_id,
            'refunded_qty' => (float) ( $refund_context['refunded_qty'] ?? 0 ),
            'refunded_total' => (float) ( $refund_context['refunded_total'] ?? 0 ),
            'reversed_qty' => 0,
        ];

        if ( (string) ( $classification['type'] ?? '' ) === 'invitation' ) {
            $mapping = teinvit_refund_map_invitation_item_to_token( $order, $order_item_id );
            $payload = array_merge( $base_payload, [
                'effect_type' => 'invitation_refund',
                'token' => sanitize_text_field( (string) ( $mapping['token'] ?? '' ) ),
                'target_token' => '',
            ] );
            $debug = [
                'dry_run' => (bool) $dry_run,
                'phase' => 'stage1_mapping_only',
                'classification' => 'invitation',
                'refund_context' => $refund_context,
                'invitation_context' => $classification['invitation_context'] ?? [],
                'mapping' => $mapping,
            ];
            teinvit_refund_process_invitation_item_stage2( $order, $payload, $mapping, $debug );
            continue;
        }

        if ( (string) ( $classification['type'] ?? '' ) === 'addon' ) {
            $mapping = teinvit_refund_map_addon_item_to_target_token( $order, $order_item_id, $classification, $refund_context );
            $payload = array_merge( $base_payload, [
                'effect_type' => sanitize_key( (string) ( $mapping['effect_type'] ?? 'unknown_addon_refund' ) ),
                'token' => '',
                'target_token' => sanitize_text_field( (string) ( $mapping['target_token'] ?? '' ) ),
                'granted_qty' => max( 0, (float) ( $mapping['granted_qty'] ?? 0 ) ),
            ] );
            $debug = [
                'dry_run' => (bool) $dry_run,
                'phase' => 'stage1_mapping_only',
                'classification' => 'addon',
                'refund_context' => $refund_context,
                'item_meta' => $classification['meta'] ?? [],
                'addon_type' => sanitize_key( (string) ( $mapping['addon_type'] ?? ( $classification['addon_type'] ?? 'unknown' ) ) ),
                'mapping' => $mapping,
            ];
            if ( ! empty( $mapping['mapped'] ) ) {
                teinvit_refund_process_addon_item_reversal( $order, $payload, $mapping, $debug );
            } else {
                teinvit_refund_record_ledger( $order, $payload, 'skipped', $mapping['reason'] ?? 'addon_mapping_missing', $debug );
            }
            continue;
        }

        $payload = array_merge( $base_payload, [
            'effect_type' => 'non_teinvit_item',
            'token' => '',
            'target_token' => '',
        ] );
        teinvit_refund_record_ledger( $order, $payload, 'skipped', 'non_teinvit_item', [
            'dry_run' => (bool) $dry_run,
            'phase' => 'stage1_mapping_only',
            'classification' => 'non_teinvit',
            'refund_context' => $refund_context,
            'item_meta' => $classification['meta'] ?? [],
        ], [], [], 'none' );
    }

    return true;
}

function teinvit_refund_note_manual_review_for_refund_change( $refund_id, $change_type, $refund = null ) {
    $refund_id = max( 0, (int) $refund_id );
    $change_type = sanitize_key( (string) $change_type );
    $ledgers = function_exists( 'teinvit_get_order_token_refund_ledgers_for_refund' )
        ? teinvit_get_order_token_refund_ledgers_for_refund( $refund_id )
        : [];
    $order_ids = [];
    foreach ( $ledgers as $ledger ) {
        $order_id = max( 0, (int) ( $ledger['order_id'] ?? 0 ) );
        if ( $order_id > 0 ) {
            $order_ids[] = $order_id;
        }
    }
    if ( empty( $order_ids ) && is_object( $refund ) && method_exists( $refund, 'get_parent_id' ) ) {
        $parent_id = max( 0, (int) $refund->get_parent_id() );
        if ( $parent_id > 0 ) {
            $order_ids[] = $parent_id;
        }
    }
    $order_ids = array_values( array_unique( $order_ids ) );

    foreach ( $order_ids as $order_id ) {
        $order = function_exists( 'wc_get_order' ) ? wc_get_order( $order_id ) : null;
        if ( ! is_object( $order ) ) {
            continue;
        }
        teinvit_refund_add_order_note_once(
            $order,
            sprintf( '[TeInvit Refund Etapa 1] Refund #%d a fost %s dupa procesarea ledgerului TeInvit. Nu se reactiveaza automat nimic; necesita manual review.', $refund_id, $change_type ),
            'refund_change|' . $change_type . '|' . $refund_id
        );
    }
}

add_action( 'woocommerce_order_refunded', function( $order_id, $refund_id ) {
    teinvit_refund_process_order_refunded( $order_id, $refund_id, true );
}, 10, 2 );

add_action( 'woocommerce_delete_order_refund', function( $refund_id ) {
    teinvit_refund_note_manual_review_for_refund_change( $refund_id, 'sters' );
}, 10, 1 );

add_action( 'woocommerce_update_order_refund', function( $refund_id, $refund = null ) {
    teinvit_refund_note_manual_review_for_refund_change( $refund_id, 'modificat', $refund );
}, 10, 2 );
