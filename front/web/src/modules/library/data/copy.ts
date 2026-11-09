/**
 * Leopardo Library — copie i18n du module (fr, en, tr, ar).
 *
 * Le module est AUTONOME : sa copie vit ici plutôt que dans le catalogue
 * partagé `shared/i18n` (qui est propagé vers le backend et le mobile, où la
 * Library n'existe pas). Les 4 locales sont obligatoires — la vitrine est
 * servie en fr/en/tr/ar et l'arabe est RTL.
 *
 * ⚠️ Toute chaîne visible doit venir d'ici : aucune copie en dur dans les
 * composants (convention vitrine, gardes mojibake/accents).
 */

export const LIBRARY_LOCALES = ['fr', 'en', 'tr', 'ar'] as const;
export type LibraryLocale = (typeof LIBRARY_LOCALES)[number];

import type { LibraryAdminCopy } from './admin-copy';
import { ADMIN_COPY } from './admin-copy';

export type { LibraryAdminCopy };

export interface LibraryCopy {
  meta: { title: string; description: string };
  nav: { label: string };
  categories: Record<string, string>;
  home: {
    title: string;
    subtitle: string;
    searchPlaceholder: string;
    popular: string;
    newArrivals: string;
    recentlyAdded: string;
    freeResources: string;
    categories: string;
    editorial: string;
    browseAll: string;
    resourcesCount: string;
    noResults: string;
  };
  filters: {
    allCategories: string;
    allLanguages: string;
    allAccess: string;
    allTypes: string;
    freeOnly: string;
    apply: string;
    reset: string;
  };
  access: {
    free: string;
    read_only: string;
    purchase: string;
    subscription: string;
    private: string;
  };
  types: {
    book: string;
    ebook: string;
    guide: string;
    manual: string;
    report: string;
    article: string;
  };
  detail: {
    author: string;
    publisher: string;
    publishedYear: string;
    pages: string;
    language: string;
    category: string;
    documentType: string;
    access: string;
    license: string;
    rightsHolder: string;
    format: string;
    readOnline: string;
    download: string;
    downloadDisabled: string;
    buy: string;
    subscribe: string;
    privateAccess: string;
    preview: string;
    related: string;
    backToLibrary: string;
    unknown: string;
    demoNotice: string;
    sourceNotice: string;
  };
  reader: {
    title: string;
    page: string;
    of: string;
    zoomIn: string;
    zoomOut: string;
    fullscreen: string;
    exitFullscreen: string;
    search: string;
    searchPlaceholder: string;
    noMatches: string;
    resume: string;
    lastPosition: string;
    close: string;
    notAvailable: string;
    loading: string;
    error: string;
    /** Navigation chapitre précédent (lecteur EPUB). */
    prev: string;
    /** Navigation chapitre suivant (lecteur EPUB). */
    next: string;
  };
  account: {
    title: string;
    myBooks: string;
    myDocuments: string;
    myPurchases: string;
    favorites: string;
    recentReads: string;
    progress: string;
    downloads: string;
    empty: string;
    signInHint: string;
    addFavorite: string;
    removeFavorite: string;
  };
  common: {
    result: string;
    reference: string;
    version: string;
    disclaimer: string;
    notFound: string;
    notFoundHint: string;
    backToLibrary: string;
    loading: string;
  };
  /** Back-office `/library-admin` (BC-40 LIBRARY) — copie dédiée. */
  admin: LibraryAdminCopy;
}

