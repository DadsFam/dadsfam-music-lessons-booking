<?php
if(!defined('ABSPATH')) exit;

add_shortcode('music_lesson_booking','mlb_sc_booking');
function mlb_sc_booking(){
    $s   = get_option('mlb_settings',[]);
    $cp  = mlb_color('color_primary',  '#e36868');
    $cs  = mlb_color('color_secondary','#c2492d');
    $sym = mlb_sym();

    /* ── Yoco return handler ─────────────────────────────────────────────── */
    $pay_status = sanitize_text_field($_GET['mlb_pay']  ?? '');
    $pay_code   = sanitize_text_field($_GET['mlb_code'] ?? '');
    if($pay_status && $pay_code){
        global $wpdb;
        $booking=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}mlb_bookings WHERE confirmation_code=%s",$pay_code));
        ob_start();
        echo '<div style="max-width:560px;margin:28px auto;font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',sans-serif;">';
        if($pay_status==='ok'&&$booking){
            echo '<div style="background:#fff;border-radius:14px;padding:34px;box-shadow:0 10px 36px rgba(0,0,0,.09);text-align:center;">';
            echo '<div style="font-size:52px;margin-bottom:12px;">✅</div>';
            echo '<h2 style="margin:0 0 8px;font-size:20px;color:#065f46;font-weight:900;">Payment Received!</h2>';
            echo '<p style="color:#475569;font-size:14px;margin:0 0 18px;">Your lesson is confirmed. A confirmation email has been sent.</p>';
            echo '<div style="background:#f0fdf4;border-radius:9px;padding:14px;margin-bottom:14px;font-size:13px;color:#1e293b;"><strong>'.esc_html($booking->instrument).'</strong> · '.date('l, d M Y',strtotime($booking->lesson_date)).' · '.date('g:i A',strtotime($booking->lesson_time)).'</div>';
            echo '<code style="background:#1e293b;color:#fbbf24;padding:8px 16px;border-radius:8px;font-size:18px;font-weight:900;letter-spacing:4px;">'.esc_html($pay_code).'</code>';
            echo '</div>';
        } elseif($pay_status==='cancel'){
            echo '<div style="background:#fff;border-radius:14px;padding:34px;box-shadow:0 10px 36px rgba(0,0,0,.09);text-align:center;">';
            echo '<div style="font-size:48px;margin-bottom:12px;">↩️</div>';
            echo '<h2 style="margin:0 0 8px;font-size:18px;color:#92400e;font-weight:900;">Payment Cancelled</h2>';
            echo '<a href="'.esc_url(remove_query_arg(['mlb_pay','mlb_code','mlb_tok'])).'" style="display:inline-block;padding:11px 22px;background:linear-gradient(135deg,'.$cp.','.$cs.');color:#fff;border-radius:9px;text-decoration:none;font-weight:800;font-size:14px;margin-top:12px;">Try Again →</a>';
            echo '</div>';
        } else {
            echo '<div style="background:#fff;border-radius:14px;padding:34px;box-shadow:0 10px 36px rgba(0,0,0,.09);text-align:center;">';
            echo '<div style="font-size:48px;margin-bottom:12px;">❌</div>';
            echo '<h2 style="margin:0 0 8px;font-size:18px;color:#991b1b;font-weight:900;">Payment Failed</h2>';
            echo '<a href="'.esc_url(remove_query_arg(['mlb_pay','mlb_code','mlb_tok'])).'" style="display:inline-block;padding:11px 22px;background:linear-gradient(135deg,'.$cp.','.$cs.');color:#fff;border-radius:9px;text-decoration:none;font-weight:800;font-size:14px;margin-top:12px;">Try Again →</a>';
            echo '</div>';
        }
        echo '</div>';
        return ob_get_clean();
    }

    /* ── Settings ────────────────────────────────────────────────────────── */
    $instruments= array_map('trim',explode(',',$s['instruments']??'Piano, Guitar, Violin, Voice'));
    $lvls       = array_map('trim',explode(',',$s['experience_options']??'Complete Beginner, Some Experience, Intermediate, Advanced'));
    $price      = $s['lesson_price']   ?? 350;
    $dur        = $s['lesson_duration'] ?? 60;
    $intro      = $s['booking_intro']  ?? 'Select a date, instrument, and time to book your lesson.';
    $maxd       = (int)($s['max_advance'] ?? 90);
    $cfs        = $s['custom_fields']  ?? [];
    $bh         = $s['business_hours'] ?? [];
    $pay_active = mlb_pay_enabled();
    $yoco_on    = mlb_yoco_enabled();
    $eft_on     = mlb_eft_enabled();
    $default_pm = $yoco_on ? 'yoco' : ($eft_on ? 'eft' : 'none');

    // Compute global open/close range for calendar row headers
    $all_opens=[]; $all_closes=[];
    foreach($bh as $dh){ if(($dh[0]??'off')!=='off'){ $all_opens[]=mlb_t2m($dh[0]); $all_closes[]=mlb_t2m($dh[1]); } }
    $cal_open  = $all_opens  ? min($all_opens)  : 8*60;
    $cal_close = $all_closes ? max($all_closes) : 18*60;

    wp_enqueue_style ('mlb-form', MLB_URL.'assets/form.css', [], MLB_VERSION);
    wp_enqueue_script('mlb-form', MLB_URL.'assets/form.js',  [], MLB_VERSION, true);
    wp_localize_script('mlb-form','mlbForm',[
        'ajaxurl'        => admin_url('admin-ajax.php'),
        'nonce'          => wp_create_nonce('mlb_front'),
        'colorPrimary'   => $cp,
        'colorSecondary' => $cs,
        'payEnabled'     => $pay_active ? 1 : 0,
        'defaultPM'      => $default_pm,
        'price'          => $price,
        'sym'            => $sym,
        'duration'       => $dur,
        'calOpen'        => $cal_open,
        'calClose'       => $cal_close,
        'maxAdvance'     => $maxd,
        'businessHours'  => $bh,
    ]);

    ob_start(); ?>
