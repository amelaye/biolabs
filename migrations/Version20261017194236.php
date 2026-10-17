<?php
/**
 * LAB-0 migration : bl_user table
 * Freely inspired by Plasmi::db 0.9.1 (Mark Brooks) and BioPHP's project biophp.org
 * Created 17 October 2026
 * Last modified 17 October 2026
 */
declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Doctrine\Migrations\AbstractMigration;

/**
 * LAB-0: users table.
 *
 * Written with the Schema API rather than raw SQL to stay valid on SQLite (development,
 * tests) as well as on MySQL (production).
 *
 * @author Amélie DUVERNET aka Amelaye <amelieonline@gmail.com>
 */
final class Version20261017194236 extends AbstractMigration
{
    /**
     * Short description shown by the migrations commands.
     *
     * @return string The purpose of the migration
     */
    public function getDescription(): string
    {
        return 'LAB-0: bl_user table (accounts, roles, audit columns)';
    }

    /**
     * Creates the user table (accounts, roles, audit columns).
     *
     * @param Schema $schema Schema to modify
     * @return void
     */
    public function up(Schema $schema): void
    {
        $table = $schema->createTable('bl_user');
        $table->addColumn('id', Types::INTEGER, ['autoincrement' => true]);
        $table->addColumn('email', Types::STRING, ['length' => 180]);
        $table->addColumn('display_name', Types::STRING, ['length' => 100]);
        $table->addColumn('roles', Types::JSON);
        $table->addColumn('password', Types::STRING, ['length' => 255]);
        $table->addColumn('active', Types::BOOLEAN);
        $table->addColumn('is_system', Types::BOOLEAN);
        $table->addColumn('locale', Types::STRING, ['length' => 5, 'notnull' => false]);
        $table->addColumn('last_login_at', Types::DATETIME_IMMUTABLE, ['notnull' => false]);
        $table->addColumn('created_at', Types::DATETIME_IMMUTABLE, ['notnull' => false]);
        $table->addColumn('updated_at', Types::DATETIME_IMMUTABLE, ['notnull' => false]);
        $table->addColumn('created_by_id', Types::INTEGER, ['notnull' => false]);
        $table->addColumn('updated_by_id', Types::INTEGER, ['notnull' => false]);

        $table->setPrimaryKey(['id']);
        $table->addUniqueIndex(['email'], 'uniq_bl_user_email');
        $table->addIndex(['created_by_id'], 'IDX_E8E71CDCB03A8386');
        $table->addIndex(['updated_by_id'], 'IDX_E8E71CDC896DBBDE');
        $table->addForeignKeyConstraint('bl_user', ['created_by_id'], ['id'], ['onDelete' => 'SET NULL'], 'FK_E8E71CDCB03A8386');
        $table->addForeignKeyConstraint('bl_user', ['updated_by_id'], ['id'], ['onDelete' => 'SET NULL'], 'FK_E8E71CDC896DBBDE');
    }

    /**
     * Drops the user table.
     *
     * @param Schema $schema Schema to modify
     * @return void
     */
    public function down(Schema $schema): void
    {
        $schema->dropTable('bl_user');
    }
}
