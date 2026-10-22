#!/usr/bin/env node
/**
 * #8754 [BOS-CHAT] — ajoute le namespace i18n `chatHub` aux quatre catalogues
 * source (`shared/i18n/locales/{fr,en,ar,tr}.json`).
 *
 * Pourquoi un script plutôt qu'une édition manuelle : les quatre fichiers font
 * plusieurs milliers de lignes, et `sync-web.js` propage ensuite vers
 * `front/admin-dashboard/src/i18n/locales/*` et `front/web/src/lib/i18n/locales/*`
 * avec une sémantique d'union qu'il faut laisser faire (il préserve les clés
 * admin-only). Réécrire les JSON à la main risquerait de casser cette union.
 *
 * Usage : node dev-hub/tools/add-chat-hub-i18n.js
 */
'use strict';

const fs = require('fs');
const path = require('path');

const repoRoot = path.resolve(__dirname, '..', '..');
const localesDir = path.join(repoRoot, 'shared', 'i18n', 'locales');

/** Clés du chat horizontal. `fr` fait foi, les autres langues suivent. */
const CATALOG = {
  fr: {
    title: 'Messagerie',
    subtitle: 'Conversations avec vos collègues et vos groupes de travail',
    list: {
      title: 'Conversations',
      search: 'Rechercher une conversation',
      empty: 'Aucune conversation',
      emptyHint: 'Les échanges avec votre manager ou vos groupes apparaîtront ici.',
      loadError: 'Impossible de charger les conversations.',
      unread: 'non lu(s)',
      noSubject: 'Discussion',
      conversation: 'Conversation',
      kindGroup: 'Groupe',
    },
    thread: {
      selectTitle: 'Sélectionnez une conversation',
      selectHint: 'Choisissez un fil à gauche pour afficher les messages.',
      empty: 'Aucun message',
      emptyHint: 'Écrivez le premier message de cette conversation.',
      loadError: 'Impossible de charger les messages.',
      placeholder: 'Écrivez un message…',
      send: 'Envoyer',
      sending: 'Envoi…',
      enterHint: 'Entrée pour envoyer, Maj+Entrée pour un retour à la ligne.',
      loadOlder: 'Charger les messages précédents',
      edited: 'modifié',
      deletedMessage: 'Message supprimé',
      reply: 'Répondre',
      edit: 'Modifier',
      delete: 'Supprimer',
      report: 'Signaler',
      editingMessage: 'Modification du message',
      replyingTo: 'En réponse à :',
      attach: 'Joindre un fichier',
      attachment: 'Pièce jointe',
      attachmentTooLarge: 'Fichier trop volumineux pour être envoyé.',
      attachmentGone: 'Pièce jointe indisponible : le fichier a été perdu lors d’un redéploiement.',
      attachmentForbidden: 'Pièce jointe introuvable ou accès refusé.',
      attachmentFailed: 'Téléchargement de la pièce jointe impossible.',
      offline: 'Hors ligne',
      live: 'À jour',
      deleteConfirm: 'Supprimer ce message ?',
      reportPrompt: 'Motif du signalement',
      reportSent: 'Signalement transmis à la modération.',
      reportFailed: 'Signalement impossible.',
      editForbidden: 'Vous ne pouvez modifier que vos propres messages.',
      editFailed: 'Modification impossible.',
      deleteFailed: 'Suppression impossible.',
      sendFailed: 'Envoi impossible. Réessayez.',
      offlineSendFailed: 'Vous êtes hors ligne : le message n’a pas été envoyé.',
      notParticipant: 'Cette conversation n’est plus accessible.',
      rateLimited: 'Trop de messages envoyés. Patientez un instant.',
      invalidMessage: 'Message invalide.',
      directSubtitle: 'Conversation privée',
      groupMembers: 'Membres du groupe',
    },
    tenantRequired: {
      title: 'Contexte entreprise requis',
      body: 'La messagerie est accessible avec une session entreprise (impersonation ou compte manager).',
    },
    forbidden: {
      title: 'Messagerie désactivée',
      body: 'Le module de messagerie n’est pas activé pour cette entreprise.',
    },
    common: {
      loading: 'Chargement…',
      retry: 'Réessayer',
      refresh: 'Rafraîchir',
      save: 'Enregistrer',
      cancel: 'Annuler',
    },
    subjects: {
      salary_advance: 'Avance sur salaire',
      attendance_correction: 'Correction de pointage',
      absence: 'Absence',
    },
    report: {
      reasonsHint: 'harassment, spam, inappropriate_content, threat, impersonation, other',
    },
  },
  en: {
    title: 'Messaging',
    subtitle: 'Conversations with your colleagues and work groups',
    list: {
      title: 'Conversations',
      search: 'Search a conversation',
      empty: 'No conversation',
      emptyHint: 'Exchanges with your manager or your groups will appear here.',
      loadError: 'Unable to load conversations.',
      unread: 'unread',
      noSubject: 'Discussion',
      conversation: 'Conversation',
      kindGroup: 'Group',
    },
    thread: {
      selectTitle: 'Select a conversation',
      selectHint: 'Pick a thread on the left to display its messages.',
      empty: 'No message',
      emptyHint: 'Write the first message of this conversation.',
      loadError: 'Unable to load messages.',
      placeholder: 'Write a message…',
      send: 'Send',
      sending: 'Sending…',
      enterHint: 'Enter to send, Shift+Enter for a new line.',
      loadOlder: 'Load previous messages',
      edited: 'edited',
      deletedMessage: 'Message deleted',
      reply: 'Reply',
      edit: 'Edit',
      delete: 'Delete',
      report: 'Report',
      editingMessage: 'Editing message',
      replyingTo: 'Replying to:',
      attach: 'Attach a file',
      attachment: 'Attachment',
      attachmentTooLarge: 'File too large to be sent.',
      attachmentGone: 'Attachment unavailable: the file was lost during a redeploy.',
      attachmentForbidden: 'Attachment not found or access denied.',
      attachmentFailed: 'Unable to download the attachment.',
      offline: 'Offline',
      live: 'Up to date',
      deleteConfirm: 'Delete this message?',
      reportPrompt: 'Report reason',
      reportSent: 'Report sent to moderation.',
      reportFailed: 'Unable to report.',
      editForbidden: 'You can only edit your own messages.',
      editFailed: 'Unable to edit.',
      deleteFailed: 'Unable to delete.',
      sendFailed: 'Unable to send. Please retry.',
      offlineSendFailed: 'You are offline: the message was not sent.',
      notParticipant: 'This conversation is no longer accessible.',
      rateLimited: 'Too many messages. Please wait a moment.',
      invalidMessage: 'Invalid message.',
      directSubtitle: 'Private conversation',
      groupMembers: 'Group members',
    },
    tenantRequired: {
      title: 'Company context required',
      body: 'Messaging requires a company session (impersonation or manager account).',
    },
    forbidden: {
      title: 'Messaging disabled',
      body: 'The messaging module is not enabled for this company.',
    },
    common: {
      loading: 'Loading…',
      retry: 'Retry',
      refresh: 'Refresh',
      save: 'Save',
      cancel: 'Cancel',
    },
    subjects: {
      salary_advance: 'Salary advance',
      attendance_correction: 'Attendance correction',
      absence: 'Absence',
    },
    report: {
      reasonsHint: 'harassment, spam, inappropriate_content, threat, impersonation, other',
    },
  },
  ar: {
    title: 'المراسلة',
    subtitle: 'محادثات مع زملائك ومجموعات العمل',
    list: {
      title: 'المحادثات',
      search: 'البحث عن محادثة',
      empty: 'لا توجد محادثة',
      emptyHint: 'ستظهر هنا المحادثات مع مديرك أو مجموعاتك.',
      loadError: 'تعذر تحميل المحادثات.',
      unread: 'غير مقروء',
      noSubject: 'نقاش',
      conversation: 'محادثة',
      kindGroup: 'مجموعة',
    },
    thread: {
      selectTitle: 'اختر محادثة',
      selectHint: 'اختر محادثة من اليسار لعرض الرسائل.',
      empty: 'لا توجد رسالة',
      emptyHint: 'اكتب أول رسالة في هذه المحادثة.',
      loadError: 'تعذر تحميل الرسائل.',
      placeholder: 'اكتب رسالة…',
      send: 'إرسال',
      sending: 'جارٍ الإرسال…',
      enterHint: 'Enter للإرسال، Shift+Enter لسطر جديد.',
      loadOlder: 'تحميل الرسائل السابقة',
      edited: 'معدلة',
      deletedMessage: 'رسالة محذوفة',
      reply: 'رد',
      edit: 'تعديل',
      delete: 'حذف',
      report: 'إبلاغ',
      editingMessage: 'تعديل الرسالة',
      replyingTo: 'ردًا على:',
      attach: 'إرفاق ملف',
      attachment: 'مرفق',
      attachmentTooLarge: 'الملف كبير جدًا للإرسال.',
      attachmentGone: 'المرفق غير متاح: فُقد الملف أثناء إعادة النشر.',
      attachmentForbidden: 'المرفق غير موجود أو الوصول مرفوض.',
      attachmentFailed: 'تعذر تنزيل المرفق.',
      offline: 'غير متصل',
      live: 'محدّث',
      deleteConfirm: 'حذف هذه الرسالة؟',
      reportPrompt: 'سبب الإبلاغ',
      reportSent: 'أُرسل الإبلاغ إلى المراجعة.',
      reportFailed: 'تعذر الإبلاغ.',
      editForbidden: 'يمكنك تعديل رسائلك فقط.',
      editFailed: 'تعذر التعديل.',
      deleteFailed: 'تعذر الحذف.',
      sendFailed: 'تعذر الإرسال. أعد المحاولة.',
      offlineSendFailed: 'أنت غير متصل: لم تُرسل الرسالة.',
      notParticipant: 'لم تعد هذه المحادثة متاحة.',
      rateLimited: 'رسائل كثيرة جدًا. انتظر لحظة.',
      invalidMessage: 'رسالة غير صالحة.',
      directSubtitle: 'محادثة خاصة',
      groupMembers: 'أعضاء المجموعة',
    },
    tenantRequired: {
      title: 'سياق الشركة مطلوب',
      body: 'المراسلة متاحة بجلسة شركة (انتحال هوية أو حساب مدير).',
    },
    forbidden: {
      title: 'المراسلة معطلة',
      body: 'وحدة المراسلة غير مفعّلة لهذه الشركة.',
    },
    common: {
      loading: 'جارٍ التحميل…',
      retry: 'إعادة المحاولة',
      refresh: 'تحديث',
      save: 'حفظ',
      cancel: 'إلغاء',
    },
    subjects: {
      salary_advance: 'سلفة على الراتب',
      attendance_correction: 'تصحيح الحضور',
      absence: 'غياب',
    },
    report: {
      reasonsHint: 'حدد سبب الإبلاغ من القائمة',
    },
  },
  tr: {
    title: 'Mesajlaşma',
    subtitle: 'İş arkadaşlarınızla ve çalışma gruplarınızla sohbetler',
    list: {
      title: 'Sohbetler',
      search: 'Sohbet ara',
      empty: 'Sohbet yok',
      emptyHint: 'Yöneticinizle veya gruplarınızla yazışmalar burada görünecek.',
      loadError: 'Sohbetler yüklenemedi.',
      unread: 'okunmamış',
      noSubject: 'Görüşme',
      conversation: 'Sohbet',
      kindGroup: 'Grup',
    },
    thread: {
      selectTitle: 'Bir sohbet seçin',
      selectHint: 'Mesajları görmek için soldan bir sohbet seçin.',
      empty: 'Mesaj yok',
      emptyHint: 'Bu sohbetin ilk mesajını yazın.',
      loadError: 'Mesajlar yüklenemedi.',
      placeholder: 'Bir mesaj yazın…',
      send: 'Gönder',
      sending: 'Gönderiliyor…',
      enterHint: 'Göndermek için Enter, yeni satır için Shift+Enter.',
      loadOlder: 'Önceki mesajları yükle',
      edited: 'düzenlendi',
      deletedMessage: 'Mesaj silindi',
      reply: 'Yanıtla',
      edit: 'Düzenle',
      delete: 'Sil',
      report: 'Bildir',
      editingMessage: 'Mesaj düzenleniyor',
      replyingTo: 'Yanıt:',
      attach: 'Dosya ekle',
      attachment: 'Ek',
      attachmentTooLarge: 'Dosya göndermek için çok büyük.',
      attachmentGone: 'Ek kullanılamıyor: dosya yeniden dağıtım sırasında kayboldu.',
      attachmentForbidden: 'Ek bulunamadı veya erişim reddedildi.',
      attachmentFailed: 'Ek indirilemedi.',
      offline: 'Çevrimdışı',
      live: 'Güncel',
      deleteConfirm: 'Bu mesaj silinsin mi?',
      reportPrompt: 'Bildirim nedeni',
      reportSent: 'Bildirim moderasyona iletildi.',
      reportFailed: 'Bildirim yapılamadı.',
      editForbidden: 'Yalnızca kendi mesajlarınızı düzenleyebilirsiniz.',
      editFailed: 'Düzenleme yapılamadı.',
      deleteFailed: 'Silme yapılamadı.',
      sendFailed: 'Gönderilemedi. Tekrar deneyin.',
      offlineSendFailed: 'Çevrimdışısınız: mesaj gönderilmedi.',
      notParticipant: 'Bu sohbet artık erişilebilir değil.',
      rateLimited: 'Çok fazla mesaj. Lütfen bekleyin.',
      invalidMessage: 'Geçersiz mesaj.',
      directSubtitle: 'Özel sohbet',
      groupMembers: 'Grup üyeleri',
    },
    tenantRequired: {
      title: 'Şirket bağlamı gerekli',
      body: 'Mesajlaşma bir şirket oturumu (kimlik taklidi veya yönetici hesabı) gerektirir.',
    },
    forbidden: {
      title: 'Mesajlaşma kapalı',
      body: 'Mesajlaşma modülü bu şirket için etkin değil.',
    },
    common: {
      loading: 'Yükleniyor…',
      retry: 'Yeniden dene',
      refresh: 'Yenile',
      save: 'Kaydet',
      cancel: 'İptal',
    },
    subjects: {
      salary_advance: 'Maaş avansı',
      attendance_correction: 'Puantaj düzeltmesi',
      absence: 'Devamsızlık',
    },
    report: {
      reasonsHint: 'harassment, spam, inappropriate_content, threat, impersonation, other',
    },
  },
};

/**
 * Écrit le catalogue SANS réordonner les clés existantes.
 *
 * La première version triait toutes les clés : le diff portait sur 16 000
 * lignes par fichier, noyant 60 clés ajoutées dans du bruit illisible. On
 * remplace donc uniquement `chatHub`, et on laisse l'ordre du fichier intact —
 * ce que fait déjà `sync-web.js` avec son `deepMerge`.
 */
const STAMP = '2026-10-20T00:00:00Z';

let touched = 0;

for (const locale of ['fr', 'en', 'ar', 'tr']) {
  const file = path.join(localesDir, `${locale}.json`);
  const catalog = JSON.parse(fs.readFileSync(file, 'utf8'));

  // Remplacement idempotent : relancer le script ne duplique rien.
  catalog.chatHub = CATALOG[locale];
  catalog._updated_at = STAMP;

  fs.writeFileSync(file, `${JSON.stringify(catalog, null, 2)}\n`, 'utf8');
  touched += 1;
}

console.log(`[chatHub] ${touched} catalogue(s) mis à jour dans shared/i18n/locales/`);
