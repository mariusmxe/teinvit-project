<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class TeInvit_Saga_XML_Writer {
    public function write( $export_type, array $documents ) {
        if ( ! class_exists( 'XMLWriter' ) ) {
            return '';
        }

        $xml = new XMLWriter();
        $xml->openMemory();
        $xml->setIndent( true );
        $xml->setIndentString( "\t" );

        if ( $export_type === 'invoices' ) {
            $this->write_invoices( $xml, $documents );
        } else {
            $this->write_orders( $xml, $documents );
        }

        return $xml->outputMemory();
    }

    private function write_invoices( XMLWriter $xml, array $documents ) {
        $xml->startElement( 'Facturi' );
        foreach ( $documents as $document ) {
            $xml->startElement( 'Factura' );
            $this->write_invoice_header( $xml, $document );
            $this->write_invoice_details( $xml, $document );
            $this->write_summary( $xml, $document );
            $this->write_observations( $xml );
            $xml->endElement();
        }
        $xml->endElement();
    }

    private function write_orders( XMLWriter $xml, array $documents ) {
        $xml->startElement( 'Comanda' );
        foreach ( $documents as $document ) {
            $this->write_order_header( $xml, $document );
            $this->write_order_details( $xml, $document );
            $this->write_summary( $xml, $document );
        }
        $xml->endElement();
    }

    private function write_invoice_header( XMLWriter $xml, array $document ) {
        $supplier = $document['supplier'];
        $client = $document['client'];

        $xml->startElement( 'Antet' );
        $this->element( $xml, 'FurnizorNume', $supplier['FurnizorNume'] );
        $this->element( $xml, 'FurnizorCIF', $supplier['FurnizorCIF'] );
        $this->element( $xml, 'FurnizorNrRegCom', $supplier['FurnizorNrRegCom'] );
        $this->element( $xml, 'FurnizorCapital', $supplier['FurnizorCapital'] );
        $this->element( $xml, 'FurnizorAdresa', $supplier['FurnizorAdresa'] );
        $this->element( $xml, 'FurnizorBanca', $supplier['FurnizorBanca'] );
        $this->element( $xml, 'FurnizorIBAN', $supplier['FurnizorIBAN'] );
        $this->element( $xml, 'FurnizorInformatiiSuplimentare', $supplier['FurnizorInformatiiSuplimentare'] );
        $this->element( $xml, 'ClientNume', $client['ClientNume'] );
        $this->element( $xml, 'ClientInformatiiSuplimentare', $client['ClientInformatiiSuplimentare'] );
        $this->element( $xml, 'ClientCIF', $client['ClientCIF'] );
        $this->element( $xml, 'ClientNrRegCom', $client['ClientNrRegCom'] );
        $this->element( $xml, 'ClientAdresa', $client['ClientAdresa'] );
        $this->element( $xml, 'ClientLocalitate', $client['ClientLocalitate'] );
        $this->element( $xml, 'ClientJudet', $client['ClientJudet'] );
        $this->element( $xml, 'ClientBanca', $client['ClientBanca'] );
        $this->element( $xml, 'ClientIBAN', $client['ClientIBAN'] );
        $this->element( $xml, 'FacturaNumar', $document['number'] );
        $this->element( $xml, 'FacturaData', $document['date'] );
        $this->element( $xml, 'FacturaScadenta', $document['due_date'] );
        $this->element( $xml, 'FacturaTaxareInversa', 'Nu' );
        $this->element( $xml, 'FacturaTVAIncasare', 'Nu' );
        $this->element( $xml, 'FacturaInformatiiSuplimentare', '' );
        $this->element( $xml, 'FacturaMoneda', $document['currency'] );
        $this->element( $xml, 'FacturaCotaTVA', $this->format_rate( $document['vat_rate'] ) );
        $this->element( $xml, 'FacturaGreutate', $document['weight'] );
        $xml->endElement();
    }

    private function write_order_header( XMLWriter $xml, array $document ) {
        $supplier = $document['supplier'];
        $client = $document['client'];

        $xml->startElement( 'Antet' );
        $this->element( $xml, 'FurnizorNume', $supplier['FurnizorNume'] );
        $this->element( $xml, 'FurnizorCIF', $supplier['FurnizorCIF'] );
        $this->element( $xml, 'FurnizorNrRegCom', $supplier['FurnizorNrRegCom'] );
        $this->element( $xml, 'FurnizorCapital', $supplier['FurnizorCapital'] );
        $this->element( $xml, 'FurnizorAdresa', $supplier['FurnizorAdresa'] );
        $this->element( $xml, 'FurnizorBanca', $supplier['FurnizorBanca'] );
        $this->element( $xml, 'FurnizorIBAN', $supplier['FurnizorIBAN'] );
        $this->element( $xml, 'FurnizorInformatiiSuplimentare', $supplier['FurnizorInformatiiSuplimentare'] );
        $this->element( $xml, 'ClientNume', $client['ClientNume'] );
        $this->element( $xml, 'ClientInformatiiSuplimentare', $client['ClientInformatiiSuplimentare'] );
        $this->element( $xml, 'ClientCIF', $client['ClientCIF'] );
        $this->element( $xml, 'ClientNrRegCom', $client['ClientNrRegCom'] );
        $this->element( $xml, 'ClientAdresa', $client['ClientAdresa'] );
        $this->element( $xml, 'ClientBanca', $client['ClientBanca'] );
        $this->element( $xml, 'ClientIBAN', $client['ClientIBAN'] );
        $this->element( $xml, 'ComandaNumar', $document['number'] );
        $this->element( $xml, 'ComandaData', $document['date'] );
        $this->element( $xml, 'ComandaInformatiiSuplimentare', '' );
        $this->element( $xml, 'ComandaCotaTVA', $this->format_rate( $document['vat_rate'] ) );
        $this->element( $xml, 'ComandaGreutate', $document['weight'] );
        $xml->endElement();
    }

    private function write_invoice_details( XMLWriter $xml, array $document ) {
        $xml->startElement( 'Detalii' );
        $xml->startElement( 'Continut' );
        foreach ( $document['lines'] as $line ) {
            $xml->startElement( 'Linie' );
            $this->element( $xml, 'LinieNrCrt', $line['line_no'] );
            $this->element( $xml, 'Gestiune', $line['inventory_code'] );
            $this->element( $xml, 'Descriere', $line['description'] );
            $this->element( $xml, 'CodArticolFurnizor', $line['supplier_code'] );
            $this->element( $xml, 'CodArticolClient', $line['client_code'] );
            $this->element( $xml, 'CodBare', $line['barcode'] );
            $this->element( $xml, 'InformatiiSuplimentare', $line['extra_info'] );
            $this->element( $xml, 'UM', $line['um'] );
            $this->element( $xml, 'Cantitate', $this->format_quantity( $line['quantity'] ) );
            $this->element( $xml, 'Pret', $this->format_amount( $line['price'] ) );
            $this->element( $xml, 'Valoare', $this->format_amount( $line['value'] ) );
            $this->element( $xml, 'ProcTVA', $this->format_rate( $line['vat_rate'] ) );
            $this->element( $xml, 'TVA', $this->format_amount( $line['vat'] ) );
            $this->element( $xml, 'Cont', $line['account'] );
            $xml->endElement();
        }
        $xml->endElement();
        $xml->endElement();
    }

    private function write_order_details( XMLWriter $xml, array $document ) {
        $xml->startElement( 'Detalii' );
        $xml->startElement( 'Continut' );
        foreach ( $document['lines'] as $line ) {
            $xml->startElement( 'Linie' );
            $this->element( $xml, 'LinieNrCrt', $line['line_no'] );
            $this->element( $xml, 'Tip', 'Marfuri' );
            $this->element( $xml, 'Descriere', $line['description'] );
            $this->element( $xml, 'CodArticolFurnizor', $line['supplier_code'] );
            $this->element( $xml, 'CodArticolClient', $line['client_code'] );
            $this->element( $xml, 'CodBare', $line['barcode'] );
            $this->element( $xml, 'InformatiiSuplimentare', $line['extra_info'] );
            $this->element( $xml, 'UM', $line['um'] );
            $this->element( $xml, 'Cantitate', $this->format_quantity( $line['quantity'] ) );
            $this->element( $xml, 'Pret', $this->format_amount( $line['price'] ) );
            $this->element( $xml, 'Valoare', $this->format_amount( $line['value'] ) );
            $this->element( $xml, 'TVA', $this->format_amount( $line['vat'] ) );
            $xml->endElement();
        }
        $xml->endElement();
        $xml->endElement();
    }

    private function write_summary( XMLWriter $xml, array $document ) {
        $xml->startElement( 'Sumar' );
        $this->element( $xml, 'TotalValoare', $this->format_amount( $document['summary']['total_value'] ) );
        $this->element( $xml, 'TotalTVA', $this->format_amount( $document['summary']['total_vat'] ) );
        $this->element( $xml, 'Total', $this->format_amount( $document['summary']['total'] ) );
        $xml->endElement();
    }

    private function write_observations( XMLWriter $xml ) {
        $xml->startElement( 'Observatii' );
        $this->element( $xml, 'txtObservatii', '' );
        $this->element( $xml, 'SoldClient', '' );
        $xml->endElement();
    }

    private function element( XMLWriter $xml, $name, $value ) {
        $xml->writeElement( $name, (string) $value );
    }

    private function format_amount( $value ) {
        return number_format( round( (float) $value, 2 ), 2, '.', '' );
    }

    private function format_quantity( $value ) {
        $value = (float) $value;
        if ( floor( $value ) === $value ) {
            return (string) (int) $value;
        }

        $formatted = number_format( round( $value, 4 ), 4, '.', '' );
        return rtrim( rtrim( $formatted, '0' ), '.' );
    }

    private function format_rate( $value ) {
        $formatted = number_format( round( (float) $value, 2 ), 2, '.', '' );
        return rtrim( rtrim( $formatted, '0' ), '.' );
    }
}
