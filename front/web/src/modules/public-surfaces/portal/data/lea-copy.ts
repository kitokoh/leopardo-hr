/**
 * Leopardo Public — copie i18n de la surface « Léa » (assistant).
 *
 * ## Pourquoi un fichier séparé
 *
 * L'onglet Messages doit présenter **deux choses distinctes** : les
 * conversations avec les professionnels, et Léa. Les mélanger dans une même
 * liste ferait croire au client qu'il écrit à un commerçant alors qu'il parle à
 * un assistant — c'est exactement ce que la spec interdit (« Léa séparée »).
 *
 * ## Ce que cette copie dit, et pourquoi elle le dit
 *
 * Léa n'a **pas** de point d'entrée public aujourd'hui : la passerelle IA
 * (`POST /api/v1/ai/chat`) exige un contexte tenant (`AITenantInjector`,
 * `companyId`), et un client grand public n'en a pas. La copie le dit donc
 * explicitement plutôt que d'afficher un champ de saisie qui échouerait.
 *
 * Un écran qui promet un assistant inexistant est pire qu'un écran qui explique
 * pourquoi il n'est pas encore là.
 */

import type { PublicPortalLocale } from './copy';

export interface PublicLeaCopy {
  /** Titre de la section Léa dans l'onglet Messages. */
  title: string;
  /** Sous-titre : ce que Léa fera. */
  subtitle: string;
  /** État « pas encore disponible » — la vérité, pas une promesse. */
  unavailableTitle: string;
  unavailableBody: string;
  /** Séparateur de la section commerçants. */
  merchantsTitle: string;
  /** Libellé accessible du groupe Léa (distinct du groupe commerçants). */
  leaGroupLabel: string;
  merchantsGroupLabel: string;
}

const fr: PublicLeaCopy = {
  title: 'Léa',
  subtitle: 'L’assistante Leopardo : elle vous aide à trouver le bon professionnel.',
  unavailableTitle: 'Léa n’est pas encore disponible ici',
  unavailableBody:
    'L’assistante est aujourd’hui réservée aux professionnels. Sur le portail grand public, vous écrivez directement aux commerçants — sans intermédiaire. Léa arrivera dans une prochaine version.',
  merchantsTitle: 'Vos conversations',
  leaGroupLabel: 'Assistante Léa',
  merchantsGroupLabel: 'Conversations avec les professionnels',
};

const en: PublicLeaCopy = {
  title: 'Léa',
  subtitle: 'The Leopardo assistant: she helps you find the right business.',
  unavailableTitle: 'Léa is not available here yet',
  unavailableBody:
    'The assistant is currently reserved for businesses. On the public portal you write directly to merchants — with no middleman. Léa will arrive in a future version.',
  merchantsTitle: 'Your conversations',
  leaGroupLabel: 'Léa assistant',
  merchantsGroupLabel: 'Conversations with businesses',
};

const tr: PublicLeaCopy = {
  title: 'Léa',
  subtitle: 'Leopardo asistanı: doğru işletmeyi bulmanıza yardımcı olur.',
  unavailableTitle: 'Léa burada henüz kullanılamıyor',
  unavailableBody:
    'Asistan şu anda işletmelere ayrılmıştır. Genel portalde doğrudan işletmelere yazarsınız — aracı yok. Léa gelecek bir sürümde gelecek.',
  merchantsTitle: 'Sohbetleriniz',
  leaGroupLabel: 'Léa asistanı',
  merchantsGroupLabel: 'İşletmelerle sohbetler',
};

const ar: PublicLeaCopy = {
  title: 'ليا',
  subtitle: 'مساعدة ليوباردو: تساعدك في العثور على النشاط التجاري المناسب.',
  unavailableTitle: 'ليا غير متاحة هنا بعد',
  unavailableBody:
    'المساعدة مخصّصة حاليًا للأنشطة التجارية. في البوابة العامة تكتب مباشرة إلى التجار — دون وسيط. ستصل ليا في إصدار قادم.',
  merchantsTitle: 'محادثاتك',
  leaGroupLabel: 'المساعدة ليا',
  merchantsGroupLabel: 'المحادثات مع الأنشطة التجارية',
};

const LEA_COPY: Record<PublicPortalLocale, PublicLeaCopy> = { fr, en, tr, ar };

/** Copie de la surface Léa pour une locale (repli FR). */
export function getPublicLeaCopy(locale: string): PublicLeaCopy {
  return LEA_COPY[(locale as PublicPortalLocale) in LEA_COPY ? (locale as PublicPortalLocale) : 'fr'];
}
