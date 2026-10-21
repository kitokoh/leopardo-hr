'use client';

import { useState } from 'react';
import { MessageSquare } from 'lucide-react';
import type { AppLocale } from '@/lib/i18n';
import { t } from '@/lib/i18n/locale-catalog';
import ContactDialog from './contact-dialog';

/**
 * P1-02 — bouton « Contacter » réutilisable depuis une fiche publique.
 *
 * Les fiches verticales (`/boutiques/{slug}`, `/operateurs/{slug}`) sont des
 * Server Components : elles ne peuvent pas ouvrir la modale elles-mêmes. Ce
 * composant client est le SEUL point d'entrée du formulaire de contact hors
 * `/decouvrir`, et il réutilise `ContactDialog` tel quel — aucune duplication
 * du formulaire, du contrat `POST /public/contact-requests` ni de la clé
 * d'idempotence.
 *
 * Le destinataire est identifié par le couple (`target_type`, `target_slug`),
 * exactement comme depuis la découverte : aucun `company_id` ne transite par le
 * navigateur, le tenant est résolu côté serveur.
 */

interface PublicContactCtaProps {
  locale: AppLocale;
  targetType: string;
  targetSlug: string;
  targetName: string;
}

export default function PublicContactCta({
  locale,
  targetType,
  targetSlug,
  targetName,
}: PublicContactCtaProps) {
  const [open, setOpen] = useState(false);

  return (
    <>
      <button
        type="button"
        onClick={() => setOpen(true)}
        className="inline-flex items-center gap-2 rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white"
      >
        <MessageSquare className="h-4 w-4" aria-hidden />
        {t(locale, 'discovery.contact')}
      </button>

      {open && (
        <ContactDialog
          locale={locale}
          targetType={targetType}
          targetSlug={targetSlug}
          targetName={targetName}
          onClose={() => setOpen(false)}
        />
      )}
    </>
  );
}
