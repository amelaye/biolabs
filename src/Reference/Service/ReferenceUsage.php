<?php
/**
 * Number of objects that use a reference entry
 * Freely inspired by Plasmi::db 0.9.1 (Mark Brooks) and BioPHP's project biophp.org
 * Created 17 October 2026
 * Last modified 17 October 2026
 */
declare(strict_types=1);

namespace App\Reference\Service;

use App\Reference\Entity\AbstractReference;
use App\Reference\Entity\SelectionMarker;
use App\Reference\Entity\Strain;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Usage counter shown next to each entry. Only the links that exist today are counted
 * (a marker used as a strain resistance); the lots that create vectors, clones or primers
 * add their own counts here.
 *
 * @author Amélie DUVERNET aka Amelaye <amelieonline@gmail.com>
 */
final class ReferenceUsage
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function count(AbstractReference $entry): int
    {
        if (!$entry instanceof SelectionMarker || null === $entry->getId()) {
            return 0;
        }

        return (int) $this->em->createQuery(
            'SELECT COUNT(s.id) FROM '.Strain::class.' s JOIN s.resistances m WHERE m = :marker',
        )->setParameter('marker', $entry)->getSingleScalarResult();
    }
}
