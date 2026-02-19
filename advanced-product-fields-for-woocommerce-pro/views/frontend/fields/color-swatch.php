<?php
/** @var array $model */

use SW_WAPF_PRO\Includes\Classes\Html;
use \SW_WAPF_PRO\Includes\Classes\Util;

if(!empty($model['field']->options['choices'])) {

    echo '<div class="wapf-swatch-wrapper">';
    echo '<input type="hidden" class="wapf-tf-h" data-fid="'.$model['field']->id.'" value="0" name="wapf[field_'.$model['field']->id.']" />';

    foreach ($model['field']->options['choices'] as $option) {

        $class_atts = Html::get_option_classes_and_attributes( $model['field'], $model['product'], $option, $model['default'], false, true, false, [] );
        $size = intval($model['field']->options['size']);

        echo sprintf(
            '<div class="wapf-swatch wapf-swatch--color wapf-single-select %s" data-dir="t"><label aria-label="%s"><div style="%sbackground-color: %s;width:%spx;height:%spx" class="wapf-color wapf--%s"></div><input type="radio" autocomplete="off" %s />%s</label></div>',
            join( ' ', $class_atts['classes'] ),
            esc_attr( $option['label'] ?? '' ),
            empty( $model['field']->options['border'] ) ? '' : ('color: ' . esc_attr($model['field']->options['border']) .';' ), // Versions older than 1.5.0 had a setting to set the "selection border color".
            $option['color'],
            $size,
            $size,
            esc_attr( $model['field']->options['layout'] ),
            Util::array_to_attributes( $class_atts['atts'] ),
            Html::swatch_label( $model['field'], $option, $model['product'], 'tooltip' )
        );

    }

    echo '</div>';

}