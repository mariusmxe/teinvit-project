<?php
/** @var array $model */

use SW_WAPF_PRO\Includes\Classes\Html;
use \SW_WAPF_PRO\Includes\Classes\Util;

if(!empty($model['field']->options['choices'])) {

    echo '<div class="wapf-checkboxes">';

    foreach ($model['field']->options['choices'] as $option) {

        $class_atts = Html::get_option_classes_and_attributes( $model['field'], $model['product'], $option, $model['default'], true, false, false, [ 'wapf-checkbox' ] );
        // TODO, the hidden input should be once at the top? No need to repeat this?
        echo sprintf(
            '<div class="%s"><label class="wapf-input-label"><input type="hidden" class="wapf-tf-h" data-fid="'.$model['field']->id.'" value="0" name="wapf[field_'.$model['field']->id.'][]" />
<input type="checkbox" %s /><span class="wapf-custom"></span><span class="wapf-label-text">%s</span></label></div>',
            join( ' ', $class_atts['classes'] ),
            Util::array_to_attributes( $class_atts['atts'] ),
            esc_html( $option['label'] ) . ' ' . Html::frontend_option_pricing_hint( $option, $model['field'], $model['product'] )
        );

    }

    echo '</div>';

}