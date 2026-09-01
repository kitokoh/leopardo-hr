/**
 * Client du questionnaire de pré-qualification des solutions sectorielles.
 *
 * Alimente le « wizard restaurateur » : les QUESTIONS et le catalogue de
 * PACKAGES viennent du backend (`GET/POST /solutions/...`) — source de
 * vérité unique. Le front ne fait qu'afficher, pré-cocher et gérer le
 * téléchargement.
 *
 * Labels : les `label_key` / `reason_key` du backend sont résolus via
 * `SOLUTION_LABELS` (fr/en complets, tr/ar → en). La migration vers le
 * catalogue i18n central (`/i18n/catalog`) est le chemin production.
 */

import { apiFetch } from '@/lib/api-client';

export type SurveyAnswerValue = string | boolean;

export type SolutionSurveyQuestion = {
  key: string;
  type: 'select' | 'bool' | 'multi';
  label_key: string;
  options?: Array<{ value: string; label_key: string }>;
  default?: string | boolean;
};

export type SolutionPackage = {
  key: string;
  type: 'mobile' | 'module' | 'device' | 'edge';
  label_key: string;
  app?: string;
  download?: 'apk' | 'edge_install' | 'guide' | null;
  required?: boolean;
};

export type SuggestedPackage = SolutionPackage & {
  reason_key: string;
  priority: number;
};

export type SurveyResult = {
  code: string;
  packages: SuggestedPackage[];
  total: number;
};

export type SolutionSummary = {
  code: string;
  name: string;
  description: string;
  maturity: string;
};

export type SurveyData = {
  code: string;
  questions: SolutionSurveyQuestion[];
  packages: SolutionPackage[];
};

export async function fetchSolutions(): Promise<SolutionSummary[]> {
  const res = await apiFetch('/solutions');
  const payload = (await res.json()) as { data?: SolutionSummary[] };
  return Array.isArray(payload.data) ? payload.data : [];
}

export async function fetchSurvey(code: string): Promise<SurveyData | null> {
  const res = await apiFetch(`/solutions/${encodeURIComponent(code)}/survey`);
  if (!res.ok) {
    return null;
  }
  const payload = (await res.json()) as { data?: SurveyData };
  return payload.data ?? null;
}

export async function suggestPack(
  code: string,
  answers: Record<string, SurveyAnswerValue>,
): Promise<SurveyResult | null> {
  const res = await apiFetch(`/solutions/${encodeURIComponent(code)}/survey`, {
    method: 'POST',
    body: JSON.stringify({ answers }),
  });
  if (!res.ok) {
    return null;
  }
  const payload = (await res.json()) as { data?: SurveyResult };
  return payload.data ?? null;
}

/** Construit les réponses par défaut depuis les `default` des questions. */
export function buildDefaultAnswers(
  questions: SolutionSurveyQuestion[],
): Record<string, SurveyAnswerValue> {
  return Object.fromEntries(
    questions
      .filter((q) => q.default !== undefined)
      .map((q) => [q.key, q.default as SurveyAnswerValue]),
  );
}

// ──────────────────────────────────────────────────────────────────────────
// Labels localisés (résolution des clés backend).
// fr/en complets — tr/ar retombent sur en (TODO: traduire via i18n catalog).
// ──────────────────────────────────────────────────────────────────────────

export type VitrineLocale = 'fr' | 'en' | 'tr' | 'ar';

type Localized = { fr: string; en: string; tr: string; ar: string };