const fr: LibraryCopy = {
  meta: {
    title: 'Leopardo Library — bibliothèque numérique',
    description:
      'Bibliothèque numérique publique et professionnelle : livres, guides, manuels et documents, en accès libre ou professionnel, en français, anglais, arabe et turc.',
  },
  nav: { label: 'Bibliothèque' },
  categories: {
    'livres-ebooks': 'Livres et ebooks',
    'education-formation': 'Éducation et formation',
    'sciences-technologie': 'Sciences et technologie',
    'informatique-ia': 'Informatique et IA',
    'entrepreneuriat-gestion': 'Entrepreneuriat et gestion',
    'agriculture-elevage': 'Agriculture et élevage',
    'commerce-import-export': 'Commerce et import-export',
    'voyage-tourisme': 'Voyage et tourisme',
    'guides-professionnels': 'Guides professionnels',
    'manuels-techniques': 'Manuels techniques',
    'documents-pratiques': 'Documents pratiques',
    litterature: 'Littérature',
  },
  home: {
    title: 'Leopardo Library',
    subtitle:
      'Une bibliothèque numérique moderne : livres, guides et documents à lire en ligne ou à télécharger, librement ou selon vos droits d’accès.',
    searchPlaceholder: 'Rechercher un titre, un auteur, un mot-clé…',
    popular: 'Livres populaires',
    newArrivals: 'Nouveautés',
    recentlyAdded: 'Documents récemment ajoutés',
    freeResources: 'Ressources gratuites',
    categories: 'Catégories',
    editorial: 'Sélection éditoriale',
    browseAll: 'Parcourir toute la bibliothèque',
    resourcesCount: 'ressources disponibles',
    noResults: 'Aucune ressource ne correspond à votre recherche.',
  },
  filters: {
    allCategories: 'Toutes les catégories',
    allLanguages: 'Toutes les langues',
    allAccess: 'Tous les accès',
    allTypes: 'Tous les types',
    freeOnly: 'Gratuit uniquement',
    apply: 'Filtrer',
    reset: 'Réinitialiser',
  },
  access: {
    free: 'Gratuit',
    read_only: 'Lecture gratuite',
    purchase: 'Achat à l’unité',
    subscription: 'Abonnement',
    private: 'Accès privé',
  },
  types: {
    book: 'Livre',
    ebook: 'Ebook',
    guide: 'Guide',
    manual: 'Manuel',
    report: 'Rapport',
    article: 'Article',
  },
  detail: {
    author: 'Auteur',
    publisher: 'Éditeur',
    publishedYear: 'Année de publication',
    pages: 'Pages',
    language: 'Langue',
    category: 'Catégorie',
    documentType: 'Type de document',
    access: 'Conditions d’accès',
    license: 'Licence',
    rightsHolder: 'Détenteur des droits',
    format: 'Format disponible',
    readOnline: 'Lire en ligne',
    download: 'Télécharger',
    downloadDisabled: 'Téléchargement non autorisé pour cette ressource.',
    buy: 'Acheter',
    subscribe: 'Inclus dans l’abonnement',
    privateAccess: 'Réservé à une organisation — accès vérifié côté serveur.',
    preview: 'Aperçu du contenu',
    related: 'Ressources similaires',
    backToLibrary: 'Retour à la bibliothèque',
    unknown: 'Non renseigné',
    demoNotice:
      'Entrée de démonstration : métadonnées illustratives, aucun fichier distribué.',
    sourceNotice: 'Source : œuvre du domaine public ou ressource librement distribuable.',
  },
  reader: {
    title: 'Lecteur',
    page: 'Page',
    of: 'sur',
    zoomIn: 'Zoom avant',
    zoomOut: 'Zoom arrière',
    fullscreen: 'Plein écran',
    exitFullscreen: 'Quitter le plein écran',
    search: 'Rechercher dans le document',
    searchPlaceholder: 'Rechercher…',
    noMatches: 'Aucun résultat dans le document.',
    resume: 'Reprendre la lecture',
    lastPosition: 'Dernière position enregistrée',
    close: 'Fermer',
    notAvailable: 'Lecture en ligne non disponible pour cette ressource.',
    loading: 'Chargement du document…',
    error: 'Impossible de charger le document.',
    prev: 'Chapitre précédent',
    next: 'Chapitre suivant',
  },
  account: {
    title: 'Mon espace',
    myBooks: 'Mes livres',
    myDocuments: 'Mes documents',
    myPurchases: 'Mes achats',
    favorites: 'Mes favoris',
    recentReads: 'Lectures récentes',
    progress: 'Progression de lecture',
    downloads: 'Téléchargements autorisés',
    empty: 'Aucun élément pour le moment.',
    signInHint: 'Connectez-vous pour synchroniser vos favoris et votre progression.',
    addFavorite: 'Ajouter aux favoris',
    removeFavorite: 'Retirer des favoris',
  },
  common: {
    result: 'Résultat',
    reference: 'Référence',
    version: 'Version',
    disclaimer:
      'Leopardo Library référence des œuvres du domaine public et des ressources librement distribuables. Vérifiez toujours les conditions de réutilisation avant tout usage professionnel.',
    notFound: 'Ressource introuvable',
    notFoundHint:
      'Cette ressource n’existe pas ou a été retirée. Parcourez la bibliothèque pour trouver ce que vous cherchez.',
    backToLibrary: 'Retour à la bibliothèque',
    loading: 'Chargement…',
  },
  admin: ADMIN_COPY.fr,
};

