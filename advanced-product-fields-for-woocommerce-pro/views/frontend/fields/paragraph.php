<?php
// this file is only here to gracefully take over the free version "paragraph" field
// Fixed in free 1.5.7 so can be deleted a year from then,
?>
<div <?php echo $model['field_attributes']; ?>>
    <?php
    echo empty($field->options['p_content']) ?
        '' :
        do_shortcode( wp_kses( $field->options['p_content'], array_merge( \SW_WAPF_PRO\Includes\Classes\Html::$minimal_allowed_html_element, ['img' => ['src' => [], 'class' => [], 'style' => [], 'id' => [] ] ] ) ) );

    ?>
</div>