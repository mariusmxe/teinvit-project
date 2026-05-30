<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'TEINVIT_EMAIL_SCHEMA_VERSION', 2 );
define( 'TEINVIT_EMAIL_SCHEMA_OPTION', 'teinvit_email_schema_version' );
define( 'TEINVIT_EMAIL_TEMPLATES_OPTION', 'teinvit_email_templates_v1' );
define( 'TEINVIT_EMAIL_SECRET_OPTION', 'teinvit_email_hmac_secret' );

function teinvit_email_tables() {
    global $wpdb;

    return [
        'sends'       => $wpdb->prefix . 'teinvit_email_sends',
        'events'      => $wpdb->prefix . 'teinvit_email_events',
        'suppression' => $wpdb->prefix . 'teinvit_email_suppression',
    ];
}

function teinvit_install_email_tables() {
    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $tables  = teinvit_email_tables();
    $charset = $wpdb->get_charset_collate();

    $sql_sends = "CREATE TABLE {$tables['sends']} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        send_id CHAR(36) NOT NULL,
        template_key VARCHAR(64) NOT NULL,
        template_id VARCHAR(64) NOT NULL,
        trigger_key VARCHAR(64) NOT NULL,
        audience_type VARCHAR(32) NOT NULL,
        token VARCHAR(191) NULL,
        order_id BIGINT UNSIGNED NULL,
        rsvp_id BIGINT UNSIGNED NULL,
        recipient_email VARCHAR(191) NOT NULL,
        recipient_hash CHAR(64) NOT NULL,
        subject_rendered VARCHAR(255) NOT NULL,
        heading_rendered VARCHAR(255) NULL,
        preheader_rendered VARCHAR(255) NULL,
        body_rendered LONGTEXT NULL,
        body_text LONGTEXT NULL,
        body_rendered_hash CHAR(64) NOT NULL,
        semantic_hash CHAR(64) NULL,
        status VARCHAR(16) NOT NULL,
        error_code VARCHAR(64) NULL,
        error_message TEXT NULL,
        scheduled_at DATETIME NULL,
        sent_at DATETIME NULL,
        created_at DATETIME NOT NULL,
        updated_at DATETIME NOT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY send_id (send_id),
        KEY template_status (template_id, status),
        KEY recipient_email (recipient_email),
        KEY token (token),
        KEY order_id (order_id),
        KEY schedule_status (scheduled_at, status)
    ) $charset;";

    $sql_events = "CREATE TABLE {$tables['events']} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        send_id CHAR(36) NOT NULL,
        event_type VARCHAR(16) NOT NULL,
        event_at DATETIME NOT NULL,
        ip_hash CHAR(64) NULL,
        ua_hash CHAR(64) NULL,
        url TEXT NULL,
        meta_json LONGTEXT NULL,
        PRIMARY KEY (id),
        KEY send_event (send_id, event_type),
        KEY event_at (event_at)
    ) $charset;";

    $sql_suppression = "CREATE TABLE {$tables['suppression']} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        email VARCHAR(191) NOT NULL,
        email_hash CHAR(64) NOT NULL,
        scope VARCHAR(32) NOT NULL,
        reason VARCHAR(64) NOT NULL,
        source_send_id CHAR(36) NULL,
        created_at DATETIME NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY email_scope (email, scope),
        KEY email_hash (email_hash)
    ) $charset;";

    dbDelta( $sql_sends );
    dbDelta( $sql_events );
    dbDelta( $sql_suppression );

    if ( ! get_option( TEINVIT_EMAIL_TEMPLATES_OPTION ) ) {
        update_option( TEINVIT_EMAIL_TEMPLATES_OPTION, teinvit_email_default_templates(), false );
    }

    update_option( TEINVIT_EMAIL_SCHEMA_OPTION, TEINVIT_EMAIL_SCHEMA_VERSION, false );
}

function teinvit_email_maybe_upgrade_schema() {
    $stored = (int) get_option( TEINVIT_EMAIL_SCHEMA_OPTION, 0 );
    if ( $stored < TEINVIT_EMAIL_SCHEMA_VERSION ) {
        teinvit_install_email_tables();
    }
}

function teinvit_email_default_templates() {
    return [
        'token_generated_customer' => [
            'id'               => 'token_generated_customer',
            'name'             => 'Token generated → Customer',
            'status'           => 'active',
            'subject'          => 'Invitația ta digitală este gata #{order_number}',
            'preheader'        => 'Administrează invitația în câteva clickuri.',
            'trigger'          => 'token_generated',
            'audience'         => 'customer',
            'delay_value'      => 0,
            'delay_unit'       => 'hours',
            'email_type'       => 'html',
            'accent_color'     => '#B07A4F',
            'is_marketing'     => 0,
            'apply_rate_limit' => 0,
            'require_consent'  => 0,
            'rate_limit_count' => 2,
            'rate_limit_days'  => 7,
            'product_ids'      => [],
            'blocks'           => [
                [ 'type' => 'logo', 'enabled' => 0, 'url' => '', 'align' => 'center' ],
                [ 'type' => 'title', 'text' => 'Administrare invitație' ],
                [ 'type' => 'text', 'html' => '<p>Salut, {customer_first_name}! Invitația ta este gata. Poți edita, publica, gestiona RSVP, cadouri și raport invitați.</p>' ],
                [ 'type' => 'button', 'label' => 'Administrare invitație', 'url' => '{admin_client_url}', 'style' => 'primary' ],
                [ 'type' => 'link', 'label' => 'Pagina invitaților', 'url' => '{invitati_url}' ],
                [ 'type' => 'footer', 'html' => '<p>Cu drag,<br>TeInvit</p>' ],
            ],
        ],
        'rsvp_received_customer' => [
            'id'               => 'rsvp_received_customer',
            'name'             => 'RSVP received → Customer',
            'status'           => 'active',
            'subject'          => 'Confirmare nouă primită pentru invitația ta',
            'preheader'        => 'Vezi detaliile RSVP.',
            'trigger'          => 'rsvp_saved',
            'audience'         => 'customer',
            'delay_value'      => 0,
            'delay_unit'       => 'hours',
            'email_type'       => 'html',
            'accent_color'     => '#B07A4F',
            'is_marketing'     => 0,
            'apply_rate_limit' => 0,
            'require_consent'  => 0,
            'rate_limit_count' => 2,
            'rate_limit_days'  => 7,
            'product_ids'      => [],
            'blocks'           => [
                [ 'type' => 'title', 'text' => 'Confirmare nouă primită' ],
                [ 'type' => 'subtitle', 'text' => 'Rezumat RSVP' ],
                [ 'type' => 'text', 'html' => '<p><strong>Nume:</strong> {guest_name}<br><strong>Telefon:</strong> {guest_phone}<br><strong>Email:</strong> {guest_email}</p>' ],
                [ 'type' => 'bullets', 'items' => [
                    'Adulți: {rsvp_adults}',
                    'Copii: {rsvp_children}',
                    'Civil: {rsvp_attending_civil}',
                    'Religios: {rsvp_attending_religious}',
                    'Petrecere: {rsvp_attending_party}',
                    'Cazare: {rsvp_accommodation}',
                    'Vegetarian: {rsvp_vegetarian} ({rsvp_vegetarian_menus})',
                    'Alergii: {rsvp_allergies}',
                    'Mesaj: {rsvp_message}',
                ] ],
                [ 'type' => 'button', 'label' => 'Vezi raportul invitaților', 'url' => '{report_url}', 'style' => 'primary' ],
                [ 'type' => 'footer', 'html' => '<p>Cu drag,<br>TeInvit</p>' ],
            ],
        ],
        'guest_marketing_consent_1' => [
            'id'               => 'guest_marketing_consent_1',
            'name'             => 'Guest consent #1 (24h)',
            'status'           => 'active',
            'subject'          => 'Inspirație pentru invitații digitale',
            'preheader'        => 'Descoperă modele și idei noi.',
            'trigger'          => 'guest_consent_1',
            'audience'         => 'guest',
            'delay_value'      => 24,
            'delay_unit'       => 'hours',
            'email_type'       => 'html',
            'accent_color'     => '#B07A4F',
            'is_marketing'     => 1,
            'apply_rate_limit' => 1,
            'require_consent'  => 1,
            'rate_limit_count' => 2,
            'rate_limit_days'  => 7,
            'product_ids'      => [],
            'blocks'           => [
                [ 'type' => 'title', 'text' => 'Invitații digitale TeInvit' ],
                [ 'type' => 'text', 'html' => '<p>Mulțumim pentru confirmare! Vezi cum funcționează invitațiile digitale și inspiră-te din cele mai noi modele.</p>' ],
                [ 'type' => 'button', 'label' => 'Vezi modele', 'url' => 'https://www.teinvit.com/magazin/', 'style' => 'primary' ],
                [ 'type' => 'footer', 'html' => '<p>{why_received_text}</p><p><a href="{unsubscribe_url}">Dezabonare</a></p>' ],
            ],
        ],
    ];
}

function teinvit_email_default_template_ids() {
    return [
        'token_generated_customer',
        'rsvp_received_customer',
        'guest_marketing_consent_1',
    ];
}


function teinvit_email_trigger_options() {
    return [
        'token_generated' => 'Token generated',
        'rsvp_saved' => 'RSVP received',
        'guest_consent_1' => 'Guest consent #1',
        'product_purchased' => 'Product purchased (global)',
        'invitation_version_saved' => 'Invitation version saved',
    ];
}


function teinvit_email_trigger_label( $trigger_key ) {
    $trigger_key = sanitize_key( (string) $trigger_key );
    $options = teinvit_email_trigger_options();
    return $options[ $trigger_key ] ?? $trigger_key;
}

function teinvit_email_is_default_template( $template_id ) {
    return in_array( (string) $template_id, teinvit_email_default_template_ids(), true );
}

function teinvit_email_default_blocks_for_template( $template_id ) {
    $defaults = teinvit_email_default_templates();
    $tpl      = $defaults[ $template_id ] ?? null;
    if ( ! is_array( $tpl ) ) {
        return [
            [ 'type' => 'title', 'text' => 'TeInvit Email' ],
            [ 'type' => 'text', 'html' => '<p>Acesta este un email TeInvit.</p>' ],
            [ 'type' => 'button', 'label' => 'Deschide TeInvit', 'url' => home_url( '/' ), 'style' => 'primary' ],
        ];
    }

    $blocks = is_array( $tpl['blocks'] ?? null ) ? $tpl['blocks'] : [];

    return empty( $blocks ) ? [
        [ 'type' => 'title', 'text' => 'TeInvit Email' ],
        [ 'type' => 'text', 'html' => '<p>Acesta este un email TeInvit.</p>' ],
    ] : $blocks;
}

function teinvit_get_email_templates() {
    $defaults = teinvit_email_default_templates();
    $stored   = get_option( TEINVIT_EMAIL_TEMPLATES_OPTION, [] );
    if ( ! is_array( $stored ) ) {
        $stored = [];
    }

    $templates = $defaults;
    foreach ( $stored as $id => $tpl ) {
        if ( ! is_array( $tpl ) ) {
            continue;
        }
        $base = is_array( $templates[ $id ] ?? null ) ? $templates[ $id ] : [];
        $templates[ $id ] = array_merge( $base, $tpl );
    }

    foreach ( $templates as $id => $tpl ) {
        if ( empty( $tpl['blocks'] ) || ! is_array( $tpl['blocks'] ) ) {
            $templates[ $id ]['blocks'] = teinvit_email_default_blocks_for_template( $id );
        }
    }

    return $templates;
}

function teinvit_get_email_template( $template_id ) {
    $templates = teinvit_get_email_templates();
    $template  = $templates[ $template_id ] ?? null;
    if ( ! is_array( $template ) ) {
        return null;
    }

    if ( empty( $template['blocks'] ) || ! is_array( $template['blocks'] ) ) {
        $template['blocks'] = teinvit_email_default_blocks_for_template( $template_id );
    }

    return $template;
}

function teinvit_update_email_template( $template_id, array $data ) {
    $templates                 = teinvit_get_email_templates();
    $templates[ $template_id ] = $data;
    update_option( TEINVIT_EMAIL_TEMPLATES_OPTION, $templates, false );
}

function teinvit_email_set_template_status( $template_id, $status ) {
    $templates = teinvit_get_email_templates();
    if ( empty( $templates[ $template_id ] ) || ! is_array( $templates[ $template_id ] ) ) {
        return;
    }

    $templates[ $template_id ]['status'] = $status === 'active' ? 'active' : 'draft';
    update_option( TEINVIT_EMAIL_TEMPLATES_OPTION, $templates, false );
}

function teinvit_email_duplicate_template( $template_id ) {
    $template = teinvit_get_email_template( $template_id );
    if ( ! $template ) {
        return '';
    }

    $suffix = '_copy_' . strtolower( wp_generate_password( 4, false, false ) );
    $base   = substr( sanitize_key( (string) $template_id ), 0, max( 1, 64 - strlen( $suffix ) ) );
    $new_id = sanitize_key( $base . $suffix );
    $tries  = 0;
    while ( teinvit_get_email_template( $new_id ) && $tries < 10 ) {
        $suffix = '_copy_' . strtolower( wp_generate_password( 4, false, false ) );
        $base   = substr( sanitize_key( (string) $template_id ), 0, max( 1, 64 - strlen( $suffix ) ) );
        $new_id = sanitize_key( $base . $suffix );
        $tries++;
    }

    if ( teinvit_get_email_template( $new_id ) ) {
        return '';
    }

    $template['id'] = $new_id;
    $template['name'] = (string) ( $template['name'] ?? $template_id ) . ' (Copy)';
    teinvit_update_email_template( $new_id, $template );

    return $new_id;
}

function teinvit_email_delete_template( $template_id ) {
    $template_id = sanitize_key( (string) $template_id );
    if ( $template_id === '' || teinvit_email_is_default_template( $template_id ) ) {
        return false;
    }

    $templates = get_option( TEINVIT_EMAIL_TEMPLATES_OPTION, [] );
    if ( ! is_array( $templates ) || empty( $templates[ $template_id ] ) ) {
        return false;
    }

    unset( $templates[ $template_id ] );
    update_option( TEINVIT_EMAIL_TEMPLATES_OPTION, $templates, false );

    return true;
}

function teinvit_email_get_secret() {
    if ( defined( 'TEINVIT_EMAIL_HMAC_SECRET' ) && TEINVIT_EMAIL_HMAC_SECRET ) {
        return (string) TEINVIT_EMAIL_HMAC_SECRET;
    }

    $secret = (string) get_option( TEINVIT_EMAIL_SECRET_OPTION, '' );
    if ( $secret !== '' ) {
        return $secret;
    }

    $secret = wp_generate_password( 64, true, true );
    update_option( TEINVIT_EMAIL_SECRET_OPTION, $secret, false );

    return $secret;
}

function teinvit_email_sign( $payload ) {
    return hash_hmac( 'sha256', $payload, teinvit_email_get_secret() );
}

function teinvit_email_uuid_v4() {
    $data    = random_bytes( 16 );
    $data[6] = chr( ord( $data[6] ) & 0x0f | 0x40 );
    $data[8] = chr( ord( $data[8] ) & 0x3f | 0x80 );

    return vsprintf( '%s%s-%s-%s-%s-%s%s%s', str_split( bin2hex( $data ), 4 ) );
}

function teinvit_email_b64url_encode( $data ) {
    return rtrim( strtr( base64_encode( (string) $data ), '+/', '-_' ), '=' );
}

function teinvit_email_b64url_decode( $data ) {
    $data = strtr( (string) $data, '-_', '+/' );
    $pad  = strlen( $data ) % 4;
    if ( $pad ) {
        $data .= str_repeat( '=', 4 - $pad );
    }

    return base64_decode( $data, true );
}

function teinvit_email_normalize_phone( $phone ) {
    $phone = trim( (string) $phone );
    return preg_replace( '/\s+/', '', $phone );
}

function teinvit_email_payload_semantic_hash( array $payload ) {
    $keys = [
        'guest_first_name',
        'guest_last_name',
        'guest_phone',
        'guest_email',
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
    ];

    $norm = [];
    foreach ( $keys as $key ) {
        $norm[ $key ] = isset( $payload[ $key ] ) ? (string) $payload[ $key ] : '';
    }
    if ( isset( $payload['extra_fields'] ) ) {
        $extra = is_array( $payload['extra_fields'] ) ? $payload['extra_fields'] : teinvit_email_decode_json_array( $payload['extra_fields'] );
        $norm['extra_fields'] = wp_json_encode( is_array( $extra ) ? $extra : [] );
    }

    return hash( 'sha256', wp_json_encode( $norm ) );
}

function teinvit_email_debug_enabled() {
    if ( defined( 'TEINVIT_EMAIL_DEBUG' ) ) {
        return (bool) TEINVIT_EMAIL_DEBUG;
    }

    $value = get_option( 'teinvit_email_debug', '' );
    if ( is_bool( $value ) ) {
        return $value;
    }

    return in_array( strtolower( trim( (string) $value ) ), [ '1', 'yes', 'true', 'on' ], true );
}

function teinvit_email_bool_label( $value ) {
    if ( is_string( $value ) ) {
        $normalized = strtolower( trim( $value ) );
        if ( in_array( $normalized, [ '1', 'yes', 'true', 'on', 'da' ], true ) ) {
            return 'Da';
        }
        if ( in_array( $normalized, [ '0', 'no', 'false', 'off', 'nu', '' ], true ) ) {
            return 'Nu';
        }
    }

    return ! empty( $value ) ? 'Da' : 'Nu';
}

function teinvit_email_join_list( $value, $separator = ', ' ) {
    if ( ! is_array( $value ) ) {
        return trim( (string) $value );
    }

    $items = [];
    foreach ( $value as $item ) {
        if ( is_array( $item ) ) {
            $item = $item['name'] ?? ( $item['title'] ?? ( $item['label'] ?? '' ) );
        }

        $item = trim( (string) $item );
        if ( $item !== '' ) {
            $items[] = $item;
        }
    }

    return implode( (string) $separator, $items );
}

function teinvit_email_array_path_value( array $data, $path, $default = '' ) {
    $current = $data;
    foreach ( explode( '.', (string) $path ) as $part ) {
        if ( $part === '' ) {
            continue;
        }
        if ( ! is_array( $current ) || ! array_key_exists( $part, $current ) ) {
            return $default;
        }
        $current = $current[ $part ];
    }

    return $current;
}

function teinvit_email_decode_json_array( $raw ) {
    if ( is_array( $raw ) ) {
        return $raw;
    }

    $decoded = json_decode( (string) $raw, true );
    return is_array( $decoded ) ? $decoded : [];
}

function teinvit_email_invitation_from_decoded_payload( array $payload ) {
    foreach ( [ 'invitation', 'snapshot.invitation', 'payload.invitation', 'data.invitation' ] as $path ) {
        $value = teinvit_email_array_path_value( $payload, $path, [] );
        if ( is_array( $value ) && ! empty( $value ) ) {
            return $value;
        }
    }

    if ( isset( $payload['events'] ) || isset( $payload['names'] ) || isset( $payload['children'] ) || isset( $payload['celebrants'] ) ) {
        return $payload;
    }

    return [];
}

function teinvit_email_invitation_context_for_token( $token, $vertical = '' ) {
    $token    = sanitize_text_field( (string) $token );
    $vertical = sanitize_key( (string) $vertical );

    if ( $vertical === '' && $token !== '' && function_exists( 'teinvit_resolve_token_vertical' ) ) {
        $vertical = sanitize_key( (string) teinvit_resolve_token_vertical( $token ) );
    }
    if ( $vertical === '' ) {
        $vertical = 'wedding';
    }

    $record = ( $token !== '' && function_exists( 'teinvit_get_invitation_record' ) ) ? teinvit_get_invitation_record( $token, $vertical ) : null;
    $record = is_array( $record ) ? $record : [];
    if ( ! empty( $record['module_key'] ) ) {
        $vertical = sanitize_key( (string) $record['module_key'] );
    }

    $config = is_array( $record['config'] ?? null ) ? $record['config'] : [];
    $invitation = [];
    $snapshot = ( $token !== '' && function_exists( 'teinvit_get_active_snapshot_for_token_from_storage' ) ) ? teinvit_get_active_snapshot_for_token_from_storage( $token, $vertical ) : null;
    $snapshot = is_array( $snapshot ) ? $snapshot : [];

    foreach ( [ 'snapshot', 'data_json', 'payload', 'data' ] as $key ) {
        if ( empty( $snapshot[ $key ] ) ) {
            continue;
        }

        $decoded = teinvit_email_decode_json_array( $snapshot[ $key ] );
        $invitation = teinvit_email_invitation_from_decoded_payload( $decoded );
        if ( ! empty( $invitation ) ) {
            break;
        }
    }

    if ( empty( $invitation ) ) {
        $invitation = teinvit_email_invitation_from_decoded_payload( $config );
    }

    if ( empty( $invitation ) && $token !== '' && function_exists( 'teinvit_get_active_version_data' ) ) {
        $legacy = teinvit_get_active_version_data( $token );
        $legacy = is_array( $legacy ) ? $legacy : [];
        foreach ( [ 'snapshot', 'data_json', 'payload', 'data' ] as $key ) {
            if ( empty( $legacy[ $key ] ) ) {
                continue;
            }

            $decoded = teinvit_email_decode_json_array( $legacy[ $key ] );
            $invitation = teinvit_email_invitation_from_decoded_payload( $decoded );
            if ( ! empty( $invitation ) ) {
                $snapshot = $legacy;
                break;
            }
        }
    }

    return [
        'vertical'   => $vertical,
        'record'     => $record,
        'config'     => $config,
        'snapshot'   => $snapshot,
        'invitation' => is_array( $invitation ) ? $invitation : [],
    ];
}

function teinvit_email_invitation_event( array $invitation, $key, array $title_needles = [] ) {
    $events = is_array( $invitation['events'] ?? null ) ? $invitation['events'] : [];
    $key    = sanitize_key( (string) $key );

    if ( $key !== '' && isset( $events[ $key ] ) && is_array( $events[ $key ] ) ) {
        return $events[ $key ];
    }

    foreach ( $events as $event ) {
        if ( ! is_array( $event ) ) {
            continue;
        }

        $title = strtolower( (string) ( $event['title'] ?? '' ) );
        foreach ( $title_needles as $needle ) {
            if ( $needle !== '' && strpos( $title, strtolower( (string) $needle ) ) !== false ) {
                return $event;
            }
        }
    }

    return [];
}

function teinvit_email_order_product_names( $order ) {
    if ( ! $order || ! method_exists( $order, 'get_items' ) ) {
        return [];
    }

    $names = [];
    foreach ( $order->get_items( 'line_item' ) as $item ) {
        if ( ! $item || ! is_a( $item, 'WC_Order_Item_Product' ) ) {
            continue;
        }

        $name = trim( (string) $item->get_name() );
        if ( $name !== '' ) {
            $names[] = $name;
        }
    }

    return array_values( array_unique( $names ) );
}

function teinvit_email_package_type_for_token( $token ) {
    $token = sanitize_text_field( (string) $token );
    if ( $token === '' ) {
        return '';
    }

    if ( function_exists( 'teinvit_resolve_token_premium_source' ) ) {
        $source = sanitize_key( (string) teinvit_resolve_token_premium_source( $token ) );
        if ( strpos( $source, 'basic_upgraded' ) === 0 ) {
            return 'Basic + Upgrade';
        }
        if ( $source === 'basic_pure' ) {
            return 'Basic';
        }
        if ( $source !== '' ) {
            return 'Premium';
        }
    }

    return '';
}

function teinvit_email_payload_extra_fields( array $payload ) {
    $extra = [];
    if ( isset( $payload['extra_fields'] ) ) {
        $extra = teinvit_email_decode_json_array( $payload['extra_fields'] );
    }

    foreach ( $payload as $key => $value ) {
        if ( ! array_key_exists( $key, $extra ) ) {
            $extra[ $key ] = $value;
        }
    }

    return $extra;
}

function teinvit_email_payload_value( array $payload, array $extra, $key, $default = '' ) {
    if ( array_key_exists( $key, $payload ) ) {
        return $payload[ $key ];
    }

    if ( array_key_exists( $key, $extra ) ) {
        return $extra[ $key ];
    }

    return $default;
}

