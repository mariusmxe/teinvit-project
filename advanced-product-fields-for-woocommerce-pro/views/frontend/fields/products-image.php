<?php
/** @var array $model */

use SW_WAPF_PRO\Includes\Classes\Html;
use \SW_WAPF_PRO\Includes\Classes\Util;

$cols           = isset($model['field']->options['items_per_row']) ? intval($model['field']->options['items_per_row']) : 3;
$cols_tablet    = isset($model['field']->options['items_per_row_tablet']) ? intval($model['field']->options['items_per_row_tablet']) : 3;
$cols_mobile    = isset($model['field']->options['items_per_row_mobile']) ? intval($model['field']->options['items_per_row_mobile']) : 3;
$label_outside  = ( $field->options[ 'label_pos' ] ?? '' ) === 'out';
$classes        = 'wapf-swatch wapf-swatch--image' . ( $label_outside ? '' : ' apf-pick-box' );
$img_width      = isset( $model['field']->options['item_width'] ) ? intval( $model['field']->options['item_width'] ) : 68;

if( ! empty( $model['data']['product_choices'] ) ) {

    echo '<div class="wapf-image-swatch-wrapper wapf-swatch-wrapper" style="--wapf-cols:auto-fill;--apf-col-width:' . $img_width . 'px">';
    echo '<input type="hidden" class="wapf-tf-h" data-fid="'.$model['field']->id.'" value="0" name="wapf[field_'.$model['field']->id.'][]" />';

    foreach ( $model['data']['product_choices'] as $option ) {

        $class_atts = Html::get_option_classes_and_attributes( $model['field'], $model['product'], $option, $model['default'], true, true, false, [] );
        $wrapper_attributes = Html::image_swatch_wrapper_attributes( $option, $model['field'] );

        if( empty( $class_atts['atts']['disabled'] ) && ! $option['product']->is_in_stock() ) {
            $class_atts['classes'][] = 'wapf-disabled';
            $class_atts['atts']['disabled'] = 'disabled';
            $class_atts['atts']['data-disabled'] = '1';
        }

        echo sprintf(
            '<div class="%s %s" %s><label aria-label="%s"><input type="checkbox" %s /><div %s>%s</div>%s</label></div>',
            $classes,
            join( ' ', $class_atts['classes'] ),
            Util::array_to_attributes( $wrapper_attributes ),
            esc_attr( $option['label'] ?? '' ),
            Util::array_to_attributes( $class_atts['atts'] ),
            $label_outside ? 'class="apf-pick-box"' : '',
            Html::get_swatch_image_html( $model['field'], $model['product'], $option ),
            Html::swatch_label( $model['field'], $option, $model['product'] )
        );

    }

    echo '</div>';

}
