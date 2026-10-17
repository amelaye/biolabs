<?php
/**
 * LAB-2 migration : targets (complex, gene, product)
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
 * LAB-2: complexes, genes, products and the complex/gene link.
 *
 * Written with the Schema API rather than raw SQL to stay valid on SQLite (development,
 * tests) as well as on MySQL (production). Index and foreign key names are the ones
 * Doctrine generates, so that migrations:diff reports no change.
 *
 * @author Amélie DUVERNET aka Amelaye <amelieonline@gmail.com>
 */
final class Version20261017220000 extends AbstractMigration
{
    /**
     * Short description shown by the migrations commands.
     *
     * @return string The purpose of the migration
     */
    public function getDescription(): string
    {
        return 'LAB-2: targets (complex, gene, product, complex/gene link)';
    }

    /**
     * Creates the complex, gene and product tables and the complex/gene link.
     *
     * @param Schema $schema Schema to modify
     * @return void
     */
    public function up(Schema $schema): void
    {
        $complex = $this->namedTable($schema, 'bl_target_complex');
        $this->auditColumns($complex);
        $this->finish($complex);

        $gene = $this->namedTable($schema, 'bl_target_gene');
        $gene->addColumn('organism_id', Types::INTEGER);
        $gene->addColumn('product_name', Types::STRING, ['length' => 50, 'notnull' => false]);
        $gene->addColumn('source_database', Types::STRING, ['length' => 20, 'notnull' => false]);
        $gene->addColumn('source_definition', Types::TEXT, ['notnull' => false]);
        $gene->addColumn('cds_sequence_raw', Types::TEXT);
        $gene->addColumn('cds_sequence', Types::TEXT);
        $this->auditColumns($gene);
        $this->finish($gene);
        $gene->addIndex(['organism_id'], $this->identifier('IDX', 'bl_target_gene', ['organism_id']));
        $gene->addForeignKeyConstraint('bl_organism', ['organism_id'], ['id'], [], $this->identifier('FK', 'bl_target_gene', ['organism_id']));

        $product = $this->namedTable($schema, 'bl_target_product');
        $product->addColumn('gene_id', Types::INTEGER);
        $product->addColumn('type', Types::STRING, ['length' => 20]);
        $product->addColumn('start_nt', Types::INTEGER, ['notnull' => false]);
        $product->addColumn('end_nt', Types::INTEGER, ['notnull' => false]);
        $this->auditColumns($product);
        $this->finish($product);
        $product->addIndex(['gene_id'], $this->identifier('IDX', 'bl_target_product', ['gene_id']));
        $product->addForeignKeyConstraint('bl_target_gene', ['gene_id'], ['id'], [], $this->identifier('FK', 'bl_target_product', ['gene_id']));

        $link = $schema->createTable('bl_target_complex_gene');
        $link->addColumn('complex_id', Types::INTEGER);
        $link->addColumn('gene_id', Types::INTEGER);
        $link->setPrimaryKey(['complex_id', 'gene_id']);
        $link->addIndex(['complex_id'], $this->identifier('IDX', 'bl_target_complex_gene', ['complex_id']));
        $link->addIndex(['gene_id'], $this->identifier('IDX', 'bl_target_complex_gene', ['gene_id']));
        $link->addForeignKeyConstraint('bl_target_complex', ['complex_id'], ['id'], ['onDelete' => 'CASCADE'], $this->identifier('FK', 'bl_target_complex_gene', ['complex_id']));
        $link->addForeignKeyConstraint('bl_target_gene', ['gene_id'], ['id'], ['onDelete' => 'CASCADE'], $this->identifier('FK', 'bl_target_complex_gene', ['gene_id']));
    }

    /**
     * Drops the target tables.
     *
     * @param Schema $schema Schema to modify
     * @return void
     */
    public function down(Schema $schema): void
    {
        foreach (['bl_target_complex_gene', 'bl_target_product', 'bl_target_gene', 'bl_target_complex'] as $table) {
            $schema->dropTable($table);
        }
    }

    /**
     * Creates a table with the columns shared by every named, archivable entry.
     *
     * @param Schema $schema Schema that receives the table
     * @param string $name Table name
     * @return Table The table being built
     */
    private function namedTable(Schema $schema, string $name): Table
    {
        $table = $schema->createTable($name);
        $table->addColumn('id', Types::INTEGER, ['autoincrement' => true]);
        $table->addColumn('name', Types::STRING, ['length' => 100]);
        $table->addColumn('name_key', Types::STRING, ['length' => 200]);
        $table->addColumn('active', Types::BOOLEAN);
        $table->addColumn('notes', Types::TEXT, ['notnull' => false]);

        return $table;
    }

    /**
     * Sets the primary key and the unique index of the normalised name.
     *
     * @param Table $table Table being built
     * @return void
     */
    private function finish(Table $table): void
    {
        $table->setPrimaryKey(['id']);
        $table->addUniqueIndex(['name_key'], 'uniq_'.$table->getName().'_name_key');
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
