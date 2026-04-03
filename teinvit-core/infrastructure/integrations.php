<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function teinvit_integrations_registry() {
    $providers = [
        'newsman' => [
            'label' => 'Newsman',
            'description' => 'Newsman email marketing provider',
            'default_config' => [
                'base_url' => 'https://ssl.newsman.com',
                'api_version' => 'api/1.2',
                'user_id' => '',
                'api_key' => '',
                'list_id' => '',
                'segment_id' => '',
                'double_optin' => 0,
                'timeout' => 20,
            ],
            'actions' => [
                'test_connection' => 'teinvit_newsman_provider_test_connection',
                'get_segments' => 'teinvit_newsman_provider_get_segments',
                'subscribe' => 'teinvit_newsman_provider_subscribe',
                'unsubscribe' => 'teinvit_newsman_provider_unsubscribe',
            ],
        ],
    ];

    return apply_filters( 'teinvit_integrations_registry', $providers );
}

function teinvit_integrations_get_provider( $provider_key ) {
    $provider_key = sanitize_key( (string) $provider_key );
    $providers = teinvit_integrations_registry();
    return isset( $providers[ $provider_key ] ) ? $providers[ $provider_key ] : null;
}

function teinvit_integrations_get_state( $provider_key ) {
    global $wpdb;

    $provider_key = sanitize_key( (string) $provider_key );
    $provider = teinvit_integrations_get_provider( $provider_key );
    if ( ! is_array( $provider ) ) {
        return null;
    }

    $tables = teinvit_db_tables();
    $row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$tables['integrations']} WHERE provider_key=%s LIMIT 1", $provider_key ), ARRAY_A );

    $defaults = [
        'provider_key' => $provider_key,
        'enabled' => 0,
        'config' => [],
        'last_status' => 'never',
        'last_error' => '',
        'last_tested_at' => null,
    ];

    if ( ! is_array( $row ) ) {
        $defaults['config'] = $provider['default_config'] ?? [];
        return $defaults;
    }

    $config = json_decode( (string) ( $row['config'] ?? '' ), true );
    if ( ! is_array( $config ) ) {
        $config = [];
    }

    $state = [
        'provider_key' => $provider_key,
        'enabled' => ! empty( $row['enabled'] ) ? 1 : 0,
        'config' => wp_parse_args( $config, (array) ( $provider['default_config'] ?? [] ) ),
        'last_status' => (string) ( $row['last_status'] ?? 'never' ),
        'last_error' => (string) ( $row['last_error'] ?? '' ),
        'last_tested_at' => $row['last_tested_at'] ?? null,
    ];

    return $state;
}

function teinvit_integrations_save_state( $provider_key, array $state ) {
    global $wpdb;

    $provider_key = sanitize_key( (string) $provider_key );
    $provider = teinvit_integrations_get_provider( $provider_key );
    if ( ! is_array( $provider ) ) {
        return false;
    }

    $tables = teinvit_db_tables();
    $current = teinvit_integrations_get_state( $provider_key );
    $now = current_time( 'mysql' );

    $next_config = wp_parse_args( (array) ( $state['config'] ?? [] ), (array) ( $current['config'] ?? [] ) );

    $payload = [
        'provider_key' => $provider_key,
        'enabled' => ! empty( $state['enabled'] ) ? 1 : 0,
        'config' => wp_json_encode( $next_config ),
        'last_status' => sanitize_key( (string) ( $state['last_status'] ?? ( $current['last_status'] ?? 'never' ) ) ),
        'last_error' => sanitize_textarea_field( (string) ( $state['last_error'] ?? ( $current['last_error'] ?? '' ) ) ),
        'last_tested_at' => isset( $state['last_tested_at'] ) ? $state['last_tested_at'] : ( $current['last_tested_at'] ?? null ),
        'updated_at' => $now,
        'created_at' => $current['created_at'] ?? $now,
    ];

    return false !== $wpdb->replace( $tables['integrations'], $payload );
}

function teinvit_integrations_is_enabled( $provider_key ) {
    $state = teinvit_integrations_get_state( $provider_key );
    return is_array( $state ) && ! empty( $state['enabled'] );
}

function teinvit_integrations_run_action( $provider_key, $action, array $payload = [] ) {
    $provider_key = sanitize_key( (string) $provider_key );
    $action = sanitize_key( (string) $action );

    $provider = teinvit_integrations_get_provider( $provider_key );
    if ( ! is_array( $provider ) ) {
        return new WP_Error( 'provider_missing', 'Unknown integration provider.' );
    }

    $state = teinvit_integrations_get_state( $provider_key );
    $callable = $provider['actions'][ $action ] ?? null;
    if ( ! is_string( $callable ) || ! function_exists( $callable ) ) {
        return new WP_Error( 'provider_action_missing', 'Provider action is not available.' );
    }

    if ( ! in_array( $action, [ 'test_connection', 'get_segments' ], true ) && empty( $state['enabled'] ) ) {
        return new WP_Error( 'provider_disabled', 'Provider is disabled.' );
    }

    $result = call_user_func( $callable, $state, $payload );

    $ok = ! is_wp_error( $result );
    teinvit_integrations_save_state(
        $provider_key,
        [
            'enabled' => $state['enabled'] ?? 0,
            'config' => $state['config'] ?? [],
            'last_status' => $ok ? 'ok' : 'error',
            'last_error' => $ok ? '' : $result->get_error_message(),
            'last_tested_at' => $action === 'test_connection' ? current_time( 'mysql' ) : ( $state['last_tested_at'] ?? null ),
        ]
    );

    return $result;
}

function teinvit_integrations_active_provider_keys() {
    $providers = teinvit_integrations_registry();
    $active = [];

    foreach ( array_keys( $providers ) as $provider_key ) {
        if ( teinvit_integrations_is_enabled( $provider_key ) ) {
            $active[] = $provider_key;
        }
    }

    return $active;
}
