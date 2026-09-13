/**
 * #SEO-OPS — garde-fous des balises de vérification des consoles.
 *
 * Le site est servi sur un sous-domaine `*.vercel.app` (suffixe public) : Google
 * Search Console n'accepte donc qu'une propriété « URL prefix », vérifiée par
 * balise HTML. Ces tests garantissent qu'aucune balise vide n'est émise (une
 * balise vide ferait échouer la vérification en silence) et que les jetons
 * fournis par l'environnement sont bien rendus.
 */
import { verificationMetadata } from '@/lib/seo-verification';

const ENV_KEYS = [
  'NEXT_PUBLIC_GOOGLE_SITE_VERIFICATION',
  'NEXT_PUBLIC_BING_SITE_VERIFICATION',
  'NEXT_PUBLIC_YANDEX_SITE_VERIFICATION',
] as const;

const clear = () => ENV_KEYS.forEach((key) => delete process.env[key]);

describe('verificationMetadata (#SEO-OPS)', () => {
  afterEach(clear);

  it("n'émet rien quand aucun jeton n'est configuré", () => {
    clear();
    expect(verificationMetadata()).toBeUndefined();
  });

  it('émet la balise google-site-verification quand le jeton est présent', () => {
    clear();
    process.env.NEXT_PUBLIC_GOOGLE_SITE_VERIFICATION = 'GOOGLE_TOKEN_123';
    expect(verificationMetadata()).toEqual({ google: 'GOOGLE_TOKEN_123' });
  });

  it('émet msvalidate.01 pour Bing Webmaster Tools', () => {
    clear();
    process.env.NEXT_PUBLIC_BING_SITE_VERIFICATION = 'BING_TOKEN_456';
    expect(verificationMetadata()).toEqual({ other: { 'msvalidate.01': 'BING_TOKEN_456' } });
  });

  it('combine Google, Bing et Yandex', () => {
    clear();
    process.env.NEXT_PUBLIC_GOOGLE_SITE_VERIFICATION = 'G';
    process.env.NEXT_PUBLIC_BING_SITE_VERIFICATION = 'B';
    process.env.NEXT_PUBLIC_YANDEX_SITE_VERIFICATION = 'Y';
    expect(verificationMetadata()).toEqual({
      google: 'G',
      yandex: 'Y',
      other: { 'msvalidate.01': 'B' },
    });
  });

  it("ignore un jeton vide ou fait d'espaces (pas de balise vide)", () => {
    clear();
    process.env.NEXT_PUBLIC_GOOGLE_SITE_VERIFICATION = '   ';
    expect(verificationMetadata()).toBeUndefined();
  });
});
