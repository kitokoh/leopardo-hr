/**
 * Copy ×4 (fr/en/tr/ar) du wizard « Je suis restaurateur » (vitrine).
 *
 * Vit dans `data/` (chemin exempté par la garde PA2-I18N-014) : c'est le
 * catalogue inline de l'UI du wizard, même mécanique que les pages vitrine
 * existantes (download, landing…). Le composant n'embarque aucun littéral.
 *
 * @see docs/architecture/RESTAURANT_SOLUTION_SURVEY.md
 */

import type { VitrineLocale } from '../lib/solution-survey';

export type WizardCopy = {
  title: string;
  subtitle: string;
  start: string;
  next: string;
  back: string;
  loading: string;
  questionsTitle: string;
  suggestionsTitle: string;
  suggestionsSubtitle: string;
  keep: string;
  uncheckNote: string;
  downloadTitle: string;
  downloadSubtitle: string;
  qrHint: string;
  edgeTitle: string;
  edgeCmdHint: string;
  guideLabel: string;
  includedLabel: string;
  restart: string;
  errorRetry: string;
};

export const WIZARD_COPY: Record<VitrineLocale, WizardCopy> = {


  fr: {
    title: 'Je suis restaurateur',
    subtitle: 'Répondez à 3 questions : on compose votre pack Leopardo sur mesure — il est offert. Vous cochez, vous téléchargez.',
    start: 'Commencer',
    next: 'Voir mon pack',
    back: 'Retour',
    loading: 'Calcul de votre pack…',
    questionsTitle: 'Parlez-nous de votre restaurant',
    suggestionsTitle: 'Votre pack offert est prêt',
    suggestionsSubtitle: 'Pré-coché selon vos réponses et inclus gratuitement. Décochez ce dont vous n\'avez pas besoin.',
    keep: 'Continuer',
    uncheckNote: 'Ces éléments s\'activeront gratuitement dans votre espace Leopardo à la création du compte.',
    downloadTitle: 'Téléchargez votre pack offert',
    downloadSubtitle: 'Scannez le QR pour installer les apps, ou suivez les liens ci-dessous.',
    qrHint: 'Scannez pour installer',
    edgeTitle: 'Nœud Edge local (hors ligne)',
    edgeCmdHint: 'Installez le nœud local sur un mini-PC du restaurant :',
    guideLabel: 'Guide de démarrage',
    includedLabel: 'Inclus gratuitement dans votre espace',
    restart: 'Recommencer',
    errorRetry: 'Impossible de calculer le pack. Réessayez.'
  },
  en: {
    title: 'I am a restaurant owner',
    subtitle: 'Answer 3 questions: we build the Leopardo pack for your restaurant — it is free. Tick, download.',
    start: 'Start',
    next: 'See my pack',
    back: 'Back',
    loading: 'Building your pack…',
    questionsTitle: 'Tell us about your restaurant',
    suggestionsTitle: 'Your free pack is ready',
    suggestionsSubtitle: 'Pre-ticked from your answers and included free. Untick what you don\'t need.',
    keep: 'Continue',
    uncheckNote: 'Selected items will be enabled free of charge in your Leopardo workspace on signup.',
    downloadTitle: 'Download your free pack',
    downloadSubtitle: 'Scan the QR code to install the apps, or use the links below.',
    qrHint: 'Scan to install',
    edgeTitle: 'Local Edge node (offline)',
    edgeCmdHint: 'Install the local node on a mini-PC at the restaurant:',
    guideLabel: 'Getting started guide',
    includedLabel: 'Included free in your workspace',
    restart: 'Start over',
    errorRetry: 'Could not build your pack. Try again.'
  },
  tr: { title: 'Ben bir restoran sahibiyim', subtitle: '3 soruya yanıt verin: restoranınıza uygun Leopardo paketini ücretsiz oluşturalım. İşaretleyin, indirin.', start: 'Başla', next: 'Paketimi gör', back: 'Geri', loading: 'Paketiniz hesaplanıyor…', questionsTitle: 'Restoranınızdan bahsedin', suggestionsTitle: 'Ücretsiz paketiniz hazır', suggestionsSubtitle: 'Yanıtlarınıza göre önceden işaretlendi — ücretsiz dahil.' , keep: 'Devam', uncheckNote: 'Seçilen öğeler hesap oluşturulduğunda ücretsiz etkinleşir.', downloadTitle: 'Ücretsiz paketinizi indirin', downloadSubtitle: 'Uygulamaları kurmak için QR kodu okutun.', qrHint: 'Kurulum için okutun', edgeTitle: 'Yerel Edge düğümü (çevrimdışı)', edgeCmdHint: 'Yerel düğümü restorandaki bir mini-PC\'ye kurun:', guideLabel: 'Başlangıç rehberi', includedLabel: 'Çalışma alanınıza ücretsiz dahil',
    errorRetry: 'Paket hesaplanamadı. Tekrar deneyin.', restart: 'Baştan başla' },
  ar: { title: 'أنا صاحب مطعم', subtitle: 'أجب عن 3 أسئلة وسننشئ لك حزمة Leopardo المناسبة لمطعمك — مجانًا. حدد ما تحتاجه وحمّله.', start: 'ابدأ', next: 'عرض حزمتي', back: 'رجوع', loading: 'جارٍ حساب الحزمة…', questionsTitle: 'حدثنا عن مطعمك', suggestionsTitle: 'حزمتك المجانية جاهزة', suggestionsSubtitle: 'محددة مسبقًا حسب إجاباتك — ومضمنة مجانًا.', keep: 'متابعة', uncheckNote: 'سيتم تفعيل العناصر المحددة مجانًا عند إنشاء الحساب.', downloadTitle: 'حمّل حزمتك المجانية', downloadSubtitle: 'امسح رمز QR لتثبيت التطبيقات.', qrHint: 'امسح للتثبيت', edgeTitle: 'عقدة Edge المحلية (بدون اتصال)', edgeCmdHint: 'ثبّت العقدة المحلية على جهاز صغير في المطعم:', guideLabel: 'دليل البدء', includedLabel: 'مضمن مجانًا في مساحتك',
    errorRetry: 'تعذر حساب الحزمة. حاولوا مرة أخرى.', restart: 'إعادة البدء' },
};

