/**
 * Copy ×4 (fr/en/tr/ar) des pages vitrine « Pack offert » par métier
 * (/packs/[vertical]).
 *
 * Vit dans `data/` (chemin exempté par la garde PA2-I18N-014), même
 * mécanique que `restaurant-wizard.ts`. La promesse est identique à la page
 * /restaurateur : le pack métier est OFFERT — c'est l'offre d'entrée grand
 * public du Business OS.
 */

import type { AppLocale } from '@/lib/i18n';

export type PackVerticalSlug = 'station-service' | 'ecole' | 'agence-de-voyage' | 'cagnottes' | 'vtc' | 'trade';

export const PACK_VERTICALS: PackVerticalSlug[] = [
  'station-service',
  'ecole',
  'agence-de-voyage',
  'cagnottes',
  'vtc',
  // BC-36 TRADE (TRADE-009) — commerce international & B2B domestique.
  'trade',
];

/**
 * #8454 — slug de page pack → secteur de l'entretien de préparation
 * (allowlist `SetupInterviewPlanner::QUESTIONS['sector']`). Le CTA « Activer
 * mon pack » porte ce paramètre : le métier est présélectionné à
 * l'inscription et l'entretien ne repose pas la question secteur.
 */
export const PACK_SECTORS: Record<PackVerticalSlug, string> = {
  'station-service': 'fuel_station',
  ecole: 'education',
  'agence-de-voyage': 'travel',
  cagnottes: 'association',
  vtc: 'services',
  // BC-36 TRADE (TRADE-009/#8484) — le commerce international n'a pas de
  // secteur dédié dans l'allowlist `SetupInterviewPlanner::QUESTIONS['sector']`
  // (`restaurant|fuel_station|education|commerce|services|travel|association|other`) :
  // on retient `commerce`, qui couvre l'activité d'achat/revente et évite
  // d'inventer une valeur que le serveur rejetterait.
  trade: 'commerce',
};

export type PackBenefitIcon =
  | 'clock'
  | 'calendar'
  | 'chart'
  | 'users'
  | 'file'
  | 'wallet'
  | 'ticket'
  | 'store'
  | 'shield'
  | 'car';

export type PackPageCopy = {
  badge: string;
  title: string;
  highlight: string;
  subtitle: string;
  points: string[];
  benefitsTitle: string;
  benefits: Array<{ icon: PackBenefitIcon; title: string; text: string }>;
  includedTitle: string;
  included: string[];
  ctaPrimary: string;
  ctaSecondary: string;
  note: string;
};

