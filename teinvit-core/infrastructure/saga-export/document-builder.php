<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class TeInvit_Saga_Document_Builder {
    private $settings;
    private $diagnostics;
    private $eligibility;
    private $invoice_adapter;

    public function __construct(
        array $settings,
        TeInvit_Saga_Export_Diagnostics $diagnostics,
        TeInvit_Saga_Product_Eligibility $eligibility,
        TeInvit_Saga_WebToffee_Invoice_Adapter $invoice_adapter
    ) {
        $this->settings = TeInvit_Saga_Export_Settings::normalize( $settings );
        $this->diagnostics = $diagnostics;
        $this->eligibility = $eligibility;
        $this->invoice_adapter = $invoice_adapter;
    }

    public function build( $export_type, array $orders, array $range ) {
        $documents = [];

        if ( $export_type === 'invoices' && ! $this->invoice_adapter->is_active() ) {
            $this->diagnostics->add( 'error', 'webtoffee_inactive', 'WebToffee PDF Invoices nu este activ. Exportul de Facturi este blocat.' );
            return [];
        }

        foreach ( $orders as $order ) {
            if ( ! $order instanceof WC_Order ) {
                continue;
            }

            $document = $export_type === 'invoices'
                ? $this->build_invoice_document( $order, $range )
                : $this->build_order_document( $order );

            if ( $document ) {
                $documents[] = $document;
                $this->diagnostics->exported_document();
                if ( $export_type === 'invoices' ) {
                    $this->add_invoice_export_diagnostic( $document );
                }
            }
        }

        return $documents;
    }

    public function collect_missing_invoice_diagnostics( array $orders ) {
        if ( ! $this->invoice_adapter->is_active() ) {
            return;
        }

        foreach ( $orders as $order ) {
            if ( ! $order instanceof WC_Order || ! $this->eligibility->order_has_eligible_items( $order ) ) {
                continue;
            }

            $invoice = $this->invoice_adapter->get_invoice_data( $order );
            if ( $invoice['has_invoice_date'] ) {
                continue;
            }

            $missing = [];
            if ( ! $invoice['has_invoice_number'] ) {
                $missing[] = 'wf_invoice_number';
            }
            if ( ! $invoice['has_invoice_date'] ) {
                $missing[] = '_wf_invoice_date';
            }

            $this->diagnostics->skipped_document();
            $this->diagnostics->add(
                'warning',
                'invoice_not_generated',
                'Comanda TeInvit este eligibila, dar nu are factura WebToffee generata complet.',
                [
                    'order_id' => $order->get_id(),
                    'missing' => implode( ', ', $missing ),
                ]
            );
        }
    }

    private function build_invoice_document( WC_Order $order, array $range ) {
        $invoice = $this->invoice_adapter->get_invoice_data( $order );

        if ( ! $invoice['has_invoice_number'] || ! $invoice['has_invoice_date'] ) {
            if ( $this->eligibility->order_has_eligible_items( $order ) ) {
                $missing = [];
                if ( ! $invoice['has_invoice_number'] ) {
                    $missing[] = 'wf_invoice_number';
                }
                if ( ! $invoice['has_invoice_date'] ) {
                    $missing[] = '_wf_invoice_date';
                }

                $this->diagnostics->skipped_document();
                $this->diagnostics->add(
                    'warning',
                    'invoice_missing_required_meta',
                    'Factura WebToffee este incompleta si a fost exclusa din export.',
                    [
                        'order_id' => $order->get_id(),
                        'missing' => implode( ', ', $missing ),
                    ]
                );
            }
            return null;
        }

        if ( $invoice['invoice_timestamp'] < (int) $range['start_ts'] || $invoice['invoice_timestamp'] > (int) $range['end_ts'] ) {
            return null;
        }

        $lines = $this->build_lines( $order, 'invoices' );
        if ( empty( $lines ) ) {
            return null;
        }

        return $this->build_document_array(
            'invoices',
            $order,
            $lines,
            [
                'number' => $invoice['invoice_number'],
                'date' => $invoice['invoice_date'],
                'due_date' => '',
                'currency' => $invoice['currency'],
            ]
        );
    }

    private function build_order_document( WC_Order $order ) {
        $date_created = $order->get_date_created();
        if ( ! $date_created ) {
            $this->diagnostics->skipped_document();
            $this->diagnostics->add( 'warning', 'order_missing_date', 'Comanda a fost exclusa: nu are data WooCommerce.', [ 'order_id' => $order->get_id() ] );
            return null;
        }

        $lines = $this->build_lines( $order, 'orders' );
        if ( empty( $lines ) ) {
            return null;
        }

        return $this->build_document_array(
            'orders',
            $order,
            $lines,
            [
                'number' => $order->get_order_number(),
                'date' => $date_created->date_i18n( 'd.m.Y' ),
                'due_date' => '',
                'currency' => $order->get_currency(),
            ]
        );
    }

    private function build_document_array( $type, WC_Order $order, array $lines, array $document_meta ) {
        $lines = $this->normalize_monetary_lines( $lines );
        $summary = $this->summarize_lines( $lines );

        return [
            'type' => $type,
            'order_id' => $order->get_id(),
            'number' => (string) $document_meta['number'],
            'date' => (string) $document_meta['date'],
            'due_date' => (string) $document_meta['due_date'],
            'currency' => (string) $document_meta['currency'],
            'supplier' => $this->supplier_data(),
            'client' => $this->client_data( $order ),
            'vat_rate' => $this->document_vat_rate( $lines ),
            'weight' => '0',
            'lines' => $lines,
            'summary' => $summary,
        ];
    }

    private function build_lines( WC_Order $order, $export_type ) {
        $lines = [];
        $excluded = [];
        $discount_net = 0.0;
        $discount_tax = 0.0;
        $discount_rate = null;

        foreach ( $order->get_items( 'line_item' ) as $item_id => $item ) {
            $context = $this->eligibility->get_item_context( $item );
            if ( ! $context ) {
                $excluded[] = [
                    'item_id' => (int) $item_id,
                    'name' => method_exists( $item, 'get_name' ) ? (string) $item->get_name() : '',
                ];
                continue;
            }

            $line = $this->product_line_from_item( $order, $item );
            $lines[] = $line;

            $item_discount_net = max( 0, (float) $item->get_subtotal() - (float) $item->get_total() );
            $item_discount_tax = max( 0, (float) $item->get_subtotal_tax() - (float) $item->get_total_tax() );
            $discount_net += $item_discount_net;
            $discount_tax += $item_discount_tax;
            if ( $discount_rate === null && ( $item_discount_net > 0 || $item_discount_tax > 0 ) ) {
                $discount_rate = $line['vat_rate'];
            }
        }

        if ( empty( $lines ) ) {
            return [];
        }

        foreach ( $excluded as $excluded_item ) {
            $this->diagnostics->add(
                'info',
                'non_teinvit_item_excluded',
                'Produs non-TeInvit exclus din export.',
                [
                    'order_id' => $order->get_id(),
                    'item_id' => $excluded_item['item_id'],
                    'name' => $excluded_item['name'],
                ]
            );
        }

        if ( $this->amount_is_non_zero( $discount_net ) || $this->amount_is_non_zero( $discount_tax ) ) {
            $lines[] = $this->discount_line( $order, $discount_net, $discount_tax, $discount_rate );
        }

        foreach ( $order->get_items( 'fee' ) as $item ) {
            $line = $this->fee_line_from_item( $order, $item );
            if ( $line ) {
                $lines[] = $line;
            }
        }

        foreach ( $order->get_items( 'shipping' ) as $item ) {
            $line = $this->shipping_line_from_item( $order, $item );
            if ( $line ) {
                $lines[] = $line;
            }
        }

        $line_no = 1;
        foreach ( $lines as &$line ) {
            $line['line_no'] = $line_no++;
            if ( $export_type === 'orders' ) {
                $line['type'] = 'Marfuri';
            }
        }
        unset( $line );

        return $lines;
    }

    private function product_line_from_item( WC_Order $order, $item ) {
        $qty = (float) $item->get_quantity();
        $net = (float) $item->get_subtotal();
        $tax = (float) $item->get_subtotal_tax();
        $gross = $net + $tax;
        $price = $qty !== 0.0 ? $gross / $qty : $gross;

        return [
            'kind' => 'product',
            'description' => (string) $item->get_name(),
            'supplier_code' => $this->sku_or_fallback( $item ),
            'client_code' => $this->sku_or_fallback( $item ),
            'barcode' => '',
            'extra_info' => '',
            'um' => 'BUC',
            'quantity' => $qty,
            'price' => $price,
            'value' => $net,
            'vat_rate' => $this->item_vat_rate( $order, $item, 'subtotal', $net, $tax ),
            'vat' => $tax,
            'account' => (string) $this->settings['product_account'],
            'inventory_code' => (string) $this->settings['inventory_code'],
        ];
    }

    private function discount_line( WC_Order $order, $discount_net, $discount_tax, $discount_rate ) {
        $codes = method_exists( $order, 'get_coupon_codes' ) ? $order->get_coupon_codes() : [];
        $description = ! empty( $codes ) ? 'Discount: ' . implode( ', ', array_map( 'strval', $codes ) ) : 'Discount comercial';
        $rate = $discount_rate !== null ? $discount_rate : $this->fallback_vat_rate( -abs( $discount_net ), -abs( $discount_tax ) );

        return [
            'kind' => 'discount',
            'description' => $description,
            'supplier_code' => '',
            'client_code' => '',
            'barcode' => '',
            'extra_info' => '',
            'um' => 'BUC',
            'quantity' => 0,
            'price' => abs( $discount_net + $discount_tax ),
            'value' => -abs( $discount_net ),
            'vat_rate' => $rate,
            'vat' => -abs( $discount_tax ),
            'account' => (string) $this->settings['discount_account'],
            'inventory_code' => (string) $this->settings['inventory_code'],
        ];
    }

    private function fee_line_from_item( WC_Order $order, $item ) {
        $net = method_exists( $item, 'get_total' ) ? (float) $item->get_total() : 0.0;
        $tax = method_exists( $item, 'get_total_tax' ) ? (float) $item->get_total_tax() : 0.0;
        if ( ! $this->amount_is_non_zero( $net ) && ! $this->amount_is_non_zero( $tax ) ) {
            return null;
        }

        $description = method_exists( $item, 'get_name' ) ? trim( (string) $item->get_name() ) : '';
        if ( $description === '' ) {
            $description = 'TAXA PROCESARE';
        }

        return [
            'kind' => 'fee',
            'description' => $description,
            'supplier_code' => '',
            'client_code' => '',
            'barcode' => '',
            'extra_info' => '',
            'um' => 'BUC',
            'quantity' => 1,
            'price' => $net + $tax,
            'value' => $net,
            'vat_rate' => $this->item_vat_rate( $order, $item, 'total', $net, $tax ),
            'vat' => $tax,
            'account' => (string) $this->settings['fee_account'],
            'inventory_code' => (string) $this->settings['inventory_code'],
        ];
    }

    private function shipping_line_from_item( WC_Order $order, $item ) {
        $net = method_exists( $item, 'get_total' ) ? (float) $item->get_total() : 0.0;
        $tax = method_exists( $item, 'get_total_tax' ) ? (float) $item->get_total_tax() : 0.0;
        if ( ! $this->amount_is_non_zero( $net ) && ! $this->amount_is_non_zero( $tax ) ) {
            return null;
        }

        return [
            'kind' => 'shipping',
            'description' => (string) $this->settings['shipping_label'],
            'supplier_code' => '',
            'client_code' => '',
            'barcode' => '',
            'extra_info' => '',
            'um' => 'BUC',
            'quantity' => 1,
            'price' => $net + $tax,
            'value' => $net,
            'vat_rate' => $this->item_vat_rate( $order, $item, 'total', $net, $tax ),
            'vat' => $tax,
            'account' => (string) $this->settings['shipping_account'],
            'inventory_code' => (string) $this->settings['inventory_code'],
        ];
    }

    private function summarize_lines( array $lines ) {
        $net_cents = 0;
        $tax_cents = 0;
        foreach ( $lines as $line ) {
            $net_cents += $this->money_to_cents( $line['value'] );
            $tax_cents += $this->money_to_cents( $line['vat'] );
        }

        return [
            'total_value' => $net_cents / 100,
            'total_vat' => $tax_cents / 100,
            'total' => ( $net_cents + $tax_cents ) / 100,
        ];
    }

    private function normalize_monetary_lines( array $lines ) {
        if ( empty( $lines ) ) {
            return $lines;
        }

        $target_value = 0.0;
        $target_vat = 0.0;
        foreach ( $lines as $line ) {
            $target_value += (float) $line['value'];
            $target_vat += (float) $line['vat'];
        }
        $target_value_cents = $this->money_to_cents( $target_value );
        $target_vat_cents = $this->money_to_cents( $target_vat );

        $rounded_value_cents = 0;
        $rounded_vat_cents = 0;
        foreach ( $lines as &$line ) {
            $line['value'] = $this->money_to_cents( $line['value'] ) / 100;
            $line['vat'] = $this->money_to_cents( $line['vat'] ) / 100;
            $line['price'] = $this->money_to_cents( $line['price'] ) / 100;
            $rounded_value_cents += $this->money_to_cents( $line['value'] );
            $rounded_vat_cents += $this->money_to_cents( $line['vat'] );
        }
        unset( $line );

        $adjust_index = $this->last_rounding_adjustment_index( $lines );
        if ( $adjust_index !== null ) {
            $value_diff_cents = $target_value_cents - $rounded_value_cents;
            $vat_diff_cents = $target_vat_cents - $rounded_vat_cents;

            if ( $value_diff_cents !== 0 || $vat_diff_cents !== 0 ) {
                $lines[ $adjust_index ]['value'] = ( $this->money_to_cents( $lines[ $adjust_index ]['value'] ) + $value_diff_cents ) / 100;
                $lines[ $adjust_index ]['vat'] = ( $this->money_to_cents( $lines[ $adjust_index ]['vat'] ) + $vat_diff_cents ) / 100;
            }
        }

        foreach ( $lines as &$line ) {
            $line['price'] = $this->line_price_from_rounded_totals( $line );
        }
        unset( $line );

        return $lines;
    }

    private function last_rounding_adjustment_index( array $lines ) {
        for ( $i = count( $lines ) - 1; $i >= 0; $i-- ) {
            if ( isset( $lines[ $i ] ) ) {
                return $i;
            }
        }

        return null;
    }

    private function line_price_from_rounded_totals( array $line ) {
        $total = (float) $line['value'] + (float) $line['vat'];
        if ( ( $line['kind'] ?? '' ) === 'discount' ) {
            return abs( $total );
        }

        $quantity = (float) ( $line['quantity'] ?? 0 );
        if ( $quantity !== 0.0 ) {
            return $total / $quantity;
        }

        return $total;
    }

    private function money_to_cents( $amount ) {
        return (int) round( (float) $amount * 100 );
    }

    private function add_invoice_export_diagnostic( array $document ) {
        $line_totals = $this->summarize_lines( $document['lines'] );
        $summary = $document['summary'];
        $diff_value = $this->money_to_cents( $line_totals['total_value'] ) - $this->money_to_cents( $summary['total_value'] );
        $diff_vat = $this->money_to_cents( $line_totals['total_vat'] ) - $this->money_to_cents( $summary['total_vat'] );
        $diff_total = $this->money_to_cents( $line_totals['total'] ) - $this->money_to_cents( $summary['total'] );

        $this->diagnostics->add(
            'info',
            'invoice_exported_totals',
            'Factura exportata cu totaluri verificate.',
            [
                'FacturaNumar' => $document['number'],
                'FacturaData' => $document['date'],
                'FacturaScadenta' => $document['due_date'],
                'TotalValoare' => number_format( (float) $summary['total_value'], 2, '.', '' ),
                'TotalTVA' => number_format( (float) $summary['total_vat'], 2, '.', '' ),
                'Total' => number_format( (float) $summary['total'], 2, '.', '' ),
                'LiniiValoare' => number_format( (float) $line_totals['total_value'], 2, '.', '' ),
                'LiniiTVA' => number_format( (float) $line_totals['total_vat'], 2, '.', '' ),
                'LiniiTotal' => number_format( (float) $line_totals['total'], 2, '.', '' ),
                'DiferentaValoare' => number_format( $diff_value / 100, 2, '.', '' ),
                'DiferentaTVA' => number_format( $diff_vat / 100, 2, '.', '' ),
                'DiferentaTotal' => number_format( $diff_total / 100, 2, '.', '' ),
            ]
        );
    }

    private function supplier_data() {
        $supplier = is_array( $this->settings['supplier'] ?? null ) ? $this->settings['supplier'] : [];

        return [
            'FurnizorNume' => (string) ( $supplier['name'] ?? '' ),
            'FurnizorCIF' => (string) ( $supplier['cif'] ?? '' ),
            'FurnizorNrRegCom' => (string) ( $supplier['reg_com'] ?? '' ),
            'FurnizorCapital' => (string) ( $supplier['capital'] ?? '' ),
            'FurnizorAdresa' => (string) ( $supplier['address'] ?? '' ),
            'FurnizorBanca' => (string) ( $supplier['bank'] ?? '' ),
            'FurnizorIBAN' => (string) ( $supplier['iban'] ?? '' ),
            'FurnizorInformatiiSuplimentare' => (string) ( $supplier['extra_info'] ?? '' ),
        ];
    }

    private function client_data( WC_Order $order ) {
        $company = trim( (string) $order->get_billing_company() );
        $name = $company !== '' ? $company : trim( (string) $order->get_billing_first_name() . ' ' . (string) $order->get_billing_last_name() );

        return [
            'ClientNume' => $name,
            'ClientInformatiiSuplimentare' => '',
            'ClientCIF' => $this->client_meta_value( $order, 'cif' ),
            'ClientNrRegCom' => $this->client_meta_value( $order, 'reg_com' ),
            'ClientAdresa' => $this->billing_address( $order ),
            'ClientLocalitate' => (string) $order->get_billing_city(),
            'ClientJudet' => $this->billing_county_code( $order ),
            'ClientBanca' => $this->client_meta_value( $order, 'bank' ),
            'ClientIBAN' => $this->client_meta_value( $order, 'iban' ),
        ];
    }

    private function client_meta_value( WC_Order $order, $key ) {
        $meta_key = (string) ( $this->settings['client_meta'][ $key ] ?? '' );
        if ( $meta_key === '' ) {
            return '';
        }

        $value = $order->get_meta( $meta_key, true );
        if ( is_array( $value ) || is_object( $value ) ) {
            return '';
        }

        return trim( (string) $value );
    }

    private function billing_address( WC_Order $order ) {
        $parts = [
            $order->get_billing_address_1(),
            $order->get_billing_address_2(),
            $order->get_billing_postcode(),
        ];

        $parts = array_filter( array_map( 'trim', array_map( 'strval', $parts ) ) );
        return implode( ' ', $parts );
    }

    private function billing_county_code( WC_Order $order ) {
        $state = trim( (string) $order->get_billing_state() );
        if ( $state === '' ) {
            return '';
        }

        $country = strtoupper( (string) $order->get_billing_country() );
        if ( $country !== 'RO' ) {
            return strtoupper( $state );
        }

        $states = [];
        if ( function_exists( 'WC' ) && WC() && isset( WC()->countries ) ) {
            $states = WC()->countries->get_states( 'RO' );
        }

        $upper_state = strtoupper( remove_accents( $state ) );
        foreach ( (array) $states as $code => $name ) {
            if ( strtoupper( (string) $code ) === $upper_state ) {
                return strtoupper( (string) $code );
            }
            if ( strtoupper( remove_accents( (string) $name ) ) === $upper_state ) {
                return strtoupper( (string) $code );
            }
        }

        return strtoupper( $state );
    }

    private function sku_or_fallback( $item ) {
        $product = method_exists( $item, 'get_product' ) ? $item->get_product() : null;
        if ( $product && method_exists( $product, 'get_sku' ) ) {
            $sku = trim( (string) $product->get_sku() );
            if ( $sku !== '' ) {
                return $sku;
            }
        }

        $variation_id = method_exists( $item, 'get_variation_id' ) ? (int) $item->get_variation_id() : 0;
        $product_id = method_exists( $item, 'get_product_id' ) ? (int) $item->get_product_id() : 0;
        $fallback_id = $variation_id > 0 ? $variation_id : $product_id;
        if ( $fallback_id > 0 ) {
            return (string) $fallback_id;
        }

        if ( $product && method_exists( $product, 'get_slug' ) ) {
            return (string) $product->get_slug();
        }

        return '';
    }

    private function item_vat_rate( WC_Order $order, $item, $tax_key, $net, $tax ) {
        $taxes = method_exists( $item, 'get_taxes' ) ? $item->get_taxes() : [];
        $rate_map = $this->order_tax_rate_map( $order );
        $tax_amounts = is_array( $taxes ) && isset( $taxes[ $tax_key ] ) && is_array( $taxes[ $tax_key ] ) ? $taxes[ $tax_key ] : [];

        foreach ( $tax_amounts as $rate_id => $amount ) {
            if ( ! $this->amount_is_non_zero( (float) $amount ) ) {
                continue;
            }
            $rate_id = (int) $rate_id;
            if ( isset( $rate_map[ $rate_id ] ) ) {
                return (float) $rate_map[ $rate_id ];
            }
        }

        return $this->fallback_vat_rate( $net, $tax );
    }

    private function order_tax_rate_map( WC_Order $order ) {
        $rates = [];
        foreach ( $order->get_items( 'tax' ) as $tax_item ) {
            if ( ! is_object( $tax_item ) || ! method_exists( $tax_item, 'get_rate_id' ) ) {
                continue;
            }
            $rate_id = (int) $tax_item->get_rate_id();
            $rate_percent = method_exists( $tax_item, 'get_rate_percent' ) ? (float) $tax_item->get_rate_percent() : null;
            if ( $rate_id > 0 && $rate_percent !== null ) {
                $rates[ $rate_id ] = $rate_percent;
            }
        }
        return $rates;
    }

    private function fallback_vat_rate( $net, $tax ) {
        $net = (float) $net;
        $tax = (float) $tax;
        if ( ! $this->amount_is_non_zero( $net ) || ! $this->amount_is_non_zero( $tax ) ) {
            return 0.0;
        }

        return abs( $tax / $net * 100 );
    }

    private function document_vat_rate( array $lines ) {
        foreach ( $lines as $line ) {
            $rate = (float) ( $line['vat_rate'] ?? 0 );
            if ( $rate > 0 ) {
                return $rate;
            }
        }
        return 0.0;
    }

    private function amount_is_non_zero( $amount ) {
        return abs( (float) $amount ) > 0.00001;
    }
}
