/* Music Lesson Booking — Admin JS */
var mlbCFI = mlbAdmin.cfIdx || 0;

function mlbTab(g, id, el) {
    document.querySelectorAll('.mlb-panel[data-g="'+g+'"]').forEach(function(p){ p.classList.remove('act'); });
    document.querySelectorAll('.mlb-tab[data-g="'+g+'"]').forEach(function(t){ t.classList.remove('act'); });
    var panel = document.getElementById('mlb-p-'+id);
    if (panel) panel.classList.add('act');
    el.classList.add('act');
    try { localStorage.setItem('mlb_tab_'+g, id); } catch(e){}
}

document.addEventListener('DOMContentLoaded', function(){
    // Restore last active tab
    document.querySelectorAll('.mlb-tabs').forEach(function(tb){
        var g = null;
        tb.querySelectorAll('.mlb-tab[data-g]').forEach(function(t){ g = t.getAttribute('data-g'); });
        if (!g) return;
        var saved; try { saved = localStorage.getItem('mlb_tab_'+g); } catch(e){}
        if (saved) {
            var btn = tb.querySelector('.mlb-tab[data-tab="'+saved+'"]');
            if (btn) btn.click();
        }
    });

    // Color preview
    var p1 = document.getElementById('mlb-cp1'), p2 = document.getElementById('mlb-cp2'), pv = document.getElementById('mlb-prev');
    function updatePrev(){ if(p1&&p2&&pv) pv.style.background='linear-gradient(135deg,'+p1.value+','+p2.value+')'; }
    if (p1) p1.addEventListener('input', updatePrev);
    if (p2) p2.addEventListener('input', updatePrev);

    // Diagnostic: test password
    var testBtn = document.getElementById('mlb-test-btn');
    if (testBtn) testBtn.addEventListener('click', function(){
        var pw  = document.getElementById('mlb-test-pw').value;
        var res = document.getElementById('mlb-test-result');
        res.textContent = 'Testing…'; res.style.color = '#64748b';
        var fd = new FormData();
        fd.append('action','mlb_test_password');
        fd.append('_ajax_nonce', mlbAdmin.nonce);
        fd.append('pw', pw);
        fetch(mlbAdmin.ajaxurl, {method:'POST',body:fd})
            .then(function(r){ return r.json(); })
            .then(function(r){ res.textContent=r.data; res.style.color=r.success?'#065f46':'#991b1b'; });
    });

    // Diagnostic: reset to teacher123
    var resetBtn = document.getElementById('mlb-reset-btn');
    if (resetBtn) resetBtn.addEventListener('click', function(){
        if (!confirm('Reset teacher password to "teacher123"?')) return;
        var res = document.getElementById('mlb-reset-result');
        res.textContent = 'Resetting…'; res.style.color = '#64748b';
        var fd = new FormData();
        fd.append('action','mlb_reset_password');
        fd.append('_ajax_nonce', mlbAdmin.nonce);
        fetch(mlbAdmin.ajaxurl, {method:'POST',body:fd})
            .then(function(r){ return r.json(); })
            .then(function(r){
                res.textContent = r.data; res.style.color = r.success?'#065f46':'#991b1b';
                if (r.success) setTimeout(function(){ location.reload(); }, 1600);
            });
    });

    // Diagnostic: save custom password directly
    var saveBtn = document.getElementById('mlb-setpw-btn');
    if (saveBtn) saveBtn.addEventListener('click', function(){
        var pw  = document.getElementById('mlb-setpw-input').value;
        var res = document.getElementById('mlb-setpw-result');
        if (!pw) { res.textContent='Enter a password first'; res.style.color='#991b1b'; return; }
        res.textContent = 'Saving…'; res.style.color = '#64748b';
        var fd = new FormData();
        fd.append('action','mlb_save_custom_pw');
        fd.append('_ajax_nonce', mlbAdmin.nonce);
        fd.append('pw', pw);
        fetch(mlbAdmin.ajaxurl, {method:'POST',body:fd})
            .then(function(r){ return r.json(); })
            .then(function(r){
                res.textContent = r.data; res.style.color = r.success?'#065f46':'#991b1b';
                if (r.success) setTimeout(function(){ location.reload(); }, 1600);
            });
    });
});

