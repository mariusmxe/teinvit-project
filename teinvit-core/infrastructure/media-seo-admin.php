<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function teinvit_media_seo_admin_capability() {
    return function_exists( 'teinvit_admin_capability' ) ? teinvit_admin_capability() : 'manage_woocommerce';
}

function teinvit_media_seo_admin_url( array $args = [] ) {
    return add_query_arg( array_merge( [ 'page' => 'teinvit-media-seo' ], $args ), admin_url( 'admin.php' ) );
}

function teinvit_media_seo_register_admin_page() {
    $parent = function_exists( 'teinvit_admin_root_slug' ) ? teinvit_admin_root_slug() : 'teinvit-admin-hub';

    add_submenu_page(
        $parent,
        'Media SEO',
        'Media SEO',
        teinvit_media_seo_admin_capability(),
        'teinvit-media-seo',
        'teinvit_media_seo_render_admin_page'
    );
}
add_action( 'admin_menu', 'teinvit_media_seo_register_admin_page', 40 );

function teinvit_media_seo_admin_notice_message( $code ) {
    $messages = [
        'dry_run_ready' => [ 'success', 'Dry-run-ul a fost generat. Verifica raportul inainte de aplicare.' ],
        'dry_run_failed' => [ 'error', 'CSV-ul a fost salvat, dar dry-run-ul a esuat. Verifica eroarea din raport.' ],
        'applied'       => [ 'success', 'Importul Media SEO a fost aplicat.' ],
        'upload_error'  => [ 'error', 'CSV-ul nu a putut fi incarcat.' ],
        'apply_error'   => [ 'error', 'Importul nu a putut fi aplicat.' ],
    ];

    return $messages[ $code ] ?? null;
}

function teinvit_media_seo_render_admin_page() {
    if ( ! current_user_can( teinvit_media_seo_admin_capability() ) ) {
        wp_die( 'Unauthorized' );
    }

    $job_id = isset( $_GET['job_id'] ) ? sanitize_text_field( wp_unslash( $_GET['job_id'] ) ) : '';
    $notice = isset( $_GET['media_seo_notice'] ) ? sanitize_key( wp_unslash( $_GET['media_seo_notice'] ) ) : '';
    $error = isset( $_GET['media_seo_error'] ) ? sanitize_key( wp_unslash( $_GET['media_seo_error'] ) ) : '';
    $record = $job_id !== '' ? teinvit_media_seo_get_import_record( $job_id ) : null;
    $report = $record ? teinvit_media_seo_report_json_decode( $record['report_json'] ?? '' ) : [];

    echo '<div class="wrap">';
    echo '<h1>Import SEO Imagini</h1>';

    $notice_data = $notice !== '' ? teinvit_media_seo_admin_notice_message( $notice ) : null;
    if ( $notice_data ) {
        echo '<div class="notice notice-' . esc_attr( $notice_data[0] ) . ' is-dismissible"><p>' . esc_html( $notice_data[1] ) . '</p></div>';
    }
    if ( $error !== '' ) {
        echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( teinvit_media_seo_admin_error_label( $error ) ) . '</p></div>';
    }

    teinvit_media_seo_render_intro_section();
    teinvit_media_seo_render_csv_format_section();
    teinvit_media_seo_render_upload_section();

    if ( $record && ! empty( $report ) ) {
        teinvit_media_seo_render_current_job_section( $record, $report );
    }

    teinvit_media_seo_render_history_section();

    echo '</div>';
}

function teinvit_media_seo_admin_error_label( $code ) {
    $labels = [
        'missing_file'      => 'Nu a fost incarcat niciun fisier CSV.',
        'invalid_nonce'     => 'Sesiunea de securitate a expirat. Reincearca.',
        'missing_job'       => 'Job-ul Media SEO nu exista.',
        'invalid_confirm'   => 'Confirmarea explicita lipseste.',
        'invalid_status'    => 'Job-ul nu este disponibil pentru aplicare.',
        'download_failed'   => 'Fisierul CSV nu poate fi descarcat.',
        'history_failed'    => 'Importul nu a putut fi salvat in istoric.',
    ];

    return $labels[ $code ] ?? 'A aparut o eroare in fluxul Media SEO.';
}

function teinvit_media_seo_report_copy_to_gallery_enabled( array $report ) {
    if ( array_key_exists( 'copy_to_gallery', $report ) ) {
        return ! empty( $report['copy_to_gallery'] );
    }

    return ! empty( $report['summary']['copy_to_gallery'] );
}

function teinvit_media_seo_render_intro_section() {
    echo '<p>Aceasta pagina actualizeaza metadata SEO pentru imaginile din WordPress Media Library: ALT text, Title, Caption si Description.</p>';
    echo '<p><strong>Important:</strong> importul lucreaza pe attachment-uri imagine. Nu modifica produse WooCommerce, SKU-uri, galerii, preview-uri, PDF-uri, comenzi sau rute publice TeInvit.</p>';
}

