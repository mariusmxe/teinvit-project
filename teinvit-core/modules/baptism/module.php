<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'TEINVIT_BAPTISM_MODULE_PATH', TEINVIT_CORE_PATH . 'modules/baptism/' );
define( 'TEINVIT_BAPTISM_MODULE_URL', TEINVIT_CORE_URL . 'modules/baptism/' );

require_once TEINVIT_BAPTISM_MODULE_PATH . 'services/placeholders.php';
require_once TEINVIT_BAPTISM_MODULE_PATH . 'contracts/boundaries.php';

function teinvit_baptism_module_bootstrap_contract() {
    return [
        'vertical_key' => 'baptism',
        'status' => 'phase3_bootstrap_ready',
        'public_runtime_enabled' => false,
        'storage_tables' => function_exists( 'teinvit_storage_tables_for_vertical' ) ? teinvit_storage_tables_for_vertical( 'baptism' ) : [],
        'boundaries' => teinvit_baptism_module_boundaries_contract(),
        'notes' => 'Bootstrap completed in Phase 3. Functional runtime remains disabled until later phases.',
    ];
}

function teinvit_baptism_module_register_runtime() {
    if ( ! function_exists( 'teinvit_register_vertical_module_runtime' ) ) {
        return;
    }

    teinvit_register_vertical_module_runtime( 'baptism', teinvit_baptism_module_bootstrap_contract() );
}

add_action( 'plugins_loaded', 'teinvit_baptism_module_register_runtime', 18 );
