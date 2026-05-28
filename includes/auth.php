<?php
/**
 * Teacher authentication.
 *
 * PASSWORD STRATEGY  (v5.0.0)
 * ============================
 * All previous versions failed because they relied on WordPress's option cache
 * (get_option / update_option) which can be intercepted by Redis, Memcached,
 * APCu, or the default WP object cache — meaning a newly-saved hash may not
 * be what the login check reads back.
 *
 * v5 writes and reads the password hash via DIRECT SQL on the options table,
 * bypassing every cache layer. PHP's built-in password_hash / password_verify
 * (bcrypt) is used instead of wp_hash_password — no WordPress functions involved.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

// ── Store password (direct SQL, no cache) ──────────────────────────────────
function mlb_save_teacher_password( string $plaintext ): bool {
    if ( $plaintext === '' ) return false;
    global $wpdb;
    $hash = password_hash( $plaintext, PASSWORD_BCRYPT );
    $exists = $wpdb->get_var(
        $wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name=%s", MLB_PW_OPT)
    );
    if ( $exists ) {
        $wpdb->query( $wpdb->prepare(
            "UPDATE {$wpdb->options} SET option_value=%s WHERE option_name=%s",
            $hash, MLB_PW_OPT
        ));
    } else {
        $wpdb->query( $wpdb->prepare(
            "INSERT INTO {$wpdb->options} (option_name,option_value,autoload) VALUES(%s,%s,'no')",
            MLB_PW_OPT, $hash
        ));
    }
    // Purge all caches so the next read is fresh
    wp_cache_delete( MLB_PW_OPT, 'options' );
    wp_cache_delete( 'alloptions', 'options' );
    return true;
}

// ── Verify password (direct SQL, no cache) ────────────────────────────────
function mlb_verify_teacher_password( string $plaintext ): bool {
    if ( $plaintext === '' ) return false;
    global $wpdb;

    // Read directly from the database — zero caching
    $hash = $wpdb->get_var(
        $wpdb->prepare("SELECT option_value FROM {$wpdb->options} WHERE option_name=%s", MLB_PW_OPT)
    );

    // Legacy fallback: password was stored inside the settings array in v1-v4
    if ( empty( $hash ) ) {
        $settings_serial = $wpdb->get_var(
            $wpdb->prepare("SELECT option_value FROM {$wpdb->options} WHERE option_name=%s", 'mlb_settings')
        );
        if ( $settings_serial ) {
            $s = maybe_unserialize( $settings_serial );
            $hash = $s['teacher_password'] ?? '';
        }
    }

    // Nothing saved at all → accept default
    if ( empty( $hash ) ) return ( $plaintext === 'teacher123' );

    // bcrypt hash (our format from v5)
    if ( str_starts_with($hash, '$2') ) return password_verify( $plaintext, $hash );

    // PHPass hash (WordPress format, used in v1–v4)
    if ( str_starts_with($hash, '$P$') ) return (bool) wp_check_password( $plaintext, $hash );

    // Last resort: direct string compare (handles any edge-case plain-text storage)
    return hash_equals( $hash, $plaintext );
}

// ── Migrate old hash on activation / upgrade ──────────────────────────────
function mlb_migrate_password_if_needed(): void {
    global $wpdb;
    $already = $wpdb->get_var(
        $wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name=%s", MLB_PW_OPT)
    );
    if ( $already ) return; // already migrated

    // Check if the old settings hash is for the default password
    $settings_serial = $wpdb->get_var(
        $wpdb->prepare("SELECT option_value FROM {$wpdb->options} WHERE option_name=%s", 'mlb_settings')
    );
    // If no old hash, set default
    $old_hash = '';
    if ( $settings_serial ) {
        $s = maybe_unserialize($settings_serial);
        $old_hash = $s['teacher_password'] ?? '';
    }
    if ( empty($old_hash) ) {
        mlb_save_teacher_password('teacher123');
    }
    // If there IS an old hash, leave MLB_PW_OPT empty so mlb_verify_teacher_password
    // falls back to the legacy check automatically until the teacher resets their password.
}

// ── WP auth user for wp_set_auth_cookie ───────────────────────────────────
function mlb_ensure_teacher_user(): int {
    $uid = (int) get_option('mlb_teacher_uid');
    if ( $uid && get_user_by('id', $uid) ) return $uid;

    $host = parse_url(home_url(), PHP_URL_HOST);
    $slug = substr(md5($host.(defined('AUTH_KEY')?AUTH_KEY:'')), 0, 10);
    $un   = 'mlb_teacher_'.$slug;
    $em   = 'mlb_'.$slug.'@local.invalid';
    if ( username_exists($un) ) $un .= '_'.wp_rand(100,999);
    if ( email_exists($em) )    $em  = 'mlb_'.wp_rand(1000,9999).'@local.invalid';

    $uid = wp_create_user($un, wp_generate_password(32), $em);
    if ( is_wp_error($uid) ) return 0;

    $u = new WP_User($uid);
    $u->set_role('subscriber');
    wp_update_user(['ID'=>$uid,'display_name'=>'Music Teacher','nickname'=>'Teacher']);
    update_user_meta($uid, 'mlb_is_teacher', 1);
    update_option('mlb_teacher_uid', $uid);

    // Ensure default password is also set
    mlb_migrate_password_if_needed();
    return $uid;
}

function mlb_teacher_ok(): bool {
    if (!is_user_logged_in()) return false;
    $tid = (int) get_option('mlb_teacher_uid');
    return $tid && get_current_user_id() === $tid;
}

// ── LOGIN / LOGOUT (init priority 1 — before any output) ──────────────────
add_action('init', 'mlb_handle_auth', 1);
function mlb_handle_auth(): void {

    /* LOGIN */
    if ( isset($_POST['mlb_do_login']) ) {
        $back = isset($_POST['_mlb_page']) ? esc_url_raw(wp_unslash($_POST['_mlb_page'])) : home_url();
        if ( !isset($_POST['_mlb_ln']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_mlb_ln'])), 'mlb_teacher_login') ) {
            wp_safe_redirect(add_query_arg('mlb_err','nonce',$back)); exit;
        }
        // Raw password — NO sanitization, NO slashing changes
        $pw = isset($_POST['mlb_tpw']) ? (string) wp_unslash($_POST['mlb_tpw']) : '';

        if ( mlb_verify_teacher_password($pw) ) {
            $uid = mlb_ensure_teacher_user();
            if ( $uid ) {
                wp_clear_auth_cookie();
                wp_set_current_user($uid);
                wp_set_auth_cookie($uid, true);
                wp_safe_redirect(remove_query_arg('mlb_err',$back)); exit;
            }
            wp_safe_redirect(add_query_arg('mlb_err','system',$back)); exit;
        }
        wp_safe_redirect(add_query_arg('mlb_err','wrong_pw',$back)); exit;
    }

    /* LOGOUT */
    if ( isset($_POST['mlb_do_logout']) ) {
        $back = isset($_POST['_mlb_page']) ? esc_url_raw(wp_unslash($_POST['_mlb_page'])) : home_url();
        if ( isset($_POST['_mlb_ln']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_mlb_ln'])), 'mlb_teacher_logout') ) {
            wp_logout();
        }
        wp_safe_redirect(remove_query_arg('mlb_err',$back)); exit;
    }
}

// Block teacher user from /wp-admin/
add_action('admin_init', function(){
    if (defined('DOING_AJAX') && DOING_AJAX) return;
    $tid = (int) get_option('mlb_teacher_uid');
    if ($tid && get_current_user_id() === $tid) { wp_safe_redirect(home_url()); exit; }
});

// Hide admin bar for teacher
add_filter('show_admin_bar', function($show){
    $tid = (int) get_option('mlb_teacher_uid');
    if (is_user_logged_in() && get_current_user_id() === $tid) return false;
    return $show;
});

// Hide teacher user from WP Users list
add_action('pre_user_query', function($q){
    if (!is_admin()) return;
    $tid = (int) get_option('mlb_teacher_uid');
    if ($tid) { global $wpdb; $q->query_where .= " AND {$wpdb->users}.ID != $tid"; }
});

// ── AJAX: test & reset password (admin diagnostic tools) ──────────────────
add_action('wp_ajax_mlb_test_password', function(){
    check_ajax_referer('mlb_diag');
    if (!current_user_can('manage_options')) wp_send_json_error('Permission denied');
    $pw = isset($_POST['pw']) ? (string) wp_unslash($_POST['pw']) : '';
    if ($pw==='') wp_send_json_error('Enter a password to test');
    wp_send_json( mlb_verify_teacher_password($pw)
        ? ['success'=>true, 'data'=>'✅ MATCH — this password will work on the portal']
        : ['success'=>false,'data'=>'❌ No match — this password does NOT match what is saved'] );
});

add_action('wp_ajax_mlb_reset_password', function(){
    check_ajax_referer('mlb_diag');
    if (!current_user_can('manage_options')) wp_send_json_error('Permission denied');
    mlb_save_teacher_password('teacher123');
    $ok = mlb_verify_teacher_password('teacher123');
    wp_send_json($ok
        ? ['success'=>true, 'data'=>'✅ Reset & verified! Go log in with: teacher123']
        : ['success'=>false,'data'=>'⚠️ Reset saved but verify failed — contact host about object cache config']);
});

add_action('wp_ajax_mlb_save_custom_pw', function(){
    check_ajax_referer('mlb_diag');
    if (!current_user_can('manage_options')) wp_send_json_error('Permission denied');
    $pw = isset($_POST['pw']) ? (string) wp_unslash($_POST['pw']) : '';
    if (strlen($pw) < 4) wp_send_json_error('Password must be at least 4 characters');
    mlb_save_teacher_password($pw);
    $ok = mlb_verify_teacher_password($pw);
    wp_send_json($ok
        ? ['success'=>true, 'data'=>'✅ Password saved & verified! Try it on the portal now.']
        : ['success'=>false,'data'=>'⚠️ Saved but verify failed. Object cache issue on your host.']);
});

// =============================================================================
// EMAIL OTP LOGIN  (v5.1.3)
// =============================================================================
// Flow: "Send Login Code" → 6-digit code emailed → teacher types it in → login
// Security: bcrypt-hashed storage, 10-min expiry, 5-attempt lockout, 60s
//           rate-limit on requests, single-use (deleted on success)
// Fallback: password login still available via toggle link
// =============================================================================

define( 'MLB_OTP_TTL',      600 );   // code valid for 10 minutes
define( 'MLB_OTP_MAX_ATT',  5   );   // max wrong attempts before lockout
define( 'MLB_OTP_RATE_SEC', 60  );   // minimum seconds between code requests

// ── Generate, hash, and store a new OTP ──────────────────────────────────────
function mlb_otp_create(): string {
    $code = str_pad( (string) random_int( 0, 999999 ), 6, '0', STR_PAD_LEFT );
    set_transient( 'mlb_otp_hash',     password_hash( $code, PASSWORD_BCRYPT ), MLB_OTP_TTL );
    set_transient( 'mlb_otp_ts',       time(),                                  MLB_OTP_TTL );
    set_transient( 'mlb_otp_rate',     time(),                                  MLB_OTP_RATE_SEC );
    delete_transient( 'mlb_otp_attempts' ); // fresh attempt counter
    return $code;
}

// ── Verify an OTP input — returns true on success, false on failure ───────────
function mlb_otp_verify( string $input ): bool {
    $hash     = get_transient( 'mlb_otp_hash' );
    if ( ! $hash ) return false; // expired or never requested

    $attempts = (int) get_transient( 'mlb_otp_attempts' );
    if ( $attempts >= MLB_OTP_MAX_ATT ) return false; // locked out

    if ( password_verify( $input, $hash ) ) {
        // Single-use: wipe everything immediately
        delete_transient( 'mlb_otp_hash' );
        delete_transient( 'mlb_otp_ts' );
        delete_transient( 'mlb_otp_attempts' );
        delete_transient( 'mlb_otp_rate' );
        return true;
    }

    set_transient( 'mlb_otp_attempts', $attempts + 1, MLB_OTP_TTL );
    return false;
}

// ── Send the OTP email ────────────────────────────────────────────────────────
function mlb_otp_send_email( string $code ): bool {
    $to  = mlb_setting( 'teacher_email', get_option('admin_email') );
    $biz = mlb_setting( 'business_name', get_bloginfo('name') );
    $cp  = mlb_color( 'color_primary',   '#e36868' );
    $cs  = mlb_color( 'color_secondary', '#c2492d' );

    // Large spaced-out code for easy reading on phone
    $display = implode( ' ', str_split( $code ) ); // "1 2 3 4 5 6"

    $body = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head>
<body style="margin:0;padding:28px;background:#fdf2e9;font-family:Arial,Helvetica,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="max-width:480px;margin:0 auto;"><tr><td>
<table width="100%" cellpadding="0" cellspacing="0"
    style="background:linear-gradient(135deg,'.$cp.','.$cs.');border-radius:12px 12px 0 0;">
<tr><td style="padding:24px;text-align:center;">
    <div style="font-size:30px;margin-bottom:6px;">🔐</div>
    <div style="font-size:17px;font-weight:800;color:#fff;">Your Login Code</div>
    <div style="font-size:12px;color:#fff;opacity:.88;margin-top:3px;">'.esc_html($biz).' — Teacher Portal</div>
</td></tr></table>
<table width="100%" cellpadding="0" cellspacing="0"
    style="background:#fff;border-radius:0 0 12px 12px;"><tr><td style="padding:28px;">
    <p style="margin:0 0 20px;font-size:14px;color:#475569;text-align:center;">
        Use the code below to log in. It expires in <strong>10 minutes</strong> and can only be used once.
    </p>
    <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:20px;">
    <tr><td style="background:#1e293b;border-radius:10px;padding:20px;text-align:center;">
        <div style="font-size:11px;color:#94a3b8;letter-spacing:3px;text-transform:uppercase;margin-bottom:8px;">Login Code</div>
        <div style="font-size:38px;font-weight:900;color:#fff;letter-spacing:12px;font-family:monospace;">'.esc_html($code).'</div>
        <div style="font-size:13px;color:#64748b;margin-top:6px;letter-spacing:2px;">'.esc_html($display).'</div>
    </td></tr>
    </table>
    <div style="background:#fef3c7;border-left:4px solid #f59e0b;border-radius:8px;padding:12px 14px;margin-bottom:16px;">
        <p style="margin:0;font-size:13px;color:#92400e;font-weight:700;">
            ⚠️ If you did not request this code, ignore this email. Your account is still secure.
        </p>
    </div>
    <p style="margin:0;font-size:12px;color:#94a3b8;text-align:center;">
        This code expires in 10 minutes and is single-use.
    </p>
</td></tr></table>
</td></tr></table>
</body></html>';

    return (bool) wp_mail( $to, '🔐 Your login code — ' . $biz, $body, ['Content-Type: text/html; charset=UTF-8'] );
}

// ── Hook OTP handlers into the existing mlb_handle_auth init action ───────────
// We add separately so the original mlb_handle_auth still handles password + logout

add_action( 'init', 'mlb_handle_otp', 1 );
function mlb_handle_otp(): void {

    /* ── SEND CODE ── */
    if ( isset( $_POST['mlb_send_otp'] ) ) {
        $back = isset($_POST['_mlb_page']) ? esc_url_raw(wp_unslash($_POST['_mlb_page'])) : home_url();
        if ( ! isset($_POST['_mlb_ln']) || ! wp_verify_nonce( sanitize_text_field(wp_unslash($_POST['_mlb_ln'])), 'mlb_send_otp' ) ) {
            wp_safe_redirect( add_query_arg( 'mlb_err', 'nonce', $back ) ); exit;
        }
        // Rate limit
        if ( get_transient( 'mlb_otp_rate' ) ) {
            wp_safe_redirect( add_query_arg( ['mlb_step'=>'otp','mlb_err'=>'rate'], remove_query_arg('mlb_err',$back) ) ); exit;
        }
        $code = mlb_otp_create();
        $sent = mlb_otp_send_email( $code );
        if ( ! $sent ) {
            wp_safe_redirect( add_query_arg( 'mlb_err', 'send_fail', $back ) ); exit;
        }
        wp_safe_redirect( add_query_arg( 'mlb_step', 'otp', remove_query_arg( ['mlb_err','mlb_step'], $back ) ) ); exit;
    }

    /* ── VERIFY CODE ── */
    if ( isset( $_POST['mlb_verify_otp'] ) ) {
        $back = isset($_POST['_mlb_page']) ? esc_url_raw(wp_unslash($_POST['_mlb_page'])) : home_url();
        if ( ! isset($_POST['_mlb_ln']) || ! wp_verify_nonce( sanitize_text_field(wp_unslash($_POST['_mlb_ln'])), 'mlb_verify_otp' ) ) {
            wp_safe_redirect( add_query_arg( ['mlb_step'=>'otp','mlb_err'=>'nonce'], $back ) ); exit;
        }
        $input = preg_replace( '/\D/', '', $_POST['mlb_otp_code'] ?? '' );
        if ( strlen( $input ) !== 6 ) {
            wp_safe_redirect( add_query_arg( ['mlb_step'=>'otp','mlb_err'=>'wrong'], $back ) ); exit;
        }
        if ( mlb_otp_verify( $input ) ) {
            $uid = mlb_ensure_teacher_user();
            if ( $uid ) {
                wp_clear_auth_cookie();
                wp_set_current_user( $uid );
                wp_set_auth_cookie( $uid, true );
                wp_safe_redirect( remove_query_arg( ['mlb_step','mlb_err'], $back ) ); exit;
            }
            wp_safe_redirect( add_query_arg( ['mlb_step'=>'otp','mlb_err'=>'system'], $back ) ); exit;
        }
        // Failed — check if now locked out
        $att = (int) get_transient( 'mlb_otp_attempts' );
        $err = ( $att >= MLB_OTP_MAX_ATT ) ? 'locked' : 'wrong';
        wp_safe_redirect( add_query_arg( ['mlb_step'=>'otp','mlb_err'=>$err], $back ) ); exit;
    }
}
