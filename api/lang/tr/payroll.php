<?php

return [
    'zero_slips_generated' => "Maaş bordrosu oluşturulmadı: maaş hesaplamadan önce bu ülke için en az bir aktif maaş yapısı olduğundan emin olun.",
    'rate_edit_locked' => "Gönderilmiş, aktif veya değiştirilmiş bir satır düzenlenemez — yeni bir değişiklik önerin.",
    'rate_delete_draft_only' => "Yalnızca taslak satır silinebilir.",
    'rate_country_unsupported' => "Desteklenmeyen ülke.",
    'placeholder_acknowledge_required' => ":country için bordro kuralları hâlâ 'placeholder' aşamasında: hiçbir yasal değer uygulanmamıştır. Açıkça onaylayın (acknowledge_placeholder=true) — tutarlar YALNIZCA gösterge niteliğindedir ve gerçek bir bordroda kullanılamaz.",
    'compliance_warning_placeholder' => ":country için yalnızca yapısal kurallar: oranlar ve katkılar henüz kaynaklandırılmadı — gerçek bordroda kullanmayın.",
    'compliance_warning_pilot' => ":country için pilot kurallar, kamu referanslarından alınmış ancak yerel olarak yasal olarak doğrulanmamıştır — yasal kullanımdan önce yerel danışmanla teyit edin.",
    'compliance_warning_production' => ":country bordrosu için doğrulanmış kurallar — beyanname vermeden önce güncel oranları yerel danışmanla teyit edin.",
    'tax_scale_default_name' => ":country yasal vergi ölçeği :year",

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
