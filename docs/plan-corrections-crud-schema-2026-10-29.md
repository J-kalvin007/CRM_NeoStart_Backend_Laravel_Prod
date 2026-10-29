# Corrections des modules, CRUD et schéma — 29 octobre 2026

## Plan et tâches

- [x] Identifier les causes des erreurs visibles, vérifier les routes et tenter le diagnostic MySQL sans modifier les données métier.
- [x] Corriger les migrations et prévoir leur reprise après interruption ; fournir une réponse API exploitable quand le schéma manque.
- [x] Corriger le CRUD des campagnes et vérifier les parcours postes, communications et congés dans les tests backend.
- [x] Placer le tableau de bord en premier et contrôler les composants de toutes les vues Vue.
- [x] Exécuter la suite complète backend, les tests web, la vérification TypeScript et la compilation.
- [x] Rédiger les scénarios de recette et les commandes de mise à niveau.

**À faire sur l’environnement utilisé dans les captures :** sauvegarder MySQL puis appliquer les migrations. L’accès MySQL depuis l’environnement de correction échoue ; aucune migration ni modification de donnée n’a été exécutée sur cette base. La validation automatique utilise une base SQLite isolée.

## Causes et corrections

### 1. Synthèse décisionnelle, rapports et dossiers des délégués

Les requêtes utilisent le référentiel réel `field_posts` et l’affectation `users.field_post_id`. La base des captures n’a pas ces éléments. La migration existait dans le code mais n’était pas appliquée à cette base.

La migration des postes peut désormais reprendre si la table a été créée avant une interruption, sans effacer ses lignes. Les migrations de conformité, de messagerie et des congés suivent le même principe : chaque table et chaque nouvelle colonne est contrôlée séparément. Une seconde exécution conserve les enregistrements existants. Cela traite la reprise des étapes de ces migrations ; cela ne répare pas automatiquement un schéma modifié manuellement ou une contrainte étrangère créée de façon incorrecte.

Une table ou une colonne manquante provoque une réponse JSON **503 / `DATABASE_SCHEMA_NOT_READY`**, avec la commande de mise à niveau. Le détail SQL et les informations de connexion restent dans les journaux serveur, y compris lorsque `APP_DEBUG=true`. Les autres erreurs SQL donnent une réponse générique 500. Aucun remplacement par des indicateurs à zéro ou une fausse liste vide n’a été ajouté.

### 2. Communication interne

Le filtre utilise explicitement `conversations.country_id`. Les colonnes de pays et de créateur, les accusés de lecture et les pièces jointes sont installés par la migration de messagerie. La requête ne dépend plus de la résolution implicite d’un nom de colonne dans ses sous-requêtes.

La fenêtre de création reste dans la hauteur de l’écran et défile lorsque son contenu est long. Les erreurs de validation sont lisibles ; une ancienne réponse contenant du SQL est filtrée à l’affichage. La correction du schéma reste nécessaire pour créer et lire réellement les conversations.

### 3. Création et modification des campagnes

- Suppression de l’identifiant de pays codé en dur dans la page.
- Pays réel transmis depuis le formulaire ; en vue « Tous les pays », le pays doit être choisi explicitement. Le formulaire ne transforme pas « group » en identifiant métier.
- Le bouton reste désactivé pendant l’enregistrement réel. Suppression du faux délai de 500 ms et de la remise à zéro prématurée : une erreur conserve les champs.
- Les messages des champs renvoyés avec HTTP 422 sont affichés dans la fenêtre. Les erreurs de chargement disposent d’une action « Réessayer ».
- Contrôle du budget négatif et des dates, y compris lorsqu’une modification ne fournit qu’une seule des deux dates.
- Création et synchronisation des produits dans une transaction ; modification et synchronisation également atomiques.
- Contrôle des droits d’écriture : administration, direction, sales manager et superviseur ; les responsables sans accès global restent dans leur pays. Un pays sélectionné différent du pays envoyé est refusé.
- Une réponse d’écriture terminée après un changement de pays n’est pas injectée dans la liste du nouveau pays.
- Protection des textes affichés dans la fenêtre de détail contre l’injection HTML.
- La couverture de cible absente de l’API est indiquée « Non mesurée », sans pourcentage inventé.

