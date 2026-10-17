<?php
/**
 * LAB-1 migration : reference lists (organism, strain, marker, promoter, origin, tag, supplier)
 * Freely inspired by Plasmi::db 0.9.1 (Mark Brooks) and BioPHP's project biophp.org
 * Created 17 October 2026
 * Last modified 17 October 2026
 */
declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use Doctrine\Migrations\AbstractMigration;

/**
 * LAB-1: the seven reference lists and the strain/marker resistance link.
 *
 * Written with the Schema API rather than raw SQL to stay valid on SQLite (development,
 * tests) as well as on MySQL (production). Index and foreign key names are the ones
 * Doctrine generates, so that migrations:diff reports no change.
 *
 * @author Amélie DUVERNET aka Amelaye <amelieonline@gmail.com>
 */
final class Version20261017210000 extends AbstractMigration
{
    private const LISTS = ['organism', 'strain', 'selection_marker', 'promoter', 'replication_origin', 'tag', 'supplier'];

    /**
     * Short description shown by the migrations commands.
     *
     * @return string The purpose of the migration
     */
    public function getDescription(): string
    {
        return 'LAB-1: reference lists (organism, strain, selection marker, promoter, replication origin, tag, supplier)';
    }

    /**
     * Creates the seven reference tables and the strain/marker resistance link.
     *
     * @param Schema $schema Schema to modify
     * @return void
     */
    public function up(Schema $schema): void
    {
        foreach (self::LISTS as $list) {
            $table = $schema->createTable('bl_'.$list);
            $table->addColumn('id', Types::INTEGER, ['autoincrement' => true]);
            $table->addColumn('name', Types::STRING, ['length' => 100]);
            $table->addColumn('name_key', Types::STRING, ['length' => 200]);
            $table->addColumn('active', Types::BOOLEAN);
            $table->addColumn('notes', Types::TEXT, ['notnull' => false]);

            match ($list) {
                'organism' => $this->organismColumns($table),
                'strain' => $this->strainColumns($table),
                'selection_marker' => $this->markerColumns($table),
                'tag' => $this->tagColumns($table),
                'supplier' => $table->addColumn('url', Types::STRING, ['length' => 255, 'notnull' => false]),
                default => null,
            };

            $this->auditColumns($table);
            $table->setPrimaryKey(['id']);
            $table->addUniqueIndex(['name_key'], 'uniq_bl_'.$list.'_name_key');
        }

        $link = $schema->createTable('bl_strain_resistance');
        $link->addColumn('strain_id', Types::INTEGER);
        $link->addColumn('selection_marker_id', Types::INTEGER);
        $link->setPrimaryKey(['strain_id', 'selection_marker_id']);
        $link->addIndex(['strain_id'], $this->identifier('IDX', 'bl_strain_resistance', ['strain_id']));
        $link->addIndex(['selection_marker_id'], $this->identifier('IDX', 'bl_strain_resistance', ['selection_marker_id']));
        $link->addForeignKeyConstraint('bl_strain', ['strain_id'], ['id'], ['onDelete' => 'CASCADE'], $this->identifier('FK', 'bl_strain_resistance', ['strain_id']));
        $link->addForeignKeyConstraint('bl_selection_marker', ['selection_marker_id'], ['id'], ['onDelete' => 'CASCADE'], $this->identifier('FK', 'bl_strain_resistance', ['selection_marker_id']));
    }

    /**
     * Drops the reference tables.
     *
     * @param Schema $schema Schema to modify
     * @return void
     */
    public function down(Schema $schema): void
    {
        $schema->dropTable('bl_strain_resistance');
        foreach (array_reverse(self::LISTS) as $list) {
            $schema->dropTable('bl_'.$list);
        }
    }

    /**
     * Adds the columns specific to an organism.
     *
     * @param Table $table Table being built
     * @return void
     */
    private function organismColumns(Table $table): void
    {
        $table->addColumn('tax_id', Types::INTEGER, ['notnull' => false]);
        $table->addColumn('genetic_code', Types::INTEGER);
        $table->addColumn('mitochondrial_genetic_code', Types::INTEGER, ['notnull' => false]);
    }

    /**
     * Adds the columns specific to a strain.
     *
     * @param Table $table Table being built
     * @return void
     */
    private function strainColumns(Table $table): void
    {
        $table->addColumn('strain_usage', Types::STRING, ['length' => 20, 'notnull' => false]);
        $table->addColumn('genotype', Types::TEXT, ['notnull' => false]);
        $table->addColumn('is_de3', Types::BOOLEAN);
    }

    /**
     * Adds the columns specific to a selection marker.
     *
     * @param Table $table Table being built
     * @return void
     */
    private function markerColumns(Table $table): void
    {
        $table->addColumn('working_concentration_ug_ml', Types::FLOAT, ['notnull' => false]);
        $table->addColumn('stock_concentration_mg_ml', Types::FLOAT, ['notnull' => false]);
    }

    /**
     * Adds the columns specific to a tag.
     *
     * @param Table $table Table being built
     * @return void
     */
    private function tagColumns(Table $table): void
    {
        $table->addColumn('purpose', Types::STRING, ['length' => 20]);
        $table->addColumn('dna_sequence', Types::STRING, ['length' => 500, 'notnull' => false]);
    }

    /**
     * Adds the audit columns, their indexes and their foreign keys to the user table.
     *
     * @param Table $table Table being built
     * @return void
     */
    private function auditColumns(Table $table): void
    {
        $name = $table->getName();
        $table->addColumn('created_at', Types::DATETIME_IMMUTABLE, ['notnull' => false]);
        $table->addColumn('updated_at', Types::DATETIME_IMMUTABLE, ['notnull' => false]);
        $table->addColumn('created_by_id', Types::INTEGER, ['notnull' => false]);
        $table->addColumn('updated_by_id', Types::INTEGER, ['notnull' => false]);
        foreach (['created_by_id', 'updated_by_id'] as $column) {
            $table->addIndex([$column], $this->identifier('IDX', $name, [$column]));
            $table->addForeignKeyConstraint('bl_user', [$column], ['id'], ['onDelete' => 'SET NULL'], $this->identifier('FK', $name, [$column]));
        }
    }

    /**
     * Name Doctrine gives to an index or foreign key: prefix + CRC32 of the table and column names.
     *
     * @param string $prefix IDX or FK
     * @param string $table Table name
     * @param list<string> $columns Column names
     * @return string The identifier (30 characters at most)
     */
    private function identifier(string $prefix, string $table, array $columns): string
    {
        $hash = implode('', array_map(static fn (string $part): string => dechex(crc32($part)), [$table, ...$columns]));

        return strtoupper(substr($prefix.'_'.$hash, 0, 30));
    }
}
