<?php
if(!defined('ABSPATH')) exit;
function mlb_pg_changelog(){
    echo '<div class="wrap"><div class="mlb-hd"><div class="mlb-hd-i">📜</div><div><h1>Changelog</h1><p>Full version history for DadsFam Music Lessons Booking System</p></div></div>';
    $versions=[
        ['5.1.3','🔐 Email OTP login','2025',[
            'ADDED:   Email OTP (one-time password) login for the teacher portal.',
            'CHANGED: Default login is now "📧 Send Login Code" — a 6-digit code is emailed to the teacher\'s registered address.',
            'ADDED:   6-digit code input with individual boxes, auto-advance between digits, paste support, and arrow key navigation.',
            'ADDED:   Live countdown timer — turns amber at 60 seconds, red at 30 seconds, disables submit on expiry.',
            'ADDED:   Resend code button (appears on OTP step) with 60-second rate limit to prevent spam.',
            'ADDED:   Maximum 5 wrong attempts before lockout — prevents brute-force guessing.',
            'ADDED:   OTP is bcrypt-hashed before storage — plaintext code never touches the database.',
            'ADDED:   Single-use — OTP is deleted immediately on successful verification.',
            'ADDED:   Password login kept as a "🔑 Use password instead" fallback toggle, hidden by default.',
        ]],
        ['5.1.2','⚙️ Teacher portal settings','2025',[
            'ADDED:   ⚙️ My Settings tab in the teacher portal — teachers manage their own schedule without needing WP Admin access.',
            'ADDED:   📅 Lesson Schedule settings: lesson duration, break between lessons (prep/toilet time), advance booking window.',
            'ADDED:   Teaching days & hours: teacher can enable/disable each day of the week and set open/close times per day.',
            'ADDED:   💰 Rates & Instruments: teacher sets price per lesson, currency symbol, and which instruments they teach.',
            'ADDED:   🚫 Days Off & Blocked Dates: teacher adds/removes blocked dates directly from the portal with optional reason.',
            'CHANGED: Settings tab hides the search bar and bookings table cleanly; each section saves independently via AJAX.',
        ]],
        ['5.1.1','🔑 Licensing hourly checks + cancellation emails','2025',[
            'FIXED:   License check interval changed from 24 hours to HOURLY — matching the Invoice Manager and Site Lock plugins exactly.',
            'ADDED:   WP Cron job (dfmlb_hourly_verify) fires every hour so even low-traffic sites are re-verified regularly.',
            'ADDED:   Transient (HOUR_IN_SECONDS) for fast license reads between API calls — transient expiry triggers the next API call automatically.',
            'ADDED:   Force-lock REST endpoint POST /wp-json/dfmlb/v1/force-lock — dadsfam.co.za License Manager can instantly suspend a key; transient is cleared and status set to suspended immediately.',
            'ADDED:   Force-lock authenticated with the stored license key (same approach as Invoice Manager).',
            'ADDED:   Cancellation email sent to the student automatically when teacher cancels a lesson from the portal.',
            'ADDED:   Cancellation email uses same branded HTML table layout as the confirmation email but with a grey header to visually distinguish it.',
            'ADDED:   Cancellation email includes a yellow notice prompting the student to contact and reschedule, with their reference code.',
        ]],
        ['5.1.0','🔑 DadsFam licensing + plugin rename','2025',[
            'CHANGED: Plugin renamed from "Music Lesson Booking System" to "DadsFam Music Lessons Booking System".',
            'ADDED:   DadsFam license integration — Settings → ⭐ License tab.',
            'ADDED:   License activation via POST to dadsfam.co.za REST API (dfmlb product).',
            'ADDED:   License status badge on admin Dashboard (Licensed / Unlicensed / Invalid / Check Failed).',
            'ADDED:   Silent background re-verify every 24 hours — keeps license status current without manual action.',
            'ADDED:   Admin notice shown if license becomes invalid or suspended after a re-verify.',
            'ADDED:   Deactivate button frees up an activation slot on the server.',
            'ADDED:   Re-check Now button for instant manual verification.',
            'ADDED:   Force-lock infrastructure fully in place — ready for Pro feature gating (no features locked yet).',
            'ADDED:   Free vs Pro tier comparison shown in the License tab.',
        ]],
        ['5.0.0','🎛 Teacher portal management + multi-file rebuild','2025',[
            'ADDED:   Full booking management inside the teacher portal — no WP admin needed.',
            'ADDED:   Search bar in portal — find by name, email, phone, or confirmation code.',
            'ADDED:   Four tabs: Upcoming | Today | All | Cancelled (AJAX, no page reload).',
            'ADDED:   Filter by instrument and date range in the portal.',
            'ADDED:   Edit panel — teacher changes all booking details from the portal.',
            'ADDED:   Cancel button — marks cancelled with confirmation, refreshes table instantly.',
            'ADDED:   Delete button — permanently removes with double-confirmation.',
            'CHANGED: Rebuilt as proper multi-file plugin (includes/, shortcodes/, assets/).',
            'FIXED:   Teacher login — DIRECT SQL password storage bypasses all cache layers.',
            'FIXED:   PHP native bcrypt (password_hash/password_verify) — no WP functions in auth path.',
            'ADDED:   Dedicated option (mlb_teacher_pw) decoupled from the settings array.',
            'ADDED:   Legacy PHPass fallback so v1–v4 installs work without resetting password.',
            'ADDED:   Password Tools: Set Password (with instant verify), Test, Reset to Default.',
        ]],
        ['4.2.1','🐛 Critical password hash mismatch fix','2025',[
            'FIXED:   sanitize_text_field() on save vs wp_unslash() on check — hash mismatch causing correct passwords to always be rejected.',
            'ADDED:   Diagnostic tools: Test Password, Reset to teacher123.',
            'ADDED:   Password status indicator in settings.',
        ]],
        ['4.2.0','🎨 WP native auth + colour customisation','2025',[
            'FIXED:   Teacher login rebuilt using wp_set_auth_cookie() — the same mechanism WP uses for its own login.',
            'ADDED:   Teacher user auto-blocked from /wp-admin/ — only sees the portal.',
            'ADDED:   Colour pickers in Settings → Appearance with live preview.',
            'CHANGED: Default warm coral/peach colour scheme for music studio aesthetic.',
        ]],
        ['4.1.0','🔍 Confirmation code + admin search','2025',[
            'ADDED:   Confirmation code column in All Bookings admin table (coloured badge).',
            'ADDED:   Search + filter bar in All Bookings — by name, email, phone, or code.',
            'ADDED:   Filter by instrument and date range.',
            'ADDED:   This Changelog page.',
        ]],
        ['4.0.0','🐛 Email layout + admin tabs fix','2025',[
            'FIXED:   Emails rendered "InstrumentPiano" — flexbox not supported in email clients; rebuilt with HTML table layout.',
            'FIXED:   Admin Settings tabs broken — PHP tag inside echo string caused silent JS syntax error.',
            'CHANGED: Custom fields builder moved into Form Fields tab.',
        ]],
        ['3.0.0','✏️ Custom fields + DB auto-upgrade','2025',[
            'FIXED:   Booking save failed on old DB tables missing student_age / experience_level columns.',
            'ADDED:   Auto-upgrade routine runs dbDelta on every version bump.',
            'ADDED:   Custom fields system — text, long text, dropdown, phone, email, number.',
            'ADDED:   Custom field data saved as JSON per booking; shown in portal and emails.',
        ]],
        ['2.0.0','🖥 Full UI redesign','2025',[
            'FIXED:   Settings nonce mismatch — "link expired" error on save.',
            'FIXED:   Currency hardcoded as "$" — now configurable (default R).',
            'ADDED:   Tabbed settings UI, stat cards, responsive layout.',
            'ADDED:   Blocked Dates admin page.',
            'ADDED:   Optional/required field toggles and custom labels.',
            'ADDED:   Admin notification email on new booking.',
            'ADDED:   Buffer time and max-advance-days settings.',
        ]],
        ['1.0.0','🚀 Initial release','2025',[
            'ADDED:   [music_lesson_booking] and [teacher_lesson_dashboard] shortcodes.',
            'ADDED:   Real-time time slot availability via AJAX.',
            'ADDED:   Overbooking prevention (existing bookings + buffer time).',
            'ADDED:   HTML confirmation email with unique confirmation code.',
            'ADDED:   Business hours configurable per day of week.',
        ]],
    ];
    foreach($versions as $v){
        echo '<div class="mlb-cl-ver"><h3>v'.esc_html($v[0]).' — '.esc_html($v[1]).' <span style="font-weight:400;color:#94a3b8;font-size:12px;">'.esc_html($v[2]).'</span></h3><ul>';
        foreach($v[3] as $note) echo '<li>'.esc_html($note).'</li>';
        echo '</ul></div>';
    }
    echo '</div>';
}
