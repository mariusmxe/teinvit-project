<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function teinvit_baptism_admin_redirect( $token, array $args = [] ) {
    $token = sanitize_text_field( (string) $token );
    $url = home_url( '/admin-client/' . rawurlencode( $token ) );
    if ( ! empty( $args ) ) {
        $url = add_query_arg( $args, $url );
    }

    wp_safe_redirect( $url );
    exit;
}

function teinvit_baptism_admin_client_template( array $context = [] ) {
    $token = sanitize_text_field( (string) ( $context['token'] ?? '' ) );
    if ( $token !== '' ) {
        set_query_var( 'teinvit_admin_client_token', $token );
    }

    ob_start();
    include TEINVIT_BAPTISM_MODULE_PATH . 'templates/page-admin-client.php';
    return ob_get_clean();
}

function teinvit_baptism_invitati_template( array $context = [] ) {
    $token = sanitize_text_field( (string) ( $context['token'] ?? '' ) );
    if ( $token !== '' ) {
        set_query_var( 'teinvit_invitati_token', $token );
    }

    ob_start();
    include TEINVIT_BAPTISM_MODULE_PATH . 'templates/page-invitati.php';
    return ob_get_clean();
}

function teinvit_baptism_read_checkbox( array $source, $key ) {
    return isset( $source[ $key ] ) && ! empty( $source[ $key ] ) ? 1 : 0;
}

function teinvit_baptism_config_with_defaults( array $config = [] ) {
    $defaults = function_exists( 'teinvit_default_rsvp_config_for_vertical' )
        ? teinvit_default_rsvp_config_for_vertical( 'baptism' )
        : [];

    $defaults = array_merge(
        [
            'show_rsvp_deadline' => 0,
            'rsvp_deadline_text' => '',
            'rsvp_deadline_date' => '',
            'show_baptism_religious_info' => 1,
            'show_baptism_party_info' => 1,
            'show_attending_religious' => 1,
            'show_attending_party' => 1,
            'show_attending_people_count' => 1,
            'show_kids' => 0,
            'show_child_menu' => 0,
            'show_child_seat' => 0,
            'show_accommodation' => 0,
            'show_transport' => 0,
            'show_vegetarian' => 0,
            'show_allergies' => 0,
            'show_message' => 1,
            'show_special_observations' => 0,
            'show_gifts_section' => 0,
            'gifts_extra_slots' => 0,
            'edits_free_remaining' => 2,
            'edits_admin_remaining' => 0,
            'edits_paid_remaining' => 0,
        ],
        $defaults
    );

    $config = wp_parse_args( $config, $defaults );
    $config['show_special_observations'] = 0;

    return $config;
}

function teinvit_baptism_admin_post_guard( $token, $required_capability = '' ) {
    $token = sanitize_text_field( (string) $token );
    if ( $token === '' ) {
        teinvit_baptism_admin_redirect( $token, [ 'error' => 'missing' ] );
    }

    $nonce = isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';
    if ( ! wp_verify_nonce( $nonce, 'teinvit_admin_' . $token ) ) {
        teinvit_baptism_admin_redirect( $token, [ 'error' => 'forbidden' ] );
    }

    $ctx = function_exists( 'teinvit_token_access_context' ) ? teinvit_token_access_context( $token ) : new WP_Error( 'missing_guard' );
    if ( is_wp_error( $ctx ) ) {
        teinvit_baptism_admin_redirect( $token, [ 'error' => 'forbidden' ] );
    }

    $vertical = function_exists( 'teinvit_resolve_token_vertical' ) ? teinvit_resolve_token_vertical( $token ) : 'wedding';
    if ( $vertical !== 'baptism' ) {
        teinvit_baptism_admin_redirect( $token, [ 'error' => 'wrong_vertical' ] );
    }

    $inv = function_exists( 'teinvit_get_invitation_record' ) ? teinvit_get_invitation_record( $token, 'baptism' ) : null;
    if ( ! is_array( $inv ) ) {
        teinvit_baptism_admin_redirect( $token, [ 'error' => 'missing' ] );
    }

    $caps = function_exists( 'teinvit_capabilities_for_token' ) ? teinvit_capabilities_for_token( $token ) : [];
    $can_manage_all = function_exists( 'teinvit_user_can_manage_all_tokens' ) && teinvit_user_can_manage_all_tokens();
    if ( $required_capability !== '' && ! $can_manage_all && empty( $caps[ $required_capability ] ) ) {
        teinvit_baptism_admin_redirect( $token, [ 'error' => 'forbidden' ] );
    }

    return [
        'order_id' => (int) $ctx[0],
        'order' => $ctx[1],
        'invitation' => $inv,
        'capabilities' => $caps,
    ];
}

function teinvit_baptism_merge_invitation_info_from_post( array $config, array $source ) {
    $config = teinvit_baptism_config_with_defaults( $config );

    $config['show_rsvp_deadline'] = teinvit_baptism_read_checkbox( $source, 'date_confirm' );
    $config['rsvp_deadline_date'] = sanitize_text_field( wp_unslash( $source['selecteaza_data'] ?? '' ) );
    $config['rsvp_deadline_text'] = $config['rsvp_deadline_date'];
    $config['show_baptism_religious_info'] = teinvit_baptism_read_checkbox( $source, 'show_baptism_religious_info' );
    $config['show_baptism_party_info'] = teinvit_baptism_read_checkbox( $source, 'show_baptism_party_info' );

    return $config;
}

function teinvit_baptism_rsvp_config_keys() {
    return [
        'show_attending_religious',
        'show_attending_party',
        'show_attending_people_count',
        'show_kids',
        'show_child_menu',
        'show_child_seat',
        'show_accommodation',
        'show_transport',
        'show_vegetarian',
        'show_allergies',
        'show_message',
    ];
}

function teinvit_baptism_merge_rsvp_config_from_post( array $config, array $source ) {
    $config = teinvit_baptism_config_with_defaults( $config );
    $published_order = [];

    foreach ( teinvit_baptism_rsvp_config_keys() as $config_key ) {
        $enabled = teinvit_baptism_read_checkbox( $source, $config_key );
        $config[ $config_key ] = $enabled;
        if ( $enabled ) {
            $published_order[] = $config_key;
        }
    }

    $config['show_special_observations'] = 0;
    $config['rsvp_zone2_order'] = $published_order;

    return $config;
}

