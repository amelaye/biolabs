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
     * Names of the selectable tables, by table number (NCBI wording, from biophp).
     *
     * @param list<int> $codes
     *
     * @return array<int, string> NCBI wording by table number
     */
    public static function geneticCodeNames(array $codes): array
    {
        $names = [];
        foreach ($codes as $code) {
            $names[$code] = (string) preg_replace('/\s+/', ' ', GeneticCodeTable::NAMES[$code] ?? '');
        }

        return $names;
    }

    public function getTaxId(): ?int
    {
        return $this->taxId;
    }

    public function setTaxId(?int $taxId): static
    {
        $this->taxId = $taxId;

        return $this;
    }

    public function getGeneticCode(): ?int
    {
        return $this->geneticCode;
    }

    public function setGeneticCode(?int $geneticCode): static
    {
        $this->geneticCode = $geneticCode;

        return $this;
    }

    public function getMitochondrialGeneticCode(): ?int
    {
        return $this->mitochondrialGeneticCode;
    }

    public function setMitochondrialGeneticCode(?int $mitochondrialGeneticCode): static
    {
        $this->mitochondrialGeneticCode = $mitochondrialGeneticCode;

        return $this;
    }
}
