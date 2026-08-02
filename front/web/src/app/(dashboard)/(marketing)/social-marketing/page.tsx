'use client';

import { useCallback, useEffect, useMemo, useState } from 'react';
import { motion } from 'framer-motion';
import { ApiError, apiFetch } from '@/lib/api-client';
import { ModulePageShell } from '@/components/module-page-shell';
import {
  Link2,
  Unlink,
  Send,
  Trash2,
  Calendar,
  CheckCircle2,
  Clock,
  AlertTriangle,
  Megaphone,
  Plus,
} from 'lucide-react';
import {
  SUPPORTED_PLATFORMS,
  STATUS_STYLES,
  type SocialAccount,
  type SocialAccountPayload,
  type SocialPost,
  type SocialPostsPayload,
} from '@/modules/marketing/types';

function statusBadge(status: string) {
  const style = STATUS_STYLES[status] ?? STATUS_STYLES.draft;
  return (
    <span className={`inline-flex rounded-full px-3 py-1 text-[11px] font-bold uppercase tracking-wider ${style.class}`}>
      {style.label}
    </span>
  );
}

export default function MarketingPage() {
  const [account, setAccount] = useState<SocialAccount | null>(null);
  const [accountLoading, setAccountLoading] = useState(true);
  const [accountError, setAccountError] = useState<string | null>(null);
  const [connecting, setConnecting] = useState(false);
  const [disconnecting, setDisconnecting] = useState(false);
  const [displayName, setDisplayName] = useState('');

  const [posts, setPosts] = useState<SocialPost[]>([]);
  const [postsLoading, setPostsLoading] = useState(true);
  const [postsError, setPostsError] = useState<string | null>(null);
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);

  const [content, setContent] = useState('');
  const [selectedPlatforms, setSelectedPlatforms] = useState<string[]>([]);
  const [scheduledAt, setScheduledAt] = useState('');
  const [submitting, setSubmitting] = useState(false);
  const [actionError, setActionError] = useState<string | null>(null);
  const [pendingActionId, setPendingActionId] = useState<number | null>(null);

  const loadAccount = useCallback(async () => {
    setAccountLoading(true);
    setAccountError(null);
    try {
      const res = await apiFetch('/marketing/social-account');
      const payload = (await res.json()) as SocialAccountPayload;
      setAccount(payload.data ?? null);
    } catch (err) {
      if (err instanceof ApiError && err.code === 'SOCIAL_ACCOUNT_NOT_FOUND') {
        setAccount(null);
      } else {
        setAccountError(err instanceof ApiError ? err.message : 'Impossible de charger le compte social.');
      }
    } finally {
      setAccountLoading(false);
    }
  }, []);

  const loadPosts = useCallback(async (targetPage: number, append: boolean) => {
    setPostsLoading(true);
    setPostsError(null);
    try {
      const res = await apiFetch(`/marketing/social-posts?per_page=15&page=${targetPage}`);
      const payload = (await res.json()) as SocialPostsPayload;
      const nextPosts = Array.isArray(payload.data) ? payload.data : [];
      setPosts((prev) => (append ? [...prev, ...nextPosts] : nextPosts));
      setPage(payload.meta?.current_page ?? targetPage);
      setLastPage(payload.meta?.last_page ?? targetPage);
    } catch (err) {
      setPostsError(err instanceof ApiError ? err.message : 'Impossible de charger les publications.');
    } finally {
      setPostsLoading(false);
    }
  }, []);

  useEffect(() => {
    void loadAccount();
    void loadPosts(1, false);
  }, [loadAccount, loadPosts]);

  const handleConnect = async () => {
    if (!displayName.trim()) {
      return;
    }
    setConnecting(true);
    setAccountError(null);
    try {
      await apiFetch('/marketing/social-account/connect', {
        method: 'POST',
        body: JSON.stringify({ display_name: displayName.trim() }),
      });
      setDisplayName('');
      await loadAccount();
    } catch (err) {
      setAccountError(err instanceof ApiError ? err.message : 'Impossible de connecter le compte social.');
    } finally {
      setConnecting(false);
    }
  };

  const handleDisconnect = async () => {
    setDisconnecting(true);
    setAccountError(null);
    try {
      await apiFetch('/marketing/social-account/disconnect', { method: 'POST' });
      await loadAccount();
    } catch (err) {
      setAccountError(err instanceof ApiError ? err.message : 'Impossible de deconnecter le compte social.');
    } finally {
      setDisconnecting(false);
    }
  };

  const togglePlatform = (value: string) => {
    setSelectedPlatforms((prev) => (
      prev.includes(value) ? prev.filter((p) => p !== value) : [...prev, value]
    ));
  };

  const handleCreatePost = async () => {
    if (!content.trim() || selectedPlatforms.length === 0) {
      return;
    }
    setSubmitting(true);
    setActionError(null);
    try {
      await apiFetch('/marketing/social-posts', {
        method: 'POST',
        body: JSON.stringify({
          content: content.trim(),
          target_platforms: selectedPlatforms,
          scheduled_at: scheduledAt ? new Date(scheduledAt).toISOString() : null,
        }),
      });
      setContent('');
      setSelectedPlatforms([]);
      setScheduledAt('');
      await loadPosts(1, false);
    } catch (err) {
      setActionError(err instanceof ApiError ? err.message : 'Impossible de creer la publication.');
    } finally {
      setSubmitting(false);
    }
  };

  const handlePublishNow = async (post: SocialPost) => {
    setPendingActionId(post.id);
    setActionError(null);
    try {
      await apiFetch(`/marketing/social-posts/${post.id}/publish`, {
        method: 'POST',
        body: JSON.stringify({ scheduled_at: null }),
      });
      await loadPosts(1, false);
    } catch (err) {
      setActionError(err instanceof ApiError ? err.message : 'Impossible de publier la publication.');
    } finally {
      setPendingActionId(null);
    }
  };

  const handleDelete = async (post: SocialPost) => {
    setPendingActionId(post.id);
    setActionError(null);
    try {
      await apiFetch(`/marketing/social-posts/${post.id}`, { method: 'DELETE' });
      setPosts((prev) => prev.filter((p) => p.id !== post.id));
    } catch (err) {
      setActionError(err instanceof ApiError ? err.message : 'Impossible de supprimer la publication.');
    } finally {
      setPendingActionId(null);
    }
  };

  const canLoadMore = page < lastPage;

  const stats = useMemo(() => ({
    total: posts.length,
    scheduled: posts.filter((p) => p.status === 'scheduled').length,
    published: posts.filter((p) => p.status === 'published').length,
    failed: posts.filter((p) => p.status === 'failed').length,
  }), [posts]);

  const statCards = [
    { label: 'Publications', value: stats.total, icon: Megaphone, accent: 'text-emerald-600 bg-emerald-50' },
    { label: 'Planifiees', value: stats.scheduled, icon: Clock, accent: 'text-info bg-info/10' },
    { label: 'Publiees', value: stats.published, icon: CheckCircle2, accent: 'text-emerald-600 bg-emerald-50' },
    { label: 'Echecs', value: stats.failed, icon: AlertTriangle, accent: 'text-red-500 bg-red-50' },
  ];

  const isActionable = (post: SocialPost) => post.status === 'draft' || post.status === 'scheduled';

  return (
    <ModulePageShell
      title="Marketing"
      subtitle="Connectez votre compte social (via Ayrshare) et pilotez vos publications multi-plateformes directement depuis Leopardo."
      accentClassName="bg-gradient-to-br from-brand-500/10 via-white to-white"
    >
      {accountError ? (
        <div className="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{accountError}</div>
      ) : null}

      <section className="rounded-3xl border border-app-border bg-white shadow-sm">
        <div className="border-b border-app-border px-6 py-4">
          <h2 className="text-sm font-bold uppercase tracking-wider text-slate-800">Compte social</h2>
        </div>
        <div className="p-6">
          {accountLoading ? (
            <p className="text-sm text-slate-500">Chargement du compte social...</p>
          ) : account ? (
            <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
              <div>
                <p className="text-sm font-bold text-slate-950">{account.display_name ?? 'Compte social'}</p>
                <p className="text-xs text-slate-500">
                  Fournisseur : {account.provider} Â· Connecte depuis {account.connected_at ?? 'date inconnue'}
                </p>
                {account.connected_platforms && account.connected_platforms.length > 0 ? (
                  <div className="mt-2 flex flex-wrap gap-1.5">
                    {account.connected_platforms.map((platform) => (
                      <span key={platform} className="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-slate-600">
                        {platform}
                      </span>
                    ))}
                  </div>
                ) : null}
                {account.last_error ? (
                  <p className="mt-2 text-xs text-red-600">{account.last_error}</p>
                ) : null}
              </div>
              <div className="flex items-center gap-3">
                <span className={`inline-flex rounded-full px-3 py-1 text-[11px] font-bold uppercase tracking-wider ${account.status === 'active' ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600'}`}>
                  {account.status === 'active' ? 'Actif' : account.status}
                </span>
                <button
                  onClick={handleDisconnect}
                  disabled={disconnecting}
                  className="inline-flex items-center gap-2 rounded-xl border border-app-border px-4 py-2 text-sm font-bold text-slate-700 transition hover:bg-transparent disabled:opacity-50"
                >
                  <Unlink className="h-4 w-4" /> {disconnecting ? 'Deconnexion...' : 'Deconnecter'}
                </button>
              </div>
            </div>
          ) : (
            <div className="flex flex-col gap-3 md:flex-row md:items-end">
              <div className="flex-1">
                <label htmlFor="marketing-display-name" className="mb-1 block text-xs font-bold uppercase tracking-wider text-slate-500">
                  Nom d affichage
                </label>
                <input
                  id="marketing-display-name"
                  type="text"
                  placeholder="Ex: Leopardo RH â€” Reseaux sociaux"
                  value={displayName}
                  onChange={(e) => setDisplayName(e.target.value)}
                  className="w-full rounded-xl border border-app-border bg-transparent px-3 py-2.5 text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-emerald-500"
                />
              </div>
              <button
                onClick={handleConnect}
                disabled={!displayName.trim() || connecting}
                className="inline-flex items-center justify-center gap-2 rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-bold text-white transition hover:bg-emerald-700 disabled:opacity-50"
              >
                <Link2 className="h-4 w-4" /> {connecting ? 'Connexion...' : 'Connecter mon compte'}
              </button>
            </div>
          )}
        </div>
      </section>

      {account ? (
        <>
          <section className="grid grid-cols-2 gap-4 sm:grid-cols-4">
            {statCards.map((stat, i) => (
              <motion.div
                key={stat.label}
                initial={{ opacity: 0, y: 10 }}
                animate={{ opacity: 1, y: 0 }}
                transition={{ delay: i * 0.05 }}
                className="rounded-2xl border border-app-border bg-white p-5 shadow-sm"
              >
                <div className={`mb-3 inline-flex h-10 w-10 items-center justify-center rounded-xl ${stat.accent}`}>
                  <stat.icon className="h-5 w-5" />
                </div>
                <p className="text-2xl font-black text-slate-950">{stat.value}</p>
                <p className="text-xs font-bold uppercase tracking-widest text-slate-400">{stat.label}</p>
              </motion.div>
            ))}
          </section>

          <section className="rounded-3xl border border-app-border bg-white shadow-sm">
            <div className="border-b border-app-border px-6 py-4">
              <h2 className="text-sm font-bold uppercase tracking-wider text-slate-800">Nouvelle publication</h2>
            </div>
            <div className="space-y-4 p-6">
              {actionError ? (
                <div className="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{actionError}</div>
              ) : null}
              <textarea
                placeholder="Contenu de la publication..."
                value={content}
                onChange={(e) => setContent(e.target.value)}
                rows={3}
                maxLength={5000}
                className="w-full rounded-xl border border-app-border bg-transparent px-3 py-2.5 text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-emerald-500"
              />
              <div>
                <p className="mb-2 text-xs font-bold uppercase tracking-wider text-slate-500">Plateformes cibles</p>
                <div className="flex flex-wrap gap-2">
                  {SUPPORTED_PLATFORMS.map((platform) => {
                    const active = selectedPlatforms.includes(platform.value);
                    return (
                      <button
                        key={platform.value}
                        type="button"
                        onClick={() => togglePlatform(platform.value)}
                        className={`rounded-full border px-3 py-1.5 text-xs font-bold transition ${
                          active
                            ? 'border-emerald-500 bg-emerald-50 text-emerald-700'
                            : 'border-app-border text-slate-600 hover:bg-transparent'
                        }`}
                      >
                        {platform.label}
                      </button>
                    );
                  })}
                </div>
              </div>
              <div className="flex flex-col gap-3 md:flex-row md:items-end">
                <div className="flex-1">
                  <label htmlFor="marketing-scheduled-at" className="mb-1 block text-xs font-bold uppercase tracking-wider text-slate-500">
                    Planifier (optionnel)
                  </label>
                  <input
                    id="marketing-scheduled-at"
                    type="datetime-local"
                    value={scheduledAt}
                    onChange={(e) => setScheduledAt(e.target.value)}
                    className="w-full rounded-xl border border-app-border bg-transparent px-3 py-2.5 text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-emerald-500"
                  />
                </div>
                <button
                  onClick={handleCreatePost}
                  disabled={!content.trim() || selectedPlatforms.length === 0 || submitting}
                  className="inline-flex items-center justify-center gap-2 rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-bold text-white transition hover:bg-emerald-700 disabled:opacity-50"
                >
                  <Plus className="h-4 w-4" /> {submitting ? 'Creation...' : (scheduledAt ? 'Planifier' : 'Enregistrer en brouillon')}
                </button>
              </div>
            </div>
          </section>

          <section className="overflow-hidden rounded-3xl border border-app-border bg-white shadow-sm">
            <div className="border-b border-app-border px-6 py-4">
              <h2 className="text-sm font-bold uppercase tracking-wider text-slate-800">Publications</h2>
            </div>
            {postsError ? (
              <div className="px-6 py-4 text-sm text-red-700">{postsError}</div>
            ) : null}
            <div className="divide-y divide-app-border">
              {postsLoading && posts.length === 0 ? (
                <div className="px-6 py-8 text-sm text-slate-500">Chargement des publications...</div>
              ) : posts.length === 0 ? (
                <div className="px-6 py-8 text-sm text-slate-500">Aucune publication pour le moment.</div>
              ) : (
                posts.map((post) => (
                  <div key={post.id} className="flex flex-col gap-3 px-6 py-5 md:flex-row md:items-center md:justify-between">
                    <div className="flex-1">
                      <p className="line-clamp-2 text-sm font-bold text-slate-950">{post.content}</p>
                      <div className="mt-2 flex flex-wrap items-center gap-2 text-xs text-slate-500">
                        {post.target_platforms.map((platform) => (
                          <span key={platform} className="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-slate-600">
                            {platform}
                          </span>
                        ))}
                        {post.scheduled_at ? (
                          <span className="flex items-center gap-1"><Calendar className="h-3 w-3" />{post.scheduled_at}</span>
                        ) : null}
                      </div>
                      {post.error_message ? (
                        <p className="mt-1 text-xs text-red-600">{post.error_message}</p>
                      ) : null}
                    </div>
                    <div className="flex items-center gap-2">
                      {statusBadge(post.status)}
                      {isActionable(post) ? (
                        <>
                          <button
                            onClick={() => handlePublishNow(post)}
                            disabled={pendingActionId === post.id}
                            title="Publier maintenant"
                            className="rounded-lg p-2 text-slate-400 transition hover:bg-slate-100 hover:text-emerald-600 disabled:opacity-50"
                          >
                            <Send className="h-4 w-4" />
                          </button>
                          <button
                            onClick={() => handleDelete(post)}
                            disabled={pendingActionId === post.id}
                            title="Supprimer"
                            className="rounded-lg p-2 text-slate-400 transition hover:bg-red-50 hover:text-red-600 disabled:opacity-50"
                          >
                            <Trash2 className="h-4 w-4" />
                          </button>
                        </>
                      ) : null}
                    </div>
                  </div>
                ))
              )}
            </div>
            {canLoadMore ? (
              <div className="border-t border-app-border px-6 py-4 text-center">
                <button
                  onClick={() => void loadPosts(page + 1, true)}
                  disabled={postsLoading}
                  className="rounded-xl border border-app-border px-4 py-2 text-sm font-bold text-slate-700 transition hover:bg-transparent disabled:opacity-50"
                >
                  Charger plus
                </button>
              </div>
            ) : null}
          </section>
        </>
      ) : null}
    </ModulePageShell>
  );
}

