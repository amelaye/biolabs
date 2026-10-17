<?php
/**
 * Tests of the audit columns
 * Freely inspired by Plasmi::db 0.9.1 (Mark Brooks) and BioPHP's project biophp.org
 * Created 17 October 2026
 * Last modified 17 October 2026
 */
declare(strict_types=1);

namespace App\Tests\Functional;

use App\Account\Entity\User;
use App\Tests\Support\DatabaseWebTestCase;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

/**
 * @author Amélie DUVERNET aka Amelaye <amelieonline@gmail.com>
 */
final class AuditTest extends DatabaseWebTestCase
{
    public function testCreationWithoutConnectedUserHasNoAuthor(): void
    {
        $user = $this->createUser();

        self::assertNotNull($user->getCreatedAt());
        self::assertNull($user->getCreatedBy());
        self::assertEquals($user->getCreatedAt(), $user->getUpdatedAt());
    }

    public function testCreationRecordsTheConnectedUser(): void
    {
        $admin = $this->createUser('admin@example.org', 'Admin', roles: [User::ROLE_ADMIN]);
        $this->authenticateAs($admin);

        $created = $this->createUser('new@example.org', 'New');

        self::assertSame($admin->getId(), $created->getCreatedBy()?->getId());
        self::assertSame($admin->getId(), $created->getUpdatedBy()?->getId());
    }

    public function testUpdateRecordsWhoAndWhenButKeepsCreation(): void
    {
        $author = $this->createUser('author@example.org', 'Author');
        $editor = $this->createUser('editor@example.org', 'Editor', roles: [User::ROLE_ADMIN]);
        $this->authenticateAs($author);
        $targetId = $this->createUser('target@example.org', 'Target')->getId();

        // reload: the database only keeps dates to the second
        $this->em->clear();
        $createdAt = $this->em->find(User::class, $targetId)->getCreatedAt();

        $this->authenticateAs($this->em->find(User::class, $editor->getId()));
        $target = $this->em->find(User::class, $targetId);
        $target->setDisplayName('Renamed target');
        $this->em->flush();

        $this->em->clear();
        $reloaded = $this->em->find(User::class, $targetId);
        self::assertSame($author->getId(), $reloaded->getCreatedBy()?->getId(), 'The creation author never changes.');
        self::assertEquals($createdAt, $reloaded->getCreatedAt(), 'The creation timestamp is immutable.');
        self::assertSame($editor->getId(), $reloaded->getUpdatedBy()?->getId());
        self::assertGreaterThanOrEqual($createdAt, $reloaded->getUpdatedAt());
    }

    public function testFlushWithoutChangeDoesNotMarkTheEntityAsUpdated(): void
    {
        $user = $this->createUser();
        $updatedAt = $user->getUpdatedAt();

        usleep(2000);
        $this->em->flush();

        self::assertEquals($updatedAt, $user->getUpdatedAt());
    }

    private function authenticateAs(User $user): void
    {
        static::getContainer()->get(TokenStorageInterface::class)
            ->setToken(new UsernamePasswordToken($user, 'main', $user->getRoles()));
    }
}
