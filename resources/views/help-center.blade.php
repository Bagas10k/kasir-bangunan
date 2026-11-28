<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $current['title'] ?? 'Pusat Bantuan' }} — Kasir Bangunan</title>
    @include('v2.ui-tokens')
    @include('v2.ui-dna-tokens')
    @include('v2.ui-font-tokens')
    <link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap" rel="stylesheet">
    <style>
        *{box-sizing:border-box}
        body{margin:0;background:var(--dna-canvas);color:var(--ui-ink);font:var(--ui-t-body)/var(--ui-leading-body) var(--font-body);-webkit-font-smoothing:antialiased;text-rendering:optimizeLegibility}
        a{color:var(--ui-green);text-underline-offset:var(--ui-space-tight)}
        :focus-visible{outline:var(--ui-stroke) solid var(--ui-focus);outline-offset:var(--ui-space-tight)}
        h1,h2,h3,p{margin:0}h1,h2,h3{font-family:var(--font-display);letter-spacing:-.01em;text-wrap:balance}p,li,dd{text-wrap:pretty}
        h1{font-size:var(--ui-h1);line-height:var(--ui-leading-tight)}
        h2{font-size:var(--ui-h2);line-height:var(--ui-leading-tight)}
        h3{font-size:var(--ui-t-body)}
        .hc-shell{max-width:var(--ui-help-page-width);margin-inline:auto;padding:var(--ui-space-section) clamp(var(--ui-space-section),3vw,calc(var(--ui-space-panel) * 2))}
        .hc-header{border-bottom:var(--ui-stroke) solid var(--dna-line);color:var(--ui-ink)}
        .hc-header .hc-shell,.hc-actions,.hc-topics{display:flex;align-items:center;gap:var(--ui-space-group);flex-wrap:wrap}
        .hc-header .hc-shell{justify-content:space-between}
        .hc-header a{color:inherit}
        .hc-header .hc-brand{display:flex;align-items:center;gap:var(--ui-space-group);color:var(--ui-green);font-weight:var(--ui-weight-strong);text-decoration:none}
        .hc-brand small{display:block;color:var(--ui-ink-2);font-size:var(--ui-t-caption);font-weight:var(--ui-weight-medium)}
        .hc-header .hc-back{padding-inline:var(--ui-space-group);border:var(--ui-stroke) solid var(--dna-line);border-radius:var(--dna-r-ctl);background:var(--dna-card);color:var(--ui-green);font-size:var(--ui-t-label);font-weight:var(--ui-weight-medium);text-decoration:none}.hc-header .hc-back:hover{border-color:var(--ui-green);background:var(--ui-green-soft)}
        .hc-search-area{margin-bottom:var(--ui-space-section)}
        .hc-search-label{display:flex;align-items:center;gap:var(--ui-space-tight);margin-bottom:var(--ui-space-control);font-size:var(--ui-t-label);font-weight:var(--ui-weight-strong)}
        .hc-search{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:var(--ui-space-control);max-width:var(--ui-help-reading-width)}
        .hc-search input{width:100%;min-width:0;min-height:var(--ui-tap);padding-inline:var(--ui-space-group);border:var(--ui-stroke) solid var(--ui-line);border-radius:var(--ui-radius);font:inherit;color:var(--ui-ink);background:var(--ui-surface)}
        .hc-button{display:inline-flex;align-items:center;justify-content:center;min-height:var(--ui-tap);padding-inline:var(--ui-space-group);border:var(--ui-stroke) solid var(--ui-green);border-radius:var(--ui-radius);background:var(--ui-green);color:var(--ui-surface);font:inherit;font-weight:var(--ui-weight-medium);text-decoration:none;cursor:pointer}
        .hc-button:hover{background:var(--ui-green-2)}
        .hc-layout{display:grid;grid-template-columns:minmax(0,var(--ui-help-nav-width)) minmax(0,1fr);grid-template-areas:"modules content";align-items:start;gap:var(--ui-space-panel)}
        /* Kartu mengikuti DNA dashboard: putih, garis tipis, radius 12, bayangan lembut. */
        .hc-card,.hc-nav,.hc-article,.hc-results{background:var(--dna-card);border:var(--ui-stroke) solid var(--dna-line);border-radius:var(--dna-r-card);box-shadow:var(--dna-shadow)}
        .hc-onpage{display:none}.hc-onpage-title{margin-bottom:var(--ui-space-control);font-size:var(--ui-t-caption);font-weight:var(--ui-weight-strong);color:var(--ui-ink-2)}.hc-onpage ul{list-style:none;margin:0;padding:0;border-left:var(--ui-stroke) solid var(--dna-line)}.hc-onpage a{display:flex;align-items:center;min-height:var(--ui-tap);margin-left:calc(var(--ui-stroke) * -1);padding-inline:var(--ui-space-group) var(--ui-space-control);border-left:calc(var(--ui-stroke) * 2) solid transparent;color:var(--ui-ink-2);font-size:var(--ui-t-label);line-height:var(--ui-leading-tight);text-decoration:none}.hc-onpage a:hover{color:var(--ui-green)}.hc-onpage a[aria-current]{border-left-color:var(--ui-green);color:var(--ui-green);font-weight:var(--ui-weight-strong)}
        .hc-nav{grid-area:modules;position:sticky;top:var(--ui-space-section);display:flex;flex-direction:column;max-height:calc(100dvh - var(--ui-space-panel));padding:var(--ui-space-group) var(--ui-space-control)}.hc-nav>.hc-search-area{margin:0 var(--ui-space-tight) var(--ui-space-group);padding-bottom:var(--ui-space-group);border-bottom:var(--ui-stroke) solid var(--dna-line-2)}.hc-nav-modules{min-height:0;overflow:auto;overscroll-behavior:contain}
        /* Scrollbar minimalis: tipis, tersembunyi saat diam, abu muda saat disentuh. Tetap scrollbar bawaan browser. */
        .hc-nav-modules,.hc-suggest{scrollbar-width:thin;scrollbar-color:transparent transparent;scrollbar-gutter:stable}
        .hc-nav-modules:is(:hover,:focus-within),.hc-suggest:hover{scrollbar-color:var(--dna-line) transparent}
        .hc-nav-modules::-webkit-scrollbar,.hc-suggest::-webkit-scrollbar{width:calc(var(--ui-space-tight) * 1.5)}
        .hc-nav-modules::-webkit-scrollbar-thumb,.hc-suggest::-webkit-scrollbar-thumb{border-radius:var(--dna-r-ctl);background:transparent}
        .hc-nav-modules:is(:hover,:focus-within)::-webkit-scrollbar-thumb,.hc-suggest:hover::-webkit-scrollbar-thumb{background:var(--dna-line)}
        .hc-nav-modules::-webkit-scrollbar-thumb:hover{background:var(--ui-line)}
        @media(forced-colors:active){.hc-nav-modules,.hc-suggest{scrollbar-color:auto}}
        .hc-nav-title{padding:var(--ui-space-tight) var(--ui-space-group) var(--ui-space-control);font-size:var(--ui-t-caption);font-weight:var(--ui-weight-medium);color:var(--ui-ink-2)}
        /* Submenu meniru sidebar POS: grup berikon + panah, anak bercabang garis pohon. */
        .hc-nav details+details{margin-top:var(--ui-space-tight)}
        .hc-nav summary{display:flex;align-items:center;gap:var(--ui-space-group);min-height:var(--ui-tap);padding:0 var(--ui-space-group);border-radius:var(--dna-r-card);cursor:pointer;list-style:none;font-size:var(--ui-t-caption);font-weight:var(--ui-weight-strong);color:var(--ui-ink)}
        .hc-nav summary::-webkit-details-marker{display:none}
        .hc-nav summary:hover{background:var(--dna-hover)}
        .hc-nav summary svg{flex:none;color:var(--ui-ink-2)}
        .hc-nav summary::after{content:"";flex:none;width:var(--ui-space-control);height:var(--ui-space-control);margin-inline-start:auto;border-right:calc(var(--ui-stroke) * 1.6) solid currentColor;border-bottom:calc(var(--ui-stroke) * 1.6) solid currentColor;opacity:.55;transform:rotate(-45deg);transition:transform var(--ui-sidebar-text-duration) ease}
        .hc-nav details[open]>summary::after{transform:rotate(45deg)}
        .hc-nav details:has([aria-current])>summary,.hc-nav details:has([aria-current])>summary svg{color:var(--ui-green)}
        .hc-submenu{display:flex;flex-direction:column;gap:calc(var(--ui-stroke) * 2);margin:calc(var(--ui-stroke) * 2) 0 var(--ui-space-control)}
        .hc-nav a,.hc-header a{display:flex;align-items:center;min-height:var(--ui-tap)}
        .hc-submenu a{position:relative;margin-inline-start:calc(var(--ui-space-panel) + var(--ui-space-section));padding:var(--ui-space-control) var(--ui-space-group);border-radius:var(--dna-r-card);font-size:var(--ui-t-label);font-weight:var(--ui-weight-medium);line-height:var(--ui-leading-tight);color:var(--ui-ink);text-decoration:none}
        .hc-submenu a::before{content:"";position:absolute;left:calc(var(--ui-space-section) * -1);top:calc(-50% - calc(var(--ui-stroke) * 2));width:var(--ui-space-section);height:calc(100% + calc(var(--ui-stroke) * 2));border-left:var(--ui-stroke) solid var(--ui-line);border-bottom:var(--ui-stroke) solid var(--ui-line);border-radius:0 0 0 var(--dna-r-card);pointer-events:none}
        /* Cabang pertama mulai dari tepi atas submenu, tidak menembus judul grup. */
        .hc-submenu a:first-child::before{top:0;height:50%}
        .hc-submenu a:hover{background:var(--dna-hover)}
        .hc-submenu a[aria-current]{background:var(--ui-green-soft);color:var(--ui-green);font-weight:var(--ui-weight-strong)}
        .hc-panel{grid-area:content;min-width:0}
        .hc-card,.hc-article,.hc-results{padding:clamp(var(--ui-space-section),2.5vw,calc(var(--ui-space-panel) * 1.25))}
        .hc-card+.hc-card,.hc-hero+.hc-card{margin-top:var(--ui-space-section)}.hc-article>section{margin-top:var(--ui-space-panel);padding-top:var(--ui-space-panel);border-top:var(--ui-stroke) solid var(--dna-line-2)}
        .hc-card-title{font-size:var(--dna-card-title);line-height:var(--ui-leading-tight);font-weight:var(--ui-weight-strong);color:var(--ui-ink)}
        .hc-card-sub{margin-top:calc(var(--ui-stroke) * 2);color:var(--ui-ink-2);font-size:var(--ui-t-caption)}
        .hc-modules-jump{display:none}
        .hc-reading{min-width:0;overflow-wrap:break-word}
        .hc-muted,.hc-crumb{color:var(--ui-ink-2);font-size:var(--ui-t-label)}
        .hc-crumb{display:flex;align-items:center;gap:var(--ui-space-control);margin-bottom:var(--ui-space-control)}
        .hc-crumb a{display:inline-flex;align-items:center;min-height:var(--ui-tap)}
        .hc-intro,.hc-actions,.hc-panel p+ p{margin-top:var(--ui-space-group)}
        .hc-intro{max-width:var(--ui-help-reading-width);color:var(--ui-ink-2);font-size:calc(var(--ui-t-body) * 1.0625);line-height:var(--ui-leading-body)}h2.hc-article-title{font-size:var(--ui-h1)}
        .hc-hero{padding:clamp(var(--ui-space-section),3vw,calc(var(--ui-space-panel) * 1.5));border:var(--ui-stroke) solid var(--ui-green-2);border-radius:var(--dna-r-card);box-shadow:var(--dna-shadow)}
        .hc-hero{display:grid;align-items:center;gap:var(--ui-space-panel);background:var(--ui-green-2);color:var(--ui-surface);border-color:var(--ui-green-2)}
        .hc-hero h1{margin-top:var(--ui-space-tight);font-size:var(--ui-help-title-size)}
        .hc-hero .hc-kicker{color:var(--ui-lime)}
        .hc-hero .hc-intro{color:var(--ui-surface)}
        .hc-hero .hc-search-area{margin:var(--ui-space-panel) 0 0}
        .hc-hero .hc-search .hc-button{background:var(--ui-lime);color:var(--ui-green-2);border-color:var(--ui-lime)}
        .hc-hero .hc-search .hc-button:hover{background:var(--ui-green-soft)}
        .hc-hero :focus-visible{outline-color:var(--ui-lime)}
        .hc-quick{padding:var(--ui-space-group);background:var(--ui-green);border:var(--ui-stroke) solid var(--ui-green-soft);border-radius:var(--ui-radius)}
        .hc-quick>h2{padding:var(--ui-space-tight) var(--ui-space-control);color:var(--ui-lime);font-size:var(--ui-t-label)}
        .hc-quick ul{list-style:none;margin:var(--ui-space-control) 0 0;padding:0}
        .hc-quick li+li{border-top:var(--ui-stroke) solid var(--ui-green-soft)}
        .hc-quick a{display:flex;align-items:center;justify-content:space-between;gap:var(--ui-space-group);min-height:var(--ui-tap);padding:var(--ui-space-control);color:var(--ui-surface);font-size:var(--ui-t-label);text-decoration:none}
        .hc-quick a:hover{text-decoration:underline}
        .hc-article-hero{display:flex;align-items:flex-end;justify-content:space-between;flex-wrap:wrap;gap:var(--ui-space-group) var(--ui-space-panel)}
        .hc-article-overview{flex:1 1 var(--ui-detail-column-min);min-width:0}
        .hc-article-hero .hc-actions{margin:0}
        .hc-kicker{color:var(--ui-green);font-size:var(--ui-t-caption);font-weight:var(--ui-weight-strong)}
        .hc-meta{display:flex;flex-wrap:wrap;gap:var(--ui-space-control) var(--ui-space-group);margin-top:var(--ui-space-section)}
        .hc-meta span{padding:var(--ui-space-tight) var(--ui-space-control);border-radius:var(--dna-r-ctl);background:var(--ui-green-soft);color:var(--ui-green);font-size:var(--ui-t-caption);font-weight:var(--ui-weight-strong)}
        .hc-actions .hc-button{gap:var(--ui-space-control)}
        .hc-panel footer{margin-top:var(--ui-space-section)}
        .hc-panel :is(section,article){scroll-margin-top:var(--ui-space-section)}
        .hc-article+.hc-article{margin-top:calc(var(--ui-space-panel) * 2)}
        .hc-article-title{font-size:var(--ui-help-title-size);line-height:var(--ui-leading-tight);color:var(--ui-green-2)}
        .hc-article>section :is(h2,h3){font-size:var(--dna-card-title);line-height:var(--ui-leading-tight)}
        .hc-topics a:hover{background:var(--ui-green-soft)}
        .hc-section-head{display:flex;align-items:baseline;flex-wrap:wrap;gap:var(--ui-space-tight) var(--ui-space-group);padding-bottom:var(--ui-space-group);border-bottom:var(--ui-stroke) solid var(--dna-line-2)}
        .hc-section-count{color:var(--ui-green);font-size:var(--ui-t-caption);font-weight:var(--ui-weight-medium)}
        .hc-guide-body{margin-top:var(--ui-space-section)}
        /* Penanda teks panduan (App\Support\HelpText). */
        .hc-ref{padding:0 var(--ui-space-tight);border-radius:var(--dna-r-ctl);background:var(--ui-green-soft);color:var(--ui-green);font-weight:var(--ui-weight-medium);text-decoration:none;box-decoration-break:clone;-webkit-box-decoration-break:clone}
        .hc-ref:hover{text-decoration:underline}
        .hc-important{padding:0 var(--ui-space-tight);border-radius:var(--dna-r-ctl);background:var(--ui-warn-bg);color:var(--ui-warn-ink);box-decoration-break:clone;-webkit-box-decoration-break:clone}
        .hc-term{font-style:italic}
        .hc-optional{opacity:.78}.hc-details p{max-width:var(--ui-help-reading-width);margin-top:var(--ui-space-group);line-height:calc(var(--ui-leading-body) + .1);color:var(--ui-ink)}
        .hc-steps{list-style:none;max-width:var(--ui-help-reading-width);padding:0;margin:0;counter-reset:help-step}
        .hc-steps li{display:grid;grid-template-columns:auto minmax(0,1fr);gap:var(--ui-space-group);align-items:start;padding-block:var(--ui-space-group);counter-increment:help-step}
        .hc-steps li:first-child{padding-top:0}
        .hc-steps li+li{border-top:var(--ui-stroke) solid var(--ui-line-soft)}
        .hc-steps li::before{content:counter(help-step);display:grid;place-items:center;width:var(--ui-space-panel);aspect-ratio:1;color:var(--ui-surface);background:var(--ui-green);border-radius:var(--ui-radius);font-size:var(--ui-t-caption);font-weight:var(--ui-weight-strong)}
        .hc-figure+.hc-steps{margin-top:var(--ui-space-section)}
        .hc-faq{display:grid;align-items:start;gap:0;max-width:var(--ui-help-reading-width);margin:var(--ui-space-control) 0 0}
        .hc-faq-item+.hc-faq-item{border-top:var(--ui-stroke) solid var(--dna-line-2)}
        .hc-faq-item[open] summary{color:var(--ui-green)}
        .hc-faq-item summary{display:flex;align-items:center;justify-content:space-between;gap:var(--ui-space-group);min-height:var(--ui-tap);padding:var(--ui-space-group) 0;font-weight:var(--ui-weight-strong);cursor:pointer;list-style:none}
        .hc-faq-item summary::-webkit-details-marker{display:none}
        .hc-faq-item summary::after{content:"";flex:none;width:var(--ui-space-control);height:var(--ui-space-control);border-right:calc(var(--ui-stroke) * 2) solid var(--ui-green);border-bottom:calc(var(--ui-stroke) * 2) solid var(--ui-green);transform:rotate(45deg);transition:transform var(--ui-sidebar-text-duration) ease}
        .hc-faq-item[open] summary::after{transform:rotate(-135deg)}
        .hc-faq-item summary:hover{color:var(--ui-green)}
        .hc-faq-item p{max-width:var(--ui-help-reading-width);padding:0 0 var(--ui-space-section);color:var(--ui-ink-2);line-height:var(--ui-leading-body)}
        .hc-button--ghost{background:var(--ui-surface);color:var(--ui-green)}
        .hc-button--ghost:hover{background:var(--ui-green-soft)}
        .hc-tool[hidden]{display:none}
        .hc-search{position:relative}
        .hc-suggest{position:absolute;inset-inline:0;top:calc(100% + var(--ui-space-tight));z-index:var(--ui-cart-layer);max-height:var(--ui-restock-list-max);overflow:auto;margin:0;padding:var(--ui-space-tight);list-style:none;background:var(--ui-surface);color:var(--ui-ink);border:var(--ui-stroke) solid var(--ui-line);border-radius:var(--ui-radius);animation:var(--ui-float-in, none)}
        .hc-suggest[hidden]{display:none}
        .hc-suggest li{min-height:var(--ui-tap);padding:var(--ui-space-control) var(--ui-space-group);border-radius:var(--ui-radius);cursor:pointer}
        .hc-suggest li[aria-selected="true"],.hc-suggest li:hover{background:var(--ui-green-soft)}
        .hc-suggest strong{display:block;font-size:var(--ui-t-label)}
        .hc-suggest small{display:block;color:var(--ui-ink-2);font-size:var(--ui-t-caption)}
        .hc-suggest .hc-suggest-all{border-top:var(--ui-stroke) solid var(--ui-line-soft);border-radius:0 0 var(--ui-radius) var(--ui-radius);color:var(--ui-green);font-weight:var(--ui-weight-strong);font-size:var(--ui-t-label)}
        .hc-suggest .hc-suggest-empty{cursor:default;color:var(--ui-ink-2);font-size:var(--ui-t-label)}
        @media(prefers-reduced-motion:reduce){.hc-faq-item summary::after{transition:none}.hc-suggest{animation:none}}
        .hc-figure{margin:0;padding:var(--ui-space-group);border:var(--ui-stroke) solid var(--dna-line);border-radius:var(--dna-r-card);background:var(--dna-panel)}
        .hc-figure-label{margin-bottom:var(--ui-space-control);color:var(--ui-green);font-size:var(--ui-t-caption);font-weight:var(--ui-weight-strong)}
        .hc-figure figcaption{padding:var(--ui-space-group) var(--ui-space-tight) var(--ui-space-tight);color:var(--ui-ink-2);font-size:var(--ui-t-label);line-height:var(--ui-leading-body)}
        .hc-figure-kind{display:block;margin-top:var(--ui-space-tight);font-size:var(--ui-t-caption)}
        .hc-mock{overflow:hidden;border:var(--ui-stroke) solid var(--ui-line-soft);border-radius:var(--ui-radius);background:var(--ui-surface)}
        .hc-mock-top,.hc-mock-line{display:flex;align-items:center;justify-content:space-between;gap:var(--ui-space-control)}
        .hc-mock-top{padding:var(--ui-space-control) var(--ui-space-group);background:var(--ui-green-2)}
        .hc-mock-top span{height:var(--ui-space-control);width:20%;border-radius:var(--ui-radius);background:var(--ui-line)}
        .hc-mock-top span+span{width:10%}
        .hc-mock-layout{display:grid;grid-template-columns:var(--ui-tap) minmax(0,1fr)}
        .hc-mock-rail{display:grid;align-content:start;gap:var(--ui-space-control);padding:var(--ui-space-control);background:var(--ui-canvas)}
        .hc-mock-rail span,.hc-mock-bar,.hc-mock-chip{display:block;border-radius:var(--ui-radius);background:var(--ui-mute-bg)}
        .hc-mock-rail span{aspect-ratio:1}
        .hc-mock-content{display:grid;gap:var(--ui-space-control);min-width:0;padding:var(--ui-space-control)}
        .hc-mock-line{min-height:var(--ui-space-panel)}
        .hc-mock-bar{flex:0 1 42%;height:var(--ui-space-control)}
        .hc-mock-chip{flex:0 1 24%;height:var(--ui-space-section)}
        .hc-mock-panel{padding:var(--ui-space-control);border:var(--ui-stroke) solid var(--ui-line-soft);border-radius:var(--ui-radius)}
        .hc-mock-panel .hc-mock-line+.hc-mock-line{border-top:var(--ui-stroke) solid var(--ui-line-soft)}
        .hc-mock-focus{padding:var(--ui-space-tight) var(--ui-space-control);border:var(--ui-stroke) solid var(--ui-green);border-radius:var(--ui-radius);background:var(--ui-lime);color:var(--ui-green-2);font-size:var(--ui-t-label);line-height:var(--ui-leading-tight);text-align:center}
        .hc-mock-footer{justify-content:flex-end}
        .hc-mock-tabs,.hc-mock-field{justify-content:flex-start}
        .hc-mock-field{padding-block:var(--ui-space-tight)}
        .hc-list{list-style:none;margin:var(--ui-space-group) 0 0;padding:0}
        .hc-list li+li{border-top:var(--ui-stroke) solid var(--ui-line-soft)}
        .hc-list a{display:block;min-height:var(--ui-tap);padding-block:var(--ui-space-group);text-decoration:none}
        .hc-list a:hover strong{text-decoration:underline}
        .hc-list span{display:block;margin-top:var(--ui-space-tight);color:var(--ui-ink-2);font-size:var(--ui-t-label)}
        .hc-module-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,var(--ui-detail-column-min)),1fr));gap:var(--ui-space-control);list-style:none;margin:var(--ui-space-group) 0 0;padding:0}
        .hc-module-card{display:flex;align-items:flex-start;justify-content:space-between;gap:var(--ui-space-control);min-height:var(--ui-tap);height:100%;padding:var(--ui-space-section);border:var(--ui-stroke) solid var(--dna-line);border-radius:var(--dna-r-card);background:var(--dna-panel);text-decoration:none;color:var(--ui-green)}
        .hc-module-card:hover{background:var(--ui-green-soft);border-color:var(--ui-green)}
        .hc-module-card small{display:block;margin-top:var(--ui-space-control);color:var(--ui-ink-2);font-size:var(--ui-t-caption)}
        .hc-module-count{display:inline-block;margin-top:var(--ui-space-control);padding:var(--ui-space-tight) var(--ui-space-control);border-radius:var(--ui-radius);background:var(--ui-head);font-size:var(--ui-t-caption);font-weight:var(--ui-weight-medium)}
        .hc-module-card>svg{flex:none}
        .hc-topics{gap:var(--ui-space-control);margin-top:var(--ui-space-control)}
        .hc-topics a,.hc-modules-jump{align-items:center;min-height:var(--ui-tap);padding-inline:var(--ui-space-group);border:var(--ui-stroke) solid var(--ui-line);border-radius:var(--ui-radius);background:var(--ui-surface);font-size:var(--ui-t-label);text-decoration:none}
        .hc-topics a{display:inline-flex}
        .hc-empty{margin-top:var(--ui-space-section);padding:var(--ui-space-section);background:var(--dna-panel);border:var(--ui-stroke) solid var(--dna-line);border-radius:var(--dna-r-card)}
        .hc-panel footer{padding-top:var(--ui-space-section);border-top:var(--ui-stroke) solid var(--ui-line-soft)}
        @media(min-width:1280px){
            .hc-layout:has(.hc-onpage){grid-template-columns:minmax(0,var(--ui-help-nav-width)) minmax(0,1fr) var(--ui-help-toc-width);grid-template-areas:"modules content onpage"}
            .hc-onpage{display:block;grid-area:onpage;position:sticky;top:var(--ui-space-section);padding-top:var(--ui-space-control)}
        }
        @media(min-width:1060px){
            .hc-hero{grid-template-columns:minmax(0,1.4fr) minmax(0,1fr)}
            .hc-guide-body--illustrated{display:grid;grid-template-columns:minmax(0,1.15fr) minmax(0,1fr);align-items:start;gap:var(--ui-space-panel)}
            .hc-guide-body--illustrated .hc-figure{grid-area:1/2}
            .hc-guide-body--illustrated .hc-steps{grid-area:1/1;margin:0}
            .hc-mock-line{min-height:calc(var(--ui-space-section) * 2)}
        }
        @media(max-width:760px){
            .hc-layout{grid-template-columns:minmax(0,1fr);grid-template-areas:"content" "modules";gap:var(--ui-space-section)}
            .hc-nav{position:static;max-height:none}.hc-nav-modules{overflow:visible}
            .hc-modules-jump{display:inline-flex;margin-bottom:var(--ui-space-control)}
            .hc-panel footer .hc-modules-jump{margin-block:var(--ui-space-control) 0}
            .hc-header .hc-shell{gap:var(--ui-space-control)}
            .hc-hero{padding:var(--ui-space-section)}
            .hc-mock-layout{grid-template-columns:minmax(0,1fr)}
            .hc-mock-rail{display:none}
        }
        @media(prefers-reduced-motion:no-preference){html{scroll-behavior:smooth}}
        @media print{body{background:var(--ui-surface)}.hc-card,.hc-article,.hc-article>section,.hc-results{box-shadow:none;border:0;padding:0}.hc-header,.hc-search-area,.hc-modules-jump,.hc-nav,.hc-onpage,.hc-actions,.hc-crumb,.hc-related,.hc-quick,.hc-suggest,body:not(.hc-print-one) .hc-article:not(.is-current),body.hc-print-one .hc-article:not(.is-print-target){display:none}.hc-layout,.hc-guide-body--illustrated,.hc-faq{display:block}.hc-panel,.hc-article+.hc-article,.hc-article-hero,.hc-hero{margin:0;padding:0;border:0}.hc-hero{background:none;color:var(--ui-ink)}.hc-hero :is(.hc-kicker,.hc-intro){color:inherit}.hc-faq-item,.hc-steps li,.hc-figure{break-inside:avoid}.hc-faq-item+.hc-faq-item{margin-top:var(--ui-space-control)}.hc-faq-item summary::after{display:none}}
    </style>
