import {
  TENANT_BRANDING_EVENT,
  TENANT_BRANDING_STORAGE_KEY,
  clearTenantBrandingCache,
  readCachedTenantBranding,
  storeTenantBranding,
  type TenantBranding,
} from '@/lib/tenant-branding';

/**
 * #7860 — cache localStorage du branding tenant + canal d'événement
 * `tenant-branding-updated` : le shell hydrate ses couleurs/logo depuis le
 * cache à chaque connexion, et la page /settings/branding notifie le layout
 * SANS rechargement après une sauvegarde réussie.
 */

const sampleBranding: TenantBranding = {
  display_name: 'TechCorp',
  logo_url: 'https://cdn.example/logo.png',
  primary_color: '#155e75',
  accent_color: '#67e8f9',
  primary_color_foreground: '#FFFFFF',
  brand_mode: 'default',
};

beforeEach(() => {
  window.localStorage.clear();
});

describe('readCachedTenantBranding', () => {
  it('retourne null quand le cache est vide', () => {
    expect(readCachedTenantBranding()).toBeNull();
  });

  it('retourne null sur un JSON corrompu (sans lever)', () => {
    window.localStorage.setItem(TENANT_BRANDING_STORAGE_KEY, '{oops');
    expect(readCachedTenantBranding()).toBeNull();
  });

  it('retourne null sur un payload qui ne respecte pas le contrat', () => {
    window.localStorage.setItem(TENANT_BRANDING_STORAGE_KEY, JSON.stringify({ hello: 'world' }));
    expect(readCachedTenantBranding()).toBeNull();
  });

  it('relit un branding précédemment stocké', () => {
    storeTenantBranding(sampleBranding);
    expect(readCachedTenantBranding()).toEqual(sampleBranding);
  });
});

describe('storeTenantBranding', () => {
  it('écrit le cache sous la clé tenant_branding', () => {
    storeTenantBranding(sampleBranding);
    const raw = window.localStorage.getItem(TENANT_BRANDING_STORAGE_KEY);
    expect(raw).not.toBeNull();
    expect(JSON.parse(raw as string)).toEqual(sampleBranding);
  });

  it('réduit le payload aux champs du contrat (logo_path & co exclus)', () => {
    storeTenantBranding({ ...sampleBranding, logo_path: 'x/y.png', logo_disk: 'public' } as TenantBranding);
    expect(readCachedTenantBranding()).toEqual(sampleBranding);
  });

  it("émet le CustomEvent 'tenant-branding-updated' avec le branding en detail", () => {
    const listener = jest.fn();
    window.addEventListener(TENANT_BRANDING_EVENT, listener);
    try {
      storeTenantBranding(sampleBranding);
      expect(listener).toHaveBeenCalledTimes(1);
      const event = listener.mock.calls[0][0] as CustomEvent<TenantBranding | null>;
      expect(event.detail).toEqual(sampleBranding);
    } finally {
      window.removeEventListener(TENANT_BRANDING_EVENT, listener);
    }
  });

  it('ignore un branding malformé (cache jamais pollué, aucun événement)', () => {
    const listener = jest.fn();
    window.addEventListener(TENANT_BRANDING_EVENT, listener);
    try {
      storeTenantBranding({ primary_color: 42 } as unknown as TenantBranding);
      expect(window.localStorage.getItem(TENANT_BRANDING_STORAGE_KEY)).toBeNull();
      expect(listener).not.toHaveBeenCalled();
    } finally {
      window.removeEventListener(TENANT_BRANDING_EVENT, listener);
    }
  });
});

describe('clearTenantBrandingCache', () => {
  it('vide le cache et notifie avec detail null (déconnexion)', () => {
    storeTenantBranding(sampleBranding);

    const listener = jest.fn();
    window.addEventListener(TENANT_BRANDING_EVENT, listener);
    try {
      clearTenantBrandingCache();
      expect(window.localStorage.getItem(TENANT_BRANDING_STORAGE_KEY)).toBeNull();
      expect(readCachedTenantBranding()).toBeNull();
      expect(listener).toHaveBeenCalledTimes(1);
      const event = listener.mock.calls[0][0] as CustomEvent<TenantBranding | null>;
      expect(event.detail).toBeNull();
    } finally {
      window.removeEventListener(TENANT_BRANDING_EVENT, listener);
    }
  });
});

/**
 * BOS-B-01 — `primary_color_foreground` (couleur de texte lisible calculée
 * côté serveur, WCAG 2.1) doit survivre au cache : sans elle, le shell ne
 * peut pas poser `--tenant-on-primary` et un `primary_color` clair rend le
 * libellé des boutons illisible.
 */
describe('primary_color_foreground (BOS-B-01)', () => {
  it('persiste la couleur de texte fournie par le serveur', () => {
    storeTenantBranding(sampleBranding);
    expect(readCachedTenantBranding()?.primary_color_foreground).toBe('#FFFFFF');
  });

  it('accepte un cache antérieur sans le champ (rétro-compatibilité)', () => {
    const legacy = {
      display_name: 'TechCorp',
      logo_url: null,
      primary_color: '#155e75',
      accent_color: '#67e8f9',
      brand_mode: 'default',
    };
    window.localStorage.setItem(TENANT_BRANDING_STORAGE_KEY, JSON.stringify(legacy));
    const read = readCachedTenantBranding();
    expect(read).not.toBeNull();
    expect(read?.primary_color_foreground).toBeUndefined();
  });

  it('normalise un champ absent en null à l\'écriture', () => {
    storeTenantBranding({
      display_name: 'TechCorp',
      logo_url: null,
      primary_color: '#155e75',
      accent_color: '#67e8f9',
      brand_mode: 'default',
    });
    expect(readCachedTenantBranding()?.primary_color_foreground).toBeNull();
  });

  it('rejette un champ de type invalide (cache jamais pollué)', () => {
    window.localStorage.setItem(
      TENANT_BRANDING_STORAGE_KEY,
      JSON.stringify({ ...sampleBranding, primary_color_foreground: 42 }),
    );
    expect(readCachedTenantBranding()).toBeNull();
  });
});
