<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'TEINVIT_EMAIL_SCHEMA_VERSION', 1 );
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

    update_option( TEINVIT_EMAIL_SCHEMA_OPTION, TEINVIT_EMAIL_SCHEMA_VERSION, false );

    if ( ! get_option( TEINVIT_EMAIL_TEMPLATES_OPTION ) ) {
        update_option( TEINVIT_EMAIL_TEMPLATES_OPTION, teinvit_email_default_templates(), false );
    }
}

function teinvit_email_default_templates() {
    return [
        'token_generated_customer' => [
            'id'            => 'token_generated_customer',
            'name'          => 'Token generated → Customer',
            'status'        => 'active',
            'subject'       => 'Invitația ta digitală este gata #{order_number}',
            'preheader'     => 'Administrează invitația în câteva clickuri.',
            'heading'       => 'Administrare invitație',
            'trigger'       => 'token_generated',
            'audience'      => 'customer',
            'delay_value'   => 0,
            'delay_unit'    => 'hours',
            'content_html'  => '<p>Salut, {customer_first_name}!</p><p>Invitația ta este gata. Poți edita, publica, gestiona RSVP, cadouri și raport invitați.</p><p><a href="{admin_client_url}">Administrare invitație</a></p><p><a href="{invitati_url}">Pagina invitaților</a></p>',
            'button_label'  => 'Administrare invitație',
            'button_url'    => '{admin_client_url}',
            'is_marketing'  => 0,
            'require_consent' => 0,
            'rate_limit_count' => 2,
            'rate_limit_days'  => 7,
        ],
        'rsvp_received_customer' => [
            'id'            => 'rsvp_received_customer',
            'name'          => 'RSVP received → Customer',
            'status'        => 'active',
            'subject'       => 'Confirmare nouă primită pentru invitația ta',
            'preheader'     => 'Vezi detaliile RSVP.',
            'heading'       => 'Confirmare nouă primită',
            'trigger'       => 'rsvp_saved',
            'audience'      => 'customer',
            'delay_value'   => 0,
            'delay_unit'    => 'hours',
            'content_html'  => '<p>Ai primit o nouă confirmare RSVP:</p><ul><li>Nume: {guest_name}</li><li>Telefon: {guest_phone}</li><li>Email: {guest_email}</li><li>Adulți: {rsvp_adults}</li><li>Copii: {rsvp_children}</li><li>Civil: {rsvp_attending_civil}</li><li>Religios: {rsvp_attending_religious}</li><li>Petrecere: {rsvp_attending_party}</li><li>Cazare: {rsvp_accommodation}</li><li>Vegetarian: {rsvp_vegetarian} ({rsvp_vegetarian_menus})</li><li>Alergii: {rsvp_allergies}</li><li>Mesaj: {rsvp_message}</li></ul><p><a href="{report_url}">Vezi raport invitați</a></p>',
            'button_label'  => 'Vezi raport invitați',
            'button_url'    => '{report_url}',
            'is_marketing'  => 0,
            'require_consent' => 0,
            'rate_limit_count' => 2,
            'rate_limit_days'  => 7,
        ],
        'guest_marketing_consent_1' => [
            'id'            => 'guest_marketing_consent_1',
            'name'          => 'Guest consent #1 (24h)',
            'status'        => 'active',
            'subject'       => 'Inspirație pentru invitații digitale',
            'preheader'     => 'Descoperă modele și idei noi.',
            'heading'       => 'Invitații digitale TeInvit',
            'trigger'       => 'guest_consent_1',
            'audience'      => 'guest',
            'delay_value'   => 24,
            'delay_unit'    => 'hours',
            'content_html'  => '<p>Mulțumim pentru confirmare! Vezi cum funcționează invitațiile digitale și inspiră-te din modele.</p><p><a href="https://www.teinvit.com/magazin/">Vezi modele</a></p><p>{why_received_text}</p><p><a href="{unsubscribe_url}">Dezabonare</a></p>',
            'button_label'  => 'Vezi modele',
            'button_url'    => 'https://www.teinvit.com/magazin/',
            'is_marketing'  => 1,
            'require_consent' => 1,
            'rate_limit_count' => 2,
            'rate_limit_days'  => 7,
        ],
    ];
}

function teinvit_get_email_templates() {
    $templates = get_option( TEINVIT_EMAIL_TEMPLATES_OPTION, [] );
    if ( ! is_array( $templates ) || empty( $templates ) ) {
        $templates = teinvit_email_default_templates();
    }

    return $templates;
}

function teinvit_get_email_template( $template_id ) {
    $templates = teinvit_get_email_templates();
    return $templates[ $template_id ] ?? null;
}

