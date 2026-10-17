---
name: wet-lab-conventions
description: Conventions de paillasse pour un LIMS de biologie moléculaire et de production de protéines — unités, plages de valeurs plausibles, nomenclature des souches, plasmides, tags et oligos, cycle de vie des échantillons, étiquetage, sécurité biologique. À charger pour concevoir ou valider un formulaire, une entité, une validation, une étiquette ou une migration de données expérimentales dans biolabs.
---

# Conventions de laboratoire — biolabs

Ces conventions servent à modéliser et valider des données saisies par des biologistes. Elles ne remplacent pas les protocoles du laboratoire : en cas de doute, **poser la question à l'utilisateur** plutôt que fixer une règle.

## 1. Unités : toujours explicites

| Grandeur | Unité canonique de stockage | Unités de saisie acceptables | Notes |
|---|---|---|---|
| Volume | µL | nL, µL, mL, L | Plasmi::db : `aliquot_vol`, `product_prep_volume`, `expr_protocol_volume` sans unité → **décision nécessaire** (µL pour aliquots, mL pour culture probable, à confirmer) |
| Concentration ADN | ng/µL | ng/µL, µg/mL (équivalents) | `dna_prep_conc` |
| Concentration protéine | mg/mL | mg/mL, µM (nécessite la masse molaire) | `product_prep_conc` ; conversion molaire déléguée à biophp |
| Concentration oligo | µM | µM, nmol (quantité livrée) | |
| Masse / quantité | mg ou nmol | | rendement : mg/L de culture |
| Température | °C | °C | induction 16–37 °C typique |
| Durée | min (stockage) | min, h, nuit (≈16 h, à rendre explicite) | `induction_time` sans unité |
| Longueur | pb (bp) pour ADN, nt pour ARN/oligo, aa pour protéine | | |
| Masse moléculaire | Da (kDa en affichage) | | |
| m/z | Th (sans unité usuelle) | | précision ≥ 4 décimales, pas de `float(8,4)` |

Règles :

- stocker une quantité = **valeur décimale + unité** (value object), jamais un flottant nu ;
- convertir à la saisie, afficher dans l'unité usuelle ;
- les symboles `µ` et `°` doivent survivre à la conversion latin1 → UTF-8 ;
- une valeur historique sans unité est migrée avec une unité **présumée** marquée comme telle, ou laissée en quarantaine.

## 2. Plages de plausibilité (avertissements, pas erreurs bloquantes)

| Champ | Plausible | Avertir si |
|---|---|---|
| Longueur de primer | 18–60 nt (jusqu'à ~100 avec extensions) | < 15 ou > 100 |
| Tm partie hybridante | 50–72 °C | hors plage ou écart F/R > 5 °C |
| Volume de réaction PCR | 10–100 µL | eau calculée < 0 |
| Concentration ADN miniprep | 20–500 ng/µL | ≤ 0 ou > 5000 |
| Concentration protéine | 0.1–50 mg/mL | ≤ 0 |
| Aliquots restants | 0 ≤ restants ≤ préparés | négatif ou supérieur |
| Température d'induction | 4–42 °C | hors plage |

Une erreur bloquante est réservée aux impossibilités (volume négatif, aliquots restants > préparés, séquence vide pour un oligo).

## 3. Nomenclature

- **Souches** : conserver la graphie exacte avec génotype (`BL21(DE3)`, `BL21(DE3) pLysS`, `DH5α`) ; ne pas normaliser la casse ni retirer parenthèses ou lettres grecques.
- **Plasmides construits** : convention historique `vecteur::insert:tag` (ex. `pET28::VMA4:6H`) — à conserver comme nom affiché, mais la structure (vecteur, insert, tags) doit être modélisée par des relations, pas parsée depuis le nom.
- **Oligos** : nom unique dans le labo ; séquence 5′→3′ ; la casse minuscule historique signale des bases non hybridantes (clamp, site) : la conserver dans la séquence brute ou la modéliser en segments.
- **Tags** : 6xHis, 10xHis, Strep-tag II, GST, TAP… issus d'un référentiel avec séquence ADN, séquence protéique, position (N/C-terminal) et site de clivage éventuel (TEV, thrombine, 3C).
- **Marqueurs** : antibiotique + concentration de travail (ex. ampicilline 100 µg/mL — Plasmi::db indique 50 µg/mL dans `marker_desc`, valeur du labo à conserver telle quelle).

## 4. Cycle de vie d'un échantillon

```text
planifié → en cours → disponible → (partiellement consommé) → épuisé
                          └──────────→ détruit / perdu / contaminé
```

- chaque échantillon physique a : identifiant étiquette stable, type, parent (traçabilité), emplacement (congélateur / rack / boîte / position), date, opérateur, statut ;
- la consommation (aliquot utilisé) est un **événement** enregistré, pas une décrémentation silencieuse d'un compteur ;
- un échantillon dont un parent est déclaré contaminé ou séquence incorrecte doit être signalé (propagation d'alerte, pas suppression).

## 5. Étiquettes

Plasmi::db imprime des étiquettes pour clones, préps ADN, expressions, stocks glycérol, préps protéiques et primers (`lib/accessories/labels/`). Une étiquette biolabs contient au minimum : identifiant stable, nom court, date, opérateur (initiales), et éventuellement code-barres/QR de l'identifiant. Taille limitée : prévoir un nom abrégé. Résistance aux solvants et au froid : hors périmètre logiciel, mais le texte doit rester lisible en petit corps.

## 6. Sécurité biologique et données

- consigner le niveau de confinement (L1/L2) d'une souche ou d'un plasmide si le labo le demande ; ne pas l'inventer ;
- les séquences de constructions non publiées sont des données confidentielles : pas dans les logs, pas dans les URL, pas dans les messages d'erreur ;
- ne jamais générer de séquence ou de protocole visant à augmenter la pathogénicité, la transmissibilité ou la résistance d'un agent ; ce n'est pas le rôle d'un LIMS.
