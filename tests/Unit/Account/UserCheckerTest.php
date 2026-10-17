<?php
/**
 * Unit tests of UserChecker
 * Freely inspired by Plasmi::db 0.9.1 (Mark Brooks) and BioPHP's project biophp.org
 * Created 17 October 2026
 * Last modified 17 October 2026
 */
declare(strict_types=1);

namespace App\Tests\Unit\Account;

use App\Account\Entity\User;
use App\Account\Security\UserChecker;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;

/**
 * @author Amélie DUVERNET aka Amelaye <amelieonline@gmail.com>
 */
final class UserCheckerTest extends TestCase
{
    /**
     * Tests that an active, non-system account passes the pre-authentication check.
     *
     * @return void
     */
    public function testActiveUserPasses(): void
    {
        (new UserChecker())->checkPreAuth(new User('a@example.org', 'A'));

        $this->addToAssertionCount(1);
    }

    /**
     * Tests that deactivated and system accounts are refused with one message that does not tell them apart.
     *
     * @return void
     */
    public function testInactiveAndSystemAccountsAreRejectedWithTheSameMessage(): void
    {
        $messages = [];
        foreach ([(new User('a@example.org', 'A'))->setActive(false), (new User('b@example.org', 'B'))->setSystem(true)] as $user) {
            try {
                (new UserChecker())->checkPreAuth($user);
                self::fail('An exception was expected.');
            } catch (CustomUserMessageAccountStatusException $e) {
                $messages[] = $e->getMessageKey();
            }
        }

        self::assertSame(['security.account_unavailable', 'security.account_unavailable'], $messages);
    }
}
