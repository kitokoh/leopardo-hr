import { chromium } from '@playwright/test';
import { readFileSync } from 'node:fs';
const AXE = readFileSync('node_modules/axe-core/axe.min.js','utf8');
const PAGES=['/','/pricing','/contact','/signup?plan=pilot','/demo','/case-studies','/employes','/marketing','/about','/faq','/blog','/testimonials','/integrations','/download','/mobile','/restaurateur','/comptabilite','/documents'];
const b = await chromium.launch();
const settle = async (p)=>{ await p.evaluate(async()=>{
  for(let y=0;y<document.body.scrollHeight;y+=500){window.scrollTo(0,y);await new Promise(r=>setTimeout(r,150));}
  window.scrollTo(0,0);}); await p.waitForTimeout(3500); };
for (const theme of ['light','dark']) {
  const ctx = await b.newContext({ viewport:{width:1440,height:900}, locale:'fr-FR' });
  await ctx.addInitScript(t=>{try{localStorage.setItem('theme',t)}catch{}}, theme);
  const p = await ctx.newPage();
  let tot=0, bad=[], artifacts=0;
  for (const path of PAGES) {
    await p.goto(`http://localhost:3000${path}${path.includes('?')?'&':'?'}lang=fr`,{waitUntil:'networkidle'});
    await p.waitForTimeout(1500); await settle(p); await p.evaluate(AXE);
    const r = await p.evaluate(async ()=>{ const x=await window.axe.run(document,{runOnly:['color-contrast']});
      return x.violations.flatMap(y=>y.nodes.map(n=>{
        const m=(n.failureSummary||'').match(/contrast of ([\d.]+) \(foreground color: (#\w+), background color: (#\w+)/);
        // opacite effective remontee depuis l'element et ses ancetres
        let el=document.querySelector(n.target.join(' ')), eff=1;
        while(el && el!==document.documentElement){ const o=parseFloat(getComputedStyle(el).opacity); if(!isNaN(o)) eff*=o; el=el.parentElement; }
        return {r:m?.[1],fg:m?.[2],bg:m?.[3],op:+eff.toFixed(2),h:(n.html||'').replace(/\s+/g,' ').slice(0,110)};}));});
    tot+=r.length;
    if(r.length){ bad.push([path,r]); artifacts+=r.filter(v=>v.op<0.99).length; }
  }
  console.log(`\n>>> ${theme.toUpperCase()} : ${tot} violation(s) | dont ${artifacts} avec opacite < 1 (fondu non termine)`);
  bad.forEach(([pa,vs])=>{console.log(`   ${pa} (${vs.length})`); vs.slice(0,4).forEach(v=>console.log(`      ${v.r} opacite=${v.op} ${v.fg} sur ${v.bg} | ${v.h}`));});
  await ctx.close();
}
await b.close();
