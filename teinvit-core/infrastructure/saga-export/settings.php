<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class TeInvit_Saga_Export_Settings {
    const OPTION_NAME = 'teinvit_saga_export_settings';

    public static function defaults() {
        return [
            'product_account' => '707',
            'discount_account' => '709',
            'fee_account' => '704',
            'shipping_account' => '704',
            'shipping_label' => 'TAXA TRANSPORT',
            'inventory_code' => '',
            'county_format' => 'code',
            'supplier' => [
                'name' => '',
                'cif' => '',
                'reg_com' => '',
                'capital' => '',
                'address' => '',
                'bank' => '',
                'iban' => '',
                'extra_info' => '',
            ],
            'client_meta' => [
                'cif' => '',
                'reg_com' => '',
                'bank' => '',
                'iban' => '',
            ],
        ];
    }

    public static function get() {
        $settings = get_option( self::OPTION_NAME, [] );
        if ( ! is_array( $settings ) ) {
            $settings = [];
        }

        return self::normalize( wp_parse_args( $settings, self::defaults() ) );
    }

    public static function from_request( $source ) {
        $source = is_array( $source ) ? $source : [];
        $settings = self::get();

        $settings['product_account'] = sanitize_text_field( wp_unslash( $source['product_account'] ?? $settings['product_account'] ) );
        $settings['discount_account'] = sanitize_text_field( wp_unslash( $source['discount_account'] ?? $settings['discount_account'] ) );
        $settings['fee_account'] = sanitize_text_field( wp_unslash( $source['fee_account'] ?? $settings['fee_account'] ) );
        $settings['shipping_account'] = sanitize_text_field( wp_unslash( $source['shipping_account'] ?? $settings['shipping_account'] ) );
        $settings['shipping_label'] = sanitize_text_field( wp_unslash( $source['shipping_label'] ?? $settings['shipping_label'] ) );
        $settings['inventory_code'] = sanitize_text_field( wp_unslash( $source['inventory_code'] ?? $settings['inventory_code'] ) );
        $settings['county_format'] = sanitize_key( wp_unslash( $source['county_format'] ?? $settings['county_format'] ) );

        foreach ( array_keys( self::defaults()['supplier'] ) as $key ) {
            $settings['supplier'][ $key ] = sanitize_text_field( wp_unslash( $source[ 'supplier_' . $key ] ?? ( $settings['supplier'][ $key ] ?? '' ) ) );
        }

        foreach ( array_keys( self::defaults()['client_meta'] ) as $key ) {
            $settings['client_meta'][ $key ] = sanitize_text_field( wp_unslash( $source[ 'client_meta_' . $key ] ?? ( $settings['client_meta'][ $key ] ?? '' ) ) );
        }

        return self::normalize( $settings );
    }

    public static function save( array $settings ) {
        $settings = self::normalize( $settings );
        if ( false === get_option( self::OPTION_NAME, false ) ) {
            add_option( self::OPTION_NAME, $settings, '', false );
            return;
        }

        update_option( self::OPTION_NAME, $settings, false );
    }

    public static function normalize( array $settings ) {
        $defaults = self::defaults();
        $settings = wp_parse_args( $settings, $defaults );
        $settings['supplier'] = wp_parse_args( is_array( $settings['supplier'] ?? null ) ? $settings['supplier'] : [], $defaults['supplier'] );
        $settings['client_meta'] = wp_parse_args( is_array( $settings['client_meta'] ?? null ) ? $settings['client_meta'] : [], $defaults['client_meta'] );

        if ( $settings['county_format'] !== 'code' ) {
            $settings['county_format'] = 'code';
        }

        return $settings;
    }
}
