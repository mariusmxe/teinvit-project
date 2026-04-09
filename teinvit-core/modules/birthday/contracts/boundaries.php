<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function teinvit_birthday_module_boundaries_contract() {
    return [
        'payload' => [
            'status' => 'phase4_ready',
            'provider' => 'teinvit_birthday_payload_builder',
            'source_of_truth' => 'apf',
        ],
        'renderer' => [
            'status' => 'phase4_ready',
            'provider' => 'teinvit_birthday_renderer',
        ],
        'preview_pdf' => [
            'status' => 'phase4_ready',
            'provider' => 'teinvit_birthday_renderer',
        ],
        'admin_client' => [
            'status' => 'scaffold',
            'provider' => 'teinvit_birthday_admin_client_placeholder',
            'acf_binding_phase' => 'phase5',
        ],
        'invitati' => [
            'status' => 'scaffold',
            'provider' => 'teinvit_birthday_invitati_placeholder',
            'acf_binding_phase' => 'phase5',
        ],
        'rsvp' => [
            'status' => 'scaffold',
            'provider' => 'teinvit_birthday_rsvp_placeholder',
        ],
        'reports' => [
            'status' => 'scaffold',
            'provider' => 'teinvit_birthday_reports_placeholder',
        ],
        'gifts' => [
            'status' => 'shared_engine_ready',
            'provider' => 'teinvit_birthday_gifts_placeholder',
            'engine' => 'shared_wedding_gifts_engine',
        ],
        'email_semantics' => [
            'status' => 'scaffold',
            'provider' => 'teinvit_birthday_email_semantics_placeholder',
            'engine' => 'shared_email_engine',
        ],
    ];
}
