<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'TEINVIT_BAPTISM_MODULE_PATH', TEINVIT_CORE_PATH . 'modules/baptism/' );
define( 'TEINVIT_BAPTISM_MODULE_URL', TEINVIT_CORE_URL . 'modules/baptism/' );

require_once TEINVIT_BAPTISM_MODULE_PATH . 'services/placeholders.php';
require_once TEINVIT_BAPTISM_MODULE_PATH . 'services/runtime.php';
require_once TEINVIT_BAPTISM_MODULE_PATH . 'contracts/boundaries.php';
require_once TEINVIT_BAPTISM_MODULE_PATH . 'hooks/product-preview.php';

function teinvit_baptism_module_bootstrap_contract() {
    return [
        'vertical_key' => 'baptism',
        'status' => 'phase4_preview_pdf_ready',
        'public_runtime_enabled' => true,
        'storage_tables' => function_exists( 'teinvit_storage_tables_for_vertical' ) ? teinvit_storage_tables_for_vertical( 'baptism' ) : [],
        'boundaries' => teinvit_baptism_module_boundaries_contract(),
        'notes' => 'Phase 4 enabled payload + renderer + preview/pdf for public token routes only.',
    ];
}

function teinvit_baptism_module_register_runtime() {
    if ( ! function_exists( 'teinvit_register_vertical_module_runtime' ) ) {
        return;
    }

    teinvit_register_vertical_module_runtime( 'baptism', teinvit_baptism_module_bootstrap_contract() );
}

add_action( 'plugins_loaded', 'teinvit_baptism_module_register_runtime', 18 );
