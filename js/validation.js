/**
 * UniSport – Client-Side Form Validation
 * Covers: Registration form, Login form
 * Shows inline error messages under each field.
 */

/* ─── Helpers ─────────────────────────────────────────────── */

function showError(field, msg) {
    if (!field) return;
    const row = field.closest('.form-row') || field.parentElement;
    field.classList.add('input-error');
    let span = row.querySelector('.field-error');
    if (!span) {
        span = document.createElement('span');
        span.className = 'field-error';
        row.appendChild(span);
    }
    span.textContent = msg;
}

function clearError(field) {
    if (!field) return;
    const row = field.closest('.form-row') || field.parentElement;
    field.classList.remove('input-error');
    const span = row.querySelector('.field-error');
    if (span) span.textContent = '';
}

function liveClear(field) {
    if (!field) return;
    field.addEventListener('input', () => clearError(field));
}

/* ─── Password strength meter ──────────────────────────────── */
(function () {
    const inp    = document.getElementById('pwInput');
    const bar    = document.getElementById('pwBar');
    const hint   = document.getElementById('pwHint');
    const dept   = document.getElementById('deptPreview');
    const matric = document.getElementById('matricInput');

    function score(pw) {
        if (!pw) return 0;
        let s = 0;
        if (pw.length >= 8)           s++;
        if (/[A-Z]/.test(pw))         s++;
        if (/[0-9]/.test(pw))         s++;
        if (/[^A-Za-z0-9]/.test(pw))  s++;
        if (pw.length >= 12)           s++;
        return Math.min(s, 4);
    }

    function paint(s) {
        if (!bar) return;
        bar.style.width      = [0,25,50,75,100][s] + '%';
        bar.style.background = ['#e5e7eb','#ef4444','#f59e0b','#eab308','#22c55e'][s];
        if (hint) hint.textContent = ['8–16 characters.','Weak','Fair','Good','Strong'][s];
    }

    if (inp) { inp.addEventListener('input', () => paint(score(inp.value))); paint(0); }

    const facMap = { '01':'FKE','02':'FKEKK','03':'FTMK','04':'FKM','05':'FKP',
                     '06':'FPTT','07':'FTK','08':'FTKEE','09':'FTKMP' };
    if (matric) {
        matric.addEventListener('input', () => {
            const code = matric.value.toUpperCase().substr(1, 2);
            if (dept) dept.value = facMap[code] || '';
        });
    }
})();

/* ─── Registration form ────────────────────────────────────── */
(function () {
    const form = document.getElementById('registerForm');
    if (!form) return;

    const fullName = form.querySelector('[name="full_name"]');
    const matric   = form.querySelector('[name="matric_number"]');
    const email    = form.querySelector('[name="email"]');
    const phone    = form.querySelector('[name="phone"]');
    const password = form.querySelector('[name="password"]');
    const confirm  = form.querySelector('[name="confirm_password"]');

    [fullName, matric, email, phone, password, confirm].forEach(liveClear);

    form.addEventListener('submit', function (e) {
        let valid = true;

        // Full name
        if (!fullName || fullName.value.trim().length < 2) {
            showError(fullName, 'Please enter your full name (at least 2 characters).');
            valid = false;
        } else { clearError(fullName); }

        // Matric number — optional but must match format if filled
        const matricVal = matric ? matric.value.trim() : '';
        if (matricVal && !/^[A-Za-z]\d{9}$/.test(matricVal)) {
            showError(matric, 'Format: 1 letter + 9 digits, e.g. B032410001.');
            valid = false;
        } else { clearError(matric); }

        // Email — must be UTeM domain
        const emailVal = email ? email.value.trim().toLowerCase() : '';
        if (!emailVal) {
            showError(email, 'UTeM email address is required.');
            valid = false;
        } else if (!/@student\.utem\.edu\.my$|@utem\.edu\.my$/.test(emailVal)) {
            showError(email, 'Must end with @student.utem.edu.my or @utem.edu.my.');
            valid = false;
        } else { clearError(email); }

        // Phone — optional, basic format
        const phoneVal = phone ? phone.value.trim() : '';
        if (phoneVal && !/^[0-9+\-\s]{7,15}$/.test(phoneVal)) {
            showError(phone, 'Enter a valid phone number (e.g. 011-12345678).');
            valid = false;
        } else { clearError(phone); }

        // Password — 8–16 chars
        const pwVal = password ? password.value : '';
        if (pwVal.length < 8) {
            showError(password, 'Password must be at least 8 characters.');
            valid = false;
        } else if (pwVal.length > 16) {
            showError(password, 'Password must not exceed 16 characters.');
            valid = false;
        } else { clearError(password); }

        // Confirm password
        const cfVal = confirm ? confirm.value : '';
        if (cfVal !== pwVal) {
            showError(confirm, 'Passwords do not match.');
            valid = false;
        } else { clearError(confirm); }

        if (!valid) {
            e.preventDefault();
            const firstErr = form.querySelector('.input-error');
            if (firstErr) firstErr.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    });
})();

/* ─── Login form ───────────────────────────────────────────── */
(function () {
    const form = document.getElementById('loginForm');
    if (!form) return;

    const identifier = form.querySelector('[name="identifier"]');
    const password   = form.querySelector('[name="password"]');

    [identifier, password].forEach(liveClear);

    form.addEventListener('submit', function (e) {
        let valid = true;

        const idVal = identifier ? identifier.value.trim() : '';
        const isMatric = /^[A-Za-z]\d{9}$/i.test(idVal);
        const isEmail  = /@student\.utem\.edu\.my$|@utem\.edu\.my$/i.test(idVal);

        if (!idVal) {
            showError(identifier, 'Please enter your matric number or UTeM email.');
            valid = false;
        } else if (!isMatric && !isEmail) {
            showError(identifier, 'Enter a valid matric number (e.g. B032410001) or UTeM email.');
            valid = false;
        } else { clearError(identifier); }

        const pwVal = password ? password.value : '';
        if (!pwVal) {
            showError(password, 'Please enter your password.');
            valid = false;
        } else if (pwVal.length < 8) {
            showError(password, 'Password must be at least 8 characters.');
            valid = false;
        } else { clearError(password); }

        if (!valid) {
            e.preventDefault();
            const firstErr = form.querySelector('.input-error');
            if (firstErr) firstErr.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    });
})();