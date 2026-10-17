# Workflows et calculs historiques de Plasmi::db

Chaque section indique où lire le code, ce qu'il fait, et vers quoi il doit migrer. **Le code historique est une preuve de comportement, pas une spécification.**

## 1. Design de primers de clonage

Sources :

- `plasmidb/lib/generators/PrimerGenerator.php` (`generate()`, ~480 lignes)
- `plasmidb/lib/accessories/designers/primer_designer.phtml` (vecteurs prédéfinis : pET21a-SLIC, pET21a-SLIC-6His…, clamps `GTC`)
- variantes : `polycistronic_primer_designer.phtml`, `pst39_primer_designer.phtml` (vecteur polycistronique pST39), `tap_primer_designer.phtml` (tag TAP)

Comportement observé dans `PrimerGenerator::generate()` :

1. lit une ou plusieurs séquences FASTA (ADN codant) ;
2. contrôle que la séquence ne contient pas les sites des enzymes 5′ et 3′ choisies (`AppValidator::ValidateSeqContainsNoRestictionSites_`) ;
3. découpe la fenêtre codante à partir de numéros de **codons** `start`/`finish` (1-based, `(start-1)*3` → `finish*3`) ;
4. retire le codon stop final puis la Met initiale (`SeqUtils::StripStop_`, `StripIMet_`) ;
5. ajoute un tag codé en dur : `0` = 6His `CACCATCACCATCACCAT`, `1` = Strep `TGGAGCCACCCGCAGTTCGAAAAA`, `2` = 10His ; position N-term (tag après ATG ?), C-term, ou aucune (`ATG`) ;
6. construit primer = clamp + site de restriction + tag/ATG + partie hybridante, la longueur de la partie hybridante étant ajustée entre `iMinCodingSeqLen`/`iMaxCodingSeqLen` pour atteindre une Tm dans `[iMinCodingSeqTm, iMaxCodingSeqTm]` ;
7. affiche l'analyse (`PrimerAnalyser`, `RestrictionAnalyser`) et propose d'enregistrer primers, PCR, produit et plasmide construit via des formulaires.

Points à vérifier par un biologiste avant migration :

- le primer reverse et le tag C-terminal doivent être en **reverse-complément** et le stop doit être réintroduit si le vecteur ne le fournit pas ;
- le maintien du **cadre de lecture** entre tag, insert et vecteur ;
- le tag N-terminal est-il bien précédé d'un ATG ?
- le nombre de bases de clamp suffit-il pour l'enzyme choisie (efficacité de coupure en bout de fragment) ?

Cible : `PrimerDesign` dans **biotools** (voir `../biophp-demo/.claude/FEATURES_PLASMIDB_CLAUDE.md`, lot DEMO-1, et `../biotools/.claude/FEATURES_PLASMIDB_CLAUDE.md`). biolabs ne fait qu'**enregistrer** le résultat (oligos, PCR, construction) et le relier aux échantillons.

## 2. Tm des primers

Source : `Bio/Bio/Seq/Dna.php::calcTm()`.

Modèle nearest-neighbor avec des paramètres ΔH/ΔS par dinucléotide (`AA` : −7.9 / −22.2, compatibles avec les valeurs unifiées SantaLucia 1998). Concentrations d'oligo et de sel à lire dans la suite de la fonction avant de comparer avec `MeltingTemperatureManager` de biotools. Le résultat est stocké arrondi à l'entier (`primer_tm int`).

Ne jamais recalculer et écraser une Tm historique lors de la migration : conserver la valeur historique et, si besoin, calculer la nouvelle à part en documentant le modèle.

## 3. Recette PCR (master mix)

Source : `plasmidb/lib/accessories/PCRrecipe.php`.

- entrées : volume de réaction (50 ou 100 µL), concentration du tampon (10X ou 5X), volumes de dNTP, de chaque primer, de polymérase, de matrice (µL), nombre de réactions ;
- tampon = volume / facteur de concentration ; eau MQ = volume − (tampon + dNTP + 2 × primer + enzyme + matrice) ;
- multiplication simple par le nombre de réactions, **sans excès de pipetage** et **sans contrôle de volume d'eau négatif**.

Cible : `PcrRecipe` dans biotools (lot DEMO-3). biolabs peut lier une recette à une `PcrReaction` enregistrée.

## 4. Construction d'ARN (hammerhead)

Source : `plasmidb/lib/accessories/designers/rna_designer.phtml`.

Format : `CLAMP :: site1 :: promoteur T7 :: ARN voulu :: ribozyme hammerhead :: tige 3′ :: site2 :: clamp`. Lot facultatif (DEMO-5) ; ne pas l'intégrer à biolabs sans besoin confirmé.

## 5. Gestion d'échantillons (cœur LIMS — c'est le périmètre propre à biolabs)

Sources : contrôleurs et vues de `clne`, `glycerol_stock`, `dna_prep`, `expr`, `product_prep`, `sequencing`, et `lib/accessories/labels/*`.

- création d'un clone à partir d'un plasmide construit, marqué positif ou non (`clpos`) ;
- dérivation : stock glycérol (clone + souche), prép ADN (type, kit, concentration), séquençage ;
- expression : clone + protocole + souche, production d'aliquots (`aliquot_num_prepared`, `aliquot_num_remain`) ;
- purification : lot protéique avec concentration, volume, rendement ;
- **étiquettes** imprimables pour clones, préps ADN, expressions, stocks glycérol, préps protéiques et primers ;
- export Excel des primers (`lib/accessories/excel/primer.php`) — commande d'oligos.

Absences notables à combler dans biolabs (à valider avec l'agent `lab-workflow-biologist`) : emplacement physique (congélateur/boîte/position), statut (disponible, épuisé, détruit), consommation d'aliquots tracée, utilisateur responsable, lien protocole versionné.

## 6. Fonctions à ne pas migrer

- clients SOAP BLAST et ClustalW (`lib/accessories/soap/`) : services EBI d'époque disparus ;
- `lib/accessories/attic/` : code abandonné par l'auteur ;
- menus, JavaScript ExtJS, sessions, authentification.
