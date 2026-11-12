import Link from 'next/link';
import { ArrowRight, MapPin } from 'lucide-react';
import {
  searchPublicDiscovery,
  type PublicDiscoveryItem,
  type PublicDiscoveryMeta,
} from '@/lib/public-discovery-api';
import type { PublicHomeCopy } from '../data/home-copy';

/**
 * Couloir 16 — M1 (#9372, point G) : « À découvrir près de vous ».
 *
 * ## Source unique
 *
 * Les fiches viennent de `GET /public/discover` — le même contrat que la
 * recherche, aucun appel supplémentaire inventé. Le rendu est serveur : la
 * section est indexable et ne dépend d'aucune permission navigateur.
 *
 * ## Règle de vérité (aucune donnée inventée)
 *
 * - Aucune fiche renvoyée ⇒ le composant rend `null` : la section est ABSENTE,
 *   pas un bloc vide avec un message d'excuse.
 * - Aucune ville, aucun pays, aucune catégorie codés en dur : la requête ne
 *   porte qu'une limite de nombre, la localisation reste côté client.
 * - Les actions affichées viennent EXCLUSIVEMENT de `meta.actions[type]`
 *   (`PublicDiscoverableProvider::supportedActions()`). Un type sans action
 *   déclarée n'affiche aucun bouton — jamais un bouton inopérant.
 *
 * ## Pourquoi pas de bouton « Contacter » ici
 *
 * Le parcours critique impose : fiche → Contacter → téléphone + code SMS.
 * Afficher un bouton de contact sur la home court-circuiterait cette étape et
 * dupliquerait la logique de `contact-dialog.tsx`. La home ne fait qu'ORIENTER
 * vers la fiche ; l'action réelle vit sur la fiche.
 */

interface PublicNearbyDiscoverProps {
  copy: PublicHomeCopy['nearby'];
  /** Nombre de fiches affichées (défaut 6). */
  limit?: number;
}

/** Libellé d'action lisible, dérivé de la clé du contrat. */
const ACTION_LABEL: Record<string, string> = {
  contact: 'Contacter',
  order: 'Commander',
  book: 'Réserver',
  quote: 'Demander un devis',
};

export async function PublicNearbyDiscover({ copy, limit = 6 }: PublicNearbyDiscoverProps) {
  const result = await searchPublicDiscovery({ per_page: limit, page: 1 });

  const items: PublicDiscoveryItem[] = result?.items ?? [];
  const meta: PublicDiscoveryMeta | null = result?.meta ?? null;

  if (items.length === 0) {
    return null;
  }

  return (
    <section aria-labelledby="home-nearby" className="mx-auto max-w-6xl px-4 py-16 sm:px-6">
      <h2
        id="home-nearby"
        className="text-2xl font-black tracking-tight text-slate-950 sm:text-3xl dark:text-white"
      >
        {copy.title}
      </h2>
      <p className="mt-2 text-sm text-slate-500 dark:text-slate-400">{copy.subtitle}</p>

      <ul className="mt-7 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
        {items.map((item) => {
          // Destination réelle uniquement : `url` fournie par le contrat, sinon
          // la fiche publique de la verticale. Jamais de route inventée.
          const href = item.url ?? `/decouvrir?q=${encodeURIComponent(item.name)}`;
          const actions = meta?.actions?.[item.type] ?? [];

          return (
            <li key={`${item.type}:${item.slug}`}>
              <div className="flex h-full flex-col rounded-2xl border border-slate-200 bg-white p-4 shadow-sm transition-shadow hover:shadow-md dark:border-white/10 dark:bg-white/[0.03]">
                <p className="text-sm font-bold text-slate-900 dark:text-white">{item.name}</p>

                {item.city && (
                  <p className="mt-1 flex items-center gap-1 text-xs text-slate-500 dark:text-slate-400">
                    <MapPin className="h-3 w-3 shrink-0" aria-hidden />
                    {item.city}
                  </p>
                )}

                {item.description && (
                  <p className="mt-2 line-clamp-2 text-xs leading-relaxed text-slate-600 dark:text-slate-400">
                    {item.description}
                  </p>
                )}

                {/* Actions : uniquement celles déclarées par la verticale. */}
                {actions.length > 0 && (
                  <ul className="mt-3 flex flex-wrap gap-1.5">
                    {actions.map((action) => (
                      <li
                        key={action}
                        className="rounded-full bg-emerald-50 px-2 py-0.5 text-[11px] font-semibold text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-300"
                      >
                        {ACTION_LABEL[action] ?? action}
                      </li>
                    ))}
                  </ul>
                )}

                <Link
                  href={href}
                  className="mt-auto inline-flex min-h-[44px] items-center gap-1.5 pt-3 text-sm font-bold text-emerald-700 underline-offset-4 hover:underline dark:text-emerald-400"
                >
                  {copy.cta}
                  <ArrowRight className="h-3.5 w-3.5 rtl:rotate-180" aria-hidden />
                </Link>
              </div>
            </li>
          );
        })}
      </ul>
    </section>
  );
}
