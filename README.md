# CRM Backend API

## Table des Matières
1. [Introduction](#introduction)
2. [Prérequis](#prérequis)
3. [Installation](#installation)
4. [Architecture et Fonctionnalités](#architecture-et-fonctionnalités)
5. [Intégration Mobile (Offline & Sync)](#intégration-mobile-offline--sync)
6. [Notifications Multi-Canaux](#notifications-multi-canaux)
7. [Génération Swagger](#génération-swagger)

## Introduction
Backend robuste pour la gestion CRM, intégrant une API complète (PHP 8 / Laravel) pour les utilisateurs web et mobiles (délégués médicaux, managers, etc.). L'API utilise les attributs PHP 8 pour la documentation Swagger.

## Prérequis
- PHP 8.2+
- Composer
- MySQL 8+
- Node.js & NPM (pour le frontend/vite si besoin)

## Installation
```bash
git clone <repository>
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
```

## Architecture et Fonctionnalités
- **RBAC Complet** : Gestion des rôles et permissions (via Spatie). Filtrage automatique des données (Global Scopes `HasPerimeter`) basé sur le territoire et la filiale du Délégué/Manager.
- **Tables de Référence (Lookups)** : Spécialités, segmentations, potentiels, influences, formes galéniques, types d'activités, etc.
- **Gestion des Stocks** : Entrées, sorties, attributions, distributions avec contrôle strict et système d'alertes via notifications.
- **Activités & Comptes Rendus** : Gestion complète des visites terrain, incluant un workflow de validation des comptes rendus de visite.
- **Fichiers et Médias** : Endpoint unifié (`/api/v1/media/upload`) pour la gestion des signatures, avatars, et e-detailing.

## Intégration Mobile (Offline & Sync)
Une interface de synchronisation robuste est exposée pour permettre à l'application mobile (Flutter) de fonctionner de manière totalement déconnectée :
- **PULL (`/api/v1/sync/pull`)** : Récupère uniquement les enregistrements modifiés ou créés depuis le dernier `last_sync` (Contacts, Accounts, Activities, Products, etc.). Inclut un noeud `deleted` pour répercuter les suppressions.
- **PUSH (`/api/v1/sync/push`)** : Permet au mobile d'envoyer un lot de créations/modifications hors-ligne. Utilise la logique d'idempotence basée sur un `local_uuid` généré par le mobile pour prévenir les doublons de synchronisation.

## Notifications Multi-Canaux
L'application intègre un service de notifications multi-canaux (`App\Services\NotificationService`) qui alerte les utilisateurs lors d'événements clés :
- **In-App (Base de données)** : Historique des notifications consultable dans l'application.
- **Push Mobile (FCM)** : Firebase Cloud Messaging.
- **Email** : Envoi de mails récapitulatifs.
*Exemples de déclencheurs : Soumission/Validation de compte rendu, Stock d'échantillons critique, Assignation de visite.*

## Génération Swagger
La documentation de l'API est générée automatiquement à partir des attributs PHP 8 (`#[OA\Get]`, `#[OA\Post]`, etc.).
```bash
php artisan l5-swagger:generate
```
L'interface Swagger est ensuite accessible sur `/api/documentation`.