export const PACK_PAGES: Record<PackVerticalSlug, Record<AppLocale, PackPageCopy>> = {
  // BC-36 TRADE (TRADE-009) — « Trade » : le point d'entrée de la verticale
  // commerce international. Le visiteur choisit d'abord le PÉRIMÈTRE
  // (International / Domestique), puis son rôle — c'est exactement ce que
  // porte le survey `trade` côté serveur (TradeSurvey). Le pack est offert.
  trade: {
    fr: {
      badge: 'Pack Commerce international offert',
      title: 'Importez, exportez ou vendez en gros',
      highlight: 'sans tableur ni e-mails perdus.',
      subtitle:
        'Décrivez ce que vous cherchez, envoyez une demande de prix à plusieurs fournisseurs, comparez les offres, chiffrez le coût réel rendu (transport, assurance, douane, taxes) et suivez chaque commande jusqu’à la livraison — le tout dans un espace unique. Activez votre pack Commerce international gratuitement.',
      points: ['Pack offert', 'Sans carte bancaire', 'Importer ET exporter'],
      benefitsTitle: 'Pensé pour le négoce international et le B2B',
      benefits: [
        { icon: 'file', title: 'Demandes de prix multi-fournisseurs', text: 'Une demande structurée (produit, quantités, destination, budget, délai) diffusée à plusieurs fournisseurs, avec suivi des réponses reçues — fini le suivi par e-mails dispersés.' },
        { icon: 'chart', title: 'Offres comparables', text: 'Les offres sont comparées à périmètre égal (Incoterm, frais inclus ou exclus, validité, délai) : deux prix qui ne couvrent pas la même chose ne sont jamais présentés comme équivalents.' },
        { icon: 'wallet', title: 'Coût rendu chiffré', text: 'Prix d’achat, fret international, assurance, manutention, droits, taxes, transport final : chaque poste porte sa source, et un taux de change inconnu n’est jamais inventé.' },
        { icon: 'shield', title: 'Aide à la préparation douanière', text: 'Documents potentiellement requis et informations manquantes signalées, avec orientation vers un transitaire qualifié. Estimation, jamais décision officielle.' },
      ],
      includedTitle: 'Inclus dans le pack',
      included: [
        'Espace d’achat : demandes de prix (RFQ), invitations fournisseurs, comparaison d’offres',
        'Espace de vente : catalogue produits, offres, devis et factures pro forma, suivi clients',
        'Dossiers de transaction : conversations, documents et historique complet au même endroit',
        'Commandes et expéditions suivies jusqu’à la livraison',
        'Demandes de cotation transport et réponses structurées des transitaires',
        'Calcul du coût total rendu, avec conversion monétaire tracée',
        'Aide à la préparation documentaire douanière',
        'Extensible aux prestataires : transporteurs, transitaires, contrôle qualité',
      ],
      ctaPrimary: 'Activer mon pack gratuit',
      ctaSecondary: 'Parler à un conseiller',
      note: 'Activation gratuite à la création de votre espace — choisissez « International » ou « Domestique » à l’entretien de préparation. Une même entreprise peut importer et exporter. Aucun engagement.',
    },
    en: {
      badge: 'Free International Trade Pack',
      title: 'Import, export or wholesale',
      highlight: 'without spreadsheets or lost emails.',
      subtitle:
        'Describe what you need, send a structured request for quote to several suppliers, compare offers, work out the true landed cost (freight, insurance, duties, taxes) and track every order through to delivery — all in one workspace. Activate your International Trade Pack for free.',
      points: ['Free pack', 'No credit card', 'Import AND export'],
      benefitsTitle: 'Built for international trade and B2B',
      benefits: [
        { icon: 'file', title: 'Multi-supplier requests for quote', text: 'One structured request (product, quantities, destination, budget, deadline) sent to several suppliers, with responses tracked — no more chasing scattered email threads.' },
        { icon: 'chart', title: 'Comparable offers', text: 'Offers are compared on equal footing (Incoterm, fees included or excluded, validity, lead time): two prices that do not cover the same scope are never presented as equivalent.' },
        { icon: 'wallet', title: 'Costed landed price', text: 'Purchase price, international freight, insurance, handling, duties, taxes, final transport: every line carries its source, and an unknown exchange rate is never invented.' },
        { icon: 'shield', title: 'Customs preparation support', text: 'Potentially required documents and missing information flagged, with referral to a qualified broker. Estimation only, never an official decision.' },
      ],
      includedTitle: 'Included in your free pack',
      included: [
        'Buyer workspace: requests for quote, supplier invitations, offer comparison',
        'Seller workspace: product catalogue, offers, quotes and proforma invoices, client follow-up',
        'Transaction files: conversations, documents and full history in one place',
        'Orders and shipments tracked through to delivery',
        'Freight quote requests and structured carrier responses',
        'Total landed-cost calculation with traced currency conversion',
        'Customs document preparation support',
        'Extensible to service providers: carriers, freight forwarders, quality control',
      ],
      ctaPrimary: 'Activate my free pack',
      ctaSecondary: 'Talk to an advisor',
      note: 'Free activation when you create your workspace — pick “International” or “Domestic” during setup. One company can both import and export. No commitment.',
    },
    tr: {
      badge: 'Ücretsiz Uluslararası Ticaret Paketi',
      title: 'İthal edin, ihraç edin veya toptan satın',
      highlight: 'tablo ve kayıp e-postalar olmadan.',
      subtitle:
        'Ne aradığınızı tanımlayın, birkaç tedarikçiye yapılandırılmış fiyat talebi gönderin, teklifleri karşılaştırın, gerçek teslim maliyetini hesaplayın (navlun, sigorta, gümrük, vergiler) ve her siparişi teslimata kadar izleyin — hepsi tek alanda. Uluslararası Ticaret Paketinizi ücretsiz etkinleştirin.',
      points: ['Ücretsiz paket', 'Kredi kartı yok', 'İthalat VE ihracat'],
      benefitsTitle: 'Uluslararası ticaret ve B2B için tasarlandı',
      benefits: [
        { icon: 'file', title: 'Çok tedarikçili fiyat talebi', text: 'Tek yapılandırılmış talep (ürün, miktar, varış yeri, bütçe, süre) birden fazla tedarikçiye gönderilir ve yanıtlar izlenir — dağınık e-posta zincirleri son bulur.' },
        { icon: 'chart', title: 'Karşılaştırılabilir teklifler', text: 'Teklifler eşit kapsamda karşılaştırılır (Incoterm, dahil/hariç masraflar, geçerlilik, süre): aynı kapsamı kapsamayan iki fiyat asla eşdeğer gösterilmez.' },
        { icon: 'wallet', title: 'Hesaplanmış teslim maliyeti', text: 'Alış fiyatı, navlun, sigorta, elleçleme, gümrük vergileri, vergiler, son taşıma: her kalem kaynağını taşır ve bilinmeyen kur asla uydurulmaz.' },
        { icon: 'shield', title: 'Gümrük hazırlığı desteği', text: 'Olası gerekli belgeler ve eksik bilgiler işaretlenir, yetkin bir müşavir yönlendirmesi yapılır. Yalnızca tahmin, asla resmî karar değildir.' },
      ],
      includedTitle: 'Ücretsiz paketinize dahil',
      included: [
        'Alıcı alanı: fiyat talepleri, tedarikçi davetleri, teklif karşılaştırması',
        'Satıcı alanı: ürün kataloğu, teklifler, proforma faturalar, müşteri takibi',
        'İşlem dosyaları: görüşmeler, belgeler ve tüm geçmiş tek yerde',
        'Sipariş ve sevkiyatların teslimata kadar takibi',
        'Navlun teklif talepleri ve taşıyıcı yanıtları',
        'Para birimi dönüşümü izlenerek toplam teslim maliyeti hesaplama',
        'Gümrük belge hazırlığı desteği',
        'Hizmet sağlayıcılara genişletilebilir: taşıyıcılar, müşavirler, kalite kontrol',
      ],
      ctaPrimary: 'Ücretsiz paketimi etkinleştir',
      ctaSecondary: 'Bir danışmanla konuş',
      note: 'Çalışma alanınızı oluştururken ücretsiz etkinleşir — kurulumda “Uluslararası” veya “Yurt içi” seçin. Bir şirket hem ithalat hem ihracat yapabilir. Taahhüt yok.',
    },
    ar: {
      badge: 'حزمة التجارة الدولية مجانًا',
      title: 'استورد أو صدّر أو بِع بالجملة',
      highlight: 'دون جداول ولا رسائل ضائعة.',
      subtitle:
        'صِف ما تبحث عنه، وأرسل طلب عرض سعر منظمًا إلى عدة موردين، وقارن العروض، واحسب تكلفة الوصول الحقيقية (الشحن، التأمين، الرسوم، الضرائب)، وتابع كل طلبية حتى التسليم — كل ذلك في مساحة واحدة. فعّل حزمة التجارة الدولية مجانًا.',
      points: ['حزمة مجانية', 'بدون بطاقة بنكية', 'الاستيراد والتصدير'],
      benefitsTitle: 'مصممة للتجارة الدولية وB2B',
      benefits: [
        { icon: 'file', title: 'طلبات عروض من عدة موردين', text: 'طلب واحد منظم (المنتج، الكميات، الوجهة، الميزانية، المهلة) يُرسل إلى عدة موردين مع تتبع الردود — وداعًا لسلاسل البريد المتناثرة.' },
        { icon: 'chart', title: 'عروض قابلة للمقارنة', text: 'تُقارن العروض على أساس متساوٍ (الإنكوترم، الرسوم المشمولة أو المستبعدة، الصلاحية، المدة): لا يُعرض سعران مختلفا النطاق كأنهما متكافئان.' },
        { icon: 'wallet', title: 'تكلفة وصول محسوبة', text: 'سعر الشراء، الشحن الدولي، التأمين، المناولة، الرسوم الجمركية، الضرائب، النقل النهائي: كل بند يحمل مصدره، وسعر صرف مجهول لا يُخترع أبدًا.' },
        { icon: 'shield', title: 'دعم تحضير التخليص الجمركي', text: 'إشارة إلى المستندات المحتمل طلبها والمعلومات الناقصة، مع توجيه إلى مخلص جمركي مؤهل. تقدير فقط، وليس قرارًا رسميًا.' },
      ],
      includedTitle: 'مضمّن في حزمتك المجانية',
      included: [
        'مساحة المشتري: طلبات عروض الأسعار، دعوات الموردين، مقارنة العروض',
        'مساحة البائع: كتالوج المنتجات، العروض، الفواتير الأولية، متابعة العملاء',
        'ملفات الصفقات: المحادثات والمستندات والسجل الكامل في مكان واحد',
        'متابعة الطلبيات والشحنات حتى التسليم',
        'طلبات عروض الشحن وردود منظمة من الناقلين',
        'حساب تكلفة الوصول الإجمالية مع تتبع تحويل العملة',
        'دعم تحضير المستندات الجمركية',
        'قابلة للتوسع لمقدمي الخدمات: الناقلون، المخلصون، مراقبة الجودة',
      ],
      ctaPrimary: 'فعّل حزمتي المجانية',
      ctaSecondary: 'تحدث إلى مستشار',
      note: 'تفعيل مجاني عند إنشاء مساحتك — اختر «دولي» أو «محلي» أثناء التهيئة. يمكن للشركة الواحدة أن تستورد وتصدّر. بلا التزام.',
    },
  },
  // FUND-095 (#8414) — pack « Cagnottes solidaires » : la verticale de
  // collecte de fonds (lien public, carte + mobile money, reversements)
  // rejoint l'offre d'entrée grand public de la vitrine.
  cagnottes: {
    fr: {
      badge: 'Pack Cagnottes solidaires offert',
      title: 'Lancez votre cagnotte en 2 minutes,',
      highlight: 'le pack est offert.',
      subtitle:
        'Créez une cagnotte pour une personne ou une cause, partagez son lien public et collectez par carte bancaire et mobile money — puis reversez les fonds au bénéficiaire. Activez votre pack Cagnottes solidaires gratuitement.',
      points: ['Pack offert', 'Sans carte bancaire', 'Lien de collecte immédiat'],
      benefitsTitle: 'Pensé pour les cagnottes solidaires',
      benefits: [
        { icon: 'ticket', title: 'Lien public de collecte', text: 'Chaque cagnotte publie une page de don partageable (WhatsApp, réseaux, QR code) — aucun compte requis pour donner.' },
        { icon: 'wallet', title: 'Carte et mobile money', text: 'Vos contributeurs paient par carte bancaire ou mobile money, avec montants suggérés et garde-fous configurables.' },
        { icon: 'users', title: 'Reversement au bénéficiaire', text: 'Suivez la collecte en temps réel et reversez le solde au bénéficiaire, en toute traçabilité.' },
      ],
      includedTitle: 'Inclus dans le pack',
      included: [
        'Cagnottes illimitées (brouillon, publication, pause, clôture)',
        'Page publique de don i18n fr/en/ar/tr avec mur des soutiens',
        'Contributions carte bancaire (Stripe) et mobile money',
        'Reversements au bénéficiaire (mobile money ou virement)',
        'Paramétrage complet : méthodes, montants, anonymat, messages',
      ],
      ctaPrimary: 'Créer ma cagnotte',
      ctaSecondary: 'Parler à un conseiller',
      note: 'Activation gratuite à la création de votre espace — répondez « Association / ONG » à l\'entretien de préparation. Aucun engagement.',
    },
    en: {
      badge: 'Free Solidarity Fundraisers Pack',
      title: 'Launch your fundraiser in 2 minutes,',
      highlight: 'the pack is free.',
      subtitle:
        'Create a fundraiser for a person or a cause, share its public link and collect by bank card and mobile money — then pay out the funds to the beneficiary. Activate your Solidarity Fundraisers Pack for free.',
      points: ['Free pack', 'No credit card', 'Instant collection link'],
      benefitsTitle: 'Built for solidarity fundraisers',
      benefits: [
        { icon: 'ticket', title: 'Public collection link', text: 'Every fundraiser publishes a shareable donation page (WhatsApp, socials, QR code) — no account needed to give.' },
        { icon: 'wallet', title: 'Card and mobile money', text: 'Your contributors pay by bank card or mobile money, with suggested amounts and configurable limits.' },
        { icon: 'users', title: 'Payout to the beneficiary', text: 'Track the collection in real time and pay out the balance to the beneficiary, fully audited.' },
      ],
      includedTitle: 'Included in the pack',
      included: [
        'Unlimited fundraisers (draft, publish, pause, close)',
        'Public donation page i18n fr/en/ar/tr with supporters wall',
        'Bank card (Stripe) and mobile money contributions',
        'Payouts to the beneficiary (mobile money or bank transfer)',
        'Full settings: methods, amounts, anonymity, messages',
      ],
      ctaPrimary: 'Create my fundraiser',
      ctaSecondary: 'Talk to an advisor',
      note: 'Free activation when you create your workspace — answer "Nonprofit / NGO" in the setup interview. No commitment.',
    },
    tr: {
      badge: 'Ücretsiz Dayanışma Kampanyası Paketi',
      title: 'Kampanyanızı 2 dakikada başlatın,',
      highlight: 'paket ücretsiz.',
      subtitle:
        'Bir kişi veya amaç için kampanya oluşturun, herkese açık bağlantısını paylaşın ve banka kartı ve mobil para ile toplayın — sonra parayı hak sahibine aktarın. Dayanışma Kampanyası Paketinizi ücretsiz etkinleştirin.',
      points: ['Ücretsiz paket', 'Kredi kartı yok', 'Anında bağış bağlantısı'],
      benefitsTitle: 'Dayanışma kampanyaları için tasarlandı',
      benefits: [
        { icon: 'ticket', title: 'Herkese açık bağış bağlantısı', text: 'Her kampanya paylaşılabilir bir bağış sayfası yayınlar (WhatsApp, sosyal medya, QR kod) — bağış için hesap gerekmez.' },
        { icon: 'wallet', title: 'Kart ve mobil para', text: 'Katkıda bulunanlar banka kartı veya mobil para ile öder; önerilen tutarlar ve yapılandırılabilir limitlerle.' },
        { icon: 'users', title: 'Hak sahibine ödeme', text: 'Bağışı gerçek zamanlı takip edin ve bakiyeyi tam denetim iziyle hak sahibine aktarın.' },
      ],
      includedTitle: 'Pakete dahil olanlar',
      included: [
        'Sınırsız kampanya (taslak, yayın, duraklatma, kapatma)',
        'Destekçi duvarlı fr/en/ar/tr bağış sayfası',
        'Banka kartı (Stripe) ve mobil para katkıları',
        'Hak sahibine ödemeler (mobil para veya havale)',
        'Tam ayarlar: yöntemler, tutarlar, anonimlik, mesajlar',
      ],
      ctaPrimary: 'Kampanyamı oluştur',
      ctaSecondary: 'Bir danışmanla konuş',
      note: 'Alanınızı oluştururken ücretsiz etkinleştirme — kurulum görüşmesinde "Dernek / STK" yanıtını verin. Taahhüt yok.',
    },
    ar: {
      badge: 'حزمة حملات التضامن المجانية',
      title: 'أطلق حملتك في دقيقتين،',
      highlight: 'الحزمة مجانية.',
      subtitle:
        'أنشئ حملة لشخص أو قضية، وشارك رابطها العام واجمع التبرعات بالبطاقة البنكية والموبايل موني — ثم حوّل الأموال إلى المستفيد. فعّل حزمة حملات التضامن مجاناً.',
      points: ['حزمة مجانية', 'بدون بطاقة بنكية', 'رابط جمع فوري'],
      benefitsTitle: 'مصممة لحملات التضامن',
      benefits: [
        { icon: 'ticket', title: 'رابط جمع عام', text: 'كل حملة تنشر صفحة تبرع قابلة للمشاركة (واتساب، الشبكات، رمز QR) — لا حساب مطلوب للتبرع.' },
        { icon: 'wallet', title: 'البطاقة والموبايل موني', text: 'يدفع المتبرعون بالبطاقة البنكية أو الموبايل موني، مع مبالغ مقترحة وحدود قابلة للضبط.' },
        { icon: 'users', title: 'التحويل إلى المستفيد', text: 'تابع الجمع لحظة بلحظة وحوّل الرصيد إلى المستفيد بتتبع كامل.' },
      ],
      includedTitle: 'مشمول في الحزمة',
      included: [
        'حملات غير محدودة (مسودة، نشر، إيقاف، إغلاق)',
        'صفحة تبرع عامة بأربع لغات مع قائمة الداعمين',
        'مساهمات بالبطاقة البنكية (Stripe) والموبايل موني',
        'تحويلات إلى المستفيد (موبايل موني أو تحويل بنكي)',
        'إعدادات كاملة: الوسائل، المبالغ، إخفاء الهوية، الرسائل',
      ],
      ctaPrimary: 'إنشاء حملتي',
      ctaSecondary: 'التحدث إلى مستشار',
      note: 'تفعيل مجاني عند إنشاء مساحتك — أجب «جمعية / منظمة غير حكومية» في مقابلة الإعداد. بدون التزام.',
    },
  },
  // #8465 — pack « VTC & Taxi » : la verticale mobilité (BC-35 — chauffeurs,
  // dispatch au plus proche via le core géo, réservation invitée sur la
  // marketplace publique, suivi par code) rejoint l'offre d'entrée de la
  // vitrine. Promesse limitée au livré : pas de paiement en ligne ni d'app
  // chauffeur (lots dédiés).
  vtc: {
    fr: {
      badge: 'Pack VTC & Taxi offert',
      title: 'Vos courses VTC & taxi réservables en ligne,',
      highlight: 'le pack est offert.',
      subtitle:
        'Chauffeurs, véhicules et grilles tarifaires dans votre espace ; vos clients réservent sans compte sur la marketplace publique, le dispatch part au chauffeur disponible le plus proche, et chacun suit la course avec un code. Activez votre pack VTC & Taxi gratuitement.',
      points: ['Pack offert', 'Sans carte bancaire', 'Réservation sans compte'],
      benefitsTitle: 'Pensé pour les exploitants VTC & taxi',
      benefits: [
        { icon: 'car', title: 'Dispatch au plus proche', text: 'Chaque demande est proposée en cascade au chauffeur disponible le plus proche du départ — distances calculées par le moteur géospatial de la plateforme (PostGIS).' },
        { icon: 'ticket', title: 'Réservation sans compte', text: 'Vos clients obtiennent un devis puis réservent en invité sur la marketplace publique — idempotent, jamais de doublon au retry.' },
        { icon: 'shield', title: 'Suivi et annulations tracés', text: 'Le passager suit sa course par référence + code ; chaque annulation est tracée avec acteur et motif, et notifiée.' },
      ],
      includedTitle: 'Inclus dans le pack',
      included: [
        'Chauffeurs, véhicules et grilles tarifaires (console de gestion)',
        'Dispatch en cascade avec expiration d\'offre et repli',
        'Annuaire marketplace opt-in : vos courses réservables par le grand public',
        'Devis, réservation invitée et suivi par référence + code',
        'Notifications passager & chauffeur (acceptation, expiration, clôture, annulation) en fr/en/ar/tr',
        'Console dispatch web : courses actives et chauffeurs (positions, statuts)',
      ],
      ctaPrimary: 'Créer mon espace VTC',
      ctaSecondary: 'Parler à un conseiller',
      note: 'Activation gratuite dans votre espace Leopardo, sans engagement. Le paiement de course en ligne et l\'app chauffeur arrivent dans des lots dédiés.',
    },
    en: {
      badge: 'Free VTC & Taxi Pack',
      title: 'Your VTC & taxi rides bookable online,',
      highlight: 'the pack is free.',
      subtitle:
        'Drivers, vehicles and fare profiles in your workspace; your customers book account-free on the public marketplace, dispatch goes to the nearest available driver, and everyone tracks the ride with a code. Activate your VTC & Taxi Pack for free.',
      points: ['Free pack', 'No credit card', 'Account-free booking'],
      benefitsTitle: 'Built for VTC & taxi operators',
      benefits: [
        { icon: 'car', title: 'Nearest-driver dispatch', text: 'Every request is offered in cascade to the available driver nearest to pickup — distances computed by the platform geospatial engine (PostGIS).' },
        { icon: 'ticket', title: 'Account-free booking', text: 'Your customers get a quote then book as guests on the public marketplace — idempotent, never a duplicate on retry.' },
        { icon: 'shield', title: 'Tracked rides and cancellations', text: 'The passenger tracks the ride by reference + code; every cancellation is logged with actor and reason, and notified.' },
      ],
      includedTitle: 'Included in the pack',
      included: [
        'Drivers, vehicles and fare profiles (management console)',
        'Cascading dispatch with offer expiry and fallback',
        'Opt-in marketplace directory: your rides bookable by the public',
        'Quote, guest booking and tracking by reference + code',
        'Passenger & driver notifications (acceptance, expiry, completion, cancellation) in fr/en/ar/tr',
        'Web dispatch console: active rides and drivers (positions, statuses)',
      ],
      ctaPrimary: 'Create my VTC workspace',
      ctaSecondary: 'Talk to an advisor',
      note: 'Free activation in your Leopardo workspace, no commitment. Online ride payment and the driver app ship in dedicated lots.',
    },
    tr: {
      badge: 'Ücretsiz VTC & Taksi Paketi',
      title: 'VTC & taksi yolculuklarınız online rezerve edilsin,',
      highlight: 'paket ücretsiz.',
      subtitle:
        'Sürücüler, araçlar ve tarife profilleri çalışma alanınızda; müşterileriniz herkese açık pazarda hesapsız rezervasyon yapar, atama en yakın müsait sürücüye gider ve herkes yolculuğu bir kodla takip eder. VTC & Taksi Paketinizi ücretsiz etkinleştirin.',
      points: ['Ücretsiz paket', 'Kredi kartı yok', 'Hesapsız rezervasyon'],
      benefitsTitle: 'VTC & taksi işletmeleri için tasarlandı',
      benefits: [
        { icon: 'car', title: 'En yakın sürücüye atama', text: 'Her talep, alış noktasına en yakın müsait sürücüye kademeli olarak önerilir — mesafeler platformun coğrafi motoruyla (PostGIS) hesaplanır.' },
        { icon: 'ticket', title: 'Hesapsız rezervasyon', text: 'Müşterileriniz önce fiyat teklifi alır, sonra pazarda misafir olarak rezervasyon yapar — idempotent, tekrar denemede asla çift kayıt yok.' },
        { icon: 'shield', title: 'İzlenen yolculuklar ve iptaller', text: 'Yolcu, yolculuğu referans + kod ile takip eder; her iptal, yapan kişi ve sebebiyle kayıt altına alınır ve bildirilir.' },
      ],
      includedTitle: 'Pakete dahil olanlar',
      included: [
        'Sürücüler, araçlar ve tarife profilleri (yönetim konsolu)',
        'Teklif süresi dolması ve yedeklemeyle kademeli atama',
        'Opt-in pazar dizini: yolculuklarınız halka açık rezerve edilebilir',
        'Teklif, misafir rezervasyonu ve referans + kod ile takip',
        'Yolcu & sürücü bildirimleri (kabul, süre dolması, tamamlanma, iptal) fr/en/ar/tr',
        'Web atama konsolu: aktif yolculuklar ve sürücüler (konumlar, durumlar)',
      ],
      ctaPrimary: 'VTC alanımı oluştur',
      ctaSecondary: 'Bir danışmanla konuş',
      note: 'Leopardo çalışma alanınızda ücretsiz etkinleştirme, taahhüt yok. Online yolculuk ödemesi ve sürücü uygulaması ayrı paketlerde gelir.',
    },
    ar: {
      badge: 'حزمة VTC والأجرة مجانية',
      title: 'رحلات VTC والأجرة قابلة للحجز عبر الإنترنت،',
      highlight: 'والحزمة مجانية.',
      subtitle:
        'السائقون والمركبات وأسعار التعرفة في مساحتك؛ عملاؤكم يحجزون دون حساب على السوق العامة، ويُرسَل الطلب إلى أقرب سائق متاح، ويتابع الجميع الرحلة برمز. فعّل حزمة VTC والأجرة مجانًا.',
      points: ['حزمة مجانية', 'بدون بطاقة بنكية', 'حجز دون حساب'],
      benefitsTitle: 'مصممة لمشغّلي VTC والأجرة',
      benefits: [
        { icon: 'car', title: 'إرسال لأقرب سائق', text: 'يُعرض كل طلب تدريجيًا على أقرب سائق متاح من نقطة الانطلاق — المسافات تُحسب بمحرك المنصة الجغرافي (PostGIS).' },
        { icon: 'ticket', title: 'حجز دون حساب', text: 'يحصل عملاؤكم على عرض سعر ثم يحجزون كضيوف على السوق العامة — بلا تكرار عند إعادة المحاولة.' },
        { icon: 'shield', title: 'رحلات وإلغاءات موثقة', text: 'يتابع الراكب رحلته بالمرجع + الرمز؛ كل إلغاء يسجَّل بفاعله وسببه ويُبلَّغ.' },
      ],
      includedTitle: 'مشمول في الحزمة',
      included: [
        'السائقون والمركبات وأسعار التعرفة (وحدة إدارة)',
        'إرسال تدريجي مع انتهاء مهلة العرض والتراجع',
        'دليل سوق بموافقة اختيارية: رحلاتكم قابلة للحجز من الجمهور',
        'عرض السعر والحجز كضيف والمتابعة بالمرجع + الرمز',
        'إشعارات الراكب والسائق (القبول والانتهاء والإتمام والإلغاء) باللغات fr/en/ar/tr',
        'وحدة تحكم إرسال ويب: الرحلات النشطة والسائقون (المواقع والحالات)',
      ],
      ctaPrimary: 'أنشئ مساحة VTC الخاصة بي',
      ctaSecondary: 'تحدث إلى مستشار',
      note: 'تفعيل مجاني في مساحة ليوباردو الخاصة بك، بلا التزام. الدفع الإلكتروني للرحلة وتطبيق السائق يأتيان في حزم مخصصة.',
    },
  },
  'station-service': {
    fr: {
      badge: 'Pack Station-service offert',
      title: 'Vos équipes tournantes sous contrôle,',
      highlight: 'le pack est offert.',
      subtitle:
        'Pointage par poste, planning des rotations, heures et majorations vers la paie — sans ressaisie ni paperasse. Activez votre pack Station-service gratuitement et démarrez aujourd\'hui.',
      points: ['Pack offert', 'Sans carte bancaire', 'Activé en quelques minutes'],
      benefitsTitle: 'Pensé pour les stations-service',
      benefits: [
        { icon: 'clock', title: 'Pointage par poste', text: 'Chaque employé pointe sur son poste — pompe, boutique, lavage — par QR, GPS ou borne.' },
        { icon: 'calendar', title: 'Rotations sans casse-tête', text: 'Plannings 3×8, remplacements et absences gérés en quelques clics, même multi-sites.' },
        { icon: 'wallet', title: 'Heures vers la paie', text: 'Heures de nuit, dimanches et majorations remontent automatiquement en variables de paie.' },
        { icon: 'chart', title: 'Visibilité direction', text: 'Qui est présent, où, sur quel poste — en temps réel, station par station.' },
      ],
      includedTitle: 'Inclus dans votre pack offert',
      included: [
        'Pointage mobile GPS et QR code',
        'Planning des équipes tournantes',
        'Préparation de paie avec majorations',
        'Dossiers employés et documents',
        'Application Employé et Manager',
      ],
      ctaPrimary: 'Activer mon pack offert',
      ctaSecondary: 'Parler à un conseiller',
      note: 'Activation gratuite à la création de votre espace. Aucun engagement.',
    },
    en: {
      badge: 'Free Fuel Station Pack',
      title: 'Your rotating crews under control,',
      highlight: 'the pack is free.',
      subtitle:
        'Per-post clock-ins, rotation scheduling, hours and overtime straight to payroll — no re-entry, no paperwork. Activate your Fuel Station Pack for free and start today.',
      points: ['Free pack', 'No credit card', 'Activated in minutes'],
      benefitsTitle: 'Built for fuel stations',
      benefits: [
        { icon: 'clock', title: 'Per-post clock-ins', text: 'Every employee clocks in at their post — pump, shop, wash — by QR, GPS or kiosk.' },
        { icon: 'calendar', title: 'Painless rotations', text: '3×8 schedules, replacements and leave handled in a few clicks, even across sites.' },
        { icon: 'wallet', title: 'Hours to payroll', text: 'Night hours, Sundays and overtime flow automatically into payroll variables.' },
        { icon: 'chart', title: 'Management visibility', text: 'Who is present, where, on which post — in real time, station by station.' },
      ],
      includedTitle: 'Included in your free pack',
      included: [
        'GPS and QR mobile clock-ins',
        'Rotating crew scheduling',
        'Payroll preparation with overtime',
        'Employee records and documents',
        'Employee and Manager apps',
      ],
      ctaPrimary: 'Activate my free pack',
      ctaSecondary: 'Talk to an advisor',
      note: 'Free activation when you create your workspace. No commitment.',
    },
    tr: {
      badge: 'Ücretsiz Akaryakıt İstasyonu Paketi',
      title: 'Vardiyalı ekipleriniz kontrol altında,',
      highlight: 'paket ücretsiz.',
      subtitle:
        'Posta bazlı yoklama, vardiya planlaması, saatler ve fazla mesai doğrudan bordroya — yeniden giriş yok, evrak yok. Akaryakıt İstasyonu Paketinizi ücretsiz etkinleştirin ve bugün başlayın.',
      points: ['Ücretsiz paket', 'Kredi kartı yok', 'Dakikalar içinde aktif'],
      benefitsTitle: 'Akaryakıt istasyonları için tasarlandı',
      benefits: [
        { icon: 'clock', title: 'Posta bazlı yoklama', text: 'Her çalışan postasında yoklama yapar — pompa, market, yıkama — QR, GPS veya kiosk ile.' },
        { icon: 'calendar', title: 'Zahmetsiz vardiyalar', text: '3×8 planlar, yerine geçmeler ve izinler birkaç tıkla, çok şubeli bile.' },
        { icon: 'wallet', title: 'Saatler bordroya', text: 'Gece saatleri, pazar günleri ve fazla mesailer otomatik olarak bordro değişkenlerine akar.' },
        { icon: 'chart', title: 'Yönetim görünürlüğü', text: 'Kim nerede, hangi postada — gerçek zamanlı, istasyon istasyon.' },
      ],
      includedTitle: 'Ücretsiz paketinize dahil',
      included: [
        'GPS ve QR mobil yoklama',
        'Vardiyalı ekip planlaması',
        'Fazla mesaili bordro hazırlığı',
        'Çalışan dosyaları ve belgeler',
        'Çalışan ve Yönetici uygulamaları',
      ],
      ctaPrimary: 'Ücretsiz paketimi etkinleştir',
      ctaSecondary: 'Bir danışmanla konuş',
      note: 'Çalışma alanınızı oluştururken ücretsiz etkinleşir. Taahhüt yok.',
    },
    ar: {
      badge: 'حزمة محطة الوقود مجانية',
      title: 'فرق المناوبة تحت السيطرة،',
      highlight: 'والحزمة مجانية.',
      subtitle:
        'تسجيل حضور حسب الموضع، جدولة المناوبات، الساعات والإضافي مباشرة إلى الرواتب — بلا إعادة إدخال ولا أوراق. فعّل حزمة محطة الوقود مجانًا وابدأ اليوم.',
      points: ['حزمة مجانية', 'بدون بطاقة بنكية', 'تُفعَّل في دقائق'],
      benefitsTitle: 'مصممة لمحطات الوقود',
      benefits: [
        { icon: 'clock', title: 'حضور حسب الموضع', text: 'كل موظف يسجل حضوره في موضعه — المضخة، المتجر، الغسيل — عبر QR أو GPS أو الجهاز.' },
        { icon: 'calendar', title: 'مناوبات بلا عناء', text: 'جداول 3×8 والبدلاء والإجازات تُدار في بضع نقرات، حتى عبر عدة مواقع.' },
        { icon: 'wallet', title: 'الساعات إلى الرواتب', text: 'ساعات الليل والأحد والإضافي تنتقل تلقائيًا إلى متغيرات الرواتب.' },
        { icon: 'chart', title: 'رؤية للإدارة', text: 'من حاضر، أين، على أي موضع — في الوقت الفعلي، محطة محطة.' },
      ],
      includedTitle: 'مضمن في حزمتك المجانية',
      included: [
        'تسجيل حضور بالجوال عبر GPS وQR',
        'جدولة فرق المناوبة',
        'إعداد الرواتب مع الإضافي',
        'ملفات الموظفين والمستندات',
        'تطبيقا الموظف والمدير',
      ],
      ctaPrimary: 'فعّل حزمتي المجانية',
      ctaSecondary: 'تحدث إلى مستشار',
      note: 'التفعيل مجاني عند إنشاء مساحتك. بلا التزام.',
    },
  },
  ecole: {
    fr: {
      badge: 'Pack École offert',
      title: 'Le personnel scolaire piloté sereinement,',
      highlight: 'le pack est offert.',
      subtitle:
        'Présence du personnel, absences et remplacements, paie alignée sur le calendrier scolaire — enseignants comme administratifs. Activez votre pack École gratuitement et démarrez aujourd\'hui.',
      points: ['Pack offert', 'Sans carte bancaire', 'Activé en quelques minutes'],
      benefitsTitle: 'Pensé pour les écoles et la formation',
      benefits: [
        { icon: 'users', title: 'Enseignants et administratifs', text: 'Un seul référentiel pour tout le personnel — contrats, documents, affectations.' },
        { icon: 'calendar', title: 'Calendrier scolaire', text: 'Congés, absences et remplacements alignés sur les périodes de l\'année scolaire.' },
        { icon: 'clock', title: 'Présence sans paperasse', text: 'Pointage du personnel par QR, GPS ou borne — fini le registre papier.' },
        { icon: 'wallet', title: 'Paie sans ressaisie', text: 'Heures, absences et retenues remontent directement en préparation de paie.' },
      ],
      includedTitle: 'Inclus dans votre pack offert',
      included: [
        'Pointage du personnel (QR, GPS, borne)',
        'Gestion des absences et remplacements',
        'Préparation de paie multi-profils',
        'Dossiers du personnel et documents',
        'Application Employé et Manager',
      ],
      ctaPrimary: 'Activer mon pack offert',
      ctaSecondary: 'Parler à un conseiller',
      note: 'Activation gratuite à la création de votre espace. Aucun engagement.',
    },
    en: {
      badge: 'Free School Pack',
      title: 'School staff management made serene,',
      highlight: 'the pack is free.',
      subtitle:
        'Staff attendance, leave and replacements, payroll aligned with the school calendar — teachers and admin alike. Activate your School Pack for free and start today.',
      points: ['Free pack', 'No credit card', 'Activated in minutes'],
      benefitsTitle: 'Built for schools & training',
      benefits: [
        { icon: 'users', title: 'Teachers and admin staff', text: 'One single record for all staff — contracts, documents, assignments.' },
        { icon: 'calendar', title: 'School calendar', text: 'Leave, absences and replacements aligned with the school year periods.' },
        { icon: 'clock', title: 'Paperless attendance', text: 'Staff clock-ins by QR, GPS or kiosk — goodbye paper register.' },
        { icon: 'wallet', title: 'Payroll without re-entry', text: 'Hours, absences and deductions flow straight into payroll preparation.' },
      ],
      includedTitle: 'Included in your free pack',
      included: [
        'Staff clock-ins (QR, GPS, kiosk)',
        'Leave and replacement management',
        'Multi-profile payroll preparation',
        'Staff records and documents',
        'Employee and Manager apps',
      ],
      ctaPrimary: 'Activate my free pack',
      ctaSecondary: 'Talk to an advisor',
      note: 'Free activation when you create your workspace. No commitment.',
    },
    tr: {
      badge: 'Ücretsiz Okul Paketi',
      title: 'Okul personeli yönetimi huzurla,',
      highlight: 'paket ücretsiz.',
      subtitle:
        'Personel yoklaması, izin ve yerine geçmeler, okul takvimiyle uyumlu bordro — öğretmenler ve idari kadro birlikte. Okul Paketinizi ücretsiz etkinleştirin ve bugün başlayın.',
      points: ['Ücretsiz paket', 'Kredi kartı yok', 'Dakikalar içinde aktif'],
      benefitsTitle: 'Okullar ve eğitim için tasarlandı',
      benefits: [
        { icon: 'users', title: 'Öğretmenler ve idari kadro', text: 'Tüm personel için tek kayıt — sözleşmeler, belgeler, görevlendirmeler.' },
        { icon: 'calendar', title: 'Okul takvimi', text: 'İzinler, devamsızlıklar ve yerine geçmeler okul yılı dönemleriyle uyumlu.' },
        { icon: 'clock', title: 'Evraksız yoklama', text: 'Personel QR, GPS veya kiosk ile yoklama yapar — kağıt deftere son.' },
        { icon: 'wallet', title: 'Yeniden girişsiz bordro', text: 'Saatler, devamsızlıklar ve kesintiler doğrudan bordro hazırlığına akar.' },
      ],
      includedTitle: 'Ücretsiz paketinize dahil',
      included: [
        'Personel yoklaması (QR, GPS, kiosk)',
        'İzin ve yerine geçme yönetimi',
        'Çok profilli bordro hazırlığı',
        'Personel dosyaları ve belgeler',
        'Çalışan ve Yönetici uygulamaları',
      ],
      ctaPrimary: 'Ücretsiz paketimi etkinleştir',
      ctaSecondary: 'Bir danışmanla konuş',
      note: 'Çalışma alanınızı oluştururken ücretsiz etkinleşir. Taahhüt yok.',
    },
    ar: {
      badge: 'حزمة المدرسة مجانية',
      title: 'إدارة طاقم المدرسة باطمئنان،',
      highlight: 'والحزمة مجانية.',
      subtitle:
        'حضور الموظفين والغيابات والبدلاء، ورواتب متوافقة مع التقويم المدرسي — للمعلمين والإداريين معًا. فعّل حزمة المدرسة مجانًا وابدأ اليوم.',
      points: ['حزمة مجانية', 'بدون بطاقة بنكية', 'تُفعَّل في دقائق'],
      benefitsTitle: 'مصممة للمدارس والتكوين',
      benefits: [
        { icon: 'users', title: 'المعلمون والإداريون', text: 'سجل واحد لكل الطاقم — العقود والمستندات والتكليفات.' },
        { icon: 'calendar', title: 'التقويم المدرسي', text: 'الإجازات والغيابات والبدلاء بما يناسب فترات السنة الدراسية.' },
        { icon: 'clock', title: 'حضور بلا أوراق', text: 'يسجل الطاقم حضوره عبر QR أو GPS أو الجهاز — وداعًا للسجل الورقي.' },
        { icon: 'wallet', title: 'رواتب بلا إعادة إدخال', text: 'الساعات والغيابات والاقتطاعات تنتقل مباشرة إلى إعداد الرواتب.' },
      ],
      includedTitle: 'مضمن في حزمتك المجانية',
      included: [
        'تسجيل حضور الطاقم (QR، GPS، جهاز)',
        'إدارة الغيابات والبدلاء',
        'إعداد رواتب متعدد الفئات',
        'ملفات الطاقم والمستندات',
        'تطبيقا الموظف والمدير',
      ],
      ctaPrimary: 'فعّل حزمتي المجانية',
      ctaSecondary: 'تحدث إلى مستشار',
      note: 'التفعيل مجاني عند إنشاء مساحتك. بلا التزام.',
    },
  },
  'agence-de-voyage': {
    fr: {
      badge: 'Pack Agence de voyage offert',
      title: 'Votre agence au niveau des grandes,',
      highlight: 'le pack est offert.',
      subtitle:
        'Dossiers clients, billetterie et paie des équipes au même endroit — comptoir comme terrain. Activez votre pack Agence de voyage gratuitement et démarrez aujourd\'hui.',
      points: ['Pack offert', 'Sans carte bancaire', 'Activé en quelques minutes'],
      benefitsTitle: 'Pensé pour les agences de voyage',
      benefits: [
        { icon: 'file', title: 'Dossiers clients centralisés', text: 'Réservations, documents et historique client réunis dans un CRM intégré.' },
        { icon: 'ticket', title: 'Billetterie & check-in', text: 'L\'app Travel Agent couvre la billetterie, le check-in et le point de vente au comptoir.' },
        { icon: 'wallet', title: 'Règlements des ventes suivis', text: 'Paiements, remboursements et règlements de la période suivis dans l’agence, paie des équipes sans double saisie.' },
        { icon: 'clock', title: 'Équipes pointées', text: 'Comptoir, accueil groupes, tournées — le pointage suit vos horaires réels.' },
      ],
      includedTitle: 'Inclus dans votre pack offert',
      included: [
        'CRM dossiers clients',
        'App Travel Agent (billetterie, point de vente)',
        'Pointage mobile et planning',
        'Préparation de paie des équipes',
        'Application Employé et Manager',
      ],
      ctaPrimary: 'Activer mon pack offert',
      ctaSecondary: 'Parler à un conseiller',
      note: 'Activation gratuite à la création de votre espace. Aucun engagement.',
    },
    en: {
      badge: 'Free Travel Agency Pack',
      title: 'Your agency at the level of the big ones,',
      highlight: 'the pack is free.',
      subtitle:
        'Client files, ticketing and team payroll in one place — front desk and field alike. Activate your Travel Agency Pack for free and start today.',
      points: ['Free pack', 'No credit card', 'Activated in minutes'],
      benefitsTitle: 'Built for travel agencies',
      benefits: [
        { icon: 'file', title: 'Centralized client files', text: 'Bookings, documents and client history brought together in an integrated CRM.' },
        { icon: 'ticket', title: 'Ticketing & check-in', text: 'The Travel Agent app covers ticketing, check-in and point of sale at the counter.' },
        { icon: 'wallet', title: 'Sales settlements tracked', text: 'Period payments, refunds and settlements tracked in the agency, team payroll without double entry.' },
        { icon: 'clock', title: 'Teams clocked in', text: 'Counter, group reception, tours — attendance follows your real schedules.' },
      ],
      includedTitle: 'Included in your free pack',
      included: [
        'Client files CRM',
        'Travel Agent app (ticketing, point of sale)',
        'Mobile clock-ins and scheduling',
        'Team payroll preparation',
        'Employee and Manager apps',
      ],
      ctaPrimary: 'Activate my free pack',
      ctaSecondary: 'Talk to an advisor',
      note: 'Free activation when you create your workspace. No commitment.',
    },
    tr: {
      badge: 'Ücretsiz Seyahat Acentesi Paketi',
      title: 'Acenteniz büyükler seviyesinde,',
      highlight: 'paket ücretsiz.',
      subtitle:
        'Müşteri dosyaları, biletleme ve ekip bordrosu tek yerde — gişe ve saha birlikte. Seyahat Acentesi Paketinizi ücretsiz etkinleştirin ve bugün başlayın.',
      points: ['Ücretsiz paket', 'Kredi kartı yok', 'Dakikalar içinde aktif'],
      benefitsTitle: 'Seyahat acenteleri için tasarlandı',
      benefits: [
        { icon: 'file', title: 'Merkezi müşteri dosyaları', text: 'Rezervasyonlar, belgeler ve müşteri geçmişi entegre CRM\'de bir arada.' },
        { icon: 'ticket', title: 'Biletleme & check-in', text: 'Travel Agent uygulaması biletleme, check-in ve gişe satışını kapsar.' },
        { icon: 'wallet', title: 'Kontrollü satış mahsupları', text: 'Dönem bazlı ödemeler, iadeler ve mahsuplar acentede takip edilir; çift giriş olmadan ekip bordrosu.' },
        { icon: 'clock', title: 'Ekip yoklaması', text: 'Gişe, grup karşılama, turlar — yoklama gerçek saatlerinizi izler.' },
      ],
      includedTitle: 'Ücretsiz paketinize dahil',
      included: [
        'Müşteri dosyaları CRM\'i',
        'Travel Agent uygulaması (biletleme, satış noktası)',
        'Mobil yoklama ve planlama',
        'Ekip bordrosu hazırlığı',
        'Çalışan ve Yönetici uygulamaları',
      ],
      ctaPrimary: 'Ücretsiz paketimi etkinleştir',
      ctaSecondary: 'Bir danışmanla konuş',
      note: 'Çalışma alanınızı oluştururken ücretsiz etkinleşir. Taahhüt yok.',
    },
    ar: {
      badge: 'حزمة وكالة السفر مجانية',
      title: 'وكالتك بمستوى الكبار،',
      highlight: 'والحزمة مجانية.',
      subtitle:
        'ملفات العملاء وإصدار التذاكر ورواتب الفريق في مكان واحد — المكتب والميدان معًا. فعّل حزمة وكالة السفر مجانًا وابدأ اليوم.',
      points: ['حزمة مجانية', 'بدون بطاقة بنكية', 'تُفعَّل في دقائق'],
      benefitsTitle: 'مصممة لوكالات السفر',
      benefits: [
        { icon: 'file', title: 'ملفات عملاء مركزية', text: 'الحجوزات والمستندات وسجل العميل مجتمعة في نظام CRM مدمج.' },
        { icon: 'ticket', title: 'التذاكر وتسجيل الوصول', text: 'يغطي تطبيق Travel Agent إصدار التذاكر وتسجيل الوصول ونقطة البيع.' },
        { icon: 'wallet', title: 'تسويات المبيعات تحت السيطرة', text: 'تتبع المدفوعات والمرتجعات وتسويات الفترة داخل الوكالة، ورواتب الفريق دون إدخال مزدوج.' },
        { icon: 'clock', title: 'حضور الفريق', text: 'المكتب واستقبال المجموعات والجولات — الحضور يتبع جداولك الفعلية.' },
      ],
      includedTitle: 'مضمن في حزمتك المجانية',
      included: [
        'نظام CRM لملفات العملاء',
        'تطبيق Travel Agent (التذاكر، نقطة البيع)',
        'تسجيل الحضور بالجوال والجدولة',
        'إعداد رواتب الفريق',
        'تطبيقا الموظف والمدير',
      ],
      ctaPrimary: 'فعّل حزمتي المجانية',
      ctaSecondary: 'تحدث إلى مستشار',
      note: 'التفعيل مجاني عند إنشاء مساحتك. بلا التزام.',
    },
  },
};

