'use client';

/**
 * HealthManager — référentiel structure (HC-002, #7786 / web #7792).
 * Onglets CRUD : services médicaux, salles, lits, spécialités, praticiens
 * et rôles réception/facturation — via le tableau générique HealthCrudTable
 * (même approche config-driven que RestaurantCrudTable).
 */
import { useState } from 'react';
import { ClipboardList } from 'lucide-react';
import { ModulePageShell } from '@/components/module-page-shell';
import { HealthCrudTable, type HealthCrudConfig } from '@/components/health/HealthCrudTable';
import { listDepartments, listRooms } from '@/lib/health-api';
import { getPreferredLocale } from '@/lib/i18n';
import { t } from '@/lib/i18n/locale-catalog';

type TabId = 'departments' | 'rooms' | 'beds' | 'specialties' | 'practitioners' | 'staff-roles';

const TAB_IDS: TabId[] = ['departments', 'rooms', 'beds', 'specialties', 'practitioners', 'staff-roles'];

export default function HealthReferentialPage() {
  const locale = getPreferredLocale();
  const [tab, setTab] = useState<TabId>('departments');

  const departmentsLoader = async () =>
    (await listDepartments()).map((department) => ({ value: department.id, label: department.name }));

  const roomsLoader = async () =>
    (await listRooms()).map((room) => ({ value: room.id, label: `${room.code} — ${room.name}` }));

  const statusOptions = [
    { value: 'active', label: t(locale, 'health.referential.statusValue.active', 'Actif') },
    { value: 'inactive', label: t(locale, 'health.referential.statusValue.inactive', 'Inactif') },
  ];

  // Reconstruit à chaque rendu — sans risque de re-fetch : HealthCrudTable ne
  // dépend que de `config.endpoint` (string stable) pour recharger la liste.
  const configs: Record<TabId, HealthCrudConfig> = {
      departments: {
        endpoint: '/health-manager/departments',
        title: t(locale, 'health.referential.departments', 'Services médicaux'),
        searchKeys: ['name', 'code'],
        columns: [
          { key: 'code', label: t(locale, 'health.referential.code', 'Code') },
          { key: 'name', label: t(locale, 'health.referential.name', 'Nom') },
          { key: 'description', label: t(locale, 'health.referential.description', 'Description') },
          {
            key: 'status',
            label: t(locale, 'health.referential.status', 'Statut'),
            render: (row) => t(locale, `health.referential.statusValue.${String(row.status)}`, String(row.status)),
          },
        ],
        fields: [
          { name: 'name', label: t(locale, 'health.referential.name', 'Nom'), type: 'text', required: true },
          { name: 'code', label: t(locale, 'health.referential.code', 'Code'), type: 'text', required: true },
          { name: 'description', label: t(locale, 'health.referential.description', 'Description'), type: 'textarea' },
          { name: 'status', label: t(locale, 'health.referential.status', 'Statut'), type: 'select', options: statusOptions },
        ],
      },
      rooms: {
        endpoint: '/health-manager/rooms',
        title: t(locale, 'health.referential.rooms', 'Salles'),
        searchKeys: ['name', 'code', 'type'],
        columns: [
          { key: 'code', label: t(locale, 'health.referential.code', 'Code') },
          { key: 'name', label: t(locale, 'health.referential.name', 'Nom') },
          {
            key: 'type',
            label: t(locale, 'health.referential.roomType', 'Type'),
            render: (row) => t(locale, `health.referential.roomTypeValue.${String(row.type)}`, String(row.type)),
          },
          { key: 'department.name', label: t(locale, 'health.referential.department', 'Service') },
          {
            key: 'status',
            label: t(locale, 'health.referential.status', 'Statut'),
            render: (row) => t(locale, `health.referential.statusValue.${String(row.status)}`, String(row.status)),
          },
        ],
        fields: [
          {
            name: 'department_id',
            label: t(locale, 'health.referential.department', 'Service'),
            type: 'select',
            required: true,
            optionsLoader: departmentsLoader,
          },
          { name: 'name', label: t(locale, 'health.referential.name', 'Nom'), type: 'text', required: true },
          { name: 'code', label: t(locale, 'health.referential.code', 'Code'), type: 'text', required: true },
          {
            name: 'type',
            label: t(locale, 'health.referential.roomType', 'Type'),
            type: 'select',
            required: true,
            options: (['consultation', 'hospitalization', 'operating', 'emergency', 'other'] as const).map((type) => ({
              value: type,
              label: t(locale, `health.referential.roomTypeValue.${type}`, type),
            })),
          },
          { name: 'status', label: t(locale, 'health.referential.status', 'Statut'), type: 'select', options: statusOptions },
        ],
      },
      beds: {
        endpoint: '/health-manager/beds',
        title: t(locale, 'health.referential.beds', 'Lits'),
        searchKeys: ['code', 'status'],
        columns: [
          { key: 'code', label: t(locale, 'health.referential.code', 'Code') },
          { key: 'room.name', label: t(locale, 'health.referential.room', 'Salle') },
          {
            key: 'status',
            label: t(locale, 'health.referential.status', 'Statut'),
            render: (row) => t(locale, `health.referential.bedStatusValue.${String(row.status)}`, String(row.status)),
          },
        ],
        fields: [
          {
            name: 'room_id',
            label: t(locale, 'health.referential.room', 'Salle'),
            type: 'select',
            required: true,
            optionsLoader: roomsLoader,
          },
          { name: 'code', label: t(locale, 'health.referential.code', 'Code'), type: 'text', required: true },
          {
            name: 'status',
            label: t(locale, 'health.referential.status', 'Statut'),
            type: 'select',
            options: (['free', 'occupied', 'maintenance'] as const).map((status) => ({
              value: status,
              label: t(locale, `health.referential.bedStatusValue.${status}`, status),
            })),
          },
        ],
      },
      specialties: {
        endpoint: '/health-manager/specialties',
        title: t(locale, 'health.referential.specialties', 'Spécialités'),
        searchKeys: ['name', 'code'],
        columns: [
          { key: 'code', label: t(locale, 'health.referential.code', 'Code') },
          { key: 'name', label: t(locale, 'health.referential.name', 'Nom') },
        ],
        fields: [
          { name: 'name', label: t(locale, 'health.referential.name', 'Nom'), type: 'text', required: true },
          { name: 'code', label: t(locale, 'health.referential.code', 'Code'), type: 'text', required: true },
        ],
      },
      practitioners: {
        endpoint: '/health-manager/practitioners',
        title: t(locale, 'health.referential.practitioners', 'Praticiens'),
        searchKeys: ['full_name', 'license_number', 'title'],
        columns: [
          { key: 'full_name', label: t(locale, 'health.referential.name', 'Nom') },
          {
            key: 'title',
            label: t(locale, 'health.referential.practitionerTitle', 'Titre'),
            render: (row) => t(locale, `health.referential.titleValue.${String(row.title)}`, String(row.title)),
          },
          { key: 'license_number', label: t(locale, 'health.referential.licenseNumber', 'N° de licence') },
          { key: 'department.name', label: t(locale, 'health.referential.department', 'Service') },
          {
            key: 'status',
            label: t(locale, 'health.referential.status', 'Statut'),
            render: (row) => t(locale, `health.referential.statusValue.${String(row.status)}`, String(row.status)),
          },
        ],
        fields: [
          {
            name: 'employee_id',
            label: t(locale, 'health.referential.employeeId', 'ID employé'),
            type: 'number',
            required: true,
            min: 1,
          },
          {
            name: 'department_id',
            label: t(locale, 'health.referential.department', 'Service'),
            type: 'select',
            optionsLoader: departmentsLoader,
          },
          {
            name: 'title',
            label: t(locale, 'health.referential.practitionerTitle', 'Titre'),
            type: 'select',
            required: true,
            options: (['dr', 'pr', 'midwife', 'nurse', 'other'] as const).map((title) => ({
              value: title,
              label: t(locale, `health.referential.titleValue.${title}`, title),
            })),
          },
          { name: 'license_number', label: t(locale, 'health.referential.licenseNumber', 'N° de licence'), type: 'text' },
          { name: 'status', label: t(locale, 'health.referential.status', 'Statut'), type: 'select', options: statusOptions },
        ],
      },
      'staff-roles': {
        endpoint: '/health-manager/staff-roles',
        title: t(locale, 'health.referential.staffRoles', 'Rôles réception & facturation'),
        searchKeys: ['role', 'employee_name'],
        canEdit: false,
        columns: [
          { key: 'employee_id', label: t(locale, 'health.referential.employeeId', 'ID employé') },
          { key: 'employee_name', label: t(locale, 'health.referential.name', 'Nom') },
          {
            key: 'role',
            label: t(locale, 'health.referential.role', 'Rôle'),
            render: (row) => t(locale, `health.referential.roleValue.${String(row.role)}`, String(row.role)),
          },
        ],
        fields: [
          {
            name: 'employee_id',
            label: t(locale, 'health.referential.employeeId', 'ID employé'),
            type: 'number',
            required: true,
            min: 1,
          },
          {
            name: 'role',
            label: t(locale, 'health.referential.role', 'Rôle'),
            type: 'select',
            required: true,
            options: (['reception', 'billing'] as const).map((role) => ({
              value: role,
              label: t(locale, `health.referential.roleValue.${role}`, role),
            })),
          },
        ],
      },
  };

  return (
    <ModulePageShell
      icon={ClipboardList}
      title={t(locale, 'health.referential.title', 'Référentiel')}
      description={t(locale, 'health.referential.subtitle', 'Structure de l’établissement : services, salles, lits, spécialités, praticiens et rôles.')}
    >
      <div className="space-y-5">
        <div className="flex flex-wrap gap-2" role="tablist" aria-label={t(locale, 'health.referential.title', 'Référentiel')}>
          {TAB_IDS.map((id) => (
            <button
              key={id}
              type="button"
              role="tab"
              aria-selected={tab === id}
              onClick={() => setTab(id)}
              className={`rounded-full px-4 py-2 text-sm font-bold transition ${
                tab === id
                  ? 'bg-gradient-to-r from-emerald-600 to-cyan-600 text-white shadow'
                  : 'border border-slate-200 bg-white/70 text-slate-600 hover:bg-slate-50'
              }`}
            >
              {t(locale, `health.referential.tab.${id}`, id)}
            </button>
          ))}
        </div>

        <HealthCrudTable key={tab} config={configs[tab]} />
      </div>
    </ModulePageShell>
  );
}
