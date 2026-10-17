<?php
/**
 * LAB-3 migration : cloning (oligo, vector, PCR, insert, construct, attachment, field history)
 * Freely inspired by Plasmi::db 0.9.1 (Mark Brooks) and BioPHP's project biophp.org
 * Created 17 October 2026
 * Last modified 18 October 2026
 */
declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use Doctrine\Migrations\AbstractMigration;

/**
 * LAB-3: oligos, vectors and their tag capabilities, attachments, PCRs, inserts, constructs,
 * the construct/product link and the history of tracked fields. Sequence, raw-value and history
 * columns are MEDIUMTEXT on MySQL (a vector accepts 300 000 characters; TEXT holds 64 KB).
 * The status of a construct is not stored: it is derived on display.
 *
 * Written with the Schema API rather than raw SQL to stay valid on SQLite (development,
 * tests) as well as on MySQL (production). Index and foreign key names are the ones
 * Doctrine generates, so that migrations:diff reports no change.
 *
 * @author Amélie DUVERNET aka Amelaye <amelieonline@gmail.com>
 */
final class Version20261017230000 extends AbstractMigration
{
    /** Length option that makes Doctrine create MEDIUMTEXT (16 MB) on MySQL; a plain TEXT stops at 64 KB. Ignored by SQLite. */
    private const MEDIUM_TEXT = 16777215;

    /**
     * Short description shown by the migrations commands.
     *
     * @return string The purpose of the migration
     */
    public function getDescription(): string
    {
        return 'LAB-3: cloning (oligo, vector, tag capability, attachment, PCR, insert, construct, field history)';
    }

