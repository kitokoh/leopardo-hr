/**
 * Leopardo Edge — copie d'interface localisée (issue #4806).
 *
 * L'app web-offline est une petite PWA sans infra i18n : on garde une carte
 * locale minimale (fr/en/tr/ar) dans ce module, avec détection depuis
 * `navigator.language` et repli sur le français (locale historique du produit).
 *
 * Les chaînes techniques (marques, URL, libellés API) restent volontairement
 * non traduites : "Node ID", "Leopardo Edge", "Statut", "http://leopardo.local".
 */

export type UiLocale = 'fr' | 'en' | 'tr' | 'ar';

export type UiCopy = {
  statusCheck: string;
  statusOnline: string;
  statusOffline: string;
  statusError: string;
  statusCardTitle: string;
  lastSync: string;
  pendingSync: string;
  pendingCount: (count: number) => string;
  connecting: string;
  unreachable: string;
  refresh: string;
  syncTitle: string;
  syncButton: string;
  offlineNotice: string;
  footer: (url: string) => string;
};

export const UI_LOCALES: UiLocale[] = ['fr', 'en', 'tr', 'ar'];

export const uiCopy: Record<UiLocale, UiCopy> = {
  fr: {
    statusCheck: 'Vérification…',
    statusOnline: 'Edge en ligne',
    statusOffline: 'Hors ligne',
    statusError: 'Erreur de connexion',
    statusCardTitle: 'Statut du node Edge',
    lastSync: 'Dernière synchronisation',
    pendingSync: 'En attente de sync',
    pendingCount: (count) => `${count} enregistrement(s)`,
    connecting: 'Connexion à http://leopardo.local…',
    unreachable: 'Node Edge non joignable. Les pointages seront enregistrés localement.',
    refresh: 'Actualiser',
    syncTitle: 'La synchronisation nécessite un nodeId et un jeton Edge authentifié.',
    syncButton: 'Synchronisation disponible depuis le node Edge authentifié',
    offlineNotice:
      "⚠️ Le node Edge local n'est pas joignable. Assurez-vous d'être connecté au réseau local ou que le service Edge est démarré sur le serveur.",
    footer: (url) => `Leopardo RH — Interface Edge locale · ${url}`,
  },
  en: {
    statusCheck: 'Checking…',
    statusOnline: 'Edge online',
    statusOffline: 'Offline',
    statusError: 'Connection error',
    statusCardTitle: 'Edge node status',
    lastSync: 'Last synchronization',
    pendingSync: 'Pending sync',
    pendingCount: (count) => `${count} record(s)`,
    connecting: 'Connecting to http://leopardo.local…',
    unreachable: 'Edge node unreachable. Punches will be recorded locally.',
    refresh: 'Refresh',
    syncTitle: 'Synchronization requires a nodeId and an authenticated Edge token.',
    syncButton: 'Synchronization available from the authenticated Edge node',
    offlineNotice:
      '⚠️ The local Edge node is unreachable. Make sure you are connected to the local network or that the Edge service is running on the server.',
    footer: (url) => `Leopardo RH — Local Edge interface · ${url}`,
  },
  tr: {
    statusCheck: 'Kontrol ediliyor…',
    statusOnline: 'Edge çevrimiçi',
    statusOffline: 'Çevrimdışı',
    statusError: 'Bağlantı hatası',
    statusCardTitle: 'Edge düğüm durumu',
    lastSync: 'Son senkronizasyon',
    pendingSync: 'Bekleyen senkronizasyon',
    pendingCount: (count) => `${count} kayıt`,
    connecting: 'http://leopardo.local bağlanılıyor…',
    unreachable: 'Edge düğümüne ulaşılamıyor. Yoklamalar yerel olarak kaydedilecek.',
    refresh: 'Yenile',
    syncTitle: 'Senkronizasyon bir nodeId ve kimliği doğrulanmış Edge jetonu gerektirir.',
    syncButton: 'Kimliği doğrulanmış Edge düğümünden senkronizasyon mevcut',
    offlineNotice:
      '⚠️ Yerel Edge düğümüne ulaşılamıyor. Yerel ağa bağlı olduğunuzdan veya Edge hizmetinin sunucuda çalıştığından emin olun.',
    footer: (url) => `Leopardo RH — Yerel Edge Arayüzü · ${url}`,
  },
  ar: {
    statusCheck: 'جارٍ التحقق…',
    statusOnline: 'Edge متصل',
    statusOffline: 'غير متصل',
    statusError: 'خطأ في الاتصال',
    statusCardTitle: 'حالة عقدة Edge',
    lastSync: 'آخر مزامنة',
    pendingSync: 'مزامنة معلقة',
    pendingCount: (count) => `${count} تسجيل(ات)`,
    connecting: 'جارٍ الاتصال بـ http://leopardo.local…',
    unreachable: 'عقدة Edge غير قابلة للوصول. سيتم تسجيل الحضور محلياً.',
    refresh: 'تحديث',
    syncTitle: 'تتطلب المزامنة nodeId ورمز Edge موثقاً.',
    syncButton: 'المزامنة متاحة من عقدة Edge الموثقة',
    offlineNotice:
      '⚠️ عقدة Edge المحلية غير قابلة للوصول. تأكد من اتصالك بالشبكة المحلية أو من أن خدمة Edge تعمل على الخادم.',
    footer: (url) => `ليوباردو RH — واجهة Edge المحلية · ${url}`,
  },
};

/**
 * Résout la locale d'interface à partir de `navigator.language` (préfixe),
 * avec repli sur le français. `tr-TR` → tr, `ar-MA` → ar, `en-US` → en,
 * tout autre préfixe (ou environnement sans navigator, ex. SSR) → fr.
 */
export function detectUiLocale(): UiLocale {
  if (typeof navigator === 'undefined') {
    return 'fr';
  }
  const lang = navigator.language.toLowerCase();
  if (lang.startsWith('tr')) return 'tr';
  if (lang.startsWith('ar')) return 'ar';
  if (lang.startsWith('en')) return 'en';
  return 'fr';
}

export function getUiCopy(locale: UiLocale = detectUiLocale()): UiCopy {
  return uiCopy[locale] ?? uiCopy.fr;
}
