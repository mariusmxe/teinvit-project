<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class TeInvit_Saga_Order_Source {
    public function get_orders( $export_type, array $range, TeInvit_Saga_Export_Diagnostics $diagnostics ) {
        if ( ! function_exists( 'wc_get_orders' ) ) {
            $diagnostics->add( 'error', 'woocommerce_inactive', 'WooCommerce nu este disponibil pentru Export SAGA.' );
            return [];
        }

        if ( $export_type === 'invoices' ) {
            return $this->get_invoice_candidate_orders( $diagnostics );
        }

        return $this->get_orders_by_order_date( $range, $diagnostics );
    }

    public function get_orders_by_order_date( array $range, TeInvit_Saga_Export_Diagnostics $diagnostics ) {
        if ( ! function_exists( 'wc_get_orders' ) ) {
            $diagnostics->add( 'error', 'woocommerce_inactive', 'WooCommerce nu este disponibil pentru Export SAGA.' );
            return [];
        }

        $args = [
            'type' => 'shop_order',
            'limit' => -1,
            'return' => 'objects',
            'orderby' => 'date',
            'order' => 'ASC',
            'status' => $this->order_statuses(),
            'date_created' => (int) $range['start_ts'] . '...' . (int) $range['end_ts'],
        ];

        $orders = wc_get_orders( $args );
        return is_array( $orders ) ? $orders : [];
    }

    private function get_invoice_candidate_orders( TeInvit_Saga_Export_Diagnostics $diagnostics ) {
        $args = [
            'type' => 'shop_order',
            'limit' => -1,
            'return' => 'objects',
            'orderby' => 'date',
            'order' => 'ASC',
            'status' => $this->order_statuses(),
            'meta_query' => [
                [
                    'key' => '_wf_invoice_date',
                    'compare' => 'EXISTS',
                ],
            ],
        ];

        $orders = wc_get_orders( $args );
        return is_array( $orders ) ? $orders : [];
    }

    private function order_statuses() {
        if ( function_exists( 'wc_get_order_statuses' ) ) {
            $statuses = array_keys( wc_get_order_statuses() );
            return ! empty( $statuses ) ? $statuses : 'any';
        }

        return 'any';
    }
}
