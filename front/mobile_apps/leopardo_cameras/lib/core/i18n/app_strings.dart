/// Catalogue de chaînes de l'app « Leopardo Caméras » (BC-19 DEVICE, #7426).
///
/// i18n ×4 (fr/ar/tr/en) local à l'app : les chaînes du parcours caméras
/// mobile sont spécifiques à l'app et ne polluent pas le catalogue partagé
/// `leopardo_core`. Résolution : langue préférée persistée
/// (AppPreferences.preferredLanguage), repli locale appareil, repli fr,
/// repli clé brute (pattern leopardo_travel_agent / leopardo_accounting,
/// issue #5236).
class AppStrings {
  const AppStrings._(this._locale);

  final String _locale;

  /// Code de langue résolu ('fr'|'ar'|'tr'|'en').
  String get locale => _locale;

  static const List<String> supportedLocales = ['fr', 'ar', 'tr', 'en'];

  /// Langue « canonique » de l'app, alignée sur le backend (fr par défaut).
  static const String defaultLocale = 'fr';

  bool get isRtl => _locale == 'ar';

  static AppStrings of(String? preferredLocale) {
    return AppStrings._(_normalize(preferredLocale));
  }

  static String _normalize(String? value) {
    if (value == null || value.isEmpty) {
      return defaultLocale;
    }
    final base = value.split(RegExp(r'[-_]')).first.toLowerCase();
    return supportedLocales.contains(base) ? base : defaultLocale;
  }

  String t(String key) {
    return _values[_locale]?[key] ?? _values[defaultLocale]?[key] ?? key;
  }

  /// Jeu de clés de la locale active (utile pour les tests de parité).
  Set<String> get keys => (_values[_locale] ?? const {}).keys.toSet();