/** Copy du héro de la page /restaurateur — le pack métier est OFFERT : c'est
 *  la promesse d'entrée pour les corps de métier (restaurants aujourd'hui,
 *  autres verticales ensuite). Vit ici (data/ exempté PA2-I18N-014). */
export type RestaurantHeroCopy = {
  badge: string;
  title: string;
  highlight: string;
  subtitle: string;
  points: string[];
};

export const RESTAURANT_HERO_COPY: Record<VitrineLocale, RestaurantHeroCopy> = {
  fr: {
    badge: 'Pack Restaurant offert',
    title: 'Votre restaurant piloté comme un grand,',
    highlight: 'sans débourser un centime.',
    subtitle:
      'Caisse, cuisine, réservations, stock, livraison — plus le pointage et la paie de vos équipes. Le pack Restaurant est offert : composez-le en 3 questions et commencez aujourd\'hui.',
    points: ['Pack offert', 'Sans carte bancaire', 'Activé en quelques minutes'],
  },
  en: {
    badge: 'Free Restaurant Pack',
    title: 'Run your restaurant like a chain,',
    highlight: 'without spending a penny.',
    subtitle:
      'POS, kitchen, reservations, stock, delivery — plus attendance and payroll for your teams. The Restaurant Pack is free: build it in 3 questions and start today.',
    points: ['Free pack', 'No credit card', 'Activated in minutes'],
  },
  tr: {
    badge: 'Ücretsiz Restoran Paketi',
    title: 'Restoranınızı bir zincir gibi yönetin,',
    highlight: 'tek kuruş ödemeden.',
    subtitle:
      'Kasa, mutfak, rezervasyon, stok, teslimat — artı ekipleriniz için yoklama ve bordro. Restoran Paketi ücretsizdir: 3 soruda oluşturun ve bugün başlayın.',
    points: ['Ücretsiz paket', 'Kredi kartı yok', 'Dakikalar içinde aktif'],
  },
  ar: {
    badge: 'حزمة المطعم مجانية',
    title: 'أدر مطعمك كأنه سلسلة مطاعم،',
    highlight: 'دون أن تدفع شيئًا.',
    subtitle:
      'نقاط البيع والمطبخ والحجوزات والمخزون والتوصيل — إضافة إلى الحضور والرواتب لفرقك. حزمة المطعم مجانية: أنشئها في 3 أسئلة وابدأ اليوم.',
    points: ['حزمة مجانية', 'بدون بطاقة بنكية', 'تُفعَّل في دقائق'],
  },
};


