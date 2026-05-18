<?php
if ( ! defined( 'ABSPATH' ) ) exit;
add_action('admin_menu', function(){
    add_menu_page('Lesson Booking','Lesson Booking','manage_options','mlb','mlb_pg_dash','dashicons-calendar-alt',25);
    add_submenu_page('mlb','Dashboard',    'Dashboard',    'manage_options','mlb',          'mlb_pg_dash');
    add_submenu_page('mlb','All Bookings', 'All Bookings', 'manage_options','mlb-book',     'mlb_pg_bookings');
    add_submenu_page('mlb','Settings',     'Settings',     'manage_options','mlb-settings', 'mlb_pg_settings');
    add_submenu_page('mlb','Blocked Dates','Blocked Dates','manage_options','mlb-blocked',  'mlb_pg_blocked');
    add_submenu_page('mlb','Changelog',    'Changelog',    'manage_options','mlb-log',      'mlb_pg_changelog');
    add_submenu_page('mlb','How to Use',   'How to Use',   'manage_options','mlb-help',     'mlb_pg_help');
});
