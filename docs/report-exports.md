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
