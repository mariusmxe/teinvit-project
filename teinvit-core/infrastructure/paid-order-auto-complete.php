<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! defined( 'TEINVIT_PAID_ORDER_AUTO_COMPLETE_OPTION' ) ) {
    define( 'TEINVIT_PAID_ORDER_AUTO_COMPLETE_OPTION', 'teinvit_paid_order_auto_complete_settings' );
}

if ( ! defined( 'TEINVIT_PAID_ORDER_AUTO_COMPLETE_ACTION' ) ) {
    define( 'TEINVIT_PAID_ORDER_AUTO_COMPLETE_ACTION', 'teinvit_auto_complete_paid_order_after_delay' );
}

if ( ! defined( 'TEINVIT_PAID_ORDER_AUTO_COMPLETE_GROUP' ) ) {
    define( 'TEINVIT_PAID_ORDER_AUTO_COMPLETE_GROUP', 'teinvit' );
}

function teinvit_paid_order_auto_complete_allowed_delays() {
    return [ 5, 10, 20, 30, 60 ];
}

function teinvit_paid_order_auto_complete_allowed_scopes() {
    return [
        'teinvit_only' => 'Only eligible TeInvit orders',
        'all_paid'     => 'All paid orders',
    ];
}

function teinvit_paid_order_auto_complete_default_settings() {
    return [
        'enabled'       => '0',
        'delay'         => 10,
        'scope'         => 'teinvit_only',
        'debug_logging' => '1',
    ];
}

function teinvit_paid_order_auto_complete_sanitize_settings( $settings ) {
    $settings = is_array( $settings ) ? $settings : [];
    $defaults = teinvit_paid_order_auto_complete_default_settings();

    $enabled = ! empty( $settings['enabled'] ) && (string) $settings['enabled'] !== '0' ? '1' : '0';

    $delay = isset( $settings['delay'] ) ? (int) $settings['delay'] : (int) $defaults['delay'];
    if ( ! in_array( $delay, teinvit_paid_order_auto_complete_allowed_delays(), true ) ) {
        $delay = (int) $defaults['delay'];
    }

    $scope = isset( $settings['scope'] ) ? sanitize_key( (string) $settings['scope'] ) : $defaults['scope'];
    if ( ! array_key_exists( $scope, teinvit_paid_order_auto_complete_allowed_scopes() ) ) {
        $scope = $defaults['scope'];
    }

    $debug_logging = ! empty( $settings['debug_logging'] ) && (string) $settings['debug_logging'] !== '0' ? '1' : '0';

    return [
        'enabled'       => $enabled,
        'delay'         => $delay,
        'scope'         => $scope,
        'debug_logging' => $debug_logging,
    ];
}

function teinvit_paid_order_auto_complete_settings() {
    $stored = get_option( TEINVIT_PAID_ORDER_AUTO_COMPLETE_OPTION, [] );
    return teinvit_paid_order_auto_complete_sanitize_settings( $stored );
}

function teinvit_paid_order_auto_complete_is_enabled() {
    $settings = teinvit_paid_order_auto_complete_settings();
    return ! empty( $settings['enabled'] );
}

function teinvit_paid_order_auto_complete_admin_capability() {
    return function_exists( 'teinvit_admin_capability' ) ? teinvit_admin_capability() : 'manage_woocommerce';
}

function teinvit_paid_order_auto_complete_log( $level, $event, array $context = [] ) {
    $settings = teinvit_paid_order_auto_complete_settings();
    $level = sanitize_key( (string) $level );
    if ( ! in_array( $level, [ 'debug', 'info', 'warning', 'error', 'critical' ], true ) ) {
        $level = 'info';
    }

    if ( empty( $settings['debug_logging'] ) && in_array( $level, [ 'debug', 'info' ], true ) ) {
        return;
    }

    $event = sanitize_key( (string) $event );
    if ( $event === '' ) {
        $event = 'event';
    }

    $context = array_merge(
        [
            'source' => 'teinvit-auto-complete',
            'event'  => $event,
        ],
        $context
    );

    if ( function_exists( 'wc_get_logger' ) ) {
        wc_get_logger()->log( $level, $event, $context );
        return;
    }

    error_log( '[TeInvit Auto Complete] level=' . $level . ' event=' . $event . ' context=' . wp_json_encode( $context ) );
}

