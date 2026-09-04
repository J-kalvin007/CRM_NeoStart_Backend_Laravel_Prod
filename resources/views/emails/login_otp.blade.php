<!DOCTYPE html>
<html>
<head>
    <title>Votre code de vérification CRM</title>
</head>
<body style="font-family: Arial, sans-serif; color: #333; line-height: 1.6;">
    <div style="max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 8px;">
        <h2 style="color: #2563EB;">Vérification de connexion</h2>
        <p>Bonjour {{ $user->name }},</p>
        <p>Une tentative de connexion a été détectée sur votre compte. Pour valider votre accès, veuillez utiliser le code à 6 chiffres suivant :</p>
        
        <div style="text-align: center; margin: 30px 0;">
            <div style="background-color: #F3F4F6; display: inline-block; padding: 15px 30px; border-radius: 8px; font-size: 24px; font-weight: bold; letter-spacing: 4px; color: #111827;">
                {{ $otpCode }}
            </div>
        </div>
        
        <p style="color: #EF4444; font-size: 0.9em; text-align: center;">Ce code expirera dans 10 minutes.</p>
        
        <hr style="border: 0; border-top: 1px solid #eee; margin: 20px 0;">
        <p style="font-size: 0.8em; color: #9CA3AF;">Si vous n'avez pas tenté de vous connecter, veuillez modifier votre mot de passe immédiatement ou contacter votre administrateur.</p>
    </div>
</body>
</html>
