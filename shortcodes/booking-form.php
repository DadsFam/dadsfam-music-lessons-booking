<?php
if(!defined('ABSPATH')) exit;

add_shortcode('music_lesson_booking','mlb_sc_booking');
function mlb_sc_booking(){
    $s=get_option('mlb_settings',[]);
    $cp=mlb_color('color_primary','#e36868');
    $cs=mlb_color('color_secondary','#c2492d');

    wp_enqueue_style('mlb-form', MLB_URL.'assets/form.css', [], MLB_VERSION);
    wp_enqueue_script('mlb-form', MLB_URL.'assets/form.js', [], MLB_VERSION, true);
    wp_localize_script('mlb-form','mlbForm',[
        'ajaxurl'      => admin_url('admin-ajax.php'),
        'nonce'        => wp_create_nonce('mlb_front'),
        'colorPrimary' => $cp,
        'colorSecondary'=> $cs,
    ]);

    $sym =mlb_sym();
    $ins =array_map('trim',explode(',',$s['instruments']??'Piano, Guitar, Violin, Voice'));
    $lvls=array_map('trim',explode(',',$s['experience_options']??'Complete Beginner, Some Experience, Intermediate, Advanced'));
    $price=$s['lesson_price']??350;
    $dur  =$s['lesson_duration']??60;
    $intro=$s['booking_intro']??'Select a date, instrument, and time to book your lesson.';
    $maxd =(int)($s['max_advance']??90);
    $cfs  =$s['custom_fields']??[];
    $today=date('Y-m-d');
    $maxdt=date('Y-m-d',strtotime("+$maxd days"));

    ob_start(); ?>
<div class="mlb-wrap">
    <div class="mlb-form-head" style="background:linear-gradient(135deg,<?php echo esc_attr($cp); ?>,<?php echo esc_attr($cs); ?>);">
        <div class="icon">🎵</div>
        <h2>Book a Music Lesson</h2>
        <p><?php echo esc_html($intro); ?></p>
    </div>
    <div class="mlb-form-body" id="mlb-form-body">
    <form id="mlb-booking-form">
        <?php wp_nonce_field('mlb_front','_mlbn'); ?>

        <div class="ms"><h3 class="mh">1 — Your Information</h3>
            <div class="m2">
                <div class="mf"><label><?php echo esc_html($s['field_name_label']??'Full Name'); ?> <span class="mr">*</span></label><input type="text" name="student_name" required></div>
                <div class="mf"><label><?php echo esc_html($s['field_email_label']??'Email Address'); ?> <span class="mr">*</span></label><input type="email" name="student_email" required></div>
            </div>
            <?php if($s['field_phone']??1): ?><div class="mf"><label><?php echo esc_html($s['field_phone_label']??'Phone'); ?><?php if($s['field_phone_req']??0) echo ' <span class="mr">*</span>'; ?></label><input type="tel" name="student_phone" <?php if($s['field_phone_req']??0) echo 'required'; ?>></div><?php endif; ?>
            <div class="m2">
            <?php if($s['field_age']??1): ?><div class="mf"><label><?php echo esc_html($s['field_age_label']??'Age'); ?><?php if($s['field_age_req']??0) echo ' <span class="mr">*</span>'; ?></label><input type="text" name="student_age" <?php if($s['field_age_req']??0) echo 'required'; ?>></div><?php endif; ?>
            <?php if($s['field_level']??1): ?><div class="mf"><label><?php echo esc_html($s['field_level_label']??'Experience Level'); ?><?php if($s['field_level_req']??0) echo ' <span class="mr">*</span>'; ?></label>
                <select name="experience_level" <?php if($s['field_level_req']??0) echo 'required'; ?> style="border-color:<?php echo esc_attr($cp); ?>;"><option value="">Select…</option>
                    <?php foreach($lvls as $l): ?><option value="<?php echo esc_attr($l); ?>"><?php echo esc_html($l); ?></option><?php endforeach; ?>
                </select></div><?php endif; ?>
            </div>
        </div>

        <div class="ms"><h3 class="mh">2 — Lesson Details</h3>
            <div class="m2">
                <div class="mf"><label><?php echo esc_html($s['field_instr_label']??'Instrument'); ?> <span class="mr">*</span></label>
                    <select name="instrument" required style="border-color:<?php echo esc_attr($cp); ?>;"><option value="">Select instrument…</option>
                        <?php foreach($ins as $i): ?><option value="<?php echo esc_attr($i); ?>"><?php echo esc_html($i); ?></option><?php endforeach; ?>
                    </select></div>
                <div class="mf"><label><?php echo esc_html($s['field_date_label']??'Preferred Date'); ?> <span class="mr">*</span></label>
                    <input type="date" name="lesson_date" id="mlb-date" required min="<?php echo $today; ?>" max="<?php echo $maxdt; ?>" style="border-color:<?php echo esc_attr($cp); ?>;"></div>
            </div>
        </div>

        <div class="ms"><h3 class="mh">3 — Choose a Time</h3>
            <div id="mlb-slots-wrap"><p style="color:#94a3b8;text-align:center;padding:14px 0;">← Select a date above to see available times</p></div>
            <input type="hidden" name="lesson_time" id="mlb-tv">
        </div>

        <?php if($s['field_notes']??1): ?>
        <div class="ms"><h3 class="mh">4 — Additional Information</h3>
            <div class="mf"><label><?php echo esc_html($s['field_notes_label']??'Notes / Goals'); ?><?php if($s['field_notes_req']??0) echo ' <span class="mr">*</span>'; ?></label>
                <textarea name="notes" rows="3" <?php if($s['field_notes_req']??0) echo 'required'; ?> placeholder="Goals, songs, questions…" style="border-color:#e2e8f0;"></textarea></div>
        </div>
        <?php endif; ?>

        <?php if(!empty($cfs)): $sn=($s['field_notes']??1)?5:4; ?>
        <div class="ms"><h3 class="mh"><?php echo $sn; ?> — More Details</h3>
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

        <div class="mlb-summary" style="background:#fdf2e9;border-left:4px solid <?php echo esc_attr($cp); ?>;">
            <div class="mlb-summary-item"><div class="label">Price</div><div class="value" style="color:<?php echo esc_attr($cp); ?>;"><?php echo $sym.number_format((float)$price,2); ?></div></div>
            <div class="mlb-summary-item"><div class="label">Duration</div><div class="value" style="color:<?php echo esc_attr($cs); ?>;"><?php echo $dur; ?> min</div></div>
            <div class="mlb-summary-item" id="mlb-st" style="display:none;"><div class="label">Selected Time</div><div class="value" id="mlb-stv" style="color:#10b981;"></div></div>
        </div>

        <button type="submit" id="mlb-submit" class="mlb-submit" disabled
            style="background:linear-gradient(135deg,<?php echo esc_attr($cp); ?>,<?php echo esc_attr($cs); ?>);">
            Complete Booking →
        </button>
        <div id="mlb-msg" class="mlb-msg"></div>
    </form>
    </div>
</div>
    <?php
    return ob_get_clean();
}
