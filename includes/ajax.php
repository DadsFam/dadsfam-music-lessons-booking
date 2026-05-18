<?php
if ( ! defined( 'ABSPATH' ) ) exit;

add_action('wp_ajax_nopriv_mlb_slots','mlb_ajax_slots');
add_action('wp_ajax_mlb_slots',       'mlb_ajax_slots');
function mlb_ajax_slots(){
    check_ajax_referer('mlb_front');
    $date=sanitize_text_field($_POST['date']??'');
    if(!$date||!preg_match('/^\d{4}-\d{2}-\d{2}$/',$date)) wp_send_json_error('Bad date');
    global $wpdb; $bt=$wpdb->prefix.'mlb_bookings'; $ut=$wpdb->prefix.'mlb_unavailable_dates';
    if($wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $ut WHERE unavailable_date=%s",$date)))
        wp_send_json_success(['slots'=>[],'reason'=>'blocked']);
    $s=get_option('mlb_settings',[]); $bh=$s['business_hours']??[];
    $dow=(int)date('w',strtotime($date)); $dh=$bh[$dow]??['off','off'];
    if($dh[0]==='off') wp_send_json_success(['slots'=>[],'reason'=>'closed']);
    $dur=(int)($s['lesson_duration']??60); $buf=(int)($s['buffer_minutes']??30);
    $ex=$wpdb->get_results($wpdb->prepare("SELECT lesson_time,duration FROM $bt WHERE lesson_date=%s AND status='confirmed'",$date));
    $busy=[];
    foreach($ex as $e){$sm=mlb_t2m($e->lesson_time);for($m=$sm;$m<$sm+(int)$e->duration+$buf;$m++) $busy[$m]=true;}
    $open=mlb_t2m($dh[0]); $close=mlb_t2m($dh[1]); $slots=[];
    for($m=$open;$m+$dur<=$close;$m+=30){
        $ok=true; for($x=$m;$x<$m+$dur;$x++){if(isset($busy[$x])){$ok=false;break;}} if($ok) $slots[]=mlb_m2t($m);
    }
    wp_send_json_success(['slots'=>$slots]);
}

add_action('wp_ajax_nopriv_mlb_book','mlb_ajax_book');
add_action('wp_ajax_mlb_book',       'mlb_ajax_book');
function mlb_ajax_book(){
    check_ajax_referer('mlb_front');
    $name =sanitize_text_field($_POST['student_name']??'');
    $email=sanitize_email($_POST['student_email']??'');
    $phone=sanitize_text_field($_POST['student_phone']??'');
    $age  =sanitize_text_field($_POST['student_age']??'');
    $level=sanitize_text_field($_POST['experience_level']??'');
    $instr=sanitize_text_field($_POST['instrument']??'');
    $date =sanitize_text_field($_POST['lesson_date']??'');
    $time =sanitize_text_field($_POST['lesson_time']??'');
    $notes=sanitize_textarea_field($_POST['notes']??'');
    if(!$name||!$email||!$instr||!$date||!$time) wp_send_json_error('Please fill in all required fields.');
    if(!is_email($email)) wp_send_json_error('Please enter a valid email address.');
    global $wpdb; $bt=$wpdb->prefix.'mlb_bookings';
    $s=get_option('mlb_settings',[]); $dur=(int)($s['lesson_duration']??60); $buf=(int)($s['buffer_minutes']??30);
    $ex=$wpdb->get_results($wpdb->prepare("SELECT lesson_time,duration FROM $bt WHERE lesson_date=%s AND status='confirmed'",$date));
    $busy=[];
    foreach($ex as $e){$sm=mlb_t2m($e->lesson_time);for($m=$sm;$m<$sm+(int)$e->duration+$buf;$m++) $busy[$m]=true;}
    $rm=mlb_t2m($time);
    for($m=$rm;$m<$rm+$dur;$m++){if(isset($busy[$m])) wp_send_json_error('This slot is no longer available.');}
    $cf_data=[]; foreach(($s['custom_fields']??[]) as $cf){$k='cf_'.sanitize_key($cf['id']); $cf_data[$cf['label']]=sanitize_text_field($_POST[$k]??'');}
    $code=strtoupper(substr(bin2hex(random_bytes(5)),0,8));
    $price=(float)($s['lesson_price']??0);
    $cols=$wpdb->get_col("SHOW COLUMNS FROM $bt");
    $data=['student_name'=>$name,'student_email'=>$email,'student_phone'=>$phone,'instrument'=>$instr,'lesson_date'=>$date,'lesson_time'=>$time,'duration'=>$dur,'price'=>$price,'notes'=>$notes,'status'=>'confirmed','confirmation_code'=>$code];
    $fmt=['%s','%s','%s','%s','%s','%s','%d','%f','%s','%s','%s'];
    if(in_array('student_age',$cols)){$data['student_age']=$age;$fmt[]='%s';}
    if(in_array('experience_level',$cols)){$data['experience_level']=$level;$fmt[]='%s';}
    if(in_array('custom_fields',$cols)&&!empty($cf_data)){$data['custom_fields']=json_encode($cf_data);$fmt[]='%s';}
    $ok=$wpdb->insert($bt,$data,$fmt);
    if($ok===false) wp_send_json_error('Database error: '.$wpdb->last_error);
    mlb_send_email($email,$name,$instr,$date,$time,$dur,$price,$code,$cf_data);
    if($s['notify_admin']??1) mlb_admin_email($name,$email,$instr,$date,$time,$code);
    wp_send_json_success(['message'=>$s['confirm_message']??'Booking confirmed! Check your email.','code'=>$code]);
}

// ── Teacher-side booking management (all require teacher login) ────────────

add_action('wp_ajax_mlb_t_bookings','mlb_ajax_t_bookings');
function mlb_ajax_t_bookings(){
    if(!mlb_teacher_ok()) wp_send_json_error('Not authorised');
    global $wpdb; $bt=$wpdb->prefix.'mlb_bookings';
    $tab  =sanitize_text_field($_POST['tab']  ??'upcoming');
    $srch =sanitize_text_field($_POST['s']    ??'');
    $finst=sanitize_text_field($_POST['inst'] ??'');
    $dfr  =sanitize_text_field($_POST['dfr']  ??'');
    $dto  =sanitize_text_field($_POST['dto']  ??'');
    $wheres=[];
    if($tab==='upcoming')  $wheres[]="status='confirmed' AND lesson_date>=CURDATE()";
    elseif($tab==='today') $wheres[]="status='confirmed' AND lesson_date='".date('Y-m-d')."'";
    elseif($tab==='cancelled') $wheres[]="status='cancelled'";
    if($srch){$like='%'.$wpdb->esc_like($srch).'%';$wheres[]=$wpdb->prepare("(student_name LIKE %s OR student_email LIKE %s OR confirmation_code LIKE %s OR student_phone LIKE %s)",$like,$like,$like,$like);}
    if($finst) $wheres[]=$wpdb->prepare("instrument=%s",$finst);
    if($dfr)   $wheres[]=$wpdb->prepare("lesson_date>=%s",$dfr);
    if($dto)   $wheres[]=$wpdb->prepare("lesson_date<=%s",$dto);
    $where=$wheres?'WHERE '.implode(' AND ',$wheres):'';
    $rows=$wpdb->get_results("SELECT * FROM $bt $where ORDER BY lesson_date ASC,lesson_time ASC");
    wp_send_json_success($rows);
}

add_action('wp_ajax_mlb_t_cancel','mlb_ajax_t_cancel');
function mlb_ajax_t_cancel(){
    check_ajax_referer('mlb_teacher_action');
    if(!mlb_teacher_ok()) wp_send_json_error('Not authorised');
    global $wpdb; $bt=$wpdb->prefix.'mlb_bookings';
    $id=intval($_POST['id']??0);
    if(!$id) wp_send_json_error('Missing ID');

    // Fetch booking BEFORE updating so we have all details for the email
    $booking=$wpdb->get_row($wpdb->prepare("SELECT * FROM $bt WHERE id=%d",$id));
    if(!$booking) wp_send_json_error('Booking not found');

    $wpdb->update($bt,['status'=>'cancelled'],['id'=>$id],['%s'],['%d']);

    // Send cancellation email to student
    if(!empty($booking->student_email)){
        mlb_send_cancellation_email(
            $booking->student_email,
            $booking->student_name,
            $booking->instrument,
            $booking->lesson_date,
            $booking->lesson_time,
            $booking->confirmation_code
        );
    }

    wp_send_json_success('Booking cancelled and student notified by email.');
}

add_action('wp_ajax_mlb_t_delete','mlb_ajax_t_delete');
function mlb_ajax_t_delete(){
    check_ajax_referer('mlb_teacher_action');
    if(!mlb_teacher_ok()) wp_send_json_error('Not authorised');
    global $wpdb; $bt=$wpdb->prefix.'mlb_bookings';
    $id=intval($_POST['id']??0);
    if(!$id) wp_send_json_error('Missing ID');
    $wpdb->delete($bt,['id'=>$id],['%d']);
    wp_send_json_success('Booking deleted');
}

add_action('wp_ajax_mlb_t_save','mlb_ajax_t_save');
function mlb_ajax_t_save(){
    check_ajax_referer('mlb_teacher_action');
    if(!mlb_teacher_ok()) wp_send_json_error('Not authorised');
    global $wpdb; $bt=$wpdb->prefix.'mlb_bookings';
    $id=intval($_POST['id']??0);
    if(!$id) wp_send_json_error('Missing ID');
    $data=[
        'student_name' =>sanitize_text_field($_POST['student_name'] ??''),
        'student_email'=>sanitize_email($_POST['student_email']      ??''),
        'student_phone'=>sanitize_text_field($_POST['student_phone'] ??''),
        'instrument'   =>sanitize_text_field($_POST['instrument']    ??''),
        'lesson_date'  =>sanitize_text_field($_POST['lesson_date']   ??''),
        'lesson_time'  =>sanitize_text_field($_POST['lesson_time']   ??''),
        'duration'     =>max(15,intval($_POST['duration']            ??60)),
        'price'        =>max(0,floatval($_POST['price']              ??0)),
        'notes'        =>sanitize_textarea_field($_POST['notes']     ??''),
        'status'       =>sanitize_text_field($_POST['status']        ??'confirmed'),
    ];
    if(!$data['student_name']||!$data['lesson_date']||!$data['lesson_time']) wp_send_json_error('Required fields missing');
    $wpdb->update($bt,$data,['id'=>$id],['%s','%s','%s','%s','%s','%s','%d','%f','%s','%s'],['%d']);
    wp_send_json_success('Booking updated');
}

// ── Teacher portal settings management ───────────────────────────────────────

add_action('wp_ajax_mlb_t_save_schedule','mlb_ajax_t_save_schedule');
function mlb_ajax_t_save_schedule(){
    check_ajax_referer('mlb_teacher_action');
    if(!mlb_teacher_ok()) wp_send_json_error('Not authorised');
    $s=get_option('mlb_settings',[]);
    $s['lesson_duration'] = max(15, intval($_POST['lesson_duration'] ?? 60));
    $s['buffer_minutes']  = max(0,  intval($_POST['buffer_minutes']  ?? 30));
    $s['max_advance']     = max(1,  intval($_POST['max_advance']     ?? 90));
    for($d=0;$d<7;$d++){
        $open  = sanitize_text_field($_POST["bh_s{$d}"] ?? 'off');
        $close = sanitize_text_field($_POST["bh_e{$d}"] ?? 'off');
        // if teacher ticked "closed" for this day, store 'off'
        if(isset($_POST["bh_closed{$d}"]) || $open==='off') { $open='off'; $close='off'; }
        $s['business_hours'][$d] = [$open,$close];
    }
    update_option('mlb_settings',$s);
    wp_send_json_success('✅ Schedule saved!');
}

add_action('wp_ajax_mlb_t_save_rates','mlb_ajax_t_save_rates');
function mlb_ajax_t_save_rates(){
    check_ajax_referer('mlb_teacher_action');
    if(!mlb_teacher_ok()) wp_send_json_error('Not authorised');
    $s=get_option('mlb_settings',[]);
    $s['lesson_price']    = max(0, floatval(sanitize_text_field($_POST['lesson_price']    ?? '0')));
    $s['currency_symbol'] = sanitize_text_field($_POST['currency_symbol'] ?? 'R');
    $s['instruments']     = sanitize_text_field($_POST['instruments']     ?? '');
    update_option('mlb_settings',$s);
    wp_send_json_success('✅ Rates saved!');
}

add_action('wp_ajax_mlb_t_get_blocked','mlb_ajax_t_get_blocked');
function mlb_ajax_t_get_blocked(){
    if(!mlb_teacher_ok()) wp_send_json_error('Not authorised');
    global $wpdb;
    $rows=$wpdb->get_results("SELECT * FROM {$wpdb->prefix}mlb_unavailable_dates ORDER BY unavailable_date ASC");
    wp_send_json_success($rows);
}

add_action('wp_ajax_mlb_t_add_blocked','mlb_ajax_t_add_blocked');
function mlb_ajax_t_add_blocked(){
    check_ajax_referer('mlb_teacher_action');
    if(!mlb_teacher_ok()) wp_send_json_error('Not authorised');
    global $wpdb; $ut=$wpdb->prefix.'mlb_unavailable_dates';
    $date  =sanitize_text_field($_POST['date']   ?? '');
    $reason=sanitize_text_field($_POST['reason'] ?? '');
    if(!$date||!preg_match('/^\d{4}-\d{2}-\d{2}$/',$date)) wp_send_json_error('Invalid date');
    $wpdb->replace($ut,['unavailable_date'=>$date,'reason'=>$reason],['%s','%s']);
    wp_send_json_success('Date blocked');
}

add_action('wp_ajax_mlb_t_remove_blocked','mlb_ajax_t_remove_blocked');
function mlb_ajax_t_remove_blocked(){
    check_ajax_referer('mlb_teacher_action');
    if(!mlb_teacher_ok()) wp_send_json_error('Not authorised');
    global $wpdb;
    $id=intval($_POST['id']??0);
    if(!$id) wp_send_json_error('Bad ID');
    $wpdb->delete($wpdb->prefix.'mlb_unavailable_dates',['id'=>$id],['%d']);
    wp_send_json_success('Removed');
}