function teinvit_paid_order_auto_complete_skip( $reason, array $context = [], $level = 'info' ) {
    $context['reason'] = sanitize_key( (string) $reason );
    teinvit_paid_order_auto_complete_log( $level, 'skip_' . sanitize_key( (string) $reason ), $context );
    return false;
}

function teinvit_paid_order_auto_complete_manual_payment_methods() {
    $methods = [ 'cod', 'bacs', 'cheque' ];
    return (array) apply_filters( 'teinvit_paid_order_auto_complete_manual_payment_methods', $methods );
}

function teinvit_paid_order_auto_complete_is_manual_payment_method( $order ) {
    if ( ! is_object( $order ) || ! method_exists( $order, 'get_payment_method' ) ) {
        return false;
    }

    $method = sanitize_key( (string) $order->get_payment_method() );
    if ( $method === '' ) {
        return false;
    }

    $manual_methods = array_map( 'sanitize_key', teinvit_paid_order_auto_complete_manual_payment_methods() );
    return in_array( $method, $manual_methods, true );
}

function teinvit_paid_order_auto_complete_has_payment_evidence( $order ) {
    if ( ! is_object( $order ) ) {
        return false;
    }

    $transaction_id = method_exists( $order, 'get_transaction_id' )
        ? trim( (string) $order->get_transaction_id() )
        : '';
    if ( $transaction_id !== '' ) {
        return true;
    }

    if ( method_exists( $order, 'get_date_paid' ) ) {
        return (bool) $order->get_date_paid( 'edit' );
    }

    return false;
}

function teinvit_paid_order_auto_complete_order_is_paid( $order ) {
    if ( ! is_object( $order ) || ! method_exists( $order, 'is_paid' ) ) {
        return false;
    }

    return (bool) $order->is_paid();
}

function teinvit_paid_order_auto_complete_item_is_eligible( $item ) {
    if ( ! is_object( $item ) ) {
        return false;
    }

    $product_id = method_exists( $item, 'get_product_id' ) ? (int) $item->get_product_id() : 0;
    $variation_id = method_exists( $item, 'get_variation_id' ) ? (int) $item->get_variation_id() : 0;
    if ( $product_id <= 0 && $variation_id <= 0 ) {
        return false;
    }

    if ( function_exists( 'teinvit_get_configurable_product_context' ) && teinvit_get_configurable_product_context( $product_id, $variation_id ) ) {
        return true;
    }

    if ( function_exists( 'teinvit_addon_product_context' ) && teinvit_addon_product_context( $product_id, $variation_id ) ) {
        return true;
    }

    return false;
}

function teinvit_paid_order_auto_complete_order_is_eligible( $order ) {
    if ( ! is_object( $order ) || ! method_exists( $order, 'get_items' ) ) {
        return false;
    }

    $items = $order->get_items( 'line_item' );
    if ( empty( $items ) || ! is_array( $items ) ) {
        return false;
    }

    foreach ( $items as $item ) {
        if ( ! teinvit_paid_order_auto_complete_item_is_eligible( $item ) ) {
            return false;
        }
    }

    return true;
}

function teinvit_paid_order_auto_complete_scope_allows_order( $order, $scope ) {
    $scope = sanitize_key( (string) $scope );
    if ( $scope === 'all_paid' ) {
        return true;
    }

    return teinvit_paid_order_auto_complete_order_is_eligible( $order );
}

function teinvit_paid_order_auto_complete_mark_payment_complete( $order_id, $transaction_id = '' ) {
    $order_id = max( 0, (int) $order_id );
    if ( $order_id <= 0 ) {
        return;
    }

    if ( ! isset( $GLOBALS['teinvit_paid_order_auto_complete_payment_complete_orders'] ) || ! is_array( $GLOBALS['teinvit_paid_order_auto_complete_payment_complete_orders'] ) ) {
        $GLOBALS['teinvit_paid_order_auto_complete_payment_complete_orders'] = [];
    }

    $GLOBALS['teinvit_paid_order_auto_complete_payment_complete_orders'][ $order_id ] = true;
}