function teinvit_media_seo_render_csv_format_section() {
    $header = implode( ',', teinvit_media_seo_expected_columns() );

    echo '<h2>Format CSV acceptat</h2>';
    echo '<p>Header asteptat:</p>';
    echo '<code style="display:block;white-space:normal;padding:8px;margin-bottom:8px;">' . esc_html( $header ) . '</code>';
    echo '<p class="description">Identificatorul principal este strict <code>attachment_id</code>. Coloanele <code>image_url</code>, <code>product_skus</code> si <code>product_names</code> sunt folosite doar pentru context si raportare.</p>';
    echo '<p class="description">Campurile SEO goale sunt ignorate si nu sterg valorile existente.</p>';
}

function teinvit_media_seo_render_upload_section() {
    echo '<h2>Upload CSV</h2>';
    echo '<form method="post" enctype="multipart/form-data" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
    echo '<input type="hidden" name="action" value="teinvit_media_seo_dry_run">';
    wp_nonce_field( 'teinvit_media_seo_dry_run', 'teinvit_media_seo_dry_run_nonce' );
    echo '<p><input type="file" name="teinvit_media_seo_csv" accept=".csv,text/csv" required></p>';
    echo '<p class="description">Limita initiala: ' . esc_html( (string) teinvit_media_seo_max_rows() ) . ' randuri si ' . esc_html( size_format( teinvit_media_seo_max_file_size() ) ) . ' per fisier.</p>';
    echo '<div style="display:flex;gap:16px;align-items:flex-start;flex-wrap:wrap;margin-top:12px;">';
    echo '<div>';
    submit_button( 'Ruleaza verificare / Dry-run', 'primary', 'teinvit_media_seo_submit', false );
    echo '</div>';
    echo '<label style="max-width:720px;margin-top:4px;"><input type="checkbox" name="teinvit_media_seo_copy_to_gallery" value="1"> <strong>Copiaza datele si pe imaginile din galerie</strong>';
    echo '<span class="description" style="display:block;margin-top:4px;">Daca este bifat, metadatele importate pentru fiecare attachment_id vor fi copiate si pe imaginile din galeria produselor care folosesc acea imagine ca imagine principala sau background TeInvit.</span></label>';
    echo '</div>';
    echo '</form>';
}

function teinvit_media_seo_render_current_job_section( array $record, array $report ) {
    $status = (string) ( $record['status'] ?? '' );

    echo '<hr>';
    echo '<h2>Raport job curent</h2>';
    echo '<p><strong>Fisier:</strong> ' . esc_html( (string) $record['original_filename'] ) . ' ';
    echo '<strong>Status:</strong> ' . esc_html( teinvit_media_seo_display_status( $record ) ) . '</p>';

    if ( ! empty( $report['fatal_error'] ) ) {
        echo '<div class="notice notice-error"><p>' . esc_html( (string) $report['fatal_error'] ) . '</p></div>';
    }

    if ( ! empty( $report['apply']['summary'] ) && is_array( $report['apply']['summary'] ) ) {
        echo '<h3>Summary final</h3>';
        teinvit_media_seo_render_apply_summary( $report['apply']['summary'] );
    }

    echo '<h3>Summary dry-run</h3>';
    teinvit_media_seo_render_dry_run_summary( $report['summary'] ?? [] );
    teinvit_media_seo_render_report_rows_table( $report );
    teinvit_media_seo_render_gallery_conflicts_section( $report );

    if ( $status === 'dry_run' ) {
        teinvit_media_seo_render_apply_confirmation( $record, $report );
    }
}

function teinvit_media_seo_render_dry_run_summary( array $summary ) {
    $copy_to_gallery = ! empty( $summary['copy_to_gallery'] );
    $labels = [
        'total_rows'              => 'Randuri citite',
        'valid_rows'              => 'Randuri valide',
        'attachment_found'        => 'Attachment-uri gasite',
        'missing_attachments'     => 'Attachment-uri lipsa',
        'non_attachment'          => 'ID-uri care nu sunt attachment-uri',
        'non_image'               => 'Attachment-uri care nu sunt imagini',
        'duplicate_attachment_id' => 'Duplicate attachment_id',
        'unchanged'               => 'Unchanged',
        'would_update'            => 'Would update',
        'skipped'                 => 'Skipped',
        'errors'                  => 'Erori',
    ];

    echo '<table class="widefat striped" style="max-width:720px;"><tbody>';
    echo '<tr><th>Copiere pe galerie</th><td>' . esc_html( $copy_to_gallery ? 'activ' : 'inactiv' ) . '</td></tr>';
    foreach ( $labels as $key => $label ) {
        echo '<tr><th>' . esc_html( $label ) . '</th><td>' . esc_html( (string) (int) ( $summary[ $key ] ?? 0 ) ) . '</td></tr>';
    }
    if ( $copy_to_gallery ) {
        $gallery_labels = [
            'gallery_product_thumbnail_matches' => 'Produse gasite prin _thumbnail_id',
            'gallery_product_background_matches' => 'Produse gasite prin _teinvit_background_image_id',
            'gallery_products_affected' => 'Produse afectate unice',
            'gallery_images_total_found' => 'Imagini galerie gasite',
            'gallery_images_unique' => 'Imagini galerie unice',
            'gallery_duplicates_removed' => 'Duplicate galerie eliminate',
            'gallery_conflicts' => 'Conflicte galerie',
            'gallery_would_update' => 'Imagini galerie would update',
            'gallery_unchanged' => 'Imagini galerie unchanged',
            'gallery_skipped' => 'Imagini galerie skipped',
            'products_without_gallery' => 'Produse fara galerie',
            'warnings' => 'Warnings',
        ];
        foreach ( $gallery_labels as $key => $label ) {
            echo '<tr><th>' . esc_html( $label ) . '</th><td>' . esc_html( (string) (int) ( $summary[ $key ] ?? 0 ) ) . '</td></tr>';
        }
    }
    echo '</tbody></table>';
}

