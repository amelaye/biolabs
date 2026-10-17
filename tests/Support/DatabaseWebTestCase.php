<?php
/**
 * Base class of functional tests : fresh SQLite schema per test
 * Freely inspired by Plasmi::db 0.9.1 (Mark Brooks) and BioPHP's project biophp.org
 * Created 17 October 2026
 * Last modified 18 October 2026
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

    /**
     * Creates the client and a fresh SQLite schema (bl_ tables only) before each test, and clears the login throttling cache.
     *
     * @return void
     */
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
     * Directory where this PHPUnit process stores attachments (one per process, see tests/bootstrap.php).
     *
     * @return string The absolute path (it may not exist yet)
     */
    protected function attachmentsDir(): string
    {
        return (string) static::getContainer()->getParameter('app.attachments_dir');
    }

    /**
     * Creates and stores a synthetic account.
     *
     * @param string $email Email address
     * @param string $name Display name
     * @param string $password Plain password, hashed before storage
     * @param list<string> $roles Roles to assign
     * @param bool $active Whether the account is enabled
     * @param bool $system Whether it is a non-login system account
     * @return User The stored account
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
