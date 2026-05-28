/* Music Lesson Booking — form.js v5.3.0 — weekly calendar view */
(function(){
var AJ   = mlbForm.ajaxurl;
var N    = mlbForm.nonce;
var CP   = mlbForm.colorPrimary;
var CS   = mlbForm.colorSecondary;
var DUR  = parseInt(mlbForm.duration)  || 60;
var OPEN = parseInt(mlbForm.calOpen)   || 540;   // minutes from midnight
var CLOS = parseInt(mlbForm.calClose)  || 1020;
var MAX  = parseInt(mlbForm.maxAdvance)|| 90;

var form    = document.getElementById('mlb-booking-form');
var dateInp = document.getElementById('mlb-date');
var timeInp = document.getElementById('mlb-tv');
var sub     = document.getElementById('mlb-submit');
var msg     = document.getElementById('mlb-msg');

if(!form) return;

// ── State ─────────────────────────────────────────────────────────────────────
var calData     = {};   // {date: {available:[], booked:[], closed:bool, past:bool, ...}}
var selected    = null; // {date, time}
var weekStart   = null; // Date object (Monday)

// ── Utilities ─────────────────────────────────────────────────────────────────
function dateStr(d){
    return d.getFullYear()+'-'+pad(d.getMonth()+1)+'-'+pad(d.getDate());
}
function pad(n){ return n<10?'0'+n:n; }
function t2m(t){ var p=t.split(':'); return parseInt(p[0])*60+parseInt(p[1]); }
function m2t(m){ return pad(Math.floor(m/60))+':'+pad(m%60); }
function fmtTime(t){
    var d=new Date('2000-01-01T'+t+':00');
    return d.toLocaleTimeString([],{hour:'numeric',minute:'2-digit'});
}
function fmtDate(dateStr){
    var d=new Date(dateStr+'T00:00:00');
    return d.toLocaleDateString('en-ZA',{weekday:'long',day:'numeric',month:'long'});
}
function getMondayOf(d){
    var dd=new Date(d);
    var day=dd.getDay();
    var diff=day===0?-6:1-day;
    dd.setDate(dd.getDate()+diff);
    dd.setHours(0,0,0,0);
    return dd;
}
function post(action,data,cb){
    var fd=new FormData();
    fd.append('action',action);fd.append('_ajax_nonce',N);
    Object.keys(data).forEach(function(k){fd.append(k,data[k]);});
    fetch(AJ,{method:'POST',body:fd}).then(function(r){return r.json();}).then(cb)
        .catch(function(){cb({success:false,data:'Network error'});});
}

// ── Calendar init / navigation ────────────────────────────────────────────────
function calInit(){
    weekStart = getMondayOf(new Date());
    calLoad();
}

window.calToday = function(){ weekStart=getMondayOf(new Date()); calLoad(); };
window.calPrev  = function(){
    var prev=new Date(weekStart); prev.setDate(prev.getDate()-7);
    // Don't go before today's week
    var todayMon=getMondayOf(new Date());
    if(prev<todayMon) return;
    weekStart=prev; calLoad();
};
window.calNext  = function(){
    var next=new Date(weekStart); next.setDate(next.getDate()+7);
    weekStart=next; calLoad();
};
window.calRefresh = function(){ if(weekStart) calLoad(); };

// Disable prev button if already on current week
function updateNavBtns(){
    var prevBtn=document.getElementById('cal-prev');
    if(!prevBtn) return;
    var todayMon=getMondayOf(new Date());
    prevBtn.disabled = weekStart <= todayMon;
    prevBtn.style.opacity = weekStart <= todayMon ? '0.35' : '1';
    prevBtn.style.cursor  = weekStart <= todayMon ? 'not-allowed' : 'pointer';
}

function calLoad(){
    updateNavBtns();
    var wrap=document.getElementById('mlb-cal-wrap');
    wrap.innerHTML='<div style="padding:22px;text-align:center;color:#94a3b8;">⏳ Loading availability…</div>';

    // Build week label
    var sun=new Date(weekStart); sun.setDate(weekStart.getDate()+6);
    var opts={day:'numeric',month:'long'};
    var lbl=weekStart.toLocaleDateString('en-ZA',opts)+' – '+sun.toLocaleDateString('en-ZA',{day:'numeric',month:'long',year:'numeric'});
    var lbl_el=document.getElementById('cal-week-label');
    if(lbl_el) lbl_el.textContent=lbl;

    post('mlb_week_slots',{week:dateStr(weekStart)},function(res){
        if(!res.success){ wrap.innerHTML='<p style="color:#ef4444;padding:18px;text-align:center;">Error loading calendar.</p>'; return; }
        calData=res.data;
        calRender(wrap);
    });
}

// ── Render the calendar table ─────────────────────────────────────────────────
function calRender(wrap){
    var today=dateStr(new Date());

    // Build list of dates for the week
    var dates=[];
    for(var i=0;i<7;i++){
        var d=new Date(weekStart); d.setDate(weekStart.getDate()+i);
        dates.push(dateStr(d));
    }

    // Build list of time rows (30-min steps across full open range)
    var times=[];
    for(var m=OPEN;m+DUR<=CLOS;m+=30) times.push(m2t(m));

    if(!times.length){
        wrap.innerHTML='<p style="color:#64748b;padding:18px;text-align:center;">No lesson times configured — check Settings → Hours.</p>';
        return;
    }

    // Column day headers
    var dayNames=['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
    var html='<table class="mlb-cal-table"><thead><tr>';
    html+='<th style="min-width:52px;"></th>'; // time column header
    dates.forEach(function(dt){
        var d=new Date(dt+'T00:00:00');
        var isToday=dt===today;
        var dayData=calData[dt]||{};
        var isOff=dayData.closed||dayData.past||dayData.too_far;
        html+='<th class="'+(isToday?'today-hd':'')+'" style="min-width:88px;'+(isOff?'opacity:.55;':'')+'">'+
              '<div>'+dayNames[d.getDay()]+'</div>'+
              '<div style="font-size:15px;font-weight:900;color:'+(isToday?'#92400e':'#1e293b')+';">'+d.getDate()+'</div>'+
              '<div style="font-size:9px;color:#94a3b8;">'+d.toLocaleDateString('en-ZA',{month:'short'})+'</div>'+
              '</th>';
    });
    html+='</tr></thead><tbody>';

    times.forEach(function(t){
        html+='<tr>';
        html+='<td class="mlb-cal-time">'+fmtTime(t)+'</td>';

        dates.forEach(function(dt){
            var dayData=calData[dt]||{};
            var isToday=dt===today;
            var isSel=selected&&selected.date===dt&&selected.time===t;
            var avail=(dayData.available||[]).indexOf(t)>-1;
            var booked=(dayData.booked||[]).indexOf(t)>-1;
            var closed=dayData.closed;
            var isPast=dayData.past;
            var tooFar=dayData.too_far;

            var tdClass='mlb-slot ';
            var inner='';
            var extraStyle=isToday?'background:#fffdf0;':'';

            if(isSel){
                tdClass+='mlb-slot-av mlb-slot-sel';
                inner='<span class="sc" style="background:'+CP+';color:#fff;">✓ '+fmtTime(t)+'</span>';
                extraStyle='background:'+CP+'18;';
            } else if(closed||isPast||tooFar){
                tdClass+='mlb-slot-off';
                inner='<span class="sc" style="opacity:.15;">–</span>';
            } else if(booked){
                tdClass+='mlb-slot-bk';
                inner='<span class="sc">✕</span>';
            } else if(avail){
                tdClass+='mlb-slot-av';
                inner='<span class="sc">'+fmtTime(t)+'</span>';
            } else {
                tdClass+='mlb-slot-off';
                inner='<span class="sc" style="opacity:.15;">–</span>';
            }

            var onclick=(avail&&!isSel&&!isPast&&!tooFar)?'onclick="calPick(\''+dt+'\',\''+t+'\')"':'';
            html+='<td class="'+tdClass+'" style="'+extraStyle+'" '+onclick+'>'+inner+'</td>';
        });
        html+='</tr>';
    });

    html+='</tbody></table>';
    wrap.innerHTML=html;

    // Scroll to first available slot
    setTimeout(function(){
        var firstAv=wrap.querySelector('.mlb-slot-av');
        if(firstAv) firstAv.scrollIntoView({block:'nearest',inline:'nearest'});
    },50);
}

// ── Pick a slot ───────────────────────────────────────────────────────────────
window.calPick = function(date,time){
    selected={date:date,time:time};
    if(dateInp) dateInp.value=date;
    if(timeInp) timeInp.value=time;

    // Update sel bar
    var bar=document.getElementById('mlb-cal-sel-bar');
    var txt=document.getElementById('mlb-cal-sel-text');
    if(bar&&txt){
        txt.textContent=fmtDate(date)+' at '+fmtTime(time);
        bar.style.display='block';
    }

    // Update summary
    var stWrap=document.getElementById('mlb-st');
    var stVal =document.getElementById('mlb-stv');
    if(stWrap) stWrap.style.display='';
    if(stVal)  stVal.textContent=fmtDate(date)+' · '+fmtTime(time);

    // Enable submit
    if(sub){ sub.disabled=false; sub.style.opacity='1'; }

    // Re-render to show selection
    var wrap=document.getElementById('mlb-cal-wrap');
    if(wrap) calRender(wrap);
};

// ── Payment method visual ─────────────────────────────────────────────────────
window.mlbUpdatePM = function(radioEl){
    var pm=radioEl.value;
    var sym=mlbForm.sym||'R';
    var pr=parseFloat(mlbForm.price||0).toFixed(2);
    var labels={'yoco':'💳 Pay '+sym+pr+' by Card →','eft':'🏦 Book & Pay '+sym+pr+' via EFT →','none':'Complete Booking →'};
    if(sub) sub.textContent=labels[pm]||labels['none'];
    document.querySelectorAll('.mlb-pay-opt').forEach(function(lbl){
        var inp=lbl.querySelector('input[type=radio]');
        var sel=inp&&inp.value===pm;
        lbl.style.borderColor=sel?CP:'#e2e8f0';
        lbl.style.background =sel?CP+'11':'#fff';
    });
};
// Set initial button label
(function(){
    var checked=document.querySelector('input[name="payment_method"]:checked');
    if(checked&&sub){
        var sym=mlbForm.sym||'R',pr=parseFloat(mlbForm.price||0).toFixed(2);
        var labels={'yoco':'💳 Pay '+sym+pr+' by Card →','eft':'🏦 Book & Pay '+sym+pr+' via EFT →'};
        sub.textContent=labels[checked.value]||sub.textContent;
    }
})();

// ── Form submit ───────────────────────────────────────────────────────────────
form.addEventListener('submit',function(e){
    e.preventDefault();
    if(!timeInp||!timeInp.value){showMsg('⚠️ Please select a time slot from the calendar.',false);return;}
    var instrEl=document.getElementById('mlb-instrument');
    if(!instrEl||!instrEl.value){showMsg('⚠️ Please select an instrument.',false);return;}
    sub.disabled=true; sub.textContent='Processing…'; sub.style.opacity='.7';
    msg.style.display='none';

    var fd=new FormData(form);
    fd.append('action','mlb_book');
    fd.append('_ajax_nonce',N);
    fd.append('return_url',window.location.href.replace(/[?#].*/,''));
    // Explicitly read the checked radio — don't rely on FormData alone
    var pmChecked=document.querySelector('input[name="payment_method"]:checked');
    if(pmChecked) fd.set('payment_method', pmChecked.value);

    fetch(AJ,{method:'POST',body:fd}).then(function(r){return r.json();}).then(function(res){
        if(!res.success){
            showMsg('❌ '+res.data,false);
            sub.disabled=false; sub.style.opacity='1';
            return;
        }
        var d=res.data;

        if(d.action==='yoco'&&d.redirect_url){
            sub.textContent='Redirecting to payment…';
            window.location.href=d.redirect_url;
            return;
        }

        if(d.action==='eft'){
            var rows='';
            [['Account Holder',d.holder],['Bank',d.bank],['Account Number',d.account],['Branch Code',d.branch],['Amount Due',d.amount],['Reference',d.ref]].forEach(function(f){
                if(!f[1]) return;
                var mono=['Account Number','Branch Code','Reference'].indexOf(f[0])>-1?'font-family:monospace;letter-spacing:1px;':'';
                rows+='<tr><td style="padding:9px 0;border-bottom:1px solid #334155;font-size:13px;color:#94a3b8;font-weight:700;width:38%;">'+f[0]+'</td>'
                     +'<td style="padding:9px 0;border-bottom:1px solid #334155;font-size:14px;color:#fff;font-weight:900;'+mono+'">'+esc(f[1])+'</td></tr>';
            });
            document.getElementById('mlb-form-body').innerHTML=
                '<div style="text-align:center;padding:20px 10px 26px;">'
                +'<div style="font-size:48px;margin-bottom:10px;">🏦</div>'
                +'<h3 style="font-size:18px;color:#1e293b;margin:0 0 6px;">Slot Reserved!</h3>'
                +'<p style="color:#64748b;font-size:13px;margin:0 0 20px;">Complete payment using the details below to confirm your lesson.</p>'
                +'<div style="background:#1e293b;border-radius:12px;padding:18px 20px;margin-bottom:16px;text-align:left;">'
                +'<div style="font-size:11px;color:#94a3b8;letter-spacing:2px;text-transform:uppercase;font-weight:800;margin-bottom:10px;">Bank Transfer Details</div>'
                +'<table width="100%" cellpadding="0" cellspacing="0">'+rows+'</table></div>'
                +(d.instructions?'<div style="background:#fef3c7;border-radius:8px;padding:12px 14px;margin-bottom:14px;font-size:13px;color:#92400e;text-align:left;">'+esc(d.instructions)+'</div>':'')
                +'<div style="background:#d1fae5;border-radius:8px;padding:11px 14px;font-size:13px;color:#065f46;font-weight:700;margin-bottom:14px;">✅ Bank details also sent to your email.</div>'
                +'<code style="background:#1e293b;color:#fbbf24;padding:8px 16px;border-radius:8px;font-size:16px;font-weight:900;letter-spacing:4px;">'+esc(d.code)+'</code>'
                +'<p style="font-size:12px;color:#94a3b8;margin-top:10px;">Your booking will be confirmed once payment is received.</p>'
                +'</div>';
            return;
        }

        // Standard success
        document.getElementById('mlb-form-body').innerHTML=
            '<div style="text-align:center;padding:38px 18px;">'
            +'<div style="font-size:48px;margin-bottom:11px;">✅</div>'
            +'<h3 style="font-size:18px;color:#065f46;margin-bottom:8px;">'+esc(d.message)+'</h3>'
            +'<div style="background:'+CP+';color:#fff;padding:11px 18px;border-radius:8px;font-size:19px;font-weight:900;letter-spacing:5px;display:inline-block;margin-top:8px;">'+esc(d.code)+'</div>'
            +'<p style="color:#64748b;margin-top:9px;font-size:13px;">Confirmation email sent. Keep your code!</p></div>';

    }).catch(function(){
        showMsg('❌ Network error — please try again.',false);
        sub.disabled=false; sub.style.opacity='1';
    });
});

function showMsg(t,ok){
    msg.style.display='block'; msg.className='mlb-msg '+(ok?'ok':'err'); msg.innerHTML=t;
}
function esc(s){var d=document.createElement('div');d.textContent=s||'';return d.innerHTML;}

// Boot the calendar on load
calInit();
})();
