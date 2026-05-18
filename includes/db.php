<?php
if ( ! defined( 'ABSPATH' ) ) exit;

register_activation_hook( MLB_DIR.'music-lesson-booking.php', 'mlb_schema' );
add_action('plugins_loaded', function(){
    if(get_option('mlb_db_ver')!==MLB_VERSION){ mlb_schema(); update_option('mlb_db_ver',MLB_VERSION); }
});

function mlb_schema(){
    global $wpdb; $cc=$wpdb->get_charset_collate();
    require_once ABSPATH.'wp-admin/includes/upgrade.php';
    dbDelta("CREATE TABLE {$wpdb->prefix}mlb_bookings (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        student_name VARCHAR(255) NOT NULL DEFAULT '',
        student_email VARCHAR(255) NOT NULL DEFAULT '',
        student_phone VARCHAR(30) DEFAULT '',
        student_age VARCHAR(20) DEFAULT '',
        experience_level VARCHAR(50) DEFAULT '',
        custom_fields LONGTEXT DEFAULT NULL,
        instrument VARCHAR(100) NOT NULL DEFAULT '',
        lesson_date DATE NOT NULL,
        lesson_time TIME NOT NULL,
        duration INT DEFAULT 60,
        price DECIMAL(10,2) DEFAULT 0.00,
        notes LONGTEXT,
        status VARCHAR(30) DEFAULT 'confirmed',
        confirmation_code VARCHAR(20) DEFAULT '',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY date_time (lesson_date,lesson_time),
        KEY email (student_email),
        KEY conf_code (confirmation_code)
    ) $cc;");
    dbDelta("CREATE TABLE {$wpdb->prefix}mlb_unavailable_dates (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        unavailable_date DATE NOT NULL,
        reason VARCHAR(255) DEFAULT '',
        PRIMARY KEY (id),
        UNIQUE KEY udate (unavailable_date)
    ) $cc;");
    if(!get_option('mlb_settings')) update_option('mlb_settings', mlb_defaults());
    mlb_ensure_teacher_user();
}

function mlb_defaults(){
    return [
        'business_name'=>get_bloginfo('name'),'teacher_email'=>get_option('admin_email'),
        'currency_symbol'=>'R','instruments'=>'Piano, Guitar, Violin, Voice',
        'lesson_duration'=>60,'lesson_price'=>350,'buffer_minutes'=>30,'max_advance'=>90,
        'business_hours'=>[
            0=>['off','off'],1=>['09:00','17:00'],2=>['09:00','17:00'],
            3=>['09:00','17:00'],4=>['09:00','17:00'],5=>['09:00','17:00'],
            6=>['09:00','13:00'],
        ],
        'field_name_label'=>'Full Name','field_email_label'=>'Email Address',
        'field_instr_label'=>'Instrument','field_date_label'=>'Preferred Date',
        'field_phone'=>1,'field_phone_req'=>0,'field_phone_label'=>'Phone Number',
        'field_age'=>1,'field_age_req'=>0,'field_age_label'=>'Age',
        'field_level'=>1,'field_level_req'=>0,'field_level_label'=>'Experience Level',
        'field_notes'=>1,'field_notes_req'=>0,'field_notes_label'=>'Notes / Goals',
        'experience_options'=>'Complete Beginner, Some Experience, Intermediate, Advanced',
        'custom_fields'=>[],
        'booking_intro'=>'Select a date, instrument, and time to book your lesson.',
        'confirm_message'=>'Your lesson is confirmed! Please check your email for details.',
        'notify_admin'=>1,
        'color_primary'=>'#e36868','color_secondary'=>'#c2492d','color_accent'=>'#f1a830',
    ];
}
