<?php
/**
 * Base class of functional tests : fresh SQLite schema per test
 * Freely inspired by Plasmi::db 0.9.1 (Mark Brooks) and BioPHP's project biophp.org
 * Created 17 October 2026
 * Last modified 17 October 2026
 */
declare(strict_types=1);

namespace App\Tests\Support;

use App\Account\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Base class of the functional tests: test SQLite database recreated before each test.
 *
 * Only the "bl_" tables are created; the tables of biophp's entities are not used here.
 *
 * @author Amélie DUVERNET aka Amelaye <amelieonline@gmail.com>
 */
abstract class DatabaseWebTestCase extends WebTestCase
{
    protected KernelBrowser $client;
    protected EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get('doctrine')->getManager();

        $metadata = array_values(array_filter(
            $this->em->getMetadataFactory()->getAllMetadata(),
            static fn ($m) => str_starts_with($m->getTableName(), 'bl_'),
        ));
        $tool = new SchemaTool($this->em);
        $tool->dropDatabase();
        $tool->createSchema($metadata);

        // Login throttling is persisted in the cache: start from scratch.
        static::getContainer()->get('cache.rate_limiter')->clear();
    }

    /**
     * @param list<string> $roles
     */
    protected function createUser(
        string $email = 'marie.test@example.org',
        string $name = 'Marie Test',
        string $password = 'a-long-test-password',
        array $roles = [User::ROLE_MEMBER],
        bool $active = true,
        bool $system = false,
    ): User {
        $user = new User($email, $name);
        $user->setRoles($roles)->setActive($active)->setSystem($system);
        $user->setPassword(static::getContainer()->get(UserPasswordHasherInterface::class)->hashPassword($user, $password));
        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }
}
