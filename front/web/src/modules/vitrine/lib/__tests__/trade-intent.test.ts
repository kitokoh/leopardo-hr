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
    expect(DEFAULT_TRADE_INTENT).toEqual({ mode: 'domestic', action: 'create', profile: null });
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
      profile: null,
    });
  });

  it('only keeps a profile that belongs to the chosen market', () => {
    // BOS-O-01 / #8625 — « grossiste » sur un marche international est une
    // intention incoherente : elle est ecartee cote client, puis refusee par
    // l'API si elle parvient malgre tout au serveur.
    expect(normalizeTradeIntent({ mode: 'international', profile: 'importer' }).profile).toBe(
      'importer',
    );
    expect(normalizeTradeIntent({ mode: 'international', profile: 'wholesaler' }).profile).toBeNull();
    expect(normalizeTradeIntent({ mode: 'domestic', profile: 'retailer' }).profile).toBe('retailer');
    expect(normalizeTradeIntent({ mode: 'domestic', profile: 'importer' }).profile).toBeNull();
    expect(normalizeTradeIntent({ mode: 'domestic', profile: 'forged' }).profile).toBeNull();
  });

  it('is idempotent: the same input yields the same stable key', () => {
    const a = normalizeTradeIntent({ mode: 'international', action: 'create' });
    const b = normalizeTradeIntent({ mode: 'international', action: 'create' });
    expect(tradeIntentKey(a)).toBe(tradeIntentKey(b));
    expect(tradeIntentKey(a)).toBe('trade:international:create:-');
  });

  it('includes the profile in the dedup key', () => {
    // Deux profils differents sont deux intentions differentes ; deux clics
    // sur le meme profil restent une seule intention.
    const importer = normalizeTradeIntent({ mode: 'international', profile: 'importer' });
    const exporter = normalizeTradeIntent({ mode: 'international', profile: 'exporter' });
    expect(tradeIntentKey(importer)).not.toBe(tradeIntentKey(exporter));
    expect(tradeIntentKey(importer)).toBe(
      tradeIntentKey(normalizeTradeIntent({ mode: 'international', profile: 'importer' })),
    );
  });

  it('routes create to the existing signup tunnel with the activity mode', () => {
    expect(tradeCreateHref('international')).toBe('/signup?trade=international');
    expect(tradeCreateHref('domestic', 'en')).toBe('/signup?trade=domestic&lang=en');
  });

  it('carries the professional profile through the create link', () => {
    // BOS-O-02 — sans ce parametre, le profil choisi restait une intention
    // purement cosmetique : le tenant etait provisionne sans marche ni profil.
    expect(tradeCreateHref('international', undefined, 'importer')).toBe(
      '/signup?trade=international&profile=importer',
    );
    expect(tradeCreateHref('domestic', 'fr', 'wholesaler')).toBe(
      '/signup?trade=domestic&profile=wholesaler&lang=fr',
    );
    expect(tradeCreateHref('domestic', undefined, null)).toBe('/signup?trade=domestic');
  });

  it('routes join to the existing login (no second tenant is created)', () => {
    expect(tradeJoinHref()).toBe('/auth/login');
    expect(tradeJoinHref('tr')).toBe('/auth/login?lang=tr');
  });
});
