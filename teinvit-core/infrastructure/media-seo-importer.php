<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function teinvit_media_seo_imports_table() {
    global $wpdb;
    return $wpdb->prefix . 'teinvit_media_seo_imports';
}

function teinvit_media_seo_max_rows() {
    return (int) apply_filters( 'teinvit_media_seo_max_rows', 1000 );
}

function teinvit_media_seo_max_file_size() {
    return (int) apply_filters( 'teinvit_media_seo_max_file_size', 5 * 1024 * 1024 );
}

function teinvit_install_media_seo_imports_table() {
    global $wpdb;

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $table = teinvit_media_seo_imports_table();
    $charset = $wpdb->get_charset_collate();

    dbDelta( "CREATE TABLE {$table} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        job_id varchar(64) NOT NULL,
        original_filename text NOT NULL,
        stored_filename varchar(255) NOT NULL,
        stored_path text NOT NULL,
        file_hash char(64) NOT NULL DEFAULT '',
        uploaded_by bigint(20) unsigned NOT NULL DEFAULT 0,
        uploaded_at datetime NOT NULL,
        vertical varchar(191) NOT NULL DEFAULT '',
        status varchar(20) NOT NULL DEFAULT 'dry_run',
        total_rows int NOT NULL DEFAULT 0,
        valid_rows int NOT NULL DEFAULT 0,
        updated_count int NOT NULL DEFAULT 0,
        unchanged_count int NOT NULL DEFAULT 0,
        skipped_count int NOT NULL DEFAULT 0,
        error_count int NOT NULL DEFAULT 0,
        report_json longtext NULL,
        applied_at datetime NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY job_id (job_id),
        KEY uploaded_at (uploaded_at),
        KEY status (status),
        KEY uploaded_by (uploaded_by)
    ) $charset;" );
}

function teinvit_media_seo_expected_columns() {
    return [
        'vertical',
        'product_family',
        'attachment_id',
        'image_url',
        'product_skus',
        'product_names',
        'image_alt_text',
        'image_title',
        'image_caption',
        'image_description',
    ];
}

function teinvit_media_seo_update_columns() {
    return [
        'image_alt_text' => [
            'target' => '_wp_attachment_image_alt',
            'label'  => 'ALT',
        ],
        'image_title' => [
            'target' => 'post_title',
            'label'  => 'Title',
        ],
        'image_caption' => [
            'target' => 'post_excerpt',
            'label'  => 'Caption',
        ],
        'image_description' => [
            'target' => 'post_content',
            'label'  => 'Description',
        ],
    ];
}

function teinvit_media_seo_required_columns() {
    return array_merge( [ 'attachment_id' ], array_keys( teinvit_media_seo_update_columns() ) );
}

function teinvit_media_seo_upload_dir() {
    $upload = wp_upload_dir();
    if ( ! empty( $upload['error'] ) || empty( $upload['basedir'] ) ) {
        return new WP_Error( 'upload_dir_error', 'Nu se poate rezolva directorul de upload WordPress.' );
    }

    $base_dir = trailingslashit( $upload['basedir'] ) . 'teinvit-media-seo-imports';

    return [
        'basedir' => $base_dir,
    ];
}

