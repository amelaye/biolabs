<?php
/**
 * Unit tests of the User entity
 * Freely inspired by Plasmi::db 0.9.1 (Mark Brooks) and BioPHP's project biophp.org
 * Created 17 October 2026
 * Last modified 17 October 2026
 */
declare(strict_types=1);

namespace App\Tests\Unit\Account;

use App\Account\Entity\User;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @author Amélie DUVERNET aka Amelaye <amelieonline@gmail.com>
 */
final class UserTest extends TestCase
{
    /**
     * Tests that the email is lowercased and trimmed, and is the login identifier.
     *
     * @return void
     */
    public function testEmailIsNormalisedAndUsedAsIdentifier(): void
    {
        $user = new User('  Marie.Test@Example.ORG ', ' Marie Test ');

        self::assertSame('marie.test@example.org', $user->getEmail());
        self::assertSame('marie.test@example.org', $user->getUserIdentifier());
        self::assertSame('Marie Test', $user->getDisplayName());
    }

    /**
     * Tests that ROLE_MEMBER is always present exactly once, whatever roles are assigned.
     *
     * @return void
     */
    public function testMemberRoleIsAlwaysPresentWithoutDuplicates(): void
    {
        $user = new User('a@example.org', 'A');
        self::assertSame([User::ROLE_MEMBER], $user->getRoles());

        $user->setRoles([User::ROLE_ADMIN, User::ROLE_ADMIN, User::ROLE_MEMBER]);
        self::assertSame([User::ROLE_ADMIN, User::ROLE_MEMBER], $user->getRoles());
    }

    /**
     * Tests that assigning a role outside the assignable roles throws an exception.
     *
     * @return void
     */
    public function testUnknownRoleIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new User('a@example.org', 'A'))->setRoles(['ROLE_SUPERUSER']);
    }

    /**
     * Tests which combinations of active and system flags may sign in.
     *
     * @param bool $active Whether the account is enabled
     * @param bool $system Whether it is a system account
     * @param bool $expected Whether the person may sign in
     * @return void
     */
    #[DataProvider('loginMatrix')]
    public function testCanLogin(bool $active, bool $system, bool $expected): void
    {
        $user = (new User('a@example.org', 'A'))->setActive($active)->setSystem($system);

        self::assertSame($expected, $user->canLogin());
    }

    /**
     * Data provider: every combination of the active and system flags.
     *
     * @return iterable<string, array{bool, bool, bool}> Active flag, system flag and expected result by case name
     */
    public static function loginMatrix(): iterable
    {
        yield 'active' => [true, false, true];
        yield 'deactivated' => [false, false, false];
        yield 'system' => [true, true, false];
        yield 'deactivated system' => [false, true, false];
    }

    /**
     * Tests that the serialized user (session) holds a fingerprint of the hash, never the hash itself.
     *
     * @return void
     */
    public function testSerializationNeverContainsThePasswordHash(): void
    {
        $hash = '$2y$13$abcdefghijklmnopqrstuuABCDEFGHIJKLMNOPQRSTUVWXYZ012345';
        $user = (new User('a@example.org', 'A'))->setPassword($hash);

        $serialized = serialize($user);

        self::assertStringNotContainsString($hash, $serialized);
        self::assertStringContainsString(hash('crc32c', $hash), $serialized);
    }
}
