'use client';

import CrudPage, { type CrudResource } from '../_components/edu-crud';

const resource: CrudResource = {
  path: '/edu-manager/academic-years',
  titleKey: 'edu.academicYears.title',
  subtitleKey: 'edu.academicYears.subtitle',
  emptyKey: 'edu.academicYears.empty',
  createKey: 'edu.academicYears.create',
  editKey: 'edu.academicYears.edit',
  statusField: 'status',
  rowKey: (row) => String(row.id),
  fields: [
    { kind: 'text', name: 'name', label: 'edu.academicYears.name', required: true },
    { kind: 'date', name: 'start_date', label: 'edu.academicYears.startDate', required: true },
    { kind: 'date', name: 'end_date', label: 'edu.academicYears.endDate', required: true },
    {
      kind: 'select',
      name: 'status',
      label: 'edu.academicYears.status',
      options: [
        { value: 'active', label: 'active' },
        { value: 'closed', label: 'closed' },
      ],
    },
  ],
  columns: [
    { key: 'name', header: 'edu.academicYears.name', render: (row) => <span className="font-bold text-slate-900">{String(row.name)}</span> },
    { key: 'start_date', header: 'edu.academicYears.startDate', render: (row) => <span className="text-slate-500">{String(row.start_date ?? '—')}</span> },
    { key: 'end_date', header: 'edu.academicYears.endDate', render: (row) => <span className="text-slate-500">{String(row.end_date ?? '—')}</span> },
  ],
};

export default function AcademicYearsPage() {
  return <CrudPage resource={resource} />;
}
