<?php

declare(strict_types=1);

// BC-30 PHARMACY (PHARMA-001..007, #7798-#7804) — رسائل المستخدم للحل العمودي
// PharmaManager (PA2-I18N-007).
return [
    'solution_inactive' => 'حل PharmaManager غير مفعّل لهذا المستأجر.',
    'insufficient_stock' => 'مخزون غير كافٍ للمنتج #:product : المطلوب :requested، المتاح :available (دفعات غير منتهية الصلاحية).',
    'invalid_transition' => 'انتقال غير صالح: :from ← :to.',
    'prescription_required' => 'المنتج « :product » يتطلب وصفة طبية: يرجى توفير prescription_id.',
    'prescription_not_found' => 'الوصفة الطبية غير موجودة.',
    'product_not_found' => 'المنتج غير موجود.',
    'batch_not_found' => 'الدفعة غير موجودة.',
    'batch_not_found_return' => 'الدفعة غير موجودة لعملية الإرجاع.',
    'order_line_not_found' => 'سطر أمر الشراء غير موجود.',
    'quantity_received_positive' => 'يجب أن تكون الكمية المستلمة موجبة تمامًا.',
    'quantity_dispensed_positive' => 'يجب أن تكون الكمية المصروفة موجبة تمامًا.',
    'adjustment_delta_nonzero' => 'لا يمكن أن يكون فرق التسوية صفرًا.',
    'adjustment_reason_required' => 'سبب التسوية إلزامي.',
    'void_reason_required' => 'سبب الإلغاء إلزامي.',
    'invalid_adjustment_type' => 'نوع تسوية غير صالح.',
    'invalid_dispense_type' => 'نوع صرف غير صالح.',
    'empty_order' => 'يجب أن يحتوي أمر الشراء على سطر واحد على الأقل.',
    'empty_receipt' => 'لا يوجد سطر للاستلام.',
    'empty_sale' => 'يجب أن تحتوي عملية البيع على سطر واحد على الأقل.',
    'over_receipt' => 'رُفض الاستلام الزائد على السطر #:line : المطلوب :ordered، المستلم :received، المقترح :proposed.',
];
