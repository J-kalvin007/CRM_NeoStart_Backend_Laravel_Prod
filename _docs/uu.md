Oui. En reprenant l’ensemble du cahier fonctionnel fourni, il faut éviter de simplement transformer chaque fonctionnalité en écran. Il faut d'abord dégager les rôles métier, leurs responsabilités, puis organiser les écrans autour de leurs workflows.

Le document fait apparaître 5 rôles opérationnels principaux : Directeur Régional, Directeur Commercial, Directeur Marketing, Superviseur et Délégué Médical. Le document mentionne aussi l’analyste dans le résumé initial, mais ses responsabilités fonctionnelles sont beaucoup moins détaillées ; je le traiterais donc comme un rôle transversal d’analyse/reporting, à confirmer.

1. Vue globale des rôles

Je structurerais le CRM ainsi :

Rôle	Fonction principale	Interface
Directeur Régional	Pilote le niveau régional, valide les plans d'actions, gère les ressources/supports et paramètres stratégiques	Web
Directeur Commercial (DC)	Pilote les ventes, objectifs commerciaux, équipes et plans d'actions	Web
Directeur Marketing	Pilote les supports, campagnes et valide les plans de tournées	Web
Superviseur	Manage les délégués, contrôle le terrain, valide les données et accompagne les délégués	Web + éventuellement tablette
Délégué Médical	Exécute les tournées, visites, présentations, distributions et saisies terrain	Mobile/Tablette
Analyste	Analyse les données et produit les rapports	Web

Le principe architectural serait donc :

                         DIRECTION
                             │
               ┌─────────────┴─────────────┐
               │                           │
     Directeur Régional            Directeur Commercial
               │                           │
               │                    Objectifs commerciaux
               │                    Suivi des ventes
               │                    Plans d'actions
               │
        Directeur Marketing
               │
        Supports / campagnes
               │
               ▼
          SUPERVISEUR
               │
       ┌───────┴────────┐
       │                │
   Délégué 1        Délégué 2 ...
       │
       ▼
 Prescripteurs / Établissements
       │
       ▼
      Visites
       │
       ▼
 Comptes rendus / ventes /
 échantillons / objections
2. Directeur Régional
Mission

Le Directeur Régional est le niveau de pilotage régional et de validation supérieure.

Dans les règles fournies, il intervient notamment pour :

valider les plans d'actions après le DC ;
gérer les supports/matériels depuis le niveau supérieur ;
définir ou contrôler certaines règles stratégiques ;
définir la capacité de portefeuille des délégués ou superviser cette règle ;
gérer les produits au niveau régional.
Écrans
DR-01 — Dashboard régional
┌─────────────────────────────────────────────┐
│ Dashboard Régional                          │
├─────────┬─────────┬─────────┬──────────────┤
│ CA      │ Ventes  │ Objectifs│ Équipes     │
│ 85 M    │ 92%     │ 87%      │ 12          │
├─────────────────────────────────────────────┤
│ Performance par secteur                     │
│ █████████████████  Secteur A                │
│ ███████████        Secteur B                │
│ ███████████████    Secteur C                │
├─────────────────────────────────────────────┤
│ Plans d'actions à valider                   │
│ [Plan 01] [Plan 02] [Voir]                 │
└─────────────────────────────────────────────┘
DR-02 — Plans d'actions à valider

Liste :

superviseur ;
zone ;
période ;
date de soumission ;
statut ;
actions.

Statuts :

Brouillon
   ↓
Soumis au DC
   ↓
Réajusté par DC
   ↓
Soumis au DR
   ↓
VALIDÉ
DR-03 — Détail / validation d'un plan

Le DR doit pouvoir :

consulter le plan ;
voir les modifications du DC ;
commenter ;
valider ;
éventuellement demander une modification.
DR-04 — Produits

Liste des produits :

Produit | DCI | Laboratoire | Classe | Forme | Statut

Actions :

Ajouter ;
Modifier ;
Consulter ;
Activer/désactiver.
DR-05 — Ajouter produit

Formulaire :

Laboratoire *
DCI *
Nom du produit *
Forme galénique *
Conditionnement *
Classe thérapeutique *
Indications *
Posologie *
Effets secondaires
Description

Le rattachement au laboratoire est obligatoire puisque le modèle impose qu'un produit soit fourni par un seul laboratoire.

