import {
  DEFAULT_TRADE_INTENT,
  isTradeAction,
  isTradeMode,
  normalizeTradeIntent,
  tradeCreateHref,
  tradeIntentKey,
  tradeJoinHref,
} from '../trade-intent';

/**
 * #8490 (I-03) — garde d'idempotence et de fail-closed de l'intention Trade.
 *
 * Deux exigences de la mission :
 *  - « rejoindre » ne doit JAMAIS etre deduit d'une entree illisible (pas de
 *    creation implicite de tenant d'un cote, pas de jointure implicite de
 *    l'autre) ;
 *  - deux normalisations identiques produisent la MEME cle, donc une seule
 *    intention enregistree par choix reel (pas de doublon de provisionnement).
 */
describe('trade-intent', () => {
  it('exposes a fail-closed default that never implies a join', () => {
    expect(DEFAULT_TRADE_INTENT).toEqual({ mode: 'domestic', action: 'create' });
  });

  it('accepts only the allowlisted modes and actions', () => {
    expect(isTradeMode('international')).toBe(true);
    expect(isTradeMode('domestic')).toBe(true);
    expect(isTradeMode('wholesale')).toBe(false);
    expect(isTradeAction('create')).toBe(true);
    expect(isTradeAction('join')).toBe(true);
    expect(isTradeAction('delete')).toBe(false);
  });

  it('normalizes unknown or missing input to the default (no forged join)', () => {
    expect(normalizeTradeIntent(null)).toEqual(DEFAULT_TRADE_INTENT);
    expect(normalizeTradeIntent(undefined)).toEqual(DEFAULT_TRADE_INTENT);
    expect(normalizeTradeIntent({ mode: 'bogus', action: 'also-bogus' })).toEqual(
      DEFAULT_TRADE_INTENT,
    );
    expect(normalizeTradeIntent({ mode: 'international' })).toEqual({
      mode: 'international',
      action: 'create',
    });
  });

  it('is idempotent: the same input yields the same stable key', () => {
    const a = normalizeTradeIntent({ mode: 'international', action: 'create' });
    const b = normalizeTradeIntent({ mode: 'international', action: 'create' });
    expect(tradeIntentKey(a)).toBe(tradeIntentKey(b));
    expect(tradeIntentKey(a)).toBe('trade:international:create');
  });

  it('routes create to the existing signup tunnel with the activity mode', () => {
    expect(tradeCreateHref('international')).toBe('/signup?trade=international');
    expect(tradeCreateHref('domestic', 'en')).toBe('/signup?trade=domestic&lang=en');
  });

  it('routes join to the existing login (no second tenant is created)', () => {
    expect(tradeJoinHref()).toBe('/auth/login');
    expect(tradeJoinHref('tr')).toBe('/auth/login?lang=tr');
  });
});
