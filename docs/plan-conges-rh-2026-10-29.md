# Plan de gestion RH des absences

- [x] Auditer les parcours existants et préserver les absences historiques.
- [x] Ajouter les données RH : demandes reliées aux activités, étapes, soldes annuels, journal de mouvements et justificatifs privés.
- [x] Appliquer les décisions séquentielles, le débit unique à l'accord final, l'annulation encadrée et les contrôles de chevauchement.
- [x] Fournir les paramètres par pays, barèmes exceptionnels, code de paie et reports annuels sans droits fictifs.
- [x] Relier le web : demandes personnelles, file des évaluateurs, détails/historique et calendrier d'équipe.
- [x] Relier le mobile aux mêmes contrats, décisions et pièces justificatives ; conserver une erreur explicite hors ligne.
- [x] Tester les workflows API réels, exports, synchronisation et contrats clients, puis compiler et documenter les limites.

## Choix d'intégration

Les identifiants utilisateurs restent des UUID. Les activités calendaires sont conservées pour l'agenda ; une demande RH leur est reliée sans recréer une seconde absence. Les anciennes absences continuent de relever de leur circuit historique jusqu'à une conversion explicite. Le planning hebdomadaire ne peut approuver ni rejeter une demande RH.

Les valeurs de droits et les barèmes indiqués comme exemples dans le cahier des charges ne constituent pas des soldes réels : aucun utilisateur ne reçoit automatiquement 30, 98 ou 10 jours. Les soldes sont attribués et tracés par un administrateur. Les barèmes de permission sont configurables par pays. Le système ne prétend pas avoir validé des règles de droit nationales.

L'intégration paie fournit les codes d'absence dans les exports ; aucun transfert vers un logiciel de paie non identifié n'est simulé.

## Mise en service et contrôles restants

1. Déployer le backend et ses dépendances Composer, puis exécuter `php artisan migrate --force` selon le processus habituel de sauvegarde. La migration ajoute six tables RH et deux champs utilisateur ; elle ne modifie pas les soldes métier existants et n'accorde aucun quota fictif.
2. Attribuer `leaves.approve-final` aux véritables décideurs finaux dans la gestion des rôles si la direction/RH n'utilise pas déjà un rôle reconnu. L'administration globale reste habilitée. La permission est créée par migration et aucune affectation de rôle n'est inventée. Le catalogue de permissions est conservé lors d'un rollback pour préserver les affectations explicites.
3. Renseigner les soldes réels et les barèmes vérifiés du pays dans `/conges/parametres`. Le sexe n'est pas déduit des noms : l'éligibilité maternité est renseignée explicitement par les RH. Aucun barème fourni comme exemple dans le cahier des charges n'est imposé comme une règle de droit.
4. Configurer un transport e-mail opérationnel (`MAIL_MAILER=smtp` et les paramètres du fournisseur). `log`/`array` ne délivrent pas d'e-mail externe ; l'écran RH signale ce cas. Renseigner `FRONTEND_URL` pour les liens des courriels. Sans cette URL, aucun lien erroné vers le backend n'est fabriqué. Les notifications internes renvoient vers la demande et les notifications de conversation ouvrent le bon fil.
5. Conserver le disque Laravel `local` privé et persistant. Régler les plafonds HTTP/PHP pour cinq fichiers de 10 Mo : `upload_max_filesize` au moins `10M`, `post_max_size` et le proxy au moins `55M`. Les justificatifs ne sont jamais copiés dans le stockage public.
6. Mettre à jour le web et l'application mobile. L'ancien contrat de création de brouillons est refusé pour empêcher le contournement des soldes ; les sélecteurs d'absence des agendas ouvrent le nouveau formulaire RH. Les historiques déjà approuvés restent visibles. Un brouillon historique peut être converti explicitement avec les informations RH requises.
7. Effectuer la recette sur appareils Android/iOS avec le sélecteur de fichiers natif, un compte collaborateur et deux évaluateurs réels, puis vérifier la réception SMTP et le blocage effectif des visites. Les tests Flutter avec moteur et les compilations natives n'ont pas pu être certifiés dans cet environnement : sockets locales interdites, SDK Android et Xcode absents. Cette limite concerne les contrôles exécutables ici, pas un remplacement du sélecteur par des données simulées.

