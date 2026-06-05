<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function teinvit_product_gallery_logs_table() {
    global $wpdb;
    return $wpdb->prefix . 'teinvit_product_gallery_logs';
}

function teinvit_install_product_gallery_logs_table() {
    global $wpdb;

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $table = teinvit_product_gallery_logs_table();
    $charset = $wpdb->get_charset_collate();

    dbDelta( "CREATE TABLE {$table} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        product_id bigint(20) unsigned NOT NULL DEFAULT 0,
        product_slug varchar(191) NOT NULL DEFAULT '',
        gallery_file_base_slug varchar(191) NOT NULL DEFAULT '',
        vertical varchar(32) NOT NULL DEFAULT '',
        theme_key_internal varchar(80) NOT NULL DEFAULT '',
        theme_label varchar(191) NOT NULL DEFAULT '',
        theme_file_key varchar(80) NOT NULL DEFAULT '',
        status varchar(40) NOT NULL DEFAULT 'pending',
        error_code varchar(80) NOT NULL DEFAULT '',
        error_message text NULL,
        png_path text NULL,
        webp_path text NULL,
        metadata_json longtext NULL,
        attempt_count int(10) unsigned NOT NULL DEFAULT 0,
        last_run_at datetime NULL,
        created_at datetime NOT NULL,
        updated_at datetime NOT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY product_theme (product_id, vertical, theme_key_internal),
        KEY product_id (product_id),
        KEY vertical (vertical),
        KEY status (status),
        KEY theme_key_internal (theme_key_internal)
    ) $charset;" );
}

function teinvit_product_gallery_admin_capability() {
    return function_exists( 'teinvit_admin_capability' ) ? teinvit_admin_capability() : 'manage_woocommerce';
}

function teinvit_product_gallery_node_base_url() {
    $endpoint = defined( 'TEINVIT_GALLERY_NODE_ENDPOINT' )
        ? (string) TEINVIT_GALLERY_NODE_ENDPOINT
        : 'https://pdf.teinvit.com/api/gallery/render';

    return rtrim( preg_replace( '#/api/gallery/render/?$#', '', $endpoint ), '/' );
}

function teinvit_product_gallery_node_endpoint() {
    return defined( 'TEINVIT_GALLERY_NODE_ENDPOINT' )
        ? (string) TEINVIT_GALLERY_NODE_ENDPOINT
        : 'https://pdf.teinvit.com/api/gallery/render';
}

function teinvit_product_gallery_node_secret() {
    if ( defined( 'TEINVIT_GALLERY_NODE_SECRET' ) ) {
        return trim( (string) TEINVIT_GALLERY_NODE_SECRET );
    }
    if ( defined( 'TEINVIT_NODE_SHARED_SECRET' ) ) {
        return trim( (string) TEINVIT_NODE_SHARED_SECRET );
    }
    $env = getenv( 'TEINVIT_GALLERY_NODE_SECRET' );
    if ( is_string( $env ) && trim( $env ) !== '' ) {
        return trim( $env );
    }
    $env = getenv( 'TEINVIT_NODE_SHARED_SECRET' );
    return is_string( $env ) ? trim( $env ) : '';
}

function teinvit_product_gallery_signing_secret() {
    if ( defined( 'TEINVIT_GALLERY_RENDER_SECRET' ) && trim( (string) TEINVIT_GALLERY_RENDER_SECRET ) !== '' ) {
        return trim( (string) TEINVIT_GALLERY_RENDER_SECRET );
    }

    return function_exists( 'wp_salt' ) ? wp_salt( 'auth' ) : ( defined( 'AUTH_SALT' ) ? (string) AUTH_SALT : 'teinvit-gallery' );
}

function teinvit_product_gallery_render_signature( $product_id, $vertical, $theme_key, $exp ) {
    $payload = implode( '|', [
        (string) absint( $product_id ),
        sanitize_key( (string) $vertical ),
        sanitize_key( (string) $theme_key ),
        (string) absint( $exp ),
    ] );

    return hash_hmac( 'sha256', $payload, teinvit_product_gallery_signing_secret() );
}

