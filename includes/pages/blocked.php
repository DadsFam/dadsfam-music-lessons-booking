<?php
if(!defined('ABSPATH')) exit;
function mlb_pg_blocked(){
    global $wpdb; $ut=$wpdb->prefix.'mlb_unavailable_dates';
    if(isset($_POST['mlb_add_blocked'])&&wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_mlb_s']??'')),'mlb_blocked')){
        $d=sanitize_text_field($_POST['block_date']??'');
        if($d){$wpdb->replace($ut,['unavailable_date'=>$d,'reason'=>sanitize_text_field($_POST['block_reason']??'')],['%s','%s']);echo '<div class="mlb-ok">✅ Date blocked.</div>';}
    }
    if(isset($_GET['mlb_unb'])&&wp_verify_nonce($_GET['_wpnonce']??'','mlb_unb_'.intval($_GET['mlb_unb']))){
        $wpdb->delete($ut,['id'=>intval($_GET['mlb_unb'])],['%d']);echo '<div class="mlb-ok">✅ Date removed.</div>';
    }
    $bl=$wpdb->get_results("SELECT * FROM $ut ORDER BY unavailable_date ASC");
    echo '<div class="wrap"><div class="mlb-hd"><div class="mlb-hd-i">🚫</div><div><h1>Blocked Dates</h1><p>Students cannot book on these dates</p></div></div>';
    echo '<div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">';
    echo '<div class="mlb-card"><div class="mlb-ct">➕ Block a Date</div><form method="post">';
    wp_nonce_field('mlb_blocked','_mlb_s');
    echo '<div style="margin-bottom:11px;"><label style="display:block;font-weight:700;font-size:13px;margin-bottom:5px;">Date</label><input type="date" name="block_date" required style="padding:7px 10px;border:2px solid #e2e8f0;border-radius:8px;font-size:13px;width:100%;max-width:270px;"></div>';
    echo '<div style="margin-bottom:12px;"><label style="display:block;font-weight:700;font-size:13px;margin-bottom:5px;">Reason (optional)</label><input type="text" name="block_reason" placeholder="e.g. Public Holiday" style="padding:7px 10px;border:2px solid #e2e8f0;border-radius:8px;font-size:13px;width:100%;max-width:340px;"></div>';
    echo '<button type="submit" name="mlb_add_blocked" class="mlb-btn mlb-bp">🚫 Block Date</button></form></div>';
    echo '<div class="mlb-card"><div class="mlb-ct">📅 Currently Blocked</div>';
    if($bl){
        echo '<table class="mlb-tbl"><thead><tr><th>Date</th><th>Reason</th><th></th></tr></thead><tbody>';
        foreach($bl as $b){
            $u=wp_nonce_url(admin_url('admin.php?page=mlb-blocked&mlb_unb='.$b->id),'mlb_unb_'.$b->id);
            echo '<tr><td>'.date('d M Y',strtotime($b->unavailable_date)).'</td><td>'.esc_html($b->reason?:'—').'</td><td><a href="'.esc_url($u).'" class="mlb-btn mlb-bg" style="padding:4px 8px;font-size:11px;">Remove</a></td></tr>';
        }
        echo '</tbody></table>';
    } else echo '<div class="mlb-empty"><div class="ei">✅</div><p>No dates blocked.</p></div>';
    echo '</div></div></div>';
}
