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
        'marketing_contacts' => $wpdb->prefix . 'teinvit_marketing_contacts',
        'consent_journal' => $wpdb->prefix . 'teinvit_consent_journal',
        'integrations' => $wpdb->prefix . 'teinvit_integrations',
        'api_keys' => $wpdb->prefix . 'teinvit_api_keys',
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
        vegetarian_menus_count int NOT NULL DEFAULT 0,
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

    dbDelta( "CREATE TABLE {$t['consent_journal']} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        email varchar(191) NOT NULL,
        email_hash char(64) NOT NULL,
        phone varchar(32) NOT NULL DEFAULT '',
        token varchar(191) NOT NULL DEFAULT '',
        source varchar(100) NOT NULL DEFAULT '',
        action varchar(40) NOT NULL,
        status varchar(40) NOT NULL DEFAULT '',
        user_id bigint(20) unsigned NULL,
        context longtext NULL,
        created_at datetime NOT NULL,
        PRIMARY KEY (id),
        KEY email_hash (email_hash),
        KEY created_at (created_at),
        KEY token (token),
        KEY action_created (action, created_at)
    ) $charset;" );

    dbDelta( "CREATE TABLE {$t['marketing_contacts']} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        email varchar(191) NOT NULL,
        email_hash char(64) NOT NULL,
        first_name varchar(191) NOT NULL DEFAULT '',
        last_name varchar(191) NOT NULL DEFAULT '',
        phone varchar(32) NOT NULL DEFAULT '',
        gdpr_accepted tinyint(1) NOT NULL DEFAULT 0,
        marketing_consent tinyint(1) NOT NULL DEFAULT 0,
        suppression_active tinyint(1) NOT NULL DEFAULT 0,
        subscription_status varchar(40) NOT NULL DEFAULT 'consent_incomplete',
        source_token varchar(191) NOT NULL DEFAULT '',
        source_event varchar(100) NOT NULL DEFAULT '',
        last_subscribed_at datetime NULL,
        last_unsubscribed_at datetime NULL,
        last_resubscribed_at datetime NULL,
        last_consent_updated_at datetime NULL,
        last_newsman_sync_at datetime NULL,
        last_newsman_sync_status varchar(20) NOT NULL DEFAULT 'none',
        last_newsman_error text NULL,
        created_at datetime NOT NULL,
        updated_at datetime NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY email_unique (email),
        KEY email_hash (email_hash),
        KEY subscription_status (subscription_status),
        KEY updated_at (updated_at)
    ) $charset;" );

    dbDelta( "CREATE TABLE {$t['integrations']} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        provider_key varchar(64) NOT NULL,
        enabled tinyint(1) NOT NULL DEFAULT 0,
        config longtext NULL,
        last_status varchar(20) NOT NULL DEFAULT 'never',
        last_error text NULL,
        last_tested_at datetime NULL,
        updated_at datetime NOT NULL,
        created_at datetime NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY provider_key (provider_key)
    ) $charset;" );

    dbDelta( "CREATE TABLE {$t['api_keys']} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        name varchar(191) NOT NULL,
        key_prefix varchar(20) NOT NULL,
        key_hash char(64) NOT NULL,
        scopes text NULL,
        status varchar(20) NOT NULL DEFAULT 'active',
        notes text NULL,
        last_used_at datetime NULL,
        revoked_at datetime NULL,
        created_at datetime NOT NULL,
        updated_at datetime NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY key_hash (key_hash),
        KEY status (status),
        KEY created_at (created_at)
    ) $charset;" );
    teinvit_ensure_versions_pdf_columns();
    teinvit_ensure_rsvp_email_column();
    teinvit_ensure_rsvp_marketing_column();
    teinvit_ensure_rsvp_vegetarian_menus_column();
    teinvit_ensure_gifts_publish_columns();
    teinvit_ensure_marketing_contacts_name_columns();
}

function teinvit_vertical_storage_keys() {
    return [ 'baptism', 'birthday' ];
}

