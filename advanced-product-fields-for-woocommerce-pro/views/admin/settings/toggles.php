<?php
/* @var $model array */
$is_first = true;
$classes = [ 'wapf-toggle', 'wapf-toggle--small' ];
?>

<?php foreach( $model['options'] as $option ) { ?>
    <div style="width: 100%; display: flex;align-items: center;<?php echo $is_first ? '' : 'margin-top: 8px;' ?>">
        <div class="<?php echo join( ' ', $classes ) ?>" rv-unique-checkbox>
            <input
                rv-checked="<?php echo $model['is_field_setting'] ? 'field' : 'settings'; ?>.<?php echo $option['id'] ?>"
                rv-default="<?php echo $model['is_field_setting'] ? 'field' : 'settings'; ?>.<?php echo $option['id'] ?>"
                data-default="true"
                rv-on-change="onChange"
                type="checkbox" 
            />
            <label class="wapf-toggle-label">
                <span class="wapf-toggle-inner" data-true="" data-false=""></span>
                <span class="wapf-toggle-switch"></span>
            </label>
        </div>
        <span style="padding-left:15px">
            <?php echo $option['label']; ?>
        </span>
    </div>
    <?php $is_first = false; } ?>