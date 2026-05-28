<?php
if(!defined('ABSPATH')) exit;

function mlb_pg_settings(){
    if(isset($_POST['mlb_save'])&&wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_mlb_s']??'')),'mlb_save_settings')){
        $s=get_option('mlb_settings',[]);
        $s['business_name']=sanitize_text_field($_POST['business_name']??'');
        $s['teacher_email']=sanitize_email($_POST['teacher_email']??'');
        $s['currency_symbol']=sanitize_text_field($_POST['currency_symbol']??'R');
        $s['lesson_duration']=max(15,intval($_POST['lesson_duration']??60));
        $s['lesson_price']=max(0,floatval($_POST['lesson_price']??0));
        $s['buffer_minutes']=max(0,intval($_POST['buffer_minutes']??30));
        $s['max_advance']=max(1,intval($_POST['max_advance']??90));
        $s['instruments']=sanitize_text_field($_POST['instruments']??'');
        $s['experience_options']=sanitize_text_field($_POST['experience_options']??'');
        $s['booking_intro']=sanitize_textarea_field($_POST['booking_intro']??'');
        $s['confirm_message']=sanitize_textarea_field($_POST['confirm_message']??'');
        $s['notify_admin']=isset($_POST['notify_admin'])?1:0;
        $s['contact_email'] = sanitize_email($_POST['contact_email']   ?? '');
        $s['contact_phone'] = sanitize_text_field($_POST['contact_phone'] ?? '');
        $s['contact_url']   = esc_url_raw($_POST['contact_url']        ?? '');
        for($d=0;$d<7;$d++) $s['business_hours'][$d]=[sanitize_text_field($_POST["bh_s$d"]??'off'),sanitize_text_field($_POST["bh_e$d"]??'off')];
        foreach(['phone','age','level','notes'] as $f){
            $s["field_{$f}"]=isset($_POST["field_{$f}"])?1:0;
            $s["field_{$f}_req"]=isset($_POST["field_{$f}_req"])?1:0;
            $s["field_{$f}_label"]=sanitize_text_field($_POST["field_{$f}_label"]??'');
        }
        foreach(['name','email','instr','date'] as $f) $s["field_{$f}_label"]=sanitize_text_field($_POST["field_{$f}_label"]??'');
        foreach(['color_primary'=>'#e36868','color_secondary'=>'#c2492d','color_accent'=>'#f1a830'] as $k=>$fb){
            $v=sanitize_text_field($_POST[$k]??$fb);
            $s[$k]=preg_match('/^#[a-fA-F0-9]{6}$/',$v)?$v:$fb;
        }
        $cfl=($_POST['cf_label']??[]); $cft=($_POST['cf_type']??[]); $cfo=($_POST['cf_options']??[]);
        $cfs=[];
        foreach($cfl as $i=>$lbl){
            $lbl=sanitize_text_field($lbl); if(!$lbl) continue;
            $cfs[]=['id'=>'cf_'.sanitize_key($lbl).'_'.$i,'label'=>$lbl,'type'=>sanitize_text_field($cft[$i]??'text'),'options'=>sanitize_text_field($cfo[$i]??''),'required'=>isset($_POST['cf_req_'.$i])?1:0];
        }
        $s['custom_fields']=$cfs;
        // ── Payment settings ─────────────────────────────────────────
        $s['pay_yoco']             = isset($_POST['pay_yoco']) ? 1 : 0;
        $s['pay_yoco_key']         = sanitize_text_field(wp_unslash($_POST['pay_yoco_key']     ?? ''));
        $s['pay_yoco_pub_key']     = sanitize_text_field(wp_unslash($_POST['pay_yoco_pub_key'] ?? ''));
        $s['pay_eft']              = isset($_POST['pay_eft'])  ? 1 : 0;
        $s['pay_eft_holder']       = sanitize_text_field($_POST['pay_eft_holder']       ?? '');
        $s['pay_eft_bank']         = sanitize_text_field($_POST['pay_eft_bank']         ?? '');
        $s['pay_eft_account']      = sanitize_text_field($_POST['pay_eft_account']      ?? '');
        $s['pay_eft_branch']       = sanitize_text_field($_POST['pay_eft_branch']       ?? '');
        $s['pay_eft_ref']          = sanitize_text_field($_POST['pay_eft_ref']          ?? 'MLB-{code}');
        $s['pay_eft_instructions'] = sanitize_textarea_field($_POST['pay_eft_instructions'] ?? '');
        update_option('mlb_settings',$s);
        echo '<div class="mlb-ok">✅ Settings saved successfully!</div>';
    }
    $s=get_option('mlb_settings',[]);
    $bh=$s['business_hours']??[];
    $days=['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
    $times=[];
    for($h=0;$h<24;$h++) for($m=0;$m<60;$m+=30) $times[]=str_pad($h,2,'0',STR_PAD_LEFT).':'.str_pad($m,2,'0',STR_PAD_LEFT);
    $cfs=$s['custom_fields']??[];
    $cp=mlb_color('color_primary','#e36868'); $cs=mlb_color('color_secondary','#c2492d'); $ca=mlb_color('color_accent','#f1a830');
    ?>
    <div class="wrap">
    <div class="mlb-hd"><div class="mlb-hd-i">⚙️</div><div><h1>Settings</h1><p>Pricing, hours, form fields, colours, and teacher access</p></div></div>
    <div class="mlb-tabs">
        <?php foreach(['gen'=>'🏢 General','hrs'=>'🕐 Hours','fld'=>'📝 Form Fields','apr'=>'🎨 Appearance','tch'=>'🔐 Teacher Login','pay'=>'💳 Payments','lic'=>'⭐ License'] as $id=>$lbl): ?>
        <button type="button" class="mlb-tab <?php echo $id==='gen'?'act':''; ?>" data-g="s" data-tab="<?php echo $id; ?>" onclick="mlbTab('s','<?php echo $id; ?>',this)"><?php echo $lbl; ?></button>
        <?php endforeach; ?>
    </div>
    <form method="post" action="">
        <?php wp_nonce_field('mlb_save_settings','_mlb_s'); ?>
        <!-- GENERAL -->
        <div id="mlb-p-gen" class="mlb-panel act" data-g="s">
            <div class="mlb-card"><div class="mlb-ct">🏢 Business</div>
                <?php mlbf('Business / Studio Name','business_name','text',$s['business_name']??get_bloginfo('name'),'Used in confirmation emails'); ?>
                <?php mlbf('Notification Email','teacher_email','email',$s['teacher_email']??get_option('admin_email'),'Receives new booking alerts'); ?>
                <div class="mlb-fr"><label></label><label style="display:flex;align-items:center;gap:8px;padding-top:0;"><input type="checkbox" name="notify_admin" value="1" <?php checked(1,$s['notify_admin']??1); ?>><span style="font-size:13px;">Email me when a new booking is made</span></label></div>
            </div>
            <div class="mlb-card"><div class="mlb-ct">📞 Contact Info for Emails</div>
                <p style="color:#64748b;font-size:13px;margin-bottom:14px;">These appear at the bottom of every email so students know how to reach you. Leave blank to hide.</p>
                <?php mlbf('Contact Email','contact_email','email',$s['contact_email']??'','Shown as a mailto: link in emails'); ?>
                <?php mlbf('Contact Phone','contact_phone','text',$s['contact_phone']??'','Shown as a tel: link — e.g. +27 82 123 4567'); ?>
                <?php mlbf('Contact URL','contact_url','url',$s['contact_url']??'','Link to your contact page or WhatsApp chat'); ?>
            </div>
            <div class="mlb-card"><div class="mlb-ct">💰 Pricing &amp; Lessons</div>
                <?php mlbf('Currency Symbol','currency_symbol','text',$s['currency_symbol']??'R','e.g. R, $, €, £','width:68px'); ?>
                <?php mlbf('Price Per Lesson','lesson_price','number',$s['lesson_price']??350); ?>
                <?php mlbf('Lesson Duration (minutes)','lesson_duration','number',$s['lesson_duration']??60); ?>
                <?php mlbf('Buffer Between Lessons (minutes)','buffer_minutes','number',$s['buffer_minutes']??30,'Prevents overbooking'); ?>
                <?php mlbf('Max Days in Advance to Book','max_advance','number',$s['max_advance']??90); ?>
            </div>
            <div class="mlb-card"><div class="mlb-ct">🎸 Instruments &amp; Levels</div>
                <?php mlbf('Available Instruments','instruments','text',$s['instruments']??'Piano, Guitar, Violin, Voice','Comma-separated'); ?>
                <?php mlbf('Experience Level Options','experience_options','text',$s['experience_options']??'Complete Beginner, Some Experience, Intermediate, Advanced','Comma-separated'); ?>
            </div>
            <div class="mlb-card"><div class="mlb-ct">📝 Booking Page Text</div>
                <?php mlbf('Booking Form Intro','booking_intro','textarea',$s['booking_intro']??'Select a date, instrument, and time to book your lesson.'); ?>
                <?php mlbf('Success Message','confirm_message','textarea',$s['confirm_message']??'Your lesson is confirmed! Please check your email for details.'); ?>
            </div>
        </div>
        <!-- HOURS -->
        <div id="mlb-p-hrs" class="mlb-panel" data-g="s">
            <div class="mlb-card"><div class="mlb-ct">🕐 Business Hours</div>
                <p style="color:#64748b;font-size:13px;margin-bottom:14px;">Set <strong>Closed</strong> for days you don't teach.</p>
                <?php foreach($days as $i=>$day): $st=$bh[$i][0]??'off'; $en=$bh[$i][1]??'off'; ?>
                <div class="mlb-hg">
                    <label><?php echo $day; ?></label>
                    <select name="bh_s<?php echo $i; ?>">
                        <option value="off" <?php selected($st,'off'); ?>>Closed</option>
                        <?php foreach($times as $t): ?><option value="<?php echo $t; ?>" <?php selected($st,$t); ?>><?php echo $t; ?></option><?php endforeach; ?>
                    </select>
                    <span>to</span>
                    <select name="bh_e<?php echo $i; ?>">
                        <option value="off">Closed</option>
                        <?php foreach($times as $t): ?><option value="<?php echo $t; ?>" <?php selected($en,$t); ?>><?php echo $t; ?></option><?php endforeach; ?>
                    </select>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <!-- FORM FIELDS -->
        <div id="mlb-p-fld" class="mlb-panel" data-g="s">
            <div class="mlb-card"><div class="mlb-ct">📝 Core Field Labels</div>
                <?php foreach([['name','Full Name','field_name_label'],['email','Email Address','field_email_label'],['instr','Instrument','field_instr_label'],['date','Date Picker','field_date_label']] as $f): ?>
                <div style="display:grid;grid-template-columns:160px 1fr;gap:9px;align-items:center;padding:7px 11px;background:#f8fafc;border-radius:8px;margin-bottom:5px;">
                    <span style="font-weight:700;font-size:13px;">✅ <?php echo $f[1]; ?></span>
                    <input type="text" name="<?php echo $f[2]; ?>" value="<?php echo esc_attr($s[$f[2]]??$f[1]); ?>" style="padding:5px 8px;border:2px solid #e2e8f0;border-radius:7px;font-size:12px;max-width:270px;">
                </div>
                <?php endforeach; ?>
            </div>
            <div class="mlb-card"><div class="mlb-ct">🔄 Optional Built-in Fields</div>
                <div class="mlb-fl-hdr"><span>Field</span><span>Show</span><span>Required</span><span>Label</span></div>
                <?php foreach([['phone','📞 Phone','field_phone','field_phone_req','field_phone_label','Phone Number'],['age','🎂 Age','field_age','field_age_req','field_age_label','Age'],['level','🎓 Level','field_level','field_level_req','field_level_label','Experience Level'],['notes','📋 Notes','field_notes','field_notes_req','field_notes_label','Notes / Goals']] as $f): ?>
                <div class="mlb-fl-row">
                    <span class="fn"><?php echo $f[1]; ?></span>
                    <label class="mlb-tog"><input type="checkbox" name="<?php echo $f[2]; ?>" value="1" <?php checked(1,$s[$f[2]]??1); ?>><span class="mlb-tsl"></span></label>
                    <label class="mlb-tog"><input type="checkbox" name="<?php echo $f[3]; ?>" value="1" <?php checked(1,$s[$f[3]]??0); ?>><span class="mlb-tsl"></span></label>
                    <input type="text" name="<?php echo $f[4]; ?>" value="<?php echo esc_attr($s[$f[4]]??$f[5]); ?>">
                </div>
                <?php endforeach; ?>
            </div>
            <div class="mlb-card"><div class="mlb-ct">✏️ Custom Fields</div>
                <p style="color:#64748b;font-size:13px;margin-bottom:10px;">Extra fields appended to the booking form.</p>
                <div class="mlb-cf-hdr"><span>Label</span><span>Type</span><span>Required</span><span>Options (Dropdown)</span><span></span></div>
                <div id="mlb-cf-rows">
                    <?php foreach($cfs as $i=>$cf): ?>
                    <div class="mlb-cf-row" id="cf-row-<?php echo $i; ?>">
                        <input type="text" name="cf_label[]" value="<?php echo esc_attr($cf['label']); ?>" required>
                        <select name="cf_type[]"><?php foreach(['text'=>'Text','textarea'=>'Long Text','select'=>'Dropdown','tel'=>'Phone','email'=>'Email','number'=>'Number'] as $k=>$v): ?><option value="<?php echo $k; ?>" <?php selected($cf['type']??'text',$k); ?>><?php echo $v; ?></option><?php endforeach; ?></select>
                        <label style="display:flex;align-items:center;gap:5px;font-size:13px;white-space:nowrap;"><input type="checkbox" name="cf_req_<?php echo $i; ?>" <?php checked(1,$cf['required']??0); ?>> Req</label>
                        <input type="text" name="cf_options[]" value="<?php echo esc_attr($cf['options']??''); ?>" placeholder="Option A, Option B">
                        <button type="button" class="mlb-del" onclick="document.getElementById('cf-row-<?php echo $i; ?>').remove()">✕</button>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div style="margin-top:11px;"><button type="button" onclick="mlbAddCF()" class="mlb-btn mlb-bg">➕ Add Field</button></div>
            </div>
        </div>
        <!-- APPEARANCE -->
        <div id="mlb-p-apr" class="mlb-panel" data-g="s">
            <div class="mlb-card"><div class="mlb-ct">🎨 Colours</div>
                <div class="mlb-fr"><label>Primary Colour</label><div><input type="color" id="mlb-cp1" name="color_primary" value="<?php echo esc_attr($cp); ?>"><p class="d">Buttons, gradient start, accents</p></div></div>
                <div class="mlb-fr"><label>Secondary Colour</label><div><input type="color" id="mlb-cp2" name="color_secondary" value="<?php echo esc_attr($cs); ?>"><p class="d">Gradient end</p></div></div>
                <div class="mlb-fr"><label>Accent Colour</label><div><input type="color" name="color_accent" value="<?php echo esc_attr($ca); ?>"><p class="d">Highlights and badges</p></div></div>
                <div id="mlb-prev" class="mlb-preview" style="background:linear-gradient(135deg,<?php echo esc_attr($cp); ?>,<?php echo esc_attr($cs); ?>);">
                    <h4>🎵 Live Preview</h4><p>How your booking form header will look</p>
                </div>
            </div>
        </div>
        <!-- TEACHER LOGIN -->
        <div id="mlb-p-tch" class="mlb-panel" data-g="s">
            <div class="mlb-card"><div class="mlb-ct">🔐 Teacher Portal Password</div>
                <p style="color:#64748b;font-size:13px;margin-bottom:14px;">The teacher uses this to log into the portal — no WordPress account needed.</p>
                <?php
                global $wpdb;
                $saved_hash=$wpdb->get_var($wpdb->prepare("SELECT option_value FROM {$wpdb->options} WHERE option_name=%s",MLB_PW_OPT));
                $is_default=!empty($saved_hash)&&password_verify('teacher123',$saved_hash);
                $has_legacy=empty($saved_hash);
                ?>
                <div class="mlb-fr"><label>Current Status</label><div>
                <?php if($has_legacy): ?>
                    <span style="background:#fee2e2;color:#991b1b;padding:6px 12px;border-radius:7px;font-size:13px;font-weight:700;">⚠️ No v5 password set — use "Set Password" tool below</span>
                <?php elseif($is_default): ?>
                    <span style="background:#fef3c7;color:#92400e;padding:6px 12px;border-radius:7px;font-size:13px;font-weight:700;">⚠️ Using DEFAULT: <code>teacher123</code></span>
                <?php else: ?>
                    <span style="background:#d1fae5;color:#065f46;padding:6px 12px;border-radius:7px;font-size:13px;font-weight:700;">✅ Custom password saved</span>
                <?php endif; ?>
                </div></div>
            </div>
            <div class="mlb-card" style="border-left:4px solid var(--cg);">
                <div class="mlb-ct">🛠 Password Tools — Use These, Not the Settings Form</div>
                <p style="color:#64748b;font-size:13px;margin-bottom:14px;">These tools write and verify the password directly in the database, bypassing all caches.</p>

                <div style="background:#f8fafc;border-radius:9px;padding:13px 15px;margin-bottom:12px;">
                    <strong style="font-size:13px;">1. Set your new password</strong>
                    <p style="font-size:12px;color:#64748b;margin:4px 0 9px;">Type a new password and click Set. It verifies immediately after saving.</p>
                    <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
                        <input type="password" id="mlb-setpw-input" placeholder="Type your new password…" style="padding:7px 11px;border:2px solid #e2e8f0;border-radius:8px;font-size:13px;min-width:220px;">
                        <button type="button" id="mlb-setpw-btn" class="mlb-btn mlb-bp">✅ Set Password</button>
                        <span id="mlb-setpw-result" style="font-size:13px;font-weight:700;"></span>
                    </div>
                </div>

                <div style="background:#f8fafc;border-radius:9px;padding:13px 15px;margin-bottom:12px;">
                    <strong style="font-size:13px;">2. Test a password against what is saved</strong>
                    <p style="font-size:12px;color:#64748b;margin:4px 0 9px;">Type any password to instantly see if it matches.</p>
                    <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
                        <input type="password" id="mlb-test-pw" placeholder="Type password to test…" style="padding:7px 11px;border:2px solid #e2e8f0;border-radius:8px;font-size:13px;min-width:220px;">
                        <button type="button" id="mlb-test-btn" class="mlb-btn mlb-bp">Test</button>
                        <span id="mlb-test-result" style="font-size:13px;font-weight:700;"></span>
                    </div>
                </div>

                <div style="background:#f8fafc;border-radius:9px;padding:13px 15px;">
                    <strong style="font-size:13px;">3. Locked out? Reset to default</strong>
                    <p style="font-size:12px;color:#64748b;margin:4px 0 9px;">Sets password to <code>teacher123</code> and verifies it works in one click.</p>
                    <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
                        <button type="button" id="mlb-reset-btn" class="mlb-btn mlb-bd">🔄 Reset to teacher123</button>
                        <span id="mlb-reset-result" style="font-size:13px;font-weight:700;"></span>
                    </div>
                </div>
            </div>
        </div>


        <!-- PAYMENTS -->
        <div id="mlb-p-pay" class="mlb-panel" data-g="s">
            <!-- Yoco -->
            <div class="mlb-card" style="border-left:4px solid #1e40af;">
                <div class="mlb-ct">💳 Yoco — Card Payments (no WooCommerce required)</div>

                <div style="background:#eff6ff;border:2px solid #bfdbfe;border-radius:10px;padding:14px 16px;margin-bottom:18px;font-size:13px;color:#1e40af;line-height:1.7;">
                    <strong>🔑 Where to find your keys:</strong><br>
                    Log in to <a href="https://portal.yoco.com" target="_blank" style="color:#1d4ed8;font-weight:700;">portal.yoco.com</a>
                    → <strong>Settings</strong> → <strong>Developers</strong> → <strong>API Keys</strong>.<br><br>
                    These are the <strong>exact same keys</strong> you use in your WooCommerce Yoco plugin —
                    copy them directly from <strong>WooCommerce → Payments → Yoco</strong> if you already have it set up there.<br><br>
                    Each key comes in two versions — use <strong>test keys</strong> while setting up and testing,
                    switch to <strong>live keys</strong> when you're ready to take real payments:
                    <ul style="margin:8px 0 0;padding-left:20px;">
                        <li><code style="background:#dbeafe;padding:1px 5px;border-radius:4px;">pk_test_...</code> / <code style="background:#dbeafe;padding:1px 5px;border-radius:4px;">pk_live_...</code> — Public Key</li>
                        <li><code style="background:#dbeafe;padding:1px 5px;border-radius:4px;">sk_test_...</code> / <code style="background:#dbeafe;padding:1px 5px;border-radius:4px;">sk_live_...</code> — Secret Key (keep this private!)</li>
                    </ul>
                </div>

                <div class="mlb-fr"><label>Enable Yoco</label>
                    <label class="mlb-tog"><input type="checkbox" name="pay_yoco" value="1" <?php checked(1,$s['pay_yoco']??0); ?>><span class="mlb-tsl"></span></label>
                </div>

                <div class="mlb-fr"><label>Public Key<br><small style="font-weight:400;color:#94a3b8;">pk_live_ or pk_test_</small></label>
                    <div>
                        <div style="display:flex;gap:8px;align-items:center;">
                            <input type="text" name="pay_yoco_pub_key"
                                value="<?php echo esc_attr($s['pay_yoco_pub_key']??''); ?>"
                                placeholder="pk_live_... or pk_test_..."
                                style="min-width:320px;font-family:monospace;letter-spacing:1px;">
                        </div>
                        <p class="d">Your public-facing Yoco key — starts with <code>pk_</code>. Safe to store, used to identify your account.</p>
                    </div>
                </div>

                <div class="mlb-fr"><label>Secret Key<br><small style="font-weight:400;color:#94a3b8;">sk_live_ or sk_test_</small></label>
                    <div>
                        <div style="display:flex;gap:8px;align-items:center;">
                            <input type="password" id="yoco-key-inp" name="pay_yoco_key"
                                value="<?php echo esc_attr($s['pay_yoco_key']??''); ?>"
                                placeholder="sk_live_... or sk_test_..."
                                style="min-width:320px;font-family:monospace;letter-spacing:1px;">
                            <button type="button" onclick="var i=document.getElementById('yoco-key-inp');i.type=i.type==='password'?'text':'password';" class="mlb-btn mlb-bg" style="padding:5px 10px;font-size:12px;">👁 Show</button>
                        </div>
                        <p class="d">Your private Yoco key — starts with <code>sk_</code>. <strong>Never share this.</strong> This is the key used to process payments.</p>
                    </div>
                </div>

                <div style="background:#dbeafe;border-left:4px solid #1e40af;border-radius:8px;padding:11px 14px;font-size:13px;color:#1e40af;max-width:580px;">
                    <strong>🧪 Test card:</strong> 4000 0000 0000 0002 · Any future expiry · Any CVC &nbsp;|&nbsp;
                    <strong>💡 Tip:</strong> Test with <code>sk_test_</code> first, then swap to <code>sk_live_</code> for real payments.
                </div>
            </div>
            <!-- EFT -->
            <div class="mlb-card" style="border-left:4px solid #10b981;">
                <div class="mlb-ct">🏦 EFT / Bank Transfer</div>
                <p style="color:#64748b;font-size:13px;margin-bottom:14px;">
                    Students pay by bank transfer. The slot is reserved immediately and you confirm payment from the teacher portal.
                </p>
                <div class="mlb-fr"><label>Enable EFT</label>
                    <label class="mlb-tog"><input type="checkbox" name="pay_eft" value="1" <?php checked(1,$s['pay_eft']??0); ?>><span class="mlb-tsl"></span></label>
                </div>
                <?php
                $eft_fields = [
                    ['Account Holder','pay_eft_holder','text','Your name or studio name',''],
                    ['Bank Name','pay_eft_bank','text','e.g. FNB, Capitec, Absa',''],
                    ['Account Number','pay_eft_account','text','','font-family:monospace;letter-spacing:1px;'],
                    ['Branch Code','pay_eft_branch','text','e.g. 250655','font-family:monospace;letter-spacing:1px;'],
                    ['Payment Reference Format','pay_eft_ref','text','Use {code} for the booking code — e.g. MLB-{code}',''],
                ];
                foreach($eft_fields as [$lbl,$key,$type,$desc,$xs]):
                ?>
                <div class="mlb-fr"><label><?php echo $lbl;?></label>
                    <div><input type="<?php echo $type;?>" name="<?php echo $key;?>"
                        value="<?php echo esc_attr($s[$key]??'');?>" style="<?php echo $xs;?>">
                        <?php if($desc): ?><p class="d"><?php echo $desc;?></p><?php endif;?>
                    </div>
                </div>
                <?php endforeach;?>
                <div class="mlb-fr"><label>Additional Instructions</label>
                    <div><textarea name="pay_eft_instructions" rows="3" style="max-width:440px;width:100%;padding:7px 10px;border:2px solid #e2e8f0;border-radius:8px;font-size:13px;"><?php echo esc_textarea($s['pay_eft_instructions']??'');?></textarea>
                    <p class="d">Shown to student in email and on screen — e.g. "Email proof of payment to..."</p></div>
                </div>
            </div>
            <!-- Preview -->
            <div class="mlb-card" style="background:#f8fafc;border:2px dashed #e2e8f0;">
                <div class="mlb-ct">ℹ️ How it works</div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;font-size:13px;">
                    <div style="padding:12px 14px;background:#fff;border-radius:9px;border-left:4px solid #1e40af;">
                        <strong style="color:#1e40af;">💳 Yoco flow</strong>
                        <ol style="margin:8px 0 0;padding-left:16px;color:#475569;line-height:2;">
                            <li>Student fills form, selects Card</li>
                            <li>Redirected to Yoco checkout page</li>
                            <li>Pays securely on Yoco</li>
                            <li>Returns to site — booking confirmed</li>
                            <li>Confirmation email sent automatically</li>
                        </ol>
                    </div>
                    <div style="padding:12px 14px;background:#fff;border-radius:9px;border-left:4px solid #10b981;">
                        <strong style="color:#10b981;">🏦 EFT flow</strong>
                        <ol style="margin:8px 0 0;padding-left:16px;color:#475569;line-height:2;">
                            <li>Student fills form, selects EFT</li>
                            <li>Slot reserved immediately</li>
                            <li>Bank details emailed to student</li>
                            <li>Teacher marks as paid in portal</li>
                            <li>Confirmation email sent to student</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>
        <!-- LICENSE -->
        <div id="mlb-p-lic" class="mlb-panel" data-g="s">
            <?php
            $lic = dfmlb_get_license();
            $is_active     = $lic['status'] === 'active';
            $is_invalid    = $lic['status'] === 'invalid';
            $is_unlicensed = $lic['status'] === 'unlicensed';
            $status_badge  = match($lic['status']) {
                'active'      => '<span style="background:#d1fae5;color:#065f46;padding:6px 14px;border-radius:7px;font-size:13px;font-weight:700;">✅ Active & Verified</span>',
                'invalid'     => '<span style="background:#fee2e2;color:#991b1b;padding:6px 14px;border-radius:7px;font-size:13px;font-weight:700;">❌ Invalid / Suspended</span>',
                'check_failed'=> '<span style="background:#fef3c7;color:#92400e;padding:6px 14px;border-radius:7px;font-size:13px;font-weight:700;">⚠️ Verification Failed</span>',
                default       => '<span style="background:#f1f5f9;color:#64748b;padding:6px 14px;border-radius:7px;font-size:13px;font-weight:700;">🔓 Not Licensed</span>',
            };
            ?>
            <!-- Status card -->
            <div class="mlb-card" style="border-left:4px solid <?php echo $is_active?'#10b981':($is_invalid?'#ef4444':'#f59e0b');?>">
                <div class="mlb-ct">⭐ DadsFam License Status</div>
                <div class="mlb-fr"><label>Current Status</label><div><?php echo $status_badge; ?></div></div>
                <?php if($is_active): ?>
                <div class="mlb-fr"><label>Licensed To</label><div><span style="font-size:13px;color:#1e293b;font-weight:700;"><?php echo esc_html($lic['product'] ?: 'DadsFam Music Lessons Booking'); ?></span></div></div>
                <div class="mlb-fr"><label>Expires</label><div><span style="font-size:13px;color:#1e293b;"><?php echo $lic['expires']==='never'?'Never (Lifetime)':esc_html($lic['expires']); ?></span></div></div>
                <div class="mlb-fr"><label>Last Verified</label><div><span style="font-size:13px;color:#64748b;"><?php echo $lic['verified_at']?date('d M Y H:i',intval($lic['verified_at'])):'Never'; ?></span></div></div>
                <?php elseif($is_invalid && !empty($lic['message'])): ?>
                <div class="mlb-fr"><label>Reason</label><div><span style="font-size:13px;color:#991b1b;"><?php echo esc_html($lic['message']); ?></span></div></div>
                <?php endif; ?>
            </div>

            <!-- Activate / manage -->
            <div class="mlb-card">
                <div class="mlb-ct"><?php echo $is_active ? '🔑 Manage License' : '🔑 Activate Your License'; ?></div>
                <?php if(!$is_active): ?>
                <p style="color:#64748b;font-size:13px;margin-bottom:16px;">
                    Enter your license key below to activate. Don't have one yet?
                    <a href="https://www.dadsfam.co.za" target="_blank" style="color:<?php echo esc_attr(mlb_color('color_primary','#e36868')); ?>;font-weight:700;">Purchase at dadsfam.co.za →</a>
                </p>
                <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-bottom:14px;">
                    <input type="password" id="dfmlb-key-input" placeholder="DFMLB-XXXX-XXXX-XXXX-XXXX"
                        value="<?php echo esc_attr($lic['key']); ?>"
                        style="flex:1;min-width:280px;padding:10px 13px;border:2px solid #e2e8f0;border-radius:9px;font-size:14px;font-family:monospace;letter-spacing:1px;">
                    <button type="button" id="dfmlb-show-key" class="mlb-btn mlb-bg" style="white-space:nowrap;">👁 Show</button>
                    <button type="button" id="dfmlb-activate-btn" class="mlb-btn mlb-bp" style="white-space:nowrap;">Activate License</button>
                </div>
                <?php else: ?>
                <p style="color:#64748b;font-size:13px;margin-bottom:16px;">Your license is active. Deactivating frees up one activation slot so you can move to a different site.</p>
                <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-bottom:14px;">
                    <code style="background:#f8fafc;padding:9px 13px;border-radius:8px;font-size:13px;border:2px solid #e2e8f0;letter-spacing:1px;"><?php echo esc_html($lic['key']); ?></code>
                    <button type="button" id="dfmlb-reverify-btn" class="mlb-btn mlb-bg">🔄 Re-check Now</button>
                    <button type="button" id="dfmlb-deactivate-btn" class="mlb-btn mlb-bd">Deactivate</button>
                </div>
                <?php endif; ?>
                <div id="dfmlb-lic-msg" style="display:none;padding:11px 14px;border-radius:9px;font-weight:700;font-size:13px;margin-top:8px;"></div>
            </div>

            <!-- What the license covers -->
            <div class="mlb-card" style="border-left:4px solid <?php echo esc_attr(mlb_color('color_accent','#f1a830')); ?>;">
                <div class="mlb-ct">💡 About Your License</div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
                    <div style="padding:13px 15px;background:#f8fafc;border-radius:9px;">
                        <strong style="font-size:13px;">🟢 Free Tier (current)</strong>
                        <ul style="font-size:12px;color:#475569;margin:8px 0 0;padding-left:16px;line-height:2;">
                            <li>Unlimited bookings</li>
                            <li>Full teacher portal</li>
                            <li>Email confirmations</li>
                            <li>Custom form fields</li>
                            <li>Overbooking prevention</li>
                            <li>All core features</li>
                        </ul>
                    </div>
                    <div style="padding:13px 15px;background:#fffbeb;border-radius:9px;border:1px dashed #f59e0b;">
                        <strong style="font-size:13px;">⭐ Pro Tier (coming soon)</strong>
                        <ul style="font-size:12px;color:#475569;margin:8px 0 0;padding-left:16px;line-height:2;">
                            <li>Online payments (Stripe/PayFast)</li>
                            <li>SMS notifications</li>
                            <li>Student accounts & history</li>
                            <li>Recurring lesson schedules</li>
                            <li>Multi-teacher support</li>
                            <li>Priority support</li>
                        </ul>
                    </div>
                </div>
                <p style="font-size:12px;color:#94a3b8;margin:12px 0 0;">
                    A license key links your site to your DadsFam account. It does not restrict any current features.
                    When Pro features launch, your active license will unlock them automatically.
                    <a href="https://www.dadsfam.co.za" target="_blank" style="color:<?php echo esc_attr(mlb_color('color_primary','#e36868')); ?>;">Learn more →</a>
                </p>
            </div>
        </div>

        <div style="padding-bottom:26px;"><button type="submit" name="mlb_save" value="1" class="mlb-btn mlb-bp" style="font-size:14px;padding:10px 24px;">💾 Save All Settings</button></div>
    </form></div>
    <?php
}
function mlbf($label,$name,$type,$value,$desc='',$xs=''){
    echo '<div class="mlb-fr"><label for="'.$name.'">'.$label.'</label><div>';
    if($type==='textarea') echo '<textarea id="'.$name.'" name="'.$name.'" rows="3" style="max-width:400px;width:100%;padding:7px 10px;border:2px solid #e2e8f0;border-radius:8px;font-size:13px;box-sizing:border-box;">'.esc_textarea($value).'</textarea>';
    else echo '<input type="'.$type.'" id="'.$name.'" name="'.$name.'" value="'.esc_attr($value).'" style="'.$xs.'">';
    if($desc) echo '<p class="d">'.$desc.'</p>';
    echo '</div></div>';
}
