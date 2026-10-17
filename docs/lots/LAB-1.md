# LAB-1 — Référentiels

Statut : **implémenté (feu vert du 17 octobre 2026), revues `bioinfo-reviewer`, `code-reviewer` et `test-reviewer` à faire**. Écarts à la fiche : voir « Écarts constatés à l'implémentation ».
Sources : inventaire du legacy (`legacy-analyst` non chargé dans la session : inventaire fait à la main), avis de `molecular-biologist` (contenu scientifique) et de `lab-workflow-biologist` (modèle LIMS, migration). Les affirmations « vérifié » citent le fichier legacy ou une source ; le reste est marqué **non vérifié**.

## Objectif

Permettre au laboratoire de tenir à jour les listes de référence (organismes, souches, marqueurs de sélection, promoteurs, origines de réplication, tags, fournisseurs) sur lesquelles s'appuieront les vecteurs, primers, clones et protocoles des lots suivants.

## Ce que faisait Plasmi::db

| Table | Lignes | Écran legacy | Colonnes utiles |
|---|---|---|---|
| species | 9 | oui | nom (30 car.), description (fiche NCBI), taxid, table génétique NCBI |
| strain | 25 | oui | nom (50), description (texte libre). **Aucun lien vers une espèce** (vérifié : DDL) |
| marker | 4 | oui | nom, description (concentration de travail en texte : « Used at 50ug/ml ») |
| promoter | 6 | non (SQL seul) | nom, description |
| repliorig | 1 | non | une seule ligne placeholder « Unknown?! » |
| puri_fusion | 5 | non | nom (30), description |
| detect_fusion | 1 | non | « No detection tag » |
| seqorigin | 4 | non | 4 libellés de provenance de séquence |
| stocktype | 3 | non | 3 libellés + `strain_id` jamais renseigné |
| company | 14 | non | nom, URL |
| enzyme | 646 | non | nom + site de reconnaissance seulement |
| reporter | 0 | non | vide |

Seuls species, strain et marker pouvaient être édités par les utilisateurs. Les autres listes n'étaient modifiables que par SQL.

## Périmètre

### Inclus
Sept référentiels éditables, avec archivage : **Organisme, Souche, Marqueur de sélection, Promoteur, Origine de réplication, Tag, Fournisseur**. Un écran générique de liste/création/édition/archivage, configuré par référentiel.

### Exclu, avec la raison
| Élément | Décision | Raison |
|---|---|---|
| **Enzymes** | Aucune table biolabs | `RestrictionEnzymeCatalog` de biophp fournit nom, alias, famille, motif, positions de coupe (lu par `molecular-biologist`). Le legacy ne stockait que le site, sans coupure : inexploitable seul. Les futures entités référenceront l'enzyme par son nom canonique du catalogue. |
| Provenance de séquence (`seqorigin`), type de stock (`stocktype`) | **Énumérations figées**, créées par le lot qui les utilise (LAB-3 / LAB-4) | Pas d'écran dans le legacy (comportement = liste fixe) ; le sens exact de `stocktype` est inconnu. |
| Reporter, site de clivage | Non créés | Table vide ; aucune donnée récupérable (`cleavage_id` pointait vers une table inexistante). À créer en LAB-3 si le laboratoire le confirme. |
| Séquence du promoteur, position et site de clivage des tags | Non | Non nécessaires avant le design de primers (LAB-3). |
| Import des données Plasmi::db | Lot LAB-7 | Règles consignées ci-dessous. |
| Import CSV, fusion de doublons, ordre manuel | Non | Sur-conception pour quelques dizaines d'entrées. |
| Historique champ par champ | Reporté (voir décision à prendre) | |

## Modèle proposé

Tous les référentiels : `AuditableTrait` (LAB-0), `name`, `active` (archivé = faux), `notes` facultative.

**Unicité** : sur une clé normalisée (Unicode NFC, espaces coupés et réduits, casse ignorée), unique en base **y compris pour les entrées archivées**. Les accents, parenthèses et lettres grecques sont conservés : `DH5α` ≠ `DH5a`, `BL21(DE3)` ≠ `BL21 DE3`. Le nom saisi est affiché tel quel.

