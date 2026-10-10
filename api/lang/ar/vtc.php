<?php

declare(strict_types=1);

// BC-34 VTC (VTC-02..06, #8358-#8362) — رسائل وحدة سيارات الأجرة/VTC
// (PA2-I18N-007: لا نصوص ثابتة، كل شيء يمر عبر هذا الفهرس).
return [
    'ride_creation_failed' => 'تعذر إنشاء رحلة VTC بعد :attempts محاولات.',
    'availability_locked' => 'لا يمكن تغيير التوفر من الحالة :status (أنهِ الرحلة أو اتصل بالمشغّل).',
    'purge_positions_description' => 'تنقية GDPR لمواقع سائقي VTC بعد انتهاء مدة الاحتفاظ (قابلة للتكرار ومدقّقة).',
    'purge_positions_done_log' => 'vtc:purge-positions — تم تنفيذ تنقية GDPR لمواقع السائقين.',
    'purge_positions_done_cli' => 'اكتملت تنقية VTC: حُذف :deleted موقعًا (احتفاظ :days يومًا، عند :cutoff).',
    'driver_delete_has_rides' => 'سائق لديه رحلات: لا يمكن الحذف (يُفضَّل التعليق — يُحتفظ بالسجل).',
    'driver_user_not_found' => 'حساب الموظف غير موجود في هذا المستأجر.',
    'driver_vehicle_not_found' => 'المركبة غير موجودة في هذا المستأجر.',
    'fare_profile_delete_in_use' => 'ملف التعرفة مرتبط برحلات: لا يمكن الحذف (تحتفظ الرحلات بعرض سعرها التاريخي).',
    'vehicle_delete_assigned' => 'المركبة ما زالت مخصصة لسائق: أزل التخصيص قبل الحذف.',
    'vehicle_plate_taken' => 'اللوحة :plate مسجلة مسبقًا لهذا المستأجر.',
];
