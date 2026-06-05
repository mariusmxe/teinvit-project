<?php
if (!defined('ABSPATH')) {
    exit;
}

function teinvit_product_gallery_bulk_lock_option() {
    return 'teinvit_product_gallery_bulk_lock';
}

function teinvit_product_gallery_bulk_lock_ttl() {
    return 15 * MINUTE_IN_SECONDS;
}

function teinvit_product_gallery_bulk_verticals() {
    return array(
        'wedding' => 'Wedding',
        'birthday' => 'Birthday',
        'baptism' => 'Baptism',
    );
}

function teinvit_product_gallery_bulk_package_scopes() {
    return array(
        'premium' => 'Premium only',
        'basic' => 'Basic only',
        'both' => 'Both',
    );
}

function teinvit_product_gallery_bulk_normalize_vertical($vertical) {
    $vertical = sanitize_key($vertical);
    $verticals = teinvit_product_gallery_bulk_verticals();
    return isset($verticals[$vertical]) ? $vertical : '';
}

function teinvit_product_gallery_bulk_normalize_package_scope($scope) {
    $scope = sanitize_key($scope);
    $scopes = teinvit_product_gallery_bulk_package_scopes();
    return isset($scopes[$scope]) ? $scope : 'premium';
}

function teinvit_product_gallery_bulk_scope_roles($scope) {
    if ($scope === 'basic') {
        return array('basic_product_ids' => 'basic');
    }
    if ($scope === 'both') {
        return array(
            'basic_product_ids' => 'basic',
            'premium_native_product_ids' => 'premium',
        );
    }
    return array('premium_native_product_ids' => 'premium');
}

function teinvit_product_gallery_bulk_add_excluded(&$excluded, &$seen, $row) {
    $product_id = isset($row['product_id']) ? (int) $row['product_id'] : 0;
    $reason = isset($row['exclusion_reason']) ? (string) $row['exclusion_reason'] : 'invalid_product_context';
    $key = $product_id . '|' . $reason;
    if (isset($seen[$key])) {
        return;
    }
    $seen[$key] = true;
    $excluded[] = array(
        'product_id' => $product_id,
        'product_name' => isset($row['product_name']) ? (string) $row['product_name'] : '',
        'vertical_detected' => isset($row['vertical_detected']) ? (string) $row['vertical_detected'] : '',
        'package_type_detected' => isset($row['package_type_detected']) ? (string) $row['package_type_detected'] : '',
        'exclusion_reason' => $reason,
    );
}

function teinvit_product_gallery_bulk_product_name($product_id) {
    $product = function_exists('wc_get_product') ? wc_get_product($product_id) : null;
    if ($product) {
        return $product->get_name();
    }
    $post = get_post($product_id);
    return $post ? $post->post_title : '';
}

function teinvit_product_gallery_bulk_detect_catalog_role($product_id) {
    if (!function_exists('teinvit_get_custom_products_catalog')) {
        return array('vertical' => '', 'package_type' => '');
    }

    $catalog = teinvit_get_custom_products_catalog();
    foreach ($catalog as $vertical => $entry) {
        foreach (array('basic_product_ids' => 'basic', 'premium_native_product_ids' => 'premium') as $role_key => $package_type) {
            $ids = teinvit_catalog_role_ids($entry, $role_key);
            if (in_array((int) $product_id, $ids, true)) {
                return array('vertical' => (string) $vertical, 'package_type' => $package_type);
            }
        }
        foreach (array('premium_upgrade_addon_ids', 'extra_edits_addon_ids', 'extra_gifts_addon_ids') as $role_key) {
            $ids = teinvit_catalog_role_ids($entry, $role_key);
            if (in_array((int) $product_id, $ids, true)) {
                return array('vertical' => (string) $vertical, 'package_type' => 'addon');
            }
        }
    }

    return array('vertical' => '', 'package_type' => '');
}

function teinvit_product_gallery_bulk_last_logs_by_theme($product_id) {
    $logs = teinvit_product_gallery_logs_for_product($product_id);
    $by_theme = array();
    foreach ($logs as $log) {
        if (!empty($log['theme_key_internal'])) {
            $by_theme[(string) $log['theme_key_internal']] = $log;
        }
    }
    return $by_theme;
}

function teinvit_product_gallery_bulk_is_failed_status($status) {
    $status = (string) $status;
    return $status === 'failed' || strpos($status, 'failed') === 0;
}

