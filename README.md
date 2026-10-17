# biolabs

LIMS de biologie moléculaire et de production de protéines, successeur de Plasmi::db, construit sur Symfony 7.4 avec `amelaye/biophp` et `amelaye/biotools`.

Statut : en construction, par lots. Voir [docs/lots/](docs/lots/) et [CLAUDE.md](CLAUDE.md).

## Démarrage

```bash
composer install
echo "APP_SECRET=$(php -r 'echo bin2hex(random_bytes(16));')" > .env.local
bin/console doctrine:migrations:migrate
bin/console biolabs:user:create vous@exemple.org "Votre Nom" --role=ROLE_ADMIN
symfony server:start
```

Base de données : SQLite par défaut. Pour MySQL, définir `DATABASE_URL` dans `.env.local` (voir `.env`).

## Tests

```bash
vendor/bin/phpunit
```

## Licence

GPL-2.0-only, comme `amelaye/biophp`, `amelaye/biotools` et Plasmi::db dont biolabs est le successeur. Voir [LICENSE](LICENSE).
