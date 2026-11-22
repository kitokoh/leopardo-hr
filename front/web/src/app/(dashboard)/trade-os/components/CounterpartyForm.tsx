'use client';

import { useEffect, useMemo, useState } from 'react';
import { Button } from '@/components/ui/Button';
import { t } from '@/lib/i18n/locale-catalog';
import type { AppLocale } from '@/lib/i18n';
import {
  COUNTERPARTY_ROLES,
  EMPTY_COUNTERPARTY_DRAFT,
  type CounterpartyDraft,
  type CounterpartyRole,
  type TradeCounterparty,
} from './types';

/**
 * Formulaire de contrepartie Trade — #9780.
 *
 * Sert à la CRÉATION (`POST`) et à l'ÉDITION (`PUT`) : le mode est décidé par
 * l'appelant, qui fournit `initial` (la contrepartie éditée) ou `null`. Le
 * formulaire n'émet aucune requête lui-même — il produit un payload, et c'est
 * la page qui choisit la méthode HTTP et l'URL.
 *
 * ## Ce que le formulaire n'envoie jamais
 *
 * - **`verification_status`** : la vérification est un acte distinct et tracé
 *   (`POST /trade/counterparties/{id}/verify`). L'accepter ici permettrait de
 *   s'auto-déclarer « vérifié » en une requête — le serveur le refuse aussi
 *   (`StoreTradeCounterpartyRequest` ne le valide pas).
 * - **`company_id`** : posé par le serveur depuis le contexte tenant.
 * - **Un rôle inventé** : le registre est fermé, la case à cocher est la seule
 *   source possible.
 *
 * ## Validation locale
 *
 * Elle ne remplace pas celle du serveur : elle évite un aller-retour évident
 * (nom vide, aucun rôle, email mal formé). Le serveur reste l'autorité.
 *
 * ## Retrait d'un rôle déjà coché
 *
 * Décocher un rôle que la fiche possède déjà n'est pas une correction de
 * frappe : c'est un changement de ce que la contrepartie peut faire (un
 * fournisseur qui n'est plus fournisseur ne peut plus être choisi sur une
 * demande de prix). Le formulaire l'annonce à l'écran dès la décoche, puis
 * demande une confirmation au moment d'enregistrer — un clic de trop ne doit
 * pas retirer un rôle en silence.
 */

type Props = {
  locale: AppLocale;
  initial?: TradeCounterparty | null;
  saving: boolean;
  error: string | null;
  onSubmit: (payload: Record<string, unknown>) => void;
  onCancel: () => void;
};

function draftFrom(counterparty?: TradeCounterparty | null): CounterpartyDraft {
  if (!counterparty) return EMPTY_COUNTERPARTY_DRAFT;

  return {
    name: counterparty.name ?? '',
    country: counterparty.country ?? '',
    city: counterparty.city ?? '',
    email: counterparty.email ?? '',
    phone: counterparty.phone ?? '',
    website: counterparty.website ?? '',
    roles: (counterparty.roles ?? []).filter((role): role is CounterpartyRole =>
      (COUNTERPARTY_ROLES as readonly string[]).includes(role),
    ),
    notes: counterparty.notes ?? '',
    crmAccountId:
      counterparty.crm_account_id === null || counterparty.crm_account_id === undefined
        ? ''
        : String(counterparty.crm_account_id),
  };
}

