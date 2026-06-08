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
            if ( ! empty( $mapping['mapped'] ) ) {
                teinvit_refund_record_ledger( $order, $payload, 'processed', '', $debug, $mapping['order_token_row'] ?? [] );
            } else {
                teinvit_refund_record_ledger( $order, $payload, 'skipped', $mapping['reason'] ?? 'token_mapping_missing', $debug );
            }
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
                teinvit_refund_record_ledger( $order, $payload, 'processed', '', $debug, $mapping['addon_ledger'] ?? [] );
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