function teinvit_paid_order_auto_complete_unmark_payment_complete( $order_id ) {
    $order_id = max( 0, (int) $order_id );
    if ( $order_id <= 0 || empty( $GLOBALS['teinvit_paid_order_auto_complete_payment_complete_orders'] ) || ! is_array( $GLOBALS['teinvit_paid_order_auto_complete_payment_complete_orders'] ) ) {
        return;
    }

    unset( $GLOBALS['teinvit_paid_order_auto_complete_payment_complete_orders'][ $order_id ] );
}

function teinvit_paid_order_auto_complete_is_payment_complete_context( $order_id ) {
    $order_id = max( 0, (int) $order_id );
    return $order_id > 0
        && ! empty( $GLOBALS['teinvit_paid_order_auto_complete_payment_complete_orders'] )
        && is_array( $GLOBALS['teinvit_paid_order_auto_complete_payment_complete_orders'] )
        && ! empty( $GLOBALS['teinvit_paid_order_auto_complete_payment_complete_orders'][ $order_id ] );
}

function teinvit_paid_order_auto_complete_filter_payment_complete_status( $status, $order_id, $order = null ) {
    $settings = teinvit_paid_order_auto_complete_settings();
    if ( empty( $settings['enabled'] ) ) {
        return $status;
    }

    $status_key = sanitize_key( (string) $status );
    $status_key = preg_replace( '/^wc-/', '', $status_key );
    if ( $status_key !== 'completed' ) {
        return $status;
    }

    $order_id = max( 0, (int) $order_id );
    if ( $order_id <= 0 || ! function_exists( 'wc_get_order' ) ) {
        return $status;
    }

    if ( ! teinvit_paid_order_auto_complete_is_payment_complete_context( $order_id ) ) {
        return $status;
    }

    if ( ! is_object( $order ) || ! method_exists( $order, 'get_id' ) || (int) $order->get_id() !== $order_id ) {
        $order = wc_get_order( $order_id );
    }

    if ( ! $order || teinvit_paid_order_auto_complete_is_manual_payment_method( $order ) ) {
        return $status;
    }

    if ( ! teinvit_paid_order_auto_complete_scope_allows_order( $order, $settings['scope'] ) ) {
        return $status;
    }

    teinvit_paid_order_auto_complete_log( 'info', 'forced_processing_until_delayed_complete', [ 'order_id' => $order_id ] );
    return 'processing';
}

function teinvit_paid_order_auto_complete_order_processed( $order ) {
    if ( ! is_object( $order ) || ! method_exists( $order, 'get_meta' ) ) {
        return false;
    }

    return (string) $order->get_meta( '_teinvit_paid_order_auto_complete_processed', true ) === '1';
}

function teinvit_paid_order_auto_complete_is_scheduled( $order_id ) {
    $order_id = max( 0, (int) $order_id );
    if ( $order_id <= 0 ) {
        return false;
    }

    $args = [ 'order_id' => $order_id ];
    if ( function_exists( 'wp_next_scheduled' ) ) {
        return (bool) wp_next_scheduled( TEINVIT_PAID_ORDER_AUTO_COMPLETE_ACTION, $args );
    }

    return false;
}