function teinvit_media_seo_render_apply_summary( array $summary ) {
    $labels = [
        'processed' => 'Procesate',
        'updated'   => 'Updated',
        'unchanged' => 'Unchanged',
        'skipped'   => 'Skipped',
        'errors'    => 'Errors',
    ];

    echo '<table class="widefat striped" style="max-width:720px;"><tbody>';
    foreach ( $labels as $key => $label ) {
        echo '<tr><th>' . esc_html( $label ) . '</th><td>' . esc_html( (string) (int) ( $summary[ $key ] ?? 0 ) ) . '</td></tr>';
    }
    if ( ! empty( $summary['gallery_processed'] ) ) {
        $gallery_labels = [
            'gallery_processed' => 'Galerie procesate',
            'gallery_updated'   => 'Galerie updated',
            'gallery_unchanged' => 'Galerie unchanged',
            'gallery_skipped'   => 'Galerie skipped',
            'gallery_errors'    => 'Galerie errors',
        ];
        foreach ( $gallery_labels as $key => $label ) {
            echo '<tr><th>' . esc_html( $label ) . '</th><td>' . esc_html( (string) (int) ( $summary[ $key ] ?? 0 ) ) . '</td></tr>';
        }
    }
    echo '</tbody></table>';
}

function teinvit_media_seo_render_report_rows_table( array $report ) {
    $rows = isset( $report['rows'] ) && is_array( $report['rows'] ) ? $report['rows'] : [];
    if ( empty( $rows ) ) {
        return;
    }

    echo '<h3>Randuri CSV</h3>';
    echo '<div style="max-width:100%;overflow:auto;">';
    echo '<table class="widefat striped">';
    echo '<thead><tr>';
    $headings = [ 'Rand', 'Attachment ID', 'Vertical', 'Product family', 'SKU-uri', 'Produse', 'Status', 'Motiv', 'Campuri modificate' ];
    $copy_to_gallery = teinvit_media_seo_report_copy_to_gallery_enabled( $report );
    if ( $copy_to_gallery ) {
        $headings[] = 'Galerie';
    }
    foreach ( $headings as $heading ) {
        echo '<th>' . esc_html( $heading ) . '</th>';
    }
    echo '</tr></thead><tbody>';

    foreach ( $rows as $row ) {
        $status = (string) ( $row['apply_status'] ?? $row['status'] ?? '' );
        if ( $status === 'updated' ) {
            $status = 'updated';
        }

        echo '<tr>';
        echo '<td>' . esc_html( (string) (int) ( $row['line_number'] ?? 0 ) ) . '</td>';
        echo '<td>' . esc_html( (string) (int) ( $row['attachment_id'] ?? 0 ) ) . '</td>';
        echo '<td>' . esc_html( (string) ( $row['vertical'] ?? '' ) ) . '</td>';
        echo '<td>' . esc_html( (string) ( $row['product_family'] ?? '' ) ) . '</td>';
        echo '<td>' . esc_html( (string) ( $row['product_skus'] ?? '' ) ) . '</td>';
        echo '<td>' . esc_html( (string) ( $row['product_names'] ?? '' ) ) . '</td>';
        echo '<td><code>' . esc_html( $status ) . '</code></td>';
        echo '<td>' . esc_html( (string) ( $row['apply_reason'] ?? $row['reason'] ?? '' ) ) . '</td>';
        echo '<td>' . teinvit_media_seo_render_changes_cell( $row['changes'] ?? [] ) . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        if ( $copy_to_gallery ) {
            echo '<td>' . teinvit_media_seo_render_gallery_cell( $row ) . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        }
        echo '</tr>';
    }

    echo '</tbody></table>';
    echo '</div>';
}

function teinvit_media_seo_render_changes_cell( $changes ) {
    if ( empty( $changes ) || ! is_array( $changes ) ) {
        return '&mdash;';
    }

    $labels = [];
    foreach ( $changes as $change ) {
        $labels[] = isset( $change['label'] ) ? (string) $change['label'] : 'field';
    }

    $html = '<details><summary>' . esc_html( implode( ', ', $labels ) ) . '</summary>';
    $html .= '<table class="widefat striped" style="margin-top:8px;"><thead><tr><th>Camp</th><th>Current</th><th>New</th></tr></thead><tbody>';
    foreach ( $changes as $change ) {
        $html .= '<tr>';
        $html .= '<td>' . esc_html( (string) ( $change['label'] ?? '' ) ) . '</td>';
        $html .= '<td>' . esc_html( teinvit_media_seo_excerpt( (string) ( $change['current'] ?? '' ) ) ) . '</td>';
        $html .= '<td>' . esc_html( teinvit_media_seo_excerpt( (string) ( $change['new'] ?? '' ) ) ) . '</td>';
        $html .= '</tr>';
    }
    $html .= '</tbody></table></details>';

    return $html;
}

