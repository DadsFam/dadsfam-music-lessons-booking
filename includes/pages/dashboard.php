<?php
if(!defined('ABSPATH')) exit;
function mlb_pg_dash(){
    global $wpdb; $bt=$wpdb->prefix.'mlb_bookings';
    $tot=(int)$wpdb->get_var("SELECT COUNT(*) FROM $bt WHERE status='confirmed'");
    $up =(int)$wpdb->get_var("SELECT COUNT(*) FROM $bt WHERE status='confirmed' AND lesson_date>=CURDATE()");
    $td =(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $bt WHERE status='confirmed' AND lesson_date=%s",date('Y-m-d')));
    $rev=(float)$wpdb->get_var("SELECT SUM(price) FROM $bt WHERE status='confirmed' AND MONTH(lesson_date)=MONTH(CURDATE()) AND YEAR(lesson_date)=YEAR(CURDATE())");
    $rec=$wpdb->get_results("SELECT * FROM $bt ORDER BY created_at DESC LIMIT 10");
    $sym=mlb_sym(); $col=mlb_color('color_primary','#e36868');

    // License status
    $lic     = dfmlb_get_license();
    $lic_badge = match($lic['status']){
        'active'      => '<span style="background:#d1fae5;color:#065f46;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:800;">⭐ Licensed</span>',
        'invalid'     => '<span style="background:#fee2e2;color:#991b1b;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:800;">❌ License Invalid</span>',
        'check_failed'=> '<span style="background:#fef3c7;color:#92400e;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:800;">⚠️ License Check Failed</span>',
        default       => '<span style="background:#f1f5f9;color:#64748b;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:800;">🔓 Unlicensed</span>',
    };

    echo '<div class="wrap">';
    echo '<div class="mlb-hd" style="justify-content:space-between;">';
    echo '<div style="display:flex;align-items:center;gap:14px;"><div class="mlb-hd-i">🎵</div><div><h1>DadsFam Music Lessons Booking</h1><p>'.date('l, d F Y').'</p></div></div>';
    echo '<div style="text-align:right;">'.$lic_badge.'<br><a href="'.admin_url('admin.php?page=mlb-settings').'" style="color:rgba(255,255,255,.8);font-size:11px;text-decoration:none;" onclick="localStorage.setItem(\'mlb_tab_s\',\'lic\')">Manage license →</a></div>';
    echo '</div>';
    echo '<div class="mlb-stats">';
    foreach([['📋','Total Confirmed',$tot,''],['📅','Upcoming',$up,'g'],['⏰','Today',$td,'c'],['💰','Revenue This Month',$sym.number_format($rev,2),'a']] as $x)
        echo '<div class="mlb-stat '.$x[3].'"><div class="sv">'.esc_html($x[2]).'</div><div class="sl">'.esc_html($x[1]).'</div><div class="si">'.esc_html($x[0]).'</div></div>';
    echo '</div>';
    echo '<div class="mlb-card"><div class="mlb-ct">📋 Recent Bookings</div>';
    if($rec){
        echo '<table class="mlb-tbl"><thead><tr><th>Student</th><th>Instrument</th><th>Date</th><th>Time</th><th>Code</th><th>Status</th></tr></thead><tbody>';
        foreach($rec as $b){
            $sc=$b->status==='confirmed'?'g':($b->status==='cancelled'?'r':'b');
            echo '<tr><td><strong>'.esc_html($b->student_name).'</strong><br><small style="color:#94a3b8">'.esc_html($b->student_email).'</small></td>';
            echo '<td>'.esc_html($b->instrument).'</td><td>'.date('d M Y',strtotime($b->lesson_date)).'</td><td>'.date('g:i A',strtotime($b->lesson_time)).'</td>';
            echo '<td><code style="background:#fff7ed;padding:3px 8px;border-radius:5px;font-size:12px;font-weight:700;color:'.$col.';">'.esc_html($b->confirmation_code).'</code></td>';
            echo '<td><span class="mlb-b '.$sc.'">'.esc_html($b->status).'</span></td></tr>';
        }
        echo '</tbody></table>';
    } else echo '<div class="mlb-empty"><div class="ei">📭</div><p>No bookings yet. Add <code>[music_lesson_booking]</code> to a page!</p></div>';
    echo '</div>';
    echo '<div class="mlb-card"><div class="mlb-ct">🚀 Shortcodes</div>';
    echo '<p style="font-size:13px;color:#64748b;margin-bottom:5px;"><strong>Student Booking Form:</strong></p><div class="mlb-scbox">[music_lesson_booking]</div>';
    echo '<p style="font-size:13px;color:#64748b;margin:11px 0 5px;"><strong>Teacher Dashboard:</strong></p><div class="mlb-scbox">[teacher_lesson_dashboard]</div>';
    echo '</div></div>';
}
