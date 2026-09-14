<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bienvenue sur le CRM - Vos identifiants de connexion</title>
</head>
<body style="margin: 0; padding: 0; background-color: #f4f6f9; font-family: 'Segoe UI', Arial, sans-serif; color: #333;">

    <div style="max-width: 600px; margin: 40px auto; background-color: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.08);">

        {{-- Header --}}
        <div style="padding: 32px 40px 20px 40px; text-align: center; border-bottom: 1px solid #e5e7eb;">
            <h1 style="margin: 0; color: #111827; font-size: 22px; font-weight: 700; letter-spacing: -0.3px;">
                🎉 Bienvenue sur la plateforme CRM
            </h1>
            <p style="margin: 8px 0 0 0; color: #6b7280; font-size: 14px;">
                Un compte a été créé pour vous
            </p>
        </div>

        {{-- Body --}}
        <div style="padding: 36px 40px;">

            <p style="font-size: 15px; margin: 0 0 20px 0;">
                Bonjour <strong>{{ $user->name }}</strong>,
            </p>
            <p style="font-size: 14px; color: #555; line-height: 1.7; margin: 0 0 24px 0;">
                Votre compte a été créé par un administrateur. Vous trouverez ci-dessous vos identifiants temporaires pour accéder à la plateforme.
            </p>

            {{-- Credentials box --}}
            <div style="background-color: #f0f4ff; border: 1px solid #c7d7fd; border-radius: 8px; padding: 20px 24px; margin-bottom: 28px;">
                <p style="margin: 0 0 10px 0; font-size: 13px; font-weight: 600; color: #1e40af; text-transform: uppercase; letter-spacing: 0.5px;">
                    Vos identifiants de connexion
                </p>
                <table style="width: 100%; border-collapse: collapse; font-size: 14px;">
                    <tr>
                        <td style="padding: 6px 0; color: #6b7280; width: 150px;">Adresse email :</td>
                        <td style="padding: 6px 0; font-weight: 600; color: #111827;">{{ $user->email }}</td>
                    </tr>
                    <tr>
                        <td style="padding: 6px 0; color: #6b7280;">Mot de passe temporaire :</td>
                        <td style="padding: 6px 0;">
                            <span style="background-color: #1d4ed8; color: #ffffff; padding: 3px 10px; border-radius: 4px; font-family: 'Courier New', monospace; font-size: 15px; font-weight: 700; letter-spacing: 1px;">
                                {{ $temporaryPassword }}
                            </span>
                        </td>
                    </tr>
                </table>
            </div>

            {{-- Warning box --}}
            <div style="background-color: #fffbeb; border: 1px solid #fcd34d; border-radius: 8px; padding: 16px 20px; margin-bottom: 28px;">
                <p style="margin: 0; font-size: 13.5px; color: #92400e; line-height: 1.6;">
                    ⚠️ <strong>Action requise dès la première connexion :</strong><br>
                    Pour des raisons de sécurité, vous serez <strong>obligé de définir un nouveau mot de passe</strong> lors de votre première connexion. Votre nouveau mot de passe devra contenir <strong>au moins 8 caractères</strong>.
                </p>
            </div>

            {{-- CTA Button --}}
            <div style="text-align: center; margin-bottom: 32px;">
                <a href="{{ config('app.frontend_url', config('app.url')) }}/login"
                   style="display: inline-block; background: linear-gradient(135deg, #1d4ed8, #2563eb); color: #ffffff; text-decoration: none; padding: 13px 36px; border-radius: 8px; font-size: 14px; font-weight: 600; letter-spacing: 0.3px;">
                    Se connecter maintenant →
                </a>
            </div>

            <p style="font-size: 13px; color: #9ca3af; line-height: 1.6; margin: 0;">
                Si vous n'êtes pas à l'origine de cette inscription ou si vous avez des questions, contactez votre administrateur système.
            </p>
        </div>

        {{-- Footer --}}
        <div style="background-color: #f9fafb; border-top: 1px solid #e5e7eb; padding: 20px 40px; text-align: center;">
            <p style="margin: 0; font-size: 12px; color: #9ca3af;">
                © {{ date('Y') }} CRM &mdash; Cet email est envoyé automatiquement, merci de ne pas y répondre.
            </p>
        </div>

    </div>

</body>
</html>
