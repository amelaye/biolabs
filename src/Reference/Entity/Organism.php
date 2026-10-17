<?php
/**
 * Entity Organism - species or sequenced organism
 * Freely inspired by Plasmi::db 0.9.1 (Mark Brooks) and BioPHP's project biophp.org
 * Created 17 October 2026
 * Last modified 17 October 2026
 */
declare(strict_types=1);

namespace App\Reference\Entity;

use Amelaye\BioPHP\Domain\Tools\ValueObject\GeneticCodeTable;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Organism a gene comes from, with its NCBI genetic code.
 *
 * The NCBI taxid is the identifier of the organism as the laboratory works with it
 * (it may be a strain-level taxid). Genetic code numbers follow the NCBI list, which has
 * no table 0, 7, 8 or 17-20; the names come from biophp's GeneticCodeTable: https://www.ncbi.nlm.nih.gov/Taxonomy/Utils/wprintgc.cgi
 *
 * @author Amélie DUVERNET aka Amelaye <amelieonline@gmail.com>
 */
#[ORM\Entity]
#[ORM\Table(name: 'bl_organism')]
#[ORM\UniqueConstraint(name: 'uniq_bl_organism_name_key', columns: ['name_key'])]
class Organism extends AbstractReference
{
    /**
     * NCBI translation tables that biophp can translate with: the list is biophp's own, so an
     * organism can never be given a table the library would refuse (27, 28 and 31 are NCBI tables
     * with context-dependent stop codons, which biophp does not support).
     */
    public const GENETIC_CODES = GeneticCodeTable::SUPPORTED_TABLES;

    /** NCBI translation tables used by mitochondria. */
    public const MITOCHONDRIAL_GENETIC_CODES = [2, 3, 4, 5, 9, 13, 14, 16, 21, 22, 23, 24, 33];

    #[ORM\Column(type: 'integer', nullable: true)]
    #[Assert\Positive]
    private ?int $taxId = null;

    #[ORM\Column(type: 'integer')]
    #[Assert\NotNull]
    #[Assert\Choice(choices: self::GENETIC_CODES, message: 'organism.genetic_code.invalid')]
    private ?int $geneticCode = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    #[Assert\Choice(choices: self::MITOCHONDRIAL_GENETIC_CODES, message: 'organism.mitochondrial_code.invalid')]
    private ?int $mitochondrialGeneticCode = null;

    /**
     * Names of the selectable genetic code tables, by table number (NCBI wording, from biophp).
     *
     * @param list<int> $codes NCBI table numbers
     * @return array<int, string> NCBI name by table number
     */
    public static function geneticCodeNames(array $codes): array
    {
        $names = [];
        foreach ($codes as $code) {
            $names[$code] = (string) preg_replace('/\s+/', ' ', GeneticCodeTable::NAMES[$code] ?? '');
        }

        return $names;
    }

    /**
     * NCBI taxonomy identifier.
     *
     * @return int|null The taxid, null if not provided
     */
    public function getTaxId(): ?int
    {
        return $this->taxId;
    }

    /**
     * Sets the NCBI taxonomy identifier.
     *
     * @param int|null $taxId Taxid (positive), null if unknown
     * @return static This entity
     */
    public function setTaxId(?int $taxId): static
    {
        $this->taxId = $taxId;

        return $this;
    }

    /**
     * NCBI genetic code table used to translate the genes of this organism.
     *
     * @return int|null The table number
     */
    public function getGeneticCode(): ?int
    {
        return $this->geneticCode;
    }

    /**
     * Sets the NCBI genetic code table.
     *
     * @param int|null $geneticCode Table number among {@see Organism::GENETIC_CODES}
     * @return static This entity
     */
    public function setGeneticCode(?int $geneticCode): static
    {
        $this->geneticCode = $geneticCode;

        return $this;
    }

    /**
     * NCBI genetic code table of the mitochondrial genes.
     *
     * @return int|null The table number, null if not provided
     */
    public function getMitochondrialGeneticCode(): ?int
    {
        return $this->mitochondrialGeneticCode;
    }

    /**
     * Sets the NCBI mitochondrial genetic code table.
     *
     * @param int|null $mitochondrialGeneticCode Table number among {@see Organism::MITOCHONDRIAL_GENETIC_CODES}, null if not provided
     * @return static This entity
     */
    public function setMitochondrialGeneticCode(?int $mitochondrialGeneticCode): static
    {
        $this->mitochondrialGeneticCode = $mitochondrialGeneticCode;

        return $this;
    }
}