function teinvit_update_email_template( $template_id, array $data ) {
    $templates                = teinvit_get_email_templates();
    $templates[ $template_id ] = $data;
    update_option( TEINVIT_EMAIL_TEMPLATES_OPTION, $templates, false );
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

function teinvit_email_normalize_phone( $phone ) {
    $phone = trim( (string) $phone );
    $phone = preg_replace( '/\s+/', '', $phone );
    return (string) $phone;
}

function teinvit_email_payload_semantic_hash( array $payload ) {
    $keys = [
        'guest_first_name', 'guest_last_name', 'guest_phone', 'guest_email', 'attending_people_count',
        'attending_civil', 'attending_religious', 'attending_party', 'bringing_kids', 'kids_count',
        'needs_accommodation', 'accommodation_people_count', 'vegetarian_requested', 'vegetarian_menus_count',
        'has_allergies', 'allergy_details', 'message_to_couple',
    ];
    $data = [];
    foreach ( $keys as $key ) {
        $data[ $key ] = isset( $payload[ $key ] ) ? (string) $payload[ $key ] : '';
    }
    return hash( 'sha256', wp_json_encode( $data ) );
}

function teinvit_email_is_suppressed( $email, $scope = 'marketing' ) {
    global $wpdb;
    $email = sanitize_email( $email );
    if ( $email === '' ) {
        return true;
    }

    $tables = teinvit_email_tables();
    $found  = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$tables['suppression']} WHERE email=%s AND scope=%s LIMIT 1", $email, $scope ) );

    return $found > 0;
}

function teinvit_email_add_suppression( $email, $scope = 'marketing', $reason = 'unsubscribe_link', $source_send_id = null ) {
    global $wpdb;
    $email = sanitize_email( $email );
    if ( $email === '' ) {
        return;
    }

    $tables = teinvit_email_tables();
    $wpdb->replace( $tables['suppression'], [
        'email'          => $email,
        'email_hash'     => hash( 'sha256', strtolower( $email ) ),
        'scope'          => sanitize_key( $scope ),
        'reason'         => sanitize_key( $reason ),
        'source_send_id' => $source_send_id ? sanitize_text_field( $source_send_id ) : null,
        'created_at'     => current_time( 'mysql' ),
    ] );
}

function teinvit_email_build_context( array $args ) {
    $token    = (string) ( $args['token'] ?? '' );
    $order_id = (int) ( $args['order_id'] ?? 0 );
    $order    = $order_id ? wc_get_order( $order_id ) : null;

    $first_name = '';
    $last_name  = '';
    $order_no   = '';
    if ( $order ) {
        $first_name = (string) $order->get_billing_first_name();
        $last_name  = (string) $order->get_billing_last_name();
        $order_no   = (string) $order->get_order_number();
    }

    $guest_name = trim( (string) ( ( $args['payload']['guest_first_name'] ?? '' ) . ' ' . ( $args['payload']['guest_last_name'] ?? '' ) ) );

    $unsubscribe_email = sanitize_email( (string) ( $args['recipient_email'] ?? '' ) );
    $send_id           = (string) ( $args['send_id'] ?? '' );
    $unsubscribe_url   = '';
    if ( $unsubscribe_email !== '' && $send_id !== '' ) {
        $u_payload      = $send_id . '|' . strtolower( $unsubscribe_email );
        $unsubscribe_url = add_query_arg( [
            'teinvit_unsub' => rawurlencode( $send_id ),
            'email'         => rawurlencode( $unsubscribe_email ),
            'sig'           => teinvit_email_sign( $u_payload ),
        ], home_url( '/' ) );
    }

    return [
        '{site_name}'               => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
        '{order_number}'            => $order_no,
        '{order_id}'                => (string) $order_id,
        '{token}'                   => $token,
        '{admin_client_url}'        => home_url( '/admin-client/' . rawurlencode( $token ) ),
        '{invitati_url}'            => home_url( '/invitati/' . rawurlencode( $token ) ),
        '{report_url}'              => home_url( '/admin-client/' . rawurlencode( $token ) . '#teinvit-report' ),
        '{customer_first_name}'     => $first_name,
        '{customer_last_name}'      => $last_name,
        '{guest_name}'              => $guest_name,
        '{guest_phone}'             => (string) ( $args['payload']['guest_phone'] ?? '' ),
        '{guest_email}'             => (string) ( $args['payload']['guest_email'] ?? '' ),
        '{rsvp_adults}'             => (string) ( $args['payload']['attending_people_count'] ?? '' ),
        '{rsvp_children}'           => (string) ( $args['payload']['kids_count'] ?? '' ),
        '{rsvp_attending_civil}'    => ! empty( $args['payload']['attending_civil'] ) ? 'Da' : 'Nu',
        '{rsvp_attending_religious}'=> ! empty( $args['payload']['attending_religious'] ) ? 'Da' : 'Nu',
        '{rsvp_attending_party}'    => ! empty( $args['payload']['attending_party'] ) ? 'Da' : 'Nu',
        '{rsvp_accommodation}'      => ! empty( $args['payload']['needs_accommodation'] ) ? 'Da' : 'Nu',
        '{rsvp_vegetarian}'         => ! empty( $args['payload']['vegetarian_requested'] ) ? 'Da' : 'Nu',
        '{rsvp_vegetarian_menus}'   => (string) ( $args['payload']['vegetarian_menus_count'] ?? '' ),
        '{rsvp_allergies}'          => (string) ( $args['payload']['allergy_details'] ?? '' ),
        '{rsvp_message}'            => (string) ( $args['payload']['message_to_couple'] ?? '' ),
        '{unsubscribe_url}'         => $unsubscribe_url,
        '{why_received_text}'       => 'Primești acest email deoarece ai bifat acordul de marketing în formularul RSVP.',
    ];
}

