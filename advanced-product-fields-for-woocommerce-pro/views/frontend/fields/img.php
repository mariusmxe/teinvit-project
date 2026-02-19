<?php

/** @var array $model */

if( ! empty( $model['field']->options['attachment'] ) ) {

    echo wp_get_attachment_image( $model['field']->options['attachment'], 'full', false, $model['raw_field_attributes'] );

} else {

    echo '<img src="' . ( empty(  $model['field']->options['image'] ) ? '' :  $model['field']->options['image'] ) . '" ' . $model['field_attributes'] . ' />';

}