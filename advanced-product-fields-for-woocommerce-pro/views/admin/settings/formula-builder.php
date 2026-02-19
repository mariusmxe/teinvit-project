<?php
/* @var $model array */
?>

<div rv-formulabuilder="<?php echo $model['is_field_setting'] ? 'field' : 'settings'; ?>.<?php echo $model['id']; ?>">

    <div>
        <input
            rv-on-change="onChange"
            rv-value="<?php echo $model['is_field_setting'] ? 'field' : 'settings'; ?>.<?php echo $model['id']; ?>"
            type="text"
            <?php if(isset($model['placeholder'])) echo 'placeholder="'.$model['placeholder'].'"'; ?>
        />
    </div>

    <div class="wapf-formula-check" style="padding-top:10px;display: none;"></div>

    <?php \SW_WAPF_PRO\Includes\Classes\Html::partial('admin/formula-keyboard') ?>

    <div style="text-align: right">
        <ul>
            <li style="display: inline-block"><a href="#" onclick="javascript:event.preventDefault();jQuery('#wapf-funcrefs').show();"><?php _e('Functions glossary', 'sw-wapf') ?></a></li>
            <li style="display: inline-block;">&bull;</li>
            <li style="display: inline-block;"><a href="https://www.studiowombat.com/knowledge-base/formulas-and-variables-explained/?ref=wapf-admin" target="_blank"><?php _e('Learn about formulas','sw-wapf'); ?></a></li>
        </ul>
    </div>

</div>