export const SOLUTION_LABELS: Record<string, Localized> = {
  // Questions
  'solutions.restaurant.question.service_type': { fr: 'Comment proposez-vous vos plats ?', en: 'How do you serve your dishes?', tr: 'Yemeklerinizi nasıl sunuyorsunuz?', ar: 'كيف تقدمون أطباقكم؟' },
  'solutions.restaurant.question.service_type.sur_place': { fr: 'Sur place', en: 'Dine-in', tr: 'Yerinde', ar: 'في المطعم' },
  'solutions.restaurant.question.service_type.a_emporter': { fr: 'À emporter', en: 'Takeaway', tr: 'Paket servis', ar: 'طلبات خارجية' },
  'solutions.restaurant.question.service_type.mixte': { fr: 'Mixte (sur place + à emporter)', en: 'Mixed (dine-in + takeaway)', tr: 'Karma (yerinde + paket)', ar: 'مختلط (في المطعم + طلبات خارجية)' },
  'solutions.restaurant.question.employee_count': { fr: 'Combien de salariés ?', en: 'How many employees?', tr: 'Kaç çalışanınız var?', ar: 'كم عدد موظفيكم؟' },
  'solutions.restaurant.question.employee_count.1_5': { fr: '1 à 5', en: '1–5', tr: '1–5', ar: '1–5' },
  'solutions.restaurant.question.employee_count.6_20': { fr: '6 à 20', en: '6–20', tr: '6–20', ar: '6–20' },
  'solutions.restaurant.question.employee_count.21_50': { fr: '21 à 50', en: '21–50', tr: '21–50', ar: '21–50' },
  'solutions.restaurant.question.employee_count.50_plus': { fr: 'Plus de 50', en: '50+', tr: '50+', ar: '+50' },
  'solutions.restaurant.question.attendance_device': { fr: 'Comment vos équipes pointent-elles ?', en: 'How do your teams clock in?', tr: 'Ekipleriniz yoklamayı nasıl alıyor?', ar: 'كيف يسجّل موظفوكم الحضور؟' },
  'solutions.restaurant.question.attendance_device.none': { fr: 'Pas de pointage aujourd\'hui', en: 'No attendance yet', tr: 'Henüz yoklama yok', ar: 'لا تسجيل حضور بعد' },
  'solutions.restaurant.question.attendance_device.mobile': { fr: 'Depuis le mobile (géolocalisé)', en: 'From mobile (geolocated)', tr: 'Mobil uygulamadan (konumlu)', ar: 'عبر الجوال (مع تحديد الموقع)' },
  'solutions.restaurant.question.attendance_device.kiosk': { fr: 'Borne / kiosque dans le restaurant', en: 'Kiosk on-site', tr: 'Restorandaki kiosk', ar: 'كشك داخل المطعم' },
  'solutions.restaurant.question.attendance_device.biometric': { fr: 'Badgeuse biométrique', en: 'Biometric terminal', tr: 'Biyometrik terminal', ar: 'جهاز بصمة' },
  'solutions.restaurant.question.scheduling': { fr: 'Gérez-vous des plannings d\'équipe ?', en: 'Do you manage team schedules?', tr: 'Ekip planlaması yapıyor musunuz?', ar: 'هل تديرون جداول الفرق؟' },
  'solutions.restaurant.question.payroll': { fr: 'Voulez-vous gérer la paie dans l\'app ?', en: 'Do you want payroll in the app?', tr: 'Maaş bordrosunu uygulamada yönetmek ister misiniz?', ar: 'هل تريدون إدارة الرواتب داخل التطبيق؟' },
  'solutions.restaurant.question.accounting': { fr: 'Besoin de suivi comptable ?', en: 'Need accounting tracking?', tr: 'Muhasebe takibine ihtiyacınız var mı?', ar: 'هل تحتاجون إلى متابعة محاسبية؟' },
  'solutions.restaurant.question.delivery': { fr: 'Faites-vous de la livraison ?', en: 'Do you offer delivery?', tr: 'Paket teslimat yapıyor musunuz?', ar: 'هل تقدمون خدمة التوصيل؟' },
  'solutions.restaurant.question.delivery.none': { fr: 'Non', en: 'No', tr: 'Hayır', ar: 'لا' },
  'solutions.restaurant.question.delivery.platforms': { fr: 'Oui, via les plateformes (Uber Eats…)', en: 'Yes, via platforms (Uber Eats…)', tr: 'Evet, platformlar üzerinden (Uber Eats…)', ar: 'نعم، عبر المنصات (Uber Eats…)' },
  'solutions.restaurant.question.delivery.own': { fr: 'Oui, avec ma propre flotte', en: 'Yes, with my own fleet', tr: 'Evet, kendi filomla', ar: 'نعم، بأسطولنا الخاص' },
  'solutions.restaurant.question.reservations': { fr: 'Prenez-vous des réservations en ligne ?', en: 'Do you take online reservations?', tr: 'Çevrimiçi rezervasyon alıyor musunuz?', ar: 'هل تستقبلون حجوزات عبر الإنترنت؟' },
  'solutions.restaurant.question.inventory': { fr: 'Voulez-vous suivre votre stock ?', en: 'Do you want inventory tracking?', tr: 'Stok takibi yapmak ister misiniz?', ar: 'هل تريدون تتبع المخزون؟' },
  'solutions.restaurant.question.loyalty': { fr: 'Programme de fidélité ?', en: 'Loyalty program?', tr: 'Sadakat programı?', ar: 'برنامج ولاء؟' },
  'solutions.restaurant.question.pos': { fr: 'Caisse enregistreuse connectée ?', en: 'Connected POS?', tr: 'Bağlı yazar kasa (POS)?', ar: 'نقطة بيع متصلة؟' },
  // Packages
  'solutions.restaurant.package.mobile_employee': { fr: 'App mobile employé', en: 'Employee mobile app', tr: 'Çalışan mobil uygulaması', ar: 'تطبيق الموظف للجوال' },
  'solutions.restaurant.package.mobile_manager': { fr: 'App mobile manager', en: 'Manager mobile app', tr: 'Yönetici mobil uygulaması', ar: 'تطبيق المدير للجوال' },
  'solutions.restaurant.package.attendance_mobile': { fr: 'Pointage mobile géolocalisé', en: 'Geolocated mobile attendance', tr: 'Konumlu mobil yoklama', ar: 'تسجيل حضور عبر الجوال مع تحديد الموقع' },
  'solutions.restaurant.package.kiosk': { fr: 'Kiosque de pointage (borne)', en: 'Attendance kiosk', tr: 'Yoklama kiosku', ar: 'كشك تسجيل الحضور' },
  'solutions.restaurant.package.edge': { fr: 'Nœud Edge local (offline-first)', en: 'Local Edge node (offline-first)', tr: 'Yerel Edge düğümü (çevrimdışı)', ar: 'عقدة Edge محلية (بدون اتصال)' },
  'solutions.restaurant.package.planning': { fr: 'Planning d\'équipe', en: 'Team scheduling', tr: 'Ekip planlaması', ar: 'جدولة الفرق' },
  'solutions.restaurant.package.payroll': { fr: 'Paie (multi-pays)', en: 'Payroll (multi-country)', tr: 'Maaş bordrosu (çok ülkeli)', ar: 'الرواتب (متعدد الدول)' },
  'solutions.restaurant.package.accounting': { fr: 'Comptabilité', en: 'Accounting', tr: 'Muhasebe', ar: 'المحاسبة' },
  'solutions.restaurant.package.delivery': { fr: 'Gestion de la livraison', en: 'Delivery management', tr: 'Teslimat yönetimi', ar: 'إدارة التوصيل' },
  'solutions.restaurant.package.reservations': { fr: 'Réservations en ligne', en: 'Online reservations', tr: 'Çevrimiçi rezervasyonlar', ar: 'الحجوزات عبر الإنترنت' },
  'solutions.restaurant.package.inventory': { fr: 'Gestion de stock', en: 'Inventory management', tr: 'Stok yönetimi', ar: 'إدارة المخزون' },
  'solutions.restaurant.package.loyalty': { fr: 'Fidélité & marketing', en: 'Loyalty & marketing', tr: 'Sadakat ve pazarlama', ar: 'الولاء والتسويق' },
  'solutions.restaurant.package.pos': { fr: 'Caisse (POS) connectée', en: 'Connected POS', tr: 'Bağlı yazar kasa (POS)', ar: 'نقطة بيع (POS) متصلة' },
  // Raisons
  'solutions.restaurant.reason.base': { fr: 'Indispensable : vos salariés pointent, consultent leurs fiches et leurs paies.', en: 'Essential: your employees clock in and view their payslips.', tr: 'Temel: çalışanlarınız yoklama alır ve bordrolarını görür.', ar: 'أساسي: يسجّل موظفوكم الحضور ويطّلعون على مستحقاتهم.' },
  'solutions.restaurant.reason.manager': { fr: 'Votre équipe est assez grande pour piloter depuis le mobile.', en: 'Your team is large enough to manage from mobile.', tr: 'Ekipleriniz mobil cihazdan yönetmek için yeterince büyük.', ar: 'فريقكم كبير بما يكفي للإدارة عبر الجوال.' },
  'solutions.restaurant.reason.attendance_mobile': { fr: 'Vous avez choisi le pointage mobile.', en: 'You chose mobile attendance.', tr: 'Mobil yoklamayı seçtiniz.', ar: 'اخترتم تسجيل الحضور عبر الجوال.' },
  'solutions.restaurant.reason.kiosk': { fr: 'Vous avez choisi un pointage sur borne.', en: 'You chose kiosk attendance.', tr: 'Kiosk yoklamasını seçtiniz.', ar: 'اخترتم تسجيل الحضور عبر الكشك.' },
  'solutions.restaurant.reason.edge': { fr: 'Le kiosque fonctionne même sans connexion grâce au nœud local.', en: 'The kiosk works offline thanks to the local node.', tr: 'Kiosk, yerel düğüm sayesinde internetsiz de çalışır.', ar: 'يعمل الكشك حتى بدون إنترنت بفضل العقدة المحلية.' },
  'solutions.restaurant.reason.scheduling': { fr: 'Vous gérez des plannings d\'équipe.', en: 'You manage team schedules.', tr: 'Ekip planlaması yapıyorsunuz.', ar: 'تديرون جداول الفرق.' },
  'solutions.restaurant.reason.payroll': { fr: 'Vous voulez internaliser la paie.', en: 'You want in-house payroll.', tr: 'Bordroyu şirket içinde yönetmek istiyorsunuz.', ar: 'تريدون إدارة الرواتب داخليًا.' },
  'solutions.restaurant.reason.accounting': { fr: 'Vous avez demandé un suivi comptable.', en: 'You asked for accounting tracking.', tr: 'Muhasebe takibi istediniz.', ar: 'طلبتم متابعة محاسبية.' },
  'solutions.restaurant.reason.delivery': { fr: 'Vous faites de la livraison.', en: 'You offer delivery.', tr: 'Paket teslimat yapıyorsunuz.', ar: 'تقدمون خدمة التوصيل.' },
  'solutions.restaurant.reason.reservations': { fr: 'Vous prenez des réservations.', en: 'You take reservations.', tr: 'Rezervasyon alıyorsunuz.', ar: 'تستقبلون الحجوزات.' },
  'solutions.restaurant.reason.inventory': { fr: 'Vous voulez suivre votre stock.', en: 'You want inventory tracking.', tr: 'Stok takibi istiyorsunuz.', ar: 'تريدون تتبع المخزون.' },
  'solutions.restaurant.reason.loyalty': { fr: 'Vous voulez fidéliser vos clients.', en: 'You want to retain customers.', tr: 'Müşterilerinizi sadakatle bağlamak istiyorsunuz.', ar: 'تريدون الاحتفاظ بعملائكم.' },
  'solutions.restaurant.reason.pos': { fr: 'Vous voulez une caisse connectée.', en: 'You want a connected POS.', tr: 'Bağlı yazar kasa istiyorsunuz.', ar: 'تريدون نقطة بيع متصلة.' },
};

export function solutionLabel(
  key: string,
  locale: VitrineLocale,
  fallback = key,
): string {
  const entry = SOLUTION_LABELS[key];
  if (!entry) {
    return fallback;
  }
  if (locale === 'fr') {
    return entry.fr;
  }
  return entry[locale] ?? entry.en; // en = langue de repli ultime
}

export const localeFromAppLocale = (locale: string): VitrineLocale =>
  locale === 'fr' ? 'fr' : locale === 'tr' ? 'tr' : locale === 'ar' ? 'ar' : 'en';
