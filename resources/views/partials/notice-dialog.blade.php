<style>
    #notice-dialog { z-index:11000; }
    #notice-dialog .modal-dialog { width:calc(100% - 32px); max-width:380px; border-radius:16px; background:var(--surface,#fff); border:1px solid var(--border-card); box-shadow:0 20px 60px #1b43322b; }
    #notice-dialog .notice-content { padding:24px 24px 18px; }
    #notice-dialog .notice-mark { display:grid; place-items:center; width:38px; height:38px; margin-bottom:14px; color:var(--primary,#2d6a4f); background:var(--mint-surface,#e8f5e9); border-radius:12px; }
    #notice-dialog h2 { margin:0 0 8px; font-size:17px; color:var(--text-main); border:0; }
    #notice-dialog p { margin:0; font-size:13px; line-height:1.65; color:var(--text-muted); }
    #notice-dialog .notice-actions { display:flex; justify-content:flex-end; padding:0 24px 20px; }
    #notice-dialog .btn { min-height:44px; border-radius:999px; padding:8px 20px; }
</style>
<div id="notice-dialog" class="modal-overlay" role="alertdialog" aria-modal="true" aria-labelledby="notice-title" aria-describedby="notice-message" hidden>
    <div class="modal-dialog">
        <div class="notice-content">
            <div class="notice-mark" aria-hidden="true"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"/><path d="M12 7v6m0 3v1"/></svg></div>
            <h2 id="notice-title"></h2>
            <p id="notice-message"></p>
        </div>
        <div class="notice-actions"><button type="button" class="btn" id="notice-dismiss">Pilih produk</button></div>
    </div>
</div>
<script>
(function () {
    const dialog = document.getElementById('notice-dialog');
    const dismiss = document.getElementById('notice-dismiss');
    let returnFocus = null;
    let previousOverflow = '';
    function closeNotice() {
        if (dialog.hidden) return;
        dialog.classList.remove('open');
        dialog.hidden = true;
        document.body.style.overflow = previousOverflow;
        if (returnFocus?.isConnected) returnFocus.focus();
    }
    window.showNotice = function (title, message, focusTarget, actionLabel = 'Mengerti') {
        returnFocus = focusTarget || document.activeElement;
        if (dialog.hidden) previousOverflow = document.body.style.overflow;
        document.getElementById('notice-title').textContent = title;
        document.getElementById('notice-message').textContent = message;
        dismiss.textContent = actionLabel;
        dialog.hidden = false;
        dialog.classList.add('open');
        document.body.style.overflow = 'hidden';
        dismiss.focus();
    };
    dismiss.addEventListener('click', closeNotice);
    dialog.addEventListener('click', function (event) {
        if (event.target === dialog) { event.stopPropagation(); closeNotice(); }
    });
    document.addEventListener('keydown', function (event) {
        if (dialog.hidden) return;
        if (event.key === 'Escape') { event.preventDefault(); event.stopImmediatePropagation(); closeNotice(); }
        if (event.key === 'Tab') { event.preventDefault(); event.stopImmediatePropagation(); dismiss.focus(); }
    }, true);
    document.addEventListener('focusin', function (event) {
        if (!dialog.hidden && !dialog.contains(event.target)) dismiss.focus();
    });
})();
</script>
