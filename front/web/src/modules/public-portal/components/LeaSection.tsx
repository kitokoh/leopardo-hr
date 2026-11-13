'use client';

/**
 * COULOIR 16 — M3-bis · Section « Léa » de l'onglet Messages.
 *
 * ## Pourquoi Léa est une section SÉPARÉE
 *
 * La spec du couloir impose « Messages (Léa séparée) ». Mélanger l'assistante
 * aux conversations de commerçants ferait croire au client qu'il écrit à un
 * professionnel alors qu'il parle à une IA — et inversement, qu'une réponse
 * d'IA vient d'un commerçant. Les deux groupes sont donc rendus dans deux
 * `<section>` distinctes, chacune avec son libellé accessible.
 *
 * ## Pourquoi la section dit « pas encore disponible »
 *
 * Il n'existe **aucun** point d'entrée public pour l'assistant : la passerelle
 * IA (`POST /api/v1/ai/chat`) exige un contexte tenant (`AITenantInjector`
 * injecte `companyId` depuis l'employé authentifié), et un client grand public
 * n'a pas de tenant. Brancher un champ de saisie sur cette route renverrait une
 * erreur à chaque message.
 *
 * Afficher un champ qui échoue serait un mensonge sur l'état du produit.
 * Afficher pourquoi il n'est pas là est la vérité — et c'est ce que la règle de
 * vérité du couloir demande.
 *
 * ## Ce que ce composant ne fait pas
 *
 * Il n'appelle aucune API. Il ne simule aucune réponse. Il ne prétend pas que
 * Léa « réfléchit ». Le jour où une route publique existera, c'est ce composant
 * qu'on branchera — la séparation visuelle est déjà en place.
 */

import { getPublicLeaCopy } from '@/modules/public-portal/data/lea-copy';

interface LeaSectionProps {
  locale: string;
}

export default function LeaSection({ locale }: LeaSectionProps) {
  const copy = getPublicLeaCopy(locale);

  return (
    <section
      aria-label={copy.leaGroupLabel}
      className="mb-6 rounded-lg border border-dashed border-gray-300 bg-gray-50 p-4"
    >
      <div className="flex items-start gap-3">
        {/* Marqueur visuel distinct : Léa n'est pas un commerçant. */}
        <span
          aria-hidden="true"
          className="mt-0.5 inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-gray-900 text-sm font-semibold text-white"
        >
          L
        </span>

        <div className="min-w-0">
          <h2 className="text-sm font-semibold text-gray-900">{copy.title}</h2>
          <p className="mt-0.5 text-xs text-gray-600">{copy.subtitle}</p>

          <p className="mt-3 text-sm font-medium text-gray-900">{copy.unavailableTitle}</p>
          <p className="mt-1 text-sm text-gray-700">{copy.unavailableBody}</p>
        </div>
      </div>
    </section>
  );
}
