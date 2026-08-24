<?php

return [
    // Belge türleri (issue #5224)
    'document_type_invoice' => 'Fatura',
    'document_type_proforma' => 'Proforma',
    'document_type_quote' => 'Teklif',
    'document_type_credit_note' => 'İade faturası',
    'document_type_delivery_note' => 'İrsaliye',
    'document_type_receipt' => 'Makbuz',

    // Durumlar
    'status_draft' => 'Taslak',
    'status_sent' => 'Gönderildi',
    'status_partially_paid' => 'Kısmen ödendi',
    'status_paid' => 'Ödendi',
    'status_cancelled' => 'İptal edildi',
    'status_overdue' => 'Vadesi geçti',

    // Başlık / taraflar
    'number' => 'No.',
    'issue_date' => 'Düzenlenme tarihi',
    'due_date' => 'Vade tarihi',
    'delivery_date' => 'Teslim tarihi',
    'from' => 'Gönderen',
    'to' => 'Müşteri',
    'nif' => 'Vergi No.',

    // Satırlar
    'description' => 'Açıklama',
    'quantity' => 'Adet',
    'unit_price' => 'Birim fiyat',
    'discount' => 'İndirim',
    'amount' => 'Tutar',

    // Toplamlar
    'subtotal_ht' => 'Ara toplam',
    'tax' => 'Vergi',
    'total_ttc' => 'Genel toplam',
    'paid' => 'Ödenen',
    'remaining' => 'Kalan',
    'page' => 'Sayfa',
    'page_of' => '/',

    'no_lines' => 'Satır yok',

    // Alt bilgi
    'legal_mentions' => 'Yasal bildirimler',

    // API iş hataları (issue #5227)
    'error_invalid_document_type' => 'Geçersiz belge türü.',
    'error_document_requires_line' => 'Bir belgenin en az bir satırı olmalıdır.',
    'error_only_draft_can_be_sent' => 'Yalnızca taslak gönderilebilir.',
    'error_send_without_lines' => 'Satırı olmayan belge gönderilemez.',
    'error_contact_required_for_invoice' => 'Fatura veya iade faturası göndermek için müşteri kişisi gereklidir.',
    'error_payment_on_closed_document' => 'Ödenmiş veya iptal edilmiş bir belgeye ödeme kaydedilemez.',
    'error_payment_amount_positive' => 'Ödeme tutarı sıfırdan büyük olmalıdır.',
    'error_payment_exceeds_total_ttc' => 'Ödemelerin toplamı belgenin genel toplamını aşıyor.',
    'error_paid_document_cannot_cancel' => 'Ödenmiş bir belge iptal edilemez.',
    'error_credit_note_requires_invoice' => 'İade faturası bir faturaya bağlı olmalıdır.',
    'error_credit_note_source_not_sent' => 'İptal edilmiş veya taslak fatura iade faturası oluşturamaz.',
    'error_source_invoice_already_paid' => 'Kaynak fatura zaten tamamen ödendi: iade faturası düzenlenemez.',
    'error_credit_note_exceeds_remaining' => 'İade faturası tutarı, kaynak faturanın kalan bakiyesini aşıyor.',
    'error_company_context_required' => 'Şirket bağlamı gereklidir.',

    'error_vat_period_invalid' => 'Geçersiz KDV beyan dönemi (beklenen biçim: YYYY-MM).',
    'error_unknown_series' => 'Bilinmeyen seri: ":key" bir belge türü değil (:allowed).',

    // Doğrulama (issue #5227)
    'validation_amount_required' => 'Tutar zorunludur.',
    'validation_amount_positive' => 'Tutar sıfırdan büyük olmalıdır.',
    'validation_payment_method_invalid' => 'Geçersiz ödeme yöntemi (cash, bank_transfer, check, card, other).',
];
