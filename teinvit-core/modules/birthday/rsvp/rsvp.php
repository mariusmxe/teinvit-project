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
        'attending_party' => $attending_party,
        'show_guest_count' => $show_guest_count ? 1 : 0,
        'child_menu_requested' => $child_menu_requested,
        'child_menu_count' => $child_menu_count,
        'message_to_celebrants' => $message_to_celebrants,
        'special_observations' => $special_observations,
        'config_snapshot' => [
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
