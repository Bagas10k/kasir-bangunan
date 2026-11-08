@php
    $acc = ($metrics ?? [])['accounts'] ?? null;
@endphp
@if($acc)
    @include('partials.monitoring-cards', ['monitoringTask' => 'akun', 'monitoringTitle' => 'Ringkasan akun login', 'monitoringCards' => [
        ['Admin aktif', $acc['admin'], 'bisa mengelola semua menu', null],
        ['Kasir aktif', $acc['cashier'], 'hanya tugas operasional', null],
        ['Akun nonaktif', $acc['inactive'], 'tidak bisa masuk', null],
    ]])
@endif
<section class="card master-entity-card" id="login-accounts" aria-labelledby="h-accounts" data-accounts-url="{{ route('v2.accounts.index') }}" data-current-user="{{ auth()->id() }}">
<h2 id="h-accounts">Daftar akun login</h2>
<p class="note">Akun untuk masuk aplikasi, bukan data karyawan. Mengubah peran, status, atau password mengakhiri sesi akun itu. Akun tidak dihapus agar riwayat transaksi tetap utuh.</p>
<div class="ui-toolbar">
    <div class="ui-search" role="search">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
        <input type="search" id="account-search" aria-label="Cari akun" placeholder="Cari nama atau email" aria-controls="accounts-body" autocomplete="off">
    </div>
    <div class="ui-toolbar-actions">
        <button type="button" class="btn-import-master" id="account-refresh">Muat ulang</button>
        <button type="button" class="btn-add-master" id="account-create" data-open-popup="account-dialog" aria-haspopup="dialog" aria-controls="account-dialog">Tambah akun</button>
    </div>