**Défaut MySQL supplémentaire :** l’ancien ENUM de `campaigns.statut` acceptait `creation`, `deploiement`, etc., alors que l’API et le web utilisaient `brouillon`, `active`, etc. La nouvelle migration `2026_10_29_140000_align_campaign_statuses` élargit la colonne avant de convertir les valeurs. Elle conserve les campagnes, leur identifiant et leurs autres champs.

| Ancien statut | Statut commun API / web après migration |
|---|---|
| creation | brouillon |
| validation | validation |
| deploiement, execution, suivi, analyse | active |
| cloture | terminee |

Les anciennes sous-phases sont regroupées dans le cycle utilisé par l’API ; aucune sous-phase distincte n’est affichée artificiellement. La migration ne rétablit pas l’ancien ENUM lors d’un rollback, pour éviter de perdre les valeurs désormais valides.

Le Kanban contient aussi les campagnes suspendues. Ses boutons et ses déplacements suivent les transitions du backend :

- Brouillon → Validation.
- Validation → Brouillon ou Active.
- Active → Suspendue ou Terminée.
- Suspendue → Active ou Terminée.
- Terminée : aucune réouverture proposée.

### 4. Navigation et composants

Le tableau de bord est premier dans « Pilotage », y compris si l’ordre précédent des modules est conservé en mémoire. Son accès par défaut inclut le sales manager.

Le composant `AppLoading`, situé dans `components/common`, est importé explicitement sur toutes les vues qui l’utilisent. Son nom d’auto-import Nuxt était différent. Deux autres imports `Building2` manquaient dans l’import des produits et le formulaire de visite : ils sont corrigés.

Un test parcourt les balises de composants de toutes les vues Vue et vérifie leur résolution par un import, le registre des composants Nuxt ou les plugins globaux existants. Les messages de développement Nuxt DevTools et `<Suspense>` ne sont pas des erreurs de création et n’ont pas été masqués.

### 5. Routes API sans implémentation

Contrôle des actions de tous les contrôleurs déclarés dans le routeur. Deux anciennes routes d’affectation des campagnes à des unités organisationnelles appelaient des méthodes supprimées ; elles sont retirées, l’ancien référentiel ayant déjà été abandonné. Aucun appel web correspondant n’a été trouvé.

Le contrôleur des profils KOL n’implémente que la lecture utilisée par l’écran. Sa déclaration REST exposait aussi des actions inexistantes : elle est limitée à `index`. Ce travail ne crée pas un nouveau CRUD KOL ; il supprime des destinations qui produisaient une erreur serveur. Les CRUD réellement utilisés restent vérifiés séparément.

## Mise à niveau de votre environnement

À exécuter sur la machine qui fournit `http://localhost:8000`, avec la version corrigée du backend.

1. Sauvegarder la base `crm_data` avec votre utilisateur MySQL et vérifier que la sauvegarde a réussi. Exemple, en remplaçant `VOTRE_UTILISATEUR` :

```sh
mysqldump --single-transaction -u VOTRE_UTILISATEUR -p crm_data > "crm_data-avant-corrections-$(date +%Y%m%d-%H%M%S).sql"
```

2. Après réussite de la sauvegarde, depuis le dossier parent `skynity` :

```sh
cd CRM-backend
php artisan migrate:status
php artisan migrate --force
php artisan optimize:clear
php artisan migrate:status
```

3. Vérifier que les migrations de conformité, messagerie, postes, congés et harmonisation des campagnes sont marquées exécutées. Si une migration échoue, conserver son message et corriger cette cause avant de poursuivre la recette. Ne pas utiliser `migrate:fresh`, `migrate:reset` ni supprimer la table des migrations sur la base métier.
4. Redémarrer les processus PHP persistants s’il y en a. Relancer le frontend en développement ; pour un déploiement compilé, reconstruire et redémarrer le frontend.
5. En production, utiliser `APP_DEBUG=false` et vérifier que le frontend pointe vers l’API du même environnement.

L’application n’exécute aucune migration automatiquement à l’ouverture d’une page.

## Scénarios de recette manuelle

Utiliser une base de recette sauvegardée, avec deux pays existants, un administrateur, un superviseur et deux collaborateurs. Les libellés « Recette » ci-dessous servent à identifier les enregistrements saisis manuellement ; aucune donnée fictive n’est installée dans l’application.

