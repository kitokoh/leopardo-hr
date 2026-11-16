'use client';

/**
 * Issue #9331 — panneau « Rôles personnalisés » d'entreprise, exposant le
 * contrat RoleDefinitionController (IAM-L5 #9095).
 *
 * Contrat API :
 *   GET /api/v1/company/roles        → liste rôles actifs + catalogue DelegablePermission
 *   POST /api/v1/company/roles       → créer un rôle nommé
 *   PUT /api/v1/company/roles/{id}   → modifier
 *   DELETE /api/v1/company/roles/{id} → révoquer
 *
 * Réservé au principal (le backend refuse 403 sinon).
 */

import { useCallback, useEffect, useState } from 'react';
import { apiFetch } from '@/lib/api-client';
import type { AppLocale } from '@/lib/i18n';
import { t } from '@/lib/i18n/locale-catalog';

type Permission = {
  key: string;
  label: string;
  category?: string;
};

type CustomRole = {
  id: number;
  name: string;
  permissions: string[];
  holder_count?: number;
};

type CompanyRolesPayload = {
  data?: {
    roles?: CustomRole[];
    catalog?: Permission[];
  };
};

type ErrorPayload = {
  error?: string;
  excess?: string[];
  message?: string;
};

type Props = {
  locale: AppLocale;
  onSaved?: (message: string) => void;
};

