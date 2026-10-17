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
use App\Reference\Entity\Organism;
use App\Reference\Entity\SelectionMarker;
use App\Reference\Entity\Strain;
use App\Target\Entity\Complex;
use App\Target\Entity\Gene;
use App\Target\Entity\Product;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Usage counter shown next to each entry. Only the links that exist today are counted
 * (marker -> strains, organism -> genes, gene -> products and complexes); the lots that
 * create vectors, clones or primers add their own counts here.
 *
 * @author Amélie DUVERNET aka Amelaye <amelieonline@gmail.com>
 */
final class ReferenceUsage
{
    /**
     * ReferenceUsage constructor.
     *
     * @param EntityManagerInterface $em Entity manager used to count
     */
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    /**
     * Number of objects that use an entry.
     *
     * @param AbstractReference $entry Entry of a reference or target list
     * @return int The count, 0 for an entry that is not stored or not used
     */
    public function count(AbstractReference $entry): int
    {
        if (null === $entry->getId()) {
            return 0;
        }

        return match (true) {
            $entry instanceof SelectionMarker => $this->scalar('SELECT COUNT(s.id) FROM '.Strain::class.' s JOIN s.resistances m WHERE m = :e', $entry),
            $entry instanceof Organism => $this->scalar('SELECT COUNT(g.id) FROM '.Gene::class.' g WHERE g.organism = :e', $entry),
            $entry instanceof Gene => $this->scalar('SELECT COUNT(p.id) FROM '.Product::class.' p WHERE p.gene = :e', $entry)
                + $this->scalar('SELECT COUNT(c.id) FROM '.Complex::class.' c JOIN c.genes g WHERE g = :e', $entry),
            default => 0,
        };
    }

    /**
     * Runs a COUNT query on one entry.
     *
     * @param string $dql DQL query with an :e parameter
     * @param AbstractReference $entry Entry bound to :e
     * @return int The single scalar result
     */
    private function scalar(string $dql, AbstractReference $entry): int
    {
        return (int) $this->em->createQuery($dql)->setParameter('e', $entry)->getSingleScalarResult();
    }
}
