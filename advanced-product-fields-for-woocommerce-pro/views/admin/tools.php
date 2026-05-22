<?php
 /** @var $model [] */

use SW_WAPF_PRO\Includes\Classes\Enumerable;
use SW_WAPF_PRO\Includes\Classes\Config;

$import_class = 'wapf-' . uniqid();
 $export_class = 'wapf-' . uniqid();
?>

<div rv-controller="ToolsCtrl" class="wapf-top-options">
	<a class="wapf-import" href="#" data-modal-class="<?php echo $import_class;?>"><?php _e('Import','sw-wapf'); ?></a>
	<a class="wapf-export" style="margin-left:15px" href="#" data-modal-class="<?php echo $export_class;?>"><?php _e('Export','sw-wapf'); ?></a>
</div>

<div class="wapf_modal_overlay <?php echo $export_class; ?>">
	<div class="wapf_modal">
        <div style="margin-bottom: 15px;">
		    <a class="wapf_close" href="#" onclick="event.preventDefault();jQuery('.<?php echo $export_class ?>').hide();">&times;</a>
        </div>
		<div>
            <p>
                <?php
                    if($model['product_mode'])
                        _e('You can use the code below to import into another product. If you are importing this on a different website, all images need re-linking as they are not copied along.','sw-wapf');
                    else
                        _e('You can use the code below to import into another field group (or product). If you are importing this on a different website, all images need re-linking as they are not copied along.','sw-wapf');
                ?>
            </p>
			<textarea class="wapf-export-ta" readonly style="width: 100%; height:200px;"></textarea>
		</div>
	</div>
</div>

<div class="wapf_modal_overlay <?php echo $import_class; ?>">
    <div class="wapf_modal">
        <div style="margin-bottom: 15px;">
            <a class="wapf_close" href="#" onclick="event.preventDefault();jQuery('.<?php echo $import_class ?>').hide();">&times;</a>
        </div>
        <div style="overflow: hidden">
            <p>
                <?php
                if($model['product_mode'])
                    _e('Paste exported code below to import it into this product. This will overwrite any current configuration.','sw-wapf');
                else
                    _e('Paste exported code below to import it into this field group. This will overwrite any current configuration.','sw-wapf');
                ?>
            </p>
            <textarea class="wapf-import-ta" style="width: 100%; height:200px;"></textarea>
        </div>
        <div style="margin-top:15px;display: flex;align-items: center">
            <span><?php _e('Import mode:','sw-wapf');?></span>
            <select class="wapf-import-mode" style="margin:0 10px;max-width:225px;">
                <option selected value="replace"><?php _e('Replace with imported','sw-wapf');?></option>
                <option value="append"><?php _e('Append to existing','sw-wapf');?></option>
            </select>
            <button class="button btn-wapf-import"><?php _e('Import','sw-wapf');?></button>
            <span class="wapf-import-success" style="display: none;color:green;padding-left:10px;"><?php _e('Import done!','sw-wapf'); ?></span>
            <span class="wapf-import-error" style="display: none;color:red;padding-left:10px;"><?php _e('An error occured. Did you paste all the code?','sw-wapf'); ?></span>
        </div>
    </div>
</div>

<div class="wapf_modal_overlay wapf-bulk-options">
    <div class="wapf_modal">
        <div style="margin-bottom: 15px;">
            <a class="wapf_close" href="#" onclick="event.preventDefault();jQuery('.wapf-bulk-options').hide();">&times;</a>
        </div>
        <div style="overflow: hidden">
            <p>
            <?php
                _e('You can import multiple options at once by placing each option on a separate line below.','sw-wapf');
            ?>
            </p>
            <textarea id="wapf-bulkopts" style="width: 100%; height:200px;"></textarea>
        </div>
        <div style="margin-top:15px;display: flex;align-items: center">
            <button class="button" id="btn-bulkopts"><?php _e('Import','sw-wapf');?></button>
            <button class="button" style="display: none" id="btn-bulkopts-done" onclick="event.preventDefault();jQuery('.wapf-bulk-options').hide();"><?php _e('Done','sw-wapf');?></button>
            <span id="bulkopts-msg" style="display: none;color:green;padding-left:10px;" data-msg="<?php _e('{x} out of {y} items imported','sw-wapf'); ?>"></span>
        </div>
    </div>
</div>

