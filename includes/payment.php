<?php
/**
 * Payment handling — Yoco (direct API, no WooCommerce) + EFT
 *
 * YOCO FLOW:  Book → Yoco checkout created → redirect to Yoco → pay →
 *             return to site → booking confirmed → emails sent
 *
 * EFT FLOW:   Book → slot reserved → EFT details emailed → teacher marks
 *             paid from portal → confirmation email sent to student
 */
if ( ! defined( 'ABSPATH' ) ) exit;

define( 'MLB_YOCO_API', 'https://payments.yoco.com/api/checkouts' );

// ── Status helpers ────────────────────────────────────────────────────────────
function mlb_pay_enabled(): bool { return mlb_yoco_enabled() || mlb_eft_enabled(); }
function mlb_yoco_enabled(): bool {
    return (bool) mlb_setting('pay_yoco', 0) && ! empty( trim( mlb_setting('pay_yoco_key','') ) );
}
function mlb_eft_enabled(): bool { return (bool) mlb_setting('pay_eft', 0); }

function mlb_payment_badge( string $method, string $status ): string {
    if ( $status === 'paid' ) {
        $icon = $method === 'yoco' ? '💳' : '🏦';
        return '<span style="background:#d1fae5;color:#065f46;padding:3px 9px;border-radius:20px;font-size:11px;font-weight:800;">' . $icon . ' Paid</span>';
    }
    if ( $status === 'pending_eft' )
        return '<span style="background:#fef3c7;color:#92400e;padding:3px 9px;border-radius:20px;font-size:11px;font-weight:800;">🏦 EFT Pending</span>';
    if ( $status === 'pending' )
        return '<span style="background:#dbeafe;color:#1e40af;padding:3px 9px;border-radius:20px;font-size:11px;font-weight:800;">💳 Awaiting Card</span>';
    if ( $status === 'failed' )
        return '<span style="background:#fee2e2;color:#991b1b;padding:3px 9px;border-radius:20px;font-size:11px;font-weight:800;">❌ Failed</span>';
    if ( $status === 'cancelled' )
        return '<span style="background:#f1f5f9;color:#64748b;padding:3px 9px;border-radius:20px;font-size:11px;font-weight:800;">↩ Cancelled</span>';
    return '<span style="background:#f1f5f9;color:#94a3b8;padding:3px 9px;border-radius:20px;font-size:11px;font-weight:800;">— N/A</span>';
}

// ── Create Yoco checkout ──────────────────────────────────────────────────────
function mlb_yoco_create_checkout( array $b, string $return_url ): array|false {
    $secret = trim( mlb_setting('pay_yoco_key','') );
    if ( empty($secret) ) return false;

    $amount = (int) round( (float) $b['price'] * 100 ); // rands → cents
    if ( $amount < 200 ) return false; // Yoco minimum = R2.00

    $code  = $b['confirmation_code'];
    $token = hash_hmac( 'sha256', $code, AUTH_KEY );

    $success = add_query_arg( ['mlb_pay'=>'ok',     'mlb_code'=>$code, 'mlb_tok'=>$token], $return_url );
    $cancel  = add_query_arg( ['mlb_pay'=>'cancel', 'mlb_code'=>$code],                    $return_url );
    $failure = add_query_arg( ['mlb_pay'=>'fail',   'mlb_code'=>$code],                    $return_url );

    $name  = explode( ' ', trim( $b['student_name'] ), 2 );
    $first = $name[0];
    $last  = $name[1] ?? '';
    $biz   = mlb_setting( 'business_name', get_bloginfo('name') );
    $df    = date( 'd M Y', strtotime( $b['lesson_date'] ) );

    $payload = [
        'amount'      => $amount,
        'currency'    => 'ZAR',
        'successUrl'  => $success,
        'cancelUrl'   => $cancel,
        'failureUrl'  => $failure,
        'externalId'  => $code,
        'productType' => 'music_lesson_booking',
        'metadata'    => [
            'billNote'             => $biz . ' — ' . $b['instrument'] . ' lesson | Ref: ' . $code,
            'customerFirstName'    => $first,
            'customerLastName'     => $last,
            'customerEmailAddress' => $b['student_email'],
        ],
        'lineItems' => [[
            'displayName' => $b['instrument'] . ' Music Lesson — ' . $df,
            'quantity'    => 1,
            'pricingDetails' => [
                'price'          => $amount,
                'taxAmount'      => 0,
                'discountAmount' => 0,
            ],
        ]],
    ];

    $resp = wp_remote_post( MLB_YOCO_API, [
        'timeout' => 15,
        'headers' => [
            'Authorization' => 'Bearer ' . $secret,
            'Content-Type'  => 'application/json',
        ],
        'body' => wp_json_encode( $payload ),
    ]);

    if ( is_wp_error($resp) ) return false;

    $http = (int) wp_remote_retrieve_response_code( $resp );
    $body = json_decode( wp_remote_retrieve_body( $resp ), true );

    if ( ! in_array( $http, [200,201,202], true ) || empty( $body['redirectUrl'] ) ) return false;

    return $body;
}

