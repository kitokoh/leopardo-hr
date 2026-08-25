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

    // Doğrulama (issue #5227)
    'validation' => [
        'amount_required' => 'Tutar gereklidir.',
        'amount_min' => 'Tutar kesinlikle pozitif olmalıdır.',
        'method_invalid' => 'Geçersiz ödeme yöntemi (cash, bank_transfer, check, card, other).',
        'series_unknown' => 'Bilinmeyen seri: « :key » bir belge türü değil (:allowed).',
    ],

    // İş hataları (issue #5227)
    'errors' => [
        'gateway_checkout_failed' => 'Ödeme sağlayıcısı geçici olarak kullanılamıyor. Lütfen daha sonra tekrar deneyin.',
        'payment_amount_positive' => 'Ödeme tutarı kesinlikle pozitif olmalıdır.',
        'wf_invalid_type' => 'Geçersiz belge türü.',
        'wf_requires_lines' => 'Bir belgenin en az bir satırı olmalıdır.',
        'wf_send_draft_only' => 'Yalnızca taslak gönderilebilir.',
        'wf_send_no_lines' => 'Satırı olmayan bir belge gönderilemez.',
        'wf_send_requires_contact' => 'Fatura veya iade faturası göndermek için müşteri iletişimi gereklidir.',
        'wf_payment_receive_status' => 'Ödenmiş veya iptal edilmiş bir belge ödeme alamaz.',
        'wf_payment_over_total' => 'Ödemelerin toplamı belgenin genel tutarını aşıyor.',
        'wf_cancel_status' => 'Ödenmiş bir belge iptal edilemez.',
        'wf_credit_note_requires_invoice' => 'İade faturası bir faturaya bağlı olmalıdır.',
        'wf_source_invoice_not_issuable' => 'İptal edilmiş veya taslak bir fatura iade faturası oluşturamaz.',
        'wf_source_invoice_paid' => 'Kaynak fatura zaten tamamen ödendi: iade faturası mümkün değil.',
        'wf_credit_exceeds_remaining' => 'İade faturası tutarı, kaynak faturanın kalan bakiyesini aşıyor.',
        'wf_company_context' => 'Şirket bağlamı gereklidir.',
    ],

    // Varsayılan KDV etiketleri (issue #5227)
    'tva_label_standard' => 'Standart KDV',
    'tva_label_sales_tax' => 'Satış vergisi',
    'tva_label_gst' => 'GST',
    'tva_label_reduced' => 'İndirimli KDV',

];
