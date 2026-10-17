<?php
/**
 * Console command creating a user account
 * Freely inspired by Plasmi::db 0.9.1 (Mark Brooks) and BioPHP's project biophp.org
 * Created 17 October 2026
 * Last modified 17 October 2026
 */
declare(strict_types=1);

namespace App\Account\Command;

use App\Account\Entity\User;
use App\Account\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @author Amélie DUVERNET aka Amelaye <amelieonline@gmail.com>
 */
#[AsCommand(name: 'biolabs:user:create', description: 'Crée un compte utilisateur')]
final class CreateUserCommand extends Command
{
    private const MIN_PASSWORD_LENGTH = 12;
    private const PASSWORD_ENV = 'BIOLABS_USER_PASSWORD';

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserRepository $users,
        private readonly UserPasswordHasherInterface $hasher,
        private readonly ValidatorInterface $validator,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'Adresse e-mail (identifiant de connexion)')
            ->addArgument('display-name', InputArgument::REQUIRED, 'Nom affiché')
            ->addOption('role', 'r', InputOption::VALUE_REQUIRED, 'Rôle : '.implode(', ', User::ASSIGNABLE_ROLES), User::ROLE_MEMBER)
            ->addOption('locale', null, InputOption::VALUE_REQUIRED, 'Langue préférée (fr, en)')
            ->addOption('system', null, InputOption::VALUE_NONE, 'Compte système non connectable (aucun mot de passe)')
            ->setHelp(<<<'HELP'
Le mot de passe n'est jamais passé en argument : il est demandé de façon masquée.
En mode non interactif, il est lu dans la variable d'environnement BIOLABS_USER_PASSWORD.
HELP);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $role = (string) $input->getOption('role');
        if (!\in_array($role, User::ASSIGNABLE_ROLES, true)) {
            $io->error(sprintf('Rôle inconnu "%s". Rôles possibles : %s.', $role, implode(', ', User::ASSIGNABLE_ROLES)));

            return Command::INVALID;
        }

        $user = new User((string) $input->getArgument('email'), (string) $input->getArgument('display-name'));
        $user->setRoles([$role])->setLocale($input->getOption('locale') ?: null);

        if (null !== $this->users->findOneByEmail($user->getEmail())) {
            $io->error('Un compte existe déjà avec cette adresse.');

            return Command::FAILURE;
        }

        $violations = $this->validator->validate($user);
        if (\count($violations) > 0) {
            foreach ($violations as $violation) {
                $io->error($violation->getPropertyPath().' : '.$violation->getMessage());
            }

            return Command::INVALID;
        }

        if ($input->getOption('system')) {
            $user->setSystem(true);
        } else {
            $plain = $this->readPassword($input, $output, $io);
            if (null === $plain) {
                return Command::FAILURE;
            }
            $user->setPassword($this->hasher->hashPassword($user, $plain));
        }

        $this->em->persist($user);
        $this->em->flush();

        $io->success(sprintf('Compte créé : %s (%s).', $user->getEmail(), $role));

        return Command::SUCCESS;
    }

    private function readPassword(InputInterface $input, OutputInterface $output, SymfonyStyle $io): ?string
    {
        if ($input->isInteractive()) {
            $ask = static function (string $label) use ($io): ?string {
                $question = (new Question($label))->setHidden(true)->setHiddenFallback(false);

                return $io->askQuestion($question);
            };
            $plain = $ask('Mot de passe');
            if ($plain !== $ask('Confirmation du mot de passe')) {
                $io->error('Les deux saisies diffèrent.');

                return null;
            }
        } else {
            $plain = getenv(self::PASSWORD_ENV) ?: null;
            if (null === $plain) {
                $io->error(sprintf('Mode non interactif : définir la variable %s.', self::PASSWORD_ENV));

                return null;
            }
        }

        if (null === $plain || mb_strlen($plain) < self::MIN_PASSWORD_LENGTH) {
            $io->error(sprintf('Le mot de passe doit contenir au moins %d caractères.', self::MIN_PASSWORD_LENGTH));

            return null;
        }

        return $plain;
    }
}
