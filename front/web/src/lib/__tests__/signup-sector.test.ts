import {
  SIGNUP_SECTOR_STORAGE_KEY,
  clearSignupSector,
  isSignupSector,
  peekSignupSector,
  persistSignupSector,
  readSignupSectorFromUrl,
} from '@/lib/signup-sector';

/**
 * #8454 — métier présélectionné à l'inscription vitrine.
 *
 * Verrouillé : la validation fail-closed de l'allowlist (miroir serveur
 * `SetupInterviewPlanner`), la lecture du `?sector=`, et le cycle
 * capture → consommation → effacement en sessionStorage.
 */
describe('signup-sector (#8454)', () => {
  beforeEach(() => {
    window.sessionStorage.clear();
  });

  it('valide strictement l’allowlist des secteurs', () => {
    expect(isSignupSector('restaurant')).toBe(true);
    expect(isSignupSector('fuel_station')).toBe(true);
    expect(isSignupSector('association')).toBe(true);
    // Fail-closed : toute valeur hors liste est rejetée (jamais de réponse
    // d'entretien forgée).
    expect(isSignupSector('restaurant; DROP TABLE')).toBe(false);
    expect(isSignupSector('RESTAURANT')).toBe(false);
    expect(isSignupSector('')).toBe(false);
    expect(isSignupSector(null)).toBe(false);
    expect(isSignupSector(undefined)).toBe(false);
  });

  it('lit le ?sector= d’une query string, null si absent ou invalide', () => {
    expect(readSignupSectorFromUrl('?sector=restaurant&source=packs')).toBe('restaurant');
    expect(readSignupSectorFromUrl('?source=packs')).toBeNull();
    expect(readSignupSectorFromUrl('?sector=hacker')).toBeNull();
    expect(readSignupSectorFromUrl('')).toBeNull();
  });

  it('cycle complet : persistance, lecture, effacement', () => {
    expect(peekSignupSector()).toBeNull();

    persistSignupSector('fuel_station');
    expect(window.sessionStorage.getItem(SIGNUP_SECTOR_STORAGE_KEY)).toBe('fuel_station');
    expect(peekSignupSector()).toBe('fuel_station');

    clearSignupSector();
    expect(peekSignupSector()).toBeNull();
    expect(window.sessionStorage.getItem(SIGNUP_SECTOR_STORAGE_KEY)).toBeNull();
  });

  it('ignore une valeur altérée directement dans le storage', () => {
    window.sessionStorage.setItem(SIGNUP_SECTOR_STORAGE_KEY, 'not-a-sector');
    expect(peekSignupSector()).toBeNull();
  });
});
