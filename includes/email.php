<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// ── Email helpers ─────────────────────────────────────────────────────────────
function mlb_payment_row( string $pay_status ): string {
    if ( $pay_status === 'not_required' || empty($pay_status) ) return '';
    $labels = [
        'paid'        => '✅ Received',
        'pending_eft' => '🏦 EFT — awaiting bank transfer',
        'pending'     => '💳 Card payment processing',
        'failed'      => '❌ Failed',
        'cancelled'   => '↩ Cancelled',
    ];
    $label = $labels[$pay_status] ?? $pay_status;
    return '<tr><td style="padding:10px 0;border-bottom:1px solid #e2e8f0;font-size:14px;color:#64748b;font-weight:700;width:42%;">Payment</td>'
         . '<td style="padding:10px 0;border-bottom:1px solid #e2e8f0;font-size:14px;color:#1e293b;font-weight:800;">'.esc_html($label).'</td></tr>';
}

function mlb_contact_footer(): string {
    $items = [];
    $email = mlb_setting('contact_email','');
    $phone = mlb_setting('contact_phone','');
    $url   = mlb_setting('contact_url','');
    if ($email) $items[] = '<a href="mailto:'.esc_attr($email).'" style="color:'.mlb_color('color_primary','#e36868').';text-decoration:none;">'.esc_html($email).'</a>';
    if ($phone) $items[] = '<a href="tel:'.esc_attr(preg_replace('/\s+/','',$phone)).'" style="color:'.mlb_color('color_primary','#e36868').';text-decoration:none;">'.esc_html($phone).'</a>';
    if ($url)   $items[] = '<a href="'.esc_url($url).'" target="_blank" style="color:'.mlb_color('color_primary','#e36868').';text-decoration:none;">Contact Us →</a>';
    if (empty($items)) return '';
    return '<p style="margin:8px 0 0;font-size:12px;color:#94a3b8;">Questions? '.implode(' &nbsp;·&nbsp; ', $items).'</p>';
}


function mlb_contact_footer_inline(): string {
    $items = [];
    $email = mlb_setting('contact_email','');
    $phone = mlb_setting('contact_phone','');
    $url   = mlb_setting('contact_url','');
    $cp    = mlb_color('color_primary','#e36868');
    if ($email) $items[] = '<a href="mailto:'.esc_attr($email).'" style="color:'.$cp.';text-decoration:none;font-weight:700;">'.esc_html($email).'</a>';
    if ($phone) $items[] = '<a href="tel:'.esc_attr(str_replace(' ','',$phone)).'" style="color:'.$cp.';text-decoration:none;font-weight:700;">'.esc_html($phone).'</a>';
    if ($url)   $items[] = '<a href="'.esc_url($url).'" target="_blank" style="color:'.$cp.';text-decoration:none;font-weight:700;">Contact Us →</a>';
    if (empty($items)) return '';
    return implode(' &nbsp;&middot;&nbsp; ', $items);
}

