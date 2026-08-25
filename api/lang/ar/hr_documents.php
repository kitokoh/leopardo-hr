<?php

return [
    // قائمة مراجعة وثائق ملف الموظف (issue #5326 — gap G3، spec hr-lifecycle §5)

    // أنواع الوثائق
    'type_contract_signed' => 'العقد الموقّع',
    'type_employee_file' => 'ملف الموظف',
    'type_career_decision' => 'قرار المسار المهني',
    'type_departure_record' => 'سجل المغادرة',
    'type_notice_summary' => 'ملخص فترة الإشعار',
    'type_settlement' => 'تسوية نهاية الخدمة',
    'type_certificate' => 'شهادة العمل',
    'type_other' => 'وثيقة أخرى',

    // الحالات
    'status_received' => 'مستلَم',
    'status_uploaded' => 'مرفوع',
    'status_generated' => 'مولَّد',
    'status_missing' => 'ناقص',

    // الرسائل
    'created' => 'تم تسجيل الوثيقة بنجاح.',
    'updated' => 'تم تحديث الوثيقة بنجاح.',
    'deleted' => 'تمت إزالة الوثيقة من الملف.',
    'not_found' => 'الوثيقة غير موجودة في شركتك.',
    'forbidden' => 'يمكن للمدير الرئيسي أو مدير الموارد البشرية فقط إدارة وثائق ملف الموظف.',
    'dossier_complete' => 'الملف مكتمل',
    'dossier_incomplete' => 'الملف ناقص',
];