export function CustomRolesPanel({ locale, onSaved }: Props) {
  const [roles, setRoles] = useState<CustomRole[]>([]);
  const [catalog, setCatalog] = useState<Permission[]>([]);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);
  
  // État pour la création/édition
  const [showEditor, setShowEditor] = useState(false);
  const [editingRole, setEditingRole] = useState<CustomRole | null>(null);
  const [draftName, setDraftName] = useState('');
  const [draftPermissions, setDraftPermissions] = useState<string[]>([]);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);

    try {
      const response = await apiFetch('/company/roles', { _cacheBust: true });
      if (!response.ok) {
        if (response.status === 403) {
          setError(t(locale, 'employees.customRoles.forbidden', 'Accès réservé au principal'));
          return;
        }
        setError(t(locale, 'employees.customRoles.loadError', 'Erreur de chargement'));
        return;
      }
      const payload = (await response.json()) as CompanyRolesPayload;
      setRoles(Array.isArray(payload.data?.roles) ? payload.data.roles : []);
      setCatalog(Array.isArray(payload.data?.catalog) ? payload.data.catalog : []);
    } catch (err) {
      setError(t(locale, 'employees.customRoles.loadError', 'Erreur de chargement'));
    } finally {
      setLoading(false);
    }
  }, [locale]);

  useEffect(() => {
    void load();
  }, [load]);

  const startCreate = () => {
    setEditingRole(null);
    setDraftName('');
    setDraftPermissions([]);
    setShowEditor(true);
    setError(null);
  };

  const startEdit = (role: CustomRole) => {
    setEditingRole(role);
    setDraftName(role.name);
    setDraftPermissions([...role.permissions]);
    setShowEditor(true);
    setError(null);
  };

  const cancelEdit = () => {
    setShowEditor(false);
    setEditingRole(null);
    setDraftName('');
    setDraftPermissions([]);
    setError(null);
  };

  const togglePermission = (key: string) => {
    setDraftPermissions((current) =>
      current.includes(key) ? current.filter((p) => p !== key) : [...current, key],
    );
  };

  const saveRole = async () => {
    if (!draftName.trim()) {
      setError(t(locale, 'employees.customRoles.nameRequired', 'Le nom est requis'));
      return;
    }

    setSaving(true);
    setError(null);

    try {
      const method = editingRole ? 'PUT' : 'POST';
      const url = editingRole ? `/company/roles/${editingRole.id}` : '/company/roles';
      
      const response = await apiFetch(url, {
        method,
        body: JSON.stringify({
          name: draftName.trim(),
          permissions: draftPermissions,
        }),
      });

      if (!response.ok) {
        const errorPayload = (await response.json().catch(() => ({}))) as ErrorPayload;
        
        if (response.status === 422 && errorPayload.error === 'DELEGATION_EXCEEDS_ACTOR') {
          const excess = errorPayload.excess?.join(', ') ?? '';
          setError(
            t(
              locale,
              'employees.customRoles.delegationExceeds',
              `Vous ne pouvez pas déléguer : ${excess}`,
            ),
          );
          return;
        }
        
        if (response.status === 403 && errorPayload.error === 'DELEGATION_FORBIDDEN') {
          setError(t(locale, 'employees.customRoles.delegationForbidden', 'Délégation interdite'));
          return;
        }

        setError(errorPayload.message ?? t(locale, 'employees.customRoles.saveError', 'Erreur d\'enregistrement'));
        return;
      }

      onSaved?.(
        editingRole
          ? t(locale, 'employees.customRoles.roleUpdated', 'Rôle mis à jour')
          : t(locale, 'employees.customRoles.roleCreated', 'Rôle créé'),
      );
      
      cancelEdit();
      await load();
    } catch (err) {
      setError(t(locale, 'employees.customRoles.saveError', 'Erreur d\'enregistrement'));
    } finally {
      setSaving(false);
    }
  };

  const deleteRole = async (roleId: number, roleName: string) => {
    if (!confirm(t(locale, 'employees.customRoles.confirmDelete', `Supprimer le rôle "${roleName}" ?`))) {
      return;
    }

    setSaving(true);
    setError(null);

    try {
      const response = await apiFetch(`/company/roles/${roleId}`, {
        method: 'DELETE',
      });

      if (!response.ok) {
        setError(t(locale, 'employees.customRoles.deleteError', 'Erreur de suppression'));
        return;
      }

      onSaved?.(t(locale, 'employees.customRoles.roleDeleted', 'Rôle supprimé'));
      await load();
    } catch (err) {
      setError(t(locale, 'employees.customRoles.deleteError', 'Erreur de suppression'));
    } finally {
      setSaving(false);
    }
  };

  // Grouper les permissions par catégorie
  const permissionsByCategory = catalog.reduce(
    (acc, perm) => {
      const category = perm.category ?? 'other';
      if (!acc[category]) acc[category] = [];
      acc[category].push(perm);
      return acc;
    },
    {} as Record<string, Permission[]>,
  );

  return (
    <div
      data-testid="custom-roles-panel"
      className="rounded-2xl border border-app-border bg-white p-4"
    >
      <div className="flex items-center justify-between">
        <div>
          <p className="text-xs font-bold uppercase tracking-wider text-slate-600">
            {t(locale, 'employees.customRoles.title', 'Rôles personnalisés')}
          </p>
          <p className="mt-1 text-xs text-slate-500">
            {t(locale, 'employees.customRoles.hint', 'Créez des rôles avec des permissions spécifiques')}
          </p>
        </div>
        {!loading && !showEditor && (
          <button
            type="button"
            onClick={startCreate}
            disabled={saving}
            data-testid="create-role-button"
            className="rounded-xl bg-brand-700 px-4 py-2 text-xs font-bold text-white transition hover:bg-brand-800 disabled:opacity-60"
          >
            {t(locale, 'employees.customRoles.createNew', 'Nouveau rôle')}
          </button>
        )}
      </div>

      {error ? <p className="mt-3 text-xs font-medium text-red-600">{error}</p> : null}

      {loading ? (
        <p className="mt-3 text-xs text-slate-500">{t(locale, 'employees.customRoles.loading', 'Chargement...')}</p>
      ) : showEditor ? (
        <div className="mt-4 space-y-4">
          <div>
            <label htmlFor="role-name" className="block text-xs font-medium text-slate-700">
              {t(locale, 'employees.customRoles.roleName', 'Nom du rôle')}
            </label>
            <input
              id="role-name"
              type="text"
              value={draftName}
              onChange={(e) => setDraftName(e.target.value)}
              data-testid="role-name-input"
              placeholder={t(locale, 'employees.customRoles.roleNamePlaceholder', 'Ex: Chef de rang')}
              className="mt-1 w-full rounded-xl border border-app-border px-3 py-2 text-sm"
            />
          </div>

          <div>
            <p className="text-xs font-medium text-slate-700">
              {t(locale, 'employees.customRoles.permissions', 'Permissions')}
            </p>
            <div className="mt-2 space-y-3">
              {Object.entries(permissionsByCategory).map(([category, perms]) => (
                <div key={category} className="rounded-xl border border-app-border bg-slate-50 p-3">
                  <p className="text-xs font-semibold text-slate-600 uppercase tracking-wide">
                    {t(locale, `employees.customRoles.category.${category}`, category)}
                  </p>
                  <div className="mt-2 grid gap-2 sm:grid-cols-2">
                    {perms.map((perm) => (
                      <label
                        key={perm.key}
                        className="flex items-center gap-2 text-xs font-medium text-slate-700"
                      >
                        <input
                          type="checkbox"
                          checked={draftPermissions.includes(perm.key)}
                          onChange={() => togglePermission(perm.key)}
                          data-testid={`permission-${perm.key}`}
                          className="h-4 w-4 rounded border-app-border text-brand-700 focus:ring-brand-500"
                        />
                        {perm.label}
                      </label>
                    ))}
                  </div>
                </div>
              ))}
            </div>
          </div>

          <div className="flex gap-2">
            <button
              type="button"
              onClick={() => void saveRole()}
              disabled={saving}
              data-testid="save-role-button"
              className="rounded-xl bg-brand-700 px-4 py-2 text-xs font-bold text-white transition hover:bg-brand-800 disabled:opacity-60"
            >
              {editingRole
                ? t(locale, 'employees.customRoles.update', 'Mettre à jour')
                : t(locale, 'employees.customRoles.create', 'Créer')}
            </button>
            <button
              type="button"
              onClick={cancelEdit}
              disabled={saving}
              className="rounded-xl border border-app-border bg-white px-4 py-2 text-xs font-bold text-slate-700 transition hover:bg-slate-50 disabled:opacity-60"
            >
              {t(locale, 'employees.customRoles.cancel', 'Annuler')}
            </button>
          </div>
        </div>
      ) : roles.length === 0 ? (
        <p className="mt-3 text-xs text-slate-500">
          {t(locale, 'employees.customRoles.empty', 'Aucun rôle personnalisé')}
        </p>
      ) : (
        <div className="mt-3 space-y-2">
          {roles.map((role) => (
            <div
              key={role.id}
              data-testid={`role-${role.id}`}
              className="flex flex-col gap-2 rounded-xl border border-app-border bg-slate-50 px-3 py-2 sm:flex-row sm:items-center sm:justify-between"
            >
              <div>
                <p className="text-sm font-semibold text-slate-800">{role.name}</p>
                <p className="text-xs text-slate-500">
                  {role.permissions.length} {t(locale, 'employees.customRoles.permissionsCount', 'permissions')}
                  {role.holder_count !== undefined && role.holder_count > 0
                    ? ` · ${role.holder_count} ${t(locale, 'employees.customRoles.holders', 'porteurs')}`
                    : ''}
                </p>
              </div>
              <div className="flex gap-2">
                <button
                  type="button"
                  onClick={() => startEdit(role)}
                  disabled={saving}
                  data-testid={`edit-role-${role.id}`}
                  className="rounded-lg border border-app-border px-3 py-1 text-xs font-semibold text-slate-700 transition hover:bg-white disabled:opacity-60"
                >
                  {t(locale, 'employees.customRoles.edit', 'Modifier')}
                </button>
                <button
                  type="button"
                  onClick={() => void deleteRole(role.id, role.name)}
                  disabled={saving}
                  data-testid={`delete-role-${role.id}`}
                  className="rounded-lg border border-red-200 px-3 py-1 text-xs font-semibold text-red-600 transition hover:bg-red-50 disabled:opacity-60"
                >
                  {t(locale, 'employees.customRoles.delete', 'Supprimer')}
                </button>
              </div>
            </div>
          ))}
        </div>
      )}
    </div>
  );
}