DR-06 — Supports / matériels
Supports
────────────────────────────────────
Nom       Type       Quantité    Statut
Brochure  Marketing  500         Disponible
Gadget    Gadget     200         Disponible
...
DR-07 — Distribution des supports
Support
Quantité
Destinataire
Zone
Date
Commentaire

[Distribuer]
DR-08 — Paramètres de portefeuille
Nombre maximal de prescripteurs / délégué

Délégué       Maximum
Sophie        100
Marc          100
Nadia         80

Cela correspond à la règle selon laquelle le niveau supérieur définit la capacité du portefeuille.

3. Directeur Commercial

Le DC est le pilote commercial.

Ses principales fonctions sont :

définir les objectifs commerciaux ;
définir les objectifs des superviseurs ;
contrôler les ventes ;
gérer les plans d'actions avec le superviseur ;
réajuster les plans ;
traiter les demandes remontées par les délégués via les superviseurs ;
analyser les performances.

Le document initial décrit également pour le DC une vision globale des équipes, des territoires, des performances et des rapports.

Écrans
DC-01 — Dashboard commercial
┌──────────────────────────────────────────┐
│ PERFORMANCE COMMERCIALE                  │
├──────────┬──────────┬──────────┬─────────┤
│ CA       │ Objectif │ Réalisé  │ Écart   │
│ 120 M    │ 130 M    │ 118 M    │ -9%     │
├──────────────────────────────────────────┤
│ Ventes par région                        │
│ Région A       ███████████ 92%           │
│ Région B       █████████    81%           │
│ Région C       ████████████ 96%           │
├──────────────────────────────────────────┤
│ Alertes                                  │
│ ⚠ Objectif non atteint                   │
│ ⚠ Région B en retard                     │
└──────────────────────────────────────────┘
DC-02 — Objectifs des superviseurs

C'est un écran très important.

Objectifs commerciaux
────────────────────────────────────────

Période : Septembre 2026

Superviseur       CA cible     Qté cible
────────────────────────────────────────
Karim             20 000 000   1 500
Paul               15 000 000   1 200
...

[Créer objectif]

Le DC définit les objectifs en quantité vendue et chiffre d'affaires.

DC-03 — Validation / réajustement des plans d'actions
Plan d'action #PA-024

Superviseur : Karim
Zone : Secteur Nord
Période : Septembre

Objectifs
──────────────────
CA : 20 M
Quantité : 2 000

Actions proposées
──────────────────
Visites : 250
Campagne : X
Produit prioritaire : Y

[Demander modification]
[Réajuster]
[Soumettre au DR]

Le workflow est :

Superviseur → DC → DR → Validation → Exécution.

DC-04 — Ventes

Filtres :

période ;
région ;
superviseur ;
délégué ;
laboratoire ;
produit ;
grossiste.

Indicateurs :

CA
Quantité vendue
Objectif
Réalisation
Écart
%
DC-05 — Équipes / territoires
Région
  └── Secteur
       └── Superviseur
            ├── Délégué
            ├── Délégué
            └── Délégué

Avec une vue carte si nécessaire.

DC-06 — Demandes reçues
Demandes
──────────────────────────────────────────
Délégué   Superviseur   Type       Statut
Sophie    Karim         Support    Validée
Marc      Karim         Matériel   En attente
...

Workflow :

Délégué → Superviseur → DC.

4. Directeur Marketing

Le rôle marketing doit être séparé du commercial.

Le document de base lui attribue :

gestion des supports CLM ;
campagnes ;
analyse des objections ;
segmentation ;
rapports marketing ;
validation finale des plans de tournées.
Écrans
DM-01 — Dashboard marketing
Supports utilisés
Campagnes actives
Produits présentés
Objections
Taux de couverture
DM-02 — Bibliothèque des supports
┌─────────────────────────────────────────┐
│ Bibliothèque CLM                        │
├─────────────────────────────────────────┤
│ Support      Produit     Version Statut │
│ DiaBex v3    DiaBex      3.0     Actif  │
│ Étude 2024   DiaBex      1.0     Arch.  │
└─────────────────────────────────────────┘

[+ Ajouter un support]

Le support peut être :

présentation ;
vidéo ;
PDF ;
infographie.
DM-03 — Création support
Nom
Produit lié
Type
Version
Date
Auteur
Contenu
Public cible
Fichier

