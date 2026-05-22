<?php
/* @var $field [] */
/* @var $type string */
use \SW_WAPF_PRO\Includes\Classes\Config;
use \SW_WAPF_PRO\Includes\Classes\Html;
$types = Config::get_field_definitions( 'admin' );
$field_options = Config::get_field_options();
?>


<div class="wapf-field-wrapper" rv-each-field="renderedFields" rv-width="field.width">
    <div rv-if="renderedFields| isInPage $index page itemsPerPage" rv-cloak class="wapf-field" rv-data-type="field.type"
         rv-data-level="field.level"
         rv-data-idx="$index"
         rv-data-field-id="field.id" rv-data-grouptype="field.group">
    <div class="wapf-field__header">
        <div class="wapf-field-sort sort--left" title="<?php _e('Drag & drop','sw-wapf');?>">☰</div>
        <div class="wapf-field-icon">
            <?php
                foreach ( $types as $type ) {
                    ?>
                    <div rv-if="field.type | eq '<?php echo $type['id']; ?>'">
                        <?php if(isset($type['icon'])) echo $type['icon']; ?>
                    </div>
                    <?php
                }
            ?>
        </div>
        <div class="wapf-field-label">
            <span rv-if="field.group|in 'field,content'" rv-show="field.label | isNotEmpty" rv-text="field.label"></span>
            <span rv-if="field.group|in 'field,content'" rv-show="field.label | isEmpty">
                <?php esc_html_e('(No label)','sw-wapf'); ?>
            </span>
            <span rv-if="field.group|eq 'layout'" style="font-weight: bold" rv-text="fieldDefinitions | getFieldDefTitle field.type"></span>
            <span class="wapf-field-below-title">
                <span class="wapf-field-id">
                    <span>ID: {field.id}</span>
                    <span class="wapf-copy-id"><a href="#" data-copy="<?php _e('copy', 'sw-wapf') ?>" data-copied="<?php _e('copied!', 'sw-wapf') ?>"><?php _e('copy', 'sw-wapf'); ?></a></span>
                </span>
            </span>
        </div>

        <div class="wapf-field-type">
            <span rv-text="fieldDefinitions | getFieldDefTitle field.type"></span>
        </div>
        <div class="wapf-field-actions">
            <a class="wapf-action-dupe" href="#" title="<?php _e('Duplicate field','sw-wapf');?>">Duplicate</a>
            <a href="#" style="color: #a00 !important" title="<?php _e('Delete field','sw-wapf');?>" rv-on-click="deleteField">Delete</a>
        </div>
    </div>
    <div class="wapf-field-body" rv-if="activeField.id | eq field.id">
        
        <div class="wapf-field-tab">
            <ul>
                <li><a data-tab="general" rv-on-click="setTab" rv-class-active="tab | eq 'general'" href="#"><?php _e( 'General', 'sw-wapf' ) ?></a></li>
                <li rv-if="field.type | eq 'date'"><a data-tab="selection" rv-on-click="setTab" rv-class-active="tab | eq 'selection'" href="#"><?php _e( 'Date selection', 'sw-wapf' ) ?></a></li>
                <li><a data-tab="appearance" rv-on-click="setTab" rv-class-active="tab | eq 'appearance'" href="#"><?php _e( 'Appearance', 'sw-wapf' ) ?></a></li>
                <li><a data-tab="advanced" rv-on-click="setTab" rv-class-active="tab | eq 'advanced'" href="#"><?php _e( 'Advanced', 'sw-wapf' ) ?></a></li>
            </ul>
        </div>
        
        <div class="tab-general" rv-show="tab | eq 'general'">
        <?php
        
            do_action('wapf/admin/before_field_settings');
            
            Html::setting([
                'type'              => 'field-type',
                'id'                => 'type',
                'label'             => __('Type','sw-wapf'),
                'description'       => __('The field\'s type.','sw-wapf'),
                'options'           => $types,
            ]);
            ?>
            <div rv-if="field.group|neq 'layout'">
            <?php
            Html::setting([
                'type'              => 'text',
                'id'                => 'label',
                'label'             => __('Label','sw-wapf'),
                'description'       => __('Label shown near the field.','sw-wapf'),
                'is_field_setting'  => true
            ]);
            ?>
            </div>
            <div rv-if="field.group | notin 'content,layout'" rv-show="field.type | neq 'calc'">
            <?php
            Html::setting([
                'type'              => 'textarea',
                'id'                => 'description',
                'label'             => __('Instructions','sw-wapf'),
                'description'       => __('Display extra info near the field.','sw-wapf'),
                'is_field_setting'  => true
            ]);
    
            Html::setting([
                'type'              => 'true-false',
                'id'                => 'required',
                'label'             => __('Required','sw-wapf'),
                'is_field_setting'  => true
            ]);
            ?>
            </div>
            
            <?php Html::settings( $field_options ); ?>
        </div>

        <?php if( get_option( 'wapf_datepicker', 'no' ) === 'yes' ) { ?>
            <div class="tab-selection" rv-show="tab | eq 'selection'">
                <?php Html::settings( $field_options, 'selection' ) ?>
            </div>
        <?php } ?>

        <div class="tab-appearance" rv-show="tab | eq 'appearance'">
            <div style="border-bottom: 1px solid #ececee;display: flex;;padding: 10px;align-items: center;">
                <div style="margin-right:10px">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 512 512" xml:space="preserve"><path d="M492.263 19.713c-26.252-26.254-68.97-26.254-95.221 0L130.066 286.69a106.923 106.923 0 0 0-10.457-.515c-53.501 0-98.808 40.039-105.386 93.134L.128 493.073a16.835 16.835 0 0 0 18.776 18.776l113.763-14.096c53.096-6.577 93.136-51.883 93.136-105.386 0-3.528-.177-7.015-.515-10.456l266.976-266.976c26.313-26.311 26.317-68.905-.001-95.222zM128.528 464.344h-.001l-92.334 11.44 11.44-92.334c4.494-36.262 35.437-63.609 71.977-63.609 39.992 0 72.527 32.535 72.527 72.527 0 36.54-27.346 67.482-63.609 71.976zm86.091-119.373a106.91 106.91 0 0 0-47.612-47.612l28.368-28.368 47.612 47.612-28.368 28.368zM468.46 91.131 266.792 292.797l-47.612-47.612L420.848 43.519c13.156-13.156 34.453-13.158 47.611 0 13.156 13.157 13.159 34.454.001 47.612z"/></svg>
                </div>
                <div>
                    <?php _e( 'Looking for more design settings? Explore all color, style, and design settings <a target="_blank" href="/wp-admin/admin.php?page=wc-settings&tab=wapf_settings&section=design">here</a>.', 'sw-wapf' ); ?>
                </div>
            </div>
            <?php Html::settings( $field_options, 'appearance' ); ?>
            <div rv-if="field.group | notin 'content,layout'">
                <?php
                Html::setting([
                    'type'              => 'true-falses',
                    'options'           => [
                        'hide_cart'     => __('Hide this field value on the cart page','sw-wapf'),
                        'hide_checkout' => __('Hide this field value on the checkout page','sw-wapf'),
                        'hide_order'    => __('Hide this field value on the "order received" page and emails.','sw-wapf'),
                    ],
                    'label'             => __('Hide on cart, checkout, order','sw-wapf'),
                    'description'       => __("Hide field values from cart, checkout, or order.",'sw-wapf'),
                    'is_field_setting'  => true
                ]);
                ?>
            </div>
            <div rv-if="field.type | neq 'sectionend'">
                <?php
                \SW_WAPF_PRO\Includes\Classes\Html::setting([
                    'type'              => 'attributes',
                    'id'                => 'attributes',
                    'label'             => __('Wrapper attributes','sw-wapf'),
                    'is_field_setting'  => true
                ]);
                ?>
            </div>
        </div>

        <div class="tab-advanced" rv-show="tab | eq 'advanced'">
            <?php do_action('wapf/admin/before_additional_field_settings'); ?>

            <div rv-if="field.type | notin 'p,img,sectionend,calc'">
            <?php
            Html::setting([
                'type'              => 'repeater',
                'id'                => 'clone',
                'label'             => __('Enable repeater','sw-wapf'),
                'description'       => __('Make the element repeatable.','sw-wapf'),
                'is_field_setting'  => true
            ]);
            ?>
            </div>

            <div rv-if="field.type | neq 'sectionend'">
                <?php
                Html::setting([
                    'type'              => 'conditionals',
                    'id'                => 'conditionals',
                    'label'             => __('Conditional logic','sw-wapf'),
                    'description'       => __('Show field only if conditions are met.','sw-wapf'),
                    'is_field_setting'  => true
                ]);
                ?>
            </div>
                
            <?php 
            Html::settings( $field_options, 'advanced' );
            do_action('wapf/admin/after_additional_field_settings');
            do_action( 'wapf/admin/after_field_settings' );
            ?>
        </div>
        
    </div>
    </div>
</div>