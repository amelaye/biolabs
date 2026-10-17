# LAB-3 — Clonage (brouillon)

Statut : **brouillon relu par `molecular-biologist`, corrections intégrées**. Aucun code n'est écrit. Rédigé à partir du rapport d'inventaire du `legacy-analyst`. Reste : validation de `lab-workflow-biologist` (pièces jointes, quarantaine, statuts), réponses aux questions ouvertes, puis feu vert explicite de la personne qui pilote le projet.

## Objectif métier

Permettre au biologiste d'enregistrer et de tracer, pour chaque produit de LAB-2, les oligos, la PCR, l'insert, le vecteur et la construction obtenue, avec des contrôles bloquants qui empêchent de déclarer valide une construction incohérente.

## Acteurs et rôles

Mêmes rôles que LAB-1/LAB-2 : lecture pour tous les comptes, création et modification pour les rôles de paillasse, suppression refusée si l'élément est référencé.

## Entités proposées

| Entité | Contenu | Contraintes |
|---|---|---|
| Oligo | nom, séquence brute et normalisée (5′→3′, ACGT + IUPAC), segments annotés (clamp, site, cadre, tag, partie hybridante), fournisseur (LAB-1), commentaire | alphabet vérifié ; longueur ≤ 255 ; nom unique |
| Tm d'oligo | valeur décimale en °C, modèle, concentrations d'oligo, Na⁺ et Mg²⁺ avec unités | jamais entière ; calculée par `MeltingTemperatureCalculator` |
| Vecteur standard | nom, fournisseur, séquence circulaire (peut être absente : « non disponible »), promoteur, marqueur, origine (LAB-1), état du tag, carte en pièce jointe | nom unique |
| Feature de vecteur | type, début, fin (1-based inclusif), brin explicite, via `PlasmidFeature` | franchissement d'origine permis |
| PCR | oligo F, oligo R, matrice (cible LAB-2 **ou** vecteur/construction, exclusif), produit attendu calculé, programme avec unités | F et R obligatoires |
| Insert | type (fragment PCR / fragment de restriction), sites 5′ et 3′ (catalogue biophp), lien vers sa PCR ou son donneur, produit LAB-2 | sites issus du catalogue uniquement |
| Construction | vecteur, insert(s), produit(s) (n-n), séquence **calculée**, statut | jamais « valide » si une règle bloquante échoue |

## Règles métier

Bloquantes :

