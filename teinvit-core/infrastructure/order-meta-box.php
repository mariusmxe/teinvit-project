<?php
/**
 * TeInvit admin order panel.
 *
 * Shared, HPOS-safe order diagnostics for legacy single-token orders and the
 * order-token tables introduced by the multi-token workflow.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! function_exists( 'teinvit_order_meta_box_order_from_context' ) ) {
    function teinvit_order_meta_box_order_from_context( $context ) {
        if ( $context instanceof WC_Order ) {
            return $context;
        }

        if ( $context instanceof WP_Post && function_exists( 'wc_get_order' ) ) {
            return wc_get_order( $context->ID );
        }

        if ( is_numeric( $context ) && function_exists( 'wc_get_order' ) ) {
            return wc_get_order( (int) $context );
        }

        return null;
    }
}

if ( ! function_exists( 'teinvit_order_meta_box_empty_value' ) ) {
    function teinvit_order_meta_box_empty_value() {
        return '<span class="teinvit-muted">-</span>';
    }
}

if ( ! function_exists( 'teinvit_order_meta_box_format_value' ) ) {
    function teinvit_order_meta_box_format_value( $value ) {
        $value = is_scalar( $value ) ? trim( (string) $value ) : '';

        return $value !== '' ? esc_html( $value ) : teinvit_order_meta_box_empty_value();
    }
}

if ( ! function_exists( 'teinvit_order_meta_box_status_badge' ) ) {
    function teinvit_order_meta_box_status_badge( $status ) {
        $status = sanitize_key( (string) $status );
        if ( $status === '' ) {
            $status = 'unknown';
        }

        $tone = 'neutral';
        if ( in_array( $status, [ 'generated', 'applied', 'active', 'completed', 'legacy' ], true ) ) {
            $tone = 'good';
        } elseif ( in_array( $status, [ 'failed', 'error', 'blocked' ], true ) ) {
            $tone = 'bad';
        } elseif ( in_array( $status, [ 'pending', 'processing', 'not_generated', 'none' ], true ) ) {
            $tone = 'warn';
        }

        return sprintf(
            '<span class="teinvit-badge teinvit-badge-%s">%s</span>',
            esc_attr( $tone ),
            esc_html( $status )
        );
    }
}

if ( ! function_exists( 'teinvit_order_meta_box_context_for_token' ) ) {
    function teinvit_order_meta_box_context_for_token( $token ) {
        $token = sanitize_text_field( (string) $token );
        if ( $token === '' || ! function_exists( 'teinvit_resolve_token_context' ) ) {
            return [];
        }

        $context = teinvit_resolve_token_context( $token );

        return is_array( $context ) ? $context : [];
    }
}

if ( ! function_exists( 'teinvit_order_meta_box_active_version' ) ) {
    function teinvit_order_meta_box_active_version( $token, array $context = [] ) {
        $token = sanitize_text_field( (string) $token );
        $row   = null;

        if ( $token !== '' && function_exists( 'teinvit_phase4_pdf_version_row' ) ) {
            $row = teinvit_phase4_pdf_version_row( $token, 0 );
        }

        if ( ! is_array( $row ) && ! empty( $context['active_snapshot'] ) && is_array( $context['active_snapshot'] ) ) {
            $row = $context['active_snapshot'];
        }

        if ( ! is_array( $row ) ) {
            return [
                'id'               => 0,
                'label'            => '',
                'pdf_status'       => '',
                'pdf_url'          => '',
                'pdf_filename'     => '',
                'pdf_generated_at' => '',
                'created_at'       => '',
            ];
        }

        $version_id = max( 0, (int) ( $row['id'] ?? $row['version_id'] ?? 0 ) );
        $variant    = 0;
        if ( $version_id > 0 && function_exists( 'teinvit_phase4_pdf_variant_number_for_version' ) ) {
            $variant = max( 0, (int) teinvit_phase4_pdf_variant_number_for_version( $token, $version_id ) );
        }

        $filename = sanitize_file_name( (string) ( $row['pdf_filename'] ?? '' ) );
        if ( $filename === '' && $version_id > 0 && function_exists( 'teinvit_phase4_pdf_filename_for_version' ) ) {
            $filename = sanitize_file_name( (string) teinvit_phase4_pdf_filename_for_version( $token, $version_id, $variant ) );
        }

        return [
            'id'               => $version_id,
            'label'            => $version_id > 0 ? sprintf( 'v%d-id%d', $variant, $version_id ) : '',
            'pdf_status'       => sanitize_key( (string) ( $row['pdf_status'] ?? '' ) ),
            'pdf_url'          => esc_url_raw( (string) ( $row['pdf_url'] ?? '' ) ),
            'pdf_filename'     => $filename,
            'pdf_generated_at' => sanitize_text_field( (string) ( $row['pdf_generated_at'] ?? '' ) ),
            'created_at'       => sanitize_text_field( (string) ( $row['created_at'] ?? '' ) ),
        ];
    }
}

if ( ! function_exists( 'teinvit_order_meta_box_capabilities_for_token' ) ) {
    function teinvit_order_meta_box_capabilities_for_token( $token, array $context = [] ) {
        $token = sanitize_text_field( (string) $token );

        if ( $token !== '' && function_exists( 'teinvit_capabilities_for_token' ) ) {
            $capabilities = teinvit_capabilities_for_token( $token );
            if ( is_array( $capabilities ) ) {
                return $capabilities;
            }
        }

        return isset( $context['capabilities'] ) && is_array( $context['capabilities'] )
            ? $context['capabilities']
            : [];
    }
}

if ( ! function_exists( 'teinvit_order_meta_box_can_open_invitati' ) ) {
    function teinvit_order_meta_box_can_open_invitati( array $capabilities ) {
        if ( empty( $capabilities ) ) {
            return current_user_can( 'manage_woocommerce' );
        }

        if ( array_key_exists( 'can_share_invitation', $capabilities ) ) {
            return ! empty( $capabilities['can_share_invitation'] );
        }

        if ( array_key_exists( 'can_manage_gifts', $capabilities ) ) {
            return ! empty( $capabilities['can_manage_gifts'] );
        }

        return current_user_can( 'manage_woocommerce' );
    }
}

if ( ! function_exists( 'teinvit_order_meta_box_collect_tokens' ) ) {
    function teinvit_order_meta_box_collect_tokens( WC_Order $order ) {
        $order_id            = $order->get_id();
        $legacy_bridge_token = sanitize_text_field( (string) $order->get_meta( '_teinvit_token' ) );
        if ( $legacy_bridge_token === '' ) {
            $legacy_bridge_token = sanitize_text_field( (string) get_post_meta( $order_id, '_teinvit_token', true ) );
        }
        $rows = function_exists( 'teinvit_get_order_tokens_for_order' )
            ? teinvit_get_order_tokens_for_order( $order_id )
            : [];
        $tokens = [];

        if ( is_array( $rows ) && ! empty( $rows ) ) {
            foreach ( $rows as $row ) {
                if ( ! is_array( $row ) ) {
                    continue;
                }

                $token = sanitize_text_field( (string) ( $row['token'] ?? '' ) );
                if ( $token === '' ) {
                    continue;
                }

                $context       = teinvit_order_meta_box_context_for_token( $token );
                $active        = teinvit_order_meta_box_active_version( $token, $context );
                $pdf_status    = sanitize_key( (string) ( $row['pdf_status'] ?? '' ) );
                $version_state = sanitize_key( (string) ( $active['pdf_status'] ?? '' ) );
                if ( $pdf_status === '' ) {
                    $pdf_status = $version_state;
                }
                if ( $pdf_status === '' && ! empty( $context['pdf_status'] ) ) {
                    $pdf_status = sanitize_key( (string) $context['pdf_status'] );
                }

                $pdf_url = esc_url_raw( (string) ( $active['pdf_url'] ?? '' ) );
                if ( $pdf_url === '' && ! empty( $context['pdf_url'] ) ) {
                    $pdf_url = esc_url_raw( (string) $context['pdf_url'] );
                }

                $tokens[] = [
                    'source'             => 'order_tokens',
                    'legacy'             => ! empty( $row['legacy'] ),
                    'legacy_bridge'      => $legacy_bridge_token !== '' && hash_equals( $legacy_bridge_token, $token ),
                    'token'              => $token,
                    'vertical'           => sanitize_key( (string) ( $context['vertical'] ?? $row['vertical'] ?? '' ) ),
                    'product_name'       => sanitize_text_field( (string) ( $context['product_name'] ?? $row['product_name'] ?? '' ) ),
                    'product_slug'       => sanitize_title( (string) ( $context['product_slug'] ?? $row['product_slug'] ?? '' ) ),
                    'package_type'       => sanitize_key( (string) ( $context['package_type'] ?? $row['package_type'] ?? 'unknown' ) ),
                    'product_id'         => max( 0, (int) ( $context['product_id'] ?? $row['product_id'] ?? 0 ) ),
                    'variation_id'       => max( 0, (int) ( $context['variation_id'] ?? $row['variation_id'] ?? 0 ) ),
                    'order_item_id'      => max( 0, (int) ( $context['order_item_id'] ?? $row['order_item_id'] ?? 0 ) ),
                    'quantity_index'     => max( 1, (int) ( $context['quantity_index'] ?? $row['quantity_index'] ?? 1 ) ),
                    'status'             => sanitize_key( (string) ( $context['status'] ?? $row['status'] ?? 'pending' ) ),
                    'pdf_status'         => $pdf_status !== '' ? $pdf_status : 'not_generated',
                    'active_version_id'  => max( 0, (int) ( $active['id'] ?? 0 ) ),
                    'active_version'     => sanitize_text_field( (string) ( $active['label'] ?? '' ) ),
                    'version_pdf_status' => $version_state,
                    'pdf_filename'       => sanitize_file_name( (string) ( $active['pdf_filename'] ?? ( $context['pdf_filename'] ?? '' ) ) ),
                    'pdf_url'            => $pdf_url,
                    'last_error'         => sanitize_textarea_field( (string) ( $row['last_error'] ?? $context['last_error'] ?? '' ) ),
                    'created_at'         => sanitize_text_field( (string) ( $row['created_at'] ?? '' ) ),
                    'updated_at'         => sanitize_text_field( (string) ( $row['updated_at'] ?? '' ) ),
                    'capabilities'       => teinvit_order_meta_box_capabilities_for_token( $token, $context ),
                ];
            }

            return $tokens;
        }

        $legacy_token = $legacy_bridge_token;

        if ( $legacy_token === '' ) {
            return [];
        }

        $context    = teinvit_order_meta_box_context_for_token( $legacy_token );
        $active     = teinvit_order_meta_box_active_version( $legacy_token, $context );
        $pdf_status = sanitize_key( (string) $order->get_meta( '_teinvit_pdf_status' ) );
        if ( $pdf_status === '' ) {
            $pdf_status = sanitize_key( (string) ( $context['pdf_status'] ?? $active['pdf_status'] ?? 'pending' ) );
        }

        $pdf_url = esc_url_raw( (string) $order->get_meta( '_teinvit_pdf_url' ) );
        if ( $pdf_url === '' ) {
            $pdf_url = esc_url_raw( (string) ( $context['pdf_url'] ?? $active['pdf_url'] ?? '' ) );
        }

        return [
            [
                'source'             => 'legacy',
                'legacy'             => true,
                'legacy_bridge'      => true,
                'token'              => $legacy_token,
                'vertical'           => sanitize_key( (string) ( $context['vertical'] ?? 'wedding' ) ),
                'product_name'       => sanitize_text_field( (string) ( $context['product_name'] ?? '' ) ),
                'product_slug'       => sanitize_title( (string) ( $context['product_slug'] ?? '' ) ),
                'package_type'       => sanitize_key( (string) ( $context['package_type'] ?? 'unknown' ) ),
                'product_id'         => max( 0, (int) ( $context['product_id'] ?? 0 ) ),
                'variation_id'       => max( 0, (int) ( $context['variation_id'] ?? 0 ) ),
                'order_item_id'      => max( 0, (int) ( $context['order_item_id'] ?? 0 ) ),
                'quantity_index'     => max( 1, (int) ( $context['quantity_index'] ?? 1 ) ),
                'status'             => 'legacy',
                'pdf_status'         => $pdf_status !== '' ? $pdf_status : 'pending',
                'active_version_id'  => max( 0, (int) ( $active['id'] ?? 0 ) ),
                'active_version'     => sanitize_text_field( (string) ( $active['label'] ?? '' ) ),
                'version_pdf_status' => sanitize_key( (string) ( $active['pdf_status'] ?? '' ) ),
                'pdf_filename'       => sanitize_file_name( (string) ( $active['pdf_filename'] ?? ( $context['pdf_filename'] ?? '' ) ) ),
                'pdf_url'            => $pdf_url,
                'last_error'         => sanitize_textarea_field( (string) ( $context['last_error'] ?? '' ) ),
                'created_at'         => '',
                'updated_at'         => '',
                'capabilities'       => teinvit_order_meta_box_capabilities_for_token( $legacy_token, $context ),
            ],
        ];
    }
}

if ( ! function_exists( 'teinvit_order_meta_box_collect_addons' ) ) {
    function teinvit_order_meta_box_collect_addons( WC_Order $order, array $tokens ) {
        global $wpdb;

        $order_id = $order->get_id();
        if (
            ! function_exists( 'teinvit_order_token_addons_table' )
            || ! function_exists( 'teinvit_database_table_exists' )
            || ! teinvit_database_table_exists( teinvit_order_token_addons_table() )
        ) {
            return [];
        }

        $table        = teinvit_order_token_addons_table();
        $token_values = [];
        foreach ( $tokens as $token_row ) {
            $token = sanitize_text_field( (string) ( $token_row['token'] ?? '' ) );
            if ( $token !== '' ) {
                $token_values[] = $token;
            }
        }
        $token_values = array_values( array_unique( $token_values ) );

        if ( ! empty( $token_values ) ) {
            $placeholders = implode( ',', array_fill( 0, count( $token_values ), '%s' ) );
            $sql          = "SELECT * FROM {$table} WHERE order_id = %d OR target_token IN ({$placeholders}) ORDER BY updated_at DESC, id DESC";
            $prepared     = $wpdb->prepare( $sql, array_merge( [ $order_id ], $token_values ) );
        } else {
            $prepared = $wpdb->prepare( "SELECT * FROM {$table} WHERE order_id = %d ORDER BY updated_at DESC, id DESC", $order_id );
        }

        $rows = $wpdb->get_results( $prepared, ARRAY_A );
        if ( ! is_array( $rows ) ) {
            return [];
        }

        $addons = [];
        $seen   = [];
        foreach ( $rows as $row ) {
            if ( ! is_array( $row ) ) {
                continue;
            }

            $row = function_exists( 'teinvit_normalize_order_token_addon_row' )
                ? teinvit_normalize_order_token_addon_row( $row )
                : $row;

            $id = max( 0, (int) ( $row['id'] ?? 0 ) );
            if ( $id > 0 && isset( $seen[ $id ] ) ) {
                continue;
            }
            if ( $id > 0 ) {
                $seen[ $id ] = true;
            }

            $product_id   = max( 0, (int) ( $row['product_id'] ?? 0 ) );
            $variation_id = max( 0, (int) ( $row['variation_id'] ?? 0 ) );
            $product      = null;
            if ( function_exists( 'wc_get_product' ) ) {
                $product = wc_get_product( $variation_id > 0 ? $variation_id : $product_id );
            }

            $item_name     = '';
            $order_item_id = max( 0, (int) ( $row['order_item_id'] ?? 0 ) );
            if ( $order_item_id > 0 && method_exists( $order, 'get_item' ) ) {
                $item = $order->get_item( $order_item_id );
                if ( is_object( $item ) && method_exists( $item, 'get_name' ) ) {
                    $item_name = sanitize_text_field( (string) $item->get_name() );
                }
            }

            $addons[] = [
                'id'                 => $id,
                'target_token'       => sanitize_text_field( (string) ( $row['target_token'] ?? '' ) ),
                'order_id'           => max( 0, (int) ( $row['order_id'] ?? 0 ) ),
                'order_item_id'      => $order_item_id,
                'product_id'         => $product_id,
                'variation_id'       => $variation_id,
                'product_name'       => $product && method_exists( $product, 'get_name' )
                    ? sanitize_text_field( (string) $product->get_name() )
                    : $item_name,
                'addon_type'         => sanitize_key( (string) ( $row['addon_type'] ?? 'unknown' ) ),
                'vertical'           => sanitize_key( (string) ( $row['vertical'] ?? '' ) ),
                'status'             => sanitize_key( (string) ( $row['status'] ?? 'pending' ) ),
                'capability_changed' => sanitize_key( (string) ( $row['capability_changed'] ?? '' ) ),
                'error_message'      => sanitize_textarea_field( (string) ( $row['error_message'] ?? '' ) ),
                'applied_at'         => sanitize_text_field( (string) ( $row['applied_at'] ?? '' ) ),
                'created_at'         => sanitize_text_field( (string) ( $row['created_at'] ?? '' ) ),
                'updated_at'         => sanitize_text_field( (string) ( $row['updated_at'] ?? '' ) ),
            ];
        }

        return $addons;
    }
}

if ( ! function_exists( 'teinvit_order_meta_box_count_by' ) ) {
    function teinvit_order_meta_box_count_by( array $rows, $key ) {
        $counts = [];
        foreach ( $rows as $row ) {
            $value = sanitize_key( (string) ( $row[ $key ] ?? '' ) );
            if ( $value === '' ) {
                $value = 'unknown';
            }
            if ( ! isset( $counts[ $value ] ) ) {
                $counts[ $value ] = 0;
            }
            $counts[ $value ]++;
        }

        ksort( $counts );

        return $counts;
    }
}

if ( ! function_exists( 'teinvit_order_meta_box_compact_counts' ) ) {
    function teinvit_order_meta_box_compact_counts( array $counts ) {
        if ( empty( $counts ) ) {
            return '-';
        }

        $parts = [];
        foreach ( $counts as $key => $count ) {
            $parts[] = sprintf( '%s: %d', $key, (int) $count );
        }

        return implode( ', ', $parts );
    }
}

if ( ! function_exists( 'teinvit_order_meta_box_summary' ) ) {
    function teinvit_order_meta_box_summary( array $tokens, array $addons ) {
        $errors = [];
        foreach ( $tokens as $token ) {
            $message = trim( (string) ( $token['last_error'] ?? '' ) );
            if ( $message !== '' ) {
                $errors[] = sprintf( '%s: %s', $token['token'] ?? '', $message );
            }
        }
        foreach ( $addons as $addon ) {
            $message = trim( (string) ( $addon['error_message'] ?? '' ) );
            if ( $message !== '' ) {
                $errors[] = sprintf( 'addon #%d: %s', (int) ( $addon['id'] ?? 0 ), $message );
            }
        }

        return [
            'total_tokens'   => count( $tokens ),
            'verticals'      => teinvit_order_meta_box_count_by( $tokens, 'vertical' ),
            'packages'       => teinvit_order_meta_box_count_by( $tokens, 'package_type' ),
            'pdf_statuses'   => teinvit_order_meta_box_count_by( $tokens, 'pdf_status' ),
            'addon_statuses' => teinvit_order_meta_box_count_by( $addons, 'status' ),
            'legacy'         => count( array_filter( $tokens, static function ( $token ) {
                return ! empty( $token['legacy'] );
            } ) ) > 0,
            'legacy_bridge'  => count( array_filter( $tokens, static function ( $token ) {
                return ! empty( $token['legacy_bridge'] );
            } ) ) > 0,
            'addon_only'     => empty( $tokens ) && ! empty( $addons ),
            'recent_errors'  => array_slice( $errors, 0, 5 ),
        ];
    }
}

if ( ! function_exists( 'teinvit_order_meta_box_render_summary' ) ) {
    function teinvit_order_meta_box_render_summary( array $summary ) {
        ?>
        <div class="teinvit-order-summary">
            <div><strong>Total token-uri</strong><span><?php echo esc_html( (string) (int) $summary['total_tokens'] ); ?></span></div>
            <div><strong>Verticale</strong><span><?php echo esc_html( teinvit_order_meta_box_compact_counts( $summary['verticals'] ) ); ?></span></div>
            <div><strong>Pachete</strong><span><?php echo esc_html( teinvit_order_meta_box_compact_counts( $summary['packages'] ) ); ?></span></div>
            <div><strong>Status PDF</strong><span><?php echo esc_html( teinvit_order_meta_box_compact_counts( $summary['pdf_statuses'] ) ); ?></span></div>
            <div><strong>Status addon-uri</strong><span><?php echo esc_html( teinvit_order_meta_box_compact_counts( $summary['addon_statuses'] ) ); ?></span></div>
            <div><strong>Flag-uri</strong><span>
                <?php
                $flags = [];
                if ( ! empty( $summary['legacy'] ) ) {
                    $flags[] = 'legacy';
                }
                if ( ! empty( $summary['legacy_bridge'] ) ) {
                    $flags[] = 'bridge';
                }
                if ( ! empty( $summary['addon_only'] ) ) {
                    $flags[] = 'addon-only';
                }
                echo esc_html( ! empty( $flags ) ? implode( ', ', $flags ) : '-' );
                ?>
            </span></div>
        </div>
        <?php if ( ! empty( $summary['recent_errors'] ) ) : ?>
            <div class="teinvit-errors">
                <strong>Erori recente</strong>
                <ul>
                    <?php foreach ( $summary['recent_errors'] as $error ) : ?>
                        <li><?php echo esc_html( $error ); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        <?php
    }
}

if ( ! function_exists( 'teinvit_order_meta_box_token_url' ) ) {
    function teinvit_order_meta_box_token_url( $path, $token ) {
        $path  = trim( (string) $path, '/' );
        $token = sanitize_text_field( (string) $token );

        return home_url( '/' . $path . '/' . rawurlencode( $token ) . '/' );
    }
}

if ( ! function_exists( 'teinvit_order_meta_box_render_token_actions' ) ) {
    function teinvit_order_meta_box_render_token_actions( WC_Order $order, array $token ) {
        $order_id      = $order->get_id();
        $token_value   = sanitize_text_field( (string) ( $token['token'] ?? '' ) );
        $version_id    = max( 0, (int) ( $token['active_version_id'] ?? 0 ) );
        $capabilities  = isset( $token['capabilities'] ) && is_array( $token['capabilities'] ) ? $token['capabilities'] : [];
        $can_regen_pdf = $token_value !== ''
            && $version_id > 0
            && function_exists( 'teinvit_generate_pdf_for_token_version' )
            && current_user_can( 'manage_woocommerce' );

        if ( $token_value === '' ) {
            echo teinvit_order_meta_box_empty_value(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            return;
        }

        ?>
        <div class="teinvit-actions">
            <button type="button" class="button button-small teinvit-copy-token" data-token="<?php echo esc_attr( $token_value ); ?>">Copiaza</button>
            <a class="button button-small" href="<?php echo esc_url( teinvit_order_meta_box_token_url( 'i', $token_value ) ); ?>" target="_blank" rel="noopener">/i</a>
            <a class="button button-small" href="<?php echo esc_url( teinvit_order_meta_box_token_url( 'pdf', $token_value ) ); ?>" target="_blank" rel="noopener">/pdf</a>
            <a class="button button-small" href="<?php echo esc_url( teinvit_order_meta_box_token_url( 'admin-client', $token_value ) ); ?>" target="_blank" rel="noopener">/admin-client</a>
            <?php if ( teinvit_order_meta_box_can_open_invitati( $capabilities ) ) : ?>
                <a class="button button-small" href="<?php echo esc_url( teinvit_order_meta_box_token_url( 'invitati', $token_value ) ); ?>" target="_blank" rel="noopener">/invitati</a>
            <?php endif; ?>
            <?php if ( ! empty( $token['pdf_url'] ) ) : ?>
                <a class="button button-small" href="<?php echo esc_url( $token['pdf_url'] ); ?>" target="_blank" rel="noopener">PDF final</a>
            <?php endif; ?>
            <?php if ( $can_regen_pdf ) : ?>
                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                    <input type="hidden" name="action" value="teinvit_regenerate_token_pdf">
                    <input type="hidden" name="order_id" value="<?php echo esc_attr( (string) $order_id ); ?>">
                    <input type="hidden" name="token" value="<?php echo esc_attr( $token_value ); ?>">
                    <input type="hidden" name="version_id" value="<?php echo esc_attr( (string) $version_id ); ?>">
                    <?php wp_nonce_field( 'teinvit_regenerate_token_pdf_' . $order_id . '_' . $token_value . '_' . $version_id ); ?>
                    <button type="submit" class="button button-small button-secondary">Regenereaza PDF</button>
                </form>
            <?php endif; ?>
        </div>
        <?php
    }
}

if ( ! function_exists( 'teinvit_order_meta_box_render_tokens_table' ) ) {
    function teinvit_order_meta_box_render_tokens_table( WC_Order $order, array $tokens ) {
        ?>
        <h3>Token-uri comanda</h3>
        <?php if ( empty( $tokens ) ) : ?>
            <p class="teinvit-muted">Nu exista token-uri TeInvit pentru aceasta comanda.</p>
            <?php return; ?>
        <?php endif; ?>

        <div class="teinvit-table-wrap">
            <table class="widefat striped teinvit-token-table">
                <thead>
                    <tr>
                        <th>Token</th>
                        <th>Vertical / produs</th>
                        <th>Pachet</th>
                        <th>ID-uri</th>
                        <th>Status</th>
                        <th>PDF</th>
                        <th>Eroare</th>
                        <th>Timestamps</th>
                        <th>Actiuni</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $tokens as $token ) : ?>
                        <tr>
                            <td>
                                <code><?php echo esc_html( (string) ( $token['token'] ?? '' ) ); ?></code><br>
                                <span class="teinvit-muted"><?php echo esc_html( (string) ( $token['source'] ?? '' ) ); ?></span>
                                <?php if ( ! empty( $token['legacy'] ) ) : ?>
                                    <?php echo teinvit_order_meta_box_status_badge( 'legacy' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                <?php endif; ?>
                                <?php if ( ! empty( $token['legacy_bridge'] ) && empty( $token['legacy'] ) ) : ?>
                                    <?php echo teinvit_order_meta_box_status_badge( 'bridge' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php echo teinvit_order_meta_box_format_value( $token['vertical'] ?? '' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><br>
                                <strong><?php echo teinvit_order_meta_box_format_value( $token['product_name'] ?? '' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></strong><br>
                                <span class="teinvit-muted"><?php echo teinvit_order_meta_box_format_value( $token['product_slug'] ?? '' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                            </td>
                            <td><?php echo teinvit_order_meta_box_status_badge( $token['package_type'] ?? 'unknown' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
                            <td>
                                Produs: <?php echo esc_html( (string) (int) ( $token['product_id'] ?? 0 ) ); ?><br>
                                Variatie: <?php echo esc_html( (string) (int) ( $token['variation_id'] ?? 0 ) ); ?><br>
                                Item: <?php echo esc_html( (string) (int) ( $token['order_item_id'] ?? 0 ) ); ?><br>
                                Q index: <?php echo esc_html( (string) (int) ( $token['quantity_index'] ?? 1 ) ); ?>
                            </td>
                            <td>
                                Token: <?php echo teinvit_order_meta_box_status_badge( $token['status'] ?? '' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><br>
                                PDF agregat: <?php echo teinvit_order_meta_box_status_badge( $token['pdf_status'] ?? '' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><br>
                                Versiune: <?php echo teinvit_order_meta_box_format_value( $token['active_version'] ?? '' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                            </td>
                            <td>
                                Versiune PDF: <?php echo teinvit_order_meta_box_status_badge( $token['version_pdf_status'] ?? '' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><br>
                                Fisier: <?php echo teinvit_order_meta_box_format_value( $token['pdf_filename'] ?? '' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><br>
                                URL: <?php if ( ! empty( $token['pdf_url'] ) ) : ?>
                                    <a href="<?php echo esc_url( $token['pdf_url'] ); ?>" target="_blank" rel="noopener">deschide</a>
                                <?php else : ?>
                                    <?php echo teinvit_order_meta_box_empty_value(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                <?php endif; ?>
                            </td>
                            <td><?php echo teinvit_order_meta_box_format_value( $token['last_error'] ?? '' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
                            <td>
                                Creat: <?php echo teinvit_order_meta_box_format_value( $token['created_at'] ?? '' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><br>
                                Modificat: <?php echo teinvit_order_meta_box_format_value( $token['updated_at'] ?? '' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                            </td>
                            <td><?php teinvit_order_meta_box_render_token_actions( $order, $token ); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
}

if ( ! function_exists( 'teinvit_order_meta_box_render_addons_table' ) ) {
    function teinvit_order_meta_box_render_addons_table( array $addons ) {
        ?>
        <h3>Addon-uri comanda</h3>
        <?php if ( empty( $addons ) ) : ?>
            <p class="teinvit-muted">Nu exista addon-uri TeInvit pentru aceasta comanda.</p>
            <?php return; ?>
        <?php endif; ?>

        <div class="teinvit-table-wrap">
            <table class="widefat striped teinvit-addon-table">
                <thead>
                    <tr>
                        <th>Addon produs</th>
                        <th>Order / item</th>
                        <th>Target token</th>
                        <th>Vertical / tip</th>
                        <th>Status</th>
                        <th>Eroare</th>
                        <th>Timestamps</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $addons as $addon ) : ?>
                        <tr>
                            <td>
                                <strong><?php echo teinvit_order_meta_box_format_value( $addon['product_name'] ?? '' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></strong><br>
                                Produs: <?php echo esc_html( (string) (int) ( $addon['product_id'] ?? 0 ) ); ?><br>
                                Variatie: <?php echo esc_html( (string) (int) ( $addon['variation_id'] ?? 0 ) ); ?>
                            </td>
                            <td>
                                Order: <?php echo esc_html( (string) (int) ( $addon['order_id'] ?? 0 ) ); ?><br>
                                Item: <?php echo esc_html( (string) (int) ( $addon['order_item_id'] ?? 0 ) ); ?>
                            </td>
                            <td><code><?php echo esc_html( (string) ( $addon['target_token'] ?? '' ) ); ?></code></td>
                            <td>
                                <?php echo teinvit_order_meta_box_format_value( $addon['vertical'] ?? '' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><br>
                                <?php echo teinvit_order_meta_box_status_badge( $addon['addon_type'] ?? 'unknown' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                            </td>
                            <td>
                                Addon: <?php echo teinvit_order_meta_box_status_badge( $addon['status'] ?? '' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><br>
                                Capability: <?php echo teinvit_order_meta_box_format_value( $addon['capability_changed'] ?? '' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                            </td>
                            <td><?php echo teinvit_order_meta_box_format_value( $addon['error_message'] ?? '' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
                            <td>
                                Aplicat: <?php echo teinvit_order_meta_box_format_value( $addon['applied_at'] ?? '' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><br>
                                Creat: <?php echo teinvit_order_meta_box_format_value( $addon['created_at'] ?? '' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><br>
                                Modificat: <?php echo teinvit_order_meta_box_format_value( $addon['updated_at'] ?? '' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
}

if ( ! function_exists( 'teinvit_render_order_meta_box' ) ) {
    function teinvit_render_order_meta_box( $context ) {
        $order = teinvit_order_meta_box_order_from_context( $context );
        if ( ! $order ) {
            echo '<em>Order context invalid</em>';
            return;
        }

        $tokens  = teinvit_order_meta_box_collect_tokens( $order );
        $addons  = teinvit_order_meta_box_collect_addons( $order, $tokens );
        $summary = teinvit_order_meta_box_summary( $tokens, $addons );

        ?>
        <style>
            .teinvit-order-panel { line-height: 1.45; }
            .teinvit-order-summary { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 8px; margin: 8px 0 14px; }
            .teinvit-order-summary > div { border: 1px solid #dcdcde; border-radius: 4px; padding: 8px; background: #fff; }
            .teinvit-order-summary strong { display: block; margin-bottom: 4px; }
            .teinvit-table-wrap { overflow-x: auto; margin-bottom: 18px; }
            .teinvit-token-table th, .teinvit-token-table td, .teinvit-addon-table th, .teinvit-addon-table td { vertical-align: top; }
            .teinvit-muted { color: #646970; }
            .teinvit-badge { display: inline-block; padding: 1px 6px; border-radius: 3px; background: #f0f0f1; color: #1d2327; font-size: 12px; line-height: 1.7; margin-top: 2px; }
            .teinvit-badge-good { background: #edfaef; color: #0a6f23; }
            .teinvit-badge-warn { background: #fff8e5; color: #8a5a00; }
            .teinvit-badge-bad { background: #fcf0f1; color: #b32d2e; }
            .teinvit-actions { display: flex; flex-wrap: wrap; gap: 4px; align-items: center; min-width: 220px; }
            .teinvit-actions form { display: inline; margin: 0; }
            .teinvit-errors { border-left: 4px solid #d63638; padding: 8px 10px; margin: 8px 0 14px; background: #fcf0f1; }
            .teinvit-errors ul { margin: 6px 0 0 18px; }
        </style>
        <div class="teinvit-order-panel">
            <?php if ( isset( $_GET['teinvit_pdf_regenerated'] ) ) : ?>
                <div class="notice notice-success inline"><p>PDF regenerat pentru token-ul selectat.</p></div>
            <?php elseif ( isset( $_GET['teinvit_pdf_error'] ) ) : ?>
                <div class="notice notice-error inline"><p><?php echo esc_html( sanitize_text_field( wp_unslash( $_GET['teinvit_pdf_error'] ) ) ); ?></p></div>
            <?php endif; ?>

            <?php teinvit_order_meta_box_render_summary( $summary ); ?>
            <?php if ( empty( $tokens ) && empty( $addons ) ) : ?>
                <p><em>Nu exista date TeInvit pentru aceasta comanda.</em></p>
            <?php endif; ?>
            <?php teinvit_order_meta_box_render_tokens_table( $order, $tokens ); ?>
            <?php teinvit_order_meta_box_render_addons_table( $addons ); ?>
        </div>
        <script>
            (function() {
                const buttons = document.querySelectorAll('.teinvit-copy-token');
                buttons.forEach(function(button) {
                    button.addEventListener('click', function() {
                        const token = button.getAttribute('data-token') || '';
                        if (!token || !navigator.clipboard) {
                            return;
                        }
                        navigator.clipboard.writeText(token).then(function() {
                            const original = button.textContent;
                            button.textContent = 'Copiat';
                            setTimeout(function() {
                                button.textContent = original;
                            }, 1200);
                        });
                    });
                });
            }());
        </script>
        <?php
    }
}

add_action( 'add_meta_boxes', static function () {
    add_meta_box(
        'teinvit_order_box',
        'TeInvit - Invitatii si PDF',
        'teinvit_render_order_meta_box',
        'shop_order',
        'normal',
        'high'
    );
} );

add_action( 'add_meta_boxes_woocommerce_page_wc-orders', static function () {
    add_meta_box(
        'teinvit_order_box',
        'TeInvit - Invitatii si PDF',
        'teinvit_render_order_meta_box',
        null,
        'normal',
        'high'
    );
} );

add_action( 'admin_post_teinvit_regenerate_token_pdf', static function () {
    if ( ! current_user_can( 'manage_woocommerce' ) ) {
        wp_die( 'Unauthorized' );
    }

    $order_id   = isset( $_POST['order_id'] ) ? (int) $_POST['order_id'] : 0;
    $token      = isset( $_POST['token'] ) ? sanitize_text_field( wp_unslash( $_POST['token'] ) ) : '';
    $version_id = isset( $_POST['version_id'] ) ? (int) $_POST['version_id'] : 0;

    if ( $order_id <= 0 || $token === '' || $version_id <= 0 ) {
        wp_die( 'Missing PDF regeneration data.' );
    }

    check_admin_referer( 'teinvit_regenerate_token_pdf_' . $order_id . '_' . $token . '_' . $version_id );

    $order = function_exists( 'wc_get_order' ) ? wc_get_order( $order_id ) : null;
    if ( ! $order ) {
        wp_die( 'Order not found.' );
    }

    $context          = teinvit_order_meta_box_context_for_token( $token );
    $context_order_id = max( 0, (int) ( $context['order_id'] ?? 0 ) );
    $legacy_token     = sanitize_text_field( (string) $order->get_meta( '_teinvit_token' ) );
    $belongs_to_order = $context_order_id === $order_id || ( $legacy_token !== '' && hash_equals( $legacy_token, $token ) );

    if ( ! $belongs_to_order ) {
        wp_die( 'Token does not belong to this order.' );
    }

    if ( ! function_exists( 'teinvit_generate_pdf_for_token_version' ) ) {
        wp_die( 'Token PDF generator is not available.' );
    }

    $result   = teinvit_generate_pdf_for_token_version( $token, $version_id, true );
    $redirect = wp_get_referer() ? wp_get_referer() : admin_url( 'post.php?post=' . $order_id . '&action=edit' );

    if ( is_wp_error( $result ) ) {
        $message = $result->get_error_message();
        if ( method_exists( $order, 'add_order_note' ) ) {
            $order->add_order_note( sprintf( 'TeInvit: manual token PDF regeneration failed for %s v%d: %s', $token, $version_id, $message ) );
            $order->save();
        }
        wp_safe_redirect( add_query_arg( 'teinvit_pdf_error', $message, remove_query_arg( [ 'teinvit_pdf_regenerated', 'teinvit_pdf_error' ], $redirect ) ) );
        exit;
    }

    if ( method_exists( $order, 'add_order_note' ) ) {
        $filename = is_array( $result ) ? sanitize_file_name( (string) ( $result['pdf_filename'] ?? '' ) ) : '';
        $order->add_order_note( sprintf( 'TeInvit: manual token PDF regeneration requested for %s v%d%s.', $token, $version_id, $filename !== '' ? ' (' . $filename . ')' : '' ) );
        $order->save();
    }

    wp_safe_redirect( add_query_arg( 'teinvit_pdf_regenerated', '1', remove_query_arg( [ 'teinvit_pdf_regenerated', 'teinvit_pdf_error' ], $redirect ) ) );
    exit;
} );

add_action( 'admin_post_teinvit_generate_pdf', static function () {
    if ( ! current_user_can( 'manage_woocommerce' ) ) {
        wp_die( 'Unauthorized' );
    }

    $order_id = isset( $_GET['order_id'] ) ? (int) $_GET['order_id'] : 0;
    if ( $order_id <= 0 ) {
        wp_die( 'Missing order ID' );
    }

    if ( function_exists( 'teinvit_try_generate_pdf' ) ) {
        teinvit_try_generate_pdf( $order_id, true );
    }

    wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url( 'post.php?post=' . $order_id . '&action=edit' ) );
    exit;
} );