function teinvit_baptism_snapshot_is_minimally_valid( array $snapshot_invitation ) {
    $children = isset( $snapshot_invitation['children'] ) && is_array( $snapshot_invitation['children'] )
        ? array_values( array_filter( $snapshot_invitation['children'] ) )
        : [];
    $theme = trim( (string) ( $snapshot_invitation['theme'] ?? '' ) );

    return ! empty( $children ) && $theme !== '';
}

function teinvit_baptism_order_product_ids( WC_Order $order ) {
    $product_ids = [];
    foreach ( $order->get_items( 'line_item' ) as $item ) {
        $product_id = (int) $item->get_product_id();
        $variation_id = (int) $item->get_variation_id();
        if ( $product_id > 0 ) {
            $product_ids[] = $product_id;
        }
        if ( $variation_id > 0 ) {
            $product_ids[] = $variation_id;
        }
    }

    return array_values( array_unique( array_filter( array_map( 'intval', $product_ids ) ) ) );
}

function teinvit_baptism_gifts_table_for_token( $token ) {
    if ( function_exists( 'teinvit_gifts_table_for_token' ) ) {
        return (string) teinvit_gifts_table_for_token( $token, 'baptism' );
    }

    if ( function_exists( 'teinvit_storage_tables_for_existing_token' ) ) {
        $tables = teinvit_storage_tables_for_existing_token( $token, 'baptism' );
        return (string) ( $tables['gifts'] ?? '' );
    }

    return '';
}

function teinvit_baptism_rsvp_table_for_token( $token ) {
    if ( function_exists( 'teinvit_rsvp_table_for_token' ) ) {
        return (string) teinvit_rsvp_table_for_token( $token, 'baptism' );
    }

    if ( function_exists( 'teinvit_storage_tables_for_existing_token' ) ) {
        $tables = teinvit_storage_tables_for_existing_token( $token, 'baptism' );
        return (string) ( $tables['rsvp'] ?? '' );
    }

    return '';
}

function teinvit_baptism_gifts_used_count_for_token( $token ) {
    global $wpdb;

    $token = sanitize_text_field( (string) $token );
    if ( $token === '' ) {
        return 0;
    }

    $gifts_table = teinvit_baptism_gifts_table_for_token( $token );
    if ( $gifts_table === '' ) {
        return 0;
    }

    $count = (int) $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(*) FROM {$gifts_table} WHERE token=%s AND (gift_name<>'' OR gift_link<>'')",
            $token
        )
    );

    return max( 0, $count );
}

function teinvit_baptism_gifts_allocation_sort_key( array $allocation ) {
    $kind = (string) ( $allocation['kind'] ?? '' );
    $kind_rank = $kind === 'base' ? 0 : 1;
    $order_id = (int) ( $allocation['order_id'] ?? 0 );
    $item_id = (int) ( $allocation['item_id'] ?? 0 );
    $created = (string) ( $allocation['applied_at'] ?? '' );
    return sprintf( '%d|%s|%010d|%010d', $kind_rank, $created, $order_id, $item_id );
}

function teinvit_baptism_build_gifts_summary_for_token( $token, $config = null ) {
    $token = sanitize_text_field( (string) $token );
    if ( $token === '' ) {
        return [ 'base_slots' => 0, 'addon_slots' => 0, 'admin_slots' => 0, 'total_slots' => 0, 'used_slots' => 0, 'available_slots' => 0, 'allocations' => [] ];
    }

    if ( ! is_array( $config ) ) {
        $inv = function_exists( 'teinvit_get_invitation_record' ) ? teinvit_get_invitation_record( $token, 'baptism' ) : null;
        $config = is_array( $inv['config'] ?? null ) ? $inv['config'] : [];
    }

    $allocations = isset( $config['gifts_allocations'] ) && is_array( $config['gifts_allocations'] ) ? $config['gifts_allocations'] : [];
    $normalized = [];
    foreach ( $allocations as $allocation ) {
        if ( ! is_array( $allocation ) ) {
            continue;
        }

        $slots_total = max( 0, (int) ( $allocation['slots_total'] ?? 0 ) );
        if ( $slots_total <= 0 ) {
            continue;
        }

        $allocation['status'] = (string) ( $allocation['status'] ?? 'applied' );
        if ( $allocation['status'] !== 'applied' && $allocation['status'] !== 'reverted' ) {
            $allocation['status'] = 'applied';
        }
        $allocation['kind'] = (string) ( $allocation['kind'] ?? 'addon' );
        $allocation['slots_total'] = $slots_total;
        $allocation['slots_remaining'] = max( 0, (int) ( $allocation['slots_remaining'] ?? $slots_total ) );
        $allocation['allocation_key'] = sanitize_text_field( (string) ( $allocation['allocation_key'] ?? '' ) );
        if ( $allocation['allocation_key'] === '' ) {
            $allocation['allocation_key'] = ( $allocation['kind'] === 'base' ? 'base' : 'addon' ) . ':' . (int) ( $allocation['order_id'] ?? 0 ) . ':' . (int) ( $allocation['item_id'] ?? 0 );
        }

        $normalized[] = $allocation;
    }

    if ( empty( $normalized ) ) {
        $base_slots = isset( $config['gifts_base_slots_applied'] ) ? max( 0, (int) $config['gifts_base_slots_applied'] ) : 20;
        $addon_slots = isset( $config['gifts_extra_slots'] ) ? max( 0, (int) $config['gifts_extra_slots'] ) : 0;

        if ( $base_slots > 0 ) {
            $normalized[] = [
                'allocation_key' => 'legacy-base',
                'kind' => 'base',
                'order_id' => 0,
                'item_id' => 0,
                'slots_total' => $base_slots,
                'slots_remaining' => $base_slots,
                'status' => 'applied',
                'applied_at' => '',
            ];
        }
        if ( $addon_slots > 0 ) {
            $normalized[] = [
                'allocation_key' => 'legacy-addon',
                'kind' => 'addon',
                'order_id' => 0,
                'item_id' => 0,
                'slots_total' => $addon_slots,
                'slots_remaining' => $addon_slots,
                'status' => 'applied',
                'applied_at' => '',
            ];
        }
    }

    usort( $normalized, static function( $a, $b ) {
        return strcmp( teinvit_baptism_gifts_allocation_sort_key( $a ), teinvit_baptism_gifts_allocation_sort_key( $b ) );
    } );

    $used = teinvit_baptism_gifts_used_count_for_token( $token );
    $remaining_to_consume = $used;
    $base_slots = 0;
    $addon_slots = 0;
    $admin_slots = 0;
    foreach ( $normalized as &$allocation ) {
        if ( (string) $allocation['status'] !== 'applied' ) {
            $allocation['slots_remaining'] = max( 0, (int) $allocation['slots_remaining'] );
            continue;
        }

        $total = (int) $allocation['slots_total'];
        $consume = min( $remaining_to_consume, $total );
        $remaining_to_consume -= $consume;
        $allocation['slots_remaining'] = max( 0, $total - $consume );
        if ( (string) $allocation['kind'] === 'base' ) {
            $base_slots += $total;
        } elseif ( (string) $allocation['kind'] === 'admin_grant' ) {
            $admin_slots += $total;
        } else {
            $addon_slots += $total;
        }
    }
    unset( $allocation );

    $total_slots = max( 0, $base_slots + $addon_slots + $admin_slots );
    $available = max( 0, $total_slots - $used );

    return [
        'base_slots' => $base_slots,
        'addon_slots' => $addon_slots,
        'admin_slots' => $admin_slots,
        'total_slots' => $total_slots,
        'used_slots' => $used,
        'available_slots' => $available,
        'allocations' => $normalized,
    ];
}

