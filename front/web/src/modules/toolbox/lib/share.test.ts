import {
  SHARE_PARAM,
  SHARE_VERSION,
  buildShareUrl,
  decodeToolState,
  encodeToolState,
  mergeWithDefaults,
} from './share';

/**
 * Tests du codec de partage — encodage/décodage d'état dans l'URL.
 *
 * Le point critique : tout ce qui vient de l'URL est NON FIABLE. Ces tests
 * couvrent donc autant le cas nominal que les entrées forgées ou corrompues.
 */

/** Encode un payload arbitraire (pour forger des liens invalides). */
function forge(payload: unknown): string {
  // `btoa` n'accepte que du latin-1 : on échappe d'abord en UTF-8 percent-encoded
  // (jsdom n'expose pas `TextEncoder`).
  const json = JSON.stringify(payload);
  const binary = unescape(encodeURIComponent(json));
  return btoa(binary).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
}

describe('share — encodeToolState', () => {
  it('encode un état simple et le décode à l’identique', () => {
    const values = { weight: '70', height: '175' };
    const encoded = encodeToolState(values);

    expect(encoded).not.toBeNull();
    expect(decodeToolState(encoded)).toEqual(values);
  });

  it('retourne null pour un état vide', () => {
    expect(encodeToolState({})).toBeNull();
  });

  it('écarte les clés non conformes (espaces, caractères spéciaux)', () => {
    const encoded = encodeToolState({ 'bad key': '1', ok_key: '2' });
    expect(decodeToolState(encoded)).toEqual({ ok_key: '2' });
  });

  it('écarte les valeurs non conformes (caractères de contrôle)', () => {
    const encoded = encodeToolState({ a: '1\u0000', b: '2' });
    expect(decodeToolState(encoded)).toEqual({ b: '2' });
  });

  it('produit une chaîne base64url sans caractère réservé d’URL', () => {
    const encoded = encodeToolState({ a: '1', b: '2', c: '3' });
    expect(encoded).toMatch(/^[A-Za-z0-9_-]+$/);
  });

  it('accepte la virgule décimale française', () => {
    const encoded = encodeToolState({ rate: '5,5' });
    expect(decodeToolState(encoded)).toEqual({ rate: '5,5' });
  });
});

describe('share — decodeToolState', () => {
  it('retourne null pour une valeur absente', () => {
    expect(decodeToolState(null)).toBeNull();
    expect(decodeToolState(undefined)).toBeNull();
    expect(decodeToolState('')).toBeNull();
  });

  it('retourne null pour du base64 invalide', () => {
    expect(decodeToolState('!!!not-base64!!!')).toBeNull();
  });

  it('retourne null pour un JSON invalide', () => {
    expect(decodeToolState(forge('pas du json'))).toBeNull();
  });

  it('retourne null pour une version inconnue', () => {
    expect(decodeToolState(forge({ v: 99, s: { a: '1' } }))).toBeNull();
  });

  it('retourne null quand le payload est vide', () => {
    expect(decodeToolState(forge({ v: SHARE_VERSION, s: {} }))).toBeNull();
  });

  it('retourne null quand le paramètre est démesurément long', () => {
    expect(decodeToolState('a'.repeat(5000))).toBeNull();
  });

  it('ignore les entrées forgées non conformes sans échouer', () => {
    const forged = forge({ v: SHARE_VERSION, s: { good: '1', 'bad key': '2', n: 3 } });
    expect(decodeToolState(forged)).toEqual({ good: '1' });
  });
});

describe('share — mergeWithDefaults', () => {
  const defaults = { weight: '70', height: '175' };

  it('retourne les valeurs par défaut quand rien n’est décodé', () => {
    expect(mergeWithDefaults(null, defaults)).toEqual(defaults);
  });

  it('remplace uniquement les clés connues de l’outil', () => {
    const merged = mergeWithDefaults({ weight: '90', injected: 'x' }, defaults);
    expect(merged).toEqual({ weight: '90', height: '175' });
    expect(merged).not.toHaveProperty('injected');
  });

  it('ne mute pas l’objet de valeurs par défaut', () => {
    const original = { ...defaults };
    mergeWithDefaults({ weight: '90' }, defaults);
    expect(defaults).toEqual(original);
  });
});

describe('share — buildShareUrl', () => {
  it('ajoute le paramètre d’état à l’URL', () => {
    const url = buildShareUrl('https://leopardo.test/toolbox/imc', { weight: '80' });
    expect(url).toContain(`${SHARE_PARAM}=`);
    expect(url.startsWith('https://leopardo.test/toolbox/imc?')).toBe(true);
  });

  it('préserve la langue et les paramètres de campagne', () => {
    const url = buildShareUrl('https://leopardo.test/toolbox/imc?lang=en&utm_source=x', {
      weight: '80',
    });
    const params = new URL(url).searchParams;
    expect(params.get('lang')).toBe('en');
    expect(params.get('utm_source')).toBe('x');
    expect(params.get(SHARE_PARAM)).not.toBeNull();
  });

  it('supprime le paramètre quand l’état est vide', () => {
    const url = buildShareUrl('https://leopardo.test/toolbox/imc?s=abc&lang=fr', {});
    const params = new URL(url).searchParams;
    expect(params.get(SHARE_PARAM)).toBeNull();
    expect(params.get('lang')).toBe('fr');
  });

  it('produit une URL qui se décode en l’état d’origine', () => {
    const values = { amount: '1000', rate: '20' };
    const url = buildShareUrl('https://leopardo.test/toolbox/tva', values);
    const encoded = new URL(url).searchParams.get(SHARE_PARAM);
    expect(decodeToolState(encoded)).toEqual(values);
  });
});
