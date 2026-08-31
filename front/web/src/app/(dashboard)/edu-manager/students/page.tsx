'use client';

import { Lock } from 'lucide-react';
import CrudPage, { type CrudResource } from '../_components/edu-crud';

const resource: CrudResource = {
  path: '/edu-manager/students',
  titleKey: 'edu.students.title',
  subtitleKey: 'edu.students.subtitle',
  emptyKey: 'edu.students.empty',
  createKey: 'edu.students.create',
  editKey: 'edu.students.edit',
  statusField: 'status',
  rowKey: (row) => String(row.id),
  fields: [
    { kind: 'text', name: 'student_number', label: 'edu.students.number', required: true },
    { kind: 'text', name: 'display_name', label: 'edu.students.name', required: true },
    {
      kind: 'select',
      name: 'status',
      label: 'edu.students.status',
      options: [
        { value: 'active', label: 'active' },
        { value: 'inactive', label: 'inactive' },
        { value: 'archived', label: 'archived' },
      ],
    },
  ],
  columns: [
    { key: 'student_number', header: 'edu.students.number', render: (row) => <span className="font-mono text-xs font-bold text-slate-500">{String(row.student_number)}</span> },
    { key: 'display_name', header: 'edu.students.name', render: (row) => <span className="font-bold text-slate-900">{String(row.display_name)}</span> },
    {
      key: 'pii',
      header: 'PII',
      render: () => (
        <span className="inline-flex items-center gap-1 text-[10px] font-bold uppercase tracking-widest text-slate-400">
          <Lock className="h-3 w-3" aria-hidden="true" />
          chiffré
        </span>
      ),
    },
  ],
};

export default function StudentsPage() {
  return <CrudPage resource={resource} />;
}
