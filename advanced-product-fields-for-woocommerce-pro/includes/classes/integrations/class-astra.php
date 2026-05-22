<?php
namespace SW_WAPF_PRO\Includes\Classes\Integrations {

	class Astra
	{
		public function __construct() {
			add_action('wp_footer', [$this, 'add_javascript'] );
		}

		public function add_javascript() {
			?>
			<script>
                jQuery(document).on('ast_quick_view_loader_stop', function(){ new WAPF.Frontend(jQuery('#ast-quick-view-modal .product')); });
			</script>
			<?php
		}

	}
}