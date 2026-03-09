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
            'require_consent'  => 0,
            'rate_limit_count' => 2,
            'rate_limit_days'  => 7,
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
            'require_consent'  => 0,
            'rate_limit_count' => 2,
            'rate_limit_days'  => 7,
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
            'require_consent'  => 1,
            'rate_limit_count' => 2,
            'rate_limit_days'  => 7,
            'blocks'           => [
                [ 'type' => 'title', 'text' => 'Invitații digitale TeInvit' ],
                [ 'type' => 'text', 'html' => '<p>Mulțumim pentru confirmare! Vezi cum funcționează invitațiile digitale și inspiră-te din cele mai noi modele.</p>' ],
                [ 'type' => 'button', 'label' => 'Vezi modele', 'url' => 'https://www.teinvit.com/magazin/', 'style' => 'primary' ],
                [ 'type' => 'footer', 'html' => '<p>{why_received_text}</p><p><a href="{unsubscribe_url}">Dezabonare</a></p>' ],
            ],
        ],
    ];
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

    return hash( 'sha256', wp_json_encode( $norm ) );
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
}

function teinvit_email_build_context( array $args ) {
    $token    = (string) ( $args['token'] ?? '' );
    $order_id = (int) ( $args['order_id'] ?? 0 );
    $payload  = is_array( $args['payload'] ?? null ) ? $args['payload'] : [];
    $send_id  = (string) ( $args['send_id'] ?? '' );

    $order = $order_id > 0 ? wc_get_order( $order_id ) : null;

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
        '{site_name}'                => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
        '{order_number}'             => $order_no,
        '{order_id}'                 => (string) $order_id,
        '{token}'                    => $token,
        '{admin_client_url}'         => home_url( '/admin-client/' . rawurlencode( $token ) ),
        '{invitati_url}'             => home_url( '/invitati/' . rawurlencode( $token ) ),
        '{report_url}'               => home_url( '/admin-client/' . rawurlencode( $token ) . '#teinvit-report' ),
        '{customer_first_name}'      => $first_name,
        '{customer_last_name}'       => $last_name,
        '{guest_name}'               => $guest_name,
        '{guest_phone}'              => (string) ( $payload['guest_phone'] ?? '' ),
        '{guest_email}'              => (string) ( $payload['guest_email'] ?? '' ),
        '{rsvp_adults}'              => (string) ( $payload['attending_people_count'] ?? '' ),
        '{rsvp_children}'            => (string) ( $payload['kids_count'] ?? '' ),
        '{rsvp_attending_civil}'     => ! empty( $payload['attending_civil'] ) ? 'Da' : 'Nu',
        '{rsvp_attending_religious}' => ! empty( $payload['attending_religious'] ) ? 'Da' : 'Nu',
        '{rsvp_attending_party}'     => ! empty( $payload['attending_party'] ) ? 'Da' : 'Nu',
        '{rsvp_accommodation}'       => ! empty( $payload['needs_accommodation'] ) ? 'Da' : 'Nu',
        '{rsvp_vegetarian}'          => ! empty( $payload['vegetarian_requested'] ) ? 'Da' : 'Nu',
        '{rsvp_vegetarian_menus}'    => (string) ( $payload['vegetarian_menus_count'] ?? '' ),
        '{rsvp_allergies}'           => (string) ( $payload['allergy_details'] ?? '' ),
        '{rsvp_message}'             => (string) ( $payload['message_to_couple'] ?? '' ),
        '{unsubscribe_url}'          => $unsubscribe,
        '{why_received_text}'        => 'Primești acest email deoarece ai bifat acordul de marketing în formularul RSVP.',
    ];
}

function teinvit_email_apply_tags( $text, array $context ) {
    return strtr( (string) $text, $context );
}