const en: LibraryCopy = {
  meta: {
    title: 'Leopardo Library — digital library',
    description:
      'Public and professional digital library: books, guides, manuals and documents, freely or professionally accessible, in French, English, Arabic and Turkish.',
  },
  nav: { label: 'Library' },
  categories: {
    'livres-ebooks': 'Books and ebooks',
    'education-formation': 'Education and training',
    'sciences-technologie': 'Science and technology',
    'informatique-ia': 'Computing and AI',
    'entrepreneuriat-gestion': 'Entrepreneurship and management',
    'agriculture-elevage': 'Agriculture and livestock',
    'commerce-import-export': 'Trade and import-export',
    'voyage-tourisme': 'Travel and tourism',
    'guides-professionnels': 'Professional guides',
    'manuels-techniques': 'Technical manuals',
    'documents-pratiques': 'Practical documents',
    litterature: 'Literature',
  },
  home: {
    title: 'Leopardo Library',
    subtitle:
      'A modern digital library: books, guides and documents to read online or download, freely or according to your access rights.',
    searchPlaceholder: 'Search a title, an author, a keyword…',
    popular: 'Popular books',
    newArrivals: 'New arrivals',
    recentlyAdded: 'Recently added documents',
    freeResources: 'Free resources',
    categories: 'Categories',
    editorial: 'Editorial selection',
    browseAll: 'Browse the whole library',
    resourcesCount: 'resources available',
    noResults: 'No resource matches your search.',
  },
  filters: {
    allCategories: 'All categories',
    allLanguages: 'All languages',
    allAccess: 'All access models',
    allTypes: 'All types',
    freeOnly: 'Free only',
    apply: 'Filter',
    reset: 'Reset',
  },
  access: {
    free: 'Free',
    read_only: 'Free reading',
    purchase: 'One-off purchase',
    subscription: 'Subscription',
    private: 'Private access',
  },
  types: {
    book: 'Book',
    ebook: 'Ebook',
    guide: 'Guide',
    manual: 'Manual',
    report: 'Report',
    article: 'Article',
  },
  detail: {
    author: 'Author',
    publisher: 'Publisher',
    publishedYear: 'Publication year',
    pages: 'Pages',
    language: 'Language',
    category: 'Category',
    documentType: 'Document type',
    access: 'Access conditions',
    license: 'License',
    rightsHolder: 'Rights holder',
    format: 'Available format',
    readOnline: 'Read online',
    download: 'Download',
    downloadDisabled: 'Download is not allowed for this resource.',
    buy: 'Buy',
    subscribe: 'Included in subscription',
    privateAccess: 'Reserved for an organisation — access verified server-side.',
    preview: 'Content preview',
    related: 'Related resources',
    backToLibrary: 'Back to the library',
    unknown: 'Not provided',
    demoNotice: 'Demo entry: illustrative metadata, no file distributed.',
    sourceNotice: 'Source: public-domain work or freely distributable resource.',
  },
  reader: {
    title: 'Reader',
    page: 'Page',
    of: 'of',
    zoomIn: 'Zoom in',
    zoomOut: 'Zoom out',
    fullscreen: 'Fullscreen',
    exitFullscreen: 'Exit fullscreen',
    search: 'Search in document',
    searchPlaceholder: 'Search…',
    noMatches: 'No match in the document.',
    resume: 'Resume reading',
    lastPosition: 'Last saved position',
    close: 'Close',
    notAvailable: 'Online reading is not available for this resource.',
    loading: 'Loading document…',
    error: 'Unable to load the document.',
    prev: 'Previous chapter',
    next: 'Next chapter',
  },
  account: {
    title: 'My space',
    myBooks: 'My books',
    myDocuments: 'My documents',
    myPurchases: 'My purchases',
    favorites: 'My favourites',
    recentReads: 'Recent reads',
    progress: 'Reading progress',
    downloads: 'Authorised downloads',
    empty: 'Nothing here yet.',
    signInHint: 'Sign in to sync your favourites and reading progress.',
    addFavorite: 'Add to favourites',
    removeFavorite: 'Remove from favourites',
  },
  common: {
    result: 'Result',
    reference: 'Reference',
    version: 'Version',
    disclaimer:
      'Leopardo Library references public-domain works and freely distributable resources. Always check reuse conditions before any professional use.',
    notFound: 'Resource not found',
    notFoundHint:
      'This resource does not exist or has been removed. Browse the library to find what you need.',
    backToLibrary: 'Back to the library',
    loading: 'Loading…',
  },
  admin: ADMIN_COPY.en,
};

