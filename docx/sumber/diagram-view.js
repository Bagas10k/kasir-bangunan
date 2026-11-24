/* Source-bound business flowcharts. No application actions or external renderer. */
'use strict';
window.BusinessDiagram = (() => {
  const NS = 'http://www.w3.org/2000/svg';
  const palette = {ink:'#102a1d',green:'#14532d',lime:'#c8f26b',muted:'#5f7468',line:'#bdcdbf',paper:'#fff',soft:'#f0f5f0',warm:'#fff7e7'};
  const svgEl = (tag, attrs={}, text) => {
    const e=document.createElementNS(NS,tag);
    for(const [key,value] of Object.entries(attrs)) e.setAttribute(key,String(value));
    if(text!==undefined)e.textContent=String(text);
    return e;
  };
  function wrap(text,max=30){
    const lines=[];let line='';
    for(const word of String(text).split(/\s+/)){if((line+' '+word).trim().length>max&&line){lines.push(line);line=word;}else line=(line+' '+word).trim();}
    if(line)lines.push(line);return lines;
  }
  function textBlock(parent,text,x,y,size=16,max=30,color=palette.ink){
    const rows=wrap(text,max),t=svgEl('text',{x,y,fill:color,'font-size':size,'font-family':'system-ui,Segoe UI,sans-serif','text-anchor':'middle','font-weight':size>=15?600:400});
    rows.forEach((line,i)=>t.append(svgEl('tspan',{x,dy:i?size*1.35:0},line)));
    parent.append(t);return rows.length;
  }
  function canvas(slug,title,description,width,height){
    const svg=svgEl('svg',{viewBox:`0 0 ${width} ${height}`,width,height,role:'img','aria-labelledby':`${slug}-title ${slug}-desc`});
    svg.style.cssText=`display:block;width:100%;min-width:${Math.min(width,920)}px;height:auto`;
    svg.append(svgEl('title',{id:slug+'-title'},title),svgEl('desc',{id:slug+'-desc'},description));
    const defs=svgEl('defs'),marker=svgEl('marker',{id:slug+'-arrow',markerWidth:8,markerHeight:8,refX:7,refY:4,orient:'auto'});
    marker.append(svgEl('path',{d:'M0 0 L8 4 L0 8 Z',fill:palette.muted}));defs.append(marker);svg.append(defs,svgEl('rect',{width,height,fill:palette.paper}));
    return svg;
  }
  function roundedPath(points,r=8){
    let d=`M${points[0][0]} ${points[0][1]}`;
    for(let i=1;i<points.length-1;i++){
      const a=points[i-1],b=points[i],c=points[i+1];
      const before=Math.min(r,Math.abs(b[0]-a[0])+Math.abs(b[1]-a[1])),after=Math.min(r,Math.abs(c[0]-b[0])+Math.abs(c[1]-b[1]));
      const p=[b[0]-Math.sign(b[0]-a[0])*before,b[1]-Math.sign(b[1]-a[1])*before];
      const q=[b[0]+Math.sign(c[0]-b[0])*after,b[1]+Math.sign(c[1]-b[1])*after];
      d+=` L${p[0]} ${p[1]} Q${b[0]} ${b[1]} ${q[0]} ${q[1]}`;
    }
    return d+` L${points.at(-1)[0]} ${points.at(-1)[1]}`;
  }
  function connector(svg,slug,points,label='',labelPoint=null,dashed=false){
    const attrs={d:roundedPath(points),fill:'none',stroke:palette.muted,'stroke-width':1.6,'marker-end':`url(#${slug}-arrow)`};
    if(dashed)attrs['stroke-dasharray']='5 4';
    const path=svgEl('path',attrs);path.dataset.connector='true';svg.append(path);
    if(label&&labelPoint){
      const rows=wrap(label,24),w=Math.max(...rows.map(s=>s.length))*8.5+16,h=rows.length*21+8;
      const [x,y]=labelPoint;
      svg.append(svgEl('rect',{x:x-w/2,y:y-13,width:w,height:h,rx:3,fill:palette.paper}));
      textBlock(svg,label,x,y,15,24,palette.muted);
    }
  }
  function node(svg,n,click){
    const g=svgEl('g',{'data-diagram-node':n.id});
    const decision=n.kind==='decision';
    const shape=decision?svgEl('polygon',{points:`${n.x+n.w/2},${n.y} ${n.x+n.w},${n.y+n.h/2} ${n.x+n.w/2},${n.y+n.h} ${n.x},${n.y+n.h/2}`,fill:palette.soft,stroke:palette.green,'stroke-width':1.6}):svgEl('rect',{x:n.x,y:n.y,width:n.w,height:n.h,rx:n.kind==='end'?28:8,fill:n.focal?palette.green:n.kind==='end'?palette.warm:palette.paper,stroke:n.focal?palette.green:palette.line,'stroke-width':1.4});
    g.append(shape);
    const rows=wrap(n.title,decision?22:30),size=16;
    const totalHeight=rows.length*size*1.35+(n.subtitle&&!decision?24:0);
    textBlock(g,n.title,n.x+n.w/2,n.y+(n.h-totalHeight)/2+size,size,decision?22:30,n.focal?'#fff':palette.ink);
    if(n.subtitle&&!decision)textBlock(g,n.subtitle,n.x+n.w/2,n.y+n.h-16,15,38,n.focal?palette.lime:palette.muted);
    const desc=svgEl('title',{},n.description||n.title);g.append(desc);
    if(click){
      g.setAttribute('tabindex','0');g.setAttribute('role','link');g.setAttribute('aria-label',n.title+' — lihat rincian');g.style.cursor='pointer';
      g.addEventListener('click',()=>click(n.id));g.addEventListener('keydown',e=>{if(e.key==='Enter'||e.key===' '){e.preventDefault();click(n.id);}});
    }
    svg.append(g);
  }
  function figure(svg,caption,focusX=176){
    const fig=document.createElement('figure');fig.className='business-diagram';fig.style.cssText='margin:24px 0;border:1px solid #dce6df;border-radius:10px;background:white';
    const cap=document.createElement('figcaption');cap.textContent=caption;cap.style.cssText='padding:14px 18px;font-size:13px;color:#52685a;border-bottom:1px solid #dce6df';
    const frame=document.createElement('div');frame.className='diagram-scroll';frame.tabIndex=0;frame.setAttribute('aria-label','Diagram alur; geser ke samping pada layar kecil');frame.style.cssText='overflow-x:auto;max-width:100%;padding:16px';frame.append(svg);fig.append(cap,frame);
    requestAnimationFrame(()=>{if(svg.isConnected&&frame.clientWidth<680){frame.scrollLeft=focusX/svg.viewBox.baseVal.width*svg.getBoundingClientRect().width-frame.clientWidth/2+16;cap.textContent=caption+' Geser ke samping untuk melihat cabang.';}});
    return fig;
  }
  function journey(jump,journey){
    const slug='flow-'+journey.id,stages=journey.steps||[],width=1064;
    const nodes=stages.map((s,i)=>({id:s.id,title:s.title,subtitle:s.actor,description:s.action,kind:s.kind==='decision'?'decision':'action',focal:s.kind==='finish',x:352,y:40+i*224,w:360,h:s.kind==='decision'?120:104}));
    const byId=new Map(nodes.map(n=>[n.id,n])),edges=[],terminals=[];
    for(const [index,stage] of stages.entries()){
      const source=nodes[index],grouped=new Map();
      for(const branch of stage.branches||[]){
        const target=branch.next||'';
        if(target){if(!grouped.has(target))grouped.set(target,[]);grouped.get(target).push(branch.label);}
        else{
          const slot=terminals.filter(t=>t.source===stage.id).length;
          const label=branch.label.replace(/^.*?→\s*/, '');
          const loopsBack=(stage.branches||[]).some(b=>b.next===stage.id),right=loopsBack?slot%2===1:slot%2===0;
          const terminal={id:stage.id+'-end-'+slot,source:stage.id,title:label,subtitle:'Cabang berakhir di sini',description:branch.result,kind:'end',x:right?800:24,y:source.y+source.h+64+Math.floor(slot/2)*136,w:232,h:100};
          terminals.push(terminal);edges.push({source,target:terminal,label:'Selesai',terminal:true});
        }
      }
      for(const target of stage.next||[]){if(!grouped.has(target))grouped.set(target,(stage.next||[]).length>1?['Opsional']:[]);}
      for(const [target,labels] of grouped){if(byId.has(target))edges.push({source,target:byId.get(target),label:labels.join(' / ')});}
    }
    const height=Math.max(240,...[...nodes,...terminals].map(n=>n.y+n.h))+92;
    const svg=canvas(slug,journey.title,`Alur ${journey.title}: tindakan, pilihan bersyarat, dan hasil. Cabang batal atau ditolak berakhir sendiri, bukan dilanjutkan ke sukses.`,width,height);
    let bypass=0;
    const sideX=(n,y,right)=>n.kind==='decision'?n.x+n.w/2+(right?1:-1)*n.w/2*(1-Math.abs(y-(n.y+n.h/2))/(n.h/2)):(right?n.x+n.w:n.x);
    for(const edge of edges){
      const a=edge.source,b=edge.target,ac=a.x+a.w/2,bc=b.x+b.w/2;
      if(a.id===b.id){
        const x=760,sy=a.y+20,ty=a.y+a.h-20;connector(svg,slug,[[sideX(a,sy,true),sy],[x,sy],[x,ty],[sideX(a,ty,true),ty]],edge.label,[x+94,a.y+62],true);
      }else if(edge.terminal){
        const right=b.x>a.x,lane=right?772:316;
        const sameTerm=edges.filter(e=>e.terminal&&e.source.id===a.id&&((e.target.x>a.x)===right));
        const k=sameTerm.indexOf(edge),sy=a.y+34+k*24;
        connector(svg,slug,[[sideX(a,sy,right),sy],[lane-k*12,sy],[lane-k*12,b.y+b.h/2],[right?b.x:b.x+b.w,b.y+b.h/2]],'',null,true);
      }else if(b.y>a.y&&b.y-a.y<=224){
        connector(svg,slug,[[ac,a.y+a.h],[bc,b.y]],edge.label,edge.label?[ac+145,(a.y+a.h+b.y)/2-4]:null);
      }else{
        const lane=144+(bypass++%4)*32;
        const sy=a.y+a.h/2,ty=b.y+b.h/2;
        const labelOffset=Math.max(...wrap(edge.label,24).map(s=>s.length),0)*8.5/2+16;
        connector(svg,slug,[[a.x,sy],[lane,sy],[lane,ty],[b.x,ty]],edge.label,edge.label?[lane+labelOffset,sy+32]:null,true);
      }
    }
    for(const n of [...nodes,...terminals])node(svg,n,byId.has(n.id)?jump:null);
    svg.append(svgEl('text',{x:28,y:height-26,fill:palette.muted,'font-size':15,'font-family':'system-ui'},'Kotak: tindakan · belah ketupat: pilihan · ujung bulat: akhir · putus-putus: alternatif'));
    return figure(svg,(stages.some(s=>s.current===false)?'Sumber berubah: diagram ini perlu ditinjau ulang. ':'')+'Diagram proses · klik tahap untuk membuka penjelasannya. Pilihan yang tidak diambil bukan tahap wajib.',532);
  }
  function overview(select){
    const slug='flow-system',svg=canvas(slug,'Alur utama Kasir Bangunan','Barang masuk menambah stok dan utang supplier; penjualan memakai stok dan membuat pembayaran atau piutang. Pengiriman dipilih terpisah; pengeluaran, rekening bank, pindah dana, dan hasil dibaca dalam rekap.',1104,744);
    const nodes=[
      {id:'barang-masuk',title:'Barang masuk',subtitle:'Admin: draf → konfirmasi + ongkir',x:40,y:40,w:272,h:112},
      {id:'stock',title:'Stok tersedia',subtitle:'Penerimaan final menambah stok',x:416,y:40,w:272,h:112,focal:true},
      {id:'penjualan',title:'Penjualan',subtitle:'Kasir/admin; kas harian terbuka',x:792,y:40,w:272,h:112},
      {id:'supplier',title:'Utang → bayar supplier',subtitle:'Kas Utama / Rekening Bank / Laci',x:40,y:304,w:272,h:112},
      {id:'pengiriman',title:'Pengiriman (jika dipilih)',subtitle:'Disiapkan → Dikirim → Diterima',x:416,y:304,w:272,h:112},
      {id:'piutang',title:'Pembayaran / piutang',subtitle:'Tunai / transfer + bukti / kredit',x:792,y:304,w:272,h:112},
      {id:'laporan',title:'Jurnal dan laporan',subtitle:'Per rekening bank; baca saja',x:40,y:568,w:272,h:112},
      {id:'keuangan-kas',title:'Pengeluaran & kas/bank',subtitle:'Biaya, COA bank, pindah dana',x:416,y:568,w:272,h:112},
      {id:'shift',title:'Kas harian (buka & tutup)',subtitle:'Admin: rekap & cek fisik laci',x:792,y:568,w:272,h:112}
    ];
    connector(svg,slug,[[312,96],[416,96]],'STOK MASUK',[364,65]);
    connector(svg,slug,[[688,96],[792,96]],'STOK CUKUP',[740,65]);
    connector(svg,slug,[[176,152],[176,304]],'UTANG AP',[250,230]);
    connector(svg,slug,[[928,152],[928,304]],'BAYAR / AR',[1004,230]);
    connector(svg,slug,[[792,116],[736,116],[736,248],[552,248],[552,304]],'JIKA KIRIM',[600,221],true);
    connector(svg,slug,[[928,416],[928,568]],'MUTASI LACI',[1010,500],true);
    connector(svg,slug,[[792,624],[688,624]],'SETOR / KAS',[740,596],true);
    connector(svg,slug,[[416,624],[312,624]],'JURNAL',[364,596]);
    connector(svg,slug,[[176,416],[176,568]],'JURNAL',[242,500]);
    for(const n of nodes)node(svg,n,n.id==='stock'?()=>select('barang-masuk'):select);
    return figure(svg,'Hubungan proses utama, bukan kewajiban menjalankan semuanya berurutan. Stok juga dapat berasal dari saldo awal; laporan membaca seluruh jurnal termasuk pengeluaran, rekening bank, dan pindah dana.');
  }
  return {journey,overview};
})();