// ── Handle Yoco return to site ────────────────────────────────────────────────
add_action( 'template_redirect', 'mlb_handle_pay_return', 5 );
function mlb_handle_pay_return(): void {
    if ( ! isset( $_GET['mlb_pay'] ) ) return;

    $status = sanitize_text_field( $_GET['mlb_pay'] );
    $code   = sanitize_text_field( $_GET['mlb_code'] ?? '' );
    $token  = sanitize_text_field( $_GET['mlb_tok']  ?? '' );
    if ( empty($code) ) return;

    global $wpdb; $bt = $wpdb->prefix . 'mlb_bookings';
    $booking = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $bt WHERE confirmation_code=%s", $code ) );
    if ( ! $booking ) return;

    if ( $status === 'ok' ) {
        // Verify HMAC token so nobody fakes a success URL
        $expected = hash_hmac( 'sha256', $code, AUTH_KEY );
        if ( ! hash_equals( $expected, $token ) ) return;

        if ( $booking->payment_status !== 'paid' ) {
            $wpdb->update( $bt,
                ['payment_status'=>'paid','payment_method'=>'yoco'],
                ['confirmation_code'=>$code],
                ['%s','%s'], ['%s']
            );
            // Now send the confirmation email
            $cfs = ! empty($booking->custom_fields) ? (array) json_decode($booking->custom_fields, true) : [];
            mlb_send_email( $booking->student_email, $booking->student_name, $booking->instrument,
                $booking->lesson_date, $booking->lesson_time, (int)$booking->duration,
                (float)$booking->price, $code, $cfs, 'paid' );
            mlb_admin_email( $booking->student_name, $booking->student_email, $booking->instrument,
                $booking->lesson_date, $booking->lesson_time, $code );
        }

    } elseif ( in_array($status, ['cancel','fail'], true) ) {
        if ( $booking->payment_status === 'pending' ) {
            $wpdb->update( $bt,
                ['payment_status' => $status === 'cancel' ? 'cancelled' : 'failed'],
                ['confirmation_code'=>$code], ['%s'], ['%s']
            );
        }
    }
    // Page continues to render — the shortcode reads GET params and shows the right screen
}