const tr: LibraryCopy = {
  meta: {
    title: 'Leopardo Library — dijital kütüphane',
    description:
      'Herkese açık ve profesyonel dijital kütüphane: kitaplar, rehberler, kılavuzlar ve belgeler; ücretsiz veya profesyonel erişimle, Fransızca, İngilizce, Arapça ve Türkçe.',
  },
  nav: { label: 'Kütüphane' },
  categories: {
    'livres-ebooks': 'Kitaplar ve e-kitaplar',
    'education-formation': 'Eğitim ve öğretim',
    'sciences-technologie': 'Bilim ve teknoloji',
    'informatique-ia': 'Bilişim ve yapay zekâ',
    'entrepreneuriat-gestion': 'Girişimcilik ve yönetim',
    'agriculture-elevage': 'Tarım ve hayvancılık',
    'commerce-import-export': 'Ticaret ve ithalat-ihracat',
    'voyage-tourisme': 'Seyahat ve turizm',
    'guides-professionnels': 'Profesyonel rehberler',
    'manuels-techniques': 'Teknik kılavuzlar',
    'documents-pratiques': 'Pratik belgeler',
    litterature: 'Edebiyat',
  },
  home: {
    title: 'Leopardo Library',
    subtitle:
      'Modern bir dijital kütüphane: çevrimiçi okunacak veya indirilecek kitaplar, rehberler ve belgeler — ücretsiz ya da erişim haklarınıza göre.',
    searchPlaceholder: 'Başlık, yazar veya anahtar kelime arayın…',
    popular: 'Popüler kitaplar',
    newArrivals: 'Yeni gelenler',
    recentlyAdded: 'Yeni eklenen belgeler',
    freeResources: 'Ücretsiz kaynaklar',
    categories: 'Kategoriler',
    editorial: 'Editör seçkisi',
    browseAll: 'Tüm kütüphaneye göz at',
    resourcesCount: 'kaynak mevcut',
    noResults: 'Aramanızla eşleşen kaynak yok.',
  },
  filters: {
    allCategories: 'Tüm kategoriler',
    allLanguages: 'Tüm diller',
    allAccess: 'Tüm erişimler',
    allTypes: 'Tüm türler',
    freeOnly: 'Yalnızca ücretsiz',
    apply: 'Filtrele',
    reset: 'Sıfırla',
  },
  access: {
    free: 'Ücretsiz',
    read_only: 'Ücretsiz okuma',
    purchase: 'Tek seferlik satın alma',
    subscription: 'Abonelik',
    private: 'Özel erişim',
  },
  types: {
    book: 'Kitap',
    ebook: 'E-kitap',
    guide: 'Rehber',
    manual: 'Kılavuz',
    report: 'Rapor',
    article: 'Makale',
  },
  detail: {
    author: 'Yazar',
    publisher: 'Yayıncı',
    publishedYear: 'Yayın yılı',
    pages: 'Sayfa',
    language: 'Dil',
    category: 'Kategori',
    documentType: 'Belge türü',
    access: 'Erişim koşulları',
    license: 'Lisans',
    rightsHolder: 'Hak sahibi',
    format: 'Mevcut biçim',
    readOnline: 'Çevrimiçi oku',
    download: 'İndir',
    downloadDisabled: 'Bu kaynak için indirme izni yok.',
    buy: 'Satın al',
    subscribe: 'Aboneliğe dahil',
    privateAccess: 'Bir kuruluşa özel — erişim sunucu tarafında doğrulanır.',
    preview: 'İçerik önizlemesi',
    related: 'Benzer kaynaklar',
    backToLibrary: 'Kütüphaneye dön',
    unknown: 'Belirtilmemiş',
    demoNotice: 'Demo kaydı: örnek meta veriler, dosya dağıtılmaz.',
    sourceNotice: 'Kaynak: kamu malı eser veya serbestçe dağıtılabilir kaynak.',
  },
  reader: {
    title: 'Okuyucu',
    page: 'Sayfa',
    of: '/',
    zoomIn: 'Yakınlaştır',
    zoomOut: 'Uzaklaştır',
    fullscreen: 'Tam ekran',
    exitFullscreen: 'Tam ekrandan çık',
    search: 'Belgede ara',
    searchPlaceholder: 'Ara…',
    noMatches: 'Belgede sonuç yok.',
    resume: 'Okumaya devam et',
    lastPosition: 'Kaydedilen son konum',
    close: 'Kapat',
    notAvailable: 'Bu kaynak için çevrimiçi okuma mevcut değil.',
    loading: 'Belge yükleniyor…',
    error: 'Belge yüklenemedi.',
    prev: 'Önceki bölüm',
    next: 'Sonraki bölüm',
  },
  account: {
    title: 'Alanım',
    myBooks: 'Kitaplarım',
    myDocuments: 'Belgelerim',
    myPurchases: 'Satın alımlarım',
    favorites: 'Favorilerim',
    recentReads: 'Son okumalar',
    progress: 'Okuma ilerlemesi',
    downloads: 'İzinli indirmeler',
    empty: 'Henüz bir şey yok.',
    signInHint: 'Favorilerinizi ve ilerlemenizi eşitlemek için giriş yapın.',
    addFavorite: 'Favorilere ekle',
    removeFavorite: 'Favorilerden çıkar',
  },
  common: {
    result: 'Sonuç',
    reference: 'Kaynak',
    version: 'Sürüm',
    disclaimer:
      'Leopardo Library kamu malı eserleri ve serbestçe dağıtılabilir kaynakları listeler. Profesyonel kullanımdan önce yeniden kullanım koşullarını her zaman kontrol edin.',
    notFound: 'Kaynak bulunamadı',
    notFoundHint:
      'Bu kaynak mevcut değil veya kaldırıldı. Aradığınızı bulmak için kütüphaneye göz atın.',
    backToLibrary: 'Kütüphaneye dön',
    loading: 'Yükleniyor…',
  },
  admin: ADMIN_COPY.tr,
};