function teinvit_email_render_template( array $template, array $args ) {
    $context = teinvit_email_build_context( $args );

    $subject = strtr( (string) ( $template['subject'] ?? '' ), $context );
    $heading = strtr( (string) ( $template['heading'] ?? '' ), $context );
    $preheader = strtr( (string) ( $template['preheader'] ?? '' ), $context );
    $body    = strtr( (string) ( $template['content_html'] ?? '' ), $context );

    $button_url   = strtr( (string) ( $template['button_url'] ?? '' ), $context );
    $button_label = strtr( (string) ( $template['button_label'] ?? '' ), $context );
    if ( $button_url !== '' && $button_label !== '' ) {
        $body .= '<p><a href="' . esc_url( $button_url ) . '">' . esc_html( $button_label ) . '</a></p>';
    }

    return [
        'subject'   => $subject,
        'heading'   => $heading,
        'preheader' => $preheader,
        'body_html' => wpautop( $body ),
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

    $salt = gmdate( 'Y-m' );
    return hash( 'sha256', $salt . '|' . $ip );
}

function teinvit_email_log_event( $send_id, $event_type, $url = null ) {
    global $wpdb;
    $tables = teinvit_email_tables();

    $wpdb->insert( $tables['events'], [
        'send_id'    => sanitize_text_field( $send_id ),
        'event_type' => sanitize_key( $event_type ),
        'event_at'   => current_time( 'mysql' ),
        'ip_hash'    => teinvit_email_hash_ip(),
        'ua_hash'    => teinvit_email_hash_ua(),
        'url'        => $url ? esc_url_raw( $url ) : null,
        'meta_json'  => null,
    ] );
}

function teinvit_email_send_rate_limited( array $template, $recipient_email ) {
    global $wpdb;
    $tables = teinvit_email_tables();

    $count_limit = max( 1, (int) ( $template['rate_limit_count'] ?? 2 ) );
    $days_limit  = max( 1, (int) ( $template['rate_limit_days'] ?? 7 ) );
    $from_time   = gmdate( 'Y-m-d H:i:s', time() - $days_limit * DAY_IN_SECONDS );

    $count = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM {$tables['sends']} WHERE template_id=%s AND recipient_email=%s AND status='sent' AND sent_at >= %s",
        $template['id'],
        $recipient_email,
        $from_time
    ) );

    return $count >= $count_limit;
}

function teinvit_email_has_recent_duplicate( array $template, $recipient_email, $semantic_hash ) {
    global $wpdb;
    $tables = teinvit_email_tables();
    $from_time = gmdate( 'Y-m-d H:i:s', time() - 10 * MINUTE_IN_SECONDS );

    $count = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM {$tables['sends']} WHERE template_id=%s AND recipient_email=%s AND semantic_hash=%s AND created_at >= %s",
        $template['id'],
        $recipient_email,
        $semantic_hash,
        $from_time
    ) );

    return $count > 0;
}

function teinvit_email_save_send( array $template, array $rendered, array $args, $status = 'queued' ) {
    global $wpdb;
    $tables = teinvit_email_tables();

    $send_id = teinvit_email_uuid_v4();
    $now     = current_time( 'mysql' );

    $recipient = sanitize_email( (string) ( $args['recipient_email'] ?? '' ) );
    $semantic_hash = (string) ( $args['semantic_hash'] ?? '' );

    $wpdb->insert( $tables['sends'], [
        'send_id'            => $send_id,
        'template_key'       => sanitize_key( $template['trigger'] . '_' . $template['audience'] ),
        'template_id'        => sanitize_text_field( $template['id'] ),
        'trigger_key'        => sanitize_key( $template['trigger'] ),
        'audience_type'      => sanitize_key( $template['audience'] ),
        'token'              => sanitize_text_field( (string) ( $args['token'] ?? '' ) ),
        'order_id'           => (int) ( $args['order_id'] ?? 0 ),
        'rsvp_id'            => (int) ( $args['rsvp_id'] ?? 0 ),
        'recipient_email'    => $recipient,
        'recipient_hash'     => hash( 'sha256', strtolower( $recipient ) ),
        'subject_rendered'   => wp_strip_all_tags( (string) $rendered['subject'] ),
        'heading_rendered'   => wp_strip_all_tags( (string) $rendered['heading'] ),
        'preheader_rendered' => wp_strip_all_tags( (string) $rendered['preheader'] ),
        'body_rendered'      => (string) $rendered['body_html'],
        'body_rendered_hash' => hash( 'sha256', (string) $rendered['body_html'] ),
        'semantic_hash'      => $semantic_hash,
        'status'             => sanitize_key( $status ),
        'scheduled_at'       => ! empty( $args['scheduled_at'] ) ? gmdate( 'Y-m-d H:i:s', (int) $args['scheduled_at'] ) : null,
        'created_at'         => $now,
        'updated_at'         => $now,
    ] );

    return $send_id;
}

function teinvit_email_get_send( $send_id ) {
    global $wpdb;
    $tables = teinvit_email_tables();
    return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$tables['sends']} WHERE send_id=%s", $send_id ), ARRAY_A );
}

