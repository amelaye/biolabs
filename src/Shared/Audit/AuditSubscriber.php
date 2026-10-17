<?php
/**
 * Fills the audit columns on every write
 * Freely inspired by Plasmi::db 0.9.1 (Mark Brooks) and BioPHP's project biophp.org
 * Created 17 October 2026
 * Last modified 17 October 2026
 */
declare(strict_types=1);

namespace App\Shared\Audit;

use App\Account\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Events;
use Psr\Clock\ClockInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Fills the audit columns on every write, with the signed-in person.
 *
 * An entity is only marked as "updated" when Doctrine detects a real change.
 *
 * @author Amélie DUVERNET aka Amelaye <amelieonline@gmail.com>
 */
#[AsDoctrineListener(event: Events::onFlush)]
final class AuditSubscriber
{
    /**
     * AuditSubscriber constructor.
     *
     * @param TokenStorageInterface $tokenStorage Gives the signed-in person
     * @param ClockInterface $clock Source of the current time (replaceable in tests)
     */
    public function __construct(
        private readonly TokenStorageInterface $tokenStorage,
        private readonly ClockInterface $clock,
    ) {
    }

    /**
     * Stamps every inserted or really modified auditable entity with the date and the signed-in person.
     *
     * @param OnFlushEventArgs $args Doctrine flush event, gives the entity manager and its unit of work
     * @return void
     */
    public function onFlush(OnFlushEventArgs $args): void
    {
        $em = $args->getObjectManager();
        $uow = $em->getUnitOfWork();
        $now = \DateTimeImmutable::createFromInterface($this->clock->now());
        $author = $this->currentUser($em);

        foreach ($uow->getScheduledEntityInsertions() as $entity) {
            if ($entity instanceof AuditableInterface) {
                $entity->recordCreation($now, $author);
                $uow->recomputeSingleEntityChangeSet($em->getClassMetadata($entity::class), $entity);
            }
        }

        foreach ($uow->getScheduledEntityUpdates() as $entity) {
            if ($entity instanceof AuditableInterface) {
                $entity->recordUpdate($now, $author);
                $uow->recomputeSingleEntityChangeSet($em->getClassMetadata($entity::class), $entity);
            }
        }
    }

    /**
     * Signed-in person as a reference attached to the current unit of work.
     *
     * @param EntityManagerInterface $em Entity manager that receives the reference
     * @return User|null The user reference, null when nobody is signed in (console, import) or the user is not stored yet
     */
    private function currentUser(EntityManagerInterface $em): ?User
    {
        $user = $this->tokenStorage->getToken()?->getUser();
        if (!$user instanceof User || null === $user->getId()) {
            return null;
        }

        // The token user may come from a session: attach it to the current unit of work.
        return $em->getReference(User::class, $user->getId());
    }
}
