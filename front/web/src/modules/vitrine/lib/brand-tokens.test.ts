import { existsSync } from 'node:fs';
import { join } from 'node:path';

import {
  BRAND_ASSETS,
  BRAND_CLEAR_SPACE_RATIO,
  BRAND_EMERALD,
  BRAND_GRADIENT,
  BRAND_NAME,
  BRAND_ON_SURFACE,
  BRAND_SURFACE,
  BRAND_TAGLINE,
  BRAND_TYPOGRAPHY,
} from './brand-tokens';

/**
 * Garde du kit de marque (issue #9194).
 *
 * Vérifie que les tokens ne dérivent pas des assets réellement livrés : un
 * chemin d'asset cassé ou un contraste insuffisant doit échouer en CI, pas en
 * production.
 */

const PUBLIC_DIR = join(process.cwd(), 'public');

/** Contraste WCAG 2.1 entre deux couleurs hexadécimales. */
function contrastRatio(foreground: string, background: string): number {
  const luminance = (hex: string): number => {
    const value = hex.replace('#', '');
    const channels = [0, 2, 4].map((offset) => parseInt(value.slice(offset, offset + 2), 16) / 255);
    const [r, g, b] = channels.map((channel) =>
      channel <= 0.03928 ? channel / 12.92 : ((channel + 0.055) / 1.055) ** 2.4,
    );
    return 0.2126 * r + 0.7152 * g + 0.0722 * b;
  };

  const a = luminance(foreground);
  const b = luminance(background);
  const lighter = Math.max(a, b);
  const darker = Math.min(a, b);
  return (lighter + 0.05) / (darker + 0.05);
}

describe('kit de marque — assets', () => {
  it('déclare les quatre assets officiels', () => {
    expect(Object.keys(BRAND_ASSETS).sort()).toEqual(['icon', 'logo', 'logoDark', 'symbol']);
  });

  it.each(Object.entries(BRAND_ASSETS))('le fichier %s existe dans public/', (_key, path) => {
    expect(existsSync(join(PUBLIC_DIR, path))).toBe(true);
  });

  it('expose des chemins absolus sous /brand/', () => {
    for (const path of Object.values(BRAND_ASSETS)) {
      expect(path.startsWith('/brand/')).toBe(true);
      expect(path.endsWith('.svg')).toBe(true);
    }
  });
});

describe('kit de marque — identité', () => {
  it('fixe le nom et le slogan officiels', () => {
    expect(BRAND_NAME).toBe('Leopardo');
    expect(BRAND_TAGLINE).toBe('BUSINESS OS');
  });

  it('définit un espace de sécurité non nul', () => {
    expect(BRAND_CLEAR_SPACE_RATIO).toBeGreaterThan(0);
    expect(BRAND_CLEAR_SPACE_RATIO).toBeLessThanOrEqual(0.5);
  });

  it('compose le dégradé officiel à partir des tokens', () => {
    expect(BRAND_GRADIENT.css).toContain(BRAND_GRADIENT.from);
    expect(BRAND_GRADIENT.css).toContain(BRAND_GRADIENT.to);
    expect(BRAND_GRADIENT.css).toMatch(/^linear-gradient\(/);
  });

  it('déclare Inter pour le display et JetBrains Mono pour le code', () => {
    expect(BRAND_TYPOGRAPHY.display.family).toContain('Inter');
    expect(BRAND_TYPOGRAPHY.mono.family).toContain('JetBrains Mono');
  });
});

describe('kit de marque — accessibilité (WCAG 2.1 AA)', () => {
  it('le texte principal sur surface atteint AAA', () => {
    expect(contrastRatio(BRAND_ON_SURFACE.base, BRAND_SURFACE.base)).toBeGreaterThanOrEqual(7);
  });

  it('le texte secondaire sur surface atteint AAA', () => {
    expect(contrastRatio(BRAND_ON_SURFACE.variant, BRAND_SURFACE.base)).toBeGreaterThanOrEqual(7);
  });

  it('emerald.700 sur blanc atteint AA pour du texte normal', () => {
    expect(contrastRatio(BRAND_EMERALD[700], '#ffffff')).toBeGreaterThanOrEqual(4.5);
  });

  it('emerald.600 sur blanc est réservé aux grands textes (documenté)', () => {
    // Verrouille la contrainte documentée dans BRAND_GUIDELINES.md : le vert de
    // marque ne passe AA en texte normal qu'à partir de emerald.700. Si la
    // couleur change et passe AA, ce test échoue et le guide doit être mis à jour.
    expect(contrastRatio(BRAND_EMERALD[600], '#ffffff')).toBeLessThan(4.5);
  });

  it('emerald.500 sur blanc est réservé au non-textuel (documenté)', () => {
    expect(contrastRatio(BRAND_EMERALD[500], '#ffffff')).toBeLessThan(3);
  });
});