function teinvit_product_gallery_verify_render_signature( $product_id, $vertical, $theme_key, $exp, $sig ) {
    $exp = absint( $exp );
    if ( $exp <= time() ) {
        return false;
    }

    $expected = teinvit_product_gallery_render_signature( $product_id, $vertical, $theme_key, $exp );
    return is_string( $sig ) && hash_equals( $expected, trim( $sig ) );
}

function teinvit_product_gallery_signed_render_url( $product_id, $vertical, $theme_key ) {
    $exp = time() + 15 * MINUTE_IN_SECONDS;
    $sig = teinvit_product_gallery_render_signature( $product_id, $vertical, $theme_key, $exp );

    return add_query_arg(
        [
            'product_id' => absint( $product_id ),
            'vertical'   => sanitize_key( (string) $vertical ),
            'theme_key'  => sanitize_key( (string) $theme_key ),
            'exp'        => $exp,
            'sig'        => $sig,
        ],
        home_url( '/teinvit-gallery-render/' )
    );
}

function teinvit_product_gallery_clean_file_base_slug( $slug ) {
    $original = sanitize_title( (string) $slug );
    if ( $original === '' ) {
        return 'produs';
    }

    $parts = array_values( array_filter( explode( '-', $original ), static function( $part ) {
        return $part !== '';
    } ) );

    $count = count( $parts );
    if ( $count > 0 && in_array( $parts[ $count - 1 ], [ 'basic', 'premium' ], true ) ) {
        array_pop( $parts );
        $count = count( $parts );
        if ( $count > 0 && in_array( $parts[ $count - 1 ], [ 'pachet', 'varianta' ], true ) ) {
            array_pop( $parts );
        }
    }

    $clean = sanitize_title( implode( '-', $parts ) );
    return $clean !== '' ? $clean : $original;
}

function teinvit_product_gallery_detect_product_context( $product_id ) {
    $product_id = absint( $product_id );
    if ( $product_id <= 0 || ! function_exists( 'wc_get_product' ) ) {
        return new WP_Error( 'invalid_product', 'Produs invalid.' );
    }

    $product = wc_get_product( $product_id );
    if ( ! $product ) {
        return new WP_Error( 'invalid_product', 'Produsul nu exista.' );
    }

    $parent_id = method_exists( $product, 'get_parent_id' ) ? absint( $product->get_parent_id() ) : 0;
    $context = function_exists( 'teinvit_get_configurable_product_context' )
        ? teinvit_get_configurable_product_context( $parent_id > 0 ? $parent_id : $product_id, $parent_id > 0 ? $product_id : 0 )
        : null;

    if ( ! is_array( $context ) || empty( $context['vertical'] ) ) {
        return new WP_Error( 'unsupported_product', 'Produsul nu este un produs configurabil TeInvit.' );
    }

    $vertical = function_exists( 'teinvit_normalize_vertical_key' )
        ? teinvit_normalize_vertical_key( $context['vertical'] )
        : sanitize_key( (string) $context['vertical'] );
    if ( ! in_array( $vertical, [ 'wedding', 'birthday', 'baptism' ], true ) ) {
        return new WP_Error( 'unsupported_vertical', 'Verticala produsului nu este suportata pentru galerie.' );
    }

    $slug = method_exists( $product, 'get_slug' ) ? sanitize_title( (string) $product->get_slug() ) : sanitize_title( get_post_field( 'post_name', $product_id ) );
    if ( $slug === '' ) {
        $slug = sanitize_title( method_exists( $product, 'get_name' ) ? (string) $product->get_name() : 'produs' );
    }

    return [
        'product'                => $product,
        'product_id'             => $product_id,
        'product_name'           => method_exists( $product, 'get_name' ) ? (string) $product->get_name() : '',
        'product_slug'           => $slug,
        'gallery_file_base_slug' => teinvit_product_gallery_clean_file_base_slug( $slug ),
        'vertical'               => $vertical,
        'package_type'           => sanitize_key( (string) ( $context['package_type'] ?? '' ) ),
    ];
}

