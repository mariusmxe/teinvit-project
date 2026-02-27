<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function teinvit_db_tables() {
    global $wpdb;

    return [
        'invitations' => $wpdb->prefix . 'teinvit_invitations',
        'versions'    => $wpdb->prefix . 'teinvit_versions',
        'rsvp'        => $wpdb->prefix . 'teinvit_rsvp',
        'gifts'       => $wpdb->prefix . 'teinvit_gifts',
    ];
}

function teinvit_install_modular_tables() {
    global $wpdb;

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $charset = $wpdb->get_charset_collate();
    $t = teinvit_db_tables();

    dbDelta( "CREATE TABLE {$t['invitations']} (
        token varchar(191) NOT NULL,
        order_id bigint(20) unsigned NOT NULL,
        module_key varchar(64) NOT NULL DEFAULT 'wedding',
        model_key varchar(64) NOT NULL DEFAULT 'invn01',
        active_version_id bigint(20) unsigned NULL,
        event_date datetime NULL,
        last_activity_at datetime NULL,
        gifts_locked tinyint(1) NOT NULL DEFAULT 0,
        config longtext NULL,
        created_at datetime NOT NULL,
        updated_at datetime NOT NULL,
        PRIMARY KEY  (token),
        KEY order_id (order_id),
        KEY active_version_id (active_version_id),
        KEY event_date (event_date),
        KEY last_activity_at (last_activity_at)
    ) $charset;" );

    dbDelta( "CREATE TABLE {$t['versions']} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        token varchar(191) NOT NULL,
        snapshot longtext NOT NULL,
        pdf_url text NULL,
        pdf_status varchar(20) NOT NULL DEFAULT 'none',
        pdf_generated_at datetime NULL,
        pdf_filename text NULL,
        created_at datetime NOT NULL,
        PRIMARY KEY (id),
        KEY token (token),
        KEY token_pdf_status (token, pdf_status)
    ) $charset;" );

    dbDelta( "CREATE TABLE {$t['rsvp']} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        token varchar(191) NOT NULL,
        guest_first_name varchar(191) NOT NULL,
        guest_last_name varchar(191) NOT NULL,
        guest_email varchar(191) NOT NULL,
        guest_phone varchar(20) NOT NULL,
        attending_people_count int NOT NULL DEFAULT 1,
        attending_civil tinyint(1) NOT NULL DEFAULT 0,
        attending_religious tinyint(1) NOT NULL DEFAULT 0,
        attending_party tinyint(1) NOT NULL DEFAULT 0,
        bringing_kids tinyint(1) NOT NULL DEFAULT 0,
        kids_count int NOT NULL DEFAULT 0,
        needs_accommodation tinyint(1) NOT NULL DEFAULT 0,
        accommodation_people_count int NOT NULL DEFAULT 0,
        vegetarian_requested tinyint(1) NOT NULL DEFAULT 0,
        has_allergies tinyint(1) NOT NULL DEFAULT 0,
        allergy_details text NULL,
        message_to_couple text NULL,
        gdpr_accepted tinyint(1) NOT NULL DEFAULT 0,
        marketing_consent tinyint(1) NOT NULL DEFAULT 0,
        created_at datetime NOT NULL,
        PRIMARY KEY (id),
        KEY token (token),
        KEY guest_email (guest_email),
        KEY guest_phone (guest_phone)
    ) $charset;" );

    dbDelta( "CREATE TABLE {$t['gifts']} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        token varchar(191) NOT NULL,
        gift_id varchar(64) NOT NULL,
        gift_name varchar(255) NOT NULL,
        gift_link text NULL,
        gift_delivery_address text NULL,
        include_in_public tinyint(1) NOT NULL DEFAULT 0,
        published_locked tinyint(1) NOT NULL DEFAULT 0,
        locked_at datetime NULL,
        status varchar(20) NOT NULL DEFAULT 'free',
        reserved_by_rsvp_id bigint(20) unsigned NULL,
        reserved_at datetime NULL,
        PRIMARY KEY (id),
        UNIQUE KEY token_gift_id (token, gift_id),
        KEY token_status (token, status)
    ) $charset;" );
    teinvit_ensure_versions_pdf_columns();
    teinvit_ensure_rsvp_email_column();
    teinvit_ensure_rsvp_marketing_column();
    teinvit_ensure_gifts_publish_columns();
}

