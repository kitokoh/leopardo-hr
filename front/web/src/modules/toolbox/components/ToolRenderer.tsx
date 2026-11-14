import React from 'react';
import { ImcForm } from './tools/ImcForm';
import { VatForm } from './tools/VatForm';
import { NetSalaryForm } from './tools/NetSalaryForm';
import { UnitConverterForm } from './tools/UnitConverterForm';
import { PercentForm } from './tools/PercentForm';
import { RegleDeTroisForm } from './tools/RegleDeTroisForm';
import { SurfaceVolumeForm } from './tools/SurfaceVolumeForm';
import { CbmForm } from './tools/CbmForm';
import { MargeForm } from './tools/MargeForm';
import { TvaMultiTauxForm } from './tools/TvaMultiTauxForm';
import { ConvertisseurDevisesForm } from './tools/ConvertisseurDevisesForm';
import { DureeEntreDatesForm } from './tools/DureeEntreDatesForm';
import { AgeForm } from './tools/AgeForm';
import { TauxHoraireForm } from './tools/TauxHoraireForm';
import { CalculDateEcheanceForm } from './tools/CalculDateEcheanceForm';
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
    case 'regle-de-trois':
      return <RegleDeTroisForm copy={copy} common={common} locale={locale} />;
    case 'surface-volume':
      return <SurfaceVolumeForm copy={copy} common={common} locale={locale} />;
    case 'cbm-poids-volumetrique':
      return <CbmForm copy={copy} common={common} locale={locale} />;
    case 'marge-prix-vente':
      return <MargeForm copy={copy} common={common} locale={locale} />;
    case 'tva-multi-taux':
      return <TvaMultiTauxForm copy={copy} common={common} locale={locale} />;
    case 'convertisseur-devises':
      return <ConvertisseurDevisesForm copy={copy} common={common} locale={locale} />;
    case 'duree-entre-dates':
      return <DureeEntreDatesForm copy={copy} common={common} locale={locale} />;
    case 'age':
      return <AgeForm copy={copy} common={common} locale={locale} />;
    case 'taux-horaire':
      return <TauxHoraireForm copy={copy} common={common} locale={locale} />;
    case 'calcul-date-echeance':
      return <CalculDateEcheanceForm copy={copy} common={common} locale={locale} />;
    default:
      return null;
  }
}