[Enregistrer]
[Valider]
DM-04 — Campagnes
Campagne
Produit
Période
Cible
Message clé
Support CLM
Objectif
Statut
DM-05 — Analyse des objections
Objection             Fréquence
───────────────────────────────
Effets secondaires       42%
Prix                     28%
Concurrent               18%
...
DM-06 — Validation des plans de tournées

C'est un écran essentiel.

Plans de tournées
────────────────────────────────────
Délégué    Superviseur    Période
Sophie     Karim          Septembre

Statut : En attente de validation

[Voir le plan]

[Valider]
[Demander modification]

Workflow :

Délégué → Superviseur → Directeur Marketing → Exécution.

5. Superviseur

À mon avis, c'est le rôle qui possède le plus grand nombre de workflows opérationnels.

Il est au milieu entre la direction et les délégués.

Il doit :

gérer son équipe ;
définir les plans d'actions ;
définir les objectifs des délégués ;
valider les prescripteurs ;
valider les établissements ;
planifier les duos ;
effectuer au moins deux duos/mois ;
faire les comptes rendus de duo ;
suivre les visites ;
suivre les ventes ;
gérer le portefeuille de prescripteurs ;
traiter les demandes des délégués ;
faire du coaching.
Écrans
SUP-01 — Dashboard superviseur
MON ÉQUIPE : 8 DÉLÉGUÉS

Visites        325
Objectifs      87%
Couverture     91%
Duos           1 / 2
Alertes        7

────────────────────────────────
Sophie       93%       🟢
Marc         84%       🟠
Nadia        60%       🔴
SUP-02 — Mon équipe
Délégué | Visites | Objectif | CA | Couverture | Actions

Sophie     42/45     93%      ...      90%       [Voir]
Marc       38/45     84%      ...      82%       [Voir]
Nadia      27/45     60%      ...      65%       [Voir]
SUP-03 — Objectifs des délégués
Délégué : Sophie

Période : Septembre

Objectif visites       45
Objectif couverture    90%
Objectif CA            5 000 000
Objectif quantité      400

[Enregistrer]

Le principe hiérarchique est :

DC → objectifs superviseurs → objectifs délégués.

6. Superviseur — Plan d'actions
SUP-04 — Mes plans d'actions
Plans d'actions

Septembre
Zone Nord
Statut : Brouillon

[Modifier]
[Soumettre au DC]

Octobre
Zone Nord
Statut : Validé
SUP-05 — Créer plan d'action
Période
Zone
Objectifs
Actions
Produits prioritaires
Nombre de visites
Cibles
Ressources nécessaires

[Enregistrer brouillon]
[Soumettre au DC]
7. Superviseur — Validation des prescripteurs
SUP-06 — Prescripteurs à valider
┌─────────────────────────────────────────┐
│ NOUVEAUX PRESCRIPTEURS                  │
├─────────────────────────────────────────┤
│ Dr X | Cardiologue | Secteur A          │
│      | Établissement principal           │
│                                         │
│ [Voir] [Valider] [Rejeter]              │
└─────────────────────────────────────────┘

Le délégué crée, le superviseur valide.

8. Superviseur — Validation établissements

Même principe :

Établissements à valider

Nom
Type
Adresse
Secteur
Créé par
Date

[Voir]
[Valider]
[Rejeter]

Le superviseur peut également créer directement un établissement.

9. Superviseur — Duos

Je créerais un module complet "Duos", et pas simplement un bouton dans le dashboard.

SUP-07 — Liste des duos
Duos - Septembre

Objectif mensuel : 2

Réalisés : 1
Planifiés : 1

────────────────────────────
Date       Délégué     Statut
05/09      Sophie      Réalisé
18/09      Marc        Planifié
SUP-08 — Planifier un duo
Délégué *
Date *
Zone *
Motif *

Objectifs du duo
────────────────
[ ] Évaluer présentation
[ ] Évaluer couverture
[ ] Coaching
[ ] Accompagnement
[ ] Autre

Prescripteurs ciblés
Établissements ciblés

[Planifier]
SUP-09 — Compte rendu du duo
DUO #D-025

Objectifs prévus
────────────────
Visites : 5
Présentations : 4
Objectif coaching : ...

Résultats
────────────────
Visites : 4
Présentations : 4

Atteinte : 85%

Commentaires
Difficultés
Points forts
Actions recommandées