- alphabet de l'oligo (IUPAC admis) ; segments annotés contigus et reconstituant la séquence ;
- chaque enzyme évaluée **indépendamment** : exactement une coupe dans le vecteur, au bon endroit (MCS, fragment retiré attendu), et deux enzymes distinctes pour une double digestion dirigée (défaut legacy S1 : vérification conditionnée par l'autre enzyme) ;
- sites internes absents de l'amplicon final (primers inclus) et de l'insert, sur les deux brins ; digest linéaire pour l'insert, circulaire pour le vecteur ; sites créés ou détruits à la jonction signalés ;
- extrémités compatibles (`RestrictionEndCompatibilityManager`), auto-religation du vecteur et orientation de l'insert (dirigée ou non) ;
- brin : le primer R est le reverse-complément du brin codant ; chaque primer s'hybride par son 3′ sur le bon brin de la matrice (mésappariement en 3′ bloquant) ; sites d'hybridation multiples signalés ;
- cadre de lecture calculé sur l'ORF réellement traduite (ATG ou tag N → stop) : stop interne en cadre et décalage à la jonction vecteur/insert bloquants ; aucun cadre imposé si aucun tag n'est fusionné ; stop final absent alors que le vecteur n'en fournit pas = bloquant ;
- séquence de la construction jamais saisie à la main.

États d'une construction : conçue → calculée → vérifiée. « Vérifiée » exige une preuve modélisée (PCR sur colonie, digestion, séquençage) reliée à la séquence attendue, mutations signalées sans correction silencieuse. Sans séquence de vecteur, ou sans service d'assemblage, la construction reste **non évaluable**, jamais « valide ».

Avertissements : Tm hors fenêtre déclarée ; écart de Tm F/R (parties hybridantes) trop grand ; clamp court pour l'enzyme (seuil à confirmer, p. ex. NEB) ; méthylation Dam/Dcm sur les sites ; deuxième codon imposé par NcoI ; compatibilité de tampon de la double digestion et petit fragment retiré ; stop présent alors qu'un tag C est attendu ; produit LAB-2 non retrouvé dans la construction.

## Tags

Le tag du vecteur est une **liste de capacités** (un pET-28a(+) a un tag N et un tag C), chacune avec position (N/C), séquence ou feature, et sites qui la conservent ou la retirent. États explicites : « aucune capacité » (fait) ≠ « inconnu » (lacune, décision 11 de LAB-1). Le tag de la construction est un **résultat calculé** sur la séquence assemblée, avec sa source (vecteur ou primer) ; il peut y en avoir plusieurs. Exemple : NcoI/NotI sur pET-28a(+) retire le tag N ; le tag C (après XhoI) ne subsiste que si le cadre est maintenu et si l'insert n'a pas de stop ; sur VMA4:6H le 6His vient du primer. Les tags/positions du dump ne servent pas de capacité tant que le laboratoire ne les a pas confirmés ; dériver de la séquence quand elle existe. Site de clivage : à créer seulement si le laboratoire le confirme.

## Modèle : compléments

- Oligo : modifications 5′/3′ (phosphorylation…) si commandées ; segments typés, la partie hybridante étant déterminée par alignement sur la cible, jamais déduite de la casse (le `GC` de maintien du cadre est en majuscules) ; sans alignement, pas de segments.
- Tm : calculée sur la seule partie hybridante, stockée comme résultat avec ses paramètres (modèle, oligo, Na⁺, Mg²⁺), pas comme attribut saisi.
- Vecteur : topologie circulaire explicite, MCS (positions, enzymes, cadre de référence), fourniture d'un ATG, d'un stop, d'un RBS ; coordonnées 1-based inclusives, origine = début de séquence, features à cheval sur l'origine permises.
- PCR : position et brin de liaison de chaque primer ; produit calculé = séquence, brin, coordonnées ; matrice circulaire gérée.
- Insert : orientation, ordre dans un assemblage multiple, méthode d'assemblage (question laboratoire).
- Construction : les features du vecteur retirées ou tronquées par l'assemblage disparaissent ou sont tronquées ; polycistronique = cadre vérifié par produit.

## Calculs et services

| Besoin | Service | Statut |
|---|---|---|
| Tm | `MeltingTemperatureCalculator::calculateNearestNeighborTm` | disponible |
| Plasmide, features | `Plasmid`, `PlasmidFeature` | disponible |
| Digestion circulaire / linéaire | `CircularRestrictionDigestManager`, `LinearRestrictionDigestManager` | disponible |
| Compatibilité des extrémités | `RestrictionEndCompatibilityManager` | disponible |
| Simulation de PCR | `PcrAmplificationManager` (biotools) | disponible |
| Catalogue d'enzymes | `RestrictionEnzymeCatalog` | disponible |
| Design de primers de clonage | — | **manquant → demande biotools** |
| Recette PCR | — | **manquant → demande biotools** |
| Vérification de cadre, assemblage vecteur + insert par restriction/ligation | — | **manquant → demande biotools** |
| Simulation de PCR en séquence complète (amplicon, brin, matrice circulaire) | `PcrAmplificationManager` est linéaire, par motifs, sans séquence d'amplicon | **insuffisant → ajouter à la demande biotools** |

Tant que ces services manquent, LAB-3 enregistre les oligos saisis à la main et n'implémente aucun design local.

## Correspondance historique → biolabs

| Legacy | biolabs | Transformation |
|---|---|---|
| `primer` (2 lignes) | Oligo | séquence en majuscules normalisée, casse d'origine conservée dans la brute ; `primer_tm` absent → pas de Tm ; `company_id` vide → NULL |
| `pcr` (1 ligne) | PCR | `pcr_date` `0000-00-00` → NULL ; `constr_plasmid_id` `'0'` → NULL ; séquence désirée **conservée en brut dans la quarantaine**, jamais utilisée comme référence (le produit calculé la remplace) |
| `insrt` (0 ligne) | Insert | rien à importer |
| `std_plasmid` (~47) | Vecteur | séquences vides → « non disponible » ; minuscules normalisées ; `/` de pET-15b mis en quarantaine ; doublon pET-11a signalé ; tags/positions incohérents signalés, non corrigés ; PDF → pièces jointes |
| `constr_plasmid` (1) + `constr_plasmid_product` (1) | Construction + lien produit | séquence vide → à recalculer ; VMA4:6H en quarantaine |
| `enzyme` | catalogue biophp | non importé |
| `seqorigin` | énumération figée (4 valeurs) | porteur à décider |

## Non repris

Designers et vecteurs/tags codés en dur, code mort de `PrimerGenerator`, formulaire feature (copie de primer), export Excel avec coût en dur, tables vides (`feature`, `reporter`, `pcr_primer_design_strategy`), table `enzyme`.

## Défauts legacy à ne pas reproduire

Voir le rapport d'inventaire : S1–S16, D1–D9, L1–L6, X1. Retenir en particulier : vérification de site conditionnée par l'autre enzyme, précédence `!$n == 1`, recherche de site sur un seul brin, absence de circularité, Tm entière, injection SQL dans les exports, formulaires qui écrivent des colonnes inexistantes.

## Cas de test imposé

VMA4:6H. Les **primers sont cohérents entre eux** : F = clamp GTC + NcoI (ccatgg) + GC de maintien du cadre + CDS, qui se lit Met-Gly-Met-Ser (le Met-Gly vient du site NcoI, l'ATG natif est conservé) ; R = clamp + NotI + stop + 6His (son reverse-complément se termine par CAC CAT CAC CAT CAC CAT TAG). Ce sont l'**ancien produit legacy** (717 nt = CDS 699 + 18 nt de 6His, sans stop ni Met-Gly) et la **séquence désirée de la PCR** (747 nt, ATG en plus, 12 His) qui sont incohérents avec les primers ; ils sont distincts du produit LAB-2 (région 1–699 sans étiquette). Attendu : construction et lien produit mis en quarantaine, différences détaillées, aucune correction automatique ; le séquençage tranche. Lectures faites à la main : à recontrôler avec biophp (traduction, reverse-complément) avant de les citer comme résultat.

Autres cas de test : plasmide synthétique avec NcoI à cheval sur l'origine (digest circulaire et coordonnées de l'assemblage) ; insert sans stop ni tag C avec un vecteur fournissant un tag C après XhoI (cadre maintenu = valide, décalé = bloquant).

## Questions ouvertes

Pour le laboratoire :

1. Tag réel de pGEX4T1, pET-21d(+), pET-9b et des pET-9x ; les positions N/C du dump sont-elles fiables ?
2. Marqueur de pCR2.1 TOPO et pET-30 Xa/LIC.
3. VMA4:6H : qu'a-t-on réellement construit (6 ou 12 His, ATG supplémentaire) ? Le séquençage tranche.
4. Reporters et sites de clivage utilisés ? Quelles protéases ?
5. Sens de `insrt_fragtype` « restriction ».
6. Stratégies à couvrir en v1 : restriction/ligation, SLIC, Gibson, TOPO, Gateway.
7. Clamp `GTC` et bornes de la partie hybridante : valeurs par défaut du labo ?
8. Export de commande de primers : conserver ? colonnes et devise.
9. Un stop interne est-il un simple avertissement (comme en LAB-2) ou bloquant pour une construction ?
10. Seuil minimal de clamp par enzyme (à confirmer par le laboratoire ou sur NEB).

Décisions de la personne qui pilote le projet (17 octobre 2026) :

1. Entités **biolabs** propres, converties vers l'agrégat `Plasmid` de biophp au calcul (pas de `PlasmidRecord`/`PlasmidFeatureRecord`).
2. LAB-3 **sans attendre biotools** : enregistrement manuel des oligos, constructions « non évaluables » tant que les services manquent ; la demande biotools s'ouvre en parallèle.
3. Cartes PDF **hors base** : stockage sur disque hors `public/`, PDF vérifié par contenu, taille maximale à fixer (5 Mo proposés), jamais servi directement.
4. **Cas simple seulement** (un vecteur, un insert, restriction/ligation) ; polycistronique, pST39 et TAP reportés ; le modèle n-n produit↔construction reste prévu.

## Périmètre de la première livraison (v1)

Arrêté après relecture de `lab-workflow-biologist`. Feu vert donné par la personne qui pilote le projet le 17 octobre 2026, qui délègue les choix de périmètre.

**Inclus**

- **Oligo** : nom unique (clé normalisée comme LAB-1), séquence brute + normalisée 5′→3′ (IUPAC), fournisseur, commentaire, archivage (pas de suppression). Pas de segments annotés ni de modifications 5′/3′. Tm calculée à l'affichage par `MeltingTemperatureCalculator`, avec ses paramètres affichés (oligo en µM, sels en mM), non saisie. Séquence **verrouillée** dès qu'une PCR l'utilise.
- **Vecteur** : nom unique, fournisseur, marqueur, origine, promoteur, séquence circulaire facultative (« non disponible » explicite), capacités de tag en trois états (aucune / inconnue / liste), pièce jointe PDF.
- **Pièce jointe** (`Attachment`) : PDF uniquement, type détecté par contenu, UUID de stockage (jamais le nom d'origine), nom d'origine nettoyé, taille, SHA-256, auteur, date, vecteur rattaché ; refus des PDF chiffrés ou contenant du JavaScript ; stockage hors `public/` ; lecture par contrôleur authentifié (`Content-Disposition`, `nosniff`) ; fichier manquant signalé ; taille maximale 5 Mo (à revoir après mesure des blobs du dump).
- **PCR** : oligo F et R obligatoires, matrice exclusive (cible LAB-2 ou vecteur), programme avec unités (°C, s, nombre de cycles, µL), **aucun produit calculé**.
- **Insert** : type, sites 5′/3′ issus du catalogue biophp, lien PCR, produit LAB-2 référencé par sa fenêtre (pas de copie de séquence).
- **Construction** : un vecteur + un insert + produit(s) (n-n prévu), **aucune séquence calculée ni saisie**. Statut déduit, jamais saisi : `designed` ou `not_evaluable`. En v1 toute construction est non évaluable pour la validité biologique (assemblage et cadre absents) ; « calculée » et « vérifiée » ne sont pas offerts.
- **Règles bloquantes v1** : alphabet de l'oligo, unicité, enzyme présente dans le catalogue, une seule coupe de chaque enzyme dans le vecteur (si séquence), deux enzymes distinctes pour une double digestion dirigée, F et R obligatoires, matrice exclusive, anti-cycle des dépendances.
- **Avertissements v1** : Tm hors fenêtre ou écart F/R, longueur d'oligo hors 15–100 nt, vecteur sans séquence, produit LAB-2 non lié à la construction.
- **Quarantaine** : statut sur l'entité (pas de table à part) avec motif et valeur brute ; un objet en quarantaine ne peut devenir parent d'aucun autre.
- **Traçabilité inverse** : depuis un oligo, lister PCR, inserts et constructions qui l'utilisent.
- **Audit** : `createdAt/By`, `updatedAt/By` plus historique ancienne/nouvelle valeur sur la séquence d'oligo, la séquence et le marqueur du vecteur, le statut de construction.
- **Confidentialité** : aucune séquence dans les URL, logs, messages d'erreur ni noms de fichier.

**Reporté** : calcul de séquence de construction, vérification de cadre, design de primers, recette PCR, assemblage, statut « vérifiée » (LAB-4), identifiants d'étiquette (LAB-4), segments annotés, Gibson/SLIC/TOPO/Gateway, import (LAB-7), export de commande, propagation d'obsolescence en cas de modification du CDS LAB-2 (alerte à prévoir).

Pas de suppression : archivage uniquement, comme LAB-1 et LAB-2. Rôles : alignés sur LAB-1/LAB-2 (`ROLE_MEMBER` écrit, lecture pour tous).

## Implémentation v1 (17 octobre 2026)

Implémentée sur feu vert du 17 octobre 2026, périmètre « v1 » ci-dessus uniquement. Revues `bioinfo-reviewer`, `code-reviewer`, `test-reviewer` à faire.

Écarts et choix par rapport à la fiche :

- **Modules** : `App\Cloning` (Oligo, Vector, VectorTagCapability, Attachment, Pcr, Insert, Construct) ; `App\Shared\Audit\FieldHistory` pour l'historique ancienne/nouvelle valeur (oligo : séquence ; vecteur : séquence et marqueur ; construction : statut). Les créations ne sont pas historisées (les colonnes d'audit disent qui et quand).
- **Écrans** : mêmes écrans génériques que LAB-1/LAB-2 (menu « Clonage », `/cloning/{oligos|vectors|pcrs|inserts|constructs}`), plus les pages de détail oligo (Tm, traçabilité inverse), vecteur (pièces jointes, capacités de tag) et construction (statut dérivé, coupures).
- **Tm** : calculée par `MeltingTemperatureCalculator::calculateNearestNeighborTm` sur l'oligo **entier** (partie hybridante non annotée en v1), conditions par défaut oligo 0,5 µM, cations monovalents 50 mM, Mg²⁺ 1,5 mM (**à confirmer par le laboratoire**) ; non calculable pour un oligo dégénéré (refus de biophp). Fenêtre d'avertissement 50–72 °C et écart F/R 5 °C : seuils indicatifs non sourcés.
- **Enzymes** : le nom saisi est validé par le catalogue biophp et conservé tel que saisi (casse du catalogue). Le catalogue traite `NcoI` comme alias de `Bsp19I` : remplacer par le nom canonique aurait surpris le biologiste. Les enzymes sont **comparées** par nom canonique du catalogue (`VectorSiteCounter::canonicalName()`), jamais réécrites : NcoI et Bsp19I (ou NcoI et ncoi) sont la même enzyme (voir correctifs).
- **Clonage dirigé** : case `directional` (par défaut cochée) ; la règle « deux enzymes distinctes » ne s'applique que si elle est cochée.
- **Statut de construction** : `designed` si la séquence du vecteur est connue, que l'insert a au moins un site et que chaque enzyme coupe le vecteur exactement une fois ; sinon `not_evaluable`. **Il n'est plus stocké** : il est dérivé à l'affichage (`CloningChecks::status()`) du vecteur et de l'insert actuels, donc jamais périmé. Il n'affirme jamais la validité biologique, et le libellé le dit (« sites uniques vérifiés ; ordre, orientation, extrémités et méthylation non vérifiés »). La liste n'affiche plus le statut (calcul de digestion par ligne trop coûteux) ; la construction n'historise plus `status` mais ses changements de `vector` et `insert`.
- **Matrice de PCR** : gène (cible LAB-2) ou vecteur, exclusivement.
- **Anti-cycle des dépendances** : non implémenté, car aucun cycle n'est possible en v1 (Construction → Insert → PCR → Oligo / Gène / Vecteur ; la matrice « construction » est hors v1).
- **Quarantaine** : colonnes sur chaque entité (statut, motif ≤ 255, valeur brute) ; un objet en quarantaine est refusé comme parent d'une PCR, d'un insert ou d'une construction et n'est plus proposé dans les formulaires. **Aucun écran ne met en quarantaine ni n'en sort** : c'est le rôle de l'import (LAB-7).
- **Pièces jointes** : `var/attachments/` (paramètre `app.attachments_dir`), nom `<uuid>.pdf`, type détecté par `finfo` sur le contenu, 5 Mo, refus des PDF contenant `/Encrypt`, `/JS`, `/JavaScript` ou `/Launch` dans les octets bruts (un PDF qui cache ces mots-clés dans un flux compressé n'est pas détecté ; le fichier n'est jamais servi inline, toujours en `attachment` avec `nosniff` et `Content-Security-Policy: default-src 'none'; sandbox`). Pas de suppression de pièce jointe (archivage seulement, comme le reste ; à décider).
- **Alphabet des oligos** : celui de `DnaSequence` de biophp (ACGT, codes IUPAC et `X`). Le `U` est refusé.
- **Tags du vecteur** : une capacité par extrémité (N et C) dans le formulaire ; le modèle accepte une liste. Pas de site de clivage, pas de sites qui conservent/retirent le tag.

### Correctifs après les trois revues (18 octobre 2026)

Faits :

1. **MySQL** : colonnes de séquence (`sequence_raw`, `sequence` des oligos et vecteurs, `quarantine_raw_value`, et aussi `old_value`/`new_value` de l'historique, qui recopient des séquences) en `MEDIUMTEXT` (option `length` 16777215) dans les attributs ORM et la migration. `Version20261017230000` n'avait été appliquée que dans des bases de test (la base de dev `var/data.db` s'arrête à `Version20261017220000`) : **modifiée en place**. Test unitaire `SequenceColumnsTest` (longueur déclarée >= maximum validé de 300 000 ; le DDL MySQL donne MEDIUMTEXT, vérifié sans serveur MySQL).
2. **Statut périmé** : colonne `status` supprimée, statut dérivé à l'affichage ; tests (vecteur modifié ensuite pour couper 2 fois : la construction n'est plus `designed`).
3. **Libellé `designed`** qualifié FR/EN, sémantique inchangée.
4. **Isoschizomères** : règle « deux enzymes distinctes » déplacée dans une contrainte de classe `InsertDistinctSites` (nom canonique du catalogue) ; `CloningChecks::distinctSites()` sert aux coupures et au statut. `Insert::sites()` (entité, sans catalogue) ne fusionne que la casse ; la comparaison par nom canonique est dans le service. Tests NcoI/Bsp19I, NcoI/ncoi, non dirigé.
5. **Bases ambiguës** dans un vecteur : avertissement `vector_ambiguous` (vecteur et construction), non bloquant ; test.
6. **Casse brute** : `sequenceRaw` suivi dans l'historique (oligo et vecteur) ; test. Le verrou d'oligo ne porte toujours que sur la séquence normalisée (un changement de casse seul est autorisé mais tracé).
7. **Tm non calculable** : message distinct (moins de deux bases / base dégénérée / X) via `OligoTm::unavailableReason()` ; avertissements de fenêtre et d'écart F/R précisant « oligo entier, partie hybridante non annotée ».
8. **PDF** : échappements `#xx` des noms décodés avant le scan ; refus aussi de `/OpenAction`, `/AA`, `/EmbeddedFile(s)`, `/RichMedia`, `/GoToR`, `/SubmitForm` ; fichier stocké en 0640 avec `LOCK_EX` ; tests (`/J#53`, `/OpenAction`, etc.).
9. **Dépôt** : refusé sur vecteur archivé ou en quarantaine ; 10 pièces jointes au plus par vecteur ; dédoublonnage (vecteur, sha256) avec contrainte d'unicité en base ; tests.
10. **Capacités de tag** : unicité (vecteur, position) + migration ; la modification d'un tag se fait en place (sinon l'insertion avant suppression de Doctrine violerait la contrainte). `FieldHistory.entity_type` = nom de classe complet (colonne 255).
11. **Verrou de séquence d'oligo** : appliqué aussi hors formulaire par `OligoLockSubscriber` (onFlush, exception `LockedOligoException` sans séquence dans le message) ; test.
12. **Tests ajoutés** : site non palindromique BsaI sur le brin reverse à cheval sur l'origine (biophp compte 1 coupure, comme sur le brin direct) ; Tm identique quelle que soit la casse ; oligo de 1 nt.
13. **Isolation des tests** : `tests/bootstrap.php` donne à chaque processus PHPUnit sa base SQLite (`var/test/data_<pid>.db`) et son répertoire de pièces jointes (`var/test/attachments_<pid>`), supprimés en fin de processus ; paramètre `TEST_ATTACHMENTS_DIR` dans `config/services.yaml`.

Dettes restantes / non faits :

- Pas de test sur un vrai MySQL (aucun accès) : MEDIUMTEXT vérifié seulement par le DDL généré par DBAL et par `migrations:diff` sur SQLite.
- Le scan de PDF reste un balayage d'octets : des mots-clés dans des flux compressés (object streams) ne sont pas détectés ; refus de `/OpenAction` possiblement trop strict pour des PDF légitimes (LaTeX/hyperref), à réévaluer avec les blobs du dump.
- Plafond de 10 pièces jointes non protégé contre deux dépôts simultanés (pas de contrainte en base).
- Pas d'écran de quarantaine, pas de suppression de pièces jointes (hors périmètre).
- Le verrou d'oligo ne couvre pas une PCR créée dans le même flush que le changement de séquence.
- Historique de construction : `vector`/`insert` stockés par nom (pas par identifiant) ; un renommage ultérieur ne réécrit pas l'historique.
- Anciennes lignes `field_history` (aucune en production) auraient un `entity_type` court : sans objet tant que LAB-3 n'est pas déployé.
