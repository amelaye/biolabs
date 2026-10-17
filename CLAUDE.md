# biolabs — instructions pour Claude Code

biolabs est le **LIMS de biologie moléculaire et de production de protéines** qui remplace Plasmi::db 0.9.1 (PHP 5 / PEAR, 2009). Il est construit avec Symfony sur le modèle de `../biophp-demo` et s'appuie sur `amelaye/biophp` et `amelaye/biotools` pour tout calcul biologique.

Statut : **LAB-0 livré** (socle Symfony, connexion, rôles, audit, commande de création de compte). Les lots suivants n'ont pas démarré. Voir `docs/lots/`.

## Règle n° 1 — feu vert obligatoire

Ne démarrer **aucun lot** (squelette, entités, import…) sans le feu vert explicite de la personne qui pilote le projet, donné dans la conversation en cours. Une validation passée ne vaut pas pour le lot suivant. En l'absence de feu vert : analyser, spécifier, proposer — ne pas écrire de code applicatif.

## Écosystème

| Chemin | Rôle | Droits |
|---|---|---|
| `.` (biolabs) | LIMS : entités, traçabilité, pages, import | lecture/écriture |
| `../plasmidb-0.9.1/` | Source historique (plasmidb, Bio, Phli, Html, dump SQL) | **lecture seule** |
| `../biophp/` | Primitives biologiques (`Domain/`) | lecture seule sauf demande explicite |
| `../biotools/` | Managers, Form Types, rendu | lecture seule sauf demande explicite |
| `../biophp-demo/` | Application modèle (conventions Symfony, templates, traductions) | lecture seule |
| `../bioapi/` | API de données de référence BioPHP | lecture seule |

Frontière : **biolabs ne contient aucun algorithme biologique.** Un calcul absent des bibliothèques fait l'objet d'une demande pour biophp/biotools, pas d'une implémentation locale. Détail dans le skill `biolabs-architecture`.

## Skills (`.claude/skills/`)

| Skill | Usage |
|---|---|
| `plasmidb-refactoring` | Méthode de refonte et découpage en lots — **à charger pour toute demande de migration** |
| `plasmidb-domain` | Connaissance de Plasmi::db : tables, chaîne métier, designers, défauts |
| `biolabs-architecture` | Où placer quoi ; services biophp/biotools disponibles ; pile cible |
| `wet-lab-conventions` | Unités, plausibilité, nomenclature, cycle de vie des échantillons, étiquettes |
| `bioinformatics` | Garde-fous scientifiques (séquences, coordonnées, Tm, restriction, masses) |

## Agents (`.claude/agents/`)

Biologistes — spécifient et valident, n'écrivent pas de code :

| Agent | Domaine |
|---|---|
| `molecular-biologist` | Gènes, primers, PCR, inserts, vecteurs, constructions, tags, séquençage |
| `protein-biochemist` | Expression, purification, lots protéiques, MS, cristallisation |
| `lab-workflow-biologist` | Modèle d'échantillons, traçabilité, stockage, étiquettes, rôles, plan de migration |
| `bioinfo-reviewer` | Revue scientifique indépendante, lecture seule |

Techniques :

| Agent | Rôle | Écrit du code |
|---|---|---|
| `legacy-analyst` | Inventaire et analyse du code et des données Plasmi::db | non |
| `symfony-developer` | Implémentation d'un lot validé | oui, après feu vert |
| `data-migration-engineer` | Import du dump Plasmi::db | oui, après feu vert |
| `test-reviewer` | Couverture et qualité des tests, exécution | tests uniquement |
| `code-reviewer` | Revue technique et sécurité, lecture seule | non |

## Déroulé d'un lot

```text
1. legacy-analyst            → rapport d'inventaire
2. agent(s) biologiste(s)    → fiche docs/lots/<LOT>.md validée
3. ── feu vert de la personne qui pilote le projet ──
4. symfony-developer         → implémentation + tests
   (data-migration-engineer  → import, si prévu et autorisé)
5. bioinfo-reviewer          → si séquences, coordonnées, unités ou calculs touchés
6. test-reviewer + code-reviewer
7. synthèse : changements, commandes réellement exécutées, risques, questions
```

