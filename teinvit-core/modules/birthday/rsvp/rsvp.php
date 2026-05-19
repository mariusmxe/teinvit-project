<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function teinvit_birthday_rsvp_bool( array $payload, $key ) {
    $value = $payload[ $key ] ?? 0;
    if ( is_bool( $value ) ) {
        return $value ? 1 : 0;
    }

    $value = strtolower( trim( (string) $value ) );
    return in_array( $value, [ '1', 'true', 'yes', 'da', 'on' ], true ) ? 1 : 0;
}

function teinvit_birthday_rsvp_int( array $payload, $key, $default = 0 ) {
    if ( ! isset( $payload[ $key ] ) || trim( (string) $payload[ $key ] ) === '' ) {
        return (int) $default;
    }

    return (int) $payload[ $key ];
}

function teinvit_birthday_rsvp_config_enabled( array $config, $key, $fallback_key = '' ) {
    if ( isset( $config[ $key ] ) ) {
        return ! empty( $config[ $key ] );
    }
    if ( $fallback_key !== '' && isset( $config[ $fallback_key ] ) ) {
        return ! empty( $config[ $fallback_key ] );
    }

    return false;
}

function teinvit_birthday_child_special_observations_labels() {
    return [
        'pickup_time' => 'Copilul trebuie preluat la o anumită oră.',
        'shy' => 'Copilul este mai timid.',
        'restricted_activities' => 'Copilul nu are voie anumite activități.',
        'accompanied_start_only' => 'Copilul va veni însoțit doar la început.',
        'other' => 'Alte observații.',
    ];
}

