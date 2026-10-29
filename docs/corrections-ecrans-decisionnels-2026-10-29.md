# Corrections des écrans décisionnels

- [x] Unifier le comptage exécutif avec les visites et le planning, ajouter la comparaison de périodes de même longueur.
- [x] Distinguer fonction, poste terrain et type de cible ; ajouter les filtres hiérarchiques dans le périmètre autorisé.
- [x] Relier le parcours web du délégué aux points GPS et étapes réels.
- [x] Fournir un service de conformité fondé sur des anomalies vérifiables et des décisions historisées.
- [x] Compléter les conversations : groupes, annonces et pièces jointes privées ; livrer l'espace web.
- [x] Livrer le cockpit exécutif et un centre de rapports avec filtres et exports.
- [x] Terminer la suite de validation finale et consigner ses résultats.

## Écrans et contrats

- `/executif` : indicateurs issus du même service que `/analytics`, comparaison avec la période précédente, pays, collaborateurs et évolution journalière. Les congés ne sont pas des visites. Les annulations et brouillons restent hors référence d'exécution.
- `/rapports-centre` : synthèse, performances, visites, comptes rendus publiés, planning/réalisation, objectifs et positions ; aperçu et exports PDF/XLSX via le service d'export existant. Les brouillons de comptes rendus restent privés.
- `/trajets/{id}` : parcours reçu pour une date, étapes de visites, export des points ; accessible depuis la carte et le dossier du délégué.
- `/anomalies` : doublons exacts, chronologie inversée et solde de lots négatif. Les règles de durée minimale et distance GPS sont désactivées tant qu'un responsable n'a pas configuré leurs seuils. Toute décision exige une note et conserve son auteur et sa date ; le collaborateur peut répondre à une demande d'explication. Ces décisions ne valident pas les comptes rendus de visite.
- `/communication` : conversations directes, groupes, annonces, participants et fichiers privés. Téléchargement soumis à la participation et au pays sélectionné. Les groupes et annonces ont un suivi des non-lus par participant. Le mobile reçoit les fichiers et respecte les annonces en lecture seule. L'évolution RH du même jour ajoute le sélecteur système de PDF/images et son raccordement à la messagerie mobile ; la compilation et la recette natives restent à réaliser sur un environnement équipé.
- `/administration/postes` : création, modification, désactivation et affectation des postes terrain par pays. Aucun poste n'est inventé ou affecté automatiquement. Le filtre `field_post_id` est distinct du filtre de fonction `role_id`. Le contrat utilisateur mobile conserve ces champs.

## Mise en service

Déployer le backend, installer ses dépendances avec `composer install --no-dev --optimize-autoloader`, puis exécuter `php artisan migrate --force` avec une sauvegarde conforme au processus de déploiement habituel, avant de mettre à jour les clients. Trois migrations ajoutent les constats/décisions, les pièces jointes privées et les postes terrain. Le disque privé Laravel `local` doit être persistant et accessible en écriture au serveur. Aucun fichier de message ne doit être publié sur le disque public.

Les données existantes ne reçoivent aucune affectation de poste arbitraire. Un administrateur crée les postes, puis affecte les collaborateurs. Un changement de pays efface l'ancienne affectation si aucun nouveau poste n'est fourni.

Les trajets ne reconstruisent ni routes, ni pauses, ni déplacements absents des données. La distance est une estimation géodésique entre points successifs, et l'absence de points conserve une distance inconnue. Les positions du centre de rapports sont un instantané explicitement indépendant du filtre de dates.

Les constats de conformité sont actuellement détectés à la consultation. Pour un volume important, une tâche planifiée et la pagination des messages/constats constituent une évolution à prévoir.

## Validation locale

- Backend : 219 tests réussis, 1298 assertions. Après le dernier enrichissement des relations de rapports, les 10 tests dédiés aux écrans décisionnels repassent également (67 assertions). La suite comprend les exports réellement générés et téléchargés en PDF/XLSX.
- Web : les 20 fichiers de tests passent, ainsi que les deux fichiers ciblés après l'ajout du poste aux exports. Le contrôle TypeScript passe. La compilation de production est vérifiée séparément avant le commit web.
- Mobile : quatre contrats exécutables de messages et de persistance du poste passent. L'analyse de `lib` et du contrat ne signale aucune erreur ni avertissement ; 14 remarques existantes concernent les noms de constantes et l'ancien usage des boutons radio.
- La validation sur navigateur connecté et sur appareil avec GPS, ainsi que les tests Flutter avec moteur, ne sont pas certifiées par ces contrôles. L'environnement bloque les sockets locales nécessaires à ces derniers.
- Aucun déploiement, aucune migration de la base métier et aucun push ne sont effectués dans cette correction.