function teinvit_baptism_token_total_gift_slots( $token, $config = null ) {
    $summary = teinvit_baptism_build_gifts_summary_for_token( $token, $config );
    return max( 0, (int) ( $summary['total_slots'] ?? 0 ) );
}

function teinvit_baptism_decode_extra_fields( $value ) {
    if ( is_array( $value ) ) {
        return $value;
    }

    $decoded = json_decode( (string) $value, true );
    return is_array( $decoded ) ? $decoded : [];
}

function teinvit_baptism_normalize_phone_for_report( $phone ) {
    $raw = trim( (string) $phone );
    $digits = preg_replace( '/\D+/', '', $raw );
    if ( $digits === '' ) {
        return '';
    }

    if ( strpos( $digits, '0040' ) === 0 ) {
        $local = substr( $digits, 4 );
    } elseif ( strpos( $digits, '40' ) === 0 ) {
        $local = substr( $digits, 2 );
    } elseif ( strpos( $digits, '0' ) === 0 ) {
        $local = substr( $digits, 1 );
    } else {
        $local = $digits;
    }

    if ( strpos( $local, '0' ) === 0 ) {
        $local = substr( $local, 1 );
    }

    if ( preg_match( '/^7\d{8}$/', $local ) ) {
        return '40' . $local;
    }

    return $digits;
}

function teinvit_baptism_format_report_datetime( $mysql_datetime ) {
    $ts = strtotime( (string) $mysql_datetime );
    if ( ! $ts ) {
        return '';
    }

    return wp_date( 'd-m-Y H:i', $ts );
}

function teinvit_baptism_enrich_rsvp_report_row( array $row ) {
    $extra = teinvit_baptism_decode_extra_fields( $row['extra_fields'] ?? '' );

    $row['baptism_extra_fields'] = $extra;
    $row['created_at_display'] = teinvit_baptism_format_report_datetime( $row['created_at'] ?? '' );
    $row['normalized_phone'] = teinvit_baptism_normalize_phone_for_report( (string) ( $row['guest_phone'] ?? '' ) );
    $row['message_to_family'] = (string) ( $extra['message_to_family'] ?? ( $row['message_to_couple'] ?? '' ) );
    $row['child_menu_requested'] = ! empty( $extra['child_menu_requested'] ) ? 1 : 0;
    $row['child_menu_count'] = max( 0, (int) ( $extra['child_menu_count'] ?? 0 ) );
    $row['child_seat_requested'] = ! empty( $extra['child_seat_requested'] ) ? 1 : 0;
    $row['child_seat_count'] = max( 0, (int) ( $extra['child_seat_count'] ?? 0 ) );
    $row['transport_requested'] = ! empty( $extra['transport_requested'] ) ? 1 : 0;
    $row['transport_people_count'] = max( 0, (int) ( $extra['transport_people_count'] ?? 0 ) );

    return $row;
}

function teinvit_baptism_get_rsvp_rows_for_report( $token ) {
    global $wpdb;

    $token = sanitize_text_field( (string) $token );
    if ( $token === '' ) {
        return [];
    }

    $table = teinvit_baptism_rsvp_table_for_token( $token );
    if ( $table === '' ) {
        return [];
    }

    $rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE token=%s ORDER BY created_at ASC, id ASC", $token ), ARRAY_A );
    if ( ! is_array( $rows ) ) {
        return [];
    }

    return array_map( 'teinvit_baptism_enrich_rsvp_report_row', $rows );
}

