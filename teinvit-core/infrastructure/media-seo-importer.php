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
    ];
}

function teinvit_media_seo_build_dry_run_report( $path ) {
    $parsed = teinvit_media_seo_parse_csv( $path );
    if ( is_wp_error( $parsed ) ) {
        $summary = teinvit_media_seo_empty_summary();
        $summary['errors'] = 1;

        return [
            'summary'     => $summary,
            'rows'        => [],
            'verticals'   => [],
            'fatal_error' => $parsed->get_error_message(),
        ];
    }

    $summary = teinvit_media_seo_empty_summary();
    $summary['total_rows'] = (int) $parsed['total_rows'];

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

        if ( $attachment_id <= 0 ) {
            $row_report['status'] = 'error';
            $row_report['reason'] = 'attachment_id lipsa sau invalid';
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
            $summary['missing_attachments']++;
            $summary['skipped']++;
            $rows[] = $row_report;
            continue;
        }

        if ( $post->post_type !== 'attachment' ) {
            $row_report['reason'] = 'ID-ul exista, dar nu este attachment';
            $summary['non_attachment']++;
            $summary['skipped']++;
            $rows[] = $row_report;
            continue;
        }

        $summary['attachment_found']++;

        if ( ! wp_attachment_is_image( $attachment_id ) ) {
            $row_report['reason'] = 'attachment-ul nu este imagine';
            $summary['non_image']++;
            $summary['skipped']++;
            $rows[] = $row_report;
            continue;
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

    return [
        'summary'   => $summary,
        'rows'      => $rows,
        'verticals' => array_keys( $verticals ),
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
    $total = count( $rows );
    $offset = max( 0, (int) $offset );
    $batch_size = max( 1, min( 100, (int) $batch_size ) );
    $end = min( $total, $offset + $batch_size );

    for ( $i = $offset; $i < $end; $i++ ) {
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
    }

    $report['rows'] = $rows;
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
    $counts = [
        'processed' => count( $rows ),
        'updated'   => 0,
        'unchanged' => 0,
        'skipped'   => 0,
        'errors'    => 0,
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

    return $counts;
}