[Enregistrer]
[Soumettre]

Cette séparation est importante puisque les objectifs sont définis avant le duo puis comparés aux résultats après le duo.

10. Superviseur — Portefeuille prescripteurs
SUP-10 — Portefeuilles
Délégué : Sophie

Capacité : 100
Actuels : 100
Disponibles : 0

────────────────────────────
Prescripteur       Visites
Dr Martin             12
Dr Dupont              8
Dr X                    2
Dr Y                    1

Si un nouveau prescripteur doit être ajouté :

SUP-11 — Réaffectation
Nouveau prescripteur
Dr Bernard

Portefeuille plein.

Prescripteur à retirer :
Dr Y
Visites : 1

Nouveau délégué :
[Marc ▼]

[Réaffecter]

Important : ne jamais supprimer le prescripteur. Il faut créer une historique d'affectations. Le document insiste explicitement sur cette conservation de l'historique.

11. Superviseur — Suivi des ventes
SUP-12 — Ventes de mon équipe
                Objectif       Réalisé       %
Sophie          5 000 000      4 500 000     90%
Marc            4 000 000      3 200 000     80%
Nadia            3 500 000      2 100 000     60%
──────────────────────────────────────────────
TOTAL           12 500 000      9 800 000     78%

Filtres :

période ;
délégué ;
produit ;
grossiste ;
secteur.
12. Délégué médical

Le délégué est l'utilisateur terrain.

Son interface doit donc être radicalement différente de celle des directions.

Le document prévoit une utilisation tablette + mobile, notamment pour l'agenda, les visites, les fiches médecins, le CLM, les échantillons, les comptes rendus et le mode hors connexion.

Je recommande une navigation très simple :

┌──────────────────────────┐
│ Bonjour Sophie           │
│                          │
│ Aujourd'hui              │
│ 5 visites                │
│                          │
│ ┌──────────────────────┐ │
│ │ 09:00 Dr Martin      │ │
│ │ Cardiologue          │ │
│ │ [Démarrer visite]    │ │
│ └──────────────────────┘ │
│                          │
│ ┌──────────────────────┐ │
│ │ 11:00 Dr Dupont      │ │
│ │ [Démarrer visite]    │ │
│ └──────────────────────┘ │
│                          │
│ Accueil Médecins Visites│
│ Objectifs Plus          │
└──────────────────────────┘
13. Délégué — Mes tournées
DEL-01 — Agenda
aujourd'hui ;
semaine ;
mois ;
carte ;
itinéraire.
DEL-02 — Créer plan de tournée
Date
Zone
Prescripteurs
Ordre de visite
Motif
Produit/campagne

[Enregistrer brouillon]
[Soumettre au superviseur]

Puis :

EN ATTENTE SUPERVISEUR
        ↓
VALIDÉ SUPERVISEUR
        ↓
EN ATTENTE MARKETING
        ↓
VALIDÉ
        ↓
EXÉCUTABLE
14. Délégué — Prescripteurs
DEL-03 — Mes prescripteurs
Recherche 🔍

[A] [B] [C]

Dr Martin
Cardiologue
Établissement principal
Dernière visite : 12/09

Dr Dupont
Généraliste
...
DEL-04 — Ajouter prescripteur
Informations personnelles
Nom
Prénom
Spécialité
Téléphone
Email

Établissement principal
Établissements secondaires

Zone

[Enregistrer]

Le nouveau prescripteur passe ensuite au superviseur pour validation.

15. Délégué — Visite

C'est probablement l'écran métier le plus important de toute l'application mobile.

Je le concevrais comme un workflow étape par étape :

1. Prescripteur
       ↓
2. Motif
       ↓
3. Produits présentés
       ↓
4. Support utilisé
       ↓
5. Objections
       ↓
6. Échantillons
       ↓
7. Gadgets
       ↓
8. Informations commerciales
       ↓
9. Compte rendu
       ↓
10. Prochaine action
       ↓
11. Soumission
16. Écran visite — Produits
Produits présentés

☑ DiaBex
☐ CardioPlus
☐ Produit X

Support :
[DiaBex CLM v3 ▼]

Temps de présentation
[automatique]

Le CRM peut enregistrer l'utilisation du support CLM, notamment les slides consultées et la durée de présentation.

17. Écran visite — Échantillons
Échantillons remis

Produit       Quantité
DiaBex        2 boîtes