function teinvit_media_seo_render_simple_list( $values ) {
    if ( empty( $values ) || ! is_array( $values ) ) {
        return '&mdash;';
    }

    $out = [];
    foreach ( $values as $value ) {
        if ( is_array( $value ) ) {
            $attachment_id = isset( $value['attachment_id'] ) ? absint( $value['attachment_id'] ) : 0;
            $reason = isset( $value['reason'] ) ? sanitize_key( (string) $value['reason'] ) : '';
            $out[] = trim( ( $attachment_id > 0 ? '#' . $attachment_id : '' ) . ( $reason !== '' ? ' ' . $reason : '' ) );
        } else {
            $out[] = (string) $value;
        }
    }

    $out = array_values( array_filter( $out, static function( $value ) {
        return trim( (string) $value ) !== '';
    } ) );

    return ! empty( $out ) ? esc_html( implode( ', ', $out ) ) : '&mdash;';
}

function teinvit_media_seo_render_metadata_cell( $metadata ) {
    if ( empty( $metadata ) || ! is_array( $metadata ) ) {
        return '&mdash;';
    }

    $labels = teinvit_media_seo_update_columns();
    $html = '<table class="widefat striped" style="margin-top:8px;"><tbody>';
    foreach ( $labels as $column => $meta ) {
        if ( ! array_key_exists( $column, $metadata ) ) {
            continue;
        }
        $html .= '<tr><th>' . esc_html( (string) ( $meta['label'] ?? $column ) ) . '</th><td>' . esc_html( teinvit_media_seo_excerpt( (string) $metadata[ $column ] ) ) . '</td></tr>';
    }
    $html .= '</tbody></table>';

    return $html;
}

function teinvit_media_seo_render_gallery_products_table( array $products ) {
    if ( empty( $products ) ) {
        return '<p><em>Nu au fost gasite produse.</em></p>';
    }

    $html = '<table class="widefat striped" style="margin-top:8px;"><thead><tr><th>Product ID</th><th>Nume</th><th>Sursa</th><th>Nr. galerie</th><th>Galerie</th><th>Invalide</th></tr></thead><tbody>';
    foreach ( $products as $product ) {
        $sources = isset( $product['match_sources'] ) && is_array( $product['match_sources'] ) ? $product['match_sources'] : [];
        $gallery_ids = isset( $product['gallery_image_ids'] ) && is_array( $product['gallery_image_ids'] ) ? $product['gallery_image_ids'] : [];
        $invalid_ids = isset( $product['invalid_gallery_image_ids'] ) && is_array( $product['invalid_gallery_image_ids'] ) ? $product['invalid_gallery_image_ids'] : [];

        $html .= '<tr>';
        $html .= '<td>' . esc_html( (string) absint( $product['product_id'] ?? 0 ) ) . '</td>';
        $html .= '<td>' . esc_html( (string) ( $product['product_name'] ?? '' ) ) . '</td>';
        $html .= '<td>' . esc_html( implode( ', ', array_map( 'sanitize_key', $sources ) ) ) . '</td>';
        $html .= '<td>' . esc_html( (string) (int) ( $product['gallery_count'] ?? 0 ) ) . '</td>';
        $html .= '<td>' . teinvit_media_seo_render_simple_list( $gallery_ids ) . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        $html .= '<td>' . teinvit_media_seo_render_simple_list( $invalid_ids ) . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        $html .= '</tr>';
    }
    $html .= '</tbody></table>';

    return $html;
}

