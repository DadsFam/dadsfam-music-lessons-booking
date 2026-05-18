<?php
/**
 * Plugin Name:  DadsFam Music Lessons Booking System
 * Plugin URI:   https://www.dadsfam.co.za
 * Description:  Complete music lesson booking system — teacher portal, overbooking prevention, email confirmations, custom form fields, colour customisation, and DadsFam license integration.
 * Version:      5.1.3
 * Author:       DadsFam
 * Author URI:   https://www.dadsfam.co.za
 * License:      GPL v2 or later
 * License URI:  https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:  dadsfam-mlb
 *
 * =============================================================================
 * CHANGELOG
 * =============================================================================
 *
 * v5.1.1 — Licensing pattern fixed + cancellation emails
 * -------------------------------------------------------
 * FIXED:   License check changed from 24-hour interval to HOURLY, matching
 *          the Invoice Manager and Site Lock plugins exactly.
 * ADDED:   WP Cron hourly job (dfmlb_hourly_verify) — low-traffic sites still
 *          get re-verified every hour even without an admin page load.
 * ADDED:   Transient DFMLB_LIC_TRANSIENT (HOUR_IN_SECONDS) for fast status
 *          reads between API calls — same pattern as Site Lock.
 * ADDED:   Force-lock REST endpoint POST /wp-json/dfmlb/v1/force-lock —
 *          dadsfam.co.za can suspend a key and lock this plugin instantly
 *          (authenticated with the stored license key, matching Invoice Manager).
 * ADDED:   Cancellation email sent to the student when teacher cancels a lesson
 *          from the portal — same HTML table layout, branded with site colours.
 *
 * v5.1.0 — DadsFam licensing + plugin rename
 * -------------------------------------------
 * CHANGED: Plugin renamed to "DadsFam Music Lessons Booking System".
 * ADDED:   DadsFam license integration — Settings → ⭐ License tab.
 * ADDED:   License key activation via POST to dadsfam.co.za REST API.
 * ADDED:   License status badge on admin Dashboard.
 * ADDED:   Force-lock infrastructure ready for Pro feature gating.
 * ADDED:   Deactivate license from Settings to free up an activation slot.
 *
 * v5.0.0 — Teacher portal management + multi-file rebuild
 * --------------------------------------------------------
 * ADDED:   Full booking management inside the teacher portal.
 * ADDED:   Search/filter, tabs, edit, cancel, delete in the teacher portal.
 * CHANGED: Rebuilt as proper multi-file plugin (includes/, shortcodes/, assets/).
 * FIXED:   Teacher login — DIRECT SQL password storage bypasses all caches.
 *
 * v4.2.1 — Critical password hash mismatch fix
 * v4.2.0 — WP native auth + colour customisation
 * v4.1.0 — Confirmation code + admin search
 * v4.0.0 — Email layout + admin tabs fix
 * v3.0.0 — Custom fields + DB auto-upgrade
 * v2.0.0 — Full UI redesign
 * v1.0.0 — Initial release
 *
 * (Full details in Lesson Booking → Changelog)
 * =============================================================================
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'MLB_VERSION',   '5.1.3' );
define( 'MLB_DIR',       plugin_dir_path( __FILE__ ) );
define( 'MLB_URL',       plugin_dir_url( __FILE__ ) );
define( 'MLB_PW_OPT',    'mlb_teacher_pw' );

// DadsFam Licensing constants
define( 'DFMLB_PRODUCT', 'dfmlb' );
define( 'DFMLB_LIC_OPT', 'dfmlb_license' );
define( 'DFMLB_API_URL', 'https://www.dadsfam.co.za/wp-json/dfem-licenses/v1/verify' );

// Load license first — registers force-lock endpoint and cron hook
require_once MLB_DIR . 'includes/license.php';

// Cron scheduling wired to activation/deactivation hooks
register_activation_hook( __FILE__,   'dfmlb_schedule_cron' );
register_deactivation_hook( __FILE__, 'dfmlb_deregister_cron' );

require_once MLB_DIR . 'includes/helpers.php';
require_once MLB_DIR . 'includes/db.php';
require_once MLB_DIR . 'includes/auth.php';
require_once MLB_DIR . 'includes/ajax.php';
require_once MLB_DIR . 'includes/email.php';
require_once MLB_DIR . 'includes/admin-menu.php';
require_once MLB_DIR . 'includes/admin-assets.php';
require_once MLB_DIR . 'includes/pages/dashboard.php';
require_once MLB_DIR . 'includes/pages/bookings.php';
require_once MLB_DIR . 'includes/pages/settings.php';
require_once MLB_DIR . 'includes/pages/blocked.php';
require_once MLB_DIR . 'includes/pages/changelog.php';
require_once MLB_DIR . 'includes/pages/help.php';
require_once MLB_DIR . 'shortcodes/booking-form.php';
require_once MLB_DIR . 'shortcodes/teacher-dashboard.php';
