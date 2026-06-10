(function(){
    const fileInput=document.getElementById('profileImage');
    if(!fileInput) return;
    const preview=document.getElementById('profilePreview');
    fileInput.addEventListener('change',()=>{
        const f=fileInput.files[0]; if(!f) return;
        if(f.size>2*1024*1024){alert('Image size must be 2MB or less.');
            fileInput.value=''; return;}
            const r=new FileReader();
            r.onload=e=>{ preview.src=e.target.result;};
            r.readAsDataURL(f);
        });

        const pwForm=document.getElementById('passwordForm');
        pwForm?.addEventListener('submit',e=>{
            const cur=pwForm.current_password.value;
            const np=pwForm.new_password.value;
            const cf=pwForm.confirm_password.value;
            const err=document.getElementById('pwError');
            err.textContent='';
            if(!cur||!np||!cf){e.preventDefault();err.textContent='All fields are required.';return;}
            if(np.length<8){e.preventDefault();err.textContent='New password must be at least 8 characters.';return;}
            if(np!==cf){e.preventDefault();err.textContent='Passwords do not match.';}
        });
    })();
