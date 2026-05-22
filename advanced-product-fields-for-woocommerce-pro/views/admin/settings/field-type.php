<?php /* @var $model array */ ?>

<select rv-default="field.<?php echo $model['id']; ?>" rv-on-change="onChangeType" rv-value="field.<?php echo $model['id']; ?>">
    <?php
    $groups = \SW_WAPF_PRO\Includes\Classes\Enumerable::from($model['options'])->groupBy(function($x){
        return isset($x['group_label']) ? $x['group_label'] : '';
    })->toArray();

    foreach( $groups as $optgroup => $types ) {
        if( empty( $optgroup ) ) continue;
	    echo '<optgroup label="' . $optgroup . '">';
	    foreach( $types as $type ) {
            if( isset( $type['title'] ) ) // some field types only used on the frontend do not have a title, so we skip them.
		        echo '<option value="'.$type['id'].'">'.esc_html($type['title']).'</option>';
	    }
    }
    ?>
</select>
<div style="padding-top:10px;display: flex;justify-content: space-between">
    <?php
    foreach( $groups as $optgroup => $types ) {
        foreach( $types as $type ) {
            if( isset( $type['title'] ) )
                echo '<div style="padding:0;opacity:.75" rv-show="field.type | eq \''.$type['id'].'\'">'.esc_html($type['description']).'</div>';
        }
    }
    ?>
    <div>
        <a href="https://www.studiowombat.com/knowledge-base/all-field-types/?ref=wapf_admin" target="_blank">
            <?php _e('Learn about all field types','sw-wapf'); ?>
        </a>
    </div>
</div>