/**
 * #AI-SEO — garde-fous des fichiers `llms.txt` / `llms-full.txt`.
 *
 * Objectif : garantir que les fichiers servis aux moteurs de réponse restent
 * (a) localisés (le FR ne doit pas fuiter dans les variantes ?lang=en/tr/ar),
 * (b) complets (pages principales, offres, FAQ), (c) pointés vers les bonnes
 * URLs canoniques de locale.
 */
import { SITE_URL } from '@/lib/site-url';
import {
  AI_SEARCH_FACTS,
  AI_SEARCH_REPOSITORY_URL,
  buildLlmsFullTxt,
  buildLlmsTxt,
  breadcrumbLabels,
  LLMS_CONTENT_TYPE,
  localizedUrl,
} from '../ai-search';
import { getFaqPageContent } from '@/modules/vitrine/data/faq-page';

describe('localizedUrl (#AI-SEO)', () => {
  it('laisse la racine FR sans query (convention hreflang du sitemap)', () => {
    expect(localizedUrl('/', 'fr')).toBe(`${SITE_URL}/`);
  });

  it('ajoute ?lang= pour les locales non-FR', () => {
    expect(localizedUrl('/pricing', 'en')).toBe(
      `${SITE_URL}/pricing?lang=en`,
    );
    expect(localizedUrl('/blog/mon-article', 'ar')).toBe(
      `${SITE_URL}/blog/mon-article?lang=ar`,
    );
  });
});

describe('buildLlmsTxt (#AI-SEO)', () => {
  it('expose le nom de marque localisé et le résumé', () => {
    const fr = buildLlmsTxt('fr');
    expect(fr.startsWith('# Leopardo RH')).toBe(true);
    expect(fr).toContain('logiciel SaaS de gestion du personnel');

    const en = buildLlmsTxt('en');
    expect(en.startsWith('# Leopardo HR')).toBe(true);

    const tr = buildLlmsTxt('tr');
    expect(tr.startsWith('# Leopardo İK')).toBe(true);

    const ar = buildLlmsTxt('ar');
    expect(ar.startsWith('# ليوباردو')).toBe(true);
  });

  it('localise les liens (FR sans query, autres locales en ?lang=)', () => {
    const fr = buildLlmsTxt('fr');
    expect(fr).toContain(`${SITE_URL}/pricing)`);
    expect(fr).not.toContain('?lang=fr');

    const en = buildLlmsTxt('en');
    expect(en).toContain(`${SITE_URL}/pricing?lang=en`);
  });

  it('déclare les alias de marque et le dépôt public (consolidation entité)', () => {
    const txt = buildLlmsTxt('fr');
    expect(txt).toContain('Leopardo HR');
    expect(txt).toContain('Leopardo İK');
    expect(txt).toContain('ليوباردو');
    expect(txt).toContain(AI_SEARCH_REPOSITORY_URL);
  });

  it('publie les chiffres clés issus de la vitrine', () => {
    const txt = buildLlmsTxt('fr');
    expect(txt).toContain(String(AI_SEARCH_FACTS.payrollCountries));
    expect(txt).toContain(String(AI_SEARCH_FACTS.trialDays));
  });

  it('reste en texte brut UTF-8', () => {
    expect(LLMS_CONTENT_TYPE).toContain('text/plain');
    expect(buildLlmsTxt('ar').length).toBeGreaterThan(200);
  });
});

describe('buildLlmsFullTxt (#AI-SEO)', () => {
  it('inline les questions fréquentes de la locale', () => {
    const { items } = getFaqPageContent('fr');
    const txt = buildLlmsFullTxt('fr');
    for (const item of items.slice(0, 3)) {
      expect(txt).toContain(item.question);
      expect(txt).toContain(item.answer);
    }
  });

  it('inline les offres tarifaires localisées', () => {
    const fr = buildLlmsFullTxt('fr');
    expect(fr).toContain('Free');
    expect(fr).toContain('Operations');
    expect(fr).toContain('EUR');
  });

  it('est plus riche que llms.txt', () => {
    expect(buildLlmsFullTxt('fr').length).toBeGreaterThan(
      buildLlmsTxt('fr').length,
    );
  });
});

describe('breadcrumbLabels (#AI-SEO)', () => {
  it('fournit des libellés localisés non vides pour les 4 locales', () => {
    for (const locale of ['fr', 'en', 'tr', 'ar'] as const) {
      const labels = breadcrumbLabels(locale);
      expect(labels.home).toBeTruthy();
      expect(labels.blog).toBeTruthy();
      expect(labels.caseStudies).toBeTruthy();
      expect(labels.guides).toBeTruthy();
    }
  });
});