export function isPackVertical(value: string): value is PackVerticalSlug {
  return (PACK_VERTICALS as string[]).includes(value);
}


/** Copy du hub /packs — la vitrine de l'offre d'entrée grand public :
 *  un pack métier OFFERT par corps de métier. */
export type PacksHubCopy = {
  badge: string;
  title: string;
  highlight: string;
  subtitle: string;
  points: string[];
  cards: Array<{ slug: 'restaurant' | PackVerticalSlug; name: string; line: string; linkLabel: string }>;
  freeBadge: string;
  note: string;
};

export const PACKS_HUB_COPY: Record<AppLocale, PacksHubCopy> = {
  fr: {
    badge: 'Packs métiers offerts',
    title: 'Un pack offert',
    highlight: 'pour votre métier.',
    subtitle:
      'Restaurateur, gérant de station-service, directeur d’école, agent de voyage : votre pack Leopardo réunit les apps, les parcours et les réglages de votre secteur — activé gratuitement dans votre espace.',
    points: ['Pack offert', 'Sans carte bancaire', 'Activé en quelques minutes'],
    cards: [
      { slug: 'restaurant', name: 'Restaurants', line: 'Caisse, cuisine, réservations, stock — composé en 3 questions.', linkLabel: 'Composer mon pack' },
      { slug: 'station-service', name: 'Stations-service', line: 'Rotations 3×8, pointage par poste, majorations vers la paie.', linkLabel: 'Découvrir le pack' },
      { slug: 'ecole', name: 'Écoles & formation', line: 'Présence du personnel, remplacements, paie calendrier scolaire.', linkLabel: 'Découvrir le pack' },
      { slug: 'agence-de-voyage', name: 'Agences de voyage', line: 'Dossiers clients, billetterie, paiements et paie des équipes.', linkLabel: 'Découvrir le pack' },
      { slug: 'vtc', name: 'VTC & Taxi', line: 'Chauffeurs, dispatch au plus proche, réservation publique sans compte.', linkLabel: 'Découvrir le pack' },
      { slug: 'trade', name: 'Trade — commerce international', line: 'Sourcing multi-fournisseurs, offres comparées, coût rendu, douane — import et export.', linkLabel: 'Découvrir le pack' },
    ],
    freeBadge: 'Offert',
    note: 'Chaque pack s’active gratuitement à la création de votre espace Leopardo. Aucun engagement.',
  },
  en: {
    badge: 'Free business packs',
    title: 'A free pack',
    highlight: 'for your trade.',
    subtitle:
      'Restaurant owner, fuel station manager, school director, travel agent: your Leopardo pack bundles the apps, flows and defaults of your industry — activated free in your workspace.',
    points: ['Free pack', 'No credit card', 'Activated in minutes'],
    cards: [
      { slug: 'restaurant', name: 'Restaurants', line: 'POS, kitchen, reservations, stock — built in 3 questions.', linkLabel: 'Build my pack' },
      { slug: 'station-service', name: 'Fuel stations', line: '3×8 rotations, per-post clock-ins, overtime to payroll.', linkLabel: 'See the pack' },
      { slug: 'ecole', name: 'Schools & training', line: 'Staff attendance, replacements, school-calendar payroll.', linkLabel: 'See the pack' },
      { slug: 'agence-de-voyage', name: 'Travel agencies', line: 'Client files, ticketing, payments, team payroll.', linkLabel: 'See the pack' },
      { slug: 'vtc', name: 'VTC & Taxi', line: 'Drivers, nearest-first dispatch, account-free public booking.', linkLabel: 'See the pack' },
      { slug: 'trade', name: 'Trade — international commerce', line: 'Multi-supplier sourcing, compared offers, landed cost, customs — import and export.', linkLabel: 'See the pack' },
    ],
    freeBadge: 'Free',
    note: 'Every pack is activated free when you create your Leopardo workspace. No commitment.',
  },
  tr: {
    badge: 'Ücretsiz iş paketleri',
    title: 'Sektörünüz için',
    highlight: 'ücretsiz paket.',
    subtitle:
      'Restorancı, istasyon müdürü, okul müdürü, seyahat acentesi: Leopardo paketiniz sektörünüzün uygulamalarını, akışlarını ve ayarlarını bir araya getirir — çalışma alanınızda ücretsiz etkinleşir.',
    points: ['Ücretsiz paket', 'Kredi kartı yok', 'Dakikalar içinde aktif'],
    cards: [
      { slug: 'restaurant', name: 'Restoranlar', line: 'Kasa, mutfak, rezervasyon, stok — 3 soruda oluşturun.', linkLabel: 'Paketimi oluştur' },
      { slug: 'station-service', name: 'Akaryakıt istasyonları', line: '3×8 vardiyalar, posta bazlı yoklama, fazla mesai bordroya.', linkLabel: 'Paketi gör' },
      { slug: 'ecole', name: 'Okullar & eğitim', line: 'Personel yoklaması, yerine geçmeler, okul takvimi bordrosu.', linkLabel: 'Paketi gör' },
      { slug: 'agence-de-voyage', name: 'Seyahat acenteleri', line: 'Müşteri dosyaları, biletleme, ödemeler, ekip bordrosu.', linkLabel: 'Paketi gör' },
      { slug: 'vtc', name: 'VTC & Taksi', line: 'Sürücüler, en yakına atama, hesapsız herkese açık rezervasyon.', linkLabel: 'Paketi gör' },
      { slug: 'trade', name: 'Trade — uluslararası ticaret', line: 'Çok tedarikçili kaynak bulma, karşılaştırılmış teklifler, teslim maliyeti, gümrük — ithalat ve ihracat.', linkLabel: 'Paketi gör' },
    ],
    freeBadge: 'Ücretsiz',
    note: 'Her paket, Leopardo çalışma alanınızı oluştururken ücretsiz etkinleşir. Taahhüt yok.',
  },
  ar: {
    badge: 'حزم الأعمال مجانية',
    title: 'حزمة مجانية',
    highlight: 'لمهنتك.',
    subtitle:
      'صاحب مطعم، مدير محطة وقود، مدير مدرسة، وكيل سفر: حزمتك من ليوباردو تجمع تطبيقات قطاعك ومساراته وإعداداته — وتُفعَّل مجانًا في مساحتك.',
    points: ['حزمة مجانية', 'بدون بطاقة بنكية', 'تُفعَّل في دقائق'],
    cards: [
      { slug: 'restaurant', name: 'المطاعم', line: 'نقاط البيع والمطبخ والحجوزات والمخزون — أنشئها في 3 أسئلة.', linkLabel: 'أنشئ حزمتي' },
      { slug: 'station-service', name: 'محطات الوقود', line: 'مناوبات 3×8، حضور حسب الموضع، الإضافي إلى الرواتب.', linkLabel: 'شاهد الحزمة' },
      { slug: 'ecole', name: 'المدارس والتكوين', line: 'حضور الطاقم والبدلاء ورواتب التقويم المدرسي.', linkLabel: 'شاهد الحزمة' },
      { slug: 'agence-de-voyage', name: 'وكالات السفر', line: 'ملفات العملاء والتذاكر والمدفوعات ورواتب الفريق.', linkLabel: 'شاهد الحزمة' },
      { slug: 'vtc', name: 'VTC والأجرة', line: 'السائقون، الإرسال للأقرب، الحجز العام دون حساب.', linkLabel: 'شاهد الحزمة' },
      { slug: 'trade', name: 'Trade — التجارة الدولية', line: 'توريد متعدد الموردين، عروض مقارنة، تكلفة الوصول، الجمارك — الاستيراد والتصدير.', linkLabel: 'شاهد الحزمة' },
    ],
    freeBadge: 'مجانًا',
    note: 'تُفعَّل كل حزمة مجانًا عند إنشاء مساحة ليوباردو الخاصة بك. بلا التزام.',
  },
};

