<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function teinvit_user_sync_meta_keys() {
    return [
        'created_by_teinvit'      => 'teinvit_created_by_teinvit',
        'subscriber_only'         => 'teinvit_subscriber_only',
        'sync_enabled'            => 'teinvit_sync_enabled',
        'subscription_status'     => 'teinvit_subscription_status',
        'gdpr_accepted'           => 'teinvit_gdpr_accepted',
        'marketing_consent'       => 'teinvit_marketing_consent',
        'guest_phone'             => 'teinvit_guest_phone',
        'source_token'            => 'teinvit_source_token',
        'source_event'            => 'teinvit_source_event',
        'last_subscribed_at'      => 'teinvit_last_subscribed_at',
        'last_unsubscribed_at'    => 'teinvit_last_unsubscribed_at',
        'last_resubscribed_at'    => 'teinvit_last_resubscribed_at',
        'last_consent_update_at'  => 'teinvit_last_consent_update_at',
    ];
}

function teinvit_user_sync_normalize_phone( $phone ) {
    $phone = trim( (string) $phone );
    if ( $phone === '' ) {
        return '';
    }

    $phone = preg_replace( '/\s+/', '', $phone );
    return sanitize_text_field( $phone );
}

function teinvit_user_sync_get_latest_non_empty_phone( $email ) {
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

    return teinvit_user_sync_normalize_phone( (string) $raw );
}

function teinvit_user_sync_log_event( array $event ) {
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
            'phone'      => teinvit_user_sync_normalize_phone( (string) ( $event['phone'] ?? '' ) ),
            'token'      => sanitize_text_field( (string) ( $event['token'] ?? '' ) ),
            'source'     => sanitize_text_field( (string) ( $event['source'] ?? '' ) ),
            'action'     => sanitize_key( (string) ( $event['action'] ?? '' ) ),
            'status'     => sanitize_key( (string) ( $event['status'] ?? '' ) ),
            'user_id'    => ! empty( $event['user_id'] ) ? (int) $event['user_id'] : null,
            'context'    => wp_json_encode( is_array( $event['context'] ?? null ) ? $event['context'] : [] ),
            'created_at' => current_time( 'mysql' ),
        ]
    );
}

function teinvit_user_sync_make_username( $email ) {
    $base = sanitize_user( strstr( (string) $email, '@', true ) ?: 'teinvit_guest', true );
    if ( $base === '' ) {
        $base = 'teinvit_guest';
    }

    $candidate = $base;
    $i = 1;
    while ( username_exists( $candidate ) ) {
        $candidate = $base . '_' . $i;
        $i++;
    }

    return $candidate;
}

function teinvit_user_sync_create_subscriber_user( $email ) {
    $user_id = wp_insert_user(
        [
            'user_login' => teinvit_user_sync_make_username( $email ),
            'user_pass'  => wp_generate_password( 32, true, true ),
            'user_email' => $email,
            'role'       => 'subscriber',
        ]
    );

    if ( is_wp_error( $user_id ) ) {
        return 0;
    }

    return (int) $user_id;
}

