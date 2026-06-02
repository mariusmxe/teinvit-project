<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function teinvit_marketing_normalize_phone( $phone ) {
    $phone = trim( (string) $phone );
    if ( $phone === '' ) {
        return '';
    }

    return sanitize_text_field( preg_replace( '/\s+/', '', $phone ) );
}

function teinvit_marketing_get_latest_non_empty_phone( $email ) {
    global $wpdb;

    $email = sanitize_email( $email );
    if ( $email === '' ) {
        return '';
    }

    $tables = teinvit_db_tables();
    $raw = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT guest_phone FROM {$tables['rsvp']} WHERE guest_email=%s AND guest_phone<>'' ORDER BY id DESC LIMIT 1",
            $email
        )
    );

    return teinvit_marketing_normalize_phone( (string) $raw );
}

function teinvit_marketing_get_contact( $email ) {
    global $wpdb;

    $email = sanitize_email( $email );
    if ( $email === '' ) {
        return null;
    }

    $tables = teinvit_db_tables();
    return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$tables['marketing_contacts']} WHERE email=%s LIMIT 1", $email ), ARRAY_A );
}

function teinvit_marketing_upsert_contact( $email, array $changes ) {
    global $wpdb;

    $email = sanitize_email( $email );
    if ( $email === '' ) {
        return;
    }

    $tables   = teinvit_db_tables();
    $existing = teinvit_marketing_get_contact( $email );
    $now      = current_time( 'mysql' );

    $row = [
        'email'                  => $email,
        'email_hash'             => hash( 'sha256', strtolower( $email ) ),
        'first_name'             => (string) ( $existing['first_name'] ?? '' ),
        'last_name'              => (string) ( $existing['last_name'] ?? '' ),
        'phone'                  => (string) ( $existing['phone'] ?? '' ),
        'gdpr_accepted'          => (int) ( $existing['gdpr_accepted'] ?? 0 ),
        'marketing_consent'      => (int) ( $existing['marketing_consent'] ?? 0 ),
        'suppression_active'     => (int) ( $existing['suppression_active'] ?? 0 ),
        'subscription_status'    => (string) ( $existing['subscription_status'] ?? 'consent_incomplete' ),
        'source_token'           => (string) ( $existing['source_token'] ?? '' ),
        'source_event'           => (string) ( $existing['source_event'] ?? '' ),
        'last_subscribed_at'     => $existing['last_subscribed_at'] ?? null,
        'last_unsubscribed_at'   => $existing['last_unsubscribed_at'] ?? null,
        'last_resubscribed_at'   => $existing['last_resubscribed_at'] ?? null,
        'last_consent_updated_at'=> $existing['last_consent_updated_at'] ?? null,
        'last_newsman_sync_at'   => $existing['last_newsman_sync_at'] ?? null,
        'last_newsman_sync_status' => (string) ( $existing['last_newsman_sync_status'] ?? 'none' ),
        'last_newsman_error'     => (string) ( $existing['last_newsman_error'] ?? '' ),
        'created_at'             => $existing['created_at'] ?? $now,
        'updated_at'             => $now,
    ];

    foreach ( $changes as $key => $value ) {
        if ( ! array_key_exists( $key, $row ) ) {
            continue;
        }

        if ( $key === 'phone' ) {
            $value = teinvit_marketing_normalize_phone( (string) $value );
            if ( $value === '' ) {
                continue;
            }
        }
        if ( in_array( $key, [ 'first_name', 'last_name' ], true ) ) {
            $value = sanitize_text_field( (string) $value );
            if ( $value === '' ) {
                continue;
            }
        }

        if ( in_array( $key, [ 'gdpr_accepted', 'marketing_consent', 'suppression_active' ], true ) ) {
            $value = ! empty( $value ) ? 1 : 0;
        }

        $row[ $key ] = $value;
    }

    $result = $wpdb->replace( $tables['marketing_contacts'], $row );
    if ( false === $result ) {
        teinvit_email_log( 'error', 'marketing_upsert_failed', [ 'recipient_email' => $email ] );
    }
}