export function packsHubCardHref(slug: PacksHubCopy['cards'][number]['slug']): string {
  return slug === 'restaurant' ? '/restaurateur' : `/packs/${slug}`;
}

/** #8462 — noms courts localisés des verticales SANS carte hub
 *  (« cagnottes » n'est pas une carte du hub /packs). */
const PACK_EXTRA_SHORT_NAMES: Record<string, Record<AppLocale, string>> = {
  cagnottes: {
    fr: 'Cagnottes solidaires',
    en: 'Solidarity fundraisers',
    tr: 'Dayanışma kampanyaları',
    ar: 'حملات التضامن',
  },
  trade: {
    fr: 'Trade — commerce international',
    en: 'Trade — international commerce',
    tr: 'Trade — uluslararası ticaret',
    ar: 'Trade — التجارة الدولية',
  },
};

/** #8462 — nom court localisé d'un pack (bandeau « pack pré-sélectionné » du
 *  formulaire d'inscription). Carte hub d'abord, repli dédié sinon, `null`
 *  si le slug est inconnu (fail-closed, comme `pack-signup`). */
export function packDisplayName(slug: string, locale: AppLocale): string | null {
  const copy = PACKS_HUB_COPY[locale] ?? PACKS_HUB_COPY.fr;
  const card = copy.cards.find((c) => c.slug === slug);
  if (card) {
    return card.name;
  }
  const extra = PACK_EXTRA_SHORT_NAMES[slug];
  return extra ? extra[locale] ?? extra.fr : null;
}