function teinvit_birthday_handle_child_rsvp_submit( $token, array $config, array $p, $first_name, $last_name, $phone, $email ) {
    global $wpdb;

    $show_party = teinvit_birthday_rsvp_config_enabled( $config, 'child_show_attending_party' );
    $show_children_count = teinvit_birthday_rsvp_config_enabled( $config, 'child_show_children_count' );
    $show_accompanying_adults = teinvit_birthday_rsvp_config_enabled( $config, 'child_show_accompanying_adults' );
    $show_vegetarian = teinvit_birthday_rsvp_config_enabled( $config, 'child_show_vegetarian' );
    $show_allergies = teinvit_birthday_rsvp_config_enabled( $config, 'child_show_allergies' );
    $show_message = teinvit_birthday_rsvp_config_enabled( $config, 'child_show_message' );
    $show_special_observations = teinvit_birthday_rsvp_config_enabled( $config, 'child_show_special_observations' );

    $attending_party = $show_party ? teinvit_birthday_rsvp_bool( $p, 'attending_party' ) : 1;

    $children_raw = isset( $p['child_participants_count'] ) ? trim( (string) $p['child_participants_count'] ) : '';
    $children_submitted = $children_raw === '' ? null : (int) $p['child_participants_count'];
    if ( ! $attending_party ) {
        if ( $children_submitted !== null && $children_submitted !== 0 ) {
            return new WP_Error( 'child_count_party_no_invalid', 'Dacă nu participați la petrecere, numărul de copii trebuie să fie 0.', [ 'status' => 400, 'field' => 'child_participants_count' ] );
        }
        $child_participants_count = 0;
    } elseif ( $show_children_count ) {
        if ( $children_submitted === null || $children_submitted < 1 || $children_submitted > 99 ) {
            return new WP_Error( 'child_count_invalid', 'Completați numărul de copii participanți, între 1 și 99.', [ 'status' => 400, 'field' => 'child_participants_count' ] );
        }
        $child_participants_count = $children_submitted;
    } else {
        $child_participants_count = 1;
    }

    if ( $child_participants_count > 99 ) {
        return new WP_Error( 'child_count_invalid', 'Numărul de copii participanți este prea mare.', [ 'status' => 400, 'field' => 'child_participants_count' ] );
    }

    $adult_stays = $show_accompanying_adults ? teinvit_birthday_rsvp_bool( $p, 'child_accompanying_adult_stays' ) : 0;
    $adult_raw = isset( $p['child_accompanying_adults_count'] ) ? trim( (string) $p['child_accompanying_adults_count'] ) : '';
    $adult_submitted = $adult_raw === '' ? null : (int) $p['child_accompanying_adults_count'];
    if ( ! $attending_party && $adult_submitted !== null && $adult_submitted !== 0 ) {
        return new WP_Error( 'child_adults_party_no_invalid', 'Dacă nu participați la petrecere, numărul adulților însoțitori trebuie să fie 0.', [ 'status' => 400, 'field' => 'child_accompanying_adults_count' ] );
    }
    if ( $adult_stays ) {
        if ( $adult_submitted === null || $adult_submitted < 1 || $adult_submitted > 99 ) {
            return new WP_Error( 'child_adults_count_invalid', 'Completați numărul de adulți însoțitori, între 1 și 99.', [ 'status' => 400, 'field' => 'child_accompanying_adults_count' ] );
        }
        $child_accompanying_adults_count = $adult_submitted;
    } else {
        if ( $adult_submitted !== null && $adult_submitted !== 0 ) {
            return new WP_Error( 'child_adults_count_no_invalid', 'Dacă adultul însoțitor nu rămâne, numărul adulților trebuie să fie 0.', [ 'status' => 400, 'field' => 'child_accompanying_adults_count' ] );
        }
        $child_accompanying_adults_count = 0;
    }

    $vegetarian_requested = $show_vegetarian ? teinvit_birthday_rsvp_bool( $p, 'vegetarian_requested' ) : 0;
    $vegetarian_menus_count = $vegetarian_requested ? max( 0, teinvit_birthday_rsvp_int( $p, 'vegetarian_menus_count', 0 ) ) : 0;
    if ( $vegetarian_requested ) {
        $max_vegetarian_menus = $child_participants_count + $child_accompanying_adults_count;
        if ( $max_vegetarian_menus <= 0 ) {
            return new WP_Error( 'vegetarian_party_no_invalid', 'Meniul vegetarian poate fi solicitat doar pentru participanți.', [ 'status' => 400, 'field' => 'vegetarian_menus_count' ] );
        }
        if ( $vegetarian_menus_count < 1 || $vegetarian_menus_count > $max_vegetarian_menus ) {
            return new WP_Error( 'vegetarian_menus_invalid', 'Numărul de meniuri vegetariene nu poate depăși totalul copiilor și adulților însoțitori.', [
                'status' => 400,
                'field' => 'vegetarian_menus_count',
                'max' => $max_vegetarian_menus,
            ] );
        }
    }

    $has_allergies = $show_allergies ? teinvit_birthday_rsvp_bool( $p, 'has_allergies' ) : 0;
    $allergy_details = $has_allergies ? sanitize_textarea_field( (string) ( $p['allergy_details'] ?? '' ) ) : '';
    if ( $has_allergies && trim( $allergy_details ) === '' ) {
        return new WP_Error( 'allergy_details_required', 'Completați alergiile sau restricțiile alimentare.', [ 'status' => 400, 'field' => 'allergy_details' ] );
    }

    $message_to_celebrants = $show_message ? sanitize_textarea_field( (string) ( $p['message_to_celebrants'] ?? '' ) ) : '';

    $allowed_observation_labels = teinvit_birthday_child_special_observations_labels();
    $child_special_options = [];
    $child_special_other = '';
    $child_pickup_time = '';
    $child_restricted_activities = '';
    $special_observations = '';
    if ( $show_special_observations ) {
        $raw_options = isset( $p['child_special_observations_options'] ) && is_array( $p['child_special_observations_options'] ) ? $p['child_special_observations_options'] : [];
        foreach ( $raw_options as $option ) {
            $key = sanitize_key( (string) $option );
            if ( isset( $allowed_observation_labels[ $key ] ) && ! in_array( $key, $child_special_options, true ) ) {
                $child_special_options[] = $key;
            }
        }

        $child_special_other = sanitize_textarea_field( (string) ( $p['child_special_observations_other'] ?? '' ) );
        if ( in_array( 'other', $child_special_options, true ) && trim( $child_special_other ) === '' ) {
            return new WP_Error( 'child_special_observations_other_required', 'Completați câmpul Alte observații.', [ 'status' => 400, 'field' => 'child_special_observations_other' ] );
        }

        $child_pickup_time = sanitize_text_field( (string) ( $p['child_pickup_time'] ?? '' ) );
        if ( in_array( 'pickup_time', $child_special_options, true ) && trim( $child_pickup_time ) === '' ) {
            return new WP_Error( 'child_pickup_time_required', 'Completați ora la care copilul trebuie preluat.', [ 'status' => 400, 'field' => 'child_pickup_time' ] );
        }
        if ( ! in_array( 'pickup_time', $child_special_options, true ) ) {
            $child_pickup_time = '';
        }

        $child_restricted_activities = sanitize_textarea_field( (string) ( $p['child_restricted_activities'] ?? '' ) );
        if ( in_array( 'restricted_activities', $child_special_options, true ) && trim( $child_restricted_activities ) === '' ) {
            return new WP_Error( 'child_restricted_activities_required', 'Completați activitățile care trebuie evitate.', [ 'status' => 400, 'field' => 'child_restricted_activities' ] );
        }
        if ( ! in_array( 'restricted_activities', $child_special_options, true ) ) {
            $child_restricted_activities = '';
        }

        $parts = [];
        foreach ( $child_special_options as $option ) {
            if ( $option === 'other' ) {
                continue;
            }
            if ( $option === 'pickup_time' ) {
                $parts[] = rtrim( $allowed_observation_labels[ $option ], ". \t\n\r\0\x0B" ) . ': ' . trim( $child_pickup_time );
                continue;
            }
            if ( $option === 'restricted_activities' ) {
                $parts[] = rtrim( $allowed_observation_labels[ $option ], ". \t\n\r\0\x0B" ) . ': ' . trim( $child_restricted_activities );
                continue;
            }
            $parts[] = $allowed_observation_labels[ $option ];
        }
        if ( in_array( 'other', $child_special_options, true ) && trim( $child_special_other ) !== '' ) {
            $parts[] = 'Alte observații: ' . trim( $child_special_other );
        }
        $special_observations = implode( "\n", $parts );
    }

    $common = [
        'token' => $token,
        'guest_first_name' => $first_name,
        'guest_last_name' => $last_name,
        'guest_email' => $email,
        'guest_phone' => $phone,
        'attending_people_count' => $child_accompanying_adults_count,
        'attending_civil' => 0,
        'attending_religious' => 0,
        'attending_party' => $attending_party,
        'bringing_kids' => $child_participants_count > 0 ? 1 : 0,
        'kids_count' => $child_participants_count,
        'needs_accommodation' => 0,
        'accommodation_people_count' => 0,
        'vegetarian_requested' => $vegetarian_requested,
        'vegetarian_menus_count' => $vegetarian_menus_count,
        'has_allergies' => $has_allergies,
        'allergy_details' => $allergy_details,
        'message_to_couple' => $message_to_celebrants,
        'gdpr_accepted' => 1,
        'marketing_consent' => teinvit_birthday_rsvp_bool( $p, 'marketing_consent' ),
        'created_at' => current_time( 'mysql' ),
    ];

    $extra = [
        'vertical' => 'birthday',
        'birthday_rsvp_mode' => 'child',
        'attending_party' => $attending_party,
        'child_participants_count' => $child_participants_count,
        'child_accompanying_adult_stays' => $adult_stays,
        'child_accompanying_adults_count' => $child_accompanying_adults_count,
        'child_special_observations_options' => $child_special_options,
        'child_pickup_time' => $child_pickup_time,
        'child_restricted_activities' => $child_restricted_activities,
        'child_special_observations_other' => $child_special_other,
        'special_observations' => $special_observations,
        'message_to_celebrants' => $message_to_celebrants,
        'config_snapshot' => [
            'birthday_rsvp_mode' => 'child',
            'child_show_attending_party' => $show_party ? 1 : 0,
            'child_show_children_count' => $show_children_count ? 1 : 0,
            'child_show_accompanying_adults' => $show_accompanying_adults ? 1 : 0,
            'child_show_allergies' => $show_allergies ? 1 : 0,
            'child_show_vegetarian' => $show_vegetarian ? 1 : 0,
            'child_show_special_observations' => $show_special_observations ? 1 : 0,
            'child_show_message' => $show_message ? 1 : 0,
        ],
    ];

    $insert_data = function_exists( 'teinvit_prepare_hybrid_rsvp_insert_data' )
        ? teinvit_prepare_hybrid_rsvp_insert_data( 'birthday', $common, $extra )
        : array_merge( $common, [ 'extra_fields' => wp_json_encode( $extra ) ] );

    $table = function_exists( 'teinvit_rsvp_table_for_token' ) ? teinvit_rsvp_table_for_token( $token, 'birthday' ) : '';
    if ( $table === '' ) {
        return new WP_Error( 'rsvp_storage_missing', 'Storage RSVP indisponibil.', [ 'status' => 500 ] );
    }

    $selected_gift_ids = array_values( array_unique( array_filter( array_map( 'sanitize_text_field', (array) ( $p['gift_ids'] ?? [] ) ), static function( $gift_id ) {
        return trim( (string) $gift_id ) !== '';
    } ) ) );
    $gifts_table = '';
    if ( ! empty( $selected_gift_ids ) ) {
        $gifts_table = function_exists( 'teinvit_birthday_gifts_table_for_token' )
            ? teinvit_birthday_gifts_table_for_token( $token )
            : ( function_exists( 'teinvit_gifts_table_for_token' ) ? teinvit_gifts_table_for_token( $token, 'birthday' ) : '' );

        if ( $gifts_table === '' ) {
            return new WP_Error( 'gifts_storage_missing', 'Storage cadouri indisponibil.', [ 'status' => 500 ] );
        }
    }

    $wpdb->query( 'START TRANSACTION' );

    $inserted = $wpdb->insert( $table, $insert_data );
    if ( ! $inserted || ! $wpdb->insert_id ) {
        $wpdb->query( 'ROLLBACK' );
        return new WP_Error( 'rsvp_failed', 'Nu s-a putut salva RSVP', [ 'status' => 500 ] );
    }

    $rsvp_id = (int) $wpdb->insert_id;

    foreach ( $selected_gift_ids as $gift_id ) {
        $updated = $wpdb->query( $wpdb->prepare(
            "UPDATE {$gifts_table} SET status='reserved', reserved_by_rsvp_id=%d, reserved_at=%s WHERE token=%s AND gift_id=%s AND include_in_public=1 AND status='free'",
            $rsvp_id,
            current_time( 'mysql' ),
            $token,
            $gift_id
        ) );

        if ( $updated !== 1 ) {
            $wpdb->query( 'ROLLBACK' );
            return new WP_Error( 'gift_conflict', 'Cadou deja rezervat între timp.', [ 'status' => 409 ] );
        }
    }

    $wpdb->query( 'COMMIT' );

    if ( function_exists( 'teinvit_touch_invitation_activity_for_token' ) ) {
        teinvit_touch_invitation_activity_for_token( $token, 'birthday' );
    }

    $rsvp_payload = array_merge( $common, [
        'vertical' => 'birthday',
        'rsvp_id' => $rsvp_id,
        'extra_fields' => $extra,
    ] );
    do_action( 'teinvit_rsvp_saved', $token, $rsvp_id, $rsvp_payload );

    return rest_ensure_response( [
        'ok' => true,
        'rsvp_id' => $rsvp_id,
    ] );
}