function teinvit_product_gallery_bulk_pair_status_label(array $existing) {
    $status = (string) ($existing['status'] ?? 'invalid');
    $reason = (string) ($existing['reason'] ?? '');
    if ($status === 'incomplete' && $reason !== 'Fisier lipsa.') {
        return 'invalid';
    }
    return $status;
}

function teinvit_product_gallery_bulk_build_dry_run($vertical, $package_scope) {
    $vertical = teinvit_product_gallery_bulk_normalize_vertical($vertical);
    $package_scope = teinvit_product_gallery_bulk_normalize_package_scope($package_scope);
    if ($vertical === '') {
        return new WP_Error('invalid_vertical', 'Invalid vertical.');
    }
    if (!function_exists('teinvit_get_custom_products_catalog')) {
        return new WP_Error('catalog_missing', 'TeInvit custom products catalog is not available.');
    }

    $catalog = teinvit_get_custom_products_catalog();
    $entry = isset($catalog[$vertical]) && is_array($catalog[$vertical]) ? $catalog[$vertical] : array();
    $themes = teinvit_product_gallery_themes_for_vertical($vertical);
    $roles = teinvit_product_gallery_bulk_scope_roles($package_scope);
    $selected_ids = array();
    $addon_ids = array();
    $included = array();
    $excluded = array();
    $excluded_seen = array();
    $jobs = array();
    $background_missing_count = 0;

    foreach ($roles as $role_key => $package_type) {
        foreach (teinvit_catalog_role_ids($entry, $role_key) as $product_id) {
            if ($product_id > 0 && !isset($selected_ids[$product_id])) {
                $selected_ids[$product_id] = $package_type;
            }
        }
    }

    foreach (array('basic_product_ids' => 'basic', 'premium_native_product_ids' => 'premium') as $role_key => $package_type) {
        if (isset($roles[$role_key])) {
            continue;
        }
        foreach (teinvit_catalog_role_ids($entry, $role_key) as $product_id) {
            if ($product_id <= 0 || isset($selected_ids[$product_id])) {
                continue;
            }
            teinvit_product_gallery_bulk_add_excluded($excluded, $excluded_seen, array(
                'product_id' => $product_id,
                'product_name' => teinvit_product_gallery_bulk_product_name($product_id),
                'vertical_detected' => $vertical,
                'package_type_detected' => $package_type,
                'exclusion_reason' => 'not_selected_package_scope',
            ));
        }
    }

    foreach (array('premium_upgrade_addon_ids', 'extra_edits_addon_ids', 'extra_gifts_addon_ids') as $role_key) {
        foreach (teinvit_catalog_role_ids($entry, $role_key) as $product_id) {
            if ($product_id > 0) {
                $addon_ids[$product_id] = true;
                teinvit_product_gallery_bulk_add_excluded($excluded, $excluded_seen, array(
                    'product_id' => $product_id,
                    'product_name' => teinvit_product_gallery_bulk_product_name($product_id),
                    'vertical_detected' => $vertical,
                    'package_type_detected' => 'addon',
                    'exclusion_reason' => 'addon_product',
                ));
            }
        }
    }

    foreach ($selected_ids as $product_id => $expected_package_type) {
        $product_id = (int) $product_id;
        $detected = teinvit_product_gallery_bulk_detect_catalog_role($product_id);
        $product_name = teinvit_product_gallery_bulk_product_name($product_id);

        if (isset($addon_ids[$product_id])) {
            teinvit_product_gallery_bulk_add_excluded($excluded, $excluded_seen, array(
                'product_id' => $product_id,
                'product_name' => $product_name,
                'vertical_detected' => $detected['vertical'] ?: $vertical,
                'package_type_detected' => 'addon',
                'exclusion_reason' => 'addon_product',
            ));
            continue;
        }

        $product = function_exists('wc_get_product') ? wc_get_product($product_id) : null;
        if (!$product) {
            teinvit_product_gallery_bulk_add_excluded($excluded, $excluded_seen, array(
                'product_id' => $product_id,
                'product_name' => $product_name,
                'vertical_detected' => $detected['vertical'],
                'package_type_detected' => $detected['package_type'],
                'exclusion_reason' => 'product_not_found',
            ));
            continue;
        }

        if (get_post_status($product_id) !== 'publish') {
            teinvit_product_gallery_bulk_add_excluded($excluded, $excluded_seen, array(
                'product_id' => $product_id,
                'product_name' => $product_name,
                'vertical_detected' => $detected['vertical'],
                'package_type_detected' => $detected['package_type'],
                'exclusion_reason' => 'not_published',
            ));
            continue;
        }

        $context = teinvit_product_gallery_detect_product_context($product_id);
        if (is_wp_error($context)) {
            teinvit_product_gallery_bulk_add_excluded($excluded, $excluded_seen, array(
                'product_id' => $product_id,
                'product_name' => $product_name,
                'vertical_detected' => $detected['vertical'],
                'package_type_detected' => $detected['package_type'],
                'exclusion_reason' => $detected['vertical'] ? 'invalid_product_context' : 'not_teinvit_product',
            ));
            continue;
        }

        if (($context['vertical'] ?? '') !== $vertical) {
            teinvit_product_gallery_bulk_add_excluded($excluded, $excluded_seen, array(
                'product_id' => $product_id,
                'product_name' => $product_name,
                'vertical_detected' => (string) ($context['vertical'] ?? $detected['vertical']),
                'package_type_detected' => (string) ($context['package_type'] ?? $detected['package_type']),
                'exclusion_reason' => 'not_selected_vertical',
            ));
            continue;
        }

        if ($package_scope !== 'both' && ($context['package_type'] ?? '') !== $expected_package_type) {
            teinvit_product_gallery_bulk_add_excluded($excluded, $excluded_seen, array(
                'product_id' => $product_id,
                'product_name' => $product_name,
                'vertical_detected' => (string) ($context['vertical'] ?? ''),
                'package_type_detected' => (string) ($context['package_type'] ?? ''),
                'exclusion_reason' => 'not_selected_package_scope',
            ));
            continue;
        }

        $background = teinvit_product_gallery_background_details_for_product($product_id);
        if (is_wp_error($background)) {
            $background_missing_count++;
            teinvit_product_gallery_bulk_add_excluded($excluded, $excluded_seen, array(
                'product_id' => $product_id,
                'product_name' => $product_name,
                'vertical_detected' => (string) ($context['vertical'] ?? ''),
                'package_type_detected' => (string) ($context['package_type'] ?? ''),
                'exclusion_reason' => 'background_missing',
            ));
            continue;
        }

        $logs_by_theme = teinvit_product_gallery_bulk_last_logs_by_theme($product_id);
        $product_counts = array(
            'theme_count' => count($themes),
            'complete_count' => 0,
            'missing_count' => 0,
            'incomplete_count' => 0,
            'invalid_count' => 0,
            'failed_count' => 0,
            'planned_jobs_count' => 0,
        );

        foreach ($themes as $theme) {
            $theme_key = (string) $theme['theme_key_internal'];
            $existing = teinvit_product_gallery_existing_pair_status($context, $theme);
            $existing_pair = teinvit_product_gallery_bulk_pair_status_label($existing);
            $last_log = isset($logs_by_theme[$theme_key]) ? $logs_by_theme[$theme_key] : array();
            $last_log_status = isset($last_log['status']) ? (string) $last_log['status'] : '';
            $is_failed = teinvit_product_gallery_bulk_is_failed_status($last_log_status);
            $planned_action = $existing_pair === 'complete' ? 'skip' : 'generate';

            if (isset($product_counts[$existing_pair . '_count'])) {
                $product_counts[$existing_pair . '_count']++;
            }
            if ($is_failed) {
                $product_counts['failed_count']++;
            }
            if ($planned_action === 'generate') {
                $product_counts['planned_jobs_count']++;
            }

            $jobs[] = array(
                'product_id' => $product_id,
                'product_name' => (string) ($context['product_name'] ?? $product_name),
                'product_slug' => (string) ($context['product_slug'] ?? ''),
                'gallery_file_base_slug' => (string) ($context['gallery_file_base_slug'] ?? ''),
                'vertical' => $vertical,
                'package_type' => (string) ($context['package_type'] ?? ''),
                'background_status' => 'ok',
                'theme_key_internal' => $theme_key,
                'theme_label' => (string) $theme['label'],
                'theme_file_key' => (string) $theme['file_key'],
                'existing_pair' => $existing_pair,
                'last_log_status' => $last_log_status,
                'last_error_code' => isset($last_log['error_code']) ? (string) $last_log['error_code'] : '',
                'planned_action' => $planned_action,
                'retry_eligible' => $is_failed && $existing_pair !== 'complete',
                'regenerate_eligible' => true,
            );
        }

        $included[] = array(
            'product_id' => $product_id,
            'product_name' => (string) ($context['product_name'] ?? $product_name),
            'product_slug' => (string) ($context['product_slug'] ?? ''),
            'gallery_file_base_slug' => (string) ($context['gallery_file_base_slug'] ?? ''),
            'vertical' => $vertical,
            'package_type' => (string) ($context['package_type'] ?? ''),
            'background_status' => 'ok',
            'theme_count' => $product_counts['theme_count'],
            'complete_count' => $product_counts['complete_count'],
            'missing_count' => $product_counts['missing_count'],
            'incomplete_count' => $product_counts['incomplete_count'],
            'invalid_count' => $product_counts['invalid_count'],
            'failed_count' => $product_counts['failed_count'],
            'planned_jobs_count' => $product_counts['planned_jobs_count'],
        );
    }

    $summary = array(
        'vertical' => $vertical,
        'package_scope' => $package_scope,
        'eligible_products' => count($included),
        'excluded_products' => count($excluded),
        'products_without_background' => $background_missing_count,
        'theme_count_per_product' => count($themes),
        'total_theme_pairs' => count($jobs),
        'complete_pairs' => 0,
        'missing_pairs' => 0,
        'incomplete_pairs' => 0,
        'invalid_pairs' => 0,
        'failed_existing' => 0,
        'job_count' => 0,
        'retry_failed_count' => 0,
        'regenerate_jobs_count' => count($jobs),
        'estimated_files_generated' => 0,
    );

    foreach ($jobs as $job) {
        if ($job['existing_pair'] === 'complete') {
            $summary['complete_pairs']++;
        } elseif ($job['existing_pair'] === 'missing') {
            $summary['missing_pairs']++;
        } elseif ($job['existing_pair'] === 'incomplete') {
            $summary['incomplete_pairs']++;
        } elseif ($job['existing_pair'] === 'invalid') {
            $summary['invalid_pairs']++;
        }
        if (teinvit_product_gallery_bulk_is_failed_status($job['last_log_status'])) {
            $summary['failed_existing']++;
        }
        if ($job['planned_action'] === 'generate') {
            $summary['job_count']++;
        }
        if (!empty($job['retry_eligible'])) {
            $summary['retry_failed_count']++;
        }
    }
    $summary['estimated_files_generated'] = $summary['job_count'] * 2;

    return array(
        'vertical' => $vertical,
        'vertical_label' => teinvit_product_gallery_bulk_verticals()[$vertical],
        'package_scope' => $package_scope,
        'package_scope_label' => teinvit_product_gallery_bulk_package_scopes()[$package_scope],
        'summary' => $summary,
        'included_products' => $included,
        'excluded_products' => $excluded,
        'jobs' => $jobs,
        'lock' => teinvit_product_gallery_bulk_public_lock(),
    );
}