function teinvit_email_attach_tracking( $send_id, $html ) {
    $open_payload = $send_id . '|open';
    $open_url     = add_query_arg( [
        'teinvit_open' => rawurlencode( $send_id ),
        'sig'          => teinvit_email_sign( $open_payload ),
    ], home_url( '/' ) );

    $html = preg_replace_callback( '/href\s*=\s*"([^"]+)"/i', function( $m ) use ( $send_id ) {
        $url = html_entity_decode( $m[1], ENT_QUOTES, 'UTF-8' );
        if ( strpos( $url, 'mailto:' ) === 0 || strpos( $url, '#' ) === 0 ) {
            return $m[0];
        }
        $encoded = rawurlencode( base64_encode( $url ) );
        $payload = $send_id . '|' . $encoded;
        $track   = add_query_arg( [
            'teinvit_click' => rawurlencode( $send_id ),
            'u'             => $encoded,
            'sig'           => teinvit_email_sign( $payload ),
        ], home_url( '/' ) );
        return 'href="' . esc_url( $track ) . '"';
    }, $html );

    $html .= '<img src="' . esc_url( $open_url ) . '" alt="" width="1" height="1" style="display:none;" />';
    return $html;
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

    $email_id_map = [
        'token_generated_customer' => 'teinvit_email_token_generated',
        'rsvp_received_customer'   => 'teinvit_email_rsvp_received',
        'guest_marketing_consent_1'=> 'teinvit_email_guest_marketing_1',
    ];
    $wc_id = $email_id_map[ $send['template_id'] ] ?? '';
    if ( $wc_id === '' ) {
        return;
    }

    $mailer = WC()->mailer();
    $emails = $mailer->get_emails();
    if ( empty( $emails[ $wc_id ] ) ) {
        return;
    }

    $status = 'failed';
    $error_message = null;

    $ok = $emails[ $wc_id ]->trigger( $send_id );
    if ( $ok ) {
        $status = 'sent';
    } else {
        $error_message = 'wc_mailer_send_failed';
    }

    $wpdb->update( teinvit_email_tables()['sends'], [
        'status'        => $status,
        'error_message' => $error_message,
        'sent_at'       => $status === 'sent' ? current_time( 'mysql' ) : null,
        'updated_at'    => current_time( 'mysql' ),
    ], [ 'send_id' => $send_id ] );
}

function teinvit_email_schedule_send( $send_id, $timestamp = null ) {
    $timestamp = $timestamp ? (int) $timestamp : time();

    if ( function_exists( 'as_schedule_single_action' ) ) {
        as_schedule_single_action( $timestamp, 'teinvit_email_process_send', [ 'send_id' => $send_id ], 'teinvit-emails' );
        return;
    }

    wp_schedule_single_event( $timestamp, 'teinvit_email_process_send', [ $send_id ] );
}

add_action( 'teinvit_email_process_send', function( $send_id ) {
    if ( is_array( $send_id ) && isset( $send_id['send_id'] ) ) {
        $send_id = $send_id['send_id'];
    }
    teinvit_email_dispatch_wc( sanitize_text_field( (string) $send_id ) );
}, 10, 1 );

function teinvit_email_queue_template( $template_id, array $args ) {
    $template = teinvit_get_email_template( $template_id );
    if ( ! $template || ( $template['status'] ?? 'draft' ) !== 'active' ) {
        return null;
    }

    $recipient = sanitize_email( (string) ( $args['recipient_email'] ?? '' ) );
    if ( $recipient === '' || ! is_email( $recipient ) ) {
        return null;
    }

    if ( ! empty( $template['is_marketing'] ) ) {
        if ( empty( $args['marketing_consent'] ) ) {
            return null;
        }
        if ( teinvit_email_is_suppressed( $recipient, 'marketing' ) ) {
            return null;
        }
    }

    if ( teinvit_email_send_rate_limited( $template, $recipient ) ) {
        return null;
    }

    $semantic_hash = (string) ( $args['semantic_hash'] ?? '' );
    if ( $semantic_hash !== '' && teinvit_email_has_recent_duplicate( $template, $recipient, $semantic_hash ) ) {
        return null;
    }

    $rendered = teinvit_email_render_template( $template, $args );
    $send_id = teinvit_email_save_send( $template, $rendered, [
        'token'           => $args['token'] ?? '',
        'order_id'        => $args['order_id'] ?? 0,
        'rsvp_id'         => $args['rsvp_id'] ?? 0,
        'recipient_email' => $recipient,
        'semantic_hash'   => $semantic_hash,
        'scheduled_at'    => $args['scheduled_at'] ?? null,
    ], 'queued' );

    $send = teinvit_email_get_send( $send_id );
    if ( $send ) {
        global $wpdb;
        $tracked = teinvit_email_attach_tracking( $send_id, (string) $send['body_rendered'] );
        $wpdb->update( teinvit_email_tables()['sends'], [
            'body_rendered'   => $tracked,
            'body_rendered_hash' => hash( 'sha256', $tracked ),
            'updated_at'      => current_time( 'mysql' ),
        ], [ 'send_id' => $send_id ] );
    }

    teinvit_email_schedule_send( $send_id, $args['scheduled_at'] ?? null );
    return $send_id;
}