function teinvit_email_gift_context_for_token( $token, $vertical, array $payload ) {
    global $wpdb;

    $token    = sanitize_text_field( (string) $token );
    $vertical = sanitize_key( (string) $vertical );
    $out      = [
        'gift_reserved_name'      => '',
        'gift_reserved_link'      => '',
        'gift_delivery_address'   => '',
        'gift_status'             => '',
        'gift_reserved_list'      => '',
        'gift_public_count'       => '',
        'gift_reserved_count'     => '',
    ];

    if ( $token === '' || ! function_exists( 'teinvit_gifts_table_for_token' ) ) {
        return $out;
    }

    $table = teinvit_gifts_table_for_token( $token, $vertical );
    if ( $table === '' ) {
        return $out;
    }

    $public_count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE token=%s AND include_in_public=1", $token ) );
    $reserved_count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE token=%s AND status='reserved'", $token ) );

    $selected = [];
    foreach ( [ 'gift_ids', 'selected_gift_ids', 'reserved_gift_ids', 'gift_id' ] as $key ) {
        if ( empty( $payload[ $key ] ) ) {
            continue;
        }
        $raw = is_array( $payload[ $key ] ) ? $payload[ $key ] : preg_split( '/[^a-zA-Z0-9_-]+/', (string) $payload[ $key ] );
        foreach ( (array) $raw as $gift_id ) {
            $gift_id = sanitize_text_field( (string) $gift_id );
            if ( $gift_id !== '' ) {
                $selected[] = $gift_id;
            }
        }
    }

    $selected = array_values( array_unique( $selected ) );
    $rows = [];
    if ( ! empty( $selected ) ) {
        $placeholders = implode( ',', array_fill( 0, count( $selected ), '%s' ) );
        $sql_args = array_merge( [ $token ], $selected );
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT gift_id,gift_name,gift_link,gift_delivery_address,status FROM {$table} WHERE token=%s AND gift_id IN ($placeholders) ORDER BY id ASC",
                $sql_args
            ),
            ARRAY_A
        );
    }

    $out['gift_public_count']   = (string) $public_count;
    $out['gift_reserved_count'] = (string) $reserved_count;

    if ( ! empty( $rows ) ) {
        $out['gift_reserved_name']    = teinvit_email_join_list( wp_list_pluck( $rows, 'gift_name' ) );
        $out['gift_reserved_link']    = teinvit_email_join_list( wp_list_pluck( $rows, 'gift_link' ) );
        $out['gift_delivery_address'] = teinvit_email_join_list( wp_list_pluck( $rows, 'gift_delivery_address' ), ' | ' );
        $out['gift_status']           = teinvit_email_join_list( wp_list_pluck( $rows, 'status' ) );
        $out['gift_reserved_list']    = implode( "\n", array_filter( array_map( static function( $row ) {
            return trim( (string) ( $row['gift_name'] ?? '' ) . ( ! empty( $row['gift_link'] ) ? ' - ' . (string) $row['gift_link'] : '' ) );
        }, $rows ) ) );
    }

    return $out;
}

function teinvit_email_merge_tags_catalog_registry() {
    $items = [];
    $add = static function( $slug, $category, $description, $context, $example, $availability ) use ( &$items ) {
        $items[ $slug ] = [
            'tag'          => '{' . $slug . '}',
            'description'  => $description,
            'context'      => $context,
            'category'     => $category,
            'example'      => $example,
            'availability' => $availability,
        ];
    };

    $add( 'site_name', 'General', 'Numele website-ului.', 'Global', 'TeInvit', 'Toate template-urile' );
    $add( 'token', 'General', 'Tokenul invitatiei.', 'Global', '1468-abc123', 'Toate template-urile cu token' );
    $add( 'order_id', 'General', 'ID-ul comenzii WooCommerce.', 'Customer', '1468', 'token_generated, rsvp_saved, guest_consent_1' );
    $add( 'order_number', 'General', 'Numarul comenzii WooCommerce.', 'Customer', '1468', 'token_generated, rsvp_saved, guest_consent_1' );
    $add( 'customer_name', 'General', 'Numele complet al clientului.', 'Customer', 'Alex Popescu', 'Emailuri catre customer' );
    $add( 'customer_first_name', 'General', 'Prenumele clientului.', 'Customer', 'Alex', 'Emailuri catre customer' );
    $add( 'customer_last_name', 'General', 'Numele de familie al clientului.', 'Customer', 'Popescu', 'Emailuri catre customer' );
    $add( 'customer_email', 'General', 'Emailul clientului.', 'Customer', 'alex@example.com', 'Emailuri catre customer' );
    $add( 'product_name', 'General', 'Numele produsului/produselor din comanda.', 'Customer', 'Invitatie Botez Premium', 'Cu order context' );
    $add( 'product_ids', 'General', 'ID-urile produselor si variatiilor din comanda.', 'Customer', '1067,1150', 'Cu order context' );
    $add( 'vertical', 'General', 'Verticala tokenului.', 'Global', 'baptism', 'Toate template-urile cu token' );
    $add( 'package_type', 'General', 'Tipul pachetului tokenului.', 'Global', 'Basic + Upgrade', 'Cand statusul poate fi rezolvat' );
    $add( 'premium_source', 'General', 'Sursa statutului Premium.', 'Global', 'basic_upgraded_woo', 'Cand resolverul de premium este disponibil' );
    $add( 'admin_client_url', 'General', 'Link catre administrarea invitatiei.', 'Customer', 'https://site.test/admin-client/token', 'Emailuri catre customer' );
    $add( 'invitati_url', 'General', 'Link public catre pagina invitatilor.', 'Customer/Guest', 'https://site.test/invitati/token', 'Cu token valid' );
    $add( 'report_url', 'General', 'Link catre raportul de invitati.', 'Customer', 'https://site.test/admin-client/token#teinvit-report', 'Emailuri catre customer' );
    $add( 'invitation_pdf_url', 'General', 'Linkul PDF al invitatiei, daca exista.', 'Customer', 'https://site.test/file.pdf', 'Dupa generare PDF' );
    $add( 'invitation_pdf_status', 'General', 'Statusul PDF al invitatiei.', 'Customer', 'ready', 'Dupa generare PDF' );
    $add( 'invitation_version_id', 'General', 'ID-ul versiunii de invitatie.', 'Customer', '123', 'invitation_version_saved' );
    $add( 'invitation_version_index', 'General', 'Numarul versiunii de invitatie.', 'Customer', '1', 'invitation_version_saved' );

    $add( 'wedding_bride_name', 'Wedding', 'Numele miresei, daca poate fi separat din invitatia Wedding.', 'Wedding', 'Ana', 'Wedding' );
    $add( 'wedding_groom_name', 'Wedding', 'Numele mirelui, daca poate fi separat din invitatia Wedding.', 'Wedding', 'Mihai', 'Wedding' );
    $add( 'wedding_couple_name', 'Wedding', 'Numele cuplului.', 'Wedding', 'Ana & Mihai', 'Wedding' );
    $add( 'wedding_message', 'Wedding', 'Mesajul invitatiei Wedding.', 'Wedding', 'Va asteptam cu drag', 'Wedding' );
    $add( 'wedding_theme', 'Wedding', 'Tema vizuala Wedding.', 'Wedding', 'editorial', 'Wedding' );
    $add( 'wedding_parents_bride', 'Wedding', 'Parintii miresei.', 'Wedding', 'Maria & Ion', 'Wedding' );
    $add( 'wedding_parents_groom', 'Wedding', 'Parintii mirelui.', 'Wedding', 'Elena & Andrei', 'Wedding' );
    $add( 'wedding_nasi', 'Wedding', 'Nasii.', 'Wedding', 'Ioana & Vlad', 'Wedding' );
    $add( 'wedding_civil_location', 'Wedding', 'Locatia cununiei civile.', 'Wedding', 'Primarie', 'Wedding' );
    $add( 'wedding_civil_date', 'Wedding', 'Data si ora cununiei civile.', 'Wedding', '12.07.2026, 12:00', 'Wedding' );
    $add( 'wedding_civil_waze', 'Wedding', 'Link Waze pentru cununia civila.', 'Wedding', 'https://waze.com/...', 'Wedding' );
    $add( 'wedding_religious_location', 'Wedding', 'Locatia ceremoniei religioase.', 'Wedding', 'Biserica', 'Wedding' );
    $add( 'wedding_religious_date', 'Wedding', 'Data si ora ceremoniei religioase.', 'Wedding', '12.07.2026, 15:00', 'Wedding' );
    $add( 'wedding_religious_waze', 'Wedding', 'Link Waze pentru ceremonia religioasa.', 'Wedding', 'https://waze.com/...', 'Wedding' );
    $add( 'wedding_party_location', 'Wedding', 'Locatia petrecerii.', 'Wedding', 'Restaurant', 'Wedding' );
    $add( 'wedding_party_date', 'Wedding', 'Data si ora petrecerii.', 'Wedding', '12.07.2026, 19:00', 'Wedding' );
    $add( 'wedding_party_waze', 'Wedding', 'Link Waze pentru petrecere.', 'Wedding', 'https://waze.com/...', 'Wedding' );
    $add( 'wedding_rsvp_deadline', 'Wedding', 'Data limita pentru confirmari Wedding.', 'Wedding', '01/07/2026', 'Wedding, daca este configurata' );

    $add( 'baptism_child_names', 'Baptism', 'Numele copilului/copiilor.', 'Baptism', 'Sofia', 'Baptism' );
    $add( 'baptism_headline', 'Baptism', 'Headline-ul invitatiei de botez.', 'Baptism', 'Sofia', 'Baptism' );
    $add( 'baptism_message', 'Baptism', 'Mesajul invitatiei de botez.', 'Baptism', 'Va asteptam cu drag', 'Baptism' );
    $add( 'baptism_theme', 'Baptism', 'Tema vizuala Baptism.', 'Baptism', 'little-princess', 'Baptism' );
    $add( 'baptism_mother', 'Baptism', 'Numele mamei.', 'Baptism', 'Maria', 'Baptism' );
    $add( 'baptism_father', 'Baptism', 'Numele tatalui.', 'Baptism', 'Ion', 'Baptism' );
    $add( 'baptism_godmother', 'Baptism', 'Numele nasei.', 'Baptism', 'Ioana', 'Baptism' );
    $add( 'baptism_godfather', 'Baptism', 'Numele nasului.', 'Baptism', 'Vlad', 'Baptism' );
    $add( 'baptism_religious_location', 'Baptism', 'Locatia slujbei de botez.', 'Baptism', 'Biserica', 'Baptism' );
    $add( 'baptism_religious_date', 'Baptism', 'Data si ora slujbei de botez.', 'Baptism', '20.08.2026, 12:00', 'Baptism' );
    $add( 'baptism_religious_waze', 'Baptism', 'Link Waze pentru slujba de botez.', 'Baptism', 'https://waze.com/...', 'Baptism' );
    $add( 'baptism_party_location', 'Baptism', 'Locatia petrecerii de botez.', 'Baptism', 'Restaurant', 'Baptism' );
    $add( 'baptism_party_date', 'Baptism', 'Data si ora petrecerii de botez.', 'Baptism', '20.08.2026, 15:00', 'Baptism' );
    $add( 'baptism_party_waze', 'Baptism', 'Link Waze pentru petrecerea de botez.', 'Baptism', 'https://waze.com/...', 'Baptism' );
    $add( 'baptism_rsvp_deadline', 'Baptism', 'Data limita pentru confirmari Baptism.', 'Baptism', '10/08/2026', 'Baptism, daca este configurata' );

    $add( 'birthday_celebrant_names', 'Birthday', 'Numele sarbatoritului/sarbatoritilor.', 'Birthday', 'Matei', 'Birthday' );
    $add( 'birthday_headline', 'Birthday', 'Headline-ul invitatiei Birthday.', 'Birthday', 'Matei', 'Birthday' );
    $add( 'birthday_age', 'Birthday', 'Varsta sarbatoritului.', 'Birthday', '7', 'Birthday, daca este configurata' );
    $add( 'birthday_age_line', 'Birthday', 'Textul de varsta afisat in invitatie.', 'Birthday', 'Implinesc 7 ani!', 'Birthday' );
    $add( 'birthday_event_name', 'Birthday', 'Numele evenimentului Birthday.', 'Birthday', 'Petrecerea lui Matei', 'Birthday' );
    $add( 'birthday_message', 'Birthday', 'Mesajul invitatiei Birthday.', 'Birthday', 'Te astept cu drag', 'Birthday' );
    $add( 'birthday_theme', 'Birthday', 'Tema vizuala Birthday.', 'Birthday', 'editorial-luxury', 'Birthday' );
    $add( 'birthday_party_location', 'Birthday', 'Locatia petrecerii Birthday.', 'Birthday', 'Kids Club', 'Birthday' );
    $add( 'birthday_party_date', 'Birthday', 'Data si ora petrecerii Birthday.', 'Birthday', '30.09.2026, 17:00', 'Birthday' );
    $add( 'birthday_party_weekday', 'Birthday', 'Ziua saptamanii pentru petrecere.', 'Birthday', 'Sambata', 'Birthday' );
    $add( 'birthday_party_waze', 'Birthday', 'Link Waze pentru petrecere.', 'Birthday', 'https://waze.com/...', 'Birthday' );
    $add( 'birthday_rsvp_mode', 'Birthday', 'Modul RSVP Birthday: adult sau child.', 'Birthday', 'child', 'Birthday' );
    $add( 'birthday_party_theme', 'Birthday', 'Tematica petrecerii Birthday.', 'Birthday', 'Supereroi', 'Birthday, daca este configurata' );
    $add( 'birthday_dress_code', 'Birthday', 'Dress code-ul Birthday.', 'Birthday', 'Casual', 'Birthday, daca este configurat' );
    $add( 'birthday_rsvp_deadline', 'Birthday', 'Data limita pentru confirmari Birthday.', 'Birthday', '20/09/2026', 'Birthday, daca este configurata' );

    $add( 'guest_name', 'RSVP', 'Numele complet al invitatului.', 'RSVP', 'Maria Ionescu', 'rsvp_saved, guest_consent_1' );
    $add( 'guest_full_name', 'RSVP', 'Alias pentru numele complet al invitatului.', 'RSVP', 'Maria Ionescu', 'rsvp_saved, guest_consent_1' );
    $add( 'guest_first_name', 'RSVP', 'Prenumele invitatului.', 'RSVP', 'Maria', 'rsvp_saved, guest_consent_1' );
    $add( 'guest_last_name', 'RSVP', 'Numele de familie al invitatului.', 'RSVP', 'Ionescu', 'rsvp_saved, guest_consent_1' );
    $add( 'guest_email', 'RSVP', 'Emailul invitatului.', 'RSVP/Guest', 'maria@example.com', 'guest_consent_1 si RSVP cu email' );
    $add( 'guest_phone', 'RSVP', 'Telefonul invitatului.', 'RSVP', '0712345678', 'rsvp_saved' );
    $add( 'rsvp_attending_status', 'RSVP', 'Status general participare.', 'RSVP', 'Participa', 'rsvp_saved' );
    $add( 'rsvp_total_people', 'RSVP', 'Numarul total de persoane confirmate.', 'RSVP', '2', 'rsvp_saved' );
    $add( 'rsvp_adults', 'RSVP', 'Numarul de adulti confirmati.', 'RSVP', '2', 'rsvp_saved' );
    $add( 'rsvp_children', 'RSVP', 'Numarul de copii confirmati.', 'RSVP', '1', 'rsvp_saved' );
    $add( 'rsvp_created_at', 'RSVP', 'Data confirmarii RSVP.', 'RSVP', '2026-05-30 12:00:00', 'rsvp_saved' );
    $add( 'rsvp_message', 'RSVP', 'Mesajul invitatului.', 'RSVP', 'Abia asteptam!', 'rsvp_saved' );
    $add( 'rsvp_gdpr_accepted', 'RSVP', 'Acordul GDPR din RSVP.', 'RSVP', 'Da', 'rsvp_saved' );
    $add( 'rsvp_marketing_consent', 'RSVP', 'Acordul marketing din RSVP.', 'Guest', 'Da', 'guest_consent_1' );
    $add( 'rsvp_bringing_kids', 'RSVP', 'Alias legacy: invitatul vine cu copii.', 'RSVP', 'Nu', 'rsvp_saved' );
    $add( 'rsvp_attending_civil', 'RSVP', 'Alias legacy: participare la cununia civila.', 'RSVP', 'Da', 'Wedding rsvp_saved' );
    $add( 'rsvp_attending_religious', 'RSVP', 'Alias legacy: participare la ceremonie/slujba religioasa.', 'RSVP', 'Da', 'Wedding/Baptism rsvp_saved' );
    $add( 'rsvp_attending_party', 'RSVP', 'Alias legacy: participare la petrecere.', 'RSVP', 'Da', 'rsvp_saved' );
    $add( 'rsvp_accommodation', 'RSVP', 'Alias legacy: cazare solicitata.', 'RSVP', 'Nu', 'rsvp_saved' );
    $add( 'rsvp_accommodation_people', 'RSVP', 'Alias legacy: numar persoane cazare.', 'RSVP', '2', 'rsvp_saved' );
    $add( 'rsvp_vegetarian', 'RSVP', 'Alias legacy: meniu vegetarian solicitat.', 'RSVP', 'Da', 'rsvp_saved' );
    $add( 'rsvp_vegetarian_menus', 'RSVP', 'Alias legacy: numar meniuri vegetariene.', 'RSVP', '1', 'rsvp_saved' );
    $add( 'rsvp_has_allergies', 'RSVP', 'Alias legacy: invitatul a declarat alergii.', 'RSVP', 'Nu', 'rsvp_saved' );
    $add( 'rsvp_allergies', 'RSVP', 'Alias legacy: detalii alergii.', 'RSVP', 'Arahide', 'rsvp_saved' );

    $add( 'rsvp_wedding_attending_civil', 'RSVP Wedding', 'Participare la cununia civila.', 'Wedding RSVP', 'Da', 'Wedding rsvp_saved' );
    $add( 'rsvp_wedding_attending_religious', 'RSVP Wedding', 'Participare la ceremonia religioasa.', 'Wedding RSVP', 'Da', 'Wedding rsvp_saved' );
    $add( 'rsvp_wedding_attending_party', 'RSVP Wedding', 'Participare la petrecere.', 'Wedding RSVP', 'Da', 'Wedding rsvp_saved' );
    $add( 'rsvp_wedding_bringing_kids', 'RSVP Wedding', 'Invitatul vine cu copii.', 'Wedding RSVP', 'Nu', 'Wedding rsvp_saved' );
    $add( 'rsvp_wedding_kids_count', 'RSVP Wedding', 'Numarul de copii.', 'Wedding RSVP', '1', 'Wedding rsvp_saved' );
    $add( 'rsvp_wedding_accommodation', 'RSVP Wedding', 'Solicitare cazare.', 'Wedding RSVP', 'Nu', 'Wedding rsvp_saved' );
    $add( 'rsvp_wedding_accommodation_people', 'RSVP Wedding', 'Numar persoane cazare.', 'Wedding RSVP', '2', 'Wedding rsvp_saved' );
    $add( 'rsvp_wedding_vegetarian', 'RSVP Wedding', 'Solicitare meniu vegetarian.', 'Wedding RSVP', 'Da', 'Wedding rsvp_saved' );
    $add( 'rsvp_wedding_vegetarian_menus', 'RSVP Wedding', 'Numar meniuri vegetariene.', 'Wedding RSVP', '1', 'Wedding rsvp_saved' );
    $add( 'rsvp_wedding_has_allergies', 'RSVP Wedding', 'Invitatul a declarat alergii.', 'Wedding RSVP', 'Nu', 'Wedding rsvp_saved' );
    $add( 'rsvp_wedding_allergies', 'RSVP Wedding', 'Detalii alergii.', 'Wedding RSVP', 'Arahide', 'Wedding rsvp_saved' );
    $add( 'rsvp_wedding_message_to_couple', 'RSVP Wedding', 'Mesaj catre miri.', 'Wedding RSVP', 'Casa de piatra!', 'Wedding rsvp_saved' );

    $add( 'rsvp_baptism_attending_religious', 'RSVP Baptism', 'Participare la slujba de botez.', 'Baptism RSVP', 'Da', 'Baptism rsvp_saved' );
    $add( 'rsvp_baptism_attending_party', 'RSVP Baptism', 'Participare la petrecerea de botez.', 'Baptism RSVP', 'Da', 'Baptism rsvp_saved' );
    $add( 'rsvp_baptism_adults', 'RSVP Baptism', 'Numarul de adulti.', 'Baptism RSVP', '2', 'Baptism rsvp_saved' );
    $add( 'rsvp_baptism_children', 'RSVP Baptism', 'Numarul de copii.', 'Baptism RSVP', '1', 'Baptism rsvp_saved' );
    $add( 'rsvp_baptism_child_menu_requested', 'RSVP Baptism', 'Meniu copil solicitat.', 'Baptism RSVP', 'Da', 'Baptism rsvp_saved' );
    $add( 'rsvp_baptism_child_menu_count', 'RSVP Baptism', 'Numar meniuri copil.', 'Baptism RSVP', '1', 'Baptism rsvp_saved' );
    $add( 'rsvp_baptism_child_seat_requested', 'RSVP Baptism', 'Scaun copil solicitat.', 'Baptism RSVP', 'Nu', 'Baptism rsvp_saved' );
    $add( 'rsvp_baptism_child_seat_count', 'RSVP Baptism', 'Numar scaune copil.', 'Baptism RSVP', '1', 'Baptism rsvp_saved' );
    $add( 'rsvp_baptism_transport_requested', 'RSVP Baptism', 'Transport solicitat.', 'Baptism RSVP', 'Nu', 'Baptism rsvp_saved' );
    $add( 'rsvp_baptism_transport_people', 'RSVP Baptism', 'Numar persoane transport.', 'Baptism RSVP', '2', 'Baptism rsvp_saved' );
    $add( 'rsvp_baptism_accommodation', 'RSVP Baptism', 'Cazare solicitata.', 'Baptism RSVP', 'Nu', 'Baptism rsvp_saved' );
    $add( 'rsvp_baptism_vegetarian', 'RSVP Baptism', 'Meniu vegetarian solicitat.', 'Baptism RSVP', 'Da', 'Baptism rsvp_saved' );
    $add( 'rsvp_baptism_vegetarian_menus', 'RSVP Baptism', 'Numar meniuri vegetariene.', 'Baptism RSVP', '1', 'Baptism rsvp_saved' );
    $add( 'rsvp_baptism_allergies', 'RSVP Baptism', 'Alergii sau restrictii.', 'Baptism RSVP', 'Lactoza', 'Baptism rsvp_saved' );
    $add( 'rsvp_baptism_message', 'RSVP Baptism', 'Mesaj pentru familie/copil.', 'Baptism RSVP', 'Felicitari!', 'Baptism rsvp_saved' );

    $add( 'rsvp_birthday_adult_guest_count', 'RSVP Birthday Adult', 'Numarul participantilor adulti.', 'Birthday Adult RSVP', '2', 'Birthday adult rsvp_saved' );
    $add( 'rsvp_birthday_adult_child_menu_requested', 'RSVP Birthday Adult', 'Meniu copil solicitat in RSVP adult.', 'Birthday Adult RSVP', 'Nu', 'Birthday adult rsvp_saved' );
    $add( 'rsvp_birthday_adult_child_menu_count', 'RSVP Birthday Adult', 'Numar meniuri copil in RSVP adult.', 'Birthday Adult RSVP', '1', 'Birthday adult rsvp_saved' );
    $add( 'rsvp_birthday_adult_vegetarian', 'RSVP Birthday Adult', 'Meniu vegetarian solicitat.', 'Birthday Adult RSVP', 'Da', 'Birthday adult rsvp_saved' );
    $add( 'rsvp_birthday_adult_vegetarian_menus', 'RSVP Birthday Adult', 'Numar meniuri vegetariene.', 'Birthday Adult RSVP', '1', 'Birthday adult rsvp_saved' );
    $add( 'rsvp_birthday_adult_allergies', 'RSVP Birthday Adult', 'Alergii sau restrictii.', 'Birthday Adult RSVP', 'Gluten', 'Birthday adult rsvp_saved' );
    $add( 'rsvp_birthday_adult_message', 'RSVP Birthday Adult', 'Mesaj catre sarbatorit.', 'Birthday Adult RSVP', 'La multi ani!', 'Birthday adult rsvp_saved' );
    $add( 'rsvp_birthday_adult_special_observations', 'RSVP Birthday Adult', 'Observatii RSVP adult.', 'Birthday Adult RSVP', 'Ajungem mai tarziu', 'Birthday adult rsvp_saved' );

    $add( 'rsvp_birthday_child_participants_count', 'RSVP Birthday Child', 'Numarul de copii participanti.', 'Birthday Child RSVP', '1', 'Birthday child rsvp_saved' );
    $add( 'rsvp_birthday_child_accompanying_adult_stays', 'RSVP Birthday Child', 'Adultul insotitor ramane la petrecere.', 'Birthday Child RSVP', 'Da', 'Birthday child rsvp_saved' );
    $add( 'rsvp_birthday_child_accompanying_adults_count', 'RSVP Birthday Child', 'Numarul adultilor insotitori.', 'Birthday Child RSVP', '1', 'Birthday child rsvp_saved' );
    $add( 'rsvp_birthday_child_vegetarian', 'RSVP Birthday Child', 'Meniu vegetarian solicitat.', 'Birthday Child RSVP', 'Nu', 'Birthday child rsvp_saved' );
    $add( 'rsvp_birthday_child_vegetarian_menus', 'RSVP Birthday Child', 'Numar meniuri vegetariene.', 'Birthday Child RSVP', '1', 'Birthday child rsvp_saved' );
    $add( 'rsvp_birthday_child_allergies', 'RSVP Birthday Child', 'Alergii sau restrictii copil.', 'Birthday Child RSVP', 'Arahide', 'Birthday child rsvp_saved' );
    $add( 'rsvp_birthday_child_special_observations', 'RSVP Birthday Child', 'Observatii organizator pentru RSVP copil.', 'Birthday Child RSVP', 'Prefera activitati linistite', 'Birthday child rsvp_saved' );
    $add( 'rsvp_birthday_child_pickup_time', 'RSVP Birthday Child', 'Ora de preluare copil.', 'Birthday Child RSVP', '20:00', 'Birthday child rsvp_saved' );
    $add( 'rsvp_birthday_child_restricted_activities', 'RSVP Birthday Child', 'Activitati restrictionate pentru copil.', 'Birthday Child RSVP', 'Trambulina', 'Birthday child rsvp_saved' );
    $add( 'rsvp_birthday_child_message', 'RSVP Birthday Child', 'Mesaj catre sarbatorit.', 'Birthday Child RSVP', 'La multi ani!', 'Birthday child rsvp_saved' );

    $add( 'gift_reserved_name', 'Gifts', 'Numele cadoului rezervat/selectat.', 'RSVP/Gifts', 'Set lego', 'Cand RSVP transmite cadou selectat' );
    $add( 'gift_reserved_link', 'Gifts', 'Linkul cadoului rezervat/selectat.', 'RSVP/Gifts', 'https://shop.test/cadou', 'Cand RSVP transmite cadou selectat' );
    $add( 'gift_delivery_address', 'Gifts', 'Adresa de livrare pentru cadou.', 'RSVP/Gifts', 'Str. Exemplu 1', 'Cand cadoul are adresa' );
    $add( 'gift_status', 'Gifts', 'Statusul cadoului.', 'RSVP/Gifts', 'reserved', 'Cand RSVP transmite cadou selectat' );
    $add( 'gift_reserved_list', 'Gifts', 'Lista cadourilor selectate in RSVP.', 'RSVP/Gifts', 'Set lego - https://shop.test/cadou', 'Cand RSVP transmite cadouri selectate' );
    $add( 'gift_public_count', 'Gifts', 'Numarul cadourilor publice pentru token.', 'Gifts', '20', 'Cu token valid' );
    $add( 'gift_reserved_count', 'Gifts', 'Numarul cadourilor rezervate pentru token.', 'Gifts', '3', 'Cu token valid' );

    $add( 'unsubscribe_url', 'Email/Links', 'Link de dezabonare pentru emailuri marketing.', 'Guest', 'https://site.test/u/send/signature', 'Emailuri guest/marketing' );
    $add( 'why_received_text', 'Email/Links', 'Text scurt despre motivul primirii emailului.', 'Guest', 'Primesti acest email...', 'Emailuri guest/marketing' );
    $add( 'update_rsvp_url', 'Email/Links', 'Link catre pagina publica pentru actualizare/recompletare RSVP.', 'Guest', 'https://site.test/invitati/token', 'Cu token valid' );
    $add( 'rsvp_form_url', 'Email/Links', 'Link catre formularul RSVP public.', 'Guest/Customer', 'https://site.test/invitati/token', 'Cu token valid' );

    return $items;
}