| N° | Actions | Résultat attendu |
|---|---|---|
| 1 | Se connecter avec chaque rôle disponible ; ouvrir la barre latérale. | « Tableau de bord » est le premier lien de Pilotage, devant Synthèse décisionnelle et Centre de rapports. |
| 2 | Après les migrations, ouvrir `/executif`, `/rapports-centre`, `/delegues`, puis le détail d’un délégué. | Réponses API 200 ; aucun message `field_posts` manquant ; données issues du pays et de la période sélectionnés. Une absence de donnée est distinguée d’une erreur. |
| 3 | Dans `/administration/postes`, créer « Recette poste » dans le pays sélectionné, modifier sa ville, affecter un collaborateur du même pays, puis essayer de supprimer le poste. | Création 201, édition 200, affectation réelle visible. Suppression refusée tant qu’un collaborateur y est affecté. Réaffecter le collaborateur avant de supprimer le poste de recette. |
| 4 | Essayer d’affecter à un collaborateur un poste d’un autre pays ; essayer un nom de poste déjà utilisé dans le même pays. | Validation refusée ; aucun enregistrement partiel. |
| 5 | Dans `/communication`, créer une conversation directe avec un destinataire visible et un premier message ; ouvrir avec le destinataire puis répondre. | Conversation réelle visible uniquement aux participants autorisés ; message envoyé et réponse visibles après actualisation. La fenêtre tient dans l’écran. |
| 6 | Créer un groupe avec titre et deux destinataires, envoyer un document autorisé, faire lire par un membre puis l’autre. Retirer un membre et retenter son téléchargement. | Fichier réel téléchargé par les membres actuels ; lectures indépendantes ; accès refusé au membre retiré et à un tiers. |
| 7 | Créer un groupe sans titre, puis soumettre un message ou un fichier non conforme. | Validation lisible ; aucun SQL affiché ; saisie conservée pour correction. Une annonce créée par un responsable est en lecture seule pour son destinataire. |
| 8 | Dans `/campagnes`, pays Togo sélectionné, créer une campagne avec nom, budget, dates et description ; passer en vue Table, modifier, rouvrir et actualiser la page. | POST 201 et valeurs persistées ; pays Togo réel dans la requête ; aucun pays fixe de démonstration. Le type et la description sont conservés. |
| 9 | Basculer vers « Tous les pays », ouvrir Nouvelle campagne et ne pas choisir de pays ; sélectionner ensuite Bénin et enregistrer. | Soumission désactivée sans pays ; création autorisée dans le pays choisi si les droits le permettent. La campagne n’apparaît pas sous Togo. |
| 10 | Créer avec une fin antérieure au début ou un budget négatif. Provoquer ensuite une erreur 422 côté API dans les outils réseau. | Dates/budget refusés ; les messages des champs sont visibles ; la fenêtre reste ouverte avec la saisie ; aucun faux succès ni effacement après 500 ms. |
| 11 | Tester Brouillon → Validation → Active → Suspendue → Active → Terminée. Tenter un déplacement Terminée → Active. | Parcours valide persistant, colonne Suspendues visible ; réouverture interdite et statut conservé côté serveur. |
| 12 | Avec un superviseur, créer/modifier une campagne de son pays ; essayer une campagne d’un autre pays et avec un délégué sans rôle de gestion. | Gestion du pays autorisée ; autre pays refusé ; délégué refusé pour l’écriture. |
| 13 | Ralentir une création de campagne dans les outils réseau, changer de pays avant la réponse, puis attendre et actualiser. | Aucune campagne de l’ancien pays ne pollue la nouvelle liste. L’écriture déjà reçue peut exister dans son pays d’origine : vérifier ce pays avant de recréer pour éviter un doublon manuel. |
| 14 | Dans le détail d’une campagne, saisir une description contenant des caractères `<`, `>`, `&`, puis ouvrir Détails. | Texte affiché comme texte ; aucun HTML utilisateur exécuté ; couverture absente indiquée « Non mesurée ». |
| 15 | Supprimer la campagne de recette puis actualiser. | Réponse 204 ; campagne retirée ; consultation de son ancien identifiant refusée. La suppression backend est logique. |
| 16 | Ouvrir campagnes, commandes, événements, ventes, grossistes, configuration, pays, produits et utilisateurs pendant leur chargement ; ouvrir l’import produits et une création de visite. | Aucun avertissement « Failed to resolve component: AppLoading » ou `Building2`. |
| 17 | Parcourir les sept jeux de données du Centre de rapports et tester un export PDF puis Excel dans un périmètre contenant des données. | Navigation réelle, données cohérentes avec les filtres, fichiers lisibles. Les tests automatiques de documents restent inclus dans la suite backend. |
| 18 | Dans `/conges`, soumettre une demande réelle avec les soldes configurés en recette, lire le circuit, faire valider successivement par les responsables autorisés puis accorder au dernier niveau. | Demande persistante, justificatifs privés, solde débité une seule fois à l’accord final, absence reflétée dans le planning. Les brouillons d’un autre utilisateur restent privés. |