export const LEAD_COPY: Record<
  VitrineLocale,
  { title: string; emailPlaceholder: string; consent: string; submit: string; sending: string; sent: string; error: string; skip: string }
> = {
  fr: { title: 'Recevez votre pack offert par email', emailPlaceholder: 'votre@email.com', consent: 'J\'accepte d\'être recontacté(e) au sujet de mon pack (consentement marketing).', submit: 'Recevoir mon pack offert', sending: 'Envoi…', sent: 'Reçu ! Votre pack arrive dans votre boîte mail.', error: 'Impossible d\'envoyer pour l\'instant. Vous pouvez télécharger directement ci-dessus.', skip: 'Vous pouvez aussi télécharger directement ci-dessus.' },
  en: { title: 'Get your free pack by email', emailPlaceholder: 'you@email.com', consent: 'I agree to be contacted about my pack (marketing consent).', submit: 'Send my free pack', sending: 'Sending…', sent: 'Received! Your pack is on its way to your inbox.', error: 'Could not send right now. You can still download directly above.', skip: 'You can also download directly above.' },
  tr: { title: 'Ücretsiz paketinizi e-postayla alın', emailPlaceholder: 'siz@eposta.com', consent: 'Paketim hakkında iletişime geçilmesini kabul ediyorum (pazarlama onayı).', submit: 'Ücretsiz paketimi gönder', sending: 'Gönderiliyor…', sent: 'Alındı! Paketiniz e-postanıza gönderiliyor.', error: 'Şu anda gönderilemedi. Yukarıdan doğrudan indirebilirsiniz.', skip: 'Ayrıca yukarıdan doğrudan indirebilirsiniz.' },
  ar: { title: 'استلموا حزمتكم المجانية عبر البريد', emailPlaceholder: 'you@email.com', consent: 'أوافق على التواصل معي بخصوص حزمتي (موافقة تسويقية).', submit: 'أرسلوا حزمتي المجانية', sending: 'جارٍ الإرسال…', sent: 'تم الاستلام! حزمتكم في طريقها إلى بريدكم.', error: 'تعذر الإرسال الآن. يمكنكم التنزيل مباشرة أعلاه.', skip: 'يمكنكم أيضًا التنزيل مباشرة أعلاه.' },
};


/** Commande d'installation du nœud Edge (affichée dans le wizard — contenu technique).
 *  #7653 : le jeton ne passe jamais en argv (visible dans ps/history) — il est
 *  transmis par variable d'environnement préservée à travers sudo.
 *  #7963 : plus aucune URL backend en dur — `<API_BASE_URL>` est un
 *  placeholder (comme `<EDGE_TOKEN>`/`<NODE_ID>`) à remplacer par l'URL de
 *  l'API de l'environnement cible (registre docs/ops/DOMAINS.md). */
export const EDGE_INSTALL_CMD =
  'curl -fsSL <API_BASE_URL>/api/v1/edge/install.sh -o install.sh && EDGE_TOKEN=<EDGE_TOKEN> sudo --preserve-env=EDGE_TOKEN bash install.sh --node-id <NODE_ID>';
