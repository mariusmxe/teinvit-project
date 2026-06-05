<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function teinvit_product_gallery_admin_register_page() {
    $parent = function_exists( 'teinvit_admin_root_slug' ) ? teinvit_admin_root_slug() : 'teinvit-admin-hub';

    add_submenu_page(
        $parent,
        'Product Gallery Images',
        'Product Gallery Images',
        teinvit_product_gallery_admin_capability(),
        'teinvit-product-gallery-images',
        'teinvit_product_gallery_render_admin_page'
    );
}
add_action( 'admin_menu', 'teinvit_product_gallery_admin_register_page', 70 );

function teinvit_product_gallery_admin_theme_rows( array $product_context ) {
    $logs = teinvit_product_gallery_logs_for_product( $product_context['product_id'] );
    $logs_by_theme = [];
    foreach ( $logs as $log ) {
        $logs_by_theme[ (string) ( $log['theme_key_internal'] ?? '' ) ] = $log;
    }

    $rows = [];
    foreach ( teinvit_product_gallery_themes_for_vertical( $product_context['vertical'] ) as $theme ) {
        $existing = teinvit_product_gallery_existing_pair_status( $product_context, $theme );
        $log = $logs_by_theme[ $theme['theme_key_internal'] ] ?? [];
        $status = (string) ( $log['status'] ?? '' );
        if ( $status === '' ) {
            $status = $existing['status'] === 'complete' ? 'success' : 'pending';
        }
        $rows[] = [
            'theme_key_internal' => $theme['theme_key_internal'],
            'theme_label'        => $theme['label'],
            'theme_file_key'     => $theme['file_key'],
            'css_class'          => $theme['css_class'],
            'status'             => $status,
            'existing_pair'      => $existing['status'],
            'error_code'         => (string) ( $log['error_code'] ?? '' ),
            'error_message'      => (string) ( $log['error_message'] ?? '' ),
            'png_path'           => (string) ( $log['png_path'] ?? ( $existing['paths']['png'] ?? '' ) ),
            'webp_path'          => (string) ( $log['webp_path'] ?? ( $existing['paths']['webp'] ?? '' ) ),
            'attempt_count'      => (int) ( $log['attempt_count'] ?? 0 ),
            'updated_at'         => (string) ( $log['updated_at'] ?? '' ),
        ];
    }

    return $rows;
}

function teinvit_product_gallery_ajax_prepare_product() {
    check_ajax_referer( 'teinvit_product_gallery_admin', 'nonce' );
    if ( ! current_user_can( teinvit_product_gallery_admin_capability() ) ) {
        wp_send_json_error( [ 'message' => 'Unauthorized' ], 403 );
    }

    $product_id = isset( $_POST['product_id'] ) ? absint( wp_unslash( $_POST['product_id'] ) ) : 0;
    $product_context = teinvit_product_gallery_detect_product_context( $product_id );
    if ( is_wp_error( $product_context ) ) {
        wp_send_json_error( [ 'message' => $product_context->get_error_message(), 'code' => $product_context->get_error_code() ], 400 );
    }

    $background = teinvit_product_gallery_background_details_for_product( $product_context['product_id'] );
    wp_send_json_success( [
        'product' => [
            'product_id'               => $product_context['product_id'],
            'product_name'             => $product_context['product_name'],
            'product_slug'             => $product_context['product_slug'],
            'gallery_file_base_slug'   => $product_context['gallery_file_base_slug'],
            'vertical'                 => $product_context['vertical'],
            'package_type'             => $product_context['package_type'],
            'background_attachment_id' => $background['background_attachment_id'],
            'background_url'           => $background['expected_background_url'],
            'background_valid'         => ! empty( $background['background_attachment_id'] ) && ! empty( $background['expected_background_url'] ),
        ],
        'themes' => teinvit_product_gallery_admin_theme_rows( $product_context ),
    ] );
}
add_action( 'wp_ajax_teinvit_product_gallery_prepare_product', 'teinvit_product_gallery_ajax_prepare_product' );

