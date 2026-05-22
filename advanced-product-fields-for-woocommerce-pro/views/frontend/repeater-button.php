<?php
$has_add_text =  !empty( $model['field']->clone['add']);
$has_del_text =  !empty( $model['field']->clone['del']);
$field = $model['field']->id;
$max_repetitions = isset( $model['field']->clone['max']) ? intval( $model['field']->clone['max'] ) : 10000;
?>

<div class="wapf-cloner cloner-<?php echo $field ?>">
    <button id="add_clone_<?php echo $field ?>" href="#" class="wapf-add-clone button" data-for="<?php echo $field ?>" data-edit-cart="<?php echo \SW_WAPF_PRO\Includes\Classes\Util::to_html_attribute_string($model['edit_cart_clones'] ) ?>">
        <?php if( empty( $model['field']->clone['add' ]) ) { ?>
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 384 512" style="height: 1em" fill="currentColor"><path d="M368 224H224V80c0-8.84-7.16-16-16-16h-32c-8.84 0-16 7.16-16 16v144H16c-8.84 0-16 7.16-16 16v32c0 8.84 7.16 16 16 16h144v144c0 8.84 7.16 16 16 16h32c8.84 0 16-7.16 16-16V288h144c8.84 0 16-7.16 16-16v-32c0-8.84-7.16-16-16-16z"/></svg>
        <?php } else echo esc_html( $model['field']->clone['add'] ); ?>
    </button>
    <button style="display: none" href="#" id="del_clone_<?php echo $field ?>" class="wapf-del-clone button" data-for="<?php echo $field ?>">
        <?php if( empty( $model['field']->clone['del' ]) ) { ?>
            <svg fill="currentColor" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 320 512" style="height: 1em;"><path d="M207.6 256l107.72-107.72c6.23-6.23 6.23-16.34 0-22.58l-25.03-25.03c-6.23-6.23-16.34-6.23-22.58 0L160 208.4 52.28 100.68c-6.23-6.23-16.34-6.23-22.58 0L4.68 125.7c-6.23 6.23-6.23 16.34 0 22.58L112.4 256 4.68 363.72c-6.23 6.23-6.23 16.34 0 22.58l25.03 25.03c6.23 6.23 16.34 6.23 22.58 0L160 303.6l107.72 107.72c6.23 6.23 16.34 6.23 22.58 0l25.03-25.03c6.23-6.23 6.23-16.34 0-22.58L207.6 256z"/></svg>
        <?php } else echo esc_html( $model['field']->clone['del'] ); ?>
    </button>
    <input type="hidden" id="field_<?php echo $field ?>_qty" name="wapf[field_<?php echo $field ?>_qty]" value="0" />
</div>
<script>

    document.addEventListener( 'wapf:init', function(e) {
        
        var $parent = jQuery( e.detail.wrapper );

        var $element = $parent.find('.field-<?php echo $field ?>');
        var isSection = $element.hasClass('wapf-section');
        var $cloner = $parent.find('.cloner-<?php echo $field ?>');
        var $qty = $parent.find('#field_<?php echo $field ?>_qty');
        var l = function(){return ($element.data('dupe') || []).length;};
        var $add = jQuery('#add_clone_<?php echo $field ?>');
        var $del = jQuery('#del_clone_<?php echo $field ?>');
        var after = function() {
            var q = l();
            $qty.val(q);
            $del[q > 0 ? 'show':'hide']();
            $add[q >= <?php echo $max_repetitions ?> ? 'hide':'show']();
            WAPF.Pricing.calculateAll($parent);
        };
        var add = function(values) {
            var $clone = WAPF.Util.repeat($parent, $element);
            $cloner.appendTo($clone);
            if( values ) {
                if (isSection) {
                    $clone.find('.wapf-input').each(function (i, e) {
                        if (values[i]) WAPF.Util.setFieldValue(jQuery(e), values[i]);
                    });
                } else {
                    WAPF.Util.setFieldValue($clone.find('.wapf-input').first(), values);
                }
            }

            after();
        };
        $cloner.on('click', '.wapf-add-clone', function(e){ e.preventDefault(); add(); });

        $cloner.on('click', '.wapf-del-clone', function(e) {
            e.preventDefault();
            var cache = $element.data('dupe') || [];
            if( !cache.length ) return;
            $cloner.appendTo( cache.length > 1 ? cache[cache.length-2] : $element);
            WAPF.Util.unrepeat($parent, $element, 1);
            after();
        });

        ($add.data('edit-cart')||[]).forEach( function(vals) { add(vals); });
    });
    
</script>