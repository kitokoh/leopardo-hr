import { chromium } from '@playwright/test';
import { readFileSync, appendFileSync } from 'node:fs';
const AXE=readFileSync('node_modules/axe-core/axe.min.js','utf8');
const L='/tmp/ap4.log'; const log=s=>appendFileSync(L,s+"\n");
const PAGES=['/dashboard','/employees','/payroll','/attendance','/absences','/contracts','/crm','/crm/pipeline','/accounting','/reports','/billing','/restaurant/pos','/edu-manager/students','/accounting/ledger'];
const b=await chromium.launch();
const ctx=await b.newContext({viewport:{width:1440,height:900},locale:'fr-FR'});
const p=await ctx.newPage(); p.setDefaultTimeout(45000);
await p.goto('http://localhost:3000/auth/login?lang=fr',{waitUntil:'domcontentloaded',timeout:90000});
await p.waitForSelector('#email-address',{timeout:60000});
await p.fill('#email-address','ahmed.benali@techcorp-algerie.dz');
await p.fill('#password','password123');
await p.getByRole('button',{name:/Se connecter/i}).click();
await p.waitForURL(/dashboard|onboarding/,{timeout:90000}).catch(()=>{});
await p.waitForTimeout(6000);
log("connecté -> "+p.url());
let tot=0;
for(const path of PAGES){
  try{ await p.goto(`http://localhost:3000${path}`,{waitUntil:'domcontentloaded',timeout:90000}); }catch(e){ log(`  ${path} -> nav KO`); continue; }
  await p.waitForTimeout(3200);
  await p.evaluate(AXE).catch(()=>{});
  let r=[];
  try{ r=await p.evaluate(async()=>{const x=await window.axe.run(document,{runOnly:['color-contrast']});
      return x.violations.flatMap(y=>y.nodes.map(n=>{const m=(n.failureSummary||'').match(/contrast of ([\d.]+) \(foreground color: (#\w+), background color: (#\w+)/);
        return `${m?.[1]} ${m?.[2]}/${m?.[3]} | ${(n.html||'').replace(/\s+/g,' ').slice(0,80)}`;}));}); }catch(e){ log(`  ${path} -> axe KO`); continue; }
  tot+=r.length; log(`  ${path} -> ${r.length}`);
  r.slice(0,5).forEach(v=>log(`      ${v}`));
}
log(`>>> TOTAL app: ${tot} sur ${PAGES.length} pages`);
await b.close();