function teinvit_email_customer_for_order( $order_id ) {
    $order = $order_id ? wc_get_order( $order_id ) : null;
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

function teinvit_email_order_id_by_token( $token ) {
    return function_exists( 'teinvit_get_order_id_by_token' ) ? (int) teinvit_get_order_id_by_token( $token ) : 0;
}

function teinvit_email_get_previous_rsvp_for_phone( $token, $phone, $exclude_id ) {
    global $wpdb;
    $t = teinvit_db_tables();
    return $wpdb->get_row( $wpdb->prepare(
        "SELECT * FROM {$t['rsvp']} WHERE token=%s AND guest_phone=%s AND id <> %d ORDER BY id DESC LIMIT 1",
        $token,
        $phone,
        (int) $exclude_id
    ), ARRAY_A );
}

function teinvit_email_rsvp_payload_from_row( array $row ) {
    return [
        'guest_first_name'          => (string) ( $row['guest_first_name'] ?? '' ),
        'guest_last_name'           => (string) ( $row['guest_last_name'] ?? '' ),
        'guest_email'               => (string) ( $row['guest_email'] ?? '' ),
        'guest_phone'               => (string) ( $row['guest_phone'] ?? '' ),
        'attending_people_count'    => (int) ( $row['attending_people_count'] ?? 0 ),
        'attending_civil'           => (int) ( $row['attending_civil'] ?? 0 ),
        'attending_religious'       => (int) ( $row['attending_religious'] ?? 0 ),
        'attending_party'           => (int) ( $row['attending_party'] ?? 0 ),
        'bringing_kids'             => (int) ( $row['bringing_kids'] ?? 0 ),
        'kids_count'                => (int) ( $row['kids_count'] ?? 0 ),
        'needs_accommodation'       => (int) ( $row['needs_accommodation'] ?? 0 ),
        'accommodation_people_count'=> (int) ( $row['accommodation_people_count'] ?? 0 ),
        'vegetarian_requested'      => (int) ( $row['vegetarian_requested'] ?? 0 ),
        'vegetarian_menus_count'    => (int) ( $row['vegetarian_menus_count'] ?? 0 ),
        'has_allergies'             => (int) ( $row['has_allergies'] ?? 0 ),
        'allergy_details'           => (string) ( $row['allergy_details'] ?? '' ),
        'message_to_couple'         => (string) ( $row['message_to_couple'] ?? '' ),
        'marketing_consent'         => (int) ( $row['marketing_consent'] ?? 0 ),
    ];
}

add_action( 'teinvit_token_generated', function( $order_id, $token ) {
    $recipient = teinvit_email_customer_for_order( (int) $order_id );
    if ( $recipient === '' ) {
        return;
    }

    teinvit_email_queue_template( 'token_generated_customer', [
        'token'           => sanitize_text_field( (string) $token ),
        'order_id'        => (int) $order_id,
        'recipient_email' => $recipient,
        'payload'         => [],
    ] );
}, 10, 2 );

add_action( 'teinvit_rsvp_saved', function( $token, $rsvp_id, $payload ) {
    $token   = sanitize_text_field( (string) $token );
    $rsvp_id = (int) $rsvp_id;
    $payload = is_array( $payload ) ? $payload : [];

    $phone = teinvit_email_normalize_phone( (string) ( $payload['guest_phone'] ?? '' ) );
    $prev  = $phone !== '' ? teinvit_email_get_previous_rsvp_for_phone( $token, $phone, $rsvp_id ) : null;

    $current_hash = teinvit_email_payload_semantic_hash( $payload );
    $prev_hash    = is_array( $prev ) ? teinvit_email_payload_semantic_hash( teinvit_email_rsvp_payload_from_row( $prev ) ) : '';

    if ( $prev_hash !== '' && $prev_hash === $current_hash ) {
        return;
    }

    $order_id   = teinvit_email_order_id_by_token( $token );
    $recipient  = teinvit_email_customer_for_order( $order_id );
    if ( $recipient === '' ) {
        return;
    }

    teinvit_email_queue_template( 'rsvp_received_customer', [
        'token'           => $token,
        'order_id'        => $order_id,
        'rsvp_id'         => $rsvp_id,
        'recipient_email' => $recipient,
        'payload'         => $payload,
        'semantic_hash'   => $current_hash,
    ] );
}, 10, 3 );

add_action( 'teinvit_rsvp_saved', function( $token, $rsvp_id, $payload ) {
    $token   = sanitize_text_field( (string) $token );
    $payload = is_array( $payload ) ? $payload : [];

    $email   = sanitize_email( (string) ( $payload['guest_email'] ?? '' ) );
    $consent = ! empty( $payload['marketing_consent'] );
    if ( $email === '' || ! is_email( $email ) || ! $consent ) {
        return;
    }

    $phone = teinvit_email_normalize_phone( (string) ( $payload['guest_phone'] ?? '' ) );
    if ( $phone !== '' ) {
        $prev = teinvit_email_get_previous_rsvp_for_phone( $token, $phone, (int) $rsvp_id );
        if ( is_array( $prev ) && ! empty( $prev['guest_email'] ) && strtolower( (string) $prev['guest_email'] ) === strtolower( $email ) ) {
            $prev_created = strtotime( (string) ( $prev['created_at'] ?? '' ) );
            if ( $prev_created && ( time() - $prev_created ) < 10 * MINUTE_IN_SECONDS ) {
                return;
            }
        }
    }

    $order_id = teinvit_email_order_id_by_token( $token );
    teinvit_email_queue_template( 'guest_marketing_consent_1', [
        'token'             => $token,
        'order_id'          => $order_id,
        'rsvp_id'           => (int) $rsvp_id,
        'recipient_email'   => $email,
        'payload'           => $payload,
        'marketing_consent' => 1,
        'semantic_hash'     => teinvit_email_payload_semantic_hash( $payload ),
        'scheduled_at'      => time() + DAY_IN_SECONDS,
    ] );
}, 20, 3 );

add_filter( 'query_vars', function( $vars ) {
    $vars[] = 'teinvit_open';
    $vars[] = 'teinvit_click';
    $vars[] = 'teinvit_unsub';
    $vars[] = 'u';
    $vars[] = 'sig';
    $vars[] = 'email';
    return $vars;
} );

add_action( 'template_redirect', function() {
    $send_id = (string) get_query_var( 'teinvit_open' );
    if ( $send_id !== '' ) {
        $sig = (string) get_query_var( 'sig' );
        if ( hash_equals( teinvit_email_sign( $send_id . '|open' ), $sig ) ) {
            teinvit_email_log_event( $send_id, 'open' );
        }
        nocache_headers();
        header( 'Content-Type: image/gif' );
        echo base64_decode( 'R0lGODlhAQABAPAAAAAAAAAAACH5BAEAAAAALAAAAAABAAEAAAICRAEAOw==' );
        exit;
    }

    $send_id = (string) get_query_var( 'teinvit_click' );
    if ( $send_id !== '' ) {
        $u   = (string) get_query_var( 'u' );
        $sig = (string) get_query_var( 'sig' );
        $dest = '';

        if ( hash_equals( teinvit_email_sign( $send_id . '|' . $u ), $sig ) ) {
            $decoded = base64_decode( rawurldecode( $u ), true );
            if ( $decoded ) {
                $dest = esc_url_raw( $decoded );
            }
            teinvit_email_log_event( $send_id, 'click', $dest );
        }

        if ( $dest === '' ) {
            $dest = home_url( '/' );
        }

        wp_safe_redirect( $dest );
        exit;
    }

    $unsub_send = (string) get_query_var( 'teinvit_unsub' );
    if ( $unsub_send !== '' ) {
        $email = sanitize_email( rawurldecode( (string) get_query_var( 'email' ) ) );
        $sig   = (string) get_query_var( 'sig' );
        if ( $email !== '' && hash_equals( teinvit_email_sign( $unsub_send . '|' . strtolower( $email ) ), $sig ) ) {
            teinvit_email_add_suppression( $email, 'marketing', 'unsubscribe_link', $unsub_send );
            wp_die( 'Te-ai dezabonat cu succes de la emailurile marketing TeInvit.' );
        }
        wp_die( 'Link de dezabonare invalid.' );
    }
} );

add_action( 'admin_menu', function() {
    add_submenu_page( 'woocommerce', 'Custom Emails', 'Custom Emails', 'manage_woocommerce', 'teinvit-custom-emails', 'teinvit_emails_page_all' );
    add_submenu_page( 'woocommerce', 'New Email', 'New Email', 'manage_woocommerce', 'teinvit-custom-emails-new', 'teinvit_emails_page_new' );
    add_submenu_page( 'woocommerce', 'Logs', 'Logs', 'manage_woocommerce', 'teinvit-custom-emails-logs', 'teinvit_emails_page_logs' );
    add_submenu_page( 'woocommerce', 'Unsubscribes', 'Unsubscribes/Suppression', 'manage_woocommerce', 'teinvit-custom-emails-suppression', 'teinvit_emails_page_suppression' );
}, 99 );

function teinvit_emails_page_all() {
    if ( ! current_user_can( 'manage_woocommerce' ) ) {
        return;
    }

    $templates = teinvit_get_email_templates();
    echo '<div class="wrap"><h1>Custom Emails</h1><table class="widefat striped"><thead><tr><th>Name</th><th>Trigger</th><th>Audience</th><th>Status</th><th>Delay</th></tr></thead><tbody>';
    foreach ( $templates as $tpl ) {
        echo '<tr>';
        echo '<td>' . esc_html( $tpl['name'] ?? '' ) . '</td>';
        echo '<td>' . esc_html( $tpl['trigger'] ?? '' ) . '</td>';
        echo '<td>' . esc_html( $tpl['audience'] ?? '' ) . '</td>';
        echo '<td>' . esc_html( $tpl['status'] ?? '' ) . '</td>';
        echo '<td>' . esc_html( (string) ( $tpl['delay_value'] ?? 0 ) . ' ' . ( $tpl['delay_unit'] ?? 'hours' ) ) . '</td>';
        echo '</tr>';
    }
    echo '</tbody></table></div>';
}

function teinvit_emails_page_new() {
    if ( ! current_user_can( 'manage_woocommerce' ) ) {
        return;
    }

    if ( isset( $_POST['teinvit_email_save'] ) && check_admin_referer( 'teinvit_email_new' ) ) {
        $id      = sanitize_key( (string) ( $_POST['template_id'] ?? '' ) );
        if ( $id === '' ) {
            $id = 'custom_' . wp_generate_password( 8, false, false );
        }

        $template = [
            'id'               => $id,
            'name'             => sanitize_text_field( (string) ( $_POST['name'] ?? '' ) ),
            'status'           => sanitize_key( (string) ( $_POST['status'] ?? 'draft' ) ),
            'subject'          => sanitize_text_field( (string) ( $_POST['subject'] ?? '' ) ),
            'preheader'        => sanitize_text_field( (string) ( $_POST['preheader'] ?? '' ) ),
            'heading'          => sanitize_text_field( (string) ( $_POST['heading'] ?? '' ) ),
            'trigger'          => sanitize_key( (string) ( $_POST['trigger'] ?? '' ) ),
            'audience'         => sanitize_key( (string) ( $_POST['audience'] ?? 'customer' ) ),
            'delay_value'      => max( 0, (int) ( $_POST['delay_value'] ?? 0 ) ),
            'delay_unit'       => sanitize_key( (string) ( $_POST['delay_unit'] ?? 'hours' ) ),
            'content_html'     => wp_kses_post( (string) ( $_POST['content_html'] ?? '' ) ),
            'button_label'     => sanitize_text_field( (string) ( $_POST['button_label'] ?? '' ) ),
            'button_url'       => esc_url_raw( (string) ( $_POST['button_url'] ?? '' ) ),
            'is_marketing'     => empty( $_POST['is_marketing'] ) ? 0 : 1,
            'require_consent'  => empty( $_POST['require_consent'] ) ? 0 : 1,
            'rate_limit_count' => max( 1, (int) ( $_POST['rate_limit_count'] ?? 2 ) ),
            'rate_limit_days'  => max( 1, (int) ( $_POST['rate_limit_days'] ?? 7 ) ),
        ];

        teinvit_update_email_template( $id, $template );
        echo '<div class="notice notice-success"><p>Email salvat.</p></div>';
    }

    echo '<div class="wrap"><h1>New Email</h1><form method="post">';
    wp_nonce_field( 'teinvit_email_new' );
    echo '<table class="form-table">';
    echo '<tr><th>Template ID</th><td><input name="template_id" class="regular-text" /></td></tr>';
    echo '<tr><th>Name</th><td><input name="name" class="regular-text" required /></td></tr>';
    echo '<tr><th>Status</th><td><select name="status"><option value="draft">Draft</option><option value="active">Active</option></select></td></tr>';
    echo '<tr><th>Subject</th><td><input name="subject" class="regular-text" required /></td></tr>';
    echo '<tr><th>Preheader</th><td><input name="preheader" class="regular-text" /></td></tr>';
    echo '<tr><th>Heading</th><td><input name="heading" class="regular-text" required /></td></tr>';
    echo '<tr><th>Trigger</th><td><select name="trigger"><option value="token_generated">Token generated</option><option value="rsvp_saved">RSVP received</option><option value="guest_consent_1">Guest consent #1</option></select></td></tr>';
    echo '<tr><th>Audience</th><td><select name="audience"><option value="customer">Customer</option><option value="guest">Guest</option></select></td></tr>';
    echo '<tr><th>Delay</th><td><input type="number" name="delay_value" value="0" min="0" /> <select name="delay_unit"><option value="minutes">minutes</option><option value="hours" selected>hours</option><option value="days">days</option></select></td></tr>';
    echo '<tr><th>Content HTML</th><td><textarea name="content_html" rows="8" class="large-text"></textarea></td></tr>';
    echo '<tr><th>Button label</th><td><input name="button_label" class="regular-text" /></td></tr>';
    echo '<tr><th>Button URL</th><td><input name="button_url" class="regular-text" /></td></tr>';
    echo '<tr><th>Marketing</th><td><label><input type="checkbox" name="is_marketing" value="1"/> Is marketing</label> <label style="margin-left:16px"><input type="checkbox" name="require_consent" value="1"/> Require consent</label></td></tr>';
    echo '<tr><th>Rate limit</th><td><input type="number" name="rate_limit_count" value="2" min="1" /> emails / <input type="number" name="rate_limit_days" value="7" min="1" /> zile</td></tr>';
    echo '</table><p class="submit"><button type="submit" name="teinvit_email_save" class="button button-primary">Save Email</button></p></form></div>';
}

function teinvit_emails_page_logs() {
    if ( ! current_user_can( 'manage_woocommerce' ) ) {
        return;
    }

    global $wpdb;
    $tables = teinvit_email_tables();
    $rows = $wpdb->get_results( "SELECT s.*, 
        (SELECT COUNT(*) FROM {$tables['events']} e WHERE e.send_id=s.send_id AND e.event_type='open') AS opens_count,
        (SELECT COUNT(*) FROM {$tables['events']} e WHERE e.send_id=s.send_id AND e.event_type='click') AS clicks_count
        FROM {$tables['sends']} s ORDER BY s.id DESC LIMIT 200", ARRAY_A );

    echo '<div class="wrap"><h1>Custom Emails Logs</h1><table class="widefat striped"><thead><tr><th>Send ID</th><th>Template</th><th>Recipient</th><th>Status</th><th>Opens</th><th>Clicks</th><th>Created</th><th>Sent</th></tr></thead><tbody>';
    foreach ( $rows as $row ) {
        echo '<tr>';
        echo '<td>' . esc_html( $row['send_id'] ) . '</td>';
        echo '<td>' . esc_html( $row['template_id'] ) . '</td>';
        echo '<td>' . esc_html( $row['recipient_email'] ) . '</td>';
        echo '<td>' . esc_html( $row['status'] ) . '</td>';
        echo '<td>' . esc_html( (string) $row['opens_count'] ) . '</td>';
        echo '<td>' . esc_html( (string) $row['clicks_count'] ) . '</td>';
        echo '<td>' . esc_html( $row['created_at'] ) . '</td>';
        echo '<td>' . esc_html( (string) $row['sent_at'] ) . '</td>';
        echo '</tr>';
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
            $wpdb->delete( $tables['suppression'], [ 'email' => $email, 'scope' => 'marketing' ] );
        }
    }

    $rows = $wpdb->get_results( "SELECT * FROM {$tables['suppression']} ORDER BY id DESC LIMIT 200", ARRAY_A );
    echo '<div class="wrap"><h1>Suppression</h1><table class="widefat striped"><thead><tr><th>Email</th><th>Scope</th><th>Reason</th><th>Created</th><th>Action</th></tr></thead><tbody>';
    foreach ( $rows as $row ) {
        echo '<tr><td>' . esc_html( $row['email'] ) . '</td><td>' . esc_html( $row['scope'] ) . '</td><td>' . esc_html( $row['reason'] ) . '</td><td>' . esc_html( $row['created_at'] ) . '</td><td>';
        echo '<form method="post">';
        wp_nonce_field( 'teinvit_unsuppress' );
        echo '<input type="hidden" name="email" value="' . esc_attr( $row['email'] ) . '" />';
        echo '<button type="submit" name="teinvit_unsuppress_email" class="button">Revoke</button></form>';
        echo '</td></tr>';
    }
    echo '</tbody></table></div>';
}

add_action( 'init', function() {
    if ( ! wp_next_scheduled( 'teinvit_email_cleanup_retention' ) ) {
        wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'teinvit_email_cleanup_retention' );
    }
} );