</div>
<p id="account-message" class="ui-status-line" role="status" aria-live="polite"></p>
<div class="ui-table-wrap scroll master-table-scroll" tabindex="0" role="region" aria-label="Daftar akun login">
<table class="ui-table master-clean-table"><caption class="sr">Daftar akun login</caption><thead><tr><th scope="col">Nama</th><th scope="col">Email login</th><th scope="col">Peran</th><th scope="col">Status</th><th scope="col" class="ui-actions">Aksi</th></tr></thead><tbody id="accounts-body"><tr><td colspan="5" class="ui-empty">Memuat akun…</td></tr></tbody></table></div>
<dialog class="input-dialog uf-dialog" id="account-dialog" aria-labelledby="account-dialog-title">
<div class="popup-heading"><h2 id="account-dialog-title">Tambah akun login</h2><button type="button" class="popup-close secondary" data-close-dialog aria-label="Tutup formulir akun">×</button></div>
<div class="popup-content"><form id="account-form" class="uf-form" novalidate>
<input type="hidden" name="account_id" value="">
<div class="uf-section"><div class="uf-grid">
<div class="uf uf-w-3"><label class="uf-label" for="account-name">Nama pengguna<span class="uf-req" aria-hidden="true">*</span></label><input id="account-name" name="name" maxlength="255" autocomplete="name" required></div>
<div class="uf uf-w-3"><label class="uf-label" for="account-email">Email login<span class="uf-req" aria-hidden="true">*</span></label><input id="account-email" name="email" type="email" maxlength="255" autocomplete="off" placeholder="nama@toko.test" required></div>
<div class="uf uf-w-3"><label class="uf-label" for="account-role">Hak akses<span class="uf-req" aria-hidden="true">*</span></label><select id="account-role" name="role" required><option value="cashier">Kasir</option><option value="admin">Admin</option></select></div>
<div class="uf uf-w-3"><label class="uf-label" for="account-active">Status akun<span class="uf-req" aria-hidden="true">*</span></label><select id="account-active" name="is_active" required><option value="1">Aktif</option><option value="0">Nonaktif</option></select></div>
</div></div>
<div class="uf-section"><h3 class="uf-section-title">Password</h3><div class="uf-grid">
<div class="uf uf-w-3"><label class="uf-label" for="account-password">Password baru</label><input id="account-password" name="password" type="password" minlength="12" maxlength="128" autocomplete="new-password" placeholder="Minimal 12 karakter" aria-describedby="account-password-help"></div>
<div class="uf uf-w-3"><label class="uf-label" for="account-confirm">Ulangi password</label><input id="account-confirm" name="password_confirmation" type="password" minlength="12" maxlength="128" autocomplete="new-password"></div>
<p class="uf-w-6 uf-none" id="account-password-help">Saat mengubah akun, kosongkan bila password tidak diganti.</p>
</div></div>
<p id="account-self-note" class="note" hidden>Anda tidak dapat menonaktifkan akun sendiri atau melepas role Admin sendiri. Penggantian password mengharuskan login ulang.</p>
<p class="err" data-error role="alert" tabindex="-1" hidden></p>
<div class="uf-actions"><button type="button" class="secondary" data-close-dialog>Batal</button><button type="submit" class="uf-primary" id="account-save">Simpan akun</button></div>
</form></div></dialog>
</section>
<script>
(()=>{'use strict';
const root=document.querySelector('#login-accounts');if(!root)return;
const form=root.querySelector('#account-form'),dialog=root.querySelector('#account-dialog'),body=root.querySelector('#accounts-body'),message=root.querySelector('#account-message'),search=root.querySelector('#account-search'),error=form.querySelector('[data-error]'),save=root.querySelector('#account-save');let accounts=[],loaded=false;
const endpoint=root.dataset.accountsUrl;
function display(){body.replaceChildren();const q=search.value.trim().toLowerCase(),filtered=accounts.filter(a=>(a.name+' '+a.email).toLowerCase().includes(q));for(const a of filtered){const row=document.createElement('tr');row.dataset.accountId=a.id;const values=[a.name+(String(a.id)===root.dataset.currentUser?' (Anda)':''),a.email,a.role==='admin'?'Admin':a.role==='cashier'?'Kasir':'Belum ditetapkan',a.is_active?'Aktif':'Nonaktif'];values.forEach((v,i)=>{const td=document.createElement('td');td.dataset.label=['Nama','Email login','Peran','Status'][i];if(i>=2){const b=document.createElement('span');b.className='ui-badge ui-badge--'+(i===2?(a.role==='admin'?'info':'mute'):(a.is_active?'ok':'mute'));b.textContent=v;td.append(b);}else{td.textContent=v;if(i===0)td.className='ui-primary';}row.append(td);});const actions=document.createElement('td');actions.dataset.label='Aksi';actions.className='ui-actions';const button=document.createElement('button');button.type='button';button.className='btn-action-pill';button.textContent='Ubah akun';button.dataset.openPopup='account-dialog';button.setAttribute('aria-haspopup','dialog');button.setAttribute('aria-controls','account-dialog');button.addEventListener('click',()=>edit(a));actions.append(button);row.append(actions);body.append(row);}if(!filtered.length){const row=body.insertRow(),cell=row.insertCell();cell.colSpan=5;cell.className='ui-empty';cell.textContent=accounts.length?'Tidak ada akun sesuai pencarian.':'Belum ada akun.';}message.textContent=filtered.length+' dari '+accounts.length+' akun.';}
async function load(){try{root.setAttribute('aria-busy','true');const res=await fetch(endpoint,{credentials:'same-origin',headers:{Accept:'application/json'},cache:'no-store'});const data=await res.json();if(!res.ok)throw Error(data.message||'Daftar akun tidak dapat dimuat.');accounts=data.accounts;loaded=true;display();}catch(e){message.textContent=e.message;}finally{root.removeAttribute('aria-busy');}}
function edit(a=null){form.reset();error.hidden=true;form.elements.account_id.value=a?.id||'';form.elements.name.value=a?.name||'';form.elements.email.value=a?.email||'';form.elements.role.value=a?.role||'cashier';form.elements.is_active.value=a?a.is_active?'1':'0':'1';form.elements.password.required=!a;form.elements.password_confirmation.required=!a;const self=a&&String(a.id)===root.dataset.currentUser;form.elements.role.disabled=!!self;form.elements.is_active.disabled=!!self;form.elements.role.dispatchEvent(new Event('change',{bubbles:true}));form.elements.is_active.dispatchEvent(new Event('change',{bubbles:true}));root.querySelector('#account-self-note').hidden=!self;root.querySelector('#account-dialog-title').textContent=a?'Ubah akun login':'Tambah akun login';}
root.querySelector('#account-create').addEventListener('click',()=>edit());root.querySelector('#account-refresh').addEventListener('click',load);search.addEventListener('input',display);
form.addEventListener('submit',async e=>{e.preventDefault();if(form.dataset.busy)return;error.hidden=true;if(!form.reportValidity())return;const id=form.elements.account_id.value,payload={name:form.elements.name.value.trim(),email:form.elements.email.value.trim(),role:form.elements.role.value,is_active:form.elements.is_active.value==='1'};if(form.elements.password.value||!id){payload.password=form.elements.password.value;payload.password_confirmation=form.elements.password_confirmation.value;}if(payload.password!==payload.password_confirmation){error.textContent='Konfirmasi password belum sama.';error.hidden=false;error.focus();return;}form.dataset.busy='1';save.disabled=true;try{const res=await fetch(id?endpoint+'/'+encodeURIComponent(id):endpoint,{method:id?'PUT':'POST',credentials:'same-origin',headers:{'Content-Type':'application/json',Accept:'application/json','X-CSRF-TOKEN':document.querySelector('meta[name=csrf-token]').content},body:JSON.stringify(payload)});const data=await res.json().catch(()=>({}));if(!res.ok)throw Error(Object.values(data.errors||{}).flat().join(' ')||data.message||'Akun tidak dapat disimpan.');form.elements.password.value='';form.elements.password_confirmation.value='';dialog.close();dialog._openerTrigger?.focus({preventScroll:true});if(String(id)===root.dataset.currentUser&&payload.password){location.href=@json(route('login'));return;}await load();message.textContent='Akun berhasil disimpan. '+message.textContent;}catch(e){error.textContent=e.message;error.hidden=false;error.focus();}finally{delete form.dataset.busy;save.disabled=false;}});
dialog.addEventListener('close',()=>{form.elements.password.value='';form.elements.password_confirmation.value='';});
document.querySelector('[data-task=akun]')?.addEventListener('click',()=>{if(!loaded)load();});const watcher=new MutationObserver(()=>{if(document.body.dataset.activeTask==='akun'&&!loaded)load();});watcher.observe(document.body,{attributes:true,attributeFilter:['data-active-task']});if(location.hash==='#akun')load();
})();
</script>
