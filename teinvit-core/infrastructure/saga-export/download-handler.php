<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class TeInvit_Saga_Download_Handler {
    const ACTION = 'teinvit_saga_export_download';
    const NONCE_ACTION = 'teinvit_saga_export';

    public static function register() {
        add_action( 'admin_post_' . self::ACTION, [ __CLASS__, 'handle' ] );
    }

    public static function handle() {
        if ( ! current_user_can( self::capability() ) ) {
            wp_die( esc_html__( 'Nu ai permisiunea necesara pentru Export SAGA.', 'teinvit' ) );
        }

        check_admin_referer( self::NONCE_ACTION, 'teinvit_saga_nonce' );

        $format = sanitize_key( wp_unslash( $_POST['teinvit_saga_format'] ?? 'xml' ) );
        if ( ! in_array( $format, [ 'xml', 'csv' ], true ) ) {
            $format = 'xml';
        }

        $result = self::build_export_from_request( $_POST );
        self::remember_diagnostics( $result );

        if ( $result['diagnostics']->has_errors() ) {
            wp_safe_redirect( self::admin_url() );
            exit;
        }

        if ( $format === 'xml' ) {
            if ( ! class_exists( 'XMLWriter' ) ) {
                wp_die( esc_html__( 'Extensia PHP XMLWriter nu este disponibila.', 'teinvit' ) );
            }
            $writer = new TeInvit_Saga_XML_Writer();
            $content = $writer->write( $result['export_type'], $result['documents'] );
            $extension = 'xml';
            $content_type = 'application/xml; charset=UTF-8';
        } else {
            $writer = new TeInvit_Saga_CSV_Writer();
            $content = $writer->write( $result['export_type'], $result['documents'] );
            $extension = 'csv';
            $content_type = 'text/csv; charset=UTF-8';
        }

        $filename = self::filename( $result['export_type'], $extension );

        nocache_headers();
        header( 'Content-Type: ' . $content_type );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
        header( 'Content-Length: ' . strlen( $content ) );
        echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        exit;
    }

    public static function build_export_from_request( $source ) {
        $source = is_array( $source ) ? $source : [];
        $export_type = sanitize_key( wp_unslash( $source['export_type'] ?? 'orders' ) );
        if ( ! in_array( $export_type, [ 'orders', 'invoices' ], true ) ) {
            $export_type = 'orders';
        }

        $settings = TeInvit_Saga_Export_Settings::from_request( $source );
        $range = TeInvit_Saga_Export_Date_Range::from_request( $source );
        $diagnostics = new TeInvit_Saga_Export_Diagnostics();
        $invoice_adapter = new TeInvit_Saga_WebToffee_Invoice_Adapter();
        $eligibility = new TeInvit_Saga_Product_Eligibility();
        $order_source = new TeInvit_Saga_Order_Source();
        $builder = new TeInvit_Saga_Document_Builder( $settings, $diagnostics, $eligibility, $invoice_adapter );

        if ( $export_type === 'invoices' && ! $invoice_adapter->is_active() ) {
            $diagnostics->add( 'error', 'webtoffee_inactive', 'WebToffee PDF Invoices nu este activ. Exportul de Facturi este blocat.' );
            return [
                'export_type' => $export_type,
                'settings' => $settings,
                'range' => $range,
                'documents' => [],
                'diagnostics' => $diagnostics,
            ];
        }

        if ( $export_type === 'invoices' ) {
            $order_date_candidates = $order_source->get_orders_by_order_date( $range, $diagnostics );
            $builder->collect_missing_invoice_diagnostics( $order_date_candidates );
        }

        $orders = $order_source->get_orders( $export_type, $range, $diagnostics );
        $documents = $builder->build( $export_type, $orders, $range );

        return [
            'export_type' => $export_type,
            'settings' => $settings,
            'range' => $range,
            'documents' => $documents,
            'diagnostics' => $diagnostics,
        ];
    }

    public static function consume_recent_diagnostics() {
        $key = self::diagnostic_transient_key();
        $diagnostics = get_transient( $key );
        if ( $diagnostics !== false ) {
            delete_transient( $key );
        }
        return is_array( $diagnostics ) ? $diagnostics : null;
    }

    public static function admin_url() {
        return admin_url( 'admin.php?page=teinvit-saga-export' );
    }

    public static function capability() {
        return function_exists( 'teinvit_admin_capability' ) ? teinvit_admin_capability() : 'manage_woocommerce';
    }

    private static function remember_diagnostics( array $result ) {
        $diagnostics = $result['diagnostics'];
        set_transient(
            self::diagnostic_transient_key(),
            [
                'exported' => $diagnostics->exported_count(),
                'skipped' => $diagnostics->skipped_count(),
                'items' => $diagnostics->all(),
            ],
            MINUTE_IN_SECONDS * 5
        );
    }

    private static function diagnostic_transient_key() {
        return 'teinvit_saga_export_diag_' . get_current_user_id();
    }

    private static function filename( $export_type, $extension ) {
        $type = $export_type === 'invoices' ? 'facturi' : 'comenzi';
        return 'teinvit-saga-' . $type . '-' . gmdate( 'Ymd-His' ) . '.' . $extension;
    }
}
