<!DOCTYPE html>
<html>
<head>
    <title>Bienvenue sur le CRM</title>
</head>
<body style="font-family: Arial, sans-serif; color: #333; line-height: 1.6;">
    <div style="max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 8px;">
        <h2 style="color: #2563EB;">Bienvenue {{ $user->name }} !</h2>
        <p>Un compte administrateur a été créé pour vous sur la plateforme CRM.</p>
        <p>Pour des raisons de sécurité, vous devez définir votre mot de passe pour activer votre compte. Ce lien est valide pendant 48 heures.</p>
        <div style="text-align: center; margin: 30px 0;">
            <a href="{{ $setupLink }}" style="background-color: #2563EB; color: white; padding: 12px 24px; text-decoration: none; border-radius: 4px; font-weight: bold;">
                Définir mon mot de passe
            </a>
        </div>
        <p>Si le bouton ne fonctionne pas, copiez-collez ce lien dans votre navigateur :</p>
        <p style="word-break: break-all; color: #6B7280; font-size: 0.9em;">{{ $setupLink }}</p>
        <hr style="border: 0; border-top: 1px solid #eee; margin: 20px 0;">
        <p style="font-size: 0.8em; color: #9CA3AF;">Si vous n'avez pas demandé la création de ce compte, ignorez simplement cet email.</p>
    </div>
</body>
</html>