function teinvit_product_gallery_bulk_lock_expired($lock) {
    if (!is_array($lock) || empty($lock)) {
        return false;
    }
    $heartbeat = isset($lock['heartbeat_ts']) ? (int) $lock['heartbeat_ts'] : 0;
    if ($heartbeat <= 0 && !empty($lock['heartbeat_at'])) {
        $heartbeat = strtotime((string) $lock['heartbeat_at']);
    }
    return $heartbeat > 0 && (time() - $heartbeat) > teinvit_product_gallery_bulk_lock_ttl();
}

function teinvit_product_gallery_bulk_public_lock() {
    $lock = get_option(teinvit_product_gallery_bulk_lock_option(), array());
    if (!is_array($lock) || empty($lock)) {
        return array('active' => false);
    }
    $lock['active'] = true;
    $lock['expired'] = teinvit_product_gallery_bulk_lock_expired($lock);
    return $lock;
}

function teinvit_product_gallery_bulk_acquire_lock($vertical, $package_scope, $mode) {
    $existing = get_option(teinvit_product_gallery_bulk_lock_option(), array());
    if (is_array($existing) && !empty($existing)) {
        if (teinvit_product_gallery_bulk_lock_expired($existing)) {
            return new WP_Error('bulk_lock_expired', 'A previous bulk lock appears abandoned and must be released first.', array('lock' => teinvit_product_gallery_bulk_public_lock()));
        }
        return new WP_Error('bulk_lock_active', 'A bulk generation is already in progress.', array('lock' => teinvit_product_gallery_bulk_public_lock()));
    }

    $now = time();
    $lock = array(
        'job_id' => wp_generate_uuid4(),
        'user_id' => get_current_user_id(),
        'vertical' => $vertical,
        'package_scope' => $package_scope,
        'mode' => $mode,
        'started_at' => current_time('mysql'),
        'started_ts' => $now,
        'heartbeat_at' => current_time('mysql'),
        'heartbeat_ts' => $now,
        'expires_at' => date_i18n('Y-m-d H:i:s', $now + teinvit_product_gallery_bulk_lock_ttl()),
        'expires_ts' => $now + teinvit_product_gallery_bulk_lock_ttl(),
    );

    $created = add_option(teinvit_product_gallery_bulk_lock_option(), $lock, '', 'no');
    if (!$created) {
        return new WP_Error('bulk_lock_active', 'A bulk generation is already in progress.', array('lock' => teinvit_product_gallery_bulk_public_lock()));
    }

    return $lock;
}