// ── Bannière « Pack offert » de la page tarifs ─────────────────────────────
// Remplace l'ancienne colonne « Self-host » (#8068) : la page tarifs pousse
// désormais l'entrée grand public du Business OS — un pack métier OFFERT —
// au même niveau que les plans cloud payants.
export type PricingPackBannerCopy = {
  badge: string;
  title: string;
  subtitle: string;
  bullets: [string, string, string];
  ctaPrimary: string;
  ctaSecondary: string;
};

export const PRICING_PACK_BANNER: Record<AppLocale, PricingPackBannerCopy> = {
  fr: {
    badge: 'Pack métier offert',
    title: 'Votre métier a son pack Leopardo — il est offert.',
    subtitle:
      'Restauration, station-service, école, agence de voyage… Les apps, les parcours et les réglages de votre secteur, activés gratuitement dans votre espace Leopardo.',
    bullets: ['Activé à l’inscription', 'Sans carte bancaire', 'Enrichi à chaque version'],
    ctaPrimary: 'Découvrir les packs offerts',
    ctaSecondary: 'Composer le pack Restaurant',
  },
  en: {
    badge: 'Free trade pack',
    title: 'Your trade has its Leopardo pack — and it is free.',
    subtitle:
      'Restaurant, fuel station, school, travel agency… Your industry’s apps, flows and defaults, activated free in your Leopardo workspace.',
    bullets: ['Activated on signup', 'No credit card', 'Improved every release'],
    ctaPrimary: 'Explore the free packs',
    ctaSecondary: 'Build the Restaurant pack',
  },
  tr: {
    badge: 'Ücretsiz meslek paketi',
    title: 'Mesleğinizin Leopardo paketi var — üstelik ücretsiz.',
    subtitle:
      'Restoran, akaryakıt istasyonu, okul, seyahat acentesi… Sektörünüzün uygulamaları, akışları ve ayarları, Leopardo çalışma alanınızda ücretsiz etkinleşir.',
    bullets: ['Kayıtta etkinleşir', 'Kredi kartı yok', 'Her sürümde zenginleşir'],
    ctaPrimary: 'Ücretsiz paketleri keşfedin',
    ctaSecondary: 'Restoran paketini oluşturun',
  },
  ar: {
    badge: 'حزمة مهنية مجانية',
    title: 'لمهنتك حزمة Leopardo — وهي مجانية.',
    subtitle:
      'مطعم، محطة وقود، مدرسة، وكالة سفر… تطبيقات قطاعك ومساراته وإعداداته تُفعَّل مجانًا في مساحة ليوباردو الخاصة بك.',
    bullets: ['تُفعَّل عند التسجيل', 'بدون بطاقة بنكية', 'تُثريها كل نسخة'],
    ctaPrimary: 'اكتشف الحزم المجانية',
    ctaSecondary: 'أنشئ حزمة المطعم',
  },
};