function teinvit_paid_order_auto_complete_maybe_schedule( $order_id, $source = 'unknown', $order = null ) {
    $order_id = max( 0, (int) $order_id );
    $source = sanitize_key( (string) $source );
    $settings = teinvit_paid_order_auto_complete_settings();

    if ( empty( $settings['enabled'] ) ) {
        return false;
    }

    if ( $order_id <= 0 || ! function_exists( 'wc_get_order' ) ) {
        return teinvit_paid_order_auto_complete_skip( 'missing_order', [ 'order_id' => $order_id, 'trigger_source' => $source ], 'warning' );
    }

    if ( ! is_object( $order ) || ! method_exists( $order, 'get_id' ) || (int) $order->get_id() !== $order_id ) {
        $order = wc_get_order( $order_id );
    }

    if ( ! $order ) {
        return teinvit_paid_order_auto_complete_skip( 'missing_order', [ 'order_id' => $order_id, 'trigger_source' => $source ], 'warning' );
    }

    if ( teinvit_paid_order_auto_complete_order_processed( $order ) ) {
        return teinvit_paid_order_auto_complete_skip( 'already_processed', [ 'order_id' => $order_id, 'trigger_source' => $source ] );
    }

    $status = method_exists( $order, 'get_status' ) ? sanitize_key( (string) $order->get_status() ) : '';
    if ( $status === 'completed' ) {
        return teinvit_paid_order_auto_complete_skip( 'already_completed', [ 'order_id' => $order_id, 'trigger_source' => $source ] );
    }

    if ( $status !== 'processing' ) {
        return teinvit_paid_order_auto_complete_skip( 'wrong_status', [ 'order_id' => $order_id, 'status' => $status, 'trigger_source' => $source ] );
    }

    if ( ! teinvit_paid_order_auto_complete_order_is_paid( $order ) ) {
        return teinvit_paid_order_auto_complete_skip( 'not_paid', [ 'order_id' => $order_id, 'status' => $status, 'trigger_source' => $source ] );
    }

    if ( teinvit_paid_order_auto_complete_is_manual_payment_method( $order ) ) {
        return teinvit_paid_order_auto_complete_skip(
            'manual_payment_method',
            [
                'order_id'       => $order_id,
                'payment_method' => method_exists( $order, 'get_payment_method' ) ? sanitize_key( (string) $order->get_payment_method() ) : '',
                'trigger_source' => $source,
            ]
        );
    }

    if ( $source !== 'payment_complete' && ! teinvit_paid_order_auto_complete_has_payment_evidence( $order ) ) {
        return teinvit_paid_order_auto_complete_skip( 'not_paid', [ 'order_id' => $order_id, 'status' => $status, 'trigger_source' => $source ] );
    }

    if ( ! teinvit_paid_order_auto_complete_scope_allows_order( $order, $settings['scope'] ) ) {
        return teinvit_paid_order_auto_complete_skip( 'ineligible_scope', [ 'order_id' => $order_id, 'scope' => $settings['scope'], 'trigger_source' => $source ] );
    }

    if ( ! function_exists( 'wp_schedule_single_event' ) || ! function_exists( 'wp_next_scheduled' ) ) {
        return teinvit_paid_order_auto_complete_skip( 'wp_cron_unavailable', [ 'order_id' => $order_id, 'trigger_source' => $source ], 'error' );
    }

    if ( teinvit_paid_order_auto_complete_is_scheduled( $order_id ) ) {
        return teinvit_paid_order_auto_complete_skip( 'already_scheduled', [ 'order_id' => $order_id, 'scheduler' => 'wp_cron', 'trigger_source' => $source ] );
    }

    $timestamp = time() + max( 1, (int) $settings['delay'] );
    $args = [ 'order_id' => $order_id ];
    $scheduled = wp_schedule_single_event( $timestamp, TEINVIT_PAID_ORDER_AUTO_COMPLETE_ACTION, $args, true );

    if ( is_wp_error( $scheduled ) ) {
        if ( $scheduled->get_error_code() === 'duplicate_event' ) {
            return teinvit_paid_order_auto_complete_skip(
                'already_scheduled',
                [
                    'order_id'       => $order_id,
                    'scheduler'      => 'wp_cron',
                    'trigger_source' => $source,
                ]
            );
        }

        return teinvit_paid_order_auto_complete_skip(
            'schedule_failed',
            [
                'order_id'       => $order_id,
                'error_message'  => $scheduled->get_error_message(),
                'scheduler'      => 'wp_cron',
                'trigger_source' => $source,
            ],
            'error'
        );
    }

    if ( false === $scheduled ) {
        return teinvit_paid_order_auto_complete_skip( 'schedule_failed', [ 'order_id' => $order_id, 'scheduler' => 'wp_cron', 'trigger_source' => $source ], 'error' );
    }

    teinvit_paid_order_auto_complete_log(
        'info',
        'scheduled',
        [
            'order_id'       => $order_id,
            'scheduler'      => 'wp_cron',
            'delay'          => (int) $settings['delay'],
            'run_at_gmt'     => gmdate( 'Y-m-d H:i:s', $timestamp ),
            'timestamp'      => $timestamp,
            'trigger_source' => $source,
        ]
    );

    return true;
}

