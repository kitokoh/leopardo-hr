import type { SurfaceIdentity } from '../domain/surface-identity';

/**
 * Barre de recherche d'un annuaire de vitrine (#9657).
 *
 * ## Le défaut que ce composant corrige
 *
 * Les six adaptateurs d'annuaire acceptaient déjà `q` et `city`, et les pages
 * les lisaient depuis l'URL. Mais **aucun annuaire n'affichait de champ** : le
 * filtre n'était atteignable qu'en éditant l'URL à la main. Un visiteur qui
 * cherchait « boulangerie à Lyon » n'avait aucun moyen de le dire à la page.
 *
 * ## Pourquoi un `<form method="get">` et pas un état React
 *
 * L'annuaire est rendu côté serveur et indexable. Un formulaire GET natif :
 *
 *  - fonctionne sans JavaScript (le parcours ne casse pas si un chunk échoue) ;
 *  - produit une URL partageable et crawlable (`/boutiques?q=boulangerie`) ;
 *  - ne duplique pas la logique de filtrage déjà présente côté serveur.
 *
 * Un `onChange` + `router.push` aurait fait la même chose en plus fragile.
 *
 * ## Ce que le composant ne fait pas
 *
 * Il ne décide pas de la composition : il rend deux champs et deux boutons,
 * avec les libellés de la locale courante. Chaque verticale l'insère où elle
 * veut dans sa page.
 */
export function SurfaceSearchBar({
  identity,
  basePath,
  defaultQuery,
  defaultCity,
  labels,
}: {
  identity: SurfaceIdentity;
  /** Chemin de l'annuaire (`/boutiques`), sans query. */
  basePath: string;
  defaultQuery?: string;
  defaultCity?: string;
  labels: {
    searchLabel: string;
    searchPlaceholder: string;
    cityLabel: string;
    cityPlaceholder: string;
    searchSubmit: string;
    searchReset: string;
  };
}) {
  const { palette } = identity;

  const fieldStyle = {
    padding: '0.6rem 0.8rem',
    borderRadius: '0.6rem',
    border: `1px solid ${palette.muted}55`,
    background: '#FFFFFF',
    color: palette.onSurface,
    fontSize: '0.9rem',
    minWidth: 0,
  } as const;

  const hasFilter = Boolean(defaultQuery || defaultCity);

  return (
    <form
      role="search"
      method="get"
      action={basePath}
      data-surface-search=""
      style={{
        display: 'flex',
        flexWrap: 'wrap',
        alignItems: 'flex-end',
        gap: '0.75rem',
        margin: '0 0 1.75rem',
      }}
    >
      <label style={{ display: 'flex', flexDirection: 'column', gap: '0.3rem', flex: '2 1 14rem' }}>
        <span style={{ fontSize: '0.75rem', fontWeight: 700, color: palette.muted }}>
          {labels.searchLabel}
        </span>
        <input
          type="search"
          name="q"
          defaultValue={defaultQuery ?? ''}
          placeholder={labels.searchPlaceholder}
          style={fieldStyle}
        />
      </label>

      <label style={{ display: 'flex', flexDirection: 'column', gap: '0.3rem', flex: '1 1 10rem' }}>
        <span style={{ fontSize: '0.75rem', fontWeight: 700, color: palette.muted }}>
          {labels.cityLabel}
        </span>
        <input
          type="search"
          name="city"
          defaultValue={defaultCity ?? ''}
          placeholder={labels.cityPlaceholder}
          style={fieldStyle}
        />
      </label>

      <button
        type="submit"
        style={{
          padding: '0.6rem 1.1rem',
          borderRadius: '0.6rem',
          border: 'none',
          background: palette.primary,
          color: palette.onPrimary,
          fontWeight: 700,
          fontSize: '0.9rem',
          cursor: 'pointer',
          minHeight: '44px',
        }}
      >
        {labels.searchSubmit}
      </button>

      {/* Le bouton de réinitialisation n'apparaît que s'il y a quelque chose à
          réinitialiser : un bouton inerte sur un annuaire vierge est du bruit. */}
      {hasFilter ? (
        <a
          href={basePath}
          style={{
            padding: '0.6rem 0.9rem',
            borderRadius: '0.6rem',
            border: `1px solid ${palette.muted}55`,
            color: palette.muted,
            fontWeight: 600,
            fontSize: '0.9rem',
            textDecoration: 'none',
            minHeight: '44px',
            display: 'inline-flex',
            alignItems: 'center',
          }}
        >
          {labels.searchReset}
        </a>
      ) : null}
    </form>
  );
}
