<style>
.ui-skeleton{display:grid;gap:var(--ui-space-section);padding:var(--ui-space-panel);background:var(--ui-surface);color:var(--ui-ink-2);border-radius:var(--ui-radius);font-family:var(--font-body,system-ui);box-sizing:border-box;min-width:0}
.ui-skeleton-label{font-size:var(--ui-t-label)}
.ui-skeleton :is(i,.ui-skeleton-title){display:block;min-height:var(--ui-tap);border-radius:var(--ui-radius);background:var(--ui-head);animation:ui-skeleton-pulse var(--ui-skeleton-duration) ease-in-out infinite alternate}
.ui-skeleton-title{width:45%}
.ui-skeleton-cards{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:var(--ui-space-group)}
.ui-skeleton-cards i{height:var(--ui-skeleton-card-height)}
.ui-skeleton-table{display:grid;gap:var(--ui-space-control)}
.ui-skeleton-table i:nth-child(even){width:85%}
#login-loading{position:fixed;inset:0;z-index:var(--ui-loading-layer);overflow:auto;background:var(--ui-canvas);padding:var(--ui-space-panel)}
#login-loading[hidden]{display:none}
#login-loading .ui-skeleton{max-width:var(--ui-loading-width);margin:auto}
#workspace-loading{margin:var(--ui-space-panel)}
body:has(main.wrap[data-ready]) #workspace-loading{display:none}
@keyframes ui-skeleton-pulse{to{opacity:var(--ui-skeleton-opacity)}}
@media(prefers-reduced-motion:reduce){.ui-skeleton :is(i,.ui-skeleton-title){animation:none}}
</style>
