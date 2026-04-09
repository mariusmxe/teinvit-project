<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function teinvit_baptism_module_boundaries_contract() {
    return [
        'payload' => [
            'status' => 'scaffold',
            'provider' => 'teinvit_baptism_payload_builder_placeholder',
            'source_of_truth' => 'apf',
        ],
        'renderer' => [
            'status' => 'scaffold',
            'provider' => 'teinvit_baptism_renderer_placeholder',
        ],
        'preview_pdf' => [
            'status' => 'scaffold',
            'provider' => 'teinvit_baptism_preview_pdf_placeholder',
        ],
        'admin_client' => [
            'status' => 'scaffold',
            'provider' => 'teinvit_baptism_admin_client_placeholder',
            'acf_binding_phase' => 'phase5',
        ],
        'invitati' => [
            'status' => 'scaffold',
            'provider' => 'teinvit_baptism_invitati_placeholder',
            'acf_binding_phase' => 'phase5',
        ],
        'rsvp' => [
            'status' => 'scaffold',
            'provider' => 'teinvit_baptism_rsvp_placeholder',
        ],
        'reports' => [
            'status' => 'scaffold',
            'provider' => 'teinvit_baptism_reports_placeholder',
        ],
        'gifts' => [
            'status' => 'shared_engine_ready',
            'provider' => 'teinvit_baptism_gifts_placeholder',
            'engine' => 'shared_wedding_gifts_engine',
        ],
        'email_semantics' => [
            'status' => 'scaffold',
            'provider' => 'teinvit_baptism_email_semantics_placeholder',
            'engine' => 'shared_email_engine',
        ],
    ];
}
