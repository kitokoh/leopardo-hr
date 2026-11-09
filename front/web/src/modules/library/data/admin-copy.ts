/**
 * Leopardo Library — copie i18n de l'ADMINISTRATION des ressources (#9241).
 *
 * Le module est AUTONOME : sa copie vit dans `data/` plutôt que dans le
 * catalogue partagé `shared/i18n` (propagé au backend et au mobile, où
 * l'administration Library n'existe pas) — même convention que
 * `library/data/copy.ts`, `toolbox/data/copy.ts` et `public-portal/data/copy.ts`.
 *
 * Les 4 locales sont obligatoires (fr/en/tr/ar) et l'arabe est RTL.
 */

import type { AppLocale } from '@/lib/i18n';

export type LibraryAdminCopy = {
  title: string;
  subtitle: string;
  loading: string;
  empty: string;
  create: string;
  creating: string;
  save: string;
  saving: string;
  cancel: string;
  edit: string;
  remove: string;
  upload: string;
  uploading: string;
  publish: string;
  unpublish: string;
  archive: string;
  statusDraft: string;
  statusPending: string;
  statusPublished: string;
  statusArchived: string;
  fieldTitle: string;
  fieldDescription: string;
  fieldType: string;
  fieldLanguage: string;
  fieldAccess: string;
  fieldPrice: string;
  fieldRightsHolder: string;
  fieldAllowDownload: string;
  fieldFeatured: string;
  hasFile: string;
  noFile: string;
  errorGeneric: string;
  errorForbidden: string;
  confirmDelete: string;
};