function mlb_send_email($to,$name,$instr,$date,$time,$dur,$price,$code,$extra=[],$pay_status='not_required'){
    $sym=mlb_sym(); $biz=mlb_setting('business_name',get_bloginfo('name'));
    $cp=mlb_color('color_primary','#e36868'); $cs=mlb_color('color_secondary','#c2492d');
    $df=date('l, d F Y',strtotime($date)); $tf=date('g:i A',strtotime($time)); $pr=$sym.number_format($price,2);
    $xrows='';
    foreach($extra as $lbl=>$val){if(!$val) continue;
        $xrows.='<tr><td style="padding:10px 0;border-bottom:1px solid #e2e8f0;font-size:14px;color:#64748b;font-weight:700;width:42%;">'.esc_html($lbl).'</td>'
              .'<td style="padding:10px 0;border-bottom:1px solid #e2e8f0;font-size:14px;color:#1e293b;font-weight:800;">'.esc_html($val).'</td></tr>';
    }
    $body = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head>
<body style="margin:0;padding:28px;background:#fdf2e9;font-family:Arial,Helvetica,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;margin:0 auto;"><tr><td>
<table width="100%" cellpadding="0" cellspacing="0" style="background:linear-gradient(135deg,'.$cp.','.$cs.');border-radius:12px 12px 0 0;">
<tr><td style="padding:26px;text-align:center;">
  <div style="font-size:34px;margin-bottom:7px;">🎵</div>
  <div style="font-size:19px;font-weight:800;color:#fff;text-shadow:0 1px 2px rgba(0,0,0,.15);">Lesson Booked!</div>
  <div style="font-size:12px;color:#fff;opacity:.92;margin-top:4px;">'.esc_html($biz).'</div>
</td></tr></table>
<table width="100%" cellpadding="0" cellspacing="0" style="background:#fff;border-radius:0 0 12px 12px;"><tr><td style="padding:24px 26px;">
  <p style="margin:0 0 16px;font-size:15px;color:#475569;">Hi <strong>'.esc_html($name).'</strong>, your lesson is confirmed!</p>
  <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:16px;">
    <tr><td style="padding:10px 0;border-bottom:1px solid #e2e8f0;font-size:14px;color:#64748b;font-weight:700;width:42%;">Instrument</td><td style="padding:10px 0;border-bottom:1px solid #e2e8f0;font-size:14px;color:#1e293b;font-weight:800;">'.esc_html($instr).'</td></tr>
    <tr><td style="padding:10px 0;border-bottom:1px solid #e2e8f0;font-size:14px;color:#64748b;font-weight:700;">Date</td><td style="padding:10px 0;border-bottom:1px solid #e2e8f0;font-size:14px;color:#1e293b;font-weight:800;">'.esc_html($df).'</td></tr>
    <tr><td style="padding:10px 0;border-bottom:1px solid #e2e8f0;font-size:14px;color:#64748b;font-weight:700;">Time</td><td style="padding:10px 0;border-bottom:1px solid #e2e8f0;font-size:14px;color:#1e293b;font-weight:800;">'.esc_html($tf).'</td></tr>
    <tr><td style="padding:10px 0;border-bottom:1px solid #e2e8f0;font-size:14px;color:#64748b;font-weight:700;">Duration</td><td style="padding:10px 0;border-bottom:1px solid #e2e8f0;font-size:14px;color:#1e293b;font-weight:800;">'.esc_html($dur).' minutes</td></tr>
    <tr><td style="padding:10px 0;border-bottom:1px solid #e2e8f0;font-size:14px;color:#64748b;font-weight:700;">Price</td><td style="padding:10px 0;border-bottom:1px solid #e2e8f0;font-size:14px;color:#1e293b;font-weight:800;">'.esc_html($pr).'</td></tr>
    '.$xrows.mlb_payment_row($pay_status).'
  </table>
  <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:16px;">
    <tr><td style="background:'.$cp.';border-radius:8px;padding:14px;text-align:center;">
      <div style="font-size:11px;color:rgba(255,255,255,.85);text-transform:uppercase;letter-spacing:2px;margin-bottom:5px;font-weight:700;">Confirmation Code</div>
      <div style="font-size:24px;font-weight:900;color:#fff;letter-spacing:5px;">'.esc_html($code).'</div>
    </td></tr>
  </table>
  <p style="margin:0;font-size:13px;color:#64748b;">Save this code. '.(mlb_setting('contact_email','')||mlb_setting('contact_phone','')||mlb_setting('contact_url','') ? 'Get in touch with us: '.mlb_contact_footer_inline() : 'Contact us if you need to cancel or reschedule.').'</p>
</td></tr>
<tr><td style="padding:0 26px 18px;text-align:center;"><p style="margin:0;font-size:11px;color:#94a3b8;">Automated message from '.esc_html($biz).'.</p>'.mlb_contact_footer().'</td></tr>
</table></td></tr></table></body></html>';
    wp_mail($to,'Lesson Confirmed — '.$code.' | '.$biz,$body,['Content-Type: text/html; charset=UTF-8']);
}

function mlb_admin_email($sn,$se,$instr,$date,$time,$code){
    $to=mlb_setting('teacher_email',get_option('admin_email'));
    $biz=mlb_setting('business_name',get_bloginfo('name'));
    wp_mail($to,"New Booking — $sn | $biz",
        "New booking!\n\nStudent: $sn\nEmail: $se\nInstrument: $instr\nDate: ".date('d F Y',strtotime($date))."\nTime: ".date('g:i A',strtotime($time))."\nCode: $code\n\nManage: ".admin_url('admin.php?page=mlb-book'));
}