function teinvit_email_context_values_registry( array $args ) {
    $token    = (string) ( $args['token'] ?? '' );
    $order_id = (int) ( $args['order_id'] ?? 0 );
    $payload  = is_array( $args['payload'] ?? null ) ? $args['payload'] : [];
    $send_id  = (string) ( $args['send_id'] ?? '' );

    if ( $order_id <= 0 && ! empty( $payload['order_id'] ) ) {
        $order_id = (int) $payload['order_id'];
    }

    $vertical = sanitize_key( (string) ( $payload['vertical'] ?? '' ) );
    if ( $vertical === '' && $token !== '' && function_exists( 'teinvit_resolve_token_vertical' ) ) {
        $vertical = sanitize_key( (string) teinvit_resolve_token_vertical( $token ) );
    }
    if ( $vertical === '' ) {
        $vertical = 'wedding';
    }

    $order = ( $order_id > 0 && function_exists( 'wc_get_order' ) ) ? wc_get_order( $order_id ) : null;
    $first_name = $order ? (string) $order->get_billing_first_name() : '';
    $last_name  = $order ? (string) $order->get_billing_last_name() : '';
    $order_no   = $order ? (string) $order->get_order_number() : '';
    $customer_email = $order ? sanitize_email( (string) $order->get_billing_email() ) : '';
    $product_names = teinvit_email_order_product_names( $order );
    $product_ids = function_exists( 'teinvit_email_order_product_ids_for_context' ) ? teinvit_email_order_product_ids_for_context( $order_id, $token ) : [];

    $recipient = sanitize_email( (string) ( $args['recipient_email'] ?? '' ) );
    $unsubscribe = '';
    if ( $recipient !== '' && $send_id !== '' ) {
        $sig = teinvit_email_sign( $send_id . '|' . strtolower( $recipient ) );
        $unsubscribe = home_url( '/u/' . rawurlencode( $send_id ) . '/' . rawurlencode( $sig ) . '/?e=' . rawurlencode( $recipient ) );
    }

    $inv_ctx = teinvit_email_invitation_context_for_token( $token, $vertical );
    $vertical = sanitize_key( (string) ( $inv_ctx['vertical'] ?? $vertical ) );
    $invitation = is_array( $inv_ctx['invitation'] ?? null ) ? $inv_ctx['invitation'] : [];
    $config = is_array( $inv_ctx['config'] ?? null ) ? $inv_ctx['config'] : [];
    $extra = teinvit_email_payload_extra_fields( $payload );

    $wedding_couple = teinvit_email_join_list( $invitation['names'] ?? '' );
    $name_parts = $wedding_couple !== '' ? preg_split( '/\s*&\s*/', $wedding_couple ) : [];
    if ( ! is_array( $name_parts ) ) {
        $name_parts = [];
    }
    $wedding_civil = teinvit_email_invitation_event( $invitation, 'civil', [ 'civil' ] );
    $wedding_religious = teinvit_email_invitation_event( $invitation, 'religious', [ 'relig' ] );
    $wedding_party = teinvit_email_invitation_event( $invitation, 'party', [ 'petrec', 'party' ] );

    $baptism_religious = teinvit_email_invitation_event( $invitation, 'religious', [ 'relig' ] );
    $baptism_party = teinvit_email_invitation_event( $invitation, 'party', [ 'petrec', 'party' ] );
    $birthday_party = teinvit_email_invitation_event( $invitation, 'party', [ 'petrec', 'party' ] );

    $guest_name = trim( (string) ( ( $payload['guest_first_name'] ?? '' ) . ' ' . ( $payload['guest_last_name'] ?? '' ) ) );
    $message = (string) teinvit_email_payload_value( $payload, $extra, 'message_to_couple', '' );
    if ( $message === '' ) {
        $message = (string) teinvit_email_payload_value( $payload, $extra, 'message_to_family', '' );
    }
    if ( $message === '' ) {
        $message = (string) teinvit_email_payload_value( $payload, $extra, 'message_to_celebrants', '' );
    }

    $adults = (int) ( $payload['attending_people_count'] ?? 0 );
    $children = (int) ( $payload['kids_count'] ?? teinvit_email_payload_value( $payload, $extra, 'child_participants_count', 0 ) );
    $total_people = max( 0, $adults + $children );
    if ( $total_people <= 0 && ! empty( $payload['attending_party'] ) ) {
        $total_people = max( 1, $adults );
    }

    $rsvp_form_url = $token !== '' ? home_url( '/invitati/' . rawurlencode( $token ) ) : '';
    $birthday_mode = sanitize_key( (string) teinvit_email_payload_value( $payload, $extra, 'birthday_rsvp_mode', ( $config['birthday_rsvp_mode'] ?? '' ) ) );
    $gift_values = teinvit_email_gift_context_for_token( $token, $vertical, $payload );

    return array_merge(
        [
            'site_name' => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
            'token' => $token,
            'order_id' => (string) $order_id,
            'order_number' => $order_no,
            'customer_name' => trim( $first_name . ' ' . $last_name ),
            'customer_first_name' => $first_name,
            'customer_last_name' => $last_name,
            'customer_email' => $customer_email,
            'product_name' => teinvit_email_join_list( $product_names ),
            'product_ids' => implode( ',', array_map( 'strval', $product_ids ) ),
            'vertical' => $vertical,
            'package_type' => teinvit_email_package_type_for_token( $token ),
            'premium_source' => ( $token !== '' && function_exists( 'teinvit_resolve_token_premium_source' ) ) ? sanitize_key( (string) teinvit_resolve_token_premium_source( $token ) ) : '',
            'admin_client_url' => $token !== '' ? home_url( '/admin-client/' . rawurlencode( $token ) ) : '',
            'invitati_url' => $rsvp_form_url,
            'report_url' => $token !== '' ? home_url( '/admin-client/' . rawurlencode( $token ) . '#teinvit-report' ) : '',
            'invitation_version_id' => (string) ( $payload['version_id'] ?? '' ),
            'invitation_version_index' => (string) ( $payload['version_index'] ?? '' ),
            'invitation_pdf_url' => (string) ( $payload['pdf_url'] ?? '' ),
            'invitation_pdf_status' => (string) ( $payload['pdf_status'] ?? '' ),

            'wedding_bride_name' => (string) ( $name_parts[0] ?? '' ),
            'wedding_groom_name' => (string) ( $name_parts[1] ?? '' ),
            'wedding_couple_name' => $wedding_couple,
            'wedding_message' => (string) ( $invitation['message'] ?? '' ),
            'wedding_theme' => (string) ( $invitation['theme'] ?? '' ),
            'wedding_parents_bride' => (string) teinvit_email_array_path_value( $invitation, 'parents.mireasa', '' ),
            'wedding_parents_groom' => (string) teinvit_email_array_path_value( $invitation, 'parents.mire', '' ),
            'wedding_nasi' => teinvit_email_join_list( $invitation['nasi'] ?? '' ),
            'wedding_civil_location' => (string) ( $wedding_civil['loc'] ?? '' ),
            'wedding_civil_date' => (string) ( $wedding_civil['date'] ?? '' ),
            'wedding_civil_waze' => (string) ( $wedding_civil['waze'] ?? '' ),
            'wedding_religious_location' => (string) ( $wedding_religious['loc'] ?? '' ),
            'wedding_religious_date' => (string) ( $wedding_religious['date'] ?? '' ),
            'wedding_religious_waze' => (string) ( $wedding_religious['waze'] ?? '' ),
            'wedding_party_location' => (string) ( $wedding_party['loc'] ?? '' ),
            'wedding_party_date' => (string) ( $wedding_party['date'] ?? '' ),
            'wedding_party_waze' => (string) ( $wedding_party['waze'] ?? '' ),
            'wedding_rsvp_deadline' => (string) ( $config['rsvp_deadline_text'] ?? ( $config['rsvp_deadline_date'] ?? '' ) ),

            'baptism_child_names' => teinvit_email_join_list( $invitation['children'] ?? ( $invitation['name_units'] ?? [] ) ),
            'baptism_headline' => (string) ( $invitation['headline'] ?? '' ),
            'baptism_message' => (string) ( $invitation['message'] ?? '' ),
            'baptism_theme' => (string) ( $invitation['theme'] ?? '' ),
            'baptism_mother' => (string) teinvit_email_array_path_value( $invitation, 'parents.mother', '' ),
            'baptism_father' => (string) teinvit_email_array_path_value( $invitation, 'parents.father', '' ),
            'baptism_godmother' => (string) teinvit_email_array_path_value( $invitation, 'godparents.godmother', '' ),
            'baptism_godfather' => (string) teinvit_email_array_path_value( $invitation, 'godparents.godfather', '' ),
            'baptism_religious_location' => (string) ( $baptism_religious['loc'] ?? '' ),
            'baptism_religious_date' => (string) ( $baptism_religious['date'] ?? '' ),
            'baptism_religious_waze' => (string) ( $baptism_religious['waze'] ?? '' ),
            'baptism_party_location' => (string) ( $baptism_party['loc'] ?? '' ),
            'baptism_party_date' => (string) ( $baptism_party['date'] ?? '' ),
            'baptism_party_waze' => (string) ( $baptism_party['waze'] ?? '' ),
            'baptism_rsvp_deadline' => (string) ( $config['rsvp_deadline_text'] ?? ( $config['rsvp_deadline_date'] ?? '' ) ),

            'birthday_celebrant_names' => teinvit_email_join_list( $invitation['celebrants'] ?? ( $invitation['name_units'] ?? [] ) ),
            'birthday_headline' => (string) ( $invitation['headline'] ?? '' ),
            'birthday_age' => (string) teinvit_email_array_path_value( $invitation, 'age.value', '' ),
            'birthday_age_line' => (string) teinvit_email_array_path_value( $invitation, 'age.line', '' ),
            'birthday_event_name' => (string) teinvit_email_array_path_value( $invitation, 'event_name.value', '' ),
            'birthday_message' => (string) ( $invitation['message'] ?? '' ),
            'birthday_theme' => (string) ( $invitation['theme'] ?? '' ),
            'birthday_party_location' => (string) ( $birthday_party['loc'] ?? '' ),
            'birthday_party_date' => (string) ( $birthday_party['date'] ?? '' ),
            'birthday_party_weekday' => (string) ( $birthday_party['weekday'] ?? '' ),
            'birthday_party_waze' => (string) ( $birthday_party['waze'] ?? '' ),
            'birthday_rsvp_mode' => $birthday_mode,
            'birthday_party_theme' => ( ! empty( $config['show_birthday_party_theme'] ) || ! empty( $config['birthday_show_party_theme'] ) ) ? (string) ( $config['birthday_party_theme_text'] ?? '' ) : '',
            'birthday_dress_code' => ( ! empty( $config['show_birthday_dress_code'] ) || ! empty( $config['birthday_show_dress_code'] ) ) ? (string) ( $config['birthday_dress_code_text'] ?? '' ) : '',
            'birthday_rsvp_deadline' => (string) ( $config['rsvp_deadline_text'] ?? ( $config['rsvp_deadline_date'] ?? '' ) ),

            'guest_name' => $guest_name,
            'guest_full_name' => $guest_name,
            'guest_first_name' => (string) ( $payload['guest_first_name'] ?? '' ),
            'guest_last_name' => (string) ( $payload['guest_last_name'] ?? '' ),
            'guest_email' => (string) ( $payload['guest_email'] ?? '' ),
            'guest_phone' => (string) ( $payload['guest_phone'] ?? '' ),
            'rsvp_attending_status' => ! empty( $payload['attending_party'] ) || ! empty( $payload['attending_religious'] ) || ! empty( $payload['attending_civil'] ) ? 'Participa' : 'Nu participa',
            'rsvp_total_people' => (string) $total_people,
            'rsvp_adults' => (string) $adults,
            'rsvp_children' => (string) $children,
            'rsvp_created_at' => (string) ( $payload['created_at'] ?? current_time( 'mysql' ) ),
            'rsvp_message' => $message,
            'rsvp_gdpr_accepted' => teinvit_email_bool_label( $payload['gdpr_accepted'] ?? '' ),
            'rsvp_marketing_consent' => teinvit_email_bool_label( $payload['marketing_consent'] ?? '' ),
            'rsvp_bringing_kids' => teinvit_email_bool_label( $payload['bringing_kids'] ?? '' ),
            'rsvp_attending_civil' => teinvit_email_bool_label( $payload['attending_civil'] ?? '' ),
            'rsvp_attending_religious' => teinvit_email_bool_label( $payload['attending_religious'] ?? '' ),
            'rsvp_attending_party' => teinvit_email_bool_label( $payload['attending_party'] ?? '' ),
            'rsvp_accommodation' => teinvit_email_bool_label( $payload['needs_accommodation'] ?? '' ),
            'rsvp_accommodation_people' => (string) ( $payload['accommodation_people_count'] ?? '' ),
            'rsvp_vegetarian' => teinvit_email_bool_label( $payload['vegetarian_requested'] ?? '' ),
            'rsvp_vegetarian_menus' => (string) ( $payload['vegetarian_menus_count'] ?? '' ),
            'rsvp_has_allergies' => teinvit_email_bool_label( $payload['has_allergies'] ?? '' ),
            'rsvp_allergies' => (string) ( $payload['allergy_details'] ?? '' ),

            'rsvp_wedding_attending_civil' => teinvit_email_bool_label( $payload['attending_civil'] ?? '' ),
            'rsvp_wedding_attending_religious' => teinvit_email_bool_label( $payload['attending_religious'] ?? '' ),
            'rsvp_wedding_attending_party' => teinvit_email_bool_label( $payload['attending_party'] ?? '' ),
            'rsvp_wedding_bringing_kids' => teinvit_email_bool_label( $payload['bringing_kids'] ?? '' ),
            'rsvp_wedding_kids_count' => (string) ( $payload['kids_count'] ?? '' ),
            'rsvp_wedding_accommodation' => teinvit_email_bool_label( $payload['needs_accommodation'] ?? '' ),
            'rsvp_wedding_accommodation_people' => (string) ( $payload['accommodation_people_count'] ?? '' ),
            'rsvp_wedding_vegetarian' => teinvit_email_bool_label( $payload['vegetarian_requested'] ?? '' ),
            'rsvp_wedding_vegetarian_menus' => (string) ( $payload['vegetarian_menus_count'] ?? '' ),
            'rsvp_wedding_has_allergies' => teinvit_email_bool_label( $payload['has_allergies'] ?? '' ),
            'rsvp_wedding_allergies' => (string) ( $payload['allergy_details'] ?? '' ),
            'rsvp_wedding_message_to_couple' => (string) ( $payload['message_to_couple'] ?? '' ),

            'rsvp_baptism_attending_religious' => teinvit_email_bool_label( $payload['attending_religious'] ?? '' ),
            'rsvp_baptism_attending_party' => teinvit_email_bool_label( $payload['attending_party'] ?? '' ),
            'rsvp_baptism_adults' => (string) ( $payload['attending_people_count'] ?? '' ),
            'rsvp_baptism_children' => (string) ( $payload['kids_count'] ?? '' ),
            'rsvp_baptism_child_menu_requested' => teinvit_email_bool_label( teinvit_email_payload_value( $payload, $extra, 'child_menu_requested', '' ) ),
            'rsvp_baptism_child_menu_count' => (string) teinvit_email_payload_value( $payload, $extra, 'child_menu_count', '' ),
            'rsvp_baptism_child_seat_requested' => teinvit_email_bool_label( teinvit_email_payload_value( $payload, $extra, 'child_seat_requested', '' ) ),
            'rsvp_baptism_child_seat_count' => (string) teinvit_email_payload_value( $payload, $extra, 'child_seat_count', '' ),
            'rsvp_baptism_transport_requested' => teinvit_email_bool_label( teinvit_email_payload_value( $payload, $extra, 'transport_requested', '' ) ),
            'rsvp_baptism_transport_people' => (string) teinvit_email_payload_value( $payload, $extra, 'transport_people_count', '' ),
            'rsvp_baptism_accommodation' => teinvit_email_bool_label( $payload['needs_accommodation'] ?? '' ),
            'rsvp_baptism_vegetarian' => teinvit_email_bool_label( $payload['vegetarian_requested'] ?? '' ),
            'rsvp_baptism_vegetarian_menus' => (string) ( $payload['vegetarian_menus_count'] ?? '' ),
            'rsvp_baptism_allergies' => (string) ( $payload['allergy_details'] ?? '' ),
            'rsvp_baptism_message' => (string) teinvit_email_payload_value( $payload, $extra, 'message_to_family', $message ),

            'rsvp_birthday_adult_guest_count' => (string) ( $payload['attending_people_count'] ?? '' ),
            'rsvp_birthday_adult_child_menu_requested' => teinvit_email_bool_label( teinvit_email_payload_value( $payload, $extra, 'child_menu_requested', '' ) ),
            'rsvp_birthday_adult_child_menu_count' => (string) teinvit_email_payload_value( $payload, $extra, 'child_menu_count', '' ),
            'rsvp_birthday_adult_vegetarian' => teinvit_email_bool_label( $payload['vegetarian_requested'] ?? '' ),
            'rsvp_birthday_adult_vegetarian_menus' => (string) ( $payload['vegetarian_menus_count'] ?? '' ),
            'rsvp_birthday_adult_allergies' => (string) ( $payload['allergy_details'] ?? '' ),
            'rsvp_birthday_adult_message' => (string) teinvit_email_payload_value( $payload, $extra, 'message_to_celebrants', $message ),
            'rsvp_birthday_adult_special_observations' => (string) teinvit_email_payload_value( $payload, $extra, 'special_observations', '' ),

            'rsvp_birthday_child_participants_count' => (string) teinvit_email_payload_value( $payload, $extra, 'child_participants_count', '' ),
            'rsvp_birthday_child_accompanying_adult_stays' => teinvit_email_bool_label( teinvit_email_payload_value( $payload, $extra, 'child_accompanying_adult_stays', '' ) ),
            'rsvp_birthday_child_accompanying_adults_count' => (string) teinvit_email_payload_value( $payload, $extra, 'child_accompanying_adults_count', '' ),
            'rsvp_birthday_child_vegetarian' => teinvit_email_bool_label( $payload['vegetarian_requested'] ?? '' ),
            'rsvp_birthday_child_vegetarian_menus' => (string) ( $payload['vegetarian_menus_count'] ?? '' ),
            'rsvp_birthday_child_allergies' => (string) ( $payload['allergy_details'] ?? '' ),
            'rsvp_birthday_child_special_observations' => (string) teinvit_email_payload_value( $payload, $extra, 'child_special_observations_other', teinvit_email_payload_value( $payload, $extra, 'special_observations', '' ) ),
            'rsvp_birthday_child_pickup_time' => (string) teinvit_email_payload_value( $payload, $extra, 'child_pickup_time', '' ),
            'rsvp_birthday_child_restricted_activities' => (string) teinvit_email_payload_value( $payload, $extra, 'child_restricted_activities', '' ),
            'rsvp_birthday_child_message' => (string) teinvit_email_payload_value( $payload, $extra, 'message_to_celebrants', $message ),

            'unsubscribe_url' => $unsubscribe,
            'why_received_text' => 'Primesti acest email deoarece ai bifat acordul de marketing in formularul RSVP.',
            'update_rsvp_url' => $rsvp_form_url,
            'rsvp_form_url' => $rsvp_form_url,
        ],
        $gift_values
    );
}

function teinvit_email_is_suppressed( $email, $scope = 'marketing' ) {
    global $wpdb;

    $email = sanitize_email( $email );
    if ( $email === '' ) {
        return true;
    }

    $tables = teinvit_email_tables();
    $id     = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$tables['suppression']} WHERE email=%s AND scope=%s LIMIT 1", $email, $scope ) );

    return $id > 0;
}

