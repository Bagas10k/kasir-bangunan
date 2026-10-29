<style>
    /* Shared density for data-entry controls; checkbox and POS quantity sizing stay independent. */
    body .form-group { margin-bottom:12px; }
    body .form-group > label { font-size:11.5px; line-height:1.4; text-transform:none; letter-spacing:0; margin-bottom:5px; }
    body input.form-control:not([type="checkbox"]):not([type="radio"]),
    body select.form-control, body .select-shell > .select-trigger {
        min-height:36px !important; height:36px !important; padding:6px 10px !important;
        font-size:12.5px !important; line-height:1.4; border-radius:8px !important;
        box-sizing:border-box; max-width:100%; min-width:0;
    }
    body textarea.form-control { min-height:76px; font-size:12.5px; padding:8px 10px; }
    /* One solid focus boundary, without translucent halos or nested input rings. */
    body input:not([type="checkbox"]):not([type="radio"]):focus,
    body textarea:focus, body select:focus,
    body .select-trigger:focus, body .date-trigger:focus {
        box-shadow:none !important; outline:none !important;
        border-color:var(--primary,#2d6a4f) !important;
    }
    body .table-search:focus-within {
        box-shadow:none !important; outline:none !important;
        border-color:var(--primary,#2d6a4f) !important;
    }
    body .table-search input, body .table-search input:focus {
        border:0 !important; outline:none !important; box-shadow:none !important;
        background:transparent; min-width:0;
    }
    body .form-control:focus-visible, body .select-trigger:focus-visible,
    body .date-trigger:focus-visible {
        outline:1px solid var(--primary,#2d6a4f) !important; outline-offset:0 !important;
    }
    body .table-search:has(input:focus-visible) {
        outline:1px solid var(--primary,#2d6a4f) !important; outline-offset:0 !important;
    }
    body .table-search input:focus-visible { outline:none !important; }
    body .modal-body { padding:18px 20px; }
    body .master-modal-grid { gap:12px 14px; }
    body .adjustment-grid { gap:12px 14px; grid-template-columns:repeat(2,minmax(0,1fr)); }
    body .adjustment-content { max-width:880px; }
    body .adjustment-confirm { margin-top:14px; padding-top:14px; }
    body .adjustment-hint { font-size:11.5px; margin-top:8px; }
    .filter-disclosure { margin:0 0 14px; border:0; }
    .filter-disclosure > summary {
        display:flex; align-items:center; gap:8px; width:fit-content; min-height:44px;
        padding:0 12px; margin-bottom:-1px; border-bottom:2px solid transparent;
        color:var(--text-muted); font-size:12px; font-weight:700; cursor:pointer; list-style:none;
    }
    .filter-disclosure > summary::-webkit-details-marker { display:none; }
    .filter-disclosure > summary::after { content:'+'; font-size:17px; font-weight:400; }
    .filter-disclosure[open] > summary { color:var(--primary); border-bottom-color:var(--primary); }
    .filter-disclosure[open] > summary::after { content:'−'; }
    .filter-disclosure > summary:focus-visible { outline:2px solid var(--primary); outline-offset:-3px; border-radius:6px; }
    .filter-active-count { border-radius:999px; padding:2px 6px; background:var(--mint-surface); color:var(--primary-hover); font-size:10px; }
    body .filter-disclosure > :not(summary) {
        display:flex; flex-wrap:wrap; align-items:end; gap:10px; padding:12px 0;
        margin:0; border:0; border-radius:0; background:transparent; box-shadow:none;
    }
    body .filter-disclosure .form-group, body .filter-disclosure .master-control,
    body .filter-disclosure .table-search, body .filter-disclosure .catalog-filter-field {
        flex:1 1 145px; min-width:0; margin:0; max-width:260px;
    }
    body .filter-disclosure label { font-size:11px; text-transform:none; letter-spacing:0; }
    body .filter-disclosure .form-control, body .filter-disclosure .select-shell { width:100%; }
    body .filter-disclosure [class$="filter-actions"], body .filter-disclosure .master-toolbar-actions { display:flex; gap:6px; flex-wrap:wrap; }
    body .filter-disclosure .btn { min-height:36px; padding:6px 12px; font-size:12px; }
    body .filter-disclosure > summary {
        border:1px solid var(--border-control,#d9e2dc); border-radius:8px; min-height:36px;
        margin:0; padding:0 12px; background:var(--surface,#fff);
    }
    body .filter-disclosure[open] > summary { border-color:var(--primary); background:var(--mint-surface); }
    body .filter-disclosure > .filter-popup {
        position:fixed !important; z-index:9000; display:grid !important;
        grid-template-columns:repeat(2,minmax(0,1fr)) !important; gap:14px !important;
        grid-template-areas:none !important; grid-template-rows:none !important;
        grid-auto-flow:row; grid-auto-rows:auto; align-items:end;
        box-sizing:border-box; padding:18px !important; margin:0 !important;
        border:1px solid var(--border-control,#d9e2dc) !important; border-radius:12px !important;
        background:var(--surface,#fff) !important; box-shadow:0 12px 32px #1b43321f !important;
        overflow:auto; overscroll-behavior:contain;
    }
    body .filter-disclosure:not([open]) > .filter-popup { display:none !important; }
    body .filter-disclosure > .filter-popup > * {
        grid-area:auto !important; min-width:0; max-width:100%; box-sizing:border-box;
    }
    body .filter-popup .master-control, body .filter-popup .form-group,
    body .filter-popup .table-search, body .filter-popup .table-perpage {
        display:flex !important; flex-direction:column; align-items:stretch; gap:5px;
        max-width:none !important; min-width:0; width:100%; grid-area:auto !important;
    }
    body .filter-popup .table-search { flex-direction:row; align-items:center; width:auto; }
    body .filter-popup label { display:block; margin:0; }
    body .filter-popup .date-field, body .filter-popup .select-shell {
        min-width:0; max-width:100%; width:100%; box-sizing:border-box;
    }
    body .filter-popup .master-toolbar-actions, body .filter-popup [class$="filter-actions"],
    body .filter-disclosure > .filter-popup > .report-filter-form {
        grid-column:1 / -1 !important;
    }
    body .filter-popup .master-toolbar-actions, body .filter-popup [class$="filter-actions"] {
        justify-content:flex-end; padding-top:12px; border-top:1px solid var(--border-card);
    }
    @media(max-width:720px), (pointer:coarse) {
        body input.form-control:not([type="checkbox"]):not([type="radio"]),
        body select.form-control, body .select-shell > .select-trigger { min-height:44px !important; height:44px !important; font-size:14px !important; }
        body .filter-disclosure .btn { min-height:44px; }
        body .filter-disclosure > summary { min-height:44px; }
    }
    @media(max-width:720px) {
        body .filter-disclosure .form-group, body .filter-disclosure .master-control,
        body .filter-disclosure .table-search { flex-basis:calc(50% - 10px); max-width:none; }
        body .adjustment-grid { grid-template-columns:minmax(0,1fr); }
    }
    @media(max-width:380px) {
        body .filter-disclosure > .filter-popup { grid-template-columns:minmax(0,1fr) !important; }
        body .filter-disclosure .form-group, body .filter-disclosure .master-control { flex-basis:100%; }
    }
</style>