// ── Cancellation email — sent to student when teacher cancels a lesson ────────
function mlb_send_cancellation_email( string $to, string $name, string $instr, string $date, string $time, string $code, string $pay_status = 'not_required' ): void {
    $sym = mlb_sym();
    $biz = mlb_setting( 'business_name', get_bloginfo('name') );
    $cp  = mlb_color( 'color_primary',   '#e36868' );
    $cs  = mlb_color( 'color_secondary', '#c2492d' );
    $df  = date( 'l, d F Y', strtotime( $date ) );
    $tf  = date( 'g:i A',    strtotime( $time ) );

    $subj = 'Lesson Cancelled — ' . $code . ' | ' . $biz;

    $body = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head>
<body style="margin:0;padding:28px;background:#fdf2e9;font-family:Arial,Helvetica,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;margin:0 auto;"><tr><td>
<!-- Header (grey-ish to visually distinguish from the green "confirmed" email) -->
<table width="100%" cellpadding="0" cellspacing="0" style="background:linear-gradient(135deg,#64748b,#475569);border-radius:12px 12px 0 0;">
<tr><td style="padding:26px;text-align:center;">
  <div style="font-size:34px;margin-bottom:7px;">📋</div>
  <div style="font-size:19px;font-weight:800;color:#fff;text-shadow:0 1px 2px rgba(0,0,0,.15);">Lesson Cancelled</div>
  <div style="font-size:12px;color:#fff;opacity:.88;margin-top:4px;">' . esc_html( $biz ) . '</div>
</td></tr></table>
<!-- Body -->
<table width="100%" cellpadding="0" cellspacing="0" style="background:#fff;border-radius:0 0 12px 12px;"><tr><td style="padding:24px 26px;">
  <p style="margin:0 0 16px;font-size:15px;color:#475569;">Hi <strong>' . esc_html( $name ) . '</strong>,</p>
  <p style="margin:0 0 18px;font-size:14px;color:#475569;">We\'re sorry to let you know that your upcoming lesson has been <strong>cancelled</strong>. Please see the details below, and contact us to reschedule at a time that suits you.</p>
  <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:18px;">
    <tr><td style="padding:10px 0;border-bottom:1px solid #e2e8f0;font-size:14px;color:#64748b;font-weight:700;width:42%;">Instrument</td><td style="padding:10px 0;border-bottom:1px solid #e2e8f0;font-size:14px;color:#1e293b;font-weight:800;">' . esc_html( $instr ) . '</td></tr>
    <tr><td style="padding:10px 0;border-bottom:1px solid #e2e8f0;font-size:14px;color:#64748b;font-weight:700;">Date</td><td style="padding:10px 0;border-bottom:1px solid #e2e8f0;font-size:14px;color:#1e293b;font-weight:800;">' . esc_html( $df ) . '</td></tr>
    <tr><td style="padding:10px 0;border-bottom:1px solid #e2e8f0;font-size:14px;color:#64748b;font-weight:700;">Time</td><td style="padding:10px 0;border-bottom:1px solid #e2e8f0;font-size:14px;color:#1e293b;font-weight:800;">' . esc_html( $tf ) . '</td></tr>
    <tr><td style="padding:10px 0;font-size:14px;color:#64748b;font-weight:700;">Reference Code</td><td style="padding:10px 0;font-size:14px;color:#1e293b;font-weight:800;font-family:monospace;letter-spacing:2px;">' . esc_html( $code ) . '</td></tr>
  </table>
  <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:18px;">
    <tr><td style="background:#fef3c7;border-left:4px solid #f59e0b;border-radius:8px;padding:14px 16px;">
      <div style="font-size:13px;color:#92400e;font-weight:700;">📞 To reschedule your lesson, please contact us directly and quote your reference code above.</div>
    </td></tr>
  '.mlb_payment_row($pay_status).'
  </table>
  <p style="margin:0;font-size:13px;color:#64748b;">We apologise for any inconvenience caused and look forward to seeing you soon!</p>
</td></tr>
<tr><td style="padding:0 26px 18px;text-align:center;">
  <p style="margin:0;font-size:11px;color:#94a3b8;">Automated message from ' . esc_html( $biz ) . ' — please do not reply.</p>' . mlb_contact_footer() . '
</td></tr></table>
</td></tr></table>
</body></html>';

    wp_mail( $to, $subj, $body, ['Content-Type: text/html; charset=UTF-8'] );
}