function teinvit_email_add_suppression( $email, $scope = 'marketing', $reason = 'unsubscribe_link', $source_send_id = null ) {
    global $wpdb;

    $email = sanitize_email( $email );
    if ( $email === '' ) {
        return;
    }

    $tables = teinvit_email_tables();
    $wpdb->replace(
        $tables['suppression'],
        [
            'email'          => $email,
            'email_hash'     => hash( 'sha256', strtolower( $email ) ),
            'scope'          => sanitize_key( $scope ),
            'reason'         => sanitize_key( $reason ),
            'source_send_id' => $source_send_id ? sanitize_text_field( $source_send_id ) : null,
            'created_at'     => current_time( 'mysql' ),
        ]
    );

    do_action( 'teinvit_email_suppression_added', $email, sanitize_key( $scope ), sanitize_key( $reason ), $source_send_id ? sanitize_text_field( $source_send_id ) : '' );
}

function teinvit_email_remove_suppression( $email, $scope = 'marketing', $reason = 'manual_remove' ) {
    global $wpdb;

    $email = sanitize_email( $email );
    if ( $email === '' ) {
        return false;
    }

    $scope  = sanitize_key( $scope );
    $tables = teinvit_email_tables();
    $deleted = (int) $wpdb->delete( $tables['suppression'], [ 'email' => $email, 'scope' => $scope ] );

    if ( $deleted > 0 ) {
        do_action( 'teinvit_email_suppression_removed', $email, $scope, sanitize_key( $reason ) );
        return true;
    }

    return false;
}


function teinvit_email_merge_tags_catalog() {
    return teinvit_email_merge_tags_catalog_registry();

    return [
        'site_name' => [ 'tag' => '{site_name}', 'description' => 'Numele website-ului', 'context' => 'Global', 'category' => 'Global', 'example' => 'TeInvit' ],
        'order_number' => [ 'tag' => '{order_number}', 'description' => 'Numărul comenzii Woo', 'context' => 'Customer', 'category' => 'Date comandă', 'example' => '512' ],
        'order_id' => [ 'tag' => '{order_id}', 'description' => 'ID comandă Woo', 'context' => 'Customer', 'category' => 'Date comandă', 'example' => '512' ],
        'token' => [ 'tag' => '{token}', 'description' => 'Token invitație', 'context' => 'Global', 'category' => 'Invitație & token', 'example' => '512-ba659c...' ],
        'admin_client_url' => [ 'tag' => '{admin_client_url}', 'description' => 'Link administrare invitație', 'context' => 'Customer', 'category' => 'Link-uri', 'example' => 'https://.../admin-client/{token}' ],
        'invitati_url' => [ 'tag' => '{invitati_url}', 'description' => 'Link pagină invitați', 'context' => 'Customer', 'category' => 'Link-uri', 'example' => 'https://.../invitati/{token}' ],
        'report_url' => [ 'tag' => '{report_url}', 'description' => 'Link raport invitați', 'context' => 'Customer', 'category' => 'Link-uri', 'example' => 'https://.../admin-client/{token}#teinvit-report' ],
        'invitation_version_id' => [ 'tag' => '{invitation_version_id}', 'description' => 'ID versiune invitație', 'context' => 'Customer', 'category' => 'Versiuni', 'example' => '123' ],
        'invitation_version_index' => [ 'tag' => '{invitation_version_index}', 'description' => 'Număr variantă invitație', 'context' => 'Customer', 'category' => 'Versiuni', 'example' => '1' ],
        'invitation_pdf_url' => [ 'tag' => '{invitation_pdf_url}', 'description' => 'Link PDF variantă', 'context' => 'Customer', 'category' => 'Versiuni', 'example' => 'https://...' ],
        'invitation_pdf_status' => [ 'tag' => '{invitation_pdf_status}', 'description' => 'Status PDF variantă', 'context' => 'Customer', 'category' => 'Versiuni', 'example' => 'ready' ],
        'customer_first_name' => [ 'tag' => '{customer_first_name}', 'description' => 'Prenume client', 'context' => 'Customer', 'category' => 'Client', 'example' => 'Alex' ],
        'customer_last_name' => [ 'tag' => '{customer_last_name}', 'description' => 'Nume client', 'context' => 'Customer', 'category' => 'Client', 'example' => 'Popescu' ],
        'guest_name' => [ 'tag' => '{guest_name}', 'description' => 'Nume invitat (complet)', 'context' => 'RSVP', 'category' => 'Invitat', 'example' => 'Maria Ionescu' ],
        'guest_first_name' => [ 'tag' => '{guest_first_name}', 'description' => 'Prenume invitat', 'context' => 'RSVP', 'category' => 'Invitat', 'example' => 'Maria' ],
        'guest_last_name' => [ 'tag' => '{guest_last_name}', 'description' => 'Nume invitat', 'context' => 'RSVP', 'category' => 'Invitat', 'example' => 'Ionescu' ],
        'guest_phone' => [ 'tag' => '{guest_phone}', 'description' => 'Telefon invitat', 'context' => 'RSVP', 'category' => 'Invitat', 'example' => '0712345678' ],
        'guest_email' => [ 'tag' => '{guest_email}', 'description' => 'Email invitat', 'context' => 'RSVP/Guest', 'category' => 'Invitat', 'example' => 'maria@example.com' ],
        'rsvp_adults' => [ 'tag' => '{rsvp_adults}', 'description' => 'Numărul de adulți confirmați', 'context' => 'RSVP', 'category' => 'RSVP', 'example' => '2' ],
        'rsvp_children' => [ 'tag' => '{rsvp_children}', 'description' => 'Numărul de copii confirmați', 'context' => 'RSVP', 'category' => 'RSVP', 'example' => '1' ],
        'rsvp_bringing_kids' => [ 'tag' => '{rsvp_bringing_kids}', 'description' => 'Invitatul vine cu copii', 'context' => 'RSVP', 'category' => 'RSVP', 'example' => 'Da' ],
        'rsvp_attending_civil' => [ 'tag' => '{rsvp_attending_civil}', 'description' => 'Participă la civil', 'context' => 'RSVP', 'category' => 'RSVP', 'example' => 'Da' ],
        'rsvp_attending_religious' => [ 'tag' => '{rsvp_attending_religious}', 'description' => 'Participă la religios', 'context' => 'RSVP', 'category' => 'RSVP', 'example' => 'Da' ],
        'rsvp_attending_party' => [ 'tag' => '{rsvp_attending_party}', 'description' => 'Participă la petrecere', 'context' => 'RSVP', 'category' => 'RSVP', 'example' => 'Nu' ],
        'rsvp_accommodation' => [ 'tag' => '{rsvp_accommodation}', 'description' => 'Solicită cazare', 'context' => 'RSVP', 'category' => 'Cazare', 'example' => 'Nu' ],
        'rsvp_accommodation_people' => [ 'tag' => '{rsvp_accommodation_people}', 'description' => 'Numărul de persoane care au nevoie de cazare', 'context' => 'RSVP', 'category' => 'Cazare', 'example' => '2' ],
        'rsvp_vegetarian' => [ 'tag' => '{rsvp_vegetarian}', 'description' => 'A cerut meniu vegetarian', 'context' => 'RSVP', 'category' => 'Meniu', 'example' => 'Da' ],
        'rsvp_vegetarian_menus' => [ 'tag' => '{rsvp_vegetarian_menus}', 'description' => 'Număr meniuri vegetariene', 'context' => 'RSVP', 'category' => 'Meniu', 'example' => '1' ],
        'rsvp_has_allergies' => [ 'tag' => '{rsvp_has_allergies}', 'description' => 'Invitatul a declarat alergii', 'context' => 'RSVP', 'category' => 'Meniu', 'example' => 'Nu' ],
        'rsvp_allergies' => [ 'tag' => '{rsvp_allergies}', 'description' => 'Detalii alergii', 'context' => 'RSVP', 'category' => 'Meniu', 'example' => 'N/A' ],
        'rsvp_message' => [ 'tag' => '{rsvp_message}', 'description' => 'Mesajul invitatului', 'context' => 'RSVP', 'category' => 'Mesaje', 'example' => 'Casă de piatră!' ],
        'rsvp_marketing_consent' => [ 'tag' => '{rsvp_marketing_consent}', 'description' => 'Acord marketing din RSVP', 'context' => 'Guest', 'category' => 'Consimțământ', 'example' => 'Da' ],
        'unsubscribe_url' => [ 'tag' => '{unsubscribe_url}', 'description' => 'Link dezabonare marketing', 'context' => 'Guest', 'category' => 'Consimțământ', 'example' => 'https://.../u/{send_id}/...' ],
        'why_received_text' => [ 'tag' => '{why_received_text}', 'description' => 'Text motiv primire email', 'context' => 'Guest', 'category' => 'Consimțământ', 'example' => 'Primești acest email...' ],
    ];
}

function teinvit_email_context_values( array $args ) {
    return teinvit_email_context_values_registry( $args );

    $token    = (string) ( $args['token'] ?? '' );
    $order_id = (int) ( $args['order_id'] ?? 0 );
    $payload  = is_array( $args['payload'] ?? null ) ? $args['payload'] : [];
    $send_id  = (string) ( $args['send_id'] ?? '' );

    $order = ( $order_id > 0 && function_exists( 'wc_get_order' ) ) ? wc_get_order( $order_id ) : null;

    $first_name = $order ? (string) $order->get_billing_first_name() : '';
    $last_name  = $order ? (string) $order->get_billing_last_name() : '';
    $order_no   = $order ? (string) $order->get_order_number() : '';

    $guest_name = trim( (string) ( ( $payload['guest_first_name'] ?? '' ) . ' ' . ( $payload['guest_last_name'] ?? '' ) ) );
    $recipient  = sanitize_email( (string) ( $args['recipient_email'] ?? '' ) );

    $unsubscribe = '';
    if ( $recipient !== '' && $send_id !== '' ) {
        $sig         = teinvit_email_sign( $send_id . '|' . strtolower( $recipient ) );
        $unsubscribe = home_url( '/u/' . rawurlencode( $send_id ) . '/' . rawurlencode( $sig ) . '/?e=' . rawurlencode( $recipient ) );
    }

    return [
        'site_name' => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
        'order_number' => $order_no,
        'order_id' => (string) $order_id,
        'token' => $token,
        'admin_client_url' => home_url( '/admin-client/' . rawurlencode( $token ) ),
        'invitati_url' => home_url( '/invitati/' . rawurlencode( $token ) ),
        'report_url' => home_url( '/admin-client/' . rawurlencode( $token ) . '#teinvit-report' ),
        'invitation_version_id' => (string) ( $payload['version_id'] ?? '' ),
        'invitation_version_index' => (string) ( $payload['version_index'] ?? '' ),
        'invitation_pdf_url' => (string) ( $payload['pdf_url'] ?? '' ),
        'invitation_pdf_status' => (string) ( $payload['pdf_status'] ?? '' ),
        'customer_first_name' => $first_name,
        'customer_last_name' => $last_name,
        'guest_name' => $guest_name,
        'guest_first_name' => (string) ( $payload['guest_first_name'] ?? '' ),
        'guest_last_name' => (string) ( $payload['guest_last_name'] ?? '' ),
        'guest_phone' => (string) ( $payload['guest_phone'] ?? '' ),
        'guest_email' => (string) ( $payload['guest_email'] ?? '' ),
        'rsvp_adults' => (string) ( $payload['attending_people_count'] ?? '' ),
        'rsvp_children' => (string) ( $payload['kids_count'] ?? '' ),
        'rsvp_bringing_kids' => ! empty( $payload['bringing_kids'] ) ? 'Da' : 'Nu',
        'rsvp_attending_civil' => ! empty( $payload['attending_civil'] ) ? 'Da' : 'Nu',
        'rsvp_attending_religious' => ! empty( $payload['attending_religious'] ) ? 'Da' : 'Nu',
        'rsvp_attending_party' => ! empty( $payload['attending_party'] ) ? 'Da' : 'Nu',
        'rsvp_accommodation' => ! empty( $payload['needs_accommodation'] ) ? 'Da' : 'Nu',
        'rsvp_accommodation_people' => (string) ( $payload['accommodation_people_count'] ?? '' ),
        'rsvp_vegetarian' => ! empty( $payload['vegetarian_requested'] ) ? 'Da' : 'Nu',
        'rsvp_vegetarian_menus' => (string) ( $payload['vegetarian_menus_count'] ?? '' ),
        'rsvp_has_allergies' => ! empty( $payload['has_allergies'] ) ? 'Da' : 'Nu',
        'rsvp_allergies' => (string) ( $payload['allergy_details'] ?? '' ),
        'rsvp_message' => (string) ( $payload['message_to_couple'] ?? '' ),
        'rsvp_marketing_consent' => ! empty( $payload['marketing_consent'] ) ? 'Da' : 'Nu',
        'unsubscribe_url' => $unsubscribe,
        'why_received_text' => 'Primești acest email deoarece ai bifat acordul de marketing în formularul RSVP.',
    ];
}

function teinvit_email_build_context( array $args ) {
    $values  = teinvit_email_context_values( $args );
    $catalog = teinvit_email_merge_tags_catalog();
    $context = [];

    foreach ( $catalog as $slug => $meta ) {
        $tag = (string) ( $meta['tag'] ?? '' );
        if ( $tag === '' ) {
            continue;
        }
        $context[ $tag ] = (string) ( $values[ $slug ] ?? '' );
    }

    return $context;
}


function teinvit_email_apply_tags( $text, array $context ) {
    $text = (string) $text;

    $square_context = [];
    foreach ( $context as $tag => $value ) {
        if ( ! is_string( $tag ) ) {
            continue;
        }

        if ( preg_match( '/^\{([a-z0-9_]+)\}$/i', $tag, $m ) ) {
            $square_context[ '[' . $m[1] . ']' ] = (string) $value;
        }
    }

    return strtr( strtr( $text, $square_context ), $context );
}

function teinvit_email_render_block( array $block, array $context, $accent ) {
    $type = sanitize_key( (string) ( $block['type'] ?? '' ) );
    if ( $type === '' ) {
        return [ 'html' => '', 'text' => '' ];
    }

    if ( array_key_exists( 'enabled', $block ) && empty( $block['enabled'] ) ) {
        return [ 'html' => '', 'text' => '' ];
    }

    $align     = in_array( (string) ( $block['align'] ?? 'center' ), [ 'left', 'center', 'right' ], true ) ? (string) $block['align'] : 'center';
    $align_css = 'text-align:' . esc_attr( $align ) . ';';

    switch ( $type ) {
        case 'logo':
            $url = esc_url( teinvit_email_apply_tags( $block['url'] ?? '', $context ) );
            if ( $url === '' ) {
                return [ 'html' => '', 'text' => '' ];
            }
            return [
                'html' => '<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr><td style="padding:0 0 20px 0;' . $align_css . '"><img src="' . $url . '" alt="Logo" style="max-width:180px;height:auto;display:inline-block;border:0;"/></td></tr></table>',
                'text' => '',
            ];

        case 'banner':
            $url = esc_url( teinvit_email_apply_tags( $block['url'] ?? '', $context ) );
            if ( $url === '' ) {
                return [ 'html' => '', 'text' => '' ];
            }
            return [
                'html' => '<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr><td style="padding:0 0 20px 0;' . $align_css . '"><img src="' . $url . '" alt="Banner" style="max-width:100%;height:auto;display:inline-block;border:0;border-radius:10px;"/></td></tr></table>',
                'text' => '',
            ];

        case 'title':
            $text = esc_html( teinvit_email_apply_tags( $block['text'] ?? '', $context ) );
            if ( $text === '' ) {
                return [ 'html' => '', 'text' => '' ];
            }
            return [
                'html' => '<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr><td style="padding:0 0 14px 0;' . $align_css . 'font-family:Arial,Helvetica,sans-serif;font-size:28px;line-height:34px;font-weight:700;color:#1f1f1f;">' . $text . '</td></tr></table>',
                'text' => $text . "

",
            ];

        case 'subtitle':
            $text = esc_html( teinvit_email_apply_tags( $block['text'] ?? '', $context ) );
            if ( $text === '' ) {
                return [ 'html' => '', 'text' => '' ];
            }
            return [
                'html' => '<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr><td style="padding:0 0 12px 0;' . $align_css . 'font-family:Arial,Helvetica,sans-serif;font-size:18px;line-height:24px;font-weight:700;color:#3a3a3a;">' . $text . '</td></tr></table>',
                'text' => $text . "
",
            ];

        case 'text':
            $raw_html = teinvit_email_apply_tags( (string) ( $block['html'] ?? '' ), $context );
            $raw_text = teinvit_email_apply_tags( (string) ( $block['text'] ?? '' ), $context );
            $has_html = trim( wp_strip_all_tags( $raw_html ) ) !== '' || strpos( $raw_html, '<' ) !== false;

            if ( $has_html ) {
                $safe_html = wp_kses_post( $raw_html );
                $html_body = wpautop( $safe_html );
                $text_body = wp_strip_all_tags( $safe_html );
            } else {
                $plain = sanitize_textarea_field( $raw_text );
                if ( trim( $plain ) === '' ) {
                    return [ 'html' => '', 'text' => '' ];
                }
                $html_body = wpautop( esc_html( $plain ) );
                $text_body = $plain;
            }

            return [
                'html' => '<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr><td style="padding:0 0 14px 0;' . $align_css . 'font-family:Arial,Helvetica,sans-serif;font-size:15px;line-height:24px;color:#2a2a2a;">' . $html_body . '</td></tr></table>',
                'text' => trim( preg_replace( '/\r\n|\r|\n/', "\n", (string) $text_body ) ) . "

",
            ];

        case 'bullets':
            $items = is_array( $block['items'] ?? null ) ? $block['items'] : [];
            if ( empty( $items ) ) {
                $fallback_lines = teinvit_email_apply_tags( (string) ( $block['text'] ?? '' ), $context );
                if ( trim( $fallback_lines ) === '' ) {
                    $fallback_lines = wp_strip_all_tags( teinvit_email_apply_tags( (string) ( $block['html'] ?? '' ), $context ) );
                }
                $parts = preg_split( '/\r\n|\r|\n/', (string) $fallback_lines );
                $items = is_array( $parts ) ? array_filter( array_map( 'trim', $parts ) ) : [];
            }
            if ( empty( $items ) ) {
                return [ 'html' => '', 'text' => '' ];
            }

            $list_html = '';
            $list_text = '';
            foreach ( $items as $item ) {
                $line = teinvit_email_apply_tags( (string) $item, $context );
                if ( trim( $line ) === '' ) {
                    continue;
                }
                $list_html .= '<li style="margin:0 0 8px 0;">' . esc_html( $line ) . '</li>';
                $list_text .= '- ' . wp_strip_all_tags( $line ) . "
";
            }
            if ( $list_html === '' ) {
                return [ 'html' => '', 'text' => '' ];
            }

            return [
                'html' => '<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr><td style="padding:0 0 14px 0;' . $align_css . '"><ul style="margin:0 0 0 18px;padding:0;font-family:Arial,Helvetica,sans-serif;font-size:15px;line-height:23px;color:#2a2a2a;display:inline-block;text-align:left;">' . $list_html . '</ul></td></tr></table>',
                'text' => $list_text . "
",
            ];

        case 'divider':
            return [
                'html' => '<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr><td style="padding:16px 0;"><div style="border-top:1px solid #e6e6e6;"></div></td></tr></table>',
                'text' => "----------------
",
            ];

        case 'button':
            $url   = teinvit_email_apply_tags( $block['url'] ?? '', $context );
            $label = teinvit_email_apply_tags( $block['label'] ?? '', $context );
            if ( $url === '' || $label === '' ) {
                return [ 'html' => '', 'text' => '' ];
            }
            $style  = ( $block['style'] ?? 'primary' ) === 'secondary' ? 'secondary' : 'primary';
            $bg     = $style === 'secondary' ? '#f3f0eb' : $accent;
            $color  = $style === 'secondary' ? '#6b4a2c' : '#ffffff';
            $border = $style === 'secondary' ? '#d8c9bb' : $accent;
            return [
                'html' => '<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr><td style="padding:4px 0 16px 0;' . $align_css . '"><table role="presentation" cellpadding="0" cellspacing="0" style="display:inline-table;"><tr><td style="border-radius:8px;background:' . esc_attr( $bg ) . ';border:1px solid ' . esc_attr( $border ) . ';"><a href="' . esc_url( $url ) . '" style="display:inline-block;padding:12px 22px;font-family:Arial,Helvetica,sans-serif;font-size:15px;font-weight:700;color:' . esc_attr( $color ) . ';text-decoration:none;border-radius:8px;">' . esc_html( $label ) . '</a></td></tr></table></td></tr></table>',
                'text' => $label . ': ' . $url . "

",
            ];

        case 'link':
            $url   = teinvit_email_apply_tags( $block['url'] ?? '', $context );
            $label = teinvit_email_apply_tags( $block['label'] ?? '', $context );
            if ( $url === '' || $label === '' ) {
                return [ 'html' => '', 'text' => '' ];
            }
            return [
                'html' => '<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr><td style="padding:0 0 14px 0;' . $align_css . 'font-family:Arial,Helvetica,sans-serif;font-size:14px;line-height:22px;"><a href="' . esc_url( $url ) . '" style="color:' . esc_attr( $accent ) . ';text-decoration:underline;">' . esc_html( $label ) . '</a></td></tr></table>',
                'text' => $label . ': ' . $url . "

",
            ];

        case 'footer':
            $raw_html = teinvit_email_apply_tags( (string) ( $block['html'] ?? '' ), $context );
            $raw_text = teinvit_email_apply_tags( (string) ( $block['text'] ?? '' ), $context );
            $has_html = trim( wp_strip_all_tags( $raw_html ) ) !== '' || strpos( $raw_html, '<' ) !== false;
            if ( $has_html ) {
                $safe_html = wp_kses_post( $raw_html );
                $html_body = wpautop( $safe_html );
                $text_body = wp_strip_all_tags( $safe_html );
            } else {
                $plain = sanitize_textarea_field( $raw_text );
                if ( trim( $plain ) === '' ) {
                    return [ 'html' => '', 'text' => '' ];
                }
                $html_body = wpautop( esc_html( $plain ) );
                $text_body = $plain;
            }

            return [
                'html' => '<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr><td style="padding:18px 0 0 0;' . $align_css . 'font-family:Arial,Helvetica,sans-serif;font-size:12px;line-height:19px;color:#6b6b6b;border-top:1px solid #ececec;">' . $html_body . '</td></tr></table>',
                'text' => trim( preg_replace( '/\r\n|\r|\n/', "\n", (string) $text_body ) ) . "
",
            ];
    }

    return [ 'html' => '', 'text' => '' ];
}

function teinvit_email_render_blocks( array $blocks, array $context, $accent ) {
    $html = '';
    $text = '';

    foreach ( $blocks as $block ) {
        if ( ! is_array( $block ) ) {
            continue;
        }
        $render = teinvit_email_render_block( $block, $context, $accent );
        $html  .= (string) ( $render['html'] ?? '' );
        $text  .= (string) ( $render['text'] ?? '' );
    }

    return [
        'html' => $html,
        'text' => trim( $text ),
    ];
}

function teinvit_email_theme_wrap_html( $subject, $preheader, $accent, $body_html ) {
    $subject   = esc_html( (string) $subject );
    $preheader = esc_html( (string) $preheader );
    $accent    = sanitize_hex_color( $accent );
    if ( ! $accent ) {
        $accent = '#B07A4F';
    }

    return '<!doctype html>
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<meta name="viewport" content="width=device-width,initial-scale=1" />
<title>' . $subject . '</title>
</head>
<body style="margin:0;padding:0;background:#f4f1ed;">
<div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent;visibility:hidden;">' . $preheader . '</div>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f1ed;padding:24px 0;">
<tr>
<td align="center">
<table role="presentation" width="620" cellpadding="0" cellspacing="0" style="width:620px;max-width:620px;background:#ffffff;border-radius:14px;border:1px solid #eee4da;overflow:hidden;">
<tr><td style="height:6px;background:' . esc_attr( $accent ) . ';line-height:6px;font-size:6px;">&nbsp;</td></tr>
<tr>
<td style="padding:28px 30px 28px 30px;">' . $body_html . '</td>
</tr>
</table>
</td>
</tr>
</table>
</body>
</html>';
}

function teinvit_email_render_template( array $template, array $args ) {
    $context = teinvit_email_build_context( $args );
    $subject = teinvit_email_apply_tags( (string) ( $template['subject'] ?? '' ), $context );
    $preheader = teinvit_email_apply_tags( (string) ( $template['preheader'] ?? '' ), $context );
    $accent = (string) ( $template['accent_color'] ?? '#B07A4F' );
    $blocks = is_array( $template['blocks'] ?? null ) ? $template['blocks'] : [];

    $rendered = teinvit_email_render_blocks( $blocks, $context, $accent );
    $full_html = teinvit_email_theme_wrap_html( $subject, $preheader, $accent, $rendered['html'] );

    return [
        'subject'   => $subject,
        'preheader' => $preheader,
        'body_html' => $full_html,
        'body_text' => $rendered['text'],
    ];
}

