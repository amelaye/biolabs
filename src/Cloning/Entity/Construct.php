<?php
/**
 * Entity Construct - vector plus insert, as designed
 * Freely inspired by Plasmi::db 0.9.1 (Mark Brooks) and BioPHP's project biophp.org
 * Created 17 October 2026
 * Last modified 18 October 2026
 */
declare(strict_types=1);

namespace App\Cloning\Entity;

use App\Cloning\Enum\ConstructStatus;
use App\Cloning\Validator\ConstructSites;
use App\Reference\Entity\AbstractReference;
use App\Shared\Audit\TracksFieldHistoryInterface;
use App\Target\Entity\Product;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * A construct: one vector, one insert and the products it is meant to express. Its sequence is
 * neither typed nor computed in this version. Its status ({@see ConstructStatus}) is NOT stored:
 * it is derived each time it is displayed from the current vector and insert (see CloningChecks::status()),
 * so that it can never be stale. It does not say the construct is biologically valid.
 *
 * @author Amélie DUVERNET aka Amelaye <amelieonline@gmail.com>
 */
#[ORM\Entity]
#[ORM\Table(name: 'bl_cloning_construct')]
#[ORM\UniqueConstraint(name: 'uniq_bl_cloning_construct_name_key', columns: ['name_key'])]
#[ConstructSites]
class Construct extends AbstractReference implements TracksFieldHistoryInterface, QuarantinableInterface
{
    use QuarantinableTrait;

    #[ORM\ManyToOne(targetEntity: Vector::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Vector $vector = null;

    #[ORM\ManyToOne(targetEntity: Insert::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Insert $insert = null;

    /** @var Collection<int, Product> */
    #[ORM\ManyToMany(targetEntity: Product::class)]
    #[ORM\JoinTable(name: 'bl_cloning_construct_product')]
    private Collection $products;

    /**
     * Construct constructor: starts without product.
     */
    public function __construct()
    {
        $this->products = new ArrayCollection();
    }

    /**
     * Vector of the construct.
     *
     * @return Vector|null The vector
     */
    public function getVector(): ?Vector
    {
        return $this->vector;
    }

    /**
     * Sets the vector.
     *
     * @param Vector|null $vector Vector of the construct
     * @return static This entity
     */
    public function setVector(?Vector $vector): static
    {
        $this->vector = $vector;

        return $this;
    }

    /**
     * Insert of the construct.
     *
     * @return Insert|null The insert
     */
    public function getInsert(): ?Insert
    {
        return $this->insert;
    }

    /**
     * Sets the insert.
     *
     * @param Insert|null $insert Insert of the construct
     * @return static This entity
     */
    public function setInsert(?Insert $insert): static
    {
        $this->insert = $insert;

        return $this;
    }

    /**
     * Products the construct is meant to express.
     *
     * @return Collection<int, Product> The products
     */
    public function getProducts(): Collection
    {
        return $this->products;
    }

    /**
     * Adds a product (ignored if already present).
     *
     * @param Product $product Product to add
     * @return static This entity
     */
    public function addProduct(Product $product): static
    {
        if (!$this->products->contains($product)) {
            $this->products->add($product);
        }

        return $this;
    }

    /**
     * Removes a product.
     *
     * @param Product $product Product to remove
     * @return static This entity
     */
    public function removeProduct(Product $product): static
    {
        $this->products->removeElement($product);

        return $this;
    }

    /**
     * Blocking rules: a vector, an insert, and no parent in quarantine.
     *
     * @param ExecutionContextInterface $context Validation context that receives the violations
     * @return void
     */
    #[Assert\Callback]
    public function validateParents(ExecutionContextInterface $context): void
    {
        if (null === $this->vector) {
            $context->buildViolation('construct.vector.required')->atPath('vector')->addViolation();
        }
        if (null === $this->insert) {
            $context->buildViolation('construct.insert.required')->atPath('insert')->addViolation();
        }
        foreach (['vector' => $this->vector, 'insert' => $this->insert] as $path => $parent) {
            if ($parent?->isQuarantined()) {
                $context->buildViolation('cloning.parent.quarantined')->atPath($path)->addViolation();
            }
        }
    }

    /**
     * Fields whose old and new values are kept in the history.
     *
     * @return list<string> The vector and the insert
     */
    public function trackedFields(): array
    {
        return ['vector', 'insert'];
    }

    /**
     * Name, vector and insert: changing them alters the meaning of what is made from the construct.
     *
     * @return array<string, scalar|null> Value by field name
     */
    public function significantValues(): array
    {
        return parent::significantValues() + ['vector' => $this->vector?->getId(), 'insert' => $this->insert?->getId()];
    }
}