<div class="wapf_modal_overlay" id="wapf-fb">
    <div class="wapf_modal modal--large">
        <div style="margin-bottom: 15px;">
            <a class="wapf_close" href="#" onclick="event.preventDefault();jQuery('#wapf-fb').hide();">&times;</a>
        </div>
        <div>
            <p>
                <?php
                _e('Visually build your formula below or check the function glossary to find out more about functions.','sw-wapf');
                ?>
            </p>
            <div id="wapf-fb-wrapper">

                <div>
                    <input rv-value="formula" type="text" />
                </div>

                <div class="wapf-formula-check" style="padding-top:10px;display: none;"></div>

                <?php \SW_WAPF_PRO\Includes\Classes\Html::partial('admin/formula-keyboard', [ 'as' => 'popup' ]) ?>

            </div>
        </div>
        <div style="margin-top:15px;display: flex;align-items: center; justify-content: space-between">
            <div>
                <button class="button" onclick="event.preventDefault();jQuery('#wapf-fb').hide();"><?php _e('Done','sw-wapf') ?></button>
            </div>
            <div>
                <ul>
                    <li style="display: inline-block"><a href="#" onclick="javascript:event.preventDefault();jQuery('#wapf-funcrefs').show();"><?php _e('Functions glossary', 'sw-wapf') ?></a></li>
                    <li style="display: inline-block;">&bull;</li>
                    <li style="display: inline-block;"><a href="https://www.studiowombat.com/knowledge-base/formulas-and-variables-explained/?ref=wapf-admin" target="_blank"><?php _e('Learn about formulas','sw-wapf'); ?></a></li>
                </ul>
            </div>
        </div>
    </div>
</div>

<div class="wapf_modal_overlay" id="wapf-funcrefs">
    <div class="wapf_modal">
        <a class="wapf_close" href="#" onclick="event.preventDefault();jQuery('#wapf-funcrefs').hide();">&times;</a>
        <h3><?php _e('Functions glossary', 'sw-wapf') ?></h3>
        <div>
            <p>
                <?php
                _e('Choose a function from the list to learn more about it.','sw-wapf');
                ?>
            </p>
            <select id="wapf-select-function">
                <?php
                $definitions = Config::get_function_definitions();
                $groups = Enumerable::from( $definitions )->groupBy( function($x) {
                    return isset($x['category']) ? $x['category'] : '';
                } )->toArray();

                $first = true;
                foreach($groups as $optgroup => $defs) {
                    echo '<optgroup label="' . $optgroup . '">';
                    foreach($defs as $def){
                        echo '<option value="' . $def['name'] . '" ' . ( $first ? 'selected' : '' ) . '>' . $def['name'] . '</option>';
                        $first = false;
                    }
                }
                ?>
            </select>
            <div>
                <?php
                $first = true;
                foreach( $definitions as $def) {
                    echo '<div style="display:' . ( $first ? 'block' : 'none' ) . '" class="wapf-def" data-func="' . $def['name'] . '"><p>' . $def['description'] . '</p>';
                    $examples = [];
                    $params = [];
                    if( !empty($def['parameters']) ) {
                        foreach ($def['parameters'] as $par) {
                            $params[] = sprintf("<code>%s</code>: %s %s.", $par['name'], $par['description'], $par['required'] ? __('Required', 'sw-wapf') : __('Optional', 'sw-wapf'));
                        }
                    }
                    foreach ($def['examples'] as $ex) {
                        $examples[] = sprintf(__("<code>%s</code> returns <code>%s</code>%s", 'sw-wapf'), $ex['example'], $ex['solution'], empty($ex['description']) ? '.' : ' ' . __('because', 'sw-wapf') . ' ' .$ex['description']);
                    }
                    if( ! empty( $params ) ) {
                        echo '<p><strong>' . __('Parameters', 'sw-wapf') . ':</strong></p><ol>';
                        foreach ($params as $p) {
                            echo '<li>' . $p . '</li>';
                        }
                        echo '</ol>';
                    }
                    if( ! empty( $examples ) ) {
                        echo '<p><strong>' . __('Examples', 'sw-wapf') . ':</strong></p><ul>';
                        foreach ($examples as $ex) {
                            echo '<li>' . $ex . '</li>';
                        }
                        echo '</ul>';
                    }
                    echo '</div>';
                    $first = false;
                }
                ?>
            </div>
        </div>
    </div>
    <script>document.getElementById('wapf-select-function').addEventListener('change', function() { jQuery('.wapf-def').hide(); jQuery('.wapf-def[data-func="'+this.value+'"]').show(); } )</script>
</div>

