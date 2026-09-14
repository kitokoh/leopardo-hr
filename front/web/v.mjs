import { chromium } from '@playwright/test';
import { readFileSync } from 'node:fs';
const AXE = readFileSync('node_modules/axe-core/axe.min.js','utf8');
const PAGES=['/','/pricing','/contact','/signup?plan=pilot','/demo','/case-studies','/employes','/marketing','/about','/faq','/blog','/testimonials','/integrations','/download','/mobile'];
const b = await chromium.launch();
for (const theme of ['dark','light']) {
  const ctx = await b.newContext({ viewport:{width:1440,height:900}, locale:'fr-FR' });
  await ctx.addInitScript(t=>{try{localStorage.setItem('theme',t);}catch{}}, theme);
  const p = await ctx.newPage();
  let tot=0, inc=0, rows=[];
  for (const path of PAGES) {
    await p.goto(`http://localhost:3000${path}${path.includes('?')?'&':'?'}lang=fr`,{waitUntil:'networkidle'});
    await p.waitForTimeout(1600);
    await p.evaluate(async()=>{for(let y=0;y<document.body.scrollHeight;y+=700){window.scrollTo(0,y);await new Promise(r=>setTimeout(r,90));}window.scrollTo(0,0);});
    await p.waitForTimeout(350);
    await p.evaluate(AXE);
    const r = await p.evaluate(async () => {
      const x = await window.axe.run(document, { runOnly: ['color-contrast'] });
      return { v: x.violations.flatMap(y=>y.nodes.map(n=>(n.failureSummary||'').match(/contrast of ([\d.]+)/)?.[1]+' '+(n.html||'').replace(/\s+/g,' ').slice(0,80))),
               inc: x.incomplete.reduce((a,y)=>a+y.nodes.length,0) };
    });
    tot+=r.v.length; inc+=r.inc;
    if(r.v.length) { console.log(`  ${path}: ${r.v.length}`); r.v.forEach(v=>console.log(`      ${v}`)); }
  }
  console.log(`\n>>> ${theme.toUpperCase()} = ${tot} violation(s) sur ${PAGES.length} pages | ${inc} non concluants\n`);
  await ctx.close();
}
await b.close();
