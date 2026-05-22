<?php
/** @var array $model */
use \SW_WAPF_PRO\Includes\Classes\File_Upload;

if(File_Upload::can_upload()) {
    
    if ( File_Upload::is_ajax_upload() ) {

        $allows_multiple    = isset($model['field']->options['multiple']) && $model['field']->options['multiple'];
        $base_url           = File_Upload::get_base_upload_url();
        $dropzone_options   = [
	        'maxFiles'              => $allows_multiple ? 9999 : 1,
	        'thumbnailWidth'        => 1000,
	        'thumbnailHeight'       => 1000,
	        'dictFileTooBig'        => __('File is too big ({{filesize}}MB). Max filesize is {{maxFilesize}}MB.','sw-wapf'),
	        'dictInvalidFileType'   => __("You can't upload files of this type.",'sw-wapf'),
	        'dictMaxFilesExceeded'  => __("You can't upload any more files.",'sw-wapf'),
	        'dictUploadCanceled'    => __("Upload canceled.",'sw-wapf'),
	        'previewTemplate'       => '<div class="dz-preview"><div class="dz-image"><img style="max-width:100%" src="" alt="" data-dz-thumbnail /></div><div class="dz-filename" data-dz-name></div><div class="dz-left"><div class="dz-progress-wrapper"><div class="dz-progress"></div><div class="dz-upload" data-dz-uploadprogress></div></div>' . apply_filters( 'wapf/html/file_entry', '', $model['field'] ) . '<div class="dz-remove" data-dz-remove title="' . __( 'Remove file', 'sw-wapf' ) . '"><svg fill="currentColor" xmlns="http://www.w3.org/2000/svg" style="width:1rem;height:1rem" viewBox="0 0 352 512"><path d="M242.72 256l100.07-100.07c12.28-12.28 12.28-32.19 0-44.48l-22.24-22.24c-12.28-12.28-32.19-12.28-44.48 0L176 189.28 75.93 89.21c-12.28-12.28-32.19-12.28-44.48 0L9.21 111.45c-12.28 12.28-12.28 32.19 0 44.48L109.28 256 9.21 356.07c-12.28 12.28-12.28 32.19 0 44.48l22.24 22.24c12.28 12.28 32.2 12.28 44.48 0L176 322.72l100.07 100.07c12.28 12.28 32.2 12.28 44.48 0l22.24-22.24c12.28-12.28 12.28-32.19 0-44.48L242.72 256z"/></svg></div></div></div>',
        ];

        if( !empty( $model['field']->options['maxsize'] ) ) {
            $max_size = intval($model['field']->options['maxsize']);
            if($max_size >= 1)
                $dropzone_options['maxFilesize'] = $max_size;
        }

	    $dropzone_options = apply_filters('wapf/ajax_file_upload_config', $dropzone_options, $model['field']);

	    $defaults = $model['is_edit'] ?  \SW_WAPF_PRO\Includes\Classes\Helper::extract_upload_urls_from_html($model['default'][0]) : [];

	    $field_id = esc_attr($model['field']->id);
        $nonce =  wp_create_nonce('wapf_fupload');
        ?>
        <div style="position: relative">
            <div class="dzone" id="wapf-dz-<?php echo $field_id;?>">
                <div class="dz-message" data-dz-message>
                    <?php _e('Drag files here or <span>browse</span>','sw-wapf'); ?>
                </div>
            </div>
            <input type="text" style="position: absolute!important;pointer-events: none;top:0;left:0;bottom:0;right:0;opacity:0!important;" data-is-file="1" value="<?php echo esc_attr(join(', ', $defaults));?>" <?php echo $model['field_attributes']; ?> name="wapf[field_<?php echo $field_id;?>]" />
        </div>

        <div class="wapf-dz-error wapf-dz-error-<?php echo $field_id;?>"></div>
        <script>
            ( function() {
                
                var init = function($) {
                    Dropzone.autoDiscover = false;
                    window.initWapfFileUpload = window.initWapfFileUpload || {};
                    if(!window.initWapfFileUpload['<?php echo $field_id; ?>'])
                        window.initWapfFileUpload['<?php echo $field_id; ?>'] = function(fieldId) {
                            var uploaded = {};
                            var toVal = function() {
                                var tmpArr = [];
                                Object.keys(uploaded).forEach(function(k){ tmpArr.push(uploaded[k]['path'].replace('<?php echo $base_url ?>','')); /* "edit cart" values can send the whole URL so remove it here. */ });
                                $('.input-'+fieldId).val(tmpArr.join(',')).trigger('change');
                            };
                            $('#wapf-dz-'+fieldId+' .wapf-dz-btn').on('click',function(e){e.preventDefault();});
                            if($('#wapf-dz-'+fieldId)[0].dropzone) return;
                            $('#wapf-dz-'+fieldId).dropzone( $.extend(
                                <?php echo wp_json_encode($dropzone_options) ?>, {
                                    paramName: 'wapf[field_'+fieldId+']',
                                    uploadMultiple:  true,
                                    parallelUploads: 1,
                                    url: wapf_config.ajax,
                                    params: function() {
                                        return {
                                            action : 'wapf_upload',
                                            nonce: '<?php echo $nonce; ?>',
                                            field_groups: $('[name=wapf_field_groups]').val()
                                        };
                                    },
                                    init: function() {
                                        this.on('addedfile', function(f){
                                            if( !f.type || f.type  )
                                            if( ! f.type || ! f.type.startsWith('image/') || f.type === 'image/tiff' || f.type === 'image/heic' ) {
                                                f.previewElement.querySelector('.dz-image').remove();
                                            }
                                        });
                                        this.on('sending', function() {
                                            $('form.cart .single_add_to_cart_button').prop('disabled',true);
                                        });
                                        this.on('complete', function() {
                                            $('form.cart .single_add_to_cart_button').prop('disabled',false);
                                        });
                                        this.on('success', function(file, response) {
                                            uploaded[file.upload.uuid] = response.data[0];
                                            $(file.previewElement).data('uuid',file.upload.uuid);
                                            $(document).trigger('wapf/file_uploaded',{response: response.data,file: file,fieldId: fieldId, uploads: uploaded});
                                            toVal();
                                            <?php if(!$allows_multiple) { ?>
                                            $('#wapf-dz-'+fieldId).find('.dz-message').hide();
                                            <?php } ?>
                                        });
                                        this.on('error', function( file, msg ) {
                                            var $wrapper = $('.wapf-dz-error-'+fieldId);
                                            var error = ( typeof msg === 'string' ? msg : (!msg.success && msg.data ? msg.data : '') );
                                            if(error) {
                                                this.removeFile(file);
                                                var $e = $('<div>').html(error).prependTo($wrapper);
                                                setTimeout( function(){$e.hide('fast',function(){$e.remove()}); }, 9000);
                                            }
                                        });
                                        this.on('removedfile', function( file ) {
                                            if(uploaded[file.upload.uuid]) {
                                                $.getJSON(wapf_config.ajax + '?action=wapf_upload_remove&nonce=<?php echo $nonce; ?>&file=' + decodeURIComponent(uploaded[file.upload.uuid].path));
                                                delete uploaded[file.upload.uuid];
                                                $(document).trigger('wapf/file_deleted', {file: file,fieldId:fieldId, uploads: uploaded});
                                                jQuery('.wttw').trigger('mouseleave');
                                                toVal();
                                                <?php if(!$allows_multiple) { ?>
                                                $('#wapf-dz-'+fieldId).find('.dz-message').show();
                                                <?php } ?>
                                            }
                                        });
                                        <?php if( ! empty( $defaults ) ) { ?>
                                        
                                        this.addFromUrl = function(urls) {
                                            urls.split(',').forEach(function(u) {
                                                var fu = /*'<?php echo $base_url ?>' +*/ u, o = _this.options, e = _this.emit.bind(_this);
                                                var file = { isEditCart: true, processing: true, accepted: true, name: u.substring(u.lastIndexOf("/") + 1).trim(), size: 1, type: 'image/jpeg', status: Dropzone.SUCCESS, upload: {uuid: Date.now()}, dataURL: fu };
                                                _this.files.push(file);
                                                e("addedfile", file);
                                                e("processing", file);
                                                e("success", file, {status: "success", data: [ { path:u, file: fu } ] }, false);
                                                if( /\.(png|jpg|jpeg|webp|gif)$/i.test(fu) ) {
                                                    _this.createThumbnailFromUrl(file, o.thumbnailWidth, o.thumbnailHeight, o.thumbnailMethod, true, function (thumbnail) {
                                                        e('thumbnail', file, thumbnail);
                                                    });
                                                }
                                                
                                                e("complete", file);
      
                                            });

                                        };
                                        var _this = this;
                                        var defaults = $( '.input-' + fieldId ).val();
                                        if( defaults ) {
                                            window.addEventListener( 'load', function() {
                                                setTimeout( function() {
                                                    _this.addFromUrl( defaults ); 
                                                }, 1000 ); //Make sure LCP picks this event up.
                                            });
                                        }
                                        <?php } ?>
                                    }
                                }
                            ));
                            // Ajax added to cart, clear files from the uploader (do not delete!)
                            $( document.body ).on( 'added_to_cart', function() {
                                uploaded = {}; toVal();
                                $('#wapf-dz-<?php echo $field_id; ?>')[0].dropzone.removeAllFiles();
                                $('#wapf-dz-<?php echo $field_id; ?> .dz-message').show();
                            });
                        };
                    window.initWapfFileUpload['<?php echo $field_id; ?>']('<?php echo $field_id; ?>');

                    $(document).on('wapf/cloned', function( e, fieldId, idx, $clone) {
                        // Skip non file fields
                        var isSection = $('.field-'+fieldId).hasClass('wapf-section');
                        if(!isSection && fieldId !== '<?php echo $field_id;?>') return; // this is not about a file field, so stop.
                        var $f = $clone.find((isSection ? '.field-<?php echo $field_id;?> ' : '')+'input');
                        // the file field is not in this section, so stop too
                        if( ! $f.length ) return;
                        // repeated file fields don't need the previous file value so clear it and remove relevant HTML.
                        $f.val('');
                        var newId = '<?php echo $field_id;?>_clone_' + idx;
                        $clone.find('.dzone').attr('id','wapf-dz-'+newId).children().not('.dz-message').html('');
                        $clone.find('.dz-message').show();
                        $clone.find('script').remove();
                        $clone.find('.wapf-dz-error').removeClass('wapf-dz-error-'+fieldId).addClass('wapf-dz-error-'+newId);
                        window.initWapfFileUpload['<?php echo $field_id;?>'](newId);
                    });
                };
                
                document.addEventListener( 'DOMContentLoaded', function() {
                    init( jQuery);
                });
                
                // Allow for manual init (for example when this content is dynamically loaded via ajax.
                document.addEventListener( 'wapf/init_dropzone', function() {
                    init( jQuery );
                });
                
            })();
        </script>
        <?php
    } else {
        echo '<input type="file" ' . $model['field_attributes'] . ' />';
    }

}
else {
    echo '<div class="wapf-upload-auth-required">' . wp_kses( get_option( 'wapf_settings_upload_msg' ), \SW_WAPF_PRO\Includes\Classes\Html::$minimal_allowed_html ) . '</div>';
}