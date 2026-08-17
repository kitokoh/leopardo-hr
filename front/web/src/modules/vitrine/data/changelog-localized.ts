import type { AppLocale } from '@/lib/i18n';

/**
 * #4610 (réouverture audit 2026-08-17) : contenu des releases réellement
 * localisé ×4. `changelogLocalized` porte les traductions en/tr/ar ; la
 * version FR (`publicChangelogReleases`) reste la source canonique et le
 * fallback. `getChangelogReleases(locale)` fusionne : override si présent,
 * sinon FR — aucune release ne peut disparaître.
 */
export const changelogLocalized: Record<
  string,
  Partial<Record<AppLocale, { title: string; bullets: string[] }>>
> = {
  '4.24.0': {
    en: {
      title: 'First public release — security, CI and quality',
      bullets: [
        'Full security hardening: SAML/OIDC SSO encrypted at rest, constrained uploads, httpOnly auth cookie, kiosk XSS eliminated, mobile JWT out of Hive.',
        'Backend coverage 71% measured in CI (per-module gate, Payroll ≥ 80% target); 1,917 tests, 424 documented API endpoints.',
        'Standardized pagination and response contracts (Growth, Cameras, DeviceToken, CabinetShare).',
        'CI: 69 actions pinned to SHAs, blocking dependency-review, anti-stale-SHA gate, A-2 secret scan actually executed.',
      ],
    },
    tr: {
      title: 'İlk genel sürüm — güvenlik, CI ve kalite',
      bullets: [
        'Kapsamlı güvenlik: SAML/OIDC SSO bekleyen veride şifreli, kısıtlı yüklemeler, httpOnly auth çerezi, kiosk XSS giderildi, mobil JWT Hive dışında.',
        'CI ölçümünde arka uç kapsamı %71 (modül başına kapı, Payroll ≥ %80 hedef); 1.917 test, 424 belgelenmiş API uç noktası.',
        'Standart sayfalama ve yanıt sözleşmeleri (Growth, Cameras, DeviceToken, CabinetShare).',
        'CI: SHA\'ye sabitlenmiş 69 action, engelleyen dependency-review, stale-SHA karşıtı kapı, gerçekten çalışan A-2 gizli tarama.',
      ],
    },
    ar: {
      title: 'أول إصدار عام — الأمان وCI والجودة',
      bullets: [
        'تعزيز أمني شامل: SSO SAML/OIDC مشفّر عند التخزين، رفع ملفات مقيد، كوكي مصادقة httpOnly، القضاء على XSS في الكشك، JWT الجوال خارج Hive.',
        'تغطية الواجهة الخلفية 71% مقاسة في CI (بوابة لكل وحدة، Payroll ≥ 80% هدف)؛ 1,917 اختبارًا، 424 نقطة نهاية API موثقة.',
        'توحيد الترقيم وعقود الاستجابة (Growth، Cameras، DeviceToken، CabinetShare).',
        'CI: 69 إجراءات مثبتة على SHA، dependency-review حاجز، بوابة ضد الـ stale SHA، فحص أسرار A-2 منفذ فعليًا.',
      ],
    },
  },
  '4.23.5': {
    en: {
      title: 'Production fixes — cold start, E2E and payroll',
      bullets: [
        'Render anti-cold-start warm-up for staging E2E (Playwright 15 s timeout).',
        'Vercel robustness: ignoreCommand with previous-SHA fallback.',
        'Payroll fixes: invoice number actually persisted, Render workers/scheduler started, salary_advances FK index.',
      ],
    },
    tr: {
      title: 'Üretim düzeltmeleri — cold start, E2E ve bordro',
      bullets: [
        'Staging E2E için Render anti-cold-start ısıtma (Playwright 15 sn zaman aşımı).',
        'Vercel sağlamlığı: önceki SHA\'ya düşen ignoreCommand.',
        'Bordro düzeltmeleri: fatura numarası gerçekten kaydediliyor, Render worker/scheduler başlatıldı, salary_advances FK dizini.',
      ],
    },
    ar: {
      title: 'إصلاحات الإنتاج — بدء التشغيل البارد وE2E والرواتب',
      bullets: [
        'إحماء مضاد لبدء التشغيل البارد في Render لاختبارات E2E التجريبية (مهلة Playwright 15 ثانية).',
        'مرونة Vercel: ignoreCommand مع تراجع إلى SHA السابق.',
        'إصلاحات الرواتب: رقم الفاتورة يُحفظ فعليًا، تشغيل العمال/المجدول في Render، فهرس المفتاح الأجنبي salary_advances.',
      ],
    },
  },
  '4.23.4': {
    en: {
      title: 'Dart compilation fixed on all 3 mobile apps + hardened CI',
      bullets: [
        'The main.dart of leopardo_employee, leopardo_manager and leopardo_hr declared main() without async while the body awaited SentryFlutter.init — aligned on Future<void> main() async (was blocking the Flutter job on main).',
        'CI/CD: supply-chain hardening (SHA pinning of third-party actions) and PHP/Flutter setup deduplicated via reusable composite actions (~360 lines removed).',
      ],
    },
    tr: {
      title: '3 mobil uygulamada da Dart derlemesi düzeltildi + güçlendirilmiş CI',
      bullets: [
        'leopardo_employee, leopardo_manager ve leopardo_hr main.dart\'leri, gövde SentryFlutter.init beklerken main() öğesini async olmadan tanımlıyordu — Future<void> main() async ile uyumlu hale getirildi (main üzerindeki Flutter işini engelliyordu).',
        'CI/CD: tedarik zinciri güçlendirmesi (üçüncü taraf action\'ların SHA sabitlemesi) ve PHP/Flutter kurulumu yeniden kullanılabilir composite action\'larla tekilleştirildi (~360 satır eksildi).',
      ],
    },
    ar: {
      title: 'إصلاح ترجمة Dart في التطبيقات الجوالة الثلاثة + تقوية CI',
      bullets: [
        'كانت ملفات main.dart لتطبيقات leopardo_employee وleopardo_manager وleopardo_hr تعرّف main() بدون async بينما ينتظر الجسم SentryFlutter.init — تمت مواءمتها مع Future<void> main() async (كانت تعطل مهمة Flutter على main).',
        'CI/CD: تقوية سلسلة التوريد (تثبيت SHA لإجراءات الأطراف الثالثة) وإزالة ازدواجية إعداد PHP/Flutter عبر إجراءات مركبة قابلة لإعادة الاستخدام (~360 سطرًا أقل).',
      ],
    },
  },
  '4.23.3': {
    en: {
      title: '34 Dependabot alerts resolved (11 high, 16 moderate, 7 low)',
      bullets: [
        'api (composer): symfony/yaml 8.0.8 → 8.1.1 (ReDoS Parser::cleanup); form-data fixed (CRLF injection).',
        'Vitrine and admin: npm audit fix (form-data, ws, js-yaml), postcss pinned, vite 6.4.3 (dev-server SSRF, path traversal).',
        'web-offline: Next.js 16.2.10 + ESLint 9 (SSRF, XSS, cache poisoning); npm/composer audit at 0 residual vulnerabilities.',
      ],
    },
    tr: {
      title: '34 Dependabot uyarısı çözüldü (11 yüksek, 16 orta, 7 düşük)',
      bullets: [
        'api (composer): symfony/yaml 8.0.8 → 8.1.1 (ReDoS Parser::cleanup); form-data düzeltildi (CRLF enjeksiyonu).',
        'Vitrine ve admin: npm audit fix (form-data, ws, js-yaml), postcss sabitlendi, vite 6.4.3 (dev-server SSRF, path traversal).',
        'web-offline: Next.js 16.2.10 + ESLint 9 (SSRF, XSS, cache poisoning); npm/composer denetiminde 0 kalıntı güvenlik açığı.',
      ],
    },
    ar: {
      title: 'حل 34 تنبيه Dependabot (11 حرجة و16 متوسطة و7 منخفضة)',
      bullets: [
        'api (composer): symfony/yaml 8.0.8 ← 8.1.1 (ReDoS Parser::cleanup)؛ إصلاح form-data (حقن CRLF).',
        'الواجهة والإدارة: npm audit fix (form-data, ws, js-yaml)، تثبيت postcss، vite 6.4.3 (SSRF لخادم التطوير، path traversal).',
        'web-offline: Next.js 16.2.10 + ESLint 9 (SSRF وXSS وتسميم ذاكرة التخزين المؤقت)؛ تدقيق npm/composer عند 0 ثغرات متبقية.',
      ],
    },
  },
  '4.23.2': {
    en: {
      title: 'CI security: workflow_run injection fixed, explicit permissions',
      bullets: [
        'deploy-main.yml: head_branch is no longer interpolated into a run: shell block (environment variable) + head_repository == github.repository check.',
        'Explicit GITHUB_TOKEN permissions (contents: read) on missing workflows; phpstan-modules.neon now loads Larastan (36 undefined-method errors resolved).',
      ],
    },
    tr: {
      title: 'CI güvenliği: workflow_run enjeksiyonu düzeltildi, açık izinler',
      bullets: [
        'deploy-main.yml: head_branch artık run: shell bloğuna enterpole edilmiyor (ortam değişkeni) + head_repository == github.repository kontrolü.',
        'Eksik iş akışlarında açık GITHUB_TOKEN izinleri (contents: read); phpstan-modules.neon artık Larastan yüklüyor (36 tanımsız yöntem hatası çözüldü).',
      ],
    },
    ar: {
      title: 'أمان CI: إصلاح حقن workflow_run، صلاحيات صريحة',
      bullets: [
        'deploy-main.yml: لم يعد head_branch يُدرج في كتلة run: shell (متغير بيئة) + التحقق head_repository == github.repository.',
        'صلاحيات GITHUB_TOKEN صريحة (contents: read) على سير العمل الناقصة؛ phpstan-modules.neon يحمّل Larastan الآن (حل 36 خطأ استدعاء طريقة غير معرّفة).',
      ],
    },
  },
  '4.23.1': {
    en: {
      title: 'Marketing module (Phase 1): base schema and models',
      bullets: [
        'New DDD module api/app/Modules/Marketing/ with tenant migrations social_accounts and social_posts.',
        'No raw OAuth token stored (encrypted reference to the aggregator profile); tenant-scoped Eloquent models, Feature tests included.',
      ],
    },
    tr: {
      title: 'Pazarlama modülü (1. Aşama): temel şema ve modeller',
      bullets: [
        'social_accounts ve social_posts kiracı geçişleriyle yeni DDD modülü api/app/Modules/Marketing/.',
        'Ham OAuth belirteci saklanmaz (toplayıcı profile şifreli referans); kiracı kapsamlı Eloquent modeller, Feature testleri dahil.',
      ],
    },
    ar: {
      title: 'وحدة التسويق (المرحلة 1): المخطط والنماذج الأساسية',
      bullets: [
        'وحدة DDD جديدة api/app/Modules/Marketing/ مع ترحيلات tenant social_accounts وsocial_posts.',
        'لا يُخزَّن أي رمز OAuth خام (مرجع مشفر لملف المجمع)؛ نماذج Eloquent نطاقها tenant، مع اختبارات Feature.',
      ],
    },
  },
  '4.23.0': {
    en: {
      title: 'Marketing manager role accepted by the API (Marketing module — Phase 0)',
      bullets: [
        'StoreEmployeeRequest/UpdateEmployeeRequest validated manager_role without the marketing value — added to the allowed list (POST/PATCH /employees).',
      ],
    },
    tr: {
      title: 'API tarafından kabul edilen pazarlama yöneticisi rolü (Pazarlama modülü — 0. Aşama)',
      bullets: [
        'StoreEmployeeRequest/UpdateEmployeeRequest, manager_role değerini marketing olmadan doğruluyordu — izin verilen listeye eklendi (POST/PATCH /employees).',
      ],
    },
    ar: {
      title: 'قبول دور مدير التسويق في API (وحدة التسويق — المرحلة 0)',
      bullets: [
        'كان StoreEmployeeRequest/UpdateEmployeeRequest يتحقق من manager_role بدون قيمة marketing — أُضيفت إلى القائمة المسموح بها (POST/PATCH /employees).',
      ],
    },
  },
  '4.22.8': {
    en: {
      title: 'Onboarding drip emails: 3 automatic nurturing emails',
      bullets: [
        'SendTrialDripEmailJob sends on D+1, D+3, D+7 after trial provisioning (bounded retries, trial status checked).',
        'New OnboardingProgress model scoped by company_id + employee_id; mobile onboarding wizard refactored (progress bar, required/error states).',
      ],
    },
    tr: {
      title: 'Onboarding drip e-postaları: 3 otomatik besleme e-postası',
      bullets: [
        'SendTrialDripEmailJob, deneme sağlamasından sonra G+1, G+3, G+7 günlerinde gönderir (sınırlı yeniden deneme, deneme durumu kontrol edilir).',
        'company_id + employee_id ile kapsamlı yeni OnboardingProgress modeli; mobil onboarding sihirbazı yeniden düzenlendi (ilerleme çubuğu, gerekli/hata durumları).',
      ],
    },
    ar: {
      title: 'رسائل البريد الإلكتروني للتغذية التلقائية: 3 رسائل تلقائية',
      bullets: [
        'يرسل SendTrialDripEmailJob في اليوم +1 و+3 و+7 بعد توفير النسخة التجريبية (محاولات محدودة، التحقق من حالة التجربة).',
        'نموذج OnboardingProgress جديد نطاقه company_id + employee_id؛ إعادة هيكلة معالج الإعداد للجوال (شريط تقدم، حالات مطلوب/خطأ).',
      ],
    },
  },
  '4.22.7': {
    en: {
      title: 'Payroll ParseError fixed + breaks deducted from worked hours',
      bullets: [
        'PHP ParseError in 7 CountryRules files fixed (main CI unblocked).',
        'Break minutes (break_minutes) are now deducted from worked hours (AttendanceLog).',
      ],
    },
    tr: {
      title: 'Bordro ParseError düzeltildi + molalar çalışılan saatlerden düşülüyor',
      bullets: [
        '7 CountryRules dosyasındaki PHP ParseError düzeltildi (main CI açıldı).',
        'Mola dakikaları (break_minutes) artık çalışılan saatlerden düşülüyor (AttendanceLog).',
      ],
    },
    ar: {
      title: 'إصلاح ParseError للرواتب + خصم فترات الراحة من ساعات العمل',
      bullets: [
        'إصلاح خطأ ParseError في PHP في 7 ملفات CountryRules (إلغاء حظر CI الرئيسي).',
        'تُخصم دقائق الراحة (break_minutes) الآن من ساعات العمل (AttendanceLog).',
      ],
    },
  },
  '4.22.6': {
    en: {
      title: 'PHPStan Modules Architecture fixed (Absence Eloquent scopes)',
      bullets: [
        'AbsenceService::request() and LeavePolicyController::balances() accessed unknown Eloquent scopes — typings fixed, CI gate green.',
      ],
    },
    tr: {
      title: 'PHPStan Modules Architecture düzeltildi (Absence Eloquent kapsamları)',
      bullets: [
        'AbsenceService::request() ve LeavePolicyController::balances() bilinmeyen Eloquent kapsamlarına erişiyordu — tipler düzeltildi, CI kapısı yeşil.',
      ],
    },
    ar: {
      title: 'إصلاح PHPStan Modules Architecture (نطاقات Eloquent للغياب)',
      bullets: [
        'كان AbsenceService::request() وLeavePolicyController::balances() يصلان إلى نطاقات Eloquent غير معروفة — تم إصلاح الأنواع، بوابة CI خضراء.',
      ],
    },
  },
  '4.22.5': {
    en: {
      title: 'Multi-tenant isolation of queued jobs',
      bullets: [
        'TenantMiddleware correctly sets the PostgreSQL search_path and the current_company binding for jobs.',
        'New App\\Contracts\\Queue\\TenantScopedJob interface: any job requiring tenant context declares tenantCompanyId().',
      ],
    },
    tr: {
      title: 'Kuyruktaki işlerin çok kiracılı izolasyonu',
      bullets: [
        'TenantMiddleware, işler için PostgreSQL search_path ve current_company bağlamasını doğru ayarlar.',
        'Yeni App\\Contracts\\Queue\\TenantScopedJob arayüzü: kiracı bağlamı gerektiren her iş tenantCompanyId() bildirir.',
      ],
    },
    ar: {
      title: 'عزل متعدد المستأجرين للمهام في قائمة الانتظار',
      bullets: [
        'يضبط TenantMiddleware search_path الخاص بـ PostgreSQL وربط current_company للمهام بشكل صحيح.',
        'واجهة جديدة App\\Contracts\\Queue\\TenantScopedJob: كل مهمة تتطلب سياق مستأجر تعلن tenantCompanyId().',
      ],
    },
  },
  '4.22.4': {
    en: {
      title: 'CI: 136 remaining failures resolved (missing company_id)',
      bullets: [
        'Same bug class as 4.22.3 on AbsenceType: company_id (NOT NULL) missing from the canonical model $fillable/casts — fixed, 899 green tests.',
      ],
    },
    tr: {
      title: 'CI: kalan 136 hata çözüldü (eksik company_id)',
      bullets: [
        'AbsenceType üzerinde 4.22.3 ile aynı hata sınıfı: company_id (NOT NULL) kanonik model $fillable/casts alanlarında yoktu — düzeltildi, 899 yeşil test.',
      ],
    },
    ar: {
      title: 'CI: حل 136 خطأً متبقيًا (company_id مفقود)',
      bullets: [
        'نفس فئة الخطأ في 4.22.3 على AbsenceType: company_id (NOT NULL) غائب عن $fillable/casts للنموذج الأساسي — تم الإصلاح، 899 اختبارًا أخضر.',
      ],
    },
  },
  '4.22.3': {
    en: {
      title: 'CI: 160 remaining failures resolved (canonical models)',
      bullets: [
        'The canonical Absence model (via the App\\Models\\Absence shim) declared neither company_id (NOT NULL) in $fillable nor the right cast — aligned, 899 green tests.',
      ],
    },
    tr: {
      title: 'CI: kalan 160 hata çözüldü (kanonik modeller)',
      bullets: [
        'Kanonik Absence modeli (App\\Models\\Absence shim üzerinden) $fillable içinde company_id (NOT NULL) bildirmiyordu ve doğru cast yoktu — uyumlu hale getirildi, 899 yeşil test.',
      ],
    },
    ar: {
      title: 'CI: حل 160 خطأً متبقيًا (النماذج الأساسية)',
      bullets: [
        'نموذج Absence الأساسي (عبر shim App\\Models\\Absence) لم يصرّح بـ company_id (NOT NULL) في $fillable ولا بالتحويل المناسب — تمت المواءمة، 899 اختبارًا أخضر.',
      ],
    },
  },
  '4.22.2': {
    en: {
      title: 'CI: app/Models shims fixed (class_alias + generation)',
      bullets: [
        '75 shim files app/Models/*.php generated in v4.22.0: class_alias resolved an incorrect class name — regenerated, Backend + Coverage green again.',
      ],
    },
    tr: {
      title: 'CI: app/Models shim\'ları düzeltildi (class_alias + üretim)',
      bullets: [
        'v4.22.0\'da üretilen 75 shim dosyası app/Models/*.php: class_alias hatalı bir sınıf adı çözüyordu — yeniden üretildi, Backend + Coverage tekrar yeşil.',
      ],
    },
    ar: {
      title: 'CI: إصلاح ملفات app/Models المؤقتة (class_alias + التوليد)',
      bullets: [
        '75 ملف shim app/Models/*.php أُنشئت في v4.22.0: class_alias كان يحل اسم فئة غير صحيح — أُعيد توليدها، Backend وCoverage أخضران مجددًا.',
      ],
    },
  },
  '4.22.1': {
    en: {
      title: 'Documentation cleanup — mojibake and consistency',
      bullets: [
        'Fixed broken encodings (mojibake) in UML diagrams and other docs — no functional change.',
      ],
    },
    tr: {
      title: 'Belge temizliği — mojibake ve tutarlılık',
      bullets: [
        'UML diyagramlarında ve diğer belgelerde bozuk kodlamalar (mojibake) düzeltildi — işlevsel değişiklik yok.',
      ],
    },
    ar: {
      title: 'تنظيف الوثائق — mojibake والاتساق',
      bullets: [
        'إصلاح الترميزات المكسورة (mojibake) في مخططات UML ووثائق أخرى — لا تغيير وظيفي.',
      ],
    },
  },
  '4.21.1': {
    en: {
      title: 'CI unblocked: migration to escaped apostrophes',
      bullets: [
        'PHP-style escaped apostrophes in a SQL comment of the employee_attendance_preferences migration — the CI blocking all merges is green again.',
      ],
    },
    tr: {
      title: 'CI açıldı: kaçışlı kesme işaretlerine geçiş',
      bullets: [
        'employee_attendance_preferences geçişinin SQL yorumunda PHP tarzı kaçışlı kesme işaretleri — tüm birleştirmeleri engelleyen CI tekrar yeşil.',
      ],
    },
    ar: {
      title: 'إلغاء حظر CI: الانتقال إلى الفواصل العليا الهاربة',
      bullets: [
        'فواصل عليا هاربة بنمط PHP في تعليق SQL لترحيل employee_attendance_preferences — CI الذي كان يعطل كل عمليات الدمج أصبح أخضر مجددًا.',
      ],
    },
  },
  '4.22.0': {
    en: {
      title: 'Architectural cleanup Phase 2 — models, services, FormRequests',
      bullets: [
        '17 orphan models relocated to Core/Tenant/Domain/Models and Core/Auth/Domain/Models; services and FormRequests reorganized per module.',
      ],
    },
    tr: {
      title: 'Mimari temizlik 2. Aşama — modeller, servisler, FormRequest\'ler',
      bullets: [
        '17 yetim model Core/Tenant/Domain/Models ve Core/Auth/Domain/Models altına taşındı; servisler ve FormRequest\'ler modül bazında yeniden düzenlendi.',
      ],
    },
    ar: {
      title: 'التنظيف المعماري المرحلة 2 — النماذج والخدمات وFormRequests',
      bullets: [
        'نقل 17 نموذجًا يتيمًا إلى Core/Tenant/Domain/Models وCore/Auth/Domain/Models؛ إعادة تنظيم الخدمات وFormRequests حسب الوحدة.',
      ],
    },
  },
  '4.21.0': {
    en: {
      title: 'API architectural cleanup — legacy duplicates removed',
      bullets: [
        '90 duplicate controllers removed from app/Http/Controllers/Api/V1 (migrated to app/Modules/*/Interfaces/Api/V1).',
      ],
    },
    tr: {
      title: 'API mimari temizliği — eski kopyalar kaldırıldı',
      bullets: [
        'app/Http/Controllers/Api/V1 altındaki 90 kopya denetleyici kaldırıldı (app/Modules/*/Interfaces/Api/V1 içine taşındı).',
      ],
    },
    ar: {
      title: 'التنظيف المعماري لـ API — إزالة النسخ القديمة',
      bullets: [
        'حذف 90 وحدة تحكم مكررة من app/Http/Controllers/Api/V1 (نُقلت إلى app/Modules/*/Interfaces/Api/V1).',
      ],
    },
  },
  '4.17.4': {
    en: {
      title: 'EdgeSync: EdgeController migrated to the DDD module',
      bullets: [
        'EdgeController and EdgeDownloadController migrated from app/Http/Controllers/Api/V1 to App\\Modules\\EdgeSync\\Interfaces\\Api\\V1.',
        'PHPStan modules raised from level 3 to 5 with targeted suppressions for modules in migration.',
      ],
    },
    tr: {
      title: 'EdgeSync: EdgeController DDD modülüne taşındı',
      bullets: [
        'EdgeController ve EdgeDownloadController, app/Http/Controllers/Api/V1\'den App\\Modules\\EdgeSync\\Interfaces\\Api\\V1\'e taşındı.',
        'PHPStan modules seviyesi, geçişteki modüller için hedefli baskılamalarla 3\'ten 5\'e yükseltildi.',
      ],
    },
    ar: {
      title: 'EdgeSync: نقل EdgeController إلى وحدة DDD',
      bullets: [
        'نقل EdgeController وEdgeDownloadController من app/Http/Controllers/Api/V1 إلى App\\Modules\\EdgeSync\\Interfaces\\Api\\V1.',
        'رفع مستوى PHPStan modules من 3 إلى 5 مع قمعات مستهدفة للوحدات قيد النقل.',
      ],
    },
  },
};