function teinvit_email_hash_ua() {
    $ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? (string) wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) : '';
    if ( $ua === '' ) {
        return null;
    }

    return hash( 'sha256', $ua );
}

function teinvit_email_hash_ip() {
    $ip = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) wp_unslash( $_SERVER['REMOTE_ADDR'] ) : '';
    if ( $ip === '' ) {
        return null;
    }

    return hash( 'sha256', gmdate( 'Y-m' ) . '|' . $ip );
}

function teinvit_email_log_event( $send_id, $event_type, $url = null ) {
    global $wpdb;

    $tables = teinvit_email_tables();
    $event_type = sanitize_key( $event_type );
    $send_id    = sanitize_text_field( $send_id );
    $url        = $url ? esc_url_raw( $url ) : null;

    if ( $event_type === 'click' && $send_id !== '' && $url ) {
        $window_start = gmdate( 'Y-m-d H:i:s', time() - MINUTE_IN_SECONDS );
        $existing = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$tables['events']} WHERE send_id=%s AND event_type='click' AND url=%s AND event_at >= %s",
                $send_id,
                $url,
                $window_start
            )
        );
        if ( $existing > 0 ) {
            return;
        }
    }

    $wpdb->insert(
        $tables['events'],
        [
            'send_id'    => $send_id,
            'event_type' => $event_type,
            'event_at'   => current_time( 'mysql' ),
            'ip_hash'    => teinvit_email_hash_ip(),
            'ua_hash'    => teinvit_email_hash_ua(),
            'url'        => $url,
            'meta_json'  => null,
        ]
    );
}

function teinvit_email_rate_limit_details( array $template, $recipient_email, $token = '' ) {
    $apply_rate_limit = array_key_exists( 'apply_rate_limit', $template ) ? ! empty( $template['apply_rate_limit'] ) : ! empty( $template['is_marketing'] );
    if ( ! $apply_rate_limit ) {
        return [
            'enabled' => false,
            'hit' => false,
            'count' => 0,
            'limit' => 0,
            'days' => 0,
            'key' => 'template=' . (string) ( $template['id'] ?? '' ) . '|recipient=' . (string) $recipient_email . '|token=' . (string) $token,
        ];
    }

    global $wpdb;

    $tables      = teinvit_email_tables();
    $count_limit = max( 1, (int) ( $template['rate_limit_count'] ?? 2 ) );
    $days_limit  = max( 1, (int) ( $template['rate_limit_days'] ?? 7 ) );
    $from_time   = gmdate( 'Y-m-d H:i:s', time() - $days_limit * DAY_IN_SECONDS );

    $count = (int) $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(*) FROM {$tables['sends']} WHERE template_id=%s AND recipient_email=%s AND status='sent' AND sent_at >= %s",
            $template['id'],
            $recipient_email,
            $from_time
        )
    );

    return [
        'enabled' => true,
        'hit' => $count >= $count_limit,
        'count' => $count,
        'limit' => $count_limit,
        'days' => $days_limit,
        'key' => 'template=' . (string) ( $template['id'] ?? '' ) . '|recipient=' . (string) $recipient_email,
    ];
}

function teinvit_email_has_recent_duplicate( array $template, $recipient_email, $semantic_hash ) {
    if ( $semantic_hash === '' ) {
        return false;
    }

    global $wpdb;
    $tables    = teinvit_email_tables();
    $from_time = gmdate( 'Y-m-d H:i:s', time() - 10 * MINUTE_IN_SECONDS );

    $count = (int) $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(*) FROM {$tables['sends']} WHERE template_id=%s AND recipient_email=%s AND semantic_hash=%s AND created_at >= %s",
            $template['id'],
            $recipient_email,
            $semantic_hash,
            $from_time
        )
    );

    return $count > 0;
}

function teinvit_email_save_send( array $template, array $rendered, array $args, $status = 'queued' ) {
    global $wpdb;

    $tables    = teinvit_email_tables();
    $send_id   = teinvit_email_uuid_v4();
    $now       = current_time( 'mysql' );
    $recipient = sanitize_email( (string) ( $args['recipient_email'] ?? '' ) );

    $wpdb->insert(
        $tables['sends'],
        [
            'send_id'            => $send_id,
            'template_key'       => sanitize_key( (string) ( $template['trigger'] ?? '' ) . '_' . (string) ( $template['audience'] ?? '' ) ),
            'template_id'        => sanitize_text_field( (string) ( $template['id'] ?? '' ) ),
            'trigger_key'        => sanitize_key( (string) ( $template['trigger'] ?? '' ) ),
            'audience_type'      => sanitize_key( (string) ( $template['audience'] ?? '' ) ),
            'token'              => sanitize_text_field( (string) ( $args['token'] ?? '' ) ),
            'order_id'           => (int) ( $args['order_id'] ?? 0 ),
            'rsvp_id'            => (int) ( $args['rsvp_id'] ?? 0 ),
            'recipient_email'    => $recipient,
            'recipient_hash'     => hash( 'sha256', strtolower( $recipient ) ),
            'subject_rendered'   => wp_strip_all_tags( (string) ( $rendered['subject'] ?? '' ) ),
            'heading_rendered'   => null,
            'preheader_rendered' => wp_strip_all_tags( (string) ( $rendered['preheader'] ?? '' ) ),
            'body_rendered'      => (string) ( $rendered['body_html'] ?? '' ),
            'body_text'          => (string) ( $rendered['body_text'] ?? '' ),
            'body_rendered_hash' => hash( 'sha256', (string) ( $rendered['body_html'] ?? '' ) ),
            'semantic_hash'      => sanitize_text_field( (string) ( $args['semantic_hash'] ?? '' ) ),
            'status'             => sanitize_key( $status ),
            'scheduled_at'       => ! empty( $args['scheduled_at'] ) ? gmdate( 'Y-m-d H:i:s', (int) $args['scheduled_at'] ) : null,
            'created_at'         => $now,
            'updated_at'         => $now,
        ]
    );

    return $send_id;
}

function teinvit_email_get_send( $send_id ) {
    global $wpdb;
    $tables = teinvit_email_tables();

    return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$tables['sends']} WHERE send_id=%s", $send_id ), ARRAY_A );
}

function teinvit_email_resolve_template_id( $template_id, array $args = [] ) {
    $template = teinvit_get_email_template( $template_id );
    if ( $template ) {
        return $template_id;
    }

    $trigger  = sanitize_key( (string) ( $args['trigger'] ?? '' ) );
    $audience = sanitize_key( (string) ( $args['audience'] ?? '' ) );

    foreach ( teinvit_get_email_templates() as $id => $tpl ) {
        if ( ( $tpl['status'] ?? 'draft' ) !== 'active' ) {
            continue;
        }
        if ( $trigger !== '' && sanitize_key( (string) ( $tpl['trigger'] ?? '' ) ) !== $trigger ) {
            continue;
        }
        if ( $audience !== '' && sanitize_key( (string) ( $tpl['audience'] ?? '' ) ) !== $audience ) {
            continue;
        }
        return (string) $id;
    }

    return $template_id;
}


function teinvit_email_current_wc_settings_section_id() {
    if ( ! is_admin() ) {
        return '';
    }

    $page = isset( $_GET['page'] ) ? sanitize_key( (string) wp_unslash( $_GET['page'] ) ) : '';
    $tab  = isset( $_GET['tab'] ) ? sanitize_key( (string) wp_unslash( $_GET['tab'] ) ) : '';
    if ( $page !== 'wc-settings' || ! in_array( $tab, [ 'email', 'emails' ], true ) ) {
        return '';
    }

    $section = isset( $_GET['section'] ) ? sanitize_key( (string) wp_unslash( $_GET['section'] ) ) : '';
    return $section;
}

function teinvit_email_debug_mailer_registry() {
    if ( ! function_exists( 'WC' ) || ! WC() ) {
        return 'mailer=unavailable';
    }

    $mailer = WC()->mailer();
    if ( ! $mailer || ! method_exists( $mailer, 'get_emails' ) ) {
        return 'mailer=invalid';
    }

    $parts = [];
    foreach ( (array) $mailer->get_emails() as $key => $email ) {
        $parts[] = sanitize_key( (string) $key ) . ':' . ( is_object( $email ) && function_exists( 'spl_object_hash' ) ? spl_object_hash( $email ) : 'nohash' );
    }

    return 'emails=' . implode( ',', $parts );
}


function teinvit_email_debug_request_context() {
    $keys = [ 'page', 'tab', 'section', 'email', 'action', 'wc-api' ];
    $parts = [];
    foreach ( $keys as $key ) {
        $value = isset( $_REQUEST[ $key ] ) ? sanitize_text_field( (string) wp_unslash( $_REQUEST[ $key ] ) ) : '';
        $parts[] = $key . '=' . $value;
    }

    return implode( ' ', $parts );
}

function teinvit_email_template_id_for_wc_email_id( $wc_email_id ) {
    $map = [
        'teinvit_email_token_generated' => 'token_generated_customer',
        'teinvit_email_rsvp_received' => 'rsvp_received_customer',
        'teinvit_email_guest_marketing_1' => 'guest_marketing_consent_1',
    ];

    $wc_email_id = sanitize_key( (string) $wc_email_id );
    if ( strpos( $wc_email_id, 'teinvit_email_tpl_' ) === 0 ) {
        $template_id = substr( $wc_email_id, strlen( 'teinvit_email_tpl_' ) );
        return sanitize_key( (string) $template_id );
    }

    return $map[ $wc_email_id ] ?? '';
}

function teinvit_email_wc_id_for_template_id( $template_id ) {
    $template_id = sanitize_key( (string) $template_id );
    if ( $template_id === '' ) {
        return '';
    }

    $map = [
        'token_generated_customer' => 'teinvit_email_token_generated',
        'rsvp_received_customer' => 'teinvit_email_rsvp_received',
        'guest_marketing_consent_1' => 'teinvit_email_guest_marketing_1',
    ];

    if ( isset( $map[ $template_id ] ) ) {
        return $map[ $template_id ];
    }

    return 'teinvit_email_tpl_' . $template_id;
}

function teinvit_email_active_templates_for_event( $trigger, $audience ) {
    $trigger  = sanitize_key( (string) $trigger );
    $audience = sanitize_key( (string) $audience );
    $matches  = [];

    foreach ( teinvit_get_email_templates() as $id => $tpl ) {
        if ( ! is_array( $tpl ) ) {
            continue;
        }

        $template_id = sanitize_key( (string) ( $tpl['id'] ?? $id ) );
        if ( $template_id === '' ) {
            continue;
        }

        if ( ( $tpl['status'] ?? 'draft' ) !== 'active' ) {
            continue;
        }

        if ( $trigger !== '' && sanitize_key( (string) ( $tpl['trigger'] ?? '' ) ) !== $trigger ) {
            continue;
        }

        if ( $audience !== '' && sanitize_key( (string) ( $tpl['audience'] ?? '' ) ) !== $audience ) {
            continue;
        }

        $matches[] = $template_id;
    }

    return array_values( array_unique( $matches ) );
}

function teinvit_email_sample_context_args( $template_id, $recipient_email = '' ) {
    return [
        'token' => 'sample-token-123',
        'order_id' => 0,
        'rsvp_id' => 0,
        'recipient_email' => $recipient_email !== '' ? $recipient_email : sanitize_email( get_option( 'admin_email' ) ),
        'send_id' => teinvit_email_uuid_v4(),
        'payload' => [
            'guest_first_name' => 'Alex',
            'guest_last_name' => 'Popescu',
            'guest_phone' => '0712345678',
            'guest_email' => 'alex@example.com',
            'attending_people_count' => 2,
            'kids_count' => 1,
            'attending_civil' => 1,
            'attending_religious' => 1,
            'attending_party' => 1,
            'needs_accommodation' => 0,
            'vegetarian_requested' => 0,
            'vegetarian_menus_count' => 0,
            'allergy_details' => 'N/A',
            'message_to_couple' => 'Casă de piatră!',
            'marketing_consent' => 1,
        ],
    ];
}

function teinvit_email_delay_seconds( array $template ) {
    $value = max( 0, (int) ( $template['delay_value'] ?? 0 ) );
    $unit  = sanitize_key( (string) ( $template['delay_unit'] ?? 'hours' ) );

    if ( $value <= 0 ) {
        return 0;
    }

    if ( $unit === 'minutes' ) {
        return $value * MINUTE_IN_SECONDS;
    }

    if ( $unit === 'days' ) {
        return $value * DAY_IN_SECONDS;
    }

    return $value * HOUR_IN_SECONDS;
}

function teinvit_email_scheduled_timestamp_for_template( array $template ) {
    $delay_seconds = teinvit_email_delay_seconds( $template );
    return $delay_seconds > 0 ? time() + $delay_seconds : null;
}

function teinvit_email_queue_args_with_template_delay( $template_id, array $args ) {
    if ( ! empty( $args['scheduled_at'] ) ) {
        return $args;
    }

    $template = teinvit_get_email_template( $template_id );
    if ( ! is_array( $template ) ) {
        return $args;
    }

    $args['scheduled_at'] = teinvit_email_scheduled_timestamp_for_template( $template );
    return $args;
}

function teinvit_email_set_dispatch_context( array $context = null ) {
    $GLOBALS['teinvit_email_dispatch_context'] = $context;
}

function teinvit_email_get_dispatch_context() {
    $context = $GLOBALS['teinvit_email_dispatch_context'] ?? null;
    return is_array( $context ) ? $context : null;
}

function teinvit_email_clear_dispatch_context() {
    unset( $GLOBALS['teinvit_email_dispatch_context'] );
}

function teinvit_email_failure_message( $error ) {
    if ( is_wp_error( $error ) ) {
        $message = $error->get_error_message();
        if ( $message !== '' ) {
            return sanitize_text_field( $message );
        }
    }

    return 'wp_mail_failed';
}

function teinvit_email_attach_tracking( $send_id, $html ) {
    $html = preg_replace_callback(
        '/href\s*=\s*"([^"]+)"/i',
        static function( $m ) use ( $send_id ) {
            $url = html_entity_decode( $m[1], ENT_QUOTES, 'UTF-8' );
            if ( strpos( $url, 'mailto:' ) === 0 || strpos( $url, '#' ) === 0 ) {
                return $m[0];
            }
            $encoded = teinvit_email_b64url_encode( $url );
            $sig     = teinvit_email_sign( $send_id . '|' . $encoded );
            $track   = home_url( '/c/' . rawurlencode( $send_id ) . '/' . rawurlencode( $sig ) . '/?u=' . rawurlencode( $encoded ) );
            return 'href="' . esc_url( $track ) . '"';
        },
        $html
    );

    $open_sig = teinvit_email_sign( $send_id . '|open' );
    $open_url = home_url( '/o/' . rawurlencode( $send_id ) . '/' . rawurlencode( $open_sig ) . '/p.gif' );

    return $html . '<img src="' . esc_url( $open_url ) . '" width="1" height="1" alt="" style="display:none;border:0;" />';
}

function teinvit_email_dispatch_wc( $send_id ) {
    global $wpdb;

    $send = teinvit_email_get_send( $send_id );
    if ( ! $send ) {
        return;
    }

    $recipient = sanitize_email( (string) ( $send['recipient_email'] ?? '' ) );
    if ( $recipient === '' || ! is_email( $recipient ) ) {
        return;
    }

    $wc_id = teinvit_email_wc_id_for_template_id( (string) ( $send['template_id'] ?? '' ) );
    if ( $wc_id === '' ) {
        return;
    }

    if ( ! function_exists( 'WC' ) || ! WC() ) {
        return;
    }

    $mailer = WC()->mailer();
    $emails = $mailer->get_emails();
    if ( empty( $emails[ $wc_id ] ) ) {
        return;
    }

    error_log( '[TeInvit Emails] dispatch start send_id=' . (string) $send_id . ' template_id=' . (string) ( $send['template_id'] ?? '' ) . ' wc_id=' . $wc_id . ' recipient=' . $recipient );
    teinvit_email_set_dispatch_context(
        [
            'send_id'   => sanitize_text_field( (string) $send_id ),
            'wc_id'     => sanitize_key( (string) $wc_id ),
            'recipient' => $recipient,
            'subject'   => sanitize_text_field( (string) ( $send['subject_rendered'] ?? '' ) ),
        ]
    );

    $ok = $emails[ $wc_id ]->trigger( $send_id );
    $failed_send = teinvit_email_get_send( $send_id );
    $already_failed = is_array( $failed_send ) && ( $failed_send['status'] ?? '' ) === 'failed';
    $existing_note = (string) ( $send['error_message'] ?? '' );
    $sent_note = strpos( $existing_note, 'test_context' ) === 0 ? $existing_note : null;

    if ( $ok && ! $already_failed ) {
        $wpdb->update(
            teinvit_email_tables()['sends'],
            [
                'status'        => 'sent',
                'error_message' => $sent_note,
                'sent_at'       => current_time( 'mysql' ),
                'updated_at'    => current_time( 'mysql' ),
            ],
            [ 'send_id' => $send_id ]
        );
    } elseif ( ! $ok && ! $already_failed ) {
        $wpdb->update(
            teinvit_email_tables()['sends'],
            [
                'status'        => 'failed',
                'error_message' => 'wc_mailer_send_failed',
                'sent_at'       => null,
                'updated_at'    => current_time( 'mysql' ),
            ],
            [ 'send_id' => $send_id ]
        );
    }

    error_log( '[TeInvit Emails] dispatch result send_id=' . (string) $send_id . ' ok=' . ( $ok ? '1' : '0' ) . ' already_failed=' . ( $already_failed ? '1' : '0' ) );
    teinvit_email_clear_dispatch_context();
}

function teinvit_email_schedule_send( $send_id, $timestamp = null ) {
    $timestamp = $timestamp ? (int) $timestamp : time();
    error_log( '[TeInvit Emails] schedule send_id=' . (string) $send_id . ' timestamp=' . (string) $timestamp . ' gmt=' . gmdate( 'Y-m-d H:i:s', $timestamp ) );

    if ( function_exists( 'as_schedule_single_action' ) ) {
        as_schedule_single_action( $timestamp, 'teinvit_email_process_send', [ 'send_id' => $send_id ], 'teinvit-emails' );
        return;
    }

    wp_schedule_single_event( $timestamp, 'teinvit_email_process_send', [ $send_id ] );
}

add_action(
    'teinvit_email_process_send',
    function( $send_id ) {
        if ( is_array( $send_id ) && isset( $send_id['send_id'] ) ) {
            $send_id = $send_id['send_id'];
        }
        $send_id = sanitize_text_field( (string) $send_id );
        error_log( '[TeInvit Emails] process_send send_id=' . $send_id );
        teinvit_email_dispatch_wc( $send_id );
    },
    10,
    1
);

add_filter(
    'wp_mail',
    function( $args ) {
        $context = teinvit_email_get_dispatch_context();
        if ( ! is_array( $context ) || empty( $context['send_id'] ) ) {
            return $args;
        }

        $header = 'X-TeInvit-Send-ID: ' . sanitize_text_field( (string) $context['send_id'] );
        if ( empty( $args['headers'] ) ) {
            $args['headers'] = [ $header ];
        } elseif ( is_array( $args['headers'] ) ) {
            $args['headers'][] = $header;
        } else {
            $args['headers'] .= "\r\n" . $header;
        }

        return $args;
    },
    10,
    1
);

add_action(
    'wp_mail_failed',
    function( $error ) {
        global $wpdb;

        $context = teinvit_email_get_dispatch_context();
        if ( ! is_array( $context ) || empty( $context['send_id'] ) ) {
            return;
        }

        $message = teinvit_email_failure_message( $error );
        error_log( '[TeInvit Emails] wp_mail_failed send_id=' . (string) $context['send_id'] . ' message=' . $message );

        $wpdb->update(
            teinvit_email_tables()['sends'],
            [
                'status'        => 'failed',
                'error_message' => $message,
                'sent_at'       => null,
                'updated_at'    => current_time( 'mysql' ),
            ],
            [ 'send_id' => sanitize_text_field( (string) $context['send_id'] ) ]
        );
    },
    10,
    1
);


function teinvit_email_parse_product_ids( $raw ) {
    if ( is_array( $raw ) ) {
        $raw = implode( ',', array_map( 'strval', $raw ) );
    }

    $parts = preg_split( '/[^0-9]+/', (string) $raw );
    if ( ! is_array( $parts ) ) {
        return [];
    }

    $ids = [];
    foreach ( $parts as $part ) {
        $id = (int) $part;
        if ( $id > 0 ) {
            $ids[] = $id;
        }
    }

    return array_values( array_unique( $ids ) );
}

function teinvit_email_template_product_ids( array $template ) {
    return teinvit_email_parse_product_ids( $template['product_ids'] ?? [] );
}

function teinvit_email_order_product_ids( $order_id ) {
    $order = ( $order_id > 0 && function_exists( 'wc_get_order' ) ) ? wc_get_order( $order_id ) : null;
    if ( ! $order ) {
        return [];
    }

    $product_ids = [];
    foreach ( $order->get_items( 'line_item' ) as $item ) {
        if ( ! $item || ! is_a( $item, 'WC_Order_Item_Product' ) ) {
            continue;
        }
        $pid = (int) $item->get_product_id();
        if ( $pid > 0 ) {
            $product_ids[] = $pid;
        }
        $vid = (int) $item->get_variation_id();
        if ( $vid > 0 ) {
            $product_ids[] = $vid;
        }
    }

    return array_values( array_unique( $product_ids ) );
}

function teinvit_email_related_order_ids_for_token( $token, $base_order_id = 0 ) {
    global $wpdb;

    $token = sanitize_text_field( (string) $token );
    $ids   = [];
    $base_order_id = (int) $base_order_id;
    if ( $base_order_id > 0 ) {
        $ids[] = $base_order_id;
    }

    if ( $token === '' ) {
        return array_values( array_unique( $ids ) );
    }

    $meta_rows = $wpdb->get_col(
        $wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value = %s",
            '_teinvit_token_target',
            $token
        )
    );
    foreach ( (array) $meta_rows as $order_id ) {
        $order_id = (int) $order_id;
        if ( $order_id > 0 ) {
            $ids[] = $order_id;
        }
    }

    $items_table = $wpdb->prefix . 'woocommerce_order_items';
    $itemmeta_table = $wpdb->prefix . 'woocommerce_order_itemmeta';
    $item_rows = $wpdb->get_col(
        $wpdb->prepare(
            "SELECT DISTINCT oi.order_id
            FROM {$items_table} oi
            INNER JOIN {$itemmeta_table} oim ON oim.order_item_id = oi.order_item_id
            WHERE oi.order_item_type = 'line_item'
              AND oim.meta_key = %s
              AND oim.meta_value = %s",
            '_teinvit_token_target',
            $token
        )
    );
    foreach ( (array) $item_rows as $order_id ) {
        $order_id = (int) $order_id;
        if ( $order_id > 0 ) {
            $ids[] = $order_id;
        }
    }

    return array_values( array_unique( $ids ) );
}

function teinvit_email_order_product_ids_for_context( $order_id, $token = '' ) {
    $products = [];
    foreach ( teinvit_email_related_order_ids_for_token( $token, (int) $order_id ) as $related_order_id ) {
        $products = array_merge( $products, teinvit_email_order_product_ids( $related_order_id ) );
    }

    return array_values( array_unique( array_map( 'intval', $products ) ) );
}

function teinvit_email_order_matches_template_products( $order_id, array $template, array $args = [] ) {
    $allowed = teinvit_email_template_product_ids( $template );
    if ( empty( $allowed ) ) {
        return [ 'match' => true, 'allowed' => [], 'order_products' => [], 'reason' => 'all_products' ];
    }

    $order_products = teinvit_email_order_product_ids_for_context( (int) $order_id, (string) ( $args['token'] ?? '' ) );
    if ( empty( $order_products ) ) {
        return [ 'match' => false, 'allowed' => $allowed, 'order_products' => [], 'reason' => 'missing_order_products' ];
    }

    $intersection = array_values( array_intersect( $allowed, $order_products ) );
    return [
        'match' => ! empty( $intersection ),
        'allowed' => $allowed,
        'order_products' => $order_products,
        'matched' => $intersection,
        'reason' => ! empty( $intersection ) ? 'matched' : 'product_mismatch',
    ];
}

