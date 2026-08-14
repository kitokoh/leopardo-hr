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
];
