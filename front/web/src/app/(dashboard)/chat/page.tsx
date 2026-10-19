'use client';

/**
 * #8751 [BOS-CHAT] — page « Messagerie » de l'espace client.
 *
 * Intégrée au design system existant (`ModulePageShell`) et branchée sur l'API
 * réelle `/conversations*`. Aucun frontend séparé : la page vit dans
 * `app/(dashboard)/chat`, comme les autres modules.
 *
 * Récupération de l'identité : la page n'invente pas un second système d'auth.
 * Elle lit l'employé courant par l'API existante (`/auth/me`) et se contente de
 * `null` si l'appel échoue — l'UI reste utilisable, seuls les boutons
 * d'édition/suppression (réservés à l'auteur) ne s'affichent pas.
 */

import { MessageSquare } from 'lucide-react';
import { useCallback, useEffect, useState, useSyncExternalStore } from 'react';

import { ModulePageShell } from '@/components/module-page-shell';
import { ConversationList } from '@/components/chat/conversation-list';
import { ConversationPanel } from '@/components/chat/conversation-panel';
import { apiFetch } from '@/lib/api-client';
import { fetchThreads, type ChatThread } from '@/lib/chat';
import { getPreferredLocale, type AppLocale } from '@/lib/i18n';
import { chatT } from '@/lib/i18n/chat';

export default function ChatPage() {
  const [threads, setThreads] = useState<ChatThread[]>([]);
  const [selectedId, setSelectedId] = useState<number | null>(null);
  const [loading, setLoading] = useState<boolean>(true);
  const [error, setError] = useState<string | null>(null);
  const [offline, setOffline] = useState<boolean>(false);
  const [currentUserId, setCurrentUserId] = useState<number | null>(null);

  // Identité courante — via l'endpoint existant, jamais via un décodage de JWT
  // côté client (le jeton est httpOnly, donc illisible par construction).
  useEffect(() => {
    let cancelled = false;

    apiFetch('/auth/me')
      .then(async (response) => {
        const payload = (await response.json()) as { data?: { id?: number }; id?: number };
        const id = payload.data?.id ?? payload.id ?? null;

        if (!cancelled && typeof id === 'number') setCurrentUserId(id);
      })
      .catch(() => {
        // Non bloquant : sans identité, l'écran reste consultable.
      });

    return () => {
      cancelled = true;
    };
  }, []);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);

    try {
      const data = await fetchThreads();
      setThreads(data);
      setOffline(false);

      // Première visite : on ouvre la conversation la plus récente, ce qui évite
      // un écran vide alors que des fils existent.
      setSelectedId((current) => current ?? data[0]?.id ?? null);
    } catch (cause) {
      const isNetwork = cause instanceof TypeError;

      setOffline(isNetwork);
      setError(
        isNetwork
          ? chatT(locale, 'offlineNotice')
          : cause instanceof Error && cause.message !== ''
            ? cause.message
            : chatT(locale, 'loadError'),
      );
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    void load();
  }, [load]);

  // Retour du réseau : on recharge la liste sans action de l'utilisateur.
  useEffect(() => {
    const onOnline = (): void => void load();

    window.addEventListener('online', onOnline);

    return () => window.removeEventListener('online', onOnline);
  }, [load]);

  const selected = threads.find((thread) => thread.id === selectedId) ?? null;

  const markActivity = useCallback((threadId: number) => {
    // Une activité dans un fil le fait remonter et le marque lu : le serveur a
    // déjà enregistré la lecture (GET de l'historique), on reflète seulement.
    setThreads((current) =>
      current.map((thread) =>
        thread.id === threadId ? { ...thread, unread: false, last_message_at: new Date().toISOString() } : thread,
      ),
    );
  }, []);

  const locale = useSyncExternalStore<AppLocale>(() => () => {}, getPreferredLocale, () => 'fr');

  return (
    <ModulePageShell
      title={chatT(locale, 'pageTitle')}
      subtitle={chatT(locale, 'pageSubtitle')}
      description={chatT(locale, 'pageDescription')}
      icon={MessageSquare}
    >
      {/* Hauteur contrainte : la liste et le fil défilent indépendamment, et
          `min-h-0` est indispensable pour que le scroll interne gagne sur la
          hauteur du parent (piège classique flexbox). */}
      <div className="flex h-[calc(100dvh-13rem)] min-h-[26rem] gap-3">
        <div className={`${selected !== null ? 'hidden md:block' : 'block'} h-full md:w-80 md:shrink-0`}>
          <ConversationList
            threads={threads}
            selectedId={selectedId}
            loading={loading}
            error={error}
            offline={offline}
            onSelect={setSelectedId}
          />
        </div>

        <div className={`${selected !== null ? 'block' : 'hidden md:block'} min-h-0 min-w-0 flex-1`}>
          <ConversationPanel
            thread={selected}
            currentUserId={currentUserId}
            onBack={() => setSelectedId(null)}
            onThreadActivity={markActivity}
          />
        </div>
      </div>
    </ModulePageShell>
  );
}