function teinvit_birthday_handle_rsvp_rest( WP_REST_Request $request ) {
    global $wpdb;

    $token = sanitize_text_field( (string) $request['token'] );
    if ( $token === '' ) {
        return new WP_Error( 'not_found', 'Token invalid', [ 'status' => 404 ] );
    }

    $vertical = function_exists( 'teinvit_resolve_token_vertical' ) ? teinvit_resolve_token_vertical( $token ) : 'wedding';
    if ( $vertical !== 'birthday' ) {
        return new WP_Error( 'vertical_mismatch', 'RSVP Birthday nu poate procesa acest token.', [ 'status' => 400 ] );
    }

    $inv = function_exists( 'teinvit_get_invitation_record' ) ? teinvit_get_invitation_record( $token, 'birthday' ) : null;
    if ( ! is_array( $inv ) ) {
        return new WP_Error( 'not_found', 'Token invalid', [ 'status' => 404 ] );
    }

    $config = function_exists( 'teinvit_birthday_config_with_defaults' )
        ? teinvit_birthday_config_with_defaults( is_array( $inv['config'] ?? null ) ? $inv['config'] : [] )
        : wp_parse_args( is_array( $inv['config'] ?? null ) ? $inv['config'] : [], function_exists( 'teinvit_default_rsvp_config_for_vertical' ) ? teinvit_default_rsvp_config_for_vertical( 'birthday' ) : [] );

    $p = (array) $request->get_json_params();

    $first_name = sanitize_text_field( (string) ( $p['guest_first_name'] ?? '' ) );
    $last_name = sanitize_text_field( (string) ( $p['guest_last_name'] ?? '' ) );
    if ( $first_name === '' ) {
        return new WP_Error( 'first_name_required', 'Prenume este obligatoriu', [ 'status' => 400, 'field' => 'guest_first_name' ] );
    }
    if ( $last_name === '' ) {
        return new WP_Error( 'last_name_required', 'Nume este obligatoriu', [ 'status' => 400, 'field' => 'guest_last_name' ] );
    }

    $phone = trim( (string) ( $p['guest_phone'] ?? '' ) );
    $phone = preg_replace( '/\s+/', '', $phone );
    $is_ro = preg_match( '/^(?:07\d{8}|\+407\d{8})$/', $phone );
    $is_intl = preg_match( '/^\+[1-9]\d{7,14}$/', $phone );
    if ( ! $is_ro && ! $is_intl ) {
        return new WP_Error( 'phone_invalid', 'Telefon invalid', [ 'status' => 400, 'field' => 'guest_phone' ] );
    }
    if ( strpos( $phone, '+407' ) === 0 ) {
        $phone = '0' . substr( $phone, 3 );
    }

    $email = sanitize_email( (string) ( $p['guest_email'] ?? '' ) );
    if ( $email !== '' && ! is_email( $email ) ) {
        return new WP_Error( 'email_invalid', 'Email invalid', [ 'status' => 400, 'field' => 'guest_email' ] );
    }

    if ( empty( $p['gdpr_accepted'] ) ) {
        return new WP_Error( 'gdpr_required', 'GDPR este obligatoriu', [ 'status' => 400, 'field' => 'gdpr_accepted' ] );
    }

    $rsvp_mode = function_exists( 'teinvit_birthday_rsvp_mode_from_config' ) ? teinvit_birthday_rsvp_mode_from_config( $config ) : ( ( $config['birthday_rsvp_mode'] ?? 'adult' ) === 'child' ? 'child' : 'adult' );
    if ( $rsvp_mode === 'child' ) {
        return teinvit_birthday_handle_child_rsvp_submit( $token, $config, $p, $first_name, $last_name, $phone, $email );
    }

    $show_party = teinvit_birthday_rsvp_config_enabled( $config, 'show_attending_party' );
    $show_guest_count = teinvit_birthday_rsvp_config_enabled( $config, 'show_guest_count', 'show_attending_people_count' );
    $show_kids = teinvit_birthday_rsvp_config_enabled( $config, 'show_kids' );
    $show_child_menu = teinvit_birthday_rsvp_config_enabled( $config, 'show_child_menu' );
    $show_accommodation = teinvit_birthday_rsvp_config_enabled( $config, 'show_accommodation' );
    $show_vegetarian = teinvit_birthday_rsvp_config_enabled( $config, 'show_vegetarian' );
    $show_allergies = teinvit_birthday_rsvp_config_enabled( $config, 'show_allergies' );
    $show_message = teinvit_birthday_rsvp_config_enabled( $config, 'show_message' );
    $show_special_observations = teinvit_birthday_rsvp_config_enabled( $config, 'show_special_observations' );

    $attending_party = $show_party ? teinvit_birthday_rsvp_bool( $p, 'attending_party' ) : 0;
    $attending_people_raw = isset( $p['attending_people_count'] ) ? trim( (string) $p['attending_people_count'] ) : '';
    $attending_people_submitted = $attending_people_raw === '' ? null : (int) $p['attending_people_count'];
    if ( $show_party && ! $attending_party ) {
        if ( $attending_people_submitted !== null && $attending_people_submitted !== 0 ) {
            return new WP_Error( 'guest_count_party_no_invalid', 'Dacă nu participați la petrecere, numărul de adulți trebuie să fie 0.', [ 'status' => 400, 'field' => 'attending_people_count' ] );
        }
        $attending_people_count = 0;
    } elseif ( $show_guest_count ) {
        if ( $attending_people_submitted === null ) {
            return new WP_Error( 'guest_count_required', 'Completați numărul de adulți participanți.', [ 'status' => 400, 'field' => 'attending_people_count' ] );
        }
        $attending_people_count = $attending_people_submitted;
    } else {
        $attending_people_count = 1;
    }
    if ( ( ! $show_party || $attending_party ) && $show_guest_count && $attending_people_count < 1 ) {
        return new WP_Error( 'guest_count_invalid', 'Numărul de adulți participanți trebuie să fie între 1 și 50.', [ 'status' => 400, 'field' => 'attending_people_count' ] );
    }
    if ( $attending_people_count > 50 ) {
        return new WP_Error( 'guest_count_invalid', 'Numărul de persoane este prea mare.', [ 'status' => 400, 'field' => 'attending_people_count' ] );
    }

    $bringing_kids = $show_kids ? teinvit_birthday_rsvp_bool( $p, 'bringing_kids' ) : 0;
    $kids_count = $bringing_kids ? max( 0, teinvit_birthday_rsvp_int( $p, 'kids_count', 0 ) ) : 0;
    if ( $bringing_kids && ( $kids_count < 1 || $kids_count > 50 ) ) {
        return new WP_Error( 'kids_count_required', 'Completați numărul de copii', [ 'status' => 400, 'field' => 'kids_count' ] );
    }

    $child_menu_requested = $show_child_menu ? teinvit_birthday_rsvp_bool( $p, 'child_menu_requested' ) : 0;
    $child_menu_count = $child_menu_requested ? max( 0, teinvit_birthday_rsvp_int( $p, 'child_menu_count', 0 ) ) : 0;
    if ( $child_menu_requested ) {
        $child_menu_max = $kids_count > 0 ? $kids_count : max( 1, $attending_people_count + $kids_count );
        if ( $child_menu_count < 1 || $child_menu_count > $child_menu_max ) {
            return new WP_Error( 'child_menu_count_invalid', 'Completați numărul de meniuri pentru copii.', [
                'status' => 400,
                'field' => 'child_menu_count',
                'max' => $child_menu_max,
            ] );
        }
    }

    $needs_accommodation = $show_accommodation ? teinvit_birthday_rsvp_bool( $p, 'needs_accommodation' ) : 0;
    $accommodation_people_count = $needs_accommodation ? max( 0, teinvit_birthday_rsvp_int( $p, 'accommodation_people_count', 0 ) ) : 0;
    if ( $needs_accommodation && ( $accommodation_people_count < 1 || $accommodation_people_count > 50 ) ) {
        return new WP_Error( 'accommodation_people_count_required', 'Completați numărul de persoane care au nevoie de cazare', [ 'status' => 400, 'field' => 'accommodation_people_count' ] );
    }

    $vegetarian_requested = $show_vegetarian ? teinvit_birthday_rsvp_bool( $p, 'vegetarian_requested' ) : 0;
    $vegetarian_menus_count = $vegetarian_requested ? max( 0, teinvit_birthday_rsvp_int( $p, 'vegetarian_menus_count', 0 ) ) : 0;
    if ( $vegetarian_requested ) {
        $max_vegetarian_menus = max( 1, $attending_people_count + $kids_count );
        if ( $vegetarian_menus_count < 1 || $vegetarian_menus_count > $max_vegetarian_menus ) {
            return new WP_Error( 'vegetarian_menus_invalid', 'Completați numărul de meniuri vegetariene', [
                'status' => 400,
                'field' => 'vegetarian_menus_count',
                'max' => $max_vegetarian_menus,
            ] );
        }
    }

    $has_allergies = $show_allergies ? teinvit_birthday_rsvp_bool( $p, 'has_allergies' ) : 0;
    $allergy_details = $has_allergies ? sanitize_textarea_field( (string) ( $p['allergy_details'] ?? '' ) ) : '';
    if ( $has_allergies && trim( $allergy_details ) === '' ) {
        return new WP_Error( 'allergy_details_required', 'Completați alergiile', [ 'status' => 400, 'field' => 'allergy_details' ] );
    }

    $message_to_celebrants = $show_message ? sanitize_textarea_field( (string) ( $p['message_to_celebrants'] ?? '' ) ) : '';
    $special_observations = $show_special_observations ? sanitize_textarea_field( (string) ( $p['special_observations'] ?? '' ) ) : '';

    $common = [
        'token' => $token,
        'guest_first_name' => $first_name,
        'guest_last_name' => $last_name,
        'guest_email' => $email,
        'guest_phone' => $phone,
        'attending_people_count' => $attending_people_count,
        'attending_civil' => 0,
        'attending_religious' => 0,
        'attending_party' => $attending_party,
        'bringing_kids' => $bringing_kids,
        'kids_count' => $kids_count,
        'needs_accommodation' => $needs_accommodation,
        'accommodation_people_count' => $accommodation_people_count,
        'vegetarian_requested' => $vegetarian_requested,
        'vegetarian_menus_count' => $vegetarian_menus_count,
        'has_allergies' => $has_allergies,
        'allergy_details' => $allergy_details,
        'message_to_couple' => $message_to_celebrants,
        'gdpr_accepted' => 1,
        'marketing_consent' => teinvit_birthday_rsvp_bool( $p, 'marketing_consent' ),
        'created_at' => current_time( 'mysql' ),
    ];

    $extra = [
        'vertical' => 'birthday',
        'birthday_rsvp_mode' => 'adult',
        'attending_party' => $attending_party,
        'show_guest_count' => $show_guest_count ? 1 : 0,
        'child_menu_requested' => $child_menu_requested,
        'child_menu_count' => $child_menu_count,
        'message_to_celebrants' => $message_to_celebrants,
        'special_observations' => $special_observations,
        'config_snapshot' => [
            'birthday_rsvp_mode' => 'adult',
            'show_attending_party' => $show_party ? 1 : 0,
            'show_guest_count' => $show_guest_count ? 1 : 0,
            'show_kids' => $show_kids ? 1 : 0,
            'show_child_menu' => $show_child_menu ? 1 : 0,
            'show_accommodation' => $show_accommodation ? 1 : 0,
            'show_vegetarian' => $show_vegetarian ? 1 : 0,
            'show_allergies' => $show_allergies ? 1 : 0,
            'show_message' => $show_message ? 1 : 0,
            'show_special_observations' => $show_special_observations ? 1 : 0,
        ],
    ];

    $insert_data = function_exists( 'teinvit_prepare_hybrid_rsvp_insert_data' )
        ? teinvit_prepare_hybrid_rsvp_insert_data( 'birthday', $common, $extra )
        : array_merge( $common, [ 'extra_fields' => wp_json_encode( $extra ) ] );

    $table = function_exists( 'teinvit_rsvp_table_for_token' ) ? teinvit_rsvp_table_for_token( $token, 'birthday' ) : '';
    if ( $table === '' ) {
        return new WP_Error( 'rsvp_storage_missing', 'Storage RSVP indisponibil.', [ 'status' => 500 ] );
    }

    $selected_gift_ids = array_values( array_unique( array_filter( array_map( 'sanitize_text_field', (array) ( $p['gift_ids'] ?? [] ) ), static function( $gift_id ) {
        return trim( (string) $gift_id ) !== '';
    } ) ) );
    $gifts_table = '';
    if ( ! empty( $selected_gift_ids ) ) {
        $gifts_table = function_exists( 'teinvit_birthday_gifts_table_for_token' )
            ? teinvit_birthday_gifts_table_for_token( $token )
            : ( function_exists( 'teinvit_gifts_table_for_token' ) ? teinvit_gifts_table_for_token( $token, 'birthday' ) : '' );

        if ( $gifts_table === '' ) {
            return new WP_Error( 'gifts_storage_missing', 'Storage cadouri indisponibil.', [ 'status' => 500 ] );
        }
    }

    $wpdb->query( 'START TRANSACTION' );

    $inserted = $wpdb->insert( $table, $insert_data );
    if ( ! $inserted || ! $wpdb->insert_id ) {
        $wpdb->query( 'ROLLBACK' );
        return new WP_Error( 'rsvp_failed', 'Nu s-a putut salva RSVP', [ 'status' => 500 ] );
    }

    $rsvp_id = (int) $wpdb->insert_id;

    foreach ( $selected_gift_ids as $gift_id ) {
        $updated = $wpdb->query( $wpdb->prepare(
            "UPDATE {$gifts_table} SET status='reserved', reserved_by_rsvp_id=%d, reserved_at=%s WHERE token=%s AND gift_id=%s AND include_in_public=1 AND status='free'",
            $rsvp_id,
            current_time( 'mysql' ),
            $token,
            $gift_id
        ) );

        if ( $updated !== 1 ) {
            $wpdb->query( 'ROLLBACK' );
            return new WP_Error( 'gift_conflict', 'Cadou deja rezervat între timp.', [ 'status' => 409 ] );
        }
    }

    $wpdb->query( 'COMMIT' );

    if ( function_exists( 'teinvit_touch_invitation_activity_for_token' ) ) {
        teinvit_touch_invitation_activity_for_token( $token, 'birthday' );
    }

    $rsvp_payload = array_merge( $common, [
        'vertical' => 'birthday',
        'rsvp_id' => $rsvp_id,
        'extra_fields' => $extra,
    ] );
    do_action( 'teinvit_rsvp_saved', $token, $rsvp_id, $rsvp_payload );

    return rest_ensure_response( [
        'ok' => true,
        'rsvp_id' => $rsvp_id,
    ] );
}
