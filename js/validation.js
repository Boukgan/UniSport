// Password strength meter
(function () {
    const inp    = document.getElementById('pwInput');
    const bar    = document.getElementById('pwBar');
    const hint   = document.getElementById('pwHint');
    const dept   = document.getElementById('deptPreview');
    const matric = document.getElementById('matricInput');

    function score(pw) {
        if (!pw) return 0;
        let s = 0;
        if (pw.length >= 8)            s++;
        if (/[A-Z]/.test(pw))          s++;
        if (/[0-9]/.test(pw))          s++;
        if (/[^A-Za-z0-9]/.test(pw))   s++;
        if (pw.length >= 12)            s++;
        return Math.min(s, 4);
    }

    function paint(s) {
        if (!bar) return;
        const pct    = [0, 25, 50, 75, 100][s];
        const color  = ['#e5e7eb', '#ef4444', '#f59e0b', '#eab308', '#22c55e'][s];
        const label  = ['8–16 characters.', 'Weak', 'Fair', 'Good', 'Strong'][s];
        bar.style.width      = pct + '%';
        bar.style.background = color;
        if (hint) hint.textContent = label;
    }

    if (inp) {
        inp.addEventListener('input', () => paint(score(inp.value)));
        paint(0);
    }

    // Department auto-detect from matric
    const facMap = {
        '01':'FKE','02':'FKEKK','03':'FTMK','04':'FKM','05':'FKP',
        '06':'FPTT','07':'FTK','08':'FTKEE','09':'FTKMP'
    };
    if (matric) {
        matric.addEventListener('input', () => {
            const v    = matric.value.toUpperCase();
            const code = v.substr(1, 2);
            if (dept) dept.value = facMap[code] || '';
        });
    }
})();
