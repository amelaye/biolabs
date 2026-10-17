<?php
/**
 * Registry of the reference lists managed by the generic screens
 * Freely inspired by Plasmi::db 0.9.1 (Mark Brooks) and BioPHP's project biophp.org
 * Created 17 October 2026
 * Last modified 17 October 2026
 */
declare(strict_types=1);

namespace App\Reference\Service;

use App\Cloning\Entity\Construct;
use App\Cloning\Entity\Insert;
use App\Cloning\Entity\Oligo;
use App\Cloning\Entity\Pcr;
use App\Cloning\Entity\Vector;
use App\Cloning\Form\ConstructFormType;
use App\Cloning\Form\InsertFormType;
use App\Cloning\Form\OligoFormType;
use App\Cloning\Form\PcrFormType;
use App\Cloning\Form\VectorFormType;
use App\Reference\Entity\AbstractReference;
use App\Reference\Entity\Organism;
use App\Reference\Entity\Promoter;
use App\Reference\Entity\ReplicationOrigin;
use App\Reference\Entity\SelectionMarker;
use App\Reference\Entity\Strain;
use App\Reference\Entity\Supplier;
use App\Reference\Entity\Tag;
use App\Reference\Form\OrganismType;
use App\Reference\Form\PlainReferenceType;
use App\Reference\Form\SelectionMarkerType;
use App\Reference\Form\StrainType;
use App\Reference\Form\SupplierType;
use App\Reference\Form\TagType;
use App\Target\Entity\Complex;
use App\Target\Entity\Gene;
use App\Target\Entity\Product;
use App\Target\Form\ComplexFormType;
use App\Target\Form\GeneFormType;
use App\Target\Form\ProductFormType;

/**
 * Maps the URL slug of each reference list to its entity and form.
 *
 * @author Amélie DUVERNET aka Amelaye <amelieonline@gmail.com>
 */
final class ReferenceRegistry
{
    public const GROUP_REFERENCE = 'reference';
    public const GROUP_TARGET = 'target';
    public const GROUP_CLONING = 'cloning';

    /** Slugs as used in the URLs; keep in sync with the route requirement of the controllers. */
    public const SLUG_PATTERN = 'organisms|strains|markers|promoters|origins|tags|suppliers';
    public const TARGET_SLUG_PATTERN = 'complexes|genes|products';
    public const CLONING_SLUG_PATTERN = 'oligos|vectors|pcrs|inserts|constructs';

    private const TYPES = [
        'organisms' => [Organism::class, OrganismType::class],
        'strains' => [Strain::class, StrainType::class],
        'markers' => [SelectionMarker::class, SelectionMarkerType::class],
        'promoters' => [Promoter::class, PlainReferenceType::class],
        'origins' => [ReplicationOrigin::class, PlainReferenceType::class],
        'tags' => [Tag::class, TagType::class],
        'suppliers' => [Supplier::class, SupplierType::class],
        'complexes' => [Complex::class, ComplexFormType::class],
        'genes' => [Gene::class, GeneFormType::class],
        'products' => [Product::class, ProductFormType::class],
        'oligos' => [Oligo::class, OligoFormType::class],
        'vectors' => [Vector::class, VectorFormType::class],
        'pcrs' => [Pcr::class, PcrFormType::class],
        'inserts' => [Insert::class, InsertFormType::class],
        'constructs' => [Construct::class, ConstructFormType::class],
    ];

    private const TARGET_SLUGS = ['complexes', 'genes', 'products'];
    private const CLONING_SLUGS = ['oligos', 'vectors', 'pcrs', 'inserts', 'constructs'];

    /**
     * Slugs of one group of lists (the tabs of a menu entry).
     *
     * @param string $group ReferenceRegistry::GROUP_REFERENCE, GROUP_TARGET or GROUP_CLONING
     * @return list<string> The URL slugs, in tab order
     */
    public function slugs(string $group = self::GROUP_REFERENCE): array
    {
        return array_values(array_filter(
            array_keys(self::TYPES),
            fn (string $slug): bool => $group === $this->groupOf($slug),
        ));
    }

    /**
     * Group a list belongs to.
     *
     * @param string $slug URL slug of the list
     * @return string ReferenceRegistry::GROUP_REFERENCE, GROUP_TARGET or GROUP_CLONING
     */
    public function groupOf(string $slug): string
    {
        return match (true) {
            \in_array($slug, self::TARGET_SLUGS, true) => self::GROUP_TARGET,
            \in_array($slug, self::CLONING_SLUGS, true) => self::GROUP_CLONING,
            default => self::GROUP_REFERENCE,
        };
    }

    /**
     * Entity class of a list.
     *
     * @param string $slug URL slug of the list
     * @return class-string<AbstractReference> The entity class
     * @throws \InvalidArgumentException If the slug is unknown
     */
    public function entityClass(string $slug): string
    {
        return $this->definition($slug)[0];
    }

    /**
     * Form type used to create and edit the entries of a list.
     *
     * @param string $slug URL slug of the list
     * @return class-string The form type class
     * @throws \InvalidArgumentException If the slug is unknown
     */
    public function formType(string $slug): string
    {
        return $this->definition($slug)[1];
    }

    /**
     * Creates an empty entry of a list.
     *
     * @param string $slug URL slug of the list
     * @return AbstractReference A new, not persisted entry
     * @throws \InvalidArgumentException If the slug is unknown
     */
    public function newEntity(string $slug): AbstractReference
    {
        $class = $this->entityClass($slug);

        return new $class();
    }

    /**
     * URL slug of the list an entry belongs to.
     *
     * @param AbstractReference $entity Entry of a registered list
     * @return string The slug
     * @throws \LogicException If the entry class is not registered
     */
    public function slugOf(AbstractReference $entity): string
    {
        foreach (self::TYPES as $slug => [$class]) {
            if ($entity instanceof $class) {
                return $slug;
            }
        }

        throw new \LogicException(sprintf('Unregistered reference class "%s".', $entity::class));
    }

    /**
     * Entity and form classes of a list.
     *
     * @param string $slug URL slug of the list
     * @return array{class-string<AbstractReference>, class-string} Entity class and form type class
     * @throws \InvalidArgumentException If the slug is unknown
     */
    private function definition(string $slug): array
    {
        return self::TYPES[$slug] ?? throw new \InvalidArgumentException(sprintf('Unknown reference list "%s".', $slug));
    }
}
