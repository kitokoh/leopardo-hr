@php
$translations = [
    'fr' => [
        'subject' => 'Votre facture Leopardo RH',
        'greeting' => 'Bonjour :name,',
        'body' => 'Votre facture :number d\'un montant de :amount est disponible.',
        'due' => 'Date d\'échéance : :date',
        'button' => 'Voir la facture',
        'thanks' => 'Merci pour votre abonnement.',
    ],
    'ar' => [
        'subject' => 'فاتورتك من ليوباردو HR',
        'greeting' => 'مرحبا :name،',
        'body' => 'فاتورتك رقم :number بمبلغ :amount متاحة.',
        'due' => 'تاريخ الاستحقاق: :date',
        'button' => 'عرض الفاتورة',
        'thanks' => 'شكرا لاشتراككم.',
    ],
    'en' => [
        'subject' => 'Your Leopardo RH Invoice',
        'greeting' => 'Hello :name,',
        'body' => 'Your invoice :number for :amount is now available.',
        'due' => 'Due date: :date',
        'button' => 'View invoice',
        'thanks' => 'Thank you for your subscription.',
    ],
];
$t = $translations[$locale ?? 'fr'];
@endphp
<!DOCTYPE html>
<html lang="{{ $locale ?? 'fr' }}">
<head><meta charset="UTF-8"><title>{{ $t['subject'] }}</title></head>
<body style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;">
    <div style="background: #2563eb; color: white; padding: 20px; border-radius: 8px 8px 0 0; text-align: center;">
        <h1 style="margin: 0; font-size: 24px;">🐆 Leopardo RH</h1>
    </div>
    <div style="background: #f9fafb; padding: 30px; border: 1px solid #e5e7eb; border-radius: 0 0 8px 8px;">
        <p style="font-size: 16px;">{{ str_replace(':name', $userName ?? '', $t['greeting']) }}</p>
        <p>{{ str_replace([':number', ':amount'], [$invoiceNumber ?? '', $invoiceAmount ?? ''], $t['body']) }}</p>
        <p style="color: #6b7280;">{{ str_replace(':date', $dueDate ?? '', $t['due']) }}</p>
        <div style="text-align: center; margin: 30px 0;">
            <a href="{{ $invoiceUrl ?? '#' }}" style="background: #2563eb; color: white; padding: 12px 30px; border-radius: 6px; text-decoration: none; font-weight: bold;">{{ $t['button'] }}</a>
        </div>
        <p style="color: #6b7280;">{{ $t['thanks'] }}</p>
    </div>
</body>
</html>
