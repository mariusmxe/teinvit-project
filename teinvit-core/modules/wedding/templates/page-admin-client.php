<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$token = get_query_var( 'teinvit_admin_client_token' );
$inv = teinvit_get_invitation( $token );
if ( ! $inv ) {
    echo '<p>Invitația nu a fost găsită.</p>';
    return;
}

$order = wc_get_order( (int) $inv['order_id'] );
if ( ! $order ) {
    echo '<p>Comanda nu a fost găsită.</p>';
    return;
}

$versions = teinvit_get_versions_for_token( $token );
usort( $versions, static function( $a, $b ) {
    return (int) $a['id'] <=> (int) $b['id'];
} );

$order_wapf = TeInvit_Wedding_Preview_Renderer::get_order_wapf_field_map( $order );
$order_invitation = TeInvit_Wedding_Preview_Renderer::get_order_invitation_data( $order );
$order_pdf_url = (string) $order->get_meta( '_teinvit_pdf_url' );

$variants = [];
foreach ( $versions as $index => $row ) {
    $snap = json_decode( (string) $row['snapshot'], true );
    $snap_inv = ( isset( $snap['invitation'] ) && is_array( $snap['invitation'] ) ) ? $snap['invitation'] : [];
    $snap_wapf = ( isset( $snap['wapf_fields'] ) && is_array( $snap['wapf_fields'] ) ) ? $snap['wapf_fields'] : [];

    if ( empty( $snap_wapf ) && 0 === $index && empty( $row['snapshot'] ) ) {
        $snap_wapf = $order_wapf;
    }
    if ( empty( $snap_inv ) && 0 === $index && empty( $row['snapshot'] ) ) {
        $snap_inv = $order_invitation;
    }

    $pdf_url = (string) ( $row['pdf_url'] ?? '' );
    if ( $index === 0 && $pdf_url === '' && $order_pdf_url !== '' ) {
        $pdf_url = esc_url_raw( $order_pdf_url );
    }

    $variants[] = [
        'id' => (int) $row['id'],
        'label' => 'Varianta ' . $index,
        'invitation' => $snap_inv,
        'wapf_fields' => $snap_wapf,
        'created_at' => (string) $row['created_at'],
        'pdf_url' => $pdf_url,
        'pdf_status' => (string) ( $row['pdf_status'] ?? 'none' ),
    ];
}

$current = null;
$active_id = (int) ( $inv['active_version_id'] ?? 0 );
$selected_version_id = isset( $_GET['selected_version_id'] ) ? (int) $_GET['selected_version_id'] : 0;

if ( $selected_version_id > 0 ) {
    foreach ( $variants as $variant ) {
        if ( (int) $variant['id'] === $selected_version_id ) {
            $current = $variant;
            break;
        }
    }
}

foreach ( $variants as $variant ) {
    if ( (int) $variant['id'] === $active_id ) {
        if ( ! $current ) {
            $current = $variant;
        }
        break;
    }
}
if ( ! $current && ! empty( $variants ) ) {
    $current = $variants[0];
}

$current_invitation = $current['invitation'] ?? $order_invitation;
$current_wapf = $current['wapf_fields'] ?? $order_wapf;
$ui_selected_version_id = (int) ( $current['id'] ?? $active_id );
$subtitle = trim( (string) ( $current_invitation['names'] ?? '' ) );
if ( $subtitle === '' ) {
    $subtitle = 'Nume Mireasă & Nume Mire';
}

$config = is_array( $inv['config'] ?? null ) ? $inv['config'] : [];
$edits_remaining = isset( $config['edits_free_remaining'] ) ? (int) $config['edits_free_remaining'] : 2;
$show_deadline = ! empty( $config['show_rsvp_deadline'] );
$deadline_date = (string) ( $config['rsvp_deadline_date'] ?? '' );

