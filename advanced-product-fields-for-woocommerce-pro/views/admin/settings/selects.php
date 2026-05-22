<?php /* @var $model array */ ?>
<div style="display:flex;flex-flow:row;">
<?php

foreach( $model['lists'] as $list ) { ?>
    <div data-setting="<?php echo esc_attr( $list['id'] ?? '' ) ?>" style="flex:1;padding-right:10px">
        <div style="padding-bottom:5px">
            <?php echo $list['title'] ?>
        </div>
        <div>
            <select
                <?php echo isset($list['multiple']) && $list['multiple'] ? 'multiple="multiple"' : ''; ?>
                    rv-default="<?php echo $model['is_field_setting'] ? 'field' : 'settings'; ?>.<?php echo $list['id']; ?>"
                    data-default="<?php echo isset($list['default']) ? esc_attr($list['default']) : ''; ?>"
                    rv-on-change="onChange"
                    rv-value="<?php echo $model['is_field_setting'] ? 'field' : 'settings'; ?>.<?php echo $list['id']; ?>"
                <?php if(isset($list['select2']) && $list['select2']) { ?>
                    rv-select2-basic="<?php echo $model['is_field_setting'] ? 'field' : 'settings'; ?>.<?php echo $list['id']; ?>"
                <?php } ?>
                <?php if(isset($list['select2_source'])) { ?>
                    data-source="<?php echo esc_attr($list['select2_source']); ?>"
                <?php } ?>
            >
                <?php
                if(isset($list['options']))
                    foreach($list['options'] as $value => $label) {
                        if(is_array($label)) {
                            echo '<optgroup label="' . $value . '">';
                            foreach ($label as $v => $l) {
                                echo '<option value="'.$v.'">'.$l.'</option>';
                            }
                            echo '</optgroup>';
                        } else echo '<option value="'.$value.'">'.$label.'</option>';

                    }
                ?>
            </select>
        </div>
    </div>
<?php } ?>
</div>
<?php if(isset($model['note'])) { ?>
    <div style="padding-top:10px;">
        <?php echo wp_kses( $model['note'], ['b' => [], 'em' => [], 'i' => [],'strong' => []] ); ?>
    </div>
<?php } ?>