function teinvit_ensure_rsvp_email_column() {
    global $wpdb;
    $t = teinvit_db_tables();
    $table = $t['rsvp'];

    $cols = $wpdb->get_col( "SHOW COLUMNS FROM {$table}", 0 );
    if ( ! is_array( $cols ) ) {
        return;
    }

    if ( ! in_array( 'guest_email', $cols, true ) ) {
        $wpdb->query( "ALTER TABLE {$table} ADD COLUMN guest_email varchar(191) NOT NULL DEFAULT '' AFTER guest_last_name" );
    }
}


function teinvit_ensure_rsvp_marketing_column() {
    global $wpdb;
    $t = teinvit_db_tables();
    $table = $t['rsvp'];

    $cols = $wpdb->get_col( "SHOW COLUMNS FROM {$table}", 0 );
    if ( ! is_array( $cols ) ) {
        return;
    }

    if ( ! in_array( 'marketing_consent', $cols, true ) ) {
        $wpdb->query( "ALTER TABLE {$table} ADD COLUMN marketing_consent tinyint(1) NOT NULL DEFAULT 0 AFTER gdpr_accepted" );
    }
}

function teinvit_ensure_gifts_publish_columns() {
    global $wpdb;
    $t = teinvit_db_tables();
    $table = $t['gifts'];

    $cols = $wpdb->get_col( "SHOW COLUMNS FROM {$table}", 0 );
    if ( ! is_array( $cols ) ) {
        return;
    }

    if ( ! in_array( 'include_in_public', $cols, true ) ) {
        $wpdb->query( "ALTER TABLE {$table} ADD COLUMN include_in_public tinyint(1) NOT NULL DEFAULT 0 AFTER gift_delivery_address" );
    }
    if ( ! in_array( 'published_locked', $cols, true ) ) {
        $wpdb->query( "ALTER TABLE {$table} ADD COLUMN published_locked tinyint(1) NOT NULL DEFAULT 0 AFTER include_in_public" );
    }
    if ( ! in_array( 'locked_at', $cols, true ) ) {
        $wpdb->query( "ALTER TABLE {$table} ADD COLUMN locked_at datetime NULL AFTER published_locked" );
    }
}

function teinvit_ensure_versions_pdf_columns() {
    global $wpdb;
    $t = teinvit_db_tables();
    $table = $t['versions'];

    $cols = $wpdb->get_col( "SHOW COLUMNS FROM {$table}", 0 );
    if ( ! is_array( $cols ) ) {
        return;
    }

    if ( ! in_array( 'pdf_url', $cols, true ) ) {
        $wpdb->query( "ALTER TABLE {$table} ADD COLUMN pdf_url text NULL" );
    }
    if ( ! in_array( 'pdf_status', $cols, true ) ) {
        $wpdb->query( "ALTER TABLE {$table} ADD COLUMN pdf_status varchar(20) NOT NULL DEFAULT 'none'" );
    }
    if ( ! in_array( 'pdf_generated_at', $cols, true ) ) {
        $wpdb->query( "ALTER TABLE {$table} ADD COLUMN pdf_generated_at datetime NULL" );
    }
    if ( ! in_array( 'pdf_filename', $cols, true ) ) {
        $wpdb->query( "ALTER TABLE {$table} ADD COLUMN pdf_filename text NULL" );
    }
}

function teinvit_get_invitation( $token ) {
    global $wpdb;
    $t = teinvit_db_tables();
    $row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t['invitations']} WHERE token = %s", $token ), ARRAY_A );
    if ( ! $row ) {
        return null;
    }
    $row['config'] = json_decode( (string) $row['config'], true );
    if ( ! is_array( $row['config'] ) ) {
        $row['config'] = [];
    }
    return $row;
}

function teinvit_save_invitation_config( $token, array $data ) {
    global $wpdb;
    $t = teinvit_db_tables();
    $payload = $data;
    $payload['updated_at'] = current_time( 'mysql' );
    if ( isset( $payload['config'] ) && is_array( $payload['config'] ) ) {
        $payload['config'] = wp_json_encode( $payload['config'] );
    }
    return $wpdb->update( $t['invitations'], $payload, [ 'token' => $token ] );
}