// ── Booking updated email (sent when teacher edits and checks "notify student") ─
function mlb_send_update_email( string $to, string $name, string $instr, string $date,
                                 string $time, int $dur, float $price, string $code,
                                 string $new_status, string $pay_status = 'not_required' ): void {
    $sym  = mlb_sym();
    $biz  = mlb_setting('business_name', get_bloginfo('name'));
    $cp   = mlb_color('color_primary',   '#e36868');
    $cs   = mlb_color('color_secondary', '#c2492d');
    $df   = date('l, d F Y', strtotime($date));
    $tf   = date('g:i A',    strtotime($time));
    $pr   = $sym . number_format($price, 2);

    // Header varies by new status
    if ($new_status === 'cancelled') {
        $icon   = '📋';
        $hdr    = 'Lesson Cancelled';
        $hdr_bg = 'linear-gradient(135deg,#64748b,#475569)';
        $intro  = 'We\'re sorry to let you know that your lesson has been <strong>cancelled</strong>.';
        $note   = 'Please contact us to reschedule at a time that suits you.';
        $subj   = 'Lesson Cancelled — ' . $code . ' | ' . $biz;
    } elseif ($new_status === 'confirmed') {
        $icon   = '🔄';
        $hdr    = 'Lesson Updated';
        $hdr_bg = 'linear-gradient(135deg,' . $cp . ',' . $cs . ')';
        $intro  = 'Your lesson booking has been <strong>updated</strong>. Here are your new details:';
        $note   = 'Contact us if you have any questions or need to make further changes.';
        $subj   = 'Lesson Updated — ' . $code . ' | ' . $biz;
    } else {
        $icon   = '📋';
        $hdr    = 'Booking Update';
        $hdr_bg = 'linear-gradient(135deg,' . $cp . ',' . $cs . ')';
        $intro  = 'There has been an update to your lesson booking.';
        $note   = 'Contact us if you have any questions.';
        $subj   = 'Booking Update — ' . $code . ' | ' . $biz;
    }

    $body = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head>
<body style="margin:0;padding:28px;background:#fdf2e9;font-family:Arial,Helvetica,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;margin:0 auto;"><tr><td>
<table width="100%" cellpadding="0" cellspacing="0" style="background:'.$hdr_bg.';border-radius:12px 12px 0 0;">
<tr><td style="padding:26px;text-align:center;">
  <div style="font-size:34px;margin-bottom:7px;">'.$icon.'</div>
  <div style="font-size:19px;font-weight:800;color:#fff;text-shadow:0 1px 2px rgba(0,0,0,.15);">'.esc_html($hdr).'</div>
  <div style="font-size:12px;color:#fff;opacity:.92;margin-top:4px;">'.esc_html($biz).'</div>
</td></tr></table>
<table width="100%" cellpadding="0" cellspacing="0" style="background:#fff;border-radius:0 0 12px 12px;"><tr><td style="padding:24px 26px;">
  <p style="margin:0 0 16px;font-size:15px;color:#475569;">Hi <strong>'.esc_html($name).'</strong>, '.$intro.'</p>
  <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:16px;">
    <tr><td style="padding:10px 0;border-bottom:1px solid #e2e8f0;font-size:14px;color:#64748b;font-weight:700;width:42%;">Instrument</td><td style="padding:10px 0;border-bottom:1px solid #e2e8f0;font-size:14px;color:#1e293b;font-weight:800;">'.esc_html($instr).'</td></tr>
    <tr><td style="padding:10px 0;border-bottom:1px solid #e2e8f0;font-size:14px;color:#64748b;font-weight:700;">Date</td><td style="padding:10px 0;border-bottom:1px solid #e2e8f0;font-size:14px;color:#1e293b;font-weight:800;">'.esc_html($df).'</td></tr>
    <tr><td style="padding:10px 0;border-bottom:1px solid #e2e8f0;font-size:14px;color:#64748b;font-weight:700;">Time</td><td style="padding:10px 0;border-bottom:1px solid #e2e8f0;font-size:14px;color:#1e293b;font-weight:800;">'.esc_html($tf).'</td></tr>
    <tr><td style="padding:10px 0;border-bottom:1px solid #e2e8f0;font-size:14px;color:#64748b;font-weight:700;">Duration</td><td style="padding:10px 0;border-bottom:1px solid #e2e8f0;font-size:14px;color:#1e293b;font-weight:800;">'.esc_html($dur).' minutes</td></tr>
    <tr><td style="padding:10px 0;border-bottom:1px solid #e2e8f0;font-size:14px;color:#64748b;font-weight:700;">Reference Code</td><td style="padding:10px 0;border-bottom:1px solid #e2e8f0;font-size:14px;font-family:monospace;font-weight:900;letter-spacing:2px;color:#1e293b;">'.esc_html($code).'</td></tr>
    '.mlb_payment_row($pay_status).'
  </table>
  <div style="background:#fef3c7;border-left:4px solid #f59e0b;border-radius:8px;padding:12px 14px;font-size:13px;color:#92400e;">
    💬 '.esc_html($note).'
  </div>
</td></tr>
<tr><td style="padding:0 26px 18px;text-align:center;"><p style="margin:0;font-size:11px;color:#94a3b8;">Automated message from '.esc_html($biz).'.</p>'.mlb_contact_footer().'</td></tr>
</table></td></tr></table></body></html>';

    wp_mail($to, $subj, $body, ['Content-Type: text/html; charset=UTF-8']);
}
