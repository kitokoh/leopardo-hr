'use client';

import { useMemo, useState } from 'react';
import { Pencil, Search, ShieldCheck, ShieldAlert, ShieldQuestion, Trash2 } from 'lucide-react';
import { Button } from '@/components/ui/Button';
import { t } from '@/lib/i18n/locale-catalog';
import type { AppLocale } from '@/lib/i18n';
import type { TradeCounterparty, VerificationStatus } from './types';

/**
 * Liste des contreparties Trade — #9780.
 *
 * ## Ce que cette liste refuse d'affirmer
 *
 * Le badge de vérification ne dit jamais « vérifié » tout court : il dit
 * « vérifié par l'équipe » et, quand la donnée existe, QUI et QUAND. C'est la
 * seule formulation honnête tant qu'aucun service de vérification externe
 * n'est souscrit (`TradeVerificationStatus` le documente côté API).
 *
 * ## Filtrage
 *
 * Le filtre par rôle est appliqué **côté client** sur la liste déjà chargée :
 * la liste est paginée à 100 par le serveur, et un filtre serveur
 * supplémentaire obligerait à recharger à chaque clic. Le champ de recherche
 * reste local pour la même raison — la recherche transverse serveur
 * (`GET /trade/search`) est un autre outil, pas ce filtre de confort.
 */

type Props = {
  locale: AppLocale;
  counterparties: TradeCounterparty[];
  loading: boolean;
  onDelete: (counterparty: TradeCounterparty) => void;
  onVerify: (counterparty: TradeCounterparty) => void;
  onEdit: (counterparty: TradeCounterparty) => void;
  canVerify: boolean;
};

const STATUS_ICON: Record<VerificationStatus, typeof ShieldCheck> = {
  verified: ShieldCheck,
  rejected: ShieldAlert,
  pending: ShieldQuestion,
  none: ShieldQuestion,
};

const STATUS_CLASS: Record<VerificationStatus, string> = {
  verified: 'bg-emerald-100 text-emerald-800',
  rejected: 'bg-rose-100 text-rose-800',
  pending: 'bg-amber-100 text-amber-800',
  none: 'bg-slate-200 text-slate-700',
};

export function CounterpartyList({
  locale,
  counterparties,
  loading,
  onDelete,
  onVerify,
  onEdit,
  canVerify,
}: Props) {
  const [roleFilter, setRoleFilter] = useState<string>('');
  const [term, setTerm] = useState('');

  const visible = useMemo(() => {
    const needle = term.trim().toLowerCase();

    return counterparties.filter((item) => {
      if (roleFilter && !item.roles.includes(roleFilter)) return false;
      if (!needle) return true;

      // Recherche locale sur les champs que la ressource expose réellement.
      return [item.name, item.email, item.city, item.country]
        .filter((value): value is string => typeof value === 'string')
        .some((value) => value.toLowerCase().includes(needle));
    });
  }, [counterparties, roleFilter, term]);

  if (loading) {
    return <p className="py-8 text-center text-sm text-slate-500">{t(locale, 'trade.loading')}</p>;
  }

  return (
    <div className="space-y-3">
      <div className="flex flex-wrap items-center gap-2">
        <label className="relative flex-1 min-w-[200px]">
          <span className="sr-only">{t(locale, 'trade.counterparties.search')}</span>
          <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
          <input
            value={term}
            onChange={(event) => setTerm(event.target.value)}
            placeholder={t(locale, 'trade.counterparties.search')}
            className="w-full rounded-lg border border-slate-300 py-2 pl-9 pr-3 text-sm"
          />
        </label>

        <label className="text-xs font-medium text-slate-700">
          <span className="sr-only">{t(locale, 'trade.counterparties.filterRole')}</span>
          <select
            value={roleFilter}
            onChange={(event) => setRoleFilter(event.target.value)}
            className="rounded-lg border border-slate-300 px-3 py-2 text-sm"
          >
            <option value="">{t(locale, 'trade.counterparties.allRoles')}</option>
            {['buyer', 'supplier', 'manufacturer', 'wholesaler', 'carrier', 'forwarder', 'customs_broker', 'inspector'].map(
              (role) => (
                <option key={role} value={role}>
                  {t(locale, `trade.counterparties.role.${role}`)}
                </option>
              ),
            )}
          </select>
        </label>
      </div>

      {visible.length === 0 ? (
        <p className="px-1 py-6 text-sm text-slate-500">
          {counterparties.length === 0
            ? t(locale, 'trade.counterparties.empty')
            : t(locale, 'trade.counterparties.noMatch')}
        </p>
      ) : (
        <ul className="divide-y divide-slate-100">
          {visible.map((item) => {
            const status = item.verification_status;
            const Icon = STATUS_ICON[status] ?? ShieldQuestion;
            const place = [item.city, item.country].filter(Boolean).join(', ');

            return (
              <li
                key={String(item.id)}
                className="flex flex-wrap items-start justify-between gap-3 py-3"
              >
                <div className="min-w-0">
                  <p className="flex flex-wrap items-center gap-2 text-sm font-medium text-slate-900">
                    {item.name}
                    <span
                      className={`inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[11px] font-medium ${
                        STATUS_CLASS[status] ?? STATUS_CLASS.none
                      }`}
                    >
                      <Icon className="h-3 w-3" />
                      {t(locale, `trade.counterparties.status.${status}`)}
                    </span>
                  </p>

                  <p className="mt-1 text-xs text-slate-500">
                    {(item.roles ?? [])
                      .map((role) => t(locale, `trade.counterparties.role.${role}`, role))
                      .join(' · ')}
                    {place ? ` — ${place}` : ''}
                  </p>

                  {item.email || item.phone ? (
                    <p className="mt-1 text-xs text-slate-500">
                      {[item.email, item.phone].filter(Boolean).join(' · ')}
                    </p>
                  ) : null}

                  {/* Qui a vérifié et quand — jamais un simple « vérifié ». */}
                  {status === 'verified' && item.verified_at ? (
                    <p className="mt-1 text-[11px] text-emerald-700">
                      {t(locale, 'trade.counterparties.verifiedBy')}{' '}
                      {new Date(item.verified_at).toLocaleDateString(locale)}
                    </p>
                  ) : null}
                </div>

                <div className="flex items-center gap-2">
                  {/* Édition (#9780) : ouvre le formulaire pré-rempli. Le nom
                      est dans l'aria-label — l'icône seule ne dit pas quelle
                      fiche on modifie. */}
                  <Button
                    variant="outline"
                    size="sm"
                    aria-label={`${t(locale, 'common.edit')} ${item.name}`}
                    onClick={() => onEdit(item)}
                  >
                    <Pencil className="h-4 w-4" />
                  </Button>
                  {canVerify ? (
                    <Button variant="outline" size="sm" onClick={() => onVerify(item)}>
                      {t(locale, 'trade.counterparties.verify')}
                    </Button>
                  ) : null}
                  <Button
                    variant="outline"
                    size="sm"
                    onClick={() => {
                      if (window.confirm(t(locale, 'trade.counterparties.deleteConfirm'))) {
                        onDelete(item);
                      }
                    }}
                  >
                    <Trash2 className="h-4 w-4" />
                    <span className="sr-only">{t(locale, 'trade.counterparties.delete')}</span>
                  </Button>
                </div>
              </li>
            );
          })}
        </ul>
      )}
    </div>
  );
}
