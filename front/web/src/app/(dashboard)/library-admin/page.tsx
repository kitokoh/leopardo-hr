'use client';

import { useEffect, useState } from 'react';
import { Library } from 'lucide-react';

import { ModulePageShell } from '@/components/module-page-shell';
import { getPreferredLocale } from '@/lib/i18n';
import { getLibraryCopy } from '@/modules/library/data/copy';
import { LibraryAdmin } from '@/modules/library/components/LibraryAdmin';

/**
 * Back-office de la bibliothèque (`/library-admin`).
 *
 * Surface PRIVÉE du tenant : elle vit sous `(dashboard)`, donc derrière le
 * proxy d'authentification, et elle est listée dans `PROTECTED_PREFIXES` pour
 * que le service worker ne la mette jamais en cache.
 *
 * Le contrôle d'accès réel est serveur (`LibraryResourcePolicy` +
 * `BelongsToCompany`) : un employé non autorisé reçoit un 403 de l'API, que
 * l'écran affiche comme une erreur de chargement — jamais comme une liste vide
 * trompeuse.
 */
export default function LibraryAdminPage() {
  const [locale, setLocale] = useState('fr');

  // La locale est lue côté client (préférence utilisateur / navigateur) : le
  // rendu initial reste `fr` pour éviter toute divergence d'hydratation.
  useEffect(() => {
    setLocale(getPreferredLocale());
  }, []);

  const copy = getLibraryCopy(locale);

  return (
    <ModulePageShell
      title={copy.admin.title}
      subtitle={copy.admin.subtitle}
      icon={Library}
    >
      <LibraryAdmin copy={copy.admin} />
    </ModulePageShell>
  );
}