Les cas de schéma absent et de reprise après interruption sont exécutés automatiquement sur la base de test. Ne pas supprimer de table ou de colonne dans la base métier pour reproduire ces erreurs.

## Vérifications automatiques et limites

- Backend : suite complète réussie sur la version finale, **247 tests et 1 938 assertions**, dont 9 nouveaux scénarios de régression CRUD/schéma et le contrôle des actions du routeur.
- Web : 22 fichiers de tests réussis, dont 7 nouveaux scénarios sur les composants, l’ordre du menu, les formulaires et le périmètre pays ; vérification TypeScript et compilation Nuxt réussies.
- Mobile : 24 contrôles de contrats Dart réussis (stock, messagerie/postes, congés, patients et objections). Aucun changement du code mobile dans cette correction.
- Vérifications de documents PDF/Excel, de confidentialité des comptes rendus, de congés et de pièces jointes incluses dans les tests backend existants.
- Les données des tests sont isolées. Aucune donnée de démonstration n’est ajoutée au produit.
- Pas de validation navigateur connectée à votre MySQL : accès à cette base indisponible ici. Les parcours web sont exercés par les tests des composants/formulaires, les appels HTTP backend de test, TypeScript et la compilation ; la recette interactive ci-dessus reste à effectuer après migration.
- La conversion du schéma a été exécutée automatiquement sur SQLite ; l’exécution MySQL de votre installation reste à confirmer avec les commandes précédentes. Aucun test natif Android/iOS supplémentaire n’est revendiqué.
- Aucun push ni déploiement effectué dans cette intervention.


## Intégration des changements distants du 29 octobre

Les branches `origin/melchior` et `origin/main` ont reçu des corrections parallèles. Les conflits ont été intégrés avec conservation des contrôles de pays, des droits d’écriture, de la saisie après erreur et des transitions de campagne.

- La normalisation des anciens statuts (`creation`, `deploiement`, `cloture`, etc.) se fait désormais à l’entrée de l’API, avant validation : la base et les réponses restent dans le vocabulaire commun. Une ancienne valeur ne contourne pas les transitions autorisées.
- Un client qui fournit le pays concret dans `X-Country-Id` peut omettre ce pays dans le corps de création. En vue consolidée, le choix explicite reste obligatoire. Un pays de sélection inconnu ne déclenche pas de repli vers un autre pays.
- La migration distante `2026_10_29_130000_change_statut_to_string_on_campaigns_table` est conservée pour les bases qui l’ont déjà enregistrée. Son annulation ne réintroduit plus un ENUM incompatible avec les données existantes. Sur une installation où seule la migration 140000 a déjà été exécutée, relancer `php artisan migrate --force` pour enregistrer également cette migration distante.
- L’organisation du menu venant du distant est conservée, avec vérification de l’absence de doublons et priorité au tableau de bord.
- Les alias de composants ajoutés par le distant sont conservés. Les chemins de scan Nuxt incorrects (`app/app/components`) sont retirés au profit du scan standard.
- Le frontend ne prend pas `localhost` comme API par défaut de déploiement. Le développement local se configure avec `NUXT_PUBLIC_API_BASE=http://localhost:8000/api` et `NUXT_PUBLIC_STORAGE_BASE=http://localhost:8000/storage`. En production, vérifier les valeurs effectives du service déployé.
- Les tests CRUD du distant utilisent maintenant un superviseur autorisé ; le refus d’écriture pour un collaborateur sans droit reste testé séparément.

Après intégration : 23 tests backend ciblés réussis (574 assertions), 22 fichiers de tests web réussis et vérification TypeScript réussie. La suite backend complète de 247 tests indiquée plus haut correspond à la version avant cette fusion ; elle n’est pas présentée comme une nouvelle exécution complète après fusion.