function teinvit_paid_order_auto_complete_lock_option_name( $order_id ) {
    return 'teinvit_paid_order_auto_complete_lock_' . max( 0, (int) $order_id );
}

function teinvit_paid_order_auto_complete_acquire_lock( $order_id ) {
    $order_id = max( 0, (int) $order_id );
    if ( $order_id <= 0 ) {
        return false;
    }

    $option_name = teinvit_paid_order_auto_complete_lock_option_name( $order_id );
    $existing = get_option( $option_name, [] );
    if ( is_array( $existing ) && ! empty( $existing['expires_at'] ) && (int) $existing['expires_at'] < time() ) {
        delete_option( $option_name );
    }

    return add_option(
        $option_name,
        [
            'order_id'    => $order_id,
            'created_at'  => time(),
            'expires_at'  => time() + 5 * MINUTE_IN_SECONDS,
            'request_key' => wp_generate_uuid4(),
        ],
        '',
        false
    );
}

function teinvit_paid_order_auto_complete_release_lock( $order_id ) {
    $order_id = max( 0, (int) $order_id );
    if ( $order_id <= 0 ) {
        return;
    }

    delete_option( teinvit_paid_order_auto_complete_lock_option_name( $order_id ) );
}

function teinvit_paid_order_auto_complete_run_job( $order_id ) {
    if ( is_array( $order_id ) && isset( $order_id['order_id'] ) ) {
        $order_id = $order_id['order_id'];
    }

    $order_id = max( 0, (int) $order_id );
    $settings = teinvit_paid_order_auto_complete_settings();

    if ( empty( $settings['enabled'] ) ) {
        return teinvit_paid_order_auto_complete_skip( 'disabled', [ 'order_id' => $order_id ] );
    }

    teinvit_paid_order_auto_complete_log( 'info', 'run_started', [ 'order_id' => $order_id, 'scheduler' => 'wp_cron' ] );

    if ( $order_id <= 0 || ! function_exists( 'wc_get_order' ) ) {
        return teinvit_paid_order_auto_complete_skip( 'missing_order', [ 'order_id' => $order_id ], 'warning' );
    }

    $order = wc_get_order( $order_id );
    if ( ! $order ) {
        return teinvit_paid_order_auto_complete_skip( 'missing_order', [ 'order_id' => $order_id ], 'warning' );
    }

    if ( teinvit_paid_order_auto_complete_order_processed( $order ) ) {
        return teinvit_paid_order_auto_complete_skip( 'already_processed', [ 'order_id' => $order_id ] );
    }

    $status = method_exists( $order, 'get_status' ) ? sanitize_key( (string) $order->get_status() ) : '';
    if ( $status === 'completed' ) {
        return teinvit_paid_order_auto_complete_skip( 'already_completed', [ 'order_id' => $order_id ] );
    }

    if ( $status !== 'processing' ) {
        return teinvit_paid_order_auto_complete_skip( 'wrong_status', [ 'order_id' => $order_id, 'status' => $status ] );
    }

    if ( ! teinvit_paid_order_auto_complete_order_is_paid( $order ) ) {
        return teinvit_paid_order_auto_complete_skip( 'not_paid', [ 'order_id' => $order_id, 'status' => $status ] );
    }

    if ( teinvit_paid_order_auto_complete_is_manual_payment_method( $order ) ) {
        return teinvit_paid_order_auto_complete_skip(
            'manual_payment_method',
            [
                'order_id'       => $order_id,
                'payment_method' => method_exists( $order, 'get_payment_method' ) ? sanitize_key( (string) $order->get_payment_method() ) : '',
            ]
        );
    }

    if ( ! teinvit_paid_order_auto_complete_scope_allows_order( $order, $settings['scope'] ) ) {
        return teinvit_paid_order_auto_complete_skip( 'ineligible_scope', [ 'order_id' => $order_id, 'scope' => $settings['scope'] ] );
    }

    if ( ! teinvit_paid_order_auto_complete_acquire_lock( $order_id ) ) {
        return teinvit_paid_order_auto_complete_skip( 'already_locked', [ 'order_id' => $order_id ] );
    }

    try {
        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return teinvit_paid_order_auto_complete_skip( 'missing_order', [ 'order_id' => $order_id ], 'warning' );
        }

        if ( teinvit_paid_order_auto_complete_order_processed( $order ) ) {
            return teinvit_paid_order_auto_complete_skip( 'already_processed', [ 'order_id' => $order_id ] );
        }

        $status = method_exists( $order, 'get_status' ) ? sanitize_key( (string) $order->get_status() ) : '';
        if ( $status === 'completed' ) {
            return teinvit_paid_order_auto_complete_skip( 'already_completed', [ 'order_id' => $order_id ] );
        }

        if ( $status !== 'processing' ) {
            return teinvit_paid_order_auto_complete_skip( 'wrong_status', [ 'order_id' => $order_id, 'status' => $status ] );
        }

        if ( ! teinvit_paid_order_auto_complete_order_is_paid( $order ) ) {
            return teinvit_paid_order_auto_complete_skip( 'not_paid', [ 'order_id' => $order_id, 'status' => $status ] );
        }

        if ( teinvit_paid_order_auto_complete_is_manual_payment_method( $order ) ) {
            return teinvit_paid_order_auto_complete_skip(
                'manual_payment_method',
                [
                    'order_id'       => $order_id,
                    'payment_method' => method_exists( $order, 'get_payment_method' ) ? sanitize_key( (string) $order->get_payment_method() ) : '',
                ]
            );
        }

        if ( ! teinvit_paid_order_auto_complete_scope_allows_order( $order, $settings['scope'] ) ) {
            return teinvit_paid_order_auto_complete_skip( 'ineligible_scope', [ 'order_id' => $order_id, 'scope' => $settings['scope'] ] );
        }

        $updated = $order->update_status(
            'completed',
            'TeInvit: paid order auto-completed after delay to trigger invitation generation.'
        );

        if ( ! $updated ) {
            return teinvit_paid_order_auto_complete_skip( 'update_status_failed', [ 'order_id' => $order_id ], 'error' );
        }

        $order = wc_get_order( $order_id );
        if ( $order ) {
            $order->update_meta_data( '_teinvit_paid_order_auto_complete_processed', '1' );
            $order->update_meta_data( '_teinvit_paid_order_auto_complete_processed_at', current_time( 'mysql' ) );
            $order->update_meta_data( '_teinvit_paid_order_auto_complete_delay', (int) $settings['delay'] );
            $order->save();
        }

        teinvit_paid_order_auto_complete_log( 'info', 'auto_completed', [ 'order_id' => $order_id ] );
        return true;
    } finally {
        teinvit_paid_order_auto_complete_release_lock( $order_id );
    }
}

