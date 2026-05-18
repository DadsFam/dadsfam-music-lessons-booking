<?php
if(!defined('ABSPATH')) exit;

add_shortcode('teacher_lesson_dashboard','mlb_sc_teacher');
function mlb_sc_teacher(){
    if(!headers_sent()){ @header('Cache-Control: no-store, no-cache, must-revalidate'); nocache_headers(); }

    $cp  = mlb_color('color_primary',  '#e36868');
    $cs  = mlb_color('color_secondary','#c2492d');
    $ca  = mlb_color('color_accent',   '#f1a830');
    $cur = esc_url_raw((is_ssl()?'https':'http').'://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI']);

    // Strip OTP-specific params for clean redirects
    $clean     = remove_query_arg(['mlb_err','mlb_step'],$cur);
    $step      = sanitize_text_field($_GET['mlb_step'] ?? '');
    $raw_err   = sanitize_text_field($_GET['mlb_err']  ?? '');

    // ── Error messages ────────────────────────────────────────────────────────
    $err_map = [
        // OTP errors
        'rate'      => '⏱️ Please wait 60 seconds before requesting another code.',
        'send_fail' => '❌ Could not send the email. Please check your email settings or use your password.',
        'wrong'     => '❌ Incorrect code. Please check your email and try again.',
        'locked'    => '🔒 Too many wrong attempts. Please request a new code.',
        'nonce'     => '⚠️ Security token expired — please refresh and try again.',
        'system'    => '⚠️ System error — please contact the admin.',
        // Password errors (fallback)
        'wrong_pw'  => '❌ Incorrect password. Please try again.',
    ];
    $err = $err_map[$raw_err] ?? '';

    // Remaining seconds on the current OTP (for countdown timer)
    $otp_ts        = (int) get_transient('mlb_otp_ts');
    $otp_remaining = $otp_ts ? max(0, MLB_OTP_TTL - (time() - $otp_ts)) : MLB_OTP_TTL;

    ob_start();

    /* ─────────────────────────────────────────────────────────────────────────
       LOGIN  — shown when teacher is NOT logged in
    ───────────────────────────────────────────────────────────────────────── */
    if(!mlb_teacher_ok()):
    ?>
    <div style="min-height:480px;background:linear-gradient(135deg,<?php echo esc_attr($cp);?>,<?php echo esc_attr($cs);?>);display:flex;align-items:center;justify-content:center;border-radius:14px;padding:22px;box-sizing:border-box;">
    <div style="background:#fff;padding:36px;border-radius:16px;box-shadow:0 20px 60px rgba(0,0,0,.22);width:100%;max-width:380px;box-sizing:border-box;">

        <div style="text-align:center;margin-bottom:24px;">
            <div style="font-size:44px;margin-bottom:8px;color:<?php echo esc_attr($cp);?>;">🎵</div>
            <h2 style="margin:0;font-size:21px;color:#1e293b;font-weight:900;">Teacher Portal</h2>
            <p style="margin:5px 0 0;color:#64748b;font-size:13px;">Manage your lessons &amp; schedule</p>
        </div>

        <?php if($err): ?>
        <div style="background:#fee2e2;color:#991b1b;border-left:4px solid #ef4444;padding:10px 13px;border-radius:8px;margin-bottom:16px;font-size:13px;font-weight:700;">
            <?php echo esc_html($err); ?>
        </div>
        <?php endif; ?>

        <?php if($step === 'otp'): ?>
        <!-- ── STEP 2: Enter the code ── -->
        <div style="text-align:center;margin-bottom:18px;">
            <div style="background:#dbeafe;border-radius:10px;padding:12px 14px;display:inline-block;">
                <p style="margin:0;font-size:13px;color:#1e40af;font-weight:700;">✉️ Code sent to your email</p>
                <p style="margin:4px 0 0;font-size:12px;color:#3b82f6;">Check your inbox (and spam folder)</p>
            </div>
        </div>

        <div id="otp-countdown" style="text-align:center;font-size:12px;color:#64748b;margin-bottom:14px;font-weight:600;">
            Expires in <?php echo intdiv($otp_remaining,60).':'.str_pad($otp_remaining%60,2,'0',STR_PAD_LEFT); ?>
        </div>

        <form method="post" action="<?php echo esc_url($clean); ?>" id="otp-form">
            <?php wp_nonce_field('mlb_verify_otp','_mlb_ln'); ?>
            <input type="hidden" name="_mlb_page" value="<?php echo esc_attr($clean); ?>">
            <input type="hidden" name="mlb_otp_code" id="otp-full">

            <!-- 6 individual digit boxes -->
            <div style="display:flex;gap:8px;justify-content:center;margin-bottom:20px;">
                <?php for($i=0;$i<6;$i++): ?>
                <input type="text" class="otp-digit" maxlength="1" inputmode="numeric" pattern="[0-9]" autocomplete="<?php echo $i===0?'one-time-code':'off'; ?>"
                    style="width:44px;height:54px;text-align:center;font-size:24px;font-weight:900;border:2px solid #e2e8f0;border-radius:10px;font-family:monospace;color:#1e293b;transition:.15s;outline:none;background:#f8fafc;"
                    onfocus="this.style.borderColor='<?php echo esc_attr($cp);?>';this.style.background='#fff';this.style.boxShadow='0 0 0 3px <?php echo esc_attr($cp);?>22'"
                    onblur="this.style.borderColor='#e2e8f0';this.style.background='#f8fafc';this.style.boxShadow='none'">
                <?php endfor; ?>
            </div>

            <button type="submit" name="mlb_verify_otp" id="otp-submit"
                style="width:100%;padding:13px;background:linear-gradient(135deg,<?php echo esc_attr($cp);?>,<?php echo esc_attr($cs);?>);color:#fff;border:none;border-radius:10px;font-size:15px;font-weight:900;cursor:pointer;box-shadow:0 5px 18px rgba(0,0,0,.15);">
                Verify &amp; Login →
            </button>
        </form>

        <div style="text-align:center;margin-top:16px;display:flex;flex-direction:column;gap:8px;">
            <!-- Resend form -->
            <form method="post" action="<?php echo esc_url($clean); ?>" style="display:inline;">
                <?php wp_nonce_field('mlb_send_otp','_mlb_ln'); ?>
                <input type="hidden" name="_mlb_page" value="<?php echo esc_attr($clean); ?>">
                <button type="submit" name="mlb_send_otp" value="1"
                    style="background:none;border:none;color:<?php echo esc_attr($cp);?>;font-size:13px;font-weight:700;cursor:pointer;text-decoration:underline;">
                    📧 Resend code
                </button>
            </form>
            <a href="<?php echo esc_url($clean); ?>" style="font-size:12px;color:#94a3b8;text-decoration:none;">← Start over</a>
        </div>

        <?php else: ?>
        <!-- ── STEP 1: Request the code ── -->
        <p style="text-align:center;color:#64748b;font-size:13px;margin:0 0 20px;">
            We'll send a 6-digit login code to your registered email address.
        </p>

        <form method="post" action="<?php echo esc_url($clean); ?>">
            <?php wp_nonce_field('mlb_send_otp','_mlb_ln'); ?>
            <input type="hidden" name="_mlb_page" value="<?php echo esc_attr($clean); ?>">
            <button type="submit" name="mlb_send_otp" value="1"
                style="width:100%;padding:13px;background:linear-gradient(135deg,<?php echo esc_attr($cp);?>,<?php echo esc_attr($cs);?>);color:#fff;border:none;border-radius:10px;font-size:15px;font-weight:900;cursor:pointer;box-shadow:0 5px 18px rgba(0,0,0,.15);margin-bottom:18px;">
                📧 Send Login Code
            </button>
        </form>

        <div style="display:flex;align-items:center;gap:10px;margin-bottom:16px;">
            <div style="flex:1;height:1px;background:#e2e8f0;"></div>
            <span style="color:#94a3b8;font-size:12px;white-space:nowrap;">or</span>
            <div style="flex:1;height:1px;background:#e2e8f0;"></div>
        </div>

        <button onclick="document.getElementById('pw-fallback').style.display=document.getElementById('pw-fallback').style.display==='none'?'block':'none';this.style.display='none';"
            style="width:100%;padding:10px;background:#f8fafc;border:2px solid #e2e8f0;border-radius:10px;font-size:13px;font-weight:700;cursor:pointer;color:#64748b;">
            🔑 Use password instead
        </button>
        <div id="pw-fallback" style="display:none;margin-top:12px;">
            <form method="post" action="<?php echo esc_url($clean); ?>">
                <?php wp_nonce_field('mlb_teacher_login','_mlb_ln'); ?>
                <input type="hidden" name="_mlb_page" value="<?php echo esc_attr($clean); ?>">
                <label style="display:block;font-weight:700;font-size:12px;color:#374151;margin-bottom:5px;">Password</label>
                <input type="password" name="mlb_tpw" required autofocus
                    style="width:100%;padding:11px 12px;border:2px solid #e2e8f0;border-radius:9px;font-size:15px;box-sizing:border-box;margin-bottom:10px;"
                    onfocus="this.style.borderColor='<?php echo esc_attr($cp);?>'"
                    onblur="this.style.borderColor='#e2e8f0'">
                <button type="submit" name="mlb_do_login" value="1"
                    style="width:100%;padding:11px;background:#64748b;color:#fff;border:none;border-radius:9px;font-size:14px;font-weight:800;cursor:pointer;">
                    Login with Password →
                </button>
            </form>
        </div>
        <?php endif; ?>
    </div>
    </div>

    <script>
    (function(){
        // ── OTP digit boxes ──────────────────────────────────────────────────
        var digits = document.querySelectorAll('.otp-digit');
        var fullInput = document.getElementById('otp-full');
        if(!digits.length) return;

        function syncFull(){
            if(fullInput) fullInput.value = Array.from(digits).map(function(d){return d.value;}).join('');
        }

        digits.forEach(function(inp,i){
            inp.addEventListener('input',function(){
                // Keep only digits
                this.value = this.value.replace(/\D/g,'').slice(0,1);
                syncFull();
                if(this.value && i < digits.length-1) digits[i+1].focus();
            });
            inp.addEventListener('keydown',function(e){
                if(e.key==='Backspace'&&!this.value&&i>0) digits[i-1].focus();
                if(e.key==='ArrowLeft'&&i>0) digits[i-1].focus();
                if(e.key==='ArrowRight'&&i<digits.length-1) digits[i+1].focus();
            });
            inp.addEventListener('paste',function(e){
                e.preventDefault();
                var text=((e.clipboardData||window.clipboardData).getData('text')).replace(/\D/g,'').slice(0,6);
                digits.forEach(function(d,j){ d.value=text[j]||''; });
                syncFull();
                var last=Math.min(text.length,5);
                digits[last].focus();
            });
        });

        // Focus first box on load
        if(digits[0]) setTimeout(function(){ digits[0].focus(); },100);

        // Prevent submit if not all 6 filled
        var form=document.getElementById('otp-form');
        if(form) form.addEventListener('submit',function(e){
            syncFull();
            var val=fullInput?fullInput.value:'';
            if(val.length!==6){ e.preventDefault(); digits[0].focus(); return; }
        });

        // ── Countdown timer ──────────────────────────────────────────────────
        var countEl=document.getElementById('otp-countdown');
        if(countEl){
            var secs=<?php echo (int)$otp_remaining; ?>;
            var tick=setInterval(function(){
                secs--;
                if(secs<=0){
                    clearInterval(tick);
                    countEl.textContent='Code expired — please request a new one';
                    countEl.style.color='#ef4444';
                    var sub=document.getElementById('otp-submit');
                    if(sub){ sub.disabled=true; sub.style.opacity='.5'; }
                    return;
                }
                var m=Math.floor(secs/60), s=secs%60;
                countEl.textContent='Expires in '+m+':'+(s<10?'0':'')+s;
                if(secs<=60) countEl.style.color='#f59e0b';
                if(secs<=30) countEl.style.color='#ef4444';
            },1000);
        }
    })();
    </script>

    <?php
    /* ─────────────────────────────────────────────────────────────────────────
       DASHBOARD — shown when teacher IS logged in
    ───────────────────────────────────────────────────────────────────────── */
    else:
        global $wpdb; $bt=$wpdb->prefix.'mlb_bookings';
        $sym  = mlb_sym();
        $s    = get_option('mlb_settings',[]);
        $bh   = $s['business_hours'] ?? [];
        $instruments = array_map('trim',explode(',',$s['instruments']??'Piano, Guitar, Violin, Voice'));
        $days_labels = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];

        $mc=(int)$wpdb->get_var("SELECT COUNT(*) FROM $bt WHERE status='confirmed' AND MONTH(lesson_date)=MONTH(CURDATE())");
        $mr=(float)$wpdb->get_var("SELECT SUM(price) FROM $bt WHERE status='confirmed' AND MONTH(lesson_date)=MONTH(CURDATE())");
        $tc=(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $bt WHERE status='confirmed' AND lesson_date=%s",date('Y-m-d')));

        $an   = wp_create_nonce('mlb_teacher_action');
        $ajurl= admin_url('admin-ajax.php');
    ?>
    <div id="mlb-portal" style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;color:#2d1810;max-width:1100px;margin:0 auto;">

        <!-- Header -->
        <div style="background:linear-gradient(135deg,<?php echo esc_attr($cp);?>,<?php echo esc_attr($cs);?>);color:#fff;border-radius:14px;padding:18px 22px;margin-bottom:16px;display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;box-shadow:0 6px 20px rgba(0,0,0,.12);">
            <div>
                <h2 style="margin:0;font-size:18px;font-weight:900;color:#fff;text-shadow:0 1px 2px rgba(0,0,0,.15);">🎵 Teacher Dashboard</h2>
                <p style="margin:3px 0 0;color:#fff;opacity:.9;font-size:12px;">Today: <?php echo date('l, d F Y');?></p>
            </div>
            <form method="post" action="<?php echo esc_url($clean);?>" style="margin:0;">
                <?php wp_nonce_field('mlb_teacher_logout','_mlb_ln'); ?>
                <input type="hidden" name="_mlb_page" value="<?php echo esc_attr($clean);?>">
                <button type="submit" name="mlb_do_logout" value="1" style="padding:8px 14px;background:rgba(255,255,255,.2);color:#fff;border:2px solid rgba(255,255,255,.4);border-radius:8px;font-weight:800;cursor:pointer;font-size:13px;">Logout</button>
            </form>
        </div>

        <!-- Stats -->
        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:11px;margin-bottom:16px;">
            <?php foreach([['📅','Lessons This Month',$mc,$cp],['💰','Revenue This Month',$sym.number_format($mr,2),'#10b981'],['⏰',"Today's Lessons",$tc,$ca]] as $x): ?>
            <div style="background:#fff;border-radius:11px;padding:15px 17px;box-shadow:0 2px 10px rgba(0,0,0,.07);border-top:4px solid <?php echo esc_attr($x[3]);?>;">
                <div style="font-size:22px;font-weight:900;color:<?php echo esc_attr($x[3]);?>"><?php echo esc_html($x[2]);?></div>
                <div style="font-size:12px;color:#64748b;margin-top:3px;"><?php echo esc_html($x[0].' '.$x[1]);?></div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Tabs -->
        <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;margin-bottom:12px;">
            <div style="display:flex;gap:3px;background:#fff;padding:4px;border-radius:10px;box-shadow:0 1px 4px rgba(0,0,0,.07);">
                <?php foreach(['upcoming'=>'📅 Upcoming','today'=>'⏰ Today','all'=>'📋 All','cancelled'=>'🚫 Cancelled'] as $k=>$v): ?>
                <button id="t-tab-<?php echo $k;?>" onclick="tTab('<?php echo $k;?>')"
                    style="padding:7px 13px;border-radius:7px;border:none;background:<?php echo $k==='upcoming'?esc_attr($cp):'transparent';?>;color:<?php echo $k==='upcoming'?'#fff':'#64748b';?>;font-size:13px;font-weight:700;cursor:pointer;transition:.15s;white-space:nowrap;">
                    <?php echo $v;?>
                </button>
                <?php endforeach; ?>
            </div>
            <button id="t-tab-settings" onclick="tTabSettings()"
                style="padding:7px 15px;border-radius:8px;border:2px solid #e2e8f0;background:#fff;color:#64748b;font-size:13px;font-weight:700;cursor:pointer;transition:.15s;white-space:nowrap;box-shadow:0 1px 4px rgba(0,0,0,.07);">
                ⚙️ My Settings
            </button>
        </div>

        <!-- Search bar -->
        <div id="t-search-bar" style="background:#fff;border-radius:11px;padding:14px 16px;margin-bottom:12px;box-shadow:0 2px 8px rgba(0,0,0,.06);">
            <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
                <input type="text" id="t-search" placeholder="🔍 Search name, email, or code…" style="flex:1;min-width:180px;padding:8px 12px;border:2px solid #e2e8f0;border-radius:8px;font-size:13px;" oninput="clearTimeout(window._tTimer);window._tTimer=setTimeout(tLoad,400)">
                <select id="t-inst" onchange="tLoad()" style="padding:8px 10px;border:2px solid #e2e8f0;border-radius:8px;font-size:13px;background:#fff;">
                    <option value="">All Instruments</option>
                    <?php foreach($instruments as $i): ?><option value="<?php echo esc_attr($i);?>"><?php echo esc_html($i);?></option><?php endforeach; ?>
                </select>
                <input type="date" id="t-dfr" onchange="tLoad()" style="padding:8px 10px;border:2px solid #e2e8f0;border-radius:8px;font-size:13px;">
                <span style="color:#64748b;font-size:13px;">to</span>
                <input type="date" id="t-dto" onchange="tLoad()" style="padding:8px 10px;border:2px solid #e2e8f0;border-radius:8px;font-size:13px;">
                <button onclick="tClear()" style="padding:8px 14px;background:#f1f5f9;border:2px solid #e2e8f0;border-radius:8px;font-size:13px;font-weight:700;cursor:pointer;color:#64748b;">Clear</button>
            </div>
        </div>

        <!-- Bookings table -->
        <div id="t-table-wrap" style="background:#fff;border-radius:12px;box-shadow:0 2px 10px rgba(0,0,0,.07);">
            <div style="padding:30px;text-align:center;color:#94a3b8;">⏳ Loading…</div>
        </div>

        <!-- Settings panel -->
        <div id="t-settings-wrap" style="display:none;">
            <!-- Schedule -->
            <div style="background:#fff;border-radius:12px;padding:20px 22px;margin-bottom:16px;box-shadow:0 2px 10px rgba(0,0,0,.07);border-top:4px solid <?php echo esc_attr($cp);?>;">
                <h3 style="margin:0 0 16px;font-size:15px;font-weight:800;color:#2d1810;border-bottom:2px solid #e2e8f0;padding-bottom:10px;">📅 Lesson Schedule</h3>
                <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-bottom:20px;">
                    <div>
                        <label style="display:block;font-weight:700;font-size:12px;color:#374151;margin-bottom:5px;">Lesson Duration</label>
                        <div style="display:flex;align-items:center;gap:7px;">
                            <input type="number" id="ts-duration" min="15" max="240" step="15" value="<?php echo esc_attr($s['lesson_duration']??60);?>" style="width:80px;padding:8px 10px;border:2px solid #e2e8f0;border-radius:8px;font-size:14px;font-weight:700;">
                            <span style="font-size:13px;color:#64748b;">minutes</span>
                        </div>
                        <p style="font-size:11px;color:#94a3b8;margin:4px 0 0;">How long each lesson runs</p>
                    </div>
                    <div>
                        <label style="display:block;font-weight:700;font-size:12px;color:#374151;margin-bottom:5px;">Break Between Lessons</label>
                        <div style="display:flex;align-items:center;gap:7px;">
                            <input type="number" id="ts-buffer" min="0" max="120" step="5" value="<?php echo esc_attr($s['buffer_minutes']??30);?>" style="width:80px;padding:8px 10px;border:2px solid #e2e8f0;border-radius:8px;font-size:14px;font-weight:700;">
                            <span style="font-size:13px;color:#64748b;">minutes</span>
                        </div>
                        <p style="font-size:11px;color:#94a3b8;margin:4px 0 0;">Prep, toilet break, changeover</p>
                    </div>
                    <div>
                        <label style="display:block;font-weight:700;font-size:12px;color:#374151;margin-bottom:5px;">Advance Booking Window</label>
                        <div style="display:flex;align-items:center;gap:7px;">
                            <input type="number" id="ts-advance" min="1" max="365" value="<?php echo esc_attr($s['max_advance']??90);?>" style="width:80px;padding:8px 10px;border:2px solid #e2e8f0;border-radius:8px;font-size:14px;font-weight:700;">
                            <span style="font-size:13px;color:#64748b;">days ahead</span>
                        </div>
                        <p style="font-size:11px;color:#94a3b8;margin:4px 0 0;">How far ahead students can book</p>
                    </div>
                </div>
                <h4 style="margin:0 0 12px;font-size:13px;font-weight:800;color:#374151;">Teaching Days &amp; Hours</h4>
                <div style="display:flex;flex-direction:column;gap:8px;margin-bottom:18px;">
                    <?php foreach($days_labels as $di=>$dname):
                        $dh=$bh[$di]??['off','off']; $closed=($dh[0]==='off');
                        $topen=$closed?'09:00':$dh[0]; $tclose=$closed?'17:00':$dh[1];
                    ?>
                    <div class="ts-day-row" data-day="<?php echo $di;?>" style="display:grid;grid-template-columns:120px 1fr;gap:10px;align-items:center;padding:10px 14px;background:#f8fafc;border-radius:9px;border:2px solid <?php echo $closed?'#e2e8f0':esc_attr($cp).'44';?>;">
                        <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-weight:700;font-size:13px;white-space:nowrap;">
                            <input type="checkbox" class="ts-day-open" data-day="<?php echo $di;?>" <?php echo $closed?'':'checked';?> onchange="tsDayToggle(<?php echo $di;?>,this.checked)" style="width:16px;height:16px;accent-color:<?php echo esc_attr($cp);?>;">
                            <?php echo $dname;?>
                        </label>
                        <div style="display:flex;align-items:center;gap:8px;">
                            <div class="ts-day-times" style="display:<?php echo $closed?'none':'flex';?>;align-items:center;gap:8px;">
                                <input type="time" class="ts-open" data-day="<?php echo $di;?>" value="<?php echo esc_attr($topen);?>" style="padding:7px 9px;border:2px solid #e2e8f0;border-radius:8px;font-size:13px;font-weight:700;color:<?php echo esc_attr($cp);?>;">
                                <span style="color:#94a3b8;font-size:12px;">to</span>
                                <input type="time" class="ts-close" data-day="<?php echo $di;?>" value="<?php echo esc_attr($tclose);?>" style="padding:7px 9px;border:2px solid #e2e8f0;border-radius:8px;font-size:13px;font-weight:700;color:#1e293b;">
                            </div>
                            <div class="ts-day-off-label" style="display:<?php echo $closed?'block':'none';?>;font-size:12px;color:#94a3b8;font-style:italic;">Day off — no bookings accepted</div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div style="display:flex;align-items:center;gap:12px;">
                    <button onclick="tsSaveSchedule()" style="padding:10px 22px;background:linear-gradient(135deg,<?php echo esc_attr($cp);?>,<?php echo esc_attr($cs);?>);color:#fff;border:none;border-radius:9px;font-size:14px;font-weight:900;cursor:pointer;box-shadow:0 4px 14px rgba(0,0,0,.12);">💾 Save Schedule</button>
                    <span id="ts-sched-msg" style="font-size:13px;font-weight:700;"></span>
                </div>
            </div>
            <!-- Rates -->
            <div style="background:#fff;border-radius:12px;padding:20px 22px;margin-bottom:16px;box-shadow:0 2px 10px rgba(0,0,0,.07);border-top:4px solid #10b981;">
                <h3 style="margin:0 0 16px;font-size:15px;font-weight:800;color:#2d1810;border-bottom:2px solid #e2e8f0;padding-bottom:10px;">💰 Rates &amp; Instruments</h3>
                <div style="display:grid;grid-template-columns:100px 1fr 1fr;gap:14px;margin-bottom:18px;align-items:start;">
                    <div>
                        <label style="display:block;font-weight:700;font-size:12px;color:#374151;margin-bottom:5px;">Currency</label>
                        <input type="text" id="ts-currency" value="<?php echo esc_attr($s['currency_symbol']??'R');?>" maxlength="5" style="width:60px;padding:8px 10px;border:2px solid #e2e8f0;border-radius:8px;font-size:16px;font-weight:900;text-align:center;">
                    </div>
                    <div>
                        <label style="display:block;font-weight:700;font-size:12px;color:#374151;margin-bottom:5px;">Price Per Lesson</label>
                        <div style="display:flex;align-items:center;gap:6px;">
                            <span id="ts-currency-preview" style="font-weight:900;font-size:16px;color:#10b981;"><?php echo esc_html($s['currency_symbol']??'R');?></span>
                            <input type="number" id="ts-price" min="0" step="0.01" value="<?php echo esc_attr(number_format((float)($s['lesson_price']??350),2,'.','')); ?>" style="width:120px;padding:8px 10px;border:2px solid #e2e8f0;border-radius:8px;font-size:14px;font-weight:700;">
                        </div>
                    </div>
                    <div>
                        <label style="display:block;font-weight:700;font-size:12px;color:#374151;margin-bottom:5px;">Instruments I Teach</label>
                        <input type="text" id="ts-instruments" value="<?php echo esc_attr($s['instruments']??'Piano, Guitar, Violin, Voice');?>" style="width:100%;padding:8px 11px;border:2px solid #e2e8f0;border-radius:8px;font-size:13px;box-sizing:border-box;">
                        <p style="font-size:11px;color:#94a3b8;margin:4px 0 0;">Comma-separated — appears in the booking form</p>
                    </div>
                </div>
                <div style="display:flex;align-items:center;gap:12px;">
                    <button onclick="tsSaveRates()" style="padding:10px 22px;background:linear-gradient(135deg,#10b981,#059669);color:#fff;border:none;border-radius:9px;font-size:14px;font-weight:900;cursor:pointer;">💾 Save Rates</button>
                    <span id="ts-rates-msg" style="font-size:13px;font-weight:700;"></span>
                </div>
            </div>
            <!-- Blocked Dates -->
            <div style="background:#fff;border-radius:12px;padding:20px 22px;margin-bottom:16px;box-shadow:0 2px 10px rgba(0,0,0,.07);border-top:4px solid <?php echo esc_attr($ca);?>;">
                <h3 style="margin:0 0 8px;font-size:15px;font-weight:800;color:#2d1810;border-bottom:2px solid #e2e8f0;padding-bottom:10px;">🚫 Days Off &amp; Blocked Dates</h3>
                <p style="color:#64748b;font-size:13px;margin:0 0 14px;">Students cannot book on these dates.</p>
                <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end;margin-bottom:16px;padding:14px;background:#f8fafc;border-radius:9px;">
                    <div><label style="display:block;font-weight:700;font-size:12px;color:#374151;margin-bottom:4px;">Date</label><input type="date" id="ts-block-date" style="padding:8px 10px;border:2px solid #e2e8f0;border-radius:8px;font-size:13px;"></div>
                    <div style="flex:1;min-width:160px;"><label style="display:block;font-weight:700;font-size:12px;color:#374151;margin-bottom:4px;">Reason (optional)</label><input type="text" id="ts-block-reason" placeholder="e.g. Public Holiday" style="width:100%;padding:8px 10px;border:2px solid #e2e8f0;border-radius:8px;font-size:13px;box-sizing:border-box;"></div>
                    <button onclick="tsAddBlocked()" style="padding:9px 18px;background:linear-gradient(135deg,<?php echo esc_attr($ca);?>,#d97706);color:#fff;border:none;border-radius:8px;font-size:13px;font-weight:900;cursor:pointer;white-space:nowrap;">🚫 Block This Date</button>
                    <span id="ts-block-msg" style="font-size:13px;font-weight:700;"></span>
                </div>
                <div id="ts-blocked-list"><div style="padding:16px;text-align:center;color:#94a3b8;font-size:13px;">⏳ Loading…</div></div>
            </div>
        </div>

        <!-- Edit booking slide-up panel -->
        <div id="t-edit-panel" style="display:none;position:fixed;bottom:0;left:0;right:0;z-index:9999;background:#fff;box-shadow:0 -8px 40px rgba(0,0,0,.18);border-radius:14px 14px 0 0;max-height:85vh;overflow-y:auto;">
            <div style="padding:18px 22px;border-bottom:2px solid #e2e8f0;display:flex;justify-content:space-between;align-items:center;">
                <h3 style="margin:0;font-size:16px;font-weight:800;color:#2d1810;">✏️ Edit Booking <span id="t-edit-code" style="font-size:12px;color:<?php echo esc_attr($cp);?>;font-family:monospace;margin-left:8px;"></span></h3>
                <button onclick="tCloseEdit()" style="background:none;border:none;font-size:22px;cursor:pointer;color:#64748b;line-height:1;">✕</button>
            </div>
            <form id="t-edit-form" onsubmit="tSaveEdit(event)" style="padding:18px 22px;">
                <input type="hidden" id="t-edit-id">
                <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px;margin-bottom:14px;">
                    <div><label style="display:block;font-weight:700;font-size:12px;color:#374151;margin-bottom:5px;">Name *</label><input type="text" id="te-name" required style="width:100%;padding:9px 11px;border:2px solid #e2e8f0;border-radius:8px;font-size:13px;box-sizing:border-box;"></div>
                    <div><label style="display:block;font-weight:700;font-size:12px;color:#374151;margin-bottom:5px;">Email</label><input type="email" id="te-email" style="width:100%;padding:9px 11px;border:2px solid #e2e8f0;border-radius:8px;font-size:13px;box-sizing:border-box;"></div>
                    <div><label style="display:block;font-weight:700;font-size:12px;color:#374151;margin-bottom:5px;">Phone</label><input type="tel" id="te-phone" style="width:100%;padding:9px 11px;border:2px solid #e2e8f0;border-radius:8px;font-size:13px;box-sizing:border-box;"></div>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr 1fr;gap:14px;margin-bottom:14px;">
                    <div><label style="display:block;font-weight:700;font-size:12px;color:#374151;margin-bottom:5px;">Instrument *</label><select id="te-instr" required style="width:100%;padding:9px 10px;border:2px solid #e2e8f0;border-radius:8px;font-size:13px;background:#fff;"><?php foreach($instruments as $i): ?><option value="<?php echo esc_attr($i);?>"><?php echo esc_html($i);?></option><?php endforeach; ?></select></div>
                    <div><label style="display:block;font-weight:700;font-size:12px;color:#374151;margin-bottom:5px;">Date *</label><input type="date" id="te-date" required style="width:100%;padding:9px 11px;border:2px solid #e2e8f0;border-radius:8px;font-size:13px;box-sizing:border-box;"></div>
                    <div><label style="display:block;font-weight:700;font-size:12px;color:#374151;margin-bottom:5px;">Time *</label><input type="time" id="te-time" required style="width:100%;padding:9px 11px;border:2px solid #e2e8f0;border-radius:8px;font-size:13px;box-sizing:border-box;"></div>
                    <div><label style="display:block;font-weight:700;font-size:12px;color:#374151;margin-bottom:5px;">Duration (min)</label><input type="number" id="te-dur" min="15" style="width:100%;padding:9px 11px;border:2px solid #e2e8f0;border-radius:8px;font-size:13px;box-sizing:border-box;"></div>
                    <div><label style="display:block;font-weight:700;font-size:12px;color:#374151;margin-bottom:5px;">Price (<?php echo mlb_sym();?>)</label><input type="number" id="te-price" step="0.01" min="0" style="width:100%;padding:9px 11px;border:2px solid #e2e8f0;border-radius:8px;font-size:13px;box-sizing:border-box;"></div>
                </div>
                <div style="display:grid;grid-template-columns:1fr 140px;gap:14px;margin-bottom:16px;">
                    <div><label style="display:block;font-weight:700;font-size:12px;color:#374151;margin-bottom:5px;">Notes</label><textarea id="te-notes" rows="2" style="width:100%;padding:9px 11px;border:2px solid #e2e8f0;border-radius:8px;font-size:13px;box-sizing:border-box;font-family:inherit;resize:vertical;"></textarea></div>
                    <div><label style="display:block;font-weight:700;font-size:12px;color:#374151;margin-bottom:5px;">Status</label><select id="te-status" style="width:100%;padding:9px 10px;border:2px solid #e2e8f0;border-radius:8px;font-size:13px;background:#fff;"><option value="confirmed">Confirmed</option><option value="cancelled">Cancelled</option></select></div>
                </div>
                <div style="display:flex;gap:10px;align-items:center;">
                    <button type="submit" style="padding:10px 24px;background:linear-gradient(135deg,<?php echo esc_attr($cp);?>,<?php echo esc_attr($cs);?>);color:#fff;border:none;border-radius:9px;font-size:14px;font-weight:900;cursor:pointer;">💾 Save</button>
                    <button type="button" onclick="tCloseEdit()" style="padding:10px 18px;background:#f1f5f9;border:2px solid #e2e8f0;border-radius:9px;font-size:14px;font-weight:700;cursor:pointer;color:#64748b;">Cancel</button>
                    <span id="t-edit-msg" style="font-size:13px;font-weight:700;"></span>
                </div>
            </form>
        </div>
        <div id="t-edit-bg" onclick="tCloseEdit()" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.4);z-index:9998;"></div>
    </div>

    <style>
    #t-table-wrap table{width:100%;border-collapse:collapse;font-size:13px;}
    #t-table-wrap th{background:#fdf2e9;padding:9px 12px;text-align:left;font-size:11px;font-weight:800;color:#64748b;text-transform:uppercase;letter-spacing:.4px;border-bottom:2px solid #e2e8f0;}
    #t-table-wrap td{padding:11px 12px;border-bottom:1px solid #f1f5f9;vertical-align:middle;}
    #t-table-wrap tr:last-child td{border-bottom:none;}
    #t-table-wrap tbody tr:hover td{background:#fdf9f7;}
    .t-action-btn{padding:5px 11px;border:none;border-radius:7px;font-size:12px;font-weight:700;cursor:pointer;display:inline-flex;align-items:center;gap:4px;margin:2px;}
    @media(max-width:700px){#mlb-portal>div:nth-child(2){grid-template-columns:1fr!important;}.ts-day-row{grid-template-columns:1fr!important;}#t-edit-form>div{grid-template-columns:1fr!important;}}
    </style>

    <script>
    (function(){
    var AJ='<?php echo esc_js($ajurl);?>';
    var AN='<?php echo esc_js($an);?>';
    var CP='<?php echo esc_js($cp);?>';
    var SYM='<?php echo esc_js(mlb_sym());?>';
    var tab='upcoming';

    function post(a,d,cb){
        var fd=new FormData();fd.append('action',a);fd.append('_ajax_nonce',AN);
        Object.keys(d).forEach(function(k){fd.append(k,d[k]);});
        fetch(AJ,{method:'POST',body:fd}).then(function(r){return r.json();}).then(cb).catch(function(){cb({success:false,data:'Network error'});});
    }
    function esc(s){var d=document.createElement('div');d.textContent=s||'';return d.innerHTML;}

    // ── Booking tabs ──
    window.tTab=function(t){
        tab=t;
        ['upcoming','today','all','cancelled'].forEach(function(k){var b=document.getElementById('t-tab-'+k);if(b){b.style.background=k===t?CP:'transparent';b.style.color=k===t?'#fff':'#64748b';}});
        var sb=document.getElementById('t-tab-settings');if(sb){sb.style.background='#fff';sb.style.color='#64748b';sb.style.borderColor='#e2e8f0';}
        document.getElementById('t-search-bar').style.display='';
        document.getElementById('t-table-wrap').style.display='';
        document.getElementById('t-settings-wrap').style.display='none';
        tLoad();
    };
    window.tTabSettings=function(){
        ['upcoming','today','all','cancelled'].forEach(function(k){var b=document.getElementById('t-tab-'+k);if(b){b.style.background='transparent';b.style.color='#64748b';}});
        var sb=document.getElementById('t-tab-settings');if(sb){sb.style.background=CP;sb.style.color='#fff';sb.style.borderColor=CP;}
        document.getElementById('t-search-bar').style.display='none';
        document.getElementById('t-table-wrap').style.display='none';
        document.getElementById('t-settings-wrap').style.display='block';
        tsLoadBlocked();
    };
    window.tLoad=function(){
        var w=document.getElementById('t-table-wrap');
        w.innerHTML='<div style="padding:30px;text-align:center;color:#94a3b8;">⏳ Loading…</div>';
        post('mlb_t_bookings',{tab:tab,s:document.getElementById('t-search').value,inst:document.getElementById('t-inst').value,dfr:document.getElementById('t-dfr').value,dto:document.getElementById('t-dto').value},function(res){
            if(!res.success){w.innerHTML='<div style="padding:30px;text-align:center;color:#ef4444;">Error loading bookings.</div>';return;}
            var rows=res.data;
            if(!rows||!rows.length){w.innerHTML='<div style="padding:40px;text-align:center;color:#94a3b8;"><div style="font-size:36px;margin-bottom:8px;">📭</div><p>No bookings found.</p></div>';return;}
            var h='<div style="overflow-x:auto;"><table><thead><tr><th>#</th><th>Student</th><th>Instrument</th><th>Date &amp; Time</th><th>Dur</th><th>Price</th><th>Code</th><th>Status</th><th style="min-width:180px;">Actions</th></tr></thead><tbody>';
            rows.forEach(function(b){
                var isToday=(b.lesson_date===new Date().toISOString().slice(0,10));
                var sc=b.status==='confirmed'?'#10b981':b.status==='cancelled'?'#ef4444':'#64748b';
                var df=new Date(b.lesson_date+'T00:00:00').toLocaleDateString('en-ZA',{day:'2-digit',month:'short',year:'numeric'});
                var tf=b.lesson_time?b.lesson_time.slice(0,5):'';
                try{tf=new Date('2000-01-01T'+b.lesson_time).toLocaleTimeString([],{hour:'numeric',minute:'2-digit'});}catch(e){}
                h+='<tr><td style="color:#94a3b8;font-size:11px;">#'+b.id+'</td>'
                +'<td><strong>'+esc(b.student_name)+'</strong>'+(isToday?'<span style="background:#fef3c7;color:#92400e;padding:1px 6px;border-radius:20px;font-size:10px;font-weight:800;margin-left:4px;">TODAY</span>':'')+'<br><small style="color:#94a3b8;">'+esc(b.student_email)+'</small>'+(b.student_phone?'<br><small style="color:#94a3b8;">'+esc(b.student_phone)+'</small>':'')+'</td>'
                +'<td>'+esc(b.instrument)+'</td><td>'+df+'<br><small style="color:'+CP+';font-weight:700;">'+tf+'</small></td>'
                +'<td>'+b.duration+'m</td><td><strong>'+SYM+parseFloat(b.price).toFixed(2)+'</strong></td>'
                +'<td><code style="background:#fff7ed;color:'+CP+';padding:3px 7px;border-radius:5px;font-size:11px;font-weight:900;letter-spacing:1px;">'+esc(b.confirmation_code)+'</code></td>'
                +'<td><span style="background:'+sc+'22;color:'+sc+';padding:3px 9px;border-radius:20px;font-size:11px;font-weight:800;">'+esc(b.status)+'</span></td>'
                +'<td><button class="t-action-btn" style="background:#dbeafe;color:#1e40af;" onclick=\'tEdit('+JSON.stringify(b)+')\'>✏️ Edit</button>'
                +(b.status==='confirmed'?'<button class="t-action-btn" style="background:#fef3c7;color:#92400e;" onclick="tCancel('+b.id+',\''+esc(b.student_name)+'\')">🚫 Cancel</button>':'')
                +'<button class="t-action-btn" style="background:#fee2e2;color:#991b1b;" onclick="tDelete('+b.id+',\''+esc(b.student_name)+'\')">🗑️ Delete</button></td></tr>';
            });
            w.innerHTML=h+'</tbody></table></div>';
        });
    };
    window.tClear=function(){document.getElementById('t-search').value='';document.getElementById('t-inst').value='';document.getElementById('t-dfr').value='';document.getElementById('t-dto').value='';tLoad();};
    window.tCancel=function(id,name){if(!confirm('Cancel lesson for '+name+'?\nThe student will receive a cancellation email.')) return;post('mlb_t_cancel',{id:id},function(res){if(res.success)tLoad();else alert(res.data||'Error');});};
    window.tDelete=function(id,name){if(!confirm('PERMANENTLY DELETE booking for '+name+'?\nThis cannot be undone.')) return;post('mlb_t_delete',{id:id},function(res){if(res.success)tLoad();else alert(res.data||'Error');});};
    window.tEdit=function(b){
        ['id','code'].forEach(function(f){var el=document.getElementById('t-edit-'+f);if(el)el[f==='id'?'value':'textContent']=b[f==='id'?'id':'confirmation_code']||'';});
        [['te-name','student_name'],['te-email','student_email'],['te-phone','student_phone'],['te-instr','instrument'],['te-date','lesson_date'],['te-time','lesson_time'],['te-dur','duration'],['te-price','price'],['te-notes','notes'],['te-status','status']].forEach(function(p){var el=document.getElementById(p[0]);if(el)el.value=p[1]==='lesson_time'?(b[p[1]]||'').slice(0,5):(b[p[1]]||'');});
        document.getElementById('t-edit-msg').textContent='';
        document.getElementById('t-edit-panel').style.display='block';
        document.getElementById('t-edit-bg').style.display='block';
        document.body.style.overflow='hidden';
    };
    window.tCloseEdit=function(){document.getElementById('t-edit-panel').style.display='none';document.getElementById('t-edit-bg').style.display='none';document.body.style.overflow='';};
    window.tSaveEdit=function(e){
        e.preventDefault();var msg=document.getElementById('t-edit-msg');msg.textContent='Saving…';msg.style.color='#64748b';
        post('mlb_t_save',{id:document.getElementById('t-edit-id').value,student_name:document.getElementById('te-name').value,student_email:document.getElementById('te-email').value,student_phone:document.getElementById('te-phone').value,instrument:document.getElementById('te-instr').value,lesson_date:document.getElementById('te-date').value,lesson_time:document.getElementById('te-time').value,duration:document.getElementById('te-dur').value,price:document.getElementById('te-price').value,notes:document.getElementById('te-notes').value,status:document.getElementById('te-status').value},
        function(res){if(res.success){msg.textContent='✅ Saved!';msg.style.color='#065f46';setTimeout(function(){tCloseEdit();tLoad();},800);}else{msg.textContent='❌ '+(res.data||'Error');msg.style.color='#991b1b';}});
    };
    // ── Settings ──
    window.tsDayToggle=function(day,open){
        var row=document.querySelector('.ts-day-row[data-day="'+day+'"]');if(!row)return;
        row.querySelector('.ts-day-times').style.display=open?'flex':'none';
        row.querySelector('.ts-day-off-label').style.display=open?'none':'block';
        row.style.borderColor=open?CP+'44':'#e2e8f0';
    };
    window.tsSaveSchedule=function(){
        var msg=document.getElementById('ts-sched-msg');msg.textContent='Saving…';msg.style.color='#64748b';
        var d={lesson_duration:document.getElementById('ts-duration').value,buffer_minutes:document.getElementById('ts-buffer').value,max_advance:document.getElementById('ts-advance').value};
        for(var i=0;i<7;i++){var cb=document.querySelector('.ts-day-open[data-day="'+i+'"]'),open=cb&&cb.checked,o=document.querySelector('.ts-open[data-day="'+i+'"]'),c=document.querySelector('.ts-close[data-day="'+i+'"]');d['bh_s'+i]=open&&o?o.value:'off';d['bh_e'+i]=open&&c?c.value:'off';}
        post('mlb_t_save_schedule',d,function(res){msg.textContent=res.data;msg.style.color=res.success?'#065f46':'#991b1b';});
    };
    window.tsSaveRates=function(){
        var msg=document.getElementById('ts-rates-msg');msg.textContent='Saving…';msg.style.color='#64748b';
        post('mlb_t_save_rates',{lesson_price:document.getElementById('ts-price').value,currency_symbol:document.getElementById('ts-currency').value,instruments:document.getElementById('ts-instruments').value},
        function(res){msg.textContent=res.data;msg.style.color=res.success?'#065f46':'#991b1b';});
    };
    var curInp=document.getElementById('ts-currency'),curPrev=document.getElementById('ts-currency-preview');
    if(curInp&&curPrev)curInp.addEventListener('input',function(){curPrev.textContent=this.value||'R';});
    window.tsLoadBlocked=function(){
        var list=document.getElementById('ts-blocked-list');if(!list)return;
        post('mlb_t_get_blocked',{},function(res){
            if(!res.success||!res.data||!res.data.length){list.innerHTML='<p style="color:#94a3b8;font-size:13px;text-align:center;padding:12px;">No dates blocked yet.</p>';return;}
            var h='<div style="display:flex;flex-direction:column;gap:6px;">';
            res.data.forEach(function(b){var df=new Date(b.unavailable_date+'T00:00:00').toLocaleDateString('en-ZA',{weekday:'long',day:'2-digit',month:'long',year:'numeric'});h+='<div style="display:flex;justify-content:space-between;align-items:center;padding:10px 14px;background:#fef2f2;border-radius:8px;border-left:3px solid #ef4444;"><div><strong style="font-size:13px;color:#991b1b;">'+esc(df)+'</strong>'+(b.reason?'<span style="color:#94a3b8;font-size:12px;margin-left:8px;">— '+esc(b.reason)+'</span>':'')+'</div><button onclick="tsRemoveBlocked('+b.id+')" style="padding:4px 10px;background:#fee2e2;color:#991b1b;border:none;border-radius:6px;font-size:12px;font-weight:700;cursor:pointer;">Remove</button></div>';});
            list.innerHTML=h+'</div>';
        });
    };
    window.tsAddBlocked=function(){
        var date=document.getElementById('ts-block-date').value,reason=document.getElementById('ts-block-reason').value,msg=document.getElementById('ts-block-msg');
        if(!date){msg.textContent='Pick a date first';msg.style.color='#991b1b';return;}
        msg.textContent='Adding…';msg.style.color='#64748b';
        post('mlb_t_add_blocked',{date:date,reason:reason},function(res){if(res.success){msg.textContent='✅ Blocked!';msg.style.color='#065f46';document.getElementById('ts-block-date').value='';document.getElementById('ts-block-reason').value='';tsLoadBlocked();setTimeout(function(){msg.textContent='';},2500);}else{msg.textContent='❌ '+(res.data||'Error');msg.style.color='#991b1b';}});
    };
    window.tsRemoveBlocked=function(id){if(!confirm('Remove this blocked date?'))return;post('mlb_t_remove_blocked',{id:id},function(res){if(res.success)tsLoadBlocked();});};
    document.addEventListener('DOMContentLoaded',function(){tLoad();});
    if(document.readyState!=='loading')tLoad();
    })();
    </script>
    <?php
    endif;
    return ob_get_clean();
}
