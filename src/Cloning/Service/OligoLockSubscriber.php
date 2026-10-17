<?php
/**
 * Doctrine listener that refuses to change the sequence of an oligo used by a PCR
 * Freely inspired by Plasmi::db 0.9.1 (Mark Brooks) and BioPHP's project biophp.org
 * Created 18 October 2026
 * Last modified 18 October 2026
 */
declare(strict_types=1);

namespace App\Cloning\Service;

use App\Cloning\Entity\Oligo;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Events;

/**
 * Backstop of the oligo lock: whatever the write path (form, console command, import, test),
 * a flush that changes the normalised sequence of an oligo used by a PCR is refused. The case
 * and spacing of the raw text are tracked in the field history instead.
 *
 * @author Amélie DUVERNET aka Amelaye <amelieonline@gmail.com>
 */
#[AsDoctrineListener(event: Events::onFlush)]
final class OligoLockSubscriber
{
    /**
     * OligoLockSubscriber constructor.
     *
     * @param OligoLock $lock Counts the PCRs that use an oligo
     */
    public function __construct(private readonly OligoLock $lock)
    {
    }

    /**
     * Refuses the flush when a locked oligo has a new normalised sequence.
     *
     * @param OnFlushEventArgs $args Doctrine flush event
     * @return void
     * @throws LockedOligoException If the sequence of an oligo used by a PCR changed
     */
    public function onFlush(OnFlushEventArgs $args): void
    {
        $uow = $args->getObjectManager()->getUnitOfWork();
        foreach ($uow->getScheduledEntityUpdates() as $entity) {
            if (!$entity instanceof Oligo || null === $entity->getId()) {
                continue;
            }
            $changes = $uow->getEntityChangeSet($entity);
            if (!isset($changes['sequence']) || $changes['sequence'][0] === $changes['sequence'][1]) {
                continue;
            }
            if ($this->lock->pcrCount($entity) > 0) {
                throw new LockedOligoException(sprintf('The sequence of oligo #%d is locked: a PCR uses it.', $entity->getId()));
            }
        }
    }
}
