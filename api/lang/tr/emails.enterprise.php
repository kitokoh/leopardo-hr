<?php

return [
    'invitation' => [
        'subject' => ':company sirketine Leopardo IK uzerinden katilmaniz icin davet edildiniz',
        'greeting' => 'Merhaba :name,',
        'body' => ':company sirketine katilmaniz icin davet edildiniz. Hesabinizi etkinlestirmek icin asagidaki baglantiya tiklayin.',
        'action' => 'Hesabimi etkinlestir',
        'footer' => 'Bu islemi siz istemediyseniz bu e-postayi yok sayin.',
    ],
    'reset_password' => [
        'subject' => 'Sifrenizi sifirlayin',
        'greeting' => 'Merhaba :name,',
        'body' => 'Sifrenizi sifirlamak icin asagidaki baglantiya tiklayin.',
        'action' => 'Sifreyi sifirla',
        'footer' => 'Bu islemi siz istemediyseniz bu e-postayi yok sayin.',
    ],
    'payroll_ready' => [
        'subject' => 'Maas pusulaniz hazir',
        'greeting' => 'Merhaba :name,',
        'body' => ':period donemi icin maas pusulaniz hazir. Leopardo IK icinde inceleyebilirsiniz.',
        'action' => 'Maas pusulasini gor',
        'footer' => 'Muhasebe aktarimindan once bilgilerinizi kontrol edin.',
    ],
    'absence_approved' => [
        'subject' => 'Izin talebiniz onaylandi',
        'greeting' => 'Merhaba :name,',
        'body' => ':period donemi icin izin talebiniz onaylandi.',
        'action' => 'Talebi gor',
        'footer' => 'Takim plani guncellendi.',
    ],
    'absence_rejected' => [
        'subject' => 'Izin talebiniz reddedildi',
        'greeting' => 'Merhaba :name,',
        'body' => ':period donemi icin izin talebiniz reddedildi.',
        'action' => 'Talebi gor',
        'footer' => 'Ek bilgiye ihtiyaciniz varsa yoneticinizle gorusun.',
    ],
];
