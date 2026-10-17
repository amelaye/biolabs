<?php
/**
 * Entity Product - region of a gene to produce
 * Freely inspired by Plasmi::db 0.9.1 (Mark Brooks) and BioPHP's project biophp.org
 * Created 17 October 2026
 * Last modified 17 October 2026
 */
declare(strict_types=1);

namespace App\Target\Entity;

use App\Reference\Entity\AbstractReference;
use App\Target\Enum\ProductType;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Product (legacy "product"): the region of the CDS of a gene that the laboratory wants to produce.
 *
 * The window is in nucleotides of the normalised CDS, 1-based, both ends included; empty
 * means the whole CDS. No sequence is stored: it is derived from the gene (one copy only).
 * Fusion tags and vectors belong to the constructs (LAB-3).
 *
 * @author Amélie DUVERNET aka Amelaye <amelieonline@gmail.com>
 */
#[ORM\Entity]
#[ORM\Table(name: 'bl_target_product')]
#[ORM\UniqueConstraint(name: 'uniq_bl_target_product_name_key', columns: ['name_key'])]
class Product extends AbstractReference
{
    #[ORM\ManyToOne(targetEntity: Gene::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull]
    private ?Gene $gene = null;

    #[ORM\Column(type: 'string', length: 20, enumType: ProductType::class)]
    #[Assert\NotNull]
    private ?ProductType $type = null;

    /** First nucleotide of the window on the CDS (1-based, included). */
    #[ORM\Column(type: 'integer', nullable: true)]
    #[Assert\Positive]
    private ?int $startNt = null;

    /** Last nucleotide of the window on the CDS (1-based, included). */
    #[ORM\Column(type: 'integer', nullable: true)]
    #[Assert\Positive]
    private ?int $endNt = null;

    /**
     * Gene whose CDS the product is taken from.
     *
     * @return Gene|null The gene
     */
    public function getGene(): ?Gene
    {
        return $this->gene;
    }

    /**
     * Sets the gene.
     *
     * @param Gene|null $gene Gene of the product
     * @return static This entity
     */
    public function setGene(?Gene $gene): static
    {
        $this->gene = $gene;

        return $this;
    }

    /**
     * Kind of molecule produced.
     *
     * @return ProductType|null Protein, RNA or DNA
     */
    public function getType(): ?ProductType
    {
        return $this->type;
    }

    /**
     * Sets the kind of molecule produced.
     *
     * @param ProductType|null $type Protein, RNA or DNA
     * @return static This entity
     */
    public function setType(?ProductType $type): static
    {
        $this->type = $type;

        return $this;
    }

    /**
     * First nucleotide of the window.
     *
     * @return int|null Position on the CDS (1-based, included), null for the whole CDS
     */
    public function getStartNt(): ?int
    {
        return $this->startNt;
    }

    /**
     * Sets the first nucleotide of the window.
     *
     * @param int|null $startNt Position on the CDS (1-based, included), null for the whole CDS
     * @return static This entity
     */
    public function setStartNt(?int $startNt): static
    {
        $this->startNt = $startNt;

        return $this;
    }

    /**
     * Last nucleotide of the window.
     *
     * @return int|null Position on the CDS (1-based, included), null for the whole CDS
     */
    public function getEndNt(): ?int
    {
        return $this->endNt;
    }

    /**
     * Sets the last nucleotide of the window.
     *
     * @param int|null $endNt Position on the CDS (1-based, included), null for the whole CDS
     * @return static This entity
     */
    public function setEndNt(?int $endNt): static
    {
        $this->endNt = $endNt;

        return $this;
    }

    /**
     * Effective window on the CDS: the whole CDS when no bound is set.
     *
     * @return array{int, int}|null 1-based inclusive [start, end], null when the gene is unknown or has no CDS
     */
    public function window(): ?array
    {
        $length = $this->gene?->getCdsLength() ?? 0;
        if (0 === $length) {
            return null;
        }

        return [$this->startNt ?? 1, $this->endNt ?? $length];
    }

    /**
     * Blocking rules of the window: both bounds or none, start before end, end inside the CDS and,
     * for a protein, on codon boundaries.
     *
     * @param ExecutionContextInterface $context Validation context that receives the violations
     * @return void
     */
    #[Assert\Callback]
    public function validateWindow(ExecutionContextInterface $context): void
    {
        if (null === $this->gene) {
            return;
        }
        if ((null === $this->startNt) !== (null === $this->endNt)) {
            $context->buildViolation('product.window.both_or_none')->atPath('endNt')->addViolation();

            return;
        }
        if (null === $this->startNt || $this->startNt < 1 || $this->endNt < 1) {
            return; // Positive reports it
        }
        if ($this->startNt > $this->endNt) {
            $context->buildViolation('product.window.start_after_end')->atPath('startNt')->addViolation();

            return;
        }
        $cdsLength = $this->gene->getCdsLength();
        if ($this->endNt > $cdsLength) {
            $context->buildViolation('product.window.beyond_cds')
                ->setParameter('{{ length }}', (string) $cdsLength)
                ->atPath('endNt')
                ->addViolation();

            return;
        }
        if (ProductType::Protein === $this->type && (0 !== ($this->startNt - 1) % 3 || 0 !== ($this->endNt - $this->startNt + 1) % 3)) {
            $context->buildViolation('product.window.out_of_frame')->atPath('startNt')->addViolation();
        }
    }

    /**
     * Name, gene, type and window: changing them alters the meaning of what is built on the product.
     *
     * @return array<string, scalar|null> Value by field name
     */
    public function significantValues(): array
    {
        return parent::significantValues() + ['gene' => $this->gene?->getId(), 'start' => $this->startNt, 'end' => $this->endNt, 'type' => $this->type?->value];
    }
}