function teinvit_email_log_skipped_queue( array $template, array $args, $recipient, $reason, $details = '' ) {
    global $wpdb;
    $tables = teinvit_email_tables();
    $now    = current_time( 'mysql' );

    $wpdb->insert(
        $tables['sends'],
        [
            'send_id'            => teinvit_email_uuid_v4(),
            'template_key'       => sanitize_key( (string) ( $template['trigger'] ?? '' ) . '_' . (string) ( $template['audience'] ?? '' ) ),
            'template_id'        => sanitize_text_field( (string) ( $template['id'] ?? '' ) ),
            'trigger_key'        => sanitize_key( (string) ( $template['trigger'] ?? '' ) ),
            'audience_type'      => sanitize_key( (string) ( $template['audience'] ?? '' ) ),
            'token'              => sanitize_text_field( (string) ( $args['token'] ?? '' ) ),
            'order_id'           => (int) ( $args['order_id'] ?? 0 ),
            'rsvp_id'            => (int) ( $args['rsvp_id'] ?? 0 ),
            'recipient_email'    => sanitize_email( (string) $recipient ),
            'recipient_hash'     => $recipient !== '' ? hash( 'sha256', strtolower( (string) $recipient ) ) : null,
            'subject_rendered'   => '',
            'heading_rendered'   => null,
            'preheader_rendered' => '',
            'body_rendered'      => null,
            'body_text'          => '',
            'body_rendered_hash' => hash( 'sha256', '' ),
            'semantic_hash'      => sanitize_text_field( (string) ( $args['semantic_hash'] ?? '' ) ),
            'status'             => 'skipped',
            'error_message'      => sanitize_text_field( (string) $reason . ( $details !== '' ? ' | ' . $details : '' ) ),
            'scheduled_at'       => null,
            'created_at'         => $now,
            'updated_at'         => $now,
        ]
    );
}

function teinvit_email_queue_template( $template_id, array $args ) {
    $template_id = sanitize_key( (string) $template_id );
    if ( empty( $args['exact_template'] ) ) {
        $template_id = teinvit_email_resolve_template_id(
            $template_id,
            [
                'trigger'  => $args['trigger'] ?? '',
                'audience' => $args['audience'] ?? '',
            ]
        );
    }

    $template = teinvit_get_email_template( $template_id );
    if ( ! $template || ( $template['status'] ?? 'draft' ) !== 'active' ) {
        error_log( '[TeInvit Emails] skipped queue: template missing/inactive for ' . (string) $template_id );
        return null;
    }

    $recipient = sanitize_email( (string) ( $args['recipient_email'] ?? '' ) );
    if ( $recipient === '' || ! is_email( $recipient ) ) {
        error_log( '[TeInvit Emails] skipped queue: invalid recipient for template ' . (string) $template_id );
        teinvit_email_log_skipped_queue( $template, $args, $recipient, 'invalid_recipient' );
        return null;
    }

    $bypass_product_filter = ! empty( $args['bypass_product_filter'] ) || ! empty( $args['is_preview_test'] );
    $product_match = teinvit_email_order_matches_template_products(
        (int) ( $args['order_id'] ?? 0 ),
        $template,
        [
            'token'           => $args['token'] ?? '',
            'is_preview_test' => ! empty( $args['is_preview_test'] ),
        ]
    );
    if ( $bypass_product_filter ) {
        if ( teinvit_email_debug_enabled() ) {
            error_log( '[TeInvit Emails] test context: bypass product filter template=' . (string) $template_id . ' trigger=' . (string) ( $template['trigger'] ?? '' ) . ' audience=' . (string) ( $template['audience'] ?? '' ) );
        }
    } elseif ( empty( $product_match['match'] ) ) {
        $details = 'allowed=' . implode( ',', array_map( 'strval', $product_match['allowed'] ?? [] ) ) . ' order_products=' . implode( ',', array_map( 'strval', $product_match['order_products'] ?? [] ) );
        $reason = (string) ( $product_match['reason'] ?? 'product_mismatch' );
        if ( $reason === 'missing_order_products' ) {
            error_log( '[TeInvit Emails] skipped queue: missing order products for runtime template ' . (string) $template_id . ' token=' . (string) ( $args['token'] ?? '' ) . ' order_id=' . (string) ( $args['order_id'] ?? 0 ) . ' ' . $details );
            teinvit_email_log_skipped_queue( $template, $args, $recipient, 'missing_order_products', $details );
        } else {
            if ( teinvit_email_debug_enabled() ) {
                error_log( '[TeInvit Emails] skipped queue: product mismatch for template ' . (string) $template_id . ' ' . $details );
            }
            teinvit_email_log_skipped_queue( $template, $args, $recipient, 'product_mismatch', $details );
        }
        return null;
    }

    if ( ! empty( $template['is_marketing'] ) || ! empty( $template['require_consent'] ) ) {
        if ( empty( $args['marketing_consent'] ) ) {
            error_log( '[TeInvit Emails] skipped queue: missing marketing consent for template ' . (string) $template_id );
            teinvit_email_log_skipped_queue( $template, $args, $recipient, 'no_consent' );
            return null;
        }
        if ( teinvit_email_is_suppressed( $recipient, 'marketing' ) ) {
            error_log( '[TeInvit Emails] skipped queue: recipient suppressed for template ' . (string) $template_id );
            teinvit_email_log_skipped_queue( $template, $args, $recipient, 'suppressed' );
            return null;
        }
    }

    $rate_limit = teinvit_email_rate_limit_details( $template, $recipient, (string) ( $args['token'] ?? '' ) );
    if ( ! empty( $rate_limit['enabled'] ) && ! empty( $rate_limit['hit'] ) ) {
        error_log(
            '[TeInvit Emails] skipped queue: rate limited for template ' . (string) $template_id .
            ' recipient ' . $recipient .
            ' key=' . (string) ( $rate_limit['key'] ?? '' ) .
            ' count=' . (string) ( $rate_limit['count'] ?? 0 ) .
            ' limit=' . (string) ( $rate_limit['limit'] ?? 0 ) .
            ' window_days=' . (string) ( $rate_limit['days'] ?? 0 )
        );
        teinvit_email_log_skipped_queue( $template, $args, $recipient, 'rate_limited' );
        return null;
    }

    $semantic_hash = (string) ( $args['semantic_hash'] ?? '' );
    if ( teinvit_email_has_recent_duplicate( $template, $recipient, $semantic_hash ) ) {
        error_log( '[TeInvit Emails] skipped queue: dedupe hit for template ' . (string) $template_id . ' recipient ' . $recipient );
        teinvit_email_log_skipped_queue( $template, $args, $recipient, 'dedupe' );
        return null;
    }

    $send_id = teinvit_email_uuid_v4();
    $rendered = teinvit_email_render_template(
        $template,
        [
            'token'           => $args['token'] ?? '',
            'order_id'        => $args['order_id'] ?? 0,
            'rsvp_id'         => $args['rsvp_id'] ?? 0,
            'payload'         => $args['payload'] ?? [],
            'recipient_email' => $recipient,
            'send_id'         => $send_id,
        ]
    );

    $tracked_html = teinvit_email_attach_tracking( $send_id, (string) $rendered['body_html'] );
    $rendered['body_html'] = $tracked_html;

    global $wpdb;
    $tables = teinvit_email_tables();
    $now    = current_time( 'mysql' );
    $queue_note = ! empty( $args['is_preview_test'] ) ? 'test_context | product_scope_bypassed' : null;

    $wpdb->insert(
        $tables['sends'],
        [
            'send_id'            => $send_id,
            'template_key'       => sanitize_key( (string) ( $template['trigger'] ?? '' ) . '_' . (string) ( $template['audience'] ?? '' ) ),
            'template_id'        => sanitize_text_field( (string) ( $template['id'] ?? '' ) ),
            'trigger_key'        => sanitize_key( (string) ( $template['trigger'] ?? '' ) ),
            'audience_type'      => sanitize_key( (string) ( $template['audience'] ?? '' ) ),
            'token'              => sanitize_text_field( (string) ( $args['token'] ?? '' ) ),
            'order_id'           => (int) ( $args['order_id'] ?? 0 ),
            'rsvp_id'            => (int) ( $args['rsvp_id'] ?? 0 ),
            'recipient_email'    => $recipient,
            'recipient_hash'     => hash( 'sha256', strtolower( $recipient ) ),
            'subject_rendered'   => wp_strip_all_tags( (string) $rendered['subject'] ),
            'heading_rendered'   => null,
            'preheader_rendered' => wp_strip_all_tags( (string) $rendered['preheader'] ),
            'body_rendered'      => (string) $rendered['body_html'],
            'body_text'          => (string) $rendered['body_text'],
            'body_rendered_hash' => hash( 'sha256', (string) $rendered['body_html'] ),
            'semantic_hash'      => sanitize_text_field( $semantic_hash ),
            'status'             => 'queued',
            'error_message'      => $queue_note,
            'scheduled_at'       => ! empty( $args['scheduled_at'] ) ? gmdate( 'Y-m-d H:i:s', (int) $args['scheduled_at'] ) : null,
            'created_at'         => $now,
            'updated_at'         => $now,
        ]
    );

    teinvit_email_schedule_send( $send_id, $args['scheduled_at'] ?? null );

    return $send_id;
}

function teinvit_email_customer_for_order( $order_id ) {
    if ( ! function_exists( 'wc_get_order' ) ) {
        return '';
    }

    $order = $order_id > 0 ? wc_get_order( $order_id ) : null;
    if ( ! $order ) {
        return '';
    }

    $email = sanitize_email( (string) $order->get_billing_email() );
    if ( $email !== '' && is_email( $email ) ) {
        return $email;
    }

    $user_id = (int) $order->get_user_id();
    if ( $user_id > 0 ) {
        $user = get_user_by( 'id', $user_id );
        if ( $user && is_email( $user->user_email ) ) {
            return sanitize_email( $user->user_email );
        }
    }

    return '';
}

function teinvit_email_customer_for_context( $token, $order_id ) {
    $recipient = teinvit_email_customer_for_order( (int) $order_id );
    if ( $recipient !== '' ) {
        return $recipient;
    }

    foreach ( teinvit_email_related_order_ids_for_token( $token, (int) $order_id ) as $related_order_id ) {
        $recipient = teinvit_email_customer_for_order( $related_order_id );
        if ( $recipient !== '' ) {
            return $recipient;
        }
    }

    return '';
}


function teinvit_email_resolve_order_token_context( $order_id ) {
    $resolved = [
        'token' => '',
        'source' => 'none',
    ];

    if ( ! function_exists( 'wc_get_order' ) ) {
        return $resolved;
    }

    $order = $order_id > 0 ? wc_get_order( $order_id ) : null;
    if ( ! $order ) {
        return $resolved;
    }

    foreach ( $order->get_items( 'line_item' ) as $item ) {
        if ( ! $item || ! is_a( $item, 'WC_Order_Item_Product' ) ) {
            continue;
        }

        $item_target = sanitize_text_field( (string) $item->get_meta( '_teinvit_token_target', true ) );
        if ( $item_target !== '' ) {
            $resolved['token'] = $item_target;
            $resolved['source'] = 'item_token_target';
            return $resolved;
        }
    }

    $order_target = sanitize_text_field( (string) $order->get_meta( '_teinvit_token_target', true ) );
    if ( $order_target !== '' ) {
        $resolved['token'] = $order_target;
        $resolved['source'] = 'order_token_target';
        return $resolved;
    }

    $order_token = sanitize_text_field( (string) $order->get_meta( '_teinvit_token', true ) );
    if ( $order_token !== '' ) {
        $resolved['token'] = $order_token;
        $resolved['source'] = 'order_token';
    }

    return $resolved;
}

function teinvit_email_order_id_by_token( $token ) {
    $token = sanitize_text_field( (string) $token );
    if ( $token === '' ) {
        return 0;
    }

    $order_id = function_exists( 'teinvit_get_order_id_by_token' ) ? (int) teinvit_get_order_id_by_token( $token ) : 0;
    if ( $order_id > 0 ) {
        return $order_id;
    }

    $vertical = function_exists( 'teinvit_resolve_token_vertical' ) ? sanitize_key( (string) teinvit_resolve_token_vertical( $token ) ) : '';
    $record = function_exists( 'teinvit_get_invitation_record' ) ? teinvit_get_invitation_record( $token, $vertical ) : null;
    if ( is_array( $record ) && ! empty( $record['order_id'] ) ) {
        return (int) $record['order_id'];
    }

    return 0;
}

function teinvit_email_get_previous_rsvp_for_phone( $token, $phone, $exclude_id, $vertical = '' ) {
    global $wpdb;
    $table = '';
    if ( function_exists( 'teinvit_rsvp_table_for_token' ) ) {
        $table = teinvit_rsvp_table_for_token( $token, $vertical );
    }
    if ( $table === '' ) {
        $t = teinvit_db_tables();
        $table = $t['rsvp'];
    }

    return $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM {$table} WHERE token=%s AND guest_phone=%s AND id<>%d ORDER BY id DESC LIMIT 1",
            $token,
            $phone,
            (int) $exclude_id
        ),
        ARRAY_A
    );
}

function teinvit_email_rsvp_payload_from_row( array $row ) {
    return [
        'guest_first_name'           => (string) ( $row['guest_first_name'] ?? '' ),
        'guest_last_name'            => (string) ( $row['guest_last_name'] ?? '' ),
        'guest_email'                => (string) ( $row['guest_email'] ?? '' ),
        'guest_phone'                => (string) ( $row['guest_phone'] ?? '' ),
        'attending_people_count'     => (int) ( $row['attending_people_count'] ?? 0 ),
        'attending_civil'            => (int) ( $row['attending_civil'] ?? 0 ),
        'attending_religious'        => (int) ( $row['attending_religious'] ?? 0 ),
        'attending_party'            => (int) ( $row['attending_party'] ?? 0 ),
        'bringing_kids'              => (int) ( $row['bringing_kids'] ?? 0 ),
        'kids_count'                 => (int) ( $row['kids_count'] ?? 0 ),
        'needs_accommodation'        => (int) ( $row['needs_accommodation'] ?? 0 ),
        'accommodation_people_count' => (int) ( $row['accommodation_people_count'] ?? 0 ),
        'vegetarian_requested'       => (int) ( $row['vegetarian_requested'] ?? 0 ),
        'vegetarian_menus_count'     => (int) ( $row['vegetarian_menus_count'] ?? 0 ),
        'has_allergies'              => (int) ( $row['has_allergies'] ?? 0 ),
        'allergy_details'            => (string) ( $row['allergy_details'] ?? '' ),
        'message_to_couple'          => (string) ( $row['message_to_couple'] ?? '' ),
        'gdpr_accepted'              => (int) ( $row['gdpr_accepted'] ?? 0 ),
        'marketing_consent'          => (int) ( $row['marketing_consent'] ?? 0 ),
        'extra_fields'               => teinvit_email_decode_json_array( $row['extra_fields'] ?? '' ),
    ];
}

add_action(
    'teinvit_token_generated',
    function( $order_id, $token ) {
        $recipient = teinvit_email_customer_for_order( (int) $order_id );
        if ( $recipient === '' ) {
            return;
        }

        $template_ids = teinvit_email_active_templates_for_event( 'token_generated', 'customer' );
        foreach ( $template_ids as $template_id ) {
            teinvit_email_queue_template(
                $template_id,
                teinvit_email_queue_args_with_template_delay(
                    $template_id,
                    [
                        'token'           => sanitize_text_field( (string) $token ),
                        'order_id'        => (int) $order_id,
                        'recipient_email' => $recipient,
                        'payload'         => [],
                        'trigger'         => 'token_generated',
                        'audience'        => 'customer',
                    ]
                )
            );
        }
    },
    10,
    2
);

add_action(
    'teinvit_invitation_version_saved',
    function( $token, $version_id, $payload ) {
        $token = sanitize_text_field( (string) $token );
        $version_id = (int) $version_id;
        $payload = is_array( $payload ) ? $payload : [];
        $order_id = (int) ( $payload['order_id'] ?? 0 );
        if ( $token === '' || $version_id <= 0 || $order_id <= 0 ) {
            return;
        }

        $recipient = teinvit_email_customer_for_order( $order_id );
        if ( empty( $recipient['email'] ) ) {
            return;
        }

        $template_ids = teinvit_email_active_templates_for_event( 'invitation_version_saved', 'customer' );
        if ( empty( $template_ids ) ) {
            return;
        }

        $snapshot_hash = sanitize_text_field( (string) ( $payload['snapshot_hash'] ?? '' ) );
        $semantic_hash = 'version_saved|' . $token . '|' . $version_id . '|' . $snapshot_hash;

        foreach ( $template_ids as $template_id ) {
            teinvit_email_queue_template(
                $template_id,
                [
                    'recipient_email' => $recipient['email'],
                    'token'           => $token,
                    'order_id'        => $order_id,
                    'payload'         => array_merge( $payload, [
                        'version_id' => $version_id,
                    ] ),
                    'semantic_hash'  => $semantic_hash,
                    'trigger'        => 'invitation_version_saved',
                    'audience'       => 'customer',
                ]
            );
        }
    },
    10,
    3
);

add_action(
    'teinvit_rsvp_saved',
    function( $token, $rsvp_id, $payload ) {
        $token   = sanitize_text_field( (string) $token );
        $rsvp_id = (int) $rsvp_id;
        $payload = is_array( $payload ) ? $payload : [];
        $vertical = function_exists( 'teinvit_resolve_token_vertical' ) ? teinvit_resolve_token_vertical( $token ) : 'wedding';
        $vertical = sanitize_key( (string) $vertical );
        if ( $vertical === '' ) {
            $vertical = 'wedding';
        }
        $order_id  = teinvit_email_order_id_by_token( $token );
        $payload['vertical'] = $vertical;
        $payload['order_id'] = $order_id;

        $phone = teinvit_email_normalize_phone( (string) ( $payload['guest_phone'] ?? '' ) );
        $prev  = $phone !== '' ? teinvit_email_get_previous_rsvp_for_phone( $token, $phone, $rsvp_id, $vertical ) : null;

        $current_hash = teinvit_email_payload_semantic_hash( $payload );
        $prev_hash    = is_array( $prev ) ? teinvit_email_payload_semantic_hash( teinvit_email_rsvp_payload_from_row( $prev ) ) : '';
        if ( $prev_hash !== '' && $prev_hash === $current_hash ) {
            return;
        }

        $recipient = teinvit_email_customer_for_context( $token, $order_id );
        if ( $recipient === '' ) {
            error_log( '[TeInvit Emails] RSVP customer skipped: missing customer recipient token=' . $token . ' vertical=' . $vertical . ' order_id=' . (string) $order_id );
            return;
        }

        $template_ids = teinvit_email_active_templates_for_event( 'rsvp_saved', 'customer' );
        foreach ( $template_ids as $template_id ) {
            teinvit_email_queue_template(
                $template_id,
                teinvit_email_queue_args_with_template_delay(
                    $template_id,
                    [
                        'token'           => $token,
                        'order_id'        => $order_id,
                        'rsvp_id'         => $rsvp_id,
                        'recipient_email' => $recipient,
                        'payload'         => $payload,
                        'semantic_hash'   => $current_hash,
                        'trigger'         => 'rsvp_saved',
                        'audience'        => 'customer',
                    ]
                )
            );
        }
    },
    10,
    3
);

add_action(
    'teinvit_rsvp_saved',
    function( $token, $rsvp_id, $payload ) {
        $token   = sanitize_text_field( (string) $token );
        $rsvp_id = (int) $rsvp_id;
        $payload = is_array( $payload ) ? $payload : [];
        $vertical = function_exists( 'teinvit_resolve_token_vertical' ) ? teinvit_resolve_token_vertical( $token ) : 'wedding';
        $vertical = sanitize_key( (string) $vertical );
        if ( $vertical === '' ) {
            $vertical = 'wedding';
        }

        $email = sanitize_email( (string) ( $payload['guest_email'] ?? '' ) );
        if ( $email === '' || ! is_email( $email ) ) {
            return;
        }

        $order_id = teinvit_email_order_id_by_token( $token );
        $consent  = ! empty( $payload['marketing_consent'] );
        if ( ! $consent ) {
            return;
        }
        if ( teinvit_email_is_suppressed( $email, 'marketing' ) ) {
            return;
        }
        $payload['vertical'] = $vertical;
        $payload['order_id'] = $order_id;
        $hash     = teinvit_email_payload_semantic_hash( $payload );
        $template_ids = teinvit_email_active_templates_for_event( 'guest_consent_1', 'guest' );

        foreach ( $template_ids as $template_id ) {
            $template = teinvit_get_email_template( $template_id );
            if ( ! is_array( $template ) ) {
                continue;
            }

            $scheduled_at = teinvit_email_scheduled_timestamp_for_template( $template );

            teinvit_email_queue_template(
                $template_id,
                [
                    'token'             => $token,
                    'order_id'          => $order_id,
                    'rsvp_id'           => $rsvp_id,
                    'recipient_email'   => $email,
                    'payload'           => $payload,
                    'marketing_consent' => $consent ? 1 : 0,
                    'semantic_hash'     => $hash,
                    'scheduled_at'      => $scheduled_at,
                    'trigger'           => 'guest_consent_1',
                    'audience'          => 'guest',
                ]
            );
        }
    },
    20,
    3
);

add_action(
    'woocommerce_order_status_completed',
    function( $order_id ) {
        $order_id = (int) $order_id;
        $recipient = teinvit_email_customer_for_order( $order_id );
        if ( $recipient === '' ) {
            error_log( '[TeInvit Emails] product_purchased skipped: invalid recipient order_id=' . (string) $order_id );
            return;
        }

        $template_ids = teinvit_email_active_templates_for_event( 'product_purchased', 'customer' );
        if ( empty( $template_ids ) ) {
            return;
        }

        $token_context = teinvit_email_resolve_order_token_context( $order_id );
        $order_products = teinvit_email_order_product_ids( $order_id );

        foreach ( $template_ids as $template_id ) {
            $template = teinvit_get_email_template( $template_id );
            $allowed_products = teinvit_email_template_product_ids( is_array( $template ) ? $template : [] );

            error_log(
                '[TeInvit Emails] queue trigger_key=product_purchased order_id=' . (string) $order_id .
                ' template_id=' . (string) $template_id .
                ' recipient=' . $recipient .
                ' order_products=' . implode( ',', array_map( 'strval', $order_products ) ) .
                ' allowed_products=' . implode( ',', array_map( 'strval', $allowed_products ) ) .
                ' token_source=' . (string) ( $token_context['source'] ?? 'none' ) .
                ' token=' . (string) ( $token_context['token'] ?? '' )
            );

            teinvit_email_queue_template(
                $template_id,
                teinvit_email_queue_args_with_template_delay(
                    $template_id,
                    [
                        'token'           => sanitize_text_field( (string) ( $token_context['token'] ?? '' ) ),
                        'order_id'        => $order_id,
                        'recipient_email' => $recipient,
                        'payload'         => [],
                        'trigger'         => 'product_purchased',
                        'audience'        => 'customer',
                    ]
                )
            );
        }
    },
    30,
    1
);

