<script>
(()=>{
const form=document.getElementById('login-form'),loading=document.getElementById('login-loading');
if(!form||!loading)return;
const shell=form.closest?.('.login-shell')||form;
let pending=false;
form.addEventListener('submit',event=>{
    if(pending){event.preventDefault();return;}
    pending=true;loading.hidden=false;shell.inert=true;
    form.setAttribute('aria-busy','true');
});
window.addEventListener('pageshow',()=>{
    pending=false;loading.hidden=true;shell.inert=false;form.removeAttribute('aria-busy');
    const button=form.querySelector('.btn-submit');
    button.removeAttribute('aria-busy');button.textContent='Masuk';
});
})();
</script>