N° lot        LOT-2026-0451

[+ Ajouter]

Signature du médecin

        ✍️
──────────────────

[Confirmer remise]

Les données essentielles comprennent produit, quantité, date et éventuellement numéro de lot.

18. Écran visite — Gadgets
Gadgets / équipements

Type
[Stylo ▼]

Quantité
[10]

[Ajouter]
19. Écran visite — Compte rendu
Compte rendu

Objection
[Effets secondaires ▼]

Réponse donnée
[........................]

Niveau d'intérêt
⭐ ⭐ ⭐ ⭐ ☆

Observations
[........................]

Action suivante
[Envoyer étude ▼]

Prochaine visite
[09/10/2026]

[Enregistrer brouillon]
[Soumettre définitivement]

Cela correspond directement au workflow brouillon → modification → soumission définitive.

20. Délégué — Ventes
DEL-05 — Saisie des données grossistes
Période : Septembre

Grossiste
[Grossiste A ▼]

Produit
[DiaBex ▼]

Quantité vendue
[250]

CA HT
[3 500 000]

[Enregistrer]

Les données sont ensuite consolidées au niveau superviseur puis direction.

21. Délégué — Mes objectifs
MES OBJECTIFS

Visites
████████████░░ 80%
32 / 40

Couverture
█████████████░ 83%
15 / 18

CA
████████░░░░░ 68%

Quantité
██████████░░ 75%
22. Délégué — Mes demandes
Mes demandes

#025  Besoin de supports     En attente
#024  Matériel               Validée
#023  Autre                  Rejetée

[+ Nouvelle demande]

Création :

Type
Objet
Description
Pièces jointes

[Soumettre au superviseur]
23. Analyste

Je mettrais l'analyste comme rôle transversal, avec une interface essentiellement orientée données.

ANA-01 — Dashboard analytique
Filtres
Période | Région | Secteur | Produit

────────────────────────────
Visites
Couverture
CA
Quantités
Objectifs
Prescripteurs
Produits
ANA-02 — Analyse des ventes
ANA-03 — Analyse des visites
ANA-04 — Analyse des prescripteurs
ANA-05 — Rapports
Rapport visites
Rapport ventes
Rapport objectifs
Rapport produits
Rapport activité terrain

[Exporter Excel]
[Exporter PDF]

Je ne donnerais toutefois pas encore à l'analyste les mêmes droits que les directions : lecture/analyse par défaut, sauf si les règles métier lui accordent explicitement des droits de modification.

24. Les écrans transversaux

Tous les rôles auront également :

AUTH-01 — Connexion
Email
Mot de passe

[Se connecter]

Mot de passe oublié ?
SYS-01 — Notifications
🔔 Notifications

Plan de tournée validé
Nouveau prescripteur à valider
Objectif non atteint
Nouvelle demande
Plan d'action à valider

Le document prévoit explicitement les notifications temps réel selon le rôle.

SYS-02 — Recherche globale

Recherche :

prescripteur ;
établissement ;
produit ;
visite ;
support ;
rapport.
SYS-03 — Profil / paramètres
SYS-04 — Historique / audit

Le document prévoit également un journal des actions permettant de savoir qui a créé, modifié ou validé une donnée.

25. Ce que je mettrais dans les menus
Directeur Régional
Dashboard
│
├── Plans d'actions
├── Produits
├── Laboratoires
├── Supports & matériels
├── Portefeuilles
├── Équipes
├── Rapports
└── Paramètres
Directeur Commercial
Dashboard
│
├── Objectifs
├── Plans d'actions
├── Ventes
├── Équipes
├── Territoires
├── Demandes
├── Prévisions
├── Rapports
└── Paramètres
Directeur Marketing
Dashboard
│
├── Plans de tournées
├── Bibliothèque CLM
├── Campagnes
├── Objections
├── Segmentation
├── Rapports Marketing
└── Paramètres
Superviseur
Dashboard
│
├── Mon équipe
├── Plans d'actions
├── Objectifs
├── Tournées
├── Visites
├── Prescripteurs
├── Établissements
├── Duos
├── Portefeuilles
├── Ventes
├── Demandes
├── Coaching
├── Alertes
└── Rapports
Délégué
Accueil
│
├── Agenda
├── Mes tournées
├── Mes visites
├── Mes prescripteurs
├── Mes établissements
├── Produits
├── Supports CLM
├── Échantillons
├── Mes ventes
├── Mes objectifs
├── Mes demandes
└── Notifications
26. Les 10 workflows à matérialiser dans les wireframes

