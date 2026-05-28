<?php
if(!defined('ABSPATH')) exit;
function mlb_pg_bookings(){
    global $wpdb; $bt=$wpdb->prefix.'mlb_bookings';
    $base=admin_url('admin.php?page=mlb-book');

    if(isset($_GET['mlb_del'])&&wp_verify_nonce($_GET['_wpnonce']??'','mlb_del_'.intval($_GET['mlb_del']))){
        $wpdb->delete($bt,['id'=>intval($_GET['mlb_del'])],['%d']);
        echo '<div class="mlb-ok">✅ Booking deleted.</div>';
    }
    if(isset($_GET['mlb_can'])&&wp_verify_nonce($_GET['_wpnonce']??'','mlb_can_'.intval($_GET['mlb_can']))){
        $wpdb->update($bt,['status'=>'cancelled'],['id'=>intval($_GET['mlb_can'])],['%s'],['%d']);
        echo '<div class="mlb-ok">✅ Booking cancelled.</div>';
    }

    $tab  = sanitize_text_field($_GET['tab'] ?? 'all');
    $srch = sanitize_text_field($_GET['s']   ?? '');
    $finst= sanitize_text_field($_GET['inst']?? '');
    $fdfr = sanitize_text_field($_GET['dfr'] ?? '');
    $fdto = sanitize_text_field($_GET['dto'] ?? '');
    $wheres=[];
    if($tab==='upcoming')  $wheres[]="status='confirmed' AND lesson_date>=CURDATE()";
    elseif($tab==='today') $wheres[]="status='confirmed' AND lesson_date='".date('Y-m-d')."'";
    elseif($tab==='cancelled') $wheres[]="status='cancelled'";
    if($srch){$like='%'.$wpdb->esc_like($srch).'%';$wheres[]=$wpdb->prepare("(student_name LIKE %s OR student_email LIKE %s OR confirmation_code LIKE %s OR student_phone LIKE %s)",$like,$like,$like,$like);}
    if($finst) $wheres[]=$wpdb->prepare("instrument=%s",$finst);
    if($fdfr)  $wheres[]=$wpdb->prepare("lesson_date>=%s",$fdfr);
    if($fdto)  $wheres[]=$wpdb->prepare("lesson_date<=%s",$fdto);
    $where=$wheres?'WHERE '.implode(' AND ',$wheres):'';
    $bk=$wpdb->get_results("SELECT * FROM $bt $where ORDER BY lesson_date DESC,lesson_time DESC");
    $sym=mlb_sym(); $col=mlb_color('color_primary','#e36868');
    $all_insts=$wpdb->get_col("SELECT DISTINCT instrument FROM $bt ORDER BY instrument");
    $instruments=array_map('trim',explode(',',(get_option('mlb_settings',[])['instruments']??'Piano, Guitar, Violin, Voice')));

    echo '<div class="wrap">';
    echo '<div class="mlb-hd"><div class="mlb-hd-i">📋</div><div><h1>All Bookings</h1><p>'.count($bk).' result(s)</p></div></div>';

    // Tabs
    echo '<div class="mlb-tabs">';
    foreach(['all'=>'All','upcoming'=>'Upcoming','today'=>'Today','cancelled'=>'Cancelled'] as $k=>$v)
        echo '<a href="'.esc_url(add_query_arg(['tab'=>$k],$base)).'" class="mlb-tab '.($tab===$k?'act':'').'">'.$v.'</a>';
    echo '</div>';

    // Search/filter
    echo '<form method="get" class="mlb-search">';
    echo '<input type="hidden" name="page" value="mlb-book"><input type="hidden" name="tab" value="'.esc_attr($tab).'">';
    echo '<input type="text" name="s" value="'.esc_attr($srch).'" placeholder="🔍 Name, email, or code…" style="min-width:200px;">';
    echo '<select name="inst"><option value="">All Instruments</option>';
    foreach($all_insts as $i) echo '<option value="'.esc_attr($i).'" '.selected($finst,$i,false).'>'.esc_html($i).'</option>';
    echo '</select>';
    echo '<input type="date" name="dfr" value="'.esc_attr($fdfr).'"> <span style="color:#64748b;font-size:13px;">to</span> <input type="date" name="dto" value="'.esc_attr($fdto).'">';
    echo '<button type="submit" class="mlb-btn mlb-bp">Filter</button>';
    if($srch||$finst||$fdfr||$fdto) echo '<a href="'.esc_url(add_query_arg(['tab'=>$tab,'page'=>'mlb-book'],admin_url('admin.php'))).'" class="mlb-btn mlb-bg">Clear</a>';
    echo '</form>';

    // Bulk action toolbar — hidden until checkboxes selected
    echo '<div id="mlb-bulk-bar" style="display:none;background:#1e293b;border-radius:10px;padding:11px 16px;margin-bottom:12px;display:none;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;">';
    echo '<span id="mlb-bulk-count" style="color:#fff;font-size:13px;font-weight:700;"></span>';
    echo '<div style="display:flex;gap:8px;">';
    echo '<button onclick="mlbBulkDelete()" style="padding:7px 16px;background:#ef4444;color:#fff;border:none;border-radius:8px;font-size:13px;font-weight:700;cursor:pointer;">🗑️ Delete Selected</button>';
    echo '<button onclick="mlbClearSelection()" style="padding:7px 14px;background:rgba(255,255,255,.15);color:#fff;border:none;border-radius:8px;font-size:13px;font-weight:700;cursor:pointer;">✕ Clear</button>';
    echo '</div></div>';

    echo '<div class="mlb-card" style="overflow-x:auto;">';
    if($bk){
        echo '<table class="mlb-tbl"><thead><tr>';
        echo '<th style="width:36px;"><label style="display:flex;align-items:center;justify-content:center;"><input type="checkbox" id="mlb-select-all" onchange="mlbSelectAll(this)" style="width:16px;height:16px;cursor:pointer;accent-color:'.esc_attr($col).';"></label></th>';
        echo '<th>#</th><th>Student</th><th>Instrument</th><th>Date &amp; Time</th><th>Dur</th><th>Price</th><th>Code</th><th>Status</th><th>Payment</th><th>Actions</th></tr></thead><tbody>';
        foreach($bk as $b){
            $du=wp_nonce_url($base.'&tab='.$tab.'&mlb_del='.$b->id,'mlb_del_'.$b->id);
            $cu=wp_nonce_url($base.'&tab='.$tab.'&mlb_can='.$b->id,'mlb_can_'.$b->id);
            $sc=$b->status==='confirmed'?'g':($b->status==='cancelled'?'r':'b');
            $pm=isset($b->payment_method)?$b->payment_method:'none';
            $ps=isset($b->payment_status)?$b->payment_status:'not_required';
            echo '<tr id="bk-row-'.$b->id.'" data-id="'.$b->id.'">';
            echo '<td style="text-align:center;"><input type="checkbox" class="mlb-row-cb" value="'.esc_attr($b->id).'" onchange="mlbUpdateBulk()" style="width:16px;height:16px;cursor:pointer;accent-color:'.esc_attr($col).';"></td>';
            echo '<td style="color:#94a3b8;font-size:11px;">#'.$b->id.'</td>';
            echo '<td><strong>'.esc_html($b->student_name).'</strong><br><small>'.esc_html($b->student_email).'</small>'.(!empty($b->student_phone)?'<br><small style="color:#94a3b8">'.esc_html($b->student_phone).'</small>':'').'</td>';
            echo '<td>'.esc_html($b->instrument).'</td>';
            echo '<td>'.date('d M Y',strtotime($b->lesson_date)).'<br><small style="color:'.$col.';font-weight:700">'.date('g:i A',strtotime($b->lesson_time)).'</small></td>';
            echo '<td>'.(int)$b->duration.'m</td>';
            echo '<td><strong>'.$sym.number_format((float)$b->price,2).'</strong></td>';
            echo '<td><code style="background:#fff7ed;color:'.$col.';padding:4px 9px;border-radius:6px;font-size:12px;font-weight:900;letter-spacing:1px;display:inline-block;">'.esc_html($b->confirmation_code).'</code></td>';
            echo '<td><span class="mlb-b '.$sc.'">'.esc_html($b->status).'</span></td>';
            echo '<td>'.mlb_payment_badge($pm,$ps).'</td>';
            echo '<td style="white-space:nowrap;">';
            echo '<button type="button" class="mlb-btn mlb-bg" style="padding:4px 10px;font-size:11px;margin-right:3px;" onclick="mlbAdminEdit('.esc_js(json_encode([
                'id'=>(int)$b->id,'student_name'=>$b->student_name,'student_email'=>$b->student_email,
                'student_phone'=>$b->student_phone??'','instrument'=>$b->instrument,
                'lesson_date'=>$b->lesson_date,'lesson_time'=>substr($b->lesson_time,0,5),
                'duration'=>(int)$b->duration,'price'=>(float)$b->price,'notes'=>$b->notes??'',
                'status'=>$b->status,'payment_status'=>$ps,
            ])).')">✏️ Edit</button>';
            if($b->status==='confirmed') echo '<a href="'.esc_url($cu).'" class="mlb-btn mlb-bg" style="padding:4px 9px;font-size:11px;margin-right:3px;" onclick="return confirm(\'Cancel this booking?\')">Cancel</a>';
            echo '<a href="'.esc_url($du).'" class="mlb-btn mlb-bd" style="padding:4px 9px;font-size:11px;" onclick="return confirm(\'Delete permanently?\')">Delete</a>';
            echo '</td></tr>';
        }
        echo '</tbody></table>';
    } else echo '<div class="mlb-empty"><div class="ei">📭</div><p>No bookings match your search.</p></div>';
    echo '</div>';

    // Edit modal
    $col_pri = mlb_color('color_primary','#e36868');
    $col_sec = mlb_color('color_secondary','#c2492d');
    ?>
    <div id="mlb-edit-overlay" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:99998;" onclick="mlbCloseEdit()"></div>
    <div id="mlb-edit-modal" style="display:none;position:fixed;bottom:0;left:0;right:0;z-index:99999;background:#fff;border-radius:14px 14px 0 0;box-shadow:0 -8px 40px rgba(0,0,0,.2);max-height:85vh;overflow-y:auto;">
        <div style="padding:16px 20px;border-bottom:2px solid #e2e8f0;display:flex;justify-content:space-between;align-items:center;background:#fff;position:sticky;top:0;z-index:1;">
            <h3 style="margin:0;font-size:15px;font-weight:800;color:#1e293b;">✏️ Edit Booking <span id="mlb-edit-code" style="font-size:12px;font-family:monospace;color:<?php echo esc_attr($col_pri);?>;margin-left:6px;"></span></h3>
            <button onclick="mlbCloseEdit()" style="background:none;border:none;font-size:22px;cursor:pointer;color:#64748b;">✕</button>
        </div>
        <div style="padding:18px 20px;">
            <input type="hidden" id="mlb-edit-id">
            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;margin-bottom:12px;">
                <div><label style="display:block;font-weight:700;font-size:12px;color:#374151;margin-bottom:4px;">Student Name *</label><input type="text" id="mlb-e-name" style="width:100%;padding:8px 10px;border:2px solid #e2e8f0;border-radius:8px;font-size:13px;box-sizing:border-box;"></div>
                <div><label style="display:block;font-weight:700;font-size:12px;color:#374151;margin-bottom:4px;">Email</label><input type="email" id="mlb-e-email" style="width:100%;padding:8px 10px;border:2px solid #e2e8f0;border-radius:8px;font-size:13px;box-sizing:border-box;"></div>
                <div><label style="display:block;font-weight:700;font-size:12px;color:#374151;margin-bottom:4px;">Phone</label><input type="tel" id="mlb-e-phone" style="width:100%;padding:8px 10px;border:2px solid #e2e8f0;border-radius:8px;font-size:13px;box-sizing:border-box;"></div>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr 1fr;gap:12px;margin-bottom:12px;">
                <div><label style="display:block;font-weight:700;font-size:12px;color:#374151;margin-bottom:4px;">Instrument *</label>
                    <select id="mlb-e-instr" style="width:100%;padding:8px 10px;border:2px solid #e2e8f0;border-radius:8px;font-size:13px;background:#fff;">
                        <?php foreach($instruments as $i): ?><option value="<?php echo esc_attr($i);?>"><?php echo esc_html($i);?></option><?php endforeach; ?>
                    </select></div>
                <div><label style="display:block;font-weight:700;font-size:12px;color:#374151;margin-bottom:4px;">Date *</label><input type="date" id="mlb-e-date" style="width:100%;padding:8px 10px;border:2px solid #e2e8f0;border-radius:8px;font-size:13px;box-sizing:border-box;"></div>
                <div><label style="display:block;font-weight:700;font-size:12px;color:#374151;margin-bottom:4px;">Time *</label><input type="time" id="mlb-e-time" style="width:100%;padding:8px 10px;border:2px solid #e2e8f0;border-radius:8px;font-size:13px;box-sizing:border-box;"></div>
                <div><label style="display:block;font-weight:700;font-size:12px;color:#374151;margin-bottom:4px;">Duration (min)</label><input type="number" id="mlb-e-dur" min="15" style="width:100%;padding:8px 10px;border:2px solid #e2e8f0;border-radius:8px;font-size:13px;box-sizing:border-box;"></div>
                <div><label style="display:block;font-weight:700;font-size:12px;color:#374151;margin-bottom:4px;">Price (<?php echo mlb_sym();?>)</label><input type="number" id="mlb-e-price" step="0.01" min="0" style="width:100%;padding:8px 10px;border:2px solid #e2e8f0;border-radius:8px;font-size:13px;box-sizing:border-box;"></div>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;margin-bottom:16px;">
                <div style="grid-column:1/3;"><label style="display:block;font-weight:700;font-size:12px;color:#374151;margin-bottom:4px;">Notes</label><textarea id="mlb-e-notes" rows="2" style="width:100%;padding:8px 10px;border:2px solid #e2e8f0;border-radius:8px;font-size:13px;box-sizing:border-box;font-family:inherit;resize:vertical;"></textarea></div>
                <div>
                    <label style="display:block;font-weight:700;font-size:12px;color:#374151;margin-bottom:4px;">Booking Status</label>
                    <select id="mlb-e-status" style="width:100%;padding:8px 10px;border:2px solid #e2e8f0;border-radius:8px;font-size:13px;background:#fff;margin-bottom:8px;">
                        <option value="confirmed">✅ Confirmed</option>
                        <option value="cancelled">🚫 Cancelled</option>
                    </select>
                    <label style="display:block;font-weight:700;font-size:12px;color:#374151;margin-bottom:4px;">Payment Status</label>
                    <select id="mlb-e-paystatus" style="width:100%;padding:8px 10px;border:2px solid #e2e8f0;border-radius:8px;font-size:13px;background:#fff;">
                        <option value="not_required">— N/A</option>
                        <option value="pending">💳 Awaiting Card</option>
                        <option value="pending_eft">🏦 EFT Pending</option>
                        <option value="paid">✅ Paid</option>
                        <option value="failed">❌ Failed</option>
                        <option value="cancelled">↩ Cancelled</option>
                    </select>
                </div>
            </div>
            <div style="background:#f0fdf4;border:2px solid #bbf7d0;border-radius:9px;padding:11px 14px;margin-bottom:14px;">
                <label style="display:flex;align-items:center;gap:10px;cursor:pointer;font-size:13px;font-weight:700;color:#065f46;">
                    <input type="checkbox" id="mlb-notify-student" checked style="width:17px;height:17px;accent-color:#10b981;cursor:pointer;">
                    📧 Send email notification to student
                    <span style="font-weight:400;color:#6b7280;font-size:12px;">(updates, reschedules, cancellations, payment confirmations)</span>
                </label>
            </div>
            <div style="display:flex;align-items:center;gap:10px;">
                <button onclick="mlbAdminSave()" style="padding:10px 24px;background:linear-gradient(135deg,<?php echo esc_attr($col_pri);?>,<?php echo esc_attr($col_sec);?>);color:#fff;border:none;border-radius:9px;font-size:14px;font-weight:900;cursor:pointer;">💾 Save Changes</button>
                <button onclick="mlbCloseEdit()" style="padding:10px 18px;background:#f1f5f9;border:2px solid #e2e8f0;border-radius:9px;font-size:14px;font-weight:700;cursor:pointer;color:#64748b;">Cancel</button>
                <span id="mlb-edit-msg" style="font-size:13px;font-weight:700;"></span>
            </div>
        </div>
    </div>

    <script>
    var mlbEditNonce = '<?php echo wp_create_nonce('mlb_admin_edit'); ?>';
    var mlbAjaxUrl   = '<?php echo esc_js(admin_url('admin-ajax.php')); ?>';

    /* ── Bulk selection ─────────────────────────────────────────────── */
    window.mlbSelectAll = function(masterCb){
        document.querySelectorAll('.mlb-row-cb').forEach(function(cb){ cb.checked = masterCb.checked; });
        mlbUpdateBulk();
    };
    window.mlbUpdateBulk = function(){
        var checked = document.querySelectorAll('.mlb-row-cb:checked');
        var bar     = document.getElementById('mlb-bulk-bar');
        var count   = document.getElementById('mlb-bulk-count');
        var master  = document.getElementById('mlb-select-all');
        var all     = document.querySelectorAll('.mlb-row-cb');
        if(checked.length > 0){
            bar.style.display = 'flex';
            count.textContent = checked.length + ' booking' + (checked.length > 1 ? 's' : '') + ' selected';
        } else {
            bar.style.display = 'none';
        }
        if(master) master.indeterminate = checked.length > 0 && checked.length < all.length;
        if(master && checked.length === all.length && all.length > 0) { master.checked = true; master.indeterminate = false; }
    };
    window.mlbClearSelection = function(){
        document.querySelectorAll('.mlb-row-cb').forEach(function(cb){ cb.checked = false; });
        var m = document.getElementById('mlb-select-all');
        if(m){ m.checked = false; m.indeterminate = false; }
        mlbUpdateBulk();
    };
    window.mlbBulkDelete = function(){
        var checked = document.querySelectorAll('.mlb-row-cb:checked');
        if(!checked.length) return;
        var names = [];
        checked.forEach(function(cb){
            var row = document.getElementById('bk-row-'+cb.value);
            var nm  = row ? row.querySelector('strong') : null;
            if(nm) names.push(nm.textContent);
        });
        var msg = 'Permanently delete ' + checked.length + ' booking' + (checked.length>1?'s':'') + '?\n\n';
        if(names.length <= 5) msg += names.join('\n') + '\n\n';
        msg += 'This cannot be undone.';
        if(!confirm(msg)) return;
        var ids = Array.from(checked).map(function(cb){ return cb.value; });
        var fd  = new FormData();
        fd.append('action','mlb_admin_bulk_delete');
        fd.append('_ajax_nonce', mlbEditNonce);
        ids.forEach(function(id){ fd.append('ids[]', id); });
        fetch(mlbAjaxUrl,{method:'POST',body:fd}).then(function(r){return r.json();}).then(function(res){
            if(res.success){
                ids.forEach(function(id){
                    var row = document.getElementById('bk-row-'+id);
                    if(row) row.remove();
                });
                mlbClearSelection();
                // Update count in header
                var rows = document.querySelectorAll('#wpbody .mlb-tbl tbody tr');
                document.querySelector('.mlb-hd p').textContent = rows.length + ' result(s)';
            } else {
                alert(res.data || 'Error deleting bookings');
            }
        });
    };

    /* ── Edit modal ─────────────────────────────────────────────────── */
    window.mlbAdminEdit = function(b){
        document.getElementById('mlb-edit-id').value     = b.id;
        document.getElementById('mlb-edit-code').textContent = b.confirmation_code || '';
        document.getElementById('mlb-e-name').value      = b.student_name   || '';
        document.getElementById('mlb-e-email').value     = b.student_email  || '';
        document.getElementById('mlb-e-phone').value     = b.student_phone  || '';
        document.getElementById('mlb-e-instr').value     = b.instrument     || '';
        document.getElementById('mlb-e-date').value      = b.lesson_date    || '';
        document.getElementById('mlb-e-time').value      = b.lesson_time    || '';
        document.getElementById('mlb-e-dur').value       = b.duration       || 60;
        document.getElementById('mlb-e-price').value     = b.price          || 0;
        document.getElementById('mlb-e-notes').value     = b.notes          || '';
        document.getElementById('mlb-e-status').value    = b.status         || 'confirmed';
        document.getElementById('mlb-e-paystatus').value = b.payment_status || 'not_required';
        document.getElementById('mlb-edit-msg').textContent = '';
        document.getElementById('mlb-edit-overlay').style.display = 'block';
        document.getElementById('mlb-edit-modal').style.display   = 'block';
        document.body.style.overflow = 'hidden';
    };
    window.mlbCloseEdit = function(){
        document.getElementById('mlb-edit-overlay').style.display = 'none';
        document.getElementById('mlb-edit-modal').style.display   = 'none';
        document.body.style.overflow = '';
    };
    window.mlbAdminSave = function(){
        var msg = document.getElementById('mlb-edit-msg');
        msg.textContent = 'Saving…'; msg.style.color = '#64748b';
        var fd = new FormData();
        fd.append('action','mlb_admin_save_booking');
        fd.append('_ajax_nonce', mlbEditNonce);
        fd.append('id',             document.getElementById('mlb-edit-id').value);
        fd.append('student_name',   document.getElementById('mlb-e-name').value);
        fd.append('student_email',  document.getElementById('mlb-e-email').value);
        fd.append('student_phone',  document.getElementById('mlb-e-phone').value);
        fd.append('instrument',     document.getElementById('mlb-e-instr').value);
        fd.append('lesson_date',    document.getElementById('mlb-e-date').value);
        fd.append('lesson_time',    document.getElementById('mlb-e-time').value);
        fd.append('duration',       document.getElementById('mlb-e-dur').value);
        fd.append('price',          document.getElementById('mlb-e-price').value);
        fd.append('notes',          document.getElementById('mlb-e-notes').value);
        fd.append('status',         document.getElementById('mlb-e-status').value);
        fd.append('payment_status', document.getElementById('mlb-e-paystatus').value);
        var notifyEl = document.getElementById('mlb-notify-student');
        fd.append('notify_student', (notifyEl && notifyEl.checked) ? '1' : '0');
        fetch(mlbAjaxUrl,{method:'POST',body:fd})
            .then(function(r){return r.json();})
            .then(function(res){
                if(res.success){
                    msg.textContent = '✅ Saved!'; msg.style.color = '#065f46';
                    setTimeout(function(){ mlbCloseEdit(); location.reload(); }, 800);
                } else {
                    msg.textContent = '❌ '+(res.data||'Error'); msg.style.color = '#991b1b';
                }
            });
    };

    </script>
    <?php
    echo '</div>';
}
