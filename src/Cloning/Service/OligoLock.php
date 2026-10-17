<?php
/**
 * Lock of the sequence of an oligo used by a PCR
 * Freely inspired by Plasmi::db 0.9.1 (Mark Brooks) and BioPHP's project biophp.org
 * Created 18 October 2026
 * Last modified 18 October 2026
 */
declare(strict_types=1);

namespace App\Cloning\Service;

use App\Cloning\Entity\Oligo;
use App\Cloning\Entity\Pcr;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Knows how many PCRs use an oligo: the sequence of an oligo is locked while there is one. The
 * rule is enforced twice: by the form validation ({@see \App\Cloning\Validator\OligoSequenceLocked},
 * for a readable message) and by {@see OligoLockSubscriber} at flush time (for any other write path).
 *
 * @author Amélie DUVERNET aka Amelaye <amelieonline@gmail.com>
 */
final class OligoLock
{
    /**
     * OligoLock constructor.
     *
     * @param EntityManagerInterface $em Entity manager used to count the PCRs
     */
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    /**
     * Number of PCRs that use an oligo as forward or reverse primer.
     *
     * @param Oligo $oligo Stored oligo
     * @return int The number of PCRs
     */
    public function pcrCount(Oligo $oligo): int
    {
        return (int) $this->em->createQuery('SELECT COUNT(p.id) FROM '.Pcr::class.' p WHERE p.forwardOligo = :o OR p.reverseOligo = :o')
            ->setParameter('o', $oligo)
            ->getSingleScalarResult();
    }
}