function teinvit_media_seo_ensure_upload_dir() {
    $dir = teinvit_media_seo_upload_dir();
    if ( is_wp_error( $dir ) ) {
        return $dir;
    }

    if ( ! wp_mkdir_p( $dir['basedir'] ) ) {
        return new WP_Error( 'upload_dir_create_failed', 'Nu se poate crea directorul pentru importurile Media SEO.' );
    }

    $index = trailingslashit( $dir['basedir'] ) . 'index.html';
    if ( ! file_exists( $index ) ) {
        file_put_contents( $index, '' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
    }

    $htaccess = trailingslashit( $dir['basedir'] ) . '.htaccess';
    if ( ! file_exists( $htaccess ) ) {
        file_put_contents( $htaccess, "<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
    }

    return $dir;
}

function teinvit_media_seo_generate_job_id() {
    if ( function_exists( 'wp_generate_uuid4' ) ) {
        return str_replace( '-', '', wp_generate_uuid4() );
    }

    return hash( 'sha256', uniqid( 'teinvit_media_seo_', true ) . wp_rand() );
}

function teinvit_media_seo_validate_upload( array $file ) {
    if ( empty( $file['tmp_name'] ) || ! is_string( $file['tmp_name'] ) ) {
        return new WP_Error( 'missing_file', 'Nu a fost incarcat niciun fisier CSV.' );
    }

    if ( ! empty( $file['error'] ) ) {
        return new WP_Error( 'upload_error', 'Upload-ul CSV a esuat.' );
    }

    $original_name = isset( $file['name'] ) ? sanitize_file_name( (string) $file['name'] ) : '';
    if ( $original_name === '' || strtolower( pathinfo( $original_name, PATHINFO_EXTENSION ) ) !== 'csv' ) {
        return new WP_Error( 'invalid_extension', 'Fisierul trebuie sa aiba extensia .csv.' );
    }

    $size = isset( $file['size'] ) ? (int) $file['size'] : 0;
    if ( $size <= 0 ) {
        return new WP_Error( 'empty_file', 'Fisierul CSV este gol.' );
    }

    if ( $size > teinvit_media_seo_max_file_size() ) {
        return new WP_Error( 'file_too_large', 'Fisierul CSV depaseste limita permisa.' );
    }

    $allowed_mimes = [
        'text/csv',
        'text/plain',
        'text/x-csv',
        'application/csv',
        'application/x-csv',
        'application/vnd.ms-excel',
        'text/comma-separated-values',
        'application/octet-stream',
    ];
    $mime = isset( $file['type'] ) ? sanitize_text_field( (string) $file['type'] ) : '';
    if ( $mime !== '' && ! in_array( $mime, $allowed_mimes, true ) ) {
        $checked = wp_check_filetype_and_ext( $file['tmp_name'], $original_name, [ 'csv' => 'text/csv' ] );
        if ( empty( $checked['ext'] ) || $checked['ext'] !== 'csv' ) {
            return new WP_Error( 'invalid_mime', 'Tipul fisierului nu pare sa fie CSV.' );
        }
    }

    return true;
}

function teinvit_media_seo_store_upload( array $file ) {
    $valid = teinvit_media_seo_validate_upload( $file );
    if ( is_wp_error( $valid ) ) {
        return $valid;
    }

    $dir = teinvit_media_seo_ensure_upload_dir();
    if ( is_wp_error( $dir ) ) {
        return $dir;
    }

    $job_id = teinvit_media_seo_generate_job_id();
    $original_name = sanitize_file_name( (string) $file['name'] );
    $stored_filename = 'media-seo-' . gmdate( 'Y-m-d-His' ) . '-' . $job_id . '.csv';
    $stored_path = trailingslashit( $dir['basedir'] ) . $stored_filename;

    $moved = false;
    if ( is_uploaded_file( $file['tmp_name'] ) ) {
        $moved = move_uploaded_file( $file['tmp_name'], $stored_path );
    }

    if ( ! $moved && file_exists( $file['tmp_name'] ) ) {
        $moved = copy( $file['tmp_name'], $stored_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_copy
    }

    if ( ! $moved || ! file_exists( $stored_path ) ) {
        return new WP_Error( 'store_failed', 'Fisierul CSV nu a putut fi salvat in zona controlata.' );
    }

    return [
        'job_id'            => $job_id,
        'original_filename' => $original_name,
        'stored_filename'   => $stored_filename,
        'stored_path'       => $stored_path,
        'file_hash'         => hash_file( 'sha256', $stored_path ),
    ];
}

function teinvit_media_seo_strip_bom( $value ) {
    $value = (string) $value;
    return preg_replace( '/^\xEF\xBB\xBF/', '', $value );
}

function teinvit_media_seo_is_empty_csv_row( array $row ) {
    foreach ( $row as $value ) {
        if ( trim( (string) $value ) !== '' ) {
            return false;
        }
    }

    return true;
}

function teinvit_media_seo_parse_csv( $path ) {
    if ( ! is_readable( $path ) ) {
        return new WP_Error( 'csv_not_readable', 'Fisierul CSV nu poate fi citit.' );
    }

    $handle = fopen( $path, 'rb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
    if ( ! $handle ) {
        return new WP_Error( 'csv_open_failed', 'Fisierul CSV nu poate fi deschis.' );
    }

    $raw_header = fgetcsv( $handle );
    if ( ! is_array( $raw_header ) ) {
        fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
        return new WP_Error( 'csv_empty', 'CSV-ul nu contine header.' );
    }

    $header = [];
    foreach ( $raw_header as $index => $column ) {
        $column = trim( teinvit_media_seo_strip_bom( (string) $column ) );
        $header[ $index ] = sanitize_key( $column );
    }

    $expected = teinvit_media_seo_expected_columns();
    $missing = array_values( array_diff( $expected, $header ) );
    if ( ! empty( $missing ) ) {
        fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
        return new WP_Error( 'csv_missing_columns', 'CSV-ul nu contine coloanele obligatorii: ' . implode( ', ', $missing ) );
    }

    $rows = [];
    $line_number = 1;
    $max_rows = teinvit_media_seo_max_rows();

    while ( ( $raw_row = fgetcsv( $handle ) ) !== false ) {
        $line_number++;

        if ( teinvit_media_seo_is_empty_csv_row( $raw_row ) ) {
            continue;
        }

        if ( count( $rows ) >= $max_rows ) {
            fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
            return new WP_Error( 'csv_too_many_rows', 'CSV-ul depaseste limita de ' . $max_rows . ' randuri.' );
        }

        $data = [];
        foreach ( $header as $index => $column ) {
            if ( $column === '' ) {
                continue;
            }
            $data[ $column ] = isset( $raw_row[ $index ] ) ? (string) $raw_row[ $index ] : '';
        }

        $rows[] = [
            'line_number' => $line_number,
            'data'        => $data,
        ];
    }

    fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose

    return [
        'headers'    => $header,
        'rows'       => $rows,
        'total_rows' => count( $rows ),
    ];
}

function teinvit_media_seo_normalize_row( array $data ) {
    return [
        'vertical'          => sanitize_key( (string) ( $data['vertical'] ?? '' ) ),
        'product_family'    => sanitize_text_field( (string) ( $data['product_family'] ?? '' ) ),
        'attachment_id'     => absint( $data['attachment_id'] ?? 0 ),
        'image_url'         => esc_url_raw( (string) ( $data['image_url'] ?? '' ) ),
        'product_skus'      => sanitize_text_field( (string) ( $data['product_skus'] ?? '' ) ),
        'product_names'     => sanitize_text_field( (string) ( $data['product_names'] ?? '' ) ),
        'image_alt_text'    => sanitize_text_field( (string) ( $data['image_alt_text'] ?? '' ) ),
        'image_title'       => sanitize_text_field( (string) ( $data['image_title'] ?? '' ) ),
        'image_caption'     => sanitize_textarea_field( (string) ( $data['image_caption'] ?? '' ) ),
        'image_description' => sanitize_textarea_field( (string) ( $data['image_description'] ?? '' ) ),
    ];
}

function teinvit_media_seo_current_values( $attachment_id ) {
    $post = get_post( $attachment_id );

    return [
        'image_alt_text'    => (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ),
        'image_title'       => $post ? (string) $post->post_title : '',
        'image_caption'     => $post ? (string) $post->post_excerpt : '',
        'image_description' => $post ? (string) $post->post_content : '',
    ];
}

function teinvit_media_seo_proposed_metadata_from_row( array $data ) {
    $metadata = [];

    foreach ( array_keys( teinvit_media_seo_update_columns() ) as $column ) {
        $value = isset( $data[ $column ] ) ? (string) $data[ $column ] : '';
        if ( trim( $value ) === '' ) {
            continue;
        }

        $metadata[ $column ] = $value;
    }

    return $metadata;
}

function teinvit_media_seo_normalize_metadata_value_for_compare( $value ) {
    $value = trim( (string) $value );
    if ( $value === '' ) {
        return '';
    }

    $normalized = preg_replace( '/\s+/', ' ', $value );
    return is_string( $normalized ) ? $normalized : $value;
}

function teinvit_media_seo_normalize_metadata_for_compare( array $metadata ) {
    $normalized = [];

    foreach ( array_keys( teinvit_media_seo_update_columns() ) as $column ) {
        if ( ! array_key_exists( $column, $metadata ) ) {
            continue;
        }

        $value = teinvit_media_seo_normalize_metadata_value_for_compare( $metadata[ $column ] );
        if ( $value === '' ) {
            continue;
        }

        $normalized[ $column ] = $value;
    }

    return $normalized;
}

function teinvit_media_seo_metadata_signature( array $metadata ) {
    $normalized = teinvit_media_seo_normalize_metadata_for_compare( $metadata );
    $json = wp_json_encode( $normalized );

    return is_string( $json ) ? $json : '';
}

function teinvit_media_seo_build_changes_for_attachment( $attachment_id, array $metadata ) {
    $changes = [];
    $current = teinvit_media_seo_current_values( $attachment_id );

    foreach ( teinvit_media_seo_update_columns() as $column => $meta ) {
        if ( ! array_key_exists( $column, $metadata ) ) {
            continue;
        }

        $new_value = (string) $metadata[ $column ];
        if ( trim( $new_value ) === '' ) {
            continue;
        }

        $current_value = (string) ( $current[ $column ] ?? '' );
        if ( $current_value !== $new_value ) {
            $changes[ $column ] = [
                'label'   => $meta['label'],
                'target'  => $meta['target'],
                'current' => $current_value,
                'new'     => $new_value,
            ];
        }
    }

    return $changes;
}

function teinvit_media_seo_gallery_product_statuses() {
    $statuses = apply_filters( 'teinvit_media_seo_gallery_product_statuses', [ 'publish' ] );
    if ( ! is_array( $statuses ) ) {
        $statuses = [ 'publish' ];
    }

    $statuses = array_values( array_filter( array_map( 'sanitize_key', $statuses ) ) );
    return ! empty( $statuses ) ? $statuses : [ 'publish' ];
}

function teinvit_media_seo_gallery_product_ids_for_meta( $meta_key, $attachment_id ) {
    $attachment_id = absint( $attachment_id );
    if ( $attachment_id <= 0 ) {
        return [];
    }

    $ids = get_posts(
        [
            'post_type'              => 'product',
            'post_status'            => teinvit_media_seo_gallery_product_statuses(),
            'posts_per_page'         => -1,
            'fields'                 => 'ids',
            'orderby'                => 'ID',
            'order'                  => 'ASC',
            'no_found_rows'          => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
            'meta_query'             => [
                [
                    'key'     => (string) $meta_key,
                    'value'   => (string) $attachment_id,
                    'compare' => '=',
                ],
            ],
        ]
    );

    return array_values( array_unique( array_filter( array_map( 'absint', is_array( $ids ) ? $ids : [] ) ) ) );
}

function teinvit_media_seo_gallery_attachment_status( $attachment_id ) {
    $attachment_id = absint( $attachment_id );
    if ( $attachment_id <= 0 ) {
        return 'invalid_attachment';
    }

    $post = get_post( $attachment_id );
    if ( ! $post || (string) $post->post_type !== 'attachment' ) {
        return 'gallery_attachment_missing';
    }

    if ( ! wp_attachment_is_image( $attachment_id ) ) {
        return 'invalid_attachment';
    }

    return 'valid';
}

function teinvit_media_seo_gallery_resolve_attachment( $attachment_id ) {
    $attachment_id = absint( $attachment_id );
    $thumbnail_ids = teinvit_media_seo_gallery_product_ids_for_meta( '_thumbnail_id', $attachment_id );
    $background_ids = teinvit_media_seo_gallery_product_ids_for_meta( '_teinvit_background_image_id', $attachment_id );
    $sources_by_product = [];

    foreach ( $thumbnail_ids as $product_id ) {
        if ( ! isset( $sources_by_product[ $product_id ] ) ) {
            $sources_by_product[ $product_id ] = [];
        }
        $sources_by_product[ $product_id ]['thumbnail'] = true;
    }

    foreach ( $background_ids as $product_id ) {
        if ( ! isset( $sources_by_product[ $product_id ] ) ) {
            $sources_by_product[ $product_id ] = [];
        }
        $sources_by_product[ $product_id ]['teinvit_background'] = true;
    }

    ksort( $sources_by_product, SORT_NUMERIC );

    $products = [];
    foreach ( $sources_by_product as $product_id => $source_flags ) {
        $product = function_exists( 'wc_get_product' ) ? wc_get_product( $product_id ) : null;
        $raw_gallery_ids = [];
        if ( $product && method_exists( $product, 'get_gallery_image_ids' ) ) {
            $raw_gallery_ids = $product->get_gallery_image_ids();
        }
        if ( ! is_array( $raw_gallery_ids ) ) {
            $raw_gallery_ids = [];
        }

        $gallery_ids = [];
        $invalid_gallery_ids = [];
        foreach ( $raw_gallery_ids as $raw_id ) {
            $gallery_id = absint( $raw_id );
            if ( $gallery_id <= 0 ) {
                $invalid_gallery_ids[] = (string) $raw_id;
                continue;
            }

            $gallery_status = teinvit_media_seo_gallery_attachment_status( $gallery_id );
            if ( $gallery_status !== 'valid' ) {
                $invalid_gallery_ids[] = [
                    'attachment_id' => $gallery_id,
                    'reason'        => $gallery_status,
                ];
                continue;
            }

            $gallery_ids[] = $gallery_id;
        }

        $gallery_ids = array_values( array_unique( $gallery_ids ) );
        $sources = array_keys( array_filter( $source_flags ) );
        sort( $sources );

        $products[] = [
            'product_id'              => (int) $product_id,
            'product_name'            => $product && method_exists( $product, 'get_name' ) ? (string) $product->get_name() : (string) get_the_title( $product_id ),
            'match_sources'           => $sources,
            'gallery_count'           => count( $gallery_ids ),
            'gallery_image_ids'       => $gallery_ids,
            'invalid_gallery_image_ids' => $invalid_gallery_ids,
        ];
    }

    return [
        'attachment_id'             => $attachment_id,
        'thumbnail_product_ids'     => $thumbnail_ids,
        'background_product_ids'    => $background_ids,
        'products'                  => $products,
    ];
}

function teinvit_media_seo_gallery_empty_report() {
    return [
        'enabled'   => false,
        'actions'   => [],
        'conflicts' => [],
    ];
}

function teinvit_media_seo_empty_summary() {
    return [
        'total_rows'               => 0,
        'valid_rows'               => 0,
        'attachment_found'         => 0,
        'missing_attachments'      => 0,
        'non_attachment'           => 0,
        'non_image'                => 0,
        'duplicate_attachment_id'  => 0,
        'unchanged'                => 0,
        'would_update'             => 0,
        'skipped'                  => 0,
        'errors'                   => 0,
        'copy_to_gallery'          => 0,
        'gallery_product_thumbnail_matches' => 0,
        'gallery_product_background_matches' => 0,
        'gallery_products_affected' => 0,
        'gallery_images_total_found' => 0,
        'gallery_images_unique'    => 0,
        'gallery_duplicates_removed' => 0,
        'gallery_conflicts'        => 0,
        'gallery_would_update'     => 0,
        'gallery_unchanged'        => 0,
        'gallery_skipped'          => 0,
        'products_without_gallery' => 0,
        'warnings'                 => 0,
    ];
}

function teinvit_media_seo_gallery_unique_products_from_occurrences( array $occurrences ) {
    $products = [];

    foreach ( $occurrences as $occurrence ) {
        $product = isset( $occurrence['product'] ) && is_array( $occurrence['product'] ) ? $occurrence['product'] : [];
        $product_id = absint( $product['product_id'] ?? 0 );
        if ( $product_id <= 0 ) {
            continue;
        }

        if ( ! isset( $products[ $product_id ] ) ) {
            $products[ $product_id ] = [
                'product_id'    => $product_id,
                'product_name'  => (string) ( $product['product_name'] ?? '' ),
                'match_sources' => [],
            ];
        }

        $sources = isset( $product['match_sources'] ) && is_array( $product['match_sources'] ) ? $product['match_sources'] : [];
        foreach ( $sources as $source ) {
            $source = sanitize_key( (string) $source );
            if ( $source !== '' ) {
                $products[ $product_id ]['match_sources'][ $source ] = true;
            }
        }
    }

    foreach ( $products as $product_id => $product ) {
        $sources = array_keys( $product['match_sources'] );
        sort( $sources );
        $products[ $product_id ]['match_sources'] = $sources;
    }

    return array_values( $products );
}

function teinvit_media_seo_gallery_source_rows_from_proposals( array $proposals ) {
    $rows = [];

    foreach ( $proposals as $proposal ) {
        $line_number = (int) ( $proposal['line_number'] ?? 0 );
        if ( $line_number > 0 ) {
            $rows[ $line_number ] = $line_number;
        }
    }

    return array_values( $rows );
}

function teinvit_media_seo_gallery_source_attachments_from_proposals( array $proposals ) {
    $attachments = [];

    foreach ( $proposals as $proposal ) {
        $attachment_id = absint( $proposal['source_attachment_id'] ?? 0 );
        if ( $attachment_id > 0 ) {
            $attachments[ $attachment_id ] = $attachment_id;
        }
    }

    return array_values( $attachments );
}

function teinvit_media_seo_gallery_row_indexes_from_proposals( array $proposals ) {
    $indexes = [];

    foreach ( $proposals as $proposal ) {
        if ( ! array_key_exists( 'row_index', $proposal ) ) {
            continue;
        }

        $index = (int) $proposal['row_index'];
        if ( $index >= 0 ) {
            $indexes[ $index ] = $index;
        }
    }

    return array_values( $indexes );
}

function teinvit_media_seo_gallery_conflict_differences( array $proposals ) {
    $differences = [];

    foreach ( array_keys( teinvit_media_seo_update_columns() ) as $column ) {
        $values = [];
        $rows = [];

        foreach ( $proposals as $proposal ) {
            $metadata = isset( $proposal['metadata_normalized'] ) && is_array( $proposal['metadata_normalized'] )
                ? $proposal['metadata_normalized']
                : teinvit_media_seo_normalize_metadata_for_compare( isset( $proposal['metadata'] ) && is_array( $proposal['metadata'] ) ? $proposal['metadata'] : [] );
            $value = (string) ( $metadata[ $column ] ?? '' );
            $values[ $value ] = true;
            $rows[] = [
                'line_number'          => (int) ( $proposal['line_number'] ?? 0 ),
                'source_attachment_id' => absint( $proposal['source_attachment_id'] ?? 0 ),
                'value'                => $value,
            ];
        }

        if ( count( $values ) > 1 ) {
            $differences[ $column ] = $rows;
        }
    }

    return $differences;
}

function teinvit_media_seo_gallery_add_row_notice( array &$rows, array $row_indexes, $target_attachment_id, $status, $reason ) {
    $target_attachment_id = absint( $target_attachment_id );
    $status = sanitize_key( (string) $status );
    $reason = sanitize_key( (string) $reason );

    foreach ( $row_indexes as $row_index ) {
        $row_index = (int) $row_index;
        if ( ! isset( $rows[ $row_index ] ) || ! is_array( $rows[ $row_index ] ) ) {
            continue;
        }
        if ( empty( $rows[ $row_index ]['gallery'] ) || ! is_array( $rows[ $row_index ]['gallery'] ) ) {
            continue;
        }

        if ( $target_attachment_id > 0 ) {
            if ( $status === 'conflict' ) {
                $rows[ $row_index ]['gallery']['conflict_attachment_ids'][ $target_attachment_id ] = $target_attachment_id;
            } elseif ( $status === 'would_update' ) {
                $rows[ $row_index ]['gallery']['will_update_attachment_ids'][ $target_attachment_id ] = $target_attachment_id;
            } elseif ( $status === 'unchanged' ) {
                $rows[ $row_index ]['gallery']['unchanged_attachment_ids'][ $target_attachment_id ] = $target_attachment_id;
            } else {
                $rows[ $row_index ]['gallery']['skipped_attachment_ids'][ $target_attachment_id ] = $target_attachment_id;
            }
        }

        if ( $reason !== '' && ! in_array( $reason, $rows[ $row_index ]['gallery']['warnings'], true ) ) {
            $rows[ $row_index ]['gallery']['warnings'][] = $reason;
        }
    }
}

function teinvit_media_seo_build_gallery_extension( array $rows ) {
    $gallery = teinvit_media_seo_gallery_empty_report();
    $gallery['enabled'] = true;

    $summary = [
        'gallery_product_thumbnail_matches' => 0,
        'gallery_product_background_matches' => 0,
        'gallery_products_affected' => 0,
        'gallery_images_total_found' => 0,
        'gallery_images_unique' => 0,
        'gallery_duplicates_removed' => 0,
        'gallery_conflicts' => 0,
        'gallery_would_update' => 0,
        'gallery_unchanged' => 0,
        'gallery_skipped' => 0,
        'products_without_gallery' => 0,
        'warnings' => 0,
    ];

    $explicit_proposals = [];
    $targets = [];
    $thumbnail_products = [];
    $background_products = [];
    $affected_products = [];
    $valid_gallery_occurrences = 0;

    foreach ( $rows as $row_index => $row ) {
        $metadata = isset( $row['proposed_metadata'] ) && is_array( $row['proposed_metadata'] ) ? $row['proposed_metadata'] : [];
        $attachment_id = absint( $row['attachment_id'] ?? 0 );

        $rows[ $row_index ]['gallery'] = [
            'status'                    => 'skipped',
            'reason'                    => '',
            'products'                  => [],
            'products_count'            => 0,
            'gallery_image_ids'         => [],
            'gallery_image_count'       => 0,
            'duplicates_removed'        => 0,
            'will_update_attachment_ids' => [],
            'unchanged_attachment_ids'  => [],
            'skipped_attachment_ids'    => [],
            'conflict_attachment_ids'   => [],
            'warnings'                  => [],
        ];

        if ( $attachment_id > 0 ) {
            $explicit_proposals[ $attachment_id ][] = [
                'row_index'             => (int) $row_index,
                'line_number'           => (int) ( $row['line_number'] ?? 0 ),
                'source_attachment_id'  => $attachment_id,
                'metadata'              => $metadata,
                'metadata_normalized'   => teinvit_media_seo_normalize_metadata_for_compare( $metadata ),
                'signature'             => teinvit_media_seo_metadata_signature( $metadata ),
                'source_type'           => 'explicit_csv',
            ];
        }
    }

    foreach ( $rows as $row_index => $row ) {
        $metadata = isset( $row['proposed_metadata'] ) && is_array( $row['proposed_metadata'] ) ? $row['proposed_metadata'] : [];
        $attachment_id = absint( $row['attachment_id'] ?? 0 );
        $primary_status = (string) ( $row['primary_attachment_status'] ?? '' );
        $row_status = (string) ( $row['status'] ?? '' );

        if ( $primary_status !== 'valid' || empty( $metadata ) || ! in_array( $row_status, [ 'would_update', 'unchanged' ], true ) ) {
            $rows[ $row_index ]['gallery']['reason'] = empty( $metadata ) ? 'no_metadata_to_copy' : 'primary_attachment_skipped';
            continue;
        }

        $resolution = teinvit_media_seo_gallery_resolve_attachment( $attachment_id );
        $products = isset( $resolution['products'] ) && is_array( $resolution['products'] ) ? $resolution['products'] : [];
        $rows[ $row_index ]['gallery']['products'] = $products;
        $rows[ $row_index ]['gallery']['products_count'] = count( $products );

        foreach ( $resolution['thumbnail_product_ids'] ?? [] as $product_id ) {
            $thumbnail_products[ (int) $product_id ] = true;
        }
        foreach ( $resolution['background_product_ids'] ?? [] as $product_id ) {
            $background_products[ (int) $product_id ] = true;
        }

        if ( empty( $products ) ) {
            $rows[ $row_index ]['gallery']['warnings'][] = 'no_products_found';
            $summary['warnings']++;
            continue;
        }

        $row_occurrence_count = 0;
        $row_unique_gallery_ids = [];

        foreach ( $products as $product ) {
            $product_id = absint( $product['product_id'] ?? 0 );
            if ( $product_id > 0 ) {
                $affected_products[ $product_id ] = true;
            }

            $gallery_ids = isset( $product['gallery_image_ids'] ) && is_array( $product['gallery_image_ids'] ) ? $product['gallery_image_ids'] : [];
            $invalid_ids = isset( $product['invalid_gallery_image_ids'] ) && is_array( $product['invalid_gallery_image_ids'] ) ? $product['invalid_gallery_image_ids'] : [];

            if ( empty( $gallery_ids ) ) {
                $rows[ $row_index ]['gallery']['warnings'][] = 'product_without_gallery';
                $summary['products_without_gallery']++;
                $summary['warnings']++;
            }

            if ( ! empty( $invalid_ids ) ) {
                $rows[ $row_index ]['gallery']['warnings'][] = 'gallery_attachment_missing';
                $summary['gallery_skipped'] += count( $invalid_ids );
                $summary['warnings'] += count( $invalid_ids );
            }

            foreach ( $gallery_ids as $gallery_id ) {
                $gallery_id = absint( $gallery_id );
                if ( $gallery_id <= 0 ) {
                    continue;
                }

                $row_occurrence_count++;
                $valid_gallery_occurrences++;
                $row_unique_gallery_ids[ $gallery_id ] = $gallery_id;

                $targets[ $gallery_id ][] = [
                    'row_index'             => (int) $row_index,
                    'line_number'           => (int) ( $row['line_number'] ?? 0 ),
                    'source_attachment_id'  => $attachment_id,
                    'target_attachment_id'  => $gallery_id,
                    'metadata'              => $metadata,
                    'metadata_normalized'   => teinvit_media_seo_normalize_metadata_for_compare( $metadata ),
                    'signature'             => teinvit_media_seo_metadata_signature( $metadata ),
                    'source_type'           => 'gallery_copy',
                    'product'               => [
                        'product_id'    => $product_id,
                        'product_name'  => (string) ( $product['product_name'] ?? '' ),
                        'match_sources' => isset( $product['match_sources'] ) && is_array( $product['match_sources'] ) ? $product['match_sources'] : [],
                    ],
                ];
            }
        }

        $rows[ $row_index ]['gallery']['gallery_image_ids'] = array_values( $row_unique_gallery_ids );
        $rows[ $row_index ]['gallery']['gallery_image_count'] = count( $row_unique_gallery_ids );
        $rows[ $row_index ]['gallery']['duplicates_removed'] = max( 0, $row_occurrence_count - count( $row_unique_gallery_ids ) );
    }

    $summary['gallery_product_thumbnail_matches'] = count( $thumbnail_products );
    $summary['gallery_product_background_matches'] = count( $background_products );
    $summary['gallery_products_affected'] = count( $affected_products );
    $summary['gallery_images_total_found'] = $valid_gallery_occurrences;
    $summary['gallery_images_unique'] = count( $targets );
    $summary['gallery_duplicates_removed'] = max( 0, $valid_gallery_occurrences - count( $targets ) );

    ksort( $targets, SORT_NUMERIC );

    foreach ( $targets as $target_attachment_id => $occurrences ) {
        $target_attachment_id = absint( $target_attachment_id );
        $products = teinvit_media_seo_gallery_unique_products_from_occurrences( $occurrences );
        $row_indexes = teinvit_media_seo_gallery_row_indexes_from_proposals( $occurrences );
        $source_rows = teinvit_media_seo_gallery_source_rows_from_proposals( $occurrences );
        $source_attachments = teinvit_media_seo_gallery_source_attachments_from_proposals( $occurrences );
        $signatures = [];

        foreach ( $occurrences as $occurrence ) {
            $signature = (string) ( $occurrence['signature'] ?? '' );
            $signatures[ $signature ] = $occurrence;
        }

        $action = [
            'target_attachment_id' => $target_attachment_id,
            'status'               => 'skipped',
            'reason'               => '',
            'source_line_numbers'  => $source_rows,
            'source_attachment_ids' => $source_attachments,
            'products'             => $products,
            'proposed_metadata'    => [],
            'changes'              => [],
            'row_indexes'          => $row_indexes,
        ];

        $explicit = isset( $explicit_proposals[ $target_attachment_id ] ) ? $explicit_proposals[ $target_attachment_id ] : [];
        if ( ! empty( $explicit ) ) {
            $explicit_signatures = [];
            foreach ( $explicit as $explicit_proposal ) {
                if ( empty( $explicit_proposal['metadata'] ) ) {
                    continue;
                }
                $explicit_signatures[ (string) $explicit_proposal['signature'] ] = true;
            }

            $all_proposals = array_merge( $occurrences, $explicit );
            $all_row_indexes = teinvit_media_seo_gallery_row_indexes_from_proposals( $all_proposals );
            $has_explicit_conflict = count( $explicit_signatures ) > 1;
            if ( ! empty( $explicit_signatures ) ) {
                foreach ( array_keys( $signatures ) as $signature ) {
                    if ( ! isset( $explicit_signatures[ $signature ] ) ) {
                        $has_explicit_conflict = true;
                        break;
                    }
                }
            } elseif ( count( $signatures ) > 1 ) {
                $has_explicit_conflict = true;
            }

            if ( $has_explicit_conflict ) {
                $conflict = [
                    'target_attachment_id' => $target_attachment_id,
                    'reason'               => 'explicit_attachment_conflict',
                    'source_line_numbers'  => teinvit_media_seo_gallery_source_rows_from_proposals( $all_proposals ),
                    'source_attachment_ids' => teinvit_media_seo_gallery_source_attachments_from_proposals( $all_proposals ),
                    'products'             => $products,
                    'differences'          => teinvit_media_seo_gallery_conflict_differences( $all_proposals ),
                ];
                $gallery['conflicts'][] = $conflict;
                $action['status'] = 'conflict';
                $action['reason'] = 'explicit_attachment_conflict';
                $action['row_indexes'] = $all_row_indexes;
                $summary['gallery_conflicts']++;
                $summary['gallery_skipped']++;
                teinvit_media_seo_gallery_add_row_notice( $rows, $all_row_indexes, $target_attachment_id, 'conflict', 'explicit_attachment_conflict' );
            } else {
                $action['status'] = 'skipped';
                $action['reason'] = 'duplicate_gallery_attachment';
                $summary['gallery_skipped']++;
                teinvit_media_seo_gallery_add_row_notice( $rows, $row_indexes, $target_attachment_id, 'skipped', 'duplicate_gallery_attachment' );
            }

            $gallery['actions'][] = $action;
            continue;
        }

        if ( count( $signatures ) > 1 ) {
            $conflict = [
                'target_attachment_id' => $target_attachment_id,
                'reason'               => 'metadata_conflict',
                'source_line_numbers'  => $source_rows,
                'source_attachment_ids' => $source_attachments,
                'products'             => $products,
                'differences'          => teinvit_media_seo_gallery_conflict_differences( $occurrences ),
            ];
            $gallery['conflicts'][] = $conflict;
            $action['status'] = 'conflict';
            $action['reason'] = 'metadata_conflict';
            $summary['gallery_conflicts']++;
            $summary['gallery_skipped']++;
            teinvit_media_seo_gallery_add_row_notice( $rows, $row_indexes, $target_attachment_id, 'conflict', 'metadata_conflict' );
            $gallery['actions'][] = $action;
            continue;
        }

        $proposal = reset( $signatures );
        $metadata = isset( $proposal['metadata'] ) && is_array( $proposal['metadata'] ) ? $proposal['metadata'] : [];
        $action['proposed_metadata'] = $metadata;

        $attachment_status = teinvit_media_seo_gallery_attachment_status( $target_attachment_id );
        if ( $attachment_status !== 'valid' ) {
            $action['status'] = 'skipped';
            $action['reason'] = $attachment_status;
            $summary['gallery_skipped']++;
            $summary['warnings']++;
            teinvit_media_seo_gallery_add_row_notice( $rows, $row_indexes, $target_attachment_id, 'skipped', $attachment_status );
            $gallery['actions'][] = $action;
            continue;
        }

        $changes = teinvit_media_seo_build_changes_for_attachment( $target_attachment_id, $metadata );
        $action['changes'] = $changes;

        if ( empty( $changes ) ) {
            $action['status'] = 'unchanged';
            $action['reason'] = 'metadata_already_current';
            $summary['gallery_unchanged']++;
            teinvit_media_seo_gallery_add_row_notice( $rows, $row_indexes, $target_attachment_id, 'unchanged', '' );
        } else {
            $action['status'] = 'would_update';
            $summary['gallery_would_update']++;
            teinvit_media_seo_gallery_add_row_notice( $rows, $row_indexes, $target_attachment_id, 'would_update', '' );
        }

        $gallery['actions'][] = $action;
    }

    foreach ( $rows as $row_index => $row ) {
        if ( empty( $row['gallery'] ) || ! is_array( $row['gallery'] ) ) {
            continue;
        }

        foreach ( [ 'will_update_attachment_ids', 'unchanged_attachment_ids', 'skipped_attachment_ids', 'conflict_attachment_ids' ] as $key ) {
            $rows[ $row_index ]['gallery'][ $key ] = array_values( $rows[ $row_index ]['gallery'][ $key ] );
        }

        $primary_status = (string) ( $row['primary_attachment_status'] ?? '' );
        $metadata = isset( $row['proposed_metadata'] ) && is_array( $row['proposed_metadata'] ) ? $row['proposed_metadata'] : [];
        if ( $primary_status !== 'valid' || empty( $metadata ) ) {
            $rows[ $row_index ]['gallery']['status'] = 'skipped';
        } elseif ( ! empty( $rows[ $row_index ]['gallery']['conflict_attachment_ids'] ) ) {
            $rows[ $row_index ]['gallery']['status'] = 'conflict';
        } elseif ( ! empty( $rows[ $row_index ]['gallery']['warnings'] ) || ! empty( $rows[ $row_index ]['gallery']['skipped_attachment_ids'] ) ) {
            $rows[ $row_index ]['gallery']['status'] = 'warning';
        } else {
            $rows[ $row_index ]['gallery']['status'] = 'ok';
        }

        $rows[ $row_index ]['gallery']['warnings'] = array_values( array_unique( $rows[ $row_index ]['gallery']['warnings'] ) );
    }

    return [
        'rows'    => $rows,
        'summary' => $summary,
        'gallery' => $gallery,
    ];
}

function teinvit_media_seo_build_dry_run_report( $path, $copy_to_gallery = false ) {
    $copy_to_gallery = (bool) $copy_to_gallery;
    $parsed = teinvit_media_seo_parse_csv( $path );
    if ( is_wp_error( $parsed ) ) {
        $summary = teinvit_media_seo_empty_summary();
        $summary['errors'] = 1;
        $summary['copy_to_gallery'] = $copy_to_gallery ? 1 : 0;

        return [
            'copy_to_gallery' => $copy_to_gallery,
            'summary'         => $summary,
            'rows'            => [],
            'verticals'       => [],
            'gallery'         => teinvit_media_seo_gallery_empty_report(),
            'fatal_error'     => $parsed->get_error_message(),
        ];
    }

    $summary = teinvit_media_seo_empty_summary();
    $summary['total_rows'] = (int) $parsed['total_rows'];
    $summary['copy_to_gallery'] = $copy_to_gallery ? 1 : 0;

    $normalized_rows = [];
    $id_counts = [];
    $verticals = [];

    foreach ( $parsed['rows'] as $row ) {
        $normalized = teinvit_media_seo_normalize_row( $row['data'] );
        $attachment_id = (int) $normalized['attachment_id'];

        if ( $attachment_id > 0 ) {
            if ( ! isset( $id_counts[ $attachment_id ] ) ) {
                $id_counts[ $attachment_id ] = 0;
            }
            $id_counts[ $attachment_id ]++;
        }

        if ( $normalized['vertical'] !== '' ) {
            $verticals[ $normalized['vertical'] ] = true;
        }

        $normalized_rows[] = [
            'line_number' => (int) $row['line_number'],
            'data'        => $normalized,
        ];
    }

    $rows = [];
    $update_columns = teinvit_media_seo_update_columns();

    foreach ( $normalized_rows as $row ) {
        $data = $row['data'];
        $attachment_id = (int) $data['attachment_id'];
        $proposed_metadata = teinvit_media_seo_proposed_metadata_from_row( $data );
        $row_report = [
            'line_number'    => (int) $row['line_number'],
            'attachment_id'  => $attachment_id,
            'vertical'       => $data['vertical'],
            'product_family' => $data['product_family'],
            'product_skus'   => $data['product_skus'],
            'product_names'  => $data['product_names'],
            'status'         => 'skipped',
            'reason'         => '',
            'changes'        => [],
        ];

        if ( $copy_to_gallery ) {
            $row_report['primary_attachment_status'] = 'skipped';
            $row_report['proposed_metadata'] = $proposed_metadata;
        }

        if ( $attachment_id <= 0 ) {
            $row_report['status'] = 'error';
            $row_report['reason'] = 'attachment_id lipsa sau invalid';
            if ( $copy_to_gallery ) {
                $row_report['primary_attachment_status'] = 'invalid';
            }
            $summary['errors']++;
            $rows[] = $row_report;
            continue;
        }

        if ( isset( $id_counts[ $attachment_id ] ) && $id_counts[ $attachment_id ] > 1 ) {
            $row_report['reason'] = 'attachment_id duplicat in CSV';
            $summary['duplicate_attachment_id']++;
            $summary['skipped']++;
            $rows[] = $row_report;
            continue;
        }

        $post = get_post( $attachment_id );
        if ( ! $post ) {
            $row_report['reason'] = 'attachment_id inexistent';
            if ( $copy_to_gallery ) {
                $row_report['primary_attachment_status'] = 'invalid';
            }
            $summary['missing_attachments']++;
            $summary['skipped']++;
            $rows[] = $row_report;
            continue;
        }

        if ( $post->post_type !== 'attachment' ) {
            $row_report['reason'] = 'ID-ul exista, dar nu este attachment';
            if ( $copy_to_gallery ) {
                $row_report['primary_attachment_status'] = 'invalid';
            }
            $summary['non_attachment']++;
            $summary['skipped']++;
            $rows[] = $row_report;
            continue;
        }

        $summary['attachment_found']++;

        if ( ! wp_attachment_is_image( $attachment_id ) ) {
            $row_report['reason'] = 'attachment-ul nu este imagine';
            if ( $copy_to_gallery ) {
                $row_report['primary_attachment_status'] = 'invalid';
            }
            $summary['non_image']++;
            $summary['skipped']++;
            $rows[] = $row_report;
            continue;
        }

        if ( $copy_to_gallery ) {
            $row_report['primary_attachment_status'] = 'valid';
        }

        $current = teinvit_media_seo_current_values( $attachment_id );
        $has_update_value = false;

        foreach ( $update_columns as $column => $meta ) {
            $new_value = (string) $data[ $column ];
            if ( trim( $new_value ) === '' ) {
                continue;
            }

            $has_update_value = true;
            $current_value = (string) ( $current[ $column ] ?? '' );
            if ( $current_value !== $new_value ) {
                $row_report['changes'][ $column ] = [
                    'label'   => $meta['label'],
                    'target'  => $meta['target'],
                    'current' => $current_value,
                    'new'     => $new_value,
                ];
            }
        }

        if ( ! $has_update_value ) {
            $row_report['reason'] = 'niciun camp SEO completat; campurile goale sunt ignorate';
            $summary['skipped']++;
            $rows[] = $row_report;
            continue;
        }

        $summary['valid_rows']++;

        if ( empty( $row_report['changes'] ) ) {
            $row_report['status'] = 'unchanged';
            $row_report['reason'] = 'valorile existente sunt identice';
            $summary['unchanged']++;
        } else {
            $row_report['status'] = 'would_update';
            $row_report['reason'] = '';
            $summary['would_update']++;
        }

        $rows[] = $row_report;
    }

    $gallery = teinvit_media_seo_gallery_empty_report();
    if ( $copy_to_gallery ) {
        $gallery_report = teinvit_media_seo_build_gallery_extension( $rows );
        $rows = $gallery_report['rows'];
        $gallery = $gallery_report['gallery'];
        foreach ( $gallery_report['summary'] as $key => $value ) {
            $summary[ $key ] = (int) $value;
        }
        $summary['copy_to_gallery'] = 1;
    }

    return [
        'copy_to_gallery' => $copy_to_gallery,
        'summary'         => $summary,
        'rows'            => $rows,
        'verticals'       => array_keys( $verticals ),
        'gallery'         => $gallery,
    ];
}

function teinvit_media_seo_report_json_encode( array $report ) {
    $json = wp_json_encode( $report );
    return is_string( $json ) ? $json : '{}';
}

function teinvit_media_seo_report_json_decode( $json ) {
    $report = json_decode( (string) $json, true );
    return is_array( $report ) ? $report : [];
}

function teinvit_media_seo_create_import_record( array $data ) {
    global $wpdb;

    $table = teinvit_media_seo_imports_table();
    $report = isset( $data['report'] ) && is_array( $data['report'] ) ? $data['report'] : [];
    $summary = isset( $report['summary'] ) && is_array( $report['summary'] ) ? $report['summary'] : teinvit_media_seo_empty_summary();
    $verticals = isset( $report['verticals'] ) && is_array( $report['verticals'] ) ? array_map( 'sanitize_key', $report['verticals'] ) : [];

    $inserted = $wpdb->insert(
        $table,
        [
            'job_id'            => sanitize_text_field( (string) $data['job_id'] ),
            'original_filename' => sanitize_file_name( (string) $data['original_filename'] ),
            'stored_filename'   => sanitize_file_name( (string) $data['stored_filename'] ),
            'stored_path'       => (string) $data['stored_path'],
            'file_hash'         => sanitize_text_field( (string) $data['file_hash'] ),
            'uploaded_by'       => (int) get_current_user_id(),
            'uploaded_at'       => current_time( 'mysql' ),
            'vertical'          => sanitize_text_field( implode( ',', array_filter( $verticals ) ) ),
            'status'            => sanitize_key( (string) ( $data['status'] ?? 'dry_run' ) ),
            'total_rows'        => (int) ( $summary['total_rows'] ?? 0 ),
            'valid_rows'        => (int) ( $summary['valid_rows'] ?? 0 ),
            'updated_count'     => (int) ( $data['updated_count'] ?? 0 ),
            'unchanged_count'   => (int) ( $summary['unchanged'] ?? 0 ),
            'skipped_count'     => (int) ( $summary['skipped'] ?? 0 ),
            'error_count'       => (int) ( $summary['errors'] ?? 0 ),
            'report_json'       => teinvit_media_seo_report_json_encode( $report ),
            'applied_at'        => null,
        ]
    );

    return $inserted ? true : new WP_Error( 'history_insert_failed', 'Importul nu a putut fi salvat in istoric.' );
}

function teinvit_media_seo_update_import_record( $job_id, array $data ) {
    global $wpdb;

    $job_id = sanitize_text_field( (string) $job_id );
    if ( $job_id === '' ) {
        return false;
    }

    return $wpdb->update( teinvit_media_seo_imports_table(), $data, [ 'job_id' => $job_id ] );
}

function teinvit_media_seo_get_import_record( $job_id ) {
    global $wpdb;

    $job_id = sanitize_text_field( (string) $job_id );
    if ( $job_id === '' ) {
        return null;
    }

    return $wpdb->get_row(
        $wpdb->prepare( 'SELECT * FROM ' . teinvit_media_seo_imports_table() . ' WHERE job_id = %s LIMIT 1', $job_id ),
        ARRAY_A
    );
}

function teinvit_media_seo_get_import_history( $limit = 30 ) {
    global $wpdb;

    $limit = max( 1, min( 100, (int) $limit ) );

    return $wpdb->get_results(
        $wpdb->prepare( 'SELECT * FROM ' . teinvit_media_seo_imports_table() . ' ORDER BY uploaded_at DESC, id DESC LIMIT %d', $limit ),
        ARRAY_A
    );
}

function teinvit_media_seo_apply_row_changes( array $row ) {
    $attachment_id = absint( $row['attachment_id'] ?? 0 );
    if ( $attachment_id <= 0 ) {
        return new WP_Error( 'invalid_attachment_id', 'attachment_id invalid.' );
    }

    $post = get_post( $attachment_id );
    if ( ! $post || $post->post_type !== 'attachment' ) {
        return new WP_Error( 'not_attachment', 'ID-ul nu este attachment.' );
    }

    if ( ! wp_attachment_is_image( $attachment_id ) ) {
        return new WP_Error( 'not_image', 'Attachment-ul nu este imagine.' );
    }

    $changes = isset( $row['changes'] ) && is_array( $row['changes'] ) ? $row['changes'] : [];
    if ( empty( $changes ) ) {
        return 'unchanged';
    }

    $current = teinvit_media_seo_current_values( $attachment_id );
    $effective_changes = [];
    foreach ( $changes as $column => $change ) {
        $new_value = isset( $change['new'] ) ? (string) $change['new'] : '';
        if ( isset( $current[ $column ] ) && (string) $current[ $column ] === $new_value ) {
            continue;
        }
        $effective_changes[ $column ] = $change;
    }

    if ( empty( $effective_changes ) ) {
        return 'unchanged';
    }

    $post_update = [ 'ID' => $attachment_id ];
    foreach ( $effective_changes as $column => $change ) {
        $new_value = isset( $change['new'] ) ? (string) $change['new'] : '';
        if ( $column === 'image_title' ) {
            $post_update['post_title'] = sanitize_text_field( $new_value );
        } elseif ( $column === 'image_caption' ) {
            $post_update['post_excerpt'] = sanitize_textarea_field( $new_value );
        } elseif ( $column === 'image_description' ) {
            $post_update['post_content'] = sanitize_textarea_field( $new_value );
        }
    }

    if ( count( $post_update ) > 1 ) {
        $updated_post = wp_update_post( $post_update, true );
        if ( is_wp_error( $updated_post ) ) {
            return $updated_post;
        }
    }

    if ( isset( $effective_changes['image_alt_text']['new'] ) ) {
        update_post_meta( $attachment_id, '_wp_attachment_image_alt', sanitize_text_field( (string) $effective_changes['image_alt_text']['new'] ) );
    }

    return 'updated';
}

function teinvit_media_seo_apply_gallery_action( array $action ) {
    $status = (string) ( $action['status'] ?? '' );
    if ( $status !== 'would_update' ) {
        return in_array( $status, [ 'unchanged', 'conflict', 'skipped' ], true ) ? $status : 'skipped';
    }

    $attachment_id = absint( $action['target_attachment_id'] ?? 0 );
    if ( $attachment_id <= 0 ) {
        return new WP_Error( 'invalid_attachment_id', 'gallery attachment_id invalid.' );
    }

    $attachment_status = teinvit_media_seo_gallery_attachment_status( $attachment_id );
    if ( $attachment_status !== 'valid' ) {
        return new WP_Error( $attachment_status, 'Imaginea de galerie nu mai este valida.' );
    }

    return teinvit_media_seo_apply_row_changes(
        [
            'attachment_id' => $attachment_id,
            'changes'       => isset( $action['changes'] ) && is_array( $action['changes'] ) ? $action['changes'] : [],
        ]
    );
}

function teinvit_media_seo_start_apply_job( array $record ) {
    $report = teinvit_media_seo_report_json_decode( $record['report_json'] ?? '' );
    if ( empty( $report['rows'] ) || ! is_array( $report['rows'] ) ) {
        return new WP_Error( 'missing_report', 'Raportul dry-run nu este disponibil pentru acest job.' );
    }

    $report['apply'] = [
        'started_at' => current_time( 'mysql' ),
        'offset'     => 0,
    ];

    teinvit_media_seo_update_import_record(
        $record['job_id'],
        [
            'status'      => 'applying',
            'report_json' => teinvit_media_seo_report_json_encode( $report ),
        ]
    );

    return true;
}

function teinvit_media_seo_process_apply_batch( $job_id, $offset = 0, $batch_size = 75 ) {
    $record = teinvit_media_seo_get_import_record( $job_id );
    if ( ! $record ) {
        return new WP_Error( 'missing_job', 'Job-ul Media SEO nu exista.' );
    }

    if ( (string) $record['status'] !== 'applying' ) {
        return new WP_Error( 'job_not_applying', 'Job-ul Media SEO nu este in starea de aplicare.' );
    }

    $report = teinvit_media_seo_report_json_decode( $record['report_json'] ?? '' );
    $rows = isset( $report['rows'] ) && is_array( $report['rows'] ) ? $report['rows'] : [];
    $copy_to_gallery = ! empty( $report['copy_to_gallery'] );
    $gallery = isset( $report['gallery'] ) && is_array( $report['gallery'] ) ? $report['gallery'] : teinvit_media_seo_gallery_empty_report();
    $gallery_actions = $copy_to_gallery && isset( $gallery['actions'] ) && is_array( $gallery['actions'] ) ? $gallery['actions'] : [];
    $row_total = count( $rows );
    $total = $row_total + count( $gallery_actions );
    $offset = max( 0, (int) $offset );
    $batch_size = max( 1, min( 100, (int) $batch_size ) );
    $end = min( $total, $offset + $batch_size );

    for ( $i = $offset; $i < $end; $i++ ) {
        if ( $i < $row_total ) {
            if ( ! isset( $rows[ $i ] ) || ! is_array( $rows[ $i ] ) ) {
                continue;
            }

            if ( (string) ( $rows[ $i ]['status'] ?? '' ) !== 'would_update' ) {
                $rows[ $i ]['apply_status'] = (string) ( $rows[ $i ]['status'] ?? 'skipped' );
                continue;
            }

            $result = teinvit_media_seo_apply_row_changes( $rows[ $i ] );
            if ( is_wp_error( $result ) ) {
                $rows[ $i ]['apply_status'] = 'error';
                $rows[ $i ]['apply_reason'] = $result->get_error_message();
            } else {
                $rows[ $i ]['apply_status'] = (string) $result;
                $rows[ $i ]['apply_reason'] = '';
            }
        } else {
            $action_index = $i - $row_total;
            if ( ! isset( $gallery_actions[ $action_index ] ) || ! is_array( $gallery_actions[ $action_index ] ) ) {
                continue;
            }

            $result = teinvit_media_seo_apply_gallery_action( $gallery_actions[ $action_index ] );
            if ( is_wp_error( $result ) ) {
                $gallery_actions[ $action_index ]['apply_status'] = 'skipped';
                $gallery_actions[ $action_index ]['apply_reason'] = $result->get_error_message();
                $gallery_actions[ $action_index ]['apply_reason_code'] = $result->get_error_code();
            } else {
                $gallery_actions[ $action_index ]['apply_status'] = (string) $result;
                $gallery_actions[ $action_index ]['apply_reason'] = '';
            }
        }
    }

    $report['rows'] = $rows;
    if ( $copy_to_gallery ) {
        $gallery['actions'] = $gallery_actions;
        $report['gallery'] = $gallery;
    }
    $report['apply']['offset'] = $end;

    $data = [
        'report_json' => teinvit_media_seo_report_json_encode( $report ),
    ];

    $done = $end >= $total;
    if ( $done ) {
        $final = teinvit_media_seo_final_counts_from_report( $report );
        $report['apply']['finished_at'] = current_time( 'mysql' );
        $report['apply']['summary'] = $final;

        $data = [
            'status'          => 'applied',
            'updated_count'   => (int) $final['updated'],
            'unchanged_count' => (int) $final['unchanged'],
            'skipped_count'   => (int) $final['skipped'],
            'error_count'     => (int) $final['errors'],
            'report_json'     => teinvit_media_seo_report_json_encode( $report ),
            'applied_at'      => current_time( 'mysql' ),
        ];
    }

    teinvit_media_seo_update_import_record( $job_id, $data );

    return [
        'done'        => $done,
        'next_offset' => $end,
        'total'       => $total,
    ];
}

function teinvit_media_seo_final_counts_from_report( array $report ) {
    $rows = isset( $report['rows'] ) && is_array( $report['rows'] ) ? $report['rows'] : [];
    $copy_to_gallery = ! empty( $report['copy_to_gallery'] );
    $gallery = isset( $report['gallery'] ) && is_array( $report['gallery'] ) ? $report['gallery'] : [];
    $gallery_actions = $copy_to_gallery && isset( $gallery['actions'] ) && is_array( $gallery['actions'] ) ? $gallery['actions'] : [];
    $counts = [
        'processed'         => count( $rows ) + count( $gallery_actions ),
        'updated'           => 0,
        'unchanged'         => 0,
        'skipped'           => 0,
        'errors'            => 0,
        'gallery_processed' => count( $gallery_actions ),
        'gallery_updated'   => 0,
        'gallery_unchanged' => 0,
        'gallery_skipped'   => 0,
        'gallery_errors'    => 0,
    ];

    foreach ( $rows as $row ) {
        $status = (string) ( $row['status'] ?? '' );
        $apply_status = (string) ( $row['apply_status'] ?? '' );

        if ( $apply_status === 'updated' ) {
            $counts['updated']++;
        } elseif ( $apply_status === 'unchanged' ) {
            $counts['unchanged']++;
        } elseif ( $apply_status === 'error' || $status === 'error' ) {
            $counts['errors']++;
        } elseif ( $status === 'unchanged' ) {
            $counts['unchanged']++;
        } elseif ( $status === 'skipped' ) {
            $counts['skipped']++;
        }
    }

    foreach ( $gallery_actions as $action ) {
        $status = (string) ( $action['status'] ?? '' );
        $apply_status = (string) ( $action['apply_status'] ?? '' );

        if ( $apply_status === 'updated' ) {
            $counts['updated']++;
            $counts['gallery_updated']++;
        } elseif ( $apply_status === 'unchanged' ) {
            $counts['unchanged']++;
            $counts['gallery_unchanged']++;
        } elseif ( $apply_status === 'error' ) {
            $counts['errors']++;
            $counts['gallery_errors']++;
        } elseif ( $apply_status === 'skipped' || in_array( $status, [ 'conflict', 'skipped' ], true ) ) {
            $counts['skipped']++;
            $counts['gallery_skipped']++;
        } elseif ( $status === 'unchanged' ) {
            $counts['unchanged']++;
            $counts['gallery_unchanged']++;
        }
    }

    return $counts;
}
