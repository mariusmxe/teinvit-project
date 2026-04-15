<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function teinvit_birthday_field_ids() {
    return [
        'show_age' => '2cac251',
        'age' => '4e73bc1',
        'show_event_name' => '1aa14a1',
        'event_name' => 'cb7c1fd',
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

function teinvit_birthday_theme_value_map() {
    $map = [];
    foreach ( teinvit_birthday_theme_catalog() as $key => $entry ) {
        $slug = trim( (string) ( $entry['slug'] ?? '' ) );
        $label = trim( (string) ( $entry['label'] ?? '' ) );
        if ( $slug !== '' ) {
            $map[ $slug ] = $key;
        }
        if ( $label !== '' ) {
            $map[ strtolower( $label ) ] = $key;
        }
    }
    return $map;
}

function teinvit_birthday_resolve_theme_key( $raw_theme ) {
    $raw_theme = trim( (string) $raw_theme );
    $raw_theme_lower = strtolower( $raw_theme );
    $map = teinvit_birthday_theme_value_map();
    if ( $raw_theme !== '' && isset( $map[ $raw_theme ] ) ) {
        return $map[ $raw_theme ];
    }
    if ( $raw_theme_lower !== '' && isset( $map[ $raw_theme_lower ] ) ) {
        return $map[ $raw_theme_lower ];
    }
    if ( $raw_theme_lower !== '' ) {
        $normalized = preg_replace( '/[^a-z0-9]+/i', '-', $raw_theme_lower );
        $normalized = trim( (string) $normalized, '-' );
        if ( $normalized !== '' && isset( teinvit_birthday_theme_catalog()[ $normalized ] ) ) {
            return $normalized;
        }
    }

    return 'editorial-luxury';
}

function teinvit_birthday_theme_class( $theme_key ) {
    $theme_key = strtolower( trim( (string) $theme_key ) );
    $catalog = teinvit_birthday_theme_catalog();
    if ( ! isset( $catalog[ $theme_key ] ) ) {
        $theme_key = 'editorial-luxury';
    }
    return 'theme-birthday-' . sanitize_html_class( $theme_key );
}

function teinvit_birthday_theme_catalog() {
    return [
        'editorial-luxury' => [ 'slug' => '58a6u', 'label' => 'Editorial Luxury', 'shared' => 'editorial' ],
        'romantic-floral' => [ 'slug' => 'trs1l', 'label' => 'Romantic Floral', 'shared' => 'romantic' ],
        'modern-minimal' => [ 'slug' => 'pu7cd', 'label' => 'Modern Minimal', 'shared' => 'modern' ],
        'classic-elegant' => [ 'slug' => 'h1ww0', 'label' => 'Classic Elegant', 'shared' => 'classic' ],
        'playful-confetti' => [ 'slug' => '761q4', 'label' => 'Playful Confetti', 'shared' => 'editorial' ],
        'candy-pastel' => [ 'slug' => 'diqh7', 'label' => 'Candy Pastel', 'shared' => 'romantic' ],
        'storybook-dream' => [ 'slug' => 'm76bw', 'label' => 'Storybook Dream', 'shared' => 'romantic' ],
        'balloon-party' => [ 'slug' => 'v79ej', 'label' => 'Balloon Party', 'shared' => 'modern' ],
        'golden-celebration' => [ 'slug' => 'i5ldk', 'label' => 'Golden Celebration', 'shared' => 'classic' ],
        'chic-blush' => [ 'slug' => 'ftv53', 'label' => 'Chic Blush', 'shared' => 'romantic' ],
        'midnight-glam' => [ 'slug' => '5pedl', 'label' => 'Midnight Glam', 'shared' => 'modern' ],
        'botanical-grace' => [ 'slug' => '8f3yw', 'label' => 'Botanical Grace', 'shared' => 'editorial' ],
        'royal-blue' => [ 'slug' => 'b1lnh', 'label' => 'Royal Blue', 'shared' => 'modern' ],
        'velvet-noir' => [ 'slug' => '8781i', 'label' => 'Velvet Noir', 'shared' => 'classic' ],
        'sunset-fiesta' => [ 'slug' => 'mwftw', 'label' => 'Sunset Fiesta', 'shared' => 'romantic' ],
    ];
}

function teinvit_birthday_payload_from_wapf_map( array $wapf, array $context = [] ) {
    $ids = teinvit_birthday_field_ids();
    $product_id = isset( $context['product_id'] ) ? (int) $context['product_id'] : 0;
    $defs = $product_id > 0 && function_exists( 'teinvit_get_wapf_defs_for_product' ) ? teinvit_get_wapf_defs_for_product( $product_id ) : [];

    $label_lookup = [];
    foreach ( $defs as $def ) {
        if ( ! is_array( $def ) ) {
            continue;
        }
        $id = function_exists( 'teinvit_normalize_wapf_field_id' ) ? teinvit_normalize_wapf_field_id( $def['id'] ?? '' ) : trim( (string) ( $def['id'] ?? '' ) );
        $label = strtolower( trim( (string) ( $def['label'] ?? '' ) ) );
        if ( $id === '' || $label === '' ) {
            continue;
        }
        $label_lookup[ $id ] = $label;
    }

    $fallback_labels = [
        'show_age' => [ 'dorești afișarea vârstei ?', 'doresti afisarea varstei ?' ],
        'age' => [ 'completează vârsta', 'completeaza varsta' ],
        'show_event_name' => [ 'afișează numele evenimentului', 'afiseaza numele evenimentului' ],
        'event_name' => [ 'completează numele evenimentului', 'completeaza numele evenimentului' ],
        'celebrants' => [ 'nume sărbătorit', 'nume sarbatorit' ],
        'show_party' => [ 'afișează locația petrecerii', 'afiseaza locatia petrecerii' ],
        'party_location' => [ 'denumire locației', 'denumire locatiei' ],
        'party_date' => [ 'data' ],
        'party_time' => [ 'ora' ],
        'party_waze' => [ 'link waze' ],
        'theme' => [ 'alege stilul invitației', 'alege stilul invitatiei' ],
    ];

    $resolve_ids = static function( $key ) use ( $ids, $label_lookup, $fallback_labels ) {
        $resolved = [];
        if ( isset( $ids[ $key ] ) && $ids[ $key ] !== '' ) {
            $resolved[] = (string) $ids[ $key ];
        }
        if ( ! isset( $fallback_labels[ $key ] ) ) {
            return $resolved;
        }

        $needles = $fallback_labels[ $key ];
        foreach ( $label_lookup as $id => $label ) {
            foreach ( $needles as $needle ) {
                if ( strpos( $label, strtolower( $needle ) ) !== false ) {
                    $resolved[] = (string) $id;
                    break;
                }
            }
        }

        return array_values( array_unique( array_filter( $resolved ) ) );
    };

    $collect_matches_for_ids = static function( array $candidate_ids ) use ( $wapf ) {
        $matches = [];
        foreach ( $wapf as $field_id => $raw_value ) {
            $field_id = trim( (string) $field_id );
            foreach ( $candidate_ids as $id ) {
                if ( $field_id === $id || strpos( $field_id, $id . '_' ) === 0 || strpos( $field_id, $id . '-' ) === 0 ) {
                    $parts = array_values( array_filter( array_map( 'trim', explode( ',', (string) $raw_value ) ) ) );
                    if ( ! empty( $parts ) ) {
                        $matches = array_merge( $matches, $parts );
                    }
                    break;
                }
            }
        }
        return $matches;
    };

    $val = static function( $key ) use ( $ids, $resolve_ids, $collect_matches_for_ids ) {
        $explicit_id = isset( $ids[ $key ] ) ? trim( (string) $ids[ $key ] ) : '';
        if ( $explicit_id !== '' ) {
            $matches = $collect_matches_for_ids( [ $explicit_id ] );
            if ( ! empty( $matches ) ) {
                return implode( ', ', array_values( array_unique( $matches ) ) );
            }
        }

        $candidate_ids = array_values( array_filter( $resolve_ids( $key ), static function( $id ) use ( $explicit_id ) {
            return $id !== '' && $id !== $explicit_id;
        } ) );
        if ( empty( $candidate_ids ) ) {
            return '';
        }

        $matches = $collect_matches_for_ids( $candidate_ids );

        if ( empty( $matches ) ) {
            return '';
        }

        return implode( ', ', array_values( array_unique( $matches ) ) );
    };
    $has = static function( $key ) use ( $val ) {
        $raw = strtolower( trim( (string) $val( $key ) ) );
        return $raw !== '' && ! in_array( $raw, [ '0', 'false', 'off', 'no' ], true );
    };

    $celebrants = array_values( array_filter( array_map( 'trim', preg_split( '/\s*,\s*/', $val( 'celebrants' ) ) ), static function( $name ) {
        $name = trim( (string) $name );
        if ( $name === '' ) {
            return false;
        }
        if ( preg_match( '/^\d+$/', $name ) ) {
            return false;
        }
        return true;
    } ) );
    $celebrants = array_slice( $celebrants, 0, 4 );
    $show_party = $has( 'show_party' );
    $theme = teinvit_birthday_resolve_theme_key( $val( 'theme' ) );

    $date = $val( 'party_date' );
    $time = $val( 'party_time' );
    $datetime = $date !== '' ? ( $date . ( $time !== '' ? ' ora ' . $time : '' ) ) : '';
    $weekday = '';
    if ( preg_match( '/^(\d{2})-(\d{2})-(\d{4})$/', $date, $m ) ) {
        $dt = DateTime::createFromFormat( 'd-m-Y', $m[1] . '-' . $m[2] . '-' . $m[3] );
        if ( $dt ) {
            $weekday_index = (int) $dt->format( 'w' );
            $weekday_map = [ 'duminică', 'luni', 'marți', 'miercuri', 'joi', 'vineri', 'sâmbătă' ];
            $weekday = $weekday_map[ $weekday_index ] ?? '';
        }
    }
    if ( $weekday !== '' ) {
        $weekday = function_exists( 'mb_strtoupper' ) ? mb_strtoupper( $weekday, 'UTF-8' ) : strtoupper( $weekday );
    }
    $message_raw = $val( 'message' );
    $message = function_exists( 'mb_substr' ) ? mb_substr( $message_raw, 0, 255 ) : substr( $message_raw, 0, 255 );
    $age = preg_replace( '/[^\d]/', '', (string) $val( 'age' ) );
    $event_name = trim( (string) $val( 'event_name' ) );

    $headline = '';
    $count = count( $celebrants );
    if ( $count === 1 ) {
        $headline = $celebrants[0];
    } elseif ( $count === 2 ) {
        $headline = $celebrants[0] . ' și ' . $celebrants[1];
    } elseif ( $count > 2 ) {
        $headline = implode( ' & ', array_slice( $celebrants, 0, -1 ) ) . ' și ' . $celebrants[ $count - 1 ];
    }

    return [
        'invitation' => [
            'vertical' => 'birthday',
            'theme' => $theme,
            'model_key' => 'invn01',
            'celebrants' => $celebrants,
            'name_units' => $celebrants,
            'headline' => $headline,
            'age' => [
                'enabled' => $has( 'show_age' ) && $age !== '',
                'value' => $age,
                'line' => ( $has( 'show_age' ) && $age !== '' ) ? ( 'Împlinesc ' . $age . ' de ani!' ) : '',
            ],
            'event_name' => [
                'enabled' => $has( 'show_event_name' ) && $event_name !== '',
                'value' => $event_name,
                'line' => ( $has( 'show_event_name' ) && $event_name !== '' ) ? ( ( count( $celebrants ) > 1 ? 'Te invităm la ' : 'Te invită la ' ) . $event_name ) : '',
            ],
            'message' => $message,
            'events' => [
                'party' => [
                    'enabled' => $show_party,
                    'title' => 'PETRECERE',
                    'loc' => $val( 'party_location' ),
                    'weekday' => $weekday,
                    'date' => $datetime,
                    'waze' => $val( 'party_waze' ),
                ],
            ],
        ],
        'wapf_fields' => $wapf,
    ];
}

function teinvit_birthday_payload_builder( array $context = [] ) {
    $order = isset( $context['order'] ) && $context['order'] instanceof WC_Order ? $context['order'] : null;
    if ( ! $order ) {
        return [ 'invitation' => [], 'wapf_fields' => [] ];
    }

    $wapf = teinvit_extract_order_wapf_field_map( $order );
    return teinvit_birthday_payload_from_wapf_map( $wapf );
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
    if ( $product_id <= 0 ) {
        $product_id = isset( $context['product_id'] ) ? (int) $context['product_id'] : 0;
    }

    $background_url = function_exists( 'teinvit_get_product_background_url' ) ? teinvit_get_product_background_url( $product_id ) : '';
    $theme_class = teinvit_birthday_theme_class( $invitation['theme'] ?? 'editorial-luxury' );
    $party = isset( $invitation['events']['party'] ) && is_array( $invitation['events']['party'] ) ? $invitation['events']['party'] : [];

    $html = '<div class="teinvit-wedding teinvit-birthday"><div class="teinvit-page"><div class="teinvit-container"><div class="teinvit-preview">';
    if ( $background_url ) {
        $html .= '<img src="' . esc_url( $background_url ) . '" alt="" class="teinvit-bg" draggable="false">';
    }

    $html .= '<div class="teinvit-canvas canvas--spread ' . esc_attr( $theme_class ) . '">';
    if ( ! empty( $invitation['age']['enabled'] ) ) {
        $html .= '<div class="inv-age">' . esc_html( (string) ( $invitation['age']['line'] ?? '' ) ) . '</div>';
    }
    $html .= '<div class="inv-names">' . esc_html( (string) ( $invitation['headline'] ?? '' ) ) . '</div>';
    if ( ! empty( $invitation['event_name']['enabled'] ) ) {
        $html .= '<div class="inv-event-name">' . esc_html( (string) ( $invitation['event_name']['line'] ?? '' ) ) . '</div>';
    }
    $html .= '<div class="inv-divider" aria-hidden="true"></div>';
    $html .= '<div class="inv-message">' . esc_html( (string) ( $invitation['message'] ?? '' ) ) . '</div>';

    if ( ! empty( $party['enabled'] ) ) {
        $html .= '<div class="inv-events"><div class="events-row top">';
        $html .= '<div class="inv-event"><strong>' . esc_html( (string) ( $party['title'] ?? 'PETRECERE' ) ) . '</strong>';
        $html .= '<div class="inv-place">' . esc_html( (string) ( $party['loc'] ?? '' ) ) . '</div>';
        if ( ! empty( $party['weekday'] ) ) {
            $html .= '<div class="inv-weekday">' . esc_html( (string) $party['weekday'] ) . '</div>';
        }
        $html .= '<div class="inv-datetime">' . esc_html( (string) ( $party['date'] ?? '' ) ) . '</div>';
        if ( ! empty( $party['waze'] ) ) {
            $html .= '<a href="' . esc_url( (string) $party['waze'] ) . '" target="_blank" rel="noopener">Deschide în Waze</a>';
        }
        $html .= '</div><div class="events-row bottom"></div></div>';
    }

    $html .= '</div></div></div></div></div>';
    if ( $is_pdf ) {
        $html .= '<script>window.__TEINVIT_PDF_READY__ = true;</script>';
    }

    static $assets_loaded = false;
    if ( ! $assets_loaded ) {
        $assets_loaded = true;
        $ver = defined( 'TEINVIT_CORE_VERSION' ) ? (string) TEINVIT_CORE_VERSION : '1';
        $base_css = TEINVIT_WEDDING_MODULE_URL . ( $is_pdf ? 'preview/pdf.css' : 'preview/preview.css' );
        $base_css = add_query_arg( 'ver', rawurlencode( $ver ), $base_css );
        $theme_css = add_query_arg( 'ver', rawurlencode( $ver ), TEINVIT_BIRTHDAY_MODULE_URL . 'preview/themes.css' );
        $html = '<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;600;700&family=Source+Serif+4:wght@400;600&family=Inter:wght@400;600;700&family=Parisienne&family=Lora:wght@400;600&family=Montserrat:wght@500;600;700&family=Poppins:wght@400;600;700&family=DM+Sans:wght@400;600;700&family=EB+Garamond:wght@400;600;700&family=Libre+Baskerville:wght@400;700&family=Baloo+2:wght@400;600;700&family=Oswald:wght@400;500;600&family=Satisfy&family=Raleway:wght@500;600;700&family=Unna:wght@400;700&family=Nunito:wght@400;600;700&family=Bodoni+Moda:wght@400;600;700&family=Playfair+Display:wght@600;700&family=Cormorant+Garamond:wght@400;600;700&family=Spectral:wght@400;600&family=DM+Serif+Display&family=Prata&display=swap">'
            . '<link rel="stylesheet" href="' . esc_url( $base_css ) . '">'
            . '<link rel="stylesheet" href="' . esc_url( $theme_css ) . '">' . $html;
    }

    if ( ! $is_pdf ) {
        $html .= '<script>window.TEINVIT_INVITATION_DATA = ' . wp_json_encode( $invitation ) . ';</script>';
        $html .= '<script>window.__TEINVIT_PDF_MODE__ = false;</script>';
        $html .= '<script>window.teinvitBirthdayPreviewConfig = ' . wp_json_encode( [ 'previewBuildUrl' => esc_url_raw( rest_url( 'teinvit/v2/preview/build' ) ) ] ) . ';</script>';
        $is_product_page = function_exists( 'is_product' ) ? (bool) is_product() : false;
        if ( ! $is_product_page ) {
            $ver = defined( 'TEINVIT_CORE_VERSION' ) ? (string) TEINVIT_CORE_VERSION : '1';
            $engine_js = add_query_arg( 'ver', rawurlencode( $ver ), TEINVIT_CORE_URL . 'infrastructure/preview-layout-engine.js' );
            $preview_js = add_query_arg( 'ver', rawurlencode( $ver ), TEINVIT_BIRTHDAY_MODULE_URL . 'preview/preview.js' );
            $html .= '<script src="' . esc_url( $engine_js ) . '"></script>';
            $html .= '<script src="' . esc_url( $preview_js ) . '"></script>';
        }
    }

    return $html;
}
