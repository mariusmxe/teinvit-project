<?php
/** @var array $model */

use SW_WAPF_PRO\Includes\Classes\Html;
use SW_WAPF_PRO\Includes\Classes\Util;

if( ! empty( $model['data']['product_choices'] ) ) {

    $qty_method = $model['field']->options['qty_method'];

    echo '<div class="wapf-checkboxes"><input type="hidden" class="wapf-tf-h" data-fid="'.$model['field']->id.'" value="0" name="wapf[field_'.$model['field']->id.'][]" />';

    foreach ( $model['data']['product_choices'] as $choice ) {
        
        $class_atts = Html::get_option_classes_and_attributes( $model['field'], $model['product'], $choice, $model['default'], $qty_method !== 'custom', false, $qty_method === 'custom', [ 'wapf-checkbox' ] );
        
        if( ! isset( $class_atts['atts']['disabled'] ) && ! $choice['product']->is_in_stock() ) {
            $class_atts['classes'][] = 'wapf-disabled';
            $class_atts['atts']['disabled'] = 'disabled';
            $class_atts['atts']['data-disabled'] = '1';
        }
        
        echo sprintf(
    '<div class="%s">
                <label class="wapf-input-label">
                    <input type="checkbox" %s /><span class="wapf-custom"></span>
                    <span class="wapf-label-text">%s</span>
                </label>
            </div>',
            join( ' ', $class_atts['classes'] ),
            Util::array_to_attributes( $class_atts['atts'] ),
            esc_html( $choice['label'] ) . ' ' . Html::product_pricing_hint( $choice['product'], $choice )
        );
        
    }

    echo '</div>';

}