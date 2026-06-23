<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rôle assigné — Leopardo RH</title>
</head>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif; background: #f8fafc; margin: 0; padding: 20px;">
    <div style="max-width: 600px; margin: 0 auto; background: white; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 24px rgba(0,0,0,0.08);">

        <!-- Header -->
        <div style="background: linear-gradient(135deg, #059669 0%, #0891b2 100%); padding: 40px 40px 32px; text-align: center;">
            <h1 style="color: white; margin: 0; font-size: 24px; font-weight: 900; letter-spacing: -0.5px;">🎉 Nouveau rôle assigné</h1>
            <p style="color: rgba(255,255,255,0.85); margin: 8px 0 0; font-size: 15px;">{{ $company->name }}</p>
        </div>

        <!-- Body -->
        <div style="padding: 40px;">
            <p style="color: #374151; font-size: 16px; margin: 0 0 16px;">Bonjour <strong>{{ $employee->first_name }}</strong>,</p>

            <p style="color: #374151; font-size: 15px; line-height: 1.6; margin: 0 0 24px;">
                <strong>{{ $assignedByName }}</strong> vous a assigné le rôle de
                <span style="display: inline-block; background: #ecfdf5; color: #059669; font-weight: 700; padding: 3px 10px; border-radius: 20px; font-size: 14px;">{{ $roleLabel }}</span>
                au sein de <strong>{{ $company->name }}</strong>.
            </p>

            <!-- App download section -->
            <div style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 12px; padding: 24px; margin: 0 0 24px;">
                <p style="color: #065f46; font-weight: 700; font-size: 15px; margin: 0 0 12px;">
                    📱 Téléchargez votre application dédiée
                </p>
                <p style="color: #374151; font-size: 14px; margin: 0 0 16px;">
                    En tant que <strong>{{ $roleLabel }}</strong>, vous avez accès à l'application <strong>{{ $appLinks['name'] }}</strong>.
                </p>
                <div style="display: flex; gap: 12px; flex-wrap: wrap;">
                    <a href="{{ $appLinks['android'] }}"
                       style="display: inline-block; background: #1e293b; color: white; text-decoration: none; padding: 12px 20px; border-radius: 10px; font-weight: 700; font-size: 13px;">
                        🤖 Android — Google Play
                    </a>
                    <a href="{{ $appLinks['ios'] }}"
                       style="display: inline-block; background: #1e293b; color: white; text-decoration: none; padding: 12px 20px; border-radius: 10px; font-weight: 700; font-size: 13px;">
                        🍎 iOS — App Store
                    </a>
                </div>
            </div>

            <p style="color: #6b7280; font-size: 13px; margin: 0;">
                Vous pouvez également vous connecter depuis le web sur
                <a href="https://app.leopardo-rh.com" style="color: #059669;">app.leopardo-rh.com</a>
                avec votre email habituel.
            </p>
        </div>

        <!-- Footer -->
        <div style="background: #f8fafc; padding: 20px 40px; border-top: 1px solid #e5e7eb; text-align: center;">
            <p style="color: #9ca3af; font-size: 12px; margin: 0;">Leopardo RH · Votre plateforme RH terrain</p>
        </div>
    </div>
</body>
</html>