function teinvit_baptism_build_rsvp_report_sets( $token ) {
    $rows = teinvit_baptism_get_rsvp_rows_for_report( $token );
    $history = [];
    $by_phone = [];

    foreach ( $rows as $row ) {
        $phone = (string) ( $row['normalized_phone'] ?? '' );
        if ( $phone === '' ) {
            $phone = 'row-' . (int) ( $row['id'] ?? 0 );
        }
        if ( ! isset( $by_phone[ $phone ] ) ) {
            $by_phone[ $phone ] = [];
        }
        $by_phone[ $phone ][] = $row;
    }

    foreach ( $by_phone as $list ) {
        $n = count( $list );
        foreach ( $list as $idx => $row ) {
            $row['multi_badge'] = $n > 1 ? sprintf( 'MULTI #%d/%d', $idx + 1, $n ) : '';
            $row['is_multi'] = $n > 1 ? 1 : 0;
            $history[] = $row;
        }
    }

    usort( $history, static function( $a, $b ) {
        $cmp = strcmp( (string) ( $a['created_at'] ?? '' ), (string) ( $b['created_at'] ?? '' ) );
        if ( $cmp !== 0 ) {
            return $cmp;
        }
        return (int) ( $a['id'] ?? 0 ) <=> (int) ( $b['id'] ?? 0 );
    } );

    $unique = [];
    foreach ( $by_phone as $list ) {
        usort( $list, static function( $a, $b ) {
            $cmp = strcmp( (string) ( $a['created_at'] ?? '' ), (string) ( $b['created_at'] ?? '' ) );
            if ( $cmp !== 0 ) {
                return $cmp;
            }
            return (int) ( $a['id'] ?? 0 ) <=> (int) ( $b['id'] ?? 0 );
        } );
        $last = end( $list );
        if ( ! $last ) {
            continue;
        }
        $last['multi_badge'] = count( $list ) > 1 ? sprintf( 'MULTI #%d/%d', count( $list ), count( $list ) ) : '';
        $last['is_multi'] = count( $list ) > 1 ? 1 : 0;
        $unique[] = $last;
    }

    usort( $unique, static function( $a, $b ) {
        $cmp = strcmp( (string) ( $a['created_at'] ?? '' ), (string) ( $b['created_at'] ?? '' ) );
        if ( $cmp !== 0 ) {
            return $cmp;
        }
        return (int) ( $a['id'] ?? 0 ) <=> (int) ( $b['id'] ?? 0 );
    } );

    return [
        'history' => $history,
        'unique' => $unique,
        'multiple_phones_count' => count( array_filter( $by_phone, static function( $list ) {
            return count( $list ) > 1;
        } ) ),
        'unique_phones_count' => count( $by_phone ),
        'submissions_count' => count( $rows ),
        'messages_count_history' => count( array_filter( $rows, static function( $r ) {
            return trim( (string) ( $r['message_to_family'] ?? '' ) ) !== '';
        } ) ),
    ];
}

function teinvit_baptism_report_total_people( array $row ) {
    $adults = max( 0, (int) ( $row['attending_people_count'] ?? 0 ) );
    $kids = ! empty( $row['bringing_kids'] ) ? max( 0, (int) ( $row['kids_count'] ?? 0 ) ) : 0;
    return $adults + $kids;
}

function teinvit_baptism_build_rsvp_report_kpis( $sets, array $config = [] ) {
    $unique = is_array( $sets['unique'] ?? null ) ? $sets['unique'] : [];
    $history = is_array( $sets['history'] ?? null ) ? $sets['history'] : [];

    $religious_people = 0;
    $party_people = 0;
    $children_total = 0;
    $veg_menus_total = 0;
    $allergies_count = 0;

    foreach ( $unique as $r ) {
        $total_people = teinvit_baptism_report_total_people( $r );
        if ( ! empty( $r['attending_religious'] ) ) {
            $religious_people += $total_people;
        }
        if ( ! empty( $r['attending_party'] ) ) {
            $party_people += $total_people;
        }
        if ( ! empty( $r['bringing_kids'] ) ) {
            $children_total += max( 0, (int) ( $r['kids_count'] ?? 0 ) );
        }
        if ( ! empty( $r['vegetarian_requested'] ) ) {
            $veg_menus_total += max( 0, (int) ( $r['vegetarian_menus_count'] ?? 0 ) );
        }
        if ( ! empty( $r['has_allergies'] ) ) {
            $allergies_count++;
        }
    }

    return [
        'Confirmări unice' => (string) (int) ( $sets['unique_phones_count'] ?? count( $unique ) ),
        'Confirmări totale completate' => (string) (int) ( $sets['submissions_count'] ?? count( $history ) ),
        'Confirmări multiple' => (string) (int) ( $sets['multiple_phones_count'] ?? 0 ),
        'Persoane la Slujba de botez' => (string) (int) $religious_people,
        'Persoane la petrecerea de botez' => (string) (int) $party_people,
        'Total copii' => (string) (int) $children_total,
        'Meniuri vegetariene' => (string) (int) $veg_menus_total,
        'Răspunsuri cu alergii/restricții' => (string) (int) $allergies_count,
        'Mesaje primite' => (string) count( array_filter( $history, static function( $r ) {
            return trim( (string) ( $r['message_to_family'] ?? '' ) ) !== '';
        } ) ),
    ];
}

function teinvit_baptism_report_headers() {
    return [
        'Status',
        'Nume',
        'Prenume',
        'Telefon',
        'Email',
        'Data/ora submit',
        'Participă la Slujba de botez',
        'Participă la petrecerea de botez',
        'Adulți confirmați',
        'Vine cu copii',
        'Câți copii',
        'Meniu copil',
        'Meniuri copil',
        'Scaun copil',
        'Număr scaune copil',
        'Cazare',
        'Cazare nr. persoane',
        'Transport între biserică și restaurant',
        'Transport nr. persoane',
        'Vegetarian',
        'Meniuri vegetariene',
        'Alergii/restricții',
        'Detalii alergii/restricții',
        'Mesaj pentru familie/copil',
    ];
}

