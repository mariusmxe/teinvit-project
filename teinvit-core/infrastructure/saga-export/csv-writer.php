<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class TeInvit_Saga_CSV_Writer {
    public function write( $export_type, array $documents ) {
        $handle = fopen( 'php://temp', 'r+' );
        if ( ! $handle ) {
            return '';
        }

        if ( $export_type === 'invoices' ) {
            $this->write_invoices( $handle, $documents );
        } else {
            $this->write_orders( $handle, $documents );
        }

        rewind( $handle );
        $csv = stream_get_contents( $handle );
        fclose( $handle );

        return (string) $csv;
    }

    private function write_invoices( $handle, array $documents ) {
        $columns = [
            'FacturaNumar',
            'FacturaData',
            'ClientNume',
            'ClientCIF',
            'ClientAdresa',
            'ClientLocalitate',
            'ClientJudet',
            'LinieNrCrt',
            'Gestiune',
            'Descriere',
            'CodArticolFurnizor',
            'CodArticolClient',
            'UM',
            'Cantitate',
            'Pret',
            'Valoare',
            'ProcTVA',
            'TVA',
            'Cont',
            'TotalValoare',
            'TotalTVA',
            'Total',
        ];
        fputcsv( $handle, $columns );

        foreach ( $documents as $document ) {
            foreach ( $document['lines'] as $line ) {
                fputcsv( $handle, [
                    $document['number'],
                    $document['date'],
                    $document['client']['ClientNume'],
                    $document['client']['ClientCIF'],
                    $document['client']['ClientAdresa'],
                    $document['client']['ClientLocalitate'],
                    $document['client']['ClientJudet'],
                    $line['line_no'],
                    $line['inventory_code'],
                    $line['description'],
                    $line['supplier_code'],
                    $line['client_code'],
                    $line['um'],
                    $this->format_quantity( $line['quantity'] ),
                    $this->format_amount( $line['price'] ),
                    $this->format_amount( $line['value'] ),
                    $this->format_rate( $line['vat_rate'] ),
                    $this->format_amount( $line['vat'] ),
                    $line['account'],
                    $this->format_amount( $document['summary']['total_value'] ),
                    $this->format_amount( $document['summary']['total_vat'] ),
                    $this->format_amount( $document['summary']['total'] ),
                ] );
            }
        }
    }

    private function write_orders( $handle, array $documents ) {
        $columns = [
            'ComandaNumar',
            'ComandaData',
            'ClientNume',
            'ClientCIF',
            'ClientAdresa',
            'LinieNrCrt',
            'Tip',
            'Descriere',
            'CodArticolFurnizor',
            'CodArticolClient',
            'UM',
            'Cantitate',
            'Pret',
            'Valoare',
            'TVA',
            'TotalValoare',
            'TotalTVA',
            'Total',
        ];
        fputcsv( $handle, $columns );

        foreach ( $documents as $document ) {
            foreach ( $document['lines'] as $line ) {
                fputcsv( $handle, [
                    $document['number'],
                    $document['date'],
                    $document['client']['ClientNume'],
                    $document['client']['ClientCIF'],
                    $document['client']['ClientAdresa'],
                    $line['line_no'],
                    'Marfuri',
                    $line['description'],
                    $line['supplier_code'],
                    $line['client_code'],
                    $line['um'],
                    $this->format_quantity( $line['quantity'] ),
                    $this->format_amount( $line['price'] ),
                    $this->format_amount( $line['value'] ),
                    $this->format_amount( $line['vat'] ),
                    $this->format_amount( $document['summary']['total_value'] ),
                    $this->format_amount( $document['summary']['total_vat'] ),
                    $this->format_amount( $document['summary']['total'] ),
                ] );
            }
        }
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
