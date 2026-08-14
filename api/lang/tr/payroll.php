<?php

return [
    'zero_slips_generated' => "Maaş bordrosu oluşturulmadı: maaş hesaplamadan önce bu ülke için en az bir aktif maaş yapısı olduğundan emin olun.",
    'rate_edit_locked' => "Gönderilmiş, aktif veya değiştirilmiş bir satır düzenlenemez — yeni bir değişiklik önerin.",
    'rate_delete_draft_only' => "Yalnızca taslak satır silinebilir.",
    'rate_country_unsupported' => "Desteklenmeyen ülke.",
    'tax_scale_default_name' => ":country yasal vergi ölçeği :year",
    // Sorun #1872 — bordro kuralları güven düzeyi: API sınırında
    // Lang::get('payroll.confidence.*') ile tüketilen yerelleştirilmiş
    // mesajlar (sunucu, simülasyon, desteklenen ülkeler kaydı).
    'confidence' => [
        'label' => 'Bordro kuralları güven düzeyi',
        'production' => [
            'message' => ':country için yasal olarak onaylanmış ve üretimde kullanılan kurallar. Zorunlu beyanlarda bu tutarlara dayanmadan önce güncel oranları her zaman yerel bir danışmanla teyit edin.',
        ],
        'pilot' => [
            'message' => ':country için pilot kurallar: tutarlar genel kamu kaynaklarından (iş kanunu) alınmıştır ancak henüz yerel olarak yasal olarak doğrulanmamıştır. Yasal yükümlülükleriniz için bu rakamlara (vergi dilimleri, sosyal katkılar, fazla mesai eşikleri) dayanmadan önce yerel hukuk/vergi danışmanınızla teyit edin.',
        ],
        'placeholder' => [
            'message' => ':country için değersiz yapısal taslak: vergi ve sosyal katkı tutarları henüz araştırılmamıştır ve değiştirilmeden gerçek bordro döngülerinde kullanılmamalıdır.',
        ],
        'unknown' => [
            'message' => ':country için henüz bordro kuralı yok: bu ülke için bordro hesaplaması kullanılamıyor.',
        ],
    ],

    // Issue #1923 — yasal oran doğrulama iş akışı (#1813): servis/dinleyici/
    // yönetici denetleyici mesajları — artık sabit kodlanmış aksanlı dize yok.
    'rate_submit_draft_only' => "Yalnızca taslak satır gönderilebilir (güncel durum: :status).",
    'rate_approve_pending_only' => "Yalnızca onay bekleyen satır onaylanabilir (güncel durum: :status).",
    'rate_reject_pending_only' => "Yalnızca onay bekleyen satır reddedilebilir (güncel durum: :status).",
    'rate_reject_reason_required' => "Red nedeni zorunludur.",
    'rate_table_unknown' => "Bilinmeyen tablo.",
    'rate_overlap_conflict' => "Bu kimlik için yeni yürürlük penceresiyle çakışan bir dönemde zaten aktif bir satır var: önce mevcut satırın penceresini kapatın.",
    'rate_validation_requested_title' => "Oran doğrulaması istendi — :label",
    'rate_validation_requested_body' => "Yasal bir :kind (:label) yönetici arayüzünde doğrulamanızı bekliyor.",
    'rate_kind_tax_scale' => "vergi ölçeği",
    'rate_kind_contribution' => "prim oranı",
    'rate_approved_title' => "Oran değişikliği onaylandı",
    'rate_approved_body' => "Yasal oran değişikliğiniz (:label) onaylandı ve artık aktif.",
    'rate_rejected_title' => "Oran değişikliği reddedildi",
    'rate_rejected_body' => "Yasal oran değişikliğiniz (:label) reddedildi: :reason",
];
