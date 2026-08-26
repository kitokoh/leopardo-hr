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

    // Email (issue #5225)
    'email_subject' => 'Belge :number',
    'email_heading' => 'Belgeniz',
    'email_body' => 'Merhaba, :number numaralı belgeniz hazır.',
    'email_button' => 'Belgemi görüntüle',
    'email_expires' => 'Bu bağlantı :date tarihinde sona erer.',
    'email_footer' => 'Bu otomatik bir e-postadır — lütfen yanıtlamayın.',

    // Alt bilgi
    'legal_mentions' => 'Yasal bildirimler',

    // Doğrulama (issue #5227)
    'validation' => [
        'amount_required' => 'Tutar gereklidir.',
        'amount_min' => 'Tutar kesinlikle pozitif olmalıdır.',
        'method_invalid' => 'Geçersiz ödeme yöntemi (cash, bank_transfer, check, card, other).',
        'series_unknown' => 'Bilinmeyen seri: « :key » bir belge türü değil (:allowed).',
        // Rapprochement / profondeur (issue #5422)
        'year_required' => 'Yıl zorunludur.',
        'year_integer' => 'Yıl bir tam sayı olmalıdır.',
        'year_range' => 'Yıl 2000 ile 2100 arasında olmalıdır.',
        'period_required' => 'Dönem zorunludur (YYYY-AA).',
        'period_invalid' => 'Geçersiz dönem. YYYY-AA biçimini kullanın.',
        'letter_required' => 'Mutabakat kodu zorunludur.',
        'letter_max' => 'Mutabakat kodu 32 karakteri aşamaz.',
        'entry_ids_required' => 'Mutabakat için en az iki kayıt seçin.',
        'entry_ids_integer' => 'Geçersiz kayıt kimlikleri.',
        'entry_ids_min' => 'Mutabakat en az iki kayıt gerektirir.',
        'year_between' => 'Yıl 2000 ile 2100 arasında olmalıdır.',
    ],

        'bank_file_required' => 'CSV dosyası zorunludur.',
        'bank_file_mimes' => 'Dosya CSV formatında olmalıdır.',
        'bank_period_required' => 'Dönem zorunludur (YYYY-MM).',
        'bank_period_format' => 'Dönem YYYY-MM formatında olmalıdır.',
        'bank_reference_required' => 'İçe aktarma referansı zorunludur.',
        'bank_payment_required' => 'Eşleştirilecek ödeme zorunludur.',
        'bank_payment_exists' => 'Seçilen ödeme mevcut değil.',
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
        'bank_line_empty' => 'Boş satır atlandı.',
        'bank_line_invalid_date' => 'Geçersiz tarih: ":value".',
        'bank_line_missing_label' => 'Açıklama eksik.',
        'bank_line_invalid_amount' => 'Geçersiz tutar: ":value".',
        'bank_empty_file' => 'CSV dosyası boş.',
        'bank_missing_columns' => 'Geçersiz başlık: gerekli sütunlar eksik (:columns).',
        'wf_company_context' => 'Şirket bağlamı gereklidir.',
        'statement_year_invalid' => 'Geçersiz mali yıl.',
        'statement_period_invalid' => 'Geçersiz muhasebe dönemi (YYYY-AA).',
        'vat_period_invalid' => 'Geçersiz dönem. AAAA-AA biçimini kullanın.',
    ],

    // Varsayılan KDV etiketleri (issue #5227)
    'tva_label_standard' => 'Standart KDV',
    'tva_label_sales_tax' => 'Satış vergisi',
    'tva_label_gst' => 'GST',
    'tva_label_reduced' => 'İndirimli KDV',


    // Profondeur comptable (issue #5422)
    'chart_system_account_not_deletable' => 'Sistem hesapları (sağlanan) silinemez — gerekirse devre dışı bırakın.',
    'chart_account_has_entries' => 'Bu hesap yevmiye kayıtları taşıyor ve silinemez.',
    'fec_no_entries' => 'Bu dönem için kayıt yok — FEC dışa aktarılamaz.',
    'fiscal_year_already_closed' => 'Bu mali yıl zaten kapatıldı veya mevcut değil.',
    'lettering_unbalanced' => 'Mutabakat dengeli olmalıdır: toplam borçlar toplam alacaklara eşit olmalıdır.',
    'lettering_invalid' => 'Geçersiz mutabakat: kayıtlar aynı hesabı hedeflemelidir.',
    'lettering_already_used' => 'Bir veya daha fazla kayıt başka bir kodla mutabık kılınmış.',
];