## Parcours livré

- Web : `/conges` (demandes personnelles, file de validation, détail et circuit, calendrier d'équipe) et `/conges/parametres` (soldes, journal, reports, barèmes, codes paie et responsable fonctionnel).
- Mobile : création et conversion d'un ancien brouillon, pièces justificatives natives, soldes annuels, détail/historique, décisions motivées, annulation et calendrier mensuel des absences accordées. Les décisions et justificatifs sont transmis en ligne ; aucun accord RH n'est mis en scène hors ligne. Le dernier état réellement reçu peut être consulté avec avertissement de péremption.
- Le sélecteur privé est également utilisé pour joindre des PDF/images à un message mobile : aperçu, retrait et envoi explicite. Les annonces restent en lecture seule pour leurs destinataires.
- Seul l'accord final débite le compteur, sous verrou utilisateur et avec un mouvement unique par demande. Les avis intermédiaires ne débitent rien. Rejet et annulation ferment les étapes suivantes sans leur inventer une décision.
- Une modification de date exige l'annulation puis une nouvelle soumission tant qu'aucun avis n'a été rendu. Une demande ayant reçu un avis ou un accord final ne peut pas être effacée ; l'historique reste conservé.
- Les nouveaux congés sont bloqués sur des journées calendaires ; le retour est inclus dans la période protégée, conformément au cahier des charges fourni. Les anciens horaires de permissions historiques ne sont pas réécrits.
- L'avertissement territorial utilise le pic de collaborateurs simultanément absents du même poste et pays, avec le seuil strictement supérieur à 50 %. Il ne compte pas comme simultanées des absences disjointes.
- Les exports PDF/XLSX contiennent les informations RH utiles, le code paie et les étapes réelles. Aucun transfert vers un prestataire de paie non identifié n'est simulé.

## Vérifications exécutées

- Suite backend complète, après vérification complémentaire : 238 tests réussis, 1457 assertions, incluant les 14 tests RH et cinq nouveaux parcours de comptes rendus, stock et reprise hors ligne.
- Les tests passent par les endpoints HTTP et une base temporaire migrée : soumission, hiérarchie séquentielle et fonctionnelle, groupe final, permission explicite, solde revérifié, débit unique, refus, annulation, non-contournement par les opérations groupées, confidentialité des documents, pays, synchronisation et calendrier historique/RH. Les e-mails et fichiers utilisent les transports de test ; cela ne certifie pas la livraison SMTP sur un serveur réel.
- Web : 21 fichiers de tests réussis, contrôle TypeScript et compilation Nuxt de production réussis. Les contrats vérifient les justificatifs requis et la conservation du véritable circuit d'approbation dans les exports.
- Mobile : 17 contrôles de contrat exécutés (congés/cache, messages, stocks/catalogue et formulaires patients). Analyse de `lib` et `tool` sans erreur ni avertissement ; 14 remarques préexistantes concernent les noms de constantes et les boutons radio.
- Les tests Flutter avec moteur ont été lancés, puis bloqués à l'ouverture du serveur local (`Operation not permitted` sur `127.0.0.1`). Ils ne sont pas déclarés réussis. Les compilations Android/iOS et l'ouverture du sélecteur système nécessitent un SDK Android / Xcode et un appareil ou simulateur autorisé.
- Les recherches de sources de données de démonstration et de KPI constants n'ont trouvé aucun jeu de données métier simulé à raccorder dans les sources actives parcourues. Les nombres d'exemple des tests restent confinés aux tests ; les effets visuels et la génération de mots de passe ne constituent pas des données métier. Un reliquat inutilisé de progression simulée dans le contrôleur des objectifs mobile a été supprimé.
- Aucun déploiement, envoi SMTP réel, migration de la base métier ou push n'a été effectué. Lors de la compilation mobile, définir `API_BASE_URL` avec l'URL réelle du backend accessible depuis l'appareil ; l'adresse locale de développement ne constitue pas une URL de production.
