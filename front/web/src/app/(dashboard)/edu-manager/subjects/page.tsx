'use client';

import CrudPage, { type CrudResource } from '../_components/edu-crud';

const resource: CrudResource = {
  path: '/edu-manager/subjects',
  titleKey: 'edu.subjects.title',
  subtitleKey: 'edu.subjects.subtitle',
  emptyKey: 'edu.subjects.empty',
  createKey: 'edu.subjects.create',
  editKey: 'edu.subjects.edit',
  statusField: 'status',
  rowKey: (row) => String(row.id),
  fields: [
    { kind: 'text', name: 'code', label: 'edu.subjects.code', required: true },
    { kind: 'text', name: 'name', label: 'edu.subjects.name', required: true },
    { kind: 'number', name: 'default_coefficient', label: 'edu.subjects.defaultCoefficient', min: 1 },
    {
      kind: 'select',
      name: 'status',
      label: 'edu.subjects.status',
      options: [
        { value: 'active', label: 'active' },
        { value: 'inactive', label: 'inactive' },
        { value: 'archived', label: 'archived' },
      ],
    },
  ],
  columns: [
    { key: 'code', header: 'edu.subjects.code', render: (row) => <span className="font-mono text-xs font-bold text-slate-500">{String(row.code)}</span> },
    { key: 'name', header: 'edu.subjects.name', render: (row) => <span className="font-bold text-slate-900">{String(row.name)}</span> },
    {
      key: 'default_coefficient',
      header: 'edu.subjects.defaultCoefficient',
      render: (row) => <span className="text-slate-500">{row.default_coefficient != null ? String(row.default_coefficient) : '—'}</span>,
    },
  ],
};

export default function SubjectsPage() {
  return <CrudPage resource={resource} />;
}