const ar: LibraryCopy = {
  meta: {
    title: 'Leopardo Library — مكتبة رقمية',
    description:
      'مكتبة رقمية عامة ومهنية: كتب وأدلة وكتيبات ووثائق، متاحة مجانًا أو بوصول مهني، بالفرنسية والإنجليزية والعربية والتركية.',
  },
  nav: { label: 'المكتبة' },
  categories: {
    'livres-ebooks': 'كتب وكتب إلكترونية',
    'education-formation': 'التعليم والتدريب',
    'sciences-technologie': 'العلوم والتقنية',
    'informatique-ia': 'الحوسبة والذكاء الاصطناعي',
    'entrepreneuriat-gestion': 'ريادة الأعمال والإدارة',
    'agriculture-elevage': 'الزراعة وتربية الحيوانات',
    'commerce-import-export': 'التجارة والاستيراد والتصدير',
    'voyage-tourisme': 'السفر والسياحة',
    'guides-professionnels': 'أدلة مهنية',
    'manuels-techniques': 'كتيبات تقنية',
    'documents-pratiques': 'وثائق عملية',
    litterature: 'الأدب',
  },
  home: {
    title: 'Leopardo Library',
    subtitle:
      'مكتبة رقمية حديثة: كتب وأدلة ووثائق للقراءة عبر الإنترنت أو للتنزيل، مجانًا أو وفق حقوق وصولك.',
    searchPlaceholder: 'ابحث عن عنوان أو مؤلف أو كلمة مفتاحية…',
    popular: 'الكتب الأكثر شعبية',
    newArrivals: 'وصل حديثًا',
    recentlyAdded: 'وثائق أُضيفت حديثًا',
    freeResources: 'موارد مجانية',
    categories: 'الفئات',
    editorial: 'اختيار تحريري',
    browseAll: 'تصفّح المكتبة كاملة',
    resourcesCount: 'مورد متاح',
    noResults: 'لا يوجد مورد يطابق بحثك.',
  },
  filters: {
    allCategories: 'كل الفئات',
    allLanguages: 'كل اللغات',
    allAccess: 'كل أنواع الوصول',
    allTypes: 'كل الأنواع',
    freeOnly: 'المجاني فقط',
    apply: 'تصفية',
    reset: 'إعادة تعيين',
  },
  access: {
    free: 'مجاني',
    read_only: 'قراءة مجانية',
    purchase: 'شراء لمرة واحدة',
    subscription: 'اشتراك',
    private: 'وصول خاص',
  },
  types: {
    book: 'كتاب',
    ebook: 'كتاب إلكتروني',
    guide: 'دليل',
    manual: 'كتيب',
    report: 'تقرير',
    article: 'مقال',
  },
  detail: {
    author: 'المؤلف',
    publisher: 'الناشر',
    publishedYear: 'سنة النشر',
    pages: 'الصفحات',
    language: 'اللغة',
    category: 'الفئة',
    documentType: 'نوع الوثيقة',
    access: 'شروط الوصول',
    license: 'الترخيص',
    rightsHolder: 'صاحب الحقوق',
    format: 'الصيغة المتاحة',
    readOnline: 'اقرأ عبر الإنترنت',
    download: 'تنزيل',
    downloadDisabled: 'التنزيل غير مسموح لهذا المورد.',
    buy: 'اشترِ',
    subscribe: 'مضمّن في الاشتراك',
    privateAccess: 'مخصّص لمؤسسة — يتم التحقق من الوصول على الخادم.',
    preview: 'معاينة المحتوى',
    related: 'موارد مشابهة',
    backToLibrary: 'العودة إلى المكتبة',
    unknown: 'غير محدّد',
    demoNotice: 'مدخل تجريبي: بيانات وصفية توضيحية، لا يتم توزيع أي ملف.',
    sourceNotice: 'المصدر: عمل في الملك العام أو مورد قابل للتوزيع بحرية.',
  },
  reader: {
    title: 'القارئ',
    page: 'صفحة',
    of: 'من',
    zoomIn: 'تكبير',
    zoomOut: 'تصغير',
    fullscreen: 'ملء الشاشة',
    exitFullscreen: 'الخروج من ملء الشاشة',
    search: 'ابحث في الوثيقة',
    searchPlaceholder: 'ابحث…',
    noMatches: 'لا نتائج في الوثيقة.',
    resume: 'متابعة القراءة',
    lastPosition: 'آخر موضع محفوظ',
    close: 'إغلاق',
    notAvailable: 'القراءة عبر الإنترنت غير متاحة لهذا المورد.',
    loading: 'جارٍ تحميل المستند…',
    error: 'تعذر تحميل المستند.',
    prev: 'الفصل السابق',
    next: 'الفصل التالي',
  },
  account: {
    title: 'مساحتي',
    myBooks: 'كتبي',
    myDocuments: 'وثائقي',
    myPurchases: 'مشترياتي',
    favorites: 'مفضّلاتي',
    recentReads: 'قراءات حديثة',
    progress: 'تقدّم القراءة',
    downloads: 'التنزيلات المسموح بها',
    empty: 'لا يوجد شيء بعد.',
    signInHint: 'سجّل الدخول لمزامنة مفضّلاتك وتقدّم قراءتك.',
    addFavorite: 'أضف إلى المفضّلة',
    removeFavorite: 'أزل من المفضّلة',
  },
  common: {
    result: 'النتيجة',
    reference: 'المرجع',
    version: 'الإصدار',
    disclaimer:
      'تُدرج Leopardo Library أعمالًا في الملك العام وموارد قابلة للتوزيع بحرية. تحقّق دائمًا من شروط إعادة الاستخدام قبل أي استخدام مهني.',
    notFound: 'المورد غير موجود',
    notFoundHint:
      'هذا المورد غير موجود أو تمت إزالته. تصفّح المكتبة للعثور على ما تحتاجه.',
    backToLibrary: 'العودة إلى المكتبة',
    loading: 'جارٍ التحميل…',
  },
  admin: ADMIN_COPY.ar,
};

const CATALOGS: Record<LibraryLocale, LibraryCopy> = { fr, en, tr, ar };

/** Retourne la copie complète du module pour une locale (repli : fr). */
export function getLibraryCopy(locale: string): LibraryCopy {
  return CATALOGS[locale as LibraryLocale] ?? CATALOGS.fr;
}