function teinvit_product_gallery_bulk_touch_lock($job_id) {
    $lock = get_option(teinvit_product_gallery_bulk_lock_option(), array());
    if (!is_array($lock) || empty($lock)) {
        return new WP_Error('bulk_lock_missing', 'Bulk lock is missing.');
    }
    if ((string) ($lock['job_id'] ?? '') !== (string) $job_id) {
        return new WP_Error('bulk_lock_mismatch', 'Bulk lock does not match this job.');
    }
    $now = time();
    $lock['heartbeat_at'] = current_time('mysql');
    $lock['heartbeat_ts'] = $now;
    $lock['expires_at'] = date_i18n('Y-m-d H:i:s', $now + teinvit_product_gallery_bulk_lock_ttl());
    $lock['expires_ts'] = $now + teinvit_product_gallery_bulk_lock_ttl();
    update_option(teinvit_product_gallery_bulk_lock_option(), $lock, false);
    return $lock;
}

function teinvit_product_gallery_bulk_release_lock($job_id = '', $force_expired = false) {
    $lock = get_option(teinvit_product_gallery_bulk_lock_option(), array());
    if (!is_array($lock) || empty($lock)) {
        return true;
    }
    if ($job_id !== '' && (string) ($lock['job_id'] ?? '') === (string) $job_id) {
        delete_option(teinvit_product_gallery_bulk_lock_option());
        return true;
    }
    if ($force_expired && teinvit_product_gallery_bulk_lock_expired($lock)) {
        delete_option(teinvit_product_gallery_bulk_lock_option());
        return true;
    }
    return new WP_Error('bulk_lock_not_released', 'The active bulk lock was not released.');
}

