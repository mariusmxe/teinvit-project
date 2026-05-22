<?php /* @var $model array */ ?>

<div style="display:flex;flex-flow:row;">

    <?php foreach( $model['numbers'] as $number ) { ?>
    <div data-setting="<?php echo esc_attr( $number['id'] ?? '' ) ?>" style="flex:1;padding-right:10px">
        <div style="padding-bottom:5px">
            <?php echo $number['title'] ?>
        </div>
        <?php if( ! empty( $number['postfix'] ) ) { ?>
            <div class="wapf-input-with-append">
        <?php } ?>
        <div>
            <input
                rv-on-keyup="onChange" rv-on-change="onChange"
                <?php if(isset($number['min'])) echo ' min="'.$number['min'].'" '; ?>
                <?php if(isset($model['$number'])) echo ' max="'.$number['max'].'" '; ?>
                rv-default="<?php echo $model['is_field_setting'] ? 'field' : 'settings'; ?>.<?php echo $number['id']; ?>" data-default="<?php echo isset($number['default']) ? esc_attr($number['default']) : ''; ?>"
                rv-value="<?php echo $model['is_field_setting'] ? 'field' : 'settings'; ?>.<?php echo $number['id']; ?>"
                type="number"
                step="any"
                placeholder="<?php echo empty( $number['placeholder'] ) ? '' : esc_attr( $number['placeholder'] ); ?>"
            />
        </div>
        <?php if( ! empty( $number['postfix'] ) ) { ?>
        <div class="wapf-input-append"><?php echo $number['postfix']; ?></div></div>
        <?php } ?>
    </div>
    <?php } ?>
    <?php if( isset( $model['empty'] ) ) for($i = 0; $i < $model['empty']; $i++ ) echo '<div style="flex:1;padding-right:10px"></div>'; ?>
</div>

<?php if(isset($model['note'])) { ?>
    <div style="padding-top:10px;">
        <?php echo wp_kses( $model['note'], ['b' => [], 'em' => [], 'i' => [],'strong' => []] ); ?>
    </div>
<?php } ?>