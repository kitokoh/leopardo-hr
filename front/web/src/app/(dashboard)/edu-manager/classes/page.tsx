'use client';

import { apiFetch } from '@/lib/api-client';
import CrudPage, { type CrudResource, type FieldOption } from '../_components/edu-crud';

async function loadCampuses(): Promise<FieldOption[]> {
  try {
    const res = await apiFetch('/edu-manager/campuses?per_page=200');
    const json = (await res.json()) as { data?: { id: number; name: string }[] };
    return (json.data ?? []).map((campus) => ({ value: String(campus.id), label: campus.name }));
  } catch {
    return [];
  }
}

async function loadAcademicYears(): Promise<FieldOption[]> {
  try {
    const res = await apiFetch('/edu-manager/academic-years?per_page=200');
    const json = (await res.json()) as { data?: { id: number; name: string }[] };
    return (json.data ?? []).map((year) => ({ value: String(year.id), label: year.name }));
  } catch {
    return [];
  }
}

async function loadTeachers(): Promise<FieldOption[]> {
  try {
    const res = await apiFetch('/employees?per_page=200');
    const json = (await res.json()) as { data?: { id: number; first_name?: string | null; last_name?: string | null; name?: string | null }[] };
    return (json.data ?? [])
      .map((employee) => ({
        value: String(employee.id),
        label: [employee.first_name, employee.last_name].filter(Boolean).join(' ') || employee.name || String(employee.id),
      }))
      .sort((a, b) => a.label.localeCompare(b.label));
  } catch {
    return [];
  }
}

const resource: CrudResource = {
  path: '/edu-manager/classes',
  titleKey: 'edu.classes.title',
  subtitleKey: 'edu.classes.subtitle',
  emptyKey: 'edu.classes.empty',
  createKey: 'edu.classes.create',
  editKey: 'edu.classes.edit',
  statusField: 'status',
  rowKey: (row) => String(row.id),
  fields: [
    { kind: 'select', name: 'campus_id', label: 'edu.classes.campus', required: true, optionsLoader: loadCampuses },
    { kind: 'select', name: 'academic_year_id', label: 'edu.classes.year', required: true, optionsLoader: loadAcademicYears },
    { kind: 'text', name: 'code', label: 'edu.classes.code', required: true },
    { kind: 'text', name: 'name', label: 'edu.classes.name', required: true },
    { kind: 'text', name: 'level', label: 'edu.classes.level' },
    { kind: 'select', name: 'teacher_id', label: 'edu.classes.teacher', optionsLoader: loadTeachers, placeholder: 'edu.classes.teacherPlaceholder' },
    { kind: 'number', name: 'capacity', label: 'edu.classes.capacity', min: 1 },
    {
      kind: 'select',
      name: 'status',
      label: 'edu.classes.status',
      options: [
        { value: 'active', label: 'active' },
        { value: 'inactive', label: 'inactive' },
      ],
    },
  ],
  columns: [
    { key: 'code', header: 'edu.classes.code', render: (row) => <span className="font-mono text-xs font-bold text-slate-500">{String(row.code)}</span> },
    { key: 'name', header: 'edu.classes.name', render: (row) => <span className="font-bold text-slate-900">{String(row.name)}</span> },
    { key: 'level', header: 'edu.classes.level', render: (row) => <span className="text-slate-500">{String(row.level ?? '—')}</span> },
    {
      key: 'campus',
      header: 'edu.classes.campus',
      render: (row) => {
        const campus = (row.campus as { name?: string } | null) ?? null;
        return <span className="text-slate-500">{campus?.name ?? '—'}</span>;
      },
    },
    { key: 'teacher_id', header: 'edu.classes.teacher', render: (row) => <span className="text-slate-500">{row.teacher_id != null ? String(row.teacher_id) : '—'}</span> },
  ],
};

export default function ClassesPage() {
  return <CrudPage resource={resource} />;
}