function teinvit_product_gallery_bulk_ajax_check() {
    check_ajax_referer('teinvit_product_gallery_admin', 'nonce');
    if (!current_user_can(teinvit_product_gallery_admin_capability())) {
        wp_send_json_error(array('message' => 'Permission denied.'), 403);
    }
}

function teinvit_product_gallery_bulk_ajax_dry_run() {
    teinvit_product_gallery_bulk_ajax_check();
    $vertical = isset($_POST['vertical']) ? teinvit_product_gallery_bulk_normalize_vertical(wp_unslash($_POST['vertical'])) : '';
    $package_scope = isset($_POST['package_scope']) ? teinvit_product_gallery_bulk_normalize_package_scope(wp_unslash($_POST['package_scope'])) : 'premium';
    $dry_run = teinvit_product_gallery_bulk_build_dry_run($vertical, $package_scope);
    if (is_wp_error($dry_run)) {
        wp_send_json_error(array('message' => $dry_run->get_error_message(), 'code' => $dry_run->get_error_code()), 400);
    }
    wp_send_json_success($dry_run);
}
add_action('wp_ajax_teinvit_product_gallery_bulk_dry_run', 'teinvit_product_gallery_bulk_ajax_dry_run');

function teinvit_product_gallery_bulk_ajax_start() {
    teinvit_product_gallery_bulk_ajax_check();
    $vertical = isset($_POST['vertical']) ? teinvit_product_gallery_bulk_normalize_vertical(wp_unslash($_POST['vertical'])) : '';
    $package_scope = isset($_POST['package_scope']) ? teinvit_product_gallery_bulk_normalize_package_scope(wp_unslash($_POST['package_scope'])) : 'premium';
    $mode = isset($_POST['mode']) ? sanitize_key(wp_unslash($_POST['mode'])) : 'missing';
    if ($vertical === '' || !in_array($mode, array('missing', 'regenerate'), true)) {
        wp_send_json_error(array('message' => 'Invalid bulk start request.'), 400);
    }
    $lock = teinvit_product_gallery_bulk_acquire_lock($vertical, $package_scope, $mode);
    if (is_wp_error($lock)) {
        wp_send_json_error(array(
            'message' => $lock->get_error_message(),
            'code' => $lock->get_error_code(),
            'lock' => $lock->get_error_data('lock'),
        ), 409);
    }
    wp_send_json_success(array('job_id' => $lock['job_id'], 'lock' => $lock));
}
add_action('wp_ajax_teinvit_product_gallery_bulk_start', 'teinvit_product_gallery_bulk_ajax_start');

