# Vérification complémentaire des parcours mobile / API / web

Cette vérification porte sur les données réellement conservées et affichées, les échantillons remis, les rapports privés et leur transmission. Aucune donnée de démonstration n'est ajoutée à l'application ni à la base métier.

## Anomalies corrigées

1. **Reprise hors ligne et échantillons.** Un compte rendu rejoué pouvait être enregistré alors que la clôture et les échantillons n'avaient jamais atteint le serveur. L'endpoint de rapport réutilise maintenant le contrôle normal de clôture et de stock, dans la même transaction. Si le stock manque, la visite et le rapport restent dans leur état précédent. Un second envoi identique ne déduit pas à nouveau les échantillons ; une remise différente est refusée explicitement.
2. **Publication implicite.** L'absence de `report_status` dans le mobile ne publie plus automatiquement le compte rendu : il reste en brouillon. La publication exige le choix explicite de l'auteur.
3. **Rapports non exécutés.** Le rapport ne permet plus de terminer ou publier une visite encore en brouillon, ni de ramener une visite terminée à un autre statut. Le passage en fin de visite suit les contrôles d'exécution existants.
4. **Objections sans données réelles.** Une objection ne choisit plus le premier produit du catalogue lorsqu'aucun produit n'a été renseigné. Une réaction absente n'est plus transformée en « convaincu ». Aucun argumentaire de remplacement n'est rédigé automatiquement. Les objections et le rapport sont enregistrés ensemble ; une erreur n'est plus masquée après une transmission partielle.
5. **Relecture et erreurs serveur.** Le mobile relit le statut serveur avant de tenter de clôturer une visite déjà terminée. Un refus HTTP n'est plus remplacé par une copie locale. L'historique d'un prescripteur conserve uniquement la dernière réponse de cet endpoint dans le bon contexte, sans reconstruire des rapports à partir de notes ou de visites non soumises.
6. **Réponses non conformes.** Une création ou une modification sans identifiant serveur valide provoque une erreur explicite. L'application ne fabrique plus une réponse de réussite avec le formulaire envoyé. Lorsqu'une visite a déjà été créée, sa reprise utilise son identifiant confirmé et ne programme pas une seconde création.

## Parcours vérifié par HTTP et base temporaire

- Dotation de 10 échantillons d'un lot réel au délégué.
- Rejeu d'une visite terminée hors ligne avec un rapport en brouillon et une remise de 2 échantillons : stock disponible de 8.
- Répétition du même envoi : toujours une seule remise, stock de 8.
- Consultation web par l'administration : contenu du brouillon masqué et absence dans la liste des rapports soumis.
- Soumission explicite par l'auteur : compte rendu visible sur le web.
- Stock insuffisant, remise contradictoire, produit ou réaction d'objection manquants : refus sans clôture ni déduction partielle.
- Publication d'une visite non exécutée et modification de statut détournée : refus.

## Périmètre de validation

Les tests backend utilisent les routes HTTP applicatives et une base temporaire migrée ; les transports de mail et de fichiers des tests sont isolés. Les contrôles web exécutent le code des magasins, filtres et exports avec des réponses de test, sans fournir ces données à l'application livrée. Les contrats Dart vérifient les valeurs réellement conservées et le traitement des informations absentes.

Ces contrôles ne certifient pas une livraison SMTP, un déploiement serveur ou l'ouverture du sélecteur natif sur téléphone. Les tests Flutter avec moteur restent bloqués ici par l'interdiction des sockets locales ; Android SDK et Xcode ne sont pas disponibles. Les étapes d'installation et de recette figurent dans `plan-conges-rh-2026-10-29.md`.

## Résultats de cette passe

- Backend : **238 tests réussis, 1457 assertions**, dont cinq nouveaux parcours HTTP de clôture / stock / publication / objections.
- Web : **21 fichiers de tests réussis** et contrôle TypeScript réussi ; aucun changement de présentation supplémentaire n'a été ajouté.
- Mobile : les **48 contrôles de contrat Dart** de confidentialité/publication, objections, congés, messages, stocks et formulaires patients réussissent. L'analyse de `lib` et `tool` ne signale aucune erreur ni avertissement ; les 14 remarques de style et dépréciation déjà présentes subsistent.
- Les fichiers de test et les transports de test ne sont jamais utilisés comme sources de données de l'application. Les API et le registre de mouvements restent les sources de vérité.
- Ces résultats sont locaux. Ils ne constituent pas une validation SMTP, Android/iOS sur appareil ou du serveur déployé. Aucune migration métier, aucun déploiement et aucun push n'ont été effectués.
