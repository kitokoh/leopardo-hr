'use client';

/**
 * Catalogue des types de frais scolaires — /edu-manager/fee-types (#9789).
 *
 * L'API `GET/POST /edu-manager/fee-types` existait (EDU-016, #5832) mais
 * AUCUNE page ne la servait : un directeur ne pouvait pas définir ses tarifs
 * (scolarité, cantine, transport, inscription) depuis l'interface, alors que
 * c'est le préalable de toute facturation scolaire.
 *
 * Réutilise le générateur `CrudPage` (liste, création, édition, suppression,
 * états vide/erreur/chargement) — même contrat que les autres référentiels.
 */

import CrudPage, { type CrudResource } from '../_components/edu-crud';

const resource: CrudResource = {
  path: '/edu-manager/fee-types',
  titleKey: 'edu.feeTypes.title',
  subtitleKey: 'edu.feeTypes.subtitle',
  emptyKey: 'edu.feeTypes.empty',
  createKey: 'edu.feeTypes.create',
  editKey: 'edu.feeTypes.edit',
  rowKey: (row) => String(row.id),
  fields: [
    { kind: 'text', name: 'code', label: 'edu.feeTypes.code', required: true, placeholder: 'TUITION' },
    { kind: 'text', name: 'label', label: 'edu.feeTypes.label', required: true },
    { kind: 'number', name: 'amount', label: 'edu.feeTypes.amount', required: true, min: 0 },
    { kind: 'text', name: 'currency', label: 'edu.feeTypes.currency', required: true, placeholder: 'DZD' },
    {
      kind: 'select',
      name: 'billing_frequency',
      label: 'edu.feeTypes.frequency',
      required: true,
      options: [
        { value: 'once', label: 'once' },
        { value: 'term', label: 'term' },
        { value: 'monthly', label: 'monthly' },
      ],
    },
  ],
  columns: [
    {
      key: 'code',
      header: 'edu.feeTypes.code',
      render: (row) => (
        <span className="font-mono text-xs font-bold text-slate-500">{String(row.code)}</span>
      ),
    },
    {
      key: 'label',
      header: 'edu.feeTypes.label',
      render: (row) => <span className="font-bold text-slate-900">{String(row.label)}</span>,
    },
    {
      key: 'amount',
      header: 'edu.feeTypes.amount',
      render: (row) => (
        <span className="tabular-nums text-slate-700">
          {String(row.amount ?? '0')} {String(row.currency ?? '')}
        </span>
      ),
    },
    {
      key: 'billing_frequency',
      header: 'edu.feeTypes.frequency',
      render: (row) => <span className="text-slate-500">{String(row.billing_frequency ?? '—')}</span>,
    },
  ],
};

export default function FeeTypesPage() {
  return <CrudPage resource={resource} />;
}