Pour moi, les wireframes doivent surtout permettre de voir les workflows, pas seulement les pages.

Workflow 1 — Tournée
Délégué
   ↓
Création plan
   ↓
Brouillon
   ↓
Soumission
   ↓
Superviseur
   ↓
Validation
   ↓
Directeur Marketing
   ↓
Validation
   ↓
Exécution
Workflow 2 — Plan d'action
Superviseur
   ↓
Plan d'action
   ↓
DC
   ↓
Réajustement éventuel
   ↓
DR
   ↓
Validation
   ↓
Exécution
Workflow 3 — Prescripteur
Délégué
   ↓
Création
   ↓
Superviseur
   ↓
Validation
   ↓
Prescripteur actif
Workflow 4 — Établissement
Délégué
   ↓
Création
   ↓
Superviseur
   ↓
Validation
Workflow 5 — Visite
Agenda
 ↓
Démarrer visite
 ↓
Produits
 ↓
CLM
 ↓
Échantillons
 ↓
Gadgets
 ↓
Objections
 ↓
Compte rendu
 ↓
Brouillon
 ↓
Soumission définitive
Workflow 6 — Duo
Superviseur
 ↓
Planifier
 ↓
Objectifs
 ↓
Réalisation
 ↓
Compte rendu
 ↓
Résultats
 ↓
Atteinte objectifs
Workflow 7 — Demande
Délégué
 ↓
Demande
 ↓
Superviseur
 ↓
Retenue / rejetée
 ↓
DC
Workflow 8 — Objectifs
DC
 ↓
Objectifs superviseurs
 ↓
Superviseur
 ↓
Objectifs délégués
 ↓
Suivi
Workflow 9 — Vente
Grossiste
 ↓
Données ventes
 ↓
Délégué
 ↓
Saisie / consultation
 ↓
Superviseur
 ↓
Consolidation
 ↓
DC / DR
 ↓
Analyse
Workflow 10 — Portefeuille
Nouveau prescripteur
 ↓
Portefeuille plein ?
 ↓ OUI
Analyse visites
 ↓
Prescripteur peu visité
 ↓
Retrait de l'affectation
 ↓
Nouvelle affectation
 ↓
Historique conservé
27. Le point le plus important pour la conception

Je ne ferais pas un CRM où tous les rôles voient les mêmes menus avec des boutons désactivés.

Il faut concevoir des espaces différents selon le rôle, car les préoccupations sont différentes :

Direction Régionale

« Qu'est-ce qui est validé ? Quelles ressources ? Quelle performance régionale ? »

Direction Commerciale

« Est-ce que mes objectifs commerciaux sont atteints ? Où sont les écarts ? »

Marketing

« Est-ce que les bons supports et campagnes sont utilisés sur le terrain ? »

Superviseur

« Est-ce que mes délégués font leurs tournées, atteignent leurs objectifs et ont besoin d'un accompagnement ? »

Délégué

« Qui dois-je visiter aujourd'hui, quoi présenter, quoi distribuer et quel compte rendu dois-je faire ? »

C'est cette logique qui doit guider les wireframes.

Architecture finale des interfaces
                    MEDCRM PRO
                         │
        ┌────────────────┼────────────────┐
        │                │                │
      WEB              WEB             MOBILE
        │                │                │
   ┌────┴─────┐     ┌────┴─────┐          │
   │ Direction│     │Superviseur│          │
   │          │     │           │          │
   │ DR       │     │ Équipe    │      Délégué
   │ DC       │     │ Duos      │          │
   │ Marketing│     │ Visites   │      Terrain
   │ Analyste │     │ Objectifs │      Visites
   └──────────┘     │ Validation│      Échantillons
                    │ Coaching  │      Comptes rendus
                    └───────────┘      Hors ligne

La prochaine étape logique n'est donc pas encore de coder les pages. Il faut transformer cette analyse en un vrai cahier de wireframes, écran par écran, avec pour chaque écran : nom, objectif, utilisateur, données affichées, boutons/actions, formulaire, statut, navigation vers l'écran suivant. C'est cela qui permettra ensuite de passer proprement à Figma puis à Nuxt/Flutter.