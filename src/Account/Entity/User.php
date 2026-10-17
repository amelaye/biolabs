<?php
/**
 * Entity User - laboratory member or system account
 * Freely inspired by Plasmi::db 0.9.1 (Mark Brooks) and BioPHP's project biophp.org
 * Created 17 October 2026
 * Last modified 17 October 2026
 */
declare(strict_types=1);

namespace App\Account\Entity;

use App\Account\Repository\UserRepository;
use App\Shared\Audit\AuditableInterface;
use App\Shared\Audit\AuditableTrait;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Person with access to biolabs, or non-login system account.
 *
 * As in Plasmi::db, every signed-in member can work on all the laboratory data;
 * only ROLE_ADMIN is added, to manage accounts. ROLE_MEMBER is always present.
 *
 * @author Amélie DUVERNET aka Amelaye <amelieonline@gmail.com>
 */
#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'bl_user')]
#[ORM\UniqueConstraint(name: 'uniq_bl_user_email', columns: ['email'])]
#[UniqueEntity(fields: ['email'], message: 'user.email.already_used')]
class User implements UserInterface, PasswordAuthenticatedUserInterface, AuditableInterface
{
    use AuditableTrait;

    public const ROLE_MEMBER = 'ROLE_MEMBER';
    public const ROLE_ADMIN = 'ROLE_ADMIN';

    /** Assignable roles, from the weakest to the strongest. */
    public const ASSIGNABLE_ROLES = [
        self::ROLE_MEMBER,
        self::ROLE_ADMIN,
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 180)]
    #[Assert\NotBlank]
    #[Assert\Email]
    #[Assert\Length(max: 180)]
    private string $email;

    #[ORM\Column(type: 'string', length: 100)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    private string $displayName;

    /** @var list<string> */
    #[ORM\Column(type: 'json')]
    private array $roles = [];

    /** Hashed password; empty string for a system account. */
    #[ORM\Column(type: 'string', length: 255)]
    private string $password = '';

    #[ORM\Column(type: 'boolean')]
    private bool $active = true;

    /** Non-login account (e.g. import of the historical data). */
    #[ORM\Column(name: 'is_system', type: 'boolean')]
    private bool $system = false;

    /** Preferred interface language (fr, en); null = browser/session language. */
    #[ORM\Column(type: 'string', length: 5, nullable: true)]
    private ?string $locale = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $lastLoginAt = null;

    /**
     * User constructor.
     *
     * @param string $email Email address, the login identifier (stored in lowercase)
     * @param string $displayName Name shown in the interface
     */
    public function __construct(string $email, string $displayName)
    {
        $this->email = mb_strtolower(trim($email));
        $this->displayName = trim($displayName);
    }

    /**
     * Primary key.
     *
     * @return int|null The identifier, null until the account is stored
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * Email address, the login identifier.
     *
     * @return string The address in lowercase
     */
    public function getEmail(): string
    {
        return $this->email;
    }

    /**
     * Name shown in the interface.
     *
     * @return string The display name
     */
    public function getDisplayName(): string
    {
        return $this->displayName;
    }

    /**
     * Changes the name shown in the interface.
     *
     * @param string $displayName New display name (surrounding spaces are removed)
     * @return static This account
     */
    public function setDisplayName(string $displayName): static
    {
        $this->displayName = trim($displayName);

        return $this;
    }

    /**
     * Identifier used by the security component.
     *
     * @return string The email address
     */
    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    /**
     * Roles of the account; ROLE_MEMBER is always present.
     *
     * @return list<string> The roles
     */
    public function getRoles(): array
    {
        return array_values(array_unique([...$this->roles, self::ROLE_MEMBER]));
    }

    /**
     * Sets the roles of the account.
     *
     * @param list<string> $roles Roles to assign, among {@see User::ASSIGNABLE_ROLES}
     * @return static This account
     * @throws \InvalidArgumentException If a role is not assignable
     */
    public function setRoles(array $roles): static
    {
        foreach ($roles as $role) {
            if (!\in_array($role, self::ASSIGNABLE_ROLES, true)) {
                throw new \InvalidArgumentException(sprintf('Unknown role: "%s".', $role));
            }
        }
        $this->roles = array_values(array_unique($roles));

        return $this;
    }

    /**
     * Keeps the hash out of the session: only its CRC32C fingerprint is serialized, which is enough for
     * Symfony to invalidate the session after a password change.
     *
     * @return array<string, mixed> The properties to serialize
     */
    public function __serialize(): array
    {
        $data = (array) $this;
        $data["\0".self::class."\0password"] = hash('crc32c', $this->password);

        return $data;
    }

    /**
     * Nothing to erase: the plain password is never kept.
     *
     * @deprecated since Symfony 7.3; kept because UserInterface requires it in 7.4
     * @return void
     */
    public function eraseCredentials(): void
    {
    }

    /**
     * Hashed password.
     *
     * @return string The hash, an empty string for a system account
     */
    public function getPassword(): string
    {
        return $this->password;
    }

    /**
     * Sets the hashed password.
     *
     * @param string $hashedPassword Password already hashed by the hasher
     * @return static This account
     */
    public function setPassword(string $hashedPassword): static
    {
        $this->password = $hashedPassword;

        return $this;
    }

    /**
     * Tells whether the account is enabled.
     *
     * @return bool True if the account is active
     */
    public function isActive(): bool
    {
        return $this->active;
    }

    /**
     * Enables or disables the account.
     *
     * @param bool $active True to enable the account
     * @return static This account
     */
    public function setActive(bool $active): static
    {
        $this->active = $active;

        return $this;
    }

    /**
     * Tells whether the account is a non-login system account.
     *
     * @return bool True for a system account
     */
    public function isSystem(): bool
    {
        return $this->system;
    }

    /**
     * Marks the account as a non-login system account.
     *
     * @param bool $system True for a system account
     * @return static This account
     */
    public function setSystem(bool $system): static
    {
        $this->system = $system;

        return $this;
    }

    /**
     * An account can sign in if it is active and is not a system account.
     *
     * @return bool True if the person may sign in
     */
    public function canLogin(): bool
    {
        return $this->active && !$this->system;
    }

    /**
     * Preferred interface language.
     *
     * @return string|null The language code (fr, en), null to follow the browser or the session
     */
    public function getLocale(): ?string
    {
        return $this->locale;
    }

    /**
     * Sets the preferred interface language.
     *
     * @param string|null $locale Language code (fr, en), null to follow the browser or the session
     * @return static This account
     */
    public function setLocale(?string $locale): static
    {
        $this->locale = $locale;

        return $this;
    }

    /**
     * Date of the last successful login.
     *
     * @return \DateTimeImmutable|null The date, null if the person never signed in
     */
    public function getLastLoginAt(): ?\DateTimeImmutable
    {
        return $this->lastLoginAt;
    }
}
