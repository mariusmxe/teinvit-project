<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function teinvit_baptism_rsvp_bool( array $payload, $key ) {
    $value = $payload[ $key ] ?? 0;
    if ( is_bool( $value ) ) {
        return $value ? 1 : 0;
    }

    $value = strtolower( trim( (string) $value ) );
    return in_array( $value, [ '1', 'true', 'yes', 'da', 'on' ], true ) ? 1 : 0;
}

function teinvit_baptism_rsvp_has_answer( array $payload, $key ) {
    if ( ! array_key_exists( $key, $payload ) ) {
        return false;
    }

    return trim( (string) $payload[ $key ] ) !== '';
}

function teinvit_baptism_rsvp_int( array $payload, $key, $default = 0 ) {
    if ( ! isset( $payload[ $key ] ) || trim( (string) $payload[ $key ] ) === '' ) {
        return (int) $default;
    }

    return (int) $payload[ $key ];
}

function teinvit_baptism_rsvp_config_enabled( array $config, $key ) {
    return isset( $config[ $key ] ) && ! empty( $config[ $key ] );
}

function teinvit_baptism_rsvp_normalize_phone_or_error( $phone ) {
    $phone = preg_replace( '/\s+/', '', trim( (string) $phone ) );
    $is_ro = preg_match( '/^(?:07\d{8}|\+407\d{8})$/', $phone );
    $is_intl = preg_match( '/^\+[1-9]\d{7,14}$/', $phone );
    if ( ! $is_ro && ! $is_intl ) {
        return new WP_Error( 'phone_invalid', 'Telefon invalid.', [ 'status' => 400, 'field' => 'guest_phone' ] );
    }
    if ( strpos( $phone, '+407' ) === 0 ) {
        $phone = '0' . substr( $phone, 3 );
    }

    return $phone;
}

function teinvit_baptism_rsvp_validate_radio_answer( array $payload, $key, $message ) {
    if ( ! teinvit_baptism_rsvp_has_answer( $payload, $key ) ) {
        return new WP_Error( $key . '_required', $message, [ 'status' => 400, 'field' => $key ] );
    }

    $raw = trim( (string) $payload[ $key ] );
    if ( $raw !== '0' && $raw !== '1' ) {
        return new WP_Error( $key . '_invalid', $message, [ 'status' => 400, 'field' => $key ] );
    }

    return teinvit_baptism_rsvp_bool( $payload, $key );
}

