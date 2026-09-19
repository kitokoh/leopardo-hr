'use client';

import { useCallback, useEffect, useState, useSyncExternalStore } from 'react';
import { ArrowLeft, LifeBuoy, Plus, Send, X, XCircle } from 'lucide-react';

import { apiFetch } from '@/lib/api-client';
import { ModulePageShell } from '@/components/module-page-shell';
import { getPreferredLocale, toIntlLocale, type AppLocale } from '@/lib/i18n';
import { interpolate } from '@/lib/i18n/locale-catalog';
import {
  SUPPORT_TICKET_CATEGORIES,
  SUPPORT_TICKET_PRIORITIES,
  supportCategoryLabel,
  supportPriorityLabel,
  supportStatusBadgeClass,
  supportStatusLabel,
  supportTicketsErrorMessage,
  supportTicketsT,
  type SupportTicketCategory,
  type SupportTicketPriority,
  type SupportTicketsKey,
} from '@/lib/i18n/support-tickets';

type TicketSummary = {
  id: number;
  subject?: string | null;
  category?: string | null;
  priority?: string | null;
  status?: string | null;
  messages_count?: number | null;
  last_message_at?: string | null;
  created_at?: string | null;
};

type TicketMessage = {
  id: number;
  body?: string | null;
  from_platform?: boolean;
  created_at?: string | null;
};

type TicketDetail = TicketSummary & {
  messages?: TicketMessage[];
};

type TicketsPayload = {
  data?: TicketSummary[];
  meta?: {
    current_page?: number;
    last_page?: number;
    total?: number;
  };
};

type TicketDetailPayload = { data?: TicketDetail };

const PAGE_SIZE = 20;

/**
 * Filtres de statut — `filter_in_progress` couvre le statut API `pending`
 * (ticket en attente d'une réponse, conversation en cours).
 */
const STATUS_FILTERS: Array<{ value: string; labelKey: SupportTicketsKey }> = [
  { value: '', labelKey: 'filter_all' },
  { value: 'open', labelKey: 'filter_open' },
  { value: 'pending', labelKey: 'filter_in_progress' },
  { value: 'resolved', labelKey: 'filter_resolved' },
  { value: 'closed', labelKey: 'filter_closed' },
];

const inputClassName =
  'w-full rounded-xl border border-app-border bg-white px-3 py-2 text-sm text-slate-900 outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20';

const selectClassName =
  'rounded-xl border border-app-border bg-white px-3 py-2 text-sm font-medium text-slate-900 outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20';

/**
 * Espace « Support » du portail client (#7759).
 *
 * Consomme l'API tenant existante (PA2-COMM-012) : liste des tickets
 * (`GET /support-tickets`), création (`POST /support-tickets`), fil de
 * messages (`GET /support-tickets/{id}`), réponse (`POST …/reply`) et
 * clôture avec confirmation inline (`POST …/close`). L'API répond 422 sur
 * une réponse à un ticket clos : l'UI masque le formulaire de réponse et
 * affiche l'explication localisée.
 *
 * Visibilité : tous les rôles du tenant pour l'instant — la restriction par
 * grant `support` arrive dans le lot « délégation par module » (spec
 * MISSION_ESPACE_CLIENT_DELEGATION_BILLING_SUPPORT.md §3.1/§3.2).
 */
