<?php
/**
 * Entity Gene - coding sequence of an organism
 * Freely inspired by Plasmi::db 0.9.1 (Mark Brooks) and BioPHP's project biophp.org
 * Created 17 October 2026
 * Last modified 17 October 2026
 */
declare(strict_types=1);

namespace App\Target\Entity;

use Amelaye\BioPHP\Domain\Sequence\Exception\InvalidSequenceException;
use Amelaye\BioPHP\Domain\Sequence\ValueObject\DnaSequence;
use App\Reference\Entity\AbstractReference;
use App\Reference\Entity\Organism;
use App\Target\Enum\SourceDatabase;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Gene (legacy "subunit"): the CDS of an organism, in the 5'->3' sense orientation.
 *
 * The CDS is kept as entered (raw) and normalised (whitespace removed, uppercase) by biophp's
 * {@see DnaSequence}. Biological checks that depend on the genetic code are warnings
 * (see GeneChecks); the alphabet and the reading frame are blocking.
 *
 * @author Amélie DUVERNET aka Amelaye <amelieonline@gmail.com>
 */
#[ORM\Entity]
#[ORM\Table(name: 'bl_target_gene')]
#[ORM\UniqueConstraint(name: 'uniq_bl_target_gene_name_key', columns: ['name_key'])]
class Gene extends AbstractReference
{
    #[ORM\ManyToOne(targetEntity: Organism::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull]
    private ?Organism $organism = null;

    /** Name of the product of the CDS (e.g. "VMA4"), the legacy subunit_cds_product_name. */
    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    #[Assert\Length(max: 50)]
    private ?string $productName = null;

    #[ORM\Column(type: 'string', length: 20, nullable: true, enumType: SourceDatabase::class)]
    private ?SourceDatabase $sourceDatabase = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Assert\Length(max: 1000)]
    private ?string $sourceDefinition = null;

    /** CDS exactly as entered. */
    #[ORM\Column(type: 'text')]
    #[Assert\Length(max: 300000)]
    private string $cdsSequenceRaw = '';

    /** CDS without whitespace, uppercase. */
    #[ORM\Column(type: 'text')]
    private string $cdsSequence = '';

    /**
     * Organism the CDS comes from.
     *
     * @return Organism|null The organism
     */
    public function getOrganism(): ?Organism
    {
        return $this->organism;
    }

    /**
     * Sets the organism, whose genetic code translates the CDS.
     *
     * @param Organism|null $organism Organism of the gene
     * @return static This entity
     */
    public function setOrganism(?Organism $organism): static
    {
        $this->organism = $organism;

        return $this;
    }

    /**
     * Name of the product of the CDS (e.g. VMA4).
     *
     * @return string|null The name, null if not provided
     */
    public function getProductName(): ?string
    {
        return $this->productName;
    }

    /**
     * Sets the name of the product of the CDS; an empty text is stored as null.
     *
     * @param string|null $productName Name as typed
     * @return static This entity
     */
    public function setProductName(?string $productName): static
    {
        $productName = null === $productName ? null : trim($productName);
        $this->productName = '' === $productName ? null : $productName;

        return $this;
    }

    /**
     * Database the sequence was taken from.
     *
     * @return SourceDatabase|null The database, null if not provided
     */
    public function getSourceDatabase(): ?SourceDatabase
    {
        return $this->sourceDatabase;
    }

    /**
     * Sets the source database.
     *
     * @param SourceDatabase|null $sourceDatabase Database, null if not provided
     * @return static This entity
     */
    public function setSourceDatabase(?SourceDatabase $sourceDatabase): static
    {
        $this->sourceDatabase = $sourceDatabase;

        return $this;
    }

    /**
     * Definition of the sequence in the source database.
     *
     * @return string|null The definition, null if not provided
     */
    public function getSourceDefinition(): ?string
    {
        return $this->sourceDefinition;
    }

    /**
     * Sets the definition in the source database; an empty text is stored as null.
     *
     * @param string|null $sourceDefinition Definition as typed
     * @return static This entity
     */
    public function setSourceDefinition(?string $sourceDefinition): static
    {
        $sourceDefinition = null === $sourceDefinition ? null : trim($sourceDefinition);
        $this->sourceDefinition = '' === $sourceDefinition ? null : $sourceDefinition;

        return $this;
    }

    /**
     * The CDS as entered (form property).
     *
     * @return string The raw text
     */
    public function getCds(): string
    {
        return $this->cdsSequenceRaw;
    }

    /**
     * The CDS exactly as entered.
     *
     * @return string The raw text, whitespace and case included
     */
    public function getCdsSequenceRaw(): string
    {
        return $this->cdsSequenceRaw;
    }

    /**
     * The CDS normalised by biophp (whitespace removed, uppercase).
     *
     * @return string The normalised sequence, empty if none
     */
    public function getCdsSequence(): string
    {
        return $this->cdsSequence;
    }

    /**
     * Length of the normalised CDS.
     *
     * @return int Number of nucleotides
     */
    public function getCdsLength(): int
    {
        return \strlen($this->cdsSequence);
    }

    /**
     * Sets the CDS from what the person typed: the raw text is kept and the normalised form is biophp's
     * DnaSequence. A text that is not a valid sequence keeps its cleaned-up form so that the validation can report it.
     *
     * @param string|null $raw CDS as typed
     * @return static This gene
     */
    public function setCds(?string $raw): static
    {
        $this->cdsSequenceRaw = $raw ?? '';
        try {
            $this->cdsSequence = (new DnaSequence($this->cdsSequenceRaw))->getValue();
        } catch (InvalidSequenceException) {
            $this->cdsSequence = strtoupper((string) preg_replace('/\s+/', '', $this->cdsSequenceRaw));
        }

        return $this;
    }

    /**
     * Blocking rules of the CDS: not empty, IUPAC nucleotide symbols only, length a multiple of 3.
     *
     * @param ExecutionContextInterface $context Validation context that receives the violations
     * @return void
     */
    #[Assert\Callback]
    public function validateCds(ExecutionContextInterface $context): void
    {
        if ('' === $this->cdsSequence) {
            $context->buildViolation('gene.cds.empty')->atPath('cds')->addViolation();

            return;
        }
        if (!DnaSequence::isValid($this->cdsSequence)) {
            $context->buildViolation('gene.cds.invalid_symbol')->atPath('cds')->addViolation();

            return;
        }
        if (0 !== $this->getCdsLength() % 3) {
            $context->buildViolation('gene.cds.not_in_frame')
                ->setParameter('{{ length }}', (string) $this->getCdsLength())
                ->atPath('cds')
                ->addViolation();
        }
    }

    /**
     * Name, CDS and organism: changing them alters the meaning of the products built on the gene.
     *
     * @return array<string, scalar|null> Value by field name
     */
    public function significantValues(): array
    {
        return parent::significantValues() + ['cds' => $this->cdsSequence, 'organism' => $this->organism?->getId()];
    }
}
