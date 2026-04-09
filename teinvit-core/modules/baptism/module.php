<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function teinvit_baptism_module_bootstrap_contract() {
    return [
        'vertical_key' => 'baptism',
        'status' => 'scaffold',
        'notes' => 'Phase 1 contract scaffold only. Runtime implementation starts in Phase 2.',
    ];
}