function teinvit_email_render_block( array $block, array $context, $accent ) {
    $type = sanitize_key( (string) ( $block['type'] ?? '' ) );
    if ( $type === '' ) {
        return [ 'html' => '', 'text' => '' ];
    }

    switch ( $type ) {
        case 'logo':
            if ( empty( $block['enabled'] ) ) {
                return [ 'html' => '', 'text' => '' ];
            }
            $url   = esc_url( teinvit_email_apply_tags( $block['url'] ?? '', $context ) );
            if ( $url === '' ) {
                return [ 'html' => '', 'text' => '' ];
            }
            $align = in_array( (string) ( $block['align'] ?? 'center' ), [ 'left', 'center', 'right' ], true ) ? $block['align'] : 'center';
            return [
                'html' => '<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr><td style="padding:0 0 20px 0;text-align:' . esc_attr( $align ) . ';"><img src="' . $url . '" alt="Logo" style="max-width:180px;height:auto;display:inline-block;border:0;"/></td></tr></table>',
                'text' => '',
            ];

        case 'banner':
            $url = esc_url( teinvit_email_apply_tags( $block['url'] ?? '', $context ) );
            if ( $url === '' ) {
                return [ 'html' => '', 'text' => '' ];
            }
            return [
                'html' => '<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr><td style="padding:0 0 20px 0;text-align:center;"><img src="' . $url . '" alt="Banner" style="max-width:100%;height:auto;display:block;border:0;border-radius:10px;"/></td></tr></table>',
                'text' => '',
            ];

        case 'title':
            $text = esc_html( teinvit_email_apply_tags( $block['text'] ?? '', $context ) );
            return [
                'html' => '<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr><td style="padding:0 0 14px 0;font-family:Arial,Helvetica,sans-serif;font-size:28px;line-height:34px;font-weight:700;color:#1f1f1f;">' . $text . '</td></tr></table>',
                'text' => $text . "\n\n",
            ];

        case 'subtitle':
            $text = esc_html( teinvit_email_apply_tags( $block['text'] ?? '', $context ) );
            return [
                'html' => '<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr><td style="padding:0 0 12px 0;font-family:Arial,Helvetica,sans-serif;font-size:18px;line-height:24px;font-weight:700;color:#3a3a3a;">' . $text . '</td></tr></table>',
                'text' => $text . "\n",
            ];

        case 'text':
            $html = teinvit_email_apply_tags( (string) ( $block['html'] ?? '' ), $context );
            $html = wp_kses_post( $html );
            return [
                'html' => '<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr><td style="padding:0 0 14px 0;font-family:Arial,Helvetica,sans-serif;font-size:15px;line-height:24px;color:#2a2a2a;">' . wpautop( $html ) . '</td></tr></table>',
                'text' => wp_strip_all_tags( str_replace( '<br>', "\n", $html ) ) . "\n\n",
            ];

        case 'bullets':
            $items = is_array( $block['items'] ?? null ) ? $block['items'] : [];
            if ( empty( $items ) ) {
                return [ 'html' => '', 'text' => '' ];
            }
            $list_html = '';
            $list_text = '';
            foreach ( $items as $item ) {
                $line = teinvit_email_apply_tags( (string) $item, $context );
                $list_html .= '<li style="margin:0 0 8px 0;">' . esc_html( $line ) . '</li>';
                $list_text .= '- ' . wp_strip_all_tags( $line ) . "\n";
            }
            return [
                'html' => '<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr><td style="padding:0 0 14px 0;"><ul style="margin:0 0 0 18px;padding:0;font-family:Arial,Helvetica,sans-serif;font-size:15px;line-height:23px;color:#2a2a2a;">' . $list_html . '</ul></td></tr></table>',
                'text' => $list_text . "\n",
            ];

        case 'divider':
            return [
                'html' => '<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr><td style="padding:16px 0;"><div style="border-top:1px solid #e6e6e6;"></div></td></tr></table>',
                'text' => "----------------\n",
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
                'html' => '<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr><td style="padding:4px 0 16px 0;"><table role="presentation" cellpadding="0" cellspacing="0"><tr><td style="border-radius:8px;background:' . esc_attr( $bg ) . ';border:1px solid ' . esc_attr( $border ) . ';"><a href="' . esc_url( $url ) . '" style="display:inline-block;padding:12px 22px;font-family:Arial,Helvetica,sans-serif;font-size:15px;font-weight:700;color:' . esc_attr( $color ) . ';text-decoration:none;border-radius:8px;">' . esc_html( $label ) . '</a></td></tr></table></td></tr></table>',
                'text' => $label . ': ' . $url . "\n\n",
            ];

        case 'link':
            $url   = teinvit_email_apply_tags( $block['url'] ?? '', $context );
            $label = teinvit_email_apply_tags( $block['label'] ?? '', $context );
            if ( $url === '' || $label === '' ) {
                return [ 'html' => '', 'text' => '' ];
            }
            return [
                'html' => '<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr><td style="padding:0 0 14px 0;font-family:Arial,Helvetica,sans-serif;font-size:14px;line-height:22px;"><a href="' . esc_url( $url ) . '" style="color:' . esc_attr( $accent ) . ';text-decoration:underline;">' . esc_html( $label ) . '</a></td></tr></table>',
                'text' => $label . ': ' . $url . "\n\n",
            ];

        case 'footer':
            $html = teinvit_email_apply_tags( (string) ( $block['html'] ?? '' ), $context );
            $html = wp_kses_post( $html );
            return [
                'html' => '<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr><td style="padding:18px 0 0 0;font-family:Arial,Helvetica,sans-serif;font-size:12px;line-height:19px;color:#6b6b6b;border-top:1px solid #ececec;">' . wpautop( $html ) . '</td></tr></table>',
                'text' => wp_strip_all_tags( $html ) . "\n",
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
    $wpdb->insert(
        $tables['events'],
        [
            'send_id'    => sanitize_text_field( $send_id ),
            'event_type' => sanitize_key( $event_type ),
            'event_at'   => current_time( 'mysql' ),
            'ip_hash'    => teinvit_email_hash_ip(),
            'ua_hash'    => teinvit_email_hash_ua(),
            'url'        => $url ? esc_url_raw( $url ) : null,
            'meta_json'  => null,
        ]
    );
}

function teinvit_email_send_rate_limited( array $template, $recipient_email ) {
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

    return $count >= $count_limit;
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

function teinvit_email_template_id_for_wc_email_id( $wc_email_id ) {
    $map = [
        'teinvit_email_token_generated' => 'token_generated_customer',
        'teinvit_email_rsvp_received' => 'rsvp_received_customer',
        'teinvit_email_guest_marketing_1' => 'guest_marketing_consent_1',
    ];

    return $map[ $wc_email_id ] ?? '';
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

    $ok = $emails[ $wc_id ]->trigger( $send_id );

    $wpdb->update(
        teinvit_email_tables()['sends'],
        [
            'status'        => $ok ? 'sent' : 'failed',
            'error_message' => $ok ? null : 'wc_mailer_send_failed',
            'sent_at'       => $ok ? current_time( 'mysql' ) : null,
            'updated_at'    => current_time( 'mysql' ),
        ],
        [ 'send_id' => $send_id ]
    );
}

function teinvit_email_schedule_send( $send_id, $timestamp = null ) {
    $timestamp = $timestamp ? (int) $timestamp : time();

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
        teinvit_email_dispatch_wc( sanitize_text_field( (string) $send_id ) );
    },
    10,
    1
);

function teinvit_email_queue_template( $template_id, array $args ) {
    $template_id = teinvit_email_resolve_template_id(
        $template_id,
        [
            'trigger'  => $args['trigger'] ?? '',
            'audience' => $args['audience'] ?? '',
        ]
    );

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
    if ( teinvit_email_has_recent_duplicate( $template, $recipient, $semantic_hash ) ) {
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
            'scheduled_at'       => ! empty( $args['scheduled_at'] ) ? gmdate( 'Y-m-d H:i:s', (int) $args['scheduled_at'] ) : null,
            'created_at'         => $now,
            'updated_at'         => $now,
        ]
    );

    teinvit_email_schedule_send( $send_id, $args['scheduled_at'] ?? null );

    return $send_id;
}

function teinvit_email_customer_for_order( $order_id ) {
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

function teinvit_email_order_id_by_token( $token ) {
    return function_exists( 'teinvit_get_order_id_by_token' ) ? (int) teinvit_get_order_id_by_token( $token ) : 0;
}

function teinvit_email_get_previous_rsvp_for_phone( $token, $phone, $exclude_id ) {
    global $wpdb;
    $t = teinvit_db_tables();

    return $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM {$t['rsvp']} WHERE token=%s AND guest_phone=%s AND id<>%d ORDER BY id DESC LIMIT 1",
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
        'marketing_consent'          => (int) ( $row['marketing_consent'] ?? 0 ),
    ];
}

add_action(
    'teinvit_token_generated',
    function( $order_id, $token ) {
        $recipient = teinvit_email_customer_for_order( (int) $order_id );
        if ( $recipient === '' ) {
            return;
        }

        teinvit_email_queue_template(
            'token_generated_customer',
            [
                'token'           => sanitize_text_field( (string) $token ),
                'order_id'        => (int) $order_id,
                'recipient_email' => $recipient,
                'payload'         => [],
            ]
        );
    },
    10,
    2
);

add_action(
    'teinvit_rsvp_saved',
    function( $token, $rsvp_id, $payload ) {
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

        $order_id  = teinvit_email_order_id_by_token( $token );
        $recipient = teinvit_email_customer_for_order( $order_id );
        if ( $recipient === '' ) {
            return;
        }

        teinvit_email_queue_template(
            'rsvp_received_customer',
            [
                'token'           => $token,
                'order_id'        => $order_id,
                'rsvp_id'         => $rsvp_id,
                'recipient_email' => $recipient,
                'payload'         => $payload,
                'semantic_hash'   => $current_hash,
            ]
        );
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

        $email   = sanitize_email( (string) ( $payload['guest_email'] ?? '' ) );
        $consent = ! empty( $payload['marketing_consent'] );
        if ( $email === '' || ! is_email( $email ) || ! $consent ) {
            return;
        }

        $order_id = teinvit_email_order_id_by_token( $token );

        teinvit_email_queue_template(
            'guest_marketing_consent_1',
            [
                'token'             => $token,
                'order_id'          => $order_id,
                'rsvp_id'           => $rsvp_id,
                'recipient_email'   => $email,
                'payload'           => $payload,
                'marketing_consent' => 1,
                'semantic_hash'     => teinvit_email_payload_semantic_hash( $payload ),
                'scheduled_at'      => time() + DAY_IN_SECONDS,
            ]
        );
    },
    20,
    3
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
        add_submenu_page( 'woocommerce', 'Custom Emails', 'Custom Emails', 'manage_woocommerce', 'teinvit-custom-emails', 'teinvit_emails_page_all' );
        add_submenu_page( 'woocommerce', 'New Email', 'New Email', 'manage_woocommerce', 'teinvit-custom-emails-new', 'teinvit_emails_page_new' );
        add_submenu_page( 'woocommerce', 'Logs', 'Logs', 'manage_woocommerce', 'teinvit-custom-emails-logs', 'teinvit_emails_page_logs' );
        add_submenu_page( 'woocommerce', 'Unsubscribes', 'Unsubscribes/Suppression', 'manage_woocommerce', 'teinvit-custom-emails-suppression', 'teinvit_emails_page_suppression' );
    },
    99
);

