/**
 * Copie du back-office de la bibliothèque (`/library-admin`) — fr/en/tr/ar.
 *
 * Extraite de `copy.ts` pour rester lisible : la surface publique et la
 * surface d'administration n'ont pas le même volume de libellés. Le type
 * `LibraryAdminCopy` est consommé par `LibraryCopy['admin']`, donc la parité
 * des 4 langues reste vérifiée par `lib/i18n.test.ts` (mêmes clés partout).
 */

import type { LibraryLocale } from '../types';

export interface LibraryAdminCopy {
  title: string;
  subtitle: string;
  restricted: string;
  list: {
    title: string;
    empty: string;
    loading: string;
    error: string;
    retry: string;
    total: string;
    page: string;
    of: string;
    previous: string;
    next: string;
    columns: {
      title: string;
      status: string;
      access: string;
      type: string;
      language: string;
      file: string;
      updated: string;
      actions: string;
    };
    filePresent: string;
    fileMissing: string;
  };
  filters: {
    title: string;
    search: string;
    searchPlaceholder: string;
    status: string;
    access: string;
    type: string;
    language: string;
    all: string;
    apply: string;
    reset: string;
  };
  form: {
    create: string;
    edit: string;
    save: string;
    saving: string;
    cancel: string;
    delete: string;
    deleteConfirm: string;
    title: string;
    slug: string;
    description: string;
    excerpt: string;
    documentType: string;
    language: string;
    accessModel: string;
    priceMinor: string;
    priceHint: string;
    currency: string;
    publisher: string;
    publishedYear: string;
    pages: string;
    licenseId: string;
    rightsHolder: string;
    allowDownload: string;
    allowRedistribution: string;
    featured: string;
    isNew: string;
    required: string;
    invalidPrice: string;
    invalidCurrency: string;
  };
  workflow: {
    title: string;
    current: string;
    publish: string;
    unpublish: string;
    archive: string;
    publishing: string;
    hint: string;
    draft: string;
    pending: string;
    published: string;
    archived: string;
  };
  rights: {
    title: string;
    hint: string;
    downloadAllowed: string;
    downloadBlocked: string;
    redistributionAllowed: string;
    redistributionBlocked: string;
    readOnlyNotice: string;
  };
  upload: {
    title: string;
    hint: string;
    choose: string;
    selected: string;
    send: string;
    sending: string;
    success: string;
    noFile: string;
    tooLarge: string;
    badType: string;
    storageUnavailable: string;
    genericError: string;
    currentFile: string;
    noCurrentFile: string;
  };
}

