<?php

return [
    'work_state_offline' => 'غير متصل',
    'work_state_leave' => 'في إجازة',
    'work_state_absent' => 'غائب',
    'work_state_break' => 'في استراحة',
    'work_state_mission' => 'في مهمة',
    'work_state_present' => 'حاضر',
    // Rôles
    'role_manager' => 'مدير',
    'role_employee' => 'موظف',
    'manager_role_principal' => 'المدير العام',
    'manager_role_rh' => 'مسؤول الموارد البشرية',
    'manager_role_dept' => 'رئيس القسم',
    'manager_role_comptable' => 'محاسب',
    'manager_role_superviseur' => 'مشرف',

    // Statuts
    'status_active' => 'نشط',
    'status_suspended' => 'معلّق',
    'status_archived' => 'مؤرشف',

    // Actions
    'created' => 'تم إنشاء الموظف بنجاح.',
    'updated' => 'تم تحديث الموظف بنجاح.',
    'archived' => 'تم أرشفة الموظف بنجاح.',
    'invited' => 'تم إرسال الدعوة بنجاح.',
    'role_assign_forbidden' => 'فقط مسؤول الشركة يمكنه تعيين الأدوار.',
    'role_assign_not_in_company' => 'الموظف مفقود في شركتك.',
    'role_assigned' => 'تم تعيين الدور \':role\' بنجاح. تم إرسال بريد إلكتروني يحتوي على روابط تحميل التطبيق.',
    'role_removed' => 'تم إلفاء الدور. الموظف الآن موظف عادي.',
    'team_roles_forbidden' => 'فقط مسؤول الشركة يمكنه عرض أدوار الفريق.',

    // Labels
    'first_name' => 'الاسم',
    'last_name' => 'اللقب',
    'email' => 'البريد الإلكتروني',
    'phone' => 'الهاتف',
    'department' => 'القسم',
    'position' => 'المنصب',
    'schedule' => 'الجدول الزمني',
    'contract_type' => 'نوع العقد',
    'salary_base' => 'الراتب الأساسي',
    'date_of_birth' => 'تاريخ الميلاد',
    'nationality' => 'الجنسية',
    'gender' => 'الجنس',
    'hire_date' => 'تاريخ التوظيف',
    'emergency_contact' => 'جهة اتصال الطوارئ',

    'evaluation_exists' => 'يوجد بالفعل تقييم لهذا الموظف لهذه الفترة.',
    'evaluation_acknowledged_locked' => 'لا يمكن تعديل تقييم تم استلامه بعد الآن.',
    'evaluation_not_draft_submit' => 'يمكن إرسال تقييم المسودة فقط.',
    'evaluation_not_draft_delete' => 'يمكن حذف تقييم المسودة فقط.',
    'evaluation_not_submitted' => 'يمكن استلام التقييم المُرسل فقط.',
    'career_event_employee_not_found' => 'الموظف غير موجود في شركتك.',
    'career_event_position_not_found' => 'المنصب غير موجود في شركتك.',
    'career_event_department_not_found' => 'القسم غير موجود في شركتك.',
    'career_event_effective_date_required' => 'تاريخ السريان مطلوب.',
    'career_event_reason_required' => 'السبب مطلوب.',
    'career_event_nothing_to_apply' => 'لا شيء للتطبيق: الحدث لا يحتوي على منصب أو قسم أو راتب مستهدف.',
    'career_event_deleted' => 'تم حذف حدث المسار المهني.',
];