function teinvit_paid_order_auto_complete_handle_payment_complete( $order_id, $transaction_id = '' ) {
    $order = function_exists( 'wc_get_order' ) ? wc_get_order( (int) $order_id ) : null;
    teinvit_paid_order_auto_complete_maybe_schedule( (int) $order_id, 'payment_complete', $order );
    teinvit_paid_order_auto_complete_unmark_payment_complete( (int) $order_id );
}

function teinvit_paid_order_auto_complete_handle_payment_status_changed( $order_id, $order = null ) {
    teinvit_paid_order_auto_complete_maybe_schedule( (int) $order_id, 'payment_status_changed', $order );
}

function teinvit_paid_order_auto_complete_handle_processing_status( $order_id, $order = null ) {
    teinvit_paid_order_auto_complete_maybe_schedule( (int) $order_id, 'status_processing', $order );
}

function teinvit_paid_order_auto_complete_handle_settings_save() {
    if ( ! current_user_can( teinvit_paid_order_auto_complete_admin_capability() ) ) {
        wp_die( 'Unauthorized' );
    }

    check_admin_referer( 'teinvit_paid_order_auto_complete_settings' );

    $raw_settings = isset( $_POST['teinvit_paid_order_auto_complete'] )
        ? wp_unslash( $_POST['teinvit_paid_order_auto_complete'] )
        : [];
    $settings = teinvit_paid_order_auto_complete_sanitize_settings( is_array( $raw_settings ) ? $raw_settings : [] );

    update_option( TEINVIT_PAID_ORDER_AUTO_COMPLETE_OPTION, $settings, false );

    wp_safe_redirect( add_query_arg( 'teinvit_settings_saved', '1', admin_url( 'admin.php?page=teinvit-settings' ) ) );
    exit;
}

