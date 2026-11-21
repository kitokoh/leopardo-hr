import Link from 'next/link';

import type { SurfaceIdentity } from '../domain/surface-identity';

/**
 * Pagination d'un annuaire de vitrine (#9657).
 *
 * ## Le défaut que ce composant corrige
 *
 * Les six adaptateurs d'annuaire (`retail`, `hospitality`, `trade`, `pharmacy`,
 * `vtc`, `careers`) acceptaient déjà un paramètre `page`, et l'API publique
 * renvoie `last_page` dans son `meta`. Mais **aucune vitrine n'exposait de
 * contrôle de pagination** : un visiteur ne pouvait atteindre que la première
 * page. Les résultats suivants existaient, étaient indexables, et étaient
 * inaccessibles depuis l'interface.
 *
 * ## Pourquoi des liens et pas des boutons
 *
 * Les annuaires sont rendus côté serveur et indexables. Une pagination en
 * `<a href>` est crawlable, partageable, et fonctionne sans JavaScript — c'est
 * exactement ce qu'on attend d'une page publique. Un `<button onClick>` aurait
 * rendu la page 2 invisible pour un moteur de recherche et cassée pour un
 * visiteur dont le JS échoue.
 *
 * ## Ce que le composant ne fait pas
 *
 * Il ne décide pas de la composition : il ne s'affiche que si `lastPage > 1`,
 * et il conserve les filtres courants (`q`, `city`…) en ne réécrivant que
 * `page`. Une pagination qui perdrait le filtre en cours renverrait le visiteur
 * à une liste non filtrée — pire que pas de pagination.
 */
export function SurfacePagination({
  identity,
  basePath,
  page,
  lastPage,
  searchParams,
  labels,
}: {
  identity: SurfaceIdentity;
  /** Chemin de l'annuaire (`/boutiques`), sans query. */
  basePath: string;
  /** Page courante, 1-indexée. */
  page: number;
  /** Dernière page connue de l'API. */
  lastPage: number;
  /** Filtres courants à conserver dans les liens. */
  searchParams?: Readonly<Record<string, string | undefined>>;
  labels: { previous: string; next: string; pageOf: string };
}) {
  // Une seule page : aucun contrôle. Afficher « Page 1 sur 1 » n'aide personne
  // et ajoute du bruit sur les verticales encore peu alimentées.
  if (lastPage <= 1) {
    return null;
  }

  const current = Math.min(Math.max(1, page), lastPage);

  const hrefFor = (target: number): string => {
    const params = new URLSearchParams();
    for (const [key, value] of Object.entries(searchParams ?? {})) {
      if (value !== undefined && value !== '' && key !== 'page') {
        params.set(key, value);
      }
    }
    if (target > 1) {
      params.set('page', String(target));
    }
    const qs = params.toString();
    return qs ? `${basePath}?${qs}` : basePath;
  };

  const { palette } = identity;

  const linkStyle = (enabled: boolean) => ({
    display: 'inline-block',
    padding: '0.55rem 1rem',
    borderRadius: '0.6rem',
    border: `1px solid ${palette.muted}55`,
    color: enabled ? palette.onSurface : palette.muted,
    fontWeight: 600,
    fontSize: '0.9rem',
    textDecoration: 'none',
    // Un lien désactivé reste un `<span>` : un `<a>` sans `href` n'est pas
    // focusable et n'est pas annoncé comme un lien par un lecteur d'écran.
    opacity: enabled ? 1 : 0.5,
    pointerEvents: enabled ? ('auto' as const) : ('none' as const),
  });

  return (
    <nav
      aria-label={labels.pageOf.replace('{page}', String(current)).replace('{last}', String(lastPage))}
      data-surface-pagination=""
      style={{
        marginTop: '2rem',
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'space-between',
        gap: '1rem',
        flexWrap: 'wrap',
      }}
    >
      {current > 1 ? (
        <Link href={hrefFor(current - 1)} rel="prev" style={linkStyle(true)}>
          ← {labels.previous}
        </Link>
      ) : (
        <span aria-disabled="true" style={linkStyle(false)}>
          ← {labels.previous}
        </span>
      )}

      <span style={{ color: palette.muted, fontSize: '0.85rem' }}>
        {labels.pageOf.replace('{page}', String(current)).replace('{last}', String(lastPage))}
      </span>

      {current < lastPage ? (
        <Link href={hrefFor(current + 1)} rel="next" style={linkStyle(true)}>
          {labels.next} →
        </Link>
      ) : (
        <span aria-disabled="true" style={linkStyle(false)}>
          {labels.next} →
        </span>
      )}
    </nav>
  );
}
