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
    $tab=sanitize_text_field($_GET['tab']??'all');
    $srch=sanitize_text_field($_GET['s']??'');
    $finst=sanitize_text_field($_GET['inst']??'');
    $fdfr=sanitize_text_field($_GET['dfr']??'');
    $fdto=sanitize_text_field($_GET['dto']??'');
    $wheres=[];
    if($tab==='upcoming') $wheres[]="status='confirmed' AND lesson_date>=CURDATE()";
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
    echo '<div class="wrap">';
    echo '<div class="mlb-hd"><div class="mlb-hd-i">📋</div><div><h1>All Bookings</h1><p>'.count($bk).' result(s)</p></div></div>';
    echo '<div class="mlb-tabs">';
    foreach(['all'=>'All','upcoming'=>'Upcoming','today'=>'Today','cancelled'=>'Cancelled'] as $k=>$v)
        echo '<a href="'.esc_url(add_query_arg(['tab'=>$k],$base)).'" class="mlb-tab '.($tab===$k?'act':'').'">'.$v.'</a>';
    echo '</div>';
    echo '<form method="get" class="mlb-search">';
    echo '<input type="hidden" name="page" value="mlb-book"><input type="hidden" name="tab" value="'.esc_attr($tab).'">';
    echo '<input type="text" name="s" value="'.esc_attr($srch).'" placeholder="🔍 Name, email, or code…" style="min-width:220px;">';
    echo '<select name="inst"><option value="">All Instruments</option>';
    foreach($all_insts as $i) echo '<option value="'.esc_attr($i).'" '.selected($finst,$i,false).'>'.esc_html($i).'</option>';
    echo '</select>';
    echo '<input type="date" name="dfr" value="'.esc_attr($fdfr).'" title="From">';
    echo '<span style="color:#64748b;font-size:13px;align-self:center;">to</span>';
    echo '<input type="date" name="dto" value="'.esc_attr($fdto).'" title="To">';
    echo '<button type="submit" class="mlb-btn mlb-bp">Filter</button>';
    if($srch||$finst||$fdfr||$fdto) echo '<a href="'.esc_url(add_query_arg(['tab'=>$tab,'page'=>'mlb-book'],admin_url('admin.php'))).'" class="mlb-btn mlb-bg">Clear</a>';
    echo '</form>';
    echo '<div class="mlb-card">';
    if($bk){
        echo '<table class="mlb-tbl"><thead><tr><th>#</th><th>Student</th><th>Instrument</th><th>Date &amp; Time</th><th>Dur</th><th>Price</th><th>Code</th><th>Status</th><th>Actions</th></tr></thead><tbody>';
        foreach($bk as $b){
            $du=wp_nonce_url($base.'&tab='.$tab.'&mlb_del='.$b->id,'mlb_del_'.$b->id);
            $cu=wp_nonce_url($base.'&tab='.$tab.'&mlb_can='.$b->id,'mlb_can_'.$b->id);
            $sc=$b->status==='confirmed'?'g':($b->status==='cancelled'?'r':'b');
            echo '<tr><td style="color:#94a3b8;font-size:11px;">#'.$b->id.'</td>';
            echo '<td><strong>'.esc_html($b->student_name).'</strong><br><small>'.esc_html($b->student_email).'</small>'.($b->student_phone?'<br><small style="color:#94a3b8">'.esc_html($b->student_phone).'</small>':'').'</td>';
            echo '<td>'.esc_html($b->instrument).'</td>';
            echo '<td>'.date('d M Y',strtotime($b->lesson_date)).'<br><small style="color:'.$col.';font-weight:700">'.date('g:i A',strtotime($b->lesson_time)).'</small></td>';
            echo '<td>'.(int)$b->duration.'m</td>';
            echo '<td><strong>'.$sym.number_format((float)$b->price,2).'</strong></td>';
            echo '<td><code style="background:#fff7ed;color:'.$col.';padding:4px 9px;border-radius:6px;font-size:12px;font-weight:900;letter-spacing:1px;display:inline-block;">'.esc_html($b->confirmation_code).'</code></td>';
            echo '<td><span class="mlb-b '.$sc.'">'.esc_html($b->status).'</span></td>';
            echo '<td style="white-space:nowrap">';
            if($b->status==='confirmed') echo '<a href="'.esc_url($cu).'" class="mlb-btn mlb-bg" style="padding:4px 9px;font-size:11px;margin-right:3px;">Cancel</a>';
            echo '<a href="'.esc_url($du).'" class="mlb-btn mlb-bd" style="padding:4px 9px;font-size:11px;" onclick="return confirm(\'Delete permanently?\')">Delete</a>';
            echo '</td></tr>';
        }
        echo '</tbody></table>';
    } else echo '<div class="mlb-empty"><div class="ei">📭</div><p>No bookings match your search.</p></div>';
    echo '</div></div>';
}