const fr: LibraryAdminCopy = {
  title: 'Administration de la bibliothèque',
  subtitle: 'Gérez les ressources, les fichiers et le cycle éditorial de votre organisation.',
  restricted: 'Cette surface est réservée aux responsables de l’organisation.',
  list: {
    title: 'Ressources',
    empty: 'Aucune ressource ne correspond à ces filtres.',
    loading: 'Chargement des ressources…',
    error: 'Impossible de charger les ressources.',
    retry: 'Réessayer',
    total: 'ressources',
    page: 'Page',
    of: 'sur',
    previous: 'Précédent',
    next: 'Suivant',
    columns: {
      title: 'Titre',
      status: 'Statut',
      access: 'Accès',
      type: 'Type',
      language: 'Langue',
      file: 'Fichier',
      updated: 'Modifié le',
      actions: 'Actions',
    },
    filePresent: 'Fichier présent',
    fileMissing: 'Aucun fichier',
  },
  filters: {
    title: 'Filtres',
    search: 'Recherche',
    searchPlaceholder: 'Titre, référence, éditeur…',
    status: 'Statut',
    access: 'Accès',
    type: 'Type de document',
    language: 'Langue',
    all: 'Tous',
    apply: 'Filtrer',
    reset: 'Réinitialiser',
  },
  form: {
    create: 'Nouvelle ressource',
    edit: 'Modifier la ressource',
    save: 'Enregistrer',
    saving: 'Enregistrement…',
    cancel: 'Annuler',
    delete: 'Supprimer',
    deleteConfirm: 'Supprimer définitivement cette ressource ?',
    title: 'Titre',
    slug: 'Référence d’URL',
    description: 'Description',
    excerpt: 'Extrait',
    documentType: 'Type de document',
    language: 'Langue',
    accessModel: 'Modèle d’accès',
    priceMinor: 'Prix (centimes)',
    priceHint: 'Entier en plus petite unité monétaire — jamais de décimales.',
    currency: 'Devise',
    publisher: 'Éditeur',
    publishedYear: 'Année de publication',
    pages: 'Nombre de pages',
    licenseId: 'Identifiant de licence',
    rightsHolder: 'Détenteur des droits',
    allowDownload: 'Autoriser le téléchargement',
    allowRedistribution: 'Autoriser la redistribution',
    featured: 'Mise en avant éditoriale',
    isNew: 'Marquer comme nouveauté',
    required: 'Ce champ est obligatoire.',
    invalidPrice: 'Le prix doit être un entier positif ou nul.',
    invalidCurrency: 'La devise doit être un code ISO à 3 lettres majuscules.',
  },
  workflow: {
    title: 'Cycle éditorial',
    current: 'Statut actuel',
    publish: 'Publier',
    unpublish: 'Repasser en brouillon',
    archive: 'Archiver',
    publishing: 'Mise à jour…',
    hint: 'Aucune publication automatique : chaque transition est explicite.',
    draft: 'Brouillon',
    pending: 'En attente de validation',
    published: 'Publié',
    archived: 'Archivé',
  },
  rights: {
    title: 'Droits d’accès',
    hint: 'Ces réglages sont vérifiés côté serveur à chaque lecture et téléchargement.',
    downloadAllowed: 'Téléchargement autorisé',
    downloadBlocked: 'Téléchargement bloqué',
    redistributionAllowed: 'Redistribution autorisée',
    redistributionBlocked: 'Redistribution interdite',
    readOnlyNotice: 'En lecture gratuite, le téléchargement est toujours refusé.',
  },
  upload: {
    title: 'Fichier du document',
    hint: 'Formats acceptés : PDF, EPUB, TXT, HTML. Le type réel est vérifié côté serveur.',
    choose: 'Choisir un fichier',
    selected: 'Fichier sélectionné',
    send: 'Téléverser',
    sending: 'Téléversement…',
    success: 'Fichier téléversé.',
    noFile: 'Sélectionnez un fichier avant de téléverser.',
    tooLarge: 'Fichier trop volumineux pour la limite configurée.',
    badType: 'Type de fichier non autorisé.',
    storageUnavailable: 'Le stockage de fichiers n’est pas encore configuré sur cet environnement.',
    genericError: 'Le téléversement a échoué.',
    currentFile: 'Fichier actuel',
    noCurrentFile: 'Aucun fichier attaché',
  },
};

