<?php
/** @var array $model */
$use_plusminus = isset( $model['field']->options['display'] ) && $model['field']->options['display'] === 'plus_min';
$classes = ( $use_plusminus ? 'apf-plusmin' : '' );
?>

<div class="<?php echo $classes ?>">
    
    <?php if( $use_plusminus ) { ?>
        <button type="button" tabindex="-1" aria-label="<?php _e( 'Reduce', 'sw-wapf' ) ?>" class="button apf-minus">
            <svg xmlns="http://www.w3.org/2000/svg" width="1rem" height="1rem" viewBox="0 0 24 24"><path d="M19 13H5v-2h14v2Z"/></svg>
        </button>
    <?php } ?>
    
    <input type="number" value="<?php echo $model['default'][0]; ?>" <?php echo $model['field_attributes']; ?> />

    <?php if( $use_plusminus ) { ?>
        <button type="button" tabindex="-1" aria-label="<?php _e( 'Increase', 'sw-wapf' ) ?>" class="button apf-plus">
            <svg xmlns="http://www.w3.org/2000/svg" width="1rem" height="1rem" viewBox="0 0 24 24"><path d="M11 4h2v7h7v2h-7v7h-2v-7H4v-2h7V4z"/></svg>
        </button>
    <?php } ?>
    
</div>
