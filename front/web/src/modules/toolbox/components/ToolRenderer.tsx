import React from 'react';
import { ImcForm } from './tools/ImcForm';
import { VatForm } from './tools/VatForm';
import { NetSalaryForm } from './tools/NetSalaryForm';
import { UnitConverterForm } from './tools/UnitConverterForm';
import { PercentForm } from './tools/PercentForm';
import type { ToolCopy, ToolboxCopy, ToolboxLocale } from '../data/copy';

interface ToolRendererProps {
  slug: string;
  copy: ToolCopy;
  common: ToolboxCopy['common'];
  locale: ToolboxLocale;
}

/**
 * Sélecteur de formulaire — associe un slug à son formulaire dédié.
 *
 * Composant SERVEUR : il ne fait que choisir le composant client à monter.
 * Ajouter un outil = ajouter une entrée ici + son dossier `tools/<slug>/`.
 */
export function ToolRenderer({ slug, copy, common, locale }: ToolRendererProps) {
  switch (slug) {
    case 'imc':
      return <ImcForm copy={copy} common={common} locale={locale} />;
    case 'tva':
      return <VatForm copy={copy} common={common} locale={locale} />;
    case 'salaire-net':
      return <NetSalaryForm copy={copy} common={common} locale={locale} />;
    case 'convertisseur-unites':
      return <UnitConverterForm copy={copy} common={common} locale={locale} />;
    case 'pourcentage':
      return <PercentForm copy={copy} common={common} locale={locale} />;
    default:
      return null;
  }
}