export const LIBRARY_ADMIN_COPY: Record<AppLocale, LibraryAdminCopy> = {
  fr: {
    title: 'Bibliothèque — administration',
    subtitle: 'Créez, éditez, téléversez et publiez les ressources de votre entreprise.',
    loading: 'Chargement des ressources…',
    empty: 'Aucune ressource pour le moment. Créez la première.',
    create: 'Nouvelle ressource',
    creating: 'Création…',
    save: 'Enregistrer',
    saving: 'Enregistrement…',
    cancel: 'Annuler',
    edit: 'Modifier',
    remove: 'Supprimer',
    upload: 'Téléverser le fichier',
    uploading: 'Téléversement…',
    publish: 'Publier',
    unpublish: 'Repasser en brouillon',
    archive: 'Archiver',
    statusDraft: 'Brouillon',
    statusPending: 'En attente',
    statusPublished: 'Publié',
    statusArchived: 'Archivé',
    fieldTitle: 'Titre',
    fieldDescription: 'Description',
    fieldType: 'Type de document',
    fieldLanguage: 'Langue',
    fieldAccess: 'Modèle d’accès',
    fieldPrice: 'Prix (centimes)',
    fieldRightsHolder: 'Ayant droit',
    fieldAllowDownload: 'Téléchargement autorisé',
    fieldFeatured: 'Mise en avant',
    hasFile: 'Fichier présent',
    noFile: 'Aucun fichier',
    errorGeneric: 'Une erreur est survenue. Réessayez.',
    errorForbidden: 'Vous n’avez pas les droits pour cette action.',
    confirmDelete: 'Supprimer définitivement cette ressource ?',
  },
  en: {
    title: 'Library — administration',
    subtitle: 'Create, edit, upload and publish your company resources.',
    loading: 'Loading resources…',
    empty: 'No resources yet. Create the first one.',
    create: 'New resource',
    creating: 'Creating…',
    save: 'Save',
    saving: 'Saving…',
    cancel: 'Cancel',
    edit: 'Edit',
    remove: 'Delete',
    upload: 'Upload file',
    uploading: 'Uploading…',
    publish: 'Publish',
    unpublish: 'Back to draft',
    archive: 'Archive',
    statusDraft: 'Draft',
    statusPending: 'Pending',
    statusPublished: 'Published',
    statusArchived: 'Archived',
    fieldTitle: 'Title',
    fieldDescription: 'Description',
    fieldType: 'Document type',
    fieldLanguage: 'Language',
    fieldAccess: 'Access model',
    fieldPrice: 'Price (minor units)',
    fieldRightsHolder: 'Rights holder',
    fieldAllowDownload: 'Download allowed',
    fieldFeatured: 'Featured',
    hasFile: 'File present',
    noFile: 'No file',
    errorGeneric: 'Something went wrong. Please retry.',
    errorForbidden: 'You are not allowed to perform this action.',
    confirmDelete: 'Permanently delete this resource?',
  },
  tr: {
    title: 'Kütüphane — yönetim',
    subtitle: 'Şirket kaynaklarınızı oluşturun, düzenleyin, yükleyin ve yayınlayın.',
    loading: 'Kaynaklar yükleniyor…',
    empty: 'Henüz kaynak yok. İlkini oluşturun.',
    create: 'Yeni kaynak',
    creating: 'Oluşturuluyor…',
    save: 'Kaydet',
    saving: 'Kaydediliyor…',
    cancel: 'İptal',
    edit: 'Düzenle',
    remove: 'Sil',
    upload: 'Dosya yükle',
    uploading: 'Yükleniyor…',
    publish: 'Yayınla',
    unpublish: 'Taslağa döndür',
    archive: 'Arşivle',
    statusDraft: 'Taslak',
    statusPending: 'Beklemede',
    statusPublished: 'Yayınlandı',
    statusArchived: 'Arşivlendi',
    fieldTitle: 'Başlık',
    fieldDescription: 'Açıklama',
    fieldType: 'Belge türü',
    fieldLanguage: 'Dil',
    fieldAccess: 'Erişim modeli',
    fieldPrice: 'Fiyat (küçük birim)',
    fieldRightsHolder: 'Hak sahibi',
    fieldAllowDownload: 'İndirmeye izin ver',
    fieldFeatured: 'Öne çıkan',
    hasFile: 'Dosya mevcut',
    noFile: 'Dosya yok',
    errorGeneric: 'Bir hata oluştu. Tekrar deneyin.',
    errorForbidden: 'Bu işlem için yetkiniz yok.',
    confirmDelete: 'Bu kaynak kalıcı olarak silinsin mi?',
  },
  ar: {
    title: 'المكتبة — الإدارة',
    subtitle: 'أنشئ موارد شركتك وحرّرها وارفعها وانشرها.',
    loading: 'جارٍ تحميل الموارد…',
    empty: 'لا توجد موارد بعد. أنشئ الأول.',
    create: 'مورد جديد',
    creating: 'جارٍ الإنشاء…',
    save: 'حفظ',
    saving: 'جارٍ الحفظ…',
    cancel: 'إلغاء',
    edit: 'تعديل',
    remove: 'حذف',
    upload: 'رفع الملف',
    uploading: 'جارٍ الرفع…',
    publish: 'نشر',
    unpublish: 'إرجاع إلى المسودة',
    archive: 'أرشفة',
    statusDraft: 'مسودة',
    statusPending: 'قيد الانتظار',
    statusPublished: 'منشور',
    statusArchived: 'مؤرشف',
    fieldTitle: 'العنوان',
    fieldDescription: 'الوصف',
    fieldType: 'نوع المستند',
    fieldLanguage: 'اللغة',
    fieldAccess: 'نموذج الوصول',
    fieldPrice: 'السعر (بالسنت)',
    fieldRightsHolder: 'صاحب الحقوق',
    fieldAllowDownload: 'السماح بالتنزيل',
    fieldFeatured: 'مميّز',
    hasFile: 'الملف موجود',
    noFile: 'لا يوجد ملف',
    errorGeneric: 'حدث خطأ. حاول مرة أخرى.',
    errorForbidden: 'ليست لديك صلاحية لهذا الإجراء.',
    confirmDelete: 'حذف هذا المورد نهائيًا؟',
  },
};

