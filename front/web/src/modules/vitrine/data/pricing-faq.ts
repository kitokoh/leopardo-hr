// FAQ tarifs par locale — source unique utilisée par la page /pricing (UI)
// et par le JSON-LD FAQPage (layout, issue #3921) pour que le schéma suive
// exactement le contenu visible.
import type { AppLocale } from '@/lib/i18n';

export type PricingFaqItem = {
  id: string;
  question: string;
  answer: string;
  category: string;
};

export const pricingFaqByLocale: Record<AppLocale, PricingFaqItem[]> = {
  fr: [
{ id: 'starter-plan', question: 'Que comprend le plan Pilot ?', answer: "Le plan Pilot à 29 €/mois inclut jusqu'à 30 employés, le pointage web et mobile, les absences et congés, les dossiers employés et les bulletins de paie PDF. Essai gratuit de 14 jours, sans carte bancaire.", category: 'Essai' },
        { id: 'change-plan', question: 'Puis-je changer de plan ?', answer: 'Oui, à tout moment. Upgrade immédiat, downgrade au prochain cycle. Aucun frais caché.', category: 'Facturation' },
        { id: 'per-employee', question: 'Comment fonctionne la facturation ?', answer: "Chaque plan inclut un prix fixe par mois avec un plafond d'employés inclus (30 pour Pilot, 250 pour Operations, illimité pour Enterprise). Pas de supplément par employé actif.", category: 'Facturation' },
        { id: 'free-trial', question: 'L\'essai est-il vraiment gratuit ?', answer: 'Oui. 14 jours complets avec toutes les fonctionnalités payantes. Aucune carte bancaire requise pour s\'inscrire.', category: 'Essai' },
        { id: 'trial-to-paid', question: 'Que se passe-t-il à la fin de l\'essai ?', answer: 'Vous choisissez un plan ou vos données restent archivées 14 jours supplémentaires. Aucune facturation automatique sans votre accord.', category: 'Essai' },
        { id: 'support', question: 'Quel support est disponible ?', answer: "Pilot : support email sous 48h. Operations : support prioritaire sous 24h. Enterprise : account manager dédié + SLA contractuel.", category: 'Support' },
        { id: 'data-location', question: 'Où sont hébergées mes données ?', answer: 'En Europe (Render EU / Supabase EU). Chiffrement AES-256 au repos, TLS 1.3 en transit. Isolation par tenant garantie.', category: 'Sécurité' },
        { id: 'gdpr', question: 'Êtes-vous conformes RGPD ?', answer: 'Oui. DPA disponible, données exclusivement en Europe, droit à l\'effacement implémenté, exports de données sur demande.', category: 'Sécurité' },
        { id: 'api', question: 'L\'API est-elle disponible ?', answer: "L'API REST et les webhooks sont disponibles à partir du plan Operations. Sur Pilot, vous pouvez exporter vos données en CSV/Excel.", category: 'Technique' },
  ],
  en: [
{ id: 'starter-plan', question: 'What does the Pilot plan include?', answer: "The Pilot plan at €29/month includes up to 30 employees, web and mobile time tracking, absences and leave, employee records and PDF payslips. Free 14-day trial, no credit card required.", category: 'Trial' },
        { id: 'change-plan', question: 'Can I change plan later?', answer: 'Yes, anytime. Upgrades are instant, downgrades apply at the next cycle. No hidden fees.', category: 'Billing' },
        { id: 'per-employee', question: 'How does billing work?', answer: "Each plan includes a fixed monthly price with an included employee cap (30 for Pilot, 250 for Operations, unlimited for Enterprise). No per-active-employee surcharge.", category: 'Billing' },
        { id: 'free-trial', question: 'Is the trial really free?', answer: 'Yes. 14 full days with all paid features included. No credit card needed to sign up.', category: 'Trial' },
        { id: 'trial-to-paid', question: 'What happens when the trial ends?', answer: 'You choose a plan or your data stays archived for 14 more days. No automatic billing without your consent.', category: 'Trial' },
        { id: 'support', question: 'What support is available?', answer: "Pilot: email support within 48h. Operations: priority support within 24h. Enterprise: dedicated account manager + contractual SLA.", category: 'Support' },
        { id: 'data-location', question: 'Where is my data hosted?', answer: 'In Europe (Render EU / Supabase EU). AES-256 encryption at rest, TLS 1.3 in transit. Tenant isolation guaranteed.', category: 'Security' },
        { id: 'gdpr', question: 'Are you GDPR compliant?', answer: 'Yes. DPA available, data exclusively in Europe, right to erasure implemented, data exports on request.', category: 'Security' },
        { id: 'api', question: 'Is the API available?', answer: "REST API and webhooks are available from the Operations plan. On Pilot you can export your data as CSV/Excel.", category: 'Technical' },
  ],
  tr: [
{ id: 'starter-plan', question: 'Pilot planı neler içerir?', answer: "Ayda 29 €'dan başlayan Pilot planı 30 çalışana kadar web ve mobil yoklama, izinler, çalışan dosyaları ve PDF maaş bordrolarını içerir. 14 gün ücretsiz deneme, kredi kartı gerekmez.", category: 'Deneme' },
        { id: 'change-plan', question: 'Planı değiştirebilir miyim?', answer: 'Evet, istediğiniz zaman. Yükseltme anında, düşürme bir sonraki dönemde uygulanır. Gizli ücret yoktur.', category: 'Faturalama' },
        { id: 'per-employee', question: 'Faturalama nasıl çalışır?', answer: "Her plan, dahil edilen çalışan sınırıyla (Pilot için 30, Operations için 250, Enterprise için sınırsız) sabit bir aylık fiyat içerir. Aktif çalışan başına ek ücret yoktur.", category: 'Faturalama' },
        { id: 'free-trial', question: 'Deneme gerçekten ücretsiz mi?', answer: 'Evet. Tüm ücretli özelliklerle 14 tam gün. Kaydolmak için kredi kartı gerekmez.', category: 'Deneme' },
        { id: 'trial-to-paid', question: 'Deneme bitince ne olur?', answer: 'Bir plan seçersiniz ya da verileriniz 14 gün daha arşivlenir. Onayınız olmadan otomatik faturalama yapılmaz.', category: 'Deneme' },
        { id: 'support', question: 'Hangi destek sağlanır?', answer: "Pilot: 48 saat içinde e-posta desteği. Operations: 24 saat içinde öncelikli destek. Enterprise: özel hesap yöneticisi + sözleşmesel SLA.", category: 'Destek' },
        { id: 'data-location', question: 'Verilerim nerede barındırılır?', answer: 'Avrupa\'da (Render EU / Supabase EU). Durağan veriler AES-256, iletimde TLS 1.3. Tenant izolasyonu garantili.', category: 'Güvenlik' },
        { id: 'gdpr', question: 'KVKK uyumlu musunuz?', answer: 'Evet. DPA mevcut, veriler yalnızca Avrupa\'da, silme hakkı uygulanmış, talep üzerine veri dışa aktarımı.', category: 'Güvenlik' },
        { id: 'api', question: 'API kullanılabilir mi?', answer: "REST API ve webhook'lar Operations planından itibaren kullanılabilir. Pilot'ta verilerinizi CSV/Excel olarak dışa aktarabilirsiniz.", category: 'Teknik' },
  ],
  ar: [
{ id: 'starter-plan', question: 'ماذا تشمل خطة Pilot؟', answer: "خطة Pilot بسعر 29 يورو/شهر تشمل حتى 30 موظفًا، تسجيل الحضور عبر الويب والجوال، الإجازات، ملفات الموظفين وكشوف الرواتب PDF. تجربة مجانية 14 يومًا بدون بطاقة ائتمان.", category: 'التجربة' },
        { id: 'change-plan', question: 'هل يمكنني تغيير الخطة لاحقًا؟', answer: 'نعم، في أي وقت. الترقية فورية والتخفيض يُطبق في الدورة التالية. لا رسوم مخفية.', category: 'الفوترة' },
        { id: 'per-employee', question: 'كيف تعمل الفوترة؟', answer: "تتضمن كل خطة سعرًا شهريًا ثابتًا مع حد أقصى مضمّن للموظفين (30 لـ Pilot، 250 لـ Operations، غير محدود لـ Enterprise). لا رسوم إضافية لكل موظف نشط.", category: 'الفوترة' },
        { id: 'free-trial', question: 'هل التجربة مجانية حقًا؟', answer: 'نعم. 14 يومًا كاملة بجميع المزايا المدفوعة. لا بطاقة ائتمان للتسجيل.', category: 'التجربة' },
        { id: 'trial-to-paid', question: 'ماذا يحدث عند انتهاء التجربة؟', answer: 'تختار خطة أو تبقى بياناتك مؤرشفة 14 يومًا إضافية. لا فوترة تلقائية بدون موافقتك.', category: 'التجربة' },
        { id: 'support', question: 'ما نوع الدعم المتاح؟', answer: "Pilot: دعم عبر البريد الإلكتروني خلال 48 ساعة. Operations: دعم ذو أولوية خلال 24 ساعة. Enterprise: مدير حساب مخصص + SLA تعاقدي.", category: 'الدعم' },
        { id: 'data-location', question: 'أين تُستضاف بياناتي؟', answer: 'في أوروبا (Render EU / Supabase EU). تشفير AES-256 أثناء التخزين وTLS 1.3 أثناء النقل. عزل المستأجرين مضمون.', category: 'الأمان' },
        { id: 'gdpr', question: 'هل أنتم متوافقون مع GDPR؟', answer: 'نعم. DPA متاح، البيانات في أوروبا حصرًا، حق الحذف مُطبَّق، تصدير البيانات عند الطلب.', category: 'الأمان' },
        { id: 'api', question: 'هل API متاح؟', answer: "REST API والـ Webhooks متاحة من خطة Operations. في Pilot يمكنك تصدير بياناتك بصيغة CSV/Excel.", category: 'التقني' },
  ],
};

export function getPricingFaq(locale: AppLocale): PricingFaqItem[] {
  return pricingFaqByLocale[locale] ?? pricingFaqByLocale.fr;
}
