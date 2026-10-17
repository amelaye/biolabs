---
name: plasmidb-refactoring
description: Méthode pas à pas pour refondre une fonctionnalité ou une table de Plasmi::db (../plasmidb-0.9.1) en module biolabs Symfony/Doctrine s'appuyant sur biophp et biotools — inventaire, fiche fonctionnelle validée par les agents biologistes, découpage en lots, implémentation, migration de données, revue. À charger dès qu'une demande consiste à « migrer », « refactoriser », « reprendre » ou « porter » quoi que ce soit de Plasmi::db.
---

# Refonte Plasmi::db → biolabs

Principe : **conserver la science et les données, pas l'architecture**. Chaque lot est livrable seul.

Skills associés : `plasmidb-domain` (connaissance de l'existant), `biolabs-architecture` (où placer quoi), `wet-lab-conventions` (unités, échantillons), `bioinformatics` (garde-fous scientifiques).

## Agents biologistes

| Agent | Quand le solliciter |
|---|---|
| `molecular-biologist` | Tout ce qui touche gènes, primers, PCR, inserts, vecteurs, plasmides construits, features, enzymes, tags, séquençage |
| `protein-biochemist` | Expression, purification, lots protéiques, protéolyse, spectrométrie de masse, cristallisation |
| `lab-workflow-biologist` | Modèle d'échantillons, traçabilité, stocks, emplacements, étiquettes, statuts, permissions par rôle de labo, plan de migration des données |
| `bioinfo-reviewer` | Revue indépendante en lecture seule de tout code ou migration pouvant modifier un résultat ou une donnée scientifique |

Les agents biologistes **spécifient et valident** ; ils n'écrivent pas le code de production.

## Étapes d'un lot

### 1. Inventaire (lecture seule)

- tables, contrôleur, vue `_form.phtml`, accessoires et classes `Bio/` impliqués ;
- volumétrie et qualité des données dans le dump (orphelins, `0000-00-00`, unités, séquences sales) ;
- défauts connus (`plasmidb-domain/references/legacy-defects.md`) ; ajouter ceux trouvés.

### 2. Fiche fonctionnelle

Rédiger `docs/lots/<LOT>.md` contenant :

- objectif métier en une phrase, du point de vue du biologiste ;
- acteurs et rôles ;
- entités et relations proposées, avec unités et contraintes ;
- règles métier (bloquantes / avertissements) ;
- calculs nécessaires et **service biophp/biotools** qui les fournit (ou « manquant → demande à ouvrir ») ;
- correspondance table/colonne historique → champ biolabs, avec la transformation appliquée ;
- ce qui n'est **pas** repris et pourquoi ;
- questions ouvertes pour la personne qui pilote le projet.

Faire valider la fiche par l'agent biologiste compétent (et `lab-workflow-biologist` dès qu'il y a des échantillons). Les réponses « à confirmer par le labo » restent des questions ouvertes : ne pas trancher à leur place.

### 3. Implémentation

- entités Doctrine + migration ; value objects pour quantités et séquences ;
- services biolabs pour les règles LIMS ; appels biophp/biotools pour les calculs ;
- Form Types, contrôleurs minces, templates Twig FR/EN dans les conventions de biophp-demo ;
- tests : unitaires des règles, fonctionnels des pages, et tests d'import sur fixtures **synthétiques** inspirées du dump.

### 4. Import des données historiques (si demandé)

- commande Symfony idempotente, en lecture seule sur la source, avec mode `--dry-run` produisant un rapport ;
- aucune donnée personnelle ni secret importé (voir `plasmidb-domain`) ;
- séquence brute conservée ; valeurs sans unité marquées « présumées » ;
- rapport : lignes importées, rejetées (avec raison), orphelines, transformées.

### 5. Revue

- `bioinfo-reviewer` sur tout code touchant séquences, coordonnées, unités ou calculs ;
- agent biologiste du lot pour vérifier que la fiche est respectée ;
- `php -l`, suite PHPUnit, `bin/console doctrine:schema:validate` lorsque l'environnement le permet. Ne jamais déclarer un test passé sans l'avoir exécuté.

## Découpage indicatif

| Lot | Contenu | Dépend de |
|---|---|---|
| LAB-0 | Squelette Symfony aligné sur biophp-demo, sécurité, utilisateurs, audit | — |
| LAB-1 | Référentiels : organismes, souches, marqueurs, promoteurs, origines, tags, fournisseurs, enzymes (via catalogue biophp) | LAB-0 |
| LAB-2 | Cibles : complexes, gènes/sous-unités, produits | LAB-1 |
| LAB-3 | Clonage : oligos, PCR, inserts, vecteurs, constructions, features (via `Domain/Cloning`) | LAB-2 |
| LAB-4 | Échantillons : clones, stocks glycérol, préps ADN, séquençages, emplacements, étiquettes | LAB-3 |
| LAB-5 | Production : protocoles d'expression, expressions et aliquots, purifications, lots protéiques | LAB-4 |
| LAB-6 | Caractérisation : MS, protéolyse, cristallisation | LAB-5, priorité basse |
| LAB-7 | Import des données Plasmi::db | lots concernés |
| LAB-X | Outils de design (primers, PCR recipe, carte) intégrés depuis biotools quand publiés | lots BIOTOOLS correspondants |

L'ordre peut changer à la demande de la personne qui pilote le projet ; ne pas lancer un lot suivant sans sa validation.

## Interdits

- copier du code de `../plasmidb-0.9.1/` dans biolabs ;
- écrire un calcul biologique dans biolabs pour contourner une version manquante de biophp/biotools ;
- modifier `../biophp`, `../biotools`, `../biophp-demo` ou `../plasmidb-0.9.1` sans demande explicite ;
- importer des secrets, mots de passe, sessions ou emails réels ;
- marquer une construction ou un échantillon « valide » si une règle bloquante échoue.