function teinvit_user_sync_update_user_meta( $user_id, array $args ) {
    $meta = teinvit_user_sync_meta_keys();
    $now  = current_time( 'mysql' );

    update_user_meta( $user_id, $meta['sync_enabled'], 1 );
    update_user_meta( $user_id, $meta['gdpr_accepted'], ! empty( $args['gdpr_accepted'] ) ? 1 : 0 );
    update_user_meta( $user_id, $meta['marketing_consent'], ! empty( $args['marketing_consent'] ) ? 1 : 0 );
    update_user_meta( $user_id, $meta['subscription_status'], sanitize_key( (string) ( $args['subscription_status'] ?? 'unsubscribed' ) ) );
    update_user_meta( $user_id, $meta['source_token'], sanitize_text_field( (string) ( $args['token'] ?? '' ) ) );
    update_user_meta( $user_id, $meta['source_event'], sanitize_text_field( (string) ( $args['source_event'] ?? '' ) ) );
    update_user_meta( $user_id, $meta['last_consent_update_at'], $now );

    $phone = teinvit_user_sync_normalize_phone( (string) ( $args['phone'] ?? '' ) );
    if ( $phone !== '' ) {
        update_user_meta( $user_id, $meta['guest_phone'], $phone );
    }

    if ( ( $args['subscription_status'] ?? '' ) === 'subscribed' ) {
        update_user_meta( $user_id, $meta['last_subscribed_at'], $now );
    }
    if ( ( $args['subscription_status'] ?? '' ) === 'unsubscribed' ) {
        update_user_meta( $user_id, $meta['last_unsubscribed_at'], $now );
    }
    if ( ( $args['subscription_status'] ?? '' ) === 'resubscribed' ) {
        update_user_meta( $user_id, $meta['last_resubscribed_at'], $now );
        update_user_meta( $user_id, $meta['last_subscribed_at'], $now );
    }
}

function teinvit_user_sync_is_deletable_teinvit_subscriber( WP_User $user ) {
    $meta = teinvit_user_sync_meta_keys();

    $created_by_teinvit = (int) get_user_meta( $user->ID, $meta['created_by_teinvit'], true ) === 1;
    $subscriber_only    = (int) get_user_meta( $user->ID, $meta['subscriber_only'], true ) === 1;

    if ( ! $created_by_teinvit || ! $subscriber_only ) {
        return false;
    }

    $roles = array_values( array_filter( (array) $user->roles ) );
    return count( $roles ) === 1 && $roles[0] === 'subscriber';
}

function teinvit_user_sync_handle_unsubscribe( $email, $source, $token = '', $reason = 'unsubscribe' ) {
    $email = sanitize_email( $email );
    if ( $email === '' ) {
        return;
    }

    $user       = get_user_by( 'email', $email );
    $user_id    = $user instanceof WP_User ? (int) $user->ID : 0;
    $phone      = teinvit_user_sync_get_latest_non_empty_phone( $email );
    $was_deleted = false;

    if ( $user instanceof WP_User && teinvit_user_sync_is_deletable_teinvit_subscriber( $user ) ) {
        require_once ABSPATH . 'wp-admin/includes/user.php';
        $deleted = wp_delete_user( $user->ID );
        $was_deleted = (bool) $deleted;
    } elseif ( $user instanceof WP_User ) {
        teinvit_user_sync_update_user_meta(
            $user->ID,
            [
                'gdpr_accepted'       => (int) get_user_meta( $user->ID, teinvit_user_sync_meta_keys()['gdpr_accepted'], true ) === 1,
                'marketing_consent'   => 0,
                'subscription_status' => 'unsubscribed',
                'token'               => $token,
                'source_event'        => $source,
                'phone'               => $phone,
            ]
        );
    }

    teinvit_user_sync_log_event(
        [
            'email'   => $email,
            'phone'   => $phone,
            'token'   => $token,
            'source'  => $source,
            'action'  => 'unsubscribed',
            'status'  => $was_deleted ? 'user_deleted' : 'user_kept',
            'user_id' => $user_id,
            'context' => [ 'reason' => $reason, 'deleted' => $was_deleted ? 1 : 0 ],
        ]
    );
}

