<?php
if ( ! defined( 'ABSPATH' ) ) exit;
function mlb_setting( $k, $d = '' ) {
    $s = get_option( 'mlb_settings', [] );
    return isset( $s[$k] ) ? $s[$k] : $d;
}
function mlb_sym()     { return esc_html( mlb_setting('currency_symbol','R') ); }
function mlb_t2m($t)   { $p=explode(':',$t.':00'); return (int)$p[0]*60+(int)$p[1]; }
function mlb_m2t($m)   { return str_pad(intdiv($m,60),2,'0',STR_PAD_LEFT).':'.str_pad($m%60,2,'0',STR_PAD_LEFT); }
function mlb_color($k,$fb) {
    $v=mlb_setting($k,$fb);
    return preg_match('/^#[a-fA-F0-9]{6}$/',$v) ? $v : $fb;
}
