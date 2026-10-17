<?php
/**
 * Entity Insert - DNA fragment to clone
 * Freely inspired by Plasmi::db 0.9.1 (Mark Brooks) and BioPHP's project biophp.org
 * Created 17 October 2026
 * Last modified 18 October 2026
 */
declare(strict_types=1);

namespace App\Cloning\Entity;

use App\Cloning\Enum\InsertType;
use App\Cloning\Validator\InsertDistinctSites;
use App\Cloning\Validator\KnownRestrictionEnzyme;
use App\Reference\Entity\AbstractReference;
use App\Target\Entity\Product;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * DNA fragment to clone: from a PCR or cut out of a donor. Its sites are names of the biophp
 * restriction enzyme catalogue (checked, never typed freely). The LAB-2 product is only
 * referenced: the insert holds no sequence, so there is one copy of every sequence.
 *
 * @author Amélie DUVERNET aka Amelaye <amelieonline@gmail.com>
 */
#[ORM\Entity]
#[ORM\Table(name: 'bl_cloning_insert')]
#[ORM\UniqueConstraint(name: 'uniq_bl_cloning_insert_name_key', columns: ['name_key'])]
#[InsertDistinctSites]
class Insert extends AbstractReference implements QuarantinableInterface
{
    use QuarantinableTrait;

    #[ORM\Column(type: 'string', length: 30, enumType: InsertType::class)]
    #[Assert\NotNull]
    private ?InsertType $type = null;

    /** Enzyme of the 5' end of the insert (catalogue name). */
    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    #[KnownRestrictionEnzyme]
    private ?string $fivePrimeSite = null;

    /** Enzyme of the 3' end of the insert (catalogue name). */
    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    #[KnownRestrictionEnzyme]
    private ?string $threePrimeSite = null;

    /** True when the two ends must be cut by two different enzymes (directional cloning). */
    #[ORM\Column(type: 'boolean')]
    private bool $directional = true;

    #[ORM\ManyToOne(targetEntity: Pcr::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Pcr $pcr = null;

    #[ORM\ManyToOne(targetEntity: Product::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Product $product = null;

    /**
     * Origin of the fragment.
     *
     * @return InsertType|null PCR fragment or restriction fragment
     */
    public function getType(): ?InsertType
    {
        return $this->type;
    }

    /**
     * Sets the origin of the fragment.
     *
     * @param InsertType|null $type PCR fragment or restriction fragment
     * @return static This entity
     */
    public function setType(?InsertType $type): static
    {
        $this->type = $type;

        return $this;
    }

    /**
     * Enzyme of the 5' end.
     *
     * @return string|null The enzyme name, null if none
     */
    public function getFivePrimeSite(): ?string
    {
        return $this->fivePrimeSite;
    }

    /**
     * Sets the enzyme of the 5' end; a blank text is stored as null.
     *
     * @param string|null $enzyme Enzyme name as typed
     * @return static This entity
     */
    public function setFivePrimeSite(?string $enzyme): static
    {
        $this->fivePrimeSite = self::blankToNull($enzyme);

        return $this;
    }

    /**
     * Enzyme of the 3' end.
     *
     * @return string|null The enzyme name, null if none
     */
    public function getThreePrimeSite(): ?string
    {
        return $this->threePrimeSite;
    }

    /**
     * Sets the enzyme of the 3' end; a blank text is stored as null.
     *
     * @param string|null $enzyme Enzyme name as typed
     * @return static This entity
     */
    public function setThreePrimeSite(?string $enzyme): static
    {
        $this->threePrimeSite = self::blankToNull($enzyme);

        return $this;
    }

    /**
     * Tells whether the cloning is directional (two different enzymes expected).
     *
     * @return bool True if directional
     */
    public function isDirectional(): bool
    {
        return $this->directional;
    }

    /**
     * Sets whether the cloning is directional.
     *
     * @param bool $directional True for two different enzymes
     * @return static This entity
     */
    public function setDirectional(bool $directional): static
    {
        $this->directional = $directional;

        return $this;
    }

    /**
     * PCR that produced the fragment.
     *
     * @return Pcr|null The PCR, null if none
     */
    public function getPcr(): ?Pcr
    {
        return $this->pcr;
    }

    /**
     * Sets the PCR that produced the fragment.
     *
     * @param Pcr|null $pcr PCR, null if none
     * @return static This entity
     */
    public function setPcr(?Pcr $pcr): static
    {
        $this->pcr = $pcr;

        return $this;
    }

    /**
     * Product of the targets this insert is meant to carry.
     *
     * @return Product|null The product, null if not provided
     */
    public function getProduct(): ?Product
    {
        return $this->product;
    }

    /**
     * Sets the product this insert is meant to carry.
     *
     * @param Product|null $product Product, null if not provided
     * @return static This entity
     */
    public function setProduct(?Product $product): static
    {
        $this->product = $product;

        return $this;
    }

    /**
     * Non-empty sites of the insert, in 5'->3' order, the same name typed twice (any case) counted once.
     * Isoschizomers under different names are NOT merged here (the entity has no catalogue):
     * use CloningChecks::distinctSites() to compare enzymes by canonical name.
     *
     * @return list<string> Enzyme names as stored
     */
    public function sites(): array
    {
        $sites = [];
        foreach ([$this->fivePrimeSite, $this->threePrimeSite] as $enzyme) {
            if (null !== $enzyme && !\in_array(strtolower($enzyme), array_map('strtolower', $sites), true)) {
                $sites[] = $enzyme;
            }
        }

        return $sites;
    }

    /**
     * Blocking rules: a PCR fragment names its PCR, no parent in quarantine (the two-different-enzymes rule is {@see InsertDistinctSites}).
     *
     * @param ExecutionContextInterface $context Validation context that receives the violations
     * @return void
     */
    #[Assert\Callback]
    public function validateInsert(ExecutionContextInterface $context): void
    {
        if (InsertType::PcrFragment === $this->type && null === $this->pcr) {
            $context->buildViolation('insert.pcr.required')->atPath('pcr')->addViolation();
        }
        if ($this->pcr?->isQuarantined()) {
            $context->buildViolation('cloning.parent.quarantined')->atPath('pcr')->addViolation();
        }
    }

    /**
     * Name, type, sites, PCR and product: changing them alters the meaning of the constructs built on the insert.
     *
     * @return array<string, scalar|null> Value by field name
     */
    public function significantValues(): array
    {
        return parent::significantValues() + [
            'type' => $this->type?->value,
            'five' => $this->fivePrimeSite,
            'three' => $this->threePrimeSite,
            'pcr' => $this->pcr?->getId(),
            'product' => $this->product?->getId(),
        ];
    }

    /**
     * Trims a text and turns an empty one into null.
     *
     * @param string|null $text Text as typed
     * @return string|null The trimmed text, null if empty
     */
    private static function blankToNull(?string $text): ?string
    {
        $text = null === $text ? null : trim($text);

        return '' === $text ? null : $text;
    }
}