  static const Map<String, Map<String, String>> _values = {
    'fr': {
      'appName': 'Leopardo Caméras',
      'loginTitle': 'Connexion',
      'loginSubtitle': 'Surveillance du site : caméras, direct et alertes.',
      'email': 'Adresse e-mail',
      'password': 'Mot de passe',
      'signIn': 'Se connecter',
      'signingIn': 'Connexion…',
      'authError': 'E-mail ou mot de passe incorrect.',
      'authGenericError': 'Impossible de se connecter. Réessayez.',
      'fillRequired': 'Remplissez les champs obligatoires.',
      'logout': 'Se déconnecter',
      'retry': 'Réessayer',
      'refresh': 'Actualiser',
      'loadError': 'Impossible de charger les données.',
      'homeTitle': 'Mur des caméras',
      'homeSubtitle': "Vue d'ensemble des caméras du site.",
      'camerasEmpty': 'Aucune caméra enregistrée.',
      'camerasEmptyHint':
          'Ajoutez une caméra depuis l’espace client web pour la voir ici.',
      'cameraInactive': 'Inactive',
      'cameraActive': 'Active',
      'featureLockedTitle': 'Module Caméras non activé',
      'featureLockedBody':
          "Votre entreprise n'a pas le module Surveillance Caméras. Demandez son activation au responsable de l'espace.",
      'forbiddenTitle': 'Accès refusé',
      'forbiddenBody':
          'Votre rôle ne permet pas de consulter les caméras. Contactez le responsable de votre espace.',
      'eventsTitle': 'Événements',
      'eventsSubtitle': 'Détections de la chaîne vidéo (mouvement, personne…).',
      'eventsEmpty': 'Aucun événement enregistré.',
      'eventSnapshot': 'Instantané disponible',
      'eventNoSnapshot': 'Sans instantané',
      'alertsTitle': 'Alertes',
      'alertsSubtitle': 'Alertes dédupliquées : ouvrir, acquitter, clôturer.',
      'alertsEmpty': 'Aucune alerte.',
      'alertAcknowledge': 'Acquitter',
      'alertResolve': 'Clôturer',
      'alertActionError': "L'action sur l'alerte a échoué.",
      'alertStatus_open': 'Ouverte',
      'alertStatus_acknowledged': 'Acquittée',
      'alertStatus_resolved': 'Clôturée',
      'liveTitle': 'Direct',
      'liveLoading': 'Ouverture du flux…',
      'liveStreamInfo': 'Flux en direct',
      'liveStreamHint':
          'Le flux WebRTC est servi par la chaîne vidéo (MediaMTX, #7424) et signé par un jeton à durée limitée.',
      'liveStreamUrl': 'URL du flux',
      'liveTokenExpiresAt': 'Jeton valable jusqu’au',
      'liveRefreshToken': 'Régénérer le jeton',
      'liveCameraNotFound': 'Caméra introuvable.',
      'location': 'Emplacement',
      'createdAt': 'Créée le',
      'eventType_motion': 'Mouvement',
      'eventType_person': 'Personne',
      'eventType_vehicle': 'Véhicule',
      'eventType_line_crossing': 'Franchissement de ligne',
      'eventType_tamper': 'Sabotage',
      'severity_info': 'Info',
      'severity_warning': 'Avertissement',
      'severity_high': 'Élevée',
      'severity_critical': 'Critique',
      'allCameras': 'Toutes les caméras',
      'noData': 'Aucune donnée',
      'close': 'Fermer',
    },
    'en': {
      'appName': 'Leopardo Cameras',
      'loginTitle': 'Sign in',
      'loginSubtitle': 'Site surveillance: cameras, live view and alerts.',
      'email': 'Email address',
      'password': 'Password',
      'signIn': 'Sign in',
      'signingIn': 'Signing in…',
      'authError': 'Incorrect email or password.',
      'authGenericError': 'Unable to sign in. Try again.',
      'fillRequired': 'Fill in the required fields.',
      'logout': 'Sign out',
      'retry': 'Retry',
      'refresh': 'Refresh',
      'loadError': 'Unable to load data.',
      'homeTitle': 'Camera wall',
      'homeSubtitle': 'Overview of the site cameras.',
      'camerasEmpty': 'No camera registered.',
      'camerasEmptyHint':
          'Add a camera from the web client area to see it here.',
      'cameraInactive': 'Inactive',
      'cameraActive': 'Active',
      'featureLockedTitle': 'Cameras module not enabled',
      'featureLockedBody':
          'Your company does not have the Camera Surveillance module. Ask the workspace owner to enable it.',
      'forbiddenTitle': 'Access denied',
      'forbiddenBody':
          'Your role does not allow viewing cameras. Contact your workspace owner.',
      'eventsTitle': 'Events',
      'eventsSubtitle': 'Video chain detections (motion, person…).',
      'eventsEmpty': 'No event recorded.',
      'eventSnapshot': 'Snapshot available',
      'eventNoSnapshot': 'No snapshot',
      'alertsTitle': 'Alerts',
      'alertsSubtitle': 'Deduplicated alerts: open, acknowledge, resolve.',
      'alertsEmpty': 'No alert.',
      'alertAcknowledge': 'Acknowledge',
      'alertResolve': 'Resolve',
      'alertActionError': 'The alert action failed.',
      'alertStatus_open': 'Open',
      'alertStatus_acknowledged': 'Acknowledged',
      'alertStatus_resolved': 'Resolved',
      'liveTitle': 'Live',
      'liveLoading': 'Opening stream…',
      'liveStreamInfo': 'Live stream',
      'liveStreamHint':
          'The WebRTC stream is served by the video chain (MediaMTX, #7424) and signed with a short-lived token.',
      'liveStreamUrl': 'Stream URL',
      'liveTokenExpiresAt': 'Token valid until',
      'liveRefreshToken': 'Regenerate token',
      'liveCameraNotFound': 'Camera not found.',
      'location': 'Location',
      'createdAt': 'Created on',
      'eventType_motion': 'Motion',
      'eventType_person': 'Person',
      'eventType_vehicle': 'Vehicle',
      'eventType_line_crossing': 'Line crossing',
      'eventType_tamper': 'Tampering',
      'severity_info': 'Info',
      'severity_warning': 'Warning',
      'severity_high': 'High',
      'severity_critical': 'Critical',
      'allCameras': 'All cameras',
      'noData': 'No data',
      'close': 'Close',
    },
    'ar': {
      'appName': 'كاميرات ليوباردو',
      'loginTitle': 'تسجيل الدخول',
      'loginSubtitle': 'مراقبة الموقع: الكاميرات والبث المباشر والتنبيهات.',
      'email': 'البريد الإلكتروني',
      'password': 'كلمة المرور',
      'signIn': 'تسجيل الدخول',
      'signingIn': 'جارٍ تسجيل الدخول…',
      'authError': 'البريد الإلكتروني أو كلمة المرور غير صحيحة.',
      'authGenericError': 'تعذر تسجيل الدخول. حاول مجددًا.',
      'fillRequired': 'املأ الحقول المطلوبة.',
      'logout': 'تسجيل الخروج',
      'retry': 'إعادة المحاولة',
      'refresh': 'تحديث',
      'loadError': 'تعذر تحميل البيانات.',
      'homeTitle': 'جدار الكاميرات',
      'homeSubtitle': 'نظرة عامة على كاميرات الموقع.',
      'camerasEmpty': 'لا توجد كاميرا مسجلة.',
      'camerasEmptyHint':
          'أضف كاميرا من مساحة العميل على الويب لتظهر هنا.',
      'cameraInactive': 'غير نشطة',
      'cameraActive': 'نشطة',
      'featureLockedTitle': 'وحدة الكاميرات غير مفعّلة',
      'featureLockedBody':
          'شركتك لا تملك وحدة مراقبة الكاميرات. اطلب تفعيلها من مسؤول المساحة.',
      'forbiddenTitle': 'تم رفض الوصول',
      'forbiddenBody':
          'دورك لا يسمح بمشاهدة الكاميرات. تواصل مع مسؤول مساحتك.',
      'eventsTitle': 'الأحداث',
      'eventsSubtitle': 'اكتشافات سلسلة الفيديو (حركة، شخص…).',
      'eventsEmpty': 'لا يوجد حدث مسجل.',
      'eventSnapshot': 'لقطة متاحة',
      'eventNoSnapshot': 'بدون لقطة',
      'alertsTitle': 'التنبيهات',
      'alertsSubtitle': 'تنبيهات مُجمّعة: فتح، إقرار، إغلاق.',
      'alertsEmpty': 'لا يوجد تنبيه.',
      'alertAcknowledge': 'إقرار',
      'alertResolve': 'إغلاق',
      'alertActionError': 'فشل الإجراء على التنبيه.',
      'alertStatus_open': 'مفتوح',
      'alertStatus_acknowledged': 'تم الإقرار',
      'alertStatus_resolved': 'مغلق',
      'liveTitle': 'مباشر',
      'liveLoading': 'جارٍ فتح البث…',
      'liveStreamInfo': 'بث مباشر',
      'liveStreamHint':
          'يُقدَّم بث WebRTC عبر سلسلة الفيديو (MediaMTX، ‎#7424) وموقّع برمز صلاحية محدودة.',
      'liveStreamUrl': 'رابط البث',
      'liveTokenExpiresAt': 'الرمز صالح حتى',
      'liveRefreshToken': 'تجديد الرمز',
      'liveCameraNotFound': 'الكاميرا غير موجودة.',
      'location': 'الموقع',
      'createdAt': 'أُنشئت في',
      'eventType_motion': 'حركة',
      'eventType_person': 'شخص',
      'eventType_vehicle': 'مركبة',
      'eventType_line_crossing': 'عبور الخط',
      'eventType_tamper': 'عبث',
      'severity_info': 'معلومة',
      'severity_warning': 'تحذير',
      'severity_high': 'مرتفعة',
      'severity_critical': 'حرجة',
      'allCameras': 'كل الكاميرات',
      'noData': 'لا توجد بيانات',
      'close': 'إغلاق',
    },
    'tr': {
      'appName': 'Leopardo Kameralar',
      'loginTitle': 'Giriş',
      'loginSubtitle': 'Saha gözetimi: kameralar, canlı yayın ve uyarılar.',
      'email': 'E-posta adresi',
      'password': 'Şifre',
      'signIn': 'Giriş yap',
      'signingIn': 'Giriş yapılıyor…',
      'authError': 'E-posta veya şifre hatalı.',
      'authGenericError': 'Giriş yapılamadı. Tekrar deneyin.',
      'fillRequired': 'Zorunlu alanları doldurun.',
      'logout': 'Çıkış yap',
      'retry': 'Tekrar dene',
      'refresh': 'Yenile',
      'loadError': 'Veriler yüklenemedi.',
      'homeTitle': 'Kamera duvarı',
      'homeSubtitle': 'Sahadaki kameraların genel görünümü.',
      'camerasEmpty': 'Kayıtlı kamera yok.',
      'camerasEmptyHint':
          'Burada görmek için web müşteri alanından bir kamera ekleyin.',
      'cameraInactive': 'Pasif',
      'cameraActive': 'Aktif',
      'featureLockedTitle': 'Kamera modülü etkin değil',
      'featureLockedBody':
          'Şirketinizde Kamera Gözetim modülü yok. Etkinleştirilmesini alan sorumlusundan isteyin.',
      'forbiddenTitle': 'Erişim reddedildi',
      'forbiddenBody':
          'Rolünüz kameraları görüntülemeye izin vermiyor. Alan sorumlunuzla iletişime geçin.',
      'eventsTitle': 'Olaylar',
      'eventsSubtitle': 'Video zinciri algılamaları (hareket, kişi…).',
      'eventsEmpty': 'Kayıtlı olay yok.',
      'eventSnapshot': 'Anlık görüntü mevcut',
      'eventNoSnapshot': 'Anlık görüntü yok',
      'alertsTitle': 'Uyarılar',
      'alertsSubtitle':
          'Tekilleştirilmiş uyarılar: açık, onaylandı, kapatıldı.',
      'alertsEmpty': 'Uyarı yok.',
      'alertAcknowledge': 'Onayla',
      'alertResolve': 'Kapat',
      'alertActionError': 'Uyarı işlemi başarısız oldu.',
      'alertStatus_open': 'Açık',
      'alertStatus_acknowledged': 'Onaylandı',
      'alertStatus_resolved': 'Kapatıldı',
      'liveTitle': 'Canlı',
      'liveLoading': 'Yayın açılıyor…',
      'liveStreamInfo': 'Canlı yayın',
      'liveStreamHint':
          'WebRTC yayını video zinciri (MediaMTX, #7424) tarafından sunulur ve süreli bir belirteçle imzalanır.',
      'liveStreamUrl': 'Yayın adresi',
      'liveTokenExpiresAt': 'Belirteç geçerlilik sonu',
      'liveRefreshToken': 'Belirteci yenile',
      'liveCameraNotFound': 'Kamera bulunamadı.',
      'location': 'Konum',
      'createdAt': 'Oluşturulma',
      'eventType_motion': 'Hareket',
      'eventType_person': 'Kişi',
      'eventType_vehicle': 'Araç',
      'eventType_line_crossing': 'Çizgi geçişi',
      'eventType_tamper': 'Kurcalama',
      'severity_info': 'Bilgi',
      'severity_warning': 'Uyarı',
      'severity_high': 'Yüksek',
      'severity_critical': 'Kritik',
      'allCameras': 'Tüm kameralar',
      'noData': 'Veri yok',
      'close': 'Kapat',
    },
  };
}