| Entité | Champs propres | Remarques |
|---|---|---|
| `Organism` | nom scientifique (≤ 100), taxid NCBI (entier > 0, facultatif), table génétique NCBI (obligatoire), table génétique mitochondriale (facultative) | Le legacy limite à 30 car. : risque de troncature. |
| `Strain` | usage (clonage / expression / les deux, facultatif), génotype (texte), résistances (liste de marqueurs), DE3 (oui/non) | **Pas de lien vers Organism** (le legacy n'en avait pas ; toutes les souches sont des *E. coli*). `notes` reçoit `strain_desc` intact. |
| `SelectionMarker` | concentration de travail (valeur + unité µg/mL, facultative), concentration de la solution mère (valeur + unité mg/mL, facultative) | Le texte d'origine du laboratoire est conservé en `notes`. |
| `Promoter` | — | |
| `ReplicationOrigin` | — | La ligne « Unknown?! » n'est pas reprise. |
| `Tag` | finalité (purification / détection), séquence ADN (facultative, ACGT) | Fusion de `puri_fusion` et `detect_fusion`. |
| `Supplier` | URL (http/https, facultative) | Pas de contact personnel ni de numéro de compte. |

**Valeurs « aucun »** (« No purification tag », « No detection tag », « No sequence avail.», « No stock available », « Unknown?! ») : jamais des lignes de référentiel. Une relation vide (`NULL`) signifie « aucun » ou « non renseigné ». Distinguer « aucun » et « inconnu » est une question ouverte.

## Droits, archivage, audit

- **Création, modification, archivage, restauration** : tout `ROLE_MEMBER` (et donc `ROLE_ADMIN`), comme le legacy où tout utilisateur connecté pouvait agir.
- **Pas de suppression via l'interface** en v1. Une erreur se corrige par renommage ou archivage. (Le legacy supprimait sans contrôle d'usage : c'est un écart volontaire, voir question 2.)
- Une entrée archivée n'est plus proposée à la saisie mais reste affichée sur les objets existants ; elle peut être restaurée. Créer un doublon d'une entrée archivée est refusé, avec proposition de la restaurer.
- Audit : `createdAt/By`, `updatedAt/By`.
- **Décision à prendre** : les deux agents recommandent un historique ancienne/nouvelle valeur pour ces données (un renommage de souche ou un changement de concentration modifie le sens de tout l'historique lié). LAB-0 l'a reporté à LAB-4. Option recommandée : livrer LAB-1 sans, avec une dette notée, et traiter l'historique en LAB-4.

## Règles de validation

**Bloquantes**
- nom vide après nettoyage ; clé normalisée déjà utilisée ;
- Organisme : table génétique hors de {1-6, 9-16, 21-33} (le NCBI n'a pas de table 0, 7, 8, 17-20) ; table mitochondriale hors de {2, 3, 4, 5, 9, 13, 14, 16, 21, 22, 23, 24, 33} ; taxid ≤ 0 ou non entier ; source : [NCBI Genetic Codes](https://www.ncbi.nlm.nih.gov/Taxonomy/Utils/wprintgc.cgi) ;
- Marqueur : concentration ≤ 0, ou valeur sans unité ;
- Tag : séquence ADN contenant autre chose que A, C, G, T ;
- Fournisseur : URL avec un schéma autre que http/https.

**Avertissements**
- nom proche d'une valeur « aucun » (commence par « No », ou vaut « Unknown », « N/A ») ;
- quasi-doublon (ponctuation et espaces ignorés) ;
- renommage ou changement de concentration d'une entrée déjà utilisée ;
- taxid absent ; concentration de marqueur hors de 5-500 µg/mL (plage **indicative non sourcée**) ;

## Écrans

Une entrée de menu « Référentiels » avec un onglet par type ; pour chacun : liste triée par nom, recherche, filtre actif/archivé/tous, création, édition, archivage/restauration, compteur d'usages (0 tant que les lots suivants n'existent pas). Textes FR/EN via les traductions. Pas de bouton supprimer.

## Plan de migration (exécuté en LAB-7, règles fixées ici)

- **Provenance** : table de correspondance `bl_legacy_map` (source, table legacy, identifiant legacy en texte, entité cible, statut, empreinte de la ligne) plutôt qu'une colonne dans chaque entité ; import idempotent, qui ne réécrase jamais une modification faite dans biolabs.
- **Encodage** : décodage **champ par champ**, jamais sur le fichier. Le dump est du latin1 déclaré mais contient de l'UTF-8 **doublement encodé** (`-20°C` stocké `C3 82 C2 B0`) et n'est pas UTF-8 valide globalement (blobs). Essayer UTF-8 strict ; si le résultat contient `Ã`/`Â`, reconvertir (jusqu'à deux passes) ; sinon lire en cp1252. Les cas à caractère de remplacement perdu (`Tuner™` → `Ã¯Â¿Â½`) vont dans un fichier de corrections validé par un humain.
- **Dates** `0000-00-00` : jamais converties en une date inventée ; date d'import avec mention « date d'origine inconnue » (question 12).
- **`created_by` / `updated_by`** : jamais copiés (emails réels). Remplacés par le compte système « import Plasmi::db » (question 6).
- **Valeurs « aucun » et FK à 0** → `NULL`, comptées dans le rapport.
- **Doublons** : détectés par la clé normalisée ; fusion automatique uniquement si tous les autres attributs sont identiques, sinon quarantaine listée.
- **Marqueurs** : la concentration n'est interprétée que si le texte est sans ambiguïté (`50ug/ml` → 50 µg/mL, µ rétabli) ; sinon vide, texte conservé.
- **Rapport de qualité** sans donnée personnelle : lignes lues/importées/fusionnées/en quarantaine, champs réparés ou irrécupérables, orphelins, noms d'enzymes absents du catalogue, usages aval. Mode `--dry-run`.
- **Exclus, sans jamais recopier la valeur** : comptes, hash, sessions (mot de passe en clair dans le dump), DSN de configuration, tout champ libre contenant un e-mail ou un nom (détecté par motif, signalé par identifiant).

### Corrections de données à appliquer (sans rien inventer)
| Donnée | Constat | Traitement |
|---|---|---|
| *Methanococcus jannaschii* (taxid 2190) : table génétique 0 | invalide ; les archées utilisent la table 11, comme les 5 autres archées/bactéries du dump | corriger en **11** (à confirmer sur NCBI Taxonomy, taxid 2190) |
| *Homo sapiens* : taxid 0 | inconnu ; l'identifiant NCBI de l'espèce est **9606** | corriger en 9606 (à confirmer sur NCBI Taxonomy) ; table 1 et mitochondriale 2 sont cohérentes |
| `Clostridium thermocellum ATCC ` et `Thermoplasma acidophilum DSM 1` | noms **tronqués à 30 caractères** (preuve : ils s'arrêtent net, et le dump n'en contient pas la fin) | retrouver le nom complet par le taxid (203119, 273075) sur NCBI Taxonomy ; ne rien deviner (je crois qu'il s'agit de ATCC 27405 et DSM 1728 : **non vérifié**) |
| *Pyrococcus horikoshii* OT3 | taxid 70601 = niveau **souche** (53953 pour l'espèce, d'après PDBj/BacDive). Les autres lignes (Pyrococcus abyssi, *P. furiosus*, *C. thermocellum*, *T. acidophilum*) sont aussi au niveau souche (« Rank: no rank ») | **conserver le niveau souche** : c'est ce que le laboratoire utilise (génomes séquencés) ; le champ est l'identifiant de l'organisme tel que travaillé |
| *S. cerevisiae* | la description cite la table mitochondriale 3 mais aucune colonne ne la porte | renseigner la table mitochondriale 3 |
| XL1-Blue | résistance Tn10 (Tet^r) citée dans la note du marqueur tétracycline ; confirmée par la fiche [Stratagene/Agilent](https://www.chem-agilent.com/pdf/strata/200158.pdf) | porter la résistance dans la souche ; avertir si un plasmide utilise le même marqueur |
| `Jm109` | graphie usuelle `JM109` (**non vérifié auprès du fournisseur**) | écrire `JM109`, ancienne graphie conservée dans `notes` |
| Noms d'espèce de 30 caractères | probable troncature | signalés |

### Enzymes (information pour LAB-7 et LAB-3)
- Biolabs ne reprend pas la table `enzyme`. Comparer les 646 noms au catalogue biophp et lister les absents.
- 78 sites en minuscules (ex. AceIII `cagctc`) : le code legacy les met toujours en majuscules (`Bio/Bio/Restriction/RestrictionEnzyme.php:370-375`) et AhaIII est en majuscules dans `Bio/Bio/Data/RestrictionEnzymeData.php`. La minuscule semble donc un **artefact d'import**, sans signification biologique (origine exacte inconnue).
- `TaqII` figure deux fois (GACCGA, CACCCA). La littérature attribue CACCCA à TaqIII ([PMC3280180](https://www.ncbi.nlm.nih.gov/pmc/articles/PMC3280180/), [PMC5587805](https://www.ncbi.nlm.nih.gov/pmc/articles/PMC5587805/)) ; REBASE **non consulté directement**.
- Le legacy porte la position de coupe dans le PHP (`RestrictionEnzymeData.php`), pas dans le SQL.

## Tests prévus

- règles bloquantes et avertissements ci-dessus (tables génétiques 0, 7, 32 valide, mito 11 refusé, taxid 0, nom de 40 caractères, concentration sans unité, URL `javascript:`, tag ADN invalide) ;
- unicité par clé normalisée, y compris archivé, accents et parenthèses conservés ;
- archivage/restauration ; une entrée archivée absente des listes de saisie mais visible sur un objet existant ;
- accès : anonyme redirigé, membre autorisé ;
- échappement des noms et notes ; liens externes avec `rel="noopener"` ;
- fixtures strictement synthétiques, y compris pour l'encodage.

## Décisions (jugement de biologiste)

Principe : Plasmi::db est un programme inachevé et bogué (colonnes de mauvais type, tables manquantes, valeurs impossibles, aucune validation scientifique). Pour les **données et règles scientifiques**, on ne reproduit pas ses défauts ; « comme le legacy » ne vaut que pour des choix de fonctionnement (rôles, comptes locaux, audit non purgé).

| # | Sujet | Décision | Raison |
|---|---|---|---|
| 1 | Qui modifie les référentiels | tous les membres | ressources partagées du laboratoire ; chaque modification est tracée (audit) |
| 2 | Suppression | **aucune** en v1, archivage uniquement | on ne perd pas la souche utilisée dans une expérience ; une erreur se corrige par renommage |
| 3 | Taxid 0 / table 0 | corrigés à l'import : *H. sapiens* 9606, *M. jannaschii* table 11 | valeurs identifiables sans ambiguïté ; **à confirmer sur NCBI Taxonomy** avant import |
| 4 | Niveau du taxid | niveau souche conservé | cohérent avec les 5 autres lignes |
| 5 | Concentrations des antibiotiques | valeurs du laboratoire conservées (Amp 50, Kan 30, Cam 34 µg/mL) ; **pas d'avertissement** sur l'écart avec d'autres références | ce sont des valeurs de travail courantes, sans erreur ; seul le laboratoire décide. On ajoute la concentration de la solution mère (le legacy cite « stock 10 mg/mL » pour la kanamycine), le solvant va en `notes` |
| 6 | Auteur historique | non conservé | e-mails réels dans le dump ; compte système « import Plasmi::db » |
| 7 | Souches | usage, génotype et résistances structurés (XL1-Blue → Tet^r) ; **pas de niveau de confinement** | l'information utile au choix du marqueur doit être filtrable ; le confinement est une décision réglementaire du laboratoire, pas à inventer |
| 8 | Tags | nom, finalité, description, séquence ADN facultative ; **aucune séquence fournie en seed** | séquences non vérifiées ; elles se saisissent depuis la carte du vecteur. Position N/C et site de clivage : LAB-3, où ils servent au design des primers |
| 9 | `Jm109` | `JM109` | graphie usuelle |
| 10 | Enzymes | rien en base, pas de suivi de stock | catalogue biophp ; un stock d'enzymes est un autre sujet (consommables) |
| 11 | « Pas de tag » ≠ « tag inconnu » | `NULL` en LAB-1 ; l'état explicite se décide en LAB-3, côté vecteur | un vecteur sans tag est un fait, un tag non renseigné est une lacune |
| 12 | Date d'origine `0000-00-00` | date d'import, marquée « origine inconnue » | on n'invente pas de date |
| 13 | `stocktype` | reporté à LAB-4 | sens inconnu ; un biologiste le redéfinit avec le stockage réel (ADN -20 °C, glycérol -80 °C) |
| 14 | Historique champ par champ | **reporté à LAB-4**, avec avertissement sur tout renommage d'une entrée utilisée (nombre d'usages affiché) | dette notée ; à reprendre avant que des échantillons réels dépendent des référentiels |

### Reste à confirmer par le laboratoire (après coup, sans bloquer le code)
- noms complets de *C. thermocellum* et *T. acidophilum* (par taxid) ;
- quelles souches servent au clonage, à l'expression, aux deux ;
- séquences et noms des tags réellement utilisés ;
- confirmation sur NCBI Taxonomy de 9606 et de la table 11 pour *M. jannaschii*.

## Écarts constatés à l'implémentation

- **Tables génétiques** : la liste n'est plus écrite en dur, c'est `GeneticCodeTable::SUPPORTED_TABLES` de biophp (24 tables). Les tables NCBI **27, 28 et 31** (arrêts dépendant du contexte) sont donc refusées : biophp ne sait pas les traduire. Les noms affichés viennent de `GeneticCodeTable::NAMES`. La liste des tables mitochondriales reste locale (biophp ne distingue pas nucléaire/mitochondrial).
- **Tag** : normalisation de la séquence par `DnaSequence` de biophp ; la règle « A, C, G, T seulement » (pas de code dégénéré) reste locale.
- **Marqueur** : les unités sont fixes (µg/mL pour le travail, mg/mL pour la solution mère), portées par le nom des champs ; le cas « valeur sans unité » ne peut donc pas se produire.
- **Colonne `strain_usage`** (et non `usage`) pour éviter un mot réservé.

## Définition de « terminé » (à cocher à la livraison)

- [x] entités, migration (API Schema), `doctrine:migrations:diff` sans changement
- [x] écrans FR/EN, accessibles au clavier, libellés associés aux champs (à confirmer par revue)
- [x] tests PHPUnit verts (87 tests), fixtures synthétiques
- [ ] revue `bioinfo-reviewer` (règles génétiques), `code-reviewer`, `test-reviewer`
- [ ] aucun secret ni donnée personnelle de Plasmi::db
