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

    // التذييل
    'legal_mentions' => 'إشعارات قانونية',

    // أخطاء الأعمال في API (issue #5227)
    'error_invalid_document_type' => 'نوع المستند غير صالح.',
    'error_document_requires_line' => 'يجب أن يحتوي المستند على سطر واحد على الأقل.',
    'error_only_draft_can_be_sent' => 'يمكن إرسال المسودة فقط.',
    'error_send_without_lines' => 'لا يمكن إرسال مستند بدون أسطر.',
    'error_contact_required_for_invoice' => 'يلزم جهة اتصال عميل لإرسال فاتورة أو إشعار دائن.',
    'error_payment_on_closed_document' => 'لا يمكن تسجيل دفعة على مستند مدفوع أو ملغى.',
    'error_payment_amount_positive' => 'يجب أن يكون مبلغ الدفعة موجباً تماماً.',
    'error_payment_exceeds_total_ttc' => 'مجموع الدفعات يتجاوز الإجمالي الشامل للضريبة للمستند.',
    'error_paid_document_cannot_cancel' => 'لا يمكن إلغاء مستند مدفوع.',
    'error_credit_note_requires_invoice' => 'يجب ربط الإشعار الدائن بفاتورة.',
    'error_credit_note_source_not_sent' => 'لا يمكن للفاتورة الملغاة أو المسودة توليد إشعار دائن.',
    'error_source_invoice_already_paid' => 'الفاتورة المصدر مدفوعة بالكامل بالفعل: لا يمكن إصدار إشعار دائن.',
    'error_credit_note_exceeds_remaining' => 'مبلغ الإشعار الدائن يتجاوز الرصيد المتبقي من الفاتورة المصدر.',
    'error_company_context_required' => 'سياق الشركة مطلوب.',

    'error_vat_period_invalid' => 'فترة إقرار الضريبة غير صالحة (الصيغة المتوقعة: YYYY-MM).',
    'error_unknown_series' => 'سلسلة غير معروفة: «:key» ليست نوع مستند (:allowed).',

    // التحقق (issue #5227)
    'validation_amount_required' => 'المبلغ مطلوب.',
    'validation_amount_positive' => 'يجب أن يكون المبلغ موجباً تماماً.',
    'validation_payment_method_invalid' => 'طريقة دفع غير صالحة (cash, bank_transfer, check, card, other).',
];
