<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function teinvit_join_ro_names( array $names ) {
    $names = array_values( array_filter( array_map( static function( $name ) {
        return trim( (string) $name );
    }, $names ), static function( $name ) {
        return $name !== '';
    } ) );

    $count = count( $names );
    if ( $count === 0 ) {
        return '';
    }
    if ( $count === 1 ) {
        return $names[0];
    }
    if ( $count === 2 ) {
        return $names[0] . ' și ' . $names[1];
    }

    $last = array_pop( $names );
    return implode( ', ', $names ) . ' și ' . $last;
}

function teinvit_vertical_semantics_registry() {
    return [
        'wedding' => [
            'label' => 'Nuntă',
            'basic_copy' => [
                'notice' => 'Pachet Basic activ. Pentru funcționalități premium (editări nelimitate, configurare RSVP avansată și administrare cadouri), cumpără addon-ul Pachet Premium.',
                'deadline_locked' => 'Publicarea datei limită pe pagina invitaților este disponibilă după upgrade la Premium.',
                'publish_locked' => 'Publicarea de versiuni este disponibilă după upgrade la Premium.',
                'guest_page_locked' => 'Pagina personalizată a invitaților tăi este disponibilă după upgrade la Premium.',
                'content_locked' => 'Editările de conținut și salvarea versiunilor sunt blocate pe pachetul Basic.',
                'gifts_locked' => 'Activarea listei de Cadouri pe pagina invitaților este disponibilă doar pentru pachetul Premium',
                'rsvp_locked' => 'Configurările RSVP avansate sunt disponibile după upgrade la Premium.',
            ],
            'rsvp_defaults' => [
                'show_attending_civil' => 1,
                'show_attending_religious' => 1,
                'show_attending_party' => 1,
                'show_attending_people_count' => 1,
                'show_kids' => 0,
                'show_accommodation' => 0,
                'show_vegetarian' => 0,
                'show_allergies' => 0,
                'show_message' => 1,
                'show_rsvp_deadline' => 0,
                'rsvp_deadline_text' => '',
                'rsvp_deadline_date' => '',
                'show_gifts_section' => 0,
                'gifts_extra_slots' => 0,
            ],
            'rsvp_fields' => [
                'show_attending_civil' => [ 'label' => 'Permite confirmarea pentru cununia civilă', 'question' => 'Veți participa la cununia civilă?', 'storage' => 'attending_civil', 'type' => 'boolean' ],
                'show_attending_religious' => [ 'label' => 'Permite confirmarea pentru ceremonia religioasă', 'question' => 'Veți participa la ceremonia religioasă?', 'storage' => 'attending_religious', 'type' => 'boolean' ],
                'show_attending_party' => [ 'label' => 'Permite confirmarea pentru petrecere', 'question' => 'Veți participa la petrecere?', 'storage' => 'attending_party', 'type' => 'boolean' ],
                'show_kids' => [ 'label' => 'Permite confirmarea copiilor', 'question' => 'Veți veni însoțiți de copii?', 'storage' => 'kids_count', 'type' => 'count' ],
                'show_accommodation' => [ 'label' => 'Permite solicitarea de cazare', 'question' => 'Aveți nevoie de cazare?', 'storage' => 'accommodation_people_count', 'type' => 'count' ],
                'show_vegetarian' => [ 'label' => 'Permite selectarea meniului vegetarian', 'question' => 'Doriți meniu vegetarian?', 'storage' => 'vegetarian_menus_count', 'type' => 'count' ],
                'show_allergies' => [ 'label' => 'Permite menționarea alergiilor', 'question' => 'Aveți alergii alimentare?', 'storage' => 'allergy_details', 'type' => 'text' ],
                'show_message' => [ 'label' => 'Permite trimiterea unui mesaj către miri', 'question' => 'Doriți să transmiteți un mesaj mirilor?', 'storage' => 'message_to_couple', 'type' => 'textarea' ],
            ],
            'report_fields' => [
                'unique_confirmations', 'submissions_count', 'attending_civil', 'attending_religious', 'attending_party', 'adults', 'children', 'accommodation', 'vegetarian_menus', 'allergies', 'messages',
            ],
            'gift_labels' => [
                'title' => 'Lista de cadouri',
                'subtitle' => 'Alege un cadou pe care dorești să îl rezervi pentru miri.',
            ],
            'share' => [
                'fallback_message' => 'Te invităm cu drag la evenimentul nostru! Vezi invitația aici:',
                'fallback_title' => 'Invitația noastră - Te Invit',
            ],
        ],
        'birthday' => [
            'label' => 'Aniversare',
            'basic_copy' => [
                'notice' => 'Pachet Basic activ. Pentru funcționalități premium (editări nelimitate, configurare RSVP avansată și administrare cadouri), cumpără addon-ul Pachet Premium.',
                'deadline_locked' => 'Informațiile pot fi publicate pe pagina invitaților doar după upgrade la Premium.',
                'publish_locked' => 'Publicarea de versiuni este disponibilă după upgrade la Premium.',
                'guest_page_locked' => 'Pagina personalizată a invitaților tăi este disponibilă după upgrade la Premium.',
                'content_locked' => 'Editările de conținut și salvarea versiunilor sunt blocate pe pachetul Basic.',
                'gifts_locked' => 'Activarea listei de Cadouri pe pagina invitaților este disponibilă doar pentru pachetul Premium',
                'rsvp_locked' => 'Configurările RSVP avansate sunt disponibile după upgrade la Premium.',
            ],
            'free_text_fields' => [
                'birthday_party_theme_text' => [
                    'enabled_key' => 'show_birthday_party_theme',
                    'label' => 'Afișează tematica petrecerii',
                    'public_label' => 'Tematica petrecerii',
                ],
                'birthday_dress_code_text' => [
                    'enabled_key' => 'show_birthday_dress_code',
                    'label' => 'Afișează dress code / ținută recomandată',
                    'public_label' => 'Dress code',
                ],
            ],
            'rsvp_defaults' => [
                'show_rsvp_deadline' => 0,
                'rsvp_deadline_text' => '',
                'rsvp_deadline_date' => '',
                'show_birthday_party_theme' => 0,
                'birthday_party_theme_text' => '',
                'show_birthday_dress_code' => 0,
                'birthday_dress_code_text' => '',
                'show_attending_party' => 1,
                'show_guest_count' => 1,
                'show_attending_people_count' => 1,
                'show_kids' => 0,
                'show_child_menu' => 0,
                'show_accommodation' => 0,
                'show_vegetarian' => 0,
                'show_allergies' => 0,
                'show_message' => 1,
                'show_special_observations' => 0,
                'show_gifts_section' => 0,
                'gifts_extra_slots' => 0,
            ],
            'rsvp_fields' => [
                'show_attending_party' => [ 'label' => 'Permite confirmarea participării la petrecere', 'question' => 'Veți participa la petrecere?', 'storage' => 'attending_party', 'type' => 'boolean' ],
                'show_guest_count' => [ 'label' => 'Permite completarea numărului de persoane participante', 'question' => 'Pentru câte persoane faceți confirmarea (exceptând copii)?', 'storage' => 'attending_people_count', 'type' => 'count' ],
                'show_kids' => [ 'label' => 'Permite confirmarea copiilor însoțitori', 'question' => 'Veți veni însoțiți de copii?', 'storage' => 'kids_count', 'type' => 'count' ],
                'show_child_menu' => [ 'label' => 'Permite solicitarea meniului pentru copii', 'question' => 'Aveți nevoie de meniu pentru copil/copii?', 'storage' => 'child_menu_count', 'type' => 'extra_count' ],
                'show_accommodation' => [ 'label' => 'Permite solicitarea de cazare', 'question' => 'Aveți nevoie de cazare?', 'storage' => 'accommodation_people_count', 'type' => 'count' ],
                'show_vegetarian' => [ 'label' => 'Permite selectarea meniului vegetarian', 'question' => 'Doriți meniu vegetarian?', 'storage' => 'vegetarian_menus_count', 'type' => 'count' ],
                'show_allergies' => [ 'label' => 'Permite menționarea alergiilor', 'question' => 'Aveți alergii alimentare?', 'storage' => 'allergy_details', 'type' => 'text' ],
                'show_message' => [ 'label' => 'Permite trimiterea unui mesaj către sărbătorit/sărbătoriți', 'question' => 'Doriți să transmiteți un mesaj sărbătoritului/sărbătoriților?', 'storage' => 'message_to_celebrants', 'type' => 'extra_textarea' ],
                'show_special_observations' => [ 'label' => 'Permite completarea observațiilor speciale', 'question' => 'Aveți observații speciale pentru organizator?', 'storage' => 'special_observations', 'type' => 'extra_textarea' ],
            ],
            'report_fields' => [
                'unique_confirmations', 'attending_people_count', 'attending_party', 'adults', 'children', 'child_menu_count', 'vegetarian_menus_count', 'allergies', 'accommodation', 'messages', 'special_observations',
            ],
            'gift_labels' => [
                'title' => 'Lista de cadouri',
                'subtitle' => 'Alege un cadou pe care dorești să îl rezervi pentru sărbătorit/sărbătoriți.',
            ],
            'share' => [
                'fallback_message' => 'Te invităm cu drag la petrecerea aniversară',
                'fallback_title' => 'Invitație aniversare - Te Invit',
            ],
        ],
        'baptism' => [
            'label' => 'Botez',
            'basic_copy' => [
                'notice' => 'Pachet Basic activ. Pentru funcționalități premium (editări nelimitate, configurare RSVP avansată și administrare cadouri), cumpără addon-ul Pachet Premium.',
                'deadline_locked' => 'Publicarea datei limită pe pagina invitaților este disponibilă după upgrade la Premium.',
                'publish_locked' => 'Publicarea de versiuni este disponibilă după upgrade la Premium.',
                'guest_page_locked' => 'Pagina personalizată a invitaților tăi este disponibilă după upgrade la Premium.',
                'content_locked' => 'Editările de conținut și salvarea versiunilor sunt blocate pe pachetul Basic.',
                'gifts_locked' => 'Activarea listei de Cadouri pe pagina invitaților este disponibilă doar pentru pachetul Premium',
                'rsvp_locked' => 'Configurările RSVP avansate sunt disponibile după upgrade la Premium.',
            ],
            'rsvp_defaults' => [
                'show_rsvp_deadline' => 0,
                'rsvp_deadline_text' => '',
                'rsvp_deadline_date' => '',
                'show_attending_religious' => 1,
                'show_attending_party' => 1,
                'show_attending_people_count' => 1,
                'show_kids' => 0,
                'show_child_menu' => 0,
                'show_child_seat' => 0,
                'show_transport' => 0,
                'show_accommodation' => 0,
                'show_vegetarian' => 0,
                'show_allergies' => 0,
                'show_message' => 1,
                'show_special_observations' => 0,
                'show_gifts_section' => 0,
                'gifts_extra_slots' => 0,
            ],
            'rsvp_fields' => [
                'show_attending_religious' => [ 'label' => 'Permite confirmarea participării la slujba de botez', 'question' => 'Veți participa la slujba de botez?', 'storage' => 'attending_religious', 'type' => 'boolean' ],
                'show_attending_party' => [ 'label' => 'Permite confirmarea participării la petrecerea de botez', 'question' => 'Veți participa la petrecerea de botez?', 'storage' => 'attending_party', 'type' => 'boolean' ],
                'show_attending_people_count' => [ 'label' => 'Permite completarea numărului de persoane participante', 'question' => 'Pentru câte persoane confirmați participarea?', 'storage' => 'attending_people_count', 'type' => 'count' ],
                'show_kids' => [ 'label' => 'Permite confirmarea copiilor însoțitori', 'question' => 'Veți veni însoțiți de copii?', 'storage' => 'kids_count', 'type' => 'count' ],
                'show_child_menu' => [ 'label' => 'Permite solicitarea meniului pentru copii', 'question' => 'Aveți nevoie de meniu pentru copil/copii?', 'storage' => 'child_menu_count', 'type' => 'extra_count' ],
                'show_child_seat' => [ 'label' => 'Permite solicitarea unui scaun pentru copil', 'question' => 'Aveți nevoie de scaun pentru copil?', 'storage' => 'child_seat_count', 'type' => 'extra_count' ],
                'show_transport' => [ 'label' => 'Permite solicitarea de transport între biserică și restaurant', 'question' => 'Aveți nevoie de transport între biserică și restaurant?', 'storage' => 'transport_people_count', 'type' => 'extra_count' ],
                'show_accommodation' => [ 'label' => 'Permite solicitarea de cazare', 'question' => 'Aveți nevoie de cazare?', 'storage' => 'accommodation_people_count', 'type' => 'count' ],
                'show_vegetarian' => [ 'label' => 'Permite selectarea meniului vegetarian', 'question' => 'Doriți meniu vegetarian?', 'storage' => 'vegetarian_menus_count', 'type' => 'count' ],
                'show_allergies' => [ 'label' => 'Permite menționarea alergiilor', 'question' => 'Aveți alergii alimentare?', 'storage' => 'allergy_details', 'type' => 'text' ],
                'show_message' => [ 'label' => 'Permite trimiterea unui mesaj către familie', 'question' => 'Doriți să transmiteți un mesaj familiei?', 'storage' => 'message_to_family', 'type' => 'extra_textarea' ],
                'show_special_observations' => [ 'label' => 'Permite completarea observațiilor speciale', 'question' => 'Aveți observații speciale pentru organizator?', 'storage' => 'special_observations', 'type' => 'extra_textarea' ],
            ],
            'report_fields' => [
                'unique_confirmations', 'attending_people_count', 'attending_religious', 'attending_party', 'adults', 'children', 'child_menu_count', 'child_seat_count', 'transport_people_count', 'accommodation', 'vegetarian_menus_count', 'allergies', 'messages', 'special_observations',
            ],
            'gift_labels' => [
                'title' => 'Lista de cadouri',
                'subtitle' => 'Alege un cadou pe care dorești să îl rezervi pentru copil/copii.',
            ],
            'share' => [
                'fallback_message' => 'Te invităm cu drag la botez',
                'fallback_title' => 'Invitație botez - Te Invit',
                'title' => 'Te invităm la botez',
            ],
        ],
    ];
}

