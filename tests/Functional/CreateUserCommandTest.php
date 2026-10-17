<?php
/**
 * Tests of the biolabs:user:create command
 * Freely inspired by Plasmi::db 0.9.1 (Mark Brooks) and BioPHP's project biophp.org
 * Created 17 October 2026
 * Last modified 17 October 2026
 */
declare(strict_types=1);

namespace App\Tests\Functional;

use App\Account\Entity\User;
use App\Account\Repository\UserRepository;
use App\Tests\Support\DatabaseWebTestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * @author Amélie DUVERNET aka Amelaye <amelieonline@gmail.com>
 */
final class CreateUserCommandTest extends DatabaseWebTestCase
{
    private const GOOD_PASSWORD = 'a-long-enough-password';

    protected function tearDown(): void
    {
        putenv('BIOLABS_USER_PASSWORD');
        parent::tearDown();
    }

    public function testCreatesAMemberWithPasswordFromEnvironmentInNonInteractiveMode(): void
    {
        putenv('BIOLABS_USER_PASSWORD='.self::GOOD_PASSWORD);

        $tester = $this->runCommand(['email' => 'Nouveau@Example.org', 'display-name' => 'Nouveau'], ['interactive' => false]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        $user = $this->users()->findOneByEmail('nouveau@example.org');
        self::assertNotNull($user);
        self::assertSame([User::ROLE_MEMBER], $user->getRoles());
        self::assertTrue(static::getContainer()->get(UserPasswordHasherInterface::class)->isPasswordValid($user, self::GOOD_PASSWORD));
        self::assertStringNotContainsString(self::GOOD_PASSWORD, $tester->getDisplay());
        self::assertStringNotContainsString(self::GOOD_PASSWORD, $user->getPassword(), 'The password is stored hashed.');
    }

    public function testInteractiveModeAsksTwiceAndRejectsMismatch(): void
    {
        $tester = $this->runCommand(['email' => 'a@example.org', 'display-name' => 'A'], ['interactive' => true], [self::GOOD_PASSWORD, 'autre-saisie-differente']);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertNull($this->users()->findOneByEmail('a@example.org'));
    }

    public function testInteractiveModeCreatesAccount(): void
    {
        $tester = $this->runCommand(['email' => 'b@example.org', 'display-name' => 'B', '--role' => User::ROLE_ADMIN], ['interactive' => true], [self::GOOD_PASSWORD, self::GOOD_PASSWORD]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertContains(User::ROLE_ADMIN, $this->users()->findOneByEmail('b@example.org')->getRoles());
    }

    public function testRefusesShortPassword(): void
    {
        putenv('BIOLABS_USER_PASSWORD=court');

        $tester = $this->runCommand(['email' => 'c@example.org', 'display-name' => 'C'], ['interactive' => false]);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertNull($this->users()->findOneByEmail('c@example.org'));
    }

    public function testNonInteractiveWithoutEnvironmentVariableFails(): void
    {
        $tester = $this->runCommand(['email' => 'd@example.org', 'display-name' => 'D'], ['interactive' => false]);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('BIOLABS_USER_PASSWORD', $tester->getDisplay());
    }

    public function testRefusesUnknownRole(): void
    {
        $tester = $this->runCommand(['email' => 'e@example.org', 'display-name' => 'E', '--role' => 'ROLE_ROOT'], ['interactive' => false]);

        self::assertSame(Command::INVALID, $tester->getStatusCode());
    }

    public function testRefusesDuplicateEmailIgnoringCase(): void
    {
        $this->createUser('f@example.org');
        putenv('BIOLABS_USER_PASSWORD='.self::GOOD_PASSWORD);

        $tester = $this->runCommand(['email' => 'F@EXAMPLE.ORG', 'display-name' => 'F bis'], ['interactive' => false]);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
    }

    public function testRefusesInvalidEmail(): void
    {
        putenv('BIOLABS_USER_PASSWORD='.self::GOOD_PASSWORD);

        $tester = $this->runCommand(['email' => 'not-an-email', 'display-name' => 'G'], ['interactive' => false]);

        self::assertSame(Command::INVALID, $tester->getStatusCode());
    }

    public function testSystemAccountHasNoPasswordAndCannotLogIn(): void
    {
        $tester = $this->runCommand(['email' => 'import@example.org', 'display-name' => 'Import Plasmi::db', '--system' => true], ['interactive' => false]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        $user = $this->users()->findOneByEmail('import@example.org');
        self::assertTrue($user->isSystem());
        self::assertSame('', $user->getPassword());
        self::assertFalse($user->canLogin());
    }

    /**
     * @param array<string, mixed> $input
     * @param array<string, mixed> $options
     * @param list<string>         $answers
     */
    private function runCommand(array $input, array $options, array $answers = []): CommandTester
    {
        $application = new Application(static::$kernel);
        $tester = new CommandTester($application->find('biolabs:user:create'));
        if ([] !== $answers) {
            $tester->setInputs($answers);
        }
        $tester->execute($input, $options);

        return $tester;
    }

    private function users(): UserRepository
    {
        return static::getContainer()->get(UserRepository::class);
    }
}
