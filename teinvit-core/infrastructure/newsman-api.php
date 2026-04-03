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
        'segment_id'   => sanitize_text_field( (string) ( $config['segment_id'] ?? '' ) ),
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

function teinvit_newsman_request( array $config, $endpoint, array $payload, $http_method = 'POST' ) {
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

    $http_method = strtoupper( sanitize_text_field( (string) $http_method ) );
    if ( $http_method === '' ) {
        $http_method = 'POST';
    }

    $request_args = [
        'timeout' => $config['timeout'],
        'headers' => [ 'Content-Type' => 'application/json' ],
        'method'  => $http_method,
    ];

    if ( $http_method === 'GET' ) {
        $url = add_query_arg( $payload, $url );
    } else {
        $request_args['body'] = wp_json_encode( $payload );
    }

    $response = wp_remote_request( $url, $request_args );

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

    $options = [ 'source' => 'teinvit' ];
    if ( ! empty( $config['double_optin'] ) && ! empty( $config['segment_id'] ) ) {
        $options['segments'] = [ (string) $config['segment_id'] ];
    }

    $payload = [
        'list_id'   => $config['list_id'],
        'email'     => $email,
        'firstname' => sanitize_text_field( (string) ( $args['firstname'] ?? '' ) ),
        'lastname'  => sanitize_text_field( (string) ( $args['lastname'] ?? '' ) ),
        'ip'        => sanitize_text_field( (string) ( $_SERVER['REMOTE_ADDR'] ?? '' ) ),
        'props'     => $props,
        'options'   => $options,
    ];

    $endpoint = ! empty( $config['double_optin'] ) ? 'subscriber.initSubscribe' : 'subscriber.saveSubscribe';
    $subscribe_result = teinvit_newsman_request( $config, $endpoint, $payload );
    if ( is_wp_error( $subscribe_result ) ) {
        return $subscribe_result;
    }

    if ( ! empty( $config['segment_id'] ) && empty( $config['double_optin'] ) ) {
        $segment_result = teinvit_newsman_attach_segment( $config, $subscribe_result, $email );
        if ( is_wp_error( $segment_result ) ) {
            return $segment_result;
        }
    }

    return $subscribe_result;
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

function teinvit_newsman_provider_get_segments( array $state, array $payload = [] ) {
    $config = teinvit_newsman_normalize_config( (array) ( $state['config'] ?? [] ) );
    if ( ! teinvit_newsman_is_configured( $config ) ) {
        return new WP_Error( 'newsman_not_configured', 'Missing Newsman credentials.' );
    }

    if ( empty( $config['list_id'] ) ) {
        return new WP_Error( 'newsman_missing_list', 'Newsman list ID is required before loading segments.' );
    }

    $result = teinvit_newsman_request(
        $config,
        'segment.all',
        [ 'list_id' => $config['list_id'] ],
        'GET'
    );
    if ( ! is_wp_error( $result ) ) {
        return teinvit_newsman_normalize_segments( $result );
    }

    $fallback = teinvit_newsman_request(
        $config,
        'list.allSegments',
        [ 'list_id' => $config['list_id'] ]
    );
    if ( is_wp_error( $fallback ) ) {
        return $result;
    }

    return teinvit_newsman_normalize_segments( $fallback );
}

function teinvit_newsman_extract_subscriber_id( $subscribe_result ) {
    if ( is_numeric( $subscribe_result ) ) {
        return (string) $subscribe_result;
    }

    if ( is_array( $subscribe_result ) ) {
        $keys = [ 'subscriber_id', 'subscriberId', 'id' ];
        foreach ( $keys as $key ) {
            if ( isset( $subscribe_result[ $key ] ) && (string) $subscribe_result[ $key ] !== '' ) {
                return (string) $subscribe_result[ $key ];
            }
        }
        if ( isset( $subscribe_result['data'] ) && is_array( $subscribe_result['data'] ) ) {
            foreach ( $keys as $key ) {
                if ( isset( $subscribe_result['data'][ $key ] ) && (string) $subscribe_result['data'][ $key ] !== '' ) {
                    return (string) $subscribe_result['data'][ $key ];
                }
            }
        }
    }

    return '';
}

function teinvit_newsman_find_subscriber_id_by_email( array $config, $email ) {
    $email = sanitize_email( $email );
    if ( $email === '' ) {
        return '';
    }

    $result = teinvit_newsman_request(
        $config,
        'subscriber.getByEmail',
        [
            'list_id' => (string) $config['list_id'],
            'email'   => $email,
        ],
        'GET'
    );
    if ( is_wp_error( $result ) ) {
        return '';
    }

    return teinvit_newsman_extract_subscriber_id( $result );
}

function teinvit_newsman_normalize_segments( $raw_segments ) {
    $rows = [];

    if ( is_array( $raw_segments ) ) {
        if ( isset( $raw_segments[0] ) && is_array( $raw_segments[0] ) ) {
            $rows = $raw_segments;
        } elseif ( isset( $raw_segments['segments'] ) && is_array( $raw_segments['segments'] ) ) {
            $rows = $raw_segments['segments'];
        } else {
            foreach ( $raw_segments as $value ) {
                if ( is_array( $value ) && isset( $value['segment_id'] ) ) {
                    $rows[] = $value;
                }
            }
        }
    }

    $normalized = [];
    foreach ( $rows as $row ) {
        if ( ! is_array( $row ) ) {
            continue;
        }
        $segment_id = sanitize_text_field( (string) ( $row['segment_id'] ?? $row['id'] ?? '' ) );
        if ( $segment_id === '' ) {
            continue;
        }
        $segment_name = sanitize_text_field( (string) ( $row['segment_name'] ?? $row['name'] ?? $row['title'] ?? $segment_id ) );
        $normalized[] = [
            'segment_id'   => $segment_id,
            'segment_name' => $segment_name,
        ];
    }

    return $normalized;
}

function teinvit_newsman_attach_segment( array $config, $subscribe_result, $email = '' ) {
    $subscriber_id = teinvit_newsman_extract_subscriber_id( $subscribe_result );
    if ( $subscriber_id === '' ) {
        $subscriber_id = teinvit_newsman_find_subscriber_id_by_email( $config, (string) $email );
    }
    if ( $subscriber_id === '' ) {
        return new WP_Error( 'newsman_missing_subscriber_id', 'Could not determine Newsman subscriber id for segment mapping (saveSubscribe response did not include it and getByEmail fallback failed).' );
    }

    return teinvit_newsman_request(
        $config,
        'segment.addSubscriber',
        [
            'list_id' => $config['list_id'],
            'segment_id' => sanitize_text_field( (string) $config['segment_id'] ),
            'subscriber_id' => $subscriber_id,
        ]
    );
}
