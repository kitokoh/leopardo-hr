// Captures du héro « stage » après le point 4 (cartes en orbite).
// Lancé contre le serveur de dev local — la preview Vercel de la branche est
// protégée par l'authentification Vercel (elle sert la page de connexion), donc
// ces captures SONT le livrable d'aperçu.
const { chromium } = require('playwright');
const fs = require('fs');

// Le sandbox embarque un Chromium headless d'une AUTRE révision que celle
// attendue par Playwright 1.64 : on pointe explicitement sur le binaire
// présent plutôt que de télécharger un navigateur (pas de réseau garanti).
const CANDIDATES = [
  '/root/.cache/ms-playwright/chromium_headless_shell-1181/chrome-linux/headless_shell',
  '/usr/bin/chrome-debug',
];
const executablePath = CANDIDATES.find((p) => fs.existsSync(p));
if (!executablePath) {
  throw new Error(`Aucun Chromium trouvé parmi : ${CANDIDATES.join(', ')}`);
}

const VIEWPORTS = [
  { name: 'desktop', width: 1440, height: 900 },
  { name: 'tablet', width: 834, height: 1112 },
  { name: 'mobile', width: 390, height: 844 },
];

(async () => {
  const browser = await chromium.launch({ executablePath, args: ['--no-sandbox'] });
  const report = {};

  for (const vp of VIEWPORTS) {
    const page = await browser.newPage({ viewport: { width: vp.width, height: vp.height } });
    await page.goto('http://localhost:3000/', { waitUntil: 'networkidle', timeout: 90000 });
    // Laisse la scène 3D s'installer (dynamic import au premier temps mort).
    await page.waitForTimeout(4000);

    const metrics = await page.evaluate(() => {
      const cards = Array.from(document.querySelectorAll('[data-testid^="hero-orbit-card-"]'));
      const rects = cards.map((c) => c.getBoundingClientRect());
      const h1 = document.querySelector('h1');
      // Boîte du TEXTE, pas du bloc : le `h1` occupe toute la largeur de son
      // conteneur (`text-center`), donc sa boîte englobante chevaucherait
      // toujours les cartes latérales et ne mesurerait rien d'utile.
      let h1Rect = null;
      if (h1) {
        const range = document.createRange();
        range.selectNodeContents(h1);
        const r = range.getBoundingClientRect();
        h1Rect = { left: r.left, right: r.right, top: r.top, bottom: r.bottom };
      }
      // Chevauchement titre / cartes : le point 4 exige qu'ils partagent le
      // flux, donc qu'ils ne se superposent pas.
      const overlaps = h1Rect
        ? rects.filter(
            (r) =>
              r.left < h1Rect.right &&
              r.right > h1Rect.left &&
              r.top < h1Rect.bottom &&
              r.bottom > h1Rect.top,
          ).length
        : -1;
      // Chevauchement CARTE/CARTE : deux pastilles qui se recouvrent rendent
      // les deux illisibles — c'est le défaut que la revue visuelle a relevé.
      const pairs = [];
      for (let i = 0; i < rects.length; i += 1) {
        for (let j = i + 1; j < rects.length; j += 1) {
          const a = rects[i];
          const b = rects[j];
          const ox = Math.min(a.right, b.right) - Math.max(a.left, b.left);
          const oy = Math.min(a.bottom, b.bottom) - Math.max(a.top, b.top);
          if (ox > 0 && oy > 0) {
            pairs.push({
              a: cards[i].getAttribute('data-testid'),
              b: cards[j].getAttribute('data-testid'),
              overlapPx: Math.round(Math.min(ox, oy)),
            });
          }
        }
      }
      return {
        cards: cards.length,
        cardOverlaps: pairs,
        focusable: cards.filter((c) => c.tabIndex >= 0).length,
        labelled: cards.filter((c) => (c.getAttribute('aria-label') || '').length > 0).length,
        h1Text: h1 ? h1.textContent.trim().slice(0, 60) : null,
        h1Top: h1Rect ? Math.round(h1Rect.top) : null,
        cardsTop: rects.length ? Math.round(Math.min(...rects.map((r) => r.top))) : null,
        titleCardOverlaps: overlaps,
        horizontalOverflow: document.documentElement.scrollWidth - window.innerWidth,
        // Contraste du titre sur le fond nuit (formule WCAG 2.1).
        titleColor: h1 ? getComputedStyle(h1).color : null,
      };
    });

    await page.screenshot({ path: `/workspace/vitrine-check/after-orbit-${vp.name}.png` });
    report[vp.name] = metrics;
    await page.close();
  }

  await browser.close();
  console.log(JSON.stringify(report, null, 2));
})();