function teinvit_paid_order_auto_complete_render_settings_section() {
    $settings = teinvit_paid_order_auto_complete_settings();
    $delays = teinvit_paid_order_auto_complete_allowed_delays();
    $scopes = teinvit_paid_order_auto_complete_allowed_scopes();

    echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
    echo '<input type="hidden" name="action" value="teinvit_save_paid_order_auto_complete_settings">';
    wp_nonce_field( 'teinvit_paid_order_auto_complete_settings' );

    echo '<h2>Paid order auto-complete</h2>';
    echo '<p>Completes paid Processing orders after a short delay so the existing TeInvit Completed pipeline can run outside the payment callback request.</p>';
    echo '<table class="form-table" role="presentation"><tbody>';

    echo '<tr><th scope="row">Auto-complete paid TeInvit orders</th><td>';
    echo '<label><input type="checkbox" name="teinvit_paid_order_auto_complete[enabled]" value="1" ' . checked( $settings['enabled'], '1', false ) . '> Enable delayed auto-complete</label>';
    echo '<p class="description">Default is disabled. When enabled, TeInvit schedules a delayed completion job for eligible paid orders.</p>';
    echo '</td></tr>';

    echo '<tr><th scope="row"><label for="teinvit_paid_order_auto_complete_delay">Delay before completing paid orders</label></th><td>';
    echo '<select id="teinvit_paid_order_auto_complete_delay" name="teinvit_paid_order_auto_complete[delay]">';
    foreach ( $delays as $delay ) {
        echo '<option value="' . esc_attr( (string) $delay ) . '" ' . selected( (int) $settings['delay'], (int) $delay, false ) . '>' . esc_html( (string) $delay ) . ' seconds</option>';
    }
    echo '</select>';
    echo '<p class="description">Default is 10 seconds. Higher values should be used only for gateway timing issues.</p>';
    echo '</td></tr>';

    echo '<tr><th scope="row"><label for="teinvit_paid_order_auto_complete_scope">Scope</label></th><td>';
    echo '<select id="teinvit_paid_order_auto_complete_scope" name="teinvit_paid_order_auto_complete[scope]">';
    foreach ( $scopes as $scope_key => $scope_label ) {
        echo '<option value="' . esc_attr( $scope_key ) . '" ' . selected( $settings['scope'], $scope_key, false ) . '>' . esc_html( $scope_label ) . '</option>';
    }
    echo '</select>';
    echo '<p class="description">Only eligible TeInvit orders requires every line item to be a configured TeInvit Basic, Premium, or addon product.</p>';
    echo '</td></tr>';

    echo '<tr><th scope="row">Debug logging</th><td>';
    echo '<label><input type="checkbox" name="teinvit_paid_order_auto_complete[debug_logging]" value="1" ' . checked( $settings['debug_logging'], '1', false ) . '> Log scheduled jobs and skipped attempts to WooCommerce logs</label>';
    echo '<p class="description">Skip reasons are written to the WooCommerce logger, not to Order Notes.</p>';
    echo '</td></tr>';

    echo '</tbody></table>';
    submit_button( 'Save TeInvit settings' );
    echo '</form>';
}

add_action( 'woocommerce_pre_payment_complete', 'teinvit_paid_order_auto_complete_mark_payment_complete', 1, 2 );
add_filter( 'woocommerce_payment_complete_order_status', 'teinvit_paid_order_auto_complete_filter_payment_complete_status', 99, 3 );
add_action( 'woocommerce_payment_complete', 'teinvit_paid_order_auto_complete_handle_payment_complete', 20, 2 );
add_action( 'woocommerce_order_payment_status_changed', 'teinvit_paid_order_auto_complete_handle_payment_status_changed', 20, 2 );
add_action( 'woocommerce_order_status_processing', 'teinvit_paid_order_auto_complete_handle_processing_status', 30, 2 );
add_action( TEINVIT_PAID_ORDER_AUTO_COMPLETE_ACTION, 'teinvit_paid_order_auto_complete_run_job', 10, 1 );
add_action( 'admin_post_teinvit_save_paid_order_auto_complete_settings', 'teinvit_paid_order_auto_complete_handle_settings_save' );
