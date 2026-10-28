# Rapports détaillés et exports

Les vues de gestion web proposent un aperçu détaillé et des téléchargements Excel (`.xlsx`) et PDF. L'aperçu montre au maximum 50 lignes et l'indique ; les fichiers contiennent toutes les lignes filtrées chargées par la vue, y compris celles des autres pages du tableau. Les rapports analytiques comprennent aussi les comparaisons par pays et par poste. Les écrans de création, connexion et configuration de secrets ne sont pas des rapports.

## Sources et périmètre

`POST /api/v1/reports/document`, protégé par Sanctum et limité à 20 appels/minute, est un service de **mise en forme de la vue courante**. Il ne recherche aucun enregistrement métier et ne remplace pas les API d'autorisation. Le client fournit les colonnes et cellules issues de ses données déjà autorisées et filtrées. Le document indique son origine, ses filtres, le pays actif, son auteur et sa date d'édition. Il ne constitue pas une attestation des données : le contenu est fourni par le client.

Les champs du client web sont explicitement sélectionnés dans `app/utils/reportExport.ts` ; les objets bruts, identifiants de connexion, mots de passe et jetons ne sont pas sérialisés. Les textes des brouillons de comptes rendus et leurs produits/échantillons/objections sont exclus. Les brouillons ne font pas partie de l'export des comptes rendus soumis. Un changement de pays pendant la génération annule la livraison du fichier précédent.

Le mobile utilise le même service pour les visites, les objectifs personnels, les objectifs de l'équipe et sa performance. Le fichier est transmis au partage natif, avec téléchargement de secours sur le web. Une indisponibilité réseau est affichée et l'export n'est pas placé dans la file des modifications hors ligne.

## Contrat de mise en forme

```json
{
  "format": "xlsx",
  "title": "Objectifs et performance",
  "filters": [{"label": "Poste", "value": "Superviseur"}],
  "summary": [{"label": "Objectifs atteints", "value": "3"}],
  "sections": [{
    "title": "Détail",
    "columns": ["Collaborateur", "Niveau d'atteinte (%)"],
    "rows": [["Afi Mensah", 125]]
  }]
}
```

La réponse est un fichier binaire avec `Content-Disposition: attachment` et son type MIME réel. Les erreurs sont des réponses JSON de validation. Limites explicites : 12 sections, 45 colonnes, 20 000 lignes par section, 100 000 cellules au total, 32 000 caractères par cellule. Aucun dépassement n'est tronqué ; il est refusé avec un message invitant à réduire le périmètre.

Excel conserve les nombres, zéros, booléens et textes ; les chaînes sont des cellules de texte explicites et ne sont jamais interprétées comme des formules. Le PDF utilise DejaVu Sans pour les accents et plusieurs pages si nécessaire. Les longs contenus sont présentés sous forme de fiches afin d'éviter la troncature des lignes d'un tableau. Les liens distants, JavaScript et PHP sont désactivés dans le moteur PDF.

## Exports interrogés directement sur le serveur

Les routes existantes `/export/activities`, `/export/duos`, `/export/trios`, `/export/expenses` et `/export/performance` renvoient désormais de vrais fichiers XLSX, et leurs variantes PDF un véritable PDF. Les deux dernières acceptent `format=pdf`. Les exports d'activités et dépenses limitent les résultats à l'utilisateur et à son équipe récursive (ou au périmètre global autorisé), puis au pays actif. L'export des activités exclut les congés ; `reports_only=1` conserve seulement les comptes rendus soumis. Les exports de coaching réutilisent les requêtes des tableaux de bord correspondants. Ces routes permettent des extractions API indépendantes de la vue courante ; les exports web privilégient la vue exacte, avec ses filtres locaux et sa recherche.

## Installation et vérification

Exécuter `composer install` à partir du fichier de verrouillage pour disposer de Dompdf et PhpSpreadsheet. Le mobile ajoute `share_plus` et nécessite un `flutter pub get`, puis une reconstruction de l'application pour enregistrer le plugin natif.

Tests : `ReportDocumentTest`, `BusinessReportExportsTest`, `DuoTrioCanevasEvaluationTest`, `tests/reportExport.test.mjs`, `tests/listStores.test.mjs` et `test/report_export_test.dart`.


## Planning comparé à la réalisation