function teinvit_baptism_handle_rsvp_rest( WP_REST_Request $request ) {
    global $wpdb;

    $token = sanitize_text_field( (string) $request['token'] );
    if ( $token === '' ) {
        return new WP_Error( 'not_found', 'Token invalid.', [ 'status' => 404 ] );
    }

    $vertical = function_exists( 'teinvit_resolve_token_vertical' ) ? teinvit_resolve_token_vertical( $token ) : 'wedding';
    if ( $vertical !== 'baptism' ) {
        return new WP_Error( 'wrong_vertical', 'RSVP Baptism este disponibil doar pentru tokenuri Baptism.', [ 'status' => 404 ] );
    }

    $inv = function_exists( 'teinvit_get_invitation_record' ) ? teinvit_get_invitation_record( $token, 'baptism' ) : null;
    if ( ! is_array( $inv ) ) {
        return new WP_Error( 'not_found', 'Token invalid.', [ 'status' => 404 ] );
    }

    $config = function_exists( 'teinvit_baptism_config_with_defaults' )
        ? teinvit_baptism_config_with_defaults( is_array( $inv['config'] ?? null ) ? $inv['config'] : [] )
        : wp_parse_args( is_array( $inv['config'] ?? null ) ? $inv['config'] : [], function_exists( 'teinvit_default_rsvp_config_for_vertical' ) ? teinvit_default_rsvp_config_for_vertical( 'baptism' ) : [] );

    $p = (array) $request->get_json_params();

    $last_name = sanitize_text_field( (string) ( $p['guest_last_name'] ?? '' ) );
    $first_name = sanitize_text_field( (string) ( $p['guest_first_name'] ?? '' ) );
    if ( trim( $last_name ) === '' ) {
        return new WP_Error( 'guest_last_name_required', 'Completați numele invitatului.', [ 'status' => 400, 'field' => 'guest_last_name' ] );
    }
    if ( trim( $first_name ) === '' ) {
        return new WP_Error( 'guest_first_name_required', 'Completați prenumele invitatului.', [ 'status' => 400, 'field' => 'guest_first_name' ] );
    }

    $phone = teinvit_baptism_rsvp_normalize_phone_or_error( $p['guest_phone'] ?? '' );
    if ( is_wp_error( $phone ) ) {
        return $phone;
    }

    $email = sanitize_email( (string) ( $p['guest_email'] ?? '' ) );
    if ( $email !== '' && ! is_email( $email ) ) {
        return new WP_Error( 'email_invalid', 'Email invalid.', [ 'status' => 400, 'field' => 'guest_email' ] );
    }

    if ( empty( $p['gdpr_accepted'] ) ) {
        return new WP_Error( 'gdpr_required', 'GDPR este obligatoriu.', [ 'status' => 400, 'field' => 'gdpr_accepted' ] );
    }

    $show_religious = teinvit_baptism_rsvp_config_enabled( $config, 'show_attending_religious' );
    $show_party = teinvit_baptism_rsvp_config_enabled( $config, 'show_attending_party' );
    $show_adults = teinvit_baptism_rsvp_config_enabled( $config, 'show_attending_people_count' );
    $show_kids = teinvit_baptism_rsvp_config_enabled( $config, 'show_kids' );
    $show_child_menu = teinvit_baptism_rsvp_config_enabled( $config, 'show_child_menu' );
    $show_child_seat = teinvit_baptism_rsvp_config_enabled( $config, 'show_child_seat' );
    $show_accommodation = teinvit_baptism_rsvp_config_enabled( $config, 'show_accommodation' );
    $show_transport = teinvit_baptism_rsvp_config_enabled( $config, 'show_transport' );
    $show_vegetarian = teinvit_baptism_rsvp_config_enabled( $config, 'show_vegetarian' );
    $show_allergies = teinvit_baptism_rsvp_config_enabled( $config, 'show_allergies' );
    $show_message = teinvit_baptism_rsvp_config_enabled( $config, 'show_message' );

    $attending_religious = 0;
    if ( $show_religious ) {
        $religious = teinvit_baptism_rsvp_validate_radio_answer( $p, 'attending_religious', 'Alegeți dacă participați la Slujba de botez.' );
        if ( is_wp_error( $religious ) ) {
            return $religious;
        }
        $attending_religious = (int) $religious;
    }

    $attending_party = 0;
    if ( $show_party ) {
        $party = teinvit_baptism_rsvp_validate_radio_answer( $p, 'attending_party', 'Alegeți dacă participați la petrecerea de botez.' );
        if ( is_wp_error( $party ) ) {
            return $party;
        }
        $attending_party = (int) $party;
    }

    $has_event_question = $show_religious || $show_party;
    $any_attending = $has_event_question ? ( $attending_religious || $attending_party ) : true;
    $technical_max = 999;

    $adults_raw = isset( $p['attending_people_count'] ) ? trim( (string) $p['attending_people_count'] ) : '';
    $adults_submitted = $adults_raw === '' ? null : (int) $p['attending_people_count'];
    if ( $show_adults ) {
        if ( $any_attending ) {
            if ( $adults_submitted === null || $adults_submitted < 1 || $adults_submitted > $technical_max ) {
                return new WP_Error( 'attending_people_count_invalid', 'Completați numărul de adulți confirmați.', [ 'status' => 400, 'field' => 'attending_people_count' ] );
            }
            $attending_people_count = $adults_submitted;
        } else {
            if ( $adults_submitted !== null && $adults_submitted !== 0 ) {
                return new WP_Error( 'attending_people_count_no_invalid', 'Dacă nu participați la niciun eveniment, numărul de adulți trebuie să fie 0.', [ 'status' => 400, 'field' => 'attending_people_count' ] );
            }
            $attending_people_count = 0;
        }
    } else {
        $attending_people_count = $any_attending ? 1 : 0;
    }

    $bringing_kids = 0;
    $kids_count = 0;
    if ( $show_kids ) {
        $kids_answer = teinvit_baptism_rsvp_validate_radio_answer( $p, 'bringing_kids', 'Alegeți dacă veniți însoțiți de copii.' );
        if ( is_wp_error( $kids_answer ) ) {
            return $kids_answer;
        }
        $bringing_kids = (int) $kids_answer;
        $kids_raw = isset( $p['kids_count'] ) ? trim( (string) $p['kids_count'] ) : '';
        $kids_submitted = $kids_raw === '' ? null : (int) $p['kids_count'];
        if ( $bringing_kids ) {
            if ( $kids_submitted === null || $kids_submitted < 1 || $kids_submitted > $technical_max ) {
                return new WP_Error( 'kids_count_required', 'Completați numărul de copii.', [ 'status' => 400, 'field' => 'kids_count' ] );
            }
            $kids_count = $kids_submitted;
        } else {
            if ( $kids_submitted !== null && $kids_submitted !== 0 ) {
                return new WP_Error( 'kids_count_no_invalid', 'Dacă nu veniți cu copii, numărul de copii trebuie să fie 0.', [ 'status' => 400, 'field' => 'kids_count' ] );
            }
            $kids_count = 0;
        }
    }

    $total_people = max( 0, $attending_people_count + $kids_count );

    $child_menu_requested = 0;
    $child_menu_count = 0;
    if ( $show_child_menu ) {
        $child_menu_requested = teinvit_baptism_rsvp_bool( $p, 'child_menu_requested' );
        $child_menu_count = $child_menu_requested ? teinvit_baptism_rsvp_int( $p, 'child_menu_count', 0 ) : 0;
        if ( $child_menu_requested ) {
            $max_child_menus = $show_kids ? $kids_count : $technical_max;
            if ( $child_menu_count < 1 || $child_menu_count > $max_child_menus ) {
                return new WP_Error( 'child_menu_count_invalid', 'Numărul de meniuri copil nu poate depăși numărul de copii confirmați.', [ 'status' => 400, 'field' => 'child_menu_count', 'max' => $max_child_menus ] );
            }
        }
    }

    $child_seat_requested = 0;
    $child_seat_count = 0;
    if ( $show_child_seat ) {
        $child_seat_requested = teinvit_baptism_rsvp_bool( $p, 'child_seat_requested' );
        $child_seat_count = $child_seat_requested ? teinvit_baptism_rsvp_int( $p, 'child_seat_count', 0 ) : 0;
        if ( $child_seat_requested ) {
            $max_child_seats = $show_kids ? $kids_count : $technical_max;
            if ( $child_seat_count < 1 || $child_seat_count > $max_child_seats ) {
                return new WP_Error( 'child_seat_count_invalid', 'Numărul de scaune copil nu poate depăși numărul de copii confirmați.', [ 'status' => 400, 'field' => 'child_seat_count', 'max' => $max_child_seats ] );
            }
        }
    }

    $needs_accommodation = 0;
    $accommodation_people_count = 0;
    if ( $show_accommodation ) {
        $needs_accommodation = teinvit_baptism_rsvp_bool( $p, 'needs_accommodation' );
        $accommodation_people_count = $needs_accommodation ? teinvit_baptism_rsvp_int( $p, 'accommodation_people_count', 0 ) : 0;
        if ( $needs_accommodation && ( $accommodation_people_count < 1 || $accommodation_people_count > $total_people ) ) {
            return new WP_Error( 'accommodation_people_count_invalid', 'Numărul de persoane pentru cazare este invalid.', [ 'status' => 400, 'field' => 'accommodation_people_count', 'max' => $total_people ] );
        }
    }

    $transport_requested = 0;
    $transport_people_count = 0;
    if ( $show_transport ) {
        $transport_requested = teinvit_baptism_rsvp_bool( $p, 'transport_requested' );
        $transport_people_count = $transport_requested ? teinvit_baptism_rsvp_int( $p, 'transport_people_count', 0 ) : 0;
        if ( $transport_requested && ( $transport_people_count < 1 || $transport_people_count > $total_people ) ) {
            return new WP_Error( 'transport_people_count_invalid', 'Numărul de persoane pentru transport este invalid.', [ 'status' => 400, 'field' => 'transport_people_count', 'max' => $total_people ] );
        }
    }

    $vegetarian_requested = 0;
    $vegetarian_menus_count = 0;
    if ( $show_vegetarian ) {
        $vegetarian_requested = teinvit_baptism_rsvp_bool( $p, 'vegetarian_requested' );
        $vegetarian_menus_count = $vegetarian_requested ? teinvit_baptism_rsvp_int( $p, 'vegetarian_menus_count', 0 ) : 0;
        if ( $vegetarian_requested && ( $vegetarian_menus_count < 1 || $vegetarian_menus_count > $total_people ) ) {
            return new WP_Error( 'vegetarian_menus_invalid', 'Numărul de meniuri vegetariene este invalid.', [ 'status' => 400, 'field' => 'vegetarian_menus_count', 'max' => $total_people ] );
        }
    }

    $has_allergies = 0;
    $allergy_details = '';
    if ( $show_allergies ) {
        $has_allergies = teinvit_baptism_rsvp_bool( $p, 'has_allergies' );
        $allergy_details = $has_allergies ? sanitize_textarea_field( (string) ( $p['allergy_details'] ?? '' ) ) : '';
        if ( $has_allergies && trim( $allergy_details ) === '' ) {
            return new WP_Error( 'allergy_details_required', 'Completați alergiile sau restricțiile alimentare.', [ 'status' => 400, 'field' => 'allergy_details' ] );
        }
    }

    $message_to_family = $show_message ? sanitize_textarea_field( (string) ( $p['message_to_family'] ?? '' ) ) : '';

    $common = [
        'token' => $token,
        'guest_first_name' => $first_name,
        'guest_last_name' => $last_name,
        'guest_email' => $email,
        'guest_phone' => $phone,
        'attending_people_count' => $attending_people_count,
        'attending_civil' => 0,
        'attending_religious' => $attending_religious,
        'attending_party' => $attending_party,
        'bringing_kids' => $bringing_kids,
        'kids_count' => $kids_count,
        'needs_accommodation' => $needs_accommodation,
        'accommodation_people_count' => $accommodation_people_count,
        'vegetarian_requested' => $vegetarian_requested,
        'vegetarian_menus_count' => $vegetarian_menus_count,
        'has_allergies' => $has_allergies,
        'allergy_details' => $allergy_details,
        'message_to_couple' => $message_to_family,
        'gdpr_accepted' => 1,
        'marketing_consent' => empty( $p['marketing_consent'] ) ? 0 : 1,
        'created_at' => current_time( 'mysql' ),
    ];

    $extra = [
        'config_snapshot' => $config,
        'message_to_family' => $message_to_family,
        'child_menu_requested' => $child_menu_requested,
        'child_menu_count' => $child_menu_count,
        'child_seat_requested' => $child_seat_requested,
        'child_seat_count' => $child_seat_count,
        'transport_requested' => $transport_requested,
        'transport_people_count' => $transport_people_count,
    ];

    $table = function_exists( 'teinvit_rsvp_table_for_token' ) ? teinvit_rsvp_table_for_token( $token, 'baptism' ) : '';
    if ( $table === '' ) {
        return new WP_Error( 'rsvp_storage_missing', 'Storage RSVP indisponibil.', [ 'status' => 500 ] );
    }

    $insert_data = function_exists( 'teinvit_prepare_hybrid_rsvp_insert_data' )
        ? teinvit_prepare_hybrid_rsvp_insert_data( 'baptism', $common, $extra )
        : array_merge( $common, [ 'extra_fields' => wp_json_encode( $extra ) ] );

    $selected = array_values( array_unique( array_map( 'sanitize_text_field', (array) ( $p['gift_ids'] ?? [] ) ) ) );
    $gifts_table = '';
    if ( ! empty( $selected ) ) {
        $gifts_table = function_exists( 'teinvit_baptism_gifts_table_for_token' )
            ? teinvit_baptism_gifts_table_for_token( $token )
            : ( function_exists( 'teinvit_gifts_table_for_token' ) ? teinvit_gifts_table_for_token( $token, 'baptism' ) : '' );
        if ( $gifts_table === '' ) {
            return new WP_Error( 'gift_storage_missing', 'Storage cadouri indisponibil.', [ 'status' => 500 ] );
        }
    }

    $wpdb->query( 'START TRANSACTION' );
    $wpdb->insert( $table, $insert_data );

    if ( ! $wpdb->insert_id ) {
        $wpdb->query( 'ROLLBACK' );
        return new WP_Error( 'rsvp_failed', 'Nu s-a putut salva RSVP.', [ 'status' => 500 ] );
    }

    $rsvp_id = (int) $wpdb->insert_id;
    foreach ( $selected as $gift_id ) {
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
    if ( function_exists( 'teinvit_touch_invitation_activity' ) ) {
        teinvit_touch_invitation_activity( $token );
    }

    $rsvp_payload = array_merge( $common, [ 'extra_fields' => $extra ] );
    do_action( 'teinvit_rsvp_saved', $token, $rsvp_id, $rsvp_payload );

    return [ 'ok' => true ];
}

function teinvit_baptism_rest_pre_dispatch_rsvp( $result, $server, $request ) {
    if ( null !== $result ) {
        return $result;
    }
    if ( ! $request instanceof WP_REST_Request || strtoupper( (string) $request->get_method() ) !== 'POST' ) {
        return $result;
    }

    $route = (string) $request->get_route();
    if ( ! preg_match( '#^/teinvit/v2/invitati/([^/]+)/rsvp$#', $route, $m ) ) {
        return $result;
    }

    $token = sanitize_text_field( rawurldecode( (string) $m[1] ) );
    if ( $token === '' ) {
        return $result;
    }

    $vertical = function_exists( 'teinvit_resolve_token_vertical' ) ? teinvit_resolve_token_vertical( $token ) : 'wedding';
    if ( $vertical !== 'baptism' ) {
        return $result;
    }

    $request->set_param( 'token', $token );
    return teinvit_baptism_handle_rsvp_rest( $request );
}
add_filter( 'rest_pre_dispatch', 'teinvit_baptism_rest_pre_dispatch_rsvp', 10, 3 );