function teinvit_emails_page_all() {
    if ( ! current_user_can( 'manage_woocommerce' ) ) {
        return;
    }

    $templates = teinvit_get_email_templates();

    echo '<div class="wrap"><h1>Custom Emails</h1><table class="widefat striped"><thead><tr><th>Name</th><th>ID</th><th>Trigger</th><th>Audience</th><th>Status</th><th>Delay</th></tr></thead><tbody>';
    foreach ( $templates as $tpl ) {
        echo '<tr>';
        echo '<td>' . esc_html( $tpl['name'] ?? '' ) . '</td>';
        echo '<td>' . esc_html( $tpl['id'] ?? '' ) . '</td>';
        echo '<td>' . esc_html( $tpl['trigger'] ?? '' ) . '</td>';
        echo '<td>' . esc_html( $tpl['audience'] ?? '' ) . '</td>';
        echo '<td>' . esc_html( $tpl['status'] ?? '' ) . '</td>';
        echo '<td>' . esc_html( (string) ( $tpl['delay_value'] ?? 0 ) . ' ' . (string) ( $tpl['delay_unit'] ?? 'hours' ) ) . '</td>';
        echo '</tr>';
    }
    echo '</tbody></table></div>';
}

function teinvit_emails_page_new() {
    if ( ! current_user_can( 'manage_woocommerce' ) ) {
        return;
    }

    $templates = teinvit_get_email_templates();
    $editing_id = isset( $_GET['template_id'] ) ? sanitize_key( (string) wp_unslash( $_GET['template_id'] ) ) : 'token_generated_customer';
    $template = $templates[ $editing_id ] ?? [
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
        'require_consent'  => 0,
        'rate_limit_count' => 2,
        'rate_limit_days'  => 7,
        'blocks'           => [ [ 'type' => 'title', 'text' => '' ] ],
    ];

    if ( $_SERVER['REQUEST_METHOD'] === 'POST' && check_admin_referer( 'teinvit_email_new' ) ) {
        $id = sanitize_key( (string) ( $_POST['template_id'] ?? '' ) );
        if ( $id === '' ) {
            $id = ! empty( $template['id'] ) ? sanitize_key( (string) $template['id'] ) : 'custom_' . wp_generate_password( 8, false, false );
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
            'require_consent'  => empty( $_POST['require_consent'] ) ? 0 : 1,
            'rate_limit_count' => max( 1, (int) ( $_POST['rate_limit_count'] ?? 2 ) ),
            'rate_limit_days'  => max( 1, (int) ( $_POST['rate_limit_days'] ?? 7 ) ),
            'blocks'           => teinvit_email_read_blocks_from_post(),
        ];

        if ( empty( $template['blocks'] ) || ! is_array( $template['blocks'] ) ) {
            $template['blocks'] = teinvit_email_default_blocks_for_template( $id );
        }

        if ( isset( $_POST['teinvit_email_save'] ) ) {
            teinvit_update_email_template( $id, $template );
            echo '<div class="notice notice-success"><p>Email salvat.</p></div>';
        } else {
            echo '<div class="notice notice-info"><p>Modificările builder sunt încă nesalvate. Apasă "Save Email" pentru persistare.</p></div>';
        }

        if ( isset( $_POST['teinvit_email_send_test'] ) ) {
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
                    ]
                );
                echo '<div class="notice notice-success"><p>Test email pus în coadă către admin email.</p></div>';
            } else {
                echo '<div class="notice notice-error"><p>Nu există admin email valid pentru test.</p></div>';
            }
        }
    }

    echo '<div class="wrap"><h1>New Email (Block Builder)</h1>';
    echo '<p>Template-uri existente: ';
    foreach ( teinvit_get_email_templates() as $tpl ) {
        $url = esc_url( admin_url( 'admin.php?page=teinvit-custom-emails-new&template_id=' . rawurlencode( $tpl['id'] ) ) );
        echo '<a href="' . $url . '" style="margin-right:10px;">' . esc_html( $tpl['name'] ) . '</a>';
    }
    echo '</p>';

    echo '<form method="post">';
    wp_nonce_field( 'teinvit_email_new' );
    echo '<table class="form-table">';
    echo '<tr><th>Template ID</th><td><input name="template_id" class="regular-text" value="' . esc_attr( $template['id'] ) . '"/></td></tr>';
    echo '<tr><th>Name</th><td><input name="name" class="regular-text" required value="' . esc_attr( $template['name'] ) . '"/></td></tr>';
    echo '<tr><th>Status</th><td><select name="status"><option value="draft"' . selected( $template['status'], 'draft', false ) . '>Draft</option><option value="active"' . selected( $template['status'], 'active', false ) . '>Active</option></select></td></tr>';
    echo '<tr><th>Subject</th><td><input name="subject" class="regular-text" required value="' . esc_attr( $template['subject'] ) . '"/></td></tr>';
    echo '<tr><th>Preheader</th><td><input name="preheader" class="regular-text" value="' . esc_attr( $template['preheader'] ) . '"/></td></tr>';
    echo '<tr><th>Trigger</th><td><select name="trigger"><option value="token_generated"' . selected( $template['trigger'], 'token_generated', false ) . '>Token generated</option><option value="rsvp_saved"' . selected( $template['trigger'], 'rsvp_saved', false ) . '>RSVP received</option><option value="guest_consent_1"' . selected( $template['trigger'], 'guest_consent_1', false ) . '>Guest consent #1</option></select></td></tr>';
    echo '<tr><th>Audience</th><td><select name="audience"><option value="customer"' . selected( $template['audience'], 'customer', false ) . '>Customer</option><option value="guest"' . selected( $template['audience'], 'guest', false ) . '>Guest</option></select></td></tr>';
    echo '<tr><th>Delay</th><td><input type="number" name="delay_value" value="' . esc_attr( (string) $template['delay_value'] ) . '" min="0" /> <select name="delay_unit"><option value="minutes"' . selected( $template['delay_unit'], 'minutes', false ) . '>minutes</option><option value="hours"' . selected( $template['delay_unit'], 'hours', false ) . '>hours</option><option value="days"' . selected( $template['delay_unit'], 'days', false ) . '>days</option></select></td></tr>';
    echo '<tr><th>Accent color</th><td><input type="color" name="accent_color" value="' . esc_attr( $template['accent_color'] ) . '"/></td></tr>';
    echo '<tr><th>Marketing</th><td><label><input type="checkbox" name="is_marketing" value="1" ' . checked( ! empty( $template['is_marketing'] ), true, false ) . '/> Is marketing</label> <label style="margin-left:16px;"><input type="checkbox" name="require_consent" value="1" ' . checked( ! empty( $template['require_consent'] ), true, false ) . '/> Require consent</label></td></tr>';
    echo '<tr><th>Rate limit</th><td><input type="number" min="1" name="rate_limit_count" value="' . esc_attr( (string) $template['rate_limit_count'] ) . '"/> emails / <input type="number" min="1" name="rate_limit_days" value="' . esc_attr( (string) $template['rate_limit_days'] ) . '"/> zile</td></tr>';
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

