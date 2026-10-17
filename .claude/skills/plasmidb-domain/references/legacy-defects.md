# Défauts et risques connus de Plasmi::db 0.9.1

Liste de départ, à compléter au fil des analyses. Chaque entrée : emplacement, constat, conséquence, traitement dans biolabs. Classer en **défaut logiciel**, **défaut scientifique**, **défaut de données** ou **sécurité**.

## Sécurité — ne jamais propager

| Emplacement | Constat | Traitement |
|---|---|---|
| `plasmidb/config/dataobject.conf` | DSN MySQL avec mot de passe root en clair | Ne pas citer la valeur ; config biolabs via `.env.local` non versionné |
| `plasmidb-0.9.1.sql` → `active_sessions` | Session sérialisée contenant un mot de passe en clair | Ne pas importer |
| `plasmidb-0.9.1.sql` → `auth_user`, `user` | Hash MD5 / SHA-1 non salés | Ne pas importer ; réinitialisation des comptes |
| dump, colonnes `created_by` | Emails réels de personnes | Pseudonymiser dans toute fixture ou exemple |
| contrôleurs `app/controllers/*.php` | Requêtes et rendu HTML sans échappement systématique | Twig autoescape, requêtes Doctrine paramétrées |
| `lab_protocol`, `purif_protocol` | Texte Textile converti en HTML | Rendu Markdown/Textile avec sanitation stricte |

## Défauts scientifiques ou de logique biologique

| Emplacement | Constat | Conséquence |
|---|---|---|
| `lib/generators/PrimerGenerator.php`, contrôle des sites | La vérification de l'enzyme 3′ est conditionnée par `if ($sRestEnzNameFivePrime)` (copier-coller) | Site 3′ interne non détecté si aucune enzyme 5′ n'est choisie |
| `PrimerGenerator.php`, tag N-terminal | `$fPrimerTagSeq = $sTagSeq` sans ATG explicite quand le tag est N-terminal | Codon d'initiation possiblement absent ; à confirmer avec la suite du code et le vecteur |
| `PrimerGenerator.php`, tags | Séquences de tags codées en dur, sélection par entier magique | Doit venir d'un référentiel (`puri_fusion`/catalogue) |
| `PCRrecipe.php` | Pas d'excès de pipetage, pas de contrôle eau < 0, unités en texte `ul` | Recette impossible affichée sans avertissement |
| `primer.primer_tm`, `primer_codingtm` | Tm stockée en `int` | Perte de précision ; modèle de Tm non enregistré |
| `ms_peak.ms_peak_mz float(8,4)` | Valeur max 9999.9999 | m/z de grosses protéines tronqués |
| `fragment.fragment_mr_error_ppm int` | Erreur ppm entière | Perte de précision |
| `feature` (`start_pos` + `length`) | Pas de convention documentée (0/1-based), pas de feature traversant l'origine | Coordonnées ambiguës à la migration |
| `enzyme.enzyme_sequence` | Site de reconnaissance sans position de coupure | Impossible de calculer les extrémités cohésives depuis cette table |
| `species.species_ncbi_trans_tab` | Table génétique disponible mais non utilisée par `Translator` historique (à vérifier) | Traduction potentiellement fausse pour mitochondries / ciliés / mycoplasmes |

## Défauts de données / schéma

| Emplacement | Constat |
|---|---|
| tout le schéma | MyISAM : aucune FK, orphelins probables |
| `clne.clne_comments` | Typé `int` : commentaires perdus ou tronqués |
| `dna_prep.clone_id` vs `clne_id` ailleurs | Nommage incohérent de la même FK |
| `std_plasmid.cleavage_id` | Pointe vers une table inexistante |
| FK en `varchar(11)` | Voir `data-model.md` |
| `feature.id`, `pcr_primer_design_strategy.id`, `reporter.id` | Non auto-incrémentés |
| `complex_subunit` | Pas de clé primaire, doublons possibles |
| `repliorig.repliorg_name` | Faute de frappe dans le nom de colonne |
| dates `0000-00-00` | Valeurs invalides en MySQL strict / Doctrine |
| séquences (`product_dna_seq`, `subunit_seq`…) | Espaces, numéros, `\r\n`, casse mixte |
| blobs PDF dans `std_plasmid`, `crystal_screen*` | Fichiers à extraire vers un stockage de pièces jointes |
| unités | Aucune unité stockée pour concentrations, volumes, températures, durées |

## Dette technique (ne pas reproduire)

PEAR DB / MDB2 / DB_DataObject / HTML_QuickForm / HTML_Table, PHP 5, singleton `Configurator`, framework maison `Phli`, `mod_rewrite` obligatoire, ExtJS 2, CVS, impression HTML directe par `print`.
