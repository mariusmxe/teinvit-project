<?php
/** @var array $model */

use SW_WAPF_PRO\Includes\Classes\Enumerable;
use SW_WAPF_PRO\Includes\Classes\Html;

if( ! empty( $model['data']['product_choices'] ) ) {
    
    echo '<select ' . $model[ 'field_attributes' ] . '>';

    if ( ! $model[ 'field' ]->required || ( $model[ 'field' ]->required && ! Enumerable::from( $model[ 'field' ]->options[ 'choices' ] )->any( function( $x ) {
        return isset( $x[ 'selected' ] ) && $x[ 'selected' ] === true;
    } ) ) ) {
        echo '<option value="">' . esc_html( $model[ 'data' ][ 'i18n_choose_option' ] ) . '</option>';
    }

    foreach ( $model['data']['product_choices'] as $choice ) {
        
        $attributes = Html::select_option_attributes( $choice, $model['field'], $model['product'], $model['is_edit'], $model['default'] );

        if( ! isset( $attributes['disabled'] ) && ! $choice['product']->is_in_stock() ) {
            $class_atts['atts']['disabled'] = '';
        }
        
        echo sprintf(
            '<option %s>%s</option>',
            Enumerable::from( $attributes )->join( function( $value, $key ) { return $value ?  ( $key . '="' . esc_attr($value) .'"' ) : $key; },' '),
            esc_html( $choice['label'] ) . ' ' . Html::product_pricing_hint( $choice['product'], $choice, true )
        );
        
    }

    echo '</select>';
    
}