import { readFileSync } from 'node:fs';
import { join } from 'node:path';

/**
 * Garde de câblage des **fiches détail** (#9705).
 *
 * ## Pourquoi une garde de source et pas un test de rendu
 *
 * Les fiches détail ne sont pas toutes des composants de verticale : trois
 * d'entre elles (`/boutiques/{slug}`, `/stay/{slug}`, `/entreprises/{slug}`)
 * rendent leur markup directement dans la page Next, et `/pharmacies/{slug}`
 * fait de même. Un test de rendu aurait dû monter huit arbres différents avec
 * leurs props de domaine — beaucoup de bruit pour vérifier une seule chose :
 * *le contrôle est-il là ?*
 *
 * La garde lit donc la source et vérifie que chaque fiche **qui liste des
 * entités** monte bien `SurfaceSearchBar` et `SurfacePagination`. C'est
 * exactement le défaut qu'on corrige : les primitives existaient et étaient
 * testées, mais quatre verticales ne les montaient pas. Un test de rendu aurait
 * passé sur les composants sans jamais voir les pages qui ne les utilisent pas.
 *
 * ## Les deux fiches sans liste
 *
 * `travel` et `vtc` n'ont **pas** de liste à filtrer, et ce n'est pas un oubli :
 *
 *  - `/billets/{id}` est une fiche trajet : un itinéraire, un horaire, un plan
 *    de sièges. Le plan est un tableau de bordées, pas une collection
 *    d'entités ; il n'y a rien à paginer ni à chercher.
 *  - `/operateurs/{slug}` expose `PublicVtcOperatorProfile` — quatre champs
 *    scalaires (nom, ville, pays, devise). Aucune collection.
 *
 * Leur ajouter un champ de recherche serait un contrôle qui ne filtre rien :
 * exactement le défaut que `SurfaceSearchBar` documente pour le champ ville de
 * `/emploi`. La garde les déclare donc explicitement comme « sans liste » pour
 * qu'une future verticale ne puisse pas s'y réfugier par accident.
 */

const ROOT = join(__dirname, '..', '..', '..');

interface DetailSurface {
  /** Nom lisible pour le message d'échec. */
  readonly label: string;
  /** Chemin relatif à `front/web/src`. */
  readonly file: string;
  /**
   * `false` quand la fiche n'a aucune collection à filtrer. Justifié dans le
   * commentaire du bloc, pas laissé implicite.
   */
  readonly hasList: boolean;
}

/**
 * Les huit fiches détail, une par verticale.
 *
 * La liste est écrite à la main **volontairement** : elle doit être mise à jour
 * quand une verticale est ajoutée. Une liste dérivée du registre ne
 * signalerait pas l'oubli d'une nouvelle verticale — c'est précisément le
 * piège qui a laissé `chrome-routes.test.ts` passer à côté de deux verticales
 * pendant des semaines.
 */
const DETAIL_SURFACES: readonly DetailSurface[] = [
  {
    label: 'restaurants — menu du restaurant',
    file: 'modules/public-surfaces/restaurants/components/RestaurantOrderPanel.tsx',
    hasList: true,
  },
  {
    label: 'retail — catégories de la boutique',
    file: 'app/boutiques/[slug]/page.tsx',
    hasList: true,
  },
  {
    label: 'hospitality — types de chambre',
    file: 'app/stay/[slug]/page.tsx',
    hasList: true,
  },
  {
    label: 'trade — catégories de produits',
    file: 'app/entreprises/[slug]/page.tsx',
    hasList: true,
  },
  {
    label: 'travel — fiche trajet',
    file: 'modules/public-surfaces/travel/components/TripDetailSurface.tsx',
    hasList: false,
  },
  {
    label: 'pharmacy — services de l’officine',
    file: 'app/pharmacies/[slug]/page.tsx',
    hasList: true,
  },
  {
    label: 'vtc — fiche opérateur',
    file: 'app/operateurs/[slug]/page.tsx',
    hasList: false,
  },
  {
    label: 'careers — offres de l’entreprise',
    file: 'modules/public-surfaces/careers/components/CompanyJobsSurface.tsx',
    hasList: true,
  },
];

const WITH_LIST = DETAIL_SURFACES.filter((surface) => surface.hasList);
const WITHOUT_LIST = DETAIL_SURFACES.filter((surface) => !surface.hasList);

function read(relativePath: string): string {
  return readFileSync(join(ROOT, relativePath), 'utf8');
}

describe('câblage des fiches détail', () => {
  it('couvre les huit verticales du registre', () => {
    expect(DETAIL_SURFACES).toHaveLength(8);
  });

  it.each(WITH_LIST)('$label monte un contrôle de recherche', ({ file }) => {
    const source = read(file);

    // Deux formes acceptées : la primitive partagée, ou un champ de recherche
    // natif quand la fiche est un composant client (le menu restaurant filtre
    // en mémoire, il n'a pas d'URL à produire).
    const hasControl =
      source.includes('SurfaceSearchBar') ||
      source.includes('type="search"') ||
      source.includes("type='search'");

    expect(hasControl).toBe(true);
  });

  it.each(WITH_LIST)('$label monte un contrôle de pagination', ({ file }) => {
    const source = read(file);

    // Le menu restaurant est une carte unique : il n'a pas de pagination, mais
    // il doit au moins filtrer. Les autres fiches listent des entités
    // indépendantes et doivent paginer.
    if (file.includes('RestaurantOrderPanel')) {
      expect(source).toContain('menuQuery');
      return;
    }

    expect(source).toContain('SurfacePagination');
  });

  it('les fiches paginées calculent leur découpage côté serveur', () => {
    // Une pagination calculée dans le navigateur laisserait la page 2 invisible
    // pour un crawler et cassée sans JavaScript — le défaut que
    // `SurfacePagination` a corrigé sur les annuaires.
    for (const { file, label } of WITH_LIST) {
      if (file.includes('RestaurantOrderPanel')) {
        continue;
      }

      const source = read(file);

      expect({ label, usesPaginateList: source.includes('paginateList') }).toEqual({
        label,
        usesPaginateList: true,
      });
    }
  });

  it('les fiches filtrent avec le noyau partagé, pas une comparaison locale', () => {
    for (const { file, label } of WITH_LIST) {
      const source = read(file);

      const usesSharedFilter =
        source.includes('filterByQuery') ||
        source.includes('matchesQuery') ||
        source.includes('normalizeQuery');

      expect({ label, usesSharedFilter }).toEqual({ label, usesSharedFilter: true });
    }
  });

  it.each(WITHOUT_LIST)(
    '$label n’a pas de liste — aucun contrôle de filtre ne doit y apparaître',
    ({ file }) => {
      const source = read(file);

      // Un champ de recherche sur une fiche sans collection serait un contrôle
      // qui ne filtre rien. Si une liste apparaît un jour sur ces fiches, ce
      // test échoue et force à câbler les primitives au lieu de les oublier.
      expect(source).not.toContain('SurfaceSearchBar');
      expect(source).not.toContain('SurfacePagination');
    },
  );
});
