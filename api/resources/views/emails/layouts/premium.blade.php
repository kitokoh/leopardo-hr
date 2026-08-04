<!DOCTYPE html>
<html lang="{{ $locale ?? 'fr' }}" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light dark">
    <meta name="supported-color-schemes" content="light dark">
    <title>{{ $title ?? config('app.name') }}</title>
    <!--[if mso]>
    <noscript>
        <xml>
            <o:OfficeDocumentSettings>
                <o:PixelsPerInch>96</o:PixelsPerInch>
            </o:OfficeDocumentSettings>
        </xml>
    </noscript>
    <![endif]-->
    <style>
        :root {
            color-scheme: light dark;
        }
        body {
            margin: 0;
            padding: 0;
            width: 100% !important;
            -webkit-text-size-adjust: 100%;
            -ms-text-size-adjust: 100%;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background-color: #f8fafc;
            color: #334155;
        }
        .email-wrapper {
            width: 100%;
            background-color: #f8fafc;
            padding: 40px 0;
        }
        .email-content {
            width: 100%;
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            border-radius: 24px;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05), 0 8px 10px -6px rgba(0, 0, 0, 0.01);
            overflow: hidden;
        }
        .header {
            background-color: #042f2e;
            padding: 40px 40px 30px;
            text-align: center;
        }
        .header h1 {
            color: #ffffff;
            font-size: 24px;
            font-weight: 600;
            margin: 0;
            line-height: 1.3;
        }
        .body {
            padding: 40px;
            font-size: 16px;
            line-height: 1.6;
        }
        .body h2 {
            color: #0f172a;
            font-size: 20px;
            font-weight: 600;
            margin-top: 0;
            margin-bottom: 20px;
        }
        .body p {
            margin-top: 0;
            margin-bottom: 20px;
        }
        .btn-primary {
            display: inline-block;
            background-color: #10b981;
            color: #ffffff !important;
            text-decoration: none;
            padding: 14px 28px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 16px;
            margin: 10px 0 20px;
            box-shadow: 0 4px 6px -1px rgba(16, 185, 129, 0.2);
            text-align: center;
        }
        .footer {
            padding: 30px 40px;
            text-align: center;
            background-color: #f1f5f9;
            color: #64748b;
            font-size: 14px;
            border-top: 1px solid #e2e8f0;
        }
        .footer a {
            color: #10b981;
            text-decoration: none;
        }
        .data-box {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        /* Dark Mode */
        @media (prefers-color-scheme: dark) {
            body, .email-wrapper {
                background-color: #020617 !important;
                color: #e2e8f0 !important;
            }
            .email-content {
                background-color: #0f172a !important;
                box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.3) !important;
                border: 1px solid rgba(255, 255, 255, 0.05);
            }
            .header {
                background-color: #022c1d !important;
            }
            .body h2 {
                color: #f8fafc !important;
            }
            .footer {
                background-color: #020617 !important;
                border-top-color: #1e293b !important;
            }
            .data-box {
                background-color: #1e293b !important;
                border-color: #334155 !important;
            }
        }
    </style>
</head>
<body>
    <div class="email-wrapper">
        <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
            <tr>
                <td align="center">
                    <div class="email-content">
                        <!-- Header -->
                        <div class="header">
                            <h1 style="font-size:32px; margin-bottom:10px;">🐆</h1>
                            <h1>@yield('header', config('app.name'))</h1>
                        </div>
                        
                        <!-- Body -->
                        <div class="body">
                            @yield('content')
                        </div>
                        
                        <!-- Footer -->
                        <div class="footer">
                            <p>&copy; {{ date('Y') }} {{ config('app.name') }}. {{ __('emails.premium_layout_rights_reserved') }}</p>
                            <p>{!! __('emails.premium_layout_footer_note', ['supportEmail' => config('mail.from.address', 'support@leopardo-rh.com')]) !!}</p>
                        </div>
                    </div>
                </td>
            </tr>
        </table>
    </div>
</body>
</html>