    /**
     * Creates the cloning tables.
     *
     * @param Schema $schema Schema to modify
     * @return void
     */
    public function up(Schema $schema): void
    {
        $oligo = $this->namedTable($schema, 'bl_cloning_oligo');
        $oligo->addColumn('supplier_id', Types::INTEGER, ['notnull' => false]);
        $oligo->addColumn('sequence_raw', Types::TEXT, ['length' => self::MEDIUM_TEXT]);
        $oligo->addColumn('sequence', Types::TEXT, ['length' => self::MEDIUM_TEXT]);
        $this->quarantineColumns($oligo);
        $this->auditColumns($oligo);
        $this->finish($oligo);
        $this->reference($oligo, 'supplier_id', 'bl_supplier');

        $vector = $this->namedTable($schema, 'bl_cloning_vector');
        $vector->addColumn('supplier_id', Types::INTEGER, ['notnull' => false]);
        $vector->addColumn('marker_id', Types::INTEGER, ['notnull' => false]);
        $vector->addColumn('origin_id', Types::INTEGER, ['notnull' => false]);
        $vector->addColumn('promoter_id', Types::INTEGER, ['notnull' => false]);
        $vector->addColumn('sequence_raw', Types::TEXT, ['length' => self::MEDIUM_TEXT, 'notnull' => false]);
        $vector->addColumn('sequence', Types::TEXT, ['length' => self::MEDIUM_TEXT, 'notnull' => false]);
        $vector->addColumn('tag_state', Types::STRING, ['length' => 20]);
        $this->quarantineColumns($vector);
        $this->auditColumns($vector);
        $this->finish($vector);
        $this->reference($vector, 'supplier_id', 'bl_supplier');
        $this->reference($vector, 'marker_id', 'bl_selection_marker');
        $this->reference($vector, 'origin_id', 'bl_replication_origin');
        $this->reference($vector, 'promoter_id', 'bl_promoter');

        $capability = $schema->createTable('bl_cloning_vector_tag_capability');
        $capability->addColumn('id', Types::INTEGER, ['autoincrement' => true]);
        $capability->addColumn('vector_id', Types::INTEGER);
        $capability->addColumn('tag_id', Types::INTEGER);
        $capability->addColumn('position', Types::STRING, ['length' => 20]);
        $capability->setPrimaryKey(['id']);
        $capability->addUniqueIndex(['vector_id', 'position'], 'uniq_bl_cloning_vector_tag_capability_position');
        $this->reference($capability, 'vector_id', 'bl_cloning_vector', 'CASCADE');
        $this->reference($capability, 'tag_id', 'bl_tag');

        $attachment = $schema->createTable('bl_cloning_attachment');
        $attachment->addColumn('id', Types::INTEGER, ['autoincrement' => true]);
        $attachment->addColumn('vector_id', Types::INTEGER);
        $attachment->addColumn('storage_id', Types::STRING, ['length' => 36]);
        $attachment->addColumn('original_name', Types::STRING, ['length' => 150]);
        $attachment->addColumn('mime_type', Types::STRING, ['length' => 100]);
        $attachment->addColumn('size_bytes', Types::INTEGER);
        $attachment->addColumn('sha256', Types::STRING, ['length' => 64]);
        $attachment->addColumn('uploaded_by_id', Types::INTEGER, ['notnull' => false]);
        $attachment->addColumn('uploaded_at', Types::DATETIME_IMMUTABLE);
        $attachment->setPrimaryKey(['id']);
        $attachment->addUniqueIndex(['storage_id'], 'uniq_bl_cloning_attachment_storage_id');
        $attachment->addUniqueIndex(['vector_id', 'sha256'], 'uniq_bl_cloning_attachment_vector_sha256');
        $this->reference($attachment, 'vector_id', 'bl_cloning_vector');
        $this->reference($attachment, 'uploaded_by_id', 'bl_user', 'SET NULL');

        $pcr = $this->namedTable($schema, 'bl_cloning_pcr');
        $pcr->addColumn('forward_oligo_id', Types::INTEGER);
        $pcr->addColumn('reverse_oligo_id', Types::INTEGER);
        $pcr->addColumn('template_gene_id', Types::INTEGER, ['notnull' => false]);
        $pcr->addColumn('template_vector_id', Types::INTEGER, ['notnull' => false]);
        $pcr->addColumn('cycles', Types::INTEGER, ['notnull' => false]);
        $pcr->addColumn('denaturation_temp_c', Types::FLOAT, ['notnull' => false]);
        $pcr->addColumn('denaturation_seconds', Types::INTEGER, ['notnull' => false]);
        $pcr->addColumn('annealing_temp_c', Types::FLOAT, ['notnull' => false]);
        $pcr->addColumn('annealing_seconds', Types::INTEGER, ['notnull' => false]);
        $pcr->addColumn('extension_temp_c', Types::FLOAT, ['notnull' => false]);
        $pcr->addColumn('extension_seconds', Types::INTEGER, ['notnull' => false]);
        $pcr->addColumn('reaction_volume_ul', Types::FLOAT, ['notnull' => false]);
        $this->quarantineColumns($pcr);
        $this->auditColumns($pcr);
        $this->finish($pcr);
        $this->reference($pcr, 'forward_oligo_id', 'bl_cloning_oligo');
        $this->reference($pcr, 'reverse_oligo_id', 'bl_cloning_oligo');
        $this->reference($pcr, 'template_gene_id', 'bl_target_gene');
        $this->reference($pcr, 'template_vector_id', 'bl_cloning_vector');

        $insert = $this->namedTable($schema, 'bl_cloning_insert');
        $insert->addColumn('type', Types::STRING, ['length' => 30]);
        $insert->addColumn('five_prime_site', Types::STRING, ['length' => 50, 'notnull' => false]);
        $insert->addColumn('three_prime_site', Types::STRING, ['length' => 50, 'notnull' => false]);
        $insert->addColumn('directional', Types::BOOLEAN);
        $insert->addColumn('pcr_id', Types::INTEGER, ['notnull' => false]);
        $insert->addColumn('product_id', Types::INTEGER, ['notnull' => false]);
        $this->quarantineColumns($insert);
        $this->auditColumns($insert);
        $this->finish($insert);
        $this->reference($insert, 'pcr_id', 'bl_cloning_pcr');
        $this->reference($insert, 'product_id', 'bl_target_product');

        $construct = $this->namedTable($schema, 'bl_cloning_construct');
        $construct->addColumn('vector_id', Types::INTEGER);
        $construct->addColumn('insert_id', Types::INTEGER);
        $this->quarantineColumns($construct);
        $this->auditColumns($construct);
        $this->finish($construct);
        $this->reference($construct, 'vector_id', 'bl_cloning_vector');
        $this->reference($construct, 'insert_id', 'bl_cloning_insert');

        $link = $schema->createTable('bl_cloning_construct_product');
        $link->addColumn('construct_id', Types::INTEGER);
        $link->addColumn('product_id', Types::INTEGER);
        $link->setPrimaryKey(['construct_id', 'product_id']);
        $this->reference($link, 'construct_id', 'bl_cloning_construct', 'CASCADE');
        $this->reference($link, 'product_id', 'bl_target_product', 'CASCADE');

        $history = $schema->createTable('bl_field_history');
        $history->addColumn('id', Types::INTEGER, ['autoincrement' => true]);
        $history->addColumn('entity_type', Types::STRING, ['length' => 255]);
        $history->addColumn('entity_id', Types::INTEGER);
        $history->addColumn('field', Types::STRING, ['length' => 100]);
        $history->addColumn('old_value', Types::TEXT, ['length' => self::MEDIUM_TEXT, 'notnull' => false]);
        $history->addColumn('new_value', Types::TEXT, ['length' => self::MEDIUM_TEXT, 'notnull' => false]);
        $history->addColumn('changed_at', Types::DATETIME_IMMUTABLE);
        $history->addColumn('changed_by_id', Types::INTEGER, ['notnull' => false]);
        $history->setPrimaryKey(['id']);
        $history->addIndex(['entity_type', 'entity_id'], 'idx_bl_field_history_entity');
        $this->reference($history, 'changed_by_id', 'bl_user', 'SET NULL');
    }

