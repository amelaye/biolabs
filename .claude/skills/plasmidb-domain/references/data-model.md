# Modèle de données Plasmi::db 0.9.1

Source : `../plasmidb-0.9.1/plasmidb/plasmidb-0.9.1.sql` (MySQL 5.0, **MyISAM**, `latin1_general_ci`).

Conséquences globales :

- **aucune contrainte de clé étrangère** (MyISAM) : l'intégrité référentielle n'a jamais été garantie, il faut l'auditer avant migration ;
- encodage latin1 avec mojibake visible (`-20Â°C`) : conversion UTF-8 à vérifier caractère par caractère sur les symboles `°`, `µ`, `±`, `→` ;
- dates par défaut `0000-00-00 00:00:00` : à convertir en `NULL`, jamais en date réelle ;
- colonnes d'audit `created_at/by`, `updated_at/by` présentes presque partout, mais `*_by` contient tantôt un email, tantôt `'0'`, tantôt un id numérique ;
- FK typées `varchar(11)` dans plusieurs tables (`primer.company_id`, `primer.product_id`, `pcr.constr_plasmid_id`, `insrt.constr_plasmid_id`, `product_prep.expr_id`, `product_prep.purif_protocol_id`, `crystal_screen_exp.crystal_screen_id`, `expr.expr_protocol_id`) ;
- valeur `0` utilisée comme « aucun » à la place de `NULL`.

## 1. Cibles biologiques

| Table | Rôle | Colonnes clés | Remarques |
|---|---|---|---|
| `complex` | Complexe protéique (ex. V-ATPase) | `complex_name`, `complex_desc` | |
| `complex_subunit` | n-n complexe ↔ sous-unité | `complex_id`, `subunit_id` | **pas de clé primaire** ; `created_by` en `int` |
| `subunit` | Gène / sous-unité | `subunit_seq`, `subunit_cds`, `species_id`, `db_name` (`embl`, `ncbi/genbank`, `dbj`, `sgd`, `swissprot`), `db_definition`, `subunit_html_req` | séquence brute et CDS séparés ; `subunit_html_req` = requête HTML vers base externe |
| `species` | Organisme | `species_ncbitxn` (taxon NCBI), `species_ncbi_trans_tab` (table génétique NCBI) | **la table génétique doit piloter la traduction** |
| `product` | Cible produite | `product_target_type` (`protein`/`rna`/`dna`), `product_target_seq`, `product_dna_seq`, `subunit_id`, `subunit_start_pos`, `subunit_end_pos` | séquences avec espaces et `\r\n` ; bornes : unité (résidu ou nt) et base (0/1) à établir depuis `PrimerGenerator` |

## 2. Clonage

| Table | Rôle | Colonnes clés | Remarques |
|---|---|---|---|
| `primer` | Oligo | `primer_seq` (≤100), `primer_coding_seq`, `primer_tm`, `primer_codingtm` (`int` !), `company_id`, `product_id`, `db_seq_id` | casse mixte signifiante (minuscules = site/clamp) ; Tm tronqué en entier |
| `pcr_primer_design_strategy` | Stratégie de design | `..._primer_f`, `..._primer_r` | `id` non auto-incrémenté |
| `pcr` | Réaction PCR | `pcr_fprimer_id`, `pcr_rprimer_id`, `db_seq_id` (matrice), `constr_plasmid_id` (matrice plasmidique, varchar), `pcr_desired_seq`, `insrt_id`, `pcr_date` | `created_by` défaut `'0'` |
| `insrt` | Fragment à insérer | `insrt_seq`, `insrt_fragtype` (`restriction`/`PCR`), `insrt_5prime_site`, `insrt_3prime_site` (→ `enzyme.id`), `pcr_id`, `constr_plasmid_id`, `product_id` | origine soit PCR soit plasmide donneur |
| `enzyme` | Enzyme de restriction | `enzyme_name`, `enzyme_sequence` (IUPAC, ex. `GACNNNNNNGTC`) | **position de coupure absente** : à prendre dans le catalogue biophp, pas ici |
| `std_plasmid` | Vecteur commercial | `std_plasmid_seq`, `std_plasmid_polylink`, carte PDF en blob (+ `std_plasmid_map_type`), `marker_id`, `reporter_id`, `promoter_id`, `puri_fusion_id` + `..._pos` (`none`/`N-Term`/`C-Term`), `detect_id` + `detect_pos`, `cleavage_id`, `sttype_id`, `company_id` | `cleavage_id` → **aucune table** ; données de vecteur dénormalisées |
| `constr_plasmid` | Plasmide construit | `seq`, `std_plasmid_id` (backbone), `insrt_id`, `constr_plasmid_intermed` | `seq` souvent vide dans les données |
| `constr_plasmid_product` | n-n construit ↔ produit | | polycistronique = plusieurs produits |
| `feature` | Annotation de plasmide | `plasmid_id`, `name`, `start_pos`, `length`, `is_reverse`, `fk_table`, `fk_id` | **lien polymorphe** (`fk_table`) ; `id` non auto-incrémenté ; pas de franchissement d'origine représentable explicitement |

