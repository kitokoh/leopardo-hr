/**
 * #9580 (BC-41 BRANDING) — barre des capacités de la surface.
 *
 * ## Ce que ce composant rend, et ce qu'il ne promet pas
 *
 * Il affiche les capacités DÉCLARÉES par le profil de la verticale, dans
 * l'ordre de pertinence (CTA primaire en tête). Il ne les rend pas
 * cliquables vers un parcours qui n'existe pas encore : chaque capacité est
 * un repère de ce que la surface sait faire, et le parcours réel (panier,
 * réservation) est porté par les blocs de la verticale.
 *
 * C'est une décision assumée : un bouton « Commander » qui mène à une page
 * vide est pire qu'un libellé qui annonce le service. Quand un bloc de
 * verticale fournira le parcours, c'est lui qui portera le lien.
 */

import type { SurfaceCapability } from '../domain/brand';

/** Libellés des capacités — registre fermé, aligné sur l'API. */
const CAPABILITY_LABELS: Record<SurfaceCapability, string> = {
  order: 'Commander',
  reserve: 'Réserver',
  book: 'Prendre rendez-vous',
  quote: 'Demander un devis',
  prescription: 'Ordonnance',
  ride: 'Réserver un trajet',
  contact: 'Contact',
  directions: 'Itinéraire',
  hours: 'Horaires',
  ticket: 'Billets',
};

interface SurfaceCapabilityBarProps {
  capabilities: string[];
  primaryCta: string | null;
}

export function SurfaceCapabilityBar({ capabilities, primaryCta }: SurfaceCapabilityBarProps) {
  if (capabilities.length === 0) {
    return null;
  }

  return (
    <nav aria-label="Services disponibles" className="border-y border-slate-200/70">
      <ul className="mx-auto flex max-w-6xl flex-wrap gap-2 px-6 py-4">
        {capabilities.map((capability) => {
          const isPrimary = capability === primaryCta;
          const label = CAPABILITY_LABELS[capability as SurfaceCapability] ?? capability;

          return (
            <li key={capability}>
              <span
                className="inline-flex items-center rounded-full px-4 py-2 text-sm font-medium"
                style={
                  isPrimary
                    ? {
                        background: 'var(--brand-primary)',
                        color: 'var(--brand-on-primary)',
                      }
                    : {
                        background: 'color-mix(in srgb, var(--brand-primary) 10%, transparent)',
                        color: 'var(--brand-primary)',
                      }
                }
              >
                {label}
              </span>
            </li>
          );
        })}
      </ul>
    </nav>
  );
}