function teinvit_vertical_semantics( $vertical_key ) {
    $vertical_key = function_exists( 'teinvit_normalize_vertical_key' ) ? teinvit_normalize_vertical_key( $vertical_key ) : 'wedding';
    $registry = teinvit_vertical_semantics_registry();

    return isset( $registry[ $vertical_key ] ) ? $registry[ $vertical_key ] : $registry['wedding'];
}

function teinvit_default_rsvp_config_for_vertical( $vertical_key ) {
    $semantics = teinvit_vertical_semantics( $vertical_key );
    $defaults = isset( $semantics['rsvp_defaults'] ) && is_array( $semantics['rsvp_defaults'] ) ? $semantics['rsvp_defaults'] : [];

    return $defaults;
}

function teinvit_vertical_rsvp_fields( $vertical_key ) {
    $semantics = teinvit_vertical_semantics( $vertical_key );
    return isset( $semantics['rsvp_fields'] ) && is_array( $semantics['rsvp_fields'] ) ? $semantics['rsvp_fields'] : [];
}

function teinvit_vertical_report_fields( $vertical_key ) {
    $semantics = teinvit_vertical_semantics( $vertical_key );
    return isset( $semantics['report_fields'] ) && is_array( $semantics['report_fields'] ) ? $semantics['report_fields'] : [];
}

