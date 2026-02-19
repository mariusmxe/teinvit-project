<div <?php echo $model['field_attributes']; ?>>
    <?php
        echo empty($field->options['p_content']) ?
            '' :
            do_shortcode( wp_kses( $field->options['p_content'], array_merge( \SW_WAPF_PRO\Includes\Classes\Html::$minimal_allowed_html_element, ['img' => ['src' => [],'target' => [], 'class' => [], 'alt' => [], 'style' => [], 'id' => [] ] ] ) ) );

    ?>
</div>