<div class="mlb-wrap">
    <div class="mlb-form-head" style="background:linear-gradient(135deg,<?php echo esc_attr($cp);?>,<?php echo esc_attr($cs);?>);">
        <div class="icon">🎵</div>
        <h2>Book a Music Lesson</h2>
        <p><?php echo esc_html($intro); ?></p>
    </div>
    <div class="mlb-form-body" id="mlb-form-body">
    <form id="mlb-booking-form">
        <?php wp_nonce_field('mlb_front','_mlbn'); ?>

        <!-- 1 — Student Info -->
        <div class="ms"><h3 class="mh">1 — Your Information</h3>
            <div class="m2">
                <div class="mf"><label><?php echo esc_html($s['field_name_label']??'Full Name'); ?> <span class="mr">*</span></label><input type="text" name="student_name" required></div>
                <div class="mf"><label><?php echo esc_html($s['field_email_label']??'Email Address'); ?> <span class="mr">*</span></label><input type="email" name="student_email" required></div>
            </div>
            <?php if($s['field_phone']??1): ?><div class="mf"><label><?php echo esc_html($s['field_phone_label']??'Phone'); ?><?php if($s['field_phone_req']??0) echo ' <span class="mr">*</span>'; ?></label><input type="tel" name="student_phone" <?php if($s['field_phone_req']??0) echo 'required'; ?>></div><?php endif; ?>
            <div class="m2">
                <?php if($s['field_age']??1): ?><div class="mf"><label><?php echo esc_html($s['field_age_label']??'Age'); ?><?php if($s['field_age_req']??0) echo ' <span class="mr">*</span>'; ?></label><input type="text" name="student_age" <?php if($s['field_age_req']??0) echo 'required'; ?>></div><?php endif; ?>
                <?php if($s['field_level']??1): ?><div class="mf"><label><?php echo esc_html($s['field_level_label']??'Experience Level'); ?><?php if($s['field_level_req']??0) echo ' <span class="mr">*</span>'; ?></label>
                    <select name="experience_level" <?php if($s['field_level_req']??0) echo 'required'; ?>><option value="">Select…</option>
                        <?php foreach($lvls as $l): ?><option value="<?php echo esc_attr($l);?>"><?php echo esc_html($l);?></option><?php endforeach; ?>
                    </select></div><?php endif; ?>
            </div>
        </div>

        <!-- 2 — Instrument -->
        <div class="ms"><h3 class="mh">2 — Instrument</h3>
            <div class="mf" style="max-width:300px;"><label><?php echo esc_html($s['field_instr_label']??'Instrument'); ?> <span class="mr">*</span></label>
                <select name="instrument" id="mlb-instrument" required onchange="calRefresh()">
                    <option value="">Select instrument…</option>
                    <?php foreach($instruments as $i): ?><option value="<?php echo esc_attr($i);?>"><?php echo esc_html($i);?></option><?php endforeach; ?>
                </select>
            </div>
        </div>

        <!-- 3 — Calendar: pick date & time -->
        <div class="ms"><h3 class="mh">3 — Choose a Date &amp; Time</h3>

            <!-- Week navigation -->
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;gap:8px;flex-wrap:wrap;">
                <div style="display:flex;gap:6px;">
                    <button type="button" id="cal-prev" onclick="calPrev()"
                        style="padding:7px 14px;border:2px solid #e2e8f0;border-radius:8px;background:#fff;cursor:pointer;font-size:13px;font-weight:700;color:#475569;transition:.15s;"
                        onmouseover="this.style.borderColor='<?php echo esc_attr($cp);?>'" onmouseout="this.style.borderColor='#e2e8f0'">← Prev</button>
                    <button type="button" onclick="calToday()"
                        style="padding:7px 14px;border:2px solid #e2e8f0;border-radius:8px;background:#fff;cursor:pointer;font-size:13px;font-weight:700;color:#475569;"
                        onmouseover="this.style.borderColor='<?php echo esc_attr($cp);?>'" onmouseout="this.style.borderColor='#e2e8f0'">Today</button>
                    <button type="button" id="cal-next" onclick="calNext()"
                        style="padding:7px 14px;border:2px solid #e2e8f0;border-radius:8px;background:#fff;cursor:pointer;font-size:13px;font-weight:700;color:#475569;"
                        onmouseover="this.style.borderColor='<?php echo esc_attr($cp);?>'" onmouseout="this.style.borderColor='#e2e8f0'">Next →</button>
                </div>
                <div id="cal-week-label" style="font-size:14px;font-weight:800;color:#1e293b;text-align:center;flex:1;min-width:160px;"></div>
                <!-- Legend -->
                <div style="display:flex;gap:10px;font-size:11px;flex-wrap:wrap;">
                    <span style="display:flex;align-items:center;gap:4px;"><span style="width:12px;height:12px;background:#d1fae5;border-radius:3px;display:inline-block;border:1px solid #6ee7b7;"></span>Available</span>
                    <span style="display:flex;align-items:center;gap:4px;"><span style="width:12px;height:12px;background:#fee2e2;border-radius:3px;display:inline-block;border:1px solid #fca5a5;"></span>Booked</span>
                    <span style="display:flex;align-items:center;gap:4px;"><span style="width:12px;height:12px;background:<?php echo esc_attr($cp);?>;border-radius:3px;display:inline-block;"></span>Selected</span>
                </div>
            </div>

            <!-- Calendar grid -->
            <div id="mlb-cal-wrap" style="overflow-x:auto;">
                <div style="padding:30px;text-align:center;color:#94a3b8;">⏳ Loading availability…</div>
            </div>

            <!-- Selected slot indicator -->
            <div id="mlb-cal-sel-bar" style="display:none;margin-top:10px;background:<?php echo esc_attr($cp);?>1a;border-left:4px solid <?php echo esc_attr($cp);?>;border-radius:8px;padding:10px 14px;">
                <span style="font-size:13px;color:#1e293b;font-weight:700;">✅ Selected: </span>
                <span id="mlb-cal-sel-text" style="font-size:13px;font-weight:800;color:<?php echo esc_attr($cp);?>;"></span>
            </div>

            <!-- Hidden fields for form submission -->
            <input type="hidden" name="lesson_date" id="mlb-date">
            <input type="hidden" name="lesson_time" id="mlb-tv">
        </div>

        <!-- 4 — Notes -->
        <?php if($s['field_notes']??1): ?>
        <div class="ms"><h3 class="mh">4 — Additional Information</h3>
            <div class="mf"><label><?php echo esc_html($s['field_notes_label']??'Notes / Goals'); ?><?php if($s['field_notes_req']??0) echo ' <span class="mr">*</span>'; ?></label>
                <textarea name="notes" rows="3" <?php if($s['field_notes_req']??0) echo 'required'; ?> placeholder="Goals, songs, questions…"></textarea></div>
        </div>
        <?php endif; ?>

        <!-- 5 — Custom Fields -->
        <?php if(!empty($cfs)): $csn=($s['field_notes']??1)?5:4; ?>
        <div class="ms"><h3 class="mh"><?php echo $csn;?> — More Details</h3>
            <?php foreach($cfs as $cf):
                $fk='cf_'.sanitize_key($cf['id']); $rq=!empty($cf['required']);
                echo '<div class="mf"><label>'.esc_html($cf['label']).($rq?' <span class="mr">*</span>':'').'</label>';
                if($cf['type']==='textarea') echo '<textarea name="'.$fk.'" '.($rq?'required':'').' rows="2"></textarea>';
                elseif($cf['type']==='select'){
                    $opts=array_map('trim',explode(',',$cf['options']??''));
                    echo '<select name="'.$fk.'" '.($rq?'required':'').'><option value="">Select…</option>';
                    foreach($opts as $o) echo '<option value="'.esc_attr($o).'">'.esc_html($o).'</option>';
                    echo '</select>';
                } else echo '<input type="'.esc_attr($cf['type']).'" name="'.$fk.'" '.($rq?'required':'').'>';
                echo '</div>';
            endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Payment Method -->
        <?php if($pay_active):
            $pstep = ($s['field_notes']??1)?5:4;
            $pstep += !empty($cfs)?1:0;
            $pstep += 1;
        ?>
        <div class="ms">
            <h3 class="mh"><?php echo $pstep;?> — Payment Method</h3>
            <div style="display:flex;flex-direction:column;gap:10px;">
                <?php if($yoco_on): ?>
                <label class="mlb-pay-opt" style="display:flex;align-items:center;gap:14px;padding:14px 16px;border:2px solid <?php echo $default_pm==='yoco'?esc_attr($cp):'#e2e8f0';?>;border-radius:11px;cursor:pointer;background:<?php echo $default_pm==='yoco'?esc_attr($cp).'11':'#fff';?>;transition:.15s;">
                    <input type="radio" name="payment_method" value="yoco" <?php echo $default_pm==='yoco'?'checked':''; ?> onchange="mlbUpdatePM(this)" style="width:18px;height:18px;accent-color:<?php echo esc_attr($cp);?>;flex-shrink:0;cursor:pointer;">
                    <div style="flex:1;"><div style="font-weight:800;font-size:14px;color:#1e293b;">💳 Pay by Card</div><div style="font-size:12px;color:#64748b;margin-top:2px;">Visa &amp; Mastercard — secure payment via Yoco</div></div>
                    <div style="font-size:20px;">🔒</div>
                </label>
                <?php endif; ?>
                <?php if($eft_on): ?>
                <label class="mlb-pay-opt" style="display:flex;align-items:center;gap:14px;padding:14px 16px;border:2px solid <?php echo $default_pm==='eft'?esc_attr($cp):'#e2e8f0';?>;border-radius:11px;cursor:pointer;background:<?php echo $default_pm==='eft'?esc_attr($cp).'11':'#fff';?>;transition:.15s;">
                    <input type="radio" name="payment_method" value="eft" <?php echo $default_pm==='eft'?'checked':''; ?> onchange="mlbUpdatePM(this)" style="width:18px;height:18px;accent-color:<?php echo esc_attr($cp);?>;flex-shrink:0;cursor:pointer;">
                    <div style="flex:1;"><div style="font-weight:800;font-size:14px;color:#1e293b;">🏦 Pay by EFT / Bank Transfer</div><div style="font-size:12px;color:#64748b;margin-top:2px;">Slot reserved — bank details emailed to you immediately</div></div>
                </label>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Summary + Submit -->
        <div class="mlb-summary" style="background:#fdf2e9;border-left:4px solid <?php echo esc_attr($cp);?>;">
            <div class="mlb-summary-item"><div class="label">Price</div><div class="value" style="color:<?php echo esc_attr($cp);?>;"><?php echo $sym.number_format((float)$price,2);?></div></div>
            <div class="mlb-summary-item"><div class="label">Duration</div><div class="value" style="color:<?php echo esc_attr($cs);?>;"><?php echo $dur;?> min</div></div>
            <div class="mlb-summary-item" id="mlb-st" style="display:none;"><div class="label">Selected</div><div class="value" id="mlb-stv" style="color:#10b981;font-size:13px;"></div></div>
        </div>

        <button type="submit" id="mlb-submit" class="mlb-submit" disabled
            style="background:linear-gradient(135deg,<?php echo esc_attr($cp);?>,<?php echo esc_attr($cs);?>);">
            <?php
            if($yoco_on&&$default_pm==='yoco') echo '💳 Pay '.esc_html($sym.number_format((float)$price,2)).' by Card →';
            elseif($eft_on&&$default_pm==='eft') echo '🏦 Book &amp; Pay '.esc_html($sym.number_format((float)$price,2)).' via EFT →';
            else echo 'Complete Booking →';
            ?>
        </button>
        <div id="mlb-msg" class="mlb-msg"></div>
    </form>
    </div>
</div>
    <?php
    return ob_get_clean();
}
