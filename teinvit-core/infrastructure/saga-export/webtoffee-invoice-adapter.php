<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class TeInvit_Saga_WebToffee_Invoice_Adapter {
    const PLUGIN_FILE = 'print-invoices-packing-slip-labels-for-woocommerce/print-invoices-packing-slip-labels-for-woocommerce.php';

    public function is_active() {
        if ( ! function_exists( 'is_plugin_active' ) && defined( 'ABSPATH' ) ) {
            $plugin_file = ABSPATH . 'wp-admin/includes/plugin.php';
            if ( file_exists( $plugin_file ) ) {
                require_once $plugin_file;
            }
        }

        if ( function_exists( 'is_plugin_active' ) && is_plugin_active( self::PLUGIN_FILE ) ) {
            return true;
        }

        if ( function_exists( 'is_plugin_active_for_network' ) && is_plugin_active_for_network( self::PLUGIN_FILE ) ) {
            return true;
        }

        return defined( 'WF_PKLIST_VERSION' )
            || class_exists( 'Wf_Woocommerce_Packing_List', false )
            || class_exists( 'Wf_Woocommerce_Packing_List_Invoice', false );
    }

    public function get_invoice_data( WC_Order $order ) {
        $invoice_number = trim( (string) $order->get_meta( 'wf_invoice_number', true ) );
        $invoice_date_raw = $order->get_meta( '_wf_invoice_date', true );
        $invoice_timestamp = $this->parse_invoice_timestamp( $invoice_date_raw );

        return [
            'has_invoice_number' => $invoice_number !== '',
            'has_invoice_date' => $invoice_timestamp !== null,
            'invoice_number' => $invoice_number,
            'invoice_date_raw' => $invoice_date_raw,
            'invoice_timestamp' => $invoice_timestamp,
            'invoice_date' => $invoice_timestamp ? $this->format_date( $invoice_timestamp ) : '',
            'due_date' => '',
            'currency' => $order->get_currency(),
            'order_id' => $order->get_id(),
        ];
    }

    public function parse_invoice_timestamp( $value ) {
        if ( is_numeric( $value ) ) {
            $timestamp = (int) $value;
            if ( $timestamp > 20000000000 ) {
                $timestamp = (int) floor( $timestamp / 1000 );
            }
            return $timestamp > 0 ? $timestamp : null;
        }

        $value = trim( (string) $value );
        if ( $value === '' ) {
            return null;
        }

        $timezone = function_exists( 'wp_timezone' ) ? wp_timezone() : new DateTimeZone( 'UTC' );
        $formats = [ 'Y-m-d', 'Y-m-d H:i:s', 'd.m.Y', 'd.m.Y H:i:s' ];
        foreach ( $formats as $format ) {
            $date = DateTimeImmutable::createFromFormat( '!' . $format, $value, $timezone );
            $errors = DateTimeImmutable::getLastErrors();
            $is_valid = $date instanceof DateTimeImmutable
                && ( $errors === false || ( (int) $errors['warning_count'] === 0 && (int) $errors['error_count'] === 0 ) );
            if ( $is_valid ) {
                return $date->setTime( 0, 0, 0 )->getTimestamp();
            }
        }

        return null;
    }

    public function format_date( $timestamp ) {
        $timezone = function_exists( 'wp_timezone' ) ? wp_timezone() : new DateTimeZone( 'UTC' );
        $date = ( new DateTimeImmutable( '@' . (int) $timestamp ) )->setTimezone( $timezone );
        return $date->format( 'd.m.Y' );
    }
}