Référentiels du clonage : `marker` (résistance, ex. Ampicilline 50 µg/mL dans `marker_desc`), `promoter`, `repliorig` (origine de réplication ; colonne mal orthographiée `repliorg_name`), `reporter`, `puri_fusion` (6xHis, 10xHis, GST…), `detect_fusion`, `seqorigin` (origine de la séquence de l'insert), `company`.

## 3. Échantillons et stocks

| Table | Rôle | Colonnes clés | Remarques |
|---|---|---|---|
| `clne` | Clone d'un plasmide construit | `clne_name`, `constr_plasmid_id`, `clpos` (clone positif, `text`), `clne_date` | `clne_comments` typé **`int`** (bug : commentaires perdus) |
| `glycerol_stock` | Stock glycérol | `clne_id`, `strain_id`, `glycerol_stock_date` | pas d'emplacement (congélateur/boîte/position) |
| `dna_prep` | Préparation d'ADN | `dna_prep_type` (`mini`…`giga`), `dna_prep_conc` (float, **unité implicite**), `clone_id` (≠ `clne_id`), `company_id` (kit) | |
| `sequencing` | Résultat de séquençage | `clne_id`, `sequencing_text` | pas de chromatogramme, pas d'alignement stocké |
| `stocktype` | Type de stock | `sttype_short`, `sttype_desc`, `strain_id` | |
| `strain` | Souche hôte | `strain_name` (ex. BL21(DE3)), `strain_desc` | génotype en texte libre |

## 4. Production protéique

| Table | Rôle | Colonnes clés | Remarques |
|---|---|---|---|
| `expr_protocol` | Protocole d'expression | `expr_protocol_medium` (`2YT`/`LB`/`MAI`), `expr_protocol_volume`, `expr_protocol_labelling` (`Unlabelled`, `Selenium`, `15N`, `13C`, `15N+13C`, `2H`), `induction_time`, `induction_temp`, `expr_protocol_system` (`erlen`/`fedbatch`/`batch`), `strain_id` | unités implicites (mL ? h ? °C) |
| `expr` | Expression réalisée | `clne_id`, `expr_protocol_id` (varchar), `strain_id`, `aliquot_vol`, `aliquot_num_prepared`, `aliquot_num_remain` | gestion d'aliquots = stock consommable |
| `purif_protocol` | Protocole de purification | `purif_protocol_desc` (longtext Textile) | |
| `product_prep` | Lot de protéine purifiée | `product_prep_conc`, `product_prep_volume`, `product_prep_yield`, `clne_id`, `expr_id`, `purif_protocol_id` | unités implicites ; rendement dérivable mais stocké |
| `lab_protocol` | Protocole libre | `lab_protocol_desc` (Textile) | rendu Textile → risque XSS si reconverti en HTML |

## 5. Caractérisation

| Table | Rôle | Remarques |
|---|---|---|
| `fragment` | Fragment protéolytique d'un produit : `fragment_start/end`, `fragment_mz_obz` (observé), `fragment_mr_calc_mono/avg`, `fragment_mr_error_ppm` (`int`), `fragment_mr_error_da`, `fragment_seq` | masses calculables par biophp → vérifier cohérence |
| `protease`, `protease_digest`, `protease_digest_timepoint` | Protéolyse ménagée | `protease_site` en texte libre |
| `ms_machine`, `ms_machine_model`, `ms_machine_type` | Spectromètres (MALDI-TOF, ESI…) | |
| `ms_sample`, `ms_spectrum`, `ms_peak` | Échantillons, spectres, pics | `ms_peak_mz float(8,4)` : précision limitée (max 9999.9999) — **troncature possible des m/z** |
| `crystal_screen` | Kit de criblage (ex. JCSG+) | documentation et feuille de score en blob |
| `crystal_screen_exp` | Criblage réalisé sur un `product_prep` | fichier de données en blob |

## 6. Authentification et sessions — ne pas migrer

`auth_user` (MD5 non salé), `user` (`password varchar(40)`, SHA-1 probable), `active_sessions` (session sérialisée contenant un mot de passe **en clair**). biolabs repart sur le composant Security de Symfony avec hachage moderne ; aucun hash historique n'est importé.

## Correspondance indicative vers biolabs

À valider par l'agent `lab-workflow-biologist` avant toute entité Doctrine :

| Historique | Cible biolabs proposée |
|---|---|
| `complex`, `subunit`, `species` | `Target\Complex`, `Target\Gene`, `Reference\Organism` |
| `product` | `Target\Construct target` (séquence cible + fenêtre) |
| `primer`, `pcr`, `insrt` | `Cloning\Oligo`, `Cloning\PcrReaction`, `Cloning\Insert` |
| `std_plasmid`, `constr_plasmid`, `feature` | `Cloning\Vector`, `Cloning\Construct`, `Cloning\Feature` (ou `PlasmidRecord` biophp) |
| `clne`, `glycerol_stock`, `dna_prep`, `sequencing` | `Sample\Clone`, `Sample\GlycerolStock`, `Sample\DnaPrep`, `Sample\SequencingResult` |
| `expr*`, `purif_protocol`, `product_prep` | `Production\ExpressionProtocol`, `Production\Expression`, `Production\PurificationProtocol`, `Production\ProteinBatch` |
| `ms_*`, `fragment`, `protease*`, `crystal_*` | `Characterisation\…` (lot séparé, priorité basse) |
| référentiels | `Reference\…` (Marker, Promoter, Origin, Tag, Strain, Supplier) |