add_action( 'teinvit_email_cleanup_retention', function() {
    global $wpdb;
    $tables = teinvit_email_tables();

    $events_before = gmdate( 'Y-m-d H:i:s', time() - 365 * DAY_IN_SECONDS );
    $sends_before  = gmdate( 'Y-m-d H:i:s', time() - 730 * DAY_IN_SECONDS );

    $wpdb->query( $wpdb->prepare( "DELETE FROM {$tables['events']} WHERE event_at < %s", $events_before ) );
    $wpdb->query( $wpdb->prepare( "DELETE FROM {$tables['sends']} WHERE created_at < %s", $sends_before ) );
} );

add_filter( 'woocommerce_email_classes', function( $emails ) {
    if ( ! class_exists( 'WC_Email' ) ) {
        return $emails;
    }

    class TeInvit_WC_Email_Base extends WC_Email {
        public function __construct( $id, $title, $description ) {
            $this->id             = $id;
            $this->title          = $title;
            $this->description    = $description;
            $this->customer_email = true;
            $this->template_html  = 'emails/plain.php';
            $this->template_plain = 'emails/plain.php';
            $this->placeholders   = [];
            parent::__construct();
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

        public function trigger( $send_id = '' ) {
            if ( 'yes' !== $this->enabled ) {
                return false;
            }

            $send = teinvit_email_get_send( $send_id );
            if ( ! $send ) {
                return false;
            }

            $recipient = sanitize_email( (string) ( $send['recipient_email'] ?? '' ) );
            if ( $recipient === '' || ! is_email( $recipient ) ) {
                return false;
            }

            $subject = (string) ( $send['subject_rendered'] ?? $this->get_default_subject() );
            $body    = (string) ( $send['body_rendered'] ?? '' );
            $headers = $this->get_headers();
            $attachments = $this->get_attachments();

            return (bool) $this->send( $recipient, $subject, $body, $headers, $attachments );
        }

        public function get_default_subject() {
            return $this->title;
        }

        public function get_content_html() {
            return '';
        }

        public function get_content_plain() {
            return '';
        }
    }

    $emails['teinvit_email_token_generated'] = new TeInvit_WC_Email_Base(
        'teinvit_email_token_generated',
        'TeInvit: Token generated → Customer',
        'Email trimis clientului când tokenul este generat.'
    );

    $emails['teinvit_email_rsvp_received'] = new TeInvit_WC_Email_Base(
        'teinvit_email_rsvp_received',
        'TeInvit: RSVP received → Customer',
        'Email trimis clientului la RSVP nou.'
    );

    $emails['teinvit_email_guest_marketing_1'] = new TeInvit_WC_Email_Base(
        'teinvit_email_guest_marketing_1',
        'TeInvit: Guest consent #1 (24h)',
        'Email marketing trimis invitaților cu consent.'
    );

    return $emails;
} );