function teinvit_get_versions_for_token( $token ) {
    global $wpdb;
    $t = teinvit_db_tables();
    return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$t['versions']} WHERE token = %s ORDER BY id DESC", $token ), ARRAY_A );
}

function teinvit_get_active_snapshot( $token ) {
    global $wpdb;
    $t = teinvit_db_tables();
    return $wpdb->get_row( $wpdb->prepare( "SELECT v.* FROM {$t['versions']} v INNER JOIN {$t['invitations']} i ON i.active_version_id = v.id WHERE i.token = %s LIMIT 1", $token ), ARRAY_A );
}

function teinvit_touch_invitation_activity( $token ) {
    global $wpdb;
    $t = teinvit_db_tables();
    $wpdb->update( $t['invitations'], [ 'last_activity_at' => current_time( 'mysql' ) ], [ 'token' => $token ] );
}

function teinvit_seed_invitation_if_missing( $token, $order_id ) {
    global $wpdb;
    $existing = teinvit_get_invitation( $token );
    if ( $existing ) {
        return $existing;
    }

    $order = wc_get_order( (int) $order_id );
    $invitation = $order ? TeInvit_Wedding_Preview_Renderer::get_order_invitation_data( $order ) : [];
    $wapf_fields = $order ? TeInvit_Wedding_Preview_Renderer::get_order_wapf_field_map( $order ) : [];
    $snapshot_id = 0;

    $t = teinvit_db_tables();
    $wpdb->insert( $t['versions'], [
        'token'      => $token,
        'snapshot'   => wp_json_encode( [
            'invitation' => $invitation,
            'wapf_fields' => $wapf_fields,
            'meta' => [
                'seeded_from_order' => true,
                'order_id' => (int) $order_id,
            ],
        ] ),
        'created_at' => current_time( 'mysql' ),
    ] );

    if ( $wpdb->insert_id ) {
        $snapshot_id = (int) $wpdb->insert_id;
    }

    $wpdb->insert( $t['invitations'], [
        'token'             => $token,
        'order_id'          => (int) $order_id,
        'module_key'        => 'wedding',
        'model_key'         => 'invn01',
        'active_version_id' => $snapshot_id,
        'event_date'        => null,
        'last_activity_at'  => current_time( 'mysql' ),
        'gifts_locked'      => 0,
        'config'            => wp_json_encode( teinvit_default_rsvp_config() ),
        'created_at'        => current_time( 'mysql' ),
        'updated_at'        => current_time( 'mysql' ),
    ] );

    return teinvit_get_invitation( $token );
}

function teinvit_default_rsvp_config() {
    return [
        'show_attending_civil' => 1,
        'show_attending_religious' => 1,
        'show_attending_party' => 1,
        'show_kids' => 0,
        'show_accommodation' => 0,
        'show_vegetarian' => 0,
        'show_allergies' => 0,
        'show_rsvp_deadline' => 0,
        'rsvp_deadline_text' => '',
        'show_gifts_section' => 0,
        'gifts_extra_slots' => 0,
    ];
}

function teinvit_cleanup_expired_invitations() {
    global $wpdb;
    $t = teinvit_db_tables();

    $days = (int) apply_filters( 'teinvit_cleanup_days', 45 );
    $threshold = gmdate( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) );

    $tokens = $wpdb->get_col( $wpdb->prepare( "SELECT token FROM {$t['invitations']} WHERE (event_date IS NOT NULL AND event_date < %s) OR (last_activity_at IS NOT NULL AND last_activity_at < %s)", $threshold, $threshold ) );
    if ( empty( $tokens ) ) {
        return;
    }

    foreach ( $tokens as $token ) {
        $wpdb->delete( $t['gifts'], [ 'token' => $token ] );
        $wpdb->delete( $t['rsvp'], [ 'token' => $token ] );
        $wpdb->delete( $t['versions'], [ 'token' => $token ] );
        $wpdb->delete( $t['invitations'], [ 'token' => $token ] );
    }
}
