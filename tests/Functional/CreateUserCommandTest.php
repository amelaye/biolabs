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

    /**
     * Removes the password environment variable set by a test.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        putenv('BIOLABS_USER_PASSWORD');
        parent::tearDown();
    }

    /**
     * Tests that a member is created with the password read from the environment when not interactive.
     *
     * @return void
     */
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

    /**
     * Tests that the interactive mode asks for a confirmation and refuses two different entries.
     *
     * @return void
     */
    public function testInteractiveModeAsksTwiceAndRejectsMismatch(): void
    {
        $tester = $this->runCommand(['email' => 'a@example.org', 'display-name' => 'A'], ['interactive' => true], [self::GOOD_PASSWORD, 'autre-saisie-differente']);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertNull($this->users()->findOneByEmail('a@example.org'));
    }

    /**
     * Tests that the interactive mode creates the account when both entries match.
     *
     * @return void
     */
    public function testInteractiveModeCreatesAccount(): void
    {
        $tester = $this->runCommand(['email' => 'b@example.org', 'display-name' => 'B', '--role' => User::ROLE_ADMIN], ['interactive' => true], [self::GOOD_PASSWORD, self::GOOD_PASSWORD]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertContains(User::ROLE_ADMIN, $this->users()->findOneByEmail('b@example.org')->getRoles());
    }

    /**
     * Tests that a password shorter than the minimum is refused and no account is created.
     *
     * @return void
     */
    public function testRefusesShortPassword(): void
    {
        putenv('BIOLABS_USER_PASSWORD=court');

        $tester = $this->runCommand(['email' => 'c@example.org', 'display-name' => 'C'], ['interactive' => false]);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertNull($this->users()->findOneByEmail('c@example.org'));
    }

    /**
     * Tests that the non-interactive mode fails when the password variable is not defined.
     *
     * @return void
     */
    public function testNonInteractiveWithoutEnvironmentVariableFails(): void
    {
        $tester = $this->runCommand(['email' => 'd@example.org', 'display-name' => 'D'], ['interactive' => false]);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('BIOLABS_USER_PASSWORD', $tester->getDisplay());
    }

    /**
     * Tests that an unknown role is refused.
     *
     * @return void
     */
    public function testRefusesUnknownRole(): void
    {
        $tester = $this->runCommand(['email' => 'e@example.org', 'display-name' => 'E', '--role' => 'ROLE_ROOT'], ['interactive' => false]);

        self::assertSame(Command::INVALID, $tester->getStatusCode());
    }

    /**
     * Tests that an email already used is refused, whatever its case.
     *
     * @return void
     */
    public function testRefusesDuplicateEmailIgnoringCase(): void
    {
        $this->createUser('f@example.org');
        putenv('BIOLABS_USER_PASSWORD='.self::GOOD_PASSWORD);

        $tester = $this->runCommand(['email' => 'F@EXAMPLE.ORG', 'display-name' => 'F bis'], ['interactive' => false]);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
    }

    /**
     * Tests that an invalid email address is refused.
     *
     * @return void
     */
    public function testRefusesInvalidEmail(): void
    {
        putenv('BIOLABS_USER_PASSWORD='.self::GOOD_PASSWORD);

        $tester = $this->runCommand(['email' => 'not-an-email', 'display-name' => 'G'], ['interactive' => false]);

        self::assertSame(Command::INVALID, $tester->getStatusCode());
    }

    /**
     * Tests that a system account is created without password and cannot sign in.
     *
     * @return void
     */
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
     * Runs the command with synthetic arguments.
     *
     * @param array<string, string> $input Arguments of the command
     * @param array<string, mixed> $options Options of the command
     * @param list<string> $answers Answers given to the interactive questions
     * @return CommandTester The tester, to read the status and the display
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

    /**
     * Repository of the accounts.
     *
     * @return UserRepository The repository
     */
    private function users(): UserRepository
    {
        return static::getContainer()->get(UserRepository::class);
    }
}
