# LAB-2 — Cibles

Statut : **fiche arrêtée et implémentée sur feu vert du 17 octobre 2026** ; revues `bioinfo-reviewer`, `code-reviewer`, `test-reviewer` à faire. Les agents biologistes n'ont pas été sollicités : les décisions ci-dessous sont celles d'un biologiste moléculaire, à confirmer par le laboratoire (voir « Questions ouvertes »).
Sources : `subunit.php`, `product.php`, `complex_subunit.php` et leurs vues `_form.phtml`, DDL et données du dump (lecture seule). Les affirmations sur le comportement du legacy citent le fichier.

## Objectif

Permettre au laboratoire de déclarer ce qu'il veut produire : un **complexe** de protéines, ses **gènes** (CDS d'un organisme) et les **produits** visés (la région du CDS à exprimer, protéine, ARN ou ADN), avec la séquence dérivée et vérifiée plutôt que saisie deux fois.

## Ce que faisait Plasmi::db

| Table | Lignes (dump) | Constat |
|---|---|---|
| `complex` | 1 | nom (32 car.), description (1000). |
| `complex_subunit` | 1 | lien n-n **sans clé primaire**, `created_by` entier à 0. |
| `subunit` | 1 | nom (127), commentaire, `db_name` (enum embl/ncbi-genbank/dbj/sgd/swissprot), `db_definition`, `subunit_seq` (**jamais éditée**, vide), `subunit_cds`, `subunit_cds_product_name` (**11 car.**), `species_id`, `subunit_html_req` (vide, requête vers une base externe). |
| `product` | 1 | nom (**30 car.**), type protein/rna/dna, `product_target_seq` (**jamais saisie** : `views/product/_form.phtml` impose une règle « required » sur un champ absent du formulaire), `product_dna_seq`, fenêtre `subunit_start_pos`/`subunit_end_pos`. |

Défauts constatés (nouveaux, à ajouter à `legacy-defects.md`) :
- `views/subunit/_form.phtml` réécrit le CDS **mis en forme** (`Seq::PrettyView_`) dans la base : espaces et retours chariot stockés dans la séquence.
- `views/subunit/_form.phtml` traduit toujours avec `Translator::translateToStop` sans table génétique : la table de l'espèce (`species_ncbi_trans_tab`) n'est pas utilisée. Faux pour *M. jannaschii* corrigé en table 11, pour les mitochondries, etc.
- Fenêtre du produit : aucune validation, aucune unité, aucune base (0/1). Dans l'unique donnée, `1–239` pour un gène de **233 aa** : la fenêtre compte les codons du **produit avec étiquette 6His** (717 nt = 702 nt de CDS − 3 nt du codon stop + 18 nt de 6His), pas des positions de la sous-unité. Le champ `subunit_end_pos` mélange donc deux référentiels.
- Le produit dupliquait la séquence ADN du gène (`product_dna_seq`) : deux copies qui peuvent diverger.
- Noms tronqués par les colonnes courtes (11 et 30 caractères).

## Périmètre

### Inclus
Trois listes éditables avec archivage, sur le même mécanisme que LAB-1 (menu « Cibles ») : **Complexe**, **Gène**, **Produit**. Pages de détail du gène (traduction, masse) et du produit (séquences dérivées, masse).

### Exclu, avec la raison
| Élément | Raison |
|---|---|
| `subunit_seq` (séquence génomique/ARNm complète) | Jamais saisie ni lue. Une séquence de contexte relève d'un enregistrement GenBank, pas du gène. |
| `subunit_html_req` | Requête HTML vers une base externe, vide, fragile. Remplacé par la base source + l'accession dans le nom. |
| Étiquette (6His…) et vecteur | Ils appartiennent à la **construction** (LAB-3). Le produit est la région du CDS à exprimer, sans étiquette. |
| Stœchiométrie du complexe | Absente du legacy et de la donnée. À ajouter si le laboratoire en a besoin (question 3). |
| BLAST / alignement CLUSTAL | SOAP legacy, non repris. |

## Modèle

Les trois entités ont `AuditableTrait`, `name` unique (clé normalisée, archivées comprises), `active`, `notes`, comme LAB-1.

| Entité | Champs propres | Remarques |
|---|---|---|
| `Complex` | gènes (n-n) | `notes` reçoit `complex_desc`. |
| `Gene` | organisme (obligatoire), nom du produit du CDS (≤ 50), base source (facultative), définition de la base source (facultative), **CDS brut** et **CDS normalisé** | `notes` reçoit `subunit_comment`. |
| `Product` | gène (obligatoire), type (protéine/ARN/ADN), début et fin **en nucléotides, base 1, bornes incluses, sur le CDS normalisé** (vides = CDS entier) | Aucune séquence stockée : elle est **dérivée** du gène. |

### Pourquoi dériver la séquence du produit
Une seule copie de la séquence évite la divergence constatée dans le legacy. Si le CDS du gène est modifié, la fenêtre du produit garde ses coordonnées : un avertissement affiche le nombre de produits concernés.

## Calculs et services biophp / biotools

