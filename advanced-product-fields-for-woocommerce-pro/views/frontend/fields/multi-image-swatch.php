<?php
/** @var array $model */

use SW_WAPF_PRO\Includes\Classes\Html;
use \SW_WAPF_PRO\Includes\Classes\Util;

$layout = $model[ 'field' ]->options[ 'grid_layout' ] ?? 'flexible'; //setting didn't exist prior to 3.0 and then it came down to "flexible" even tho default now is "fixed".
$css = '';

if( $layout === 'flexible' ) {
    $cols = isset( $model[ 'field' ]->options[ 'items_per_row' ] ) ? intval( $model[ 'field' ]->options[ 'items_per_row' ] ) : 3;
    $cols_tablet = isset( $model[ 'field' ]->options[ 'items_per_row_tablet' ] ) ? intval( $model[ 'field' ]->options[ 'items_per_row_tablet' ] ) : 3;
    $cols_mobile = isset( $model[ 'field' ]->options[ 'items_per_row_mobile' ] ) ? intval( $model[ 'field' ]->options[ 'items_per_row_mobile' ] ) : 3;
    $css = '--wapf-cols:' . $cols . ';--wapf-cols-t:' . $cols_tablet . ';--wapf-cols-m:' . $cols_mobile;
} else {
    $img_width = isset( $model['field']->options['item_width'] ) ? intval( $model['field']->options['item_width'] ) : 68;
    $css = '--wapf-cols:auto-fill;--apf-col-width:' . $img_width .'px';
}

$label_outside  = ( $field->options[ 'label_pos' ] ?? '' ) === 'out';
$classes        = 'wapf-swatch wapf-swatch--image' . ( $label_outside ? '' : ' apf-pick-box' );

if(!empty($model['field']->options['choices'])) {

    echo '<div class="wapf-image-swatch-wrapper wapf-swatch-wrapper" style="' . $css . '">';
    echo '<input type="hidden" class="wapf-tf-h" data-fid="'.$model['field']->id.'" value="0" name="wapf[field_'.$model['field']->id.'][]" />';
    
    foreach ($model['field']->options['choices'] as $option) {

        $class_atts = Html::get_option_classes_and_attributes( $model['field'], $model['product'], $option, $model['default'], true, true, false, [] );
        $wrapper_attributes = Html::image_swatch_wrapper_attributes( $option, $model['field'] );
        
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