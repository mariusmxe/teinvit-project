# Incident fix: redirect nejustificat din `/admin-client/{token}` către `/my-account/`

## Concluzie tehnică (cauză confirmată)

Cauza este **confirmată**:

- Form-urile din pagina `/admin-client/{token}` trimit toate către `admin-post.php` (`action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"`).
- Acțiunile trimise sunt:
  - `teinvit_save_invitation_info`
  - `teinvit_set_active_version`
  - `teinvit_save_rsvp_config`
  - `teinvit_save_version_snapshot`
  - `teinvit_save_gifts`
- În backend există handler-ele WordPress `admin_post_*` pentru exact aceste acțiuni.

Dacă există un snippet global care face redirect pe `init` pentru rolurile `customer/subscriber` atunci când `is_admin() === true` (cu excepția AJAX), cererile către `/wp-admin/admin-post.php` sunt redirecționate înainte să ajungă la handler-ele `admin_post_teinvit_*`.

Rezultatul este exact simptomul din producție:
- click pe buton în `/admin-client/{token}`
- redirect instant în `/my-account/`
- acțiunea nu rulează (nu se salvează/publică nimic)

## Endpoint-uri și acțiuni TeInvit folosite acum

### Endpoint principal folosit de butoanele din `/admin-client/{token}`
- `/wp-admin/admin-post.php`

### Action whitelist recomandat (strict)
- `teinvit_save_invitation_info`
- `teinvit_save_rsvp_config`
- `teinvit_set_active_version`
- `teinvit_save_version_snapshot`
- `teinvit_save_gifts`
- `teinvit_export_guest_report` (export raport)

## Fix recomandat (WPCode snippet, minim invaziv + sigur)

Înlocuiește snippet-ul existent cu varianta de mai jos (copy/paste 1:1):

```php
add_action('init', function () {
    if (!is_user_logged_in()) {
        return;
    }

    $user = wp_get_current_user();
    if (!$user || !$user->ID) {
        return;
    }

    $blocked_roles = ['customer', 'subscriber'];
    if (!array_intersect((array) $user->roles, $blocked_roles)) {
        return;
    }

    if (!is_admin()) {
        return;
    }

    $script = isset($_SERVER['SCRIPT_NAME']) ? wp_basename(wp_unslash($_SERVER['SCRIPT_NAME'])) : '';
    $action = isset($_REQUEST['action']) ? sanitize_key(wp_unslash($_REQUEST['action'])) : '';

    // Allowlist strict pentru flow-ul TeInvit din /admin-client/{token}
    $allowed_admin_post_actions = [
        'teinvit_save_invitation_info',
        'teinvit_save_rsvp_config',
        'teinvit_set_active_version',
        'teinvit_save_version_snapshot',
        'teinvit_save_gifts',
        'teinvit_export_guest_report',
    ];

    $allowed_admin_ajax_actions = [
        // momentan gol; adaugi aici doar dacă apar acțiuni TeInvit pe admin-ajax
    ];

    // Permite strict admin-post.php pentru acțiunile TeInvit whitelisted.
    if ($script === 'admin-post.php' && in_array($action, $allowed_admin_post_actions, true)) {
        return;
    }

    // Permite strict admin-ajax.php pentru acțiuni TeInvit explicit whitelisted.
    if ($script === 'admin-ajax.php' && in_array($action, $allowed_admin_ajax_actions, true)) {
        return;
    }

    // Blochează tot restul din wp-admin pentru customer/subscriber.
    $my_account_id = (int) get_option('woocommerce_myaccount_page_id');
    $redirect_url  = $my_account_id ? get_permalink($my_account_id) : home_url('/');
    wp_safe_redirect($redirect_url);
    exit;
}, 1);

add_filter('show_admin_bar', function ($show) {
    if (!is_user_logged_in()) {
        return $show;
    }

    $user = wp_get_current_user();
    if (!$user || !$user->ID) {
        return $show;
    }

    if (array_intersect((array) $user->roles, ['customer', 'subscriber'])) {
        return false;
    }

    return $show;
}, 10, 1);
```

## De ce fixul păstrează securitatea

- **Nu deschide wp-admin** pentru customer/subscriber.
- Permite doar endpoint-ul tehnic necesar (`admin-post.php`) și doar pe acțiuni TeInvit explicite.
- Orice alt URL din `/wp-admin/*` continuă să fie redirecționat la `/my-account/`.
- Verificările de securitate TeInvit rămân active în handler:
  - nonce (`wp_verify_nonce`)
  - token→order ownership (`$order->get_user_id() === get_current_user_id()`).

## Pași de validare după deploy (acceptance)

1. Client logat → `/admin-client/{token}` → **Publică data limită**
   - nu mai redirecționează la `/my-account/`
   - se vede data publicată în `/invitati/{token}`
2. Client logat → **Salvează modificările**
   - se creează snapshot/variantă nouă
3. Client logat → **Publică selecțiile**
   - se salvează configurația RSVP
4. Client logat → **Salvează lista**
   - se salvează/publică lista de cadouri
5. Client logat → acces direct `/wp-admin/`
   - este redirecționat în continuare la `/my-account/`

## Notă privind TeInvit core

Nu este necesară modificare în codul TeInvit pentru acest incident, deoarece fluxul existent este corect; blocajul este introdus de snippet-ul global de securizare care nu face excepție pentru acțiunile TeInvit din `admin-post.php`.
