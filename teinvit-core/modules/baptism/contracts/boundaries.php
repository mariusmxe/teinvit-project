<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function teinvit_baptism_module_boundaries_contract() {
    return [
        'payload' => [
            'status' => 'phase4_ready',
            'provider' => 'teinvit_baptism_payload_builder',
            'source_of_truth' => 'apf',
        ],
        'renderer' => [
            'status' => 'phase4_ready',
            'provider' => 'teinvit_baptism_renderer',
        ],
        'preview_pdf' => [
            'status' => 'phase4_ready',
            'provider' => 'teinvit_baptism_renderer',
        ],
        'admin_client' => [
            'status' => 'phase5_ready',
            'provider' => 'teinvit_baptism_admin_client_template',
            'acf_binding_phase' => 'phase5',
        ],
        'invitati' => [
            'status' => 'phase5_ready',
            'provider' => 'teinvit_baptism_invitati_template',
            'acf_binding_phase' => 'phase5',
        ],
        'rsvp' => [
            'status' => 'phase5_ready',
            'provider' => 'teinvit_baptism_handle_rsvp_rest',
        ],
        'reports' => [
            'status' => 'phase5_ready',
            'provider' => 'teinvit_baptism_build_rsvp_report_sets',
        ],
        'gifts' => [
            'status' => 'phase5_ready',
            'provider' => 'teinvit_baptism_build_gifts_summary_for_token',
            'engine' => 'shared_wedding_gifts_engine',
        ],
        'email_semantics' => [
            'status' => 'scaffold',
            'provider' => 'teinvit_baptism_email_semantics_placeholder',
            'engine' => 'shared_email_engine',
        ],
    ];
}
