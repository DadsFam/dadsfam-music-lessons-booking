/* Music Lesson Booking — Booking Form JS */
(function(){
    var n = mlbForm.nonce, a = mlbForm.ajaxurl;
    var form = document.getElementById('mlb-booking-form');
    if (!form) return;
    var dateEl = document.getElementById('mlb-date');
    var tv     = document.getElementById('mlb-tv');
    var sub    = document.getElementById('mlb-submit');
    var msg    = document.getElementById('mlb-msg');
    var stWrap = document.getElementById('mlb-st');
    var stVal  = document.getElementById('mlb-stv');

    function showMsg(t,ok){
        msg.style.display='block';
        msg.className='mlb-msg '+(ok?'ok':'err');
        msg.innerHTML=t;
    }
    function setBtn(on){
        sub.disabled=!on;
        sub.style.opacity=on?'1':'0.5';
        sub.style.cursor=on?'pointer':'not-allowed';
    }

    function loadSlots(date){
        var w = document.getElementById('mlb-slots-wrap');
        w.innerHTML='<p style="color:#94a3b8;text-align:center;padding:14px;">⏳ Checking availability…</p>';
        tv.value=''; setBtn(false);
        if (stWrap) stWrap.style.display='none';
        var fd = new FormData();
        fd.append('action','mlb_slots'); fd.append('_ajax_nonce',n); fd.append('date',date);
        fetch(a,{method:'POST',body:fd}).then(function(r){return r.json();}).then(function(res){
            if(!res.success){w.innerHTML='<p style="color:#ef4444;text-align:center;padding:14px;">⚠️ Could not load times.</p>';return;}
            var sl=res.data.slots;
            if(!sl||!sl.length){
                var r2=res.data.reason,
                    t2=r2==='blocked'?'🚫 This date is unavailable.':r2==='closed'?'🚫 No lessons on this day.':'😕 No available times for this date.';
                w.innerHTML='<p style="color:#ef4444;text-align:center;padding:14px;">'+t2+'</p>';return;
            }
            var h='<div class="mlb-slg">';
            sl.forEach(function(t){
                var d=new Date('2000-01-01T'+t+':00'),lb=d.toLocaleTimeString([],{hour:'numeric',minute:'2-digit'});
                h+='<div class="mlb-sl" data-time="'+t+'" onclick="mlbPickSlot(this,\''+t+'\')">'+lb+'</div>';
            });
            w.innerHTML=h+'</div>';
        }).catch(function(){w.innerHTML='<p style="color:#ef4444;text-align:center;padding:14px;">⚠️ Network error.</p>';});
    }

    window.mlbPickSlot = function(el,t){
        document.querySelectorAll('.mlb-sl').forEach(function(s){
            s.classList.remove('sel'); s.style.background=''; s.style.borderColor='';
        });
        el.classList.add('sel');
        el.style.background = mlbForm.colorPrimary;
        el.style.borderColor= mlbForm.colorPrimary;
        tv.value=t;
        var d=new Date('2000-01-01T'+t+':00');
        if(stWrap){stWrap.style.display='';}
        if(stVal) stVal.textContent=d.toLocaleTimeString([],{hour:'numeric',minute:'2-digit'});
        setBtn(true);
    };

    if (dateEl) dateEl.addEventListener('change',function(){if(this.value) loadSlots(this.value);});

    form.addEventListener('submit',function(e){
        e.preventDefault();
        if(!tv.value){showMsg('⚠️ Please select a time slot.',false);return;}
        sub.disabled=true; sub.textContent='Booking…'; sub.style.opacity='.7';
        msg.style.display='none';
        var fd=new FormData(form);
        fd.append('action','mlb_book'); fd.append('_ajax_nonce',n);
        fetch(a,{method:'POST',body:fd}).then(function(r){return r.json();}).then(function(res){
            if(res.success){
                document.getElementById('mlb-form-body').innerHTML=
                    '<div style="text-align:center;padding:38px 18px;">'+
                    '<div style="font-size:48px;margin-bottom:11px;">✅</div>'+
                    '<h3 style="font-size:18px;color:#065f46;margin-bottom:8px;">'+res.data.message+'</h3>'+
                    '<div style="background:'+mlbForm.colorPrimary+';color:#fff;padding:11px 18px;border-radius:8px;font-size:19px;font-weight:900;letter-spacing:5px;display:inline-block;margin-top:8px;">'+res.data.code+'</div>'+
                    '<p style="color:#64748b;margin-top:9px;font-size:13px;">Confirmation email sent. Keep your code!</p></div>';
            } else {
                showMsg('❌ '+res.data, false);
                sub.disabled=false; sub.textContent='Complete Booking →'; sub.style.opacity='1';
            }
        }).catch(function(){
            showMsg('❌ Network error — please try again.',false);
            sub.disabled=false; sub.textContent='Complete Booking →'; sub.style.opacity='1';
        });
    });
})();