$active_snapshot = function_exists( 'teinvit_get_active_snapshot' ) ? teinvit_get_active_snapshot( $token ) : [];
$active_payload = ! empty( $active_snapshot['snapshot'] ) ? json_decode( (string) $active_snapshot['snapshot'], true ) : [];
$active_events = isset( $active_payload['invitation']['events'] ) && is_array( $active_payload['invitation']['events'] ) ? $active_payload['invitation']['events'] : [];
$event_flags = [
    'civil' => false,
    'religious' => false,
    'party' => false,
];
foreach ( $active_events as $event ) {
    if ( ! is_array( $event ) ) {
        continue;
    }
    $title = strtolower( trim( (string) ( $event['title'] ?? '' ) ) );
    if ( strpos( $title, 'civil' ) !== false ) {
        $event_flags['civil'] = true;
    }
    if ( strpos( $title, 'religio' ) !== false ) {
        $event_flags['religious'] = true;
    }
    if ( strpos( $title, 'petrec' ) !== false ) {
        $event_flags['party'] = true;
    }
}

$admin_toggle_fields = [
    'permite_confirmarea_pentru_cununia_civila' => [
        'label' => 'Permite confirmarea pentru cununia civilă',
        'config_key' => 'show_attending_civil',
        'event_key' => 'civil',
    ],
    'permite_confirmarea_pentru_ceremonia_religioasa' => [
        'label' => 'Permite confirmarea pentru ceremonia religioasă',
        'config_key' => 'show_attending_religious',
        'event_key' => 'religious',
    ],
    'permite_confirmarea_pentru_petrecere' => [
        'label' => 'Permite confirmarea pentru petrecere',
        'config_key' => 'show_attending_party',
        'event_key' => 'party',
    ],
    'permite_confirmarea_copiilor' => [
        'label' => 'Permite confirmarea copiilor',
        'config_key' => 'show_kids',
    ],
    'permite_solicitarea_de_cazare' => [
        'label' => 'Permite solicitarea de cazare',
        'config_key' => 'show_accommodation',
    ],
    'permite_selectarea_meniului_vegetarian' => [
        'label' => 'Permite selectarea meniului vegetarian',
        'config_key' => 'show_vegetarian',
    ],
    'permite_mentionarea_alergiilor' => [
        'label' => 'Permite menționarea alergiilor',
        'config_key' => 'show_allergies',
    ],
    'permite_trimiterea_unui_mesaj_catre_miri' => [
        'label' => 'Permite trimiterea unui mesaj către miri',
        'config_key' => 'show_message',
    ],
];

