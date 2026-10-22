import type { Metadata } from 'next';
import { normalizeLocale } from '@/lib/i18n';
import { t } from '@/lib/i18n/locale-catalog';
import { SITE_URL } from '@/lib/site-url';
import ConversationsSpace from './conversations-space';

/**
 * #8760 [BOS-CHAT] — Espace conversations du client public (`/conversations`).
 *
 * Étape 10 du parcours cible : « le client retrouve conversations et suivi dans
 * son espace public ». La page est un simple conteneur serveur — l'état
 * (liste, fil, polling) vit dans `ConversationsSpace`, qui parle directement au
 * contrat `/api/v1/public/conversations` avec le cookie de session du client.
 *
 * ⚠️ `robots: noindex` : c'est un espace PERSONNEL. L'indexer exposerait des
 * URLs de conversations dans les moteurs de recherche, et le contenu n'a aucun
 * intérêt public — contrairement à `/decouvrir`, qui est la page indexable.
 */
interface ConversationsPageProps {
  searchParams: Promise<{ lang?: string }>;
}

export async function generateMetadata({ searchParams }: ConversationsPageProps): Promise<Metadata> {
  const { lang } = await searchParams;
  const locale = normalizeLocale(lang ?? '');

  return {
    title: t(locale, 'discovery.conversationsTitle'),
    alternates: { canonical: `${SITE_URL}/conversations` },
    robots: { index: false, follow: false },
  };
}

export default async function ConversationsPage({ searchParams }: ConversationsPageProps) {
  const params = await searchParams;
  const locale = normalizeLocale(params.lang ?? '');

  return (
    <main className="mx-auto w-full max-w-2xl px-4 py-6">
      <ConversationsSpace locale={locale} />
    </main>
  );
}