function teinvit_product_gallery_background_details_for_product( $product_id ) {
    $product_id = absint( $product_id );
    $details = [
        'requested_product_id'      => $product_id,
        'background_source_product_id' => $product_id,
        'background_attachment_id'  => 0,
        'expected_background_url'   => '',
    ];

    if ( $product_id <= 0 ) {
        return $details;
    }

    $attachment_id = absint( get_post_meta( $product_id, '_teinvit_background_image_id', true ) );
    if ( $attachment_id <= 0 ) {
        return $details;
    }

    $url = wp_get_attachment_image_url( $attachment_id, 'full' );
    if ( ! $url ) {
        return $details;
    }

    $details['background_attachment_id'] = $attachment_id;
    $details['expected_background_url'] = esc_url_raw( $url );
    return $details;
}

function teinvit_product_gallery_output_dir( $vertical, $product_id ) {
    $upload = wp_upload_dir();
    if ( ! empty( $upload['error'] ) ) {
        return new WP_Error( 'upload_dir_failed', (string) $upload['error'] );
    }

    $vertical = sanitize_key( (string) $vertical );
    $product_id = absint( $product_id );
    $dir = trailingslashit( $upload['basedir'] ) . 'teinvit/product-gallery/' . $vertical . '/' . $product_id;

    if ( ! wp_mkdir_p( $dir ) ) {
        return new WP_Error( 'mkdir_failed', 'Folderul final nu a putut fi creat.' );
    }

    return $dir;
}

function teinvit_product_gallery_final_paths( array $product_context, array $theme ) {
    $dir = teinvit_product_gallery_output_dir( $product_context['vertical'], $product_context['product_id'] );
    if ( is_wp_error( $dir ) ) {
        return $dir;
    }

    $base = sanitize_file_name( $product_context['gallery_file_base_slug'] . '-' . $theme['file_key'] );
    return [
        'dir'  => $dir,
        'base' => $base,
        'png'  => trailingslashit( $dir ) . $base . '.png',
        'webp' => trailingslashit( $dir ) . $base . '.webp',
    ];
}

function teinvit_product_gallery_validate_image_file( $path, $expected_mime ) {
    $path = (string) $path;
    if ( $path === '' || ! file_exists( $path ) || ! is_file( $path ) ) {
        return new WP_Error( 'missing_file', 'Fisier lipsa.', [ 'expected_mime' => $expected_mime ] );
    }

    $bytes = filesize( $path );
    if ( ! $bytes || $bytes <= 0 ) {
        return new WP_Error( 'empty_file', 'Fisier gol.', [ 'expected_mime' => $expected_mime ] );
    }

    $size = @getimagesize( $path );
    if ( ! is_array( $size ) || (int) ( $size[0] ?? 0 ) !== 1118 || (int) ( $size[1] ?? 0 ) !== 1588 ) {
        return new WP_Error( 'invalid_dimensions', 'Dimensiuni imagine invalide.', [ 'expected_mime' => $expected_mime ] );
    }

    $mime = function_exists( 'wp_get_image_mime' ) ? wp_get_image_mime( $path ) : ( $size['mime'] ?? '' );
    if ( $mime !== $expected_mime ) {
        return new WP_Error( 'invalid_mime', 'MIME imagine invalid.', [ 'expected_mime' => $expected_mime, 'actual_mime' => $mime ] );
    }

    return [
        'exists' => true,
        'width'  => 1118,
        'height' => 1588,
        'bytes'  => (int) $bytes,
        'mime'   => $mime,
    ];
}

