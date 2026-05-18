<?php
if(!defined('ABSPATH')) exit;
function mlb_pg_help(){
    echo '<div class="wrap"><div class="mlb-hd"><div class="mlb-hd-i">📌</div><div><h1>How to Use</h1><p>Setup guide</p></div></div>';
    echo '<div class="mlb-card"><div class="mlb-ct">📝 Student Booking Form</div><div class="mlb-scbox">[music_lesson_booking]</div></div>';
    echo '<div class="mlb-card"><div class="mlb-ct">👩‍🏫 Teacher Dashboard</div><div class="mlb-scbox">[teacher_lesson_dashboard]</div></div>';
    echo '<div class="mlb-card" style="border-left:4px solid #f59e0b;"><div class="mlb-ct">💡 Quick Setup</div><ol style="font-size:13px;color:#475569;line-height:2.3;margin:0;padding-left:18px;">
    <li><strong>Settings → General</strong>: currency, price, name, instruments</li>
    <li><strong>Settings → Hours</strong>: days and times you teach</li>
    <li><strong>Settings → Form Fields</strong>: configure labels, optional fields, add custom fields</li>
    <li><strong>Settings → Appearance</strong>: match your site colours</li>
    <li><strong>Settings → Teacher Login</strong>: use the <em>Set Password</em> tool to set your portal password</li>
    <li>Create page "Book a Lesson" → add <code>[music_lesson_booking]</code></li>
    <li>Create page "Teacher Portal" → add <code>[teacher_lesson_dashboard]</code></li>
    <li><strong>Blocked Dates</strong> → mark public holidays or days off</li>
    </ol></div>
    <div class="mlb-card" style="border-left:4px solid #10b981;"><div class="mlb-ct">🔍 Looking Up a Booking Code</div>
    <p style="font-size:13px;color:#475569;margin:0;"><strong>All Bookings</strong> → type the code in the search bar → booking appears instantly. Code shown in a coloured badge.</p>
    </div></div>';
}