function teinvit_marketing_backfill_contact_names_from_rsvp( $limit = 500 ) {
    global $wpdb;

    $limit = max( 1, (int) $limit );
    $tables = teinvit_db_tables();

    $rows = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT mc.email
             FROM {$tables['marketing_contacts']} mc
             WHERE (mc.first_name='' OR mc.last_name='')
             ORDER BY mc.id DESC
             LIMIT %d",
            $limit
        ),
        ARRAY_A
    );
    if ( ! is_array( $rows ) || empty( $rows ) ) {
        return 0;
    }

    $updated = 0;
    foreach ( $rows as $row ) {
        $email = sanitize_email( (string) ( $row['email'] ?? '' ) );
        if ( $email === '' ) {
            continue;
        }

        $rsvp = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT guest_first_name, guest_last_name
                 FROM {$tables['rsvp']}
                 WHERE guest_email=%s
                 AND (guest_first_name<>'' OR guest_last_name<>'')
                 ORDER BY id DESC
                 LIMIT 1",
                $email
            ),
            ARRAY_A
        );
        if ( ! is_array( $rsvp ) ) {
            continue;
        }

        teinvit_marketing_upsert_contact(
            $email,
            [
                'first_name' => sanitize_text_field( (string) ( $rsvp['guest_first_name'] ?? '' ) ),
                'last_name'  => sanitize_text_field( (string) ( $rsvp['guest_last_name'] ?? '' ) ),
            ]
        );
        $updated++;
    }

    return $updated;
}

function teinvit_marketing_log_event( array $event ) {
    global $wpdb;

    $email = sanitize_email( (string) ( $event['email'] ?? '' ) );
    if ( $email === '' ) {
        return;
    }

    $tables = teinvit_db_tables();
    $wpdb->insert(
        $tables['consent_journal'],
        [
            'email'      => $email,
            'email_hash' => hash( 'sha256', strtolower( $email ) ),
            'phone'      => teinvit_marketing_normalize_phone( (string) ( $event['phone'] ?? '' ) ),
            'token'      => sanitize_text_field( (string) ( $event['token'] ?? '' ) ),
            'source'     => sanitize_text_field( (string) ( $event['source'] ?? '' ) ),
            'action'     => sanitize_key( (string) ( $event['action'] ?? '' ) ),
            'status'     => sanitize_key( (string) ( $event['status'] ?? '' ) ),
            'user_id'    => null,
            'context'    => wp_json_encode( is_array( $event['context'] ?? null ) ? $event['context'] : [] ),
            'created_at' => current_time( 'mysql' ),
        ]
    );
}

function teinvit_marketing_sync_newsman_subscribe( $email, array $args = [] ) {
    $result = teinvit_integrations_run_action(
        'newsman',
        'subscribe',
        array_merge(
            $args,
            [
                'email' => sanitize_email( $email ),
            ]
        )
    );
    $ok = ! is_wp_error( $result );

    teinvit_marketing_upsert_contact(
        $email,
        [
            'last_newsman_sync_at'     => current_time( 'mysql' ),
            'last_newsman_sync_status' => $ok ? 'ok' : 'error',
            'last_newsman_error'       => $ok ? '' : (string) $result->get_error_message(),
        ]
    );

    return $result;
}

function teinvit_marketing_sync_newsman_unsubscribe( $email ) {
    $result = teinvit_integrations_run_action(
        'newsman',
        'unsubscribe',
        [
            'email' => sanitize_email( $email ),
        ]
    );
    $ok = ! is_wp_error( $result );

    teinvit_marketing_upsert_contact(
        $email,
        [
            'last_newsman_sync_at'     => current_time( 'mysql' ),
            'last_newsman_sync_status' => $ok ? 'ok' : 'error',
            'last_newsman_error'       => $ok ? '' : (string) $result->get_error_message(),
        ]
    );

    return $result;
}

