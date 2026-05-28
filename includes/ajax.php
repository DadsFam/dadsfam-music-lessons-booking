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
    $name   = sanitize_text_field($_POST['student_name']    ?? '');
    $email  = sanitize_email($_POST['student_email']        ?? '');
    $phone  = sanitize_text_field($_POST['student_phone']   ?? '');
    $age    = sanitize_text_field($_POST['student_age']     ?? '');
    $level  = sanitize_text_field($_POST['experience_level']?? '');
    $instr  = sanitize_text_field($_POST['instrument']      ?? '');
    $date   = sanitize_text_field($_POST['lesson_date']     ?? '');
    $time   = sanitize_text_field($_POST['lesson_time']     ?? '');
    $notes  = sanitize_textarea_field($_POST['notes']       ?? '');
    $ret_url= esc_url_raw($_POST['return_url']              ?? home_url());

    // Payment method — read from POST, then apply server-side fallback
    $pay_m  = sanitize_text_field($_POST['payment_method']  ?? 'none');
    if($pay_m === 'none'){
        if(mlb_eft_enabled() && !mlb_yoco_enabled()) $pay_m = 'eft';
        elseif(mlb_yoco_enabled() && !mlb_eft_enabled()) $pay_m = 'yoco';
    }

    if(!$name||!$email||!$instr||!$date||!$time) wp_send_json_error('Please fill in all required fields.');
    if(!is_email($email)) wp_send_json_error('Please enter a valid email address.');

    global $wpdb; $bt=$wpdb->prefix.'mlb_bookings';
    $s  = get_option('mlb_settings',[]);
    $dur= (int)($s['lesson_duration'] ?? 60);
    $buf= (int)($s['buffer_minutes']  ?? 30);

    // Slot availability check
    $ex=$wpdb->get_results($wpdb->prepare(
        "SELECT lesson_time,duration FROM $bt WHERE lesson_date=%s AND status='confirmed'",$date));
    $busy=[];
    foreach($ex as $e){$sm=mlb_t2m($e->lesson_time);for($m=$sm;$m<$sm+(int)$e->duration+$buf;$m++) $busy[$m]=true;}
    $rm=mlb_t2m($time);
    for($m=$rm;$m<$rm+$dur;$m++){if(isset($busy[$m])) wp_send_json_error('This slot is no longer available.');}

    // Custom fields
    $cf_data=[];
    foreach(($s['custom_fields']??[]) as $cf){
        $k='cf_'.sanitize_key($cf['id']);
        $cf_data[$cf['label']]=sanitize_text_field($_POST[$k]??'');
    }

    $code  = strtoupper(substr(bin2hex(random_bytes(5)),0,8));
    $price = (float)($s['lesson_price'] ?? 0);
    $cols  = $wpdb->get_col("SHOW COLUMNS FROM $bt");

    // Payment status
    $pay_status = 'not_required';
    if($pay_m==='yoco') $pay_status = 'pending';
    elseif($pay_m==='eft') $pay_status = 'pending_eft';

    $data = ['student_name'=>$name,'student_email'=>$email,'student_phone'=>$phone,
             'instrument'=>$instr,'lesson_date'=>$date,'lesson_time'=>$time,
             'duration'=>$dur,'price'=>$price,'notes'=>$notes,
             'status'=>'confirmed','confirmation_code'=>$code];
    $fmt  = ['%s','%s','%s','%s','%s','%s','%d','%f','%s','%s','%s'];

    if(in_array('student_age',$cols))      { $data['student_age']=$age;                  $fmt[]='%s'; }
    if(in_array('experience_level',$cols)) { $data['experience_level']=$level;            $fmt[]='%s'; }
    if(in_array('custom_fields',$cols)&&!empty($cf_data)){ $data['custom_fields']=json_encode($cf_data); $fmt[]='%s'; }
    if(in_array('payment_method',$cols))   { $data['payment_method']=$pay_m;              $fmt[]='%s'; }
    if(in_array('payment_status',$cols))   { $data['payment_status']=$pay_status;         $fmt[]='%s'; }

    $ok=$wpdb->insert($bt,$data,$fmt);
    if($ok===false) wp_send_json_error('Database error: '.$wpdb->last_error);

    // ── Yoco: create checkout & redirect ─────────────────────────────────────
    if($pay_m === 'yoco'){
        $yoco = mlb_yoco_create_checkout(
            array_merge($data,['confirmation_code'=>$code,'price'=>$price]),
            $ret_url
        );
        if(!$yoco){
            $wpdb->delete($bt,['confirmation_code'=>$code],['%s']);
            wp_send_json_error('Could not connect to Yoco. Please try again or choose EFT.');
        }
        if(in_array('payment_reference',$cols)){
            $wpdb->update($bt,['payment_reference'=>$yoco['id']??''],['confirmation_code'=>$code],['%s'],['%s']);
        }
        wp_send_json_success(['action'=>'yoco','redirect_url'=>$yoco['redirectUrl'],'code'=>$code]);
    }

    // ── EFT: send bank details email ──────────────────────────────────────────
    if($pay_m === 'eft'){
        mlb_send_eft_email($email,$name,$instr,$date,$time,$price,$code);
        if($s['notify_admin']??1) mlb_admin_email($name,$email,$instr,$date,$time,$code);
        $ref = str_replace('{code}',$code,mlb_setting('pay_eft_ref','MLB-{code}'));
        wp_send_json_success([
            'action'       => 'eft',
            'code'         => $code,
            'ref'          => $ref,
            'holder'       => mlb_setting('pay_eft_holder',''),
            'bank'         => mlb_setting('pay_eft_bank',''),
            'account'      => mlb_setting('pay_eft_account',''),
            'branch'       => mlb_setting('pay_eft_branch',''),
            'amount'       => mlb_sym().number_format($price,2),
            'instructions' => mlb_setting('pay_eft_instructions',''),
        ]);
    }

    // ── No payment: standard confirmation ────────────────────────────────────
    mlb_send_email($email,$name,$instr,$date,$time,$dur,$price,$code,$cf_data);
    if($s['notify_admin']??1) mlb_admin_email($name,$email,$instr,$date,$time,$code);
    wp_send_json_success(['action'=>'confirmed','message'=>$s['confirm_message']??'Booking confirmed! Check your email.','code'=>$code]);
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
            $booking->confirmation_code,
            $booking->payment_status ?? 'not_required'
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
        'status'        =>sanitize_text_field($_POST['status']         ??'confirmed'),
        'payment_status'=>sanitize_text_field($_POST['payment_status'] ??'not_required'),
    ];
    if(!$data['student_name']||!$data['lesson_date']||!$data['lesson_time']) wp_send_json_error('Required fields missing');

    // Fetch existing record before updating (needed to detect status changes for email)
    $old = $wpdb->get_row($wpdb->prepare("SELECT * FROM $bt WHERE id=%d",$id));
    $wpdb->update($bt,$data,['id'=>$id],['%s','%s','%s','%s','%s','%s','%d','%f','%s','%s','%s'],['%d']);

    // Send notification email if teacher requested it
    if(!empty($_POST['notify_student']) && $old){
        $em  = $old->student_email;
        $nm  = $data['student_name'];
        $ins = $data['instrument'];
        $dt  = $data['lesson_date'];
        $tm  = $data['lesson_time'];
        $dur = (int)$data['duration'];
        $pr  = (float)$data['price'];
        $cd  = $old->confirmation_code;
        $ns  = $data['status'];
        $np  = $data['payment_status'];

        $cfs = !empty($old->custom_fields)?(array)json_decode($old->custom_fields,true):[];
        if($ns === 'cancelled'){
            // Cancelled → cancellation email
            mlb_send_cancellation_email($em,$nm,$ins,$dt,$tm,$cd,$np);
        } elseif($ns === 'confirmed' && ($np === 'paid' || $np === 'not_required')){
            // Confirmed AND payment complete/not needed → "Lesson Booked!" ✅
            mlb_send_email($em,$nm,$ins,$dt,$tm,$dur,$pr,$cd,$cfs,$np);
        } else {
            // Confirmed but payment still pending/failed, or other detail change → "Lesson Updated"
            mlb_send_update_email($em,$nm,$ins,$dt,$tm,$dur,$pr,$cd,$ns,$np);
        }
    }

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
    $s['contact_email']   = sanitize_email($_POST['contact_email']        ?? '');
    $s['contact_phone']   = sanitize_text_field($_POST['contact_phone']   ?? '');
    $s['contact_url']     = esc_url_raw($_POST['contact_url']             ?? '');
    update_option('mlb_settings',$s);
    wp_send_json_success('✅ Rates & contact info saved!');
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

