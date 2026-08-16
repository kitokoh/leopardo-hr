// ============================================================
// Error messages localisés (core) — issue #4408.
//
// L'ApiClient (core) construisait ses messages de repli en FR codé en dur
// (« Impossible de se connecter au serveur », « Fonction bientôt
// disponible », ...) affichés verbatim dans les 4 locales de toutes les
// apps. Ce catalogue centralise ces replis par code + locale appareil
// (fr/en/tr/ar), sans dépendre des ARB des apps (le core n'en a pas).
//
// NB : quand le BACKEND répond (JSON avec `localized_message`/`message`),
// le message serveur (déjà localisé) est utilisé tel quel — ce catalogue
// ne couvre que les erreurs sans réponse (réseau/timeout/statut nu).
// ============================================================

import 'dart:ui' show PlatformDispatcher;

/// Locale UI de l'appareil (fr/en/tr/ar, repli fr) — miroir de
/// [deviceIntlDateLocale]/[deviceIntlNumberLocale].
String get deviceUiLocale {
  final language = PlatformDispatcher.instance.locale.languageCode.toLowerCase();
  return switch (language) {
    'ar' => 'ar',
    'tr' => 'tr',
    'en' => 'en',
    _ => 'fr',
  };
}

const Map<String, Map<String, String>> _errorCatalog = {
  'CONNECTION': {
    'fr': 'Impossible de se connecter au serveur',
    'en': 'Unable to connect to the server',
    'tr': 'Sunucuya bağlanılamıyor',
    'ar': 'تعذر الاتصال بالخادم',
  },
  'NOT_IMPLEMENTED': {
    'fr': 'Fonction bientôt disponible',
    'en': 'Feature coming soon',
    'tr': 'Özellik yakında kullanıma sunulacak',
    'ar': 'الميزة قريبًا',
  },
  'ACCOUNT_SUSPENDED': {
    'fr': 'Compte suspendu - contactez votre employeur',
    'en': 'Account suspended - contact your employer',
    'tr': 'Hesap askıya alındı - işvereninizle iletişime geçin',
    'ar': 'الحساب موقوف - تواصل مع جهة عملك',
  },
  'FORBIDDEN': {
    'fr': 'Action non autorisée pour votre profil',
    'en': 'Action not allowed for your profile',
    'tr': 'Profiliniz için izin verilmeyen işlem',
    'ar': 'إجراء غير مسموح لملفك الشخصي',
  },
  'CONNECTION_TIMEOUT': {
    'fr': 'Délai de connexion dépassé',
    'en': 'Connection timed out',
    'tr': 'Bağlantı zaman aşımı',
    'ar': 'انتهت مهلة الاتصال',
  },
  'RECEIVE_TIMEOUT': {
    'fr': 'Le serveur met trop de temps à répondre',
    'en': 'The server is taking too long to respond',
    'tr': 'Sunucu yanıt vermekte çok yavaş',
    'ar': 'الخادم يستغرق وقتًا طويلاً للرد',
  },
  'CONNECTION_ERROR': {
    'fr': 'Connexion indisponible - vérifiez internet ou l\'URL API',
    'en': 'Connection unavailable - check your internet or the API URL',
    'tr': 'Bağlantı kullanılamıyor - interneti veya API adresini kontrol edin',
    'ar': 'الاتصال غير متاح - تحقق من الإنترنت أو عنوان API',
  },
  'DOWNLOAD_FAILED': {
    'fr': 'Échec du téléchargement',
    'en': 'Download failed',
    'tr': 'İndirme başarısız',
    'ar': 'فشل التنزيل',
  },
};

/// Message localisé pour un code d'erreur connu (locale appareil sinon fr).
String localizedErrorCode(String code, [String? locale]) {
  final lang = (locale ?? deviceUiLocale).toLowerCase();
  return _errorCatalog[code]?[lang] ??
      _errorCatalog[code]?['fr'] ??
      code;
}
