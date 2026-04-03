<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function teinvit_newsman_normalize_config( array $config ) {
    return [
        'base_url'     => untrailingslashit( esc_url_raw( (string) ( $config['base_url'] ?? '' ) ) ),
        'api_version'  => trim( sanitize_text_field( (string) ( $config['api_version'] ?? '' ) ), '/' ),
        'user_id'      => sanitize_text_field( (string) ( $config['user_id'] ?? '' ) ),
        'api_key'      => sanitize_text_field( (string) ( $config['api_key'] ?? '' ) ),
        'list_id'      => sanitize_text_field( (string) ( $config['list_id'] ?? '' ) ),
        'double_optin' => ! empty( $config['double_optin'] ) ? 1 : 0,
        'timeout'      => max( 5, (int) ( $config['timeout'] ?? 20 ) ),
    ];
}

function teinvit_newsman_is_configured( array $config ) {
    return ( $config['base_url'] ?? '' ) !== '' &&
        ( $config['api_version'] ?? '' ) !== '' &&
        ( $config['user_id'] ?? '' ) !== '' &&
        ( $config['api_key'] ?? '' ) !== '' &&
        ( $config['list_id'] ?? '' ) !== '';
}

function teinvit_newsman_request( array $config, $endpoint, array $payload ) {
    $config = teinvit_newsman_normalize_config( $config );
    if ( ! teinvit_newsman_is_configured( $config ) ) {
        return new WP_Error( 'newsman_not_configured', 'TeInvit Newsman API config is missing or disabled.' );
    }

    $endpoint = sanitize_text_field( (string) $endpoint );
    if ( $endpoint === '' ) {
        return new WP_Error( 'newsman_invalid_endpoint', 'Missing Newsman endpoint.' );
    }

    $url = sprintf(
        '%s/%s/rest/%s/%s/%s.json',
        $config['base_url'],
        $config['api_version'],
        rawurlencode( $config['user_id'] ),
        rawurlencode( $config['api_key'] ),
        rawurlencode( $endpoint )
    );

    $response = wp_remote_post(
        $url,
        [
            'timeout' => $config['timeout'],
            'headers' => [ 'Content-Type' => 'application/json' ],
            'body'    => wp_json_encode( $payload ),
        ]
    );

    if ( is_wp_error( $response ) ) {
        return $response;
    }

    $status = (int) wp_remote_retrieve_response_code( $response );
    $raw    = (string) wp_remote_retrieve_body( $response );
    $json   = json_decode( $raw, true );

    if ( $status < 200 || $status >= 300 ) {
        return new WP_Error( 'newsman_http_error', 'Newsman API HTTP error: ' . $status, [ 'status' => $status, 'body' => $raw ] );
    }

    if ( is_array( $json ) && isset( $json['error_message'] ) && $json['error_message'] !== '' ) {
        return new WP_Error( 'newsman_api_error', (string) $json['error_message'], $json );
    }

    return is_array( $json ) ? $json : [ 'raw' => $raw ];
}

function teinvit_newsman_subscribe_contact( array $config, $email, array $args = [] ) {
    $config = teinvit_newsman_normalize_config( $config );
    $email  = sanitize_email( $email );
    if ( $email === '' ) {
        return new WP_Error( 'newsman_invalid_email', 'Invalid email for subscribe.' );
    }

    $phone = sanitize_text_field( (string) ( $args['phone'] ?? '' ) );
    $token = sanitize_text_field( (string) ( $args['token'] ?? '' ) );

    $props = [
        'source'                => 'teinvit_rsvp',
        'teinvit_source_token'  => $token,
    ];
    if ( $phone !== '' ) {
        $props['phone'] = $phone;
        $props['tel'] = $phone;
        $props['telephone'] = $phone;
    }

    $payload = [
        'list_id'   => $config['list_id'],
        'email'     => $email,
        'firstname' => sanitize_text_field( (string) ( $args['firstname'] ?? '' ) ),
        'lastname'  => sanitize_text_field( (string) ( $args['lastname'] ?? '' ) ),
        'ip'        => sanitize_text_field( (string) ( $_SERVER['REMOTE_ADDR'] ?? '' ) ),
        'props'     => $props,
        'options'   => [ 'source' => 'teinvit' ],
    ];

    $endpoint = ! empty( $config['double_optin'] ) ? 'subscriber.initSubscribe' : 'subscriber.saveSubscribe';
    return teinvit_newsman_request( $config, $endpoint, $payload );
}

function teinvit_newsman_unsubscribe_contact( array $config, $email ) {
    $config = teinvit_newsman_normalize_config( $config );
    $email  = sanitize_email( $email );
    if ( $email === '' ) {
        return new WP_Error( 'newsman_invalid_email', 'Invalid email for unsubscribe.' );
    }

    return teinvit_newsman_request(
        $config,
        'subscriber.saveUnsubscribe',
        [
            'list_id' => $config['list_id'],
            'email'   => $email,
            'ip'      => sanitize_text_field( (string) ( $_SERVER['REMOTE_ADDR'] ?? '' ) ),
        ]
    );
}

function teinvit_newsman_provider_subscribe( array $state, array $payload ) {
    $config = (array) ( $state['config'] ?? [] );
    return teinvit_newsman_subscribe_contact( $config, (string) ( $payload['email'] ?? '' ), $payload );
}

function teinvit_newsman_provider_unsubscribe( array $state, array $payload ) {
    $config = (array) ( $state['config'] ?? [] );
    return teinvit_newsman_unsubscribe_contact( $config, (string) ( $payload['email'] ?? '' ) );
}

function teinvit_newsman_provider_test_connection( array $state, array $payload = [] ) {
    $config = (array) ( $state['config'] ?? [] );
    $config = teinvit_newsman_normalize_config( $config );

    if ( ! teinvit_newsman_is_configured( $config ) ) {
        return new WP_Error( 'newsman_not_configured', 'Missing Newsman credentials.' );
    }

    return teinvit_newsman_request(
        $config,
        'list.all',
        []
    );
}
