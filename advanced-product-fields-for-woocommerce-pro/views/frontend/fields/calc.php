<?php
/** @var array $model */

use SW_WAPF_PRO\Includes\Classes\Util;

$formula = empty( $model['field']->options['formula'] ) ? 0 : esc_attr( $model['field']->options['formula'] );
$result_text = empty( $model['field']->options['result_text'] ) ? '{result}' : esc_attr( $model['field']->options['result_text'] );
$type = isset( $model['field']->options['calc_type'] ) && $model['field']->options['calc_type'] === 'cost' ? 'cost' : 'default';
$result_format = $type === 'default' ? ( empty( $model['field']->options['result_format'] ) ? 'number' : 'none' ) : '';

$attributes = [
    'class'                 => 'wapf-input input-' . $model['field']->id,
    'data-field-id '        => $model['field']->id,
    'data-calc-type'        => $type,
    'data-wapf-price'       => $formula,
    'data-wapf-pricetype'   => 'fx'
];

$attributes = Util::array_to_attributes( $attributes );
// Put value="idle" so that calculateOptionsTotal would calculate this field (doesn't calc empty fields).
?>

<div class="wapf-calc-wrapper">
    <span class="wapf-calc-text" data-type="<?php echo esc_attr( $type ) ?>" data-format="<?php echo esc_attr( $result_format ) ?>" data-txt="<?php echo esc_attr( $result_text ) ?>" data-formula="<?php echo esc_attr( $formula ) ?>"></span>
    <input data-is-calc="1" type="hidden" <?php echo $attributes ?> value="idle" name="wapf[field_<?php echo esc_attr( $model['field']->id ) ?>]" />
    <input type="hidden" value="" class="calc-raw" data-field-id="<?php echo esc_attr( $model['field']->id ) ?>" name="wapf[field_<?php echo esc_attr( $model['field']->id ) ?>_raw]" />
</div>