<?php
/**
 * Registry of the reference lists managed by the generic screens
 * Freely inspired by Plasmi::db 0.9.1 (Mark Brooks) and BioPHP's project biophp.org
 * Created 17 October 2026
 * Last modified 17 October 2026
 */
declare(strict_types=1);

namespace App\Reference\Service;

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

/**
 * Maps the URL slug of each reference list to its entity and form.
 *
 * @author Amélie DUVERNET aka Amelaye <amelieonline@gmail.com>
 */
final class ReferenceRegistry
{
    /** Slugs as used in the URLs; keep in sync with the route requirement of the controller. */
    public const SLUG_PATTERN = 'organisms|strains|markers|promoters|origins|tags|suppliers';

    private const TYPES = [
        'organisms' => [Organism::class, OrganismType::class],
        'strains' => [Strain::class, StrainType::class],
        'markers' => [SelectionMarker::class, SelectionMarkerType::class],
        'promoters' => [Promoter::class, PlainReferenceType::class],
        'origins' => [ReplicationOrigin::class, PlainReferenceType::class],
        'tags' => [Tag::class, TagType::class],
        'suppliers' => [Supplier::class, SupplierType::class],
    ];

    /**
     * @return list<string>
     */
    public function slugs(): array
    {
        return array_keys(self::TYPES);
    }

    /**
     * @return class-string<AbstractReference>
     */
    public function entityClass(string $slug): string
    {
        return $this->definition($slug)[0];
    }

    /**
     * @return class-string
     */
    public function formType(string $slug): string
    {
        return $this->definition($slug)[1];
    }

    public function newEntity(string $slug): AbstractReference
    {
        $class = $this->entityClass($slug);

        return new $class();
    }

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
     * @return array{class-string<AbstractReference>, class-string}
     */
    private function definition(string $slug): array
    {
        return self::TYPES[$slug] ?? throw new \InvalidArgumentException(sprintf('Unknown reference list "%s".', $slug));
    }
}