const en: LibraryAdminCopy = {
  title: 'Library administration',
  subtitle: 'Manage your organisation’s resources, files and editorial lifecycle.',
  restricted: 'This area is reserved for organisation managers.',
  list: {
    title: 'Resources',
    empty: 'No resource matches these filters.',
    loading: 'Loading resources…',
    error: 'Could not load the resources.',
    retry: 'Retry',
    total: 'resources',
    page: 'Page',
    of: 'of',
    previous: 'Previous',
    next: 'Next',
    columns: {
      title: 'Title',
      status: 'Status',
      access: 'Access',
      type: 'Type',
      language: 'Language',
      file: 'File',
      updated: 'Updated',
      actions: 'Actions',
    },
    filePresent: 'File attached',
    fileMissing: 'No file',
  },
  filters: {
    title: 'Filters',
    search: 'Search',
    searchPlaceholder: 'Title, reference, publisher…',
    status: 'Status',
    access: 'Access',
    type: 'Document type',
    language: 'Language',
    all: 'All',
    apply: 'Filter',
    reset: 'Reset',
  },
  form: {
    create: 'New resource',
    edit: 'Edit resource',
    save: 'Save',
    saving: 'Saving…',
    cancel: 'Cancel',
    delete: 'Delete',
    deleteConfirm: 'Permanently delete this resource?',
    title: 'Title',
    slug: 'URL reference',
    description: 'Description',
    excerpt: 'Excerpt',
    documentType: 'Document type',
    language: 'Language',
    accessModel: 'Access model',
    priceMinor: 'Price (minor units)',
    priceHint: 'Integer in the smallest currency unit — never decimals.',
    currency: 'Currency',
    publisher: 'Publisher',
    publishedYear: 'Publication year',
    pages: 'Page count',
    licenseId: 'Licence identifier',
    rightsHolder: 'Rights holder',
    allowDownload: 'Allow download',
    allowRedistribution: 'Allow redistribution',
    featured: 'Editorial highlight',
    isNew: 'Mark as new',
    required: 'This field is required.',
    invalidPrice: 'The price must be a positive integer or zero.',
    invalidCurrency: 'The currency must be a 3-letter uppercase ISO code.',
  },
  workflow: {
    title: 'Editorial lifecycle',
    current: 'Current status',
    publish: 'Publish',
    unpublish: 'Back to draft',
    archive: 'Archive',
    publishing: 'Updating…',
    hint: 'No automatic publication: every transition is explicit.',
    draft: 'Draft',
    pending: 'Pending review',
    published: 'Published',
    archived: 'Archived',
  },
  rights: {
    title: 'Access rights',
    hint: 'These settings are enforced server-side on every read and download.',
    downloadAllowed: 'Download allowed',
    downloadBlocked: 'Download blocked',
    redistributionAllowed: 'Redistribution allowed',
    redistributionBlocked: 'Redistribution forbidden',
    readOnlyNotice: 'For free reading, download is always refused.',
  },
  upload: {
    title: 'Document file',
    hint: 'Accepted formats: PDF, EPUB, TXT, HTML. The real type is verified server-side.',
    choose: 'Choose a file',
    selected: 'Selected file',
    send: 'Upload',
    sending: 'Uploading…',
    success: 'File uploaded.',
    noFile: 'Select a file before uploading.',
    tooLarge: 'File too large for the configured limit.',
    badType: 'File type not allowed.',
    storageUnavailable: 'File storage is not configured on this environment yet.',
    genericError: 'The upload failed.',
    currentFile: 'Current file',
    noCurrentFile: 'No file attached',
  },
};

