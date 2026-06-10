<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class TeInvit_Saga_Admin_Page {
    const MENU_SLUG = 'teinvit-saga-export';

    public static function register() {
        add_action( 'admin_menu', [ __CLASS__, 'register_menu' ], 130 );
    }

    public static function register_menu() {
        $parent_slug = function_exists( 'teinvit_admin_root_slug' ) ? teinvit_admin_root_slug() : 'woocommerce';

        add_submenu_page(
            $parent_slug,
            'Export SAGA',
            'Export SAGA',
            TeInvit_Saga_Download_Handler::capability(),
            self::MENU_SLUG,
            [ __CLASS__, 'render' ]
        );
    }

    public static function render() {
        if ( ! current_user_can( TeInvit_Saga_Download_Handler::capability() ) ) {
            wp_die( esc_html__( 'Nu ai permisiunea necesara pentru Export SAGA.', 'teinvit' ) );
        }

        $notice = '';
        $preview = null;

        if ( isset( $_POST['teinvit_saga_settings_action'] ) ) {
            check_admin_referer( TeInvit_Saga_Download_Handler::NONCE_ACTION, 'teinvit_saga_nonce' );
            $action = sanitize_key( wp_unslash( $_POST['teinvit_saga_settings_action'] ) );

            if ( $action === 'save' ) {
                TeInvit_Saga_Export_Settings::save( TeInvit_Saga_Export_Settings::from_request( $_POST ) );
                $notice = 'Setarile Export SAGA au fost salvate.';
            } elseif ( $action === 'preview' ) {
                $preview = TeInvit_Saga_Download_Handler::build_export_from_request( $_POST );
            }
        }

        $settings = isset( $_POST['teinvit_saga_settings_action'] ) && $_POST['teinvit_saga_settings_action'] === 'preview'
            ? TeInvit_Saga_Export_Settings::from_request( $_POST )
            : TeInvit_Saga_Export_Settings::get();
        $range = TeInvit_Saga_Export_Date_Range::from_request( $_POST );
        $export_type = sanitize_key( wp_unslash( $_POST['export_type'] ?? 'orders' ) );
        if ( ! in_array( $export_type, [ 'orders', 'invoices' ], true ) ) {
            $export_type = 'orders';
        }

        $recent_diagnostics = TeInvit_Saga_Download_Handler::consume_recent_diagnostics();

        echo '<div class="wrap teinvit-saga-export">';
        echo '<h1>TeInvit - Export SAGA</h1>';

        if ( $notice !== '' ) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $notice ) . '</p></div>';
        }

        if ( is_array( $recent_diagnostics ) ) {
            self::render_diagnostics_array( 'Diagnostic ultimul export', $recent_diagnostics );
        }

        if ( is_array( $preview ) ) {
            self::render_diagnostics_result( 'Diagnostic preview', $preview );
        }

        echo '<form method="post" action="' . esc_url( admin_url( 'admin.php?page=' . self::MENU_SLUG ) ) . '">';
        wp_nonce_field( TeInvit_Saga_Download_Handler::NONCE_ACTION, 'teinvit_saga_nonce' );
        echo '<input type="hidden" name="action" value="' . esc_attr( TeInvit_Saga_Download_Handler::ACTION ) . '">';

        self::render_filters( $export_type, $range );
        self::render_account_settings( $settings );
        self::render_supplier_settings( $settings );
        self::render_client_meta_settings( $settings );
        self::render_unavailable_invoice_options();
        self::render_buttons();

        echo '</form>';
        self::render_inline_script();
        echo '</div>';
    }

    private static function render_filters( $export_type, array $range ) {
        echo '<h2>Filtre export</h2>';
        echo '<table class="form-table" role="presentation"><tbody>';

        echo '<tr><th scope="row"><label for="teinvit-saga-period">Perioada</label></th><td>';
        echo '<select id="teinvit-saga-period" name="period">';
        foreach ( TeInvit_Saga_Export_Date_Range::options() as $value => $label ) {
            echo '<option value="' . esc_attr( $value ) . '"' . selected( $range['period'], $value, false ) . '>' . esc_html( $label ) . '</option>';
        }
        echo '</select>';
        echo '<span class="teinvit-saga-custom-dates" style="margin-left:12px;">';
        echo '<label>Data inceput <input type="date" name="date_start" value="' . esc_attr( $range['start_date'] ) . '"></label> ';
        echo '<label>Data sfarsit <input type="date" name="date_end" value="' . esc_attr( $range['end_date'] ) . '"></label>';
        echo '</span>';
        echo '<p class="description">Facturi: filtrare dupa _wf_invoice_date. Comenzi: filtrare dupa data comenzii WooCommerce.</p>';
        echo '</td></tr>';

        echo '<tr><th scope="row"><label for="teinvit-saga-export-type">Tip export</label></th><td>';
        echo '<select id="teinvit-saga-export-type" name="export_type">';
        echo '<option value="orders"' . selected( $export_type, 'orders', false ) . '>Comenzi</option>';
        echo '<option value="invoices"' . selected( $export_type, 'invoices', false ) . '>Facturi</option>';
        echo '</select>';
        echo '</td></tr>';

        echo '</tbody></table>';
    }

    private static function render_account_settings( array $settings ) {
        echo '<h2>Conturi si optiuni linii</h2>';
        echo '<table class="form-table" role="presentation"><tbody>';
        self::text_row( 'Cont contabil produse', 'product_account', $settings['product_account'] );
        self::text_row( 'Cont contabil discount', 'discount_account', $settings['discount_account'] );
        self::text_row( 'Cont contabil taxa procesare / fee', 'fee_account', $settings['fee_account'] );
        self::text_row( 'Cont contabil transport', 'shipping_account', $settings['shipping_account'] );
        self::text_row( 'Denumire transport pentru SAGA', 'shipping_label', $settings['shipping_label'], 'regular-text' );
        self::text_row( 'Cod gestiune', 'inventory_code', $settings['inventory_code'], 'regular-text', 'Gol inseamna fara gestiune; in XML Facturi se exporta <Gestiune></Gestiune>.' );

        echo '<tr><th scope="row"><label for="county_format">Format judet</label></th><td>';
        echo '<select id="county_format" name="county_format">';
        echo '<option value="code"' . selected( $settings['county_format'], 'code', false ) . '>Cod</option>';
        echo '</select>';
        echo '<p class="description">Pentru Facturi, judetul se exporta ca abreviere/cod.</p>';
        echo '</td></tr>';

        echo '</tbody></table>';
    }

    private static function render_supplier_settings( array $settings ) {
        $supplier = $settings['supplier'];
        echo '<h2>Date furnizor</h2>';
        echo '<table class="form-table" role="presentation"><tbody>';
        self::text_row( 'FurnizorNume', 'supplier_name', $supplier['name'], 'regular-text' );
        self::text_row( 'FurnizorCIF', 'supplier_cif', $supplier['cif'], 'regular-text' );
        self::text_row( 'FurnizorNrRegCom', 'supplier_reg_com', $supplier['reg_com'], 'regular-text' );
        self::text_row( 'FurnizorCapital', 'supplier_capital', $supplier['capital'], 'regular-text' );
        self::text_row( 'FurnizorAdresa', 'supplier_address', $supplier['address'], 'large-text' );
        self::text_row( 'FurnizorBanca', 'supplier_bank', $supplier['bank'], 'regular-text' );
        self::text_row( 'FurnizorIBAN', 'supplier_iban', $supplier['iban'], 'regular-text' );
        self::text_row( 'FurnizorInformatiiSuplimentare', 'supplier_extra_info', $supplier['extra_info'], 'large-text' );
        echo '</tbody></table>';
    }

    private static function render_client_meta_settings( array $settings ) {
        $client_meta = $settings['client_meta'];
        echo '<h2>Mapari meta client fiscal</h2>';
        echo '<table class="form-table" role="presentation"><tbody>';
        self::text_row( 'ClientCIF meta key', 'client_meta_cif', $client_meta['cif'], 'regular-text' );
        self::text_row( 'ClientNrRegCom meta key', 'client_meta_reg_com', $client_meta['reg_com'], 'regular-text' );
        self::text_row( 'ClientBanca meta key', 'client_meta_bank', $client_meta['bank'], 'regular-text' );
        self::text_row( 'ClientIBAN meta key', 'client_meta_iban', $client_meta['iban'], 'regular-text' );
        echo '</tbody></table>';
    }

    private static function render_unavailable_invoice_options() {
        echo '<h2>Facturi anulate / stornate</h2>';
        echo '<table class="form-table" role="presentation"><tbody>';
        echo '<tr><th scope="row">Include facturile anulate</th><td>';
        echo '<label><input type="checkbox" disabled> Include facturile anulate</label>';
        echo '<p class="description">Disponibil ulterior doar cu WebToffee Pro / sursă fiscală pentru facturi anulate.</p>';
        echo '</td></tr>';
        echo '<tr><th scope="row">Include facturile stornate</th><td>';
        echo '<label><input type="checkbox" disabled> Include facturile stornate</label>';
        echo '<p class="description">Disponibil ulterior doar cu WebToffee Pro / modul credit note.</p>';
        echo '</td></tr>';
        echo '</tbody></table>';
    }

    private static function render_buttons() {
        echo '<p class="submit">';
        echo '<button type="submit" name="teinvit_saga_settings_action" value="save" class="button">Salveaza setarile</button> ';
        echo '<button type="submit" name="teinvit_saga_settings_action" value="preview" class="button">Preview diagnostic</button> ';
        echo '<button type="submit" formaction="' . esc_url( admin_url( 'admin-post.php' ) ) . '" name="teinvit_saga_format" value="xml" class="button button-primary">Download XML</button> ';
        echo '<button type="submit" formaction="' . esc_url( admin_url( 'admin-post.php' ) ) . '" name="teinvit_saga_format" value="csv" class="button button-primary">Download CSV</button>';
        echo '</p>';
    }

    private static function text_row( $label, $name, $value, $class = 'regular-text', $description = '' ) {
        echo '<tr><th scope="row"><label for="' . esc_attr( $name ) . '">' . esc_html( $label ) . '</label></th><td>';
        echo '<input type="text" id="' . esc_attr( $name ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '" class="' . esc_attr( $class ) . '">';
        if ( $description !== '' ) {
            echo '<p class="description">' . esc_html( $description ) . '</p>';
        }
        echo '</td></tr>';
    }

    private static function render_diagnostics_result( $title, array $result ) {
        $diagnostics = $result['diagnostics'];
        self::render_diagnostics_array(
            $title,
            [
                'exported' => $diagnostics->exported_count(),
                'skipped' => $diagnostics->skipped_count(),
                'items' => $diagnostics->all(),
            ]
        );
    }

    private static function render_diagnostics_array( $title, array $diagnostics ) {
        $items = is_array( $diagnostics['items'] ?? null ) ? $diagnostics['items'] : [];
        echo '<div class="notice notice-info"><p><strong>' . esc_html( $title ) . '</strong></p>';
        echo '<p>Documente exportate: <strong>' . esc_html( (string) (int) ( $diagnostics['exported'] ?? 0 ) ) . '</strong>. ';
        echo 'Documente sarite: <strong>' . esc_html( (string) (int) ( $diagnostics['skipped'] ?? 0 ) ) . '</strong>.</p>';

        if ( ! empty( $items ) ) {
            echo '<table class="widefat striped" style="margin:8px 0 12px;max-width:1100px;"><thead><tr>';
            echo '<th>Nivel</th><th>Cod</th><th>Mesaj</th><th>Context</th>';
            echo '</tr></thead><tbody>';
            foreach ( array_slice( $items, 0, 100 ) as $item ) {
                echo '<tr>';
                echo '<td>' . esc_html( (string) ( $item['level'] ?? '' ) ) . '</td>';
                echo '<td><code>' . esc_html( (string) ( $item['code'] ?? '' ) ) . '</code></td>';
                echo '<td>' . esc_html( (string) ( $item['message'] ?? '' ) ) . '</td>';
                echo '<td><code>' . esc_html( wp_json_encode( $item['context'] ?? [] ) ) . '</code></td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
            if ( count( $items ) > 100 ) {
                echo '<p>Diagnostic trunchiat in UI la primele 100 intrari.</p>';
            }
        }

        echo '</div>';
    }

    private static function render_inline_script() {
        echo '<script>
document.addEventListener("DOMContentLoaded", function () {
    var period = document.getElementById("teinvit-saga-period");
    var custom = document.querySelector(".teinvit-saga-custom-dates");
    function syncCustomDates() {
        if (!period || !custom) {
            return;
        }
        custom.style.display = period.value === "custom" ? "inline-block" : "none";
    }
    if (period) {
        period.addEventListener("change", syncCustomDates);
    }
    syncCustomDates();
});
</script>';
    }
}
