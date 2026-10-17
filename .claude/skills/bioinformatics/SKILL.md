---
name: bioinformatics
description: Garde-fous scientifiques pour tout code biolabs qui manipule ou affiche un résultat biologique calculé — séquences, traduction, primers, Tm, sites de restriction, cartes plasmidiques, coordonnées de features, masses et propriétés protéiques, parseurs FASTA/GenBank. À charger avant d'écrire ou de relire un tel code. Ne pas charger pour du CRUD pur, du routage, du style ou du déploiement qui ne peuvent pas changer un résultat scientifique.
---

# Garde-fous bioinformatiques — biolabs

biolabs est une application de laboratoire (LIMS) issue de Plasmi::db. Elle **consomme** les calculs de `amelaye/biophp` et `amelaye/biotools` ; elle ne doit pas les réimplémenter. Ce skill sert à vérifier que les données biologiques transitent, sont stockées et sont affichées sans être corrompues, et que les appels aux bibliothèques sont scientifiquement corrects.

## Règles fondamentales

1. Le code historique (`../plasmidb-0.9.1/`) et les tests qui passent sont des **preuves de comportement**, pas des preuves de justesse scientifique.
2. Avant tout calcul ou toute transformation, expliciter : type de molécule, alphabet, brin et sens (5′→3′), système de coordonnées (0/1-based, borne de fin incluse/exclue, circularité), unités et jeu de paramètres.
3. Distinguer quatre situations :
   - **défaut logiciel** : le code contredit son propre modèle documenté ;
   - **défaut scientifique** : le modèle hérité est faux pour l'usage revendiqué ;
   - **différence de compatibilité** : le résultat historique est voulu mais diffère d'une convention préférable ;
   - **comportement sous-spécifié** : plusieurs résultats défendables faute de convention.
4. Ne jamais remplacer silencieusement un modèle scientifique par un autre (ex. Tm Wallace → nearest-neighbor), même « meilleur ». Rendre le changement explicite, documenté et testé.
5. Si un calcul manque dans biophp/biotools, **ne pas l'écrire dans biolabs** : le signaler et proposer de l'ajouter dans la bibliothèque concernée (voir skill `biolabs-architecture`).
6. Un oracle de test doit être indépendant du code de production (valeur littérale sourcée, outil tiers versionné, invariant mathématique).

## Spécificités d'un LIMS

Les erreurs scientifiques d'un LIMS sont surtout des erreurs de **conservation de l'information** :

- **Normalisation de séquence** : Plasmi::db stocke des séquences avec espaces, numéros, `\r\n` et casse mixte (la casse minuscule y marque parfois un site de restriction ou un clamp, ex. `GTCccatggGCATG…`). Décider explicitement ce qui est conservé (séquence brute) et ce qui est dérivé (séquence normalisée). Ne jamais perdre la version brute lors d'une migration.
- **Alphabet** : refuser ou signaler les symboles invalides au lieu de les supprimer. Les codes IUPAC ambigus sont légitimes dans un site d'enzyme (`GACNNNNNNGTC`), pas forcément dans une séquence de plasmide séquencé.
- **Coordonnées de features** : Plasmi::db utilise `start_pos` + `length` + `is_reverse`. Toute conversion vers `start`/`end` doit fixer la base (1-based inclusif recommandé, comme GenBank) et traiter les features qui **traversent l'origine** d'un plasmide circulaire.
- **Circularité** : un plasmide est circulaire ; un insert, un produit PCR ou un primer sont linéaires. Ne jamais appliquer une digestion linéaire à un plasmide.
- **Orientation des primers** : toujours stockés et affichés 5′→3′. Le primer reverse est le reverse-complément du brin codant, pas son complément.
- **Traduction** : utiliser la table génétique de l'espèce (`species.species_ncbi_trans_tab`), pas implicitement la table 1.
- **Unités** : voir skill `wet-lab-conventions`. Une valeur sans unité n'est pas migrable sans décision.

## Méthode

1. Localiser le contrat public (service biophp/biotools appelé), l'appelant biolabs, les tests et le comportement historique Plasmi::db équivalent.
2. Énoncer l'interprétation scientifique avant d'éditer.
3. Lire uniquement les références utiles ci-dessous.
4. Faire le plus petit changement cohérent ; ne pas élargir l'alphabet accepté ou la normalisation par effet de bord.
5. Tester contre un résultat attendu indépendant et au moins une propriété (invariant).
6. Pour tout changement pouvant modifier un résultat scientifique, demander une passe en lecture seule à l'agent `bioinfo-reviewer`.

## Références

- Alphabets ADN/ARN, complément, reverse-complément, transcription, codons, ORF, traduction : [sequences-and-translation.md](references/sequences-and-translation.md)
- Primers, PCR, Tm, sites de restriction, coordonnées de coupure : [pcr-tm-restriction.md](references/pcr-tm-restriction.md)
- Masses, pI, charge, coefficient d'extinction, hydropathie : [molecular-properties.md](references/molecular-properties.md)
- FASTA, GenBank, EMBL, UniProt, PDB : [file-formats.md](references/file-formats.md)
- Alignements : [alignments.md](references/alignments.md)
- CGR, k-mers, skews : [signatures-and-skews.md](references/signatures-and-skews.md)
- Stratégie de test, oracles, tolérances : [testing-strategy.md](references/testing-strategy.md)

Ces références sont partagées avec biophp et biotools ; en cas de divergence, la version de `../biophp/.claude/` fait foi et doit être corrigée à la source.

## Questions de revue obligatoires

- ADN, ARN ou protéine ? Symboles ambigus autorisés ?
- Toute séquence est-elle documentée en 5′→3′ ?
- Coordonnées 0- ou 1-based, fin incluse ou exclue, molécule circulaire ou linéaire ?
- Unités et paramètres physiques visibles à l'interface ?
- Comportement délibéré pour vide, longueur 1, minuscules, ambigus, invalides, très longues séquences ?
- La donnée brute historique est-elle conservée après migration ?
- Un oracle indépendant confirme-t-il des cas représentatifs ?
