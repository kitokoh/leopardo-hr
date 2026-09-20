<?php

declare(strict_types=1);

// BC-30 PHARMACY (PHARMA-001..007, #7798-#7804) — PharmaManager dikeyinin
// kullanıcı mesajları (PA2-I18N-007).
return [
    'solution_inactive' => 'PharmaManager çözümü bu kiracı için etkin değil.',
    'insufficient_stock' => '#:product ürünü için yetersiz stok: :requested istendi, :available mevcut (süresi geçmemiş partiler).',
    'invalid_transition' => 'Geçersiz geçiş: :from → :to.',
    'prescription_required' => '":product" ürünü reçete gerektirir: prescription_id sağlayın.',
    'prescription_not_found' => 'Reçete bulunamadı.',
    'product_not_found' => 'Ürün bulunamadı.',
    'batch_not_found' => 'Parti bulunamadı.',
    'batch_not_found_return' => 'İade işlemi için parti bulunamadı.',
    'order_line_not_found' => 'Satın alma siparişi satırı bulunamadı.',
    'quantity_received_positive' => 'Teslim alınan miktar kesinlikle pozitif olmalıdır.',
    'quantity_dispensed_positive' => 'Verilen miktar kesinlikle pozitif olmalıdır.',
    'adjustment_delta_nonzero' => 'Düzeltme farkı sıfır olamaz.',
    'adjustment_reason_required' => 'Düzeltme gerekçesi zorunludur.',
    'void_reason_required' => 'İptal gerekçesi zorunludur.',
    'invalid_adjustment_type' => 'Geçersiz düzeltme türü.',
    'invalid_dispense_type' => 'Geçersiz verme türü.',
    'empty_order' => 'Bir satın alma siparişi en az bir satır içermelidir.',
    'empty_receipt' => 'Teslim alınacak satır yok.',
    'empty_sale' => 'Bir satış en az bir satır içermelidir.',
    'over_receipt' => '#:line satırında fazla teslim alma reddedildi: :ordered sipariş edildi, :received zaten alındı, :proposed önerildi.',
];