L'analyse distingue le niveau d'atteinte des objectifs attribués du taux de réalisation du planning. Ce dernier vaut visites terminées / visites prévues × 100 ; sans planning, il reste non renseigné. Une visite validée est une autorisation de planning, pas une exécution. Les congés, annulations, refus et brouillons sont exclus. L'écart au planning correspond aux visites non réalisées, dont les manquées à replanifier, les visites en cours, en retard et à venir.

Le détail par visite comprend les dates/heures prévues, les véritables heures de début et de fin enregistrées, l'écart signé au démarrage et le résultat. Une heure manquante n'est jamais reconstituée. Les mêmes filtres par pays, poste, collaborateur et période s'appliquent au détail, aux synthèses et aux exports. La référence est le planning actuel de la période, sans prétendre reconstituer un planning historique avant modifications.

## Dossiers des délégués et analyses par pays

Le web propose `/delegues` et `/delegues/{id}`. Les API `GET /api/v1/delegates` et `GET /api/v1/delegates/{id}/overview` appliquent le pays actif et les droits de l'utilisateur : administration globale, équipe récursive ou propre dossier. Un délégué hors périmètre répond 404. La période est choisie parmi semaine, mois, trimestre, année et dates personnalisées pour le dossier.

Le dossier regroupe les visites, les comptes rendus publiés complets, le planning comparé à la réalisation, les plannings hebdomadaires, les objectifs, les congés/activités du calendrier et le portefeuille. La dernière position GPS garde sa source et son horodatage. Le portefeuille et la dernière position décrivent l'état actuel ; les autres onglets suivent la période. Les textes de brouillons ne sont pas envoyés, même si le visiteur est leur auteur. Aucune limite cachée de 50 visites n'est appliquée. Chaque onglet propose une recherche et des exports ; le dossier complet exporte tous les onglets sur la période.

Dans `/analytics`, les graphiques comparent les pays et suivent l'évolution des cohortes par date prévue. Les KPI distinguent réalisation du planning et atteinte des objectifs, puis détaillent comptes rendus, praticiens/établissements visités distincts, durée réelle moyenne et ponctualité. Les moyennes et taux sont pondérés par leurs dénominateurs ; aucune valeur n'est inventée sans observation. Les visites multi-praticiens conservent toutes leurs cibles pour le décompte distinct. La ponctualité utilise les horaires exacts, y compris pour les retards inférieurs à une minute. Les tableaux de données correspondants sont inclus dans les fichiers Excel et PDF.

Les raccourcis du dashboard transmettent le statut et les bornes de la période choisie aux visites et aux comptes rendus. Les dates du raccourci restent visibles et peuvent être effacées depuis la page d'arrivée.

## Contrôle du serveur et préparation des exports

Après déploiement du backend, exécuter depuis son dossier :

```sh
sh bin/prepare-reports.sh
```

Ce script installe les dépendances verrouillées, vérifie les extensions PHP, vide les caches Laravel et exécute `php artisan reports:check`. Il ne lance aucune migration et n’insère aucune donnée métier. Le contrôle produit réellement les deux formats et vérifie leur signature. Il doit être exécuté avec le PHP utilisé par l’application et un accès en écriture au dossier `storage`.

`composer install` contrôle aussi la présence des classes Dompdf et PhpSpreadsheet après la génération de l’autoloader. Si un moteur manque malgré tout sur une installation incomplète, l’API retourne une erreur 503 explicite plutôt qu’une exception « classe introuvable ». Les exports PDF utilisent un cache de polices sous `storage/framework/cache/dompdf`, sans écriture dans `vendor`.

La mémoire du rendu est configurable avec `REPORT_EXPORT_MEMORY_LIMIT` (512M par défaut). Le renderer relève uniquement une limite existante inférieure ; il ne réduit pas une limite supérieure ou illimitée. Les limites de taille et de cellules des requêtes restent appliquées. Le navigateur vérifie le type et la signature du fichier avant de proposer son téléchargement ; une page d’erreur HTML ou JSON n’est pas enregistrée comme un PDF ou un Excel.

Le test `PlanningTaskExportWorkflowTest` crée par API une activité de calendrier, un planning et une tâche dans la base de test. Il relit les ressources, exporte les données obtenues et ouvre réellement le classeur pour vérifier les valeurs. Il ne crée aucune donnée dans la base de production.