// ── EFT details email ─────────────────────────────────────────────────────────
function mlb_send_eft_email( string $to, string $name, string $instr, string $date, string $time, float $price, string $code ): void {
    $sym  = mlb_sym();
    $biz  = mlb_setting('business_name', get_bloginfo('name'));
    $cp   = mlb_color('color_primary',   '#e36868');
    $cs   = mlb_color('color_secondary', '#c2492d');
    $df   = date('l, d F Y', strtotime($date));
    $tf   = date('g:i A',    strtotime($time));
    $pr   = $sym . number_format($price, 2);
    $ref  = str_replace('{code}', $code, mlb_setting('pay_eft_ref','MLB-{code}'));
    $inst = nl2br(esc_html(mlb_setting('pay_eft_instructions','')));

    $rows = '';
    foreach ([
        'Account Holder' => mlb_setting('pay_eft_holder',''),
        'Bank'           => mlb_setting('pay_eft_bank',''),
        'Account Number' => mlb_setting('pay_eft_account',''),
        'Branch Code'    => mlb_setting('pay_eft_branch',''),
        'Amount Due'     => $pr,
        'Reference'      => $ref,
    ] as $lbl => $val) {
        if (empty($val)) continue;
        $mono = in_array($lbl,['Reference','Account Number','Branch Code']) ? 'font-family:monospace;letter-spacing:1px;' : '';
        $rows .= '<tr>'
            . '<td style="padding:9px 0;border-bottom:1px solid #334155;font-size:13px;color:#94a3b8;font-weight:700;width:38%;">'.esc_html($lbl).'</td>'
            . '<td style="padding:9px 0;border-bottom:1px solid #334155;font-size:14px;color:#fff;font-weight:900;'.$mono.'">'.esc_html($val).'</td>'
            . '</tr>';
    }

    $body = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head>
<body style="margin:0;padding:28px;background:#fdf2e9;font-family:Arial,Helvetica,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;margin:0 auto;"><tr><td>
<table width="100%" cellpadding="0" cellspacing="0" style="background:linear-gradient(135deg,'.$cp.','.$cs.');border-radius:12px 12px 0 0;">
<tr><td style="padding:24px;text-align:center;">
  <div style="font-size:32px;margin-bottom:6px;">🏦</div>
  <div style="font-size:17px;font-weight:800;color:#fff;">Slot Reserved — Payment Pending</div>
  <div style="font-size:12px;color:#fff;opacity:.88;margin-top:3px;">'.esc_html($biz).'</div>
</td></tr></table>
<table width="100%" cellpadding="0" cellspacing="0" style="background:#fff;border-radius:0 0 12px 12px;"><tr><td style="padding:24px 26px;">
  <p style="margin:0 0 16px;font-size:15px;color:#475569;">Hi <strong>'.esc_html($name).'</strong>, your lesson slot is reserved! Complete your payment to confirm it.</p>
  <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:16px;">
    <tr><td style="padding:9px 0;border-bottom:1px solid #e2e8f0;font-size:14px;color:#64748b;font-weight:700;width:38%;">Instrument</td><td style="padding:9px 0;border-bottom:1px solid #e2e8f0;font-size:14px;color:#1e293b;font-weight:800;">'.esc_html($instr).'</td></tr>
    <tr><td style="padding:9px 0;border-bottom:1px solid #e2e8f0;font-size:14px;color:#64748b;font-weight:700;">Date</td><td style="padding:9px 0;border-bottom:1px solid #e2e8f0;font-size:14px;color:#1e293b;font-weight:800;">'.esc_html($df).'</td></tr>
    <tr><td style="padding:9px 0;font-size:14px;color:#64748b;font-weight:700;">Time</td><td style="padding:9px 0;font-size:14px;color:#1e293b;font-weight:800;">'.esc_html($tf).'</td></tr>
  </table>
  <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:16px;">
  <tr><td style="background:#1e293b;border-radius:10px;padding:16px 18px;">
    <div style="font-size:11px;color:#94a3b8;text-transform:uppercase;letter-spacing:2px;margin-bottom:10px;font-weight:800;">Bank Transfer Details</div>
    <table width="100%" cellpadding="0" cellspacing="0">'.$rows.'</table>
  </td></tr></table>
  '.($inst ? '<div style="background:#fef3c7;border-left:4px solid #f59e0b;border-radius:8px;padding:12px 14px;margin-bottom:14px;font-size:13px;color:#92400e;">'.$inst.'</div>' : '').'
  <div style="background:#d1fae5;border-left:4px solid #10b981;border-radius:8px;padding:12px 14px;font-size:13px;color:#065f46;font-weight:700;">
    ✅ Your slot is held. You will receive a booking confirmation email once payment is received.
  </div>
</td></tr>
<tr><td style="padding:0 26px 18px;text-align:center;"><p style="margin:0;font-size:11px;color:#94a3b8;">'.esc_html($biz).'</p>'.mlb_contact_footer().'</td></tr>
</table></td></tr></table></body></html>';

    wp_mail( $to, '🏦 Lesson Reserved — Pay by EFT | '.$biz, $body, ['Content-Type: text/html; charset=UTF-8'] );
}

// ── AJAX: Teacher confirms EFT received ──────────────────────────────────────
add_action( 'wp_ajax_mlb_t_confirm_eft', function() {
    check_ajax_referer('mlb_teacher_action');
    if ( ! mlb_teacher_ok() ) wp_send_json_error('Not authorised');
    global $wpdb; $bt = $wpdb->prefix.'mlb_bookings';
    $id = intval($_POST['id'] ?? 0);
    if ( !$id ) wp_send_json_error('Missing ID');
    $booking = $wpdb->get_row( $wpdb->prepare("SELECT * FROM $bt WHERE id=%d",$id) );
    if ( !$booking ) wp_send_json_error('Booking not found');

    $wpdb->update( $bt,
        ['payment_status'=>'paid','payment_method'=>'eft'],
        ['id'=>$id], ['%s','%s'], ['%d']
    );
    // Send the full confirmation email now that payment is confirmed
    $cfs = !empty($booking->custom_fields) ? (array)json_decode($booking->custom_fields, true) : [];
    mlb_send_email( $booking->student_email, $booking->student_name, $booking->instrument,
        $booking->lesson_date, $booking->lesson_time, (int)$booking->duration,
        (float)$booking->price, $booking->confirmation_code, $cfs, 'paid' );
    wp_send_json_success('✅ EFT confirmed — confirmation email sent to student');
});