function teinvit_product_gallery_bulk_ajax_step() {
    teinvit_product_gallery_bulk_ajax_check();
    $job_id = isset($_POST['job_id']) ? sanitize_text_field(wp_unslash($_POST['job_id'])) : '';
    $product_id = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;
    $theme_key = isset($_POST['theme_key']) ? sanitize_key(wp_unslash($_POST['theme_key'])) : '';
    $mode = isset($_POST['mode']) ? sanitize_key(wp_unslash($_POST['mode'])) : 'missing';
    if ($job_id === '' || $product_id <= 0 || $theme_key === '' || !in_array($mode, array('missing', 'regenerate'), true)) {
        wp_send_json_error(array('message' => 'Invalid bulk step request.'), 400);
    }
    $lock = teinvit_product_gallery_bulk_touch_lock($job_id);
    if (is_wp_error($lock)) {
        wp_send_json_error(array('message' => $lock->get_error_message(), 'code' => $lock->get_error_code()), 409);
    }

    $started = microtime(true);
    $result = teinvit_product_gallery_generate_theme($product_id, $theme_key, $mode);
    teinvit_product_gallery_bulk_touch_lock($job_id);
    $duration_ms = (int) round((microtime(true) - $started) * 1000);
    $context = teinvit_product_gallery_detect_product_context($product_id);
    $row = array();
    if (!is_wp_error($context)) {
        foreach (teinvit_product_gallery_admin_theme_rows($context) as $theme_row) {
            if ((string) ($theme_row['theme_key_internal'] ?? '') === $theme_key) {
                $row = $theme_row;
                break;
            }
        }
    }

    if (is_wp_error($result)) {
        wp_send_json_success(array(
            'job_success' => false,
            'status' => 'failed',
            'error_code' => $result->get_error_code(),
            'error_message' => $result->get_error_message(),
            'duration_ms' => $duration_ms,
            'row' => $row,
        ));
    }

    wp_send_json_success(array(
        'job_success' => true,
        'status' => (string) ($result['status'] ?? 'success'),
        'error_code' => (string) ($result['error_code'] ?? ''),
        'error_message' => (string) ($result['error_message'] ?? ''),
        'duration_ms' => $duration_ms,
        'row' => $row,
        'result' => $result,
    ));
}
add_action('wp_ajax_teinvit_product_gallery_bulk_step', 'teinvit_product_gallery_bulk_ajax_step');

function teinvit_product_gallery_bulk_ajax_release_lock() {
    teinvit_product_gallery_bulk_ajax_check();
    $job_id = isset($_POST['job_id']) ? sanitize_text_field(wp_unslash($_POST['job_id'])) : '';
    $force_expired = !empty($_POST['force_expired']);
    $released = teinvit_product_gallery_bulk_release_lock($job_id, $force_expired);
    if (is_wp_error($released)) {
        wp_send_json_error(array('message' => $released->get_error_message(), 'code' => $released->get_error_code(), 'lock' => teinvit_product_gallery_bulk_public_lock()), 409);
    }
    wp_send_json_success(array('released' => true, 'lock' => teinvit_product_gallery_bulk_public_lock()));
}
add_action('wp_ajax_teinvit_product_gallery_bulk_release_lock', 'teinvit_product_gallery_bulk_ajax_release_lock');
