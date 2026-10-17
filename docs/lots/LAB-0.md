# LAB-0 — Socle applicatif, sécurité et audit

Statut : **livré, en attente de relecture** (feu vert reçu le 2026-10-17).
Lot technique sans donnée de laboratoire : il n'a pas nécessité d'inventaire Plasmi::db ni de validation par un agent biologiste. Les lots suivants suivront le déroulé complet de `CLAUDE.md`.

## Objectif

Disposer d'une application Symfony qui démarre, où des personnes identifiées peuvent se connecter avec un rôle, et où toute entité future peut tracer qui l'a créée ou modifiée.

## Périmètre

| Inclus | Exclu (lots suivants) |
|---|---|
| Squelette Symfony 7.4 / Doctrine 2.19 + ORM 3 / Twig / Security / Translation FR+EN | Entités de laboratoire |
| Bundles `amelaye/biophp` 2.0.1 et `amelaye/biotools` 2.0.0 enregistrés | Appels aux services biologiques |
| Entité `User` (e-mail, nom affiché, rôles, actif, langue, utilisateur système) | Gestion des utilisateurs par interface (liste, édition) |
| Connexion par formulaire, CSRF, limitation des tentatives, déconnexion | Réinitialisation de mot de passe par e-mail, double authentification |
| Deux rôles (membre, administrateur) | Permissions fines par objet |
| Audit `createdAt/By`, `updatedAt/By` sur les entités auditables | Historique champ par champ (à concevoir avec les échantillons, LAB-4) |
| Commande `biolabs:user:create` | Import des comptes Plasmi::db (volontairement jamais importés) |
| Layout, page d'accueil, sélecteur de langue | Navigation des modules |
| Tests unitaires et fonctionnels | |

## Décisions

1. **Versions Doctrine** : DoctrineBundle **2.19** et MigrationsBundle **3.7**, comme biophp-demo. `amelaye/biophp` 2.0.1 injecte l'option `auto_generate_proxy_classes`, supprimée de DoctrineBundle 3 : la version 3 fait échouer la compilation du conteneur. À corriger dans biophp avant toute montée de version.
2. **Base de données** : SQLite en développement et en test (aucun serveur requis), MySQL 8 en production via `.env.local`. Les migrations sont écrites avec l'API `Schema` de Doctrine pour rester indépendantes de la plate-forme.
3. **Tables biophp** : biophp déclare ses propres entités (`sequence`, `feature`, `plasmid`, `collection`…). Elles sont masquées par un `schema_filter` : le schéma de biolabs ne contient que les tables préfixées `bl_`.
4. **Langue par défaut** : français, anglais disponible ; choix mémorisé en session (`?_locale=en`), comme dans biophp-demo.
5. **Rôles** : alignés sur Plasmi::db, qui ne distinguait aucun profil (colonne `perms` jamais lue ; tout utilisateur connecté peut créer, modifier et supprimer). Deux rôles seulement : `ROLE_MEMBER` (tout le travail de laboratoire) et `ROLE_ADMIN` (gestion des comptes, que le legacy n'encadrait pas). Des rôles intermédiaires pourront être ajoutés sur demande du laboratoire.
6. **Authentification** : comptes locaux, comme dans Plasmi::db. Pas de LDAP/SSO ; si le besoin apparaît, ce sera un lot dédié (le `User` aurait alors un identifiant externe).
7. **Conservation de l'audit** : sans limite et sans purge, comme dans Plasmi::db (colonnes `created_*`/`updated_*` jamais effacées). Une politique de rétention serait un lot dédié.
8. **Utilisateur système** : champ `system` pour les comptes non connectables (ex. « import Plasmi::db », prévu au lot LAB-7). Un compte système ne peut jamais s'authentifier.
9. **Mots de passe** : jamais en argument de ligne de commande. Saisie masquée, ou variable d'environnement `BIOLABS_USER_PASSWORD` en mode non interactif. Longueur minimale 12 caractères.
10. **Pas de git** : le dépôt n'est pas initialisé ; à décider par la personne qui pilote le projet.

## Questions tranchées

Les trois questions posées (distinction responsable d'équipe / gestionnaire de plateforme, LDAP/SSO, durée de conservation de l'audit) ont été tranchées par « faire comme le legacy » : voir décisions 5, 6 et 7.

## Définition de « terminé »

- [x] conteneur compilé ; `lint:container`, `lint:twig`, `lint:yaml` sans erreur
- [x] migration appliquée, annulée (`down`) puis réappliquée ; `doctrine:migrations:diff` répond « No changes detected »
- [x] `vendor/bin/phpunit` : 39 tests, tous verts (PHP 8.5.9, PHPUnit 11.5)
- [x] aucun secret versionné (`.env.local` ignoré par git) ; aucune donnée de Plasmi::db recopiée
- [ ] relecture `code-reviewer` et `test-reviewer` : **non faite** (agents créés en cours de session, non chargés ; à lancer après redémarrage)

## Points ouverts

1. **biophp 2.0.1 et DoctrineBundle 3** : incompatibles (option `auto_generate_proxy_classes` injectée par `AmelayeBioPHPExtension::prepend`). Le projet reste sur DoctrineBundle 2.19 / MigrationsBundle 3.7. À corriger dans biophp avant toute montée de version.
2. **`doctrine:schema:validate`** signale toujours un écart : `schema_filter` masque les tables biophp côté base, pas côté mapping. La vérification de synchronisation se fait avec `doctrine:migrations:diff`.
3. **Durée des tests** : ≈ 65 s pour 39 tests sur cette machine, soit environ 1,3 s par première requête d'un noyau. Cause non identifiée (ni cache, ni mode debug, ni routeur, ni listeners `kernel.request` ; l'attente se situe entre `kernel.controller_arguments` et `kernel.response`, et le processeur est inactif). À creuser si cela gêne.
4. **MySQL** : la migration et les tests ont été validés sur SQLite uniquement ; aucune instance MySQL n'était démarrée. À exécuter sur MySQL 8 avant un déploiement.