add_action(
    'teinvit_rsvp_saved',
    function( $token, $rsvp_id, $payload ) {
        $email = sanitize_email( (string) ( $payload['guest_email'] ?? '' ) );
        if ( $email === '' ) {
            return;
        }

        $phone     = teinvit_user_sync_normalize_phone( (string) ( $payload['guest_phone'] ?? '' ) );
        $gdpr      = ! empty( $payload['gdpr_accepted'] );
        $marketing = ! empty( $payload['marketing_consent'] );
        $eligible  = $gdpr && $marketing;

        $user = get_user_by( 'email', $email );
        $user_id = $user instanceof WP_User ? (int) $user->ID : 0;
        $source = 'rsvp_saved';

        if ( $eligible ) {
            $was_suppressed = function_exists( 'teinvit_email_is_suppressed' ) ? teinvit_email_is_suppressed( $email, 'marketing' ) : false;
            $was_unsubscribed = $user instanceof WP_User && get_user_meta( $user_id, teinvit_user_sync_meta_keys()['subscription_status'], true ) === 'unsubscribed';

            if ( $was_suppressed && function_exists( 'teinvit_email_remove_suppression' ) ) {
                teinvit_email_remove_suppression( $email, 'marketing', 'rsvp_resubscribe' );
            }

            if ( ! ( $user instanceof WP_User ) ) {
                $new_user_id = teinvit_user_sync_create_subscriber_user( $email );
                if ( $new_user_id > 0 ) {
                    $user_id = $new_user_id;
                    $user = get_user_by( 'id', $new_user_id );
                    $meta = teinvit_user_sync_meta_keys();
                    update_user_meta( $new_user_id, $meta['created_by_teinvit'], 1 );
                    update_user_meta( $new_user_id, $meta['subscriber_only'], 1 );
                }
            }

            $latest_phone = $phone !== '' ? $phone : teinvit_user_sync_get_latest_non_empty_phone( $email );
            $action = ( $was_suppressed || $was_unsubscribed ) ? 'resubscribed' : 'subscribed';
            $status = 'user_synced';

            if ( $user instanceof WP_User ) {
                teinvit_user_sync_update_user_meta(
                    $user->ID,
                    [
                        'gdpr_accepted'       => 1,
                        'marketing_consent'   => 1,
                        'subscription_status' => $action === 'resubscribed' ? 'resubscribed' : 'subscribed',
                        'token'               => $token,
                        'source_event'        => $source,
                        'phone'               => $latest_phone,
                    ]
                );
            } else {
                $status = 'user_create_failed';
            }

            teinvit_user_sync_log_event(
                [
                    'email'   => $email,
                    'phone'   => $latest_phone,
                    'token'   => $token,
                    'source'  => $source,
                    'action'  => $action,
                    'status'  => $status,
                    'user_id' => $user instanceof WP_User ? (int) $user->ID : 0,
                    'context' => [ 'rsvp_id' => (int) $rsvp_id, 'gdpr_accepted' => 1, 'marketing_consent' => 1 ],
                ]
            );

            return;
        }

        $latest_phone = $phone !== '' ? $phone : teinvit_user_sync_get_latest_non_empty_phone( $email );
        if ( $user instanceof WP_User ) {
            teinvit_user_sync_update_user_meta(
                $user->ID,
                [
                    'gdpr_accepted'       => $gdpr ? 1 : 0,
                    'marketing_consent'   => $marketing ? 1 : 0,
                    'subscription_status' => 'consent_incomplete',
                    'token'               => $token,
                    'source_event'        => $source,
                    'phone'               => $latest_phone,
                ]
            );
        }

        teinvit_user_sync_log_event(
            [
                'email'   => $email,
                'phone'   => $latest_phone,
                'token'   => $token,
                'source'  => $source,
                'action'  => 'consent_updated',
                'status'  => 'consent_incomplete',
                'user_id' => $user instanceof WP_User ? (int) $user->ID : 0,
                'context' => [
                    'rsvp_id'            => (int) $rsvp_id,
                    'gdpr_accepted'      => $gdpr ? 1 : 0,
                    'marketing_consent'  => $marketing ? 1 : 0,
                ],
            ]
        );
    },
    25,
    3
);

add_action(
    'teinvit_email_suppression_added',
    function( $email, $scope, $reason, $source_send_id ) {
        if ( $scope !== 'marketing' ) {
            return;
        }

        teinvit_user_sync_handle_unsubscribe(
            $email,
            'email_suppression',
            '',
            $reason !== '' ? $reason : 'suppression_added'
        );
    },
    10,
    4
);