export default function SupportPage() {
  const locale = useSyncExternalStore<AppLocale>(() => () => {}, getPreferredLocale, () => 'fr');

  const [tickets, setTickets] = useState<TicketSummary[]>([]);
  const [meta, setMeta] = useState({ page: 1, lastPage: 1, total: 0 });
  const [statusFilter, setStatusFilter] = useState('');
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [notice, setNotice] = useState<string | null>(null);

  // ── Création d'un ticket ──────────────────────────────────────────────
  const [showCreateForm, setShowCreateForm] = useState(false);
  const [subject, setSubject] = useState('');
  const [category, setCategory] = useState<SupportTicketCategory>('general');
  const [priority, setPriority] = useState<SupportTicketPriority>('normal');
  const [message, setMessage] = useState('');
  const [submitting, setSubmitting] = useState(false);

  // ── Vue fil de messages ───────────────────────────────────────────────
  const [selectedTicket, setSelectedTicket] = useState<TicketDetail | null>(null);
  const [ticketLoading, setTicketLoading] = useState(false);
  const [reply, setReply] = useState('');
  const [replying, setReplying] = useState(false);
  const [confirmClose, setConfirmClose] = useState(false);
  const [closing, setClosing] = useState(false);

  const loadTickets = useCallback(async (targetPage: number, targetStatus: string) => {
    const params = new URLSearchParams({ per_page: String(PAGE_SIZE) });
    if (targetPage > 1) {
      params.set('page', String(targetPage));
    }
    if (targetStatus) {
      params.set('status', targetStatus);
    }

    const response = await apiFetch(`/support-tickets?${params.toString()}`, { _cacheBust: true });
    const payload = (await response.json()) as TicketsPayload;
    const list = Array.isArray(payload.data) ? payload.data : [];
    setTickets(list);
    setMeta({
      page: payload.meta?.current_page ?? targetPage,
      lastPage: Math.max(payload.meta?.last_page ?? 1, 1),
      total: payload.meta?.total ?? list.length,
    });
  }, []);

  useEffect(() => {
    let active = true;

    async function bootstrap() {
      try {
        await loadTickets(1, '');
      } catch {
        if (active) {
          setError(supportTicketsT(locale, 'load_error'));
        }
      } finally {
        if (active) {
          setLoading(false);
        }
      }
    }

    void bootstrap();

    return () => {
      active = false;
    };
  }, [locale, loadTickets]);

  const changeFilter = async (value: string) => {
    setStatusFilter(value);
    setError(null);
    setNotice(null);
    setLoading(true);
    try {
      await loadTickets(1, value);
    } catch {
      setError(supportTicketsT(locale, 'load_error'));
    } finally {
      setLoading(false);
    }
  };

  const goToPage = async (targetPage: number) => {
    if (targetPage < 1 || targetPage > meta.lastPage || targetPage === meta.page) {
      return;
    }
    setError(null);
    setNotice(null);
    setLoading(true);
    try {
      await loadTickets(targetPage, statusFilter);
    } catch {
      setError(supportTicketsT(locale, 'load_error'));
    } finally {
      setLoading(false);
    }
  };

  const openTicket = async (ticketId: number) => {
    setError(null);
    setNotice(null);
    setConfirmClose(false);
    setReply('');
    setTicketLoading(true);
    try {
      const response = await apiFetch(`/support-tickets/${ticketId}`, { _cacheBust: true });
      const payload = (await response.json()) as TicketDetailPayload;
      setSelectedTicket(payload.data ?? null);
    } catch {
      setError(supportTicketsT(locale, 'load_error'));
    } finally {
      setTicketLoading(false);
    }
  };

  const backToList = async () => {
    setSelectedTicket(null);
    setConfirmClose(false);
    setError(null);
    setNotice(null);
    setLoading(true);
    try {
      await loadTickets(meta.page, statusFilter);
    } catch {
      setError(supportTicketsT(locale, 'load_error'));
    } finally {
      setLoading(false);
    }
  };

  const handleCreate = async (event: React.FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    setError(null);
    setNotice(null);

    if (!subject.trim() || !message.trim()) {
      setError(supportTicketsT(locale, 'form_incomplete'));
      return;
    }

    setSubmitting(true);

    try {
      const response = await apiFetch('/support-tickets', {
        method: 'POST',
        body: JSON.stringify({
          subject: subject.trim(),
          category,
          priority,
          message: message.trim(),
        }),
      });
      const payload = (await response.json()) as TicketDetailPayload;

      setSubject('');
      setCategory('general');
      setPriority('normal');
      setMessage('');
      setShowCreateForm(false);
      setNotice(supportTicketsT(locale, 'create_success'));
      // Ouvre directement le fil du ticket créé : le client voit son message
      // enregistré et peut compléter sans repasser par la liste.
      setSelectedTicket(payload.data ?? null);
    } catch (err) {
      setError(supportTicketsErrorMessage(locale, err));
    } finally {
      setSubmitting(false);
    }
  };

  const handleReply = async (event: React.FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    if (!selectedTicket) {
      return;
    }
    setError(null);
    setNotice(null);

    if (!reply.trim()) {
      return;
    }

    setReplying(true);

    try {
      const response = await apiFetch(`/support-tickets/${selectedTicket.id}/reply`, {
        method: 'POST',
        body: JSON.stringify({ message: reply.trim() }),
      });
      const payload = (await response.json()) as TicketDetailPayload;
      setSelectedTicket(payload.data ?? selectedTicket);
      setReply('');
      setNotice(supportTicketsT(locale, 'reply_sent'));
    } catch (err) {
      setError(supportTicketsErrorMessage(locale, err));
    } finally {
      setReplying(false);
    }
  };

  const handleClose = async () => {
    if (!selectedTicket) {
      return;
    }
    setError(null);
    setNotice(null);
    setClosing(true);

    try {
      const response = await apiFetch(`/support-tickets/${selectedTicket.id}/close`, {
        method: 'POST',
      });
      const payload = (await response.json()) as TicketDetailPayload;
      setSelectedTicket(payload.data ?? selectedTicket);
      setConfirmClose(false);
      setNotice(supportTicketsT(locale, 'close_success'));
    } catch (err) {
      setError(supportTicketsErrorMessage(locale, err));
    } finally {
      setClosing(false);
    }
  };

  const formatDate = (value: string | null | undefined): string => {
    if (!value) {
      return '';
    }
    return new Date(value).toLocaleString(toIntlLocale(locale), {
      dateStyle: 'medium',
      timeStyle: 'short',
    });
  };

  const shell = (children: React.ReactNode) => (
    <ModulePageShell
      title={supportTicketsT(locale, 'title')}
      subtitle={supportTicketsT(locale, 'subtitle')}
      icon={LifeBuoy}
      accentClassName="bg-gradient-to-br from-slate-50 via-white to-white"
    >
      {children}
    </ModulePageShell>
  );

  const feedback = (
    <>
      {notice ? (
        <div
          role="status"
          className="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800"
        >
          {notice}
        </div>
      ) : null}

      {error ? (
        <div
          role="alert"
          className="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700"
        >
          {error}
        </div>
      ) : null}
    </>
  );

  // ── Vue fil de messages ─────────────────────────────────────────────────
  if (selectedTicket) {
    const isClosed = selectedTicket.status === 'closed';
    const messages = Array.isArray(selectedTicket.messages) ? selectedTicket.messages : [];

    return shell(
      <>
        {feedback}

        <section className="rounded-3xl border border-app-border bg-white p-6 shadow-sm" data-testid="support-ticket-detail">
          <div className="flex flex-wrap items-start justify-between gap-3">
            <div className="min-w-0">
              <button
                type="button"
                onClick={() => void backToList()}
                data-testid="support-back-to-list"
                className="inline-flex items-center gap-1.5 rounded-xl border border-app-border px-3 py-1.5 text-[11px] font-bold text-slate-600 transition hover:bg-slate-100"
              >
                <ArrowLeft className="h-3.5 w-3.5 rtl:rotate-180" aria-hidden="true" />
                {supportTicketsT(locale, 'back_to_list')}
              </button>
              <h2 className="mt-3 text-lg font-black text-slate-950">{selectedTicket.subject}</h2>
              <div className="mt-2 flex flex-wrap items-center gap-2">
                <span
                  data-testid="support-detail-status"
                  className={`rounded-full px-3 py-1 text-[11px] font-bold uppercase tracking-wider ${supportStatusBadgeClass(selectedTicket.status)}`}
                >
                  {supportStatusLabel(locale, selectedTicket.status)}
                </span>
                <span className="rounded-full bg-slate-100 px-3 py-1 text-[11px] font-bold uppercase tracking-wider text-slate-600">
                  {supportCategoryLabel(locale, selectedTicket.category)}
                </span>
                <span className="rounded-full bg-slate-100 px-3 py-1 text-[11px] font-bold uppercase tracking-wider text-slate-600">
                  {supportPriorityLabel(locale, selectedTicket.priority)}
                </span>
              </div>
            </div>

            {!isClosed ? (
              <div className="flex flex-wrap items-center gap-2">
                {confirmClose ? (
                  <>
                    <span className="text-[11px] font-bold text-slate-600">
                      {supportTicketsT(locale, 'close_confirm_title')}
                    </span>
                    <button
                      type="button"
                      onClick={() => void handleClose()}
                      disabled={closing}
                      data-testid="support-close-confirm"
                      className="rounded-xl bg-red-600 px-3 py-1.5 text-[11px] font-bold text-white transition hover:bg-red-700 disabled:opacity-60"
                    >
                      {supportTicketsT(locale, 'close_confirm')}
                    </button>
                    <button
                      type="button"
                      onClick={() => setConfirmClose(false)}
                      className="rounded-xl border border-app-border px-3 py-1.5 text-[11px] font-bold text-slate-600 transition hover:bg-slate-100"
                    >
                      {supportTicketsT(locale, 'cancel')}
                    </button>
                  </>
                ) : (
                  <button
                    type="button"
                    onClick={() => {
                      setConfirmClose(true);
                      setError(null);
                      setNotice(null);
                    }}
                    data-testid="support-close-toggle"
                    className="inline-flex items-center gap-1.5 rounded-xl border border-app-border px-3 py-1.5 text-[11px] font-bold text-slate-600 transition hover:bg-slate-100"
                  >
                    <XCircle className="h-3.5 w-3.5" aria-hidden="true" />
                    {supportTicketsT(locale, 'close_action')}
                  </button>
                )}
              </div>
            ) : null}
          </div>

          {/* ── Fil de messages ───────────────────────────────────────── */}
          <div className="mt-6 space-y-3" data-testid="support-message-thread">
            {messages.map((item) => (
              <div
                key={item.id}
                data-testid={`support-message-${item.id}`}
                className={`max-w-[85%] rounded-2xl border px-4 py-3 ${
                  item.from_platform
                    ? 'border-emerald-200 bg-emerald-50'
                    : 'ms-auto border-app-border bg-slate-50'
                }`}
              >
                <p className="text-[11px] font-bold uppercase tracking-wider text-slate-500">
                  {supportTicketsT(locale, item.from_platform ? 'from_platform' : 'from_company')}
                  {item.created_at ? ` — ${formatDate(item.created_at)}` : ''}
                </p>
                <p className="mt-1 whitespace-pre-wrap text-sm leading-6 text-slate-800">{item.body}</p>
              </div>
            ))}
          </div>

          {/* ── Réponse / ticket clos ─────────────────────────────────── */}
          {isClosed ? (
            <p
              data-testid="support-closed-notice"
              className="mt-6 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-medium text-slate-600"
            >
              {supportTicketsT(locale, 'closed_notice')}
            </p>
          ) : (
            <form onSubmit={handleReply} className="mt-6 space-y-3">
              <label className="block text-sm font-medium text-slate-700">
                {supportTicketsT(locale, 'reply_label')}
                <textarea
                  value={reply}
                  onChange={(event) => setReply(event.target.value)}
                  required
                  rows={4}
                  maxLength={5000}
                  placeholder={supportTicketsT(locale, 'reply_placeholder')}
                  data-testid="support-reply-input"
                  className={`mt-1 ${inputClassName}`}
                />
              </label>
              <button
                type="submit"
                disabled={replying}
                data-testid="support-reply-submit"
                className="inline-flex items-center gap-2 rounded-xl bg-brand-700 px-4 py-2 text-sm font-bold text-white transition hover:bg-brand-800 disabled:opacity-60"
              >
                <Send className="h-4 w-4" aria-hidden="true" />
                {supportTicketsT(locale, 'reply_submit')}
              </button>
            </form>
          )}
        </section>
      </>,
    );
  }

  // ── Vue liste + création ─────────────────────────────────────────────────
  return shell(
    <>
      {feedback}

      <section className="grid gap-4 lg:grid-cols-[1fr_auto]">
        <div className="flex flex-wrap items-center gap-2 rounded-3xl border border-app-border bg-white p-6 shadow-sm">
          {STATUS_FILTERS.map((filter) => (
            <button
              key={filter.value || 'all'}
              type="button"
              onClick={() => void changeFilter(filter.value)}
              data-testid={`support-filter-${filter.value || 'all'}`}
              className={`rounded-xl px-3 py-1.5 text-[12px] font-bold transition ${
                statusFilter === filter.value
                  ? 'bg-brand-700 text-white'
                  : 'border border-app-border text-slate-600 hover:bg-slate-100'
              }`}
            >
              {supportTicketsT(locale, filter.labelKey)}
            </button>
          ))}
        </div>

        <div className="flex items-center rounded-3xl border border-app-border bg-white p-6 shadow-sm">
          <button
            type="button"
            onClick={() => {
              setShowCreateForm((value) => !value);
              setError(null);
              setNotice(null);
            }}
            data-testid="support-create-toggle"
            className="inline-flex items-center gap-2 rounded-xl bg-brand-700 px-4 py-2 text-sm font-bold text-white transition hover:bg-brand-800"
          >
            {showCreateForm ? <X className="h-4 w-4" aria-hidden="true" /> : <Plus className="h-4 w-4" aria-hidden="true" />}
            {supportTicketsT(locale, showCreateForm ? 'cancel' : 'new_ticket')}
          </button>
        </div>
      </section>

      {showCreateForm ? (
        <section className="rounded-3xl border border-app-border bg-white p-6 shadow-sm">
          <h2 className="text-sm font-bold uppercase tracking-wider text-slate-800">
            {supportTicketsT(locale, 'new_ticket')}
          </h2>
          <form onSubmit={handleCreate} className="mt-5 grid gap-4 md:grid-cols-2">
            <label className="text-sm font-medium text-slate-700 md:col-span-2">
              {supportTicketsT(locale, 'field_subject')}
              <input
                type="text"
                value={subject}
                onChange={(event) => setSubject(event.target.value)}
                required
                maxLength={200}
                data-testid="support-create-subject"
                className={`mt-1 ${inputClassName}`}
              />
            </label>

            <label className="text-sm font-medium text-slate-700">
              {supportTicketsT(locale, 'field_category')}
              <select
                value={category}
                onChange={(event) => setCategory(event.target.value as SupportTicketCategory)}
                data-testid="support-create-category"
                className={`mt-1 w-full ${selectClassName}`}
              >
                {SUPPORT_TICKET_CATEGORIES.map((value) => (
                  <option key={value} value={value}>
                    {supportCategoryLabel(locale, value)}
                  </option>
                ))}
              </select>
            </label>

            <label className="text-sm font-medium text-slate-700">
              {supportTicketsT(locale, 'field_priority')}
              <select
                value={priority}
                onChange={(event) => setPriority(event.target.value as SupportTicketPriority)}
                data-testid="support-create-priority"
                className={`mt-1 w-full ${selectClassName}`}
              >
                {SUPPORT_TICKET_PRIORITIES.map((value) => (
                  <option key={value} value={value}>
                    {supportPriorityLabel(locale, value)}
                  </option>
                ))}
              </select>
            </label>

            <label className="text-sm font-medium text-slate-700 md:col-span-2">
              {supportTicketsT(locale, 'field_message')}
              <textarea
                value={message}
                onChange={(event) => setMessage(event.target.value)}
                required
                rows={5}
                maxLength={5000}
                data-testid="support-create-message"
                className={`mt-1 ${inputClassName}`}
              />
            </label>

            <div className="flex gap-3 md:col-span-2">
              <button
                type="submit"
                disabled={submitting}
                data-testid="support-create-submit"
                className="inline-flex items-center gap-2 rounded-xl bg-brand-700 px-4 py-2 text-sm font-bold text-white transition hover:bg-brand-800 disabled:opacity-60"
              >
                {supportTicketsT(locale, 'create_submit')}
              </button>
            </div>
          </form>
        </section>
      ) : null}

      {/* ── Liste des tickets ──────────────────────────────────────────── */}
      <section className="overflow-hidden rounded-3xl border border-app-border bg-white shadow-sm">
        <div className="flex flex-wrap items-center justify-between gap-2 border-b border-app-border px-6 py-4">
          <h2 className="text-sm font-bold uppercase tracking-wider text-slate-800">
            {supportTicketsT(locale, 'title')}
          </h2>
          <p className="text-xs font-bold uppercase tracking-wider text-slate-500">
            {interpolate(supportTicketsT(locale, 'messages_count'), { count: meta.total })}
          </p>
        </div>

        <div className="hidden gap-4 border-b border-app-border px-6 py-3 text-[11px] font-bold uppercase tracking-wider text-slate-500 md:grid md:grid-cols-[minmax(0,2.2fr)_minmax(0,1fr)_minmax(0,1fr)_minmax(0,1fr)_minmax(0,1.3fr)]">
          <span>{supportTicketsT(locale, 'field_subject')}</span>
          <span>{supportTicketsT(locale, 'field_category')}</span>
          <span>{supportTicketsT(locale, 'field_priority')}</span>
          <span>{supportTicketsT(locale, 'col_status')}</span>
          <span>{supportTicketsT(locale, 'col_last_activity')}</span>
        </div>

        {loading || ticketLoading ? (
          <p className="px-6 py-8 text-sm text-slate-500">{supportTicketsT(locale, 'loading')}</p>
        ) : tickets.length === 0 ? (
          <p className="px-6 py-8 text-sm text-slate-500" data-testid="support-empty">
            {supportTicketsT(locale, 'empty')}
          </p>
        ) : (
          <div className="divide-y divide-app-border">
            {tickets.map((ticket) => (
              <button
                key={ticket.id}
                type="button"
                onClick={() => void openTicket(ticket.id)}
                data-testid={`support-ticket-row-${ticket.id}`}
                className="grid w-full gap-4 px-6 py-5 text-start transition hover:bg-slate-50 md:grid-cols-[minmax(0,2.2fr)_minmax(0,1fr)_minmax(0,1fr)_minmax(0,1fr)_minmax(0,1.3fr)] md:items-center"
              >
                <span className="min-w-0">
                  <span className="block truncate text-sm font-bold text-slate-950">{ticket.subject}</span>
                  <span className="block text-xs text-slate-500">
                    {interpolate(supportTicketsT(locale, 'messages_count'), { count: ticket.messages_count ?? 0 })}
                  </span>
                </span>
                <span className="text-sm text-slate-600">{supportCategoryLabel(locale, ticket.category)}</span>
                <span className="text-sm text-slate-600">{supportPriorityLabel(locale, ticket.priority)}</span>
                <span>
                  <span
                    data-testid={`support-ticket-status-${ticket.id}`}
                    className={`inline-flex rounded-full px-3 py-1 text-[11px] font-bold uppercase tracking-wider ${supportStatusBadgeClass(ticket.status)}`}
                  >
                    {supportStatusLabel(locale, ticket.status)}
                  </span>
                </span>
                <span className="text-xs font-medium text-slate-500">
                  {formatDate(ticket.last_message_at ?? ticket.created_at)}
                </span>
              </button>
            ))}
          </div>
        )}

        <div className="flex flex-wrap items-center justify-between gap-3 border-t border-app-border px-6 py-4">
          <p className="text-xs font-medium text-slate-500">
            {interpolate(supportTicketsT(locale, 'pagination_status'), { page: meta.page, last: meta.lastPage })}
          </p>
          <div className="flex gap-2">
            <button
              type="button"
              onClick={() => void goToPage(meta.page - 1)}
              disabled={meta.page <= 1}
              className="rounded-xl border border-app-border px-3 py-1.5 text-xs font-bold text-slate-700 transition hover:bg-slate-100 disabled:opacity-50"
            >
              {supportTicketsT(locale, 'pagination_prev')}
            </button>
            <button
              type="button"
              onClick={() => void goToPage(meta.page + 1)}
              disabled={meta.page >= meta.lastPage}
              className="rounded-xl border border-app-border px-3 py-1.5 text-xs font-bold text-slate-700 transition hover:bg-slate-100 disabled:opacity-50"
            >
              {supportTicketsT(locale, 'pagination_next')}
            </button>
          </div>
        </div>
      </section>
    </>,
  );
}