function teinvit_emails_page_logs() {
    if ( ! current_user_can( 'manage_woocommerce' ) ) {
        return;
    }

    global $wpdb;
    $tables = teinvit_email_tables();

    $rows = $wpdb->get_results(
        "SELECT s.*,
        (SELECT COUNT(*) FROM {$tables['events']} e WHERE e.send_id=s.send_id AND e.event_type='open') AS opens_count,
        (SELECT COUNT(*) FROM {$tables['events']} e WHERE e.send_id=s.send_id AND e.event_type='click') AS clicks_count
        FROM {$tables['sends']} s
        ORDER BY s.id DESC
        LIMIT 300",
        ARRAY_A
    );

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

    $rows = $wpdb->get_results( "SELECT * FROM {$tables['suppression']} ORDER BY id DESC LIMIT 300", ARRAY_A );

    echo '<div class="wrap"><h1>Unsubscribes / Suppression</h1><table class="widefat striped"><thead><tr><th>Email</th><th>Scope</th><th>Reason</th><th>Created</th><th>Action</th></tr></thead><tbody>';
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
                public function __construct( $id, $title, $description ) {
                    $this->id             = $id;
                    $this->title          = $title;
                    $this->description    = $description;
                    $this->customer_email = true;
                    $this->email_type     = 'html';
                    $this->enabled        = 'yes';
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

                public function get_content_type( $default_content_type = '' ) {
                    if ( is_string( $default_content_type ) && $default_content_type !== '' ) {
                        return 'text/html; charset=UTF-8';
                    }

                    return 'text/html; charset=UTF-8';
                }

                public function trigger( $send_id = '' ) {
                    if ( 'yes' !== $this->enabled ) {
                        return false;
                    }

                    $send = teinvit_email_get_send( $send_id );
                    if ( ! $send ) {
                        $template_id = teinvit_email_template_id_for_wc_email_id( $this->id );
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
                        if ( $recipient === '' || ! is_email( $recipient ) ) {
                            return false;
                        }

                        return (bool) $this->send( $recipient, (string) $render['subject'], (string) $render['body_html'], $this->get_headers(), $this->get_attachments() );
                    }

                    $recipient = sanitize_email( (string) ( $send['recipient_email'] ?? '' ) );
                    if ( $recipient === '' || ! is_email( $recipient ) ) {
                        return false;
                    }

                    $subject = (string) ( $send['subject_rendered'] ?? $this->get_default_subject() );
                    $content = (string) ( $send['body_rendered'] ?? '' );
                    if ( $content === '' ) {
                        $template_id = teinvit_email_template_id_for_wc_email_id( $this->id );
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

                    return (bool) $this->send( $recipient, $subject, $content, $this->get_headers(), $this->get_attachments() );
                }

                public function get_content_html() {
                    $template_id = teinvit_email_template_id_for_wc_email_id( $this->id );
                    $template    = $template_id ? teinvit_get_email_template( $template_id ) : null;
                    if ( ! $template ) {
                        return '<p>TeInvit email preview indisponibil.</p>';
                    }

                    $sample = teinvit_email_sample_context_args( $template_id, sanitize_email( get_option( 'admin_email' ) ) );
                    $render = teinvit_email_render_template( $template, $sample );

                    return (string) ( $render['body_html'] ?? '' );
                }

                public function get_content_plain() {
                    $template_id = teinvit_email_template_id_for_wc_email_id( $this->id );
                    $template    = $template_id ? teinvit_get_email_template( $template_id ) : null;
                    if ( ! $template ) {
                        return 'TeInvit email preview indisponibil.';
                    }

                    $sample = teinvit_email_sample_context_args( $template_id, sanitize_email( get_option( 'admin_email' ) ) );
                    $render = teinvit_email_render_template( $template, $sample );

                    return (string) ( $render['body_text'] ?? '' );
                }

                public function get_default_subject() {
                    return $this->title;
                }
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
            'Email trimis clientului după un RSVP nou.'
        );
        $emails['teinvit_email_guest_marketing_1'] = new TeInvit_WC_Email_Base(
            'teinvit_email_guest_marketing_1',
            'TeInvit: Guest consent #1 (24h)',
            'Email marketing trimis invitaților care au consent valid.'
        );

        return $emails;
    }
);
