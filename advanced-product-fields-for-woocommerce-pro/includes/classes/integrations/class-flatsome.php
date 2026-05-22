<?php
namespace SW_WAPF_PRO\Includes\Classes\Integrations {

	class Flatsome
	{
		public function __construct() {
			add_action('wp_footer', [ $this, 'add_javascript' ]);
		}

		public function add_javascript() {
			?>
			<script>
            jQuery(document).ajaxSuccess(function(e,xhr,d){
                jQuery(document).on('mfpOpen', function(){
                    new WAPF.Frontend(jQuery('.product-quick-view-container'));
                });
            });
			</script>
			<?php
		}

	}
}