Lots prévus (ordre modifiable sur demande) : LAB-0 squelette & sécurité · LAB-1 référentiels · LAB-2 cibles · LAB-3 clonage · LAB-4 échantillons & étiquettes · LAB-5 production · LAB-6 caractérisation · LAB-7 import · LAB-X outils de design biotools.

## Pile cible

PHP ≥ 8.2 (8.5 installé localement), Symfony 7.4, Doctrine ORM 3 + migrations, Twig, Form, Validator, Security, Translation FR/EN, PHPUnit 11, `amelaye/biophp` et `amelaye/biotools` aux versions de biophp-demo (actuellement 2.0.1 / 2.0.0) sauf décision explicite. MySQL disponible localement.

## Commandes

```bash
composer install
bin/console doctrine:migrations:migrate        # SQLite par défaut (var/data.db)
bin/console biolabs:user:create prenom.nom@exemple.org "Prénom Nom" --role=ROLE_ADMIN
symfony server:start                           # ou php -S localhost:8000 -t public
vendor/bin/phpunit
bin/console doctrine:migrations:diff           # doit répondre « No changes detected »
```

Piège connu : `doctrine:schema:validate` signale toujours les tables de biophp (voir `docs/lots/LAB-0.md`) ; utiliser `migrations:diff`.
Les migrations s'écrivent avec l'API `Schema` (pas de SQL brut) pour rester valables sur SQLite et MySQL.

## Règles permanentes

- **En-tête de tout fichier PHP créé ou modifié** (même convention que biophp et biotools) : bloc de tête avant `declare(strict_types=1);` puis `@author` dans la docblock de la classe.
  ```php
  /**
   * Titre court du fichier
   * Freely inspired by Plasmi::db 0.9.1 (Mark Brooks) and BioPHP's project biophp.org
   * Created 17 October 2026
   * Last modified 17 October 2026
   */
  ```
  et `@author Amélie DUVERNET aka Amelaye <amelieonline@gmail.com>`. La date « Created » ne change jamais ; **« Last modified » est mise à jour à chaque modification du fichier** (format `17 October 2026`, mois en anglais). Ne pas toucher la date d'un fichier non modifié.
- Ne jamais copier de code de `../plasmidb-0.9.1/` ; s'en inspirer fonctionnellement.
- Ne jamais recopier les secrets, hash, sessions ou emails présents dans `../plasmidb-0.9.1/plasmidb/config/` et dans le dump SQL — ni dans le code, ni dans les fixtures, ni dans une réponse.
- Fixtures et exemples de test : données synthétiques.
- Secrets uniquement dans `.env.local` (non versionné).
- Aucune séquence ni donnée expérimentale dans les URL, logs ou messages d'erreur.
- Quantités toujours avec unité ; séquences brute + normalisée ; oligos en 5′→3′.
- Pas de commande destructive (drop de base, `rm -rf`, reset/force-push git) sans confirmation.
- Ne jamais affirmer qu'un test ou une commande passe sans l'avoir exécuté.
- Le dépôt n'est pas sous git : la décision revient à la personne qui pilote le projet, ne pas faire `git init` d'office.

## Langue

- **Commentaires, docblocks et variables : TOUJOURS EN ANGLAIS** (aussi les noms de classes, méthodes, champs, clés de données de test, messages d'exception et d'assertion destinés aux développeurs). Cela vaut pour le PHP, les commentaires YAML/`.env`/Twig et le SQL.
- Documentation (`docs/`, `README.md`, `CLAUDE.md`, skills, agents) et messages de commit : français.
- Textes de l'interface web : français et anglais via `translations/`, jamais en dur dans un template. La sortie des commandes console reste en français (non traduite).