function teinvit_baptism_report_row_values( array $r ) {
    $yn = static function( $v ) {
        return (int) $v === 1 ? 'DA' : 'NU';
    };
    $children = ! empty( $r['bringing_kids'] ) ? max( 0, (int) ( $r['kids_count'] ?? 0 ) ) : 0;
    $child_menu_requested = ! empty( $r['child_menu_requested'] );
    $child_seat_requested = ! empty( $r['child_seat_requested'] );
    $needs_accommodation = ! empty( $r['needs_accommodation'] );
    $transport_requested = ! empty( $r['transport_requested'] );
    $vegetarian_requested = ! empty( $r['vegetarian_requested'] );
    $has_allergies = ! empty( $r['has_allergies'] );

    return [
        (string) ( $r['multi_badge'] ?? '' ),
        (string) ( $r['guest_last_name'] ?? '' ),
        (string) ( $r['guest_first_name'] ?? '' ),
        (string) ( $r['guest_phone'] ?? '' ),
        (string) ( $r['guest_email'] ?? '' ),
        (string) ( $r['created_at_display'] ?? teinvit_baptism_format_report_datetime( $r['created_at'] ?? '' ) ),
        $yn( $r['attending_religious'] ?? 0 ),
        $yn( $r['attending_party'] ?? 0 ),
        (string) max( 0, (int) ( $r['attending_people_count'] ?? 0 ) ),
        $yn( $r['bringing_kids'] ?? 0 ),
        ! empty( $r['bringing_kids'] ) ? (string) $children : '-',
        $child_menu_requested ? 'DA' : 'NU',
        $child_menu_requested ? (string) max( 0, (int) ( $r['child_menu_count'] ?? 0 ) ) : '-',
        $child_seat_requested ? 'DA' : 'NU',
        $child_seat_requested ? (string) max( 0, (int) ( $r['child_seat_count'] ?? 0 ) ) : '-',
        $yn( $r['needs_accommodation'] ?? 0 ),
        $needs_accommodation ? (string) max( 0, (int) ( $r['accommodation_people_count'] ?? 0 ) ) : '-',
        $transport_requested ? 'DA' : 'NU',
        $transport_requested ? (string) max( 0, (int) ( $r['transport_people_count'] ?? 0 ) ) : '-',
        $yn( $r['vegetarian_requested'] ?? 0 ),
        $vegetarian_requested ? (string) max( 0, (int) ( $r['vegetarian_menus_count'] ?? 0 ) ) : '-',
        $yn( $r['has_allergies'] ?? 0 ),
        $has_allergies ? (string) ( $r['allergy_details'] ?? '' ) : '-',
        trim( (string) ( $r['message_to_family'] ?? '' ) ) !== '' ? (string) $r['message_to_family'] : '-',
    ];
}

function teinvit_baptism_xlsx_safe_text( $value ) {
    if ( function_exists( 'teinvit_xlsx_safe_text' ) ) {
        return teinvit_xlsx_safe_text( $value );
    }

    $text = wp_check_invalid_utf8( (string) $value, true );
    if ( ! is_string( $text ) ) {
        $text = '';
    }

    if ( function_exists( 'mb_convert_encoding' ) ) {
        $text = mb_convert_encoding( $text, 'UTF-8', 'UTF-8' );
    } elseif ( function_exists( 'iconv' ) ) {
        $conv = @iconv( 'UTF-8', 'UTF-8//IGNORE', $text );
        if ( $conv !== false ) {
            $text = $conv;
        }
    }

    $clean = preg_replace( '/[^\x09\x0A\x0D\x20-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', $text );
    return $clean === null ? '' : $clean;
}

function teinvit_baptism_xlsx_sheet_xml( array $rows ) {
    $xml = '<?xml version="1.0" encoding="UTF-8"?>';
    $xml .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';
    $xml .= '<sheetData>';

    foreach ( $rows as $ri => $cells ) {
        $row_num = $ri + 1;
        $xml .= '<row r="' . $row_num . '">';
        foreach ( $cells as $ci => $value ) {
            $col = '';
            $n = $ci;
            do {
                $col = chr( 65 + ( $n % 26 ) ) . $col;
                $n = intdiv( $n, 26 ) - 1;
            } while ( $n >= 0 );

            $ref = $col . $row_num;
            $safe = teinvit_baptism_xlsx_safe_text( $value );
            $val = htmlspecialchars( $safe, ENT_QUOTES | ENT_XML1 | ENT_SUBSTITUTE, 'UTF-8' );
            $xml .= '<c r="' . $ref . '" t="inlineStr"><is><t>' . $val . '</t></is></c>';
        }
        $xml .= '</row>';
    }

    $xml .= '</sheetData>';
    $xml .= '</worksheet>';
    return $xml;
}

function teinvit_baptism_report_filename_label( $token ) {
    $active = function_exists( 'teinvit_get_active_snapshot_for_token_from_storage' )
        ? teinvit_get_active_snapshot_for_token_from_storage( $token, 'baptism' )
        : null;
    $payload = ! empty( $active['snapshot'] ) ? json_decode( (string) $active['snapshot'], true ) : [];
    $invitation = isset( $payload['invitation'] ) && is_array( $payload['invitation'] ) ? $payload['invitation'] : [];
    $children = isset( $invitation['children'] ) && is_array( $invitation['children'] ) ? $invitation['children'] : [];
    $names = function_exists( 'teinvit_join_ro_names' ) ? teinvit_join_ro_names( $children ) : implode( ' ', array_filter( array_map( 'trim', $children ) ) );

    return trim( $names ) !== '' ? trim( $names ) : (string) $token;
}

add_action( 'admin_post_teinvit_baptism_save_invitation_info', function() {
    $token = sanitize_text_field( wp_unslash( $_POST['token'] ?? '' ) );
    $ctx = teinvit_baptism_admin_post_guard( $token, 'can_save_invitation_info' );
    $inv = $ctx['invitation'];
    $config = teinvit_baptism_config_with_defaults( is_array( $inv['config'] ?? null ) ? $inv['config'] : [] );
    $config = teinvit_baptism_merge_invitation_info_from_post( $config, $_POST );

    teinvit_save_invitation_config_for_token( $token, [ 'config' => $config ], 'baptism' );
    teinvit_baptism_admin_redirect( $token, [ 'saved' => 'info' ] );
} );

add_action( 'admin_post_teinvit_baptism_save_rsvp_config', function() {
    $token = sanitize_text_field( wp_unslash( $_POST['token'] ?? '' ) );
    $ctx = teinvit_baptism_admin_post_guard( $token, 'can_save_rsvp_config' );
    $inv = $ctx['invitation'];
    $config = teinvit_baptism_config_with_defaults( is_array( $inv['config'] ?? null ) ? $inv['config'] : [] );
    $config = teinvit_baptism_merge_rsvp_config_from_post( $config, $_POST );

    teinvit_save_invitation_config_for_token( $token, [ 'config' => $config ], 'baptism' );
    teinvit_baptism_admin_redirect( $token, [ 'saved' => 'config' ] );
} );

