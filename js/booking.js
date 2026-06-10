// Calender + 1-hour consecutive slot selector (1-3 slots = 1-3 hours)
(function(){
    const cal = document.getElementById('calendar');
    if(!cal) return;
    const facId=cal.dataset.facilityId;
    const dateInput=document.getElementById('selectedDate');
    const slotsBox=document.getElementById('slotsBox');
    const form=document.getElementById('reserveForm');
    const info=document.getElementById('slotInfo');
    let cursor=new Date(); cursor.setDate(1);
    const today=new Date(); today.setHours(0,0,0,0);
    let currentSlots=[]; let selected=[]; // array of start indices

    function render(){
        const y=cursor.getFullYear(); const m=cursor.getMonth();
        const
        months=['January','February','March','April','May','June','July','August','September','October','November','December'];
        let html=`<div class="cal-header"><button type="button" class="cal-nav" id="prevMo">‹</button>
      <div class="cal-title">${months[m]} ${y}</div>
      <button type="button" class="cal-nav" id="nextMo">›</button>
    </div><div class="cal-grid">
    ${['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa'].map(d=>`<div class="cal-day">${d}</div>`).join('')}`;
    const first=new Date(y,m,1).getDay();
    const days=new Date(y,m+1,0).getDate();
    for(let i=0;i<first;i++) html+='<div></div>';
    for(let d=1;d<=days;d++){
        const dt=new Date(y,m,d);
        const iso=dt.getFullYear()+'-'+String(dt.getMonth()+1).padStart(2,'0')+'-'+String(d).padStart(2,'0');   
        const past = dt < today;
        const cls = ['cal-date'];
        if(past) cls.push('disabled');
        if(+dt===+today) cls.push('today');
        if(dateInput.value===iso) cls.push('selected');
        html+=`<div class="${cls.join(' ')}" data-iso="${iso}">${d}</div>`;
    }
    html+='</div>';
    cal.innerHTML=html;
    cal.querySelector('#prevMo').onclick=()=>
        { cursor.setMonth(cursor.getMonth()-1); render(); };
    cal.querySelector('#nextMo').onclick=()=>{cursor.setMonth(cursor.getMonth()+1); render(); };
    cal.querySelectorAll('.cal-date:not(.disabled)').forEach(el=>{
       el.onclick=()=>{
        if (!el.dataset.iso) return;
        
        dateInput.value=el.dataset.iso;
        render(); 
        loadSlots(el.dataset.iso);
      };
    });
}

function loadSlots(iso){
    selected=[]; form.style.display='none';
    slotsBox.innerHTML='<p style="color:var(--muted);font-size:13px">Loading slots…</p>';
    fetch('booking.php?facility_id='+facId+'&fetch_slots=1&date='+encodeURIComponent(iso))
    .then(r=>r.json()).then(j=>{
        currentSlots=j.slots||[];
        // Disable past slots if today is selected
        const todayIso=today.getFullYear()+'-'+String(today.getMonth()+1).padStart(2,'0')+'-'+String(today.getDate()).padStart(2,'0');
        const nowHour=new Date().getHours();
        if(iso===todayIso){
            currentSlots=currentSlots.map(s=>{
                const slotHour=parseInt(s.start.split(':')[0],10);
                if(slotHour<=nowHour) return {...s,status: 'full'};
                return s;
            });
        } 
        if(!currentSlots.length){ slotsBox.innerHTML='<p class="empty">No slots available.</p>'; return; }
        let h='<div class="slot-legend">'+
        '<div class="legend-item"><span class ="legend-dot" style="background:#22c55e"></span> Available</div>'+
         '<div class="legend-item"><span class="legend-dot" style="background:#eab308"></span>Limited</div>'+
         '<div class="legend-item"><span class="legend-dot" style="background:#ef4444"></span>Full</div>'+
         '<div class="legend-item"><span class="legend-dot" style="background:#6b7280"></span>Maintenance</div>'+
            '</div><div class="slot-grid">';
        currentSlots.forEach((s,i)=>{
            const disabled = s.status==='full' || s.status==='maintenance';
            h+=`<div class="slot ${s.status}" data-i="${i}" ${disabled?'data-disabled="1"':''}>${s.label}</div>`;
        });
        h+='</div>';
        slotsBox.innerHTML=h;
        slotsBox.querySelectorAll('.slot').forEach(el=>{
            el.onclick=()=>toggleSlot(parseInt(el.dataset.i,10), el);
        });
    });
}
function toggleSlot(i, el){
    if(el.dataset.disabled) return;

    if(selected.includes(i)){
        selected=selected.filter(x=>x!==i);
    } else {
        if (selected.length>=3){ info.textContent='Maximum reservation duration is 3 hours.';
            info.style.color='var(--red)'; 
            return; 
        }

            const candidate=[...selected, i].sort((a,b)=>a-b);

            // must remain consecutive
            for (let k=1;k<candidate.length;k++){
                if (candidate[k]!==candidate[k-1]+1){ info.textContent='Please pick consecutive time slots.'; info.style.color='var(--red)'; 
                    return; 

                }
            }
            selected=candidate;
        }
        info.style.color='var(--muted)';
        refreshSelection();
    }
    
    function refreshSelection(){
        slotsBox.querySelectorAll('.slot').forEach(el=>{
            el.classList.toggle('selected', selected.includes(parseInt(el.dataset.i,10)));
        });

        if (!selected.length){
            form.style.display='none';
            info.textContent='Click 1 to 3 consecutive slots, then confirm.';
            return;
        }

        const first=currentSlots[selected[0]];
        const last=currentSlots[selected[selected.length-1]];

        form.querySelector('[name=booking_date]').value=dateInput.value;
        form.querySelector('[name=start_time]').value=first.start;
        form.querySelector('[name=end_time]').value=last.end;

        info.textContent='Selected: '+first.label.split(' - ')[0]+' – '+last.label.split(' - ')[1]+' ('+selected.length+'h)';
        form.style.display='block';

    }


render();
})();
        
