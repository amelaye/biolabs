<?php
/**
 * Records the old and new value of the tracked fields on every update
 * Freely inspired by Plasmi::db 0.9.1 (Mark Brooks) and BioPHP's project biophp.org
 * Created 17 October 2026
 * Last modified 18 October 2026
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
 * For each really modified {@see TracksFieldHistoryInterface} entity, inserts one
 * {@see FieldHistory} row per tracked field whose value changed. Creations are not recorded
 * (the audit columns already say who created the entity and when).
 *
 * @author Amélie DUVERNET aka Amelaye <amelieonline@gmail.com>
 */
#[AsDoctrineListener(event: Events::onFlush)]
final class FieldHistorySubscriber
{
    /**
     * FieldHistorySubscriber constructor.
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
     * Inserts the history rows of the tracked fields that changed in this flush.
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
        $meta = $em->getClassMetadata(FieldHistory::class);

        foreach ($uow->getScheduledEntityUpdates() as $entity) {
            if (!$entity instanceof TracksFieldHistoryInterface || null === $entity->getId()) {
                continue;
            }
            $changes = $uow->getEntityChangeSet($entity);
            foreach ($entity->trackedFields() as $field) {
                if (!isset($changes[$field])) {
                    continue;
                }
                [$old, $new] = $changes[$field];
                $oldText = $this->asText($old);
                $newText = $this->asText($new);
                if ($oldText === $newText) {
                    continue;
                }
                $row = new FieldHistory($em->getClassMetadata($entity::class)->getName(), $entity->getId(), $field, $oldText, $newText, $now, $author);
                $em->persist($row);
                $uow->computeChangeSet($meta, $row);
            }
        }
    }

    /**
     * Text stored for a value: scalars as is, enums by value, entities by name or identifier.
     *
     * @param mixed $value Value from the Doctrine change set
     * @return string|null The text, null for null
     */
    private function asText(mixed $value): ?string
    {
        return match (true) {
            null === $value => null,
            $value instanceof \BackedEnum => (string) $value->value,
            \is_object($value) && method_exists($value, 'getName') => (string) $value->getName(),
            \is_object($value) && method_exists($value, 'getId') => (string) $value->getId(),
            \is_scalar($value) => (string) $value,
            default => null,
        };
    }

    /**
     * Signed-in person as a reference attached to the current unit of work.
     *
     * @param EntityManagerInterface $em Entity manager that receives the reference
     * @return User|null The user reference, null when nobody is signed in (console, import)
     */
    private function currentUser(EntityManagerInterface $em): ?User
    {
        $user = $this->tokenStorage->getToken()?->getUser();
        if (!$user instanceof User || null === $user->getId()) {
            return null;
        }

        return $em->getReference(User::class, $user->getId());
    }
}