function teinvit_install_vertical_storage_tables() {
    global $wpdb;

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $charset = $wpdb->get_charset_collate();

    foreach ( teinvit_vertical_storage_keys() as $vertical_key ) {
        if ( ! function_exists( 'teinvit_vertical_storage_family_map' ) ) {
            continue;
        }

        $t = teinvit_vertical_storage_family_map( $vertical_key );
        if ( empty( $t['invitations'] ) || empty( $t['versions'] ) || empty( $t['rsvp'] ) || empty( $t['gifts'] ) ) {
            continue;
        }

        $module_default = sanitize_key( (string) $vertical_key );

        dbDelta( "CREATE TABLE {$t['invitations']} (
            token varchar(191) NOT NULL,
            order_id bigint(20) unsigned NOT NULL,
            module_key varchar(64) NOT NULL DEFAULT '{$module_default}',
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
            vegetarian_menus_count int NOT NULL DEFAULT 0,
            has_allergies tinyint(1) NOT NULL DEFAULT 0,
            allergy_details text NULL,
            message_to_couple text NULL,
            extra_fields longtext NULL,
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
    }

    teinvit_ensure_vertical_rsvp_extra_fields_columns();
}

function teinvit_ensure_vertical_rsvp_extra_fields_columns() {
    global $wpdb;

    foreach ( teinvit_vertical_storage_keys() as $vertical_key ) {
        if ( ! function_exists( 'teinvit_vertical_storage_family_map' ) ) {
            continue;
        }

        $t = teinvit_vertical_storage_family_map( $vertical_key );
        if ( empty( $t['rsvp'] ) ) {
            continue;
        }

        $cols = $wpdb->get_col( "SHOW COLUMNS FROM {$t['rsvp']}", 0 );
        if ( ! is_array( $cols ) ) {
            continue;
        }

        if ( ! in_array( 'extra_fields', $cols, true ) ) {
            $wpdb->query( "ALTER TABLE {$t['rsvp']} ADD COLUMN extra_fields longtext NULL AFTER message_to_couple" );
        }
    }
}

function teinvit_ensure_marketing_contacts_name_columns() {
    global $wpdb;
    $t = teinvit_db_tables();
    $table = $t['marketing_contacts'];

    $cols = $wpdb->get_col( "SHOW COLUMNS FROM {$table}", 0 );
    if ( ! is_array( $cols ) ) {
        return;
    }

    if ( ! in_array( 'first_name', $cols, true ) ) {
        $wpdb->query( "ALTER TABLE {$table} ADD COLUMN first_name varchar(191) NOT NULL DEFAULT '' AFTER email_hash" );
    }
    if ( ! in_array( 'last_name', $cols, true ) ) {
        $wpdb->query( "ALTER TABLE {$table} ADD COLUMN last_name varchar(191) NOT NULL DEFAULT '' AFTER first_name" );
    }
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


function teinvit_ensure_rsvp_vegetarian_menus_column() {
    global $wpdb;
    $t = teinvit_db_tables();
    $table = $t['rsvp'];

    $cols = $wpdb->get_col( "SHOW COLUMNS FROM {$table}", 0 );
    if ( ! is_array( $cols ) ) {
        return;
    }

    if ( ! in_array( 'vegetarian_menus_count', $cols, true ) ) {
        $wpdb->query( "ALTER TABLE {$table} ADD COLUMN vegetarian_menus_count int NOT NULL DEFAULT 0 AFTER vegetarian_requested" );
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

function teinvit_storage_tables_for_vertical_safe( $vertical_key ) {
    $vertical_key = sanitize_key( (string) $vertical_key );
    if ( $vertical_key === '' ) {
        $vertical_key = 'wedding';
    }

    if ( function_exists( 'teinvit_storage_tables_for_vertical' ) ) {
        $tables = teinvit_storage_tables_for_vertical( $vertical_key );
        if ( is_array( $tables ) && ! empty( $tables['invitations'] ) ) {
            return array_merge( teinvit_db_tables(), $tables );
        }
    }

    return teinvit_db_tables();
}

function teinvit_storage_vertical_candidates_for_token( $token, $preferred_vertical = '' ) {
    $token = sanitize_text_field( (string) $token );
    $preferred_vertical = sanitize_key( (string) $preferred_vertical );
    $candidates = [];

    if ( $preferred_vertical !== '' ) {
        $candidates[] = $preferred_vertical;
    }

    $order_id = function_exists( 'teinvit_get_order_id_by_token' ) ? (int) teinvit_get_order_id_by_token( $token ) : 0;
    if ( $order_id > 0 ) {
        $snapshot = sanitize_key( (string) get_post_meta( $order_id, '_teinvit_vertical_key_snapshot', true ) );
        $meta_vertical = sanitize_key( (string) get_post_meta( $order_id, '_teinvit_vertical_key', true ) );
        if ( $snapshot !== '' ) {
            $candidates[] = $snapshot;
        }
        if ( $meta_vertical !== '' ) {
            $candidates[] = $meta_vertical;
        }
        if ( function_exists( 'wc_get_order' ) && function_exists( 'teinvit_find_catalog_vertical_for_product_id' ) ) {
            $order = wc_get_order( $order_id );
            if ( class_exists( 'WC_Order' ) && $order instanceof WC_Order ) {
                foreach ( $order->get_items( 'line_item' ) as $item ) {
                    $product_vertical = teinvit_find_catalog_vertical_for_product_id( (int) $item->get_product_id() );
                    if ( is_string( $product_vertical ) && $product_vertical !== '' ) {
                        $candidates[] = sanitize_key( $product_vertical );
                        break;
                    }
                }
            }
        }
    }

    $known = function_exists( 'teinvit_vertical_keys' ) ? teinvit_vertical_keys() : [ 'wedding', 'baptism', 'birthday' ];
    $candidates = array_merge( $candidates, [ 'wedding' ], $known );
    $candidates = array_values( array_unique( array_map( static function( $vertical ) {
        $vertical = sanitize_key( (string) $vertical );
        return $vertical !== '' ? $vertical : 'wedding';
    }, $candidates ) ) );

    return $candidates;
}

function teinvit_find_invitation_storage_for_token( $token, $preferred_vertical = '' ) {
    global $wpdb;

    $token = sanitize_text_field( (string) $token );
    if ( $token === '' ) {
        return null;
    }

    foreach ( teinvit_storage_vertical_candidates_for_token( $token, $preferred_vertical ) as $vertical_key ) {
        $tables = teinvit_storage_tables_for_vertical_safe( $vertical_key );
        if ( empty( $tables['invitations'] ) ) {
            continue;
        }

        $row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$tables['invitations']} WHERE token = %s", $token ), ARRAY_A );
        if ( ! $row ) {
            continue;
        }

        $module_key = sanitize_key( (string) ( $row['module_key'] ?? $vertical_key ) );
        if ( $module_key === '' ) {
            $module_key = $vertical_key;
        }

        return [
            'vertical' => $module_key,
            'tables' => $tables,
            'row' => $row,
        ];
    }

    return null;
}

function teinvit_decode_invitation_config_row( array $row ) {
    $row['config'] = json_decode( (string) ( $row['config'] ?? '' ), true );
    if ( ! is_array( $row['config'] ) ) {
        $row['config'] = [];
    }
    return $row;
}

function teinvit_get_invitation_record( $token, $vertical_key = '' ) {
    $storage = teinvit_find_invitation_storage_for_token( $token, $vertical_key );
    if ( ! $storage || empty( $storage['row'] ) || ! is_array( $storage['row'] ) ) {
        return null;
    }

    return teinvit_decode_invitation_config_row( $storage['row'] );
}

function teinvit_get_invitation( $token ) {
    return teinvit_get_invitation_record( $token );
}

function teinvit_save_invitation_config_for_token( $token, array $data, $vertical_key = '' ) {
    global $wpdb;

    $token = sanitize_text_field( (string) $token );
    if ( $token === '' ) {
        return false;
    }

    $storage = teinvit_find_invitation_storage_for_token( $token, $vertical_key );
    if ( ! $storage || empty( $storage['tables']['invitations'] ) ) {
        return false;
    }

    $payload = $data;
    $payload['updated_at'] = current_time( 'mysql' );
    if ( isset( $payload['config'] ) && is_array( $payload['config'] ) ) {
        $payload['config'] = wp_json_encode( $payload['config'] );
    }

    return $wpdb->update( $storage['tables']['invitations'], $payload, [ 'token' => $token ] );
}

function teinvit_save_invitation_config( $token, array $data ) {
    return teinvit_save_invitation_config_for_token( $token, $data );
}

function teinvit_storage_tables_for_existing_token( $token, $vertical_key = '' ) {
    $storage = teinvit_find_invitation_storage_for_token( $token, $vertical_key );
    if ( $storage && ! empty( $storage['tables'] ) && is_array( $storage['tables'] ) ) {
        return $storage['tables'];
    }

    $vertical_key = sanitize_key( (string) $vertical_key );
    if ( $vertical_key !== '' ) {
        return teinvit_storage_tables_for_vertical_safe( $vertical_key );
    }

    $candidates = teinvit_storage_vertical_candidates_for_token( $token );
    if ( ! empty( $candidates[0] ) ) {
        return teinvit_storage_tables_for_vertical_safe( $candidates[0] );
    }

    return teinvit_db_tables();
}

function teinvit_storage_table_for_token( $token, $family, $vertical_key = '' ) {
    $family = sanitize_key( (string) $family );
    $tables = teinvit_storage_tables_for_existing_token( $token, $vertical_key );
    return isset( $tables[ $family ] ) ? (string) $tables[ $family ] : '';
}

function teinvit_rsvp_table_for_token( $token, $vertical_key = '' ) {
    return teinvit_storage_table_for_token( $token, 'rsvp', $vertical_key );
}

function teinvit_gifts_table_for_token( $token, $vertical_key = '' ) {
    return teinvit_storage_table_for_token( $token, 'gifts', $vertical_key );
}

function teinvit_rsvp_common_storage_columns() {
    return [
        'token',
        'guest_first_name',
        'guest_last_name',
        'guest_email',
        'guest_phone',
        'attending_people_count',
        'attending_civil',
        'attending_religious',
        'attending_party',
        'bringing_kids',
        'kids_count',
        'needs_accommodation',
        'accommodation_people_count',
        'vegetarian_requested',
        'vegetarian_menus_count',
        'has_allergies',
        'allergy_details',
        'message_to_couple',
        'gdpr_accepted',
        'marketing_consent',
        'created_at',
    ];
}

function teinvit_prepare_hybrid_rsvp_insert_data( $vertical_key, array $common_fields, array $extra_fields = [] ) {
    $vertical_key = function_exists( 'teinvit_normalize_vertical_key' ) ? teinvit_normalize_vertical_key( $vertical_key ) : sanitize_key( (string) $vertical_key );
    $allowed = array_flip( teinvit_rsvp_common_storage_columns() );
    $data = [];

    foreach ( $common_fields as $key => $value ) {
        $key = sanitize_key( (string) $key );
        if ( isset( $allowed[ $key ] ) ) {
            $data[ $key ] = $value;
        }
    }

    if ( $vertical_key !== 'wedding' && ! empty( $extra_fields ) ) {
        $data['extra_fields'] = wp_json_encode( $extra_fields );
    }

    return $data;
}

function teinvit_get_versions_for_token_from_storage( $token, $vertical_key = '' ) {
    global $wpdb;
    $tables = teinvit_storage_tables_for_existing_token( $token, $vertical_key );
    if ( empty( $tables['versions'] ) ) {
        return [];
    }

    return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$tables['versions']} WHERE token = %s ORDER BY id DESC", $token ), ARRAY_A );
}

function teinvit_get_versions_for_token( $token ) {
    return teinvit_get_versions_for_token_from_storage( $token );
}

function teinvit_get_active_snapshot_for_token_from_storage( $token, $vertical_key = '' ) {
    global $wpdb;
    $tables = teinvit_storage_tables_for_existing_token( $token, $vertical_key );
    if ( empty( $tables['versions'] ) || empty( $tables['invitations'] ) ) {
        return null;
    }

    return $wpdb->get_row( $wpdb->prepare( "SELECT v.* FROM {$tables['versions']} v INNER JOIN {$tables['invitations']} i ON i.active_version_id = v.id WHERE i.token = %s LIMIT 1", $token ), ARRAY_A );
}

function teinvit_get_active_snapshot( $token ) {
    return teinvit_get_active_snapshot_for_token_from_storage( $token );
}

function teinvit_touch_invitation_activity_for_token( $token, $vertical_key = '' ) {
    global $wpdb;
    $tables = teinvit_storage_tables_for_existing_token( $token, $vertical_key );
    if ( empty( $tables['invitations'] ) ) {
        return false;
    }

    return $wpdb->update( $tables['invitations'], [ 'last_activity_at' => current_time( 'mysql' ) ], [ 'token' => $token ] );
}

function teinvit_touch_invitation_activity( $token ) {
    return teinvit_touch_invitation_activity_for_token( $token );
}

function teinvit_seed_invitation_if_missing( $token, $order_id ) {
    global $wpdb;

    $module_key = function_exists( 'teinvit_resolve_token_vertical' )
        ? teinvit_resolve_token_vertical( $token )
        : 'wedding';

    $module_key = sanitize_key( (string) $module_key );
    if ( $module_key === '' ) {
        $module_key = 'wedding';
    }

    $t = teinvit_db_tables();
    if ( $module_key !== 'wedding' && function_exists( 'teinvit_storage_tables_for_vertical' ) ) {
        $candidate = teinvit_storage_tables_for_vertical( $module_key );
        if ( is_array( $candidate ) && ! empty( $candidate['invitations'] ) && ! empty( $candidate['versions'] ) ) {
            $t = $candidate;
        }
    }

    $existing = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t['invitations']} WHERE token = %s", $token ), ARRAY_A );
    if ( $existing ) {
        $existing['config'] = json_decode( (string) $existing['config'], true );
        if ( ! is_array( $existing['config'] ) ) {
            $existing['config'] = [];
        }
        return $existing;
    }

    $order = wc_get_order( (int) $order_id );
    if ( $module_key === 'wedding' && $order && function_exists( 'teinvit_resolve_vertical_for_order' ) ) {
        $order_vertical = sanitize_key( (string) teinvit_resolve_vertical_for_order( $order ) );
        if ( in_array( $order_vertical, [ 'baptism', 'birthday' ], true ) ) {
            $module_key = $order_vertical;
            update_post_meta( (int) $order_id, '_teinvit_vertical_key_snapshot', $module_key );
            update_post_meta( (int) $order_id, '_teinvit_vertical_key', $module_key );
            if ( function_exists( 'teinvit_storage_tables_for_vertical' ) ) {
                $candidate = teinvit_storage_tables_for_vertical( $module_key );
                if ( is_array( $candidate ) && ! empty( $candidate['invitations'] ) && ! empty( $candidate['versions'] ) ) {
                    $t = $candidate;
                }
            }
        }
    }

    $built_payload = $order && function_exists( 'teinvit_build_invitation_payload_from_order' )
        ? teinvit_build_invitation_payload_from_order( $module_key, $order, $token )
        : [ 'invitation' => [], 'wapf_fields' => [] ];

    $invitation = isset( $built_payload['invitation'] ) && is_array( $built_payload['invitation'] ) ? $built_payload['invitation'] : [];
    $wapf_fields = isset( $built_payload['wapf_fields'] ) && is_array( $built_payload['wapf_fields'] ) ? $built_payload['wapf_fields'] : [];
    $snapshot_id = 0;

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
        'module_key'        => $module_key,
        'model_key'         => 'invn01',
        'active_version_id' => $snapshot_id,
        'event_date'        => null,
        'last_activity_at'  => current_time( 'mysql' ),
        'gifts_locked'      => 0,
        'config'            => wp_json_encode( function_exists( 'teinvit_default_rsvp_config_for_vertical' ) ? teinvit_default_rsvp_config_for_vertical( $module_key ) : teinvit_default_rsvp_config() ),
        'created_at'        => current_time( 'mysql' ),
        'updated_at'        => current_time( 'mysql' ),
    ] );

    $created = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t['invitations']} WHERE token = %s", $token ), ARRAY_A );
    if ( ! $created ) {
        return null;
    }

    $created['config'] = json_decode( (string) $created['config'], true );
    if ( ! is_array( $created['config'] ) ) {
        $created['config'] = [];
    }

    return $created;
}

function teinvit_default_rsvp_config() {
    if ( function_exists( 'teinvit_default_rsvp_config_for_vertical' ) ) {
        return teinvit_default_rsvp_config_for_vertical( 'wedding' );
    }

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

function teinvit_cleanup_legacy_cron_migration_once() {
    $flag_option = 'teinvit_cleanup_cron_legacy_removed_v1';
    if ( get_option( $flag_option, '0' ) === '1' ) {
        return;
    }

    if ( function_exists( 'wp_clear_scheduled_hook' ) ) {
        wp_clear_scheduled_hook( 'teinvit_cleanup_cron' );
    }

    update_option( $flag_option, '1', false );
}
add_action( 'plugins_loaded', 'teinvit_cleanup_legacy_cron_migration_once', 30 );
