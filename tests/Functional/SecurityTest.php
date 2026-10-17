<?php
/**
 * Tests of login, logout and throttling
 * Freely inspired by Plasmi::db 0.9.1 (Mark Brooks) and BioPHP's project biophp.org
 * Created 17 October 2026
 * Last modified 17 October 2026
 */
declare(strict_types=1);

namespace App\Tests\Functional;

use App\Account\Entity\User;
use App\Tests\Support\DatabaseWebTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * @author Amélie DUVERNET aka Amelaye <amelieonline@gmail.com>
 */
final class SecurityTest extends DatabaseWebTestCase
{
    public function testAnonymousVisitorIsRedirectedToLogin(): void
    {
        $this->client->request('GET', '/');

        self::assertResponseRedirects('/login');
    }

    public function testLoginPageShowsLabelledFormWithCsrfToken(): void
    {
        $crawler = $this->client->request('GET', '/login');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('label[for="username"]');
        self::assertSelectorExists('label[for="password"]');
        self::assertCount(1, $crawler->filter('input[name="_csrf_token"]'));
    }

    public function testMemberCanLogInAndSeesTheirNameAndRole(): void
    {
        $this->createUser();

        $this->logIn('marie.test@example.org', 'a-long-test-password');

        self::assertResponseRedirects('/');
        $this->client->followRedirect();
        self::assertSelectorTextContains('.user-name', 'Marie Test');
        self::assertSelectorTextContains('.role-badge', 'Membre');
    }

    public function testLoginIsCaseInsensitiveOnEmail(): void
    {
        $this->createUser();

        $this->logIn('Marie.Test@EXAMPLE.org', 'a-long-test-password');

        self::assertResponseRedirects('/');
    }

    public function testLastLoginIsRecordedWithoutTouchingAudit(): void
    {
        $user = $this->createUser();
        $this->em->clear();
        $updatedAt = $this->em->find(User::class, $user->getId())->getUpdatedAt();
        self::assertNotNull($updatedAt);

        $this->logIn('marie.test@example.org', 'a-long-test-password');

        $this->em->clear();
        $reloaded = $this->em->find(User::class, $user->getId());
        self::assertNotNull($reloaded->getLastLoginAt());
        self::assertEquals($updatedAt, $reloaded->getUpdatedAt(), 'A login is not a modification of the account.');
    }

    public function testWrongPasswordIsRefusedWithoutRevealingWhetherTheAccountExists(): void
    {
        $this->createUser();

        $this->logIn('marie.test@example.org', 'wrong-password');
        $this->client->followRedirect();
        $wrongPassword = $this->client->getCrawler()->filter('.notice-error')->text();

        $this->logIn('inconnu@example.org', 'wrong-password');
        $this->client->followRedirect();
        $unknownAccount = $this->client->getCrawler()->filter('.notice-error')->text();

        self::assertNotSame('', $wrongPassword);
        self::assertSame($wrongPassword, $unknownAccount);
    }

    #[DataProvider('unavailableAccounts')]
    public function testInactiveOrSystemAccountsCannotLogIn(bool $active, bool $system): void
    {
        $this->createUser(active: $active, system: $system);

        $this->logIn('marie.test@example.org', 'a-long-test-password');
        $this->client->followRedirect();

        self::assertSelectorTextContains('.notice-error', 'Ce compte ne peut pas se connecter');
        $this->client->request('GET', '/');
        self::assertResponseRedirects('/login');
    }

    /**
     * @return iterable<string, array{bool, bool}>
     */
    public static function unavailableAccounts(): iterable
    {
        yield 'deactivated' => [false, false];
        yield 'system' => [true, true];
    }

    public function testLoginIsThrottledAfterRepeatedFailures(): void
    {
        $this->createUser();

        for ($i = 0; $i < 6; ++$i) {
            $this->logIn('marie.test@example.org', 'wrong-'.$i);
            $this->client->followRedirect();
        }
        // even with the right password, the limit is reached
        $this->logIn('marie.test@example.org', 'a-long-test-password');
        $this->client->request('GET', '/');

        self::assertResponseRedirects('/login');
    }

    public function testLogoutEndsTheSession(): void
    {
        $this->client->loginUser($this->createUser());
        $this->client->request('GET', '/');
        self::assertResponseIsSuccessful();

        $this->client->click($this->client->getCrawler()->selectLink('Se déconnecter')->link());
        $this->client->request('GET', '/');

        self::assertResponseRedirects('/login');
    }

    public function testLogoutWithoutCsrfTokenDoesNotEndTheSession(): void
    {
        $this->client->loginUser($this->createUser());

        $this->client->request('GET', '/logout');
        $this->client->request('GET', '/');

        self::assertResponseIsSuccessful();
    }

    public function testAlreadyAuthenticatedUserIsSentHomeFromLogin(): void
    {
        $this->client->loginUser($this->createUser());

        $this->client->request('GET', '/login');

        self::assertResponseRedirects('/');
    }

    private function logIn(string $email, string $password): void
    {
        $this->client->request('GET', '/login');
        $this->client->submitForm('Se connecter', ['_username' => $email, '_password' => $password]);
    }
}
