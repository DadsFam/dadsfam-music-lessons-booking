<?php
/**
 * DadsFam Licensing — Music Lessons Booking System
 *
 * PATTERN (matches Invoice Manager + Site Lock plugins exactly)
 * ─────────────────────────────────────────────────────────────
 * • Transient DFMLB_LIC_TRANSIENT caches the status for HOUR_IN_SECONDS.
 *   On expiry the next admin page-load re-verifies from the API automatically.
 * • WP Cron fires every hour so low-traffic sites still get checked even if
 *   no admin is logged in.
 * • Force-lock REST endpoint (POST /wp-json/dfmlb/v1/force-lock) lets the
 *   dadsfam.co.za License Manager instantly invalidate a suspended key — the
 *   transient is deleted and status is set to 'suspended' immediately.
 * • All persistent state lives in option DFMLB_LIC_OPT (not in settings array).
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'DFMLB_LIC_TRANSIENT', 'dfmlb_lic_status' );   // 1-hour status cache
define( 'DFMLB_CRON_HOOK',     'dfmlb_hourly_verify' ); // WP Cron hook name

// ── Activation / deactivation (cron scheduling) ──────────────────────────────
function dfmlb_schedule_cron(): void {
    if ( ! wp_next_scheduled( DFMLB_CRON_HOOK ) ) {
        wp_schedule_event( time(), 'hourly', DFMLB_CRON_HOOK );
    }
}
function dfmlb_deregister_cron(): void {
    wp_clear_scheduled_hook( DFMLB_CRON_HOOK );
}

// WP Cron callback — verifies against API every hour regardless of traffic
add_action( DFMLB_CRON_HOOK, 'dfmlb_cron_verify' );
function dfmlb_cron_verify(): void {
    $lic = dfmlb_get_license();
    if ( empty( $lic['key'] ) ) return;

    $result = dfmlb_call_api( $lic['key'] );
    $valid  = ! empty( $result['valid'] );

    $lic['status']      = $valid ? 'active' : ( str_contains( strtolower( $result['message'] ?? '' ), 'suspend' ) ? 'suspended' : 'invalid' );
    $lic['message']     = $result['message'] ?? '';
    $lic['expires']     = $valid ? ( $result['expires'] ?? 'never' ) : $lic['expires'];
    $lic['verified_at'] = time();
    update_option( DFMLB_LIC_OPT, $lic );

    // Refresh transient so next admin page load uses the fresh status
    set_transient( DFMLB_LIC_TRANSIENT, $lic['status'], HOUR_IN_SECONDS );
}

// ── Force-lock REST endpoint ──────────────────────────────────────────────────
// Called from dadsfam.co.za when a key is suspended/revoked.
// POST /wp-json/dfmlb/v1/force-lock   body: { key: "<license_key>" }
add_action( 'rest_api_init', 'dfmlb_register_force_lock' );
function dfmlb_register_force_lock(): void {
    register_rest_route( 'dfmlb/v1', '/force-lock', [
        'methods'             => 'POST',
        'callback'            => 'dfmlb_rest_force_lock',
        'permission_callback' => '__return_true',
    ]);
}
function dfmlb_rest_force_lock( WP_REST_Request $request ): WP_REST_Response {
    $provided   = sanitize_text_field( $request->get_param('key') ?? '' );
    $lic        = dfmlb_get_license();
    $stored_key = $lic['key'] ?? '';

    // Authenticate with the stored license key so random calls are ignored
    if ( empty( $provided ) || empty( $stored_key ) || ! hash_equals( $stored_key, $provided ) ) {
        return new WP_REST_Response( ['success' => false, 'message' => 'Invalid key'], 403 );
    }

    // Immediately mark as suspended and clear the transient cache
    delete_transient( DFMLB_LIC_TRANSIENT );
    $lic['status']      = 'suspended';
    $lic['message']     = 'License remotely suspended by DadsFam.';
    $lic['verified_at'] = time();
    update_option( DFMLB_LIC_OPT, $lic );

    return new WP_REST_Response( ['success' => true, 'message' => 'Plugin locked instantly'], 200 );
}

// ── License data helper ───────────────────────────────────────────────────────
function dfmlb_get_license(): array {
    return wp_parse_args( get_option( DFMLB_LIC_OPT, [] ), [
        'key'         => '',
        'status'      => 'unlicensed',
        'message'     => '',
        'product'     => '',
        'expires'     => '',
        'verified_at' => 0,
    ]);
}

// ── Hourly transient check (matches Site Lock pattern exactly) ────────────────
function dfmlb_is_licensed(): bool {
    // Fast path — trust the transient for up to 1 hour
    $cached = get_transient( DFMLB_LIC_TRANSIENT );
    if ( $cached !== false ) return $cached === 'active';

    // Transient expired → call API now and cache result for 1 hour
    $lic = dfmlb_get_license();
    if ( empty( $lic['key'] ) ) {
        set_transient( DFMLB_LIC_TRANSIENT, 'unlicensed', HOUR_IN_SECONDS );
        return false;
    }
    $result = dfmlb_call_api( $lic['key'] );
    $valid  = ! empty( $result['valid'] );
    $status = $valid ? 'active'
            : ( str_contains( strtolower( $result['message'] ?? '' ), 'suspend' ) ? 'suspended' : 'invalid' );

    $lic['status']      = $status;
    $lic['message']     = $result['message'] ?? '';
    $lic['expires']     = $valid ? ( $result['expires'] ?? 'never' ) : $lic['expires'];
    $lic['verified_at'] = time();
    update_option( DFMLB_LIC_OPT, $lic );
    set_transient( DFMLB_LIC_TRANSIENT, $status, HOUR_IN_SECONDS );

    return $valid;
}

// ── Call the DadsFam license server ──────────────────────────────────────────
function dfmlb_call_api( string $key ): array {
    $response = wp_remote_post( DFMLB_API_URL, [
        'timeout'   => 10,
        'sslverify' => true,
        'body'      => [
            'license_key' => sanitize_text_field( $key ),
            'site_url'    => home_url(),
            'plugin_ver'  => MLB_VERSION,
        ],
    ]);
    if ( is_wp_error( $response ) ) {
        return ['valid' => false, 'message' => 'Could not reach the license server.'];
    }
    $body = json_decode( wp_remote_retrieve_body( $response ), true );
    return is_array( $body ) ? $body : ['valid' => false, 'message' => 'Invalid server response.'];
}

// ── Activate ─────────────────────────────────────────────────────────────────
function dfmlb_activate( string $key ): array {
    $key = strtoupper( sanitize_text_field( trim( $key ) ) );
    if ( empty( $key ) ) return ['success' => false, 'message' => 'Please enter a license key.'];

    $result = dfmlb_call_api( $key );

    if ( ! empty( $result['valid'] ) ) {
        update_option( DFMLB_LIC_OPT, [
            'key'         => $key,
            'status'      => 'active',
            'message'     => $result['message'] ?? 'License is valid.',
            'product'     => $result['product'] ?? DFMLB_PRODUCT,
            'expires'     => $result['expires']  ?? 'never',
            'verified_at' => time(),
        ]);
        set_transient( DFMLB_LIC_TRANSIENT, 'active', HOUR_IN_SECONDS );
        dfmlb_schedule_cron(); // ensure cron is running
        return ['success' => true, 'message' => '✅ License activated! Thank you for supporting DadsFam.'];
    }

    $status = str_contains( strtolower( $result['message'] ?? '' ), 'suspend' ) ? 'suspended' : 'invalid';
    update_option( DFMLB_LIC_OPT, [
        'key'         => $key,
        'status'      => $status,
        'message'     => $result['message'] ?? 'License key is not valid.',
        'product'     => '',
        'expires'     => '',
        'verified_at' => time(),
    ]);
    set_transient( DFMLB_LIC_TRANSIENT, $status, HOUR_IN_SECONDS );
    return ['success' => false, 'message' => $result['message'] ?? 'License key is not valid.'];
}

// ── Deactivate ────────────────────────────────────────────────────────────────
function dfmlb_deactivate(): void {
    update_option( DFMLB_LIC_OPT, [
        'key'         => '',
        'status'      => 'unlicensed',
        'message'     => '',
        'product'     => '',
        'expires'     => '',
        'verified_at' => 0,
    ]);
    delete_transient( DFMLB_LIC_TRANSIENT );
}

// ── Admin notice if license invalid / suspended ───────────────────────────────
add_action( 'admin_notices', function () {
    $scr = get_current_screen();
    if ( ! $scr || strpos( $scr->id, 'mlb' ) === false ) return;
    $lic = dfmlb_get_license();
    if ( in_array( $lic['status'], ['invalid','suspended'], true ) && ! empty( $lic['key'] ) ) {
        $msg = esc_html( $lic['message'] ?: 'License verification failed.' );
        $url = esc_url( admin_url('admin.php?page=mlb-settings') );
        echo '<div class="notice notice-warning is-dismissible"><p>'
            . '🔑 <strong>DadsFam Music Lessons Booking:</strong> '
            . $msg
            . ' <a href="' . $url . '" onclick="localStorage.setItem(\'mlb_tab_s\',\'lic\')">'
            . 'Manage license →</a></p></div>';
    }
});

// ── AJAX: Activate ────────────────────────────────────────────────────────────
add_action( 'wp_ajax_dfmlb_activate', function () {
    check_ajax_referer( 'dfmlb_lic_nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Permission denied.' );
    $r = dfmlb_activate( sanitize_text_field( wp_unslash( $_POST['key'] ?? '' ) ) );
    wp_send_json([ 'success' => $r['success'], 'data' => $r['message'] ]);
});

// ── AJAX: Deactivate ──────────────────────────────────────────────────────────
add_action( 'wp_ajax_dfmlb_deactivate', function () {
    check_ajax_referer( 'dfmlb_lic_nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Permission denied.' );
    dfmlb_deactivate();
    wp_send_json_success( 'License deactivated.' );
});

// ── AJAX: Force re-verify now ─────────────────────────────────────────────────
add_action( 'wp_ajax_dfmlb_reverify', function () {
    check_ajax_referer( 'dfmlb_lic_nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Permission denied.' );
    $lic = dfmlb_get_license();
    if ( empty( $lic['key'] ) ) wp_send_json_error( 'No license key saved.' );

    delete_transient( DFMLB_LIC_TRANSIENT ); // force fresh check
    $valid = dfmlb_is_licensed();             // will call API & cache result
    $lic   = dfmlb_get_license();             // re-read updated status

    if ( $valid ) wp_send_json_success( '✅ Verified — license is active.' );
    wp_send_json_error( $lic['message'] ?: 'License verification failed.' );
});