    /**
     * Drops the cloning tables.
     *
     * @param Schema $schema Schema to modify
     * @return void
     */
    public function down(Schema $schema): void
    {
        foreach ([
            'bl_field_history',
            'bl_cloning_construct_product',
            'bl_cloning_construct',
            'bl_cloning_insert',
            'bl_cloning_pcr',
            'bl_cloning_attachment',
            'bl_cloning_vector_tag_capability',
            'bl_cloning_vector',
            'bl_cloning_oligo',
        ] as $table) {
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
     * Adds the quarantine columns (status, reason, raw value).
     *
     * @param Table $table Table being built
     * @return void
     */
    private function quarantineColumns(Table $table): void
    {
        $table->addColumn('quarantined', Types::BOOLEAN);
        $table->addColumn('quarantine_reason', Types::STRING, ['length' => 255, 'notnull' => false]);
        $table->addColumn('quarantine_raw_value', Types::TEXT, ['length' => self::MEDIUM_TEXT, 'notnull' => false]);
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
     * Adds the index and the foreign key of a many-to-one column, with the names Doctrine generates.
     *
     * @param Table $table Table being built
     * @param string $column Column holding the identifier of the target row
     * @param string $target Table that holds the target row
     * @param string|null $onDelete Referential action on delete, null for the default
     * @return void
     */
    private function reference(Table $table, string $column, string $target, ?string $onDelete = null): void
    {
        $name = $table->getName();
        $table->addIndex([$column], $this->identifier('IDX', $name, [$column]));
        $table->addForeignKeyConstraint($target, [$column], ['id'], null === $onDelete ? [] : ['onDelete' => $onDelete], $this->identifier('FK', $name, [$column]));
    }

    /**
     * Adds the audit columns, their indexes and their foreign keys to the user table.
     *
     * @param Table $table Table being built
     * @return void
     */
    private function auditColumns(Table $table): void
    {
        $table->addColumn('created_at', Types::DATETIME_IMMUTABLE, ['notnull' => false]);
        $table->addColumn('updated_at', Types::DATETIME_IMMUTABLE, ['notnull' => false]);
        $table->addColumn('created_by_id', Types::INTEGER, ['notnull' => false]);
        $table->addColumn('updated_by_id', Types::INTEGER, ['notnull' => false]);
        foreach (['created_by_id', 'updated_by_id'] as $column) {
            $this->reference($table, $column, 'bl_user', 'SET NULL');
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
