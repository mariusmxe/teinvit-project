<?php
/** @var array $model */

use SW_WAPF_PRO\Includes\Classes\Helper;
use SW_WAPF_PRO\Includes\Classes\Html;
use \SW_WAPF_PRO\Includes\Classes\Util;

if(!empty($model['field']->options['choices'])) {

	echo '<div class="wapf-swatch-wrapper">';
	echo '<input type="hidden" class="wapf-tf-h" data-fid="'.$model['field']->id.'" value="0" name="wapf[field_'.$model['field']->id.']" />';
	foreach ($model['field']->options['choices'] as $option) {

        $class_atts = Html::get_option_classes_and_attributes( $model['field'], $model['product'], $option, $model['default'], false, true, false, [] );
        
		echo sprintf(
			'<div class="wapf-swatch wapf-swatch--text wapf-single-select %s"><label><span>%s</span><input type="radio" autocomplete="off" %s /></label></div>',
            join( ' ', $class_atts['classes'] ),
			wp_kses( $option['label'], Helper::$allowed_html_minimal ) . ' ' . Html::frontend_option_pricing_hint( $option, $model['field'], $model['product'] ),
            Util::array_to_attributes( $class_atts['atts'] )
		);

	}

	echo '</div>';

}