// ── Week calendar: return all slot data for 7 days at once ───────────────────
add_action('wp_ajax_nopriv_mlb_week_slots','mlb_ajax_week_slots');
add_action('wp_ajax_mlb_week_slots',       'mlb_ajax_week_slots');
function mlb_ajax_week_slots(){
    check_ajax_referer('mlb_front');
    $week = sanitize_text_field($_POST['week'] ?? date('Y-m-d'));
    if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$week)) wp_send_json_error('Bad date');

    global $wpdb;
    $bt=$wpdb->prefix.'mlb_bookings';
    $ut=$wpdb->prefix.'mlb_unavailable_dates';
    $s  = get_option('mlb_settings',[]);
    $bh = $s['business_hours'] ?? [];
    $dur= (int)($s['lesson_duration'] ?? 60);
    $buf= (int)($s['buffer_minutes']  ?? 30);
    $max= (int)($s['max_advance']     ?? 90);
    $today   = date('Y-m-d');
    $max_date= date('Y-m-d', strtotime("+{$max} days"));

    $result = [];
    for($i=0;$i<7;$i++){
        $date = date('Y-m-d', strtotime("{$week} +{$i} days"));
        $dow  = (int)date('w', strtotime($date));
        $dh   = $bh[$dow] ?? ['off','off'];

        $day = [
            'date'      => $date,
            'dow'       => $dow,
            'past'      => $date < $today,
            'too_far'   => $date > $max_date,
            'closed'    => false,
            'blocked'   => false,
            'available' => [],
            'booked'    => [],
        ];

        // Day off
        if($dh[0]==='off'){ $day['closed']=true; $result[$date]=$day; continue; }

        // Beyond booking window
        if($date < $today || $date > $max_date){ $result[$date]=$day; continue; }

        // Blocked date
        if($wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $ut WHERE unavailable_date=%s",$date))){
            $day['closed']=true; $day['blocked']=true; $result[$date]=$day; continue;
        }

        // Existing bookings → build busy map
        $ex=$wpdb->get_results($wpdb->prepare(
            "SELECT lesson_time,duration FROM $bt WHERE lesson_date=%s AND status='confirmed'",$date));
        $busy=[];
        foreach($ex as $e){ $sm=mlb_t2m($e->lesson_time); for($m=$sm;$m<$sm+(int)$e->duration+$buf;$m++) $busy[$m]=true; }

        // Generate slots
        $open =mlb_t2m($dh[0]);
        $close=mlb_t2m($dh[1]);
        for($m=$open;$m+$dur<=$close;$m+=30){
            $ok=true;
            for($x=$m;$x<$m+$dur;$x++){if(isset($busy[$x])){$ok=false;break;}}
            if($ok) $day['available'][]=mlb_m2t($m);
            else    $day['booked'][]   =mlb_m2t($m);
        }
        $result[$date]=$day;
    }
    wp_send_json_success($result);
}

