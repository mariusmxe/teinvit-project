<?php
/* @var $model array */
use SW_WAPF_PRO\Includes\Classes\Util;
use SW_WAPF_PRO\Includes\Classes\Html;
?>

<div rv-controller="LayoutCtrl" data-layout-options="<?php echo Util::to_html_attribute_string($model['layout']); ?>"
>

    <input type="hidden" name="wapf-layout" rv-value="layoutJson" />

    <div class="wapf-layout-list">

        <div class="apf-conditions-list__body">

            <?php

                Html::setting([
                    'type'              => 'select',
                    'id'                => 'labels_position',
                    'label'             => __('Label position','sw-wapf'),
                    'description'       => __('Where should the label be positioned?','sw-wapf'),
                    'options'           => [
                        'above'         => __('Above the field', 'sw-wapf'),
                        'below'         => __('Below the field', 'sw-wapf'),
                        /*'left'          => __('Left from the field', 'sw-wapf'),
                        'right'         => __('Right from the field', 'sw-wapf'),*/
                    ],
                    'is_field_setting'  => false
                ]);

                Html::setting([
                    'type'              => 'select',
                    'id'                => 'instructions_position',
                    'label'             => __('Instruction position','sw-wapf'),
                    'description'       => __('Where should instructions be positioned?','sw-wapf'),
                    'options'           => [
                        'label'         => __('Below the label', 'sw-wapf'),
                        'field'         => __('Below the field', 'sw-wapf'),
                        'tooltip'       => __('As tooltip', 'sw-wapf'),
                    ],
                    'is_field_setting'  => false
                ]);

                Html::setting([
                    'type'              => 'true-false',
                    'id'                => 'mark_required',
                    'label'             => __('Mark required fields','sw-wapf'),
                    'description'       => __('Add *-symbol for required fields.','sw-wapf'),
                    'is_field_setting'  => false
                ]);

            Html::setting([
	            'type'              => 'gallery-image',
	            'id'                => 'gallery_images',
	            'label'             => __('Change product image','sw-wapf'),
	            'description'       => __('Change the main product image depending on selected options.','sw-wapf'),
            ]);

            ?>

            <div class="wapf-field__setting">
                <div class="wapf-setting__label">
                    <label><?php esc_html_e( 'Design settings', 'sw-wapf' ) ?></label>
                </div>
                <div class="wapf-setting__input">
                    <div class="apf-info-note">
                        <div class="dashicon dashicons dashicons-info"></div>
                        <div>
                            <?php
                            $design_url = add_query_arg(
                                [
                                    'page'    => 'wc-settings',
                                    'tab'     => 'wapf_settings',
                                    'section' => 'design',
                                ],
                                admin_url('admin.php')
                            );
                            printf(
                                esc_html__( 'Want to style your fields? %s', 'sw-wapf' ),
                                sprintf(
                                    '<a target="_blank" href="%s">%s</a>',
                                    esc_url( $design_url ),
                                    esc_html__( 'Go to design the settings.', 'sw-wapf' )
                                )
                            );
                            ?>
                        </div>
                    </div>
                </div>
            </div>
            
        </div>
    </div>
</div>