const tr: LibraryAdminCopy = {
  title: 'Kütüphane yönetimi',
  subtitle: 'Kuruluşunuzun kaynaklarını, dosyalarını ve yayın döngüsünü yönetin.',
  restricted: 'Bu alan yalnızca kuruluş yöneticilerine açıktır.',
  list: {
    title: 'Kaynaklar',
    empty: 'Bu filtrelerle eşleşen kaynak yok.',
    loading: 'Kaynaklar yükleniyor…',
    error: 'Kaynaklar yüklenemedi.',
    retry: 'Yeniden dene',
    total: 'kaynak',
    page: 'Sayfa',
    of: '/',
    previous: 'Önceki',
    next: 'Sonraki',
    columns: {
      title: 'Başlık',
      status: 'Durum',
      access: 'Erişim',
      type: 'Tür',
      language: 'Dil',
      file: 'Dosya',
      updated: 'Güncellendi',
      actions: 'İşlemler',
    },
    filePresent: 'Dosya ekli',
    fileMissing: 'Dosya yok',
  },
  filters: {
    title: 'Filtreler',
    search: 'Arama',
    searchPlaceholder: 'Başlık, referans, yayıncı…',
    status: 'Durum',
    access: 'Erişim',
    type: 'Belge türü',
    language: 'Dil',
    all: 'Tümü',
    apply: 'Filtrele',
    reset: 'Sıfırla',
  },
  form: {
    create: 'Yeni kaynak',
    edit: 'Kaynağı düzenle',
    save: 'Kaydet',
    saving: 'Kaydediliyor…',
    cancel: 'İptal',
    delete: 'Sil',
    deleteConfirm: 'Bu kaynak kalıcı olarak silinsin mi?',
    title: 'Başlık',
    slug: 'URL referansı',
    description: 'Açıklama',
    excerpt: 'Özet',
    documentType: 'Belge türü',
    language: 'Dil',
    accessModel: 'Erişim modeli',
    priceMinor: 'Fiyat (küçük birim)',
    priceHint: 'En küçük para biriminde tam sayı — asla ondalık değil.',
    currency: 'Para birimi',
    publisher: 'Yayıncı',
    publishedYear: 'Yayın yılı',
    pages: 'Sayfa sayısı',
    licenseId: 'Lisans tanımlayıcısı',
    rightsHolder: 'Hak sahibi',
    allowDownload: 'İndirmeye izin ver',
    allowRedistribution: 'Yeniden dağıtıma izin ver',
    featured: 'Editoryal öne çıkarma',
    isNew: 'Yeni olarak işaretle',
    required: 'Bu alan zorunludur.',
    invalidPrice: 'Fiyat sıfır veya pozitif bir tam sayı olmalıdır.',
    invalidCurrency: 'Para birimi 3 harfli büyük ISO kodu olmalıdır.',
  },
  workflow: {
    title: 'Yayın döngüsü',
    current: 'Mevcut durum',
    publish: 'Yayınla',
    unpublish: 'Taslağa döndür',
    archive: 'Arşivle',
    publishing: 'Güncelleniyor…',
    hint: 'Otomatik yayın yok: her geçiş açıkça yapılır.',
    draft: 'Taslak',
    pending: 'Onay bekliyor',
    published: 'Yayınlandı',
    archived: 'Arşivlendi',
  },
  rights: {
    title: 'Erişim hakları',
    hint: 'Bu ayarlar her okuma ve indirmede sunucu tarafında uygulanır.',
    downloadAllowed: 'İndirme izinli',
    downloadBlocked: 'İndirme engelli',
    redistributionAllowed: 'Yeniden dağıtım izinli',
    redistributionBlocked: 'Yeniden dağıtım yasak',
    readOnlyNotice: 'Ücretsiz okumada indirme her zaman reddedilir.',
  },
  upload: {
    title: 'Belge dosyası',
    hint: 'Kabul edilen biçimler: PDF, EPUB, TXT, HTML. Gerçek tür sunucuda doğrulanır.',
    choose: 'Dosya seç',
    selected: 'Seçilen dosya',
    send: 'Yükle',
    sending: 'Yükleniyor…',
    success: 'Dosya yüklendi.',
    noFile: 'Yüklemeden önce bir dosya seçin.',
    tooLarge: 'Dosya yapılandırılmış sınır için çok büyük.',
    badType: 'Dosya türüne izin verilmiyor.',
    storageUnavailable: 'Dosya depolama bu ortamda henüz yapılandırılmadı.',
    genericError: 'Yükleme başarısız oldu.',
    currentFile: 'Mevcut dosya',
    noCurrentFile: 'Ekli dosya yok',
  },
};