add_action( 'admin_post_teinvit_baptism_save_gifts', function() {
    global $wpdb;

    $token = sanitize_text_field( wp_unslash( $_POST['token'] ?? '' ) );
    $ctx = teinvit_baptism_admin_post_guard( $token, 'can_manage_gifts' );
    $inv = $ctx['invitation'];

    $gifts_table = teinvit_baptism_gifts_table_for_token( $token );
    if ( $gifts_table === '' ) {
        teinvit_baptism_admin_redirect( $token, [ 'error' => 'storage' ] );
    }

    $config = teinvit_baptism_config_with_defaults( is_array( $inv['config'] ?? null ) ? $inv['config'] : [] );
    $config['show_gifts_section'] = isset( $_POST['show_gifts_section'] ) ? 1 : 0;
    $max_slots = teinvit_baptism_token_total_gift_slots( $token, $config );

    $existing_rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$gifts_table} WHERE token=%s ORDER BY id ASC", $token ), ARRAY_A );
    $existing_rows = is_array( $existing_rows ) ? $existing_rows : [];
    $existing_map = [];
    foreach ( $existing_rows as $row ) {
        $existing_map[ (string) ( $row['gift_id'] ?? '' ) ] = $row;
    }

    $rows = isset( $_POST['gifts'] ) && is_array( $_POST['gifts'] ) ? $_POST['gifts'] : [];
    $entered_count = 0;
    foreach ( $rows as $gift ) {
        $name = sanitize_text_field( wp_unslash( $gift['gift_name'] ?? '' ) );
        $link = esc_url_raw( wp_unslash( $gift['gift_link'] ?? '' ) );
        if ( $name !== '' || $link !== '' ) {
            $entered_count++;
        }
    }

    if ( $entered_count > $max_slots ) {
        teinvit_baptism_admin_redirect( $token, [ 'error' => 'gifts_limit' ] );
    }

    $posted_ids = [];
    foreach ( $rows as $index => $gift ) {
        $gift_id = sanitize_text_field( wp_unslash( $gift['gift_id'] ?? '' ) );
        if ( $gift_id === '' ) {
            $gift_id = 'gift-' . wp_generate_password( 10, false, false ) . '-' . (int) $index;
        }
        $posted_ids[] = $gift_id;

        $include = ! empty( $gift['include_in_public'] ) ? 1 : 0;
        $name = sanitize_text_field( wp_unslash( $gift['gift_name'] ?? '' ) );
        $link = esc_url_raw( wp_unslash( $gift['gift_link'] ?? '' ) );
        $address = sanitize_textarea_field( wp_unslash( $gift['gift_delivery_address'] ?? '' ) );
        $is_complete = ( $name !== '' || $link !== '' );
        $existing = isset( $existing_map[ $gift_id ] ) ? $existing_map[ $gift_id ] : null;

        if ( $existing && ! empty( $existing['published_locked'] ) ) {
            $update_data = [ 'include_in_public' => $include ];
            if ( trim( (string) ( $existing['gift_name'] ?? '' ) ) === '' && $name !== '' ) {
                $update_data['gift_name'] = $name;
            }
            if ( trim( (string) ( $existing['gift_link'] ?? '' ) ) === '' && $link !== '' ) {
                $update_data['gift_link'] = $link;
            }
            if ( trim( (string) ( $existing['gift_delivery_address'] ?? '' ) ) === '' && $address !== '' ) {
                $update_data['gift_delivery_address'] = $address;
            }

            $wpdb->update( $gifts_table, $update_data, [ 'id' => (int) $existing['id'] ] );
            continue;
        }

        $payload = [
            'token' => $token,
            'gift_id' => $gift_id,
            'gift_name' => $name,
            'gift_link' => $link,
            'gift_delivery_address' => $address,
            'include_in_public' => $include,
            'published_locked' => ( $include && $is_complete ) ? 1 : 0,
            'locked_at' => ( $include && $is_complete ) ? current_time( 'mysql' ) : null,
            'status' => $existing ? (string) ( $existing['status'] ?? 'free' ) : 'free',
            'reserved_by_rsvp_id' => $existing ? (int) ( $existing['reserved_by_rsvp_id'] ?? 0 ) : null,
            'reserved_at' => $existing ? ( $existing['reserved_at'] ?? null ) : null,
        ];

        if ( $existing ) {
            $wpdb->update( $gifts_table, $payload, [ 'id' => (int) $existing['id'] ] );
        } else {
            $wpdb->insert( $gifts_table, $payload );
        }
    }

    foreach ( $existing_rows as $row ) {
        if ( in_array( (string) ( $row['gift_id'] ?? '' ), $posted_ids, true ) ) {
            continue;
        }
        if ( ! empty( $row['published_locked'] ) ) {
            continue;
        }
        $wpdb->delete( $gifts_table, [ 'id' => (int) $row['id'] ] );
    }

    $summary_after = teinvit_baptism_build_gifts_summary_for_token( $token, $config );
    $config['gifts_allocations'] = $summary_after['allocations'];
    $config['gifts_base_slots_applied'] = (int) $summary_after['base_slots'];
    $config['gifts_extra_slots'] = (int) $summary_after['addon_slots'];
    $config['gifts_admin_slots'] = (int) ( $summary_after['admin_slots'] ?? 0 );
    $config['gifts_total_slots_applied'] = (int) $summary_after['total_slots'];
    $config['gifts_slots_used'] = (int) $summary_after['used_slots'];
    $config['gifts_slots_available'] = (int) $summary_after['available_slots'];

    teinvit_save_invitation_config_for_token( $token, [ 'config' => $config ], 'baptism' );
    teinvit_baptism_admin_redirect( $token, [ 'saved' => 'gifts' ] );
} );

add_action( 'admin_post_teinvit_baptism_set_active_version', function() {
    global $wpdb;

    $token = sanitize_text_field( wp_unslash( $_POST['token'] ?? '' ) );
    teinvit_baptism_admin_post_guard( $token, 'can_set_active_version' );

    $version_id = (int) ( $_POST['active_version_id'] ?? 0 );
    $tables = function_exists( 'teinvit_storage_tables_for_existing_token' ) ? teinvit_storage_tables_for_existing_token( $token, 'baptism' ) : [];
    $exists = 0;
    if ( ! empty( $tables['versions'] ) && $version_id > 0 ) {
        $exists = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$tables['versions']} WHERE token=%s AND id=%d", $token, $version_id ) );
    }

    if ( $exists ) {
        teinvit_save_invitation_config_for_token( $token, [ 'active_version_id' => $version_id ], 'baptism' );
    }

    teinvit_baptism_admin_redirect( $token, [
        'saved' => $exists ? 'active' : 'missing_version',
        'selected_version_id' => $version_id,
    ] );
} );