function teinvit_product_gallery_ajax_generate_theme() {
    check_ajax_referer( 'teinvit_product_gallery_admin', 'nonce' );
    if ( ! current_user_can( teinvit_product_gallery_admin_capability() ) ) {
        wp_send_json_error( [ 'message' => 'Unauthorized' ], 403 );
    }

    $product_id = isset( $_POST['product_id'] ) ? absint( wp_unslash( $_POST['product_id'] ) ) : 0;
    $theme_key = isset( $_POST['theme_key'] ) ? sanitize_key( wp_unslash( $_POST['theme_key'] ) ) : '';
    $mode = isset( $_POST['mode'] ) && sanitize_key( wp_unslash( $_POST['mode'] ) ) === 'regenerate' ? 'regenerate' : 'missing';

    $result = teinvit_product_gallery_generate_theme( $product_id, $theme_key, $mode );
    $product_context = teinvit_product_gallery_detect_product_context( $product_id );
    if ( is_wp_error( $product_context ) ) {
        wp_send_json_error( [ 'message' => $product_context->get_error_message(), 'code' => $product_context->get_error_code() ], 400 );
    }

    $rows = teinvit_product_gallery_admin_theme_rows( $product_context );
    $row = null;
    foreach ( $rows as $candidate ) {
        if ( (string) $candidate['theme_key_internal'] === $theme_key ) {
            $row = $candidate;
            break;
        }
    }

    if ( is_wp_error( $result ) ) {
        wp_send_json_error( [
            'message' => $result->get_error_message(),
            'code'    => $result->get_error_code(),
            'row'     => $row,
        ], 200 );
    }

    wp_send_json_success( [
        'result' => $result,
        'row'    => $row,
    ] );
}
add_action( 'wp_ajax_teinvit_product_gallery_generate_theme', 'teinvit_product_gallery_ajax_generate_theme' );