const ar: LibraryAdminCopy = {
  title: 'إدارة المكتبة',
  subtitle: 'أدر موارد مؤسستك وملفاتها ودورة النشر التحريرية.',
  restricted: 'هذه المساحة مخصّصة لمديري المؤسسة.',
  list: {
    title: 'الموارد',
    empty: 'لا يوجد مورد يطابق هذه المرشّحات.',
    loading: 'جارٍ تحميل الموارد…',
    error: 'تعذّر تحميل الموارد.',
    retry: 'إعادة المحاولة',
    total: 'موردًا',
    page: 'صفحة',
    of: 'من',
    previous: 'السابق',
    next: 'التالي',
    columns: {
      title: 'العنوان',
      status: 'الحالة',
      access: 'الوصول',
      type: 'النوع',
      language: 'اللغة',
      file: 'الملف',
      updated: 'آخر تحديث',
      actions: 'إجراءات',
    },
    filePresent: 'ملف مرفق',
    fileMissing: 'لا يوجد ملف',
  },
  filters: {
    title: 'المرشّحات',
    search: 'بحث',
    searchPlaceholder: 'العنوان، المرجع، الناشر…',
    status: 'الحالة',
    access: 'الوصول',
    type: 'نوع المستند',
    language: 'اللغة',
    all: 'الكل',
    apply: 'تصفية',
    reset: 'إعادة تعيين',
  },
  form: {
    create: 'مورد جديد',
    edit: 'تعديل المورد',
    save: 'حفظ',
    saving: 'جارٍ الحفظ…',
    cancel: 'إلغاء',
    delete: 'حذف',
    deleteConfirm: 'حذف هذا المورد نهائيًا؟',
    title: 'العنوان',
    slug: 'مرجع الرابط',
    description: 'الوصف',
    excerpt: 'مقتطف',
    documentType: 'نوع المستند',
    language: 'اللغة',
    accessModel: 'نموذج الوصول',
    priceMinor: 'السعر (الوحدة الصغرى)',
    priceHint: 'عدد صحيح بأصغر وحدة نقدية — دون كسور إطلاقًا.',
    currency: 'العملة',
    publisher: 'الناشر',
    publishedYear: 'سنة النشر',
    pages: 'عدد الصفحات',
    licenseId: 'معرّف الترخيص',
    rightsHolder: 'صاحب الحقوق',
    allowDownload: 'السماح بالتنزيل',
    allowRedistribution: 'السماح بإعادة التوزيع',
    featured: 'إبراز تحريري',
    isNew: 'وسم كجديد',
    required: 'هذا الحقل مطلوب.',
    invalidPrice: 'يجب أن يكون السعر عددًا صحيحًا موجبًا أو صفرًا.',
    invalidCurrency: 'يجب أن تكون العملة رمز ISO من ثلاثة أحرف كبيرة.',
  },
  workflow: {
    title: 'دورة النشر',
    current: 'الحالة الحالية',
    publish: 'نشر',
    unpublish: 'إرجاع إلى المسودة',
    archive: 'أرشفة',
    publishing: 'جارٍ التحديث…',
    hint: 'لا نشر تلقائي: كل انتقال صريح.',
    draft: 'مسودة',
    pending: 'بانتظار المراجعة',
    published: 'منشور',
    archived: 'مؤرشف',
  },
  rights: {
    title: 'حقوق الوصول',
    hint: 'تُطبَّق هذه الإعدادات على الخادم عند كل قراءة وتنزيل.',
    downloadAllowed: 'التنزيل مسموح',
    downloadBlocked: 'التنزيل محظور',
    redistributionAllowed: 'إعادة التوزيع مسموحة',
    redistributionBlocked: 'إعادة التوزيع ممنوعة',
    readOnlyNotice: 'في القراءة المجانية، يُرفض التنزيل دائمًا.',
  },
  upload: {
    title: 'ملف المستند',
    hint: 'الصيغ المقبولة: PDF وEPUB وTXT وHTML. يُتحقَّق من النوع الحقيقي على الخادم.',
    choose: 'اختيار ملف',
    selected: 'الملف المحدَّد',
    send: 'رفع',
    sending: 'جارٍ الرفع…',
    success: 'تم رفع الملف.',
    noFile: 'اختر ملفًا قبل الرفع.',
    tooLarge: 'الملف أكبر من الحدّ المُهيّأ.',
    badType: 'نوع الملف غير مسموح.',
    storageUnavailable: 'تخزين الملفات غير مُهيّأ بعد في هذه البيئة.',
    genericError: 'فشل الرفع.',
    currentFile: 'الملف الحالي',
    noCurrentFile: 'لا يوجد ملف مرفق',
  },
};

/** Copie d'administration par locale (parité garantie par le type). */
export const ADMIN_COPY: Record<LibraryLocale, LibraryAdminCopy> = { fr, en, tr, ar };
