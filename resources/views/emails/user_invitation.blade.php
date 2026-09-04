<!DOCTYPE html>
<html>
<head>
    <title>Vos identifiants de connexion CRM</title>
</head>
<body style="font-family: Arial, sans-serif; color: #333; line-height: 1.6;">
    <div style="max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 8px;">
        <h2 style="color: #2563EB;">Bienvenue {{ $user->name }} !</h2>
        <p>Un compte administrateur a été créé pour vous sur la plateforme CRM.</p>
        <p>Voici vos identifiants temporaires pour vous connecter :</p>
        
        <div style="background-color: #f3f4f6; padding: 15px; border-radius: 5px; margin: 20px 0;">
            <p style="margin: 0;"><strong>Email :</strong> {{ $user->email }}</p>
            <p style="margin: 5px 0 0 0;"><strong>Mot de passe temporaire :</strong> {{ $temporaryPassword }}</p>
        </div>

        <p style="color: #d97706; font-weight: bold;">⚠️ Lors de votre toute première connexion, il vous sera demandé de modifier ce mot de passe temporaire par mesure de sécurité.</p>
        
        <hr style="border: 0; border-top: 1px solid #eee; margin: 20px 0;">
        <p style="font-size: 0.8em; color: #9CA3AF;">Si vous n'avez pas demandé la création de ce compte, merci de contacter votre administrateur.</p>
    </div>
</body>
</html>