function teinvit_vertical_gift_labels( $vertical_key ) {
    $semantics = teinvit_vertical_semantics( $vertical_key );
    return isset( $semantics['gift_labels'] ) && is_array( $semantics['gift_labels'] ) ? $semantics['gift_labels'] : [];
}

function teinvit_vertical_basic_copy( $vertical_key ) {
    $semantics = teinvit_vertical_semantics( $vertical_key );
    return isset( $semantics['basic_copy'] ) && is_array( $semantics['basic_copy'] ) ? $semantics['basic_copy'] : [];
}

function teinvit_vertical_share_payload( $vertical_key, array $invitation = [], $url = '' ) {
    $vertical_key = function_exists( 'teinvit_normalize_vertical_key' ) ? teinvit_normalize_vertical_key( $vertical_key ) : 'wedding';
    $semantics = teinvit_vertical_semantics( $vertical_key );
    $share = isset( $semantics['share'] ) && is_array( $semantics['share'] ) ? $semantics['share'] : [];
    $url = esc_url_raw( (string) $url );

    if ( $vertical_key === 'birthday' ) {
        $names = isset( $invitation['celebrants'] ) && is_array( $invitation['celebrants'] ) ? $invitation['celebrants'] : [];
        $joined_names = teinvit_join_ro_names( $names );
        $count = count( array_filter( $names ) );
        $event_name = '';
        if ( isset( $invitation['event_name'] ) && is_array( $invitation['event_name'] ) && ! empty( $invitation['event_name']['enabled'] ) ) {
            $event_name = trim( (string) ( $invitation['event_name']['value'] ?? $invitation['event_name']['line'] ?? '' ) );
        }

        if ( $joined_names !== '' ) {
            if ( $event_name !== '' ) {
                $message = $joined_names . ' te invită la ' . $event_name;
                $title = $count <= 1 ? ( 'Te invit la ' . $event_name ) : ( 'Te invităm la ' . $event_name );
            } else {
                $message = $joined_names . ( $count <= 1 ? ' te invită la aniversarea sa' : ' te invită la aniversarea lor' );
                $title = $count <= 1 ? 'Te invit la aniversarea mea' : 'Te invităm la aniversarea noastră';
            }
        } else {
            $message = (string) ( $share['fallback_message'] ?? 'Te invităm cu drag la petrecerea aniversară' );
            $title = (string) ( $share['fallback_title'] ?? 'Invitație aniversare - Te Invit' );
        }

        return [
            'title' => $title,
            'message' => trim( $message . ( $url !== '' ? ' ' . $url : '' ) ),
            'text' => $message,
            'url' => $url,
        ];
    }

    if ( $vertical_key === 'baptism' ) {
        $children = isset( $invitation['children'] ) && is_array( $invitation['children'] ) ? $invitation['children'] : [];
        $joined_children = teinvit_join_ro_names( $children );
        $text = $joined_children !== ''
            ? 'Te invităm cu drag la botez, alături de ' . $joined_children
            : (string) ( $share['fallback_message'] ?? 'Te invităm cu drag la botez' );

        return [
            'title' => (string) ( $share['title'] ?? 'Te invităm la botez' ),
            'message' => trim( $text . ( $url !== '' ? ' ' . $url : '' ) ),
            'text' => $text,
            'url' => $url,
        ];
    }

    $text = (string) ( $share['fallback_message'] ?? 'Te invităm cu drag la evenimentul nostru! Vezi invitația aici:' );
    return [
        'title' => (string) ( $share['fallback_title'] ?? 'Invitația noastră - Te Invit' ),
        'message' => trim( $text . ( $url !== '' ? ' ' . $url : '' ) ),
        'text' => $text,
        'url' => $url,
    ];
}

