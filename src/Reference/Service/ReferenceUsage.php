<?php
/**
 * Number of objects that use a reference entry
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
use App\Cloning\Entity\VectorTagCapability;
use App\Reference\Entity\AbstractReference;
use App\Reference\Entity\Organism;
use App\Reference\Entity\Promoter;
use App\Reference\Entity\ReplicationOrigin;
use App\Reference\Entity\SelectionMarker;
use App\Reference\Entity\Strain;
use App\Reference\Entity\Supplier;
use App\Reference\Entity\Tag;
use App\Target\Entity\Complex;
use App\Target\Entity\Gene;
use App\Target\Entity\Product;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Usage counter shown next to each entry. Only the links that exist today are counted
 * (marker -> strains and vectors, organism -> genes, gene -> products, complexes and PCRs,
 * oligo -> PCRs, vector -> constructs and PCRs...); the lots that create clones or samples add
 * their own counts here.
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
            $entry instanceof SelectionMarker => $this->scalar('SELECT COUNT(s.id) FROM '.Strain::class.' s JOIN s.resistances m WHERE m = :e', $entry)
                + $this->scalar('SELECT COUNT(v.id) FROM '.Vector::class.' v WHERE v.marker = :e', $entry),
            $entry instanceof Organism => $this->scalar('SELECT COUNT(g.id) FROM '.Gene::class.' g WHERE g.organism = :e', $entry),
            $entry instanceof Gene => $this->scalar('SELECT COUNT(p.id) FROM '.Product::class.' p WHERE p.gene = :e', $entry)
                + $this->scalar('SELECT COUNT(c.id) FROM '.Complex::class.' c JOIN c.genes g WHERE g = :e', $entry)
                + $this->scalar('SELECT COUNT(r.id) FROM '.Pcr::class.' r WHERE r.templateGene = :e', $entry),
            $entry instanceof Product => $this->scalar('SELECT COUNT(i.id) FROM '.Insert::class.' i WHERE i.product = :e', $entry)
                + $this->scalar('SELECT COUNT(c.id) FROM '.Construct::class.' c JOIN c.products p WHERE p = :e', $entry),
            $entry instanceof Supplier => $this->scalar('SELECT COUNT(o.id) FROM '.Oligo::class.' o WHERE o.supplier = :e', $entry)
                + $this->scalar('SELECT COUNT(v.id) FROM '.Vector::class.' v WHERE v.supplier = :e', $entry),
            $entry instanceof Promoter => $this->scalar('SELECT COUNT(v.id) FROM '.Vector::class.' v WHERE v.promoter = :e', $entry),
            $entry instanceof ReplicationOrigin => $this->scalar('SELECT COUNT(v.id) FROM '.Vector::class.' v WHERE v.origin = :e', $entry),
            $entry instanceof Tag => $this->scalar('SELECT COUNT(t.id) FROM '.VectorTagCapability::class.' t WHERE t.tag = :e', $entry),
            $entry instanceof Oligo => $this->scalar('SELECT COUNT(r.id) FROM '.Pcr::class.' r WHERE r.forwardOligo = :e OR r.reverseOligo = :e', $entry),
            $entry instanceof Vector => $this->scalar('SELECT COUNT(c.id) FROM '.Construct::class.' c WHERE c.vector = :e', $entry)
                + $this->scalar('SELECT COUNT(r.id) FROM '.Pcr::class.' r WHERE r.templateVector = :e', $entry),
            $entry instanceof Pcr => $this->scalar('SELECT COUNT(i.id) FROM '.Insert::class.' i WHERE i.pcr = :e', $entry),
            $entry instanceof Insert => $this->scalar('SELECT COUNT(c.id) FROM '.Construct::class.' c WHERE c.insert = :e', $entry),
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