add_action(
    'teinvit_rsvp_saved',
    function( $token, $rsvp_id, $payload ) {
        $payload = is_array( $payload ) ? $payload : [];
        $email   = sanitize_email( (string) ( $payload['guest_email'] ?? '' ) );
        if ( $email === '' ) {
            return;
        }

        $gdpr      = ! empty( $payload['gdpr_accepted'] );
        $marketing = ! empty( $payload['marketing_consent'] );
        $eligible  = $gdpr && $marketing;
        $phone     = teinvit_marketing_normalize_phone( (string) ( $payload['guest_phone'] ?? '' ) );
        $phone     = $phone !== '' ? $phone : teinvit_marketing_get_latest_non_empty_phone( $email );
        $token     = sanitize_text_field( (string) $token );

        teinvit_marketing_upsert_contact(
            $email,
            [
                'phone'                   => $phone,
                'first_name'              => sanitize_text_field( (string) ( $payload['guest_first_name'] ?? '' ) ),
                'last_name'               => sanitize_text_field( (string) ( $payload['guest_last_name'] ?? '' ) ),
                'gdpr_accepted'           => $gdpr ? 1 : 0,
                'marketing_consent'       => $marketing ? 1 : 0,
                'source_token'            => $token,
                'source_event'            => 'rsvp_saved',
                'last_consent_updated_at' => current_time( 'mysql' ),
            ]
        );

        if ( ! $eligible ) {
            teinvit_marketing_upsert_contact( $email, [ 'subscription_status' => 'consent_incomplete' ] );
            teinvit_marketing_log_event(
                [
                    'email'  => $email,
                    'phone'  => $phone,
                    'token'  => $token,
                    'source' => 'rsvp_saved',
                    'action' => 'consent_updated',
                    'status' => 'consent_incomplete',
                    'context' => [
                        'rsvp_id' => (int) $rsvp_id,
                        'gdpr_accepted' => $gdpr ? 1 : 0,
                        'marketing_consent' => $marketing ? 1 : 0,
                    ],
                ]
            );
            return;
        }

        $contact = teinvit_marketing_get_contact( $email );
        $was_unsubscribed = is_array( $contact ) && ( $contact['subscription_status'] ?? '' ) === 'unsubscribed';
        $was_suppressed   = function_exists( 'teinvit_email_is_suppressed' ) ? teinvit_email_is_suppressed( $email, 'marketing' ) : false;

        if ( $was_suppressed && function_exists( 'teinvit_email_remove_suppression' ) ) {
            teinvit_email_remove_suppression( $email, 'marketing', 'rsvp_resubscribe' );
        }

        $action = ( $was_unsubscribed || $was_suppressed ) ? 'resubscribed' : 'subscribed';
        $now    = current_time( 'mysql' );

        teinvit_marketing_upsert_contact(
            $email,
            [
                'subscription_status'   => 'subscribed',
                'suppression_active'    => 0,
                'last_subscribed_at'    => $now,
                'last_resubscribed_at'  => $action === 'resubscribed' ? $now : ( is_array( $contact ) ? ( $contact['last_resubscribed_at'] ?? null ) : null ),
            ]
        );

        $sync_result = teinvit_marketing_sync_newsman_subscribe(
            $email,
            [
                'phone'     => $phone,
                'token'     => $token,
                'firstname' => sanitize_text_field( (string) ( $payload['guest_first_name'] ?? '' ) ),
                'lastname'  => sanitize_text_field( (string) ( $payload['guest_last_name'] ?? '' ) ),
            ]
        );

        teinvit_marketing_log_event(
            [
                'email'  => $email,
                'phone'  => $phone,
                'token'  => $token,
                'source' => 'rsvp_saved',
                'action' => $action,
                'status' => is_wp_error( $sync_result ) ? 'newsman_sync_error' : 'newsman_sync_ok',
                'context' => [
                    'rsvp_id' => (int) $rsvp_id,
                    'newsman_error' => is_wp_error( $sync_result ) ? $sync_result->get_error_message() : '',
                ],
            ]
        );
    },
    5,
    3
);

add_action(
    'teinvit_email_suppression_added',
    function( $email, $scope, $reason ) {
        if ( $scope !== 'marketing' ) {
            return;
        }

        $email = sanitize_email( (string) $email );
        if ( $email === '' ) {
            return;
        }

        $phone = teinvit_marketing_get_latest_non_empty_phone( $email );
        $now   = current_time( 'mysql' );

        teinvit_marketing_upsert_contact(
            $email,
            [
                'phone'                => $phone,
                'marketing_consent'    => 0,
                'suppression_active'   => 1,
                'subscription_status'  => 'unsubscribed',
                'source_event'         => 'email_suppression',
                'last_unsubscribed_at' => $now,
            ]
        );

        $sync_result = teinvit_marketing_sync_newsman_unsubscribe( $email );

        teinvit_marketing_log_event(
            [
                'email'  => $email,
                'phone'  => $phone,
                'token'  => '',
                'source' => 'email_suppression',
                'action' => 'unsubscribed',
                'status' => is_wp_error( $sync_result ) ? 'newsman_sync_error' : 'newsman_sync_ok',
                'context' => [
                    'reason' => sanitize_key( (string) $reason ),
                    'newsman_error' => is_wp_error( $sync_result ) ? $sync_result->get_error_message() : '',
                ],
            ]
        );
    },
    10,
    4
);

add_action(
    'teinvit_email_suppression_removed',
    function( $email, $scope ) {
        if ( $scope !== 'marketing' ) {
            return;
        }

        $email = sanitize_email( (string) $email );
        if ( $email === '' ) {
            return;
        }

        teinvit_marketing_upsert_contact( $email, [ 'suppression_active' => 0 ] );
    },
    10,
    3
);