$preview_html = TeInvit_Wedding_Preview_Renderer::render_from_invitation_data( $current_invitation, $order );
$product_id = teinvit_get_order_primary_product_id( $order );
$product = $product_id ? wc_get_product( $product_id ) : null;
$apf_html = ( $product && function_exists( 'wapf_display_field_groups_for_product' ) ) ? wapf_display_field_groups_for_product( $product ) : '';
$buy_edits_url = add_query_arg( [ 'add-to-cart' => 301, 'quantity' => 1 ], wc_get_cart_url() );
$global_admin_content = function_exists( 'teinvit_render_admin_client_global_content' ) ? teinvit_render_admin_client_global_content() : '';
?>
<style>
.teinvit-admin-page{max-width:1200px;margin:20px auto;padding:16px}.teinvit-admin-page h1{text-align:center}
.teinvit-deadline-title,.teinvit-rsvp-settings-title{text-align:center}
.teinvit-deadline-form{display:flex;flex-direction:column;align-items:center;gap:10px}
.teinvit-deadline-form label{display:block;text-align:center}
.teinvit-zone{border:1px solid #e5e5e5;padding:14px;border-radius:8px;background:#fff;margin:16px 0}
.teinvit-two-col{display:grid;grid-template-columns:minmax(0,1.2fr) minmax(0,1fr);gap:20px}
@media (max-width: 1024px){.teinvit-two-col{grid-template-columns:1fr}}
.teinvit-apf-col .wapf-wrapper,.teinvit-apf-col .wapf{max-width:100%}
.teinvit-admin-page .teinvit-page,.teinvit-admin-page .teinvit-container{max-width:100%;overflow:hidden}
.teinvit-admin-page .teinvit-preview{max-width:760px;margin:0 auto;overflow:hidden}
</style>
<div class="teinvit-admin-page">
  <h1>Administrare invitație</h1>
  <h1><?php echo esc_html( $subtitle ); ?></h1>

  <?php if ( $global_admin_content !== '' ) : ?>
  <div class="teinvit-zone teinvit-admin-global-zone">
    <?php echo $global_admin_content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
  </div>
  <?php endif; ?>

  <div class="teinvit-zone">
    <h3 class="teinvit-deadline-title">Data limită pentru confirmări</h3>
    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="teinvit-deadline-form" class="teinvit-deadline-form">
      <?php wp_nonce_field( 'teinvit_admin_' . $token ); ?>
      <input type="hidden" name="action" value="teinvit_save_invitation_info">
      <input type="hidden" name="token" value="<?php echo esc_attr( $token ); ?>">
      <label><input type="checkbox" id="date_confirm" name="date_confirm" value="1" <?php checked( $show_deadline ); ?>> Doresc afișarea datei limită pentru confirmări în pagina invitaților</label>
      <div id="selecteaza-data-wrap" style="margin-top:10px;<?php echo $show_deadline ? '' : 'display:none;'; ?>" class="acf-field acf-field-date-picker" data-name="selecteaza_data" data-type="date_picker">
        <label for="selecteaza_data">Selectează data</label>
        <div class="acf-input">
          <div class="acf-date-picker acf-input-wrap" data-date_format="dd/mm/yy" data-display_format="dd/mm/yy" data-first_day="1">
            <input type="text" id="selecteaza_data" name="selecteaza_data" placeholder="zz/ll/aaaa" value="<?php echo esc_attr( $deadline_date ); ?>" autocomplete="off" class="input">
          </div>
        </div>
      </div>
      <p><button type="submit" class="button">Publică data limită</button></p>
    </form>
  </div>

  <div class="teinvit-zone teinvit-two-col">
    <div>
      <?php echo $preview_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
      <h3>Alege varianta afișată invitaților:</h3>
      <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="teinvit-publish-form">
        <?php wp_nonce_field( 'teinvit_admin_' . $token ); ?>
        <input type="hidden" name="action" value="teinvit_set_active_version">
        <input type="hidden" name="token" value="<?php echo esc_attr( $token ); ?>">
        <?php foreach ( $variants as $variant ) : ?>
          <label style="display:block;margin-bottom:6px;">
            <input type="radio" name="active_version_id" value="<?php echo (int) $variant['id']; ?>" <?php checked( (int) $variant['id'], $ui_selected_version_id ); ?> class="teinvit-variant-radio">
            <?php echo esc_html( $variant['label'] ); ?>
            <?php if ( ! empty( $variant['pdf_url'] ) ) : ?>
              — <a href="<?php echo esc_url( $variant['pdf_url'] ); ?>" target="_blank" rel="noopener">Descarcă PDF</a>
            <?php elseif ( ( $variant['pdf_status'] ?? '' ) === 'processing' ) : ?>
              — <em>PDF în curs...</em>
            <?php elseif ( ( $variant['pdf_status'] ?? '' ) === 'failed' ) : ?>
              — <em>PDF eșuat</em>
            <?php endif; ?>
          </label>
        <?php endforeach; ?>
        <button type="submit" class="button button-primary">Publică</button>
      </form>

      <div class="teinvit-zone teinvit-admin-rsvp-settings">
        <h3 class="teinvit-rsvp-settings-title">Setările formularului de confirmare</h3>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="teinvit-rsvp-config-form">
          <?php wp_nonce_field( 'teinvit_admin_' . $token ); ?>
          <input type="hidden" name="action" value="teinvit_save_rsvp_config">
          <input type="hidden" name="token" value="<?php echo esc_attr( $token ); ?>">
          <?php foreach ( $admin_toggle_fields as $field_name => $field_def ) : ?>
            <?php
            $event_key = isset( $field_def['event_key'] ) ? (string) $field_def['event_key'] : '';
            if ( $event_key !== '' && empty( $event_flags[ $event_key ] ) ) {
                continue;
            }
            $checked = ! empty( $config[ $field_def['config_key'] ] );
            ?>
            <label>
              <input type="checkbox" name="<?php echo esc_attr( $field_name ); ?>" value="1" <?php checked( $checked ); ?>>
              <?php echo esc_html( $field_def['label'] ); ?>
            </label><br>
          <?php endforeach; ?>
          <p><button type="submit" class="button">Publică selecțiile</button></p>
        </form>
      </div>

      <p style="margin-top:8px;">
        <a href="<?php echo esc_url( home_url( '/invitati/' . rawurlencode( $token ) ) ); ?>" target="_blank" rel="noopener">Vezi pagina invitaților</a>
      </p>
    </div>

    <div class="teinvit-apf-col" data-product-page-preselected-id="<?php echo (int) $product_id; ?>">
      <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="teinvit-save-form" class="cart" data-product-page-preselected-id="<?php echo (int) $product_id; ?>">
        <?php wp_nonce_field( 'teinvit_admin_' . $token ); ?>
        <input type="hidden" name="action" value="teinvit_save_version_snapshot">
        <input type="hidden" name="token" value="<?php echo esc_attr( $token ); ?>">
        <input type="hidden" name="teinvit_parent_checked_json" id="teinvit-parent-checked-json" value="">

        <?php if ( $apf_html !== '' ) : ?>
          <?php echo $apf_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        <?php else : ?>
          <p>Câmpurile APF nu sunt disponibile (plugin/APF hooks).</p>
        <?php endif; ?>

        <p id="teinvit-edits-counter"><?php echo (int) $edits_remaining; ?> modificări gratuite disponibile</p>
        <?php if ( $edits_remaining > 0 ) : ?>
          <button type="submit" class="button button-primary" id="teinvit-save-btn">Salvează modificările</button>
        <?php else : ?>
          <a href="<?php echo esc_url( $buy_edits_url ); ?>" class="button">Cumpără modificări suplimentare</a>
        <?php endif; ?>
      </form>
    </div>
  </div>
</div>
<script>
(function(){
  window.teinvitPreviewConfig = Object.assign({}, window.teinvitPreviewConfig || {}, {
    previewBuildUrl: <?php echo wp_json_encode( esc_url_raw( rest_url( 'teinvit/v2/preview/build' ) ) ); ?>,
    token: <?php echo wp_json_encode( (string) $token ); ?>,
    productId: <?php echo (int) $product_id; ?>
  });

  const variants = <?php echo wp_json_encode( $variants ); ?>;
  const initialWapf = <?php echo wp_json_encode( $current_wapf ); ?>;
  const initialInvitation = <?php echo wp_json_encode( $current_invitation ); ?>;
  const editsRemaining = <?php echo (int) $edits_remaining; ?>;
  const parentBooleanIds = ['696445d6a9ce9','696448f2ae763','69644d9e814ef','69645088f4b73','696451a951467'];
  let isApplyingVariant = false;

  const deadlineCb = document.getElementById('date_confirm');
  const deadlineWrap = document.getElementById('selecteaza-data-wrap');
  if(deadlineCb && deadlineWrap){ deadlineCb.addEventListener('change',()=>{ deadlineWrap.style.display = deadlineCb.checked ? '' : 'none'; }); }

  if (window.acf && typeof window.acf.doAction === 'function') {
    window.acf.doAction('ready');
    window.acf.doAction('append', window.jQuery ? window.jQuery('#teinvit-deadline-form') : null);
  }

  if (window.jQuery && window.jQuery.fn && typeof window.jQuery.fn.datepicker === 'function') {
    window.jQuery('#selecteaza_data').datepicker({ dateFormat: 'dd/mm/yy' });
  }

  function normalizeToArray(value){
    if (Array.isArray(value)) return value;
    if (typeof value === 'string') return value.split(',').map(s=>s.trim()).filter(Boolean);
    if (value == null) return [];
    return [String(value)];
  }

  function parseWapfForm(){
    const out = {};
    document.querySelectorAll('#teinvit-save-form [name^="wapf[field_"]').forEach(el=>{
      const m = el.name.match(/^wapf\[field_([^\]]+)\]/); if(!m) return;
      const id = m[1];

      if (el.type === 'hidden') {
        return;
      }

      if (el.type === 'checkbox') {
        if (!Array.isArray(out[id])) {
          out[id] = normalizeToArray(out[id]);
        }
        if (el.checked) {
          const val = el.value || '1';
          if (!out[id].includes(val)) out[id].push(val);
        }
        return;
      }

      if(el.type==='radio'){
        if(el.checked){ out[id]=el.value || ''; }
        else if(typeof out[id] === 'undefined'){ out[id]=''; }
        return;
      }

      if (!Array.isArray(out[id])) {
        out[id]=el.value || '';
      }
    });

    Object.keys(out).forEach(k=>{
      if(Array.isArray(out[k])) out[k]=out[k].join(', ');
    });

    return out;
  }

  function splitSelected(raw){
    return String(raw || '').split(',').map(s=>s.trim()).filter(Boolean);
  }

  function buildOptionLookups(elements){
    const byValue = {};
    const byLabel = {};
    elements.forEach(el=>{
      if (el.tagName === 'SELECT') {
        Array.from(el.options || []).forEach(opt=>{
          const value = String(opt.value || '').trim();
          const label = String(opt.text || '').trim();
          if (value) byValue[value] = label;
          if (label) byLabel[label.toLowerCase()] = value || label;
        });
        return;
      }
      const value = String(el.value || '').trim();
      const labelNode = el.closest('label');
      const label = labelNode && labelNode.textContent ? String(labelNode.textContent).trim() : '';
      if (value && value !== '0') byValue[value] = label || value;
      if (label) byLabel[label.toLowerCase()] = value || label;
    });
    return { byValue, byLabel };
  }

  function normalizeRawWithLookups(raw, lookups, allowMulti){
    const rawString = String(raw || '').trim();
    if (!rawString) return '';

    const resolveSingle = (token)=>{
      const t = String(token || '').trim();
      if (!t || t === '0') return '';
      if (Object.prototype.hasOwnProperty.call(lookups.byValue, t)) return t;
      const byLabel = lookups.byLabel[t.toLowerCase()];
      return byLabel ? String(byLabel) : '';
    };

    if (!allowMulti) {
      return resolveSingle(rawString) || rawString;
    }

    const resolved = [];
    const direct = resolveSingle(rawString);
    if (direct) resolved.push(direct);
    splitSelected(rawString).forEach(token=>{
      const match = resolveSingle(token);
      if (match) resolved.push(match);
    });
    return Array.from(new Set(resolved)).join(', ');
  }

  function normalizeMapToCurrentInputs(map){
    const out = Object.assign({}, map || {});
    const groups = {};
    document.querySelectorAll('#teinvit-save-form [name^="wapf[field_"]').forEach(el=>{
      const m = el.name.match(/^wapf\[field_([^\]]+)\]/); if(!m) return;
      const id = m[1];
      if(!groups[id]) groups[id] = [];
      groups[id].push(el);
    });

    Object.keys(groups).forEach(id=>{
      const elements = groups[id];
      const raw = out[id] ?? '';
      const nonHidden = elements.filter(el=>el.type !== 'hidden');
      const lookups = buildOptionLookups(nonHidden);
      const isMultiCheckbox = nonHidden.some(el=>el.type === 'checkbox');
      out[id] = normalizeRawWithLookups(raw, lookups, isMultiCheckbox);
    });

    return out;
  }

  function buildThemeLookup(){
    const out = {};
    const themeSelect = document.querySelector('#teinvit-save-form [name="wapf[field_6967752ab511b]"]');
    if(!themeSelect || !themeSelect.options) return out;
    Array.from(themeSelect.options).forEach(opt=>{
      const val = String(opt.value || '').trim();
      const label = String(opt.text || '').trim();
      if(val) out[val] = label;
    });
    return out;
  }

  function resolveThemeKey(raw, themeLookup){
    const rawTrimmed = String(raw || '').trim();
    const rawLower = rawTrimmed.toLowerCase();

    const toKey = (value)=>{
      const normalized = String(value || '').trim().toLowerCase();
      if(!normalized) return '';
      if(normalized === 'editorial' || normalized.includes('editorial')) return 'editorial';
      if(normalized === 'romantic' || normalized.includes('romantic')) return 'romantic';
      if(normalized === 'modern' || normalized.includes('modern')) return 'modern';
      if(normalized === 'classic' || normalized.includes('classic')) return 'classic';
      return '';
    };

    const directKey = toKey(rawLower);
    if (directKey) return directKey;

    const mappedLabel = String(themeLookup[rawTrimmed] || '').trim();
    const mappedKey = toKey(mappedLabel);
    if (mappedKey) return mappedKey;

    return 'editorial';
  }

  function isParentBooleanField(id){
    return parentBooleanIds.includes(String(id || ''));
  }

  function setWapfValues(map, options){
    const shouldTrigger = !(options && options.triggerEvents === false);
    const batchMode = !!(options && options.batchMode);
    const phase = options && options.phase ? String(options.phase) : 'all';
    const normalizedMap = normalizeMapToCurrentInputs(map || {});
    const groups = {};

    document.querySelectorAll('#teinvit-save-form [name^="wapf[field_"]').forEach(el=>{
      const m = el.name.match(/^wapf\[field_([^\]]+)\]/); if(!m) return;
      const id = m[1];
      if(!groups[id]) groups[id] = [];
      groups[id].push(el);
    });

    Object.keys(groups).forEach(id=>{
      if (phase === 'parents_only' && !isParentBooleanField(id)) return;
      if (phase === 'non_parents_only' && isParentBooleanField(id)) return;

      const elements = groups[id];
      const raw = normalizedMap[id] ?? '';
      const selected = splitSelected(raw);

      elements.forEach(el=>{
        if (el.type === 'hidden' && el.classList.contains('wapf-tf-h')) {
          return;
        }

        if(el.type==='checkbox'){
          const label = (el.closest('label') && el.closest('label').textContent ? el.closest('label').textContent.trim() : '');
          const selectedLower = selected.map(v=>String(v).toLowerCase());
          const valueLower = String(el.value || '').toLowerCase();
          const labelLower = String(label || '').toLowerCase();
          el.checked = selected.includes(el.value) || selected.includes(label) || selectedLower.includes(valueLower) || selectedLower.includes(labelLower) || (selected.includes('1') && (el.value === '1' || el.value === 'on'));
        } else if(el.type==='radio'){
          const label = (el.closest('label') && el.closest('label').textContent ? el.closest('label').textContent.trim() : '');
          el.checked = String(el.value) === String(raw) || String(label).trim().toLowerCase() === String(raw).trim().toLowerCase();
        } else if(el.tagName === 'SELECT'){
          const opts = Array.from(el.options || []);
          const byValue = opts.find(o=>String(o.value)===String(raw));
          const byLabel = opts.find(o=>String(o.text).trim()===String(raw).trim());
          el.value = byValue ? byValue.value : (byLabel ? byLabel.value : String(raw));
        } else {
          el.value = String(raw);
        }

      });
    });

    if (shouldTrigger && !batchMode) {
      document.dispatchEvent(new Event('input', {bubbles:true}));
      document.dispatchEvent(new Event('change', {bubbles:true}));
    }

    if (window.jQuery && !batchMode) {
      const $form = window.jQuery('#teinvit-save-form');
      window.jQuery(document).trigger('wapf/init', [$form]);
      window.jQuery(document).trigger('wapf/init_datepickers', [$form]);
      document.dispatchEvent(new CustomEvent('wapf:init', { detail: { wrapper: $form[0] || null } }));
    }
  }

  function buildInvitation(w){
    const get=(k)=> (w[k]||'').trim();
    const isZeroish=(v)=>{
      const tokens = String(v || '').split(',').map(t=>t.trim().toLowerCase()).filter(Boolean);
      if(!tokens.length) return false;
      return tokens.every(t => t === '0' || t === 'false' || t === 'off' || t === 'no');
    };
    const has=(k)=> {
      const val = get(k);
      return val !== '' && !isZeroish(val);
    };
    const inv={theme:'editorial',names:[get('6963a95e66425'),get('6963aa37412e4')].filter(Boolean).join(' & '),message:get('6963aa782092d'),show_parents:false,parents:{},show_nasi:false,nasi:'',events:[]};
    inv.theme = resolveThemeKey(get('6967752ab511b'), buildThemeLookup());
    if(has('696445d6a9ce9')){ inv.show_parents=true; inv.parents={mireasa:[get('6964461d67da5'),get('6964466afe4d1')].filter(Boolean).join(' & '),mire:[get('69644689ee7e1'),get('696446dfabb7b')].filter(Boolean).join(' & ')}; }
    if(has('696448f2ae763')){ inv.show_nasi=true; inv.nasi=[get('69644a3415fb9'),get('69644a5822ddc')].filter(Boolean).join(' & '); }
    if(has('69644d9e814ef')) inv.events.push({title:'Cununie civilă',loc:get('69644f2b40023'),date:[get('69644f85d865e'),get('8dec5e7')].filter(Boolean).join(' ora '),waze:get('69644fd5c832b')});
    if(has('69645088f4b73')) inv.events.push({title:'Ceremonie religioasă',loc:get('696450ee17f9e'),date:[get('696450ffe7db4'),get('32f74cc')].filter(Boolean).join(' ora '),waze:get('69645104b39f4')});
    if(has('696451a951467')) inv.events.push({title:'Petrecerea',loc:get('696451d204a8a'),date:[get('696452023cdcd'),get('a4a0fca')].filter(Boolean).join(' ora '),waze:get('696452478586d')});
    return inv;
  }

  function refreshPreview(){
    if (isApplyingVariant) {
      return;
    }

    const parsed = parseWapfForm();
    if (Object.keys(parsed).length === 0) {
      return;
    }
    const inv = buildInvitation(parsed);
    window.TEINVIT_INVITATION_DATA = inv;
    window.__TEINVIT_AUTOFIT_DONE__ = false;
    document.dispatchEvent(new Event('input', {bubbles:true}));
  }

  function runPreviewCycle(options){
    const skipFormRefresh = !!(options && options.skipFormRefresh);

    window.__TEINVIT_AUTOFIT_DONE__ = false;
    const form = document.getElementById('teinvit-save-form');
    if (window.jQuery && form) {
      const $form = window.jQuery(form);
      window.jQuery(document).trigger('wapf/init', [$form]);
      window.jQuery(document).trigger('wapf/init_datepickers', [$form]);
      document.dispatchEvent(new CustomEvent('wapf:init', { detail: { wrapper: $form[0] || null } }));
    }
    document.dispatchEvent(new Event('change', {bubbles:true}));
    if (!skipFormRefresh) {
      refreshPreview();
    }
    document.dispatchEvent(new Event('input', {bubbles:true}));
    document.dispatchEvent(new CustomEvent('teinvit:variant-applied', { bubbles: true }));
  }

  function flushWapfDependencies(){
    const form = document.getElementById('teinvit-save-form');
    if (!form) return;

    const parentCheckboxes = parentBooleanIds
      .map(id => Array.from(form.querySelectorAll('[name="wapf[field_' + id + '][]"]')).filter(el => el && el.type === 'checkbox'))
      .flat();

    parentCheckboxes.forEach((cb)=>{
      cb.dispatchEvent(new Event('change', { bubbles: true }));
    });
  }

  function applyVariant(id){
    const variant = variants.find(v => String(v.id)===String(id)); if(!variant) return;
    isApplyingVariant = true;

    setWapfValues(variant.wapf_fields || {}, { triggerEvents: false, batchMode: true, phase: 'parents_only' });
    flushWapfDependencies();
    setWapfValues(variant.wapf_fields || {}, { triggerEvents: false, batchMode: true, phase: 'non_parents_only' });

    window.TEINVIT_INVITATION_DATA = variant.invitation || null;
    runPreviewCycle({ skipFormRefresh: true });

    window.setTimeout(()=>{
      isApplyingVariant = false;
      refreshPreview();
    }, 0);
  }

  isApplyingVariant = true;
  setWapfValues(initialWapf, { triggerEvents: false, batchMode: true, phase: 'parents_only' });
  flushWapfDependencies();
  setWapfValues(initialWapf, { triggerEvents: false, batchMode: true, phase: 'non_parents_only' });
  window.TEINVIT_INVITATION_DATA = initialInvitation || (variants.find(v => String(v.id)===String(<?php echo (int)($current['id'] ?? 0); ?>)) || {}).invitation || null;
  runPreviewCycle({ skipFormRefresh: true });
  window.setTimeout(()=>{
    isApplyingVariant = false;
    refreshPreview();
  }, 0);

  document.querySelectorAll('.teinvit-variant-radio').forEach(r=>r.addEventListener('change',()=>applyVariant(r.value)));
  document.querySelectorAll('#teinvit-save-form input, #teinvit-save-form select, #teinvit-save-form textarea').forEach(el=>{
    el.addEventListener('input', refreshPreview); el.addEventListener('change', refreshPreview);
  });

  const saveForm = document.getElementById('teinvit-save-form');
  if(saveForm){
    const serializeParentCheckedState = ()=>{
      const payload = {};
      parentBooleanIds.forEach((id)=>{
        const checkboxes = Array.from(saveForm.querySelectorAll('[name="wapf[field_' + id + '][]"]')).filter((el)=>el && el.type === 'checkbox');
        payload[id] = checkboxes.some((el)=>el.checked) ? 1 : 0;
      });
      const holder = saveForm.querySelector('#teinvit-parent-checked-json');
      if (holder) {
        holder.value = JSON.stringify(payload);
      }
    };

    serializeParentCheckedState();
    saveForm.addEventListener('input', serializeParentCheckedState);
    saveForm.addEventListener('change', serializeParentCheckedState);

    saveForm.addEventListener('submit', function(e){
      serializeParentCheckedState();
      if(editsRemaining <= 0){ e.preventDefault(); return; }
      const msg = editsRemaining === 1
        ? 'Aceasta este ultima modificare disponibilă. Poți achiziționa altele oricând. Salvezi modificările?'
        : 'Această acțiune consumă o modificare disponibilă din ' + editsRemaining + '. Salvezi modificările?';
      if(!window.confirm(msg)){ e.preventDefault(); }
    });
  }
})();
</script>
