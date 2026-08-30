'use client';

import CrudPage, { type CrudResource } from '../_components/edu-crud';

const resource: CrudResource = {
  path: '/edu-manager/campuses',
  titleKey: 'edu.campuses.title',
  subtitleKey: 'edu.campuses.subtitle',
  emptyKey: 'edu.campuses.empty',
  createKey: 'edu.campuses.create',
  editKey: 'edu.campuses.edit',
  statusField: 'status',
  rowKey: (row) => String(row.id),
  fields: [
    { kind: 'text', name: 'code', label: 'edu.campuses.code', required: true },
    { kind: 'text', name: 'name', label: 'edu.campuses.name', required: true },
    { kind: 'text', name: 'address', label: 'edu.campuses.address' },
    { kind: 'text', name: 'timezone', label: 'edu.campuses.timezone', placeholder: 'UTC' },
    {
      kind: 'select',
      name: 'status',
      label: 'edu.campuses.status',
      options: [
        { value: 'active', label: 'active' },
        { value: 'inactive', label: 'inactive' },
        { value: 'archived', label: 'archived' },
      ],
    },
  ],
  columns: [
    { key: 'code', header: 'edu.campuses.code', render: (row) => <span className="font-mono text-xs font-bold text-slate-500">{String(row.code)}</span> },
    { key: 'name', header: 'edu.campuses.name', render: (row) => <span className="font-bold text-slate-900">{String(row.name)}</span> },
    { key: 'address', header: 'edu.campuses.address', render: (row) => <span className="text-slate-500">{String(row.address ?? '—')}</span> },
    { key: 'timezone', header: 'edu.campuses.timezone', render: (row) => <span className="text-slate-500">{String(row.timezone ?? 'UTC')}</span> },
  ],
};

export default function CampusesPage() {
  return <CrudPage resource={resource} />;
}
