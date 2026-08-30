'use client';

/**
 * RESTO-704 (#6217) — UI admin web : écrans référentiel RestaurantManager.
 * Navigateur à onglets sur les 11 ressources du référentiel
 * (`/restaurant/branches`, `zones`, `tables`, `categories`, `products`,
 * `ingredients`, `units`, `menus`, `tax-rates`, `suppliers`, `hours`),
 * CRUD générique config-driven (RestaurantCrudTable).
 */
import { useState } from 'react';
import { BookOpen } from 'lucide-react';
import { ModulePageShell } from '@/components/module-page-shell';
import { RestaurantCrudTable, type CrudConfig } from '@/components/restaurant/RestaurantCrudTable';
import { getPreferredLocale } from '@/lib/i18n';
import { t } from '@/lib/i18n/locale-catalog';

const STATUS_OPTIONS = [
  { value: 'active', label: 'Active' },
  { value: 'disabled', label: 'Désactivée' },
];

export default function RestaurantReferentialPage() {
  const locale = getPreferredLocale();
  const [tab, setTab] = useState('branches');

  const configs: Record<string, CrudConfig> = {
    branches: {
      endpoint: '/restaurant/branches',
      title: t(locale, 'restaurant.ref.branches', 'Branches (établissements)'),
      searchKeys: ['code', 'name', 'city'],
      columns: [
        { key: 'code', label: 'Code' },
        { key: 'name', label: 'Nom' },
        { key: 'city', label: 'Ville' },
        { key: 'currency', label: 'Devise' },
        { key: 'status', label: 'Statut' },
      ],
      fields: [
        { name: 'code', label: 'Code', type: 'text', required: true },
        { name: 'name', label: 'Nom', type: 'text', required: true },
        { name: 'address', label: 'Adresse', type: 'text' },
        { name: 'city', label: 'Ville', type: 'text' },
        { name: 'phone', label: 'Téléphone', type: 'text' },
        { name: 'currency', label: 'Devise (3 lettres)', type: 'text' },
        { name: 'status', label: 'Statut', type: 'select', options: STATUS_OPTIONS },
      ],
    },
    zones: {
      endpoint: '/restaurant/zones',
      title: t(locale, 'restaurant.ref.zones', 'Zones (salles)'),
      searchKeys: ['name'],
      columns: [
        { key: 'name', label: 'Nom' },
        { key: 'branch_id', label: 'Branche' },
        { key: 'color', label: 'Couleur' },
        { key: 'sort_order', label: 'Ordre' },
        { key: 'status', label: 'Statut' },
      ],
      fields: [
        { name: 'branch_id', label: 'Branche ID', type: 'number', required: true },
        { name: 'name', label: 'Nom', type: 'text', required: true },
        { name: 'color', label: 'Couleur', type: 'text' },
        { name: 'sort_order', label: 'Ordre', type: 'number' },
        { name: 'status', label: 'Statut', type: 'select', options: STATUS_OPTIONS },
      ],
    },
    tables: {
      endpoint: '/restaurant/tables',
      title: t(locale, 'restaurant.ref.tables', 'Tables (plan de salle)'),
      searchKeys: ['label'],
      columns: [
        { key: 'label', label: 'Table' },
        { key: 'branch_id', label: 'Branche' },
        { key: 'zone_id', label: 'Zone' },
        { key: 'capacity', label: 'Capacité' },
        { key: 'status', label: 'Statut' },
      ],
      fields: [
        { name: 'branch_id', label: 'Branche ID', type: 'number', required: true },
        { name: 'zone_id', label: 'Zone ID', type: 'number' },
        { name: 'label', label: 'Libellé', type: 'text', required: true },
        { name: 'capacity', label: 'Capacité (couverts)', type: 'number', required: true, min: 1 },
        { name: 'status', label: 'Statut', type: 'select', options: STATUS_OPTIONS },
      ],
    },
    categories: {
      endpoint: '/restaurant/categories',
      title: t(locale, 'restaurant.ref.categories', 'Catégories'),
      searchKeys: ['name'],
      columns: [
        { key: 'name', label: 'Nom' },
        { key: 'sort_order', label: 'Ordre' },
        { key: 'status', label: 'Statut' },
      ],
      fields: [
        { name: 'name', label: 'Nom', type: 'text', required: true },
        { name: 'sort_order', label: 'Ordre', type: 'number' },
        { name: 'status', label: 'Statut', type: 'select', options: STATUS_OPTIONS },
      ],
    },
    products: {
      endpoint: '/restaurant/products',
      title: t(locale, 'restaurant.ref.products', 'Produits (catalogue & recettes)'),
      searchKeys: ['code', 'name'],
      columns: [
        { key: 'code', label: 'Code' },
        { key: 'name', label: 'Nom' },
        { key: 'category_id', label: 'Catégorie' },
        { key: 'price_minor', label: 'Prix (minor)' },
        { key: 'is_available', label: 'Disponible' },
        { key: 'status', label: 'Statut' },
      ],
      fields: [
        { name: 'code', label: 'Code', type: 'text', required: true },
        { name: 'name', label: 'Nom', type: 'text', required: true },
        { name: 'category_id', label: 'Catégorie ID', type: 'number', required: true },
        { name: 'price_minor', label: 'Prix (minor units)', type: 'number', required: true, min: 0 },
        { name: 'description_redacted', label: 'Description', type: 'textarea' },
        { name: 'is_available', label: 'Disponible', type: 'select', options: [{ value: 'true', label: 'Oui' }, { value: 'false', label: 'Non' }] },
        { name: 'status', label: 'Statut', type: 'select', options: STATUS_OPTIONS },
      ],
    },
    ingredients: {
      endpoint: '/restaurant/ingredients',
      title: t(locale, 'restaurant.ref.ingredients', 'Ingrédients (matières)'),
      searchKeys: ['code', 'name'],
      columns: [
        { key: 'code', label: 'Code' },
        { key: 'name', label: 'Nom' },
        { key: 'unit_code', label: 'Unité' },
        { key: 'avg_cost_minor', label: 'Coût moyen' },
        { key: 'status', label: 'Statut' },
      ],
      fields: [
        { name: 'code', label: 'Code', type: 'text', required: true },
        { name: 'name', label: 'Nom', type: 'text', required: true },
        { name: 'unit_code', label: 'Unité (kg/l/u/pce)', type: 'text', required: true },
        { name: 'avg_cost_minor', label: 'Coût moyen (minor)', type: 'number', min: 0 },
        { name: 'status', label: 'Statut', type: 'select', options: STATUS_OPTIONS },
      ],
    },
    units: {
      endpoint: '/restaurant/units',
      title: t(locale, 'restaurant.ref.units', 'Unités'),
      searchKeys: ['code', 'label'],
      columns: [
        { key: 'code', label: 'Code' },
        { key: 'label', label: 'Libellé' },
        { key: 'status', label: 'Statut' },
      ],
      fields: [
        { name: 'code', label: 'Code', type: 'text', required: true },
        { name: 'label', label: 'Libellé', type: 'text', required: true },
        { name: 'status', label: 'Statut', type: 'select', options: STATUS_OPTIONS },
      ],
    },
    menus: {
      endpoint: '/restaurant/menus',
      title: t(locale, 'restaurant.ref.menus', 'Menus (formules)'),
      searchKeys: ['code', 'name'],
      columns: [
        { key: 'code', label: 'Code' },
        { key: 'name', label: 'Nom' },
        { key: 'price_minor', label: 'Prix' },
        { key: 'status', label: 'Statut' },
      ],
      fields: [
        { name: 'code', label: 'Code', type: 'text', required: true },
        { name: 'name', label: 'Nom', type: 'text', required: true },
        { name: 'price_minor', label: 'Prix (minor)', type: 'number', required: true, min: 0 },
        { name: 'starts_at', label: 'Début', type: 'datetime' },
        { name: 'ends_at', label: 'Fin', type: 'datetime' },
        { name: 'status', label: 'Statut', type: 'select', options: STATUS_OPTIONS },
      ],
    },
    taxRates: {
      endpoint: '/restaurant/tax-rates',
      title: t(locale, 'restaurant.ref.taxRates', 'Taux de TVA'),
      searchKeys: ['code', 'label'],
      columns: [
        { key: 'code', label: 'Code' },
        { key: 'label', label: 'Libellé' },
        { key: 'rate_bps', label: 'Taux (bps)' },
        { key: 'is_default', label: 'Défaut' },
      ],
      fields: [
        { name: 'code', label: 'Code', type: 'text', required: true },
        { name: 'label', label: 'Libellé', type: 'text', required: true },
        { name: 'rate_bps', label: 'Taux (points de base)', type: 'number', required: true, min: 0 },
        { name: 'is_default', label: 'Par défaut', type: 'select', options: [{ value: 'true', label: 'Oui' }, { value: 'false', label: 'Non' }] },
      ],
    },
    suppliers: {
      endpoint: '/restaurant/suppliers',
      title: t(locale, 'restaurant.ref.suppliers', 'Fournisseurs'),
      searchKeys: ['name', 'contact_phone'],
      columns: [
        { key: 'name', label: 'Nom' },
        { key: 'contact_phone', label: 'Téléphone' },
        { key: 'status', label: 'Statut' },
      ],
      fields: [
        { name: 'name', label: 'Nom', type: 'text', required: true },
        { name: 'contact_phone', label: 'Téléphone', type: 'text' },
        { name: 'email', label: 'Email', type: 'text' },
        { name: 'address', label: 'Adresse', type: 'text' },
        { name: 'status', label: 'Statut', type: 'select', options: STATUS_OPTIONS },
      ],
    },
    hours: {
      endpoint: '/restaurant/hours',
      title: t(locale, 'restaurant.ref.hours', 'Horaires'),
      searchKeys: ['day_of_week'],
      columns: [
        { key: 'branch_id', label: 'Branche' },
        { key: 'day_of_week', label: 'Jour' },
        { key: 'opens_at', label: 'Ouverture' },
        { key: 'closes_at', label: 'Fermeture' },
        { key: 'is_closed', label: 'Fermé' },
      ],
      fields: [
        { name: 'branch_id', label: 'Branche ID', type: 'number', required: true },
        { name: 'day_of_week', label: 'Jour (0-6)', type: 'number', required: true, min: 0 },
        { name: 'opens_at', label: 'Ouverture (HH:MM)', type: 'text' },
        { name: 'closes_at', label: 'Fermeture (HH:MM)', type: 'text' },
        { name: 'is_closed', label: 'Fermé', type: 'select', options: [{ value: 'true', label: 'Oui' }, { value: 'false', label: 'Non' }] },
      ],
    },
  };

  const tabs = [
    { key: 'branches', label: t(locale, 'restaurant.ref.tabBranches', 'Branches') },
    { key: 'zones', label: t(locale, 'restaurant.ref.tabZones', 'Zones') },
    { key: 'tables', label: t(locale, 'restaurant.ref.tabTables', 'Tables') },
    { key: 'categories', label: t(locale, 'restaurant.ref.tabCategories', 'Catégories') },
    { key: 'products', label: t(locale, 'restaurant.ref.tabProducts', 'Produits') },
    { key: 'ingredients', label: t(locale, 'restaurant.ref.tabIngredients', 'Ingrédients') },
    { key: 'units', label: t(locale, 'restaurant.ref.tabUnits', 'Unités') },
    { key: 'menus', label: t(locale, 'restaurant.ref.tabMenus', 'Menus') },
    { key: 'taxRates', label: t(locale, 'restaurant.ref.tabTaxRates', 'TVA') },
    { key: 'suppliers', label: t(locale, 'restaurant.ref.tabSuppliers', 'Fournisseurs') },
    { key: 'hours', label: t(locale, 'restaurant.ref.tabHours', 'Horaires') },
  ];

  return (
    <ModulePageShell
      icon={BookOpen}
      title={t(locale, 'restaurant.ref.title', 'Référentiel restaurant')}
      description={t(locale, 'restaurant.ref.subtitle', 'Branches, plan de salle, catalogue, matières, menus, fiscalité, fournisseurs')}
    >
      <div className="flex flex-wrap gap-2">
        {tabs.map((tb) => (
          <button
            key={tb.key}
            type="button"
            onClick={() => setTab(tb.key)}
            className={`rounded-lg px-3 py-1.5 text-sm font-medium ${
              tab === tb.key ? 'bg-emerald-600 text-white' : 'border border-slate-200 bg-white text-slate-700 hover:bg-slate-50'
            }`}
          >
            {tb.label}
          </button>
        ))}
      </div>
      <RestaurantCrudTable key={tab} config={configs[tab]} />
    </ModulePageShell>
  );
}
