---
name: biolabs-architecture
description: Frontières de responsabilité entre biophp, biotools, biophp-demo et biolabs, et règles pour décider où placer un calcul, une règle métier, une entité ou une page lors de la refonte de Plasmi::db. À charger avant de créer une classe, une entité Doctrine, un service ou un contrôleur dans biolabs, ou avant de proposer un ajout dans biophp/biotools.
---

# Architecture de l'écosystème et place de biolabs

## Rôle de chaque dépôt

| Dépôt | Rôle | Contient | Ne contient pas |
|---|---|---|---|
| `../biophp` (`amelaye/biophp`) | Bibliothèque de primitives biologiques | `Domain/` (Sequence, Cloning, Tools, Alignment, Parser, Database, Phylogenetics, Variants, Sequencing), `Api/`, `Legacy/` | UI, persistance applicative |
| `../biotools` (`amelaye/biotools`) | Outils de haut niveau (managers, Form Types, validateurs, rendu graphique) | `Service/*Manager`, `Form/`, `Validator/`, `Twig/` | Persistance LIMS |
| `../biophp-demo` | Vitrine web sans état des deux bibliothèques | Contrôleurs d'orchestration, templates Twig, traductions | Calculs, base de données métier |
| **`biolabs`** | **LIMS** issu de Plasmi::db : traçabilité des cibles, constructions, échantillons, productions | Entités Doctrine, migrations, permissions, audit, étiquettes, workflows d'échantillons, pages CRUD, import des données historiques | Algorithmes biologiques |

La spécification de biophp-demo (`../biophp-demo/.claude/FEATURES_PLASMIDB_CLAUDE.md`, §8) prévoit explicitement que le suivi des clones, stocks, préparations, expressions, purifications, séquençages et cristallisations vive **dans un module séparé** : c'est biolabs.

## Règle de placement

Pour chaque élément issu de Plasmi::db, poser les questions dans cet ordre :

1. **Est-ce un calcul ou une règle biologique réutilisable sans base de données ?** (Tm, reverse-complément, digestion, traduction, masse, design de primer)
   → biophp (`Domain/`) si c'est une primitive, biotools si c'est un outil composé.
   → Vérifier d'abord s'il existe déjà (voir tableau ci-dessous). S'il manque : **ne pas l'implémenter dans biolabs** ; rédiger une demande pour la bibliothèque concernée et bloquer la fonctionnalité dépendante ou la livrer sans ce calcul.
2. **Est-ce une donnée à conserver, tracer ou partager entre personnes ?** → biolabs (entité Doctrine).
3. **Est-ce une présentation d'un résultat de calcul ?** → template biolabs qui appelle le service biotools/biophp, en suivant les conventions de biophp-demo.

## Capacités déjà présentes (à confirmer dans la version installée)

Toujours vérifier dans `vendor/amelaye/biophp` et `vendor/amelaye/biotools` (ou dans `composer.lock`) avant d'utiliser une classe ; biophp-demo est actuellement sur biophp 2.0.1 / biotools 2.0.0.

| Besoin Plasmi::db | biophp | biotools |
|---|---|---|
| Plasmide, features, import/export GenBank, GFF, BED | `Domain/Cloning` : `Aggregate/Plasmid`, `Entity/PlasmidRecord`, `ValueObject/PlasmidFeature`, `Service/Mapper/GenbankPlasmidMapper`, `Service/Writer/GenbankWriter`, readers/writers GFF/BED | |
| Digestion de restriction (linéaire / circulaire), compatibilité d'extrémités | `Domain/Cloning/Service/LinearRestrictionDigestManager`, `CircularRestrictionDigestManager`, `RestrictionEndCompatibilityManager` ; catalogue `Domain/Sequence/Service/RestrictionEnzymeCatalog` | `RestrictionDigestManager` |
| Gibson | `Domain/Cloning/Service/GibsonAssemblyManager` | |
| Chevauchement de features | `Domain/Cloning/Service/FeatureOverlapManager` | |
| Tm | `Domain/Tools/Service/MeltingTemperatureCalculator` | `MeltingTemperatureManager` |
| PCR | | `PcrAmplificationManager` |
| Oligos | `Domain/Tools/Service/OligosManager` | |
| Traduction, tables génétiques alternatives | `Domain/Tools/Service/Codon/AlternateGeneticCodeTranslator` | `DnaToProteinManager` |
| ORF | `Domain/Tools/Service/OrfFinder` | |
| Propriétés protéiques (masse, pI…) | `Domain/Tools/Service/ProteinPropertiesCalculator`, `Domain/Sequence/Service/ProteinManager` | `ProteinPropertiesManager` |
| Séquence, FASTA | `Domain/Sequence/Service/SequenceManager`, `FastaWriter` | `FastaUploaderManager` |
| Design de primers de clonage, recette PCR, carte SVG, construction ARN | à venir (voir `../biotools/.claude/FEATURES_PLASMIDB_CLAUDE.md`) | lots BIOTOOLS-1 à 5 |

Ce tableau est un point de départ, pas une garantie : relire la signature réelle avant tout appel.

## Pile technique cible (alignée sur biophp-demo)

- PHP ≥ 8.2, Symfony 7.4, Doctrine ORM 3 + migrations, Twig, Symfony Form/Validator/Translation (FR + EN), PHPUnit 11 ;
- `amelaye/biophp` et `amelaye/biotools` comme dépendances Composer, mêmes contraintes de version que biophp-demo sauf décision explicite ;
- secrets uniquement dans `.env.local` (non versionné) ;
- conventions de templates, de navigation et de traductions reprises de biophp-demo ; ne pas copier son code, s'en inspirer.

## Principes de conception biolabs

- **Entités** : nommées selon les concepts de labo (`Clone`, `GlycerolStock`, `DnaPrep`, `Construct`, `Vector`, `Oligo`), pas selon les tables historiques (`clne`, `insrt`, `constr_plasmid`).
- **Séquences** : stocker la séquence normalisée **et** conserver la séquence brute importée quand elle diffère ; préciser l'alphabet et la topologie (linéaire/circulaire).
- **Quantités** : valeur + unité explicite (voir skill `wet-lab-conventions`) ; jamais de flottant nu.
- **Audit** : créé/modifié par un utilisateur réel (relation), horodatage immuable ; historique des changements d'état d'un échantillon.
- **Identifiants** : identifiant stable lisible pour les étiquettes (ex. `CL-000285`), distinct de la clé primaire.
- **Pièces jointes** : cartes de vecteurs, chromatogrammes, feuilles de criblage hors base, avec type MIME contrôlé.
- **Contrôleurs minces** : orchestration seulement ; règles métier LIMS dans des services biolabs testables ; calculs biologiques délégués.
- **Pas de PEAR, pas de Phli, pas d'ExtJS, pas de SOAP** hérités.
