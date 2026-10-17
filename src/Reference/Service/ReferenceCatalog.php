<?php
/**
 * Listing and search of reference entries
 * Freely inspired by Plasmi::db 0.9.1 (Mark Brooks) and BioPHP's project biophp.org
 * Created 17 October 2026
 * Last modified 17 October 2026
 */
declare(strict_types=1);

namespace App\Reference\Service;

use App\Reference\Entity\AbstractReference;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @author Amélie DUVERNET aka Amelaye <amelieonline@gmail.com>
 */
final class ReferenceCatalog
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_ARCHIVED = 'archived';
    public const STATUS_ALL = 'all';

    /**
     * ReferenceCatalog constructor.
     *
     * @param EntityManagerInterface $em Entity manager used to query
     */
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    /**
     * Entries of a list filtered by status and by a name search, sorted by name.
     *
     * @param class-string<AbstractReference> $class Entity class of the list
     * @param string $query Text searched in the name (case and spacing ignored, % and _ are literal), empty for no filter
     * @param string $status One of ReferenceCatalog::STATUS_ACTIVE, STATUS_ARCHIVED or STATUS_ALL
     * @return list<AbstractReference> The matching entries
     */
    public function search(string $class, string $query, string $status): array
    {
        $qb = $this->em->createQueryBuilder()->select('e')->from($class, 'e')->orderBy('e.nameKey', 'ASC');

        if (self::STATUS_ACTIVE === $status) {
            $qb->andWhere('e.active = true');
        } elseif (self::STATUS_ARCHIVED === $status) {
            $qb->andWhere('e.active = false');
        }

        $key = ReferenceName::key($query);
        if ('' !== $key) {
            // "!" is the escape character: the user's % and _ are literal.
            $qb->andWhere("e.nameKey LIKE :q ESCAPE '!'")
                ->setParameter('q', '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $key).'%');
        }

        return $qb->getQuery()->getResult();
    }
}
