// ===== User dropdown =====
function toggleUserMenu(e){
    e.stopPropagation();
    document.getElementById('userDropdown')?.classList.toggle('open');
}
document.addEventListener('click',(e)=>{
    if(!e.target.closest('#notifWrap'))
        document.getElementById('notifDropdown')?.classList.remove('open');
    if(!e.target.closest('.nav-user'))
    document.getElementById('userDropdown')?.classList.remove('open');
});

// ===== Theme toggle =====
(function(){
    const root=document.documentElement;
    function apply(theme){
        root.setAttribute('data-theme', theme);
        try{ localStorage.setItem('unisport-theme', theme); }catch(e){}
    }
    // sync from localStorage on load
    try{
        const saved = localStorage.getItem('unisport-theme');
        if(saved) root.setAttribute('data-theme', saved);
    }catch(e){}
    const btn = document.getElementById('themeToggle');
    if(btn){
        btn.addEventListener('click',()=>{
            const next = root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
            apply(next);
            // also persist server-side when logged in
            fetch('profile.php', {
                method: 'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
                body:'action=dark_mode&dark_mode='+(next==='dark'?1:0)
            }).catch(()=>{});
               });
            }
            })();

        // =====  Notification dropdown =====
        (function(){
            const wrap = document.getElementById('notifWrap');
            if(!wrap) return;
            const btn = document.getElementById('notifBtn');
            const dd  = document.getElementById('notifDropdown');
            const badge = document.getElementById('notifBadge');
            const list = document.getElementById('notifList');
            const markAll = document.getElementById('notifMarkAll');

            btn.addEventListener('click',(e)=>{e.stopPropagation();dd.classList.toggle('open');});
            dd.addEventListener('click',(e)=>e.stopPropagation());

            function setUnread(n){
                if(!badge) return;
                if(n>0){ badge.textContent=n; badge.style.display=''; } else
                { badge.style.display='none'; }
            }
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
            const baseUrl = (document.querySelector('nav[data-base]')?.dataset.base || '').replace(/\/+$/, '');
            const notifUrl = baseUrl + '/notifications.php';
            function markRead(id, el){
                const fd = new URLSearchParams();
                fd.append('api', 'mark_read'); fd.append('id', id); fd.append('csrf_token', csrfToken);
                fetch(notifUrl, { method:'POST', body:fd})
                .then(r=>r.json()).then(j=>{ if(j.ok){ el?.classList.remove('unread');
                    el?.classList.add('read'); setUnread(j.unread); }});
                }
            list?.querySelectorAll('.notif-item').forEach(el=>{
                el.addEventListener('click',()=>{
                    if(el.classList.contains('unread')) markRead(el.dataset.id, el);
                });
            });
        markAll?.addEventListener('click',()=>{
            const fd = new URLSearchParams(); fd.append('api', 'mark_all');
            fd.append('csrf_token', csrfToken);
            fetch(notifUrl, { method:'POST', body:fd})
            .then(r=>r.json()).then(j=>{ if(j.ok){ list?.querySelectorAll('.notif-item.unread').forEach(el=>{ el.classList.remove('unread'); el.classList.add('read'); });
setUnread(j.unread); }});
            });
        })();

        // ===== Home slider =====
        (function(){
            const track=document.getElementById('homeSlider');
            if(!track) return;
            const prev=document.querySelector('.slider-arrow.prev');
            const next=document.querySelector('.slider-arrow.next');
            prev?.addEventListener('click',()=> track.scrollBy({left:-340, behavior:'smooth'}));
            next?.addEventListener('click',()=> track.scrollBy({left:340, behavior:'smooth'}));
            let auto=setInterval(()=>{
                if(track.scrollLeft+track.clientWidth>=track.scrollWidth-5)
                    track.scrollTo({left:0,behavior:'smooth'});
                else track.scrollBy({left:340,behavior:'smooth'});
        }, 4500);
        track.addEventListener('mouseenter',()=>clearInterval(auto));
            })();