function teinvit_render_vertical_admin_client_foundation( $token, $vertical_key, $order = null ) {
    $token = sanitize_text_field( (string) $token );
    $vertical_key = function_exists( 'teinvit_normalize_vertical_key' ) ? teinvit_normalize_vertical_key( $vertical_key ) : 'wedding';
    $semantics = teinvit_vertical_semantics( $vertical_key );
    $label = (string) ( $semantics['label'] ?? ucfirst( $vertical_key ) );
    $copy = teinvit_vertical_basic_copy( $vertical_key );
    $caps = function_exists( 'teinvit_capabilities_for_token' ) ? teinvit_capabilities_for_token( $token ) : [ 'state' => 'premium_native' ];
    $state = (string) ( $caps['state'] ?? 'premium_native' );
    $upgrade_url = add_query_arg( [ 'teinvit_buy_premium_upgrade_token' => $token ], home_url( '/' ) );
    $active = function_exists( 'teinvit_get_active_snapshot' ) ? teinvit_get_active_snapshot( $token ) : null;
    $snapshot = $active && ! empty( $active['snapshot'] ) ? json_decode( (string) $active['snapshot'], true ) : [];
    $invitation = isset( $snapshot['invitation'] ) && is_array( $snapshot['invitation'] ) ? $snapshot['invitation'] : [];
    $preview_html = '';

    if ( ! empty( $invitation ) && function_exists( 'teinvit_render_invitation_html_for_vertical' ) ) {
        $product_id = 0;
        if ( class_exists( 'WC_Order' ) && $order instanceof WC_Order && function_exists( 'teinvit_get_order_primary_product_id' ) ) {
            $product_id = (int) teinvit_get_order_primary_product_id( $order );
        }
        $preview_html = teinvit_render_invitation_html_for_vertical( $vertical_key, $invitation, $order, 'preview', $product_id );
    }

    echo '<div class="teinvit-admin-page teinvit-admin-foundation teinvit-admin-foundation-' . esc_attr( $vertical_key ) . '" style="max-width:1100px;margin:20px auto;padding:16px;">';
    echo '<div class="teinvit-zone" style="border:1px solid #e5e5e5;padding:14px;border-radius:8px;background:#fff;margin:16px 0;">';
    echo '<h1 style="text-align:center;margin-top:0;">Administrare invitație - ' . esc_html( $label ) . '</h1>';

    if ( $state === 'basic_pure' ) {
        echo '<div class="notice notice-warning" style="padding:10px;">';
        echo '<p><strong>Pachet Basic activ.</strong> ' . esc_html( preg_replace( '/^Pachet Basic activ\.\s*/', '', (string) ( $copy['notice'] ?? '' ) ) ) . '</p>';
        if ( ! empty( $caps['can_buy_premium_upgrade'] ) ) {
            echo '<p><a href="' . esc_url( $upgrade_url ) . '" class="button button-primary">Upgrade la Premium</a></p>';
        }
        echo '</div>';
    }

    echo '<p>Această verticală are fundația /admin-client activată. UI-ul complet pentru editări, RSVP, rapoarte și cadouri se activează incremental în Faza 5.2.</p>';
    echo '</div>';

    if ( $preview_html !== '' ) {
        echo '<div class="teinvit-zone" style="border:1px solid #e5e5e5;padding:14px;border-radius:8px;background:#fff;margin:16px 0;">';
        echo $preview_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo '</div>';
    }

    if ( $state === 'basic_pure' ) {
        echo '<div class="teinvit-zone" style="border:1px solid #e5e5e5;padding:14px;border-radius:8px;background:#fff;margin:16px 0;">';
        echo '<p><em>' . esc_html( (string) ( $copy['guest_page_locked'] ?? '' ) ) . '</em></p>';
        echo '<p><em>' . esc_html( (string) ( $copy['content_locked'] ?? '' ) ) . '</em></p>';
        echo '<p><em>' . esc_html( (string) ( $copy['gifts_locked'] ?? '' ) ) . '</em></p>';
        echo '</div>';
    }

    echo '</div>';
}