function teinvit_email_handle_short_tracking_routes() {
    $uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
    if ( $uri === '' ) {
        return;
    }

    $path = (string) wp_parse_url( $uri, PHP_URL_PATH );
    if ( preg_match( '#^/o/([a-f0-9\-]{36})/([a-f0-9]{64})/p\.gif$#i', $path, $m ) ) {
        $send_id = sanitize_text_field( $m[1] );
        $sig     = sanitize_text_field( $m[2] );

        if ( hash_equals( teinvit_email_sign( $send_id . '|open' ), $sig ) ) {
            teinvit_email_log_event( $send_id, 'open' );
        }

        nocache_headers();
        header( 'Content-Type: image/gif' );
        echo base64_decode( 'R0lGODlhAQABAPAAAAAAAAAAACH5BAEAAAAALAAAAAABAAEAAAICRAEAOw==' );
        exit;
    }

    if ( preg_match( '#^/c/([a-f0-9\-]{36})/([a-f0-9]{64})/?$#i', $path, $m ) ) {
        $send_id  = sanitize_text_field( $m[1] );
        $sig      = sanitize_text_field( $m[2] );
        $encoded  = isset( $_GET['u'] ) ? sanitize_text_field( (string) wp_unslash( $_GET['u'] ) ) : '';
        $dest_raw = $encoded !== '' ? teinvit_email_b64url_decode( $encoded ) : false;
        $dest     = $dest_raw ? esc_url_raw( $dest_raw ) : '';

        if ( $encoded !== '' && hash_equals( teinvit_email_sign( $send_id . '|' . $encoded ), $sig ) ) {
            teinvit_email_log_event( $send_id, 'click', $dest );
            if ( $dest !== '' ) {
                wp_safe_redirect( $dest );
                exit;
            }
        }

        wp_safe_redirect( home_url( '/' ) );
        exit;
    }

    if ( preg_match( '#^/u/([a-f0-9\-]{36})/([a-f0-9]{64})/?$#i', $path, $m ) ) {
        $send_id = sanitize_text_field( $m[1] );
        $sig     = sanitize_text_field( $m[2] );
        $email   = isset( $_GET['e'] ) ? sanitize_email( (string) wp_unslash( $_GET['e'] ) ) : '';

        if ( $email !== '' && hash_equals( teinvit_email_sign( $send_id . '|' . strtolower( $email ) ), $sig ) ) {
            teinvit_email_add_suppression( $email, 'marketing', 'unsubscribe_link', $send_id );
            wp_die( 'Te-ai dezabonat cu succes de la emailurile marketing TeInvit.' );
        }

        wp_die( 'Link de dezabonare invalid.' );
    }
}
add_action( 'template_redirect', 'teinvit_email_handle_short_tracking_routes', 1 );

function teinvit_email_read_blocks_from_post() {
    $types = isset( $_POST['block_type'] ) && is_array( $_POST['block_type'] ) ? $_POST['block_type'] : [];
    $data  = [];

    foreach ( $types as $idx => $type_raw ) {
        $type = sanitize_key( (string) $type_raw );
        if ( $type === '' ) {
            continue;
        }

        $block = [ 'type' => $type ];
        $block['enabled'] = ! empty( $_POST['block_enabled'][ $idx ] ) ? 1 : 0;
        $block['align']   = sanitize_key( (string) ( $_POST['block_align'][ $idx ] ?? 'center' ) );
        $block['url']     = sanitize_text_field( (string) ( $_POST['block_url'][ $idx ] ?? '' ) );
        $block['label']   = sanitize_text_field( (string) ( $_POST['block_label'][ $idx ] ?? '' ) );
        $block['style']   = sanitize_key( (string) ( $_POST['block_style'][ $idx ] ?? 'primary' ) );
        $block['text']    = sanitize_text_field( (string) ( $_POST['block_text'][ $idx ] ?? '' ) );
        $block['html']    = wp_kses_post( (string) ( $_POST['block_html'][ $idx ] ?? '' ) );

        $items_raw = isset( $_POST['block_items'][ $idx ] ) ? (string) $_POST['block_items'][ $idx ] : '';
        $items     = array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', $items_raw ) ) );
        $block['items'] = array_values( array_map( 'sanitize_text_field', $items ) );

        $data[] = $block;
    }

    if ( isset( $_POST['teinvit_block_add'] ) ) {
        $new_type = sanitize_key( (string) ( $_POST['teinvit_new_block_type'] ?? 'text' ) );
        $data[] = [ 'type' => $new_type, 'enabled' => 1, 'align' => 'center', 'style' => 'primary', 'text' => '', 'html' => '', 'url' => '', 'label' => '', 'items' => [] ];
    }

    if ( isset( $_POST['teinvit_block_up'] ) ) {
        $i = (int) $_POST['teinvit_block_up'];
        if ( isset( $data[ $i ] ) && $i > 0 ) {
            $tmp         = $data[ $i - 1 ];
            $data[ $i-1 ] = $data[ $i ];
            $data[ $i ]   = $tmp;
        }
    }

    if ( isset( $_POST['teinvit_block_down'] ) ) {
        $i = (int) $_POST['teinvit_block_down'];
        if ( isset( $data[ $i ], $data[ $i + 1 ] ) ) {
            $tmp          = $data[ $i + 1 ];
            $data[ $i+1 ] = $data[ $i ];
            $data[ $i ]   = $tmp;
        }
    }

    if ( isset( $_POST['teinvit_block_delete'] ) ) {
        $i = (int) $_POST['teinvit_block_delete'];
        if ( isset( $data[ $i ] ) ) {
            unset( $data[ $i ] );
            $data = array_values( $data );
        }
    }

    return $data;
}

add_action(
    'admin_menu',
    function() {
        $parent = function_exists( 'teinvit_admin_root_slug' ) ? teinvit_admin_root_slug() : 'woocommerce';
        $capability = function_exists( 'teinvit_admin_capability' ) ? teinvit_admin_capability() : 'manage_woocommerce';
        add_submenu_page( $parent, 'Custom Emails', 'Custom Emails', $capability, 'teinvit-custom-emails', 'teinvit_emails_page_router' );
        add_submenu_page( $parent, 'TeInvit Merge Tags', 'Merge Tags', $capability, 'teinvit-email-merge-tags', 'teinvit_emails_page_merge_tags' );
    },
    99
);

function teinvit_emails_admin_tab_url( $tab, array $extra = [] ) {
    $args = array_merge(
        [
            'page' => 'teinvit-custom-emails',
            'tab'  => sanitize_key( (string) $tab ),
        ],
        $extra
    );

    return admin_url( 'admin.php?' . http_build_query( $args ) );
}

function teinvit_emails_admin_tabs( $active_tab ) {
    $tabs = [
        'all'         => 'All Emails',
        'new'         => 'New Email',
        'logs'        => 'Logs',
        'suppression' => 'Unsubscribes/Suppression',
    ];

    echo '<h2 class="nav-tab-wrapper" style="margin-bottom:16px;">';
    foreach ( $tabs as $tab => $label ) {
        $class = $tab === $active_tab ? 'nav-tab nav-tab-active' : 'nav-tab';
        echo '<a class="' . esc_attr( $class ) . '" href="' . esc_url( teinvit_emails_admin_tab_url( $tab ) ) . '">' . esc_html( $label ) . '</a>';
    }
    echo '</h2>';
}

function teinvit_emails_page_router() {
    if ( ! current_user_can( 'manage_woocommerce' ) ) {
        return;
    }

    $tab = isset( $_GET['tab'] ) ? sanitize_key( (string) wp_unslash( $_GET['tab'] ) ) : 'all';
    if ( ! in_array( $tab, [ 'all', 'new', 'logs', 'suppression' ], true ) ) {
        $tab = 'all';
    }

    if ( $tab === 'new' ) {
        teinvit_emails_page_new();
        return;
    }

    if ( $tab === 'logs' ) {
        teinvit_emails_page_logs();
        return;
    }

    if ( $tab === 'suppression' ) {
        teinvit_emails_page_suppression();
        return;
    }

    teinvit_emails_page_all();
}

function teinvit_emails_page_all() {
    if ( ! current_user_can( 'manage_woocommerce' ) ) {
        return;
    }

    if ( isset( $_GET['teinvit_action'], $_GET['template_id'] ) && check_admin_referer( 'teinvit_emails_action' ) ) {
        $action = sanitize_key( (string) wp_unslash( $_GET['teinvit_action'] ) );
        $id     = sanitize_key( (string) wp_unslash( $_GET['template_id'] ) );

        if ( $action === 'disable' ) {
            teinvit_email_set_template_status( $id, 'draft' );
            echo '<div class="notice notice-success"><p>Template dezactivat.</p></div>';
        } elseif ( $action === 'enable' ) {
            teinvit_email_set_template_status( $id, 'active' );
            echo '<div class="notice notice-success"><p>Template activat.</p></div>';
        } elseif ( $action === 'duplicate' ) {
            $new_id = teinvit_email_duplicate_template( $id );
            if ( $new_id !== '' ) {
                echo '<div class="notice notice-success"><p>Template duplicat: ' . esc_html( $new_id ) . '</p></div>';
            }
        } elseif ( $action === 'delete' ) {
            if ( teinvit_email_delete_template( $id ) ) {
                echo '<div class="notice notice-success"><p>Template șters.</p></div>';
            } else {
                echo '<div class="notice notice-warning"><p>Template-ul nu poate fi șters (default sau inexistent).</p></div>';
            }
        }
    }

    $templates = teinvit_get_email_templates();

    echo '<div class="wrap"><h1>Custom Emails</h1>';
    teinvit_emails_admin_tabs( 'all' );
    echo '<table class="widefat striped"><thead><tr><th>Name</th><th>ID</th><th>Trigger</th><th>Audience</th><th>Products</th><th>Status</th><th>Delay</th><th>Actions</th></tr></thead><tbody>';
    foreach ( $templates as $tpl ) {
        $id = sanitize_key( (string) ( $tpl['id'] ?? '' ) );
        $edit_url = teinvit_emails_admin_tab_url( 'new', [ 'template_id' => $id ] );
        $dup_url = wp_nonce_url( teinvit_emails_admin_tab_url( 'all', [ 'teinvit_action' => 'duplicate', 'template_id' => $id ] ), 'teinvit_emails_action' );
        $toggle_action = ( ( $tpl['status'] ?? 'draft' ) === 'active' ) ? 'disable' : 'enable';
        $toggle_label  = $toggle_action === 'disable' ? 'Disable' : 'Enable';
        $toggle_url    = wp_nonce_url( teinvit_emails_admin_tab_url( 'all', [ 'teinvit_action' => $toggle_action, 'template_id' => $id ] ), 'teinvit_emails_action' );
        $delete_url    = wp_nonce_url( teinvit_emails_admin_tab_url( 'all', [ 'teinvit_action' => 'delete', 'template_id' => $id ] ), 'teinvit_emails_action' );
        $is_default    = teinvit_email_is_default_template( $id );

        echo '<tr>';
        echo '<td><a href="' . esc_url( $edit_url ) . '"><strong>' . esc_html( $tpl['name'] ?? '' ) . '</strong></a></td>';
        echo '<td>' . esc_html( $id ) . '</td>';
        echo '<td>' . esc_html( teinvit_email_trigger_label( $tpl['trigger'] ?? '' ) ) . '</td>';
        echo '<td>' . esc_html( $tpl['audience'] ?? '' ) . '</td>';
        $product_ids = teinvit_email_template_product_ids( is_array( $tpl ) ? $tpl : [] );
        echo '<td>' . esc_html( empty( $product_ids ) ? 'All' : implode( ',', array_map( 'strval', $product_ids ) ) ) . '</td>';
        echo '<td>' . esc_html( $tpl['status'] ?? '' ) . '</td>';
        echo '<td>' . esc_html( (string) ( $tpl['delay_value'] ?? 0 ) . ' ' . (string) ( $tpl['delay_unit'] ?? 'hours' ) ) . '</td>';
        echo '<td><a href="' . esc_url( $edit_url ) . '">Edit</a> | <a href="' . esc_url( $dup_url ) . '">Duplicate</a> | <a href="' . esc_url( $toggle_url ) . '">' . esc_html( $toggle_label ) . '</a>';
        if ( ! $is_default ) {
            echo ' | <a href="' . esc_url( $delete_url ) . '" onclick="return confirm(\'Sigur ștergi template-ul?\');">Delete</a>';
        }
        echo '</td>';
        echo '</tr>';
    }
    echo '</tbody></table></div>';
}

function teinvit_emails_page_new() {
    if ( ! current_user_can( 'manage_woocommerce' ) ) {
        return;
    }

    $templates = teinvit_get_email_templates();
    $mode = isset( $_GET['mode'] ) ? sanitize_key( (string) wp_unslash( $_GET['mode'] ) ) : '';
    $editing_id = isset( $_GET['template_id'] ) ? sanitize_key( (string) wp_unslash( $_GET['template_id'] ) ) : '';
    $is_create_mode = ( $mode === 'create' || $editing_id === '' || empty( $templates[ $editing_id ] ) );

    $template = $is_create_mode ? [
        'id'               => '',
        'name'             => '',
        'status'           => 'draft',
        'subject'          => '',
        'preheader'        => '',
        'trigger'          => 'token_generated',
        'audience'         => 'customer',
        'delay_value'      => 0,
        'delay_unit'       => 'hours',
        'email_type'       => 'html',
        'accent_color'     => '#B07A4F',
        'is_marketing'     => 0,
        'apply_rate_limit' => 0,
        'require_consent'  => 0,
        'rate_limit_count' => 2,
        'rate_limit_days'  => 7,
        'product_ids'      => [],
        'blocks'           => [ [ 'type' => 'title', 'text' => '' ] ],
    ] : $templates[ $editing_id ];

    $generated_id = '';
    $save_error   = '';

    if ( $_SERVER['REQUEST_METHOD'] === 'POST' && check_admin_referer( 'teinvit_email_new' ) ) {
        $posted_original_id = sanitize_key( (string) ( $_POST['original_template_id'] ?? '' ) );
        $posted_id = substr( sanitize_key( (string) ( $_POST['template_id'] ?? '' ) ), 0, 64 );

        $is_edit_mode = ( $posted_original_id !== '' && ! empty( $templates[ $posted_original_id ] ) );
        if ( $is_edit_mode ) {
            $id = $posted_original_id;
        } else {
            $id = $posted_id;
            if ( $id === '' ) {
                $generated_id = 'custom_' . gmdate( 'YmdHis' ) . '_' . wp_generate_password( 4, false, false );
                $generated_id = sanitize_key( $generated_id );
                $id = substr( $generated_id, 0, 64 );
            }

            if ( $id === '' ) {
                $save_error = 'Nu am putut genera un Template ID valid. Încearcă din nou.';
            } elseif ( ! empty( $templates[ $id ] ) ) {
                $save_error = 'Template ID deja existent. Folosește un alt ID sau apasă Duplicate.';
            }
        }

        $template = [
            'id'               => $id,
            'name'             => sanitize_text_field( (string) ( $_POST['name'] ?? '' ) ),
            'status'           => sanitize_key( (string) ( $_POST['status'] ?? 'draft' ) ),
            'subject'          => sanitize_text_field( (string) ( $_POST['subject'] ?? '' ) ),
            'preheader'        => sanitize_text_field( (string) ( $_POST['preheader'] ?? '' ) ),
            'trigger'          => sanitize_key( (string) ( $_POST['trigger'] ?? 'token_generated' ) ),
            'audience'         => sanitize_key( (string) ( $_POST['audience'] ?? 'customer' ) ),
            'delay_value'      => max( 0, (int) ( $_POST['delay_value'] ?? 0 ) ),
            'delay_unit'       => sanitize_key( (string) ( $_POST['delay_unit'] ?? 'hours' ) ),
            'email_type'       => 'html',
            'accent_color'     => sanitize_hex_color( (string) ( $_POST['accent_color'] ?? '#B07A4F' ) ) ?: '#B07A4F',
            'is_marketing'     => empty( $_POST['is_marketing'] ) ? 0 : 1,
            'apply_rate_limit' => empty( $_POST['apply_rate_limit'] ) ? 0 : 1,
            'require_consent'  => empty( $_POST['require_consent'] ) ? 0 : 1,
            'rate_limit_count' => max( 1, (int) ( $_POST['rate_limit_count'] ?? 2 ) ),
            'rate_limit_days'  => max( 1, (int) ( $_POST['rate_limit_days'] ?? 7 ) ),
            'product_ids'      => teinvit_email_parse_product_ids( (string) ( $_POST['product_ids'] ?? '' ) ),
            'blocks'           => teinvit_email_read_blocks_from_post(),
        ];

        if ( empty( $template['blocks'] ) || ! is_array( $template['blocks'] ) ) {
            $template['blocks'] = teinvit_email_default_blocks_for_template( $id );
        }

        if ( $save_error !== '' ) {
            echo '<div class="notice notice-error"><p>' . esc_html( $save_error ) . '</p></div>';
        } elseif ( isset( $_POST['teinvit_email_save'] ) ) {
            teinvit_update_email_template( $id, $template );
            echo '<div class="notice notice-success"><p>Email salvat.</p></div>';
            $editing_id = $id;
            $is_create_mode = false;
            $templates = teinvit_get_email_templates();
        } else {
            echo '<div class="notice notice-info"><p>Modificările builder sunt încă nesalvate. Apasă "Save Email" pentru persistare.</p></div>';
        }

        if ( $save_error === '' && isset( $_POST['teinvit_email_send_test'] ) ) {
            $recipient_test = sanitize_email( (string) get_option( 'admin_email' ) );
            if ( $recipient_test && is_email( $recipient_test ) ) {
                teinvit_update_email_template( $id, $template );
                $sample = teinvit_email_sample_context_args( $id, $recipient_test );
                teinvit_email_queue_template(
                    $id,
                    [
                        'token' => $sample['token'],
                        'order_id' => 0,
                        'rsvp_id' => 0,
                        'recipient_email' => $recipient_test,
                        'payload' => $sample['payload'],
                        'semantic_hash' => hash( 'sha256', $id . '|' . time() ),
                        'trigger' => $template['trigger'],
                        'audience' => $template['audience'],
                        'exact_template' => 1,
                        'is_preview_test' => 1,
                        'bypass_product_filter' => 1,
                    ]
                );
                echo '<div class="notice notice-success"><p>Test email pus în coadă către admin email. Context de test: product scope este bypassed.</p></div>';
            } else {
                echo '<div class="notice notice-error"><p>Nu există admin email valid pentru test.</p></div>';
            }
        }
    }

    echo '<div class="wrap"><h1>New Email (Block Builder)</h1>';
    teinvit_emails_admin_tabs( 'new' );
    $create_url = teinvit_emails_admin_tab_url( 'new', [ 'mode' => 'create' ] );
    echo '<p><a class="button button-primary" href="' . esc_url( $create_url ) . '">Create new template</a></p>';
    echo '<p>Template-uri existente: ';
    foreach ( teinvit_get_email_templates() as $tpl ) {
        $url = esc_url( teinvit_emails_admin_tab_url( 'new', [ 'template_id' => (string) $tpl['id'] ] ) );
        echo '<a href="' . $url . '" style="margin-right:10px;">' . esc_html( $tpl['name'] ) . '</a>';
    }
    echo '</p>';

    echo '<form method="post">';
    wp_nonce_field( 'teinvit_email_new' );
    echo '<input type="hidden" name="original_template_id" value="' . esc_attr( $is_create_mode ? '' : (string) ( $template['id'] ?? '' ) ) . '" />';
    echo '<table class="form-table">';
    if ( $is_create_mode ) {
        echo '<tr><th>Template ID</th><td><input name="template_id" class="regular-text" value="' . esc_attr( (string) ( $template['id'] ?? '' ) ) . '"/> <p class="description">Lasă gol pentru auto-ID. Ex: custom_20260101120000_ab12</p></td></tr>';
    } else {
        echo '<tr><th>Template ID</th><td><input name="template_id" class="regular-text" value="' . esc_attr( (string) ( $template['id'] ?? '' ) ) . '" readonly/> <p class="description">Template ID este imuabil după creare.</p></td></tr>';
    }
    echo '<tr><th>Name</th><td><input name="name" class="regular-text" required value="' . esc_attr( $template['name'] ) . '"/></td></tr>';
    echo '<tr><th>Status</th><td><select name="status"><option value="draft"' . selected( $template['status'], 'draft', false ) . '>Draft</option><option value="active"' . selected( $template['status'], 'active', false ) . '>Active</option></select></td></tr>';
    echo '<tr><th>Subject</th><td><input name="subject" class="regular-text" required value="' . esc_attr( $template['subject'] ) . '"/></td></tr>';
    echo '<tr><th>Preheader</th><td><input name="preheader" class="regular-text" value="' . esc_attr( $template['preheader'] ) . '"/></td></tr>';
    echo '<tr><th>Trigger</th><td><select name="trigger">';
    foreach ( teinvit_email_trigger_options() as $trigger_key => $trigger_label ) {
        echo '<option value="' . esc_attr( $trigger_key ) . '"' . selected( $template['trigger'], $trigger_key, false ) . '>' . esc_html( $trigger_label ) . '</option>';
    }
    echo '</select></td></tr>';
    echo '<tr><th>Audience</th><td><select name="audience"><option value="customer"' . selected( $template['audience'], 'customer', false ) . '>Customer</option><option value="guest"' . selected( $template['audience'], 'guest', false ) . '>Guest</option></select></td></tr>';
    echo '<tr><th>Delay</th><td><input type="number" name="delay_value" value="' . esc_attr( (string) $template['delay_value'] ) . '" min="0" /> <select name="delay_unit"><option value="minutes"' . selected( $template['delay_unit'], 'minutes', false ) . '>minutes</option><option value="hours"' . selected( $template['delay_unit'], 'hours', false ) . '>hours</option><option value="days"' . selected( $template['delay_unit'], 'days', false ) . '>days</option></select></td></tr>';
    echo '<tr><th>Accent color</th><td><input type="color" name="accent_color" value="' . esc_attr( $template['accent_color'] ) . '"/></td></tr>';
    echo '<tr><th>Marketing</th><td><label><input type="checkbox" name="is_marketing" value="1" ' . checked( ! empty( $template['is_marketing'] ), true, false ) . '/> Is marketing</label> <label style="margin-left:16px;"><input type="checkbox" name="require_consent" value="1" ' . checked( ! empty( $template['require_consent'] ), true, false ) . '/> Require consent</label></td></tr>';
    echo '<tr><th>Rate limit</th><td><label><input type="checkbox" name="apply_rate_limit" value="1" ' . checked( ! empty( $template['apply_rate_limit'] ), true, false ) . '/> Enable rate limit for this template</label></td></tr>';
    echo '<tr><th>Rate limit values</th><td><input type="number" min="1" name="rate_limit_count" value="' . esc_attr( (string) $template['rate_limit_count'] ) . '"/> emails / <input type="number" min="1" name="rate_limit_days" value="' . esc_attr( (string) $template['rate_limit_days'] ) . '"/> zile</td></tr>';
    echo '<tr><th>Se aplică doar pentru produsele (IDs)</th><td><input class="large-text" name="product_ids" value="' . esc_attr( implode( ',', teinvit_email_template_product_ids( is_array( $template ) ? $template : [] ) ) ) . '"/> <p class="description">Ex: 123,456,789. Gol = toate produsele.</p></td></tr>';
    echo '</table>';

    echo '<h2>Builder blocuri (Add / Move / Delete)</h2>';
    echo '<table class="widefat striped"><thead><tr><th>Type</th><th>Config</th><th>Actions</th></tr></thead><tbody>';
    $blocks = is_array( $template['blocks'] ?? null ) ? $template['blocks'] : [];
    foreach ( $blocks as $i => $block ) {
        $type = sanitize_key( (string) ( $block['type'] ?? 'text' ) );
        echo '<tr>';
        echo '<td><select name="block_type[' . (int) $i . ']">';
        $types = [ 'logo', 'banner', 'title', 'subtitle', 'text', 'bullets', 'divider', 'button', 'link', 'footer' ];
        foreach ( $types as $t ) {
            echo '<option value="' . esc_attr( $t ) . '"' . selected( $type, $t, false ) . '>' . esc_html( $t ) . '</option>';
        }
        echo '</select></td>';

        echo '<td>';
        echo '<p><label><input type="checkbox" name="block_enabled[' . (int) $i . ']" value="1" ' . checked( ! empty( $block['enabled'] ), true, false ) . '/> Enabled</label> | Align: <select name="block_align[' . (int) $i . ']"><option value="left"' . selected( $block['align'] ?? '', 'left', false ) . '>left</option><option value="center"' . selected( $block['align'] ?? 'center', 'center', false ) . '>center</option><option value="right"' . selected( $block['align'] ?? '', 'right', false ) . '>right</option></select></p>';
        echo '<p>Text: <input class="regular-text" name="block_text[' . (int) $i . ']" value="' . esc_attr( (string) ( $block['text'] ?? '' ) ) . '"/></p>';
        echo '<p>HTML: <textarea name="block_html[' . (int) $i . ']" rows="3" class="large-text">' . esc_textarea( (string) ( $block['html'] ?? '' ) ) . '</textarea></p>';
        echo '<p>Label: <input class="regular-text" name="block_label[' . (int) $i . ']" value="' . esc_attr( (string) ( $block['label'] ?? '' ) ) . '"/> URL: <input class="regular-text" name="block_url[' . (int) $i . ']" value="' . esc_attr( (string) ( $block['url'] ?? '' ) ) . '"/></p>';
        echo '<p>Style: <select name="block_style[' . (int) $i . ']"><option value="primary"' . selected( $block['style'] ?? 'primary', 'primary', false ) . '>primary</option><option value="secondary"' . selected( $block['style'] ?? '', 'secondary', false ) . '>secondary</option></select></p>';
        echo '<p>Bullets (un item pe linie):<br><textarea name="block_items[' . (int) $i . ']" rows="4" class="large-text">' . esc_textarea( implode( "\n", is_array( $block['items'] ?? null ) ? $block['items'] : [] ) ) . '</textarea></p>';
        echo '</td>';

        echo '<td><button class="button" name="teinvit_block_up" value="' . (int) $i . '">Move Up</button> <button class="button" name="teinvit_block_down" value="' . (int) $i . '">Move Down</button> <button class="button button-link-delete" name="teinvit_block_delete" value="' . (int) $i . '">Delete</button></td>';
        echo '</tr>';
    }
    echo '</tbody></table>';

    echo '<p><select name="teinvit_new_block_type"><option value="text">text</option><option value="logo">logo</option><option value="banner">banner</option><option value="title">title</option><option value="subtitle">subtitle</option><option value="bullets">bullets</option><option value="divider">divider</option><option value="button">button</option><option value="link">link</option><option value="footer">footer</option></select> <button type="submit" class="button" name="teinvit_block_add" value="1">Add Block</button></p>';

    echo '<p class="submit"><button type="submit" class="button button-primary" name="teinvit_email_save" value="1">Save Email</button> <button type="submit" class="button" name="teinvit_email_send_test" value="1">Send test email</button></p>';
    echo '</form></div>';
}