export function CounterpartyForm({ locale, initial, saving, error, onSubmit, onCancel }: Props) {
  const [draft, setDraft] = useState<CounterpartyDraft>(() => draftFrom(initial));
  const [localError, setLocalError] = useState<string | null>(null);

  // Le formulaire sert à la CRÉATION et à l'ÉDITION (#9780). Quand la
  // contrepartie éditée change — ou quand on repasse en création — le
  // brouillon doit suivre : sans cette resynchronisation, ouvrir « Modifier »
  // sur une autre fiche garderait les valeurs de la précédente.
  useEffect(() => {
    setDraft(draftFrom(initial));
    setLocalError(null);
  }, [initial]);

  /**
   * Rôles que la fiche possède aujourd'hui et que le brouillon ne porte plus.
   *
   * Vide en création (`initial` absent) : on ne « retire » rien à une fiche
   * qui n'existe pas encore. Filtré sur le registre fermé, comme le
   * pré-remplissage — un rôle inconnu renvoyé par l'API n'est pas un rôle que
   * l'utilisateur peut retirer.
   */
  const removedRoles = useMemo<CounterpartyRole[]>(() => {
    if (!initial) return [];

    const kept = new Set<string>(draft.roles);

    return (initial.roles ?? []).filter(
      (role): role is CounterpartyRole =>
        (COUNTERPARTY_ROLES as readonly string[]).includes(role) && !kept.has(role),
    );
  }, [initial, draft.roles]);

  const roleLabels = (roles: CounterpartyRole[]): string =>
    roles.map((role) => t(locale, `trade.counterparties.role.${role}`, role)).join(', ');

  const toggleRole = (role: CounterpartyRole) => {
    setDraft((current) => ({
      ...current,
      roles: current.roles.includes(role)
        ? current.roles.filter((item) => item !== role)
        : [...current.roles, role],
    }));
  };

  const submit = () => {
    setLocalError(null);

    const name = draft.name.trim();
    if (!name) {
      setLocalError(t(locale, 'trade.counterparties.nameRequired'));
      return;
    }

    if (draft.roles.length === 0) {
      setLocalError(t(locale, 'trade.counterparties.roleRequired'));
      return;
    }

    const email = draft.email.trim();
    if (email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
      setLocalError(t(locale, 'trade.counterparties.emailInvalid'));
      return;
    }

    const country = draft.country.trim().toUpperCase();
    if (country && !/^[A-Z]{2}$/.test(country)) {
      setLocalError(t(locale, 'trade.counterparties.countryInvalid'));
      return;
    }

    const crm = draft.crmAccountId.trim();
    if (crm && !/^[0-9]+$/.test(crm)) {
      setLocalError(t(locale, 'trade.counterparties.crmInvalid'));
      return;
    }

    // Retirer un rôle change ce que la contrepartie peut faire : on le
    // confirme explicitement. Un refus n'envoie rien et laisse la saisie
    // intacte — l'utilisateur peut recocher le rôle et repartir.
    if (removedRoles.length > 0) {
      const confirmed = window.confirm(
        `${t(locale, 'trade.counterparties.roleRemovalConfirm')} ${roleLabels(removedRoles)}`,
      );
      if (!confirmed) return;
    }

    // Champs vides → `null`, jamais `''` : une chaîne vide en base n'est pas
    // « non renseigné », c'est une valeur qui casse les filtres `IS NULL`.
    onSubmit({
      name,
      country: country || null,
      city: draft.city.trim() || null,
      email: email || null,
      phone: draft.phone.trim() || null,
      website: draft.website.trim() || null,
      roles: draft.roles,
      notes: draft.notes.trim() || null,
      crm_account_id: crm ? Number(crm) : null,
    });
  };

  const message = localError ?? error;

  return (
    <form
      className="mt-4 grid gap-3 rounded-xl bg-slate-50 p-4 sm:grid-cols-2"
      onSubmit={(event) => {
        event.preventDefault();
        submit();
      }}
    >
      <label className="text-xs font-medium text-slate-700 sm:col-span-2">
        {t(locale, 'trade.counterparties.name')}
        <input
          required
          value={draft.name}
          onChange={(event) => setDraft({ ...draft, name: event.target.value })}
          className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
        />
      </label>

      <fieldset className="sm:col-span-2">
        <legend className="text-xs font-medium text-slate-700">
          {t(locale, 'trade.counterparties.roles')}
        </legend>
        <div className="mt-2 flex flex-wrap gap-2">
          {COUNTERPARTY_ROLES.map((role) => {
            const checked = draft.roles.includes(role);
            return (
              <label
                key={role}
                className={`inline-flex cursor-pointer items-center gap-2 rounded-lg px-3 py-1.5 text-xs font-medium ring-1 transition ${
                  checked
                    ? 'bg-slate-900 text-white ring-slate-900'
                    : 'bg-white text-slate-700 ring-slate-300 hover:bg-slate-100'
                }`}
              >
                <input
                  type="checkbox"
                  className="sr-only"
                  checked={checked}
                  onChange={() => toggleRole(role)}
                />
                {t(locale, `trade.counterparties.role.${role}`)}
              </label>
            );
          })}
        </div>

        {/* Annoncé AVANT l'enregistrement : l'utilisateur voit ce qu'il va
            retirer, pas seulement une boîte de dialogue au moment du clic. */}
        {removedRoles.length > 0 ? (
          <p className="mt-2 rounded-lg bg-amber-50 px-3 py-2 text-[11px] text-amber-800">
            {t(locale, 'trade.counterparties.roleRemovalWarning')} {roleLabels(removedRoles)}
          </p>
        ) : null}
      </fieldset>

      <label className="text-xs font-medium text-slate-700">
        {t(locale, 'trade.counterparties.country')}
        <input
          maxLength={2}
          value={draft.country}
          onChange={(event) => setDraft({ ...draft, country: event.target.value })}
          className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm uppercase"
        />
      </label>

      <label className="text-xs font-medium text-slate-700">
        {t(locale, 'trade.counterparties.city')}
        <input
          value={draft.city}
          onChange={(event) => setDraft({ ...draft, city: event.target.value })}
          className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
        />
      </label>

      <label className="text-xs font-medium text-slate-700">
        {t(locale, 'trade.counterparties.email')}
        <input
          type="email"
          value={draft.email}
          onChange={(event) => setDraft({ ...draft, email: event.target.value })}
          className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
        />
      </label>

      <label className="text-xs font-medium text-slate-700">
        {t(locale, 'trade.counterparties.phone')}
        <input
          value={draft.phone}
          onChange={(event) => setDraft({ ...draft, phone: event.target.value })}
          className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
        />
      </label>

      <label className="text-xs font-medium text-slate-700">
        {t(locale, 'trade.counterparties.website')}
        <input
          type="url"
          value={draft.website}
          onChange={(event) => setDraft({ ...draft, website: event.target.value })}
          className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
        />
      </label>

      <label className="text-xs font-medium text-slate-700">
        {t(locale, 'trade.counterparties.crmAccount')}
        <input
          inputMode="numeric"
          value={draft.crmAccountId}
          onChange={(event) => setDraft({ ...draft, crmAccountId: event.target.value })}
          className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
        />
        <span className="mt-1 block text-[11px] font-normal text-slate-500">
          {t(locale, 'trade.counterparties.crmHint')}
        </span>
      </label>

      <label className="text-xs font-medium text-slate-700 sm:col-span-2">
        {t(locale, 'trade.counterparties.notes')}
        <textarea
          rows={3}
          value={draft.notes}
          onChange={(event) => setDraft({ ...draft, notes: event.target.value })}
          className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
        />
      </label>

      {message ? (
        <p className="rounded-xl bg-rose-50 px-4 py-2 text-xs text-rose-700 sm:col-span-2">
          {message}
        </p>
      ) : null}

      <div className="flex items-end gap-2 sm:col-span-2">
        <Button type="submit" size="sm" disabled={saving}>
          {initial ? t(locale, 'common.edit') : t(locale, 'trade.counterparties.save')}
        </Button>
        <Button type="button" variant="outline" size="sm" onClick={onCancel}>
          {t(locale, 'trade.counterparties.cancel')}
        </Button>
      </div>
    </form>
  );
}