function teinvit_render_vertical_invitati_foundation( $token, $vertical_key, $order = null, array $invitation = [], $preview_html = '' ) {
    $token = sanitize_text_field( (string) $token );
    $vertical_key = function_exists( 'teinvit_normalize_vertical_key' ) ? teinvit_normalize_vertical_key( $vertical_key ) : 'wedding';
    $semantics = teinvit_vertical_semantics( $vertical_key );
    $label = (string) ( $semantics['label'] ?? ucfirst( $vertical_key ) );

    if ( $preview_html === '' && ! empty( $invitation ) && function_exists( 'teinvit_render_invitation_html_for_vertical' ) ) {
        $product_id = 0;
        if ( class_exists( 'WC_Order' ) && $order instanceof WC_Order && function_exists( 'teinvit_get_order_primary_product_id' ) ) {
            $product_id = (int) teinvit_get_order_primary_product_id( $order );
        }
        $preview_html = teinvit_render_invitation_html_for_vertical( $vertical_key, $invitation, $order, 'preview', $product_id );
    }

    echo '<div class="teinvit-invitati-foundation teinvit-invitati-foundation-' . esc_attr( $vertical_key ) . '" style="max-width:980px;margin:0 auto;">';
    if ( $preview_html !== '' ) {
        echo '<div class="teinvit-slot teinvit-slot-preview" data-teinvit-slot="preview">';
        echo $preview_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo '</div>';
    }
    echo '<div class="teinvit-surface-card teinvit-rsvp-card" style="background:#fff;border:1px solid rgba(0,0,0,.08);border-radius:14px;padding:18px;margin-top:16px;">';
    echo '<h3 style="text-align:center;margin-top:0;">Confirmări invitați - ' . esc_html( $label ) . '</h3>';
    echo '<p style="text-align:center;">Formularul RSVP pentru această verticală este pregătit la nivel de storage/registry și va fi activat într-un pas ulterior.</p>';
    echo '</div>';
    echo '</div>';
}
