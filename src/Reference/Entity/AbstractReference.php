<?php
/**
 * Base class of the reference entries (name, archive flag, notes, audit)
 * Freely inspired by Plasmi::db 0.9.1 (Mark Brooks) and BioPHP's project biophp.org
 * Created 17 October 2026
 * Last modified 17 October 2026
 */
declare(strict_types=1);

namespace App\Reference\Entity;

use App\Reference\Service\ReferenceName;
use App\Reference\Validator\UniqueReferenceName;
use App\Shared\Audit\AuditableInterface;
use App\Shared\Audit\AuditableTrait;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Entry of a laboratory reference list (organism, strain, marker...).
 *
 * An entry is never deleted from the interface: it is archived (inactive), which hides it
 * from the entry forms but keeps it visible on the objects that already use it.
 * The name is unique through its normalised key, archived entries included.
 *
 * @author Amélie DUVERNET aka Amelaye <amelieonline@gmail.com>
 */
#[ORM\MappedSuperclass]
#[UniqueReferenceName]
abstract class AbstractReference implements AuditableInterface
{
    use AuditableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 100)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    private string $name = '';

    /** Lowercased normalised name, unique per table (see {@see ReferenceName}). */
    #[ORM\Column(type: 'string', length: 200)]
    private string $nameKey = '';

    #[ORM\Column(type: 'boolean')]
    private bool $active = true;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Assert\Length(max: 2000)]
    private ?string $notes = null;

    /**
     * Primary key.
     *
     * @return int|null The identifier, null until the entry is stored
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * Name of the entry as displayed (normalised: NFC, trimmed, single spaces).
     *
     * @return string The display name
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Sets the name: the display form and the unique key (lowercase) are both derived from the text.
     *
     * @param string|null $name Name as typed
     * @return static This entry
     */
    public function setName(?string $name): static
    {
        $this->name = ReferenceName::display($name);
        $this->nameKey = ReferenceName::key($name);

        return $this;
    }

    /**
     * Normalised lowercase key used for the uniqueness of the name.
     *
     * @return string The key, empty while no name is set
     */
    public function getNameKey(): string
    {
        return $this->nameKey;
    }

    /**
     * Tells whether the entry is offered for new entries (not archived).
     *
     * @return bool True if the entry is active
     */
    public function isActive(): bool
    {
        return $this->active;
    }

    /**
     * Archives the entry: it is no longer offered in the forms but stays visible on the objects that use it.
     *
     * @return static This entry
     */
    public function archive(): static
    {
        $this->active = false;

        return $this;
    }

    /**
     * Restores an archived entry.
     *
     * @return static This entry
     */
    public function restore(): static
    {
        $this->active = true;

        return $this;
    }

    /**
     * Free notes.
     *
     * @return string|null The notes, null if none
     */
    public function getNotes(): ?string
    {
        return $this->notes;
    }

    /**
     * Sets the notes; an empty text is stored as null.
     *
     * @param string|null $notes Notes as typed
     * @return static This entry
     */
    public function setNotes(?string $notes): static
    {
        $notes = null === $notes ? null : trim($notes);
        $this->notes = '' === $notes ? null : $notes;

        return $this;
    }

    /**
     * Values whose change alters the meaning of everything that uses the entry.
     *
     * @return array<string, scalar|null> Value by field name
     */
    public function significantValues(): array
    {
        return ['name' => $this->name];
    }
}