function teinvit_product_gallery_existing_pair_status( array $product_context, array $theme ) {
    $paths = teinvit_product_gallery_final_paths( $product_context, $theme );
    if ( is_wp_error( $paths ) ) {
        return [ 'status' => 'invalid', 'reason' => $paths->get_error_message(), 'paths' => [] ];
    }

    $png = teinvit_product_gallery_validate_image_file( $paths['png'], 'image/png' );
    $webp = teinvit_product_gallery_validate_image_file( $paths['webp'], 'image/webp' );

    if ( ! is_wp_error( $png ) && ! is_wp_error( $webp ) ) {
        return [ 'status' => 'complete', 'reason' => '', 'paths' => $paths ];
    }

    if ( file_exists( $paths['png'] ) || file_exists( $paths['webp'] ) ) {
        return [ 'status' => 'incomplete', 'reason' => is_wp_error( $png ) ? $png->get_error_message() : $webp->get_error_message(), 'paths' => $paths ];
    }

    return [ 'status' => 'missing', 'reason' => '', 'paths' => $paths ];
}

function teinvit_product_gallery_encode_metadata( $metadata ) {
    return wp_json_encode( is_array( $metadata ) ? $metadata : [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
}

function teinvit_product_gallery_upsert_log( array $data ) {
    global $wpdb;

    $table = teinvit_product_gallery_logs_table();
    $now = current_time( 'mysql' );
    $product_id = absint( $data['product_id'] ?? 0 );
    $vertical = sanitize_key( (string) ( $data['vertical'] ?? '' ) );
    $theme_key = sanitize_key( (string) ( $data['theme_key_internal'] ?? '' ) );
    if ( $product_id <= 0 || $vertical === '' || $theme_key === '' ) {
        return false;
    }

    $existing_id = (int) $wpdb->get_var(
        $wpdb->prepare(
            'SELECT id FROM ' . $table . ' WHERE product_id = %d AND vertical = %s AND theme_key_internal = %s LIMIT 1',
            $product_id,
            $vertical,
            $theme_key
        )
    );

    $payload = [
        'product_id'             => $product_id,
        'product_slug'           => sanitize_title( (string) ( $data['product_slug'] ?? '' ) ),
        'gallery_file_base_slug' => sanitize_title( (string) ( $data['gallery_file_base_slug'] ?? '' ) ),
        'vertical'               => $vertical,
        'theme_key_internal'     => $theme_key,
        'theme_label'            => sanitize_text_field( (string) ( $data['theme_label'] ?? '' ) ),
        'theme_file_key'         => sanitize_key( (string) ( $data['theme_file_key'] ?? '' ) ),
        'status'                 => sanitize_key( (string) ( $data['status'] ?? 'pending' ) ),
        'error_code'             => sanitize_key( (string) ( $data['error_code'] ?? '' ) ),
        'error_message'          => sanitize_textarea_field( (string) ( $data['error_message'] ?? '' ) ),
        'png_path'               => (string) ( $data['png_path'] ?? '' ),
        'webp_path'              => (string) ( $data['webp_path'] ?? '' ),
        'metadata_json'          => isset( $data['metadata'] ) ? teinvit_product_gallery_encode_metadata( $data['metadata'] ) : (string) ( $data['metadata_json'] ?? '' ),
        'last_run_at'            => (string) ( $data['last_run_at'] ?? $now ),
        'updated_at'             => $now,
    ];

    if ( $existing_id > 0 ) {
        if ( ! empty( $data['increment_attempt'] ) ) {
            $wpdb->query( $wpdb->prepare( 'UPDATE ' . $table . ' SET attempt_count = attempt_count + 1 WHERE id = %d', $existing_id ) );
        }
        return false !== $wpdb->update( $table, $payload, [ 'id' => $existing_id ] );
    }

    $payload['attempt_count'] = ! empty( $data['increment_attempt'] ) ? 1 : absint( $data['attempt_count'] ?? 0 );
    $payload['created_at'] = $now;
    return (bool) $wpdb->insert( $table, $payload );
}

function teinvit_product_gallery_logs_for_product( $product_id ) {
    global $wpdb;
    $product_id = absint( $product_id );
    if ( $product_id <= 0 ) {
        return [];
    }

    return $wpdb->get_results(
        $wpdb->prepare( 'SELECT * FROM ' . teinvit_product_gallery_logs_table() . ' WHERE product_id = %d ORDER BY vertical ASC, theme_label ASC', $product_id ),
        ARRAY_A
    );
}

function teinvit_product_gallery_download_node_file( $download_path, $target_tmp ) {
    $download_path = (string) $download_path;
    if ( $download_path === '' ) {
        return new WP_Error( 'missing_download_path', 'Download path lipsa.' );
    }

    $url = preg_match( '#^https?://#i', $download_path )
        ? $download_path
        : teinvit_product_gallery_node_base_url() . '/' . ltrim( $download_path, '/' );

    $response = wp_remote_get(
        esc_url_raw( $url ),
        [
            'timeout'     => 60,
            'redirection' => 0,
            'stream'      => true,
            'filename'    => $target_tmp,
        ]
    );

    if ( is_wp_error( $response ) ) {
        return $response;
    }

    $code = (int) wp_remote_retrieve_response_code( $response );
    if ( $code < 200 || $code >= 300 ) {
        return new WP_Error( 'download_failed', 'Download Node esuat: HTTP ' . $code );
    }

    return true;
}

function teinvit_product_gallery_publish_node_files( array $product_context, array $theme, array $node_response ) {
    $paths = teinvit_product_gallery_final_paths( $product_context, $theme );
    if ( is_wp_error( $paths ) ) {
        return $paths;
    }

    $tmp_png = trailingslashit( $paths['dir'] ) . '.tmp-' . wp_generate_uuid4() . '.png';
    $tmp_webp = trailingslashit( $paths['dir'] ) . '.tmp-' . wp_generate_uuid4() . '.webp';
    $files = isset( $node_response['files'] ) && is_array( $node_response['files'] ) ? $node_response['files'] : [];

    $png_path = (string) ( $files['png']['download_path'] ?? '' );
    $webp_path = (string) ( $files['webp']['download_path'] ?? '' );

    $download_png = teinvit_product_gallery_download_node_file( $png_path, $tmp_png );
    if ( is_wp_error( $download_png ) ) {
        @unlink( $tmp_png );
        @unlink( $tmp_webp );
        return $download_png;
    }

    $download_webp = teinvit_product_gallery_download_node_file( $webp_path, $tmp_webp );
    if ( is_wp_error( $download_webp ) ) {
        @unlink( $tmp_png );
        @unlink( $tmp_webp );
        return $download_webp;
    }

    $png_meta = teinvit_product_gallery_validate_image_file( $tmp_png, 'image/png' );
    $webp_meta = teinvit_product_gallery_validate_image_file( $tmp_webp, 'image/webp' );
    if ( is_wp_error( $png_meta ) || is_wp_error( $webp_meta ) ) {
        @unlink( $tmp_png );
        @unlink( $tmp_webp );
        return is_wp_error( $png_meta ) ? $png_meta : $webp_meta;
    }

    $rollback_id = wp_generate_uuid4();
    $old_png = null;
    $old_webp = null;

    if ( file_exists( $paths['png'] ) ) {
        $old_png = $paths['png'] . '.old-' . $rollback_id;
        if ( ! @rename( $paths['png'], $old_png ) ) {
            @unlink( $tmp_png );
            @unlink( $tmp_webp );
            return new WP_Error( 'atomic_write_failed', 'PNG existent nu a putut fi pregatit pentru suprascriere atomica.' );
        }
    }

    if ( file_exists( $paths['webp'] ) ) {
        $old_webp = $paths['webp'] . '.old-' . $rollback_id;
        if ( ! @rename( $paths['webp'], $old_webp ) ) {
            if ( $old_png ) {
                @rename( $old_png, $paths['png'] );
            }
            @unlink( $tmp_png );
            @unlink( $tmp_webp );
            return new WP_Error( 'atomic_write_failed', 'WebP existent nu a putut fi pregatit pentru suprascriere atomica.' );
        }
    }

    if ( ! @rename( $tmp_png, $paths['png'] ) ) {
        if ( $old_png ) {
            @rename( $old_png, $paths['png'] );
        }
        if ( $old_webp ) {
            @rename( $old_webp, $paths['webp'] );
        }
        @unlink( $tmp_png );
        @unlink( $tmp_webp );
        return new WP_Error( 'atomic_write_failed', 'PNG nu a putut fi publicat atomic.' );
    }

    if ( ! @rename( $tmp_webp, $paths['webp'] ) ) {
        @unlink( $paths['png'] );
        if ( $old_png ) {
            @rename( $old_png, $paths['png'] );
        }
        if ( $old_webp ) {
            @rename( $old_webp, $paths['webp'] );
        }
        @unlink( $tmp_webp );
        return new WP_Error( 'atomic_write_failed', 'WebP nu a putut fi publicat atomic.' );
    }

    if ( $old_png ) {
        @unlink( $old_png );
    }
    if ( $old_webp ) {
        @unlink( $old_webp );
    }

    return [
        'paths' => $paths,
        'png'   => $png_meta,
        'webp'  => $webp_meta,
    ];
}

function teinvit_product_gallery_cleanup_node_job( $job_id ) {
    $job_id = sanitize_text_field( (string) $job_id );
    $secret = teinvit_product_gallery_node_secret();
    if ( $job_id === '' || $secret === '' ) {
        return false;
    }

    wp_remote_post(
        teinvit_product_gallery_node_base_url() . '/api/gallery/cleanup',
        [
            'timeout' => 15,
            'headers' => [
                'Content-Type'      => 'application/json',
                'X-TeInvit-Secret' => $secret,
            ],
            'body'    => wp_json_encode( [ 'job_id' => $job_id ] ),
        ]
    );

    return true;
}

function teinvit_product_gallery_generate_theme( $product_id, $theme_key, $mode = 'missing' ) {
    $product_context = teinvit_product_gallery_detect_product_context( $product_id );
    if ( is_wp_error( $product_context ) ) {
        return $product_context;
    }

    $theme = teinvit_product_gallery_resolve_theme( $product_context['vertical'], $theme_key );
    if ( ! is_array( $theme ) ) {
        return new WP_Error( 'invalid_theme', 'Tema nu este activa pentru verticala produsului.' );
    }

    $background = teinvit_product_gallery_background_details_for_product( $product_context['product_id'] );
    if ( empty( $background['background_attachment_id'] ) || empty( $background['expected_background_url'] ) ) {
        teinvit_product_gallery_upsert_log( [
            'product_id'             => $product_context['product_id'],
            'product_slug'           => $product_context['product_slug'],
            'gallery_file_base_slug' => $product_context['gallery_file_base_slug'],
            'vertical'               => $product_context['vertical'],
            'theme_key_internal'     => $theme['theme_key_internal'],
            'theme_label'            => $theme['label'],
            'theme_file_key'         => $theme['file_key'],
            'status'                 => 'failed',
            'error_code'             => 'missing_background',
            'error_message'          => 'Produsul nu are background TeInvit valid.',
            'metadata'               => [ 'background' => $background ],
            'increment_attempt'      => true,
        ] );
        return new WP_Error( 'missing_background', 'Produsul nu are background TeInvit valid.' );
    }

    $existing = teinvit_product_gallery_existing_pair_status( $product_context, $theme );
    if ( $mode !== 'regenerate' && ( $existing['status'] ?? '' ) === 'complete' ) {
        teinvit_product_gallery_upsert_log( [
            'product_id'             => $product_context['product_id'],
            'product_slug'           => $product_context['product_slug'],
            'gallery_file_base_slug' => $product_context['gallery_file_base_slug'],
            'vertical'               => $product_context['vertical'],
            'theme_key_internal'     => $theme['theme_key_internal'],
            'theme_label'            => $theme['label'],
            'theme_file_key'         => $theme['file_key'],
            'status'                 => 'skipped_existing',
            'png_path'               => (string) ( $existing['paths']['png'] ?? '' ),
            'webp_path'              => (string) ( $existing['paths']['webp'] ?? '' ),
            'metadata'               => [ 'existing_pair' => $existing ],
        ] );
        return [
            'status'  => 'skipped_existing',
            'theme'   => $theme,
            'product' => $product_context,
            'paths'   => $existing['paths'],
        ];
    }

    teinvit_product_gallery_upsert_log( [
        'product_id'             => $product_context['product_id'],
        'product_slug'           => $product_context['product_slug'],
        'gallery_file_base_slug' => $product_context['gallery_file_base_slug'],
        'vertical'               => $product_context['vertical'],
        'theme_key_internal'     => $theme['theme_key_internal'],
        'theme_label'            => $theme['label'],
        'theme_file_key'         => $theme['file_key'],
        'status'                 => 'processing',
        'error_code'             => '',
        'error_message'          => '',
        'increment_attempt'      => true,
    ] );

    $render_url = teinvit_product_gallery_signed_render_url( $product_context['product_id'], $product_context['vertical'], $theme['theme_key_internal'] );
    $secret = teinvit_product_gallery_node_secret();
    if ( $secret === '' ) {
        teinvit_product_gallery_upsert_log( [
            'product_id'             => $product_context['product_id'],
            'product_slug'           => $product_context['product_slug'],
            'gallery_file_base_slug' => $product_context['gallery_file_base_slug'],
            'vertical'               => $product_context['vertical'],
            'theme_key_internal'     => $theme['theme_key_internal'],
            'theme_label'            => $theme['label'],
            'theme_file_key'         => $theme['file_key'],
            'status'                 => 'failed_transfer',
            'error_code'             => 'missing_node_secret',
            'error_message'          => 'Secretul pentru Node gallery lipseste.',
        ] );
        return new WP_Error( 'missing_node_secret', 'Secretul pentru Node gallery lipseste.' );
    }

    $request_payload = [
        'render_url'                 => esc_url_raw( $render_url ),
        'product_id'                 => $product_context['product_id'],
        'requested_product_id'       => $product_context['product_id'],
        'product_slug'               => $product_context['product_slug'],
        'gallery_file_base_slug'     => $product_context['gallery_file_base_slug'],
        'vertical'                   => $product_context['vertical'],
        'theme_key_internal'         => $theme['theme_key_internal'],
        'theme_label'                => $theme['label'],
        'theme_file_key'             => $theme['file_key'],
        'theme_css_class'            => $theme['css_class'],
        'theme_css_class_expected'   => $theme['css_class'],
        'background_url'             => $background['expected_background_url'],
        'background_attachment_id'   => $background['background_attachment_id'],
        'background_source_product_id' => $background['background_source_product_id'],
        'output'                     => [
            'layout_width'         => 559,
            'layout_height'        => 794,
            'device_scale_factor'  => 2,
            'output_width'         => 1118,
            'output_height'        => 1588,
            'webp_quality'         => 88,
        ],
    ];

    $response = wp_remote_post(
        teinvit_product_gallery_node_endpoint(),
        [
            'timeout' => 120,
            'headers' => [
                'Content-Type'      => 'application/json',
                'X-TeInvit-Secret' => $secret,
            ],
            'body'    => wp_json_encode( $request_payload ),
        ]
    );

    if ( is_wp_error( $response ) ) {
        teinvit_product_gallery_upsert_log( [
            'product_id' => $product_context['product_id'],
            'vertical' => $product_context['vertical'],
            'theme_key_internal' => $theme['theme_key_internal'],
            'theme_label' => $theme['label'],
            'theme_file_key' => $theme['file_key'],
            'product_slug' => $product_context['product_slug'],
            'gallery_file_base_slug' => $product_context['gallery_file_base_slug'],
            'status' => 'failed_transfer',
            'error_code' => $response->get_error_code(),
            'error_message' => $response->get_error_message(),
        ] );
        return $response;
    }

    $body = json_decode( (string) wp_remote_retrieve_body( $response ), true );
    $code = (int) wp_remote_retrieve_response_code( $response );
    if ( $code < 200 || $code >= 300 || ! is_array( $body ) || (string) ( $body['status'] ?? '' ) !== 'ok' ) {
        $error_code = sanitize_key( (string) ( $body['code'] ?? 'node_render_failed' ) );
        if ( $error_code === 'gallery_ready_timeout' || $error_code === 'gallery_readiness_failed' ) {
            $status = 'failed_readiness';
        } elseif ( $error_code === 'failed_webp' ) {
            $status = 'failed_webp';
        } else {
            $status = 'failed';
        }
        teinvit_product_gallery_upsert_log( [
            'product_id' => $product_context['product_id'],
            'vertical' => $product_context['vertical'],
            'theme_key_internal' => $theme['theme_key_internal'],
            'theme_label' => $theme['label'],
            'theme_file_key' => $theme['file_key'],
            'product_slug' => $product_context['product_slug'],
            'gallery_file_base_slug' => $product_context['gallery_file_base_slug'],
            'status' => $status,
            'error_code' => $error_code,
            'error_message' => sanitize_textarea_field( (string) ( $body['message'] ?? 'Node render failed.' ) ),
            'metadata' => is_array( $body ) ? $body : [],
        ] );
        if ( ! empty( $body['job_id'] ) ) {
            teinvit_product_gallery_cleanup_node_job( (string) $body['job_id'] );
        }
        return new WP_Error( $error_code, (string) ( $body['message'] ?? 'Node render failed.' ) );
    }

    $publish = teinvit_product_gallery_publish_node_files( $product_context, $theme, $body );
    if ( ! empty( $body['job_id'] ) ) {
        teinvit_product_gallery_cleanup_node_job( (string) $body['job_id'] );
    }

    if ( is_wp_error( $publish ) ) {
        $publish_error_data = $publish->get_error_data();
        $status = is_array( $publish_error_data ) && ( $publish_error_data['expected_mime'] ?? '' ) === 'image/webp'
            ? 'failed_webp'
            : 'failed_transfer';
        teinvit_product_gallery_upsert_log( [
            'product_id' => $product_context['product_id'],
            'vertical' => $product_context['vertical'],
            'theme_key_internal' => $theme['theme_key_internal'],
            'theme_label' => $theme['label'],
            'theme_file_key' => $theme['file_key'],
            'product_slug' => $product_context['product_slug'],
            'gallery_file_base_slug' => $product_context['gallery_file_base_slug'],
            'status' => $status,
            'error_code' => $publish->get_error_code(),
            'error_message' => $publish->get_error_message(),
            'metadata' => $body,
        ] );
        return $publish;
    }

    $metadata = isset( $body['metadata'] ) && is_array( $body['metadata'] ) ? $body['metadata'] : [];
    $metadata['wordpress_publish'] = [
        'png'  => $publish['png'],
        'webp' => $publish['webp'],
    ];

    teinvit_product_gallery_upsert_log( [
        'product_id' => $product_context['product_id'],
        'vertical' => $product_context['vertical'],
        'theme_key_internal' => $theme['theme_key_internal'],
        'theme_label' => $theme['label'],
        'theme_file_key' => $theme['file_key'],
        'product_slug' => $product_context['product_slug'],
        'gallery_file_base_slug' => $product_context['gallery_file_base_slug'],
        'status' => 'success',
        'error_code' => '',
        'error_message' => '',
        'png_path' => $publish['paths']['png'],
        'webp_path' => $publish['paths']['webp'],
        'metadata' => $metadata,
    ] );

    return [
        'status' => 'success',
        'theme' => $theme,
        'product' => $product_context,
        'paths' => $publish['paths'],
        'metadata' => $metadata,
    ];
}
