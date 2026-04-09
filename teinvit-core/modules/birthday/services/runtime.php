<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function teinvit_birthday_field_ids() {
    return [
        'celebrants' => 'd1fe0da',
        'message' => 'bef895a',
        'show_party' => 'fc5b530',
        'party_location' => '0c45e7b',
        'party_date' => '1d485ae',
        'party_time' => 'baee2f0',
        'party_waze' => 'a2be7ee',
        'theme' => '4445eae',
    ];
}

function teinvit_birthday_payload_builder( array $context = [] ) {
    $order = isset( $context['order'] ) && $context['order'] instanceof WC_Order ? $context['order'] : null;
    if ( ! $order ) {
        return [ 'invitation' => [], 'wapf_fields' => [] ];
    }

    $ids = teinvit_birthday_field_ids();
    $wapf = teinvit_extract_order_wapf_field_map( $order );
    $val = static function( $key ) use ( $wapf, $ids ) {
        $id = isset( $ids[ $key ] ) ? $ids[ $key ] : '';
        return $id !== '' && isset( $wapf[ $id ] ) ? trim( (string) $wapf[ $id ] ) : '';
    };

    $celebrants = array_values( array_filter( array_map( 'trim', preg_split( '/\s*,\s*/', $val( 'celebrants' ) ) ) ) );
    $celebrants = array_slice( $celebrants, 0, 4 );
    $show_party_raw = strtolower( $val( 'show_party' ) );
    $show_party = $show_party_raw !== '' && ! in_array( $show_party_raw, [ '0', 'false', 'off', 'no' ], true );

    $theme = function_exists( 'teinvit_resolve_theme_key_from_wapf_value' ) ? teinvit_resolve_theme_key_from_wapf_value( $val( 'theme' ) ) : 'editorial';

    $date = $val( 'party_date' );
    $time = $val( 'party_time' );
    $datetime = $date !== '' ? ( $date . ( $time !== '' ? ' ora ' . $time : '' ) ) : '';

    return [
        'invitation' => [
            'vertical' => 'birthday',
            'theme' => $theme,
            'model_key' => 'invn01',
            'celebrants' => $celebrants,
            'headline' => implode( ' și ', $celebrants ),
            'message' => $val( 'message' ),
            'events' => [
                'party' => [
                    'enabled' => $show_party,
                    'title' => 'Petrecere',
                    'loc' => $val( 'party_location' ),
                    'date' => $datetime,
                    'waze' => $val( 'party_waze' ),
                ],
            ],
        ],
        'wapf_fields' => $wapf,
    ];
}

function teinvit_birthday_renderer( array $context = [] ) {
    $invitation = isset( $context['invitation'] ) && is_array( $context['invitation'] ) ? $context['invitation'] : [];
    $order = isset( $context['order'] ) && $context['order'] instanceof WC_Order ? $context['order'] : null;
    $is_pdf = ( isset( $context['render_context'] ) && $context['render_context'] === 'pdf' );

    $product_id = 0;
    if ( $order ) {
        $items = $order->get_items();
        if ( ! empty( $items ) ) {
            $item = reset( $items );
            $product_id = $item ? (int) $item->get_product_id() : 0;
        }
    }

    $background_url = function_exists( 'teinvit_get_product_background_url' ) ? teinvit_get_product_background_url( $product_id ) : '';
    $theme_class = function_exists( 'teinvit_theme_class_from_key' ) ? teinvit_theme_class_from_key( $invitation['theme'] ?? 'editorial' ) : 'theme-editorial-luxury';
    $party = isset( $invitation['events']['party'] ) && is_array( $invitation['events']['party'] ) ? $invitation['events']['party'] : [];

    $html = '<div class="teinvit-wedding teinvit-birthday"><div class="teinvit-page"><div class="teinvit-container"><div class="teinvit-preview">';
    if ( $background_url ) {
        $html .= '<img src="' . esc_url( $background_url ) . '" alt="" class="teinvit-bg" draggable="false">';
    }

    $html .= '<div class="teinvit-canvas canvas--spread ' . esc_attr( $theme_class ) . '">';
    $html .= '<div class="inv-names">' . esc_html( (string) ( $invitation['headline'] ?? '' ) ) . '</div>';
    $html .= '<div class="inv-divider" aria-hidden="true"></div>';
    $html .= '<div class="inv-message">' . esc_html( (string) ( $invitation['message'] ?? '' ) ) . '</div>';

    if ( ! empty( $party['enabled'] ) ) {
        $html .= '<div class="inv-events"><div class="events-row top">';
        $html .= '<div class="inv-event"><strong>' . esc_html( (string) ( $party['title'] ?? 'Petrecere' ) ) . '</strong>';
        $html .= '<div>' . esc_html( (string) ( $party['loc'] ?? '' ) ) . '</div>';
        $html .= '<div>' . esc_html( (string) ( $party['date'] ?? '' ) ) . '</div>';
        if ( ! empty( $party['waze'] ) ) {
            $html .= '<a href="' . esc_url( (string) $party['waze'] ) . '" target="_blank" rel="noopener">Waze</a>';
        }
        $html .= '</div></div><div class="events-row bottom"></div></div>';
    }

    $html .= '</div></div></div></div></div>';

    static $assets_loaded = false;
    if ( ! $assets_loaded ) {
        $assets_loaded = true;
        $html = '<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600&family=Source+Serif+4:wght@400&family=Raleway:wght@600&family=Parisienne&family=Crimson+Text:wght@400;600&family=DM+Sans:wght@600&family=Inter:wght@400;600&display=swap">'
            . '<link rel="stylesheet" href="' . esc_url( TEINVIT_WEDDING_MODULE_URL . ( $is_pdf ? 'preview/pdf.css' : 'preview/preview.css' ) ) . '">' . $html;
    }

    return $html;
}
