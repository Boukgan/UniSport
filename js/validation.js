// Password strength meter - wired to #pwInput / #pwBar
(function(){
    const inp = document.getElementById('pwInput');
    const bar = document.getElementById('pwBar');
    const dept = document.getElementById('deptPreview');
    const matric = document.getElementById('matricInput');

    function score(pw){
        let s=0;
        if (pw.length>=8) s++;
        if (/[A-Z]/.test(pw)) s++;
        if (/[0-9]/.test(pw)) s++;
        if (/[^A-Za-z0-9]/.test(pw)) s++;
        if (pw.length>=12) s++;
        return Math.min(s, 4);
    }
    function paint(s){
        const colors=['#e5e7eb','#ef4444','#f59e0b','#eab308','#22c55e','#16a34a'];
        const pct=[0,20,40,60,85,100][s];
        if(!bar) return;
        bar.style.width=pct+'%';
        bar.style.background=colors[s];
    }
    inp?.addEventListener('input',()=>paint(score(inp.value)));

    // Department auto-detect from matric
    const facMap={'01':'FKE','02':'FKEKK','03':'FTMK','04':'FKM','05':'FKP',
        '06':'FPTT','07':'FTK','08':'FTKEE','09':'FTKMP'};
        matric?.addEventListener('input',()=>{
            const v=matric.value.toUpperCase();
            const code=v.substr(1,2);
            if(dept) dept.value = facMap[code] || '';
        });  
    })();