function teinvit_media_seo_render_gallery_cell( array $row ) {
    $gallery = isset( $row['gallery'] ) && is_array( $row['gallery'] ) ? $row['gallery'] : [];
    if ( empty( $gallery ) ) {
        return '&mdash;';
    }

    $status = sanitize_key( (string) ( $gallery['status'] ?? 'skipped' ) );
    $products_count = (int) ( $gallery['products_count'] ?? 0 );
    $image_count = (int) ( $gallery['gallery_image_count'] ?? 0 );
    $will_update = isset( $gallery['will_update_attachment_ids'] ) && is_array( $gallery['will_update_attachment_ids'] ) ? $gallery['will_update_attachment_ids'] : [];
    $unchanged = isset( $gallery['unchanged_attachment_ids'] ) && is_array( $gallery['unchanged_attachment_ids'] ) ? $gallery['unchanged_attachment_ids'] : [];
    $skipped = isset( $gallery['skipped_attachment_ids'] ) && is_array( $gallery['skipped_attachment_ids'] ) ? $gallery['skipped_attachment_ids'] : [];
    $conflicts = isset( $gallery['conflict_attachment_ids'] ) && is_array( $gallery['conflict_attachment_ids'] ) ? $gallery['conflict_attachment_ids'] : [];
    $warnings = isset( $gallery['warnings'] ) && is_array( $gallery['warnings'] ) ? $gallery['warnings'] : [];

    $summary = '<code>' . esc_html( $status ) . '</code> ';
    $summary .= esc_html( 'Produse: ' . $products_count . ', imagini unice: ' . $image_count . ', update: ' . count( $will_update ) . ', conflicte: ' . count( $conflicts ) );

    $html = '<details><summary>' . $summary . '</summary>';
    $html .= '<p><strong>Status imagine principala:</strong> ' . esc_html( (string) ( $row['primary_attachment_status'] ?? '' ) ) . '</p>';
    $html .= '<p><strong>Metadata propusa:</strong></p>';
    $html .= teinvit_media_seo_render_metadata_cell( $row['proposed_metadata'] ?? [] );
    $html .= '<p><strong>Produse gasite:</strong></p>';
    $html .= teinvit_media_seo_render_gallery_products_table( isset( $gallery['products'] ) && is_array( $gallery['products'] ) ? $gallery['products'] : [] );
    $html .= '<p><strong>Imagini galerie unice:</strong> ' . teinvit_media_seo_render_simple_list( $gallery['gallery_image_ids'] ?? [] ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    $html .= '<p><strong>Duplicate eliminate pe rand:</strong> ' . esc_html( (string) (int) ( $gallery['duplicates_removed'] ?? 0 ) ) . '</p>';
    $html .= '<p><strong>Would update:</strong> ' . teinvit_media_seo_render_simple_list( $will_update ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    $html .= '<p><strong>Unchanged:</strong> ' . teinvit_media_seo_render_simple_list( $unchanged ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    $html .= '<p><strong>Skipped:</strong> ' . teinvit_media_seo_render_simple_list( $skipped ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    $html .= '<p><strong>Conflicte:</strong> ' . teinvit_media_seo_render_simple_list( $conflicts ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    if ( ! empty( $warnings ) ) {
        $html .= '<p><strong>Warnings:</strong> ' . esc_html( implode( ', ', array_map( 'sanitize_key', $warnings ) ) ) . '</p>';
    }
    $html .= '</details>';

    return $html;
}

function teinvit_media_seo_render_conflict_differences( $differences ) {
    if ( empty( $differences ) || ! is_array( $differences ) ) {
        return '&mdash;';
    }

    $labels = teinvit_media_seo_update_columns();
    $html = '<details><summary>Diferente metadata</summary>';
    foreach ( $differences as $column => $rows ) {
        $label = isset( $labels[ $column ]['label'] ) ? (string) $labels[ $column ]['label'] : (string) $column;
        $html .= '<h4>' . esc_html( $label ) . '</h4>';
        $html .= '<table class="widefat striped"><thead><tr><th>Rand CSV</th><th>Attachment sursa</th><th>Valoare</th></tr></thead><tbody>';
        foreach ( is_array( $rows ) ? $rows : [] as $row ) {
            $html .= '<tr>';
            $html .= '<td>' . esc_html( (string) (int) ( $row['line_number'] ?? 0 ) ) . '</td>';
            $html .= '<td>' . esc_html( (string) absint( $row['source_attachment_id'] ?? 0 ) ) . '</td>';
            $html .= '<td>' . esc_html( teinvit_media_seo_excerpt( (string) ( $row['value'] ?? '' ) ) ) . '</td>';
            $html .= '</tr>';
        }
        $html .= '</tbody></table>';
    }
    $html .= '</details>';

    return $html;
}

function teinvit_media_seo_render_gallery_conflicts_section( array $report ) {
    if ( ! teinvit_media_seo_report_copy_to_gallery_enabled( $report ) ) {
        return;
    }

    $gallery = isset( $report['gallery'] ) && is_array( $report['gallery'] ) ? $report['gallery'] : [];
    $conflicts = isset( $gallery['conflicts'] ) && is_array( $gallery['conflicts'] ) ? $gallery['conflicts'] : [];

    echo '<h3>Conflicte galerie</h3>';
    if ( empty( $conflicts ) ) {
        echo '<p>Nu au fost detectate conflicte pentru imaginile de galerie.</p>';
        return;
    }

    echo '<div style="max-width:100%;overflow:auto;">';
    echo '<table class="widefat striped">';
    echo '<thead><tr><th>Attachment galerie</th><th>Motiv</th><th>Randuri CSV</th><th>Attachment-uri sursa</th><th>Produse</th><th>Diferente</th></tr></thead><tbody>';
    foreach ( $conflicts as $conflict ) {
        $products = isset( $conflict['products'] ) && is_array( $conflict['products'] ) ? $conflict['products'] : [];
        $product_labels = [];
        foreach ( $products as $product ) {
            $product_labels[] = '#' . absint( $product['product_id'] ?? 0 ) . ' ' . (string) ( $product['product_name'] ?? '' );
        }

        echo '<tr>';
        echo '<td>' . esc_html( (string) absint( $conflict['target_attachment_id'] ?? 0 ) ) . '</td>';
        echo '<td><code>' . esc_html( sanitize_key( (string) ( $conflict['reason'] ?? '' ) ) ) . '</code></td>';
        echo '<td>' . teinvit_media_seo_render_simple_list( $conflict['source_line_numbers'] ?? [] ) . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo '<td>' . teinvit_media_seo_render_simple_list( $conflict['source_attachment_ids'] ?? [] ) . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo '<td>' . esc_html( implode( '; ', $product_labels ) ) . '</td>';
        echo '<td>' . teinvit_media_seo_render_conflict_differences( $conflict['differences'] ?? [] ) . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo '</tr>';
    }
    echo '</tbody></table>';
    echo '</div>';
}

function teinvit_media_seo_excerpt( $value, $length = 140 ) {
    $value = trim( (string) $value );
    if ( $value === '' ) {
        return '';
    }

    return wp_html_excerpt( $value, $length, '...' );
}

function teinvit_media_seo_render_apply_confirmation( array $record, array $report ) {
    $summary = isset( $report['summary'] ) && is_array( $report['summary'] ) ? $report['summary'] : [];
    $would_update = (int) ( $summary['would_update'] ?? 0 ) + (int) ( $summary['gallery_would_update'] ?? 0 );

    echo '<h3>Confirmare import</h3>';
    if ( $would_update <= 0 ) {
        echo '<p>Dry-run-ul nu contine randuri care ar modifica Media Library.</p>';
        return;
    }

    echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
    echo '<input type="hidden" name="action" value="teinvit_media_seo_apply">';
    echo '<input type="hidden" name="job_id" value="' . esc_attr( (string) $record['job_id'] ) . '">';
    wp_nonce_field( 'teinvit_media_seo_apply_' . $record['job_id'], 'teinvit_media_seo_apply_nonce' );
    echo '<p><label><input type="checkbox" name="teinvit_media_seo_confirm_checked" value="1" required> Am verificat raportul dry-run si confirm aplicarea modificarilor.</label></p>';
    echo '<p><label>Scrie <code>IMPORT</code> pentru confirmare:<br><input type="text" name="teinvit_media_seo_confirm_text" class="regular-text" required></label></p>';
    submit_button( 'Aplica modificarile', 'primary', 'teinvit_media_seo_apply_submit' );
    echo '</form>';
}

function teinvit_media_seo_history_gallery_label( array $record ) {
    $report = teinvit_media_seo_report_json_decode( $record['report_json'] ?? '' );
    if ( ! teinvit_media_seo_report_copy_to_gallery_enabled( $report ) ) {
        return 'Galerie: inactiv';
    }

    $summary = isset( $report['summary'] ) && is_array( $report['summary'] ) ? $report['summary'] : [];
    $apply_summary = isset( $report['apply']['summary'] ) && is_array( $report['apply']['summary'] ) ? $report['apply']['summary'] : [];
    $updated = (int) ( $apply_summary['gallery_updated'] ?? 0 );
    $planned = (int) ( $summary['gallery_would_update'] ?? 0 );
    $display_update = ! empty( $apply_summary ) ? $updated : $planned;
    $conflicts = (int) ( $summary['gallery_conflicts'] ?? 0 );
    $skipped = (int) ( $apply_summary['gallery_skipped'] ?? ( $summary['gallery_skipped'] ?? 0 ) );

    return 'Galerie: activ | update ' . $display_update . ' | conflicte ' . $conflicts . ' | skipped ' . $skipped;
}

function teinvit_media_seo_render_history_section() {
    $history = teinvit_media_seo_get_import_history( 30 );

    echo '<hr>';
    echo '<h2>Istoric fisiere incarcate</h2>';
    if ( empty( $history ) ) {
        echo '<p>Nu exista importuri Media SEO in istoric.</p>';
        return;
    }

    echo '<div style="max-width:100%;overflow:auto;">';
    echo '<table class="widefat striped">';
    echo '<thead><tr>';
    foreach ( [ 'Data', 'Utilizator', 'Verticala', 'Fisier', 'Status', 'Galerie', 'Randuri', 'Updated', 'Unchanged', 'Skipped', 'Errors', 'Actiuni' ] as $heading ) {
        echo '<th>' . esc_html( $heading ) . '</th>';
    }
    echo '</tr></thead><tbody>';

    foreach ( $history as $row ) {
        $user = ! empty( $row['uploaded_by'] ) ? get_userdata( (int) $row['uploaded_by'] ) : null;
        $download_url = teinvit_media_seo_download_url( $row );
        $view_url = teinvit_media_seo_admin_url( [ 'job_id' => (string) $row['job_id'] ] );

        echo '<tr>';
        echo '<td>' . esc_html( (string) ( $row['uploaded_at'] ?? '' ) ) . '</td>';
        echo '<td>' . esc_html( $user ? $user->user_login : '-' ) . '</td>';
        echo '<td>' . esc_html( (string) ( $row['vertical'] ?? '' ) ) . '</td>';
        echo '<td>' . esc_html( (string) ( $row['original_filename'] ?? '' ) ) . '</td>';
        echo '<td><code>' . esc_html( teinvit_media_seo_display_status( $row ) ) . '</code></td>';
        echo '<td>' . esc_html( teinvit_media_seo_history_gallery_label( $row ) ) . '</td>';
        echo '<td>' . esc_html( (string) (int) ( $row['total_rows'] ?? 0 ) ) . '</td>';
        echo '<td>' . esc_html( (string) (int) ( $row['updated_count'] ?? 0 ) ) . '</td>';
        echo '<td>' . esc_html( (string) (int) ( $row['unchanged_count'] ?? 0 ) ) . '</td>';
        echo '<td>' . esc_html( (string) (int) ( $row['skipped_count'] ?? 0 ) ) . '</td>';
        echo '<td>' . esc_html( (string) (int) ( $row['error_count'] ?? 0 ) ) . '</td>';
        echo '<td><a class="button button-small" href="' . esc_url( $view_url ) . '">Raport</a> ';
        if ( $download_url !== '' ) {
            echo '<a class="button button-small" href="' . esc_url( $download_url ) . '">Download CSV</a>';
        } else {
            echo '<span class="description">CSV lipsa</span>';
        }
        echo '</td>';
        echo '</tr>';
    }

    echo '</tbody></table>';
    echo '</div>';
}

function teinvit_media_seo_display_status( array $record ) {
    $path = isset( $record['stored_path'] ) ? (string) $record['stored_path'] : '';
    if ( $path !== '' && ! file_exists( $path ) ) {
        return 'deleted';
    }

    return sanitize_key( (string) ( $record['status'] ?? '' ) );
}

function teinvit_media_seo_download_url( array $record ) {
    if ( empty( $record['job_id'] ) || empty( $record['stored_path'] ) || ! file_exists( (string) $record['stored_path'] ) ) {
        return '';
    }

    $job_id = sanitize_text_field( (string) $record['job_id'] );
    $url = add_query_arg(
        [
            'action' => 'teinvit_media_seo_download',
            'job_id' => $job_id,
        ],
        admin_url( 'admin-post.php' )
    );

    return wp_nonce_url( $url, 'teinvit_media_seo_download_' . $job_id, '_wpnonce' );
}

function teinvit_media_seo_handle_dry_run() {
    if ( ! current_user_can( teinvit_media_seo_admin_capability() ) ) {
        wp_die( 'Unauthorized' );
    }

    if ( ! check_admin_referer( 'teinvit_media_seo_dry_run', 'teinvit_media_seo_dry_run_nonce' ) ) {
        wp_safe_redirect( teinvit_media_seo_admin_url( [ 'media_seo_error' => 'invalid_nonce' ] ) );
        exit;
    }

    if ( empty( $_FILES['teinvit_media_seo_csv'] ) || ! is_array( $_FILES['teinvit_media_seo_csv'] ) ) {
        wp_safe_redirect( teinvit_media_seo_admin_url( [ 'media_seo_error' => 'missing_file' ] ) );
        exit;
    }

    $stored = teinvit_media_seo_store_upload( $_FILES['teinvit_media_seo_csv'] );
    if ( is_wp_error( $stored ) ) {
        wp_safe_redirect( teinvit_media_seo_admin_url( [ 'media_seo_error' => 'upload_error' ] ) );
        exit;
    }

    $copy_to_gallery = ! empty( $_POST['teinvit_media_seo_copy_to_gallery'] );
    $report = teinvit_media_seo_build_dry_run_report( $stored['stored_path'], $copy_to_gallery );
    $status = ! empty( $report['fatal_error'] ) ? 'failed' : 'dry_run';
    $created = teinvit_media_seo_create_import_record(
        [
            'job_id'            => $stored['job_id'],
            'original_filename' => $stored['original_filename'],
            'stored_filename'   => $stored['stored_filename'],
            'stored_path'       => $stored['stored_path'],
            'file_hash'         => $stored['file_hash'],
            'status'            => $status,
            'report'            => $report,
        ]
    );

    if ( is_wp_error( $created ) ) {
        wp_safe_redirect( teinvit_media_seo_admin_url( [ 'media_seo_error' => 'history_failed' ] ) );
        exit;
    }

    wp_safe_redirect(
        teinvit_media_seo_admin_url(
            [
                'job_id'           => $stored['job_id'],
                'media_seo_notice' => $status === 'dry_run' ? 'dry_run_ready' : 'dry_run_failed',
            ]
        )
    );
    exit;
}
add_action( 'admin_post_teinvit_media_seo_dry_run', 'teinvit_media_seo_handle_dry_run' );

function teinvit_media_seo_handle_apply() {
    if ( ! current_user_can( teinvit_media_seo_admin_capability() ) ) {
        wp_die( 'Unauthorized' );
    }

    $job_id = isset( $_POST['job_id'] ) ? sanitize_text_field( wp_unslash( $_POST['job_id'] ) ) : '';
    if ( $job_id === '' ) {
        wp_safe_redirect( teinvit_media_seo_admin_url( [ 'media_seo_error' => 'missing_job' ] ) );
        exit;
    }

    if ( ! check_admin_referer( 'teinvit_media_seo_apply_' . $job_id, 'teinvit_media_seo_apply_nonce' ) ) {
        wp_safe_redirect( teinvit_media_seo_admin_url( [ 'job_id' => $job_id, 'media_seo_error' => 'invalid_nonce' ] ) );
        exit;
    }

    $checked = ! empty( $_POST['teinvit_media_seo_confirm_checked'] );
    $confirm_text = isset( $_POST['teinvit_media_seo_confirm_text'] ) ? sanitize_text_field( wp_unslash( $_POST['teinvit_media_seo_confirm_text'] ) ) : '';
    if ( ! $checked || $confirm_text !== 'IMPORT' ) {
        wp_safe_redirect( teinvit_media_seo_admin_url( [ 'job_id' => $job_id, 'media_seo_error' => 'invalid_confirm' ] ) );
        exit;
    }

    $record = teinvit_media_seo_get_import_record( $job_id );
    if ( ! $record ) {
        wp_safe_redirect( teinvit_media_seo_admin_url( [ 'media_seo_error' => 'missing_job' ] ) );
        exit;
    }

    if ( (string) $record['status'] !== 'dry_run' ) {
        wp_safe_redirect( teinvit_media_seo_admin_url( [ 'job_id' => $job_id, 'media_seo_error' => 'invalid_status' ] ) );
        exit;
    }

    $started = teinvit_media_seo_start_apply_job( $record );
    if ( is_wp_error( $started ) ) {
        wp_safe_redirect( teinvit_media_seo_admin_url( [ 'job_id' => $job_id, 'media_seo_error' => 'apply_error' ] ) );
        exit;
    }

    $batch_url = add_query_arg(
        [
            'action' => 'teinvit_media_seo_apply_batch',
            'job_id' => $job_id,
            'offset' => 0,
        ],
        admin_url( 'admin-post.php' )
    );

    wp_safe_redirect( add_query_arg( '_wpnonce', wp_create_nonce( 'teinvit_media_seo_apply_batch_' . $job_id ), $batch_url ) );
    exit;
}
add_action( 'admin_post_teinvit_media_seo_apply', 'teinvit_media_seo_handle_apply' );

function teinvit_media_seo_handle_apply_batch() {
    if ( ! current_user_can( teinvit_media_seo_admin_capability() ) ) {
        wp_die( 'Unauthorized' );
    }

    $job_id = isset( $_GET['job_id'] ) ? sanitize_text_field( wp_unslash( $_GET['job_id'] ) ) : '';
    $offset = isset( $_GET['offset'] ) ? absint( $_GET['offset'] ) : 0;
    if ( $job_id === '' ) {
        wp_safe_redirect( teinvit_media_seo_admin_url( [ 'media_seo_error' => 'missing_job' ] ) );
        exit;
    }

    $nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
    if ( ! wp_verify_nonce( $nonce, 'teinvit_media_seo_apply_batch_' . $job_id ) ) {
        wp_safe_redirect( teinvit_media_seo_admin_url( [ 'job_id' => $job_id, 'media_seo_error' => 'invalid_nonce' ] ) );
        exit;
    }

    $result = teinvit_media_seo_process_apply_batch( $job_id, $offset, 75 );
    if ( is_wp_error( $result ) ) {
        teinvit_media_seo_update_import_record( $job_id, [ 'status' => 'failed' ] );
        wp_safe_redirect( teinvit_media_seo_admin_url( [ 'job_id' => $job_id, 'media_seo_error' => 'apply_error' ] ) );
        exit;
    }

    if ( ! empty( $result['done'] ) ) {
        wp_safe_redirect( teinvit_media_seo_admin_url( [ 'job_id' => $job_id, 'media_seo_notice' => 'applied' ] ) );
        exit;
    }

    $next_url = add_query_arg(
        [
            'action' => 'teinvit_media_seo_apply_batch',
            'job_id' => $job_id,
            'offset' => (int) $result['next_offset'],
        ],
        admin_url( 'admin-post.php' )
    );

    wp_safe_redirect( add_query_arg( '_wpnonce', wp_create_nonce( 'teinvit_media_seo_apply_batch_' . $job_id ), $next_url ) );
    exit;
}
add_action( 'admin_post_teinvit_media_seo_apply_batch', 'teinvit_media_seo_handle_apply_batch' );

function teinvit_media_seo_handle_download() {
    if ( ! current_user_can( teinvit_media_seo_admin_capability() ) ) {
        wp_die( 'Unauthorized' );
    }

    $job_id = isset( $_GET['job_id'] ) ? sanitize_text_field( wp_unslash( $_GET['job_id'] ) ) : '';
    if ( $job_id === '' ) {
        wp_die( 'Job invalid.' );
    }

    $nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
    if ( ! wp_verify_nonce( $nonce, 'teinvit_media_seo_download_' . $job_id ) ) {
        wp_die( 'Nonce invalid.' );
    }

    $record = teinvit_media_seo_get_import_record( $job_id );
    if ( ! $record || empty( $record['stored_path'] ) ) {
        wp_die( 'Fisier inexistent.' );
    }

    $dir = teinvit_media_seo_upload_dir();
    if ( is_wp_error( $dir ) ) {
        wp_die( 'Director invalid.' );
    }

    $base_real = realpath( $dir['basedir'] );
    $file_real = realpath( (string) $record['stored_path'] );
    if ( ! $base_real || ! $file_real || strpos( wp_normalize_path( $file_real ), trailingslashit( wp_normalize_path( $base_real ) ) ) !== 0 ) {
        wp_die( 'Fisier invalid.' );
    }

    if ( ! is_readable( $file_real ) ) {
        wp_die( 'Fisierul nu poate fi citit.' );
    }

    $download_name = sanitize_file_name( (string) ( $record['original_filename'] ?: $record['stored_filename'] ) );
    if ( $download_name === '' ) {
        $download_name = 'teinvit-media-seo.csv';
    }

    nocache_headers();
    header( 'Content-Type: text/csv; charset=utf-8' );
    header( 'Content-Disposition: attachment; filename="' . $download_name . '"' );
    header( 'Content-Length: ' . filesize( $file_real ) );
    readfile( $file_real ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
    exit;
}
add_action( 'admin_post_teinvit_media_seo_download', 'teinvit_media_seo_handle_download' );