// ── Admin: save/edit booking (uses manage_options, not teacher login) ─────────
add_action('wp_ajax_mlb_admin_save_booking', function(){
    check_ajax_referer('mlb_admin_edit');
    if(!current_user_can('manage_options')) wp_send_json_error('Permission denied');
    global $wpdb; $bt=$wpdb->prefix.'mlb_bookings';
    $id=intval($_POST['id']??0);
    if(!$id) wp_send_json_error('Missing ID');
    $data=[
        'student_name' => sanitize_text_field($_POST['student_name'] ?? ''),
        'student_email'=> sanitize_email($_POST['student_email']     ?? ''),
        'student_phone'=> sanitize_text_field($_POST['student_phone']?? ''),
        'instrument'   => sanitize_text_field($_POST['instrument']   ?? ''),
        'lesson_date'  => sanitize_text_field($_POST['lesson_date']  ?? ''),
        'lesson_time'  => sanitize_text_field($_POST['lesson_time']  ?? ''),
        'duration'     => max(15,intval($_POST['duration']           ?? 60)),
        'price'        => max(0,floatval($_POST['price']             ?? 0)),
        'notes'        => sanitize_textarea_field($_POST['notes']    ?? ''),
        'status'       => sanitize_text_field($_POST['status']       ?? 'confirmed'),
        'payment_status'=> sanitize_text_field($_POST['payment_status']??'not_required'),
    ];
    if(!$data['student_name']||!$data['lesson_date']||!$data['lesson_time']) wp_send_json_error('Required fields missing');

    $old = $wpdb->get_row($wpdb->prepare("SELECT * FROM $bt WHERE id=%d",$id));
    $wpdb->update($bt,$data,['id'=>$id],['%s','%s','%s','%s','%s','%s','%d','%f','%s','%s','%s'],['%d']);

    if(!empty($_POST['notify_student']) && $old){
        $em  = $old->student_email;
        $nm  = $data['student_name'];
        $ins = $data['instrument'];
        $dt  = $data['lesson_date'];
        $tm  = $data['lesson_time'];
        $dur = (int)$data['duration'];
        $pr  = (float)$data['price'];
        $cd  = $old->confirmation_code;
        $ns  = $data['status'];
        $np  = $data['payment_status'];

        $cfs = !empty($old->custom_fields)?(array)json_decode($old->custom_fields,true):[];
        if($ns === 'cancelled'){
            mlb_send_cancellation_email($em,$nm,$ins,$dt,$tm,$cd,$np);
        } elseif($ns === 'confirmed' && ($np === 'paid' || $np === 'not_required')){
            // Confirmed + payment settled → "Lesson Booked!" ✅
            mlb_send_email($em,$nm,$ins,$dt,$tm,$dur,$pr,$cd,$cfs,$np);
        } else {
            // Confirmed but payment pending/failed, or other changes → "Lesson Updated"
            mlb_send_update_email($em,$nm,$ins,$dt,$tm,$dur,$pr,$cd,$ns,$np);
        }
    }

    wp_send_json_success('Booking updated');
});