</head>
<body>
    @php($isHome = ! $current && $query === '')
    @php($moduleEntries = $current ? [$article => $current['title']] + $groups[$current['group']] : [])
    @php($moduleIcons = [
        'Mulai di sini' => 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18zM12 16v-4M12 8h.01',
        'Operasional' => 'M3 9 5 4h14l2 5M3 9h18M3 9v11h18V9M9 20v-6h6v6',
        'Stok & pembelian' => 'M21 8 12 3 3 8v8l9 5 9-5zM3 8l9 5 9-5M12 13v8',
        'Master data' => 'M4 6c0-3.5 16-3.5 16 0v12c0 3.5-16 3.5-16 0zM4 6c0 3.5 16 3.5 16 0M4 12c0 3.5 16 3.5 16 0',
        'Keuangan' => 'M5 4.5A1.5 1.5 0 0 1 6.5 3H20v15H6.5A1.5 1.5 0 0 0 5 19.5zM5 19.5A1.5 1.5 0 0 0 6.5 21H20M9 7h7M9 11h5',
    ])
    <header class="hc-header">
        <div class="hc-shell">
            <a class="hc-brand" href="{{ route('help.index') }}">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 6v15M3 4h5a4 4 0 0 1 4 4 4 4 0 0 1 4-4h5v15h-5a4 4 0 0 0-4 2 4 4 0 0 0-4-2H3z"/></svg>
                <span>Pusat Bantuan<small>Kasir Bangunan</small></span>
            </a>
            <a class="hc-back" href="{{ $workspace }}">Kembali ke POS</a>
        </div>
    </header>
    <div class="hc-shell">
        <a class="hc-modules-jump" href="{{ $isHome ? '#jelajahi-modul' : '#modul-panduan' }}">{{ $isHome ? 'Lihat modul panduan' : 'Cari atau pilih panduan lain' }}</a>
        <div class="hc-layout">
            <main class="hc-panel" id="isi-panduan">
                <div class="hc-reading">
                    @if ($current)
                        <nav class="hc-crumb" aria-label="Lokasi artikel"><a href="{{ route('help.index') }}">Panduan</a><span aria-hidden="true">›</span><span>{{ $current['group'] }}</span></nav>
                        @foreach ($moduleEntries as $slug => $title)
                            @include('partials.help-article', [
                                'entry' => $articles[$slug],
                                'primary' => $slug === $article,
                                'relatedEntries' => $slug === $article ? $related : [],
                            ])
                        @endforeach
                    @elseif ($query !== '')
                        <div class="hc-results">
                        <h1 class="hc-card-title">Hasil pencarian</h1>
                        <p class="hc-card-sub">{{ count($results) }} panduan untuk “{{ $query }}”.</p>
                        @if ($results)
                            <ul class="hc-list">
                                @foreach ($results as $slug => $entry)
                                    <li><a href="{{ route('help.index', $slug) }}"><strong>{{ $entry['title'] }}</strong><span>{{ $entry['group'] }} · {{ \App\Support\HelpText::plain($entry['intro']) }}</span></a></li>
                                @endforeach
                            </ul>
                        @else
                            <div class="hc-empty">
                                <h2>Belum menemukan jawabannya?</h2>
                                <p>Coba kata yang lebih pendek atau pilih salah satu topik berikut.</p>
                                <div class="hc-topics">
                                    @foreach (['nota', 'kas harian', 'transfer'] as $term)
                                        <a href="{{ route('help.index', ['q' => $term]) }}">{{ $term }}</a>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                        </div>
                    @else
                        <div class="hc-hero">
                            <div>
                                <p class="hc-kicker">Panduan Kasir Bangunan</p>
                                <h1>Apa yang ingin Anda kerjakan?</h1>
                                <p class="hc-intro">Cari cara pakainya atau temukan jawaban saat ada kendala.</p>
                                @include('partials.help-search')
                            </div>
                            <nav class="hc-quick" aria-labelledby="hc-quick-title">
                                <h2 id="hc-quick-title">Jalur cepat</h2>
                                <ul>
                                    @foreach ([
                                        'kasir' => 'Buat penjualan',
                                        'shift' => 'Cek kas harian',
                                        'riwayat-penjualan' => 'Cari atau cetak ulang nota',
                                        'persetujuan' => 'Cek status pembayaran',
                                    ] as $slug => $label)
                                        <li><a href="{{ route('help.index', $slug) }}">{{ $label }} <span aria-hidden="true">›</span></a></li>
                                    @endforeach
                                </ul>
                            </nav>
                        </div>
                        <section id="jelajahi-modul" class="hc-card" aria-labelledby="hc-module-title">
                            @php($moduleDescriptions = [
                                'Mulai di sini' => 'Kenali cara kerja toko dan peran setiap petugas.',
                                'Operasional' => 'Penjualan, kas harian, tagihan, dan pengiriman.',
                                'Stok & pembelian' => 'Barang masuk, stok opname, dan retur.',
                                'Master data' => 'Kelola produk, pemasok, pelanggan, dan data lain.',
                                'Keuangan' => 'Biaya, saldo, dan cara membaca laporan toko.',
                            ])
                            <h2 id="hc-module-title" class="hc-card-title">Jelajahi modul</h2>
                            <p class="hc-card-sub">{{ count($articles) }} panduan dalam {{ count($groups) }} modul yang bisa Anda akses.</p>
                            <ul class="hc-module-grid" role="list">
                                @foreach ($groups as $group => $entries)
                                    <li>
                                        <a class="hc-module-card" href="{{ route('help.index', array_key_first($entries)) }}">
                                            <span>
                                                <strong>{{ $group }}</strong>
                                                <small>{{ $moduleDescriptions[$group] ?? '' }}</small>
                                                <span class="hc-module-count">{{ count($entries) }} panduan</span>
                                            </span>
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 18 6-6-6-6"/></svg>
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        </section>
                    @endif
                </div>
            </main>
            <aside class="hc-nav" id="modul-panduan" tabindex="-1" aria-label="Pencarian dan modul panduan">
                @unless ($isHome)
                    @include('partials.help-search')
                @endunless
                <nav class="hc-nav-modules" aria-label="Modul panduan">
                <h2 class="hc-nav-title">Modul panduan</h2>
                @foreach ($groups as $group => $entries)
                    @php($isReadingGroup = $current && $current['group'] === $group)
                    <details @if ($isReadingGroup) open @endif>
                        <summary><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ $moduleIcons[$group] ?? $moduleIcons['Mulai di sini'] }}"/></svg><span>{{ $group }}</span></summary>
                        <div class="hc-submenu">
                            @foreach (($isReadingGroup ? $moduleEntries : $entries) as $slug => $title)
                                @if ($isReadingGroup)
                                    <a href="#panduan-{{ $slug }}" data-module-article @if ($article === $slug) aria-current="location" @endif>{{ $title }}</a>
                                @else
                                    <a href="{{ route('help.index', $slug) }}">{{ $title }}</a>
                                @endif
                            @endforeach
                        </div>
                    </details>
                @endforeach
                </nav>
            </aside>
            @if ($current)
                <aside class="hc-onpage" aria-labelledby="hc-onpage-title">
                    <h2 id="hc-onpage-title" class="hc-onpage-title">Di halaman ini</h2>
                    @foreach ($moduleEntries as $slug => $title)
                        @php($isPrimary = $slug === $article)
                        <ul data-onpage-for="panduan-{{ $slug }}" @unless ($isPrimary) hidden @endunless>
                            <li><a href="#panduan-{{ $slug }}">Ringkasan</a></li>
                            @if ($articles[$slug]['details'] ?? [])
                                <li><a href="#{{ $isPrimary ? 'penjelasan' : 'penjelasan-'.$slug }}">Penjelasan</a></li>
                            @endif
                            <li><a href="#{{ $isPrimary ? 'langkah' : 'langkah-'.$slug }}">Langkah singkat</a></li>
                            <li><a href="#{{ $isPrimary ? 'kendala' : 'kendala-'.$slug }}">Jika bingung</a></li>
                            @if ($isPrimary && $related)
                                <li><a href="#terkait">Panduan terkait</a></li>
                            @endif
                        </ul>
                    @endforeach
                </aside>
            @endif
        </div>
    </div>
    @include('v2.ui-tooltip')
    @include('partials.help-interactions')
    @if ($current)
        <script>
            (() => {
                const entries = [...document.querySelectorAll('.hc-article')];
                const sidebar = document.querySelector('.hc-nav-modules');
                const links = new Map([...sidebar.querySelectorAll('[data-module-article]')].map(link => [link.hash, link]));
                let active;
                let frame = 0;

                function keepLinkVisible(link) {
                    if (!link || sidebar.scrollHeight <= sidebar.clientHeight) return;
                    const bounds = sidebar.getBoundingClientRect();
                    const item = link.getBoundingClientRect();
                    const top = bounds.top + sidebar.clientTop;
                    const bottom = top + sidebar.clientHeight;
                    if (item.top < top) sidebar.scrollTop += item.top - top;
                    else if (item.bottom > bottom) sidebar.scrollTop += item.bottom - bottom;
                }

                function setActive(entry) {
                    if (!entry || entry === active) return;
                    active?.classList.remove('is-current');
                    if (active) links.get('#' + active.id)?.removeAttribute('aria-current');
                    entry.classList.add('is-current');
                    const link = links.get('#' + entry.id);
                    link?.setAttribute('aria-current', 'location');
                    active = entry;
                    keepLinkVisible(link);
                }

                const onpage = document.querySelector('.hc-onpage');
                function syncOnpage() {
                    if (!onpage || !active) return;
                    let list;
                    for (const candidate of onpage.querySelectorAll('[data-onpage-for]')) {
                        candidate.hidden = candidate.dataset.onpageFor !== active.id;
                        if (!candidate.hidden) list = candidate;
                    }
                    if (!list) return;
                    const items = [...list.querySelectorAll('a')];
                    const readingLine = window.innerHeight / 3;
                    const atBottom = window.innerHeight + window.scrollY >= document.documentElement.scrollHeight - 2;
                    let current = items[0];
                    for (const item of items) {
                        const target = document.getElementById(item.hash.slice(1));
                        if (target && target.getBoundingClientRect().top <= readingLine) current = item;
                    }
                    if (atBottom && active === entries.at(-1)) current = items.at(-1);
                    for (const item of items) {
                        if (item === current) item.setAttribute('aria-current', 'location');
                        else item.removeAttribute('aria-current');
                    }
                }

                function updateFromScroll() {
                    if (frame) return;
                    frame = requestAnimationFrame(() => {
                        frame = 0;
                        const readingLine = window.innerHeight / 3;
                        let visible = entries[0];
                        for (const entry of entries) {
                            if (entry.getBoundingClientRect().top > readingLine) break;
                            visible = entry;
                        }
                        setActive(visible);
                        syncOnpage();
                    });
                }

                function updateFromHash() {
                    const target = document.getElementById(location.hash.slice(1))?.closest('.hc-article');
                    if (target) {
                        setActive(target);
                        syncOnpage();
                    } else updateFromScroll();
                }

                active = entries.find(entry => entry.classList.contains('is-current'));
                window.addEventListener('scroll', updateFromScroll, { passive: true });
                window.addEventListener('resize', updateFromScroll);
                window.addEventListener('hashchange', updateFromHash);
                updateFromHash();
            })();
        </script>
    @endif
</body>
</html>
