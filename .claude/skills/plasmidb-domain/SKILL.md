---
name: plasmidb-domain
description: Connaissance métier du programme de laboratoire historique Plasmi::db 0.9.1 (../plasmidb-0.9.1) — ses 46 tables, la chaîne gène → produit → primer → PCR → insert → plasmide construit → clone → stocks/préparations → expression → purification → spectrométrie de masse/cristallisation, ses designers de primers et ses défauts connus. À charger pour analyser, cartographier ou migrer une fonctionnalité ou une table de Plasmi::db vers biolabs.
---

# Domaine Plasmi::db

Plasmi::db (Mark Brooks, Université Paris-Sud, 2008-2009, GPL) est un LIMS de biologie structurale : il suit la production de protéines recombinantes depuis le gène cible jusqu'au cristal. Source **en lecture seule** : `../plasmidb-0.9.1/`.

## Organisation du code historique

| Répertoire | Contenu | Statut pour biolabs |
|---|---|---|
| `plasmidb/app/controllers/` | 28 contrôleurs CRUD, un par table | Inspiration fonctionnelle seulement |
| `plasmidb/app/models/` | DataObjects PEAR générés | Schéma à cartographier |
| `plasmidb/app/views/` | Formulaires QuickForm `.phtml` | Liste des champs saisis |
| `plasmidb/lib/generators/PrimerGenerator.php` | Génération de primers de clonage | Logique bio → biotools |
| `plasmidb/lib/accessories/designers/` | 5 designers : standard, polycistronique, pST39, TAP, ARN hammerhead | Logique bio → biotools |
| `plasmidb/lib/accessories/PCRrecipe.php` | Calculateur de master mix | Logique bio → biotools |
| `plasmidb/lib/accessories/labels/` | Étiquettes clones, préps ADN, expressions, stocks, primers | Fonction LIMS → biolabs |
| `plasmidb/lib/accessories/soap/` | Clients SOAP BLAST / ClustalW | Ne pas migrer |
| `plasmidb/lib/AppValidator.php` | Validations (sites de restriction internes…) | Règles à recenser |
| `plasmidb/plasmidb-0.9.1.sql` | Dump MySQL schéma + données | Schéma et jeux d'exemple |
| `Bio/` | Ancien BioPHP (Seq, Translator, Restriction, Tm NN) | Remplacé par `amelaye/biophp` |
| `Phli/`, `Html/` | Mini-framework MVC et helpers HTML maison | Ne pas migrer |

## Chaîne métier

```text
complex ─┬─ complex_subunit ─┬─ subunit (gène/CDS, espèce, base externe)
                                     │
                                  product (cible : protéine/ARN/ADN, fenêtre start–end sur la sous-unité)
                                     │
            primer (F/R) ──► pcr (matrice, séquence voulue) ──► insrt (fragment PCR ou restriction)
                                                                   │
            std_plasmid (vecteur commercial) ──────────────► constr_plasmid (vecteur + insert) ◄─► product
                                                                   │            └── feature (annotations)
                                                                 clne (clone, positif ?)
                                         ┌─────────────┬───────────┼──────────────┐
                                  glycerol_stock    dna_prep   sequencing        expr (protocole, souche, aliquots)
                                                                                   │
                                                                              product_prep (purification)
                                                                         ┌─────────┴──────────┐
                                                               crystal_screen_exp      ms_sample → ms_spectrum → ms_peak
                                                                                        fragment (masses théoriques/observées)
```

Détail des tables, colonnes, relations et pièges : [references/data-model.md](references/data-model.md).
Workflows, designers et calculs historiques : [references/workflows.md](references/workflows.md).
Défauts et risques connus : [references/legacy-defects.md](references/legacy-defects.md).

## Règles d'usage

1. Lire la table **et** son contrôleur **et** sa vue `_form.phtml` avant de conclure sur une fonctionnalité : le schéma seul ne dit pas ce qui est réellement saisi.
2. Les clés étrangères historiques sont souvent typées `varchar`, nullables à `0`, ou pointent vers des tables inexistantes (`std_plasmid.cleavage_id`). Ne jamais les reprendre telles quelles.
3. Le dump contient des **données personnelles et des secrets** (emails réels, hash MD5, session avec mot de passe en clair ; DSN avec mot de passe dans `config/dataobject.conf`). Ne jamais les recopier dans biolabs, dans des fixtures, des tests, des logs ni dans un message. Fixtures : données synthétiques.
4. Les cartes de vecteurs sont des PDF stockés en blob dans `std_plasmid` : à traiter comme pièces jointes, pas comme données.
5. Plasmi::db est une **source d'inspiration** : biolabs n'a aucune obligation de compatibilité d'URL, de session ou de format HTML. L'obligation porte sur la conservation des données scientifiques lors d'une migration.
6. Toute affirmation sur le comportement historique doit citer le fichier et la ligne.
