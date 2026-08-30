<!DOCTYPE html>
<html lang="{{ $locale ?? 'fr' }}">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>{{ $locale === 'en' ? 'Weekly HR Digest' : ($locale === 'tr' ? 'Haftalık HR Özeti' : ($locale === 'ar' ? 'الملخص الأسبوعي' : 'Résumé RH hebdomadaire')) }}</title>
</head>
<body style="margin:0;padding:0;background:#f8fafc;font-family:Arial,Helvetica,sans-serif;color:#0f172a;">

<table width="100%" cellpadding="0" cellspacing="0" style="background:#f8fafc;padding:32px 16px;">
  <tr>
    <td align="center">
      <table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,0.06);">

        {{-- Header --}}
        <tr>
          <td style="background:linear-gradient(135deg,#059669 0%,#0891b2 100%);padding:32px 40px;">
            <p style="margin:0;font-size:11px;font-weight:700;letter-spacing:0.15em;text-transform:uppercase;color:#a7f3d0;">
              Leopardo RH
            </p>
            <h1 style="margin:8px 0 4px;font-size:26px;font-weight:900;color:#ffffff;line-height:1.2;">
              @if($locale === 'en') Weekly HR Digest
              @elseif($locale === 'tr') Haftalık HR Özeti
              @elseif($locale === 'ar') الملخص الأسبوعي للموارد البشرية
              @else Résumé RH hebdomadaire
              @endif
            </h1>
            <p style="margin:0;font-size:13px;color:#d1fae5;">
              {{ $data['company_name'] }} — {{ $data['week_label'] }}
            </p>
          </td>
        </tr>

        {{-- Greeting --}}
        <tr>
          <td style="padding:32px 40px 16px;">
            <p style="margin:0;font-size:15px;color:#334155;">
              @if($locale === 'en') Hello <strong>{{ $data['manager_name'] }}</strong>,
              @elseif($locale === 'tr') Merhaba <strong>{{ $data['manager_name'] }}</strong>,
              @elseif($locale === 'ar') مرحباً <strong>{{ $data['manager_name'] }}</strong>،
              @else Bonjour <strong>{{ $data['manager_name'] }}</strong>,
              @endif
            </p>
            <p style="margin:10px 0 0;font-size:14px;color:#64748b;line-height:1.6;">
              @if($locale === 'en') Here is your weekly summary for the HR activity of your company.
              @elseif($locale === 'tr') Şirketinizin geçen haftaki İK aktivitesinin özeti aşağıdadır.
              @elseif($locale === 'ar') فيما يلي ملخص النشاط الأسبوعي لإدارة الموارد البشرية في شركتك.
              @else Voici le résumé de l'activité RH de votre entreprise pour la semaine écoulée.
              @endif
            </p>
          </td>
        </tr>

        {{-- Stats Grid --}}
        <tr>
          <td style="padding:16px 40px;">
            <table width="100%" cellpadding="0" cellspacing="0">
              <tr>
                {{-- Absences en attente --}}
                <td width="48%" style="background:#fef3c7;border-radius:12px;padding:20px;vertical-align:top;">
                  <p style="margin:0;font-size:11px;font-weight:700;letter-spacing:0.12em;text-transform:uppercase;color:#92400e;">
                    @if($locale === 'en') Pending leave requests
                    @elseif($locale === 'tr') Bekleyen izin talepleri
                    @elseif($locale === 'ar') طلبات الإجازة المعلقة
                    @else Congés en attente
                    @endif
                  </p>
                  <p style="margin:8px 0 0;font-size:40px;font-weight:900;color:#b45309;line-height:1;">
                    {{ $data['pending_absences'] }}
                  </p>
                  @if($data['pending_absences'] > 0)
                  <p style="margin:6px 0 0;font-size:12px;color:#92400e;">
                    @if($locale === 'en') ⚡ Requires your attention
                    @elseif($locale === 'tr') ⚡ Dikkatinizi gerektiriyor
                    @elseif($locale === 'ar') ⚡ تتطلب انتباهك
                    @else ⚡ Nécessite votre attention
                    @endif
                  </p>
                  @endif
                </td>
                <td width="4%"></td>
                {{-- Présence --}}
                <td width="48%" style="background:#ecfdf5;border-radius:12px;padding:20px;vertical-align:top;">
                  <p style="margin:0;font-size:11px;font-weight:700;letter-spacing:0.12em;text-transform:uppercase;color:#065f46;">
                    @if($locale === 'en') Avg. attendance rate
                    @elseif($locale === 'tr') Ort. devam oranı
                    @elseif($locale === 'ar') متوسط نسبة الحضور
                    @else Taux de présence moy.
                    @endif
                  </p>
                  <p style="margin:8px 0 0;font-size:40px;font-weight:900;color:#047857;line-height:1;">
                    {{ $data['avg_attendance_pct'] }}%
                  </p>
                  <p style="margin:6px 0 0;font-size:12px;color:#065f46;">
                    @if($locale === 'en') Last 7 days
                    @elseif($locale === 'tr') Son 7 gün
                    @elseif($locale === 'ar') آخر 7 أيام
                    @else 7 derniers jours
                    @endif
                  </p>
                </td>
              </tr>
              <tr><td colspan="3" style="height:12px;"></td></tr>
              <tr>
                {{-- Nouveaux employés --}}
                <td width="48%" style="background:#eff6ff;border-radius:12px;padding:20px;vertical-align:top;">
                  <p style="margin:0;font-size:11px;font-weight:700;letter-spacing:0.12em;text-transform:uppercase;color:#1e40af;">
                    @if($locale === 'en') New employees
                    @elseif($locale === 'tr') Yeni çalışanlar
                    @elseif($locale === 'ar') موظفون جدد
                    @else Nouveaux employés
                    @endif
                  </p>
                  <p style="margin:8px 0 0;font-size:40px;font-weight:900;color:#1d4ed8;line-height:1;">
                    {{ $data['new_employees'] }}
                  </p>
                  <p style="margin:6px 0 0;font-size:12px;color:#1e40af;">
                    @if($locale === 'en') This week
                    @elseif($locale === 'tr') Bu hafta
                    @elseif($locale === 'ar') هذا الأسبوع
                    @else Cette semaine
                    @endif
                  </p>
                </td>
                <td width="4%"></td>
                {{-- Contrats expirant --}}
                <td width="48%" style="background:#fdf4ff;border-radius:12px;padding:20px;vertical-align:top;">
                  <p style="margin:0;font-size:11px;font-weight:700;letter-spacing:0.12em;text-transform:uppercase;color:#6b21a8;">
                    @if($locale === 'en') Expiring contracts (30d)
                    @elseif($locale === 'tr') Sona eren sözleşmeler (30g)
                    @elseif($locale === 'ar') العقود المنتهية (30 يوم)
                    @else Contrats expirant (30 j)
                    @endif
                  </p>
                  <p style="margin:8px 0 0;font-size:40px;font-weight:900;color:#7c3aed;line-height:1;">
                    {{ $data['expiring_contracts'] }}
                  </p>
                  @if($data['expiring_contracts'] > 0)
                  <p style="margin:6px 0 0;font-size:12px;color:#6b21a8;">
                    @if($locale === 'en') ⚠️ To renew
                    @elseif($locale === 'tr') ⚠️ Yenilenecek
                    @elseif($locale === 'ar') ⚠️ للتجديد
                    @else ⚠️ À renouveler
                    @endif
                  </p>
                  @endif
                </td>
              </tr>
            </table>
          </td>
        </tr>

        {{-- CTA --}}
        <tr>
          <td style="padding:24px 40px 32px;text-align:center;">
            <a href="{{ $data['app_url'] }}/dashboard"
               style="display:inline-block;background:linear-gradient(135deg,#059669,#0891b2);color:#ffffff;text-decoration:none;font-size:14px;font-weight:700;padding:14px 32px;border-radius:12px;">
              @if($locale === 'en') Open dashboard
              @elseif($locale === 'tr') Paneli aç
              @elseif($locale === 'ar') فتح لوحة التحكم
              @else Ouvrir le tableau de bord
              @endif
            </a>
          </td>
        </tr>

        {{-- Footer --}}
        <tr>
          <td style="background:#f8fafc;padding:20px 40px;border-top:1px solid #e2e8f0;text-align:center;">
            <p style="margin:0;font-size:11px;color:#94a3b8;">
              Leopardo RH — {{ $data['company_name'] }}<br>
              @if($locale === 'en') You receive this email because you are a manager in Leopardo.
              @elseif($locale === 'tr') Bu e-postayı Leopardo'da yönetici olduğunuz için alıyorsunuz.
              @elseif($locale === 'ar') تستلم هذا البريد الإلكتروني لأنك مدير في Leopardo.
              @else Vous recevez cet email car vous êtes manager dans Leopardo.
              @endif
            </p>
          </td>
        </tr>

      </table>
    </td>
  </tr>
</table>

</body>
</html>