function teinvit_emails_page_merge_tags() {
    if ( ! current_user_can( 'manage_woocommerce' ) ) {
        return;
    }

    $catalog = teinvit_email_merge_tags_catalog();

    echo '<div class="wrap"><h1>TeInvit Merge Tags</h1>';
    echo '<p>Poți folosi atât formatul <code>{tag}</code>, cât și <code>[tag]</code>.</p>';
    echo '<p><input type="search" id="teinvit-tags-search" class="regular-text" placeholder="Caută tag sau descriere..." /></p>';
    $groups = [];
    foreach ( $catalog as $meta ) {
        $category = (string) ( $meta['category'] ?? 'Diverse' );
        if ( ! isset( $groups[ $category ] ) ) {
            $groups[ $category ] = [];
        }
        $groups[ $category ][] = $meta;
    }

    echo '<table class="widefat striped" id="teinvit-tags-table"><thead><tr><th>Tag</th><th>Descriere</th><th>Categorie</th><th>Context</th><th>Disponibilitate</th><th>Exemplu output</th><th>Copy</th></tr></thead><tbody>';

    foreach ( $groups as $category => $items ) {
        echo '<tr class="teinvit-tag-category-row"><td colspan="7"><strong>' . esc_html( $category ) . '</strong></td></tr>';
        foreach ( $items as $meta ) {
            $tag = (string) ( $meta['tag'] ?? '' );
            echo '<tr data-search="' . esc_attr( strtolower( $tag . ' ' . (string) ( $meta['description'] ?? '' ) . ' ' . (string) ( $meta['category'] ?? '' ) . ' ' . (string) ( $meta['context'] ?? '' ) . ' ' . (string) ( $meta['availability'] ?? '' ) ) ) . '">';
            echo '<td><code>' . esc_html( $tag ) . '</code></td>';
            echo '<td>' . esc_html( (string) ( $meta['description'] ?? '' ) ) . '</td>';
            echo '<td>' . esc_html( (string) ( $meta['category'] ?? '' ) ) . '</td>';
            echo '<td>' . esc_html( (string) ( $meta['context'] ?? '' ) ) . '</td>';
            echo '<td>' . esc_html( (string) ( $meta['availability'] ?? '' ) ) . '</td>';
            echo '<td>' . esc_html( (string) ( $meta['example'] ?? '' ) ) . '</td>';
            echo '<td><button type="button" class="button teinvit-copy-tag" data-tag="' . esc_attr( $tag ) . '">Copy</button></td>';
            echo '</tr>';
        }
    }

    echo '</tbody></table>';
    echo '<script>(function(){const s=document.getElementById("teinvit-tags-search");const rows=[...document.querySelectorAll("#teinvit-tags-table tbody tr[data-search]")];const catRows=[...document.querySelectorAll("#teinvit-tags-table tbody tr.teinvit-tag-category-row")];function syncCategories(){catRows.forEach(cat=>{let n=cat.nextElementSibling,visible=0;while(n&&!n.classList.contains("teinvit-tag-category-row")){if(n.style.display!=="none"){visible++;}n=n.nextElementSibling;}cat.style.display=visible>0?"":"none";});}if(s){s.addEventListener("input",function(){const q=(s.value||"").toLowerCase().trim();rows.forEach(r=>{const t=r.getAttribute("data-search")||"";r.style.display=(q===""||t.indexOf(q)!==-1)?"":"none";});syncCategories();});}syncCategories();document.querySelectorAll(".teinvit-copy-tag").forEach(btn=>btn.addEventListener("click",async()=>{const v=btn.getAttribute("data-tag")||"";try{await navigator.clipboard.writeText(v);btn.textContent="Copied";setTimeout(()=>btn.textContent="Copy",900);}catch(e){window.prompt("Copy tag",v);}}));})();</script>';
    echo '</div>';
}

function teinvit_emails_page_logs() {
    if ( ! current_user_can( 'manage_woocommerce' ) ) {
        return;
    }

    global $wpdb;
    $tables = teinvit_email_tables();
    $view = isset( $_GET['log_view'] ) ? sanitize_key( (string) wp_unslash( $_GET['log_view'] ) ) : 'operational';
    if ( ! in_array( $view, [ 'operational', 'skipped' ], true ) ) {
        $view = 'operational';
    }

    $statuses = $view === 'skipped'
        ? [ 'skipped' ]
        : [ 'queued', 'pending', 'processing', 'sent', 'failed' ];
    $placeholders = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );

    $rows = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT s.*,
        (SELECT COUNT(*) FROM {$tables['events']} e WHERE e.send_id=s.send_id AND e.event_type='open') AS opens_count,
        (SELECT COUNT(*) FROM {$tables['events']} e WHERE e.send_id=s.send_id AND e.event_type='click') AS clicks_count
        FROM {$tables['sends']} s
        WHERE s.status IN ($placeholders)
        ORDER BY s.id DESC
        LIMIT 300",
            $statuses
        ),
        ARRAY_A
    );

    echo '<div class="wrap"><h1>Custom Emails Logs</h1>';
    teinvit_emails_admin_tabs( 'logs' );
    $operational_url = teinvit_emails_admin_tab_url( 'logs', [ 'log_view' => 'operational' ] );
    $skipped_url = teinvit_emails_admin_tab_url( 'logs', [ 'log_view' => 'skipped' ] );
    echo '<p class="subsubsub">';
    echo '<a href="' . esc_url( $operational_url ) . '" class="' . ( $view === 'operational' ? 'current' : '' ) . '">Operational</a>';
    echo ' | ';
    echo '<a href="' . esc_url( $skipped_url ) . '" class="' . ( $view === 'skipped' ? 'current' : '' ) . '">Skipped / Debug</a>';
    echo '</p><br class="clear" />';
    echo '<table class="widefat striped"><thead><tr><th>Send ID</th><th>Template / Trigger</th><th>Order/Token</th><th>Recipient</th><th>Status</th><th>Reason</th><th>Opens</th><th>Clicks</th><th>Created</th><th>Sent</th></tr></thead><tbody>';
    foreach ( $rows as $row ) {
        $order_token = ! empty( $row['order_id'] ) ? ( 'Order #' . (int) $row['order_id'] ) : ( 'Token: ' . (string) ( $row['token'] ?? '-' ) );
        echo '<tr>';
        echo '<td>' . esc_html( $row['send_id'] ) . '</td>';
        echo '<td>' . esc_html( $row['template_id'] ) . ' <br><small>' . esc_html( teinvit_email_trigger_label( $row['trigger_key'] ?? '' ) ) . '</small></td>';
        echo '<td>' . esc_html( $order_token ) . '</td>';
        echo '<td>' . esc_html( $row['recipient_email'] ) . '</td>';
        echo '<td>' . esc_html( $row['status'] ) . '</td>';
        echo '<td>' . esc_html( (string) ( $row['error_message'] ?? '' ) ) . '</td>';
        echo '<td>' . esc_html( (string) $row['opens_count'] ) . '</td>';
        echo '<td>' . esc_html( (string) $row['clicks_count'] ) . '</td>';
        echo '<td>' . esc_html( $row['created_at'] ) . '</td>';
        echo '<td>' . esc_html( (string) $row['sent_at'] ) . '</td>';
        echo '</tr>';
    }
    if ( empty( $rows ) ) {
        echo '<tr><td colspan="10">Nu exista loguri pentru filtrul curent.</td></tr>';
    }
    echo '</tbody></table></div>';
}

function teinvit_emails_page_suppression() {
    if ( ! current_user_can( 'manage_woocommerce' ) ) {
        return;
    }

    global $wpdb;
    $tables = teinvit_email_tables();

    if ( isset( $_POST['teinvit_unsuppress_email'] ) && check_admin_referer( 'teinvit_unsuppress' ) ) {
        $email = sanitize_email( (string) ( $_POST['email'] ?? '' ) );
        if ( $email !== '' ) {
            teinvit_email_remove_suppression( $email, 'marketing', 'admin_revoke' );
        }
    }

    $rows = $wpdb->get_results( "SELECT * FROM {$tables['suppression']} ORDER BY id DESC LIMIT 300", ARRAY_A );

    echo '<div class="wrap"><h1>Unsubscribes / Suppression</h1>';
    teinvit_emails_admin_tabs( 'suppression' );
    echo '<table class="widefat striped"><thead><tr><th>Email</th><th>Scope</th><th>Reason</th><th>Created</th><th>Action</th></tr></thead><tbody>';
    foreach ( $rows as $row ) {
        echo '<tr>';
        echo '<td>' . esc_html( $row['email'] ) . '</td>';
        echo '<td>' . esc_html( $row['scope'] ) . '</td>';
        echo '<td>' . esc_html( $row['reason'] ) . '</td>';
        echo '<td>' . esc_html( $row['created_at'] ) . '</td>';
        echo '<td><form method="post">';
        wp_nonce_field( 'teinvit_unsuppress' );
        echo '<input type="hidden" name="email" value="' . esc_attr( $row['email'] ) . '"/>';
        echo '<button type="submit" name="teinvit_unsuppress_email" class="button">Revoke</button>';
        echo '</form></td>';
        echo '</tr>';
    }
    echo '</tbody></table></div>';
}


add_action(
    'admin_init',
    function() {
        if ( ! is_admin() ) {
            return;
        }

        $page = isset( $_GET['page'] ) ? sanitize_key( (string) wp_unslash( $_GET['page'] ) ) : '';
        $tab  = isset( $_GET['tab'] ) ? sanitize_key( (string) wp_unslash( $_GET['tab'] ) ) : '';
        if ( $page !== 'wc-settings' || ! in_array( $tab, [ 'email', 'emails' ], true ) ) {
            return;
        }

        static $logged = false;
        if ( $logged ) {
            return;
        }

        if ( ! function_exists( 'WC' ) || ! WC() ) {
            return;
        }

        $mailer = WC()->mailer();
        if ( ! $mailer ) {
            return;
        }

        $emails = $mailer->get_emails();
        $keys   = is_array( $emails ) ? array_keys( $emails ) : [];
        error_log( '[TeInvit Emails][WC mailer keys] ' . implode( ',', array_map( 'strval', $keys ) ) );
        $logged = true;
    },
    20
);

add_action(
    'init',
    function() {
        teinvit_email_maybe_upgrade_schema();

        if ( ! wp_next_scheduled( 'teinvit_email_cleanup_retention' ) ) {
            wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'teinvit_email_cleanup_retention' );
        }
    },
    20
);

add_action(
    'teinvit_email_cleanup_retention',
    function() {
        global $wpdb;
        $tables = teinvit_email_tables();

        $events_before = gmdate( 'Y-m-d H:i:s', time() - 365 * DAY_IN_SECONDS );
        $sends_before  = gmdate( 'Y-m-d H:i:s', time() - 730 * DAY_IN_SECONDS );

        $wpdb->query( $wpdb->prepare( "DELETE FROM {$tables['events']} WHERE event_at < %s", $events_before ) );
        $wpdb->query( $wpdb->prepare( "DELETE FROM {$tables['sends']} WHERE created_at < %s", $sends_before ) );
    }
);

add_filter(
    'woocommerce_email_classes',
    function( $emails ) {
        if ( ! class_exists( 'WC_Email' ) ) {
            return $emails;
        }

        if ( ! class_exists( 'TeInvit_WC_Email_Base' ) ) {
            class TeInvit_WC_Email_Base extends WC_Email {
                protected $teinvit_template_id = '';

                public function __construct( $id, $title, $description, $template_id ) {
                    $this->id             = $id;
                    $this->title          = $title;
                    $this->description    = $description;
                    $this->teinvit_template_id = sanitize_key( (string) $template_id );
                    $this->customer_email = true;
                    $this->email_type     = 'html';
                    $this->enabled        = 'yes';
                    parent::__construct();
                }

                protected function get_teinvit_template_id() {
                    $current_email_id = sanitize_key( (string) $this->id );
                    $is_custom_email  = strpos( $current_email_id, 'teinvit_email_tpl_' ) === 0;

                    if ( $is_custom_email && $this->teinvit_template_id !== '' ) {
                        return $this->teinvit_template_id;
                    }

                    $section_email_id = teinvit_email_current_wc_settings_section_id();
                    if ( $section_email_id !== '' ) {
                        $section_template_id = teinvit_email_template_id_for_wc_email_id( $section_email_id );
                        if ( $section_template_id !== '' ) {
                            return (string) $section_template_id;
                        }
                    }

                    if ( $this->teinvit_template_id !== '' ) {
                        return $this->teinvit_template_id;
                    }

                    $fallback = teinvit_email_template_id_for_wc_email_id( $current_email_id );
                    return $fallback ? (string) $fallback : '';
                }

                protected function teinvit_debug_identity() {
                    $hash = function_exists( 'spl_object_hash' ) ? spl_object_hash( $this ) : '';
                    return 'class=' . get_class( $this ) . ' email_class=' . (string) $this->id . ' title=' . (string) $this->title . ' template_id=' . (string) $this->teinvit_template_id . ' obj=' . $hash;
                }

                public function init_form_fields() {
                    $this->form_fields = [
                        'enabled' => [
                            'title'   => 'Enable/Disable',
                            'type'    => 'checkbox',
                            'label'   => 'Enable this email notification',
                            'default' => 'yes',
                        ],
                    ];
                }

                protected function teinvit_is_wc_settings_request() {
                    $page = isset( $_REQUEST['page'] ) ? sanitize_key( (string) wp_unslash( $_REQUEST['page'] ) ) : '';
                    $tab  = isset( $_REQUEST['tab'] ) ? sanitize_key( (string) wp_unslash( $_REQUEST['tab'] ) ) : '';
                    return $page === 'wc-settings' && in_array( $tab, [ 'email', 'emails' ], true );
                }

                protected function teinvit_debug_content_path( $path ) {
                    $is_plain = $this->get_email_type() === 'plain';
                    error_log( '[TeInvit Emails][WC content path] ' . $this->teinvit_debug_identity() . ' path=' . sanitize_key( (string) $path ) . ' email_type=' . (string) $this->get_email_type() . ' is_plain=' . ( $is_plain ? '1' : '0' ) . ' ' . teinvit_email_debug_request_context() );
                }

                public function get_content_type( $default_content_type = '' ) {
                    if ( is_string( $default_content_type ) && $default_content_type !== '' ) {
                        return 'text/html; charset=UTF-8';
                    }

                    return 'text/html; charset=UTF-8';
                }

                public function get_email_type() {
                    return 'html';
                }

                public function get_content() {
                    $this->teinvit_debug_content_path( 'get_content' );
                    return $this->get_content_html();
                }

                public function trigger( $send_id = '' ) {
                    if ( 'yes' !== $this->enabled ) {
                        return false;
                    }

                    $send = teinvit_email_get_send( $send_id );
                    if ( ! $send ) {
                        $template_id = $this->get_teinvit_template_id();
                        if ( $template_id === '' ) {
                            return false;
                        }

                        $template = teinvit_get_email_template( $template_id );
                        if ( ! $template ) {
                            return false;
                        }

                        $sample    = teinvit_email_sample_context_args( $template_id, sanitize_email( get_option( 'admin_email' ) ) );
                        $render    = teinvit_email_render_template( $template, $sample );
                        $recipient = sanitize_email( (string) $sample['recipient_email'] );
                        error_log( '[TeInvit Emails][WC test trigger] ' . $this->teinvit_debug_identity() . ' wc_id=' . (string) $this->id . ' section=' . teinvit_email_current_wc_settings_section_id() . ' template_id=' . (string) $template_id . ' ' . teinvit_email_debug_request_context() . ' ' . teinvit_email_debug_mailer_registry() . ' subject=' . substr( (string) ( $render['subject'] ?? '' ), 0, 80 ) );
                        if ( $recipient === '' || ! is_email( $recipient ) ) {
                            return false;
                        }

                        $result = (bool) $this->send( $recipient, (string) $render['subject'], (string) $render['body_html'], $this->get_headers(), $this->get_attachments() );
                        error_log( '[TeInvit Emails][WC test trigger] send_result=' . ( $result ? '1' : '0' ) . ' recipient=' . $recipient . ' wc_id=' . (string) $this->id );
                        return $result;
                    }

                    $recipient = sanitize_email( (string) ( $send['recipient_email'] ?? '' ) );
                    if ( $recipient === '' || ! is_email( $recipient ) ) {
                        return false;
                    }

                    $subject = (string) ( $send['subject_rendered'] ?? $this->get_default_subject() );
                    $content = (string) ( $send['body_rendered'] ?? '' );
                    if ( $content === '' ) {
                        $template_id = $this->get_teinvit_template_id();
                        $template    = $template_id ? teinvit_get_email_template( $template_id ) : null;
                        if ( $template ) {
                            $sample  = teinvit_email_sample_context_args( $template_id, $recipient );
                            $render  = teinvit_email_render_template( $template, $sample );
                            $content = (string) ( $render['body_html'] ?? '' );
                            $subject = (string) ( $render['subject'] ?? $subject );
                        }
                    }

                    if ( $content === '' ) {
                        return false;
                    }

                    $result = (bool) $this->send( $recipient, $subject, $content, $this->get_headers(), $this->get_attachments() );
                    error_log( '[TeInvit Emails][WC send] send_id=' . sanitize_text_field( (string) $send_id ) . ' result=' . ( $result ? '1' : '0' ) . ' recipient=' . $recipient . ' wc_id=' . (string) $this->id );
                    return $result;
                }

                public function get_content_html() {
                    $template_id = $this->get_teinvit_template_id();
                    $template    = $template_id ? teinvit_get_email_template( $template_id ) : null;
                    if ( ! $template ) {
                        return '<p>TeInvit email preview indisponibil.</p>';
                    }

                    $sample = teinvit_email_sample_context_args( $template_id, sanitize_email( get_option( 'admin_email' ) ) );
                    $render = teinvit_email_render_template( $template, $sample );
                    $this->teinvit_debug_content_path( 'get_content_html' );
                    error_log( '[TeInvit Emails][WC preview html] ' . $this->teinvit_debug_identity() . ' wc_id=' . (string) $this->id . ' section=' . teinvit_email_current_wc_settings_section_id() . ' template_id=' . (string) $template_id . ' ' . teinvit_email_debug_request_context() . ' ' . teinvit_email_debug_mailer_registry() . ' subject=' . substr( (string) ( $render['subject'] ?? '' ), 0, 80 ) );

                    return (string) ( $render['body_html'] ?? '' );
                }

                public function get_content_plain() {
                    $template_id = $this->get_teinvit_template_id();
                    $template    = $template_id ? teinvit_get_email_template( $template_id ) : null;
                    if ( ! $template ) {
                        return 'TeInvit email preview indisponibil.';
                    }

                    $sample = teinvit_email_sample_context_args( $template_id, sanitize_email( get_option( 'admin_email' ) ) );
                    $render = teinvit_email_render_template( $template, $sample );
                    $this->teinvit_debug_content_path( 'get_content_plain' );
                    error_log( '[TeInvit Emails][WC preview plain] ' . $this->teinvit_debug_identity() . ' wc_id=' . (string) $this->id . ' section=' . teinvit_email_current_wc_settings_section_id() . ' template_id=' . (string) $template_id . ' ' . teinvit_email_debug_request_context() . ' ' . teinvit_email_debug_mailer_registry() . ' subject=' . substr( (string) ( $render['subject'] ?? '' ), 0, 80 ) );

                    if ( $this->teinvit_is_wc_settings_request() ) {
                        return (string) ( $render['body_html'] ?? '' );
                    }

                    return (string) ( $render['body_text'] ?? '' );
                }

                public function get_default_subject() {
                    return $this->title;
                }
            }
        }

        if ( ! class_exists( 'TeInvit_WC_Email_Token_Generated' ) ) {
            class TeInvit_WC_Email_Token_Generated extends TeInvit_WC_Email_Base {
                public function __construct() {
                    parent::__construct(
                        'teinvit_email_token_generated',
                        'TeInvit: Token generated → Customer',
                        'Email trimis clientului când tokenul este generat.',
                        'token_generated_customer'
                    );
                }
            }
        }

        if ( ! class_exists( 'TeInvit_WC_Email_RSVP_Received' ) ) {
            class TeInvit_WC_Email_RSVP_Received extends TeInvit_WC_Email_Base {
                public function __construct() {
                    parent::__construct(
                        'teinvit_email_rsvp_received',
                        'TeInvit: RSVP received → Customer',
                        'Email trimis clientului după un RSVP nou.',
                        'rsvp_received_customer'
                    );
                }
            }
        }

        if ( ! class_exists( 'TeInvit_WC_Email_Guest_Marketing_1' ) ) {
            class TeInvit_WC_Email_Guest_Marketing_1 extends TeInvit_WC_Email_Base {
                public function __construct() {
                    parent::__construct(
                        'teinvit_email_guest_marketing_1',
                        'TeInvit: Guest consent #1 (24h)',
                        'Email marketing trimis invitaților care au consent valid.',
                        'guest_marketing_consent_1'
                    );
                }
            }
        }

        $emails['teinvit_email_token_generated'] = new TeInvit_WC_Email_Token_Generated();
        $emails['teinvit_email_rsvp_received'] = new TeInvit_WC_Email_RSVP_Received();
        $emails['teinvit_email_guest_marketing_1'] = new TeInvit_WC_Email_Guest_Marketing_1();

        foreach ( teinvit_get_email_templates() as $tpl ) {
            if ( ! is_array( $tpl ) ) {
                continue;
            }

            $template_id = sanitize_key( (string) ( $tpl['id'] ?? '' ) );
            if ( $template_id === '' ) {
                continue;
            }

            if ( ( $tpl['status'] ?? 'draft' ) !== 'active' ) {
                continue;
            }

            if ( in_array( $template_id, [ 'token_generated_customer', 'rsvp_received_customer', 'guest_marketing_consent_1' ], true ) ) {
                continue;
            }

            $wc_id = teinvit_email_wc_id_for_template_id( $template_id );
            if ( $wc_id === '' || isset( $emails[ $wc_id ] ) ) {
                continue;
            }

            $title = '[TeInvit] ' . (string) ( $tpl['name'] ?? $template_id );
            $description = 'Template dinamic TeInvit (' . (string) ( $tpl['trigger'] ?? '' ) . ' / ' . (string) ( $tpl['audience'] ?? '' ) . ').';
            $class_suffix = strtoupper( substr( md5( $template_id ), 0, 12 ) );
            $class_name   = 'TeInvit_WC_Email_Tpl_' . $class_suffix;

            if ( ! class_exists( $class_name ) ) {
                eval(
                    'class ' . $class_name . ' extends TeInvit_WC_Email_Base {' .
                    'public function __construct($id, $title, $description, $template_id) {' .
                    'parent::__construct($id, $title, $description, $template_id);' .
                    '}' .
                    '}'
                );
            }

            $emails[ $wc_id ] = new $class_name(
                $wc_id,
                $title,
                $description,
                $template_id
            );
        }

        return $emails;
    }
);
