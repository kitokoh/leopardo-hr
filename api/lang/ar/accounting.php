<?php

return [
    // أنواع المستندات (issue #5224)
    'document_type_invoice' => 'فاتورة',
    'document_type_proforma' => 'فاتورة أولية',
    'document_type_quote' => 'عرض سعر',
    'document_type_credit_note' => 'إشعار دائن',
    'document_type_delivery_note' => 'سند تسليم',
    'document_type_receipt' => 'إيصال',

    // الحالات
    'status_draft' => 'مسودة',
    'status_sent' => 'أُرسلت',
    'status_partially_paid' => 'مدفوعة جزئياً',
    'status_paid' => 'مدفوعة',
    'status_cancelled' => 'ملغاة',
    'status_overdue' => 'متأخرة',

    // الترويسة / الأطراف
    'number' => 'الرقم',
    'issue_date' => 'تاريخ الإصدار',
    'due_date' => 'تاريخ الاستحقاق',
    'delivery_date' => 'تاريخ التسليم',
    'from' => 'المُصدر',
    'to' => 'العميل',
    'nif' => 'رقم التعريف الجبائي',

    // الأسطر
    'description' => 'البيان',
    'quantity' => 'الكمية',
    'unit_price' => 'سعر الوحدة',
    'discount' => 'الخصم',
    'amount' => 'المبلغ',

    // المجاميع
    'subtotal_ht' => 'المجموع الجزئي',
    'tax' => 'الضريبة',
    'total_ttc' => 'الإجمالي',
    'paid' => 'المدفوع',
    'remaining' => 'المتبقي',
    'page' => 'صفحة',
    'page_of' => 'من',

    'no_lines' => 'لا توجد أسطر',

    // Email (issue #5225)
    'email_subject' => 'مستند :number',
    'email_heading' => 'مستندك',
    'email_body' => 'مرحباً، مستندك :number متاح.',
    'email_button' => 'عرض المستند',
    'email_expires' => 'تنتهي صلاحية هذا الرابط في :date.',
    'email_footer' => 'هذه رسالة تلقائية — يرجى عدم الرد.',

    // التذييل
    'legal_mentions' => 'إشعارات قانونية',

    // التحقق (issue #5227)
    'validation' => [
        'amount_required' => 'المبلغ مطلوب.',
        'amount_min' => 'يجب أن يكون المبلغ موجباً تماماً.',
        'method_invalid' => 'طريقة دفع غير صالحة (cash, bank_transfer, check, card, other).',
        'series_unknown' => 'سلسلة غير معروفة: « :key » ليست نوع مستند (:allowed).',
        // Rapprochement / profondeur (issue #5422)
        'year_required' => 'السنة مطلوبة.',
        'year_integer' => 'يجب أن تكون السنة رقماً صحيحاً.',
        'year_range' => 'يجب أن تكون السنة بين 2000 و 2100.',
        'period_required' => 'الفترة مطلوبة (YYYY-MM).',
        'period_invalid' => 'فترة غير صالحة. استخدم صيغة YYYY-MM.',
        'letter_required' => 'رمز التسوية مطلوب.',
        'letter_max' => 'لا يمكن أن يتجاوز رمز التسوية 32 حرفاً.',
        'entry_ids_required' => 'اختر قيدين على الأقل للتسوية.',
        'entry_ids_integer' => 'معرّفات قيود غير صالحة.',
        'entry_ids_min' => 'تتطلب التسوية قيدين على الأقل.',
        'year_between' => 'يجب أن تكون السنة بين 2000 و 2100.',
    ],

        'bank_file_required' => 'ملف CSV مطلوب.',
        'bank_file_mimes' => 'يجب أن يكون الملف بصيغة CSV.',
        'bank_period_required' => 'فترة الكشف مطلوبة (YYYY-MM).',
        'bank_period_format' => 'يجب أن تكون الفترة بصيغة YYYY-MM.',
        'bank_reference_required' => 'مرجع الاستيراد مطلوب.',
        'bank_payment_required' => 'الدفعة المراد مطابقتها مطلوبة.',
        'bank_payment_exists' => 'الدفعة المحددة غير موجودة.',
    'errors' => [
        'gateway_checkout_failed' => 'بوابة الدفع غير متاحة مؤقتًا. يرجى المحاولة لاحقًا.',
        'payment_amount_positive' => 'يجب أن يكون مبلغ الدفع موجباً تماماً.',
        'wf_invalid_type' => 'نوع مستند غير صالح.',
        'wf_requires_lines' => 'يجب أن يحتوي المستند على سطر واحد على الأقل.',
        'wf_send_draft_only' => 'يمكن إرسال المسودة فقط.',
        'wf_send_no_lines' => 'لا يمكن إرسال مستند بدون أسطر.',
        'wf_send_requires_contact' => 'يلزم جهة اتصال عميل لإرسال فاتورة أو إشعار دائن.',
        'wf_payment_receive_status' => 'لا يمكن أن يستقبل المستند المدفوع أو الملغى دفعة.',
        'wf_payment_over_total' => 'إجمالي الدفعات يتجاوز المبلغ الإجمالي للمستند.',
        'wf_cancel_status' => 'لا يمكن إلغاء مستند مدفوع.',
        'wf_credit_note_requires_invoice' => 'يجب ربط الإشعار الدائن بفاتورة.',
        'wf_source_invoice_not_issuable' => 'لا يمكن للفاتورة الملغاة أو المسودة توليد إشعار دائن.',
        'wf_source_invoice_paid' => 'الفاتورة المصدرية مدفوعة بالكامل بالفعل: لا يمكن إصدار إشعار دائن.',
        'wf_credit_exceeds_remaining' => 'مبلغ الإشعار الدائن يتجاوز الرصيد المتبقي للفاتورة المصدرية.',
        'bank_line_empty' => 'تم تخطي السطر الفارغ.',
        'bank_line_invalid_date' => 'تاريخ غير صالح: «:value».',
        'bank_line_missing_label' => 'البيان مفقود.',
        'bank_line_invalid_amount' => 'مبلغ غير صالح: «:value».',
        'bank_empty_file' => 'ملف CSV فارغ.',
        'bank_missing_columns' => 'ترويسة غير صالحة: أعمدة مطلوبة مفقودة (:columns).',
        'wf_company_context' => 'سياق الشركة مطلوب.',
        'statement_year_invalid' => 'سنة مالية غير صالحة.',
        'statement_period_invalid' => 'فترة محاسبية غير صالحة (YYYY-MM).',
        'vat_period_invalid' => 'فترة غير صالحة. استخدم صيغة YYYY-MM.',
    ],

    // تسميات الضريبة الافتراضية (issue #5227)
    'tva_label_standard' => 'الضريبة القياسية',
    'tva_label_sales_tax' => 'ضريبة المبيعات',
    'tva_label_gst' => 'ضريبة السلع والخدمات',
    'tva_label_reduced' => 'الضريبة المخفضة',


    // Profondeur comptable (issue #5422)
    'chart_system_account_not_deletable' => 'لا يمكن حذف الحسابات النظامية (المجهزة) — يمكن تعطيلها إذا لزم الأمر.',
    'chart_account_has_entries' => 'هذا الحساب يحمل قيوداً في اليومية ولا يمكن حذفه.',
    'fec_no_entries' => 'لا توجد قيود لهذه الفترة — تعذر تصدير FEC.',
    'fiscal_year_already_closed' => 'هذه السنة المالية مغلقة بالفعل أو غير موجودة.',
    'lettering_unbalanced' => 'يجب أن تكون التسوية متوازنة: مجموع المدين يجب أن يساوي مجموع الدائن.',
    'lettering_invalid' => 'تسوية غير صالحة: يجب أن تستهدف القيود نفس الحساب.',
    'lettering_already_used' => 'قيد واحد أو أكثر مسوّى بالفعل برمز آخر.',
];



