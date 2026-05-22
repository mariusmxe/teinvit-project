<div class="notice notice-error is-dismissible">
    <p>
        <strong><?php esc_html_e( 'Advanced Product Fields - Important notice', 'sw-wapf' ); ?></strong>
    </p>
    <p>
        <?php esc_html_e( "To keep the plugin Advanced Product Fields running smoothly, we have to update your database. This typically doesn't take long but it depends on how many products are using the plugin. We recommend taking a backup before performing this action.", 'sw-wapf' ); ?>
    </p>
    <p>
        <a style="background: #d63638;border-color:transparent" href="#" class="button button-primary wapf-update-db"><?php esc_html_e( 'Update Database', 'sw-wapf' ); ?></a>
        <span class="wapf-update-progress" style="font-style:italic;padding-left:10px;"></span>
    </p>

    <script>
        
        var wapfUpdateInProgress = false;

        jQuery('.wapf-update-db').on('click', function(e) {
            
            e.preventDefault();
            if(wapfUpdateInProgress) return;

            var $button = jQuery(e.currentTarget);
            var $progress = jQuery('.wapf-update-progress');
            $progress.text('<?php _e('Starting update...', 'sw-wapf') ?>');
            wapfUpdateInProgress = true;    
            
            function update() {
                jQuery.ajax({
                    type: 'POST',
                    url: ajaxurl,
                    data: {
                        action: '<?php esc_html_e( $model['identifier'] ) ?>',
                        _nonce: '<?php echo $model['nonce'] ?>'
                    },
                    success: function(response) {
                        response = typeof response === 'string' ? JSON.parse(response) : response;

                        $progress.text(response.data.message);
                        if(! response.data.finished ) {
                            update();
                        } else {
                            wapfUpdateInProgress = false;
                            $button.hide();
                        }
                    },
                    error: function() {
                        wapfUpdateInProgress = false;
                        $progress.html('<?php _e('Something went wrong. Try again or <a target="_blank" href="https://studiowombat.com/request-support">contact support</a>.', 'sw-wapf') ?>');
                    }
                });
            }
            update();
        });
    </script>

</div>