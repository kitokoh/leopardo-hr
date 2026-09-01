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
    subtitle: 'Répondez à 3 questions : on vous propose automatiquement le pack Leopardo adapté à votre restaurant. Vous cochez, vous téléchargez.',
    start: 'Commencer',
    next: 'Voir mon pack',
    back: 'Retour',
    loading: 'Calcul de votre pack…',
    questionsTitle: 'Parlez-nous de votre restaurant',
    suggestionsTitle: 'Voici le pack recommandé pour vous',
    suggestionsSubtitle: 'Pré-coché selon vos réponses. Décochez ce dont vous n\'avez pas besoin.',
    keep: 'Continuer',
    uncheckNote: 'Ces éléments s\'activeront dans votre espace Leopardo à la création du compte.',
    downloadTitle: 'Téléchargez votre pack',
    downloadSubtitle: 'Scannez le QR pour installer les apps, ou suivez les liens ci-dessous.',
    qrHint: 'Scannez pour installer',
    edgeTitle: 'Nœud Edge local (offline)',
    edgeCmdHint: 'Installez le nœud local sur un mini-PC du restaurant :',
    guideLabel: 'Guide de démarrage',
    includedLabel: 'Inclus dans votre espace',
    restart: 'Recommencer',
    errorRetry: 'Impossible de calculer le pack. Réessayez.'
  },
  en: {
    title: 'I am a restaurant owner',
    subtitle: 'Answer 3 questions: we automatically build the Leopardo pack for your restaurant. Tick, download.',
    start: 'Start',
    next: 'See my pack',
    back: 'Back',
    loading: 'Building your pack…',
    questionsTitle: 'Tell us about your restaurant',
    suggestionsTitle: 'Here is your recommended pack',
    suggestionsSubtitle: 'Pre-ticked from your answers. Untick what you don\'t need.',
    keep: 'Continue',
    uncheckNote: 'Selected items will be enabled in your Leopardo workspace on signup.',
    downloadTitle: 'Download your pack',
    downloadSubtitle: 'Scan the QR code to install the apps, or use the links below.',
    qrHint: 'Scan to install',
    edgeTitle: 'Local Edge node (offline)',
    edgeCmdHint: 'Install the local node on a mini-PC at the restaurant:',
    guideLabel: 'Getting started guide',
    includedLabel: 'Included in your workspace',
    restart: 'Start over',
    errorRetry: 'Could not build your pack. Try again.'
  },
  tr: { title: 'Ben bir restoran sahibiyim', subtitle: '3 soruya yanıt verin: restoranınıza uygun Leopardo paketini otomatik önerelim. İşaretleyin, indirin.', start: 'Başla', next: 'Paketimi gör', back: 'Geri', loading: 'Paketiniz hesaplanıyor…', questionsTitle: 'Restoranınızdan bahsedin', suggestionsTitle: 'Size önerilen paket', suggestionsSubtitle: 'Yanıtlarınıza göre önceden işaretlendi.', keep: 'Devam', uncheckNote: 'Seçilen öğeler hesap oluşturulduğunda etkinleşir.', downloadTitle: 'Paketinizi indirin', downloadSubtitle: 'Uygulamaları kurmak için QR kodu okutun.', qrHint: 'Kurulum için okutun', edgeTitle: 'Yerel Edge düğümü (çevrimdışı)', edgeCmdHint: 'Yerel düğümü restorandaki bir mini-PC\'ye kurun:', guideLabel: 'Başlangıç rehberi', includedLabel: 'Çalışma alanınıza dahil',
    errorRetry: 'Paket hesaplanamadı. Tekrar deneyin.' restart: 'Baştan başla' },
  ar: { title: 'أنا صاحب مطعم', subtitle: 'أجب عن 3 أسئلة وسنقترح عليك تلقائيًا حزمة Leopardo المناسبة لمطعمك. حدد ما تحتاجه وحمّله.', start: 'ابدأ', next: 'عرض حزمتي', back: 'رجوع', loading: 'جارٍ حساب الحزمة…', questionsTitle: 'حدثنا عن مطعمك', suggestionsTitle: 'هذه هي الحزمة المقترحة لك', suggestionsSubtitle: 'محددة مسبقًا حسب إجاباتك.', keep: 'متابعة', uncheckNote: 'سيتم تفعيل العناصر المحددة عند إنشاء الحساب.', downloadTitle: 'حمّل حزمتك', downloadSubtitle: 'امسح رمز QR لتثبيت التطبيقات.', qrHint: 'امسح للتثبيت', edgeTitle: 'عقدة Edge المحلية (بدون اتصال)', edgeCmdHint: 'ثبّت العقدة المحلية على جهاز صغير في المطعم:', guideLabel: 'دليل البدء', includedLabel: 'مضمن في مساحتك',
    errorRetry: 'تعذر حساب الحزمة. حاولوا مرة أخرى.' restart: 'إعادة البدء' },
};

const LEAD_COPY: Record<
  VitrineLocale,
  { title: string; emailPlaceholder: string; consent: string; submit: string; sending: string; sent: string; error: string; skip: string }
> = {
  fr: { title: 'Recevez votre pack par email', emailPlaceholder: 'votre@email.com', consent: 'J\'accepte d\'être recontacté(e) au sujet de mon pack (consentement marketing).', submit: 'Recevoir mon pack', sending: 'Envoi…', sent: 'Reçu ! Votre pack arrive dans votre boîte mail.', error: 'Impossible d\'envoyer pour l\'instant. Vous pouvez télécharger directement ci-dessus.', skip: 'Vous pouvez aussi télécharger directement ci-dessus.' },
  en: { title: 'Get your pack by email', emailPlaceholder: 'you@email.com', consent: 'I agree to be contacted about my pack (marketing consent).', submit: 'Send my pack', sending: 'Sending…', sent: 'Received! Your pack is on its way to your inbox.', error: 'Could not send right now. You can still download directly above.', skip: 'You can also download directly above.' },
  tr: { title: 'Paketinizi e-postayla alın', emailPlaceholder: 'siz@eposta.com', consent: 'Paketim hakkında iletişime geçilmesini kabul ediyorum (pazarlama onayı).', submit: 'Paketimi gönder', sending: 'Gönderiliyor…', sent: 'Alındı! Paketiniz e-postanıza gönderiliyor.', error: 'Şu anda gönderilemedi. Yukarıdan doğrudan indirebilirsiniz.', skip: 'Ayrıca yukarıdan doğrudan indirebilirsiniz.' },
  ar: { title: 'استلموا حزمتكم عبر البريد', emailPlaceholder: 'you@email.com', consent: 'أوافق على التواصل معي بخصوص حزمتي (موافقة تسويقية).', submit: 'أرسلوا حزمتي', sending: 'جارٍ الإرسال…', sent: 'تم الاستلام! حزمتكم في طريقها إلى بريدكم.', error: 'تعذر الإرسال الآن. يمكنكم التنزيل مباشرة أعلاه.', skip: 'يمكنكم أيضًا التنزيل مباشرة أعلاه.' },
};


/** Commande d'installation du nœud Edge (affichée dans le wizard — contenu technique). */
export const EDGE_INSTALL_CMD =
  'curl -fsSL https://gestionemployerbackend.onrender.com/api/v1/edge/install.sh | sudo bash -s -- --node-id <NODE_ID> --token <EDGE_TOKEN>';