| Besoin | Service | Remarque |
|---|---|---|
| Normaliser/valider le CDS | `DnaSequence` (biophp) | alphabet IUPAC accepté ; les symboles non ACGT donnent un avertissement. |
| Traduction sous la table de l'organisme | `AlternateGeneticCodeTranslator::translateCodon` (biophp) | tables 1–6, 9–16, 21–26, 29, 30, 32, 33 (`GeneticCodeTable`). |
| Transcription | `DnaSequence::toRna()` (biophp) | |
| Masse ADN/ARN | `SequenceManager::molwt` (biophp) | |
| Masse protéique | `ProteinPropertiesManager` (biotools) | |
| Mise en forme/numérotation | non reprise | affichage simple en blocs de 10 sans modifier la donnée. |

biolabs ne contient **aucun algorithme biologique** : le service local `SequenceAnalysis` ne fait qu'orchestrer ces appels (lecture des codons un à un, découpage du CDS par coordonnées).

## Règles de validation

**Bloquantes**
- nom vide ou déjà utilisé (clé normalisée, archivées comprises) ;
- Gène : organisme absent ; CDS vide ; symbole hors alphabet ADN IUPAC (`DnaSequence`) ; longueur non multiple de 3 ;
- Produit : gène absent ; début ou fin seul ; début < 1 ; début > fin ; fin > longueur du CDS ; **pour une protéine, fenêtre hors cadre** (début ≡ 1 mod 3 et longueur multiple de 3).

**Avertissements**
- Gène : ne commence pas par ATG (les codons d'initiation alternatifs existent) ; ne se termine pas par un codon stop (sous la table de l'organisme) ; **codon stop interne** (readthrough, sélénocystéine, pseudogène ou mauvaise table) ; symboles dégénérés (N, R…) présents ;
- Produit : fenêtre qui contient le codon stop final ; CDS du gène modifié depuis la création (produits liés comptés) ;
- noms, quasi-doublons, entrée utilisée : mêmes avertissements que LAB-1.

## Correspondance historique → biolabs

| Legacy | biolabs | Transformation |
|---|---|---|
| `complex.complex_name`, `complex_desc` | `Complex.name`, `notes` | |
| `complex_subunit` | `Complex.genes` | n-n ; orphelins listés ; `created_by` ignoré |
| `subunit.subunit_name` | `Gene.name` | |
| `subunit.subunit_cds` | `Gene.cdsSequenceRaw`, `cdsSequence` | brut conservé, normalisé = sans espaces ni retours, majuscules |
| `subunit.subunit_cds_product_name` | `Gene.productName` | |
| `subunit.db_name`, `db_definition` | `Gene.sourceDatabase`, `sourceDefinition` | `ncbi/genbank` → `genbank` |
| `subunit.species_id` | `Gene.organism` | via `bl_legacy_map` (LAB-7) |
| `subunit.subunit_comment` | `Gene.notes` | |
| `subunit.subunit_seq`, `subunit_html_req` | — | non repris |
| `product.product_name`, `product_target_type`, `product_desc` | `Product.name`, `type`, `notes` | |
| `product.subunit_id` | `Product.gene` | |
| `product.subunit_start_pos`, `subunit_end_pos` | `startNt`, `endNt` | **non convertis automatiquement** : unité et référentiel ambigus. Le produit est importé avec un avertissement et la fenêtre dans `notes` ; un biologiste recalcule. |
| `product.product_dna_seq`, `product_target_seq` | — | `product_dna_seq` sert à **retrouver la fenêtre** : s'il est une sous-séquence exacte du CDS (sans étiquette ni stop), la fenêtre est déduite ; sinon quarantaine. |

Cas de l'unique ligne du dump (V-ATPase, VMA4:6H) : le produit n'est pas une sous-séquence du CDS (étiquette 6His ajoutée). Il est mis en quarantaine ; la région à exprimer est le CDS entier sans le codon stop, soit 1–699, et l'étiquette 6His relève du lot LAB-3.

## Droits, archivage, audit
Identiques à LAB-1 : tout membre crée, modifie, archive et restaure ; aucune suppression ; audit `createdAt/By`, `updatedAt/By`.

## Questions ouvertes
1. Quels codons d'initiation alternatifs le laboratoire accepte-t-il sans avertissement ?
2. Un codon stop interne doit-il rester seulement un avertissement ? (choix actuel : oui, pour permettre le readthrough et la sélénocystéine)
3. La stœchiométrie des sous-unités d'un complexe est-elle utile (ex. V-ATPase) ?
4. Faut-il conserver un lien vers l'accession exacte (GenBank, SGD) ? Le legacy ne gardait que le nom et une définition libre.

## Tests prévus
Règles bloquantes et avertissements ci-dessus (longueur non multiple de 3, symbole `1` dans le CDS, fenêtre hors cadre, début > fin, fin > CDS, table 11 vs table 1 sur un codon différent, codon stop interne), dérivation de la séquence d'un produit, unicité, archivage, accès, échappement, fixtures strictement synthétiques.

## Définition de « terminé »
- [x] entités, migration (API Schema), `doctrine:migrations:diff` sans changement
- [x] écrans FR/EN
- [x] tests PHPUnit verts, fixtures synthétiques
- [ ] revue `bioinfo-reviewer` (traduction, coordonnées), `code-reviewer`, `test-reviewer`
- [x] aucun secret ni donnée personnelle de Plasmi::db
