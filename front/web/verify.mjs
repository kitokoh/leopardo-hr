import { chromium } from '@playwright/test';
import { readFileSync } from 'node:fs';
const AXE = readFileSync('node_modules/axe-core/axe.min.js','utf8');
const PAGES=['/','/pricing','/contact','/signup?plan=pilot','/demo','/case-studies','/employes','/marketing','/about','/faq','/blog','/testimonials','/integrations','/download','/mobile','/restaurateur','/comptabilite','/documents'];
const b = await chromium.launch();
const settle = async (p)=>{ await p.evaluate(async()=>{
  for(let y=0;y<document.body.scrollHeight;y+=500){window.scrollTo(0,y);await new Promise(r=>setTimeout(r,150));}
  window.scrollTo(0,0);}); await p.waitForTimeout(3500); };
// canari
const cc=await b.newContext({viewport:{width:1440,height:900}});
await cc.addInitScript(()=>{try{localStorage.setItem('theme','dark')}catch{}});
const cp=await cc.newPage(); await cp.goto('http://localhost:3000/?lang=fr',{waitUntil:'networkidle'}); await cp.waitForTimeout(2500);
await cp.evaluate(()=>{const d=document.createElement('div');d.style.cssText='position:fixed;top:0;left:0;z-index:99999;background:rgb(255,255,255);padding:8px';const q=document.createElement('p');q.style.cssText='color:rgb(235,235,235);font-size:16px;margin:0';q.textContent='CANARI ILLISIBLE';d.appendChild(q);document.body.appendChild(d);});
await cp.evaluate(AXE);
const cr=await cp.evaluate(async()=>{const x=await window.axe.run(document,{runOnly:['color-contrast']});return x.violations.flatMap(y=>y.nodes.map(n=>n.html))});
console.log(`[canari] ${cr.some(h=>/CANARI/.test(h||''))?'✅ auditeur VALIDE':'❌ AVEUGLE'}`);
await cc.close();
for (const theme of ['light','dark']) {
  const ctx=await b.newContext({viewport:{width:1440,height:900},locale:'fr-FR'});
  await ctx.addInitScript(t=>{try{localStorage.setItem('theme',t)}catch{}},theme);
  const p=await ctx.newPage(); let tot=0,bad=[];
  for (const path of PAGES) {
    await p.goto(`http://localhost:3000${path}${path.includes('?')?'&':'?'}lang=fr`,{waitUntil:'networkidle'});
    await p.waitForTimeout(1500); await settle(p); await p.evaluate(AXE);
    const r=await p.evaluate(async()=>{const x=await window.axe.run(document,{runOnly:['color-contrast']});
      return x.violations.flatMap(y=>y.nodes.map(n=>{const m=(n.failureSummary||'').match(/contrast of ([\d.]+) \(foreground color: (#\w+), background color: (#\w+)/);
        return {r:m?.[1],fg:m?.[2],bg:m?.[3],h:(n.html||'').replace(/\s+/g,' ').slice(0,110)};}));});
    tot+=r.length; if(r.length) bad.push([path,r]);
  }
  console.log(`\n>>> ${theme.toUpperCase()} : ${tot} violation(s) sur ${PAGES.length} pages`);
  bad.forEach(([pa,vs])=>{console.log(`   ${pa} (${vs.length})`); vs.slice(0,4).forEach(v=>console.log(`      ${v.r} ${v.fg}/${v.bg} | ${v.h}`));});
  await ctx.close();
}
await b.close();