function teinvit_product_gallery_render_admin_page() {
    if ( ! current_user_can( teinvit_product_gallery_admin_capability() ) ) {
        wp_die( 'Unauthorized' );
    }

    $nonce = wp_create_nonce( 'teinvit_product_gallery_admin' );

    echo '<div class="wrap teinvit-product-gallery-admin">';
    echo '<h1>Product Gallery Images</h1>';
    echo '<p>Genereaza imagini demonstrative PNG si WebP pentru galeriile produselor, individual sau controlat pe verticala.</p>';
    echo '<div class="notice notice-warning inline"><p>Nu se creeaza attachment-uri Media Library si nu se modifica galeria WooCommerce.</p></div>';

    echo '<div style="display:flex;gap:12px;align-items:flex-end;margin:20px 0;">';
    echo '<label><strong>Product ID</strong><br><input type="number" min="1" id="teinvit-gallery-product-id" class="regular-text" style="width:180px;"></label>';
    echo '<button type="button" class="button button-primary" id="teinvit-gallery-load-product">Load product</button>';
    echo '</div>';

    echo '<div id="teinvit-gallery-product-summary" style="margin:16px 0;"></div>';
    echo '<div id="teinvit-gallery-actions" style="display:none;margin:16px 0;">';
    echo '<label><strong>Theme</strong><br><select id="teinvit-gallery-theme-select"></select></label> ';
    echo '<button type="button" class="button button-primary" id="teinvit-gallery-generate-theme">Generate missing/incomplete selected theme</button> ';
    echo '<button type="button" class="button" id="teinvit-gallery-regenerate-theme">Regenerate selected theme</button> ';
    echo '<button type="button" class="button" id="teinvit-gallery-generate-all">Generate missing/incomplete all themes</button> ';
    echo '<button type="button" class="button" id="teinvit-gallery-retry-failed">Retry failed</button> ';
    echo '<button type="button" class="button button-secondary" id="teinvit-gallery-regenerate-product">Regenerate selected product</button>';
    echo '<div id="teinvit-gallery-progress" style="margin-top:10px;"></div>';
    echo '</div>';

    echo '<table class="widefat striped" id="teinvit-gallery-themes-table" style="display:none;max-width:1200px;">';
    echo '<thead><tr><th>Theme</th><th>File key</th><th>Status</th><th>Existing pair</th><th>Attempts</th><th>Error</th><th>PNG</th><th>WebP</th><th>Updated</th></tr></thead><tbody></tbody>';
    echo '</table>';

    echo '<hr style="margin:32px 0;">';
    echo '<h2>Bulk Generation</h2>';
    echo '<p>Genereaza controlat pe verticala, cu dry run obligatoriu. Fiecare request proceseaza exact 1 produs + 1 tema.</p>';
    echo '<div style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap;margin:16px 0;">';
    echo '<label><strong>Vertical</strong><br><select id="teinvit-gallery-bulk-vertical"><option value="wedding">Wedding</option><option value="birthday">Birthday</option><option value="baptism">Baptism</option></select></label>';
    echo '<label><strong>Package scope</strong><br><select id="teinvit-gallery-bulk-scope"><option value="premium" selected>Premium only</option><option value="basic">Basic only</option><option value="both">Both</option></select></label>';
    echo '<button type="button" class="button button-primary" id="teinvit-gallery-bulk-dry-run">Dry run</button>';
    echo '</div>';
    echo '<div id="teinvit-gallery-bulk-lock" style="margin:12px 0;"></div>';
    echo '<div id="teinvit-gallery-bulk-summary" style="margin:12px 0;"></div>';
    echo '<div id="teinvit-gallery-bulk-actions" style="display:none;margin:16px 0;padding:12px;border:1px solid #dcdcde;background:#fff;">';
    echo '<button type="button" class="button button-primary" id="teinvit-gallery-bulk-start-missing" disabled>Generate missing/incomplete only</button> ';
    echo '<button type="button" class="button" id="teinvit-gallery-bulk-retry-failed" disabled>Retry failed</button> ';
    echo '<button type="button" class="button" id="teinvit-gallery-bulk-stop" disabled>Stop after current request</button>';
    echo '<div style="margin-top:16px;padding-top:12px;border-top:1px solid #dcdcde;">';
    echo '<h3 style="margin:0 0 10px;">Regenerate existing</h3>';
    echo '<label><input type="checkbox" id="teinvit-gallery-bulk-regenerate-check"> I understand this overwrites existing gallery images atomically</label><br>';
    echo '<label style="display:block;margin-top:8px;">Type confirmation <code id="teinvit-gallery-bulk-regenerate-expected">REGENERATE WEDDING</code><br><input type="text" id="teinvit-gallery-bulk-regenerate-text" class="regular-text"></label>';
    echo '<button type="button" class="button button-secondary" id="teinvit-gallery-bulk-start-regenerate" disabled style="margin-top:8px;">Regenerate existing</button>';
    echo '</div>';
    echo '</div>';
    echo '<div id="teinvit-gallery-bulk-progress" style="margin:12px 0;"></div>';
    echo '<div id="teinvit-gallery-bulk-included-wrap" style="display:none;margin-top:16px;"><h3>Included products</h3><table class="widefat striped" id="teinvit-gallery-bulk-included-table"><thead><tr><th>Product ID</th><th>Name</th><th>Slug</th><th>Gallery base slug</th><th>Vertical</th><th>Package</th><th>Background</th><th>Themes</th><th>Complete</th><th>Missing</th><th>Incomplete</th><th>Invalid</th><th>Failed</th><th>Planned jobs</th></tr></thead><tbody></tbody></table></div>';
    echo '<div id="teinvit-gallery-bulk-excluded-wrap" style="display:none;margin-top:16px;"><h3>Excluded products</h3><table class="widefat striped" id="teinvit-gallery-bulk-excluded-table"><thead><tr><th>Product ID</th><th>Name</th><th>Vertical detected</th><th>Package detected</th><th>Reason</th></tr></thead><tbody></tbody></table></div>';
    echo '<div id="teinvit-gallery-bulk-results-wrap" style="display:none;margin-top:16px;"><h3>Bulk results</h3><table class="widefat striped" id="teinvit-gallery-bulk-results-table"><thead><tr><th>Product ID</th><th>Name</th><th>Theme</th><th>Action</th><th>Result</th><th>Error</th><th>Duration</th></tr></thead><tbody></tbody></table></div>';

    echo '<script>';
    echo 'window.TEINVIT_PRODUCT_GALLERY_ADMIN=' . wp_json_encode( [
        'ajax_url' => admin_url( 'admin-ajax.php' ),
        'nonce'    => $nonce,
    ] ) . ';';
    echo <<<'JS'
(function(){
  var cfg = window.TEINVIT_PRODUCT_GALLERY_ADMIN || {};
  var state = { product: null, themes: [], bulk: { dryRun: null, jobs: [], jobId: '', running: false, stopRequested: false, results: [] } };
  function qs(sel){ return document.querySelector(sel); }
  function setProgress(msg){ var el = qs('#teinvit-gallery-progress'); if (el) el.textContent = msg || ''; }
  function statusClass(status){
    if (status === 'success' || status === 'skipped_existing') return 'color:#137333;font-weight:600;';
    if (String(status || '').indexOf('failed') === 0 || status === 'failed') return 'color:#b3261e;font-weight:600;';
    if (status === 'processing') return 'color:#8a5a00;font-weight:600;';
    return '';
  }
  function request(action, data){
    var body = new URLSearchParams();
    body.set('action', action);
    body.set('nonce', cfg.nonce || '');
    Object.keys(data || {}).forEach(function(k){ body.set(k, data[k]); });
    return fetch(cfg.ajax_url, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
      body: body.toString()
    }).then(function(r){ return r.json(); });
  }
  function renderSummary(){
    var box = qs('#teinvit-gallery-product-summary');
    if (!box || !state.product) { if (box) box.innerHTML = ''; return; }
    var p = state.product;
    box.innerHTML =
      '<table class="widefat striped" style="max-width:720px;"><tbody>' +
      '<tr><th>Product</th><td>' + esc(p.product_name) + ' (#' + esc(p.product_id) + ')</td></tr>' +
      '<tr><th>Vertical</th><td><code>' + esc(p.vertical) + '</code></td></tr>' +
      '<tr><th>Product slug</th><td><code>' + esc(p.product_slug) + '</code></td></tr>' +
      '<tr><th>Gallery base slug</th><td><code>' + esc(p.gallery_file_base_slug) + '</code></td></tr>' +
      '<tr><th>Background</th><td>' + (p.background_valid ? 'OK attachment #' + esc(p.background_attachment_id) : '<strong style="color:#b3261e;">missing</strong>') + '</td></tr>' +
      '</tbody></table>';
  }
  function esc(v){
    return String(v == null ? '' : v).replace(/[&<>"']/g, function(c){ return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]; });
  }
  function renderThemes(){
    var table = qs('#teinvit-gallery-themes-table');
    var tbody = table ? table.querySelector('tbody') : null;
    var select = qs('#teinvit-gallery-theme-select');
    if (!table || !tbody || !select) return;
    tbody.innerHTML = '';
    select.innerHTML = '';
    state.themes.forEach(function(t){
      var opt = document.createElement('option');
      opt.value = t.theme_key_internal;
      opt.textContent = t.theme_label + ' (' + t.theme_file_key + ')';
      select.appendChild(opt);
      var tr = document.createElement('tr');
      tr.setAttribute('data-theme', t.theme_key_internal);
      tr.innerHTML =
        '<td>' + esc(t.theme_label) + '<br><code>' + esc(t.theme_key_internal) + '</code></td>' +
        '<td><code>' + esc(t.theme_file_key) + '</code></td>' +
        '<td style="' + statusClass(t.status) + '">' + esc(t.status) + '</td>' +
        '<td>' + esc(t.existing_pair) + '</td>' +
        '<td>' + esc(t.attempt_count) + '</td>' +
        '<td><code>' + esc(t.error_code) + '</code><br>' + esc(t.error_message) + '</td>' +
        '<td><small>' + esc(t.png_path) + '</small></td>' +
        '<td><small>' + esc(t.webp_path) + '</small></td>' +
        '<td>' + esc(t.updated_at) + '</td>';
      tbody.appendChild(tr);
    });
    table.style.display = state.themes.length ? '' : 'none';
    var actions = qs('#teinvit-gallery-actions');
    if (actions) actions.style.display = state.themes.length ? '' : 'none';
  }
  function updateRow(row){
    if (!row) return;
    state.themes = state.themes.map(function(t){ return t.theme_key_internal === row.theme_key_internal ? row : t; });
    renderThemes();
  }
  function loadProduct(){
    var id = qs('#teinvit-gallery-product-id').value;
    setProgress('Loading product...');
    request('teinvit_product_gallery_prepare_product', { product_id: id }).then(function(json){
      if (!json || !json.success) {
        state.product = null;
        state.themes = [];
        renderSummary(); renderThemes();
        setProgress((json && json.data && json.data.message) || 'Product load failed.');
        return;
      }
      state.product = json.data.product;
      state.themes = json.data.themes || [];
      renderSummary(); renderThemes();
      setProgress('Product loaded.');
    }).catch(function(err){ setProgress(err.message || 'Request failed.'); });
  }
  function generateTheme(themeKey, mode){
    if (!state.product || !themeKey) return Promise.resolve(false);
    updateRow(Object.assign({}, state.themes.find(function(t){ return t.theme_key_internal === themeKey; }) || {}, { status: 'processing' }));
    setProgress('Processing ' + themeKey + '...');
    return request('teinvit_product_gallery_generate_theme', {
      product_id: state.product.product_id,
      theme_key: themeKey,
      mode: mode || 'missing'
    }).then(function(json){
      if (json && json.data && json.data.row) updateRow(json.data.row);
      if (!json || !json.success) {
        setProgress(((json && json.data && json.data.message) || 'Theme failed') + ' (' + themeKey + ')');
        return false;
      }
      setProgress('Done ' + themeKey + '.');
      return true;
    }).catch(function(err){ setProgress(err.message || 'Request failed.'); return false; });
  }
  function runQueue(themes, mode){
    var list = themes.slice();
    var total = list.length;
    var index = 0;
    function next(){
      if (!list.length) { setProgress('Finished ' + index + '/' + total + '.'); return Promise.resolve(); }
      var key = list.shift();
      index += 1;
      setProgress('Processing ' + index + '/' + total + ': ' + key);
      return generateTheme(key, mode).then(next);
    }
    return next();
  }
  function bulkVertical(){ var el = qs('#teinvit-gallery-bulk-vertical'); return el ? el.value : 'wedding'; }
  function bulkScope(){ var el = qs('#teinvit-gallery-bulk-scope'); return el ? el.value : 'premium'; }
  function bulkExpectedConfirm(){ return 'REGENERATE ' + String(bulkVertical()).toUpperCase(); }
  function setBulkProgress(msg){
    var el = qs('#teinvit-gallery-bulk-progress');
    if (el) el.textContent = msg || '';
  }
  function bulkSetButtons(){
    var dry = state.bulk.dryRun;
    var running = state.bulk.running;
    var lock = dry && dry.lock ? dry.lock : null;
    var locked = !!(lock && lock.active);
    var missingJobs = bulkJobsFor('missing').length;
    var retryJobs = bulkJobsFor('retry').length;
    var regenJobs = bulkJobsFor('regenerate').length;
    var check = qs('#teinvit-gallery-bulk-regenerate-check');
    var text = qs('#teinvit-gallery-bulk-regenerate-text');
    var expected = qs('#teinvit-gallery-bulk-regenerate-expected');
    if (expected) expected.textContent = bulkExpectedConfirm();
    var startMissing = qs('#teinvit-gallery-bulk-start-missing');
    var retryFailed = qs('#teinvit-gallery-bulk-retry-failed');
    var stop = qs('#teinvit-gallery-bulk-stop');
    var regen = qs('#teinvit-gallery-bulk-start-regenerate');
    if (startMissing) startMissing.disabled = !dry || running || locked || missingJobs <= 0;
    if (retryFailed) retryFailed.disabled = !dry || running || locked || retryJobs <= 0;
    if (stop) stop.disabled = !running;
    if (regen) regen.disabled = !dry || running || locked || regenJobs <= 0 || !check || !check.checked || !text || text.value !== bulkExpectedConfirm();
  }
  function bulkRenderLock(lock){
    var el = qs('#teinvit-gallery-bulk-lock');
    if (!el) return;
    if (!lock || !lock.active) {
      el.innerHTML = '';
      return;
    }
    var html = '<div class="notice notice-' + (lock.expired ? 'warning' : 'error') + ' inline"><p>';
    html += 'Exista un bulk in desfasurare pentru <code>' + esc(lock.vertical) + '</code>, pornit de user <code>' + esc(lock.user_id) + '</code> la <code>' + esc(lock.started_at) + '</code>.';
    if (lock.expired) {
      html += ' Lock-ul pare abandonat. <button type="button" class="button" id="teinvit-gallery-bulk-release-expired">Release expired lock</button>';
    }
    html += '</p></div>';
    el.innerHTML = html;
  }
  function bulkRenderSummary(dryRun){
    var el = qs('#teinvit-gallery-bulk-summary');
    var actions = qs('#teinvit-gallery-bulk-actions');
    if (!el || !dryRun) {
      if (el) el.innerHTML = '';
      if (actions) actions.style.display = 'none';
      return;
    }
    var s = dryRun.summary || {};
    el.innerHTML =
      '<table class="widefat striped" style="max-width:900px;"><tbody>' +
      '<tr><th>Vertical</th><td><code>' + esc(dryRun.vertical_label || dryRun.vertical) + '</code></td><th>Package scope</th><td><code>' + esc(dryRun.package_scope_label || dryRun.package_scope) + '</code></td></tr>' +
      '<tr><th>Eligible products</th><td>' + esc(s.eligible_products) + '</td><th>Excluded products</th><td>' + esc(s.excluded_products) + '</td></tr>' +
      '<tr><th>Products without background</th><td>' + esc(s.products_without_background) + '</td><th>Total theme pairs</th><td>' + esc(s.total_theme_pairs) + '</td></tr>' +
      '<tr><th>Complete pairs</th><td>' + esc(s.complete_pairs) + '</td><th>Missing pairs</th><td>' + esc(s.missing_pairs) + '</td></tr>' +
      '<tr><th>Incomplete pairs</th><td>' + esc(s.incomplete_pairs) + '</td><th>Invalid pairs</th><td>' + esc(s.invalid_pairs) + '</td></tr>' +
      '<tr><th>Existing failed</th><td>' + esc(s.failed_existing) + '</td><th>Jobs to generate</th><td>' + esc(s.job_count) + ' (' + esc(s.estimated_files_generated) + ' files)</td></tr>' +
      '<tr><th>Retry failed jobs</th><td>' + esc(s.retry_failed_count) + '</td><th>Regenerate jobs</th><td>' + esc(s.regenerate_jobs_count) + '</td></tr>' +
      '</tbody></table>';
    if (actions) actions.style.display = '';
  }
  function bulkRenderIncluded(rows){
    var wrap = qs('#teinvit-gallery-bulk-included-wrap');
    var table = qs('#teinvit-gallery-bulk-included-table');
    var tbody = table ? table.querySelector('tbody') : null;
    if (!wrap || !tbody) return;
    tbody.innerHTML = '';
    (rows || []).forEach(function(r){
      var tr = document.createElement('tr');
      tr.innerHTML =
        '<td>' + esc(r.product_id) + '</td>' +
        '<td>' + esc(r.product_name) + '</td>' +
        '<td><code>' + esc(r.product_slug) + '</code></td>' +
        '<td><code>' + esc(r.gallery_file_base_slug) + '</code></td>' +
        '<td><code>' + esc(r.vertical) + '</code></td>' +
        '<td><code>' + esc(r.package_type) + '</code></td>' +
        '<td>' + esc(r.background_status) + '</td>' +
        '<td>' + esc(r.theme_count) + '</td>' +
        '<td>' + esc(r.complete_count) + '</td>' +
        '<td>' + esc(r.missing_count) + '</td>' +
        '<td>' + esc(r.incomplete_count) + '</td>' +
        '<td>' + esc(r.invalid_count) + '</td>' +
        '<td>' + esc(r.failed_count) + '</td>' +
        '<td>' + esc(r.planned_jobs_count) + '</td>';
      tbody.appendChild(tr);
    });
    wrap.style.display = rows && rows.length ? '' : 'none';
  }
  function bulkRenderExcluded(rows){
    var wrap = qs('#teinvit-gallery-bulk-excluded-wrap');
    var table = qs('#teinvit-gallery-bulk-excluded-table');
    var tbody = table ? table.querySelector('tbody') : null;
    if (!wrap || !tbody) return;
    tbody.innerHTML = '';
    (rows || []).forEach(function(r){
      var tr = document.createElement('tr');
      tr.innerHTML =
        '<td>' + esc(r.product_id) + '</td>' +
        '<td>' + esc(r.product_name) + '</td>' +
        '<td><code>' + esc(r.vertical_detected) + '</code></td>' +
        '<td><code>' + esc(r.package_type_detected) + '</code></td>' +
        '<td><code>' + esc(r.exclusion_reason) + '</code></td>';
      tbody.appendChild(tr);
    });
    wrap.style.display = rows && rows.length ? '' : 'none';
  }
  function bulkRenderResults(){
    var wrap = qs('#teinvit-gallery-bulk-results-wrap');
    var table = qs('#teinvit-gallery-bulk-results-table');
    var tbody = table ? table.querySelector('tbody') : null;
    if (!wrap || !tbody) return;
    tbody.innerHTML = '';
    state.bulk.results.forEach(function(r){
      var tr = document.createElement('tr');
      tr.innerHTML =
        '<td>' + esc(r.product_id) + '</td>' +
        '<td>' + esc(r.product_name) + '</td>' +
        '<td>' + esc(r.theme_label) + '<br><code>' + esc(r.theme_key_internal) + '</code></td>' +
        '<td><code>' + esc(r.action) + '</code></td>' +
        '<td style="' + statusClass(r.status) + '">' + esc(r.status) + '</td>' +
        '<td><code>' + esc(r.error_code) + '</code><br>' + esc(r.error_message) + '</td>' +
        '<td>' + esc(r.duration_ms) + ' ms</td>';
      tbody.appendChild(tr);
    });
    wrap.style.display = state.bulk.results.length ? '' : 'none';
  }
  function bulkJobsFor(action){
    var dry = state.bulk.dryRun;
    var jobs = dry && dry.jobs ? dry.jobs : [];
    if (action === 'retry') {
      return jobs.filter(function(j){ return !!j.retry_eligible; });
    }
    if (action === 'regenerate') {
      return jobs.filter(function(j){ return !!j.regenerate_eligible; });
    }
    return jobs.filter(function(j){ return j.planned_action === 'generate'; });
  }
  function bulkSkippedResults(action){
    var dry = state.bulk.dryRun;
    var jobs = dry && dry.jobs ? dry.jobs : [];
    if (action !== 'missing') return [];
    return jobs.filter(function(j){ return j.planned_action === 'skip'; }).map(function(j){
      return {
        product_id: j.product_id,
        product_name: j.product_name,
        theme_key_internal: j.theme_key_internal,
        theme_label: j.theme_label,
        action: 'skip',
        status: 'skipped_existing',
        error_code: '',
        error_message: '',
        duration_ms: 0
      };
    });
  }
  function bulkDryRun(){
    setBulkProgress('Running dry run...');
    request('teinvit_product_gallery_bulk_dry_run', {
      vertical: bulkVertical(),
      package_scope: bulkScope()
    }).then(function(json){
      if (!json || !json.success) {
        state.bulk.dryRun = null;
        bulkRenderSummary(null);
        bulkRenderIncluded([]);
        bulkRenderExcluded([]);
        bulkRenderLock(json && json.data && json.data.lock);
        setBulkProgress((json && json.data && json.data.message) || 'Dry run failed.');
        bulkSetButtons();
        return;
      }
      state.bulk.dryRun = json.data;
      state.bulk.jobs = json.data.jobs || [];
      state.bulk.results = [];
      bulkRenderLock(json.data.lock);
      bulkRenderSummary(json.data);
      bulkRenderIncluded(json.data.included_products || []);
      bulkRenderExcluded(json.data.excluded_products || []);
      bulkRenderResults();
      setBulkProgress('Dry run ready.');
      bulkSetButtons();
    }).catch(function(err){
      setBulkProgress(err.message || 'Dry run request failed.');
      bulkSetButtons();
    });
  }
  function bulkStart(action){
    var jobs = bulkJobsFor(action);
    if (!jobs.length || state.bulk.running) return;
    var mode = action === 'regenerate' ? 'regenerate' : 'missing';
    state.bulk.running = true;
    state.bulk.stopRequested = false;
    state.bulk.results = bulkSkippedResults(action);
    bulkRenderResults();
    bulkSetButtons();
    setBulkProgress('Acquiring bulk lock...');
    request('teinvit_product_gallery_bulk_start', {
      vertical: bulkVertical(),
      package_scope: bulkScope(),
      mode: mode
    }).then(function(json){
      if (!json || !json.success) {
        state.bulk.running = false;
        if (state.bulk.dryRun && json && json.data && json.data.lock) state.bulk.dryRun.lock = json.data.lock;
        bulkRenderLock(json && json.data && json.data.lock);
        setBulkProgress((json && json.data && json.data.message) || 'Could not start bulk.');
        bulkSetButtons();
        return;
      }
      state.bulk.jobId = json.data.job_id;
      if (state.bulk.dryRun) state.bulk.dryRun.lock = json.data.lock;
      bulkRenderLock(json.data.lock);
      bulkRunNext(jobs, mode, action, 0);
    }).catch(function(err){
      state.bulk.running = false;
      setBulkProgress(err.message || 'Bulk start failed.');
      bulkSetButtons();
    });
  }
  function bulkRunNext(jobs, mode, action, index){
    if (state.bulk.stopRequested) {
      bulkReleaseLock(false, 'Stopped after current request.');
      return;
    }
    if (index >= jobs.length) {
      bulkReleaseLock(false, 'Bulk finished. Processed ' + jobs.length + ' jobs.');
      return;
    }
    var job = jobs[index];
    setBulkProgress('Processing ' + (index + 1) + '/' + jobs.length + ': product #' + job.product_id + ' / ' + job.theme_key_internal);
    request('teinvit_product_gallery_bulk_step', {
      job_id: state.bulk.jobId,
      product_id: job.product_id,
      theme_key: job.theme_key_internal,
      mode: mode
    }).then(function(json){
      var data = json && json.data ? json.data : {};
      var row = data.row || {};
      var status = data.status || (json && json.success ? 'success' : 'failed');
      state.bulk.results.push({
        product_id: job.product_id,
        product_name: job.product_name,
        theme_key_internal: job.theme_key_internal,
        theme_label: job.theme_label,
        action: action,
        status: status,
        error_code: data.error_code || '',
        error_message: data.error_message || ((json && json.success) ? '' : ((json && json.data && json.data.message) || 'Request failed')),
        duration_ms: data.duration_ms || 0
      });
      if (row && row.theme_key_internal && state.product && String(state.product.product_id) === String(job.product_id)) {
        updateRow(row);
      }
      bulkRenderResults();
      bulkRunNext(jobs, mode, action, index + 1);
    }).catch(function(err){
      state.bulk.results.push({
        product_id: job.product_id,
        product_name: job.product_name,
        theme_key_internal: job.theme_key_internal,
        theme_label: job.theme_label,
        action: action,
        status: 'failed',
        error_code: 'ajax_request_failed',
        error_message: err.message || 'Request failed',
        duration_ms: 0
      });
      bulkRenderResults();
      bulkRunNext(jobs, mode, action, index + 1);
    });
  }
  function bulkReleaseLock(forceExpired, message){
    request('teinvit_product_gallery_bulk_release_lock', {
      job_id: state.bulk.jobId || '',
      force_expired: forceExpired ? '1' : ''
    }).then(function(json){
      state.bulk.running = false;
      state.bulk.jobId = '';
      state.bulk.stopRequested = false;
      if (state.bulk.dryRun && json && json.data) state.bulk.dryRun.lock = json.data.lock;
      bulkRenderLock(json && json.data && json.data.lock);
      setBulkProgress(message || 'Bulk lock released.');
      bulkSetButtons();
    }).catch(function(err){
      state.bulk.running = false;
      setBulkProgress(err.message || 'Could not release lock.');
      bulkSetButtons();
    });
  }
  document.addEventListener('click', function(e){
    if (e.target && e.target.id === 'teinvit-gallery-load-product') loadProduct();
    if (e.target && e.target.id === 'teinvit-gallery-generate-theme') generateTheme(qs('#teinvit-gallery-theme-select').value, 'missing');
    if (e.target && e.target.id === 'teinvit-gallery-regenerate-theme') {
      if (confirm('Regenerate selected theme and overwrite valid files only after a new valid pair is ready?')) generateTheme(qs('#teinvit-gallery-theme-select').value, 'regenerate');
    }
    if (e.target && e.target.id === 'teinvit-gallery-generate-all') {
      runQueue(state.themes.map(function(t){ return t.theme_key_internal; }), 'missing');
    }
    if (e.target && e.target.id === 'teinvit-gallery-retry-failed') {
      runQueue(state.themes.filter(function(t){ return String(t.status || '').indexOf('failed') === 0 || t.status === 'failed'; }).map(function(t){ return t.theme_key_internal; }), 'missing');
    }
    if (e.target && e.target.id === 'teinvit-gallery-regenerate-product') {
      if (confirm('Regenerate all themes for this product? Existing files are overwritten atomically only after each new pair is valid.')) {
        runQueue(state.themes.map(function(t){ return t.theme_key_internal; }), 'regenerate');
      }
    }
    if (e.target && e.target.id === 'teinvit-gallery-bulk-dry-run') {
      bulkDryRun();
    }
    if (e.target && e.target.id === 'teinvit-gallery-bulk-start-missing') {
      bulkStart('missing');
    }
    if (e.target && e.target.id === 'teinvit-gallery-bulk-retry-failed') {
      bulkStart('retry');
    }
    if (e.target && e.target.id === 'teinvit-gallery-bulk-start-regenerate') {
      if (confirm('Regenerate existing gallery images for this dry run? Existing valid files remain untouched if a new pair fails.')) {
        bulkStart('regenerate');
      }
    }
    if (e.target && e.target.id === 'teinvit-gallery-bulk-stop') {
      state.bulk.stopRequested = true;
      setBulkProgress('Stop requested. Current request will finish first.');
      bulkSetButtons();
    }
    if (e.target && e.target.id === 'teinvit-gallery-bulk-release-expired') {
      if (confirm('Release the expired bulk lock?')) {
        bulkReleaseLock(true, 'Expired lock released.');
      }
    }
  });
  document.addEventListener('change', function(e){
    if (e.target && (e.target.id === 'teinvit-gallery-bulk-vertical' || e.target.id === 'teinvit-gallery-bulk-scope')) {
      state.bulk.dryRun = null;
      state.bulk.jobs = [];
      state.bulk.results = [];
      bulkRenderSummary(null);
      bulkRenderIncluded([]);
      bulkRenderExcluded([]);
      bulkRenderResults();
      setBulkProgress('Run dry run for the selected scope.');
      bulkSetButtons();
    }
    if (e.target && e.target.id === 'teinvit-gallery-bulk-regenerate-check') {
      bulkSetButtons();
    }
  });
  document.addEventListener('input', function(e){
    if (e.target && e.target.id === 'teinvit-gallery-bulk-regenerate-text') {
      bulkSetButtons();
    }
  });
  bulkSetButtons();
})();
JS;
    echo '</script>';
    echo '</div>';
}