function mlbAddCF(){
    var i = mlbCFI++;
    var r = document.createElement('div');
    r.className = 'mlb-cf-row'; r.id = 'cf-row-'+i;
    r.innerHTML =
        '<input type="text" name="cf_label[]" placeholder="Field label" required>' +
        '<select name="cf_type[]">' +
        '<option value="text">Text</option><option value="textarea">Long Text</option>' +
        '<option value="select">Dropdown</option><option value="tel">Phone</option>' +
        '<option value="email">Email</option><option value="number">Number</option>' +
        '</select>' +
        '<label style="display:flex;align-items:center;gap:5px;font-size:13px;white-space:nowrap;">' +
        '<input type="checkbox" name="cf_req_'+i+'"> Req</label>' +
        '<input type="text" name="cf_options[]" placeholder="A, B, C (dropdown)">' +
        '<button type="button" class="mlb-del" onclick="document.getElementById(\'cf-row-'+i+'\').remove()">✕</button>';
    document.getElementById('mlb-cf-rows').appendChild(r);
}

/* ── DadsFam License handlers ──────────────────────────────────────────── */
document.addEventListener('DOMContentLoaded', function(){
    var aj  = mlbAdmin.ajaxurl;
    var ln  = mlbAdmin.licNonce;

    function licPost(action, extra, cb){
        var fd = new FormData();
        fd.append('action', action);
        fd.append('_ajax_nonce', ln);
        if(extra) Object.keys(extra).forEach(function(k){ fd.append(k, extra[k]); });
        fetch(aj,{method:'POST',body:fd}).then(function(r){return r.json();}).then(cb)
            .catch(function(){ cb({success:false,data:'Network error'}); });
    }

    function setLicMsg(msg, ok){
        var el = document.getElementById('dfmlb-lic-msg');
        if(!el) return;
        el.textContent = msg;
        el.style.display = 'block';
        el.style.background = ok ? '#d1fae5' : '#fee2e2';
        el.style.color      = ok ? '#065f46' : '#991b1b';
        el.style.border     = '2px solid ' + (ok ? '#6ee7b7' : '#fca5a5');
    }

    // Activate
    var actBtn = document.getElementById('dfmlb-activate-btn');
    if(actBtn) actBtn.addEventListener('click', function(){
        var key = (document.getElementById('dfmlb-key-input').value || '').trim();
        if(!key){ setLicMsg('Please enter your license key.', false); return; }
        actBtn.textContent = 'Activating…'; actBtn.disabled = true;
        licPost('dfmlb_activate', {key: key}, function(r){
            setLicMsg(r.data, r.success);
            actBtn.textContent = 'Activate License'; actBtn.disabled = false;
            if(r.success) setTimeout(function(){ location.reload(); }, 1200);
        });
    });

    // Deactivate
    var deaBtn = document.getElementById('dfmlb-deactivate-btn');
    if(deaBtn) deaBtn.addEventListener('click', function(){
        if(!confirm('Deactivate this license?\n\nThis frees up one activation slot. You can re-activate later.')) return;
        deaBtn.textContent = 'Deactivating…'; deaBtn.disabled = true;
        licPost('dfmlb_deactivate', {}, function(r){
            if(r.success) { location.reload(); }
            else { setLicMsg(r.data, false); deaBtn.textContent = 'Deactivate'; deaBtn.disabled = false; }
        });
    });

    // Re-verify now
    var revBtn = document.getElementById('dfmlb-reverify-btn');
    if(revBtn) revBtn.addEventListener('click', function(){
        revBtn.textContent = 'Checking…'; revBtn.disabled = true;
        licPost('dfmlb_reverify', {}, function(r){
            setLicMsg(r.data, r.success);
            revBtn.textContent = 'Re-check Now'; revBtn.disabled = false;
            if(r.success) setTimeout(function(){ location.reload(); }, 1200);
        });
    });

    // Toggle key visibility
    var showBtn = document.getElementById('dfmlb-show-key');
    if(showBtn) showBtn.addEventListener('click', function(){
        var inp = document.getElementById('dfmlb-key-input');
        if(!inp) return;
        inp.type = inp.type === 'password' ? 'text' : 'password';
        showBtn.textContent = inp.type === 'password' ? '👁 Show' : '🙈 Hide';
    });
});
