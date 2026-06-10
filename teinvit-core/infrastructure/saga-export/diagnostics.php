<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class TeInvit_Saga_Export_Diagnostics {
    private $items = [];
    private $exported = 0;
    private $skipped = 0;

    public function add( $level, $code, $message, array $context = [] ) {
        $this->items[] = [
            'level' => sanitize_key( (string) $level ),
            'code' => sanitize_key( (string) $code ),
            'message' => (string) $message,
            'context' => $context,
        ];
    }

    public function exported_document() {
        $this->exported++;
    }

    public function skipped_document() {
        $this->skipped++;
    }

    public function all() {
        return $this->items;
    }

    public function exported_count() {
        return $this->exported;
    }

    public function skipped_count() {
        return $this->skipped;
    }

    public function has_errors() {
        foreach ( $this->items as $item ) {
            if ( ( $item['level'] ?? '' ) === 'error' ) {
                return true;
            }
        }
        return false;
    }
}