add_action( 'admin_post_teinvit_baptism_save_version_snapshot', function() {
    global $wpdb;

    $token = sanitize_text_field( wp_unslash( $_POST['token'] ?? '' ) );
    $ctx = teinvit_baptism_admin_post_guard( $token, 'can_save_version_snapshot' );
    $order_id = (int) $ctx['order_id'];
    $order = $ctx['order'];
    $inv = $ctx['invitation'];

    $config = teinvit_baptism_config_with_defaults( is_array( $inv['config'] ?? null ) ? $inv['config'] : [] );
    if ( function_exists( 'teinvit_config_ensure_edit_balance_keys' ) ) {
        $config = teinvit_config_ensure_edit_balance_keys( $config );
        $edit_balance = teinvit_edit_balance_summary( $config );
        $free_remaining = (int) $edit_balance['free'];
        $admin_remaining = (int) $edit_balance['admin'];
        $paid_remaining = (int) $edit_balance['paid'];
        $remaining = (int) $edit_balance['total'];
    } else {
        $free_remaining = max( 0, (int) ( $config['edits_free_remaining'] ?? 2 ) );
        $admin_remaining = max( 0, (int) ( $config['edits_admin_remaining'] ?? 0 ) );
        $paid_remaining = max( 0, (int) ( $config['edits_paid_remaining'] ?? 0 ) );
        $remaining = $free_remaining + $admin_remaining + $paid_remaining;
    }
    if ( $remaining <= 0 ) {
        teinvit_baptism_admin_redirect( $token, [ 'error' => 'noedits' ] );
    }

    $wapf = function_exists( 'teinvit_extract_posted_wapf_map' ) ? teinvit_extract_posted_wapf_map( $_POST ) : [];
    $product_id = function_exists( 'teinvit_get_order_primary_product_id' ) ? (int) teinvit_get_order_primary_product_id( $order ) : 0;
    $built = function_exists( 'teinvit_build_invitation_payload_from_wapf_map' )
        ? teinvit_build_invitation_payload_from_wapf_map( 'baptism', $wapf, $product_id )
        : [ 'invitation' => [], 'wapf_fields' => $wapf ];

    $snapshot_invitation = isset( $built['invitation'] ) && is_array( $built['invitation'] ) ? $built['invitation'] : [];
    $snapshot_wapf = isset( $built['wapf_fields'] ) && is_array( $built['wapf_fields'] ) ? $built['wapf_fields'] : $wapf;
    if ( ! teinvit_baptism_snapshot_is_minimally_valid( $snapshot_invitation ) ) {
        teinvit_baptism_admin_redirect( $token, [ 'error' => 'invalid_snapshot' ] );
    }

    $snapshot = [
        'invitation' => $snapshot_invitation,
        'wapf_fields' => $snapshot_wapf,
        'meta' => [
            'order_id' => $order_id,
            'vertical' => 'baptism',
        ],
    ];
    $snapshot_json = wp_json_encode( $snapshot );
    $tables = function_exists( 'teinvit_storage_tables_for_existing_token' ) ? teinvit_storage_tables_for_existing_token( $token, 'baptism' ) : [];
    if ( empty( $tables['versions'] ) ) {
        teinvit_baptism_admin_redirect( $token, [ 'error' => 'storage' ] );
    }

    $wpdb->insert( $tables['versions'], [
        'token' => $token,
        'snapshot' => $snapshot_json,
        'created_at' => current_time( 'mysql' ),
    ] );

    $version_id = (int) $wpdb->insert_id;
    if ( $version_id <= 0 ) {
        teinvit_baptism_admin_redirect( $token, [ 'error' => 'version_failed' ] );
    }

    $pdf_status = 'none';
    $pdf_url = '';
    $pdf_filename = '';
    $version_index = max( 0, (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$tables['versions']} WHERE token = %s AND id <= %d", $token, $version_id ) ) - 1 );

    if ( function_exists( 'teinvit_pdf_filename_for_version' ) && function_exists( 'teinvit_generate_pdf_for_version' ) ) {
        $pdf_filename = teinvit_pdf_filename_for_version( $order, $version_index );
        $wpdb->update( $tables['versions'], [
            'pdf_status' => 'processing',
            'pdf_filename' => $pdf_filename,
        ], [ 'id' => $version_id ] );

        $pdf_result = teinvit_generate_pdf_for_version( $token, $order_id, $pdf_filename, $version_id );
        if ( is_wp_error( $pdf_result ) ) {
            $pdf_status = 'failed';
        } else {
            $pdf_status = 'ready';
            $pdf_url = (string) ( $pdf_result['pdf_url'] ?? '' );
        }

        $wpdb->update( $tables['versions'], [
            'pdf_status' => $pdf_status,
            'pdf_url' => $pdf_url,
            'pdf_generated_at' => current_time( 'mysql' ),
            'pdf_filename' => $pdf_filename,
        ], [ 'id' => $version_id ] );
    }

    if ( function_exists( 'teinvit_config_consume_one_edit' ) ) {
        $config = teinvit_config_consume_one_edit( $config );
    } elseif ( $free_remaining > 0 ) {
        $config['edits_free_remaining'] = max( 0, $free_remaining - 1 );
    } elseif ( $admin_remaining > 0 ) {
        $config['edits_admin_remaining'] = max( 0, $admin_remaining - 1 );
    } else {
        $config['edits_paid_remaining'] = max( 0, $paid_remaining - 1 );
    }

    teinvit_save_invitation_config_for_token( $token, [ 'config' => $config ], 'baptism' );

    do_action( 'teinvit_invitation_version_saved', $token, $version_id, [
        'token' => $token,
        'order_id' => $order_id,
        'vertical' => 'baptism',
        'version_id' => $version_id,
        'version_index' => $version_index,
        'active_version_id' => (int) ( $inv['active_version_id'] ?? 0 ),
        'pdf_status' => $pdf_status,
        'pdf_url' => $pdf_url,
        'pdf_filename' => $pdf_filename,
        'admin_client_url' => home_url( '/admin-client/' . rawurlencode( $token ) ),
        'invitati_url' => home_url( '/invitati/' . rawurlencode( $token ) ),
        'product_ids' => teinvit_baptism_order_product_ids( $order ),
        'snapshot_hash' => hash( 'sha256', (string) $snapshot_json ),
    ] );

    teinvit_baptism_admin_redirect( $token, [
        'saved' => 'version',
        'selected_version_id' => $version_id,
    ] );
} );

function teinvit_baptism_export_guest_report_handler() {
    $token = sanitize_text_field( wp_unslash( $_GET['token'] ?? '' ) );
    $nonce = sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ?? '' ) );

    if ( $token === '' || ! wp_verify_nonce( $nonce, 'teinvit_admin_' . $token ) ) {
        wp_die( 'Nonce invalid' );
    }

    $ctx = function_exists( 'teinvit_token_access_context' ) ? teinvit_token_access_context( $token ) : new WP_Error( 'missing_guard' );
    if ( is_wp_error( $ctx ) ) {
        wp_die( esc_html( $ctx->get_error_message() ) );
    }

    $caps = function_exists( 'teinvit_capabilities_for_token' ) ? teinvit_capabilities_for_token( $token ) : [];
    $can_manage_all = function_exists( 'teinvit_user_can_manage_all_tokens' ) && teinvit_user_can_manage_all_tokens();
    if ( ! $can_manage_all && empty( $caps['can_save_rsvp_config'] ) ) {
        wp_die( 'Exportul este disponibil doar pentru pachetul Premium.' );
    }

    $vertical = function_exists( 'teinvit_resolve_token_vertical' ) ? teinvit_resolve_token_vertical( $token ) : 'wedding';
    if ( $vertical !== 'baptism' ) {
        wp_die( 'Exportul Baptism este disponibil doar pentru tokenuri Baptism.' );
    }

    $inv = function_exists( 'teinvit_get_invitation_record' ) ? teinvit_get_invitation_record( $token, 'baptism' ) : null;
    if ( ! is_array( $inv ) ) {
        wp_die( 'Token invalid' );
    }

    if ( ! class_exists( 'ZipArchive' ) ) {
        wp_die( 'ZipArchive lipsă pentru XLSX export.' );
    }

    $config = teinvit_baptism_config_with_defaults( is_array( $inv['config'] ?? null ) ? $inv['config'] : [] );
    $sets = teinvit_baptism_build_rsvp_report_sets( $token );
    $unique = is_array( $sets['unique'] ?? null ) ? $sets['unique'] : [];
    $history = is_array( $sets['history'] ?? null ) ? $sets['history'] : [];
    $headers = teinvit_baptism_report_headers();
    $rows_unique = array_map( 'teinvit_baptism_report_row_values', $unique );
    $rows_history = array_map( 'teinvit_baptism_report_row_values', $history );
    $kpis = teinvit_baptism_build_rsvp_report_kpis( $sets, $config );

    $summary_rows = [ [ 'Metrică', 'Valoare' ] ];
    foreach ( $kpis as $metric => $value ) {
        $summary_rows[] = [ (string) $metric, (string) $value ];
    }

    $label = teinvit_baptism_report_filename_label( $token );
    $filename = sanitize_file_name( 'Raport invitati Baptism ' . $label ) . '.xlsx';

    $tmp = wp_tempnam( 'teinvit-baptism-report-' . $token . '.xlsx' );
    if ( ! $tmp ) {
        wp_die( 'Nu s-a putut crea fișierul temporar pentru export.' );
    }

    $zip = new ZipArchive();
    if ( true !== $zip->open( $tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
        @unlink( $tmp );
        wp_die( 'Nu s-a putut inițializa exportul XLSX.' );
    }
    $zip->addFromString( '[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/worksheets/sheet2.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/worksheets/sheet3.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>' );
    $zip->addFromString( '_rels/.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>' );
    $zip->addFromString( 'xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Rezumat" sheetId="1" r:id="rId1"/><sheet name="Unic" sheetId="2" r:id="rId2"/><sheet name="Istoric" sheetId="3" r:id="rId3"/></sheets></workbook>' );
    $zip->addFromString( 'xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet2.xml"/><Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet3.xml"/></Relationships>' );
    $zip->addFromString( 'xl/worksheets/sheet1.xml', teinvit_baptism_xlsx_sheet_xml( $summary_rows ) );
    $zip->addFromString( 'xl/worksheets/sheet2.xml', teinvit_baptism_xlsx_sheet_xml( array_merge( [ $headers ], $rows_unique ) ) );
    $zip->addFromString( 'xl/worksheets/sheet3.xml', teinvit_baptism_xlsx_sheet_xml( array_merge( [ $headers ], $rows_history ) ) );
    $zip->close();

    if ( function_exists( 'teinvit_validate_generated_xlsx_xml' ) ) {
        $xlsx_validation = teinvit_validate_generated_xlsx_xml( $tmp );
        if ( is_wp_error( $xlsx_validation ) ) {
            error_log( '[TeInvit Baptism] XLSX validation failed: ' . $xlsx_validation->get_error_message() );
            @unlink( $tmp );
            wp_die( 'Export XLSX invalid. Reîncearcă sau contactează suportul.' );
        }
    }

    $xlsx_md5 = md5_file( $tmp );

    if ( function_exists( 'ini_set' ) ) {
        @ini_set( 'zlib.output_compression', 'Off' );
    }
    if ( function_exists( 'apache_setenv' ) ) {
        @apache_setenv( 'no-gzip', '1' );
    }

    while ( ob_get_level() > 0 ) {
        ob_end_clean();
    }

    nocache_headers();
    header( 'Content-Description: File Transfer' );
    header( 'Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' );
    header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
    header( 'Content-Transfer-Encoding: binary' );
    header( 'X-TeInvit-XLSX-MD5: ' . $xlsx_md5 );
    readfile( $tmp );
    @unlink( $tmp );
    exit;
}
add_action( 'admin_post_teinvit_baptism_export_guest_report', 'teinvit_baptism_export_guest_report_handler' );