// ── Admin: bulk delete bookings ───────────────────────────────────────────────
add_action('wp_ajax_mlb_admin_bulk_delete', function(){
    check_ajax_referer('mlb_admin_edit');
    if(!current_user_can('manage_options')) wp_send_json_error('Permission denied');
    global $wpdb; $bt=$wpdb->prefix.'mlb_bookings';
    $raw = $_POST['ids'] ?? [];
    $ids = array_filter(array_map('intval', is_array($raw) ? $raw : explode(',', $raw)));
    if(empty($ids)) wp_send_json_error('No bookings selected');
    $ph  = implode(',', array_fill(0, count($ids), '%d'));
    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
    $wpdb->query($wpdb->prepare("DELETE FROM $bt WHERE id IN ($ph)", $ids));
    wp_send_json_success(count($ids).' booking(s) deleted');
});

// ── Teacher portal: bulk delete bookings ──────────────────────────────────────
add_action('wp_ajax_mlb_t_bulk_delete', function(){
    check_ajax_referer('mlb_teacher_action');
    if(!mlb_teacher_ok()) wp_send_json_error('Not authorised');
    global $wpdb; $bt=$wpdb->prefix.'mlb_bookings';
    $raw = $_POST['ids'] ?? [];
    $ids = array_filter(array_map('intval', is_array($raw) ? $raw : explode(',', $raw)));
    if(empty($ids)) wp_send_json_error('No bookings selected');
    $ph  = implode(',', array_fill(0, count($ids), '%d'));
    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
    $wpdb->query($wpdb->prepare("DELETE FROM $bt WHERE id IN ($ph)", $ids));
    wp_send_json_success(count($ids).' booking(s) deleted');
});
