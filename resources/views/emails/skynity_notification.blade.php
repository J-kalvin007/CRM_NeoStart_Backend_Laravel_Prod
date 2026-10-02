<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'Notification SKYNITY CRM' }}</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background-color: #F8FAFC;
            margin: 0;
            padding: 0;
            color: #1E293B;
        }
        .container {
            max-width: 600px;
            margin: 30px auto;
            background: #FFFFFF;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 10px 25px rgba(0, 80, 203, 0.08);
            border: 1px solid #E2E8F0;
        }
        .header {
            background: linear-gradient(135deg, #0050CB 0%, #003D9E 100%);
            padding: 32px 24px;
            text-align: center;
            color: #FFFFFF;
        }
        .header h1 {
            margin: 0;
            font-size: 22px;
            font-weight: 700;
            letter-spacing: 0.5px;
        }
        .header p {
            margin: 6px 0 0;
            font-size: 13px;
            opacity: 0.85;
        }
        .content {
            padding: 32px 28px;
        }
        .greeting {
            font-size: 16px;
            font-weight: 600;
            color: #0F172A;
            margin-bottom: 16px;
        }
        .message-box {
            background-color: #F1F5F9;
            border-left: 4px solid #0050CB;
            padding: 16px 20px;
            border-radius: 8px;
            margin: 20px 0;
            font-size: 14px;
            line-height: 1.6;
            color: #334155;
        }
        .action-button {
            display: inline-block;
            background-color: #0050CB;
            color: #FFFFFF !important;
            text-decoration: none;
            padding: 14px 28px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 14px;
            margin-top: 12px;
            box-shadow: 0 4px 12px rgba(0, 80, 203, 0.25);
        }
        .footer {
            background-color: #F8FAFC;
            padding: 20px 24px;
            text-align: center;
            border-top: 1px solid #E2E8F0;
            font-size: 12px;
            color: #94A3B8;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>SKYNITY CRM</h1>
            <p>Plateforme de Gestion Médicale & Pharmaceutique</p>
        </div>
        <div class="content">
            <div class="greeting">Bonjour {{ $userName ?? 'Utilisateur' }},</div>
            <p style="font-size: 14px; color: #475569;">Vous avez reçu une nouvelle mise à jour importante concernant vos activités :</p>
            
            <div class="message-box">
                <strong style="color: #0050CB; display: block; margin-bottom: 6px; font-size: 15px;">{{ $title }}</strong>
                {{ $messageText }}
            </div>

            @if(!empty($actionUrl))
                <div style="text-align: center; margin-top: 24px;">
                    <a href="{{ $actionUrl }}" class="action-button">Consulter dans l'application</a>
                </div>
            @endif

            <p style="font-size: 13px; color: #64748B; margin-top: 28px;">
                Si vous avez des questions, rendez-vous dans l'application SKYNITY.
            </p>
        </div>
        <div class="footer">
            &copy; {{ date('Y') }} SKYNITY CRM — Développé par Neo Start Technology. Tous droits réservés.
        </div>
    </div>
</body>
</html>
