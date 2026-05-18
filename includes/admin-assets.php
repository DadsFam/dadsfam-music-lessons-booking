<?php
if ( ! defined( 'ABSPATH' ) ) exit;
add_action('admin_enqueue_scripts', function($hook){
    if (strpos($hook,'mlb') === false) return;
    $cf = (int) count((array) mlb_setting('custom_fields',[]));
    $p  = mlb_color('color_primary','#e36868');
    $s2 = mlb_color('color_secondary','#c2492d');
    wp_enqueue_style('mlb-admin', MLB_URL.'assets/admin.css', [], MLB_VERSION);
    wp_add_inline_style('mlb-admin', ":root{--c1:{$p};--c2:{$s2};}");
    wp_enqueue_script('mlb-admin', MLB_URL.'assets/admin.js', ['jquery'], MLB_VERSION, true);
    wp_localize_script('mlb-admin','mlbAdmin',[
        'ajaxurl'   => admin_url('admin-ajax.php'),
        'nonce'     => wp_create_nonce('mlb_diag'),
        'licNonce'  => wp_create_nonce('dfmlb_lic_nonce'),
        'cfIdx'     => $cf,
    ]);
});
